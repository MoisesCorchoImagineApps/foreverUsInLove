<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

use App\Domain\Chat\Entities\Chat;
use App\Domain\Chat\ValueObjects\ChatId;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Chat\ValueObjects\ChatType;
use App\Domain\Chat\ValueObjects\ChatStatus;
use App\Domain\Chat\ValueObjects\ParticipantRole;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

/**
 * ChatRepositoryInterface - Repository contracts for chat management operations
 * 
 * Provides comprehensive repository interface for chat management in the ForeverUsInLove
 * dating application. Supports all chat types including private chats, group chats,
 * video calls, and Happy Hour events with full CRUD operations, filtering, searching,
 * analytics, and real-time capabilities.
 * 
 * Features:
 * - Complete chat lifecycle management (create, read, update, delete)
 * - Multi-type chat support (private, group, video, happy_hour)
 * - Advanced search and filtering capabilities
 * - Real-time chat session management
 * - Participant management with role-based permissions
 * - Chat analytics and engagement tracking
 * - Privacy and moderation controls
 * - Premium feature integration
 * - GDPR compliance support
 * - Performance optimization with caching
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
 * @see \App\Domain\Chat\Entities\Chat
 * @see \App\Domain\Chat\Services\ChatService
 * @see \App\Infrastructure\Persistence\Eloquent\ChatEloquentRepository
 */
interface ChatRepositoryInterface
{
    // ============================================================================
    // CORE CHAT OPERATIONS
    // ============================================================================

    /**
     * Create a new chat with comprehensive configuration
     * 
     * Creates a new chat entity with full initialization including participants,
     * settings, privacy configuration, and real-time setup. Supports all chat
     * types with appropriate validation and default settings.
     * 
     * @param array{
     *     type: ChatType|string,
     *     name?: string,
     *     description?: string,
     *     creator_id: UserId|string,
     *     participants: array<UserId|string>,
     *     settings?: array{
     *         is_private?: bool,
     *         require_approval?: bool,
     *         allow_invites?: bool,
     *         max_participants?: int,
     *         auto_delete_after?: int,
     *         encryption_enabled?: bool,
     *         moderation_level?: string,
     *         notification_settings?: array
     *     },
     *     metadata?: array,
     *     expires_at?: Carbon|string|null
     * } $data Chat creation data with comprehensive configuration
     * 
     * @return Chat Newly created and fully configured chat entity
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatCreationException When chat creation fails
     * @throws \App\Domain\Chat\Exceptions\InvalidChatDataException When data is invalid
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When participants not found
     * @throws \App\Domain\Common\Exceptions\ValidationException When validation fails
     * 
     * @example
     * ```php
     * $chat = $repository->create([
     *     'type' => ChatType::GROUP,
     *     'name' => 'Photography Enthusiasts',
     *     'description' => 'Share your best shots!',
     *     'creator_id' => $userId,
     *     'participants' => [$userId1, $userId2, $userId3],
     *     'settings' => [
     *         'is_private' => false,
     *         'require_approval' => true,
     *         'max_participants' => 50,
     *         'moderation_level' => 'moderate'
     *     ]
     * ]);
     * ```
     */
    public function create(array $data): Chat;

    /**
     * Find chat by ID with optional eager loading
     * 
     * Retrieves a chat by its unique identifier with support for eager loading
     * related entities like participants, messages, and settings. Includes
     * comprehensive error handling for not found scenarios.
     * 
     * @param ChatId|string $chatId Unique chat identifier
     * @param array<string> $with Optional relations to eager load
     *                             ['participants', 'messages', 'settings', 'creator']
     * 
     * @return Chat|null Chat entity if found, null otherwise
     * 
     * @throws \App\Domain\Chat\Exceptions\InvalidChatIdException When chat ID is invalid
     * 
     * @example
     * ```php
     * $chat = $repository->findById($chatId, ['participants', 'messages']);
     * ```
     */
    public function findById(ChatId|string $chatId, array $with = []): ?Chat;

    /**
     * Find chat by ID or throw exception
     * 
     * Retrieves a chat by its unique identifier or throws an exception if not found.
     * Useful for operations that require the chat to exist.
     * 
     * @param ChatId|string $chatId Unique chat identifier
     * @param array<string> $with Optional relations to eager load
     * 
     * @return Chat Chat entity (guaranteed to exist)
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     * @throws \App\Domain\Chat\Exceptions\InvalidChatIdException When chat ID is invalid
     */
    public function findByIdOrFail(ChatId|string $chatId, array $with = []): Chat;

    /**
     * Update chat with comprehensive data validation
     * 
     * Updates an existing chat with new data including settings, participants,
     * and metadata. Supports partial updates and maintains data integrity.
     * 
     * @param ChatId|string $chatId Chat to update
     * @param array{
     *     name?: string,
     *     description?: string,
     *     settings?: array,
     *     metadata?: array,
     *     status?: ChatStatus|string,
     *     expires_at?: Carbon|string|null
     * } $data Update data
     * 
     * @return Chat Updated chat entity
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     * @throws \App\Domain\Chat\Exceptions\ChatUpdateException When update fails
     * @throws \App\Domain\Common\Exceptions\ValidationException When data is invalid
     */
    public function update(ChatId|string $chatId, array $data): Chat;

    /**
     * Delete chat with comprehensive cleanup
     * 
     * Soft deletes a chat and performs comprehensive cleanup including:
     * - Message archival/deletion based on settings
     * - Participant notification
     * - Related data cleanup
     * - Analytics recording
     * 
     * @param ChatId|string $chatId Chat to delete
     * @param bool $force Whether to force permanent deletion
     * 
     * @return bool True if successfully deleted
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     * @throws \App\Domain\Chat\Exceptions\ChatDeletionException When deletion fails
     */
    public function delete(ChatId|string $chatId, bool $force = false): bool;

    // ============================================================================
    // CHAT DISCOVERY AND FILTERING
    // ============================================================================

    /**
     * Get user's chats with advanced filtering and pagination
     * 
     * Retrieves all chats for a user with comprehensive filtering, sorting,
     * and pagination options. Supports real-time updates and performance
     * optimization through eager loading and caching.
     * 
     * @param UserId|string $userId User identifier
     * @param array{
     *     types?: array<ChatType|string>,
     *     status?: array<ChatStatus|string>,
     *     search?: string,
     *     created_after?: Carbon|string,
     *     created_before?: Carbon|string,
     *     has_unread?: bool,
     *     is_active?: bool,
     *     sort_by?: string,
     *     sort_direction?: string,
     *     per_page?: int,
     *     page?: int,
     *     with?: array<string>
     * } $filters Comprehensive filtering options
     * 
     * @return LengthAwarePaginator Paginated chat results with metadata
     * 
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     * @throws \App\Domain\Common\Exceptions\ValidationException When filters are invalid
     * 
     * @example
     * ```php
     * $chats = $repository->getUserChats($userId, [
     *     'types' => [ChatType::PRIVATE, ChatType::GROUP],
     *     'has_unread' => true,
     *     'sort_by' => 'last_activity',
     *     'per_page' => 20
     * ]);
     * ```
     */
    public function getUserChats(UserId|string $userId, array $filters = []): LengthAwarePaginator;

    /**
     * Find private chat between two users
     * 
     * Locates existing private chat between two specific users. Essential for
     * preventing duplicate private chats and message routing.
     * 
     * @param UserId|string $userId1 First user identifier
     * @param UserId|string $userId2 Second user identifier
     * 
     * @return Chat|null Existing private chat or null if none exists
     * 
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When users not found
     */
    public function findPrivateChatBetweenUsers(UserId|string $userId1, UserId|string $userId2): ?Chat;

    /**
     * Search chats with advanced criteria
     * 
     * Performs full-text search across chats with support for complex queries,
     * filters, and relevance scoring. Includes participant matching and
     * content-based search.
     * 
     * @param array{
     *     query?: string,
     *     user_id?: UserId|string,
     *     types?: array<ChatType|string>,
     *     tags?: array<string>,
     *     created_after?: Carbon|string,
     *     created_before?: Carbon|string,
     *     min_participants?: int,
     *     max_participants?: int,
     *     is_public?: bool,
     *     has_activity?: bool,
     *     sort_by?: string,
     *     per_page?: int
     * } $criteria Search criteria with comprehensive options
     * 
     * @return LengthAwarePaginator Search results with relevance scoring
     * 
     * @throws \App\Domain\Common\Exceptions\ValidationException When criteria are invalid
     */
    public function searchChats(array $criteria): LengthAwarePaginator;

    // ============================================================================
    // PARTICIPANT MANAGEMENT
    // ============================================================================

    /**
     * Add participant to chat with role assignment
     * 
     * Adds a new participant to an existing chat with appropriate role assignment,
     * permission validation, and notification handling.
     * 
     * @param ChatId|string $chatId Target chat identifier
     * @param UserId|string $userId User to add
     * @param ParticipantRole|string $role Participant role (member, moderator, admin)
     * @param array{
     *     invited_by?: UserId|string,
     *     permissions?: array<string>,
     *     metadata?: array,
     *     notify?: bool
     * } $options Additional options for participant addition
     * 
     * @return bool True if successfully added
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     * @throws \App\Domain\Chat\Exceptions\ParticipantAlreadyExistsException When already participant
     * @throws \App\Domain\Chat\Exceptions\ChatCapacityExceededException When chat is full
     */
    public function addParticipant(
        ChatId|string $chatId,
        UserId|string $userId,
        ParticipantRole|string $role = ParticipantRole::MEMBER,
        array $options = []
    ): bool;

    /**
     * Remove participant from chat
     * 
     * Removes a participant from chat with proper cleanup, notification,
     * and permission validation.
     * 
     * @param ChatId|string $chatId Target chat identifier
     * @param UserId|string $userId User to remove
     * @param array{
     *     removed_by?: UserId|string,
     *     reason?: string,
     *     notify?: bool,
     *     transfer_ownership?: bool
     * } $options Removal options and metadata
     * 
     * @return bool True if successfully removed
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     * @throws \App\Domain\Chat\Exceptions\ParticipantNotFoundException When not participant
     * @throws \App\Domain\Chat\Exceptions\CannotRemoveCreatorException When trying to remove creator
     */
    public function removeParticipant(ChatId|string $chatId, UserId|string $userId, array $options = []): bool;

    /**
     * Update participant role and permissions
     * 
     * Updates participant's role, permissions, and metadata within a chat.
     * Includes permission validation and change notification.
     * 
     * @param ChatId|string $chatId Target chat identifier
     * @param UserId|string $userId Participant to update
     * @param array{
     *     role?: ParticipantRole|string,
     *     permissions?: array<string>,
     *     metadata?: array,
     *     updated_by?: UserId|string
     * } $updates Participant updates
     * 
     * @return bool True if successfully updated
     * 
     * @throws \App\Domain\Chat\Exceptions\ParticipantNotFoundException When not participant
     * @throws \App\Domain\Chat\Exceptions\InsufficientPermissionsException When lacking permissions
     */
    public function updateParticipant(ChatId|string $chatId, UserId|string $userId, array $updates): bool;

    /**
     * Get chat participants with roles and metadata
     * 
     * Retrieves all participants of a chat including their roles, permissions,
     * join date, and activity statistics.
     * 
     * @param ChatId|string $chatId Target chat identifier
     * @param array{
     *     roles?: array<ParticipantRole|string>,
     *     active_only?: bool,
     *     with_stats?: bool,
     *     sort_by?: string
     * } $options Filtering and sorting options
     * 
     * @return Collection Collection of participant data with metadata
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     */
    public function getChatParticipants(ChatId|string $chatId, array $options = []): Collection;

    // ============================================================================
    // CHAT ANALYTICS AND STATISTICS
    // ============================================================================

    /**
     * Get comprehensive chat statistics
     * 
     * Retrieves detailed analytics and statistics for a chat including:
     * - Message counts and activity patterns
     * - Participant engagement metrics
     * - Peak activity times
     * - Content analysis summary
     * 
     * @param ChatId|string $chatId Target chat identifier
     * @param array{
     *     period?: string,
     *     start_date?: Carbon|string,
     *     end_date?: Carbon|string,
     *     include_participants?: bool,
     *     include_activity?: bool
     * } $options Statistics configuration
     * 
     * @return array{
     *     total_messages: int,
     *     active_participants: int,
     *     avg_response_time: float,
     *     peak_hours: array,
     *     engagement_score: float,
     *     content_summary: array,
     *     activity_timeline: array
     * } Comprehensive statistics array
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     */
    public function getChatStatistics(ChatId|string $chatId, array $options = []): array;

    /**
     * Get user's chat engagement metrics
     * 
     * Retrieves comprehensive engagement metrics for a user across all chats
     * including participation patterns, message frequency, and social metrics.
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     period?: string,
     *     chat_types?: array<ChatType|string>,
     *     include_rankings?: bool
     * } $options Metrics configuration
     * 
     * @return array{
     *     total_chats: int,
     *     messages_sent: int,
     *     avg_response_time: float,
     *     favorite_chat_type: string,
     *     engagement_score: float,
     *     social_ranking: int|null
     * } User engagement metrics
     * 
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     */
    public function getUserEngagementMetrics(UserId|string $userId, array $options = []): array;

    // ============================================================================
    // REAL-TIME AND SESSION MANAGEMENT
    // ============================================================================

    /**
     * Get active chat sessions for user
     * 
     * Retrieves all currently active chat sessions for a user including
     * real-time status, typing indicators, and presence information.
     * 
     * @param UserId|string $userId Target user identifier
     * 
     * @return Collection Active chat sessions with real-time data
     * 
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     */
    public function getActiveUserSessions(UserId|string $userId): Collection;

    /**
     * Update chat activity timestamp
     * 
     * Updates the last activity timestamp for a chat, essential for
     * real-time ordering and activity tracking.
     * 
     * @param ChatId|string $chatId Target chat identifier
     * @param Carbon|null $timestamp Optional custom timestamp
     * 
     * @return bool True if successfully updated
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     */
    public function updateLastActivity(ChatId|string $chatId, ?Carbon $timestamp = null): bool;

    /**
     * Mark chat as read for user
     * 
     * Updates read status for a user in a specific chat, clearing unread
     * message counters and updating notification state.
     * 
     * @param ChatId|string $chatId Target chat identifier
     * @param UserId|string $userId User marking as read
     * @param Carbon|null $readAt Optional custom read timestamp
     * 
     * @return bool True if successfully marked as read
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     * @throws \App\Domain\Chat\Exceptions\ParticipantNotFoundException When not participant
     */
    public function markAsRead(ChatId|string $chatId, UserId|string $userId, ?Carbon $readAt = null): bool;

    // ============================================================================
    // PREMIUM AND SPECIAL FEATURES
    // ============================================================================

    /**
     * Get premium chats for user
     * 
     * Retrieves chats with premium features enabled for a user, including
     * enhanced group chats, priority matching, and exclusive events.
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     feature_types?: array<string>,
     *     active_only?: bool,
     *     per_page?: int
     * } $options Premium chat filtering options
     * 
     * @return LengthAwarePaginator Premium chat results
     * 
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     */
    public function getPremiumChats(UserId|string $userId, array $options = []): LengthAwarePaginator;

    /**
     * Find chats by location proximity
     * 
     * Discovers nearby chats based on geographical location, essential for
     * Happy Hour events and location-based matching.
     * 
     * @param float $latitude User's latitude
     * @param float $longitude User's longitude
     * @param int $radiusKm Search radius in kilometers
     * @param array{
     *     types?: array<ChatType|string>,
     *     exclude_user?: UserId|string,
     *     min_participants?: int,
     *     per_page?: int
     * } $options Location search options
     * 
     * @return LengthAwarePaginator Nearby chats with distance information
     * 
     * @throws \App\Domain\Common\Exceptions\ValidationException When coordinates are invalid
     */
    public function findNearbyChats(float $latitude, float $longitude, int $radiusKm, array $options = []): LengthAwarePaginator;

    // ============================================================================
    // MODERATION AND ADMINISTRATION
    // ============================================================================

    /**
     * Get chats requiring moderation
     * 
     * Retrieves chats flagged for moderation review including reported content,
     * suspicious activity, and policy violations.
     * 
     * @param array{
     *     severity?: array<string>,
     *     types?: array<ChatType|string>,
     *     created_after?: Carbon|string,
     *     per_page?: int
     * } $filters Moderation filtering options
     * 
     * @return LengthAwarePaginator Chats requiring attention
     */
    public function getChatsForModeration(array $filters = []): LengthAwarePaginator;

    /**
     * Archive expired chats
     * 
     * Archives or deletes chats that have exceeded their expiration time,
     * performing proper cleanup and user notification.
     * 
     * @param int $batchSize Number of chats to process per batch
     * 
     * @return array{
     *     archived: int,
     *     deleted: int,
     *     errors: array
     * } Processing results summary
     */
    public function archiveExpiredChats(int $batchSize = 100): array;

    // ============================================================================
    // PERFORMANCE AND CACHING
    // ============================================================================

    /**
     * Warm cache for frequently accessed chats
     * 
     * Preloads frequently accessed chat data into cache for improved
     * performance during peak usage times.
     * 
     * @param array<ChatId|string> $chatIds Optional specific chats to cache
     * 
     * @return int Number of chats cached
     */
    public function warmCache(array $chatIds = []): int;

    /**
     * Clear chat-related caches
     * 
     * Clears all cached data related to specific chat or all chats,
     * useful after bulk operations or system maintenance.
     * 
     * @param ChatId|string|null $chatId Optional specific chat to clear
     * 
     * @return bool True if cache cleared successfully
     */
    public function clearCache(?ChatId $chatId = null): bool;

    // ============================================================================
    // BULK OPERATIONS
    // ============================================================================

    /**
     * Bulk update multiple chats
     * 
     * Performs efficient bulk updates on multiple chats with transaction
     * support and comprehensive error handling.
     * 
     * @param array<ChatId|string> $chatIds Chats to update
     * @param array $updates Common update data
     * 
     * @return array{
     *     updated: int,
     *     failed: array,
     *     errors: array
     * } Bulk operation results
     * 
     * @throws \App\Domain\Common\Exceptions\BulkOperationException When bulk operation fails
     */
    public function bulkUpdate(array $chatIds, array $updates): array;

    /**
     * Bulk delete multiple chats
     * 
     * Efficiently deletes multiple chats with proper cleanup and
     * participant notification.
     * 
     * @param array<ChatId|string> $chatIds Chats to delete
     * @param bool $force Whether to force permanent deletion
     * 
     * @return array{
     *     deleted: int,
     *     failed: array,
     *     errors: array
     * } Bulk deletion results
     * 
     * @throws \App\Domain\Common\Exceptions\BulkOperationException When bulk operation fails
     */
    public function bulkDelete(array $chatIds, bool $force = false): array;

    // ============================================================================
    // HAPPY HOUR EVENTS AND ANALYTICS
    // ============================================================================

    /**
     * Get user's event participation history
     * 
     * Retrieves comprehensive history of user's participation in events
     * including Happy Hour events with analytics and engagement data.
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     subtype?: string,
     *     include_analytics?: bool,
     *     date_range?: array{start: Carbon, end: Carbon}
     * } $options Query options for event history
     * 
     * @return Collection Event participation history with analytics
     * 
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     */
    public function getUserEventHistory(UserId|string $userId, array $options = []): Collection;

    /**
     * Get count of events hosted by user
     * 
     * Returns the number of events currently hosted by a user,
     * useful for enforcing hosting limits and permissions.
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     date_range?: array{start: Carbon, end: Carbon},
     *     event_type?: string
     * } $options Query options for hosted events count
     * 
     * @return int Number of events hosted by user
     * 
     * @throws \App\Domain\Auth\Exceptions\UserNotFoundException When user not found
     */
    public function getUserHostedEventCount(UserId|string $userId, array $options = []): int;

    /**
     * Update group analytics and statistics
     * 
     * Updates analytics data for group chats and events including
     * participant counts, engagement metrics, and activity statistics.
     * 
     * @param ChatId|string $chatId Target chat/event identifier
     * @param array{
     *     participant_count_updated?: Carbon,
     *     engagement_score?: float,
     *     activity_metrics?: array,
     *     completion_rate?: float
     * } $analyticsData Analytics data to update
     * 
     * @return bool True if successfully updated
     * 
     * @throws \App\Domain\Chat\Exceptions\ChatNotFoundException When chat not found
     */
    public function updateGroupAnalytics(ChatId|string $chatId, array $analyticsData): bool;

    // ============================================================================
    // VIDEO CALL MANAGEMENT
    // ============================================================================

    /**
     * Create a video call session
     * 
     * Creates a new video call session with WebRTC configuration,
     * participant management, and call settings.
     * 
     * @param array $callData Video call configuration data
     * @param array $options Additional options for call creation
     * 
     * @return array Created video call data
     * 
     * @throws \App\Domain\Chat\Exceptions\VideoCallException When call creation fails
     */
    public function createVideoCall(array $callData, array $options = []): array;

    /**
     * Find video call by ID
     * 
     * @param string $callId Video call identifier
     * 
     * @return array|null Video call data if found
     */
    public function findVideoCall(string $callId): ?array;

    /**
     * Update video call data
     * 
     * @param string $callId Video call identifier
     * @param array $updates Data to update
     * 
     * @return bool True if successfully updated
     */
    public function updateVideoCall(string $callId, array $updates): bool;

    /**
     * Update call participant data
     * 
     * @param string $callId Video call identifier
     * @param UserId|string $userId Participant user ID
     * @param array $participantData Participant data to update
     * 
     * @return bool True if successfully updated
     */
    public function updateCallParticipant(string $callId, UserId|string $userId, array $participantData): bool;

    /**
     * Update call settings
     * 
     * @param string $callId Video call identifier
     * @param array $settings Settings to update
     * @param array $options Update options
     * 
     * @return bool True if successfully updated
     */
    public function updateCallSettings(string $callId, array $settings, array $options = []): bool;

    /**
     * End video call
     * 
     * @param string $callId Video call identifier
     * @param array $endData End call data
     * 
     * @return bool True if successfully ended
     */
    public function endVideoCall(string $callId, array $endData): bool;

    /**
     * Get user's active call
     * 
     * @param string $userId User identifier
     * 
     * @return array|null Active call data if found
     */
    public function getUserActiveCall(string $userId): ?array;

    /**
     * Get call history for user
     * 
     * @param UserId|string $userId User identifier
     * @param array $filters Call history filters
     * @param array $options Query options
     * 
     * @return Collection Call history
     */
    public function getCallHistory(UserId|string $userId, array $filters, array $options = []): Collection;

    /**
     * Create message in chat
     * 
     * @param array $messageData Message data
     * 
     * @return array Created message data
     */
    public function createMessage(array $messageData): array;
}