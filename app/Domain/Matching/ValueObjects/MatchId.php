<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use App\Domain\Shared\ValueObjects\UuidValueObject;

/**
 * MatchId Value Object
 * 
 * Represents a unique identifier for a match in the ForeverUsInLove dating platform.
 * Extends the base UuidValueObject to provide match-specific functionality.
 * 
 * This Value Object ensures type safety and immutability for match identifiers
 * throughout the matching domain, preventing ID confusion and ensuring
 * proper match tracking and management.
 * 
 * @package App\Domain\Matching\ValueObjects
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 * @since 2024-01-01
 */
final class MatchId extends UuidValueObject
{
    /**
     * Create a new MatchId from string
     * 
     * @param string $value UUID string value
     * @return self New MatchId instance
     * @throws \InvalidArgumentException If UUID is invalid
     */
    public static function fromString(string $value): self
    {
        return parent::fromString($value);
    }

    /**
     * Generate a new unique MatchId
     * 
     * @return self New MatchId instance
     */
    public static function generate(): self
    {
        return parent::generate();
    }

    /**
     * Get the string representation of the MatchId
     * 
     * @return string UUID string
     */
    public function toString(): string
    {
        return $this->value();
    }

    /**
     * Get the MatchId for database storage
     * 
     * @return string UUID string for database
     */
    public function toDatabaseValue(): string
    {
        return $this->value();
    }

    /**
     * Check if this MatchId is valid for matching operations
     * 
     * @return bool True if valid for matching
     */
    public function isValidForMatching(): bool
    {
        // All generated UUIDs are valid for matching
        return true;
    }

    /**
     * Get a short representation for logging/debugging
     * 
     * @return string Short UUID representation
     */
    public function toShortString(): string
    {
        return substr($this->value(), 0, 8) . '...';
    }

    /**
     * Convert to array representation
     * 
     * @return array Array representation
     */
    public function toArray(): array
    {
        return [
            'match_id' => $this->value(),
            'short_id' => $this->toShortString(),
            'is_valid' => $this->isValidForMatching()
        ];
    }
}
