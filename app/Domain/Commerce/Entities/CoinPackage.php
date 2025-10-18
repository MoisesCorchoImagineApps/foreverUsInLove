<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Entities;

use App\Domain\Commerce\ValueObjects\CoinPackageId;
use App\Domain\Commerce\ValueObjects\Price;
use App\Domain\Commerce\ValueObjects\Currency;
use Carbon\Carbon;

/**
 * CoinPackage Entity
 * 
 * Core domain entity representing a virtual coin package in the ForeverUsInLove dating application.
 * Encapsulates coin package business logic, pricing, bonuses, and package management.
 * 
 * @package App\Domain\Commerce\Entities
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class CoinPackage
{
    // Package Types
    public const TYPE_SMALL = 'small';
    public const TYPE_MEDIUM = 'medium';
    public const TYPE_LARGE = 'large';
    public const TYPE_PREMIUM = 'premium';
    public const TYPE_PROMOTIONAL = 'promotional';
    public const TYPE_SEASONAL = 'seasonal';
    public const TYPE_BETA = 'beta';

    // Package Tiers
    public const TIER_BASIC = 'basic';
    public const TIER_STANDARD = 'standard';
    public const TIER_PREMIUM = 'premium';
    public const TIER_VIP = 'vip';
    public const TIER_ELITE = 'elite';

    // Bonus Types
    public const BONUS_NONE = 'none';
    public const BONUS_PERCENTAGE = 'percentage';
    public const BONUS_FIXED = 'fixed';
    public const BONUS_DOUBLE = 'double';

    private function __construct(
        private readonly CoinPackageId $id,
        private string $name,
        private string $description,
        private int $coinAmount,
        private int $bonusCoins,
        private string $bonusType,
        private Price $price,
        private Currency $currency,
        private string $type,
        private string $tier,
        private bool $isActive,
        private bool $isFeatured,
        private bool $isLimited,
        private bool $isPopular,
        private int $sortOrder,
        private int $purchaseCount,
        private float $totalRevenue,
        private ?Carbon $availableFrom,
        private ?Carbon $availableUntil,
        private ?Carbon $promotionEndsAt,
        private array $tags,
        private array $metadata,
        private Carbon $createdAt,
        private Carbon $updatedAt
    ) {}

    public static function create(
        CoinPackageId $id,
        string $name,
        string $description,
        int $coinAmount,
        Price $price,
        Currency $currency,
        string $type = self::TYPE_MEDIUM,
        string $tier = self::TIER_STANDARD,
        int $bonusCoins = 0,
        string $bonusType = self::BONUS_NONE,
        bool $isActive = true,
        bool $isFeatured = false,
        bool $isLimited = false,
        array $tags = [],
        array $metadata = []
    ): self {
        $now = now();
        
        return new self(
            id: $id,
            name: $name,
            description: $description,
            coinAmount: $coinAmount,
            bonusCoins: $bonusCoins,
            bonusType: $bonusType,
            price: $price,
            currency: $currency,
            type: $type,
            tier: $tier,
            isActive: $isActive,
            isFeatured: $isFeatured,
            isLimited: $isLimited,
            isPopular: false,
            sortOrder: 0,
            purchaseCount: 0,
            totalRevenue: 0.0,
            availableFrom: null,
            availableUntil: null,
            promotionEndsAt: null,
            tags: $tags,
            metadata: $metadata,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public static function fromRepositoryData(array $data): self
    {
        $packageId = CoinPackageId::fromInt($data['id']);
        $price = Price::fromFloat($data['price'], Currency::fromString($data['currency']));
        $currency = Currency::fromString($data['currency']);

        $package = new self(
            id: $packageId,
            name: $data['name'],
            description: $data['description'] ?? '',
            coinAmount: $data['coin_amount'],
            bonusCoins: $data['bonus_coins'] ?? 0,
            bonusType: $data['bonus_type'] ?? self::BONUS_NONE,
            price: $price,
            currency: $currency,
            type: $data['type'] ?? self::TYPE_MEDIUM,
            tier: $data['tier'] ?? self::TIER_STANDARD,
            isActive: $data['is_active'] ?? true,
            isFeatured: $data['is_featured'] ?? false,
            isLimited: $data['is_limited'] ?? false,
            isPopular: $data['is_popular'] ?? false,
            sortOrder: $data['sort_order'] ?? 0,
            purchaseCount: $data['purchase_count'] ?? 0,
            totalRevenue: $data['total_revenue'] ?? 0.0,
            availableFrom: isset($data['available_from']) ? Carbon::parse($data['available_from']) : null,
            availableUntil: isset($data['available_until']) ? Carbon::parse($data['available_until']) : null,
            promotionEndsAt: isset($data['promotion_ends_at']) ? Carbon::parse($data['promotion_ends_at']) : null,
            tags: $data['tags'] ?? [],
            metadata: $data['metadata'] ?? [],
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at'])
        );

        return $package;
    }

    // Getters
    public function id(): CoinPackageId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function coinAmount(): int
    {
        return $this->coinAmount;
    }

    public function bonusCoins(): int
    {
        return $this->bonusCoins;
    }

    public function bonusType(): string
    {
        return $this->bonusType;
    }

    public function price(): Price
    {
        return $this->price;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function tier(): string
    {
        return $this->tier;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function isLimited(): bool
    {
        return $this->isLimited;
    }

    public function isPopular(): bool
    {
        return $this->isPopular;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function purchaseCount(): int
    {
        return $this->purchaseCount;
    }

    public function totalRevenue(): float
    {
        return $this->totalRevenue;
    }

    public function availableFrom(): ?Carbon
    {
        return $this->availableFrom;
    }

    public function availableUntil(): ?Carbon
    {
        return $this->availableUntil;
    }

    public function promotionEndsAt(): ?Carbon
    {
        return $this->promotionEndsAt;
    }

    public function tags(): array
    {
        return $this->tags;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function createdAt(): Carbon
    {
        return $this->createdAt;
    }

    public function updatedAt(): Carbon
    {
        return $this->updatedAt;
    }

    // Business Logic Methods
    public function getTotalCoins(): int
    {
        return $this->coinAmount + $this->bonusCoins;
    }

    public function hasBonus(): bool
    {
        return $this->bonusCoins > 0 && $this->bonusType !== self::BONUS_NONE;
    }

    public function getBonusPercentage(): float
    {
        if (!$this->hasBonus() || $this->coinAmount === 0) {
            return 0.0;
        }

        return ($this->bonusCoins / $this->coinAmount) * 100;
    }

    public function getCoinPrice(): float
    {
        return $this->price->toFloat() / $this->coinAmount;
    }

    public function getTotalValue(): float
    {
        return $this->price->toFloat() / $this->getTotalCoins();
    }

    public function getSavings(): float
    {
        if (!$this->hasBonus()) {
            return 0.0;
        }

        $bonusValue = $this->bonusCoins * $this->getCoinPrice();
        return $bonusValue;
    }

    public function getSavingsPercentage(): float
    {
        if (!$this->hasBonus()) {
            return 0.0;
        }

        return ($this->bonusCoins / $this->getTotalCoins()) * 100;
    }

    public function isAvailable(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $now = now();
        
        if ($this->availableFrom && $now->isBefore($this->availableFrom)) {
            return false;
        }

        if ($this->availableUntil && $now->isAfter($this->availableUntil)) {
            return false;
        }

        return true;
    }

    public function isPromotional(): bool
    {
        return $this->type === self::TYPE_PROMOTIONAL || 
               ($this->promotionEndsAt && now()->isBefore($this->promotionEndsAt));
    }

    public function isSeasonal(): bool
    {
        return $this->type === self::TYPE_SEASONAL;
    }

    public function isPremium(): bool
    {
        return $this->type === self::TYPE_PREMIUM || $this->tier === self::TIER_PREMIUM;
    }

    public function isVip(): bool
    {
        return $this->tier === self::TIER_VIP || $this->tier === self::TIER_ELITE;
    }

    public function isSmall(): bool
    {
        return $this->type === self::TYPE_SMALL;
    }

    public function isMedium(): bool
    {
        return $this->type === self::TYPE_MEDIUM;
    }

    public function isLarge(): bool
    {
        return $this->type === self::TYPE_LARGE;
    }

    public function isBeta(): bool
    {
        return $this->type === self::TYPE_BETA;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags);
    }

    public function getAverageRevenue(): float
    {
        if ($this->purchaseCount === 0) {
            return 0.0;
        }

        return $this->totalRevenue / $this->purchaseCount;
    }

    public function getPopularityScore(): int
    {
        // Simple popularity calculation based on purchases and revenue
        $purchaseScore = min($this->purchaseCount * 10, 500);
        $revenueScore = min($this->totalRevenue / 10, 500);
        
        return (int) ($purchaseScore + $revenueScore);
    }

    public function isTrending(): bool
    {
        return $this->getPopularityScore() >= 1000;
    }

    public function getValueRating(): string
    {
        $value = $this->getTotalValue();
        
        if ($value <= 0.01) {
            return 'excellent';
        } elseif ($value <= 0.02) {
            return 'very_good';
        } elseif ($value <= 0.03) {
            return 'good';
        } elseif ($value <= 0.05) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    // State Management Methods
    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = now();
    }

    public function updateDescription(string $description): void
    {
        $this->description = $description;
        $this->updatedAt = now();
    }

    public function updateCoinAmount(int $coinAmount): void
    {
        $this->coinAmount = $coinAmount;
        $this->updatedAt = now();
    }

    public function updateBonus(int $bonusCoins, string $bonusType = self::BONUS_FIXED): void
    {
        $this->bonusCoins = $bonusCoins;
        $this->bonusType = $bonusType;
        $this->updatedAt = now();
    }

    public function updatePrice(Price $price): void
    {
        $this->price = $price;
        $this->updatedAt = now();
    }

    public function updateType(string $type): void
    {
        $this->type = $type;
        $this->updatedAt = now();
    }

    public function updateTier(string $tier): void
    {
        $this->tier = $tier;
        $this->updatedAt = now();
    }

    public function setActive(bool $active): void
    {
        $this->isActive = $active;
        $this->updatedAt = now();
    }

    public function setFeatured(bool $featured): void
    {
        $this->isFeatured = $featured;
        $this->updatedAt = now();
    }

    public function setLimited(bool $limited): void
    {
        $this->isLimited = $limited;
        $this->updatedAt = now();
    }

    public function setPopular(bool $popular): void
    {
        $this->isPopular = $popular;
        $this->updatedAt = now();
    }

    public function setSortOrder(int $order): void
    {
        $this->sortOrder = $order;
        $this->updatedAt = now();
    }

    public function setAvailability(?Carbon $availableFrom, ?Carbon $availableUntil): void
    {
        $this->availableFrom = $availableFrom;
        $this->availableUntil = $availableUntil;
        $this->updatedAt = now();
    }

    public function setPromotion(?Carbon $promotionEndsAt): void
    {
        $this->promotionEndsAt = $promotionEndsAt;
        $this->updatedAt = now();
    }

    public function updateTags(array $tags): void
    {
        $this->tags = $tags;
        $this->updatedAt = now();
    }

    public function addTag(string $tag): void
    {
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
            $this->updatedAt = now();
        }
    }

    public function removeTag(string $tag): void
    {
        $this->tags = array_filter($this->tags, fn($t) => $t !== $tag);
        $this->updatedAt = now();
    }

    public function recordPurchase(float $revenue): void
    {
        $this->purchaseCount++;
        $this->totalRevenue += $revenue;
        $this->updatedAt = now();
    }

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        $this->updatedAt = now();
    }

    // Conversion Methods
    public function toArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'name' => $this->name,
            'description' => $this->description,
            'coin_amount' => $this->coinAmount,
            'bonus_coins' => $this->bonusCoins,
            'bonus_type' => $this->bonusType,
            'total_coins' => $this->getTotalCoins(),
            'price' => $this->price->toFloat(),
            'currency' => $this->currency->value(),
            'coin_price' => $this->getCoinPrice(),
            'total_value' => $this->getTotalValue(),
            'savings' => $this->getSavings(),
            'savings_percentage' => $this->getSavingsPercentage(),
            'type' => $this->type,
            'tier' => $this->tier,
            'is_active' => $this->isActive,
            'is_featured' => $this->isFeatured,
            'is_limited' => $this->isLimited,
            'is_popular' => $this->isPopular,
            'is_available' => $this->isAvailable(),
            'is_promotional' => $this->isPromotional(),
            'is_seasonal' => $this->isSeasonal(),
            'is_premium' => $this->isPremium(),
            'is_vip' => $this->isVip(),
            'has_bonus' => $this->hasBonus(),
            'bonus_percentage' => $this->getBonusPercentage(),
            'sort_order' => $this->sortOrder,
            'purchase_count' => $this->purchaseCount,
            'total_revenue' => $this->totalRevenue,
            'average_revenue' => $this->getAverageRevenue(),
            'popularity_score' => $this->getPopularityScore(),
            'is_trending' => $this->isTrending(),
            'value_rating' => $this->getValueRating(),
            'available_from' => $this->availableFrom?->toISOString(),
            'available_until' => $this->availableUntil?->toISOString(),
            'promotion_ends_at' => $this->promotionEndsAt?->toISOString(),
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }

    public function toRepositoryArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'name' => $this->name,
            'description' => $this->description,
            'coin_amount' => $this->coinAmount,
            'bonus_coins' => $this->bonusCoins,
            'bonus_type' => $this->bonusType,
            'price' => $this->price->toFloat(),
            'currency' => $this->currency->value(),
            'type' => $this->type,
            'tier' => $this->tier,
            'is_active' => $this->isActive,
            'is_featured' => $this->isFeatured,
            'is_limited' => $this->isLimited,
            'is_popular' => $this->isPopular,
            'sort_order' => $this->sortOrder,
            'purchase_count' => $this->purchaseCount,
            'total_revenue' => $this->totalRevenue,
            'available_from' => $this->availableFrom,
            'available_until' => $this->availableUntil,
            'promotion_ends_at' => $this->promotionEndsAt,
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'name' => $this->name,
            'description' => $this->description,
            'coin_amount' => $this->coinAmount,
            'bonus_coins' => $this->bonusCoins,
            'total_coins' => $this->getTotalCoins(),
            'pricing' => [
                'price' => $this->price->toFloat(),
                'currency' => $this->currency->value(),
                'formatted' => $this->currency->formatAmount($this->price->toFloat()),
                'coin_price' => $this->getCoinPrice(),
                'total_value' => $this->getTotalValue(),
            ],
            'bonus' => [
                'has_bonus' => $this->hasBonus(),
                'bonus_coins' => $this->bonusCoins,
                'bonus_type' => $this->bonusType,
                'bonus_percentage' => $this->getBonusPercentage(),
                'savings' => $this->getSavings(),
                'savings_percentage' => $this->getSavingsPercentage(),
            ],
            'type' => $this->type,
            'tier' => $this->tier,
            'status' => [
                'is_active' => $this->isActive,
                'is_featured' => $this->isFeatured,
                'is_limited' => $this->isLimited,
                'is_popular' => $this->isPopular,
                'is_available' => $this->isAvailable(),
                'is_promotional' => $this->isPromotional(),
                'is_seasonal' => $this->isSeasonal(),
            ],
            'analytics' => [
                'purchase_count' => $this->purchaseCount,
                'popularity_score' => $this->getPopularityScore(),
                'is_trending' => $this->isTrending(),
                'value_rating' => $this->getValueRating(),
            ],
            'availability' => [
                'available_from' => $this->availableFrom?->toISOString(),
                'available_until' => $this->availableUntil?->toISOString(),
                'promotion_ends_at' => $this->promotionEndsAt?->toISOString(),
            ],
            'tags' => $this->tags,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }

    public function getDisplayName(): string
    {
        $displayName = $this->name;
        
        if ($this->hasBonus()) {
            $displayName .= " (+{$this->bonusCoins} bonus)";
        }
        
        if ($this->isLimited) {
            $displayName .= ' (Limited)';
        }
        
        if ($this->isPromotional()) {
            $displayName .= ' (Promo)';
        }
        
        return $displayName;
    }

    public function getFormattedPrice(): string
    {
        return $this->currency->formatAmount($this->price->toFloat());
    }

    public function canBePurchased(): bool
    {
        return $this->isAvailable() && $this->isActive;
    }

    public function getAvailabilityStatus(): string
    {
        if (!$this->isActive) {
            return 'inactive';
        }

        if ($this->availableFrom && now()->isBefore($this->availableFrom)) {
            return 'coming_soon';
        }

        if ($this->availableUntil && now()->isAfter($this->availableUntil)) {
            return 'expired';
        }

        if ($this->promotionEndsAt && now()->isAfter($this->promotionEndsAt)) {
            return 'promotion_ended';
        }

        return 'available';
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }

    public function __debugInfo(): array
    {
        return [
            'id' => $this->id->toString(),
            'name' => $this->name,
            'type' => $this->type,
            'tier' => $this->tier,
            'coin_amount' => $this->coinAmount,
            'total_coins' => $this->getTotalCoins(),
            'price' => $this->price->toFloat(),
            'currency' => $this->currency->value(),
            'is_active' => $this->isActive,
            'is_available' => $this->isAvailable(),
            'purchase_count' => $this->purchaseCount,
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
