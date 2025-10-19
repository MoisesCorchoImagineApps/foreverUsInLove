<?php

declare(strict_types=1);

namespace App\Http\Resources\Commerce;

use App\Models\Commerce\Order;
use App\Models\Commerce\Payment;
use App\Models\Commerce\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * OrderResource - Resource para serialización de datos de órdenes
 * 
 * Este resource maneja la transformación y serialización de datos del modelo Order
 * para respuestas de API, incluyendo filtrado de información sensible, formateo
 * de datos y agregación de información relacionada.
 * 
 * Funcionalidades:
 * - Transformación de datos de órdenes para API
 * - Filtrado de información sensible según contexto
 * - Formateo de precios y datos especiales
 * - Agregación de información de items y pagos
 * - Cálculo de métricas en tiempo real
 * - Soporte para diferentes contextos de visualización
 * - Compatibilidad con diferentes tipos de órdenes (gifts, subscriptions, coins, features, boosts)
 * - Manejo de estados de cumplimiento y pagos
 * - Soporte para análisis de órdenes y reportes
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class OrderResource extends JsonResource
{
    /**
     * Contextos de visualización disponibles
     */
    public const CONTEXT_CUSTOMER = 'customer';     // Vista del cliente
    public const CONTEXT_ADMIN = 'admin';           // Vista administrativa
    public const CONTEXT_ANALYTICS = 'analytics';   // Vista de analytics
    public const CONTEXT_SUPPORT = 'support';       // Vista de soporte al cliente
    public const CONTEXT_FULFILLMENT = 'fulfillment'; // Vista de cumplimiento

    /**
     * Campos que nunca se deben exponer públicamente
     */
    private const PRIVATE_FIELDS = [
        'metadata',
        'error_log',
        'admin_notes',
        'retry_count'
    ];

    /**
     * Campos sensibles que solo se muestran en contexto admin/support
     */
    private const SENSITIVE_FIELDS = [
        'billing_address',
        'fulfillment_data',
        'cancellation_reason',
        'refund_reason',
        'expires_at'
    ];

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $context = $this->determineContext($request);
        
        return [
            // Información básica de la orden
            'id' => $this->id,
            'order_number' => $this->order_number,
            'type' => $this->type,
            'status' => $this->status,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Información del usuario
            'customer' => $this->getCustomerData($context),
            
            // Información financiera
            'financial' => $this->getFinancialData($context),
            
            // Información de estado
            'status_info' => $this->getStatusData($context),
            
            // Información de items
            'items' => $this->getItemsData($context),
            
            // Información de pago
            'payment' => $this->getPaymentData($context),
            
            // Información de cumplimiento
            'fulfillment' => $this->getFulfillmentData($context),
            
            // Información de tiempo
            'timeline' => $this->getTimelineData($context),
            
            // Información de notas y comentarios
            'notes' => $this->getNotesData($context),
            
            // Información de analytics (solo admin)
            'analytics' => $this->getAnalyticsData($context),
            
            // Información de configuración
            'configuration' => $this->getConfigurationData($context),
        ];
    }

    /**
     * Determina el contexto de visualización basado en la request
     */
    private function determineContext(Request $request): string
    {
        // Verificar si es una vista administrativa
        if ($request->user()?->isAdmin()) {
            return self::CONTEXT_ADMIN;
        }

        // Verificar si es para analytics
        if ($request->has('analytics') || $request->routeIs('*.analytics')) {
            return self::CONTEXT_ANALYTICS;
        }

        // Verificar si es para soporte al cliente
        if ($request->has('support') || $request->routeIs('*.support')) {
            return self::CONTEXT_SUPPORT;
        }

        // Verificar si es para cumplimiento
        if ($request->has('fulfillment') || $request->routeIs('*.fulfillment')) {
            return self::CONTEXT_FULFILLMENT;
        }

        // Por defecto, contexto del cliente
        return self::CONTEXT_CUSTOMER;
    }

    /**
     * Obtiene datos del cliente según el contexto
     */
    private function getCustomerData(string $context): array
    {
        $customer = [
            'id' => $this->user_id,
        ];

        // Solo mostrar información completa en contextos apropiados
        if (in_array($context, [self::CONTEXT_ADMIN, self::CONTEXT_SUPPORT, self::CONTEXT_ANALYTICS])) {
            $customer['name'] = $this->user?->name;
            $customer['email'] = $this->user?->email;
            $customer['phone'] = $this->user?->phone;
            $customer['created_at'] = $this->user?->created_at?->toISOString();
        }

        return $customer;
    }

    /**
     * Obtiene datos financieros según el contexto
     */
    private function getFinancialData(string $context): array
    {
        $financial = [
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'discount' => $this->discount,
            'total' => $this->total,
            'currency' => $this->currency,
            'formatted_total' => $this->formatted_total,
            'formatted_subtotal' => $this->formatted_subtotal,
        ];

        // En contexto admin, agregar información adicional
        if ($context === self::CONTEXT_ADMIN) {
            $financial['profit_margin'] = $this->calculateProfitMargin();
            $financial['cost_breakdown'] = $this->getCostBreakdown();
        }

        return $financial;
    }

    /**
     * Obtiene datos de estado según el contexto
     */
    private function getStatusData(string $context): array
    {
        $status = [
            'order_status' => $this->status,
            'payment_status' => $this->payment_status,
            'fulfillment_status' => $this->fulfillment_status,
            'is_paid' => $this->is_paid,
            'is_fulfilled' => $this->is_fulfilled,
            'is_completed' => $this->is_completed,
            'can_be_cancelled' => $this->can_be_cancelled,
            'can_be_refunded' => $this->can_be_refunded,
            'is_expired' => $this->is_expired,
        ];

        // Agregar información adicional en contextos apropiados
        if (in_array($context, [self::CONTEXT_ADMIN, self::CONTEXT_SUPPORT])) {
            $status['estimated_fulfillment'] = $this->estimated_fulfillment;
            $status['retry_count'] = $this->retry_count;
        }

        return $status;
    }

    /**
     * Obtiene datos de items según el contexto
     */
    private function getItemsData(string $context): array
    {
        $items = [];

        if ($this->relationLoaded('items')) {
            foreach ($this->items as $item) {
                $itemData = [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ];

                // Agregar información del producto si está disponible
                if ($item->relationLoaded('product')) {
                    $itemData['product'] = new ProductResource($item->product);
                }

                // En contexto admin, agregar información adicional
                if ($context === self::CONTEXT_ADMIN) {
                    $itemData['options'] = $item->options ?? [];
                    $itemData['fulfillment_status'] = $item->fulfillment_status ?? 'pending';
                }

                $items[] = $itemData;
            }
        }

        return $items;
    }

    /**
     * Obtiene datos de pago según el contexto
     */
    private function getPaymentData(string $context): array
    {
        $payment = [
            'status' => $this->payment_status,
            'is_paid' => $this->is_paid,
        ];

        // Agregar información del pago si está disponible
        if ($this->relationLoaded('payment') && $this->payment) {
            $payment['payment_id'] = $this->payment->id;
            $payment['payment_number'] = $this->payment->payment_number;
            $payment['gateway'] = $this->payment->gateway;
            $payment['amount'] = $this->payment->amount;
            $payment['currency'] = $this->payment->currency;
            $payment['formatted_amount'] = $this->payment->formatted_amount;

            // Solo mostrar información sensible en contextos apropiados
            if (in_array($context, [self::CONTEXT_ADMIN, self::CONTEXT_SUPPORT])) {
                $payment['gateway_transaction_id'] = $this->payment->gateway_transaction_id;
                $payment['payment_method_id'] = $this->payment->payment_method_id;
                $payment['fee'] = $this->payment->fee;
                $payment['net_amount'] = $this->payment->net_amount;
                $payment['risk_score'] = $this->payment->risk_score;
                $payment['is_test'] = $this->payment->is_test;
            }
        }

        return $payment;
    }

    /**
     * Obtiene datos de cumplimiento según el contexto
     */
    private function getFulfillmentData(string $context): array
    {
        $fulfillment = [
            'status' => $this->fulfillment_status,
            'type' => $this->fulfillment_type,
            'is_fulfilled' => $this->is_fulfilled,
            'fulfilled_at' => $this->fulfilled_at ? $this->formatDateTime($this->fulfilled_at) : null,
        ];

        // Agregar información adicional en contextos apropiados
        if (in_array($context, [self::CONTEXT_ADMIN, self::CONTEXT_FULFILLMENT, self::CONTEXT_SUPPORT])) {
            $fulfillment['fulfillment_data'] = $this->fulfillment_data ?? [];
            $fulfillment['estimated_delivery'] = $this->getEstimatedDelivery();
            $fulfillment['tracking_info'] = $this->getTrackingInfo();
        }

        return $fulfillment;
    }

    /**
     * Obtiene datos de línea de tiempo según el contexto
     */
    private function getTimelineData(string $context): array
    {
        $timeline = [
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
        ];

        // Agregar fechas específicas según el estado
        if ($this->fulfilled_at) {
            $timeline['fulfilled_at'] = $this->formatDateTime($this->fulfilled_at);
        }

        if ($this->cancelled_at) {
            $timeline['cancelled_at'] = $this->formatDateTime($this->cancelled_at);
        }

        if ($this->refunded_at) {
            $timeline['refunded_at'] = $this->formatDateTime($this->refunded_at);
        }

        if ($this->expires_at) {
            $timeline['expires_at'] = $this->formatDateTime($this->expires_at);
        }

        // En contexto admin, agregar información de procesamiento
        if ($context === self::CONTEXT_ADMIN) {
            $timeline['processing_time'] = $this->calculateProcessingTime();
            $timeline['fulfillment_time'] = $this->calculateFulfillmentTime();
        }

        return $timeline;
    }

    /**
     * Obtiene datos de notas según el contexto
     */
    private function getNotesData(string $context): array
    {
        $notes = [];

        // Notas del cliente siempre visibles
        if ($this->notes) {
            $notes['customer_notes'] = $this->notes;
        }

        // Notas administrativas solo en contextos apropiados
        if (in_array($context, [self::CONTEXT_ADMIN, self::CONTEXT_SUPPORT])) {
            if ($this->admin_notes) {
                $notes['admin_notes'] = $this->admin_notes;
            }

            if ($this->cancellation_reason) {
                $notes['cancellation_reason'] = $this->cancellation_reason;
            }

            if ($this->refund_reason) {
                $notes['refund_reason'] = $this->refund_reason;
            }
        }

        return $notes;
    }

    /**
     * Obtiene datos de analytics según el contexto
     */
    private function getAnalyticsData(string $context): array
    {
        if ($context !== self::CONTEXT_ADMIN && $context !== self::CONTEXT_ANALYTICS) {
            return [];
        }

        return [
            'order_value_score' => $this->calculateOrderValueScore(),
            'customer_lifetime_value' => $this->calculateCustomerLifetimeValue(),
            'fulfillment_efficiency' => $this->calculateFulfillmentEfficiency(),
            'payment_success_rate' => $this->calculatePaymentSuccessRate(),
            'refund_probability' => $this->calculateRefundProbability(),
            'seasonal_performance' => $this->getSeasonalPerformance(),
            'competitor_comparison' => $this->getCompetitorComparison(),
        ];
    }

    /**
     * Obtiene datos de configuración según el contexto
     */
    private function getConfigurationData(string $context): array
    {
        $configuration = [
            'order_type' => $this->type,
            'fulfillment_type' => $this->fulfillment_type,
            'requires_manual_review' => $this->requiresManualReview(),
            'priority_level' => $this->getPriorityLevel(),
        ];

        // En contexto admin, mostrar configuración completa
        if ($context === self::CONTEXT_ADMIN) {
            $configuration['auto_fulfillment'] = $this->metadata['auto_fulfillment'] ?? true;
            $configuration['fraud_check_passed'] = $this->metadata['fraud_check_passed'] ?? true;
            $configuration['special_instructions'] = $this->metadata['special_instructions'] ?? [];
        }

        return $configuration;
    }

    /**
     * Formatea fechas de manera consistente
     */
    private function formatDateTime(?Carbon $date): ?string
    {
        return $date ? $date->toISOString() : null;
    }

    /**
     * Calcula el margen de ganancia
     */
    private function calculateProfitMargin(): ?float
    {
        $cost = $this->getTotalCost();
        if (!$cost) return null;
        
        return round((($this->total - $cost) / $this->total) * 100, 2);
    }

    /**
     * Obtiene el desglose de costos
     */
    private function getCostBreakdown(): array
    {
        return [
            'product_cost' => $this->getProductCost(),
            'fulfillment_cost' => $this->getFulfillmentCost(),
            'payment_processing_cost' => $this->getPaymentProcessingCost(),
            'total_cost' => $this->getTotalCost(),
        ];
    }

    /**
     * Obtiene el costo total de productos
     */
    private function getProductCost(): float
    {
        if (!$this->relationLoaded('items')) return 0;
        
        return $this->items->sum(function($item) {
            return $item->product?->metadata['cost'] ?? 0;
        });
    }

    /**
     * Obtiene el costo de cumplimiento
     */
    private function getFulfillmentCost(): float
    {
        return $this->metadata['fulfillment_cost'] ?? 0;
    }

    /**
     * Obtiene el costo de procesamiento de pago
     */
    private function getPaymentProcessingCost(): float
    {
        return $this->payment?->fee ?? 0;
    }

    /**
     * Obtiene el costo total
     */
    private function getTotalCost(): float
    {
        return $this->getProductCost() + $this->getFulfillmentCost() + $this->getPaymentProcessingCost();
    }

    /**
     * Obtiene el tiempo estimado de entrega
     */
    private function getEstimatedDelivery(): string
    {
        return match($this->type) {
            Order::TYPE_GIFT, Order::TYPE_COINS => 'Instant',
            Order::TYPE_SUBSCRIPTION => '1-2 minutes',
            Order::TYPE_FEATURE, Order::TYPE_BOOST => '2-5 minutes',
            default => 'Variable',
        };
    }

    /**
     * Obtiene información de seguimiento
     */
    private function getTrackingInfo(): array
    {
        return $this->fulfillment_data['tracking'] ?? [];
    }

    /**
     * Calcula el tiempo de procesamiento
     */
    private function calculateProcessingTime(): ?int
    {
        if (!$this->fulfilled_at) return null;
        
        return $this->created_at->diffInMinutes($this->fulfilled_at);
    }

    /**
     * Calcula el tiempo de cumplimiento
     */
    private function calculateFulfillmentTime(): ?int
    {
        if (!$this->fulfilled_at) return null;
        
        return $this->created_at->diffInMinutes($this->fulfilled_at);
    }

    /**
     * Calcula el puntaje de valor de la orden
     */
    private function calculateOrderValueScore(): int
    {
        $score = 0;
        
        // Base score por valor total
        if ($this->total >= 100) $score += 40;
        elseif ($this->total >= 50) $score += 30;
        elseif ($this->total >= 20) $score += 20;
        else $score += 10;
        
        // Bonus por tipo de orden
        $typeBonus = match($this->type) {
            Order::TYPE_SUBSCRIPTION => 30,
            Order::TYPE_FEATURE => 20,
            Order::TYPE_BOOST => 15,
            Order::TYPE_GIFT => 10,
            Order::TYPE_COINS => 5,
            default => 0,
        };
        
        $score += $typeBonus;
        
        return min(100, $score);
    }

    /**
     * Calcula el valor de vida del cliente
     */
    private function calculateCustomerLifetimeValue(): float
    {
        // Implementación simplificada - en producción sería más compleja
        return $this->user?->orders()->sum('total') ?? 0;
    }

    /**
     * Calcula la eficiencia de cumplimiento
     */
    private function calculateFulfillmentEfficiency(): float
    {
        if (!$this->fulfilled_at) return 0;
        
        $expectedTime = match($this->type) {
            Order::TYPE_GIFT, Order::TYPE_COINS => 1, // 1 minuto
            Order::TYPE_SUBSCRIPTION => 2, // 2 minutos
            Order::TYPE_FEATURE, Order::TYPE_BOOST => 5, // 5 minutos
            default => 10,
        };
        
        $actualTime = $this->calculateFulfillmentTime();
        if (!$actualTime) return 0;
        
        return round(($expectedTime / $actualTime) * 100, 2);
    }

    /**
     * Calcula la tasa de éxito del pago
     */
    private function calculatePaymentSuccessRate(): float
    {
        // Implementación simplificada
        return $this->is_paid ? 100.0 : 0.0;
    }

    /**
     * Calcula la probabilidad de reembolso
     */
    private function calculateRefundProbability(): float
    {
        $probability = 0;
        
        // Factores que aumentan la probabilidad de reembolso
        if ($this->total > 100) $probability += 20;
        if ($this->type === Order::TYPE_SUBSCRIPTION) $probability += 15;
        if ($this->retry_count > 0) $probability += 25;
        
        return min(100, $probability);
    }

    /**
     * Obtiene el rendimiento estacional
     */
    private function getSeasonalPerformance(): array
    {
        $month = $this->created_at->month;
        
        return [
            'season' => match(true) {
                in_array($month, [12, 1, 2]) => 'Winter',
                in_array($month, [3, 4, 5]) => 'Spring',
                in_array($month, [6, 7, 8]) => 'Summer',
                default => 'Fall',
            },
            'is_peak_season' => in_array($month, [12, 2, 6]),
        ];
    }

    /**
     * Obtiene comparación con competidores
     */
    private function getCompetitorComparison(): array
    {
        return [
            'price_position' => $this->total > 50 ? 'premium' : 'competitive',
            'fulfillment_speed' => $this->calculateFulfillmentEfficiency() > 80 ? 'fast' : 'average',
        ];
    }

    /**
     * Verifica si requiere revisión manual
     */
    private function requiresManualReview(): bool
    {
        return $this->metadata['requires_manual_review'] ?? false;
    }

    /**
     * Obtiene el nivel de prioridad
     */
    private function getPriorityLevel(): string
    {
        if ($this->total >= 100) return 'high';
        if ($this->total >= 50) return 'medium';
        return 'normal';
    }
}
