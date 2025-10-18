<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

/**
 * ChatType Value Object
 * 
 * Represents the type of chat in the ForeverUsInLove dating application.
 * Defines all supported chat types with validation and type safety.
 * 
 * @package App\Domain\Chat\ValueObjects
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
final class ChatType
{
    public const PRIVATE = 'private';
    public const GROUP = 'group';
    public const VIDEO = 'video';
    public const HAPPY_HOUR = 'happy_hour';

    private const VALID_TYPES = [
        self::PRIVATE,
        self::GROUP,
        self::VIDEO,
        self::HAPPY_HOUR,
    ];

    private function __construct(
        private readonly string $value
    ) {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid chat type: {$value}");
        }
    }

    public static function private(): self
    {
        return new self(self::PRIVATE);
    }

    public static function group(): self
    {
        return new self(self::GROUP);
    }

    public static function video(): self
    {
        return new self(self::VIDEO);
    }

    public static function happyHour(): self
    {
        return new self(self::HAPPY_HOUR);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isPrivate(): bool
    {
        return $this->value === self::PRIVATE;
    }

    public function isGroup(): bool
    {
        return $this->value === self::GROUP;
    }

    public function isVideo(): bool
    {
        return $this->value === self::VIDEO;
    }

    public function isHappyHour(): bool
    {
        return $this->value === self::HAPPY_HOUR;
    }

    public function equals(ChatType $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
