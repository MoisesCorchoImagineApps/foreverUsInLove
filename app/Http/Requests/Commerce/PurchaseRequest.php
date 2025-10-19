<?php

declare(strict_types=1);

namespace App\Http\Requests\Commerce;

use App\Models\Commerce\Product;
use App\Models\Commerce\Order;
use App\Models\Commerce\Payment;
use App\Models\Commerce\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PurchaseRequest - Validación para compras y transacciones comerciales
 * 
 * Este FormRequest maneja la validación de datos para todas las operaciones
 * de compra en la aplicación ForeverUsInLove, incluyendo gifts, suscripciones,
 * coins, features y boosts.
 * 
 * Integrado con:
 * - PaymentService para procesamiento de pagos
 * - OrderService para gestión de órdenes
 * - GiftService para envío de regalos
 * - PlanService para suscripciones
 * - CoinService para compra de monedas
 * 
 * @package App\Http\Requests\Commerce
 * @version 1.0.0
 * @author ForeverUsInLove Team
 */
class PurchaseRequest extends FormRequest
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

        // Verificar límites de compra por tipo de usuario
        $purchaseType = $this->input('type');
        if ($purchaseType === Order::TYPE_SUBSCRIPTION) {
            // Verificar que no tenga una suscripción activa del mismo tipo
            $planId = $this->input('plan_id');
            if ($planId && $user->hasActiveSubscription($planId)) {
                return false;
            }
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
        return [
            // ========================================
            // DATOS BÁSICOS DE LA COMPRA
            // ========================================
            
            'type' => [
                'required',
                'string',
                Rule::in([
                    Order::TYPE_GIFT,
                    Order::TYPE_SUBSCRIPTION,
                    Order::TYPE_COINS,
                    Order::TYPE_FEATURE,
                    Order::TYPE_BOOST,
                ]),
            ],
            
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
                function ($attribute, $value, $fail) {
                    $product = Product::find($value);
                    if ($product && !$product->isPurchasable()) {
                        $fail('El producto no está disponible para compra.');
                    }
                },
            ],
            
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:100',
                function ($attribute, $value, $fail) {
                    $productId = $this->input('product_id');
                    if ($productId) {
                        $product = Product::find($productId);
                        if ($product && !$product->isInStock($value)) {
                            $fail('No hay suficiente stock disponible.');
                        }
                    }
                },
            ],
            
            // ========================================
            // DATOS ESPECÍFICOS POR TIPO DE COMPRA
            // ========================================
            
            // Para regalos (gifts)
            'recipient_id' => [
                'required_if:type,' . Order::TYPE_GIFT,
                'nullable',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($this->input('type') === Order::TYPE_GIFT && $value) {
                        $user = $this->user();
                        if ($value === $user->id) {
                            $fail('No puedes enviarte un regalo a ti mismo.');
                        }
                        
                        $recipient = \App\Models\User\User::find($value);
                        if ($recipient && !$recipient->profile) {
                            $fail('El destinatario no tiene un perfil activo.');
                        }
                    }
                },
            ],
            
            'gift_message' => [
                'nullable',
                'string',
                'max:500',
                'required_if:type,' . Order::TYPE_GIFT,
            ],
            
            // Para suscripciones (subscriptions)
            'plan_id' => [
                'required_if:type,' . Order::TYPE_SUBSCRIPTION,
                'nullable',
                'integer',
                'exists:plans,id',
                function ($attribute, $value, $fail) {
                    if ($this->input('type') === Order::TYPE_SUBSCRIPTION && $value) {
                        $plan = Plan::find($value);
                        if ($plan && !$plan->is_active) {
                            $fail('El plan no está disponible.');
                        }
                    }
                },
            ],
            
            'billing_cycle' => [
                'required_if:type,' . Order::TYPE_SUBSCRIPTION,
                'nullable',
                'string',
                Rule::in([
                    Plan::TYPE_MONTHLY,
                    Plan::TYPE_QUARTERLY,
                    Plan::TYPE_ANNUAL,
                ]),
            ],
            
            'auto_renew' => [
                'nullable',
                'boolean',
            ],
            
            // Para coins
            'coin_package_id' => [
                'required_if:type,' . Order::TYPE_COINS,
                'nullable',
                'integer',
                'exists:products,id',
                function ($attribute, $value, $fail) {
                    if ($this->input('type') === Order::TYPE_COINS && $value) {
                        $product = Product::find($value);
                        if ($product && $product->category !== Product::CATEGORY_COINS) {
                            $fail('El producto no es un paquete de monedas válido.');
                        }
                    }
                },
            ],
            
            // Para features y boosts
            'feature_type' => [
                'required_if:type,' . Order::TYPE_FEATURE,
                'nullable',
                'string',
                Rule::in([
                    'boost',
                    'super_like',
                    'rewind',
                    'passport',
                    'incognito',
                ]),
            ],
            
            'boost_duration_hours' => [
                'required_if:feature_type,boost',
                'nullable',
                'integer',
                'min:1',
                'max:168', // Máximo 1 semana
            ],
            
            // ========================================
            // DATOS DE PAGO
            // ========================================
            
            'payment_method_id' => [
                'required',
                'string',
                'max:255',
            ],
            
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
            // DATOS DE FACTURACIÓN
            // ========================================
            
            'billing_address' => [
                'required',
                'array',
            ],
            
            'billing_address.street' => [
                'required',
                'string',
                'max:255',
            ],
            
            'billing_address.city' => [
                'required',
                'string',
                'max:100',
            ],
            
            'billing_address.state' => [
                'required',
                'string',
                'max:100',
            ],
            
            'billing_address.postal_code' => [
                'required',
                'string',
                'max:20',
            ],
            
            'billing_address.country' => [
                'required',
                'string',
                'max:2',
                'regex:/^[A-Z]{2}$/', // ISO country code
            ],
            
            'billing_address.phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[1-9][\d]{0,15}$/',
            ],
            
            // ========================================
            // DATOS DE TARJETA (para algunos gateways)
            // ========================================
            
            'card_number' => [
                'required_if:gateway,' . Payment::GATEWAY_STRIPE,
                'nullable',
                'string',
                'regex:/^[0-9]{13,19}$/',
            ],
            
            'card_expiry_month' => [
                'required_if:gateway,' . Payment::GATEWAY_STRIPE,
                'nullable',
                'integer',
                'min:1',
                'max:12',
            ],
            
            'card_expiry_year' => [
                'required_if:gateway,' . Payment::GATEWAY_STRIPE,
                'nullable',
                'integer',
                'min:' . date('Y'),
                'max:' . (date('Y') + 20),
            ],
            
            'card_cvv' => [
                'required_if:gateway,' . Payment::GATEWAY_STRIPE,
                'nullable',
                'string',
                'regex:/^[0-9]{3,4}$/',
            ],
            
            'cardholder_name' => [
                'required_if:gateway,' . Payment::GATEWAY_STRIPE,
                'nullable',
                'string',
                'max:255',
            ],
            
            // ========================================
            // DATOS ADICIONALES
            // ========================================
            
            'coupon_code' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        // TODO: Implementar validación de cupones cuando se cree el modelo Coupon
                        // Por ahora solo validamos el formato
                        $fail('Los cupones no están disponibles temporalmente.');
                    }
                },
            ],
            
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            
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
            
            // ========================================
            // VALIDACIONES DE SEGURIDAD
            // ========================================
            
            'device_fingerprint' => [
                'nullable',
                'string',
                'max:255',
            ],
            
            'captcha_token' => [
                'nullable',
                'string',
                'max:1000',
            ],
            
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
            
            'marketing_consent' => [
                'nullable',
                'boolean',
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
            'type.required' => 'El tipo de compra es obligatorio.',
            'type.in' => 'El tipo de compra no es válido.',
            'product_id.required' => 'El ID del producto es obligatorio.',
            'product_id.exists' => 'El producto seleccionado no existe.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.min' => 'La cantidad mínima es 1.',
            'quantity.max' => 'La cantidad máxima es 100.',
            
            // Mensajes para regalos
            'recipient_id.required_if' => 'El destinatario es obligatorio para regalos.',
            'recipient_id.exists' => 'El destinatario seleccionado no existe.',
            'gift_message.required_if' => 'El mensaje del regalo es obligatorio.',
            'gift_message.max' => 'El mensaje del regalo no puede exceder 500 caracteres.',
            
            // Mensajes para suscripciones
            'plan_id.required_if' => 'El plan es obligatorio para suscripciones.',
            'plan_id.exists' => 'El plan seleccionado no existe.',
            'billing_cycle.required_if' => 'El ciclo de facturación es obligatorio para suscripciones.',
            'billing_cycle.in' => 'El ciclo de facturación no es válido.',
            
            // Mensajes para coins
            'coin_package_id.required_if' => 'El paquete de monedas es obligatorio.',
            'coin_package_id.exists' => 'El paquete de monedas seleccionado no existe.',
            
            // Mensajes para features
            'feature_type.required_if' => 'El tipo de feature es obligatorio.',
            'feature_type.in' => 'El tipo de feature no es válido.',
            'boost_duration_hours.required_if' => 'La duración del boost es obligatoria.',
            'boost_duration_hours.min' => 'La duración mínima del boost es 1 hora.',
            'boost_duration_hours.max' => 'La duración máxima del boost es 168 horas (1 semana).',
            
            // Mensajes de pago
            'payment_method_id.required' => 'El método de pago es obligatorio.',
            'gateway.required' => 'El gateway de pago es obligatorio.',
            'gateway.in' => 'El gateway de pago no es válido.',
            'payment_type.required' => 'El tipo de pago es obligatorio.',
            'payment_type.in' => 'El tipo de pago no es válido.',
            
            // Mensajes de facturación
            'billing_address.required' => 'La dirección de facturación es obligatoria.',
            'billing_address.street.required' => 'La calle es obligatoria.',
            'billing_address.city.required' => 'La ciudad es obligatoria.',
            'billing_address.state.required' => 'El estado es obligatorio.',
            'billing_address.postal_code.required' => 'El código postal es obligatorio.',
            'billing_address.country.required' => 'El país es obligatorio.',
            'billing_address.country.regex' => 'El código de país debe ser de 2 letras (ISO).',
            'billing_address.phone.regex' => 'El formato del teléfono no es válido.',
            
            // Mensajes de tarjeta
            'card_number.required_if' => 'El número de tarjeta es obligatorio.',
            'card_number.regex' => 'El formato del número de tarjeta no es válido.',
            'card_expiry_month.required_if' => 'El mes de expiración es obligatorio.',
            'card_expiry_month.min' => 'El mes debe ser entre 1 y 12.',
            'card_expiry_month.max' => 'El mes debe ser entre 1 y 12.',
            'card_expiry_year.required_if' => 'El año de expiración es obligatorio.',
            'card_expiry_year.min' => 'El año de expiración no puede ser anterior al año actual.',
            'card_expiry_year.max' => 'El año de expiración no puede ser más de 20 años en el futuro.',
            'card_cvv.required_if' => 'El CVV es obligatorio.',
            'card_cvv.regex' => 'El formato del CVV no es válido.',
            'cardholder_name.required_if' => 'El nombre del titular es obligatorio.',
            
            // Mensajes adicionales
            'coupon_code.regex' => 'El formato del código de cupón no es válido.',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres.',
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
            'type' => 'tipo de compra',
            'product_id' => 'producto',
            'quantity' => 'cantidad',
            'recipient_id' => 'destinatario',
            'gift_message' => 'mensaje del regalo',
            'plan_id' => 'plan',
            'billing_cycle' => 'ciclo de facturación',
            'auto_renew' => 'renovación automática',
            'coin_package_id' => 'paquete de monedas',
            'feature_type' => 'tipo de feature',
            'boost_duration_hours' => 'duración del boost',
            'payment_method_id' => 'método de pago',
            'gateway' => 'gateway de pago',
            'payment_type' => 'tipo de pago',
            'billing_address' => 'dirección de facturación',
            'billing_address.street' => 'calle',
            'billing_address.city' => 'ciudad',
            'billing_address.state' => 'estado',
            'billing_address.postal_code' => 'código postal',
            'billing_address.country' => 'país',
            'billing_address.phone' => 'teléfono',
            'card_number' => 'número de tarjeta',
            'card_expiry_month' => 'mes de expiración',
            'card_expiry_year' => 'año de expiración',
            'card_cvv' => 'CVV',
            'cardholder_name' => 'nombre del titular',
            'coupon_code' => 'código de cupón',
            'notes' => 'notas',
            'metadata' => 'metadatos',
            'metadata.ip_address' => 'dirección IP',
            'metadata.user_agent' => 'user agent',
            'metadata.platform' => 'plataforma',
            'metadata.app_version' => 'versión de la app',
            'device_fingerprint' => 'huella del dispositivo',
            'captcha_token' => 'token de captcha',
            'terms_accepted' => 'términos y condiciones',
            'privacy_policy_accepted' => 'política de privacidad',
            'marketing_consent' => 'consentimiento de marketing',
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
            
            // Verificar límites de compra por usuario
            $this->validateUserPurchaseLimits($validator);
            
            // Verificar disponibilidad de stock
            $this->validateStockAvailability($validator);
            
            // Verificar límites de precio
            $this->validatePriceLimits($validator);
            
            // Verificar restricciones geográficas
            $this->validateGeographicRestrictions($validator);
            
            // Verificar límites de frecuencia de compra
            $this->validatePurchaseFrequency($validator);
        });
    }

    /**
     * Validar límites de compra por usuario
     */
    protected function validateUserPurchaseLimits($validator): void
    {
        $user = $this->user();
        $type = $this->input('type');
        
        // Verificar límites diarios por tipo de usuario
        $dailyLimit = match($user->subscription_tier ?? 'basic') {
            'basic' => 5,
            'premium' => 20,
            'vip' => 50,
            'elite' => 100,
            default => 5,
        };
        
        $todayPurchases = Order::forUser($user->id)
            ->where('type', $type)
            ->whereDate('created_at', today())
            ->count();
            
        if ($todayPurchases >= $dailyLimit) {
            $validator->errors()->add(
                'type',
                "Has alcanzado el límite diario de compras de este tipo ({$dailyLimit})."
            );
        }
    }

    /**
     * Validar disponibilidad de stock
     */
    protected function validateStockAvailability($validator): void
    {
        $productId = $this->input('product_id');
        $quantity = $this->input('quantity');
        
        if ($productId && $quantity) {
            $product = Product::find($productId);
            if ($product && !$product->isInStock($quantity)) {
                $validator->errors()->add(
                    'quantity',
                    'No hay suficiente stock disponible para la cantidad solicitada.'
                );
            }
        }
    }

    /**
     * Validar límites de precio
     */
    protected function validatePriceLimits($validator): void
    {
        $user = $this->user();
        $productId = $this->input('product_id');
        
        if ($productId) {
            $product = Product::find($productId);
            if ($product) {
                $maxPrice = match($user->subscription_tier ?? 'basic') {
                    'basic' => 50.00,
                    'premium' => 200.00,
                    'vip' => 500.00,
                    'elite' => 1000.00,
                    default => 50.00,
                };
                
                if ($product->price > $maxPrice) {
                    $validator->errors()->add(
                        'product_id',
                        "El precio del producto excede tu límite de compra (máximo: \${$maxPrice})."
                    );
                }
            }
        }
    }

    /**
     * Validar restricciones geográficas
     */
    protected function validateGeographicRestrictions($validator): void
    {
        $country = $this->input('billing_address.country');
        $productId = $this->input('product_id');
        
        if ($country && $productId) {
            $product = Product::find($productId);
            if ($product && !empty($product->restrictions)) {
                $restrictedCountries = $product->restrictions['countries'] ?? [];
                if (in_array($country, $restrictedCountries)) {
                    $validator->errors()->add(
                        'billing_address.country',
                        'Este producto no está disponible en tu país.'
                    );
                }
            }
        }
    }

    /**
     * Validar límites de frecuencia de compra
     */
    protected function validatePurchaseFrequency($validator): void
    {
        $user = $this->user();
        $type = $this->input('type');
        
        // Verificar límites de frecuencia (compras por hora)
        $hourlyLimit = match($user->subscription_tier ?? 'basic') {
            'basic' => 2,
            'premium' => 5,
            'vip' => 10,
            'elite' => 20,
            default => 2,
        };
        
        $recentPurchases = Order::forUser($user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subHour())
            ->count();
            
        if ($recentPurchases >= $hourlyLimit) {
            $validator->errors()->add(
                'type',
                "Has alcanzado el límite de compras por hora ({$hourlyLimit}). Intenta nuevamente más tarde."
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
        if ($this->has('billing_address.country')) {
            $this->merge([
                'billing_address' => array_merge($this->input('billing_address', []), [
                    'country' => strtoupper($this->input('billing_address.country')),
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
        $validated['calculated_total'] = $this->calculateTotal();
        $validated['user_id'] = $this->user()->id;
        $validated['order_number'] = $this->generateOrderNumber();
        
        return $validated;
    }

    /**
     * Calcular total de la compra
     */
    protected function calculateTotal(): float
    {
        $productId = $this->input('product_id');
        $quantity = $this->input('quantity', 1);
        
        if ($productId) {
            $product = Product::find($productId);
            if ($product) {
                $subtotal = $product->price * $quantity;
                $tax = $subtotal * 0.10; // 10% tax
                $discount = 0; // TODO: Calculate discount from coupon
                
                return $subtotal + $tax - $discount;
            }
        }
        
        return 0.00;
    }

    /**
     * Generar número de orden único
     */
    protected function generateOrderNumber(): string
    {
        return 'ORD-' . strtoupper(uniqid());
    }
}
