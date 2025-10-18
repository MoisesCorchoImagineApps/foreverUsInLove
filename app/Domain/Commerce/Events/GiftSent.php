<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\Entities\GiftTransaction;
use App\Domain\Shared\ValueObjects\UserId;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

/**
 * GiftSent Event - Gift delivery and recipient notifications
 * 
 * Comprehensive event fired when a virtual gift is successfully sent and delivered
 * in the ForeverUsInLove dating application. Handles real-time gift notifications,
 * romantic animations, recipient alerts, and engagement tracking with sophisticated
 * personalization and relationship context awareness.
 * 
 * Features:
 * - Real-time gift delivery notifications with rich animations
 * - Personalized recipient notifications with romantic context
 * - Sender confirmation and delivery tracking
 * - Social engagement and relationship milestone tracking
 * - Gift analytics and popularity insights
 * - Integration with matching and recommendation algorithms
 * - Romantic gesture automation and follow-up suggestions
 * - Gift history and relationship timeline integration
 * - Gamification elements and achievement unlocking
 * - Social sharing and viral mechanics
 * - Customer engagement optimization
 * - Revenue attribution and conversion tracking
 * 
 * Broadcasting Channels:
 * - Private recipient channel for gift notifications
 * - Private sender channel for delivery confirmations
 * - Relationship channel for couple interactions
 * - Analytics channel for engagement metrics
 * - Admin channel for gift monitoring
 * - Social channel for community features
 * 
 * Architecture:
 * - Event-Driven Architecture for decoupled notifications
 * - Domain-Driven Design (DDD) principles
 * - Clean Architecture / Hexagonal Architecture
 * - SOLID principles with Dependency Inversion
 * - Laravel 12 with PHP 8.2 strict typing
 * - Real-time broadcasting with Laravel Echo
 * - Rich media and animation support
 * 
 * @package App\Domain\Commerce\Events
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 * 
 * @see \App\Domain\Commerce\Entities\GiftTransaction
 * @see \App\Domain\Commerce\Services\GiftService
 */
class GiftSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Gift transaction with complete delivery details
     */
    public readonly GiftTransaction $giftTransaction;

    /**
     * User who sent the gift
     */
    public readonly UserId $senderId;

    /**
     * User who receives the gift
     */
    public readonly UserId $recipientId;

    /**
     * Event timestamp for ordering and analytics
     */
    public readonly Carbon $sentAt;

    /**
     * Gift delivery context and metadata
     */
    public readonly array $deliveryContext;

    /**
     * Relationship context between sender and recipient
     */
    public readonly array $relationshipContext;

    /**
     * Personalization data for notifications
     */
    public readonly array $personalizationData;

    /**
     * Animation and presentation configuration
     */
    public readonly array $animationConfig;

    /**
     * Create a new GiftSent event instance
     * 
     * @param GiftTransaction $giftTransaction Successfully sent gift transaction
     * @param UserId|string $senderId User who sent the gift
     * @param UserId|string $recipientId User who receives the gift
     * @param array $deliveryContext Additional delivery metadata
     */
    public function __construct(
        GiftTransaction $giftTransaction,
        UserId|string $senderId,
        UserId|string $recipientId,
        array $deliveryContext = []
    ) {
        $this->giftTransaction = $giftTransaction;
        $this->senderId = $senderId instanceof UserId ? $senderId : UserId::from($senderId);
        $this->recipientId = $recipientId instanceof UserId ? $recipientId : UserId::from($recipientId);
        $this->sentAt = Carbon::now();
        $this->deliveryContext = $deliveryContext;
        
        // Initialize contextual data
        $this->relationshipContext = $this->buildRelationshipContext();
        $this->personalizationData = $this->buildPersonalizationData();
        $this->animationConfig = $this->buildAnimationConfig();
    }

    /**
     * Get the channels the event should broadcast on
     * 
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            // Private channel for recipient notifications
            new PrivateChannel("user.{$this->recipientId}.gifts"),
            
            // Private channel for sender confirmations
            new PrivateChannel("user.{$this->senderId}.gifts.sent"),
            
            // Analytics channel for engagement metrics
            new Channel('analytics.gifts'),
            
            // Admin channel for gift monitoring
            new PrivateChannel('admin.gifts.monitor')
        ];

        // Add relationship channel if users are connected
        if ($this->areUsersConnected()) {
            $relationshipId = $this->getRelationshipId();
            $channels[] = new PrivateChannel("relationship.{$relationshipId}.activities");
        }

        // Add social channel for community features if gift is shareable
        if ($this->isGiftSociallyShareable()) {
            $channels[] = new Channel('social.gifts.public');
        }

        // Add special occasion channel if applicable
        if ($this->isSpecialOccasionGift()) {
            $occasion = $this->getSpecialOccasion();
            $channels[] = new Channel("occasions.{$occasion}.gifts");
        }

        return array_filter($channels); // Remove null channels
    }

    /**
     * Get the event name for broadcasting
     */
    public function broadcastAs(): string
    {
        return 'gift.sent';
    }

    /**
     * Get the data to broadcast with the event
     * 
     * @return array Comprehensive gift delivery data
     */
    public function broadcastWith(): array
    {
        return [
            'gift_data' => $this->getGiftData(),
            'sender_data' => $this->getSenderData(),
            'recipient_data' => $this->getRecipientData(),
            'relationship_data' => $this->getRelationshipData(),
            'delivery_data' => $this->getDeliveryData(),
            'animation_data' => $this->getAnimationData(),
            'engagement_data' => $this->getEngagementData(),
            'personalization_data' => $this->personalizationData,
            'social_data' => $this->getSocialData(),
            'sent_at' => $this->sentAt->toISOString(),
            'event_id' => $this->generateEventId()
        ];
    }

    /**
     * Determine if the event should broadcast immediately
     */
    public function shouldBroadcast(): bool
    {
        return $this->giftTransaction->isSuccessful() && 
               !$this->isTestGift() && 
               $this->passesContentPolicy() &&
               $this->recipientAcceptsGifts();
    }

    /**
     * Get comprehensive gift data for notifications
     * 
     * @return array Gift information with rich metadata
     */
    public function getGiftData(): array
    {
        $gift = $this->giftTransaction->getGift();
        
        return [
            'transaction_id' => $this->giftTransaction->getId(),
            'gift_id' => $gift->getId(),
            'gift_name' => $gift->getName(),
            'gift_type' => $gift->getType(),
            'gift_category' => $gift->getCategory(),
            'gift_rarity' => $gift->getRarity(),
            'gift_value' => $gift->getValue(),
            'gift_description' => $gift->getDescription(),
            'gift_image_url' => $gift->getImageUrl(),
            'gift_animation_url' => $gift->getAnimationUrl(),
            'gift_sound_url' => $gift->getSoundUrl(),
            'is_premium' => $gift->isPremium(),
            'is_limited_edition' => $gift->isLimitedEdition(),
            'is_seasonal' => $gift->isSeasonal(),
            'sentiment_score' => $gift->getSentimentScore(),
            'romantic_level' => $gift->getRomanticLevel(),
            'personal_message' => $this->giftTransaction->getPersonalMessage(),
            'delivery_method' => $this->giftTransaction->getDeliveryMethod(),
            'scheduled_delivery' => $this->giftTransaction->getScheduledDelivery()?->toISOString()
        ];
    }

    /**
     * Get sender data for recipient notifications
     * 
     * @return array Sender information with privacy filtering
     */
    public function getSenderData(): array
    {
        return [
            'sender_id' => $this->senderId->toInt(),
            'sender_name' => $this->getSenderDisplayName(),
            'sender_avatar' => $this->getSenderAvatarUrl(),
            'sender_profile_url' => $this->getSenderProfileUrl(),
            'is_anonymous' => $this->giftTransaction->isAnonymous(),
            'sender_tier' => $this->getSenderTier(),
            'gift_sending_streak' => $this->getSenderGiftStreak(),
            'total_gifts_sent' => $this->getSenderTotalGifts(),
            'relationship_status' => $this->getRelationshipStatus(),
            'connection_strength' => $this->getConnectionStrength(),
            'last_interaction' => $this->getLastInteractionDate()?->toISOString(),
            'mutual_interests' => $this->getMutualInterests()
        ];
    }

    /**
     * Get recipient data for personalization
     * 
     * @return array Recipient information for customization
     */
    public function getRecipientData(): array
    {
        return [
            'recipient_id' => $this->recipientId->toInt(),
            'recipient_name' => $this->getRecipientDisplayName(),
            'notification_preferences' => $this->getRecipientNotificationPreferences(),
            'gift_preferences' => $this->getRecipientGiftPreferences(),
            'timezone' => $this->getRecipientTimezone(),
            'locale' => $this->getRecipientLocale(),
            'is_online' => $this->isRecipientOnline(),
            'last_seen' => $this->getRecipientLastSeen()?->toISOString(),
            'gifts_received_count' => $this->getRecipientGiftCount(),
            'favorite_gift_categories' => $this->getRecipientFavoriteCategories(),
            'appreciation_style' => $this->getRecipientAppreciationStyle()
        ];
    }

    /**
     * Get relationship data for context
     * 
     * @return array Relationship context and history
     */
    public function getRelationshipData(): array
    {
        return [
            'relationship_id' => $this->getRelationshipId(),
            'relationship_type' => $this->relationshipContext['type'] ?? 'potential',
            'relationship_stage' => $this->relationshipContext['stage'] ?? 'early',
            'relationship_duration' => $this->relationshipContext['duration'] ?? 0,
            'compatibility_score' => $this->relationshipContext['compatibility'] ?? 0,
            'interaction_frequency' => $this->relationshipContext['frequency'] ?? 'low',
            'gift_exchange_history' => $this->getGiftExchangeHistory(),
            'milestone_context' => $this->getMilestoneContext(),
            'special_occasions' => $this->getSpecialOccasions(),
            'communication_patterns' => $this->getCommunicationPatterns(),
            'shared_activities' => $this->getSharedActivities(),
            'relationship_goals' => $this->getRelationshipGoals()
        ];
    }

    /**
     * Get delivery data for tracking
     * 
     * @return array Delivery information and tracking
     */
    public function getDeliveryData(): array
    {
        return [
            'delivery_status' => 'delivered',
            'delivery_method' => $this->giftTransaction->getDeliveryMethod(),
            'delivery_timestamp' => $this->sentAt->toISOString(),
            'delivery_confirmation' => $this->generateDeliveryConfirmation(),
            'read_receipt_enabled' => $this->isReadReceiptEnabled(),
            'notification_channels' => $this->getDeliveryChannels(),
            'delivery_animation' => $this->getDeliveryAnimationType(),
            'gift_unwrapping' => $this->getUnwrappingConfiguration(),
            'surprise_level' => $this->getSurpriseLevel(),
            'timing_optimization' => $this->getTimingOptimization(),
            'delivery_context' => $this->deliveryContext
        ];
    }

    /**
     * Get animation data for rich presentations
     * 
     * @return array Animation and visual configuration
     */
    public function getAnimationData(): array
    {
        return [
            'animation_type' => $this->animationConfig['type'] ?? 'standard',
            'animation_duration' => $this->animationConfig['duration'] ?? 3.0,
            'animation_effects' => $this->animationConfig['effects'] ?? [],
            'particle_effects' => $this->animationConfig['particles'] ?? [],
            'sound_effects' => $this->animationConfig['sounds'] ?? [],
            'haptic_feedback' => $this->animationConfig['haptics'] ?? false,
            'transition_style' => $this->animationConfig['transition'] ?? 'smooth',
            'color_scheme' => $this->animationConfig['colors'] ?? 'romantic',
            'gift_reveal_style' => $this->animationConfig['reveal'] ?? 'unwrap',
            'background_effects' => $this->animationConfig['background'] ?? [],
            'interactive_elements' => $this->animationConfig['interactive'] ?? [],
            'customization_options' => $this->animationConfig['customizable'] ?? []
        ];
    }

    /**
     * Get engagement data for analytics
     * 
     * @return array Engagement metrics and triggers
     */
    public function getEngagementData(): array
    {
        return [
            'engagement_triggers' => [
                'triggers_response_prompt' => $this->triggersResponsePrompt(),
                'triggers_reciprocal_gift' => $this->triggersReciprocalGift(),
                'triggers_conversation_starter' => $this->triggersConversationStarter(),
                'triggers_date_suggestion' => $this->triggersDateSuggestion(),
                'triggers_milestone_celebration' => $this->triggersMilestoneCelebration()
            ],
            'gamification_elements' => [
                'unlocks_achievement' => $this->unlocksAchievement(),
                'awards_points' => $this->getPointsAwarded(),
                'completes_challenge' => $this->completesChallenge(),
                'advances_streak' => $this->advancesStreak(),
                'unlocks_badge' => $this->unlocksBadge()
            ],
            'social_elements' => [
                'is_shareable' => $this->isGiftSociallyShareable(),
                'generates_story' => $this->generatesStory(),
                'creates_memory' => $this->createsMemory(),
                'influences_matching' => $this->influencesMatching(),
                'affects_visibility' => $this->affectsVisibility()
            ],
            'conversion_tracking' => [
                'attribution_source' => $this->getAttributionSource(),
                'campaign_context' => $this->getCampaignContext(),
                'conversion_value' => $this->getConversionValue(),
                'lifetime_value_impact' => $this->getLifetimeValueImpact(),
                'retention_probability' => $this->getRetentionProbability()
            ]
        ];
    }

    /**
     * Get social data for community features
     * 
     * @return array Social sharing and community data
     */
    public function getSocialData(): array
    {
        return [
            'social_visibility' => $this->getSocialVisibility(),
            'sharing_options' => $this->getSharingOptions(),
            'community_impact' => $this->getCommunityImpact(),
            'viral_potential' => $this->getViralPotential(),
            'influence_metrics' => $this->getInfluenceMetrics(),
            'trend_contribution' => $this->getTrendContribution(),
            'social_proof_elements' => $this->getSocialProofElements(),
            'community_reactions' => $this->getCommunityReactions()
        ];
    }

    // ============================================================================
    // PRIVATE HELPER METHODS
    // ============================================================================

    /**
     * Build relationship context between users
     */
    private function buildRelationshipContext(): array
    {
        // Implementation would analyze relationship between sender and recipient
        return [
            'type' => 'match', // 'match', 'conversation', 'favorite', 'potential'
            'stage' => 'getting_to_know', // 'initial', 'getting_to_know', 'dating', 'serious'
            'duration' => 7, // days since first interaction
            'compatibility' => 85.5, // compatibility score
            'frequency' => 'moderate' // interaction frequency
        ];
    }

    /**
     * Build personalization data
     */
    private function buildPersonalizationData(): array
    {
        return [
            'sender_preferences' => $this->getSenderGiftPreferences(),
            'recipient_preferences' => $this->getRecipientGiftPreferences(),
            'timing_preferences' => $this->getTimingPreferences(),
            'cultural_context' => $this->getCulturalContext(),
            'seasonal_context' => $this->getSeasonalContext(),
            'personal_significance' => $this->getPersonalSignificance()
        ];
    }

    /**
     * Build animation configuration
     */
    private function buildAnimationConfig(): array
    {
        $gift = $this->giftTransaction->getGift();
        
        return [
            'type' => $gift->getAnimationType() ?? 'romantic_sparkle',
            'duration' => $gift->getAnimationDuration() ?? 3.0,
            'effects' => $this->determineAnimationEffects(),
            'particles' => $this->determineParticleEffects(),
            'sounds' => $this->determineSoundEffects(),
            'haptics' => $this->shouldUseHapticFeedback(),
            'transition' => 'smooth',
            'colors' => $this->determineColorScheme(),
            'reveal' => $this->determineRevealStyle(),
            'background' => $this->determineBackgroundEffects(),
            'interactive' => $this->determineInteractiveElements(),
            'customizable' => $this->getCustomizationOptions()
        ];
    }

    /**
     * Check if users are connected
     */
    private function areUsersConnected(): bool
    {
        // Implementation would check if users have an active connection/match
        return true; // Placeholder
    }

    /**
     * Get relationship identifier
     */
    private function getRelationshipId(): string
    {
        // Implementation would generate or retrieve relationship ID
        $ids = [$this->senderId->toInt(), $this->recipientId->toInt()];
        sort($ids);
        return 'rel_' . implode('_', $ids);
    }

    /**
     * Check if gift is socially shareable
     */
    private function isGiftSociallyShareable(): bool
    {
        return $this->giftTransaction->getGift()->isSociallyShareable() &&
               !$this->giftTransaction->isAnonymous() &&
               $this->getSocialVisibility() !== 'private';
    }

    /**
     * Check if this is a special occasion gift
     */
    private function isSpecialOccasionGift(): bool
    {
        return !empty($this->getSpecialOccasion());
    }

    /**
     * Get special occasion
     */
    private function getSpecialOccasion(): ?string
    {
        $metadata = $this->giftTransaction->getMetadata();
        return $metadata['occasion'] ?? null;
    }

    /**
     * Check if this is a test gift
     */
    private function isTestGift(): bool
    {
        return config('app.env') === 'testing' ||
               str_contains($this->giftTransaction->getGift()->getName(), 'test');
    }

    /**
     * Check if gift passes content policy
     */
    private function passesContentPolicy(): bool
    {
        // Implementation would check content moderation
        return true;
    }

    /**
     * Check if recipient accepts gifts
     */
    private function recipientAcceptsGifts(): bool
    {
        // Implementation would check recipient preferences
        return true;
    }

    /**
     * Generate unique event identifier
     */
    private function generateEventId(): string
    {
        return 'evt_gift_' . $this->giftTransaction->getId() . '_' . time();
    }

    /**
     * Additional helper methods would be implemented here for:
     * - User data retrieval and privacy filtering
     * - Relationship analysis and context building
     * - Animation and presentation configuration
     * - Engagement and gamification logic
     * - Social sharing and community features
     * - Analytics and conversion tracking
     * - Personalization and localization
     */

    /**
     * Placeholder methods for demonstration
     */
    private function getSenderDisplayName(): string { return 'John D.'; }
    private function getSenderAvatarUrl(): string { return '/avatars/sender.jpg'; }
    private function getSenderProfileUrl(): string { return '/profile/sender'; }
    private function getSenderTier(): string { return 'premium'; }
    private function getSenderGiftStreak(): int { return 3; }
    private function getSenderTotalGifts(): int { return 15; }
    private function getRelationshipStatus(): string { return 'dating'; }
    private function getConnectionStrength(): float { return 78.5; }
    private function getLastInteractionDate(): ?Carbon { return Carbon::now()->subHours(2); }
    private function getMutualInterests(): array { return ['travel', 'photography']; }
    private function getRecipientDisplayName(): string { return 'Sarah M.'; }
    private function getRecipientNotificationPreferences(): array { return ['push' => true, 'email' => true]; }
    private function getRecipientGiftPreferences(): array { return ['romantic' => true, 'flowers' => true]; }
    private function getRecipientTimezone(): string { return 'America/New_York'; }
    private function getRecipientLocale(): string { return 'en_US'; }
    private function isRecipientOnline(): bool { return true; }
    private function getRecipientLastSeen(): ?Carbon { return Carbon::now()->subMinutes(5); }
    private function getRecipientGiftCount(): int { return 8; }
    private function getRecipientFavoriteCategories(): array { return ['romantic', 'thoughtful']; }
    private function getRecipientAppreciationStyle(): string { return 'expressive'; }
    
    // Additional placeholder methods...
    private function getGiftExchangeHistory(): array { return []; }
    private function getMilestoneContext(): array { return []; }
    private function getSpecialOccasions(): array { return []; }
    private function getCommunicationPatterns(): array { return []; }
    private function getSharedActivities(): array { return []; }
    private function getRelationshipGoals(): array { return []; }
    private function generateDeliveryConfirmation(): string { return 'delivered_' . time(); }
    private function isReadReceiptEnabled(): bool { return true; }
    private function getDeliveryChannels(): array { return ['push', 'in_app']; }
    private function getDeliveryAnimationType(): string { return 'romantic_sparkle'; }
    private function getUnwrappingConfiguration(): array { return ['style' => 'elegant']; }
    private function getSurpriseLevel(): string { return 'high'; }
    private function getTimingOptimization(): array { return ['optimal_time' => true]; }
    private function determineAnimationEffects(): array { return ['sparkle', 'glow']; }
    private function determineParticleEffects(): array { return ['hearts', 'stars']; }
    private function determineSoundEffects(): array { return ['chime', 'romantic']; }
    private function shouldUseHapticFeedback(): bool { return true; }
    private function determineColorScheme(): string { return 'romantic_pink'; }
    private function determineRevealStyle(): string { return 'elegant_unwrap'; }
    private function determineBackgroundEffects(): array { return ['soft_glow']; }
    private function determineInteractiveElements(): array { return ['tap_to_open']; }
    private function getCustomizationOptions(): array { return []; }
    private function triggersResponsePrompt(): bool { return true; }
    private function triggersReciprocalGift(): bool { return false; }
    private function triggersConversationStarter(): bool { return true; }
    private function triggersDateSuggestion(): bool { return false; }
    private function triggersMilestoneCelebration(): bool { return false; }
    private function unlocksAchievement(): bool { return false; }
    private function getPointsAwarded(): int { return 50; }
    private function completesChallenge(): bool { return false; }
    private function advancesStreak(): bool { return true; }
    private function unlocksBadge(): bool { return false; }
    private function generatesStory(): bool { return true; }
    private function createsMemory(): bool { return true; }
    private function influencesMatching(): bool { return false; }
    private function affectsVisibility(): bool { return false; }
    private function getAttributionSource(): string { return 'gift_recommendation'; }
    private function getCampaignContext(): array { return []; }
    private function getConversionValue(): float { return 25.99; }
    private function getLifetimeValueImpact(): float { return 5.0; }
    private function getRetentionProbability(): float { return 0.85; }
    private function getSocialVisibility(): string { return 'friends'; }
    private function getSharingOptions(): array { return ['facebook', 'instagram']; }
    private function getCommunityImpact(): array { return []; }
    private function getViralPotential(): float { return 0.15; }
    private function getInfluenceMetrics(): array { return []; }
    private function getTrendContribution(): array { return []; }
    private function getSocialProofElements(): array { return []; }
    private function getCommunityReactions(): array { return []; }
    private function getSenderGiftPreferences(): array { return []; }
    private function getTimingPreferences(): array { return []; }
    private function getCulturalContext(): array { return []; }
    private function getSeasonalContext(): array { return []; }
    private function getPersonalSignificance(): array { return []; }
}