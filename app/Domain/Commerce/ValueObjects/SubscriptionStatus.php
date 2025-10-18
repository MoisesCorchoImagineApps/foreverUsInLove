<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

/**
 * SubscriptionStatus ValueObject
 * 
 * Represents the status of a subscription in the ForeverUsInLove dating application.
 * Defines the different states a subscription can be in throughout its lifecycle.
 * 
 * @package App\Domain\Commerce\ValueObjects
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 */
final readonly class SubscriptionStatus
{
    public const PENDING = 'pending';
    public const ACTIVE = 'active';
    public const TRIAL = 'trial';
    public const PAUSED = 'paused';
    public const SUSPENDED = 'suspended';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
    public const FAILED = 'failed';

    private const VALID_STATUSES = [
        self::PENDING,
        self::ACTIVE,
        self::TRIAL,
        self::PAUSED,
        self::SUSPENDED,
        self::CANCELLED,
        self::EXPIRED,
        self::FAILED
    ];

    public function __construct(
        private string $value
    ) {
        if (!in_array($this->value, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(
                "Invalid subscription status: {$this->value}. Valid statuses are: " . implode(', ', self::VALID_STATUSES)
            );
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isTrial(): bool
    {
        return $this->value === self::TRIAL;
    }

    public function isPaused(): bool
    {
        return $this->value === self::PAUSED;
    }

    public function isSuspended(): bool
    {
        return $this->value === self::SUSPENDED;
    }

    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->value === self::EXPIRED;
    }

    public function isFailed(): bool
    {
        return $this->value === self::FAILED;
    }

    public function isActiveOrTrial(): bool
    {
        return in_array($this->value, [self::ACTIVE, self::TRIAL], true);
    }

    public function isTerminated(): bool
    {
        return in_array($this->value, [self::CANCELLED, self::EXPIRED, self::FAILED], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->value, [self::ACTIVE, self::TRIAL, self::PAUSED], true);
    }

    public function canBePaused(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function canBeResumed(): bool
    {
        return $this->value === self::PAUSED;
    }

    public function getDisplayName(): string
    {
        return match ($this->value) {
            self::PENDING => 'Pending',
            self::ACTIVE => 'Active',
            self::TRIAL => 'Trial',
            self::PAUSED => 'Paused',
            self::SUSPENDED => 'Suspended',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
            self::FAILED => 'Failed',
            default => ucfirst($this->value)
        };
    }

    public function getPriority(): int
    {
        return match ($this->value) {
            self::ACTIVE => 1,
            self::TRIAL => 2,
            self::PENDING => 3,
            self::PAUSED => 4,
            self::SUSPENDED => 5,
            self::CANCELLED => 6,
            self::EXPIRED => 7,
            self::FAILED => 8,
            default => 9
        };
    }

    public function equals(SubscriptionStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function trial(): self
    {
        return new self(self::TRIAL);
    }

    public static function paused(): self
    {
        return new self(self::PAUSED);
    }

    public static function suspended(): self
    {
        return new self(self::SUSPENDED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function getAllStatuses(): array
    {
        return self::VALID_STATUSES;
    }
}
