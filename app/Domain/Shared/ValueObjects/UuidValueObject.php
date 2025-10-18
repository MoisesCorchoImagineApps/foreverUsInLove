<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * UuidValueObject Base Class
 * 
 * Base class for UUID-based value objects in the ForeverUsInLove application.
 * Provides common UUID functionality with validation and type safety.
 * 
 * @package App\Domain\Shared\ValueObjects
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
abstract class UuidValueObject
{
    private function __construct(
        private readonly UuidInterface $value
    ) {}

    public static function generate(): static
    {
        return new static(Uuid::uuid4());
    }

    public static function fromString(string $value): static
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException("Invalid UUID: {$value}");
        }

        return new static(Uuid::fromString($value));
    }

    public function value(): string
    {
        return $this->value->toString();
    }

    public function equals(UuidValueObject $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function __toString(): string
    {
        return $this->value->toString();
    }
}
