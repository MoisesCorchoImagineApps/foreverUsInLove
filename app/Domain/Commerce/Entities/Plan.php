<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Entities;

use App\Domain\Commerce\ValueObjects\PlanId;
use App\Domain\Commerce\ValueObjects\Price;
use App\Domain\Commerce\ValueObjects\Currency;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;

/**
 * Plan Entity
 * 
 * Core domain entity representing a subscription plan in the ForeverUsInLove dating application.
 * Encapsulates plan business logic, pricing, features, and subscription management.
 * 
 * @package App\Domain\Commerce\Entities
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class Plan
{
    // Plan Tiers
    public const TIER_BASIC = 'basic';
    public const TIER_PREMIUM = 'premium';
    public const TIER_VIP = 'vip';
    public const TIER_ELITE = 'elite';

    // Billing Cycles
    public const TYPE_MONTHLY = 'monthly';
    public const TYPE_QUARTERLY = 'quarterly';
    public const TYPE_ANNUAL = 'annual';

    // Feature Keys
    public const FEATURE_DAILY_LIKES = 'daily_likes';
    public const FEATURE_SUPER_LIKES = 'super_likes';
    public const FEATURE_BOOST = 'boost';
    public const FEATURE_UNLIMITED_MESSAGING = 'unlimited_messaging';
    public const FEATURE_ADVANCED_FILTERS = 'advanced_filters';
    public const FEATURE_READ_RECEIPTS = 'read_receipts';
    public const FEATURE_VIDEO_CALLS = 'video_calls';
    public const FEATURE_INCOGNITO_MODE = 'incognito_mode';
    public const FEATURE_TRAVEL_MODE = 'travel_mode';
    public const FEATURE_PRIORITY_SUPPORT = 'priority_support';
    public const FEATURE_PERSONAL_MATCHMAKER = 'personal_matchmaker';
    public const FEATURE_EXCLUSIVE_EVENTS = 'exclusive_events';

    private function __construct(
        private readonly PlanId $id,
        private string $name,
        private string $slug,
        private string $tier,
        private string $type,
        private string $description,
        private array $features,
        private array $limits,
        private Price $priceMonthly,
        private Price $priceQuarterly,
        private Price $priceAnnual,
        private Currency $currency,
        private array $pricingTiers,
        private int $trialDays,
        private bool $isFeatured,
        private bool $isPopular,
        private bool $isActive,
        private int $sortOrder,
        private int $subscriberCount,
        private array $metadata,
        private Carbon $createdAt,
        private Carbon $updatedAt
    ) {}

    public static function create(
        PlanId $id,
        string $name,
        string $tier,
        string $type,
        Price $priceMonthly,
        Price $priceQuarterly,
        Price $priceAnnual,
        Currency $currency,
        array $features = [],
        array $limits = [],
        string $description = '',
        int $trialDays = 7,
        bool $isActive = true
    ): self {
        $now = now();
        
        return new self(
            id: $id,
            name: $name,
            slug: \Illuminate\Support\Str::slug($name),
            tier: $tier,
            type: $type,
            description: $description,
            features: $features,
            limits: $limits,
            priceMonthly: $priceMonthly,
            priceQuarterly: $priceQuarterly,
            priceAnnual: $priceAnnual,
            currency: $currency,
            pricingTiers: [],
            trialDays: $trialDays,
            isFeatured: false,
            isPopular: false,
            isActive: $isActive,
            sortOrder: 0,
            subscriberCount: 0,
            metadata: [],
            createdAt: $now,
            updatedAt: $now
        );
    }

    public static function fromRepositoryData(array $data): self
    {
        $planId = PlanId::fromInt($data['id']);
        $priceMonthly = Price::fromFloat($data['price_monthly'], Currency::fromString($data['currency']));
        $priceQuarterly = Price::fromFloat($data['price_quarterly'], Currency::fromString($data['currency']));
        $priceAnnual = Price::fromFloat($data['price_annual'], Currency::fromString($data['currency']));
        $currency = Currency::fromString($data['currency']);

        $plan = new self(
            id: $planId,
            name: $data['name'],
            slug: $data['slug'],
            tier: $data['tier'],
            type: $data['type'],
            description: $data['description'] ?? '',
            features: $data['features'] ?? [],
            limits: $data['limits'] ?? [],
            priceMonthly: $priceMonthly,
            priceQuarterly: $priceQuarterly,
            priceAnnual: $priceAnnual,
            currency: $currency,
            pricingTiers: $data['pricing_tiers'] ?? [],
            trialDays: $data['trial_days'] ?? 7,
            isFeatured: $data['is_featured'] ?? false,
            isPopular: $data['is_popular'] ?? false,
            isActive: $data['is_active'] ?? true,
            sortOrder: $data['sort_order'] ?? 0,
            subscriberCount: $data['subscriber_count'] ?? 0,
            metadata: $data['metadata'] ?? [],
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at'])
        );

        return $plan;
    }

    // Getters
    public function id(): PlanId
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

    public function tier(): string
    {
        return $this->tier;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function features(): array
    {
        return $this->features;
    }

    public function limits(): array
    {
        return $this->limits;
    }

    public function priceMonthly(): Price
    {
        return $this->priceMonthly;
    }

    public function priceQuarterly(): Price
    {
        return $this->priceQuarterly;
    }

    public function priceAnnual(): Price
    {
        return $this->priceAnnual;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function pricingTiers(): array
    {
        return $this->pricingTiers;
    }

    public function trialDays(): int
    {
        return $this->trialDays;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function isPopular(): bool
    {
        return $this->isPopular;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function subscriberCount(): int
    {
        return $this->subscriberCount;
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
    public function getPriceForCycle(string $cycle): Price
    {
        return match($cycle) {
            self::TYPE_MONTHLY => $this->priceMonthly,
            self::TYPE_QUARTERLY => $this->priceQuarterly,
            self::TYPE_ANNUAL => $this->priceAnnual,
            default => $this->priceMonthly,
        };
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features);
    }

    public function getLimit(string $feature): mixed
    {
        return $this->limits[$feature] ?? null;
    }

    public function isUnlimited(string $feature): bool
    {
        $limit = $this->getLimit($feature);
        return $limit === -1 || $limit === true;
    }

    public function canAccess(string $feature): bool
    {
        if (!$this->hasFeature($feature)) {
            return false;
        }

        $limit = $this->getLimit($feature);

        // Boolean features
        if (is_bool($limit)) {
            return $limit;
        }

        // Unlimited
        if ($limit === -1) {
            return true;
        }

        // Numeric limits checked elsewhere
        return true;
    }

    public function calculateSavings(string $cycle): float
    {
        return match($cycle) {
            self::TYPE_QUARTERLY => max(0, ($this->priceMonthly->toFloat() * 3) - $this->priceQuarterly->toFloat()),
            self::TYPE_ANNUAL => max(0, ($this->priceMonthly->toFloat() * 12) - $this->priceAnnual->toFloat()),
            default => 0,
        };
    }

    public function calculateSavingsPercentage(string $cycle): int
    {
        $monthlyEquivalent = $this->priceMonthly->toFloat() * match($cycle) {
            self::TYPE_QUARTERLY => 3,
            self::TYPE_ANNUAL => 12,
            default => 1,
        };

        if ($monthlyEquivalent == 0) return 0;
        
        $savings = $this->calculateSavings($cycle);
        return intval(($savings / $monthlyEquivalent) * 100);
    }

    public function compareWith(Plan $otherPlan): array
    {
        $thisFeatures = $this->getAvailableFeatures();
        $otherFeatures = $otherPlan->getAvailableFeatures();

        $allFeatures = array_unique(array_merge(array_keys($thisFeatures), array_keys($otherFeatures)));

        $comparison = [];
        foreach ($allFeatures as $feature) {
            $comparison[$feature] = [
                'feature' => $feature,
                'this_plan' => $thisFeatures[$feature] ?? null,
                'other_plan' => $otherFeatures[$feature] ?? null,
                'better' => $this->isFeatureBetter($feature, $otherPlan),
            ];
        }

        return $comparison;
    }

    public function getAvailableFeatures(): array
    {
        $features = [];
        foreach ($this->features as $feature) {
            $features[$feature] = [
                'key' => $feature,
                'name' => ucwords(str_replace('_', ' ', $feature)),
                'limit' => $this->getLimit($feature),
                'unlimited' => $this->isUnlimited($feature),
                'enabled' => $this->canAccess($feature),
            ];
        }
        return $features;
    }

    protected function isFeatureBetter(string $feature, Plan $otherPlan): ?bool
    {
        $thisLimit = $this->getLimit($feature);
        $otherLimit = $otherPlan->getLimit($feature);

        if ($thisLimit === null) return false;
        if ($otherLimit === null) return true;

        // Unlimited is always better
        if ($thisLimit === -1) return true;
        if ($otherLimit === -1) return false;

        // Boolean comparison
        if (is_bool($thisLimit) && is_bool($otherLimit)) {
            return $thisLimit && !$otherLimit;
        }

        // Numeric comparison
        if (is_numeric($thisLimit) && is_numeric($otherLimit)) {
            return $thisLimit > $otherLimit;
        }

        return null;
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

    public function updateFeatures(array $features): void
    {
        $this->features = $features;
        $this->updatedAt = now();
    }

    public function updateLimits(array $limits): void
    {
        $this->limits = $limits;
        $this->updatedAt = now();
    }

    public function updatePricing(Price $monthly, Price $quarterly, Price $annual): void
    {
        $this->priceMonthly = $monthly;
        $this->priceQuarterly = $quarterly;
        $this->priceAnnual = $annual;
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

    public function setActive(bool $active): void
    {
        $this->isActive = $active;
        $this->updatedAt = now();
    }

    public function setSortOrder(int $order): void
    {
        $this->sortOrder = $order;
        $this->updatedAt = now();
    }

    public function incrementSubscribers(): void
    {
        $this->subscriberCount++;
        $this->updatedAt = now();
    }

    public function decrementSubscribers(): void
    {
        $this->subscriberCount = max(0, $this->subscriberCount - 1);
        $this->updatedAt = now();
    }

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        $this->updatedAt = now();
    }

    // Query Methods
    public function isBasic(): bool
    {
        return $this->tier === self::TIER_BASIC;
    }

    public function isPremium(): bool
    {
        return $this->tier === self::TIER_PREMIUM;
    }

    public function isVip(): bool
    {
        return $this->tier === self::TIER_VIP;
    }

    public function isElite(): bool
    {
        return $this->tier === self::TIER_ELITE;
    }

    public function isMonthly(): bool
    {
        return $this->type === self::TYPE_MONTHLY;
    }

    public function isQuarterly(): bool
    {
        return $this->type === self::TYPE_QUARTERLY;
    }

    public function isAnnual(): bool
    {
        return $this->type === self::TYPE_ANNUAL;
    }

    public function hasTrial(): bool
    {
        return $this->trialDays > 0;
    }

    /**
     * Get the appropriate price based on billing cycle
     */
    public function getPrice(): Price
    {
        return match($this->type) {
            self::TYPE_QUARTERLY => $this->priceQuarterly,
            self::TYPE_ANNUAL => $this->priceAnnual,
            default => $this->priceMonthly
        };
    }

    /**
     * Get exclusive benefits for this plan
     */
    public function getExclusiveBenefits(): array
    {
        return $this->metadata['exclusive_benefits'] ?? [];
    }

    /**
     * Get premium perks for this plan
     */
    public function getPremiumPerks(): array
    {
        return $this->metadata['premium_perks'] ?? [];
    }

    /**
     * Get social status benefits
     */
    public function getSocialStatus(): array
    {
        return $this->metadata['social_status'] ?? [];
    }

    /**
     * Get priority level for this plan
     */
    public function getPriorityLevel(): string
    {
        return $this->metadata['priority_level'] ?? 'standard';
    }

    /**
     * Get support level for this plan
     */
    public function getSupportLevel(): string
    {
        return $this->metadata['support_level'] ?? 'standard';
    }

    /**
     * Get customization options
     */
    public function getCustomizationOptions(): array
    {
        return $this->metadata['customization_options'] ?? [];
    }

    /**
     * Get community access level
     */
    public function getCommunityAccess(): array
    {
        return $this->metadata['community_access'] ?? [];
    }

    /**
     * Get community tier
     */
    public function getCommunityTier(): string
    {
        return $this->metadata['community_tier'] ?? $this->tier;
    }

    /**
     * Get partner integrations
     */
    public function getPartnerIntegrations(): array
    {
        return $this->metadata['partner_integrations'] ?? [];
    }

    // Conversion Methods
    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'name' => $this->name,
            'slug' => $this->slug,
            'tier' => $this->tier,
            'type' => $this->type,
            'description' => $this->description,
            'features' => $this->features,
            'limits' => $this->limits,
            'price_monthly' => $this->priceMonthly->toFloat(),
            'price_quarterly' => $this->priceQuarterly->toFloat(),
            'price_annual' => $this->priceAnnual->toFloat(),
            'currency' => $this->currency->value(),
            'pricing_tiers' => $this->pricingTiers,
            'trial_days' => $this->trialDays,
            'is_featured' => $this->isFeatured,
            'is_popular' => $this->isPopular,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
            'subscriber_count' => $this->subscriberCount,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->toDateTimeString(),
            'updated_at' => $this->updatedAt->toDateTimeString(),
        ];
    }

    public function toRepositoryArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'name' => $this->name,
            'slug' => $this->slug,
            'tier' => $this->tier,
            'type' => $this->type,
            'description' => $this->description,
            'features' => $this->features,
            'limits' => $this->limits,
            'price_monthly' => $this->priceMonthly->toFloat(),
            'price_quarterly' => $this->priceQuarterly->toFloat(),
            'price_annual' => $this->priceAnnual->toFloat(),
            'currency' => $this->currency->value(),
            'pricing_tiers' => $this->pricingTiers,
            'trial_days' => $this->trialDays,
            'is_featured' => $this->isFeatured,
            'is_popular' => $this->isPopular,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
            'subscriber_count' => $this->subscriberCount,
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
            'tier' => $this->tier,
            'type' => $this->type,
            'description' => $this->description,
            'features' => $this->getAvailableFeatures(),
            'pricing' => [
                'monthly' => [
                    'amount' => $this->priceMonthly->toFloat(),
                    'currency' => $this->currency->value(),
                    'formatted' => $this->currency->formatAmount($this->priceMonthly->toFloat()),
                ],
                'quarterly' => [
                    'amount' => $this->priceQuarterly->toFloat(),
                    'currency' => $this->currency->value(),
                    'formatted' => $this->currency->formatAmount($this->priceQuarterly->toFloat()),
                    'savings' => $this->calculateSavings(self::TYPE_QUARTERLY),
                    'savings_percentage' => $this->calculateSavingsPercentage(self::TYPE_QUARTERLY),
                ],
                'annual' => [
                    'amount' => $this->priceAnnual->toFloat(),
                    'currency' => $this->currency->value(),
                    'formatted' => $this->currency->formatAmount($this->priceAnnual->toFloat()),
                    'savings' => $this->calculateSavings(self::TYPE_ANNUAL),
                    'savings_percentage' => $this->calculateSavingsPercentage(self::TYPE_ANNUAL),
                ],
            ],
            'trial_days' => $this->trialDays,
            'is_featured' => $this->isFeatured,
            'is_popular' => $this->isPopular,
            'is_active' => $this->isActive,
            'subscriber_count' => $this->subscriberCount,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }

    public function __toString(): string
    {
        return "Plan({$this->id->toString()}, {$this->name}, {$this->tier})";
    }

    public function __debugInfo(): array
    {
        return [
            'id' => $this->id->toString(),
            'name' => $this->name,
            'tier' => $this->tier,
            'type' => $this->type,
            'is_active' => $this->isActive,
            'subscriber_count' => $this->subscriberCount,
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
