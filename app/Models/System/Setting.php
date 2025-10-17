<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Domain\System\Events\SettingUpdated;

/**
 * Class Setting
 *
 * Eloquent Model for system-wide configuration settings.
 * Manages application settings, feature flags, rate limits, and system configurations.
 *
 * @package App\Models\System
 * 
 * @property int $id
 * @property string $setting_key Unique identifier for the setting
 * @property mixed $setting_value The actual value (encrypted if sensitive)
 * @property string $category Category grouping (app_settings, feature_flags, etc.)
 * @property string $type Data type (string, integer, boolean, json, array)
 * @property bool $is_public Whether setting is publicly accessible
 * @property bool $is_encrypted Whether value is encrypted
 * @property mixed $default_value Default fallback value
 * @property string|null $validation_rules Validation rules for the value
 * @property string|null $description Human-readable description
 * @property array|null $options Available options for enum-type settings
 * @property bool $is_active Whether setting is currently active
 * @property bool $requires_restart Whether changing requires app restart
 * @property string|null $group Sub-grouping within category
 * @property int $display_order Order for UI display
 * @property array|null $metadata Additional metadata
 * @property int|null $updated_by_admin_id Admin who last updated
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \App\Models\System\Admin|null $updatedBy
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Setting byCategory(string $category)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting byGroup(string $group)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting publicOnly()
 * @method static \Illuminate\Database\Eloquent\Builder|Setting active()
 * @method static \Illuminate\Database\Eloquent\Builder|Setting searchByKey(string $search)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting requiresRestart()
 * @method static \Illuminate\Database\Eloquent\Builder|Setting featureFlags()
 * @method static \Illuminate\Database\Eloquent\Builder|Setting securitySettings()
 */
class Setting extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'settings';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'setting_key',
        'setting_value',
        'category',
        'type',
        'is_public',
        'is_encrypted',
        'default_value',
        'validation_rules',
        'description',
        'options',
        'is_active',
        'requires_restart',
        'group',
        'display_order',
        'metadata',
        'updated_by_admin_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_public' => 'boolean',
        'is_encrypted' => 'boolean',
        'is_active' => 'boolean',
        'requires_restart' => 'boolean',
        'options' => 'array',
        'metadata' => 'array',
        'display_order' => 'integer',
        'updated_by_admin_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<string>
     */
    protected $hidden = [
        'is_encrypted',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'decoded_value',
        'cache_key',
    ];

    // ==================== CONSTANTS ====================

    /**
     * Setting categories
     */
    const CATEGORY_APP_SETTINGS = 'app_settings';
    const CATEGORY_FEATURE_FLAGS = 'feature_flags';
    const CATEGORY_RATE_LIMITS = 'rate_limits';
    const CATEGORY_NOTIFICATION_SETTINGS = 'notification_settings';
    const CATEGORY_SECURITY_SETTINGS = 'security_settings';
    const CATEGORY_PAYMENT_SETTINGS = 'payment_settings';
    const CATEGORY_CONTENT_POLICIES = 'content_policies';
    const CATEGORY_LOCALIZATION_SETTINGS = 'localization_settings';

    /**
     * Data types
     */
    const TYPE_STRING = 'string';
    const TYPE_INTEGER = 'integer';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_FLOAT = 'float';
    const TYPE_JSON = 'json';
    const TYPE_ARRAY = 'array';
    const TYPE_DATETIME = 'datetime';
    const TYPE_URL = 'url';
    const TYPE_EMAIL = 'email';

    /**
     * Common setting keys - App Settings
     */
    const KEY_APP_NAME = 'app.name';
    const KEY_APP_URL = 'app.url';
    const KEY_APP_TIMEZONE = 'app.timezone';
    const KEY_APP_LOCALE = 'app.locale';
    const KEY_APP_DEBUG = 'app.debug';
    const KEY_APP_MAINTENANCE = 'app.maintenance_mode';

    /**
     * Feature Flags
     */
    const KEY_FEATURE_MATCHING = 'feature.matching_enabled';
    const KEY_FEATURE_VIDEO_CALL = 'feature.video_call_enabled';
    const KEY_FEATURE_GIFTS = 'feature.gifts_enabled';
    const KEY_FEATURE_STORIES = 'feature.stories_enabled';
    const KEY_FEATURE_LIVE_STREAM = 'feature.live_stream_enabled';
    const KEY_FEATURE_AI_MATCHING = 'feature.ai_matching_enabled';

    /**
     * Rate Limits
     */
    const KEY_RATE_API_LIMIT = 'rate_limit.api_requests_per_minute';
    const KEY_RATE_MESSAGE_LIMIT = 'rate_limit.messages_per_hour';
    const KEY_RATE_MATCH_LIMIT = 'rate_limit.swipes_per_day';
    const KEY_RATE_UPLOAD_LIMIT = 'rate_limit.photo_uploads_per_day';

    /**
     * Notification Settings
     */
    const KEY_NOTIF_EMAIL_ENABLED = 'notification.email_enabled';
    const KEY_NOTIF_PUSH_ENABLED = 'notification.push_enabled';
    const KEY_NOTIF_SMS_ENABLED = 'notification.sms_enabled';
    const KEY_NOTIF_BATCH_SIZE = 'notification.batch_size';

    /**
     * Security Settings
     */
    const KEY_SEC_2FA_REQUIRED = 'security.2fa_required';
    const KEY_SEC_PASSWORD_EXPIRY = 'security.password_expiry_days';
    const KEY_SEC_SESSION_TIMEOUT = 'security.session_timeout_minutes';
    const KEY_SEC_MAX_LOGIN_ATTEMPTS = 'security.max_login_attempts';
    const KEY_SEC_LOCKOUT_DURATION = 'security.lockout_duration_minutes';

    /**
     * Payment Settings
     */
    const KEY_PAY_PROVIDER = 'payment.provider';
    const KEY_PAY_CURRENCY = 'payment.default_currency';
    const KEY_PAY_TAX_RATE = 'payment.tax_rate';
    const KEY_PAY_MIN_AMOUNT = 'payment.minimum_amount';

    /**
     * Content Policies
     */
    const KEY_CONTENT_MAX_PHOTOS = 'content.max_photos_per_profile';
    const KEY_CONTENT_MAX_BIO_LENGTH = 'content.max_bio_length';
    const KEY_CONTENT_AUTO_MODERATION = 'content.auto_moderation_enabled';
    const KEY_CONTENT_AI_THRESHOLD = 'content.ai_confidence_threshold';

    /**
     * Localization Settings
     */
    const KEY_LOCALE_SUPPORTED = 'localization.supported_languages';
    const KEY_LOCALE_DEFAULT = 'localization.default_language';
    const KEY_LOCALE_AUTO_DETECT = 'localization.auto_detect_enabled';

    /**
     * Cache configuration
     */
    const CACHE_PREFIX = 'setting:';
    const CACHE_TTL = 3600; // 1 hour
    const CACHE_TAG = 'settings';

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the admin who last updated this setting.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy()
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    // ==================== QUERY SCOPES ====================

    /**
     * Scope a query to only include settings of a specific category.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $category
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to only include settings of a specific group.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $group
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope a query to only include public settings.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublicOnly($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope a query to only include active settings.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search settings by key.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchByKey($query, string $search)
    {
        return $query->where('setting_key', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
    }

    /**
     * Scope a query to only include settings that require restart.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRequiresRestart($query)
    {
        return $query->where('requires_restart', true);
    }

    /**
     * Scope a query to only include feature flags.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFeatureFlags($query)
    {
        return $query->where('category', self::CATEGORY_FEATURE_FLAGS);
    }

    /**
     * Scope a query to only include security settings.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSecuritySettings($query)
    {
        return $query->where('category', self::CATEGORY_SECURITY_SETTINGS);
    }

    // ==================== ACCESSORS & MUTATORS ====================

    /**
     * Get the decoded/decrypted value.
     *
     * @return mixed
     */
    public function getDecodedValueAttribute()
    {
        $value = $this->setting_value;

        // Decrypt if encrypted
        if ($this->is_encrypted && $value !== null) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception $e) {
                Log::error('Failed to decrypt setting', [
                    'key' => $this->setting_key,
                    'error' => $e->getMessage()
                ]);
                return $this->default_value;
            }
        }

        // Cast to appropriate type
        return $this->castValue($value);
    }

    /**
     * Get the cache key for this setting.
     *
     * @return string
     */
    public function getCacheKeyAttribute(): string
    {
        return self::CACHE_PREFIX . $this->setting_key;
    }

    /**
     * Set the setting value with automatic encryption.
     *
     * @param mixed $value
     * @return void
     */
    public function setSettingValueAttribute($value)
    {
        // Convert value to string for storage
        $stringValue = $this->valueToString($value);

        // Encrypt if required
        if ($this->is_encrypted && $stringValue !== null) {
            $stringValue = Crypt::encryptString($stringValue);
        }

        $this->attributes['setting_value'] = $stringValue;
    }

    /**
     * Set default value with type casting.
     *
     * @param mixed $value
     * @return void
     */
    public function setDefaultValueAttribute($value)
    {
        $this->attributes['default_value'] = $this->valueToString($value);
    }

    // ==================== STATIC METHODS ====================

    /**
     * Get a setting value by key with caching.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        return Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = self::where('setting_key', $key)
                          ->where('is_active', true)
                          ->first();

            if (!$setting) {
                return $default;
            }

            return $setting->decoded_value ?? $default;
        });
    }

    /**
     * Set a setting value by key.
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $adminId
     * @return bool
     */
    public static function set(string $key, $value, ?int $adminId = null): bool
    {
        $setting = self::firstOrNew(['setting_key' => $key]);
        
        $setting->setting_value = $value;
        
        if ($adminId) {
            $setting->updated_by_admin_id = $adminId;
        }

        $saved = $setting->save();

        if ($saved) {
            // Clear cache
            self::clearCache($key);
            
            // Dispatch event
            event(new SettingUpdated($setting));
        }

        return $saved;
    }

    /**
     * Get all settings by category.
     *
     * @param string $category
     * @param bool $activeOnly
     * @return Collection
     */
    public static function getByCategory(string $category, bool $activeOnly = true): Collection
    {
        $cacheKey = self::CACHE_PREFIX . "category:{$category}:" . ($activeOnly ? 'active' : 'all');

        return Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($category, $activeOnly) {
            $query = self::byCategory($category);
            
            if ($activeOnly) {
                $query->active();
            }

            return $query->orderBy('display_order')
                        ->orderBy('setting_key')
                        ->get()
                        ->mapWithKeys(function ($setting) {
                            return [$setting->setting_key => $setting->decoded_value];
                        });
        });
    }

    /**
     * Get all feature flags.
     *
     * @return array
     */
    public static function getFeatureFlags(): array
    {
        return self::getByCategory(self::CATEGORY_FEATURE_FLAGS)->toArray();
    }

    /**
     * Check if a feature is enabled.
     *
     * @param string $featureKey
     * @return bool
     */
    public static function isFeatureEnabled(string $featureKey): bool
    {
        $key = str_starts_with($featureKey, 'feature.') 
            ? $featureKey 
            : 'feature.' . $featureKey;

        return (bool) self::get($key, false);
    }

    /**
     * Update multiple settings at once.
     *
     * @param array $settings Key-value pairs
     * @param int|null $adminId
     * @return array Updated keys
     */
    public static function updateBatch(array $settings, ?int $adminId = null): array
    {
        $updatedKeys = [];

        foreach ($settings as $key => $value) {
            if (self::set($key, $value, $adminId)) {
                $updatedKeys[] = $key;
            }
        }

        return $updatedKeys;
    }

    /**
     * Reset a setting to its default value.
     *
     * @param string $key
     * @return bool
     */
    public static function reset(string $key): bool
    {
        $setting = self::where('setting_key', $key)->first();

        if (!$setting) {
            return false;
        }

        $setting->setting_value = $setting->default_value;
        $saved = $setting->save();

        if ($saved) {
            self::clearCache($key);
        }

        return $saved;
    }

    /**
     * Clear cache for a specific key or all settings.
     *
     * @param string|null $key
     * @return void
     */
    public static function clearCache(?string $key = null): void
    {
        if ($key) {
            Cache::tags([self::CACHE_TAG])->forget(self::CACHE_PREFIX . $key);
        } else {
            Cache::tags([self::CACHE_TAG])->flush();
        }
    }

    // ==================== INSTANCE METHODS ====================

    /**
     * Cast value to appropriate type.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function castValue($value)
    {
        if ($value === null) {
            return $this->default_value;
        }

        return match ($this->type) {
            self::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_INTEGER => (int) $value,
            self::TYPE_FLOAT => (float) $value,
            self::TYPE_JSON, self::TYPE_ARRAY => is_string($value) ? json_decode($value, true) : $value,
            self::TYPE_DATETIME => $value instanceof \DateTime ? $value : new \DateTime($value),
            default => $value,
        };
    }

    /**
     * Convert value to string for storage.
     *
     * @param mixed $value
     * @return string|null
     */
    protected function valueToString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * Validate setting value against validation rules.
     *
     * @param mixed $value
     * @return bool
     */
    public function validate($value): bool
    {
        if (!$this->validation_rules) {
            return true;
        }

        $validator = Validator::make(
            ['value' => $value],
            ['value' => $this->validation_rules]
        );

        return $validator->passes();
    }

    /**
     * Get setting with metadata.
     *
     * @return array
     */
    public function toDetailedArray(): array
    {
        return [
            'key' => $this->setting_key,
            'value' => $this->decoded_value,
            'default_value' => $this->default_value,
            'category' => $this->category,
            'group' => $this->group,
            'type' => $this->type,
            'description' => $this->description,
            'options' => $this->options,
            'is_public' => $this->is_public,
            'is_active' => $this->is_active,
            'requires_restart' => $this->requires_restart,
            'validation_rules' => $this->validation_rules,
            'metadata' => $this->metadata,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'updated_by' => $this->updatedBy?->only(['id', 'name', 'email']),
        ];
    }

    /**
     * Check if setting can be publicly accessed.
     *
     * @return bool
     */
    public function isPublic(): bool
    {
        return $this->is_public && $this->is_active;
    }

    /**
     * Check if setting requires application restart.
     *
     * @return bool
     */
    public function needsRestart(): bool
    {
        return $this->requires_restart;
    }

    // ==================== MODEL EVENTS ====================

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate cache key before saving
        static::saving(function ($setting) {
            // Validate value before saving
            if (!$setting->validate($setting->setting_value)) {
                throw new \InvalidArgumentException("Invalid value for setting: {$setting->setting_key}");
            }

            // Set default display order if not set
            if ($setting->display_order === null) {
                $setting->display_order = self::where('category', $setting->category)->max('display_order') + 1;
            }
        });

        // Clear cache after saving
        static::saved(function ($setting) {
            self::clearCache($setting->setting_key);
        });

        // Clear cache after deleting
        static::deleted(function ($setting) {
            self::clearCache($setting->setting_key);
        });
    }
}