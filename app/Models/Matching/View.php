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
 * View Model
 * 
 * Representa la visualización de un perfil de usuario por otro usuario.
 * Rastrea analytics de comportamiento, tiempo de visualización y conversiones.
 * 
 * @property string $view_id UUID primary key
 * @property string $viewer_id User who viewed the profile
 * @property string $viewed_id User whose profile was viewed
 * @property string $source Source: discovery, search, recommendations, profile, match_list, likes_you
 * @property int $duration_seconds Time spent viewing (seconds)
 * @property int $view_count Number of times viewed
 * @property bool $profile_completed Whether full profile was viewed
 * @property bool $photos_viewed Whether photos were viewed
 * @property int $photos_viewed_count Number of photos viewed
 * @property bool $resulted_in_like Whether view resulted in like
 * @property bool $resulted_in_super_like Whether view resulted in super like
 * @property bool $resulted_in_match Whether view resulted in match
 * @property string|null $session_id Discovery session ID
 * @property array|null $metadata JSON: scroll_depth, sections_viewed, swipe_direction
 * @property Carbon|null $last_viewed_at Last view timestamp
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property-read User $viewer User who viewed
 * @property-read User $viewed User who was viewed
 */
class View extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'profile_views';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'view_id';

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
        'view_id',
        'viewer_id',
        'viewed_id',
        'source',
        'duration_seconds',
        'view_count',
        'profile_completed',
        'photos_viewed',
        'photos_viewed_count',
        'resulted_in_like',
        'resulted_in_super_like',
        'resulted_in_match',
        'session_id',
        'metadata',
        'last_viewed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'duration_seconds' => 'integer',
        'view_count' => 'integer',
        'profile_completed' => 'boolean',
        'photos_viewed' => 'boolean',
        'photos_viewed_count' => 'integer',
        'resulted_in_like' => 'boolean',
        'resulted_in_super_like' => 'boolean',
        'resulted_in_match' => 'boolean',
        'metadata' => 'array',
        'last_viewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * View source constants
     */
    public const SOURCE_DISCOVERY = 'discovery';
    public const SOURCE_SEARCH = 'search';
    public const SOURCE_RECOMMENDATIONS = 'recommendations';
    public const SOURCE_PROFILE = 'profile';
    public const SOURCE_MATCH_LIST = 'match_list';
    public const SOURCE_LIKES_YOU = 'likes_you';
    public const SOURCE_BOOST = 'boost';
    public const SOURCE_EVENT = 'event';

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (View $view) {
            if (empty($view->view_id)) {
                $view->view_id = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($view->last_viewed_at)) {
                $view->last_viewed_at = now();
            }
            if (!isset($view->view_count)) {
                $view->view_count = 1;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user who viewed the profile.
     */
    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id', 'user_id');
    }

    /**
     * Get the user whose profile was viewed.
     */
    public function viewed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewed_id', 'user_id');
    }

    /**
     * Get the discovery session.
     */
    public function discoverySession(): BelongsTo
    {
        return $this->belongsTo(Discovery::class, 'session_id', 'session_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to get views by a specific viewer.
     */
    public function scopeByViewer(Builder $query, string $viewerId): Builder
    {
        return $query->where('viewer_id', $viewerId);
    }

    /**
     * Scope to get views of a specific profile.
     */
    public function scopeOfProfile(Builder $query, string $viewedId): Builder
    {
        return $query->where('viewed_id', $viewedId);
    }

    /**
     * Scope to get views by source.
     */
    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * Scope to get views that resulted in like.
     */
    public function scopeResultedInLike(Builder $query): Builder
    {
        return $query->where('resulted_in_like', true);
    }

    /**
     * Scope to get views that resulted in super like.
     */
    public function scopeResultedInSuperLike(Builder $query): Builder
    {
        return $query->where('resulted_in_super_like', true);
    }

    /**
     * Scope to get views that resulted in match.
     */
    public function scopeResultedInMatch(Builder $query): Builder
    {
        return $query->where('resulted_in_match', true);
    }

    /**
     * Scope to get views with completed profile viewing.
     */
    public function scopeCompletedProfile(Builder $query): Builder
    {
        return $query->where('profile_completed', true);
    }

    /**
     * Scope to get views with photos viewed.
     */
    public function scopePhotosViewed(Builder $query): Builder
    {
        return $query->where('photos_viewed', true);
    }

    /**
     * Scope to get recent views (within last 24 hours).
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->where('last_viewed_at', '>=', now()->subDay());
    }

    /**
     * Scope to get views with minimum duration.
     */
    public function scopeMinimumDuration(Builder $query, int $seconds): Builder
    {
        return $query->where('duration_seconds', '>=', $seconds);
    }

    /**
     * Scope to get multiple views (viewed more than once).
     */
    public function scopeMultipleViews(Builder $query): Builder
    {
        return $query->where('view_count', '>', 1);
    }

    /**
     * Scope to get today's views.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('last_viewed_at', today());
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Get engagement score (0-100).
     */
    public function getEngagementScoreAttribute(): float
    {
        $score = 0;

        // Duration score (40% weight, max 60 seconds)
        $score += min(($this->duration_seconds / 60) * 40, 40);

        // Profile completion (20% weight)
        if ($this->profile_completed) {
            $score += 20;
        }

        // Photos viewed (20% weight)
        if ($this->photos_viewed) {
            $score += 20;
        }

        // Multiple views (20% weight)
        if ($this->view_count > 1) {
            $score += min($this->view_count * 5, 20);
        }

        return round($score, 2);
    }

    /**
     * Get conversion rate (did view lead to action?).
     */
    public function getConversionRateAttribute(): float
    {
        if ($this->resulted_in_match) {
            return 100.0;
        }
        
        if ($this->resulted_in_super_like) {
            return 75.0;
        }
        
        if ($this->resulted_in_like) {
            return 50.0;
        }

        return 0.0;
    }

    /**
     * Check if view is recent (within 24 hours).
     */
    public function getIsRecentAttribute(): bool
    {
        return $this->last_viewed_at->isToday() || $this->last_viewed_at->isYesterday();
    }

    /**
     * Get hours since last view.
     */
    public function getHoursSinceViewAttribute(): int
    {
        return (int) $this->last_viewed_at->diffInHours(now());
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Increment view count.
     */
    public function incrementViewCount(int $durationSeconds = 0): bool
    {
        $this->view_count++;
        $this->duration_seconds += $durationSeconds;
        $this->last_viewed_at = now();

        return $this->save();
    }

    /**
     * Mark profile as completed.
     */
    public function markProfileCompleted(): bool
    {
        $this->profile_completed = true;

        return $this->save();
    }

    /**
     * Mark photos as viewed.
     */
    public function markPhotosViewed(int $photosCount = 1): bool
    {
        $this->photos_viewed = true;
        $this->photos_viewed_count += $photosCount;

        return $this->save();
    }

    /**
     * Mark as resulted in like.
     */
    public function markResultedInLike(bool $isSuperLike = false): bool
    {
        $this->resulted_in_like = true;
        
        if ($isSuperLike) {
            $this->resulted_in_super_like = true;
        }

        return $this->save();
    }

    /**
     * Mark as resulted in match.
     */
    public function markResultedInMatch(): bool
    {
        $this->resulted_in_match = true;

        return $this->save();
    }

    /**
     * Update duration.
     */
    public function updateDuration(int $seconds): bool
    {
        $this->duration_seconds += $seconds;
        $this->last_viewed_at = now();

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
     * Get scroll depth from metadata.
     */
    public function getScrollDepth(): ?int
    {
        return $this->getMetadata('scroll_depth');
    }

    /**
     * Get sections viewed from metadata.
     */
    public function getSectionsViewed(): array
    {
        return $this->getMetadata('sections_viewed', []);
    }

    /**
     * Get swipe direction from metadata.
     */
    public function getSwipeDirection(): ?string
    {
        return $this->getMetadata('swipe_direction');
    }

    /**
     * Check if view is high engagement.
     */
    public function isHighEngagement(): bool
    {
        return $this->engagement_score >= 70;
    }

    /**
     * Check if view converted to action.
     */
    public function hasConverted(): bool
    {
        return $this->resulted_in_like || $this->resulted_in_super_like || $this->resulted_in_match;
    }

    /*
    |--------------------------------------------------------------------------
    | Static Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Find or create view record.
     */
    public static function findOrCreate(
        string $viewerId,
        string $viewedId,
        string $source = self::SOURCE_DISCOVERY,
        int $durationSeconds = 0
    ): self {
        $view = static::where('viewer_id', $viewerId)
            ->where('viewed_id', $viewedId)
            ->first();

        if ($view) {
            $view->incrementViewCount($durationSeconds);
            return $view;
        }

        return static::create([
            'view_id' => (string) \Illuminate\Support\Str::uuid(),
            'viewer_id' => $viewerId,
            'viewed_id' => $viewedId,
            'source' => $source,
            'duration_seconds' => $durationSeconds,
            'view_count' => 1,
            'profile_completed' => false,
            'photos_viewed' => false,
            'photos_viewed_count' => 0,
            'resulted_in_like' => false,
            'resulted_in_super_like' => false,
            'resulted_in_match' => false,
            'last_viewed_at' => now(),
        ]);
    }

    /**
     * Check if user has viewed another user's profile.
     */
    public static function hasViewed(string $viewerId, string $viewedId): bool
    {
        return static::where('viewer_id', $viewerId)
            ->where('viewed_id', $viewedId)
            ->exists();
    }

    /**
     * Get view count between two users.
     */
    public static function getViewCount(string $viewerId, string $viewedId): int
    {
        $view = static::where('viewer_id', $viewerId)
            ->where('viewed_id', $viewedId)
            ->first();

        return $view ? $view->view_count : 0;
    }

    /**
     * Get today's view count for a user.
     */
    public static function getTodayViewCount(string $viewerId): int
    {
        return static::where('viewer_id', $viewerId)
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * Get profile views received by user.
     */
    public static function getProfileViewsReceived(string $userId, int $days = 7): int
    {
        return static::where('viewed_id', $userId)
            ->where('last_viewed_at', '>=', now()->subDays($days))
            ->sum('view_count');
    }

    /**
     * Get conversion rate for user's profile.
     */
    public static function getProfileConversionRate(string $userId): float
    {
        $totalViews = static::where('viewed_id', $userId)->sum('view_count');
        
        if ($totalViews === 0) {
            return 0.0;
        }

        $conversions = static::where('viewed_id', $userId)
            ->where(function ($query) {
                $query->where('resulted_in_like', true)
                    ->orWhere('resulted_in_super_like', true)
                    ->orWhere('resulted_in_match', true);
            })
            ->count();

        return round(($conversions / $totalViews) * 100, 2);
    }

    /**
     * Get average view duration for user's profile.
     */
    public static function getAverageViewDuration(string $userId): float
    {
        return (float) static::where('viewed_id', $userId)
            ->avg('duration_seconds') ?? 0.0;
    }

    /**
     * Record a profile view.
     */
    public static function recordView(
        string $viewerId,
        string $viewedId,
        string $source,
        int $durationSeconds = 0,
        array $metadata = []
    ): self {
        return static::findOrCreate($viewerId, $viewedId, $source, $durationSeconds);
    }

    /**
     * Get all valid sources.
     */
    public static function getValidSources(): array
    {
        return [
            self::SOURCE_DISCOVERY,
            self::SOURCE_SEARCH,
            self::SOURCE_RECOMMENDATIONS,
            self::SOURCE_PROFILE,
            self::SOURCE_MATCH_LIST,
            self::SOURCE_LIKES_YOU,
            self::SOURCE_BOOST,
            self::SOURCE_EVENT,
        ];
    }
}