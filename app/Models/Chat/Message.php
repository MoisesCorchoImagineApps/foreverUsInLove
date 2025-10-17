<?php

declare(strict_types=1);

namespace App\Models\Chat;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Class Message
 *
 * Modelo de mensajes con soporte para múltiples tipos de contenido,
 * delivery tracking, encriptación, moderación y threading de respuestas.
 *
 * @package App\Models\Chat
 *
 * @property int $id
 * @property int $chat_id
 * @property int $sender_id
 * @property int|null $parent_id // reply_to message
 * @property string $type // text, image, video, voice, file, sticker, gif, system, icebreaker
 * @property string $status // sending, sent, delivered, read, failed
 * @property string|null $content
 * @property string|null $encrypted_content
 * @property array $attachments
 * @property array $metadata
 * @property array|null $read_by // [user_id => timestamp]
 * @property array|null $delivered_to // [user_id => timestamp]
 * @property string|null $moderation_status // pending, approved, rejected, flagged
 * @property array|null $moderation_data
 * @property Carbon|null $moderated_at
 * @property int|null $moderated_by_user_id
 * @property Carbon|null $edited_at
 * @property array|null $edit_history
 * @property Carbon|null $deleted_by_sender_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $failed_at
 * @property string|null $failure_reason
 * @property bool $is_encrypted
 * @property bool $is_edited
 * @property bool $is_deleted_by_sender
 * @property bool $is_system_message
 * @property float|null $sentiment_score
 * @property float|null $spam_probability
 * @property int $reply_count
 * @property int $reaction_count
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read Chat $chat
 * @property-read User $sender
 * @property-read Message|null $parent
 * @property-read Collection|Message[] $replies
 */
class Message extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'messages';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'chat_id',
        'sender_id',
        'parent_id',
        'type',
        'status',
        'content',
        'encrypted_content',
        'attachments',
        'metadata',
        'read_by',
        'delivered_to',
        'moderation_status',
        'moderation_data',
        'moderated_at',
        'moderated_by_user_id',
        'edited_at',
        'edit_history',
        'deleted_by_sender_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'failure_reason',
        'is_encrypted',
        'is_edited',
        'is_deleted_by_sender',
        'is_system_message',
        'sentiment_score',
        'spam_probability',
        'reply_count',
        'reaction_count',
        'scheduled_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'attachments' => 'array',
        'metadata' => 'array',
        'read_by' => 'array',
        'delivered_to' => 'array',
        'moderation_data' => 'array',
        'moderated_at' => 'datetime',
        'edited_at' => 'datetime',
        'edit_history' => 'array',
        'deleted_by_sender_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_encrypted' => 'boolean',
        'is_edited' => 'boolean',
        'is_deleted_by_sender' => 'boolean',
        'is_system_message' => 'boolean',
        'sentiment_score' => 'float',
        'spam_probability' => 'float',
        'reply_count' => 'integer',
        'reaction_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'encrypted_content',
        'deleted_at',
    ];

    /**
     * Message types
     */
    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';
    public const TYPE_VOICE = 'voice';
    public const TYPE_FILE = 'file';
    public const TYPE_STICKER = 'sticker';
    public const TYPE_GIF = 'gif';
    public const TYPE_SYSTEM = 'system';
    public const TYPE_ICEBREAKER = 'icebreaker';

    /**
     * Message status
     */
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    /**
     * Moderation status
     */
    public const MODERATION_PENDING = 'pending';
    public const MODERATION_APPROVED = 'approved';
    public const MODERATION_REJECTED = 'rejected';
    public const MODERATION_FLAGGED = 'flagged';

    /**
     * Content limits
     */
    public const MAX_TEXT_LENGTH = 2000;
    public const MAX_IMAGE_SIZE_MB = 10;
    public const MAX_VIDEO_SIZE_MB = 100;
    public const MAX_FILE_SIZE_MB = 50;
    public const MAX_VOICE_DURATION_SECONDS = 300;

    /**
     * Rate limits (per user)
     */
    public const RATE_LIMIT_MESSAGES_PER_MINUTE_FREE = 30;
    public const RATE_LIMIT_MESSAGES_PER_MINUTE_PREMIUM = 60;
    public const RATE_LIMIT_MESSAGES_PER_HOUR_FREE = 500;
    public const RATE_LIMIT_MESSAGES_PER_HOUR_PREMIUM = 1000;

    /**
     * Edit and delete windows
     */
    public const EDIT_WINDOW_MINUTES = 15;
    public const DELETE_WINDOW_HOURS = 24;

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Message $message) {
            if (empty($message->metadata)) {
                $message->metadata = [
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'platform' => self::detectPlatform(),
                ];
            }

            // Set initial status
            if (!$message->status) {
                $message->status = self::STATUS_SENDING;
            }

            // Initialize delivery tracking arrays
            if (empty($message->read_by)) {
                $message->read_by = [];
            }
            if (empty($message->delivered_to)) {
                $message->delivered_to = [];
            }
        });

        static::created(function (Message $message) {
            // Update chat counters
            $message->chat->increment('message_count');
            $message->chat->update([
                'last_message_at' => $message->created_at,
                'last_activity_at' => now(),
            ]);

            // Increment unread count for other participants
            $message->chat->incrementUnreadCount($message->sender_id);
        });

        static::deleting(function (Message $message) {
            // Delete attachments from storage
            if (!empty($message->attachments)) {
                foreach ($message->attachments as $attachment) {
                    if (isset($attachment['path'])) {
                        Storage::disk('private')->delete($attachment['path']);
                    }
                }
            }
        });

        static::deleted(function (Message $message) {
            // Update chat message count
            $message->chat->decrement('message_count');
        });
    }

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Get the chat this message belongs to
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }

    /**
     * Get the sender of the message
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the parent message (if this is a reply)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    /**
     * Get all replies to this message
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope for messages by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for text messages
     */
    public function scopeText($query)
    {
        return $query->where('type', self::TYPE_TEXT);
    }

    /**
     * Scope for media messages (image, video, voice)
     */
    public function scopeMedia($query)
    {
        return $query->whereIn('type', [self::TYPE_IMAGE, self::TYPE_VIDEO, self::TYPE_VOICE]);
    }

    /**
     * Scope for messages with attachments
     */
    public function scopeWithAttachments($query)
    {
        return $query->whereNotNull('attachments')->where('attachments', '!=', '[]');
    }

    /**
     * Scope for system messages
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system_message', true);
    }

    /**
     * Scope for user messages (non-system)
     */
    public function scopeUserMessages($query)
    {
        return $query->where('is_system_message', false);
    }

    /**
     * Scope for unread messages by user
     */
    public function scopeUnreadBy($query, int $userId)
    {
        return $query->whereJsonDoesntContain('read_by', (string) $userId);
    }

    /**
     * Scope for delivered messages
     */
    public function scopeDelivered($query)
    {
        return $query->whereIn('status', [self::STATUS_DELIVERED, self::STATUS_READ]);
    }

    /**
     * Scope for read messages
     */
    public function scopeRead($query)
    {
        return $query->where('status', self::STATUS_READ);
    }

    /**
     * Scope for failed messages
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for messages pending moderation
     */
    public function scopePendingModeration($query)
    {
        return $query->where('moderation_status', self::MODERATION_PENDING);
    }

    /**
     * Scope for flagged messages
     */
    public function scopeFlagged($query)
    {
        return $query->where('moderation_status', self::MODERATION_FLAGGED);
    }

    /**
     * Scope for scheduled messages
     */
    public function scopeScheduled($query)
    {
        return $query->whereNotNull('scheduled_at')->where('scheduled_at', '>', now());
    }

    /**
     * Scope for expired messages
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    /**
     * Scope for edited messages
     */
    public function scopeEdited($query)
    {
        return $query->where('is_edited', true);
    }

    /**
     * Scope for deleted by sender
     */
    public function scopeDeletedBySender($query)
    {
        return $query->where('is_deleted_by_sender', true);
    }

    /**
     * Scope for messages with replies
     */
    public function scopeWithReplies($query)
    {
        return $query->where('reply_count', '>', 0);
    }

    /**
     * Scope for recent messages (last N hours)
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if message is text
     */
    public function isText(): bool
    {
        return $this->type === self::TYPE_TEXT;
    }

    /**
     * Check if message has attachments
     */
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    /**
     * Check if message is media (image, video, voice)
     */
    public function isMedia(): bool
    {
        return in_array($this->type, [self::TYPE_IMAGE, self::TYPE_VIDEO, self::TYPE_VOICE]);
    }

    /**
     * Check if message is a reply
     */
    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Check if message has replies
     */
    public function hasReplies(): bool
    {
        return $this->reply_count > 0;
    }

    /**
     * Check if message is sent
     */
    public function isSent(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_DELIVERED, self::STATUS_READ]);
    }

    /**
     * Check if message is delivered
     */
    public function isDelivered(): bool
    {
        return in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_READ]);
    }

    /**
     * Check if message is read
     */
    public function isRead(): bool
    {
        return $this->status === self::STATUS_READ;
    }

    /**
     * Check if message failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if message is scheduled
     */
    public function isScheduled(): bool
    {
        return $this->scheduled_at && $this->scheduled_at->isFuture();
    }

    /**
     * Check if message is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if user has read the message
     */
    public function isReadBy(int $userId): bool
    {
        return isset($this->read_by[(string) $userId]);
    }

    /**
     * Check if message was delivered to user
     */
    public function isDeliveredTo(int $userId): bool
    {
        return isset($this->delivered_to[(string) $userId]);
    }

    /**
     * Check if message can be edited
     */
    public function canBeEdited(): bool
    {
        if ($this->is_system_message || $this->is_deleted_by_sender) {
            return false;
        }

        return $this->created_at->diffInMinutes(now()) <= self::EDIT_WINDOW_MINUTES;
    }

    /**
     * Check if message can be deleted
     */
    public function canBeDeleted(): bool
    {
        if ($this->is_system_message || $this->is_deleted_by_sender) {
            return false;
        }

        return $this->created_at->diffInHours(now()) <= self::DELETE_WINDOW_HOURS;
    }

    /**
     * Mark as sent
     */
    public function markAsSent(): bool
    {
        return $this->update([
            'status' => self::STATUS_SENT,
        ]);
    }

    /**
     * Mark as delivered to user
     */
    public function markAsDeliveredTo(int $userId): bool
    {
        $deliveredTo = $this->delivered_to ?? [];
        $deliveredTo[(string) $userId] = now()->toIso8601String();

        $update = [
            'delivered_to' => $deliveredTo,
            'delivered_at' => now(),
        ];

        // Update status if first delivery
        if ($this->status === self::STATUS_SENT) {
            $update['status'] = self::STATUS_DELIVERED;
        }

        return $this->update($update);
    }

    /**
     * Mark as read by user
     */
    public function markAsReadBy(int $userId): bool
    {
        $readBy = $this->read_by ?? [];
        $readBy[(string) $userId] = now()->toIso8601String();

        $update = [
            'read_by' => $readBy,
            'read_at' => now(),
            'status' => self::STATUS_READ,
        ];

        return $this->update($update);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $reason = null): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Edit message content
     */
    public function editContent(string $newContent): bool
    {
        if (!$this->canBeEdited()) {
            return false;
        }

        $editHistory = $this->edit_history ?? [];
        $editHistory[] = [
            'content' => $this->content,
            'edited_at' => now()->toIso8601String(),
        ];

        return $this->update([
            'content' => $newContent,
            'is_edited' => true,
            'edited_at' => now(),
            'edit_history' => $editHistory,
        ]);
    }

    /**
     * Delete message (soft delete by sender)
     */
    public function deleteBySender(): bool
    {
        if (!$this->canBeDeleted()) {
            return false;
        }

        return $this->update([
            'is_deleted_by_sender' => true,
            'deleted_by_sender_at' => now(),
            'content' => null,
            'attachments' => [],
        ]);
    }

    /**
     * Update moderation status
     */
    public function updateModerationStatus(string $status, array $data = null, int $moderatorId = null): bool
    {
        return $this->update([
            'moderation_status' => $status,
            'moderation_data' => $data,
            'moderated_at' => now(),
            'moderated_by_user_id' => $moderatorId,
        ]);
    }

    /**
     * Approve message
     */
    public function approve(int $moderatorId = null): bool
    {
        return $this->updateModerationStatus(self::MODERATION_APPROVED, null, $moderatorId);
    }

    /**
     * Reject message
     */
    public function reject(array $reason, int $moderatorId = null): bool
    {
        return $this->updateModerationStatus(self::MODERATION_REJECTED, $reason, $moderatorId);
    }

    /**
     * Flag message
     */
    public function flag(array $reason, int $moderatorId = null): bool
    {
        return $this->updateModerationStatus(self::MODERATION_FLAGGED, $reason, $moderatorId);
    }

    /**
     * Get message length
     */
    public function getContentLength(): int
    {
        return mb_strlen($this->content ?? '');
    }

    /**
     * Get delivery statistics
     */
    public function getDeliveryStats(): array
    {
        $totalParticipants = $this->chat->participant_count - 1; // Exclude sender
        $deliveredCount = count($this->delivered_to ?? []);
        $readCount = count($this->read_by ?? []);

        return [
            'total_participants' => $totalParticipants,
            'delivered_count' => $deliveredCount,
            'read_count' => $readCount,
            'delivery_rate' => $totalParticipants > 0 ? round(($deliveredCount / $totalParticipants) * 100, 2) : 0,
            'read_rate' => $totalParticipants > 0 ? round(($readCount / $totalParticipants) * 100, 2) : 0,
        ];
    }

    /**
     * Get engagement metrics
     */
    public function getEngagementMetrics(): array
    {
        return [
            'reply_count' => $this->reply_count,
            'reaction_count' => $this->reaction_count,
            'is_edited' => $this->is_edited,
            'edit_count' => count($this->edit_history ?? []),
            'sentiment_score' => $this->sentiment_score,
            'spam_probability' => $this->spam_probability,
            'delivery_stats' => $this->getDeliveryStats(),
        ];
    }

    /**
     * Get attachment metadata
     */
    public function getAttachmentMetadata(): array
    {
        if (empty($this->attachments)) {
            return [];
        }

        return array_map(function ($attachment) {
            return [
                'type' => $attachment['type'] ?? null,
                'filename' => $attachment['filename'] ?? null,
                'size' => $attachment['size'] ?? null,
                'mime_type' => $attachment['mime_type'] ?? null,
                'duration' => $attachment['duration'] ?? null,
            ];
        }, $this->attachments);
    }

    // ========================================
    // CACHE METHODS
    // ========================================

    /**
     * Get cache key for message
     */
    public function getCacheKey(string $suffix = ''): string
    {
        $key = "message:{$this->id}";
        return $suffix ? "{$key}:{$suffix}" : $key;
    }

    /**
     * Clear message cache
     */
    public function clearCache(): void
    {
        Cache::forget($this->getCacheKey());
        Cache::forget($this->getCacheKey('thread'));
    }

    // ========================================
    // STATIC HELPER METHODS
    // ========================================

    /**
     * Detect platform from user agent
     */
    protected static function detectPlatform(): string
    {
        $userAgent = request()->userAgent() ?? '';

        if (stripos($userAgent, 'android') !== false) {
            return 'android';
        } elseif (stripos($userAgent, 'iphone') !== false || stripos($userAgent, 'ipad') !== false) {
            return 'ios';
        } elseif (stripos($userAgent, 'windows') !== false) {
            return 'windows';
        } elseif (stripos($userAgent, 'mac') !== false) {
            return 'mac';
        } elseif (stripos($userAgent, 'linux') !== false) {
            return 'linux';
        }

        return 'web';
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
        $array['can_be_edited'] = $this->canBeEdited();
        $array['can_be_deleted'] = $this->canBeDeleted();
        $array['content_length'] = $this->getContentLength();
        $array['has_attachments'] = $this->hasAttachments();
        $array['is_reply'] = $this->isReply();
        $array['has_replies'] = $this->hasReplies();

        // Sanitize deleted content
        if ($this->is_deleted_by_sender) {
            $array['content'] = null;
            $array['attachments'] = [];
        }

        return $array;
    }
}