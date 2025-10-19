<?php

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Http\Controllers\Controller;
use App\Domain\Commerce\Services\OrderService;
use App\Http\Requests\Commerce\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class OrderController
 * 
 * Handles all order-related operations in the ForeverUsInLove platform.
 * Manages order creation, processing, fulfillment, and analytics.
 * 
 * @package App\Http\Controllers\Api\V1\Commerce
 * @version 1.0.0
 */
class OrderController extends Controller
{
    /**
     * @var OrderService
     */
    protected OrderService $orderService;

    /**
     * OrderController constructor.
     *
     * @param OrderService $orderService
     */
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:30,1')->only(['store', 'cancel']);
        $this->middleware('throttle:100,1')->only(['index', 'show']);
    }

    /**
     * Get order history for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/orders",
     *     summary="Get order history",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (pending, processing, fulfilled, completed, cancelled, refunded)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="order_type",
     *         in="query",
     *         description="Filter by type (gift, subscription, coins, feature, boost)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date filter",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date filter",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(response=200, description="Order history retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $filters = [
                'status' => $request->input('status'),
                'order_type' => $request->input('order_type'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'per_page' => $request->input('per_page', 20),
            ];

            $orders = $this->orderService->getUserOrders($userId, $filters);

            return response()->json([
                'status' => 'success',
                'message' => 'Order history retrieved successfully',
                'data' => [
                    'orders' => $orders['orders'],
                    'total' => $orders['total'],
                    'total_amount' => $orders['total_amount'],
                    'current_page' => $orders['current_page'],
                    'last_page' => $orders['last_page'],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve order history', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve order history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get details of a specific order.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/orders/{orderId}",
     *     summary="Get order details",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Order details retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     *
     * @param int $orderId
     * @return JsonResponse
     */
    public function show(int $orderId): JsonResponse
    {
        try {
            $userId = Auth::id();

            $order = $this->orderService->getOrderDetails($userId, $orderId);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Order details retrieved successfully',
                'data' => [
                    'order' => $order,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve order details', [
                'user_id' => $userId ?? null,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve order details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Create a new order.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/orders",
     *     summary="Create an order",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_type", "items"},
     *             @OA\Property(property="order_type", type="string", example="gift"),
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 @OA\Property(property="product_id", type="integer", example=1),
     *                 @OA\Property(property="quantity", type="integer", example=2),
     *                 @OA\Property(property="price", type="number", format="float", example=9.99)
     *             )),
     *             @OA\Property(property="payment_method_id", type="integer", example=1),
     *             @OA\Property(property="billing_address", type="object", example={
     *                 "line1": "123 Main St",
     *                 "city": "New York",
     *                 "state": "NY",
     *                 "postal_code": "10001",
     *                 "country": "US"
     *             }),
     *             @OA\Property(property="coupon_code", type="string", example="SAVE10"),
     *             @OA\Property(property="notes", type="string", example="Gift wrapping requested")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Order created successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=400, description="Order creation failed")
     * )
     *
     * @param PurchaseRequest $request
     * @return JsonResponse
     */
    public function store(PurchaseRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $orderData = [
                'user_id' => $userId,
                'order_type' => $request->input('order_type'),
                'items' => $request->input('items'),
                'payment_method_id' => $request->input('payment_method_id'),
                'billing_address' => $request->input('billing_address'),
                'coupon_code' => $request->input('coupon_code'),
                'notes' => $request->input('notes'),
            ];

            // Create order through domain service
            $result = $this->orderService->createOrder($orderData);

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'error_code' => $result['error_code'],
                ], $result['status_code'] ?? 400);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order created successfully',
                'data' => [
                    'order' => $result['order'],
                    'order_number' => $result['order_number'],
                    'total_amount' => $result['total_amount'],
                    'discount_applied' => $result['discount_applied'],
                    'estimated_delivery' => $result['estimated_delivery'] ?? null,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create order', [
                'user_id' => $userId ?? null,
                'order_type' => $request->input('order_type'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create order',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Process a pending order (payment and fulfillment).
     *
     * @OA\Post(
     *     path="/api/v1/commerce/orders/{orderId}/process",
     *     summary="Process an order",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Order processed successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Order not found"),
     *     @OA\Response(response=400, description="Order cannot be processed")
     * )
     *
     * @param int $orderId
     * @return JsonResponse
     */
    public function process(int $orderId): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();

            $result = $this->orderService->processOrder($userId, $orderId);

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'error_code' => $result['error_code'],
                ], $result['status_code'] ?? 400);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order processed successfully',
                'data' => [
                    'order' => $result['order'],
                    'payment' => $result['payment'],
                    'fulfillment_status' => $result['fulfillment_status'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to process order', [
                'user_id' => $userId ?? null,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process order',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Fulfill an order (mark as delivered/completed).
     *
     * @OA\Post(
     *     path="/api/v1/commerce/orders/{orderId}/fulfill",
     *     summary="Fulfill an order",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="tracking_number", type="string", example="TRACK123456"),
     *             @OA\Property(property="fulfillment_notes", type="string", example="Delivered successfully")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Order fulfilled successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Order not found"),
     *     @OA\Response(response=400, description="Order cannot be fulfilled")
     * )
     *
     * @param Request $request
     * @param int $orderId
     * @return JsonResponse
     */
    public function fulfill(Request $request, int $orderId): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $fulfillmentData = [
                'tracking_number' => $request->input('tracking_number'),
                'fulfillment_notes' => $request->input('fulfillment_notes'),
            ];

            $result = $this->orderService->fulfillOrder($userId, $orderId, $fulfillmentData);

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], $result['status_code'] ?? 400);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order fulfilled successfully',
                'data' => [
                    'order' => $result['order'],
                    'fulfilled_at' => $result['fulfilled_at'],
                    'fulfillment_details' => $result['fulfillment_details'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to fulfill order', [
                'user_id' => $userId ?? null,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fulfill order',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Complete an order (final status).
     *
     * @OA\Post(
     *     path="/api/v1/commerce/orders/{orderId}/complete",
     *     summary="Complete an order",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="customer_rating", type="integer", example=5),
     *             @OA\Property(property="customer_feedback", type="string", example="Excellent service!")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Order completed successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     *
     * @param Request $request
     * @param int $orderId
     * @return JsonResponse
     */
    public function complete(Request $request, int $orderId): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $completionData = [
                'customer_rating' => $request->input('customer_rating'),
                'customer_feedback' => $request->input('customer_feedback'),
            ];

            $result = $this->orderService->completeOrder($userId, $orderId, $completionData);

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], $result['status_code'] ?? 400);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order completed successfully',
                'data' => [
                    'order' => $result['order'],
                    'completed_at' => $result['completed_at'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to complete order', [
                'user_id' => $userId ?? null,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to complete order',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Cancel an order.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/orders/{orderId}/cancel",
     *     summary="Cancel an order",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Changed my mind"),
     *             @OA\Property(property="request_refund", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Order cancelled successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Order not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     *
     * @param Request $request
     * @param int $orderId
     * @return JsonResponse
     */
    public function cancel(Request $request, int $orderId): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $cancelData = [
                'reason' => $request->input('reason'),
                'request_refund' => $request->boolean('request_refund', true),
            ];

            $result = $this->orderService->cancelOrder($userId, $orderId, $cancelData);

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'error_code' => $result['error_code'],
                ], $result['status_code'] ?? 400);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order cancelled successfully',
                'data' => [
                    'order' => $result['order'],
                    'cancelled_at' => $result['cancelled_at'],
                    'refund_status' => $result['refund_status'],
                    'refund_amount' => $result['refund_amount'] ?? null,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to cancel order', [
                'user_id' => $userId ?? null,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel order',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Track order status and fulfillment.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/orders/{orderId}/track",
     *     summary="Track order",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Order tracking retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     *
     * @param int $orderId
     * @return JsonResponse
     */
    public function track(int $orderId): JsonResponse
    {
        try {
            $userId = Auth::id();

            $tracking = $this->orderService->trackOrder($userId, $orderId);

            if (!$tracking) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Order tracking retrieved successfully',
                'data' => [
                    'order' => $tracking['order'],
                    'current_status' => $tracking['current_status'],
                    'status_history' => $tracking['status_history'],
                    'tracking_number' => $tracking['tracking_number'] ?? null,
                    'estimated_delivery' => $tracking['estimated_delivery'] ?? null,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to track order', [
                'user_id' => $userId ?? null,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to track order',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get order analytics for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/orders/analytics",
     *     summary="Get order analytics",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Time period (7d, 30d, 90d, 1y, all)",
     *         required=false,
     *         @OA\Schema(type="string", default="30d")
     *     ),
     *     @OA\Response(response=200, description="Analytics retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $period = $request->input('period', '30d');

            $analytics = $this->orderService->getOrderAnalytics($userId, $period);

            return response()->json([
                'status' => 'success',
                'message' => 'Order analytics retrieved successfully',
                'data' => [
                    'total_orders' => $analytics['total_orders'],
                    'total_spent' => $analytics['total_spent'],
                    'orders_by_status' => $analytics['orders_by_status'],
                    'orders_by_type' => $analytics['orders_by_type'],
                    'average_order_value' => $analytics['average_order_value'],
                    'spending_trends' => $analytics['spending_trends'],
                    'popular_products' => $analytics['popular_products'],
                ],
                'meta' => [
                    'period' => $period,
                    'generated_at' => now()->toIso8601String(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve order analytics', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve order analytics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Create a bundle order with multiple products.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/orders/bundle",
     *     summary="Create bundle order",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"bundle_id", "payment_method_id"},
     *             @OA\Property(property="bundle_id", type="integer", example=5),
     *             @OA\Property(property="payment_method_id", type="integer", example=1),
     *             @OA\Property(property="billing_address", type="object"),
     *             @OA\Property(property="coupon_code", type="string", example="BUNDLE20")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Bundle order created successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     *
     * @param PurchaseRequest $request
     * @return JsonResponse
     */
    public function createBundle(PurchaseRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $bundleData = [
                'user_id' => $userId,
                'bundle_id' => $request->input('bundle_id'),
                'payment_method_id' => $request->input('payment_method_id'),
                'billing_address' => $request->input('billing_address'),
                'coupon_code' => $request->input('coupon_code'),
            ];

            $result = $this->orderService->createBundleOrder($bundleData);

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'error_code' => $result['error_code'],
                ], $result['status_code'] ?? 400);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Bundle order created successfully',
                'data' => [
                    'order' => $result['order'],
                    'bundle_details' => $result['bundle_details'],
                    'total_savings' => $result['total_savings'],
                    'total_amount' => $result['total_amount'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create bundle order', [
                'user_id' => $userId ?? null,
                'bundle_id' => $request->input('bundle_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create bundle order',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get available product bundles.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/orders/bundles",
     *     summary="Get available bundles",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Bundles retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bundles(Request $request): JsonResponse
    {
        try {
            $category = $request->input('category');

            $cacheKey = 'available_bundles_' . ($category ?? 'all');
            
            $bundles = Cache::remember($cacheKey, now()->addHours(6), function () use ($category) {
                return $this->orderService->getAvailableBundles($category);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Bundles retrieved successfully',
                'data' => [
                    'bundles' => $bundles,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve bundles', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve bundles',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Check product availability and inventory.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/orders/check-availability",
     *     summary="Check product availability",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"items"},
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 @OA\Property(property="product_id", type="integer", example=1),
     *                 @OA\Property(property="quantity", type="integer", example=2)
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Availability checked successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        try {
            $items = $request->input('items');

            $availability = $this->orderService->checkProductAvailability($items);

            return response()->json([
                'status' => 'success',
                'message' => 'Availability checked successfully',
                'data' => [
                    'all_available' => $availability['all_available'],
                    'items_availability' => $availability['items'],
                    'out_of_stock_items' => $availability['out_of_stock_items'],
                    'low_stock_items' => $availability['low_stock_items'],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to check availability', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check availability',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get order receipt/invoice.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/orders/{orderId}/receipt",
     *     summary="Get order receipt",
     *     tags={"Commerce - Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Receipt format (json, pdf)",
     *         required=false,
     *         @OA\Schema(type="string", default="json")
     *     ),
     *     @OA\Response(response=200, description="Receipt retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     *
     * @param Request $request
     * @param int $orderId
     * @return JsonResponse
     */
    public function receipt(Request $request, int $orderId): JsonResponse
    {
        try {
            $userId = Auth::id();
            $format = $request->input('format', 'json');

            $receipt = $this->orderService->getOrderReceipt($userId, $orderId, $format);

            if (!$receipt) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found',
                ], 404);
            }

            if ($format === 'pdf') {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Receipt PDF URL generated',
                    'data' => [
                        'pdf_url' => $receipt['pdf_url'],
                        'expires_at' => $receipt['expires_at'],
                    ],
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Receipt retrieved successfully',
                'data' => [
                    'receipt' => $receipt,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve order receipt', [
                'user_id' => $userId ?? null,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve order receipt',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}