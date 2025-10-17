<?php

declare(strict_types=1);

namespace App\Models\Chat;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Class Chat
 *
 * Modelo de conversaciones con soporte polymorphic para chats privados,
 * grupales y videollamadas. Implementa encriptación, moderación y cache.
 *
 * @package App\Models\Chat
 *
 * @property int $id
 * @property int $creator_id
 * @property string $type // private, group, video_call
 * @property string $status // active, archived, blocked, inactive
 * @property string|null $chatable_type // GroupChat, VideoCall, null for private
 * @property int|null $chatable_id
 * @property bool $is_encrypted
 * @property bool $is_moderated
 * @property array $settings
 * @property array $metadata
 * @property Carbon|null $last_message_at
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $blocked_at
 * @property int|null $blocked_by_user_id
 * @property string|null $blocked_reason
 * @property int $message_count
 * @property int $unread_count
 * @property int $participant_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read User $creator
 * @property-read Collection|User[] $participants
 * @property-read Collection|Message[] $messages
 * @property-read GroupChat|VideoCall|null $chatable
 * @property-read Message|null $lastMessage
 */
class Chat extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'chats';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'creator_id',
        'type',
        'status',
        'chatable_type',
        'chatable_id',
        'is_encrypted',
        'is_moderated',
        'settings',
        'metadata',
        'last_message_at',
        'last_activity_at',
        'archived_at',
        'blocked_at',
        'blocked_by_user_id',
        'blocked_reason',
        'message_count',
        'unread_count',
        'participant_count',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_encrypted' => 'boolean',
        'is_moderated' => 'boolean',
        'settings' => 'array',
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'archived_at' => 'datetime',
        'blocked_at' => 'datetime',
        'message_count' => 'integer',
        'unread_count' => 'integer',
        'participant_count' => 'integer',
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
     * Chat types
     */
    public const TYPE_PRIVATE = 'private';
    public const TYPE_GROUP = 'group';
    public const TYPE_VIDEO_CALL = 'video_call';

    /**
     * Chat status
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_INACTIVE = 'inactive';

    /**
     * Default settings structure
     */
    public const DEFAULT_SETTINGS = [
        'notifications_enabled' => true,
        'sound_enabled' => true,
        'typing_indicators' => true,
        'read_receipts' => true,
        'auto_archive_after_days' => 90,
        'message_retention_days' => 365,
        'allow_media' => true,
        'allow_voice' => true,
        'allow_files' => true,
        'max_file_size_mb' => 50,
    ];

    /**
     * Cache TTL in seconds (15 minutes)
     */
    public const CACHE_TTL = 900;

    /**
     * Session timeout in seconds (1 hour)
     */
    public const SESSION_TIMEOUT = 3600;

    /**
     * Max chats per user by tier
     */
    public const MAX_CHATS_FREE = 50;
    public const MAX_CHATS_PREMIUM = 200;

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Chat $chat) {
            if (empty($chat->settings)) {
                $chat->settings = self::DEFAULT_SETTINGS;
            }
            if (empty($chat->metadata)) {
                $chat->metadata = [
                    'created_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ];
            }
        });

        static::updated(function (Chat $chat) {
            // Clear cache when chat is updated
            $chat->clearCache();
        });

        static::deleted(function (Chat $chat) {
            // Clear cache when chat is deleted
            $chat->clearCache();
        });
    }

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Get the creator of the chat
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get all participants of the chat
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_participants', 'chat_id', 'user_id')
            ->withPivot([
                'role',
                'status',
                'joined_at',
                'left_at',
                'last_read_at',
                'unread_count',
                'is_muted',
                'is_pinned',
                'is_archived',
                'notifications_enabled',
                'settings',
            ])
            ->withTimestamps();
    }

    /**
     * Get all messages in the chat
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'chat_id');
    }

    /**
     * Get the polymorphic chatable entity (GroupChat or VideoCall)
     */
    public function chatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the last message in the chat
     */
    public function lastMessage(): HasMany
    {
        return $this->messages()->latest()->limit(1);
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope for active chats
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for archived chats
     */
    public function scopeArchived($query)
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    /**
     * Scope for blocked chats
     */
    public function scopeBlocked($query)
    {
        return $query->where('status', self::STATUS_BLOCKED);
    }

    /**
     * Scope for private chats
     */
    public function scopePrivate($query)
    {
        return $query->where('type', self::TYPE_PRIVATE);
    }

    /**
     * Scope for group chats
     */
    public function scopeGroup($query)
    {
        return $query->where('type', self::TYPE_GROUP);
    }

    /**
     * Scope for video call chats
     */
    public function scopeVideoCall($query)
    {
        return $query->where('type', self::TYPE_VIDEO_CALL);
    }

    /**
     * Scope for chats with activity in the last N hours
     */
    public function scopeRecentlyActive($query, int $hours = 24)
    {
        return $query->where('last_activity_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for chats with unread messages
     */
    public function scopeWithUnread($query)
    {
        return $query->where('unread_count', '>', 0);
    }

    /**
     * Scope for chats by participant
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    /**
     * Scope for encrypted chats
     */
    public function scopeEncrypted($query)
    {
        return $query->where('is_encrypted', true);
    }

    /**
     * Scope for moderated chats
     */
    public function scopeModerated($query)
    {
        return $query->where('is_moderated', true);
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if chat is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if chat is archived
     */
    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * Check if chat is blocked
     */
    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    /**
     * Check if chat is private
     */
    public function isPrivate(): bool
    {
        return $this->type === self::TYPE_PRIVATE;
    }

    /**
     * Check if chat is group
     */
    public function isGroup(): bool
    {
        return $this->type === self::TYPE_GROUP;
    }

    /**
     * Check if chat is video call
     */
    public function isVideoCall(): bool
    {
        return $this->type === self::TYPE_VIDEO_CALL;
    }

    /**
     * Check if user is participant
     */
    public function hasParticipant(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Check if user is creator
     */
    public function isCreator(int $userId): bool
    {
        return $this->creator_id === $userId;
    }

    /**
     * Get participant role
     */
    public function getParticipantRole(int $userId): ?string
    {
        $participant = $this->participants()->where('user_id', $userId)->first();
        return $participant?->pivot?->role;
    }

    /**
     * Get participant unread count
     */
    public function getParticipantUnreadCount(int $userId): int
    {
        $participant = $this->participants()->where('user_id', $userId)->first();
        return $participant?->pivot?->unread_count ?? 0;
    }

    /**
     * Check if chat session is active (last activity within session timeout)
     */
    public function isSessionActive(): bool
    {
        if (!$this->last_activity_at) {
            return false;
        }

        return $this->last_activity_at->diffInSeconds(now()) < self::SESSION_TIMEOUT;
    }

    /**
     * Update last activity timestamp
     */
    public function updateActivity(): bool
    {
        return $this->update([
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Archive chat
     */
    public function archive(): bool
    {
        return $this->update([
            'status' => self::STATUS_ARCHIVED,
            'archived_at' => now(),
        ]);
    }

    /**
     * Unarchive chat
     */
    public function unarchive(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACTIVE,
            'archived_at' => null,
        ]);
    }

    /**
     * Block chat
     */
    public function block(int $blockedByUserId, string $reason = null): bool
    {
        return $this->update([
            'status' => self::STATUS_BLOCKED,
            'blocked_at' => now(),
            'blocked_by_user_id' => $blockedByUserId,
            'blocked_reason' => $reason,
        ]);
    }

    /**
     * Unblock chat
     */
    public function unblock(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACTIVE,
            'blocked_at' => null,
            'blocked_by_user_id' => null,
            'blocked_reason' => null,
        ]);
    }

    /**
     * Add participant to chat
     */
    public function addParticipant(int $userId, array $attributes = []): bool
    {
        if ($this->hasParticipant($userId)) {
            return false;
        }

        $defaults = [
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
            'unread_count' => 0,
            'is_muted' => false,
            'is_pinned' => false,
            'is_archived' => false,
            'notifications_enabled' => true,
        ];

        $this->participants()->attach($userId, array_merge($defaults, $attributes));
        $this->increment('participant_count');

        return true;
    }

    /**
     * Remove participant from chat
     */
    public function removeParticipant(int $userId): bool
    {
        if (!$this->hasParticipant($userId)) {
            return false;
        }

        $this->participants()->updateExistingPivot($userId, [
            'status' => 'left',
            'left_at' => now(),
        ]);

        $this->decrement('participant_count');

        return true;
    }

    /**
     * Update participant settings
     */
    public function updateParticipant(int $userId, array $attributes): bool
    {
        if (!$this->hasParticipant($userId)) {
            return false;
        }

        $this->participants()->updateExistingPivot($userId, $attributes);

        return true;
    }

    /**
     * Mark messages as read for user
     */
    public function markAsRead(int $userId): bool
    {
        return $this->updateParticipant($userId, [
            'last_read_at' => now(),
            'unread_count' => 0,
        ]);
    }

    /**
     * Increment unread count for participants except sender
     */
    public function incrementUnreadCount(int $senderUserId): void
    {
        $this->participants()
            ->where('user_id', '!=', $senderUserId)
            ->increment('unread_count');

        $this->increment('unread_count');
    }

    /**
     * Update message count
     */
    public function updateMessageCount(): bool
    {
        $count = $this->messages()->count();
        return $this->update(['message_count' => $count]);
    }

    /**
     * Get chat statistics
     */
    public function getStatistics(): array
    {
        return [
            'message_count' => $this->message_count,
            'participant_count' => $this->participant_count,
            'unread_count' => $this->unread_count,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'duration_days' => $this->created_at->diffInDays(now()),
            'is_session_active' => $this->isSessionActive(),
            'average_messages_per_day' => $this->getAverageMessagesPerDay(),
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

    /**
     * Get engagement score (0-100)
     */
    public function getEngagementScore(): int
    {
        $score = 0;

        // Message frequency (40 points)
        $avgMessagesPerDay = $this->getAverageMessagesPerDay();
        $score += min(40, $avgMessagesPerDay * 2);

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

        // Participant engagement (30 points)
        if ($this->participant_count > 1) {
            $activeParticipants = $this->participants()
                ->wherePivot('last_read_at', '>=', now()->subDays(7))
                ->count();
            $participantEngagement = ($activeParticipants / $this->participant_count) * 30;
            $score += $participantEngagement;
        }

        return min(100, (int) round($score));
    }

    // ========================================
    // CACHE METHODS
    // ========================================

    /**
     * Get cache key for chat
     */
    public function getCacheKey(string $suffix = ''): string
    {
        $key = "chat:{$this->id}";
        return $suffix ? "{$key}:{$suffix}" : $key;
    }

    /**
     * Get cached chat data
     */
    public function getCached(): ?self
    {
        return Cache::remember(
            $this->getCacheKey(),
            self::CACHE_TTL,
            fn() => $this->load(['creator', 'participants', 'lastMessage'])
        );
    }

    /**
     * Clear chat cache
     */
    public function clearCache(): void
    {
        Cache::forget($this->getCacheKey());
        Cache::forget($this->getCacheKey('messages'));
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

    /**
     * Check if notifications are enabled
     */
    public function areNotificationsEnabled(): bool
    {
        return $this->getSetting('notifications_enabled', true);
    }

    /**
     * Check if sound is enabled
     */
    public function isSoundEnabled(): bool
    {
        return $this->getSetting('sound_enabled', true);
    }

    /**
     * Check if typing indicators are enabled
     */
    public function areTypingIndicatorsEnabled(): bool
    {
        return $this->getSetting('typing_indicators', true);
    }

    /**
     * Check if read receipts are enabled
     */
    public function areReadReceiptsEnabled(): bool
    {
        return $this->getSetting('read_receipts', true);
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
        $array['is_session_active'] = $this->isSessionActive();
        $array['engagement_score'] = $this->getEngagementScore();

        return $array;
    }
}