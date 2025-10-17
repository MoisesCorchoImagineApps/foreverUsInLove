<?php

namespace App\Models\Moderation;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Class PQRS
 * 
 * Modelo Eloquent para la gestión completa del sistema de tickets PQRS
 * (Peticiones, Quejas, Reclamos, Sugerencias) con SLA automatizado,
 * escalamiento inteligente y asignación optimizada de agentes.
 * 
 * Basado en: Domain/Moderation/PQRSService.php
 * 
 * @package App\Models\Moderation
 * @version 1.0.0
 * @since Laravel 12.x / PHP 8.2
 * 
 * TABLA DE CONTENIDOS:
 * ==================
 * 1. Constantes de Negocio (Tipos, Categorías, Prioridades, Estados, SLA)
 * 2. Propiedades del Modelo (Fillable, Casts, Dates, Appends)
 * 3. Relaciones Eloquent (User, Agent, Responses)
 * 4. Query Scopes (Pending, Assigned, BreachingSLA, ByCategory)
 * 5. Accessors & Mutators (TicketNumber, SLAStatus, TimeRemaining)
 * 6. Métodos de Negocio (Create, Assign, Escalate, UpdateStatus)
 * 7. Métodos de SLA (CalculateSLA, IsSLABreached, GetDeadline)
 * 8. Métodos de Analytics (Statistics, History, PerformanceMetrics)
 * 9. Event Dispatching (Notifications por prioridad)
 * 10. Cache Management
 * 
 * RESPONSABILIDADES:
 * ==================
 * - Gestión de 4 tipos de PQRS (Petición, Queja, Reclamo, Sugerencia)
 * - 24 categorías organizadas por área (Técnica, Cuenta, Pagos, Moderación, Features)
 * - Sistema de priorización automática (5 niveles)
 * - SLA management con deadlines por prioridad (1h-168h)
 * - Escalamiento automático cuando se incumple SLA
 * - Asignación inteligente de agentes con balanceo de carga
 * - Tracking completo de estados (10 estados en lifecycle)
 * - Múltiples canales de comunicación (5 canales)
 * - Gestión de respuestas y attachments
 * - Analytics y métricas de performance
 * - Notificaciones diferenciadas por prioridad (Slack/Email/SMS)
 * - Generación automática de ticket numbers (PQRS-2025-000001)
 * 
 * @property int $id
 * @property string $ticket_number Código único (PQRS-2025-000001)
 * @property int $user_id Usuario que crea el PQRS
 * @property int|null $assigned_to Agente/Moderador asignado
 * @property string $type Tipo (4 opciones)
 * @property string $category Categoría (24 opciones)
 * @property string $priority Prioridad (5 niveles)
 * @property string $status Estado actual (10 estados)
 * @property string $channel Canal de comunicación (5 opciones)
 * @property string $subject Asunto del ticket
 * @property string $description Descripción detallada
 * @property array|null $attachments URLs de archivos adjuntos
 * @property \Carbon\Carbon $submitted_at Fecha de envío
 * @property \Carbon\Carbon|null $assigned_at Fecha de asignación
 * @property \Carbon\Carbon|null $resolved_at Fecha de resolución
 * @property \Carbon\Carbon|null $closed_at Fecha de cierre
 * @property \Carbon\Carbon $sla_deadline Deadline según SLA
 * @property int $response_count Contador de respuestas
 * @property int|null $satisfaction_rating Rating de satisfacción (1-5)
 * @property array $metadata Contexto adicional encriptado
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @property-read User $user Relación con usuario
 * @property-read User|null $agent Relación con agente asignado
 * @property-read string $sla_status Estado del SLA (on_time, warning, breached)
 * @property-read string $time_remaining Tiempo restante/excedido del SLA
 * @property-read bool $is_sla_breached Flag de SLA incumplido
 * @property-read int $hours_open Horas desde creación
 * @property-read string $priority_label Etiqueta legible de prioridad
 * 
 * @method static Builder pending() PQRS pendientes
 * @method static Builder assigned() PQRS asignados
 * @method static Builder unassigned() PQRS sin asignar
 * @method static Builder breachingSLA() PQRS incumpliendo SLA
 * @method static Builder byCategory(string $category) Filtrar por categoría
 * @method static Builder byType(string $type) Filtrar por tipo
 * @method static Builder byUser(int $userId) PQRS de un usuario
 * @method static Builder assignedTo(int $agentId) Asignados a agente
 * @method static Builder highPriority() Alta prioridad
 * @method static Builder critical() Prioridad crítica/urgente
 */
class PQRS extends Model
{
    use HasFactory, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | 1. CONSTANTES DE NEGOCIO
    |--------------------------------------------------------------------------
    */

    /**
     * Tipos de PQRS (4 categorías principales)
     */
    public const TYPE_PETITION = 'petition';
    public const TYPE_COMPLAINT = 'complaint';
    public const TYPE_CLAIM = 'claim';
    public const TYPE_SUGGESTION = 'suggestion';

    public const TYPES = [
        self::TYPE_PETITION,
        self::TYPE_COMPLAINT,
        self::TYPE_CLAIM,
        self::TYPE_SUGGESTION,
    ];

    /**
     * Categorías específicas (24 opciones organizadas por área)
     */
    // Categorías Técnicas
    public const CATEGORY_TECHNICAL_ISSUE = 'technical_issue';
    public const CATEGORY_APP_BUG = 'app_bug';
    public const CATEGORY_LOGIN_PROBLEMS = 'login_problems';
    public const CATEGORY_PERFORMANCE_ISSUE = 'performance_issue';

    // Categorías de Cuenta
    public const CATEGORY_ACCOUNT_SUSPENSION = 'account_suspension';
    public const CATEGORY_PROFILE_VERIFICATION = 'profile_verification';
    public const CATEGORY_ACCOUNT_DELETION = 'account_deletion';
    public const CATEGORY_PRIVACY_SETTINGS = 'privacy_settings';

    // Categorías de Pagos/Billing
    public const CATEGORY_BILLING_ISSUE = 'billing_issue';
    public const CATEGORY_REFUND_REQUEST = 'refund_request';
    public const CATEGORY_SUBSCRIPTION_PROBLEM = 'subscription_problem';
    public const CATEGORY_PAYMENT_FAILED = 'payment_failed';

    // Categorías de Moderación
    public const CATEGORY_INAPPROPRIATE_BEHAVIOR = 'inappropriate_behavior';
    public const CATEGORY_SAFETY_CONCERN = 'safety_concern';
    public const CATEGORY_FAKE_PROFILE = 'fake_profile';
    public const CATEGORY_HARASSMENT_REPORT = 'harassment_report';

    // Categorías de Funcionalidades
    public const CATEGORY_MATCHING_ALGORITHM = 'matching_algorithm';
    public const CATEGORY_MESSAGING_ISSUE = 'messaging_issue';
    public const CATEGORY_NOTIFICATION_PROBLEM = 'notification_problem';
    public const CATEGORY_FEATURE_REQUEST = 'feature_request';

    // Categorías Generales
    public const CATEGORY_GENERAL_INQUIRY = 'general_inquiry';
    public const CATEGORY_FEEDBACK = 'feedback';
    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        // Technical
        self::CATEGORY_TECHNICAL_ISSUE,
        self::CATEGORY_APP_BUG,
        self::CATEGORY_LOGIN_PROBLEMS,
        self::CATEGORY_PERFORMANCE_ISSUE,
        // Account
        self::CATEGORY_ACCOUNT_SUSPENSION,
        self::CATEGORY_PROFILE_VERIFICATION,
        self::CATEGORY_ACCOUNT_DELETION,
        self::CATEGORY_PRIVACY_SETTINGS,
        // Billing
        self::CATEGORY_BILLING_ISSUE,
        self::CATEGORY_REFUND_REQUEST,
        self::CATEGORY_SUBSCRIPTION_PROBLEM,
        self::CATEGORY_PAYMENT_FAILED,
        // Moderation
        self::CATEGORY_INAPPROPRIATE_BEHAVIOR,
        self::CATEGORY_SAFETY_CONCERN,
        self::CATEGORY_FAKE_PROFILE,
        self::CATEGORY_HARASSMENT_REPORT,
        // Features
        self::CATEGORY_MATCHING_ALGORITHM,
        self::CATEGORY_MESSAGING_ISSUE,
        self::CATEGORY_NOTIFICATION_PROBLEM,
        self::CATEGORY_FEATURE_REQUEST,
        // General
        self::CATEGORY_GENERAL_INQUIRY,
        self::CATEGORY_FEEDBACK,
        self::CATEGORY_OTHER,
    ];

    /**
     * Niveles de prioridad (5 niveles)
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';
    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
        self::PRIORITY_CRITICAL,
    ];

    /**
     * Estados del PQRS (10 estados en lifecycle)
     */
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PENDING_INFO = 'pending_info';
    public const STATUS_ESCALATED = 'escalated';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_REOPENED = 'reopened';

    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_RECEIVED,
        self::STATUS_IN_REVIEW,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_PENDING_INFO,
        self::STATUS_ESCALATED,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
        self::STATUS_REOPENED,
    ];

    /**
     * Canales de comunicación (5 opciones)
     */
    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_PHONE = 'phone';
    public const CHANNEL_CHAT = 'chat';
    public const CHANNEL_SOCIAL_MEDIA = 'social_media';

    public const CHANNELS = [
        self::CHANNEL_IN_APP,
        self::CHANNEL_EMAIL,
        self::CHANNEL_PHONE,
        self::CHANNEL_CHAT,
        self::CHANNEL_SOCIAL_MEDIA,
    ];

    /**
     * SLA (Service Level Agreement) por prioridad en horas
     */
    public const SLA_HOURS = [
        self::PRIORITY_CRITICAL => 1,
        self::PRIORITY_URGENT => 4,
        self::PRIORITY_HIGH => 24,
        self::PRIORITY_MEDIUM => 72,
        self::PRIORITY_LOW => 168,
    ];

    /**
     * Mapeo de categoría a prioridad por defecto
     */
    public const CATEGORY_DEFAULT_PRIORITY = [
        // Critical
        self::CATEGORY_ACCOUNT_SUSPENSION => self::PRIORITY_CRITICAL,
        self::CATEGORY_PAYMENT_FAILED => self::PRIORITY_CRITICAL,
        self::CATEGORY_LOGIN_PROBLEMS => self::PRIORITY_CRITICAL,
        
        // Urgent
        self::CATEGORY_SAFETY_CONCERN => self::PRIORITY_URGENT,
        self::CATEGORY_HARASSMENT_REPORT => self::PRIORITY_URGENT,
        self::CATEGORY_REFUND_REQUEST => self::PRIORITY_URGENT,
        
        // High
        self::CATEGORY_BILLING_ISSUE => self::PRIORITY_HIGH,
        self::CATEGORY_SUBSCRIPTION_PROBLEM => self::PRIORITY_HIGH,
        self::CATEGORY_ACCOUNT_DELETION => self::PRIORITY_HIGH,
        self::CATEGORY_FAKE_PROFILE => self::PRIORITY_HIGH,
        
        // Medium
        self::CATEGORY_TECHNICAL_ISSUE => self::PRIORITY_MEDIUM,
        self::CATEGORY_APP_BUG => self::PRIORITY_MEDIUM,
        self::CATEGORY_PROFILE_VERIFICATION => self::PRIORITY_MEDIUM,
        self::CATEGORY_MESSAGING_ISSUE => self::PRIORITY_MEDIUM,
        self::CATEGORY_NOTIFICATION_PROBLEM => self::PRIORITY_MEDIUM,
        
        // Low
        self::CATEGORY_FEATURE_REQUEST => self::PRIORITY_LOW,
        self::CATEGORY_FEEDBACK => self::PRIORITY_LOW,
        self::CATEGORY_GENERAL_INQUIRY => self::PRIORITY_LOW,
        self::CATEGORY_OTHER => self::PRIORITY_LOW,
    ];

    /*
    |--------------------------------------------------------------------------
    | 2. PROPIEDADES DEL MODELO
    |--------------------------------------------------------------------------
    */

    /**
     * Nombre de la tabla en la base de datos
     */
    protected $table = 'pqrs';

    /**
     * Clave primaria de la tabla
     */
    protected $primaryKey = 'id';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'ticket_number',
        'user_id',
        'assigned_to',
        'type',
        'category',
        'priority',
        'status',
        'channel',
        'subject',
        'description',
        'attachments',
        'submitted_at',
        'assigned_at',
        'resolved_at',
        'closed_at',
        'sla_deadline',
        'response_count',
        'satisfaction_rating',
        'metadata',
    ];

    /**
     * Atributos que deben ser casteados a tipos nativos
     */
    protected $casts = [
        'user_id' => 'integer',
        'assigned_to' => 'integer',
        'attachments' => 'array',
        'response_count' => 'integer',
        'satisfaction_rating' => 'integer',
        'metadata' => 'encrypted:array',
        'submitted_at' => 'datetime',
        'assigned_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_deadline' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Atributos que deben ser mutados a fechas
     */
    protected $dates = [
        'submitted_at',
        'assigned_at',
        'resolved_at',
        'closed_at',
        'sla_deadline',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Atributos que deben ser añadidos a las representaciones de arrays/JSON
     */
    protected $appends = [
        'sla_status',
        'time_remaining',
        'is_sla_breached',
        'hours_open',
        'priority_label',
    ];

    /**
     * Valores por defecto para atributos
     */
    protected $attributes = [
        'status' => self::STATUS_SUBMITTED,
        'priority' => self::PRIORITY_MEDIUM,
        'channel' => self::CHANNEL_IN_APP,
        'response_count' => 0,
    ];

    /*
    |--------------------------------------------------------------------------
    | 3. RELACIONES ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Usuario que creó el PQRS
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Agente/Moderador asignado al PQRS
     *
     * @return BelongsTo
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /*
    |--------------------------------------------------------------------------
    | 4. QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope para obtener PQRS pendientes
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_SUBMITTED,
            self::STATUS_RECEIVED,
            self::STATUS_IN_REVIEW,
        ]);
    }

    /**
     * Scope para obtener PQRS asignados
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereNotNull('assigned_to');
    }

    /**
     * Scope para obtener PQRS sin asignar
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to')
            ->whereIn('status', [
                self::STATUS_SUBMITTED,
                self::STATUS_RECEIVED,
                self::STATUS_IN_REVIEW,
            ]);
    }

    /**
     * Scope para obtener PQRS incumpliendo SLA
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeBreachingSLA(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ])->where('sla_deadline', '<', now());
    }

    /**
     * Scope para obtener PQRS próximos a incumplir SLA (warning zone)
     *
     * @param Builder $query
     * @param int $hoursBuffer Horas antes del deadline
     * @return Builder
     */
    public function scopeApproachingSLA(Builder $query, int $hoursBuffer = 2): Builder
    {
        return $query->whereNotIn('status', [
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ])->whereBetween('sla_deadline', [now(), now()->addHours($hoursBuffer)]);
    }

    /**
     * Scope para filtrar por categoría
     *
     * @param Builder $query
     * @param string $category
     * @return Builder
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope para filtrar por tipo
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
     * Scope para obtener PQRS de un usuario específico
     *
     * @param Builder $query
     * @param int $userId
     * @return Builder
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para obtener PQRS asignados a un agente específico
     *
     * @param Builder $query
     * @param int $agentId
     * @return Builder
     */
    public function scopeAssignedTo(Builder $query, int $agentId): Builder
    {
        return $query->where('assigned_to', $agentId);
    }

    /**
     * Scope para obtener PQRS de alta prioridad
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('priority', [
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
            self::PRIORITY_CRITICAL,
        ]);
    }

    /**
     * Scope para obtener PQRS críticos/urgentes
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->whereIn('priority', [
            self::PRIORITY_URGENT,
            self::PRIORITY_CRITICAL,
        ]);
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
                WHEN '" . self::PRIORITY_CRITICAL . "' THEN 5
                WHEN '" . self::PRIORITY_URGENT . "' THEN 4
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
     * Accessor para obtener el estado del SLA
     *
     * @return string on_time|warning|breached
     */
    public function getSlaStatusAttribute(): string
    {
        if (in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED])) {
            return 'completed';
        }

        $now = now();
        $deadline = $this->sla_deadline;
        $hoursRemaining = $now->diffInHours($deadline, false);

        if ($hoursRemaining < 0) {
            return 'breached';
        } elseif ($hoursRemaining <= 2) {
            return 'warning';
        }

        return 'on_time';
    }

    /**
     * Accessor para obtener el tiempo restante/excedido del SLA
     *
     * @return string
     */
    public function getTimeRemainingAttribute(): string
    {
        if (in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED])) {
            if ($this->resolved_at) {
                $hours = $this->submitted_at->diffInHours($this->resolved_at);
                return "Resuelto en {$hours}h";
            }
            return 'Completado';
        }

        $now = now();
        $deadline = $this->sla_deadline;
        $hoursRemaining = $now->diffInHours($deadline, false);

        if ($hoursRemaining < 0) {
            return 'Vencido hace ' . abs($hoursRemaining) . 'h';
        }

        $days = floor($hoursRemaining / 24);
        $hours = $hoursRemaining % 24;

        if ($days > 0) {
            return "{$days}d {$hours}h restantes";
        }

        return "{$hours}h restantes";
    }

    /**
     * Accessor para verificar si el SLA está incumplido
     *
     * @return bool
     */
    public function getIsSlaBreachedAttribute(): bool
    {
        if (in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED])) {
            return false;
        }

        return now()->greaterThan($this->sla_deadline);
    }

    /**
     * Accessor para obtener las horas desde la creación
     *
     * @return int
     */
    public function getHoursOpenAttribute(): int
    {
        return $this->submitted_at->diffInHours(now());
    }

    /**
     * Accessor para obtener etiqueta legible de prioridad
     *
     * @return string
     */
    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_CRITICAL => '🔴 CRÍTICO',
            self::PRIORITY_URGENT => '🟠 URGENTE',
            self::PRIORITY_HIGH => '🟡 ALTO',
            self::PRIORITY_MEDIUM => '🔵 MEDIO',
            self::PRIORITY_LOW => '⚪ BAJO',
            default => $this->priority,
        };
    }

    /**
     * Mutator para generar ticket_number automáticamente
     *
     * @param string|null $value
     * @return void
     */
    public function setTicketNumberAttribute(?string $value): void
    {
        if (empty($value)) {
            $year = now()->year;
            $lastTicket = static::whereYear('created_at', $year)
                ->orderBy('id', 'desc')
                ->first();
            
            $sequence = $lastTicket ? (int) substr($lastTicket->ticket_number, -6) + 1 : 1;
            $value = sprintf('PQRS-%d-%06d', $year, $sequence);
        }

        $this->attributes['ticket_number'] = $value;
    }

    /*
    |--------------------------------------------------------------------------
    | 6. MÉTODOS DE NEGOCIO
    |--------------------------------------------------------------------------
    */

    /**
     * Crear un nuevo PQRS con cálculo automático de prioridad y SLA
     *
     * @param array $data Datos del PQRS
     * @return static
     */
    public static function createPQRS(array $data): static
    {
        // Determinar prioridad automática si no se proporciona
        if (empty($data['priority'])) {
            $data['priority'] = static::determinePriority($data['category'], $data);
        }

        // Calcular SLA deadline
        $slaHours = self::SLA_HOURS[$data['priority']] ?? 72;
        $slaDeadline = now()->addHours($slaHours);

        // Crear el PQRS
        $pqrs = static::create([
            'ticket_number' => null, // Se genera automáticamente
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'status' => self::STATUS_SUBMITTED,
            'channel' => $data['channel'] ?? self::CHANNEL_IN_APP,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'attachments' => $data['attachments'] ?? [],
            'submitted_at' => now(),
            'sla_deadline' => $slaDeadline,
            'metadata' => $data['metadata'] ?? [],
        ]);

        // Intentar auto-asignación si es posible
        $pqrs->attemptAutoAssignment();

        // Notificar según prioridad
        $pqrs->sendNotifications();

        return $pqrs;
    }

    /**
     * Asignar PQRS a un agente
     *
     * @param int $agentId ID del agente
     * @return bool
     */
    public function assignAgent(int $agentId): bool
    {
        $this->update([
            'assigned_to' => $agentId,
            'assigned_at' => now(),
            'status' => $this->status === self::STATUS_SUBMITTED ? self::STATUS_ASSIGNED : $this->status,
        ]);

        // TODO: Notificar al agente

        return true;
    }

    /**
     * Actualizar estado del PQRS
     *
     * @param string $newStatus Nuevo estado
     * @param string|null $notes Notas opcionales
     * @return bool
     */
    public function updateStatus(string $newStatus, ?string $notes = null): bool
    {
        if (!in_array($newStatus, self::STATUSES)) {
            throw new \InvalidArgumentException("Estado inválido: {$newStatus}");
        }

        $updates = ['status' => $newStatus];

        // Actualizar timestamps específicos
        if ($newStatus === self::STATUS_RESOLVED && !$this->resolved_at) {
            $updates['resolved_at'] = now();
        } elseif ($newStatus === self::STATUS_CLOSED && !$this->closed_at) {
            $updates['closed_at'] = now();
        }

        // Agregar notas a metadata
        if ($notes) {
            $metadata = $this->metadata ?? [];
            $metadata['status_history'][] = [
                'status' => $newStatus,
                'notes' => $notes,
                'updated_at' => now()->toIso8601String(),
            ];
            $updates['metadata'] = $metadata;
        }

        $this->update($updates);
        $this->clearCache();

        return true;
    }

    /**
     * Escalar el PQRS
     *
     * @param string $reason Razón del escalamiento
     * @return bool
     */
    public function escalate(string $reason): bool
    {
        // Incrementar prioridad si no es máxima
        $newPriority = $this->priority;
        if ($this->priority === self::PRIORITY_LOW) {
            $newPriority = self::PRIORITY_MEDIUM;
        } elseif ($this->priority === self::PRIORITY_MEDIUM) {
            $newPriority = self::PRIORITY_HIGH;
        } elseif ($this->priority === self::PRIORITY_HIGH) {
            $newPriority = self::PRIORITY_URGENT;
        } elseif ($this->priority === self::PRIORITY_URGENT) {
            $newPriority = self::PRIORITY_CRITICAL;
        }

        // Recalcular SLA deadline con nueva prioridad
        $newSlaHours = self::SLA_HOURS[$newPriority];
        $newDeadline = $this->submitted_at->addHours($newSlaHours);

        $this->update([
            'status' => self::STATUS_ESCALATED,
            'priority' => $newPriority,
            'sla_deadline' => $newDeadline,
            'metadata' => array_merge($this->metadata ?? [], [
                'escalation_reason' => $reason,
                'escalated_at' => now()->toIso8601String(),
                'previous_priority' => $this->priority,
            ]),
        ]);

        // TODO: Notificar a supervisores

        return true;
    }

    /**
     * Reabrir PQRS cerrado
     *
     * @param string $reason Razón de reapertura
     * @return bool
     */
    public function reopen(string $reason): bool
    {
        if (!in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED])) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_REOPENED,
            'resolved_at' => null,
            'closed_at' => null,
            'metadata' => array_merge($this->metadata ?? [], [
                'reopen_reason' => $reason,
                'reopened_at' => now()->toIso8601String(),
            ]),
        ]);

        return true;
    }

    /**
     * Incrementar contador de respuestas
     *
     * @return void
     */
    public function incrementResponseCount(): void
    {
        $this->increment('response_count');
    }

    /**
     * Registrar rating de satisfacción
     *
     * @param int $rating Rating de 1-5
     * @return bool
     */
    public function rateSatisfaction(int $rating): bool
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException("Rating debe estar entre 1 y 5");
        }

        if (!in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED])) {
            return false;
        }

        $this->update(['satisfaction_rating' => $rating]);

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | 7. MÉTODOS DE SLA
    |--------------------------------------------------------------------------
    */

    /**
     * Calcular SLA deadline basado en prioridad
     *
     * @return \Carbon\Carbon
     */
    public function calculateSLA(): \Carbon\Carbon
    {
        $slaHours = self::SLA_HOURS[$this->priority] ?? 72;
        return $this->submitted_at->addHours($slaHours);
    }

    /**
     * Obtener porcentaje de tiempo SLA consumido
     *
     * @return float
     */
    public function getSLAPercentage(): float
    {
        $totalHours = self::SLA_HOURS[$this->priority] ?? 72;
        $elapsedHours = $this->submitted_at->diffInHours(now());

        return min(($elapsedHours / $totalHours) * 100, 100);
    }

    /**
     * Determinar prioridad automática basada en categoría y contexto
     *
     * @param string $category
     * @param array $data
     * @return string
     */
    protected static function determinePriority(string $category, array $data): string
    {
        // Prioridad base por categoría
        $basePriority = self::CATEGORY_DEFAULT_PRIORITY[$category] ?? self::PRIORITY_MEDIUM;

        // Escalar si el usuario tiene múltiples PQRS abiertos
        if (!empty($data['user_id'])) {
            $openTickets = static::byUser($data['user_id'])
                ->whereNotIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED])
                ->count();

            if ($openTickets >= 3 && $basePriority !== self::PRIORITY_CRITICAL) {
                // Escalar un nivel
                $priorityLevels = array_flip(self::PRIORITIES);
                $currentLevel = $priorityLevels[$basePriority];
                $newLevel = min($currentLevel + 1, count(self::PRIORITIES) - 1);
                $basePriority = self::PRIORITIES[$newLevel];
            }
        }

        return $basePriority;
    }

    /**
     * Intentar asignación automática a agente disponible
     *
     * @return bool
     */
    protected function attemptAutoAssignment(): bool
    {
        // TODO: Implementar lógica de auto-asignación con balanceo de carga
        // Por ahora retorna false (asignación manual)
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | 8. MÉTODOS DE ANALYTICS
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener historial de PQRS de un usuario
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUserPQRSHistory(int $userId)
    {
        return static::byUser($userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtener estadísticas de PQRS
     *
     * @param array $filters Filtros opcionales
     * @return array
     */
    public static function getPQRSStatistics(array $filters = []): array
    {
        $query = static::query();

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        $total = $query->count();
        $byType = (clone $query)->selectRaw('type, COUNT(*) as count')->groupBy('type')->pluck('count', 'type');
        $byCategory = (clone $query)->selectRaw('category, COUNT(*) as count')->groupBy('category')->pluck('count', 'category');
        $byStatus = (clone $query)->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $byPriority = (clone $query)->selectRaw('priority, COUNT(*) as count')->groupBy('priority')->pluck('count', 'priority');

        $averageResolutionHours = static::whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, submitted_at, resolved_at)) as avg_hours')
            ->value('avg_hours');

        $slaCompliance = static::whereNotNull('resolved_at')
            ->whereRaw('resolved_at <= sla_deadline')
            ->count();

        $totalResolved = static::whereNotNull('resolved_at')->count();
        $slaComplianceRate = $totalResolved > 0 ? ($slaCompliance / $totalResolved) * 100 : 0;

        return [
            'total' => $total,
            'by_type' => $byType,
            'by_category' => $byCategory,
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'average_resolution_hours' => round($averageResolutionHours ?? 0, 2),
            'sla_compliance_rate' => round($slaComplianceRate, 2),
            'pending' => static::pending()->count(),
            'breaching_sla' => static::breachingSLA()->count(),
            'unassigned' => static::unassigned()->count(),
        ];
    }

    /**
     * Obtener métricas de performance de un agente
     *
     * @param int $agentId
     * @return array
     */
    public static function getAgentPerformance(int $agentId): array
    {
        $assigned = static::assignedTo($agentId)->count();
        $resolved = static::assignedTo($agentId)->where('status', self::STATUS_RESOLVED)->count();
        
        $avgResolutionHours = static::assignedTo($agentId)
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, assigned_at, resolved_at)) as avg_hours')
            ->value('avg_hours');

        $slaCompliance = static::assignedTo($agentId)
            ->whereNotNull('resolved_at')
            ->whereRaw('resolved_at <= sla_deadline')
            ->count();

        $avgSatisfaction = static::assignedTo($agentId)
            ->whereNotNull('satisfaction_rating')
            ->avg('satisfaction_rating');

        return [
            'tickets_assigned' => $assigned,
            'tickets_resolved' => $resolved,
            'resolution_rate' => $assigned > 0 ? round(($resolved / $assigned) * 100, 2) : 0,
            'average_resolution_hours' => round($avgResolutionHours ?? 0, 2),
            'sla_compliance_count' => $slaCompliance,
            'average_satisfaction' => round($avgSatisfaction ?? 0, 2),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 9. EVENT DISPATCHING
    |--------------------------------------------------------------------------
    */

    /**
     * Enviar notificaciones según prioridad
     *
     * @return void
     */
    protected function sendNotifications(): void
    {
        // TODO: Implementar notificaciones diferenciadas
        // CRITICAL/URGENT: Slack + Email + SMS
        // HIGH: Slack + Email
        // MEDIUM: Email
        // LOW: Email diferido
    }

    /**
     * Boot del modelo para eventos
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::created(function (PQRS $pqrs) {
            $pqrs->clearCache();
        });

        static::updated(function (PQRS $pqrs) {
            $pqrs->clearCache();
        });

        static::deleted(function (PQRS $pqrs) {
            $pqrs->clearCache();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | 10. CACHE MANAGEMENT
    |--------------------------------------------------------------------------
    */

    /**
     * Limpiar cache relacionado con el PQRS
     *
     * @return void
     */
    protected function clearCache(): void
    {
        Cache::tags([
            'pqrs',
            "user.{$this->user_id}",
            $this->assigned_to ? "agent.{$this->assigned_to}" : null,
            'moderation',
        ])->flush();
    }

    /**
     * Obtener cache key para el PQRS
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return "pqrs.{$this->id}";
    }
}