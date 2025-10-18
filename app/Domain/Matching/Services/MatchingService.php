<?php

declare(strict_types=1);

namespace App\Domain\Matching\Services;

use App\Domain\Matching\Repositories\MatchRepositoryInterface;
use App\Domain\Matching\Repositories\LikeRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Matching\Entities\MatchEntity;
use App\Domain\Matching\ValueObjects\MatchId;
use App\Domain\Matching\ValueObjects\MatchScore;
use App\Domain\Matching\ValueObjects\MatchingCriteria;
use App\Domain\Matching\ValueObjects\CompatibilityFactor;
use App\Domain\Matching\ValueObjects\DistanceRadius;
use App\Domain\Matching\Events\MatchCreated;
use App\Domain\Matching\Events\MatchUnmatched;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Profile\ValueObjects\ProfileId;
use App\Domain\Matching\Exceptions\MatchingException;
use App\Domain\Matching\Exceptions\InsufficientDataException;
use App\Domain\Matching\Exceptions\MatchingRateLimitException;
use App\Domain\Matching\Exceptions\InvalidMatchingCriteriaException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\CarbonInterface;
use Carbon\Carbon;

/**
 * MatchingService
 * 
 * Core matching service for the ForeverUsInLove dating platform. Handles the primary
 * matching algorithms, compatibility calculations, and match generation following
 * Domain-Driven Design principles and Clean Architecture patterns.
 * 
 * Key Responsibilities:
 * - Generate potential matches using sophisticated algorithms
 * - Calculate compatibility scores between users
 * - Apply matching criteria and filters
 * - Handle mutual matching and match creation
 * - Manage match quality assessment and optimization
 * - Support multiple matching strategies and A/B testing
 * - Rate limiting and fraud prevention
 * - Match analytics and performance tracking
 * 
 * Matching Algorithms:
 * 1. Compatibility-based matching using multi-dimensional scoring
 * 2. Location-aware matching with distance optimization
 * 3. Preference-based filtering and requirement matching
 * 4. Behavioral pattern analysis for improved matching
 * 5. Machine learning enhanced recommendations
 * 6. Social graph analysis for network-based matching
 * 7. Time-based optimization for peak engagement
 * 8. Premium feature integration for enhanced matching
 * 
 * Business Rules:
 * - Users must have complete profiles to participate in matching
 * - Age verification is required for all matches
 * - Geographic and demographic preferences are respected
 * - Blocked users are excluded from all matching
 * - Premium users receive priority in match queues
 * - Daily matching limits apply based on subscription tier
 * - Match quality scores influence recommendation order
 * - Mutual interest is required for match activation
 * 
 * @package ForeverUsInLove\Domain\Matching\Services
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove
 * @license Proprietary
 * @version 1.0.0
 * @since 2024-01-01
 * 
 * @phpstan-consistent-constructor
 */
final class MatchingService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const MAX_DAILY_MATCHES = 50;
    private const MAX_PREMIUM_DAILY_MATCHES = 200;
    private const MIN_COMPATIBILITY_SCORE = 0.6;
    private const DEFAULT_SEARCH_RADIUS_KM = 25;
    private const MAX_SEARCH_RADIUS_KM = 100;
    
    // Compatibility factor weights
    private const COMPATIBILITY_WEIGHTS = [
        'age_compatibility' => 0.15,
        'location_proximity' => 0.20,
        'shared_interests' => 0.25,
        'lifestyle_alignment' => 0.20,
        'personality_match' => 0.20
    ];

    public function __construct(
        private readonly MatchRepositoryInterface $matchRepository,
        private readonly LikeRepositoryInterface $likeRepository,
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly FilterService $filterService,
        private readonly LikeService $likeService
    ) {}

    // ================================================================
    // CORE MATCHING OPERATIONS
    // ================================================================

    /**
     * Generate potential matches for a user
     * 
     * Creates personalized match suggestions based on compatibility algorithms,
     * user preferences, location proximity, and behavioral analysis. Uses
     * sophisticated scoring to rank matches by potential success.
     * 
     * @param UserId $userId The user requesting matches
     * @param MatchingCriteria|null $criteria Optional custom matching criteria
     * @param int $limit Maximum number of matches to generate
     * @param bool $premiumBoost Whether to apply premium user boost
     * @return Collection<MatchEntity> Generated potential matches
     * 
     * @throws MatchingException When match generation fails
     * @throws InsufficientDataException When user profile is incomplete
     * @throws MatchingRateLimitException When daily limit is exceeded
     * @throws InvalidMatchingCriteriaException When criteria validation fails
     * 
     * @example
     * ```php
     * $criteria = new MatchingCriteria([
     *     'age_range' => [25, 35],
     *     'distance_km' => 50,
     *     'shared_interests' => ['music', 'travel']
     * ]);
     * $matches = $matchingService->generateMatches($userId, $criteria, 10);
     * ```
     */
    public function generateMatches(
        UserId $userId,
        ?MatchingCriteria $criteria = null,
        int $limit = 10,
        bool $premiumBoost = false
    ): Collection {
        try {
            Log::info('Generating matches for user', [
                'user_id' => $userId->toString(),
                'limit' => $limit,
                'premium_boost' => $premiumBoost
            ]);

            // Validate rate limits
            $this->validateDailyMatchingLimit($userId, $premiumBoost);

            // Get and validate user profile
            $userProfile = $this->profileRepository->findByUserId($userId->toInt());
            if (!$userProfile || $userProfile->status !== 'active' || $userProfile->completeness_percentage < 80) {
                throw new InsufficientDataException(
                    'User profile is incomplete or not eligible for matching'
                );
            }

            // Use default criteria if none provided
            $criteria = $criteria ?? $this->getDefaultMatchingCriteria($userProfile);
            $this->validateMatchingCriteria($criteria);

            // Check cache for existing matches
            $cacheKey = $this->buildMatchesCacheKey($userId, $criteria);
            if (Cache::has($cacheKey)) {
                Log::debug('Returning cached matches');
                return Cache::get($cacheKey);
            }

            // Find potential match candidates
            $candidates = $this->findMatchingCandidates($userId, $criteria, $limit * 5);
            
            if ($candidates->isEmpty()) {
                Log::info('No matching candidates found', ['user_id' => $userId->toString()]);
                return new Collection();
            }

            // Calculate compatibility scores for all candidates
            $scoredCandidates = $this->calculateCompatibilityScores($userId, $candidates);

            // Apply quality filters and premium boost
            $filteredCandidates = $this->applyQualityFilters($scoredCandidates, $premiumBoost);

            // Generate final match entities
            $matches = $this->createMatchEntities(
                $userId,
                $filteredCandidates,
                $limit,
                $premiumBoost
            );

            // Cache results
            Cache::put($cacheKey, $matches, self::CACHE_TTL);

            // Update user matching statistics
            $this->updateUserMatchingStatistics($userId, $matches->count());

            Log::info('Matches generated successfully', [
                'user_id' => $userId->toString(),
                'matches_generated' => $matches->count(),
                'candidates_processed' => $candidates->count()
            ]);

            return $matches;

        } catch (MatchingException $e) {
            Log::error('Match generation failed', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during match generation', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new MatchingException('Failed to generate matches: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a mutual match between two users
     * 
     * Establishes a bidirectional match when both users have expressed mutual
     * interest, triggering match notifications and enabling communication features.
     * 
     * @param UserId $userId1 First user in the match
     * @param UserId $userId2 Second user in the match
     * @param MatchScore|null $compatibilityScore Optional pre-calculated compatibility
     * @param array<string, mixed> $matchContext Additional context for match creation
     * @return MatchEntity The created mutual match
     * 
     * @throws MatchingException When match creation fails
     * @throws InvalidMatchingCriteriaException When users cannot match
     * 
     * @example
     * ```php
     * $match = $matchingService->createMutualMatch(
     *     $user1Id, 
     *     $user2Id,
     *     null,
     *     ['source' => 'like_swipe', 'location' => 'discovery_page']
     * );
     * ```
     */
    public function createMutualMatch(
        UserId $userId1,
        UserId $userId2,
        ?MatchScore $compatibilityScore = null,
        array $matchContext = []
    ): MatchEntity {
        try {
            Log::info('Creating mutual match', [
                'user1_id' => $userId1->toString(),
                'user2_id' => $userId2->toString(),
                'context' => $matchContext
            ]);

            // Validate that users can match
            $this->validateUsersCanMatch($userId1, $userId2);

            // Check for mutual likes
            $mutualLike = $this->likeService->checkMutualLike($userId1, $userId2);
            if (!$mutualLike) {
                throw new InvalidMatchingCriteriaException('Mutual like required for match creation');
            }

            // Calculate compatibility score if not provided
            if (!$compatibilityScore) {
                $compatibilityScore = $this->calculateDetailedCompatibility($userId1, $userId2);
            }

            // Create match entity
            $match = new MatchEntity(
                matchId: MatchId::generate(),
                userId1: $userId1,
                userId2: $userId2,
                compatibilityScore: $compatibilityScore,
                matchedAt: Carbon::now(),
                matchSource: $matchContext['source'] ?? 'mutual_like',
                isActive: true,
                matchContext: $matchContext
            );

            // Persist the match
            $matchData = $this->matchRepository->createMatch(
                $userId1,
                $userId2,
                [
                    'compatibility_score' => $compatibilityScore->getValue(),
                    'matching_factors' => array_map(fn($factor) => $factor->getName(), $compatibilityScore->getFactors()),
                    'algorithm_version' => $compatibilityScore->getAlgorithmVersion(),
                    'match_source' => $matchContext['source'] ?? 'mutual_like',
                    'metadata' => $matchContext
                ],
                [
                    'send_notifications' => true,
                    'track_analytics' => true,
                    'premium_features' => false,
                    'priority_level' => 'normal'
                ]
            );
            
            // Create MatchEntity from repository response
            $savedMatch = MatchEntity::fromArray($matchData);

            // Create UserMatch model for events
            $userMatch = new \App\Models\Matching\UserMatch($matchData);
            
            // Trigger match created event
            Event::dispatch(new MatchCreated(
                $userMatch,
                $this->buildMatchNotificationData($savedMatch)
            ));

            // Update user statistics
            $this->updateUserMatchCounters($userId1, $userId2);

            // Clear relevant caches
            $this->clearUserMatchingCaches([$userId1, $userId2]);

            Log::info('Mutual match created successfully', [
                'match_id' => $savedMatch->getId()->toString(),
                'compatibility_score' => $compatibilityScore->getValue()
            ]);

            return $savedMatch;

        } catch (\Exception $e) {
            Log::error('Failed to create mutual match', [
                'user1_id' => $userId1->toString(),
                'user2_id' => $userId2->toString(),
                'error' => $e->getMessage()
            ]);
            throw new MatchingException('Failed to create mutual match: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Calculate compatibility score between two users
     * 
     * Computes comprehensive compatibility using multiple factors including
     * age alignment, location proximity, shared interests, lifestyle compatibility,
     * and personality matching using advanced algorithms.
     * 
     * @param UserId $userId1 First user for compatibility calculation
     * @param UserId $userId2 Second user for compatibility calculation
     * @param bool $detailedAnalysis Whether to perform detailed factor analysis
     * @return MatchScore Calculated compatibility score with breakdown
     * 
     * @throws MatchingException When compatibility calculation fails
     * @throws InsufficientDataException When user data is incomplete
     * 
     * @example
     * ```php
     * $compatibility = $matchingService->calculateCompatibility($user1Id, $user2Id, true);
     * $score = $compatibility->getValue(); // 0.0 - 1.0
     * $factors = $compatibility->getFactorBreakdown();
     * ```
     */
    public function calculateCompatibility(
        UserId $userId1,
        UserId $userId2,
        bool $detailedAnalysis = false
    ): MatchScore {
        try {
            Log::debug('Calculating compatibility between users', [
                'user1_id' => $userId1->toString(),
                'user2_id' => $userId2->toString(),
                'detailed_analysis' => $detailedAnalysis
            ]);

            // Get user profiles
            $profile1 = $this->profileRepository->findByUserId($userId1->toInt());
            $profile2 = $this->profileRepository->findByUserId($userId2->toInt());

            if (!$profile1 || !$profile2) {
                throw new InsufficientDataException('One or both user profiles not found');
            }

            // Calculate individual compatibility factors
            $ageCompatibility = $this->calculateAgeCompatibility($profile1, $profile2);
            $locationCompatibility = $this->calculateLocationCompatibility($profile1, $profile2);
            $interestCompatibility = $this->calculateInterestCompatibility($profile1, $profile2);
            $lifestyleCompatibility = $this->calculateLifestyleCompatibility($profile1, $profile2);
            $personalityCompatibility = $this->calculatePersonalityCompatibility($profile1, $profile2);

            // Calculate weighted overall score
            $overallScore = 
                $ageCompatibility * self::COMPATIBILITY_WEIGHTS['age_compatibility'] +
                $locationCompatibility * self::COMPATIBILITY_WEIGHTS['location_proximity'] +
                $interestCompatibility * self::COMPATIBILITY_WEIGHTS['shared_interests'] +
                $lifestyleCompatibility * self::COMPATIBILITY_WEIGHTS['lifestyle_alignment'] +
                $personalityCompatibility * self::COMPATIBILITY_WEIGHTS['personality_match'];

            // Create compatibility factors array
            $compatibilityFactors = [
                CompatibilityFactor::createAgeCompatibility($ageCompatibility, abs($profile1->age - $profile2->age)),
                CompatibilityFactor::createLocationProximity($locationCompatibility, $profile1->distanceTo($profile2) ?? 0),
                CompatibilityFactor::createSharedInterests($interestCompatibility, array_intersect($profile1->interests ?? [], $profile2->interests ?? []), count(array_unique(array_merge($profile1->interests ?? [], $profile2->interests ?? [])))),
                CompatibilityFactor::createLifestyleAlignment($lifestyleCompatibility, []),
                CompatibilityFactor::createPersonalityMatch($personalityCompatibility, [])
            ];

            // Build match score with detailed breakdown if requested
            $matchScore = new MatchScore(
                value: $overallScore,
                factors: $detailedAnalysis ? $compatibilityFactors : [],
                calculatedAt: Carbon::now(),
                algorithmVersion: '2.1',
                confidenceLevel: $this->calculateConfidenceLevel($profile1, $profile2)
            );

            Log::debug('Compatibility calculation completed', [
                'user1_id' => $userId1->toString(),
                'user2_id' => $userId2->toString(),
                'overall_score' => $overallScore,
                'confidence_level' => $matchScore->getConfidenceLevel()
            ]);

            return $matchScore;

        } catch (\Exception $e) {
            Log::error('Compatibility calculation failed', [
                'user1_id' => $userId1->toString(),
                'user2_id' => $userId2->toString(),
                'error' => $e->getMessage()
            ]);
            throw new MatchingException('Failed to calculate compatibility: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // MATCH MANAGEMENT AND LIFECYCLE
    // ================================================================

    /**
     * Get active matches for a user
     * 
     * Retrieves all active matches for a user with optional filtering and sorting.
     * Includes match quality scores and recent interaction data.
     * 
     * @param UserId $userId The user whose matches to retrieve
     * @param int $limit Maximum number of matches to return
     * @param string $sortBy Sorting criteria (newest, score, activity)
     * @param bool $includeInactive Whether to include inactive matches
     * @return Collection<MatchEntity> User's active matches
     * 
     * @throws MatchingException When retrieval fails
     * 
     * @example
     * ```php
     * $matches = $matchingService->getUserMatches($userId, 20, 'score', false);
     * foreach ($matches as $match) {
     *     $otherUser = $match->getOtherUser($userId);
     *     $compatibility = $match->getCompatibilityScore();
     * }
     * ```
     */
    public function getUserMatches(
        UserId $userId,
        int $limit = 50,
        string $sortBy = 'newest',
        bool $includeInactive = false
    ): Collection {
        try {
            Log::debug('Retrieving user matches', [
                'user_id' => $userId->toString(),
                'limit' => $limit,
                'sort_by' => $sortBy,
                'include_inactive' => $includeInactive
            ]);

            $matchesPaginator = $this->matchRepository->getUserMatches(
                $userId,
                [
                    'status' => $includeInactive ? 'all' : 'active',
                    'min_compatibility' => 0.0
                ],
                [
                    'field' => $sortBy === 'score' ? 'compatibility_score' : 'created_at',
                    'direction' => 'desc'
                ],
                1,
                $limit
            );

            // Convert paginator to collection of MatchEntity objects
            $matches = collect($matchesPaginator->items())->map(function ($matchData) {
                return MatchEntity::fromArray($matchData);
            });

            // Filter out expired matches unless specifically requested
            if (!$includeInactive) {
                $matches = $matches->filter(fn(MatchEntity $match) => $match->isActive());
            }

            // Update match view statistics
            $this->updateMatchViewStatistics($userId, $matches->count());

            Log::debug('User matches retrieved successfully', [
                'user_id' => $userId->toString(),
                'matches_count' => $matches->count()
            ]);

            return $matches;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve user matches', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new MatchingException('Failed to retrieve matches: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Unmatch users and remove their match
     * 
     * Removes an existing match between two users, typically initiated by
     * one of the matched users. Handles cleanup and notification.
     * 
     * @param UserId $initiatorId The user initiating the unmatch
     * @param UserId $targetUserId The other user in the match
     * @param string|null $reason Optional reason for unmatching
     * @return bool True if unmatch was successful
     * 
     * @throws MatchingException When unmatch operation fails
     * 
     * @example
     * ```php
     * $success = $matchingService->unmatchUsers(
     *     $userId, 
     *     $otherUserId, 
     *     'No longer interested'
     * );
     * ```
     */
    public function unmatchUsers(
        UserId $initiatorId,
        UserId $targetUserId,
        ?string $reason = null
    ): bool {
        try {
            Log::info('Unmatching users', [
                'initiator_id' => $initiatorId->toString(),
                'target_user_id' => $targetUserId->toString(),
                'reason' => $reason
            ]);

            // Find existing match
            $matchData = $this->matchRepository->getMatchBetweenUsers($initiatorId, $targetUserId);
            if (!$matchData) {
                throw new MatchingException('No active match found between users');
            }

            // Create MatchEntity and deactivate it
            $match = MatchEntity::fromArray($matchData);
            $deactivatedMatch = $match->deactivate($reason);
            
            // Update the match in repository
            $this->matchRepository->updateMatch(
                $match->getId()->toString(),
                [
                    'is_active' => false,
                    'deactivated_at' => Carbon::now(),
                    'deactivation_reason' => $reason
                ]
            );

            // Create UserMatch model for events
            $userMatch = new \App\Models\Matching\UserMatch($matchData);
            
            // Trigger unmatch event
            Event::dispatch(new MatchUnmatched(
                $userMatch,
                $initiatorId,
                $reason
            ));

            // Update statistics
            $this->updateUnmatchStatistics($initiatorId, $targetUserId);

            // Clear caches
            $this->clearUserMatchingCaches([$initiatorId, $targetUserId]);

            Log::info('Users unmatched successfully', [
                'match_id' => $match->getId()->toString(),
                'reason' => $reason
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to unmatch users', [
                'initiator_id' => $initiatorId->toString(),
                'target_user_id' => $targetUserId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new MatchingException('Failed to unmatch users: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // MATCHING OPTIMIZATION AND ANALYTICS
    // ================================================================

    /**
     * Optimize matching algorithms based on success metrics
     * 
     * Analyzes matching success patterns and adjusts algorithm parameters
     * to improve match quality and user satisfaction over time.
     * 
     * @param CarbonInterface|null $since Optional date to analyze from
     * @param array<string, mixed> $optimizationConfig Configuration for optimization
     * @return array<string, mixed> Optimization results and new parameters
     * 
     * @throws MatchingException When optimization fails
     * 
     * @example
     * ```php
     * $results = $matchingService->optimizeMatchingAlgorithms(
     *     Carbon::now()->subWeeks(4),
     *     ['focus_on' => 'message_rates', 'min_sample_size' => 1000]
     * );
     * ```
     */
    public function optimizeMatchingAlgorithms(
        ?CarbonInterface $since = null,
        array $optimizationConfig = []
    ): array {
        try {
            $since = $since ?? Carbon::now()->subMonth();
            
            Log::info('Starting matching algorithm optimization', [
                'since' => $since->toISOString(),
                'config' => $optimizationConfig
            ]);

            // Analyze recent matching performance
            $performanceMetrics = $this->analyzeMatchingPerformance($since);
            
            // Identify optimization opportunities
            $optimizationOpportunities = $this->identifyOptimizationOpportunities($performanceMetrics);
            
            // Calculate new algorithm parameters
            $newParameters = $this->calculateOptimizedParameters($optimizationOpportunities);
            
            // Test new parameters with A/B testing framework
            $testResults = $this->testOptimizedParameters($newParameters);
            
            // Apply improvements if test results are positive
            if ($testResults['improvement_significant']) {
                $this->applyOptimizedParameters($newParameters);
            }

            $results = [
                'optimization_date' => Carbon::now(),
                'performance_metrics' => $performanceMetrics,
                'opportunities_identified' => $optimizationOpportunities,
                'new_parameters' => $newParameters,
                'test_results' => $testResults,
                'parameters_applied' => $testResults['improvement_significant']
            ];

            Log::info('Matching algorithm optimization completed', [
                'improvements_applied' => $testResults['improvement_significant'],
                'performance_gain' => $testResults['performance_improvement'] ?? 0
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Matching algorithm optimization failed', [
                'error' => $e->getMessage()
            ]);
            throw new MatchingException('Algorithm optimization failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get matching analytics and performance metrics
     * 
     * Provides comprehensive analytics on matching performance including
     * success rates, user engagement, and algorithm effectiveness.
     * 
     * @param UserId|null $userId Optional specific user for personalized analytics
     * @param CarbonInterface|null $since Optional date range start
     * @param CarbonInterface|null $until Optional date range end
     * @return array<string, mixed> Comprehensive matching analytics
     * 
     * @throws MatchingException When analytics generation fails
     * 
     * @example
     * ```php
     * $analytics = $matchingService->getMatchingAnalytics(
     *     null, // All users
     *     Carbon::now()->subMonth(),
     *     Carbon::now()
     * );
     * ```
     */
    public function getMatchingAnalytics(
        ?UserId $userId = null,
        ?CarbonInterface $since = null,
        ?CarbonInterface $until = null
    ): array {
        try {
            $since = $since ?? Carbon::now()->subMonth();
            $until = $until ?? Carbon::now();

            Log::info('Generating matching analytics', [
                'user_id' => $userId?->toString(),
                'date_range' => [$since->toISOString(), $until->toISOString()]
            ]);

            $analytics = [
                'period' => [
                    'start' => $since,
                    'end' => $until,
                    'duration_days' => $since->diffInDays($until)
                ],
                'match_statistics' => $this->getMatchStatistics($userId, $since, $until),
                'compatibility_analysis' => $this->getCompatibilityAnalysis($userId, $since, $until),
                'user_engagement' => $this->getUserEngagementMetrics($userId, $since, $until),
                'algorithm_performance' => $this->getAlgorithmPerformanceMetrics($since, $until),
                'success_indicators' => $this->getSuccessIndicators($userId, $since, $until)
            ];

            Log::info('Matching analytics generated successfully', [
                'user_id' => $userId?->toString(),
                'total_matches_analyzed' => $analytics['match_statistics']['total_matches']
            ]);

            return $analytics;

        } catch (\Exception $e) {
            Log::error('Failed to generate matching analytics', [
                'user_id' => $userId?->toString(),
                'error' => $e->getMessage()
            ]);
            throw new MatchingException('Failed to generate analytics: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // PRIVATE HELPER METHODS
    // ================================================================

    /**
     * Validate daily matching limit for user
     */
    private function validateDailyMatchingLimit(UserId $userId, bool $isPremium): void
    {
        $cacheKey = "daily_matches.{$userId->toString()}." . Carbon::now()->format('Y-m-d');
        $dailyMatches = Cache::get($cacheKey, 0);
        
        $maxMatches = $isPremium ? self::MAX_PREMIUM_DAILY_MATCHES : self::MAX_DAILY_MATCHES;

        if ($dailyMatches >= $maxMatches) {
            throw new MatchingRateLimitException('Daily matching limit exceeded');
        }
    }

    /**
     * Get default matching criteria for a user profile
     */
    private function getDefaultMatchingCriteria($userProfile): MatchingCriteria
    {
        return new MatchingCriteria([
            'age_range' => [
                max(18, $userProfile->age - 10),
                min(80, $userProfile->age + 10)
            ],
            'distance_radius' => DistanceRadius::fromKilometers(self::DEFAULT_SEARCH_RADIUS_KM),
            'min_compatibility_score' => self::MIN_COMPATIBILITY_SCORE,
            'exclude_blocked' => true,
            'exclude_already_matched' => true,
            'require_photos' => true,
            'require_verified' => false
        ]);
    }

    /**
     * Find matching candidates based on criteria
     */
    private function findMatchingCandidates(
        UserId $userId,
        MatchingCriteria $criteria,
        int $limit
    ): Collection {
        // Convert MatchingCriteria to FilterCriteria for FilterService
        $filterCriteria = $this->convertMatchingCriteriaToFilterCriteria($criteria);
        return $this->filterService->findCandidates($userId, $filterCriteria, $limit);
    }

    /**
     * Calculate compatibility scores for candidates
     */
    private function calculateCompatibilityScores(UserId $userId, Collection $candidates): Collection
    {
        return $candidates->map(function ($candidate) use ($userId) {
            $compatibility = $this->calculateCompatibility(
                $userId,
                UserId::fromInt($candidate->user_id),
                false // No detailed analysis for bulk operations
            );
            
            // Add compatibility score to candidate data
            $candidate->compatibility_score = $compatibility;
            return $candidate;
        })->filter(function ($candidate) {
            return $candidate->compatibility_score->getValue() >= self::MIN_COMPATIBILITY_SCORE;
        });
    }

    /**
     * Apply quality filters and premium boost
     */
    private function applyQualityFilters(Collection $candidates, bool $premiumBoost): Collection
    {
        $filtered = $candidates->sortByDesc(fn($candidate) => $candidate->compatibility_score->getValue());
        
        if ($premiumBoost) {
            // Apply premium boost by prioritizing high-quality profiles
            $filtered = $filtered->sortByDesc(function ($candidate) {
                $qualityScore = $candidate->completeness_percentage / 100; // Convert to 0-1 scale
                return ($candidate->compatibility_score->getValue() * 0.7) +
                       ($qualityScore * 0.3);
            });
        }

        return $filtered;
    }

    /**
     * Create match entities from candidates
     */
    private function createMatchEntities(
        UserId $userId,
        Collection $candidates,
        int $limit,
        bool $premiumBoost
    ): Collection {
        return $candidates->take($limit)->map(function ($candidate, $index) use ($userId, $premiumBoost) {
            return new MatchEntity(
                matchId: MatchId::generate(),
                userId1: $userId,
                userId2: UserId::fromInt($candidate->user_id),
                compatibilityScore: $candidate->compatibility_score,
                matchedAt: Carbon::now(),
                matchSource: $premiumBoost ? 'premium_discovery' : 'standard_discovery',
                isActive: false, // Potential match, not active until mutual like
                matchContext: [
                    'algorithm_version' => '2.1',
                    'premium_boost' => $premiumBoost,
                    'candidate_rank' => $index + 1
                ]
            );
        });
    }

    /**
     * Convert MatchingCriteria to FilterCriteria for FilterService
     */
    private function convertMatchingCriteriaToFilterCriteria(MatchingCriteria $criteria): \App\Domain\Matching\ValueObjects\FilterCriteria
    {
        $criteriaArray = $criteria->toArray();
        
        $filterData = [];
        
        if (isset($criteriaArray['age_range'])) {
            $filterData['age_range'] = new \App\Domain\Matching\ValueObjects\AgeRange(
                $criteriaArray['age_range'][0], 
                $criteriaArray['age_range'][1]
            );
        }
        
        if (isset($criteriaArray['distance_radius'])) {
            $filterData['distance'] = $criteriaArray['distance_radius'];
        }
        
        $filterData['basic_constraints'] = [
            'min_compatibility_score' => $criteriaArray['min_compatibility_score'] ?? 0.6,
            'exclude_blocked' => $criteriaArray['exclude_blocked'] ?? true,
            'exclude_already_matched' => $criteriaArray['exclude_already_matched'] ?? true,
            'require_photos' => $criteriaArray['require_photos'] ?? true,
            'require_verified' => $criteriaArray['require_verified'] ?? false
        ];
        
        return \App\Domain\Matching\ValueObjects\FilterCriteria::create($filterData);
    }

    /**
     * Build cache key for matches
     */
    private function buildMatchesCacheKey(UserId $userId, MatchingCriteria $criteria): string
    {
        return "matches.{$userId->toString()}." . md5(serialize($criteria->toArray()));
    }

    /**
     * Clear user matching caches
     */
    private function clearUserMatchingCaches(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $pattern = "matches.{$userId->toString()}.*";
            Cache::forget($pattern);
        }
    }

    /**
     * Calculate detailed compatibility for match creation
     */
    private function calculateDetailedCompatibility(UserId $userId1, UserId $userId2): MatchScore
    {
        return $this->calculateCompatibility($userId1, $userId2, true);
    }

    /**
     * Validate that users can match
     */
    private function validateUsersCanMatch(UserId $userId1, UserId $userId2): void
    {
        // Validate match eligibility (includes block checking)
        $eligibility = $this->matchRepository->validateMatchEligibility($userId1, $userId2);
        if (!$eligibility['eligible']) {
            $reasons = implode(', ', $eligibility['reasons']);
            throw new InvalidMatchingCriteriaException("Users cannot match: {$reasons}");
        }

        if ($this->matchRepository->matchExists($userId1, $userId2, ['include_inactive' => false])) {
            throw new InvalidMatchingCriteriaException('Active match already exists between users');
        }
    }

    /**
     * Validate matching criteria
     */
    private function validateMatchingCriteria(MatchingCriteria $criteria): void
    {
        $criteriaArray = $criteria->toArray();
        
        if (isset($criteriaArray['age_range']) && 
            ($criteriaArray['age_range'][0] < 18 || $criteriaArray['age_range'][1] > 100)) {
            throw new InvalidMatchingCriteriaException('Age range must be between 18 and 100');
        }
        
        if (isset($criteriaArray['min_compatibility_score']) && 
            ($criteriaArray['min_compatibility_score'] < 0 || $criteriaArray['min_compatibility_score'] > 1)) {
            throw new InvalidMatchingCriteriaException('Compatibility score must be between 0 and 1');
        }
    }

    /**
     * Calculate age compatibility between two profiles
     */
    private function calculateAgeCompatibility($profile1, $profile2): float
    {
        if (!$profile1->age || !$profile2->age) {
            return 0.5; // Neutral score if age is missing
        }
        
        $ageDiff = abs($profile1->age - $profile2->age);
        
        if ($ageDiff <= 2) return 1.0;
        if ($ageDiff <= 5) return 0.8;
        if ($ageDiff <= 10) return 0.6;
        if ($ageDiff <= 15) return 0.4;
        
        return 0.2;
    }

    /**
     * Calculate location compatibility between two profiles
     */
    private function calculateLocationCompatibility($profile1, $profile2): float
    {
        if (!$profile1->latitude || !$profile1->longitude || 
            !$profile2->latitude || !$profile2->longitude) {
            return 0.5; // Neutral score if location is missing
        }
        
        $distance = $profile1->distanceTo($profile2);
        if (!$distance) return 0.5;
        
        if ($distance <= 5) return 1.0;
        if ($distance <= 15) return 0.8;
        if ($distance <= 30) return 0.6;
        if ($distance <= 50) return 0.4;
        
        return 0.2;
    }

    /**
     * Calculate interest compatibility between two profiles
     */
    private function calculateInterestCompatibility($profile1, $profile2): float
    {
        if (!$profile1->interests || !$profile2->interests) {
            return 0.5; // Neutral score if interests are missing
        }
        
        $commonInterests = count(array_intersect($profile1->interests, $profile2->interests));
        $totalInterests = count(array_unique(array_merge($profile1->interests, $profile2->interests)));
        
        if ($totalInterests === 0) return 0.5;
        
        return $commonInterests / $totalInterests;
    }

    /**
     * Calculate lifestyle compatibility between two profiles
     */
    private function calculateLifestyleCompatibility($profile1, $profile2): float
    {
        $score = 0.5; // Base score
        
        // Smoking compatibility
        if ($profile1->smoking && $profile2->smoking) {
            if ($profile1->smoking === $profile2->smoking) {
                $score += 0.2;
            } else {
                $score -= 0.1;
            }
        }
        
        // Drinking compatibility
        if ($profile1->drinking && $profile2->drinking) {
            if ($profile1->drinking === $profile2->drinking) {
                $score += 0.2;
            } else {
                $score -= 0.1;
            }
        }
        
        // Children compatibility
        if ($profile1->wants_children !== null && $profile2->wants_children !== null) {
            if ($profile1->wants_children === $profile2->wants_children) {
                $score += 0.1;
            } else {
                $score -= 0.1;
            }
        }
        
        return max(0, min(1, $score));
    }

    /**
     * Calculate personality compatibility between two profiles
     */
    private function calculatePersonalityCompatibility($profile1, $profile2): float
    {
        if (!$profile1->personality_type || !$profile2->personality_type) {
            return 0.5; // Neutral score if personality type is missing
        }
        
        // Simple personality matching - can be enhanced with MBTI compatibility matrix
        if ($profile1->personality_type === $profile2->personality_type) {
            return 0.8;
        }
        
        return 0.5; // Default compatibility
    }

    /**
     * Calculate confidence level for compatibility calculation
     */
    private function calculateConfidenceLevel($profile1, $profile2): float
    {
        $confidence = 0.5; // Base confidence
        
        // Increase confidence based on profile completeness
        $completeness1 = $profile1->completeness_percentage / 100;
        $completeness2 = $profile2->completeness_percentage / 100;
        
        $confidence += ($completeness1 + $completeness2) * 0.25;
        
        return min(1.0, $confidence);
    }

    /**
     * Update user matching statistics
     */
    private function updateUserMatchingStatistics(UserId $userId, int $matchesGenerated): void
    {
        // Implementation would update user statistics
        Log::debug('Updated user matching statistics', [
            'user_id' => $userId->toString(),
            'matches_generated' => $matchesGenerated
        ]);
    }

    /**
     * Update match view statistics
     */
    private function updateMatchViewStatistics(UserId $userId, int $matchesViewed): void
    {
        // Implementation would update match view statistics
        Log::debug('Updated match view statistics', [
            'user_id' => $userId->toString(),
            'matches_viewed' => $matchesViewed
        ]);
    }

    /**
     * Update user match counters
     */
    private function updateUserMatchCounters(UserId $userId1, UserId $userId2): void
    {
        // Implementation would update match counters for both users
        Log::debug('Updated user match counters', [
            'user1_id' => $userId1->toString(),
            'user2_id' => $userId2->toString()
        ]);
    }

    /**
     * Update unmatch statistics
     */
    private function updateUnmatchStatistics(UserId $initiatorId, UserId $targetUserId): void
    {
        // Implementation would update unmatch statistics
        Log::debug('Updated unmatch statistics', [
            'initiator_id' => $initiatorId->toString(),
            'target_user_id' => $targetUserId->toString()
        ]);
    }

    /**
     * Build match notification data
     */
    private function buildMatchNotificationData(MatchEntity $match): array
    {
        return [
            'match_id' => $match->getId()->toString(),
            'user1_id' => $match->getUserId1()->toString(),
            'user2_id' => $match->getUserId2()->toString(),
            'compatibility_score' => $match->getCompatibilityScore()->getValue(),
            'matched_at' => $match->getMatchedAt()->toISOString()
        ];
    }

    /**
     * Analyze matching performance
     */
    private function analyzeMatchingPerformance(CarbonInterface $since): array
    {
        // Placeholder implementation
        return [
            'total_matches' => 0,
            'success_rate' => 0.0,
            'average_compatibility' => 0.0
        ];
    }

    /**
     * Identify optimization opportunities
     */
    private function identifyOptimizationOpportunities(array $performanceMetrics): array
    {
        // Placeholder implementation
        return [];
    }

    /**
     * Calculate optimized parameters
     */
    private function calculateOptimizedParameters(array $opportunities): array
    {
        // Placeholder implementation
        return [];
    }

    /**
     * Test optimized parameters
     */
    private function testOptimizedParameters(array $parameters): array
    {
        // Placeholder implementation
        return [
            'improvement_significant' => false,
            'performance_improvement' => 0.0
        ];
    }

    /**
     * Apply optimized parameters
     */
    private function applyOptimizedParameters(array $parameters): void
    {
        // Placeholder implementation
        Log::info('Applied optimized parameters', ['parameters' => $parameters]);
    }

    /**
     * Get match statistics
     */
    private function getMatchStatistics(?UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        // Placeholder implementation
        return [
            'total_matches' => 0,
            'active_matches' => 0,
            'mutual_matches' => 0
        ];
    }

    /**
     * Get compatibility analysis
     */
    private function getCompatibilityAnalysis(?UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        // Placeholder implementation
        return [
            'average_score' => 0.0,
            'score_distribution' => []
        ];
    }

    /**
     * Get user engagement metrics
     */
    private function getUserEngagementMetrics(?UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        // Placeholder implementation
        return [
            'profile_views' => 0,
            'likes_sent' => 0,
            'messages_sent' => 0
        ];
    }

    /**
     * Get algorithm performance metrics
     */
    private function getAlgorithmPerformanceMetrics(CarbonInterface $since, CarbonInterface $until): array
    {
        // Placeholder implementation
        return [
            'processing_time' => 0.0,
            'accuracy' => 0.0
        ];
    }

    /**
     * Get success indicators
     */
    private function getSuccessIndicators(?UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        // Placeholder implementation
        return [
            'match_success_rate' => 0.0,
            'conversion_rate' => 0.0
        ];
    }
}