<?php

declare(strict_types=1);

namespace App\Domain\Matching\Events;

use App\Domain\Matching\Entities\SuperLike;
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
 * SuperLikeReceived Event
 * 
 * Domain event triggered when a user receives a super like from another user
 * in the ForeverUsInLove dating platform. This event handles enhanced
 * notifications and special handling for premium super like interactions.
 * 
 * @package ForeverUsInLove\Domain\Matching\Events
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove
 * @license Proprietary
 * @version 1.0.0
 * @since 2025-10-10
 */
class SuperLikeReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var SuperLike The super like entity that was created
     */
    public readonly SuperLike $superLike;

    /**
     * @var bool Whether this super like created a mutual match
     */
    public readonly bool $isMutualMatch;

    /**
     * @var array<string, mixed> Enhanced notification data for super likes
     */
    public readonly array $notificationData;

    /**
     * Create a new SuperLikeReceived event instance
     * 
     * @param SuperLike $superLike The super like that was created
     * @param bool $isMutualMatch Whether this created a mutual match
     * @param array<string, mixed> $notificationData Enhanced notification data
     */
    public function __construct(
        SuperLike $superLike,
        bool $isMutualMatch = false,
        array $notificationData = []
    ) {
        $this->superLike = $superLike;
        $this->isMutualMatch = $isMutualMatch;
        $this->notificationData = $notificationData;

        // Set socket connection context for real-time broadcasting
        $this->socket = $notificationData['socket_id'] ?? null;
    }

    /**
     * Get the channels the event should broadcast on
     * 
     * @return array<Channel|string> Array of broadcasting channels
     */
    public function broadcastOn(): array
    {
        $channels = [
            // Private channel to the liked user for immediate notification
            new PrivateChannel("user.{$this->superLike->getLikedId()->toString()}"),
            
            // Premium super likes channel
            new PrivateChannel('premium.super-likes'),
            
            // Analytics channel for engagement tracking
            new PrivateChannel('analytics.super-likes')
        ];

        // Add mutual match celebration channels
        if ($this->isMutualMatch) {
            $channels[] = new PrivateChannel('celebrations.mutual-super-likes');
            $channels[] = new PrivateChannel("user.{$this->superLike->getLikerId()->toString()}");
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
            return 'mutual-super-like.created';
        }

        return 'super-like.received';
    }

    /**
     * Get the data to broadcast with the event
     * 
     * @return array<string, mixed> Broadcast payload data
     */
    public function broadcastWith(): array
    {
        return [
            'super_like' => [
                'id' => $this->superLike->getId()->toString(),
                'liker_id' => $this->superLike->getLikerId()->toString(),
                'liked_id' => $this->superLike->getLikedId()->toString(),
                'source' => $this->superLike->getSource()->getValue(),
                'created_at' => $this->superLike->getCreatedAt()->toISOString(),
                'has_message' => !empty($this->superLike->getMessage()),
                'message_preview' => $this->getMessagePreview(),
                'is_mutual_match' => $this->isMutualMatch,
                'cost' => $this->superLike->getCost(),
                'visibility_multiplier' => $this->superLike->getVisibilityMultiplier()
            ],
            'notification' => [
                'title' => $this->getNotificationTitle(),
                'message' => $this->getNotificationMessage(),
                'priority' => 'high',
                'celebration_level' => $this->getCelebrationLevel(),
                'should_show_immediately' => true
            ],
            'metadata' => [
                'event_id' => $this->generateEventId(),
                'timestamp' => Carbon::now()->toISOString(),
                'version' => '1.0.0',
                'source' => 'like_service'
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
        // Always broadcast super likes immediately
        return true;
    }

    /**
     * Get the queue connection for broadcasting
     * 
     * @return string|null Queue connection name
     */
    public function broadcastQueue(): ?string
    {
        return 'high-priority';
    }

    /**
     * Get message preview (truncated for notifications)
     */
    private function getMessagePreview(): ?string
    {
        $message = $this->superLike->getMessage();
        if (!$message) {
            return null;
        }

        return strlen($message) > 50 ? substr($message, 0, 47) . '...' : $message;
    }

    /**
     * Get notification title
     */
    private function getNotificationTitle(): string
    {
        return $this->notificationData['title'] ?? '⭐ Super Like Received!';
    }

    /**
     * Get notification message
     */
    private function getNotificationMessage(): string
    {
        return $this->notificationData['message'] ?? 'Someone super liked you!';
    }

    /**
     * Get celebration level
     */
    private function getCelebrationLevel(): string
    {
        if ($this->isMutualMatch) {
            return 'spectacular';
        }

        return 'elevated';
    }

    /**
     * Generate unique event ID for tracking
     */
    private function generateEventId(): string
    {
        return 'super_like_received_' . $this->superLike->getId()->toString() . '_' . Carbon::now()->timestamp;
    }
}
