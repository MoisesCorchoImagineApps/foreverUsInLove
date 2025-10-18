<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

/**
 * ChatStatus Value Object
 * 
 * Represents the status of a chat in the ForeverUsInLove dating application.
 * Defines all possible chat states with validation and type safety.
 * 
 * @package App\Domain\Chat\ValueObjects
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
final class ChatStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const ARCHIVED = 'archived';
    public const SUSPENDED = 'suspended';
    public const DELETED = 'deleted';

    private const VALID_STATUSES = [
        self::ACTIVE,
        self::INACTIVE,
        self::ARCHIVED,
        self::SUSPENDED,
        self::DELETED,
    ];

    private function __construct(
        private readonly string $value
    ) {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid chat status: {$value}");
        }
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function inactive(): self
    {
        return new self(self::INACTIVE);
    }

    public static function archived(): self
    {
        return new self(self::ARCHIVED);
    }

    public static function suspended(): self
    {
        return new self(self::SUSPENDED);
    }

    public static function deleted(): self
    {
        return new self(self::DELETED);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->value === self::INACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->value === self::ARCHIVED;
    }

    public function isSuspended(): bool
    {
        return $this->value === self::SUSPENDED;
    }

    public function isDeleted(): bool
    {
        return $this->value === self::DELETED;
    }

    public function equals(ChatStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
