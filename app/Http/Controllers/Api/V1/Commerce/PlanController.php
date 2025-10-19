<?php

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Http\Controllers\Controller;
use App\Domain\Commerce\Services\PlanService;
use App\Http\Requests\Commerce\PurchaseRequest;
use App\Events\Commerce\PlanSubscribed;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class PlanController
 * 
 * Handles all subscription plan-related operations in the ForeverUsInLove platform.
 * Manages premium plans, subscriptions, upgrades/downgrades, and plan analytics.
 * 
 * @package App\Http\Controllers\Api\V1\Commerce
 * @version 1.0.0
 */
class PlanController extends Controller
{
    /**
     * @var PlanService
     */
    protected PlanService $planService;

    /**
     * PlanController constructor.
     *
     * @param PlanService $planService
     */
    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:30,1')->only(['subscribe', 'changePlan', 'cancelSubscription']);
        $this->middleware('throttle:100,1')->only(['index', 'show', 'features']);
    }

    /**
     * Get all available subscription plans.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/plans",
     *     summary="Get available plans",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tier",
     *         in="query",
     *         description="Filter by tier (basic, premium, vip, elite)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="billing_cycle",
     *         in="query",
     *         description="Filter by billing cycle (monthly, quarterly, annual)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="include_trial",
     *         in="query",
     *         description="Include plans with trial periods",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(response=200, description="Plans retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $filters = [
                'tier' => $request->input('tier'),
                'billing_cycle' => $request->input('billing_cycle'),
                'include_trial' => $request->boolean('include_trial', false),
            ];

            $cacheKey = 'available_plans_' . md5(json_encode($filters));
            
            $plans = Cache::remember($cacheKey, now()->addHours(6), function () use ($filters, $userId) {
                return $this->planService->getAvailablePlans($userId, $filters);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Plans retrieved successfully',
                'data' => [
                    'plans' => $plans['plans'],
                    'user_current_plan' => $plans['user_current_plan'],
                    'comparison_matrix' => $plans['comparison_matrix'],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve plans', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve plans',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get details of a specific plan.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/plans/{planId}",
     *     summary="Get plan details",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="planId",
     *         in="path",
     *         description="Plan ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Plan details retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Plan not found")
     * )
     *
     * @param int $planId
     * @return JsonResponse
     */
    public function show(int $planId): JsonResponse
    {
        try {
            $userId = Auth::id();

            $plan = Cache::remember("plan_details_{$planId}", now()->addHours(6), function () use ($planId, $userId) {
                return $this->planService->getPlanDetails($planId, $userId);
            });

            if (!$plan) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Plan not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Plan details retrieved successfully',
                'data' => [
                    'plan' => $plan,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve plan details', [
                'user_id' => $userId ?? null,
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve plan details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get personalized plan recommendations for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/plans/recommendations",
     *     summary="Get plan recommendations",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="include_reasons",
     *         in="query",
     *         description="Include recommendation reasons",
     *         required=false,
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Response(response=200, description="Recommendations retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recommendations(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $includeReasons = $request->boolean('include_reasons', true);

            $recommendations = $this->planService->getPlanRecommendations($userId, $includeReasons);

            return response()->json([
                'status' => 'success',
                'message' => 'Plan recommendations retrieved successfully',
                'data' => [
                    'recommended_plans' => $recommendations['plans'],
                    'user_behavior_profile' => $recommendations['user_profile'],
                    'recommendation_reasons' => $includeReasons ? $recommendations['reasons'] : null,
                ],
                'meta' => [
                    'ml_model_version' => $recommendations['model_version'],
                    'confidence_score' => $recommendations['confidence_score'],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve plan recommendations', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve plan recommendations',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get plan features comparison.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/plans/features",
     *     summary="Get plan features comparison",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="plan_ids",
     *         in="query",
     *         description="Comma-separated plan IDs to compare",
     *         required=false,
     *         @OA\Schema(type="string", example="1,2,3")
     *     ),
     *     @OA\Response(response=200, description="Features retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function features(Request $request): JsonResponse
    {
        try {
            $planIds = $request->input('plan_ids')
                ? explode(',', $request->input('plan_ids'))
                : null;

            $cacheKey = 'plan_features_' . ($planIds ? md5(implode(',', $planIds)) : 'all');
            
            $features = Cache::remember($cacheKey, now()->addHours(12), function () use ($planIds) {
                return $this->planService->getPlanFeaturesComparison($planIds);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Plan features retrieved successfully',
                'data' => [
                    'features_matrix' => $features,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve plan features', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve plan features',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Subscribe user to a plan.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/plans/subscribe",
     *     summary="Subscribe to a plan",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"plan_id", "payment_method_id"},
     *             @OA\Property(property="plan_id", type="integer", example=2),
     *             @OA\Property(property="payment_method_id", type="integer", example=1),
     *             @OA\Property(property="billing_cycle", type="string", example="monthly"),
     *             @OA\Property(property="coupon_code", type="string", example="PREMIUM50"),
     *             @OA\Property(property="start_trial", type="boolean", example=true),
     *             @OA\Property(property="auto_renew", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Subscription successful"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=402, description="Payment failed")
     * )
     *
     * @param PurchaseRequest $request
     * @return JsonResponse
     */
    public function subscribe(PurchaseRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $subscriptionData = [
                'user_id' => $userId,
                'plan_id' => $request->input('plan_id'),
                'payment_method_id' => $request->input('payment_method_id'),
                'billing_cycle' => $request->input('billing_cycle', 'monthly'),
                'coupon_code' => $request->input('coupon_code'),
                'start_trial' => $request->boolean('start_trial', false),
                'auto_renew' => $request->boolean('auto_renew', true),
            ];

            // Subscribe user through domain service
            $result = $this->planService->subscribeUserToPlan($subscriptionData);

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'error_code' => $result['error_code'],
                ], $result['status_code'] ?? 402);
            }

            DB::commit();

            // Broadcast plan subscribed event
            broadcast(new PlanSubscribed(
                $result['subscription'],
                $userId,
                $result['plan']
            ))->toOthers();

            return response()->json([
                'status' => 'success',
                'message' => 'Subscription successful',
                'data' => [
                    'subscription' => $result['subscription'],
                    'plan' => $result['plan'],
                    'activated_features' => $result['activated_features'],
                    'next_billing_date' => $result['next_billing_date'],
                    'trial_ends_at' => $result['trial_ends_at'] ?? null,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to subscribe to plan', [
                'user_id' => $userId ?? null,
                'plan_id' => $request->input('plan_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to subscribe to plan',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Change user's current subscription plan.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/plans/change",
     *     summary="Change subscription plan",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"new_plan_id"},
     *             @OA\Property(property="new_plan_id", type="integer", example=3),
     *             @OA\Property(property="billing_cycle", type="string", example="annual"),
     *             @OA\Property(property="prorate", type="boolean", example=true),
     *             @OA\Property(property="immediate", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Plan changed successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=400, description="No active subscription")
     * )
     *
     * @param PurchaseRequest $request
     * @return JsonResponse
     */
    public function changePlan(PurchaseRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $changeData = [
                'user_id' => $userId,
                'new_plan_id' => $request->input('new_plan_id'),
                'billing_cycle' => $request->input('billing_cycle'),
                'prorate' => $request->boolean('prorate', true),
                'immediate' => $request->boolean('immediate', true),
            ];

            // Change plan through domain service
            $result = $this->planService->changeSubscriptionPlan($changeData);

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'error_code' => $result['error_code'],
                ], $result['status_code'] ?? 400);
            }

            DB::commit();

            // Broadcast plan subscribed event for the new plan
            broadcast(new PlanSubscribed(
                $result['subscription'],
                $userId,
                $result['new_plan']
            ))->toOthers();

            return response()->json([
                'status' => 'success',
                'message' => 'Plan changed successfully',
                'data' => [
                    'subscription' => $result['subscription'],
                    'old_plan' => $result['old_plan'],
                    'new_plan' => $result['new_plan'],
                    'proration_details' => $result['proration_details'],
                    'effective_date' => $result['effective_date'],
                    'next_billing_date' => $result['next_billing_date'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to change plan', [
                'user_id' => $userId ?? null,
                'new_plan_id' => $request->input('new_plan_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to change plan',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get current subscription for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/plans/subscription",
     *     summary="Get current subscription",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Subscription retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="No active subscription")
     * )
     *
     * @return JsonResponse
     */
    public function currentSubscription(): JsonResponse
    {
        try {
            $userId = Auth::id();

            $subscription = $this->planService->getUserCurrentSubscription($userId);

            if (!$subscription) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No active subscription found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Subscription retrieved successfully',
                'data' => [
                    'subscription' => $subscription,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve current subscription', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve current subscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get subscription history for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/plans/subscription/history",
     *     summary="Get subscription history",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(response=200, description="History retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function subscriptionHistory(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $perPage = $request->input('per_page', 20);

            $history = $this->planService->getUserSubscriptionHistory($userId, $perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Subscription history retrieved successfully',
                'data' => [
                    'subscriptions' => $history['subscriptions'],
                    'total' => $history['total'],
                    'current_page' => $history['current_page'],
                    'last_page' => $history['last_page'],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve subscription history', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve subscription history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Cancel user's subscription.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/plans/subscription/cancel",
     *     summary="Cancel subscription",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Too expensive"),
     *             @OA\Property(property="cancel_immediately", type="boolean", example=false),
     *             @OA\Property(property="feedback", type="string", example="Great service but I can't afford it right now")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Subscription cancelled successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="No active subscription"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $cancelData = [
                'user_id' => $userId,
                'reason' => $request->input('reason'),
                'cancel_immediately' => $request->boolean('cancel_immediately', false),
                'feedback' => $request->input('feedback'),
            ];

            $result = $this->planService->cancelSubscription($cancelData);

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
                'message' => 'Subscription cancelled successfully',
                'data' => [
                    'cancelled_subscription' => $result['subscription'],
                    'access_until' => $result['access_until'],
                    'refund_amount' => $result['refund_amount'] ?? null,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to cancel subscription', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel subscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Reactivate a cancelled subscription.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/plans/subscription/reactivate",
     *     summary="Reactivate subscription",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="payment_method_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Subscription reactivated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="No cancelled subscription found")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reactivateSubscription(Request $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $paymentMethodId = $request->input('payment_method_id');

            $result = $this->planService->reactivateSubscription($userId, $paymentMethodId);

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
                'message' => 'Subscription reactivated successfully',
                'data' => [
                    'subscription' => $result['subscription'],
                    'next_billing_date' => $result['next_billing_date'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to reactivate subscription', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reactivate subscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update subscription payment method.
     *
     * @OA\Put(
     *     path="/api/v1/commerce/plans/subscription/payment-method",
     *     summary="Update subscription payment method",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_method_id"},
     *             @OA\Property(property="payment_method_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payment method updated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="No active subscription")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePaymentMethod(Request $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $paymentMethodId = $request->input('payment_method_id');

            $result = $this->planService->updateSubscriptionPaymentMethod($userId, $paymentMethodId);

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
                'message' => 'Payment method updated successfully',
                'data' => [
                    'subscription' => $result['subscription'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update payment method', [
                'user_id' => $userId ?? null,
                'payment_method_id' => $request->input('payment_method_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update payment method',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Apply a coupon to the subscription.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/plans/subscription/coupon",
     *     summary="Apply coupon to subscription",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"coupon_code"},
     *             @OA\Property(property="coupon_code", type="string", example="SAVE20")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Coupon applied successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="No active subscription or invalid coupon"),
     *     @OA\Response(response=422, description="Coupon already applied or expired")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $userId = Auth::id();
            $couponCode = $request->input('coupon_code');

            $result = $this->planService->applyCouponToSubscription($userId, $couponCode);

            if (!$result['success']) {
                DB::rollBack();
                
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], $result['status_code'] ?? 404);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Coupon applied successfully',
                'data' => [
                    'subscription' => $result['subscription'],
                    'discount_amount' => $result['discount_amount'],
                    'new_price' => $result['new_price'],
                    'valid_until' => $result['valid_until'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to apply coupon', [
                'user_id' => $userId ?? null,
                'coupon_code' => $request->input('coupon_code'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to apply coupon',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get plan analytics and metrics.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/plans/analytics",
     *     summary="Get plan analytics",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Analytics retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @return JsonResponse
     */
    public function analytics(): JsonResponse
    {
        try {
            $userId = Auth::id();

            $analytics = $this->planService->getUserPlanAnalytics($userId);

            return response()->json([
                'status' => 'success',
                'message' => 'Plan analytics retrieved successfully',
                'data' => [
                    'subscription_history' => $analytics['subscription_history'],
                    'total_spent' => $analytics['total_spent'],
                    'feature_usage' => $analytics['feature_usage'],
                    'usage_limits' => $analytics['usage_limits'],
                    'value_metrics' => $analytics['value_metrics'],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve plan analytics', [
                'user_id' => $userId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve plan analytics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get global plan statistics and trends.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/plans/statistics",
     *     summary="Get plan statistics",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Statistics retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        try {
            $cacheKey = 'plan_statistics';
            
            $statistics = Cache::remember($cacheKey, now()->addHours(1), function () {
                return $this->planService->getGlobalPlanStatistics();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Plan statistics retrieved successfully',
                'data' => [
                    'popular_plans' => $statistics['popular_plans'],
                    'conversion_rates' => $statistics['conversion_rates'],
                    'churn_rates' => $statistics['churn_rates'],
                    'average_lifetime_value' => $statistics['average_ltv'],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve plan statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve plan statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Check feature access for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/plans/features/check/{featureName}",
     *     summary="Check feature access",
     *     tags={"Commerce - Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="featureName",
     *         in="path",
     *         description="Feature name to check",
     *         required=true,
     *         @OA\Schema(type="string", example="unlimited_likes")
     *     ),
     *     @OA\Response(response=200, description="Feature access checked"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @param string $featureName
     * @return JsonResponse
     */
    public function checkFeatureAccess(string $featureName): JsonResponse
    {
        try {
            $userId = Auth::id();

            $access = $this->planService->checkUserFeatureAccess($userId, $featureName);

            return response()->json([
                'status' => 'success',
                'message' => 'Feature access checked',
                'data' => [
                    'has_access' => $access['has_access'],
                    'feature' => $access['feature'],
                    'current_plan' => $access['current_plan'],
                    'upgrade_required' => $access['upgrade_required'],
                    'suggested_plans' => $access['suggested_plans'] ?? [],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to check feature access', [
                'user_id' => $userId ?? null,
                'feature_name' => $featureName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check feature access',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}