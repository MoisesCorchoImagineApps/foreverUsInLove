<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Matching;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Requests\Matching\StartDiscoverySessionRequest;
use App\Http\Requests\Matching\GenerateExploreCardsRequest;
use App\Http\Requests\Matching\GenerateLocalCardsRequest;
use App\Http\Requests\Matching\ApplyBoostRequest;
use App\Domain\Matching\Services\DiscoveryService;
use App\Domain\Matching\ValueObjects\DiscoveryMode;
use App\Domain\Matching\ValueObjects\DiscoveryRadius;
use App\Domain\Matching\ValueObjects\ExplorationCriteria;
use App\Domain\Matching\Exceptions\DiscoveryException;
use App\Domain\Matching\Exceptions\InsufficientCandidatesException;
use App\Domain\Matching\Exceptions\DiscoverySessionExpiredException;
use App\Infrastructure\External\GoogleMapsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * ========================================================================
 * DISCOVERY CONTROLLER - User Discovery & Exploration
 * ========================================================================
 * 
 * Manages user discovery sessions, profile exploration, and serendipitous 
 * matching experiences in the ForeverUsInLove dating platform.
 * 
 * Framework: Laravel 12 | PHP 8.2+
 * Architecture: DDD + Clean Architecture
 * 
 * INTEGRACIONES:
 * - ✅ GoogleMapsService: Cálculo de distancias y geolocalización
 * 
 * FUNCIONALIDADES:
 * - ✅ Sesiones de discovery personalizadas
 * - ✅ Múltiples modos (standard, explore, local)
 * - ✅ Boosts de visibilidad
 * - ✅ Filtrado geográfico con GoogleMaps
 * - ✅ Tarjetas de discovery con insights
 * - ✅ Analytics de engagement
 * 
 * ENDPOINTS:
 * - POST   /discovery/session/start        - Start new discovery session
 * - GET    /discovery/session/current      - Get current active session
 * - GET    /discovery/session/next-card    - Get next discovery card
 * - POST   /discovery/cards/explore        - Generate explore mode cards
 * - POST   /discovery/cards/local          - Generate local discovery cards
 * - POST   /discovery/boost                - Apply discovery boost
 * - GET    /discovery/boost/status         - Check active boost status
 * - GET    /discovery/analytics            - Get discovery analytics
 * 
 * @package App\Http\Controllers\Api\V1\Matching
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 * @created 2025-10-17
 * 
 * ========================================================================
 */
class DiscoveryController extends Controller
{
    use ApiResponseTrait;

    /**
     * Constructor con inyección de dependencias
     * 
     * @param DiscoveryService $discoveryService Servicio de discovery
     * @param GoogleMapsService $googleMapsService Servicio de Google Maps
     */
    public function __construct(
        private readonly DiscoveryService $discoveryService,
        private readonly GoogleMapsService $googleMapsService
    ) {}

    /**
     * Start new discovery session
     * 
     * Creates a personalized discovery session with curated profiles based on
     * selected mode, user preferences, and engagement patterns.
     * 
     * @param StartDiscoverySessionRequest $request Validated discovery session request
     * @return JsonResponse Discovery session with cards
     * 
     * @response 201 {
     *   "success": true,
     *   "message": "Discovery session started successfully",
     *   "data": {
     *     "session": {
     *       "session_id": "uuid-here",
     *       "mode": "explore",
     *       "cards_generated": 20,
     *       "expires_at": "2025-10-16T14:30:00Z",
     *       "is_premium": true
     *     },
     *     "first_card": {
     *       "card_id": "uuid",
     *       "profile": {...},
     *       "insights": {...}
     *     }
     *   }
     * }
     */
    public function startSession(StartDiscoverySessionRequest $request): JsonResponse
    {
        try {
            Log::info('Discovery: Starting discovery session', [
                'user_id' => auth()->id(),
                'mode' => $request->mode,
                'is_premium' => auth()->user()->isPremium()
            ]);

            $user = auth()->user();
            $mode = DiscoveryMode::fromString($request->mode);
            $isPremium = $user->isPremium();

            // Build discovery preferences if provided
            $preferences = $request->has('preferences') 
                ? $this->buildDiscoveryPreferences($request->preferences)
                : null;

            DB::beginTransaction();

            try {
                // Start discovery session
                $session = $this->discoveryService->startDiscoverySession(
                    userId: $user->id,
                    mode: $mode,
                    preferences: $preferences,
                    isPremium: $isPremium
                );

                // Get first card immediately
                $firstCard = $session->getNextCard();

                // Si la card tiene ubicación, calcular distancia con GoogleMaps
                if ($firstCard && $user->latitude && $user->longitude) {
                    $cardProfile = $firstCard->getProfile();
                    
                    if ($cardProfile->getLatitude() && $cardProfile->getLongitude()) {
                        try {
                            $distance = $this->googleMapsService->calculateDistance(
                                $user->latitude,
                                $user->longitude,
                                $cardProfile->getLatitude(),
                                $cardProfile->getLongitude()
                            );

                            // Actualizar distancia en la card
                            $firstCard->setDistanceKm($distance);

                            Log::debug('Discovery: Distance calculated for first card', [
                                'distance_km' => $distance
                            ]);

                        } catch (\Exception $e) {
                            Log::warning('Discovery: Failed to calculate distance for first card', [
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }

                DB::commit();

                $responseData = [
                    'session' => [
                        'session_id' => $session->getId()->toString(),
                        'mode' => $session->getMode()->getValue(),
                        'cards_generated' => $session->getCards()->count(),
                        'cards_remaining' => $session->getRemainingCards(),
                        'expires_at' => $session->getExpiresAt()->toIso8601String(),
                        'is_premium' => $session->isPremium(),
                        'max_cards' => $session->getMaxCards()
                    ],
                    'first_card' => $firstCard ? $this->formatDiscoveryCard($firstCard) : null
                ];

                Log::info('Discovery: Session started successfully', [
                    'session_id' => $session->getId()->toString(),
                    'user_id' => $user->id,
                    'cards_count' => $session->getCards()->count()
                ]);

                return $this->successResponse(
                    $responseData,
                    'Discovery session started successfully',
                    201
                );

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (InsufficientCandidatesException $e) {
            Log::warning('Discovery: Insufficient candidates for session', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Not enough profiles available for discovery. Try adjusting your preferences.',
                422,
                ['error_code' => 'INSUFFICIENT_CANDIDATES']
            );

        } catch (DiscoveryException $e) {
            Log::error('Discovery: Session creation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to start discovery session: ' . $e->getMessage(),
                500
            );

        } catch (\Exception $e) {
            Log::error('Discovery: Unexpected error starting session', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'An unexpected error occurred while starting discovery session',
                500
            );
        }
    }

    /**
     * Get current active discovery session
     * 
     * @return JsonResponse Current session data or null
     */
    public function getCurrentSession(): JsonResponse
    {
        try {
            Log::debug('Discovery: Retrieving current session', [
                'user_id' => auth()->id()
            ]);

            $user = auth()->user();
            $session = $this->discoveryService->getCurrentSession($user->id);

            if (!$session) {
                return $this->successResponse(
                    ['session' => null],
                    'No active discovery session found'
                );
            }

            $sessionData = [
                'session' => [
                    'session_id' => $session->getId()->toString(),
                    'mode' => $session->getMode()->getValue(),
                    'cards_remaining' => $session->getRemainingCards(),
                    'cards_viewed' => $session->getViewedCardsCount(),
                    'expires_at' => $session->getExpiresAt()->toIso8601String(),
                    'is_premium' => $session->isPremium(),
                    'started_at' => $session->getStartedAt()->toIso8601String()
                ]
            ];

            return $this->successResponse($sessionData, 'Current session retrieved');

        } catch (DiscoverySessionExpiredException $e) {
            Log::info('Discovery: Session expired', [
                'user_id' => auth()->id()
            ]);

            return $this->successResponse(
                ['session' => null],
                'Discovery session has expired'
            );

        } catch (\Exception $e) {
            Log::error('Discovery: Error retrieving current session', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to retrieve current session',
                500
            );
        }
    }

    /**
     * Get next discovery card
     * 
     * @return JsonResponse Next discovery card or session complete
     */
    public function getNextCard(): JsonResponse
    {
        try {
            Log::debug('Discovery: Getting next card', [
                'user_id' => auth()->id()
            ]);

            $user = auth()->user();
            $nextCard = $this->discoveryService->getNextCard($user->id);

            if (!$nextCard) {
                Log::info('Discovery: No more cards available', [
                    'user_id' => $user->id
                ]);

                return $this->successResponse(
                    [
                        'card' => null,
                        'session_complete' => true,
                        'message' => 'Discovery session completed'
                    ],
                    'No more cards available in current session'
                );
            }

            // Calcular distancia con GoogleMaps si hay ubicación
            if ($user->latitude && $user->longitude) {
                $cardProfile = $nextCard->getProfile();
                
                if ($cardProfile->getLatitude() && $cardProfile->getLongitude()) {
                    try {
                        $distance = $this->googleMapsService->calculateDistance(
                            $user->latitude,
                            $user->longitude,
                            $cardProfile->getLatitude(),
                            $cardProfile->getLongitude()
                        );

                        $nextCard->setDistanceKm($distance);

                        Log::debug('Discovery: Distance calculated for card', [
                            'card_id' => $nextCard->getId()->toString(),
                            'distance_km' => $distance
                        ]);

                    } catch (\Exception $e) {
                        Log::warning('Discovery: Failed to calculate distance', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Get session for progress tracking
            $session = $this->discoveryService->getCurrentSession($user->id);

            $responseData = [
                'card' => $this->formatDiscoveryCard($nextCard),
                'progress' => [
                    'cards_viewed' => $session->getViewedCardsCount(),
                    'cards_remaining' => $session->getRemainingCards(),
                    'total_cards' => $session->getMaxCards()
                ],
                'session_complete' => false
            ];

            return $this->successResponse(
                $responseData,
                'Next discovery card retrieved'
            );

        } catch (DiscoverySessionExpiredException $e) {
            Log::info('Discovery: Session expired while getting card', [
                'user_id' => auth()->id()
            ]);

            return $this->errorResponse(
                'Discovery session has expired. Please start a new session.',
                410,
                ['error_code' => 'SESSION_EXPIRED']
            );

        } catch (\Exception $e) {
            Log::error('Discovery: Error getting next card', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to get next discovery card',
                500
            );
        }
    }

    /**
     * Generate explore mode cards
     * 
     * Creates discovery cards that broaden horizons with profiles outside
     * typical preferences while maintaining compatibility potential.
     * 
     * @param GenerateExploreCardsRequest $request
     * @return JsonResponse Explore mode discovery cards
     */
    public function generateExploreCards(GenerateExploreCardsRequest $request): JsonResponse
    {
        try {
            Log::info('Discovery: Generating explore mode cards', [
                'user_id' => auth()->id(),
                'card_count' => $request->card_count ?? 15
            ]);

            $user = auth()->user();
            $cardCount = $request->card_count ?? 15;

            // Build exploration criteria
            $criteria = new ExplorationCriteria([
                'expand_age_range' => $request->expand_age_range ?? 10,
                'expand_radius' => $request->expand_radius ?? 50,
                'include_different_interests' => $request->include_different_interests ?? true,
                'expand_education' => $request->expand_education ?? false,
                'expand_lifestyle' => $request->expand_lifestyle ?? false
            ]);

            $exploreCards = $this->discoveryService->generateExploreCards(
                userId: $user->id,
                criteria: $criteria,
                cardCount: $cardCount
            );

            // Calcular distancias con GoogleMaps para todas las cards
            if ($user->latitude && $user->longitude) {
                try {
                    $exploreCards = $exploreCards->map(function ($card) use ($user) {
                        $profile = $card->getProfile();
                        
                        if ($profile->getLatitude() && $profile->getLongitude()) {
                            $distance = $this->googleMapsService->calculateDistance(
                                $user->latitude,
                                $user->longitude,
                                $profile->getLatitude(),
                                $profile->getLongitude()
                            );

                            $card->setDistanceKm($distance);
                        }

                        return $card;
                    });

                    Log::debug('Discovery: Distances calculated for explore cards', [
                        'cards_count' => $exploreCards->count()
                    ]);

                } catch (\Exception $e) {
                    Log::warning('Discovery: Failed to calculate distances for explore cards', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $responseData = [
                'cards' => $exploreCards->map(fn($card) => $this->formatDiscoveryCard($card))->values(),
                'cards_generated' => $exploreCards->count(),
                'exploration_insights' => [
                    'diversity_score' => $this->calculateDiversityScore($exploreCards),
                    'expanded_criteria' => $criteria->toArray(),
                    'typical_preferences' => $this->getUserTypicalPreferences($user)
                ]
            ];

            Log::info('Discovery: Explore cards generated successfully', [
                'user_id' => $user->id,
                'cards_count' => $exploreCards->count()
            ]);

            return $this->successResponse(
                $responseData,
                'Explore mode cards generated successfully'
            );

        } catch (DiscoveryException $e) {
            Log::error('Discovery: Explore cards generation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to generate explore cards: ' . $e->getMessage(),
                500
            );

        } catch (\Exception $e) {
            Log::error('Discovery: Unexpected error generating explore cards', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'An unexpected error occurred',
                500
            );
        }
    }

    /**
     * Generate local discovery cards
     * 
     * Creates discovery cards focused on nearby users using GoogleMaps
     * for accurate distance calculation.
     * 
     * @param GenerateLocalCardsRequest $request
     * @return JsonResponse Local discovery cards
     */
    public function generateLocalCards(GenerateLocalCardsRequest $request): JsonResponse
    {
        try {
            Log::info('Discovery: Generating local cards', [
                'user_id' => auth()->id(),
                'radius_km' => $request->radius_km ?? 25
            ]);

            $user = auth()->user();

            // Validar que el usuario tenga ubicación
            if (!$user->latitude || !$user->longitude) {
                Log::warning('Discovery: User has no location for local discovery', [
                    'user_id' => $user->id
                ]);

                return $this->errorResponse(
                    'Please enable location services to use local discovery',
                    422,
                    ['error_code' => 'LOCATION_REQUIRED']
                );
            }

            $radiusKm = $request->radius_km ?? 25;
            $cardCount = $request->card_count ?? 20;
            $prioritizeActivity = $request->prioritize_activity ?? true;

            $radius = DiscoveryRadius::fromKilometers($radiusKm);

            $localCards = $this->discoveryService->generateLocalCards(
                userId: $user->id,
                radius: $radius,
                cardCount: $cardCount,
                prioritizeActivity: $prioritizeActivity
            );

            // Calcular distancias precisas con GoogleMaps
            $cardsWithDistance = [];
            $totalDistance = 0;
            $minDistance = PHP_FLOAT_MAX;

            foreach ($localCards as $card) {
                $profile = $card->getProfile();
                
                if ($profile->getLatitude() && $profile->getLongitude()) {
                    try {
                        $distance = $this->googleMapsService->calculateDistance(
                            $user->latitude,
                            $user->longitude,
                            $profile->getLatitude(),
                            $profile->getLongitude()
                        );

                        // Solo incluir si está dentro del radio
                        if ($distance <= $radiusKm) {
                            $card->setDistanceKm($distance);
                            $cardsWithDistance[] = $card;
                            $totalDistance += $distance;
                            $minDistance = min($minDistance, $distance);
                        }

                    } catch (\Exception $e) {
                        Log::warning('Discovery: Failed to calculate distance for card', [
                            'card_id' => $card->getId()->toString(),
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Ordenar por distancia (más cercanos primero)
            usort($cardsWithDistance, function ($a, $b) {
                return $a->getDistanceKm() <=> $b->getDistanceKm();
            });

            $avgDistance = count($cardsWithDistance) > 0 
                ? round($totalDistance / count($cardsWithDistance), 1)
                : 0;

            $closestDistance = $minDistance !== PHP_FLOAT_MAX ? round($minDistance, 1) : 0;

            $responseData = [
                'cards' => array_map(
                    fn($card) => $this->formatDiscoveryCard($card),
                    $cardsWithDistance
                ),
                'cards_generated' => count($cardsWithDistance),
                'local_insights' => [
                    'search_radius_km' => $radiusKm,
                    'average_distance_km' => $avgDistance,
                    'closest_match_km' => $closestDistance,
                    'prioritized_activity' => $prioritizeActivity,
                    'user_location' => [
                        'lat' => $user->latitude,
                        'lng' => $user->longitude
                    ]
                ]
            ];

            Log::info('Discovery: Local cards generated successfully', [
                'user_id' => $user->id,
                'cards_count' => count($cardsWithDistance),
                'avg_distance' => $avgDistance
            ]);

            return $this->successResponse(
                $responseData,
                'Local discovery cards generated successfully'
            );

        } catch (DiscoveryException $e) {
            Log::error('Discovery: Local cards generation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to generate local cards: ' . $e->getMessage(),
                500
            );

        } catch (\Exception $e) {
            Log::error('Discovery: Unexpected error generating local cards', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'An unexpected error occurred',
                500
            );
        }
    }

    /**
     * Apply discovery boost
     * 
     * @param ApplyBoostRequest $request
     * @return JsonResponse Boost application result
     */
    public function applyBoost(ApplyBoostRequest $request): JsonResponse
    {
        try {
            Log::info('Discovery: Applying boost', [
                'user_id' => auth()->id(),
                'boost_type' => $request->boost_type,
                'duration_minutes' => $request->duration_minutes ?? 60
            ]);

            $user = auth()->user();
            $boostType = $request->boost_type;
            $durationMinutes = $request->duration_minutes ?? 60;

            DB::beginTransaction();

            try {
                // Build boost configuration
                $boost = new DiscoveryBoost([
                    'type' => $boostType,
                    'multiplier' => $this->getBoostMultiplier($boostType),
                    'target_modes' => $request->target_modes ?? ['standard', 'explore']
                ]);

                $success = $this->discoveryService->applyDiscoveryBoost(
                    userId: $user->id,
                    boost: $boost,
                    durationMinutes: $durationMinutes
                );

                if (!$success) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Failed to apply discovery boost',
                        500
                    );
                }

                DB::commit();

                $expiresAt = Carbon::now()->addMinutes($durationMinutes);

                $responseData = [
                    'boost' => [
                        'type' => $boostType,
                        'multiplier' => $boost->getMultiplier(),
                        'duration_minutes' => $durationMinutes,
                        'applied_at' => Carbon::now()->toIso8601String(),
                        'expires_at' => $expiresAt->toIso8601String(),
                        'target_modes' => $boost->getTargetModes()
                    ]
                ];

                Log::info('Discovery: Boost applied successfully', [
                    'user_id' => $user->id,
                    'boost_type' => $boostType,
                    'expires_at' => $expiresAt
                ]);

                return $this->successResponse(
                    $responseData,
                    'Discovery boost applied successfully'
                );

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (DiscoveryException $e) {
            Log::error('Discovery: Boost application failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to apply boost: ' . $e->getMessage(),
                422
            );

        } catch (\Exception $e) {
            Log::error('Discovery: Unexpected error applying boost', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'An unexpected error occurred',
                500
            );
        }
    }

    /**
     * Check active boost status
     * 
     * @return JsonResponse Active boost data or null
     */
    public function getBoostStatus(): JsonResponse
    {
        try {
            $user = auth()->user();
            $activeBoost = $this->discoveryService->getActiveBoost($user->id);

            if (!$activeBoost) {
                return $this->successResponse(
                    ['active_boost' => null],
                    'No active boost found'
                );
            }

            $expiresAt = Carbon::parse($activeBoost['expires_at']);
            $timeRemaining = Carbon::now()->diffInMinutes($expiresAt, false);

            $boostData = [
                'active_boost' => [
                    'type' => $activeBoost['boost']->getType(),
                    'multiplier' => $activeBoost['boost']->getMultiplier(),
                    'applied_at' => Carbon::parse($activeBoost['applied_at'])->toIso8601String(),
                    'expires_at' => $expiresAt->toIso8601String(),
                    'time_remaining_minutes' => max(0, $timeRemaining),
                    'is_active' => $timeRemaining > 0
                ]
            ];

            return $this->successResponse($boostData, 'Active boost retrieved');

        } catch (\Exception $e) {
            Log::error('Discovery: Error checking boost status', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to check boost status',
                500
            );
        }
    }

    /**
     * Get discovery analytics
     * 
     * @return JsonResponse Discovery analytics
     */
    public function getAnalytics(): JsonResponse
    {
        try {
            Log::info('Discovery: Retrieving analytics', [
                'user_id' => auth()->id()
            ]);

            $user = auth()->user();
            $since = Carbon::now()->subMonth();
            $until = Carbon::now();

            $analytics = $this->discoveryService->getDiscoveryAnalytics(
                userId: $user->id,
                since: $since,
                until: $until
            );

            return $this->successResponse(
                ['analytics' => $analytics],
                'Discovery analytics retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('Discovery: Error retrieving analytics', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Failed to retrieve analytics',
                500
            );
        }
    }

    // ================================================================
    // PRIVATE HELPER METHODS
    // ================================================================

    /**
     * Format discovery card for API response
     */
    private function formatDiscoveryCard($card): array
    {
        $profile = $card->getProfile();
        
        return [
            'card_id' => $card->getId()->toString(),
            'profile' => [
                'user_id' => $profile->getUserId()->toString(),
                'name' => $profile->getName(),
                'age' => $profile->getAge(),
                'photos' => $profile->getPhotos()->map(fn($photo) => [
                    'url' => $photo->getUrl(),
                    'is_primary' => $photo->isPrimary()
                ])->values(),
                'bio' => $profile->getBio(),
                'location' => [
                    'city' => $profile->getLocation()->getCity(),
                    'distance_km' => $card->getDistanceKm()
                ],
                'interests' => $profile->getInterests()
            ],
            'insights' => [
                'compatibility_score' => $card->getCompatibilityScore(),
                'shared_interests' => $card->getSharedInterests(),
                'compatibility_highlights' => $card->getCompatibilityHighlights(),
                'distance_km' => $card->getDistanceKm()
            ],
            'discovery_context' => [
                'mode' => $card->getDiscoveryMode()->getValue(),
                'card_position' => $card->getPosition(),
                'generated_at' => $card->getGeneratedAt()->toIso8601String()
            ]
        ];
    }

    /**
     * Build discovery preferences from request
     */
    private function buildDiscoveryPreferences(array $preferencesData): DiscoveryPreferences
    {
        return new DiscoveryPreferences([
            'radius' => isset($preferencesData['radius']) 
                ? DiscoveryRadius::fromKilometers($preferencesData['radius']) 
                : null,
            'age_range' => $preferencesData['age_range'] ?? null,
            'include_verified_only' => $preferencesData['verified_only'] ?? false,
            'exclude_seen_profiles' => $preferencesData['exclude_seen'] ?? true,
            'prioritize_active_users' => $preferencesData['prioritize_active'] ?? true
        ]);
    }

    /**
     * Calculate diversity score for explore cards
     */
    private function calculateDiversityScore($cards): float
    {
        // Implementation would analyze how different the cards are from typical preferences
        return 0.82; // Placeholder
    }

    /**
     * Get user's typical preferences
     */
    private function getUserTypicalPreferences($user): array
    {
        // Implementation would analyze user's past interactions
        return [
            'typical_age_range' => [25, 35],
            'typical_distance' => 25,
            'typical_interests' => ['travel', 'music']
        ];
    }

    /**
     * Get boost multiplier based on type
     */
    private function getBoostMultiplier(string $boostType): float
    {
        return match($boostType) {
            'standard' => 2.0,
            'premium' => 5.0,
            'super' => 10.0,
            default => 1.0
        };
    }
}