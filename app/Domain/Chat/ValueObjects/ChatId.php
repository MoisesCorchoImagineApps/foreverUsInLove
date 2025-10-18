<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

use App\Domain\Shared\ValueObjects\UuidValueObject;

/**
 * ChatId Value Object
 * 
 * Represents a unique identifier for a chat entity in the ForeverUsInLove
 * dating application. Ensures type safety and validation for chat IDs.
 * 
 * @package App\Domain\Chat\ValueObjects
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
final class ChatId extends UuidValueObject
{
    /**
     * Create a new ChatId from string
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Create a new ChatId from integer (for database IDs)
     */
    public static function fromInt(int $value): self
    {
        // Convert integer to UUID-like string for consistency
        $uuidString = sprintf('%08x-%04x-%04x-%04x-%012x', 
            $value, 
            ($value >> 16) & 0xffff, 
            ($value >> 32) & 0xffff, 
            ($value >> 48) & 0xffff, 
            $value & 0xffffffffffff
        );
        
        return new self($uuidString);
    }

    /**
     * Generate a new random ChatId
     */
    public static function generate(): self
    {
        return new self(parent::generate()->value());
    }
}
