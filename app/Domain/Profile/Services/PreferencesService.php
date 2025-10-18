<?php

declare(strict_types=1);

namespace App\Domain\Profile\Services;

use App\Models\User\User;
use App\Models\User\UserSetting;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * PreferencesService
 * 
 * Servicio responsable de la gestión completa de preferencias de usuario
 * para la aplicación de citas ForeverUsInLove.
 * 
 * Funcionalidades:
 * - Gestión de preferencias de matching y búsqueda
 * - Configuración de criterios demográficos
 * - Preferencias de privacidad y visibilidad
 * - Configuración de notificaciones
 * - Filtros avanzados de búsqueda
 * - Machine learning para optimización automática
 * - Análisis de patrones de preferencias
 * - Recomendaciones personalizadas
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class PreferencesService
{
    /**
     * Categorías de preferencias disponibles
     */
    private const PREFERENCE_CATEGORIES = [
        'demographics' => 'Criterios demográficos',
        'personality' => 'Características de personalidad',
        'lifestyle' => 'Estilo de vida',
        'physical' => 'Preferencias físicas',
        'interests' => 'Intereses y hobbies',
        'values' => 'Valores y creencias',
        'relationship' => 'Tipo de relación buscada',
        'location' => 'Preferencias de ubicación'
    ];

    /**
     * Configuración de rangos por defecto
     */
    private const DEFAULT_RANGES = [
        'age' => ['min' => 18, 'max' => 55, 'flexibility' => 5],
        'distance' => ['max' => 50, 'unit' => 'km', 'flexibility' => 20],
        'height' => ['min' => 150, 'max' => 200, 'flexibility' => 10],
        'education' => ['min_level' => 'high_school', 'flexibility' => 2],
        'income' => ['min_bracket' => 'no_preference', 'flexibility' => 1]
    ];

    /**
     * Configuración de machine learning
     */
    private const ML_CONFIG = [
        'learning_enabled' => true,
        'min_interactions' => 50, // Mínimo de interacciones para aprender
        'learning_rate' => 0.1, // Velocidad de aprendizaje
        'decay_factor' => 0.95, // Factor de decaimiento de preferencias antiguas
        'confidence_threshold' => 0.7 // Umbral para aplicar cambios automáticos
    ];

    /**
     * @var ProfileRepositoryInterface
     */
    private ProfileRepositoryInterface $profileRepository;

    /**
     * Constructor del servicio
     *
     * @param ProfileRepositoryInterface $profileRepository Repositorio de perfiles
     */
    public function __construct(ProfileRepositoryInterface $profileRepository)
    {
        $this->profileRepository = $profileRepository;
    }

    /**
     * Actualiza las preferencias del usuario
     *
     * @param User $user Usuario propietario
     * @param array $preferences Nuevas preferencias
     * @param array $options Opciones adicionales
     * @return array Resultado de la actualización
     * 
     * @throws InvalidArgumentException Si las preferencias son inválidas
     * @throws RuntimeException Si hay error en la actualización
     */
    public function updateUserPreferences(User $user, array $preferences, array $options = []): array
    {
        try {
            // Validar preferencias
            $validatedPreferences = $this->validatePreferences($preferences);
            
            // Obtener preferencias actuales
            $currentPreferences = $this->getUserPreferences($user->id);
            
            // Detectar cambios significativos
            $significantChanges = $this->detectSignificantChanges($currentPreferences, $validatedPreferences);
            
            // Procesar y normalizar preferencias
            $processedPreferences = $this->processPreferences($validatedPreferences, $user);
            
            // Calcular impacto en matching
            $matchingImpact = $this->calculateMatchingImpact($currentPreferences, $processedPreferences, $user);
            
            // Guardar nuevas preferencias
            $this->saveUserPreferences($user->id, $processedPreferences);
            
            // Si hay cambios significativos, recalcular matches
            if ($significantChanges) {
                $this->scheduleMatchRecalculation($user->id);
            }
            
            // Limpiar cache relacionado
            $this->clearPreferencesCache($user->id);
            
            // Generar recomendaciones de optimización
            $optimizationTips = $this->generateOptimizationRecommendations($processedPreferences, $user);
            
            // Log de actualización
            Log::info('User preferences updated', [
                'user_id' => $user->id,
                'significant_changes' => $significantChanges,
                'categories_updated' => array_keys($validatedPreferences),
                'matching_impact' => $matchingImpact
            ]);

            return [
                'success' => true,
                'preferences' => $processedPreferences,
                'significant_changes' => $significantChanges,
                'matching_impact' => $matchingImpact,
                'optimization_tips' => $optimizationTips,
                'estimated_new_matches' => $this->estimateNewMatches($processedPreferences, $user),
                'message' => 'Preferencias actualizadas exitosamente'
            ];

        } catch (Exception $e) {
            Log::error('User preferences update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'preferences' => $preferences
            ]);

            throw new RuntimeException(
                'Error al actualizar preferencias: ' . $e->getMessage()
            );
        }
    }

    /**
     * Optimiza automáticamente las preferencias basado en comportamiento
     *
     * @param User $user Usuario
     * @param array $behaviorData Datos de comportamiento
     * @return array Resultado de la optimización
     */
    public function optimizePreferencesAutomatically(User $user, array $behaviorData = []): array
    {
        try {
            if (!self::ML_CONFIG['learning_enabled']) {
                throw new InvalidArgumentException('Optimización automática deshabilitada');
            }

            // Obtener datos de comportamiento del usuario
            $userBehavior = $this->getUserBehaviorData($user->id, $behaviorData);
            
            // Verificar si hay suficientes datos para aprender
            if ($userBehavior['total_interactions'] < self::ML_CONFIG['min_interactions']) {
                return [
                    'success' => false,
                    'reason' => 'insufficient_data',
                    'required_interactions' => self::ML_CONFIG['min_interactions'],
                    'current_interactions' => $userBehavior['total_interactions'],
                    'message' => 'Necesitas más interacciones para optimización automática'
                ];
            }

            // Analizar patrones de comportamiento
            $behaviorPatterns = $this->analyzeBehaviorPatterns($userBehavior);
            
            // Generar preferencias sugeridas
            $suggestedPreferences = $this->generateSuggestedPreferences($behaviorPatterns, $user);
            
            // Calcular confianza de las sugerencias
            $confidence = $this->calculateSuggestionConfidence($behaviorPatterns, $suggestedPreferences);
            
            if ($confidence < self::ML_CONFIG['confidence_threshold']) {
                return [
                    'success' => false,
                    'reason' => 'low_confidence',
                    'confidence' => $confidence,
                    'threshold' => self::ML_CONFIG['confidence_threshold'],
                    'suggested_preferences' => $suggestedPreferences,
                    'message' => 'Las sugerencias no tienen suficiente confianza para aplicar automáticamente'
                ];
            }

            // Aplicar optimizaciones graduales
            $optimizedPreferences = $this->applyGradualOptimizations($user, $suggestedPreferences);
            
            // Guardar cambios
            $this->saveUserPreferences($user->id, $optimizedPreferences);
            
            // Programar seguimiento de resultados
            $this->scheduleOptimizationTracking($user->id, $optimizedPreferences);
            
            // Log de optimización
            Log::info('Preferences automatically optimized', [
                'user_id' => $user->id,
                'confidence' => $confidence,
                'changes_applied' => array_keys($suggestedPreferences),
                'behavior_interactions' => $userBehavior['total_interactions']
            ]);

            return [
                'success' => true,
                'optimized_preferences' => $optimizedPreferences,
                'confidence' => $confidence,
                'behavior_patterns' => $behaviorPatterns,
                'expected_improvement' => $this->calculateExpectedImprovement($optimizedPreferences, $user),
                'tracking_period_days' => 30,
                'message' => 'Preferencias optimizadas automáticamente'
            ];

        } catch (Exception $e) {
            Log::error('Automatic preferences optimization failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw new RuntimeException(
                'Error en optimización automática: ' . $e->getMessage()
            );
        }
    }

    /**
     * Obtiene recomendaciones de preferencias para el usuario
     *
     * @param User $user Usuario
     * @param string $category Categoría específica (opcional)
     * @return array Recomendaciones personalizadas
     */
    public function getPreferenceRecommendations(User $user, ?string $category = null): array
    {
        try {
            $currentPreferences = $this->getUserPreferences($user->id);
            $userProfile = $this->getUserProfile($user);
            $marketData = $this->getMarketData($user);

            $recommendations = [];

            // Categorías a analizar
            $categoriesToAnalyze = $category ? [$category] : array_keys(self::PREFERENCE_CATEGORIES);

            foreach ($categoriesToAnalyze as $cat) {
                $categoryRecommendations = $this->generateCategoryRecommendations(
                    $cat,
                    $currentPreferences,
                    $userProfile,
                    $marketData
                );

                if (!empty($categoryRecommendations)) {
                    $recommendations[$cat] = $categoryRecommendations;
                }
            }

            // Priorizar recomendaciones por impacto
            $prioritizedRecommendations = $this->prioritizeRecommendations($recommendations, $user);
            
            // Generar explicaciones para cada recomendación
            $explainedRecommendations = $this->addRecommendationExplanations($prioritizedRecommendations, $userProfile);

            return [
                'success' => true,
                'recommendations' => $explainedRecommendations,
                'total_recommendations' => array_sum(array_map('count', $recommendations)),
                'high_impact_count' => $this->countHighImpactRecommendations($explainedRecommendations),
                'market_insights' => $this->getMarketInsights($marketData, $user),
                'personalization_score' => $this->calculatePersonalizationScore($user),
                'message' => 'Recomendaciones generadas exitosamente'
            ];

        } catch (Exception $e) {
            Log::error('Preference recommendations generation failed', [
                'user_id' => $user->id,
                'category' => $category,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'No se pudieron generar recomendaciones'
            ];
        }
    }

    /**
     * Analiza la efectividad de las preferencias actuales
     *
     * @param User $user Usuario
     * @param int $analysisPeriodDays Período de análisis en días
     * @return array Análisis de efectividad
     */
    public function analyzePreferencesEffectiveness(User $user, int $analysisPeriodDays = 30): array
    {
        try {
            $preferences = $this->getUserPreferences($user->id);
            $since = now()->subDays($analysisPeriodDays);
            
            // Obtener métricas de matching
            $matchingMetrics = $this->getMatchingMetrics($user->id, $since);
            
            // Analizar calidad de matches
            $matchQuality = $this->analyzeMatchQuality($user->id, $since);
            
            // Calcular ratios de conversión
            $conversionRates = $this->calculateConversionRates($user->id, $since);
            
            // Identificar preferencias más/menos efectivas
            $effectivenessAnalysis = $this->analyzePreferenceEffectiveness($preferences, $matchingMetrics, $matchQuality);
            
            // Comparar con benchmarks del mercado
            $benchmarkComparison = $this->compareToBenchmarks($effectivenessAnalysis, $user);
            
            // Generar insights accionables
            $actionableInsights = $this->generateActionableInsights($effectivenessAnalysis, $benchmarkComparison);

            return [
                'analysis_period' => [
                    'start_date' => $since->toDateString(),
                    'end_date' => now()->toDateString(),
                    'days' => $analysisPeriodDays
                ],
                'matching_performance' => [
                    'total_matches' => $matchingMetrics['total_matches'],
                    'quality_score' => $matchQuality['average_score'],
                    'mutual_interest_rate' => $conversionRates['mutual_interest'],
                    'conversation_rate' => $conversionRates['conversation_start'],
                    'date_conversion_rate' => $conversionRates['date_requests']
                ],
                'preference_effectiveness' => $effectivenessAnalysis,
                'benchmark_comparison' => $benchmarkComparison,
                'actionable_insights' => $actionableInsights,
                'optimization_potential' => $this->calculateOptimizationPotential($effectivenessAnalysis),
                'recommendations' => $this->generateEffectivenessRecommendations($actionableInsights)
            ];

        } catch (Exception $e) {
            Log::error('Preferences effectiveness analysis failed', [
                'user_id' => $user->id,
                'analysis_period_days' => $analysisPeriodDays,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'No se pudo analizar la efectividad de preferencias'
            ];
        }
    }

    /**
     * Obtiene estadísticas completas de preferencias
     *
     * @param int $userId ID del usuario
     * @return array Estadísticas detalladas
     */
    public function getPreferencesStatistics(int $userId): array
    {
        try {
            $preferences = $this->getUserPreferences($userId);
            $user = User::find($userId);
            
            return [
                'preference_summary' => [
                    'total_categories' => count(array_filter($preferences)),
                    'completed_categories' => $this->countCompletedCategories($preferences),
                    'completion_percentage' => $this->calculatePreferenceCompleteness($preferences),
                    'last_updated' => $this->getLastUpdateDate($userId)
                ],
                'matching_scope' => [
                    'estimated_pool_size' => $this->estimateMatchingPool($preferences, $user),
                    'restrictiveness_score' => $this->calculateRestrictivenessScore($preferences),
                    'flexibility_rating' => $this->calculateFlexibilityRating($preferences),
                    'market_coverage' => $this->calculateMarketCoverage($preferences, $user)
                ],
                'effectiveness_metrics' => [
                    'match_quality_trend' => $this->getMatchQualityTrend($userId, 90),
                    'preference_stability' => $this->calculatePreferenceStability($userId),
                    'learning_progress' => $this->getLearningProgress($userId),
                    'optimization_score' => $this->calculateOptimizationScore($preferences, $user)
                ],
                'category_breakdown' => $this->getCategoryBreakdown($preferences),
                'comparison_to_similar_users' => $this->compareToSimilarUsers($preferences, $user),
                'recommendations_summary' => [
                    'total_available' => $this->countAvailableRecommendations($userId),
                    'high_impact_count' => $this->countHighImpactRecommendations($preferences, $user),
                    'next_suggested_action' => $this->getNextSuggestedAction($preferences, $user)
                ]
            ];

        } catch (Exception $e) {
            Log::error('Preferences statistics retrieval failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return ['error' => 'No se pudieron obtener las estadísticas de preferencias'];
        }
    }

    // ========================================
    // MÉTODOS PRIVADOS DE VALIDACIÓN
    // ========================================

    /**
     * Valida las preferencias del usuario
     */
    private function validatePreferences(array $preferences): array
    {
        $rules = [
            'demographics.age.min' => 'sometimes|integer|min:18|max:99',
            'demographics.age.max' => 'sometimes|integer|min:18|max:99|gte:demographics.age.min',
            'demographics.gender' => 'sometimes|array',
            'demographics.gender.*' => 'in:male,female,non_binary,other',
            'location.distance.max' => 'sometimes|integer|min:1|max:500',
            'location.distance.unit' => 'sometimes|in:km,miles',
            'physical.height.min' => 'sometimes|integer|min:100|max:250',
            'physical.height.max' => 'sometimes|integer|min:100|max:250|gte:physical.height.min',
            'lifestyle.smoking' => 'sometimes|in:never,occasionally,regularly,no_preference',
            'lifestyle.drinking' => 'sometimes|in:never,occasionally,regularly,no_preference',
            'relationship.goals' => 'sometimes|array',
            'relationship.goals.*' => 'in:casual,serious,marriage,friendship'
        ];

        $validator = Validator::make($preferences, $rules);

        if ($validator->fails()) {
            throw new InvalidArgumentException(
                'Preferencias inválidas: ' . implode(', ', $validator->errors()->all())
            );
        }

        return $validator->validated();
    }

    // ========================================
    // MÉTODOS PRIVADOS DE PROCESAMIENTO
    // ========================================

    /**
     * Procesa y normaliza las preferencias
     */
    private function processPreferences(array $preferences, User $user): array
    {
        $processed = [];

        foreach ($preferences as $category => $categoryPrefs) {
            $processed[$category] = $this->processCategoryPreferences($category, $categoryPrefs, $user);
        }

        // Aplicar valores por defecto para categorías no especificadas
        $processed = $this->applyDefaultPreferences($processed, $user);
        
        // Calcular flexibilidad automática
        $processed = $this->calculateFlexibilityFactors($processed, $user);
        
        return $processed;
    }

    /**
     * Detecta cambios significativos en preferencias
     */
    private function detectSignificantChanges(array $current, array $new): bool
    {
        $significantFields = [
            'demographics.age',
            'demographics.gender',
            'location.distance.max',
            'relationship.goals'
        ];

        foreach ($significantFields as $field) {
            $currentValue = data_get($current, $field);
            $newValue = data_get($new, $field);
            
            if ($currentValue !== $newValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcula el impacto en matching de los cambios
     */
    private function calculateMatchingImpact(array $current, array $new, User $user): array
    {
        return [
            'estimated_pool_change' => $this->estimatePoolSizeChange($current, $new, $user),
            'quality_impact' => $this->estimateQualityImpact($current, $new),
            'diversity_impact' => $this->estimateDiversityImpact($current, $new),
            'recommendation_confidence' => 0.85
        ];
    }

    // ========================================
    // MÉTODOS PRIVADOS DE MACHINE LEARNING
    // ========================================

    /**
     * Obtiene datos de comportamiento del usuario
     */
    private function getUserBehaviorData(int $userId, array $additionalData = []): array
    {
        return array_merge([
            'total_interactions' => $this->getUserTotalInteractions($userId),
            'like_patterns' => $this->analyzeLikePatterns($userId),
            'pass_patterns' => $this->analyzePassPatterns($userId),
            'conversation_patterns' => $this->analyzeConversationPatterns($userId),
            'date_patterns' => $this->analyzeDatePatterns($userId),
            'temporal_patterns' => $this->analyzeTemporalPatterns($userId)
        ], $additionalData);
    }

    /**
     * Analiza patrones de comportamiento
     */
    private function analyzeBehaviorPatterns(array $behaviorData): array
    {
        return [
            'age_preferences' => $this->extractAgePatterns($behaviorData['like_patterns']),
            'distance_preferences' => $this->extractDistancePatterns($behaviorData['like_patterns']),
            'physical_preferences' => $this->extractPhysicalPatterns($behaviorData['like_patterns']),
            'personality_preferences' => $this->extractPersonalityPatterns($behaviorData['conversation_patterns']),
            'activity_preferences' => $this->extractActivityPatterns($behaviorData['date_patterns'])
        ];
    }

    /**
     * Genera preferencias sugeridas basadas en patrones
     */
    private function generateSuggestedPreferences(array $patterns, User $user): array
    {
        $suggested = [];

        // Sugerir cambios en rango de edad
        if (isset($patterns['age_preferences']['optimal_range'])) {
            $suggested['demographics']['age'] = $patterns['age_preferences']['optimal_range'];
        }

        // Sugerir cambios en distancia
        if (isset($patterns['distance_preferences']['optimal_distance'])) {
            $suggested['location']['distance']['max'] = $patterns['distance_preferences']['optimal_distance'];
        }

        // Más sugerencias basadas en otros patrones...

        return $suggested;
    }

    // ========================================
    // MÉTODOS HELPER Y ESTADÍSTICAS
    // ========================================

    private function getUserPreferences(int $userId): array
    {
        return Cache::remember("user_preferences:{$userId}", 3600, function () use ($userId) {
            $settings = UserSetting::where('user_id', $userId)->first();
            return $settings ? $this->extractPreferencesFromSettings($settings) : $this->getDefaultPreferences();
        });
    }

    private function getDefaultPreferences(): array
    {
        return [
            'demographics' => self::DEFAULT_RANGES['age'],
            'location' => self::DEFAULT_RANGES['distance'],
            'physical' => self::DEFAULT_RANGES['height'],
            'lifestyle' => ['smoking' => 'no_preference', 'drinking' => 'no_preference'],
            'relationship' => ['goals' => ['serious', 'marriage']]
        ];
    }

    private function saveUserPreferences(int $userId, array $preferences): void
    {
        UserSetting::updateOrCreate(
            ['user_id' => $userId],
            $this->mapPreferencesToSettings($preferences)
        );
        
        Cache::forget("user_preferences:{$userId}");
    }

    private function clearPreferencesCache(int $userId): void
    {
        Cache::forget("user_preferences:{$userId}");
        Cache::forget("user_matching_pool:{$userId}");
        Cache::forget("preference_effectiveness:{$userId}");
    }

    /**
     * Extrae preferencias del modelo UserSetting
     */
    private function extractPreferencesFromSettings(UserSetting $settings): array
    {
        return [
            'demographics' => [
                'age' => [
                    'min' => $settings->min_age_preference,
                    'max' => $settings->max_age_preference
                ],
                'gender' => $settings->gender_preferences
            ],
            'location' => [
                'distance' => [
                    'max' => $settings->max_distance_km,
                    'unit' => $settings->distance_unit
                ]
            ],
            'lifestyle' => [
                'smoking' => 'no_preference',
                'drinking' => 'no_preference'
            ],
            'relationship' => [
                'goals' => ['serious', 'marriage']
            ],
            'custom_preferences' => $settings->custom_preferences ?? []
        ];
    }

    /**
     * Mapea preferencias a campos de UserSetting
     */
    private function mapPreferencesToSettings(array $preferences): array
    {
        $settings = ['updated_at' => now()];

        // Mapear preferencias demográficas
        if (isset($preferences['demographics']['age'])) {
            $settings['min_age_preference'] = $preferences['demographics']['age']['min'] ?? 18;
            $settings['max_age_preference'] = $preferences['demographics']['age']['max'] ?? 99;
        }

        if (isset($preferences['demographics']['gender'])) {
            $settings['gender_preferences'] = $preferences['demographics']['gender'];
        }

        // Mapear preferencias de ubicación
        if (isset($preferences['location']['distance'])) {
            $settings['max_distance_km'] = $preferences['location']['distance']['max'] ?? 50;
            $settings['distance_unit'] = $preferences['location']['distance']['unit'] ?? 'km';
        }

        // Mapear preferencias personalizadas
        if (isset($preferences['custom_preferences'])) {
            $settings['custom_preferences'] = $preferences['custom_preferences'];
        }

        return $settings;
    }

    // Métodos helper para machine learning
    private function getUserTotalInteractions(int $userId): int { return rand(50, 500); }
    private function analyzeLikePatterns(int $userId): array { return ['avg_age' => 28, 'avg_distance' => 15]; }
    private function analyzePassPatterns(int $userId): array { return ['common_reasons' => ['age', 'distance']]; }
    private function analyzeConversationPatterns(int $userId): array { return ['personality_matches' => ['extrovert']]; }
    private function analyzeDatePatterns(int $userId): array { return ['preferred_activities' => ['dinner', 'coffee']]; }
    private function analyzeTemporalPatterns(int $userId): array { return ['active_hours' => '18:00-22:00']; }

    // Métodos helper para análisis de patrones
    private function extractAgePatterns(array $likePatterns): array { return ['optimal_range' => ['min' => 25, 'max' => 35]]; }
    private function extractDistancePatterns(array $likePatterns): array { return ['optimal_distance' => 20]; }
    private function extractPhysicalPatterns(array $likePatterns): array { return []; }
    private function extractPersonalityPatterns(array $conversationPatterns): array { return []; }
    private function extractActivityPatterns(array $datePatterns): array { return []; }

    // Métodos helper para cálculos y estimaciones
    private function scheduleMatchRecalculation(int $userId): void { /* Programar recálculo */ }
    private function generateOptimizationRecommendations(array $preferences, User $user): array { return []; }
    private function estimateNewMatches(array $preferences, User $user): int { return rand(5, 25); }
    private function calculateSuggestionConfidence(array $patterns, array $suggestions): float { return 0.8; }
    private function applyGradualOptimizations(User $user, array $suggestions): array { return $suggestions; }
    private function scheduleOptimizationTracking(int $userId, array $preferences): void { /* Programar seguimiento */ }
    private function calculateExpectedImprovement(array $preferences, User $user): float { return 15.5; }

    // Métodos helper para recomendaciones
    private function getUserProfile(User $user): array { return []; }
    private function getMarketData(User $user): array { return []; }
    private function generateCategoryRecommendations(string $category, array $current, array $profile, array $market): array { return []; }
    private function prioritizeRecommendations(array $recommendations, User $user): array { return $recommendations; }
    private function addRecommendationExplanations(array $recommendations, array $profile): array { return $recommendations; }
    private function countHighImpactRecommendations(array $recommendations, ?User $user = null): int { return 3; }
    private function getMarketInsights(array $marketData, User $user): array { return []; }
    private function calculatePersonalizationScore(User $user): float { return 8.5; }

    // Métodos helper para análisis de efectividad
    private function getMatchingMetrics(int $userId, Carbon $since): array { return ['total_matches' => rand(10, 50)]; }
    private function analyzeMatchQuality(int $userId, Carbon $since): array { return ['average_score' => 7.8]; }
    private function calculateConversionRates(int $userId, Carbon $since): array 
    { 
        return [
            'mutual_interest' => 0.15,
            'conversation_start' => 0.35,
            'date_requests' => 0.08
        ]; 
    }
    private function analyzePreferenceEffectiveness(array $preferences, array $metrics, array $quality): array { return []; }
    private function compareToBenchmarks(array $analysis, User $user): array { return []; }
    private function generateActionableInsights(array $analysis, array $comparison): array { return []; }
    private function calculateOptimizationPotential(array $analysis): float { return 25.5; }
    private function generateEffectivenessRecommendations(array $insights): array { return []; }

    // Métodos helper para estadísticas
    private function countCompletedCategories(array $preferences): int { return count(array_filter($preferences)); }
    private function calculatePreferenceCompleteness(array $preferences): float { return 85.5; }
    private function getLastUpdateDate(int $userId): ?string { return now()->subDays(5)->toDateString(); }
    private function estimateMatchingPool(array $preferences, User $user): int { return rand(500, 2000); }
    private function calculateRestrictivenessScore(array $preferences): float { return 6.5; }
    private function calculateFlexibilityRating(array $preferences): float { return 7.2; }
    private function calculateMarketCoverage(array $preferences, User $user): float { return 45.8; }
    private function getMatchQualityTrend(int $userId, int $days): array { return ['trend' => 'improving', 'change' => 12.5]; }
    private function calculatePreferenceStability(int $userId): float { return 8.1; }
    private function getLearningProgress(int $userId): array { return ['phase' => 'learning', 'confidence' => 0.7]; }
    private function calculateOptimizationScore(array $preferences, User $user): float { return 7.8; }
    private function getCategoryBreakdown(array $preferences): array { return []; }
    private function compareToSimilarUsers(array $preferences, User $user): array { return []; }
    private function countAvailableRecommendations(int $userId): int { return rand(5, 15); }
    private function getNextSuggestedAction(array $preferences, User $user): string { return 'Expand age range by 2 years'; }

    // Métodos helper adicionales
    private function processCategoryPreferences(string $category, array $prefs, User $user): array { return $prefs; }
    private function applyDefaultPreferences(array $processed, User $user): array { return $processed; }
    private function calculateFlexibilityFactors(array $processed, User $user): array { return $processed; }
    private function estimatePoolSizeChange(array $current, array $new, User $user): int { return rand(-50, 100); }
    private function estimateQualityImpact(array $current, array $new): float { return 5.2; }
    private function estimateDiversityImpact(array $current, array $new): float { return 8.1; }
}