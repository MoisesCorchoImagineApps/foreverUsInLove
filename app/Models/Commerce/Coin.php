<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

/**
 * Coin Model - Virtual Currency Package
 * 
 * Represents purchasable coin packages with bonus tiers
 * 5 tiers: starter, popular, value, premium, elite
 * 
 * @property int $id
 * @property string $name (Starter Pack, Popular Pack, etc)
 * @property string $slug URL-friendly identifier
 * @property string $tier (starter, popular, value, premium, elite)
 * @property int $coins Base coin amount
 * @property int $bonus_coins Bonus coins
 * @property int $total_coins coins + bonus_coins
 * @property float $bonus_percentage (0%, 15%, 25%, 35%, 50%)
 * @property decimal $price USD price
 * @property string $currency Default USD
 * @property array $pricing_tiers Regional pricing
 * @property string $description
 * @property bool $is_featured
 * @property bool $is_popular Best value
 * @property bool $is_active
 * @property int $sort_order Display order
 * @property int $purchase_count Total purchases
 * @property int $popularity_score Trending score
 * @property array $metadata Additional data
 * @property timestamps created_at, updated_at
 * @property softDeletes deleted_at
 */
class Coin extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'coin_packages';

    protected $fillable = [
        'name',
        'slug',
        'tier',
        'coins',
        'bonus_coins',
        'total_coins',
        'bonus_percentage',
        'price',
        'currency',
        'pricing_tiers',
        'description',
        'is_featured',
        'is_popular',
        'is_active',
        'sort_order',
        'purchase_count',
        'popularity_score',
        'metadata',
    ];

    protected $hidden = [
        'metadata',
    ];

    protected $casts = [
        'coins' => 'integer',
        'bonus_coins' => 'integer',
        'total_coins' => 'integer',
        'bonus_percentage' => 'float',
        'price' => 'decimal:2',
        'pricing_tiers' => 'array',
        'is_featured' => 'boolean',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'purchase_count' => 'integer',
        'popularity_score' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Package Tiers
    const TIER_STARTER = 'starter';
    const TIER_POPULAR = 'popular';
    const TIER_VALUE = 'value';
    const TIER_PREMIUM = 'premium';
    const TIER_ELITE = 'elite';

    // Default Packages (from CoinService)
    const PACKAGES = [
        'starter' => [
            'name' => 'Starter Pack',
            'coins' => 100,
            'price' => 4.99,
            'bonus_percentage' => 0,
        ],
        'popular' => [
            'name' => 'Popular Pack',
            'coins' => 500,
            'price' => 19.99,
            'bonus_percentage' => 15,
        ],
        'value' => [
            'name' => 'Value Pack',
            'coins' => 1000,
            'price' => 34.99,
            'bonus_percentage' => 25,
        ],
        'premium' => [
            'name' => 'Premium Pack',
            'coins' => 2500,
            'price' => 74.99,
            'bonus_percentage' => 35,
        ],
        'elite' => [
            'name' => 'Elite Pack',
            'coins' => 5000,
            'price' => 99.99,
            'bonus_percentage' => 50,
        ],
    ];

    /**
     * ===============================================
     * RELATIONSHIPS
     * ===============================================
     */

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
     * Scope for active packages
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured packages
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for popular packages
     */
    public function scopePopular(Builder $query): Builder
    {
        return $query->where('is_popular', true);
    }

    /**
     * Scope by tier
     */
    public function scopeByTier(Builder $query, string $tier): Builder
    {
        return $query->where('tier', $tier);
    }

    /**
     * Scope ordered by sort order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    /**
     * Scope by coin amount range
     */
    public function scopeByCoinRange(Builder $query, int $min, int $max): Builder
    {
        return $query->whereBetween('coins', [$min, $max]);
    }

    /**
     * ===============================================
     * ACCESSORS & MUTATORS
     * ===============================================
     */

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Get price per coin
     */
    public function getPricePerCoinAttribute(): float
    {
        return $this->total_coins > 0 ? round($this->price / $this->total_coins, 4) : 0;
    }

    /**
     * Get formatted price per coin
     */
    public function getFormattedPricePerCoinAttribute(): string
    {
        return '$' . number_format($this->price_per_coin, 4);
    }

    /**
     * Calculate and set total coins on creation/update
     */
    protected function calculateTotalCoins(): void
    {
        $this->total_coins = $this->coins + $this->bonus_coins;
    }

    /**
     * Calculate bonus coins from percentage
     */
    protected function calculateBonusCoins(): void
    {
        $this->bonus_coins = intval($this->coins * ($this->bonus_percentage / 100));
    }

    /**
     * Get value rating (1-5 stars based on price per coin)
     */
    public function getValueRatingAttribute(): int
    {
        // Lower price per coin = better value
        $pricePerCoin = $this->price_per_coin;
        
        if ($pricePerCoin <= 0.015) return 5;
        if ($pricePerCoin <= 0.025) return 4;
        if ($pricePerCoin <= 0.035) return 3;
        if ($pricePerCoin <= 0.045) return 2;
        return 1;
    }

    /**
     * Get savings compared to base package
     */
    public function getSavingsPercentageAttribute(): int
    {
        $basePackage = self::byTier(self::TIER_STARTER)->first();
        if (!$basePackage) return 0;

        $basePricePerCoin = $basePackage->price_per_coin;
        $thisPricePerCoin = $this->price_per_coin;

        if ($basePricePerCoin == 0) return 0;

        return intval((($basePricePerCoin - $thisPricePerCoin) / $basePricePerCoin) * 100);
    }

    /**
     * ===============================================
     * BUSINESS LOGIC METHODS
     * ===============================================
     */

    /**
     * Purchase this coin package for user
     */
    public function purchaseFor(User $user, ?string $paymentMethodId = null): bool
    {
        try {
            DB::beginTransaction();

            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'type' => Order::TYPE_COINS,
                'status' => Order::STATUS_PENDING,
                'total' => $this->price,
            ]);

            // Add order item
            $order->addItem($this->product, 1, [
                'coins' => $this->coins,
                'bonus_coins' => $this->bonus_coins,
                'total_coins' => $this->total_coins,
            ]);

            // Process payment
            $payment = Payment::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'payment_method_id' => $paymentMethodId,
                'amount' => $this->price,
                'gateway' => 'stripe',
                'status' => Payment::STATUS_PENDING,
            ]);

            if (!$payment->authorize() || !$payment->capture()) {
                throw new \Exception('Payment failed');
            }

            // Fulfill order (add coins to user's wallet)
            $user->wallet->addCoins($this->total_coins, 'purchase', [
                'package_id' => $this->id,
                'order_id' => $order->id,
            ]);

            $order->fulfill();
            $order->complete();

            // Update package stats
            $this->increment('purchase_count');
            $this->increment('popularity_score', 10);

            DB::commit();

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Coin package purchase failed', [
                'package_id' => $this->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get best value package
     */
    public static function getBestValue(): ?self
    {
        return self::active()
            ->get()
            ->sortBy('price_per_coin')
            ->first();
    }

    /**
     * Get recommended package for amount
     */
    public static function getRecommendedFor(int $coinsNeeded): ?self
    {
        return self::active()
            ->where('total_coins', '>=', $coinsNeeded)
            ->orderBy('total_coins')
            ->first();
    }

    /**
     * Get package comparison
     */
    public static function getComparison(): array
    {
        $packages = self::active()->ordered()->get();

        return $packages->map(function($package) {
            return [
                'id' => $package->id,
                'name' => $package->name,
                'tier' => $package->tier,
                'coins' => $package->coins,
                'bonus_coins' => $package->bonus_coins,
                'total_coins' => $package->total_coins,
                'bonus_percentage' => $package->bonus_percentage . '%',
                'price' => $package->formatted_price,
                'price_per_coin' => $package->formatted_price_per_coin,
                'value_rating' => $package->value_rating,
                'savings' => $package->savings_percentage . '%',
                'is_popular' => $package->is_popular,
                'is_featured' => $package->is_featured,
            ];
        })->toArray();
    }

    /**
     * Get package analytics
     */
    public static function getAnalytics(int $days = 30): array
    {
        $packages = self::all();

        return [
            'total_packages' => $packages->count(),
            'active_packages' => $packages->where('is_active', true)->count(),
            'total_purchases' => $packages->sum('purchase_count'),
            'most_popular' => $packages->sortByDesc('purchase_count')->first(),
            'best_value' => self::getBestValue(),
            'total_revenue' => $packages->sum(fn($p) => $p->price * $p->purchase_count),
            'avg_package_price' => $packages->avg('price'),
            'avg_coins_per_purchase' => $packages->avg('total_coins'),
            'by_tier' => $packages->groupBy('tier')->map(function($tierPackages) {
                return [
                    'count' => $tierPackages->count(),
                    'purchases' => $tierPackages->sum('purchase_count'),
                    'revenue' => $tierPackages->sum(fn($p) => $p->price * $p->purchase_count),
                ];
            }),
        ];
    }

    /**
     * Seed default packages
     */
    public static function seedDefaults(): void
    {
        $sortOrder = 1;
        
        foreach (self::PACKAGES as $tier => $data) {
            $bonusCoins = intval($data['coins'] * ($data['bonus_percentage'] / 100));
            $totalCoins = $data['coins'] + $bonusCoins;

            self::updateOrCreate(
                ['tier' => $tier],
                [
                    'name' => $data['name'],
                    'slug' => \Illuminate\Support\Str::slug($data['name']),
                    'coins' => $data['coins'],
                    'bonus_coins' => $bonusCoins,
                    'total_coins' => $totalCoins,
                    'bonus_percentage' => $data['bonus_percentage'],
                    'price' => $data['price'],
                    'currency' => 'USD',
                    'description' => "{$totalCoins} coins for {$data['price']}",
                    'is_active' => true,
                    'is_popular' => $tier === self::TIER_POPULAR,
                    'is_featured' => $tier === self::TIER_ELITE,
                    'sort_order' => $sortOrder++,
                ]
            );
        }
    }

    /**
     * ===============================================
     * MODEL EVENTS
     * ===============================================
     */

    protected static function booted(): void
    {
        // Calculate totals on creation
        static::creating(function (Coin $package) {
            if (empty($package->slug)) {
                $package->slug = \Illuminate\Support\Str::slug($package->name);
            }

            // Calculate bonus coins from percentage if not set
            if ($package->bonus_coins === null && $package->bonus_percentage > 0) {
                $package->calculateBonusCoins();
            }

            // Calculate total coins
            $package->calculateTotalCoins();

            // Set defaults
            $package->is_active = $package->is_active ?? true;
            $package->is_featured = $package->is_featured ?? false;
            $package->is_popular = $package->is_popular ?? false;
            $package->purchase_count = $package->purchase_count ?? 0;
            $package->popularity_score = $package->popularity_score ?? 0;
            $package->currency = $package->currency ?? 'USD';
        });

        // Recalculate totals on update
        static::updating(function (Coin $package) {
            if ($package->isDirty('coins') || $package->isDirty('bonus_percentage')) {
                $package->calculateBonusCoins();
                $package->calculateTotalCoins();
            }
        });
    }
}