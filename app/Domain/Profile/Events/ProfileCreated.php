<?php

declare(strict_types=1);

namespace App\Domain\Profile\Events;

use App\Models\User\User;
use App\Models\User\Profile;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

/**
 * ProfileCreated Event
 * 
 * Evento disparado cuando un perfil de usuario es creado exitosamente
 * en la aplicación de citas ForeverUsInLove.
 * 
 * Este evento permite a otros componentes del sistema reaccionar
 * a la creación de perfiles para:
 * - Iniciar proceso de onboarding personalizado
 * - Configurar recomendaciones iniciales de matching
 * - Activar sistemas de moderación de contenido
 * - Registrar métricas de conversión y engagement
 * - Disparar campañas de bienvenida personalizadas
 * - Calcular compatibilidades iniciales
 * - Generar insights de completitud de perfil
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class ProfileCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Usuario propietario del perfil
     */
    public readonly User $user;
    
    /**
     * Perfil recién creado
     */
    public readonly Profile $profile;
    
    /**
     * Timestamp del evento
     */
    public readonly Carbon $timestamp;
    
    /**
     * Porcentaje de completitud inicial
     */
    public readonly int $completenessPercentage;
    
    /**
     * Puntaje inicial del perfil
     */
    public readonly float $profileScore;
    
    /**
     * Opciones y contexto de creación
     */
    public readonly array $creationContext;
    
    /**
     * Información demográfica del perfil
     */
    public readonly array $demographicData;
    
    /**
     * Estado inicial del perfil
     */
    public readonly string $initialStatus;
    
    /**
     * Métricas iniciales calculadas
     */
    public readonly array $initialMetrics;

    /**
     * Create a new event instance.
     *
     * @param User $user Usuario propietario
     * @param Profile $profile Perfil creado
     * @param int $completenessPercentage Porcentaje de completitud inicial
     * @param float $profileScore Puntaje inicial del perfil
     * @param array $creationContext Contexto de creación
     */
    public function __construct(
        User $user,
        Profile $profile,
        int $completenessPercentage = 0,
        float $profileScore = 0.0,
        array $creationContext = []
    ) {
        $this->user = $user;
        $this->profile = $profile;
        $this->timestamp = now();
        $this->completenessPercentage = $completenessPercentage;
        $this->profileScore = $profileScore;
        $this->creationContext = $creationContext;
        $this->demographicData = $this->extractDemographicData($profile);
        $this->initialStatus = $profile->status ?? 'incomplete';
        $this->initialMetrics = $this->calculateInitialMetrics($profile, $completenessPercentage, $profileScore);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Canal para el usuario - onboarding personalizado
            new PrivateChannel("user.{$this->user->id}.onboarding"),
            
            // Canal administrativo para métricas en tiempo real
            new PrivateChannel('admin.profile-creations'),
            
            // Canal para analytics y conversión
            new PrivateChannel('analytics.profile-conversions'),
            
            // Canal para sistema de matching
            new PrivateChannel('matching.new-profiles'),
            
            // Canal para moderación de contenido
            new PrivateChannel('moderation.profile-reviews')
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
            'profile_id' => $this->profile->id,
            'profile_uuid' => $this->profile->profile_uuid ?? null,
            'timestamp' => $this->timestamp->toISOString(),
            
            // Información básica del perfil (sin datos sensibles)
            'profile_info' => [
                'status' => $this->initialStatus,
                'completeness_percentage' => $this->completenessPercentage,
                'profile_score' => $this->profileScore,
                'has_bio' => !empty($this->profile->bio),
                'has_location' => !empty($this->profile->location),
                'age_range' => $this->getAgeRange($this->profile->age ?? null)
            ],
            
            // Datos demográficos generales
            'demographics' => [
                'age_group' => $this->demographicData['age_group'] ?? 'unknown',
                'gender' => $this->demographicData['gender'] ?? 'not_specified',
                'location_type' => $this->demographicData['location_type'] ?? 'unknown',
                'education_level' => $this->demographicData['education_level'] ?? 'not_specified'
            ],
            
            // Métricas para matching
            'matching_readiness' => [
                'ready_for_matching' => $this->completenessPercentage >= 80,
                'estimated_pool_size' => $this->initialMetrics['estimated_pool_size'] ?? 0,
                'compatibility_factors' => $this->initialMetrics['compatibility_factors'] ?? [],
                'recommended_next_steps' => $this->getRecommendedNextSteps()
            ],
            
            // Contexto de creación
            'creation_context' => [
                'source' => $this->creationContext['source'] ?? 'web',
                'onboarding_flow' => $this->creationContext['onboarding_flow'] ?? 'standard',
                'referral_source' => $this->creationContext['referral_source'] ?? null,
                'campaign_id' => $this->creationContext['campaign_id'] ?? null
            ],
            
            // Configuraciones de personalización
            'personalization' => [
                'recommended_tests' => $this->getRecommendedPersonalityTests(),
                'suggested_interests' => $this->getSuggestedInterests(),
                'photo_recommendations' => $this->getPhotoRecommendations(),
                'profile_optimization_tips' => $this->getProfileOptimizationTips()
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
        return 'profile.created';
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
            'event_type' => 'profile_created',
            'event_version' => '2.0',
            'user_id' => $this->user->id,
            'profile_id' => $this->profile->id,
            'timestamp' => $this->timestamp->toISOString(),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            
            // Información del usuario
            'user_info' => [
                'username' => $this->user->username,
                'email' => $this->user->email,
                'registration_date' => $this->user->created_at->toISOString(),
                'account_age_hours' => $this->user->created_at->diffInHours($this->timestamp),
                'email_verified' => $this->user->hasVerifiedEmail(),
                'phone_verified' => $this->user->phone_verified_at !== null
            ],
            
            // Datos del perfil creado
            'profile_data' => [
                'profile_uuid' => $this->profile->profile_uuid ?? null,
                'status' => $this->initialStatus,
                'completeness_percentage' => $this->completenessPercentage,
                'profile_score' => $this->profileScore,
                'has_required_fields' => $this->hasRequiredFields(),
                'field_completion' => $this->getFieldCompletion(),
                'bio_length' => strlen($this->profile->bio ?? ''),
                'interests_count' => count($this->profile->interests ?? [])
            ],
            
            // Análisis demográfico
            'demographic_analysis' => array_merge($this->demographicData, [
                'target_market_segment' => $this->getTargetMarketSegment(),
                'geographic_region' => $this->getGeographicRegion(),
                'socioeconomic_indicators' => $this->getSocioeconomicIndicators()
            ]),
            
            // Contexto de creación detallado
            'creation_context' => array_merge($this->creationContext, [
                'device_info' => [
                    'type' => $this->creationContext['device_type'] ?? 'unknown',
                    'platform' => $this->creationContext['platform'] ?? 'unknown',
                    'app_version' => $this->creationContext['app_version'] ?? null
                ],
                'acquisition_data' => [
                    'utm_source' => $this->creationContext['utm_source'] ?? null,
                    'utm_medium' => $this->creationContext['utm_medium'] ?? null,
                    'utm_campaign' => $this->creationContext['utm_campaign'] ?? null,
                    'referrer_url' => $this->creationContext['referrer_url'] ?? null
                ],
                'onboarding_metrics' => [
                    'steps_completed' => $this->creationContext['onboarding_steps'] ?? [],
                    'completion_time_minutes' => $this->creationContext['completion_time'] ?? null,
                    'abandonment_points' => $this->creationContext['abandonment_points'] ?? []
                ]
            ]),
            
            // Métricas calculadas
            'calculated_metrics' => $this->initialMetrics,
            
            // Configuración de matching inicial
            'matching_configuration' => [
                'age_preferences' => $this->getDefaultAgePreferences(),
                'distance_preferences' => $this->getDefaultDistancePreferences(),
                'initial_visibility' => $this->getInitialVisibility(),
                'matching_algorithm_version' => config('matching.algorithm_version', '2.1')
            ]
        ];
    }

    /**
     * Get data for conversion funnel analysis
     *
     * @return array
     */
    public function getConversionFunnelData(): array
    {
        return [
            'event' => 'profile_creation_completed',
            'user_id' => $this->user->id,
            'timestamp' => $this->timestamp->timestamp,
            'funnel_stage' => 'profile_created',
            'conversion_properties' => [
                'registration_to_profile_hours' => $this->user->created_at->diffInHours($this->timestamp),
                'initial_completeness' => $this->completenessPercentage,
                'profile_quality_score' => $this->profileScore,
                'onboarding_source' => $this->creationContext['source'] ?? 'unknown',
                'device_type' => $this->creationContext['device_type'] ?? 'unknown',
                'geographic_market' => $this->getGeographicMarket(),
                'user_segment' => $this->getUserSegment(),
                'acquisition_channel' => $this->getAcquisitionChannel()
            ]
        ];
    }

    /**
     * Get matching readiness assessment
     *
     * @return array
     */
    public function getMatchingReadinessAssessment(): array
    {
        $readinessScore = $this->calculateMatchingReadiness();
        
        return [
            'overall_readiness' => $readinessScore,
            'ready_for_matching' => $readinessScore >= 75,
            'readiness_factors' => [
                'profile_completeness' => $this->completenessPercentage,
                'has_photos' => $this->hasPhotos(),
                'bio_quality' => $this->getBioQuality(),
                'interests_defined' => $this->hasInterestsDefined(),
                'location_specified' => !empty($this->profile->location)
            ],
            'blocking_factors' => $this->getBlockingFactors($readinessScore),
            'time_to_ready_estimate' => $this->estimateTimeToReady($readinessScore),
            'recommended_actions' => $this->getRecommendedNextSteps(),
            'estimated_impact' => [
                'potential_matches_per_week' => $this->estimatePotentialMatches(),
                'visibility_boost_percentage' => $this->calculateVisibilityBoost($readinessScore)
            ]
        ];
    }

    /**
     * Get personalization recommendations
     *
     * @return array
     */
    public function getPersonalizationRecommendations(): array
    {
        return [
            'immediate_actions' => [
                'upload_photos' => [
                    'priority' => 'high',
                    'impact' => 'significant',
                    'description' => 'Subir al menos 3 fotos de calidad',
                    'estimated_time_minutes' => 10
                ],
                'complete_bio' => [
                    'priority' => 'high',
                    'impact' => 'significant', 
                    'description' => 'Escribir una biografía atractiva de al menos 100 palabras',
                    'estimated_time_minutes' => 15
                ],
                'add_interests' => [
                    'priority' => 'medium',
                    'impact' => 'moderate',
                    'description' => 'Seleccionar al menos 5 intereses',
                    'estimated_time_minutes' => 5
                ]
            ],
            'suggested_content' => [
                'bio_templates' => $this->getBioTemplates(),
                'popular_interests' => $this->getPopularInterests(),
                'photo_style_suggestions' => $this->getPhotoStyleSuggestions()
            ],
            'personality_insights' => [
                'recommended_tests' => $this->getRecommendedPersonalityTests(),
                'test_order' => ['big_five', 'love_language', 'attachment_style'],
                'completion_incentives' => $this->getTestCompletionIncentives()
            ],
            'matching_optimization' => [
                'preference_suggestions' => $this->getPreferenceSuggestions(),
                'distance_recommendations' => $this->getDistanceRecommendations(),
                'age_range_suggestions' => $this->getAgeRangeSuggestions()
            ]
        ];
    }

    /**
     * Get moderation and safety data
     *
     * @return array
     */
    public function getModerationData(): array
    {
        return [
            'requires_review' => $this->requiresManualReview(),
            'auto_moderation_flags' => $this->getAutoModerationFlags(),
            'content_analysis' => [
                'bio_sentiment' => $this->analyzeBioSentiment(),
                'bio_language_detected' => $this->detectBioLanguage(),
                'inappropriate_content_risk' => $this->calculateInappropriateContentRisk(),
                'spam_indicators' => $this->getSpamIndicators()
            ],
            'safety_score' => $this->calculateSafetyScore(),
            'verification_requirements' => [
                'photo_verification_needed' => $this->needsPhotoVerification(),
                'identity_verification_suggested' => $this->suggestIdentityVerification(),
                'phone_verification_required' => !$this->user->phone_verified_at
            ],
            'risk_assessment' => [
                'account_authenticity_score' => $this->calculateAuthenticityScore(),
                'behavioral_risk_indicators' => $this->getBehavioralRiskIndicators(),
                'compliance_status' => $this->getComplianceStatus()
            ]
        ];
    }

    // ========================================
    // MÉTODOS PRIVADOS HELPER
    // ========================================

    /**
     * Extrae datos demográficos del perfil
     */
    private function extractDemographicData(Profile $profile): array
    {
        return [
            'age_group' => $this->calculateAgeGroup($profile->age ?? null),
            'gender' => $profile->gender ?? 'not_specified',
            'location_type' => $this->analyzeLocationType($profile->location ?? ''),
            'education_level' => $profile->education ?? 'not_specified',
            'occupation_category' => $this->categorizeOccupation($profile->occupation ?? ''),
            'relationship_goals' => $profile->relationship_goals ?? 'not_specified'
        ];
    }

    /**
     * Calcula métricas iniciales
     */
    private function calculateInitialMetrics(Profile $profile, int $completeness, float $score): array
    {
        return [
            'estimated_pool_size' => $this->estimateInitialPoolSize($profile),
            'compatibility_factors' => $this->identifyCompatibilityFactors($profile),
            'engagement_prediction' => $this->predictEngagement($profile, $completeness, $score),
            'success_probability' => $this->calculateSuccessProbability($profile, $completeness),
            'optimization_potential' => max(0, 100 - $completeness),
            'market_competitiveness' => $this->assessMarketCompetitiveness($profile)
        ];
    }

    /**
     * Obtiene rango de edad para segmentación
     */
    private function getAgeRange(?int $age): string
    {
        if (!$age) return 'unknown';
        if ($age < 25) return '18-24';
        if ($age < 35) return '25-34';
        if ($age < 45) return '35-44';
        if ($age < 55) return '45-54';
        return '55+';
    }

    /**
     * Calcula grupo de edad
     */
    private function calculateAgeGroup(?int $age): string
    {
        return $this->getAgeRange($age);
    }

    /**
     * Analiza tipo de ubicación
     */
    private function analyzeLocationType(string $location): string
    {
        if (empty($location)) return 'not_specified';
        
        $majorCities = ['Madrid', 'Barcelona', 'Valencia', 'Sevilla', 'Bilbao'];
        foreach ($majorCities as $city) {
            if (str_contains($location, $city)) {
                return 'major_city';
            }
        }
        
        return 'other';
    }

    /**
     * Categoriza ocupación
     */
    private function categorizeOccupation(string $occupation): string
    {
        if (empty($occupation)) return 'not_specified';
        
        $categories = [
            'technology' => ['developer', 'engineer', 'programmer', 'tech'],
            'healthcare' => ['doctor', 'nurse', 'medical', 'health'],
            'education' => ['teacher', 'professor', 'education', 'tutor'],
            'business' => ['manager', 'consultant', 'business', 'executive'],
            'creative' => ['artist', 'designer', 'creative', 'photographer']
        ];
        
        $occupation = strtolower($occupation);
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($occupation, $keyword)) {
                    return $category;
                }
            }
        }
        
        return 'other';
    }

    // ========================================
    // MÉTODOS HELPER ADICIONALES
    // ========================================

    private function getRecommendedNextSteps(): array
    {
        $steps = [];
        
        if ($this->completenessPercentage < 50) {
            $steps[] = ['action' => 'complete_basic_info', 'priority' => 'high'];
        }
        
        if (!$this->hasPhotos()) {
            $steps[] = ['action' => 'upload_photos', 'priority' => 'critical'];
        }
        
        if (empty($this->profile->bio) || strlen($this->profile->bio) < 50) {
            $steps[] = ['action' => 'write_bio', 'priority' => 'high'];
        }
        
        return $steps;
    }

    private function getRecommendedPersonalityTests(): array
    {
        return ['big_five', 'love_language', 'attachment_style'];
    }

    private function getSuggestedInterests(): array
    {
        return ['travel', 'fitness', 'music', 'movies', 'cooking'];
    }

    private function getPhotoRecommendations(): array
    {
        return [
            'Upload a clear headshot as your main photo',
            'Include a full-body photo',
            'Add photos showing your hobbies'
        ];
    }

    private function getProfileOptimizationTips(): array
    {
        return [
            'Write a bio that shows your personality',
            'Be specific about your interests',
            'Use recent, high-quality photos'
        ];
    }

    private function hasRequiredFields(): bool
    {
        return !empty($this->profile->first_name) &&
               !empty($this->profile->age) &&
               !empty($this->profile->gender) &&
               !empty($this->profile->location);
    }

    private function getFieldCompletion(): array
    {
        return [
            'first_name' => !empty($this->profile->first_name),
            'age' => !empty($this->profile->age),
            'gender' => !empty($this->profile->gender),
            'bio' => !empty($this->profile->bio),
            'location' => !empty($this->profile->location),
            'occupation' => !empty($this->profile->occupation),
            'education' => !empty($this->profile->education),
            'interests' => !empty($this->profile->interests)
        ];
    }

    // Métodos helper que retornan valores mock para demostración
    private function getTargetMarketSegment(): string { return 'young_professional'; }
    private function getGeographicRegion(): string { return 'madrid_metro'; }
    private function getSocioeconomicIndicators(): array { return ['income_level' => 'middle', 'education' => 'university']; }
    private function getDefaultAgePreferences(): array { return ['min' => 22, 'max' => 35]; }
    private function getDefaultDistancePreferences(): array { return ['max_km' => 25]; }
    private function getInitialVisibility(): string { return 'standard'; }
    private function getGeographicMarket(): string { return 'es_madrid'; }
    private function getUserSegment(): string { return 'new_user'; }
    private function getAcquisitionChannel(): string { return $this->creationContext['utm_source'] ?? 'organic'; }
    private function calculateMatchingReadiness(): int { return min(100, $this->completenessPercentage + 10); }
    private function hasPhotos(): bool { return false; } // Mock - en realidad verificaría fotos
    private function getBioQuality(): int { return strlen($this->profile->bio ?? '') > 50 ? 80 : 40; }
    private function hasInterestsDefined(): bool { return !empty($this->profile->interests); }
    private function getBlockingFactors(int $readiness): array { return $readiness < 75 ? ['missing_photos', 'incomplete_bio'] : []; }
    private function estimateTimeToReady(int $readiness): int { return max(0, (100 - $readiness) * 2); }
    private function estimatePotentialMatches(): int { return rand(10, 50); }
    private function calculateVisibilityBoost(int $readiness): int { return $readiness - 50; }
    private function getBioTemplates(): array { return ['adventure_seeker', 'romantic', 'intellectual']; }
    private function getPopularInterests(): array { return ['travel', 'fitness', 'music']; }
    private function getPhotoStyleSuggestions(): array { return ['natural_lighting', 'genuine_smile', 'hobby_photos']; }
    private function getTestCompletionIncentives(): array { return ['profile_boost', 'better_matches']; }
    private function getPreferenceSuggestions(): array { return []; }
    private function getDistanceRecommendations(): array { return ['start_with' => 25, 'expand_to' => 50]; }
    private function getAgeRangeSuggestions(): array { return ['expand_by' => 3, 'direction' => 'both']; }
    private function requiresManualReview(): bool { return false; }
    private function getAutoModerationFlags(): array { return []; }
    private function analyzeBioSentiment(): string { return 'positive'; }
    private function detectBioLanguage(): string { return 'spanish'; }
    private function calculateInappropriateContentRisk(): float { return 0.1; }
    private function getSpamIndicators(): array { return []; }
    private function calculateSafetyScore(): float { return 8.5; }
    private function needsPhotoVerification(): bool { return true; }
    private function suggestIdentityVerification(): bool { return false; }
    private function calculateAuthenticityScore(): float { return 7.8; }
    private function getBehavioralRiskIndicators(): array { return []; }
    private function getComplianceStatus(): string { return 'compliant'; }
    private function estimateInitialPoolSize(Profile $profile): int { return rand(500, 2000); }
    private function identifyCompatibilityFactors(Profile $profile): array { return ['age', 'location', 'interests']; }
    private function predictEngagement(Profile $profile, int $completeness, float $score): float { return ($completeness + $score * 10) / 2; }
    private function calculateSuccessProbability(Profile $profile, int $completeness): float { return $completeness / 100 * 0.8; }
    private function assessMarketCompetitiveness(Profile $profile): float { return 6.5; }

    /**
     * Convert event to array format for storage or serialization
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'profile' => $this->profile->toArray(),
            'timestamp' => $this->timestamp->toISOString(),
            'completeness_percentage' => $this->completenessPercentage,
            'profile_score' => $this->profileScore,
            'creation_context' => $this->creationContext,
            'demographic_data' => $this->demographicData,
            'initial_status' => $this->initialStatus,
            'initial_metrics' => $this->initialMetrics
        ];
    }
}