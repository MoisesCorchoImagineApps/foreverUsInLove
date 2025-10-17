<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * Class Admin
 *
 * Modelo de administradores del sistema ForeverUsInLove.
 * Gestiona cuentas administrativas, permisos, roles, auditoría de acciones
 * y control de acceso al panel administrativo.
 *
 * @package App\Models\System
 *
 * @property string $admin_id UUID primary key
 * @property string|null $user_id FK to users (si el admin también es usuario)
 * @property string $email Email único del administrador
 * @property string $password Password encriptado
 * @property string $name Nombre completo
 * @property string $role Rol del administrador (ROLES)
 * @property array|null $permissions Permisos específicos
 * @property string $status Estado del administrador (STATUSES)
 * @property string|null $department Departamento
 * @property string|null $position Posición/cargo
 * @property string|null $phone Teléfono de contacto
 * @property string|null $avatar_url URL del avatar
 * @property array|null $access_levels Niveles de acceso por módulo
 * @property array|null $ip_whitelist IPs permitidas para acceso
 * @property bool $two_factor_enabled 2FA habilitado
 * @property string|null $two_factor_secret Secreto 2FA
 * @property array|null $two_factor_recovery_codes Códigos de recuperación
 * @property Carbon|null $last_login_at Último login
 * @property string|null $last_login_ip IP del último login
 * @property string|null $last_login_user_agent User agent del último login
 * @property int $login_attempts Intentos de login fallidos
 * @property Carbon|null $locked_until Bloqueado hasta
 * @property Carbon|null $password_changed_at Última cambio de password
 * @property bool $must_change_password Requiere cambio de password
 * @property string|null $session_token Token de sesión actual
 * @property Carbon|null $session_expires_at Expiración de sesión
 * @property array|null $preferences Preferencias del admin
 * @property array|null $notifications_settings Configuración de notificaciones
 * @property array|null $dashboard_layout Layout personalizado del dashboard
 * @property array|null $metadata Metadata adicional
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read User|null $user
 * @property-read Collection|AdminAction[] $actions
 * @property-read Collection|AdminSession[] $sessions
 *
 * @method static \Illuminate\Database\Eloquent\Builder byRole(string $role)
 * @method static \Illuminate\Database\Eloquent\Builder byStatus(string $status)
 * @method static \Illuminate\Database\Eloquent\Builder byDepartment(string $department)
 * @method static \Illuminate\Database\Eloquent\Builder active()
 * @method static \Illuminate\Database\Eloquent\Builder suspended()
 * @method static \Illuminate\Database\Eloquent\Builder withPermission(string $permission)
 * @method static \Illuminate\Database\Eloquent\Builder onlineNow()
 * @method static \Illuminate\Database\Eloquent\Builder recentlyActive(int $minutes = 15)
 * @method static \Illuminate\Database\Eloquent\Builder twoFactorEnabled()
 * @method static \Illuminate\Database\Eloquent\Builder superAdmins()
 * @method static \Illuminate\Database\Eloquent\Builder moderators()
 * @method static \Illuminate\Database\Eloquent\Builder analysts()
 */
class Admin extends Model
{
    use HasFactory, SoftDeletes, Authorizable;

    /**
     * Primary key configuration
     */
    protected $primaryKey = 'admin_id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The table associated with the model.
     */
    protected $table = 'admins';

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'session_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'admin_id' => 'string',
        'user_id' => 'string',
        'permissions' => 'array',
        'access_levels' => 'array',
        'ip_whitelist' => 'array',
        'two_factor_enabled' => 'boolean',
        'two_factor_recovery_codes' => 'encrypted:array',
        'login_attempts' => 'integer',
        'must_change_password' => 'boolean',
        'preferences' => 'array',
        'notifications_settings' => 'array',
        'dashboard_layout' => 'array',
        'metadata' => 'encrypted:array',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'password_changed_at' => 'datetime',
        'session_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Admin Roles
     */
    public const ROLE_SUPER_ADMIN = 'super_admin'; // Acceso total al sistema
    public const ROLE_ADMIN = 'admin'; // Administrador general
    public const ROLE_MODERATOR = 'moderator'; // Moderador de contenido
    public const ROLE_ANALYST = 'analyst'; // Analista de datos
    public const ROLE_SUPPORT = 'support'; // Soporte al usuario
    public const ROLE_MARKETING = 'marketing'; // Marketing y campañas
    public const ROLE_FINANCE = 'finance'; // Finanzas y pagos
    public const ROLE_DEVELOPER = 'developer'; // Desarrollador/técnico
    public const ROLE_VIEWER = 'viewer'; // Solo lectura

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_MODERATOR,
        self::ROLE_ANALYST,
        self::ROLE_SUPPORT,
        self::ROLE_MARKETING,
        self::ROLE_FINANCE,
        self::ROLE_DEVELOPER,
        self::ROLE_VIEWER,
    ];

    /**
     * Admin Statuses
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_PENDING = 'pending';
    public const STATUS_LOCKED = 'locked';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_INACTIVE,
        self::STATUS_PENDING,
        self::STATUS_LOCKED,
    ];

    /**
     * Permissions Categories
     */
    public const PERMISSION_USER_MANAGEMENT = 'user_management';
    public const PERMISSION_CONTENT_MODERATION = 'content_moderation';
    public const PERMISSION_ANALYTICS = 'analytics';
    public const PERMISSION_SYSTEM_CONFIG = 'system_config';
    public const PERMISSION_FINANCIAL = 'financial';
    public const PERMISSION_MARKETING = 'marketing';
    public const PERMISSION_SUPPORT = 'support';
    public const PERMISSION_REPORTS = 'reports';
    public const PERMISSION_API_ACCESS = 'api_access';
    public const PERMISSION_AUDIT_LOG = 'audit_log';

    public const PERMISSIONS = [
        self::PERMISSION_USER_MANAGEMENT,
        self::PERMISSION_CONTENT_MODERATION,
        self::PERMISSION_ANALYTICS,
        self::PERMISSION_SYSTEM_CONFIG,
        self::PERMISSION_FINANCIAL,
        self::PERMISSION_MARKETING,
        self::PERMISSION_SUPPORT,
        self::PERMISSION_REPORTS,
        self::PERMISSION_API_ACCESS,
        self::PERMISSION_AUDIT_LOG,
    ];

    /**
     * Departments
     */
    public const DEPARTMENT_OPERATIONS = 'operations';
    public const DEPARTMENT_MODERATION = 'moderation';
    public const DEPARTMENT_ANALYTICS = 'analytics';
    public const DEPARTMENT_SUPPORT = 'support';
    public const DEPARTMENT_MARKETING = 'marketing';
    public const DEPARTMENT_FINANCE = 'finance';
    public const DEPARTMENT_ENGINEERING = 'engineering';
    public const DEPARTMENT_PRODUCT = 'product';

    public const DEPARTMENTS = [
        self::DEPARTMENT_OPERATIONS,
        self::DEPARTMENT_MODERATION,
        self::DEPARTMENT_ANALYTICS,
        self::DEPARTMENT_SUPPORT,
        self::DEPARTMENT_MARKETING,
        self::DEPARTMENT_FINANCE,
        self::DEPARTMENT_ENGINEERING,
        self::DEPARTMENT_PRODUCT,
    ];

    /**
     * Security Constants
     */
    public const MAX_LOGIN_ATTEMPTS = 5;
    public const LOCKOUT_DURATION_MINUTES = 30;
    public const SESSION_LIFETIME_HOURS = 8;
    public const PASSWORD_EXPIRY_DAYS = 90;
    public const TWO_FACTOR_CODE_LENGTH = 6;

    /**
     * Cache Configuration
     */
    public const CACHE_PREFIX = 'admin:';
    public const CACHE_PERMISSIONS_TTL = 3600; // 1 hour
    public const CACHE_SESSION_TTL = 28800; // 8 hours

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Get the associated user account (if admin is also a regular user)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get all actions performed by this admin
     */
    public function actions(): HasMany
    {
        return $this->hasMany(AdminAction::class, 'admin_id', 'admin_id');
    }

    /**
     * Get all sessions of this admin
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(AdminSession::class, 'admin_id', 'admin_id');
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Filter by role
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by department
     */
    public function scopeByDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope: Get active admins
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: Get suspended admins
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', self::STATUS_SUSPENDED);
    }

    /**
     * Scope: Filter by permission
     */
    public function scopeWithPermission($query, string $permission)
    {
        return $query->whereJsonContains('permissions', $permission);
    }

    /**
     * Scope: Get admins currently online
     */
    public function scopeOnlineNow($query)
    {
        return $query->where('session_expires_at', '>', now())
            ->whereNotNull('session_token');
    }

    /**
     * Scope: Get recently active admins
     */
    public function scopeRecentlyActive($query, int $minutes = 15)
    {
        return $query->where('last_login_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Scope: Get admins with 2FA enabled
     */
    public function scopeTwoFactorEnabled($query)
    {
        return $query->where('two_factor_enabled', true);
    }

    /**
     * Scope: Get super admins
     */
    public function scopeSuperAdmins($query)
    {
        return $query->where('role', self::ROLE_SUPER_ADMIN);
    }

    /**
     * Scope: Get moderators
     */
    public function scopeModerators($query)
    {
        return $query->where('role', self::ROLE_MODERATOR);
    }

    /**
     * Scope: Get analysts
     */
    public function scopeAnalysts($query)
    {
        return $query->where('role', self::ROLE_ANALYST);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Check if admin is active
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if admin is locked
     */
    public function getIsLockedAttribute(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Check if admin is online
     */
    public function getIsOnlineAttribute(): bool
    {
        return !is_null($this->session_expires_at) && $this->session_expires_at->isFuture();
    }

    /**
     * Check if password has expired
     */
    public function getPasswordExpiredAttribute(): bool
    {
        if (!$this->password_changed_at) {
            return true;
        }

        return $this->password_changed_at->addDays(self::PASSWORD_EXPIRY_DAYS)->isPast();
    }

    /**
     * Check if admin has 2FA enabled
     */
    public function getHasTwoFactorAttribute(): bool
    {
        return $this->two_factor_enabled && !is_null($this->two_factor_secret);
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Get role display name
     */
    public function getRoleNameAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'Super Administrator',
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_MODERATOR => 'Moderator',
            self::ROLE_ANALYST => 'Analyst',
            self::ROLE_SUPPORT => 'Support',
            self::ROLE_MARKETING => 'Marketing',
            self::ROLE_FINANCE => 'Finance',
            self::ROLE_DEVELOPER => 'Developer',
            self::ROLE_VIEWER => 'Viewer',
            default => 'Unknown',
        };
    }

    /**
     * Get time since last login
     */
    public function getLastSeenAttribute(): ?string
    {
        if (!$this->last_login_at) {
            return null;
        }

        return $this->last_login_at->diffForHumans();
    }

    /**
     * Hash password before saving
     */
    public function setPasswordAttribute($value): void
    {
        if (!empty($value)) {
            $this->attributes['password'] = Hash::make($value);
            $this->attributes['password_changed_at'] = now();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PERMISSION & AUTHORIZATION METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Check if admin has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        // Super admin has all permissions
        if ($this->role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        // Check explicit permissions
        if (is_array($this->permissions) && in_array($permission, $this->permissions)) {
            return true;
        }

        // Check role-based permissions
        return $this->hasRolePermission($permission);
    }

    /**
     * Check if admin has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if admin has all of the given permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check role-based permissions
     */
    protected function hasRolePermission(string $permission): bool
    {
        $rolePermissions = $this->getRolePermissions($this->role);
        return in_array($permission, $rolePermissions);
    }

    /**
     * Get permissions for a role
     */
    protected function getRolePermissions(string $role): array
    {
        $cacheKey = self::CACHE_PREFIX . "role_permissions:{$role}";

        return Cache::remember($cacheKey, self::CACHE_PERMISSIONS_TTL, function () use ($role) {
            return match ($role) {
                self::ROLE_SUPER_ADMIN => self::PERMISSIONS,
                self::ROLE_ADMIN => [
                    self::PERMISSION_USER_MANAGEMENT,
                    self::PERMISSION_CONTENT_MODERATION,
                    self::PERMISSION_ANALYTICS,
                    self::PERMISSION_SUPPORT,
                    self::PERMISSION_REPORTS,
                ],
                self::ROLE_MODERATOR => [
                    self::PERMISSION_CONTENT_MODERATION,
                    self::PERMISSION_SUPPORT,
                ],
                self::ROLE_ANALYST => [
                    self::PERMISSION_ANALYTICS,
                    self::PERMISSION_REPORTS,
                ],
                self::ROLE_SUPPORT => [
                    self::PERMISSION_SUPPORT,
                    self::PERMISSION_USER_MANAGEMENT,
                ],
                self::ROLE_MARKETING => [
                    self::PERMISSION_MARKETING,
                    self::PERMISSION_ANALYTICS,
                ],
                self::ROLE_FINANCE => [
                    self::PERMISSION_FINANCIAL,
                    self::PERMISSION_REPORTS,
                ],
                default => [],
            };
        });
    }

    /**
     * Check if admin has access to specific module
     */
    public function canAccessModule(string $module): bool
    {
        // Super admin has access to all modules
        if ($this->role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        // Check access levels
        if (is_array($this->access_levels) && isset($this->access_levels[$module])) {
            return $this->access_levels[$module] === true;
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATION & SESSION METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Attempt login with credentials
     */
    public function attemptLogin(string $password, string $ipAddress, string $userAgent): bool
    {
        // Check if account is locked
        if ($this->is_locked) {
            return false;
        }

        // Check if account is active
        if (!$this->is_active) {
            return false;
        }

        // Verify password
        if (!Hash::check($password, $this->password)) {
            $this->incrementLoginAttempts();
            return false;
        }

        // Reset login attempts
        $this->resetLoginAttempts();

        // Update last login info
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
            'last_login_user_agent' => $userAgent,
        ]);

        // Create session
        $this->createSession();

        return true;
    }

    /**
     * Create new session
     */
    public function createSession(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->update([
            'session_token' => Hash::make($token),
            'session_expires_at' => now()->addHours(self::SESSION_LIFETIME_HOURS),
        ]);

        // Store session in database
        DB::table('admin_sessions')->insert([
            'session_id' => (string) \Illuminate\Support\Str::uuid(),
            'admin_id' => $this->admin_id,
            'token' => Hash::make($token),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => now()->addHours(self::SESSION_LIFETIME_HOURS),
            'created_at' => now(),
        ]);

        return $token;
    }

    /**
     * Verify session token
     */
    public function verifySession(string $token): bool
    {
        if (!$this->session_token || !$this->session_expires_at) {
            return false;
        }

        if ($this->session_expires_at->isPast()) {
            return false;
        }

        return Hash::check($token, $this->session_token);
    }

    /**
     * Logout and destroy session
     */
    public function logout(): bool
    {
        $this->update([
            'session_token' => null,
            'session_expires_at' => null,
        ]);

        // Delete active sessions
        DB::table('admin_sessions')
            ->where('admin_id', $this->admin_id)
            ->where('expires_at', '>', now())
            ->delete();

        return true;
    }

    /**
     * Increment login attempts
     */
    protected function incrementLoginAttempts(): void
    {
        $this->increment('login_attempts');

        if ($this->login_attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $this->lockAccount();
        }
    }

    /**
     * Reset login attempts
     */
    protected function resetLoginAttempts(): void
    {
        $this->update([
            'login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Lock account temporarily
     */
    public function lockAccount(): void
    {
        $this->update([
            'status' => self::STATUS_LOCKED,
            'locked_until' => now()->addMinutes(self::LOCKOUT_DURATION_MINUTES),
        ]);
    }

    /**
     * Unlock account
     */
    public function unlockAccount(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'locked_until' => null,
            'login_attempts' => 0,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TWO-FACTOR AUTHENTICATION
    |--------------------------------------------------------------------------
    */

    /**
     * Enable two-factor authentication
     */
    public function enableTwoFactor(string $secret): bool
    {
        $recoveryCodes = $this->generateRecoveryCodes();

        return $this->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable two-factor authentication
     */
    public function disableTwoFactor(): bool
    {
        return $this->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);
    }

    /**
     * Generate recovery codes
     */
    protected function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(md5(random_bytes(16)), 0, 10));
        }
        return $codes;
    }

    /**
     * Verify two-factor code
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (!$this->two_factor_enabled || !$this->two_factor_secret) {
            return false;
        }

        // Verify TOTP code (would use actual TOTP library in production)
        // For now, return placeholder logic
        return strlen($code) === self::TWO_FACTOR_CODE_LENGTH;
    }

    /**
     * Use recovery code
     */
    public function useRecoveryCode(string $code): bool
    {
        $codes = $this->two_factor_recovery_codes ?? [];

        if (!in_array($code, $codes)) {
            return false;
        }

        // Remove used code
        $codes = array_diff($codes, [$code]);

        $this->update(['two_factor_recovery_codes' => $codes]);

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIT & LOGGING
    |--------------------------------------------------------------------------
    */

    /**
     * Log admin action
     */
    public function logAction(string $action, array $data = []): void
    {
        DB::table('admin_actions')->insert([
            'action_id' => (string) \Illuminate\Support\Str::uuid(),
            'admin_id' => $this->admin_id,
            'action' => $action,
            'data' => json_encode($data),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Get recent actions
     */
    public function getRecentActions(int $limit = 50): Collection
    {
        return $this->actions()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get action statistics
     */
    public function getActionStatistics(int $days = 30): array
    {
        $actions = $this->actions()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        return [
            'total_actions' => $actions->count(),
            'actions_per_day' => $actions->count() / $days,
            'actions_by_type' => $actions->groupBy('action')->map->count(),
            'most_active_day' => $actions->groupBy(fn($a) => $a->created_at->format('Y-m-d'))
                ->sortByDesc->count()
                ->keys()
                ->first(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | UTILITY METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Check IP whitelist
     */
    public function isIPAllowed(string $ipAddress): bool
    {
        // Super admin can access from any IP
        if ($this->role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        // If no whitelist, allow all
        if (empty($this->ip_whitelist)) {
            return true;
        }

        return in_array($ipAddress, $this->ip_whitelist);
    }

    /**
     * Update preferences
     */
    public function updatePreferences(array $preferences): bool
    {
        $current = $this->preferences ?? [];
        $updated = array_merge($current, $preferences);

        return $this->update(['preferences' => $updated]);
    }

    /**
     * Update dashboard layout
     */
    public function updateDashboardLayout(array $layout): bool
    {
        return $this->update(['dashboard_layout' => $layout]);
    }

    /**
     * Clear cache related to admin
     */
    public function clearCache(): void
    {
        $cacheKeys = [
            self::CACHE_PREFIX . "permissions:{$this->admin_id}",
            self::CACHE_PREFIX . "role_permissions:{$this->role}",
            self::CACHE_PREFIX . "session:{$this->admin_id}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Get admin display information
     */
    public function toDisplayArray(): array
    {
        return [
            'admin_id' => $this->admin_id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_name' => $this->role_name,
            'department' => $this->department,
            'position' => $this->position,
            'status' => $this->status,
            'is_online' => $this->is_online,
            'last_seen' => $this->last_seen,
            'avatar_url' => $this->avatar_url,
            'two_factor_enabled' => $this->two_factor_enabled,
        ];
    }

    /**
     * Create a new collection instance
     */
    public function newCollection(array $models = []): AdminCollection
    {
        return new AdminCollection($models);
    }
}

/**
 * Custom Collection for Admin Model
 */
class AdminCollection extends Collection
{
    /**
     * Get only active admins
     */
    public function active(): self
    {
        return $this->filter(fn($admin) => $admin->is_active);
    }

    /**
     * Get admins by role
     */
    public function byRole(string $role): self
    {
        return $this->filter(fn($admin) => $admin->role === $role);
    }

    /**
     * Get online admins
     */
    public function online(): self
    {
        return $this->filter(fn($admin) => $admin->is_online);
    }

    /**
     * Get admins with specific permission
     */
    public function withPermission(string $permission): self
    {
        return $this->filter(fn($admin) => $admin->hasPermission($permission));
    }

    /**
     * Get admins with 2FA enabled
     */
    public function withTwoFactor(): self
    {
        return $this->filter(fn($admin) => $admin->has_two_factor);
    }
}

/**
 * Admin Action Model (simple model for audit trail)
 */
class AdminAction extends Model
{
    protected $primaryKey = 'action_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'admin_actions';
    public $timestamps = false;

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];
}

/**
 * Admin Session Model
 */
class AdminSession extends Model
{
    protected $primaryKey = 'session_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'admin_sessions';
    public $timestamps = false;

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}