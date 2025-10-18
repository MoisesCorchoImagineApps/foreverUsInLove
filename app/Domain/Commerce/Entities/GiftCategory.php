<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Entities;

use App\Domain\Commerce\ValueObjects\GiftCategoryId;
use Carbon\Carbon;

/**
 * GiftCategory Entity
 * 
 * Core domain entity representing a gift category in the ForeverUsInLove dating application.
 * Encapsulates gift category business logic, organization, and categorization management.
 * 
 * @package App\Domain\Commerce\Entities
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class GiftCategory
{
    // Category Types
    public const TYPE_ROMANTIC = 'romantic';
    public const TYPE_HOLIDAY = 'holiday';
    public const TYPE_CELEBRATION = 'celebration';
    public const TYPE_PREMIUM = 'premium';
    public const TYPE_FUN = 'fun';
    public const TYPE_LUXURY = 'luxury';
    public const TYPE_PERSONALIZED = 'personalized';
    public const TYPE_SEASONAL = 'seasonal';

    // Category Status
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_DRAFT = 'draft';

    // Visibility Levels
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_PREMIUM = 'premium';
    public const VISIBILITY_VIP = 'vip';

    private function __construct(
        private readonly GiftCategoryId $id,
        private string $name,
        private string $slug,
        private string $description,
        private string $type,
        private string $status,
        private string $visibility,
        private ?string $icon,
        private ?string $image,
        private array $tags,
        private int $sortOrder,
        private bool $isFeatured,
        private bool $isPopular,
        private int $giftCount,
        private int $totalPurchases,
        private float $totalRevenue,
        private ?Carbon $availableFrom,
        private ?Carbon $availableUntil,
        private array $metadata,
        private Carbon $createdAt,
        private Carbon $updatedAt
    ) {}

    public static function create(
        GiftCategoryId $id,
        string $name,
        string $description,
        string $type = self::TYPE_ROMANTIC,
        string $status = self::STATUS_ACTIVE,
        string $visibility = self::VISIBILITY_PUBLIC,
        ?string $icon = null,
        ?string $image = null,
        array $tags = [],
        int $sortOrder = 0,
        bool $isFeatured = false
    ): self {
        $now = now();
        
        return new self(
            id: $id,
            name: $name,
            slug: \Illuminate\Support\Str::slug($name),
            description: $description,
            type: $type,
            status: $status,
            visibility: $visibility,
            icon: $icon,
            image: $image,
            tags: $tags,
            sortOrder: $sortOrder,
            isFeatured: $isFeatured,
            isPopular: false,
            giftCount: 0,
            totalPurchases: 0,
            totalRevenue: 0.0,
            availableFrom: null,
            availableUntil: null,
            metadata: [],
            createdAt: $now,
            updatedAt: $now
        );
    }

    public static function fromRepositoryData(array $data): self
    {
        $categoryId = GiftCategoryId::fromInt($data['id']);

        $category = new self(
            id: $categoryId,
            name: $data['name'],
            slug: $data['slug'],
            description: $data['description'] ?? '',
            type: $data['type'] ?? self::TYPE_ROMANTIC,
            status: $data['status'] ?? self::STATUS_ACTIVE,
            visibility: $data['visibility'] ?? self::VISIBILITY_PUBLIC,
            icon: $data['icon'] ?? null,
            image: $data['image'] ?? null,
            tags: $data['tags'] ?? [],
            sortOrder: $data['sort_order'] ?? 0,
            isFeatured: $data['is_featured'] ?? false,
            isPopular: $data['is_popular'] ?? false,
            giftCount: $data['gift_count'] ?? 0,
            totalPurchases: $data['total_purchases'] ?? 0,
            totalRevenue: $data['total_revenue'] ?? 0.0,
            availableFrom: isset($data['available_from']) ? Carbon::parse($data['available_from']) : null,
            availableUntil: isset($data['available_until']) ? Carbon::parse($data['available_until']) : null,
            metadata: $data['metadata'] ?? [],
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at'])
        );

        return $category;
    }

    // Getters
    public function id(): GiftCategoryId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function visibility(): string
    {
        return $this->visibility;
    }

    public function icon(): ?string
    {
        return $this->icon;
    }

    public function image(): ?string
    {
        return $this->image;
    }

    public function tags(): array
    {
        return $this->tags;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function isPopular(): bool
    {
        return $this->isPopular;
    }

    public function giftCount(): int
    {
        return $this->giftCount;
    }

    public function totalPurchases(): int
    {
        return $this->totalPurchases;
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

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function isPrivate(): bool
    {
        return $this->visibility === self::VISIBILITY_PRIVATE;
    }

    public function isPremium(): bool
    {
        return $this->visibility === self::VISIBILITY_PREMIUM;
    }

    public function isVip(): bool
    {
        return $this->visibility === self::VISIBILITY_VIP;
    }

    public function isRomantic(): bool
    {
        return $this->type === self::TYPE_ROMANTIC;
    }

    public function isHoliday(): bool
    {
        return $this->type === self::TYPE_HOLIDAY;
    }

    public function isCelebration(): bool
    {
        return $this->type === self::TYPE_CELEBRATION;
    }

    public function isPremiumType(): bool
    {
        return $this->type === self::TYPE_PREMIUM || $this->type === self::TYPE_LUXURY;
    }

    public function isFun(): bool
    {
        return $this->type === self::TYPE_FUN;
    }

    public function isLuxury(): bool
    {
        return $this->type === self::TYPE_LUXURY;
    }

    public function isPersonalized(): bool
    {
        return $this->type === self::TYPE_PERSONALIZED;
    }

    public function isSeasonal(): bool
    {
        return $this->type === self::TYPE_SEASONAL;
    }

    public function isAvailable(): bool
    {
        if (!$this->isActive()) {
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

    public function hasGifts(): bool
    {
        return $this->giftCount > 0;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags);
    }

    public function getAverageRevenue(): float
    {
        if ($this->totalPurchases === 0) {
            return 0.0;
        }

        return $this->totalRevenue / $this->totalPurchases;
    }

    public function getPopularityScore(): int
    {
        // Simple popularity calculation based on purchases and revenue
        $purchaseScore = min($this->totalPurchases * 2, 1000);
        $revenueScore = min($this->totalRevenue / 5, 1000);
        $giftScore = min($this->giftCount * 5, 500);
        
        return (int) ($purchaseScore + $revenueScore + $giftScore);
    }

    public function isTrending(): bool
    {
        return $this->getPopularityScore() >= 2000;
    }

    public function getTypeDisplayName(): string
    {
        return match($this->type) {
            self::TYPE_ROMANTIC => 'Romantic',
            self::TYPE_HOLIDAY => 'Holiday',
            self::TYPE_CELEBRATION => 'Celebration',
            self::TYPE_PREMIUM => 'Premium',
            self::TYPE_FUN => 'Fun',
            self::TYPE_LUXURY => 'Luxury',
            self::TYPE_PERSONALIZED => 'Personalized',
            self::TYPE_SEASONAL => 'Seasonal',
            default => ucfirst($this->type),
        };
    }

    public function getStatusDisplayName(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_ARCHIVED => 'Archived',
            self::STATUS_DRAFT => 'Draft',
            default => ucfirst($this->status),
        };
    }

    public function getVisibilityDisplayName(): string
    {
        return match($this->visibility) {
            self::VISIBILITY_PUBLIC => 'Public',
            self::VISIBILITY_PRIVATE => 'Private',
            self::VISIBILITY_PREMIUM => 'Premium',
            self::VISIBILITY_VIP => 'VIP',
            default => ucfirst($this->visibility),
        };
    }

    // State Management Methods
    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->slug = \Illuminate\Support\Str::slug($name);
        $this->updatedAt = now();
    }

    public function updateDescription(string $description): void
    {
        $this->description = $description;
        $this->updatedAt = now();
    }

    public function updateType(string $type): void
    {
        $this->type = $type;
        $this->updatedAt = now();
    }

    public function updateStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedAt = now();
    }

    public function updateVisibility(string $visibility): void
    {
        $this->visibility = $visibility;
        $this->updatedAt = now();
    }

    public function updateIcon(?string $icon): void
    {
        $this->icon = $icon;
        $this->updatedAt = now();
    }

    public function updateImage(?string $image): void
    {
        $this->image = $image;
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

    public function incrementGiftCount(int $increment = 1): void
    {
        $this->giftCount += $increment;
        $this->updatedAt = now();
    }

    public function decrementGiftCount(int $decrement = 1): void
    {
        $this->giftCount = max(0, $this->giftCount - $decrement);
        $this->updatedAt = now();
    }

    public function recordPurchase(float $revenue): void
    {
        $this->totalPurchases++;
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
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type,
            'type_display' => $this->getTypeDisplayName(),
            'status' => $this->status,
            'status_display' => $this->getStatusDisplayName(),
            'visibility' => $this->visibility,
            'visibility_display' => $this->getVisibilityDisplayName(),
            'icon' => $this->icon,
            'image' => $this->image,
            'tags' => $this->tags,
            'sort_order' => $this->sortOrder,
            'is_featured' => $this->isFeatured,
            'is_popular' => $this->isPopular,
            'gift_count' => $this->giftCount,
            'total_purchases' => $this->totalPurchases,
            'total_revenue' => $this->totalRevenue,
            'average_revenue' => $this->getAverageRevenue(),
            'popularity_score' => $this->getPopularityScore(),
            'is_trending' => $this->isTrending(),
            'is_active' => $this->isActive(),
            'is_available' => $this->isAvailable(),
            'has_gifts' => $this->hasGifts(),
            'available_from' => $this->availableFrom?->toISOString(),
            'available_until' => $this->availableUntil?->toISOString(),
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
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'icon' => $this->icon,
            'image' => $this->image,
            'tags' => $this->tags,
            'sort_order' => $this->sortOrder,
            'is_featured' => $this->isFeatured,
            'is_popular' => $this->isPopular,
            'gift_count' => $this->giftCount,
            'total_purchases' => $this->totalPurchases,
            'total_revenue' => $this->totalRevenue,
            'available_from' => $this->availableFrom,
            'available_until' => $this->availableUntil,
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
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => [
                'value' => $this->type,
                'display' => $this->getTypeDisplayName(),
            ],
            'status' => [
                'value' => $this->status,
                'display' => $this->getStatusDisplayName(),
            ],
            'visibility' => [
                'value' => $this->visibility,
                'display' => $this->getVisibilityDisplayName(),
            ],
            'assets' => [
                'icon' => $this->icon,
                'image' => $this->image,
            ],
            'tags' => $this->tags,
            'analytics' => [
                'gift_count' => $this->giftCount,
                'total_purchases' => $this->totalPurchases,
                'total_revenue' => $this->totalRevenue,
                'average_revenue' => $this->getAverageRevenue(),
                'popularity_score' => $this->getPopularityScore(),
                'is_trending' => $this->isTrending(),
            ],
            'flags' => [
                'is_featured' => $this->isFeatured,
                'is_popular' => $this->isPopular,
                'is_active' => $this->isActive(),
                'is_available' => $this->isAvailable(),
                'has_gifts' => $this->hasGifts(),
            ],
            'availability' => [
                'available_from' => $this->availableFrom?->toISOString(),
                'available_until' => $this->availableUntil?->toISOString(),
            ],
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
        
        if ($this->isPopular) {
            $displayName .= ' (Popular)';
        }
        
        return $displayName;
    }

    public function getAvailabilityStatus(): string
    {
        if (!$this->isActive()) {
            return 'inactive';
        }

        if ($this->availableFrom && now()->isBefore($this->availableFrom)) {
            return 'coming_soon';
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
            'type' => $this->type,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'gift_count' => $this->giftCount,
            'is_active' => $this->isActive(),
            'is_available' => $this->isAvailable(),
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
