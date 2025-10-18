<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

use App\Domain\Shared\ValueObjects\UuidValueObject;

/**
 * MessageId Value Object
 * 
 * Represents a unique identifier for a message entity in the ForeverUsInLove
 * dating application. Ensures type safety and validation for message IDs.
 * 
 * @package App\Domain\Chat\ValueObjects
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
final class MessageId extends UuidValueObject
{
    /**
     * Create a new MessageId from string
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Generate a new random MessageId
     */
    public static function generate(): self
    {
        return new self(parent::generate()->value());
    }
}
