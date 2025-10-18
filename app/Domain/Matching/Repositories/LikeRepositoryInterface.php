<?php

declare(strict_types=1);

namespace App\Domain\Matching\Repositories;

use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\DateRange;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Like Repository Interface
 * 
 * Provides comprehensive repository contracts for like/dislike operations
 * following Clean Architecture and DDD principles with strict type safety.
 * 
 * @package App\Domain\Matching\Repositories
 * @author ForeverUsInLove Team
 * @copyright 2024 ForeverUsInLove
 * @license MIT
 * 
 * @version 1.0.0
 * @since 2024-01-20
 * 
 * Architecture: Clean Architecture / Hexagonal Architecture
 * Pattern: Repository Pattern with Domain-Driven Design
 * 
 * Dependencies:
 * - Domain\Shared\ValueObjects for type safety
 * - Laravel Collections and Pagination
 * - Carbon for date handling
 * 
 * Key Features:
 * - Comprehensive like/dislike lifecycle management
 * - Super likes and premium interactions
 * - Mutual match detection and processing
 * - Rate limiting and abuse prevention
 * - Analytics and engagement tracking
 * - Privacy and GDPR compliance
 * - Undo and rewind functionality
 * - Batch processing operations
 * - Real-time notifications and events
 */
interface LikeRepositoryInterface
{
    /**
     * ========================================
     * CORE LIKE/DISLIKE OPERATIONS
     * ========================================
     */

    /**
     * Record a like from one user to another
     * 
     * @param UserId $fromUserId User giving the like
     * @param UserId $toUserId User receiving the like
     * @param array $likeData Like metadata and context
     * @param array $options Additional options
     * @return array Created like data with ID and timestamps
     * 
     * @throws \App\Domain\Matching\Exceptions\LikeAlreadyExistsException
     * @throws \App\Domain\Matching\Exceptions\SelfLikeNotAllowedException
     * @throws \App\Domain\Matching\Exceptions\UserBlockedException
     * @throws \App\Domain\Matching\Exceptions\RateLimitExceededException
     * 
     * Expected $likeData structure:
     * [
     *     'type' => 'standard', // standard, super_like, boost_like
     *     'source' => 'discovery', // discovery, search, profile_visit
     *     'interaction_data' => [
     *         'profile_view_duration' => 45, // seconds
     *         'photos_viewed' => [1, 2, 3],
     *         'sections_viewed' => ['photos', 'bio', 'interests'],
     *         'swipe_velocity' => 'slow' // slow, medium, fast
     *     ],
     *     'context' => [
     *         'session_id' => 'session_uuid',
     *         'device_info' => 'iOS 17.0',
     *         'location_at_like' => ['lat' => 40.7128, 'lng' => -74.0060],
     *         'time_of_day' => 'evening'
     *     ],
     *     'premium_features_used' => false,
     *     'algorithm_confidence' => 0.78
     * ]
     * 
     * Expected $options structure:
     * [
     *     'check_mutual' => true,
     *     'send_notifications' => true,
     *     'track_analytics' => true,
     *     'validate_rate_limits' => true,
     *     'premium_boost' => false
     * ]
     */
    public function createLike(
        UserId $fromUserId,
        UserId $toUserId,
        array $likeData,
        array $options = []
    ): array;

    /**
     * Record a dislike from one user to another
     * 
     * @param UserId $fromUserId User giving the dislike
     * @param UserId $toUserId User receiving the dislike
     * @param array $dislikeData Dislike metadata and context
     * @param array $options Additional options
     * @return array Created dislike data
     * 
     * Expected $dislikeData structure:
     * [
     *     'reason' => 'not_interested', // not_interested, inappropriate, fake, other
     *     'feedback' => 'Optional user feedback',
     *     'interaction_data' => [
     *         'profile_view_duration' => 5, // seconds
     *         'photos_viewed' => [1],
     *         'swipe_direction' => 'left',
     *         'swipe_velocity' => 'fast'
     *     ],
     *     'report_concerns' => false,
     *     'block_user' => false
     * ]
     */
    public function createDislike(
        UserId $fromUserId,
        UserId $toUserId,
        array $dislikeData,
        array $options = []
    ): array;

    /**
     * Record a super like (premium feature)
     * 
     * @param UserId $fromUserId User giving the super like
     * @param UserId $toUserId User receiving the super like
     * @param array $superLikeData Super like specific data
     * @param array $options Additional options
     * @return array Created super like data
     * 
     * @throws \App\Domain\Matching\Exceptions\SuperLikeQuotaExceededException
     * @throws \App\Domain\Matching\Exceptions\PremiumFeatureRequiredException
     * 
     * Expected $superLikeData structure:
     * [
     *     'message' => 'Optional personalized message',
     *     'highlight_feature' => 'photo_3', // Specific photo or feature to highlight
     *     'premium_tier' => 'gold', // gold, platinum, diamond
     *     'boost_visibility' => true,
     *     'priority_notification' => true
     * ]
     */
    public function createSuperLike(
        UserId $fromUserId,
        UserId $toUserId,
        array $superLikeData,
        array $options = []
    ): array;

    /**
     * ========================================
     * LIKE RETRIEVAL AND QUERIES
     * ========================================
     */

    /**
     * Find a specific like by ID
     * 
     * @param string $likeId Like unique identifier
     * @param array $options Loading options
     * @return array|null Like data or null if not found
     */
    public function findById(string $likeId, array $options = []): ?array;

    /**
     * Get like between two specific users
     * 
     * @param UserId $fromUserId User who gave the like
     * @param UserId $toUserId User who received the like
     * @param array $options Query options
     * @return array|null Like data or null
     * 
     * Expected $options structure:
     * [
     *     'include_dislikes' => false,
     *     'include_expired' => false,
     *     'with_interaction_data' => false
     * ]
     */
    public function getLikeBetweenUsers(
        UserId $fromUserId,
        UserId $toUserId,
        array $options = []
    ): ?array;

    /**
     * Check if a like exists between two users
     * 
     * @param UserId $fromUserId User who might have liked
     * @param UserId $toUserId User who might have been liked
     * @param array $options Check options
     * @return bool Like existence status
     * 
     * Expected $options structure:
     * [
     *     'type' => null, // null for any, 'standard', 'super_like'
     *     'include_expired' => false,
     *     'cache_result' => true
     * ]
     */
    public function likeExists(
        UserId $fromUserId,
        UserId $toUserId,
        array $options = []
    ): bool;

    /**
     * Get all likes given by a user
     * 
     * @param UserId $userId User identifier
     * @param array $filters Filter options
     * @param array $sorting Sorting options
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return LengthAwarePaginator Paginated likes given
     * 
     * Expected $filters structure:
     * [
     *     'type' => ['standard', 'super_like'],
     *     'date_range' => DateRange::lastMonth(),
     *     'mutual_only' => false,
     *     'has_match' => null, // null, true, false
     *     'response_status' => null // null, 'pending', 'liked_back', 'not_interested'
     * ]
     */
    public function getLikesGiven(
        UserId $userId,
        array $filters = [],
        array $sorting = [],
        int $page = 1,
        int $perPage = 20
    ): LengthAwarePaginator;

    /**
     * Get all likes received by a user
     * 
     * @param UserId $userId User identifier
     * @param array $filters Filter options
     * @param array $sorting Sorting options
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return LengthAwarePaginator Paginated likes received
     * 
     * Expected $filters structure:
     * [
     *     'type' => ['standard', 'super_like'],
     *     'date_range' => DateRange::lastWeek(),
     *     'unread_only' => false,
     *     'premium_only' => false,
     *     'from_verified' => false
     * ]
     */
    public function getLikesReceived(
        UserId $userId,
        array $filters = [],
        array $sorting = [],
        int $page = 1,
        int $perPage = 20
    ): LengthAwarePaginator;

    /**
     * Get new/unread likes received by a user
     * 
     * @param UserId $userId User identifier
     * @param int $limit Maximum number of likes
     * @param array $options Additional options
     * @return Collection New likes collection
     * 
     * Expected $options structure:
     * [
     *     'prioritize_super_likes' => true,
     *     'include_premium' => true,
     *     'with_preview_data' => true,
     *     'sort_by_compatibility' => false
     * ]
     */
    public function getNewLikesReceived(
        UserId $userId,
        int $limit = 10,
        array $options = []
    ): Collection;

    /**
     * ========================================
     * MUTUAL LIKES AND MATCH DETECTION
     * ========================================
     */

    /**
     * Check for mutual likes between two users
     * 
     * @param UserId $userId1 First user ID
     * @param UserId $userId2 Second user ID
     * @param array $options Check options
     * @return array Mutual like status and details
     * 
     * Expected return structure:
     * [
     *     'is_mutual' => true,
     *     'match_eligible' => true,
     *     'like_1' => [], // Like from user1 to user2
     *     'like_2' => [], // Like from user2 to user1
     *     'compatibility_score' => 0.85,
     *     'match_quality' => 'high' // low, medium, high, premium
     * ]
     */
    public function checkMutualLike(
        UserId $userId1,
        UserId $userId2,
        array $options = []
    ): array;

    /**
     * Get all mutual likes for a user (potential matches)
     * 
     * @param UserId $userId User identifier
     * @param array $filters Filter options
     * @param int $limit Maximum results
     * @return Collection Mutual likes collection
     * 
     * Expected $filters structure:
     * [
     *     'unprocessed_only' => true,
     *     'date_range' => DateRange::lastDay(),
     *     'min_compatibility' => 0.7,
     *     'include_super_likes' => true
     * ]
     */
    public function getMutualLikes(
        UserId $userId,
        array $filters = [],
        int $limit = 50
    ): Collection;

    /**
     * Process mutual likes to create matches
     * 
     * @param array $mutualLikeIds Array of mutual like IDs to process
     * @param array $options Processing options
     * @return array Processing results
     * 
     * Expected return structure:
     * [
     *     'processed_count' => 5,
     *     'matches_created' => 4,
     *     'errors' => [],
     *     'notifications_sent' => 8
     * ]
     */
    public function processMutualLikesToMatches(
        array $mutualLikeIds,
        array $options = []
    ): array;

    /**
     * ========================================
     * UNDO AND REWIND OPERATIONS
     * ========================================
     */

    /**
     * Undo a like (premium feature)
     * 
     * @param string $likeId Like identifier to undo
     * @param UserId $requestingUserId User requesting the undo
     * @param array $options Undo options
     * @return bool Success status
     * 
     * @throws \App\Domain\Matching\Exceptions\LikeNotFoundException
     * @throws \App\Domain\Matching\Exceptions\UndoTimeExpiredException
     * @throws \App\Domain\Matching\Exceptions\PremiumFeatureRequiredException
     * 
     * Expected $options structure:
     * [
     *     'reason' => 'accidental_swipe',
     *     'restore_visibility' => true,
     *     'refund_super_like' => false,
     *     'track_analytics' => true
     * ]
     */
    public function undoLike(
        string $likeId,
        UserId $requestingUserId,
        array $options = []
    ): bool;

    /**
     * Get undoable likes for a user
     * 
     * @param UserId $userId User identifier
     * @param int $hoursLimit Undo time limit in hours
     * @param int $limit Maximum undoable likes
     * @return Collection Undoable likes collection
     */
    public function getUndoableLikes(
        UserId $userId,
        int $hoursLimit = 24,
        int $limit = 10
    ): Collection;

    /**
     * Rewind user's like history (premium feature)
     * 
     * @param UserId $userId User identifier
     * @param int $stepsBack Number of likes to rewind
     * @param array $options Rewind options
     * @return array Rewind results
     * 
     * @throws \App\Domain\Matching\Exceptions\PremiumFeatureRequiredException
     * @throws \App\Domain\Matching\Exceptions\RewindLimitExceededException
     */
    public function rewindLikeHistory(
        UserId $userId,
        int $stepsBack,
        array $options = []
    ): array;

    /**
     * ========================================
     * RATE LIMITING AND QUOTA MANAGEMENT
     * ========================================
     */

    /**
     * Check if user has reached like limit
     * 
     * @param UserId $userId User identifier
     * @param string $timeWindow Time window ('hour', 'day', 'week')
     * @param array $options Check options
     * @return array Rate limit status
     * 
     * Expected return structure:
     * [
     *     'limit_reached' => false,
     *     'current_count' => 45,
     *     'limit' => 100,
     *     'reset_time' => Carbon::tomorrow(),
     *     'premium_limits' => 500,
     *     'time_until_reset' => 18000 // seconds
     * ]
     */
    public function checkRateLimit(
        UserId $userId,
        string $timeWindow = 'day',
        array $options = []
    ): array;

    /**
     * Get user's like quotas and usage
     * 
     * @param UserId $userId User identifier
     * @param array $quotaTypes Types of quotas to check
     * @return array Quota information
     * 
     * Expected return structure:
     * [
     *     'standard_likes' => ['used' => 45, 'limit' => 100, 'reset' => Carbon::tomorrow()],
     *     'super_likes' => ['used' => 2, 'limit' => 5, 'reset' => Carbon::tomorrow()],
     *     'rewinds' => ['used' => 1, 'limit' => 3, 'reset' => Carbon::nextWeek()],
     *     'premium_tier' => 'gold'
     * ]
     */
    public function getUserQuotas(
        UserId $userId,
        array $quotaTypes = []
    ): array;

    /**
     * Reset user's like quotas
     * 
     * @param UserId $userId User identifier
     * @param array $quotaTypes Quotas to reset
     * @param array $options Reset options
     * @return bool Success status
     */
    public function resetUserQuotas(
        UserId $userId,
        array $quotaTypes = [],
        array $options = []
    ): bool;

    /**
     * ========================================
     * ANALYTICS AND ENGAGEMENT TRACKING
     * ========================================
     */

    /**
     * Get like statistics for a user
     * 
     * @param UserId $userId User identifier
     * @param DateRange $dateRange Analysis period
     * @param array $metrics Specific metrics to calculate
     * @return array Comprehensive like statistics
     * 
     * Expected return structure:
     * [
     *     'likes_given' => 156,
     *     'likes_received' => 89,
     *     'super_likes_given' => 12,
     *     'super_likes_received' => 7,
     *     'mutual_likes' => 23,
     *     'like_back_rate' => 0.35,
     *     'super_like_success_rate' => 0.58,
     *     'average_response_time' => 4.2, // hours
     *     'engagement_score' => 0.72,
     *     'trending_direction' => 'up' // up, down, stable
     * ]
     */
    public function getLikeStatistics(
        UserId $userId,
        DateRange $dateRange,
        array $metrics = []
    ): array;

    /**
     * Get engagement patterns and insights
     * 
     * @param UserId $userId User identifier
     * @param array $options Analysis options
     * @return array Engagement insights
     * 
     * Expected return structure:
     * [
     *     'peak_activity_hours' => [19, 20, 21],
     *     'preferred_like_types' => ['standard' => 0.8, 'super_like' => 0.2],
     *     'interaction_quality' => 0.78,
     *     'profile_optimization_tips' => [],
     *     'behavioral_patterns' => [],
     *     'success_predictions' => []
     * ]
     */
    public function getEngagementInsights(
        UserId $userId,
        array $options = []
    ): array;

    /**
     * Track like interaction event
     * 
     * @param string $likeId Like identifier
     * @param string $eventType Type of interaction
     * @param array $eventData Event details
     * @param array $options Tracking options
     * @return bool Success status
     * 
     * Event types: viewed, responded, profile_visited, photo_zoomed, etc.
     */
    public function trackLikeInteraction(
        string $likeId,
        string $eventType,
        array $eventData,
        array $options = []
    ): bool;

    /**
     * ========================================
     * BATCH AND BULK OPERATIONS
     * ========================================
     */

    /**
     * Create multiple likes in batch
     * 
     * @param array $likesData Array of like data
     * @param array $options Batch options
     * @return array Batch operation results
     * 
     * Expected $likesData structure:
     * [
     *     [
     *         'from_user_id' => 'uuid1',
     *         'to_user_id' => 'uuid2',
     *         'type' => 'standard',
     *         'like_data' => []
     *     ],
     *     // ... more likes
     * ]
     */
    public function createBatchLikes(
        array $likesData,
        array $options = []
    ): array;

    /**
     * Mark multiple likes as viewed/read
     * 
     * @param array $likeIds Array of like IDs
     * @param UserId $userId User marking as read
     * @param array $options Batch read options
     * @return array Batch read results
     */
    public function markLikesAsRead(
        array $likeIds,
        UserId $userId,
        array $options = []
    ): array;

    /**
     * Archive old likes and dislikes
     * 
     * @param int $ageDays Age threshold in days
     * @param array $options Archive options
     * @return array Archive operation results
     */
    public function archiveOldLikes(
        int $ageDays = 180,
        array $options = []
    ): array;

    /**
     * ========================================
     * PREMIUM FEATURES AND SPECIAL OPERATIONS
     * ========================================
     */

    /**
     * Get super likes received with premium details
     * 
     * @param UserId $userId User identifier
     * @param array $filters Premium filters
     * @param int $limit Maximum results
     * @return Collection Premium super likes
     */
    public function getPremiumSuperLikes(
        UserId $userId,
        array $filters = [],
        int $limit = 20
    ): Collection;

    /**
     * Apply boost to user's likes visibility
     * 
     * @param UserId $userId User to boost
     * @param int $durationHours Boost duration
     * @param array $boostOptions Boost configuration
     * @return array Boost application results
     */
    public function applyLikeBoost(
        UserId $userId,
        int $durationHours,
        array $boostOptions = []
    ): array;

    /**
     * Get like recommendations using ML
     * 
     * @param UserId $userId User identifier
     * @param array $preferences User preferences
     * @param int $limit Maximum recommendations
     * @param array $options ML options
     * @return Collection ML-recommended likes
     */
    public function getRecommendedLikes(
        UserId $userId,
        array $preferences = [],
        int $limit = 20,
        array $options = []
    ): Collection;

    /**
     * ========================================
     * PRIVACY AND GDPR COMPLIANCE
     * ========================================
     */

    /**
     * Anonymize user data in likes (GDPR compliance)
     * 
     * @param UserId $userId User requesting anonymization
     * @param array $options Anonymization options
     * @return array Anonymization results
     * 
     * Expected $options structure:
     * [
     *     'keep_analytics' => false,
     *     'notify_affected_users' => true,
     *     'cleanup_interactions' => true,
     *     'retention_period' => 30 // days
     * ]
     */
    public function anonymizeUserLikes(
        UserId $userId,
        array $options = []
    ): array;

    /**
     * Export user like data (GDPR data portability)
     * 
     * @param UserId $userId User requesting export
     * @param array $exportOptions Export configuration
     * @return array Export data and metadata
     */
    public function exportUserLikeData(
        UserId $userId,
        array $exportOptions = []
    ): array;

    /**
     * Block user from seeing/liking (privacy feature)
     * 
     * @param UserId $blockingUserId User creating the block
     * @param UserId $blockedUserId User being blocked
     * @param array $blockOptions Block configuration
     * @return bool Success status
     */
    public function blockUserFromLikes(
        UserId $blockingUserId,
        UserId $blockedUserId,
        array $blockOptions = []
    ): bool;

    /**
     * ========================================
     * PERFORMANCE AND CACHING
     * ========================================
     */

    /**
     * Cache frequently accessed like data
     * 
     * @param UserId $userId User identifier
     * @param array $cacheOptions Cache configuration
     * @return bool Success status
     */
    public function cacheUserLikes(
        UserId $userId,
        array $cacheOptions = []
    ): bool;

    /**
     * Invalidate like cache for user
     * 
     * @param UserId $userId User identifier
     * @param array $invalidationOptions Cache invalidation options
     * @return bool Success status
     */
    public function invalidateLikeCache(
        UserId $userId,
        array $invalidationOptions = []
    ): bool;

    /**
     * Preload like data for performance
     * 
     * @param UserId $userId User identifier
     * @param array $preloadOptions Preload configuration
     * @return bool Success status
     */
    public function preloadLikeData(
        UserId $userId,
        array $preloadOptions = []
    ): bool;

    /**
     * ========================================
     * DEBUGGING AND MONITORING
     * ========================================
     */

    /**
     * Get like system health metrics
     * 
     * @param array $healthChecks Specific health checks to perform
     * @return array System health status
     */
    public function getSystemHealthMetrics(array $healthChecks = []): array;

    /**
     * Get like algorithm performance metrics
     * 
     * @param DateRange $period Analysis period
     * @param array $metrics Performance metrics to analyze
     * @return array Algorithm performance data
     */
    public function getAlgorithmPerformance(
        DateRange $period,
        array $metrics = []
    ): array;

    /**
     * Log like operation for debugging
     * 
     * @param string $operation Operation name
     * @param array $context Operation context
     * @param array $options Logging options
     * @return bool Success status
     */
    public function logLikeOperation(
        string $operation,
        array $context,
        array $options = []
    ): bool;
}