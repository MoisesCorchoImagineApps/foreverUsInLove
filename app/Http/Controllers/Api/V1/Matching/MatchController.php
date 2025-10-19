<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\V1\Matching;

use App\Services\Matching\MatchingService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Match Controller
 *
 * Gestiona la lógica de matches mutuos, generación de compatibilidad,
 * algoritmos de matching y análisis de resultados.
 *
 * @package App\Presentation\Http\Controllers\Api\V1\Matching
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 *
 * Business Rules:
 * - Matches solo se crean con compatibilidad >= 60%
 * - Límites diarios: 50 matches standard, 200 premium
 * - Radio de búsqueda: 25-100km
 * - Factores de compatibilidad: Age(15%), Location(20%), Interests(25%), Lifestyle(20%), Personality(20%)
 * - Cooldown entre generaciones: 5 minutos standard, 1 minuto premium
 * - Matches expirados después de 30 días de inactividad
 *
 * Dependencies:
 * @see \App\Application\Services\Matching\MatchingService
 * @see \App\Domain\Matching\Events\MatchCreatedEvent
 * @see \App\Domain\Matching\Events\MatchUnmatchedEvent
 */
class MatchController extends Controller
{
    use ApiResponseTrait;

    private const CACHE_TTL = 1800; // 30 minutos
    private const GENERATION_COOLDOWN_STANDARD = 300; // 5 minutos
    private const GENERATION_COOLDOWN_PREMIUM = 60; // 1 minuto
    private const DAILY_MATCH_LIMIT_STANDARD = 50;
    private const DAILY_MATCH_LIMIT_PREMIUM = 200;
    private const MIN_COMPATIBILITY_THRESHOLD = 0.6; // 60%

    /**
     * @param MatchingService $matchingService
     */
    public function __construct(
        private readonly MatchingService $matchingService
    ) {}

    /**
     * Generate new matches for the authenticated user
     *
     * Genera matches potenciales basados en compatibilidad, preferencias,
     * ubicación y algoritmos avanzados de machine learning.
     *
     * Rate Limiting: 10 requests per minute (write operation)
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @throws \Exception
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Matches generated successfully",
     *   "data": {
     *     "matches": [
     *       {
     *         "id": "uuid",
     *         "user": {
     *           "id": "uuid",
     *           "name": "Jane Doe",
     *           "age": 28,
     *           "location": "San Francisco, CA",
     *           "photos": ["url1", "url2"],
     *           "bio": "Love hiking and coffee"
     *         },
     *         "compatibility_score": 0.85,
     *         "compatibility_factors": {
     *           "age": 0.90,
     *           "location": 0.85,
     *           "interests": 0.88,
     *           "lifestyle": 0.82,
     *           "personality": 0.80
     *         },
     *         "distance_km": 15.5,
     *         "shared_interests": ["hiking", "coffee", "travel"],
     *         "generated_at": "2025-10-16T10:30:00Z"
     *       }
     *     ],
     *     "total_generated": 10,
     *     "remaining_daily_limit": 40,
     *     "next_generation_available_at": "2025-10-16T10:35:00Z",
     *     "stats": {
     *       "avg_compatibility": 0.78,
     *       "max_distance": 25.0,
     *       "total_candidates_analyzed": 150
     *     }
     *   }
     * }
     *
     * @response 429 {
     *   "success": false,
     *   "message": "Generation cooldown active",
     *   "errors": {
     *     "cooldown": "You can generate matches again in 3 minutes"
     *   }
     * }
     */
    public function generateMatches(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $isPremium = auth()->user()->isPremium();

            Log::info('Match generation initiated', [
                'user_id' => $userId,
                'is_premium' => $isPremium,
                'ip' => $request->ip()
            ]);

            // Check cooldown
            $cooldownKey = "match_generation_cooldown:{$userId}";
            if (Cache::has($cooldownKey)) {
                $remainingSeconds = Cache::get($cooldownKey);
                
                Log::warning('Match generation cooldown active', [
                    'user_id' => $userId,
                    'remaining_seconds' => $remainingSeconds
                ]);

                return $this->errorResponse(
                    'Generation cooldown active',
                    ['cooldown' => "You can generate matches again in {$remainingSeconds} seconds"],
                    Response::HTTP_TOO_MANY_REQUESTS
                );
            }

            // Check daily limit
            $dailyLimit = $isPremium ? self::DAILY_MATCH_LIMIT_PREMIUM : self::DAILY_MATCH_LIMIT_STANDARD;
            $generatedToday = $this->matchingService->getTodayGenerationCount($userId);

            if ($generatedToday >= $dailyLimit) {
                Log::warning('Daily match generation limit reached', [
                    'user_id' => $userId,
                    'generated_today' => $generatedToday,
                    'limit' => $dailyLimit
                ]);

                return $this->errorResponse(
                    'Daily limit reached',
                    ['limit' => "You have reached your daily limit of {$dailyLimit} match generations"],
                    Response::HTTP_TOO_MANY_REQUESTS
                );
            }

            // Generate matches
            $options = [
                'max_distance_km' => $request->input('max_distance_km', 25),
                'min_compatibility' => $request->input('min_compatibility', self::MIN_COMPATIBILITY_THRESHOLD),
                'max_results' => $request->input('max_results', 10),
                'include_compatibility_details' => $request->boolean('include_details', true)
            ];

            $result = $this->matchingService->generateMatches($userId, $options);

            // Set cooldown
            $cooldown = $isPremium ? self::GENERATION_COOLDOWN_PREMIUM : self::GENERATION_COOLDOWN_STANDARD;
            Cache::put($cooldownKey, $cooldown, $cooldown);

            // Prepare response data
            $responseData = [
                'matches' => $result['matches'],
                'total_generated' => count($result['matches']),
                'remaining_daily_limit' => $dailyLimit - ($generatedToday + 1),
                'next_generation_available_at' => now()->addSeconds($cooldown)->toIso8601String(),
                'stats' => [
                    'avg_compatibility' => $result['avg_compatibility'] ?? 0,
                    'max_distance' => $result['max_distance'] ?? 0,
                    'total_candidates_analyzed' => $result['candidates_analyzed'] ?? 0
                ]
            ];

            Log::info('Matches generated successfully', [
                'user_id' => $userId,
                'total_generated' => count($result['matches']),
                'avg_compatibility' => $result['avg_compatibility'] ?? 0
            ]);

            return $this->successResponse(
                $responseData,
                'Matches generated successfully',
                Response::HTTP_OK
            );

        } catch (\Exception $e) {
            Log::error('Error generating matches', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Failed to generate matches',
                ['error' => config('app.debug') ? $e->getMessage() : 'Internal server error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get user's matches list
     *
     * Retorna lista de matches actuales con opción de filtrado y paginación.
     *
     * Rate Limiting: 60 requests per minute (read operation)
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Matches retrieved successfully",
     *   "data": {
     *     "matches": [
     *       {
     *         "id": "uuid",
     *         "matched_user": {
     *           "id": "uuid",
     *           "name": "John Smith",
     *           "age": 30,
     *           "photos": ["url1"],
     *           "bio": "Engineer and traveler"
     *         },
     *         "compatibility_score": 0.82,
     *         "matched_at": "2025-10-15T14:20:00Z",
     *         "last_message_at": "2025-10-16T09:15:00Z",
     *         "unread_messages_count": 3,
     *         "is_active": true
     *       }
     *     ],
     *     "pagination": {
     *       "current_page": 1,
     *       "per_page": 20,
     *       "total": 45,
     *       "last_page": 3
     *     },
     *     "stats": {
     *       "total_matches": 45,
     *       "active_conversations": 12,
     *       "avg_compatibility": 0.75
     *     }
     *   }
     * }
     */
    public function getUserMatches(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            Log::info('Fetching user matches', [
                'user_id' => $userId,
                'page' => $request->input('page', 1)
            ]);

            // Build filters
            $filters = [
                'status' => $request->input('status', 'active'), // active, inactive, all
                'min_compatibility' => $request->input('min_compatibility'),
                'has_unread_messages' => $request->boolean('has_unread'),
                'sort_by' => $request->input('sort_by', 'recent'), // recent, compatibility, activity
                'per_page' => $request->input('per_page', 20)
            ];

            // Try cache for default filters
            $cacheKey = "user_matches:{$userId}:" . md5(json_encode($filters));
            
            if ($request->input('refresh', false) === false && Cache::has($cacheKey)) {
                $data = Cache::get($cacheKey);
                
                Log::info('User matches retrieved from cache', [
                    'user_id' => $userId,
                    'total' => $data['stats']['total_matches']
                ]);

                return $this->successResponse($data, 'Matches retrieved successfully');
            }

            // Fetch matches
            $result = $this->matchingService->getUserMatches($userId, $filters);

            $responseData = [
                'matches' => $result['matches'],
                'pagination' => $result['pagination'],
                'stats' => [
                    'total_matches' => $result['total_matches'],
                    'active_conversations' => $result['active_conversations'] ?? 0,
                    'avg_compatibility' => $result['avg_compatibility'] ?? 0
                ]
            ];

            // Cache for 30 minutes
            Cache::put($cacheKey, $responseData, self::CACHE_TTL);

            Log::info('User matches retrieved successfully', [
                'user_id' => $userId,
                'total' => $result['total_matches']
            ]);

            return $this->successResponse($responseData, 'Matches retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching user matches', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Failed to retrieve matches',
                ['error' => config('app.debug') ? $e->getMessage() : 'Internal server error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get match details
     *
     * Retorna detalles completos de un match específico incluyendo
     * compatibilidad detallada y contexto de la relación.
     *
     * Rate Limiting: 60 requests per minute (read operation)
     *
     * @param string $matchId
     * @return JsonResponse
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Match details retrieved successfully",
     *   "data": {
     *     "match": {
     *       "id": "uuid",
     *       "matched_user": {
     *         "id": "uuid",
     *         "name": "Sarah Johnson",
     *         "age": 27,
     *         "location": "Los Angeles, CA",
     *         "photos": ["url1", "url2", "url3"],
     *         "bio": "Artist and music lover",
     *         "interests": ["art", "music", "yoga"],
     *         "occupation": "Graphic Designer"
     *       },
     *       "compatibility": {
     *         "overall_score": 0.87,
     *         "factors": {
     *           "age": {"score": 0.95, "weight": 0.15},
     *           "location": {"score": 0.90, "distance_km": 12.3, "weight": 0.20},
     *           "interests": {"score": 0.85, "shared_count": 5, "weight": 0.25},
     *           "lifestyle": {"score": 0.88, "weight": 0.20},
     *           "personality": {"score": 0.82, "weight": 0.20}
     *         }
     *       },
     *       "matched_at": "2025-10-15T14:20:00Z",
     *       "conversation": {
     *         "message_count": 24,
     *         "last_message_at": "2025-10-16T09:45:00Z",
     *         "unread_count": 2
     *       },
     *       "shared_interests": ["art", "music", "yoga", "travel", "coffee"],
     *       "match_context": {
     *         "matched_through": "mutual_like",
     *         "first_interaction": "like",
     *         "days_matched": 1
     *       }
     *     }
     *   }
     * }
     */
    public function getMatchDetails(string $matchId): JsonResponse
    {
        try {
            $userId = auth()->id();

            Log::info('Fetching match details', [
                'user_id' => $userId,
                'match_id' => $matchId
            ]);

            // Try cache
            $cacheKey = "match_details:{$matchId}:{$userId}";
            
            if (Cache::has($cacheKey)) {
                $data = Cache::get($cacheKey);
                
                Log::info('Match details retrieved from cache', [
                    'match_id' => $matchId
                ]);

                return $this->successResponse($data, 'Match details retrieved successfully');
            }

            // Fetch match details
            $match = $this->matchingService->getMatchDetails($matchId, $userId);

            if (!$match) {
                Log::warning('Match not found', [
                    'user_id' => $userId,
                    'match_id' => $matchId
                ]);

                return $this->errorResponse(
                    'Match not found',
                    ['match_id' => 'The specified match does not exist or you do not have access'],
                    Response::HTTP_NOT_FOUND
                );
            }

            $responseData = ['match' => $match];

            // Cache for 30 minutes
            Cache::put($cacheKey, $responseData, self::CACHE_TTL);

            Log::info('Match details retrieved successfully', [
                'match_id' => $matchId,
                'compatibility_score' => $match['compatibility']['overall_score'] ?? 0
            ]);

            return $this->successResponse($responseData, 'Match details retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching match details', [
                'user_id' => auth()->id(),
                'match_id' => $matchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Failed to retrieve match details',
                ['error' => config('app.debug') ? $e->getMessage() : 'Internal server error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Unmatch a user
     *
     * Deshace un match existente y previene futuras interacciones.
     * Esta acción es irreversible.
     *
     * Rate Limiting: 10 requests per minute (write operation)
     *
     * @param string $matchId
     * @param Request $request
     * @return JsonResponse
     *
     * @response 200 {
     *   "success": true,
     *   "message": "User unmatched successfully",
     *   "data": {
     *     "match_id": "uuid",
     *     "unmatched_at": "2025-10-16T10:45:00Z",
     *     "reason": "no_connection"
     *   }
     * }
     */
    public function unmatchUser(string $matchId, Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            Log::info('Unmatch initiated', [
                'user_id' => $userId,
                'match_id' => $matchId
            ]);

            $validated = $request->validate([
                'reason' => 'nullable|string|in:no_connection,inappropriate,safety,other',
                'feedback' => 'nullable|string|max:500'
            ]);

            // Perform unmatch
            $result = $this->matchingService->unmatchUsers($matchId, $userId, $validated);

            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'] ?? 'Failed to unmatch user',
                    ['match_id' => $matchId],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Clear caches
            $this->clearMatchCaches($userId, $matchId);

            Log::info('User unmatched successfully', [
                'user_id' => $userId,
                'match_id' => $matchId,
                'reason' => $validated['reason'] ?? 'not_specified'
            ]);

            return $this->successResponse(
                [
                    'match_id' => $matchId,
                    'unmatched_at' => now()->toIso8601String(),
                    'reason' => $validated['reason'] ?? null
                ],
                'User unmatched successfully'
            );

        } catch (\Exception $e) {
            Log::error('Error unmatching user', [
                'user_id' => auth()->id(),
                'match_id' => $matchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Failed to unmatch user',
                ['error' => config('app.debug') ? $e->getMessage() : 'Internal server error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Calculate compatibility with another user
     *
     * Calcula compatibilidad en tiempo real sin crear match.
     * Útil para preview de compatibilidad antes de like.
     *
     * Rate Limiting: 30 requests per minute
     *
     * @param string $targetUserId
     * @return JsonResponse
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Compatibility calculated successfully",
     *   "data": {
     *     "compatibility": {
     *       "overall_score": 0.78,
     *       "factors": {
     *         "age": 0.85,
     *         "location": 0.75,
     *         "interests": 0.80,
     *         "lifestyle": 0.72,
     *         "personality": 0.78
     *       },
     *       "shared_interests": ["hiking", "coffee"],
     *       "distance_km": 18.5,
     *       "estimated_match_quality": "high"
     *     }
     *   }
     * }
     */
    public function calculateCompatibility(string $targetUserId): JsonResponse
    {
        try {
            $userId = auth()->id();

            if ($userId === $targetUserId) {
                return $this->errorResponse(
                    'Invalid target user',
                    ['target_user_id' => 'Cannot calculate compatibility with yourself'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            Log::info('Calculating compatibility', [
                'user_id' => $userId,
                'target_user_id' => $targetUserId
            ]);

            // Try cache
            $cacheKey = "compatibility:{$userId}:{$targetUserId}";
            
            if (Cache::has($cacheKey)) {
                $compatibility = Cache::get($cacheKey);
                
                return $this->successResponse(
                    ['compatibility' => $compatibility],
                    'Compatibility retrieved from cache'
                );
            }

            // Calculate compatibility
            $compatibility = $this->matchingService->calculateCompatibility($userId, $targetUserId);

            // Cache for 1 hour
            Cache::put($cacheKey, $compatibility, 3600);

            Log::info('Compatibility calculated successfully', [
                'user_id' => $userId,
                'target_user_id' => $targetUserId,
                'score' => $compatibility['overall_score']
            ]);

            return $this->successResponse(
                ['compatibility' => $compatibility],
                'Compatibility calculated successfully'
            );

        } catch (\Exception $e) {
            Log::error('Error calculating compatibility', [
                'user_id' => auth()->id(),
                'target_user_id' => $targetUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Failed to calculate compatibility',
                ['error' => config('app.debug') ? $e->getMessage() : 'Internal server error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get matching analytics
     *
     * Retorna analytics y métricas del historial de matching del usuario.
     *
     * Rate Limiting: 30 requests per minute
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Analytics retrieved successfully",
     *   "data": {
     *     "analytics": {
     *       "total_matches": 45,
     *       "active_matches": 32,
     *       "avg_compatibility": 0.76,
     *       "match_rate": 0.12,
     *       "response_rate": 0.68,
     *       "matches_last_30_days": 15,
     *       "most_compatible_match": {
     *         "score": 0.92,
     *         "user_name": "Jane D."
     *       },
     *       "compatibility_distribution": {
     *         "0.6-0.7": 8,
     *         "0.7-0.8": 22,
     *         "0.8-0.9": 12,
     *         "0.9-1.0": 3
     *       }
     *     }
     *   }
     * }
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            Log::info('Fetching match analytics', ['user_id' => $userId]);

            $timeframe = $request->input('timeframe', '30'); // días
            
            // Try cache
            $cacheKey = "match_analytics:{$userId}:{$timeframe}";
            
            if (Cache::has($cacheKey)) {
                $analytics = Cache::get($cacheKey);
                
                return $this->successResponse(
                    ['analytics' => $analytics],
                    'Analytics retrieved from cache'
                );
            }

            // Get analytics
            $analytics = $this->matchingService->getMatchAnalytics($userId, (int)$timeframe);

            // Cache for 1 hour
            Cache::put($cacheKey, $analytics, 3600);

            Log::info('Match analytics retrieved successfully', [
                'user_id' => $userId,
                'total_matches' => $analytics['total_matches'] ?? 0
            ]);

            return $this->successResponse(
                ['analytics' => $analytics],
                'Analytics retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('Error fetching match analytics', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Failed to retrieve analytics',
                ['error' => config('app.debug') ? $e->getMessage() : 'Internal server error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Clear match-related caches
     *
     * @param string $userId
     * @param string $matchId
     * @return void
     */
    private function clearMatchCaches(string $userId, string $matchId): void
    {
        $patterns = [
            "user_matches:{$userId}:*",
            "match_details:{$matchId}:*",
            "match_analytics:{$userId}:*"
        ];

        foreach ($patterns as $pattern) {
            try {
                Cache::forget($pattern);
            } catch (\Exception $e) {
                Log::warning('Failed to clear cache pattern', [
                    'pattern' => $pattern,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}