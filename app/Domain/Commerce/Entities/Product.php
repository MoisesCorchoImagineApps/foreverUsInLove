<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Entities;

use App\Domain\Commerce\ValueObjects\ProductId;
use App\Domain\Commerce\ValueObjects\ProductType;
use App\Domain\Commerce\ValueObjects\Price;
use App\Domain\Commerce\ValueObjects\Currency;
use Carbon\Carbon;

/**
 * Product Entity
 * 
 * Core domain entity representing a generic product in the ForeverUsInLove dating application.
 * Encapsulates product business logic, state management, and domain rules for all product types.
 * 
 * @package App\Domain\Commerce\Entities
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class Product
{
    // Product Types
    public const TYPE_GIFT = 'gift';
    public const TYPE_PLAN = 'plan';
    public const TYPE_COIN_PACKAGE = 'coin_package';
    public const TYPE_FEATURE = 'feature';
    public const TYPE_SERVICE = 'service';
    public const TYPE_PREMIUM = 'premium';

    // Product Status
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_DISCONTINUED = 'discontinued';

    // Availability Status
    public const AVAILABILITY_AVAILABLE = 'available';
    public const AVAILABILITY_OUT_OF_STOCK = 'out_of_stock';
    public const AVAILABILITY_COMING_SOON = 'coming_soon';
    public const AVAILABILITY_DISCONTINUED = 'discontinued';

    // Product Tiers
    public const TIER_BASIC = 'basic';
    public const TIER_STANDARD = 'standard';
    public const TIER_PREMIUM = 'premium';
    public const TIER_VIP = 'vip';
    public const TIER_ELITE = 'elite';

    private function __construct(
        private readonly ProductId $id,
        private string $name,
        private string $description,
        private ProductType $type,
        private string $tier,
        private string $status,
        private string $availability,
        private Price $price,
        private Currency $currency,
        private ?int $stock,
        private ?int $minStock,
        private bool $isFeatured,
        private bool $isPopular,
        private bool $isLimited,
        private bool $isSeasonal,
        private int $sortOrder,
        private int $viewCount,
        private int $purchaseCount,
        private float $totalRevenue,
        private ?Carbon $availableFrom,
        private ?Carbon $availableUntil,
        private array $tags,
        private array $metadata,
        private Carbon $createdAt,
        private Carbon $updatedAt
    ) {}

    public static function create(
        ProductId $id,
        string $name,
        string $description,
        ProductType $type,
        Price $price,
        Currency $currency,
        string $tier = self::TIER_STANDARD,
        string $status = self::STATUS_ACTIVE,
        string $availability = self::AVAILABILITY_AVAILABLE,
        ?int $stock = null,
        ?int $minStock = null,
        bool $isFeatured = false,
        bool $isLimited = false,
        bool $isSeasonal = false,
        array $tags = [],
        array $metadata = []
    ): self {
        $now = now();
        
        return new self(
            id: $id,
            name: $name,
            description: $description,
            type: $type,
            tier: $tier,
            status: $status,
            availability: $availability,
            price: $price,
            currency: $currency,
            stock: $stock,
            minStock: $minStock,
            isFeatured: $isFeatured,
            isPopular: false,
            isLimited: $isLimited,
            isSeasonal: $isSeasonal,
            sortOrder: 0,
            viewCount: 0,
            purchaseCount: 0,
            totalRevenue: 0.0,
            availableFrom: null,
            availableUntil: null,
            tags: $tags,
            metadata: $metadata,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public static function fromRepositoryData(array $data): self
    {
        $productId = ProductId::fromInt($data['id']);
        $productType = ProductType::fromString($data['type']);
        $price = Price::fromFloat($data['price'], Currency::fromString($data['currency']));
        $currency = Currency::fromString($data['currency']);

        $product = new self(
            id: $productId,
            name: $data['name'],
            description: $data['description'] ?? '',
            type: $productType,
            tier: $data['tier'] ?? self::TIER_STANDARD,
            status: $data['status'] ?? self::STATUS_ACTIVE,
            availability: $data['availability'] ?? self::AVAILABILITY_AVAILABLE,
            price: $price,
            currency: $currency,
            stock: $data['stock'] ?? null,
            minStock: $data['min_stock'] ?? null,
            isFeatured: $data['is_featured'] ?? false,
            isPopular: $data['is_popular'] ?? false,
            isLimited: $data['is_limited'] ?? false,
            isSeasonal: $data['is_seasonal'] ?? false,
            sortOrder: $data['sort_order'] ?? 0,
            viewCount: $data['view_count'] ?? 0,
            purchaseCount: $data['purchase_count'] ?? 0,
            totalRevenue: $data['total_revenue'] ?? 0.0,
            availableFrom: isset($data['available_from']) ? Carbon::parse($data['available_from']) : null,
            availableUntil: isset($data['available_until']) ? Carbon::parse($data['available_until']) : null,
            tags: $data['tags'] ?? [],
            metadata: $data['metadata'] ?? [],
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at'])
        );

        return $product;
    }

    // Getters
    public function id(): ProductId
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

    public function type(): ProductType
    {
        return $this->type;
    }

    public function tier(): string
    {
        return $this->tier;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function availability(): string
    {
        return $this->availability;
    }

    public function price(): Price
    {
        return $this->price;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function stock(): ?int
    {
        return $this->stock;
    }

    public function minStock(): ?int
    {
        return $this->minStock;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function isPopular(): bool
    {
        return $this->isPopular;
    }

    public function isLimited(): bool
    {
        return $this->isLimited;
    }

    public function isSeasonal(): bool
    {
        return $this->isSeasonal;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function viewCount(): int
    {
        return $this->viewCount;
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
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function isDiscontinued(): bool
    {
        return $this->status === self::STATUS_DISCONTINUED;
    }

    public function isAvailable(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->availability !== self::AVAILABILITY_AVAILABLE) {
            return false;
        }

        $now = now();
        
        if ($this->availableFrom && $now->isBefore($this->availableFrom)) {
            return false;
        }

        if ($this->availableUntil && $now->isAfter($this->availableUntil)) {
            return false;
        }

        // Check stock if applicable
        if ($this->stock !== null && $this->stock <= 0) {
            return false;
        }

        return true;
    }

    public function isOutOfStock(): bool
    {
        return $this->availability === self::AVAILABILITY_OUT_OF_STOCK || 
               ($this->stock !== null && $this->stock <= 0);
    }

    public function isComingSoon(): bool
    {
        return $this->availability === self::AVAILABILITY_COMING_SOON ||
               ($this->availableFrom && now()->isBefore($this->availableFrom));
    }

    public function isBasic(): bool
    {
        return $this->tier === self::TIER_BASIC;
    }

    public function isStandard(): bool
    {
        return $this->tier === self::TIER_STANDARD;
    }

    public function isPremium(): bool
    {
        return $this->tier === self::TIER_PREMIUM;
    }

    public function isVip(): bool
    {
        return $this->tier === self::TIER_VIP || $this->tier === self::TIER_ELITE;
    }

    public function isElite(): bool
    {
        return $this->tier === self::TIER_ELITE;
    }

    public function isGift(): bool
    {
        return $this->type->equals(ProductType::gift());
    }

    public function isPlan(): bool
    {
        return $this->type->equals(ProductType::plan());
    }

    public function isCoinPackage(): bool
    {
        return $this->type->equals(ProductType::coinPackage());
    }

    public function isFeature(): bool
    {
        return $this->type->equals(ProductType::feature());
    }

    public function isService(): bool
    {
        return $this->type->equals(ProductType::service());
    }

    public function hasStock(): bool
    {
        return $this->stock !== null;
    }

    public function hasLowStock(): bool
    {
        return $this->stock !== null && $this->minStock !== null && 
               $this->stock <= $this->minStock;
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
        // Simple popularity calculation based on views, purchases and revenue
        $viewScore = min($this->viewCount / 10, 500);
        $purchaseScore = min($this->purchaseCount * 10, 500);
        $revenueScore = min($this->totalRevenue / 10, 500);
        
        return (int) ($viewScore + $purchaseScore + $revenueScore);
    }

    public function isTrending(): bool
    {
        return $this->getPopularityScore() >= 1000;
    }

    public function getConversionRate(): float
    {
        if ($this->viewCount === 0) {
            return 0.0;
        }

        return ($this->purchaseCount / $this->viewCount) * 100;
    }

    public function getTypeDisplayName(): string
    {
        return match($this->type->value()) {
            self::TYPE_GIFT => 'Gift',
            self::TYPE_PLAN => 'Subscription Plan',
            self::TYPE_COIN_PACKAGE => 'Coin Package',
            self::TYPE_FEATURE => 'Feature',
            self::TYPE_SERVICE => 'Service',
            self::TYPE_PREMIUM => 'Premium',
            default => ucfirst($this->type->value()),
        };
    }

    public function getStatusDisplayName(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ARCHIVED => 'Archived',
            self::STATUS_DISCONTINUED => 'Discontinued',
            default => ucfirst($this->status),
        };
    }

    public function getAvailabilityDisplayName(): string
    {
        return match($this->availability) {
            self::AVAILABILITY_AVAILABLE => 'Available',
            self::AVAILABILITY_OUT_OF_STOCK => 'Out of Stock',
            self::AVAILABILITY_COMING_SOON => 'Coming Soon',
            self::AVAILABILITY_DISCONTINUED => 'Discontinued',
            default => ucfirst($this->availability),
        };
    }

    public function getTierDisplayName(): string
    {
        return match($this->tier) {
            self::TIER_BASIC => 'Basic',
            self::TIER_STANDARD => 'Standard',
            self::TIER_PREMIUM => 'Premium',
            self::TIER_VIP => 'VIP',
            self::TIER_ELITE => 'Elite',
            default => ucfirst($this->tier),
        };
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

    public function updateType(ProductType $type): void
    {
        $this->type = $type;
        $this->updatedAt = now();
    }

    public function updateTier(string $tier): void
    {
        $this->tier = $tier;
        $this->updatedAt = now();
    }

    public function updateStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedAt = now();
    }

    public function updateAvailability(string $availability): void
    {
        $this->availability = $availability;
        $this->updatedAt = now();
    }

    public function updatePrice(Price $price): void
    {
        $this->price = $price;
        $this->updatedAt = now();
    }

    public function updateStock(?int $stock): void
    {
        $this->stock = $stock;
        $this->updatedAt = now();
    }

    public function setMinStock(?int $minStock): void
    {
        $this->minStock = $minStock;
        $this->updatedAt = now();
    }

    public function setFeatured(bool $featured): void
    {
        $this->isFeatured = $featured;
        $this->updatedAt = now();
    }

    public function setPopular(bool $popular): void
    {
        $this->isPopular = $popular;
        $this->updatedAt = now();
    }

    public function setLimited(bool $limited): void
    {
        $this->isLimited = $limited;
        $this->updatedAt = now();
    }

    public function setSeasonal(bool $seasonal): void
    {
        $this->isSeasonal = $seasonal;
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

    public function incrementViews(int $increment = 1): void
    {
        $this->viewCount += $increment;
        $this->updatedAt = now();
    }

    public function recordPurchase(float $revenue): void
    {
        $this->purchaseCount++;
        $this->totalRevenue += $revenue;
        
        // Decrease stock if applicable
        if ($this->stock !== null) {
            $this->stock = max(0, $this->stock - 1);
        }
        
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
            'type' => $this->type->value(),
            'type_display' => $this->getTypeDisplayName(),
            'tier' => $this->tier,
            'tier_display' => $this->getTierDisplayName(),
            'status' => $this->status,
            'status_display' => $this->getStatusDisplayName(),
            'availability' => $this->availability,
            'availability_display' => $this->getAvailabilityDisplayName(),
            'price' => $this->price->toFloat(),
            'currency' => $this->currency->value(),
            'stock' => $this->stock,
            'min_stock' => $this->minStock,
            'is_featured' => $this->isFeatured,
            'is_popular' => $this->isPopular,
            'is_limited' => $this->isLimited,
            'is_seasonal' => $this->isSeasonal,
            'is_active' => $this->isActive(),
            'is_available' => $this->isAvailable(),
            'is_out_of_stock' => $this->isOutOfStock(),
            'is_coming_soon' => $this->isComingSoon(),
            'has_stock' => $this->hasStock(),
            'has_low_stock' => $this->hasLowStock(),
            'sort_order' => $this->sortOrder,
            'view_count' => $this->viewCount,
            'purchase_count' => $this->purchaseCount,
            'total_revenue' => $this->totalRevenue,
            'average_revenue' => $this->getAverageRevenue(),
            'popularity_score' => $this->getPopularityScore(),
            'is_trending' => $this->isTrending(),
            'conversion_rate' => $this->getConversionRate(),
            'available_from' => $this->availableFrom?->toISOString(),
            'available_until' => $this->availableUntil?->toISOString(),
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
            'type' => $this->type->value(),
            'tier' => $this->tier,
            'status' => $this->status,
            'availability' => $this->availability,
            'price' => $this->price->toFloat(),
            'currency' => $this->currency->value(),
            'stock' => $this->stock,
            'min_stock' => $this->minStock,
            'is_featured' => $this->isFeatured,
            'is_popular' => $this->isPopular,
            'is_limited' => $this->isLimited,
            'is_seasonal' => $this->isSeasonal,
            'sort_order' => $this->sortOrder,
            'view_count' => $this->viewCount,
            'purchase_count' => $this->purchaseCount,
            'total_revenue' => $this->totalRevenue,
            'available_from' => $this->availableFrom,
            'available_until' => $this->availableUntil,
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
            'type' => [
                'value' => $this->type->value(),
                'display' => $this->getTypeDisplayName(),
            ],
            'tier' => [
                'value' => $this->tier,
                'display' => $this->getTierDisplayName(),
            ],
            'status' => [
                'value' => $this->status,
                'display' => $this->getStatusDisplayName(),
            ],
            'availability' => [
                'value' => $this->availability,
                'display' => $this->getAvailabilityDisplayName(),
            ],
            'pricing' => [
                'price' => $this->price->toFloat(),
                'currency' => $this->currency->value(),
                'formatted' => $this->currency->formatAmount($this->price->toFloat()),
            ],
            'inventory' => [
                'stock' => $this->stock,
                'min_stock' => $this->minStock,
                'has_stock' => $this->hasStock(),
                'has_low_stock' => $this->hasLowStock(),
                'is_out_of_stock' => $this->isOutOfStock(),
            ],
            'analytics' => [
                'view_count' => $this->viewCount,
                'purchase_count' => $this->purchaseCount,
                'total_revenue' => $this->totalRevenue,
                'average_revenue' => $this->getAverageRevenue(),
                'popularity_score' => $this->getPopularityScore(),
                'is_trending' => $this->isTrending(),
                'conversion_rate' => $this->getConversionRate(),
            ],
            'flags' => [
                'is_featured' => $this->isFeatured,
                'is_popular' => $this->isPopular,
                'is_limited' => $this->isLimited,
                'is_seasonal' => $this->isSeasonal,
                'is_active' => $this->isActive(),
                'is_available' => $this->isAvailable(),
                'is_coming_soon' => $this->isComingSoon(),
            ],
            'availability' => [
                'available_from' => $this->availableFrom?->toISOString(),
                'available_until' => $this->availableUntil?->toISOString(),
            ],
            'tags' => $this->tags,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }

    public function getDisplayName(): string
    {
        $displayName = $this->name;
        
        if ($this->isFeatured) {
            $displayName .= ' (Featured)';
        }
        
        if ($this->isLimited) {
            $displayName .= ' (Limited)';
        }
        
        if ($this->isSeasonal) {
            $displayName .= ' (Seasonal)';
        }
        
        return $displayName;
    }

    public function getFormattedPrice(): string
    {
        return $this->currency->formatAmount($this->price->toFloat());
    }

    public function canBePurchased(): bool
    {
        return $this->isAvailable() && $this->isActive();
    }

    public function getAvailabilityStatus(): string
    {
        if (!$this->isActive()) {
            return 'inactive';
        }

        if ($this->isComingSoon()) {
            return 'coming_soon';
        }

        if ($this->isOutOfStock()) {
            return 'out_of_stock';
        }

        if ($this->availableUntil && now()->isAfter($this->availableUntil)) {
            return 'expired';
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
            'type' => $this->type->value(),
            'tier' => $this->tier,
            'status' => $this->status,
            'availability' => $this->availability,
            'price' => $this->price->toFloat(),
            'currency' => $this->currency->value(),
            'is_active' => $this->isActive(),
            'is_available' => $this->isAvailable(),
            'stock' => $this->stock,
            'purchase_count' => $this->purchaseCount,
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
