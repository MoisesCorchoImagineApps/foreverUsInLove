<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

use App\Domain\Chat\Entities\Message;
use App\Domain\Chat\ValueObjects\MessageId;
use App\Domain\Chat\ValueObjects\ChatId;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Chat\ValueObjects\MessageType;
use App\Domain\Chat\ValueObjects\MessageStatus;
use App\Domain\Chat\ValueObjects\ContentModerationResult;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

/**
 * MessageRepositoryInterface - Repository contracts for message operations
 * 
 * Provides comprehensive repository interface for message management in the ForeverUsInLove
 * dating application. Supports all message types including text, multimedia, system messages,
 * and special content with full CRUD operations, advanced search, content moderation,
 * analytics, and real-time capabilities.
 * 
 * Features:
 * - Complete message lifecycle management (create, read, update, delete)
 * - Multi-type message support (text, image, video, audio, location, system)
 * - Advanced search and filtering with full-text capabilities
 * - Content moderation and safety features
 * - Real-time message delivery and read receipts
 * - Message threading and reply management
 * - Multimedia attachment handling
 * - Message analytics and engagement tracking
 * - Privacy and encryption support
 * - GDPR compliance and data retention
 * - Performance optimization with indexing and caching
 * 
 * Architecture:
 * - Repository Pattern implementation
 * - Domain-Driven Design (DDD) principles
 * - Clean Architecture / Hexagonal Architecture
 * - SOLID principles with Dependency Inversion
 * - Laravel 12 with PHP 8.2 strict typing
 * - Comprehensive error handling and validation
 * 
 * @package App\Domain\Chat\Repositories
 * @version 1.0.0
 * @since 2025-10-10
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 * 
 * @see \App\Domain\Chat\Entities\Message
 * @see \App\Domain\Chat\Services\MessageService
 * @see \App\Infrastructure\Persistence\Eloquent\MessageEloquentRepository
 */
interface MessageRepositoryInterface
{
    // ============================================================================
    // CORE MESSAGE OPERATIONS
    // ============================================================================

    /**
     * Create a new message with comprehensive configuration
     * 
     * Creates a new message entity with full initialization including content
     * processing, moderation checks, attachment handling, and real-time delivery
     * setup. Supports all message types with appropriate validation.
     * 
     * @param array{
     *     chat_id: ChatId|string,
     *     sender_id: UserId|string,
     *     type: MessageType|string,
     *     content?: string,
     *     attachments?: array{
     *         type: string,
     *         url: string,
     *         filename?: string,
     *         size?: int,
     *         mime_type?: string,
     *         metadata?: array
     *     }[],
     *     reply_to_id?: MessageId|string,
     *     mentions?: array<UserId|string>,
     *     metadata?: array{
     *         location?: array{latitude: float, longitude: float, address?: string},
     *         poll?: array{question: string, options: array<string>, expires_at?: string},
     *         system_action?: string,
     *         formatting?: array
     *     },
     *     scheduled_at?: Carbon|string|null,
     *     expires_at?: Carbon|string|null,
     *     is_encrypted?: bool
     * } $data Message creation data with comprehensive configuration
     * 
     * @return Message Newly created and fully processed message entity
     * 
     * @throws \App\Domain\Chat\Exceptions\MessageCreationException When message creation fails
     * @throws \App\Domain\Chat\Exceptions\InvalidMessageDataException When data is invalid
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When sender not found
     * @throws \App\Domain\Chat\Exceptions\ContentModerationException When content is rejected
     * @throws \App\Domain\Common\Exceptions\ValidationException When validation fails
     * 
     * @example
     * ```php
     * $message = $repository->create([
     *     'chat_id' => $chatId,
     *     'sender_id' => $userId,
     *     'type' => MessageType::TEXT,
     *     'content' => 'Hello! How are you today?',
     *     'mentions' => [$mentionedUserId],
     *     'metadata' => [
     *         'formatting' => ['bold' => [0, 5]]
     *     ]
     * ]);
     * ```
     */
    public function create(array $data): Message;

    /**
     * Find message by ID with optional eager loading
     * 
     * Retrieves a message by its unique identifier with support for eager loading
     * related entities like sender, chat, replies, and attachments. Includes
     * comprehensive error handling for not found scenarios.
     * 
     * @param MessageId|string $messageId Unique message identifier
     * @param array<string> $with Optional relations to eager load
     *                            ['sender', 'chat', 'replies', 'attachments', 'mentions']
     * 
     * @return Message|null Message entity if found, null otherwise
     * 
     * @throws \App\Domain\Chat\Exceptions\InvalidMessageIdException When message ID is invalid
     * 
     * @example
     * ```php
     * $message = $repository->findById($messageId, ['sender', 'attachments']);
     * ```
     */
    public function findById(MessageId|string $messageId, array $with = []): ?Message;

    /**
     * Find message by ID or throw exception
     * 
     * Retrieves a message by its unique identifier or throws an exception if not found.
     * Useful for operations that require the message to exist.
     * 
     * @param MessageId|string $messageId Unique message identifier
     * @param array<string> $with Optional relations to eager load
     * 
     * @return Message Message entity (guaranteed to exist)
     * 
     * @throws \App\Domain\Chat\Exceptions\MessageNotFoundException When message not found
     * @throws \App\Domain\Chat\Exceptions\InvalidMessageIdException When message ID is invalid
     */
    public function findByIdOrFail(MessageId|string $messageId, array $with = []): Message;

    /**
     * Update message with comprehensive data validation
     * 
     * Updates an existing message with new data including content editing,
     * attachment modification, and metadata updates. Supports edit history
     * tracking and maintains message integrity.
     * 
     * @param MessageId|string $messageId Message to update
     * @param array{
     *     content?: string,
     *     attachments?: array,
     *     metadata?: array,
     *     status?: MessageStatus|string,
     *     edited_at?: Carbon|string,
     *     edit_reason?: string
     * } $data Update data
     * 
     * @return Message Updated message entity
     * 
     * @throws \App\Domain\Chat\Exceptions\MessageNotFoundException When message not found
     * @throws \App\Domain\Chat\Exceptions\MessageUpdateException When update fails
     * @throws \App\Domain\Chat\Exceptions\MessageEditTimeExpiredException When edit window expired
     * @throws \App\Domain\Common\Exceptions\ValidationException When data is invalid
     */
    public function update(MessageId|string $messageId, array $data): Message;

    /**
     * Delete message with comprehensive cleanup
     * 
     * Soft deletes a message and performs comprehensive cleanup including:
     * - Reply chain handling
     * - Attachment cleanup
     * - Notification updates
     * - Analytics recording
     * 
     * @param MessageId|string $messageId Message to delete
     * @param bool $force Whether to force permanent deletion
     * @param array{
     *     deleted_by?: UserId|string,
     *     reason?: string,
     *     cleanup_attachments?: bool
     * } $options Deletion options and metadata
     * 
     * @return bool True if successfully deleted
     * 
     * @throws \App\Domain\Chat\Exceptions\MessageNotFoundException When message not found
     * @throws \App\Domain\Chat\Exceptions\MessageDeletionException When deletion fails
     */
    public function delete(MessageId|string $messageId, bool $force = false, array $options = []): bool;

    // ============================================================================
    // MESSAGE RETRIEVAL AND FILTERING
    // ============================================================================

    /**
     * Get chat messages with advanced filtering and pagination
     * 
     * Retrieves all messages for a chat with comprehensive filtering, sorting,
     * and pagination options. Supports real-time updates and performance
     * optimization through eager loading and cursor pagination.
     * 
     * @param ChatId|string $chatId Target chat identifier
     * @param array{
     *     types?: array<MessageType|string>,
     *     status?: array<MessageStatus|string>,
     *     sender_id?: UserId|string,
     *     search?: string,
     *     sent_after?: Carbon|string,
     *     sent_before?: Carbon|string,
     *     has_attachments?: bool,
     *     has_mentions?: bool,
     *     reply_to_id?: MessageId|string,
     *     sort_by?: string,
     *     sort_direction?: string,
     *     per_page?: int,
     *     cursor?: string,
     *     with?: array<string>
     * } $filters Comprehensive filtering options
     * 
     * @return LengthAwarePaginator Paginated message results with metadata
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     * @throws \App\Domain\Common\Exceptions\ValidationException When filters are invalid
     * 
     * @example
     * ```php
     * $messages = $repository->getChatMessages($chatId, [
     *     'types' => [MessageType::TEXT, MessageType::IMAGE],
     *     'has_attachments' => true,
     *     'sort_by' => 'sent_at',
     *     'per_page' => 50
     * ]);
     * ```
     */
    public function getChatMessages(ChatId|string $chatId, array $filters = []): LengthAwarePaginator;

    /**
     * Get user's messages across all chats
     * 
     * Retrieves all messages sent by a specific user with filtering and
     * pagination support. Useful for user analytics and content review.
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     chat_ids?: array<ChatId|string>,
     *     types?: array<MessageType|string>,
     *     sent_after?: Carbon|string,
     *     sent_before?: Carbon|string,
     *     per_page?: int
     * } $filters Message filtering options
     * 
     * @return LengthAwarePaginator User's messages with metadata
     * 
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     */
    public function getUserMessages(UserId|string $userId, array $filters = []): LengthAwarePaginator;

    /**
     * Search messages with advanced full-text capabilities
     * 
     * Performs comprehensive full-text search across messages with support for
     * complex queries, content analysis, and relevance scoring.
     * 
     * @param array{
     *     query?: string,
     *     chat_id?: ChatId|string,
     *     user_id?: UserId|string,
     *     types?: array<MessageType|string>,
     *     sent_after?: Carbon|string,
     *     sent_before?: Carbon|string,
     *     has_attachments?: bool,
     *     attachment_types?: array<string>,
     *     sort_by?: string,
     *     per_page?: int
     * } $criteria Search criteria with comprehensive options
     * 
     * @return LengthAwarePaginator Search results with relevance scoring
     * 
     * @throws \App\Domain\Common\Exceptions\ValidationException When criteria are invalid
     */
    public function searchMessages(array $criteria): LengthAwarePaginator;

    /**
     * Get message thread (replies and context)
     * 
     * Retrieves a complete message thread including the root message and all
     * replies in chronological order. Essential for threaded conversations.
     * 
     * @param MessageId|string $messageId Root message or any message in thread
     * @param array{
     *     include_root?: bool,
     *     max_depth?: int,
     *     with?: array<string>
     * } $options Thread retrieval options
     * 
     * @return Collection Ordered collection of thread messages
     * 
     * @throws \App\Domain\Chat\Exceptions\MessageNotFoundException When message not found
     */
    public function getMessageThread(MessageId|string $messageId, array $options = []): Collection;

    // ============================================================================
    // REAL-TIME AND DELIVERY MANAGEMENT
    // ============================================================================

    /**
     * Mark message as delivered to specific user
     * 
     * Records message delivery confirmation for real-time messaging and
     * read receipt functionality.
     * 
     * @param MessageId|string $messageId Target message identifier
     * @param UserId|string $userId User who received the message
     * @param Carbon|null $deliveredAt Optional custom delivery timestamp
     * 
     * @return bool True if successfully marked as delivered
     * 
     * @throws \App\Domain\Chat\Exceptions\MessageNotFoundException When message not found
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     */
    public function markAsDelivered(MessageId|string $messageId, UserId|string $userId, ?Carbon $deliveredAt = null): bool;

    /**
     * Mark message as read by specific user
     * 
     * Records message read confirmation and updates read receipts for
     * the sender and other participants.
     * 
     * @param MessageId|string $messageId Target message identifier
     * @param UserId|string $userId User who read the message
     * @param Carbon|null $readAt Optional custom read timestamp
     * 
     * @return bool True if successfully marked as read
     * 
     * @throws \App\Domain\Chat\Exceptions\MessageNotFoundException When message not found
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     */
    public function markAsRead(MessageId|string $messageId, UserId|string $userId, ?Carbon $readAt = null): bool;

    /**
     * Get message delivery status for all recipients
     * 
     * Retrieves comprehensive delivery and read status for all message
     * recipients including timestamps and detailed status information.
     * 
     * @param MessageId|string $messageId Target message identifier
     * 
     * @return array{
     *     total_recipients: int,
     *     delivered: array<array{user_id: string, delivered_at: string}>,
     *     read: array<array{user_id: string, read_at: string}>,
     *     pending: array<string>
     * } Comprehensive delivery status
     * 
     * @throws \App\Domain\Chat\Exceptions\MessageNotFoundException When message not found
     */
    public function getMessageDeliveryStatus(MessageId|string $messageId): array;

    /**
     * Get unread messages for user
     * 
     * Retrieves all unread messages for a user across all chats or specific
     * chats with comprehensive filtering and sorting options.
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     chat_ids?: array<ChatId|string>,
     *     limit?: int,
     *     include_muted?: bool,
     *     sort_by?: string
     * } $options Unread messages filtering
     * 
     * @return Collection Unread messages with metadata
     * 
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     */
    public function getUnreadMessages(UserId|string $userId, array $options = []): Collection;

    // ============================================================================
    // CONTENT MODERATION AND SAFETY
    // ============================================================================

    /**
     * Get messages requiring moderation review
     * 
     * Retrieves messages flagged by automated systems or user reports that
     * require human moderation review.
     * 
     * @param array{
     *     severity?: array<string>,
     *     types?: array<MessageType|string>,
     *     reported_after?: Carbon|string,
     *     per_page?: int,
     *     include_context?: bool
     * } $filters Moderation filtering options
     * 
     * @return LengthAwarePaginator Messages requiring moderation
     */
    public function getMessagesForModeration(array $filters = []): LengthAwarePaginator;

    /**
     * Update message moderation status
     * 
     * Updates the moderation status of a message after review including
     * approval, rejection, or requiring further action.
     * 
     * @param MessageId|string $messageId Target message identifier
     * @param ContentModerationResult $result Moderation decision and details
     * @param array{
     *     moderator_id?: UserId|string,
     *     notes?: string,
     *     action_taken?: string
     * } $metadata Moderation metadata
     * 
     * @return bool True if successfully updated
     * 
     * @throws \App\Domain\Chat\Exceptions\MessageNotFoundException When message not found
     */
    public function updateModerationStatus(
        MessageId|string $messageId,
        ContentModerationResult $result,
        array $metadata = []
    ): bool;

    /**
     * Report message for policy violation
     * 
     * Records a user report for message content that potentially violates
     * community guidelines or terms of service.
     * 
     * @param MessageId|string $messageId Reported message identifier
     * @param UserId|string $reporterId User making the report
     * @param array{
     *     reason: string,
     *     category: string,
     *     description?: string,
     *     severity?: string
     * } $reportData Report details and context
     * 
     * @return bool True if report successfully recorded
     * 
     * @throws \App\Domain\Chat\Exceptions\MessageNotFoundException When message not found
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When reporter not found
     */
    public function reportMessage(MessageId|string $messageId, UserId|string $reporterId, array $reportData): bool;

    // ============================================================================
    // MULTIMEDIA AND ATTACHMENT MANAGEMENT
    // ============================================================================

    /**
     * Get messages with specific attachment types
     * 
     * Retrieves messages containing attachments of specified types including
     * images, videos, audio files, and documents.
     * 
     * @param ChatId|string $chatId Target chat identifier
     * @param array<string> $attachmentTypes MIME types or categories to filter
     * @param array{
     *     sent_after?: Carbon|string,
     *     sent_before?: Carbon|string,
     *     per_page?: int
     * } $options Additional filtering options
     * 
     * @return LengthAwarePaginator Messages with specified attachments
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     */
    public function getMessagesWithAttachments(ChatId|string $chatId, array $attachmentTypes, array $options = []): LengthAwarePaginator;

    /**
     * Get attachment statistics for chat
     * 
     * Retrieves comprehensive statistics about attachments shared in a chat
     * including file types, sizes, and sharing patterns.
     * 
     * @param ChatId|string $chatId Target chat identifier
     * @param array{
     *     period?: string,
     *     start_date?: Carbon|string,
     *     end_date?: Carbon|string
     * } $options Statistics period configuration
     * 
     * @return array{
     *     total_attachments: int,
     *     total_size_bytes: int,
     *     by_type: array<string, array{count: int, size: int}>,
     *     most_active_users: array,
     *     trending_types: array
     * } Comprehensive attachment statistics
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     */
    public function getAttachmentStatistics(ChatId|string $chatId, array $options = []): array;

    // ============================================================================
    // MESSAGE ANALYTICS AND METRICS
    // ============================================================================

    /**
     * Get message engagement metrics
     * 
     * Retrieves detailed engagement metrics for messages including read rates,
     * response times, and interaction patterns.
     * 
     * @param ChatId|string $chatId Target chat identifier
     * @param array{
     *     period?: string,
     *     message_types?: array<MessageType|string>,
     *     start_date?: Carbon|string,
     *     end_date?: Carbon|string
     * } $options Metrics configuration
     * 
     * @return array{
     *     total_messages: int,
     *     read_rate: float,
     *     avg_response_time: float,
     *     most_active_hours: array,
     *     engagement_score: float,
     *     popular_content_types: array
     * } Comprehensive engagement metrics
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     */
    public function getMessageEngagementMetrics(ChatId|string $chatId, array $options = []): array;

    /**
     * Get user's messaging patterns
     * 
     * Analyzes and retrieves detailed messaging patterns for a user including
     * preferred communication times, message types, and social behavior.
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     period?: string,
     *     chat_types?: array<string>,
     *     include_predictions?: bool
     * } $options Pattern analysis configuration
     * 
     * @return array{
     *     messages_per_day: float,
     *     preferred_hours: array,
     *     favorite_message_types: array,
     *     avg_message_length: int,
     *     response_time_percentiles: array,
     *     communication_style: string
     * } User messaging patterns and insights
     * 
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     */
    public function getUserMessagingPatterns(UserId|string $userId, array $options = []): array;

    // ============================================================================
    // SCHEDULED AND SPECIAL MESSAGES
    // ============================================================================

    /**
     * Get scheduled messages ready for delivery
     * 
     * Retrieves messages that are scheduled for delivery and are ready to be
     * sent based on their scheduled timestamp.
     * 
     * @param Carbon|null $upTo Optional maximum timestamp for message selection
     * @param int $limit Maximum number of messages to retrieve
     * 
     * @return Collection Scheduled messages ready for delivery
     */
    public function getScheduledMessagesForDelivery(?Carbon $upTo = null, int $limit = 100): Collection;

    /**
     * Get expiring messages that need cleanup
     * 
     * Retrieves messages that have exceeded their expiration time and need
     * to be archived or deleted according to retention policies.
     * 
     * @param Carbon|null $before Optional expiration cutoff timestamp
     * @param int $limit Maximum number of messages to retrieve
     * 
     * @return Collection Expired messages for cleanup
     */
    public function getExpiredMessages(?Carbon $before = null, int $limit = 100): Collection;

    // ============================================================================
    // PERFORMANCE AND CACHING
    // ============================================================================

    /**
     * Warm cache for frequently accessed messages
     * 
     * Preloads frequently accessed message data into cache for improved
     * performance during peak usage times.
     * 
     * @param ChatId|string $chatId Chat whose messages should be cached
     * @param int $messageCount Number of recent messages to cache
     * 
     * @return int Number of messages cached
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     */
    public function warmMessageCache(ChatId|string $chatId, int $messageCount = 100): int;

    /**
     * Clear message-related caches
     * 
     * Clears all cached data related to specific messages or all messages,
     * useful after bulk operations or system maintenance.
     * 
     * @param ChatId|string|null $chatId Optional specific chat to clear
     * @param MessageId|string|null $messageId Optional specific message to clear
     * 
     * @return bool True if cache cleared successfully
     */
    public function clearMessageCache(?ChatId $chatId = null, ?MessageId $messageId = null): bool;

    // ============================================================================
    // BULK OPERATIONS
    // ============================================================================

    /**
     * Bulk update multiple messages
     * 
     * Performs efficient bulk updates on multiple messages with transaction
     * support and comprehensive error handling.
     * 
     * @param array<MessageId|string> $messageIds Messages to update
     * @param array $updates Common update data
     * @param array{
     *     validate_permissions?: bool,
     *     skip_moderation?: bool,
     *     batch_size?: int
     * } $options Bulk operation configuration
     * 
     * @return array{
     *     updated: int,
     *     failed: array,
     *     errors: array
     * } Bulk operation results
     * 
     * @throws \App\Domain\Common\Exceptions\BulkOperationException When bulk operation fails
     */
    public function bulkUpdateMessages(array $messageIds, array $updates, array $options = []): array;

    /**
     * Bulk delete multiple messages
     * 
     * Efficiently deletes multiple messages with proper cleanup,
     * attachment handling, and notification management.
     * 
     * @param array<MessageId|string> $messageIds Messages to delete
     * @param bool $force Whether to force permanent deletion
     * @param array{
     *     deleted_by?: UserId|string,
     *     reason?: string,
     *     cleanup_attachments?: bool,
     *     batch_size?: int
     * } $options Deletion configuration
     * 
     * @return array{
     *     deleted: int,
     *     failed: array,
     *     errors: array
     * } Bulk deletion results
     * 
     * @throws \App\Domain\Common\Exceptions\BulkOperationException When bulk operation fails
     */
    public function bulkDeleteMessages(array $messageIds, bool $force = false, array $options = []): array;

    /**
     * Bulk mark messages as read for user
     * 
     * Efficiently marks multiple messages as read for a user, useful for
     * "mark all as read" functionality.
     * 
     * @param array<MessageId|string> $messageIds Messages to mark as read
     * @param UserId|string $userId User marking messages as read
     * @param Carbon|null $readAt Optional custom read timestamp
     * 
     * @return array{
     *     marked: int,
     *     failed: array,
     *     errors: array
     * } Bulk read marking results
     * 
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     */
    public function bulkMarkAsRead(array $messageIds, UserId|string $userId, ?Carbon $readAt = null): array;

    // ============================================================================
    // DATA EXPORT AND ARCHIVAL
    // ============================================================================

    /**
     * Export chat messages for user data request
     * 
     * Exports all messages for a user in a structured format for GDPR
     * compliance and data portability requirements.
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     format?: string,
     *     include_attachments?: bool,
     *     date_range?: array{start: Carbon|string, end: Carbon|string},
     *     chat_ids?: array<ChatId|string>
     * } $options Export configuration
     * 
     * @return array{
     *     messages: array,
     *     attachments?: array,
     *     metadata: array{
     *         total_messages: int,
     *         export_date: string,
     *         format: string
     *     }
     * } Structured export data
     * 
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     */
    public function exportUserMessages(UserId|string $userId, array $options = []): array;

    /**
     * Archive old messages according to retention policy
     * 
     * Archives messages older than specified retention period, moving them
     * to long-term storage while maintaining data integrity.
     * 
     * @param Carbon $olderThan Cutoff date for archival
     * @param int $batchSize Number of messages to process per batch
     * @param array{
     *     preserve_attachments?: bool,
     *     compress_data?: bool,
     *     notify_users?: bool
     * } $options Archival configuration
     * 
     * @return array{
     *     archived: int,
     *     failed: array,
     *     total_size_saved: int
     * } Archival operation results
     */
    public function archiveOldMessages(Carbon $olderThan, int $batchSize = 1000, array $options = []): array;
}