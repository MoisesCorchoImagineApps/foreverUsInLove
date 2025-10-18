<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Entities\Gift;
use App\Domain\Commerce\Entities\GiftCategory;
use App\Domain\Commerce\Entities\GiftTransaction;
use App\Domain\Commerce\ValueObjects\GiftId;
use App\Domain\Commerce\ValueObjects\GiftType;
use App\Domain\Commerce\ValueObjects\TransactionStatus;
use App\Domain\Commerce\ValueObjects\Price;
use App\Domain\Commerce\ValueObjects\Currency;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Commerce\Repositories\ProductRepositoryInterface;
use App\Domain\Commerce\Repositories\OrderRepositoryInterface;
use App\Domain\Commerce\Events\GiftSent;
use App\Domain\Commerce\Exceptions\GiftNotFoundException;
use App\Domain\Commerce\Exceptions\InsufficientFundsException;
use App\Domain\Commerce\Exceptions\GiftNotAvailableException;
use App\Domain\Commerce\Exceptions\InvalidGiftDataException;
use App\Domain\Common\Exceptions\ValidationException;
use App\Domain\Auth\Exceptions\UserNotFoundException;
use App\Domain\Commerce\Exceptions\PaymentMethodNotFoundException;
use App\Domain\Commerce\Entities\PaymentMethod;
use App\Domain\Commerce\ValueObjects\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Carbon\Carbon;

/**
 * GiftService - Virtual gift system with romantic gestures and premium items
 * 
 * Comprehensive gift management service for the ForeverUsInLove dating application.
 * Handles virtual gifts, romantic gestures, premium items, gift categories, and 
 * special occasions. Provides complete gift lifecycle management from catalog
 * browsing to delivery and analytics.
 * 
 * Features:
 * - Complete gift catalog management with categories and collections
 * - Virtual gift purchasing and delivery system
 * - Romantic gesture automation and scheduling
 * - Premium gift collections with exclusive items
 * - Special occasion gifts (birthdays, anniversaries, holidays)
 * - Gift analytics and popularity tracking
 * - Personalized gift recommendations
 * - Bulk gift operations for events and promotions
 * - Gift history and transaction management
 * - Integration with coin system and payment processing
 * - Real-time gift notifications and animations
 * - Gift market insights and trending analysis
 * 
 * Architecture:
 * - Service Layer Pattern for business logic
 * - Domain-Driven Design (DDD) principles
 * - Clean Architecture / Hexagonal Architecture
 * - SOLID principles with Dependency Inversion
 * - Laravel 12 with PHP 8.2 strict typing
 * - Event-driven architecture for gift delivery
 * - Repository pattern for data persistence
 * - Comprehensive error handling and validation
 * 
 * @package App\Domain\Commerce\Services
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 * 
 * @see \App\Domain\Commerce\Entities\Gift
 * @see \App\Domain\Commerce\Events\GiftSent
 * @see \App\Domain\Commerce\Repositories\ProductRepositoryInterface
 */
class GiftService
{
    /**
     * Constructor with dependency injection
     * 
     * @param ProductRepositoryInterface $productRepository Product data management
     * @param OrderRepositoryInterface $orderRepository Order and transaction management
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OrderRepositoryInterface $orderRepository
    ) {}

    // ============================================================================
    // GIFT CATALOG AND DISCOVERY
    // ============================================================================

    /**
     * Get comprehensive gift catalog with filtering and pagination
     * 
     * Retrieves the complete gift catalog with advanced filtering options including
     * categories, price ranges, popularity, and personalized recommendations based
     * on user preferences and relationship history.
     * 
     * @param array{
     *     user_id?: UserId|string,
     *     categories?: array<string>,
     *     types?: array<GiftType|string>,
     *     price_min?: float,
     *     price_max?: float,
     *     currency?: Currency|string,
     *     popularity?: string,
     *     occasion?: string,
     *     is_premium?: bool,
     *     is_featured?: bool,
     *     search?: string,
     *     sort_by?: string,
     *     sort_direction?: string,
     *     per_page?: int,
     *     page?: int
     * } $filters Comprehensive filtering and pagination options
     * 
     * @return LengthAwarePaginator Gift catalog with metadata and recommendations
     * 
     * @throws ValidationException When filter parameters are invalid
     * 
     * @example
     * ```php
     * $catalog = $giftService->getGiftCatalog([
     *     'categories' => ['romantic', 'anniversary'],
     *     'price_max' => 50.00,
     *     'currency' => Currency::USD,
     *     'sort_by' => 'popularity',
     *     'per_page' => 24
     * ]);
     * ```
     */
    public function getGiftCatalog(array $filters = []): LengthAwarePaginator
    {
        try {
            Log::info('Retrieving gift catalog', ['filters' => $filters]);

            // Validate filter parameters
            $this->validateCatalogFilters($filters);

            // Build base query with filters
            $query = $this->productRepository->buildGiftCatalogQuery($filters);

            // Apply personalized recommendations if user provided
            if (isset($filters['user_id'])) {
                $query = $this->applyPersonalizedRecommendations($query, $filters['user_id']);
            }

            // Apply sorting and pagination
            $gifts = $this->productRepository->executeGiftCatalogQuery($query, $filters);

            // Enrich results with additional metadata
            $this->enrichGiftCatalogResults($gifts);

            Log::info('Gift catalog retrieved successfully', [
                'total_gifts' => $gifts->total(),
                'filters_applied' => count($filters)
            ]);

            return $gifts;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve gift catalog', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            throw $e;
        }
    }

    /**
     * Get gift categories with statistics and trending information
     * 
     * Retrieves all available gift categories including popularity statistics,
     * trending information, and seasonal recommendations.
     * 
     * @param array{
     *     include_stats?: bool,
     *     include_trending?: bool,
     *     user_id?: UserId|string,
     *     period?: string
     * } $options Category retrieval options
     * 
     * @return Collection Gift categories with enriched metadata
     * 
     * @example
     * ```php
     * $categories = $giftService->getGiftCategories([
     *     'include_stats' => true,
     *     'include_trending' => true,
     *     'period' => 'month'
     * ]);
     * ```
     */
    public function getGiftCategories(array $options = []): Collection
    {
        try {
            Log::info('Retrieving gift categories', ['options' => $options]);

            // Get base categories
            $categories = $this->productRepository->getGiftCategories();

            // Enrich with statistics if requested
            if ($options['include_stats'] ?? false) {
                $this->enrichCategoriesWithStats($categories, $options);
            }

            // Add trending information if requested
            if ($options['include_trending'] ?? false) {
                $this->addTrendingInfo($categories, $options);
            }

            // Apply personalization if user provided
            if (isset($options['user_id'])) {
                $this->personalizeCategories($categories, $options['user_id']);
            }

            return $categories;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve gift categories', [
                'error' => $e->getMessage(),
                'options' => $options
            ]);
            throw $e;
        }
    }

    /**
     * Get personalized gift recommendations for user
     * 
     * Generates personalized gift recommendations based on user preferences,
     * purchase history, relationship status, and behavioral patterns.
     * 
     * @param UserId|string $userId Target user for recommendations
     * @param array{
     *     recipient_id?: UserId|string,
     *     occasion?: string,
     *     budget_min?: float,
     *     budget_max?: float,
     *     relationship_stage?: string,
     *     limit?: int,
     *     include_reasoning?: bool
     * } $options Recommendation configuration
     * 
     * @return array{
     *     recommendations: Collection,
     *     reasoning?: array,
     *     categories_suggested: array,
     *     budget_analysis: array
     * } Personalized recommendations with context
     * 
     * @throws UserNotFoundException When user not found
     * 
     * @example
     * ```php
     * $recommendations = $giftService->getPersonalizedRecommendations($userId, [
     *     'recipient_id' => $partnerId,
     *     'occasion' => 'anniversary',
     *     'budget_max' => 100.00,
     *     'limit' => 10,
     *     'include_reasoning' => true
     * ]);
     * ```
     */
    public function getPersonalizedRecommendations(UserId|string $userId, array $options = []): array
    {
        try {
            Log::info('Generating personalized gift recommendations', [
                'user_id' => $userId,
                'options' => $options
            ]);

            // Validate user exists
            $this->validateUserExists($userId);

            // Get user profile and preferences
            $userProfile = $this->getUserGiftProfile($userId);

            // Analyze relationship context if recipient provided
            $relationshipContext = null;
            if (isset($options['recipient_id'])) {
                $relationshipContext = $this->analyzeRelationshipContext($userId, $options['recipient_id']);
            }

            // Generate recommendations using ML algorithms
            $recommendations = $this->generateMLRecommendations($userProfile, $relationshipContext, $options);

            // Apply business rules and constraints
            $filteredRecommendations = $this->applyRecommendationFilters($recommendations, $options);

            // Generate reasoning if requested
            $reasoning = [];
            if ($options['include_reasoning'] ?? false) {
                $reasoning = $this->generateRecommendationReasoning($filteredRecommendations, $userProfile);
            }

            // Analyze budget and categories
            $budgetAnalysis = $this->analyzeBudgetOptions($filteredRecommendations, $options);
            $suggestedCategories = $this->suggestCategories($userProfile, $relationshipContext);

            $result = [
                'recommendations' => $filteredRecommendations,
                'categories_suggested' => $suggestedCategories,
                'budget_analysis' => $budgetAnalysis
            ];

            if (!empty($reasoning)) {
                $result['reasoning'] = $reasoning;
            }

            Log::info('Personalized recommendations generated successfully', [
                'user_id' => $userId,
                'recommendation_count' => $filteredRecommendations->count()
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to generate personalized recommendations', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'options' => $options
            ]);
            throw $e;
        }
    }

    // ============================================================================
    // GIFT PURCHASING AND DELIVERY
    // ============================================================================

    /**
     * Send virtual gift with comprehensive delivery handling
     * 
     * Processes virtual gift purchase and delivery including payment verification,
     * inventory management, delivery coordination, and recipient notification.
     * Supports scheduled delivery and personalized messages.
     * 
     * @param array{
     *     gift_id: GiftId|string,
     *     sender_id: UserId|string,
     *     recipient_id: UserId|string,
     *     message?: string,
     *     scheduled_at?: Carbon|string|null,
     *     is_anonymous?: bool,
     *     payment_method?: string,
     *     use_coins?: bool,
     *     coins_amount?: int,
     *     metadata?: array{
     *         occasion?: string,
     *         custom_animation?: string,
     *         delivery_preferences?: array
     *     }
     * } $giftData Comprehensive gift sending configuration
     * 
     * @return GiftTransaction Completed gift transaction with delivery details
     * 
     * @throws GiftNotFoundException When gift not found or unavailable
     * @throws UserNotFoundException When sender or recipient not found
     * @throws InsufficientFundsException When insufficient balance or coins
     * @throws InvalidGiftDataException When gift data is invalid
     * @throws ValidationException When validation fails
     * 
     * @example
     * ```php
     * $transaction = $giftService->sendGift([
     *     'gift_id' => $giftId,
     *     'sender_id' => $senderId,
     *     'recipient_id' => $recipientId,
     *     'message' => 'Happy Anniversary, my love!',
     *     'use_coins' => true,
     *     'coins_amount' => 50,
     *     'metadata' => [
     *         'occasion' => 'anniversary',
     *         'custom_animation' => 'hearts_rain'
     *     ]
     * ]);
     * ```
     */
    public function sendGift(array $giftData): GiftTransaction
    {
        return DB::transaction(function () use ($giftData) {
            try {
                Log::info('Processing gift delivery', ['gift_data' => $giftData]);

                // Validate gift data
                $this->validateGiftData($giftData);

                // Verify gift availability
                $gift = $this->verifyGiftAvailability($giftData['gift_id']);

                // Validate users
                $this->validateGiftUsers($giftData['sender_id'], $giftData['recipient_id']);

                // Check sender balance and payment method
                $paymentResult = $this->processGiftPayment($giftData, $gift);

                // Create gift transaction
                $transaction = $this->createGiftTransaction($giftData, $gift, $paymentResult);

                // Schedule or execute delivery
                if (isset($giftData['scheduled_at'])) {
                    $this->scheduleGiftDelivery($transaction, $giftData['scheduled_at']);
                } else {
                    $this->executeGiftDelivery($transaction);
                }

                // Update gift statistics
                $this->updateGiftStatistics($gift, $transaction);

                // Update user gift history
                $this->updateUserGiftHistory($giftData['sender_id'], $giftData['recipient_id'], $transaction);

                Log::info('Gift sent successfully', [
                    'transaction_id' => $transaction->getId(),
                    'gift_id' => $gift->getId(),
                    'sender_id' => $giftData['sender_id'],
                    'recipient_id' => $giftData['recipient_id']
                ]);

                return $transaction;

            } catch (\Exception $e) {
                Log::error('Failed to send gift', [
                    'error' => $e->getMessage(),
                    'gift_data' => $giftData
                ]);
                throw $e;
            }
        });
    }

    /**
     * Process bulk gift sending for events and promotions
     * 
     * Handles bulk gift operations for special events, promotions, or group
     * celebrations with optimized processing and comprehensive error handling.
     * 
     * @param array{
     *     gift_id: GiftId|string,
     *     sender_id: UserId|string,
     *     recipients: array<array{
     *         user_id: UserId|string,
     *         message?: string,
     *         scheduled_at?: Carbon|string
     *     }>,
     *     default_message?: string,
     *     payment_method?: string,
     *     metadata?: array
     * } $bulkData Bulk gift sending configuration
     * 
     * @return array{
     *     successful: array<GiftTransaction>,
     *     failed: array<array{recipient_id: string, error: string}>,
     *     summary: array{
     *         total: int,
     *         successful: int,
     *         failed: int,
     *         total_cost: float
     *     }
     * } Bulk operation results with detailed summary
     * 
     * @throws InvalidGiftDataException When bulk data is invalid
     * @throws GiftNotFoundException When gift not found
     * 
     * @example
     * ```php
     * $result = $giftService->sendBulkGifts([
     *     'gift_id' => $promotionalGiftId,
     *     'sender_id' => $adminId,
     *     'recipients' => [
     *         ['user_id' => $user1, 'message' => 'Happy Holidays!'],
     *         ['user_id' => $user2, 'message' => 'Happy Holidays!']
     *     ],
     *     'metadata' => ['campaign' => 'holiday_2024']
     * ]);
     * ```
     */
    public function sendBulkGifts(array $bulkData): array
    {
        try {
            Log::info('Processing bulk gift sending', [
                'gift_id' => $bulkData['gift_id'],
                'sender_id' => $bulkData['sender_id'],
                'recipient_count' => count($bulkData['recipients'])
            ]);

            // Validate bulk data
            $this->validateBulkGiftData($bulkData);

            // Verify gift and sender
            $gift = $this->verifyGiftAvailability($bulkData['gift_id']);
            $this->validateUserExists($bulkData['sender_id']);

            // Process recipients in batches
            $successful = [];
            $failed = [];
            $totalCost = 0.0;

            foreach (array_chunk($bulkData['recipients'], 50) as $batch) {
                $batchResults = $this->processBulkGiftBatch($bulkData, $gift, $batch);
                
                $successful = array_merge($successful, $batchResults['successful']);
                $failed = array_merge($failed, $batchResults['failed']);
                $totalCost += $batchResults['cost'];
            }

            $summary = [
                'total' => count($bulkData['recipients']),
                'successful' => count($successful),
                'failed' => count($failed),
                'total_cost' => $totalCost
            ];

            Log::info('Bulk gift sending completed', ['summary' => $summary]);

            return [
                'successful' => $successful,
                'failed' => $failed,
                'summary' => $summary
            ];

        } catch (\Exception $e) {
            Log::error('Failed to process bulk gift sending', [
                'error' => $e->getMessage(),
                'bulk_data' => $bulkData
            ]);
            throw $e;
        }
    }

    // ============================================================================
    // GIFT HISTORY AND ANALYTICS
    // ============================================================================

    /**
     * Get user's gift history with comprehensive filtering
     * 
     * Retrieves complete gift history for a user including sent and received
     * gifts with advanced filtering, analytics, and relationship insights.
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     type?: string, // 'sent', 'received', or 'all'
     *     date_from?: Carbon|string,
     *     date_to?: Carbon|string,
     *     partner_id?: UserId|string,
     *     gift_categories?: array<string>,
     *     include_analytics?: bool,
     *     sort_by?: string,
     *     per_page?: int
     * } $filters History filtering and analysis options
     * 
     * @return array{
     *     transactions: LengthAwarePaginator,
     *     analytics?: array{
     *         total_sent: int,
     *         total_received: int,
     *         favorite_categories: array,
     *         spending_patterns: array,
     *         relationship_insights: array
     *     }
     * } Gift history with optional analytics
     * 
     * @throws UserNotFoundException When user not found
     * 
     * @example
     * ```php
     * $history = $giftService->getUserGiftHistory($userId, [
     *     'type' => 'sent',
     *     'date_from' => Carbon::now()->subMonths(6),
     *     'include_analytics' => true,
     *     'per_page' => 20
     * ]);
     * ```
     */
    public function getUserGiftHistory(UserId|string $userId, array $filters = []): array
    {
        try {
            Log::info('Retrieving user gift history', [
                'user_id' => $userId,
                'filters' => $filters
            ]);

            // Validate user exists
            $this->validateUserExists($userId);

            // Get gift transactions
            $transactions = $this->orderRepository->getUserGiftTransactions($userId, $filters);

            $result = ['transactions' => $transactions];

            // Generate analytics if requested
            if ($filters['include_analytics'] ?? false) {
                $result['analytics'] = $this->generateGiftAnalytics($userId, $filters);
            }

            Log::info('User gift history retrieved successfully', [
                'user_id' => $userId,
                'transaction_count' => $transactions->total()
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve user gift history', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            throw $e;
        }
    }

    /**
     * Get gift analytics and insights
     * 
     * Generates comprehensive analytics including popularity trends, revenue
     * insights, user behavior patterns, and market intelligence.
     * 
     * @param array{
     *     period?: string,
     *     start_date?: Carbon|string,
     *     end_date?: Carbon|string,
     *     categories?: array<string>,
     *     user_segments?: array<string>,
     *     include_predictions?: bool
     * } $options Analytics configuration
     * 
     * @return array{
     *     popularity_trends: array,
     *     revenue_insights: array,
     *     user_behavior: array,
     *     category_performance: array,
     *     seasonal_patterns: array,
     *     predictions?: array
     * } Comprehensive gift analytics
     * 
     * @example
     * ```php
     * $analytics = $giftService->getGiftAnalytics([
     *     'period' => 'quarter',
     *     'include_predictions' => true,
     *     'categories' => ['romantic', 'anniversary']
     * ]);
     * ```
     */
    public function getGiftAnalytics(array $options = []): array
    {
        try {
            Log::info('Generating gift analytics', ['options' => $options]);

            // Generate popularity trends
            $popularityTrends = $this->generatePopularityTrends($options);

            // Calculate revenue insights
            $revenueInsights = $this->calculateRevenueInsights($options);

            // Analyze user behavior patterns
            $userBehavior = $this->analyzeUserBehavior($options);

            // Evaluate category performance
            $categoryPerformance = $this->evaluateCategoryPerformance($options);

            // Identify seasonal patterns
            $seasonalPatterns = $this->identifySeasonalPatterns($options);

            $analytics = [
                'popularity_trends' => $popularityTrends,
                'revenue_insights' => $revenueInsights,
                'user_behavior' => $userBehavior,
                'category_performance' => $categoryPerformance,
                'seasonal_patterns' => $seasonalPatterns
            ];

            // Generate predictions if requested
            if ($options['include_predictions'] ?? false) {
                $analytics['predictions'] = $this->generateGiftPredictions($analytics);
            }

            Log::info('Gift analytics generated successfully');

            return $analytics;

        } catch (\Exception $e) {
            Log::error('Failed to generate gift analytics', [
                'error' => $e->getMessage(),
                'options' => $options
            ]);
            throw $e;
        }
    }

    // ============================================================================
    // SPECIAL OCCASIONS AND AUTOMATED GIFTS
    // ============================================================================

    /**
     * Schedule automatic gift for special occasion
     * 
     * Sets up automated gift delivery for special occasions like birthdays,
     * anniversaries, holidays, and relationship milestones with intelligent
     * gift selection and personalization.
     * 
     * @param array{
     *     sender_id: UserId|string,
     *     recipient_id: UserId|string,
     *     occasion_type: string,
     *     occasion_date: Carbon|string,
     *     auto_select_gift?: bool,
     *     gift_preferences?: array,
     *     budget_limit?: float,
     *     personal_message?: string,
     *     delivery_time?: string,
     *     recurring?: bool,
     *     notification_preferences?: array
     * } $scheduleData Occasion scheduling configuration
     * 
     * @return array{
     *     schedule_id: string,
     *     recommended_gifts: Collection,
     *     delivery_date: Carbon,
     *     estimated_cost: float,
     *     auto_renewal: bool
     * } Scheduled occasion details
     * 
     * @throws UserNotFoundException When users not found
     * @throws InvalidGiftDataException When schedule data is invalid
     * 
     * @example
     * ```php
     * $schedule = $giftService->scheduleOccasionGift([
     *     'sender_id' => $userId,
     *     'recipient_id' => $partnerId,
     *     'occasion_type' => 'anniversary',
     *     'occasion_date' => Carbon::parse('2024-02-14'),
     *     'auto_select_gift' => true,
     *     'budget_limit' => 75.00,
     *     'recurring' => true
     * ]);
     * ```
     */
    public function scheduleOccasionGift(array $scheduleData): array
    {
        try {
            Log::info('Scheduling occasion gift', ['schedule_data' => $scheduleData]);

            // Validate schedule data
            $this->validateOccasionScheduleData($scheduleData);

            // Validate users
            $this->validateGiftUsers($scheduleData['sender_id'], $scheduleData['recipient_id']);

            // Generate gift recommendations for occasion
            $recommendedGifts = $this->generateOccasionGiftRecommendations($scheduleData);

            // Calculate delivery timing
            $deliveryDate = $this->calculateOptimalDeliveryTime($scheduleData);

            // Estimate costs
            $estimatedCost = $this->estimateOccasionCost($recommendedGifts, $scheduleData);

            // Create occasion schedule
            $schedule = $this->createOccasionSchedule($scheduleData, $recommendedGifts, $deliveryDate);

            // Set up automation if enabled
            if ($scheduleData['auto_select_gift'] ?? false) {
                $this->setupGiftAutomation($schedule, $recommendedGifts);
            }

            $result = [
                'schedule_id' => $schedule->getId(),
                'recommended_gifts' => $recommendedGifts,
                'delivery_date' => $deliveryDate,
                'estimated_cost' => $estimatedCost,
                'auto_renewal' => $scheduleData['recurring'] ?? false
            ];

            Log::info('Occasion gift scheduled successfully', [
                'schedule_id' => $schedule->getId(),
                'occasion_type' => $scheduleData['occasion_type'],
                'delivery_date' => $deliveryDate
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to schedule occasion gift', [
                'error' => $e->getMessage(),
                'schedule_data' => $scheduleData
            ]);
            throw $e;
        }
    }

    // ============================================================================
    // PRIVATE HELPER METHODS
    // ============================================================================

    /**
     * Validate gift catalog filters
     */
    private function validateCatalogFilters(array $filters): void
    {
        if (isset($filters['price_min'], $filters['price_max'])) {
            if ($filters['price_min'] > $filters['price_max']) {
                throw new ValidationException('Minimum price cannot be greater than maximum price');
            }
        }

        if (isset($filters['per_page']) && ($filters['per_page'] < 1 || $filters['per_page'] > 100)) {
            throw new ValidationException('Per page must be between 1 and 100');
        }
    }

    /**
     * Apply personalized recommendations to query
     */
    private function applyPersonalizedRecommendations($query, UserId|string $userId)
    {
        // Get user preferences and behavior
        $userProfile = $this->getUserGiftProfile($userId);
        
        // Apply ML-based recommendations
        return $this->productRepository->applyPersonalization($query, $userProfile);
    }

    /**
     * Enrich gift catalog results with metadata
     */
    private function enrichGiftCatalogResults(LengthAwarePaginator $gifts): void
    {
        foreach ($gifts->items() as $gift) {
            $gift->popularity_score = $this->calculatePopularityScore($gift);
            $gift->recommendation_reasons = $this->getRecommendationReasons($gift);
        }
    }

    /**
     * Validate gift sending data
     */
    private function validateGiftData(array $giftData): void
    {
        $required = ['gift_id', 'sender_id', 'recipient_id'];
        
        foreach ($required as $field) {
            if (!isset($giftData[$field])) {
                throw new ValidationException("Field '{$field}' is required");
            }
        }

        if (isset($giftData['message']) && strlen($giftData['message']) > 500) {
            throw new ValidationException('Gift message cannot exceed 500 characters');
        }

        if ($giftData['sender_id'] === $giftData['recipient_id']) {
            throw new ValidationException('Cannot send gift to yourself');
        }
    }

    /**
     * Verify gift availability
     */
    private function verifyGiftAvailability(GiftId|string $giftId): Gift
    {
        $gift = $this->productRepository->findGiftById($giftId);
        
        if (!$gift) {
            throw new GiftNotFoundException("Gift not found: {$giftId}");
        }

        if (!$gift->isAvailable()) {
            throw new GiftNotAvailableException("Gift is not available: {$giftId}");
        }

        return $gift;
    }

    /**
     * Validate gift users
     */
    private function validateGiftUsers(UserId|string $senderId, UserId|string $recipientId): void
    {
        $this->validateUserExists($senderId);
        $this->validateUserExists($recipientId);
    }

    /**
     * Validate user exists
     */
    private function validateUserExists(UserId|string $userId): void
    {
        // This would typically call a user repository
        // For now, we'll assume it's implemented
        if (!$this->userExists($userId)) {
            throw new UserNotFoundException("User not found: {$userId}");
        }
    }

    /**
     * Check if user exists
     */
    private function userExists(UserId|string $userId): bool
    {
        $id = $userId instanceof UserId ? $userId->toInt() : (int) $userId;
        
        return Cache::remember("user_exists_{$id}", 300, function () use ($id) {
            return DB::table('users')->where('id', $id)->exists();
        });
    }

    /**
     * Process gift payment
     */
    private function processGiftPayment(array $giftData, Gift $gift): array
    {
        $amount = $gift->getPrice()->toFloat();
        $currency = $gift->getCurrency()->toString();
        $paymentMethod = $giftData['payment_method'] ?? 'coins';
        
        // Handle coin payments
        if ($giftData['use_coins'] ?? false) {
            $coinsAmount = $giftData['coins_amount'] ?? (int) ($amount * 100); // Convert to coins
            $userId = $giftData['sender_id'];
            
            // Check user coin balance
            $userCoins = $this->getUserCoinBalance($userId);
            if ($userCoins < $coinsAmount) {
                throw new InsufficientFundsException("Insufficient coins. Required: {$coinsAmount}, Available: {$userCoins}");
            }
            
            // Deduct coins
            $this->orderRepository->decrementUserCoinBalance($userId, $coinsAmount);
            
            return [
                'payment_id' => 'coin_pay_' . uniqid(),
                'amount' => $amount,
                'currency' => $currency,
                'method' => 'coins',
                'coins_used' => $coinsAmount,
                'status' => 'completed'
            ];
        }
        
        // Handle regular payment processing
        $paymentMethodObj = $this->getPaymentMethod($giftData['sender_id'], $paymentMethod);
        $paymentData = [
            'amount' => $amount,
            'currency' => $currency,
            'user_id' => $giftData['sender_id'],
            'gateway' => $paymentMethod,
            'payment_method_id' => $paymentMethodObj->id ?? null,
            'status' => PaymentStatus::processing()
        ];
        
        $payment = $this->orderRepository->createPayment($paymentData);
        
        return [
            'payment_id' => $payment->id ?? 'payment_' . uniqid(),
            'amount' => $amount,
            'currency' => $currency,
            'method' => $paymentMethod,
            'status' => $payment->status ?? 'processing'
        ];
    }

    /**
     * Create gift transaction
     */
    private function createGiftTransaction(array $giftData, Gift $gift, array $paymentResult): GiftTransaction
    {
        return $this->orderRepository->createGiftTransaction([
            'gift_id' => $gift->getId(),
            'sender_id' => $giftData['sender_id'],
            'recipient_id' => $giftData['recipient_id'],
            'payment_data' => $paymentResult,
            'message' => $giftData['message'] ?? null,
            'metadata' => $giftData['metadata'] ?? [],
            'status' => TransactionStatus::COMPLETED
        ]);
    }

    /**
     * Execute gift delivery
     */
    private function executeGiftDelivery(GiftTransaction $transaction): void
    {
        // Fire gift sent event
        Event::dispatch(new GiftSent(
            $transaction,
            $transaction->getSenderId(),
            $transaction->getRecipientId()
        ));
    }

    /**
     * Schedule gift delivery
     */
    private function scheduleGiftDelivery(GiftTransaction $transaction, Carbon|string $scheduledAt): void
    {
        // Implementation would schedule the delivery
        // This could use Laravel's queue system with delayed jobs
    }

    /**
     * Update gift statistics
     */
    private function updateGiftStatistics(Gift $gift, GiftTransaction $transaction): void
    {
        $this->productRepository->incrementGiftPopularity($gift->getId());
        $this->productRepository->updateGiftRevenue($gift->getId(), $transaction->getAmount());
    }

    /**
     * Update user gift history
     */
    private function updateUserGiftHistory(UserId|string $senderId, UserId|string $recipientId, GiftTransaction $transaction): void
    {
        // Implementation would update user statistics
        Cache::forget("user_gift_stats_{$senderId}");
        Cache::forget("user_gift_stats_{$recipientId}");
    }

    /**
     * Get user gift profile
     */
    private function getUserGiftProfile(UserId|string $userId): array
    {
        return Cache::remember("user_gift_profile_{$userId}", 3600, function () use ($userId) {
            return [
                'preferences' => $this->getUserGiftPreferences($userId),
                'history' => $this->getUserGiftBehavior($userId),
                'relationships' => $this->getUserRelationshipData($userId)
            ];
        });
    }

    /**
     * Enrich categories with statistics
     */
    private function enrichCategoriesWithStats(Collection $categories, array $options): void
    {
        $period = $options['period'] ?? 'month';
        
        foreach ($categories as $category) {
            $category->gift_count = $this->getCategoryGiftCount($category->id);
            $category->total_revenue = $this->getCategoryRevenue($category->id, $period);
            $category->popularity_trend = $this->getCategoryPopularityTrend($category->id, $period);
            $category->average_price = $this->getCategoryAveragePrice($category->id);
            $category->conversion_rate = $this->getCategoryConversionRate($category->id, $period);
        }
    }

    /**
     * Add trending information to categories
     */
    private function addTrendingInfo(Collection $categories, array $options): void
    {
        $period = $options['period'] ?? 'week';
        
        foreach ($categories as $category) {
            $trendingGifts = $this->getTrendingGiftsInCategory($category->id, $period, 5);
            $category->trending_gifts = $trendingGifts;
            $category->trend_score = $this->calculateCategoryTrendScore($category->id, $period);
            $category->growth_rate = $this->calculateCategoryGrowthRate($category->id, $period);
        }
    }

    /**
     * Personalize categories based on user preferences
     */
    private function personalizeCategories(Collection $categories, UserId|string $userId): void
    {
        $userPreferences = $this->getUserGiftPreferences($userId);
        $userHistory = $this->getUserGiftBehavior($userId);
        
        foreach ($categories as $category) {
            $category->personalized_score = $this->calculatePersonalizedCategoryScore($category, $userPreferences, $userHistory);
            $category->recommended_for_user = $category->personalized_score > 0.7;
        }
        
        // Sort categories by personalized score
        $categories = $categories->sortByDesc('personalized_score');
    }

    /**
     * Analyze relationship context between users
     */
    private function analyzeRelationshipContext(UserId|string $senderId, UserId|string $recipientId): array
    {
        // This would typically integrate with a relationship/matching service
        // For now, we'll return basic relationship context
        return [
            'relationship_type' => $this->determineRelationshipType($senderId, $recipientId),
            'relationship_duration' => $this->calculateRelationshipDuration($senderId, $recipientId),
            'interaction_frequency' => $this->calculateInteractionFrequency($senderId, $recipientId),
            'previous_gifts' => $this->getPreviousGiftsBetweenUsers($senderId, $recipientId),
            'shared_interests' => $this->getSharedInterests($senderId, $recipientId),
            'relationship_stage' => $this->determineRelationshipStage($senderId, $recipientId),
            'special_occasions' => $this->getUpcomingSpecialOccasions($senderId, $recipientId)
        ];
    }

    /**
     * Generate ML-based recommendations
     */
    private function generateMLRecommendations(array $userProfile, ?array $relationshipContext, array $options): Collection
    {
        $limit = $options['limit'] ?? 20;
        $budgetMin = $options['budget_min'] ?? 0;
        $budgetMax = $options['budget_max'] ?? 1000;
        
        // Get base recommendations from repository
        $baseRecommendations = $this->getGiftRecommendations([
            'user_profile' => $userProfile,
            'relationship_context' => $relationshipContext,
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
            'limit' => $limit * 2 // Get more to filter
        ]);
        
        // Apply ML scoring
        $scoredRecommendations = [];
        foreach ($baseRecommendations as $gift) {
            if ($gift instanceof Gift) {
                $score = $this->calculateMLRecommendationScore($gift, $userProfile, $relationshipContext);
                $scoredRecommendations[] = ['gift' => $gift, 'score' => $score];
            }
        }
        
        // Sort by ML score and limit results
        usort($scoredRecommendations, fn($a, $b) => $b['score'] <=> $a['score']);
        $sortedGifts = collect(array_map(fn($item) => $item['gift'], $scoredRecommendations));
        
        return $sortedGifts->take($limit);
    }

    /**
     * Apply recommendation filters
     */
    private function applyRecommendationFilters(Collection $recommendations, array $options): Collection
    {
        $filtered = $recommendations;
        
        // Filter by occasion if specified
        if (isset($options['occasion'])) {
            $filtered = $filtered->filter(function ($gift) use ($options) {
                return $gift instanceof Gift && $this->isGiftSuitableForOccasion($gift, $options['occasion']);
            });
        }
        
        // Filter by relationship stage if specified
        if (isset($options['relationship_stage'])) {
            $filtered = $filtered->filter(function ($gift) use ($options) {
                return $gift instanceof Gift && $this->isGiftSuitableForRelationshipStage($gift, $options['relationship_stage']);
            });
        }
        
        return $filtered;
    }

    /**
     * Generate recommendation reasoning
     */
    private function generateRecommendationReasoning(Collection $recommendations, array $userProfile): array
    {
        $reasoning = [];
        
        foreach ($recommendations as $gift) {
            if ($gift instanceof Gift) {
                $reasoning[$gift->getId()->toString()] = [
                    'why_recommended' => $this->generateWhyRecommended($gift, $userProfile),
                    'confidence_score' => 0.8, // Default confidence score
                    'key_factors' => $this->getRecommendationKeyFactors($gift, $userProfile),
                    'alternatives' => $this->getAlternativeGifts($gift->getId(), $userProfile, 3)
                ];
            }
        }
        
        return $reasoning;
    }

    /**
     * Analyze budget options
     */
    private function analyzeBudgetOptions(Collection $recommendations, array $options): array
    {
        $budgetMin = $options['budget_min'] ?? 0;
        $budgetMax = $options['budget_max'] ?? 1000;
        
        $priceRanges = [
            'low' => ['min' => $budgetMin, 'max' => $budgetMin + ($budgetMax - $budgetMin) * 0.33],
            'medium' => ['min' => $budgetMin + ($budgetMax - $budgetMin) * 0.33, 'max' => $budgetMin + ($budgetMax - $budgetMin) * 0.66],
            'high' => ['min' => $budgetMin + ($budgetMax - $budgetMin) * 0.66, 'max' => $budgetMax]
        ];
        
        $analysis = [];
        foreach ($priceRanges as $range => $bounds) {
            $giftsInRange = $recommendations->filter(function ($gift) use ($bounds) {
                if ($gift instanceof Gift) {
                    $price = $gift->getPrice()->toFloat();
                    return $price >= $bounds['min'] && $price <= $bounds['max'];
                }
                return false;
            });
            
            $analysis[$range] = [
                'count' => $giftsInRange->count(),
                'percentage' => $recommendations->count() > 0 ? ($giftsInRange->count() / $recommendations->count()) * 100 : 0,
                'average_price' => $giftsInRange->count() > 0 ? $giftsInRange->avg('price.amount') : 0,
                'recommended_gifts' => $giftsInRange->take(3)->toArray()
            ];
        }
        
        return $analysis;
    }

    /**
     * Suggest categories based on user profile and relationship context
     */
    private function suggestCategories(array $userProfile, ?array $relationshipContext): array
    {
        $suggestions = [];
        
        // Based on user preferences
        if (isset($userProfile['preferences']['favorite_categories'])) {
            $suggestions = array_merge($suggestions, $userProfile['preferences']['favorite_categories']);
        }
        
        // Based on relationship context
        if ($relationshipContext) {
            $relationshipStage = $relationshipContext['relationship_stage'] ?? 'casual';
            $occasion = $relationshipContext['special_occasions'][0] ?? null;
            
            if ($occasion) {
                $suggestions[] = $this->getCategoryForOccasion($occasion);
            }
            
            $suggestions[] = $this->getCategoryForRelationshipStage($relationshipStage);
        }
        
        // Based on trending categories
        $trendingCategories = $this->getTrendingCategories('week', 3);
        foreach ($trendingCategories as $category) {
            $suggestions[] = $category->name;
        }
        
        return array_unique($suggestions);
    }

    /**
     * Validate bulk gift data
     */
    private function validateBulkGiftData(array $bulkData): void
    {
        $required = ['gift_id', 'sender_id', 'recipients'];
        
        foreach ($required as $field) {
            if (!isset($bulkData[$field])) {
                throw new ValidationException("Field '{$field}' is required for bulk gift sending");
            }
        }
        
        if (empty($bulkData['recipients'])) {
            throw new ValidationException('Recipients list cannot be empty');
        }
        
        if (count($bulkData['recipients']) > 1000) {
            throw new ValidationException('Cannot send to more than 1000 recipients at once');
        }
        
        foreach ($bulkData['recipients'] as $index => $recipient) {
            if (!isset($recipient['user_id'])) {
                throw new ValidationException("Recipient at index {$index} must have user_id");
            }
        }
    }

    /**
     * Process bulk gift batch
     */
    private function processBulkGiftBatch(array $bulkData, Gift $gift, array $batch): array
    {
        $successful = [];
        $failed = [];
        $totalCost = 0.0;
        
        foreach ($batch as $recipientData) {
            try {
                $giftData = [
                    'gift_id' => $bulkData['gift_id'],
                    'sender_id' => $bulkData['sender_id'],
                    'recipient_id' => $recipientData['user_id'],
                    'message' => $recipientData['message'] ?? $bulkData['default_message'],
                    'scheduled_at' => $recipientData['scheduled_at'] ?? null,
                    'metadata' => array_merge($bulkData['metadata'] ?? [], ['bulk_send' => true])
                ];
                
                $transaction = $this->sendGift($giftData);
                $successful[] = $transaction;
                $totalCost += $gift->getPrice()->toFloat();
                
            } catch (\Exception $e) {
                $failed[] = [
                    'recipient_id' => $recipientData['user_id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'successful' => $successful,
            'failed' => $failed,
            'cost' => $totalCost
        ];
    }

    /**
     * Generate gift analytics for user
     */
    private function generateGiftAnalytics(UserId|string $userId, array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subYear();
        $dateTo = $filters['date_to'] ?? now();
        
        return [
            'total_sent' => $this->getUserGiftTransactionCount($userId, 'sent', $dateFrom, $dateTo),
            'total_received' => $this->getUserGiftTransactionCount($userId, 'received', $dateFrom, $dateTo),
            'favorite_categories' => $this->getUserFavoriteGiftCategories($userId, $dateFrom, $dateTo),
            'spending_patterns' => $this->getUserGiftSpendingPatterns($userId, $dateFrom, $dateTo),
            'relationship_insights' => $this->getUserGiftRelationshipInsights($userId, $dateFrom, $dateTo)
        ];
    }

    /**
     * Generate popularity trends
     */
    private function generatePopularityTrends(array $options): array
    {
        $period = $options['period'] ?? 'month';
        $startDate = $options['start_date'] ?? now()->subMonth();
        $endDate = $options['end_date'] ?? now();
        
        return [
            'trending_gifts' => $this->getTrendingGifts($period, 10),
            'trending_categories' => $this->getTrendingCategories($period, 5),
            'popularity_changes' => $this->getPopularityChanges($startDate, $endDate),
            'viral_gifts' => $this->getViralGifts($period),
            'declining_gifts' => $this->getDecliningGifts($period)
        ];
    }

    /**
     * Calculate revenue insights
     */
    private function calculateRevenueInsights(array $options): array
    {
        $period = $options['period'] ?? 'month';
        $startDate = $options['start_date'] ?? now()->subMonth();
        $endDate = $options['end_date'] ?? now();
        
        return [
            'total_revenue' => $this->getGiftRevenue($startDate, $endDate),
            'revenue_by_category' => $this->getGiftRevenueByCategory($startDate, $endDate),
            'revenue_growth' => $this->getGiftRevenueGrowth($startDate, $endDate),
            'average_gift_value' => $this->getAverageGiftValue($startDate, $endDate),
            'top_revenue_gifts' => $this->getTopRevenueGifts($startDate, $endDate, 10),
            'revenue_by_user_segment' => $this->getGiftRevenueByUserSegment($startDate, $endDate)
        ];
    }

    /**
     * Analyze user behavior patterns
     */
    private function analyzeUserBehavior(array $options): array
    {
        $period = $options['period'] ?? 'month';
        $userSegments = $options['user_segments'] ?? ['all'];
        
        return [
            'purchase_patterns' => $this->getUserPurchasePatterns($period, $userSegments),
            'preferred_times' => $this->getPreferredPurchaseTimes($period, $userSegments),
            'gift_recipient_patterns' => $this->getGiftRecipientPatterns($period, $userSegments),
            'spending_behavior' => $this->getUserSpendingBehavior($period, $userSegments),
            'engagement_levels' => $this->getUserEngagementLevels($period, $userSegments)
        ];
    }

    /**
     * Evaluate category performance
     */
    private function evaluateCategoryPerformance(array $options): array
    {
        $period = $options['period'] ?? 'month';
        $categories = $options['categories'] ?? [];
        
        return [
            'performance_metrics' => $this->getCategoryPerformanceMetrics($period, $categories),
            'conversion_rates' => $this->getCategoryConversionRates($period, $categories),
            'revenue_distribution' => $this->getCategoryRevenueDistribution($period, $categories),
            'user_satisfaction' => $this->getCategoryUserSatisfaction($period, $categories),
            'growth_rates' => $this->getCategoryGrowthRates($period, $categories)
        ];
    }

    /**
     * Identify seasonal patterns
     */
    private function identifySeasonalPatterns(array $options): array
    {
        $startDate = $options['start_date'] ?? now()->subYear();
        $endDate = $options['end_date'] ?? now();
        
        return [
            'seasonal_trends' => $this->getSeasonalTrends($startDate, $endDate),
            'holiday_patterns' => $this->getHolidayGiftPatterns($startDate, $endDate),
            'monthly_variations' => $this->getMonthlyGiftVariations($startDate, $endDate),
            'special_occasion_impact' => $this->getSpecialOccasionImpact($startDate, $endDate),
            'weather_correlation' => $this->getWeatherGiftCorrelation($startDate, $endDate)
        ];
    }

    /**
     * Generate gift predictions
     */
    private function generateGiftPredictions(array $analytics): array
    {
        return [
            'trend_predictions' => $this->predictGiftTrends($analytics),
            'revenue_forecast' => $this->predictGiftRevenue($analytics),
            'popular_category_predictions' => $this->predictPopularCategories($analytics),
            'seasonal_forecasts' => $this->predictSeasonalDemand($analytics),
            'user_behavior_predictions' => $this->predictUserBehavior($analytics)
        ];
    }

    /**
     * Validate occasion schedule data
     */
    private function validateOccasionScheduleData(array $scheduleData): void
    {
        $required = ['sender_id', 'recipient_id', 'occasion_type', 'occasion_date'];
        
        foreach ($required as $field) {
            if (!isset($scheduleData[$field])) {
                throw new ValidationException("Field '{$field}' is required for occasion scheduling");
            }
        }
        
        if ($scheduleData['sender_id'] === $scheduleData['recipient_id']) {
            throw new ValidationException('Cannot schedule gift for yourself');
        }
        
        $occasionDate = Carbon::parse($scheduleData['occasion_date']);
        if ($occasionDate->isPast()) {
            throw new ValidationException('Occasion date cannot be in the past');
        }
        
        if (isset($scheduleData['budget_limit']) && $scheduleData['budget_limit'] < 0) {
            throw new ValidationException('Budget limit cannot be negative');
        }
    }

    /**
     * Generate occasion gift recommendations
     */
    private function generateOccasionGiftRecommendations(array $scheduleData): Collection
    {
        $occasionType = $scheduleData['occasion_type'];
        $budgetLimit = $scheduleData['budget_limit'] ?? 100;
        $limit = 10;
        
        $recommendations = $this->getOccasionGiftRecommendations([
            'occasion_type' => $occasionType,
            'budget_limit' => $budgetLimit,
            'limit' => $limit,
            'user_preferences' => $this->getUserGiftPreferences($scheduleData['sender_id'])
        ]);
        
        // Score recommendations based on occasion suitability
        $scoredRecommendations = [];
        foreach ($recommendations as $gift) {
            if ($gift instanceof Gift) {
                $score = $this->calculateOccasionSuitabilityScore($gift, $occasionType);
                $scoredRecommendations[] = ['gift' => $gift, 'score' => $score];
            }
        }
        
        // Sort by score and return gifts
        usort($scoredRecommendations, fn($a, $b) => $b['score'] <=> $a['score']);
        return collect(array_map(fn($item) => $item['gift'], $scoredRecommendations));
    }

    /**
     * Calculate optimal delivery time
     */
    private function calculateOptimalDeliveryTime(array $scheduleData): Carbon
    {
        $occasionDate = Carbon::parse($scheduleData['occasion_date']);
        $deliveryTime = $scheduleData['delivery_time'] ?? 'morning';
        
        // Calculate optimal delivery time based on occasion and user preferences
        switch ($deliveryTime) {
            case 'morning':
                return $occasionDate->copy()->setTime(9, 0);
            case 'afternoon':
                return $occasionDate->copy()->setTime(15, 0);
            case 'evening':
                return $occasionDate->copy()->setTime(19, 0);
            default:
                return $occasionDate->copy()->setTime(12, 0);
        }
    }

    /**
     * Estimate occasion cost
     */
    private function estimateOccasionCost(Collection $recommendedGifts, array $scheduleData): float
    {
        if ($recommendedGifts->isEmpty()) {
            return 0.0;
        }
        
        $budgetLimit = $scheduleData['budget_limit'] ?? 100;
        $topRecommendations = $recommendedGifts->take(3);
        
        $estimatedCost = $topRecommendations->avg(function ($gift) {
            return $gift instanceof Gift ? $gift->getPrice()->toFloat() : 0;
        });
        
        // Apply budget constraints
        return min($estimatedCost, $budgetLimit);
    }

    /**
     * Create occasion schedule
     */
    private function createOccasionSchedule(array $scheduleData, Collection $recommendedGifts, Carbon $deliveryDate): object
    {
        // This would typically create a schedule record in the database
        // For now, return a mock schedule object
        return (object) [
            'id' => 'schedule_' . uniqid(),
            'sender_id' => $scheduleData['sender_id'],
            'recipient_id' => $scheduleData['recipient_id'],
            'occasion_type' => $scheduleData['occasion_type'],
            'occasion_date' => $scheduleData['occasion_date'],
            'delivery_date' => $deliveryDate,
            'recommended_gifts' => $recommendedGifts,
            'budget_limit' => $scheduleData['budget_limit'] ?? null,
            'auto_select_gift' => $scheduleData['auto_select_gift'] ?? false,
            'recurring' => $scheduleData['recurring'] ?? false,
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    /**
     * Setup gift automation
     */
    private function setupGiftAutomation(object $schedule, Collection $recommendedGifts): void
    {
        // This would typically set up automated gift selection and delivery
        // For now, we'll just log the automation setup
        Log::info('Gift automation setup', [
            'schedule_id' => $schedule->id,
            'recommended_gifts_count' => $recommendedGifts->count(),
            'delivery_date' => $schedule->delivery_date
        ]);
        
        // In a real implementation, this would:
        // 1. Set up a scheduled job for gift delivery
        // 2. Configure automatic gift selection based on criteria
        // 3. Set up notifications and reminders
        // 4. Handle payment processing for automated purchases
    }

    /**
     * Calculate popularity score for gift
     */
    private function calculatePopularityScore(Gift $gift): float
    {
        $baseScore = $gift->getPopularityScore();
        $purchaseCount = $gift->getPurchaseCount();
        $recentPurchases = $this->getRecentGiftPurchases($gift->getId(), 7); // Last 7 days
        
        // Calculate weighted popularity score
        $recencyWeight = min($recentPurchases / 10, 1.0); // Recent purchases boost score
        $volumeWeight = min($purchaseCount / 100, 1.0); // Volume purchases boost score
        
        return ($baseScore * 0.4) + ($recencyWeight * 100 * 0.3) + ($volumeWeight * 100 * 0.3);
    }

    /**
     * Get recommendation reasons for gift
     */
    private function getRecommendationReasons(Gift $gift): array
    {
        $reasons = [];
        
        if ($gift->isTrending()) {
            $reasons[] = 'Trending gift with high popularity';
        }
        
        if ($gift->isPremium()) {
            $reasons[] = 'Premium gift for special occasions';
        }
        
        if ($gift->isLimited()) {
            $reasons[] = 'Limited edition gift';
        }
        
        if ($gift->getRomanticLevel() >= 4) {
            $reasons[] = 'Highly romantic gift';
        }
        
        if ($gift->isRecentlyCreated()) {
            $reasons[] = 'New and fresh gift';
        }
        
        return $reasons;
    }

    /**
     * Get user gift preferences
     */
    private function getUserGiftPreferences(UserId|string $userId): array
    {
        return Cache::remember("user_gift_preferences_{$userId}", 3600, function () use ($userId) {
            // This would typically fetch from user preferences table
            return [
                'favorite_categories' => ['romantic', 'anniversary', 'birthday'],
                'price_range' => ['min' => 10, 'max' => 100],
                'preferred_styles' => ['elegant', 'cute', 'funny'],
                'avoid_categories' => ['expensive'],
                'gift_frequency' => 'monthly',
                'romantic_level_preference' => 'high'
            ];
        });
    }

    /**
     * Get user gift behavior
     */
    private function getUserGiftBehavior(UserId|string $userId): array
    {
        return Cache::remember("user_gift_behavior_{$userId}", 1800, function () use ($userId) {
            // This would typically analyze user's gift history
            return [
                'total_gifts_sent' => $this->getUserGiftTransactionCount($userId, 'sent', now()->subYear(), now()),
                'total_gifts_received' => $this->getUserGiftTransactionCount($userId, 'received', now()->subYear(), now()),
                'average_gift_value' => $this->getUserAverageGiftValue($userId),
                'most_sent_categories' => $this->getUserMostSentCategories($userId),
                'gift_timing_patterns' => $this->getUserGiftTimingPatterns($userId),
                'recipient_patterns' => $this->getUserRecipientPatterns($userId)
            ];
        });
    }

    /**
     * Get user relationship data
     */
    private function getUserRelationshipData(UserId|string $userId): array
    {
        return Cache::remember("user_relationship_data_{$userId}", 3600, function () use ($userId) {
            // This would typically integrate with relationship/matching service
            return [
                'active_relationships' => $this->getUserActiveRelationships($userId),
                'relationship_stages' => $this->getUserRelationshipStages($userId),
                'upcoming_occasions' => $this->getUserUpcomingOccasions($userId),
                'relationship_preferences' => $this->getUserRelationshipPreferences($userId)
            ];
        });
    }

    // ============================================================================
    // ADDITIONAL HELPER METHODS
    // ============================================================================

    /**
     * Calculate category trend score
     */
    private function calculateCategoryTrendScore(string $categoryId, string $period): float
    {
        // Implementation would calculate trend based on recent activity
        return rand(50, 100) / 100; // Placeholder
    }

    /**
     * Calculate category growth rate
     */
    private function calculateCategoryGrowthRate(string $categoryId, string $period): float
    {
        // Implementation would calculate growth rate
        return rand(-10, 25) / 100; // Placeholder
    }

    /**
     * Calculate personalized category score
     */
    private function calculatePersonalizedCategoryScore(object $category, array $userPreferences, array $userHistory): float
    {
        $score = 0.5; // Base score
        
        // Boost score if category is in user's favorite categories
        if (in_array($category->name, $userPreferences['favorite_categories'] ?? [])) {
            $score += 0.3;
        }
        
        // Boost score if user has sent gifts in this category before
        if (in_array($category->name, $userHistory['most_sent_categories'] ?? [])) {
            $score += 0.2;
        }
        
        return min($score, 1.0);
    }

    /**
     * Determine relationship type between users
     */
    private function determineRelationshipType(UserId|string $senderId, UserId|string $recipientId): string
    {
        // Implementation would check relationship status
        return 'dating'; // Placeholder
    }

    /**
     * Calculate relationship duration
     */
    private function calculateRelationshipDuration(UserId|string $senderId, UserId|string $recipientId): int
    {
        // Implementation would calculate days since relationship started
        return rand(30, 365); // Placeholder
    }

    /**
     * Calculate interaction frequency
     */
    private function calculateInteractionFrequency(UserId|string $senderId, UserId|string $recipientId): string
    {
        // Implementation would analyze interaction patterns
        return 'daily'; // Placeholder
    }

    /**
     * Get previous gifts between users
     */
    private function getPreviousGiftsBetweenUsers(UserId|string $senderId, UserId|string $recipientId): array
    {
        return $this->getGiftsBetweenUsers($senderId, $recipientId);
    }

    /**
     * Get shared interests between users
     */
    private function getSharedInterests(UserId|string $senderId, UserId|string $recipientId): array
    {
        // Implementation would find shared interests
        return ['music', 'travel', 'food']; // Placeholder
    }

    /**
     * Determine relationship stage
     */
    private function determineRelationshipStage(UserId|string $senderId, UserId|string $recipientId): string
    {
        // Implementation would determine based on relationship data
        return 'serious'; // Placeholder
    }

    /**
     * Get upcoming special occasions
     */
    private function getUpcomingSpecialOccasions(UserId|string $senderId, UserId|string $recipientId): array
    {
        // Implementation would get upcoming occasions
        return [
            [
                'type' => 'anniversary',
                'date' => now()->addDays(30),
                'importance' => 'high'
            ]
        ]; // Placeholder
    }

    /**
     * Calculate ML recommendation score
     */
    private function calculateMLRecommendationScore(Gift $gift, array $userProfile, ?array $relationshipContext): float
    {
        $score = 0.5; // Base score
        
        // Factor in user preferences
        if (in_array($gift->getCategory(), $userProfile['preferences']['favorite_categories'] ?? [])) {
            $score += 0.2;
        }
        
        // Factor in relationship context
        if ($relationshipContext && $gift->getRomanticLevel() >= 4 && $relationshipContext['relationship_stage'] === 'serious') {
            $score += 0.3;
        }
        
        return min($score, 1.0);
    }

    /**
     * Check if gift is suitable for occasion
     */
    private function isGiftSuitableForOccasion(Gift $gift, string $occasion): bool
    {
        $occasionGifts = [
            'birthday' => ['fun', 'celebration', 'party'],
            'anniversary' => ['romantic', 'love', 'celebration'],
            'valentine' => ['romantic', 'love', 'hearts'],
            'christmas' => ['holiday', 'celebration', 'winter']
        ];
        
        $suitableTags = $occasionGifts[$occasion] ?? [];
        return !empty(array_intersect($gift->getTags(), $suitableTags));
    }

    /**
     * Check if gift is suitable for relationship stage
     */
    private function isGiftSuitableForRelationshipStage(Gift $gift, string $stage): bool
    {
        $stageRequirements = [
            'casual' => ['romantic_level' => 'low'],
            'dating' => ['romantic_level' => 'medium'],
            'serious' => ['romantic_level' => 'high'],
            'committed' => ['romantic_level' => 'high']
        ];
        
        $requirements = $stageRequirements[$stage] ?? [];
        return $gift->getRomanticLevel() >= ($requirements['romantic_level'] === 'high' ? 4 : 2);
    }

    /**
     * Generate why recommended explanation
     */
    private function generateWhyRecommended(Gift $gift, array $userProfile): string
    {
        $reasons = [];
        
        if (in_array($gift->getCategory(), $userProfile['preferences']['favorite_categories'] ?? [])) {
            $reasons[] = "matches your favorite category";
        }
        
        if ($gift->isTrending()) {
            $reasons[] = "is currently trending";
        }
        
        if ($gift->getRomanticLevel() >= 4) {
            $reasons[] = "is highly romantic";
        }
        
        return "This gift " . implode(' and ', $reasons) . ".";
    }

    /**
     * Get recommendation key factors
     */
    private function getRecommendationKeyFactors(Gift $gift, array $userProfile): array
    {
        $factors = [];
        
        if (in_array($gift->getCategory(), $userProfile['preferences']['favorite_categories'] ?? [])) {
            $factors[] = 'category_preference';
        }
        
        if ($gift->isTrending()) {
            $factors[] = 'trending';
        }
        
        if ($gift->getPrice()->toFloat() <= ($userProfile['preferences']['price_range']['max'] ?? 100)) {
            $factors[] = 'budget_friendly';
        }
        
        return $factors;
    }


    /**
     * Get category for occasion
     */
    private function getCategoryForOccasion(array $occasion): string
    {
        $occasionCategories = [
            'birthday' => 'celebration',
            'anniversary' => 'romantic',
            'valentine' => 'romantic',
            'christmas' => 'holiday'
        ];
        
        return $occasionCategories[$occasion['type']] ?? 'general';
    }

    /**
     * Get category for relationship stage
     */
    private function getCategoryForRelationshipStage(string $stage): string
    {
        $stageCategories = [
            'casual' => 'fun',
            'dating' => 'romantic',
            'serious' => 'romantic',
            'committed' => 'romantic'
        ];
        
        return $stageCategories[$stage] ?? 'general';
    }

    /**
     * Get user active relationships
     */
    private function getUserActiveRelationships(UserId|string $userId): array
    {
        // Implementation would get from relationship service
        return []; // Placeholder
    }

    /**
     * Get user relationship stages
     */
    private function getUserRelationshipStages(UserId|string $userId): array
    {
        // Implementation would get from relationship service
        return []; // Placeholder
    }

    /**
     * Get user upcoming occasions
     */
    private function getUserUpcomingOccasions(UserId|string $userId): array
    {
        // Implementation would get from calendar/occasion service
        return []; // Placeholder
    }

    /**
     * Get user relationship preferences
     */
    private function getUserRelationshipPreferences(UserId|string $userId): array
    {
        // Implementation would get from user preferences
        return []; // Placeholder
    }

    /**
     * Calculate occasion suitability score
     */
    private function calculateOccasionSuitabilityScore(Gift $gift, string $occasionType): float
    {
        $suitability = [
            'birthday' => ['fun' => 1.0, 'celebration' => 1.0, 'romantic' => 0.3],
            'anniversary' => ['romantic' => 1.0, 'love' => 1.0, 'celebration' => 0.8],
            'valentine' => ['romantic' => 1.0, 'love' => 1.0, 'hearts' => 1.0],
            'christmas' => ['holiday' => 1.0, 'celebration' => 0.8, 'winter' => 0.9]
        ];
        
        $scores = $suitability[$occasionType] ?? [];
        $giftTags = $gift->getTags();
        
        $maxScore = 0;
        foreach ($giftTags as $tag) {
            if (isset($scores[$tag])) {
                $maxScore = max($maxScore, $scores[$tag]);
            }
        }
        
        return $maxScore > 0 ? $maxScore : 0.5;
    }

    /**
     * Predict gift trends
     */
    private function predictGiftTrends(array $analytics): array
    {
        // Implementation would use ML to predict trends
        return [
            'predicted_trending_categories' => ['romantic', 'anniversary'],
            'predicted_popular_gifts' => [],
            'confidence_score' => 0.75
        ];
    }

    /**
     * Predict gift revenue
     */
    private function predictGiftRevenue(array $analytics): array
    {
        // Implementation would predict revenue
        return [
            'predicted_revenue' => 10000.0,
            'growth_rate' => 0.15,
            'confidence_score' => 0.8
        ];
    }

    /**
     * Predict popular categories
     */
    private function predictPopularCategories(array $analytics): array
    {
        return [
            'predicted_categories' => ['romantic', 'anniversary', 'birthday'],
            'confidence_scores' => [0.8, 0.7, 0.6]
        ];
    }

    /**
     * Predict seasonal demand
     */
    private function predictSeasonalDemand(array $analytics): array
    {
        return [
            'seasonal_predictions' => [
                'spring' => ['romantic' => 0.8, 'birthday' => 0.6],
                'summer' => ['fun' => 0.9, 'travel' => 0.7],
                'fall' => ['anniversary' => 0.8, 'romantic' => 0.7],
                'winter' => ['holiday' => 0.9, 'romantic' => 0.8]
            ]
        ];
    }

    /**
     * Predict user behavior
     */
    private function predictUserBehavior(array $analytics): array
    {
        return [
            'predicted_purchase_patterns' => [],
            'predicted_spending_behavior' => [],
            'confidence_score' => 0.7
        ];
    }

    // ============================================================================
    // HELPER METHODS FOR REPOSITORY OPERATIONS
    // ============================================================================

    /**
     * Get category gift count
     */
    private function getCategoryGiftCount(string $categoryId): int
    {
        // Implementation would query database
        return rand(10, 100);
    }

    /**
     * Get category revenue
     */
    private function getCategoryRevenue(string $categoryId, string $period): float
    {
        // Implementation would calculate revenue
        return rand(1000, 10000) / 100;
    }

    /**
     * Get category popularity trend
     */
    private function getCategoryPopularityTrend(string $categoryId, string $period): float
    {
        // Implementation would calculate trend
        return rand(0, 100) / 100;
    }

    /**
     * Get category average price
     */
    private function getCategoryAveragePrice(string $categoryId): float
    {
        // Implementation would calculate average price
        return rand(10, 100) / 100;
    }

    /**
     * Get category conversion rate
     */
    private function getCategoryConversionRate(string $categoryId, string $period): float
    {
        // Implementation would calculate conversion rate
        return rand(5, 25) / 100;
    }

    /**
     * Get trending gifts in category
     */
    private function getTrendingGiftsInCategory(string $categoryId, string $period, int $limit): Collection
    {
        // Implementation would get trending gifts
        return collect([]);
    }

    /**
     * Get gift recommendations
     */
    private function getGiftRecommendations(array $criteria): Collection
    {
        // Implementation would get recommendations from repository
        return $this->productRepository->getAvailableGifts([
            'categories' => $criteria['user_profile']['preferences']['favorite_categories'] ?? [],
            'price_range' => ['min' => $criteria['budget_min'] ?? 0, 'max' => $criteria['budget_max'] ?? 1000],
            'per_page' => $criteria['limit'] ?? 20
        ])->getCollection();
    }

    /**
     * Get trending categories
     */
    private function getTrendingCategories(string $period, int $limit): Collection
    {
        // Implementation would get trending categories
        return collect([
            (object) ['name' => 'romantic'],
            (object) ['name' => 'anniversary'],
            (object) ['name' => 'birthday']
        ])->take($limit);
    }

    /**
     * Get user gift transaction count
     */
    private function getUserGiftTransactionCount(UserId|string $userId, string $type, Carbon $dateFrom, Carbon $dateTo): int
    {
        // Implementation would query user transactions
        return rand(0, 50);
    }

    /**
     * Get user favorite gift categories
     */
    private function getUserFavoriteGiftCategories(UserId|string $userId, Carbon $dateFrom, Carbon $dateTo): array
    {
        // Implementation would analyze user's gift history
        return ['romantic', 'anniversary', 'birthday'];
    }

    /**
     * Get user gift spending patterns
     */
    private function getUserGiftSpendingPatterns(UserId|string $userId, Carbon $dateFrom, Carbon $dateTo): array
    {
        // Implementation would analyze spending patterns
        return [
            'average_spending' => rand(20, 100) / 100,
            'total_spent' => rand(100, 1000) / 100,
            'spending_frequency' => 'monthly'
        ];
    }

    /**
     * Get user gift relationship insights
     */
    private function getUserGiftRelationshipInsights(UserId|string $userId, Carbon $dateFrom, Carbon $dateTo): array
    {
        // Implementation would analyze relationship patterns
        return [
            'most_gifted_to' => [],
            'relationship_stages' => [],
            'gift_reciprocity' => rand(50, 100) / 100
        ];
    }

    /**
     * Get trending gifts
     */
    private function getTrendingGifts(string $period, int $limit): Collection
    {
        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            default => now()->subWeek()
        };
        
        return DB::table('gift_transactions')
            ->join('gifts', 'gift_transactions.gift_id', '=', 'gifts.id')
            ->where('gift_transactions.created_at', '>=', $startDate)
            ->where('gift_transactions.status', 'completed')
            ->select('gifts.*', DB::raw('COUNT(gift_transactions.id) as recent_purchases'))
            ->groupBy('gifts.id')
            ->orderBy('recent_purchases', 'desc')
            ->orderBy('gifts.popularity_score', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($data) => $this->productRepository->findGiftById(new GiftId($data->id)));
    }


    /**
     * Get popularity changes
     */
    private function getPopularityChanges(Carbon $startDate, Carbon $endDate): array
    {
        return DB::table('gifts')
            ->leftJoin('gift_transactions', function ($join) use ($startDate, $endDate) {
                $join->on('gifts.id', '=', 'gift_transactions.gift_id')
                     ->whereBetween('gift_transactions.created_at', [$startDate, $endDate])
                     ->where('gift_transactions.status', 'completed');
            })
            ->select(
                'gifts.id',
                'gifts.name',
                'gifts.popularity_score',
                DB::raw('COUNT(gift_transactions.id) as recent_purchases'),
                DB::raw('(COUNT(gift_transactions.id) - gifts.popularity_score) as popularity_change')
            )
            ->groupBy('gifts.id', 'gifts.name', 'gifts.popularity_score')
            ->orderBy('popularity_change', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * Get viral gifts
     */
    private function getViralGifts(string $period): Collection
    {
        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subWeek()
        };
        
        return DB::table('gift_transactions')
            ->join('gifts', 'gift_transactions.gift_id', '=', 'gifts.id')
            ->where('gift_transactions.created_at', '>=', $startDate)
            ->where('gift_transactions.status', 'completed')
            ->select('gifts.*', DB::raw('COUNT(DISTINCT gift_transactions.sender_id) as unique_senders'))
            ->groupBy('gifts.id')
            ->having('unique_senders', '>=', 5) // At least 5 different senders
            ->orderBy('unique_senders', 'desc')
            ->orderBy('gifts.popularity_score', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($data) => $this->productRepository->findGiftById(new GiftId($data->id)));
    }

    /**
     * Get declining gifts
     */
    private function getDecliningGifts(string $period): Collection
    {
        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subWeek()
        };
        
        return DB::table('gifts')
            ->leftJoin('gift_transactions', function ($join) use ($startDate) {
                $join->on('gifts.id', '=', 'gift_transactions.gift_id')
                     ->where('gift_transactions.created_at', '>=', $startDate)
                     ->where('gift_transactions.status', 'completed');
            })
            ->select('gifts.*', DB::raw('COUNT(gift_transactions.id) as recent_purchases'))
            ->groupBy('gifts.id')
            ->having('recent_purchases', '<', 3) // Less than 3 purchases in period
            ->where('gifts.popularity_score', '>', 10) // Previously popular
            ->orderBy('gifts.popularity_score', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($data) => $this->productRepository->findGiftById(new GiftId($data->id)));
    }

    /**
     * Get gift revenue
     */
    private function getGiftRevenue(Carbon $startDate, Carbon $endDate): float
    {
        return DB::table('gift_transactions')
            ->join('gifts', 'gift_transactions.gift_id', '=', 'gifts.id')
            ->whereBetween('gift_transactions.created_at', [$startDate, $endDate])
            ->where('gift_transactions.status', 'completed')
            ->sum('gifts.price_amount');
    }

    /**
     * Get gift revenue by category
     */
    private function getGiftRevenueByCategory(Carbon $startDate, Carbon $endDate): array
    {
        return DB::table('gift_transactions')
            ->join('gifts', 'gift_transactions.gift_id', '=', 'gifts.id')
            ->whereBetween('gift_transactions.created_at', [$startDate, $endDate])
            ->where('gift_transactions.status', 'completed')
            ->select('gifts.category', DB::raw('SUM(gifts.price_amount) as revenue'))
            ->groupBy('gifts.category')
            ->orderBy('revenue', 'desc')
            ->pluck('revenue', 'gifts.category')
            ->toArray();
    }

    /**
     * Get gift revenue growth
     */
    private function getGiftRevenueGrowth(Carbon $startDate, Carbon $endDate): float
    {
        $periodLength = $startDate->diffInDays($endDate);
        $previousStartDate = $startDate->copy()->subDays($periodLength);
        $previousEndDate = $startDate->copy()->subDay();
        
        $currentRevenue = $this->getGiftRevenue($startDate, $endDate);
        $previousRevenue = $this->getGiftRevenue($previousStartDate, $previousEndDate);
        
        if ($previousRevenue == 0) {
            return $currentRevenue > 0 ? 100.0 : 0.0;
        }
        
        return round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2);
    }

    /**
     * Get average gift value
     */
    private function getAverageGiftValue(Carbon $startDate, Carbon $endDate): float
    {
        return DB::table('gift_transactions')
            ->join('gifts', 'gift_transactions.gift_id', '=', 'gifts.id')
            ->whereBetween('gift_transactions.created_at', [$startDate, $endDate])
            ->where('gift_transactions.status', 'completed')
            ->avg('gifts.price_amount') ?? 0.0;
    }

    /**
     * Get top revenue gifts
     */
    private function getTopRevenueGifts(Carbon $startDate, Carbon $endDate, int $limit): Collection
    {
        return DB::table('gift_transactions')
            ->join('gifts', 'gift_transactions.gift_id', '=', 'gifts.id')
            ->whereBetween('gift_transactions.created_at', [$startDate, $endDate])
            ->where('gift_transactions.status', 'completed')
            ->select('gifts.*', DB::raw('SUM(gifts.price_amount) as total_revenue'))
            ->groupBy('gifts.id')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($data) => $this->productRepository->findGiftById(new GiftId($data->id)));
    }

    /**
     * Get gift revenue by user segment
     */
    private function getGiftRevenueByUserSegment(Carbon $startDate, Carbon $endDate): array
    {
        return DB::table('gift_transactions')
            ->join('gifts', 'gift_transactions.gift_id', '=', 'gifts.id')
            ->join('users', 'gift_transactions.sender_id', '=', 'users.id')
            ->whereBetween('gift_transactions.created_at', [$startDate, $endDate])
            ->where('gift_transactions.status', 'completed')
            ->select(
                DB::raw('CASE 
                    WHEN users.subscription_type = "premium" THEN "premium"
                    WHEN users.subscription_type = "basic" THEN "basic"
                    ELSE "free"
                END as user_segment'),
                DB::raw('SUM(gifts.price_amount) as revenue')
            )
            ->groupBy('user_segment')
            ->orderBy('revenue', 'desc')
            ->pluck('revenue', 'user_segment')
            ->toArray();
    }

    /**
     * Get user purchase patterns
     */
    private function getUserPurchasePatterns(string $period, array $userSegments): array
    {
        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subWeek()
        };
        
        return DB::table('gift_transactions')
            ->join('users', 'gift_transactions.sender_id', '=', 'users.id')
            ->where('gift_transactions.created_at', '>=', $startDate)
            ->where('gift_transactions.status', 'completed')
            ->whereIn('users.subscription_type', $userSegments)
            ->select(
                'users.subscription_type',
                DB::raw('COUNT(*) as purchase_count'),
                DB::raw('AVG(gift_transactions.amount) as avg_purchase_value'),
                DB::raw('COUNT(DISTINCT gift_transactions.sender_id) as unique_users')
            )
            ->groupBy('users.subscription_type')
            ->get()
            ->keyBy('subscription_type')
            ->toArray();
    }

    /**
     * Get preferred purchase times
     */
    private function getPreferredPurchaseTimes(string $period, array $userSegments): array
    {
        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subWeek()
        };
        
        return DB::table('gift_transactions')
            ->join('users', 'gift_transactions.sender_id', '=', 'users.id')
            ->where('gift_transactions.created_at', '>=', $startDate)
            ->where('gift_transactions.status', 'completed')
            ->whereIn('users.subscription_type', $userSegments)
            ->select(
                'users.subscription_type',
                DB::raw('HOUR(gift_transactions.created_at) as hour'),
                DB::raw('DAYOFWEEK(gift_transactions.created_at) as day_of_week'),
                DB::raw('COUNT(*) as purchase_count')
            )
            ->groupBy('users.subscription_type', 'hour', 'day_of_week')
            ->orderBy('purchase_count', 'desc')
            ->get()
            ->groupBy('subscription_type')
            ->map(fn($group) => $group->take(5)->toArray())
            ->toArray();
    }

    /**
     * Get gift recipient patterns
     */
    private function getGiftRecipientPatterns(string $period, array $userSegments): array
    {
        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subWeek()
        };
        
        return DB::table('gift_transactions')
            ->join('users', 'gift_transactions.sender_id', '=', 'users.id')
            ->where('gift_transactions.created_at', '>=', $startDate)
            ->where('gift_transactions.status', 'completed')
            ->whereIn('users.subscription_type', $userSegments)
            ->select(
                'users.subscription_type',
                DB::raw('COUNT(DISTINCT gift_transactions.recipient_id) as unique_recipients'),
                DB::raw('COUNT(*) as total_gifts'),
                DB::raw('COUNT(*) / COUNT(DISTINCT gift_transactions.recipient_id) as avg_gifts_per_recipient')
            )
            ->groupBy('users.subscription_type')
            ->get()
            ->keyBy('subscription_type')
            ->toArray();
    }

    /**
     * Get user spending behavior
     */
    private function getUserSpendingBehavior(string $period, array $userSegments): array
    {
        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subWeek()
        };
        
        return DB::table('gift_transactions')
            ->join('users', 'gift_transactions.sender_id', '=', 'users.id')
            ->where('gift_transactions.created_at', '>=', $startDate)
            ->where('gift_transactions.status', 'completed')
            ->whereIn('users.subscription_type', $userSegments)
            ->select(
                'users.subscription_type',
                DB::raw('MIN(gift_transactions.amount) as min_spend'),
                DB::raw('MAX(gift_transactions.amount) as max_spend'),
                DB::raw('AVG(gift_transactions.amount) as avg_spend'),
                DB::raw('SUM(gift_transactions.amount) as total_spend'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy('users.subscription_type')
            ->get()
            ->keyBy('subscription_type')
            ->toArray();
    }

    /**
     * Get user engagement levels
     */
    private function getUserEngagementLevels(string $period, array $userSegments): array
    {
        $startDate = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subWeek()
        };
        
        return DB::table('gift_transactions')
            ->join('users', 'gift_transactions.sender_id', '=', 'users.id')
            ->where('gift_transactions.created_at', '>=', $startDate)
            ->where('gift_transactions.status', 'completed')
            ->whereIn('users.subscription_type', $userSegments)
            ->select(
                'users.subscription_type',
                DB::raw('COUNT(DISTINCT gift_transactions.sender_id) as active_users'),
                DB::raw('COUNT(*) as total_activity'),
                DB::raw('COUNT(*) / COUNT(DISTINCT gift_transactions.sender_id) as avg_activity_per_user')
            )
            ->groupBy('users.subscription_type')
            ->get()
            ->keyBy('subscription_type')
            ->toArray();
    }

    /**
     * Get category performance metrics
     */
    private function getCategoryPerformanceMetrics(string $period, array $categories): array
    {
        // Implementation would calculate performance metrics
        return [];
    }

    /**
     * Get category conversion rates
     */
    private function getCategoryConversionRates(string $period, array $categories): array
    {
        // Implementation would calculate conversion rates
        return [];
    }

    /**
     * Get category revenue distribution
     */
    private function getCategoryRevenueDistribution(string $period, array $categories): array
    {
        // Implementation would calculate revenue distribution
        return [];
    }

    /**
     * Get category user satisfaction
     */
    private function getCategoryUserSatisfaction(string $period, array $categories): array
    {
        // Implementation would calculate user satisfaction
        return [];
    }

    /**
     * Get category growth rates
     */
    private function getCategoryGrowthRates(string $period, array $categories): array
    {
        // Implementation would calculate growth rates
        return [];
    }

    /**
     * Get seasonal trends
     */
    private function getSeasonalTrends(Carbon $startDate, Carbon $endDate): array
    {
        // Implementation would analyze seasonal trends
        return [];
    }

    /**
     * Get holiday gift patterns
     */
    private function getHolidayGiftPatterns(Carbon $startDate, Carbon $endDate): array
    {
        // Implementation would analyze holiday patterns
        return [];
    }

    /**
     * Get monthly gift variations
     */
    private function getMonthlyGiftVariations(Carbon $startDate, Carbon $endDate): array
    {
        // Implementation would analyze monthly variations
        return [];
    }

    /**
     * Get special occasion impact
     */
    private function getSpecialOccasionImpact(Carbon $startDate, Carbon $endDate): array
    {
        // Implementation would analyze special occasion impact
        return [];
    }

    /**
     * Get weather gift correlation
     */
    private function getWeatherGiftCorrelation(Carbon $startDate, Carbon $endDate): array
    {
        // Implementation would analyze weather correlation
        return [];
    }

    /**
     * Get occasion gift recommendations
     */
    private function getOccasionGiftRecommendations(array $criteria): Collection
    {
        // Implementation would get occasion-specific recommendations
        return $this->productRepository->getAvailableGifts([
            'categories' => [$this->getCategoryForOccasion(['type' => $criteria['occasion_type']])],
            'price_range' => ['min' => 0, 'max' => $criteria['budget_limit'] ?? 100],
            'per_page' => $criteria['limit'] ?? 10
        ])->getCollection();
    }







    /**
     * Get alternative gifts
     */
    private function getAlternativeGifts(GiftId $giftId, array $userProfile, int $limit): Collection
    {
        // Get the original gift to find similar alternatives
        $originalGift = $this->productRepository->findGiftById($giftId);
        
        if (!$originalGift) {
            return collect([]);
        }
        
        // Find alternative gifts in the same category with similar price range
        $alternatives = $this->productRepository->getAvailableGifts([
            'categories' => [$originalGift->getCategory()],
            'price_range' => [
                'min' => $originalGift->getPrice()->toFloat() * 0.5,
                'max' => $originalGift->getPrice()->toFloat() * 1.5
            ],
            'per_page' => $limit + 1 // +1 to exclude the original gift
        ]);
        
        // Filter out the original gift and return alternatives
        return $alternatives->getCollection()
            ->filter(fn($gift) => !$gift->getId()->equals($giftId))
            ->take($limit);
    }

    /**
     * Get user coin balance
     */
    private function getUserCoinBalance(UserId|string $userId): int
    {
        $id = $userId instanceof UserId ? $userId->toInt() : (int) $userId;
        
        return Cache::remember("user_coin_balance_{$id}", 300, function () use ($id) {
            return DB::table('users')->where('id', $id)->value('coin_balance') ?? 0;
        });
    }

    /**
     * Get payment method for user
     */
    private function getPaymentMethod(UserId|string $userId, string $methodType): object
    {
        $id = $userId instanceof UserId ? $userId->toInt() : (int) $userId;
        
        // Get user's default payment method of the specified type
        $paymentMethods = $this->orderRepository->getUserPaymentMethods($userId, [
            'types' => [$methodType],
            'include_expired' => false
        ]);
        
        $defaultMethod = $paymentMethods->where('is_default', true)->first();
        
        if (!$defaultMethod) {
            $defaultMethod = $paymentMethods->first();
        }
        
        if (!$defaultMethod) {
            throw new PaymentMethodNotFoundException("No payment method found for user {$id} of type {$methodType}");
        }
        
        return $defaultMethod;
    }

    /**
     * Get recent gift purchases for popularity calculation
     */
    private function getRecentGiftPurchases(GiftId $giftId, int $days): int
    {
        $id = $giftId->toInt();
        
        return DB::table('gift_transactions')
            ->where('gift_id', $id)
            ->where('created_at', '>=', now()->subDays($days))
            ->where('status', 'completed')
            ->count();
    }

    /**
     * Get user average gift value
     */
    private function getUserAverageGiftValue(UserId|string $userId): float
    {
        $id = $userId instanceof UserId ? $userId->toInt() : (int) $userId;
        
        return Cache::remember("user_avg_gift_value_{$id}", 3600, function () use ($id) {
            $avgValue = DB::table('gift_transactions')
                ->join('gifts', 'gift_transactions.gift_id', '=', 'gifts.id')
                ->where('gift_transactions.sender_id', $id)
                ->where('gift_transactions.status', 'completed')
                ->avg('gifts.price_amount');
                
            return round($avgValue ?? 0.0, 2);
        });
    }

    /**
     * Get user most sent categories
     */
    private function getUserMostSentCategories(UserId|string $userId): array
    {
        $id = $userId instanceof UserId ? $userId->toInt() : (int) $userId;
        
        return Cache::remember("user_most_sent_categories_{$id}", 3600, function () use ($id) {
            return DB::table('gift_transactions')
                ->join('gifts', 'gift_transactions.gift_id', '=', 'gifts.id')
                ->where('gift_transactions.sender_id', $id)
                ->where('gift_transactions.status', 'completed')
                ->select('gifts.category', DB::raw('COUNT(*) as count'))
                ->groupBy('gifts.category')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->pluck('gifts.category')
                ->toArray();
        });
    }

    /**
     * Get user gift timing patterns
     */
    private function getUserGiftTimingPatterns(UserId|string $userId): array
    {
        $id = $userId instanceof UserId ? $userId->toInt() : (int) $userId;
        
        return Cache::remember("user_gift_timing_patterns_{$id}", 3600, function () use ($id) {
            $patterns = [];
            
            // Analyze gift sending by hour of day
            $hourlyPattern = DB::table('gift_transactions')
                ->where('sender_id', $id)
                ->where('status', 'completed')
                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
                ->groupBy('hour')
                ->orderBy('count', 'desc')
                ->limit(3)
                ->pluck('hour')
                ->toArray();
            
            $patterns['preferred_hours'] = $hourlyPattern;
            
            // Analyze gift sending by day of week
            $weeklyPattern = DB::table('gift_transactions')
                ->where('sender_id', $id)
                ->where('status', 'completed')
                ->select(DB::raw('DAYOFWEEK(created_at) as day'), DB::raw('COUNT(*) as count'))
                ->groupBy('day')
                ->orderBy('count', 'desc')
                ->limit(3)
                ->pluck('day')
                ->toArray();
            
            $patterns['preferred_days'] = $weeklyPattern;
            
            return $patterns;
        });
    }

    /**
     * Get user recipient patterns
     */
    private function getUserRecipientPatterns(UserId|string $userId): array
    {
        $id = $userId instanceof UserId ? $userId->toInt() : (int) $userId;
        
        return Cache::remember("user_recipient_patterns_{$id}", 3600, function () use ($id) {
            return DB::table('gift_transactions')
                ->where('sender_id', $id)
                ->where('status', 'completed')
                ->select('recipient_id', DB::raw('COUNT(*) as gift_count'))
                ->groupBy('recipient_id')
                ->orderBy('gift_count', 'desc')
                ->limit(10)
                ->pluck('gift_count', 'recipient_id')
                ->toArray();
        });
    }

    /**
     * Get gifts between users
     */
    private function getGiftsBetweenUsers(UserId|string $senderId, UserId|string $recipientId): array
    {
        $sender = $senderId instanceof UserId ? $senderId->toInt() : (int) $senderId;
        $recipient = $recipientId instanceof UserId ? $recipientId->toInt() : (int) $recipientId;
        
        return DB::table('gift_transactions')
            ->where(function ($query) use ($sender, $recipient) {
                $query->where(function ($q) use ($sender, $recipient) {
                    $q->where('sender_id', $sender)->where('recipient_id', $recipient);
                })->orWhere(function ($q) use ($sender, $recipient) {
                    $q->where('sender_id', $recipient)->where('recipient_id', $sender);
                });
            })
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

}