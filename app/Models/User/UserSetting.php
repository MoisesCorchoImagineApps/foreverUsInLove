<?php

declare(strict_types=1);

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UserSetting Model - Configuraciones y preferencias de usuario
 * 
 * Maneja todas las configuraciones personalizables del usuario incluyendo
 * preferencias de matching, notificaciones, privacidad y discovery.
 * 
 * @property int $id
 * @property int $user_id
 * 
 * DISCOVERY & MATCHING PREFERENCES
 * @property string $discovery_mode Valores: everyone, nearby, selected
 * @property bool $show_me_in_discovery
 * @property int $min_age_preference
 * @property int $max_age_preference
 * @property int $max_distance_km
 * @property array|null $gender_preferences Array de géneros de interés
 * @property array|null $interest_preferences Array de intereses requeridos
 * @property bool $show_verified_only Mostrar solo perfiles verificados
 * @property bool $show_profiles_with_photos_only
 * @property string $distance_unit Valores: km, miles
 * 
 * NOTIFICATION PREFERENCES
 * @property bool $notifications_enabled
 * @property bool $email_notifications
 * @property bool $push_notifications
 * @property bool $sms_notifications
 * @property bool $notify_new_matches
 * @property bool $notify_new_messages
 * @property bool $notify_new_likes
 * @property bool $notify_profile_views
 * @property bool $notify_super_likes
 * @property bool $notify_new_followers
 * @property bool $notify_mentions
 * @property bool $notify_promotions
 * @property array|null $notification_schedule Horarios permitidos para notificaciones
 * @property bool $do_not_disturb
 * @property string|null $do_not_disturb_start Hora de inicio DND (HH:MM)
 * @property string|null $do_not_disturb_end Hora de fin DND (HH:MM)
 * 
 * PRIVACY SETTINGS
 * @property string $profile_visibility Valores: public, friends, private
 * @property bool $show_online_status
 * @property bool $show_last_active
 * @property bool $show_read_receipts
 * @property bool $show_typing_indicator
 * @property bool $allow_messages_from_non_matches
 * @property bool $incognito_mode Navegar sin dejar rastro
 * @property bool $hide_profile_from_contacts Ocultar de contactos del teléfono
 * @property array|null $blocked_users_ids Array de IDs de usuarios bloqueados
 * 
 * CHAT & COMMUNICATION
 * @property bool $auto_reply_enabled
 * @property string|null $auto_reply_message
 * @property bool $message_sound_enabled
 * @property string $message_preview Valores: full, name_only, none
 * @property int $chat_retention_days Días para mantener chats
 * 
 * CONTENT PREFERENCES
 * @property string $language Idioma preferido (ISO 639-1)
 * @property string $timezone
 * @property string $theme Valores: light, dark, auto
 * @property bool $reduce_motion Accesibilidad
 * @property bool $high_contrast Accesibilidad
 * @property string $content_filter Valores: none, moderate, strict
 * @property bool $show_explicit_content
 * 
 * SUBSCRIPTION & BILLING
 * @property bool $is_premium
 * @property string|null $subscription_tier Valores: free, basic, premium, elite
 * @property Carbon|null $subscription_expires_at
 * @property bool $auto_renew_subscription
 * @property string|null $preferred_currency
 * 
 * ADVANCED FEATURES
 * @property bool $boost_enabled
 * @property int $super_likes_remaining
 * @property int $rewinds_remaining
 * @property Carbon|null $last_boost_at
 * @property bool $passport_enabled Cambiar ubicación (premium)
 * @property array|null $passport_locations Ubicaciones guardadas
 * @property bool $read_receipts_enabled
 * @property bool $unlimited_likes
 * 
 * SAFETY & SECURITY
 * @property bool $photo_verification_required
 * @property bool $safe_mode_enabled
 * @property bool $share_location_enabled
 * @property bool $panic_mode_enabled Ocultar app rápidamente
 * @property array|null $trusted_contacts Array de contactos de emergencia
 * 
 * DATA & ANALYTICS
 * @property bool $data_collection_consent
 * @property bool $personalization_enabled
 * @property bool $analytics_tracking
 * @property array|null $custom_preferences Preferencias personalizadas adicionales
 * 
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property-read User $user
 * @property-read bool $is_premium_active
 * @property-read bool $has_notifications_enabled
 * 
 * @method static \Illuminate\Database\Eloquent\Builder premium()
 * @method static \Illuminate\Database\Eloquent\Builder withNotifications()
 * @method static \Illuminate\Database\Eloquent\Builder incognitoMode()
 */
class UserSetting extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'user_settings';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        
        // Discovery & Matching
        'discovery_mode',
        'show_me_in_discovery',
        'min_age_preference',
        'max_age_preference',
        'max_distance_km',
        'gender_preferences',
        'interest_preferences',
        'show_verified_only',
        'show_profiles_with_photos_only',
        'distance_unit',
        
        // Notifications
        'notifications_enabled',
        'email_notifications',
        'push_notifications',
        'sms_notifications',
        'notify_new_matches',
        'notify_new_messages',
        'notify_new_likes',
        'notify_profile_views',
        'notify_super_likes',
        'notify_new_followers',
        'notify_mentions',
        'notify_promotions',
        'notification_schedule',
        'do_not_disturb',
        'do_not_disturb_start',
        'do_not_disturb_end',
        
        // Privacy
        'profile_visibility',
        'show_online_status',
        'show_last_active',
        'show_read_receipts',
        'show_typing_indicator',
        'allow_messages_from_non_matches',
        'incognito_mode',
        'hide_profile_from_contacts',
        'blocked_users_ids',
        
        // Chat
        'auto_reply_enabled',
        'auto_reply_message',
        'message_sound_enabled',
        'message_preview',
        'chat_retention_days',
        
        // Content
        'language',
        'timezone',
        'theme',
        'reduce_motion',
        'high_contrast',
        'content_filter',
        'show_explicit_content',
        
        // Subscription
        'is_premium',
        'subscription_tier',
        'subscription_expires_at',
        'auto_renew_subscription',
        'preferred_currency',
        
        // Advanced
        'boost_enabled',
        'super_likes_remaining',
        'rewinds_remaining',
        'last_boost_at',
        'passport_enabled',
        'passport_locations',
        'read_receipts_enabled',
        'unlimited_likes',
        
        // Safety
        'photo_verification_required',
        'safe_mode_enabled',
        'share_location_enabled',
        'panic_mode_enabled',
        'trusted_contacts',
        
        // Data
        'data_collection_consent',
        'personalization_enabled',
        'analytics_tracking',
        'custom_preferences',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        // Discovery & Matching
        'show_me_in_discovery' => 'boolean',
        'min_age_preference' => 'integer',
        'max_age_preference' => 'integer',
        'max_distance_km' => 'integer',
        'gender_preferences' => 'array',
        'interest_preferences' => 'array',
        'show_verified_only' => 'boolean',
        'show_profiles_with_photos_only' => 'boolean',
        
        // Notifications
        'notifications_enabled' => 'boolean',
        'email_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'notify_new_matches' => 'boolean',
        'notify_new_messages' => 'boolean',
        'notify_new_likes' => 'boolean',
        'notify_profile_views' => 'boolean',
        'notify_super_likes' => 'boolean',
        'notify_new_followers' => 'boolean',
        'notify_mentions' => 'boolean',
        'notify_promotions' => 'boolean',
        'notification_schedule' => 'array',
        'do_not_disturb' => 'boolean',
        
        // Privacy
        'show_online_status' => 'boolean',
        'show_last_active' => 'boolean',
        'show_read_receipts' => 'boolean',
        'show_typing_indicator' => 'boolean',
        'allow_messages_from_non_matches' => 'boolean',
        'incognito_mode' => 'boolean',
        'hide_profile_from_contacts' => 'boolean',
        'blocked_users_ids' => 'array',
        
        // Chat
        'auto_reply_enabled' => 'boolean',
        'message_sound_enabled' => 'boolean',
        'chat_retention_days' => 'integer',
        
        // Content
        'reduce_motion' => 'boolean',
        'high_contrast' => 'boolean',
        'show_explicit_content' => 'boolean',
        
        // Subscription
        'is_premium' => 'boolean',
        'subscription_expires_at' => 'datetime',
        'auto_renew_subscription' => 'boolean',
        
        // Advanced
        'boost_enabled' => 'boolean',
        'super_likes_remaining' => 'integer',
        'rewinds_remaining' => 'integer',
        'last_boost_at' => 'datetime',
        'passport_enabled' => 'boolean',
        'passport_locations' => 'array',
        'read_receipts_enabled' => 'boolean',
        'unlimited_likes' => 'boolean',
        
        // Safety
        'photo_verification_required' => 'boolean',
        'safe_mode_enabled' => 'boolean',
        'share_location_enabled' => 'boolean',
        'panic_mode_enabled' => 'boolean',
        'trusted_contacts' => 'array',
        
        // Data
        'data_collection_consent' => 'boolean',
        'personalization_enabled' => 'boolean',
        'analytics_tracking' => 'boolean',
        'custom_preferences' => 'array',
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (UserSetting $settings) {
            // Set default values
            $settings->discovery_mode = $settings->discovery_mode ?? 'everyone';
            $settings->show_me_in_discovery = $settings->show_me_in_discovery ?? true;
            $settings->min_age_preference = $settings->min_age_preference ?? 18;
            $settings->max_age_preference = $settings->max_age_preference ?? 99;
            $settings->max_distance_km = $settings->max_distance_km ?? 50;
            $settings->distance_unit = $settings->distance_unit ?? 'km';
            
            $settings->notifications_enabled = $settings->notifications_enabled ?? true;
            $settings->email_notifications = $settings->email_notifications ?? true;
            $settings->push_notifications = $settings->push_notifications ?? true;
            $settings->notify_new_matches = $settings->notify_new_matches ?? true;
            $settings->notify_new_messages = $settings->notify_new_messages ?? true;
            $settings->notify_new_likes = $settings->notify_new_likes ?? true;
            
            $settings->profile_visibility = $settings->profile_visibility ?? 'public';
            $settings->show_online_status = $settings->show_online_status ?? true;
            $settings->show_last_active = $settings->show_last_active ?? true;
            $settings->show_read_receipts = $settings->show_read_receipts ?? true;
            $settings->show_typing_indicator = $settings->show_typing_indicator ?? true;
            
            $settings->message_sound_enabled = $settings->message_sound_enabled ?? true;
            $settings->message_preview = $settings->message_preview ?? 'full';
            $settings->chat_retention_days = $settings->chat_retention_days ?? 90;
            
            $settings->language = $settings->language ?? 'en';
            $settings->timezone = $settings->timezone ?? 'UTC';
            $settings->theme = $settings->theme ?? 'auto';
            $settings->content_filter = $settings->content_filter ?? 'moderate';
            
            $settings->subscription_tier = $settings->subscription_tier ?? 'free';
            $settings->is_premium = false;
            $settings->super_likes_remaining = 5;
            $settings->rewinds_remaining = 3;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user that owns the settings.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to filter premium users.
     */
    public function scopePremium($query)
    {
        return $query->where('is_premium', true)
            ->where('subscription_expires_at', '>', now());
    }

    /**
     * Scope to filter users with notifications enabled.
     */
    public function scopeWithNotifications($query)
    {
        return $query->where('notifications_enabled', true);
    }

    /**
     * Scope to filter users in incognito mode.
     */
    public function scopeIncognitoMode($query)
    {
        return $query->where('incognito_mode', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Check if premium subscription is active.
     */
    public function getIsPremiumActiveAttribute(): bool
    {
        return $this->is_premium && 
               $this->subscription_expires_at && 
               $this->subscription_expires_at->isFuture();
    }

    /**
     * Check if any notifications are enabled.
     */
    public function getHasNotificationsEnabledAttribute(): bool
    {
        return $this->notifications_enabled && (
            $this->email_notifications || 
            $this->push_notifications || 
            $this->sms_notifications
        );
    }

    /**
     * Check if currently in Do Not Disturb hours.
     */
    public function getIsInDndHoursAttribute(): bool
    {
        if (!$this->do_not_disturb || !$this->do_not_disturb_start || !$this->do_not_disturb_end) {
            return false;
        }

        $now = now()->format('H:i');
        $start = $this->do_not_disturb_start;
        $end = $this->do_not_disturb_end;

        // Handle overnight DND (e.g., 22:00 to 08:00)
        if ($start > $end) {
            return $now >= $start || $now <= $end;
        }

        return $now >= $start && $now <= $end;
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if user is blocked.
     */
    public function isUserBlocked(int $userId): bool
    {
        return in_array($userId, $this->blocked_users_ids ?? []);
    }

    /**
     * Block a user.
     */
    public function blockUser(int $userId): void
    {
        $blocked = $this->blocked_users_ids ?? [];
        
        if (!in_array($userId, $blocked)) {
            $blocked[] = $userId;
            $this->update(['blocked_users_ids' => $blocked]);
        }
    }

    /**
     * Unblock a user.
     */
    public function unblockUser(int $userId): void
    {
        $blocked = $this->blocked_users_ids ?? [];
        
        $blocked = array_values(array_filter($blocked, fn($id) => $id !== $userId));
        
        $this->update(['blocked_users_ids' => $blocked]);
    }

    /**
     * Upgrade to premium subscription.
     */
    public function upgradeToPremium(string $tier, Carbon $expiresAt): void
    {
        $this->update([
            'is_premium' => true,
            'subscription_tier' => $tier,
            'subscription_expires_at' => $expiresAt,
            'unlimited_likes' => true,
            'read_receipts_enabled' => true,
            'passport_enabled' => true,
            'super_likes_remaining' => 50,
            'rewinds_remaining' => 30,
        ]);
    }

    /**
     * Downgrade from premium subscription.
     */
    public function downgradeToPremium(): void
    {
        $this->update([
            'is_premium' => false,
            'subscription_tier' => 'free',
            'subscription_expires_at' => null,
            'unlimited_likes' => false,
            'read_receipts_enabled' => false,
            'passport_enabled' => false,
            'super_likes_remaining' => 5,
            'rewinds_remaining' => 3,
        ]);
    }

    /**
     * Use a super like.
     */
    public function useSuperLike(): bool
    {
        if ($this->super_likes_remaining > 0 || $this->unlimited_likes) {
            if (!$this->unlimited_likes) {
                $this->decrement('super_likes_remaining');
            }
            return true;
        }

        return false;
    }

    /**
     * Use a rewind.
     */
    public function useRewind(): bool
    {
        if ($this->rewinds_remaining > 0) {
            $this->decrement('rewinds_remaining');
            return true;
        }

        return false;
    }

    /**
     * Activate boost feature.
     */
    public function activateBoost(): void
    {
        $this->update([
            'boost_enabled' => true,
            'last_boost_at' => now(),
        ]);
    }

    /**
     * Deactivate boost feature.
     */
    public function deactivateBoost(): void
    {
        $this->update(['boost_enabled' => false]);
    }

    /**
     * Enable incognito mode.
     */
    public function enableIncognito(): void
    {
        $this->update(['incognito_mode' => true]);
    }

    /**
     * Disable incognito mode.
     */
    public function disableIncognito(): void
    {
        $this->update(['incognito_mode' => false]);
    }

    /**
     * Check if user matches discovery preferences.
     */
    public function matchesPreferences(Profile $profile): bool
    {
        // Check age
        if ($profile->age < $this->min_age_preference || $profile->age > $this->max_age_preference) {
            return false;
        }

        // Check gender
        if ($this->gender_preferences && !in_array($profile->gender, $this->gender_preferences)) {
            return false;
        }

        // Check verified only
        if ($this->show_verified_only && !$profile->is_verified) {
            return false;
        }

        // Check photos requirement
        if ($this->show_profiles_with_photos_only && $profile->user->images()->count() === 0) {
            return false;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get validation rules for settings update.
     */
    public static function validationRules(): array
    {
        return [
            'min_age_preference' => ['nullable', 'integer', 'between:18,99'],
            'max_age_preference' => ['nullable', 'integer', 'between:18,99', 'gte:min_age_preference'],
            'max_distance_km' => ['nullable', 'integer', 'between:1,500'],
            'discovery_mode' => ['nullable', 'in:everyone,nearby,selected'],
            'profile_visibility' => ['nullable', 'in:public,friends,private'],
            'distance_unit' => ['nullable', 'in:km,miles'],
            'language' => ['nullable', 'string', 'size:2'],
            'theme' => ['nullable', 'in:light,dark,auto'],
            'message_preview' => ['nullable', 'in:full,name_only,none'],
            'content_filter' => ['nullable', 'in:none,moderate,strict'],
        ];
    }
}
