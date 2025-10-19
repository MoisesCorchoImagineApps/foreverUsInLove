<?php

declare(strict_types=1);

namespace App\Http\Resources\Matching;

use App\Models\Matching\Discovery;
use App\Http\Resources\Auth\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * DiscoveryResource - Resource para serialización de datos de discovery
 * 
 * Este resource maneja la transformación y serialización de datos del modelo Discovery
 * para respuestas de API, incluyendo filtrado de información sensible, formateo
 * de datos y agregación de información relacionada.
 * 
 * Funcionalidades:
 * - Transformación de datos de discovery para API
 * - Filtrado de información sensible según contexto
 * - Formateo de fechas y datos especiales
 * - Agregación de información de usuario y sesión
 * - Cálculo de métricas en tiempo real
 * - Soporte para diferentes contextos de visualización
 * - Compatibilidad con sistema de discovery avanzado
 * - Manejo de 8 modos de discovery diferentes
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class DiscoveryResource extends JsonResource
{
    /**
     * Contextos de visualización disponibles
     */
    public const CONTEXT_OWN = 'own';           // Sesión propia del usuario
    public const CONTEXT_PUBLIC = 'public';     // Sesión pública para analytics
    public const CONTEXT_ADMIN = 'admin';       // Vista administrativa
    public const CONTEXT_ANALYTICS = 'analytics'; // Vista de analytics

    /**
     * Campos que nunca se deben exponer públicamente
     */
    private const PRIVATE_FIELDS = [
        'session_id',
        'algorithm_params',
        'metadata',
        'filter_criteria'
    ];

    /**
     * Campos sensibles que solo se muestran al propietario
     */
    private const SENSITIVE_FIELDS = [
        'cards_liked',
        'cards_disliked',
        'cards_super_liked',
        'matches_created'
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
            // Información básica de la sesión
            'id' => $this->session_id,
            'mode' => $this->mode,
            'status' => $this->status,
            'is_premium' => $this->is_premium,
            'boost_id' => $this->boost_id,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Información temporal
            'timeline' => $this->getTimelineData($context),
            
            // Información de límites y restricciones
            'limits' => $this->getLimitsData($context),
            
            // Información de progreso
            'progress' => $this->getProgressData($context),
            
            // Información de engagement
            'engagement' => $this->getEngagementData($context),
            
            // Información de métricas
            'metrics' => $this->getMetrics($context),
            
            // Información de configuración
            'configuration' => $this->getConfigurationData($context),
            
            // Información relacionada
            'related_data' => $this->getRelatedData($context),
            
            // Recomendaciones y sugerencias
            'recommendations' => $this->getRecommendations($context),
        ];
    }

    /**
     * Obtiene información temporal de la sesión
     */
    private function getTimelineData(string $context): array
    {
        $data = [
            'started_at' => $this->formatDateTime($this->started_at),
            'ended_at' => $this->formatDateTime($this->ended_at),
            'duration_seconds' => $this->duration_seconds,
            'duration_minutes' => $this->getDurationMinutesAttribute(),
        ];

        // Solo mostrar información detallada en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['remaining_time'] = $this->getRemainingTimeAttribute();
            $data['remaining_time_minutes'] = (int) ceil($this->getRemainingTimeAttribute() / 60);
            $data['is_active'] = $this->getIsActiveAttribute();
            $data['is_expired'] = $this->isExpired();
            $data['is_completed'] = $this->isCompleted();
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de límites y restricciones
     */
    private function getLimitsData(string $context): array
    {
        $data = [
            'max_duration_seconds' => $this->max_duration_seconds,
            'max_duration_minutes' => (int) ceil($this->max_duration_seconds / 60),
            'max_cards' => $this->max_cards,
        ];

        // Solo mostrar información detallada en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['remaining_cards'] = $this->getRemainingCardsAttribute();
            $data['has_time_remaining'] = $this->getHasTimeRemainingAttribute();
            $data['has_cards_remaining'] = $this->getHasCardsRemainingAttribute();
            $data['can_show_more_cards'] = $this->canShowMoreCards();
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de progreso de la sesión
     */
    private function getProgressData(string $context): array
    {
        $data = [
            'cards_shown' => $this->cards_shown,
            'progress_percentage' => $this->getProgressPercentage(),
        ];

        // Solo mostrar conteos detallados en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['cards_liked'] = $this->cards_liked;
            $data['cards_disliked'] = $this->cards_disliked;
            $data['cards_super_liked'] = $this->cards_super_liked;
            $data['cards_skipped'] = $this->cards_skipped;
            $data['matches_created'] = $this->matches_created;
        }

        // En contexto público, solo mostrar si han interactuado
        if ($context === self::CONTEXT_PUBLIC) {
            $data['has_interactions'] = $this->cards_liked > 0 || $this->cards_disliked > 0;
            $data['has_matches'] = $this->matches_created > 0;
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de engagement
     */
    private function getEngagementData(string $context): array
    {
        $data = [
            'engagement_rate' => $this->getEngagementRateAttribute(),
            'like_rate' => $this->getLikeRateAttribute(),
            'match_rate' => $this->getMatchRateAttribute(),
        ];

        // Solo mostrar métricas detalladas en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['engagement_metrics'] = [
                'total_actions' => $this->cards_liked + $this->cards_disliked + $this->cards_super_liked,
                'action_breakdown' => [
                    'likes' => $this->cards_liked,
                    'dislikes' => $this->cards_disliked,
                    'super_likes' => $this->cards_super_liked,
                    'skips' => $this->cards_skipped,
                ],
                'conversion_rates' => [
                    'likes_to_matches' => $this->getMatchRateAttribute(),
                    'cards_to_actions' => $this->getEngagementRateAttribute(),
                ],
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene métricas de la sesión
     */
    private function getMetrics(string $context): array
    {
        $data = [];

        // Métricas básicas siempre disponibles
        $data['basic'] = [
            'cards_shown' => $this->cards_shown,
            'engagement_rate' => $this->getEngagementRateAttribute(),
            'like_rate' => $this->getLikeRateAttribute(),
            'match_rate' => $this->getMatchRateAttribute(),
            'duration_minutes' => $this->getDurationMinutesAttribute(),
        ];

        // Métricas detalladas solo para contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['detailed'] = [
                'cards_liked' => $this->cards_liked,
                'cards_disliked' => $this->cards_disliked,
                'cards_super_liked' => $this->cards_super_liked,
                'cards_skipped' => $this->cards_skipped,
                'matches_created' => $this->matches_created,
                'remaining_time' => $this->getRemainingTimeAttribute(),
                'remaining_cards' => $this->getRemainingCardsAttribute(),
                'is_active' => $this->getIsActiveAttribute(),
                'is_completed' => $this->isCompleted(),
                'is_expired' => $this->isExpired(),
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de configuración
     */
    private function getConfigurationData(string $context): array
    {
        $data = [
            'mode' => $this->mode,
            'mode_description' => $this->getModeDescription(),
            'is_premium' => $this->is_premium,
        ];

        // Solo mostrar configuración detallada en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['filter_criteria'] = $this->getFilterCriteria($context);
            $data['algorithm_params'] = $this->getAlgorithmParams($context);
            $data['boost_info'] = $this->getBoostInfo();
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene criterios de filtro
     */
    private function getFilterCriteria(string $context): array
    {
        if (!$this->filter_criteria) {
            return [];
        }

        $data = $this->filter_criteria;

        // En contexto público, simplificar información sensible
        if ($context === self::CONTEXT_PUBLIC) {
            $data = array_intersect_key($data, array_flip([
                'age_range',
                'interests',
                'location_type'
            ]));
        }

        return $data;
    }

    /**
     * Obtiene parámetros del algoritmo
     */
    private function getAlgorithmParams(string $context): array
    {
        if (!$this->algorithm_params || $context === self::CONTEXT_PUBLIC) {
            return [];
        }

        return $this->algorithm_params;
    }

    /**
     * Obtiene información del boost
     */
    private function getBoostInfo(): ?array
    {
        if (!$this->boost_id) {
            return null;
        }

        return [
            'boost_id' => $this->boost_id,
            'is_boosted' => true,
            'boost_type' => $this->getBoostType(),
        ];
    }

    /**
     * Obtiene datos relacionados (usuario, likes, etc.)
     */
    private function getRelatedData(string $context): array
    {
        $data = [];

        // Información del usuario
        if ($this->relationLoaded('user')) {
            $data['user'] = [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'is_premium' => $this->user->is_premium ?? false,
                'last_active_at' => $this->formatDateTime($this->user->last_active_at),
            ];
        }

        // Información de likes de esta sesión
        if ($this->relationLoaded('likes')) {
            $data['likes'] = [
                'count' => $this->likes->count(),
                'types' => $this->likes->pluck('type')->unique()->values()->toArray(),
                'recent_likes' => $context === self::CONTEXT_OWN ? $this->getRecentLikes() : null,
            ];
        }

        // Información de vistas de esta sesión
        if ($this->relationLoaded('views')) {
            $data['views'] = [
                'count' => $this->views->count(),
                'unique_profiles_viewed' => $this->views->pluck('viewed_user_id')->unique()->count(),
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene recomendaciones para la sesión
     */
    private function getRecommendations(string $context): array
    {
        // Solo mostrar recomendaciones en contexto propio
        if ($context !== self::CONTEXT_OWN) {
            return [];
        }

        $recommendations = [];

        // Recomendaciones basadas en engagement bajo
        if ($this->getEngagementRateAttribute() < 30) {
            $recommendations[] = [
                'type' => 'engagement',
                'priority' => 'medium',
                'title' => 'Aumenta tu engagement',
                'description' => 'Interactúa más con los perfiles para mejores resultados',
                'action' => 'increase_engagement'
            ];
        }

        // Recomendaciones basadas en tiempo restante
        if ($this->getRemainingTimeAttribute() < 300) { // 5 minutos
            $recommendations[] = [
                'type' => 'time',
                'priority' => 'high',
                'title' => 'Tiempo limitado',
                'description' => 'Tu sesión expirará pronto, aprovecha el tiempo restante',
                'action' => 'use_remaining_time'
            ];
        }

        // Recomendaciones basadas en tarjetas restantes
        if ($this->getRemainingCardsAttribute() < 5) {
            $recommendations[] = [
                'type' => 'cards',
                'priority' => 'medium',
                'title' => 'Pocas tarjetas restantes',
                'description' => 'Te quedan pocas tarjetas en esta sesión',
                'action' => 'start_new_session'
            ];
        }

        // Recomendaciones basadas en modo
        if ($this->mode === 'standard' && $this->getMatchRateAttribute() < 10) {
            $recommendations[] = [
                'type' => 'mode',
                'priority' => 'low',
                'title' => 'Prueba otros modos',
                'description' => 'Explora otros modos de discovery para mejores matches',
                'action' => 'try_other_modes'
            ];
        }

        return $recommendations;
    }

    /**
     * Determina el contexto de visualización basado en la request
     */
    private function determineContext(Request $request): string
    {
        $user = $request->user();
        
        // Si no hay usuario autenticado, es contexto público
        if (!$user) {
            return self::CONTEXT_PUBLIC;
        }

        // Si la sesión pertenece al usuario autenticado, es contexto propio
        if ($this->user_id === $user->id) {
            return self::CONTEXT_OWN;
        }

        // Si es admin, contexto administrativo
        if ($user->is_admin ?? false) {
            // Si está en ruta de analytics, contexto específico
            if (str_contains($request->path(), 'analytics')) {
                return self::CONTEXT_ANALYTICS;
            }
            return self::CONTEXT_ADMIN;
        }

        // Por defecto, contexto público
        return self::CONTEXT_PUBLIC;
    }

    /**
     * Formatea una fecha y hora para la respuesta
     */
    private function formatDateTime(?Carbon $dateTime): ?string
    {
        return $dateTime?->toISOString();
    }

    /**
     * Obtiene descripción del modo de discovery
     */
    private function getModeDescription(): string
    {
        return match ($this->mode) {
            'standard' => 'Discovery estándar',
            'explore' => 'Modo exploración',
            'boost' => 'Discovery con boost',
            'local' => 'Discovery local',
            'global' => 'Discovery global',
            'interest' => 'Basado en intereses',
            'second_chance' => 'Segunda oportunidad',
            'trending' => 'Perfiles trending',
            default => 'Modo desconocido',
        };
    }

    /**
     * Obtiene tipo de boost
     */
    private function getBoostType(): string
    {
        // Placeholder - en implementación real se obtendría del boost_id
        return 'premium_boost';
    }

    /**
     * Obtiene porcentaje de progreso
     */
    private function getProgressPercentage(): float
    {
        if ($this->max_cards === 0) {
            return 0.0;
        }

        return round(($this->cards_shown / $this->max_cards) * 100, 2);
    }

    /**
     * Obtiene likes recientes
     */
    private function getRecentLikes(): array
    {
        return $this->likes->take(5)->map(function ($like) {
            return [
                'type' => $like->type,
                'created_at' => $like->created_at?->toISOString(),
                'is_mutual' => $like->is_mutual,
            ];
        })->toArray();
    }

    /**
     * Filtra valores nulos de un array
     */
    private function filterNullValues(array $data): array
    {
        return array_filter($data, function ($value) {
            return $value !== null;
        });
    }

    /**
     * Obtiene metadatos adicionales para la respuesta
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'context' => $this->determineContext($request),
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0',
                'cache_ttl' => $this->getCacheTTL(),
                'discovery_mode_info' => $this->getDiscoveryModeInfo(),
            ]
        ];
    }

    /**
     * Obtiene el TTL de cache basado en el contexto
     */
    private function getCacheTTL(): int
    {
        $context = $this->determineContext(request());
        
        return match ($context) {
            self::CONTEXT_OWN => 300,           // 5 minutos para sesión propia
            self::CONTEXT_PUBLIC => 1800,       // 30 minutos para sesión pública
            self::CONTEXT_ADMIN => 60,          // 1 minuto para vista admin
            self::CONTEXT_ANALYTICS => 900,     // 15 minutos para analytics
            default => 600,                     // 10 minutos por defecto
        };
    }

    /**
     * Obtiene información del modo de discovery
     */
    private function getDiscoveryModeInfo(): array
    {
        return [
            'mode' => $this->mode,
            'description' => $this->getModeDescription(),
            'is_premium_mode' => in_array($this->mode, [
                'boost',
                'global',
                'trending'
            ]),
            'features' => $this->getModeFeatures(),
        ];
    }

    /**
     * Obtiene características del modo
     */
    private function getModeFeatures(): array
    {
        return match ($this->mode) {
            'standard' => ['basic_matching', 'compatibility_scoring'],
            'explore' => ['diverse_profiles', 'expanded_criteria', 'novelty_matching'],
            'boost' => ['premium_visibility', 'priority_placement', 'enhanced_algorithms'],
            'local' => ['geographic_focus', 'proximity_matching', 'local_events'],
            'global' => ['worldwide_reach', 'cultural_matching', 'international_focus'],
            'interest' => ['shared_hobbies', 'activity_matching', 'interest_scoring'],
            'second_chance' => ['reconsideration', 'profile_updates', 'time_based'],
            'trending' => ['popularity_boost', 'engagement_focus', 'viral_profiles'],
            default => ['basic_matching'],
        };
    }
}
