<?php

declare(strict_types=1);

namespace App\Domain\Matching\Repositories;

use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\Location;
use App\Domain\Shared\ValueObjects\DateRange;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Match Repository Interface
 * 
 * Provides comprehensive repository contracts for match management operations
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
 * - Comprehensive match lifecycle management
 * - Advanced search and filtering capabilities
 * - Analytics and metrics tracking
 * - Privacy and GDPR compliance
 * - Premium feature support
 * - Performance optimization contracts
 * - Real-time updates and broadcasting
 */
interface MatchRepositoryInterface
{
    /**
     * ========================================
     * CORE MATCH MANAGEMENT OPERATIONS
     * ========================================
     */

    /**
     * Create a new match between two users
     * 
     * @param UserId $userId1 First user ID
     * @param UserId $userId2 Second user ID
     * @param array $matchData Match metadata and compatibility data
     * @param array $options Additional options (analytics, notifications, etc.)
     * @return array Match data with generated ID and timestamps
     * 
     * @throws \App\Domain\Matching\Exceptions\MatchAlreadyExistsException
     * @throws \App\Domain\Matching\Exceptions\UsersNotCompatibleException
     * @throws \App\Domain\Matching\Exceptions\UserBlockedException
     * 
     * Expected $matchData structure:
     * [
     *     'compatibility_score' => 0.85,
     *     'matching_factors' => ['interests', 'location', 'age'],
     *     'algorithm_version' => 'v2.1',
     *     'match_source' => 'discovery', // discovery, boost, super_like, etc.
     *     'metadata' => []
     * ]
     * 
     * Expected $options structure:
     * [
     *     'send_notifications' => true,
     *     'track_analytics' => true,
     *     'premium_features' => false,
     *     'priority_level' => 'normal' // normal, high, premium
     * ]
     */
    public function createMatch(
        UserId $userId1,
        UserId $userId2,
        array $matchData,
        array $options = []
    ): array;

    /**
     * Find a match by its unique identifier
     * 
     * @param string $matchId Match unique identifier
     * @param array $options Loading options (with relations, cache, etc.)
     * @return array|null Match data or null if not found
     * 
     * Expected $options structure:
     * [
     *     'with_users' => true,
     *     'with_messages' => false,
     *     'with_analytics' => false,
     *     'cache_ttl' => 300
     * ]
     */
    public function findById(string $matchId, array $options = []): ?array;

    /**
     * Update match data and metadata
     * 
     * @param string $matchId Match identifier
     * @param array $updateData Data to update
     * @param array $options Update options
     * @return bool Success status
     * 
     * @throws \App\Domain\Matching\Exceptions\MatchNotFoundException
     * @throws \App\Domain\Matching\Exceptions\MatchReadOnlyException
     * 
     * Expected $updateData structure:
     * [
     *     'last_activity' => Carbon::now(),
     *     'conversation_starter' => 'Hello!',
     *     'match_quality_score' => 0.92,
     *     'engagement_metrics' => [],
     *     'metadata' => []
     * ]
     */
    public function updateMatch(
        string $matchId,
        array $updateData,
        array $options = []
    ): bool;

    /**
     * Remove/unmatch a specific match
     * 
     * @param string $matchId Match identifier
     * @param UserId $requestingUserId User requesting the unmatch
     * @param array $options Unmatch options
     * @return bool Success status
     * 
     * @throws \App\Domain\Matching\Exceptions\MatchNotFoundException
     * @throws \App\Domain\Matching\Exceptions\UnauthorizedUnmatchException
     * 
     * Expected $options structure:
     * [
     *     'reason' => 'not_interested',
     *     'cleanup_messages' => true,
     *     'block_user' => false,
     *     'feedback_provided' => false,
     *     'gdpr_compliance' => true
     * ]
     */
    public function removeMatch(
        string $matchId,
        UserId $requestingUserId,
        array $options = []
    ): bool;

    /**
     * ========================================
     * USER MATCH RETRIEVAL OPERATIONS
     * ========================================
     */

    /**
     * Get all matches for a specific user
     * 
     * @param UserId $userId User identifier
     * @param array $filters Filtering options
     * @param array $sorting Sorting preferences
     * @param int $page Page number for pagination
     * @param int $perPage Items per page
     * @return LengthAwarePaginator Paginated match results
     * 
     * Expected $filters structure:
     * [
     *     'status' => 'active', // active, inactive, archived
     *     'date_range' => DateRange::lastWeek(),
     *     'min_compatibility' => 0.7,
     *     'has_messages' => true,
     *     'match_source' => ['discovery', 'boost'],
     *     'location_radius' => 50
     * ]
     * 
     * Expected $sorting structure:
     * [
     *     'field' => 'last_activity', // last_activity, compatibility_score, created_at
     *     'direction' => 'desc',
     *     'secondary_field' => 'compatibility_score'
     * ]
     */
    public function getUserMatches(
        UserId $userId,
        array $filters = [],
        array $sorting = [],
        int $page = 1,
        int $perPage = 20
    ): LengthAwarePaginator;

    /**
     * Get new/unviewed matches for a user
     * 
     * @param UserId $userId User identifier
     * @param int $limit Maximum number of matches
     * @param array $options Additional options
     * @return Collection New matches collection
     * 
     * Expected $options structure:
     * [
     *     'include_premium' => false,
     *     'priority_order' => true,
     *     'with_icebreakers' => true,
     *     'personalized' => true
     * ]
     */
    public function getNewMatches(
        UserId $userId,
        int $limit = 10,
        array $options = []
    ): Collection;

    /**
     * Get active matches with recent activity
     * 
     * @param UserId $userId User identifier
     * @param int $hoursThreshold Activity threshold in hours
     * @param int $limit Maximum number of matches
     * @return Collection Active matches collection
     */
    public function getActiveMatches(
        UserId $userId,
        int $hoursThreshold = 24,
        int $limit = 50
    ): Collection;

    /**
     * Get matches by compatibility score range
     * 
     * @param UserId $userId User identifier
     * @param float $minScore Minimum compatibility score
     * @param float $maxScore Maximum compatibility score
     * @param int $limit Maximum number of matches
     * @return Collection High-compatibility matches
     */
    public function getMatchesByCompatibility(
        UserId $userId,
        float $minScore = 0.8,
        float $maxScore = 1.0,
        int $limit = 20
    ): Collection;

    /**
     * ========================================
     * MATCH EXISTENCE AND VALIDATION
     * ========================================
     */

    /**
     * Check if a match exists between two users
     * 
     * @param UserId $userId1 First user ID
     * @param UserId $userId2 Second user ID
     * @param array $options Check options
     * @return bool Match existence status
     * 
     * Expected $options structure:
     * [
     *     'include_inactive' => false,
     *     'cache_result' => true,
     *     'check_blocks' => true
     * ]
     */
    public function matchExists(
        UserId $userId1,
        UserId $userId2,
        array $options = []
    ): bool;

    /**
     * Get match between two specific users
     * 
     * @param UserId $userId1 First user ID
     * @param UserId $userId2 Second user ID
     * @param array $options Loading options
     * @return array|null Match data or null
     */
    public function getMatchBetweenUsers(
        UserId $userId1,
        UserId $userId2,
        array $options = []
    ): ?array;

    /**
     * Validate match eligibility between users
     * 
     * @param UserId $userId1 First user ID
     * @param UserId $userId2 Second user ID
     * @param array $criteria Validation criteria
     * @return array Validation result with details
     * 
     * Expected return structure:
     * [
     *     'eligible' => true,
     *     'reasons' => [],
     *     'compatibility_score' => 0.85,
     *     'blocking_factors' => [],
     *     'recommendations' => []
     * ]
     */
    public function validateMatchEligibility(
        UserId $userId1,
        UserId $userId2,
        array $criteria = []
    ): array;

    /**
     * ========================================
     * SEARCH AND DISCOVERY OPERATIONS
     * ========================================
     */

    /**
     * Search matches by various criteria
     * 
     * @param UserId $userId User performing search
     * @param array $searchCriteria Search parameters
     * @param array $options Search options
     * @return Collection Search results
     * 
     * Expected $searchCriteria structure:
     * [
     *     'age_range' => [25, 35],
     *     'distance' => 50,
     *     'interests' => ['hiking', 'photography'],
     *     'education_level' => 'university',
     *     'profession' => 'engineer',
     *     'relationship_goals' => 'serious',
     *     'lifestyle' => ['non_smoker', 'social_drinker']
     * ]
     * 
     * Expected $options structure:
     * [
     *     'algorithm' => 'ml_enhanced', // basic, advanced, ml_enhanced
     *     'boost_premium' => false,
     *     'exclude_shown' => true,
     *     'personalization' => true,
     *     'diversity_factor' => 0.3
     * ]
     */
    public function searchMatches(
        UserId $userId,
        array $searchCriteria,
        array $options = []
    ): Collection;

    /**
     * Find potential matches within location radius
     * 
     * @param UserId $userId User identifier
     * @param Location $location Center location
     * @param float $radiusKm Search radius in kilometers
     * @param array $filters Additional filters
     * @param int $limit Maximum results
     * @return Collection Location-based matches
     */
    public function findMatchesNearLocation(
        UserId $userId,
        Location $location,
        float $radiusKm,
        array $filters = [],
        int $limit = 50
    ): Collection;

    /**
     * Get recommended matches using ML algorithms
     * 
     * @param UserId $userId User identifier
     * @param array $preferences User preferences
     * @param int $limit Maximum recommendations
     * @param array $options ML options
     * @return Collection ML-recommended matches
     * 
     * Expected $options structure:
     * [
     *     'model_version' => 'v2.1',
     *     'include_explanation' => false,
     *     'diversity_boost' => true,
     *     'real_time_features' => true,
     *     'cold_start_handling' => true
     * ]
     */
    public function getRecommendedMatches(
        UserId $userId,
        array $preferences = [],
        int $limit = 20,
        array $options = []
    ): Collection;

    /**
     * ========================================
     * MATCH ANALYTICS AND METRICS
     * ========================================
     */

    /**
     * Get match statistics for a user
     * 
     * @param UserId $userId User identifier
     * @param DateRange $dateRange Analysis period
     * @param array $metrics Specific metrics to calculate
     * @return array Comprehensive match statistics
     * 
     * Expected return structure:
     * [
     *     'total_matches' => 45,
     *     'new_matches' => 8,
     *     'active_conversations' => 12,
     *     'average_compatibility' => 0.78,
     *     'match_sources' => ['discovery' => 30, 'boost' => 15],
     *     'response_rate' => 0.65,
     *     'conversation_conversion' => 0.45,
     *     'premium_matches' => 5
     * ]
     */
    public function getMatchStatistics(
        UserId $userId,
        DateRange $dateRange,
        array $metrics = []
    ): array;

    /**
     * Get match quality metrics and insights
     * 
     * @param UserId $userId User identifier
     * @param array $options Analysis options
     * @return array Quality metrics and insights
     * 
     * Expected return structure:
     * [
     *     'overall_quality_score' => 0.82,
     *     'compatibility_distribution' => [],
     *     'engagement_patterns' => [],
     *     'success_factors' => [],
     *     'improvement_suggestions' => [],
     *     'trend_analysis' => []
     * ]
     */
    public function getMatchQualityMetrics(
        UserId $userId,
        array $options = []
    ): array;

    /**
     * Track match interaction events
     * 
     * @param string $matchId Match identifier
     * @param string $eventType Type of interaction
     * @param array $eventData Event details
     * @param array $options Tracking options
     * @return bool Success status
     * 
     * Event types: view, message_sent, profile_visited, photo_liked, etc.
     */
    public function trackMatchInteraction(
        string $matchId,
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
     * Create multiple matches in batch
     * 
     * @param array $matchesData Array of match data
     * @param array $options Batch options
     * @return array Batch operation results
     * 
     * Expected $matchesData structure:
     * [
     *     [
     *         'user_id_1' => 'uuid1',
     *         'user_id_2' => 'uuid2',
     *         'compatibility_score' => 0.85,
     *         'match_data' => []
     *     ],
     *     // ... more matches
     * ]
     */
    public function createBatchMatches(
        array $matchesData,
        array $options = []
    ): array;

    /**
     * Update multiple matches in batch
     * 
     * @param array $updates Array of match updates
     * @param array $options Batch options
     * @return array Batch update results
     */
    public function updateBatchMatches(
        array $updates,
        array $options = []
    ): array;

    /**
     * Archive old inactive matches
     * 
     * @param int $inactiveDays Days of inactivity threshold
     * @param array $options Archive options
     * @return array Archive operation results
     */
    public function archiveInactiveMatches(
        int $inactiveDays = 90,
        array $options = []
    ): array;

    /**
     * ========================================
     * PREMIUM AND ADVANCED FEATURES
     * ========================================
     */

    /**
     * Get premium matches for subscribed users
     * 
     * @param UserId $userId Premium user identifier
     * @param array $premiumFilters Premium filtering options
     * @param int $limit Maximum premium matches
     * @return Collection Premium matches collection
     * 
     * Expected $premiumFilters structure:
     * [
     *     'verified_profiles' => true,
     *     'high_quality_photos' => true,
     *     'active_recently' => 48, // hours
     *     'premium_algorithm' => true,
     *     'boost_visibility' => true
     * ]
     */
    public function getPremiumMatches(
        UserId $userId,
        array $premiumFilters = [],
        int $limit = 30
    ): Collection;

    /**
     * Apply boost to user matches visibility
     * 
     * @param UserId $userId User to boost
     * @param int $durationHours Boost duration in hours
     * @param array $boostOptions Boost configuration
     * @return array Boost application results
     */
    public function applyMatchBoost(
        UserId $userId,
        int $durationHours,
        array $boostOptions = []
    ): array;

    /**
     * Get super like matches (premium feature)
     * 
     * @param UserId $userId User identifier
     * @param array $options Super like options
     * @return Collection Super like matches
     */
    public function getSuperLikeMatches(
        UserId $userId,
        array $options = []
    ): Collection;

    /**
     * ========================================
     * PRIVACY AND GDPR COMPLIANCE
     * ========================================
     */

    /**
     * Anonymize user data in matches (GDPR compliance)
     * 
     * @param UserId $userId User requesting anonymization
     * @param array $options Anonymization options
     * @return array Anonymization results
     * 
     * Expected $options structure:
     * [
     *     'keep_analytics' => false,
     *     'notify_matches' => true,
     *     'cleanup_messages' => true,
     *     'retention_period' => 30 // days
     * ]
     */
    public function anonymizeUserMatches(
        UserId $userId,
        array $options = []
    ): array;

    /**
     * Export user match data (GDPR data portability)
     * 
     * @param UserId $userId User requesting export
     * @param array $exportOptions Export configuration
     * @return array Export data and metadata
     */
    public function exportUserMatchData(
        UserId $userId,
        array $exportOptions = []
    ): array;

    /**
     * Apply privacy settings to match visibility
     * 
     * @param UserId $userId User identifier
     * @param array $privacySettings Privacy configuration
     * @return bool Success status
     */
    public function applyPrivacySettings(
        UserId $userId,
        array $privacySettings
    ): bool;

    /**
     * ========================================
     * PERFORMANCE AND CACHING
     * ========================================
     */

    /**
     * Cache frequently accessed match data
     * 
     * @param UserId $userId User identifier
     * @param array $cacheOptions Cache configuration
     * @return bool Success status
     */
    public function cacheUserMatches(
        UserId $userId,
        array $cacheOptions = []
    ): bool;

    /**
     * Invalidate match cache for user
     * 
     * @param UserId $userId User identifier
     * @param array $invalidationOptions Cache invalidation options
     * @return bool Success status
     */
    public function invalidateMatchCache(
        UserId $userId,
        array $invalidationOptions = []
    ): bool;

    /**
     * Preload match data for performance
     * 
     * @param UserId $userId User identifier
     * @param array $preloadOptions Preload configuration
     * @return bool Success status
     */
    public function preloadMatchData(
        UserId $userId,
        array $preloadOptions = []
    ): bool;

    /**
     * ========================================
     * DEBUGGING AND MONITORING
     * ========================================
     */

    /**
     * Get match system health metrics
     * 
     * @param array $healthChecks Specific health checks to perform
     * @return array System health status
     */
    public function getSystemHealthMetrics(array $healthChecks = []): array;

    /**
     * Get detailed match algorithm performance
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
     * Log match operation for debugging
     * 
     * @param string $operation Operation name
     * @param array $context Operation context
     * @param array $options Logging options
     * @return bool Success status
     */
    public function logMatchOperation(
        string $operation,
        array $context,
        array $options = []
    ): bool;
}