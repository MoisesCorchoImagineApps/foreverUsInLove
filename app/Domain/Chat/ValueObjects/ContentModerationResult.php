<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

/**
 * ContentModerationResult Value Object
 * 
 * Represents the result of content moderation for messages in the ForeverUsInLove
 * dating application. Encapsulates moderation decisions and metadata.
 * 
 * @package App\Domain\Chat\ValueObjects
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
final class ContentModerationResult
{
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const FLAGGED = 'flagged';
    public const PENDING = 'pending';
    public const QUARANTINED = 'quarantined';

    private const VALID_RESULTS = [
        self::APPROVED,
        self::REJECTED,
        self::FLAGGED,
        self::PENDING,
        self::QUARANTINED,
    ];

    private function __construct(
        private readonly string $result,
        private readonly float $confidence,
        private readonly array $flags,
        private readonly ?string $reason,
        private readonly array $metadata
    ) {
        if (!in_array($result, self::VALID_RESULTS, true)) {
            throw new \InvalidArgumentException("Invalid moderation result: {$result}");
        }

        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new \InvalidArgumentException("Confidence must be between 0.0 and 1.0");
        }
    }

    public static function approved(float $confidence = 1.0, array $metadata = []): self
    {
        return new self(
            result: self::APPROVED,
            confidence: $confidence,
            flags: [],
            reason: null,
            metadata: $metadata
        );
    }

    public static function rejected(string $reason, float $confidence = 1.0, array $flags = [], array $metadata = []): self
    {
        return new self(
            result: self::REJECTED,
            confidence: $confidence,
            flags: $flags,
            reason: $reason,
            metadata: $metadata
        );
    }

    public static function flagged(array $flags, float $confidence, ?string $reason = null, array $metadata = []): self
    {
        return new self(
            result: self::FLAGGED,
            confidence: $confidence,
            flags: $flags,
            reason: $reason,
            metadata: $metadata
        );
    }

    public static function pending(array $metadata = []): self
    {
        return new self(
            result: self::PENDING,
            confidence: 0.0,
            flags: [],
            reason: null,
            metadata: $metadata
        );
    }

    public static function quarantined(string $reason, array $flags = [], array $metadata = []): self
    {
        return new self(
            result: self::QUARANTINED,
            confidence: 1.0,
            flags: $flags,
            reason: $reason,
            metadata: $metadata
        );
    }

    public function result(): string
    {
        return $this->result;
    }

    public function confidence(): float
    {
        return $this->confidence;
    }

    public function flags(): array
    {
        return $this->flags;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function isApproved(): bool
    {
        return $this->result === self::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->result === self::REJECTED;
    }

    public function isFlagged(): bool
    {
        return $this->result === self::FLAGGED;
    }

    public function isPending(): bool
    {
        return $this->result === self::PENDING;
    }

    public function isQuarantined(): bool
    {
        return $this->result === self::QUARANTINED;
    }

    public function isSafe(): bool
    {
        return $this->result === self::APPROVED;
    }

    public function requiresReview(): bool
    {
        return in_array($this->result, [self::FLAGGED, self::PENDING], true);
    }

    public function hasFlags(): bool
    {
        return !empty($this->flags);
    }

    public function hasHighConfidence(): bool
    {
        return $this->confidence >= 0.8;
    }

    public function hasMediumConfidence(): bool
    {
        return $this->confidence >= 0.5 && $this->confidence < 0.8;
    }

    public function hasLowConfidence(): bool
    {
        return $this->confidence < 0.5;
    }

    public function toArray(): array
    {
        return [
            'result' => $this->result,
            'confidence' => $this->confidence,
            'flags' => $this->flags,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
        ];
    }

    public function equals(ContentModerationResult $other): bool
    {
        return $this->result === $other->result &&
               $this->confidence === $other->confidence &&
               $this->flags === $other->flags &&
               $this->reason === $other->reason;
    }

    public function __toString(): string
    {
        return $this->result;
    }
}
