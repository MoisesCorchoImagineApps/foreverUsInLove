<?php

declare(strict_types=1);

namespace App\Http\Resources\Matching;

use App\Models\Matching\Match;
use App\Http\Resources\Auth\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * MatchResource - Resource para serialización de datos de matches
 * 
 * Este resource maneja la transformación y serialización de datos del modelo Match
 * para respuestas de API, incluyendo filtrado de información sensible, formateo
 * de datos y agregación de información relacionada.
 * 
 * Funcionalidades:
 * - Transformación de datos de match para API
 * - Filtrado de información sensible según contexto
 * - Formateo de fechas y datos especiales
 * - Agregación de información de usuarios y mensajes
 * - Cálculo de métricas en tiempo real
 * - Soporte para diferentes contextos de visualización
 * - Compatibilidad con sistema de matching avanzado
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class MatchResource extends JsonResource
{
    /**
     * Contextos de visualización disponibles
     */
    public const CONTEXT_OWN = 'own';           // Match propio del usuario
    public const CONTEXT_PUBLIC = 'public';     // Match público para discovery
    public const CONTEXT_ADMIN = 'admin';       // Vista administrativa
    public const CONTEXT_ANALYTICS = 'analytics'; // Vista de analytics

    /**
     * Campos que nunca se deben exponer públicamente
     */
    private const PRIVATE_FIELDS = [
        'match_id',
        'compatibility_factors',
        'match_metadata',
        'unmatched_by',
        'unmatched_reason',
        'notification_sent'
    ];

    /**
     * Campos sensibles que solo se muestran al propietario
     */
    private const SENSITIVE_FIELDS = [
        'unmatched_at',
        'interaction_count',
        'message_count'
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
            // Información básica del match
            'id' => $this->match_id,
            'status' => $this->status,
            'compatibility_score' => $this->compatibility_score,
            'match_source' => $this->match_source,
            'is_featured' => $this->is_featured,
            'created_at' => $this->formatDateTime($this->matched_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Información de usuarios
            'users' => $this->getUsersData($context),
            
            // Información de compatibilidad
            'compatibility' => $this->getCompatibilityData($context),
            
            // Información de interacción
            'interaction' => $this->getInteractionData($context),
            
            // Información de engagement
            'engagement' => $this->getEngagementData($context),
            
            // Información de calidad del match
            'quality' => $this->getQualityData($context),
            
            // Información temporal
            'timeline' => $this->getTimelineData($context),
            
            // Información relacionada
            'related_data' => $this->getRelatedData($context),
            
            // Métricas y estadísticas
            'metrics' => $this->getMetrics($context),
            
            // Recomendaciones y sugerencias
            'recommendations' => $this->getRecommendations($context),
        ];
    }

    /**
     * Obtiene datos de usuarios del match
     */
    private function getUsersData(string $context): array
    {
        $data = [];

        // Usuario 1
        if ($this->relationLoaded('user1')) {
            $data['user1'] = $this->getUserData($this->user1, $context, 1);
        }

        // Usuario 2
        if ($this->relationLoaded('user2')) {
            $data['user2'] = $this->getUserData($this->user2, $context, 2);
        }

        // Información del otro usuario (para contexto de matching)
        if ($context === self::CONTEXT_OWN && $this->relationLoaded('user1') && $this->relationLoaded('user2')) {
            $currentUser = request()->user();
            $otherUser = $this->getOtherUser($currentUser->id);
            
            if ($otherUser) {
                $data['other_user'] = $this->getUserData($otherUser, $context, 'other');
            }
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene datos de un usuario específico
     */
    private function getUserData($user, string $context, $position): ?array
    {
        if (!$user) {
            return null;
        }

        // Usar UserResource para consistencia
        $userResource = match ($context) {
            self::CONTEXT_OWN => UserResource::profile($user, true),
            self::CONTEXT_PUBLIC => UserResource::profile($user, false),
            self::CONTEXT_ADMIN => UserResource::admin($user, true),
            default => UserResource::basic($user),
        };

        $userData = $userResource->toArray(request());
        
        // Agregar información específica del match
        $userData['match_position'] = $position;
        $userData['is_online'] = $user->last_active_at && $user->last_active_at->isAfter(now()->subMinutes(5));
        $userData['last_active'] = $user->last_active_at?->diffForHumans();

        return $userData;
    }

    /**
     * Obtiene información de compatibilidad
     */
    private function getCompatibilityData(string $context): array
    {
        $data = [
            'score' => $this->compatibility_score,
            'level' => $this->getCelebrationLevel(),
            'factors' => $this->getCompatibilityFactors($context),
            'mutual_interests' => $this->getMutualInterests(),
            'recommendation_reason' => $this->getRecommendationReason(),
        ];

        // En contexto público, ocultar factores detallados
        if ($context === self::CONTEXT_PUBLIC) {
            $data['factors'] = $this->getPublicCompatibilityFactors();
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene factores de compatibilidad
     */
    private function getCompatibilityFactors(string $context): array
    {
        if (!$this->compatibility_factors) {
            return [];
        }

        $data = [
            'age' => $this->compatibility_factors['age'] ?? null,
            'location' => $this->compatibility_factors['location'] ?? null,
            'interests' => $this->compatibility_factors['interests'] ?? null,
            'lifestyle' => $this->compatibility_factors['lifestyle'] ?? null,
            'personality' => $this->compatibility_factors['personality'] ?? null,
        ];

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene factores de compatibilidad públicos (simplificados)
     */
    private function getPublicCompatibilityFactors(): array
    {
        if (!$this->compatibility_factors) {
            return [];
        }

        return [
            'interests_match' => $this->compatibility_factors['interests'] ?? null,
            'lifestyle_match' => $this->compatibility_factors['lifestyle'] ?? null,
        ];
    }

    /**
     * Obtiene información de interacción
     */
    private function getInteractionData(string $context): array
    {
        $data = [
            'has_interacted' => $this->interaction_count > 0,
            'last_interaction_at' => $this->formatDateTime($this->last_interaction_at),
            'days_since_last_interaction' => $this->getDaysSinceLastInteraction(),
        ];

        // Solo mostrar conteos en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['interaction_count'] = $this->interaction_count;
            $data['message_count'] = $this->message_count;
        }

        // En contexto público, solo mostrar si han interactuado
        if ($context === self::CONTEXT_PUBLIC) {
            $data['has_messages'] = $this->message_count > 0;
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de engagement
     */
    private function getEngagementData(string $context): array
    {
        $data = [
            'engagement_level' => $this->getEngagementLevel(),
            'quality_score' => $this->getQualityScore(),
            'is_active' => $this->isActive(),
            'is_stale' => $this->getIsStaleAttribute(),
        ];

        // Solo mostrar métricas detalladas en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['engagement_metrics'] = [
                'interaction_rate' => $this->getInteractionRate(),
                'message_response_rate' => $this->getMessageResponseRate(),
                'activity_score' => $this->getActivityScore(),
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de calidad del match
     */
    private function getQualityData(string $context): array
    {
        $data = [
            'quality_score' => $this->getQualityScore(),
            'celebration_level' => $this->getCelebrationLevel(),
            'is_new' => $this->getIsNewAttribute(),
            'is_featured' => $this->is_featured,
        ];

        // Solo mostrar análisis detallado en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['quality_analysis'] = [
                'compatibility_weight' => 0.5,
                'interaction_weight' => 0.25,
                'message_weight' => 0.25,
                'breakdown' => $this->getQualityBreakdown(),
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información temporal del match
     */
    private function getTimelineData(string $context): array
    {
        $data = [
            'matched_at' => $this->formatDateTime($this->matched_at),
            'age_in_days' => $this->getAgeInDaysAttribute(),
            'last_interaction_at' => $this->formatDateTime($this->last_interaction_at),
        ];

        // Solo mostrar información de unmatch en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN]) && $this->isUnmatched()) {
            $data['unmatched_at'] = $this->formatDateTime($this->unmatched_at);
            $data['unmatched_by'] = $this->unmatched_by;
            $data['unmatched_reason'] = $this->unmatched_reason;
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene datos relacionados (mensajes, likes, etc.)
     */
    private function getRelatedData(string $context): array
    {
        $data = [];

        // Información de mensajes
        if ($this->relationLoaded('messages')) {
            $data['messages'] = [
                'count' => $this->messages->count(),
                'last_message_at' => $this->messages->last()?->created_at?->toISOString(),
                'preview' => $context === self::CONTEXT_OWN ? $this->getLastMessagePreview() : null,
            ];
        }

        // Información de likes que crearon el match
        if ($this->relationLoaded('likes')) {
            $data['likes'] = [
                'count' => $this->likes->count(),
                'created_by' => $this->likes->pluck('user_id')->toArray(),
            ];
        }

        // Información de interacciones
        if ($this->relationLoaded('interactions')) {
            $data['interactions'] = [
                'count' => $this->interactions->count(),
                'types' => $this->interactions->pluck('interaction_type')->unique()->values()->toArray(),
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene métricas del match
     */
    private function getMetrics(string $context): array
    {
        $data = [];

        // Métricas básicas siempre disponibles
        $data['basic'] = [
            'compatibility_score' => $this->compatibility_score,
            'quality_score' => $this->getQualityScore(),
            'engagement_level' => $this->getEngagementLevel(),
            'age_in_days' => $this->getAgeInDaysAttribute(),
        ];

        // Métricas detalladas solo para contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['detailed'] = [
                'interaction_count' => $this->interaction_count,
                'message_count' => $this->message_count,
                'days_since_last_interaction' => $this->getDaysSinceLastInteractionAttribute(),
                'is_new' => $this->getIsNewAttribute(),
                'is_stale' => $this->getIsStaleAttribute(),
                'has_recent_activity' => $this->hasRecentActivity(),
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene recomendaciones para el match
     */
    private function getRecommendations(string $context): array
    {
        // Solo mostrar recomendaciones en contexto propio
        if ($context !== self::CONTEXT_OWN) {
            return [];
        }

        $recommendations = [];

        // Recomendaciones basadas en engagement
        if ($this->getEngagementLevel() === 'low') {
            $recommendations[] = [
                'type' => 'engagement',
                'priority' => 'medium',
                'title' => 'Aumenta la interacción',
                'description' => 'Envía un mensaje para reactivar la conversación',
                'action' => 'send_message'
            ];
        }

        // Recomendaciones basadas en tiempo sin interacción
        if ($this->getDaysSinceLastInteractionAttribute() > 7) {
            $recommendations[] = [
                'type' => 'activity',
                'priority' => 'high',
                'title' => 'Reaviva la conexión',
                'description' => 'Ha pasado tiempo desde la última interacción',
                'action' => 'reconnect'
            ];
        }

        // Recomendaciones basadas en calidad
        if ($this->getQualityScore() < 50) {
            $recommendations[] = [
                'type' => 'quality',
                'priority' => 'low',
                'title' => 'Mejora la conexión',
                'description' => 'Comparte más intereses comunes',
                'action' => 'improve_connection'
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

        // Si el match incluye al usuario autenticado, es contexto propio
        if ($this->includesUser($user->id)) {
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
     * Obtiene el nivel de celebración del match
     */
    private function getCelebrationLevel(): string
    {
        return $this->getCelebrationLevelAttribute();
    }

    /**
     * Obtiene intereses mutuos
     */
    private function getMutualInterests(): array
    {
        return $this->getMutualInterests();
    }

    /**
     * Obtiene razón de recomendación
     */
    private function getRecommendationReason(): ?string
    {
        return $this->getRecommendationReason();
    }

    /**
     * Obtiene días desde última interacción
     */
    private function getDaysSinceLastInteraction(): ?int
    {
        return $this->getDaysSinceLastInteractionAttribute();
    }

    /**
     * Obtiene nivel de engagement
     */
    private function getEngagementLevel(): string
    {
        return $this->getEngagementLevel();
    }

    /**
     * Obtiene score de calidad
     */
    private function getQualityScore(): float
    {
        return $this->getQualityScore();
    }

    /**
     * Obtiene desglose de calidad
     */
    private function getQualityBreakdown(): array
    {
        return [
            'compatibility' => $this->compatibility_score * 0.5,
            'interaction' => min(($this->interaction_count / 10) * 25, 25),
            'message' => min(($this->message_count / 20) * 25, 25),
        ];
    }

    /**
     * Obtiene tasa de interacción
     */
    private function getInteractionRate(): float
    {
        if ($this->getAgeInDaysAttribute() === 0) {
            return 0.0;
        }

        return round($this->interaction_count / $this->getAgeInDaysAttribute(), 2);
    }

    /**
     * Obtiene tasa de respuesta a mensajes
     */
    private function getMessageResponseRate(): float
    {
        // Placeholder - en implementación real se calcularía basado en mensajes
        return 0.0;
    }

    /**
     * Obtiene score de actividad
     */
    private function getActivityScore(): float
    {
        $interactionRate = $this->getInteractionRate();
        $recentActivity = $this->hasRecentActivity() ? 1.0 : 0.0;
        
        return round(($interactionRate * 0.7) + ($recentActivity * 0.3), 2);
    }

    /**
     * Obtiene preview del último mensaje
     */
    private function getLastMessagePreview(): ?array
    {
        $lastMessage = $this->messages->last();
        
        if (!$lastMessage) {
            return null;
        }

        return [
            'content' => substr($lastMessage->content, 0, 100),
            'type' => $lastMessage->type,
            'sent_at' => $lastMessage->created_at?->toISOString(),
            'sender_id' => $lastMessage->sender_id,
        ];
    }

    /**
     * Obtiene el otro usuario del match
     */
    private function getOtherUser(string $userId): ?object
    {
        return $this->getOtherUser($userId);
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
                'match_source_info' => $this->getMatchSourceInfo(),
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
            self::CONTEXT_OWN => 300,           // 5 minutos para match propio
            self::CONTEXT_PUBLIC => 1800,       // 30 minutos para match público
            self::CONTEXT_ADMIN => 60,          // 1 minuto para vista admin
            self::CONTEXT_ANALYTICS => 900,     // 15 minutos para analytics
            default => 600,                     // 10 minutos por defecto
        };
    }

    /**
     * Obtiene información del source del match
     */
    private function getMatchSourceInfo(): array
    {
        return [
            'source' => $this->match_source,
            'description' => $this->getMatchSourceDescription(),
            'is_premium' => in_array($this->match_source, [
                'premium',
                'boost',
                'super_like',
            ]),
        ];
    }

    /**
     * Obtiene descripción del source del match
     */
    private function getMatchSourceDescription(): string
    {
        switch ($this->match_source) {
            case 'standard':
                return 'Match estándar';
            case 'premium':
                return 'Match premium';
            case 'boost':
                return 'Match con boost';
            case 'super_like':
                return 'Super like';
            case 'icebreaker':
                return 'Icebreaker';
            case 'algorithm':
                return 'Algoritmo avanzado';
            case 'event':
                return 'Evento especial';
            default:
                return 'Match desconocido';
        }
    }
}
