<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

/**
 * PlanType ValueObject
 * 
 * Represents the type of subscription plan in the ForeverUsInLove dating application.
 * Defines the different tiers of premium plans available to users.
 * 
 * @package App\Domain\Commerce\ValueObjects
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 */
final readonly class PlanType
{
    public const BASIC = 'basic';
    public const PREMIUM = 'premium';
    public const VIP = 'vip';
    public const ELITE = 'elite';

    private const VALID_TYPES = [
        self::BASIC,
        self::PREMIUM,
        self::VIP,
        self::ELITE
    ];

    public function __construct(
        private string $value
    ) {
        if (!in_array($this->value, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid plan type: {$this->value}. Valid types are: " . implode(', ', self::VALID_TYPES)
            );
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isBasic(): bool
    {
        return $this->value === self::BASIC;
    }

    public function isPremium(): bool
    {
        return $this->value === self::PREMIUM;
    }

    public function isVip(): bool
    {
        return $this->value === self::VIP;
    }

    public function isElite(): bool
    {
        return $this->value === self::ELITE;
    }

    public function isPremiumOrHigher(): bool
    {
        return in_array($this->value, [self::PREMIUM, self::VIP, self::ELITE], true);
    }

    public function isVipOrHigher(): bool
    {
        return in_array($this->value, [self::VIP, self::ELITE], true);
    }

    public function getDisplayName(): string
    {
        return match ($this->value) {
            self::BASIC => 'Basic',
            self::PREMIUM => 'Premium',
            self::VIP => 'VIP',
            self::ELITE => 'Elite',
            default => ucfirst($this->value)
        };
    }

    public function getTierLevel(): int
    {
        return match ($this->value) {
            self::BASIC => 1,
            self::PREMIUM => 2,
            self::VIP => 3,
            self::ELITE => 4,
            default => 0
        };
    }

    public function equals(PlanType $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function basic(): self
    {
        return new self(self::BASIC);
    }

    public static function premium(): self
    {
        return new self(self::PREMIUM);
    }

    public static function vip(): self
    {
        return new self(self::VIP);
    }

    public static function elite(): self
    {
        return new self(self::ELITE);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function getAllTypes(): array
    {
        return self::VALID_TYPES;
    }
}
