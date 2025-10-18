<?php

declare(strict_types=1);

namespace App\Domain\Matching\Events;

use App\Domain\Matching\Entities\Like;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Matching\ValueObjects\LikeType;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

/**
 * UserLiked Event
 * 
 * Domain event triggered when a user likes another user's profile in the
 * ForeverUsInLove dating platform. This event handles real-time notifications,
 * mutual match detection, and user engagement tracking following Domain-Driven
 * Design principles and event-driven architecture patterns.
 * 
 * Key Responsibilities:
 * - Broadcast real-time like notifications to the liked user
 * - Handle immediate mutual match detection and celebration
 * - Trigger engagement boost notifications and profile visibility
 * - Update user interaction statistics and analytics
 * - Manage like notification preferences and delivery timing
 * - Support different like types (standard, super, premium) with appropriate handling
 * - Integrate with recommendation algorithms for preference learning
 * - Handle notification batching and rate limiting for user experience
 * 
 * Broadcasting Channels:
 * 1. Private channel to the liked user for immediate notification
 * 2. Analytics channel for real-time engagement tracking
 * 3. Recommendation engine channel for algorithm feedback
 * 4. Admin monitoring channel for like activity oversight
 * 5. Premium features channel for enhanced like handling
 * 
 * Event Data Payload:
 * - Like entity with complete interaction details
 * - Liker profile information (filtered for privacy)
 * - Like type and source context for personalized responses
 * - Mutual match status and celebration triggers
 * - Notification preferences and delivery options
 * - Analytics data for engagement tracking
 * 
 * Notification Types:
 * 1. Immediate - For super likes and premium interactions
 * 2. Batched - For standard likes (grouped for better UX)
 * 3. Daily Digest - For users preferring consolidated notifications
 * 4. Push Notifications - Based on user mobile preferences
 * 5. Email Notifications - For significant interactions only
 * 
 * Integration Points:
 * - Real-time notification delivery system
 * - Push notification services (FCM, APNS)
 * - Email notification service
 * - Analytics and metrics collection
 * - Recommendation algorithm feedback loops
 * - User engagement tracking
 * - Premium feature activation
 * - Conversation starter suggestions
 * 
 * @package ForeverUsInLove\Domain\Matching\Events
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove
 * @license Proprietary
 * @version 1.0.0
 * @since 2025-10-10
 */
class UserLiked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Like The like entity that was created
     */
    public readonly Like $like;

    /**
     * @var bool Whether this like created a mutual match
     */
    public readonly bool $isMutualMatch;

    /**
     * @var array<string, mixed> Notification configuration and preferences
     */
    public readonly array $notificationData;

    /**
     * @var array<string, mixed> Analytics and tracking data
     */
    public readonly array $analyticsData;

    /**
     * @var array<string, mixed> Engagement boost and visibility data
     */
    public readonly array $engagementData;

    /**
     * @var array<string, mixed> Integration data for external services
     */
    public readonly array $integrationData;

    /**
     * Create a new UserLiked event instance
     * 
     * @param Like $like The like that was created
     * @param bool $isMutualMatch Whether this created a mutual match
     * @param array<string, mixed> $notificationData Notification preferences and data
     * @param array<string, mixed> $analyticsData Analytics tracking information
     * @param array<string, mixed> $engagementData Engagement boost information
     * @param array<string, mixed> $integrationData External service integration data
     */
    public function __construct(
        Like $like,
        bool $isMutualMatch = false,
        array $notificationData = [],
        array $analyticsData = [],
        array $engagementData = [],
        array $integrationData = []
    ) {
        $this->like = $like;
        $this->isMutualMatch = $isMutualMatch;
        $this->notificationData = $notificationData;
        $this->analyticsData = $analyticsData;
        $this->engagementData = $engagementData;
        $this->integrationData = $integrationData;

        // Set socket connection context for real-time broadcasting
        $this->socket = $integrationData['socket_id'] ?? null;
    }

    /**
     * Get the channels the event should broadcast on
     * 
     * Defines broadcasting channels based on like type, mutual match status,
     * and user notification preferences.
     * 
     * @return array<Channel|string> Array of broadcasting channels
     */
    public function broadcastOn(): array
    {
        $channels = [
            // Private channel to the liked user for notification
            new PrivateChannel("user.{$this->like->getLikedId()->toString()}"),
            
            // Analytics channel for engagement tracking
            new PrivateChannel('analytics.likes'),
            
            // Recommendation engine feedback channel
            new PrivateChannel('recommendations.like-feedback')
        ];

        // Add special channels for premium likes
        if ($this->like->getType() === LikeType::SUPER_LIKE) {
            $channels[] = new PrivateChannel('premium.super-likes');
        }

        // Add mutual match celebration channels
        if ($this->isMutualMatch) {
            $channels[] = new PrivateChannel('celebrations.mutual-matches');
            $channels[] = new PrivateChannel("user.{$this->like->getLikerId()->toString()}");
        }

        // Add admin monitoring channel for high-value interactions
        if ($this->isHighValueInteraction()) {
            $channels[] = new PrivateChannel('admin.high-value-likes');
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
        if ($this->isMutualMatch) {
            return 'mutual-match.created';
        }

        return match($this->like->getType()) {
            LikeType::SUPER_LIKE => 'super-like.received',
            LikeType::PREMIUM_LIKE => 'premium-like.received',
            default => 'like.received'
        };
    }

    /**
     * Get the data to broadcast with the event
     * 
     * Provides optimized like data for real-time client consumption while
     * respecting privacy settings and user preferences.
     * 
     * @return array<string, mixed> Broadcast payload data
     */
    public function broadcastWith(): array
    {
        return [
            'like' => [
                'id' => $this->like->getId()->toString(),
                'liker_id' => $this->like->getLikerId()->toString(),
                'liked_id' => $this->like->getLikedId()->toString(),
                'type' => $this->like->getType()->getValue(),
                'source' => $this->like->getSource()->getValue(),
                'created_at' => $this->like->getCreatedAt()->toISOString(),
                'has_message' => !empty($this->like->getMessage()),
                'message_preview' => $this->getMessagePreview(),
                'is_mutual_match' => $this->isMutualMatch
            ],
            'liker_profile' => [
                'id' => $this->like->getLikerId()->toString(),
                'display_name' => $this->getLikerDisplayName(),
                'age' => $this->getLikerAge(),
                'location_display' => $this->getLikerLocationDisplay(),
                'primary_photo_url' => $this->getLikerPrimaryPhotoUrl(),
                'verification_status' => $this->getLikerVerificationStatus(),
                'premium_status' => $this->getLikerPremiumStatus(),
                'compatibility_preview' => $this->getCompatibilityPreview(),
                'shared_interests_count' => $this->getSharedInterestsCount()
            ],
            'notification' => [
                'title' => $this->getNotificationTitle(),
                'message' => $this->getNotificationMessage(),
                'action_url' => $this->getActionUrl(),
                'priority' => $this->getNotificationPriority(),
                'delivery_method' => $this->getPreferredDeliveryMethod(),
                'should_show_immediately' => $this->shouldShowImmediately(),
                'celebration_level' => $this->getCelebrationLevel()
            ],
            'engagement' => [
                'profile_boost' => $this->getProfileBoostInfo(),
                'visibility_increase' => $this->getVisibilityIncrease(),
                'like_streak' => $this->getLikeStreak(),
                'recent_likes_count' => $this->getRecentLikesCount(),
                'engagement_score' => $this->getEngagementScore()
            ],
            'interaction_options' => [
                'can_like_back' => $this->canLikeBack(),
                'can_super_like_back' => $this->canSuperLikeBack(),
                'can_send_message' => $this->canSendMessage(),
                'icebreaker_suggestions' => $this->getIcebreakerSuggestions(),
                'premium_features_available' => $this->getPremiumFeaturesAvailable()
            ],
            'analytics' => [
                'like_rank' => $this->getLikeRank(),
                'compatibility_score' => $this->getCompatibilityScore(),
                'interaction_quality' => $this->getInteractionQuality(),
                'recommendation_source' => $this->getRecommendationSource(),
                'timing_score' => $this->getTimingScore()
            ],
            'metadata' => [
                'event_id' => $this->generateEventId(),
                'timestamp' => Carbon::now()->toISOString(),
                'version' => '1.0.0',
                'source' => 'like_service',
                'correlation_id' => $this->getCorrelationId(),
                'user_timezone' => $this->getUserTimezone()
            ]
        ];
    }

    /**
     * Determine if the event should be broadcast immediately
     * 
     * @return bool True to broadcast immediately
     */
    public function broadcastWhen(): bool
    {
        // Always broadcast super likes and mutual matches immediately
        if ($this->like->getType() === LikeType::SUPER_LIKE || $this->isMutualMatch) {
            return true;
        }

        // Check user notification preferences
        $userPreferences = $this->notificationData['user_preferences'] ?? [];
        return $userPreferences['immediate_notifications'] ?? true;
    }

    /**
     * Get the queue connection for broadcasting
     * 
     * @return string|null Queue connection name
     */
    public function broadcastQueue(): ?string
    {
        if ($this->isMutualMatch || $this->like->getType() === LikeType::SUPER_LIKE) {
            return 'high-priority';
        }

        return 'default';
    }

    /**
     * Get notification data for external delivery services
     * 
     * Provides structured data for push notifications, emails, and SMS delivery.
     * 
     * @return array<string, mixed> External notification delivery data
     */
    public function getExternalNotificationData(): array
    {
        $likedUserId = $this->like->getLikedId();
        
        return [
            'recipient' => [
                'user_id' => $likedUserId->toString(),
                'notification_preferences' => $this->getUserNotificationPreferences($likedUserId),
                'timezone' => $this->getUserTimezone(),
                'language' => $this->getUserLanguage(),
                'delivery_methods' => $this->getPreferredDeliveryMethods()
            ],
            'notification' => [
                'type' => $this->getNotificationType(),
                'priority' => $this->getNotificationPriority(),
                'title' => $this->getLocalizedNotificationTitle(),
                'body' => $this->getLocalizedNotificationBody(),
                'action_url' => $this->getActionUrl(),
                'deep_link' => $this->getDeepLink(),
                'custom_data' => $this->getCustomNotificationData()
            ],
            'content' => [
                'liker_name' => $this->getLikerDisplayName(),
                'liker_photo' => $this->getLikerPrimaryPhotoUrl(),
                'compatibility_score' => $this->getCompatibilityScore(),
                'shared_interests' => $this->getSharedInterests(),
                'like_message' => $this->like->getMessage(),
                'celebration_assets' => $this->getCelebrationAssets()
            ],
            'timing' => [
                'send_immediately' => $this->shouldSendImmediately(),
                'batch_with_others' => $this->shouldBatchWithOthers(),
                'optimal_send_time' => $this->getOptimalSendTime(),
                'respect_quiet_hours' => $this->shouldRespectQuietHours()
            ]
        ];
    }

    /**
     * Get analytics data for tracking and machine learning
     * 
     * Provides comprehensive data for analytics systems and recommendation optimization.
     * 
     * @return array<string, mixed> Analytics tracking data
     */
    public function getAnalyticsTrackingData(): array
    {
        return [
            'interaction_metrics' => [
                'like_id' => $this->like->getId()->toString(),
                'liker_id' => $this->like->getLikerId()->toString(),
                'liked_id' => $this->like->getLikedId()->toString(),
                'like_type' => $this->like->getType()->getValue(),
                'like_source' => $this->like->getSource()->getValue(),
                'is_mutual_match' => $this->isMutualMatch,
                'has_message' => !empty($this->like->getMessage()),
                'message_length' => strlen($this->like->getMessage() ?? ''),
                'interaction_timestamp' => $this->like->getCreatedAt()->toISOString()
            ],
            'user_context' => [
                'liker_profile_completeness' => $this->getLikerProfileCompleteness(),
                'liked_user_profile_completeness' => $this->getLikedUserProfileCompleteness(),
                'liker_activity_level' => $this->getLikerActivityLevel(),
                'liked_user_activity_level' => $this->getLikedUserActivityLevel(),
                'liker_premium_status' => $this->getLikerPremiumStatus(),
                'liked_user_premium_status' => $this->getLikedUserPremiumStatus(),
                'geographic_distance_km' => $this->getGeographicDistance(),
                'age_difference' => $this->getAgeDifference()
            ],
            'compatibility_metrics' => [
                'overall_compatibility_score' => $this->getCompatibilityScore(),
                'shared_interests_count' => $this->getSharedInterestsCount(),
                'lifestyle_compatibility' => $this->getLifestyleCompatibility(),
                'personality_match_score' => $this->getPersonalityMatchScore(),
                'demographic_alignment' => $this->getDemographicAlignment(),
                'behavioral_similarity' => $this->getBehavioralSimilarity()
            ],
            'engagement_context' => [
                'time_since_profile_view' => $this->getTimeSinceProfileView(),
                'discovery_session_position' => $this->getDiscoverySessionPosition(),
                'previous_interactions_count' => $this->getPreviousInteractionsCount(),
                'liker_daily_likes_count' => $this->getLikerDailyLikesCount(),
                'liked_user_daily_likes_received' => $this->getLikedUserDailyLikesReceived(),
                'interaction_speed_score' => $this->getInteractionSpeedScore()
            ],
            'prediction_data' => [
                'mutual_like_probability' => $this->getMutualLikeProbability(),
                'conversation_start_probability' => $this->getConversationStartProbability(),
                'message_response_probability' => $this->getMessageResponseProbability(),
                'meeting_likelihood' => $this->getMeetingLikelihood(),
                'relationship_potential_score' => $this->getRelationshipPotentialScore()
            ],
            'recommendation_feedback' => [
                'recommendation_algorithm' => $this->getRecommendationAlgorithm(),
                'recommendation_confidence' => $this->getRecommendationConfidence(),
                'discovery_method' => $this->getDiscoveryMethod(),
                'filter_bypass_count' => $this->getFilterBypassCount(),
                'personalization_factors' => $this->getPersonalizationFactors()
            ]
        ];
    }

    // ================================================================
    // PRIVATE HELPER METHODS FOR DATA PREPARATION
    // ================================================================

    /**
     * Check if this is a high-value interaction
     */
    private function isHighValueInteraction(): bool
    {
        return $this->like->getType() === LikeType::SUPER_LIKE ||
               $this->isMutualMatch ||
               $this->getCompatibilityScore() >= 0.85 ||
               !empty($this->like->getMessage());
    }

    /**
     * Get notification type based on like type and context
     */
    private function getNotificationType(): string
    {
        if ($this->isMutualMatch) {
            return 'mutual_match';
        }

        return match($this->like->getType()) {
            LikeType::SUPER_LIKE => 'super_like',
            LikeType::PREMIUM_LIKE => 'premium_like',
            default => 'standard_like'
        };
    }

    /**
     * Get notification priority level
     */
    private function getNotificationPriority(): string
    {
        if ($this->isMutualMatch) {
            return 'urgent';
        }

        return match($this->like->getType()) {
            LikeType::SUPER_LIKE => 'high',
            LikeType::PREMIUM_LIKE => 'medium',
            default => 'normal'
        };
    }

    /**
     * Get celebration level based on interaction quality
     */
    private function getCelebrationLevel(): string
    {
        if ($this->isMutualMatch) {
            $compatibility = $this->getCompatibilityScore();
            if ($compatibility >= 0.9) return 'spectacular';
            if ($compatibility >= 0.8) return 'high';
            return 'standard';
        }

        return match($this->like->getType()) {
            LikeType::SUPER_LIKE => 'elevated',
            default => 'minimal'
        };
    }

    /**
     * Get localized notification title
     */
    private function getLocalizedNotificationTitle(): string
    {
        $likerName = $this->getLikerDisplayName();
        
        if ($this->isMutualMatch) {
            return "🎉 It's a match with {$likerName}!";
        }

        return match($this->like->getType()) {
            LikeType::SUPER_LIKE => "⭐ {$likerName} super liked you!",
            default => "💕 {$likerName} likes you!"
        };
    }

    /**
     * Get localized notification body
     */
    private function getLocalizedNotificationBody(): string
    {
        if ($this->isMutualMatch) {
            return "You both liked each other! Start a conversation now.";
        }

        if ($this->like->getMessage()) {
            return "They sent you a message: \"" . $this->getMessagePreview() . "\"";
        }

        $compatibility = $this->getCompatibilityScore();
        if ($compatibility >= 0.8) {
            return "You have great compatibility! Check out their profile.";
        }

        return "See what caught their interest in your profile.";
    }

    /**
     * Get message preview (truncated for notifications)
     */
    private function getMessagePreview(): ?string
    {
        $message = $this->like->getMessage();
        if (!$message) {
            return null;
        }

        return strlen($message) > 50 ? substr($message, 0, 47) . '...' : $message;
    }

    /**
     * Generate unique event ID for tracking
     */
    private function generateEventId(): string
    {
        return 'user_liked_' . $this->like->getId()->toString() . '_' . Carbon::now()->timestamp;
    }

    /**
     * Get correlation ID for distributed tracing
     */
    private function getCorrelationId(): string
    {
        return $this->integrationData['correlation_id'] ?? 
               'like_' . substr($this->like->getId()->toString(), 0, 8);
    }

    /**
     * Get liker display name
     */
    private function getLikerDisplayName(): string
    {
        return $this->analyticsData['liker_display_name'] ?? 'Someone';
    }

    /**
     * Get liker age
     */
    private function getLikerAge(): ?int
    {
        return $this->analyticsData['liker_age'] ?? null;
    }

    /**
     * Get liker location display
     */
    private function getLikerLocationDisplay(): ?string
    {
        return $this->analyticsData['liker_location'] ?? null;
    }

    /**
     * Get liker primary photo URL
     */
    private function getLikerPrimaryPhotoUrl(): ?string
    {
        return $this->analyticsData['liker_photo_url'] ?? null;
    }

    /**
     * Get liker verification status
     */
    private function getLikerVerificationStatus(): bool
    {
        return $this->analyticsData['liker_verified'] ?? false;
    }

    /**
     * Get liker premium status
     */
    private function getLikerPremiumStatus(): bool
    {
        return $this->analyticsData['liker_premium'] ?? false;
    }

    /**
     * Get compatibility preview
     */
    private function getCompatibilityPreview(): ?string
    {
        return $this->analyticsData['compatibility_preview'] ?? null;
    }

    /**
     * Get shared interests count
     */
    private function getSharedInterestsCount(): int
    {
        return $this->analyticsData['shared_interests_count'] ?? 0;
    }

    /**
     * Get notification title
     */
    private function getNotificationTitle(): string
    {
        return $this->notificationData['title'] ?? 'New Like!';
    }

    /**
     * Get notification message
     */
    private function getNotificationMessage(): string
    {
        return $this->notificationData['message'] ?? 'Someone likes you!';
    }

    /**
     * Get action URL
     */
    private function getActionUrl(): string
    {
        return $this->notificationData['action_url'] ?? '/likes';
    }

    /**
     * Get preferred delivery method
     */
    private function getPreferredDeliveryMethod(): string
    {
        return $this->notificationData['delivery_method'] ?? 'push';
    }

    /**
     * Check if should show immediately
     */
    private function shouldShowImmediately(): bool
    {
        return $this->notificationData['immediate'] ?? false;
    }

    /**
     * Get profile boost info
     */
    private function getProfileBoostInfo(): array
    {
        return $this->engagementData['profile_boost'] ?? [];
    }

    /**
     * Get visibility increase
     */
    private function getVisibilityIncrease(): float
    {
        return $this->engagementData['visibility_increase'] ?? 0.0;
    }

    /**
     * Get like streak
     */
    private function getLikeStreak(): int
    {
        return $this->engagementData['like_streak'] ?? 0;
    }

    /**
     * Get recent likes count
     */
    private function getRecentLikesCount(): int
    {
        return $this->engagementData['recent_likes_count'] ?? 0;
    }

    /**
     * Get engagement score
     */
    private function getEngagementScore(): float
    {
        return $this->engagementData['engagement_score'] ?? 0.0;
    }

    /**
     * Check if can like back
     */
    private function canLikeBack(): bool
    {
        return $this->integrationData['can_like_back'] ?? true;
    }

    /**
     * Check if can super like back
     */
    private function canSuperLikeBack(): bool
    {
        return $this->integrationData['can_super_like_back'] ?? false;
    }

    /**
     * Check if can send message
     */
    private function canSendMessage(): bool
    {
        return $this->integrationData['can_send_message'] ?? false;
    }

    /**
     * Get icebreaker suggestions
     */
    private function getIcebreakerSuggestions(): array
    {
        return $this->integrationData['icebreaker_suggestions'] ?? [];
    }

    /**
     * Get premium features available
     */
    private function getPremiumFeaturesAvailable(): array
    {
        return $this->integrationData['premium_features'] ?? [];
    }

    /**
     * Get like rank
     */
    private function getLikeRank(): int
    {
        return $this->analyticsData['like_rank'] ?? 0;
    }

    /**
     * Get compatibility score
     */
    private function getCompatibilityScore(): float
    {
        return $this->analyticsData['compatibility_score'] ?? 0.0;
    }

    /**
     * Get interaction quality
     */
    private function getInteractionQuality(): float
    {
        return $this->analyticsData['interaction_quality'] ?? 0.0;
    }

    /**
     * Get recommendation source
     */
    private function getRecommendationSource(): ?string
    {
        return $this->analyticsData['recommendation_source'] ?? null;
    }

    /**
     * Get timing score
     */
    private function getTimingScore(): float
    {
        return $this->analyticsData['timing_score'] ?? 0.0;
    }

    /**
     * Get user timezone
     */
    private function getUserTimezone(): string
    {
        return $this->integrationData['user_timezone'] ?? 'UTC';
    }

    /**
     * Get user notification preferences
     */
    private function getUserNotificationPreferences(UserId $userId): array
    {
        return $this->integrationData['user_preferences'] ?? [];
    }

    /**
     * Get user language
     */
    private function getUserLanguage(): string
    {
        return $this->integrationData['user_language'] ?? 'en';
    }

    /**
     * Get preferred delivery methods
     */
    private function getPreferredDeliveryMethods(): array
    {
        return $this->integrationData['delivery_methods'] ?? ['push'];
    }

    /**
     * Get deep link
     */
    private function getDeepLink(): string
    {
        return $this->integrationData['deep_link'] ?? '/app/likes';
    }

    /**
     * Get custom notification data
     */
    private function getCustomNotificationData(): array
    {
        return $this->integrationData['custom_data'] ?? [];
    }

    /**
     * Get shared interests
     */
    private function getSharedInterests(): array
    {
        return $this->analyticsData['shared_interests'] ?? [];
    }

    /**
     * Get celebration assets
     */
    private function getCelebrationAssets(): array
    {
        return $this->integrationData['celebration_assets'] ?? [];
    }

    /**
     * Check if should send immediately
     */
    private function shouldSendImmediately(): bool
    {
        return $this->notificationData['send_immediately'] ?? false;
    }

    /**
     * Check if should batch with others
     */
    private function shouldBatchWithOthers(): bool
    {
        return $this->notificationData['batch_with_others'] ?? true;
    }

    /**
     * Get optimal send time
     */
    private function getOptimalSendTime(): ?string
    {
        return $this->notificationData['optimal_send_time'] ?? null;
    }

    /**
     * Check if should respect quiet hours
     */
    private function shouldRespectQuietHours(): bool
    {
        return $this->notificationData['respect_quiet_hours'] ?? true;
    }

    /**
     * Get liker profile completeness
     */
    private function getLikerProfileCompleteness(): float
    {
        return $this->analyticsData['liker_profile_completeness'] ?? 0.0;
    }

    /**
     * Get liked user profile completeness
     */
    private function getLikedUserProfileCompleteness(): float
    {
        return $this->analyticsData['liked_user_profile_completeness'] ?? 0.0;
    }

    /**
     * Get liker activity level
     */
    private function getLikerActivityLevel(): float
    {
        return $this->analyticsData['liker_activity_level'] ?? 0.0;
    }

    /**
     * Get liked user activity level
     */
    private function getLikedUserActivityLevel(): float
    {
        return $this->analyticsData['liked_user_activity_level'] ?? 0.0;
    }

    /**
     * Get liked user premium status
     */
    private function getLikedUserPremiumStatus(): bool
    {
        return $this->analyticsData['liked_user_premium'] ?? false;
    }

    /**
     * Get geographic distance
     */
    private function getGeographicDistance(): ?float
    {
        return $this->analyticsData['geographic_distance'] ?? null;
    }

    /**
     * Get age difference
     */
    private function getAgeDifference(): ?int
    {
        return $this->analyticsData['age_difference'] ?? null;
    }

    /**
     * Get lifestyle compatibility
     */
    private function getLifestyleCompatibility(): float
    {
        return $this->analyticsData['lifestyle_compatibility'] ?? 0.0;
    }

    /**
     * Get personality match score
     */
    private function getPersonalityMatchScore(): float
    {
        return $this->analyticsData['personality_match_score'] ?? 0.0;
    }

    /**
     * Get demographic alignment
     */
    private function getDemographicAlignment(): float
    {
        return $this->analyticsData['demographic_alignment'] ?? 0.0;
    }

    /**
     * Get behavioral similarity
     */
    private function getBehavioralSimilarity(): float
    {
        return $this->analyticsData['behavioral_similarity'] ?? 0.0;
    }

    /**
     * Get time since profile view
     */
    private function getTimeSinceProfileView(): ?int
    {
        return $this->analyticsData['time_since_profile_view'] ?? null;
    }

    /**
     * Get discovery session position
     */
    private function getDiscoverySessionPosition(): ?int
    {
        return $this->analyticsData['discovery_session_position'] ?? null;
    }

    /**
     * Get previous interactions count
     */
    private function getPreviousInteractionsCount(): int
    {
        return $this->analyticsData['previous_interactions_count'] ?? 0;
    }

    /**
     * Get liker daily likes count
     */
    private function getLikerDailyLikesCount(): int
    {
        return $this->analyticsData['liker_daily_likes_count'] ?? 0;
    }

    /**
     * Get liked user daily likes received
     */
    private function getLikedUserDailyLikesReceived(): int
    {
        return $this->analyticsData['liked_user_daily_likes_received'] ?? 0;
    }

    /**
     * Get interaction speed score
     */
    private function getInteractionSpeedScore(): float
    {
        return $this->analyticsData['interaction_speed_score'] ?? 0.0;
    }

    /**
     * Get mutual like probability
     */
    private function getMutualLikeProbability(): float
    {
        return $this->analyticsData['mutual_like_probability'] ?? 0.0;
    }

    /**
     * Get conversation start probability
     */
    private function getConversationStartProbability(): float
    {
        return $this->analyticsData['conversation_start_probability'] ?? 0.0;
    }

    /**
     * Get message response probability
     */
    private function getMessageResponseProbability(): float
    {
        return $this->analyticsData['message_response_probability'] ?? 0.0;
    }

    /**
     * Get meeting likelihood
     */
    private function getMeetingLikelihood(): float
    {
        return $this->analyticsData['meeting_likelihood'] ?? 0.0;
    }

    /**
     * Get relationship potential score
     */
    private function getRelationshipPotentialScore(): float
    {
        return $this->analyticsData['relationship_potential_score'] ?? 0.0;
    }

    /**
     * Get recommendation algorithm
     */
    private function getRecommendationAlgorithm(): ?string
    {
        return $this->analyticsData['recommendation_algorithm'] ?? null;
    }

    /**
     * Get recommendation confidence
     */
    private function getRecommendationConfidence(): float
    {
        return $this->analyticsData['recommendation_confidence'] ?? 0.0;
    }

    /**
     * Get discovery method
     */
    private function getDiscoveryMethod(): ?string
    {
        return $this->analyticsData['discovery_method'] ?? null;
    }

    /**
     * Get filter bypass count
     */
    private function getFilterBypassCount(): int
    {
        return $this->analyticsData['filter_bypass_count'] ?? 0;
    }

    /**
     * Get personalization factors
     */
    private function getPersonalizationFactors(): array
    {
        return $this->analyticsData['personalization_factors'] ?? [];
    }
}