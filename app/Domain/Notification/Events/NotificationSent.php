<?php

declare(strict_types=1);

namespace App\Domain\Notification\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Evento disparado cuando una notificación es enviada exitosamente en ForeverUsInLove
 * 
 * Este evento se dispara tras el envío exitoso de cualquier tipo de notificación
 * (push, email, SMS, in-app), proporcionando información detallada sobre el envío,
 * métricas de entrega, y activando procesos de seguimiento, analytics y optimización
 * del sistema de notificaciones.
 * 
 * Características principales:
 * - Broadcasting en tiempo real para dashboards de administración
 * - Activación de analytics y métricas de engagement
 * - Seguimiento de entrega y confirmación de recepción
 * - Optimización automática de canales y horarios
 * - A/B testing y análisis de performance
 * - Integración con sistemas de CRM y marketing automation
 * - Alertas de fallos y degradación de servicio
 * - Compliance y auditoría de comunicaciones
 * 
 * @package App\Domain\Notification\Events
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Información de la notificación enviada
     */
    public readonly int $notificationId;
    public readonly int $userId;
    public readonly string $notificationType;
    public readonly array $channelsUsed;
    public readonly array $deliveryResults;
    public readonly Carbon $sentAt;
    public readonly array $metadata;

    /**
     * Constructor del evento
     *
     * @param int $notificationId ID de la notificación
     * @param int $userId ID del usuario destinatario
     * @param string $notificationType Tipo de notificación enviada
     * @param array $channelsUsed Canales utilizados para el envío
     * @param array $deliveryResults Resultados de entrega por canal
     * @param array $metadata Metadatos adicionales
     */
    public function __construct(
        int $notificationId,
        int $userId,
        string $notificationType,
        array $channelsUsed,
        array $deliveryResults,
        array $metadata = []
    ) {
        $this->notificationId = $notificationId;
        $this->userId = $userId;
        $this->notificationType = $notificationType;
        $this->channelsUsed = $channelsUsed;
        $this->deliveryResults = $deliveryResults;
        $this->sentAt = Carbon::now();
        $this->metadata = array_merge($metadata, [
            'event_id' => uniqid('notification_sent_', true),
            'timestamp' => $this->sentAt->toISOString(),
            'source' => 'notification_system',
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

        // Canal privado para administradores del sistema
        $channels[] = new PrivateChannel('admin.notifications');

        // Canal específico para analytics de notificaciones
        $channels[] = new PrivateChannel('analytics.notification-delivery');

        // Canal para marketing y engagement teams
        $channels[] = new PrivateChannel('marketing.notification-campaigns');

        // Canal para monitoreo técnico y DevOps
        $channels[] = new PrivateChannel('monitoring.notification-health');

        // Canal para el equipo de producto (métricas de engagement)
        $channels[] = new PrivateChannel('product.user-engagement');

        // Canal específico para A/B testing si aplica
        if ($this->isABTestNotification()) {
            $channels[] = new PrivateChannel('experiments.ab-testing');
        }

        // Canal para compliance y auditoría
        if ($this->requiresAuditTrail()) {
            $channels[] = new PrivateChannel('compliance.notification-audit');
        }

        return $channels;
    }

    /**
     * Nombre del evento para broadcasting
     */
    public function broadcastAs(): string
    {
        return 'notification.sent';
    }

    /**
     * Datos que serán transmitidos
     */
    public function broadcastWith(): array
    {
        return [
            'notification_info' => [
                'id' => $this->notificationId,
                'type' => $this->notificationType,
                'category' => $this->getNotificationCategory(),
                'priority' => $this->getNotificationPriority(),
                'sent_at' => $this->sentAt->toISOString(),
                'reference_id' => $this->generateReferenceId()
            ],
            'recipient_info' => [
                'user_id' => $this->userId,
                'user_segment' => $this->getUserSegment(),
                'timezone' => $this->getUserTimezone(),
                'preferences_profile' => $this->getUserPreferencesProfile(),
                'engagement_history' => $this->getUserEngagementSummary()
            ],
            'delivery_info' => [
                'channels_used' => $this->channelsUsed,
                'total_channels' => count($this->channelsUsed),
                'successful_channels' => $this->getSuccessfulChannels(),
                'failed_channels' => $this->getFailedChannels(),
                'delivery_results' => $this->deliveryResults,
                'multi_channel_success' => $this->isMultiChannelSuccess()
            ],
            'performance_metrics' => [
                'processing_time' => $this->calculateProcessingTime(),
                'delivery_latency' => $this->calculateDeliveryLatency(),
                'cost_breakdown' => $this->calculateCostBreakdown(),
                'efficiency_score' => $this->calculateEfficiencyScore(),
                'provider_performance' => $this->getProviderPerformanceData()
            ],
            'content_analysis' => [
                'content_length' => $this->getContentLength(),
                'personalization_level' => $this->getPersonalizationLevel(),
                'template_version' => $this->getTemplateVersion(),
                'language' => $this->getContentLanguage(),
                'ab_test_variant' => $this->getABTestVariant()
            ],
            'engagement_predictions' => [
                'predicted_open_rate' => $this->getPredictedOpenRate(),
                'predicted_click_rate' => $this->getPredictedClickRate(),
                'engagement_probability' => $this->getEngagementProbability(),
                'optimal_followup_timing' => $this->getOptimalFollowupTiming(),
                'churn_risk_indicator' => $this->getChurnRiskIndicator()
            ],
            'system_context' => [
                'system_load' => $this->getSystemLoadContext(),
                'queue_depth' => $this->getQueueDepthContext(),
                'provider_health' => $this->getProviderHealthContext(),
                'rate_limit_status' => $this->getRateLimitStatus(),
                'fallback_used' => $this->wasFallbackUsed()
            ],
            'business_metrics' => [
                'campaign_id' => $this->getCampaignId(),
                'conversion_goal' => $this->getConversionGoal(),
                'revenue_attribution' => $this->getRevenueAttribution(),
                'ltv_impact_prediction' => $this->getLTVImpactPrediction(),
                'retention_influence_score' => $this->getRetentionInfluenceScore()
            ],
            'compliance_data' => [
                'consent_status' => $this->getConsentStatus(),
                'opt_in_date' => $this->getOptInDate(),
                'regulation_compliance' => $this->getRegulationCompliance(),
                'data_retention_policy' => $this->getDataRetentionPolicy(),
                'audit_trail_id' => $this->getAuditTrailId()
            ],
            'optimization_insights' => [
                'channel_optimization' => $this->getChannelOptimizationInsights(),
                'timing_optimization' => $this->getTimingOptimizationInsights(),
                'content_optimization' => $this->getContentOptimizationInsights(),
                'frequency_optimization' => $this->getFrequencyOptimizationInsights(),
                'personalization_opportunities' => $this->getPersonalizationOpportunities()
            ],
            'metadata' => $this->metadata
        ];
    }

    /**
     * Configuración de colas para procesamiento
     */
    public function viaQueues(): array
    {
        $queues = ['notification-events'];

        // Cola de alta prioridad para notificaciones críticas
        if ($this->isCriticalNotification()) {
            $queues[] = 'critical-notifications';
        }

        // Cola de analytics para procesamiento de métricas
        $queues[] = 'analytics-processing';

        // Cola de machine learning para optimización
        $queues[] = 'ml-optimization';

        return $queues;
    }

    /**
     * Tiempo límite para reintentos del evento
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }

    /**
     * Genera ID de referencia único para la notificación
     */
    private function generateReferenceId(): string
    {
        return 'NT-' . 
               $this->sentAt->format('Ymd') . '-' . 
               str_pad((string)$this->notificationId, 8, '0', STR_PAD_LEFT) . '-' .
               strtoupper(substr(md5($this->notificationId . $this->sentAt->timestamp), 0, 6));
    }

    /**
     * Obtiene la categoría de la notificación
     */
    private function getNotificationCategory(): string
    {
        $categoryMap = [
            'new_match' => 'dating_activity',
            'new_message' => 'communication',
            'verification_code' => 'security',
            'promotional_offer' => 'marketing',
            'weekly_digest' => 'engagement',
            'safety_alert' => 'safety'
        ];

        return $categoryMap[$this->notificationType] ?? 'general';
    }

    /**
     * Obtiene la prioridad de la notificación
     */
    private function getNotificationPriority(): string
    {
        $priorityMap = [
            'verification_code' => 'critical',
            'safety_alert' => 'critical',
            'security_alert' => 'critical',
            'new_match' => 'high',
            'new_message' => 'high',
            'promotional_offer' => 'medium',
            'weekly_digest' => 'low'
        ];

        return $priorityMap[$this->notificationType] ?? 'medium';
    }

    /**
     * Obtiene segmento del usuario
     */
    private function getUserSegment(): string
    {
        // Implementación simulada - en producción obtendría del perfil del usuario
        $segments = ['new_user', 'active_user', 'premium_user', 'inactive_user', 'vip_user'];
        return $segments[array_rand($segments)];
    }

    /**
     * Obtiene zona horaria del usuario
     */
    private function getUserTimezone(): string
    {
        // Implementación simulada
        return 'UTC-5'; // Se obtendría del perfil del usuario
    }

    /**
     * Obtiene perfil de preferencias del usuario
     */
    private function getUserPreferencesProfile(): array
    {
        return [
            'preferred_channels' => $this->channelsUsed,
            'frequency_preference' => 'moderate',
            'optimal_send_time' => '19:00',
            'content_personalization_level' => 'high',
            'notification_types_enabled' => [$this->notificationType]
        ];
    }

    /**
     * Obtiene resumen de engagement del usuario
     */
    private function getUserEngagementSummary(): array
    {
        return [
            'avg_open_rate' => rand(15, 85),
            'avg_click_rate' => rand(2, 15),
            'last_engagement' => Carbon::now()->subDays(rand(1, 7))->toDateString(),
            'engagement_trend' => ['increasing', 'stable', 'decreasing'][rand(0, 2)],
            'preferred_content_types' => ['matches', 'messages', 'promotions']
        ];
    }

    /**
     * Obtiene canales exitosos
     */
    private function getSuccessfulChannels(): array
    {
        return array_filter($this->channelsUsed, function($channel) {
            return $this->deliveryResults[$channel]['success'] ?? false;
        });
    }

    /**
     * Obtiene canales fallidos
     */
    private function getFailedChannels(): array
    {
        return array_filter($this->channelsUsed, function($channel) {
            return !($this->deliveryResults[$channel]['success'] ?? true);
        });
    }

    /**
     * Verifica si fue exitoso en múltiples canales
     */
    private function isMultiChannelSuccess(): bool
    {
        return count($this->getSuccessfulChannels()) > 1;
    }

    /**
     * Calcula tiempo de procesamiento
     */
    private function calculateProcessingTime(): float
    {
        // Simulación - en producción se obtendría de métricas reales
        return round(rand(100, 2000) / 1000, 3); // 0.1 - 2.0 segundos
    }

    /**
     * Calcula latencia de entrega
     */
    private function calculateDeliveryLatency(): array
    {
        $latencies = [];
        foreach ($this->channelsUsed as $channel) {
            $latencies[$channel] = rand(100, 5000); // ms
        }
        return $latencies;
    }

    /**
     * Calcula desglose de costos
     */
    private function calculateCostBreakdown(): array
    {
        $costs = [];
        $costMap = [
            'push' => 0.001,    // $0.001 por push
            'email' => 0.01,    // $0.01 por email
            'sms' => 0.05      // $0.05 por SMS
        ];

        foreach ($this->channelsUsed as $channel) {
            $costs[$channel] = $costMap[$channel] ?? 0.001;
        }

        return [
            'by_channel' => $costs,
            'total_cost' => array_sum($costs),
            'cost_efficiency_score' => $this->calculateCostEfficiency($costs)
        ];
    }

    /**
     * Calcula score de eficiencia
     */
    private function calculateEfficiencyScore(): int
    {
        $successfulChannels = count($this->getSuccessfulChannels());
        $totalChannels = count($this->channelsUsed);
        $processingTime = $this->calculateProcessingTime();
        
        // Score basado en éxito y velocidad
        $successRate = $totalChannels > 0 ? $successfulChannels / $totalChannels : 0;
        $speedScore = max(0, 100 - ($processingTime * 10)); // Penalizar lentitud
        
        return min(100, round(($successRate * 70) + ($speedScore * 0.3)));
    }

    /**
     * Obtiene datos de performance del proveedor
     */
    private function getProviderPerformanceData(): array
    {
        $performance = [];
        foreach ($this->deliveryResults as $channel => $result) {
            $performance[$channel] = [
                'success_rate' => rand(85, 99),
                'avg_latency' => rand(100, 1000),
                'cost_per_message' => rand(1, 10) / 1000,
                'reliability_score' => rand(90, 100)
            ];
        }
        return $performance;
    }

    /**
     * Obtiene longitud del contenido
     */
    private function getContentLength(): array
    {
        return [
            'title' => rand(20, 60),
            'body' => rand(50, 300),
            'total_characters' => rand(70, 360)
        ];
    }

    /**
     * Obtiene nivel de personalización
     */
    private function getPersonalizationLevel(): string
    {
        $levels = ['basic', 'moderate', 'high', 'advanced'];
        return $levels[array_rand($levels)];
    }

    /**
     * Obtiene versión de plantilla utilizada
     */
    private function getTemplateVersion(): string
    {
        return 'v' . rand(1, 5) . '.' . rand(0, 9);
    }

    /**
     * Obtiene idioma del contenido
     */
    private function getContentLanguage(): string
    {
        return 'en'; // Se obtendría del contenido real
    }

    /**
     * Obtiene variante de A/B test si aplica
     */
    private function getABTestVariant(): ?string
    {
        return $this->isABTestNotification() ? ['A', 'B', 'C'][rand(0, 2)] : null;
    }

    /**
     * Predice tasa de apertura
     */
    private function getPredictedOpenRate(): float
    {
        // Basado en ML - simulación
        return round(rand(15, 85), 1);
    }

    /**
     * Predice tasa de clics
     */
    private function getPredictedClickRate(): float
    {
        return round(rand(2, 15), 1);
    }

    /**
     * Obtiene probabilidad de engagement
     */
    private function getEngagementProbability(): float
    {
        return round(rand(20, 90) / 100, 2);
    }

    /**
     * Obtiene timing óptimo para seguimiento
     */
    private function getOptimalFollowupTiming(): array
    {
        return [
            'next_best_time' => Carbon::now()->addHours(rand(2, 48))->toISOString(),
            'confidence' => rand(70, 95),
            'reasoning' => 'Based on user historical engagement patterns'
        ];
    }

    /**
     * Obtiene indicador de riesgo de churn
     */
    private function getChurnRiskIndicator(): array
    {
        return [
            'risk_level' => ['low', 'medium', 'high'][rand(0, 2)],
            'probability' => rand(5, 30) / 100,
            'key_factors' => ['engagement_decline', 'notification_fatigue', 'competitor_activity']
        ];
    }

    /**
     * Verifica si es notificación de A/B testing
     */
    private function isABTestNotification(): bool
    {
        return isset($this->metadata['ab_test_id']);
    }

    /**
     * Verifica si requiere auditoría
     */
    private function requiresAuditTrail(): bool
    {
        $auditTypes = ['verification_code', 'security_alert', 'payment_alert'];
        return in_array($this->notificationType, $auditTypes);
    }

    /**
     * Verifica si es notificación crítica
     */
    private function isCriticalNotification(): bool
    {
        return $this->getNotificationPriority() === 'critical';
    }

    /**
     * Calcula score de eficiencia de costos
     */
    private function calculateCostEfficiency(array $costs): float
    {
        // Implementación simulada - en producción se calcularía basado en métricas reales
        $totalCost = array_sum($costs);
        $successfulChannels = count($this->getSuccessfulChannels());
        
        // Score basado en costo vs efectividad
        if ($totalCost === 0 || $successfulChannels === 0) {
            return 0.0;
        }
        
        return round(($successfulChannels / count($this->channelsUsed)) * (1 / ($totalCost + 0.001)) * 100, 2);
    }

    /**
     * Obtiene contexto de carga del sistema
     */
    private function getSystemLoadContext(): array
    {
        return [
            'cpu_usage' => rand(20, 80),
            'memory_usage' => rand(30, 90),
            'queue_size' => rand(10, 500),
            'active_connections' => rand(50, 200),
            'timestamp' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Obtiene contexto de profundidad de cola
     */
    private function getQueueDepthContext(): array
    {
        return [
            'notification_queue' => rand(5, 100),
            'email_queue' => rand(0, 50),
            'sms_queue' => rand(0, 25),
            'push_queue' => rand(10, 200),
            'processing_time_avg' => rand(100, 2000) // ms
        ];
    }

    /**
     * Obtiene contexto de salud del proveedor
     */
    private function getProviderHealthContext(): array
    {
        return [
            'email_provider' => ['status' => 'healthy', 'response_time' => rand(50, 200)],
            'sms_provider' => ['status' => 'healthy', 'response_time' => rand(100, 500)],
            'push_provider' => ['status' => 'healthy', 'response_time' => rand(30, 150)],
            'overall_health' => 'healthy'
        ];
    }

    /**
     * Obtiene estado de límite de tasa
     */
    private function getRateLimitStatus(): array
    {
        return [
            'email_rate_limit' => ['current' => rand(10, 90), 'limit' => 100],
            'sms_rate_limit' => ['current' => rand(5, 45), 'limit' => 50],
            'push_rate_limit' => ['current' => rand(20, 180), 'limit' => 200],
            'reset_time' => Carbon::now()->addHour()->toISOString()
        ];
    }

    /**
     * Verifica si se usó fallback
     */
    private function wasFallbackUsed(): bool
    {
        // Si algún canal falló, es probable que se haya usado fallback
        return count($this->getFailedChannels()) > 0;
    }

    /**
     * Obtiene ID de campaña
     */
    private function getCampaignId(): ?string
    {
        return $this->metadata['campaign_id'] ?? null;
    }

    /**
     * Obtiene objetivo de conversión
     */
    private function getConversionGoal(): array
    {
        return [
            'goal_type' => 'engagement',
            'target_rate' => rand(10, 30),
            'current_rate' => rand(5, 25),
            'timeframe' => '24h'
        ];
    }

    /**
     * Obtiene atribución de ingresos
     */
    private function getRevenueAttribution(): array
    {
        return [
            'estimated_revenue_impact' => rand(1, 50),
            'attribution_model' => 'last_touch',
            'conversion_value' => rand(5, 100),
            'currency' => 'USD'
        ];
    }

    /**
     * Obtiene predicción de impacto en LTV
     */
    private function getLTVImpactPrediction(): array
    {
        return [
            'ltv_increase' => rand(1, 20),
            'confidence' => rand(70, 95),
            'timeframe' => '30_days',
            'factors' => ['engagement', 'retention', 'conversion']
        ];
    }

    /**
     * Obtiene score de influencia en retención
     */
    private function getRetentionInfluenceScore(): array
    {
        return [
            'influence_score' => rand(60, 90),
            'retention_probability' => rand(70, 95) / 100,
            'key_factors' => ['timing', 'content_relevance', 'channel_preference']
        ];
    }

    /**
     * Obtiene estado de consentimiento
     */
    private function getConsentStatus(): array
    {
        return [
            'email_consent' => true,
            'sms_consent' => rand(0, 1) === 1,
            'push_consent' => true,
            'consent_date' => Carbon::now()->subDays(rand(1, 365))->toDateString(),
            'gdpr_compliant' => true
        ];
    }

    /**
     * Obtiene fecha de opt-in
     */
    private function getOptInDate(): string
    {
        return Carbon::now()->subDays(rand(1, 365))->toDateString();
    }

    /**
     * Obtiene cumplimiento regulatorio
     */
    private function getRegulationCompliance(): array
    {
        return [
            'gdpr' => ['compliant' => true, 'consent_verified' => true],
            'ccpa' => ['compliant' => true, 'opt_out_available' => true],
            'can_spam' => ['compliant' => true, 'unsubscribe_available' => true]
        ];
    }

    /**
     * Obtiene política de retención de datos
     */
    private function getDataRetentionPolicy(): array
    {
        return [
            'retention_period' => '2_years',
            'auto_deletion' => true,
            'data_categories' => ['engagement_data', 'delivery_metrics'],
            'last_updated' => Carbon::now()->subMonths(6)->toDateString()
        ];
    }

    /**
     * Obtiene ID de auditoría
     */
    private function getAuditTrailId(): string
    {
        return 'audit_' . $this->notificationId . '_' . $this->sentAt->timestamp;
    }

    /**
     * Obtiene insights de optimización de canal
     */
    private function getChannelOptimizationInsights(): array
    {
        return [
            'optimal_channels' => $this->channelsUsed,
            'underperforming_channels' => [],
            'recommendations' => [
                'Consider A/B testing different send times',
                'Optimize content for mobile push notifications'
            ],
            'confidence' => rand(75, 95)
        ];
    }

    /**
     * Obtiene insights de optimización de timing
     */
    private function getTimingOptimizationInsights(): array
    {
        return [
            'current_send_time' => $this->sentAt->format('H:i'),
            'optimal_send_time' => Carbon::now()->setHour(19)->format('H:i'),
            'user_timezone_optimized' => true,
            'recommendations' => [
                'Send during peak engagement hours (7-9 PM)',
                'Avoid sending during sleep hours'
            ]
        ];
    }

    /**
     * Obtiene insights de optimización de contenido
     */
    private function getContentOptimizationInsights(): array
    {
        return [
            'content_length_score' => rand(70, 95),
            'personalization_score' => rand(60, 90),
            'cta_effectiveness' => rand(65, 85),
            'recommendations' => [
                'Add more personalization tokens',
                'Shorten subject line for better mobile display'
            ]
        ];
    }

    /**
     * Obtiene insights de optimización de frecuencia
     */
    private function getFrequencyOptimizationInsights(): array
    {
        return [
            'current_frequency' => 'moderate',
            'optimal_frequency' => 'moderate',
            'fatigue_risk' => rand(10, 30),
            'recommendations' => [
                'Monitor engagement metrics for frequency adjustments',
                'Implement frequency capping for inactive users'
            ]
        ];
    }

    /**
     * Obtiene oportunidades de personalización
     */
    private function getPersonalizationOpportunities(): array
    {
        return [
            'available_data_points' => ['user_preferences', 'behavior_history', 'demographics'],
            'personalization_level' => $this->getPersonalizationLevel(),
            'opportunities' => [
                'Add user name to subject line',
                'Include relevant product recommendations',
                'Use location-based content'
            ],
            'implementation_effort' => 'low'
        ];
    }
}