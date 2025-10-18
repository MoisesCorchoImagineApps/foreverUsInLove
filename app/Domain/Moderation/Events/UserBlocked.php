<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Evento disparado cuando un usuario bloquea a otro usuario en ForeverUsInLove
 * 
 * Este evento maneja todas las consecuencias del bloqueo entre usuarios,
 * incluyendo notificaciones, actualizaciones de estado, limpieza de interacciones
 * y activación de medidas de protección. Es fundamental para mantener
 * un ambiente seguro y respetuoso en la plataforma.
 * 
 * Características principales:
 * - Broadcasting en tiempo real para actualizaciones inmediatas
 * - Gestión automática de consecuencias del bloqueo
 * - Notificaciones contextuales sin revelar identidades
 * - Limpieza automática de matches, conversaciones y notificaciones
 * - Análisis de patrones de bloqueo para detectar usuarios problemáticos
 * - Integración con sistemas de moderación y seguridad
 * - Métricas y analytics en tiempo real
 * - Protección de privacidad y datos personales
 * 
 * @package App\Domain\Moderation\Events
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class UserBlocked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Información del bloqueo
     */
    public readonly int $blockId;
    public readonly int $blockerId;
    public readonly int $blockedId;
    public readonly string $blockType;
    public readonly ?string $reason;
    public readonly ?int $durationHours;
    public readonly Carbon $blockedAt;
    public readonly array $metadata;

    /**
     * Constructor del evento
     *
     * @param int $blockId ID único del bloqueo
     * @param int $blockerId ID del usuario que bloquea
     * @param int $blockedId ID del usuario bloqueado
     * @param string $blockType Tipo de bloqueo aplicado
     * @param string|null $reason Razón del bloqueo
     * @param int|null $durationHours Duración del bloqueo en horas (null = permanente)
     * @param array $metadata Metadatos adicionales del bloqueo
     */
    public function __construct(
        int $blockId,
        int $blockerId,
        int $blockedId,
        string $blockType,
        ?string $reason = null,
        ?int $durationHours = null,
        array $metadata = []
    ) {
        $this->blockId = $blockId;
        $this->blockerId = $blockerId;
        $this->blockedId = $blockedId;
        $this->blockType = $blockType;
        $this->reason = $reason;
        $this->durationHours = $durationHours;
        $this->blockedAt = Carbon::now();
        $this->metadata = array_merge($metadata, [
            'event_id' => uniqid('user_blocked_', true),
            'timestamp' => $this->blockedAt->toISOString(),
            'source' => 'block_system',
            'version' => '1.0.0',
            'is_temporary' => !is_null($durationHours),
            'expires_at' => $durationHours ? 
                           $this->blockedAt->addHours($durationHours)->toISOString() : 
                           null
        ]);
    }

    /**
     * Obtiene los canales donde se transmitirá el evento
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Canal privado para el usuario que bloquea (confirmación)
        $channels[] = new PrivateChannel("user.{$this->blockerId}.blocks");

        // Canal privado para el usuario bloqueado (notificación discreta)
        // Solo para ciertos tipos de notificación que no revelen la identidad
        if ($this->shouldNotifyBlockedUser()) {
            $channels[] = new PrivateChannel("user.{$this->blockedId}.account-updates");
        }

        // Canal para el sistema de moderación
        $channels[] = new PrivateChannel('moderation.user-blocks');

        // Canal para análisis de seguridad y patrones
        $channels[] = new PrivateChannel('analytics.blocking-patterns');

        // Canal para sistemas de matching (para actualizar algoritmos)
        $channels[] = new PrivateChannel('matching.user-restrictions');

        // Canal para customer service (en casos que requieren seguimiento)
        if ($this->requiresCustomerServiceAlert()) {
            $channels[] = new PrivateChannel('customer-service.block-alerts');
        }

        // Canal para administradores en casos de bloqueos masivos o patrones sospechosos
        if ($this->shouldAlertAdministrators()) {
            $channels[] = new PrivateChannel('admin.security-monitoring');
        }

        return $channels;
    }

    /**
     * Nombre del evento para broadcasting
     */
    public function broadcastAs(): string
    {
        return 'user.blocked';
    }

    /**
     * Datos que serán transmitidos
     */
    public function broadcastWith(): array
    {
        return [
            'block_info' => [
                'id' => $this->blockId,
                'type' => $this->blockType,
                'is_temporary' => !is_null($this->durationHours),
                'duration_hours' => $this->durationHours,
                'blocked_at' => $this->blockedAt->toISOString(),
                'expires_at' => $this->metadata['expires_at'],
                'reference_number' => $this->generateBlockReference()
            ],
            'blocker_info' => [
                'id' => $this->blockerId,
                'blocking_frequency' => $this->getBlockingFrequency($this->blockerId),
                'is_verified' => $this->isVerifiedUser($this->blockerId),
                'account_age' => $this->getUserAccountAge($this->blockerId),
                'previous_blocks_made' => $this->getPreviousBlocksCount($this->blockerId)
            ],
            'blocked_user_info' => [
                'id' => $this->blockedId,
                'times_blocked' => $this->getTimesBlockedCount($this->blockedId),
                'risk_profile' => $this->getUserRiskProfile($this->blockedId),
                'is_verified' => $this->isVerifiedUser($this->blockedId),
                'recent_reports' => $this->getRecentReportsCount($this->blockedId)
            ],
            'relationship_context' => [
                'had_match' => $this->hadMatch(),
                'conversation_exists' => $this->hasConversation(),
                'interaction_duration' => $this->getInteractionDuration(),
                'last_interaction' => $this->getLastInteractionTime(),
                'interaction_type' => $this->getLastInteractionType()
            ],
            'consequences' => [
                'matches_removed' => $this->getMatchesRemoved(),
                'conversations_hidden' => $this->getConversationsHidden(),
                'notifications_cleared' => $this->getNotificationsCleared(),
                'visibility_restricted' => $this->getVisibilityRestrictions(),
                'features_disabled' => $this->getDisabledFeatures()
            ],
            'system_actions' => [
                'auto_cleanup_triggered' => $this->getAutoCleanupActions(),
                'algorithm_updates' => $this->getAlgorithmUpdates(),
                'protection_measures' => $this->getProtectionMeasures(),
                'escalation_triggered' => $this->shouldTriggerEscalation()
            ],
            'pattern_analysis' => [
                'is_mutual_block' => $this->isMutualBlock(),
                'blocking_pattern_detected' => $this->detectBlockingPattern(),
                'harassment_indicators' => $this->getHarassmentIndicators(),
                'community_impact_score' => $this->calculateCommunityImpact()
            ],
            'safety_measures' => [
                'additional_monitoring' => $this->requiresAdditionalMonitoring(),
                'contact_prevention' => $this->getContactPreventionMeasures(),
                'data_isolation' => $this->getDataIsolationMeasures(),
                'appeal_process_available' => $this->isAppealProcessAvailable()
            ],
            'notifications' => [
                'blocker_notification' => $this->getBlockerNotificationData(),
                'blocked_user_notification' => $this->getBlockedUserNotificationData(),
                'moderator_alert' => $this->getModeratorAlertData(),
                'cs_ticket_created' => $this->shouldCreateCSTicket()
            ],
            'privacy_protection' => [
                'identity_masked' => true,
                'reason_disclosed' => false,
                'mutual_protection' => $this->getMutualProtectionMeasures(),
                'data_anonymization' => $this->getDataAnonymizationStatus()
            ],
            'analytics_data' => [
                'block_category' => $this->categorizeBlock(),
                'user_segment' => $this->getUserSegment($this->blockerId),
                'geographic_context' => $this->getGeographicContext(),
                'temporal_patterns' => $this->getTemporalPatterns(),
                'behavioral_indicators' => $this->getBehavioralIndicators()
            ],
            'metadata' => $this->metadata
        ];
    }

    /**
     * Configuración de colas para procesamiento
     */
    public function viaQueues(): array
    {
        $queues = ['blocks'];

        // Cola de alta prioridad para casos que requieren acción inmediata
        if ($this->requiresImmediateAction()) {
            $queues[] = 'urgent-blocks';
        }

        // Cola de limpieza para operaciones de base de datos
        $queues[] = 'cleanup';

        // Cola de análisis para detección de patrones
        $queues[] = 'analytics';

        // Cola de notificaciones
        $queues[] = 'notifications';

        return $queues;
    }

    /**
     * Tiempo límite para reintentos del evento
     */
    public function retryUntil(): \DateTime
    {
        // Reintentar durante 2 horas para bloqueos críticos, 30 minutos para otros
        $minutes = $this->requiresImmediateAction() ? 120 : 30;
        return now()->addMinutes($minutes);
    }

    /**
     * Determina si debería fallar silenciosamente
     */
    public function shouldFailSilently(): bool
    {
        // No fallar silenciosamente para bloqueos que requieren acción inmediata
        return !$this->requiresImmediateAction();
    }

    /**
     * Genera referencia única para el bloqueo
     */
    private function generateBlockReference(): string
    {
        return 'BLK-' . 
               $this->blockedAt->format('Ymd') . '-' . 
               str_pad((string)$this->blockId, 6, '0', STR_PAD_LEFT) . '-' .
               strtoupper(substr(md5($this->blockId . $this->blockedAt->timestamp), 0, 4));
    }

    /**
     * Determina si debe notificar al usuario bloqueado
     */
    private function shouldNotifyBlockedUser(): bool
    {
        // Solo notificar de manera genérica en ciertos casos
        return in_array($this->blockType, ['complete', 'messaging']) &&
               !$this->isHarassmentCase();
    }

    /**
     * Determina si requiere alerta al customer service
     */
    private function requiresCustomerServiceAlert(): bool
    {
        return $this->detectBlockingPattern() ||
               $this->getTimesBlockedCount($this->blockedId) > 10 ||
               in_array($this->reason, ['harassment', 'threats', 'scam_attempt']);
    }

    /**
     * Determina si debe alertar a administradores
     */
    private function shouldAlertAdministrators(): bool
    {
        return $this->getPreviousBlocksCount($this->blockerId) > 20 ||
               $this->isMassBlockingEvent() ||
               $this->detectSuspiciousActivity();
    }

    /**
     * Obtiene frecuencia de bloqueos del usuario
     */
    private function getBlockingFrequency(int $userId): array
    {
        // Implementación simulada
        return [
            'blocks_last_7_days' => rand(0, 5),
            'blocks_last_30_days' => rand(0, 15),
            'average_blocks_per_week' => rand(0, 3),
            'blocking_trend' => ['increasing', 'stable', 'decreasing'][rand(0, 2)]
        ];
    }

    /**
     * Verifica si el usuario está verificado
     */
    private function isVerifiedUser(int $userId): bool
    {
        // Implementación simulada
        return (bool)rand(0, 1);
    }

    /**
     * Obtiene antigüedad de la cuenta
     */
    private function getUserAccountAge(int $userId): array
    {
        $days = rand(1, 1000);
        return [
            'days' => $days,
            'is_new_account' => $days < 30,
            'created_at' => Carbon::now()->subDays($days)->toDateString()
        ];
    }

    /**
     * Obtiene número de bloqueos previos hechos por el usuario
     */
    private function getPreviousBlocksCount(int $userId): int
    {
        return rand(0, 25);
    }

    /**
     * Obtiene número de veces que el usuario ha sido bloqueado
     */
    private function getTimesBlockedCount(int $userId): int
    {
        return rand(0, 20);
    }

    /**
     * Obtiene perfil de riesgo del usuario
     */
    private function getUserRiskProfile(int $userId): array
    {
        return [
            'risk_score' => rand(0, 100),
            'risk_level' => ['low', 'medium', 'high', 'critical'][rand(0, 3)],
            'risk_factors' => [
                'multiple_blocks_received' => (bool)rand(0, 1),
                'recent_reports' => (bool)rand(0, 1),
                'suspicious_behavior' => (bool)rand(0, 1),
                'new_account' => (bool)rand(0, 1)
            ]
        ];
    }

    /**
     * Obtiene reportes recientes del usuario
     */
    private function getRecentReportsCount(int $userId): int
    {
        return rand(0, 10);
    }

    /**
     * Verifica si había match entre los usuarios
     */
    private function hadMatch(): bool
    {
        return (bool)rand(0, 1);
    }

    /**
     * Verifica si existe conversación
     */
    private function hasConversation(): bool
    {
        return (bool)rand(0, 1);
    }

    /**
     * Obtiene duración de la interacción
     */
    private function getInteractionDuration(): ?string
    {
        if ($this->hasConversation()) {
            return rand(1, 100) . ' days';
        }
        return null;
    }

    /**
     * Obtiene tiempo de última interacción
     */
    private function getLastInteractionTime(): ?string
    {
        if ($this->hasConversation()) {
            return Carbon::now()->subHours(rand(1, 168))->toISOString();
        }
        return null;
    }

    /**
     * Obtiene tipo de última interacción
     */
    private function getLastInteractionType(): ?string
    {
        if ($this->hasConversation()) {
            return ['message', 'profile_view', 'like', 'super_like'][rand(0, 3)];
        }
        return null;
    }

    /**
     * Obtiene matches removidos como consecuencia
     */
    private function getMatchesRemoved(): int
    {
        return $this->hadMatch() ? 1 : 0;
    }

    /**
     * Obtiene conversaciones ocultadas
     */
    private function getConversationsHidden(): int
    {
        return $this->hasConversation() ? 1 : 0;
    }

    /**
     * Obtiene notificaciones limpiadas
     */
    private function getNotificationsCleared(): int
    {
        return rand(0, 10);
    }

    /**
     * Obtiene restricciones de visibilidad aplicadas
     */
    private function getVisibilityRestrictions(): array
    {
        $restrictions = [];
        
        if ($this->blockType === 'complete') {
            $restrictions = ['search_results', 'recommendations', 'profile_visibility'];
        } elseif ($this->blockType === 'search_results') {
            $restrictions = ['search_results'];
        }
        
        return $restrictions;
    }

    /**
     * Obtiene funcionalidades deshabilitadas
     */
    private function getDisabledFeatures(): array
    {
        $disabled = [];
        
        switch ($this->blockType) {
            case 'complete':
                $disabled = ['messaging', 'profile_view', 'matching', 'notifications'];
                break;
            case 'messaging':
                $disabled = ['messaging', 'notifications'];
                break;
            case 'profile_view':
                $disabled = ['profile_view'];
                break;
        }
        
        return $disabled;
    }

    /**
     * Obtiene acciones de limpieza automática ejecutadas
     */
    private function getAutoCleanupActions(): array
    {
        return [
            'match_removal' => $this->hadMatch(),
            'conversation_archival' => $this->hasConversation(),
            'notification_cleanup' => true,
            'cache_invalidation' => true,
            'recommendation_exclusion' => true
        ];
    }

    /**
     * Obtiene actualizaciones de algoritmo necesarias
     */
    private function getAlgorithmUpdates(): array
    {
        return [
            'matching_algorithm' => true,
            'recommendation_engine' => true,
            'search_filters' => $this->blockType === 'search_results',
            'notification_system' => true
        ];
    }

    /**
     * Obtiene medidas de protección aplicadas
     */
    private function getProtectionMeasures(): array
    {
        return [
            'contact_prevention' => true,
            'data_isolation' => $this->blockType === 'complete',
            'interaction_monitoring' => $this->requiresMonitoring(),
            'appeal_process' => true
        ];
    }

    /**
     * Determina si debe disparar escalamiento
     */
    private function shouldTriggerEscalation(): bool
    {
        return $this->getPreviousBlocksCount($this->blockerId) > 15 ||
               $this->getTimesBlockedCount($this->blockedId) > 8 ||
               $this->detectBlockingPattern();
    }

    /**
     * Verifica si es bloqueo mutuo
     */
    private function isMutualBlock(): bool
    {
        // Implementación simulada - verificaría si existe bloqueo recíproco
        return (bool)rand(0, 1);
    }

    /**
     * Detecta patrón de bloqueo sospechoso
     */
    private function detectBlockingPattern(): bool
    {
        $blocksLast24h = $this->getBlockingFrequency($this->blockerId)['blocks_last_7_days'];
        return $blocksLast24h > 3;
    }

    /**
     * Obtiene indicadores de acoso
     */
    private function getHarassmentIndicators(): array
    {
        return [
            'rapid_blocking' => $this->detectBlockingPattern(),
            'mutual_blocks' => $this->isMutualBlock(),
            'harassment_reports' => $this->getRecentReportsCount($this->blockedId) > 3,
            'pattern_match' => (bool)rand(0, 1)
        ];
    }

    /**
     * Calcula impacto en la comunidad
     */
    private function calculateCommunityImpact(): int
    {
        // Score de 0-100 basado en varios factores
        $impact = 0;
        
        $impact += $this->getPreviousBlocksCount($this->blockerId) * 2;
        $impact += $this->getTimesBlockedCount($this->blockedId) * 3;
        $impact += $this->detectBlockingPattern() ? 20 : 0;
        
        return min($impact, 100);
    }

    /**
     * Determina si requiere monitoreo adicional
     */
    private function requiresAdditionalMonitoring(): bool
    {
        return $this->detectBlockingPattern() ||
               in_array($this->reason, ['harassment', 'threats']) ||
               $this->getUserRiskProfile($this->blockedId)['risk_level'] === 'high';
    }

    /**
     * Determina si requiere acción inmediata
     */
    private function requiresImmediateAction(): bool
    {
        return $this->detectBlockingPattern() ||
               $this->shouldTriggerEscalation() ||
               in_array($this->reason, ['harassment', 'threats', 'safety_concerns']);
    }

    /**
     * Determina si es caso de acoso
     */
    private function isHarassmentCase(): bool
    {
        return in_array($this->reason, ['harassment', 'unwanted_contact', 'threats']);
    }
}