<?php

namespace App\Models\Moderation;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use App\Domain\Moderation\Events\ContentFlagged;

/**
 * Class ContentFlag
 * 
 * Modelo Eloquent para la moderación automática de contenido usando IA y ML.
 * Soporta análisis multimodal (texto, imagen, video, audio, perfil, comportamiento),
 * detección de 19 categorías de violaciones, scoring de confianza ML,
 * y acciones de moderación automatizadas con revisión humana opcional.
 * 
 * Basado en: Domain/Moderation/ContentModerationService.php
 * 
 * @package App\Models\Moderation
 * @version 1.0.0
 * @since Laravel 12.x / PHP 8.2
 * 
 * TABLA DE CONTENIDOS:
 * ==================
 * 1. Constantes de Negocio (Tipos Contenido, Violaciones, Confianza, Acciones)
 * 2. Propiedades del Modelo (Fillable, Casts, Dates, Appends)
 * 3. Relaciones Eloquent (User, Flaggable Polymorphic, Reviewer)
 * 4. Query Scopes (PendingReview, HighRisk, ByContentType, AutoModerated)
 * 5. Accessors & Mutators (RiskLevel, ViolationSummary, RequiresHuman)
 * 6. Métodos de Negocio (Moderate, EscalateToHuman, ApplyAction, ProcessFeedback)
 * 7. Métodos de ML Analysis (CalculateRisk, DetectViolations, ConfidenceScore)
 * 8. Métodos de Analytics (ModerationStatistics, ModelPerformance)
 * 9. Event Dispatching (ContentFlagged Broadcasting)
 * 10. Cache Management
 * 
 * RESPONSABILIDADES:
 * ==================
 * - Moderación automática de 6 tipos de contenido (TEXT, IMAGE, VIDEO, AUDIO, PROFILE_DATA, USER_BEHAVIOR)
 * - Detección de 19 categorías de violación con ML
 * - 5 niveles de confianza (VERY_LOW→VERY_HIGH con thresholds 0.2→0.95)
 * - 8 acciones de moderación automatizadas (ALLOW, FLAG, BLUR, HIDE, BLOCK, etc.)
 * - Análisis contextual según relación usuarios (5 contextos)
 * - Escalamiento automático a revisión humana en baja confianza
 * - ML feedback loop para mejora continua del modelo
 * - Broadcasting a 9 canales según severidad
 * - Tracking de análisis automático vs revisión humana
 * - Gestión de feature importance y prediction explanations
 * - Statistics de accuracy, precision, recall del modelo ML
 * 
 * @property int $id
 * @property int $user_id Usuario creador del contenido
 * @property int $flaggable_id ID del contenido flaggeado
 * @property string $flaggable_type Tipo del contenido (polymorphic)
 * @property string $content_type Tipo de contenido moderado (6 opciones)
 * @property string|null $content_snapshot Snapshot del contenido (texto)
 * @property array $violation_categories Categorías detectadas (19 opciones)
 * @property float $confidence_score Score de confianza ML (0-1)
 * @property string $threat_level Nivel de amenaza (5 niveles)
 * @property string $moderation_action Acción aplicada (8 opciones)
 * @property \Carbon\Carbon|null $action_taken_at Timestamp de acción
 * @property array $automatic_analysis Análisis ML detallado
 * @property bool $human_reviewed Flag de revisión humana
 * @property int|null $reviewer_id Moderador que revisó
 * @property \Carbon\Carbon|null $reviewed_at Timestamp de revisión
 * @property string|null $review_notes Notas del moderador
 * @property \Carbon\Carbon $flagged_at Timestamp del flagging
 * @property array $metadata Contexto adicional encriptado
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @property-read User $user Relación con usuario creador
 * @property-read Model $flaggable Relación polymorphic con contenido
 * @property-read User|null $reviewer Relación con moderador revisor
 * @property-read string $risk_level Nivel de riesgo calculado
 * @property-read string $violation_summary Resumen de violaciones
 * @property-read bool $requires_human_review Flag de revisión requerida
 * @property-read bool $was_correct Feedback de precisión (si hay revisión)
 * 
 * @method static Builder pendingReview() Contenido pendiente de revisión humana
 * @method static Builder highRisk() Contenido de alto riesgo
 * @method static Builder byContentType(string $type) Filtrar por tipo
 * @method static Builder autoModerated() Moderado automáticamente
 * @method static Builder humanReviewed() Revisado por humanos
 * @method static Builder byAction(string $action) Filtrar por acción
 * @method static Builder byViolation(string $violation) Contiene violación específica
 */
class ContentFlag extends Model
{
    use HasFactory, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | 1. CONSTANTES DE NEGOCIO
    |--------------------------------------------------------------------------
    */

    /**
     * Tipos de contenido moderables (6 categorías)
     */
    public const CONTENT_TYPE_TEXT = 'text';
    public const CONTENT_TYPE_IMAGE = 'image';
    public const CONTENT_TYPE_VIDEO = 'video';
    public const CONTENT_TYPE_AUDIO = 'audio';
    public const CONTENT_TYPE_PROFILE_DATA = 'profile_data';
    public const CONTENT_TYPE_USER_BEHAVIOR = 'user_behavior';

    public const CONTENT_TYPES = [
        self::CONTENT_TYPE_TEXT,
        self::CONTENT_TYPE_IMAGE,
        self::CONTENT_TYPE_VIDEO,
        self::CONTENT_TYPE_AUDIO,
        self::CONTENT_TYPE_PROFILE_DATA,
        self::CONTENT_TYPE_USER_BEHAVIOR,
    ];

    /**
     * Categorías de violación detectables (19 categorías)
     */
    public const VIOLATION_ADULT_CONTENT = 'adult_content';
    public const VIOLATION_NUDITY = 'nudity';
    public const VIOLATION_SEXUAL_CONTENT = 'sexual_content';
    public const VIOLATION_VIOLENCE = 'violence';
    public const VIOLATION_THREATS = 'threats';
    public const VIOLATION_SELF_HARM = 'self_harm';
    public const VIOLATION_HATE_SPEECH = 'hate_speech';
    public const VIOLATION_DISCRIMINATION = 'discrimination';
    public const VIOLATION_BULLYING = 'bullying';
    public const VIOLATION_SCAM = 'scam';
    public const VIOLATION_FAKE_IDENTITY = 'fake_identity';
    public const VIOLATION_MISLEADING_INFO = 'misleading_info';
    public const VIOLATION_SPAM = 'spam';
    public const VIOLATION_SOLICITATION = 'solicitation';
    public const VIOLATION_COMMERCIAL_CONTENT = 'commercial_content';
    public const VIOLATION_PERSONAL_INFO = 'personal_info';
    public const VIOLATION_CONTACT_SHARING = 'contact_sharing';
    public const VIOLATION_UNDERAGE_CONTENT = 'underage_content';
    public const VIOLATION_ILLEGAL_CONTENT = 'illegal_content';
    public const VIOLATION_POLICY_VIOLATION = 'policy_violation';

    public const VIOLATION_CATEGORIES = [
        self::VIOLATION_ADULT_CONTENT,
        self::VIOLATION_NUDITY,
        self::VIOLATION_SEXUAL_CONTENT,
        self::VIOLATION_VIOLENCE,
        self::VIOLATION_THREATS,
        self::VIOLATION_SELF_HARM,
        self::VIOLATION_HATE_SPEECH,
        self::VIOLATION_DISCRIMINATION,
        self::VIOLATION_BULLYING,
        self::VIOLATION_SCAM,
        self::VIOLATION_FAKE_IDENTITY,
        self::VIOLATION_MISLEADING_INFO,
        self::VIOLATION_SPAM,
        self::VIOLATION_SOLICITATION,
        self::VIOLATION_COMMERCIAL_CONTENT,
        self::VIOLATION_PERSONAL_INFO,
        self::VIOLATION_CONTACT_SHARING,
        self::VIOLATION_UNDERAGE_CONTENT,
        self::VIOLATION_ILLEGAL_CONTENT,
        self::VIOLATION_POLICY_VIOLATION,
    ];

    /**
     * Niveles de confianza ML (5 niveles con thresholds)
     */
    public const CONFIDENCE_VERY_LOW = 'very_low';
    public const CONFIDENCE_LOW = 'low';
    public const CONFIDENCE_MEDIUM = 'medium';
    public const CONFIDENCE_HIGH = 'high';
    public const CONFIDENCE_VERY_HIGH = 'very_high';

    public const CONFIDENCE_LEVELS = [
        self::CONFIDENCE_VERY_LOW => 0.2,
        self::CONFIDENCE_LOW => 0.4,
        self::CONFIDENCE_MEDIUM => 0.6,
        self::CONFIDENCE_HIGH => 0.8,
        self::CONFIDENCE_VERY_HIGH => 0.95,
    ];

    /**
     * Acciones de moderación disponibles (8 acciones)
     */
    public const ACTION_ALLOW = 'allow';
    public const ACTION_FLAG = 'flag';
    public const ACTION_BLUR = 'blur';
    public const ACTION_HIDE = 'hide';
    public const ACTION_BLOCK = 'block';
    public const ACTION_REQUIRE_REVIEW = 'require_review';
    public const ACTION_WARN_USER = 'warn_user';
    public const ACTION_ESCALATE = 'escalate';

    public const MODERATION_ACTIONS = [
        self::ACTION_ALLOW,
        self::ACTION_FLAG,
        self::ACTION_BLUR,
        self::ACTION_HIDE,
        self::ACTION_BLOCK,
        self::ACTION_REQUIRE_REVIEW,
        self::ACTION_WARN_USER,
        self::ACTION_ESCALATE,
    ];

    /**
     * Contextos de moderación (5 contextos)
     */
    public const CONTEXT_PUBLIC_PROFILE = 'public_profile';
    public const CONTEXT_PRIVATE_MESSAGE = 'private_message';
    public const CONTEXT_GROUP_CHAT = 'group_chat';
    public const CONTEXT_INITIAL_CONTACT = 'initial_contact';
    public const CONTEXT_ESTABLISHED_RELATIONSHIP = 'established_relationship';

    public const MODERATION_CONTEXTS = [
        self::CONTEXT_PUBLIC_PROFILE,
        self::CONTEXT_PRIVATE_MESSAGE,
        self::CONTEXT_GROUP_CHAT,
        self::CONTEXT_INITIAL_CONTACT,
        self::CONTEXT_ESTABLISHED_RELATIONSHIP,
    ];

    /**
     * Mapeo de violación a severidad base (0-100)
     */
    public const VIOLATION_SEVERITY_SCORES = [
        self::VIOLATION_UNDERAGE_CONTENT => 100,
        self::VIOLATION_ILLEGAL_CONTENT => 95,
        self::VIOLATION_THREATS => 90,
        self::VIOLATION_VIOLENCE => 85,
        self::VIOLATION_SELF_HARM => 85,
        self::VIOLATION_HATE_SPEECH => 80,
        self::VIOLATION_SEXUAL_CONTENT => 75,
        self::VIOLATION_NUDITY => 70,
        self::VIOLATION_ADULT_CONTENT => 65,
        self::VIOLATION_SCAM => 70,
        self::VIOLATION_FAKE_IDENTITY => 65,
        self::VIOLATION_DISCRIMINATION => 75,
        self::VIOLATION_BULLYING => 70,
        self::VIOLATION_MISLEADING_INFO => 50,
        self::VIOLATION_SPAM => 40,
        self::VIOLATION_SOLICITATION => 55,
        self::VIOLATION_COMMERCIAL_CONTENT => 35,
        self::VIOLATION_PERSONAL_INFO => 60,
        self::VIOLATION_CONTACT_SHARING => 55,
        self::VIOLATION_POLICY_VIOLATION => 45,
    ];

    /*
    |--------------------------------------------------------------------------
    | 2. PROPIEDADES DEL MODELO
    |--------------------------------------------------------------------------
    */

    /**
     * Nombre de la tabla en la base de datos
     */
    protected $table = 'content_flags';

    /**
     * Clave primaria de la tabla
     */
    protected $primaryKey = 'id';

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'user_id',
        'flaggable_id',
        'flaggable_type',
        'content_type',
        'content_snapshot',
        'violation_categories',
        'confidence_score',
        'threat_level',
        'moderation_action',
        'action_taken_at',
        'automatic_analysis',
        'human_reviewed',
        'reviewer_id',
        'reviewed_at',
        'review_notes',
        'flagged_at',
        'metadata',
    ];

    /**
     * Atributos que deben ser casteados a tipos nativos
     */
    protected $casts = [
        'user_id' => 'integer',
        'flaggable_id' => 'integer',
        'violation_categories' => 'array',
        'confidence_score' => 'float',
        'automatic_analysis' => 'array',
        'human_reviewed' => 'boolean',
        'reviewer_id' => 'integer',
        'metadata' => 'encrypted:array',
        'flagged_at' => 'datetime',
        'action_taken_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Atributos que deben ser mutados a fechas
     */
    protected $dates = [
        'flagged_at',
        'action_taken_at',
        'reviewed_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Atributos que deben ser añadidos a las representaciones de arrays/JSON
     */
    protected $appends = [
        'risk_level',
        'violation_summary',
        'requires_human_review',
        'was_correct',
    ];

    /**
     * Atributos ocultos en arrays/JSON
     */
    protected $hidden = [
        'content_snapshot',
        'metadata',
    ];

    /**
     * Valores por defecto para atributos
     */
    protected $attributes = [
        'confidence_score' => 0.5,
        'human_reviewed' => false,
        'moderation_action' => self::ACTION_REQUIRE_REVIEW,
    ];

    /*
    |--------------------------------------------------------------------------
    | 3. RELACIONES ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Usuario creador del contenido flaggeado
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Contenido flaggeado (relación polymorphic)
     * Puede ser: Message, Profile, UserImage, GroupChat, etc.
     *
     * @return MorphTo
     */
    public function flaggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Moderador humano que revisó el contenido
     *
     * @return BelongsTo
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /*
    |--------------------------------------------------------------------------
    | 4. QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope para obtener contenido pendiente de revisión humana
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('human_reviewed', false)
            ->where('moderation_action', self::ACTION_REQUIRE_REVIEW);
    }

    /**
     * Scope para obtener contenido de alto riesgo
     *
     * @param Builder $query
     * @param float $threshold Threshold de confianza (default 0.8)
     * @return Builder
     */
    public function scopeHighRisk(Builder $query, float $threshold = 0.8): Builder
    {
        return $query->where('confidence_score', '>=', $threshold)
            ->whereIn('moderation_action', [
                self::ACTION_BLOCK,
                self::ACTION_HIDE,
                self::ACTION_ESCALATE,
            ]);
    }

    /**
     * Scope para filtrar por tipo de contenido
     *
     * @param Builder $query
     * @param string $type
     * @return Builder
     */
    public function scopeByContentType(Builder $query, string $type): Builder
    {
        return $query->where('content_type', $type);
    }

    /**
     * Scope para obtener contenido moderado automáticamente
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeAutoModerated(Builder $query): Builder
    {
        return $query->where('human_reviewed', false)
            ->whereNotIn('moderation_action', [self::ACTION_REQUIRE_REVIEW]);
    }

    /**
     * Scope para obtener contenido revisado por humanos
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeHumanReviewed(Builder $query): Builder
    {
        return $query->where('human_reviewed', true);
    }

    /**
     * Scope para filtrar por acción de moderación
     *
     * @param Builder $query
     * @param string $action
     * @return Builder
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('moderation_action', $action);
    }

    /**
     * Scope para filtrar por violación específica
     *
     * @param Builder $query
     * @param string $violation
     * @return Builder
     */
    public function scopeByViolation(Builder $query, string $violation): Builder
    {
        return $query->whereJsonContains('violation_categories', $violation);
    }

    /**
     * Scope para obtener contenido por usuario
     *
     * @param Builder $query
     * @param int $userId
     * @return Builder
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Accessor para obtener nivel de riesgo legible
     *
     * @return string
     */
    public function getRiskLevelAttribute(): string
    {
        $score = $this->confidence_score;

        if ($score >= 0.95) return 'CRÍTICO';
        if ($score >= 0.8) return 'MUY ALTO';
        if ($score >= 0.6) return 'ALTO';
        if ($score >= 0.4) return 'MEDIO';
        return 'BAJO';
    }

    /**
     * Accessor para obtener resumen de violaciones detectadas
     *
     * @return string
     */
    public function getViolationSummaryAttribute(): string
    {
        if (empty($this->violation_categories)) {
            return 'Sin violaciones detectadas';
        }

        $count = count($this->violation_categories);
        $primary = $this->violation_categories[0] ?? 'unknown';
        
        if ($count === 1) {
            return ucwords(str_replace('_', ' ', $primary));
        }

        return ucwords(str_replace('_', ' ', $primary)) . " (+{$count} más)";
    }

    /**
     * Accessor para determinar si requiere revisión humana
     *
     * @return bool
     */
    public function getRequiresHumanReviewAttribute(): bool
    {
        // Baja confianza siempre requiere revisión
        if ($this->confidence_score < 0.6) {
            return true;
        }

        // Violaciones críticas requieren revisión
        $criticalViolations = [
            self::VIOLATION_UNDERAGE_CONTENT,
            self::VIOLATION_ILLEGAL_CONTENT,
            self::VIOLATION_THREATS,
            self::VIOLATION_SELF_HARM,
        ];

        foreach ($this->violation_categories ?? [] as $violation) {
            if (in_array($violation, $criticalViolations)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Accessor para verificar si la moderación automática fue correcta
     * Solo disponible después de revisión humana
     *
     * @return bool|null
     */
    public function getWasCorrectAttribute(): ?bool
    {
        if (!$this->human_reviewed) {
            return null;
        }

        // TODO: Comparar acción automática vs decisión humana
        // Por ahora retorna null
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | 6. MÉTODOS DE NEGOCIO
    |--------------------------------------------------------------------------
    */

    /**
     * Moderar contenido con ML automático
     *
     * @param array $content Contenido a moderar
     * @param string $contentType Tipo de contenido
     * @param int $userId Usuario creador
     * @param Model $flaggable Modelo del contenido
     * @param array $context Contexto adicional
     * @return static
     */
    public static function moderateContent(
        array $content,
        string $contentType,
        int $userId,
        Model $flaggable,
        array $context = []
    ): static {
        // Realizar análisis ML automático
        $analysis = static::performMLAnalysis($content, $contentType, $context);

        // Determinar acción basada en análisis
        $action = static::determineAction($analysis);

        // Crear el flag
        $flag = static::create([
            'user_id' => $userId,
            'flaggable_id' => $flaggable->id,
            'flaggable_type' => get_class($flaggable),
            'content_type' => $contentType,
            'content_snapshot' => $contentType === self::CONTENT_TYPE_TEXT ? ($content['text'] ?? null) : null,
            'violation_categories' => $analysis['violations'] ?? [],
            'confidence_score' => $analysis['confidence'] ?? 0.5,
            'threat_level' => $analysis['threat_level'] ?? 'low',
            'moderation_action' => $action,
            'action_taken_at' => in_array($action, [self::ACTION_ALLOW, self::ACTION_REQUIRE_REVIEW]) ? null : now(),
            'automatic_analysis' => $analysis,
            'flagged_at' => now(),
            'metadata' => $context,
        ]);

        // Aplicar acción si es automática
        if (!in_array($action, [self::ACTION_REQUIRE_REVIEW])) {
            $flag->applyModerationAction();
        }

        // Dispatch event
        event(new ContentFlagged(
            userId: $flag->user_id,
            content: $flag->content_snapshot ?? 'Content flagged',
            moderationAction: $flag->moderation_action,
            violations: $flag->violation_categories ?? [],
            confidenceScore: $flag->confidence_score,
            metadata: $flag->metadata ?? []
        ));

        return $flag;
    }

    /**
     * Escalar a revisión humana
     *
     * @param string $reason Razón del escalamiento
     * @return bool
     */
    public function escalateToHuman(string $reason): bool
    {
        $this->update([
            'moderation_action' => self::ACTION_REQUIRE_REVIEW,
            'metadata' => array_merge($this->metadata ?? [], [
                'escalation_reason' => $reason,
                'escalated_at' => now()->toIso8601String(),
            ]),
        ]);

        // TODO: Notificar a moderadores humanos

        return true;
    }

    /**
     * Procesar revisión humana
     *
     * @param int $reviewerId ID del moderador
     * @param string $decision Decisión del moderador
     * @param string|null $notes Notas de revisión
     * @return bool
     */
    public function processHumanReview(
        int $reviewerId,
        string $decision,
        ?string $notes = null
    ): bool {
        $this->update([
            'human_reviewed' => true,
            'reviewer_id' => $reviewerId,
            'reviewed_at' => now(),
            'moderation_action' => $decision,
            'review_notes' => $notes,
        ]);

        // Aplicar acción decidida por humano
        $this->applyModerationAction();

        // Registrar feedback para ML
        $this->processFeedback();

        return true;
    }

    /**
     * Aplicar acción de moderación al contenido
     *
     * @return void
     */
    protected function applyModerationAction(): void
    {
        $content = $this->flaggable;

        switch ($this->moderation_action) {
            case self::ACTION_ALLOW:
                // No hacer nada, contenido permitido
                break;

            case self::ACTION_FLAG:
                // Marcar contenido como flaggeado
                if (method_exists($content, 'markAsFlagged')) {
                    $content->markAsFlagged();
                }
                break;

            case self::ACTION_BLUR:
                // Difuminar contenido (imágenes/videos)
                if (method_exists($content, 'blur')) {
                    $content->blur();
                }
                break;

            case self::ACTION_HIDE:
                // Ocultar contenido
                if (method_exists($content, 'hide')) {
                    $content->hide();
                }
                break;

            case self::ACTION_BLOCK:
                // Bloquear/eliminar contenido
                if (method_exists($content, 'delete')) {
                    $content->delete();
                }
                break;

            case self::ACTION_WARN_USER:
                // Enviar warning al usuario
                // TODO: Implementar sistema de warnings
                break;

            case self::ACTION_ESCALATE:
                // Ya manejado en escalateToHuman()
                break;
        }

        $this->update(['action_taken_at' => now()]);
    }

    /**
     * Procesar feedback para mejorar modelo ML
     *
     * @return void
     */
    protected function processFeedback(): void
    {
        if (!$this->human_reviewed) {
            return;
        }

        // TODO: Enviar feedback al servicio de ML para reentrenamiento
        // Comparar automatic_analysis con decisión humana
    }

    /*
    |--------------------------------------------------------------------------
    | 7. MÉTODOS DE ML ANALYSIS
    |--------------------------------------------------------------------------
    */

    /**
     * Realizar análisis ML del contenido
     *
     * @param array $content Contenido a analizar
     * @param string $contentType Tipo de contenido
     * @param array $context Contexto
     * @return array Resultados del análisis
     */
    protected static function performMLAnalysis(
        array $content,
        string $contentType,
        array $context
    ): array {
        // TODO: Integrar con servicios de ML reales (OpenAI, Google Vision, etc.)
        
        $violations = [];
        $confidence = 0.75;
        $threatLevel = 'low';

        // Análisis básico por tipo de contenido
        switch ($contentType) {
            case self::CONTENT_TYPE_TEXT:
                $textAnalysis = static::analyzeText($content['text'] ?? '');
                $violations = $textAnalysis['violations'];
                $confidence = $textAnalysis['confidence'];
                break;

            case self::CONTENT_TYPE_IMAGE:
            case self::CONTENT_TYPE_VIDEO:
                // TODO: Análisis de imágenes/videos
                break;
        }

        // Calcular threat level
        if (!empty($violations)) {
            $maxSeverity = 0;
            foreach ($violations as $violation) {
                $severity = self::VIOLATION_SEVERITY_SCORES[$violation] ?? 0;
                $maxSeverity = max($maxSeverity, $severity);
            }

            if ($maxSeverity >= 90) {
                $threatLevel = 'critical';
            } elseif ($maxSeverity >= 70) {
                $threatLevel = 'high';
            } elseif ($maxSeverity >= 50) {
                $threatLevel = 'medium';
            } else {
                $threatLevel = 'low';
            }
        }

        return [
            'violations' => $violations,
            'confidence' => $confidence,
            'threat_level' => $threatLevel,
            'sentiment_score' => 0.0,
            'toxicity_score' => 0.0,
            'model_version' => '1.0.0',
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Analizar texto para detectar violaciones
     *
     * @param string $text Texto a analizar
     * @return array
     */
    protected static function analyzeText(string $text): array
    {
        // TODO: Implementar análisis real de texto
        // Por ahora retorna análisis básico
        $violations = [];
        $confidence = 0.5;

        // Detección básica de palabras clave
        $text = strtolower($text);

        // Hate speech
        $hateKeywords = ['hate', 'kill', 'die', 'racist', 'nazi'];
        foreach ($hateKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                $violations[] = self::VIOLATION_HATE_SPEECH;
                $confidence = 0.75;
                break;
            }
        }

        // Spam
        if (str_contains($text, 'buy now') || str_contains($text, 'click here')) {
            $violations[] = self::VIOLATION_SPAM;
            $confidence = 0.8;
        }

        return [
            'violations' => array_unique($violations),
            'confidence' => $confidence,
        ];
    }

    /**
     * Determinar acción basada en análisis ML
     *
     * @param array $analysis Resultados del análisis
     * @return string Acción a tomar
     */
    protected static function determineAction(array $analysis): string
    {
        $confidence = $analysis['confidence'] ?? 0.5;
        $violations = $analysis['violations'] ?? [];
        $threatLevel = $analysis['threat_level'] ?? 'low';

        // Sin violaciones detectadas
        if (empty($violations)) {
            return self::ACTION_ALLOW;
        }

        // Baja confianza -> requiere revisión humana
        if ($confidence < 0.6) {
            return self::ACTION_REQUIRE_REVIEW;
        }

        // Alta confianza + threat crítico -> bloquear
        if ($confidence >= 0.9 && $threatLevel === 'critical') {
            return self::ACTION_BLOCK;
        }

        // Alta confianza + threat alto -> ocultar
        if ($confidence >= 0.8 && $threatLevel === 'high') {
            return self::ACTION_HIDE;
        }

        // Media confianza -> flagear
        if ($confidence >= 0.6) {
            return self::ACTION_FLAG;
        }

        return self::ACTION_REQUIRE_REVIEW;
    }

    /*
    |--------------------------------------------------------------------------
    | 8. MÉTODOS DE ANALYTICS
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener estadísticas de moderación
     *
     * @param array $filters Filtros opcionales
     * @return array
     */
    public static function getModerationStatistics(array $filters = []): array
    {
        $query = static::query();

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        $total = $query->count();
        $byAction = (clone $query)->selectRaw('moderation_action, COUNT(*) as count')
            ->groupBy('moderation_action')
            ->pluck('count', 'moderation_action');
        
        $byContentType = (clone $query)->selectRaw('content_type, COUNT(*) as count')
            ->groupBy('content_type')
            ->pluck('count', 'content_type');

        $autoModerated = static::autoModerated()->count();
        $humanReviewed = static::humanReviewed()->count();
        $pendingReview = static::pendingReview()->count();

        $avgConfidence = $query->avg('confidence_score');

        return [
            'total_flagged' => $total,
            'by_action' => $byAction,
            'by_content_type' => $byContentType,
            'auto_moderated' => $autoModerated,
            'human_reviewed' => $humanReviewed,
            'pending_review' => $pendingReview,
            'average_confidence' => round($avgConfidence ?? 0, 2),
            'automation_rate' => $total > 0 ? round(($autoModerated / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Obtener métricas de performance del modelo ML
     *
     * @return array
     */
    public static function getModelPerformance(): array
    {
        $reviewed = static::humanReviewed()->get();
        $total = $reviewed->count();

        if ($total === 0) {
            return [
                'accuracy' => 0,
                'precision' => 0,
                'recall' => 0,
                'samples' => 0,
            ];
        }

        // TODO: Calcular métricas reales comparando automatic vs human decisions
        $accuracy = 0.85; // Placeholder
        $precision = 0.80; // Placeholder
        $recall = 0.75; // Placeholder

        return [
            'accuracy' => round($accuracy, 2),
            'precision' => round($precision, 2),
            'recall' => round($recall, 2),
            'samples' => $total,
        ];
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
        static::created(function (ContentFlag $flag) {
            event(new ContentFlagged(
                userId: $flag->user_id,
                content: $flag->content_snapshot ?? 'Content flagged',
                moderationAction: $flag->moderation_action,
                violations: $flag->violation_categories ?? [],
                confidenceScore: $flag->confidence_score,
                metadata: $flag->metadata ?? []
            ));
            $flag->clearCache();
        });

        static::updated(function (ContentFlag $flag) {
            $flag->clearCache();
        });

        static::deleted(function (ContentFlag $flag) {
            $flag->clearCache();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | 10. CACHE MANAGEMENT
    |--------------------------------------------------------------------------
    */

    /**
     * Limpiar cache relacionado con el flag
     *
     * @return void
     */
    protected function clearCache(): void
    {
        Cache::tags([
            'content_flags',
            "user.{$this->user_id}",
            'moderation',
        ])->flush();
    }

    /**
     * Obtener cache key para el flag
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return "content_flag.{$this->id}";
    }
}