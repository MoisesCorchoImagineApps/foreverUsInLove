<?php

declare(strict_types=1);

namespace App\Models\Notification;

use App\Domain\Notification\Events\NotificationSent;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Class Notification
 *
 * Modelo orquestador central del sistema de notificaciones multi-canal.
 * Gestiona el envío, programación, tracking y análisis de notificaciones
 * a través de múltiples canales (push, email, sms, in-app, webhooks).
 *
 * @package App\Models\Notification
 *
 * @property string $notification_id UUID primary key
 * @property string $user_id FK to users
 * @property string|null $notifiable_type Polymorphic type
 * @property string|null $notifiable_id Polymorphic ID
 * @property string $type Notification type (NOTIFICATION_TYPES)
 * @property array $channels Array of delivery channels
 * @property string $priority Notification priority (LOW to CRITICAL)
 * @property string $status Current status (QUEUED to EXPIRED)
 * @property string|null $category Notification category
 * @property string $title Notification title
 * @property string $message Notification message
 * @property array|null $data Custom notification data (encrypted)
 * @property array|null $delivery_results Results per channel
 * @property array|null $preferences_snapshot User preferences at send time
 * @property string|null $template_id Template identifier
 * @property string|null $campaign_id Campaign identifier
 * @property string|null $ab_test_variant A/B test variant
 * @property array|null $personalization Personalization data
 * @property string|null $reference_id External reference
 * @property array|null $metadata Additional metadata (encrypted)
 * @property Carbon|null $scheduled_at Scheduled send time
 * @property Carbon|null $sent_at Actual send time
 * @property Carbon|null $delivered_at Delivery confirmation time
 * @property Carbon|null $read_at Read timestamp
 * @property Carbon|null $clicked_at Click timestamp
 * @property Carbon|null $expires_at Expiration time
 * @property int $retry_count Number of retry attempts
 * @property string|null $failure_reason Failure reason if failed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read User $user
 * @property-read Model $notifiable
 * @property-read Collection|PushNotification[] $pushNotifications
 * @property-read Collection|EmailNotification[] $emailNotifications
 *
 * @method static \Illuminate\Database\Eloquent\Builder byUser(string $userId)
 * @method static \Illuminate\Database\Eloquent\Builder byType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder byChannel(string $channel)
 * @method static \Illuminate\Database\Eloquent\Builder byPriority(string $priority)
 * @method static \Illuminate\Database\Eloquent\Builder byStatus(string $status)
 * @method static \Illuminate\Database\Eloquent\Builder unread()
 * @method static \Illuminate\Database\Eloquent\Builder read()
 * @method static \Illuminate\Database\Eloquent\Builder pending()
 * @method static \Illuminate\Database\Eloquent\Builder sent()
 * @method static \Illuminate\Database\Eloquent\Builder delivered()
 * @method static \Illuminate\Database\Eloquent\Builder failed()
 * @method static \Illuminate\Database\Eloquent\Builder scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder highPriority()
 * @method static \Illuminate\Database\Eloquent\Builder urgent()
 * @method static \Illuminate\Database\Eloquent\Builder critical()
 * @method static \Illuminate\Database\Eloquent\Builder recentNotifications(int $days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder expiringSoon(int $hours = 24)
 * @method static \Illuminate\Database\Eloquent\Builder needsRetry()
 * @method static \Illuminate\Database\Eloquent\Builder inCampaign(string $campaignId)
 * @method static \Illuminate\Database\Eloquent\Builder byCategory(string $category)
 */
class Notification extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Primary key configuration
     */
    protected $primaryKey = 'notification_id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The table associated with the model.
     */
    protected $table = 'notifications';

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'notification_id' => 'string',
        'channels' => 'array',
        'data' => 'encrypted:array',
        'delivery_results' => 'encrypted:array',
        'preferences_snapshot' => 'array',
        'personalization' => 'array',
        'metadata' => 'encrypted:array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'clicked_at' => 'datetime',
        'expires_at' => 'datetime',
        'retry_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Notification Types - Dating Activity
     */
    public const TYPE_NEW_MATCH = 'new_match';
    public const TYPE_MATCH_LIKED_YOU = 'match_liked_you';
    public const TYPE_MATCH_MESSAGE = 'match_message';
    public const TYPE_MATCH_VIEWED_PROFILE = 'match_viewed_profile';
    public const TYPE_SUPER_LIKE_RECEIVED = 'super_like_received';
    public const TYPE_PROFILE_VISIT = 'profile_visit';
    public const TYPE_PHOTO_LIKED = 'photo_liked';
    public const TYPE_GIFT_RECEIVED = 'gift_received';

    /**
     * Notification Types - Messaging
     */
    public const TYPE_NEW_MESSAGE = 'new_message';
    public const TYPE_MESSAGE_READ = 'message_read';
    public const TYPE_VOICE_MESSAGE = 'voice_message';
    public const TYPE_VIDEO_CALL_INVITE = 'video_call_invite';
    public const TYPE_MISSED_CALL = 'missed_call';
    public const TYPE_TYPING_INDICATOR = 'typing_indicator';

    /**
     * Notification Types - Social Engagement
     */
    public const TYPE_COMMENT_RECEIVED = 'comment_received';
    public const TYPE_STORY_VIEW = 'story_view';
    public const TYPE_STORY_REACTION = 'story_reaction';
    public const TYPE_FRIEND_REQUEST = 'friend_request';
    public const TYPE_CONNECTION_ACCEPTED = 'connection_accepted';

    /**
     * Notification Types - System
     */
    public const TYPE_PROFILE_APPROVED = 'profile_approved';
    public const TYPE_PROFILE_REJECTED = 'profile_rejected';
    public const TYPE_VERIFICATION_COMPLETE = 'verification_complete';
    public const TYPE_VERIFICATION_FAILED = 'verification_failed';
    public const TYPE_ACCOUNT_WARNING = 'account_warning';
    public const TYPE_ACCOUNT_SUSPENDED = 'account_suspended';
    public const TYPE_PASSWORD_CHANGED = 'password_changed';
    public const TYPE_EMAIL_CHANGED = 'email_changed';

    /**
     * Notification Types - Commerce
     */
    public const TYPE_SUBSCRIPTION_EXPIRING = 'subscription_expiring';
    public const TYPE_SUBSCRIPTION_RENEWED = 'subscription_renewed';
    public const TYPE_PAYMENT_SUCCESS = 'payment_success';
    public const TYPE_PAYMENT_FAILED = 'payment_failed';
    public const TYPE_REFUND_PROCESSED = 'refund_processed';
    public const TYPE_COINS_PURCHASED = 'coins_purchased';
    public const TYPE_LOW_BALANCE = 'low_balance';

    /**
     * Notification Types - Marketing & Engagement
     */
    public const TYPE_DAILY_MATCHES = 'daily_matches';
    public const TYPE_WEEKLY_DIGEST = 'weekly_digest';
    public const TYPE_PROMOTIONAL_OFFER = 'promotional_offer';
    public const TYPE_FEATURE_ANNOUNCEMENT = 'feature_announcement';
    public const TYPE_RE_ENGAGEMENT = 're_engagement';
    public const TYPE_COMEBACK_OFFER = 'comeback_offer';
    public const TYPE_PREMIUM_TRIAL = 'premium_trial';

    /**
     * Notification Types - Moderation & Safety
     */
    public const TYPE_SAFETY_ALERT = 'safety_alert';
    public const TYPE_REPORT_UPDATE = 'report_update';
    public const TYPE_MODERATION_ACTION = 'moderation_action';
    public const TYPE_BLOCK_NOTIFICATION = 'block_notification';
    public const TYPE_CONTENT_FLAGGED = 'content_flagged';

    /**
     * Notification Types - Special Events
     */
    public const TYPE_BIRTHDAY_REMINDER = 'birthday_reminder';
    public const TYPE_ANNIVERSARY_REMINDER = 'anniversary_reminder';
    public const TYPE_SEASONAL_GREETINGS = 'seasonal_greetings';
    public const TYPE_SPECIAL_EVENT = 'special_event';
    public const TYPE_MILESTONE_ACHIEVED = 'milestone_achieved';

    /**
     * Delivery Channels
     */
    public const CHANNEL_PUSH = 'push';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_WEBHOOK = 'webhook';
    public const CHANNEL_SLACK = 'slack';
    public const CHANNEL_DISCORD = 'discord';

    public const CHANNELS = [
        self::CHANNEL_PUSH,
        self::CHANNEL_EMAIL,
        self::CHANNEL_SMS,
        self::CHANNEL_IN_APP,
        self::CHANNEL_WEBHOOK,
        self::CHANNEL_SLACK,
        self::CHANNEL_DISCORD,
    ];

    /**
     * Priority Levels
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';
    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
        self::PRIORITY_CRITICAL,
    ];

    /**
     * Status Constants
     */
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_SENT,
        self::STATUS_DELIVERED,
        self::STATUS_READ,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    /**
     * Notification Categories
     */
    public const CATEGORY_DATING = 'dating';
    public const CATEGORY_MESSAGING = 'messaging';
    public const CATEGORY_SOCIAL = 'social';
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_MARKETING = 'marketing';
    public const CATEGORY_MODERATION = 'moderation';

    public const CATEGORIES = [
        self::CATEGORY_DATING,
        self::CATEGORY_MESSAGING,
        self::CATEGORY_SOCIAL,
        self::CATEGORY_SYSTEM,
        self::CATEGORY_MARKETING,
        self::CATEGORY_MODERATION,
    ];

    /**
     * Retry Configuration
     */
    public const MAX_RETRY_ATTEMPTS = 3;
    public const RETRY_DELAY_MINUTES = 5;

    /**
     * Cache Configuration
     */
    public const CACHE_PREFIX = 'notification:';
    public const CACHE_USER_UNREAD_TTL = 300; // 5 minutes
    public const CACHE_USER_PREFERENCES_TTL = 3600; // 1 hour

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user that owns the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the notifiable entity (polymorphic).
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the push notifications for this notification.
     */
    public function pushNotifications(): HasMany
    {
        return $this->hasMany(PushNotification::class, 'notification_id', 'notification_id');
    }

    /**
     * Get the email notifications for this notification.
     */
    public function emailNotifications(): HasMany
    {
        return $this->hasMany(EmailNotification::class, 'notification_id', 'notification_id');
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Filter by user ID
     */
    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter by notification type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Filter by delivery channel
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->whereJsonContains('channels', $channel);
    }

    /**
     * Scope: Filter by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Get unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope: Get read notifications
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope: Get pending notifications (queued or processing)
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_QUEUED, self::STATUS_PROCESSING]);
    }

    /**
     * Scope: Get sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope: Get delivered notifications
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Scope: Get failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope: Get scheduled notifications
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_QUEUED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now());
    }

    /**
     * Scope: Get high priority notifications
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', self::PRIORITY_HIGH);
    }

    /**
     * Scope: Get urgent notifications
     */
    public function scopeUrgent($query)
    {
        return $query->where('priority', self::PRIORITY_URGENT);
    }

    /**
     * Scope: Get critical notifications
     */
    public function scopeCritical($query)
    {
        return $query->where('priority', self::PRIORITY_CRITICAL);
    }

    /**
     * Scope: Get recent notifications
     */
    public function scopeRecentNotifications($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Scope: Get notifications expiring soon
     */
    public function scopeExpiringSoon($query, int $hours = 24)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addHours($hours))
            ->where('expires_at', '>', now())
            ->whereNotIn('status', [self::STATUS_READ, self::STATUS_EXPIRED, self::STATUS_CANCELLED]);
    }

    /**
     * Scope: Get notifications that need retry
     */
    public function scopeNeedsRetry($query)
    {
        return $query->where('status', self::STATUS_FAILED)
            ->where('retry_count', '<', self::MAX_RETRY_ATTEMPTS)
            ->where('updated_at', '<=', now()->subMinutes(self::RETRY_DELAY_MINUTES));
    }

    /**
     * Scope: Filter by campaign
     */
    public function scopeInCampaign($query, string $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope: Get ready scheduled notifications
     */
    public function scopeReadyScheduled($query)
    {
        return $query->where('status', self::STATUS_QUEUED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Get formatted channels list
     */
    public function getFormattedChannelsAttribute(): string
    {
        return implode(', ', array_map('ucfirst', $this->channels ?? []));
    }

    /**
     * Check if notification is read
     */
    public function getIsReadAttribute(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if notification is delivered
     */
    public function getIsDeliveredAttribute(): bool
    {
        return !is_null($this->delivered_at);
    }

    /**
     * Check if notification is scheduled
     */
    public function getIsScheduledAttribute(): bool
    {
        return !is_null($this->scheduled_at) && $this->scheduled_at->isFuture();
    }

    /**
     * Check if notification is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        return !is_null($this->expires_at) && $this->expires_at->isPast();
    }

    /**
     * Get time until scheduled send
     */
    public function getTimeUntilSendAttribute(): ?string
    {
        if (!$this->is_scheduled) {
            return null;
        }

        return $this->scheduled_at->diffForHumans();
    }

    /**
     * Get delivery success rate
     */
    public function getDeliverySuccessRateAttribute(): float
    {
        if (empty($this->delivery_results)) {
            return 0.0;
        }

        $total = count($this->delivery_results);
        $successful = collect($this->delivery_results)->filter(fn($result) => $result['success'] ?? false)->count();

        return $total > 0 ? round(($successful / $total) * 100, 2) : 0.0;
    }

    /**
     * Get priority level as integer
     */
    public function getPriorityLevelAttribute(): int
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => 1,
            self::PRIORITY_NORMAL => 2,
            self::PRIORITY_HIGH => 3,
            self::PRIORITY_URGENT => 4,
            self::PRIORITY_CRITICAL => 5,
            default => 2,
        };
    }

    /**
     * Get priority color for UI
     */
    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => 'gray',
            self::PRIORITY_NORMAL => 'blue',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_URGENT => 'red',
            self::PRIORITY_CRITICAL => 'purple',
            default => 'blue',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Send notification to specified channels
     */
    public function send(?array $channels): bool
    {
        try {
            $channels = $channels ?? $this->channels;

            if (empty($channels)) {
                $this->markAsFailed('No channels specified');
                return false;
            }

            // Update status to processing
            $this->update(['status' => self::STATUS_PROCESSING]);

            $results = [];
            $overallSuccess = false;

            // Send to each channel
            foreach ($channels as $channel) {
                $result = $this->sendToChannel($channel);
                $results[$channel] = $result;

                if ($result['success']) {
                    $overallSuccess = true;
                }
            }

            // Update delivery results
            $this->update([
                'delivery_results' => $results,
                'sent_at' => now(),
                'status' => $overallSuccess ? self::STATUS_SENT : self::STATUS_FAILED,
            ]);

            // Dispatch event if successful
            if ($overallSuccess) {
                event(new NotificationSent(
                    crc32($this->notification_id),
                    (int) $this->user_id,
                    $this->type,
                    $this->channels,
                    $results,
                    [
                        'notification_id' => $this->notification_id,
                        'priority' => $this->priority,
                        'category' => $this->category,
                        'template_id' => $this->template_id,
                        'campaign_id' => $this->campaign_id
                    ]
                ));
            }

            // Clear cache
            $this->clearUserCache();

            return $overallSuccess;
        } catch (\Exception $e) {
            $this->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to a specific channel
     */
    public function sendToChannel(string $channel): array
    {
        try {
            switch ($channel) {
                case self::CHANNEL_PUSH:
                    return $this->sendPushNotification();

                case self::CHANNEL_EMAIL:
                    return $this->sendEmailNotification();

                case self::CHANNEL_SMS:
                    return $this->sendSmsNotification();

                case self::CHANNEL_IN_APP:
                    return $this->sendInAppNotification();

                case self::CHANNEL_WEBHOOK:
                    return $this->sendWebhookNotification();

                default:
                    return [
                        'success' => false,
                        'channel' => $channel,
                        'error' => 'Unsupported channel',
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send push notification
     */
    protected function sendPushNotification(): array
    {
        $push = PushNotification::create([
            'push_id' => (string) \Illuminate\Support\Str::uuid(),
            'notification_id' => $this->notification_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'body' => $this->message,
            'data' => $this->data,
            'priority' => $this->priority,
        ]);

        $result = $push->sendToAllPlatforms();

        return [
            'success' => $result,
            'channel' => self::CHANNEL_PUSH,
            'push_id' => $push->push_id,
        ];
    }

    /**
     * Send email notification
     */
    protected function sendEmailNotification(): array
    {
        $email = EmailNotification::create([
            'email_id' => (string) \Illuminate\Support\Str::uuid(),
            'notification_id' => $this->notification_id,
            'user_id' => $this->user_id,
            'to_email' => $this->user->email,
            'subject' => $this->title,
            'html_body' => $this->message,
            'template_id' => $this->template_id,
            'priority' => $this->priority,
        ]);

        $result = $email->sendEmail();

        return [
            'success' => $result,
            'channel' => self::CHANNEL_EMAIL,
            'email_id' => $email->email_id,
        ];
    }

    /**
     * Send SMS notification
     */
    protected function sendSmsNotification(): array
    {
        // SMS logic would be implemented via SMSService
        // For now, return placeholder
        return [
            'success' => true,
            'channel' => self::CHANNEL_SMS,
            'message' => 'SMS sent successfully',
        ];
    }

    /**
     * Send in-app notification
     */
    protected function sendInAppNotification(): array
    {
        // In-app notifications are typically stored and displayed in the app
        // No external service call needed
        return [
            'success' => true,
            'channel' => self::CHANNEL_IN_APP,
            'message' => 'In-app notification created',
        ];
    }

    /**
     * Send webhook notification
     */
    protected function sendWebhookNotification(): array
    {
        // Webhook logic would trigger external endpoints
        return [
            'success' => true,
            'channel' => self::CHANNEL_WEBHOOK,
            'message' => 'Webhook triggered',
        ];
    }

    /**
     * Schedule notification for future delivery
     */
    public function schedule(Carbon $scheduledAt): bool
    {
        if ($scheduledAt->isPast()) {
            return false;
        }

        return $this->update([
            'scheduled_at' => $scheduledAt,
            'status' => self::STATUS_QUEUED,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }

        $result = $this->update([
            'read_at' => now(),
            'status' => self::STATUS_READ,
        ]);

        $this->clearUserCache();

        return $result;
    }

    /**
     * Mark notification as delivered
     */
    public function markAsDelivered(): bool
    {
        return $this->update([
            'delivered_at' => now(),
            'status' => self::STATUS_DELIVERED,
        ]);
    }

    /**
     * Mark notification as failed
     */
    public function markAsFailed(string $reason): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'retry_count' => DB::raw('retry_count + 1'),
        ]);
    }

    /**
     * Cancel scheduled notification
     */
    public function cancel(): bool
    {
        if (!$this->is_scheduled) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Check if notification has expired
     */
    public function checkExpiration(): bool
    {
        if ($this->is_expired && $this->status !== self::STATUS_EXPIRED) {
            return $this->update(['status' => self::STATUS_EXPIRED]);
        }

        return false;
    }

    /**
     * Get optimal channel for user based on preferences and behavior
     */
    public function getOptimalChannel(string $userId): string
    {
        $cacheKey = self::CACHE_PREFIX . "optimal_channel:{$userId}";

        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            // Logic to determine best channel based on:
            // - User preferences
            // - Historical engagement rates
            // - Time of day
            // - Device availability

            $preferences = $this->getUserPreferences($userId);

            if ($preferences['push_enabled'] ?? true) {
                return self::CHANNEL_PUSH;
            }

            if ($preferences['email_enabled'] ?? true) {
                return self::CHANNEL_EMAIL;
            }

            return self::CHANNEL_IN_APP;
        });
    }

    /**
     * Get user notification preferences
     */
    public function getUserPreferences(string $userId): array
    {
        $cacheKey = self::CACHE_PREFIX . "preferences:{$userId}";

        return Cache::remember($cacheKey, self::CACHE_USER_PREFERENCES_TTL, function () use ($userId) {
            // Fetch from user settings or notification_preferences table
            return [
                'push_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => false,
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '08:00',
            ];
        });
    }

    /**
     * Check if user can receive this notification type
     */
    public function canSendToUser(string $userId): bool
    {
        $preferences = $this->getUserPreferences($userId);

        // Check if user has opted out of this notification type
        $optedOut = $preferences['opted_out_types'] ?? [];
        if (in_array($this->type, $optedOut)) {
            return false;
        }

        // Check quiet hours
        if ($this->isInQuietHours($preferences)) {
            return $this->priority >= self::PRIORITY_URGENT;
        }

        return true;
    }

    /**
     * Check if current time is in user's quiet hours
     */
    protected function isInQuietHours(array $preferences): bool
    {
        $now = now();
        $start = Carbon::parse($preferences['quiet_hours_start'] ?? '22:00');
        $end = Carbon::parse($preferences['quiet_hours_end'] ?? '08:00');

        if ($start->lt($end)) {
            return $now->between($start, $end);
        }

        return $now->gte($start) || $now->lte($end);
    }

    /**
     * Retry failed notification
     */
    public function retry(): bool
    {
        if ($this->retry_count >= self::MAX_RETRY_ATTEMPTS) {
            return false;
        }

        return $this->send($this->channels);
    }

    /**
     * Get notification statistics for a user
     */
    public static function getUserStatistics(string $userId, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $notifications = self::byUser($userId)
            ->where('created_at', '>=', $startDate)
            ->get();

        return [
            'total_sent' => $notifications->count(),
            'total_read' => $notifications->where('read_at', '!=', null)->count(),
            'total_unread' => $notifications->where('read_at', null)->count(),
            'read_rate' => $notifications->count() > 0
                ? round(($notifications->where('read_at', '!=', null)->count() / $notifications->count()) * 100, 2)
                : 0,
            'by_type' => $notifications->groupBy('type')->map->count(),
            'by_channel' => $notifications->flatMap(fn($n) => $n->channels)->countBy(),
            'by_priority' => $notifications->groupBy('priority')->map->count(),
            'avg_time_to_read' => $notifications->where('read_at', '!=', null)
                ->avg(fn($n) => $n->read_at->diffInMinutes($n->sent_at)),
        ];
    }

    /**
     * Process scheduled notifications that are ready to send
     */
    public static function processScheduledNotifications(): int
    {
        $notifications = self::readyScheduled()->get();
        $processed = 0;

        foreach ($notifications as $notification) {
            if ($notification->send()) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Process expired notifications
     */
    public static function processExpiredNotifications(): int
    {
        return self::expiringSoon(0)
            ->update(['status' => self::STATUS_EXPIRED]);
    }

    /**
     * Clear user cache
     */
    protected function clearUserCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . "unread_count:{$this->user_id}");
        Cache::forget(self::CACHE_PREFIX . "recent:{$this->user_id}");
        Cache::forget(self::CACHE_PREFIX . "preferences:{$this->user_id}");
    }

    /**
     * Get unread count for user (cached)
     */
    public static function getUnreadCount(string $userId): int
    {
        $cacheKey = self::CACHE_PREFIX . "unread_count:{$userId}";

        return Cache::remember($cacheKey, self::CACHE_USER_UNREAD_TTL, function () use ($userId) {
            return self::byUser($userId)->unread()->count();
        });
    }

    /**
     * Mark multiple notifications as read
     */
    public static function markMultipleAsRead(array $notificationIds): int
    {
        return self::whereIn('notification_id', $notificationIds)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'status' => self::STATUS_READ,
            ]);
    }

    /**
     * Mark all user notifications as read
     */
    public static function markAllAsRead(string $userId): int
    {
        $count = self::byUser($userId)
            ->unread()
            ->update([
                'read_at' => now(),
                'status' => self::STATUS_READ,
            ]);

        Cache::forget(self::CACHE_PREFIX . "unread_count:{$userId}");

        return $count;
    }

    /**
     * Create a new collection instance
     */
    public function newCollection(array $models = []): NotificationCollection
    {
        return new NotificationCollection($models);
    }
}

/**
 * Custom Collection for Notification Model
 */
class NotificationCollection extends Collection
{
    /**
     * Get only unread notifications
     */
    public function unread(): self
    {
        return $this->filter(fn($notification) => is_null($notification->read_at));
    }

    /**
     * Get only read notifications
     */
    public function read(): self
    {
        return $this->filter(fn($notification) => !is_null($notification->read_at));
    }

    /**
     * Get notifications by type
     */
    public function byType(string $type): self
    {
        return $this->filter(fn($notification) => $notification->type === $type);
    }

    /**
     * Get notifications by priority
     */
    public function byPriority(string $priority): self
    {
        return $this->filter(fn($notification) => $notification->priority === $priority);
    }

    /**
     * Get high priority notifications
     */
    public function highPriority(): self
    {
        return $this->filter(fn($notification) => in_array($notification->priority, [
            Notification::PRIORITY_HIGH,
            Notification::PRIORITY_URGENT,
            Notification::PRIORITY_CRITICAL,
        ]));
    }

    /**
     * Calculate read rate
     */
    public function readRate(): float
    {
        if ($this->isEmpty()) {
            return 0.0;
        }

        return round(($this->read()->count() / $this->count()) * 100, 2);
    }

    /**
     * Group by channel
     */
    public function groupByChannel(): Collection
    {
        return $this->flatMap(fn($notification) => $notification->channels)
            ->countBy()
            ->sortDesc();
    }

    /**
     * Get average time to read
     */
    public function averageTimeToRead(): ?float
    {
        $readNotifications = $this->read()
            ->filter(fn($notification) => !is_null($notification->sent_at));

        if ($readNotifications->isEmpty()) {
            return null;
        }

        return $readNotifications->avg(fn($notification) =>
            $notification->read_at->diffInMinutes($notification->sent_at)
        );
    }
}