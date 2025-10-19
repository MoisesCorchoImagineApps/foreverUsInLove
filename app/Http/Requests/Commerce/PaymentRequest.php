<?php

declare(strict_types=1);

namespace App\Http\Requests\Commerce;

use App\Models\Commerce\Payment;
use App\Models\Commerce\Order;
use App\Models\Commerce\Plan;
use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PaymentRequest - Validación para procesamiento de pagos
 * 
 * Este FormRequest maneja la validación de datos para todas las operaciones
 * de pago en la aplicación ForeverUsInLove, incluyendo pagos únicos, suscripciones
 * recurrentes, reembolsos y validaciones de seguridad avanzadas.
 * 
 * Integrado con:
 * - PaymentService para procesamiento de pagos
 * - OrderService para gestión de órdenes
 * - PlanService para suscripciones
 * - Fraud detection y validaciones de seguridad
 * 
 * @package App\Http\Requests\Commerce
 * @version 1.0.0
 * @author ForeverUsInLove Team
 */
class PaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // El usuario debe estar autenticado
        $user = $this->user();
        if (!$user) {
            return false;
        }

        // El usuario debe tener un perfil activo
        if (!$user->profile || $user->profile->status !== 'active') {
            return false;
        }

        // Verificar que el usuario no esté suspendido
        if ($user->status === 'suspended' || $user->status === 'banned') {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * 
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $action = $this->route('action') ?? 'process';
        
        return match($action) {
            'process' => $this->getProcessPaymentRules(),
            'authorize' => $this->getAuthorizePaymentRules(),
            'capture' => $this->getCapturePaymentRules(),
            'refund' => $this->getRefundPaymentRules(),
            'retry' => $this->getRetryPaymentRules(),
            'cancel' => $this->getCancelPaymentRules(),
            default => $this->getProcessPaymentRules(),
        };
    }

    /**
     * Reglas para procesar un nuevo pago
     */
    protected function getProcessPaymentRules(): array
    {
        return [
            // ========================================
            // DATOS BÁSICOS DEL PAGO
            // ========================================
            
            'order_id' => [
                'required',
                'integer',
                'exists:orders,id',
                function ($attribute, $value, $fail) {
                    $order = Order::find($value);
                    if ($order && $order->user_id !== $this->user()->id) {
                        $fail('No tienes permisos para pagar esta orden.');
                    }
                    if ($order && $order->payment_status !== Order::PAYMENT_PENDING) {
                        $fail('Esta orden ya ha sido procesada.');
                    }
                },
            ],
            
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:10000.00',
                function ($attribute, $value, $fail) {
                    $orderId = $this->input('order_id');
                    if ($orderId) {
                        $order = Order::find($orderId);
                        if ($order && abs($order->total - $value) > 0.01) {
                            $fail('El monto no coincide con el total de la orden.');
                        }
                    }
                },
            ],
            
            'currency' => [
                'required',
                'string',
                'max:3',
                Rule::in(['USD', 'EUR', 'GBP', 'CAD', 'AUD']),
            ],
            
            // ========================================
            // MÉTODO DE PAGO Y GATEWAY
            // ========================================
            
            'gateway' => [
                'required',
                'string',
                Rule::in([
                    Payment::GATEWAY_STRIPE,
                    Payment::GATEWAY_PAYPAL,
                    Payment::GATEWAY_APPLE_PAY,
                    Payment::GATEWAY_GOOGLE_PAY,
                    Payment::GATEWAY_BRAINTREE,
                    Payment::GATEWAY_SQUARE,
                    Payment::GATEWAY_ADYEN,
                ]),
            ],
            
            'payment_method_id' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $gateway = $this->input('gateway');
                    $user = $this->user();
                    
                    // Verificar que el método de pago pertenezca al usuario
                    // TODO: Implementar validación real cuando se cree PaymentMethod model
                    if (empty($value)) {
                        $fail('El método de pago es requerido.');
                    }
                },
            ],
            
            'payment_type' => [
                'required',
                'string',
                Rule::in([
                    Payment::TYPE_ONE_TIME,
                    Payment::TYPE_RECURRING,
                    Payment::TYPE_SUBSCRIPTION,
                ]),
            ],
            
            // ========================================
            // DATOS DE TARJETA (para gateways que lo requieren)
            // ========================================
            
            'card_details' => [
                'required_if:gateway,' . Payment::GATEWAY_STRIPE,
                'nullable',
                'array',
            ],
            
            'card_details.number' => [
                'required_if:gateway,' . Payment::GATEWAY_STRIPE,
                'nullable',
                'string',
                'regex:/^[0-9]{13,19}$/',
            ],
            
            'card_details.expiry_month' => [
                'required_if:gateway,' . Payment::GATEWAY_STRIPE,
                'nullable',
                'integer',
                'min:1',
                'max:12',
            ],
            
            'card_details.expiry_year' => [
                'required_if:gateway,' . Payment::GATEWAY_STRIPE,
                'nullable',
                'integer',
                'min:' . date('Y'),
                'max:' . (date('Y') + 20),
            ],
            
            'card_details.cvv' => [
                'required_if:gateway,' . Payment::GATEWAY_STRIPE,
                'nullable',
                'string',
                'regex:/^[0-9]{3,4}$/',
            ],
            
            'card_details.holder_name' => [
                'required_if:gateway,' . Payment::GATEWAY_STRIPE,
                'nullable',
                'string',
                'max:255',
            ],
            
            // ========================================
            // DATOS DE FACTURACIÓN
            // ========================================
            
            'billing_details' => [
                'required',
                'array',
            ],
            
            'billing_details.name' => [
                'required',
                'string',
                'max:255',
            ],
            
            'billing_details.email' => [
                'required',
                'email',
                'max:255',
            ],
            
            'billing_details.phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[1-9][\d]{0,15}$/',
            ],
            
            'billing_details.address' => [
                'required',
                'array',
            ],
            
            'billing_details.address.line1' => [
                'required',
                'string',
                'max:255',
            ],
            
            'billing_details.address.line2' => [
                'nullable',
                'string',
                'max:255',
            ],
            
            'billing_details.address.city' => [
                'required',
                'string',
                'max:100',
            ],
            
            'billing_details.address.state' => [
                'required',
                'string',
                'max:100',
            ],
            
            'billing_details.address.postal_code' => [
                'required',
                'string',
                'max:20',
            ],
            
            'billing_details.address.country' => [
                'required',
                'string',
                'max:2',
                'regex:/^[A-Z]{2}$/',
            ],
            
            // ========================================
            // CONFIGURACIONES ESPECÍFICAS
            // ========================================
            
            'save_payment_method' => [
                'nullable',
                'boolean',
            ],
            
            'requires_3ds' => [
                'nullable',
                'boolean',
            ],
            
            'three_ds_redirect_url' => [
                'nullable',
                'url',
                'max:500',
            ],
            
            'return_url' => [
                'nullable',
                'url',
                'max:500',
            ],
            
            'cancel_url' => [
                'nullable',
                'url',
                'max:500',
            ],
            
            // ========================================
            // METADATOS Y SEGURIDAD
            // ========================================
            
            'metadata' => [
                'nullable',
                'array',
            ],
            
            'metadata.ip_address' => [
                'nullable',
                'ip',
            ],
            
            'metadata.user_agent' => [
                'nullable',
                'string',
                'max:500',
            ],
            
            'metadata.platform' => [
                'nullable',
                'string',
                Rule::in(['ios', 'android', 'web', 'desktop']),
            ],
            
            'metadata.app_version' => [
                'nullable',
                'string',
                'max:20',
            ],
            
            'device_fingerprint' => [
                'nullable',
                'string',
                'max:255',
            ],
            
            'fraud_check_token' => [
                'nullable',
                'string',
                'max:500',
            ],
            
            'captcha_token' => [
                'nullable',
                'string',
                'max:1000',
            ],
            
            // ========================================
            // CONFIRMACIONES DE SEGURIDAD
            // ========================================
            
            'terms_accepted' => [
                'required',
                'boolean',
                'accepted',
            ],
            
            'privacy_policy_accepted' => [
                'required',
                'boolean',
                'accepted',
            ],
        ];
    }

    /**
     * Reglas para autorizar un pago
     */
    protected function getAuthorizePaymentRules(): array
    {
        return [
            'payment_id' => [
                'required',
                'integer',
                'exists:payments,id',
                function ($attribute, $value, $fail) {
                    $payment = Payment::find($value);
                    if ($payment && $payment->user_id !== $this->user()->id) {
                        $fail('No tienes permisos para autorizar este pago.');
                    }
                    if ($payment && $payment->status !== Payment::STATUS_PENDING) {
                        $fail('Este pago no puede ser autorizado.');
                    }
                },
            ],
        ];
    }

    /**
     * Reglas para capturar un pago autorizado
     */
    protected function getCapturePaymentRules(): array
    {
        return [
            'payment_id' => [
                'required',
                'integer',
                'exists:payments,id',
                function ($attribute, $value, $fail) {
                    $payment = Payment::find($value);
                    if ($payment && $payment->user_id !== $this->user()->id) {
                        $fail('No tienes permisos para capturar este pago.');
                    }
                    if ($payment && $payment->status !== Payment::STATUS_AUTHORIZED) {
                        $fail('Este pago no puede ser capturado.');
                    }
                },
            ],
            
            'amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    $paymentId = $this->input('payment_id');
                    if ($paymentId && $value) {
                        $payment = Payment::find($paymentId);
                        if ($payment && $value > $payment->amount) {
                            $fail('No puedes capturar más del monto autorizado.');
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Reglas para reembolsar un pago
     */
    protected function getRefundPaymentRules(): array
    {
        return [
            'payment_id' => [
                'required',
                'integer',
                'exists:payments,id',
                function ($attribute, $value, $fail) {
                    $payment = Payment::find($value);
                    if ($payment && $payment->user_id !== $this->user()->id) {
                        $fail('No tienes permisos para reembolsar este pago.');
                    }
                    if ($payment && !$payment->can_be_refunded) {
                        $fail('Este pago no puede ser reembolsado.');
                    }
                },
            ],
            
            'amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    $paymentId = $this->input('payment_id');
                    if ($paymentId && $value) {
                        $payment = Payment::find($paymentId);
                        if ($payment && $value > $payment->amount) {
                            $fail('No puedes reembolsar más del monto original.');
                        }
                    }
                },
            ],
            
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Reglas para reintentar un pago fallido
     */
    protected function getRetryPaymentRules(): array
    {
        return [
            'payment_id' => [
                'required',
                'integer',
                'exists:payments,id',
                function ($attribute, $value, $fail) {
                    $payment = Payment::find($value);
                    if ($payment && $payment->user_id !== $this->user()->id) {
                        $fail('No tienes permisos para reintentar este pago.');
                    }
                    if ($payment && $payment->status !== Payment::STATUS_FAILED) {
                        $fail('Solo se pueden reintentar pagos fallidos.');
                    }
                    if ($payment && $payment->retry_count >= 3) {
                        $fail('Se ha alcanzado el límite máximo de reintentos.');
                    }
                },
            ],
        ];
    }

    /**
     * Reglas para cancelar un pago
     */
    protected function getCancelPaymentRules(): array
    {
        return [
            'payment_id' => [
                'required',
                'integer',
                'exists:payments,id',
                function ($attribute, $value, $fail) {
                    $payment = Payment::find($value);
                    if ($payment && $payment->user_id !== $this->user()->id) {
                        $fail('No tienes permisos para cancelar este pago.');
                    }
                    if ($payment && !in_array($payment->status, [Payment::STATUS_PENDING, Payment::STATUS_AUTHORIZED])) {
                        $fail('Este pago no puede ser cancelado.');
                    }
                },
            ],
            
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     * 
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Mensajes básicos
            'order_id.required' => 'El ID de la orden es obligatorio.',
            'order_id.exists' => 'La orden seleccionada no existe.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.min' => 'El monto mínimo es $0.01.',
            'amount.max' => 'El monto máximo es $10,000.00.',
            'currency.required' => 'La moneda es obligatoria.',
            'currency.in' => 'La moneda no es válida.',
            
            // Mensajes de gateway
            'gateway.required' => 'El gateway de pago es obligatorio.',
            'gateway.in' => 'El gateway de pago no es válido.',
            'payment_method_id.required' => 'El método de pago es obligatorio.',
            'payment_type.required' => 'El tipo de pago es obligatorio.',
            'payment_type.in' => 'El tipo de pago no es válido.',
            
            // Mensajes de tarjeta
            'card_details.number.required_if' => 'El número de tarjeta es obligatorio.',
            'card_details.number.regex' => 'El formato del número de tarjeta no es válido.',
            'card_details.expiry_month.required_if' => 'El mes de expiración es obligatorio.',
            'card_details.expiry_month.min' => 'El mes debe ser entre 1 y 12.',
            'card_details.expiry_month.max' => 'El mes debe ser entre 1 y 12.',
            'card_details.expiry_year.required_if' => 'El año de expiración es obligatorio.',
            'card_details.expiry_year.min' => 'El año de expiración no puede ser anterior al año actual.',
            'card_details.expiry_year.max' => 'El año de expiración no puede ser más de 20 años en el futuro.',
            'card_details.cvv.required_if' => 'El CVV es obligatorio.',
            'card_details.cvv.regex' => 'El formato del CVV no es válido.',
            'card_details.holder_name.required_if' => 'El nombre del titular es obligatorio.',
            
            // Mensajes de facturación
            'billing_details.required' => 'Los datos de facturación son obligatorios.',
            'billing_details.name.required' => 'El nombre es obligatorio.',
            'billing_details.email.required' => 'El email es obligatorio.',
            'billing_details.email.email' => 'El email debe tener un formato válido.',
            'billing_details.phone.regex' => 'El formato del teléfono no es válido.',
            'billing_details.address.required' => 'La dirección es obligatoria.',
            'billing_details.address.line1.required' => 'La dirección línea 1 es obligatoria.',
            'billing_details.address.city.required' => 'La ciudad es obligatoria.',
            'billing_details.address.state.required' => 'El estado es obligatorio.',
            'billing_details.address.postal_code.required' => 'El código postal es obligatorio.',
            'billing_details.address.country.required' => 'El país es obligatorio.',
            'billing_details.address.country.regex' => 'El código de país debe ser de 2 letras (ISO).',
            
            // Mensajes de URLs
            'three_ds_redirect_url.url' => 'La URL de redirección 3DS no es válida.',
            'return_url.url' => 'La URL de retorno no es válida.',
            'cancel_url.url' => 'La URL de cancelación no es válida.',
            
            // Mensajes de metadatos
            'metadata.ip_address.ip' => 'La dirección IP no es válida.',
            'metadata.platform.in' => 'La plataforma especificada no es válida.',
            'metadata.app_version.max' => 'La versión de la app no puede exceder 20 caracteres.',
            
            // Mensajes de seguridad
            'terms_accepted.required' => 'Debes aceptar los términos y condiciones.',
            'terms_accepted.accepted' => 'Debes aceptar los términos y condiciones.',
            'privacy_policy_accepted.required' => 'Debes aceptar la política de privacidad.',
            'privacy_policy_accepted.accepted' => 'Debes aceptar la política de privacidad.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     * 
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'order_id' => 'orden',
            'amount' => 'monto',
            'currency' => 'moneda',
            'gateway' => 'gateway de pago',
            'payment_method_id' => 'método de pago',
            'payment_type' => 'tipo de pago',
            'card_details.number' => 'número de tarjeta',
            'card_details.expiry_month' => 'mes de expiración',
            'card_details.expiry_year' => 'año de expiración',
            'card_details.cvv' => 'CVV',
            'card_details.holder_name' => 'nombre del titular',
            'billing_details.name' => 'nombre',
            'billing_details.email' => 'email',
            'billing_details.phone' => 'teléfono',
            'billing_details.address.line1' => 'dirección línea 1',
            'billing_details.address.line2' => 'dirección línea 2',
            'billing_details.address.city' => 'ciudad',
            'billing_details.address.state' => 'estado',
            'billing_details.address.postal_code' => 'código postal',
            'billing_details.address.country' => 'país',
            'save_payment_method' => 'guardar método de pago',
            'requires_3ds' => 'requiere 3DS',
            'three_ds_redirect_url' => 'URL de redirección 3DS',
            'return_url' => 'URL de retorno',
            'cancel_url' => 'URL de cancelación',
            'metadata.ip_address' => 'dirección IP',
            'metadata.user_agent' => 'user agent',
            'metadata.platform' => 'plataforma',
            'metadata.app_version' => 'versión de la app',
            'device_fingerprint' => 'huella del dispositivo',
            'fraud_check_token' => 'token de verificación de fraude',
            'captcha_token' => 'token de captcha',
            'terms_accepted' => 'términos y condiciones',
            'privacy_policy_accepted' => 'política de privacidad',
        ];
    }

    /**
     * Configure the validator instance.
     * 
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validaciones adicionales que requieren acceso a múltiples campos
            
            // Verificar límites de pago por usuario
            $this->validateUserPaymentLimits($validator);
            
            // Verificar límites de fraude
            $this->validateFraudLimits($validator);
            
            // Verificar restricciones geográficas
            $this->validateGeographicRestrictions($validator);
            
            // Verificar límites de frecuencia de pago
            $this->validatePaymentFrequency($validator);
        });
    }

    /**
     * Validar límites de pago por usuario
     */
    protected function validateUserPaymentLimits($validator): void
    {
        $user = $this->user();
        $amount = $this->input('amount');
        
        if (!$amount) return;
        
        // Verificar límites diarios por tipo de usuario
        $dailyLimit = match($user->subscription_tier ?? 'basic') {
            'basic' => 100.00,
            'premium' => 500.00,
            'vip' => 1000.00,
            'elite' => 5000.00,
            default => 100.00,
        };
        
        $todayPayments = Payment::forUser($user->id)
            ->whereDate('created_at', today())
            ->where('status', Payment::STATUS_CAPTURED)
            ->sum('amount');
            
        if (($todayPayments + $amount) > $dailyLimit) {
            $validator->errors()->add(
                'amount',
                "Has alcanzado tu límite diario de pagos (\${$dailyLimit})."
            );
        }
    }

    /**
     * Validar límites de fraude
     */
    protected function validateFraudLimits($validator): void
    {
        $user = $this->user();
        $amount = $this->input('amount');
        
        if (!$amount) return;
        
        // Verificar si el monto es sospechoso (muy alto)
        if ($amount > Payment::FRAUD_HIGH_AMOUNT) {
            // Para montos altos, requerir verificación adicional
            if (!$this->input('fraud_check_token')) {
                $validator->errors()->add(
                    'amount',
                    'Para montos altos se requiere verificación adicional de fraude.'
                );
            }
        }
        
        // Verificar velocidad de pagos (pagos por hora)
        $recentPayments = Payment::forUser($user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
            
        if ($recentPayments >= Payment::FRAUD_VELOCITY_LIMIT) {
            $validator->errors()->add(
                'amount',
                'Has realizado demasiados pagos en la última hora. Intenta más tarde.'
            );
        }
    }

    /**
     * Validar restricciones geográficas
     */
    protected function validateGeographicRestrictions($validator): void
    {
        $country = $this->input('billing_details.address.country');
        $gateway = $this->input('gateway');
        
        if ($country && $gateway) {
            // Verificar restricciones por país y gateway
            $restrictions = [
                'IR' => [Payment::GATEWAY_STRIPE, Payment::GATEWAY_PAYPAL], // Iran
                'KP' => [Payment::GATEWAY_STRIPE, Payment::GATEWAY_PAYPAL], // North Korea
                'SY' => [Payment::GATEWAY_STRIPE, Payment::GATEWAY_PAYPAL], // Syria
            ];
            
            if (isset($restrictions[$country]) && in_array($gateway, $restrictions[$country])) {
                $validator->errors()->add(
                    'billing_details.address.country',
                    'El gateway seleccionado no está disponible en tu país.'
                );
            }
        }
    }

    /**
     * Validar límites de frecuencia de pago
     */
    protected function validatePaymentFrequency($validator): void
    {
        $user = $this->user();
        
        // Verificar límites de frecuencia (pagos por minuto)
        $minuteLimit = match($user->subscription_tier ?? 'basic') {
            'basic' => 2,
            'premium' => 5,
            'vip' => 10,
            'elite' => 20,
            default => 2,
        };
        
        $recentPayments = Payment::forUser($user->id)
            ->where('created_at', '>=', now()->subMinute())
            ->count();
            
        if ($recentPayments >= $minuteLimit) {
            $validator->errors()->add(
                'amount',
                "Has realizado demasiados pagos en el último minuto ({$minuteLimit}). Intenta más tarde."
            );
        }
    }

    /**
     * Prepare the data for validation.
     * 
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Agregar metadatos automáticamente
        $this->merge([
            'metadata' => array_merge($this->input('metadata', []), [
                'ip_address' => $this->ip(),
                'user_agent' => $this->userAgent(),
                'platform' => $this->detectPlatform(),
                'timestamp' => now()->toISOString(),
            ]),
        ]);
        
        // Normalizar datos de facturación
        if ($this->has('billing_details.address.country')) {
            $this->merge([
                'billing_details' => array_merge($this->input('billing_details', []), [
                    'address' => array_merge($this->input('billing_details.address', []), [
                        'country' => strtoupper($this->input('billing_details.address.country')),
                    ]),
                ]),
            ]);
        }
        
        // Normalizar número de tarjeta (remover espacios y guiones)
        if ($this->has('card_details.number')) {
            $cardNumber = preg_replace('/[\s\-]/', '', $this->input('card_details.number'));
            $this->merge([
                'card_details' => array_merge($this->input('card_details', []), [
                    'number' => $cardNumber,
                ]),
            ]);
        }
    }

    /**
     * Detectar plataforma desde User-Agent
     */
    protected function detectPlatform(): string
    {
        $userAgent = $this->userAgent() ?? '';
        
        if (stripos($userAgent, 'android') !== false) {
            return 'android';
        } elseif (stripos($userAgent, 'iphone') !== false || stripos($userAgent, 'ipad') !== false) {
            return 'ios';
        } elseif (stripos($userAgent, 'windows') !== false) {
            return 'desktop';
        } elseif (stripos($userAgent, 'mac') !== false) {
            return 'desktop';
        }
        
        return 'web';
    }

    /**
     * Get the validated data from the request.
     * 
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        
        // Agregar datos calculados
        $validated['user_id'] = $this->user()->id;
        $validated['payment_number'] = $this->generatePaymentNumber();
        
        // Calcular fee si no se proporciona
        if (!isset($validated['fee'])) {
            $validated['fee'] = $this->calculateFee($validated['amount']);
        }
        
        $validated['net_amount'] = $validated['amount'] - $validated['fee'];
        
        return $validated;
    }

    /**
     * Generar número de pago único
     */
    protected function generatePaymentNumber(): string
    {
        return 'PAY-' . strtoupper(uniqid());
    }

    /**
     * Calcular fee del gateway
     */
    protected function calculateFee(float $amount): float
    {
        return ($amount * Payment::FEE_PERCENTAGE) + Payment::FEE_FIXED;
    }
}
