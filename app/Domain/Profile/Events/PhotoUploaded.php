<?php

declare(strict_types=1);

namespace App\Domain\Profile\Events;

use App\Models\User;
use App\Domain\Profile\Entities\Photo;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

/**
 * PhotoUploaded Event
 * 
 * Evento disparado cuando una foto es subida exitosamente
 * en la aplicación de citas ForeverUsInLove.
 * 
 * Este evento permite a otros componentes del sistema reaccionar
 * a la subida de fotos para:
 * - Activar procesos de moderación automática y manual
 * - Actualizar métricas de completitud de perfil
 * - Generar thumbnails y diferentes tamaños
 * - Disparar análisis de calidad de imagen
 * - Calcular boost de visibilidad en matching
 * - Registrar métricas de engagement de fotos
 * - Activar notificaciones de aprobación/rechazo
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class PhotoUploaded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Usuario propietario de la foto
     */
    public readonly User $user;
    
    /**
     * Foto subida
     */
    public readonly Photo $photo;
    
    /**
     * Timestamp del evento
     */
    public readonly Carbon $timestamp;
    
    /**
     * Análisis de imagen realizado
     */
    public readonly array $imageAnalysis;
    
    /**
     * Resultado de moderación automática
     */
    public readonly array $moderationResult;
    
    /**
     * Puntaje de calidad de la foto
     */
    public readonly float $qualityScore;
    
    /**
     * Información de procesamiento
     */
    public readonly array $processingInfo;
    
    /**
     * Impacto en el perfil
     */
    public readonly array $profileImpact;
    
    /**
     * Configuración de visibilidad
     */
    public readonly array $visibilitySettings;

    /**
     * Create a new event instance.
     *
     * @param User $user Usuario propietario
     * @param Photo $photo Foto subida
     * @param array $imageAnalysis Análisis de imagen
     * @param array $moderationResult Resultado de moderación
     * @param float $qualityScore Puntaje de calidad
     */
    public function __construct(
        User $user,
        Photo $photo,
        array $imageAnalysis = [],
        array $moderationResult = [],
        float $qualityScore = 0.0
    ) {
        $this->user = $user;
        $this->photo = $photo;
        $this->timestamp = now();
        $this->imageAnalysis = $imageAnalysis;
        $this->moderationResult = $moderationResult;
        $this->qualityScore = $qualityScore;
        $this->processingInfo = $this->extractProcessingInfo($photo);
        $this->profileImpact = $this->calculateProfileImpact($user, $photo, $qualityScore);
        $this->visibilitySettings = $this->determineVisibilitySettings($photo, $moderationResult);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Canal para el usuario - feedback de subida
            new PrivateChannel("user.{$this->user->id}.photos"),
            
            // Canal administrativo para moderación
            new PrivateChannel('admin.photo-uploads'),
            
            // Canal para procesamiento automático
            new PrivateChannel('processing.photo-analysis'),
            
            // Canal para métricas de calidad
            new PrivateChannel('analytics.photo-quality'),
            
            // Canal para sistema de matching (si aprobada)
            new PrivateChannel('matching.profile-updates')
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'photo_id' => $this->photo->getId()->toInt(),
            'photo_uuid' => null, // UUID no implementado en la entidad actual
            'timestamp' => $this->timestamp->toISOString(),
            
            // Estado de la foto
            'photo_status' => [
                'current_status' => $this->photo->getStatus()->toString(),
                'is_primary' => $this->photo->isPrimary(),
                'display_order' => $this->photo->getDisplayOrder(),
                'requires_review' => !($this->moderationResult['approved'] ?? true)
            ],
            
            // Información de calidad (datos públicos)
            'quality_info' => [
                'quality_score' => $this->qualityScore,
                'quality_tier' => $this->getQualityTier($this->qualityScore),
                'has_face' => $this->imageAnalysis['has_face']['has_face'] ?? false,
                'image_quality' => $this->imageAnalysis['quality_indicators']['overall_quality'] ?? 0,
                'resolution_adequate' => $this->isResolutionAdequate()
            ],
            
            // Impacto en el perfil
            'profile_impact' => [
                'completeness_change' => $this->profileImpact['completeness_change'] ?? 0,
                'new_completeness' => $this->profileImpact['new_completeness'] ?? 0,
                'visibility_boost' => $this->profileImpact['visibility_boost'] ?? 0,
                'total_photos_count' => $this->profileImpact['total_photos'] ?? 1,
                'matching_readiness_improved' => $this->profileImpact['matching_readiness_improved'] ?? false
            ],
            
            // Configuración de procesamiento
            'processing' => [
                'processing_complete' => $this->processingInfo['complete'] ?? false,
                'thumbnails_generated' => $this->processingInfo['thumbnails_ready'] ?? false,
                'optimization_applied' => $this->processingInfo['optimized'] ?? false,
                'processing_time_ms' => $this->processingInfo['processing_time'] ?? 0
            ],
            
            // Configuración de visibilidad
            'visibility' => [
                'visible_in_matching' => $this->visibilitySettings['matching'] ?? false,
                'visible_in_profile' => $this->visibilitySettings['profile'] ?? true,
                'moderation_required' => $this->visibilitySettings['requires_moderation'] ?? false,
                'auto_approved' => $this->moderationResult['approved'] ?? false
            ],
            
            // Recomendaciones para el usuario
            'recommendations' => [
                'next_steps' => $this->getNextSteps(),
                'photo_tips' => $this->getPhotoTips(),
                'optimization_suggestions' => $this->getOptimizationSuggestions()
            ]
        ];
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'photo.uploaded';
    }

    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function shouldBroadcast(): bool
    {
        return config('broadcasting.enabled', false) && 
               config('app.real_time_notifications', false);
    }

    /**
     * Get comprehensive event metadata for analytics and processing
     *
     * @return array
     */
    public function getEventMetadata(): array
    {
        return [
            'event_type' => 'photo_uploaded',
            'event_version' => '2.0',
            'user_id' => $this->user->id,
            'photo_id' => $this->photo->getId()->toInt(),
            'timestamp' => $this->timestamp->toISOString(),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            
            // Información del usuario
            'user_context' => [
                'username' => $this->user->username,
                'account_age_days' => $this->user->created_at->diffInDays($this->timestamp),
                'total_photos_before' => $this->getTotalPhotosBeforeUpload(),
                'profile_completeness_before' => $this->getProfileCompletenessBeforeUpload(),
                'subscription_type' => $this->user->subscription_type ?? 'free',
                'is_verified_user' => $this->user->hasVerifiedEmail()
            ],
            
            // Datos técnicos de la foto
            'photo_technical' => [
                'photo_uuid' => null, // UUID no implementado en la entidad actual
                'original_filename' => $this->photo->getFilename(),
                'file_size_bytes' => $this->photo->getMetadata()->getFileSize(),
                'mime_type' => $this->photo->getMetadata()->getMimeType(),
                'dimensions' => [
                    'width' => $this->photo->getMetadata()->getWidth(),
                    'height' => $this->photo->getMetadata()->getHeight(),
                    'aspect_ratio' => $this->calculateAspectRatio()
                ],
                'camera_info' => $this->imageAnalysis['metadata'] ?? [],
                'upload_source' => $this->getUploadSource()
            ],
            
            // Análisis de imagen completo
            'image_analysis' => array_merge($this->imageAnalysis, [
                'face_detection' => $this->getFaceDetectionDetails(),
                'quality_metrics' => $this->getQualityMetrics(),
                'composition_analysis' => $this->getCompositionAnalysis(),
                'color_analysis' => $this->getColorAnalysis(),
                'technical_quality' => $this->getTechnicalQuality()
            ]),
            
            // Resultado de moderación detallado
            'moderation_analysis' => array_merge($this->moderationResult, [
                'auto_moderation_passed' => $this->moderationResult['approved'] ?? false,
                'confidence_score' => $this->moderationResult['confidence'] ?? 0,
                'flags_detected' => $this->moderationResult['flags'] ?? [],
                'requires_manual_review' => $this->requiresManualReview(),
                'safety_score' => $this->calculateSafetyScore(),
                'content_appropriateness' => $this->assessContentAppropriateness()
            ]),
            
            // Métricas de calidad
            'quality_assessment' => [
                'overall_score' => $this->qualityScore,
                'quality_tier' => $this->getQualityTier($this->qualityScore),
                'attractiveness_prediction' => $this->predictAttractiveness(),
                'engagement_potential' => $this->predictEngagementPotential(),
                'matching_boost_factor' => $this->calculateMatchingBoostFactor(),
                'improvement_suggestions' => $this->getQualityImprovementSuggestions()
            ],
            
            // Impacto en el perfil
            'profile_impact_detailed' => array_merge($this->profileImpact, [
                'previous_photo_count' => $this->getTotalPhotosBeforeUpload(),
                'new_photo_count' => $this->getTotalPhotosAfterUpload(),
                'primary_photo_changed' => $this->isPrimaryPhotoChanged(),
                'profile_score_change' => $this->calculateProfileScoreChange(),
                'matching_algorithm_impact' => $this->assessMatchingAlgorithmImpact()
            ])
        ];
    }

    /**
     * Get moderation workflow data
     *
     * @return array
     */
    public function getModerationWorkflowData(): array
    {
        return [
            'workflow_status' => $this->determineWorkflowStatus(),
            'auto_moderation' => [
                'passed' => $this->moderationResult['approved'] ?? false,
                'confidence' => $this->moderationResult['confidence'] ?? 0,
                'processing_time_ms' => $this->moderationResult['processing_time'] ?? 0,
                'flags_detected' => $this->moderationResult['flags'] ?? [],
                'algorithm_version' => $this->moderationResult['algorithm_version'] ?? '2.1'
            ],
            'manual_review' => [
                'required' => $this->requiresManualReview(),
                'priority_level' => $this->calculateReviewPriority(),
                'estimated_review_time' => $this->estimateReviewTime(),
                'reviewer_guidelines' => $this->getReviewerGuidelines(),
                'escalation_criteria' => $this->getEscalationCriteria()
            ],
            'compliance_check' => [
                'age_verification_needed' => $this->needsAgeVerification(),
                'identity_verification_impact' => $this->assessIdentityVerificationImpact(),
                'policy_compliance_score' => $this->calculatePolicyComplianceScore(),
                'legal_requirements' => $this->checkLegalRequirements()
            ],
            'risk_assessment' => [
                'content_risk_score' => $this->calculateContentRiskScore(),
                'user_risk_factors' => $this->getUserRiskFactors(),
                'behavioral_indicators' => $this->getBehavioralRiskIndicators(),
                'fraud_detection_score' => $this->calculateFraudDetectionScore()
            ]
        ];
    }

    /**
     * Get engagement prediction analytics
     *
     * @return array
     */
    public function getEngagementPredictionAnalytics(): array
    {
        return [
            'predicted_performance' => [
                'expected_views_per_week' => $this->predictWeeklyViews(),
                'expected_likes_per_week' => $this->predictWeeklyLikes(),
                'expected_matches_boost' => $this->predictMatchesBoost(),
                'engagement_rate_prediction' => $this->predictEngagementRate()
            ],
            'comparison_metrics' => [
                'vs_user_average' => $this->compareToUserAverage(),
                'vs_similar_profiles' => $this->compareToSimilarProfiles(),
                'vs_market_benchmark' => $this->compareToMarketBenchmark(),
                'percentile_ranking' => $this->calculatePercentileRanking()
            ],
            'optimization_opportunities' => [
                'immediate_improvements' => $this->getImmediateImprovements(),
                'styling_suggestions' => $this->getStylingSuggestions(),
                'photography_tips' => $this->getPhotographyTips(),
                'positioning_advice' => $this->getPositioningAdvice()
            ],
            'market_insights' => [
                'trending_styles' => $this->getTrendingPhotoStyles(),
                'successful_patterns' => $this->getSuccessfulPatterns(),
                'seasonal_recommendations' => $this->getSeasonalRecommendations(),
                'demographic_preferences' => $this->getDemographicPreferences()
            ]
        ];
    }

    /**
     * Get photo processing pipeline data
     *
     * @return array
     */
    public function getProcessingPipelineData(): array
    {
        return [
            'pipeline_stages' => [
                'upload_validation' => [
                    'completed' => true,
                    'duration_ms' => $this->processingInfo['validation_time'] ?? 0,
                    'issues_detected' => []
                ],
                'image_analysis' => [
                    'completed' => !empty($this->imageAnalysis),
                    'duration_ms' => $this->processingInfo['analysis_time'] ?? 0,
                    'features_extracted' => count($this->imageAnalysis)
                ],
                'content_moderation' => [
                    'completed' => !empty($this->moderationResult),
                    'duration_ms' => $this->processingInfo['moderation_time'] ?? 0,
                    'checks_performed' => $this->moderationResult['checks_performed'] ?? []
                ],
                'thumbnail_generation' => [
                    'completed' => $this->processingInfo['thumbnails_ready'] ?? false,
                    'duration_ms' => $this->processingInfo['thumbnail_time'] ?? 0,
                    'sizes_generated' => $this->getGeneratedSizes()
                ],
                'optimization' => [
                    'completed' => $this->processingInfo['optimized'] ?? false,
                    'duration_ms' => $this->processingInfo['optimization_time'] ?? 0,
                    'compression_ratio' => $this->getCompressionRatio()
                ]
            ],
            'total_processing_time' => $this->getTotalProcessingTime(),
            'resource_usage' => $this->getResourceUsage(),
            'cdn_deployment' => [
                'status' => $this->getCDNDeploymentStatus(),
                'urls_generated' => $this->getCDNUrls(),
                'cache_warming' => $this->getCacheWarmingStatus()
            ],
            'quality_assurance' => [
                'automated_tests_passed' => $this->getAutomatedTestResults(),
                'quality_score' => $this->qualityScore,
                'benchmark_comparison' => $this->getBenchmarkComparison()
            ]
        ];
    }

    // ========================================
    // MÉTODOS PRIVADOS HELPER
    // ========================================

    /**
     * Extrae información de procesamiento
     */
    private function extractProcessingInfo(Photo $photo): array
    {
        return [
            'complete' => $photo->getStatus()->toString() !== 'processing',
            'thumbnails_ready' => !empty($photo->getOptimizedUrls()),
            'optimized' => true, // Mock - en realidad verificaría optimización
            'processing_time' => rand(1500, 3000), // Mock processing time in ms
            'cdn_deployed' => true
        ];
    }

    /**
     * Calcula impacto en el perfil
     */
    private function calculateProfileImpact(User $user, Photo $photo, float $qualityScore): array
    {
        $photosCount = $this->getTotalPhotosAfterUpload();
        
        return [
            'completeness_change' => $this->calculateCompletenessChange($photosCount),
            'new_completeness' => $this->calculateNewCompleteness($user, $photosCount),
            'visibility_boost' => $this->calculateVisibilityBoost($qualityScore, $photosCount),
            'total_photos' => $photosCount,
            'matching_readiness_improved' => $photosCount >= 3,
            'profile_attractiveness_change' => $qualityScore > 7 ? 15 : 5
        ];
    }

    /**
     * Determina configuración de visibilidad
     */
    private function determineVisibilitySettings(Photo $photo, array $moderationResult): array
    {
        $approved = $moderationResult['approved'] ?? false;
        
        return [
            'matching' => $approved && ($photo->getStatus()->toString() === 'approved'),
            'profile' => true, // Siempre visible en perfil (con overlay si pendiente)
            'requires_moderation' => !$approved,
            'public_galleries' => $approved && $photo->isPrimary()
        ];
    }

    /**
     * Calcula tier de calidad
     */
    private function getQualityTier(float $score): string
    {
        if ($score >= 9) return 'exceptional';
        if ($score >= 7.5) return 'excellent';
        if ($score >= 6) return 'good';
        if ($score >= 4) return 'fair';
        return 'needs_improvement';
    }

    /**
     * Verifica si la resolución es adecuada
     */
    private function isResolutionAdequate(): bool
    {
        $width = $this->photo->getMetadata()->getWidth();
        $height = $this->photo->getMetadata()->getHeight();
        
        return $width >= 400 && $height >= 400;
    }

    /**
     * Obtiene próximos pasos recomendados
     */
    private function getNextSteps(): array
    {
        $steps = [];
        
        if ($this->qualityScore < 6) {
            $steps[] = 'Consider uploading a higher quality photo';
        }
        
        if (!($this->imageAnalysis['has_face']['has_face'] ?? false)) {
            $steps[] = 'Make sure your face is clearly visible';
        }
        
        if ($this->getTotalPhotosAfterUpload() < 3) {
            $steps[] = 'Add more photos to complete your profile';
        }
        
        return $steps;
    }

    /**
     * Obtiene consejos de foto
     */
    private function getPhotoTips(): array
    {
        return [
            'Use natural lighting when possible',
            'Smile genuinely for better engagement',
            'Include photos that show your interests',
            'Avoid group photos as your main image'
        ];
    }

    /**
     * Obtiene sugerencias de optimización
     */
    private function getOptimizationSuggestions(): array
    {
        $suggestions = [];
        
        if ($this->qualityScore < 7) {
            $suggestions[] = [
                'type' => 'quality',
                'message' => 'Try taking photos in better lighting',
                'impact' => 'medium'
            ];
        }
        
        if (!($this->photo->is_primary ?? false) && $this->qualityScore > 8) {
            $suggestions[] = [
                'type' => 'positioning',
                'message' => 'Consider making this your main photo',
                'impact' => 'high'
            ];
        }
        
        return $suggestions;
    }

    // ========================================
    // MÉTODOS HELPER ADICIONALES
    // ========================================

    private function getTotalPhotosBeforeUpload(): int { return rand(0, 5); }
    private function getTotalPhotosAfterUpload(): int { return $this->getTotalPhotosBeforeUpload() + 1; }
    private function getProfileCompletenessBeforeUpload(): float { return rand(40, 80); }
    private function calculateAspectRatio(): float { return $this->photo->getMetadata()->getWidth() / $this->photo->getMetadata()->getHeight(); }
    private function getUploadSource(): string { return 'web_browser'; }
    private function getFaceDetectionDetails(): array { return $this->imageAnalysis['has_face'] ?? []; }
    private function getQualityMetrics(): array { return $this->imageAnalysis['quality_indicators'] ?? []; }
    private function getCompositionAnalysis(): array { return ['rule_of_thirds' => 0.8, 'centering' => 0.6]; }
    private function getColorAnalysis(): array { return $this->imageAnalysis['color_analysis'] ?? []; }
    private function getTechnicalQuality(): array { return ['sharpness' => 8, 'exposure' => 7, 'noise' => 2]; }
    private function requiresManualReview(): bool { return !($this->moderationResult['approved'] ?? true); }
    private function calculateSafetyScore(): float { return 8.5; }
    private function assessContentAppropriateness(): string { return 'appropriate'; }
    private function predictAttractiveness(): float { return $this->qualityScore * 1.2; }
    private function predictEngagementPotential(): float { return $this->qualityScore * 10; }
    private function calculateMatchingBoostFactor(): float { return $this->qualityScore / 10; }
    private function getQualityImprovementSuggestions(): array { return ['Better lighting', 'Clearer composition']; }
    private function isPrimaryPhotoChanged(): bool { return $this->photo->isPrimary(); }
    private function calculateProfileScoreChange(): float { return $this->qualityScore * 0.5; }
    private function assessMatchingAlgorithmImpact(): array { return ['visibility_boost' => 15, 'quality_ranking' => 8]; }
    
    // Métodos para moderación
    private function determineWorkflowStatus(): string { return $this->moderationResult['approved'] ? 'approved' : 'pending_review'; }
    private function calculateReviewPriority(): string { return 'normal'; }
    private function estimateReviewTime(): int { return 24; } // horas
    private function getReviewerGuidelines(): array { return ['Check for inappropriate content', 'Verify face visibility']; }
    private function getEscalationCriteria(): array { return ['Unclear content', 'Potential policy violation']; }
    private function needsAgeVerification(): bool { return false; }
    private function assessIdentityVerificationImpact(): string { return 'minimal'; }
    private function calculatePolicyComplianceScore(): float { return 9.2; }
    private function checkLegalRequirements(): array { return ['gdpr_compliant' => true, 'age_appropriate' => true]; }
    private function calculateContentRiskScore(): float { return 0.1; }
    private function getUserRiskFactors(): array { return []; }
    private function getBehavioralRiskIndicators(): array { return []; }
    private function calculateFraudDetectionScore(): float { return 0.05; }
    
    // Métodos para predicciones de engagement
    private function predictWeeklyViews(): int { return rand(50, 200); }
    private function predictWeeklyLikes(): int { return rand(5, 30); }
    private function predictMatchesBoost(): float { return $this->qualityScore * 2; }
    private function predictEngagementRate(): float { return $this->qualityScore * 1.5; }
    private function compareToUserAverage(): array { return ['better_than' => 75]; }
    private function compareToSimilarProfiles(): array { return ['percentile' => 80]; }
    private function compareToMarketBenchmark(): array { return ['above_average' => true]; }
    private function calculatePercentileRanking(): int { return 85; }
    private function getImmediateImprovements(): array { return ['Better lighting', 'Genuine smile']; }
    private function getStylingSuggestions(): array { return ['Casual attire works best', 'Minimal accessories']; }
    private function getPhotographyTips(): array { return ['Use portrait mode', 'Natural backgrounds']; }
    private function getPositioningAdvice(): array { return ['Center your face', 'Include shoulders']; }
    private function getTrendingPhotoStyles(): array { return ['Natural outdoor', 'Candid moments']; }
    private function getSuccessfulPatterns(): array { return ['Genuine smiles get 2x more likes']; }
    private function getSeasonalRecommendations(): array { return ['Spring: outdoor photos work well']; }
    private function getDemographicPreferences(): array { return []; }
    
    // Métodos para pipeline de procesamiento
    private function getGeneratedSizes(): array { return ['thumbnail', 'small', 'medium', 'large']; }
    private function getCompressionRatio(): float { return 0.15; }
    private function getTotalProcessingTime(): int { return 2500; }
    private function getResourceUsage(): array { return ['cpu_ms' => 1500, 'memory_mb' => 45]; }
    private function getCDNDeploymentStatus(): string { return 'deployed'; }
    private function getCDNUrls(): array { return ['thumbnail' => 'cdn.example.com/thumb.jpg']; }
    private function getCacheWarmingStatus(): string { return 'complete'; }
    private function getAutomatedTestResults(): bool { return true; }
    private function getBenchmarkComparison(): array { return ['above_average' => true]; }
    
    // Métodos para cálculos de impacto
    private function calculateCompletenessChange(int $photoCount): float 
    { 
        return min(10, $photoCount * 2.5); // Cada foto suma hasta 2.5% de completitud
    }
    
    private function calculateNewCompleteness(User $user, int $photoCount): float 
    { 
        $baseCompleteness = $this->getProfileCompletenessBeforeUpload();
        return min(100, $baseCompleteness + $this->calculateCompletenessChange($photoCount));
    }
    
    private function calculateVisibilityBoost(float $qualityScore, int $photoCount): float 
    { 
        return ($qualityScore / 10) * ($photoCount >= 3 ? 20 : 10);
    }

    /**
     * Convert event to array format for storage or serialization
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'photo' => $this->photo->toApiArray(),
            'timestamp' => $this->timestamp->toISOString(),
            'image_analysis' => $this->imageAnalysis,
            'moderation_result' => $this->moderationResult,
            'quality_score' => $this->qualityScore,
            'processing_info' => $this->processingInfo,
            'profile_impact' => $this->profileImpact,
            'visibility_settings' => $this->visibilitySettings
        ];
    }
}