<?php

declare(strict_types=1);

namespace App\Domain\Profile\Events;

use App\Models\User\User;
use App\Models\User\Profile;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

/**
 * ProfilePaused Event
 * 
 * Evento disparado cuando un perfil de usuario es pausado temporalmente
 * en la aplicación de citas ForeverUsInLove.
 * 
 * Este evento permite a otros componentes del sistema reaccionar
 * a la pausa de perfiles para:
 * - Remover perfil de algoritmos de matching activo
 * - Notificar a matches activos sobre indisponibilidad temporal
 * - Preservar datos de engagement para análisis futuro
 * - Configurar reactivación automática programada
 * - Registrar métricas de retention y churn
 * - Activar campañas de re-engagement
 * - Generar insights sobre patrones de pausa
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class ProfilePaused implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Usuario propietario del perfil
     */
    public readonly User $user;
    
    /**
     * Perfil que ha sido pausado
     */
    public readonly Profile $profile;
    
    /**
     * Timestamp del evento
     */
    public readonly Carbon $timestamp;
    
    /**
     * Razón de la pausa
     */
    public readonly string $pauseReason;
    
    /**
     * Fecha de reanudación programada (opcional)
     */
    public readonly ?Carbon $resumeAt;
    
    /**
     * Estado anterior del perfil
     */
    public readonly string $previousStatus;
    
    /**
     * Contexto de la pausa
     */
    public readonly array $pauseContext;
    
    /**
     * Datos de actividad antes de la pausa
     */
    public readonly array $activitySnapshot;
    
    /**
     * Configuración de reactivación
     */
    public readonly array $reactivationConfig;

    /**
     * Create a new event instance.
     *
     * @param User $user Usuario propietario
     * @param Profile $profile Perfil pausado
     * @param string $pauseReason Razón de la pausa
     * @param Carbon|null $resumeAt Fecha de reanudación programada
     */
    public function __construct(
        User $user,
        Profile $profile,
        string $pauseReason = '',
        ?Carbon $resumeAt = null
    ) {
        $this->user = $user;
        $this->profile = $profile;
        $this->timestamp = now();
        $this->pauseReason = $pauseReason;
        $this->resumeAt = $resumeAt;
        $this->previousStatus = $profile->previous_status ?? 'active';
        $this->pauseContext = $this->buildPauseContext($user, $profile, $pauseReason);
        $this->activitySnapshot = $this->captureActivitySnapshot($user, $profile);
        $this->reactivationConfig = $this->buildReactivationConfig($user, $resumeAt);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Canal para el usuario - confirmación de pausa
            new PrivateChannel("user.{$this->user->id}.profile-status"),
            
            // Canal para matches activos - notificación de indisponibilidad
            new PrivateChannel("user.{$this->user->id}.match-notifications"),
            
            // Canal administrativo para métricas de churn
            new PrivateChannel('admin.profile-pauses'),
            
            // Canal para analytics de retention
            new PrivateChannel('analytics.user-retention'),
            
            // Canal para sistema de matching - remover de pools activos
            new PrivateChannel('matching.profile-status-changes'),
            
            // Canal para re-engagement campaigns
            new PrivateChannel('campaigns.re-engagement')
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'profile_id' => $this->profile->id,
            'timestamp' => $this->timestamp->toISOString(),
            
            // Estado de la pausa
            'pause_info' => [
                'paused_at' => $this->timestamp->toISOString(),
                'pause_reason_category' => $this->categorizePauseReason($this->pauseReason),
                'is_temporary' => $this->resumeAt !== null,
                'resume_scheduled_at' => $this->resumeAt?->toISOString(),
                'previous_status' => $this->previousStatus,
                'expected_duration_days' => $this->calculateExpectedDuration()
            ],
            
            // Impacto en matching
            'matching_impact' => [
                'removed_from_discovery' => true,
                'active_conversations_affected' => $this->activitySnapshot['active_conversations'] ?? 0,
                'pending_matches_affected' => $this->activitySnapshot['pending_matches'] ?? 0,
                'visibility_status' => 'hidden',
                'matching_pool_removal' => 'immediate'
            ],
            
            // Configuración de notificaciones
            'notification_settings' => [
                'notify_matches' => $this->shouldNotifyMatches(),
                'match_notification_message' => $this->getMatchNotificationMessage(),
                'auto_response_enabled' => $this->pauseContext['auto_response_enabled'] ?? false,
                'auto_response_message' => $this->pauseContext['auto_response_message'] ?? null
            ],
            
            // Datos de reactivación
            'reactivation' => [
                'automatic_resume' => $this->resumeAt !== null,
                'resume_conditions' => $this->reactivationConfig['conditions'] ?? [],
                'reminder_schedule' => $this->reactivationConfig['reminders'] ?? [],
                'incentives_available' => $this->reactivationConfig['incentives'] ?? []
            ],
            
            // Preservación de datos
            'data_preservation' => [
                'activity_data_saved' => true,
                'preferences_maintained' => true,
                'match_history_preserved' => true,
                'conversation_history_maintained' => true
            ]
        ];
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'profile.paused';
    }

    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function shouldBroadcast(): bool
    {
        return config('broadcasting.enabled', false) && 
               config('app.real_time_notifications', false);
    }

    /**
     * Get comprehensive event metadata for analytics and processing
     *
     * @return array
     */
    public function getEventMetadata(): array
    {
        return [
            'event_type' => 'profile_paused',
            'event_version' => '2.0',
            'user_id' => $this->user->id,
            'profile_id' => $this->profile->id,
            'timestamp' => $this->timestamp->toISOString(),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            
            // Información del usuario
            'user_context' => [
                'username' => $this->user->username,
                'account_age_days' => $this->user->created_at->diffInDays($this->timestamp),
                'subscription_type' => $this->user->subscription_type ?? 'free',
                'total_logins' => $this->getUserTotalLogins(),
                'last_login_at' => $this->user->last_login_at?->toISOString(),
                'days_since_last_login' => $this->user->last_login_at ? 
                    $this->timestamp->diffInDays($this->user->last_login_at) : null
            ],
            
            // Contexto detallado de la pausa
            'pause_details' => [
                'reason' => $this->pauseReason,
                'reason_category' => $this->categorizePauseReason($this->pauseReason),
                'reason_subcategory' => $this->subcategorizePauseReason($this->pauseReason),
                'user_initiated' => $this->pauseContext['user_initiated'] ?? true,
                'temporary_pause' => $this->resumeAt !== null,
                'scheduled_resume' => $this->resumeAt?->toISOString(),
                'expected_duration_hours' => $this->calculateExpectedDurationHours(),
                'pause_frequency' => $this->calculateUserPauseFrequency()
            ],
            
            // Estado del perfil antes de la pausa
            'profile_state_before_pause' => [
                'status' => $this->previousStatus,
                'completeness_percentage' => $this->profile->completeness_percentage ?? 0,
                'profile_score' => $this->profile->profile_score ?? 0,
                'total_photos' => $this->activitySnapshot['total_photos'] ?? 0,
                'has_bio' => !empty($this->profile->bio),
                'personality_tests_completed' => $this->activitySnapshot['personality_tests'] ?? 0
            ],
            
            // Snapshot de actividad
            'activity_snapshot' => array_merge($this->activitySnapshot, [
                'profile_views_7d' => $this->getProfileViews(7),
                'likes_received_7d' => $this->getLikesReceived(7),
                'matches_7d' => $this->getMatches(7),
                'conversations_started_7d' => $this->getConversationsStarted(7),
                'messages_sent_7d' => $this->getMessagesSent(7),
                'app_sessions_7d' => $this->getAppSessions(7),
                'total_time_spent_7d_minutes' => $this->getTotalTimeSpent(7)
            ]),
            
            // Análisis de engagement antes de pausa
            'engagement_analysis' => [
                'engagement_trend' => $this->analyzeEngagementTrend(),
                'match_success_rate' => $this->calculateMatchSuccessRate(),
                'conversation_conversion_rate' => $this->calculateConversationConversionRate(),
                'activity_level' => $this->categorizeActivityLevel(),
                'frustration_indicators' => $this->identifyFrustrationIndicators(),
                'satisfaction_score' => $this->calculateSatisfactionScore()
            ],
            
            // Configuración de reactivación
            'reactivation_strategy' => array_merge($this->reactivationConfig, [
                'auto_resume_enabled' => $this->resumeAt !== null,
                'reminder_cadence' => $this->reactivationConfig['reminder_schedule'] ?? [],
                'incentive_tier' => $this->determineIncentiveTier(),
                'personalized_campaign' => $this->getPersonalizedCampaignId(),
                're_engagement_probability' => $this->calculateReEngagementProbability()
            ])
        ];
    }

    /**
     * Get churn risk analysis data
     *
     * @return array
     */
    public function getChurnRiskAnalysis(): array
    {
        return [
            'churn_risk_assessment' => [
                'overall_risk_score' => $this->calculateOverallChurnRisk(),
                'risk_category' => $this->categorizeChurnRisk(),
                'time_sensitive' => $this->isTimeSensitiveCase(),
                'intervention_priority' => $this->calculateInterventionPriority()
            ],
            'risk_factors' => [
                'engagement_decline' => $this->hasEngagementDecline(),
                'low_match_success' => $this->hasLowMatchSuccess(),
                'negative_experiences' => $this->hasNegativeExperiences(),
                'competitor_activity' => $this->detectCompetitorActivity(),
                'seasonal_patterns' => $this->identifySeasonalPatterns(),
                'demographic_risk_factors' => $this->getDemographicRiskFactors()
            ],
            'protective_factors' => [
                'investment_level' => $this->calculateInvestmentLevel(),
                'social_connections' => $this->countSocialConnections(),
                'premium_features_usage' => $this->getPremiumFeaturesUsage(),
                'platform_advocacy' => $this->measurePlatformAdvocacy(),
                'completion_achievements' => $this->getCompletionAchievements()
            ],
            'historical_patterns' => [
                'previous_pauses' => $this->getPreviousPauseHistory(),
                'return_patterns' => $this->analyzeReturnPatterns(),
                'seasonal_behavior' => $this->analyzeSeasonalBehavior(),
                'lifecycle_stage' => $this->determineLifecycleStage()
            ],
            'prediction_model' => [
                'return_probability' => $this->predictReturnProbability(),
                'optimal_reactivation_timing' => $this->predictOptimalReactivationTiming(),
                'most_effective_incentives' => $this->predictMostEffectiveIncentives(),
                'personalization_recommendations' => $this->getPersonalizationRecommendations()
            ]
        ];
    }

    /**
     * Get impact on matches and conversations
     *
     * @return array
     */
    public function getMatchesAndConversationsImpact(): array
    {
        return [
            'immediate_impact' => [
                'active_conversations' => $this->activitySnapshot['active_conversations'] ?? 0,
                'pending_matches' => $this->activitySnapshot['pending_matches'] ?? 0,
                'scheduled_dates' => $this->activitySnapshot['scheduled_dates'] ?? 0,
                'ongoing_interests' => $this->activitySnapshot['ongoing_interests'] ?? 0
            ],
            'notification_strategy' => [
                'matches_to_notify' => $this->getMatchesToNotify(),
                'notification_timing' => $this->getOptimalNotificationTiming(),
                'message_personalization' => $this->getPersonalizedMessages(),
                'follow_up_schedule' => $this->getFollowUpSchedule()
            ],
            'conversation_management' => [
                'auto_response_setup' => $this->setupAutoResponse(),
                'conversation_preservation' => $this->getConversationPreservationSettings(),
                'reactivation_conversation_strategy' => $this->getReactivationConversationStrategy(),
                'match_queue_management' => $this->getMatchQueueManagement()
            ],
            'relationship_continuity' => [
                'strong_connections_identified' => $this->identifyStrongConnections(),
                'priority_relationships' => $this->identifyPriorityRelationships(),
                'relationship_maintenance_plan' => $this->createRelationshipMaintenancePlan(),
                'return_impact_on_relationships' => $this->assessReturnImpactOnRelationships()
            ]
        ];
    }

    /**
     * Get re-engagement campaign data
     *
     * @return array
     */
    public function getReEngagementCampaignData(): array
    {
        return [
            'campaign_segmentation' => [
                'user_segment' => $this->determineUserSegment(),
                'pause_reason_segment' => $this->categorizePauseReason($this->pauseReason),
                'engagement_level_segment' => $this->categorizeEngagementLevel(),
                'value_segment' => $this->determineValueSegment(),
                'lifecycle_segment' => $this->determineLifecycleSegment()
            ],
            'personalized_messaging' => [
                'primary_message_theme' => $this->selectPrimaryMessageTheme(),
                'emotional_tone' => $this->selectEmotionalTone(),
                'value_propositions' => $this->selectRelevantValuePropositions(),
                'social_proof_elements' => $this->selectSocialProofElements(),
                'urgency_level' => $this->determineUrgencyLevel()
            ],
            'channel_strategy' => [
                'primary_channel' => $this->selectPrimaryChannel(),
                'secondary_channels' => $this->selectSecondaryChannels(),
                'channel_timing' => $this->optimizeChannelTiming(),
                'frequency_cap' => $this->determineFrequencyCap(),
                'cross_channel_coordination' => $this->planCrossChannelCoordination()
            ],
            'incentive_strategy' => [
                'incentive_type' => $this->selectIncentiveType(),
                'incentive_value' => $this->calculateIncentiveValue(),
                'incentive_timing' => $this->optimizeIncentiveTiming(),
                'incentive_personalization' => $this->personalizeIncentive(),
                'success_metrics' => $this->defineIncentiveSuccessMetrics()
            ],
            'campaign_timing' => [
                'initial_contact_delay' => $this->calculateInitialContactDelay(),
                'follow_up_sequence' => $this->createFollowUpSequence(),
                'seasonal_considerations' => $this->incorporateSeasonalFactors(),
                'optimal_send_times' => $this->determineOptimalSendTimes(),
                'campaign_duration' => $this->calculateCampaignDuration()
            ]
        ];
    }

    // ========================================
    // MÉTODOS PRIVADOS HELPER
    // ========================================

    /**
     * Construye contexto de la pausa
     */
    private function buildPauseContext(User $user, Profile $profile, string $reason): array
    {
        return [
            'user_initiated' => true, // En la mayoría de casos
            'pause_trigger' => $this->identifyPauseTrigger($reason),
            'recent_activity' => $this->getRecentActivity($user),
            'frustration_signals' => $this->detectFrustrationSignals($user),
            'auto_response_enabled' => $this->shouldEnableAutoResponse($reason),
            'auto_response_message' => $this->generateAutoResponseMessage($reason),
            'pause_frequency' => $this->calculateUserPauseFrequency(),
            'device_info' => $this->getDeviceInfo()
        ];
    }

    /**
     * Captura snapshot de actividad
     */
    private function captureActivitySnapshot(User $user, Profile $profile): array
    {
        return [
            'total_photos' => $this->getUserPhotoCount($user->id),
            'personality_tests' => $this->getUserPersonalityTestCount($user->id),
            'active_conversations' => $this->getActiveConversationsCount($user->id),
            'pending_matches' => $this->getPendingMatchesCount($user->id),
            'scheduled_dates' => $this->getScheduledDatesCount($user->id),
            'profile_completeness' => $profile->completeness_percentage ?? 0,
            'profile_score' => $profile->profile_score ?? 0,
            'last_activity' => $user->last_activity_at?->toISOString(),
            'engagement_level' => $this->calculateCurrentEngagementLevel($user)
        ];
    }

    /**
     * Construye configuración de reactivación
     */
    private function buildReactivationConfig(User $user, ?Carbon $resumeAt): array
    {
        return [
            'automatic_resume' => $resumeAt !== null,
            'resume_datetime' => $resumeAt?->toISOString(),
            'conditions' => $this->getReactivationConditions($user),
            'reminders' => $this->buildReminderSchedule($user, $resumeAt),
            'incentives' => $this->getAvailableIncentives($user),
            'personalization' => $this->getPersonalizationConfig($user)
        ];
    }

    /**
     * Categoriza la razón de pausa
     */
    private function categorizePauseReason(string $reason): string
    {
        $categories = [
            'personal' => ['personal', 'relationship', 'family', 'health'],
            'platform' => ['frustrated', 'not_working', 'poor_matches', 'technical'],
            'temporary' => ['vacation', 'busy', 'break', 'hiatus'],
            'financial' => ['cost', 'premium', 'subscription'],
            'safety' => ['harassment', 'safety', 'privacy']
        ];

        $reason = strtolower($reason);
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($reason, $keyword)) {
                    return $category;
                }
            }
        }

        return 'other';
    }

    /**
     * Calcula duración esperada
     */
    private function calculateExpectedDuration(): ?int
    {
        if (!$this->resumeAt) {
            return null;
        }

        return (int) $this->timestamp->diffInDays($this->resumeAt);
    }

    /**
     * Determina si notificar a matches
     */
    private function shouldNotifyMatches(): bool
    {
        return ($this->activitySnapshot['active_conversations'] ?? 0) > 0;
    }

    /**
     * Obtiene mensaje para matches
     */
    private function getMatchNotificationMessage(): string
    {
        if ($this->resumeAt) {
            return "Tomando un descanso temporal, regreso pronto";
        }
        
        return "Perfil pausado temporalmente";
    }

    // ========================================
    // MÉTODOS HELPER ADICIONALES
    // ========================================

    private function getUserTotalLogins(): int { return rand(50, 500); }
    private function subcategorizePauseReason(string $reason): string { return 'user_choice'; }
    private function calculateExpectedDurationHours(): ?int { return $this->resumeAt ? (int) $this->timestamp->diffInHours($this->resumeAt) : null; }
    private function calculateUserPauseFrequency(): string { return 'first_time'; }
    private function getProfileViews(int $days): int { return rand(10, 100); }
    private function getLikesReceived(int $days): int { return rand(5, 30); }
    private function getMatches(int $days): int { return rand(2, 15); }
    private function getConversationsStarted(int $days): int { return rand(1, 10); }
    private function getMessagesSent(int $days): int { return rand(5, 50); }
    private function getAppSessions(int $days): int { return rand(3, 20); }
    private function getTotalTimeSpent(int $days): int { return rand(60, 300); }
    private function analyzeEngagementTrend(): string { return 'declining'; }
    private function calculateMatchSuccessRate(): float { return 0.15; }
    private function calculateConversationConversionRate(): float { return 0.35; }
    private function categorizeActivityLevel(): string { return 'moderate'; }
    private function identifyFrustrationIndicators(): array { return ['low_match_rate', 'short_conversations']; }
    private function calculateSatisfactionScore(): float { return 6.5; }
    private function determineIncentiveTier(): string { return 'standard'; }
    private function getPersonalizedCampaignId(): string { return 'reactivation_' . $this->categorizePauseReason($this->pauseReason); }
    private function calculateReEngagementProbability(): float { return 0.65; }
    
    // Métodos para análisis de churn
    private function calculateOverallChurnRisk(): float { return 0.4; }
    private function categorizeChurnRisk(): string { return 'medium'; }
    private function isTimeSensitiveCase(): bool { return false; }
    private function calculateInterventionPriority(): string { return 'medium'; }
    private function hasEngagementDecline(): bool { return true; }
    private function hasLowMatchSuccess(): bool { return true; }
    private function hasNegativeExperiences(): bool { return false; }
    private function detectCompetitorActivity(): bool { return false; }
    private function identifySeasonalPatterns(): array { return []; }
    private function getDemographicRiskFactors(): array { return []; }
    private function calculateInvestmentLevel(): float { return 0.6; }
    private function countSocialConnections(): int { return 5; }
    private function getPremiumFeaturesUsage(): array { return []; }
    private function measurePlatformAdvocacy(): float { return 0.3; }
    private function getCompletionAchievements(): array { return ['profile_complete']; }
    private function getPreviousPauseHistory(): array { return []; }
    private function analyzeReturnPatterns(): array { return []; }
    private function analyzeSeasonalBehavior(): array { return []; }
    private function determineLifecycleStage(): string { return 'active_user'; }
    private function predictReturnProbability(): float { return 0.7; }
    private function predictOptimalReactivationTiming(): int { return 7; } // días
    private function predictMostEffectiveIncentives(): array { return ['free_boost', 'premium_trial']; }
    private function getPersonalizationRecommendations(): array { return []; }
    
    // Métodos para impacto en matches
    private function getMatchesToNotify(): array { return []; }
    private function getOptimalNotificationTiming(): string { return 'immediate'; }
    private function getPersonalizedMessages(): array { return []; }
    private function getFollowUpSchedule(): array { return []; }
    private function setupAutoResponse(): array { return []; }
    private function getConversationPreservationSettings(): array { return []; }
    private function getReactivationConversationStrategy(): array { return []; }
    private function getMatchQueueManagement(): array { return []; }
    private function identifyStrongConnections(): array { return []; }
    private function identifyPriorityRelationships(): array { return []; }
    private function createRelationshipMaintenancePlan(): array { return []; }
    private function assessReturnImpactOnRelationships(): array { return []; }
    
    // Métodos para campaign data
    private function determineUserSegment(): string { return 'standard_user'; }
    private function categorizeEngagementLevel(): string { return 'moderate'; }
    private function determineValueSegment(): string { return 'standard'; }
    private function determineLifecycleSegment(): string { return 'active'; }
    private function selectPrimaryMessageTheme(): string { return 'missed_connections'; }
    private function selectEmotionalTone(): string { return 'encouraging'; }
    private function selectRelevantValuePropositions(): array { return ['new_matches', 'improved_algorithm']; }
    private function selectSocialProofElements(): array { return ['success_stories']; }
    private function determineUrgencyLevel(): string { return 'low'; }
    private function selectPrimaryChannel(): string { return 'email'; }
    private function selectSecondaryChannels(): array { return ['push_notification']; }
    private function optimizeChannelTiming(): array { return []; }
    private function determineFrequencyCap(): int { return 3; }
    private function planCrossChannelCoordination(): array { return []; }
    private function selectIncentiveType(): string { return 'feature_access'; }
    private function calculateIncentiveValue(): float { return 10.0; }
    private function optimizeIncentiveTiming(): int { return 3; } // días
    private function personalizeIncentive(): array { return []; }
    private function defineIncentiveSuccessMetrics(): array { return []; }
    private function calculateInitialContactDelay(): int { return 24; } // horas
    private function createFollowUpSequence(): array { return []; }
    private function incorporateSeasonalFactors(): array { return []; }
    private function determineOptimalSendTimes(): array { return []; }
    private function calculateCampaignDuration(): int { return 30; } // días
    
    // Métodos para contexto de pausa
    private function identifyPauseTrigger(string $reason): string { return 'user_action'; }
    private function getRecentActivity(User $user): array { return []; }
    private function detectFrustrationSignals(User $user): array { return []; }
    private function shouldEnableAutoResponse(string $reason): bool { return true; }
    private function generateAutoResponseMessage(string $reason): string { return 'Gracias por tu mensaje. Regresaré pronto.'; }
    private function getDeviceInfo(): array { return ['type' => 'mobile', 'platform' => 'ios']; }
    
    // Métodos para snapshot de actividad
    private function getUserPhotoCount(int $userId): int { return rand(1, 9); }
    private function getUserPersonalityTestCount(int $userId): int { return rand(0, 5); }
    private function getActiveConversationsCount(int $userId): int { return rand(0, 10); }
    private function getPendingMatchesCount(int $userId): int { return rand(0, 20); }
    private function getScheduledDatesCount(int $userId): int { return rand(0, 3); }
    private function calculateCurrentEngagementLevel(User $user): string { return 'moderate'; }
    
    // Métodos para configuración de reactivación
    private function getReactivationConditions(User $user): array { return ['manual_resume']; }
    private function buildReminderSchedule(User $user, ?Carbon $resumeAt): array { return []; }
    private function getAvailableIncentives(User $user): array { return ['profile_boost', 'premium_trial']; }
    private function getPersonalizationConfig(User $user): array { return []; }

    /**
     * Convert event to array format for storage or serialization
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'profile' => $this->profile->toArray(),
            'timestamp' => $this->timestamp->toISOString(),
            'pause_reason' => $this->pauseReason,
            'resume_at' => $this->resumeAt?->toISOString(),
            'previous_status' => $this->previousStatus,
            'pause_context' => $this->pauseContext,
            'activity_snapshot' => $this->activitySnapshot,
            'reactivation_config' => $this->reactivationConfig
        ];
    }
}