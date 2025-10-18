<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Entities;

use App\Domain\Commerce\ValueObjects\GiftId;
use App\Domain\Commerce\ValueObjects\Price;
use App\Domain\Commerce\ValueObjects\Currency;
use Carbon\Carbon;

/**
 * Gift Entity
 * 
 * Core domain entity representing a virtual gift in the ForeverUsInLove dating application.
 * Encapsulates gift business logic, state management, and domain rules.
 * 
 * @package App\Domain\Commerce\Entities
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class Gift
{
    private function __construct(
        private readonly GiftId $id,
        private string $name,
        private string $description,
        private Price $price,
        private string $category,
        private string $type,
        private array $tags,
        private array $images,
        private array $animations,
        private bool $isActive,
        private bool $isPremium,
        private bool $isLimited,
        private bool $isSeasonal,
        private ?Carbon $availableFrom,
        private ?Carbon $availableUntil,
        private int $popularityScore,
        private int $purchaseCount,
        private float $totalRevenue,
        private array $metadata,
        private Carbon $createdAt,
        private Carbon $updatedAt
    ) {}

    public static function create(
        GiftId $id,
        string $name,
        string $description,
        Price $price,
        string $category,
        string $type = 'virtual',
        array $tags = [],
        array $images = [],
        array $animations = [],
        bool $isPremium = false,
        bool $isLimited = false,
        bool $isSeasonal = false,
        ?Carbon $availableFrom = null,
        ?Carbon $availableUntil = null,
        array $metadata = []
    ): self {
        $now = now();
        
        return new self(
            id: $id,
            name: $name,
            description: $description,
            price: $price,
            category: $category,
            type: $type,
            tags: $tags,
            images: $images,
            animations: $animations,
            isActive: true,
            isPremium: $isPremium,
            isLimited: $isLimited,
            isSeasonal: $isSeasonal,
            availableFrom: $availableFrom,
            availableUntil: $availableUntil,
            popularityScore: 0,
            purchaseCount: 0,
            totalRevenue: 0.0,
            metadata: $metadata,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public function id(): GiftId
    {
        return $this->id;
    }

    /**
     * Get gift ID (alias for id())
     */
    public function getId(): GiftId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPrice(): Price
    {
        return $this->price;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function getAnimations(): array
    {
        return $this->animations;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isPremium(): bool
    {
        return $this->isPremium;
    }

    public function isLimited(): bool
    {
        return $this->isLimited;
    }

    public function isSeasonal(): bool
    {
        return $this->isSeasonal;
    }

    /**
     * Get gift rarity level
     */
    public function getRarity(): string
    {
        return $this->metadata['rarity'] ?? 'common';
    }

    /**
     * Get gift value (alias for price amount)
     */
    public function getValue(): float
    {
        return $this->price->toFloat();
    }

    /**
     * Get main image URL
     */
    public function getImageUrl(): ?string
    {
        return $this->getMainImage();
    }

    /**
     * Get animation URL
     */
    public function getAnimationUrl(): ?string
    {
        return $this->animations[0] ?? null;
    }

    /**
     * Get sound URL
     */
    public function getSoundUrl(): ?string
    {
        return $this->metadata['sound_url'] ?? null;
    }

    /**
     * Get sentiment score
     */
    public function getSentimentScore(): float
    {
        return $this->metadata['sentiment_score'] ?? 0.5;
    }

    /**
     * Get romantic level
     */
    public function getRomanticLevel(): int
    {
        return $this->metadata['romantic_level'] ?? 3;
    }

    /**
     * Get animation type
     */
    public function getAnimationType(): ?string
    {
        return $this->metadata['animation_type'] ?? null;
    }

    /**
     * Get animation duration
     */
    public function getAnimationDuration(): ?float
    {
        return $this->metadata['animation_duration'] ?? null;
    }

    /**
     * Check if gift is limited edition
     */
    public function isLimitedEdition(): bool
    {
        return $this->isLimited;
    }

    /**
     * Check if gift is socially shareable
     */
    public function isSociallyShareable(): bool
    {
        return $this->metadata['socially_shareable'] ?? true;
    }

    public function getAvailableFrom(): ?Carbon
    {
        return $this->availableFrom;
    }

    public function getAvailableUntil(): ?Carbon
    {
        return $this->availableUntil;
    }

    public function getPopularityScore(): int
    {
        return $this->popularityScore;
    }

    public function getPurchaseCount(): int
    {
        return $this->purchaseCount;
    }

    public function getTotalRevenue(): float
    {
        return $this->totalRevenue;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): Carbon
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): Carbon
    {
        return $this->updatedAt;
    }

    public function getCurrency(): Currency
    {
        // Price no incluye currency, usar USD por defecto
        return Currency::usd();
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

    public function isCurrentlyLimited(): bool
    {
        return $this->isLimited && $this->isAvailable();
    }

    public function isCurrentlySeasonal(): bool
    {
        return $this->isSeasonal && $this->isAvailable();
    }

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

    public function updatePrice(Price $price): void
    {
        $this->price = $price;
        $this->updatedAt = now();
    }

    public function updateCategory(string $category): void
    {
        $this->category = $category;
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

    public function updateImages(array $images): void
    {
        $this->images = $images;
        $this->updatedAt = now();
    }

    public function addImage(string $imageUrl): void
    {
        if (!in_array($imageUrl, $this->images)) {
            $this->images[] = $imageUrl;
            $this->updatedAt = now();
        }
    }

    public function updateAnimations(array $animations): void
    {
        $this->animations = $animations;
        $this->updatedAt = now();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = now();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = now();
    }

    public function setPremium(bool $isPremium): void
    {
        $this->isPremium = $isPremium;
        $this->updatedAt = now();
    }

    public function setLimited(bool $isLimited): void
    {
        $this->isLimited = $isLimited;
        $this->updatedAt = now();
    }

    public function setSeasonal(bool $isSeasonal): void
    {
        $this->isSeasonal = $isSeasonal;
        $this->updatedAt = now();
    }

    public function setAvailability(?Carbon $availableFrom, ?Carbon $availableUntil): void
    {
        $this->availableFrom = $availableFrom;
        $this->availableUntil = $availableUntil;
        $this->updatedAt = now();
    }

    public function incrementPopularity(int $increment = 1): void
    {
        $this->popularityScore += $increment;
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

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags);
    }

    public function hasImage(string $imageUrl): bool
    {
        return in_array($imageUrl, $this->images);
    }

    public function getMainImage(): ?string
    {
        return $this->images[0] ?? null;
    }

    public function getAverageRevenue(): float
    {
        if ($this->purchaseCount === 0) {
            return 0.0;
        }

        return $this->totalRevenue / $this->purchaseCount;
    }

    public function isPopular(): bool
    {
        return $this->popularityScore >= 100;
    }

    public function isTrending(): bool
    {
        return $this->popularityScore >= 500;
    }

    public function getDaysSinceCreation(): int
    {
        return (int) $this->createdAt->diffInDays(now());
    }

    public function isRecentlyCreated(int $days = 7): bool
    {
        return $this->getDaysSinceCreation() <= $days;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price->toArray(),
            'category' => $this->category,
            'type' => $this->type,
            'tags' => $this->tags,
            'images' => $this->images,
            'animations' => $this->animations,
            'is_active' => $this->isActive,
            'is_premium' => $this->isPremium,
            'is_limited' => $this->isLimited,
            'is_seasonal' => $this->isSeasonal,
            'is_available' => $this->isAvailable(),
            'available_from' => $this->availableFrom?->toISOString(),
            'available_until' => $this->availableUntil?->toISOString(),
            'popularity_score' => $this->popularityScore,
            'purchase_count' => $this->purchaseCount,
            'total_revenue' => $this->totalRevenue,
            'average_revenue' => (float) $this->getAverageRevenue(),
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }

    public function getDisplayName(): string
    {
        $displayName = $this->name;
        
        if ($this->isPremium) {
            $displayName .= ' (Premium)';
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
        return $this->price->toString();
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

        return 'available';
    }

    public function getPopularityLevel(): string
    {
        if ($this->popularityScore >= 1000) {
            return 'viral';
        }

        if ($this->popularityScore >= 500) {
            return 'trending';
        }

        if ($this->popularityScore >= 100) {
            return 'popular';
        }

        if ($this->popularityScore >= 50) {
            return 'rising';
        }

        return 'new';
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }
}
