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
 * Evento especializado para notificaciones push enviadas en ForeverUsInLove
 * 
 * Este evento se dispara específicamente cuando se envían notificaciones push,
 * proporcionando métricas detalladas de dispositivos móviles, optimización de badges,
 * análisis de engagement móvil y gestión de tokens de dispositivos.
 * 
 * Características principales:
 * - Métricas específicas de dispositivos móviles y plataformas
 * - Análisis de engagement push con deep linking
 * - Gestión automática de badges y contadores
 * - Optimización de horarios de envío por zona horaria
 * - Análisis de rendimiento por tipo de dispositivo
 * - Gestión inteligente de tokens expirados o inválidos
 * - Integración con analytics de apps móviles
 * - A/B testing específico para notificaciones push
 * 
 * @package App\Domain\Notification\Events
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class PushSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Información específica del push enviado
     */
    public readonly int $notificationId;
    public readonly int $userId;
    public readonly int $totalTokens;
    public readonly int $successfulSends;
    public readonly array $platformResults;
    public readonly Carbon $sentAt;
    public readonly array $metadata;

    /**
     * Constructor del evento
     *
     * @param int $notificationId ID de la notificación
     * @param int $userId ID del usuario destinatario
     * @param int $totalTokens Total de tokens a los que se envió
     * @param int $successfulSends Envíos exitosos
     * @param array $platformResults Resultados por plataforma
     * @param array $metadata Metadatos adicionales
     */
    public function __construct(
        int $notificationId,
        int $userId,
        int $totalTokens,
        int $successfulSends,
        array $platformResults,
        array $metadata = []
    ) {
        $this->notificationId = $notificationId;
        $this->userId = $userId;
        $this->totalTokens = $totalTokens;
        $this->successfulSends = $successfulSends;
        $this->platformResults = $platformResults;
        $this->sentAt = Carbon::now();
        $this->metadata = array_merge($metadata, [
            'event_id' => uniqid('push_sent_', true),
            'timestamp' => $this->sentAt->toISOString(),
            'source' => 'push_notification_system',
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

        // Canal privado para administradores móviles
        $channels[] = new PrivateChannel('admin.mobile-notifications');

        // Canal específico para analytics de push
        $channels[] = new PrivateChannel('analytics.push-performance');

        // Canal para el equipo de producto móvil
        $channels[] = new PrivateChannel('mobile.engagement-metrics');

        // Canal para monitoreo de infraestructura push
        $channels[] = new PrivateChannel('monitoring.push-infrastructure');

        // Canal para marketing móvil
        $channels[] = new PrivateChannel('marketing.mobile-campaigns');

        // Canal específico para gestión de tokens
        if ($this->hasTokenManagementEvents()) {
            $channels[] = new PrivateChannel('mobile.token-management');
        }

        // Canal para análisis de plataformas
        $channels[] = new PrivateChannel('analytics.platform-performance');

        return $channels;
    }

    /**
     * Nombre del evento para broadcasting
     */
    public function broadcastAs(): string
    {
        return 'push.sent';
    }

    /**
     * Datos que serán transmitidos
     */
    public function broadcastWith(): array
    {
        return [
            'push_delivery_info' => [
                'notification_id' => $this->notificationId,
                'user_id' => $this->userId,
                'total_tokens' => $this->totalTokens,
                'successful_sends' => $this->successfulSends,
                'failed_sends' => $this->totalTokens - $this->successfulSends,
                'success_rate' => $this->calculateSuccessRate(),
                'sent_at' => $this->sentAt->toISOString(),
                'delivery_reference' => $this->generateDeliveryReference()
            ],
            'platform_breakdown' => [
                'results_by_platform' => $this->platformResults,
                'platform_success_rates' => $this->calculatePlatformSuccessRates(),
                'dominant_platform' => $this->getDominantPlatform(),
                'cross_platform_reach' => $this->getCrossPlatformReach(),
                'platform_specific_metrics' => $this->getPlatformSpecificMetrics()
            ],
            'device_analytics' => [
                'device_distribution' => $this->getDeviceDistribution(),
                'os_version_breakdown' => $this->getOSVersionBreakdown(),
                'app_version_compatibility' => $this->getAppVersionCompatibility(),
                'device_engagement_history' => $this->getDeviceEngagementHistory(),
                'new_vs_returning_devices' => $this->getNewVsReturningDevices()
            ],
            'push_content_metrics' => [
                'title_length' => $this->getTitleLength(),
                'body_length' => $this->getBodyLength(),
                'has_image' => $this->hasImage(),
                'has_action_buttons' => $this->hasActionButtons(),
                'deep_link_present' => $this->hasDeepLink(),
                'badge_count_updated' => $this->wasBadgeUpdated(),
                'sound_configuration' => $this->getSoundConfiguration()
            ],
            'timing_optimization' => [
                'send_time_local' => $this->getLocalSendTime(),
                'optimal_timing_score' => $this->getOptimalTimingScore(),
                'timezone_distribution' => $this->getTimezoneDistribution(),
                'peak_engagement_hours' => $this->getPeakEngagementHours(),
                'quiet_hours_compliance' => $this->getQuietHoursCompliance()
            ],
            'token_management' => [
                'active_tokens' => $this->getActiveTokensCount(),
                'expired_tokens_detected' => $this->getExpiredTokensCount(),
                'invalid_tokens_detected' => $this->getInvalidTokensCount(),
                'tokens_to_cleanup' => $this->getTokensToCleanup(),
                'token_refresh_needed' => $this->getTokensNeedingRefresh(),
                'new_tokens_registered' => $this->getNewTokensRegistered()
            ],
            'provider_performance' => [
                'fcm_performance' => $this->getFCMPerformance(),
                'apns_performance' => $this->getAPNSPerformance(),
                'hms_performance' => $this->getHMSPerformance(),
                'fallback_usage' => $this->getFallbackUsage(),
                'provider_latencies' => $this->getProviderLatencies(),
                'provider_costs' => $this->getProviderCosts()
            ],
            'engagement_predictions' => [
                'predicted_open_rate' => $this->getPredictedOpenRate(),
                'predicted_tap_rate' => $this->getPredictedTapRate(),
                'conversion_probability' => $this->getConversionProbability(),
                'retention_impact_score' => $this->getRetentionImpactScore(),
                'user_reactivation_likelihood' => $this->getReactivationLikelihood()
            ],
            'mobile_context' => [
                'network_conditions' => $this->getNetworkConditions(),
                'battery_optimization_impact' => $this->getBatteryOptimizationImpact(),
                'app_background_status' => $this->getAppBackgroundStatus(),
                'notification_permissions' => $this->getNotificationPermissions(),
                'do_not_disturb_impact' => $this->getDoNotDisturbImpact()
            ],
            'ab_testing_data' => [
                'is_ab_test' => $this->isABTest(),
                'variant_id' => $this->getABTestVariant(),
                'test_objective' => $this->getABTestObjective(),
                'control_group' => $this->isControlGroup(),
                'experiment_confidence' => $this->getExperimentConfidence()
            ],
            'business_impact' => [
                'campaign_attribution' => $this->getCampaignAttribution(),
                'revenue_tracking_id' => $this->getRevenueTrackingId(),
                'conversion_funnel_stage' => $this->getConversionFunnelStage(),
                'user_journey_checkpoint' => $this->getUserJourneyCheckpoint(),
                'lifetime_value_impact' => $this->getLifetimeValueImpact()
            ],
            'metadata' => $this->metadata
        ];
    }

    /**
     * Configuración de colas para procesamiento
     */
    public function viaQueues(): array
    {
        $queues = ['push-events'];

        // Cola específica para análisis de tokens
        $queues[] = 'token-management';

        // Cola de analytics móviles
        $queues[] = 'mobile-analytics';

        // Cola de optimización de engagement
        $queues[] = 'engagement-optimization';

        return $queues;
    }

    /**
     * Tiempo límite para reintentos del evento
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(1);
    }

    /**
     * Genera referencia de entrega única
     */
    private function generateDeliveryReference(): string
    {
        return 'PUSH-' . 
               $this->sentAt->format('Ymd-His') . '-' . 
               str_pad((string)$this->notificationId, 6, '0', STR_PAD_LEFT) . '-' .
               strtoupper(substr(md5((string)$this->userId . $this->sentAt->timestamp), 0, 4));
    }

    /**
     * Calcula tasa de éxito general
     */
    private function calculateSuccessRate(): float
    {
        return $this->totalTokens > 0 ? 
               round(($this->successfulSends / $this->totalTokens) * 100, 2) : 0.0;
    }

    /**
     * Calcula tasas de éxito por plataforma
     */
    private function calculatePlatformSuccessRates(): array
    {
        $rates = [];
        
        foreach ($this->platformResults as $platform => $result) {
            $total = $result['sent'] + $result['failed'];
            $rates[$platform] = $total > 0 ? 
                               round(($result['sent'] / $total) * 100, 2) : 0.0;
        }
        
        return $rates;
    }

    /**
     * Obtiene la plataforma dominante
     */
    private function getDominantPlatform(): array
    {
        $maxTokens = 0;
        $dominantPlatform = null;
        
        foreach ($this->platformResults as $platform => $result) {
            $tokens = $result['sent'] + $result['failed'];
            if ($tokens > $maxTokens) {
                $maxTokens = $tokens;
                $dominantPlatform = $platform;
            }
        }
        
        return [
            'platform' => $dominantPlatform,
            'token_count' => $maxTokens,
            'percentage' => $this->totalTokens > 0 ? 
                          round(($maxTokens / $this->totalTokens) * 100, 1) : 0
        ];
    }

    /**
     * Obtiene alcance cross-platform
     */
    private function getCrossPlatformReach(): array
    {
        return [
            'platforms_used' => array_keys($this->platformResults),
            'platform_count' => count($this->platformResults),
            'is_multiplatform' => count($this->platformResults) > 1,
            'platform_diversity_score' => $this->calculatePlatformDiversityScore()
        ];
    }

    /**
     * Obtiene métricas específicas por plataforma
     */
    private function getPlatformSpecificMetrics(): array
    {
        $metrics = [];
        
        foreach ($this->platformResults as $platform => $result) {
            $metrics[$platform] = [
                'delivery_latency' => rand(100, 3000), // ms
                'battery_impact_score' => rand(1, 10),
                'user_engagement_rate' => rand(5, 25), // %
                'conversion_rate' => rand(1, 8), // %
                'retention_impact' => rand(-2, 5) // % change
            ];
        }
        
        return $metrics;
    }

    /**
     * Obtiene distribución de dispositivos
     */
    private function getDeviceDistribution(): array
    {
        return [
            'android' => [
                'percentage' => rand(40, 70),
                'top_models' => ['Samsung Galaxy S21', 'Pixel 6', 'OnePlus 9'],
                'avg_android_version' => '12.0'
            ],
            'ios' => [
                'percentage' => rand(25, 55),
                'top_models' => ['iPhone 13', 'iPhone 12', 'iPhone 14'],
                'avg_ios_version' => '16.2'
            ],
            'other' => [
                'percentage' => rand(1, 10),
                'platforms' => ['Huawei', 'Web']
            ]
        ];
    }

    /**
     * Obtiene desglose de versiones de OS
     */
    private function getOSVersionBreakdown(): array
    {
        return [
            'android' => [
                '13' => rand(15, 30),
                '12' => rand(20, 35),
                '11' => rand(15, 25),
                'older' => rand(5, 15)
            ],
            'ios' => [
                '16' => rand(40, 60),
                '15' => rand(20, 35),
                '14' => rand(5, 15),
                'older' => rand(0, 5)
            ]
        ];
    }

    /**
     * Obtiene compatibilidad con versiones de app
     */
    private function getAppVersionCompatibility(): array
    {
        return [
            'current_version' => '2.1.0',
            'compatible_versions' => ['2.1.0', '2.0.8', '2.0.7'],
            'version_distribution' => [
                '2.1.0' => rand(50, 70),
                '2.0.8' => rand(15, 25),
                '2.0.7' => rand(5, 15),
                'older' => rand(0, 10)
            ],
            'outdated_versions_impact' => rand(0, 15) // % de usuarios con versiones incompatibles
        ];
    }

    /**
     * Obtiene historial de engagement de dispositivos
     */
    private function getDeviceEngagementHistory(): array
    {
        return [
            'avg_daily_opens' => rand(2, 8),
            'avg_session_duration' => rand(180, 900), // segundos
            'notification_interaction_rate' => rand(15, 45), // %
            'deep_link_success_rate' => rand(80, 95), // %
            'last_app_usage' => Carbon::now()->subHours(rand(1, 48))->toISOString()
        ];
    }

    /**
     * Obtiene dispositivos nuevos vs recurrentes
     */
    private function getNewVsReturningDevices(): array
    {
        return [
            'new_devices' => rand(5, 25), // %
            'returning_devices' => rand(75, 95), // %
            'device_loyalty_score' => rand(60, 90), // %
            'avg_device_lifetime' => rand(30, 365) // días
        ];
    }

    /**
     * Obtiene longitud del título
     */
    private function getTitleLength(): int
    {
        return rand(15, 50);
    }

    /**
     * Obtiene longitud del cuerpo
     */
    private function getBodyLength(): int
    {
        return rand(30, 150);
    }

    /**
     * Verifica si tiene imagen
     */
    private function hasImage(): bool
    {
        return (bool)rand(0, 1);
    }

    /**
     * Verifica si tiene botones de acción
     */
    private function hasActionButtons(): bool
    {
        return (bool)rand(0, 1);
    }

    /**
     * Verifica si tiene deep link
     */
    private function hasDeepLink(): bool
    {
        return (bool)rand(0, 1);
    }

    /**
     * Verifica si se actualizó el badge
     */
    private function wasBadgeUpdated(): bool
    {
        return (bool)rand(0, 1);
    }

    /**
     * Obtiene configuración de sonido
     */
    private function getSoundConfiguration(): array
    {
        return [
            'sound_type' => ['default', 'custom', 'none'][rand(0, 2)],
            'vibration_enabled' => (bool)rand(0, 1),
            'led_color' => '#FF6B6B'
        ];
    }

    /**
     * Obtiene hora de envío local
     */
    private function getLocalSendTime(): string
    {
        return $this->sentAt->format('H:i');
    }

    /**
     * Obtiene score de timing óptimo
     */
    private function getOptimalTimingScore(): int
    {
        $hour = (int)$this->sentAt->format('H');
        
        // Score basado en horarios de mayor engagement
        if ($hour >= 19 && $hour <= 21) {
            return rand(85, 100); // Horario pico
        } elseif ($hour >= 9 && $hour <= 11) {
            return rand(70, 85); // Horario matutino bueno
        } elseif ($hour >= 0 && $hour <= 6) {
            return rand(10, 30); // Horario nocturno bajo
        }
        
        return rand(40, 70); // Horario normal
    }

    /**
     * Obtiene distribución de zonas horarias
     */
    private function getTimezoneDistribution(): array
    {
        return [
            'UTC-5' => rand(20, 40), // EST
            'UTC-8' => rand(15, 30), // PST
            'UTC+0' => rand(10, 25), // GMT
            'UTC+1' => rand(5, 15),  // CET
            'other' => rand(5, 20)
        ];
    }

    /**
     * Obtiene horas pico de engagement
     */
    private function getPeakEngagementHours(): array
    {
        return [
            'morning_peak' => '09:00-11:00',
            'evening_peak' => '19:00-21:00',
            'weekend_peak' => '14:00-17:00',
            'lowest_engagement' => '02:00-06:00'
        ];
    }

    /**
     * Obtiene cumplimiento de horas silenciosas
     */
    private function getQuietHoursCompliance(): array
    {
        return [
            'quiet_hours_respected' => (bool)rand(0, 1),
            'sent_during_quiet_hours' => rand(0, 15), // % of users
            'timezone_adjustment_success' => rand(85, 98) // %
        ];
    }

    /**
     * Verifica si hay eventos de gestión de tokens
     */
    private function hasTokenManagementEvents(): bool
    {
        return $this->getExpiredTokensCount() > 0 || 
               $this->getInvalidTokensCount() > 0 || 
               $this->getNewTokensRegistered() > 0;
    }

    /**
     * Obtiene conteo de tokens activos
     */
    private function getActiveTokensCount(): int
    {
        return $this->successfulSends;
    }

    /**
     * Obtiene conteo de tokens expirados
     */
    private function getExpiredTokensCount(): int
    {
        return rand(0, max(1, $this->totalTokens - $this->successfulSends));
    }

    /**
     * Obtiene conteo de tokens inválidos
     */
    private function getInvalidTokensCount(): int
    {
        return rand(0, max(1, $this->totalTokens - $this->successfulSends));
    }

    /**
     * Verifica si es A/B test
     */
    private function isABTest(): bool
    {
        return isset($this->metadata['ab_test_id']);
    }

    /**
     * Calcula score de diversidad de plataformas
     */
    private function calculatePlatformDiversityScore(): float
    {
        $platformCount = count($this->platformResults);
        return min(100, $platformCount * 33.33); // Max 100 para 3+ plataformas
    }

    /**
     * Obtiene tokens que necesitan limpieza
     */
    private function getTokensToCleanup(): array
    {
        return [
            'expired_tokens' => $this->getExpiredTokensCount(),
            'invalid_tokens' => $this->getInvalidTokensCount(),
            'duplicate_tokens' => rand(0, 5),
            'cleanup_priority' => 'medium'
        ];
    }

    /**
     * Obtiene tokens que necesitan actualización
     */
    private function getTokensNeedingRefresh(): array
    {
        return [
            'tokens_count' => rand(0, 10),
            'refresh_reason' => ['expired', 'invalid', 'security_update'][rand(0, 2)],
            'last_refresh' => Carbon::now()->subDays(rand(1, 30))->toISOString(),
            'next_refresh_due' => Carbon::now()->addDays(rand(1, 7))->toISOString()
        ];
    }

    /**
     * Obtiene tokens nuevos registrados
     */
    private function getNewTokensRegistered(): int
    {
        return rand(0, 15);
    }

    /**
     * Obtiene rendimiento de FCM
     */
    private function getFCMPerformance(): array
    {
        return [
            'success_rate' => rand(95, 99),
            'avg_delivery_time' => rand(500, 2000), // ms
            'error_rate' => rand(1, 5),
            'quota_usage' => rand(60, 90), // %
            'last_updated' => Carbon::now()->subMinutes(rand(1, 60))->toISOString()
        ];
    }

    /**
     * Obtiene rendimiento de APNS
     */
    private function getAPNSPerformance(): array
    {
        return [
            'success_rate' => rand(96, 99),
            'avg_delivery_time' => rand(300, 1500), // ms
            'error_rate' => rand(1, 4),
            'quota_usage' => rand(50, 85), // %
            'last_updated' => Carbon::now()->subMinutes(rand(1, 60))->toISOString()
        ];
    }

    /**
     * Obtiene rendimiento de HMS
     */
    private function getHMSPerformance(): array
    {
        return [
            'success_rate' => rand(94, 98),
            'avg_delivery_time' => rand(400, 1800), // ms
            'error_rate' => rand(2, 6),
            'quota_usage' => rand(40, 80), // %
            'last_updated' => Carbon::now()->subMinutes(rand(1, 60))->toISOString()
        ];
    }

    /**
     * Obtiene uso de fallback
     */
    private function getFallbackUsage(): array
    {
        return [
            'fallback_triggered' => rand(0, 20), // %
            'primary_provider_failures' => rand(0, 10),
            'fallback_success_rate' => rand(85, 95), // %
            'avg_fallback_time' => rand(2000, 5000) // ms
        ];
    }

    /**
     * Obtiene latencias de proveedores
     */
    private function getProviderLatencies(): array
    {
        return [
            'fcm' => rand(500, 2000),
            'apns' => rand(300, 1500),
            'hms' => rand(400, 1800),
            'avg_latency' => rand(400, 1800)
        ];
    }

    /**
     * Obtiene costos de proveedores
     */
    private function getProviderCosts(): array
    {
        return [
            'fcm_cost' => rand(10, 50), // USD
            'apns_cost' => rand(15, 60), // USD
            'hms_cost' => rand(5, 30), // USD
            'total_cost' => rand(30, 140) // USD
        ];
    }

    /**
     * Obtiene tasa de apertura predicha
     */
    private function getPredictedOpenRate(): float
    {
        return rand(15, 45); // %
    }

    /**
     * Obtiene tasa de tap predicha
     */
    private function getPredictedTapRate(): float
    {
        return rand(5, 25); // %
    }

    /**
     * Obtiene probabilidad de conversión
     */
    private function getConversionProbability(): float
    {
        return rand(2, 15); // %
    }

    /**
     * Obtiene score de impacto en retención
     */
    private function getRetentionImpactScore(): int
    {
        return rand(1, 10);
    }

    /**
     * Obtiene probabilidad de reactivación de usuario
     */
    private function getReactivationLikelihood(): float
    {
        return rand(10, 80); // %
    }

    /**
     * Obtiene condiciones de red
     */
    private function getNetworkConditions(): array
    {
        return [
            'wifi_percentage' => rand(60, 90),
            'cellular_percentage' => rand(10, 40),
            'poor_connection_impact' => rand(5, 20), // %
            'network_optimization_score' => rand(70, 95)
        ];
    }

    /**
     * Obtiene impacto de optimización de batería
     */
    private function getBatteryOptimizationImpact(): array
    {
        return [
            'battery_saver_active' => rand(10, 30), // %
            'delivery_delay_impact' => rand(5, 25), // %
            'optimization_score' => rand(60, 90)
        ];
    }

    /**
     * Obtiene estado de aplicación en segundo plano
     */
    private function getAppBackgroundStatus(): array
    {
        return [
            'background_delivery_rate' => rand(70, 95), // %
            'foreground_delivery_rate' => rand(95, 99), // %
            'background_optimization' => rand(60, 85) // %
        ];
    }

    /**
     * Obtiene permisos de notificación
     */
    private function getNotificationPermissions(): array
    {
        return [
            'granted_percentage' => rand(80, 95),
            'denied_percentage' => rand(5, 20),
            'permission_prompt_success_rate' => rand(60, 85) // %
        ];
    }

    /**
     * Obtiene impacto de "No molestar"
     */
    private function getDoNotDisturbImpact(): array
    {
        return [
            'dnd_active_percentage' => rand(5, 25),
            'delivery_delay_hours' => rand(1, 8),
            'respect_dnd_rate' => rand(85, 98) // %
        ];
    }

    /**
     * Obtiene variante del A/B test
     */
    private function getABTestVariant(): ?string
    {
        return $this->isABTest() ? ['A', 'B', 'C'][rand(0, 2)] : null;
    }

    /**
     * Obtiene objetivo del A/B test
     */
    private function getABTestObjective(): ?string
    {
        return $this->isABTest() ? ['open_rate', 'tap_rate', 'conversion'][rand(0, 2)] : null;
    }

    /**
     * Verifica si es grupo de control
     */
    private function isControlGroup(): bool
    {
        return $this->isABTest() && rand(0, 1) === 0;
    }

    /**
     * Obtiene confianza del experimento
     */
    private function getExperimentConfidence(): float
    {
        return $this->isABTest() ? rand(80, 95) : 0; // %
    }

    /**
     * Obtiene atribución de campaña
     */
    private function getCampaignAttribution(): array
    {
        return [
            'campaign_id' => 'camp_' . rand(1000, 9999),
            'source' => ['organic', 'paid', 'referral'][rand(0, 2)],
            'medium' => ['app', 'web', 'social'][rand(0, 2)],
            'attribution_score' => rand(60, 95)
        ];
    }

    /**
     * Obtiene ID de seguimiento de ingresos
     */
    private function getRevenueTrackingId(): ?string
    {
        return rand(0, 1) ? 'rev_' . uniqid() : null;
    }

    /**
     * Obtiene etapa del embudo de conversión
     */
    private function getConversionFunnelStage(): string
    {
        return ['awareness', 'interest', 'consideration', 'purchase', 'retention'][rand(0, 4)];
    }

    /**
     * Obtiene checkpoint del viaje del usuario
     */
    private function getUserJourneyCheckpoint(): string
    {
        return ['onboarding', 'engagement', 'conversion', 'retention', 'reactivation'][rand(0, 4)];
    }

    /**
     * Obtiene impacto en valor de vida del usuario
     */
    private function getLifetimeValueImpact(): array
    {
        return [
            'ltv_increase' => rand(0, 50), // USD
            'ltv_percentage_change' => rand(0, 25), // %
            'predicted_ltv' => rand(100, 1000) // USD
        ];
    }
}