<?php

declare(strict_types=1);

namespace App\Domain\Chat\Events;

use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Message Sent Event
 * 
 * Comprehensive event for real-time message broadcasting, notifications,
 * and cross-system integration when messages are sent in chats.
 * 
 * @package App\Domain\Chat\Events
 * @author ForeverUsInLove Team
 * @copyright 2024 ForeverUsInLove
 * @license MIT
 * 
 * @version 1.0.0
 * @since 2024-01-20
 * 
 * Architecture: Clean Architecture / Hexagonal Architecture
 * Pattern: Domain Events with Laravel Broadcasting
 * 
 * Key Features:
 * - Real-time message broadcasting via WebSocket
 * - Push notifications for offline users
 * - Analytics and engagement tracking
 * - Cross-system message synchronization
 * - Message delivery confirmation
 * - Read receipt management
 * - Spam and abuse detection triggers
 * - AI-powered content analysis
 * - Premium feature notifications
 * - Multi-device synchronization
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Event data
     */
    public readonly string $messageId;
    public readonly string $chatId;
    public readonly UserId $senderId;
    public readonly array $messageData;
    public readonly array $eventOptions;
    public readonly Carbon $timestamp;

    /**
     * Broadcasting configuration
     */
    private array $broadcastChannels = [];
    private array $notificationTargets = [];
    private array $analyticsData = [];

    /**
     * Create a new event instance
     * 
     * @param string $messageId Message unique identifier
     * @param string $chatId Chat identifier
     * @param UserId $senderId User who sent the message
     * @param array $messageData Complete message data
     * @param array $eventOptions Event processing options
     */
    public function __construct(
        string $messageId,
        string $chatId,
        UserId $senderId,
        array $messageData,
        array $eventOptions = []
    ) {
        $this->messageId = $messageId;
        $this->chatId = $chatId;
        $this->senderId = $senderId;
        $this->messageData = $messageData;
        $this->eventOptions = $eventOptions;
        $this->timestamp = Carbon::now();

        // Initialize broadcasting configuration
        $this->initializeBroadcastingConfig();

        // Prepare analytics data
        $this->prepareAnalyticsData();

        Log::info('MessageSent event created', [
            'message_id' => $this->messageId,
            'chat_id' => $this->chatId,
            'sender_id' => $this->senderId->toInt(),
            'message_type' => $this->messageData['type'] ?? 'text'
        ]);
    }

    /**
     * Get the channels the event should broadcast on
     * 
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return $this->broadcastChannels;
    }

    /**
     * Get the data to broadcast
     * 
     * @return array Broadcast payload
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'message.sent',
            'message' => [
                'id' => $this->messageId,
                'chat_id' => $this->chatId,
                'sender_id' => $this->senderId->toInt(),
                'type' => $this->messageData['type'] ?? 'text',
                'content' => $this->getBroadcastContent(),
                'timestamp' => $this->timestamp->toISOString(),
                'metadata' => $this->getBroadcastMetadata()
            ],
            'chat' => [
                'id' => $this->chatId,
                'type' => $this->getChatType(),
                'last_activity' => $this->timestamp->toISOString(),
                'unread_count_updates' => $this->getUnreadCountUpdates()
            ],
            'sender' => [
                'id' => $this->senderId->toInt(),
                'name' => $this->getSenderDisplayName(),
                'avatar' => $this->getSenderAvatar(),
                'online_status' => 'online'
            ],
            'delivery' => [
                'delivered_at' => $this->timestamp->toISOString(),
                'read_receipts_enabled' => $this->areReadReceiptsEnabled(),
                'typing_indicator_clear' => true
            ],
            'features' => [
                'reactions_enabled' => $this->areReactionsEnabled(),
                'replies_enabled' => $this->areRepliesEnabled(),
                'editing_enabled' => $this->isEditingEnabled(),
                'forwarding_enabled' => $this->isForwardingEnabled()
            ]
        ];
    }

    /**
     * Get broadcast event name
     * 
     * @return string Event name
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Determine if the event should be queued
     * 
     * @return bool Whether to queue the broadcast
     */
    public function shouldQueue(): bool
    {
        // Real-time messages should broadcast immediately
        return false;
    }

    /**
     * Get event tags for queue monitoring
     * 
     * @return array Queue tags
     */
    public function tags(): array
    {
        return [
            'chat:' . $this->chatId,
            'sender:' . $this->senderId->toInt(),
            'message:' . $this->messageId,
            'type:' . ($this->messageData['type'] ?? 'text')
        ];
    }

    /**
     * ========================================
     * EVENT DATA GETTERS
     * ========================================
     */

    /**
     * Get message ID
     */
    public function getMessageId(): string
    {
        return $this->messageId;
    }

    /**
     * Get chat ID
     */
    public function getChatId(): string
    {
        return $this->chatId;
    }

    /**
     * Get sender ID
     */
    public function getSenderId(): UserId
    {
        return $this->senderId;
    }

    /**
     * Get complete message data
     */
    public function getMessageData(): array
    {
        return $this->messageData;
    }

    /**
     * Get event options
     */
    public function getEventOptions(): array
    {
        return $this->eventOptions;
    }

    /**
     * Get event timestamp
     */
    public function getTimestamp(): Carbon
    {
        return $this->timestamp;
    }

    /**
     * Get notification targets
     */
    public function getNotificationTargets(): array
    {
        return $this->notificationTargets;
    }

    /**
     * Get analytics data
     */
    public function getAnalyticsData(): array
    {
        return $this->analyticsData;
    }

    /**
     * Check if push notifications should be sent
     */
    public function shouldSendPushNotifications(): bool
    {
        return $this->eventOptions['send_push_notifications'] ?? true;
    }

    /**
     * Check if email notifications should be sent
     */
    public function shouldSendEmailNotifications(): bool
    {
        return $this->eventOptions['send_email_notifications'] ?? false;
    }

    /**
     * Check if analytics tracking is enabled
     */
    public function shouldTrackAnalytics(): bool
    {
        return $this->eventOptions['track_analytics'] ?? true;
    }

    /**
     * Check if content moderation should be triggered
     */
    public function shouldTriggerModeration(): bool
    {
        return $this->eventOptions['trigger_moderation'] ?? true;
    }

    /**
     * Check if AI content analysis should run
     */
    public function shouldRunContentAnalysis(): bool
    {
        return $this->eventOptions['run_content_analysis'] ?? true;
    }

    /**
     * ========================================
     * SPECIALIZED GETTERS
     * ========================================
     */

    /**
     * Get message type (text, image, video, etc.)
     */
    public function getMessageType(): string
    {
        return $this->messageData['type'] ?? 'text';
    }

    /**
     * Get message content (sanitized for broadcasting)
     */
    public function getMessageContent(): string
    {
        return $this->messageData['content'] ?? '';
    }

    /**
     * Get message metadata
     */
    public function getMessageMetadata(): array
    {
        return $this->messageData['metadata'] ?? [];
    }

    /**
     * Check if message is a reply to another message
     */
    public function isReply(): bool
    {
        return !empty($this->messageData['metadata']['reply_to'] ?? null);
    }

    /**
     * Get parent message ID if this is a reply
     */
    public function getReplyToMessageId(): ?string
    {
        return $this->messageData['metadata']['reply_to'] ?? null;
    }

    /**
     * Check if message contains multimedia content
     */
    public function hasMultimediaContent(): bool
    {
        return in_array($this->getMessageType(), ['image', 'video', 'voice', 'file']);
    }

    /**
     * Get multimedia file data if applicable
     */
    public function getFileData(): ?array
    {
        return $this->messageData['file_data'] ?? null;
    }

    /**
     * Check if message contains mentions
     */
    public function hasMentions(): bool
    {
        $mentions = $this->messageData['metadata']['mentions'] ?? [];
        return !empty($mentions);
    }

    /**
     * Get mentioned user IDs
     */
    public function getMentionedUsers(): array
    {
        return $this->messageData['metadata']['mentions'] ?? [];
    }

    /**
     * Check if message contains links
     */
    public function hasLinks(): bool
    {
        $links = $this->messageData['metadata']['links'] ?? [];
        return !empty($links);
    }

    /**
     * Get detected links in message
     */
    public function getLinks(): array
    {
        return $this->messageData['metadata']['links'] ?? [];
    }

    /**
     * ========================================
     * CHAT CONTEXT GETTERS
     * ========================================
     */

    /**
     * Get chat participants (excluding sender)
     */
    public function getChatParticipants(): array
    {
        $allParticipants = $this->messageData['chat_participants'] ?? [];
        return array_filter($allParticipants, fn($id) => $id !== $this->senderId->toInt());
    }

    /**
     * Check if this is a group chat message
     */
    public function isGroupMessage(): bool
    {
        return ($this->messageData['chat_type'] ?? 'private') === 'group';
    }

    /**
     * Check if this is a Happy Hour event message
     */
    public function isHappyHourMessage(): bool
    {
        return ($this->messageData['chat_subtype'] ?? null) === 'happy_hour';
    }

    /**
     * Check if this is a video call related message
     */
    public function isVideoCallMessage(): bool
    {
        return in_array($this->getMessageType(), ['call_start', 'call_end', 'call_invite']);
    }

    /**
     * ========================================
     * PREMIUM FEATURES
     * ========================================
     */

    /**
     * Check if sender has premium status
     */
    public function isSenderPremium(): bool
    {
        return $this->messageData['sender_premium'] ?? false;
    }

    /**
     * Get premium features used in this message
     */
    public function getPremiumFeaturesUsed(): array
    {
        $features = [];

        if ($this->getMessageType() === 'voice' && $this->isSenderPremium()) {
            $features[] = 'voice_messages';
        }

        if ($this->getMessageType() === 'video' && $this->isSenderPremium()) {
            $features[] = 'video_messages';
        }

        if (!empty($this->messageData['file_data']) && $this->isSenderPremium()) {
            $features[] = 'file_sharing';
        }

        return $features;
    }

    /**
     * ========================================
     * PRIVATE HELPER METHODS
     * ========================================
     */

    /**
     * Initialize broadcasting configuration
     */
    private function initializeBroadcastingConfig(): void
    {
        // Main chat channel for real-time messaging
        $this->broadcastChannels[] = new PrivateChannel("chat.{$this->chatId}");

        // Individual user channels for notifications and updates
        $participants = $this->getChatParticipants();
        foreach ($participants as $participantId) {
            $this->broadcastChannels[] = new PrivateChannel("user.{$participantId}.notifications");
            
            // Add to notification targets
            $this->notificationTargets[] = [
                'user_id' => $participantId,
                'channels' => ['push', 'websocket'],
                'priority' => $this->calculateNotificationPriority($participantId),
                'delay' => $this->calculateNotificationDelay($participantId)
            ];
        }

        // Group-specific channels if applicable
        if ($this->isGroupMessage()) {
            $this->broadcastChannels[] = new PresenceChannel("group.{$this->chatId}.presence");
        }

        // Happy Hour event channels if applicable
        if ($this->isHappyHourMessage()) {
            $this->broadcastChannels[] = new Channel("event.{$this->chatId}.activity");
        }

        // Analytics and monitoring channels
        if ($this->shouldTrackAnalytics()) {
            $this->broadcastChannels[] = new Channel('analytics.messages');
        }

        // Admin monitoring for content moderation
        if ($this->shouldTriggerModeration()) {
            $this->broadcastChannels[] = new PrivateChannel('admin.content.moderation');
        }
    }

    /**
     * Prepare analytics data for tracking
     */
    private function prepareAnalyticsData(): void
    {
        $this->analyticsData = [
            'event_type' => 'message_sent',
            'message_id' => $this->messageId,
            'chat_id' => $this->chatId,
            'sender_id' => $this->senderId->toInt(),
            'message_type' => $this->getMessageType(),
            'message_length' => strlen($this->getMessageContent()),
            'has_multimedia' => $this->hasMultimediaContent(),
            'has_mentions' => $this->hasMentions(),
            'has_links' => $this->hasLinks(),
            'is_reply' => $this->isReply(),
            'is_group_message' => $this->isGroupMessage(),
            'is_happy_hour' => $this->isHappyHourMessage(),
            'sender_premium' => $this->isSenderPremium(),
            'premium_features_used' => $this->getPremiumFeaturesUsed(),
            'timestamp' => $this->timestamp->toISOString(),
            'platform' => $this->messageData['metadata']['platform'] ?? 'web',
            'device_info' => $this->messageData['metadata']['device_info'] ?? 'unknown',
            'participant_count' => count($this->getChatParticipants()) + 1, // +1 for sender
            'chat_age_minutes' => $this->calculateChatAge(),
            'user_engagement_score' => $this->calculateUserEngagementScore(),
            'message_sentiment' => $this->analyzeMessageSentiment(),
            'spam_probability' => $this->calculateSpamProbability()
        ];

        // Add location data if available
        if (isset($this->messageData['metadata']['location'])) {
            $this->analyticsData['location_data'] = $this->messageData['metadata']['location'];
        }

        // Add A/B testing data if available
        if (isset($this->eventOptions['ab_test_variant'])) {
            $this->analyticsData['ab_test_variant'] = $this->eventOptions['ab_test_variant'];
        }
    }

    /**
     * Get content safe for broadcasting (with privacy filters)
     */
    private function getBroadcastContent(): string
    {
        $content = $this->getMessageContent();
        
        // Apply content filters for broadcasting
        if ($this->hasMultimediaContent()) {
            return '[' . strtoupper($this->getMessageType()) . ']';
        }

        // Truncate very long messages for broadcast
        if (strlen($content) > 200) {
            $content = substr($content, 0, 197) . '...';
        }

        return $content;
    }

    /**
     * Get metadata safe for broadcasting
     */
    private function getBroadcastMetadata(): array
    {
        $metadata = $this->getMessageMetadata();
        
        // Filter sensitive metadata
        $allowedFields = [
            'platform',
            'reply_to',
            'message_type',
            'edited',
            'edited_at'
        ];

        return array_intersect_key($metadata, array_flip($allowedFields));
    }

    /**
     * Get chat type for broadcasting
     */
    private function getChatType(): string
    {
        return $this->messageData['chat_type'] ?? 'private';
    }

    /**
     * Get unread count updates for each participant
     */
    private function getUnreadCountUpdates(): array
    {
        $updates = [];
        foreach ($this->getChatParticipants() as $participantId) {
            $updates[$participantId] = [
                'increment' => 1,
                'total' => null // Will be calculated by the client or listener
            ];
        }
        return $updates;
    }

    /**
     * Get sender display name for broadcasting
     */
    private function getSenderDisplayName(): string
    {
        return $this->messageData['sender_name'] ?? 'Unknown User';
    }

    /**
     * Get sender avatar for broadcasting
     */
    private function getSenderAvatar(): ?string
    {
        return $this->messageData['sender_avatar'] ?? null;
    }

    /**
     * Check if read receipts are enabled
     */
    private function areReadReceiptsEnabled(): bool
    {
        return $this->messageData['chat_settings']['read_receipts_enabled'] ?? true;
    }

    /**
     * Check if reactions are enabled
     */
    private function areReactionsEnabled(): bool
    {
        return $this->messageData['chat_settings']['reactions_enabled'] ?? true;
    }

    /**
     * Check if replies are enabled
     */
    private function areRepliesEnabled(): bool
    {
        return $this->messageData['chat_settings']['replies_enabled'] ?? true;
    }

    /**
     * Check if editing is enabled for this message
     */
    private function isEditingEnabled(): bool
    {
        return $this->getMessageType() === 'text' && !$this->isGroupMessage();
    }

    /**
     * Check if forwarding is enabled
     */
    private function isForwardingEnabled(): bool
    {
        return $this->messageData['chat_settings']['forwarding_enabled'] ?? true;
    }

    /**
     * Calculate notification priority for a user
     */
    private function calculateNotificationPriority(string $userId): string
    {
        // Higher priority for mentions, direct messages, etc.
        if ($this->hasMentions() && in_array($userId, $this->getMentionedUsers())) {
            return 'high';
        }

        if (!$this->isGroupMessage()) {
            return 'medium';
        }

        return 'normal';
    }

    /**
     * Calculate notification delay for a user
     */
    private function calculateNotificationDelay(string $userId): int
    {
        // Immediate for direct messages and mentions
        if (!$this->isGroupMessage() || 
            ($this->hasMentions() && in_array($userId, $this->getMentionedUsers()))) {
            return 0; // No delay
        }

        // Small delay for group messages to batch notifications
        return 5; // 5 seconds
    }

    /**
     * Calculate how old the chat is in minutes
     */
    private function calculateChatAge(): int
    {
        $createdAt = $this->messageData['chat_created_at'] ?? $this->timestamp;
        return (int) Carbon::parse($createdAt)->diffInMinutes($this->timestamp);
    }

    /**
     * Calculate user engagement score
     */
    private function calculateUserEngagementScore(): float
    {
        // This would be calculated based on user's recent activity
        // For now, return a placeholder
        return $this->messageData['sender_engagement_score'] ?? 0.5;
    }

    /**
     * Analyze message sentiment (basic implementation)
     */
    private function analyzeMessageSentiment(): string
    {
        $content = strtolower($this->getMessageContent());
        
        // Very basic sentiment analysis (would use AI service in production)
        $positiveWords = ['happy', 'great', 'awesome', 'love', 'amazing', '😊', '😍', '🎉'];
        $negativeWords = ['sad', 'angry', 'hate', 'terrible', 'awful', '😢', '😠', '💔'];
        
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            if (strpos($content, $word) !== false) {
                $positiveCount++;
            }
        }
        
        foreach ($negativeWords as $word) {
            if (strpos($content, $word) !== false) {
                $negativeCount++;
            }
        }
        
        if ($positiveCount > $negativeCount) {
            return 'positive';
        } elseif ($negativeCount > $positiveCount) {
            return 'negative';
        }
        
        return 'neutral';
    }

    /**
     * Calculate spam probability (basic implementation)
     */
    private function calculateSpamProbability(): float
    {
        $content = $this->getMessageContent();
        $probability = 0.0;
        
        // Basic spam indicators
        if (strlen($content) > 500) {
            $probability += 0.2; // Very long messages
        }
        
        if (preg_match_all('/https?:\/\//', $content) > 2) {
            $probability += 0.3; // Multiple links
        }
        
        if (preg_match('/[A-Z]{10,}/', $content)) {
            $probability += 0.2; // Excessive caps
        }
        
        if (preg_match('/(.)\1{4,}/', $content)) {
            $probability += 0.1; // Repeated characters
        }
        
        return min(1.0, $probability);
    }
}