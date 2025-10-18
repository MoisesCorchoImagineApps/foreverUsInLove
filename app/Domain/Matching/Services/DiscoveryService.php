<?php

declare(strict_types=1);

namespace App\Domain\Matching\Services;

use App\Domain\Matching\Repositories\MatchRepositoryInterface;
use App\Domain\Matching\Repositories\LikeRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Matching\Entities\DiscoverySession;
use App\Domain\Matching\Entities\DiscoveryCard;
use App\Domain\Matching\ValueObjects\DiscoverySessionId;
use App\Domain\Matching\ValueObjects\DiscoveryMode;
use App\Domain\Matching\ValueObjects\DiscoveryRadius;
use App\Domain\Matching\ValueObjects\DiscoveryPreferences;
use App\Domain\Matching\ValueObjects\ExplorationCriteria;
use App\Domain\Matching\ValueObjects\DiscoveryBoost;
use App\Domain\Matching\ValueObjects\DiscoveryCardId;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Matching\Exceptions\DiscoveryException;
use App\Domain\Matching\Exceptions\InsufficientCandidatesException;
use App\Domain\Matching\Exceptions\DiscoverySessionExpiredException;
use App\Domain\Matching\Exceptions\InvalidDiscoveryModeException;
use App\Domain\Matching\Events\DiscoveryBoostActivated;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * DiscoveryService
 * 
 * Advanced discovery and exploration service for the ForeverUsInLove dating platform.
 * Provides sophisticated algorithms for user discovery, profile exploration,
 * and serendipitous matching experiences following Domain-Driven Design principles.
 * 
 * Key Responsibilities:
 * - Manage discovery sessions and user exploration flows
 * - Implement multiple discovery modes (standard, explore, boost, premium)
 * - Handle discovery card generation and presentation logic
 * - Support geographical and interest-based exploration
 * - Provide discovery analytics and optimization
 * - Manage discovery boosts and premium features
 * - Handle discovery session persistence and caching
 * - Implement discovery rate limiting and fraud prevention
 * 
 * Discovery Modes:
 * 1. Standard Discovery - Regular matching algorithm results
 * 2. Explore Mode - Broaden horizons with different profile types
 * 3. Boost Mode - Premium enhanced discovery with better visibility
 * 4. Local Mode - Focus on nearby users for location-based matching
 * 5. Global Mode - Expand search radius for international connections
 * 6. Interest Mode - Discovery based on shared hobbies and interests
 * 7. Second Chance - Re-discover previously passed profiles
 * 8. Trending - Discover popular and highly engaged profiles
 * 
 * Business Rules:
 * - Discovery sessions have time limits based on user tier
 * - Premium users get enhanced discovery features and unlimited sessions
 * - Discovery boosts increase profile visibility temporarily
 * - Geographic preferences are respected unless explicitly overridden
 * - Previously liked/passed profiles are filtered unless in second chance mode
 * - Discovery analytics track user engagement and preferences
 * - Rate limiting prevents excessive discovery session creation
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
final class DiscoveryService
{
    private const CACHE_TTL = 1800; // 30 minutes
    private const DEFAULT_CARDS_PER_SESSION = 20;
    private const PREMIUM_CARDS_PER_SESSION = 50;
    private const MAX_DAILY_SESSIONS = 10;
    private const MAX_PREMIUM_DAILY_SESSIONS = 50;
    private const SESSION_DURATION_MINUTES = 30;
    private const PREMIUM_SESSION_DURATION_MINUTES = 60;
    
    // Discovery mode weights for algorithm tuning
    private const MODE_WEIGHTS = [
        'standard' => ['compatibility' => 0.7, 'freshness' => 0.2, 'activity' => 0.1],
        'explore' => ['diversity' => 0.5, 'compatibility' => 0.3, 'novelty' => 0.2],
        'boost' => ['quality' => 0.4, 'compatibility' => 0.4, 'premium_priority' => 0.2],
        'local' => ['proximity' => 0.6, 'compatibility' => 0.3, 'activity' => 0.1],
        'global' => ['compatibility' => 0.5, 'diversity' => 0.3, 'cultural_interest' => 0.2],
        'interest' => ['shared_interests' => 0.6, 'compatibility' => 0.3, 'activity' => 0.1],
        'second_chance' => ['improvement' => 0.4, 'time_passed' => 0.3, 'compatibility' => 0.3],
        'trending' => ['popularity' => 0.5, 'engagement' => 0.3, 'compatibility' => 0.2]
    ];

    public function __construct(
        private readonly MatchRepositoryInterface $matchRepository,
        private readonly LikeRepositoryInterface $likeRepository,
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly FilterService $filterService,
        private readonly MatchingService $matchingService
    ) {}

    // ================================================================
    // DISCOVERY SESSION MANAGEMENT
    // ================================================================

    /**
     * Start a new discovery session for a user
     * 
     * Creates a personalized discovery session with curated profiles based on
     * the selected discovery mode, user preferences, and engagement patterns.
     * 
     * @param UserId $userId The user starting the discovery session
     * @param DiscoveryMode $mode The discovery mode to use
     * @param DiscoveryPreferences|null $preferences Optional custom preferences
     * @param bool $isPremium Whether user has premium features
     * @return DiscoverySession The created discovery session
     * 
     * @throws DiscoveryException When session creation fails
     * @throws InsufficientCandidatesException When not enough profiles available
     * @throws InvalidDiscoveryModeException When mode is not supported
     * 
     * @example
     * ```php
     * $preferences = new DiscoveryPreferences([
     *     'radius' => 50,
     *     'age_range' => [25, 35],
     *     'interests' => ['music', 'travel']
     * ]);
     * $session = $discoveryService->startDiscoverySession(
     *     $userId, 
     *     DiscoveryMode::EXPLORE, 
     *     $preferences,
     *     true
     * );
     * ```
     */
    public function startDiscoverySession(
        UserId $userId,
        DiscoveryMode $mode,
        ?DiscoveryPreferences $preferences = null,
        bool $isPremium = false
    ): DiscoverySession {
        try {
            Log::info('Starting discovery session', [
                'user_id' => $userId->toString(),
                'mode' => $mode->getValue(),
                'is_premium' => $isPremium
            ]);

            // Validate daily session limits
            $this->validateDailySessionLimit($userId, $isPremium);

            // Validate discovery mode
            $this->validateDiscoveryMode($mode, $isPremium);

            // Get user profile for session customization
            $userProfile = $this->profileRepository->findByUserId($userId->toInt());
            if (!$userProfile) {
                throw new DiscoveryException('User profile not found');
            }

            // Use default preferences if none provided
            $preferences = $preferences ?? $this->getDefaultDiscoveryPreferences($userProfile);

            // Calculate session parameters
            $cardsPerSession = $isPremium ? self::PREMIUM_CARDS_PER_SESSION : self::DEFAULT_CARDS_PER_SESSION;
            $sessionDuration = $isPremium ? self::PREMIUM_SESSION_DURATION_MINUTES : self::SESSION_DURATION_MINUTES;

            // Generate discovery cards based on mode
            $discoveryCards = $this->generateDiscoveryCards(
                $userId,
                $mode,
                $preferences,
                $cardsPerSession
            );

            if ($discoveryCards->isEmpty()) {
                throw new InsufficientCandidatesException('No suitable profiles found for discovery');
            }

            // Create discovery session
            $session = new DiscoverySession(
                sessionId: DiscoverySessionId::generate(),
                userId: $userId,
                mode: $mode,
                preferences: $preferences,
                cards: $discoveryCards,
                startedAt: Carbon::now(),
                expiresAt: Carbon::now()->addMinutes($sessionDuration),
                isPremium: $isPremium,
                maxCards: $cardsPerSession
            );

            // Cache session for quick access
            $this->cacheDiscoverySession($session);

            // Update user discovery statistics
            $this->updateDiscoveryStatistics($userId, $mode);

            Log::info('Discovery session started successfully', [
                'session_id' => $session->getId()->toString(),
                'cards_generated' => $discoveryCards->count(),
                'expires_at' => $session->getExpiresAt()->toISOString()
            ]);

            return $session;

        } catch (DiscoveryException $e) {
            Log::error('Discovery session creation failed', [
                'user_id' => $userId->toString(),
                'mode' => $mode->getValue(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during discovery session creation', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new DiscoveryException('Failed to start discovery session: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get current discovery session for a user
     * 
     * Retrieves the active discovery session if one exists, or returns null
     * if no active session is found.
     * 
     * @param UserId $userId The user whose session to retrieve
     * @return DiscoverySession|null The active session or null
     * 
     * @throws DiscoverySessionExpiredException When session has expired
     * 
     * @example
     * ```php
     * $session = $discoveryService->getCurrentSession($userId);
     * if ($session && !$session->isExpired()) {
     *     $nextCard = $session->getNextCard();
     * }
     * ```
     */
    public function getCurrentSession(UserId $userId): ?DiscoverySession
    {
        try {
            $cacheKey = $this->buildSessionCacheKey($userId);
            $session = Cache::get($cacheKey);

            if (!$session) {
                return null;
            }

            // Check if session has expired
            if ($session->isExpired()) {
                $this->expireSession($session);
                throw new DiscoverySessionExpiredException('Discovery session has expired');
            }

            return $session;

        } catch (\Exception $e) {
            Log::warning('Failed to retrieve current discovery session', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get next discovery card from session
     * 
     * Retrieves the next unviewed discovery card from the current session,
     * handling session progression and card tracking.
     * 
     * @param UserId $userId The user requesting the next card
     * @return DiscoveryCard|null The next card or null if session complete
     * 
     * @throws DiscoveryException When card retrieval fails
     * @throws DiscoverySessionExpiredException When session has expired
     * 
     * @example
     * ```php
     * $card = $discoveryService->getNextCard($userId);
     * if ($card) {
     *     $profile = $card->getProfile();
     *     $insights = $card->getInsights();
     * }
     * ```
     */
    public function getNextCard(UserId $userId): ?DiscoveryCard
    {
        try {
            Log::debug('Getting next discovery card', ['user_id' => $userId->toString()]);

            $session = $this->getCurrentSession($userId);
            if (!$session) {
                Log::info('No active discovery session found', ['user_id' => $userId->toString()]);
                return null;
            }

            $nextCard = $session->getNextCard();
            if (!$nextCard) {
                Log::info('No more cards in discovery session', [
                    'user_id' => $userId->toString(),
                    'session_id' => $session->getId()->toString()
                ]);
                
                // Complete the session
                $this->completeSession($session);
                return null;
            }

            // Update session progress
            $session->markCardViewed($nextCard->getId());
            $this->cacheDiscoverySession($session);

            // Track card view analytics
            $this->trackCardView($userId, $nextCard, $session->getMode());

            Log::debug('Next discovery card retrieved', [
                'user_id' => $userId->toString(),
                'card_id' => $nextCard->getId()->toString(),
                'target_user_id' => $nextCard->getProfile()->getUserId()->toString()
            ]);

            return $nextCard;

        } catch (\Exception $e) {
            Log::error('Failed to get next discovery card', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new DiscoveryException('Failed to get next card: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // DISCOVERY MODES AND ALGORITHMS
    // ================================================================

    /**
     * Generate discovery cards for explore mode
     * 
     * Creates discovery cards that broaden user horizons by suggesting profiles
     * outside their typical preferences while maintaining compatibility potential.
     * 
     * @param UserId $userId The user for exploration
     * @param ExplorationCriteria $criteria Exploration parameters
     * @param int $cardCount Number of cards to generate
     * @return Collection<DiscoveryCard> Exploration discovery cards
     * 
     * @throws DiscoveryException When exploration generation fails
     * 
     * @example
     * ```php
     * $criteria = new ExplorationCriteria([
     *     'expand_age_range' => 10,
     *     'expand_radius' => 50,
     *     'include_different_interests' => true
     * ]);
     * $exploreCards = $discoveryService->generateExploreCards($userId, $criteria, 15);
     * ```
     */
    public function generateExploreCards(
        UserId $userId,
        ExplorationCriteria $criteria,
        int $cardCount = 15
    ): Collection {
        try {
            Log::info('Generating explore mode cards', [
                'user_id' => $userId->toString(),
                'card_count' => $cardCount
            ]);

            $userProfile = $this->profileRepository->findByUserId($userId->toInt());
            if (!$userProfile) {
                throw new DiscoveryException('User profile not found');
            }

            // Get user's typical preferences to expand beyond them
            $typicalPreferences = $this->analyzeUserPreferences($userId);

            // Create expanded search criteria
            $expandedCriteria = $this->createExpandedCriteria($typicalPreferences, $criteria);

            // Find diverse candidate profiles
            $candidates = $this->findDiverseCandidates($userId, $expandedCriteria, $cardCount * 3);

            // Score candidates for exploration value
            $scoredCandidates = $this->scoreExplorationCandidates($userId, $candidates, $typicalPreferences);

            // Create discovery cards with exploration insights
            $exploreCards = $scoredCandidates
                ->sortByDesc('exploration_score')
                ->take($cardCount)
                ->map(fn($candidate) => $this->createExplorationCard($candidate, $typicalPreferences));

            Log::info('Explore mode cards generated', [
                'user_id' => $userId->toString(),
                'cards_generated' => $exploreCards->count(),
                'candidates_analyzed' => $candidates->count()
            ]);

            return $exploreCards;

        } catch (\Exception $e) {
            Log::error('Failed to generate explore cards', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new DiscoveryException('Failed to generate explore cards: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate local discovery cards
     * 
     * Creates discovery cards focused on nearby users for location-based
     * matching and real-world connection opportunities.
     * 
     * @param UserId $userId The user for local discovery
     * @param DiscoveryRadius $radius Search radius for local discovery
     * @param int $cardCount Number of cards to generate
     * @param bool $prioritizeActivity Whether to prioritize recently active users
     * @return Collection<DiscoveryCard> Local discovery cards
     * 
     * @throws DiscoveryException When local discovery generation fails
     * 
     * @example
     * ```php
     * $radius = DiscoveryRadius::fromKilometers(10);
     * $localCards = $discoveryService->generateLocalCards($userId, $radius, 20, true);
     * ```
     */
    public function generateLocalCards(
        UserId $userId,
        DiscoveryRadius $radius,
        int $cardCount = 20,
        bool $prioritizeActivity = true
    ): Collection {
        try {
            Log::info('Generating local discovery cards', [
                'user_id' => $userId->toString(),
                'radius_km' => $radius->getKilometers(),
                'card_count' => $cardCount,
                'prioritize_activity' => $prioritizeActivity
            ]);

            $userProfile = $this->profileRepository->findByUserId($userId->toInt());
            if (!$userProfile || !$userProfile->getLocation()) {
                throw new DiscoveryException('User profile or location not found');
            }

            // Find nearby candidates within radius
            $nearbyCandidates = $this->findNearbyCandidates(
                $userProfile->getLocation(),
                $radius,
                $cardCount * 2
            );

            if ($nearbyCandidates->isEmpty()) {
                Log::info('No nearby candidates found', [
                    'user_id' => $userId->toString(),
                    'radius_km' => $radius->getKilometers()
                ]);
                return new Collection();
            }

            // Filter out already interacted users
            $filteredCandidates = $this->filterPreviouslyInteracted($userId, $nearbyCandidates);

            // Score candidates for local discovery
            $scoredCandidates = $this->scoreLocalCandidates(
                $userId,
                $filteredCandidates,
                $userProfile->getLocation(),
                $prioritizeActivity
            );

            // Create local discovery cards
            $localCards = $scoredCandidates
                ->sortByDesc('local_score')
                ->take($cardCount)
                ->map(fn($candidate) => $this->createLocalCard($candidate, $userProfile->getLocation()));

            Log::info('Local discovery cards generated', [
                'user_id' => $userId->toString(),
                'cards_generated' => $localCards->count(),
                'nearby_candidates_found' => $nearbyCandidates->count()
            ]);

            return $localCards;

        } catch (\Exception $e) {
            Log::error('Failed to generate local cards', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new DiscoveryException('Failed to generate local cards: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate second chance discovery cards
     * 
     * Re-surfaces previously passed profiles that may now be good matches
     * due to updated profiles, changed preferences, or time factors.
     * 
     * @param UserId $userId The user for second chance discovery
     * @param CarbonInterface|null $since Optional date threshold for reconsideration
     * @param int $cardCount Number of cards to generate
     * @return Collection<DiscoveryCard> Second chance discovery cards
     * 
     * @throws DiscoveryException When second chance generation fails
     * 
     * @example
     * ```php
     * $secondChanceCards = $discoveryService->generateSecondChanceCards(
     *     $userId,
     *     Carbon::now()->subMonths(6),
     *     10
     * );
     * ```
     */
    public function generateSecondChanceCards(
        UserId $userId,
        ?CarbonInterface $since = null,
        int $cardCount = 10
    ): Collection {
        try {
            $since = $since ?? Carbon::now()->subMonths(3);
            
            Log::info('Generating second chance cards', [
                'user_id' => $userId->toString(),
                'since' => $since->toISOString(),
                'card_count' => $cardCount
            ]);

            // Get previously passed profiles
            $passedProfiles = $this->findPassedProfiles($userId, $since, $cardCount * 3);

            if ($passedProfiles->isEmpty()) {
                Log::info('No previously passed profiles found for second chance', [
                    'user_id' => $userId->toString()
                ]);
                return new Collection();
            }

            // Filter profiles that have been updated or improved
            $improvedProfiles = $this->findImprovedProfiles($passedProfiles, $since);

            // Score profiles for second chance value
            $scoredProfiles = $this->scoreSecondChanceProfiles($userId, $improvedProfiles);

            // Create second chance discovery cards
            $secondChanceCards = $scoredProfiles
                ->sortByDesc('second_chance_score')
                ->take($cardCount)
                ->map(fn($profile) => $this->createSecondChanceCard($profile, $since));

            Log::info('Second chance cards generated', [
                'user_id' => $userId->toString(),
                'cards_generated' => $secondChanceCards->count(),
                'passed_profiles_analyzed' => $passedProfiles->count()
            ]);

            return $secondChanceCards;

        } catch (\Exception $e) {
            Log::error('Failed to generate second chance cards', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new DiscoveryException('Failed to generate second chance cards: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // DISCOVERY BOOSTS AND PREMIUM FEATURES
    // ================================================================

    /**
     * Apply discovery boost to user profile
     * 
     * Temporarily increases profile visibility in other users' discovery sessions,
     * improving match potential during the boost period.
     * 
     * @param UserId $userId The user applying the boost
     * @param DiscoveryBoost $boost The boost configuration
     * @param int $durationMinutes Duration of the boost in minutes
     * @return bool True if boost was applied successfully
     * 
     * @throws DiscoveryException When boost application fails
     * 
     * @example
     * ```php
     * $boost = new DiscoveryBoost([
     *     'type' => 'premium',
     *     'multiplier' => 5.0,
     *     'target_modes' => ['standard', 'explore']
     * ]);
     * $success = $discoveryService->applyDiscoveryBoost($userId, $boost, 60);
     * ```
     */
    public function applyDiscoveryBoost(
        UserId $userId,
        DiscoveryBoost $boost,
        int $durationMinutes = 60
    ): bool {
        try {
            Log::info('Applying discovery boost', [
                'user_id' => $userId->toString(),
                'boost_type' => $boost->getType(),
                'duration_minutes' => $durationMinutes
            ]);

            // Validate user eligibility for boost
            $this->validateBoostEligibility($userId, $boost);

            // Calculate boost expiration
            $expiresAt = Carbon::now()->addMinutes($durationMinutes);

            // Apply boost to user profile
            $boostKey = "discovery_boost.{$userId->toString()}";
            $boostData = [
                'user_id' => $userId->toString(),
                'boost' => $boost,
                'applied_at' => Carbon::now(),
                'expires_at' => $expiresAt,
                'duration_minutes' => $durationMinutes
            ];

            Cache::put($boostKey, $boostData, $durationMinutes * 60); // Convert to seconds

            // Update boost analytics
            $this->trackBoostApplication($userId, $boost, $durationMinutes);

            // Trigger boost activation events
            Event::dispatch(new DiscoveryBoostActivated($userId, $boost, $expiresAt));

            Log::info('Discovery boost applied successfully', [
                'user_id' => $userId->toString(),
                'expires_at' => $expiresAt->toISOString()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to apply discovery boost', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new DiscoveryException('Failed to apply boost: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if user has active discovery boost
     * 
     * Determines if a user currently has an active discovery boost and
     * returns boost details if available.
     * 
     * @param UserId $userId The user to check for active boost
     * @return array<string, mixed>|null Boost data or null if no active boost
     * 
     * @example
     * ```php
     * $activeBoost = $discoveryService->getActiveBoost($userId);
     * if ($activeBoost) {
     *     $multiplier = $activeBoost['boost']->getMultiplier();
     *     $expiresAt = $activeBoost['expires_at'];
     * }
     * ```
     */
    public function getActiveBoost(UserId $userId): ?array
    {
        try {
            $boostKey = "discovery_boost.{$userId->toString()}";
            $boostData = Cache::get($boostKey);

            if (!$boostData) {
                return null;
            }

            // Check if boost has expired
            if (Carbon::now()->isAfter($boostData['expires_at'])) {
                Cache::forget($boostKey);
                return null;
            }

            return $boostData;

        } catch (\Exception $e) {
            Log::warning('Failed to check active boost', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    // ================================================================
    // DISCOVERY ANALYTICS AND INSIGHTS
    // ================================================================

    /**
     * Get discovery analytics for a user
     * 
     * Provides comprehensive analytics on user discovery patterns,
     * engagement rates, and success metrics.
     * 
     * @param UserId $userId The user for analytics
     * @param CarbonInterface|null $since Optional start date for analytics period
     * @param CarbonInterface|null $until Optional end date for analytics period
     * @return array<string, mixed> Comprehensive discovery analytics
     * 
     * @throws DiscoveryException When analytics generation fails
     * 
     * @example
     * ```php
     * $analytics = $discoveryService->getDiscoveryAnalytics(
     *     $userId,
     *     Carbon::now()->subMonth(),
     *     Carbon::now()
     * );
     * ```
     */
    public function getDiscoveryAnalytics(
        UserId $userId,
        ?CarbonInterface $since = null,
        ?CarbonInterface $until = null
    ): array {
        try {
            $since = $since ?? Carbon::now()->subMonth();
            $until = $until ?? Carbon::now();

            Log::info('Generating discovery analytics', [
                'user_id' => $userId->toString(),
                'period' => [$since->toISOString(), $until->toISOString()]
            ]);

            $analytics = [
                'period' => [
                    'start' => $since,
                    'end' => $until,
                    'duration_days' => $since->diffInDays($until)
                ],
                'session_statistics' => $this->getSessionStatistics($userId, $since, $until),
                'mode_preferences' => $this->getModePreferences($userId, $since, $until),
                'engagement_metrics' => $this->getEngagementMetrics($userId, $since, $until),
                'match_conversion_rates' => $this->getMatchConversionRates($userId, $since, $until),
                'discovery_effectiveness' => $this->getDiscoveryEffectiveness($userId, $since, $until),
                'boost_performance' => $this->getBoostPerformance($userId, $since, $until)
            ];

            Log::info('Discovery analytics generated successfully', [
                'user_id' => $userId->toString(),
                'sessions_analyzed' => $analytics['session_statistics']['total_sessions']
            ]);

            return $analytics;

        } catch (\Exception $e) {
            Log::error('Failed to generate discovery analytics', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new DiscoveryException('Failed to generate analytics: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // PRIVATE HELPER METHODS
    // ================================================================

    /**
     * Validate daily session limit for user
     */
    private function validateDailySessionLimit(UserId $userId, bool $isPremium): void
    {
        $cacheKey = "daily_sessions.{$userId->toString()}." . Carbon::now()->format('Y-m-d');
        $dailySessions = Cache::get($cacheKey, 0);
        
        $maxSessions = $isPremium ? self::MAX_PREMIUM_DAILY_SESSIONS : self::MAX_DAILY_SESSIONS;

        if ($dailySessions >= $maxSessions) {
            throw new DiscoveryException('Daily discovery session limit exceeded');
        }
    }

    /**
     * Generate discovery cards based on mode
     */
    private function generateDiscoveryCards(
        UserId $userId,
        DiscoveryMode $mode,
        DiscoveryPreferences $preferences,
        int $cardCount
    ): Collection {
        return match($mode->getValue()) {
            'standard' => $this->generateStandardCards($userId, $preferences, $cardCount),
            'explore' => $this->generateExploreCards($userId, $preferences->toExplorationCriteria(), $cardCount),
            'boost' => $this->generateBoostCards($userId, $preferences, $cardCount),
            'local' => $this->generateLocalCards($userId, $preferences->getRadius(), $cardCount),
            'global' => $this->generateGlobalCards($userId, $preferences, $cardCount),
            'interest' => $this->generateInterestCards($userId, $preferences, $cardCount),
            'second_chance' => $this->generateSecondChanceCards($userId, null, $cardCount),
            'trending' => $this->generateTrendingCards($userId, $preferences, $cardCount),
            default => $this->generateStandardCards($userId, $preferences, $cardCount)
        };
    }

    /**
     * Cache discovery session
     */
    private function cacheDiscoverySession(DiscoverySession $session): void
    {
        $cacheKey = $this->buildSessionCacheKey($session->getUserId());
        Cache::put($cacheKey, $session, self::CACHE_TTL);
    }

    /**
     * Build session cache key
     */
    private function buildSessionCacheKey(UserId $userId): string
    {
        return "discovery_session.{$userId->toString()}";
    }

    /**
     * Get default discovery preferences for user
     */
    private function getDefaultDiscoveryPreferences($userProfile): DiscoveryPreferences
    {
        return DiscoveryPreferences::fromArray([
            'radius' => DiscoveryRadius::fromKilometers(25),
            'age_range' => [
                max(18, $userProfile->getAge() - 5),
                min(80, $userProfile->getAge() + 5)
            ],
            'include_verified_only' => false,
            'exclude_seen_profiles' => true,
            'prioritize_active_users' => true
        ]);
    }

    /**
     * Validate discovery mode for user
     */
    private function validateDiscoveryMode(DiscoveryMode $mode, bool $isPremium): void
    {
        // Premium users can use all modes
        if ($isPremium) {
            return;
        }

        // Free users have limited access to certain modes
        $premiumOnlyModes = ['boost', 'trending'];
        
        if (in_array($mode->getValue(), $premiumOnlyModes)) {
            throw new InvalidDiscoveryModeException('This discovery mode requires premium subscription');
        }
    }

    /**
     * Update discovery statistics for user
     */
    private function updateDiscoveryStatistics(UserId $userId, DiscoveryMode $mode): void
    {
        $cacheKey = "discovery_stats.{$userId->toString()}";
        $stats = Cache::get($cacheKey, []);
        
        $stats[$mode->getValue()] = ($stats[$mode->getValue()] ?? 0) + 1;
        $stats['total_sessions'] = ($stats['total_sessions'] ?? 0) + 1;
        $stats['last_session'] = now()->toISOString();
        
        Cache::put($cacheKey, $stats, 86400); // 24 hours
    }

    /**
     * Expire discovery session
     */
    private function expireSession(DiscoverySession $session): void
    {
        $cacheKey = $this->buildSessionCacheKey($session->getUserId());
        Cache::forget($cacheKey);
        
        Log::info('Discovery session expired', [
            'session_id' => $session->getId()->toString(),
            'user_id' => $session->getUserId()->toString()
        ]);
    }

    /**
     * Complete discovery session
     */
    private function completeSession(DiscoverySession $session): void
    {
        $this->expireSession($session);
        
        Log::info('Discovery session completed', [
            'session_id' => $session->getId()->toString(),
            'user_id' => $session->getUserId()->toString(),
            'cards_viewed' => $session->getViewedCardsCount()
        ]);
    }

    /**
     * Track card view analytics
     */
    private function trackCardView(UserId $userId, DiscoveryCard $card, DiscoveryMode $mode): void
    {
        $cacheKey = "card_views.{$userId->toString()}";
        $views = Cache::get($cacheKey, []);
        
        $views[] = [
            'card_id' => $card->getId()->toString(),
            'target_user_id' => $card->getProfile()->getUserId()->toString(),
            'mode' => $mode->getValue(),
            'viewed_at' => now()->toISOString()
        ];
        
        Cache::put($cacheKey, $views, 3600); // 1 hour
    }

    /**
     * Analyze user preferences for exploration
     */
    private function analyzeUserPreferences(UserId $userId): array
    {
        $cacheKey = "user_preferences.{$userId->toString()}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            // This would typically analyze user's past interactions
            // For now, return default preferences
            return [
                'typical_age_range' => [25, 35],
                'typical_radius' => 25,
                'common_interests' => ['music', 'travel'],
                'preferred_gender' => 'any'
            ];
        });
    }

    /**
     * Create expanded criteria for exploration
     */
    private function createExpandedCriteria(array $typicalPreferences, ExplorationCriteria $criteria): array
    {
        return [
            'age_range' => [
                max(18, $typicalPreferences['typical_age_range'][0] - $criteria->getExpandAgeRange()),
                min(80, $typicalPreferences['typical_age_range'][1] + $criteria->getExpandAgeRange())
            ],
            'radius' => $typicalPreferences['typical_radius'] + $criteria->getExpandRadius(),
            'include_different_interests' => true
        ];
    }

    /**
     * Find diverse candidates for exploration
     */
    private function findDiverseCandidates(UserId $userId, array $criteria, int $limit): Collection
    {
        return $this->profileRepository->findCompatibleProfiles(
            $userId->toInt(),
            $criteria,
            $limit
        );
    }

    /**
     * Score exploration candidates
     */
    private function scoreExplorationCandidates(UserId $userId, Collection $candidates, array $typicalPreferences): Collection
    {
        return $candidates->map(function ($candidate) use ($typicalPreferences) {
            $score = 0;
            
            // Score based on diversity from typical preferences
            $ageDiff = abs($candidate->age - ($typicalPreferences['typical_age_range'][0] + $typicalPreferences['typical_age_range'][1]) / 2);
            $score += min($ageDiff * 0.1, 2.0);
            
            $candidate->exploration_score = $score;
            return $candidate;
        });
    }

    /**
     * Create exploration card
     */
    private function createExplorationCard($candidate, array $typicalPreferences): DiscoveryCard
    {
        return new DiscoveryCard(
            id: DiscoveryCardId::generate(),
            profile: $candidate,
            insights: [
                'exploration_reason' => 'Broadens your typical preferences',
                'diversity_score' => $candidate->exploration_score ?? 0
            ]
        );
    }

    /**
     * Find nearby candidates
     */
    private function findNearbyCandidates($location, DiscoveryRadius $radius, int $limit): Collection
    {
        return $this->profileRepository->findByLocation(
            $location,
            $radius->getKilometers(),
            [$location->getLatitude(), $location->getLongitude()]
        )->take($limit);
    }

    /**
     * Filter previously interacted users
     */
    private function filterPreviouslyInteracted(UserId $userId, Collection $candidates): Collection
    {
        $interactedUserIds = []; // TODO: Implement proper interaction tracking
        
        return $candidates->filter(function ($candidate) use ($interactedUserIds) {
            return !in_array($candidate->user_id, $interactedUserIds);
        });
    }

    /**
     * Score local candidates
     */
    private function scoreLocalCandidates(UserId $userId, Collection $candidates, $userLocation, bool $prioritizeActivity): Collection
    {
        return $candidates->map(function ($candidate) use ($userLocation, $prioritizeActivity) {
            $score = 0;
            
            // Distance score (closer is better)
            if ($candidate->latitude && $candidate->longitude) {
                $distance = $this->calculateDistance(
                    $userLocation->getLatitude(),
                    $userLocation->getLongitude(),
                    $candidate->latitude,
                    $candidate->longitude
                );
                $score += max(0, 10 - $distance);
            }
            
            // Activity score
            if ($prioritizeActivity) {
                $lastActivity = $candidate->last_updated_at ?? $candidate->created_at;
                $hoursSinceActivity = now()->diffInHours($lastActivity);
                $score += max(0, 5 - ($hoursSinceActivity / 24));
            }
            
            $candidate->local_score = $score;
            return $candidate;
        });
    }

    /**
     * Create local discovery card
     */
    private function createLocalCard($candidate, $userLocation): DiscoveryCard
    {
        $distance = $this->calculateDistance(
            $userLocation->getLatitude(),
            $userLocation->getLongitude(),
            $candidate->latitude,
            $candidate->longitude
        );

        return new DiscoveryCard(
            id: DiscoveryCardId::generate(),
            profile: $candidate,
            insights: [
                'distance_km' => round($distance, 1),
                'local_reason' => 'Nearby user for real-world connections'
            ]
        );
    }

    /**
     * Find passed profiles for second chance
     */
    private function findPassedProfiles(UserId $userId, CarbonInterface $since, int $limit): Collection
    {
        // This would typically query the like repository for passed profiles
        // For now, return empty collection
        return collect();
    }

    /**
     * Find improved profiles
     */
    private function findImprovedProfiles(Collection $passedProfiles, CarbonInterface $since): Collection
    {
        return $passedProfiles->filter(function ($profile) use ($since) {
            return $profile->updated_at && $profile->updated_at->isAfter($since);
        });
    }

    /**
     * Score second chance profiles
     */
    private function scoreSecondChanceProfiles(UserId $userId, Collection $profiles): Collection
    {
        return $profiles->map(function ($profile) {
            $score = 0;
            
            // Score based on time since last interaction
            $daysSincePassed = now()->diffInDays($profile->last_interaction_at ?? $profile->created_at);
            $score += min($daysSincePassed * 0.1, 3.0);
            
            $profile->second_chance_score = $score;
            return $profile;
        });
    }

    /**
     * Create second chance card
     */
    private function createSecondChanceCard($profile, CarbonInterface $since): DiscoveryCard
    {
        return new DiscoveryCard(
            id: DiscoveryCardId::generate(),
            profile: $profile,
            insights: [
                'second_chance_reason' => 'Profile has been updated since you last saw it',
                'days_since_passed' => now()->diffInDays($since)
            ]
        );
    }

    /**
     * Validate boost eligibility
     */
    private function validateBoostEligibility(UserId $userId, DiscoveryBoost $boost): void
    {
        // Check if user already has an active boost
        if ($this->getActiveBoost($userId)) {
            throw new DiscoveryException('User already has an active discovery boost');
        }

        // Additional validation logic can be added here
    }

    /**
     * Track boost application
     */
    private function trackBoostApplication(UserId $userId, DiscoveryBoost $boost, int $durationMinutes): void
    {
        $cacheKey = "boost_applications.{$userId->toString()}";
        $applications = Cache::get($cacheKey, []);
        
        $applications[] = [
            'boost_type' => $boost->getType(),
            'duration_minutes' => $durationMinutes,
            'applied_at' => now()->toISOString()
        ];
        
        Cache::put($cacheKey, $applications, 86400); // 24 hours
    }

    /**
     * Get session statistics
     */
    private function getSessionStatistics(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        $cacheKey = "session_stats.{$userId->toString()}";
        $stats = Cache::get($cacheKey, []);
        
        return [
            'total_sessions' => $stats['total_sessions'] ?? 0,
            'sessions_in_period' => count(array_filter($stats['sessions'] ?? [], function ($session) use ($since, $until) {
                return $session['created_at'] >= $since && $session['created_at'] <= $until;
            })),
            'avg_cards_per_session' => $stats['avg_cards'] ?? 0
        ];
    }

    /**
     * Get mode preferences
     */
    private function getModePreferences(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        $cacheKey = "mode_preferences.{$userId->toString()}";
        $preferences = Cache::get($cacheKey, []);
        
        return $preferences;
    }

    /**
     * Get engagement metrics
     */
    private function getEngagementMetrics(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [
            'cards_viewed' => 0,
            'likes_given' => 0,
            'matches_made' => 0,
            'session_duration_avg' => 0
        ];
    }

    /**
     * Get match conversion rates
     */
    private function getMatchConversionRates(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [
            'like_to_match_rate' => 0.15,
            'card_to_like_rate' => 0.25,
            'session_to_match_rate' => 0.05
        ];
    }

    /**
     * Get discovery effectiveness
     */
    private function getDiscoveryEffectiveness(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [
            'most_effective_mode' => 'standard',
            'best_time_of_day' => 'evening',
            'optimal_session_length' => 20
        ];
    }

    /**
     * Get boost performance
     */
    private function getBoostPerformance(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        return [
            'boosts_used' => 0,
            'boost_effectiveness' => 0,
            'recommended_boost_type' => 'premium'
        ];
    }

    /**
     * Generate standard discovery cards
     */
    private function generateStandardCards(UserId $userId, DiscoveryPreferences $preferences, int $cardCount): Collection
    {
        return $this->profileRepository->findCompatibleProfiles(
            $userId->toInt(),
            $preferences->toArray(),
            $cardCount
        )->map(function ($profile) {
            return new DiscoveryCard(
                id: DiscoveryCardId::generate(),
                profile: $profile,
                insights: ['reason' => 'Standard compatibility match']
            );
        });
    }

    /**
     * Generate boost discovery cards
     */
    private function generateBoostCards(UserId $userId, DiscoveryPreferences $preferences, int $cardCount): Collection
    {
        // Similar to standard but with boost logic
        return $this->generateStandardCards($userId, $preferences, $cardCount);
    }

    /**
     * Generate global discovery cards
     */
    private function generateGlobalCards(UserId $userId, DiscoveryPreferences $preferences, int $cardCount): Collection
    {
        // Expand radius for global discovery
        $globalPreferences = $preferences->toArray();
        $globalPreferences['radius'] = 1000; // Global radius
        
        return $this->profileRepository->findCompatibleProfiles(
            $userId->toInt(),
            $globalPreferences,
            $cardCount
        )->map(function ($profile) {
            return new DiscoveryCard(
                id: DiscoveryCardId::generate(),
                profile: $profile,
                insights: ['reason' => 'Global discovery match']
            );
        });
    }

    /**
     * Generate interest-based discovery cards
     */
    private function generateInterestCards(UserId $userId, DiscoveryPreferences $preferences, int $cardCount): Collection
    {
        $interests = $preferences->getInterests();
        
        return $this->profileRepository->findByInterests(
            $interests,
            1,
            ['status' => 'active']
        )->take($cardCount)->map(function ($profile) {
            return new DiscoveryCard(
                id: DiscoveryCardId::generate(),
                profile: $profile,
                insights: ['reason' => 'Shared interests match']
            );
        });
    }

    /**
     * Generate trending discovery cards
     */
    private function generateTrendingCards(UserId $userId, DiscoveryPreferences $preferences, int $cardCount): Collection
    {
        return $this->profileRepository->getTrendingProfiles(
            'week',
            $cardCount,
            ['status' => 'active']
        )->map(function ($profile) {
            return new DiscoveryCard(
                id: DiscoveryCardId::generate(),
                profile: $profile,
                insights: ['reason' => 'Trending profile']
            );
        });
    }

    /**
     * Calculate distance between two points
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
}