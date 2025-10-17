<?php

declare(strict_types=1);

namespace App\Models\Notification;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

/**
 * Class EmailNotification
 *
 * Modelo especializado en notificaciones por email con campaign tracking,
 * A/B testing, webhook processing y analytics detallados. Gestiona envíos
 * transaccionales, promocionales y de marketing con múltiples proveedores.
 *
 * @package App\Models\Notification
 *
 * @property string $email_id UUID primary key
 * @property string $notification_id FK to notifications
 * @property string $user_id FK to users
 * @property string $email_type Type of email (EMAIL_TYPES)
 * @property string $provider Email provider (PROVIDERS)
 * @property string $category Email category
 * @property string $priority Email priority
 * @property string $status Delivery status (DELIVERY_STATUSES)
 * @property string $from_email Sender email address
 * @property string|null $from_name Sender name
 * @property string $to_email Recipient email address
 * @property string|null $to_name Recipient name
 * @property array|null $cc_emails CC recipients
 * @property array|null $bcc_emails BCC recipients
 * @property string $subject Email subject
 * @property string|null $preheader Email preheader text
 * @property string $html_body HTML email body
 * @property string|null $text_body Plain text email body
 * @property string|null $template_id Email template identifier
 * @property string|null $campaign_id Campaign identifier
 * @property string|null $ab_test_variant A/B test variant (A, B, C, etc.)
 * @property array|null $attachments Email attachments
 * @property array|null $tags Email tags for filtering
 * @property array|null $metadata Additional metadata (encrypted)
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $clicked_at
 * @property Carbon|null $bounced_at
 * @property Carbon|null $unsubscribed_at
 * @property int $open_count Number of times opened
 * @property int $click_count Number of link clicks
 * @property string|null $provider_message_id Provider's message ID
 * @property string|null $bounce_reason Bounce reason if bounced
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read Notification $notification
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder byUser(string $userId)
 * @method static \Illuminate\Database\Eloquent\Builder byType(string $emailType)
 * @method static \Illuminate\Database\Eloquent\Builder byProvider(string $provider)
 * @method static \Illuminate\Database\Eloquent\Builder byCategory(string $category)
 * @method static \Illuminate\Database\Eloquent\Builder byStatus(string $status)
 * @method static \Illuminate\Database\Eloquent\Builder sent()
 * @method static \Illuminate\Database\Eloquent\Builder delivered()
 * @method static \Illuminate\Database\Eloquent\Builder opened()
 * @method static \Illuminate\Database\Eloquent\Builder clicked()
 * @method static \Illuminate\Database\Eloquent\Builder bounced()
 * @method static \Illuminate\Database\Eloquent\Builder failed()
 * @method static \Illuminate\Database\Eloquent\Builder unsubscribed()
 * @method static \Illuminate\Database\Eloquent\Builder transactional()
 * @method static \Illuminate\Database\Eloquent\Builder promotional()
 * @method static \Illuminate\Database\Eloquent\Builder highPriority()
 * @method static \Illuminate\Database\Eloquent\Builder inCampaign(string $campaignId)
 * @method static \Illuminate\Database\Eloquent\Builder abTestVariant(string $variant)
 * @method static \Illuminate\Database\Eloquent\Builder recentEmails(int $days = 7)
 */
class EmailNotification extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Primary key configuration
     */
    protected $primaryKey = 'email_id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The table associated with the model.
     */
    protected $table = 'email_notifications';

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_id' => 'string',
        'notification_id' => 'string',
        'user_id' => 'string',
        'cc_emails' => 'array',
        'bcc_emails' => 'array',
        'attachments' => 'array',
        'tags' => 'array',
        'metadata' => 'encrypted:array',
        'open_count' => 'integer',
        'click_count' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Email Types - Transactional
     */
    public const TYPE_WELCOME = 'welcome';
    public const TYPE_EMAIL_VERIFICATION = 'email_verification';
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_ACCOUNT_SUSPENSION = 'account_suspension';
    public const TYPE_SECURITY_ALERT = 'security_alert';
    public const TYPE_LOGIN_NOTIFICATION = 'login_notification';
    public const TYPE_TWO_FACTOR_CODE = 'two_factor_code';

    /**
     * Email Types - Dating Activity
     */
    public const TYPE_NEW_MATCH_EMAIL = 'new_match_email';
    public const TYPE_DAILY_MATCHES = 'daily_matches';
    public const TYPE_WEEKLY_DIGEST = 'weekly_digest';
    public const TYPE_PROFILE_VIEWS_SUMMARY = 'profile_views_summary';
    public const TYPE_MESSAGES_WAITING = 'messages_waiting';
    public const TYPE_SUPER_LIKE_NOTIFICATION = 'super_like_notification';
    public const TYPE_MATCH_EXPIRING = 'match_expiring';

    /**
     * Email Types - Engagement & Re-engagement
     */
    public const TYPE_COMEBACK_OFFER = 'comeback_offer';
    public const TYPE_INACTIVE_USER = 'inactive_user';
    public const TYPE_SPECIAL_PROMOTION = 'special_promotion';
    public const TYPE_PREMIUM_FEATURES = 'premium_features';
    public const TYPE_SUCCESS_STORIES = 'success_stories';
    public const TYPE_TIPS_AND_ADVICE = 'tips_and_advice';

    /**
     * Email Types - Commerce
     */
    public const TYPE_SUBSCRIPTION_EXPIRING = 'subscription_expiring';
    public const TYPE_PAYMENT_FAILED = 'payment_failed';
    public const TYPE_PAYMENT_SUCCESS = 'payment_success';
    public const TYPE_REFUND_PROCESSED = 'refund_processed';
    public const TYPE_INVOICE = 'invoice';
    public const TYPE_RECEIPT = 'receipt';

    /**
     * Email Types - Updates & Notifications
     */
    public const TYPE_PRIVACY_POLICY_UPDATE = 'privacy_policy_update';
    public const TYPE_TERMS_UPDATE = 'terms_update';
    public const TYPE_FEATURE_ANNOUNCEMENT = 'feature_announcement';
    public const TYPE_NEWSLETTER = 'newsletter';

    /**
     * Email Types - Moderation
     */
    public const TYPE_PROFILE_APPROVED = 'profile_approved';
    public const TYPE_PROFILE_REJECTED = 'profile_rejected';
    public const TYPE_REPORT_UPDATE = 'report_update';
    public const TYPE_SUPPORT_RESPONSE = 'support_response';

    /**
     * Email Types - Special Occasions
     */
    public const TYPE_BIRTHDAY_EMAIL = 'birthday_email';
    public const TYPE_ANNIVERSARY = 'anniversary';
    public const TYPE_SEASONAL_GREETINGS = 'seasonal_greetings';
    public const TYPE_VALENTINE_SPECIAL = 'valentine_special';

    /**
     * Email Providers
     */
    public const PROVIDER_SENDGRID = 'sendgrid';
    public const PROVIDER_MAILGUN = 'mailgun';
    public const PROVIDER_SES = 'ses'; // Amazon Simple Email Service
    public const PROVIDER_POSTMARK = 'postmark';
    public const PROVIDER_MAILCHIMP = 'mailchimp';
    public const PROVIDER_RESEND = 'resend';
    public const PROVIDER_SMTP = 'smtp';

    public const PROVIDERS = [
        self::PROVIDER_SENDGRID,
        self::PROVIDER_MAILGUN,
        self::PROVIDER_SES,
        self::PROVIDER_POSTMARK,
        self::PROVIDER_MAILCHIMP,
        self::PROVIDER_RESEND,
        self::PROVIDER_SMTP,
    ];

    /**
     * Email Categories
     */
    public const CATEGORY_TRANSACTIONAL = 'transactional';
    public const CATEGORY_PROMOTIONAL = 'promotional';
    public const CATEGORY_NEWSLETTER = 'newsletter';
    public const CATEGORY_NOTIFICATION = 'notification';
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_MARKETING = 'marketing';

    public const CATEGORIES = [
        self::CATEGORY_TRANSACTIONAL,
        self::CATEGORY_PROMOTIONAL,
        self::CATEGORY_NEWSLETTER,
        self::CATEGORY_NOTIFICATION,
        self::CATEGORY_SYSTEM,
        self::CATEGORY_MARKETING,
    ];

    /**
     * Priority Levels
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    /**
     * Delivery Statuses
     */
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_OPENED = 'opened';
    public const STATUS_CLICKED = 'clicked';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_SPAM = 'spam';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';
    public const STATUS_FAILED = 'failed';

    public const DELIVERY_STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_SENT,
        self::STATUS_DELIVERED,
        self::STATUS_OPENED,
        self::STATUS_CLICKED,
        self::STATUS_BOUNCED,
        self::STATUS_SPAM,
        self::STATUS_UNSUBSCRIBED,
        self::STATUS_FAILED,
    ];

    /**
     * Bounce Types
     */
    public const BOUNCE_HARD = 'hard'; // Permanent failure
    public const BOUNCE_SOFT = 'soft'; // Temporary failure
    public const BOUNCE_COMPLAINT = 'complaint'; // Spam complaint

    /**
     * A/B Test Variants
     */
    public const AB_VARIANT_CONTROL = 'control';
    public const AB_VARIANT_A = 'variant_a';
    public const AB_VARIANT_B = 'variant_b';
    public const AB_VARIANT_C = 'variant_c';

    /**
     * Cache Configuration
     */
    public const CACHE_PREFIX = 'email_notification:';
    public const CACHE_USER_EMAILS_TTL = 600; // 10 minutes
    public const CACHE_CAMPAIGN_STATS_TTL = 300; // 5 minutes

    /**
     * Rate Limiting
     */
    public const MAX_EMAILS_PER_USER_PER_DAY = 50;
    public const MAX_PROMOTIONAL_PER_WEEK = 3;
    public const MAX_ATTACHMENT_SIZE_MB = 10;

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the parent notification.
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id', 'notification_id');
    }

    /**
     * Get the user that owns the email notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
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
     * Scope: Filter by email type
     */
    public function scopeByType($query, string $emailType)
    {
        return $query->where('email_type', $emailType);
    }

    /**
     * Scope: Filter by provider
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope: Filter by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Get sent emails
     */
    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at');
    }

    /**
     * Scope: Get delivered emails
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Scope: Get opened emails
     */
    public function scopeOpened($query)
    {
        return $query->whereNotNull('opened_at');
    }

    /**
     * Scope: Get clicked emails
     */
    public function scopeClicked($query)
    {
        return $query->whereNotNull('clicked_at');
    }

    /**
     * Scope: Get bounced emails
     */
    public function scopeBounced($query)
    {
        return $query->where('status', self::STATUS_BOUNCED);
    }

    /**
     * Scope: Get failed emails
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope: Get unsubscribed emails
     */
    public function scopeUnsubscribed($query)
    {
        return $query->whereNotNull('unsubscribed_at');
    }

    /**
     * Scope: Get transactional emails
     */
    public function scopeTransactional($query)
    {
        return $query->where('category', self::CATEGORY_TRANSACTIONAL);
    }

    /**
     * Scope: Get promotional emails
     */
    public function scopePromotional($query)
    {
        return $query->where('category', self::CATEGORY_PROMOTIONAL);
    }

    /**
     * Scope: Get high priority emails
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    /**
     * Scope: Filter by campaign
     */
    public function scopeInCampaign($query, string $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope: Filter by A/B test variant
     */
    public function scopeAbTestVariant($query, string $variant)
    {
        return $query->where('ab_test_variant', $variant);
    }

    /**
     * Scope: Get recent emails
     */
    public function scopeRecentEmails($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Check if email was sent
     */
    public function getIsSentAttribute(): bool
    {
        return !is_null($this->sent_at);
    }

    /**
     * Check if email was delivered
     */
    public function getIsDeliveredAttribute(): bool
    {
        return !is_null($this->delivered_at);
    }

    /**
     * Check if email was opened
     */
    public function getIsOpenedAttribute(): bool
    {
        return !is_null($this->opened_at);
    }

    /**
     * Check if email was clicked
     */
    public function getIsClickedAttribute(): bool
    {
        return !is_null($this->clicked_at);
    }

    /**
     * Check if email bounced
     */
    public function getIsBouncedAttribute(): bool
    {
        return !is_null($this->bounced_at);
    }

    /**
     * Check if user unsubscribed
     */
    public function getIsUnsubscribedAttribute(): bool
    {
        return !is_null($this->unsubscribed_at);
    }

    /**
     * Get time to open (in minutes)
     */
    public function getTimeToOpenAttribute(): ?float
    {
        if (!$this->is_opened || !$this->sent_at) {
            return null;
        }

        return round($this->opened_at->diffInMinutes($this->sent_at), 2);
    }

    /**
     * Get time to click (in minutes)
     */
    public function getTimeToClickAttribute(): ?float
    {
        if (!$this->is_clicked || !$this->sent_at) {
            return null;
        }

        return round($this->clicked_at->diffInMinutes($this->sent_at), 2);
    }

    /**
     * Get engagement score (0-100)
     */
    public function getEngagementScoreAttribute(): float
    {
        $score = 0;

        if ($this->is_delivered) $score += 25;
        if ($this->is_opened) $score += 35;
        if ($this->is_clicked) $score += 40;

        return $score;
    }

    /**
     * Get provider display name
     */
    public function getProviderNameAttribute(): string
    {
        return match ($this->provider) {
            self::PROVIDER_SENDGRID => 'SendGrid',
            self::PROVIDER_MAILGUN => 'Mailgun',
            self::PROVIDER_SES => 'Amazon SES',
            self::PROVIDER_POSTMARK => 'Postmark',
            self::PROVIDER_MAILCHIMP => 'Mailchimp',
            self::PROVIDER_RESEND => 'Resend',
            self::PROVIDER_SMTP => 'SMTP',
            default => 'Unknown',
        };
    }

    /**
     * Check if email has attachments
     */
    public function getHasAttachmentsAttribute(): bool
    {
        return !empty($this->attachments);
    }

    /**
     * Get total attachment size in MB
     */
    public function getAttachmentSizeAttribute(): float
    {
        if (empty($this->attachments)) {
            return 0;
        }

        $totalSize = collect($this->attachments)->sum('size');
        return round($totalSize / (1024 * 1024), 2);
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Send email notification
     */
    public function sendEmail(): bool
    {
        try {
            // Check if user can receive emails
            if (!$this->canSendToUser($this->user_id)) {
                return false;
            }

            // Check rate limits
            if (!$this->checkRateLimits()) {
                return false;
            }

            // Select provider
            $provider = $this->provider ?? $this->selectOptimalProvider();

            // Send via provider
            $result = $this->sendViaProvider($provider);

            if ($result['success']) {
                $this->update([
                    'provider' => $provider,
                    'status' => self::STATUS_SENT,
                    'sent_at' => now(),
                    'provider_message_id' => $result['message_id'] ?? null,
                ]);

                return true;
            }

            $this->update(['status' => self::STATUS_FAILED]);
            return false;
        } catch (\Exception $e) {
            Log::error('Email send failed', [
                'email_id' => $this->email_id,
                'error' => $e->getMessage(),
            ]);

            $this->update(['status' => self::STATUS_FAILED]);
            return false;
        }
    }

    /**
     * Send via specific provider
     */
    protected function sendViaProvider(string $provider): array
    {
        return match ($provider) {
            self::PROVIDER_SENDGRID => $this->sendViaSendGrid(),
            self::PROVIDER_MAILGUN => $this->sendViaMailgun(),
            self::PROVIDER_SES => $this->sendViaSES(),
            self::PROVIDER_POSTMARK => $this->sendViaPostmark(),
            default => ['success' => false, 'error' => 'Unsupported provider'],
        };
    }

    /**
     * Send via SendGrid
     */
    protected function sendViaSendGrid(): array
    {
        try {
            $payload = [
                'personalizations' => [
                    [
                        'to' => [['email' => $this->to_email, 'name' => $this->to_name]],
                        'subject' => $this->subject,
                    ],
                ],
                'from' => [
                    'email' => $this->from_email,
                    'name' => $this->from_name,
                ],
                'content' => [
                    ['type' => 'text/html', 'value' => $this->html_body],
                ],
                'custom_args' => [
                    'email_id' => $this->email_id,
                    'notification_id' => $this->notification_id,
                ],
            ];

            if ($this->text_body) {
                $payload['content'][] = ['type' => 'text/plain', 'value' => $this->text_body];
            }

            if (!empty($this->cc_emails)) {
                $payload['personalizations'][0]['cc'] = array_map(fn($email) => ['email' => $email], $this->cc_emails);
            }

            if (!empty($this->tags)) {
                $payload['categories'] = $this->tags;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.sendgrid.api_key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.sendgrid.com/v3/mail/send', $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->header('X-Message-Id'),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['errors'][0]['message'] ?? 'Unknown error',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send via Mailgun
     */
    protected function sendViaMailgun(): array
    {
        try {
            $domain = config('services.mailgun.domain');
            $apiKey = config('services.mailgun.secret');

            $payload = [
                'from' => "{$this->from_name} <{$this->from_email}>",
                'to' => $this->to_email,
                'subject' => $this->subject,
                'html' => $this->html_body,
                'o:tag' => $this->tags ?? [],
                'o:tracking' => 'yes',
                'o:tracking-clicks' => 'yes',
                'o:tracking-opens' => 'yes',
                'v:email_id' => $this->email_id,
            ];

            if ($this->text_body) {
                $payload['text'] = $this->text_body;
            }

            $response = Http::withBasicAuth('api', $apiKey)
                ->asForm()
                ->post("https://api.mailgun.net/v3/{$domain}/messages", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json()['id'] ?? null,
                ];
            }

            return ['success' => false, 'error' => $response->json()['message'] ?? 'Unknown error'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send via Amazon SES
     */
    protected function sendViaSES(): array
    {
        try {
            // AWS SES implementation would go here
            // Using Laravel's Mail facade as example
            Mail::send([], [], function ($message) {
                $message->from($this->from_email, $this->from_name)
                    ->to($this->to_email, $this->to_name)
                    ->subject($this->subject)
                    ->setBody($this->html_body, 'text/html');

                if ($this->text_body) {
                    $message->addPart($this->text_body, 'text/plain');
                }
            });

            return ['success' => true, 'message_id' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send via Postmark
     */
    protected function sendViaPostmark(): array
    {
        try {
            $payload = [
                'From' => "{$this->from_name} <{$this->from_email}>",
                'To' => $this->to_email,
                'Subject' => $this->subject,
                'HtmlBody' => $this->html_body,
                'TextBody' => $this->text_body,
                'TrackOpens' => true,
                'TrackLinks' => 'HtmlAndText',
                'Metadata' => [
                    'email_id' => $this->email_id,
                    'notification_id' => $this->notification_id,
                ],
            ];

            if (!empty($this->tags)) {
                $payload['Tag'] = implode(',', $this->tags);
            }

            $response = Http::withHeaders([
                'X-Postmark-Server-Token' => config('services.postmark.token'),
                'Content-Type' => 'application/json',
            ])->post('https://api.postmarkapp.com/email', $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json()['MessageID'] ?? null,
                ];
            }

            return ['success' => false, 'error' => $response->json()['Message'] ?? 'Unknown error'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Select optimal email provider based on category and performance
     */
    protected function selectOptimalProvider(): string
    {
        // Transactional emails prefer reliable providers
        if ($this->category === self::CATEGORY_TRANSACTIONAL) {
            return self::PROVIDER_POSTMARK;
        }

        // Marketing emails prefer feature-rich providers
        if ($this->category === self::CATEGORY_MARKETING) {
            return self::PROVIDER_SENDGRID;
        }

        // Default to SendGrid
        return self::PROVIDER_SENDGRID;
    }

    /**
     * Check if user can receive this email
     */
    protected function canSendToUser(string $userId): bool
    {
        // Check if user has unsubscribed
        $unsubscribed = DB::table('email_unsubscribes')
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->where('email_type', $this->email_type)
                    ->orWhere('category', $this->category);
            })
            ->exists();

        if ($unsubscribed && $this->category !== self::CATEGORY_TRANSACTIONAL) {
            return false;
        }

        // Check if email is in bounce list
        $bounced = DB::table('email_bounces')
            ->where('email', $this->to_email)
            ->where('bounce_type', self::BOUNCE_HARD)
            ->exists();

        return !$bounced;
    }

    /**
     * Check rate limits
     */
    protected function checkRateLimits(): bool
    {
        // Check daily limit
        $dailyCount = self::byUser($this->user_id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($dailyCount >= self::MAX_EMAILS_PER_USER_PER_DAY) {
            return false;
        }

        // Check promotional weekly limit
        if ($this->category === self::CATEGORY_PROMOTIONAL) {
            $weeklyPromo = self::byUser($this->user_id)
                ->promotional()
                ->where('created_at', '>=', now()->subWeek())
                ->count();

            if ($weeklyPromo >= self::MAX_PROMOTIONAL_PER_WEEK) {
                return false;
            }
        }

        return true;
    }

    /**
     * Track email open
     */
    public function trackOpen(): bool
    {
        $updated = $this->update([
            'opened_at' => $this->opened_at ?? now(),
            'status' => self::STATUS_OPENED,
            'open_count' => DB::raw('open_count + 1'),
        ]);

        Cache::tags(['email_stats'])->flush();

        return $updated;
    }

    /**
     * Track email click
     */
    public function trackClick(?string $linkUrl): bool
    {
        $updated = $this->update([
            'clicked_at' => $this->clicked_at ?? now(),
            'status' => self::STATUS_CLICKED,
            'click_count' => DB::raw('click_count + 1'),
        ]);

        // Store click details if needed
        if ($linkUrl) {
            DB::table('email_clicks')->insert([
                'email_id' => $this->email_id,
                'link_url' => $linkUrl,
                'clicked_at' => now(),
            ]);
        }

        Cache::tags(['email_stats'])->flush();

        return $updated;
    }

    /**
     * Process bounce
     */
    public function processBounce(string $reason, string $bounceType = self::BOUNCE_HARD): bool
    {
        $this->update([
            'status' => self::STATUS_BOUNCED,
            'bounced_at' => now(),
            'bounce_reason' => $reason,
        ]);

        // Store bounce record
        DB::table('email_bounces')->insert([
            'email' => $this->to_email,
            'bounce_type' => $bounceType,
            'reason' => $reason,
            'bounced_at' => now(),
        ]);

        return true;
    }

    /**
     * Process unsubscribe
     */
    public function unsubscribe(): bool
    {
        $this->update([
            'status' => self::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ]);

        // Store unsubscribe record
        DB::table('email_unsubscribes')->insert([
            'user_id' => $this->user_id,
            'email_type' => $this->email_type,
            'category' => $this->category,
            'unsubscribed_at' => now(),
        ]);

        return true;
    }

    /**
     * Process webhook from email provider
     */
    public static function processWebhook(string $provider, array $data): bool
    {
        try {
            $emailId = $data['custom_args']['email_id'] ?? $data['metadata']['email_id'] ?? null;

            if (!$emailId) {
                return false;
            }

            $email = self::find($emailId);

            if (!$email) {
                return false;
            }

            // Process different event types
            $eventType = $data['event'] ?? null;

            return match ($eventType) {
                'delivered' => $email->update(['status' => self::STATUS_DELIVERED, 'delivered_at' => now()]),
                'open', 'opened' => $email->trackOpen(),
                'click', 'clicked' => $email->trackClick($data['url'] ?? null),
                'bounce', 'bounced' => $email->processBounce($data['reason'] ?? 'Unknown', $data['type'] ?? self::BOUNCE_HARD),
                'spam', 'spamreport' => $email->update(['status' => self::STATUS_SPAM]),
                'unsubscribe', 'unsubscribed' => $email->unsubscribe(),
                default => false,
            };
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get email statistics
     */
    public static function getEmailStatistics(int $days = 30): array
    {
        $cacheKey = self::CACHE_PREFIX . "stats:{$days}";

        return Cache::remember($cacheKey, self::CACHE_CAMPAIGN_STATS_TTL, function () use ($days) {
            $startDate = now()->subDays($days);

            $emails = self::where('created_at', '>=', $startDate)->get();

            $sent = $emails->where('sent_at', '!=', null);
            $delivered = $emails->where('status', self::STATUS_DELIVERED);
            $opened = $emails->where('opened_at', '!=', null);
            $clicked = $emails->where('clicked_at', '!=', null);
            $bounced = $emails->where('status', self::STATUS_BOUNCED);

            return [
                'total_sent' => $sent->count(),
                'total_delivered' => $delivered->count(),
                'total_opened' => $opened->count(),
                'total_clicked' => $clicked->count(),
                'total_bounced' => $bounced->count(),
                'delivery_rate' => $sent->count() > 0
                    ? round(($delivered->count() / $sent->count()) * 100, 2)
                    : 0,
                'open_rate' => $delivered->count() > 0
                    ? round(($opened->count() / $delivered->count()) * 100, 2)
                    : 0,
                'click_rate' => $opened->count() > 0
                    ? round(($clicked->count() / $opened->count()) * 100, 2)
                    : 0,
                'bounce_rate' => $sent->count() > 0
                    ? round(($bounced->count() / $sent->count()) * 100, 2)
                    : 0,
                'by_category' => $emails->groupBy('category')->map->count(),
                'by_provider' => $emails->groupBy('provider')->map->count(),
            ];
        });
    }

    /**
     * Get campaign statistics
     */
    public static function getCampaignStatistics(string $campaignId): array
    {
        $emails = self::inCampaign($campaignId)->get();

        return [
            'total_sent' => $emails->count(),
            'delivered' => $emails->where('status', self::STATUS_DELIVERED)->count(),
            'opened' => $emails->where('opened_at', '!=', null)->count(),
            'clicked' => $emails->where('clicked_at', '!=', null)->count(),
            'bounced' => $emails->where('status', self::STATUS_BOUNCED)->count(),
            'unsubscribed' => $emails->where('unsubscribed_at', '!=', null)->count(),
            'open_rate' => $emails->count() > 0
                ? round(($emails->where('opened_at', '!=', null)->count() / $emails->count()) * 100, 2)
                : 0,
            'click_rate' => $emails->where('opened_at', '!=', null)->count() > 0
                ? round(($emails->where('clicked_at', '!=', null)->count() / $emails->where('opened_at', '!=', null)->count()) * 100, 2)
                : 0,
            'avg_time_to_open' => $emails->where('opened_at', '!=', null)
                ->avg(fn($e) => $e->opened_at->diffInMinutes($e->sent_at)),
        ];
    }

    /**
     * Get A/B test results
     */
    public static function getABTestResults(string $campaignId): array
    {
        $variants = self::inCampaign($campaignId)
            ->whereNotNull('ab_test_variant')
            ->get()
            ->groupBy('ab_test_variant');

        $results = [];

        foreach ($variants as $variant => $emails) {
            $results[$variant] = [
                'sent' => $emails->count(),
                'opened' => $emails->where('opened_at', '!=', null)->count(),
                'clicked' => $emails->where('clicked_at', '!=', null)->count(),
                'open_rate' => $emails->count() > 0
                    ? round(($emails->where('opened_at', '!=', null)->count() / $emails->count()) * 100, 2)
                    : 0,
                'click_rate' => $emails->where('opened_at', '!=', null)->count() > 0
                    ? round(($emails->where('clicked_at', '!=', null)->count() / $emails->where('opened_at', '!=', null)->count()) * 100, 2)
                    : 0,
            ];
        }

        return $results;
    }

    /**
     * Create a new collection instance
     */
    public function newCollection(array $models = []): EmailNotificationCollection
    {
        return new EmailNotificationCollection($models);
    }
}

/**
 * Custom Collection for EmailNotification Model
 */
class EmailNotificationCollection extends Collection
{
    /**
     * Get only sent emails
     */
    public function sent(): self
    {
        return $this->filter(fn($email) => !is_null($email->sent_at));
    }

    /**
     * Get only delivered emails
     */
    public function delivered(): self
    {
        return $this->filter(fn($email) => $email->status === EmailNotification::STATUS_DELIVERED);
    }

    /**
     * Get only opened emails
     */
    public function opened(): self
    {
        return $this->filter(fn($email) => !is_null($email->opened_at));
    }

    /**
     * Get only clicked emails
     */
    public function clicked(): self
    {
        return $this->filter(fn($email) => !is_null($email->clicked_at));
    }

    /**
     * Calculate delivery rate
     */
    public function deliveryRate(): float
    {
        $sent = $this->sent();

        if ($sent->isEmpty()) {
            return 0.0;
        }

        return round(($this->delivered()->count() / $sent->count()) * 100, 2);
    }

    /**
     * Calculate open rate
     */
    public function openRate(): float
    {
        $delivered = $this->delivered();

        if ($delivered->isEmpty()) {
            return 0.0;
        }

        return round(($this->opened()->count() / $delivered->count()) * 100, 2);
    }

    /**
     * Calculate click rate
     */
    public function clickRate(): float
    {
        $opened = $this->opened();

        if ($opened->isEmpty()) {
            return 0.0;
        }

        return round(($this->clicked()->count() / $opened->count()) * 100, 2);
    }

    /**
     * Get average time to open
     */
    public function averageTimeToOpen(): ?float
    {
        $openedEmails = $this->opened()
            ->filter(fn($email) => !is_null($email->sent_at));

        if ($openedEmails->isEmpty()) {
            return null;
        }

        return round($openedEmails->avg(fn($email) =>
            $email->opened_at->diffInMinutes($email->sent_at)
        ), 2);
    }

    /**
     * Get average engagement score
     */
    public function averageEngagementScore(): float
    {
        if ($this->isEmpty()) {
            return 0.0;
        }

        return round($this->avg('engagement_score'), 2);
    }
}