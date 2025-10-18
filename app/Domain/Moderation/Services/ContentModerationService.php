<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Services;

use App\Domain\Moderation\Events\ContentFlagged;
use App\Domain\Moderation\Repositories\ReportRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Chat\Repositories\MessageRepositoryInterface;
use App\Exceptions\ContentModerationException;
use App\Exceptions\InvalidContentTypeException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Servicio avanzado de moderación automática de contenido para ForeverUsInLove
 * 
 * Este servicio implementa un sistema de moderación integral que utiliza múltiples
 * tecnologías para detectar, analizar y actuar sobre contenido inapropiado de manera
 * automática y en tiempo real.
 * 
 * Características principales:
 * - Análisis de texto con NLP y machine learning
 * - Detección de contenido visual inapropiado
 * - Sistema de puntuación de riesgo dinámico
 * - Filtros adaptativos que aprenden de la comunidad
 * - Moderación contextual según la relación entre usuarios
 * - Sistema de whitelist/blacklist personalizable
 * - Detección de patrones de comportamiento sospechoso
 * - Integración con APIs de moderación externas (Google Cloud AI, AWS, etc.)
 * - Sistema de aprendizaje continuo con feedback humano
 * - Análisis de sentimientos y contexto emocional
 * 
 * @package App\Domain\Moderation\Services
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class ContentModerationService
{
    /**
     * Tipos de contenido que pueden ser moderados
     */
    public const CONTENT_TYPES = [
        'TEXT' => 'text',                   // Mensajes, biografías, comentarios
        'IMAGE' => 'image',                 // Fotos de perfil, imágenes enviadas
        'VIDEO' => 'video',                 // Videos compartidos
        'AUDIO' => 'audio',                 // Mensajes de voz
        'PROFILE_DATA' => 'profile_data',   // Información del perfil
        'USER_BEHAVIOR' => 'user_behavior'  // Patrones de comportamiento
    ];

    /**
     * Categorías de contenido inapropiado
     */
    public const VIOLATION_CATEGORIES = [
        // Contenido explícito
        'ADULT_CONTENT' => 'adult_content',
        'NUDITY' => 'nudity',
        'SEXUAL_CONTENT' => 'sexual_content',
        
        // Violencia y amenazas
        'VIOLENCE' => 'violence',
        'THREATS' => 'threats',
        'SELF_HARM' => 'self_harm',
        
        // Odio y discriminación
        'HATE_SPEECH' => 'hate_speech',
        'DISCRIMINATION' => 'discrimination',
        'BULLYING' => 'bullying',
        
        // Engaño y estafa
        'SCAM' => 'scam',
        'FAKE_IDENTITY' => 'fake_identity',
        'MISLEADING_INFO' => 'misleading_info',
        
        // Spam y contenido no deseado
        'SPAM' => 'spam',
        'SOLICITATION' => 'solicitation',
        'COMMERCIAL_CONTENT' => 'commercial_content',
        
        // Información personal
        'PERSONAL_INFO' => 'personal_info',
        'CONTACT_SHARING' => 'contact_sharing',
        
        // Otros
        'UNDERAGE_CONTENT' => 'underage_content',
        'ILLEGAL_CONTENT' => 'illegal_content',
        'POLICY_VIOLATION' => 'policy_violation'
    ];

    /**
     * Niveles de confianza de detección
     */
    public const CONFIDENCE_LEVELS = [
        'VERY_LOW' => 0.2,
        'LOW' => 0.4,
        'MEDIUM' => 0.6,
        'HIGH' => 0.8,
        'VERY_HIGH' => 0.95
    ];

    /**
     * Acciones automáticas posibles
     */
    public const MODERATION_ACTIONS = [
        'ALLOW' => 'allow',                 // Permitir contenido
        'FLAG' => 'flag',                   // Marcar para revisión
        'BLUR' => 'blur',                   // Difuminar contenido
        'HIDE' => 'hide',                   // Ocultar contenido
        'BLOCK' => 'block',                 // Bloquear completamente
        'REQUIRE_REVIEW' => 'require_review', // Requerir revisión humana
        'WARN_USER' => 'warn_user',         // Advertir al usuario
        'ESCALATE' => 'escalate'            // Escalar a moderación humana
    ];

    /**
     * Contextos de moderación
     */
    public const MODERATION_CONTEXTS = [
        'PUBLIC_PROFILE' => 'public_profile',
        'PRIVATE_MESSAGE' => 'private_message',
        'GROUP_CHAT' => 'group_chat',
        'INITIAL_CONTACT' => 'initial_contact',
        'ESTABLISHED_RELATIONSHIP' => 'established_relationship'
    ];

    /**
     * Constructor del servicio de moderación
     */
    public function __construct(
        private readonly ReportRepositoryInterface $reportRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly MessageRepositoryInterface $messageRepository
    ) {}

    /**
     * Modera contenido de texto (mensajes, biografías, etc.)
     *
     * @param string $content Contenido a moderar
     * @param int $userId ID del usuario que envía el contenido
     * @param string $context Contexto de la moderación
     * @param array $metadata Metadatos adicionales
     * @return array Resultado de la moderación
     * @throws ContentModerationException
     */
    public function moderateText(
        string $content,
        int $userId,
        string $context = self::MODERATION_CONTEXTS['PUBLIC_PROFILE'],
        array $metadata = []
    ): array {
        try {
            Log::info('Iniciando moderación de texto', [
                'user_id' => $userId,
                'content_length' => strlen($content),
                'context' => $context
            ]);

            // Análisis preliminar básico
            $preliminaryResult = $this->performPreliminaryTextAnalysis($content);
            
            if ($preliminaryResult['is_safe']) {
                return $this->createModerationResult('ALLOW', $preliminaryResult);
            }

            // Análisis detallado con múltiples engines
            $analysisResults = [
                'profanity_check' => $this->analyzeProfanity($content),
                'hate_speech_detection' => $this->analyzeHateSpeech($content),
                'scam_detection' => $this->analyzeScamContent($content),
                'personal_info_detection' => $this->analyzePersonalInfo($content),
                'sentiment_analysis' => $this->analyzeSentiment($content),
                'contextual_analysis' => $this->analyzeContext($content, $context, $userId),
                'ml_classification' => $this->performMLClassification($content),
                'pattern_matching' => $this->performPatternMatching($content)
            ];

            // Calcular puntuación de riesgo agregada
            $riskScore = $this->calculateAggregatedRiskScore($analysisResults, $context);

            // Determinar acción basada en el score y contexto
            $action = $this->determineAction($riskScore, $context, $userId, $analysisResults);

            // Crear resultado de moderación
            $result = $this->createModerationResult($action, [
                'risk_score' => $riskScore,
                'analysis_results' => $analysisResults,
                'detected_violations' => $this->extractViolations($analysisResults),
                'confidence_level' => $this->calculateConfidenceLevel($analysisResults),
                'processing_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? 0)
            ]);

            // Ejecutar acción si es necesaria
            if ($action !== self::MODERATION_ACTIONS['ALLOW']) {
                $this->executeAction($action, $content, $userId, $result, $metadata);
            }

            // Aprender del resultado para mejorar futuras moderaciones
            $this->feedbackLearning($content, $result, $userId);

            Log::info('Moderación de texto completada', [
                'user_id' => $userId,
                'action' => $action,
                'risk_score' => $riskScore
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Error en moderación de texto', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw new ContentModerationException('Error al moderar contenido: ' . $e->getMessage());
        }
    }

    /**
     * Modera contenido visual (imágenes, videos)
     *
     * @param string $mediaUrl URL del archivo multimedia
     * @param string $mediaType Tipo de multimedia
     * @param int $userId ID del usuario
     * @param string $context Contexto de la moderación
     * @param array $metadata Metadatos adicionales
     * @return array Resultado de la moderación
     */
    public function moderateVisualContent(
        string $mediaUrl,
        string $mediaType,
        int $userId,
        string $context = self::MODERATION_CONTEXTS['PUBLIC_PROFILE'],
        array $metadata = []
    ): array {
        try {
            Log::info('Iniciando moderación de contenido visual', [
                'user_id' => $userId,
                'media_type' => $mediaType,
                'context' => $context
            ]);

            // Análisis con múltiples providers de IA
            $analysisResults = [
                'google_vision' => $this->analyzeWithGoogleVision($mediaUrl),
                'aws_rekognition' => $this->analyzeWithAWSRekognition($mediaUrl),
                'azure_computer_vision' => $this->analyzeWithAzureCV($mediaUrl),
                'custom_ml_model' => $this->analyzeWithCustomModel($mediaUrl, $mediaType),
                'nudity_detection' => $this->detectNudity($mediaUrl),
                'face_analysis' => $this->analyzeFaces($mediaUrl),
                'object_detection' => $this->detectInappropriateObjects($mediaUrl),
                'text_extraction' => $this->extractAndAnalyzeText($mediaUrl)
            ];

            // Calcular score de riesgo para contenido visual
            $riskScore = $this->calculateVisualRiskScore($analysisResults, $context);

            // Determinar acción
            $action = $this->determineVisualAction($riskScore, $analysisResults, $context);

            // Crear resultado
            $result = $this->createModerationResult($action, [
                'risk_score' => $riskScore,
                'analysis_results' => $analysisResults,
                'detected_violations' => $this->extractVisualViolations($analysisResults),
                'confidence_level' => $this->calculateVisualConfidence($analysisResults)
            ]);

            // Ejecutar acción
            if ($action !== self::MODERATION_ACTIONS['ALLOW']) {
                $this->executeVisualAction($action, $mediaUrl, $userId, $result, $metadata);
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Error en moderación visual', [
                'user_id' => $userId,
                'media_url' => $mediaUrl,
                'error' => $e->getMessage()
            ]);
            throw new ContentModerationException('Error al moderar contenido visual: ' . $e->getMessage());
        }
    }

    /**
     * Analiza el comportamiento de un usuario para detectar patrones sospechosos
     *
     * @param int $userId ID del usuario
     * @param array $behaviorData Datos de comportamiento
     * @param string $timeframe Marco temporal para el análisis
     * @return array Resultado del análisis de comportamiento
     */
    public function analyzeBehaviorPatterns(
        int $userId,
        array $behaviorData,
        string $timeframe = '24h'
    ): array {
        try {
            Log::info('Analizando patrones de comportamiento', [
                'user_id' => $userId,
                'timeframe' => $timeframe
            ]);

            $cacheKey = "behavior_analysis_{$userId}_{$timeframe}";
            
            return Cache::remember($cacheKey, 1800, function () use ($userId, $behaviorData, $timeframe) {
                // Obtener historial del usuario
                $userHistory = $this->getUserBehaviorHistory($userId, $timeframe);
                
                // Análisis de patrones múltiples
                $patternAnalysis = [
                    'messaging_patterns' => $this->analyzeMessagingPatterns($userHistory),
                    'profile_changes' => $this->analyzeProfileChanges($userHistory),
                    'interaction_patterns' => $this->analyzeInteractionPatterns($userHistory),
                    'timing_patterns' => $this->analyzeTimingPatterns($userHistory),
                    'velocity_analysis' => $this->analyzeVelocity($userHistory),
                    'anomaly_detection' => $this->detectAnomalies($userHistory, $behaviorData),
                    'reputation_analysis' => $this->analyzeReputation($userId),
                    'network_analysis' => $this->analyzeNetwork($userId)
                ];

                // Calcular score de riesgo comportamental
                $behaviorRiskScore = $this->calculateBehaviorRiskScore($patternAnalysis);

                // Determinar alertas y acciones
                $alerts = $this->generateBehaviorAlerts($patternAnalysis, $behaviorRiskScore);
                $recommendedActions = $this->recommendBehaviorActions($behaviorRiskScore, $alerts);

                return [
                    'user_id' => $userId,
                    'timeframe' => $timeframe,
                    'risk_score' => $behaviorRiskScore,
                    'risk_level' => $this->categorizeBehaviorRisk($behaviorRiskScore),
                    'pattern_analysis' => $patternAnalysis,
                    'alerts' => $alerts,
                    'recommended_actions' => $recommendedActions,
                    'confidence_score' => $this->calculateBehaviorConfidence($patternAnalysis),
                    'analysis_timestamp' => Carbon::now()->toISOString()
                ];
            });

        } catch (Exception $e) {
            Log::error('Error en análisis de comportamiento', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw new ContentModerationException('Error al analizar comportamiento: ' . $e->getMessage());
        }
    }

    /**
     * Procesa feedback humano para mejorar los modelos de moderación
     *
     * @param string $contentId ID del contenido
     * @param string $moderationAction Acción tomada automáticamente
     * @param string $humanFeedback Feedback del moderador humano
     * @param array $additionalData Datos adicionales
     * @return array Resultado del procesamiento del feedback
     */
    public function processFeedback(
        string $contentId,
        string $moderationAction,
        string $humanFeedback,
        array $additionalData = []
    ): array {
        try {
            // Registrar el feedback
            $feedbackData = [
                'content_id' => $contentId,
                'auto_action' => $moderationAction,
                'human_feedback' => $humanFeedback,
                'feedback_type' => $this->determineFeedbackType($moderationAction, $humanFeedback),
                'accuracy_score' => $this->calculateAccuracy($moderationAction, $humanFeedback),
                'additional_data' => $additionalData,
                'timestamp' => Carbon::now()
            ];

            $this->reportRepository->storeModerationFeedback($feedbackData);

            // Actualizar modelos de aprendizaje
            $this->updateLearningModels($feedbackData);

            // Ajustar parámetros de confianza
            $this->adjustConfidenceParameters($feedbackData);

            // Generar métricas de mejora
            $improvementMetrics = $this->calculateImprovementMetrics($contentId);

            Log::info('Feedback de moderación procesado', [
                'content_id' => $contentId,
                'feedback_type' => $feedbackData['feedback_type'],
                'accuracy_score' => $feedbackData['accuracy_score']
            ]);

            return [
                'success' => true,
                'feedback_processed' => true,
                'feedback_type' => $feedbackData['feedback_type'],
                'accuracy_impact' => $improvementMetrics['accuracy_impact'],
                'model_updates' => $improvementMetrics['model_updates'],
                'confidence_adjustments' => $improvementMetrics['confidence_adjustments']
            ];

        } catch (Exception $e) {
            Log::error('Error procesando feedback', [
                'content_id' => $contentId,
                'error' => $e->getMessage()
            ]);
            throw new ContentModerationException('Error al procesar feedback: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene estadísticas de rendimiento de la moderación automática
     *
     * @param array $filters Filtros para las estadísticas
     * @param string $period Período de análisis
     * @return array Estadísticas de moderación
     */
    public function getModerationStatistics(array $filters = [], string $period = 'week'): array
    {
        $cacheKey = "moderation_stats_" . md5(serialize($filters)) . "_{$period}";
        
        return Cache::remember($cacheKey, 3600, function () use ($filters, $period) {
            $stats = $this->reportRepository->getModerationStatistics($filters, $period);
            
            return [
                'period' => $period,
                'total_content_moderated' => $stats['total_moderated'] ?? 0,
                'content_by_type' => $stats['by_type'] ?? [],
                'actions_taken' => $stats['actions'] ?? [],
                'accuracy_metrics' => [
                    'overall_accuracy' => $this->calculateOverallAccuracy($stats),
                    'false_positive_rate' => $this->calculateFalsePositiveRate($stats),
                    'false_negative_rate' => $this->calculateFalseNegativeRate($stats),
                    'precision' => $this->calculatePrecision($stats),
                    'recall' => $this->calculateRecall($stats)
                ],
                'performance_metrics' => [
                    'avg_processing_time' => $stats['avg_processing_time'] ?? 0,
                    'throughput' => $stats['throughput'] ?? 0,
                    'escalation_rate' => $this->calculateEscalationRate($stats)
                ],
                'violation_categories' => $stats['violations'] ?? [],
                'trending_violations' => $this->identifyTrendingViolations($stats),
                'model_performance' => $this->analyzeModelPerformance($stats),
                'improvement_suggestions' => $this->generateImprovementSuggestions($stats),
                'generated_at' => Carbon::now()->toISOString()
            ];
        });
    }

    /**
     * Actualiza las reglas de moderación dinámicamente
     *
     * @param array $newRules Nuevas reglas a aplicar
     * @param string $ruleType Tipo de reglas ('text', 'visual', 'behavior')
     * @return array Resultado de la actualización
     */
    public function updateModerationRules(array $newRules, string $ruleType): array
    {
        try {
            // Validar reglas
            $this->validateModerationRules($newRules, $ruleType);

            // Aplicar reglas
            $currentRules = $this->getCurrentRules($ruleType);
            $mergedRules = $this->mergeRules($currentRules, $newRules);

            // Guardar reglas actualizadas
            $this->reportRepository->updateModerationRules($ruleType, $mergedRules);

            // Limpiar caches relevantes
            $this->clearModerationCache($ruleType);

            // Probar reglas con contenido de muestra
            $testResults = $this->testNewRules($mergedRules, $ruleType);

            Log::info('Reglas de moderación actualizadas', [
                'rule_type' => $ruleType,
                'rules_count' => count($newRules),
                'test_results' => $testResults
            ]);

            return [
                'success' => true,
                'rule_type' => $ruleType,
                'rules_updated' => count($newRules),
                'total_rules' => count($mergedRules),
                'test_results' => $testResults,
                'updated_at' => Carbon::now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('Error actualizando reglas de moderación', [
                'rule_type' => $ruleType,
                'error' => $e->getMessage()
            ]);
            throw new ContentModerationException('Error al actualizar reglas: ' . $e->getMessage());
        }
    }

    /**
     * Realiza análisis preliminar básico de texto
     */
    private function performPreliminaryTextAnalysis(string $content): array
    {
        $content = trim($content);
        $contentLower = strtolower($content);
        
        // Verificaciones básicas rápidas
        $checks = [
            'empty_content' => empty($content),
            'too_short' => strlen($content) < 3,
            'too_long' => strlen($content) > 10000,
            'excessive_caps' => $this->hasExcessiveCaps($content),
            'excessive_repetition' => $this->hasExcessiveRepetition($content),
            'suspicious_patterns' => $this->hasSuspiciousPatterns($contentLower),
            'known_safe_phrases' => $this->containsKnownSafePhrases($contentLower)
        ];

        $isSafe = !$checks['excessive_caps'] && 
                  !$checks['excessive_repetition'] && 
                  !$checks['suspicious_patterns'] &&
                  !$checks['empty_content'] &&
                  !$checks['too_short'] &&
                  !$checks['too_long'];

        return [
            'is_safe' => $isSafe,
            'checks' => $checks,
            'content_length' => strlen($content),
            'word_count' => str_word_count($content)
        ];
    }

    /**
     * Analiza profanidad en el texto
     */
    private function analyzeProfanity(string $content): array
    {
        $profanityWords = $this->getProfanityWordList();
        $contentLower = strtolower($content);
        
        $detectedWords = [];
        $severity = 0;
        
        foreach ($profanityWords as $word => $weight) {
            if (str_contains($contentLower, $word)) {
                $detectedWords[] = $word;
                $severity += $weight;
            }
        }
        
        return [
            'detected_words' => $detectedWords,
            'word_count' => count($detectedWords),
            'severity_score' => min($severity, 100),
            'contains_profanity' => !empty($detectedWords)
        ];
    }

    /**
     * Detecta discurso de odio
     */
    private function analyzeHateSpeech(string $content): array
    {
        $hatePatterns = $this->getHateSpeechPatterns();
        $contentLower = strtolower($content);
        
        $matches = [];
        $confidence = 0;
        
        foreach ($hatePatterns as $pattern => $weight) {
            if (preg_match($pattern, $contentLower)) {
                $matches[] = $pattern;
                $confidence += $weight;
            }
        }
        
        return [
            'detected_patterns' => $matches,
            'confidence_score' => min($confidence, 100),
            'is_hate_speech' => $confidence > 50,
            'category' => $this->categorizeHateSpeech($matches)
        ];
    }

    /**
     * Calcula puntuación de riesgo agregada
     */
    private function calculateAggregatedRiskScore(array $analysisResults, string $context): float
    {
        $weights = [
            'profanity_check' => 0.2,
            'hate_speech_detection' => 0.25,
            'scam_detection' => 0.2,
            'personal_info_detection' => 0.1,
            'sentiment_analysis' => 0.1,
            'contextual_analysis' => 0.1,
            'ml_classification' => 0.05
        ];
        
        $totalScore = 0;
        $totalWeight = 0;
        
        foreach ($analysisResults as $analysis => $result) {
            if (isset($weights[$analysis]) && isset($result['score'])) {
                $weight = $weights[$analysis];
                $score = $result['score'];
                
                // Ajustar peso según contexto
                if ($context === self::MODERATION_CONTEXTS['PRIVATE_MESSAGE']) {
                    $weight *= 0.8; // Menos restrictivo en mensajes privados
                }
                
                $totalScore += $score * $weight;
                $totalWeight += $weight;
            }
        }
        
        return $totalWeight > 0 ? $totalScore / $totalWeight : 0;
    }

    /**
     * Determina la acción a tomar basada en el score
     */
    private function determineAction(
        float $riskScore,
        string $context,
        int $userId,
        array $analysisResults
    ): string {
        // Obtener historial del usuario
        $userRisk = $this->getUserRiskProfile($userId);
        
        // Ajustar score según el historial
        $adjustedScore = $riskScore * (1 + $userRisk['risk_multiplier']);
        
        // Determinar acción según score y contexto
        if ($adjustedScore >= 90) {
            return self::MODERATION_ACTIONS['BLOCK'];
        } elseif ($adjustedScore >= 75) {
            return self::MODERATION_ACTIONS['REQUIRE_REVIEW'];
        } elseif ($adjustedScore >= 60) {
            return self::MODERATION_ACTIONS['FLAG'];
        } elseif ($adjustedScore >= 40) {
            return self::MODERATION_ACTIONS['WARN_USER'];
        }
        
        return self::MODERATION_ACTIONS['ALLOW'];
    }

    /**
     * Crea resultado de moderación estandarizado
     */
    private function createModerationResult(string $action, array $data = []): array
    {
        return array_merge([
            'action' => $action,
            'timestamp' => Carbon::now()->toISOString(),
            'version' => '1.0.0'
        ], $data);
    }

    /**
     * Ejecuta la acción de moderación
     */
    private function executeAction(
        string $action,
        string $content,
        int $userId,
        array $result,
        array $metadata
    ): void {
        switch ($action) {
            case self::MODERATION_ACTIONS['FLAG']:
                $this->flagContent($content, $userId, $result);
                break;
                
            case self::MODERATION_ACTIONS['WARN_USER']:
                $this->warnUser($userId, $result);
                break;
                
            case self::MODERATION_ACTIONS['REQUIRE_REVIEW']:
                $this->requireHumanReview($content, $userId, $result);
                break;
                
            case self::MODERATION_ACTIONS['BLOCK']:
                $this->blockContent($content, $userId, $result);
                break;
                
            case self::MODERATION_ACTIONS['ESCALATE']:
                $this->escalateToHuman($content, $userId, $result);
                break;
        }
        
        // Disparar evento de contenido marcado
        if ($action !== self::MODERATION_ACTIONS['ALLOW']) {
            Event::dispatch(new ContentFlagged(
                $userId,
                $content,
                $action,
                $result['detected_violations'] ?? [],
                $result['confidence_level'] ?? 0
            ));
        }
    }

    /**
     * Obtiene lista de palabras de profanidad con pesos
     */
    private function getProfanityWordList(): array
    {
        return Cache::remember('profanity_words', 86400, function () {
            return $this->reportRepository->getProfanityWords();
        });
    }

    /**
     * Obtiene patrones de discurso de odio
     */
    private function getHateSpeechPatterns(): array
    {
        return Cache::remember('hate_speech_patterns', 86400, function () {
            return $this->reportRepository->getHateSpeechPatterns();
        });
    }

    /**
     * Verifica mayúsculas excesivas
     */
    private function hasExcessiveCaps(string $content): bool
    {
        $totalChars = strlen($content);
        if ($totalChars < 10) return false;
        
        $upperChars = strlen(preg_replace('/[^A-Z]/', '', $content));
        return ($upperChars / $totalChars) > 0.6;
    }

    /**
     * Verifica repetición excesiva
     */
    private function hasExcessiveRepetition(string $content): bool
    {
        // Buscar patrones repetitivos
        return preg_match('/(.{2,})\1{3,}/', $content) > 0;
    }

    /**
     * Verifica patrones sospechosos
     */
    private function hasSuspiciousPatterns(string $content): bool
    {
        $suspiciousPatterns = [
            '/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/',  // Números de teléfono
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',  // Emails
            '/(?:https?:\/\/)?(?:www\.)?[a-zA-Z0-9]/',  // URLs
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
}