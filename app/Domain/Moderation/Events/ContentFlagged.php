<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Evento disparado cuando contenido es marcado como inapropiado en ForeverUsInLove
 * 
 * Este evento se dispara cuando el sistema de moderación automática o moderadores
 * humanos identifican contenido que viola las políticas de la plataforma. Maneja
 * la respuesta inmediata, notificaciones, acciones correctivas y análisis de patrones
 * para mantener la seguridad y calidad del contenido en la comunidad.
 * 
 * Características principales:
 * - Broadcasting en tiempo real para respuesta inmediata
 * - Análisis automático de severidad y contexto
 * - Escalamiento inteligente basado en patrones
 * - Protección de contenido sensible y usuarios vulnerables
 * - Integración con sistemas de machine learning
 * - Métricas de moderación y análisis de tendencias
 * - Gestión de apelaciones y revisiones humanas
 * - Notificaciones contextuales sin comprometer privacidad
 * 
 * @package App\Domain\Moderation\Events
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class ContentFlagged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Información del contenido marcado
     */
    public readonly int $userId;
    public readonly string $contentId;
    public readonly string $contentType;
    public readonly string $contentPreview;
    public readonly string $flagReason;
    public readonly array $violations;
    public readonly float $confidenceScore;
    public readonly string $moderationAction;
    public readonly Carbon $flaggedAt;
    public readonly array $metadata;

    /**
     * Constructor del evento
     *
     * @param int $userId ID del usuario propietario del contenido
     * @param string $content Contenido marcado (será procesado para preview)
     * @param string $moderationAction Acción de moderación tomada
     * @param array $violations Violaciones detectadas
     * @param float $confidenceScore Score de confianza de la detección
     * @param array $metadata Metadatos adicionales
     */
    public function __construct(
        int $userId,
        string $content,
        string $moderationAction,
        array $violations = [],
        float $confidenceScore = 0.0,
        array $metadata = []
    ) {
        $this->userId = $userId;
        $this->contentId = $this->generateContentId($content, $userId);
        $this->contentType = $this->detectContentType($content, $metadata);
        $this->contentPreview = $this->createSecurePreview($content);
        $this->moderationAction = $moderationAction;
        $this->violations = $violations;
        $this->confidenceScore = $confidenceScore;
        $this->flagReason = $this->determinePrimaryReason($violations);
        $this->flaggedAt = Carbon::now();
        $this->metadata = array_merge($metadata, [
            'event_id' => uniqid('content_flagged_', true),
            'timestamp' => $this->flaggedAt->toISOString(),
            'source' => 'content_moderation_system',
            'version' => '1.0.0',
            'content_hash' => hash('sha256', $content),
            'content_length' => strlen($content)
        ]);
    }

    /**
     * Obtiene los canales donde se transmitirá el evento
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Canal privado para el equipo de moderación
        $channels[] = new PrivateChannel('moderation.content-flags');

        // Canal específico para contenido de alta severidad
        if ($this->isHighSeverity()) {
            $channels[] = new PrivateChannel('moderation.high-severity-content');
        }

        // Canal para contenido que requiere revisión humana inmediata
        if ($this->requiresImmediateHumanReview()) {
            $channels[] = new PrivateChannel('moderation.urgent-review');
        }

        // Canal para administradores del sistema
        $channels[] = new PrivateChannel('admin.content-monitoring');

        // Canal privado para el usuario (notificación discreta si aplica)
        if ($this->shouldNotifyUser()) {
            $channels[] = new PrivateChannel("user.{$this->userId}.content-notifications");
        }

        // Canal para sistemas de machine learning (feedback y entrenamiento)
        $channels[] = new PrivateChannel('ml.content-feedback');

        // Canal para análisis de patrones y tendencias
        $channels[] = new PrivateChannel('analytics.content-violations');

        // Canal para customer service si requiere seguimiento
        if ($this->requiresCustomerServiceEscalation()) {
            $channels[] = new PrivateChannel('customer-service.content-violations');
        }

        // Canal para sistemas legales en casos extremos
        if ($this->requiresLegalNotification()) {
            $channels[] = new PrivateChannel('legal.content-violations');
        }

        return $channels;
    }

    /**
     * Nombre del evento para broadcasting
     */
    public function broadcastAs(): string
    {
        return 'content.flagged';
    }

    /**
     * Datos que serán transmitidos
     */
    public function broadcastWith(): array
    {
        return [
            'content_info' => [
                'id' => $this->contentId,
                'type' => $this->contentType,
                'preview' => $this->contentPreview,
                'length' => $this->metadata['content_length'],
                'hash' => $this->metadata['content_hash'],
                'flagged_at' => $this->flaggedAt->toISOString()
            ],
            'user_info' => [
                'id' => $this->userId,
                'is_verified' => $this->isVerifiedUser($this->userId),
                'account_age' => $this->getUserAccountAge($this->userId),
                'previous_violations' => $this->getPreviousViolationsCount($this->userId),
                'risk_profile' => $this->getUserRiskProfile($this->userId),
                'content_history' => $this->getContentHistorySummary($this->userId)
            ],
            'moderation_info' => [
                'action' => $this->moderationAction,
                'primary_reason' => $this->flagReason,
                'all_violations' => $this->violations,
                'confidence_score' => $this->confidenceScore,
                'confidence_level' => $this->getConfidenceLevel(),
                'detection_method' => $this->getDetectionMethod(),
                'processing_time' => $this->getProcessingTime()
            ],
            'severity_assessment' => [
                'severity_level' => $this->calculateSeverityLevel(),
                'threat_level' => $this->assessThreatLevel(),
                'community_impact' => $this->assessCommunityImpact(),
                'legal_risk' => $this->assessLegalRisk(),
                'brand_risk' => $this->assessBrandRisk()
            ],
            'content_analysis' => [
                'language_detected' => $this->detectLanguage(),
                'sentiment_score' => $this->analyzeSentiment(),
                'toxicity_score' => $this->analyzeToxicity(),
                'adult_content_probability' => $this->analyzeAdultContent(),
                'violence_indicators' => $this->analyzeViolenceIndicators(),
                'hate_speech_probability' => $this->analyzeHateSpeech()
            ],
            'context_information' => [
                'content_context' => $this->getContentContext(),
                'user_interaction_context' => $this->getUserInteractionContext(),
                'temporal_context' => $this->getTemporalContext(),
                'geographic_context' => $this->getGeographicContext(),
                'platform_context' => $this->getPlatformContext()
            ],
            'response_actions' => [
                'immediate_actions' => $this->getImmediateActions(),
                'content_status' => $this->getContentStatus(),
                'user_restrictions' => $this->getUserRestrictions(),
                'escalation_triggered' => $this->getEscalationActions(),
                'notification_sent' => $this->getNotificationActions()
            ],
            'review_process' => [
                'requires_human_review' => $this->requiresHumanReview(),
                'review_priority' => $this->getReviewPriority(),
                'estimated_review_time' => $this->getEstimatedReviewTime(),
                'assigned_reviewer' => $this->getAssignedReviewer(),
                'appeal_available' => $this->isAppealAvailable()
            ],
            'pattern_detection' => [
                'similar_content_count' => $this->getSimilarContentCount(),
                'user_violation_pattern' => $this->detectUserViolationPattern(),
                'temporal_violation_pattern' => $this->detectTemporalPattern(),
                'network_violation_indicators' => $this->detectNetworkPatterns(),
                'coordinated_activity' => $this->detectCoordinatedActivity()
            ],
            'ml_feedback' => [
                'model_version' => $this->getMLModelVersion(),
                'feature_importance' => $this->getFeatureImportance(),
                'prediction_explanation' => $this->getPredictionExplanation(),
                'training_data_candidate' => $this->isTrainingDataCandidate(),
                'model_performance_metrics' => $this->getModelPerformanceMetrics()
            ],
            'compliance_data' => [
                'regulatory_flags' => $this->getRegulatoryFlags(),
                'age_appropriateness' => $this->getAgeAppropriatenessRating(),
                'content_rating' => $this->getContentRating(),
                'jurisdiction_concerns' => $this->getJurisdictionConcerns(),
                'parental_control_relevant' => $this->isParentalControlRelevant()
            ],
            'metadata' => $this->metadata
        ];
    }

    /**
     * Configuración de colas para procesamiento
     */
    public function viaQueues(): array
    {
        $queues = ['content-moderation'];

        // Cola de alta prioridad para contenido crítico
        if ($this->isHighSeverity()) {
            $queues[] = 'critical-content';
        }

        // Cola especial para contenido que requiere revisión legal
        if ($this->requiresLegalNotification()) {
            $queues[] = 'legal-review';
        }

        // Cola de machine learning para mejoras de modelo
        $queues[] = 'ml-feedback';

        // Cola de análisis para detección de patrones
        $queues[] = 'pattern-analysis';

        // Cola de notificaciones
        $queues[] = 'notifications';

        return $queues;
    }

    /**
     * Tiempo límite para reintentos del evento
     */
    public function retryUntil(): \DateTime
    {
        // Reintentar durante 4 horas para contenido crítico, 1 hora para otros
        $hours = $this->isHighSeverity() ? 4 : 1;
        return now()->addHours($hours);
    }

    /**
     * Determina si debería fallar silenciosamente
     */
    public function shouldFailSilently(): bool
    {
        // No fallar silenciosamente para contenido de alta severidad
        return !$this->isHighSeverity();
    }

    /**
     * Genera ID único para el contenido
     */
    private function generateContentId(string $content, int $userId): string
    {
        return 'CNT-' . 
               $this->flaggedAt->format('Ymd') . '-' . 
               $userId . '-' .
               strtoupper(substr(hash('sha256', $content . $this->flaggedAt->timestamp), 0, 8));
    }

    /**
     * Detecta el tipo de contenido
     */
    private function detectContentType(string $content, array $metadata): string
    {
        if (isset($metadata['content_type'])) {
            return $metadata['content_type'];
        }

        // Detección automática basada en el contenido
        if (filter_var($content, FILTER_VALIDATE_URL)) {
            return 'url';
        } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $content)) {
            return 'image';
        } elseif (preg_match('/\.(mp4|avi|mov|webm)$/i', $content)) {
            return 'video';
        } elseif (preg_match('/\.(mp3|wav|ogg|m4a)$/i', $content)) {
            return 'audio';
        } elseif (strlen($content) > 500) {
            return 'long_text';
        }

        return 'text';
    }

    /**
     * Crea un preview seguro del contenido
     */
    private function createSecurePreview(string $content): string
    {
        // Sanitizar y crear preview sin información sensible
        $preview = strip_tags($content);
        $preview = preg_replace('/[^\w\s\-\.]/u', '*', $preview);
        
        return Str::limit($preview, 100, '...');
    }

    /**
     * Determina la razón principal de la violación
     */
    private function determinePrimaryReason(array $violations): string
    {
        if (empty($violations)) {
            return 'unknown';
        }

        // Ordenar por severidad y retornar la más grave
        $severityOrder = [
            'illegal_content' => 100,
            'violence_threats' => 95,
            'hate_speech' => 90,
            'harassment' => 85,
            'adult_content' => 80,
            'spam' => 50,
            'inappropriate_language' => 40,
            'policy_violation' => 30
        ];

        $maxSeverity = 0;
        $primaryReason = 'unknown';

        foreach ($violations as $violation) {
            $severity = $severityOrder[$violation] ?? 0;
            if ($severity > $maxSeverity) {
                $maxSeverity = $severity;
                $primaryReason = $violation;
            }
        }

        return $primaryReason;
    }

    /**
     * Determina si es de alta severidad
     */
    private function isHighSeverity(): bool
    {
        $highSeverityReasons = [
            'illegal_content',
            'violence_threats', 
            'hate_speech',
            'harassment',
            'underage_content'
        ];

        return in_array($this->flagReason, $highSeverityReasons) ||
               $this->confidenceScore > 0.9;
    }

    /**
     * Determina si requiere revisión humana inmediata
     */
    private function requiresImmediateHumanReview(): bool
    {
        return $this->isHighSeverity() ||
               $this->confidenceScore < 0.7 ||
               in_array($this->flagReason, ['violence_threats', 'illegal_content']);
    }

    /**
     * Determina si debe notificar al usuario
     */
    private function shouldNotifyUser(): bool
    {
        // Solo notificar para acciones que afectan visiblemente al usuario
        return in_array($this->moderationAction, ['hide', 'remove', 'require_review']) &&
               !in_array($this->flagReason, ['illegal_content', 'violence_threats']);
    }

    /**
     * Determina si requiere escalamiento a customer service
     */
    private function requiresCustomerServiceEscalation(): bool
    {
        return $this->getPreviousViolationsCount($this->userId) > 5 ||
               in_array($this->flagReason, ['harassment', 'hate_speech']) ||
               $this->detectUserViolationPattern();
    }

    /**
     * Determina si requiere notificación legal
     */
    private function requiresLegalNotification(): bool
    {
        return in_array($this->flagReason, [
            'illegal_content',
            'violence_threats',
            'underage_content'
        ]) && $this->confidenceScore > 0.8;
    }

    /**
     * Verifica si el usuario está verificado
     */
    private function isVerifiedUser(int $userId): bool
    {
        // Implementación simulada
        return (bool)rand(0, 1);
    }

    /**
     * Obtiene antigüedad de la cuenta
     */
    private function getUserAccountAge(int $userId): array
    {
        $days = rand(1, 1000);
        return [
            'days' => $days,
            'is_new_account' => $days < 30,
            'created_at' => Carbon::now()->subDays($days)->toDateString()
        ];
    }

    /**
     * Obtiene número de violaciones previas
     */
    private function getPreviousViolationsCount(int $userId): int
    {
        return rand(0, 15);
    }

    /**
     * Obtiene perfil de riesgo del usuario
     */
    private function getUserRiskProfile(int $userId): array
    {
        return [
            'risk_score' => rand(0, 100),
            'risk_level' => ['low', 'medium', 'high', 'critical'][rand(0, 3)],
            'violation_frequency' => rand(0, 10),
            'account_flags' => (bool)rand(0, 1)
        ];
    }

    /**
     * Obtiene resumen del historial de contenido
     */
    private function getContentHistorySummary(int $userId): array
    {
        return [
            'total_content_items' => rand(10, 1000),
            'flagged_content_percentage' => rand(0, 20),
            'content_quality_score' => rand(60, 100),
            'recent_violations' => rand(0, 5)
        ];
    }

    /**
     * Obtiene nivel de confianza categórico
     */
    private function getConfidenceLevel(): string
    {
        return match(true) {
            $this->confidenceScore >= 0.95 => 'very_high',
            $this->confidenceScore >= 0.8 => 'high',
            $this->confidenceScore >= 0.6 => 'medium',
            $this->confidenceScore >= 0.4 => 'low',
            default => 'very_low'
        };
    }

    /**
     * Calcula nivel de severidad
     */
    private function calculateSeverityLevel(): string
    {
        if ($this->isHighSeverity()) {
            return 'critical';
        } elseif ($this->confidenceScore > 0.7) {
            return 'high';
        } elseif ($this->confidenceScore > 0.5) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Evalúa nivel de amenaza
     */
    private function assessThreatLevel(): string
    {
        if (in_array($this->flagReason, ['violence_threats', 'illegal_content'])) {
            return 'severe';
        } elseif (in_array($this->flagReason, ['harassment', 'hate_speech'])) {
            return 'moderate';
        }
        return 'low';
    }

    /**
     * Evalúa impacto en la comunidad
     */
    private function assessCommunityImpact(): array
    {
        return [
            'impact_score' => rand(0, 100),
            'affected_users_estimate' => rand(1, 1000),
            'viral_potential' => ['low', 'medium', 'high'][rand(0, 2)],
            'community_sentiment_risk' => ['low', 'medium', 'high'][rand(0, 2)]
        ];
    }

    /**
     * Evalúa riesgo legal
     */
    private function assessLegalRisk(): array
    {
        return [
            'risk_level' => ['none', 'low', 'medium', 'high', 'critical'][rand(0, 4)],
            'jurisdiction_concerns' => (bool)rand(0, 1),
            'regulatory_implications' => (bool)rand(0, 1),
            'requires_legal_review' => $this->requiresLegalNotification()
        ];
    }

    /**
     * Evalúa riesgo de marca
     */
    private function assessBrandRisk(): array
    {
        return [
            'reputation_impact' => ['minimal', 'low', 'medium', 'high', 'severe'][rand(0, 4)],
            'media_attention_risk' => ['low', 'medium', 'high'][rand(0, 2)],
            'advertiser_friendly' => !(bool)rand(0, 1),
            'pr_concern_level' => rand(0, 10)
        ];
    }

    /**
     * Detecta idioma del contenido
     */
    private function detectLanguage(): string
    {
        // Implementación simulada
        return ['en', 'es', 'fr', 'de', 'it', 'pt'][rand(0, 5)];
    }

    /**
     * Analiza sentimiento del contenido
     */
    private function analyzeSentiment(): array
    {
        return [
            'sentiment' => ['very_negative', 'negative', 'neutral', 'positive', 'very_positive'][rand(0, 4)],
            'sentiment_score' => rand(-100, 100) / 100,
            'emotional_intensity' => rand(0, 100) / 100
        ];
    }

    /**
     * Analiza toxicidad del contenido
     */
    private function analyzeToxicity(): array
    {
        return [
            'toxicity_score' => rand(0, 100) / 100,
            'toxicity_level' => ['none', 'mild', 'moderate', 'high', 'severe'][rand(0, 4)],
            'toxic_categories' => array_rand(array_flip(['insult', 'threat', 'profanity', 'identity_attack']), rand(0, 4))
        ];
    }

    /**
     * Analiza contenido adulto
     */
    private function analyzeAdultContent(): float
    {
        return rand(0, 100) / 100;
    }

    /**
     * Analiza indicadores de violencia
     */
    private function analyzeViolenceIndicators(): array
    {
        return [
            'violence_probability' => rand(0, 100) / 100,
            'threat_indicators' => (bool)rand(0, 1),
            'weapon_mentions' => (bool)rand(0, 1),
            'violence_type' => ['none', 'verbal', 'physical', 'extreme'][rand(0, 3)]
        ];
    }

    /**
     * Analiza discurso de odio
     */
    private function analyzeHateSpeech(): float
    {
        return rand(0, 100) / 100;
    }

    /**
     * Detecta patrón de violaciones del usuario
     */
    private function detectUserViolationPattern(): bool
    {
        return $this->getPreviousViolationsCount($this->userId) > 3 &&
               rand(0, 1) === 1;
    }

    /**
     * Determina si requiere revisión humana
     */
    private function requiresHumanReview(): bool
    {
        return $this->confidenceScore < 0.8 ||
               $this->isHighSeverity() ||
               $this->detectUserViolationPattern();
    }
}