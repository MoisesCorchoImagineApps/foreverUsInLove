<?php

declare(strict_types=1);

namespace App\Models\Matching;

use App\Models\User\User;
use App\Events\Matching\MatchCreated;
use App\Events\Matching\MatchUnmatched;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Match Model
 * 
 * Representa un match mutuo entre dos usuarios en la plataforma.
 * Gestiona la compatibilidad, estado, interacciones y ciclo de vida del match.
 * 
 * @property string $match_id UUID primary key
 * @property string $user_id_1 First user ID
 * @property string $user_id_2 Second user ID
 * @property float $compatibility_score Score 0-100
 * @property string $match_source Source: standard, premium, boost, super_like, icebreaker, algorithm, event
 * @property string $status Status: active, inactive, unmatched, expired, blocked
 * @property array $compatibility_factors JSON: age, location, interests, lifestyle, personality weights
 * @property array $match_metadata JSON: celebration_level, recommendation_reason, mutual_interests
 * @property Carbon $matched_at Timestamp when match was created
 * @property Carbon|null $last_interaction_at Last interaction timestamp
 * @property Carbon|null $unmatched_at Timestamp when unmatched
 * @property string|null $unmatched_by User who unmatched
 * @property string|null $unmatched_reason Reason: user_initiated, mutual, system, policy_violation, inactive_cleanup
 * @property int $interaction_count Total interactions
 * @property int $message_count Messages exchanged
 * @property bool $is_featured Featured match flag
 * @property bool $notification_sent Notification sent flag
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * 
 * @property-read User $user1 First user
 * @property-read User $user2 Second user
 * @property-read Collection<User> $users Both users
 * @property-read Collection $messages Messages between users
 * @property-read Collection $interactions Match interactions
 * @property-read Collection $analytics Match analytics
 */
class Match extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'matches';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'match_id';

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
        'match_id',
        'user_id_1',
        'user_id_2',
        'compatibility_score',
        'match_source',
        'status',
        'compatibility_factors',
        'match_metadata',
        'matched_at',
        'last_interaction_at',
        'unmatched_at',
        'unmatched_by',
        'unmatched_reason',
        'interaction_count',
        'message_count',
        'is_featured',
        'notification_sent',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'compatibility_score' => 'float',
        'compatibility_factors' => 'array',
        'match_metadata' => 'array',
        'matched_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'unmatched_at' => 'datetime',
        'interaction_count' => 'integer',
        'message_count' => 'integer',
        'is_featured' => 'boolean',
        'notification_sent' => 'boolean',
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
        'created' => MatchCreated::class,
    ];

    /**
     * Match source constants
     */
    public const SOURCE_STANDARD = 'standard';
    public const SOURCE_PREMIUM = 'premium';
    public const SOURCE_BOOST = 'boost';
    public const SOURCE_SUPER_LIKE = 'super_like';
    public const SOURCE_ICEBREAKER = 'icebreaker';
    public const SOURCE_ALGORITHM = 'algorithm';
    public const SOURCE_EVENT = 'event';

    /**
     * Match status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_BLOCKED = 'blocked';

    /**
     * Celebration level constants
     */
    public const CELEBRATION_SPECTACULAR = 'spectacular'; // 95-100 compatibility
    public const CELEBRATION_HIGH = 'high';               // 85-94
    public const CELEBRATION_MEDIUM = 'medium';           // 70-84
    public const CELEBRATION_STANDARD = 'standard';       // <70

    /**
     * Unmatch reason constants
     */
    public const UNMATCH_USER_INITIATED = 'user_initiated';
    public const UNMATCH_MUTUAL = 'mutual';
    public const UNMATCH_SYSTEM = 'system_initiated';
    public const UNMATCH_POLICY_VIOLATION = 'policy_violation';
    public const UNMATCH_INACTIVE_CLEANUP = 'inactive_cleanup';

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Match $match) {
            if (empty($match->match_id)) {
                $match->match_id = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($match->matched_at)) {
                $match->matched_at = now();
            }
            if (empty($match->status)) {
                $match->status = self::STATUS_ACTIVE;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the first user in the match.
     */
    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_1', 'user_id');
    }

    /**
     * Get the second user in the match.
     */
    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_2', 'user_id');
    }

    /**
     * Get both users in the match.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'match_user', 'match_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the messages for this match.
     * Relationship to Chat domain (will be implemented in Chat models).
     */
    public function messages(): HasMany
    {
        return $this->hasMany(\App\Models\Chat\Message::class, 'match_id', 'match_id');
    }

    /**
     * Get the likes that created this match.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class, 'match_id', 'match_id');
    }

    /**
     * Get the interactions for this match.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(\App\Models\Analytics\MatchInteraction::class, 'match_id', 'match_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to get active matches.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get inactive matches.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Scope to get unmatched records.
     */
    public function scopeUnmatched(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNMATCHED);
    }

    /**
     * Scope to get matches for a specific user.
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id_1', $userId)
              ->orWhere('user_id_2', $userId);
        });
    }

    /**
     * Scope to get new matches (within last 24 hours).
     */
    public function scopeNew(Builder $query): Builder
    {
        return $query->where('matched_at', '>=', now()->subDay());
    }

    /**
     * Scope to get featured matches.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to get matches by minimum compatibility score.
     */
    public function scopeByCompatibility(Builder $query, float $minScore): Builder
    {
        return $query->where('compatibility_score', '>=', $minScore);
    }

    /**
     * Scope to get matches by source.
     */
    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('match_source', $source);
    }

    /**
     * Scope to get matches with recent interaction.
     */
    public function scopeRecentlyActive(Builder $query, int $days = 7): Builder
    {
        return $query->where('last_interaction_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get stale matches (no interaction for X days).
     */
    public function scopeStale(Builder $query, int $days = 30): Builder
    {
        return $query->where(function ($q) use ($days) {
            $q->whereNull('last_interaction_at')
              ->orWhere('last_interaction_at', '<', now()->subDays($days));
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Get the celebration level based on compatibility score.
     */
    public function getCelebrationLevelAttribute(): string
    {
        return match (true) {
            $this->compatibility_score >= 95 => self::CELEBRATION_SPECTACULAR,
            $this->compatibility_score >= 85 => self::CELEBRATION_HIGH,
            $this->compatibility_score >= 70 => self::CELEBRATION_MEDIUM,
            default => self::CELEBRATION_STANDARD,
        };
    }

    /**
     * Get the age of the match in days.
     */
    public function getAgeInDaysAttribute(): int
    {
        return (int) $this->matched_at->diffInDays(now());
    }

    /**
     * Get days since last interaction.
     */
    public function getDaysSinceLastInteractionAttribute(): ?int
    {
        return $this->last_interaction_at 
            ? (int) $this->last_interaction_at->diffInDays(now())
            : null;
    }

    /**
     * Check if match is new (less than 24 hours).
     */
    public function getIsNewAttribute(): bool
    {
        return $this->matched_at->isToday() || $this->matched_at->isYesterday();
    }

    /**
     * Check if match is stale (no interaction for 30+ days).
     */
    public function getIsStaleAttribute(): bool
    {
        if (!$this->last_interaction_at) {
            return $this->age_in_days > 30;
        }
        
        return $this->days_since_last_interaction > 30;
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get the other user in the match.
     */
    public function getOtherUser(string $userId): ?User
    {
        if ($this->user_id_1 === $userId) {
            return $this->user2;
        }
        
        if ($this->user_id_2 === $userId) {
            return $this->user1;
        }
        
        return null;
    }

    /**
     * Get the other user ID.
     */
    public function getOtherUserId(string $userId): ?string
    {
        if ($this->user_id_1 === $userId) {
            return $this->user_id_2;
        }
        
        if ($this->user_id_2 === $userId) {
            return $this->user_id_1;
        }
        
        return null;
    }

    /**
     * Check if match is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if match is inactive.
     */
    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    /**
     * Check if users have been unmatched.
     */
    public function isUnmatched(): bool
    {
        return $this->status === self::STATUS_UNMATCHED;
    }

    /**
     * Check if match has expired.
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Check if user is part of this match.
     */
    public function includesUser(string $userId): bool
    {
        return $this->user_id_1 === $userId || $this->user_id_2 === $userId;
    }

    /**
     * Activate the match.
     */
    public function activate(): bool
    {
        if ($this->isActive()) {
            return true;
        }

        $this->status = self::STATUS_ACTIVE;
        $this->unmatched_at = null;
        $this->unmatched_by = null;
        $this->unmatched_reason = null;

        return $this->save();
    }

    /**
     * Deactivate the match.
     */
    public function deactivate(): bool
    {
        if ($this->isInactive()) {
            return true;
        }

        $this->status = self::STATUS_INACTIVE;

        return $this->save();
    }

    /**
     * Unmatch users.
     */
    public function unmatch(
        string $unmatchedBy,
        string $reason = self::UNMATCH_USER_INITIATED
    ): bool {
        if ($this->isUnmatched()) {
            return true;
        }

        $this->status = self::STATUS_UNMATCHED;
        $this->unmatched_at = now();
        $this->unmatched_by = $unmatchedBy;
        $this->unmatched_reason = $reason;

        $saved = $this->save();

        if ($saved) {
            // Dispatch unmatch event
            event(new MatchUnmatched(
                match: $this,
                unmatchedBy: $unmatchedBy,
                reason: $reason
            ));
        }

        return $saved;
    }

    /**
     * Mark match as expired.
     */
    public function markAsExpired(): bool
    {
        $this->status = self::STATUS_EXPIRED;
        $this->unmatched_at = now();
        $this->unmatched_reason = 'expired';

        return $this->save();
    }

    /**
     * Block the match.
     */
    public function block(): bool
    {
        $this->status = self::STATUS_BLOCKED;

        return $this->save();
    }

    /**
     * Track an interaction.
     */
    public function trackInteraction(string $interactionType = 'message'): bool
    {
        $this->interaction_count++;
        $this->last_interaction_at = now();

        if ($interactionType === 'message') {
            $this->message_count++;
        }

        return $this->save();
    }

    /**
     * Increment message count.
     */
    public function incrementMessageCount(): bool
    {
        $this->message_count++;
        $this->last_interaction_at = now();

        return $this->save();
    }

    /**
     * Mark notification as sent.
     */
    public function markNotificationSent(): bool
    {
        $this->notification_sent = true;

        return $this->save();
    }

    /**
     * Feature the match.
     */
    public function feature(): bool
    {
        $this->is_featured = true;

        return $this->save();
    }

    /**
     * Unfeature the match.
     */
    public function unfeature(): bool
    {
        $this->is_featured = false;

        return $this->save();
    }

    /**
     * Recalculate compatibility score.
     */
    public function recalculateCompatibility(): bool
    {
        // Load users with their profiles
        $user1 = $this->user1()->with('profile')->first();
        $user2 = $this->user2()->with('profile')->first();

        if (!$user1 || !$user2 || !$user1->profile || !$user2->profile) {
            return false;
        }

        // Calculate compatibility using profiles
        $score = $user1->profile->compatibilityWith($user2->profile);
        
        $this->compatibility_score = $score;

        return $this->save();
    }

    /**
     * Get compatibility factor value.
     */
    public function getCompatibilityFactor(string $factor): ?float
    {
        return $this->compatibility_factors[$factor] ?? null;
    }

    /**
     * Get match metadata value.
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->match_metadata[$key] ?? $default;
    }

    /**
     * Set match metadata value.
     */
    public function setMetadata(string $key, $value): bool
    {
        $metadata = $this->match_metadata ?? [];
        $metadata[$key] = $value;
        $this->match_metadata = $metadata;

        return $this->save();
    }

    /**
     * Get mutual interests from metadata.
     */
    public function getMutualInterests(): array
    {
        return $this->getMetadata('mutual_interests', []);
    }

    /**
     * Get recommendation reason.
     */
    public function getRecommendationReason(): ?string
    {
        return $this->getMetadata('recommendation_reason');
    }

    /**
     * Check if match has any messages.
     */
    public function hasMessages(): bool
    {
        return $this->message_count > 0;
    }

    /**
     * Check if match has recent activity.
     */
    public function hasRecentActivity(int $days = 7): bool
    {
        if (!$this->last_interaction_at) {
            return false;
        }

        return $this->last_interaction_at->isAfter(now()->subDays($days));
    }

    /**
     * Get match quality score (0-100).
     * Based on compatibility, interactions, and message exchange.
     */
    public function getQualityScore(): float
    {
        $score = $this->compatibility_score * 0.5; // 50% weight

        // Interaction quality (25% weight)
        $interactionScore = min(($this->interaction_count / 10) * 25, 25);
        $score += $interactionScore;

        // Message quality (25% weight)
        $messageScore = min(($this->message_count / 20) * 25, 25);
        $score += $messageScore;

        return round($score, 2);
    }

    /**
     * Get match engagement level.
     */
    public function getEngagementLevel(): string
    {
        $qualityScore = $this->getQualityScore();

        return match (true) {
            $qualityScore >= 80 => 'high',
            $qualityScore >= 50 => 'medium',
            default => 'low',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Static Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Find match between two users.
     */
    public static function findBetweenUsers(string $userId1, string $userId2): ?self
    {
        return static::where(function ($query) use ($userId1, $userId2) {
            $query->where('user_id_1', $userId1)->where('user_id_2', $userId2);
        })->orWhere(function ($query) use ($userId1, $userId2) {
            $query->where('user_id_1', $userId2)->where('user_id_2', $userId1);
        })->first();
    }

    /**
     * Check if match exists between users.
     */
    public static function existsBetweenUsers(string $userId1, string $userId2): bool
    {
        return static::findBetweenUsers($userId1, $userId2) !== null;
    }

    /**
     * Create match between two users.
     */
    public static function createMatch(
        string $userId1,
        string $userId2,
        float $compatibilityScore,
        string $source = self::SOURCE_STANDARD,
        array $metadata = []
    ): self {
        return static::create([
            'match_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id_1' => $userId1,
            'user_id_2' => $userId2,
            'compatibility_score' => $compatibilityScore,
            'match_source' => $source,
            'status' => self::STATUS_ACTIVE,
            'match_metadata' => $metadata,
            'matched_at' => now(),
            'interaction_count' => 0,
            'message_count' => 0,
            'is_featured' => false,
            'notification_sent' => false,
        ]);
    }

    /**
     * Get all valid match sources.
     */
    public static function getValidSources(): array
    {
        return [
            self::SOURCE_STANDARD,
            self::SOURCE_PREMIUM,
            self::SOURCE_BOOST,
            self::SOURCE_SUPER_LIKE,
            self::SOURCE_ICEBREAKER,
            self::SOURCE_ALGORITHM,
            self::SOURCE_EVENT,
        ];
    }

    /**
     * Get all valid match statuses.
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_UNMATCHED,
            self::STATUS_EXPIRED,
            self::STATUS_BLOCKED,
        ];
    }

    /**
     * Get all valid unmatch reasons.
     */
    public static function getValidUnmatchReasons(): array
    {
        return [
            self::UNMATCH_USER_INITIATED,
            self::UNMATCH_MUTUAL,
            self::UNMATCH_SYSTEM,
            self::UNMATCH_POLICY_VIOLATION,
            self::UNMATCH_INACTIVE_CLEANUP,
        ];
    }
}