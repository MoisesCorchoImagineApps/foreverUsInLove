<?php

declare(strict_types=1);

namespace App\Models\Matching;

use App\Models\User\User;
use App\Events\Matching\UserLiked;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Like Model
 * 
 * Representa likes, dislikes y super likes entre usuarios.
 * Gestiona la detección de matches mutuos, límites diarios y funcionalidad de undo.
 * 
 * @property string $like_id UUID primary key
 * @property string $liker_id User who performed the action
 * @property string $liked_id User who received the action
 * @property string $type Type: like, dislike, super_like, premium_like
 * @property string $source Source: discovery, search, recommendations, boost, event
 * @property string|null $message Optional message (super likes)
 * @property bool $is_mutual Whether this resulted in mutual like
 * @property string|null $match_id Match ID if mutual
 * @property bool $is_undoable Whether action can be undone
 * @property Carbon|null $undo_expires_at Expiration for undo (10 min window)
 * @property bool $notification_sent Notification sent flag
 * @property string|null $notification_type Type: immediate, batched, daily_digest, push
 * @property array|null $metadata JSON: swipe_direction, time_on_profile, from_feature
 * @property string|null $session_id Discovery session ID
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * 
 * @property-read User $liker User who liked
 * @property-read User $liked User who was liked
 * @property-read Match|null $match Match if mutual
 */
class Like extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'likes';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'like_id';

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
        'like_id',
        'liker_id',
        'liked_id',
        'type',
        'source',
        'message',
        'is_mutual',
        'match_id',
        'is_undoable',
        'undo_expires_at',
        'notification_sent',
        'notification_type',
        'metadata',
        'session_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_mutual' => 'boolean',
        'is_undoable' => 'boolean',
        'undo_expires_at' => 'datetime',
        'notification_sent' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * The event map for the model.
     */
    protected $dispatchesEvents = [
        'created' => UserLiked::class,
    ];

    /**
     * Like type constants
     */
    public const TYPE_LIKE = 'like';
    public const TYPE_DISLIKE = 'dislike';
    public const TYPE_SUPER_LIKE = 'super_like';
    public const TYPE_PREMIUM_LIKE = 'premium_like';

    /**
     * Like source constants
     */
    public const SOURCE_DISCOVERY = 'discovery';
    public const SOURCE_SEARCH = 'search';
    public const SOURCE_RECOMMENDATIONS = 'recommendations';
    public const SOURCE_BOOST = 'boost';
    public const SOURCE_EVENT = 'event';
    public const SOURCE_PROFILE = 'profile';

    /**
     * Notification type constants
     */
    public const NOTIFICATION_IMMEDIATE = 'immediate';
    public const NOTIFICATION_BATCHED = 'batched';
    public const NOTIFICATION_DAILY_DIGEST = 'daily_digest';
    public const NOTIFICATION_PUSH = 'push';

    /**
     * Undo window in minutes
     */
    public const UNDO_WINDOW_MINUTES = 10;

    /**
     * Daily limits
     */
    public const DAILY_LIMIT_FREE = 100;
    public const DAILY_LIMIT_PREMIUM = 500;
    public const DAILY_LIMIT_SUPER_LIKE_FREE = 5;
    public const DAILY_LIMIT_SUPER_LIKE_PREMIUM = 20;

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Like $like) {
            if (empty($like->like_id)) {
                $like->like_id = (string) \Illuminate\Support\Str::uuid();
            }

            // Set undo expiration for likes (not dislikes)
            if ($like->type === self::TYPE_LIKE || $like->type === self::TYPE_SUPER_LIKE) {
                $like->is_undoable = true;
                $like->undo_expires_at = now()->addMinutes(self::UNDO_WINDOW_MINUTES);
            } else {
                $like->is_undoable = false;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user who performed the like/dislike.
     */
    public function liker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liker_id', 'user_id');
    }

    /**
     * Get the user who received the like/dislike.
     */
    public function liked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liked_id', 'user_id');
    }

    /**
     * Get the match if this like resulted in a mutual match.
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(Match::class, 'match_id', 'match_id');
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
     * Scope to get likes only.
     */
    public function scopeLikes(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_LIKE);
    }

    /**
     * Scope to get dislikes only.
     */
    public function scopeDislikes(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_DISLIKE);
    }

    /**
     * Scope to get super likes only.
     */
    public function scopeSuperLikes(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SUPER_LIKE);
    }

    /**
     * Scope to get premium likes only.
     */
    public function scopePremiumLikes(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PREMIUM_LIKE);
    }

    /**
     * Scope to get mutual likes.
     */
    public function scopeMutual(Builder $query): Builder
    {
        return $query->where('is_mutual', true);
    }

    /**
     * Scope to get non-mutual likes.
     */
    public function scopeNonMutual(Builder $query): Builder
    {
        return $query->where('is_mutual', false);
    }

    /**
     * Scope to get likes given by a user.
     */
    public function scopeGivenBy(Builder $query, string $userId): Builder
    {
        return $query->where('liker_id', $userId);
    }

    /**
     * Scope to get likes received by a user.
     */
    public function scopeReceivedBy(Builder $query, string $userId): Builder
    {
        return $query->where('liked_id', $userId);
    }

    /**
     * Scope to get undoable likes.
     */
    public function scopeUndoable(Builder $query): Builder
    {
        return $query->where('is_undoable', true)
            ->where('undo_expires_at', '>', now());
    }

    /**
     * Scope to get likes by source.
     */
    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * Scope to get new likes (within last 24 hours).
     */
    public function scopeNew(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subDay());
    }

    /**
     * Scope to get today's likes for a user.
     */
    public function scopeTodayFor(Builder $query, string $userId): Builder
    {
        return $query->where('liker_id', $userId)
            ->whereDate('created_at', today());
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this is a like (not dislike).
     */
    public function getIsLikeAttribute(): bool
    {
        return $this->type === self::TYPE_LIKE || $this->type === self::TYPE_SUPER_LIKE || $this->type === self::TYPE_PREMIUM_LIKE;
    }

    /**
     * Check if this is a dislike.
     */
    public function getIsDislikeAttribute(): bool
    {
        return $this->type === self::TYPE_DISLIKE;
    }

    /**
     * Check if this is a super like.
     */
    public function getIsSuperLikeAttribute(): bool
    {
        return $this->type === self::TYPE_SUPER_LIKE;
    }

    /**
     * Check if this is a premium like.
     */
    public function getIsPremiumLikeAttribute(): bool
    {
        return $this->type === self::TYPE_PREMIUM_LIKE;
    }

    /**
     * Check if undo period is still active.
     */
    public function getCanUndoAttribute(): bool
    {
        if (!$this->is_undoable) {
            return false;
        }

        if (!$this->undo_expires_at) {
            return false;
        }

        return $this->undo_expires_at->isFuture();
    }

    /**
     * Get remaining undo time in seconds.
     */
    public function getUndoTimeRemainingAttribute(): ?int
    {
        if (!$this->can_undo) {
            return null;
        }

        return max(0, $this->undo_expires_at->diffInSeconds(now()));
    }

    /**
     * Check if like has resulted in a match.
     */
    public function getHasMatchedAttribute(): bool
    {
        return $this->is_mutual && !empty($this->match_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this is a like action.
     */
    public function isLike(): bool
    {
        return $this->is_like;
    }

    /**
     * Check if this is a dislike action.
     */
    public function isDislike(): bool
    {
        return $this->is_dislike;
    }

    /**
     * Check if this is a super like.
     */
    public function isSuperLike(): bool
    {
        return $this->is_super_like;
    }

    /**
     * Check if this is a premium like.
     */
    public function isPremiumLike(): bool
    {
        return $this->is_premium_like;
    }

    /**
     * Check if mutual match was created.
     */
    public function isMutual(): bool
    {
        return $this->is_mutual;
    }

    /**
     * Check if action can be undone.
     */
    public function canUndo(): bool
    {
        return $this->can_undo;
    }

    /**
     * Undo the like.
     */
    public function undo(): bool
    {
        if (!$this->canUndo()) {
            return false;
        }

        // If this was a mutual like, need to handle match deletion
        if ($this->is_mutual && $this->match_id) {
            $match = Match::find($this->match_id);
            if ($match) {
                $match->delete();
            }
        }

        return $this->delete();
    }

    /**
     * Mark as mutual and associate with match.
     */
    public function markAsMutual(string $matchId): bool
    {
        $this->is_mutual = true;
        $this->match_id = $matchId;

        return $this->save();
    }

    /**
     * Mark notification as sent.
     */
    public function markNotificationSent(string $type = self::NOTIFICATION_IMMEDIATE): bool
    {
        $this->notification_sent = true;
        $this->notification_type = $type;

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
     * Get swipe direction from metadata.
     */
    public function getSwipeDirection(): ?string
    {
        return $this->getMetadata('swipe_direction');
    }

    /**
     * Get time spent on profile before action.
     */
    public function getTimeOnProfile(): ?int
    {
        return $this->getMetadata('time_on_profile');
    }

    /**
     * Get the feature this like came from.
     */
    public function getFromFeature(): ?string
    {
        return $this->getMetadata('from_feature');
    }

    /*
    |--------------------------------------------------------------------------
    | Static Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if user has liked another user.
     */
    public static function hasLiked(string $likerId, string $likedId): bool
    {
        return static::where('liker_id', $likerId)
            ->where('liked_id', $likedId)
            ->whereIn('type', [self::TYPE_LIKE, self::TYPE_SUPER_LIKE, self::TYPE_PREMIUM_LIKE])
            ->exists();
    }

    /**
     * Check if mutual like exists.
     */
    public static function hasMutualLike(string $userId1, string $userId2): bool
    {
        $like1 = static::hasLiked($userId1, $userId2);
        $like2 = static::hasLiked($userId2, $userId1);

        return $like1 && $like2;
    }

    /**
     * Find like between two users.
     */
    public static function findLike(string $likerId, string $likedId): ?self
    {
        return static::where('liker_id', $likerId)
            ->where('liked_id', $likedId)
            ->whereIn('type', [self::TYPE_LIKE, self::TYPE_SUPER_LIKE, self::TYPE_PREMIUM_LIKE])
            ->first();
    }

    /**
     * Get today's like count for user.
     */
    public static function getTodayLikeCount(string $userId, bool $superLikesOnly = false): int
    {
        $query = static::where('liker_id', $userId)
            ->whereDate('created_at', today());

        if ($superLikesOnly) {
            $query->where('type', self::TYPE_SUPER_LIKE);
        } else {
            $query->whereIn('type', [self::TYPE_LIKE, self::TYPE_PREMIUM_LIKE]);
        }

        return $query->count();
    }

    /**
     * Check if user has reached daily like limit.
     */
    public static function hasReachedDailyLimit(string $userId, bool $isPremium = false): bool
    {
        $count = static::getTodayLikeCount($userId);
        $limit = $isPremium ? self::DAILY_LIMIT_PREMIUM : self::DAILY_LIMIT_FREE;

        return $count >= $limit;
    }

    /**
     * Check if user has reached daily super like limit.
     */
    public static function hasReachedSuperLikeLimit(string $userId, bool $isPremium = false): bool
    {
        $count = static::getTodayLikeCount($userId, true);
        $limit = $isPremium ? self::DAILY_LIMIT_SUPER_LIKE_PREMIUM : self::DAILY_LIMIT_SUPER_LIKE_FREE;

        return $count >= $limit;
    }

    /**
     * Get remaining likes for today.
     */
    public static function getRemainingLikes(string $userId, bool $isPremium = false): int
    {
        $count = static::getTodayLikeCount($userId);
        $limit = $isPremium ? self::DAILY_LIMIT_PREMIUM : self::DAILY_LIMIT_FREE;

        return max(0, $limit - $count);
    }

    /**
     * Get remaining super likes for today.
     */
    public static function getRemainingSuperLikes(string $userId, bool $isPremium = false): int
    {
        $count = static::getTodayLikeCount($userId, true);
        $limit = $isPremium ? self::DAILY_LIMIT_SUPER_LIKE_PREMIUM : self::DAILY_LIMIT_SUPER_LIKE_FREE;

        return max(0, $limit - $count);
    }

    /**
     * Create a like.
     */
    public static function createLike(
        string $likerId,
        string $likedId,
        string $type = self::TYPE_LIKE,
        string $source = self::SOURCE_DISCOVERY,
        ?string $message = null,
        array $metadata = []
    ): self {
        return static::create([
            'like_id' => (string) \Illuminate\Support\Str::uuid(),
            'liker_id' => $likerId,
            'liked_id' => $likedId,
            'type' => $type,
            'source' => $source,
            'message' => $message,
            'is_mutual' => false,
            'metadata' => $metadata,
            'notification_sent' => false,
        ]);
    }

    /**
     * Create a dislike.
     */
    public static function createDislike(
        string $likerId,
        string $likedId,
        string $source = self::SOURCE_DISCOVERY,
        array $metadata = []
    ): self {
        return static::create([
            'like_id' => (string) \Illuminate\Support\Str::uuid(),
            'liker_id' => $likerId,
            'liked_id' => $likedId,
            'type' => self::TYPE_DISLIKE,
            'source' => $source,
            'is_mutual' => false,
            'is_undoable' => false,
            'metadata' => $metadata,
            'notification_sent' => false,
        ]);
    }

    /**
     * Get all valid like types.
     */
    public static function getValidTypes(): array
    {
        return [
            self::TYPE_LIKE,
            self::TYPE_DISLIKE,
            self::TYPE_SUPER_LIKE,
            self::TYPE_PREMIUM_LIKE,
        ];
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
            self::SOURCE_BOOST,
            self::SOURCE_EVENT,
            self::SOURCE_PROFILE,
        ];
    }

    /**
     * Get all valid notification types.
     */
    public static function getValidNotificationTypes(): array
    {
        return [
            self::NOTIFICATION_IMMEDIATE,
            self::NOTIFICATION_BATCHED,
            self::NOTIFICATION_DAILY_DIGEST,
            self::NOTIFICATION_PUSH,
        ];
    }
}