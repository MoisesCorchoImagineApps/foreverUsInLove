<?php

declare(strict_types=1);

namespace App\Models\Matching;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Filter Model
 * 
 * Representa filtros de búsqueda y preferencias de matching de un usuario.
 * Gestiona 8 categorías de filtros con pesos y límites premium.
 * 
 * @property string $filter_id UUID primary key
 * @property string $user_id Owner of the filter
 * @property string $name Filter name
 * @property string $category Category: basic, demographic, lifestyle, interest, relationship, premium, behavioral, custom
 * @property array $criteria JSON: Filter criteria
 * @property array $weights JSON: Filter weights (age: 1.0, distance: 0.8, interests: 0.7, lifestyle: 0.6)
 * @property bool $is_active Whether filter is active
 * @property bool $is_default Whether this is the default filter
 * @property bool $is_premium Whether this requires premium subscription
 * @property int $priority Priority order (1-10)
 * @property array|null $metadata JSON: Additional metadata
 * @property Carbon|null $last_used_at Last time filter was used
 * @property int $use_count Number of times used
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property-read User $user Owner of the filter
 */
class Filter extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'matching_filters';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'filter_id';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'filter_id',
        'user_id',
        'name',
        'category',
        'criteria',
        'weights',
        'is_active',
        'is_default',
        'is_premium',
        'priority',
        'metadata',
        'last_used_at',
        'use_count',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'criteria' => 'array',
        'weights' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_premium' => 'boolean',
        'priority' => 'integer',
        'metadata' => 'array',
        'last_used_at' => 'datetime',
        'use_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Filter category constants
     */
    public const CATEGORY_BASIC = 'basic';
    public const CATEGORY_DEMOGRAPHIC = 'demographic';
    public const CATEGORY_LIFESTYLE = 'lifestyle';
    public const CATEGORY_INTEREST = 'interest';
    public const CATEGORY_RELATIONSHIP = 'relationship';
    public const CATEGORY_PREMIUM = 'premium';
    public const CATEGORY_BEHAVIORAL = 'behavioral';
    public const CATEGORY_CUSTOM = 'custom';

    /**
     * Default filter weights
     */
    public const DEFAULT_WEIGHTS = [
        'age' => 1.0,
        'distance' => 0.8,
        'interests' => 0.7,
        'lifestyle' => 0.6,
        'personality' => 0.5,
        'relationship_goals' => 0.5,
        'education' => 0.4,
        'height' => 0.3,
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Filter $filter) {
            if (empty($filter->filter_id)) {
                $filter->filter_id = (string) \Illuminate\Support\Str::uuid();
            }
            if (!isset($filter->is_active)) {
                $filter->is_active = true;
            }
            if (!isset($filter->is_default)) {
                $filter->is_default = false;
            }
            if (!isset($filter->use_count)) {
                $filter->use_count = 0;
            }
            if (empty($filter->weights)) {
                $filter->weights = self::DEFAULT_WEIGHTS;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user who owns this filter.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to get active filters.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get inactive filters.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to get default filter.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get premium filters.
     */
    public function scopePremium(Builder $query): Builder
    {
        return $query->where('is_premium', true);
    }

    /**
     * Scope to get filters by category.
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get filters for a user.
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get recently used filters.
     */
    public function scopeRecentlyUsed(Builder $query, int $days = 7): Builder
    {
        return $query->where('last_used_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to order by priority.
     */
    public function scopeByPriority(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('priority', $direction);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Get total weight of all criteria.
     */
    public function getTotalWeightAttribute(): float
    {
        return array_sum($this->weights ?? []);
    }

    /**
     * Get number of criteria defined.
     */
    public function getCriteriaCountAttribute(): int
    {
        return count($this->criteria ?? []);
    }

    /**
     * Check if filter has been used recently.
     */
    public function getIsRecentlyUsedAttribute(): bool
    {
        if (!$this->last_used_at) {
            return false;
        }

        return $this->last_used_at->isAfter(now()->subWeek());
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if filter is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if filter is default.
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Check if filter is premium.
     */
    public function isPremium(): bool
    {
        return $this->is_premium;
    }

    /**
     * Activate the filter.
     */
    public function activate(): bool
    {
        $this->is_active = true;

        return $this->save();
    }

    /**
     * Deactivate the filter.
     */
    public function deactivate(): bool
    {
        $this->is_active = false;

        return $this->save();
    }

    /**
     * Set as default filter.
     */
    public function setAsDefault(): bool
    {
        // Deactivate other default filters for this user
        static::where('user_id', $this->user_id)
            ->where('filter_id', '!=', $this->filter_id)
            ->update(['is_default' => false]);

        $this->is_default = true;

        return $this->save();
    }

    /**
     * Mark filter as used.
     */
    public function markAsUsed(): bool
    {
        $this->use_count++;
        $this->last_used_at = now();

        return $this->save();
    }

    /**
     * Get criterion value.
     */
    public function getCriterion(string $key, $default = null)
    {
        return $this->criteria[$key] ?? $default;
    }

    /**
     * Set criterion value.
     */
    public function setCriterion(string $key, $value): bool
    {
        $criteria = $this->criteria ?? [];
        $criteria[$key] = $value;
        $this->criteria = $criteria;

        return $this->save();
    }

    /**
     * Remove criterion.
     */
    public function removeCriterion(string $key): bool
    {
        $criteria = $this->criteria ?? [];
        unset($criteria[$key]);
        $this->criteria = $criteria;

        return $this->save();
    }

    /**
     * Get weight for a criterion.
     */
    public function getWeight(string $criterion): float
    {
        return $this->weights[$criterion] ?? 0.0;
    }

    /**
     * Set weight for a criterion.
     */
    public function setWeight(string $criterion, float $weight): bool
    {
        $weights = $this->weights ?? [];
        $weights[$criterion] = $weight;
        $this->weights = $weights;

        return $this->save();
    }

    /**
     * Get metadata value.
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set metadata value.
     */
    public function setMetadata(string $key, $value): bool
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;

        return $this->save();
    }

    /**
     * Check if filter matches criteria.
     */
    public function matchesCriteria(array $profileData): bool
    {
        foreach ($this->criteria as $key => $value) {
            if (!isset($profileData[$key])) {
                continue;
            }

            // Handle different comparison types
            if (is_array($value)) {
                // Range comparison (e.g., age: [25, 35])
                if (isset($value['min']) && $profileData[$key] < $value['min']) {
                    return false;
                }
                if (isset($value['max']) && $profileData[$key] > $value['max']) {
                    return false;
                }
                
                // Array membership (e.g., interests: ['music', 'sports'])
                if (is_array($profileData[$key])) {
                    $intersection = array_intersect($value, $profileData[$key]);
                    if (empty($intersection)) {
                        return false;
                    }
                }
            } else {
                // Exact match
                if ($profileData[$key] !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Calculate match score based on filter weights.
     */
    public function calculateMatchScore(array $profileData): float
    {
        $totalScore = 0.0;
        $totalWeight = 0.0;

        foreach ($this->weights as $criterion => $weight) {
            if (!isset($this->criteria[$criterion]) || !isset($profileData[$criterion])) {
                continue;
            }

            $criterionValue = $this->criteria[$criterion];
            $profileValue = $profileData[$criterion];

            // Calculate criterion score
            $score = $this->calculateCriterionScore($criterionValue, $profileValue);
            
            $totalScore += $score * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight === 0) {
            return 0.0;
        }

        return round(($totalScore / $totalWeight) * 100, 2);
    }

    /**
     * Calculate score for a single criterion.
     */
    protected function calculateCriterionScore($criterionValue, $profileValue): float
    {
        // Exact match
        if ($criterionValue === $profileValue) {
            return 1.0;
        }

        // Range match
        if (is_array($criterionValue)) {
            if (isset($criterionValue['min']) && isset($criterionValue['max'])) {
                if ($profileValue >= $criterionValue['min'] && $profileValue <= $criterionValue['max']) {
                    return 1.0;
                }
            }
            
            // Array intersection
            if (is_array($profileValue)) {
                $intersection = array_intersect($criterionValue, $profileValue);
                $union = array_unique(array_merge($criterionValue, $profileValue));
                
                if (empty($union)) {
                    return 0.0;
                }
                
                return count($intersection) / count($union);
            }
        }

        return 0.0;
    }

    /**
     * Duplicate the filter.
     */
    public function duplicate(string $newName): self
    {
        return static::create([
            'filter_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->user_id,
            'name' => $newName,
            'category' => $this->category,
            'criteria' => $this->criteria,
            'weights' => $this->weights,
            'is_active' => true,
            'is_default' => false,
            'is_premium' => $this->is_premium,
            'priority' => $this->priority,
            'metadata' => $this->metadata,
            'use_count' => 0,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Static Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get user's default filter.
     */
    public static function getDefaultForUser(string $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get user's active filters.
     */
    public static function getActiveForUser(string $userId): \Illuminate\Support\Collection
    {
        return static::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Create default filter for user.
     */
    public static function createDefaultForUser(string $userId): self
    {
        return static::create([
            'filter_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $userId,
            'name' => 'Default Filter',
            'category' => self::CATEGORY_BASIC,
            'criteria' => [
                'age' => ['min' => 18, 'max' => 99],
                'distance' => ['max' => 50], // km
                'gender' => null, // Will be set based on user preference
            ],
            'weights' => self::DEFAULT_WEIGHTS,
            'is_active' => true,
            'is_default' => true,
            'is_premium' => false,
            'priority' => 5,
            'use_count' => 0,
        ]);
    }

    /**
     * Get all valid categories.
     */
    public static function getValidCategories(): array
    {
        return [
            self::CATEGORY_BASIC,
            self::CATEGORY_DEMOGRAPHIC,
            self::CATEGORY_LIFESTYLE,
            self::CATEGORY_INTEREST,
            self::CATEGORY_RELATIONSHIP,
            self::CATEGORY_PREMIUM,
            self::CATEGORY_BEHAVIORAL,
            self::CATEGORY_CUSTOM,
        ];
    }

    /**
     * Get premium filter categories.
     */
    public static function getPremiumCategories(): array
    {
        return [
            self::CATEGORY_PREMIUM,
            self::CATEGORY_BEHAVIORAL,
        ];
    }
}