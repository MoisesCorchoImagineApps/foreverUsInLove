<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

/**
 * MessageStatus Value Object
 * 
 * Represents the status of a message in the ForeverUsInLove dating application.
 * Defines all possible message states with validation and type safety.
 * 
 * @package App\Domain\Chat\ValueObjects
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
final class MessageStatus
{
    public const SENT = 'sent';
    public const DELIVERED = 'delivered';
    public const READ = 'read';
    public const FAILED = 'failed';
    public const DELETED = 'deleted';
    public const PENDING = 'pending';
    public const SCHEDULED = 'scheduled';
    public const EXPIRED = 'expired';

    private const VALID_STATUSES = [
        self::SENT,
        self::DELIVERED,
        self::READ,
        self::FAILED,
        self::DELETED,
        self::PENDING,
        self::SCHEDULED,
        self::EXPIRED,
    ];

    private const ACTIVE_STATUSES = [
        self::SENT,
        self::DELIVERED,
        self::READ,
        self::PENDING,
        self::SCHEDULED,
    ];

    private const FINAL_STATUSES = [
        self::FAILED,
        self::DELETED,
        self::EXPIRED,
    ];

    private function __construct(
        private readonly string $value
    ) {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid message status: {$value}");
        }
    }

    public static function sent(): self
    {
        return new self(self::SENT);
    }

    public static function delivered(): self
    {
        return new self(self::DELIVERED);
    }

    public static function read(): self
    {
        return new self(self::READ);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function deleted(): self
    {
        return new self(self::DELETED);
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function scheduled(): self
    {
        return new self(self::SCHEDULED);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isSent(): bool
    {
        return $this->value === self::SENT;
    }

    public function isDelivered(): bool
    {
        return $this->value === self::DELIVERED;
    }

    public function isRead(): bool
    {
        return $this->value === self::READ;
    }

    public function isFailed(): bool
    {
        return $this->value === self::FAILED;
    }

    public function isDeleted(): bool
    {
        return $this->value === self::DELETED;
    }

    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public function isScheduled(): bool
    {
        return $this->value === self::SCHEDULED;
    }

    public function isExpired(): bool
    {
        return $this->value === self::EXPIRED;
    }

    public function isActive(): bool
    {
        return in_array($this->value, self::ACTIVE_STATUSES, true);
    }

    public function isFinal(): bool
    {
        return in_array($this->value, self::FINAL_STATUSES, true);
    }

    public function canTransitionTo(MessageStatus $newStatus): bool
    {
        return match ($this->value) {
            self::PENDING => in_array($newStatus->value, [self::SENT, self::FAILED], true),
            self::SENT => in_array($newStatus->value, [self::DELIVERED, self::FAILED, self::DELETED], true),
            self::DELIVERED => in_array($newStatus->value, [self::READ, self::DELETED], true),
            self::READ => $newStatus->value === self::DELETED,
            self::SCHEDULED => in_array($newStatus->value, [self::SENT, self::FAILED], true),
            self::FAILED, self::DELETED, self::EXPIRED => false,
            default => false,
        };
    }

    public function equals(MessageStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
