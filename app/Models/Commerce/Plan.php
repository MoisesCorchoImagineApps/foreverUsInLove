<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Plan Model - Subscription Plan Management
 * 
 * Defines subscription tiers with features and limits
 * 4 tiers: basic, premium, vip, elite
 * 
 * @property int $id
 * @property string $name (Basic, Premium, VIP, Elite)
 * @property string $slug URL-friendly identifier
 * @property string $tier (basic, premium, vip, elite)
 * @property string $type (monthly, quarterly, annual)
 * @property string $description
 * @property array $features List of feature keys
 * @property array $limits Numeric limits per feature
 * @property decimal $price_monthly
 * @property decimal $price_quarterly
 * @property decimal $price_annual
 * @property string $currency Default USD
 * @property array $pricing_tiers Regional pricing
 * @property int $trial_days Free trial period
 * @property bool $is_featured
 * @property bool $is_popular Most popular plan
 * @property bool $is_active
 * @property int $sort_order Display order
 * @property int $subscriber_count Active subscribers
 * @property array $metadata Additional data
 * @property timestamps created_at, updated_at
 * @property softDeletes deleted_at
 */
class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'plans';

    protected $fillable = [
        'name',
        'slug',
        'tier',
        'type',
        'description',
        'features',
        'limits',
        'price_monthly',
        'price_quarterly',
        'price_annual',
        'currency',
        'pricing_tiers',
        'trial_days',
        'is_featured',
        'is_popular',
        'is_active',
        'sort_order',
        'subscriber_count',
        'metadata',
    ];

    protected $hidden = [
        'metadata',
    ];

    protected $casts = [
        'features' => 'array',
        'limits' => 'array',
        'price_monthly' => 'decimal:2',
        'price_quarterly' => 'decimal:2',
        'price_annual' => 'decimal:2',
        'pricing_tiers' => 'array',
        'trial_days' => 'integer',
        'is_featured' => 'boolean',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'subscriber_count' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Plan Tiers
    const TIER_BASIC = 'basic';
    const TIER_PREMIUM = 'premium';
    const TIER_VIP = 'vip';
    const TIER_ELITE = 'elite';

    // Billing Cycles
    const TYPE_MONTHLY = 'monthly';
    const TYPE_QUARTERLY = 'quarterly';
    const TYPE_ANNUAL = 'annual';

    // Feature Keys
    const FEATURE_DAILY_LIKES = 'daily_likes';
    const FEATURE_SUPER_LIKES = 'super_likes';
    const FEATURE_BOOST = 'boost';
    const FEATURE_UNLIMITED_MESSAGING = 'unlimited_messaging';
    const FEATURE_ADVANCED_FILTERS = 'advanced_filters';
    const FEATURE_READ_RECEIPTS = 'read_receipts';
    const FEATURE_VIDEO_CALLS = 'video_calls';
    const FEATURE_INCOGNITO_MODE = 'incognito_mode';
    const FEATURE_TRAVEL_MODE = 'travel_mode';
    const FEATURE_PRIORITY_SUPPORT = 'priority_support';
    const FEATURE_PERSONAL_MATCHMAKER = 'personal_matchmaker';
    const FEATURE_EXCLUSIVE_EVENTS = 'exclusive_events';

    // Default Limits by Tier
    const LIMITS_BASIC = [
        'daily_likes' => 10,
        'super_likes' => 1,
        'boost' => 0,
        'video_calls' => false,
        'advanced_filters' => false,
    ];

    const LIMITS_PREMIUM = [
        'daily_likes' => 50,
        'super_likes' => 5,
        'boost' => 1,
        'video_calls' => true,
        'advanced_filters' => true,
        'unlimited_messaging' => true,
        'read_receipts' => true,
    ];

    const LIMITS_VIP = [
        'daily_likes' => 100,
        'super_likes' => 10,
        'boost' => 2,
        'video_calls' => true,
        'advanced_filters' => true,
        'unlimited_messaging' => true,
        'read_receipts' => true,
        'incognito_mode' => true,
        'travel_mode' => true,
        'priority_support' => true,
    ];

    const LIMITS_ELITE = [
        'daily_likes' => -1, // unlimited
        'super_likes' => -1, // unlimited
        'boost' => 5,
        'video_calls' => true,
        'advanced_filters' => true,
        'unlimited_messaging' => true,
        'read_receipts' => true,
        'incognito_mode' => true,
        'travel_mode' => true,
        'priority_support' => true,
        'personal_matchmaker' => true,
        'exclusive_events' => true,
    ];

    /**
     * ===============================================
     * RELATIONSHIPS
     * ===============================================
     */

    /**
     * Active subscriptions for this plan
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Active subscriptions only
     */
    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE);
    }

    /**
     * Users subscribed to this plan
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subscriptions')
            ->wherePivot('status', Subscription::STATUS_ACTIVE)
            ->withTimestamps();
    }

    /**
     * Product representation
     */
    public function product(): MorphOne
    {
        return $this->morphOne(Product::class, 'productable');
    }

    /**
     * ===============================================
     * SCOPES
     * ===============================================
     */

    /**
     * Scope for active plans
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured plans
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope by tier
     */
    public function scopeByTier(Builder $query, string $tier): Builder
    {
        return $query->where('tier', $tier);
    }

    /**
     * Scope by type (billing cycle)
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope ordered by price
     */
    public function scopeOrderedByPrice(Builder $query, string $cycle = 'monthly'): Builder
    {
        $column = "price_{$cycle}";
        return $query->orderBy($column);
    }

    /**
     * Scope ordered by sort order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('price_monthly');
    }

    /**
     * ===============================================
     * ACCESSORS & MUTATORS
     * ===============================================
     */

    /**
     * Get price for billing cycle
     */
    public function getPriceAttribute(): float
    {
        return match($this->type) {
            self::TYPE_MONTHLY => $this->price_monthly,
            self::TYPE_QUARTERLY => $this->price_quarterly,
            self::TYPE_ANNUAL => $this->price_annual,
            default => $this->price_monthly,
        };
    }

    /**
     * Get formatted monthly price
     */
    public function getFormattedPriceMonthlyAttribute(): string
    {
        return '$' . number_format($this->price_monthly, 2);
    }

    /**
     * Get formatted quarterly price
     */
    public function getFormattedPriceQuarterlyAttribute(): string
    {
        return '$' . number_format($this->price_quarterly, 2);
    }

    /**
     * Get formatted annual price
     */
    public function getFormattedPriceAnnualAttribute(): string
    {
        return '$' . number_format($this->price_annual, 2);
    }

    /**
     * Calculate savings for quarterly
     */
    public function getQuarterlySavingsAttribute(): float
    {
        $monthlyEquivalent = $this->price_monthly * 3;
        return max(0, $monthlyEquivalent - $this->price_quarterly);
    }

    /**
     * Calculate savings for annual
     */
    public function getAnnualSavingsAttribute(): float
    {
        $monthlyEquivalent = $this->price_monthly * 12;
        return max(0, $monthlyEquivalent - $this->price_annual);
    }

    /**
     * Get savings percentage for annual
     */
    public function getAnnualSavingsPercentageAttribute(): int
    {
        $monthlyEquivalent = $this->price_monthly * 12;
        if ($monthlyEquivalent == 0) return 0;
        
        return intval(($this->annual_savings / $monthlyEquivalent) * 100);
    }

    /**
     * Check if plan has feature
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Get limit for feature
     */
    public function getLimit(string $feature): mixed
    {
        return $this->limits[$feature] ?? null;
    }

    /**
     * Check if feature is unlimited
     */
    public function isUnlimited(string $feature): bool
    {
        $limit = $this->getLimit($feature);
        return $limit === -1 || $limit === true;
    }

    /**
     * ===============================================
     * BUSINESS LOGIC METHODS
     * ===============================================
     */

    /**
     * Check if user can access feature
     */
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

    /**
     * Get all available features
     */
    public function getAvailableFeatures(): Collection
    {
        return collect($this->features ?? [])->mapWithKeys(function($feature) {
            return [
                $feature => [
                    'key' => $feature,
                    'name' => ucwords(str_replace('_', ' ', $feature)),
                    'limit' => $this->getLimit($feature),
                    'unlimited' => $this->isUnlimited($feature),
                    'enabled' => $this->canAccess($feature),
                ]
            ];
        });
    }

    /**
     * Compare with another plan
     */
    public function compareWith(Plan $otherPlan): array
    {
        $thisFeatures = $this->getAvailableFeatures();
        $otherFeatures = $otherPlan->getAvailableFeatures();

        $allFeatures = $thisFeatures->keys()->merge($otherFeatures->keys())->unique();

        return $allFeatures->mapWithKeys(function($feature) use ($thisFeatures, $otherFeatures) {
            return [
                $feature => [
                    'feature' => $feature,
                    'this_plan' => $thisFeatures->get($feature),
                    'other_plan' => $otherFeatures->get($feature),
                    'better' => $this->isFeatureBetter($feature, $otherPlan),
                ]
            ];
        })->toArray();
    }

    /**
     * Check if this plan's feature is better than another
     */
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

    /**
     * Get recommendation score for user
     */
    public function getRecommendationScore(User $user): int
    {
        $score = 0;

        // Base score by tier
        $tierScores = [
            self::TIER_BASIC => 40,
            self::TIER_PREMIUM => 60,
            self::TIER_VIP => 75,
            self::TIER_ELITE => 85,
        ];
        $score += $tierScores[$this->tier] ?? 50;

        // Adjust based on user activity
        $activityLevel = $user->getActivityLevel(); // high, medium, low
        
        if ($activityLevel === 'high' && in_array($this->tier, [self::TIER_VIP, self::TIER_ELITE])) {
            $score += 15;
        } elseif ($activityLevel === 'medium' && $this->tier === self::TIER_PREMIUM) {
            $score += 10;
        } elseif ($activityLevel === 'low' && $this->tier === self::TIER_BASIC) {
            $score += 5;
        }

        // Popularity bonus
        if ($this->is_popular) {
            $score += 10;
        }

        // Match history bonus
        $matchCount = $user->matches()->count();
        if ($matchCount > 50 && in_array($this->tier, [self::TIER_PREMIUM, self::TIER_VIP])) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * Calculate proration for plan change
     */
    public function calculateProration(Subscription $currentSubscription, string $newCycle): array
    {
        $daysRemaining = now()->diffInDays($currentSubscription->ends_at);
        $totalDays = $currentSubscription->started_at->diffInDays($currentSubscription->ends_at);
        
        $unusedValue = ($currentSubscription->plan->price * $daysRemaining) / $totalDays;
        
        $newPrice = match($newCycle) {
            self::TYPE_MONTHLY => $this->price_monthly,
            self::TYPE_QUARTERLY => $this->price_quarterly,
            self::TYPE_ANNUAL => $this->price_annual,
            default => $this->price_monthly,
        };

        $amountDue = max(0, $newPrice - $unusedValue);

        return [
            'unused_value' => round($unusedValue, 2),
            'new_price' => $newPrice,
            'amount_due' => round($amountDue, 2),
            'days_remaining' => $daysRemaining,
            'total_days' => $totalDays,
        ];
    }

    /**
     * Increment subscriber count
     */
    public function incrementSubscribers(): void
    {
        $this->increment('subscriber_count');
    }

    /**
     * Decrement subscriber count
     */
    public function decrementSubscribers(): void
    {
        $this->decrement('subscriber_count');
    }

    /**
     * Sync subscriber count from database
     */
    public function syncSubscriberCount(): void
    {
        $count = $this->activeSubscriptions()->count();
        $this->update(['subscriber_count' => $count]);
    }

    /**
     * ===============================================
     * STATIC METHODS
     * ===============================================
     */

    /**
     * Get all active plans grouped by tier
     */
    public static function getActivePlansByTier(): Collection
    {
        return self::active()
            ->ordered()
            ->get()
            ->groupBy('tier');
    }

    /**
     * Get pricing comparison table
     */
    public static function getPricingComparison(): array
    {
        $plans = self::active()->ordered()->get();
        
        $allFeatures = $plans->flatMap(fn($plan) => $plan->features)->unique()->values();

        return [
            'features' => $allFeatures,
            'plans' => $plans->map(function($plan) use ($allFeatures) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'tier' => $plan->tier,
                    'price_monthly' => $plan->formatted_price_monthly,
                    'price_annual' => $plan->formatted_price_annual,
                    'annual_savings' => $plan->annual_savings_percentage . '%',
                    'is_popular' => $plan->is_popular,
                    'features' => $allFeatures->mapWithKeys(fn($f) => [
                        $f => [
                            'enabled' => $plan->hasFeature($f),
                            'limit' => $plan->getLimit($f),
                            'unlimited' => $plan->isUnlimited($f),
                        ]
                    ]),
                ];
            }),
        ];
    }

    /**
     * Get recommended plan for user
     */
    public static function getRecommendedFor(User $user): ?self
    {
        $plans = self::active()->get();

        $scored = $plans->map(function($plan) use ($user) {
            return [
                'plan' => $plan,
                'score' => $plan->getRecommendationScore($user),
            ];
        })->sortByDesc('score');

        return $scored->first()['plan'] ?? null;
    }

    /**
     * Get upgrade path from current plan
     */
    public static function getUpgradePath(Plan $currentPlan): Collection
    {
        $tierOrder = [
            self::TIER_BASIC => 1,
            self::TIER_PREMIUM => 2,
            self::TIER_VIP => 3,
            self::TIER_ELITE => 4,
        ];

        $currentOrder = $tierOrder[$currentPlan->tier] ?? 0;

        return self::active()
            ->get()
            ->filter(function($plan) use ($tierOrder, $currentOrder) {
                return ($tierOrder[$plan->tier] ?? 0) > $currentOrder;
            })
            ->sortBy(fn($plan) => $tierOrder[$plan->tier]);
    }

    /**
     * Get plan analytics
     */
    public static function getAnalytics(): array
    {
        $plans = self::withCount('activeSubscriptions')->get();

        return [
            'total_plans' => $plans->count(),
            'active_plans' => $plans->where('is_active', true)->count(),
            'total_subscribers' => $plans->sum('subscriber_count'),
            'by_tier' => $plans->groupBy('tier')->map(function($tierPlans) {
                return [
                    'count' => $tierPlans->count(),
                    'subscribers' => $tierPlans->sum('subscriber_count'),
                    'revenue_potential' => $tierPlans->sum(fn($p) => $p->price_monthly * $p->subscriber_count),
                ];
            }),
            'most_popular' => $plans->sortByDesc('subscriber_count')->first(),
            'avg_price' => $plans->avg('price_monthly'),
            'revenue_potential_monthly' => $plans->sum(fn($p) => $p->price_monthly * $p->subscriber_count),
        ];
    }

    /**
     * Seed default plans
     */
    public static function seedDefaults(): void
    {
        $defaults = [
            [
                'name' => 'Basic',
                'tier' => self::TIER_BASIC,
                'price_monthly' => 0,
                'price_quarterly' => 0,
                'price_annual' => 0,
                'features' => [self::FEATURE_DAILY_LIKES, self::FEATURE_SUPER_LIKES],
                'limits' => self::LIMITS_BASIC,
                'sort_order' => 1,
            ],
            [
                'name' => 'Premium',
                'tier' => self::TIER_PREMIUM,
                'price_monthly' => 29.99,
                'price_quarterly' => 79.99,
                'price_annual' => 299.99,
                'features' => array_keys(self::LIMITS_PREMIUM),
                'limits' => self::LIMITS_PREMIUM,
                'is_popular' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'VIP',
                'tier' => self::TIER_VIP,
                'price_monthly' => 49.99,
                'price_quarterly' => 139.99,
                'price_annual' => 499.99,
                'features' => array_keys(self::LIMITS_VIP),
                'limits' => self::LIMITS_VIP,
                'sort_order' => 3,
            ],
            [
                'name' => 'Elite',
                'tier' => self::TIER_ELITE,
                'price_monthly' => 99.99,
                'price_quarterly' => 279.99,
                'price_annual' => 999.99,
                'features' => array_keys(self::LIMITS_ELITE),
                'limits' => self::LIMITS_ELITE,
                'is_featured' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($defaults as $planData) {
            $planData['slug'] = \Illuminate\Support\Str::slug($planData['name']);
            $planData['type'] = self::TYPE_MONTHLY;
            $planData['is_active'] = true;
            $planData['trial_days'] = 7;
            
            self::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }

    /**
     * ===============================================
     * CACHE METHODS
     * ===============================================
     */

    /**
     * Clear plan caches
     */
    public function clearCaches(): void
    {
        Cache::forget("plan_{$this->id}");
        Cache::forget("plan_slug_{$this->slug}");
        Cache::tags(['plans'])->flush();
    }

    /**
     * Warm plan caches
     */
    public static function warmCaches(): void
    {
        Cache::remember('active_plans', 3600, function() {
            return self::active()->ordered()->get();
        });

        Cache::remember('pricing_comparison', 3600, function() {
            return self::getPricingComparison();
        });
    }

    /**
     * ===============================================
     * MODEL EVENTS
     * ===============================================
     */

    protected static function booted(): void
    {
        // Generate slug on creation
        static::creating(function (Plan $plan) {
            if (empty($plan->slug)) {
                $plan->slug = \Illuminate\Support\Str::slug($plan->name);
            }

            // Set defaults
            $plan->is_active = $plan->is_active ?? true;
            $plan->is_featured = $plan->is_featured ?? false;
            $plan->is_popular = $plan->is_popular ?? false;
            $plan->subscriber_count = $plan->subscriber_count ?? 0;
            $plan->trial_days = $plan->trial_days ?? 7;
            $plan->currency = $plan->currency ?? 'USD';
        });

        // Clear caches on update
        static::updated(function (Plan $plan) {
            $plan->clearCaches();
        });

        // Clear caches on delete
        static::deleted(function (Plan $plan) {
            $plan->clearCaches();
        });
    }
}