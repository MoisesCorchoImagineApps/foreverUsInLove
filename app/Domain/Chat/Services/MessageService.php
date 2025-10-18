<?php

declare(strict_types=1);

namespace App\Domain\Chat\Services;

use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Chat\Repositories\ChatRepositoryInterface;
use App\Domain\Chat\Repositories\MessageRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Chat\Events\MessageSent;
use App\Domain\Chat\Exceptions\MessageException;
use App\Domain\Chat\Exceptions\UnauthorizedChatAccessException;
use App\Domain\Chat\Exceptions\MessageNotFoundException;
use App\Domain\Chat\Exceptions\MessageValidationException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Message Handling and Delivery Service
 * 
 * Comprehensive service for message management, delivery, real-time processing,
 * content moderation, and multimedia message handling following Clean Architecture principles.
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
 * - MessageRepositoryInterface for message data operations
 * - ChatRepositoryInterface for chat validation and updates
 * - ProfileRepositoryInterface for user data and permissions
 * - Laravel Events for real-time message broadcasting
 * - Carbon for date/time handling
 * 
 * Key Features:
 * - Real-time message sending and delivery
 * - Multimedia message support (text, images, voice, video)
 * - Message threading and replies
 * - Read receipts and delivery status
 * - Message editing and deletion
 * - Content moderation and safety filters
 * - Message encryption and security
 * - Typing indicators and presence
 * - Message search and filtering
 * - Analytics and engagement tracking
 * - Spam and abuse prevention
 * - Message reactions and interactions
 */
class MessageService
{
    /**
     * Message type constants
     */
    private const TYPE_TEXT = 'text';
    private const TYPE_IMAGE = 'image';
    private const TYPE_VIDEO = 'video';
    private const TYPE_VOICE = 'voice';
    private const TYPE_FILE = 'file';
    private const TYPE_STICKER = 'sticker';
    private const TYPE_GIF = 'gif';
    private const TYPE_SYSTEM = 'system';
    private const TYPE_ICEBREAKER = 'icebreaker';

    /**
     * Message status constants
     */
    private const STATUS_SENDING = 'sending';
    private const STATUS_SENT = 'sent';
    private const STATUS_DELIVERED = 'delivered';
    private const STATUS_READ = 'read';
    private const STATUS_FAILED = 'failed';

    /**
     * Content limits and restrictions
     */
    private const MAX_TEXT_LENGTH = 2000;
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
    private const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
    private const MAX_VIDEO_SIZE = 100 * 1024 * 1024; // 100MB
    private const MAX_VOICE_DURATION = 300; // 5 minutes in seconds
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Rate limiting constants
     */
    private const RATE_LIMIT_MESSAGES_PER_MINUTE = 30;
    private const RATE_LIMIT_MESSAGES_PER_HOUR = 500;
    private const PREMIUM_RATE_MULTIPLIER = 2;

    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly ProfileRepositoryInterface $profileRepository
    ) {}

    /**
     * ========================================
     * MESSAGE SENDING AND CREATION
     * ========================================
     */

    /**
     * Send a text message
     * 
     * @param string $chatId Chat identifier
     * @param UserId $senderId Sender user ID
     * @param string $content Message content
     * @param array $options Message options
     * @return array Created message data
     * 
     * @throws MessageException
     * @throws UnauthorizedChatAccessException
     */
    public function sendTextMessage(
        string $chatId,
        UserId $senderId,
        string $content,
        array $options = []
    ): array {
        try {
            Log::info('Sending text message', [
                'chat_id' => $chatId,
                'sender_id' => $senderId->toInt(),
                'content_length' => strlen($content)
            ]);

            // Validate chat access and rate limits
            $this->validateMessageSending($chatId, $senderId);
            
            // Validate and sanitize content
            $sanitizedContent = $this->validateAndSanitizeTextContent($content);
            
            // Check content moderation
            $moderationResult = $this->moderateContent($sanitizedContent, self::TYPE_TEXT);
            if (!$moderationResult['approved']) {
                throw new MessageValidationException('Message content violates community guidelines');
            }

            // Prepare message data
            $messageData = [
                'id' => Str::uuid()->toString(),
                'chat_id' => $chatId,
                'sender_id' => $senderId->toInt(),
                'type' => self::TYPE_TEXT,
                'content' => $sanitizedContent,
                'status' => self::STATUS_SENDING,
                'metadata' => [
                    'platform' => $options['platform'] ?? 'web',
                    'device_info' => $options['device'] ?? 'unknown',
                    'reply_to' => $options['reply_to'] ?? null,
                    'thread_id' => $options['thread_id'] ?? null,
                    'mentions' => $this->extractMentions($sanitizedContent),
                    'links' => $this->extractLinks($sanitizedContent),
                    'moderation' => $moderationResult
                ],
                'delivery' => [
                    'sent_at' => Carbon::now(),
                    'delivered_at' => null,
                    'read_by' => [],
                    'failed_recipients' => []
                ],
                'encryption' => [
                    'encrypted' => $options['encrypt'] ?? true,
                    'key_version' => 'v1.0'
                ]
            ];

            // Create the message
            $messageEntity = $this->messageRepository->create($messageData);
            
            // Convert to array for compatibility
            $message = [
                'id' => $messageEntity->id()->value(),
                'chat_id' => $messageEntity->chatId()->value(),
                'sender_id' => $messageEntity->senderId()->toInt(),
                'type' => $messageEntity->type()->value(),
                'content' => $messageEntity->content(),
                'status' => $messageEntity->status()->value(),
                'created_at' => $messageEntity->createdAt(),
                'updated_at' => $messageEntity->updatedAt()
            ];

            // Update chat last activity
            $this->updateChatLastActivity($chatId, $message);

            // Process real-time delivery
            $this->processMessageDelivery($message, $options);

            // Track analytics
            $this->trackMessageAnalytics($message, 'sent');

            Log::info('Text message sent successfully', [
                'message_id' => $message['id'],
                'chat_id' => $chatId,
                'sender_id' => $senderId->toInt()
            ]);

            return $message;

        } catch (\Exception $e) {
            Log::error('Failed to send text message', [
                'chat_id' => $chatId,
                'sender_id' => $senderId->toInt(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new MessageException('Failed to send message: ' . $e->getMessage());
        }
    }

    /**
     * Send a multimedia message (image, video, voice, file)
     * 
     * @param string $chatId Chat identifier
     * @param UserId $senderId Sender user ID
     * @param array $fileData File information and metadata
     * @param array $options Message options
     * @return array Created message data
     */
    public function sendMultimediaMessage(
        string $chatId,
        UserId $senderId,
        array $fileData,
        array $options = []
    ): array {
        try {
            Log::info('Sending multimedia message', [
                'chat_id' => $chatId,
                'sender_id' => $senderId->toInt(),
                'file_type' => $fileData['type'],
                'file_size' => $fileData['size']
            ]);

            // Validate chat access and permissions
            $this->validateMessageSending($chatId, $senderId);
            $this->validateMultimediaPermissions($senderId, $fileData['type']);

            // Validate file data
            $validatedFileData = $this->validateFileData($fileData);

            // Process and upload file
            $processedFile = $this->processMultimediaFile($validatedFileData, $options);

            // Content moderation for multimedia
            $moderationResult = $this->moderateMultimediaContent($processedFile);
            if (!$moderationResult['approved']) {
                throw new MessageValidationException('Multimedia content violates community guidelines');
            }

            // Prepare message data
            $messageData = [
                'id' => Str::uuid()->toString(),
                'chat_id' => $chatId,
                'sender_id' => $senderId->toInt(),
                'type' => $validatedFileData['type'],
                'content' => $options['caption'] ?? '',
                'status' => self::STATUS_SENDING,
                'file_data' => [
                    'original_name' => $validatedFileData['original_name'],
                    'file_url' => $processedFile['url'],
                    'thumbnail_url' => $processedFile['thumbnail_url'] ?? null,
                    'file_size' => $validatedFileData['size'],
                    'mime_type' => $validatedFileData['mime_type'],
                    'dimensions' => $processedFile['dimensions'] ?? null,
                    'duration' => $processedFile['duration'] ?? null,
                    'transcription' => $processedFile['transcription'] ?? null
                ],
                'metadata' => [
                    'platform' => $options['platform'] ?? 'web',
                    'device_info' => $options['device'] ?? 'unknown',
                    'reply_to' => $options['reply_to'] ?? null,
                    'processing_time' => $processedFile['processing_time'],
                    'moderation' => $moderationResult,
                    'quality_settings' => $processedFile['quality_settings'] ?? null
                ],
                'delivery' => [
                    'sent_at' => Carbon::now(),
                    'delivered_at' => null,
                    'read_by' => [],
                    'failed_recipients' => []
                ],
                'encryption' => [
                    'encrypted' => true,
                    'key_version' => 'v1.0'
                ]
            ];

            // Create the message
            $messageEntity = $this->messageRepository->create($messageData, [
                'validate_chat' => false,
                'send_notifications' => $options['notify'] ?? true
            ]);

            // Convert to array for compatibility
            $message = $messageEntity->toArray();

            // Update chat last activity
            $this->updateChatLastActivity($chatId, $message);

            // Process delivery
            $this->processMessageDelivery($message, $options);

            // Track analytics
            $this->trackMessageAnalytics($message, 'multimedia_sent');

            Log::info('Multimedia message sent successfully', [
                'message_id' => $message['id'],
                'file_type' => $validatedFileData['type'],
                'file_size' => $validatedFileData['size']
            ]);

            return $message;

        } catch (\Exception $e) {
            Log::error('Failed to send multimedia message', [
                'chat_id' => $chatId,
                'sender_id' => $senderId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new MessageException('Failed to send multimedia message: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * MESSAGE RETRIEVAL AND MANAGEMENT
     * ========================================
     */

    /**
     * Get messages for a chat with pagination
     * 
     * @param string $chatId Chat identifier
     * @param UserId $requestingUserId User requesting messages
     * @param array $options Query options
     * @return Collection Messages collection
     */
    public function getChatMessages(
        string $chatId,
        UserId $requestingUserId,
        array $options = []
    ): Collection {
        try {
            // Validate chat access
            $this->validateChatAccess($chatId, $requestingUserId);

            // Set default options
            $defaultOptions = [
                'limit' => 50,
                'offset' => 0,
                'order' => 'desc', // newest first
                'include_system' => true,
                'include_deleted' => false,
                'mark_as_read' => true
            ];

            $mergedOptions = array_merge($defaultOptions, $options);

            // Get messages from repository
            $messages = $this->messageRepository->getChatMessages($chatId, $mergedOptions);

            // Decrypt messages if needed
            $decryptedMessages = $messages->map(function ($message) {
                return $this->decryptMessage($message);
            });

            // Mark messages as read if requested
            if ($mergedOptions['mark_as_read']) {
                $this->markMessagesAsRead($chatId, $requestingUserId, $decryptedMessages->pluck('id')->toArray());
            }

            // Add user-specific data
            $enrichedMessages = $decryptedMessages->map(function ($message) use ($requestingUserId) {
                return $this->enrichMessageForUser($message, $requestingUserId);
            });

            Log::info('Chat messages retrieved', [
                'chat_id' => $chatId,
                'user_id' => $requestingUserId->toInt(),
                'message_count' => $enrichedMessages->count()
            ]);

            return $enrichedMessages;

        } catch (\Exception $e) {
            Log::error('Failed to get chat messages', [
                'chat_id' => $chatId,
                'user_id' => $requestingUserId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new MessageException('Failed to retrieve messages: ' . $e->getMessage());
        }
    }

    /**
     * Search messages in chat
     * 
     * @param string $chatId Chat identifier
     * @param UserId $userId User performing search
     * @param string $query Search query
     * @param array $filters Search filters
     * @return Collection Search results
     */
    public function searchMessages(
        string $chatId,
        UserId $userId,
        string $query,
        array $filters = []
    ): Collection {
        try {
            // Validate chat access
            $this->validateChatAccess($chatId, $userId);

            // Validate search query
            if (strlen($query) < 3) {
                throw new MessageValidationException('Search query must be at least 3 characters');
            }

            // Prepare search parameters
            $searchParams = [
                'query' => $query,
                'chat_id' => $chatId,
                'filters' => array_merge([
                    'message_types' => [self::TYPE_TEXT, self::TYPE_FILE],
                    'date_range' => null,
                    'sender_id' => null,
                    'has_attachments' => null
                ], $filters),
                'limit' => $filters['limit'] ?? 100
            ];

            // Perform search
            $results = $this->messageRepository->searchMessages($searchParams);

            // Decrypt and enrich results
            $enrichedResults = $results->map(function ($message) use ($userId) {
                $decrypted = $this->decryptMessage($message);
                return $this->enrichMessageForUser($decrypted, $userId);
            });

            // Track search analytics
            $this->trackSearchAnalytics($chatId, $userId, $query, $enrichedResults->count());

            return $enrichedResults;

        } catch (\Exception $e) {
            Log::error('Message search failed', [
                'chat_id' => $chatId,
                'user_id' => $userId->toInt(),
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            throw new MessageException('Message search failed: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * MESSAGE STATUS AND INTERACTIONS
     * ========================================
     */

    /**
     * Mark messages as read
     * 
     * @param string $chatId Chat identifier
     * @param UserId $userId User marking messages as read
     * @param array $messageIds Message IDs to mark as read
     * @return bool Success status
     */
    public function markMessagesAsRead(
        string $chatId,
        UserId $userId,
        array $messageIds
    ): bool {
        try {
            // Validate chat access
            $this->validateChatAccess($chatId, $userId);

            $result = $this->messageRepository->bulkMarkAsRead(
                $messageIds,
                $userId,
                Carbon::now()
            );
            
            $success = $result['marked'] > 0;

            if ($success) {
                // Broadcast read receipts
                $this->broadcastReadReceipts($chatId, $userId, $messageIds);

                // Update chat analytics
                $this->updateChatEngagement($chatId, $userId, 'messages_read', count($messageIds));

                Log::info('Messages marked as read', [
                    'chat_id' => $chatId,
                    'user_id' => $userId->toInt(),
                    'message_count' => count($messageIds)
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('Failed to mark messages as read', [
                'chat_id' => $chatId,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Edit a message
     * 
     * @param string $messageId Message identifier
     * @param UserId $userId User editing the message
     * @param string $newContent New message content
     * @param array $options Edit options
     * @return array Updated message data
     */
    public function editMessage(
        string $messageId,
        UserId $userId,
        string $newContent,
        array $options = []
    ): array {
        try {
            // Get and validate message
            $message = $this->validateMessageOwnership($messageId, $userId);

            // Check if message can be edited (time limit, etc.)
            $this->validateMessageEditability($message);

            // Validate new content
            $sanitizedContent = $this->validateAndSanitizeTextContent($newContent);

            // Moderate new content
            $moderationResult = $this->moderateContent($sanitizedContent, $message['type']);
            if (!$moderationResult['approved']) {
                throw new MessageValidationException('Edited content violates community guidelines');
            }

            // Prepare edit data
            $editData = [
                'content' => $sanitizedContent,
                'edited' => true,
                'edited_at' => Carbon::now(),
                'edit_history' => array_merge($message['edit_history'] ?? [], [
                    [
                        'previous_content' => $message['content'],
                        'edited_at' => Carbon::now(),
                        'reason' => $options['reason'] ?? 'user_edit'
                    ]
                ]),
                'metadata' => array_merge($message['metadata'], [
                    'moderation_edit' => $moderationResult
                ])
            ];

            // Update message
            $updatedMessageEntity = $this->messageRepository->update($messageId, $editData);
            
            // Convert to array for compatibility
            $updatedMessage = [
                'id' => $updatedMessageEntity->id()->value(),
                'chat_id' => $updatedMessageEntity->chatId()->value(),
                'sender_id' => $updatedMessageEntity->senderId()->toInt(),
                'type' => $updatedMessageEntity->type()->value(),
                'content' => $updatedMessageEntity->content(),
                'status' => $updatedMessageEntity->status()->value(),
                'edited' => true,
                'edited_at' => $editData['edited_at'],
                'created_at' => $updatedMessageEntity->createdAt(),
                'updated_at' => $updatedMessageEntity->updatedAt()
            ];

            // Broadcast edit notification
            $this->broadcastMessageEdit($updatedMessage);

            // Track analytics
            $this->trackMessageAnalytics($updatedMessage, 'edited');

            Log::info('Message edited successfully', [
                'message_id' => $messageId,
                'user_id' => $userId->toInt(),
                'chat_id' => $message['chat_id']
            ]);

            return $updatedMessage;

        } catch (\Exception $e) {
            Log::error('Failed to edit message', [
                'message_id' => $messageId,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new MessageException('Failed to edit message: ' . $e->getMessage());
        }
    }

    /**
     * Delete a message
     * 
     * @param string $messageId Message identifier
     * @param UserId $userId User deleting the message
     * @param array $options Delete options
     * @return bool Success status
     */
    public function deleteMessage(
        string $messageId,
        UserId $userId,
        array $options = []
    ): bool {
        try {
            // Get and validate message
            $message = $this->validateMessageOwnership($messageId, $userId);

            // Check deletion permissions and time limits
            $this->validateMessageDeletability($message, $options);

            $deleteType = $options['delete_type'] ?? 'soft'; // soft, hard

            $success = $this->messageRepository->delete(
                $messageId,
                $deleteType === 'hard',
                [
                    'deleted_by' => $userId->toInt(),
                    'deleted_at' => Carbon::now(),
                    'delete_reason' => $options['reason'] ?? 'user_request',
                    'cleanup_attachments' => $options['cleanup_files'] ?? true
                ]
            );

            if ($success) {
                // Broadcast deletion notification
                $this->broadcastMessageDeletion($message);

                // Clean up associated files if needed
                if ($options['cleanup_files'] ?? true) {
                    $this->cleanupMessageFiles($message);
                }

                // Track analytics
                $this->trackMessageAnalytics($message, 'deleted');

                Log::info('Message deleted successfully', [
                    'message_id' => $messageId,
                    'user_id' => $userId->toInt(),
                    'delete_type' => $deleteType
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('Failed to delete message', [
                'message_id' => $messageId,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new MessageException('Failed to delete message: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * REAL-TIME FEATURES
     * ========================================
     */

    /**
     * Update typing indicator status
     * 
     * @param string $chatId Chat identifier
     * @param UserId $userId User typing
     * @param bool $isTyping Typing status
     * @return bool Success status
     */
    public function updateTypingIndicator(
        string $chatId,
        UserId $userId,
        bool $isTyping
    ): bool {
        try {
            // Validate chat access
            $this->validateChatAccess($chatId, $userId);

            $cacheKey = "typing:{$chatId}";
            $typingUsers = Cache::get($cacheKey, []);

            if ($isTyping) {
                $typingUsers[$userId->toInt()] = [
                    'started_at' => Carbon::now(),
                    'expires_at' => Carbon::now()->addSeconds(10)
                ];
            } else {
                unset($typingUsers[$userId->toInt()]);
            }

            // Clean up expired typing indicators
            $typingUsers = array_filter($typingUsers, function ($data) {
                return Carbon::parse($data['expires_at'])->isFuture();
            });

            Cache::put($cacheKey, $typingUsers, 15); // 15 seconds TTL

            // Broadcast typing indicator update
            $this->broadcastTypingIndicator($chatId, $userId, $isTyping);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update typing indicator', [
                'chat_id' => $chatId,
                'user_id' => $userId->toInt(),
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
     * Validate message sending permissions and limits
     */
    private function validateMessageSending(string $chatId, UserId $senderId): void
    {
        // Validate chat access
        $this->validateChatAccess($chatId, $senderId);

        // Check rate limits
        $this->validateRateLimits($senderId);

        // Check if chat allows new messages
        $chat = $this->chatRepository->findById($chatId);
        if ($chat['status'] !== 'active') {
            throw new UnauthorizedChatAccessException('Chat is not active for messaging');
        }
    }

    /**
     * Validate and sanitize text content
     */
    private function validateAndSanitizeTextContent(string $content): string
    {
        $trimmed = trim($content);
        
        if (empty($trimmed)) {
            throw new MessageValidationException('Message content cannot be empty');
        }

        if (strlen($trimmed) > self::MAX_TEXT_LENGTH) {
            throw new MessageValidationException('Message exceeds maximum length of ' . self::MAX_TEXT_LENGTH . ' characters');
        }

        // Basic sanitization (remove dangerous scripts, etc.)
        $sanitized = strip_tags($trimmed);
        
        // Additional sanitization for XSS prevention
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        
        return $sanitized;
    }

    /**
     * Moderate message content
     */
    private function moderateContent(string $content, string $type): array
    {
        // Implement content moderation logic
        // This would typically integrate with AI moderation services
        
        $result = [
            'approved' => true,
            'confidence' => 0.95,
            'flags' => [],
            'filtered_content' => $content,
            'moderation_time' => microtime(true) * 1000
        ];

        // Basic profanity filter (simplified example)
        $profanityWords = ['spam', 'scam', 'hate']; // This would be a comprehensive list
        foreach ($profanityWords as $word) {
            if (stripos($content, $word) !== false) {
                $result['approved'] = false;
                $result['flags'][] = 'profanity';
                break;
            }
        }

        return $result;
    }

    /**
     * Process message delivery and notifications
     */
    private function processMessageDelivery(array $message, array $options): void
    {
        // Update message status to sent
        $this->messageRepository->update($message['id'], [
            'status' => self::STATUS_SENT,
            'delivery.sent_at' => Carbon::now()
        ]);

        // Trigger message sent event for real-time broadcasting
        Event::dispatch(new MessageSent(
            $message['id'],
            $message['chat_id'],
            new UserId($message['sender_id']),
            $message,
            $options
        ));

        // Send push notifications if enabled
        if ($options['notify'] ?? true) {
            $this->sendPushNotifications($message);
        }
    }

    /**
     * Additional helper methods...
     */
    
    private function validateChatAccess(string $chatId, UserId $userId): void
    {
        $chat = $this->chatRepository->findById($chatId);
        if (!$chat) {
            throw new MessageException("Chat not found: {$chatId}");
        }

        if (!in_array($userId->toInt(), $chat['participants'])) {
            throw new UnauthorizedChatAccessException('User not authorized to access this chat');
        }
    }

    private function validateRateLimits(UserId $userId): void
    {
        $userProfile = $this->profileRepository->findById($userId->toInt());
        $isPremium = $userProfile['premium_status'] ?? false;
        
        $minuteLimit = $isPremium ? self::RATE_LIMIT_MESSAGES_PER_MINUTE * self::PREMIUM_RATE_MULTIPLIER : self::RATE_LIMIT_MESSAGES_PER_MINUTE;
        $hourLimit = $isPremium ? self::RATE_LIMIT_MESSAGES_PER_HOUR * self::PREMIUM_RATE_MULTIPLIER : self::RATE_LIMIT_MESSAGES_PER_HOUR;

        // Check minute limit
        $minuteKey = "message_rate_minute:{$userId->toInt()}:" . Carbon::now()->format('Y-m-d H:i');
        $minuteCount = Cache::get($minuteKey, 0);
        if ($minuteCount >= $minuteLimit) {
            throw new MessageException('Rate limit exceeded: too many messages per minute');
        }
        Cache::put($minuteKey, $minuteCount + 1, 60);

        // Check hour limit
        $hourKey = "message_rate_hour:{$userId->toInt()}:" . Carbon::now()->format('Y-m-d H');
        $hourCount = Cache::get($hourKey, 0);
        if ($hourCount >= $hourLimit) {
            throw new MessageException('Rate limit exceeded: too many messages per hour');
        }
        Cache::put($hourKey, $hourCount + 1, 3600);
    }

    private function extractMentions(string $content): array
    {
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
        return $matches[1] ?? [];
    }

    private function extractLinks(string $content): array
    {
        preg_match_all('/https?:\/\/[^\s]+/', $content, $matches);
        return $matches[0] ?? [];
    }

    private function validateMultimediaPermissions(UserId $userId, string $fileType): void
    {
        $userProfile = $this->profileRepository->findById($userId->toInt());
        $isPremium = $userProfile['premium_status'] ?? false;

        // Check if user has permission for this file type
        $restrictedTypes = [self::TYPE_VIDEO, self::TYPE_VOICE];
        if (in_array($fileType, $restrictedTypes) && !$isPremium) {
            throw new MessageException("Premium subscription required for {$fileType} messages");
        }
    }

    private function validateFileData(array $fileData): array
    {
        $required = ['type', 'size', 'mime_type', 'original_name'];
        foreach ($required as $field) {
            if (!isset($fileData[$field])) {
                throw new MessageValidationException("Missing required file field: {$field}");
            }
        }

        // Validate file size based on type
        $maxSize = match($fileData['type']) {
            self::TYPE_IMAGE => self::MAX_IMAGE_SIZE,
            self::TYPE_VIDEO => self::MAX_VIDEO_SIZE,
            default => self::MAX_FILE_SIZE
        };

        if ($fileData['size'] > $maxSize) {
            throw new MessageValidationException("File size exceeds maximum allowed: " . ($maxSize / 1024 / 1024) . "MB");
        }

        return $fileData;
    }

    private function processMultimediaFile(array $fileData, array $options): array
    {
        // This would integrate with file processing services
        // For now, return mock processed data
        return [
            'url' => "https://cdn.forevereusinlove.com/files/" . Str::uuid(),
            'thumbnail_url' => $fileData['type'] === self::TYPE_VIDEO ? "https://cdn.forevereusinlove.com/thumbnails/" . Str::uuid() : null,
            'processing_time' => 150, // milliseconds
            'dimensions' => $fileData['type'] === self::TYPE_IMAGE ? ['width' => 1920, 'height' => 1080] : null,
            'duration' => $fileData['type'] === self::TYPE_VIDEO ? 45 : null // seconds
        ];
    }

    private function moderateMultimediaContent(array $processedFile): array
    {
        // AI-based multimedia moderation would go here
        return [
            'approved' => true,
            'confidence' => 0.98,
            'flags' => [],
            'moderation_time' => 200
        ];
    }

    private function updateChatLastActivity(string $chatId, array $message): void
    {
        $this->chatRepository->updateLastActivity($chatId, Carbon::now());
    }

    private function decryptMessage(array $message): array
    {
        // Message decryption logic would go here
        // For now, return message as-is
        return $message;
    }

    private function enrichMessageForUser(array $message, UserId $userId): array
    {
        // Add user-specific data to message
        $message['is_own'] = $message['sender_id'] === $userId->toInt();
        $message['read_by_user'] = isset($message['delivery']['read_by'][$userId->toInt()]);
        
        return $message;
    }

    private function validateMessageOwnership(string $messageId, UserId $userId): array
    {
        $messageEntity = $this->messageRepository->findById($messageId);
        if (!$messageEntity) {
            throw new MessageNotFoundException("Message not found: {$messageId}");
        }

        // Convert to array for compatibility
        $message = $messageEntity->toArray();

        if ($message['sender_id'] !== $userId->toInt()) {
            throw new UnauthorizedChatAccessException('User not authorized to modify this message');
        }

        return $message;
    }

    private function validateMessageEditability(array $message): void
    {
        // Check if message type can be edited
        if (!in_array($message['type'], [self::TYPE_TEXT])) {
            throw new MessageException('This message type cannot be edited');
        }

        // Check time limit for editing (15 minutes)
        $sentAt = Carbon::parse($message['delivery']['sent_at']);
        if ($sentAt->diffInMinutes(Carbon::now()) > 15) {
            throw new MessageException('Message can only be edited within 15 minutes of sending');
        }
    }

    private function validateMessageDeletability(array $message, array $options): void
    {
        // Check if user can delete (owner or admin)
        // Time limits, etc.
        
        // For now, allow deletion within 24 hours
        $sentAt = Carbon::parse($message['delivery']['sent_at']);
        if ($sentAt->diffInHours(Carbon::now()) > 24 && ($options['force'] ?? false) === false) {
            throw new MessageException('Message can only be deleted within 24 hours of sending');
        }
    }

    private function trackMessageAnalytics(array $message, string $event): void
    {
        // Track message analytics
        Log::info("Message analytics: {$event}", [
            'message_id' => $message['id'],
            'chat_id' => $message['chat_id'],
            'message_type' => $message['type'],
            'sender_id' => $message['sender_id']
        ]);
    }

    private function trackSearchAnalytics(string $chatId, UserId $userId, string $query, int $resultCount): void
    {
        Log::info('Message search performed', [
            'chat_id' => $chatId,
            'user_id' => $userId->toInt(),
            'query_length' => strlen($query),
            'result_count' => $resultCount
        ]);
    }

    private function broadcastReadReceipts(string $chatId, UserId $userId, array $messageIds): void
    {
        // Broadcast read receipts via WebSocket or similar
        Log::info('Broadcasting read receipts', [
            'chat_id' => $chatId,
            'user_id' => $userId->toInt(),
            'message_count' => count($messageIds)
        ]);
    }

    private function broadcastMessageEdit(array $message): void
    {
        // Broadcast message edit via real-time channels
        Log::info('Broadcasting message edit', ['message_id' => $message['id']]);
    }

    private function broadcastMessageDeletion(array $message): void
    {
        // Broadcast message deletion via real-time channels
        Log::info('Broadcasting message deletion', ['message_id' => $message['id']]);
    }

    private function broadcastTypingIndicator(string $chatId, UserId $userId, bool $isTyping): void
    {
        // Broadcast typing indicator via WebSocket
        Log::info('Broadcasting typing indicator', [
            'chat_id' => $chatId,
            'user_id' => $userId->toInt(),
            'is_typing' => $isTyping
        ]);
    }

    private function sendPushNotifications(array $message): void
    {
        // Send push notifications to offline users
        Log::info('Sending push notification for message', ['message_id' => $message['id']]);
    }

    private function updateChatEngagement(string $chatId, UserId $userId, string $action, int $count): void
    {
        // Update chat engagement metrics
        Log::info('Updating chat engagement', [
            'chat_id' => $chatId,
            'user_id' => $userId->toInt(),
            'action' => $action,
            'count' => $count
        ]);
    }

    private function cleanupMessageFiles(array $message): void
    {
        // Clean up associated files when message is deleted
        if (isset($message['file_data']['file_url'])) {
            Log::info('Cleaning up message files', ['message_id' => $message['id']]);
        }
    }
}