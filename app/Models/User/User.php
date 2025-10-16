<?php

declare(strict_types=1);

namespace App\Models\User;

use App\Domain\Auth\Events\AccountDeleted;
use App\Domain\Auth\Events\UserLoggedIn;
use App\Domain\Auth\Events\UserRegistered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model - Modelo principal de usuario de ForeverUsInLove
 * 
 * Maneja la autenticación, verificación y gestión de usuarios de la aplicación.
 * Integrado con Clean Architecture y DDD patterns.
 * 
 * @property int $id
 * @property string $email
 * @property string $username
 * @property string|null $phone
 * @property string $password
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone_verified_at
 * @property string $status Valores: active, inactive, suspended, banned, pending_verification
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property int $login_attempts
 * @property Carbon|null $locked_until
 * @property string|null $face_id_data Datos biométricos encriptados para Face ID
 * @property bool $face_id_enabled
 * @property bool $two_factor_enabled
 * @property string|null $two_factor_secret
 * @property array|null $two_factor_recovery_codes
 * @property string|null $device_token Token para notificaciones push
 * @property string|null $remember_token
 * @property array|null $gdpr_consents Registro de consentimientos GDPR
 * @property Carbon|null $gdpr_consent_date
 * @property bool $marketing_emails_consent
 * @property Carbon|null $last_active_at
 * @property int $rate_limit_hits Contador para rate limiting
 * @property Carbon|null $rate_limit_reset_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * 
 * @property-read Profile|null $profile
 * @property-read \Illuminate\Database\Eloquent\Collection|UserImage[] $images
 * @property-read UserSetting|null $settings
 * @property-read \Illuminate\Database\Eloquent\Collection|PersonalityTest[] $personalityTests
 * 
 * @method static \Illuminate\Database\Eloquent\Builder active()
 * @method static \Illuminate\Database\Eloquent\Builder verified()
 * @method static \Illuminate\Database\Eloquent\Builder suspended()
 * @method static \Illuminate\Database\Eloquent\Builder banned()
 * @method static \Illuminate\Database\Eloquent\Builder withProfile()
 * @method static \Illuminate\Database\Eloquent\Builder recentlyActive()
 * @method static \Illuminate\Database\Eloquent\Builder hasCompletedProfile()
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'email',
        'username',
        'phone',
        'password',
        'status',
        'device_token',
        'marketing_emails_consent',
        'user_type',
        'email_verification_code',
        'email_verification_expires_at',
        'phone_verification_code',
        'phone_verification_expires_at',
        'password_reset_code',
        'password_reset_code_expires_at',
        'password_reset_sent_at',
        'password_reset_token',
        'password_reset_token_expires_at',
        'password_changed_at',
        'login_count',
        'last_logout_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'face_id_data',
        'email_verification_code',
        'phone_verification_code',
        'password_reset_code',
        'password_reset_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'face_id_enabled' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'two_factor_recovery_codes' => 'array',
        'gdpr_consents' => 'array',
        'gdpr_consent_date' => 'datetime',
        'marketing_emails_consent' => 'boolean',
        'last_active_at' => 'datetime',
        'login_attempts' => 'integer',
        'rate_limit_hits' => 'integer',
        'rate_limit_reset_at' => 'datetime',
        'password' => 'hashed',
        'email_verification_expires_at' => 'datetime',
        'phone_verification_expires_at' => 'datetime',
        'password_reset_code_expires_at' => 'datetime',
        'password_reset_token_expires_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'last_logout_at' => 'datetime',
        'login_count' => 'integer'
    ];

    /**
     * The attributes that should be encrypted.
     */
    protected $encryptable = [
        'face_id_data',
        'phone',
    ];

    /**
     * The event map for the model.
     */
    protected $dispatchesEvents = [
        'created' => UserRegistered::class,
        'deleted' => AccountDeleted::class,
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (User $user) {
            $user->status = $user->status ?? 'pending_verification';
            $user->login_attempts = 0;
            $user->rate_limit_hits = 0;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the profile associated with the user.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Get the user's images.
     */
    public function images(): HasMany
    {
        return $this->hasMany(UserImage::class)->orderBy('order');
    }

    /**
     * Get the user's primary image.
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(UserImage::class)->where('is_primary', true);
    }

    /**
     * Get the settings associated with the user.
     */
    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    /**
     * Get the personality tests taken by the user.
     */
    public function personalityTests(): HasMany
    {
        return $this->hasMany(PersonalityTest::class)->latest();
    }

    /**
     * Get the user's latest personality test.
     */
    public function latestPersonalityTest(): HasOne
    {
        return $this->hasOne(PersonalityTest::class)->latestOfMany();
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to filter active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter verified users.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope to filter suspended users.
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    /**
     * Scope to filter banned users.
     */
    public function scopeBanned($query)
    {
        return $query->where('status', 'banned');
    }

    /**
     * Scope to eager load profile relationship.
     */
    public function scopeWithProfile($query)
    {
        return $query->with('profile');
    }

    /**
     * Scope to filter recently active users (within last 30 days).
     */
    public function scopeRecentlyActive($query)
    {
        return $query->where('last_active_at', '>=', now()->subDays(30));
    }

    /**
     * Scope to filter users with completed profiles.
     */
    public function scopeHasCompletedProfile($query)
    {
        return $query->whereHas('profile', function ($q) {
            $q->where('completeness_percentage', '>=', 80);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user's full name from profile.
     */
    public function getFullNameAttribute(): ?string
    {
        return $this->profile?->full_name;
    }

    /**
     * Check if the user is active.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the user is verified.
     */
    public function getIsVerifiedAttribute(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Check if the user is phone verified.
     */
    public function getIsPhoneVerifiedAttribute(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /**
     * Check if the user account is locked.
     */
    public function getIsLockedAttribute(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Check if the user has completed their profile.
     */
    public function getHasCompletedProfileAttribute(): bool
    {
        return $this->profile && $this->profile->completeness_percentage >= 80;
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Mark the user as logged in and dispatch event.
     */
    public function markAsLoggedIn(string $ipAddress): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
            'login_attempts' => 0,
            'last_active_at' => now(),
        ]);

        // event(new UserLoggedIn($this->id, $ipAddress, now()));
    }

    /**
     * Increment login attempts for rate limiting.
     */
    public function incrementLoginAttempts(): void
    {
        $this->increment('login_attempts');

        if ($this->login_attempts >= 5) {
            $this->update([
                'locked_until' => now()->addMinutes(30),
            ]);
        }
    }

    /**
     * Reset login attempts.
     */
    public function resetLoginAttempts(): void
    {
        $this->update([
            'login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Update last active timestamp.
     */
    public function updateLastActive(): void
    {
        $this->update(['last_active_at' => now()]);
    }

    /**
     * Suspend the user account.
     */
    public function suspend(?string $reason = null): void
    {
        $this->update(['status' => 'suspended']);

        // TODO: Log suspension reason in moderation system
    }

    /**
     * Ban the user account.
     */
    public function ban(?string $reason = null): void
    {
        $this->update(['status' => 'banned']);

        // TODO: Log ban reason in moderation system
    }

    /**
     * Reactivate a suspended or banned account.
     */
    public function reactivate(): void
    {
        $this->update([
            'status' => 'active',
            'locked_until' => null,
            'login_attempts' => 0,
        ]);
    }

    /**
     * Enable Face ID authentication.
     */
    public function enableFaceId(string $biometricData): void
    {
        $this->update([
            'face_id_data' => encrypt($biometricData),
            'face_id_enabled' => true,
        ]);
    }

    /**
     * Disable Face ID authentication.
     */
    public function disableFaceId(): void
    {
        $this->update([
            'face_id_data' => null,
            'face_id_enabled' => false,
        ]);
    }

    /**
     * Check if rate limit is exceeded.
     */
    public function isRateLimited(): bool
    {
        if (!$this->rate_limit_reset_at || $this->rate_limit_reset_at->isPast()) {
            $this->resetRateLimit();
            return false;
        }

        return $this->rate_limit_hits >= 100; // 100 requests per hour
    }

    /**
     * Increment rate limit counter.
     */
    public function incrementRateLimit(): void
    {
        if (!$this->rate_limit_reset_at || $this->rate_limit_reset_at->isPast()) {
            $this->update([
                'rate_limit_hits' => 1,
                'rate_limit_reset_at' => now()->addHour(),
            ]);
        } else {
            $this->increment('rate_limit_hits');
        }
    }

    /**
     * Reset rate limit counter.
     */
    public function resetRateLimit(): void
    {
        $this->update([
            'rate_limit_hits' => 0,
            'rate_limit_reset_at' => now()->addHour(),
        ]);
    }

    /**
     * Record GDPR consent.
     */
    public function recordGdprConsent(array $consents): void
    {
        $this->update([
            'gdpr_consents' => array_merge($this->gdpr_consents ?? [], $consents),
            'gdpr_consent_date' => now(),
        ]);
    }

    /**
     * Check if user has given specific GDPR consent.
     */
    public function hasGdprConsent(string $consentType): bool
    {
        return isset($this->gdpr_consents[$consentType]) && $this->gdpr_consents[$consentType] === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get validation rules for user registration.
     */
    public static function registrationRules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username', 'alpha_dash'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
        ];
    }

    /**
     * Get validation rules for user update.
     */
    public static function updateRules(int $userId): array
    {
        return [
            'email' => ['sometimes', 'string', 'email', 'max:255', "unique:users,email,{$userId}"],
            'username' => ['sometimes', 'string', 'max:50', "unique:users,username,{$userId}", 'alpha_dash'],
            'phone' => ['nullable', 'string', 'max:20', "unique:users,phone,{$userId}"],
        ];
    }
}
