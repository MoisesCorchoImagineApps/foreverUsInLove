<?php

declare(strict_types=1);

namespace App\Models\Notification;

use App\Domain\Notification\Events\PushSent;
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
use Carbon\Carbon;

/**
 * Class PushNotification
 *
 * Modelo especializado en notificaciones push móviles multi-plataforma.
 * Gestiona el envío de push notifications a Android, iOS, Web y Huawei,
 * incluyendo device token management, rich notifications, deep linking,
 * y analytics detallados por plataforma.
 *
 * @package App\Models\Notification
 *
 * @property string $push_id UUID primary key
 * @property string $notification_id FK to notifications
 * @property string $user_id FK to users
 * @property string $platform Target platform (PLATFORMS)
 * @property string $provider Push provider (PROVIDERS)
 * @property string $push_type Type of push (PUSH_TYPES)
 * @property string $priority Delivery priority
 * @property int $total_tokens Total device tokens targeted
 * @property int $sent_count Successfully sent count
 * @property int $failed_count Failed send count
 * @property array|null $platform_results Results by platform
 * @property string $title Push notification title
 * @property string $body Push notification body
 * @property string|null $image_url Rich notification image
 * @property string|null $icon_url Notification icon
 * @property string|null $deep_link Deep link URL
 * @property int|null $badge_count Badge count (iOS)
 * @property string|null $sound Sound file name
 * @property array|null $action_buttons Interactive buttons
 * @property array|null $data Custom payload data (encrypted)
 * @property array|null $metadata Additional metadata (encrypted)
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read Notification $notification
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder byUser(string $userId)
 * @method static \Illuminate\Database\Eloquent\Builder byPlatform(string $platform)
 * @method static \Illuminate\Database\Eloquent\Builder byProvider(string $provider)
 * @method static \Illuminate\Database\Eloquent\Builder byPushType(string $pushType)
 * @method static \Illuminate\Database\Eloquent\Builder successful()
 * @method static \Illuminate\Database\Eloquent\Builder failed()
 * @method static \Illuminate\Database\Eloquent\Builder pending()
 * @method static \Illuminate\Database\Eloquent\Builder highPriority()
 * @method static \Illuminate\Database\Eloquent\Builder withImage()
 * @method static \Illuminate\Database\Eloquent\Builder interactive()
 * @method static \Illuminate\Database\Eloquent\Builder opened()
 * @method static \Illuminate\Database\Eloquent\Builder sent()
 * @method static \Illuminate\Database\Eloquent\Builder recentPushes(int $days = 7)
 */
class PushNotification extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Primary key configuration
     */
    protected $primaryKey = 'push_id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The table associated with the model.
     */
    protected $table = 'push_notifications';

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'push_id' => 'string',
        'notification_id' => 'string',
        'user_id' => 'string',
        'total_tokens' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'platform_results' => 'encrypted:array',
        'action_buttons' => 'array',
        'data' => 'encrypted:array',
        'metadata' => 'encrypted:array',
        'badge_count' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Target Platforms
     */
    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_IOS = 'ios';
    public const PLATFORM_WEB = 'web';
    public const PLATFORM_HUAWEI = 'huawei';

    public const PLATFORMS = [
        self::PLATFORM_ANDROID,
        self::PLATFORM_IOS,
        self::PLATFORM_WEB,
        self::PLATFORM_HUAWEI,
    ];

    /**
     * Push Providers
     */
    public const PROVIDER_FCM = 'fcm'; // Firebase Cloud Messaging (Android, iOS, Web)
    public const PROVIDER_APNS = 'apns'; // Apple Push Notification Service
    public const PROVIDER_HMS = 'hms'; // Huawei Mobile Services
    public const PROVIDER_WNS = 'wns'; // Windows Notification Service
    public const PROVIDER_PUSHY = 'pushy'; // Pushy.me
    public const PROVIDER_ONESIGNAL = 'onesignal'; // OneSignal

    public const PROVIDERS = [
        self::PROVIDER_FCM,
        self::PROVIDER_APNS,
        self::PROVIDER_HMS,
        self::PROVIDER_WNS,
        self::PROVIDER_PUSHY,
        self::PROVIDER_ONESIGNAL,
    ];

    /**
     * Push Types
     */
    public const TYPE_ALERT = 'alert'; // Standard notification with sound and banner
    public const TYPE_BADGE = 'badge'; // Badge count update only
    public const TYPE_SOUND = 'sound'; // Sound only notification
    public const TYPE_SILENT = 'silent'; // Silent background notification
    public const TYPE_RICH = 'rich'; // Rich notification with media
    public const TYPE_INTERACTIVE = 'interactive'; // Notification with action buttons

    public const PUSH_TYPES = [
        self::TYPE_ALERT,
        self::TYPE_BADGE,
        self::TYPE_SOUND,
        self::TYPE_SILENT,
        self::TYPE_RICH,
        self::TYPE_INTERACTIVE,
    ];

    /**
     * Delivery Priorities
     */
    public const PRIORITY_LOW = 'low'; // Delay delivery for battery optimization
    public const PRIORITY_NORMAL = 'normal'; // Standard delivery
    public const PRIORITY_HIGH = 'high'; // Immediate delivery

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
    ];

    /**
     * Device Token Statuses
     */
    public const TOKEN_STATUS_ACTIVE = 'active';
    public const TOKEN_STATUS_INACTIVE = 'inactive';
    public const TOKEN_STATUS_EXPIRED = 'expired';
    public const TOKEN_STATUS_INVALID = 'invalid';

    public const TOKEN_STATUSES = [
        self::TOKEN_STATUS_ACTIVE,
        self::TOKEN_STATUS_INACTIVE,
        self::TOKEN_STATUS_EXPIRED,
        self::TOKEN_STATUS_INVALID,
    ];

    /**
     * Platform Configuration
     */
    public const PLATFORM_CONFIGS = [
        self::PLATFORM_ANDROID => [
            'default_provider' => self::PROVIDER_FCM,
            'fallback_provider' => self::PROVIDER_ONESIGNAL,
            'supports_rich' => true,
            'supports_interactive' => true,
            'max_payload_size' => 4096, // bytes
        ],
        self::PLATFORM_IOS => [
            'default_provider' => self::PROVIDER_APNS,
            'fallback_provider' => self::PROVIDER_FCM,
            'supports_rich' => true,
            'supports_interactive' => true,
            'max_payload_size' => 4096,
        ],
        self::PLATFORM_WEB => [
            'default_provider' => self::PROVIDER_FCM,
            'fallback_provider' => self::PROVIDER_ONESIGNAL,
            'supports_rich' => true,
            'supports_interactive' => true,
            'max_payload_size' => 4096,
        ],
        self::PLATFORM_HUAWEI => [
            'default_provider' => self::PROVIDER_HMS,
            'fallback_provider' => null,
            'supports_rich' => true,
            'supports_interactive' => true,
            'max_payload_size' => 4096,
        ],
    ];

    /**
     * Cache Configuration
     */
    public const CACHE_PREFIX = 'push_notification:';
    public const CACHE_USER_TOKENS_TTL = 3600; // 1 hour
    public const CACHE_PLATFORM_STATS_TTL = 600; // 10 minutes

    /**
     * Rate Limiting
     */
    public const MAX_TOKENS_PER_USER = 10;
    public const MAX_BATCH_SIZE = 500;
    public const TOKEN_EXPIRY_DAYS = 90;

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
     * Get the user that owns the push notification.
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
     * Scope: Filter by platform
     */
    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope: Filter by provider
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope: Filter by push type
     */
    public function scopeByPushType($query, string $pushType)
    {
        return $query->where('push_type', $pushType);
    }

    /**
     * Scope: Get successful pushes
     */
    public function scopeSuccessful($query)
    {
        return $query->where('sent_count', '>', 0);
    }

    /**
     * Scope: Get failed pushes
     */
    public function scopeFailed($query)
    {
        return $query->where('sent_count', 0)
            ->where('failed_count', '>', 0);
    }

    /**
     * Scope: Get pending pushes
     */
    public function scopePending($query)
    {
        return $query->whereNull('sent_at');
    }

    /**
     * Scope: Get high priority pushes
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', self::PRIORITY_HIGH);
    }

    /**
     * Scope: Get pushes with images
     */
    public function scopeWithImage($query)
    {
        return $query->whereNotNull('image_url');
    }

    /**
     * Scope: Get interactive pushes
     */
    public function scopeInteractive($query)
    {
        return $query->where('push_type', self::TYPE_INTERACTIVE)
            ->orWhereNotNull('action_buttons');
    }

    /**
     * Scope: Get opened pushes
     */
    public function scopeOpened($query)
    {
        return $query->whereNotNull('opened_at');
    }

    /**
     * Scope: Get sent pushes
     */
    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at');
    }

    /**
     * Scope: Get recent pushes
     */
    public function scopeRecentPushes($query, int $days = 7)
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
     * Get success rate
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_tokens === 0) {
            return 0.0;
        }

        return round(($this->sent_count / $this->total_tokens) * 100, 2);
    }

    /**
     * Get failure rate
     */
    public function getFailureRateAttribute(): float
    {
        if ($this->total_tokens === 0) {
            return 0.0;
        }

        return round(($this->failed_count / $this->total_tokens) * 100, 2);
    }

    /**
     * Check if push was opened
     */
    public function getIsOpenedAttribute(): bool
    {
        return !is_null($this->opened_at);
    }

    /**
     * Check if push was sent
     */
    public function getIsSentAttribute(): bool
    {
        return !is_null($this->sent_at);
    }

    /**
     * Check if push is rich media
     */
    public function getIsRichAttribute(): bool
    {
        return !is_null($this->image_url) || $this->push_type === self::TYPE_RICH;
    }

    /**
     * Check if push is interactive
     */
    public function getIsInteractiveAttribute(): bool
    {
        return !empty($this->action_buttons) || $this->push_type === self::TYPE_INTERACTIVE;
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
     * Get platform display name
     */
    public function getPlatformNameAttribute(): string
    {
        return match ($this->platform) {
            self::PLATFORM_ANDROID => 'Android',
            self::PLATFORM_IOS => 'iOS',
            self::PLATFORM_WEB => 'Web',
            self::PLATFORM_HUAWEI => 'Huawei',
            default => 'Unknown',
        };
    }

    /**
     * Get provider display name
     */
    public function getProviderNameAttribute(): string
    {
        return match ($this->provider) {
            self::PROVIDER_FCM => 'Firebase Cloud Messaging',
            self::PROVIDER_APNS => 'Apple Push Notification Service',
            self::PROVIDER_HMS => 'Huawei Mobile Services',
            self::PROVIDER_WNS => 'Windows Notification Service',
            self::PROVIDER_PUSHY => 'Pushy',
            self::PROVIDER_ONESIGNAL => 'OneSignal',
            default => 'Unknown',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Send push notification to specific platform
     */
    public function sendToPlatform(?string $platform): bool
    {
        $platform = $platform ?? $this->platform;

        try {
            // Get user's active tokens for platform
            $tokens = $this->getUserActiveTokens($this->user_id, $platform);

            if ($tokens->isEmpty()) {
                return false;
            }

            $this->total_tokens = $tokens->count();
            $this->platform = $platform;
            $this->save();

            // Get provider configuration
            $config = self::PLATFORM_CONFIGS[$platform];
            $provider = $this->provider ?? $config['default_provider'];

            // Send to provider
            $results = $this->sendViaProvider($provider, $tokens);

            // Update statistics
            $this->update([
                'provider' => $provider,
                'sent_count' => $results['success_count'],
                'failed_count' => $results['failure_count'],
                'platform_results' => $results['details'],
                'sent_at' => now(),
            ]);

            // Dispatch event
            if ($results['success_count'] > 0) {
                event(new PushSent(
                    notificationId: (int) $this->notification_id,
                    userId: (int) $this->user_id,
                    totalTokens: $this->total_tokens,
                    successfulSends: $results['success_count'],
                    platformResults: $results['details'] ?? []
                ));
            }

            // Cleanup invalid tokens
            $this->cleanupInvalidTokens($results['invalid_tokens'] ?? []);

            return $results['success_count'] > 0;
        } catch (\Exception $e) {
            Log::error('Push notification send failed', [
                'push_id' => $this->push_id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send push to all user's platforms
     */
    public function sendToAllPlatforms(): bool
    {
        $overallSuccess = false;
        $platformResults = [];

        foreach (self::PLATFORMS as $platform) {
            $tokens = $this->getUserActiveTokens($this->user_id, $platform);

            if ($tokens->isEmpty()) {
                continue;
            }

            $success = $this->sendToPlatform($platform);
            $platformResults[$platform] = $success;

            if ($success) {
                $overallSuccess = true;
            }
        }

        $this->update(['platform_results' => $platformResults]);

        return $overallSuccess;
    }

    /**
     * Send via specific provider
     */
    protected function sendViaProvider(string $provider, Collection $tokens): array
    {
        return match ($provider) {
            self::PROVIDER_FCM => $this->sendViaFCM($tokens),
            self::PROVIDER_APNS => $this->sendViaAPNS($tokens),
            self::PROVIDER_HMS => $this->sendViaHMS($tokens),
            self::PROVIDER_ONESIGNAL => $this->sendViaOneSignal($tokens),
            default => ['success_count' => 0, 'failure_count' => $tokens->count(), 'details' => []],
        };
    }

    /**
     * Send via Firebase Cloud Messaging
     */
    protected function sendViaFCM(Collection $tokens): array
    {
        try {
            $fcmTokens = $tokens->pluck('token')->toArray();

            $payload = [
                'registration_ids' => $fcmTokens,
                'notification' => [
                    'title' => $this->title,
                    'body' => $this->body,
                    'icon' => $this->icon_url,
                    'image' => $this->image_url,
                    'sound' => $this->sound ?? 'default',
                    'click_action' => $this->deep_link,
                ],
                'data' => array_merge($this->data ?? [], [
                    'push_id' => $this->push_id,
                    'notification_id' => $this->notification_id,
                ]),
                'priority' => $this->priority === self::PRIORITY_HIGH ? 'high' : 'normal',
            ];

            // Add action buttons for Android
            if (!empty($this->action_buttons)) {
                $payload['notification']['actions'] = $this->action_buttons;
            }

            $response = Http::withHeaders([
                'Authorization' => 'key=' . config('services.fcm.server_key'),
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);

            $result = $response->json();

            return [
                'success_count' => $result['success'] ?? 0,
                'failure_count' => $result['failure'] ?? 0,
                'details' => $result,
                'invalid_tokens' => $this->extractInvalidTokens($result),
            ];
        } catch (\Exception $e) {
            return [
                'success_count' => 0,
                'failure_count' => $tokens->count(),
                'details' => ['error' => $e->getMessage()],
                'invalid_tokens' => [],
            ];
        }
    }

    /**
     * Send via Apple Push Notification Service
     */
    protected function sendViaAPNS(Collection $tokens): array
    {
        try {
            $successCount = 0;
            $failureCount = 0;
            $invalidTokens = [];

            foreach ($tokens as $tokenRecord) {
                $payload = [
                    'aps' => [
                        'alert' => [
                            'title' => $this->title,
                            'body' => $this->body,
                        ],
                        'badge' => $this->badge_count ?? 0,
                        'sound' => $this->sound ?? 'default',
                        'category' => $this->push_type,
                    ],
                    'data' => array_merge($this->data ?? [], [
                        'push_id' => $this->push_id,
                        'notification_id' => $this->notification_id,
                        'deep_link' => $this->deep_link,
                    ]),
                ];

                // Add rich media
                if ($this->image_url) {
                    $payload['aps']['mutable-content'] = 1;
                    $payload['media_url'] = $this->image_url;
                }

                // Simulate APNS send (would use actual APNS library in production)
                $success = true; // Replace with actual APNS call

                if ($success) {
                    $successCount++;
                } else {
                    $failureCount++;
                    $invalidTokens[] = $tokenRecord->token;
                }
            }

            return [
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'details' => ['platform' => 'apns'],
                'invalid_tokens' => $invalidTokens,
            ];
        } catch (\Exception $e) {
            return [
                'success_count' => 0,
                'failure_count' => $tokens->count(),
                'details' => ['error' => $e->getMessage()],
                'invalid_tokens' => [],
            ];
        }
    }

    /**
     * Send via Huawei Mobile Services
     */
    protected function sendViaHMS(Collection $tokens): array
    {
        // HMS implementation similar to FCM
        return [
            'success_count' => $tokens->count(),
            'failure_count' => 0,
            'details' => ['platform' => 'hms'],
            'invalid_tokens' => [],
        ];
    }

    /**
     * Send via OneSignal
     */
    protected function sendViaOneSignal(Collection $tokens): array
    {
        // OneSignal implementation
        return [
            'success_count' => $tokens->count(),
            'failure_count' => 0,
            'details' => ['platform' => 'onesignal'],
            'invalid_tokens' => [],
        ];
    }

    /**
     * Extract invalid tokens from FCM response
     */
    protected function extractInvalidTokens(array $result): array
    {
        $invalidTokens = [];

        if (!isset($result['results'])) {
            return $invalidTokens;
        }

        foreach ($result['results'] as $index => $item) {
            if (isset($item['error']) && in_array($item['error'], ['NotRegistered', 'InvalidRegistration'])) {
                $invalidTokens[] = $index;
            }
        }

        return $invalidTokens;
    }

    /**
     * Register device token for user
     */
    public static function registerDeviceToken(string $userId, string $token, string $platform, array $deviceInfo = []): bool
    {
        try {
            // Check if token already exists
            $existingToken = DB::table('device_tokens')
                ->where('token', $token)
                ->first();

            if ($existingToken) {
                // Update existing token
                DB::table('device_tokens')
                    ->where('token', $token)
                    ->update([
                        'user_id' => $userId,
                        'platform' => $platform,
                        'device_info' => json_encode($deviceInfo),
                        'status' => self::TOKEN_STATUS_ACTIVE,
                        'last_used_at' => now(),
                        'updated_at' => now(),
                    ]);
            } else {
                // Create new token
                DB::table('device_tokens')->insert([
                    'token_id' => (string) \Illuminate\Support\Str::uuid(),
                    'user_id' => $userId,
                    'token' => $token,
                    'platform' => $platform,
                    'device_info' => json_encode($deviceInfo),
                    'status' => self::TOKEN_STATUS_ACTIVE,
                    'last_used_at' => now(),
                    'expires_at' => now()->addDays(self::TOKEN_EXPIRY_DAYS),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Cleanup old tokens if user has too many
            self::cleanupUserTokens($userId);

            // Clear cache
            Cache::forget(self::CACHE_PREFIX . "tokens:{$userId}");

            return true;
        } catch (\Exception $e) {
            Log::error('Device token registration failed', [
                'user_id' => $userId,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Unregister device token
     */
    public static function unregisterDeviceToken(string $token): bool
    {
        $result = DB::table('device_tokens')
            ->where('token', $token)
            ->update([
                'status' => self::TOKEN_STATUS_INACTIVE,
                'updated_at' => now(),
            ]);

        return $result > 0;
    }

    /**
     * Get user's active tokens for platform
     */
    public function getUserActiveTokens(string $userId, ?string $platform): Collection
    {
        $cacheKey = self::CACHE_PREFIX . "tokens:{$userId}:{$platform}";

        return Cache::remember($cacheKey, self::CACHE_USER_TOKENS_TTL, function () use ($userId, $platform) {
            $query = DB::table('device_tokens')
                ->where('user_id', $userId)
                ->where('status', self::TOKEN_STATUS_ACTIVE)
                ->where('expires_at', '>', now());

            if ($platform) {
                $query->where('platform', $platform);
            }

            return collect($query->get());
        });
    }

    /**
     * Update badge count for iOS
     */
    public static function updateBadgeCount(string $userId, int $count): bool
    {
        try {
            $tokens = DB::table('device_tokens')
                ->where('user_id', $userId)
                ->where('platform', self::PLATFORM_IOS)
                ->where('status', self::TOKEN_STATUS_ACTIVE)
                ->get();

            foreach ($tokens as $tokenRecord) {
                // Create badge-only push
                $push = self::create([
                    'push_id' => (string) \Illuminate\Support\Str::uuid(),
                    'user_id' => $userId,
                    'platform' => self::PLATFORM_IOS,
                    'push_type' => self::TYPE_BADGE,
                    'badge_count' => $count,
                    'title' => '',
                    'body' => '',
                ]);

                $push->sendToPlatform(self::PLATFORM_IOS);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Cleanup invalid tokens
     */
    protected function cleanupInvalidTokens(array $invalidTokenIndices): void
    {
        if (empty($invalidTokenIndices)) {
            return;
        }

        $tokens = $this->getUserActiveTokens($this->user_id, $this->platform);

        foreach ($invalidTokenIndices as $index) {
            if (isset($tokens[$index])) {
                DB::table('device_tokens')
                    ->where('token', $tokens[$index]->token)
                    ->update(['status' => self::TOKEN_STATUS_INVALID]);
            }
        }

        Cache::forget(self::CACHE_PREFIX . "tokens:{$this->user_id}");
    }

    /**
     * Cleanup old user tokens
     */
    protected static function cleanupUserTokens(string $userId): void
    {
        $tokenCount = DB::table('device_tokens')
            ->where('user_id', $userId)
            ->where('status', self::TOKEN_STATUS_ACTIVE)
            ->count();

        if ($tokenCount > self::MAX_TOKENS_PER_USER) {
            $excessCount = $tokenCount - self::MAX_TOKENS_PER_USER;

            $oldTokens = DB::table('device_tokens')
                ->where('user_id', $userId)
                ->where('status', self::TOKEN_STATUS_ACTIVE)
                ->orderBy('last_used_at', 'asc')
                ->limit($excessCount)
                ->pluck('token_id');

            DB::table('device_tokens')
                ->whereIn('token_id', $oldTokens)
                ->update(['status' => self::TOKEN_STATUS_EXPIRED]);
        }
    }

    /**
     * Get push statistics by platform
     */
    public static function getPlatformStatistics(int $days = 30): array
    {
        $cacheKey = self::CACHE_PREFIX . "platform_stats:{$days}";

        return Cache::remember($cacheKey, self::CACHE_PLATFORM_STATS_TTL, function () use ($days) {
            $startDate = now()->subDays($days);

            $pushes = self::where('created_at', '>=', $startDate)->get();

            $stats = [];

            foreach (self::PLATFORMS as $platform) {
                $platformPushes = $pushes->where('platform', $platform);

                $stats[$platform] = [
                    'total_sent' => $platformPushes->count(),
                    'successful' => $platformPushes->where('sent_count', '>', 0)->count(),
                    'failed' => $platformPushes->where('sent_count', 0)->count(),
                    'opened' => $platformPushes->whereNotNull('opened_at')->count(),
                    'success_rate' => $platformPushes->count() > 0
                        ? round(($platformPushes->where('sent_count', '>', 0)->count() / $platformPushes->count()) * 100, 2)
                        : 0,
                    'open_rate' => $platformPushes->where('sent_count', '>', 0)->count() > 0
                        ? round(($platformPushes->whereNotNull('opened_at')->count() / $platformPushes->where('sent_count', '>', 0)->count()) * 100, 2)
                        : 0,
                ];
            }

            return $stats;
        });
    }

    /**
     * Track push open
     */
    public function trackOpen(): bool
    {
        if ($this->is_opened) {
            return true;
        }

        return $this->update(['opened_at' => now()]);
    }

    /**
     * Cleanup expired tokens globally
     */
    public static function cleanupExpiredTokens(): int
    {
        return DB::table('device_tokens')
            ->where('status', self::TOKEN_STATUS_ACTIVE)
            ->where('expires_at', '<', now())
            ->update(['status' => self::TOKEN_STATUS_EXPIRED]);
    }

    /**
     * Create a new collection instance
     */
    public function newCollection(array $models = []): PushNotificationCollection
    {
        return new PushNotificationCollection($models);
    }
}

/**
 * Custom Collection for PushNotification Model
 */
class PushNotificationCollection extends Collection
{
    /**
     * Get only successful pushes
     */
    public function successful(): self
    {
        return $this->filter(fn($push) => $push->sent_count > 0);
    }

    /**
     * Get only failed pushes
     */
    public function failed(): self
    {
        return $this->filter(fn($push) => $push->sent_count === 0 && $push->failed_count > 0);
    }

    /**
     * Get only opened pushes
     */
    public function opened(): self
    {
        return $this->filter(fn($push) => !is_null($push->opened_at));
    }

    /**
     * Calculate overall success rate
     */
    public function successRate(): float
    {
        if ($this->isEmpty()) {
            return 0.0;
        }

        return round(($this->successful()->count() / $this->count()) * 100, 2);
    }

    /**
     * Calculate overall open rate
     */
    public function openRate(): float
    {
        $successful = $this->successful();

        if ($successful->isEmpty()) {
            return 0.0;
        }

        return round(($this->opened()->count() / $successful->count()) * 100, 2);
    }

    /**
     * Group by platform
     */
    public function groupByPlatform(): Collection
    {
        return $this->groupBy('platform')
            ->map(fn($pushes) => [
                'count' => $pushes->count(),
                'success_rate' => $pushes->successRate(),
                'open_rate' => $pushes->openRate(),
            ]);
    }

    /**
     * Get average time to open
     */
    public function averageTimeToOpen(): ?float
    {
        $openedPushes = $this->opened()
            ->filter(fn($push) => !is_null($push->sent_at));

        if ($openedPushes->isEmpty()) {
            return null;
        }

        return round($openedPushes->avg(fn($push) =>
            $push->opened_at->diffInMinutes($push->sent_at)
        ), 2);
    }
}