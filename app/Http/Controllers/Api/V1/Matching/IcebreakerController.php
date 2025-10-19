<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Matching;

use App\Http\Controllers\Controller;
use App\Domain\Matching\Services\FilterService;
use App\Infrastructure\External\GoogleMapsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Auth, Log, DB, Cache, RateLimiter};
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * FilterController - Gestión de Filtros de Búsqueda y Presets
 * 
 * @package ForeverUsInLove - Matching Module
 * @version 2.0.0 - Refactored with DDD + Clean Architecture
 * 
 * Funcionalidades:
 * - Aplicación de filtros de búsqueda multi-criterio
 * - Gestión de presets de filtros personalizados
 * - Filtrado geográfico con GoogleMapsService
 * - Analytics de uso de filtros
 * 
 * Integraciones:
 * - FilterService (Domain Service)
 * - GoogleMapsService (Filtrado geográfico y radio de búsqueda)
 * 
 * Filter Categories:
 * - Basic: edad, género, distancia
 * - Demographic: educación, ocupación, ingresos
 * - Lifestyle: hábitos, actividades, intereses
 * - Interest: hobbies, pasatiempos específicos
 * - Relationship: objetivos, planes familiares
 * - Premium: verificados, activos recientes, popularidad
 * - Behavioral: actividad en app, ratio de respuesta
 * - Custom: filtros definidos por el usuario
 * 
 * Preset Limits:
 * - Standard: 5 presets máximo
 * - Premium: 20 presets máximo
 * 
 * Business Rules:
 * - Distancia máxima: 50km standard, 500km premium
 * - Filtros premium solo para usuarios premium
 * - Presets tienen nombres únicos por usuario
 * - Caché de resultados: 30 minutos
 */
class FilterController extends Controller
{
    /**
     * Constructor con inyección de dependencias
     */
    public function __construct(
        private readonly FilterService $filterService,
        private readonly GoogleMapsService $googleMapsService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('verified');
        
        Log::info('FilterController initialized', [
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Aplicar filtros de búsqueda para obtener usuarios que coincidan
     * 
     * POST /api/matching/filters/apply
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function applyFilters(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('FilterController::applyFilters - Starting filter application', [
            'user_id' => $userId,
            'request_data' => $request->all()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                // Basic Filters
                'age_min' => 'nullable|integer|min:18|max:99',
                'age_max' => 'nullable|integer|min:18|max:99',
                'gender' => 'nullable|array',
                'gender.*' => 'string|in:male,female,non_binary,other',
                'distance_km' => 'nullable|integer|min:1|max:500',
                'location' => 'nullable|array',
                'location.latitude' => 'required_with:location|numeric|between:-90,90',
                'location.longitude' => 'required_with:location|numeric|between:-180,180',
                
                // Demographic Filters
                'education_level' => 'nullable|array',
                'education_level.*' => 'string|in:high_school,bachelors,masters,phd,other',
                'occupation' => 'nullable|array',
                'occupation.*' => 'string',
                'income_range' => 'nullable|string|in:0-30k,30-60k,60-100k,100k+',
                
                // Lifestyle Filters
                'lifestyle' => 'nullable|array',
                'lifestyle.*' => 'string|in:active,moderate,relaxed,adventurous,homebody',
                'drinking' => 'nullable|array',
                'drinking.*' => 'string|in:never,occasionally,socially,regularly',
                'smoking' => 'nullable|array',
                'smoking.*' => 'string|in:never,occasionally,regularly',
                'exercise_frequency' => 'nullable|array',
                'exercise_frequency.*' => 'string|in:never,rarely,sometimes,often,daily',
                
                // Interest Filters
                'interests' => 'nullable|array',
                'interests.*' => 'string',
                'hobbies' => 'nullable|array',
                'hobbies.*' => 'string',
                
                // Relationship Filters
                'relationship_goals' => 'nullable|array',
                'relationship_goals.*' => 'string|in:casual,dating,serious,marriage',
                'wants_children' => 'nullable|array',
                'wants_children.*' => 'string|in:yes,no,maybe,have_children',
                'family_plans' => 'nullable|array',
                'family_plans.*' => 'string',
                
                // Premium Filters (require premium)
                'verified_only' => 'nullable|boolean',
                'active_recently' => 'nullable|boolean',
                'min_popularity_score' => 'nullable|integer|min:0|max:100',
                'has_photos_count' => 'nullable|integer|min:1|max:10',
                
                // Behavioral Filters
                'min_response_rate' => 'nullable|integer|min:0|max:100',
                'active_within_hours' => 'nullable|integer|min:1|max:720',
                
                // Pagination & Sorting
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort' => 'nullable|string|in:relevance,distance,recent,popular',
                
                // Options
                'save_as_preset' => 'nullable|boolean',
                'preset_name' => 'nullable|string|max:100',
                'use_cache' => 'nullable|boolean'
            ]);

            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 20;
            $sort = $validated['sort'] ?? 'relevance';
            $useCache = $validated['use_cache'] ?? true;

            // Verificar premium status
            $isPremium = $this->checkPremiumStatus($userId);

            // Validar filtros premium
            $premiumFiltersUsed = [
                'verified_only', 'active_recently', 'min_popularity_score', 
                'has_photos_count', 'min_response_rate', 'active_within_hours'
            ];
            
            foreach ($premiumFiltersUsed as $premiumFilter) {
                if (isset($validated[$premiumFilter]) && !$isPremium) {
                    Log::warning('FilterController::applyFilters - Non-premium user attempting premium filter', [
                        'user_id' => $userId,
                        'filter' => $premiumFilter
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => "El filtro '{$premiumFilter}' solo está disponible para usuarios Premium",
                        'error_code' => 'PREMIUM_FILTER_REQUIRED',
                        'upgrade_url' => route('premium.plans')
                    ], 403);
                }
            }

            // Ajustar distancia máxima según plan
            if (isset($validated['distance_km'])) {
                $maxDistance = $isPremium ? 500 : 50;
                if ($validated['distance_km'] > $maxDistance) {
                    $validated['distance_km'] = $maxDistance;
                    
                    Log::info('FilterController::applyFilters - Distance adjusted to plan limit', [
                        'user_id' => $userId,
                        'requested' => $request->input('distance_km'),
                        'adjusted_to' => $maxDistance
                    ]);
                }
            }

            // Preparar ubicación del usuario para filtrado geográfico
            $userLocation = null;
            if (isset($validated['location'])) {
                $userLocation = [
                    'latitude' => $validated['location']['latitude'],
                    'longitude' => $validated['location']['longitude']
                ];
            } else {
                // Obtener ubicación guardada del usuario
                $userProfile = $this->getUserProfile($userId);
                if ($userProfile['has_location']) {
                    $userLocation = [
                        'latitude' => $userProfile['latitude'],
                        'longitude' => $userProfile['longitude']
                    ];
                }
            }

            // Verificar caché si está habilitado
            if ($useCache) {
                $cacheKey = $this->generateFilterCacheKey($userId, $validated, $page);
                $cacheTTL = 1800; // 30 minutos

                if (Cache::has($cacheKey)) {
                    $cachedResults = Cache::get($cacheKey);
                    
                    Log::info('FilterController::applyFilters - Returning cached results', [
                        'user_id' => $userId,
                        'results_count' => count($cachedResults['data'])
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Resultados obtenidos de caché',
                        'data' => $cachedResults,
                        'meta' => [
                            'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                            'timestamp' => now()->toISOString(),
                            'from_cache' => true
                        ]
                    ], 200);
                }
            }

            DB::beginTransaction();

            try {
                // Paso 1: Aplicar filtros básicos a través del domain service
                $filteredUsers = $this->filterService->applyFilters(
                    userId: $userId,
                    filters: $validated,
                    page: $page,
                    perPage: $perPage * 2 // Obtenemos más para filtrar por distancia
                );

                Log::info('FilterController::applyFilters - Basic filters applied', [
                    'user_id' => $userId,
                    'filtered_count' => count($filteredUsers['data'])
                ]);

                // Paso 2: Filtrar por distancia geográfica usando GoogleMapsService
                if (isset($validated['distance_km']) && $userLocation) {
                    $radiusKm = $validated['distance_km'];
                    
                    // Usar GoogleMapsService para filtrado geográfico eficiente
                    $usersWithinRadius = $this->googleMapsService->filterUsersWithinRadius(
                        centerLat: $userLocation['latitude'],
                        centerLng: $userLocation['longitude'],
                        radiusKm: $radiusKm,
                        users: $filteredUsers['data']
                    );

                    Log::info('FilterController::applyFilters - Geographic filtering applied', [
                        'user_id' => $userId,
                        'before_count' => count($filteredUsers['data']),
                        'after_count' => count($usersWithinRadius),
                        'radius_km' => $radiusKm
                    ]);

                    // Calcular distancia exacta para cada usuario
                    foreach ($usersWithinRadius as &$user) {
                        if (isset($user['latitude']) && isset($user['longitude'])) {
                            $distance = $this->googleMapsService->calculateDistance(
                                lat1: $userLocation['latitude'],
                                lng1: $userLocation['longitude'],
                                lat2: $user['latitude'],
                                lng2: $user['longitude'],
                                unit: 'km'
                            );
                            $user['distance_km'] = round($distance, 1);
                        }
                    }

                    $filteredUsers['data'] = $usersWithinRadius;
                }

                // Paso 3: Aplicar sorting
                $sortedUsers = $this->applySorting($filteredUsers['data'], $sort, $userLocation);

                // Paso 4: Paginar resultados
                $totalResults = count($sortedUsers);
                $paginatedUsers = array_slice($sortedUsers, 0, $perPage);

                // Paso 5: Formatear resultados
                $formattedUsers = array_map(function ($user) {
                    return $this->formatUserData($user);
                }, $paginatedUsers);

                $results = [
                    'data' => $formattedUsers,
                    'pagination' => [
                        'total' => $totalResults,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'last_page' => ceil($totalResults / $perPage),
                        'has_more' => $totalResults > $perPage
                    ],
                    'filters_applied' => $this->getAppliedFiltersCount($validated),
                    'active_filters' => $this->formatActiveFilters($validated)
                ];

                // Cachear resultados
                if ($useCache) {
                    $cacheKey = $this->generateFilterCacheKey($userId, $validated, $page);
                    Cache::put($cacheKey, $results, 1800);
                }

                // Guardar como preset si se solicita
                if ($validated['save_as_preset'] ?? false) {
                    if (!isset($validated['preset_name'])) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Debes proporcionar un nombre para el preset',
                            'error_code' => 'PRESET_NAME_REQUIRED'
                        ], 422);
                    }

                    $presetResult = $this->filterService->savePreset(
                        userId: $userId,
                        presetName: $validated['preset_name'],
                        filters: $validated
                    );

                    if (!$presetResult['success']) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => $presetResult['message'],
                            'error_code' => $presetResult['error_code']
                        ], 422);
                    }

                    $results['preset_saved'] = true;
                    $results['preset_id'] = $presetResult['preset_id'];
                }

                DB::commit();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('FilterController::applyFilters - Filters applied successfully', [
                    'user_id' => $userId,
                    'total_results' => $totalResults,
                    'filters_count' => $results['filters_applied'],
                    'execution_time_ms' => $executionTime
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Filtros aplicados correctamente',
                    'data' => $results,
                    'meta' => [
                        'execution_time_ms' => $executionTime,
                        'timestamp' => now()->toISOString(),
                        'from_cache' => false
                    ]
                ], 200);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            Log::warning('FilterController::applyFilters - Validation error', [
                'user_id' => $userId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('FilterController::applyFilters - Error applying filters', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al aplicar filtros. Por favor intenta de nuevo.',
                'error_code' => 'APPLY_FILTERS_ERROR'
            ], 500);
        }
    }

    /**
     * Guardar preset de filtros personalizado
     * 
     * POST /api/matching/filters/presets
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function savePreset(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('FilterController::savePreset - Starting preset creation', [
            'user_id' => $userId,
            'request_data' => $request->all()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'filters' => 'required|array',
                'is_default' => 'nullable|boolean',
                'description' => 'nullable|string|max:255'
            ]);

            // Verificar límite de presets según plan
            $isPremium = $this->checkPremiumStatus($userId);
            $maxPresets = $isPremium ? 20 : 5;

            $currentPresetsCount = $this->filterService->getUserPresetsCount($userId);

            if ($currentPresetsCount >= $maxPresets) {
                Log::warning('FilterController::savePreset - Preset limit reached', [
                    'user_id' => $userId,
                    'current_count' => $currentPresetsCount,
                    'limit' => $maxPresets
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Has alcanzado tu límite de {$maxPresets} presets",
                    'error_code' => 'PRESET_LIMIT_REACHED',
                    'limit' => $maxPresets,
                    'current_count' => $currentPresetsCount,
                    'upgrade_available' => !$isPremium
                ], 429);
            }

            DB::beginTransaction();

            try {
                // Crear preset a través del domain service
                $presetResult = $this->filterService->savePreset(
                    userId: $userId,
                    presetName: $validated['name'],
                    filters: $validated['filters'],
                    isDefault: $validated['is_default'] ?? false,
                    description: $validated['description'] ?? null
                );

                if (!$presetResult['success']) {
                    DB::rollBack();

                    Log::warning('FilterController::savePreset - Preset creation failed', [
                        'user_id' => $userId,
                        'reason' => $presetResult['message']
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $presetResult['message'],
                        'error_code' => $presetResult['error_code']
                    ], 422);
                }

                DB::commit();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('FilterController::savePreset - Preset created successfully', [
                    'user_id' => $userId,
                    'preset_id' => $presetResult['preset_id'],
                    'preset_name' => $validated['name'],
                    'execution_time_ms' => $executionTime
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Preset guardado correctamente',
                    'data' => [
                        'preset_id' => $presetResult['preset_id'],
                        'name' => $validated['name'],
                        'filters_count' => count($validated['filters']),
                        'is_default' => $validated['is_default'] ?? false,
                        'created_at' => now()->toISOString()
                    ],
                    'meta' => [
                        'execution_time_ms' => $executionTime,
                        'timestamp' => now()->toISOString()
                    ]
                ], 201);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            Log::warning('FilterController::savePreset - Validation error', [
                'user_id' => $userId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('FilterController::savePreset - Error saving preset', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar preset',
                'error_code' => 'SAVE_PRESET_ERROR'
            ], 500);
        }
    }

    /**
     * Obtener presets de filtros del usuario
     * 
     * GET /api/matching/filters/presets
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserPresets(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('FilterController::getUserPresets - Fetching user presets', [
            'user_id' => $userId
        ]);

        try {
            // Obtener presets con caché
            $cacheKey = "user_filter_presets:{$userId}";
            $cacheTTL = 3600; // 1 hora

            $presets = Cache::remember($cacheKey, $cacheTTL, function () use ($userId) {
                return $this->filterService->getUserPresets($userId);
            });

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('FilterController::getUserPresets - Presets fetched successfully', [
                'user_id' => $userId,
                'presets_count' => count($presets),
                'execution_time_ms' => $executionTime
            ]);

            // Verificar límites
            $isPremium = $this->checkPremiumStatus($userId);
            $maxPresets = $isPremium ? 20 : 5;

            return response()->json([
                'success' => true,
                'message' => 'Presets obtenidos correctamente',
                'data' => [
                    'presets' => array_map(fn($preset) => $this->formatPresetData($preset), $presets),
                    'total_presets' => count($presets),
                    'limits' => [
                        'max_presets' => $maxPresets,
                        'remaining' => max(0, $maxPresets - count($presets))
                    ]
                ],
                'meta' => [
                    'execution_time_ms' => $executionTime,
                    'timestamp' => now()->toISOString(),
                    'cached' => true
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('FilterController::getUserPresets - Error fetching presets', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener presets',
                'error_code' => 'FETCH_PRESETS_ERROR'
            ], 500);
        }
    }

    /**
     * Actualizar preset existente
     * 
     * PUT /api/matching/filters/presets/{presetId}
     * 
     * @param Request $request
     * @param int $presetId
     * @return JsonResponse
     */
    public function updatePreset(Request $request, int $presetId): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('FilterController::updatePreset - Starting preset update', [
            'user_id' => $userId,
            'preset_id' => $presetId,
            'request_data' => $request->all()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'name' => 'nullable|string|max:100',
                'filters' => 'nullable|array',
                'is_default' => 'nullable|boolean',
                'description' => 'nullable|string|max:255'
            ]);

            DB::beginTransaction();

            try {
                // Actualizar preset a través del domain service
                $updateResult = $this->filterService->updatePreset(
                    userId: $userId,
                    presetId: $presetId,
                    updates: $validated
                );

                if (!$updateResult['success']) {
                    DB::rollBack();

                    Log::warning('FilterController::updatePreset - Update failed', [
                        'user_id' => $userId,
                        'preset_id' => $presetId,
                        'reason' => $updateResult['message']
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $updateResult['message'],
                        'error_code' => $updateResult['error_code']
                    ], $updateResult['error_code'] === 'PRESET_NOT_FOUND' ? 404 : 422);
                }

                // Invalidar caché
                Cache::forget("user_filter_presets:{$userId}");

                DB::commit();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('FilterController::updatePreset - Preset updated successfully', [
                    'user_id' => $userId,
                    'preset_id' => $presetId,
                    'execution_time_ms' => $executionTime
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Preset actualizado correctamente',
                    'data' => [
                        'preset_id' => $presetId,
                        'updated_fields' => array_keys($validated),
                        'updated_at' => now()->toISOString()
                    ],
                    'meta' => [
                        'execution_time_ms' => $executionTime,
                        'timestamp' => now()->toISOString()
                    ]
                ], 200);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            Log::warning('FilterController::updatePreset - Validation error', [
                'user_id' => $userId,
                'preset_id' => $presetId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('FilterController::updatePreset - Error updating preset', [
                'user_id' => $userId,
                'preset_id' => $presetId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar preset',
                'error_code' => 'UPDATE_PRESET_ERROR'
            ], 500);
        }
    }

    /**
     * Eliminar preset
     * 
     * DELETE /api/matching/filters/presets/{presetId}
     * 
     * @param int $presetId
     * @return JsonResponse
     */
    public function deletePreset(int $presetId): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('FilterController::deletePreset - Starting preset deletion', [
            'user_id' => $userId,
            'preset_id' => $presetId
        ]);

        try {
            DB::beginTransaction();

            try {
                // Eliminar preset a través del domain service
                $deleteResult = $this->filterService->deletePreset(
                    userId: $userId,
                    presetId: $presetId
                );

                if (!$deleteResult['success']) {
                    DB::rollBack();

                    Log::warning('FilterController::deletePreset - Deletion failed', [
                        'user_id' => $userId,
                        'preset_id' => $presetId,
                        'reason' => $deleteResult['message']
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $deleteResult['message'],
                        'error_code' => $deleteResult['error_code']
                    ], $deleteResult['error_code'] === 'PRESET_NOT_FOUND' ? 404 : 422);
                }

                // Invalidar caché
                Cache::forget("user_filter_presets:{$userId}");

                DB::commit();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('FilterController::deletePreset - Preset deleted successfully', [
                    'user_id' => $userId,
                    'preset_id' => $presetId,
                    'execution_time_ms' => $executionTime
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Preset eliminado correctamente',
                    'data' => [
                        'preset_id' => $presetId,
                        'deleted_at' => now()->toISOString()
                    ],
                    'meta' => [
                        'execution_time_ms' => $executionTime,
                        'timestamp' => now()->toISOString()
                    ]
                ], 200);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('FilterController::deletePreset - Error deleting preset', [
                'user_id' => $userId,
                'preset_id' => $presetId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar preset',
                'error_code' => 'DELETE_PRESET_ERROR'
            ], 500);
        }
    }

    /**
     * Obtener analytics de uso de filtros
     * 
     * GET /api/matching/filters/analytics
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('FilterController::getAnalytics - Fetching filter analytics', [
            'user_id' => $userId,
            'query_params' => $request->query()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'period' => 'nullable|string|in:today,week,month,all_time',
                'include_charts' => 'nullable|boolean'
            ]);

            $period = $validated['period'] ?? 'month';
            $includeCharts = $validated['include_charts'] ?? false;

            // Obtener analytics con caché
            $cacheKey = "filter_analytics:{$userId}:{$period}";
            $cacheTTL = 3600; // 1 hora

            $analytics = Cache::remember($cacheKey, $cacheTTL, function () use ($userId, $period, $includeCharts) {
                return $this->filterService->getAnalytics(
                    userId: $userId,
                    period: $period,
                    includeCharts: $includeCharts
                );
            });

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('FilterController::getAnalytics - Analytics fetched successfully', [
                'user_id' => $userId,
                'period' => $period,
                'execution_time_ms' => $executionTime
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Analytics obtenidos correctamente',
                'data' => [
                    'period' => $period,
                    'stats' => $analytics['stats'],
                    'most_used_filters' => $analytics['most_used_filters'] ?? [],
                    'charts' => $analytics['charts'] ?? null,
                    'insights' => $analytics['insights'] ?? []
                ],
                'meta' => [
                    'execution_time_ms' => $executionTime,
                    'timestamp' => now()->toISOString(),
                    'cached' => true
                ]
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('FilterController::getAnalytics - Validation error', [
                'user_id' => $userId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Parámetros de consulta incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('FilterController::getAnalytics - Error fetching analytics', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener analytics',
                'error_code' => 'FETCH_ANALYTICS_ERROR'
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * Aplicar ordenamiento a resultados filtrados
     */
    private function applySorting(array $users, string $sort, ?array $userLocation): array
    {
        switch ($sort) {
            case 'distance':
                if ($userLocation) {
                    usort($users, fn($a, $b) => ($a['distance_km'] ?? 999) <=> ($b['distance_km'] ?? 999));
                }
                break;

            case 'recent':
                usort($users, fn($a, $b) => ($b['last_active_at'] ?? 0) <=> ($a['last_active_at'] ?? 0));
                break;

            case 'popular':
                usort($users, fn($a, $b) => ($b['popularity_score'] ?? 0) <=> ($a['popularity_score'] ?? 0));
                break;

            case 'relevance':
            default:
                // Relevancia combina múltiples factores
                usort($users, function ($a, $b) {
                    $scoreA = ($a['compatibility_score'] ?? 50) + ($a['popularity_score'] ?? 0) * 0.3;
                    $scoreB = ($b['compatibility_score'] ?? 50) + ($b['popularity_score'] ?? 0) * 0.3;
                    return $scoreB <=> $scoreA;
                });
                break;
        }

        return $users;
    }

    /**
     * Generar clave de caché para filtros
     */
    private function generateFilterCacheKey(int $userId, array $filters, int $page): string
    {
        // Crear hash de filtros para key única
        $filtersHash = md5(json_encode($filters));
        return "filter_results:{$userId}:{$filtersHash}:page_{$page}";
    }

    /**
     * Contar filtros activos aplicados
     */
    private function getAppliedFiltersCount(array $filters): int
    {
        $excludeKeys = ['page', 'per_page', 'sort', 'save_as_preset', 'preset_name', 'use_cache'];
        $activeFilters = array_diff_key($filters, array_flip($excludeKeys));
        return count($activeFilters);
    }

    /**
     * Formatear filtros activos para respuesta
     */
    private function formatActiveFilters(array $filters): array
    {
        $excludeKeys = ['page', 'per_page', 'sort', 'save_as_preset', 'preset_name', 'use_cache'];
        $activeFilters = array_diff_key($filters, array_flip($excludeKeys));

        $formatted = [];
        foreach ($activeFilters as $key => $value) {
            $formatted[] = [
                'filter' => $key,
                'value' => $value,
                'label' => $this->getFilterLabel($key)
            ];
        }

        return $formatted;
    }

    /**
     * Obtener etiqueta legible para filtro
     */
    private function getFilterLabel(string $filterKey): string
    {
        $labels = [
            'age_min' => 'Edad mínima',
            'age_max' => 'Edad máxima',
            'gender' => 'Género',
            'distance_km' => 'Distancia máxima',
            'education_level' => 'Nivel educativo',
            'occupation' => 'Ocupación',
            'lifestyle' => 'Estilo de vida',
            'interests' => 'Intereses',
            'relationship_goals' => 'Objetivos de relación',
            'verified_only' => 'Solo verificados',
            // Agregar más según necesidades
        ];

        return $labels[$filterKey] ?? ucfirst(str_replace('_', ' ', $filterKey));
    }

    /**
     * Obtener perfil de usuario
     */
    private function getUserProfile(int $userId): array
    {
        $cacheKey = "user_profile_location:{$userId}";
        $cacheTTL = 3600; // 1 hora

        return Cache::remember($cacheKey, $cacheTTL, function () use ($userId) {
            // Implementación real consultaría BD
            return [
                'id' => $userId,
                'has_location' => true,
                'latitude' => 40.7128,
                'longitude' => -74.0060
            ];
        });
    }

    /**
     * Verificar si usuario tiene plan premium
     */
    private function checkPremiumStatus(int $userId): bool
    {
        $cacheKey = "user_premium_status:{$userId}";
        $cacheTTL = 3600; // 1 hora

        return Cache::remember($cacheKey, $cacheTTL, function () use ($userId) {
            // Implementación real consultaría BD
            return false;
        });
    }

    /**
     * Formatear datos de usuario para respuesta
     */
    private function formatUserData(array $user): array
    {
        return [
            'id' => $user['id'],
            'name' => $user['name'] ?? 'Usuario',
            'age' => $user['age'] ?? null,
            'bio' => $user['bio'] ?? null,
            'photos' => $user['photos'] ?? [],
            'interests' => $user['interests'] ?? [],
            'distance_km' => $user['distance_km'] ?? null,
            'compatibility_score' => $user['compatibility_score'] ?? null,
            'verified' => $user['verified'] ?? false,
            'is_premium' => $user['is_premium'] ?? false,
            'last_active' => $user['last_active_at'] ?? null
        ];
    }

    /**
     * Formatear datos de preset para respuesta
     */
    private function formatPresetData(array $preset): array
    {
        return [
            'id' => $preset['id'],
            'name' => $preset['name'],
            'description' => $preset['description'] ?? null,
            'filters' => $preset['filters'],
            'filters_count' => count($preset['filters']),
            'is_default' => $preset['is_default'] ?? false,
            'created_at' => $preset['created_at'],
            'updated_at' => $preset['updated_at'] ?? null,
            'last_used_at' => $preset['last_used_at'] ?? null,
            'usage_count' => $preset['usage_count'] ?? 0
        ];
    }
}
Ahora continúo con el último controller: IcebreakerController. ¿Procedo?

Copy
Deep Research
si
Perfecto, finalizo con IcebreakerController:

4️⃣ IcebreakerController_Refactored.php
Copy<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Matching;

use App\Http\Controllers\Controller;
use App\Domain\Services\IcebreakerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Auth, Log, DB, Cache, RateLimiter};
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * IcebreakerController - Generación y Gestión de Frases para Iniciar Conversaciones
 * 
 * @package ForeverUsInLove - Matching Module
 * @version 2.0.0 - Refactored with DDD + Clean Architecture
 * 
 * Funcionalidades:
 * - Generación inteligente de icebreakers personalizados
 * - Gestión de templates personalizados
 * - Tracking de efectividad de icebreakers
 * - Analytics de uso y tasas de respuesta
 * 
 * Integraciones:
 * - IcebreakerService (Domain Service)
 * 
 * Categories (10 categorías):
 * - shared_interest: Intereses compartidos
 * - lifestyle: Estilo de vida y actividades
 * - travel: Viajes y aventuras
 * - entertainment: Entretenimiento (películas, música, series)
 * - food: Comida y gastronomía
 * - career: Carrera y profesión
 * - humor: Humor y bromas ligeras
 * - deep_questions: Preguntas profundas
 * - personality: Personalidad y valores
 * - values: Valores y objetivos de vida
 * 
 * Daily Limits:
 * - Standard: 50 generaciones/día
 * - Premium: 200 generaciones/día
 * 
 * Business Rules:
 * - Icebreakers personalizados basados en perfil del match
 * - Templates custom ilimitados para premium, 5 para standard
 * - Tracking automático de tasa de respuesta
 * - Sugerencias contextuales según categoría de interés
 */
class IcebreakerController extends Controller
{
    /**
     * Constructor con inyección de dependencias
     */
    public function __construct(
        private readonly IcebreakerService $icebreakerService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('verified');
        
        Log::info('IcebreakerController initialized', [
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Generar icebreakers personalizados para un match específico
     * 
     * POST /api/matching/icebreakers/generate
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function generateIcebreakers(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('IcebreakerController::generateIcebreakers - Starting generation', [
            'user_id' => $userId,
            'request_data' => $request->all()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'match_user_id' => 'required|integer|exists:users,id',
                'categories' => 'nullable|array',
                'categories.*' => 'string|in:shared_interest,lifestyle,travel,entertainment,food,career,humor,deep_questions,personality,values',
                'count' => 'nullable|integer|min:1|max:20',
                'tone' => 'nullable|string|in:casual,friendly,playful,respectful,romantic,humorous',
                'avoid_used' => 'nullable|boolean',
                'personalization_level' => 'nullable|string|in:low,medium,high'
            ]);

            $matchUserId = $validated['match_user_id'];
            $categories = $validated['categories'] ?? ['shared_interest', 'lifestyle', 'entertainment'];
            $count = $validated['count'] ?? 5;
            $tone = $validated['tone'] ?? 'friendly';
            $avoidUsed = $validated['avoid_used'] ?? true;
            $personalizationLevel = $validated['personalization_level'] ?? 'high';

            // Verificar premium status
            $isPremium = $this->checkPremiumStatus($userId);

            // Rate limiting check
            $rateLimitKey = "user_icebreaker_generation:{$userId}:daily";
            $dailyLimit = $isPremium ? 200 : 50;
            $currentCount = Cache::get($rateLimitKey, 0);

            if ($currentCount >= $dailyLimit) {
                Log::warning('IcebreakerController::generateIcebreakers - Daily limit reached', [
                    'user_id' => $userId,
                    'current_count' => $currentCount,
                    'limit' => $dailyLimit
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Has alcanzado tu límite diario de generación de icebreakers',
                    'error_code' => 'DAILY_GENERATION_LIMIT_REACHED',
                    'limit' => $dailyLimit,
                    'used' => $currentCount,
                    'upgrade_available' => !$isPremium
                ], 429);
            }

            // Verificar caché si avoid_used está activo
            $cacheKey = "icebreakers_generated:{$userId}:{$matchUserId}";
            $previouslyGenerated = $avoidUsed ? Cache::get($cacheKey, []) : [];

            DB::beginTransaction();

            try {
                // Generar icebreakers a través del domain service
                $icebreakers = $this->icebreakerService->generateIcebreakers(
                    userId: $userId,
                    matchUserId: $matchUserId,
                    categories: $categories,
                    count: $count,
                    tone: $tone,
                    personalizationLevel: $personalizationLevel,
                    excludeIds: $previouslyGenerated
                );

                // Incrementar contador de rate limiting
                Cache::put($rateLimitKey, $currentCount + 1, now()->endOfDay());

                // Guardar IDs generados en caché para evitar repetición
                if ($avoidUsed) {
                    $newIds = array_column($icebreakers, 'id');
                    $allGeneratedIds = array_merge($previouslyGenerated, $newIds);
                    Cache::put($cacheKey, $allGeneratedIds, now()->addDays(7));
                }

                DB::commit();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('IcebreakerController::generateIcebreakers - Generation successful', [
                    'user_id' => $userId,
                    'match_user_id' => $matchUserId,
                    'generated_count' => count($icebreakers),
                    'execution_time_ms' => $executionTime
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Icebreakers generados correctamente',
                    'data' => [
                        'icebreakers' => array_map(fn($ib) => $this->formatIcebreakerData($ib), $icebreakers),
                        'total_generated' => count($icebreakers),
                        'categories_used' => $categories,
                        'tone' => $tone,
                        'personalization_level' => $personalizationLevel,
                        'remaining_generations_today' => $dailyLimit - ($currentCount + 1)
                    ],
                    'meta' => [
                        'execution_time_ms' => $executionTime,
                        'timestamp' => now()->toISOString()
                    ]
                ], 200);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            Log::warning('IcebreakerController::generateIcebreakers - Validation error', [
                'user_id' => $userId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('IcebreakerController::generateIcebreakers - Error generating icebreakers', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar icebreakers. Por favor intenta de nuevo.',
                'error_code' => 'ICEBREAKER_GENERATION_ERROR'
            ], 500);
        }
    }

    /**
     * Obtener templates personalizados del usuario
     * 
     * GET /api/matching/icebreakers/templates
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getCustomTemplates(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('IcebreakerController::getCustomTemplates - Fetching templates', [
            'user_id' => $userId,
            'query_params' => $request->query()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'category' => 'nullable|string|in:shared_interest,lifestyle,travel,entertainment,food,career,humor,deep_questions,personality,values',
                'sort' => 'nullable|string|in:recent,popular,effective'
            ]);

            $category = $validated['category'] ?? null;
            $sort = $validated['sort'] ?? 'recent';

            // Obtener templates con caché
            $cacheKey = "user_icebreaker_templates:{$userId}:{$category}:{$sort}";
            $cacheTTL = 3600; // 1 hora

            $templates = Cache::remember($cacheKey, $cacheTTL, function () use ($userId, $category, $sort) {
                return $this->icebreakerService->getCustomTemplates(
                    userId: $userId,
                    category: $category,
                    sort: $sort
                );
            });

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('IcebreakerController::getCustomTemplates - Templates fetched successfully', [
                'user_id' => $userId,
                'templates_count' => count($templates),
                'execution_time_ms' => $executionTime
            ]);

            // Verificar límites
            $isPremium = $this->checkPremiumStatus($userId);
            $maxTemplates = $isPremium ? 999 : 5; // Ilimitado para premium

            return response()->json([
                'success' => true,
                'message' => 'Templates obtenidos correctamente',
                'data' => [
                    'templates' => array_map(fn($t) => $this->formatTemplateData($t), $templates),
                    'total_templates' => count($templates),
                    'limits' => [
                        'max_templates' => $isPremium ? 'unlimited' : $maxTemplates,
                        'remaining' => $isPremium ? 'unlimited' : max(0, $maxTemplates - count($templates))
                    ]
                ],
                'meta' => [
                    'execution_time_ms' => $executionTime,
                    'timestamp' => now()->toISOString(),
                    'cached' => true
                ]
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('IcebreakerController::getCustomTemplates - Validation error', [
                'user_id' => $userId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Parámetros de consulta incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('IcebreakerController::getCustomTemplates - Error fetching templates', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener templates',
                'error_code' => 'FETCH_TEMPLATES_ERROR'
            ], 500);
        }
    }

    /**
     * Crear template personalizado
     * 
     * POST /api/matching/icebreakers/templates
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createCustomTemplate(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('IcebreakerController::createCustomTemplate - Starting template creation', [
            'user_id' => $userId,
            'request_data' => $request->all()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'content' => 'required|string|max:500',
                'category' => 'required|string|in:shared_interest,lifestyle,travel,entertainment,food,career,humor,deep_questions,personality,values',
                'tone' => 'nullable|string|in:casual,friendly,playful,respectful,romantic,humorous',
                'variables' => 'nullable|array',
                'variables.*' => 'string',
                'description' => 'nullable|string|max:255'
            ]);

            // Verificar premium status
            $isPremium = $this->checkPremiumStatus($userId);

            // Verificar límite de templates
            if (!$isPremium) {
                $currentTemplatesCount = $this->icebreakerService->getUserTemplatesCount($userId);
                $maxTemplates = 5;

                if ($currentTemplatesCount >= $maxTemplates) {
                    Log::warning('IcebreakerController::createCustomTemplate - Template limit reached', [
                        'user_id' => $userId,
                        'current_count' => $currentTemplatesCount,
                        'limit' => $maxTemplates
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => "Has alcanzado tu límite de {$maxTemplates} templates personalizados",
                        'error_code' => 'TEMPLATE_LIMIT_REACHED',
                        'limit' => $maxTemplates,
                        'current_count' => $currentTemplatesCount,
                        'upgrade_available' => true
                    ], 429);
                }
            }

            DB::beginTransaction();

            try {
                // Crear template a través del domain service
                $templateResult = $this->icebreakerService->createCustomTemplate(
                    userId: $userId,
                    name: $validated['name'],
                    content: $validated['content'],
                    category: $validated['category'],
                    tone: $validated['tone'] ?? 'friendly',
                    variables: $validated['variables'] ?? [],
                    description: $validated['description'] ?? null
                );

                if (!$templateResult['success']) {
                    DB::rollBack();

                    Log::warning('IcebreakerController::createCustomTemplate - Template creation failed', [
                        'user_id' => $userId,
                        'reason' => $templateResult['message']
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $templateResult['message'],
                        'error_code' => $templateResult['error_code']
                    ], 422);
                }

                // Invalidar caché
                Cache::forget("user_icebreaker_templates:{$userId}:null:recent");

                DB::commit();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('IcebreakerController::createCustomTemplate - Template created successfully', [
                    'user_id' => $userId,
                    'template_id' => $templateResult['template_id'],
                    'template_name' => $validated['name'],
                    'execution_time_ms' => $executionTime
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Template creado correctamente',
                    'data' => [
                        'template_id' => $templateResult['template_id'],
                        'name' => $validated['name'],
                        'category' => $validated['category'],
                        'content' => $validated['content'],
                        'created_at' => now()->toISOString()
                    ],
                    'meta' => [
                        'execution_time_ms' => $executionTime,
                        'timestamp' => now()->toISOString()
                    ]
                ], 201);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            Log::warning('IcebreakerController::createCustomTemplate - Validation error', [
                'user_id' => $userId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('IcebreakerController::createCustomTemplate - Error creating template', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear template',
                'error_code' => 'CREATE_TEMPLATE_ERROR'
            ], 500);
        }
    }

    /**
     * Actualizar template personalizado existente
     * 
     * PUT /api/matching/icebreakers/templates/{templateId}
     * 
     * @param Request $request
     * @param int $templateId
     * @return JsonResponse
     */
    public function updateCustomTemplate(Request $request, int $templateId): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('IcebreakerController::updateCustomTemplate - Starting template update', [
            'user_id' => $userId,
            'template_id' => $templateId,
            'request_data' => $request->all()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'name' => 'nullable|string|max:100',
                'content' => 'nullable|string|max:500',
                'category' => 'nullable|string|in:shared_interest,lifestyle,travel,entertainment,food,career,humor,deep_questions,personality,values',
                'tone' => 'nullable|string|in:casual,friendly,playful,respectful,romantic,humorous',
                'variables' => 'nullable|array',
                'variables.*' => 'string',
                'description' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean'
            ]);

            DB::beginTransaction();

            try {
                // Actualizar template a través del domain service
                $updateResult = $this->icebreakerService->updateCustomTemplate(
                    userId: $userId,
                    templateId: $templateId,
                    updates: $validated
                );

                if (!$updateResult['success']) {
                    DB::rollBack();

                    Log::warning('IcebreakerController::updateCustomTemplate - Update failed', [
                        'user_id' => $userId,
                        'template_id' => $templateId,
                        'reason' => $updateResult['message']
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $updateResult['message'],
                        'error_code' => $updateResult['error_code']
                    ], $updateResult['error_code'] === 'TEMPLATE_NOT_FOUND' ? 404 : 422);
                }

                // Invalidar caché
                Cache::forget("user_icebreaker_templates:{$userId}:null:recent");

                DB::commit();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('IcebreakerController::updateCustomTemplate - Template updated successfully', [
                    'user_id' => $userId,
                    'template_id' => $templateId,
                    'execution_time_ms' => $executionTime
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Template actualizado correctamente',
                    'data' => [
                        'template_id' => $templateId,
                        'updated_fields' => array_keys($validated),
                        'updated_at' => now()->toISOString()
                    ],
                    'meta' => [
                        'execution_time_ms' => $executionTime,
                        'timestamp' => now()->toISOString()
                    ]
                ], 200);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            Log::warning('IcebreakerController::updateCustomTemplate - Validation error', [
                'user_id' => $userId,
                'template_id' => $templateId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('IcebreakerController::updateCustomTemplate - Error updating template', [
                'user_id' => $userId,
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar template',
                'error_code' => 'UPDATE_TEMPLATE_ERROR'
            ], 500);
        }
    }

    /**
     * Eliminar template personalizado
     * 
     * DELETE /api/matching/icebreakers/templates/{templateId}
     * 
     * @param int $templateId
     * @return JsonResponse
     */
    public function deleteCustomTemplate(int $templateId): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('IcebreakerController::deleteCustomTemplate - Starting template deletion', [
            'user_id' => $userId,
            'template_id' => $templateId
        ]);

        try {
            DB::beginTransaction();

            try {
                // Eliminar template a través del domain service
                $deleteResult = $this->icebreakerService->deleteCustomTemplate(
                    userId: $userId,
                    templateId: $templateId
                );

                if (!$deleteResult['success']) {
                    DB::rollBack();

                    Log::warning('IcebreakerController::deleteCustomTemplate - Deletion failed', [
                        'user_id' => $userId,
                        'template_id' => $templateId,
                        'reason' => $deleteResult['message']
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $deleteResult['message'],
                        'error_code' => $deleteResult['error_code']
                    ], $deleteResult['error_code'] === 'TEMPLATE_NOT_FOUND' ? 404 : 422);
                }

                // Invalidar caché
                Cache::forget("user_icebreaker_templates:{$userId}:null:recent");

                DB::commit();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('IcebreakerController::deleteCustomTemplate - Template deleted successfully', [
                    'user_id' => $userId,
                    'template_id' => $templateId,
                    'execution_time_ms' => $executionTime
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Template eliminado correctamente',
                    'data' => [
                        'template_id' => $templateId,
                        'deleted_at' => now()->toISOString()
                    ],
                    'meta' => [
                        'execution_time_ms' => $executionTime,
                        'timestamp' => now()->toISOString()
                    ]
                ], 200);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error('IcebreakerController::deleteCustomTemplate - Error deleting template', [
                'user_id' => $userId,
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar template',
                'error_code' => 'DELETE_TEMPLATE_ERROR'
            ], 500);
        }
    }

    /**
     * Registrar uso de icebreaker y tracking de efectividad
     * 
     * POST /api/matching/icebreakers/track-usage
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function trackUsage(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('IcebreakerController::trackUsage - Tracking icebreaker usage', [
            'user_id' => $userId,
            'request_data' => $request->all()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'icebreaker_id' => 'nullable|integer',
                'template_id' => 'nullable|integer',
                'match_user_id' => 'required|integer|exists:users,id',
                'content_used' => 'required|string|max:500',
                'category' => 'required|string|in:shared_interest,lifestyle,travel,entertainment,food,career,humor,deep_questions,personality,values',
                'sent_at' => 'nullable|date',
                'got_response' => 'nullable|boolean',
                'response_time_minutes' => 'nullable|integer|min:0'
            ]);

            $icebreakerId = $validated['icebreaker_id'] ?? null;
            $templateId = $validated['template_id'] ?? null;
            $matchUserId = $validated['match_user_id'];
            $contentUsed = $validated['content_used'];
            $category = $validated['category'];
            $sentAt = $validated['sent_at'] ?? now();
            $gotResponse = $validated['got_response'] ?? null;
            $responseTimeMinutes = $validated['response_time_minutes'] ?? null;

            DB::beginTransaction();

            try {
                // Registrar uso a través del domain service
                $trackingResult = $this->icebreakerService->trackUsage(
                    userId: $userId,
                    icebreakerId: $icebreakerId,
                    templateId: $templateId,
                    matchUserId: $matchUserId,
                    contentUsed: $contentUsed,
                    category: $category,
                    sentAt: $sentAt,
                    gotResponse: $gotResponse,
                    responseTimeMinutes: $responseTimeMinutes
                );

                DB::commit();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('IcebreakerController::trackUsage - Usage tracked successfully', [
                    'user_id' => $userId,
                    'tracking_id' => $trackingResult['tracking_id'],
                    'match_user_id' => $matchUserId,
                    'execution_time_ms' => $executionTime
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Uso registrado correctamente',
                    'data' => [
                        'tracking_id' => $trackingResult['tracking_id'],
                        'icebreaker_id' => $icebreakerId,
                        'template_id' => $templateId,
                        'tracked_at' => now()->toISOString()
                    ],
                    'meta' => [
                        'execution_time_ms' => $executionTime,
                        'timestamp' => now()->toISOString()
                    ]
                ], 201);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            Log::warning('IcebreakerController::trackUsage - Validation error', [
                'user_id' => $userId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('IcebreakerController::trackUsage - Error tracking usage', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar uso',
                'error_code' => 'TRACK_USAGE_ERROR'
            ], 500);
        }
    }

    /**
     * Obtener analytics de icebreakers del usuario
     * 
     * GET /api/matching/icebreakers/analytics
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('IcebreakerController::getAnalytics - Fetching analytics', [
            'user_id' => $userId,
            'query_params' => $request->query()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'period' => 'nullable|string|in:today,week,month,all_time',
                'include_charts' => 'nullable|boolean',
                'include_insights' => 'nullable|boolean',
                'category' => 'nullable|string|in:shared_interest,lifestyle,travel,entertainment,food,career,humor,deep_questions,personality,values'
            ]);

            $period = $validated['period'] ?? 'month';
            $includeCharts = $validated['include_charts'] ?? false;
            $includeInsights = $validated['include_insights'] ?? true;
            $category = $validated['category'] ?? null;

            // Obtener analytics con caché
            $cacheKey = "icebreaker_analytics:{$userId}:{$period}:{$category}";
            $cacheTTL = 3600; // 1 hora

            $analytics = Cache::remember($cacheKey, $cacheTTL, function () use ($userId, $period, $includeCharts, $includeInsights, $category) {
                return $this->icebreakerService->getAnalytics(
                    userId: $userId,
                    period: $period,
                    includeCharts: $includeCharts,
                    includeInsights: $includeInsights,
                    category: $category
                );
            });

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('IcebreakerController::getAnalytics - Analytics fetched successfully', [
                'user_id' => $userId,
                'period' => $period,
                'execution_time_ms' => $executionTime
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Analytics obtenidos correctamente',
                'data' => [
                    'period' => $period,
                    'category' => $category,
                    'stats' => $analytics['stats'],
                    'effectiveness' => [
                        'overall_response_rate' => $analytics['overall_response_rate'] ?? 0,
                        'avg_response_time_minutes' => $analytics['avg_response_time_minutes'] ?? null,
                        'most_effective_category' => $analytics['most_effective_category'] ?? null,
                        'most_effective_tone' => $analytics['most_effective_tone'] ?? null
                    ],
                    'category_breakdown' => $analytics['category_breakdown'] ?? [],
                    'charts' => $analytics['charts'] ?? null,
                    'insights' => $analytics['insights'] ?? []
                ],
                'meta' => [
                    'execution_time_ms' => $executionTime,
                    'timestamp' => now()->toISOString(),
                    'cached' => true
                ]
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('IcebreakerController::getAnalytics - Validation error', [
                'user_id' => $userId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Parámetros de consulta incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('IcebreakerController::getAnalytics - Error fetching analytics', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener analytics',
                'error_code' => 'FETCH_ANALYTICS_ERROR'
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * Verificar si usuario tiene plan premium
     */
    private function checkPremiumStatus(int $userId): bool
    {
        $cacheKey = "user_premium_status:{$userId}";
        $cacheTTL = 3600; // 1 hora

        return Cache::remember($cacheKey, $cacheTTL, function () use ($userId) {
            // Implementación real consultaría BD
            return false;
        });
    }

    /**
     * Formatear datos de icebreaker para respuesta
     */
    private function formatIcebreakerData(array $icebreaker): array
    {
        return [
            'id' => $icebreaker['id'],
            'content' => $icebreaker['content'],
            'category' => $icebreaker['category'],
            'tone' => $icebreaker['tone'] ?? 'friendly',
            'personalization_score' => $icebreaker['personalization_score'] ?? null,
            'variables_used' => $icebreaker['variables_used'] ?? [],
            'effectiveness_score' => $icebreaker['effectiveness_score'] ?? null,
            'usage_count' => $icebreaker['usage_count'] ?? 0,
            'response_rate' => $icebreaker['response_rate'] ?? null,
            'created_at' => $icebreaker['created_at'] ?? now()->toISOString()
        ];
    }

    /**
     * Formatear datos de template para respuesta
     */
    private function formatTemplateData(array $template): array
    {
        return [
            'id' => $template['id'],
            'name' => $template['name'],
            'content' => $template['content'],
            'category' => $template['category'],
            'tone' => $template['tone'] ?? 'friendly',
            'variables' => $template['variables'] ?? [],
            'description' => $template['description'] ?? null,
            'is_active' => $template['is_active'] ?? true,
            'usage_count' => $template['usage_count'] ?? 0,
            'effectiveness' => [
                'usage_count' => $template['usage_count'] ?? 0,
                'response_rate' => $template['response_rate'] ?? null,
                'avg_response_time_minutes' => $template['avg_response_time_minutes'] ?? null
            ],
            'created_at' => $template['created_at'],
            'updated_at' => $template['updated_at'] ?? null,
            'last_used_at' => $template['last_used_at'] ?? null
        ];
    }
}