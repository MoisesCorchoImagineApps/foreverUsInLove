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
 * MatchCreated Event
 * 
 * Domain event triggered when a new match is created between two users in the
 * ForeverUsInLove dating platform. This event facilitates real-time notifications,
 * analytics tracking, and integration with various platform services following
 * Domain-Driven Design principles.
 * 
 * Key Responsibilities:
 * - Broadcast real-time match notifications to both users
 * - Trigger match celebration and congratulations workflows
 * - Initialize conversation starter suggestions and icebreakers
 * - Update user matching statistics and analytics
 * - Integrate with messaging system for match conversations
 * - Trigger premium feature promotions for enhanced matching
 * - Update recommendation algorithms based on successful matches
 * - Send external notifications (push, email, SMS) based on preferences
 * 
 * Broadcasting Channels:
 * 1. User-specific private channels for personalized notifications
 * 2. Analytics channel for real-time matching metrics
 * 3. Admin dashboard channel for monitoring match creation rates
 * 4. Recommendation engine channel for algorithm updates
 * 5. Messaging system channel for conversation initialization
 * 
 * Event Data Payload:
 * - Match entity with full match details and compatibility scores
 * - Both user profiles with essential matching information
 * - Match creation context (source, algorithm used, timing)
 * - Personalization data for customized user experiences
 * - Analytics data for performance tracking and optimization
 * 
 * Integration Points:
 * - Real-time notification system
 * - Email/SMS notification services
 * - Push notification delivery
 * - Analytics and metrics collection
 * - Recommendation algorithm feedback
 * - Conversation starter generation
 * - Premium feature promotion engine
 * - User engagement tracking
 * 
 * @package App\Domain\Matching\Events
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove
 * @license Proprietary
 * @version 1.0.0
 * @since 2025-10-10
 */
class MatchCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var UserMatch The created match entity
     */
    public readonly UserMatch $match;

    /**
     * @var array<string, mixed> Match notification data
     */
    public readonly array $notificationData;

    /**
     * @var array<string, mixed> Analytics and tracking data
     */
    public readonly array $analyticsData;

    /**
     * @var array<string, mixed> Personalization context for user experiences
     */
    public readonly array $personalizationData;

    /**
     * @var array<string, mixed> Integration data for external services
     */
    public readonly array $integrationData;

    /**
     * Create a new MatchCreated event instance
     * 
     * @param UserMatch $match The match that was created
     * @param array<string, mixed> $notificationData Data for notifications
     * @param array<string, mixed> $analyticsData Data for analytics tracking
     * @param array<string, mixed> $personalizationData Data for personalization
     * @param array<string, mixed> $integrationData Data for external integrations
     */
    public function __construct(
        UserMatch $match,
        array $notificationData = [],
        array $analyticsData = [],
        array $personalizationData = [],
        array $integrationData = []
    ) {
        $this->match = $match;
        $this->notificationData = $notificationData;
        $this->analyticsData = $analyticsData;
        $this->personalizationData = $personalizationData;
        $this->integrationData = $integrationData;

        // Set socket connection context for real-time broadcasting
        $this->socket = $integrationData['socket_id'] ?? null;
    }

    /**
     * Get the channels the event should broadcast on
     * 
     * Defines multiple broadcasting channels for different aspects of the match
     * creation event, enabling targeted real-time updates.
     * 
     * @return array<Channel|string> Array of broadcasting channels
     */
    public function broadcastOn(): array
    {
        return [
            // Private channels for each user involved in the match
            new PrivateChannel("user.{$this->match->user_id_1}"),
            new PrivateChannel("user.{$this->match->user_id_2}"),
            
            // Analytics channel for real-time metrics tracking
            new PrivateChannel('analytics.matches'),
            
            // Admin dashboard channel for monitoring
            new PrivateChannel('admin.matches.created'),
            
            // Recommendation engine channel for algorithm feedback
            new PrivateChannel('recommendations.match-feedback'),
            
            // Messaging system channel for conversation initialization
            new PrivateChannel("messaging.match.{$this->match->match_id}")
        ];
    }

    /**
     * Get the broadcast event name
     * 
     * @return string The event name for client-side handling
     */
    public function broadcastAs(): string
    {
        return 'match.created';
    }

    /**
     * Get the data to broadcast with the event
     * 
     * Provides comprehensive match data optimized for real-time client consumption
     * while ensuring sensitive information is appropriately filtered.
     * 
     * @return array<string, mixed> Broadcast payload data
     */
    public function broadcastWith(): array
    {
        return [
            'match' => [
                'id' => $this->match->match_id,
                'user1_id' => $this->match->user_id_1,
                'user2_id' => $this->match->user_id_2,
                'compatibility_score' => $this->match->compatibility_score,
                'match_source' => $this->match->match_source,
                'created_at' => $this->match->matched_at->toISOString(),
                'is_premium_match' => $this->isPremiumMatch(),
                'quality_indicators' => $this->getQualityIndicators()
            ],
            'notification' => [
                'title' => $this->getNotificationTitle(),
                'message' => $this->getNotificationMessage(),
                'celebration_level' => $this->getCelebrationLevel(),
                'should_show_animation' => $this->shouldShowAnimation(),
                'custom_messages' => $this->getCustomMessages()
            ],
            'conversation' => [
                'icebreaker_suggestions' => $this->getIcebreakerSuggestions(),
                'conversation_starter_id' => $this->getConversationStarterId(),
                'shared_interests' => $this->getSharedInterests(),
                'conversation_tips' => $this->getConversationTips()
            ],
            'features' => [
                'available_features' => $this->getAvailableFeatures(),
                'premium_promotions' => $this->getPremiumPromotions(),
                'next_steps' => $this->getNextSteps(),
                'enhanced_features' => $this->getEnhancedFeatures()
            ],
            'analytics' => [
                'match_rank' => $this->getMatchRank(),
                'prediction_accuracy' => $this->getPredictionAccuracy(),
                'recommendation_source' => $this->getRecommendationSource(),
                'success_probability' => $this->getSuccessProbability()
            ],
            'personalization' => [
                'celebration_style' => $this->getCelebrationStyle(),
                'notification_preferences' => $this->getNotificationPreferences(),
                'user_context' => $this->getUserContext(),
                'timing_context' => $this->getTimingContext()
            ],
            'metadata' => [
                'event_id' => $this->generateEventId(),
                'timestamp' => Carbon::now()->toISOString(),
                'version' => '1.0.0',
                'source' => 'matching_service',
                'correlation_id' => $this->getCorrelationId()
            ]
        ];
    }

    /**
     * Determine if the event should be broadcast immediately or queued
     * 
     * @return bool True to broadcast immediately, false to queue
     */
    public function broadcastWhen(): bool
    {
        // Broadcast immediately for high-priority match notifications
        return $this->isPremiumMatch() || $this->isHighCompatibilityMatch();
    }

    /**
     * Get the queue connection for broadcasting
     * 
     * @return string|null Queue connection name
     */
    public function broadcastQueue(): ?string
    {
        return $this->isPremiumMatch() ? 'high-priority' : 'default';
    }

    /**
     * Get notification data for external services
     * 
     * Provides structured data for push notifications, emails, and SMS.
     * 
     * @return array<string, mixed> External notification data
     */
    public function getExternalNotificationData(): array
    {
        return [
            'users' => [
                $this->match->user_id_1 => [
                    'notification_title' => $this->getPersonalizedTitle(UserId::fromString($this->match->user_id_1)),
                    'notification_body' => $this->getPersonalizedMessage(UserId::fromString($this->match->user_id_1)),
                    'action_url' => $this->getActionUrl(UserId::fromString($this->match->user_id_1)),
                    'celebration_data' => $this->getCelebrationData(UserId::fromString($this->match->user_id_1))
                ],
                $this->match->user_id_2 => [
                    'notification_title' => $this->getPersonalizedTitle(UserId::fromString($this->match->user_id_2)),
                    'notification_body' => $this->getPersonalizedMessage(UserId::fromString($this->match->user_id_2)),
                    'action_url' => $this->getActionUrl(UserId::fromString($this->match->user_id_2)),
                    'celebration_data' => $this->getCelebrationData(UserId::fromString($this->match->user_id_2))
                ]
            ],
            'match_context' => [
                'compatibility_score' => $this->match->compatibility_score,
                'match_quality' => $this->getMatchQuality(),
                'shared_interests_count' => count($this->getSharedInterests()),
                'premium_features_available' => $this->getAvailableFeatures()
            ]
        ];
    }

    /**
     * Get analytics data for tracking and optimization
     * 
     * Provides comprehensive data for analytics systems and machine learning.
     * 
     * @return array<string, mixed> Analytics tracking data
     */
    public function getAnalyticsTrackingData(): array
    {
        return [
            'match_metrics' => [
                'match_id' => $this->match->match_id,
                'compatibility_score' => $this->match->compatibility_score,
                'match_source' => $this->match->match_source,
                'algorithm_version' => $this->getAlgorithmVersion(),
                'processing_time_ms' => $this->getProcessingTime(),
                'candidate_pool_size' => $this->getCandidatePoolSize()
            ],
            'user_metrics' => [
                'user1_profile_completeness' => $this->getUserProfileCompleteness(UserId::fromString($this->match->user_id_1)),
                'user2_profile_completeness' => $this->getUserProfileCompleteness(UserId::fromString($this->match->user_id_2)),
                'user1_activity_level' => $this->getUserActivityLevel(UserId::fromString($this->match->user_id_1)),
                'user2_activity_level' => $this->getUserActivityLevel(UserId::fromString($this->match->user_id_2)),
                'user1_premium_status' => $this->getUserPremiumStatus(UserId::fromString($this->match->user_id_1)),
                'user2_premium_status' => $this->getUserPremiumStatus(UserId::fromString($this->match->user_id_2))
            ],
            'compatibility_breakdown' => [
                'personality_score' => $this->getPersonalityCompatibility(),
                'interests_score' => $this->getInterestsCompatibility(),
                'lifestyle_score' => $this->getLifestyleCompatibility(),
                'demographics_score' => $this->getDemographicsCompatibility(),
                'behavioral_score' => $this->getBehavioralCompatibility()
            ],
            'prediction_data' => [
                'success_probability' => $this->getSuccessProbability(),
                'conversation_likelihood' => $this->getConversationLikelihood(),
                'meeting_probability' => $this->getMeetingProbability(),
                'relationship_potential' => $this->getRelationshipPotential()
            ],
            'context_data' => [
                'match_timing' => $this->getMatchTiming(),
                'geographic_distance' => $this->getGeographicDistance(),
                'age_difference' => $this->getAgeDifference(),
                'shared_interests' => $this->getSharedInterests(),
                'mutual_connections' => $this->getMutualConnections()
            ]
        ];
    }

    // ================================================================
    // PRIVATE HELPER METHODS FOR DATA PREPARATION
    // ================================================================

    /**
     * Check if this is a premium match
     */
    private function isPremiumMatch(): bool
    {
        return $this->match->compatibility_score >= 85 ||
               $this->getUserPremiumStatus(UserId::fromString($this->match->user_id_1)) ||
               $this->getUserPremiumStatus(UserId::fromString($this->match->user_id_2));
    }

    /**
     * Check if this is a high compatibility match
     */
    private function isHighCompatibilityMatch(): bool
    {
        return $this->match->compatibility_score >= 80;
    }

    /**
     * Get celebration level based on match quality
     */
    private function getCelebrationLevel(): string
    {
        $score = $this->match->compatibility_score;
        
        if ($score >= 90) return 'spectacular';
        if ($score >= 80) return 'high';
        if ($score >= 70) return 'medium';
        return 'standard';
    }

    /**
     * Get personalized notification title for user
     */
    private function getPersonalizedTitle(UserId $userId): string
    {
        $celebrationLevel = $this->getCelebrationLevel();
        
        return match($celebrationLevel) {
            'spectacular' => '🎉 It\'s a Perfect Match! 🎉',
            'high' => '✨ You have a new match! ✨',
            'medium' => '💕 New match found!',
            default => '👋 You matched with someone!'
        };
    }

    /**
     * Get personalized notification message for user
     */
    private function getPersonalizedMessage(UserId $userId): string
    {
        $otherUserId = $userId->toString() === $this->match->user_id_1 
            ? $this->match->user_id_2 
            : $this->match->user_id_1;
            
        $compatibilityScore = $this->match->compatibility_score;
        $scorePercentage = round($compatibilityScore);
        
        return "You and your new match have a {$scorePercentage}% compatibility score! " .
               "Start a conversation and see where it leads.";
    }

    /**
     * Generate unique event ID for tracking
     */
    private function generateEventId(): string
    {
        return 'match_created_' . $this->match->match_id . '_' . Carbon::now()->timestamp;
    }

    /**
     * Get correlation ID for distributed tracing
     */
    private function getCorrelationId(): string
    {
        return $this->integrationData['correlation_id'] ?? 
               'match_' . substr($this->match->match_id, 0, 8);
    }

    /**
     * Get icebreaker suggestions for the match
     */
    private function getIcebreakerSuggestions(): array
    {
        return $this->notificationData['icebreaker_suggestions'] ?? [
            "I noticed we both love {shared_interest}! What got you into it?",
            "Your {profile_detail} caught my attention. Tell me more about it!",
            "We seem to have great compatibility - what's your favorite way to spend weekends?"
        ];
    }

    /**
     * Get shared interests between matched users
     */
    private function getSharedInterests(): array
    {
        return $this->personalizationData['shared_interests'] ?? [];
    }

    /**
     * Get available features for the match
     */
    private function getAvailableFeatures(): array
    {
        $features = ['messaging', 'icebreakers'];
        
        if ($this->isPremiumMatch()) {
            $features = array_merge($features, [
                'video_call', 'voice_message', 'priority_messaging', 'read_receipts'
            ]);
        }
        
        return $features;
    }

    /**
     * Get quality indicators for the match
     */
    private function getQualityIndicators(): array
    {
        return [
            'compatibility_score' => $this->match->compatibility_score,
            'interaction_count' => $this->match->interaction_count,
            'message_count' => $this->match->message_count,
            'is_featured' => $this->match->is_featured,
            'celebration_level' => $this->match->celebration_level
        ];
    }

    /**
     * Get notification title
     */
    private function getNotificationTitle(): string
    {
        return '¡Nuevo Match! 💕';
    }

    /**
     * Get notification message
     */
    private function getNotificationMessage(): string
    {
        $score = round($this->match->compatibility_score, 1);
        return "Tienes un nuevo match con {$score}% de compatibilidad. ¡Comienza a chatear!";
    }

    /**
     * Check if should show animation
     */
    private function shouldShowAnimation(): bool
    {
        return $this->match->compatibility_score >= 80;
    }

    /**
     * Get custom messages
     */
    private function getCustomMessages(): array
    {
        return [
            '¡Es un match perfecto!',
            '¡Tienen mucha química!',
            '¡Comienza la conversación!'
        ];
    }

    /**
     * Get conversation starter ID
     */
    private function getConversationStarterId(): ?string
    {
        return $this->notificationData['conversation_starter_id'] ?? null;
    }

    /**
     * Get conversation tips
     */
    private function getConversationTips(): array
    {
        return [
            'Pregunta sobre sus intereses compartidos',
            'Menciona algo de su perfil',
            'Sé auténtico y divertido'
        ];
    }

    /**
     * Get premium promotions
     */
    private function getPremiumPromotions(): array
    {
        return $this->isPremiumMatch() ? [] : [
            'upgrade_to_premium' => 'Desbloquea funciones premium',
            'unlimited_matches' => 'Matches ilimitados',
            'advanced_filters' => 'Filtros avanzados'
        ];
    }

    /**
     * Get next steps
     */
    private function getNextSteps(): array
    {
        return [
            'start_conversation' => 'Iniciar conversación',
            'view_profile' => 'Ver perfil completo',
            'send_icebreaker' => 'Enviar rompehielos'
        ];
    }

    /**
     * Get enhanced features
     */
    private function getEnhancedFeatures(): array
    {
        return $this->isPremiumMatch() ? [
            'video_call' => 'Llamada de video',
            'voice_message' => 'Mensaje de voz',
            'priority_messaging' => 'Mensajería prioritaria'
        ] : [];
    }

    /**
     * Get match rank
     */
    private function getMatchRank(): int
    {
        return (int) ($this->match->compatibility_score / 10);
    }

    /**
     * Get prediction accuracy
     */
    private function getPredictionAccuracy(): float
    {
        return min(0.95, $this->match->compatibility_score / 100);
    }

    /**
     * Get recommendation source
     */
    private function getRecommendationSource(): string
    {
        return $this->match->match_source ?? 'algorithm';
    }

    /**
     * Get success probability
     */
    private function getSuccessProbability(): float
    {
        return $this->match->compatibility_score / 100;
    }

    /**
     * Get celebration style
     */
    private function getCelebrationStyle(): string
    {
        return $this->match->celebration_level ?? 'standard';
    }

    /**
     * Get notification preferences
     */
    private function getNotificationPreferences(): array
    {
        return $this->personalizationData['notification_preferences'] ?? [
            'push' => true,
            'email' => true,
            'sms' => false
        ];
    }

    /**
     * Get user context
     */
    private function getUserContext(): array
    {
        return $this->personalizationData['user_context'] ?? [];
    }

    /**
     * Get timing context
     */
    private function getTimingContext(): array
    {
        return [
            'time_of_day' => Carbon::now()->format('H:i'),
            'day_of_week' => Carbon::now()->dayOfWeek,
            'is_weekend' => Carbon::now()->isWeekend()
        ];
    }

    /**
     * Get action URL for user
     */
    private function getActionUrl(UserId $userId): string
    {
        return "/matches/{$this->match->match_id}";
    }

    /**
     * Get celebration data for user
     */
    private function getCelebrationData(UserId $userId): array
    {
        return [
            'level' => $this->getCelebrationLevel(),
            'animation' => $this->shouldShowAnimation(),
            'sound' => true
        ];
    }

    /**
     * Get match quality
     */
    private function getMatchQuality(): string
    {
        $score = $this->match->compatibility_score;
        if ($score >= 90) return 'excellent';
        if ($score >= 80) return 'very_good';
        if ($score >= 70) return 'good';
        return 'fair';
    }

    /**
     * Get algorithm version
     */
    private function getAlgorithmVersion(): string
    {
        return $this->analyticsData['algorithm_version'] ?? '1.0';
    }

    /**
     * Get processing time
     */
    private function getProcessingTime(): int
    {
        return $this->analyticsData['processing_time_ms'] ?? 0;
    }

    /**
     * Get candidate pool size
     */
    private function getCandidatePoolSize(): int
    {
        return $this->analyticsData['candidate_pool_size'] ?? 0;
    }

    /**
     * Get user profile completeness
     */
    private function getUserProfileCompleteness(UserId $userId): float
    {
        return $this->analyticsData['user_profile_completeness'][$userId->toString()] ?? 0.8;
    }

    /**
     * Get user activity level
     */
    private function getUserActivityLevel(UserId $userId): string
    {
        return $this->analyticsData['user_activity_level'][$userId->toString()] ?? 'medium';
    }

    /**
     * Get user premium status
     */
    private function getUserPremiumStatus(UserId $userId): bool
    {
        return $this->analyticsData['user_premium_status'][$userId->toString()] ?? false;
    }

    /**
     * Get personality compatibility
     */
    private function getPersonalityCompatibility(): float
    {
        return $this->analyticsData['compatibility_breakdown']['personality'] ?? 0.8;
    }

    /**
     * Get interests compatibility
     */
    private function getInterestsCompatibility(): float
    {
        return $this->analyticsData['compatibility_breakdown']['interests'] ?? 0.7;
    }

    /**
     * Get lifestyle compatibility
     */
    private function getLifestyleCompatibility(): float
    {
        return $this->analyticsData['compatibility_breakdown']['lifestyle'] ?? 0.75;
    }

    /**
     * Get demographics compatibility
     */
    private function getDemographicsCompatibility(): float
    {
        return $this->analyticsData['compatibility_breakdown']['demographics'] ?? 0.85;
    }

    /**
     * Get behavioral compatibility
     */
    private function getBehavioralCompatibility(): float
    {
        return $this->analyticsData['compatibility_breakdown']['behavioral'] ?? 0.8;
    }

    /**
     * Get conversation likelihood
     */
    private function getConversationLikelihood(): float
    {
        return $this->analyticsData['prediction_data']['conversation_likelihood'] ?? 0.7;
    }

    /**
     * Get meeting probability
     */
    private function getMeetingProbability(): float
    {
        return $this->analyticsData['prediction_data']['meeting_probability'] ?? 0.5;
    }

    /**
     * Get relationship potential
     */
    private function getRelationshipPotential(): float
    {
        return $this->analyticsData['prediction_data']['relationship_potential'] ?? 0.6;
    }

    /**
     * Get match timing
     */
    private function getMatchTiming(): string
    {
        return $this->analyticsData['context_data']['match_timing'] ?? 'optimal';
    }

    /**
     * Get geographic distance
     */
    private function getGeographicDistance(): float
    {
        return $this->analyticsData['context_data']['geographic_distance'] ?? 0.0;
    }

    /**
     * Get age difference
     */
    private function getAgeDifference(): int
    {
        return $this->analyticsData['context_data']['age_difference'] ?? 0;
    }

    /**
     * Get mutual connections
     */
    private function getMutualConnections(): array
    {
        return $this->analyticsData['context_data']['mutual_connections'] ?? [];
    }
}