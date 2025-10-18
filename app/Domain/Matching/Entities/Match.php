<?php

declare(strict_types=1);

namespace App\Domain\Matching\Entities;

use App\Domain\Matching\ValueObjects\MatchId;
use App\Domain\Matching\ValueObjects\MatchScore;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\CarbonInterface;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Match Entity
 * 
 * Represents a match between two users in the ForeverUsInLove dating platform.
 * Encapsulates the core matching relationship with compatibility scoring,
 * match status, and contextual information about how the match was created.
 * 
 * This Entity follows Domain-Driven Design principles and represents the
 * core business concept of a match between two users, including both
 * potential matches and active mutual matches.
 * 
 * @package App\Domain\Matching\Entities
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 * @since 2024-01-01
 */
final class MatchEntity
{
    private const MAX_MATCH_CONTEXT_SIZE = 1000;
    private const VALID_MATCH_SOURCES = [
        'mutual_like',
        'standard_discovery',
        'premium_discovery',
        'super_like',
        'boost',
        'manual',
        'admin'
    ];

    /**
     * @param MatchId $matchId Unique match identifier
     * @param UserId $userId1 First user in the match
     * @param UserId $userId2 Second user in the match
     * @param MatchScore $compatibilityScore Compatibility score between users
     * @param CarbonInterface $matchedAt When the match was created
     * @param string $matchSource How the match was created
     * @param bool $isActive Whether the match is currently active
     * @param array<string, mixed> $matchContext Additional context about the match
     */
    private function __construct(
        private readonly MatchId $matchId,
        private readonly UserId $userId1,
        private readonly UserId $userId2,
        private readonly MatchScore $compatibilityScore,
        private readonly CarbonInterface $matchedAt,
        private readonly string $matchSource,
        private readonly bool $isActive,
        private readonly array $matchContext
    ) {
        $this->validateMatchSource($matchSource);
        $this->validateMatchContext($matchContext);
        $this->validateUsers($userId1, $userId2);
    }

    /**
     * Create a new Match
     * 
     * @param MatchId $matchId Unique match identifier
     * @param UserId $userId1 First user in the match
     * @param UserId $userId2 Second user in the match
     * @param MatchScore $compatibilityScore Compatibility score
     * @param CarbonInterface $matchedAt Match creation time
     * @param string $matchSource How the match was created
     * @param bool $isActive Whether the match is active
     * @param array<string, mixed> $matchContext Additional context
     * @return self New Match instance
     */
    public static function create(
        MatchId $matchId,
        UserId $userId1,
        UserId $userId2,
        MatchScore $compatibilityScore,
        CarbonInterface $matchedAt,
        string $matchSource,
        bool $isActive,
        array $matchContext = []
    ): self {
        return new self(
            $matchId,
            $userId1,
            $userId2,
            $compatibilityScore,
            $matchedAt,
            $matchSource,
            $isActive,
            $matchContext
        );
    }

    /**
     * Create Match from array data
     * 
     * @param array $data Match data
     * @return self New Match instance
     */
    public static function fromArray(array $data): self
    {
        return new self(
            MatchId::fromString($data['match_id']),
            UserId::fromInt($data['user_id1']),
            UserId::fromInt($data['user_id2']),
            MatchScore::fromArray($data['compatibility_score']),
            Carbon::parse($data['matched_at']),
            $data['match_source'],
            $data['is_active'],
            $data['match_context'] ?? []
        );
    }

    /**
     * Get the match ID
     * 
     * @return MatchId Match identifier
     */
    public function getId(): MatchId
    {
        return $this->matchId;
    }

    /**
     * Get the first user ID
     * 
     * @return UserId First user identifier
     */
    public function getUserId1(): UserId
    {
        return $this->userId1;
    }

    /**
     * Get the second user ID
     * 
     * @return UserId Second user identifier
     */
    public function getUserId2(): UserId
    {
        return $this->userId2;
    }

    /**
     * Get the compatibility score
     * 
     * @return MatchScore Compatibility score
     */
    public function getCompatibilityScore(): MatchScore
    {
        return $this->compatibilityScore;
    }

    /**
     * Get the match creation time
     * 
     * @return CarbonInterface Match creation timestamp
     */
    public function getMatchedAt(): CarbonInterface
    {
        return $this->matchedAt;
    }

    /**
     * Get the match source
     * 
     * @return string How the match was created
     */
    public function getMatchSource(): string
    {
        return $this->matchSource;
    }

    /**
     * Check if the match is active
     * 
     * @return bool True if match is active
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Get the match context
     * 
     * @return array<string, mixed> Additional context
     */
    public function getMatchContext(): array
    {
        return $this->matchContext;
    }

    /**
     * Get the other user ID given one user ID
     * 
     * @param UserId $userId One of the user IDs
     * @return UserId The other user ID
     * @throws InvalidArgumentException If userId is not part of this match
     */
    public function getOtherUser(UserId $userId): UserId
    {
        if ($this->userId1->equals($userId)) {
            return $this->userId2;
        }

        if ($this->userId2->equals($userId)) {
            return $this->userId1;
        }

        throw new InvalidArgumentException('User is not part of this match');
    }

    /**
     * Check if a user is part of this match
     * 
     * @param UserId $userId User ID to check
     * @return bool True if user is part of this match
     */
    public function containsUser(UserId $userId): bool
    {
        return $this->userId1->equals($userId) || $this->userId2->equals($userId);
    }

    /**
     * Check if this is a mutual match (both users liked each other)
     * 
     * @return bool True if this is a mutual match
     */
    public function isMutualMatch(): bool
    {
        return $this->isActive && $this->matchSource === 'mutual_like';
    }

    /**
     * Check if this is a potential match (not yet mutual)
     * 
     * @return bool True if this is a potential match
     */
    public function isPotentialMatch(): bool
    {
        return !$this->isActive;
    }

    /**
     * Check if this is a premium match
     * 
     * @return bool True if created through premium features
     */
    public function isPremiumMatch(): bool
    {
        return in_array($this->matchSource, ['premium_discovery', 'boost', 'super_like']);
    }

    /**
     * Check if this is a high-quality match
     * 
     * @return bool True if compatibility score is high
     */
    public function isHighQuality(): bool
    {
        return $this->compatibilityScore->isHighQuality();
    }

    /**
     * Get the match age in days
     * 
     * @return int Age in days
     */
    public function getAgeInDays(): int
    {
        $now = Carbon::now();
        $diff = $now->diff($this->matchedAt);
        return $diff->days;
    }

    /**
     * Check if the match is recent (within specified days)
     * 
     * @param int $days Number of days to check
     * @return bool True if match is recent
     */
    public function isRecent(int $days = 7): bool
    {
        return $this->getAgeInDays() <= $days;
    }

    /**
     * Check if the match is stale (older than specified days)
     * 
     * @param int $days Number of days to check
     * @return bool True if match is stale
     */
    public function isStale(int $days = 30): bool
    {
        return $this->getAgeInDays() > $days;
    }

    /**
     * Get match quality category
     * 
     * @return string Quality category (high, medium, low)
     */
    public function getQualityCategory(): string
    {
        return $this->compatibilityScore->getQualityCategory();
    }

    /**
     * Get match insights
     * 
     * @return array Match insights
     */
    public function getInsights(): array
    {
        return [
            'match_id' => $this->matchId->toString(),
            'user_ids' => [
                $this->userId1->toString(),
                $this->userId2->toString()
            ],
            'compatibility_score' => $this->compatibilityScore->getValue(),
            'quality_category' => $this->getQualityCategory(),
            'match_source' => $this->matchSource,
            'is_active' => $this->isActive,
            'is_mutual' => $this->isMutualMatch(),
            'is_premium' => $this->isPremiumMatch(),
            'is_high_quality' => $this->isHighQuality(),
            'matched_at' => $this->matchedAt->toISOString(),
            'age_days' => $this->getAgeInDays(),
            'is_recent' => $this->isRecent(),
            'is_stale' => $this->isStale(),
            'confidence_level' => $this->compatibilityScore->getConfidenceLevel(),
            'algorithm_version' => $this->compatibilityScore->getAlgorithmVersion()
        ];
    }

    /**
     * Create a deactivated version of this match
     * 
     * @param string|null $reason Reason for deactivation
     * @return self New deactivated match
     */
    public function deactivate(?string $reason = null): self
    {
        $context = $this->matchContext;
        $context['deactivated_at'] = Carbon::now()->toISOString();
        $context['deactivation_reason'] = $reason;

        return new self(
            $this->matchId,
            $this->userId1,
            $this->userId2,
            $this->compatibilityScore,
            $this->matchedAt,
            $this->matchSource,
            false, // Deactivated
            $context
        );
    }

    /**
     * Create an activated version of this match
     * 
     * @param string $reason Reason for activation
     * @return self New activated match
     */
    public function activate(string $reason = 'mutual_like'): self
    {
        $context = $this->matchContext;
        $context['activated_at'] = Carbon::now()->toISOString();
        $context['activation_reason'] = $reason;

        return new self(
            $this->matchId,
            $this->userId1,
            $this->userId2,
            $this->compatibilityScore,
            $this->matchedAt,
            $reason, // Update source to activation reason
            true, // Activated
            $context
        );
    }

    /**
     * Update match context
     * 
     * @param array<string, mixed> $newContext New context data
     * @return self New match with updated context
     */
    public function updateContext(array $newContext): self
    {
        $mergedContext = array_merge($this->matchContext, $newContext);
        
        return new self(
            $this->matchId,
            $this->userId1,
            $this->userId2,
            $this->compatibilityScore,
            $this->matchedAt,
            $this->matchSource,
            $this->isActive,
            $mergedContext
        );
    }

    /**
     * Check if this match equals another
     * 
     * @param MatchEntity $other Other match to compare
     * @return bool True if matches are equal
     */
    public function equals(MatchEntity $other): bool
    {
        return $this->matchId->equals($other->matchId);
    }

    /**
     * Check if this match is better than another
     * 
     * @param MatchEntity $other Other match to compare
     * @return bool True if this match is better
     */
    public function isBetterThan(MatchEntity $other): bool
    {
        return $this->compatibilityScore->isBetterThan($other->compatibilityScore);
    }

    /**
     * Convert to array representation
     * 
     * @return array Array representation
     */
    public function toArray(): array
    {
        return [
            'match_id' => $this->matchId->toString(),
            'user_id1' => $this->userId1->toInt(),
            'user_id2' => $this->userId2->toInt(),
            'compatibility_score' => $this->compatibilityScore->toArray(),
            'matched_at' => $this->matchedAt->toISOString(),
            'match_source' => $this->matchSource,
            'is_active' => $this->isActive,
            'match_context' => $this->matchContext
        ];
    }

    /**
     * Convert to JSON representation
     * 
     * @return string JSON string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * String representation
     * 
     * @return string String representation
     */
    public function __toString(): string
    {
        return sprintf(
            'Match(%s: %s-%s, score: %.3f, %s)',
            $this->matchId->toShortString(),
            $this->userId1->toString(),
            $this->userId2->toString(),
            $this->compatibilityScore->getValue(),
            $this->isActive ? 'active' : 'inactive'
        );
    }

    /**
     * Validate match source
     * 
     * @param string $source Source to validate
     * @throws InvalidArgumentException If source is invalid
     */
    private function validateMatchSource(string $source): void
    {
        if (!in_array($source, self::VALID_MATCH_SOURCES)) {
            throw new InvalidArgumentException(
                sprintf('Invalid match source: %s. Valid sources: %s', $source, implode(', ', self::VALID_MATCH_SOURCES))
            );
        }
    }

    /**
     * Validate match context
     * 
     * @param array $context Context to validate
     * @throws InvalidArgumentException If context is invalid
     */
    private function validateMatchContext(array $context): void
    {
        $contextSize = strlen(json_encode($context));
        if ($contextSize > self::MAX_MATCH_CONTEXT_SIZE) {
            throw new InvalidArgumentException(
                sprintf('Match context exceeds maximum size of %d bytes', self::MAX_MATCH_CONTEXT_SIZE)
            );
        }
    }

    /**
     * Validate that users are different
     * 
     * @param UserId $userId1 First user ID
     * @param UserId $userId2 Second user ID
     * @throws InvalidArgumentException If users are the same
     */
    private function validateUsers(UserId $userId1, UserId $userId2): void
    {
        if ($userId1->equals($userId2)) {
            throw new InvalidArgumentException('A user cannot match with themselves');
        }
    }
}

