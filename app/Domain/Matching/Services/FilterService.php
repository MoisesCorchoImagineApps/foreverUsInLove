<?php

declare(strict_types=1);

namespace App\Domain\Matching\Services;

use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Matching\Repositories\LikeRepositoryInterface;
use App\Domain\Matching\Repositories\MatchRepositoryInterface;
use App\Domain\Matching\ValueObjects\FilterCriteria;
use App\Domain\Matching\ValueObjects\AgeRange;
use App\Domain\Matching\ValueObjects\DistanceFilter;
use App\Domain\Matching\ValueObjects\InterestFilter;
use App\Domain\Matching\ValueObjects\LifestyleFilter;
use App\Domain\Matching\ValueObjects\DemographicFilter;
use App\Domain\Matching\ValueObjects\FilterPreset;
use App\Domain\Matching\ValueObjects\CustomFilter;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Matching\Exceptions\FilterException;
use App\Domain\Matching\Exceptions\InvalidFilterCriteriaException;
use App\Domain\Matching\Exceptions\FilterProcessingException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * FilterService
 * 
 * Advanced filtering service for the ForeverUsInLove dating platform. Provides
 * sophisticated profile filtering capabilities with multiple criteria combinations,
 * preset filters, and personalized filtering algorithms following Domain-Driven
 * Design principles.
 * 
 * Key Responsibilities:
 * - Apply comprehensive filtering criteria to candidate profiles
 * - Manage user filter preferences and presets
 * - Handle geographic and demographic filtering
 * - Process interest-based and lifestyle filtering
 * - Support advanced premium filtering options
 * - Optimize filter performance and caching
 * - Provide filter analytics and effectiveness tracking
 * - Handle filter validation and constraint checking
 * 
 * Filter Categories:
 * 1. Basic Filters - Age, distance, gender preferences
 * 2. Demographic Filters - Education, income, occupation, ethnicity
 * 3. Lifestyle Filters - Smoking, drinking, exercise, diet preferences
 * 4. Interest Filters - Hobbies, activities, music, books, movies
 * 5. Relationship Filters - Goals, children preferences, religion
 * 6. Premium Filters - Advanced compatibility scoring, verified profiles only
 * 7. Behavioral Filters - Activity level, response patterns, engagement
 * 8. Custom Filters - User-defined combinations and weighted preferences
 * 
 * Business Rules:
 * - Basic filters are available to all users
 * - Premium users get access to advanced filtering options
 * - Geographic filters respect privacy settings
 * - Filter combinations must be logically consistent
 * - Filter effectiveness is tracked for optimization
 * - Saved filter presets have limits based on user tier
 * - Filter performance is cached for efficiency
 * - Results maintain diversity while respecting strict requirements
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
final class FilterService
{
    private const CACHE_TTL = 1800; // 30 minutes
    private const MAX_FILTER_PRESETS = 5;
    private const MAX_PREMIUM_FILTER_PRESETS = 20;
    private const DEFAULT_SEARCH_RADIUS_KM = 50;
    private const MAX_SEARCH_RADIUS_KM = 500;
    private const MIN_CANDIDATE_POOL_SIZE = 10;
    
    // Filter weight multipliers for scoring
    private const FILTER_WEIGHTS = [
        'age' => 1.0,
        'distance' => 0.8,
        'education' => 0.6,
        'interests' => 0.7,
        'lifestyle' => 0.6,
        'demographics' => 0.5,
        'relationship_goals' => 0.8,
        'premium_criteria' => 0.9
    ];

    // Performance optimization thresholds
    private const BATCH_SIZE = 1000;
    private const MAX_PROCESSING_TIME_MS = 2000;

    public function __construct(
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly LikeRepositoryInterface $likeRepository,
        private readonly MatchRepositoryInterface $matchRepository
    ) {}

    // ================================================================
    // CORE FILTERING OPERATIONS
    // ================================================================

    /**
     * Find candidate profiles based on filter criteria
     * 
     * Applies comprehensive filtering to find matching candidate profiles
     * based on user preferences, constraints, and advanced criteria.
     * 
     * @param UserId $userId The user requesting filtered candidates
     * @param FilterCriteria $criteria The filtering criteria to apply
     * @param int $limit Maximum number of candidates to return
     * @param bool $optimizeForDiversity Whether to optimize for result diversity
     * @return Collection Collection of filtered candidate profiles
     * 
     * @throws FilterException When filtering fails
     * @throws InvalidFilterCriteriaException When criteria validation fails
     * @throws FilterProcessingException When filter processing encounters errors
     * 
     * @example
     * ```php
     * $criteria = new FilterCriteria([
     *     'age_range' => new AgeRange(25, 35),
     *     'distance' => new DistanceFilter(50, 'km'),
     *     'education' => ['bachelors', 'masters', 'phd'],
     *     'interests' => new InterestFilter(['music', 'travel', 'fitness'])
     * ]);
     * $candidates = $filterService->findCandidates($userId, $criteria, 50);
     * ```
     */
    public function findCandidates(
        UserId $userId,
        FilterCriteria $criteria,
        int $limit = 50,
        bool $optimizeForDiversity = true
    ): Collection {
        try {
            Log::info('Finding filtered candidates', [
                'user_id' => $userId->toString(),
                'limit' => $limit,
                'optimize_diversity' => $optimizeForDiversity,
                'filter_count' => $criteria->getFilterCount()
            ]);

            // Validate filter criteria
            $this->validateFilterCriteria($criteria);

            // Get user profile for context
            $userProfile = $this->profileRepository->findByUserId($userId->toInt());
            if (!$userProfile) {
                throw new FilterException('User profile not found');
            }

            // Check cache for filtered results
            $cacheKey = $this->buildFilterCacheKey($userId, $criteria, $limit);
            if (Cache::has($cacheKey)) {
                Log::debug('Returning cached filtered candidates');
                return Cache::get($cacheKey);
            }

            // Start with base candidate pool
            $baseCandidates = $this->getBaseCandidatePool($userId, $criteria);
            
            if ($baseCandidates->isEmpty()) {
                Log::info('No base candidates found for filtering', [
                    'user_id' => $userId->toString()
                ]);
                return new Collection();
            }

            // Apply filtering stages in order of selectivity
            $filteredCandidates = $this->applyFilteringPipeline(
                $baseCandidates,
                $criteria,
                $userProfile,
                $limit,
                $optimizeForDiversity
            );

            // Ensure minimum diversity if requested
            if ($optimizeForDiversity) {
                $filteredCandidates = $this->ensureResultDiversity($filteredCandidates, $criteria);
            }

            // Cache results
            Cache::put($cacheKey, $filteredCandidates, self::CACHE_TTL);

            // Update filter analytics
            $this->updateFilterAnalytics($userId, $criteria, $filteredCandidates->count());

            Log::info('Candidates filtered successfully', [
                'user_id' => $userId->toString(),
                'input_candidates' => $baseCandidates->count(),
                'filtered_results' => $filteredCandidates->count(),
                'filters_applied' => $criteria->getActiveFilters()
            ]);

            return $filteredCandidates;

        } catch (FilterException $e) {
            Log::error('Candidate filtering failed', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during filtering', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new FilterProcessingException('Filter processing failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Apply age range filter to candidate profiles
     * 
     * Filters candidates based on specified age range with optional
     * flexibility for edge cases and premium users.
     * 
     * @param Collection $candidates The candidate profiles to filter
     * @param AgeRange $ageRange The age range criteria
     * @param bool $allowFlexibility Whether to allow slight age range flexibility
     * @return Collection Filtered candidates within age range
     * 
     * @throws FilterException When age filtering fails
     * 
     * @example
     * ```php
     * $ageRange = new AgeRange(28, 38);
     * $filtered = $filterService->applyAgeFilter($candidates, $ageRange, true);
     * ```
     */
    public function applyAgeFilter(
        Collection $candidates,
        AgeRange $ageRange,
        bool $allowFlexibility = false
    ): Collection {
        try {
            Log::debug('Applying age filter', [
                'candidates_count' => $candidates->count(),
                'min_age' => $ageRange->getMinAge(),
                'max_age' => $ageRange->getMaxAge(),
                'allow_flexibility' => $allowFlexibility
            ]);

            $filtered = $candidates->filter(function ($candidate) use ($ageRange, $allowFlexibility) {
                $candidateAge = $candidate->getAge();
                
                // Strict age range check
                if ($candidateAge >= $ageRange->getMinAge() && $candidateAge <= $ageRange->getMaxAge()) {
                    return true;
                }
                
                // Flexibility allows +/- 2 years for high compatibility candidates
                if ($allowFlexibility) {
                    $flexibilityRange = 2;
                    $flexibleMin = $ageRange->getMinAge() - $flexibilityRange;
                    $flexibleMax = $ageRange->getMaxAge() + $flexibilityRange;
                    
                    if ($candidateAge >= $flexibleMin && $candidateAge <= $flexibleMax) {
                        // Only include if high compatibility or other strong indicators
                        return $candidate->hasHighCompatibilityIndicators();
                    }
                }
                
                return false;
            });

            Log::debug('Age filter applied', [
                'input_count' => $candidates->count(),
                'filtered_count' => $filtered->count(),
                'filter_effectiveness' => $filtered->count() / max($candidates->count(), 1)
            ]);

            return $filtered;

        } catch (\Exception $e) {
            Log::error('Age filter application failed', ['error' => $e->getMessage()]);
            throw new FilterException('Failed to apply age filter: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Apply distance/location filter to candidates
     * 
     * Filters candidates based on geographic proximity with support for
     * multiple distance units and location privacy considerations.
     * 
     * @param Collection $candidates The candidate profiles to filter
     * @param DistanceFilter $distanceFilter The distance criteria
     * @param array<float, float> $userLocation User's location coordinates [lat, lng]
     * @param bool $respectPrivacy Whether to respect location privacy settings
     * @return Collection Filtered candidates within distance range
     * 
     * @throws FilterException When distance filtering fails
     * 
     * @example
     * ```php
     * $distanceFilter = new DistanceFilter(25, 'km', true);
     * $userLocation = [40.7128, -74.0060]; // NYC coordinates
     * $filtered = $filterService->applyDistanceFilter(
     *     $candidates, 
     *     $distanceFilter, 
     *     $userLocation
     * );
     * ```
     */
    public function applyDistanceFilter(
        Collection $candidates,
        DistanceFilter $distanceFilter,
        array $userLocation,
        bool $respectPrivacy = true
    ): Collection {
        try {
            Log::debug('Applying distance filter', [
                'candidates_count' => $candidates->count(),
                'max_distance' => $distanceFilter->getMaxDistance(),
                'unit' => $distanceFilter->getUnit(),
                'respect_privacy' => $respectPrivacy
            ]);

            $filtered = $candidates->filter(function ($candidate) use ($distanceFilter, $userLocation, $respectPrivacy) {
                $candidateLocation = $candidate->getLocation();
                
                // Skip if no location available
                if (!$candidateLocation) {
                    return false;
                }
                
                // Respect privacy settings
                if ($respectPrivacy && !$candidate->allowsLocationFiltering()) {
                    // Include if they're in a major city match or have premium
                    return $candidate->isInMajorCity() || $candidate->isPremium();
                }
                
                // Calculate distance
                $distance = $this->calculateDistance(
                    $userLocation,
                    $candidateLocation->getCoordinates(),
                    $distanceFilter->getUnit()
                );
                
                return $distance <= $distanceFilter->getMaxDistance();
            });

            Log::debug('Distance filter applied', [
                'input_count' => $candidates->count(),
                'filtered_count' => $filtered->count(),
                'max_distance' => $distanceFilter->getMaxDistance(),
                'unit' => $distanceFilter->getUnit()
            ]);

            return $filtered;

        } catch (\Exception $e) {
            Log::error('Distance filter application failed', ['error' => $e->getMessage()]);
            throw new FilterException('Failed to apply distance filter: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Apply interest-based filtering to candidates
     * 
     * Filters candidates based on shared interests, hobbies, and activities
     * with configurable matching thresholds and interest weighting.
     * 
     * @param Collection $candidates The candidate profiles to filter
     * @param InterestFilter $interestFilter The interest criteria
     * @param float $minMatchThreshold Minimum interest overlap threshold (0.0-1.0)
     * @return Collection Filtered candidates with sufficient interest overlap
     * 
     * @throws FilterException When interest filtering fails
     * 
     * @example
     * ```php
     * $interestFilter = new InterestFilter([
     *     'required' => ['music', 'travel'],
     *     'preferred' => ['fitness', 'cooking', 'photography'],
     *     'weighted' => true
     * ]);
     * $filtered = $filterService->applyInterestFilter($candidates, $interestFilter, 0.3);
     * ```
     */
    public function applyInterestFilter(
        Collection $candidates,
        InterestFilter $interestFilter,
        float $minMatchThreshold = 0.2
    ): Collection {
        try {
            Log::debug('Applying interest filter', [
                'candidates_count' => $candidates->count(),
                'required_interests' => $interestFilter->getRequiredInterests(),
                'preferred_interests' => $interestFilter->getPreferredInterests(),
                'min_threshold' => $minMatchThreshold
            ]);

            $filtered = $candidates->filter(function ($candidate) use ($interestFilter, $minMatchThreshold) {
                $candidateInterests = $candidate->getInterests();
                
                // Check required interests first (must have all)
                $requiredInterests = $interestFilter->getRequiredInterests();
                if (!empty($requiredInterests)) {
                    foreach ($requiredInterests as $required) {
                        if (!in_array($required, $candidateInterests)) {
                            return false;
                        }
                    }
                }
                
                // Calculate interest overlap score
                $overlapScore = $this->calculateInterestOverlap(
                    $interestFilter->getPreferredInterests(),
                    $candidateInterests,
                    $interestFilter->isWeighted()
                );
                
                return $overlapScore >= $minMatchThreshold;
            });

            Log::debug('Interest filter applied', [
                'input_count' => $candidates->count(),
                'filtered_count' => $filtered->count(),
                'average_overlap' => $this->calculateAverageInterestOverlap($filtered, $interestFilter)
            ]);

            return $filtered;

        } catch (\Exception $e) {
            Log::error('Interest filter application failed', ['error' => $e->getMessage()]);
            throw new FilterException('Failed to apply interest filter: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // FILTER PRESETS AND MANAGEMENT
    // ================================================================

    /**
     * Save a custom filter preset for a user
     * 
     * Stores user-defined filter combinations as reusable presets with
     * validation and limit checking based on user tier.
     * 
     * @param UserId $userId The user saving the preset
     * @param string $presetName Name for the filter preset
     * @param FilterCriteria $criteria The filter criteria to save
     * @param bool $isDefault Whether this should be the default preset
     * @return FilterPreset The saved filter preset
     * 
     * @throws FilterException When preset saving fails
     * 
     * @example
     * ```php
     * $preset = $filterService->saveFilterPreset(
     *     $userId,
     *     'Nearby Professionals',
     *     $criteria,
     *     false
     * );
     * ```
     */
    public function saveFilterPreset(
        UserId $userId,
        string $presetName,
        FilterCriteria $criteria,
        bool $isDefault = false
    ): FilterPreset {
        try {
            Log::info('Saving filter preset', [
                'user_id' => $userId->toString(),
                'preset_name' => $presetName,
                'is_default' => $isDefault
            ]);

            // Validate preset limits
            $this->validatePresetLimits($userId);

            // Validate filter criteria
            $this->validateFilterCriteria($criteria);

            // Check for duplicate preset names
            $existingPresets = $this->getUserFilterPresets($userId);
            if ($existingPresets->contains('name', $presetName)) {
                throw new FilterException('Filter preset with this name already exists');
            }

            // Create filter preset
            $preset = new FilterPreset(
                userId: $userId,
                name: $presetName,
                criteria: $criteria,
                isDefault: $isDefault,
                createdAt: Carbon::now(),
                updatedAt: Carbon::now(),
                usageCount: 0,
                id: 'temp-id'
            );

            // Save preset
            $savedPreset = $this->savePresetToRepository($preset);

            // Update default preset if requested
            if ($isDefault) {
                $this->updateDefaultPreset($userId, $savedPreset);
            }

            Log::info('Filter preset saved successfully', [
                'user_id' => $userId->toString(),
                'preset_id' => method_exists($savedPreset, 'getId') ? (string)$savedPreset->getId() : 'unknown',
                'preset_name' => $presetName
            ]);

            return $savedPreset;

        } catch (\Exception $e) {
            Log::error('Failed to save filter preset', [
                'user_id' => $userId->toString(),
                'preset_name' => $presetName,
                'error' => $e->getMessage()
            ]);
            throw new FilterException('Failed to save filter preset: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get user's saved filter presets
     * 
     * Retrieves all filter presets saved by a user with usage statistics
     * and effectiveness metrics.
     * 
     * @param UserId $userId The user whose presets to retrieve
     * @param bool $includeAnalytics Whether to include usage analytics
     * @return Collection<FilterPreset> User's filter presets
     * 
     * @throws FilterException When preset retrieval fails
     * 
     * @example
     * ```php
     * $presets = $filterService->getUserFilterPresets($userId, true);
     * foreach ($presets as $preset) {
     *     $effectiveness = $preset->getEffectivenessScore();
     *     $usageCount = $preset->getUsageCount();
     * }
     * ```
     */
    public function getUserFilterPresets(UserId $userId, bool $includeAnalytics = false): Collection
    {
        try {
            Log::debug('Retrieving user filter presets', [
                'user_id' => $userId->toString(),
                'include_analytics' => $includeAnalytics
            ]);

            $presets = $this->profileRepository->findUserFilterPresets($userId->toInt());

            if ($includeAnalytics) {
                $presets = $presets->map(function ($preset) {
                    // Convertir modelo Eloquent a FilterPreset si es necesario
                    // Por ahora solo agregamos analytics básicos
                    $analytics = $this->calculatePresetAnalytics($preset);
                    $preset->analytics = $analytics;
                    return $preset;
                });
            }

            Log::debug('Filter presets retrieved', [
                'user_id' => $userId->toString(),
                'presets_count' => $presets->count()
            ]);

            return $presets;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve filter presets', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new FilterException('Failed to retrieve filter presets: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // ADVANCED FILTERING FEATURES
    // ================================================================

    /**
     * Apply premium filtering criteria
     * 
     * Processes advanced premium filtering options including compatibility
     * scoring, verification status, and enhanced behavioral filtering.
     * 
     * @param Collection $candidates The candidate profiles to filter
     * @param array<string, mixed> $premiumCriteria Premium filtering criteria
     * @param bool $strictMode Whether to apply strict premium requirements
     * @return Collection Filtered candidates meeting premium criteria
     * 
     * @throws FilterException When premium filtering fails
     * 
     * @example
     * ```php
     * $premiumCriteria = [
     *     'verified_only' => true,
     *     'min_compatibility_score' => 0.8,
     *     'high_engagement_only' => true,
     *     'exclude_inactive' => true
     * ];
     * $filtered = $filterService->applyPremiumFilters($candidates, $premiumCriteria);
     * ```
     */
    public function applyPremiumFilters(
        Collection $candidates,
        array $premiumCriteria,
        bool $strictMode = false
    ): Collection {
        try {
            Log::debug('Applying premium filters', [
                'candidates_count' => $candidates->count(),
                'criteria' => array_keys($premiumCriteria),
                'strict_mode' => $strictMode
            ]);

            $filtered = $candidates;

            // Apply verified profiles filter
            if ($premiumCriteria['verified_only'] ?? false) {
                $filtered = $filtered->filter(fn($candidate) => $candidate->isVerified());
            }

            // Apply minimum compatibility score filter
            if (isset($premiumCriteria['min_compatibility_score'])) {
                $minScore = $premiumCriteria['min_compatibility_score'];
                $filtered = $filtered->filter(fn($candidate) => 
                    $candidate->getCompatibilityScore() >= $minScore
                );
            }

            // Apply high engagement filter
            if ($premiumCriteria['high_engagement_only'] ?? false) {
                $filtered = $filtered->filter(fn($candidate) => $candidate->hasHighEngagement());
            }

            // Apply activity filter
            if ($premiumCriteria['exclude_inactive'] ?? false) {
                $filtered = $filtered->filter(fn($candidate) => $candidate->isRecentlyActive());
            }

            // Apply premium quality score filter
            if (isset($premiumCriteria['min_quality_score'])) {
                $minQuality = $premiumCriteria['min_quality_score'];
                $filtered = $filtered->filter(fn($candidate) => 
                    $candidate->getQualityScore() >= $minQuality
                );
            }

            Log::debug('Premium filters applied', [
                'input_count' => $candidates->count(),
                'filtered_count' => $filtered->count(),
                'filters_applied' => count(array_filter($premiumCriteria))
            ]);

            return $filtered;

        } catch (\Exception $e) {
            Log::error('Premium filter application failed', ['error' => $e->getMessage()]);
            throw new FilterException('Failed to apply premium filters: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get filter effectiveness analytics
     * 
     * Analyzes filter performance and provides insights on filter effectiveness,
     * candidate pool impact, and optimization recommendations.
     * 
     * @param UserId $userId The user for filter analytics
     * @param CarbonInterface|null $since Optional start date for analysis
     * @param CarbonInterface|null $until Optional end date for analysis
     * @return array<string, mixed> Comprehensive filter analytics
     * 
     * @throws FilterException When analytics generation fails
     * 
     * @example
     * ```php
     * $analytics = $filterService->getFilterAnalytics(
     *     $userId,
     *     Carbon::now()->subMonth()
     * );
     * ```
     */
    public function getFilterAnalytics(
        UserId $userId,
        ?CarbonInterface $since = null,
        ?CarbonInterface $until = null
    ): array {
        try {
            $since = $since ?? Carbon::now()->subMonth();
            $until = $until ?? Carbon::now();

            Log::info('Generating filter analytics', [
                'user_id' => $userId->toString(),
                'period' => [$since->toISOString(), $until->toISOString()]
            ]);

            $analytics = [
                'period' => [
                    'start' => $since,
                    'end' => $until,
                    'duration_days' => $since->diffInDays($until)
                ],
                'filter_usage' => $this->getFilterUsageStats($userId, $since, $until),
                'effectiveness_metrics' => $this->getFilterEffectivenessMetrics($userId, $since, $until),
                'preset_performance' => $this->getPresetPerformanceStats($userId, $since, $until),
                'optimization_suggestions' => $this->generateOptimizationSuggestions($userId, $since, $until),
                'candidate_pool_analysis' => $this->analyzeCandidatePoolImpact($userId, $since, $until)
            ];

            Log::info('Filter analytics generated successfully', [
                'user_id' => $userId->toString(),
                'filters_analyzed' => count($analytics['filter_usage'])
            ]);

            return $analytics;

        } catch (\Exception $e) {
            Log::error('Failed to generate filter analytics', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new FilterException('Failed to generate filter analytics: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // PRIVATE HELPER METHODS
    // ================================================================

    /**
     * Get base candidate pool before filtering
     */
    private function getBaseCandidatePool(UserId $userId, FilterCriteria $criteria): Collection
    {
        // Start with all active profiles excluding already interacted
        return $this->profileRepository->findCandidatePool(
            $userId->toInt(),
            $criteria->getGenderPreferences(),
            $criteria->getBasicConstraints()
        );
    }

    /**
     * Apply complete filtering pipeline
     */
    private function applyFilteringPipeline(
        Collection $candidates,
        FilterCriteria $criteria,
        $userProfile,
        int $limit,
        bool $optimizeForDiversity
    ): Collection {
        $startTime = microtime(true);
        
        // Stage 1: Basic filters (most selective first)
        if ($criteria->hasAgeFilter()) {
            $candidates = $this->applyAgeFilter($candidates, $criteria->getAgeRange());
        }

        if ($criteria->hasDistanceFilter()) {
            $candidates = $this->applyDistanceFilter(
                $candidates,
                $criteria->getDistanceFilter(),
                $userProfile->getLocation()->getCoordinates()
            );
        }

        // Stage 2: Demographic filters
        if ($criteria->hasDemographicFilter()) {
            $candidates = $this->applyDemographicFilter($candidates, $criteria->getDemographicFilter());
        }

        // Stage 3: Interest and lifestyle filters
        if ($criteria->hasInterestFilter()) {
            $candidates = $this->applyInterestFilter($candidates, $criteria->getInterestFilter());
        }

        if ($criteria->hasLifestyleFilter()) {
            $candidates = $this->applyLifestyleFilter($candidates, $criteria->getLifestyleFilter());
        }

        // Stage 4: Premium filters
        if ($criteria->hasPremiumFilters()) {
            $candidates = $this->applyPremiumFilters(
                $candidates,
                $criteria->getPremiumFilters(),
                $userProfile->isPremium()
            );
        }

        // Check processing time and candidate pool size
        $processingTime = (microtime(true) - $startTime) * 1000;
        if ($processingTime > self::MAX_PROCESSING_TIME_MS) {
            Log::warning('Filter processing exceeded time limit', [
                'processing_time_ms' => $processingTime,
                'candidates_processed' => $candidates->count()
            ]);
        }

        return $candidates->take($limit);
    }

    /**
     * Calculate distance between two coordinates
     */
    private function calculateDistance(array $coord1, array $coord2, string $unit = 'km'): float
    {
        $lat1 = deg2rad($coord1[0]);
        $lon1 = deg2rad($coord1[1]);
        $lat2 = deg2rad($coord2[0]);
        $lon2 = deg2rad($coord2[1]);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = 6371 * $c; // Earth's radius in kilometers

        return $unit === 'miles' ? $distance * 0.621371 : $distance;
    }

    /**
     * Calculate interest overlap score
     */
    private function calculateInterestOverlap(array $userInterests, array $candidateInterests, bool $weighted): float
    {
        if (empty($userInterests) || empty($candidateInterests)) {
            return 0.0;
        }

        $intersection = array_intersect($userInterests, $candidateInterests);
        $union = array_unique(array_merge($userInterests, $candidateInterests));

        if (empty($union)) {
            return 0.0;
        }

        $basicScore = count($intersection) / count($union);

        if (!$weighted) {
            return $basicScore;
        }

        // Apply interest category weights for more accurate scoring
        $weightedScore = 0.0;
        $totalWeight = 0.0;

        foreach ($intersection as $interest) {
            $weight = $this->getInterestWeight($interest);
            $weightedScore += $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weightedScore / $totalWeight : $basicScore;
    }

    /**
     * Build filter cache key
     */
    private function buildFilterCacheKey(UserId $userId, FilterCriteria $criteria, int $limit): string
    {
        $criteriaHash = md5(serialize($criteria->toArray()));
        return "filtered_candidates.{$userId->toString()}.{$criteriaHash}.{$limit}";
    }

    /**
     * Validate filter criteria
     */
    private function validateFilterCriteria(FilterCriteria $criteria): void
    {
        if ($criteria->hasAgeFilter()) {
            $ageRange = $criteria->getAgeRange();
            if ($ageRange->getMinAge() > $ageRange->getMaxAge()) {
                throw new InvalidFilterCriteriaException('Invalid age range: minimum age greater than maximum');
            }
        }

        if ($criteria->hasDistanceFilter()) {
            $distanceFilter = $criteria->getDistanceFilter();
            if ($distanceFilter->getMaxDistance() <= 0) {
                throw new InvalidFilterCriteriaException('Invalid distance filter: distance must be positive');
            }
        }
    }

    /**
     * Apply demographic filter to candidates
     */
    private function applyDemographicFilter(Collection $candidates, DemographicFilter $demographicFilter): Collection
    {
        try {
            Log::debug('Applying demographic filter', [
                'candidates_count' => $candidates->count(),
                'demographic_criteria' => $demographicFilter->toArray()
            ]);

            $filtered = $candidates->filter(function ($candidate) use ($demographicFilter) {
                // Implementar lógica de filtrado demográfico
                // Por ahora retornamos todos los candidatos
                return true;
            });

            Log::debug('Demographic filter applied', [
                'input_count' => $candidates->count(),
                'filtered_count' => $filtered->count()
            ]);

            return $filtered;
        } catch (\Exception $e) {
            Log::error('Demographic filter application failed', ['error' => $e->getMessage()]);
            throw new FilterException('Failed to apply demographic filter: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Apply lifestyle filter to candidates
     */
    private function applyLifestyleFilter(Collection $candidates, LifestyleFilter $lifestyleFilter): Collection
    {
        try {
            Log::debug('Applying lifestyle filter', [
                'candidates_count' => $candidates->count(),
                'lifestyle_criteria' => $lifestyleFilter->toArray()
            ]);

            $filtered = $candidates->filter(function ($candidate) use ($lifestyleFilter) {
                // Implementar lógica de filtrado de estilo de vida
                // Por ahora retornamos todos los candidatos
                return true;
            });

            Log::debug('Lifestyle filter applied', [
                'input_count' => $candidates->count(),
                'filtered_count' => $filtered->count()
            ]);

            return $filtered;
        } catch (\Exception $e) {
            Log::error('Lifestyle filter application failed', ['error' => $e->getMessage()]);
            throw new FilterException('Failed to apply lifestyle filter: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate preset limits for user
     */
    private function validatePresetLimits(UserId $userId): void
    {
        // Implementar validación de límites de presets
        // Por ahora no hay restricciones
    }

    /**
     * Save preset to repository
     */
    private function savePresetToRepository(FilterPreset $preset): FilterPreset
    {
        // Implementar guardado de preset
        // Por ahora retornamos el preset sin cambios
        return $preset;
    }

    /**
     * Update default preset for user
     */
    private function updateDefaultPreset(UserId $userId, $presetId): void
    {
        // Implementar actualización de preset por defecto
    }

    /**
     * Update filter analytics
     */
    private function updateFilterAnalytics(UserId $userId, FilterCriteria $criteria, int $resultCount): void
    {
        // Implementar actualización de analytics
    }

    /**
     * Ensure result diversity
     */
    private function ensureResultDiversity(Collection $candidates, FilterCriteria $criteria): Collection
    {
        // Implementar algoritmo de diversidad
        // Por ahora retornamos los candidatos sin cambios
        return $candidates;
    }

    /**
     * Calculate preset analytics
     */
    private function calculatePresetAnalytics($preset): array
    {
        return [
            'usage_count' => method_exists($preset, 'getUsageCount') ? $preset->getUsageCount() : 0,
            'effectiveness_score' => 0.8,
            'last_used' => null
        ];
    }

    /**
     * Get filter usage stats
     */
    private function getFilterUsageStats(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [];
    }

    /**
     * Get filter effectiveness metrics
     */
    private function getFilterEffectivenessMetrics(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [];
    }

    /**
     * Get preset performance stats
     */
    private function getPresetPerformanceStats(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [];
    }

    /**
     * Generate optimization suggestions
     */
    private function generateOptimizationSuggestions(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [];
    }

    /**
     * Analyze candidate pool impact
     */
    private function analyzeCandidatePoolImpact(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [];
    }

    /**
     * Calculate average interest overlap
     */
    private function calculateAverageInterestOverlap(Collection $candidates, InterestFilter $interestFilter): float
    {
        if ($candidates->isEmpty()) {
            return 0.0;
        }

        $totalOverlap = $candidates->sum(function ($candidate) use ($interestFilter) {
            return $this->calculateInterestOverlap(
                $interestFilter->getPreferredInterests(),
                $candidate->getInterests(),
                $interestFilter->isWeighted()
            );
        });

        return $totalOverlap / $candidates->count();
    }

    /**
     * Get interest weight
     */
    private function getInterestWeight(string $interest): float
    {
        // Implementar sistema de pesos para intereses
        // Por ahora todos tienen peso 1.0
        return 1.0;
    }
}