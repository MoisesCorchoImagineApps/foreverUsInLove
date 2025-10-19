<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Domain\Profile\Services\PreferencesService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * PreferencesController
 * 
 * Controlador responsable de la gestión de preferencias de usuario.
 * 
 * Endpoints:
 * - GET    /preferences           - Obtener preferencias del usuario
 * - PUT    /preferences           - Actualizar preferencias
 * - POST   /preferences/optimize  - Optimizar preferencias automáticamente
 * - GET    /preferences/recommendations - Obtener recomendaciones de preferencias
 * - GET    /preferences/effectiveness  - Analizar efectividad de preferencias
 * - GET    /preferences/stats     - Obtener estadísticas de preferencias
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class PreferencesController extends Controller
{
    use ApiResponseTrait;

    /**
     * Servicio de gestión de preferencias
     */
    private PreferencesService $preferencesService;

    /**
     * Constructor del controlador
     *
     * @param PreferencesService $preferencesService
     */
    public function __construct(PreferencesService $preferencesService)
    {
        $this->preferencesService = $preferencesService;
    }

    /**
     * Obtener preferencias del usuario
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "preferences": {...},
     *     "matching_scope": {...},
     *     "last_updated": "2024-01-15T10:30:00Z"
     *   }
     * }
     */
    public function show(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            
            // Verificar que tenga perfil
            if (!$user->profile) {
                return $this->errorResponse(
                    'Debes crear un perfil antes de gestionar preferencias',
                    403
                );
            }

            // Obtener preferencias
            $preferences = $user->preferences ?? [];
            
            // Calcular métricas adicionales
            $matchingScope = [
                'estimated_pool_size' => rand(500, 2000), // Mock
                'restrictiveness_score' => 6.5,
                'flexibility_rating' => 7.2
            ];

            Log::info('User preferences retrieved', [
                'user_id' => $user->id
            ]);

            return $this->successResponse([
                'preferences' => $preferences,
                'matching_scope' => $matchingScope,
                'last_updated' => $user->preferences_updated_at ?? now()
            ], 'Preferencias obtenidas exitosamente');

        } catch (Exception $e) {
            Log::error('Preferences retrieval failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Error al obtener preferencias',
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }

    /**
     * Actualizar preferencias del usuario
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Preferencias actualizadas exitosamente",
     *   "data": {
     *     "preferences": {...},
     *     "matching_impact": {...},
     *     "optimization_tips": [...]
     *   }
     * }
     */
    public function update(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            
            // Verificar que tenga perfil
            if (!$user->profile) {
                return $this->errorResponse(
                    'Debes crear un perfil antes de gestionar preferencias',
                    403
                );
            }

            // Validar preferencias
            $validated = $request->validate([
                'demographics' => 'nullable|array',
                'demographics.age' => 'nullable|array',
                'demographics.age.min' => 'nullable|integer|min:18|max:99',
                'demographics.age.max' => 'nullable|integer|min:18|max:99|gte:demographics.age.min',
                'demographics.gender' => 'nullable|array',
                'demographics.gender.*' => 'in:male,female,non_binary,other',
                
                'location' => 'nullable|array',
                'location.distance' => 'nullable|array',
                'location.distance.max' => 'nullable|integer|min:1|max:500',
                'location.distance.unit' => 'nullable|in:km,miles',
                
                'physical' => 'nullable|array',
                'physical.height' => 'nullable|array',
                'physical.height.min' => 'nullable|integer|min:100|max:250',
                'physical.height.max' => 'nullable|integer|min:100|max:250|gte:physical.height.min',
                
                'lifestyle' => 'nullable|array',
                'lifestyle.smoking' => 'nullable|in:never,occasionally,regularly,no_preference',
                'lifestyle.drinking' => 'nullable|in:never,occasionally,regularly,no_preference',
                
                'relationship' => 'nullable|array',
                'relationship.goals' => 'nullable|array',
                'relationship.goals.*' => 'in:casual,serious,marriage,friendship'
            ]);

            // Actualizar preferencias
            $result = $this->preferencesService->updateUserPreferences(
                $user,
                $validated
            );

            Log::info('User preferences updated', [
                'user_id' => $user->id,
                'significant_changes' => $result['significant_changes'] ?? false,
                'categories_updated' => array_keys($validated)
            ]);

            return $this->successResponse(
                $result,
                $result['message']
            );

        } catch (Exception $e) {
            Log::error('Preferences update failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Error al actualizar preferencias: ' . $e->getMessage(),
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }

    /**
     * Optimizar preferencias automáticamente basado en comportamiento
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Preferencias optimizadas automáticamente",
     *   "data": {
     *     "optimized_preferences": {...},
     *     "confidence": 0.85,
     *     "expected_improvement": 15.5
     *   }
     * }
     */
    public function optimize(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            
            // Verificar que tenga perfil
            if (!$user->profile) {
                return $this->errorResponse(
                    'Debes crear un perfil antes de optimizar preferencias',
                    403
                );
            }

            // Validar datos de comportamiento opcionales
            $validated = $request->validate([
                'behavior_data' => 'nullable|array'
            ]);

            // Optimizar preferencias
            $result = $this->preferencesService->optimizePreferencesAutomatically(
                $user,
                $validated['behavior_data'] ?? []
            );

            // Si la optimización fue exitosa
            if ($result['success']) {
                Log::info('Preferences optimized automatically', [
                    'user_id' => $user->id,
                    'confidence' => $result['confidence'] ?? 0,
                    'changes_applied' => array_keys($result['optimized_preferences'] ?? [])
                ]);

                return $this->successResponse(
                    $result,
                    $result['message']
                );
            }

            // Si no se pudo optimizar
            Log::info('Preferences optimization not applied', [
                'user_id' => $user->id,
                'reason' => $result['reason'] ?? 'unknown'
            ]);

            return $this->successResponse(
                $result,
                $result['message'],
                202 // Accepted pero no aplicado
            );

        } catch (Exception $e) {
            Log::error('Preferences optimization failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Error al optimizar preferencias: ' . $e->getMessage(),
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }

    /**
     * Obtener recomendaciones de preferencias
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "recommendations": {...},
     *     "total_recommendations": 5,
     *     "high_impact_count": 2,
     *     "market_insights": {...}
     *   }
     * }
     */
    public function recommendations(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            
            // Verificar que tenga perfil
            if (!$user->profile) {
                return $this->errorResponse(
                    'Debes crear un perfil antes de obtener recomendaciones',
                    403
                );
            }

            // Validar categoría opcional
            $validated = $request->validate([
                'category' => 'nullable|string|in:demographics,personality,lifestyle,physical,interests,values,relationship,location'
            ]);

            // Obtener recomendaciones
            $result = $this->preferencesService->getPreferenceRecommendations(
                $user,
                $validated['category'] ?? null
            );

            Log::info('Preference recommendations retrieved', [
                'user_id' => $user->id,
                'category' => $validated['category'] ?? 'all',
                'recommendations_count' => $result['total_recommendations'] ?? 0
            ]);

            return $this->successResponse(
                $result,
                $result['message']
            );

        } catch (Exception $e) {
            Log::error('Preference recommendations retrieval failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Error al obtener recomendaciones',
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }

    /**
     * Analizar efectividad de preferencias actuales
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "analysis_period": {...},
     *     "matching_performance": {...},
     *     "preference_effectiveness": {...},
     *     "actionable_insights": [...]
     *   }
     * }
     */
    public function effectiveness(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            
            // Verificar que tenga perfil
            if (!$user->profile) {
                return $this->errorResponse(
                    'Debes crear un perfil antes de analizar efectividad',
                    403
                );
            }

            // Validar período de análisis
            $validated = $request->validate([
                'analysis_period_days' => 'nullable|integer|min:7|max:90'
            ]);

            // Analizar efectividad
            $result = $this->preferencesService->analyzePreferencesEffectiveness(
                $user,
                $validated['analysis_period_days'] ?? 30
            );

            Log::info('Preferences effectiveness analyzed', [
                'user_id' => $user->id,
                'analysis_period_days' => $validated['analysis_period_days'] ?? 30,
                'total_matches' => $result['matching_performance']['total_matches'] ?? 0
            ]);

            return $this->successResponse(
                $result,
                'Análisis de efectividad completado'
            );

        } catch (Exception $e) {
            Log::error('Preferences effectiveness analysis failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Error al analizar efectividad',
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }

    /**
     * Obtener estadísticas de preferencias
     *
     * @param Request $request
     * @return JsonResponse
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "preference_summary": {...},
     *     "matching_scope": {...},
     *     "effectiveness_metrics": {...},
     *     "recommendations_summary": {...}
     *   }
     * }
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            
            // Verificar que tenga perfil
            if (!$user->profile) {
                return $this->errorResponse(
                    'Debes crear un perfil antes de obtener estadísticas',
                    403
                );
            }

            // Obtener estadísticas
            $stats = $this->preferencesService->getPreferencesStatistics($user->id);

            Log::info('Preferences statistics retrieved', [
                'user_id' => $user->id,
                'completion_percentage' => $stats['preference_summary']['completion_percentage'] ?? 0
            ]);

            return $this->successResponse(
                $stats,
                'Estadísticas obtenidas exitosamente'
            );

        } catch (Exception $e) {
            Log::error('Preferences statistics retrieval failed', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Error al obtener estadísticas',
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }
}