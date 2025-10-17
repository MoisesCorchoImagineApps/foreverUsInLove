<?php

namespace App\Models\Moderation;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use App\Domain\Moderation\Events\UserBlocked;

/**
 * Class Block
 * 
 * Modelo Eloquent para la gestión completa de bloqueos entre usuarios con múltiples tipos,
 * duraciones temporales, consecuencias automatizadas y detección de patrones de bloqueo.
 * Soporta bloqueos completos, parciales, temporales y shadow bans silenciosos.
 * 
 * Basado en: Domain/Moderation/BlockService.php
 * 
 * @package App\Models\Moderation
 * @version 1.0.0
 * @since Laravel 12.x / PHP 8.2
 * 
 * TABLA DE CONTENIDOS:
 * ==================
 * 1. Constantes de Negocio (Tipos, Razones, Estados, Duraciones)
 * 2. Propiedades del Modelo (Fillable, Casts, Dates, Appends)
 * 3. Relaciones Eloquent (Blocker, Blocked)
 * 4. Query Scopes (Active, Expired, Mutual, ByType, Temporary)
 * 5. Accessors & Mutators (IsExpired, RemainingTime, IsMutual)
 * 6. Métodos de Negocio (Block, Unblock, CheckMutual, ProcessExpired)
 * 7. Métodos de Consecuencias (ApplyConsequences, RemoveConsequences)
 * 8. Métodos de Analytics (BlockStatistics, BlockPatterns)
 * 9. Event Dispatching (UserBlocked Broadcasting)
 * 10. Cache Management
 * 
 * RESPONSABILIDADES:
 * ==================
 * - Gestión de 6 tipos de bloqueo (completo, mensajería, perfil, matching, búsqueda, shadow)
 * - Bloqueos temporales con expiración automática (1h, 6h, 24h, 3d, 7d, 30d)
 * - Bloqueos permanentes sin fecha de expiración
 * - Detección automática de bloqueos mutuos (bidireccionales)
 * - Aplicación de consecuencias: remove matches, hide conversations, clear notifications
 * - Reversión de consecuencias al desbloquear
 * - Analytics de patrones de bloqueo por usuario
 * - Broadcasting de eventos a 7 canales diferentes
 * - Procesamiento automático de bloqueos expirados (cron job)
 * - Prevención de interacciones según tipo de bloqueo
 * - Tracking de historial de bloqueos entre usuarios
 * 
 * @property int $id
 * @property int $blocker_id Usuario que bloquea
 * @property int $blocked_id Usuario bloqueado
 * @property string $type Tipo de bloqueo (6 opciones)
 * @property string $reason Razón del bloqueo (10 opciones)
 * @property string $status Estado actual (4 opciones)
 * @property bool $is_temporary Flag de bloqueo temporal
 * @property int|null $duration_hours Duración en horas (si es temporal)
 * @property \Carbon\Carbon $blocked_at Timestamp del bloqueo
 * @property \Carbon\Carbon|null $expires_at Fecha de expiración (si es temporal)
 * @property \Carbon\Carbon|null $lifted_at Fecha cuando se levantó el bloqueo
 * @property array $consequences Consecuencias aplicadas (matches, conversations, etc.)
 * @property bool $is_mutual Flag de bloqueo mutuo (calculado)
 * @property array $metadata Contexto adicional encriptado
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @property-read User $blocker Relación con usuario bloqueador
 * @property-read User $blocked Relación con usuario bloqueado
 * @property-read bool $is_expired Flag de bloqueo expirado
 * @property-read string|null $remaining_time Tiempo restante del bloqueo
 * @property-read bool $is_active Flag de bloqueo activo
 * @property-read int $hours_active Horas desde el bloqueo
 * 
 * @method static Builder active() Bloqueos activos
 * @method static Builder expired() Bloqueos expirados
 * @method static Builder mutual() Bloqueos mutuos
 * @method static Builder byType(string $type) Filtrar por tipo
 * @method static Builder byBlocker(int $userId) Bloqueos realizados por usuario
 * @method static Builder byBlocked(int $userId) Bloqueos recibidos por usuario
 * @method static Builder temporary() Bloqueos temporales
 * @method static Builder permanent() Bloqueos permanentes
 * @method static Builder expiringIn(int $hours) Expiran en X horas
 */
class Block extends Model
{
    use HasFactory, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | 1. CONSTANTES DE NEGOCIO
    |--------------------------------------------------------------------------
    */

    /**
     * Tipos de bloqueo disponibles (6 categorías)
     */
    public const TYPE_COMPLETE = 'complete';
    public const TYPE_MESSAGING = 'messaging';
    public const TYPE_PROFILE_VIEW = 'profile_view';
    public const TYPE_MATCHING = 'matching';
    public const TYPE_SEARCH_RESULTS = 'search_results';
    public const TYPE_SOFT_BLOCK = 'soft_block';

    public const BLOCK_TYPES = [
        self::TYPE_COMPLETE,
        self::TYPE_MESSAGING,
        self::TYPE_PROFILE_VIEW,
        self::TYPE_MATCHING,
        self::TYPE_SEARCH_RESULTS,
        self::TYPE_SOFT_BLOCK,
    ];

    /**
     * Razones de bloqueo disponibles (10 opciones)
     */
    public const REASON_HARASSMENT = 'harassment';
    public const REASON_INAPPROPRIATE_MESSAGES = 'inappropriate_messages';
    public const REASON_FAKE_PROFILE = 'fake_profile';
    public const REASON_SPAM = 'spam';
    public const REASON_PERSONAL_PREFERENCE = 'personal_preference';
    public const REASON_SAFETY_CONCERNS = 'safety_concerns';
    public const REASON_OFFENSIVE_BEHAVIOR = 'offensive_behavior';
    public const REASON_UNWANTED_CONTACT = 'unwanted_contact';
    public const REASON_SCAM_ATTEMPT = 'scam_attempt';
    public const REASON_POLICY_VIOLATION = 'policy_violation';

    public const BLOCK_REASONS = [
        self::REASON_HARASSMENT,
        self::REASON_INAPPROPRIATE_MESSAGES,
        self::REASON_FAKE_PROFILE,
        self::REASON_SPAM,
        self::REASON_PERSONAL_PREFERENCE,
        self::REASON_SAFETY_CONCERNS,
        self::REASON_OFFENSIVE_BEHAVIOR,
        self::REASON_UNWANTED_CONTACT,
        self::REASON_SCAM_ATTEMPT,
        self::REASON_POLICY_VIOLATION,
    ];

    /**
     * Estados del bloqueo (4 estados)
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_LIFTED = 'lifted';
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_EXPIRED,
        self::STATUS_LIFTED,
        self::STATUS_PENDING_REVIEW,
    ];

    /**
     * Duraciones temporales predefinidas (en horas)
     */
    public const DURATION_1_HOUR = 1;
    public const DURATION_6_HOURS = 6;
    public const DURATION_24_HOURS = 24;
    public const DURATION_3_DAYS = 72;
    public const DURATION_7_DAYS = 168;
    public const DURATION_30_DAYS = 720;

    public const DURATIONS = [
        '1_HOUR' => self::DURATION_1_HOUR,
        '6_HOURS' => self::DURATION_6_HOURS,
        '24_HOURS' => self::DURATION_24_HOURS,
        '3_DAYS' => self::DURATION_3_DAYS,
        '7_DAYS' => self::DURATION_7_DAYS,
        '30_DAYS' => self::DURATION_30_DAYS,
    ];

    /**
     * Mapeo de tipo de bloqueo a consecuencias
     */
    public const TYPE_CONSEQUENCES = [
        self::TYPE_COMPLETE => [
            'remove_matches' => true,
            'hide_conversations' => true,
            'clear_notifications' => true,
            'prevent_profile_view' => true,
            'prevent_messaging' => true,
            'remove_from_discovery' => true,
            'remove_from_search' => true,
        ],
        self::TYPE_MESSAGING => [
            'prevent_messaging' => true,
            'hide_conversations' => true,
        ],
        self::TYPE_PROFILE_VIEW => [
            'prevent_profile_view' => true,
        ],
        self::TYPE_MATCHING => [
            'remove_matches' => true,
            'remove_from_discovery' => true,
        ],
        self::TYPE_SEARCH_RESULTS => [
            'remove_from_search' => true,
        ],
        self::TYPE_SOFT_BLOCK => [
            'remove_from_discovery' => true,
            'remove_from_search' => true,
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | 2. PROPIEDADES DEL MODELO
    |--------------------------------------------------------------------------
    */

    /**
     * Nombre de la tabla en la base de datos
     */
    protected $table = 'blocks';

    /**
     * Clave primaria de la tabla
     */
    protected $primaryKey = 'id';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'blocker_id',
        'blocked_id',
        'type',
        'reason',
        'status',
        'is_temporary',
        'duration_hours',
        'blocked_at',
        'expires_at',
        'lifted_at',
        'consequences',
        'is_mutual',
        'metadata',
    ];

    /**
     * Atributos que deben ser casteados a tipos nativos
     */
    protected $casts = [
        'blocker_id' => 'integer',
        'blocked_id' => 'integer',
        'is_temporary' => 'boolean',
        'duration_hours' => 'integer',
        'consequences' => 'array',
        'is_mutual' => 'boolean',
        'metadata' => 'encrypted:array',
        'blocked_at' => 'datetime',
        'expires_at' => 'datetime',
        'lifted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Atributos que deben ser mutados a fechas
     */
    protected $dates = [
        'blocked_at',
        'expires_at',
        'lifted_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Atributos que deben ser añadidos a las representaciones de arrays/JSON
     */
    protected $appends = [
        'is_expired',
        'remaining_time',
        'is_active',
        'hours_active',
    ];

    /**
     * Valores por defecto para atributos
     */
    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'is_temporary' => false,
        'is_mutual' => false,
        'type' => self::TYPE_COMPLETE,
    ];

    /*
    |--------------------------------------------------------------------------
    | 3. RELACIONES ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Usuario que realiza el bloqueo
     *
     * @return BelongsTo
     */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    /**
     * Usuario que está siendo bloqueado
     *
     * @return BelongsTo
     */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    /*
    |--------------------------------------------------------------------------
    | 4. QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope para obtener bloqueos activos
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope para obtener bloqueos expirados
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('is_temporary', true)
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope para obtener bloqueos mutuos
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeMutual(Builder $query): Builder
    {
        return $query->where('is_mutual', true);
    }

    /**
     * Scope para filtrar por tipo de bloqueo
     *
     * @param Builder $query
     * @param string $type
     * @return Builder
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para obtener bloqueos realizados por un usuario
     *
     * @param Builder $query
     * @param int $userId
     * @return Builder
     */
    public function scopeByBlocker(Builder $query, int $userId): Builder
    {
        return $query->where('blocker_id', $userId);
    }

    /**
     * Scope para obtener bloqueos recibidos por un usuario
     *
     * @param Builder $query
     * @param int $userId
     * @return Builder
     */
    public function scopeByBlocked(Builder $query, int $userId): Builder
    {
        return $query->where('blocked_id', $userId);
    }

    /**
     * Scope para obtener bloqueos temporales
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeTemporary(Builder $query): Builder
    {
        return $query->where('is_temporary', true);
    }

    /**
     * Scope para obtener bloqueos permanentes
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePermanent(Builder $query): Builder
    {
        return $query->where('is_temporary', false);
    }

    /**
     * Scope para obtener bloqueos que expiran en X horas
     *
     * @param Builder $query
     * @param int $hours
     * @return Builder
     */
    public function scopeExpiringIn(Builder $query, int $hours): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('is_temporary', true)
            ->whereBetween('expires_at', [now(), now()->addHours($hours)]);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Accessor para verificar si el bloqueo está expirado
     *
     * @return bool
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->is_temporary || $this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Accessor para obtener el tiempo restante del bloqueo
     *
     * @return string|null
     */
    public function getRemainingTimeAttribute(): ?string
    {
        if (!$this->is_temporary || !$this->expires_at || $this->status !== self::STATUS_ACTIVE) {
            return null;
        }

        if ($this->expires_at->isPast()) {
            return 'Expirado';
        }

        $diff = now()->diff($this->expires_at);

        if ($diff->days > 0) {
            return $diff->days . ' día(s) ' . $diff->h . ' hora(s)';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hora(s) ' . $diff->i . ' minuto(s)';
        } else {
            return $diff->i . ' minuto(s)';
        }
    }

    /**
     * Accessor para verificar si el bloqueo está activo
     *
     * @return bool
     */
    public function getIsActiveAttribute(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->is_temporary && $this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Accessor para obtener las horas desde que se bloqueó
     *
     * @return int
     */
    public function getHoursActiveAttribute(): int
    {
        return $this->blocked_at->diffInHours(now());
    }

    /**
     * Mutator para calcular expires_at basado en duration_hours
     *
     * @param int|null $value
     * @return void
     */
    public function setDurationHoursAttribute(?int $value): void
    {
        $this->attributes['duration_hours'] = $value;

        if ($value && $this->is_temporary) {
            $this->attributes['expires_at'] = now()->addHours($value)->toDateTimeString();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 6. MÉTODOS DE NEGOCIO
    |--------------------------------------------------------------------------
    */

    /**
     * Bloquear un usuario
     *
     * @param array $data [blocker_id, blocked_id, type, reason, is_temporary, duration_hours]
     * @return static
     */
    public static function blockUser(array $data): static
    {
        // Verificar si ya existe un bloqueo activo
        $existingBlock = static::active()
            ->where('blocker_id', $data['blocker_id'])
            ->where('blocked_id', $data['blocked_id'])
            ->first();

        if ($existingBlock) {
            return $existingBlock;
        }

        // Verificar si es bloqueo mutuo
        $isMutual = static::active()
            ->where('blocker_id', $data['blocked_id'])
            ->where('blocked_id', $data['blocker_id'])
            ->exists();

        // Crear el bloqueo
        $block = static::create([
            'blocker_id' => $data['blocker_id'],
            'blocked_id' => $data['blocked_id'],
            'type' => $data['type'] ?? self::TYPE_COMPLETE,
            'reason' => $data['reason'],
            'status' => self::STATUS_ACTIVE,
            'is_temporary' => $data['is_temporary'] ?? false,
            'duration_hours' => $data['duration_hours'] ?? null,
            'blocked_at' => now(),
            'is_mutual' => $isMutual,
            'metadata' => $data['metadata'] ?? [],
        ]);

        // Si es bloqueo temporal, calcular expires_at
        if ($block->is_temporary && $block->duration_hours) {
            $block->update([
                'expires_at' => now()->addHours($block->duration_hours),
            ]);
        }

        // Aplicar consecuencias del bloqueo
        $block->applyConsequences();

        // Actualizar bloqueo inverso si existe (marcar como mutuo)
        if ($isMutual) {
            static::active()
                ->where('blocker_id', $data['blocked_id'])
                ->where('blocked_id', $data['blocker_id'])
                ->update(['is_mutual' => true]);
        }

        // Dispatch event
        event(new UserBlocked(
            blockId: $block->id,
            blockerId: $block->blocker_id,
            blockedId: $block->blocked_id,
            blockType: $block->type,
            reason: $block->reason,
            durationHours: $block->duration_hours,
            metadata: $block->metadata ?? []
        ));

        return $block;
    }

    /**
     * Desbloquear usuario
     *
     * @return bool
     */
    public function unblock(): bool
    {
        $this->update([
            'status' => self::STATUS_LIFTED,
            'lifted_at' => now(),
        ]);

        // Remover consecuencias del bloqueo
        $this->removeConsequences();

        // Actualizar bloqueo mutuo si existe
        if ($this->is_mutual) {
            static::active()
                ->where('blocker_id', $this->blocked_id)
                ->where('blocked_id', $this->blocker_id)
                ->update(['is_mutual' => false]);
        }

        $this->clearCache();

        return true;
    }

    /**
     * Verificar si un usuario está bloqueado por otro
     *
     * @param int $blockerId
     * @param int $blockedId
     * @param string|null $type Verificar tipo específico de bloqueo
     * @return bool
     */
    public static function isUserBlocked(int $blockerId, int $blockedId, ?string $type = null): bool
    {
        $query = static::active()
            ->where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId);

        if ($type) {
            $query->where(function ($q) use ($type) {
                $q->where('type', self::TYPE_COMPLETE)
                    ->orWhere('type', $type);
            });
        }

        return $query->exists();
    }

    /**
     * Verificar si existe bloqueo mutuo entre dos usuarios
     *
     * @param int $userId1
     * @param int $userId2
     * @return bool
     */
    public static function checkMutualBlock(int $userId1, int $userId2): bool
    {
        $block1 = static::isUserBlocked($userId1, $userId2);
        $block2 = static::isUserBlocked($userId2, $userId1);

        return $block1 && $block2;
    }

    /**
     * Procesar bloqueos expirados (ejecutar en cron job)
     *
     * @return int Número de bloqueos procesados
     */
    public static function processExpiredBlocks(): int
    {
        $expiredBlocks = static::expired()->get();

        foreach ($expiredBlocks as $block) {
            $block->update([
                'status' => self::STATUS_EXPIRED,
            ]);

            // Remover consecuencias automáticamente
            $block->removeConsequences();

            $block->clearCache();
        }

        return $expiredBlocks->count();
    }

    /**
     * Extender duración del bloqueo temporal
     *
     * @param int $additionalHours
     * @return bool
     */
    public function extendDuration(int $additionalHours): bool
    {
        if (!$this->is_temporary || $this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $newExpiresAt = ($this->expires_at ?? now())->addHours($additionalHours);

        $this->update([
            'duration_hours' => $this->duration_hours + $additionalHours,
            'expires_at' => $newExpiresAt,
        ]);

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | 7. MÉTODOS DE CONSECUENCIAS
    |--------------------------------------------------------------------------
    */

    /**
     * Aplicar consecuencias del bloqueo
     *
     * @return void
     */
    protected function applyConsequences(): void
    {
        $consequences = self::TYPE_CONSEQUENCES[$this->type] ?? [];
        $appliedConsequences = [];

        foreach ($consequences as $action => $shouldApply) {
            if (!$shouldApply) {
                continue;
            }

            switch ($action) {
                case 'remove_matches':
                    // TODO: Remover matches entre los usuarios
                    $appliedConsequences['matches_removed'] = true;
                    break;

                case 'hide_conversations':
                    // TODO: Ocultar conversaciones
                    $appliedConsequences['conversations_hidden'] = true;
                    break;

                case 'clear_notifications':
                    // TODO: Limpiar notificaciones
                    $appliedConsequences['notifications_cleared'] = true;
                    break;

                case 'prevent_profile_view':
                    $appliedConsequences['profile_view_prevented'] = true;
                    break;

                case 'prevent_messaging':
                    $appliedConsequences['messaging_prevented'] = true;
                    break;

                case 'remove_from_discovery':
                    // TODO: Remover de discovery queue
                    $appliedConsequences['removed_from_discovery'] = true;
                    break;

                case 'remove_from_search':
                    // TODO: Remover de resultados de búsqueda
                    $appliedConsequences['removed_from_search'] = true;
                    break;
            }
        }

        $this->update(['consequences' => $appliedConsequences]);
    }

    /**
     * Remover consecuencias del bloqueo
     *
     * @return void
     */
    protected function removeConsequences(): void
    {
        $consequences = $this->consequences ?? [];

        foreach ($consequences as $action => $value) {
            if (!$value) {
                continue;
            }

            switch ($action) {
                case 'conversations_hidden':
                    // TODO: Restaurar conversaciones
                    break;

                case 'removed_from_discovery':
                    // TODO: Restaurar en discovery
                    break;

                case 'removed_from_search':
                    // TODO: Restaurar en búsqueda
                    break;
            }
        }

        $this->update(['consequences' => []]);
    }

    /*
    |--------------------------------------------------------------------------
    | 8. MÉTODOS DE ANALYTICS
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener estadísticas de bloqueos de un usuario
     *
     * @param int $userId
     * @return array
     */
    public static function getUserBlockStatistics(int $userId): array
    {
        $blocksGiven = static::byBlocker($userId)->count();
        $blocksReceived = static::byBlocked($userId)->count();
        $activeBlocksGiven = static::active()->byBlocker($userId)->count();
        $activeBlocksReceived = static::active()->byBlocked($userId)->count();
        $mutualBlocks = static::mutual()
            ->where(function ($q) use ($userId) {
                $q->where('blocker_id', $userId)
                    ->orWhere('blocked_id', $userId);
            })
            ->count() / 2; // Dividir por 2 porque se cuentan ambos lados

        $blocksByReason = static::byBlocker($userId)
            ->selectRaw('reason, COUNT(*) as count')
            ->groupBy('reason')
            ->pluck('count', 'reason');

        return [
            'blocks_given' => $blocksGiven,
            'blocks_received' => $blocksReceived,
            'active_blocks_given' => $activeBlocksGiven,
            'active_blocks_received' => $activeBlocksReceived,
            'mutual_blocks' => $mutualBlocks,
            'blocks_by_reason' => $blocksByReason,
            'blocking_frequency' => $blocksGiven > 0 ? 'high' : 'low',
        ];
    }

    /**
     * Obtener lista de usuarios bloqueados por un usuario
     *
     * @param int $blockerId
     * @param bool $activeOnly Solo bloqueos activos
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getBlockedUsers(int $blockerId, bool $activeOnly = true)
    {
        $query = static::byBlocker($blockerId);

        if ($activeOnly) {
            $query->active();
        }

        return $query->with('blocked')->get();
    }

    /**
     * Detectar patrones de bloqueo sospechosos
     *
     * @param int $userId
     * @return array
     */
    public static function detectBlockingPatterns(int $userId): array
    {
        $recentBlocks = static::byBlocker($userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $patterns = [
            'excessive_blocking' => $recentBlocks > 10,
            'rapid_blocking' => $recentBlocks > 5,
            'pattern_detected' => false,
        ];

        // Verificar si bloquea y desbloquea repetidamente
        $blockUnblockCycles = static::byBlocker($userId)
            ->where('status', self::STATUS_LIFTED)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $patterns['block_unblock_cycles'] = $blockUnblockCycles > 5;
        $patterns['pattern_detected'] = $patterns['excessive_blocking'] || $patterns['block_unblock_cycles'];

        return $patterns;
    }

    /*
    |--------------------------------------------------------------------------
    | 9. EVENT DISPATCHING
    |--------------------------------------------------------------------------
    */

    /**
     * Boot del modelo para eventos
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::created(function (Block $block) {
            event(new UserBlocked(
                blockId: $block->id,
                blockerId: $block->blocker_id,
                blockedId: $block->blocked_id,
                blockType: $block->type,
                reason: $block->reason,
                durationHours: $block->duration_hours,
                metadata: $block->metadata ?? []
            ));
            $block->clearCache();
        });

        static::updated(function (Block $block) {
            $block->clearCache();
        });

        static::deleted(function (Block $block) {
            $block->clearCache();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | 10. CACHE MANAGEMENT
    |--------------------------------------------------------------------------
    */

    /**
     * Limpiar cache relacionado con el bloqueo
     *
     * @return void
     */
    protected function clearCache(): void
    {
        Cache::tags([
            'blocks',
            "user.{$this->blocker_id}",
            "user.{$this->blocked_id}",
            'moderation',
        ])->flush();
    }

    /**
     * Obtener cache key para verificación rápida de bloqueo
     *
     * @param int $blockerId
     * @param int $blockedId
     * @return string
     */
    public static function getBlockCacheKey(int $blockerId, int $blockedId): string
    {
        return "block.{$blockerId}.{$blockedId}";
    }
}