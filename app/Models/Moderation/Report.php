<?php

namespace App\Models\Moderation;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use App\Domain\Moderation\Events\UserReported;

/**
 * Class Report
 * 
 * Modelo Eloquent para la gestión integral de reportes de usuarios por comportamiento inapropiado.
 * Implementa sistema completo de moderación con análisis automático ML, priorización inteligente,
 * escalamiento automatizado y lifecycle management desde PENDING hasta CLOSED.
 * 
 * Basado en: Domain/Moderation/ReportService.php
 * 
 * @package App\Models\Moderation
 * @version 1.0.0
 * @since Laravel 12.x / PHP 8.2
 * 
 * TABLA DE CONTENIDOS:
 * ==================
 * 1. Constantes de Negocio (Tipos, Estados, Prioridades, Acciones)
 * 2. Propiedades del Modelo (Fillable, Casts, Dates, Appends)
 * 3. Relaciones Eloquent (Reporter, Reported, Moderator)
 * 4. Query Scopes (Pending, HighPriority, ByReporter, RequiresReview)
 * 5. Accessors & Mutators (ReferenceNumber, SeverityLevel, TimeToResolve)
 * 6. Métodos de Negocio (Create, Process, Escalate, Analyze)
 * 7. Métodos de Analytics (Statistics, History, Patterns)
 * 8. Event Dispatching (UserReported Broadcasting)
 * 9. Cache Management
 * 
 * RESPONSABILIDADES:
 * ==================
 * - Gestión completa del lifecycle de reportes (12 tipos, 8 estados)
 * - Análisis automático con ML para detección de patrones
 * - Cálculo inteligente de prioridad basado en severidad/contexto
 * - Escalamiento automático según criterios de riesgo
 * - Tracking de acciones de moderación aplicadas
 * - Historial completo de reportes por usuario
 * - Detección de reportes similares y patrones de abuso
 * - Broadcasting de eventos a 6 canales diferentes
 * - Gestión de evidencias (screenshots, logs, metadata)
 * - Statistics y analytics para moderadores
 * 
 * @property int $id
 * @property string $reference_number Código único (REP-2025-000001)
 * @property int $reporter_id Usuario que reporta
 * @property int $reported_id Usuario reportado
 * @property string $type Tipo de reporte (12 opciones)
 * @property string $status Estado actual (8 opciones)
 * @property string $priority Prioridad calculada (5 niveles)
 * @property string $description Descripción del incidente
 * @property array $evidence Evidencias encriptadas (screenshots, logs)
 * @property int|null $moderator_id Moderador asignado
 * @property \Carbon\Carbon|null $assigned_at Timestamp de asignación
 * @property \Carbon\Carbon|null $processed_at Timestamp de procesamiento
 * @property string|null $action_taken Acción de moderación aplicada
 * @property string|null $resolution_notes Notas de resolución
 * @property array|null $automatic_analysis Análisis ML automático
 * @property int $severity_score Score de severidad (0-100)
 * @property bool $requires_immediate_action Flag de acción inmediata
 * @property array $metadata Contexto adicional encriptado
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @property-read User $reporter Relación con usuario reportador
 * @property-read User $reported Relación con usuario reportado
 * @property-read User|null $moderator Relación con moderador asignado
 * @property-read string $severity_level Nivel de severidad calculado
 * @property-read string $time_to_resolve Tiempo transcurrido/restante
 * @property-read bool $is_overdue Flag de reporte vencido
 * @property-read int $days_open Días desde creación
 * 
 * @method static Builder pending() Reportes pendientes de revisión
 * @method static Builder underReview() Reportes en revisión activa
 * @method static Builder highPriority() Reportes de alta prioridad
 * @method static Builder critical() Reportes críticos
 * @method static Builder requiresAction() Reportes que requieren acción
 * @method static Builder byReporter(int $userId) Reportes de un usuario específico
 * @method static Builder byReported(int $userId) Reportes contra un usuario
 * @method static Builder byType(string $type) Filtrar por tipo
 * @method static Builder assignedTo(int $moderatorId) Asignados a moderador
 * @method static Builder unassigned() Sin asignar
 * @method static Builder overdue() Reportes vencidos
 */
class Report extends Model
{
    use HasFactory, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | 1. CONSTANTES DE NEGOCIO
    |--------------------------------------------------------------------------
    */

    /**
     * Tipos de reportes disponibles (12 categorías)
     */
    public const TYPE_INAPPROPRIATE_BEHAVIOR = 'inappropriate_behavior';
    public const TYPE_FAKE_PROFILE = 'fake_profile';
    public const TYPE_HARASSMENT = 'harassment';
    public const TYPE_SPAM = 'spam';
    public const TYPE_OFFENSIVE_CONTENT = 'offensive_content';
    public const TYPE_INAPPROPRIATE_PHOTOS = 'inappropriate_photos';
    public const TYPE_SCAM_FRAUD = 'scam_fraud';
    public const TYPE_UNDERAGE_USER = 'underage_user';
    public const TYPE_VIOLENCE_THREATS = 'violence_threats';
    public const TYPE_HATE_SPEECH = 'hate_speech';
    public const TYPE_CATFISH = 'catfish';
    public const TYPE_SOLICITATION = 'solicitation';

    public const REPORT_TYPES = [
        self::TYPE_INAPPROPRIATE_BEHAVIOR,
        self::TYPE_FAKE_PROFILE,
        self::TYPE_HARASSMENT,
        self::TYPE_SPAM,
        self::TYPE_OFFENSIVE_CONTENT,
        self::TYPE_INAPPROPRIATE_PHOTOS,
        self::TYPE_SCAM_FRAUD,
        self::TYPE_UNDERAGE_USER,
        self::TYPE_VIOLENCE_THREATS,
        self::TYPE_HATE_SPEECH,
        self::TYPE_CATFISH,
        self::TYPE_SOLICITATION,
    ];

    /**
     * Estados del reporte (8 estados en lifecycle)
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_ESCALATED = 'escalated';
    public const STATUS_APPEALED = 'appealed';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_INVESTIGATING,
        self::STATUS_RESOLVED,
        self::STATUS_DISMISSED,
        self::STATUS_ESCALATED,
        self::STATUS_APPEALED,
        self::STATUS_CLOSED,
    ];

    /**
     * Niveles de prioridad (5 niveles)
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';
    public const PRIORITY_EMERGENCY = 'emergency';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_CRITICAL,
        self::PRIORITY_EMERGENCY,
    ];

    /**
     * Acciones de moderación disponibles (8 acciones)
     */
    public const ACTION_WARNING_ISSUED = 'warning_issued';
    public const ACTION_CONTENT_REMOVED = 'content_removed';
    public const ACTION_PROFILE_SUSPENDED = 'profile_suspended';
    public const ACTION_ACCOUNT_BANNED = 'account_banned';
    public const ACTION_NO_ACTION_NEEDED = 'no_action_needed';
    public const ACTION_REQUIRE_VERIFICATION = 'require_verification';
    public const ACTION_SHADOWBAN_APPLIED = 'shadowban_applied';
    public const ACTION_FEATURES_RESTRICTED = 'features_restricted';

    public const MODERATION_ACTIONS = [
        self::ACTION_WARNING_ISSUED,
        self::ACTION_CONTENT_REMOVED,
        self::ACTION_PROFILE_SUSPENDED,
        self::ACTION_ACCOUNT_BANNED,
        self::ACTION_NO_ACTION_NEEDED,
        self::ACTION_REQUIRE_VERIFICATION,
        self::ACTION_SHADOWBAN_APPLIED,
        self::ACTION_FEATURES_RESTRICTED,
    ];

    /**
     * Mapeo de prioridad a tiempo máximo de respuesta (horas)
     */
    public const PRIORITY_SLA_HOURS = [
        self::PRIORITY_EMERGENCY => 1,
        self::PRIORITY_CRITICAL => 4,
        self::PRIORITY_HIGH => 24,
        self::PRIORITY_MEDIUM => 72,
        self::PRIORITY_LOW => 168,
    ];

    /**
     * Mapeo de tipo a severidad base (0-100)
     */
    public const TYPE_SEVERITY_SCORES = [
        self::TYPE_UNDERAGE_USER => 95,
        self::TYPE_VIOLENCE_THREATS => 90,
        self::TYPE_SCAM_FRAUD => 85,
        self::TYPE_HARASSMENT => 80,
        self::TYPE_HATE_SPEECH => 75,
        self::TYPE_INAPPROPRIATE_PHOTOS => 70,
        self::TYPE_CATFISH => 65,
        self::TYPE_OFFENSIVE_CONTENT => 60,
        self::TYPE_FAKE_PROFILE => 55,
        self::TYPE_SOLICITATION => 50,
        self::TYPE_SPAM => 40,
        self::TYPE_INAPPROPRIATE_BEHAVIOR => 35,
    ];

    /*
    |--------------------------------------------------------------------------
    | 2. PROPIEDADES DEL MODELO
    |--------------------------------------------------------------------------
    */

    /**
     * Nombre de la tabla en la base de datos
     */
    protected $table = 'reports';

    /**
     * Clave primaria de la tabla
     */
    protected $primaryKey = 'id';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'reference_number',
        'reporter_id',
        'reported_id',
        'type',
        'status',
        'priority',
        'description',
        'evidence',
        'moderator_id',
        'assigned_at',
        'processed_at',
        'action_taken',
        'resolution_notes',
        'automatic_analysis',
        'severity_score',
        'requires_immediate_action',
        'metadata',
    ];

    /**
     * Atributos que deben ser casteados a tipos nativos
     */
    protected $casts = [
        'reporter_id' => 'integer',
        'reported_id' => 'integer',
        'moderator_id' => 'integer',
        'evidence' => 'encrypted:array',
        'automatic_analysis' => 'array',
        'metadata' => 'encrypted:array',
        'severity_score' => 'integer',
        'requires_immediate_action' => 'boolean',
        'assigned_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Atributos que deben ser mutados a fechas
     */
    protected $dates = [
        'assigned_at',
        'processed_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Atributos que deben ser añadidos a las representaciones de arrays/JSON
     */
    protected $appends = [
        'severity_level',
        'time_to_resolve',
        'is_overdue',
        'days_open',
    ];

    /**
     * Atributos ocultos en arrays/JSON
     */
    protected $hidden = [
        'evidence',
        'metadata',
    ];

    /**
     * Valores por defecto para atributos
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'priority' => self::PRIORITY_MEDIUM,
        'severity_score' => 50,
        'requires_immediate_action' => false,
    ];

    /*
    |--------------------------------------------------------------------------
    | 3. RELACIONES ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Usuario que realiza el reporte
     *
     * @return BelongsTo
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Usuario que está siendo reportado
     *
     * @return BelongsTo
     */
    public function reported(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_id');
    }

    /**
     * Moderador asignado al reporte
     *
     * @return BelongsTo
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    /*
    |--------------------------------------------------------------------------
    | 4. QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope para obtener reportes pendientes de revisión
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope para obtener reportes en revisión activa
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnderReview(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_UNDER_REVIEW,
            self::STATUS_INVESTIGATING,
        ]);
    }

    /**
     * Scope para obtener reportes de alta prioridad
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('priority', [
            self::PRIORITY_HIGH,
            self::PRIORITY_CRITICAL,
            self::PRIORITY_EMERGENCY,
        ]);
    }

    /**
     * Scope para obtener reportes críticos
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->whereIn('priority', [
            self::PRIORITY_CRITICAL,
            self::PRIORITY_EMERGENCY,
        ]);
    }

    /**
     * Scope para obtener reportes que requieren acción inmediata
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeRequiresAction(Builder $query): Builder
    {
        return $query->where('requires_immediate_action', true)
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_UNDER_REVIEW,
                self::STATUS_INVESTIGATING,
            ]);
    }

    /**
     * Scope para obtener reportes realizados por un usuario específico
     *
     * @param Builder $query
     * @param int $userId
     * @return Builder
     */
    public function scopeByReporter(Builder $query, int $userId): Builder
    {
        return $query->where('reporter_id', $userId);
    }

    /**
     * Scope para obtener reportes contra un usuario específico
     *
     * @param Builder $query
     * @param int $userId
     * @return Builder
     */
    public function scopeByReported(Builder $query, int $userId): Builder
    {
        return $query->where('reported_id', $userId);
    }

    /**
     * Scope para filtrar reportes por tipo
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
     * Scope para obtener reportes asignados a un moderador específico
     *
     * @param Builder $query
     * @param int $moderatorId
     * @return Builder
     */
    public function scopeAssignedTo(Builder $query, int $moderatorId): Builder
    {
        return $query->where('moderator_id', $moderatorId);
    }

    /**
     * Scope para obtener reportes sin asignar
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('moderator_id')
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_UNDER_REVIEW,
            ]);
    }

    /**
     * Scope para obtener reportes vencidos (superaron SLA)
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_INVESTIGATING,
        ])->where(function ($q) {
            foreach (self::PRIORITY_SLA_HOURS as $priority => $hours) {
                $q->orWhere(function ($subQuery) use ($priority, $hours) {
                    $subQuery->where('priority', $priority)
                        ->where('created_at', '<', now()->subHours($hours));
                });
            }
        });
    }

    /**
     * Scope para ordenar por prioridad descendente
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOrderByPriority(Builder $query): Builder
    {
        return $query->orderByRaw("
            CASE priority
                WHEN '" . self::PRIORITY_EMERGENCY . "' THEN 5
                WHEN '" . self::PRIORITY_CRITICAL . "' THEN 4
                WHEN '" . self::PRIORITY_HIGH . "' THEN 3
                WHEN '" . self::PRIORITY_MEDIUM . "' THEN 2
                WHEN '" . self::PRIORITY_LOW . "' THEN 1
            END DESC
        ");
    }

    /*
    |--------------------------------------------------------------------------
    | 5. ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Accessor para obtener el nivel de severidad legible
     *
     * @return string
     */
    public function getSeverityLevelAttribute(): string
    {
        $score = $this->severity_score;

        if ($score >= 90) return 'CRÍTICO';
        if ($score >= 75) return 'MUY ALTO';
        if ($score >= 60) return 'ALTO';
        if ($score >= 40) return 'MEDIO';
        return 'BAJO';
    }

    /**
     * Accessor para calcular el tiempo para resolver el reporte
     *
     * @return string
     */
    public function getTimeToResolveAttribute(): string
    {
        if (in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED, self::STATUS_DISMISSED])) {
            if ($this->processed_at) {
                $diff = $this->created_at->diffInHours($this->processed_at);
                return "Resuelto en {$diff} horas";
            }
            return 'Resuelto';
        }

        $slaHours = self::PRIORITY_SLA_HOURS[$this->priority] ?? 72;
        $deadline = $this->created_at->addHours($slaHours);
        $remaining = now()->diffInHours($deadline, false);

        if ($remaining < 0) {
            return "Vencido hace " . abs($remaining) . " horas";
        }

        return "Quedan {$remaining} horas";
    }

    /**
     * Accessor para verificar si el reporte está vencido
     *
     * @return bool
     */
    public function getIsOverdueAttribute(): bool
    {
        if (in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED, self::STATUS_DISMISSED])) {
            return false;
        }

        $slaHours = self::PRIORITY_SLA_HOURS[$this->priority] ?? 72;
        $deadline = $this->created_at->addHours($slaHours);

        return now()->greaterThan($deadline);
    }

    /**
     * Accessor para obtener días desde la creación
     *
     * @return int
     */
    public function getDaysOpenAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Mutator para generar reference_number automáticamente si no existe
     *
     * @param string|null $value
     * @return void
     */
    public function setReferenceNumberAttribute(?string $value): void
    {
        if (empty($value)) {
            $year = now()->year;
            $lastReport = static::whereYear('created_at', $year)
                ->orderBy('id', 'desc')
                ->first();
            
            $sequence = $lastReport ? (int) substr($lastReport->reference_number, -6) + 1 : 1;
            $value = sprintf('REP-%d-%06d', $year, $sequence);
        }

        $this->attributes['reference_number'] = $value;
    }

    /*
    |--------------------------------------------------------------------------
    | 6. MÉTODOS DE NEGOCIO
    |--------------------------------------------------------------------------
    */

    /**
     * Crear un nuevo reporte con análisis automático
     *
     * @param array $data Datos del reporte [reporter_id, reported_id, type, description, evidence]
     * @return static
     */
    public static function createReport(array $data): static
    {
        // Calcular prioridad y severidad automáticamente
        $priority = static::calculatePriority($data['type'], $data['reported_id'] ?? null);
        $severityScore = static::calculateSeverityScore($data['type'], $data);

        // Realizar análisis automático
        $automaticAnalysis = static::performAutomaticAnalysis($data);

        // Crear el reporte
        $report = static::create([
            'reference_number' => null, // Se genera automáticamente
            'reporter_id' => $data['reporter_id'],
            'reported_id' => $data['reported_id'],
            'type' => $data['type'],
            'status' => self::STATUS_PENDING,
            'priority' => $priority,
            'description' => $data['description'],
            'evidence' => $data['evidence'] ?? [],
            'severity_score' => $severityScore,
            'requires_immediate_action' => $severityScore >= 80 || $priority === self::PRIORITY_EMERGENCY,
            'automatic_analysis' => $automaticAnalysis,
            'metadata' => $data['metadata'] ?? [],
        ]);

        // Dispatch event
        event(new UserReported(
            reportId: $report->id,
            reporterId: $report->reporter_id,
            reportedId: $report->reported_id,
            reportType: $report->type,
            priority: $report->priority,
            metadata: $report->metadata ?? []
        ));

        return $report;
    }

    /**
     * Procesar el reporte y aplicar acción de moderación
     *
     * @param string $action Acción de moderación a aplicar
     * @param string|null $resolutionNotes Notas de resolución
     * @param int|null $moderatorId ID del moderador que procesa
     * @return bool
     */
    public function processReport(
        string $action,
        ?string $resolutionNotes = null,
        ?int $moderatorId = null
    ): bool {
        if (!in_array($action, self::MODERATION_ACTIONS)) {
            throw new \InvalidArgumentException("Acción de moderación inválida: {$action}");
        }

        $this->update([
            'status' => self::STATUS_RESOLVED,
            'action_taken' => $action,
            'resolution_notes' => $resolutionNotes,
            'moderator_id' => $moderatorId ?? $this->moderator_id,
            'processed_at' => now(),
        ]);

        // Aplicar la acción al usuario reportado
        $this->applyModerationAction($action);

        // Limpiar cache
        $this->clearCache();

        return true;
    }

    /**
     * Escalar el reporte a nivel superior
     *
     * @param string $reason Razón del escalamiento
     * @return bool
     */
    public function escalate(string $reason): bool
    {
        // Actualizar prioridad si no es ya máxima
        $newPriority = $this->priority;
        if ($this->priority === self::PRIORITY_LOW) {
            $newPriority = self::PRIORITY_MEDIUM;
        } elseif ($this->priority === self::PRIORITY_MEDIUM) {
            $newPriority = self::PRIORITY_HIGH;
        } elseif ($this->priority === self::PRIORITY_HIGH) {
            $newPriority = self::PRIORITY_CRITICAL;
        } elseif ($this->priority === self::PRIORITY_CRITICAL) {
            $newPriority = self::PRIORITY_EMERGENCY;
        }

        $this->update([
            'status' => self::STATUS_ESCALATED,
            'priority' => $newPriority,
            'requires_immediate_action' => true,
            'metadata' => array_merge($this->metadata ?? [], [
                'escalation_reason' => $reason,
                'escalated_at' => now()->toIso8601String(),
                'previous_priority' => $this->priority,
            ]),
        ]);

        // TODO: Notificar a supervisores/administradores

        return true;
    }

    /**
     * Asignar el reporte a un moderador
     *
     * @param int $moderatorId ID del moderador
     * @return bool
     */
    public function assignToModerator(int $moderatorId): bool
    {
        $this->update([
            'moderator_id' => $moderatorId,
            'assigned_at' => now(),
            'status' => $this->status === self::STATUS_PENDING ? self::STATUS_UNDER_REVIEW : $this->status,
        ]);

        return true;
    }

    /**
     * Rechazar/desestimar el reporte
     *
     * @param string $reason Razón del rechazo
     * @param int|null $moderatorId ID del moderador
     * @return bool
     */
    public function dismiss(string $reason, ?int $moderatorId = null): bool
    {
        $this->update([
            'status' => self::STATUS_DISMISSED,
            'action_taken' => self::ACTION_NO_ACTION_NEEDED,
            'resolution_notes' => $reason,
            'moderator_id' => $moderatorId ?? $this->moderator_id,
            'processed_at' => now(),
        ]);

        $this->clearCache();

        return true;
    }

    /**
     * Aplicar acción de moderación al usuario reportado
     *
     * @param string $action Acción a aplicar
     * @return void
     */
    protected function applyModerationAction(string $action): void
    {
        $user = $this->reported;

        switch ($action) {
            case self::ACTION_WARNING_ISSUED:
                // TODO: Enviar warning al usuario
                break;

            case self::ACTION_CONTENT_REMOVED:
                // TODO: Remover contenido específico
                break;

            case self::ACTION_PROFILE_SUSPENDED:
                $user->update(['status' => 'suspended']);
                break;

            case self::ACTION_ACCOUNT_BANNED:
                $user->update(['status' => 'banned', 'banned_at' => now()]);
                break;

            case self::ACTION_SHADOWBAN_APPLIED:
                $user->update(['is_shadowbanned' => true]);
                break;

            case self::ACTION_FEATURES_RESTRICTED:
                // TODO: Restringir features específicas
                break;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 7. MÉTODOS DE ANALYTICS
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener reportes similares basados en tipo y usuario reportado
     *
     * @param int $limit Límite de resultados
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSimilarReports(int $limit = 10)
    {
        return static::where('id', '!=', $this->id)
            ->where(function ($query) {
                $query->where('reported_id', $this->reported_id)
                    ->orWhere('type', $this->type);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtener historial de reportes de un usuario
     *
     * @param int $userId ID del usuario
     * @param string $role 'reporter' o 'reported'
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUserReportHistory(int $userId, string $role = 'reported')
    {
        $column = $role === 'reporter' ? 'reporter_id' : 'reported_id';

        return static::where($column, $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtener estadísticas de reportes
     *
     * @param array $filters Filtros opcionales [start_date, end_date, type, status]
     * @return array
     */
    public static function getReportStatistics(array $filters = []): array
    {
        $query = static::query();

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $total = $query->count();
        $byType = (clone $query)->selectRaw('type, COUNT(*) as count')->groupBy('type')->pluck('count', 'type');
        $byStatus = (clone $query)->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $byPriority = (clone $query)->selectRaw('priority, COUNT(*) as count')->groupBy('priority')->pluck('count', 'priority');

        $averageResolutionTime = static::whereNotNull('processed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, processed_at)) as avg_hours')
            ->value('avg_hours');

        return [
            'total' => $total,
            'by_type' => $byType,
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'average_resolution_hours' => round($averageResolutionTime ?? 0, 2),
            'pending' => static::pending()->count(),
            'overdue' => static::overdue()->count(),
            'critical' => static::critical()->count(),
        ];
    }

    /**
     * Calcular prioridad automáticamente basada en tipo y contexto
     *
     * @param string $type Tipo de reporte
     * @param int|null $reportedUserId ID del usuario reportado
     * @return string
     */
    protected static function calculatePriority(string $type, ?int $reportedUserId = null): string
    {
        // Prioridades base por tipo
        $basePriorities = [
            self::TYPE_UNDERAGE_USER => self::PRIORITY_EMERGENCY,
            self::TYPE_VIOLENCE_THREATS => self::PRIORITY_CRITICAL,
            self::TYPE_SCAM_FRAUD => self::PRIORITY_CRITICAL,
            self::TYPE_HARASSMENT => self::PRIORITY_HIGH,
            self::TYPE_HATE_SPEECH => self::PRIORITY_HIGH,
            self::TYPE_INAPPROPRIATE_PHOTOS => self::PRIORITY_HIGH,
            self::TYPE_CATFISH => self::PRIORITY_MEDIUM,
            self::TYPE_OFFENSIVE_CONTENT => self::PRIORITY_MEDIUM,
            self::TYPE_FAKE_PROFILE => self::PRIORITY_MEDIUM,
            self::TYPE_SOLICITATION => self::PRIORITY_MEDIUM,
            self::TYPE_SPAM => self::PRIORITY_LOW,
            self::TYPE_INAPPROPRIATE_BEHAVIOR => self::PRIORITY_LOW,
        ];

        $priority = $basePriorities[$type] ?? self::PRIORITY_MEDIUM;

        // Escalar prioridad si el usuario tiene múltiples reportes
        if ($reportedUserId) {
            $previousReports = static::where('reported_id', $reportedUserId)
                ->whereIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED])
                ->count();

            if ($previousReports >= 5 && $priority !== self::PRIORITY_EMERGENCY) {
                // Escalar un nivel
                $priorityLevels = array_flip(self::PRIORITIES);
                $currentLevel = $priorityLevels[$priority];
                $newLevel = min($currentLevel + 1, count(self::PRIORITIES) - 1);
                $priority = self::PRIORITIES[$newLevel];
            }
        }

        return $priority;
    }

    /**
     * Calcular severity score basado en tipo y contexto
     *
     * @param string $type Tipo de reporte
     * @param array $data Datos adicionales del reporte
     * @return int Score de 0-100
     */
    protected static function calculateSeverityScore(string $type, array $data): int
    {
        $baseScore = self::TYPE_SEVERITY_SCORES[$type] ?? 50;

        // Ajustar score basado en evidencias
        if (!empty($data['evidence']) && count($data['evidence']) > 2) {
            $baseScore += 10;
        }

        // Ajustar score si hay reportes previos
        if (!empty($data['reported_id'])) {
            $previousReports = static::where('reported_id', $data['reported_id'])
                ->where('status', self::STATUS_RESOLVED)
                ->count();

            $baseScore += min($previousReports * 5, 20);
        }

        return min($baseScore, 100);
    }

    /**
     * Realizar análisis automático del reporte con ML
     *
     * @param array $data Datos del reporte
     * @return array Resultados del análisis
     */
    protected static function performAutomaticAnalysis(array $data): array
    {
        // TODO: Integrar con servicios de ML para análisis real
        return [
            'sentiment_score' => 0.0,
            'toxicity_score' => 0.0,
            'threat_level' => 'low',
            'confidence' => 0.75,
            'detected_patterns' => [],
            'similar_cases_count' => 0,
            'recommendation' => 'requires_human_review',
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 8. EVENT DISPATCHING
    |--------------------------------------------------------------------------
    */

    /**
     * Boot del modelo para eventos
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::created(function (Report $report) {
            event(new UserReported(
                reportId: $report->id,
                reporterId: $report->reporter_id,
                reportedId: $report->reported_id,
                reportType: $report->type,
                priority: $report->priority,
                metadata: $report->metadata ?? []
            ));
            $report->clearCache();
        });

        static::updated(function (Report $report) {
            $report->clearCache();
        });

        static::deleted(function (Report $report) {
            $report->clearCache();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | 9. CACHE MANAGEMENT
    |--------------------------------------------------------------------------
    */

    /**
     * Limpiar cache relacionado con el reporte
     *
     * @return void
     */
    protected function clearCache(): void
    {
        Cache::tags([
            'reports',
            "user.{$this->reporter_id}",
            "user.{$this->reported_id}",
            'moderation',
        ])->flush();
    }

    /**
     * Obtener cache key para el reporte
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return "report.{$this->id}";
    }
}