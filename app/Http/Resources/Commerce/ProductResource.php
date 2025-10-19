<?php

declare(strict_types=1);

namespace App\Http\Resources\Commerce;

use App\Models\Commerce\Product;
use App\Models\Commerce\Plan;
use App\Models\Commerce\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * ProductResource - Resource para serialización de datos de productos
 * 
 * Este resource maneja la transformación y serialización de datos del modelo Product
 * para respuestas de API, incluyendo filtrado de información sensible, formateo
 * de datos y agregación de información relacionada.
 * 
 * Funcionalidades:
 * - Transformación de datos de productos para API
 * - Filtrado de información sensible según contexto
 * - Formateo de precios y datos especiales
 * - Agregación de información de categorías y tipos
 * - Cálculo de métricas en tiempo real
 * - Soporte para diferentes contextos de visualización
 * - Compatibilidad con diferentes tipos de productos (gifts, plans, coins, features, boosts)
 * - Manejo de disponibilidad y restricciones de productos
 * - Soporte para precios regionales y promociones
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class ProductResource extends JsonResource
{
    /**
     * Contextos de visualización disponibles
     */
    public const CONTEXT_CATALOG = 'catalog';     // Catálogo público de productos
    public const CONTEXT_PURCHASE = 'purchase';   // Vista para compra
    public const CONTEXT_ADMIN = 'admin';         // Vista administrativa
    public const CONTEXT_ANALYTICS = 'analytics'; // Vista de analytics
    public const CONTEXT_RECOMMENDATION = 'recommendation'; // Recomendaciones personalizadas

    /**
     * Campos que nunca se deben exponer públicamente
     */
    private const PRIVATE_FIELDS = [
        'reserved_quantity',
        'metadata',
        'admin_notes',
        'internal_sku'
    ];

    /**
     * Campos sensibles que solo se muestran en contexto admin
     */
    private const SENSITIVE_FIELDS = [
        'purchase_count',
        'view_count',
        'popularity_score',
        'rating_average',
        'rating_count',
        'last_purchased_at'
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
            // Información básica del producto
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'type' => $this->type,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Información de precios
            'pricing' => $this->getPricingData($context),
            
            // Información de disponibilidad
            'availability' => $this->getAvailabilityData($context),
            
            // Información de inventario
            'inventory' => $this->getInventoryData($context),
            
            // Información de características
            'features' => $this->getFeaturesData($context),
            
            // Información de restricciones
            'restrictions' => $this->getRestrictionsData($context),
            
            // Información de popularidad y ratings
            'popularity' => $this->getPopularityData($context),
            
            // Información específica del tipo de producto
            'product_specific' => $this->getProductSpecificData($context),
            
            // Información de recomendaciones
            'recommendations' => $this->getRecommendationsData($context),
            
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

        // Verificar si es para recomendaciones
        if ($request->has('recommendations') || $request->routeIs('*.recommendations')) {
            return self::CONTEXT_RECOMMENDATION;
        }

        // Verificar si es para compra
        if ($request->has('purchase') || $request->routeIs('*.purchase')) {
            return self::CONTEXT_PURCHASE;
        }

        // Por defecto, contexto de catálogo
        return self::CONTEXT_CATALOG;
    }

    /**
     * Obtiene datos de precios según el contexto
     */
    private function getPricingData(string $context): array
    {
        $basePrice = [
            'base_price' => $this->price,
            'currency' => $this->currency,
            'formatted_price' => $this->formatted_price,
        ];

        // Agregar precios regionales si están disponibles
        if (!empty($this->pricing_tiers)) {
            $basePrice['regional_pricing'] = $this->pricing_tiers;
        }

        // En contexto de compra, agregar información adicional
        if ($context === self::CONTEXT_PURCHASE) {
            $basePrice['tax_included'] = false;
            $basePrice['processing_fee'] = $this->calculateProcessingFee();
        }

        // En contexto admin, mostrar información completa
        if ($context === self::CONTEXT_ADMIN) {
            $basePrice['cost'] = $this->metadata['cost'] ?? null;
            $basePrice['margin'] = $this->calculateMargin();
            $basePrice['profit'] = $this->calculateProfit();
        }

        return $basePrice;
    }

    /**
     * Obtiene datos de disponibilidad según el contexto
     */
    private function getAvailabilityData(string $context): array
    {
        $availability = [
            'is_available' => $this->is_available,
            'is_in_stock' => $this->is_in_stock,
            'availability_type' => $this->availability,
        ];

        // Agregar fechas de disponibilidad si están definidas
        if ($this->available_from) {
            $availability['available_from'] = $this->formatDateTime($this->available_from);
        }

        if ($this->available_until) {
            $availability['available_until'] = $this->formatDateTime($this->available_until);
        }

        // En contexto de compra, agregar información de tiempo estimado
        if ($context === self::CONTEXT_PURCHASE) {
            $availability['estimated_delivery'] = $this->getEstimatedDelivery();
            $availability['fulfillment_type'] = $this->getFulfillmentType();
        }

        return $availability;
    }

    /**
     * Obtiene datos de inventario según el contexto
     */
    private function getInventoryData(string $context): array
    {
        $inventory = [
            'is_limited_stock' => !is_null($this->stock_quantity),
            'is_unlimited' => is_null($this->stock_quantity),
        ];

        // Solo mostrar información de stock en contextos apropiados
        if ($context === self::CONTEXT_ADMIN || $context === self::CONTEXT_PURCHASE) {
            if (!is_null($this->stock_quantity)) {
                $inventory['total_stock'] = $this->stock_quantity;
                $inventory['available_stock'] = $this->available_stock;
                $inventory['reserved_stock'] = $this->reserved_quantity;
                $inventory['stock_percentage'] = $this->calculateStockPercentage();
            }
        }

        return $inventory;
    }

    /**
     * Obtiene datos de características según el contexto
     */
    private function getFeaturesData(string $context): array
    {
        $features = [
            'is_featured' => $this->is_featured,
            'is_trending' => $this->is_trending,
            'requires_subscription' => $this->requires_subscription,
        ];

        // Agregar características específicas del producto
        if (!empty($this->features)) {
            $features['product_features'] = $this->features;
        }

        // Agregar tags para búsqueda y filtrado
        if (!empty($this->tags)) {
            $features['tags'] = $this->tags;
        }

        return $features;
    }

    /**
     * Obtiene datos de restricciones según el contexto
     */
    private function getRestrictionsData(string $context): array
    {
        $restrictions = [
            'visibility_level' => $this->visibility,
            'requires_subscription' => $this->requires_subscription,
        ];

        // Agregar restricciones específicas si están definidas
        if (!empty($this->restrictions)) {
            $restrictions['age_restrictions'] = $this->restrictions['age'] ?? null;
            $restrictions['region_restrictions'] = $this->restrictions['regions'] ?? null;
            $restrictions['user_type_restrictions'] = $this->restrictions['user_types'] ?? null;
        }

        return $restrictions;
    }

    /**
     * Obtiene datos de popularidad según el contexto
     */
    private function getPopularityData(string $context): array
    {
        $popularity = [
            'is_featured' => $this->is_featured,
            'is_trending' => $this->is_trending,
        ];

        // Solo mostrar métricas detalladas en contextos apropiados
        if ($context === self::CONTEXT_ADMIN || $context === self::CONTEXT_ANALYTICS) {
            $popularity['popularity_score'] = $this->popularity_score;
            $popularity['popularity_percentage'] = $this->popularity_percentage;
            $popularity['view_count'] = $this->view_count;
            $popularity['purchase_count'] = $this->purchase_count;
            $popularity['rating_average'] = $this->rating_average;
            $popularity['rating_count'] = $this->rating_count;
            $popularity['last_purchased_at'] = $this->last_purchased_at ? $this->formatDateTime($this->last_purchased_at) : null;
        }

        return $popularity;
    }

    /**
     * Obtiene datos específicos del tipo de producto
     */
    private function getProductSpecificData(string $context): array
    {
        $specific = [];

        // Datos específicos según el tipo de producto
        switch ($this->category) {
            case Product::CATEGORY_GIFT:
                $specific = $this->getGiftSpecificData($context);
                break;
            case Product::CATEGORY_SUBSCRIPTION:
                $specific = $this->getSubscriptionSpecificData($context);
                break;
            case Product::CATEGORY_COINS:
                $specific = $this->getCoinsSpecificData($context);
                break;
            case Product::CATEGORY_FEATURE:
                $specific = $this->getFeatureSpecificData($context);
                break;
            case Product::CATEGORY_BOOST:
                $specific = $this->getBoostSpecificData($context);
                break;
        }

        return $specific;
    }

    /**
     * Obtiene datos específicos para regalos
     */
    private function getGiftSpecificData(string $context): array
    {
        return [
            'gift_type' => $this->type,
            'is_animated' => $this->metadata['animated'] ?? false,
            'animation_duration' => $this->metadata['animation_duration'] ?? null,
            'sound_effect' => $this->metadata['sound_effect'] ?? null,
            'is_exclusive' => $this->metadata['exclusive'] ?? false,
            'collection' => $this->metadata['collection'] ?? null,
        ];
    }

    /**
     * Obtiene datos específicos para suscripciones
     */
    private function getSubscriptionSpecificData(string $context): array
    {
        $plan = $this->productable;
        
        if (!$plan instanceof Plan) {
            return [];
        }

        return [
            'plan_name' => $plan->name,
            'plan_tier' => $plan->tier,
            'billing_cycle' => $plan->type,
            'trial_days' => $plan->trial_days,
            'features' => $plan->getAvailableFeatures(),
            'limits' => $plan->limits,
            'is_popular' => $plan->is_popular,
            'subscriber_count' => $context === self::CONTEXT_ADMIN ? $plan->subscriber_count : null,
        ];
    }

    /**
     * Obtiene datos específicos para monedas
     */
    private function getCoinsSpecificData(string $context): array
    {
        return [
            'coin_amount' => $this->metadata['coin_amount'] ?? null,
            'bonus_percentage' => $this->metadata['bonus_percentage'] ?? 0,
            'bonus_coins' => $this->metadata['bonus_coins'] ?? 0,
            'total_coins' => $this->metadata['total_coins'] ?? null,
            'is_bonus_package' => $this->metadata['bonus_package'] ?? false,
        ];
    }

    /**
     * Obtiene datos específicos para características
     */
    private function getFeatureSpecificData(string $context): array
    {
        return [
            'feature_type' => $this->type,
            'duration_days' => $this->metadata['duration_days'] ?? null,
            'is_permanent' => $this->metadata['permanent'] ?? false,
            'activation_method' => $this->metadata['activation_method'] ?? 'instant',
        ];
    }

    /**
     * Obtiene datos específicos para boosts
     */
    private function getBoostSpecificData(string $context): array
    {
        return [
            'boost_type' => $this->type,
            'duration_hours' => $this->metadata['duration_hours'] ?? 24,
            'visibility_multiplier' => $this->metadata['visibility_multiplier'] ?? 1,
            'priority_level' => $this->metadata['priority_level'] ?? 'normal',
        ];
    }

    /**
     * Obtiene datos de recomendaciones según el contexto
     */
    private function getRecommendationsData(string $context): array
    {
        if ($context !== self::CONTEXT_RECOMMENDATION && $context !== self::CONTEXT_CATALOG) {
            return [];
        }

        return [
            'recommendation_score' => $this->calculateRecommendationScore(),
            'recommendation_reasons' => $this->getRecommendationReasons(),
            'similar_products' => $this->getSimilarProducts(),
            'frequently_bought_together' => $this->getFrequentlyBoughtTogether(),
        ];
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
            'conversion_rate' => $this->calculateConversionRate(),
            'revenue_generated' => $this->calculateRevenueGenerated(),
            'customer_satisfaction' => $this->rating_average,
            'return_rate' => $this->calculateReturnRate(),
            'seasonal_performance' => $this->getSeasonalPerformance(),
            'competitor_analysis' => $this->getCompetitorAnalysis(),
        ];
    }

    /**
     * Obtiene datos de configuración según el contexto
     */
    private function getConfigurationData(string $context): array
    {
        $configuration = [
            'is_purchasable' => $this->isPurchasable(),
            'requires_verification' => $this->requiresVerification(),
            'max_quantity_per_order' => $this->getMaxQuantityPerOrder(),
        ];

        // En contexto admin, mostrar configuración completa
        if ($context === self::CONTEXT_ADMIN) {
            $configuration['auto_fulfillment'] = $this->metadata['auto_fulfillment'] ?? true;
            $configuration['requires_approval'] = $this->metadata['requires_approval'] ?? false;
            $configuration['notification_settings'] = $this->metadata['notifications'] ?? [];
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
     * Calcula la tarifa de procesamiento
     */
    private function calculateProcessingFee(): float
    {
        return round($this->price * 0.029 + 0.30, 2); // Stripe-like fees
    }

    /**
     * Calcula el margen de ganancia
     */
    private function calculateMargin(): ?float
    {
        $cost = $this->metadata['cost'] ?? null;
        if (!$cost) return null;
        
        return round((($this->price - $cost) / $this->price) * 100, 2);
    }

    /**
     * Calcula la ganancia
     */
    private function calculateProfit(): ?float
    {
        $cost = $this->metadata['cost'] ?? null;
        if (!$cost) return null;
        
        return round($this->price - $cost, 2);
    }

    /**
     * Obtiene el tiempo estimado de entrega
     */
    private function getEstimatedDelivery(): string
    {
        return match($this->category) {
            Product::CATEGORY_GIFT, Product::CATEGORY_COINS => 'Instant',
            Product::CATEGORY_SUBSCRIPTION => '1-2 minutes',
            Product::CATEGORY_FEATURE, Product::CATEGORY_BOOST => '2-5 minutes',
            default => 'Variable',
        };
    }

    /**
     * Obtiene el tipo de cumplimiento
     */
    private function getFulfillmentType(): string
    {
        return match($this->category) {
            Product::CATEGORY_GIFT, Product::CATEGORY_COINS => 'instant',
            Product::CATEGORY_SUBSCRIPTION => 'scheduled',
            Product::CATEGORY_FEATURE, Product::CATEGORY_BOOST => 'manual',
            default => 'instant',
        };
    }

    /**
     * Calcula el porcentaje de stock disponible
     */
    private function calculateStockPercentage(): ?int
    {
        if (is_null($this->stock_quantity) || $this->stock_quantity == 0) {
            return null;
        }
        
        return intval(($this->available_stock / $this->stock_quantity) * 100);
    }

    /**
     * Calcula el puntaje de recomendación
     */
    private function calculateRecommendationScore(): int
    {
        $score = 0;
        
        // Base score por popularidad
        $score += min(50, $this->popularity_score / 20);
        
        // Bonus por rating
        if ($this->rating_average > 0) {
            $score += ($this->rating_average / 5) * 30;
        }
        
        // Bonus por trending
        if ($this->is_trending) {
            $score += 20;
        }
        
        return min(100, $score);
    }

    /**
     * Obtiene las razones de recomendación
     */
    private function getRecommendationReasons(): array
    {
        $reasons = [];
        
        if ($this->is_trending) {
            $reasons[] = 'Trending now';
        }
        
        if ($this->rating_average >= 4.5) {
            $reasons[] = 'Highly rated';
        }
        
        if ($this->is_featured) {
            $reasons[] = 'Featured product';
        }
        
        if ($this->purchase_count > 100) {
            $reasons[] = 'Popular choice';
        }
        
        return $reasons;
    }

    /**
     * Obtiene productos similares
     */
    private function getSimilarProducts(): array
    {
        // Esta implementación sería más compleja en producción
        return Product::where('category', $this->category)
            ->where('id', '!=', $this->id)
            ->where('status', Product::STATUS_ACTIVE)
            ->limit(5)
            ->get(['id', 'name', 'price', 'sku'])
            ->toArray();
    }

    /**
     * Obtiene productos frecuentemente comprados juntos
     */
    private function getFrequentlyBoughtTogether(): array
    {
        // Implementación simplificada - en producción sería más compleja
        return [];
    }

    /**
     * Calcula la tasa de conversión
     */
    private function calculateConversionRate(): float
    {
        if ($this->view_count == 0) return 0;
        
        return round(($this->purchase_count / $this->view_count) * 100, 2);
    }

    /**
     * Calcula los ingresos generados
     */
    private function calculateRevenueGenerated(): float
    {
        return round($this->purchase_count * $this->price, 2);
    }

    /**
     * Calcula la tasa de retorno
     */
    private function calculateReturnRate(): float
    {
        // Implementación simplificada
        return round(($this->rating_count / max(1, $this->purchase_count)) * 100, 2);
    }

    /**
     * Obtiene el rendimiento estacional
     */
    private function getSeasonalPerformance(): array
    {
        // Implementación simplificada
        return [
            'peak_season' => 'Holidays',
            'performance_trend' => 'stable',
        ];
    }

    /**
     * Obtiene análisis de competencia
     */
    private function getCompetitorAnalysis(): array
    {
        // Implementación simplificada
        return [
            'market_position' => 'competitive',
            'price_comparison' => 'average',
        ];
    }

    /**
     * Verifica si requiere verificación
     */
    private function requiresVerification(): bool
    {
        return $this->metadata['requires_verification'] ?? false;
    }

    /**
     * Obtiene la cantidad máxima por orden
     */
    private function getMaxQuantityPerOrder(): int
    {
        return $this->metadata['max_quantity_per_order'] ?? 10;
    }
}
