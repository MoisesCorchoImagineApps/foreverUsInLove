<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use App\Events\Commerce\ProductViewed;
use App\Events\Commerce\ProductPurchased;

/**
 * Product Model - Polymorphic Base for Commerce Items
 * 
 * Represents purchasable items in the system (Gifts, Plans, Coin Packages)
 * Implements polymorphic relationship pattern for different product types
 * 
 * @property int $id
 * @property string $productable_type (Gift, Plan, CoinPackage)
 * @property int $productable_id
 * @property string $sku Unique product identifier
 * @property string $name
 * @property string $description
 * @property string $category (gift, subscription, coins, feature, boost)
 * @property string $type (virtual_gift, premium_plan, coin_package, etc)
 * @property decimal $price Base price in USD
 * @property string $currency Default USD
 * @property array $pricing_tiers Regional pricing
 * @property string $status (active, inactive, discontinued, coming_soon)
 * @property string $availability (always, limited, seasonal, promotional)
 * @property int $stock_quantity NULL = unlimited
 * @property int $reserved_quantity For cart reservations
 * @property bool $is_featured
 * @property bool $is_trending
 * @property bool $requires_subscription Premium products
 * @property string $visibility (public, premium, vip, elite)
 * @property int $popularity_score Based on purchases/views
 * @property int $view_count
 * @property int $purchase_count
 * @property float $rating_average
 * @property int $rating_count
 * @property array $metadata Additional product data
 * @property array $tags For search/filtering
 * @property array $features Product highlights
 * @property array $restrictions Age/region restrictions
 * @property datetime $available_from
 * @property datetime $available_until
 * @property datetime $last_purchased_at
 * @property timestamps created_at, updated_at
 * @property softDeletes deleted_at
 */
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'productable_type',
        'productable_id',
        'sku',
        'name',
        'description',
        'category',
        'type',
        'price',
        'currency',
        'pricing_tiers',
        'status',
        'availability',
        'stock_quantity',
        'reserved_quantity',
        'is_featured',
        'is_trending',
        'requires_subscription',
        'visibility',
        'popularity_score',
        'view_count',
        'purchase_count',
        'rating_average',
        'rating_count',
        'metadata',
        'tags',
        'features',
        'restrictions',
        'available_from',
        'available_until',
        'last_purchased_at',
    ];

    protected $hidden = [
        'reserved_quantity',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'pricing_tiers' => 'array',
        'is_featured' => 'boolean',
        'is_trending' => 'boolean',
        'requires_subscription' => 'boolean',
        'popularity_score' => 'integer',
        'view_count' => 'integer',
        'purchase_count' => 'integer',
        'rating_average' => 'float',
        'rating_count' => 'integer',
        'metadata' => 'array',
        'tags' => 'array',
        'features' => 'array',
        'restrictions' => 'array',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
        'last_purchased_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Categories
    const CATEGORY_GIFT = 'gift';
    const CATEGORY_SUBSCRIPTION = 'subscription';
    const CATEGORY_COINS = 'coins';
    const CATEGORY_FEATURE = 'feature';
    const CATEGORY_BOOST = 'boost';

    // Status
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_DISCONTINUED = 'discontinued';
    const STATUS_COMING_SOON = 'coming_soon';

    // Availability
    const AVAILABILITY_ALWAYS = 'always';
    const AVAILABILITY_LIMITED = 'limited';
    const AVAILABILITY_SEASONAL = 'seasonal';
    const AVAILABILITY_PROMOTIONAL = 'promotional';

    // Visibility
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_PREMIUM = 'premium';
    const VISIBILITY_VIP = 'vip';
    const VISIBILITY_ELITE = 'elite';

    /**
     * ===============================================
     * RELATIONSHIPS
     * ===============================================
     */

    /**
     * Get the owning productable model (Gift, Plan, CoinPackage)
     */
    public function productable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Orders containing this product
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Users who favorited this product
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'product_favorites')
            ->withTimestamps();
    }

    /**
     * Product reviews
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * ===============================================
     * SCOPES
     * ===============================================
     */

    /**
     * Scope for active products
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for available products (time-based)
     */
    public function scopeAvailable(Builder $query): Builder
    {
        $now = now();
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function($q) use ($now) {
                $q->whereNull('available_from')
                  ->orWhere('available_from', '<=', $now);
            })
            ->where(function($q) use ($now) {
                $q->whereNull('available_until')
                  ->orWhere('available_until', '>=', $now);
            });
    }

    /**
     * Scope for in-stock products
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function($q) {
            $q->whereNull('stock_quantity')
              ->orWhereRaw('(stock_quantity - reserved_quantity) > 0');
        });
    }

    /**
     * Scope for featured products
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for trending products
     */
    public function scopeTrending(Builder $query): Builder
    {
        return $query->where('is_trending', true)
            ->orderByDesc('popularity_score');
    }

    /**
     * Scope by category
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by visibility level
     */
    public function scopeByVisibility(Builder $query, string $visibility): Builder
    {
        return $query->where('visibility', $visibility);
    }

    /**
     * Scope for public products
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    /**
     * Scope by price range
     */
    public function scopeByPriceRange(Builder $query, float $min, float $max): Builder
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    /**
     * Scope for searching products
     */
    public function scopeSearch(Builder $query, string $searchTerm): Builder
    {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('description', 'like', "%{$searchTerm}%")
              ->orWhere('sku', 'like', "%{$searchTerm}%")
              ->orWhereJsonContains('tags', $searchTerm);
        });
    }

    /**
     * ===============================================
     * ACCESSORS & MUTATORS
     * ===============================================
     */

    /**
     * Get available stock
     */
    public function getAvailableStockAttribute(): ?int
    {
        if (is_null($this->stock_quantity)) {
            return null; // Unlimited
        }

        return max(0, $this->stock_quantity - $this->reserved_quantity);
    }

    /**
     * Check if product is in stock
     */
    public function getIsInStockAttribute(): bool
    {
        if (is_null($this->stock_quantity)) {
            return true; // Unlimited stock
        }

        return $this->available_stock > 0;
    }

    /**
     * Check if product is available now
     */
    public function getIsAvailableAttribute(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $now = now();

        if ($this->available_from && $now->lt($this->available_from)) {
            return false;
        }

        if ($this->available_until && $now->gt($this->available_until)) {
            return false;
        }

        return true;
    }

    /**
     * Get price for user's region
     */
    public function getPriceForRegion(string $region = 'US', string $currency = 'USD'): float
    {
        if (empty($this->pricing_tiers)) {
            return $this->price;
        }

        // Check for region-specific pricing
        foreach ($this->pricing_tiers as $tier) {
            if ($tier['region'] === $region && $tier['currency'] === $currency) {
                return $tier['price'];
            }
        }

        return $this->price;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Get popularity percentage (0-100)
     */
    public function getPopularityPercentageAttribute(): int
    {
        return min(100, intval(($this->popularity_score / 1000) * 100));
    }

    /**
     * ===============================================
     * BUSINESS LOGIC METHODS
     * ===============================================
     */

    /**
     * Check if product is purchasable
     */
    public function isPurchasable(): bool
    {
        return $this->is_available && $this->is_in_stock;
    }

    /**
     * Check if product requires subscription
     */
    public function requiresSubscription(): bool
    {
        return $this->requires_subscription;
    }

    /**
     * Check if user can access this product
     */
    public function canBeAccessedBy(User $user): bool
    {
        // Public products accessible to all
        if ($this->visibility === self::VISIBILITY_PUBLIC) {
            return true;
        }

        // Check subscription level
        $subscription = $user->activeSubscription;
        if (!$subscription) {
            return false;
        }

        $userTier = $subscription->plan->tier ?? 'basic';

        return match($this->visibility) {
            self::VISIBILITY_PREMIUM => in_array($userTier, ['premium', 'vip', 'elite']),
            self::VISIBILITY_VIP => in_array($userTier, ['vip', 'elite']),
            self::VISIBILITY_ELITE => $userTier === 'elite',
            default => true,
        };
    }

    /**
     * Reserve inventory for cart
     */
    public function reserveInventory(int $quantity = 1): bool
    {
        if (is_null($this->stock_quantity)) {
            return true; // Unlimited stock
        }

        if ($this->available_stock < $quantity) {
            return false;
        }

        $this->increment('reserved_quantity', $quantity);
        return true;
    }

    /**
     * Release inventory reservation
     */
    public function releaseInventory(int $quantity = 1): void
    {
        if (!is_null($this->stock_quantity)) {
            $this->decrement('reserved_quantity', $quantity);
        }
    }

    /**
     * Deduct inventory after purchase
     */
    public function deductInventory(int $quantity = 1): bool
    {
        if (is_null($this->stock_quantity)) {
            return true; // Unlimited stock
        }

        if ($this->available_stock < $quantity) {
            return false;
        }

        $this->decrement('stock_quantity', $quantity);
        $this->decrement('reserved_quantity', $quantity);
        
        return true;
    }

    /**
     * Increment view count
     */
    public function incrementViews(): void
    {
        $this->increment('view_count');
        $this->recalculatePopularity();

        event(new ProductViewed($this));
    }

    /**
     * Increment purchase count
     */
    public function incrementPurchases(): void
    {
        $this->increment('purchase_count');
        $this->update(['last_purchased_at' => now()]);
        $this->recalculatePopularity();

        event(new ProductPurchased($this));
    }

    /**
     * Recalculate popularity score
     */
    public function recalculatePopularity(): void
    {
        // Weighted formula: purchases worth more than views
        $score = ($this->purchase_count * 10) + ($this->view_count * 1);
        
        // Bonus for ratings
        if ($this->rating_count > 0) {
            $score += ($this->rating_average * $this->rating_count * 5);
        }

        // Time decay: recent purchases worth more
        if ($this->last_purchased_at) {
            $daysAgo = now()->diffInDays($this->last_purchased_at);
            $recencyBonus = max(0, 100 - $daysAgo);
            $score += $recencyBonus;
        }

        $this->update(['popularity_score' => intval($score)]);
    }

    /**
     * Update trending status
     */
    public function updateTrendingStatus(): void
    {
        // Trending if purchased recently and high popularity
        $recentPurchases = $this->orderItems()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $isTrending = $recentPurchases >= 10 && $this->popularity_score >= 500;

        $this->update(['is_trending' => $isTrending]);
    }

    /**
     * Add review and update rating
     */
    public function addReview(User $user, int $rating, ?string $comment = null): void
    {
        $this->reviews()->create([
            'user_id' => $user->id,
            'rating' => $rating,
            'comment' => $comment,
        ]);

        $this->updateRatings();
    }

    /**
     * Update average ratings
     */
    public function updateRatings(): void
    {
        $average = $this->reviews()->avg('rating');
        $count = $this->reviews()->count();

        $this->update([
            'rating_average' => round($average, 2),
            'rating_count' => $count,
        ]);

        $this->recalculatePopularity();
    }

    /**
     * Check if product matches criteria
     */
    public function matchesCriteria(array $criteria): bool
    {
        foreach ($criteria as $key => $value) {
            if ($key === 'min_price' && $this->price < $value) return false;
            if ($key === 'max_price' && $this->price > $value) return false;
            if ($key === 'category' && $this->category !== $value) return false;
            if ($key === 'tags' && !array_intersect($this->tags ?? [], (array)$value)) return false;
        }

        return true;
    }

    /**
     * Get personalized recommendations
     */
    public static function getRecommendationsFor(User $user, int $limit = 10): Collection
    {
        $cacheKey = "product_recommendations_{$user->id}";

        return Cache::remember($cacheKey, 3600, function() use ($user, $limit) {
            // Get user's purchase history
            $purchasedCategories = $user->orders()
                ->with('items.product')
                ->get()
                ->pluck('items.*.product.category')
                ->flatten()
                ->unique()
                ->toArray();

            // Recommend products in similar categories
            return self::query()
                ->available()
                ->inStock()
                ->when(!empty($purchasedCategories), function($q) use ($purchasedCategories) {
                    $q->whereIn('category', $purchasedCategories);
                })
                ->where(function($q) use ($user) {
                    // Exclude already purchased
                    $purchasedIds = $user->orders()
                        ->with('items')
                        ->get()
                        ->pluck('items.*.product_id')
                        ->flatten()
                        ->unique()
                        ->toArray();
                    
                    if (!empty($purchasedIds)) {
                        $q->whereNotIn('id', $purchasedIds);
                    }
                })
                ->orderByDesc('popularity_score')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get catalog health metrics
     */
    public static function getCatalogHealthMetrics(): array
    {
        return [
            'total_products' => self::count(),
            'active_products' => self::active()->count(),
            'available_products' => self::available()->count(),
            'in_stock_products' => self::inStock()->count(),
            'featured_products' => self::featured()->count(),
            'trending_products' => self::trending()->count(),
            'out_of_stock' => self::whereNotNull('stock_quantity')
                ->whereRaw('(stock_quantity - reserved_quantity) <= 0')
                ->count(),
            'avg_price' => self::active()->avg('price'),
            'total_revenue_potential' => self::active()->sum('price'),
            'avg_rating' => self::whereNotNull('rating_average')->avg('rating_average'),
        ];
    }

    /**
     * Clear product caches
     */
    public function clearCaches(): void
    {
        Cache::forget("product_{$this->id}");
        Cache::forget("product_sku_{$this->sku}");
        Cache::tags(['products', 'catalog'])->flush();
    }

    /**
     * Warm product cache
     */
    public static function warmCaches(): void
    {
        // Cache featured products
        Cache::remember('featured_products', 3600, function() {
            return self::featured()->available()->limit(20)->get();
        });

        // Cache trending products
        Cache::remember('trending_products', 3600, function() {
            return self::trending()->available()->limit(20)->get();
        });

        // Cache by categories
        $categories = [self::CATEGORY_GIFT, self::CATEGORY_SUBSCRIPTION, self::CATEGORY_COINS];
        foreach ($categories as $category) {
            Cache::remember("products_category_{$category}", 3600, function() use ($category) {
                return self::byCategory($category)->available()->limit(50)->get();
            });
        }
    }

    /**
     * ===============================================
     * MODEL EVENTS
     * ===============================================
     */

    protected static function booted(): void
    {
        // Generate SKU on creation if not provided
        static::creating(function (Product $product) {
            if (empty($product->sku)) {
                $product->sku = self::generateSKU($product->category);
            }

            // Initialize counters
            $product->view_count = $product->view_count ?? 0;
            $product->purchase_count = $product->purchase_count ?? 0;
            $product->reserved_quantity = $product->reserved_quantity ?? 0;
            $product->popularity_score = $product->popularity_score ?? 0;
        });

        // Clear caches on update
        static::updated(function (Product $product) {
            $product->clearCaches();
        });

        // Clear caches on delete
        static::deleted(function (Product $product) {
            $product->clearCaches();
        });
    }

    /**
     * Generate unique SKU
     */
    protected static function generateSKU(string $category): string
    {
        $prefix = strtoupper(substr($category, 0, 3));
        $random = strtoupper(substr(uniqid(), -6));
        return "{$prefix}-{$random}";
    }
}