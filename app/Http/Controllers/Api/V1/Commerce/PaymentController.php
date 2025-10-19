<?php

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Http\Controllers\Controller;
use App\Infrastructure\External\{
    StripeService,
    GooglePlayService,
    ApplePayService,
    SendGridService,
    FirebaseService
};
use App\Models\{Payment, PaymentMethod, User};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Auth, DB, Log, Cache, Validator};
use Illuminate\Validation\Rule;

/**
 * PaymentController - Procesamiento de pagos multi-gateway
 * 
 * Servicios externos integrados:
 * - StripeService: Tarjetas crédito/débito, suscripciones web, 3D Secure
 * - GooglePlayService: In-App Purchases Android, RTDN webhooks
 * - ApplePayService: In-App Purchases iOS, receipt validation
 * - SendGridService: Notificaciones email de transacciones
 * - FirebaseService: Push notifications en tiempo real
 * 
 * Características:
 * - Multi-gateway payment processing
 * - Payment methods management (Stripe)
 * - Authorize/Capture flow
 * - Refunds (full/partial)
 * - Mobile IAP verification (iOS/Android)
 * - Fraud detection
 * - Analytics
 * - Webhooks para cada gateway
 * - Notificaciones multi-canal
 * 
 * @package App\Http\Controllers\Api\V1\Commerce
 * @version 1.0.0
 */
class PaymentController extends Controller
{
    protected StripeService $stripe;
    protected GooglePlayService $googlePlay;
    protected ApplePayService $applePay;
    protected SendGridService $sendgrid;
    protected FirebaseService $firebase;

    /**
     * Constructor con inyección de servicios externos
     */
    public function __construct(
        StripeService $stripe,
        GooglePlayService $googlePlay,
        ApplePayService $applePay,
        SendGridService $sendgrid,
        FirebaseService $firebase
    ) {
        $this->stripe = $stripe;
        $this->googlePlay = $googlePlay;
        $this->applePay = $applePay;
        $this->sendgrid = $sendgrid;
        $this->firebase = $firebase;

        $this->middleware('auth:sanctum');
        $this->middleware('throttle:20,1')->only([
            'processPayment',
            'processRefund',
            'verifyGooglePlay',
            'verifyApplePay'
        ]);
        $this->middleware('throttle:60,1')->only([
            'index',
            'show',
            'methods',
            'gateways',
            'analytics'
        ]);
    }

    /**
     * ==========================================
     * PAYMENT HISTORY & DETAILS
     * ==========================================
     */

    /**
     * Get payment history for authenticated user
     * 
     * @OA\Get(
     *     path="/api/v1/commerce/payments",
     *     summary="Get payment history",
     *     tags={"Commerce - Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Payment history retrieved")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|in:pending,completed,failed,refunded,cancelled',
                'gateway' => 'sometimes|in:stripe,google_play,apple_pay',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after_or_equal:start_date',
                'per_page' => 'sometimes|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = Payment::where('user_id', $user->id);

            // Filtros
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('gateway')) {
                $query->where('gateway', $request->input('gateway'));
            }

            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->input('start_date'));
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->input('end_date'));
            }

            $perPage = $request->input('per_page', 20);
            $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Calcular totales
            $totalAmount = Payment::where('user_id', $user->id)
                ->where('status', 'completed')
                ->sum('amount');

            Log::info('Payment history retrieved', [
                'user_id' => $user->id,
                'count' => $payments->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment history retrieved successfully',
                'data' => [
                    'payments' => $payments->items(),
                    'pagination' => [
                        'current_page' => $payments->currentPage(),
                        'last_page' => $payments->lastPage(),
                        'per_page' => $payments->perPage(),
                        'total' => $payments->total()
                    ],
                    'summary' => [
                        'total_amount' => $totalAmount,
                        'total_transactions' => $payments->total()
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve payment history', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment history'
            ], 500);
        }
    }

    /**
     * Get details of a specific payment
     * 
     * @OA\Get(
     *     path="/api/v1/commerce/payments/{paymentId}",
     *     summary="Get payment details",
     *     tags={"Commerce - Payments"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show(int $paymentId): JsonResponse
    {
        $user = Auth::user();

        try {
            $payment = Payment::where('id', $paymentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            Log::info('Payment details retrieved', [
                'user_id' => $user->id,
                'payment_id' => $paymentId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment details retrieved successfully',
                'data' => [
                    'payment' => $payment
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve payment details', [
                'user_id' => $user->id,
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment details'
            ], 500);
        }
    }

    /**
     * ==========================================
     * PAYMENT PROCESSING (MULTI-GATEWAY)
     * ==========================================
     */

    /**
     * Process payment (multi-gateway)
     * 
     * Soporta:
     * - gateway=stripe: Tarjetas web
     * - gateway=google_play: IAP Android
     * - gateway=apple_pay: IAP iOS
     * 
     * @OA\Post(
     *     path="/api/v1/commerce/payments/process",
     *     summary="Process payment",
     *     tags={"Commerce - Payments"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function processPayment(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            // Validación dinámica según gateway
            $validator = Validator::make($request->all(), [
                'gateway' => 'required|in:stripe,google_play,apple_pay',
                
                // Stripe fields
                'amount' => 'required_if:gateway,stripe|numeric|min:0.50',
                'currency' => 'required_if:gateway,stripe|string|size:3',
                'payment_method_id' => 'required_if:gateway,stripe|string',
                
                // Google Play fields
                'product_id' => 'required_unless:gateway,stripe|string',
                'purchase_token' => 'required_if:gateway,google_play|string',
                
                // Apple Pay fields
                'receipt_data' => 'required_if:gateway,apple_pay|string',
                
                // Common fields
                'description' => 'sometimes|string|max:200',
                'metadata' => 'sometimes|array',
                'order_id' => 'sometimes|integer|exists:orders,id',
                'three_d_secure' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                Log::warning('Payment validation failed', [
                    'user_id' => $user->id,
                    'errors' => $validator->errors()->toArray()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $gateway = $request->input('gateway');

            Log::info('Processing payment', [
                'user_id' => $user->id,
                'gateway' => $gateway
            ]);

            DB::beginTransaction();

            // Procesar según gateway
            $result = match($gateway) {
                'stripe' => $this->processStripePayment($user, $request),
                'google_play' => $this->processGooglePlayPurchase($user, $request),
                'apple_pay' => $this->processApplePayPurchase($user, $request),
                default => ['success' => false, 'error' => 'Invalid gateway']
            };

            if (!$result['success']) {
                DB::rollBack();
                
                Log::warning('Payment processing failed', [
                    'user_id' => $user->id,
                    'gateway' => $gateway,
                    'error' => $result['error']
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'error_code' => $result['error_code'] ?? 'PAYMENT_FAILED'
                ], $result['status_code'] ?? 402);
            }

            // Guardar transacción en BD
            $payment = Payment::create([
                'user_id' => $user->id,
                'gateway' => $gateway,
                'amount' => $result['amount'],
                'currency' => $result['currency'] ?? 'USD',
                'status' => $result['status'] ?? 'completed',
                'transaction_id' => $result['transaction_id'],
                'gateway_response' => $result['gateway_response'] ?? null,
                'description' => $request->input('description'),
                'order_id' => $request->input('order_id'),
                'metadata' => array_merge(
                    $request->input('metadata', []),
                    ['client_secret' => $result['client_secret'] ?? null]
                ),
                'processed_at' => now()
            ]);

            DB::commit();

            // Invalidar cache
            Cache::forget("user_payments:{$user->id}");

            // Notificaciones asíncronas (email + push)
            $this->sendPaymentNotifications($user, $payment);

            Log::info('Payment processed successfully', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'gateway' => $gateway,
                'amount' => $result['amount']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'transaction_id' => $result['transaction_id'],
                    'amount' => $result['amount'],
                    'currency' => $result['currency'] ?? 'USD',
                    'gateway' => $gateway,
                    'status' => $payment->status,
                    'client_secret' => $result['client_secret'] ?? null,
                    'requires_action' => $result['requires_action'] ?? false,
                    'next_action' => $result['next_action'] ?? null
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment processing error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Procesar pago con Stripe (interno)
     */
    protected function processStripePayment(User $user, Request $request): array
    {
        try {
            // 1. Crear o recuperar Stripe Customer
            $customerResult = $this->stripe->createCustomer([
                'email' => $user->email,
                'name' => $user->name ?? $user->username,
                'phone' => $user->phone,
                'metadata' => [
                    'user_id' => $user->id,
                    'username' => $user->username
                ]
            ]);

            if (!$customerResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to create Stripe customer',
                    'error_code' => 'STRIPE_CUSTOMER_FAILED'
                ];
            }

            // Guardar Stripe customer ID
            if (!$user->stripe_customer_id) {
                $user->update(['stripe_customer_id' => $customerResult['customer_id']]);
            }

            // 2. Crear Payment Intent
            $intentResult = $this->stripe->createPaymentIntent([
                'amount' => $request->input('amount'),
                'currency' => strtolower($request->input('currency')),
                'customer_id' => $customerResult['customer_id'],
                'payment_method' => $request->input('payment_method_id'),
                'description' => $request->input('description'),
                'metadata' => array_merge(
                    $request->input('metadata', []),
                    [
                        'user_id' => $user->id,
                        'order_id' => $request->input('order_id')
                    ]
                ),
                'three_d_secure' => $request->boolean('three_d_secure', true)
            ]);

            if (!$intentResult['success']) {
                return [
                    'success' => false,
                    'error' => $intentResult['error'],
                    'error_code' => 'STRIPE_INTENT_FAILED',
                    'stripe_code' => $intentResult['stripe_code'] ?? null
                ];
            }

            Log::info('Stripe payment intent created', [
                'user_id' => $user->id,
                'intent_id' => $intentResult['payment_intent_id'],
                'status' => $intentResult['status']
            ]);

            return [
                'success' => true,
                'transaction_id' => $intentResult['payment_intent_id'],
                'amount' => $intentResult['amount'],
                'currency' => $intentResult['currency'],
                'status' => $intentResult['status'] === 'succeeded' ? 'completed' : 'pending',
                'client_secret' => $intentResult['client_secret'],
                'requires_action' => $intentResult['requires_action'],
                'next_action' => $intentResult['next_action'],
                'gateway_response' => $intentResult
            ];

        } catch (\Exception $e) {
            Log::error('Stripe payment processing error', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Stripe payment failed: ' . $e->getMessage(),
                'error_code' => 'STRIPE_ERROR'
            ];
        }
    }

    /**
     * Procesar compra Google Play (interno)
     */
    protected function processGooglePlayPurchase(User $user, Request $request): array
    {
        try {
            $productId = $request->input('product_id');
            $purchaseToken = $request->input('purchase_token');

            Log::info('Verifying Google Play purchase', [
                'user_id' => $user->id,
                'product_id' => $productId
            ]);

            // 1. Verificar compra en Google Play
            $verifyResult = $this->googlePlay->verifyPurchase($productId, $purchaseToken);

            if (!$verifyResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Google Play verification failed',
                    'error_code' => 'GOOGLE_PLAY_VERIFICATION_FAILED'
                ];
            }

            if (!$verifyResult['is_valid']) {
                return [
                    'success' => false,
                    'error' => 'Invalid Google Play purchase',
                    'error_code' => 'GOOGLE_PLAY_INVALID_PURCHASE'
                ];
            }

            // 2. Acknowledge purchase (confirmar recepción)
            $ackResult = $this->googlePlay->acknowledgePurchase($productId, $purchaseToken);

            if (!$ackResult['success']) {
                Log::warning('Failed to acknowledge Google Play purchase', [
                    'user_id' => $user->id,
                    'product_id' => $productId
                ]);
            }

            // 3. Obtener precio del producto desde catálogo (simplificado)
            $productPrice = $this->getProductPrice($productId); // Implementar según catálogo

            Log::info('Google Play purchase verified', [
                'user_id' => $user->id,
                'order_id' => $verifyResult['order_id']
            ]);

            return [
                'success' => true,
                'transaction_id' => $verifyResult['order_id'],
                'amount' => $productPrice['amount'],
                'currency' => $productPrice['currency'],
                'status' => 'completed',
                'gateway_response' => $verifyResult
            ];

        } catch (\Exception $e) {
            Log::error('Google Play purchase processing error', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Google Play purchase failed: ' . $e->getMessage(),
                'error_code' => 'GOOGLE_PLAY_ERROR'
            ];
        }
    }

    /**
     * Procesar compra Apple Pay (interno)
     */
    protected function processApplePayPurchase(User $user, Request $request): array
    {
        try {
            $receiptData = $request->input('receipt_data');
            $productId = $request->input('product_id');

            Log::info('Verifying Apple receipt', [
                'user_id' => $user->id,
                'product_id' => $productId
            ]);

            // 1. Verificar receipt
            $verifyResult = $this->applePay->verifyReceipt($receiptData);

            if (!$verifyResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Apple receipt verification failed: ' . $verifyResult['status_message'],
                    'error_code' => 'APPLE_PAY_VERIFICATION_FAILED',
                    'apple_status' => $verifyResult['status']
                ];
            }

            // 2. Extraer transaction del receipt
            $latestReceipt = $verifyResult['latest_receipt_info'][0] ?? null;
            
            if (!$latestReceipt) {
                return [
                    'success' => false,
                    'error' => 'No transaction found in Apple receipt',
                    'error_code' => 'APPLE_PAY_NO_TRANSACTION'
                ];
            }

            // Verificar que el product_id coincida
            if (($latestReceipt['product_id'] ?? '') !== $productId) {
                return [
                    'success' => false,
                    'error' => 'Product ID mismatch in receipt',
                    'error_code' => 'APPLE_PAY_PRODUCT_MISMATCH'
                ];
            }

            // 3. Obtener precio del producto desde catálogo
            $productPrice = $this->getProductPrice($productId);

            Log::info('Apple receipt verified', [
                'user_id' => $user->id,
                'transaction_id' => $latestReceipt['transaction_id']
            ]);

            return [
                'success' => true,
                'transaction_id' => $latestReceipt['transaction_id'],
                'amount' => $productPrice['amount'],
                'currency' => $productPrice['currency'],
                'status' => 'completed',
                'gateway_response' => $verifyResult
            ];

        } catch (\Exception $e) {
            Log::error('Apple Pay purchase processing error', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Apple Pay purchase failed: ' . $e->getMessage(),
                'error_code' => 'APPLE_PAY_ERROR'
            ];
        }
    }

    /**
     * ==========================================
     * STRIPE-SPECIFIC OPERATIONS
     * ==========================================
     */

    /**
     * Authorize payment without capturing (Stripe only)
     */
    public function authorizeStripe(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0.50',
                'currency' => 'required|string|size:3',
                'payment_method_id' => 'required|string',
                'description' => 'sometimes|string|max:200'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Crear Payment Intent sin capturar
            $intentResult = $this->stripe->createPaymentIntent([
                'amount' => $request->input('amount'),
                'currency' => $request->input('currency'),
                'payment_method' => $request->input('payment_method_id'),
                'description' => $request->input('description'),
                'capture_method' => 'manual', // Authorize only
                'metadata' => [
                    'user_id' => $user->id
                ]
            ]);

            if (!$intentResult['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $intentResult['error']
                ], 402);
            }

            // Guardar autorización en BD
            $payment = Payment::create([
                'user_id' => $user->id,
                'gateway' => 'stripe',
                'amount' => $intentResult['amount'],
                'currency' => $intentResult['currency'],
                'status' => 'authorized',
                'transaction_id' => $intentResult['payment_intent_id'],
                'description' => $request->input('description'),
                'processed_at' => now()
            ]);

            DB::commit();

            Log::info('Payment authorized', [
                'user_id' => $user->id,
                'payment_id' => $payment->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment authorized successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'authorization_id' => $intentResult['payment_intent_id'],
                    'amount' => $intentResult['amount'],
                    'expires_at' => now()->addDays(7)->toIso8601String()
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment authorization failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authorization failed'
            ], 500);
        }
    }

    /**
     * Capture previously authorized payment (Stripe only)
     */
    public function captureStripe(int $authorizationId): JsonResponse
    {
        $user = Auth::user();

        try {
            $payment = Payment::where('id', $authorizationId)
                ->where('user_id', $user->id)
                ->where('status', 'authorized')
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization not found'
                ], 404);
            }

            DB::beginTransaction();

            // Capturar en Stripe
            $captureResult = $this->stripe->capturePaymentIntent($payment->transaction_id);

            if (!$captureResult['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $captureResult['error']
                ], 402);
            }

            // Actualizar estado
            $payment->update([
                'status' => 'completed',
                'captured_at' => now()
            ]);

            DB::commit();

            // Notificaciones
            $this->sendPaymentNotifications($user, $payment);

            Log::info('Payment captured', [
                'user_id' => $user->id,
                'payment_id' => $payment->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment captured successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'amount' => $captureResult['captured_amount']
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment capture failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Capture failed'
            ], 500);
        }
    }

    /**
     * Process refund (Stripe only for now)
     */
    public function refundStripe(Request $request, int $paymentId): JsonResponse
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'sometimes|numeric|min:0.01',
                'reason' => 'required|string|max:200',
                'reason_code' => 'sometimes|in:duplicate,fraudulent,requested_by_customer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payment = Payment::where('id', $paymentId)
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found or cannot be refunded'
                ], 404);
            }

            if ($payment->gateway !== 'stripe') {
                return response()->json([
                    'success' => false,
                    'message' => 'Refunds only available for Stripe payments'
                ], 422);
            }

            DB::beginTransaction();

            // Crear refund en Stripe
            $refundResult = $this->stripe->createRefund(
                $payment->transaction_id,
                $request->input('amount'),
                $request->input('reason_code')
            );

            if (!$refundResult['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $refundResult['error']
                ], 402);
            }

            // Actualizar payment
            $payment->update([
                'status' => 'refunded',
                'refunded_amount' => $refundResult['amount'],
                'refunded_at' => now(),
                'refund_reason' => $request->input('reason')
            ]);

            DB::commit();

            // Notificación de refund
            try {
                $this->sendgrid->sendEmail([
                    'to' => $user->email,
                    'subject' => 'Refund Processed - ForeverUsInLove',
                    'html' => view('emails.payment.refund', [
                        'user' => $user,
                        'payment' => $payment,
                        'refund_amount' => $refundResult['amount']
                    ])->render()
                ]);

                $this->firebase->sendToUser($user->id, [
                    'title' => '💰 Refund Processed',
                    'body' => sprintf('Refund of $%.2f processed', $refundResult['amount']),
                    'data' => [
                        'type' => 'payment_refunded',
                        'payment_id' => $payment->id
                    ]
                ]);
            } catch (\Exception $e) {
                Log::warning('Refund notification failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
            }

            Log::info('Payment refunded', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'amount' => $refundResult['amount']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => [
                    'refund_id' => $refundResult['refund_id'],
                    'amount' => $refundResult['amount'],
                    'status' => $refundResult['status'],
                    'processing_time' => '3-5 business days'
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Refund processing failed', [
                'user_id' => $user->id,
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Refund failed'
            ], 500);
        }
    }

    /**
     * ==========================================
     * PAYMENT METHODS MANAGEMENT (STRIPE)
     * ==========================================
     */

    /**
     * Get all payment methods for user
     */
    public function methods(): JsonResponse
    {
        $user = Auth::user();

        try {
            if (!$user->stripe_customer_id) {
                return response()->json([
                    'success' => true,
                    'message' => 'No payment methods found',
                    'data' => [
                        'payment_methods' => [],
                        'default_method_id' => null
                    ]
                ], 200);
            }

            // Listar desde Stripe
            $listResult = $this->stripe->listPaymentMethods($user->stripe_customer_id);

            if (!$listResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve payment methods'
                ], 500);
            }

            $methods = PaymentMethod::where('user_id', $user->id)
                ->where('is_deleted', false)
                ->get();

            Log::info('Payment methods retrieved', [
                'user_id' => $user->id,
                'count' => count($listResult['payment_methods'])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment methods retrieved successfully',
                'data' => [
                    'payment_methods' => $listResult['payment_methods'],
                    'default_method_id' => $methods->where('is_default', true)->first()?->id
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve payment methods', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment methods'
            ], 500);
        }
    }

    /**
     * Add new payment method
     */
    public function addMethod(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                'payment_method_id' => 'required|string',
                'set_as_default' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Crear customer si no existe
            if (!$user->stripe_customer_id) {
                $customerResult = $this->stripe->createCustomer([
                    'email' => $user->email,
                    'name' => $user->name ?? $user->username
                ]);

                if (!$customerResult['success']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to create customer'
                    ], 500);
                }

                $user->update(['stripe_customer_id' => $customerResult['customer_id']]);
            }

            // Attach payment method
            $attachResult = $this->stripe->attachPaymentMethod(
                $request->input('payment_method_id'),
                $user->stripe_customer_id
            );

            if (!$attachResult['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $attachResult['error']
                ], 402);
            }

            // Guardar en BD
            if ($request->boolean('set_as_default')) {
                PaymentMethod::where('user_id', $user->id)
                    ->update(['is_default' => false]);
            }

            $paymentMethod = PaymentMethod::create([
                'user_id' => $user->id,
                'gateway' => 'stripe',
                'gateway_payment_method_id' => $attachResult['payment_method']['id'],
                'type' => $attachResult['payment_method']['type'],
                'card_brand' => $attachResult['payment_method']['card']['brand'] ?? null,
                'card_last4' => $attachResult['payment_method']['card']['last4'] ?? null,
                'card_exp_month' => $attachResult['payment_method']['card']['exp_month'] ?? null,
                'card_exp_year' => $attachResult['payment_method']['card']['exp_year'] ?? null,
                'is_default' => $request->boolean('set_as_default', false)
            ]);

            DB::commit();

            Log::info('Payment method added', [
                'user_id' => $user->id,
                'method_id' => $paymentMethod->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment method added successfully',
                'data' => [
                    'payment_method' => $paymentMethod
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to add payment method', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add payment method'
            ], 500);
        }
    }

    /**
     * Update payment method
     */
    public function updateMethod(Request $request, int $methodId): JsonResponse
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                'set_as_default' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $method = PaymentMethod::where('id', $methodId)
                ->where('user_id', $user->id)
                ->first();

            if (!$method) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment method not found'
                ], 404);
            }

            DB::beginTransaction();

            if ($request->boolean('set_as_default')) {
                PaymentMethod::where('user_id', $user->id)
                    ->update(['is_default' => false]);
                
                $method->update(['is_default' => true]);
            }

            DB::commit();

            Log::info('Payment method updated', [
                'user_id' => $user->id,
                'method_id' => $methodId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment method updated successfully',
                'data' => [
                    'payment_method' => $method->fresh()
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update payment method', [
                'user_id' => $user->id,
                'method_id' => $methodId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment method'
            ], 500);
        }
    }

    /**
     * Delete payment method
     */
    public function deleteMethod(int $methodId): JsonResponse
    {
        $user = Auth::user();

        try {
            $method = PaymentMethod::where('id', $methodId)
                ->where('user_id', $user->id)
                ->first();

            if (!$method) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment method not found'
                ], 404);
            }

            DB::beginTransaction();

            // Detach from Stripe
            $detachResult = $this->stripe->detachPaymentMethod($method->gateway_payment_method_id);

            if (!$detachResult['success']) {
                Log::warning('Failed to detach from Stripe, continuing with DB delete', [
                    'method_id' => $methodId,
                    'error' => $detachResult['error']
                ]);
            }

            // Soft delete
            $method->update(['is_deleted' => true]);

            DB::commit();

            Log::info('Payment method deleted', [
                'user_id' => $user->id,
                'method_id' => $methodId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment method deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete payment method', [
                'user_id' => $user->id,
                'method_id' => $methodId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment method'
            ], 500);
        }
    }

    /**
     * ==========================================
     * MOBILE IAP VERIFICATION
     * ==========================================
     */

    /**
     * Verify Google Play purchase (endpoint alternativo)
     */
    public function verifyGooglePlay(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|string',
                'purchase_token' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $verifyResult = $this->googlePlay->verifyPurchase(
                $request->input('product_id'),
                $request->input('purchase_token')
            );

            return response()->json([
                'success' => $verifyResult['success'],
                'message' => $verifyResult['success'] ? 'Purchase verified' : 'Verification failed',
                'data' => $verifyResult
            ], $verifyResult['success'] ? 200 : 402);

        } catch (\Exception $e) {
            Log::error('Google Play verification error', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification failed'
            ], 500);
        }
    }

    /**
     * Verify Apple Pay receipt (endpoint alternativo)
     */
    public function verifyApplePay(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                'receipt_data' => 'required|string',
                'product_id' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $verifyResult = $this->applePay->verifyReceipt(
                $request->input('receipt_data')
            );

            return response()->json([
                'success' => $verifyResult['success'],
                'message' => $verifyResult['success'] ? 'Receipt verified' : 'Verification failed',
                'data' => $verifyResult
            ], $verifyResult['success'] ? 200 : 402);

        } catch (\Exception $e) {
            Log::error('Apple Pay verification error', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification failed'
            ], 500);
        }
    }

    /**
     * Check Apple subscription status
     */
    public function appleSubscriptionStatus(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                'receipt_data' => 'required|string',
                'subscription_id' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $statusResult = $this->applePay->verifySubscription(
                $request->input('receipt_data'),
                $request->input('subscription_id')
            );

            return response()->json([
                'success' => $statusResult['success'],
                'message' => 'Subscription status retrieved',
                'data' => $statusResult
            ], 200);

        } catch (\Exception $e) {
            Log::error('Apple subscription status error', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get subscription status'
            ], 500);
        }
    }

    /**
     * ==========================================
     * GATEWAYS & ANALYTICS
     * ==========================================
     */

    /**
     * Get available payment gateways
     */
    public function gateways(): JsonResponse
    {
        try {
            $gateways = [
                'stripe' => [
                    'name' => 'Credit/Debit Card',
                    'enabled' => $this->stripe->isEnabled(),
                    'methods' => ['card', 'bank_account'],
                    'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
                    'fees' => [
                        'domestic' => '2.9% + $0.30 per transaction',
                        'international' => '3.9% + $0.30 per transaction',
                        'currency_conversion' => '+1%'
                    ],
                    'features' => [
                        '3d_secure' => true,
                        'subscriptions' => true,
                        'refunds' => true,
                        'authorize_capture' => true
                    ]
                ],
                'google_play' => [
                    'name' => 'Google Play (Android)',
                    'enabled' => $this->googlePlay->isEnabled(),
                    'methods' => ['in_app_purchase'],
                    'currencies' => ['USD', 'EUR', 'GBP'],
                    'fees' => [
                        'standard' => '30% (after $1M: 15%)',
                        'small_business' => '15% (under $1M/year)',
                        'subscriptions' => '15% (after 12 months)'
                    ],
                    'features' => [
                        'rtdn_webhooks' => true,
                        'subscriptions' => true,
                        'refunds' => false // Managed by Google
                    ]
                ],
                'apple_pay' => [
                    'name' => 'App Store (iOS)',
                    'enabled' => $this->applePay->isEnabled(),
                    'methods' => ['in_app_purchase'],
                    'currencies' => ['USD', 'EUR', 'GBP'],
                    'fees' => [
                        'standard' => '30%',
                        'small_business' => '15% (under $1M/year)',
                        'subscriptions' => '15% (after 1 year)'
                    ],
                    'features' => [
                        'server_notifications' => true,
                        'subscriptions' => true,
                        'refunds' => false // Managed by Apple
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Payment gateways retrieved successfully',
                'data' => ['gateways' => $gateways]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve gateways', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve gateways'
            ], 500);
        }
    }

    /**
     * Get payment analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                'period' => 'sometimes|in:7d,30d,90d,1y,all'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $period = $request->input('period', '30d');
            $startDate = $this->getPeriodStartDate($period);

            $query = Payment::where('user_id', $user->id);
            
            if ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }

            $totalSpent = $query->where('status', 'completed')->sum('amount');
            $totalTransactions = $query->count();
            $successfulPayments = $query->where('status', 'completed')->count();
            $failedPayments = $query->where('status', 'failed')->count();
            $refundedAmount = $query->where('status', 'refunded')->sum('refunded_amount');

            // Spending by gateway
            $spendingByGateway = Payment::where('user_id', $user->id)
                ->where('status', 'completed')
                ->selectRaw('gateway, SUM(amount) as total, COUNT(*) as count')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->groupBy('gateway')
                ->get();

            // Spending trends (últimos 12 meses)
            $spendingTrends = Payment::where('user_id', $user->id)
                ->where('status', 'completed')
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(amount) as total')
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('month')
                ->orderBy('month', 'asc')
                ->get();

            Log::info('Payment analytics retrieved', [
                'user_id' => $user->id,
                'period' => $period
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment analytics retrieved successfully',
                'data' => [
                    'total_spent' => $totalSpent,
                    'total_transactions' => $totalTransactions,
                    'successful_payments' => $successfulPayments,
                    'failed_payments' => $failedPayments,
                    'refunded_amount' => $refundedAmount,
                    'success_rate' => $totalTransactions > 0 
                        ? round(($successfulPayments / $totalTransactions) * 100, 2) 
                        : 0,
                    'spending_by_gateway' => $spendingByGateway,
                    'spending_trends' => $spendingTrends,
                    'preferred_gateway' => $spendingByGateway->sortByDesc('total')->first()?->gateway
                ],
                'meta' => [
                    'period' => $period,
                    'start_date' => $startDate?->toIso8601String(),
                    'generated_at' => now()->toIso8601String()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve payment analytics', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analytics'
            ], 500);
        }
    }

    /**
     * ==========================================
     * FRAUD CHECK
     * ==========================================
     */

    /**
     * Check payment for fraud patterns
     */
    public function fraudCheck(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0',
                'currency' => 'required|string|size:3',
                'payment_method_id' => 'sometimes|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Análisis simple de fraude (expandir con Stripe Radar)
            $flags = [];
            $riskScore = 0;

            // Check 1: Cantidad sospechosa
            if ($request->input('amount') > 1000) {
                $flags[] = 'high_amount';
                $riskScore += 20;
            }

            // Check 2: Múltiples intentos fallidos recientes
            $recentFailed = Payment::where('user_id', $user->id)
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            if ($recentFailed >= 3) {
                $flags[] = 'multiple_failed_attempts';
                $riskScore += 30;
            }

            // Check 3: Frecuencia de transacciones
            $recentPayments = Payment::where('user_id', $user->id)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($recentPayments >= 5) {
                $flags[] = 'high_frequency';
                $riskScore += 25;
            }

            // Check 4: Cuenta nueva
            $accountAge = now()->diffInDays($user->created_at);
            if ($accountAge < 7) {
                $flags[] = 'new_account';
                $riskScore += 15;
            }

            // Determinar nivel de riesgo
            $riskLevel = match(true) {
                $riskScore >= 70 => 'high',
                $riskScore >= 40 => 'medium',
                default => 'low'
            };

            $isSafe = $riskScore < 40;

            $recommendations = [];
            if (!$isSafe) {
                $recommendations[] = 'Require 3D Secure authentication';
                $recommendations[] = 'Manual review recommended';
                if (in_array('high_frequency', $flags)) {
                    $recommendations[] = 'Apply rate limiting';
                }
            }

            Log::info('Fraud check completed', [
                'user_id' => $user->id,
                'risk_score' => $riskScore,
                'risk_level' => $riskLevel
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Fraud check completed',
                'data' => [
                    'risk_score' => $riskScore,
                    'risk_level' => $riskLevel,
                    'is_safe' => $isSafe,
                    'flags' => $flags,
                    'recommendations' => $recommendations
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Fraud check failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fraud check failed'
            ], 500);
        }
    }

    /**
     * ==========================================
     * WEBHOOKS (SIN AUTENTICACIÓN)
     * ==========================================
     */

    /**
     * Stripe webhook handler
     */
    public function stripeWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('Stripe-Signature');

            // Verificar firma
            if (!$this->stripe->verifyWebhookSignature($payload, $signature)) {
                Log::warning('Invalid Stripe webhook signature');
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $event = json_decode($payload, true);
            $eventType = $event['type'] ?? 'unknown';

            Log::info('Stripe webhook received', [
                'event_type' => $eventType
            ]);

            // Procesar eventos
            match($eventType) {
                'payment_intent.succeeded' => $this->handlePaymentSucceeded($event['data']['object']),
                'payment_intent.payment_failed' => $this->handlePaymentFailed($event['data']['object']),
                'charge.refunded' => $this->handleChargeRefunded($event['data']['object']),
                default => Log::info('Unhandled Stripe event', ['type' => $eventType])
            };

            return response()->json(['received' => true], 200);

        } catch (\Exception $e) {
            Log::error('Stripe webhook error', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Google Play webhook handler
     */
    public function googlePlayWebhook(Request $request): JsonResponse
    {
        try {
            $notificationData = $request->input('message.data');
            
            $rtdnResult = $this->googlePlay->verifyRTDN($notificationData);

            if (!$rtdnResult['success']) {
                return response()->json(['error' => 'Invalid RTDN'], 400);
            }

            $notification = $rtdnResult['notification'];
            $notificationType = $notification['notificationType'] ?? 0;

            Log::info('Google Play RTDN received', [
                'notification_type' => $notificationType
            ]);

            // Procesar según tipo
            // 1 = SUBSCRIPTION_PURCHASED, 2 = SUBSCRIPTION_CANCELED, etc.

            return response()->json(['received' => true], 200);

        } catch (\Exception $e) {
            Log::error('Google Play webhook error', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Apple Pay webhook handler
     */
    public function applePayWebhook(Request $request): JsonResponse
    {
        try {
            $notificationData = $request->all();

            $notificationResult = $this->applePay->processServerNotification($notificationData);

            if (!$notificationResult['success']) {
                return response()->json(['error' => 'Invalid notification'], 400);
            }

            Log::info('Apple server notification received', [
                'notification_type' => $notificationResult['notification_type']
            ]);

            return response()->json(['received' => true], 200);

        } catch (\Exception $e) {
            Log::error('Apple webhook error', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * ==========================================
     * HELPER METHODS
     * ==========================================
     */

    /**
     * Enviar notificaciones de pago (email + push)
     */
    protected function sendPaymentNotifications(User $user, Payment $payment): void
    {
        try {
            // Email notification
            $this->sendgrid->sendEmail([
                'to' => $user->email,
                'subject' => 'Payment Confirmation - ForeverUsInLove',
                'html' => view('emails.payment.confirmation', [
                    'user' => $user,
                    'payment' => $payment
                ])->render()
            ]);

            // Push notification
            $this->firebase->sendToUser($user->id, [
                'title' => '✅ Payment Successful',
                'body' => sprintf('Payment of $%.2f completed', $payment->amount),
                'data' => [
                    'type' => 'payment_completed',
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency
                ]
            ]);

        } catch (\Exception $e) {
            Log::warning('Payment notifications failed', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener precio de producto desde catálogo (mock)
     */
    protected function getProductPrice(string $productId): array
    {
        // TODO: Implementar catálogo real de productos
        $catalog = [
            'premium_1month' => ['amount' => 9.99, 'currency' => 'USD'],
            'premium_3months' => ['amount' => 24.99, 'currency' => 'USD'],
            'premium_12months' => ['amount' => 79.99, 'currency' => 'USD'],
            'coins_100' => ['amount' => 4.99, 'currency' => 'USD'],
            'coins_500' => ['amount' => 19.99, 'currency' => 'USD'],
        ];

        return $catalog[$productId] ?? ['amount' => 0, 'currency' => 'USD'];
    }

    /**
     * Obtener fecha de inicio según período
     */
    protected function getPeriodStartDate(string $period): ?\Carbon\Carbon
    {
        return match($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            'all' => null,
            default => now()->subDays(30)
        };
    }

    /**
     * Handle payment succeeded webhook
     */
    protected function handlePaymentSucceeded(array $paymentIntent): void
    {
        $payment = Payment::where('transaction_id', $paymentIntent['id'])->first();
        
        if ($payment && $payment->status !== 'completed') {
            $payment->update([
                'status' => 'completed',
                'processed_at' => now()
            ]);

            Log::info('Payment marked as completed via webhook', [
                'payment_id' => $payment->id
            ]);
        }
    }

    /**
     * Handle payment failed webhook
     */
    protected function handlePaymentFailed(array $paymentIntent): void
    {
        $payment = Payment::where('transaction_id', $paymentIntent['id'])->first();
        
        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $paymentIntent['last_payment_error']['message'] ?? 'Unknown error'
            ]);

            Log::info('Payment marked as failed via webhook', [
                'payment_id' => $payment->id
            ]);
        }
    }

    /**
     * Handle charge refunded webhook
     */
    protected function handleChargeRefunded(array $charge): void
    {
        // Buscar payment por charge ID
        $payment = Payment::where('gateway_response->charge_id', $charge['id'])->first();
        
        if ($payment && $payment->status !== 'refunded') {
            $payment->update([
                'status' => 'refunded',
                'refunded_amount' => $charge['amount_refunded'] / 100,
                'refunded_at' => now()
            ]);

            Log::info('Payment marked as refunded via webhook', [
                'payment_id' => $payment->id
            ]);
        }
    }
}
