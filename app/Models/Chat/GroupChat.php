<?php

declare(strict_types=1);

namespace App\Models\Chat;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Class GroupChat
 *
 * Modelo de chats grupales con soporte para diferentes tipos de grupos,
 * roles de miembros, moderación automática y límites por tier.
 *
 * @package App\Models\Chat
 *
 * @property int $id
 * @property int $creator_id
 * @property string $name
 * @property string|null $description
 * @property string $type // public, private, happy_hour, themed, event_based
 * @property string $status // active, inactive, archived, suspended
 * @property string|null $avatar_url
 * @property array $settings
 * @property array $metadata
 * @property array|null $theme_data
 * @property array|null $event_data
 * @property array $privacy_settings
 * @property array $moderation_settings
 * @property array|null $banned_users
 * @property array|null $muted_users
 * @property int $member_count
 * @property int $max_members
 * @property int $message_count
 * @property float $activity_score
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $event_starts_at
 * @property Carbon|null $event_ends_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read User $creator
 * @property-read Collection|User[] $members
 * @property-read Chat $chat
 */
class GroupChat extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'group_chats';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'creator_id',
        'name',
        'description',
        'type',
        'status',
        'avatar_url',
        'settings',
        'metadata',
        'theme_data',
        'event_data',
        'privacy_settings',
        'moderation_settings',
        'banned_users',
        'muted_users',
        'member_count',
        'max_members',
        'message_count',
        'activity_score',
        'last_activity_at',
        'archived_at',
        'event_starts_at',
        'event_ends_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'settings' => 'array',
        'metadata' => 'array',
        'theme_data' => 'array',
        'event_data' => 'array',
        'privacy_settings' => 'array',
        'moderation_settings' => 'array',
        'banned_users' => 'array',
        'muted_users' => 'array',
        'member_count' => 'integer',
        'max_members' => 'integer',
        'message_count' => 'integer',
        'activity_score' => 'float',
        'last_activity_at' => 'datetime',
        'archived_at' => 'datetime',
        'event_starts_at' => 'datetime',
        'event_ends_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Group types
     */
    public const TYPE_PUBLIC = 'public';
    public const TYPE_PRIVATE = 'private';
    public const TYPE_HAPPY_HOUR = 'happy_hour';
    public const TYPE_THEMED = 'themed';
    public const TYPE_EVENT_BASED = 'event_based';

    /**
     * Group status
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Member roles
     */
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_MEMBER = 'member';
    public const ROLE_GUEST = 'guest';

    /**
     * Member status
     */
    public const MEMBER_STATUS_ACTIVE = 'active';
    public const MEMBER_STATUS_INVITED = 'invited';
    public const MEMBER_STATUS_LEFT = 'left';
    public const MEMBER_STATUS_KICKED = 'kicked';
    public const MEMBER_STATUS_BANNED = 'banned';

    /**
     * Max members by tier and type
     */
    public const MAX_MEMBERS_FREE = 10;
    public const MAX_MEMBERS_PREMIUM = 50;
    public const MAX_MEMBERS_HAPPY_HOUR = 100;
    public const MAX_MEMBERS_EVENT = 200;

    /**
     * Max groups per user by tier
     */
    public const MAX_GROUPS_PER_USER_FREE = 20;
    public const MAX_GROUPS_PER_USER_PREMIUM = 100;

    /**
     * Themes
     */
    public const THEMES = [
        'casual_chat',
        'hobby_lovers',
        'foodies',
        'travelers',
        'fitness',
        'movies_tv',
        'music',
        'books',
        'games',
        'professionals',
        'artists',
        'entrepreneurs',
    ];

    /**
     * Default settings structure
     */
    public const DEFAULT_SETTINGS = [
        'allow_member_invites' => true,
        'require_approval' => false,
        'allow_media' => true,
        'allow_voice' => true,
        'allow_files' => true,
        'auto_moderation' => false,
        'notification_level' => 'all', // all, mentions, none
    ];

    /**
     * Default privacy settings
     */
    public const DEFAULT_PRIVACY_SETTINGS = [
        'is_searchable' => true,
        'show_member_list' => true,
        'allow_join_requests' => true,
        'require_invitation' => false,
    ];

    /**
     * Default moderation settings
     */
    public const DEFAULT_MODERATION_SETTINGS = [
        'content_filtering' => true,
        'spam_protection' => true,
        'profanity_filter' => false,
        'auto_ban_threshold' => 3,
        'mute_duration_minutes' => 60,
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (GroupChat $groupChat) {
            if (empty($groupChat->settings)) {
                $groupChat->settings = self::DEFAULT_SETTINGS;
            }
            if (empty($groupChat->privacy_settings)) {
                $groupChat->privacy_settings = self::DEFAULT_PRIVACY_SETTINGS;
            }
            if (empty($groupChat->moderation_settings)) {
                $groupChat->moderation_settings = self::DEFAULT_MODERATION_SETTINGS;
            }
            if (empty($groupChat->metadata)) {
                $groupChat->metadata = [
                    'created_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ];
            }

            // Set max members based on type
            if (!$groupChat->max_members) {
                $groupChat->max_members = match($groupChat->type) {
                    self::TYPE_HAPPY_HOUR => self::MAX_MEMBERS_HAPPY_HOUR,
                    self::TYPE_EVENT_BASED => self::MAX_MEMBERS_EVENT,
                    default => self::MAX_MEMBERS_FREE,
                };
            }
        });

        static::created(function (GroupChat $groupChat) {
            // Create associated Chat model
            $chat = Chat::create([
                'creator_id' => $groupChat->creator_id,
                'type' => Chat::TYPE_GROUP,
                'status' => Chat::STATUS_ACTIVE,
                'chatable_type' => self::class,
                'chatable_id' => $groupChat->id,
                'is_encrypted' => false,
                'is_moderated' => $groupChat->moderation_settings['content_filtering'] ?? true,
            ]);

            // Add creator as owner
            $groupChat->addMember($groupChat->creator_id, [
                'role' => self::ROLE_OWNER,
                'status' => self::MEMBER_STATUS_ACTIVE,
                'joined_at' => now(),
            ]);
        });

        static::updated(function (GroupChat $groupChat) {
            $groupChat->clearCache();
        });

        static::deleted(function (GroupChat $groupChat) {
            // Delete associated chat
            $groupChat->chat?->delete();
            $groupChat->clearCache();
        });
    }

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Get the creator of the group
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get all members of the group
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_chat_members', 'group_chat_id', 'user_id')
            ->withPivot([
                'role',
                'status',
                'joined_at',
                'left_at',
                'invited_by_user_id',
                'kicked_by_user_id',
                'kick_reason',
                'last_read_at',
                'unread_count',
                'is_muted',
                'mute_expires_at',
                'settings',
            ])
            ->withTimestamps();
    }

    /**
     * Get the associated Chat model (polymorphic)
     */
    public function chat(): MorphOne
    {
        return $this->morphOne(Chat::class, 'chatable');
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope for active groups
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for public groups
     */
    public function scopePublic($query)
    {
        return $query->where('type', self::TYPE_PUBLIC);
    }

    /**
     * Scope for private groups
     */
    public function scopePrivate($query)
    {
        return $query->where('type', self::TYPE_PRIVATE);
    }

    /**
     * Scope for happy hour groups
     */
    public function scopeHappyHour($query)
    {
        return $query->where('type', self::TYPE_HAPPY_HOUR);
    }

    /**
     * Scope for themed groups
     */
    public function scopeThemed($query)
    {
        return $query->where('type', self::TYPE_THEMED);
    }

    /**
     * Scope for event-based groups
     */
    public function scopeEventBased($query)
    {
        return $query->where('type', self::TYPE_EVENT_BASED);
    }

    /**
     * Scope for searchable groups
     */
    public function scopeSearchable($query)
    {
        return $query->whereJsonContains('privacy_settings->is_searchable', true);
    }

    /**
     * Scope for groups by theme
     */
    public function scopeByTheme($query, string $theme)
    {
        return $query->whereJsonContains('theme_data->theme', $theme);
    }

    /**
     * Scope for groups with available slots
     */
    public function scopeWithAvailableSlots($query)
    {
        return $query->whereRaw('member_count < max_members');
    }

    /**
     * Scope for recently active groups
     */
    public function scopeRecentlyActive($query, int $hours = 24)
    {
        return $query->where('last_activity_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for upcoming events
     */
    public function scopeUpcomingEvents($query)
    {
        return $query->where('type', self::TYPE_EVENT_BASED)
            ->where('event_starts_at', '>', now())
            ->orderBy('event_starts_at');
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if group is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if group is public
     */
    public function isPublic(): bool
    {
        return $this->type === self::TYPE_PUBLIC;
    }

    /**
     * Check if group is private
     */
    public function isPrivate(): bool
    {
        return $this->type === self::TYPE_PRIVATE;
    }

    /**
     * Check if group is happy hour
     */
    public function isHappyHour(): bool
    {
        return $this->type === self::TYPE_HAPPY_HOUR;
    }

    /**
     * Check if group is event-based
     */
    public function isEventBased(): bool
    {
        return $this->type === self::TYPE_EVENT_BASED;
    }

    /**
     * Check if group is full
     */
    public function isFull(): bool
    {
        return $this->member_count >= $this->max_members;
    }

    /**
     * Check if group has available slots
     */
    public function hasAvailableSlots(): bool
    {
        return $this->member_count < $this->max_members;
    }

    /**
     * Check if user is member
     */
    public function hasMember(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    /**
     * Check if user is creator
     */
    public function isCreator(int $userId): bool
    {
        return $this->creator_id === $userId;
    }

    /**
     * Check if user is owner
     */
    public function isOwner(int $userId): bool
    {
        $member = $this->members()->where('user_id', $userId)->first();
        return $member?->pivot?->role === self::ROLE_OWNER;
    }

    /**
     * Check if user is admin or owner
     */
    public function isAdminOrOwner(int $userId): bool
    {
        $member = $this->members()->where('user_id', $userId)->first();
        return in_array($member?->pivot?->role, [self::ROLE_OWNER, self::ROLE_ADMIN]);
    }

    /**
     * Check if user is moderator, admin or owner
     */
    public function canModerate(int $userId): bool
    {
        $member = $this->members()->where('user_id', $userId)->first();
        return in_array($member?->pivot?->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MODERATOR]);
    }

    /**
     * Check if user is banned
     */
    public function isBanned(int $userId): bool
    {
        return in_array($userId, $this->banned_users ?? []);
    }

    /**
     * Check if user is muted
     */
    public function isMuted(int $userId): bool
    {
        return in_array($userId, $this->muted_users ?? []);
    }

    /**
     * Get member role
     */
    public function getMemberRole(int $userId): ?string
    {
        $member = $this->members()->where('user_id', $userId)->first();
        return $member?->pivot?->role;
    }

    /**
     * Get member status
     */
    public function getMemberStatus(int $userId): ?string
    {
        $member = $this->members()->where('user_id', $userId)->first();
        return $member?->pivot?->status;
    }

    /**
     * Add member to group
     */
    public function addMember(int $userId, array $attributes = []): bool
    {
        if ($this->hasMember($userId)) {
            return false;
        }

        if ($this->isFull()) {
            return false;
        }

        if ($this->isBanned($userId)) {
            return false;
        }

        $defaults = [
            'role' => self::ROLE_MEMBER,
            'status' => self::MEMBER_STATUS_ACTIVE,
            'joined_at' => now(),
            'unread_count' => 0,
            'is_muted' => false,
        ];

        $this->members()->attach($userId, array_merge($defaults, $attributes));
        $this->increment('member_count');

        // Add to associated Chat
        $this->chat?->addParticipant($userId);

        return true;
    }

    /**
     * Remove member from group
     */
    public function removeMember(int $userId): bool
    {
        if (!$this->hasMember($userId)) {
            return false;
        }

        // Cannot remove owner
        if ($this->isOwner($userId)) {
            return false;
        }

        $this->members()->updateExistingPivot($userId, [
            'status' => self::MEMBER_STATUS_LEFT,
            'left_at' => now(),
        ]);

        $this->decrement('member_count');

        // Remove from associated Chat
        $this->chat?->removeParticipant($userId);

        return true;
    }

    /**
     * Kick member from group
     */
    public function kickMember(int $userId, int $kickedBy, string $reason = null): bool
    {
        if (!$this->hasMember($userId)) {
            return false;
        }

        // Cannot kick owner
        if ($this->isOwner($userId)) {
            return false;
        }

        $this->members()->updateExistingPivot($userId, [
            'status' => self::MEMBER_STATUS_KICKED,
            'left_at' => now(),
            'kicked_by_user_id' => $kickedBy,
            'kick_reason' => $reason,
        ]);

        $this->decrement('member_count');

        // Remove from associated Chat
        $this->chat?->removeParticipant($userId);

        return true;
    }

    /**
     * Ban user from group
     */
    public function banUser(int $userId, int $bannedBy, string $reason = null): bool
    {
        // Cannot ban owner
        if ($this->isOwner($userId)) {
            return false;
        }

        $bannedUsers = $this->banned_users ?? [];
        if (!in_array($userId, $bannedUsers)) {
            $bannedUsers[] = $userId;
        }

        // Update member status if they're a member
        if ($this->hasMember($userId)) {
            $this->members()->updateExistingPivot($userId, [
                'status' => self::MEMBER_STATUS_BANNED,
                'left_at' => now(),
                'kicked_by_user_id' => $bannedBy,
                'kick_reason' => $reason,
            ]);
            $this->decrement('member_count');
        }

        return $this->update(['banned_users' => $bannedUsers]);
    }

    /**
     * Unban user from group
     */
    public function unbanUser(int $userId): bool
    {
        $bannedUsers = $this->banned_users ?? [];
        $bannedUsers = array_values(array_diff($bannedUsers, [$userId]));

        return $this->update(['banned_users' => $bannedUsers]);
    }

    /**
     * Mute user in group
     */
    public function muteUser(int $userId, int $durationMinutes = null): bool
    {
        if (!$this->hasMember($userId)) {
            return false;
        }

        $mutedUsers = $this->muted_users ?? [];
        if (!in_array($userId, $mutedUsers)) {
            $mutedUsers[] = $userId;
        }

        $muteExpiresAt = $durationMinutes 
            ? now()->addMinutes($durationMinutes) 
            : null;

        $this->members()->updateExistingPivot($userId, [
            'is_muted' => true,
            'mute_expires_at' => $muteExpiresAt,
        ]);

        return $this->update(['muted_users' => $mutedUsers]);
    }

    /**
     * Unmute user in group
     */
    public function unmuteUser(int $userId): bool
    {
        if (!$this->hasMember($userId)) {
            return false;
        }

        $mutedUsers = $this->muted_users ?? [];
        $mutedUsers = array_values(array_diff($mutedUsers, [$userId]));

        $this->members()->updateExistingPivot($userId, [
            'is_muted' => false,
            'mute_expires_at' => null,
        ]);

        return $this->update(['muted_users' => $mutedUsers]);
    }

    /**
     * Update member role
     */
    public function updateMemberRole(int $userId, string $role): bool
    {
        if (!$this->hasMember($userId)) {
            return false;
        }

        // Cannot change owner role
        if ($this->isOwner($userId)) {
            return false;
        }

        return $this->members()->updateExistingPivot($userId, ['role' => $role]);
    }

    /**
     * Transfer ownership
     */
    public function transferOwnership(int $newOwnerId): bool
    {
        if (!$this->hasMember($newOwnerId)) {
            return false;
        }

        $currentOwnerId = $this->creator_id;

        // Update current owner to admin
        $this->members()->updateExistingPivot($currentOwnerId, [
            'role' => self::ROLE_ADMIN,
        ]);

        // Update new owner
        $this->members()->updateExistingPivot($newOwnerId, [
            'role' => self::ROLE_OWNER,
        ]);

        return $this->update(['creator_id' => $newOwnerId]);
    }

    /**
     * Update activity
     */
    public function updateActivity(): bool
    {
        $activityScore = $this->calculateActivityScore();

        return $this->update([
            'last_activity_at' => now(),
            'activity_score' => $activityScore,
        ]);
    }

    /**
     * Calculate activity score (0-100)
     */
    public function calculateActivityScore(): float
    {
        $score = 0;

        // Member count (30 points)
        $memberRatio = $this->member_count / $this->max_members;
        $score += $memberRatio * 30;

        // Message activity (40 points)
        if ($this->message_count > 0) {
            $avgMessagesPerDay = $this->message_count / max(1, $this->created_at->diffInDays(now()));
            $score += min(40, $avgMessagesPerDay * 2);
        }

        // Recent activity (30 points)
        if ($this->last_activity_at) {
            $hoursSinceActivity = $this->last_activity_at->diffInHours(now());
            if ($hoursSinceActivity < 24) {
                $score += 30;
            } elseif ($hoursSinceActivity < 72) {
                $score += 20;
            } elseif ($hoursSinceActivity < 168) {
                $score += 10;
            }
        }

        return min(100, round($score, 2));
    }

    /**
     * Get group statistics
     */
    public function getStatistics(): array
    {
        return [
            'member_count' => $this->member_count,
            'max_members' => $this->max_members,
            'message_count' => $this->message_count,
            'activity_score' => $this->activity_score,
            'available_slots' => $this->max_members - $this->member_count,
            'is_full' => $this->isFull(),
            'days_active' => $this->created_at->diffInDays(now()),
            'average_messages_per_day' => $this->getAverageMessagesPerDay(),
            'banned_user_count' => count($this->banned_users ?? []),
            'muted_user_count' => count($this->muted_users ?? []),
        ];
    }

    /**
     * Get average messages per day
     */
    public function getAverageMessagesPerDay(): float
    {
        $days = max(1, $this->created_at->diffInDays(now()));
        return round($this->message_count / $days, 2);
    }

    // ========================================
    // CACHE METHODS
    // ========================================

    /**
     * Get cache key
     */
    public function getCacheKey(string $suffix = ''): string
    {
        $key = "group_chat:{$this->id}";
        return $suffix ? "{$key}:{$suffix}" : $key;
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        Cache::forget($this->getCacheKey());
        Cache::forget($this->getCacheKey('members'));
        Cache::forget($this->getCacheKey('statistics'));
    }

    // ========================================
    // SETTINGS HELPERS
    // ========================================

    /**
     * Get setting value
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set setting value
     */
    public function setSetting(string $key, $value): bool
    {
        $settings = $this->settings;
        data_set($settings, $key, $value);
        return $this->update(['settings' => $settings]);
    }

    // ========================================
    // ARRAY / JSON SERIALIZATION
    // ========================================

    /**
     * Get the instance as an array
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Add computed attributes
        $array['is_full'] = $this->isFull();
        $array['available_slots'] = $this->max_members - $this->member_count;

        return $array;
    }
}