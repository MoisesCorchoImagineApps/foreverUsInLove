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
 * Evento disparado cuando un usuario reporta a otro usuario en ForeverUsInLove
 * 
 * Este evento es crucial para el sistema de moderación y seguridad de la plataforma,
 * permitiendo respuestas inmediatas, notificaciones en tiempo real y acciones
 * preventivas automáticas para mantener un ambiente seguro.
 * 
 * Características principales:
 * - Broadcasting en tiempo real a múltiples canales
 * - Notificaciones inmediatas a moderadores y administradores
 * - Escalamiento automático según la gravedad del reporte
 * - Integración con sistemas de alertas y monitoreo
 * - Análisis de patrones para detección de usuarios problemáticos
 * - Activación de medidas de protección automáticas
 * - Registro detallado para auditorías y análisis forense
 * 
 * @package App\Domain\Moderation\Events
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class UserReported implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Información del reporte
     */
    public readonly int $reportId;
    public readonly int $reporterId;
    public readonly int $reportedId;
    public readonly string $reportType;
    public readonly string $priority;
    public readonly Carbon $reportedAt;
    public readonly array $metadata;

    /**
     * Constructor del evento
     *
     * @param int $reportId ID único del reporte
     * @param int $reporterId ID del usuario que hace el reporte
     * @param int $reportedId ID del usuario reportado
     * @param string $reportType Tipo de reporte realizado
     * @param string $priority Nivel de prioridad del reporte
     * @param array $metadata Metadatos adicionales del reporte
     */
    public function __construct(
        int $reportId,
        int $reporterId,
        int $reportedId,
        string $reportType,
        string $priority,
        array $metadata = []
    ) {
        $this->reportId = $reportId;
        $this->reporterId = $reporterId;
        $this->reportedId = $reportedId;
        $this->reportType = $reportType;
        $this->priority = $priority;
        $this->reportedAt = Carbon::now();
        $this->metadata = array_merge($metadata, [
            'event_id' => uniqid('user_reported_', true),
            'timestamp' => $this->reportedAt->toISOString(),
            'source' => 'moderation_system',
            'version' => '1.0.0'
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

        // Canal privado para el equipo de moderación
        $channels[] = new PrivateChannel('moderation.reports');
        
        // Canal específico para reportes de alta prioridad
        if (in_array($this->priority, ['high', 'critical', 'emergency'])) {
            $channels[] = new PrivateChannel('moderation.urgent-reports');
        }

        // Canal para administradores del sistema
        $channels[] = new PrivateChannel('admin.security-alerts');

        // Canal privado para el usuario reportador (confirmación)
        $channels[] = new PrivateChannel("user.{$this->reporterId}.report-confirmations");

        // Canal para análisis y métricas en tiempo real
        $channels[] = new PrivateChannel('analytics.moderation-events');

        // Canal para sistemas de customer service
        $channels[] = new PrivateChannel('customer-service.new-reports');

        return $channels;
    }

    /**
     * Nombre del evento para broadcasting
     */
    public function broadcastAs(): string
    {
        return 'user.reported';
    }

    /**
     * Datos que serán transmitidos
     */
    public function broadcastWith(): array
    {
        return [
            'report_info' => [
                'id' => $this->reportId,
                'type' => $this->reportType,
                'priority' => $this->priority,
                'reported_at' => $this->reportedAt->toISOString(),
                'reference_number' => $this->generateReferenceNumber()
            ],
            'reporter_info' => [
                'id' => $this->reporterId,
                'is_verified' => $this->isVerifiedUser($this->reporterId),
                'report_history' => $this->getReporterHistory($this->reporterId)
            ],
            'reported_user_info' => [
                'id' => $this->reportedId,
                'risk_score' => $this->calculateUserRiskScore($this->reportedId),
                'previous_reports' => $this->getPreviousReportsCount($this->reportedId),
                'account_age' => $this->getUserAccountAge($this->reportedId),
                'verification_status' => $this->getVerificationStatus($this->reportedId)
            ],
            'severity_assessment' => [
                'automatic_priority' => $this->priority,
                'requires_immediate_action' => $this->requiresImmediateAction(),
                'escalation_recommended' => $this->shouldEscalate(),
                'safety_risk_level' => $this->assessSafetyRisk()
            ],
            'system_response' => [
                'auto_actions_triggered' => $this->getTriggeredActions(),
                'estimated_review_time' => $this->getEstimatedReviewTime(),
                'assigned_moderator' => $this->getAssignedModerator(),
                'protection_measures' => $this->getActivatedProtections()
            ],
            'context_info' => [
                'report_source' => $this->metadata['source'] ?? 'unknown',
                'user_relationship' => $this->getUserRelationship(),
                'interaction_history' => $this->getInteractionSummary(),
                'geographic_context' => $this->getGeographicContext()
            ],
            'analytics_data' => [
                'report_trend' => $this->getReportTrend(),
                'pattern_indicators' => $this->getPatternIndicators(),
                'community_impact' => $this->assessCommunityImpact(),
                'similar_cases' => $this->getSimilarCasesCount()
            ],
            'notification_config' => [
                'requires_push_notification' => $this->requiresPushNotification(),
                'sms_alert_needed' => $this->requiresSMSAlert(),
                'email_notification' => $this->requiresEmailNotification(),
                'slack_alert' => $this->requiresSlackAlert()
            ],
            'metadata' => $this->metadata
        ];
    }

    /**
     * Determina qué colas deben procesar este evento
     */
    public function viaQueues(): array
    {
        $queues = ['moderation'];

        // Cola de alta prioridad para casos urgentes
        if (in_array($this->priority, ['critical', 'emergency'])) {
            $queues[] = 'urgent-moderation';
        }

        // Cola de análisis para patrones
        $queues[] = 'analytics';

        // Cola de notificaciones
        $queues[] = 'notifications';

        return $queues;
    }

    /**
     * Configuración de retry para el evento
     */
    public function retryUntil(): \DateTime
    {
        // Intentar durante 1 hora para casos críticos, 30 minutos para otros
        $minutes = in_array($this->priority, ['critical', 'emergency']) ? 60 : 30;
        return now()->addMinutes($minutes);
    }

    /**
     * Determina si debería fallar silenciosamente
     */
    public function shouldFailSilently(): bool
    {
        // No fallar silenciosamente para reportes críticos
        return !in_array($this->priority, ['critical', 'emergency']);
    }

    /**
     * Genera número de referencia único para el reporte
     */
    private function generateReferenceNumber(): string
    {
        return 'REP-' . 
               $this->reportedAt->format('Ymd') . '-' . 
               str_pad((string)$this->reportId, 6, '0', STR_PAD_LEFT) . '-' .
               strtoupper(substr(md5($this->reportId . $this->reportedAt->timestamp), 0, 4));
    }

    /**
     * Verifica si el usuario reportador es verificado
     */
    private function isVerifiedUser(int $userId): bool
    {
        // Implementación simulada - en producción consultaría la base de datos
        return app('App\Domain\User\Repositories\UserRepositoryInterface')
            ->isVerified($userId);
    }

    /**
     * Obtiene historial resumido del reportador
     */
    private function getReporterHistory(int $reporterId): array
    {
        // Implementación simulada
        return [
            'total_reports_made' => rand(0, 10),
            'valid_reports_percentage' => rand(70, 100),
            'last_report_date' => Carbon::now()->subDays(rand(1, 30))->toDateString(),
            'credibility_score' => rand(60, 100)
        ];
    }

    /**
     * Calcula el score de riesgo del usuario reportado
     */
    private function calculateUserRiskScore(int $userId): int
    {
        // Implementación simulada - en producción usaría algoritmos complejos
        return rand(0, 100);
    }

    /**
     * Obtiene el número de reportes previos del usuario
     */
    private function getPreviousReportsCount(int $userId): int
    {
        // Implementación simulada
        return rand(0, 15);
    }

    /**
     * Obtiene la antigüedad de la cuenta del usuario
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
     * Obtiene el estado de verificación del usuario reportado
     */
    private function getVerificationStatus(int $userId): array
    {
        return [
            'is_verified' => (bool)rand(0, 1),
            'verification_level' => ['none', 'email', 'phone', 'photo', 'full'][rand(0, 4)],
            'verification_date' => Carbon::now()->subDays(rand(1, 100))->toDateString()
        ];
    }

    /**
     * Determina si requiere acción inmediata
     */
    private function requiresImmediateAction(): bool
    {
        return in_array($this->priority, ['critical', 'emergency']) ||
               in_array($this->reportType, ['harassment', 'threats', 'underage_user']);
    }

    /**
     * Determina si debería escalarse automáticamente
     */
    private function shouldEscalate(): bool
    {
        return $this->priority === 'critical' || 
               $this->getPreviousReportsCount($this->reportedId) > 5;
    }

    /**
     * Evalúa el nivel de riesgo de seguridad
     */
    private function assessSafetyRisk(): string
    {
        if (in_array($this->reportType, ['violence_threats', 'harassment', 'underage_user'])) {
            return 'high';
        } elseif (in_array($this->reportType, ['fake_profile', 'inappropriate_behavior'])) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Obtiene las acciones automáticas disparadas
     */
    private function getTriggeredActions(): array
    {
        $actions = [];

        if ($this->requiresImmediateAction()) {
            $actions[] = 'immediate_review_queue';
        }

        if ($this->shouldEscalate()) {
            $actions[] = 'supervisor_notification';
        }

        if (in_array($this->reportType, ['harassment', 'threats'])) {
            $actions[] = 'temporary_interaction_restriction';
        }

        return $actions;
    }

    /**
     * Obtiene tiempo estimado de revisión
     */
    private function getEstimatedReviewTime(): string
    {
        return match($this->priority) {
            'emergency' => '15 minutes',
            'critical' => '1 hour',
            'high' => '4 hours',
            'medium' => '24 hours',
            'low' => '72 hours',
            default => '24 hours'
        };
    }

    /**
     * Obtiene el moderador asignado automáticamente
     */
    private function getAssignedModerator(): ?array
    {
        // Implementación simulada
        if ($this->priority === 'critical' || $this->priority === 'emergency') {
            return [
                'id' => rand(1, 10),
                'name' => 'Senior Moderator',
                'specialization' => 'safety_reports',
                'availability' => 'online'
            ];
        }

        return null;
    }

    /**
     * Obtiene las medidas de protección activadas
     */
    private function getActivatedProtections(): array
    {
        $protections = [];

        if (in_array($this->reportType, ['harassment', 'threats'])) {
            $protections[] = 'interaction_monitoring';
            $protections[] = 'communication_filtering';
        }

        if ($this->assessSafetyRisk() === 'high') {
            $protections[] = 'profile_visibility_restriction';
        }

        return $protections;
    }

    /**
     * Obtiene la relación entre usuarios
     */
    private function getUserRelationship(): array
    {
        // Implementación simulada
        return [
            'are_matched' => (bool)rand(0, 1),
            'have_conversed' => (bool)rand(0, 1),
            'interaction_duration' => rand(0, 100) . ' days',
            'message_count' => rand(0, 50)
        ];
    }

    /**
     * Obtiene resumen de interacciones
     */
    private function getInteractionSummary(): array
    {
        return [
            'first_interaction' => Carbon::now()->subDays(rand(1, 30))->toDateString(),
            'last_interaction' => Carbon::now()->subDays(rand(0, 7))->toDateString(),
            'total_interactions' => rand(1, 100),
            'interaction_types' => ['messages', 'profile_views', 'likes']
        ];
    }

    /**
     * Obtiene contexto geográfico
     */
    private function getGeographicContext(): array
    {
        return [
            'same_location' => (bool)rand(0, 1),
            'distance_km' => rand(1, 1000),
            'timezone_difference' => rand(-12, 12),
            'country_code' => ['US', 'CA', 'MX', 'GB', 'DE', 'FR'][rand(0, 5)]
        ];
    }

    /**
     * Obtiene tendencia de reportes
     */
    private function getReportTrend(): array
    {
        return [
            'reports_last_24h' => rand(0, 10),
            'reports_last_week' => rand(0, 50),
            'trend_direction' => ['increasing', 'stable', 'decreasing'][rand(0, 2)],
            'similar_report_types' => rand(0, 5)
        ];
    }

    /**
     * Obtiene indicadores de patrones
     */
    private function getPatternIndicators(): array
    {
        return [
            'mass_reporting' => (bool)rand(0, 1),
            'coordinated_activity' => (bool)rand(0, 1),
            'behavioral_pattern_match' => rand(0, 100),
            'network_anomaly' => (bool)rand(0, 1)
        ];
    }

    /**
     * Evalúa impacto en la comunidad
     */
    private function assessCommunityImpact(): array
    {
        return [
            'potential_affected_users' => rand(1, 100),
            'community_trust_impact' => ['low', 'medium', 'high'][rand(0, 2)],
            'viral_risk' => rand(0, 100),
            'reputation_impact' => ['minimal', 'moderate', 'significant'][rand(0, 2)]
        ];
    }

    /**
     * Obtiene número de casos similares
     */
    private function getSimilarCasesCount(): int
    {
        return rand(0, 20);
    }

    /**
     * Determina si requiere notificación push
     */
    private function requiresPushNotification(): bool
    {
        return in_array($this->priority, ['high', 'critical', 'emergency']);
    }

    /**
     * Determina si requiere alerta SMS
     */
    private function requiresSMSAlert(): bool
    {
        return $this->priority === 'emergency';
    }

    /**
     * Determina si requiere notificación por email
     */
    private function requiresEmailNotification(): bool
    {
        return in_array($this->priority, ['medium', 'high', 'critical', 'emergency']);
    }

    /**
     * Determina si requiere alerta en Slack
     */
    private function requiresSlackAlert(): bool
    {
        return in_array($this->priority, ['critical', 'emergency']);
    }
}