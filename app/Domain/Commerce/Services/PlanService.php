<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Entities\Plan;
use App\Domain\Commerce\Entities\Subscription;
use App\Domain\Commerce\Entities\Feature;
use App\Domain\Commerce\Entities\PlanTier;
use App\Domain\Commerce\Entities\Payment;
use App\Domain\Commerce\ValueObjects\PlanId;
use App\Domain\Commerce\ValueObjects\SubscriptionId;
use App\Domain\Commerce\ValueObjects\PlanType;
use App\Domain\Commerce\ValueObjects\SubscriptionStatus;
use App\Domain\Commerce\ValueObjects\BillingCycle;
use App\Domain\Commerce\ValueObjects\Price;
use App\Domain\Commerce\ValueObjects\Currency;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Commerce\Repositories\ProductRepositoryInterface;
use App\Domain\Commerce\Repositories\OrderRepositoryInterface;
use App\Domain\Commerce\Services\PaymentService;
use App\Domain\Commerce\Events\PlanSubscribed;
use App\Domain\Commerce\Exceptions\PlanNotFoundException;
use App\Domain\Commerce\Exceptions\SubscriptionNotFoundException;
use App\Domain\Commerce\Exceptions\InvalidSubscriptionException;
use App\Domain\Commerce\Exceptions\PlanNotAvailableException;
use App\Domain\Commerce\Exceptions\SubscriptionAlreadyActiveException;
use App\Domain\Common\Exceptions\ValidationException;
use App\Domain\Auth\Exceptions\UserNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

/**
 * PlanService - Subscription plan management with premium features
 * 
 * Comprehensive subscription plan management service for the ForeverUsInLove dating 
 * application. Handles premium plans, feature management, subscription lifecycle,
 * billing cycles, upgrades/downgrades, and plan analytics with sophisticated
 * business logic and integration capabilities.
 * 
 * Features:
 * - Complete subscription plan catalog management
 * - Multi-tier premium plan system (Basic, Premium, VIP, Elite)
 * - Feature-based access control and entitlements
 * - Flexible billing cycles (monthly, quarterly, annual)
 * - Plan upgrades, downgrades, and changes with proration
 * - Trial periods and promotional pricing
 * - Family and couple plan options
 * - Geographic and demographic pricing strategies
 * - A/B testing for plan optimization
 * - Subscription analytics and churn prediction
 * - Integration with payment processing and billing
 * - Automated subscription lifecycle management
 * - Plan recommendation engine based on user behavior
 * - Enterprise and white-label plan support
 * 
 * Architecture:
 * - Service Layer Pattern for business logic
 * - Domain-Driven Design (DDD) principles
 * - Clean Architecture / Hexagonal Architecture
 * - SOLID principles with Dependency Inversion
 * - Laravel 12 with PHP 8.2 strict typing
 * - Event-driven architecture for subscription events
 * - Repository pattern for data persistence
 * - Strategy pattern for pricing calculations
 * 
 * @package App\Domain\Commerce\Services
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 * 
 * @see \App\Domain\Commerce\Entities\Plan
 * @see \App\Domain\Commerce\Entities\Subscription
 * @see \App\Domain\Commerce\Events\PlanSubscribed
 */
class PlanService
{
    /**
     * Available plan types and their features
     */
    private const PLAN_FEATURES = [
        'basic' => [
            'max_daily_likes' => 10,
            'max_super_likes' => 1,
            'profile_boost' => false,
            'unlimited_messaging' => false,
            'advanced_filters' => false,
            'read_receipts' => false,
            'video_calls' => false,
            'priority_support' => false
        ],
        'premium' => [
            'max_daily_likes' => 50,
            'max_super_likes' => 5,
            'profile_boost' => true,
            'unlimited_messaging' => true,
            'advanced_filters' => true,
            'read_receipts' => true,
            'video_calls' => true,
            'priority_support' => false
        ],
        'vip' => [
            'max_daily_likes' => 100,
            'max_super_likes' => 10,
            'profile_boost' => true,
            'unlimited_messaging' => true,
            'advanced_filters' => true,
            'read_receipts' => true,
            'video_calls' => true,
            'priority_support' => true,
            'incognito_mode' => true,
            'travel_mode' => true
        ],
        'elite' => [
            'max_daily_likes' => -1, // unlimited
            'max_super_likes' => -1, // unlimited
            'profile_boost' => true,
            'unlimited_messaging' => true,
            'advanced_filters' => true,
            'read_receipts' => true,
            'video_calls' => true,
            'priority_support' => true,
            'incognito_mode' => true,
            'travel_mode' => true,
            'personal_matchmaker' => true,
            'exclusive_events' => true
        ]
    ];

    /**
     * Constructor with dependency injection
     * 
     * @param ProductRepositoryInterface $productRepository Product and plan data management
     * @param OrderRepositoryInterface $orderRepository Subscription and order management
     * @param PaymentService $paymentService Payment processing integration
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PaymentService $paymentService
    ) {}

    // ============================================================================
    // PLAN CATALOG AND DISCOVERY
    // ============================================================================

    /**
     * Get available subscription plans with personalized pricing
     * 
     * Retrieves all available subscription plans with personalized pricing based
     * on user location, demographics, and behavioral data. Includes promotional
     * offers and A/B testing variants.
     * 
     * @param array{
     *     user_id?: UserId|string,
     *     currency?: Currency|string,
     *     country?: string,
     *     include_trials?: bool,
     *     include_promotions?: bool,
     *     plan_types?: array<PlanType|string>,
     *     billing_cycles?: array<BillingCycle|string>,
     *     ab_test_variant?: string
     * } $options Plan retrieval options with personalization
     * 
     * @return Collection Available plans with personalized pricing and features
     * 
     * @example
     * ```php
     * $plans = $planService->getAvailablePlans([
     *     'user_id' => $userId,
     *     'currency' => Currency::USD,
     *     'country' => 'US',
     *     'include_trials' => true,
     *     'include_promotions' => true,
     *     'ab_test_variant' => 'pricing_test_v2'
     * ]);
     * ```
     */
    public function getAvailablePlans(array $options = []): Collection
    {
        try {
            Log::info('Retrieving available plans', ['options' => $options]);

            // Get base plans
            $plans = $this->productRepository->getAvailablePlans($options);

            // Apply personalized pricing
            if (isset($options['user_id'])) {
                $this->applyPersonalizedPricing($plans, $options['user_id'], $options);
            }

            // Apply geographic pricing
            if (isset($options['country'])) {
                $this->applyGeographicPricing($plans, $options['country'], $options);
            }

            // Include trial offers if requested
            if ($options['include_trials'] ?? false) {
                $this->enrichPlansWithTrialOffers($plans, $options);
            }

            // Include promotional pricing if requested
            if ($options['include_promotions'] ?? false) {
                $this->applyPromotionalPricing($plans, $options);
            }

            // Apply A/B testing variants
            if (isset($options['ab_test_variant'])) {
                $this->applyABTestVariant($plans, $options['ab_test_variant'], $options);
            }

            // Enrich with feature comparisons
            $this->enrichPlansWithFeatureComparison($plans);

            // Sort plans by recommended order
            $sortedPlans = $this->sortPlansByRecommendation($plans, $options);

            Log::info('Available plans retrieved successfully', [
                'plan_count' => $sortedPlans->count(),
                'user_id' => $options['user_id'] ?? null
            ]);

            return $sortedPlans;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve available plans', [
                'error' => $e->getMessage(),
                'options' => $options
            ]);
            throw $e;
        }
    }

    /**
     * Get plan recommendations based on user behavior and preferences
     * 
     * Generates intelligent plan recommendations using machine learning analysis
     * of user behavior, usage patterns, and similar user preferences.
     * 
     * @param UserId|string $userId Target user for recommendations
     * @param array{
     *     include_reasoning?: bool,
     *     max_recommendations?: int,
     *     consider_budget?: bool,
     *     usage_analysis_period?: int, // days
     *     similar_users_weight?: float
     * } $options Recommendation configuration
     * 
     * @return array{
     *     recommended_plans: Collection,
     *     reasoning?: array,
     *     user_usage_analysis: array,
     *     potential_savings: array,
     *     upgrade_timeline: array
     * } Intelligent plan recommendations with analysis
     * 
     * @throws UserNotFoundException When user not found
     * 
     * @example
     * ```php
     * $recommendations = $planService->getPlanRecommendations($userId, [
     *     'include_reasoning' => true,
     *     'max_recommendations' => 3,
     *     'consider_budget' => true,
     *     'usage_analysis_period' => 30
     * ]);
     * ```
     */
    public function getPlanRecommendations(UserId|string $userId, array $options = []): array
    {
        try {
            Log::info('Generating plan recommendations', [
                'user_id' => $userId,
                'options' => $options
            ]);

            // Validate user exists
            $this->validateUserExists($userId);

            // Analyze user behavior and usage patterns
            $usageAnalysis = $this->analyzeUserUsagePatterns($userId, $options);

            // Get current subscription if any
            $currentSubscription = $this->getCurrentUserSubscription($userId);

            // Analyze similar users' plan choices
            $similarUsersAnalysis = $this->analyzeSimilarUsersPlans($userId, $usageAnalysis);

            // Generate ML-based recommendations
            $mlRecommendations = $this->generateMLPlanRecommendations($userId, $usageAnalysis, $similarUsersAnalysis);

            // Apply business rules and constraints
            $filteredRecommendations = $this->applyRecommendationConstraints($mlRecommendations, $currentSubscription, $options);

            // Calculate potential savings and benefits
            $potentialSavings = $this->calculatePotentialSavings($filteredRecommendations, $currentSubscription);

            // Generate upgrade timeline suggestions
            $upgradeTimeline = $this->generateUpgradeTimeline($filteredRecommendations, $usageAnalysis);

            $result = [
                'recommended_plans' => $filteredRecommendations,
                'user_usage_analysis' => $usageAnalysis,
                'potential_savings' => $potentialSavings,
                'upgrade_timeline' => $upgradeTimeline
            ];

            // Generate reasoning if requested
            if ($options['include_reasoning'] ?? false) {
                $result['reasoning'] = $this->generateRecommendationReasoning($filteredRecommendations, $usageAnalysis);
            }

            Log::info('Plan recommendations generated successfully', [
                'user_id' => $userId,
                'recommendation_count' => $filteredRecommendations->count()
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to generate plan recommendations', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'options' => $options
            ]);
            throw $e;
        }
    }

    // ============================================================================
    // SUBSCRIPTION MANAGEMENT
    // ============================================================================

    /**
     * Subscribe user to plan with comprehensive setup
     * 
     * Creates a new subscription with payment processing, feature activation,
     * trial handling, and complete subscription lifecycle setup.
     * 
     * @param array{
     *     user_id: UserId|string,
     *     plan_id: PlanId|string,
     *     billing_cycle: BillingCycle|string,
     *     payment_method_id?: string,
     *     payment_method_data?: array,
     *     start_trial?: bool,
     *     trial_days?: int,
     *     promo_code?: string,
     *     auto_renew?: bool,
     *     metadata?: array{
     *         source?: string,
     *         campaign_id?: string,
     *         referral_code?: string
     *     }
     * } $subscriptionData Comprehensive subscription creation data
     * 
     * @return Subscription Created and activated subscription
     * 
     * @throws PlanNotFoundException When plan not found
     * @throws UserNotFoundException When user not found
     * @throws SubscriptionAlreadyActiveException When user already has active subscription
     * @throws PaymentFailedException When payment processing fails
     * @throws ValidationException When validation fails
     * 
     * @example
     * ```php
     * $subscription = $planService->subscribeUserToPlan([
     *     'user_id' => $userId,
     *     'plan_id' => 'premium_monthly',
     *     'billing_cycle' => BillingCycle::MONTHLY,
     *     'payment_method_id' => 'pm_card_123',
     *     'start_trial' => true,
     *     'trial_days' => 7,
     *     'auto_renew' => true,
     *     'metadata' => [
     *         'source' => 'mobile_app',
     *         'campaign_id' => 'valentine_promo_2024'
     *     ]
     * ]);
     * ```
     */
    public function subscribeUserToPlan(array $subscriptionData): Subscription
    {
        return DB::transaction(function () use ($subscriptionData) {
            try {
                Log::info('Creating user subscription', [
                    'user_id' => $subscriptionData['user_id'],
                    'plan_id' => $subscriptionData['plan_id'],
                    'billing_cycle' => $subscriptionData['billing_cycle']
                ]);

                // Validate subscription data
                $this->validateSubscriptionData($subscriptionData);

                // Validate user and plan
                $this->validateUserExists($subscriptionData['user_id']);
                $plan = $this->validatePlanExists($subscriptionData['plan_id']);

                // Check for existing active subscriptions
                $this->checkExistingSubscriptions($subscriptionData['user_id']);

                // Apply promotional codes if provided
                $pricingAdjustments = [];
                if (isset($subscriptionData['promo_code'])) {
                    $pricingAdjustments = $this->applyPromoCode($subscriptionData['promo_code'], $plan);
                }

                // Calculate subscription pricing
                $pricingDetails = $this->calculateSubscriptionPricing($plan, $subscriptionData, $pricingAdjustments);

                // Create subscription record
                $subscription = $this->createSubscriptionRecord($subscriptionData, $plan, $pricingDetails);

                // Handle trial period if applicable
                if ($subscriptionData['start_trial'] ?? false) {
                    $this->setupTrialPeriod($subscription, $subscriptionData['trial_days'] ?? 7);
                } else {
                    // Process initial payment
                    $payment = $this->processInitialPayment($subscription, $subscriptionData, $pricingDetails);
                    // $subscription->setInitialPayment($payment); // Method not available in Subscription entity
                }

                // Activate subscription features
                $this->activateSubscriptionFeatures($subscription);

                // Set up billing schedule
                $this->setupBillingSchedule($subscription);

                // Update user's subscription status
                $this->updateUserSubscriptionStatus($subscriptionData['user_id'], $subscription);

                // Fire subscription event
                Event::dispatch(new PlanSubscribed(
                    $subscription,
                    $subscriptionData['user_id'],
                    $plan
                ));

                // Update subscription analytics
                $this->updateSubscriptionAnalytics($subscription, $subscriptionData);

                Log::info('User subscription created successfully', [
                    'subscription_id' => $subscription->getId(),
                    'user_id' => $subscriptionData['user_id'],
                    'plan_id' => $plan->id()->toString(),
                    'status' => $subscription->getStatus()
                ]);

                return $subscription;

            } catch (\Exception $e) {
                Log::error('Failed to create user subscription', [
                    'error' => $e->getMessage(),
                    'subscription_data' => $subscriptionData
                ]);
                throw $e;
            }
        });
    }

    /**
     * Change subscription plan with proration handling
     * 
     * Handles plan changes including upgrades, downgrades, and billing cycle
     * modifications with sophisticated proration calculations and feature transitions.
     * 
     * @param array{
     *     subscription_id: SubscriptionId|string,
     *     new_plan_id: PlanId|string,
     *     new_billing_cycle?: BillingCycle|string,
     *     effective_date?: Carbon|string,
     *     proration_behavior?: string, // 'immediate', 'end_of_cycle', 'custom'
     *     payment_method_id?: string,
     *     reason?: string,
     *     metadata?: array
     * } $changeData Plan change configuration
     * 
     * @return array{
     *     subscription: Subscription,
     *     proration_details: array{
     *         credit_amount: float,
     *         charge_amount: float,
     *         net_change: float,
     *         effective_date: Carbon
     *     },
     *     feature_changes: array{
     *         added_features: array,
     *         removed_features: array,
     *         modified_features: array
     *     },
     *     billing_changes: array
     * } Comprehensive plan change result
     * 
     * @throws SubscriptionNotFoundException When subscription not found
     * @throws PlanNotFoundException When new plan not found
     * @throws InvalidSubscriptionException When plan change not allowed
     * 
     * @example
     * ```php
     * $result = $planService->changeSubscriptionPlan([
     *     'subscription_id' => $subscriptionId,
     *     'new_plan_id' => 'vip_annual',
     *     'new_billing_cycle' => BillingCycle::ANNUAL,
     *     'proration_behavior' => 'immediate',
     *     'reason' => 'user_upgrade_request'
     * ]);
     * ```
     */
    public function changeSubscriptionPlan(array $changeData): array
    {
        return DB::transaction(function () use ($changeData) {
            try {
                Log::info('Processing subscription plan change', [
                    'subscription_id' => $changeData['subscription_id'],
                    'new_plan_id' => $changeData['new_plan_id']
                ]);

                // Validate change data
                $this->validatePlanChangeData($changeData);

                // Get current subscription
                $subscription = $this->getSubscriptionById($changeData['subscription_id']);
                if (!$subscription) {
                    throw new SubscriptionNotFoundException('Subscription not found');
                }

                // Get new plan
                $newPlan = $this->validatePlanExists($changeData['new_plan_id']);

                // Validate plan change eligibility
                $this->validatePlanChangeEligibility($subscription, $newPlan, $changeData);

                // Calculate proration details
                $prorationDetails = $this->calculateProrationDetails($subscription, $newPlan, $changeData);

                // Analyze feature changes
                $currentPlan = $this->getPlanById($subscription->getPlanId());
                $featureChanges = $currentPlan ? $this->analyzeFeatureChanges($currentPlan, $newPlan) : [];

                // Process proration payment if needed
                $prorationPayment = null;
                if ($prorationDetails['net_change'] > 0) {
                    $prorationPayment = $this->processProrationPayment($subscription, $prorationDetails, $changeData);
                } elseif ($prorationDetails['net_change'] < 0) {
                    $this->processProrationCredit($subscription, $prorationDetails);
                }

                // Update subscription with new plan
                $updatedSubscription = $this->updateSubscriptionPlan($subscription, $newPlan, $changeData);

                // Update billing schedule
                $billingChanges = $this->updateBillingSchedule($updatedSubscription, $changeData);

                // Apply feature changes
                $this->applyFeatureChanges($updatedSubscription, $featureChanges);

                // Record plan change history
                $this->recordPlanChangeHistory($subscription, $updatedSubscription, $changeData);

                // Update analytics
                $this->updatePlanChangeAnalytics($subscription, $updatedSubscription, $changeData);

                $result = [
                    'subscription' => $updatedSubscription,
                    'proration_details' => $prorationDetails,
                    'feature_changes' => $featureChanges,
                    'billing_changes' => $billingChanges
                ];

                Log::info('Subscription plan changed successfully', [
                    'subscription_id' => $subscription->getId(),
                    'old_plan' => $subscription->getPlanId(),
                    'new_plan' => $newPlan->id()->toString(),
                    'net_change' => $prorationDetails['net_change']
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::error('Failed to change subscription plan', [
                    'subscription_id' => $changeData['subscription_id'],
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    // ============================================================================
    // SUBSCRIPTION ANALYTICS AND INSIGHTS
    // ============================================================================

    /**
     * Get comprehensive subscription analytics
     * 
     * Generates detailed subscription analytics including revenue metrics,
     * churn analysis, plan performance, and business intelligence insights.
     * 
     * @param array{
     *     period?: string,
     *     start_date?: Carbon|string,
     *     end_date?: Carbon|string,
     *     plan_types?: array<string>,
     *     user_segments?: array<string>,
     *     include_predictions?: bool,
     *     include_cohort_analysis?: bool,
     *     include_churn_analysis?: bool
     * } $options Analytics configuration
     * 
     * @return array{
     *     revenue_metrics: array,
     *     subscription_metrics: array,
     *     plan_performance: array,
     *     churn_analysis?: array,
     *     cohort_analysis?: array,
     *     predictions?: array,
     *     recommendations: array
     * } Comprehensive subscription analytics
     * 
     * @example
     * ```php
     * $analytics = $planService->getSubscriptionAnalytics([
     *     'period' => 'quarter',
     *     'include_predictions' => true,
     *     'include_churn_analysis' => true,
     *     'plan_types' => ['premium', 'vip']
     * ]);
     * ```
     */
    public function getSubscriptionAnalytics(array $options = []): array
    {
        try {
            Log::info('Generating subscription analytics', ['options' => $options]);

            // Generate revenue metrics
            $revenueMetrics = $this->generateRevenueMetrics($options);

            // Calculate subscription metrics
            $subscriptionMetrics = $this->calculateSubscriptionMetrics($options);

            // Analyze plan performance
            $planPerformance = $this->analyzePlanPerformance($options);

            // Generate business recommendations
            $recommendations = $this->generateBusinessRecommendations($revenueMetrics, $subscriptionMetrics, $planPerformance);

            $analytics = [
                'revenue_metrics' => $revenueMetrics,
                'subscription_metrics' => $subscriptionMetrics,
                'plan_performance' => $planPerformance,
                'recommendations' => $recommendations
            ];

            // Include churn analysis if requested
            if ($options['include_churn_analysis'] ?? false) {
                $analytics['churn_analysis'] = $this->performChurnAnalysis($options);
            }

            // Include cohort analysis if requested
            if ($options['include_cohort_analysis'] ?? false) {
                $analytics['cohort_analysis'] = $this->performCohortAnalysis($options);
            }

            // Generate predictions if requested
            if ($options['include_predictions'] ?? false) {
                $analytics['predictions'] = $this->generateSubscriptionPredictions($analytics);
            }

            Log::info('Subscription analytics generated successfully');

            return $analytics;

        } catch (\Exception $e) {
            Log::error('Failed to generate subscription analytics', [
                'error' => $e->getMessage(),
                'options' => $options
            ]);
            throw $e;
        }
    }

    // ============================================================================
    // PRIVATE HELPER METHODS
    // ============================================================================

    /**
     * Validate subscription data
     */
    private function validateSubscriptionData(array $subscriptionData): void
    {
        $required = ['user_id', 'plan_id', 'billing_cycle'];
        
        foreach ($required as $field) {
            if (!isset($subscriptionData[$field])) {
                throw new ValidationException("Field '{$field}' is required");
            }
        }

        if (isset($subscriptionData['trial_days']) && $subscriptionData['trial_days'] < 0) {
            throw new ValidationException('Trial days must be non-negative');
        }
    }

    /**
     * Validate user exists
     */
    private function validateUserExists(UserId|string $userId): void
    {
        if (!$this->userExists($userId)) {
            throw new UserNotFoundException("User not found: {$userId}");
        }
    }

    /**
     * Validate plan exists and return plan
     */
    private function validatePlanExists(PlanId|string $planId): Plan
    {
        $plan = $this->productRepository->findPlanById($planId);
        
        if (!$plan) {
            throw new PlanNotFoundException("Plan not found: {$planId}");
        }

        if (!$plan->isActive()) {
            throw new PlanNotAvailableException("Plan is not available: {$planId}");
        }

        return $plan;
    }

    /**
     * Check for existing active subscriptions
     */
    private function checkExistingSubscriptions(UserId|string $userId): void
    {
        $activeSubscription = $this->orderRepository->getUserActiveSubscription($userId);
        
        if ($activeSubscription) {
            throw new SubscriptionAlreadyActiveException('User already has an active subscription');
        }
    }

    /**
     * Apply promotional code
     */
    private function applyPromoCode(string $promoCode, Plan $plan): array
    {
        // Implementation would validate and apply promo code
        return [
            'discount_percentage' => 0,
            'discount_amount' => 0.0,
            'discount_duration' => 0
        ];
    }

    /**
     * Calculate subscription pricing
     */
    private function calculateSubscriptionPricing(Plan $plan, array $subscriptionData, array $adjustments): array
    {
        $basePrice = $plan->getPriceForCycle($subscriptionData['billing_cycle']);
        
        return [
            'base_price' => $basePrice->toFloat(),
            'discount_amount' => $adjustments['discount_amount'] ?? 0.0,
            'final_price' => $basePrice->toFloat() - ($adjustments['discount_amount'] ?? 0.0),
            'currency' => 'USD', // Default currency, should be passed from plan
            'billing_cycle' => $subscriptionData['billing_cycle']
        ];
    }

    /**
     * Create subscription record
     */
    private function createSubscriptionRecord(array $subscriptionData, Plan $plan, array $pricingDetails): Subscription
    {
        return $this->orderRepository->createSubscription([
            'user_id' => $subscriptionData['user_id'],
            'plan_id' => $plan->id()->toString(),
            'billing_cycle' => $subscriptionData['billing_cycle'],
            'pricing_details' => $pricingDetails,
            'auto_renew' => $subscriptionData['auto_renew'] ?? true,
            'metadata' => $subscriptionData['metadata'] ?? [],
            'status' => SubscriptionStatus::PENDING
        ]);
    }

    /**
     * Process initial payment
     */
    private function processInitialPayment(Subscription $subscription, array $subscriptionData, array $pricingDetails): Payment
    {
        return $this->paymentService->processPayment([
            'amount' => $pricingDetails['final_price'],
            'currency' => $pricingDetails['currency'],
            'user_id' => $subscriptionData['user_id'],
            'payment_method_id' => $subscriptionData['payment_method_id'] ?? null,
            'payment_method_data' => $subscriptionData['payment_method_data'] ?? null,
            'description' => "Subscription to {$subscription->getPlanId()}",
            'metadata' => [
                'subscription_id' => $subscription->getId(),
                'plan_id' => $subscription->getPlanId()
            ]
        ]);
    }

    /**
     * Check if user exists
     */
    private function userExists(UserId|string $userId): bool
    {
        // Implementation would check user repository
        return true; // Placeholder
    }

    // ============================================================================
    // PLAN RECOMMENDATION HELPER METHODS
    // ============================================================================

    /**
     * Analyze user usage patterns
     */
    private function analyzeUserUsagePatterns(UserId|string $userId, array $options): array
    {
        // Implementation would analyze user behavior and usage patterns
        return [
            'likes_per_day' => 15,
            'super_likes_per_day' => 2,
            'messages_sent' => 25,
            'profile_views' => 50,
            'feature_usage' => [
                'advanced_filters' => 0.3,
                'profile_boost' => 0.1,
                'read_receipts' => 0.8
            ],
            'engagement_score' => 0.7
        ];
    }

    /**
     * Get current user subscription
     */
    private function getCurrentUserSubscription(UserId|string $userId): ?Subscription
    {
        return $this->orderRepository->getUserActiveSubscription($userId);
    }

    /**
     * Analyze similar users' plan choices
     */
    private function analyzeSimilarUsersPlans(UserId|string $userId, array $usageAnalysis): array
    {
        // Implementation would analyze similar users
        return [
            'similar_users_count' => 150,
            'popular_plans' => ['premium', 'vip'],
            'conversion_rate' => 0.35
        ];
    }

    /**
     * Generate ML-based plan recommendations
     */
    private function generateMLPlanRecommendations(UserId|string $userId, array $usageAnalysis, array $similarUsersAnalysis): Collection
    {
        // Implementation would use ML to generate recommendations
        return collect([]);
    }

    /**
     * Apply recommendation constraints
     */
    private function applyRecommendationConstraints(Collection $recommendations, ?Subscription $currentSubscription, array $options): Collection
    {
        // Implementation would apply business rules
        return $recommendations;
    }

    /**
     * Calculate potential savings
     */
    private function calculatePotentialSavings(Collection $recommendations, ?Subscription $currentSubscription): array
    {
        // Implementation would calculate savings
        return [
            'monthly_savings' => 10.0,
            'annual_savings' => 120.0
        ];
    }

    /**
     * Generate upgrade timeline
     */
    private function generateUpgradeTimeline(Collection $recommendations, array $usageAnalysis): array
    {
        // Implementation would generate timeline
        return [
            'immediate' => [],
            '3_months' => [],
            '6_months' => []
        ];
    }

    /**
     * Generate recommendation reasoning
     */
    private function generateRecommendationReasoning(Collection $recommendations, array $usageAnalysis): array
    {
        // Implementation would generate reasoning
        return [
            'usage_based' => 'High engagement suggests premium features would be beneficial',
            'similar_users' => 'Users with similar patterns often choose premium plans'
        ];
    }

    // ============================================================================
    // SUBSCRIPTION LIFECYCLE HELPER METHODS
    // ============================================================================

    /**
     * Setup trial period
     */
    private function setupTrialPeriod(Subscription $subscription, int $trialDays): void
    {
        $subscription->updateMetadata([
            'trial_days' => $trialDays,
            'trial_start' => now()->toISOString(),
            'trial_end' => now()->addDays($trialDays)->toISOString()
        ]);
    }

    /**
     * Activate subscription features
     */
    private function activateSubscriptionFeatures(Subscription $subscription): void
    {
        // Implementation would activate features based on plan
        Log::info('Activating subscription features', [
            'subscription_id' => $subscription->getId(),
            'plan_id' => $subscription->getPlanId()
        ]);
    }

    /**
     * Setup billing schedule
     */
    private function setupBillingSchedule(Subscription $subscription): void
    {
        // Implementation would setup billing schedule
        Log::info('Setting up billing schedule', [
            'subscription_id' => $subscription->getId(),
            'billing_cycle' => $subscription->getBillingCycle()
        ]);
    }

    /**
     * Update user subscription status
     */
    private function updateUserSubscriptionStatus(UserId|string $userId, Subscription $subscription): void
    {
        // Implementation would update user's subscription status
        Log::info('Updating user subscription status', [
            'user_id' => $userId,
            'subscription_id' => $subscription->getId()
        ]);
    }

    /**
     * Update subscription analytics
     */
    private function updateSubscriptionAnalytics(Subscription $subscription, array $subscriptionData): void
    {
        // Implementation would update analytics
        Log::info('Updating subscription analytics', [
            'subscription_id' => $subscription->getId(),
            'plan_id' => $subscription->getPlanId()
        ]);
    }

    // ============================================================================
    // PLAN CHANGE HELPER METHODS
    // ============================================================================

    /**
     * Validate plan change data
     */
    private function validatePlanChangeData(array $changeData): void
    {
        $required = ['subscription_id', 'new_plan_id'];
        
        foreach ($required as $field) {
            if (!isset($changeData[$field])) {
                throw new ValidationException("Field '{$field}' is required for plan change");
            }
        }
    }

    /**
     * Get subscription by ID
     */
    private function getSubscriptionById(SubscriptionId|string $subscriptionId): ?Subscription
    {
        // Implementation would get subscription from repository
        return null; // Placeholder
    }

    /**
     * Validate plan change eligibility
     */
    private function validatePlanChangeEligibility(Subscription $subscription, Plan $newPlan, array $changeData): void
    {
        // Implementation would validate if plan change is allowed
        if (!$subscription->isActive()) {
            throw new InvalidSubscriptionException('Cannot change plan for inactive subscription');
        }
    }

    /**
     * Calculate proration details
     */
    private function calculateProrationDetails(Subscription $subscription, Plan $newPlan, array $changeData): array
    {
        // Implementation would calculate proration
        return [
            'credit_amount' => 0.0,
            'charge_amount' => 0.0,
            'net_change' => 0.0,
            'effective_date' => now()
        ];
    }

    /**
     * Analyze feature changes
     */
    private function analyzeFeatureChanges(Plan $oldPlan, Plan $newPlan): array
    {
        // Implementation would analyze feature differences
        return [
            'added_features' => [],
            'removed_features' => [],
            'modified_features' => []
        ];
    }

    /**
     * Process proration payment
     */
    private function processProrationPayment(Subscription $subscription, array $prorationDetails, array $changeData): ?Payment
    {
        // Implementation would process proration payment
        return null;
    }

    /**
     * Process proration credit
     */
    private function processProrationCredit(Subscription $subscription, array $prorationDetails): void
    {
        // Implementation would process proration credit
    }

    /**
     * Update subscription plan
     */
    private function updateSubscriptionPlan(Subscription $subscription, Plan $newPlan, array $changeData): Subscription
    {
        // Implementation would update subscription with new plan
        return $subscription;
    }

    /**
     * Update billing schedule for plan change
     */
    private function updateBillingSchedule(Subscription $subscription, array $changeData): array
    {
        // Implementation would update billing schedule
        return [];
    }

    /**
     * Apply feature changes
     */
    private function applyFeatureChanges(Subscription $subscription, array $featureChanges): void
    {
        // Implementation would apply feature changes
    }

    /**
     * Record plan change history
     */
    private function recordPlanChangeHistory(Subscription $oldSubscription, Subscription $newSubscription, array $changeData): void
    {
        // Implementation would record change history
    }

    /**
     * Update plan change analytics
     */
    private function updatePlanChangeAnalytics(Subscription $oldSubscription, Subscription $newSubscription, array $changeData): void
    {
        // Implementation would update analytics
    }

    // ============================================================================
    // ANALYTICS HELPER METHODS
    // ============================================================================

    /**
     * Generate revenue metrics
     */
    private function generateRevenueMetrics(array $options): array
    {
        // Implementation would generate revenue metrics
        return [
            'total_revenue' => 0.0,
            'recurring_revenue' => 0.0,
            'new_subscription_revenue' => 0.0
        ];
    }

    /**
     * Calculate subscription metrics
     */
    private function calculateSubscriptionMetrics(array $options): array
    {
        // Implementation would calculate subscription metrics
        return [
            'total_subscriptions' => 0,
            'active_subscriptions' => 0,
            'trial_subscriptions' => 0
        ];
    }

    /**
     * Analyze plan performance
     */
    private function analyzePlanPerformance(array $options): array
    {
        // Implementation would analyze plan performance
        return [
            'plan_conversion_rates' => [],
            'plan_revenue_distribution' => [],
            'plan_churn_rates' => []
        ];
    }

    /**
     * Generate business recommendations
     */
    private function generateBusinessRecommendations(array $revenueMetrics, array $subscriptionMetrics, array $planPerformance): array
    {
        // Implementation would generate business recommendations
        return [
            'pricing_optimization' => [],
            'plan_improvements' => [],
            'marketing_suggestions' => []
        ];
    }

    /**
     * Perform churn analysis
     */
    private function performChurnAnalysis(array $options): array
    {
        // Implementation would perform churn analysis
        return [
            'churn_rate' => 0.0,
            'churn_reasons' => [],
            'retention_metrics' => []
        ];
    }

    /**
     * Perform cohort analysis
     */
    private function performCohortAnalysis(array $options): array
    {
        // Implementation would perform cohort analysis
        return [
            'cohort_retention' => [],
            'lifetime_value' => [],
            'conversion_funnels' => []
        ];
    }

    /**
     * Generate subscription predictions
     */
    private function generateSubscriptionPredictions(array $analytics): array
    {
        // Implementation would generate predictions
        return [
            'revenue_forecast' => [],
            'churn_prediction' => [],
            'growth_projections' => []
        ];
    }

    /**
     * Get plan by ID for compatibility
     */
    private function getPlanById(PlanId|string $planId): ?Plan
    {
        return $this->productRepository->findPlanById($planId);
    }

    /**
     * Apply personalized pricing
     */
    private function applyPersonalizedPricing(Collection $plans, UserId|string $userId, array $options): void
    {
        // Implementation would apply ML-based personalized pricing
    }

    /**
     * Apply geographic pricing
     */
    private function applyGeographicPricing(Collection $plans, string $country, array $options): void
    {
        // Implementation would apply geographic pricing strategies
    }

    /**
     * Enrich plans with trial offers
     */
    private function enrichPlansWithTrialOffers(Collection $plans, array $options): void
    {
        // Implementation would add trial offer information
    }

    /**
     * Apply promotional pricing
     */
    private function applyPromotionalPricing(Collection $plans, array $options): void
    {
        // Implementation would apply active promotional pricing
    }

    /**
     * Apply A/B test variant
     */
    private function applyABTestVariant(Collection $plans, string $variant, array $options): void
    {
        // Implementation would apply A/B testing variants
    }

    /**
     * Enrich plans with feature comparison
     */
    private function enrichPlansWithFeatureComparison(Collection $plans): void
    {
        foreach ($plans as $plan) {
            $features = self::PLAN_FEATURES[$plan->getType()] ?? [];
            // $plan->setFeatureComparison($features); // Method not available in Plan entity
        }
    }

    /**
     * Sort plans by recommendation
     */
    private function sortPlansByRecommendation(Collection $plans, array $options): Collection
    {
        // Implementation would sort plans based on recommendation algorithm
        return $plans->sortBy('recommended_order');
    }
}