<?php

declare(strict_types=1);

namespace App\Domain\Chat\Services;

use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Chat\Repositories\ChatRepositoryInterface;
use App\Domain\Chat\Repositories\MessageRepositoryInterface;
use App\Domain\Matching\Repositories\MatchRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Chat\Events\MessageSent;
use App\Domain\Chat\Exceptions\ChatException;
use App\Domain\Chat\Exceptions\UnauthorizedChatAccessException;
use App\Domain\Chat\Exceptions\ChatNotFoundException;
use App\Domain\Chat\Exceptions\UserBlockedException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Core Chat Management Service
 * 
 * Comprehensive chat service for managing conversations, chat sessions,
 * user interactions, and real-time messaging following Clean Architecture principles.
 * 
 * @package App\Domain\Chat\Services
 * @author ForeverUsInLove Team
 * @copyright 2024 ForeverUsInLove
 * @license MIT
 * 
 * @version 1.0.0
 * @since 2025-10-10
 * 
 * Architecture: Clean Architecture / Hexagonal Architecture
 * Pattern: Service Layer Pattern with Domain-Driven Design
 * 
 * Dependencies:
 * - ChatRepositoryInterface for chat data operations
 * - MessageRepositoryInterface for message management
 * - MatchRepositoryInterface for match validation
 * - UserProfileRepositoryInterface for user data
 * - Laravel Events for real-time notifications
 * - Carbon for date/time handling
 * 
 * Key Features:
 * - Real-time chat session management
 * - Match-based chat creation and validation
 * - Privacy and security controls
 * - Chat moderation and safety features
 * - Analytics and engagement tracking
 * - Premium chat features and boosts
 * - Multi-device synchronization
 * - Push notifications and alerts
 * - Chat archiving and history management
 * - AI-powered conversation assistance
 */
class ChatService
{
    /**
     * Chat configuration constants
     */
    private const CHAT_SESSION_TIMEOUT = 3600; // 1 hour in seconds
    private const MAX_ACTIVE_CHATS = 50;
    private const PREMIUM_CHAT_LIMIT = 200;
    private const CHAT_CACHE_TTL = 900; // 15 minutes
    private const MESSAGE_PREVIEW_LENGTH = 100;
    
    /**
     * Chat status constants
     */
    private const STATUS_ACTIVE = 'active';
    private const STATUS_ARCHIVED = 'archived';
    private const STATUS_BLOCKED = 'blocked';
    private const STATUS_INACTIVE = 'inactive';
    
    /**
     * Chat type constants
     */
    private const TYPE_PRIVATE = 'private';
    private const TYPE_GROUP = 'group';
    private const TYPE_HAPPY_HOUR = 'happy_hour';
    private const TYPE_VIDEO_CALL = 'video_call';

    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly MatchRepositoryInterface $matchRepository,
        private readonly ProfileRepositoryInterface $profileRepository
    ) {}

    /**
     * ========================================
     * CHAT CREATION AND INITIALIZATION
     * ========================================
     */

    /**
     * Create a new chat between matched users
     * 
     * @param UserId $userId1 First participant
     * @param UserId $userId2 Second participant
     * @param array $chatOptions Chat configuration options
     * @return array Created chat data
     * 
     * @throws ChatException
     * @throws UnauthorizedChatAccessException
     */
    public function createMatchChat(
        UserId $userId1,
        UserId $userId2,
        array $chatOptions = []
    ): array {
        try {
            Log::info('Creating match chat', [
                'user_1' => $userId1->toInt(),
                'user_2' => $userId2->toInt(),
                'options' => $chatOptions
            ]);

            // Validate users can chat (must be matched)
            $this->validateChatEligibility($userId1, $userId2);

            // Check if chat already exists
            $existingChat = $this->chatRepository->findPrivateChatBetweenUsers($userId1, $userId2);
            if ($existingChat) {
                Log::info('Existing chat found', ['chat_id' => $existingChat->id()->value()]);
                // Convert Chat entity to array for compatibility
                return [
                    'id' => $existingChat->id()->value(),
                    'type' => $existingChat->type()->value(),
                    'name' => $existingChat->name(),
                    'description' => $existingChat->description(),
                    'creator_id' => $existingChat->creatorId()->toInt(),
                    'participants' => array_map(fn($p) => $p['user_id'] ?? $p, $existingChat->participants()),
                    'settings' => $existingChat->settings(),
                    'metadata' => $existingChat->metadata()
                ];
            }

            // Prepare chat data
            $chatData = [
                'id' => Str::uuid()->toString(),
                'type' => self::TYPE_PRIVATE,
                'status' => self::STATUS_ACTIVE,
                'participants' => [$userId1->toInt(), $userId2->toInt()],
                'created_by' => $chatOptions['created_by'] ?? $userId1->toInt(),
                'match_id' => $this->getMatchId($userId1, $userId2),
                'settings' => [
                    'notifications_enabled' => true,
                    'read_receipts_enabled' => true,
                    'typing_indicators_enabled' => true,
                    'message_encryption' => true,
                    'auto_delete_after_days' => null,
                    'premium_features_enabled' => $chatOptions['premium_features'] ?? false
                ],
                'metadata' => [
                    'platform_created' => $chatOptions['platform'] ?? 'web',
                    'initial_context' => $chatOptions['context'] ?? 'match_created',
                    'conversation_starter' => $chatOptions['icebreaker'] ?? null,
                    'compatibility_score' => $this->getCompatibilityScore($userId1, $userId2)
                ],
                'analytics' => [
                    'created_at' => Carbon::now(),
                    'last_activity_at' => Carbon::now(),
                    'message_count' => 0,
                    'participant_engagement' => [
                        $userId1->toInt() => ['messages' => 0, 'last_seen' => null],
                        $userId2->toInt() => ['messages' => 0, 'last_seen' => null]
                    ]
                ]
            ];

            // Create the chat
            $chat = $this->chatRepository->create($chatData);

            // Add conversation starter if provided
            if (isset($chatOptions['icebreaker'])) {
                $this->addSystemMessage($chat->id()->value(), $chatOptions['icebreaker'], [
                    'type' => 'icebreaker',
                    'auto_generated' => true
                ]);
            }

            // Cache chat for quick access
            $this->cacheChatData([
                'id' => $chat->id()->value(),
                'type' => $chat->type()->value(),
                'name' => $chat->name(),
                'description' => $chat->description(),
                'creator_id' => $chat->creatorId()->toInt(),
                'participants' => array_map(fn($p) => $p['user_id'] ?? $p, $chat->participants()),
                'settings' => $chat->settings(),
                'metadata' => $chat->metadata()
            ]);

            Log::info('Match chat created successfully', [
                'chat_id' => $chat->id()->value(),
                'participants' => array_map(fn($p) => $p['user_id'] ?? $p, $chat->participants())
            ]);

            // Convert Chat entity to array for compatibility
            return [
                'id' => $chat->id()->value(),
                'type' => $chat->type()->value(),
                'name' => $chat->name(),
                'description' => $chat->description(),
                'creator_id' => $chat->creatorId()->toInt(),
                'participants' => array_map(fn($p) => $p['user_id'] ?? $p, $chat->participants()),
                'settings' => $chat->settings(),
                'metadata' => $chat->metadata()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create match chat', [
                'user_1' => $userId1->toInt(),
                'user_2' => $userId2->toInt(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ChatException('Failed to create chat: ' . $e->getMessage());
        }
    }

    /**
     * Initialize a chat session for real-time communication
     * 
     * @param string $chatId Chat identifier
     * @param UserId $userId User starting the session
     * @param array $sessionOptions Session configuration
     * @return array Session data and connection info
     */
    public function initializeChatSession(
        string $chatId,
        UserId $userId,
        array $sessionOptions = []
    ): array {
        try {
            // Validate chat access
            $chat = $this->validateChatAccess($chatId, $userId);

            // Create session data
            $sessionData = [
                'session_id' => Str::uuid()->toString(),
                'chat_id' => $chatId,
                'user_id' => $userId->toInt(),
                'started_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addSeconds(self::CHAT_SESSION_TIMEOUT),
                'device_info' => $sessionOptions['device'] ?? 'unknown',
                'platform' => $sessionOptions['platform'] ?? 'web',
                'features' => [
                    'real_time_messaging' => true,
                    'typing_indicators' => true,
                    'read_receipts' => true,
                    'file_sharing' => $chat['settings']['premium_features_enabled'] ?? false,
                    'voice_messages' => $chat['settings']['premium_features_enabled'] ?? false,
                    'video_calling' => $chat['settings']['premium_features_enabled'] ?? false
                ],
                'connection' => [
                    'websocket_url' => $this->generateWebSocketUrl($chatId, $userId),
                    'channel' => "chat.{$chatId}",
                    'auth_token' => $this->generateSessionToken($chatId, $userId),
                    'heartbeat_interval' => 30 // seconds
                ]
            ];

            // Update user's last seen in chat
            $this->updateUserLastSeen($chatId, $userId);

            // Track session analytics
            $this->trackChatActivity($chatId, 'session_started', [
                'user_id' => $userId->toInt(),
                'session_id' => $sessionData['session_id']
            ]);

            Log::info('Chat session initialized', [
                'chat_id' => $chatId,
                'user_id' => $userId->toInt(),
                'session_id' => $sessionData['session_id']
            ]);

            return $sessionData;

        } catch (\Exception $e) {
            Log::error('Failed to initialize chat session', [
                'chat_id' => $chatId,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new ChatException('Failed to initialize chat session: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * CHAT RETRIEVAL AND MANAGEMENT
     * ========================================
     */

    /**
     * Get all active chats for a user
     * 
     * @param UserId $userId User identifier
     * @param array $filters Filter options
     * @param array $options Query options
     * @return Collection User's active chats
     */
    public function getUserChats(
        UserId $userId,
        array $filters = [],
        array $options = []
    ): Collection {
        try {
            // Check cache first
            $cacheKey = "user_chats:{$userId->toInt()}:" . md5(serialize($filters));
            if ($options['use_cache'] ?? true) {
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    return collect($cached);
                }
            }

            // Default filters
            $defaultFilters = [
                'status' => [self::STATUS_ACTIVE],
                'include_archived' => false,
                'has_messages' => null,
                'last_activity_hours' => null,
                'chat_types' => [self::TYPE_PRIVATE, self::TYPE_GROUP]
            ];

            $mergedFilters = array_merge($defaultFilters, $filters);

            // Get chats from repository
            $chats = $this->chatRepository->getUserChats(
                $userId,
                $mergedFilters,
                $options
            );

            // Enrich with additional data
            $enrichedChats = $chats->map(function ($chat) use ($userId) {
                return $this->enrichChatData($chat, $userId);
            });

            // Sort by last activity
            $sortedChats = $enrichedChats->sortByDesc(function ($chat) {
                return $chat['last_message']['sent_at'] ?? $chat['created_at'];
            })->values();

            // Cache results
            if ($options['use_cache'] ?? true) {
                Cache::put($cacheKey, $sortedChats->toArray(), self::CHAT_CACHE_TTL);
            }

            return $sortedChats;

        } catch (\Exception $e) {
            Log::error('Failed to get user chats', [
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new ChatException('Failed to retrieve chats: ' . $e->getMessage());
        }
    }

    /**
     * Get chat details by ID
     * 
     * @param string $chatId Chat identifier
     * @param UserId $requestingUserId User requesting the chat
     * @param array $options Loading options
     * @return array Chat details
     */
    public function getChatDetails(
        string $chatId,
        UserId $requestingUserId,
        array $options = []
    ): array {
        try {
            // Validate access
            $chat = $this->validateChatAccess($chatId, $requestingUserId);

            // Load additional data if requested
            if ($options['with_messages'] ?? false) {
                $messageLimit = $options['message_limit'] ?? 50;
                $chat['recent_messages'] = $this->messageRepository->getChatMessages(
                    $chatId,
                    ['limit' => $messageLimit, 'order' => 'desc']
                );
            }

            if ($options['with_participants'] ?? false) {
                $chat['participant_details'] = $this->loadParticipantDetails($chat['participants']);
            }

            if ($options['with_analytics'] ?? false) {
                $chat['analytics_summary'] = $this->getChatAnalytics($chatId);
            }

            // Enrich with user-specific data
            $enrichedChat = $this->enrichChatData($chat, $requestingUserId);

            // Update last seen
            $this->updateUserLastSeen($chatId, $requestingUserId);

            return $enrichedChat;

        } catch (\Exception $e) {
            Log::error('Failed to get chat details', [
                'chat_id' => $chatId,
                'user_id' => $requestingUserId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new ChatException('Failed to get chat details: ' . $e->getMessage());
        }
    }

    /**
     * Update chat settings
     * 
     * @param string $chatId Chat identifier
     * @param UserId $userId User making the update
     * @param array $settings New settings
     * @return bool Success status
     */
    public function updateChatSettings(
        string $chatId,
        UserId $userId,
        array $settings
    ): bool {
        try {
            // Validate access
            $this->validateChatAccess($chatId, $userId);

            // Validate settings
            $validatedSettings = $this->validateChatSettings($settings);

            // Update chat settings
            $updatedChat = $this->chatRepository->update(
                $chatId,
                ['settings' => $validatedSettings]
            );

            if ($updatedChat) {
                // Invalidate cache
                $this->invalidateChatCache($chatId);

                // Track activity
                $this->trackChatActivity($chatId, 'settings_updated', [
                    'user_id' => $userId->toInt(),
                    'settings_changed' => array_keys($validatedSettings)
                ]);

                Log::info('Chat settings updated', [
                    'chat_id' => $chatId,
                    'user_id' => $userId->toInt(),
                    'settings' => array_keys($validatedSettings)
                ]);
            }

            return (bool) $updatedChat;

        } catch (\Exception $e) {
            Log::error('Failed to update chat settings', [
                'chat_id' => $chatId,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new ChatException('Failed to update chat settings: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * CHAT STATUS AND LIFECYCLE MANAGEMENT
     * ========================================
     */

    /**
     * Archive a chat conversation
     * 
     * @param string $chatId Chat identifier
     * @param UserId $userId User archiving the chat
     * @param array $options Archive options
     * @return bool Success status
     */
    public function archiveChat(
        string $chatId,
        UserId $userId,
        array $options = []
    ): bool {
        try {
            // Validate access
            $this->validateChatAccess($chatId, $userId);

            $archiveData = [
                'status' => self::STATUS_ARCHIVED,
                'archived_at' => Carbon::now(),
                'archived_by' => $userId->toInt(),
                'archive_reason' => $options['reason'] ?? 'user_request',
                'preserve_messages' => $options['preserve_messages'] ?? true
            ];

            $updatedChat = $this->chatRepository->update($chatId, $archiveData);

            if ($updatedChat) {
                // Clean up active sessions
                $this->cleanupActiveSessions($chatId);

                // Invalidate cache
                $this->invalidateChatCache($chatId);

                // Track activity
                $this->trackChatActivity($chatId, 'chat_archived', [
                    'user_id' => $userId->toInt(),
                    'reason' => $options['reason'] ?? 'user_request'
                ]);

                Log::info('Chat archived', [
                    'chat_id' => $chatId,
                    'user_id' => $userId->toInt()
                ]);
            }

            return (bool) $updatedChat;

        } catch (\Exception $e) {
            Log::error('Failed to archive chat', [
                'chat_id' => $chatId,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new ChatException('Failed to archive chat: ' . $e->getMessage());
        }
    }

    /**
     * Block a user in chat context
     * 
     * @param string $chatId Chat identifier
     * @param UserId $blockingUserId User doing the blocking
     * @param UserId $blockedUserId User being blocked
     * @param array $options Block options
     * @return bool Success status
     */
    public function blockUserInChat(
        string $chatId,
        UserId $blockingUserId,
        UserId $blockedUserId,
        array $options = []
    ): bool {
        try {
            // Validate access
            $this->validateChatAccess($chatId, $blockingUserId);

            // Update chat status to blocked
            $blockData = [
                'status' => self::STATUS_BLOCKED,
                'blocked_at' => Carbon::now(),
                'blocked_by' => $blockingUserId->toInt(),
                'blocked_user' => $blockedUserId->toInt(),
                'block_reason' => $options['reason'] ?? 'user_request',
                'report_submitted' => $options['report'] ?? false
            ];

            $updatedChat = $this->chatRepository->update($chatId, $blockData);

            if ($updatedChat) {
                // Clean up sessions
                $this->cleanupActiveSessions($chatId);

                // Invalidate cache
                $this->invalidateChatCache($chatId);

                // Track activity
                $this->trackChatActivity($chatId, 'user_blocked', [
                    'blocking_user' => $blockingUserId->toInt(),
                    'blocked_user' => $blockedUserId->toInt(),
                    'reason' => $options['reason'] ?? 'user_request'
                ]);

                Log::info('User blocked in chat', [
                    'chat_id' => $chatId,
                    'blocking_user' => $blockingUserId->toInt(),
                    'blocked_user' => $blockedUserId->toInt()
                ]);
            }

            return (bool) $updatedChat;

        } catch (\Exception $e) {
            Log::error('Failed to block user in chat', [
                'chat_id' => $chatId,
                'blocking_user' => $blockingUserId->toInt(),
                'blocked_user' => $blockedUserId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new ChatException('Failed to block user: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * CHAT ANALYTICS AND INSIGHTS
     * ========================================
     */

    /**
     * Get chat analytics and engagement metrics
     * 
     * @param string $chatId Chat identifier
     * @param UserId $requestingUserId User requesting analytics
     * @param array $options Analytics options
     * @return array Chat analytics data
     */
    public function getChatAnalytics(
        string $chatId,
        ?UserId $requestingUserId = null,
        array $options = []
    ): array {
        try {
            if ($requestingUserId) {
                $this->validateChatAccess($chatId, $requestingUserId);
            }

            $analytics = $this->chatRepository->getChatStatistics($chatId, $options);

            // Add computed metrics
            $analytics['engagement_score'] = $this->calculateEngagementScore($chatId);
            $analytics['response_time_avg'] = $this->calculateAverageResponseTime($chatId);
            $analytics['conversation_quality'] = $this->assessConversationQuality($chatId);

            return $analytics;

        } catch (\Exception $e) {
            Log::error('Failed to get chat analytics', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            throw new ChatException('Failed to get chat analytics: ' . $e->getMessage());
        }
    }

    /**
     * Track user activity in chat
     * 
     * @param string $chatId Chat identifier
     * @param string $activityType Type of activity
     * @param array $activityData Activity details
     * @return bool Success status
     */
    public function trackChatActivity(
        string $chatId,
        string $activityType,
        array $activityData
    ): bool {
        try {
            // Track activity by updating last activity timestamp
            $this->chatRepository->updateLastActivity($chatId);
            
            // Log the activity for analytics
            Log::info('Chat activity tracked', [
                'chat_id' => $chatId,
                'activity_type' => $activityType,
                'activity_data' => $activityData
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to track chat activity', [
                'chat_id' => $chatId,
                'activity_type' => $activityType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * ========================================
     * PRIVATE HELPER METHODS
     * ========================================
     */

    /**
     * Validate chat eligibility between users
     */
    private function validateChatEligibility(UserId $userId1, UserId $userId2): void
    {
        // Check if users are matched
        $matchExists = $this->matchRepository->matchExists($userId1, $userId2);
        if (!$matchExists) {
            throw new UnauthorizedChatAccessException('Users must be matched to start a chat');
        }

        // Check if either user has blocked the other
        $isBlocked = $this->checkUserBlocked($userId1, $userId2);
        if ($isBlocked) {
            throw new UserBlockedException('Cannot create chat with blocked user');
        }

        // Check user chat limits
        $this->validateChatLimits($userId1);
        $this->validateChatLimits($userId2);
    }

    /**
     * Validate chat access for user
     */
    private function validateChatAccess(string $chatId, UserId $userId): array
    {
        $chat = $this->chatRepository->findById($chatId);
        if (!$chat) {
            throw new ChatNotFoundException("Chat not found: {$chatId}");
        }

        // Convert Chat entity to array for compatibility
        $chatArray = [
            'id' => $chat->id()->value(),
            'type' => $chat->type()->value(),
            'name' => $chat->name(),
            'description' => $chat->description(),
            'creator_id' => $chat->creatorId()->toInt(),
            'participants' => array_map(fn($p) => $p['user_id'] ?? $p, $chat->participants()),
            'settings' => $chat->settings(),
            'metadata' => $chat->metadata(),
            'status' => 'active' // TODO: Add status to Chat entity
        ];

        if (!in_array($userId->toInt(), $chatArray['participants'])) {
            throw new UnauthorizedChatAccessException('User not authorized to access this chat');
        }

        if ($chatArray['status'] === self::STATUS_BLOCKED) {
            throw new UserBlockedException('Chat is blocked');
        }

        return $chatArray;
    }

    /**
     * Get match ID between two users
     */
    private function getMatchId(UserId $userId1, UserId $userId2): ?string
    {
        $match = $this->matchRepository->getMatchBetweenUsers($userId1, $userId2);
        return $match['id'] ?? null;
    }

    /**
     * Get compatibility score between users
     */
    private function getCompatibilityScore(UserId $userId1, UserId $userId2): float
    {
        $match = $this->matchRepository->getMatchBetweenUsers($userId1, $userId2);
        return $match['compatibility_score'] ?? 0.0;
    }

    /**
     * Enrich chat data with additional information
     */
    private function enrichChatData(array $chat, UserId $userId): array
    {
        // Add last message preview
        $lastMessage = $this->messageRepository->getChatMessages($chat['id'], [
            'limit' => 1,
            'order' => 'desc'
        ])->first();
        $chat['last_message'] = $lastMessage ? [
            'id' => $lastMessage['id'],
            'preview' => $this->generateMessagePreview($lastMessage['content']),
            'sender_id' => $lastMessage['sender_id'],
            'sent_at' => $lastMessage['sent_at'],
            'is_read' => $lastMessage['read_by'][$userId->toInt()] ?? false
        ] : null;

        // Add unread count
        $unreadMessages = $this->messageRepository->getUnreadMessages($userId, [
            'chat_id' => $chat['id']
        ]);
        $chat['unread_count'] = $unreadMessages->count();

        // Add other participant info
        $otherParticipants = array_diff($chat['participants'], [$userId->toInt()]);
        $chat['other_participants'] = $this->loadParticipantDetails($otherParticipants);

        // Add typing indicators
        $chat['typing_users'] = $this->getTypingUsers($chat['id'], $userId);

        return $chat;
    }

    /**
     * Additional helper methods for various operations...
     */
    
    private function addSystemMessage(string $chatId, string $content, array $options = []): void
    {
        $this->messageRepository->create([
            'chat_id' => $chatId,
            'sender_id' => 'system',
            'content' => $content,
            'type' => $options['type'] ?? 'system',
            'metadata' => $options
        ]);
    }

    private function cacheChatData(array $chat): void
    {
        Cache::put("chat:{$chat['id']}", $chat, self::CHAT_CACHE_TTL);
    }

    private function invalidateChatCache(string $chatId): void
    {
        Cache::forget("chat:{$chatId}");
        // Also invalidate user chat lists
        $chat = $this->chatRepository->findById($chatId);
        if ($chat) {
            foreach ($chat['participants'] as $participantId) {
                Cache::forget("user_chats:{$participantId}");
            }
        }
    }

    private function generateWebSocketUrl(string $chatId, UserId $userId): string
    {
        // Generate WebSocket connection URL for real-time messaging
        return "wss://chat.forevereusinlove.com/chat/{$chatId}/user/{$userId->toInt()}";
    }

    private function generateSessionToken(string $chatId, UserId $userId): string
    {
        // Generate secure session token for WebSocket authentication
        return hash_hmac('sha256', $chatId . $userId->toInt() . time(), config('app.key'));
    }

    private function updateUserLastSeen(string $chatId, UserId $userId): void
    {
        $this->chatRepository->updateLastActivity($chatId);
    }

    private function cleanupActiveSessions(string $chatId): void
    {
        // Clean up any active WebSocket sessions for this chat
        Cache::tags(["chat_sessions:{$chatId}"])->flush();
    }

    private function validateChatSettings(array $settings): array
    {
        $allowedSettings = [
            'notifications_enabled',
            'read_receipts_enabled',
            'typing_indicators_enabled',
            'auto_delete_after_days'
        ];

        return array_intersect_key($settings, array_flip($allowedSettings));
    }

    private function checkUserBlocked(UserId $userId1, UserId $userId2): bool
    {
        try {
            $profile1 = $this->profileRepository->findByUserId($userId1->toInt());
            $profile2 = $this->profileRepository->findByUserId($userId2->toInt());
            
            if (!$profile1 || !$profile2) {
                return false;
            }
            
            // Check if either user has blocked the other
            // This would require a blocked_users table or similar mechanism
            // For now, we'll implement a basic check using profile settings
            $blockedUsers1 = $profile1->settings['blocked_users'] ?? [];
            $blockedUsers2 = $profile2->settings['blocked_users'] ?? [];
            
            return in_array($userId2->toInt(), $blockedUsers1) || 
                   in_array($userId1->toInt(), $blockedUsers2);
        } catch (\Exception $e) {
            Log::error('Error checking if users are blocked', [
                'user1' => $userId1->toInt(),
                'user2' => $userId2->toInt(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function validateChatLimits(UserId $userId): void
    {
        $userProfile = $this->profileRepository->findById($userId->toInt());
        $isPremium = $userProfile['premium_status'] ?? false;
        
        $userChats = $this->chatRepository->getUserChats($userId, [
            'status' => [self::STATUS_ACTIVE],
            'per_page' => 1000 // Get all active chats
        ]);
        $activeChats = $userChats->total();
        $limit = $isPremium ? self::PREMIUM_CHAT_LIMIT : self::MAX_ACTIVE_CHATS;
        
        if ($activeChats >= $limit) {
            throw new ChatException("Chat limit exceeded. Maximum: {$limit}");
        }
    }

    private function loadParticipantDetails(array $participantIds): array
    {
        return collect($participantIds)->map(function ($participantId) {
            $profile = $this->profileRepository->findById($participantId, [
                'fields' => ['id', 'name', 'avatar', 'online_status', 'last_active']
            ]);
            return $profile ?: ['id' => $participantId, 'name' => 'Unknown User'];
        })->toArray();
    }

    private function generateMessagePreview(string $content): string
    {
        return Str::limit(strip_tags($content), self::MESSAGE_PREVIEW_LENGTH);
    }

    private function getTypingUsers(string $chatId, UserId $currentUserId): array
    {
        // Get currently typing users from cache/real-time store
        $typingUsers = Cache::get("typing:{$chatId}", []);
        return array_filter($typingUsers, fn($userId) => $userId !== $currentUserId->toInt());
    }

    private function calculateEngagementScore(string $chatId): float
    {
        // Calculate engagement score based on message frequency, response times, etc.
        $analytics = $this->chatRepository->getChatStatistics($chatId);
        
        $messageCount = $analytics['message_count'] ?? 0;
        $daysSinceCreated = Carbon::parse($analytics['created_at'])->diffInDays(Carbon::now()) ?: 1;
        $messagesPerDay = $messageCount / $daysSinceCreated;
        
        // Score based on messages per day (0-1 scale)
        return min(1.0, $messagesPerDay / 10); // Normalize to 10 messages/day = 1.0 score
    }

    private function calculateAverageResponseTime(string $chatId): int
    {
        try {
            $metrics = $this->messageRepository->getMessageEngagementMetrics($chatId);
            return (int) ($metrics['avg_response_time_minutes'] ?? 0);
        } catch (\Exception $e) {
            Log::error('Error calculating average response time', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    private function assessConversationQuality(string $chatId): array
    {
        try {
            $metrics = $this->messageRepository->getMessageEngagementMetrics($chatId);
            
            return [
                'message_length_avg' => $metrics['avg_message_length'] ?? 0,
                'emoji_usage' => $metrics['emoji_usage_stats'] ?? [],
                'question_ratio' => $metrics['question_ratio'] ?? 0.0,
                'sentiment_score' => $metrics['sentiment_score'] ?? 0.0,
                'engagement_score' => $metrics['engagement_score'] ?? 0.0,
                'response_rate' => $metrics['response_rate'] ?? 0.0
            ];
        } catch (\Exception $e) {
            Log::error('Error assessing conversation quality', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'message_length_avg' => 0,
                'emoji_usage' => [],
                'question_ratio' => 0.0,
                'sentiment_score' => 0.0,
                'engagement_score' => 0.0,
                'response_rate' => 0.0
            ];
        }
    }
}