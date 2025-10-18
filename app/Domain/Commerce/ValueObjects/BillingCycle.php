<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

/**
 * BillingCycle ValueObject
 * 
 * Represents the billing cycle for subscriptions in the ForeverUsInLove dating application.
 * Defines the different billing intervals available for subscription plans.
 * 
 * @package App\Domain\Commerce\ValueObjects
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 */
final readonly class BillingCycle
{
    public const WEEKLY = 'weekly';
    public const MONTHLY = 'monthly';
    public const QUARTERLY = 'quarterly';
    public const SEMI_ANNUAL = 'semi_annual';
    public const ANNUAL = 'annual';

    private const VALID_CYCLES = [
        self::WEEKLY,
        self::MONTHLY,
        self::QUARTERLY,
        self::SEMI_ANNUAL,
        self::ANNUAL
    ];

    private const DAYS_MAP = [
        self::WEEKLY => 7,
        self::MONTHLY => 30,
        self::QUARTERLY => 90,
        self::SEMI_ANNUAL => 180,
        self::ANNUAL => 365
    ];

    public function __construct(
        private string $value
    ) {
        if (!in_array($this->value, self::VALID_CYCLES, true)) {
            throw new \InvalidArgumentException(
                "Invalid billing cycle: {$this->value}. Valid cycles are: " . implode(', ', self::VALID_CYCLES)
            );
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isWeekly(): bool
    {
        return $this->value === self::WEEKLY;
    }

    public function isMonthly(): bool
    {
        return $this->value === self::MONTHLY;
    }

    public function isQuarterly(): bool
    {
        return $this->value === self::QUARTERLY;
    }

    public function isSemiAnnual(): bool
    {
        return $this->value === self::SEMI_ANNUAL;
    }

    public function isAnnual(): bool
    {
        return $this->value === self::ANNUAL;
    }

    public function getDays(): int
    {
        return self::DAYS_MAP[$this->value];
    }

    public function getMonths(): float
    {
        return match ($this->value) {
            self::WEEKLY => 0.25,
            self::MONTHLY => 1.0,
            self::QUARTERLY => 3.0,
            self::SEMI_ANNUAL => 6.0,
            self::ANNUAL => 12.0,
            default => 0.0
        };
    }

    public function getDisplayName(): string
    {
        return match ($this->value) {
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::SEMI_ANNUAL => 'Semi-Annual',
            self::ANNUAL => 'Annual',
            default => ucfirst($this->value)
        };
    }

    public function getShortDisplayName(): string
    {
        return match ($this->value) {
            self::WEEKLY => 'Week',
            self::MONTHLY => 'Month',
            self::QUARTERLY => 'Quarter',
            self::SEMI_ANNUAL => '6 Months',
            self::ANNUAL => 'Year',
            default => ucfirst($this->value)
        };
    }

    public function getDiscountMultiplier(): float
    {
        return match ($this->value) {
            self::WEEKLY => 1.0,
            self::MONTHLY => 1.0,
            self::QUARTERLY => 0.95, // 5% discount
            self::SEMI_ANNUAL => 0.90, // 10% discount
            self::ANNUAL => 0.85, // 15% discount
            default => 1.0
        };
    }

    public function isLongTerm(): bool
    {
        return in_array($this->value, [self::QUARTERLY, self::SEMI_ANNUAL, self::ANNUAL], true);
    }

    public function isShortTerm(): bool
    {
        return in_array($this->value, [self::WEEKLY, self::MONTHLY], true);
    }

    public function getFrequency(): int
    {
        return match ($this->value) {
            self::WEEKLY => 52, // times per year
            self::MONTHLY => 12,
            self::QUARTERLY => 4,
            self::SEMI_ANNUAL => 2,
            self::ANNUAL => 1,
            default => 1
        };
    }

    public function equals(BillingCycle $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function weekly(): self
    {
        return new self(self::WEEKLY);
    }

    public static function monthly(): self
    {
        return new self(self::MONTHLY);
    }

    public static function quarterly(): self
    {
        return new self(self::QUARTERLY);
    }

    public static function semiAnnual(): self
    {
        return new self(self::SEMI_ANNUAL);
    }

    public static function annual(): self
    {
        return new self(self::ANNUAL);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function getAllCycles(): array
    {
        return self::VALID_CYCLES;
    }

    public static function getLongTermCycles(): array
    {
        return [self::QUARTERLY, self::SEMI_ANNUAL, self::ANNUAL];
    }

    public static function getShortTermCycles(): array
    {
        return [self::WEEKLY, self::MONTHLY];
    }
}
