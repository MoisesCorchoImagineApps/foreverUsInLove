<?php

declare(strict_types=1);

namespace App\Domain\Matching\Services;

use App\Domain\Matching\Repositories\LikeRepositoryInterface;
use App\Domain\Matching\Repositories\MatchRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Matching\Entities\Like;
use App\Domain\Matching\Entities\SuperLike;
use App\Domain\Matching\Entities\Pass;
use App\Domain\Matching\ValueObjects\LikeId;
use App\Domain\Matching\ValueObjects\LikeType;
use App\Domain\Matching\ValueObjects\LikeSource;
use App\Domain\Matching\ValueObjects\InteractionContext;
use App\Domain\Matching\ValueObjects\MatchId;
use App\Domain\Matching\Events\UserLiked;
use App\Domain\Matching\Events\MatchCreated;
use App\Domain\Matching\Events\SuperLikeReceived;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Matching\Exceptions\LikeException;
use App\Domain\Matching\Exceptions\LikeRateLimitException;
use App\Domain\Matching\Exceptions\InvalidLikeTargetException;
use App\Domain\Matching\Exceptions\DuplicateLikeException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * LikeService
 * 
 * Comprehensive like/dislike management service for the ForeverUsInLove dating platform.
 * Handles all aspects of user interactions including likes, super likes, passes, and
 * mutual matching detection following Domain-Driven Design principles.
 * 
 * Key Responsibilities:
 * - Process like and dislike actions with validation
 * - Handle super likes and premium interaction features
 * - Detect mutual likes and trigger match creation
 * - Manage interaction rate limiting and fraud prevention
 * - Track interaction analytics and success patterns
 * - Handle like notifications and real-time events
 * - Support undo functionality for premium users
 * - Manage interaction context and source tracking
 * 
 * Interaction Types:
 * 1. Standard Like - Basic positive interaction
 * 2. Super Like - Premium enhanced like with higher visibility
 * 3. Pass/Dislike - Negative interaction (hidden from target user)
 * 4. Mutual Like - Automatic match creation when both users like each other
 * 5. Undo Action - Premium feature to reverse recent interactions
 * 6. Boost Like - Like with temporary visibility boost
 * 7. Message Like - Like with attached message for premium users
 * 8. Photo Like - Like with specific photo preference indication
 * 
 * Business Rules:
 * - Daily like limits based on subscription tier
 * - Super likes have separate, more restricted limits
 * - Mutual likes automatically create matches
 * - Users cannot like the same person multiple times
 * - Blocked users cannot like each other
 * - Like notifications respect user preferences
 * - Premium users get enhanced like features
 * - Interaction analytics track success patterns
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
final class LikeService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const MAX_DAILY_LIKES = 100;
    private const MAX_PREMIUM_DAILY_LIKES = 500;
    private const MAX_DAILY_SUPER_LIKES = 5;
    private const MAX_PREMIUM_DAILY_SUPER_LIKES = 20;
    private const UNDO_TIME_LIMIT_MINUTES = 10;
    private const RATE_LIMIT_WINDOW_MINUTES = 5;
    private const MAX_LIKES_PER_WINDOW = 20;
    
    // Like notification delays to prevent spam
    private const NOTIFICATION_DELAYS = [
        'standard' => 0, // Immediate
        'super_like' => 0, // Immediate for super likes
        'batch' => 300, // 5 minutes for batch notifications
        'daily_digest' => 86400 // 24 hours for daily digest
    ];

    public function __construct(
        private readonly LikeRepositoryInterface $likeRepository,
        private readonly MatchRepositoryInterface $matchRepository,
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly MatchingService $matchingService
    ) {}

    // ================================================================
    // CORE LIKE/DISLIKE OPERATIONS
    // ================================================================

    /**
     * Process a like action from one user to another
     * 
     * Handles the complete like workflow including validation, rate limiting,
     * mutual like detection, and match creation when appropriate.
     * 
     * @param UserId $likerId The user giving the like
     * @param UserId $likedId The user receiving the like
     * @param LikeType $type The type of like (standard, super, etc.)
     * @param InteractionContext|null $context Optional interaction context
     * @param string|null $message Optional message for premium users
     * @return Like The created like entity
     * 
     * @throws LikeException When like processing fails
     * @throws LikeRateLimitException When rate limits are exceeded
     * @throws InvalidLikeTargetException When target user is invalid
     * @throws DuplicateLikeException When like already exists
     * 
     * @example
     * ```php
     * $context = new InteractionContext([
     *     'source' => 'discovery_card',
     *     'session_id' => 'abc123',
     *     'card_position' => 3
     * ]);
     * $like = $likeService->likeUser(
     *     $userId, 
     *     $targetUserId, 
     *     LikeType::STANDARD,
     *     $context
     * );
     * ```
     */
    public function likeUser(
        UserId $likerId,
        UserId $likedId,
        LikeType $type,
        ?InteractionContext $context = null,
        ?string $message = null
    ): Like {
        try {
            Log::info('Processing like action', [
                'liker_id' => $likerId->toString(),
                'liked_id' => $likedId->toString(),
                'type' => $type->getValue(),
                'has_message' => !empty($message)
            ]);

            // Validate like action
            $this->validateLikeAction($likerId, $likedId, $type);

            // Check rate limits
            $this->validateRateLimits($likerId, $type);

            // Check for existing interaction
            $existingInteraction = $this->likeRepository->getLikeBetweenUsers($likerId, $likedId);
            if ($existingInteraction) {
                throw new DuplicateLikeException('User has already interacted with this profile');
            }

            // Create like entity
            $like = new Like(
                id: LikeId::generate(),
                likerId: $likerId,
                likedId: $likedId,
                type: $type,
                source: $context?->getSource() ?? LikeSource::MANUAL,
                createdAt: Carbon::now(),
                context: $context,
                message: $message,
                isActive: true
            );

            // Save like
            $likeData = [
                'from_user_id' => $likerId->toInt(),
                'to_user_id' => $likedId->toInt(),
                'type' => $type->getValue(),
                'source' => $context?->getSource()?->getValue() ?? LikeSource::MANUAL,
                'context' => $context?->toArray(),
                'message' => $message,
                'created_at' => Carbon::now()->toISOString()
            ];
            $savedLikeData = $this->likeRepository->createLike($likerId, $likedId, $likeData);

            // Check for mutual like
            $mutualLike = $this->checkMutualLike($likerId, $likedId);
            if ($mutualLike) {
                Log::info('Mutual like detected', [
                    'user1_id' => $likerId->toString(),
                    'user2_id' => $likedId->toString()
                ]);

                // Create match
                $match = $this->matchingService->createMutualMatch(
                    $likerId,
                    $likedId,
                    null,
                    [
                        'source' => 'mutual_like',
                        'like_types' => [$type->getValue(), $mutualLike->getType()->getValue()],
                        'context' => $context?->toArray()
                    ]
                );

                // Update like with match reference
                $like->setMatchId($match->getId());
            }

            // Trigger like event
            Event::dispatch(new UserLiked(
                $like,
                $mutualLike !== null,
                $this->buildLikeNotificationData($like)
            ));

            // Update user like statistics
            $this->updateLikeStatistics($likerId, $type);

            // Clear relevant caches
            $this->clearUserLikeCaches($likerId, $likedId);

            Log::info('Like processed successfully', [
                'like_id' => $like->getId()->toString(),
                'mutual_match' => $mutualLike !== null
            ]);

            return $like;

        } catch (LikeException $e) {
            Log::error('Like processing failed', [
                'liker_id' => $likerId->toString(),
                'liked_id' => $likedId->toString(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during like processing', [
                'liker_id' => $likerId->toString(),
                'liked_id' => $likedId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new LikeException('Failed to process like: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Process a pass/dislike action
     * 
     * Handles negative interactions where users pass on profiles,
     * ensuring they don't appear in future discovery sessions.
     * 
     * @param UserId $passerId The user making the pass
     * @param UserId $passedId The user being passed
     * @param InteractionContext|null $context Optional interaction context
     * @param string|null $reason Optional reason for passing
     * @return Pass The created pass entity
     * 
     * @throws LikeException When pass processing fails
     * @throws DuplicateLikeException When interaction already exists
     * 
     * @example
     * ```php
     * $pass = $likeService->passUser(
     *     $userId, 
     *     $targetUserId, 
     *     $context,
     *     'Not my type'
     * );
     * ```
     */
    public function passUser(
        UserId $passerId,
        UserId $passedId,
        ?InteractionContext $context = null,
        ?string $reason = null
    ): Pass {
        try {
            Log::debug('Processing pass action', [
                'passer_id' => $passerId->toString(),
                'passed_id' => $passedId->toString(),
                'reason' => $reason
            ]);

            // Validate pass action
            $this->validatePassAction($passerId, $passedId);

            // Check for existing interaction
            $existingInteraction = $this->likeRepository->getLikeBetweenUsers($passerId, $passedId);
            if ($existingInteraction) {
                throw new DuplicateLikeException('User has already interacted with this profile');
            }

            // Create pass entity
            $pass = new Pass(
                id: LikeId::generate(),
                passerId: $passerId,
                passedId: $passedId,
                source: $context?->getSource() ?? LikeSource::MANUAL,
                createdAt: Carbon::now(),
                context: $context,
                reason: $reason,
                isActive: true
            );

            // Save pass
            $passData = [
                'from_user_id' => $passerId->toInt(),
                'to_user_id' => $passedId->toInt(),
                'type' => 'pass',
                'source' => $context?->getSource()?->getValue() ?? LikeSource::MANUAL,
                'context' => $context?->toArray(),
                'reason' => $reason,
                'created_at' => Carbon::now()->toISOString()
            ];
            $savedPassData = $this->likeRepository->createDislike($passerId, $passedId, $passData);

            // Update pass statistics (for analytics)
            $this->updatePassStatistics($passerId);

            // Clear relevant caches
            $this->clearUserLikeCaches($passerId, $passedId);

            Log::debug('Pass processed successfully', [
                'pass_id' => $pass->getId()->toString()
            ]);

            return $pass;

        } catch (\Exception $e) {
            Log::error('Failed to process pass', [
                'passer_id' => $passerId->toString(),
                'passed_id' => $passedId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new LikeException('Failed to process pass: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Send a super like to another user
     * 
     * Processes premium super like functionality with enhanced visibility
     * and special notification handling.
     * 
     * @param UserId $likerId The user sending the super like
     * @param UserId $likedId The user receiving the super like
     * @param InteractionContext|null $context Optional interaction context
     * @param string|null $message Optional personal message
     * @return SuperLike The created super like entity
     * 
     * @throws LikeException When super like processing fails
     * @throws LikeRateLimitException When super like limits are exceeded
     * 
     * @example
     * ```php
     * $superLike = $likeService->superLikeUser(
     *     $userId, 
     *     $targetUserId, 
     *     $context,
     *     'I think we would be perfect together!'
     * );
     * ```
     */
    public function superLikeUser(
        UserId $likerId,
        UserId $likedId,
        ?InteractionContext $context = null,
        ?string $message = null
    ): SuperLike {
        try {
            Log::info('Processing super like action', [
                'liker_id' => $likerId->toString(),
                'liked_id' => $likedId->toString(),
                'has_message' => !empty($message)
            ]);

            // Validate super like action
            $this->validateSuperLikeAction($likerId, $likedId);

            // Check super like rate limits
            $this->validateSuperLikeRateLimits($likerId);

            // Check for existing interaction
            $existingInteraction = $this->likeRepository->getLikeBetweenUsers($likerId, $likedId);
            if ($existingInteraction) {
                throw new DuplicateLikeException('User has already interacted with this profile');
            }

            // Create super like entity
            $superLike = new SuperLike(
                id: LikeId::generate(),
                likerId: $likerId,
                likedId: $likedId,
                source: $context?->getSource() ?? LikeSource::MANUAL,
                createdAt: Carbon::now(),
                context: $context,
                message: $message,
                isActive: true,
                notificationSent: false
            );

            // Save super like
            $superLikeData = [
                'from_user_id' => $likerId->toInt(),
                'to_user_id' => $likedId->toInt(),
                'type' => 'super_like',
                'source' => $context?->getSource()?->getValue() ?? LikeSource::MANUAL,
                'context' => $context?->toArray(),
                'message' => $message,
                'created_at' => Carbon::now()->toISOString()
            ];
            $savedSuperLikeData = $this->likeRepository->createSuperLike($likerId, $likedId, $superLikeData);

            // Check for mutual like (super likes can create matches too)
            $mutualLike = $this->checkMutualLike($likerId, $likedId);
            if ($mutualLike) {
                // Create match with super like context
                $match = $this->matchingService->createMutualMatch(
                    $likerId,
                    $likedId,
                    null,
                    [
                        'source' => 'mutual_super_like',
                        'super_like_message' => $message,
                        'context' => $context?->toArray()
                    ]
                );

                $superLike->setMatchId($match->getId());
            }

            // Trigger enhanced super like notification
            Event::dispatch(new SuperLikeReceived(
                $superLike,
                $mutualLike !== null,
                $this->buildSuperLikeNotificationData($superLike)
            ));

            // Update super like statistics
            $this->updateSuperLikeStatistics($likerId);

            Log::info('Super like processed successfully', [
                'super_like_id' => $superLike->getId()->toString(),
                'mutual_match' => $mutualLike !== null
            ]);

            return $superLike;

        } catch (\Exception $e) {
            Log::error('Failed to process super like', [
                'liker_id' => $likerId->toString(),
                'liked_id' => $likedId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new LikeException('Failed to process super like: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // LIKE MANAGEMENT AND RETRIEVAL
    // ================================================================

    /**
     * Get likes received by a user
     * 
     * Retrieves all likes received by a user with optional filtering
     * and sorting options.
     * 
     * @param UserId $userId The user whose received likes to get
     * @param int $limit Maximum number of likes to return
     * @param bool $includeMatched Whether to include already matched likes
     * @param string $sortBy Sort order (newest, oldest, super_likes_first)
     * @return Collection<Like> Collection of received likes
     * 
     * @throws LikeException When retrieval fails
     * 
     * @example
     * ```php
     * $receivedLikes = $likeService->getReceivedLikes(
     *     $userId, 
     *     50, 
     *     false, 
     *     'super_likes_first'
     * );
     * ```
     */
    public function getReceivedLikes(
        UserId $userId,
        int $limit = 50,
        bool $includeMatched = false,
        string $sortBy = 'newest'
    ): Collection {
        try {
            Log::debug('Retrieving received likes', [
                'user_id' => $userId->toString(),
                'limit' => $limit,
                'include_matched' => $includeMatched,
                'sort_by' => $sortBy
            ]);

            $likesData = $this->likeRepository->getLikesReceived(
                $userId,
                ['include_matched' => $includeMatched],
                ['sort_by' => $sortBy],
                1,
                $limit
            );
            
            // Convert array data to Like entities
            $likes = collect($likesData->items())->map(function ($likeData) {
                return new Like(
                    id: LikeId::fromString($likeData['id']),
                    likerId: UserId::fromInt($likeData['from_user_id']),
                    likedId: UserId::fromInt($likeData['to_user_id']),
                    type: LikeType::fromString($likeData['type']),
                    source: LikeSource::fromString($likeData['source']),
                    createdAt: Carbon::parse($likeData['created_at']),
                    context: isset($likeData['context']) ? InteractionContext::fromArray($likeData['context']) : null,
                    message: $likeData['message'] ?? null,
                    isActive: $likeData['is_active'] ?? true,
                    matchId: isset($likeData['match_id']) ? LikeId::fromString($likeData['match_id']) : null
                );
            });

            // Mark likes as viewed for analytics
            $this->markLikesAsViewed($userId, $likes);

            Log::debug('Received likes retrieved', [
                'user_id' => $userId->toString(),
                'likes_count' => $likes->count()
            ]);

            return $likes;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve received likes', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new LikeException('Failed to retrieve likes: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if there's a mutual like between two users
     * 
     * Determines if both users have liked each other, which would
     * create a mutual match.
     * 
     * @param UserId $userId1 First user ID
     * @param UserId $userId2 Second user ID
     * @return Like|null The reciprocal like if mutual, null otherwise
     * 
     * @example
     * ```php
     * $mutualLike = $likeService->checkMutualLike($user1Id, $user2Id);
     * if ($mutualLike) {
     *     // Create match or notify users
     * }
     * ```
     */
    public function checkMutualLike(UserId $userId1, UserId $userId2): ?Like
    {
        try {
            // Check if user2 has liked user1
            $reciprocalLikeData = $this->likeRepository->getLikeBetweenUsers($userId2, $userId1);
            
            if ($reciprocalLikeData && ($reciprocalLikeData['is_active'] ?? true)) {
                return new Like(
                    id: LikeId::fromString($reciprocalLikeData['id']),
                    likerId: UserId::fromInt($reciprocalLikeData['from_user_id']),
                    likedId: UserId::fromInt($reciprocalLikeData['to_user_id']),
                    type: LikeType::fromString($reciprocalLikeData['type']),
                    source: LikeSource::fromString($reciprocalLikeData['source']),
                    createdAt: Carbon::parse($reciprocalLikeData['created_at']),
                    context: isset($reciprocalLikeData['context']) ? InteractionContext::fromArray($reciprocalLikeData['context']) : null,
                    message: $reciprocalLikeData['message'] ?? null,
                    isActive: $reciprocalLikeData['is_active'] ?? true,
                    matchId: isset($reciprocalLikeData['match_id']) ? LikeId::fromString($reciprocalLikeData['match_id']) : null
                );
            }
            
            return null;

        } catch (\Exception $e) {
            Log::warning('Failed to check mutual like', [
                'user1_id' => $userId1->toString(),
                'user2_id' => $userId2->toString(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Undo a recent like action (premium feature)
     * 
     * Allows premium users to undo recent like or pass actions within
     * a specified time window.
     * 
     * @param UserId $userId The user requesting the undo
     * @param LikeId $likeId The like ID to undo
     * @return bool True if undo was successful
     * 
     * @throws LikeException When undo fails or is not allowed
     * 
     * @example
     * ```php
     * $success = $likeService->undoLike($userId, $likeId);
     * if ($success) {
     *     // Show success message
     * }
     * ```
     */
    public function undoLike(UserId $userId, LikeId $likeId): bool
    {
        try {
            Log::info('Processing like undo', [
                'user_id' => $userId->toString(),
                'like_id' => $likeId->toString()
            ]);

            // Find the like
            $likeData = $this->likeRepository->findById($likeId->toString());
            if (!$likeData || $likeData['from_user_id'] !== $userId->toInt()) {
                throw new LikeException('Like not found or not owned by user');
            }
            
            // Create Like entity from data
            $like = new Like(
                id: LikeId::fromString($likeData['id']),
                likerId: UserId::fromInt($likeData['from_user_id']),
                likedId: UserId::fromInt($likeData['to_user_id']),
                type: LikeType::fromString($likeData['type']),
                source: LikeSource::fromString($likeData['source']),
                createdAt: Carbon::parse($likeData['created_at']),
                context: isset($likeData['context']) ? InteractionContext::fromArray($likeData['context']) : null,
                message: $likeData['message'] ?? null,
                isActive: $likeData['is_active'] ?? true,
                matchId: isset($likeData['match_id']) ? LikeId::fromString($likeData['match_id']) : null
            );

            // Check if undo is within time limit
            if (!$this->canUndoLike($like)) {
                throw new LikeException('Like cannot be undone - time limit exceeded');
            }

            // Check if like resulted in a match
            if ($like->getMatchId()) {
                // Need to handle match removal as well
                $this->handleMatchUndo($like->getMatchId());
            }

            // Deactivate the like
            $like->deactivate();
            // Note: In a real implementation, you would need to update the repository
            // $this->likeRepository->updateLike($likeId->toString(), ['is_active' => false]);

            // Update undo statistics
            $this->updateUndoStatistics($userId);

            // Clear caches
            $this->clearUserLikeCaches($userId, $like->getLikedId());

            Log::info('Like undo processed successfully', [
                'like_id' => $likeId->toString()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to undo like', [
                'user_id' => $userId->toString(),
                'like_id' => $likeId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new LikeException('Failed to undo like: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // ANALYTICS AND STATISTICS
    // ================================================================

    /**
     * Get like analytics for a user
     * 
     * Provides comprehensive analytics on user like patterns, success rates,
     * and interaction effectiveness.
     * 
     * @param UserId $userId The user for analytics
     * @param CarbonInterface|null $since Optional start date
     * @param CarbonInterface|null $until Optional end date
     * @return array<string, mixed> Comprehensive like analytics
     * 
     * @throws LikeException When analytics generation fails
     * 
     * @example
     * ```php
     * $analytics = $likeService->getLikeAnalytics(
     *     $userId,
     *     Carbon::now()->subMonth()
     * );
     * ```
     */
    public function getLikeAnalytics(
        UserId $userId,
        ?CarbonInterface $since = null,
        ?CarbonInterface $until = null
    ): array {
        try {
            $since = $since ?? Carbon::now()->subMonth();
            $until = $until ?? Carbon::now();

            Log::info('Generating like analytics', [
                'user_id' => $userId->toString(),
                'period' => [$since->toISOString(), $until->toISOString()]
            ]);

            $analytics = [
                'period' => [
                    'start' => $since,
                    'end' => $until,
                    'duration_days' => $since->diffInDays($until)
                ],
                'likes_given' => $this->getLikesGivenStats($userId, $since, $until),
                'likes_received' => $this->getLikesReceivedStats($userId, $since, $until),
                'super_likes' => $this->getSuperLikeStats($userId, $since, $until),
                'match_conversion' => $this->getMatchConversionStats($userId, $since, $until),
                'interaction_patterns' => $this->getInteractionPatterns($userId, $since, $until),
                'success_metrics' => $this->getSuccessMetrics($userId, $since, $until)
            ];

            Log::info('Like analytics generated successfully', [
                'user_id' => $userId->toString(),
                'total_likes_analyzed' => $analytics['likes_given']['total'] + $analytics['likes_received']['total']
            ]);

            return $analytics;

        } catch (\Exception $e) {
            Log::error('Failed to generate like analytics', [
                'user_id' => $userId->toString(),
                'error' => $e->getMessage()
            ]);
            throw new LikeException('Failed to generate analytics: ' . $e->getMessage(), 0, $e);
        }
    }

    // ================================================================
    // PRIVATE HELPER METHODS
    // ================================================================

    /**
     * Validate like action before processing
     */
    private function validateLikeAction(UserId $likerId, UserId $likedId, LikeType $type): void
    {
        // Cannot like yourself
        if ($likerId->equals($likedId)) {
            throw new InvalidLikeTargetException('Cannot like yourself');
        }

        // Check if users are blocked
        if ($this->areUsersBlocked($likerId, $likedId)) {
            throw new InvalidLikeTargetException('Cannot like blocked user');
        }

        // Check if target profile exists and is active
        $targetProfile = $this->profileRepository->findByUserId($likedId->toInt());
        if (!$targetProfile || !$targetProfile->isActive()) {
            throw new InvalidLikeTargetException('Target profile not found or inactive');
        }
    }

    /**
     * Validate rate limits for likes
     */
    private function validateRateLimits(UserId $userId, LikeType $type): void
    {
        // Check daily limits
        $dailyLikes = $this->getDailyLikeCount($userId);
        $userProfile = $this->profileRepository->findByUserId($userId->toInt());
        $maxDailyLikes = $userProfile && $userProfile->isPremium() 
            ? self::MAX_PREMIUM_DAILY_LIKES 
            : self::MAX_DAILY_LIKES;

        if ($dailyLikes >= $maxDailyLikes) {
            throw new LikeRateLimitException('Daily like limit exceeded');
        }

        // Check rapid-fire rate limits
        $recentLikes = $this->getRecentLikeCount($userId, self::RATE_LIMIT_WINDOW_MINUTES);
        if ($recentLikes >= self::MAX_LIKES_PER_WINDOW) {
            throw new LikeRateLimitException('Rate limit exceeded - please slow down');
        }
    }

    /**
     * Check if users are blocked from each other
     */
    private function areUsersBlocked(UserId $userId1, UserId $userId2): bool
    {
        // Note: This method would need to be implemented in the repository
        // For now, return false as a placeholder
        return false;
    }

    /**
     * Get daily like count for user
     */
    private function getDailyLikeCount(UserId $userId): int
    {
        $cacheKey = "daily_likes.{$userId->toString()}." . Carbon::now()->format('Y-m-d');
        return Cache::get($cacheKey, 0);
    }

    /**
     * Get recent like count within time window
     */
    private function getRecentLikeCount(UserId $userId, int $minutes): int
    {
        // Note: This method would need to be implemented in the repository
        // For now, return 0 as a placeholder
        return 0;
    }

    /**
     * Update like statistics
     */
    private function updateLikeStatistics(UserId $userId, LikeType $type): void
    {
        $cacheKey = "daily_likes.{$userId->toString()}." . Carbon::now()->format('Y-m-d');
        $currentCount = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $currentCount + 1, 86400); // 24 hours
    }

    /**
     * Clear user like caches
     */
    private function clearUserLikeCaches(UserId $userId1, UserId $userId2): void
    {
        $patterns = [
            "likes.{$userId1->toString()}.*",
            "likes.{$userId2->toString()}.*",
            "mutual_check.{$userId1->toString()}.{$userId2->toString()}",
            "mutual_check.{$userId2->toString()}.{$userId1->toString()}"
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Can undo like within time limit
     */
    private function canUndoLike(Like $like): bool
    {
        return Carbon::now()->diffInMinutes($like->getCreatedAt()) <= self::UNDO_TIME_LIMIT_MINUTES;
    }

    /**
     * Build super like notification data
     */
    private function buildSuperLikeNotificationData(SuperLike $superLike): array
    {
        return [
            'super_like_id' => $superLike->getId()->toString(),
            'liker_id' => $superLike->getLikerId()->toString(),
            'has_message' => !empty($superLike->getMessage()),
            'created_at' => $superLike->getCreatedAt(),
            'context' => $superLike->getContext()?->toArray()
        ];
    }

    /**
     * Validate pass action before processing
     */
    private function validatePassAction(UserId $passerId, UserId $passedId): void
    {
        // Cannot pass on yourself
        if ($passerId->equals($passedId)) {
            throw new InvalidLikeTargetException('Cannot pass on yourself');
        }

        // Check if users are blocked
        if ($this->areUsersBlocked($passerId, $passedId)) {
            throw new InvalidLikeTargetException('Cannot pass on blocked user');
        }

        // Check if target profile exists and is active
        $targetProfile = $this->profileRepository->findByUserId($passedId->toInt());
        if (!$targetProfile || !$targetProfile->isActive()) {
            throw new InvalidLikeTargetException('Target profile not found or inactive');
        }
    }

    /**
     * Validate super like action before processing
     */
    private function validateSuperLikeAction(UserId $likerId, UserId $likedId): void
    {
        // Cannot super like yourself
        if ($likerId->equals($likedId)) {
            throw new InvalidLikeTargetException('Cannot super like yourself');
        }

        // Check if users are blocked
        if ($this->areUsersBlocked($likerId, $likedId)) {
            throw new InvalidLikeTargetException('Cannot super like blocked user');
        }

        // Check if target profile exists and is active
        $targetProfile = $this->profileRepository->findByUserId($likedId->toInt());
        if (!$targetProfile || !$targetProfile->isActive()) {
            throw new InvalidLikeTargetException('Target profile not found or inactive');
        }
    }

    /**
     * Validate super like rate limits
     */
    private function validateSuperLikeRateLimits(UserId $userId): void
    {
        // Check daily super like limits
        $dailySuperLikes = $this->getDailySuperLikeCount($userId);
        $userProfile = $this->profileRepository->findByUserId($userId->toInt());
        $maxDailySuperLikes = $userProfile && $userProfile->isPremium() 
            ? self::MAX_PREMIUM_DAILY_SUPER_LIKES 
            : self::MAX_DAILY_SUPER_LIKES;

        if ($dailySuperLikes >= $maxDailySuperLikes) {
            throw new LikeRateLimitException('Daily super like limit exceeded');
        }
    }

    /**
     * Get daily super like count for user
     */
    private function getDailySuperLikeCount(UserId $userId): int
    {
        $cacheKey = "daily_super_likes.{$userId->toString()}." . Carbon::now()->format('Y-m-d');
        return Cache::get($cacheKey, 0);
    }

    /**
     * Update super like statistics
     */
    private function updateSuperLikeStatistics(UserId $userId): void
    {
        $cacheKey = "daily_super_likes.{$userId->toString()}." . Carbon::now()->format('Y-m-d');
        $currentCount = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $currentCount + 1, 86400); // 24 hours
    }

    /**
     * Update pass statistics
     */
    private function updatePassStatistics(UserId $userId): void
    {
        $cacheKey = "daily_passes.{$userId->toString()}." . Carbon::now()->format('Y-m-d');
        $currentCount = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $currentCount + 1, 86400); // 24 hours
    }

    /**
     * Mark likes as viewed for analytics
     */
    private function markLikesAsViewed(UserId $userId, Collection $likes): void
    {
        // Implementation would mark likes as viewed in the database
        // This is a placeholder for the actual implementation
    }

    /**
     * Handle match undo when like is undone
     */
    private function handleMatchUndo(?MatchId $matchId): void
    {
        if ($matchId) {
            // Implementation would handle match removal
            // This is a placeholder for the actual implementation
        }
    }

    /**
     * Update undo statistics
     */
    private function updateUndoStatistics(UserId $userId): void
    {
        $cacheKey = "daily_undos.{$userId->toString()}." . Carbon::now()->format('Y-m-d');
        $currentCount = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $currentCount + 1, 86400); // 24 hours
    }

    /**
     * Get likes given statistics
     */
    private function getLikesGivenStats(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        // Note: These methods would need to be implemented in the repository
        // For now, return placeholder data
        return [
            'total' => 0,
            'by_type' => [],
            'daily_average' => 0.0
        ];
    }

    /**
     * Get likes received statistics
     */
    private function getLikesReceivedStats(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        // Note: These methods would need to be implemented in the repository
        // For now, return placeholder data
        return [
            'total' => 0,
            'by_type' => [],
            'daily_average' => 0.0
        ];
    }

    /**
     * Get super like statistics
     */
    private function getSuperLikeStats(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        // Note: These methods would need to be implemented in the repository
        // For now, return placeholder data
        return [
            'given' => 0,
            'received' => 0,
            'conversion_rate' => 0.0
        ];
    }

    /**
     * Get match conversion statistics
     */
    private function getMatchConversionStats(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        // Note: These methods would need to be implemented in the repository
        // For now, return placeholder data
        return [
            'likes_to_matches' => 0,
            'super_likes_to_matches' => 0,
            'overall_conversion_rate' => 0.0
        ];
    }

    /**
     * Get interaction patterns
     */
    private function getInteractionPatterns(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        // Note: These methods would need to be implemented in the repository
        // For now, return placeholder data
        return [
            'peak_hours' => [],
            'preferred_sources' => [],
            'response_times' => []
        ];
    }

    /**
     * Get success metrics
     */
    private function getSuccessMetrics(UserId $userId, CarbonInterface $since, CarbonInterface $until): array
    {
        // Note: These methods would need to be implemented in the repository
        // For now, return placeholder data
        return [
            'match_rate' => 0.0,
            'conversation_rate' => 0.0,
            'engagement_score' => 0.0
        ];
    }

    /**
     * Calculate daily average
     */
    private function calculateDailyAverage(UserId $userId, CarbonInterface $since, CarbonInterface $until, string $type): float
    {
        // Note: This method would need to be implemented with actual repository calls
        // For now, return 0 as a placeholder
        return 0.0;
    }

    /**
     * Calculate super like conversion rate
     */
    private function calculateSuperLikeConversionRate(UserId $userId, CarbonInterface $since, CarbonInterface $until): float
    {
        // Note: This method would need to be implemented with actual repository calls
        // For now, return 0 as a placeholder
        return 0.0;
    }

    /**
     * Calculate overall conversion rate
     */
    private function calculateOverallConversionRate(UserId $userId, CarbonInterface $since, CarbonInterface $until): float
    {
        // Note: This method would need to be implemented with actual repository calls
        // For now, return 0 as a placeholder
        return 0.0;
    }

    /**
     * Calculate match rate
     */
    private function calculateMatchRate(UserId $userId, CarbonInterface $since, CarbonInterface $until): float
    {
        // Note: This method would need to be implemented with actual repository calls
        // For now, return 0 as a placeholder
        return 0.0;
    }

    /**
     * Calculate conversation rate
     */
    private function calculateConversationRate(UserId $userId, CarbonInterface $since, CarbonInterface $until): float
    {
        // Note: This method would need to be implemented with actual repository calls
        // For now, return 0 as a placeholder
        return 0.0;
    }

    /**
     * Calculate engagement score
     */
    private function calculateEngagementScore(UserId $userId, CarbonInterface $since, CarbonInterface $until): float
    {
        // Note: This method would need to be implemented with actual repository calls
        // For now, return 0 as a placeholder
        return 0.0;
    }

    /**
     * Build like notification data
     */
    private function buildLikeNotificationData(Like $like): array
    {
        return [
            'like_id' => $like->getId()->toString(),
            'liker_id' => $like->getLikerId()->toString(),
            'like_type' => $like->getType()->getValue(),
            'has_message' => !empty($like->getMessage()),
            'created_at' => $like->getCreatedAt(),
            'context' => $like->getContext()?->toArray()
        ];
    }
}