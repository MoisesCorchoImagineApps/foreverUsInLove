<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Matching;

use App\Http\Controllers\Controller;
use App\Domain\Matching\Services\MatchingService;
use App\Infrastructure\External\GoogleMapsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Auth, Log, DB, Cache, RateLimiter};
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * MatchController - Gestión de Matches y Compatibilidad
 * 
 * @package ForeverUsInLove - Matching Module
 * @version 2.0.0 - Refactored with DDD + Clean Architecture
 * 
 * Funcionalidades:
 * - Generación automática de matches basados en compatibilidad
 * - Cálculo de compatibilidad multi-dimensional con distancia geográfica
 * - Gestión de matches activos
 * - Unmatch de usuarios
 * - Analytics de matches y tasas de éxito
 * 
 * Integraciones:
 * - MatchingService (Domain Service)
 * - GoogleMapsService (Geolocalización y cálculo de distancias)
 * 
 * Rate Limiting:
 * - Standard: 50 generaciones/día
 * - Premium: 200 generaciones/día
 * 
 * Business Rules:
 * - Compatibility threshold: 60% mínimo para generar match
 * - Distancia máxima: 50km standard, 200km premium
 * - Factores de compatibilidad: intereses (30%), valores (25%), lifestyle (20%), 
 *   personalidad (15%), distancia geográfica (10%)
 * - Caché de matches: 30 minutos
 * - Unmatch requiere confirmación y es irreversible
 */
class MatchController extends Controller
{
    /**
     * Constructor con inyección de dependencias
     */
    public function __construct(
        private readonly MatchingService $matchingService,
        private readonly GoogleMapsService $googleMapsService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('verified');
        
        Log::info('MatchController initialized', [
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Generar nuevos matches para el usuario basados en algoritmo de compatibilidad
     * 
     * POST /api/matching/matches/generate
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function generateMatches(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('MatchController::generateMatches - Starting match generation', [
            'user_id' => $userId,
            'request_data' => $request->all()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'max_matches' => 'nullable|integer|min:1|max:100',
                'min_compatibility' => 'nullable|integer|min:0|max:100',
                'max_distance_km' => 'nullable|integer|min:1|max:500',
                'filters' => 'nullable|array',
                'filters.age_min' => 'nullable|integer|min:18|max:99',
                'filters.age_max' => 'nullable|integer|min:18|max:99',
                'filters.interests' => 'nullable|array',
                'filters.relationship_goals' => 'nullable|array',
                'force_refresh' => 'nullable|boolean'
            ]);

            $maxMatches = $validated['max_matches'] ?? 20;
            $minCompatibility = $validated['min_compatibility'] ?? 60;
            $maxDistanceKm = $validated['max_distance_km'] ?? null;
            $filters = $validated['filters'] ?? [];
            $forceRefresh = $validated['force_refresh'] ?? false;

            // Verificar premium status
            $isPremium = $this->checkPremiumStatus($userId);
            
            // Ajustar límites según plan
            if (!$isPremium) {
                $maxDistanceKm = min($maxDistanceKm ?? 50, 50);
                $maxMatches = min($maxMatches, 20);
            } else {
                $maxDistanceKm = $maxDistanceKm ?? 200;
            }

            // Rate limiting check
            $rateLimitKey = "user_match_generation:{$userId}:daily";
            $dailyLimit = $isPremium ? 200 : 50;
            $currentCount = Cache::get($rateLimitKey, 0);

            if ($currentCount >= $dailyLimit) {
                Log::warning('MatchController::generateMatches - Daily limit reached', [
                    'user_id' => $userId,
                    'current_count' => $currentCount,
                    'limit' => $dailyLimit
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Has alcanzado tu límite diario de generación de matches',
                    'error_code' => 'DAILY_GENERATION_LIMIT_REACHED',
                    'limit' => $dailyLimit,
                    'used' => $currentCount,
                    'upgrade_available' => !$isPremium
                ], 429);
            }

            // Verificar caché si no se fuerza refresh
            $cacheKey = "user_generated_matches:{$userId}";
            $cacheTTL = 1800; // 30 minutos

            if (!$forceRefresh && Cache::has($cacheKey)) {
                $cachedMatches = Cache::get($cacheKey);
                
                Log::info('MatchController::generateMatches - Returning cached matches', [
                    'user_id' => $userId,
                    'matches_count' => count($cachedMatches)
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Matches obtenidos de caché',
                    'data' => [
                        'matches' => $cachedMatches,
                        'total_matches' => count($cachedMatches),
                        'from_cache' => true
                    ],
                    'meta' => [
                        'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                        'timestamp' => now()->toISOString()
                    ]
                ], 200);
            }

            DB::beginTransaction();

            try {
                // Obtener perfil del usuario con ubicación
                $userProfile = $this->getUserProfileWithLocation($userId);

                if (!$userProfile['has_location']) {
                    Log::warning('MatchController::generateMatches - User has no location', [
                        'user_id' => $userId
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Necesitas configurar tu ubicación para generar matches',
                        'error_code' => 'LOCATION_REQUIRED'
                    ], 422);
                }

                // Paso 1: Obtener candidatos potenciales del domain service
                $candidateUsers = $this->matchingService->getCandidateUsers(
                    userId: $userId,
                    filters: $filters,
                    limit: $maxMatches * 3 // Obtenemos más para filtrar por distancia
                );

                Log::info('MatchController::generateMatches - Candidate users fetched', [
                    'user_id' => $userId,
                    'candidates_count' => count($candidateUsers)
                ]);

                // Paso 2: Filtrar candidatos por distancia usando GoogleMapsService
                $candidatesWithDistance = [];
                
                foreach ($candidateUsers as $candidate) {
                    if (!isset($candidate['latitude']) || !isset($candidate['longitude'])) {
                        continue;
                    }

                    // Calcular distancia usando Haversine (GRATIS, sin API calls)
                    $distance = $this->googleMapsService->calculateDistance(
                        lat1: $userProfile['latitude'],
                        lng1: $userProfile['longitude'],
                        lat2: $candidate['latitude'],
                        lng2: $candidate['longitude'],
                        unit: 'km'
                    );

                    // Filtrar por distancia máxima
                    if ($distance <= $maxDistanceKm) {
                        $candidate['distance_km'] = round($distance, 1);
                        $candidatesWithDistance[] = $candidate;
                    }
                }

                Log::info('MatchController::generateMatches - Candidates filtered by distance', [
                    'user_id' => $userId,
                    'original_count' => count($candidateUsers),
                    'filtered_count' => count($candidatesWithDistance),
                    'max_distance_km' => $maxDistanceKm
                ]);

                // Paso 3: Calcular compatibilidad multi-dimensional para cada candidato
                $matchesWithCompatibility = [];

                foreach ($candidatesWithDistance as $candidate) {
                    $compatibility = $this->calculateDetailedCompatibility(
                        userProfile: $userProfile,
                        candidateProfile: $candidate
                    );

                    // Filtrar por compatibilidad mínima
                    if ($compatibility['total_score'] >= $minCompatibility) {
                        $matchesWithCompatibility[] = [
                            'user' => $this->formatUserMatchData($candidate),
                            'compatibility' => $compatibility,
                            'distance_km' => $candidate['distance_km'],
                            'match_score' => $this->calculateFinalMatchScore($compatibility, $candidate['distance_km'])
                        ];
                    }
                }

                // Paso 4: Ordenar por match_score descendente
                usort($matchesWithCompatibility, function ($a, $b) {
                    return $b['match_score'] <=> $a['match_score'];
                });

                // Paso 5: Limitar a maxMatches
                $finalMatches = array_slice($matchesWithCompatibility, 0, $maxMatches);

                // Paso 6: Guardar matches generados en BD a través del domain service
                $savedMatches = $this->matchingService->saveGeneratedMatches(
                    userId: $userId,
                    matches: $finalMatches
                );

                // Incrementar contador de rate limiting
                Cache::put($rateLimitKey, $currentCount + 1, now()->endOfDay());

                // Cachear resultados
                Cache::put($cacheKey, $finalMatches, $cacheTTL);

                DB::commit();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('MatchController::generateMatches - Matches generated successfully', [
                    'user_id' => $userId,
                    'total_matches' => count($finalMatches),
                    'avg_compatibility' => round(array_sum(array_column($finalMatches, 'match_score')) / count($finalMatches), 1),
                    'execution_time_ms' => $executionTime
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Matches generados correctamente',
                    'data' => [
                        'matches' => $finalMatches,
                        'total_matches' => count($finalMatches),
                        'generation_stats' => [
                            'candidates_analyzed' => count($candidateUsers),
                            'distance_filtered' => count($candidatesWithDistance),
                            'compatibility_filtered' => count($matchesWithCompatibility),
                            'final_matches' => count($finalMatches),
                            'avg_compatibility' => round(array_sum(array_column($finalMatches, 'match_score')) / max(count($finalMatches), 1), 1),
                            'max_distance_km' => $maxDistanceKm,
                            'min_compatibility' => $minCompatibility
                        ],
                        'remaining_generations_today' => $dailyLimit - ($currentCount + 1)
                    ],
                    'meta' => [
                        'execution_time_ms' => $executionTime,
                        'timestamp' => now()->toISOString(),
                        'cache_expires_at' => now()->addSeconds($cacheTTL)->toISOString()
                    ]
                ], 201);

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            Log::warning('MatchController::generateMatches - Validation error', [
                'user_id' => $userId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('MatchController::generateMatches - Error generating matches', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar matches. Por favor intenta de nuevo.',
                'error_code' => 'MATCH_GENERATION_ERROR'
            ], 500);
        }
    }

    /**
     * Obtener matches activos del usuario
     * 
     * GET /api/matching/matches
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserMatches(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('MatchController::getUserMatches - Fetching user matches', [
            'user_id' => $userId,
            'query_params' => $request->query()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort' => 'nullable|string|in:recent,compatibility,distance,activity',
                'filter_distance' => 'nullable|integer|min:1|max:500',
                'filter_min_compatibility' => 'nullable|integer|min:0|max:100',
                'include_location' => 'nullable|boolean'
            ]);

            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 20;
            $sort = $validated['sort'] ?? 'recent';
            $filterDistance = $validated['filter_distance'] ?? null;
            $filterMinCompatibility = $validated['filter_min_compatibility'] ?? null;
            $includeLocation = $validated['include_location'] ?? false;

            // Obtener matches con caché
            $cacheKey = "user_matches:{$userId}:{$sort}:page_{$page}";
            $cacheTTL = 1800; // 30 minutos

            $matchesData = Cache::remember($cacheKey, $cacheTTL, function () use ($userId, $sort, $page, $perPage) {
                return $this->matchingService->getUserMatches(
                    userId: $userId,
                    sort: $sort,
                    page: $page,
                    perPage: $perPage
                );
            });

            // Enriquecer con datos de distancia si se solicita
            if ($includeLocation) {
                $userProfile = $this->getUserProfileWithLocation($userId);
                
                if ($userProfile['has_location']) {
                    foreach ($matchesData['data'] as &$match) {
                        if (isset($match['user']['latitude']) && isset($match['user']['longitude'])) {
                            $distance = $this->googleMapsService->calculateDistance(
                                lat1: $userProfile['latitude'],
                                lng1: $userProfile['longitude'],
                                lat2: $match['user']['latitude'],
                                lng2: $match['user']['longitude'],
                                unit: 'km'
                            );
                            $match['distance_km'] = round($distance, 1);
                        }
                    }
                }
            }

            // Aplicar filtros adicionales si se especifican
            if ($filterDistance !== null || $filterMinCompatibility !== null) {
                $matchesData['data'] = array_filter($matchesData['data'], function ($match) use ($filterDistance, $filterMinCompatibility) {
                    if ($filterDistance !== null && isset($match['distance_km'])) {
                        if ($match['distance_km'] > $filterDistance) {
                            return false;
                        }
                    }
                    if ($filterMinCompatibility !== null && isset($match['compatibility']['total_score'])) {
                        if ($match['compatibility']['total_score'] < $filterMinCompatibility) {
                            return false;
                        }
                    }
                    return true;
                });
                
                $matchesData['data'] = array_values($matchesData['data']); // Reindexar
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('MatchController::getUserMatches - Matches fetched successfully', [
                'user_id' => $userId,
                'total_matches' => $matchesData['total'],
                'execution_time_ms' => $executionTime
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Matches obtenidos correctamente',
                'data' => [
                    'matches' => $matchesData['data'],
                    'pagination' => [
                        'total' => $matchesData['total'],
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'last_page' => ceil($matchesData['total'] / $perPage),
                        'has_more' => $matchesData['has_more']
                    ],
                    'stats' => [
                        'total_matches' => $matchesData['total'],
                        'new_matches' => $matchesData['new_matches_count'] ?? 0,
                        'avg_compatibility' => $matchesData['avg_compatibility'] ?? 0
                    ]
                ],
                'meta' => [
                    'execution_time_ms' => $executionTime,
                    'timestamp' => now()->toISOString(),
                    'cached' => true
                ]
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('MatchController::getUserMatches - Validation error', [
                'user_id' => $userId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Parámetros de consulta incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('MatchController::getUserMatches - Error fetching matches', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener matches',
                'error_code' => 'FETCH_MATCHES_ERROR'
            ], 500);
        }
    }

    /**
     * Obtener detalles completos de un match específico
     * 
     * GET /api/matching/matches/{matchId}
     * 
     * @param int $matchId
     * @return JsonResponse
     */
    public function getMatchDetails(int $matchId): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('MatchController::getMatchDetails - Fetching match details', [
            'user_id' => $userId,
            'match_id' => $matchId
        ]);

        try {
            // Obtener detalles con caché
            $cacheKey = "match_details:{$matchId}:{$userId}";
            $cacheTTL = 1800; // 30 minutos

            $matchDetails = Cache::remember($cacheKey, $cacheTTL, function () use ($userId, $matchId) {
                return $this->matchingService->getMatchDetails(
                    userId: $userId,
                    matchId: $matchId
                );
            });

            if (!$matchDetails) {
                Log::warning('MatchController::getMatchDetails - Match not found', [
                    'user_id' => $userId,
                    'match_id' => $matchId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Match no encontrado',
                    'error_code' => 'MATCH_NOT_FOUND'
                ], 404);
            }

            // Enriquecer con datos de distancia usando GoogleMapsService
            $userProfile = $this->getUserProfileWithLocation($userId);
            
            if ($userProfile['has_location'] && isset($matchDetails['user']['latitude']) && isset($matchDetails['user']['longitude'])) {
                $distance = $this->googleMapsService->calculateDistance(
                    lat1: $userProfile['latitude'],
                    lng1: $userProfile['longitude'],
                    lat2: $matchDetails['user']['latitude'],
                    lng2: $matchDetails['user']['longitude'],
                    unit: 'km'
                );
                $matchDetails['distance_km'] = round($distance, 1);
                
                // Obtener ubicación formateada del match
                $matchDetails['user']['location_formatted'] = $this->googleMapsService->reverseGeocode(
                    latitude: $matchDetails['user']['latitude'],
                    longitude: $matchDetails['user']['longitude'],
                    resultType: 'locality'
                )['formatted_address'] ?? null;
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('MatchController::getMatchDetails - Match details fetched successfully', [
                'user_id' => $userId,
                'match_id' => $matchId,
                'execution_time_ms' => $executionTime
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Detalles del match obtenidos correctamente',
                'data' => $matchDetails,
                'meta' => [
                    'execution_time_ms' => $executionTime,
                    'timestamp' => now()->toISOString(),
                    'cached' => true
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('MatchController::getMatchDetails - Error fetching match details', [
                'user_id' => $userId,
                'match_id' => $matchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalles del match',
                'error_code' => 'FETCH_MATCH_DETAILS_ERROR'
            ], 500);
        }
    }

    /**
     * Deshacer match con un usuario (unmatch) - IRREVERSIBLE
     * 
     * DELETE /api/matching/matches/{matchId}
     * 
     * @param Request $request
     * @param int $matchId
     * @return JsonResponse
     */
    public function unmatchUser(Request $request, int $matchId): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('MatchController::unmatchUser - Starting unmatch action', [
            'user_id' => $userId,
            'match_id' => $matchId,
            'request_data' => $request->all()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'reason' => 'nullable|string|in:not_interested,inappropriate_behavior,no_chemistry,other',
                'block_user' => 'nullable|boolean',
                'report_user' => 'nullable|boolean'
            ]);

            $reason = $validated['reason'] ?? 'not_interested';
            $blockUser = $validated['block_user'] ?? false;
            $reportUser = $validated['report_user'] ?? false;

            DB::beginTransaction();

            try {
                // Ejecutar unmatch a través del domain service
                $unmatchResult = $this->matchingService->unmatchUsers(
                    userId: $userId,
                    matchId: $matchId,
                    reason: $reason,
                    blockUser: $blockUser,
                    reportUser: $reportUser
                );

                if (!$unmatchResult['success']) {
                    DB::rollBack();

                    Log::warning('MatchController::unmatchUser - Unmatch failed', [
                        'user_id' => $userId,
                        'match_id' => $matchId,
                        'reason' => $unmatchResult['message']
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $unmatchResult['message'],
                        'error_code' => $unmatchResult['error_code']
                    ], 422);
                }

                // Invalidar cachés relacionados
                Cache::forget("match_details:{$matchId}:{$userId}");
                Cache::forget("user_matches:{$userId}:recent:page_1");
                Cache::forget("user_matches:{$userId}:compatibility:page_1");

                DB::commit();

                $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('MatchController::unmatchUser - Unmatch successful', [
                    'user_id' => $userId,
                    'match_id' => $matchId,
                    'blocked' => $blockUser,
                    'reported' => $reportUser,
                    'execution_time_ms' => $executionTime
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Match deshecho correctamente',
                    'data' => [
                        'match_id' => $matchId,
                        'unmatched_at' => now()->toISOString(),
                        'user_blocked' => $blockUser,
                        'user_reported' => $reportUser
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
            Log::warning('MatchController::unmatchUser - Validation error', [
                'user_id' => $userId,
                'match_id' => $matchId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('MatchController::unmatchUser - Error unmatching', [
                'user_id' => $userId,
                'match_id' => $matchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al deshacer el match',
                'error_code' => 'UNMATCH_ERROR'
            ], 500);
        }
    }

    /**
     * Calcular compatibilidad detallada entre usuario actual y otro usuario
     * 
     * POST /api/matching/compatibility/calculate
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function calculateCompatibility(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('MatchController::calculateCompatibility - Starting compatibility calculation', [
            'user_id' => $userId,
            'request_data' => $request->all()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'target_user_id' => 'required|integer|exists:users,id',
                'include_breakdown' => 'nullable|boolean',
                'include_suggestions' => 'nullable|boolean'
            ]);

            $targetUserId = $validated['target_user_id'];
            $includeBreakdown = $validated['include_breakdown'] ?? true;
            $includeSuggestions = $validated['include_suggestions'] ?? false;

            // Verificar caché
            $cacheKey = "compatibility_calc:{$userId}:{$targetUserId}";
            $cacheTTL = 3600; // 1 hora

            $compatibilityData = Cache::remember($cacheKey, $cacheTTL, function () use ($userId, $targetUserId, $includeBreakdown, $includeSuggestions) {
                // Obtener perfiles completos
                $userProfile = $this->getUserProfileWithLocation($userId);
                $targetProfile = $this->getUserProfileWithLocation($targetUserId);

                // Calcular compatibilidad multi-dimensional
                $compatibility = $this->calculateDetailedCompatibility(
                    userProfile: $userProfile,
                    candidateProfile: $targetProfile
                );

                // Calcular distancia geográfica
                $distance = null;
                if ($userProfile['has_location'] && $targetProfile['has_location']) {
                    $distance = $this->googleMapsService->calculateDistance(
                        lat1: $userProfile['latitude'],
                        lng1: $userProfile['longitude'],
                        lat2: $targetProfile['latitude'],
                        lng2: $targetProfile['longitude'],
                        unit: 'km'
                    );
                }

                // Calcular match score final
                $matchScore = $this->calculateFinalMatchScore($compatibility, $distance);

                return [
                    'compatibility' => $compatibility,
                    'distance_km' => $distance ? round($distance, 1) : null,
                    'match_score' => $matchScore,
                    'target_user' => $this->formatUserBasicData($targetProfile)
                ];
            });

            // Agregar sugerencias si se solicitan
            if ($includeSuggestions) {
                $compatibilityData['suggestions'] = $this->generateCompatibilitySuggestions(
                    $compatibilityData['compatibility']
                );
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('MatchController::calculateCompatibility - Compatibility calculated successfully', [
                'user_id' => $userId,
                'target_user_id' => $targetUserId,
                'match_score' => $compatibilityData['match_score'],
                'execution_time_ms' => $executionTime
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Compatibilidad calculada correctamente',
                'data' => $compatibilityData,
                'meta' => [
                    'execution_time_ms' => $executionTime,
                    'timestamp' => now()->toISOString(),
                    'cached' => true
                ]
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('MatchController::calculateCompatibility - Validation error', [
                'user_id' => $userId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('MatchController::calculateCompatibility - Error calculating compatibility', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al calcular compatibilidad',
                'error_code' => 'CALCULATE_COMPATIBILITY_ERROR'
            ], 500);
        }
    }

    /**
     * Obtener analytics de matches del usuario
     * 
     * GET /api/matching/matches/analytics
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $userId = Auth::id();

        Log::info('MatchController::getAnalytics - Fetching match analytics', [
            'user_id' => $userId,
            'query_params' => $request->query()
        ]);

        try {
            // Validación
            $validated = $request->validate([
                'period' => 'nullable|string|in:today,week,month,all_time',
                'include_charts' => 'nullable|boolean',
                'include_insights' => 'nullable|boolean'
            ]);

            $period = $validated['period'] ?? 'month';
            $includeCharts = $validated['include_charts'] ?? false;
            $includeInsights = $validated['include_insights'] ?? true;

            // Obtener analytics con caché
            $cacheKey = "match_analytics:{$userId}:{$period}";
            $cacheTTL = 3600; // 1 hora

            $analytics = Cache::remember($cacheKey, $cacheTTL, function () use ($userId, $period, $includeCharts, $includeInsights) {
                return $this->matchingService->getAnalytics(
                    userId: $userId,
                    period: $period,
                    includeCharts: $includeCharts,
                    includeInsights: $includeInsights
                );
            });

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('MatchController::getAnalytics - Analytics fetched successfully', [
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
            Log::warning('MatchController::getAnalytics - Validation error', [
                'user_id' => $userId,
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Parámetros de consulta incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('MatchController::getAnalytics - Error fetching analytics', [
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
     * Calcular compatibilidad multi-dimensional detallada
     * 
     * Factores:
     * - Intereses compartidos: 30%
     * - Valores y objetivos: 25%
     * - Estilo de vida: 20%
     * - Personalidad: 15%
     * - Distancia geográfica: 10%
     */
    private function calculateDetailedCompatibility(array $userProfile, array $candidateProfile): array
    {
        $scores = [];

        // 1. Intereses compartidos (30%)
        $sharedInterests = array_intersect(
            $userProfile['interests'] ?? [],
            $candidateProfile['interests'] ?? []
        );
        $interestsScore = count($sharedInterests) > 0 
            ? min(100, (count($sharedInterests) / max(count($userProfile['interests'] ?? [1]), 1)) * 100 * 1.5)
            : 0;
        $scores['interests'] = round($interestsScore, 1);

        // 2. Valores y objetivos (25%)
        $valueMatches = 0;
        $valueFields = ['relationship_goals', 'family_plans', 'religion', 'politics'];
        foreach ($valueFields as $field) {
            if (isset($userProfile[$field]) && isset($candidateProfile[$field])) {
                if ($userProfile[$field] === $candidateProfile[$field]) {
                    $valueMatches++;
                }
            }
        }
        $scores['values'] = round(($valueMatches / count($valueFields)) * 100, 1);

        // 3. Estilo de vida (20%)
        $lifestyleScore = 0;
        $lifestyleFields = ['lifestyle', 'drinking', 'smoking', 'exercise_frequency'];
        $lifestyleMatches = 0;
        foreach ($lifestyleFields as $field) {
            if (isset($userProfile[$field]) && isset($candidateProfile[$field])) {
                $lifestyleMatches += $this->calculateFieldCompatibility(
                    $userProfile[$field],
                    $candidateProfile[$field]
                );
            }
        }
        $scores['lifestyle'] = round(($lifestyleMatches / count($lifestyleFields)) * 100, 1);

        // 4. Personalidad (15%)
        $personalityScore = 0;
        if (isset($userProfile['personality_traits']) && isset($candidateProfile['personality_traits'])) {
            $personalityScore = $this->calculatePersonalityCompatibility(
                $userProfile['personality_traits'],
                $candidateProfile['personality_traits']
            );
        }
        $scores['personality'] = round($personalityScore, 1);

        // 5. Distancia geográfica (10%) - usando GoogleMapsService
        $distanceScore = 100; // Por defecto máximo si no hay ubicación
        if (isset($userProfile['latitude']) && isset($candidateProfile['latitude'])) {
            $distance = $this->googleMapsService->calculateDistance(
                lat1: $userProfile['latitude'],
                lng1: $userProfile['longitude'],
                lat2: $candidateProfile['latitude'],
                lng2: $candidateProfile['longitude'],
                unit: 'km'
            );

            // Scoring: 0-10km = 100%, 10-50km = 80-100%, 50-100km = 50-80%, 100+km = 0-50%
            if ($distance <= 10) {
                $distanceScore = 100;
            } elseif ($distance <= 50) {
                $distanceScore = 100 - (($distance - 10) / 40) * 20;
            } elseif ($distance <= 100) {
                $distanceScore = 80 - (($distance - 50) / 50) * 30;
            } else {
                $distanceScore = max(0, 50 - (($distance - 100) / 100) * 50);
            }
        }
        $scores['distance'] = round($distanceScore, 1);

        // Calcular score total ponderado
        $totalScore = (
            $scores['interests'] * 0.30 +
            $scores['values'] * 0.25 +
            $scores['lifestyle'] * 0.20 +
            $scores['personality'] * 0.15 +
            $scores['distance'] * 0.10
        );

        return [
            'total_score' => round($totalScore, 1),
            'breakdown' => $scores,
            'category_weights' => [
                'interests' => 30,
                'values' => 25,
                'lifestyle' => 20,
                'personality' => 15,
                'distance' => 10
            ],
            'match_quality' => $this->getMatchQualityLabel($totalScore)
        ];
    }

    /**
     * Calcular match score final combinando compatibilidad y otros factores
     */
    private function calculateFinalMatchScore(array $compatibility, ?float $distanceKm): float
    {
        $baseScore = $compatibility['total_score'];

        // Bonus por proximidad geográfica
        $proximityBonus = 0;
        if ($distanceKm !== null) {
            if ($distanceKm <= 5) {
                $proximityBonus = 5;
            } elseif ($distanceKm <= 10) {
                $proximityBonus = 3;
            } elseif ($distanceKm <= 20) {
                $proximityBonus = 1;
            }
        }

        return min(100, round($baseScore + $proximityBonus, 1));
    }

    /**
     * Calcular compatibilidad entre dos campos específicos
     */
    private function calculateFieldCompatibility($value1, $value2): float
    {
        if ($value1 === $value2) {
            return 1.0;
        }

        // Compatibilidad parcial para valores similares
        $compatibilityMatrix = [
            'occasionally' => ['rarely' => 0.7, 'regularly' => 0.5],
            'moderate' => ['light' => 0.6, 'active' => 0.6],
            // Agregar más mapeos según necesidades
        ];

        if (isset($compatibilityMatrix[$value1][$value2])) {
            return $compatibilityMatrix[$value1][$value2];
        }

        return 0.0;
    }

    /**
     * Calcular compatibilidad de personalidad (Big Five o similar)
     */
    private function calculatePersonalityCompatibility(array $traits1, array $traits2): float
    {
        $totalDifference = 0;
        $traitCount = 0;

        foreach ($traits1 as $trait => $score1) {
            if (isset($traits2[$trait])) {
                $score2 = $traits2[$trait];
                // Diferencia normalizada (0-100)
                $difference = abs($score1 - $score2);
                $totalDifference += $difference;
                $traitCount++;
            }
        }

        if ($traitCount === 0) {
            return 50; // Score neutral si no hay datos
        }

        // Convertir diferencia promedio en score de compatibilidad
        $avgDifference = $totalDifference / $traitCount;
        return max(0, 100 - $avgDifference);
    }

    /**
     * Obtener etiqueta de calidad del match según score
     */
    private function getMatchQualityLabel(float $score): string
    {
        if ($score >= 90) return 'Excepcional';
        if ($score >= 80) return 'Excelente';
        if ($score >= 70) return 'Muy Bueno';
        if ($score >= 60) return 'Bueno';
        if ($score >= 50) return 'Moderado';
        return 'Bajo';
    }

    /**
     * Generar sugerencias basadas en compatibilidad
     */
    private function generateCompatibilitySuggestions(array $compatibility): array
    {
        $suggestions = [];
        $breakdown = $compatibility['breakdown'];

        if ($breakdown['interests'] < 50) {
            $suggestions[] = [
                'category' => 'interests',
                'message' => 'Exploren nuevas actividades juntos para descubrir intereses compartidos',
                'priority' => 'high'
            ];
        }

        if ($breakdown['values'] < 60) {
            $suggestions[] = [
                'category' => 'values',
                'message' => 'Tengan conversaciones profundas sobre objetivos de vida y valores',
                'priority' => 'high'
            ];
        }

        if ($breakdown['distance'] < 70) {
            $suggestions[] = [
                'category' => 'distance',
                'message' => 'La distancia puede ser un desafío. Planifiquen cómo mantener la conexión',
                'priority' => 'medium'
            ];
        }

        return $suggestions;
    }

    /**
     * Obtener perfil de usuario con datos de ubicación
     */
    private function getUserProfileWithLocation(int $userId): array
    {
        $cacheKey = "user_profile_location:{$userId}";
        $cacheTTL = 3600; // 1 hora

        return Cache::remember($cacheKey, $cacheTTL, function () use ($userId) {
            // Implementación real consultaría BD
            // Por ahora retorna estructura mock
            return [
                'id' => $userId,
                'has_location' => true,
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'interests' => [],
                'personality_traits' => []
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
     * Formatear datos básicos de usuario
     */
    private function formatUserBasicData(array $user): array
    {
        return [
            'id' => $user['id'],
            'name' => $user['name'] ?? 'Usuario',
            'age' => $user['age'] ?? null,
            'location' => $user['location'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
            'verified' => $user['verified'] ?? false,
            'is_premium' => $user['is_premium'] ?? false
        ];
    }

    /**
     * Formatear datos de match para respuestas
     */
    private function formatUserMatchData(array $candidate): array
    {
        return [
            'id' => $candidate['id'],
            'name' => $candidate['name'] ?? 'Usuario',
            'age' => $candidate['age'] ?? null,
            'bio' => $candidate['bio'] ?? null,
            'photos' => $candidate['photos'] ?? [],
            'interests' => $candidate['interests'] ?? [],
            'occupation' => $candidate['occupation'] ?? null,
            'education' => $candidate['education'] ?? null,
            'verified' => $candidate['verified'] ?? false,
            'is_premium' => $candidate['is_premium'] ?? false,
            'latitude' => $candidate['latitude'] ?? null,
            'longitude' => $candidate['longitude'] ?? null
        ];
    }
}
