<?php

declare(strict_types=1);

namespace App\Domain\Matching\Events;

use App\Models\Matching\UserMatch;
use App\Domain\Shared\ValueObjects\UserId;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

/**
 * MatchUnmatched Event
 * 
 * Domain event triggered when a match is dissolved between two users in the
 * ForeverUsInLove dating platform. This event handles the unmatch process,
 * cleanup operations, analytics tracking, and user experience optimization
 * following Domain-Driven Design principles.
 * 
 * Key Responsibilities:
 * - Handle match dissolution and cleanup operations
 * - Broadcast unmatch notifications to affected parties
 * - Track unmatch reasons and patterns for analytics
 * - Manage conversation and interaction history cleanup
 * - Trigger re-engagement and recovery workflows
 * - Update user behavior patterns and preferences
 * - Provide feedback to matching algorithms for improvement
 * - Handle privacy and data retention requirements
 * 
 * Unmatch Scenarios:
 * 1. User-initiated unmatch - One user chooses to end the match
 * 2. Mutual unmatch - Both users agree to end the match
 * 3. System-initiated unmatch - Automated unmatch due to policy violations
 * 4. Inactive match cleanup - Unmatch due to prolonged inactivity
 * 5. Account deletion unmatch - Unmatch due to user account deletion
 * 6. Policy violation unmatch - Unmatch due to community guideline violations
 * 7. Expired premium match - Unmatch when premium match period expires
 * 8. Geographic distance unmatch - Automated unmatch for excessive distance
 * 
 * Broadcasting Channels:
 * 1. Private channels for both users (when appropriate)
 * 2. Analytics channel for unmatch pattern tracking
 * 3. Recommendation engine channel for algorithm feedback
 * 4. Admin monitoring channel for policy violation cases
 * 5. Conversation cleanup channel for message history management
 * 
 * Event Data Payload:
 * - Match entity with unmatch details and timestamp
 * - Initiator information and unmatch reasoning
 * - Conversation cleanup requirements and data retention
 * - Analytics data for pattern recognition and improvement
 * - Re-engagement opportunities and suggestions
 * - Privacy and data handling requirements
 * 
 * Privacy Considerations:
 * - Unmatch notifications respect privacy preferences
 * - Conversation history is handled according to retention policies
 * - User data is anonymized for analytics where appropriate
 * - Blocking status is maintained for future interaction prevention
 * - Report mechanisms are preserved for safety and security
 * 
 * @package App\Domain\Matching\Events
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove
 * @license Proprietary
 * @version 1.0.0
 * @since 2024-01-01
 */
class MatchUnmatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var UserMatch The match that was unmatched
     */
    public readonly UserMatch $userMatch;

    /**
     * @var UserId The user who initiated the unmatch
     */
    public readonly UserId $initiatorId;

    /**
     * @var string|null The reason for unmatching
     */
    public readonly ?string $reason;

    /**
     * @var array<string, mixed> Unmatch context and metadata
     */
    public readonly array $unmatchContext;

    /**
     * @var array<string, mixed> Cleanup requirements and data retention
     */
    public readonly array $cleanupData;

    /**
     * @var array<string, mixed> Analytics and tracking data
     */
    public readonly array $analyticsData;

    /**
     * @var array<string, mixed> Re-engagement and recovery data
     */
    public readonly array $recoveryData;

    /**
     * Create a new MatchUnmatched event instance
     * 
     * @param UserMatch $userMatch The match that was unmatched
     * @param UserId $initiatorId The user who initiated the unmatch
     * @param string|null $reason The reason for unmatching
     * @param array<string, mixed> $unmatchContext Additional unmatch context
     * @param array<string, mixed> $cleanupData Cleanup and retention requirements
     * @param array<string, mixed> $analyticsData Analytics tracking information
     * @param array<string, mixed> $recoveryData Re-engagement data
     */
    public function __construct(
        UserMatch $userMatch,
        UserId $initiatorId,
        ?string $reason = null,
        array $unmatchContext = [],
        array $cleanupData = [],
        array $analyticsData = [],
        array $recoveryData = []
    ) {
        $this->userMatch = $userMatch;
        $this->initiatorId = $initiatorId;
        $this->reason = $reason;
        $this->unmatchContext = $unmatchContext;
        $this->cleanupData = $cleanupData;
        $this->analyticsData = $analyticsData;
        $this->recoveryData = $recoveryData;

        // Set socket connection context for real-time broadcasting
        $this->socket = $unmatchContext['socket_id'] ?? null;
    }

    /**
     * Get the channels the event should broadcast on
     * 
     * Defines broadcasting channels based on unmatch type, initiator,
     * and privacy considerations.
     * 
     * @return array<Channel|string> Array of broadcasting channels
     */
    public function broadcastOn(): array
    {
        $channels = [
            // Analytics channel for unmatch tracking
            new PrivateChannel('analytics.unmatches'),
            
            // Recommendation engine feedback channel
            new PrivateChannel('recommendations.unmatch-feedback'),
            
            // Conversation cleanup channel
            new PrivateChannel('messaging.cleanup')
        ];

        // Add user notification channels based on unmatch type and privacy
        if ($this->shouldNotifyUsers()) {
            // Notify both users if it's a mutual or transparent unmatch
            if ($this->isMutualUnmatch() || $this->isTransparentUnmatch()) {
                $channels[] = new PrivateChannel("user.{$this->userMatch->user_id_1}");
                $channels[] = new PrivateChannel("user.{$this->userMatch->user_id_2}");
            } else {
                // Only notify the initiator for privacy-preserving unmatches
                $channels[] = new PrivateChannel("user.{$this->initiatorId->toString()}");
            }
        }

        // Add admin monitoring for policy violations or system-initiated unmatches
        if ($this->requiresAdminAttention()) {
            $channels[] = new PrivateChannel('admin.unmatches.monitoring');
        }

        // Add high-value match tracking for premium or high-compatibility unmatches
        if ($this->isHighValueUnmatch()) {
            $channels[] = new PrivateChannel('analytics.high-value-unmatches');
        }

        return $channels;
    }

    /**
     * Get the broadcast event name
     * 
     * @return string The event name for client-side handling
     */
    public function broadcastAs(): string
    {
        return match($this->getUnmatchType()) {
            'user_initiated' => 'match.unmatched',
            'mutual' => 'match.mutually-unmatched',
            'system_initiated' => 'match.system-unmatched',
            'policy_violation' => 'match.policy-unmatched',
            'inactive_cleanup' => 'match.inactive-unmatched',
            default => 'match.unmatched'
        };
    }

    /**
     * Get the data to broadcast with the event
     * 
     * Provides unmatch data optimized for real-time client consumption
     * while respecting privacy and data retention requirements.
     * 
     * @return array<string, mixed> Broadcast payload data
     */
    public function broadcastWith(): array
    {
        return [
            'match' => [
                'id' => $this->userMatch->match_id,
                'user1_id' => $this->userMatch->user_id_1,
                'user2_id' => $this->userMatch->user_id_2,
                'original_compatibility_score' => $this->userMatch->compatibility_score,
                'match_duration_days' => $this->getMatchDurationDays(),
                'conversation_count' => $this->getConversationCount(),
                'last_activity' => $this->getLastActivityTimestamp(),
                'unmatch_timestamp' => Carbon::now()->toISOString()
            ],
            'unmatch_details' => [
                'initiator_id' => $this->initiatorId->toString(),
                'unmatch_type' => $this->getUnmatchType(),
                'reason_category' => $this->getReasonCategory(),
                'is_mutual' => $this->isMutualUnmatch(),
                'is_reversible' => $this->isReversibleUnmatch(),
                'requires_cleanup' => $this->requiresDataCleanup(),
                'privacy_level' => $this->getPrivacyLevel()
            ],
            'user_experience' => [
                'show_notification' => $this->shouldShowNotification(),
                'notification_message' => $this->getNotificationMessage(),
                'feedback_request' => $this->shouldRequestFeedback(),
                'recovery_suggestions' => $this->getRecoverySuggestions(),
                'next_actions' => $this->getNextActions(),
                'emotional_support' => $this->getEmotionalSupportResources()
            ],
            'data_management' => [
                'conversation_retention_days' => $this->getConversationRetentionDays(),
                'photo_cleanup_required' => $this->requiresPhotoCleanup(),
                'blocking_status' => $this->getBlockingStatus(),
                'report_preservation' => $this->shouldPreserveReports(),
                'analytics_anonymization' => $this->requiresAnalyticsAnonymization()
            ],
            'recovery_options' => [
                'can_rematch' => $this->canRematch(),
                'rematch_cooldown_hours' => $this->getRematchCooldownHours(),
                'alternative_suggestions' => $this->getAlternativeSuggestions(),
                'profile_improvement_tips' => $this->getProfileImprovementTips(),
                'engagement_recommendations' => $this->getEngagementRecommendations()
            ],
            'analytics_summary' => [
                'match_quality_score' => $this->getMatchQualityScore(),
                'engagement_level' => $this->getEngagementLevel(),
                'success_indicators' => $this->getSuccessIndicators(),
                'failure_factors' => $this->getFailureFactors(),
                'learning_opportunities' => $this->getLearningOpportunities()
            ],
            'metadata' => [
                'event_id' => $this->generateEventId(),
                'timestamp' => Carbon::now()->toISOString(),
                'version' => '1.0.0',
                'source' => 'matching_service',
                'correlation_id' => $this->getCorrelationId(),
                'processing_context' => $this->getProcessingContext()
            ]
        ];
    }

    /**
     * Determine if the event should be broadcast
     * 
     * @return bool True if event should be broadcast
     */
    public function broadcastWhen(): bool
    {
        // Always broadcast for analytics and system processing
        // User notifications are controlled separately via shouldNotifyUsers()
        return true;
    }

    /**
     * Get the queue connection for broadcasting
     * 
     * @return string|null Queue connection name
     */
    public function broadcastQueue(): ?string
    {
        if ($this->requiresAdminAttention() || $this->isHighValueUnmatch()) {
            return 'high-priority';
        }

        return 'default';
    }

    /**
     * Get cleanup requirements for external services
     * 
     * Provides detailed cleanup requirements for conversation history,
     * shared media, and related data management.
     * 
     * @return array<string, mixed> Cleanup requirements data
     */
    public function getCleanupRequirements(): array
    {
        return [
            'conversation_cleanup' => [
                'match_id' => $this->userMatch->match_id,
                'cleanup_type' => $this->getConversationCleanupType(),
                'retention_period_days' => $this->getConversationRetentionDays(),
                'anonymize_data' => $this->shouldAnonymizeConversationData(),
                'preserve_reports' => $this->shouldPreserveReports(),
                'cleanup_priority' => $this->getCleanupPriority()
            ],
            'media_cleanup' => [
                'shared_photos_action' => $this->getSharedPhotosAction(),
                'media_retention_days' => $this->getMediaRetentionDays(),
                'backup_requirements' => $this->getBackupRequirements(),
                'privacy_level' => $this->getPrivacyLevel()
            ],
            'notification_cleanup' => [
                'clear_pending_notifications' => $this->shouldClearPendingNotifications(),
                'remove_match_from_feeds' => $this->shouldRemoveFromFeeds(),
                'update_conversation_lists' => $this->shouldUpdateConversationLists(),
                'clear_typing_indicators' => $this->shouldClearTypingIndicators()
            ],
            'data_retention' => [
                'analytics_data_retention' => $this->getAnalyticsDataRetention(),
                'user_preference_updates' => $this->getUserPreferenceUpdates(),
                'recommendation_feedback' => $this->getRecommendationFeedback(),
                'safety_report_preservation' => $this->getSafetyReportPreservation()
            ]
        ];
    }

    /**
     * Get analytics data for tracking and algorithm improvement
     * 
     * Provides comprehensive data for understanding unmatch patterns
     * and improving matching algorithms.
     * 
     * @return array<string, mixed> Analytics tracking data
     */
    public function getAnalyticsTrackingData(): array
    {
        return [
            'unmatch_metrics' => [
                'match_id' => $this->userMatch->match_id,
                'match_duration_hours' => $this->getMatchDurationHours(),
                'conversation_count' => $this->getConversationCount(),
                'message_count' => $this->getMessageCount(),
                'last_activity_hours_ago' => $this->getHoursSinceLastActivity(),
                'initiator_id' => $this->initiatorId->toString(),
                'unmatch_reason' => $this->reason,
                'unmatch_category' => $this->getReasonCategory(),
                'is_mutual' => $this->isMutualUnmatch()
            ],
            'match_quality_analysis' => [
                'original_compatibility_score' => $this->userMatch->compatibility_score,
                'compatibility_accuracy' => $this->getCompatibilityAccuracy(),
                'prediction_success' => $this->getPredictionSuccess(),
                'match_source_algorithm' => $this->getMatchSourceAlgorithm(),
                'recommendation_confidence' => $this->getOriginalRecommendationConfidence(),
                'user_feedback_score' => $this->getUserFeedbackScore()
            ],
            'behavioral_patterns' => [
                'initiator_unmatch_frequency' => $this->getInitiatorUnmatchFrequency(),
                'target_user_unmatch_frequency' => $this->getTargetUserUnmatchFrequency(),
                'conversation_engagement_score' => $this->getConversationEngagementScore(),
                'response_time_patterns' => $this->getResponseTimePatterns(),
                'interaction_quality_score' => $this->getInteractionQualityScore(),
                'ghosting_indicators' => $this->getGhostingIndicators()
            ],
            'user_context' => [
                'initiator_profile_completeness' => $this->getInitiatorProfileCompleteness(),
                'target_user_profile_completeness' => $this->getTargetUserProfileCompleteness(),
                'initiator_premium_status' => $this->getInitiatorPremiumStatus(),
                'target_user_premium_status' => $this->getTargetUserPremiumStatus(),
                'geographic_distance_km' => $this->getGeographicDistance(),
                'age_difference' => $this->getAgeDifference(),
                'shared_interests_count' => $this->getSharedInterestsCount()
            ],
            'timing_analysis' => [
                'match_creation_hour' => $this->getMatchCreationHour(),
                'first_message_delay_hours' => $this->getFirstMessageDelayHours(),
                'conversation_peak_hours' => $this->getConversationPeakHours(),
                'unmatch_timing_hour' => Carbon::now()->hour,
                'weekend_vs_weekday_activity' => $this->getWeekendVsWeekdayActivity(),
                'seasonal_context' => $this->getSeasonalContext()
            ],
            'algorithm_feedback' => [
                'matching_algorithm_version' => $this->getMatchingAlgorithmVersion(),
                'feature_weights_used' => $this->getFeatureWeightsUsed(),
                'personalization_factors' => $this->getPersonalizationFactors(),
                'filter_bypass_indicators' => $this->getFilterBypassIndicators(),
                'success_prediction_accuracy' => $this->getSuccessPredictionAccuracy(),
                'improvement_recommendations' => $this->getAlgorithmImprovementRecommendations()
            ]
        ];
    }

    // ================================================================
    // PRIVATE HELPER METHODS FOR DATA ANALYSIS
    // ================================================================

    /**
     * Determine the type of unmatch
     */
    private function getUnmatchType(): string
    {
        if ($this->isMutualUnmatch()) {
            return 'mutual';
        }

        if ($this->isSystemInitiated()) {
            return 'system_initiated';
        }

        if ($this->isPolicyViolation()) {
            return 'policy_violation';
        }

        if ($this->isInactiveCleanup()) {
            return 'inactive_cleanup';
        }

        return 'user_initiated';
    }

    /**
     * Check if this is a mutual unmatch
     */
    private function isMutualUnmatch(): bool
    {
        return $this->unmatchContext['is_mutual'] ?? false;
    }

    /**
     * Check if unmatch was system-initiated
     */
    private function isSystemInitiated(): bool
    {
        return $this->unmatchContext['system_initiated'] ?? false;
    }

    /**
     * Check if unmatch was due to policy violation
     */
    private function isPolicyViolation(): bool
    {
        return $this->unmatchContext['policy_violation'] ?? false;
    }

    /**
     * Check if unmatch was due to inactivity cleanup
     */
    private function isInactiveCleanup(): bool
    {
        return $this->unmatchContext['inactive_cleanup'] ?? false;
    }

    /**
     * Check if users should be notified
     */
    private function shouldNotifyUsers(): bool
    {
        // Don't notify for system cleanups or policy violations in some cases
        if ($this->isInactiveCleanup() || $this->isPolicyViolation()) {
            return $this->unmatchContext['notify_users'] ?? false;
        }

        return true;
    }

    /**
     * Check if this is a transparent unmatch (both users are informed)
     */
    private function isTransparentUnmatch(): bool
    {
        return $this->isMutualUnmatch() || 
               ($this->unmatchContext['transparent'] ?? false);
    }

    /**
     * Check if unmatch requires admin attention
     */
    private function requiresAdminAttention(): bool
    {
        return $this->isPolicyViolation() || 
               $this->isSystemInitiated() ||
               ($this->unmatchContext['admin_review'] ?? false);
    }

    /**
     * Check if this is a high-value unmatch
     */
    private function isHighValueUnmatch(): bool
    {
        return $this->userMatch->compatibility_score >= 85 ||
               $this->getMatchDurationDays() >= 30 ||
               $this->getConversationCount() >= 50;
    }

    /**
     * Get match duration in days
     */
    private function getMatchDurationDays(): int
    {
        return (int) Carbon::now()->diffInDays($this->userMatch->matched_at);
    }

    /**
     * Get conversation count from match
     */
    private function getConversationCount(): int
    {
        return $this->analyticsData['conversation_count'] ?? 0;
    }

    /**
     * Generate unique event ID for tracking
     */
    private function generateEventId(): string
    {
        return 'match_unmatched_' . $this->userMatch->match_id . '_' . Carbon::now()->timestamp;
    }

    /**
     * Get correlation ID for distributed tracing
     */
    private function getCorrelationId(): string
    {
        return $this->unmatchContext['correlation_id'] ?? 
               'unmatch_' . substr($this->userMatch->match_id, 0, 8);
    }

    /**
     * Get last activity timestamp
     */
    private function getLastActivityTimestamp(): ?string
    {
        return $this->userMatch->last_interaction_at?->toISOString();
    }

    /**
     * Get reason category
     */
    private function getReasonCategory(): string
    {
        return $this->reason ?? 'user_initiated';
    }

    /**
     * Check if unmatch is reversible
     */
    private function isReversibleUnmatch(): bool
    {
        return !$this->isPolicyViolation() && !$this->isSystemInitiated();
    }

    /**
     * Check if data cleanup is required
     */
    private function requiresDataCleanup(): bool
    {
        return $this->getConversationCount() > 0 || $this->getMatchDurationDays() > 0;
    }

    /**
     * Get privacy level
     */
    private function getPrivacyLevel(): string
    {
        return $this->unmatchContext['privacy_level'] ?? 'standard';
    }

    /**
     * Check if should show notification
     */
    private function shouldShowNotification(): bool
    {
        return $this->shouldNotifyUsers();
    }

    /**
     * Get notification message
     */
    private function getNotificationMessage(): string
    {
        if ($this->isMutualUnmatch()) {
            return 'El match ha sido disuelto mutuamente.';
        }
        return 'El match ha sido disuelto.';
    }

    /**
     * Check if should request feedback
     */
    private function shouldRequestFeedback(): bool
    {
        return $this->getMatchDurationDays() >= 7;
    }

    /**
     * Get recovery suggestions
     */
    private function getRecoverySuggestions(): array
    {
        return [
            'Mejora tu perfil',
            'Sé más activo en la app',
            'Considera ajustar tus preferencias'
        ];
    }

    /**
     * Get next actions
     */
    private function getNextActions(): array
    {
        return [
            'explore_new_matches' => 'Explorar nuevos matches',
            'update_profile' => 'Actualizar perfil',
            'adjust_preferences' => 'Ajustar preferencias'
        ];
    }

    /**
     * Get emotional support resources
     */
    private function getEmotionalSupportResources(): array
    {
        return [
            'support_articles' => 'Artículos de apoyo',
            'community_forums' => 'Foros de la comunidad',
            'professional_help' => 'Ayuda profesional'
        ];
    }

    /**
     * Get conversation retention days
     */
    private function getConversationRetentionDays(): int
    {
        return $this->cleanupData['conversation_retention_days'] ?? 30;
    }

    /**
     * Check if photo cleanup is required
     */
    private function requiresPhotoCleanup(): bool
    {
        return $this->cleanupData['photo_cleanup_required'] ?? true;
    }

    /**
     * Get blocking status
     */
    private function getBlockingStatus(): string
    {
        return $this->cleanupData['blocking_status'] ?? 'none';
    }

    /**
     * Check if should preserve reports
     */
    private function shouldPreserveReports(): bool
    {
        return $this->cleanupData['preserve_reports'] ?? true;
    }

    /**
     * Check if analytics anonymization is required
     */
    private function requiresAnalyticsAnonymization(): bool
    {
        return $this->cleanupData['analytics_anonymization'] ?? false;
    }

    /**
     * Check if can rematch
     */
    private function canRematch(): bool
    {
        return $this->recoveryData['can_rematch'] ?? true;
    }

    /**
     * Get rematch cooldown hours
     */
    private function getRematchCooldownHours(): int
    {
        return $this->recoveryData['rematch_cooldown_hours'] ?? 24;
    }

    /**
     * Get alternative suggestions
     */
    private function getAlternativeSuggestions(): array
    {
        return $this->recoveryData['alternative_suggestions'] ?? [];
    }

    /**
     * Get profile improvement tips
     */
    private function getProfileImprovementTips(): array
    {
        return [
            'Agrega más fotos',
            'Completa tu perfil',
            'Sé más específico en tus intereses'
        ];
    }

    /**
     * Get engagement recommendations
     */
    private function getEngagementRecommendations(): array
    {
        return [
            'Sé más activo en la app',
            'Responde mensajes más rápido',
            'Inicia más conversaciones'
        ];
    }

    /**
     * Get match quality score
     */
    private function getMatchQualityScore(): float
    {
        return $this->userMatch->compatibility_score;
    }

    /**
     * Get engagement level
     */
    private function getEngagementLevel(): string
    {
        $count = $this->getConversationCount();
        if ($count >= 50) return 'high';
        if ($count >= 10) return 'medium';
        return 'low';
    }

    /**
     * Get success indicators
     */
    private function getSuccessIndicators(): array
    {
        return [
            'high_compatibility' => $this->userMatch->compatibility_score >= 80,
            'active_conversation' => $this->getConversationCount() > 0,
            'quick_response' => true
        ];
    }

    /**
     * Get failure factors
     */
    private function getFailureFactors(): array
    {
        return [
            'low_engagement' => $this->getConversationCount() === 0,
            'slow_response' => true,
            'incompatible_preferences' => true
        ];
    }

    /**
     * Get learning opportunities
     */
    private function getLearningOpportunities(): array
    {
        return [
            'improve_matching_algorithm',
            'enhance_user_profiles',
            'optimize_conversation_starters'
        ];
    }

    /**
     * Get processing context
     */
    private function getProcessingContext(): array
    {
        return [
            'timestamp' => Carbon::now()->toISOString(),
            'version' => '1.0.0',
            'environment' => 'production'
        ];
    }

    // Additional methods for analytics tracking
    private function getMatchDurationHours(): int
    {
        return $this->getMatchDurationDays() * 24;
    }

    private function getMessageCount(): int
    {
        return $this->userMatch->message_count ?? 0;
    }

    private function getHoursSinceLastActivity(): int
    {
        if (!$this->userMatch->last_interaction_at) {
            return $this->getMatchDurationHours();
        }
        return (int) Carbon::now()->diffInHours($this->userMatch->last_interaction_at);
    }

    private function getCompatibilityAccuracy(): float
    {
        return $this->analyticsData['compatibility_accuracy'] ?? 0.8;
    }

    private function getPredictionSuccess(): bool
    {
        return $this->analyticsData['prediction_success'] ?? true;
    }

    private function getMatchSourceAlgorithm(): string
    {
        return $this->userMatch->match_source ?? 'standard';
    }

    private function getOriginalRecommendationConfidence(): float
    {
        return $this->analyticsData['recommendation_confidence'] ?? 0.75;
    }

    private function getUserFeedbackScore(): float
    {
        return $this->analyticsData['user_feedback_score'] ?? 0.0;
    }

    private function getInitiatorUnmatchFrequency(): int
    {
        return $this->analyticsData['initiator_unmatch_frequency'] ?? 0;
    }

    private function getTargetUserUnmatchFrequency(): int
    {
        return $this->analyticsData['target_user_unmatch_frequency'] ?? 0;
    }

    private function getConversationEngagementScore(): float
    {
        return $this->analyticsData['conversation_engagement_score'] ?? 0.5;
    }

    private function getResponseTimePatterns(): array
    {
        return $this->analyticsData['response_time_patterns'] ?? [];
    }

    private function getInteractionQualityScore(): float
    {
        return $this->analyticsData['interaction_quality_score'] ?? 0.6;
    }

    private function getGhostingIndicators(): array
    {
        return $this->analyticsData['ghosting_indicators'] ?? [];
    }

    private function getInitiatorProfileCompleteness(): float
    {
        return $this->analyticsData['initiator_profile_completeness'] ?? 0.8;
    }

    private function getTargetUserProfileCompleteness(): float
    {
        return $this->analyticsData['target_user_profile_completeness'] ?? 0.8;
    }

    private function getInitiatorPremiumStatus(): bool
    {
        return $this->analyticsData['initiator_premium_status'] ?? false;
    }

    private function getTargetUserPremiumStatus(): bool
    {
        return $this->analyticsData['target_user_premium_status'] ?? false;
    }

    private function getGeographicDistance(): float
    {
        return $this->analyticsData['geographic_distance'] ?? 0.0;
    }

    private function getAgeDifference(): int
    {
        return $this->analyticsData['age_difference'] ?? 0;
    }

    private function getSharedInterestsCount(): int
    {
        return $this->analyticsData['shared_interests_count'] ?? 0;
    }

    private function getMatchCreationHour(): int
    {
        return $this->userMatch->matched_at->hour;
    }

    private function getFirstMessageDelayHours(): int
    {
        return $this->analyticsData['first_message_delay_hours'] ?? 0;
    }

    private function getConversationPeakHours(): array
    {
        return $this->analyticsData['conversation_peak_hours'] ?? [];
    }

    private function getWeekendVsWeekdayActivity(): string
    {
        return $this->analyticsData['weekend_vs_weekday_activity'] ?? 'weekday';
    }

    private function getSeasonalContext(): string
    {
        return $this->analyticsData['seasonal_context'] ?? 'normal';
    }

    private function getMatchingAlgorithmVersion(): string
    {
        return $this->analyticsData['matching_algorithm_version'] ?? '1.0';
    }

    private function getFeatureWeightsUsed(): array
    {
        return $this->analyticsData['feature_weights_used'] ?? [];
    }

    private function getPersonalizationFactors(): array
    {
        return $this->analyticsData['personalization_factors'] ?? [];
    }

    private function getFilterBypassIndicators(): array
    {
        return $this->analyticsData['filter_bypass_indicators'] ?? [];
    }

    private function getSuccessPredictionAccuracy(): float
    {
        return $this->analyticsData['success_prediction_accuracy'] ?? 0.7;
    }

    private function getAlgorithmImprovementRecommendations(): array
    {
        return $this->analyticsData['algorithm_improvement_recommendations'] ?? [];
    }

    // Additional cleanup methods
    private function getConversationCleanupType(): string
    {
        return $this->cleanupData['conversation_cleanup_type'] ?? 'standard';
    }

    private function shouldAnonymizeConversationData(): bool
    {
        return $this->cleanupData['anonymize_conversation_data'] ?? false;
    }

    private function getCleanupPriority(): string
    {
        return $this->cleanupData['cleanup_priority'] ?? 'normal';
    }

    private function getSharedPhotosAction(): string
    {
        return $this->cleanupData['shared_photos_action'] ?? 'delete';
    }

    private function getMediaRetentionDays(): int
    {
        return $this->cleanupData['media_retention_days'] ?? 7;
    }

    private function getBackupRequirements(): array
    {
        return $this->cleanupData['backup_requirements'] ?? [];
    }

    private function shouldClearPendingNotifications(): bool
    {
        return $this->cleanupData['clear_pending_notifications'] ?? true;
    }

    private function shouldRemoveFromFeeds(): bool
    {
        return $this->cleanupData['remove_from_feeds'] ?? true;
    }

    private function shouldUpdateConversationLists(): bool
    {
        return $this->cleanupData['update_conversation_lists'] ?? true;
    }

    private function shouldClearTypingIndicators(): bool
    {
        return $this->cleanupData['clear_typing_indicators'] ?? true;
    }

    private function getAnalyticsDataRetention(): int
    {
        return $this->cleanupData['analytics_data_retention'] ?? 90;
    }

    private function getUserPreferenceUpdates(): array
    {
        return $this->cleanupData['user_preference_updates'] ?? [];
    }

    private function getRecommendationFeedback(): array
    {
        return $this->cleanupData['recommendation_feedback'] ?? [];
    }

    private function getSafetyReportPreservation(): bool
    {
        return $this->cleanupData['safety_report_preservation'] ?? true;
    }
}