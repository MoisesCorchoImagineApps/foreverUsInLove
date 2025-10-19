<?php

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Http\Controllers\Controller;
use App\Domain\Commerce\Services\GiftService;
use App\Http\Requests\Commerce\PurchaseRequest;
use App\Events\Commerce\GiftSent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class GiftController
 * 
 * Handles all gift-related operations in the ForeverUsInLove platform.
 * Manages virtual gift catalog, sending, recommendations, scheduling, and analytics.
 * 
 * @package App\Http\Controllers\Api\V1\Commerce
 * @version 1.0.0
 */
class GiftController extends Controller
{
    /**
     * @var GiftService
     */
    protected GiftService $giftService;

    /**
     * GiftController constructor.
     *
     * @param GiftService $giftService
     */
    public function __construct(GiftService $giftService)
    {
        $this->giftService = $giftService;
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:60,1')->only(['store', 'bulkSend']);
        $this->middleware('throttle:100,1')->only(['index', 'show', 'history']);
    }

    /**
     * Get the complete gift catalog with optional filters.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/gifts",
     *     summary="Get gift catalog",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="collection_id",
     *         in="query",
     *         description="Filter by collection ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price in coins",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price in coins",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="rarity",
     *         in="query",
     *         description="Filter by rarity (common, rare, epic, legendary)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="occasion",
     *         in="query",
     *         description="Filter by occasion (birthday, anniversary, valentine, etc.)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort by (popular, newest, price_asc, price_desc)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(response=200, description="Gift catalog retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'category_id' => $request->input('category_id'),
                'collection_id' => $request->input('collection_id'),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'rarity' => $request->input('rarity'),
                'occasion' => $request->input('occasion'),
                'sort' => $request->input('sort', 'popular'),
                'per_page' => $request->input('per_page', 20),
            ];

            $cacheKey = 'gift_catalog_' . md5(json_encode($filters));
            
            $catalog = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($filters) {
                return $this->giftService->getGiftCatalog($filters);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Gift catalog retrieved successfully',
                'data' => [
                    'gifts' => $catalog['gifts'],
                    'total' => $catalog['total'],
                    'current_page' => $catalog['current_page'],
                    'last_page' => $catalog['last_page'],
                    'per_page' => $catalog['per_page'],
                ],
                'meta' => [
                    'filters_applied' => array_filter($filters),
                    'cached_at' => now()->toIso8601String(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve gift catalog', [
                'error' => $e->getMessage(),
                'filters' => $filters ?? [],
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve gift catalog',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get gift categories.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/gifts/categories",
     *     summary="Get gift categories",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Categories retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @return JsonResponse
     */
    public function categories(): JsonResponse
    {
        try {
            $categories = Cache::remember('gift_categories', now()->addHours(24), function () {
                return $this->giftService->getGiftCategories();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Gift categories retrieved successfully',
                'data' => [
                    'categories' => $categories,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve gift categories', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve gift categories',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get gift collections.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/gifts/collections",
     *     summary="Get gift collections",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="featured",
     *         in="query",
     *         description="Get only featured collections",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(response=200, description="Collections retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function collections(Request $request): JsonResponse
    {
        try {
            $featuredOnly = $request->boolean('featured', false);

            $cacheKey = 'gift_collections_' . ($featuredOnly ? 'featured' : 'all');
            
            $collections = Cache::remember($cacheKey, now()->addHours(12), function () use ($featuredOnly) {
                return $this->giftService->getGiftCollections($featuredOnly);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Gift collections retrieved successfully',
                'data' => [
                    'collections' => $collections,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve gift collections', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve gift collections',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get personalized gift recommendations for a specific user.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/gifts/recommendations/{recipientId}",
     *     summary="Get personalized gift recommendations",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="recipientId",
     *         in="path",
     *         description="Recipient user ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="occasion",
     *         in="query",
     *         description="Occasion type",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of recommendations",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(response=200, description="Recommendations retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Recipient not found")
     * )
     *
     * @param Request $request
     * @param int $recipientId
     * @return JsonResponse
     */
    public function recommendations(Request $request, int $recipientId): JsonResponse
    {
        try {
            $userId = Auth::id();
            $occasion = $request->input('occasion');
            $limit = $request->input('limit', 10);

            $recommendations = $this->giftService->getPersonalizedRecommendations(
                $userId,
                $recipientId,
                $occasion,
                $limit
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Gift recommendations retrieved successfully',
                'data' => [
                    'recommendations' => $recommendations['gifts'],
                    'recommendation_reasons' => $recommendations['reasons'],
                    'recipient_preferences' => $recommendations['recipient_preferences'],
                ],
                'meta' => [
                    'ml_model_version' => $recommendations['model_version'],
                    'confidence_score' => $recommendations['confidence_score'],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve gift recommendations', [
                'user_id' => $userId ?? null,
                'recipient_id' => $recipientId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve gift recommendations',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get trending and popular gifts.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/gifts/trending",
     *     summary="Get trending gifts",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Time period (24h, 7d, 30d)",
     *         required=false,
     *         @OA\Schema(type="string", default="7d")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of results",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(response=200, description="Trending gifts retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trending(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', '7d');
            $limit = $request->input('limit', 20);

            $cacheKey = "trending_gifts_{$period}_{$limit}";
            
            $trending = Cache::remember($cacheKey, now()->addHours(1), function () use ($period, $limit) {
                return $this->giftService->getTrendingGifts($period, $limit);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Trending gifts retrieved successfully',
                'data' => [
                    'trending_gifts' => $trending['gifts'],
                    'popularity_metrics' => $trending['metrics'],
                ],
                'meta' => [
                    'period' => $period,
                    'updated_at' => now()->toIso8601String(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve trending gifts', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve trending gifts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get details of a specific gift.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/gifts/{giftId}",
     *     summary="Get gift details",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="giftId",
     *         in="path",
     *         description="Gift ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Gift details retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Gift not found")
     * )
     *
     * @param int $giftId
     * @return JsonResponse
     */
    public function show(int $giftId): JsonResponse
    {
        try {
            $gift = Cache::remember("gift_details_{$giftId}", now()->addMinutes(30), function () use ($giftId) {
                return $this->giftService->getGiftDetails($giftId);
            });

            if (!$gift) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gift not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Gift details retrieved successfully',
                'data' => [
                    'gift' => $gift,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve gift details', [
                'gift_id' => $giftId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve gift details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Send a gift to a user.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/gifts/send",
     *     summary="Send a gift",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"gift_id", "recipient_id"},
     *             @OA\Property(property="gift_id", type="integer", example=1),
     *             @OA\Property(property="recipient_id", type="integer", example=123),
     *             @OA\Property(property="message", type="string", example="Happy Birthday!"),
     *             @OA\Property(property="anonymous", type="boolean", example=false),
     *             @OA\Property(property="quantity", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Gift sent successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=402, description="Insufficient coins")
     * )
     *
     * @param PurchaseRequest $request
     * @return JsonResponse
     */
    public function store(PurchaseRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $giftId = $request->input('gift_id');
            $recipientId = $request->input('recipient_id');
            $message = $request->input('message');
            $anonymous = $request->boolean('anonymous', false);
            $quantity = $request->input('quantity', 1);

            // Send gift through domain service
            $result = $this->giftService->sendGift(
                $userId,
                $recipientId,
                $giftId,
                $message,
                $anonymous,
                $quantity
            );

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'error_code' => $result['error_code'],
                ], $result['status_code'] ?? 400);
            }

            DB::commit();

            // Broadcast gift sent event
            broadcast(new GiftSent(
                $result['gift_transaction'],
                $userId,
                $recipientId
            ))->toOthers();

            return response()->json([
                'status' => 'success',
                'message' => 'Gift sent successfully',
                'data' => [
                    'transaction' => $result['gift_transaction'],
                    'remaining_balance' => $result['remaining_balance'],
                    'gift_details' => $result['gift_details'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to send gift', [
                'user_id' => $userId ?? null,
                'gift_id' => $giftId ?? null,
                'recipient_id' => $recipientId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send gift',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Send gifts to multiple users at once.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/gifts/send-bulk",
     *     summary="Send gifts to multiple users",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"gift_id", "recipient_ids"},
     *             @OA\Property(property="gift_id", type="integer", example=1),
     *             @OA\Property(property="recipient_ids", type="array", @OA\Items(type="integer"), example={123, 456, 789}),
     *             @OA\Property(property="message", type="string", example="Thank you all!"),
     *             @OA\Property(property="anonymous", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Bulk gifts sent successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=402, description="Insufficient coins")
     * )
     *
     * @param PurchaseRequest $request
     * @return JsonResponse
     */
    public function bulkSend(PurchaseRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $giftId = $request->input('gift_id');
            $recipientIds = $request->input('recipient_ids');
            $message = $request->input('message');
            $anonymous = $request->boolean('anonymous', false);

            // Send bulk gifts through domain service
            $result = $this->giftService->sendBulkGifts(
                $userId,
                $recipientIds,
                $giftId,
                $message,
                $anonymous
            );

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'error_code' => $result['error_code'],
                ], $result['status_code'] ?? 400);
            }

            DB::commit();

            // Broadcast gift sent events to all recipients
            foreach ($result['transactions'] as $transaction) {
                broadcast(new GiftSent(
                    $transaction,
                    $userId,
                    $transaction['recipient_id']
                ))->toOthers();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk gifts sent successfully',
                'data' => [
                    'successful_sends' => $result['successful_sends'],
                    'failed_sends' => $result['failed_sends'],
                    'total_recipients' => count($recipientIds),
                    'transactions' => $result['transactions'],
                    'remaining_balance' => $result['remaining_balance'],
                    'total_cost' => $result['total_cost'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to send bulk gifts', [
                'user_id' => $userId ?? null,
                'gift_id' => $giftId ?? null,
                'recipient_count' => count($recipientIds ?? []),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send bulk gifts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Schedule a gift to be sent at a specific time or occasion.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/gifts/schedule",
     *     summary="Schedule a gift delivery",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"gift_id", "recipient_id", "scheduled_at"},
     *             @OA\Property(property="gift_id", type="integer", example=1),
     *             @OA\Property(property="recipient_id", type="integer", example=123),
     *             @OA\Property(property="message", type="string", example="Happy Birthday!"),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time", example="2024-12-25T00:00:00Z"),
     *             @OA\Property(property="occasion", type="string", example="birthday"),
     *             @OA\Property(property="anonymous", type="boolean", example=false),
     *             @OA\Property(property="quantity", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Gift scheduled successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     *
     * @param PurchaseRequest $request
     * @return JsonResponse
     */
    public function schedule(PurchaseRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $giftId = $request->input('gift_id');
            $recipientId = $request->input('recipient_id');
            $message = $request->input('message');
            $scheduledAt = $request->input('scheduled_at');
            $occasion = $request->input('occasion');
            $anonymous = $request->boolean('anonymous', false);
            $quantity = $request->input('quantity', 1);

            // Schedule gift through domain service
            $result = $this->giftService->scheduleOccasionGift(
                $userId,
                $recipientId,
                $giftId,
                $scheduledAt,
                $occasion,
                $message,
                $anonymous,
                $quantity
            );

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'error_code' => $result['error_code'],
                ], $result['status_code'] ?? 400);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gift scheduled successfully',
                'data' => [
                    'scheduled_gift' => $result['scheduled_gift'],
                    'estimated_send_time' => $result['estimated_send_time'],
                    'reserved_coins' => $result['reserved_coins'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to schedule gift', [
                'user_id' => $userId ?? null,
                'gift_id' => $giftId ?? null,
                'recipient_id' => $recipientId ?? null,
                'scheduled_at' => $scheduledAt ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to schedule gift',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get gift sending history for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/gifts/history/sent",
     *     summary="Get sent gifts history",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date filter",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date filter",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="recipient_id",
     *         in="query",
     *         description="Filter by recipient",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(response=200, description="Sent gifts history retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sentHistory(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $filters = [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'recipient_id' => $request->input('recipient_id'),
                'per_page' => $request->input('per_page', 20),
            ];

            $history = $this->giftService->getUserSentGiftsHistory($userId, $filters);

            return response()->json([
                'status' => 'success',
                'message' => 'Sent gifts history retrieved successfully',
                'data' => [
                    'sent_gifts' => $history['gifts'],
                    'total' => $history['total'],
                    'total_spent' => $history['total_spent'],
                    'current_page' => $history['current_page'],
                    'last_page' => $history['last_page'],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve sent gifts history', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve sent gifts history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get gift receiving history for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/gifts/history/received",
     *     summary="Get received gifts history",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date filter",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date filter",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="sender_id",
     *         in="query",
     *         description="Filter by sender",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(response=200, description="Received gifts history retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function receivedHistory(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $filters = [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'sender_id' => $request->input('sender_id'),
                'per_page' => $request->input('per_page', 20),
            ];

            $history = $this->giftService->getUserReceivedGiftsHistory($userId, $filters);

            return response()->json([
                'status' => 'success',
                'message' => 'Received gifts history retrieved successfully',
                'data' => [
                    'received_gifts' => $history['gifts'],
                    'total' => $history['total'],
                    'total_value' => $history['total_value'],
                    'current_page' => $history['current_page'],
                    'last_page' => $history['last_page'],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve received gifts history', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve received gifts history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get scheduled gifts for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/gifts/scheduled",
     *     summary="Get scheduled gifts",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (pending, sent, cancelled)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Scheduled gifts retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function scheduled(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $status = $request->input('status', 'pending');

            $scheduledGifts = $this->giftService->getUserScheduledGifts($userId, $status);

            return response()->json([
                'status' => 'success',
                'message' => 'Scheduled gifts retrieved successfully',
                'data' => [
                    'scheduled_gifts' => $scheduledGifts,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve scheduled gifts', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve scheduled gifts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Cancel a scheduled gift.
     *
     * @OA\Delete(
     *     path="/api/v1/commerce/gifts/scheduled/{scheduledGiftId}",
     *     summary="Cancel scheduled gift",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="scheduledGiftId",
     *         in="path",
     *         description="Scheduled gift ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Scheduled gift cancelled successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Scheduled gift not found")
     * )
     *
     * @param int $scheduledGiftId
     * @return JsonResponse
     */
    public function cancelScheduled(int $scheduledGiftId): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();

            $result = $this->giftService->cancelScheduledGift($userId, $scheduledGiftId);

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], $result['status_code'] ?? 400);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Scheduled gift cancelled successfully',
                'data' => [
                    'refunded_coins' => $result['refunded_coins'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to cancel scheduled gift', [
                'user_id' => $userId ?? null,
                'scheduled_gift_id' => $scheduledGiftId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel scheduled gift',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get gift analytics for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/gifts/analytics",
     *     summary="Get gift analytics",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Time period (7d, 30d, 90d, 1y, all)",
     *         required=false,
     *         @OA\Schema(type="string", default="30d")
     *     ),
     *     @OA\Response(response=200, description="Analytics retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $period = $request->input('period', '30d');

            $analytics = $this->giftService->getUserGiftAnalytics($userId, $period);

            return response()->json([
                'status' => 'success',
                'message' => 'Gift analytics retrieved successfully',
                'data' => [
                    'sent_analytics' => $analytics['sent'],
                    'received_analytics' => $analytics['received'],
                    'popular_gifts' => $analytics['popular_gifts'],
                    'top_recipients' => $analytics['top_recipients'],
                    'top_senders' => $analytics['top_senders'],
                    'spending_trends' => $analytics['spending_trends'],
                ],
                'meta' => [
                    'period' => $period,
                    'generated_at' => now()->toIso8601String(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve gift analytics', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve gift analytics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get global gift popularity trends.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/gifts/analytics/global",
     *     summary="Get global gift trends",
     *     tags={"Commerce - Gifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Time period",
     *         required=false,
     *         @OA\Schema(type="string", default="7d")
     *     ),
     *     @OA\Response(response=200, description="Global trends retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function globalTrends(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', '7d');

            $cacheKey = "global_gift_trends_{$period}";
            
            $trends = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($period) {
                return $this->giftService->getGlobalGiftTrends($period);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Global gift trends retrieved successfully',
                'data' => [
                    'trends' => $trends,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve global gift trends', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve global gift trends',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}