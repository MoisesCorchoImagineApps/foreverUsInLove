<?php

declare(strict_types=1);

namespace App\Models\Matching;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Discovery Model
 * 
 * Representa una sesión de descubrimiento de perfiles.
 * Gestiona 8 modos de discovery, límites de tiempo y tarjetas por sesión.
 * 
 * @property string $session_id UUID primary key
 * @property string $user_id User who started the session
 * @property string $mode Discovery mode: standard, explore, boost, local, global, interest, second_chance, trending
 * @property string $status Status: active, paused, completed, expired
 * @property Carbon $started_at Session start time
 * @property Carbon|null $ended_at Session end time
 * @property int $duration_seconds Total session duration
 * @property int $max_duration_seconds Max allowed duration (free: 1800s, premium: 3600s)
 * @property int $cards_shown Total cards shown
 * @property int $max_cards Max cards allowed (free: 20, premium: 50)
 * @property int $cards_liked Cards that were liked
 * @property int $cards_disliked Cards that were disliked
 * @property int $cards_super_liked Cards that were super liked
 * @property int $cards_skipped Cards that were skipped
 * @property int $matches_created Matches created during session
 * @property array|null $filter_criteria JSON: Applied filters
 * @property array|null $algorithm_params JSON: Algorithm parameters
 * @property array|null $metadata JSON: Additional metadata
 * @property bool $is_premium Whether session is premium
 * @property string|null $boost_id Associated boost ID if applicable
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property-read User $user Session owner
 * @property-read \Illuminate\Support\Collection $cards Discovery cards shown
 * @property-read \Illuminate\Support\Collection $likes Likes from this session
 * @property-read \Illuminate\Support\Collection $views Profile views from this session
 */
class Discovery extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'discovery_sessions';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'session_id';

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
        'session_id',
        'user_id',
        'mode',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'max_duration_seconds',
        'cards_shown',
        'max_cards',
        'cards_liked',
        'cards_disliked',
        'cards_super_liked',
        'cards_skipped',
        'matches_created',
        'filter_criteria',
        'algorithm_params',
        'metadata',
        'is_premium',
        'boost_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'max_duration_seconds' => 'integer',
        'cards_shown' => 'integer',
        'max_cards' => 'integer',
        'cards_liked' => 'integer',
        'cards_disliked' => 'integer',
        'cards_super_liked' => 'integer',
        'cards_skipped' => 'integer',
        'matches_created' => 'integer',
        'filter_criteria' => 'array',
        'algorithm_params' => 'array',
        'metadata' => 'array',
        'is_premium' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Discovery mode constants
     */
    public const MODE_STANDARD = 'standard';
    public const MODE_EXPLORE = 'explore';
    public const MODE_BOOST = 'boost';
    public const MODE_LOCAL = 'local';
    public const MODE_GLOBAL = 'global';
    public const MODE_INTEREST = 'interest';
    public const MODE_SECOND_CHANCE = 'second_chance';
    public const MODE_TRENDING = 'trending';

    /**
     * Session status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Time limits in seconds
     */
    public const MAX_DURATION_FREE = 1800; // 30 minutes
    public const MAX_DURATION_PREMIUM = 3600; // 60 minutes

    /**
     * Card limits
     */
    public const MAX_CARDS_FREE = 20;
    public const MAX_CARDS_PREMIUM = 50;

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Discovery $session) {
            if (empty($session->session_id)) {
                $session->session_id = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($session->started_at)) {
                $session->started_at = now();
            }
            if (empty($session->status)) {
                $session->status = self::STATUS_ACTIVE;
            }
            if (!isset($session->duration_seconds)) {
                $session->duration_seconds = 0;
            }
            if (!isset($session->cards_shown)) {
                $session->cards_shown = 0;
            }
            
            // Set limits based on premium status
            if (!isset($session->max_duration_seconds)) {
                $session->max_duration_seconds = $session->is_premium 
                    ? self::MAX_DURATION_PREMIUM 
                    : self::MAX_DURATION_FREE;
            }
            if (!isset($session->max_cards)) {
                $session->max_cards = $session->is_premium 
                    ? self::MAX_CARDS_PREMIUM 
                    : self::MAX_CARDS_FREE;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user who owns this session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the likes from this session.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class, 'session_id', 'session_id');
    }

    /**
     * Get the profile views from this session.
     */
    public function views(): HasMany
    {
        return $this->hasMany(View::class, 'session_id', 'session_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to get active sessions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get completed sessions.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to get paused sessions.
     */
    public function scopePaused(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAUSED);
    }

    /**
     * Scope to get expired sessions.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * Scope to get sessions by mode.
     */
    public function scopeByMode(Builder $query, string $mode): Builder
    {
        return $query->where('mode', $mode);
    }

    /**
     * Scope to get premium sessions.
     */
    public function scopePremium(Builder $query): Builder
    {
        return $query->where('is_premium', true);
    }

    /**
     * Scope to get sessions for a user.
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get today's sessions.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('started_at', today());
    }

    /**
     * Scope to get recent sessions.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Get engagement rate (actions / cards shown).
     */
    public function getEngagementRateAttribute(): float
    {
        if ($this->cards_shown === 0) {
            return 0.0;
        }

        $totalActions = $this->cards_liked + $this->cards_disliked + $this->cards_super_liked;
        
        return round(($totalActions / $this->cards_shown) * 100, 2);
    }

    /**
     * Get like rate (likes / cards shown).
     */
    public function getLikeRateAttribute(): float
    {
        if ($this->cards_shown === 0) {
            return 0.0;
        }

        return round(($this->cards_liked / $this->cards_shown) * 100, 2);
    }

    /**
     * Get match rate (matches / likes).
     */
    public function getMatchRateAttribute(): float
    {
        $totalLikes = $this->cards_liked + $this->cards_super_liked;
        
        if ($totalLikes === 0) {
            return 0.0;
        }

        return round(($this->matches_created / $totalLikes) * 100, 2);
    }

    /**
     * Get remaining time in seconds.
     */
    public function getRemainingTimeAttribute(): int
    {
        return max(0, $this->max_duration_seconds - $this->duration_seconds);
    }

    /**
     * Get remaining cards.
     */
    public function getRemainingCardsAttribute(): int
    {
        return max(0, $this->max_cards - $this->cards_shown);
    }

    /**
     * Check if session has time remaining.
     */
    public function getHasTimeRemainingAttribute(): bool
    {
        return $this->remaining_time > 0;
    }

    /**
     * Check if session has cards remaining.
     */
    public function getHasCardsRemainingAttribute(): bool
    {
        return $this->remaining_cards > 0;
    }

    /**
     * Check if session is currently active.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Get session duration in minutes.
     */
    public function getDurationMinutesAttribute(): int
    {
        return (int) ceil($this->duration_seconds / 60);
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if session is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if session is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if session is paused.
     */
    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    /**
     * Check if session is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Start the session.
     */
    public function start(): bool
    {
        $this->status = self::STATUS_ACTIVE;
        $this->started_at = now();

        return $this->save();
    }

    /**
     * Pause the session.
     */
    public function pause(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $this->status = self::STATUS_PAUSED;

        return $this->save();
    }

    /**
     * Resume the session.
     */
    public function resume(): bool
    {
        if (!$this->isPaused()) {
            return false;
        }

        $this->status = self::STATUS_ACTIVE;

        return $this->save();
    }

    /**
     * Complete the session.
     */
    public function complete(): bool
    {
        $this->status = self::STATUS_COMPLETED;
        $this->ended_at = now();
        
        if ($this->started_at) {
            $this->duration_seconds = (int) $this->started_at->diffInSeconds($this->ended_at);
        }

        return $this->save();
    }

    /**
     * Expire the session.
     */
    public function expire(): bool
    {
        $this->status = self::STATUS_EXPIRED;
        $this->ended_at = now();

        return $this->save();
    }

    /**
     * Increment card counter.
     */
    public function incrementCards(): bool
    {
        $this->cards_shown++;

        // Auto-complete if max cards reached
        if ($this->cards_shown >= $this->max_cards) {
            return $this->complete();
        }

        return $this->save();
    }

    /**
     * Record a like action.
     */
    public function recordLike(bool $isSuperLike = false): bool
    {
        if ($isSuperLike) {
            $this->cards_super_liked++;
        } else {
            $this->cards_liked++;
        }

        return $this->save();
    }

    /**
     * Record a dislike action.
     */
    public function recordDislike(): bool
    {
        $this->cards_disliked++;

        return $this->save();
    }

    /**
     * Record a skip action.
     */
    public function recordSkip(): bool
    {
        $this->cards_skipped++;

        return $this->save();
    }

    /**
     * Record a match creation.
     */
    public function recordMatch(): bool
    {
        $this->matches_created++;

        return $this->save();
    }

    /**
     * Update session duration.
     */
    public function updateDuration(): bool
    {
        if (!$this->started_at) {
            return false;
        }

        $this->duration_seconds = (int) $this->started_at->diffInSeconds(now());

        // Auto-expire if max duration reached
        if ($this->duration_seconds >= $this->max_duration_seconds) {
            return $this->expire();
        }

        return $this->save();
    }

    /**
     * Check if can show more cards.
     */
    public function canShowMoreCards(): bool
    {
        return $this->isActive() 
            && $this->cards_shown < $this->max_cards 
            && $this->duration_seconds < $this->max_duration_seconds;
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
     * Get algorithm parameter.
     */
    public function getAlgorithmParam(string $key, $default = null)
    {
        return $this->algorithm_params[$key] ?? $default;
    }

    /**
     * Get filter criterion.
     */
    public function getFilterCriterion(string $key, $default = null)
    {
        return $this->filter_criteria[$key] ?? $default;
    }

    /*
    |--------------------------------------------------------------------------
    | Static Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get user's active session.
     */
    public static function getActiveForUser(string $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('status', self::STATUS_ACTIVE)
            ->first();
    }

    /**
     * Get today's session count for user.
     */
    public static function getTodayCountForUser(string $userId): int
    {
        return static::where('user_id', $userId)
            ->whereDate('started_at', today())
            ->count();
    }

    /**
     * Create new discovery session.
     */
    public static function createSession(
        string $userId,
        string $mode = self::MODE_STANDARD,
        bool $isPremium = false,
        array $filterCriteria = [],
        array $algorithmParams = []
    ): self {
        return static::create([
            'session_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $userId,
            'mode' => $mode,
            'status' => self::STATUS_ACTIVE,
            'started_at' => now(),
            'duration_seconds' => 0,
            'max_duration_seconds' => $isPremium ? self::MAX_DURATION_PREMIUM : self::MAX_DURATION_FREE,
            'cards_shown' => 0,
            'max_cards' => $isPremium ? self::MAX_CARDS_PREMIUM : self::MAX_CARDS_FREE,
            'cards_liked' => 0,
            'cards_disliked' => 0,
            'cards_super_liked' => 0,
            'cards_skipped' => 0,
            'matches_created' => 0,
            'filter_criteria' => $filterCriteria,
            'algorithm_params' => $algorithmParams,
            'is_premium' => $isPremium,
        ]);
    }

    /**
     * Get all valid modes.
     */
    public static function getValidModes(): array
    {
        return [
            self::MODE_STANDARD,
            self::MODE_EXPLORE,
            self::MODE_BOOST,
            self::MODE_LOCAL,
            self::MODE_GLOBAL,
            self::MODE_INTEREST,
            self::MODE_SECOND_CHANCE,
            self::MODE_TRENDING,
        ];
    }

    /**
     * Get all valid statuses.
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_PAUSED,
            self::STATUS_COMPLETED,
            self::STATUS_EXPIRED,
        ];
    }

    /**
     * Get premium modes.
     */
    public static function getPremiumModes(): array
    {
        return [
            self::MODE_BOOST,
            self::MODE_GLOBAL,
            self::MODE_TRENDING,
        ];
    }
}