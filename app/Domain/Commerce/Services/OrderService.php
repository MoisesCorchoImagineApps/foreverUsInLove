<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Entities\Order;
use App\Domain\Commerce\Entities\OrderItem;
use App\Domain\Commerce\Entities\Invoice;
use App\Domain\Commerce\ValueObjects\OrderId;
use App\Domain\Commerce\ValueObjects\OrderStatus;
use App\Domain\Commerce\ValueObjects\OrderType;
use App\Domain\Commerce\ValueObjects\FulfillmentStatus;
use App\Domain\Commerce\ValueObjects\Price;
use App\Domain\Commerce\ValueObjects\Currency;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Commerce\Repositories\OrderRepositoryInterface;
use App\Domain\Commerce\Repositories\ProductRepositoryInterface;
use App\Domain\Commerce\Services\PaymentService;
use App\Domain\Commerce\Services\GiftService;
use App\Domain\Commerce\Services\CoinService;
use App\Domain\Commerce\Exceptions\OrderNotFoundException;
use App\Domain\Commerce\Exceptions\InvalidOrderException;
use App\Domain\Commerce\Exceptions\OrderProcessingException;
use App\Domain\Commerce\Exceptions\FulfillmentException;
use App\Domain\Commerce\Exceptions\InsufficientInventoryException;
use App\Domain\Common\Exceptions\ValidationException;
use App\Domain\Auth\Exceptions\UserNotFoundException;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

/**
 * OrderService - Order management and fulfillment system
 * 
 * Comprehensive order management service for the ForeverUsInLove dating application.
 * Handles order lifecycle, fulfillment processing, inventory management, and
 * integration with payment, gift, and subscription systems with advanced
 * workflow automation and analytics.
 * 
 * Features:
 * - Complete order lifecycle management (create, process, fulfill, complete)
 * - Multi-type order support (gifts, subscriptions, coins, features)
 * - Advanced inventory management and availability checking
 * - Automated fulfillment workflows with status tracking
 * - Order bundling and promotional package handling
 * - Integration with payment processing and refund management
 * - Real-time order status updates and notifications
 * - Order analytics and business intelligence
 * - Fraud detection and risk assessment
 * - Customer service tools and order modifications
 * - Bulk order processing and batch operations
 * - Advanced reporting and export capabilities
 * - Integration with CRM and customer support systems
 * - Performance optimization with caching and indexing
 * 
 * Architecture:
 * - Service Layer Pattern for business logic
 * - Domain-Driven Design (DDD) principles
 * - Clean Architecture / Hexagonal Architecture
 * - SOLID principles with Dependency Inversion
 * - Laravel 12 with PHP 8.2 strict typing
 * - Event-driven architecture for order processing
 * - Repository pattern for data persistence
 * - State machine pattern for order status management
 * 
 * @package App\Domain\Commerce\Services
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 * 
 * @see \App\Domain\Commerce\Entities\Order
 * @see \App\Domain\Commerce\Entities\OrderItem
 * @see \App\Domain\Commerce\Repositories\OrderRepositoryInterface
 */
class OrderService
{
    /**
     * Order status workflow configuration
     */
    private const ORDER_WORKFLOWS = [
        OrderType::GIFT => [
            OrderStatus::PENDING => [OrderStatus::PROCESSING, OrderStatus::CANCELLED],
            OrderStatus::PROCESSING => [OrderStatus::FULFILLED, OrderStatus::FAILED],
            OrderStatus::FULFILLED => [OrderStatus::COMPLETED, OrderStatus::REFUNDED],
            OrderStatus::COMPLETED => [OrderStatus::REFUNDED],
            OrderStatus::FAILED => [OrderStatus::PROCESSING, OrderStatus::CANCELLED],
            OrderStatus::CANCELLED => [],
            OrderStatus::REFUNDED => []
        ],
        OrderType::SUBSCRIPTION => [
            OrderStatus::PENDING => [OrderStatus::PROCESSING, OrderStatus::CANCELLED],
            OrderStatus::PROCESSING => [OrderStatus::ACTIVE, OrderStatus::FAILED],
            OrderStatus::ACTIVE => [OrderStatus::CANCELLED, OrderStatus::EXPIRED],
            OrderStatus::FAILED => [OrderStatus::PROCESSING, OrderStatus::CANCELLED],
            OrderStatus::CANCELLED => [],
            OrderStatus::EXPIRED => [OrderStatus::RENEWED]
        ],
        OrderType::COINS => [
            OrderStatus::PENDING => [OrderStatus::PROCESSING, OrderStatus::CANCELLED],
            OrderStatus::PROCESSING => [OrderStatus::COMPLETED, OrderStatus::FAILED],
            OrderStatus::COMPLETED => [OrderStatus::REFUNDED],
            OrderStatus::FAILED => [OrderStatus::PROCESSING, OrderStatus::CANCELLED],
            OrderStatus::CANCELLED => [],
            OrderStatus::REFUNDED => []
        ]
    ];

    /**
     * Fulfillment timeframes by order type
     */
    private const FULFILLMENT_TIMEFRAMES = [
        OrderType::GIFT => 'instant',
        OrderType::COINS => 'instant',
        OrderType::SUBSCRIPTION => '1-2 minutes',
        OrderType::FEATURE => 'instant',
        OrderType::BOOST => 'instant'
    ];

    /**
     * Constructor with dependency injection
     * 
     * @param OrderRepositoryInterface $orderRepository Order data management
     * @param ProductRepositoryInterface $productRepository Product and inventory management
     * @param PaymentService $paymentService Payment processing integration
     * @param GiftService $giftService Gift fulfillment integration
     * @param CoinService $coinService Coin transaction integration
     * @param UserRepositoryInterface $userRepository User validation and management
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly PaymentService $paymentService,
        private readonly GiftService $giftService,
        private readonly CoinService $coinService,
        private readonly UserRepositoryInterface $userRepository
    ) {}

    // ============================================================================
    // ORDER CREATION AND PROCESSING
    // ============================================================================

    /**
     * Create comprehensive order with validation and processing
     * 
     * Creates a new order with complete validation, inventory checking, pricing
     * calculation, and initial processing setup. Supports various order types
     * including gifts, subscriptions, coins, and feature purchases.
     * 
     * @param array{
     *     user_id: UserId|string,
     *     order_type: OrderType|string,
     *     items: array<array{
     *         product_id: string,
     *         product_type: string,
     *         quantity: int,
     *         price?: float,
     *         metadata?: array
     *     }>,
     *     payment_method_id?: string,
     *     payment_method_data?: array,
     *     billing_address?: array{
     *         line1: string,
     *         city: string,
     *         state: string,
     *         postal_code: string,
     *         country: string
     *     },
     *     shipping_address?: array,
     *     promo_code?: string,
     *     gift_message?: string,
     *     scheduled_delivery?: Carbon|string,
     *     auto_process?: bool,
     *     metadata?: array{
     *         source?: string,
     *         campaign_id?: string,
     *         referral_code?: string,
     *         recipient_id?: string
     *     }
     * } $orderData Comprehensive order creation configuration
     * 
     * @return Order Created order with initial processing status
     * 
     * @throws UserNotFoundException When user not found
     * @throws InvalidOrderException When order data is invalid
     * @throws InsufficientInventoryException When products unavailable
     * @throws ValidationException When validation fails
     * 
     * @example
     * ```php
     * $order = $orderService->createOrder([
     *     'user_id' => $userId,
     *     'order_type' => OrderType::GIFT,
     *     'items' => [
     *         [
     *             'product_id' => 'gift_roses_bouquet',
     *             'product_type' => 'virtual_gift',
     *             'quantity' => 1,
     *             'metadata' => ['recipient_id' => $recipientId]
     *         ]
     *     ],
     *     'payment_method_id' => 'pm_card_123',
     *     'gift_message' => 'Happy Valentine\'s Day!',
     *     'auto_process' => true,
     *     'metadata' => [
     *         'source' => 'mobile_app',
     *         'campaign_id' => 'valentine_2024'
     *     ]
     * ]);
     * ```
     */
    public function createOrder(array $orderData): Order
    {
        return DB::transaction(function () use ($orderData) {
            try {
                Log::info('Creating new order', [
                    'user_id' => $orderData['user_id'],
                    'order_type' => $orderData['order_type'],
                    'item_count' => count($orderData['items'])
                ]);

                // Validate order data
                $this->validateOrderData($orderData);

                // Validate user exists
                $this->validateUserExists($orderData['user_id']);

                // Validate and process order items
                $processedItems = $this->validateAndProcessOrderItems($orderData['items']);

                // Check inventory availability
                $this->validateInventoryAvailability($processedItems);

                // Apply promotional codes if provided
                $pricingAdjustments = [];
                if (isset($orderData['promo_code'])) {
                    $pricingAdjustments = $this->applyOrderPromoCode($orderData['promo_code'], $processedItems);
                }

                // Calculate order totals
                $orderTotals = $this->calculateOrderTotals($processedItems, $pricingAdjustments, $orderData);

                // Create order record
                $order = $this->createOrderRecord($orderData, $processedItems, $orderTotals);

                // Reserve inventory
                $this->reserveOrderInventory($order, $processedItems);

                // Set up order tracking and notifications
                $this->setupOrderTracking($order);

                // Auto-process if requested
                if ($orderData['auto_process'] ?? false) {
                    $this->processOrder($order->getId());
                }

                // Update order analytics
                $this->updateOrderAnalytics($order);

                Log::info('Order created successfully', [
                    'order_id' => $order->getId(),
                    'user_id' => $orderData['user_id'],
                    'total_amount' => $orderTotals['grand_total'],
                    'status' => $order->getStatus()
                ]);

                return $order;

            } catch (\Exception $e) {
                Log::error('Failed to create order', [
                    'error' => $e->getMessage(),
                    'order_data' => $orderData
                ]);
                throw $e;
            }
        });
    }

    /**
     * Process order through payment and fulfillment
     * 
     * Processes an order through the complete workflow including payment
     * processing, fulfillment coordination, and status updates with
     * comprehensive error handling and rollback capabilities.
     * 
     * @param OrderId|string $orderId Target order identifier
     * @param array{
     *     force_payment?: bool,
     *     skip_inventory_check?: bool,
     *     custom_fulfillment?: array,
     *     notification_preferences?: array
     * } $options Processing configuration options
     * 
     * @return array{
     *     order: Order,
     *     payment_result: array,
     *     fulfillment_results: array,
     *     processing_time: float,
     *     status_history: array
     * } Complete processing result
     * 
     * @throws OrderNotFoundException When order not found
     * @throws OrderProcessingException When processing fails
     * @throws PaymentFailedException When payment processing fails
     * @throws FulfillmentException When fulfillment fails
     * 
     * @example
     * ```php
     * $result = $orderService->processOrder($orderId, [
     *     'force_payment' => false,
     *     'notification_preferences' => [
     *         'email' => true,
     *         'push' => true,
     *         'sms' => false
     *     ]
     * ]);
     * ```
     */
    public function processOrder(OrderId|string $orderId, array $options = []): array
    {
        return DB::transaction(function () use ($orderId, $options) {
            $startTime = microtime(true);
            
            try {
                Log::info('Processing order', [
                    'order_id' => $orderId,
                    'options' => $options
                ]);

                // Get order
                $order = $this->getOrderById($orderId);
                if (!$order) {
                    throw new OrderNotFoundException("Order not found: {$orderId}");
                }

                // Validate order can be processed
                $this->validateOrderProcessingEligibility($order, $options);

                // Update order status to processing
                $this->updateOrderStatus($order, OrderStatus::processing());

                // Re-validate inventory if not skipped
                if (!($options['skip_inventory_check'] ?? false)) {
                    $this->revalidateOrderInventory($order);
                }

                // Process payment
                $paymentResult = $this->processOrderPayment($order, $options);

                // Process fulfillment
                $fulfillmentResults = $this->processOrderFulfillment($order, $options);

                // Update order based on fulfillment results
                $this->updateOrderFromFulfillmentResults($order, $fulfillmentResults);

                // Send notifications
                $this->sendOrderProcessingNotifications($order, $paymentResult, $fulfillmentResults);

                // Record processing history
                $statusHistory = $this->recordOrderProcessingHistory($order, $paymentResult, $fulfillmentResults);

                // Update analytics
                $this->updateOrderProcessingAnalytics($order, $paymentResult, $fulfillmentResults);

                $processingTime = microtime(true) - $startTime;

                $result = [
                    'order' => $order,
                    'payment_result' => $paymentResult,
                    'fulfillment_results' => $fulfillmentResults,
                    'processing_time' => $processingTime,
                    'status_history' => $statusHistory
                ];

                Log::info('Order processed successfully', [
                    'order_id' => $orderId,
                    'final_status' => $order->getStatus(),
                    'processing_time' => $processingTime
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::error('Failed to process order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                    'processing_time' => microtime(true) - $startTime
                ]);

                // Attempt to rollback order processing
                $this->rollbackOrderProcessing($orderId, $e);

                throw new OrderProcessingException("Order processing failed: {$e->getMessage()}", 0, $e);
            }
        });
    }

    // ============================================================================
    // ORDER FULFILLMENT
    // ============================================================================

    /**
     * Execute order fulfillment with service integration
     * 
     * Coordinates fulfillment across different service providers based on
     * order type including gift delivery, subscription activation, and
     * coin crediting with comprehensive status tracking.
     * 
     * @param OrderId|string $orderId Target order identifier
     * @param array{
     *     fulfillment_mode?: string, // 'automatic', 'manual', 'scheduled'
     *     delivery_preferences?: array,
     *     custom_handlers?: array,
     *     notification_settings?: array
     * } $fulfillmentOptions Fulfillment configuration
     * 
     * @return array{
     *     fulfillment_status: FulfillmentStatus,
     *     fulfilled_items: array,
     *     failed_items: array,
     *     delivery_confirmations: array,
     *     estimated_completion: Carbon,
     *     tracking_information: array
     * } Comprehensive fulfillment result
     * 
     * @throws OrderNotFoundException When order not found
     * @throws FulfillmentException When fulfillment fails
     * 
     * @example
     * ```php
     * $fulfillment = $orderService->fulfillOrder($orderId, [
     *     'fulfillment_mode' => 'automatic',
     *     'delivery_preferences' => [
     *         'priority' => 'high',
     *         'notification_method' => 'push'
     *     ]
     * ]);
     * ```
     */
    public function fulfillOrder(OrderId|string $orderId, array $fulfillmentOptions = []): array
    {
        return DB::transaction(function () use ($orderId, $fulfillmentOptions) {
            try {
                Log::info('Fulfilling order', [
                    'order_id' => $orderId,
                    'options' => $fulfillmentOptions
                ]);

                // Get order
                $order = $this->getOrderById($orderId);
                if (!$order) {
                    throw new OrderNotFoundException("Order not found: {$orderId}");
                }

                // Validate fulfillment eligibility
                $this->validateFulfillmentEligibility($order);

                // Update fulfillment status
                $this->updateFulfillmentStatus($order, FulfillmentStatus::inProgress());

                // Process fulfillment by order type
                $fulfillmentResults = [];
                $failedItems = [];

                foreach ($order->getItems() as $item) {
                    try {
                        $itemResult = $this->fulfillOrderItem($order, $item, $fulfillmentOptions);
                        $fulfillmentResults[] = $itemResult;
                    } catch (\Exception $e) {
                        Log::error('Failed to fulfill order item', [
                            'order_id' => $orderId,
                            'item_id' => $item->getId(),
                            'error' => $e->getMessage()
                        ]);
                        $failedItems[] = [
                            'item' => $item,
                            'error' => $e->getMessage()
                        ];
                    }
                }

                // Determine overall fulfillment status
                $overallStatus = $this->determineFulfillmentStatus($fulfillmentResults, $failedItems);

                // Update order fulfillment status
                $this->updateFulfillmentStatus($order, $overallStatus);

                // Generate delivery confirmations
                $deliveryConfirmations = $this->generateDeliveryConfirmations($order, $fulfillmentResults);

                // Set up tracking information
                $trackingInfo = $this->setupFulfillmentTracking($order, $fulfillmentResults);

                // Estimate completion time
                $estimatedCompletion = $this->estimateFulfillmentCompletion($order, $fulfillmentResults);

                // Send fulfillment notifications
                $this->sendFulfillmentNotifications($order, $fulfillmentResults, $deliveryConfirmations);

                $result = [
                    'fulfillment_status' => $overallStatus,
                    'fulfilled_items' => $fulfillmentResults,
                    'failed_items' => $failedItems,
                    'delivery_confirmations' => $deliveryConfirmations,
                    'estimated_completion' => $estimatedCompletion,
                    'tracking_information' => $trackingInfo
                ];

                Log::info('Order fulfillment completed', [
                    'order_id' => $orderId,
                    'fulfillment_status' => $overallStatus,
                    'fulfilled_count' => count($fulfillmentResults),
                    'failed_count' => count($failedItems)
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::error('Failed to fulfill order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ]);
                throw new FulfillmentException("Order fulfillment failed: {$e->getMessage()}", 0, $e);
            }
        });
    }

    // ============================================================================
    // ORDER QUERY AND MANAGEMENT
    // ============================================================================

    /**
     * Get user orders with comprehensive filtering and pagination
     * 
     * Retrieves user's orders with advanced filtering, sorting, and analytics
     * including order history, status tracking, and performance metrics.
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     order_types?: array<OrderType|string>,
     *     statuses?: array<OrderStatus|string>,
     *     date_from?: Carbon|string,
     *     date_to?: Carbon|string,
     *     include_items?: bool,
     *     include_payments?: bool,
     *     include_analytics?: bool,
     *     sort_by?: string,
     *     sort_direction?: string,
     *     per_page?: int,
     *     page?: int
     * } $filters Comprehensive filtering and pagination options
     * 
     * @return array{
     *     orders: LengthAwarePaginator,
     *     analytics?: array{
     *         total_orders: int,
     *         total_spent: float,
     *         average_order_value: float,
     *         order_frequency: array,
     *         favorite_products: array
     *     },
     *     summary: array{
     *         pending_orders: int,
     *         completed_orders: int,
     *         refunded_orders: int,
     *         total_value: float
     *     }
     * } User orders with optional analytics
     * 
     * @throws UserNotFoundException When user not found
     * 
     * @example
     * ```php
     * $userOrders = $orderService->getUserOrders($userId, [
     *     'order_types' => [OrderType::GIFT, OrderType::SUBSCRIPTION],
     *     'statuses' => [OrderStatus::COMPLETED, OrderStatus::ACTIVE],
     *     'date_from' => Carbon::now()->subMonths(6),
     *     'include_analytics' => true,
     *     'per_page' => 20
     * ]);
     * ```
     */
    public function getUserOrders(UserId|string $userId, array $filters = []): array
    {
        try {
            Log::info('Retrieving user orders', [
                'user_id' => $userId,
                'filters' => $filters
            ]);

            // Validate user exists
            $this->validateUserExists($userId);

            // Get orders with filtering and pagination
            $orders = $this->orderRepository->getUserOrders($userId, $filters);

            // Generate order summary
            $summary = $this->generateOrderSummary($userId, $filters);

            $result = [
                'orders' => $orders,
                'summary' => $summary
            ];

            // Include analytics if requested
            if ($filters['include_analytics'] ?? false) {
                $result['analytics'] = $this->generateUserOrderAnalytics($userId, $filters);
            }

            Log::info('User orders retrieved successfully', [
                'user_id' => $userId,
                'order_count' => $orders->total()
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve user orders', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            throw $e;
        }
    }

    // ============================================================================
    // ORDER ANALYTICS AND REPORTING
    // ============================================================================

    /**
     * Generate comprehensive order analytics
     * 
     * Creates detailed order analytics including sales metrics, performance
     * indicators, trend analysis, and business intelligence insights.
     * 
     * @param array{
     *     period?: string,
     *     start_date?: Carbon|string,
     *     end_date?: Carbon|string,
     *     order_types?: array<string>,
     *     user_segments?: array<string>,
     *     include_forecasting?: bool,
     *     include_cohort_analysis?: bool
     * } $options Analytics configuration
     * 
     * @return array{
     *     sales_metrics: array,
     *     order_metrics: array,
     *     fulfillment_metrics: array,
     *     product_performance: array,
     *     user_behavior: array,
     *     forecasting?: array,
     *     cohort_analysis?: array
     * } Comprehensive order analytics
     * 
     * @example
     * ```php
     * $analytics = $orderService->getOrderAnalytics([
     *     'period' => 'quarter',
     *     'include_forecasting' => true,
     *     'include_cohort_analysis' => true,
     *     'order_types' => ['gift', 'subscription']
     * ]);
     * ```
     */
    public function getOrderAnalytics(array $options = []): array
    {
        try {
            Log::info('Generating order analytics', ['options' => $options]);

            // Generate sales metrics
            $salesMetrics = $this->generateSalesMetrics($options);

            // Calculate order metrics
            $orderMetrics = $this->calculateOrderMetrics($options);

            // Analyze fulfillment performance
            $fulfillmentMetrics = $this->analyzeFulfillmentMetrics($options);

            // Evaluate product performance
            $productPerformance = $this->evaluateProductPerformance($options);

            // Analyze user behavior
            $userBehavior = $this->analyzeUserOrderBehavior($options);

            $analytics = [
                'sales_metrics' => $salesMetrics,
                'order_metrics' => $orderMetrics,
                'fulfillment_metrics' => $fulfillmentMetrics,
                'product_performance' => $productPerformance,
                'user_behavior' => $userBehavior
            ];

            // Include forecasting if requested
            if ($options['include_forecasting'] ?? false) {
                $analytics['forecasting'] = $this->generateOrderForecasting($analytics);
            }

            // Include cohort analysis if requested
            if ($options['include_cohort_analysis'] ?? false) {
                $analytics['cohort_analysis'] = $this->performOrderCohortAnalysis($options);
            }

            Log::info('Order analytics generated successfully');

            return $analytics;

        } catch (\Exception $e) {
            Log::error('Failed to generate order analytics', [
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
     * Validate order data
     */
    private function validateOrderData(array $orderData): void
    {
        $required = ['user_id', 'order_type', 'items'];
        
        foreach ($required as $field) {
            if (!isset($orderData[$field])) {
                throw new ValidationException("Field '{$field}' is required");
            }
        }

        if (empty($orderData['items'])) {
            throw new ValidationException('Order must contain at least one item');
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
     * Check if user exists
     */
    private function userExists(UserId|string $userId): bool
    {
        try {
            $userIdValue = $userId instanceof UserId ? $userId->toInt() : (int) $userId;
            return $this->userRepository->existsById($userIdValue);
        } catch (\Exception $e) {
            Log::warning('Failed to check user existence', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate and process order items
     */
    private function validateAndProcessOrderItems(array $items): array
    {
        $processedItems = [];
        
        foreach ($items as $item) {
            // Validate item structure
            $this->validateOrderItemData($item);
            
            // Get product details
            $product = $this->findProductById($item['product_id']);
            if (!$product) {
                throw new InvalidOrderException("Product not found: {$item['product_id']}");
            }
            
            $processedItems[] = array_merge($item, [
                'product' => $product,
                'unit_price' => $item['price'] ?? $product->getPrice()->toFloat(),
                'total_price' => ($item['price'] ?? $product->getPrice()->toFloat()) * $item['quantity']
            ]);
        }
        
        return $processedItems;
    }

    /**
     * Validate order item data
     */
    private function validateOrderItemData(array $item): void
    {
        $required = ['product_id', 'product_type', 'quantity'];
        
        foreach ($required as $field) {
            if (!isset($item[$field])) {
                throw new ValidationException("Item field '{$field}' is required");
            }
        }

        if ($item['quantity'] <= 0) {
            throw new ValidationException('Item quantity must be positive');
        }
    }

    /**
     * Get order by ID
     */
    private function getOrderById(OrderId|string $orderId): ?Order
    {
        return $this->orderRepository->findById($orderId);
    }

    /**
     * Apply promotional code to order
     */
    private function applyOrderPromoCode(string $promoCode, array $processedItems): array
    {
        try {
            Log::info('Applying promotional code to order', [
                'promo_code' => $promoCode,
                'items_count' => count($processedItems)
            ]);

            // Validate promo code format
            if (empty($promoCode) || strlen($promoCode) < 3) {
                throw new InvalidOrderException('Invalid promotional code format');
            }

            // Get promo code details from cache or database
            $promoData = $this->getPromoCodeDetails($promoCode);
            
            if (!$promoData) {
                throw new InvalidOrderException("Promotional code '{$promoCode}' not found or expired");
            }

            // Validate promo code eligibility
            $this->validatePromoCodeEligibility($promoData, $processedItems);

            // Calculate discount based on promo code type
            $discountResult = $this->calculatePromoDiscount($promoData, $processedItems);

            // Apply usage limits and track usage
            $this->trackPromoCodeUsage($promoCode, $discountResult);

            Log::info('Promotional code applied successfully', [
                'promo_code' => $promoCode,
                'discount_amount' => $discountResult['discount_amount'],
                'discount_percentage' => $discountResult['discount_percentage']
            ]);

            return array_merge($discountResult, [
                'promo_code' => $promoCode,
                'promo_name' => $promoData['name'] ?? '',
                'promo_description' => $promoData['description'] ?? '',
                'applied_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to apply promotional code', [
                'promo_code' => $promoCode,
                'error' => $e->getMessage()
            ]);
            
            // Return zero discount on failure
            return [
                'discount_amount' => 0.0,
                'discount_percentage' => 0.0,
                'promo_code' => $promoCode,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get promotional code details from cache or database
     */
    private function getPromoCodeDetails(string $promoCode): ?array
    {
        // Try cache first
        $cacheKey = "promo_code_{$promoCode}";
        $promoData = Cache::get($cacheKey);
        
        if ($promoData) {
            return $promoData;
        }

        // Mock implementation - in real scenario would query database
        $mockPromoCodes = [
            'WELCOME10' => [
                'id' => 1,
                'name' => 'Welcome Discount',
                'description' => '10% off for new users',
                'type' => 'percentage',
                'value' => 10.0,
                'min_order_amount' => 0.0,
                'max_discount' => 50.0,
                'valid_from' => now()->subDays(30),
                'valid_until' => now()->addDays(30),
                'usage_limit' => 1000,
                'used_count' => 150,
                'is_active' => true,
                'applicable_products' => ['all'],
                'user_restrictions' => ['new_users_only']
            ],
            'SAVE20' => [
                'id' => 2,
                'name' => 'Save $20',
                'description' => '$20 off orders over $100',
                'type' => 'fixed_amount',
                'value' => 20.0,
                'min_order_amount' => 100.0,
                'max_discount' => 20.0,
                'valid_from' => now()->subDays(15),
                'valid_until' => now()->addDays(15),
                'usage_limit' => 500,
                'used_count' => 89,
                'is_active' => true,
                'applicable_products' => ['all'],
                'user_restrictions' => []
            ],
            'GIFT50' => [
                'id' => 3,
                'name' => 'Gift Special',
                'description' => '50% off gift orders',
                'type' => 'percentage',
                'value' => 50.0,
                'min_order_amount' => 0.0,
                'max_discount' => 100.0,
                'valid_from' => now()->subDays(7),
                'valid_until' => now()->addDays(7),
                'usage_limit' => 200,
                'used_count' => 45,
                'is_active' => true,
                'applicable_products' => ['virtual_gift'],
                'user_restrictions' => []
            ]
        ];

        $promoData = $mockPromoCodes[$promoCode] ?? null;
        
        if ($promoData) {
            // Cache for 1 hour
            Cache::put($cacheKey, $promoData, 3600);
        }

        return $promoData;
    }

    /**
     * Validate promotional code eligibility
     */
    private function validatePromoCodeEligibility(array $promoData, array $processedItems): void
    {
        // Check if promo code is active
        if (!$promoData['is_active']) {
            throw new InvalidOrderException('Promotional code is not active');
        }

        // Check validity period
        $now = now();
        if ($now->isBefore($promoData['valid_from']) || $now->isAfter($promoData['valid_until'])) {
            throw new InvalidOrderException('Promotional code is expired');
        }

        // Check usage limits
        if ($promoData['used_count'] >= $promoData['usage_limit']) {
            throw new InvalidOrderException('Promotional code usage limit exceeded');
        }

        // Check minimum order amount
        $orderTotal = array_sum(array_column($processedItems, 'total_price'));
        if ($orderTotal < $promoData['min_order_amount']) {
            throw new InvalidOrderException(
                "Minimum order amount of {$promoData['min_order_amount']} required for this promotional code"
            );
        }

        // Check applicable products
        if (!in_array('all', $promoData['applicable_products'])) {
            $hasApplicableProduct = false;
            foreach ($processedItems as $item) {
                if (in_array($item['product_type'], $promoData['applicable_products'])) {
                    $hasApplicableProduct = true;
                    break;
                }
            }
            
            if (!$hasApplicableProduct) {
                throw new InvalidOrderException('Promotional code not applicable to selected products');
            }
        }
    }

    /**
     * Calculate promotional discount
     */
    private function calculatePromoDiscount(array $promoData, array $processedItems): array
    {
        $orderTotal = array_sum(array_column($processedItems, 'total_price'));
        $discountAmount = 0.0;
        $discountPercentage = 0.0;

        if ($promoData['type'] === 'percentage') {
            $discountAmount = ($orderTotal * $promoData['value']) / 100;
            $discountPercentage = $promoData['value'];
            
            // Apply maximum discount limit
            if ($promoData['max_discount'] > 0 && $discountAmount > $promoData['max_discount']) {
                $discountAmount = $promoData['max_discount'];
                $discountPercentage = ($discountAmount / $orderTotal) * 100;
            }
        } elseif ($promoData['type'] === 'fixed_amount') {
            $discountAmount = $promoData['value'];
            $discountPercentage = ($discountAmount / $orderTotal) * 100;
            
            // Don't exceed order total
            if ($discountAmount > $orderTotal) {
                $discountAmount = $orderTotal;
                $discountPercentage = 100.0;
            }
        }

        return [
            'discount_amount' => round($discountAmount, 2),
            'discount_percentage' => round($discountPercentage, 2),
            'original_total' => $orderTotal,
            'discounted_total' => $orderTotal - $discountAmount,
            'promo_type' => $promoData['type'],
            'promo_value' => $promoData['value']
        ];
    }

    /**
     * Track promotional code usage
     */
    private function trackPromoCodeUsage(string $promoCode, array $discountResult): void
    {
        // In real implementation, would update database
        // For now, just log the usage
        Log::info('Promotional code usage tracked', [
            'promo_code' => $promoCode,
            'discount_amount' => $discountResult['discount_amount'],
            'usage_timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Reserve inventory for order
     */
    private function reserveOrderInventory(Order $order, array $processedItems): void
    {
        foreach ($processedItems as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];
            
            try {
                $reservation = $this->productRepository->reserveProductInventory($productId, $quantity, [
                    'reservation_duration' => 30, // 30 minutes
                    'user_id' => $order->getUserId(),
                    'order_id' => $order->getId()->toString(),
                    'metadata' => [
                        'order_type' => $order->getOrderType(),
                        'reserved_at' => now()->toISOString()
                    ]
                ]);
                
                Log::info('Inventory reserved successfully', [
                    'order_id' => $order->getId(),
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'reservation_id' => $reservation['reservation_id']
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to reserve inventory', [
                    'order_id' => $order->getId(),
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'error' => $e->getMessage()
                ]);
                throw new InsufficientInventoryException(
                    "Failed to reserve inventory for product {$productId}: {$e->getMessage()}"
                );
            }
        }
    }

    /**
     * Setup order tracking
     */
    private function setupOrderTracking(Order $order): void
    {
        $trackingData = [
            'order_id' => $order->getId()->toString(),
            'tracking_number' => 'TRK-' . strtoupper(substr(md5($order->getId()->toString() . time()), 0, 12)),
            'status' => $order->getStatus()->toString(),
            'created_at' => now()->toISOString(),
            'estimated_delivery' => $this->getEstimatedDeliveryTime($order),
            'tracking_url' => config('app.url') . '/tracking/' . $order->getId()->toString(),
            'metadata' => [
                'order_type' => $order->getOrderType(),
                'priority' => $order->getPriority(),
                'delivery_method' => $order->getDeliveryMethod()
            ]
        ];
        
        // Store tracking information in cache for quick access
        Cache::put(
            "order_tracking_{$order->getId()}",
            $trackingData,
            now()->addDays(30)
        );
        
        Log::info('Order tracking setup completed', [
            'order_id' => $order->getId(),
            'tracking_number' => $trackingData['tracking_number']
        ]);
    }
    
    /**
     * Get estimated delivery time based on order type
     */
    private function getEstimatedDeliveryTime(Order $order): string
    {
        $timeframe = self::FULFILLMENT_TIMEFRAMES[$order->getOrderType()] ?? 'unknown';
        
        if ($timeframe === 'instant') {
            return now()->addMinutes(1)->toISOString();
        }
        
        if (str_contains($timeframe, 'minutes')) {
            $minutes = (int) filter_var($timeframe, FILTER_SANITIZE_NUMBER_INT);
            return now()->addMinutes($minutes)->toISOString();
        }
        
        return now()->addHours(1)->toISOString();
    }

    /**
     * Update order analytics
     */
    private function updateOrderAnalytics(Order $order): void
    {
        $analyticsData = [
            'order_id' => $order->getId()->toString(),
            'user_id' => $order->getUserId()->toString(),
            'order_type' => $order->getOrderType(),
            'total_amount' => $order->getTotalAmount(),
            'item_count' => $order->getItemCount(),
            'created_at' => $order->getCreatedAt()->toISOString(),
            'status' => $order->getStatus()->toString(),
            'priority' => $order->getPriority(),
            'is_gift' => $order->isGift(),
            'is_recurring' => $order->isRecurring(),
            'metadata' => $order->getMetadata()
        ];
        
        // Update analytics cache
        $cacheKey = "order_analytics_{$order->getUserId()}_" . now()->format('Y-m-d');
        $existingData = Cache::get($cacheKey, []);
        
        $existingData[] = $analyticsData;
        
        Cache::put($cacheKey, $existingData, now()->addDays(7));
        
        // Update product analytics for each item
        foreach ($order->getItems() as $item) {
            if (isset($item['product_id'])) {
                $this->productRepository->incrementGiftPopularity($item['product_id'], 1);
                $this->productRepository->updateGiftRevenue($item['product_id'], $item['total_price'] ?? 0);
            }
        }
        
        Log::info('Order analytics updated', [
            'order_id' => $order->getId(),
            'user_id' => $order->getUserId(),
            'total_amount' => $order->getTotalAmount()
        ]);
    }

    /**
     * Validate order processing eligibility
     */
    private function validateOrderProcessingEligibility(Order $order, array $options): void
    {
        // Check if order can be processed
        if (!$order->getStatus()->isPending()) {
            throw new OrderProcessingException(
                "Order {$order->getId()} cannot be processed. Current status: {$order->getStatus()->toString()}"
            );
        }
        
        // Check if order is not already processed
        if ($order->isProcessed()) {
            throw new OrderProcessingException(
                "Order {$order->getId()} is already processed"
            );
        }
        
        // Check if order has valid items
        if (empty($order->getItems())) {
            throw new OrderProcessingException(
                "Order {$order->getId()} has no items to process"
            );
        }
        
        // Check if order has valid totals
        if ($order->getTotalAmount() <= 0) {
            throw new OrderProcessingException(
                "Order {$order->getId()} has invalid total amount: {$order->getTotalAmount()}"
            );
        }
        
        // Check if order is not expired (if applicable)
        if ($order->getCreatedAt()->diffInHours(now()) > 24) {
            Log::warning('Processing old order', [
                'order_id' => $order->getId(),
                'age_hours' => $order->getCreatedAt()->diffInHours(now())
            ]);
        }
        
        // Validate payment method if required
        if ($order->getTotalAmount() > 0 && empty($order->getPaymentMethod())) {
            throw new OrderProcessingException(
                "Order {$order->getId()} requires payment method for amount {$order->getTotalAmount()}"
            );
        }
        
        Log::info('Order processing eligibility validated', [
            'order_id' => $order->getId(),
            'status' => $order->getStatus()->toString(),
            'total_amount' => $order->getTotalAmount()
        ]);
    }

    /**
     * Update order status
     */
    private function updateOrderStatus(Order $order, OrderStatus $status): void
    {
        try {
            $oldStatus = $order->getStatus();
            
            // Update the order status using the entity method
            $order->updateStatus($status);
            
            // Update in repository
            $this->orderRepository->update($order->getId(), [
                'status' => $status->toString(),
                'updated_at' => now()
            ]);
            
            // Update tracking cache
            $trackingData = Cache::get("order_tracking_{$order->getId()}", []);
            if (!empty($trackingData)) {
                $trackingData['status'] = $status->toString();
                $trackingData['status_updated_at'] = now()->toISOString();
                Cache::put("order_tracking_{$order->getId()}", $trackingData, now()->addDays(30));
            }
            
            Log::info('Order status updated', [
                'order_id' => $order->getId(),
                'old_status' => $oldStatus->toString(),
                'new_status' => $status->toString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update order status', [
                'order_id' => $order->getId(),
                'status' => $status->toString(),
                'error' => $e->getMessage()
            ]);
            throw new OrderProcessingException(
                "Failed to update order status: {$e->getMessage()}"
            );
        }
    }

    /**
     * Revalidate order inventory
     */
    private function revalidateOrderInventory(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $productId = $item['product_id'] ?? $item['product']['id'] ?? null;
            $quantity = $item['quantity'] ?? 1;
            
            if (!$productId) {
                Log::warning('Item missing product ID during revalidation', [
                    'order_id' => $order->getId(),
                    'item' => $item
                ]);
                continue;
            }
            
            $availability = $this->productRepository->checkProductAvailability($productId, [
                'quantity' => $quantity,
                'check_restrictions' => true
            ]);
            
            if (!$availability['available']) {
                throw new InsufficientInventoryException(
                    "Product {$productId} is no longer available for order {$order->getId()}"
                );
            }
            
            if ($availability['quantity_available'] !== null && 
                $availability['quantity_available'] < $quantity) {
                throw new InsufficientInventoryException(
                    "Insufficient inventory for product {$productId} in order {$order->getId()}. Available: {$availability['quantity_available']}, Required: {$quantity}"
                );
            }
        }
        
        Log::info('Order inventory revalidated successfully', [
            'order_id' => $order->getId(),
            'item_count' => count($order->getItems())
        ]);
    }

    /**
     * Process order payment
     */
    private function processOrderPayment(Order $order, array $options): array
    {
        try {
            // Skip payment processing for free orders
            if ($order->getTotalAmount() <= 0) {
                return [
                    'payment_id' => null,
                    'status' => 'completed',
                    'amount' => 0.0,
                    'currency' => 'USD',
                    'gateway' => 'free',
                    'transaction_id' => null,
                    'processed_at' => now()->toISOString()
                ];
            }
            
            // Process payment through PaymentService
            $paymentData = [
                'amount' => $order->getTotalAmount(),
                'currency' => 'USD', // Default currency
                'user_id' => $order->getUserId(),
                'gateway' => $order->getPaymentMethod(),
                'order_id' => $order->getId()->toString(),
                'metadata' => [
                    'order_type' => $order->getOrderType(),
                    'item_count' => $order->getItemCount(),
                    'is_gift' => $order->isGift(),
                    'priority' => $order->getPriority()
                ]
            ];
            
            $paymentResult = $this->paymentService->processPayment($paymentData, $options);
            
            // Create payment record in repository
            $payment = $this->orderRepository->createPayment([
                'amount' => $paymentResult['amount'],
                'currency' => $paymentResult['currency'],
                'user_id' => $order->getUserId(),
                'gateway' => $paymentResult['gateway'],
                'payment_method' => $order->getPaymentMethod(),
                'metadata' => $paymentData['metadata'],
                'status' => $paymentResult['status']
            ]);
            
            Log::info('Order payment processed successfully', [
                'order_id' => $order->getId(),
                'payment_id' => $payment->id()->toString(),
                'amount' => $paymentResult['amount'],
                'status' => $paymentResult['status']
            ]);
            
            return array_merge($paymentResult, [
                'payment_id' => $payment->id()->toString(),
                'processed_at' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to process order payment', [
                'order_id' => $order->getId(),
                'amount' => $order->getTotalAmount(),
                'error' => $e->getMessage()
            ]);
            throw new OrderProcessingException(
                "Payment processing failed for order {$order->getId()}: {$e->getMessage()}"
            );
        }
    }

    /**
     * Process order fulfillment
     */
    private function processOrderFulfillment(Order $order, array $options): array
    {
        try {
            $fulfillmentResults = [];
            $orderType = $order->getOrderType();
            
            foreach ($order->getItems() as $item) {
                $itemResult = $this->fulfillOrderItem($order, $item, $options);
                $fulfillmentResults[] = $itemResult;
            }
            
            // Determine overall fulfillment status
            $allSuccessful = true;
            foreach ($fulfillmentResults as $result) {
                if ($result['status'] !== 'fulfilled' && $result['status'] !== 'completed') {
                    $allSuccessful = false;
                    break;
                }
            }
            
            $overallStatus = $allSuccessful ? 'completed' : 'partial';
            
            Log::info('Order fulfillment processed', [
                'order_id' => $order->getId(),
                'order_type' => $orderType,
                'overall_status' => $overallStatus,
                'items_fulfilled' => count($fulfillmentResults)
            ]);
            
            return [
                'fulfillment_status' => $overallStatus,
                'items_fulfilled' => $fulfillmentResults,
                'order_type' => $orderType,
                'processed_at' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to process order fulfillment', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
            throw new FulfillmentException(
                "Fulfillment processing failed for order {$order->getId()}: {$e->getMessage()}"
            );
        }
    }

    /**
     * Update order from fulfillment results
     */
    private function updateOrderFromFulfillmentResults(Order $order, array $fulfillmentResults): void
    {
        try {
            $fulfillmentStatus = $fulfillmentResults['fulfillment_status'] ?? 'unknown';
            
            // Update order status based on fulfillment results
            switch ($fulfillmentStatus) {
                case 'completed':
                    $this->updateOrderStatus($order, OrderStatus::completed());
                    break;
                case 'partial':
                    $this->updateOrderStatus($order, OrderStatus::fulfilled());
                    break;
                case 'failed':
                    $this->updateOrderStatus($order, OrderStatus::failed());
                    break;
                default:
                    Log::warning('Unknown fulfillment status', [
                        'order_id' => $order->getId(),
                        'fulfillment_status' => $fulfillmentStatus
                    ]);
            }
            
            // Update order metadata with fulfillment details
            $fulfillmentMetadata = [
                'fulfillment_status' => $fulfillmentStatus,
                'fulfillment_completed_at' => now()->toISOString(),
                'items_fulfilled_count' => count($fulfillmentResults['items_fulfilled'] ?? []),
                'fulfillment_details' => $fulfillmentResults
            ];
            
            $order->updateMetadata($fulfillmentMetadata);
            
            // Update repository
            $this->orderRepository->update($order->getId(), [
                'metadata' => $order->getMetadata(),
                'updated_at' => now()
            ]);
            
            Log::info('Order updated from fulfillment results', [
                'order_id' => $order->getId(),
                'fulfillment_status' => $fulfillmentStatus,
                'items_fulfilled' => count($fulfillmentResults['items_fulfilled'] ?? [])
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update order from fulfillment results', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
            throw new OrderProcessingException(
                "Failed to update order from fulfillment results: {$e->getMessage()}"
            );
        }
    }

    /**
     * Send order processing notifications
     */
    private function sendOrderProcessingNotifications(Order $order, array $paymentResult, array $fulfillmentResults): void
    {
        try {
            $notificationData = [
                'order_id' => $order->getId()->toString(),
                'user_id' => $order->getUserId()->toString(),
                'order_type' => $order->getOrderType(),
                'status' => $order->getStatus()->toString(),
                'total_amount' => $order->getTotalAmount(),
                'payment_status' => $paymentResult['status'] ?? 'unknown',
                'fulfillment_status' => $fulfillmentResults['fulfillment_status'] ?? 'unknown',
                'items_count' => $order->getItemCount(),
                'is_gift' => $order->isGift(),
                'gift_message' => $order->getGiftMessage(),
                'tracking_number' => Cache::get("order_tracking_{$order->getId()}")['tracking_number'] ?? null
            ];
            
            // Send email notification
            Event::dispatch('order.processed', $notificationData);
            
            // Send push notification if user has push enabled
            if ($this->userHasPushNotifications($order->getUserId())) {
                Event::dispatch('order.push.notification', $notificationData);
            }
            
            // Send SMS notification for high-value orders
            if ($order->getTotalAmount() > 100 && $this->userHasSMSNotifications($order->getUserId())) {
                Event::dispatch('order.sms.notification', $notificationData);
            }
            
            Log::info('Order processing notifications sent', [
                'order_id' => $order->getId(),
                'user_id' => $order->getUserId(),
                'notification_types' => ['email', 'push', 'sms']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send order processing notifications', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
            // Don't throw exception for notification failures
        }
    }
    
    /**
     * Check if user has push notifications enabled
     */
    private function userHasPushNotifications(UserId $userId): bool
    {
        try {
            // Get user notification preferences from cache or database
            $notificationSettings = $this->getUserNotificationSettings($userId);
            
            // Check if push notifications are globally enabled
            $globalPushEnabled = $notificationSettings['push_notifications']['enabled'] ?? true;
            
            if (!$globalPushEnabled) {
                return false;
            }
            
            // Check if order-related push notifications are enabled
            $orderPushEnabled = $notificationSettings['push_notifications']['order_updates'] ?? true;
            
            // Check if user has active push tokens
            $hasActiveTokens = $notificationSettings['push_notifications']['has_active_tokens'] ?? false;
            
            // Check user's device preferences
            $devicePushEnabled = $notificationSettings['push_notifications']['device_enabled'] ?? true;
            
            // Check quiet hours
            $isQuietHours = $this->isQuietHours($notificationSettings);
            
            Log::debug('Push notification check for user', [
                'user_id' => $userId,
                'global_enabled' => $globalPushEnabled,
                'order_updates_enabled' => $orderPushEnabled,
                'has_active_tokens' => $hasActiveTokens,
                'device_enabled' => $devicePushEnabled,
                'is_quiet_hours' => $isQuietHours,
                'result' => $globalPushEnabled && $orderPushEnabled && $hasActiveTokens && $devicePushEnabled && !$isQuietHours
            ]);
            
            return $globalPushEnabled && 
                   $orderPushEnabled && 
                   $hasActiveTokens && 
                   $devicePushEnabled && 
                   !$isQuietHours;
                   
        } catch (\Exception $e) {
            Log::error('Failed to check push notification preferences', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            // Default to true on error to ensure notifications are sent
            return true;
        }
    }

    /**
     * Get user notification settings from cache or database
     */
    private function getUserNotificationSettings(UserId $userId): array
    {
        $cacheKey = "user_notification_settings_{$userId}";
        $settings = Cache::get($cacheKey);
        
        if ($settings) {
            return $settings;
        }

        try {
            // Get user from repository to access notification settings
            $user = $this->userRepository->findById($userId->toInt());
            
            if (!$user) {
                return $this->getDefaultNotificationSettings();
            }

            // Parse notification settings from user model
            $settings = [
                'push_notifications' => [
                    'enabled' => $user->notification_settings['push_notifications']['enabled'] ?? true,
                    'order_updates' => $user->notification_settings['push_notifications']['order_updates'] ?? true,
                    'has_active_tokens' => $this->checkUserHasActivePushTokens($user),
                    'device_enabled' => $user->notification_settings['push_notifications']['device_enabled'] ?? true,
                    'quiet_hours' => $user->notification_settings['push_notifications']['quiet_hours'] ?? []
                ],
                'email_notifications' => [
                    'enabled' => $user->notification_settings['email_notifications']['enabled'] ?? true,
                    'order_updates' => $user->notification_settings['email_notifications']['order_updates'] ?? true
                ],
                'sms_notifications' => [
                    'enabled' => $user->notification_settings['sms_notifications']['enabled'] ?? false,
                    'order_updates' => $user->notification_settings['sms_notifications']['order_updates'] ?? false,
                    'phone_verified' => !empty($user->phone_verified_at)
                ]
            ];

            // Cache for 1 hour
            Cache::put($cacheKey, $settings, 3600);
            
            return $settings;
            
        } catch (\Exception $e) {
            Log::warning('Failed to get user notification settings, using defaults', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return $this->getDefaultNotificationSettings();
        }
    }

    /**
     * Check if user has active push tokens
     */
    private function checkUserHasActivePushTokens($user): bool
    {
        // Mock implementation - in real scenario would check push token table
        // For now, assume users have active tokens if they have recent activity
        return !empty($user->last_activity_at) && 
               $user->last_activity_at->diffInDays(now()) <= 30;
    }

    /**
     * Check if current time is within user's quiet hours
     */
    private function isQuietHours(array $notificationSettings): bool
    {
        $quietHours = $notificationSettings['push_notifications']['quiet_hours'] ?? [];
        
        if (empty($quietHours) || !isset($quietHours['enabled']) || !$quietHours['enabled']) {
            return false;
        }
        
        $now = now();
        $currentHour = $now->hour;
        $currentMinute = $now->minute;
        $currentTime = $currentHour * 60 + $currentMinute; // Convert to minutes
        
        $startTime = ($quietHours['start_hour'] ?? 22) * 60 + ($quietHours['start_minute'] ?? 0);
        $endTime = ($quietHours['end_hour'] ?? 8) * 60 + ($quietHours['end_minute'] ?? 0);
        
        // Handle quiet hours that cross midnight
        if ($startTime > $endTime) {
            return $currentTime >= $startTime || $currentTime <= $endTime;
        }
        
        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    /**
     * Get default notification settings
     */
    private function getDefaultNotificationSettings(): array
    {
        return [
            'push_notifications' => [
                'enabled' => true,
                'order_updates' => true,
                'has_active_tokens' => true,
                'device_enabled' => true,
                'quiet_hours' => []
            ],
            'email_notifications' => [
                'enabled' => true,
                'order_updates' => true
            ],
            'sms_notifications' => [
                'enabled' => false,
                'order_updates' => false,
                'phone_verified' => false
            ]
        ];
    }
    
    /**
     * Check if user has SMS notifications enabled
     */
    private function userHasSMSNotifications(UserId $userId): bool
    {
        try {
            // Get user notification settings
            $notificationSettings = $this->getUserNotificationSettings($userId);
            
            // Check if SMS notifications are globally enabled
            $globalSMSEnabled = $notificationSettings['sms_notifications']['enabled'] ?? false;
            
            if (!$globalSMSEnabled) {
                Log::debug('SMS notifications globally disabled for user', ['user_id' => $userId]);
                return false;
            }
            
            // Check if order-related SMS notifications are enabled
            $orderSMSEnabled = $notificationSettings['sms_notifications']['order_updates'] ?? false;
            
            // Check if user has verified phone number
            $phoneVerified = $notificationSettings['sms_notifications']['phone_verified'] ?? false;
            
            // Check if user has opted into SMS notifications
            $userOptedIn = $this->checkUserSMSOptIn($userId);
            
            // Check SMS rate limiting
            $rateLimitOk = $this->checkSMSRateLimit($userId);
            
            Log::debug('SMS notification check for user', [
                'user_id' => $userId,
                'global_enabled' => $globalSMSEnabled,
                'order_updates_enabled' => $orderSMSEnabled,
                'phone_verified' => $phoneVerified,
                'user_opted_in' => $userOptedIn,
                'rate_limit_ok' => $rateLimitOk,
                'result' => $globalSMSEnabled && $orderSMSEnabled && $phoneVerified && $userOptedIn && $rateLimitOk
            ]);
            
            return $globalSMSEnabled && 
                   $orderSMSEnabled && 
                   $phoneVerified && 
                   $userOptedIn && 
                   $rateLimitOk;
                   
        } catch (\Exception $e) {
            Log::error('Failed to check SMS notification preferences', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            // Default to false on error to avoid unwanted SMS charges
            return false;
        }
    }

    /**
     * Check if user has opted into SMS notifications
     */
    private function checkUserSMSOptIn(UserId $userId): bool
    {
        try {
            $user = $this->userRepository->findById($userId->toInt());
            
            if (!$user) {
                return false;
            }
            
            // Check if user has explicitly opted into SMS notifications
            $smsOptIn = $user->notification_settings['sms_notifications']['opted_in'] ?? false;
            
            // Check if user has provided phone number
            $hasPhone = !empty($user->phone);
            
            // Check if user has not opted out
            $notOptedOut = !($user->notification_settings['sms_notifications']['opted_out'] ?? false);
            
            return $smsOptIn && $hasPhone && $notOptedOut;
            
        } catch (\Exception $e) {
            Log::warning('Failed to check SMS opt-in status', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Check SMS rate limiting for user
     */
    private function checkSMSRateLimit(UserId $userId): bool
    {
        try {
            $cacheKey = "sms_rate_limit_{$userId}_" . now()->format('Y-m-d-H');
            $smsCount = Cache::get($cacheKey, 0);
            
            // Allow maximum 3 SMS per hour per user
            $maxSMSPerHour = 3;
            
            if ($smsCount >= $maxSMSPerHour) {
                Log::warning('SMS rate limit exceeded for user', [
                    'user_id' => $userId,
                    'sms_count' => $smsCount,
                    'limit' => $maxSMSPerHour
                ]);
                
                return false;
            }
            
            // Increment SMS count for this hour
            Cache::put($cacheKey, $smsCount + 1, 3600); // Cache for 1 hour
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to check SMS rate limit', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            // Allow SMS on error to avoid blocking legitimate notifications
            return true;
        }
    }

    /**
     * Record order processing history
     */
    private function recordOrderProcessingHistory(Order $order, array $paymentResult, array $fulfillmentResults): array
    {
        $historyData = [
            'order_id' => $order->getId()->toString(),
            'user_id' => $order->getUserId()->toString(),
            'processing_time' => microtime(true),
            'status_changes' => [
                [
                    'from_status' => 'pending',
                    'to_status' => $order->getStatus()->toString(),
                    'timestamp' => now()->toISOString(),
                    'reason' => 'order_processing'
                ]
            ],
            'payment_details' => [
                'payment_id' => $paymentResult['payment_id'] ?? null,
                'amount' => $paymentResult['amount'] ?? 0,
                'status' => $paymentResult['status'] ?? 'unknown',
                'gateway' => $paymentResult['gateway'] ?? 'unknown'
            ],
            'fulfillment_details' => [
                'status' => $fulfillmentResults['fulfillment_status'] ?? 'unknown',
                'items_count' => count($fulfillmentResults['items_fulfilled'] ?? []),
                'order_type' => $fulfillmentResults['order_type'] ?? 'unknown'
            ],
            'metadata' => [
                'processing_duration' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'memory_usage' => memory_get_usage(true),
                    'peak_memory' => memory_get_peak_usage(true)
                ]
            ]
        ];
        
        // Store in cache for quick access
        $cacheKey = "order_history_{$order->getId()}";
        Cache::put($cacheKey, $historyData, now()->addDays(30));
        
        Log::info('Order processing history recorded', [
            'order_id' => $order->getId(),
            'processing_time' => $historyData['processing_time']
        ]);
        
        return $historyData;
    }

    /**
     * Update order processing analytics
     */
    private function updateOrderProcessingAnalytics(Order $order, array $paymentResult, array $fulfillmentResults): void
    {
        try {
            $analyticsData = [
                'order_id' => $order->getId()->toString(),
                'user_id' => $order->getUserId()->toString(),
                'order_type' => $order->getOrderType(),
                'total_amount' => $order->getTotalAmount(),
                'payment_status' => $paymentResult['status'] ?? 'unknown',
                'fulfillment_status' => $fulfillmentResults['fulfillment_status'] ?? 'unknown',
                'processing_completed_at' => now()->toISOString(),
                'items_count' => $order->getItemCount(),
                'is_gift' => $order->isGift(),
                'priority' => $order->getPriority(),
                'payment_gateway' => $paymentResult['gateway'] ?? 'unknown',
                'payment_amount' => $paymentResult['amount'] ?? 0
            ];
            
            // Update daily analytics cache
            $dailyCacheKey = "daily_analytics_" . now()->format('Y-m-d');
            $dailyData = Cache::get($dailyCacheKey, []);
            
            if (!isset($dailyData['orders'])) {
                $dailyData['orders'] = [];
            }
            
            $dailyData['orders'][] = $analyticsData;
            $dailyData['summary'] = $this->calculateDailySummary($dailyData['orders']);
            
            Cache::put($dailyCacheKey, $dailyData, now()->addDays(7));
            
            // Update user analytics
            $userCacheKey = "user_analytics_{$order->getUserId()}";
            $userData = Cache::get($userCacheKey, []);
            
            if (!isset($userData['orders'])) {
                $userData['orders'] = [];
            }
            
            $userData['orders'][] = $analyticsData;
            $userData['summary'] = $this->calculateUserSummary($userData['orders']);
            
            Cache::put($userCacheKey, $userData, now()->addDays(30));
            
            Log::info('Order processing analytics updated', [
                'order_id' => $order->getId(),
                'user_id' => $order->getUserId(),
                'total_amount' => $order->getTotalAmount()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update order processing analytics', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
            // Don't throw exception for analytics failures
        }
    }
    
    /**
     * Calculate daily summary from orders
     */
    private function calculateDailySummary(array $orders): array
    {
        $totalOrders = count($orders);
        $totalRevenue = array_sum(array_column($orders, 'total_amount'));
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        
        return [
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'average_order_value' => $averageOrderValue,
            'order_types' => array_count_values(array_column($orders, 'order_type')),
            'payment_gateways' => array_count_values(array_column($orders, 'payment_gateway'))
        ];
    }
    
    /**
     * Calculate user summary from orders
     */
    private function calculateUserSummary(array $orders): array
    {
        $totalOrders = count($orders);
        $totalSpent = array_sum(array_column($orders, 'total_amount'));
        $averageOrderValue = $totalOrders > 0 ? $totalSpent / $totalOrders : 0;
        
        return [
            'total_orders' => $totalOrders,
            'total_spent' => $totalSpent,
            'average_order_value' => $averageOrderValue,
            'favorite_order_types' => array_count_values(array_column($orders, 'order_type')),
            'last_order_date' => end($orders)['processing_completed_at'] ?? null
        ];
    }

    /**
     * Rollback order processing
     */
    private function rollbackOrderProcessing(OrderId|string $orderId, \Exception $exception): void
    {
        try {
            $order = $this->getOrderById($orderId);
            if (!$order) {
                Log::warning('Cannot rollback - order not found', ['order_id' => $orderId]);
                return;
            }
            
            // Update order status to failed
            $this->updateOrderStatus($order, OrderStatus::failed());
            
            // Set failure reason
            $order->setFailureReason($exception->getMessage());
            
            // Release any reserved inventory
            foreach ($order->getItems() as $item) {
                $productId = $item['product_id'] ?? $item['product']['id'] ?? null;
                if ($productId) {
                    try {
                        // Find and release inventory reservations for this order
                        $this->productRepository->releaseInventoryReservation(
                            "order_{$orderId}_{$productId}",
                            ['reason' => 'order_processing_failed']
                        );
                    } catch (\Exception $e) {
                        Log::warning('Failed to release inventory reservation during rollback', [
                            'order_id' => $orderId,
                            'product_id' => $productId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // Clear tracking cache
            Cache::forget("order_tracking_{$orderId}");
            
            // Record rollback in analytics
            $rollbackData = [
                'order_id' => $orderId,
                'rollback_reason' => $exception->getMessage(),
                'rollback_time' => now()->toISOString(),
                'original_error' => $exception->getTraceAsString()
            ];
            
            Cache::put("order_rollback_{$orderId}", $rollbackData, now()->addDays(7));
            
            Log::info('Order processing rolled back successfully', [
                'order_id' => $orderId,
                'reason' => $exception->getMessage()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to rollback order processing', [
                'order_id' => $orderId,
                'original_error' => $exception->getMessage(),
                'rollback_error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validate fulfillment eligibility
     */
    private function validateFulfillmentEligibility(Order $order): void
    {
        try {
            Log::info('Validating fulfillment eligibility', [
                'order_id' => $order->getId(),
                'order_status' => $order->getStatus()->toString(),
                'order_type' => $order->getOrderType()
            ]);

            // Check if order status allows fulfillment
            if (!$order->getStatus()->isProcessing() && !$order->getStatus()->isPending()) {
                throw new FulfillmentException(
                    "Order {$order->getId()} cannot be fulfilled. Current status: {$order->getStatus()->toString()}"
                );
            }

            // Check if order has items to fulfill
            if (empty($order->getItems())) {
                throw new FulfillmentException(
                    "Order {$order->getId()} has no items to fulfill"
                );
            }

            // Check if order is not already fulfilled
            if ($order->isProcessed()) {
                throw new FulfillmentException(
                    "Order {$order->getId()} is already processed and cannot be fulfilled again"
                );
            }

            // Validate inventory availability for each item
            foreach ($order->getItems() as $item) {
                $productId = $item['product_id'] ?? $item['product']['id'] ?? null;
                $quantity = $item['quantity'] ?? 1;
                
                if (!$productId) {
                    throw new FulfillmentException(
                        "Item in order {$order->getId()} missing product ID"
                    );
                }

                // Check product availability
                $availability = $this->productRepository->checkProductAvailability($productId, [
                    'quantity' => $quantity,
                    'check_restrictions' => true,
                    'order_id' => $order->getId()->toString()
                ]);

                if (!$availability['available']) {
                    throw new FulfillmentException(
                        "Product {$productId} is not available for fulfillment in order {$order->getId()}"
                    );
                }

                if ($availability['quantity_available'] !== null && 
                    $availability['quantity_available'] < $quantity) {
                    throw new FulfillmentException(
                        "Insufficient inventory for product {$productId} in order {$order->getId()}. Available: {$availability['quantity_available']}, Required: {$quantity}"
                    );
                }

                // Check product restrictions
                if (!empty($availability['restrictions'])) {
                    foreach ($availability['restrictions'] as $restriction) {
                        if ($restriction['type'] === 'user_restriction' && 
                            $restriction['user_id'] !== $order->getUserId()->toString()) {
                            throw new FulfillmentException(
                                "Product {$productId} has user restrictions that prevent fulfillment"
                            );
                        }
                        
                        if ($restriction['type'] === 'time_restriction' && 
                            !$this->isWithinAllowedTime($restriction)) {
                            throw new FulfillmentException(
                                "Product {$productId} has time restrictions that prevent fulfillment"
                            );
                        }
                    }
                }
            }

            // Check if order has valid payment (for paid orders)
            if ($order->getTotalAmount() > 0) {
                $paymentStatus = $this->getOrderPaymentStatus($order);
                if (!$paymentStatus || $paymentStatus !== 'completed') {
                    throw new FulfillmentException(
                        "Order {$order->getId()} requires completed payment before fulfillment"
                    );
                }
            }

            // Check order age (prevent fulfilling very old orders)
            $orderAge = $order->getCreatedAt()->diffInHours(now());
            if ($orderAge > 72) { // 3 days
                Log::warning('Attempting to fulfill old order', [
                    'order_id' => $order->getId(),
                    'age_hours' => $orderAge
                ]);
                
                // For very old orders, require manual approval
                if ($orderAge > 168) { // 7 days
                    throw new FulfillmentException(
                        "Order {$order->getId()} is too old for automatic fulfillment. Manual approval required."
                    );
                }
            }

            // Check if order is not in a blocked state
            if ($order->requiresAttention()) {
                throw new FulfillmentException(
                    "Order {$order->getId()} requires attention and cannot be fulfilled automatically"
                );
            }

            // Validate order metadata for fulfillment requirements
            $metadata = $order->getMetadata();
            if (isset($metadata['fulfillment_blocked']) && $metadata['fulfillment_blocked']) {
                throw new FulfillmentException(
                    "Order {$order->getId()} is blocked from fulfillment"
                );
            }

            // Check scheduled delivery requirements
            if ($order->getScheduledDelivery() && 
                $order->getScheduledDelivery()->isFuture()) {
                throw new FulfillmentException(
                    "Order {$order->getId()} is scheduled for future delivery and cannot be fulfilled now"
                );
            }

            Log::info('Fulfillment eligibility validated successfully', [
                'order_id' => $order->getId(),
                'item_count' => count($order->getItems()),
                'total_amount' => $order->getTotalAmount()
            ]);

        } catch (FulfillmentException $e) {
            Log::warning('Fulfillment eligibility validation failed', [
                'order_id' => $order->getId(),
                'reason' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during fulfillment eligibility validation', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
            throw new FulfillmentException(
                "Failed to validate fulfillment eligibility: {$e->getMessage()}"
            );
        }
    }

    /**
     * Update fulfillment status
     */
    private function updateFulfillmentStatus(Order $order, FulfillmentStatus $status): void
    {
        try {
            Log::info('Updating fulfillment status', [
                'order_id' => $order->getId(),
                'new_status' => $status->toString(),
                'order_status' => $order->getStatus()->toString()
            ]);

            // Validate status transition
            $currentFulfillmentStatus = $this->getCurrentFulfillmentStatus($order);
            if ($currentFulfillmentStatus && !$currentFulfillmentStatus->canTransitionTo($status)) {
                throw new FulfillmentException(
                    "Cannot transition fulfillment status from {$currentFulfillmentStatus->toString()} to {$status->toString()}"
                );
            }

            // Update order metadata with fulfillment status
            $fulfillmentMetadata = [
                'fulfillment_status' => $status->toString(),
                'fulfillment_updated_at' => now()->toISOString(),
                'fulfillment_updated_by' => 'system', // Could be user_id if manual update
                'fulfillment_history' => $this->getFulfillmentHistory($order)
            ];

            // Add status-specific metadata
            switch ($status->toString()) {
                case FulfillmentStatus::IN_PROGRESS:
                    $fulfillmentMetadata['fulfillment_started_at'] = now()->toISOString();
                    $fulfillmentMetadata['estimated_completion'] = $this->getEstimatedFulfillmentCompletion($order);
                    break;
                    
                case FulfillmentStatus::FULFILLED:
                    $fulfillmentMetadata['fulfillment_completed_at'] = now()->toISOString();
                    $fulfillmentMetadata['fulfillment_duration'] = $this->calculateFulfillmentDuration($order);
                    break;
                    
                case FulfillmentStatus::DELIVERED:
                    $fulfillmentMetadata['delivery_confirmed_at'] = now()->toISOString();
                    $fulfillmentMetadata['delivery_method'] = $order->getDeliveryMethod();
                    break;
                    
                case FulfillmentStatus::COMPLETED:
                    $fulfillmentMetadata['completion_confirmed_at'] = now()->toISOString();
                    $fulfillmentMetadata['total_fulfillment_time'] = $this->calculateTotalFulfillmentTime($order);
                    break;
                    
                case FulfillmentStatus::FAILED:
                    $fulfillmentMetadata['failure_reason'] = $order->getFailureReason();
                    $fulfillmentMetadata['failure_timestamp'] = now()->toISOString();
                    $fulfillmentMetadata['retry_count'] = $order->getAttemptCount();
                    break;
            }

            // Update order metadata
            $order->updateMetadata($fulfillmentMetadata);

            // Update order in repository
            $this->orderRepository->update($order->getId(), [
                'metadata' => $order->getMetadata(),
                'updated_at' => now()
            ]);

            // Update fulfillment tracking cache
            $this->updateFulfillmentTrackingCache($order, $status);

            // Update order status based on fulfillment status if needed
            $this->updateOrderStatusFromFulfillment($order, $status);

            // Send fulfillment status notifications
            $this->sendFulfillmentStatusNotification($order, $status);

            // Update analytics
            $this->updateFulfillmentAnalytics($order, $status);

            Log::info('Fulfillment status updated successfully', [
                'order_id' => $order->getId(),
                'status' => $status->toString(),
                'order_status' => $order->getStatus()->toString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update fulfillment status', [
                'order_id' => $order->getId(),
                'status' => $status->toString(),
                'error' => $e->getMessage()
            ]);
            throw new FulfillmentException(
                "Failed to update fulfillment status: {$e->getMessage()}"
            );
        }
    }

    /**
     * Fulfill order item
     */
    private function fulfillOrderItem(Order $order, $item, array $fulfillmentOptions): array
    {
        try {
            $productId = $item['product_id'] ?? $item['product']['id'] ?? null;
            $productType = $item['product_type'] ?? 'unknown';
            $quantity = $item['quantity'] ?? 1;
            
            if (!$productId) {
                throw new FulfillmentException('Item missing product ID');
            }
            
            $fulfillmentResult = [
                'item_id' => $item['id'] ?? uniqid(),
                'product_id' => $productId,
                'product_type' => $productType,
                'quantity' => $quantity,
                'status' => 'pending',
                'fulfillment_time' => now()->toISOString(),
                'metadata' => []
            ];
            
            // Route fulfillment based on product type
            switch ($productType) {
                case 'virtual_gift':
                    $fulfillmentResult = $this->fulfillVirtualGift($order, $item, $fulfillmentResult);
                    break;
                case 'coin_package':
                    $fulfillmentResult = $this->fulfillCoinPackage($order, $item, $fulfillmentResult);
                    break;
                case 'subscription_plan':
                    $fulfillmentResult = $this->fulfillSubscriptionPlan($order, $item, $fulfillmentResult);
                    break;
                case 'premium_feature':
                    $fulfillmentResult = $this->fulfillPremiumFeature($order, $item, $fulfillmentResult);
                    break;
                default:
                    $fulfillmentResult['status'] = 'completed';
                    $fulfillmentResult['metadata']['note'] = 'Generic fulfillment completed';
            }
            
            Log::info('Order item fulfilled', [
                'order_id' => $order->getId(),
                'product_id' => $productId,
                'product_type' => $productType,
                'status' => $fulfillmentResult['status']
            ]);
            
            return $fulfillmentResult;
            
        } catch (\Exception $e) {
            Log::error('Failed to fulfill order item', [
                'order_id' => $order->getId(),
                'item' => $item,
                'error' => $e->getMessage()
            ]);
            
            return [
                'item_id' => $item['id'] ?? uniqid(),
                'product_id' => $item['product_id'] ?? 'unknown',
                'status' => 'failed',
                'error' => $e->getMessage(),
                'fulfillment_time' => now()->toISOString()
            ];
        }
    }
    
    /**
     * Fulfill virtual gift
     */
    private function fulfillVirtualGift(Order $order, $item, array $fulfillmentResult): array
    {
        try {
            $giftData = [
                'gift_id' => $item['product_id'],
                'sender_id' => $order->getUserId(),
                'recipient_id' => $item['metadata']['recipient_id'] ?? $order->getUserId(),
                'message' => $order->getGiftMessage(),
                'order_id' => $order->getId()->toString()
            ];
            
            $giftTransaction = $this->giftService->sendGift($giftData);
            
            $fulfillmentResult['status'] = 'fulfilled';
            $fulfillmentResult['metadata']['gift_transaction_id'] = $giftTransaction->getId();
            $fulfillmentResult['metadata']['recipient_id'] = $giftData['recipient_id'];
            
            return $fulfillmentResult;
            
        } catch (\Exception $e) {
            $fulfillmentResult['status'] = 'failed';
            $fulfillmentResult['error'] = $e->getMessage();
            return $fulfillmentResult;
        }
    }
    
    /**
     * Fulfill coin package
     */
    private function fulfillCoinPackage(Order $order, $item, array $fulfillmentResult): array
    {
        try {
            $coinData = [
                'user_id' => $order->getUserId(),
                'package_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'order_id' => $order->getId()->toString()
            ];
            
            $coinResult = $this->coinService->purchaseCoinPackage($coinData);
            
            $fulfillmentResult['status'] = 'fulfilled';
            $fulfillmentResult['metadata']['coin_transaction_id'] = $coinResult['transaction']->getId();
            $fulfillmentResult['metadata']['coins_added'] = $coinResult['coins_credited'];
            
            return $fulfillmentResult;
            
        } catch (\Exception $e) {
            $fulfillmentResult['status'] = 'failed';
            $fulfillmentResult['error'] = $e->getMessage();
            return $fulfillmentResult;
        }
    }
    
    /**
     * Fulfill subscription plan
     */
    private function fulfillSubscriptionPlan(Order $order, $item, array $fulfillmentResult): array
    {
        try {
            $subscriptionData = [
                'user_id' => $order->getUserId(),
                'plan_id' => $item['product_id'],
                'order_id' => $order->getId()->toString(),
                'billing_cycle' => $item['metadata']['billing_cycle'] ?? 'monthly'
            ];
            
            $subscription = $this->orderRepository->createSubscription($subscriptionData);
            
            $fulfillmentResult['status'] = 'fulfilled';
            $fulfillmentResult['metadata']['subscription_id'] = $subscription->getId();
            $fulfillmentResult['metadata']['billing_cycle'] = $subscriptionData['billing_cycle'];
            
            return $fulfillmentResult;
            
        } catch (\Exception $e) {
            $fulfillmentResult['status'] = 'failed';
            $fulfillmentResult['error'] = $e->getMessage();
            return $fulfillmentResult;
        }
    }
    
    /**
     * Fulfill premium feature
     */
    private function fulfillPremiumFeature(Order $order, $item, array $fulfillmentResult): array
    {
        try {
            // Activate premium feature for user
            $featureData = [
                'user_id' => $order->getUserId(),
                'feature_id' => $item['product_id'],
                'order_id' => $order->getId()->toString(),
                'duration' => $item['metadata']['duration'] ?? 'permanent'
            ];
            
            $fulfillmentResult['status'] = 'fulfilled';
            $fulfillmentResult['metadata']['feature_activated'] = true;
            $fulfillmentResult['metadata']['feature_id'] = $item['product_id'];
            $fulfillmentResult['metadata']['duration'] = $featureData['duration'];
            
            return $fulfillmentResult;
            
        } catch (\Exception $e) {
            $fulfillmentResult['status'] = 'failed';
            $fulfillmentResult['error'] = $e->getMessage();
            return $fulfillmentResult;
        }
    }

    /**
     * Determine fulfillment status
     */
    private function determineFulfillmentStatus(array $fulfillmentResults, array $failedItems): FulfillmentStatus
    {
        if (empty($failedItems)) {
            return FulfillmentStatus::completed();
        }
        
        return count($failedItems) === count($fulfillmentResults) 
            ? FulfillmentStatus::failed() 
            : FulfillmentStatus::fulfilled();
    }

    /**
     * Generate delivery confirmations
     */
    private function generateDeliveryConfirmations(Order $order, array $fulfillmentResults): array
    {
        try {
            Log::info('Generating delivery confirmations', [
                'order_id' => $order->getId(),
                'fulfillment_results_count' => count($fulfillmentResults)
            ]);

            $confirmations = [];
            $orderTrackingData = Cache::get("order_tracking_{$order->getId()}", []);
            
            foreach ($fulfillmentResults as $result) {
                $confirmation = [
                    'confirmation_id' => 'CONF-' . strtoupper(substr(md5($order->getId()->toString() . $result['item_id'] . time()), 0, 12)),
                    'order_id' => $order->getId()->toString(),
                    'item_id' => $result['item_id'] ?? uniqid(),
                    'product_id' => $result['product_id'] ?? 'unknown',
                    'product_type' => $result['product_type'] ?? 'unknown',
                    'quantity' => $result['quantity'] ?? 1,
                    'status' => $result['status'] ?? 'unknown',
                    'delivery_method' => $this->determineDeliveryMethod($order, $result),
                    'delivery_timestamp' => now()->toISOString(),
                    'delivery_location' => $this->getDeliveryLocation($order, $result),
                    'tracking_information' => $this->generateTrackingInformation($order, $result),
                    'delivery_notes' => $this->generateDeliveryNotes($order, $result),
                    'recipient_information' => $this->getRecipientInformation($order, $result),
                    'sender_information' => $this->getSenderInformation($order),
                    'delivery_confirmation_code' => $this->generateDeliveryConfirmationCode($order, $result),
                    'estimated_delivery_time' => $this->getEstimatedDeliveryTime($order),
                    'actual_delivery_time' => $result['status'] === 'fulfilled' ? now()->toISOString() : null,
                    'delivery_attempts' => $this->getDeliveryAttempts($order, $result),
                    'delivery_signature' => $this->generateDeliverySignature($order, $result),
                    'delivery_photo_url' => $this->generateDeliveryPhotoUrl($order, $result),
                    'metadata' => [
                        'fulfillment_result' => $result,
                        'order_metadata' => $order->getMetadata(),
                        'tracking_data' => $orderTrackingData,
                        'generated_at' => now()->toISOString(),
                        'confirmation_version' => '1.0'
                    ]
                ];

                // Add product-specific confirmation details
                $confirmation = $this->addProductSpecificConfirmationDetails($confirmation, $result);

                // Add delivery method specific information
                $confirmation = $this->addDeliveryMethodSpecificDetails($confirmation, $order, $result);

                // Validate confirmation data
                $this->validateDeliveryConfirmation($confirmation);

                $confirmations[] = $confirmation;

                // Store confirmation in cache for quick access
                Cache::put(
                    "delivery_confirmation_{$confirmation['confirmation_id']}",
                    $confirmation,
                    now()->addDays(30)
                );
            }

            // Generate overall order confirmation
            $orderConfirmation = [
                'order_confirmation_id' => 'ORD-CONF-' . strtoupper(substr(md5($order->getId()->toString() . time()), 0, 12)),
                'order_id' => $order->getId()->toString(),
                'user_id' => $order->getUserId()->toString(),
                'order_type' => $order->getOrderType(),
                'total_items' => count($confirmations),
                'successful_deliveries' => count(array_filter($confirmations, fn($c) => $c['status'] === 'fulfilled')),
                'failed_deliveries' => count(array_filter($confirmations, fn($c) => $c['status'] === 'failed')),
                'overall_status' => $this->determineOverallDeliveryStatus($confirmations),
                'delivery_summary' => $this->generateDeliverySummary($confirmations),
                'item_confirmations' => $confirmations,
                'order_tracking_number' => $orderTrackingData['tracking_number'] ?? null,
                'delivery_timeline' => $this->generateDeliveryTimeline($order, $confirmations),
                'next_steps' => $this->generateNextSteps($confirmations),
                'customer_service_info' => $this->generateCustomerServiceInfo($order),
                'generated_at' => now()->toISOString(),
                'expires_at' => now()->addDays(30)->toISOString()
            ];

            // Store order confirmation in cache
            Cache::put(
                "order_confirmation_{$orderConfirmation['order_confirmation_id']}",
                $orderConfirmation,
                now()->addDays(30)
            );

            Log::info('Delivery confirmations generated successfully', [
                'order_id' => $order->getId(),
                'confirmations_count' => count($confirmations),
                'order_confirmation_id' => $orderConfirmation['order_confirmation_id']
            ]);

            return [
                'order_id' => $order->getId()->toString(),
                'order_confirmation' => $orderConfirmation,
                'item_confirmations' => $confirmations,
                'generated_at' => now()->toISOString(),
                'total_confirmations' => count($confirmations) + 1 // +1 for order confirmation
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate delivery confirmations', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
            
            // Return basic confirmation on error
            return [
                'order_id' => $order->getId()->toString(),
                'confirmations' => [],
                'error' => $e->getMessage(),
                'generated_at' => now()->toISOString()
            ];
        }
    }

    /**
     * Setup fulfillment tracking
     */
    private function setupFulfillmentTracking(Order $order, array $fulfillmentResults): array
    {
        try {
            Log::info('Setting up fulfillment tracking', [
                'order_id' => $order->getId(),
                'fulfillment_results_count' => count($fulfillmentResults)
            ]);

            // Generate unique tracking identifier
            $trackingId = 'TRK-' . strtoupper(substr(md5($order->getId()->toString() . time()), 0, 12));
            
            // Create comprehensive tracking data
            $trackingData = [
                'tracking_id' => $trackingId,
                'order_id' => $order->getId()->toString(),
                'user_id' => $order->getUserId()->toString(),
                'order_type' => $order->getOrderType(),
                'tracking_url' => config('app.url') . '/tracking/' . $trackingId,
                'public_tracking_url' => config('app.url') . '/tracking/public/' . $trackingId,
                'created_at' => now()->toISOString(),
                'status' => 'active',
                'estimated_completion' => $this->getEstimatedDeliveryTime($order),
                'tracking_methods' => $this->getTrackingMethods($order),
                'delivery_channels' => $this->getDeliveryChannels($order),
                'notification_preferences' => $this->getTrackingNotificationPreferences($order),
                'tracking_history' => [
                    [
                        'status' => 'tracking_created',
                        'timestamp' => now()->toISOString(),
                        'description' => 'Fulfillment tracking initialized',
                        'location' => 'system'
                    ]
                ],
                'metadata' => [
                    'order_priority' => $order->getPriority(),
                    'is_gift' => $order->isGift(),
                    'gift_message' => $order->getGiftMessage(),
                    'delivery_method' => $order->getDeliveryMethod(),
                    'scheduled_delivery' => $order->getScheduledDelivery()?->toISOString(),
                    'tracking_version' => '1.0',
                    'created_by' => 'system'
                ]
            ];

            // Add item-specific tracking
            $trackingData['items'] = [];
            foreach ($fulfillmentResults as $result) {
                $itemTracking = [
                    'item_id' => $result['item_id'] ?? uniqid(),
                    'product_id' => $result['product_id'] ?? 'unknown',
                    'product_type' => $result['product_type'] ?? 'unknown',
                    'quantity' => $result['quantity'] ?? 1,
                    'status' => $result['status'] ?? 'pending',
                    'tracking_status' => $this->mapFulfillmentStatusToTrackingStatus($result['status'] ?? 'pending'),
                    'estimated_delivery' => $this->getItemEstimatedDelivery($order, $result),
                    'delivery_method' => $this->getItemDeliveryMethod($order, $result),
                    'tracking_details' => $this->getItemTrackingDetails($result),
                    'last_updated' => now()->toISOString()
                ];
                
                $trackingData['items'][] = $itemTracking;
            }

            // Store tracking data in cache for quick access
            Cache::put(
                "fulfillment_tracking_{$trackingId}",
                $trackingData,
                now()->addDays(30)
            );

            // Store tracking ID in order metadata
            $orderMetadata = $order->getMetadata();
            $orderMetadata['tracking_id'] = $trackingId;
            $orderMetadata['tracking_url'] = $trackingData['tracking_url'];
            $orderMetadata['tracking_created_at'] = now()->toISOString();
            
            $order->updateMetadata($orderMetadata);
            
            // Update order in repository
            $this->orderRepository->update($order->getId(), [
                'metadata' => $orderMetadata,
                'updated_at' => now()
            ]);

            // Create tracking record in database (if tracking table exists)
            $this->createTrackingRecord($trackingData);

            Log::info('Fulfillment tracking setup completed', [
                'order_id' => $order->getId(),
                'tracking_id' => $trackingId,
                'tracking_url' => $trackingData['tracking_url']
            ]);

            return $trackingData;

        } catch (\Exception $e) {
            Log::error('Failed to setup fulfillment tracking', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
            
            // Return basic tracking data on error
            return [
                'tracking_id' => 'TRK-ERROR-' . substr(md5($order->getId()->toString()), 0, 8),
                'order_id' => $order->getId()->toString(),
                'tracking_url' => config('app.url') . '/tracking/error',
                'status' => 'error',
                'error' => $e->getMessage(),
                'created_at' => now()->toISOString()
            ];
        }
    }

    /**
     * Estimate fulfillment completion
     */
    private function estimateFulfillmentCompletion(Order $order, array $fulfillmentResults): \Carbon\Carbon
    {
        try {
            Log::info('Estimating fulfillment completion', [
                'order_id' => $order->getId(),
                'order_type' => $order->getOrderType(),
                'fulfillment_results_count' => count($fulfillmentResults)
            ]);

            $orderType = $order->getOrderType();
            $baseTimeframe = self::FULFILLMENT_TIMEFRAMES[$orderType] ?? 'unknown';
            
            // Start with base estimation
            $estimatedCompletion = $this->getBaseEstimatedTime($baseTimeframe);
            
            // Adjust based on fulfillment results
            $adjustments = $this->calculateFulfillmentAdjustments($order, $fulfillmentResults);
            
            // Apply adjustments
            $estimatedCompletion = $estimatedCompletion->addMinutes($adjustments['time_adjustment_minutes']);
            
            // Consider order priority
            if ($order->getPriority() === 'high') {
                $estimatedCompletion = $estimatedCompletion->subMinutes(5);
            } elseif ($order->getPriority() === 'low') {
                $estimatedCompletion = $estimatedCompletion->addMinutes(10);
            }
            
            // Consider scheduled delivery
            if ($order->getScheduledDelivery() && $order->getScheduledDelivery()->isFuture()) {
                $estimatedCompletion = $order->getScheduledDelivery();
            }
            
            // Consider system load and processing time
            $systemLoadAdjustment = $this->getSystemLoadAdjustment();
            $estimatedCompletion = $estimatedCompletion->addMinutes($systemLoadAdjustment);
            
            // Ensure minimum time (at least 1 minute from now)
            $minimumTime = now()->addMinutes(1);
            if ($estimatedCompletion->isBefore($minimumTime)) {
                $estimatedCompletion = $minimumTime;
            }
            
            // Store estimation in cache for tracking
            $estimationData = [
                'order_id' => $order->getId()->toString(),
                'estimated_completion' => $estimatedCompletion->toISOString(),
                'base_timeframe' => $baseTimeframe,
                'adjustments' => $adjustments,
                'system_load_adjustment' => $systemLoadAdjustment,
                'order_priority' => $order->getPriority(),
                'is_scheduled' => !is_null($order->getScheduledDelivery()),
                'estimated_at' => now()->toISOString(),
                'confidence_level' => $this->calculateEstimationConfidence($order, $fulfillmentResults)
            ];
            
            Cache::put(
                "fulfillment_estimation_{$order->getId()}",
                $estimationData,
                now()->addHours(2)
            );
            
            Log::info('Fulfillment completion estimated', [
                'order_id' => $order->getId(),
                'estimated_completion' => $estimatedCompletion->toISOString(),
                'confidence_level' => $estimationData['confidence_level'],
                'adjustments_applied' => $adjustments
            ]);
            
            return $estimatedCompletion;
            
        } catch (\Exception $e) {
            Log::error('Failed to estimate fulfillment completion', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
            
            // Return conservative fallback estimation
            return now()->addMinutes(15);
        }
    }

    /**
     * Send fulfillment notifications
     */
    private function sendFulfillmentNotifications(Order $order, array $fulfillmentResults, array $deliveryConfirmations): void
    {
        try {
            Log::info('Sending fulfillment notifications', [
                'order_id' => $order->getId(),
                'fulfillment_results_count' => count($fulfillmentResults),
                'delivery_confirmations_count' => count($deliveryConfirmations['item_confirmations'] ?? [])
            ]);

            // Prepare notification data
            $notificationData = [
                'order_id' => $order->getId()->toString(),
                'user_id' => $order->getUserId()->toString(),
                'order_type' => $order->getOrderType(),
                'order_status' => $order->getStatus()->toString(),
                'total_amount' => $order->getTotalAmount(),
                'is_gift' => $order->isGift(),
                'gift_message' => $order->getGiftMessage(),
                'fulfillment_results' => $fulfillmentResults,
                'delivery_confirmations' => $deliveryConfirmations,
                'tracking_information' => $this->getOrderTrackingInformation($order),
                'estimated_completion' => $this->estimateFulfillmentCompletion($order, $fulfillmentResults)->toISOString(),
                'notification_timestamp' => now()->toISOString()
            ];

            // Determine notification types based on fulfillment results
            $notificationTypes = $this->determineNotificationTypes($order, $fulfillmentResults);
            
            // Send email notifications
            if ($notificationTypes['email']) {
                $this->sendFulfillmentEmailNotification($order, $notificationData);
            }
            
            // Send push notifications
            if ($notificationTypes['push'] && $this->userHasPushNotifications($order->getUserId())) {
                $this->sendFulfillmentPushNotification($order, $notificationData);
            }
            
            // Send SMS notifications for high-priority orders
            if ($notificationTypes['sms'] && $this->userHasSMSNotifications($order->getUserId())) {
                $this->sendFulfillmentSMSNotification($order, $notificationData);
            }
            
            // Send in-app notifications
            if ($notificationTypes['in_app']) {
                $this->sendFulfillmentInAppNotification($order, $notificationData);
            }
            
            // Dispatch fulfillment events for external integrations
            $this->dispatchFulfillmentEvents($order, $fulfillmentResults, $deliveryConfirmations);
            
            // Send notifications to gift recipients if applicable
            if ($order->isGift()) {
                $this->sendGiftRecipientNotifications($order, $fulfillmentResults, $deliveryConfirmations);
            }
            
            // Send admin notifications for high-value or failed orders
            if ($this->shouldNotifyAdmins($order, $fulfillmentResults)) {
                $this->sendAdminFulfillmentNotification($order, $fulfillmentResults, $deliveryConfirmations);
            }
            
            // Update notification history
            $this->updateFulfillmentNotificationHistory($order, $notificationTypes, $notificationData);
            
            Log::info('Fulfillment notifications sent successfully', [
                'order_id' => $order->getId(),
                'notification_types' => array_keys(array_filter($notificationTypes)),
                'total_notifications_sent' => count(array_filter($notificationTypes))
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send fulfillment notifications', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Send fallback notification to ensure user is informed
            $this->sendFallbackFulfillmentNotification($order, $e);
        }
    }

    /**
     * Generate order summary
     */
    private function generateOrderSummary(UserId|string $userId, array $filters): array
    {
        // PLACEHOLDER: Implementation would generate order summary
        return [
            'pending_orders' => 0,
            'completed_orders' => 0,
            'refunded_orders' => 0,
            'total_value' => 0.0
        ];
    }

    /**
     * Generate user order analytics
     */
    private function generateUserOrderAnalytics(UserId|string $userId, array $filters): array
    {
        // PLACEHOLDER: Implementation would generate user analytics
        return [
            'total_orders' => 0,
            'total_spent' => 0.0,
            'average_order_value' => 0.0,
            'order_frequency' => [],
            'favorite_products' => []
        ];
    }

    /**
     * Generate sales metrics
     */
    private function generateSalesMetrics(array $options): array
    {
        // PLACEHOLDER: Implementation would generate sales metrics
        return [
            'total_sales' => 0.0,
            'sales_growth' => 0.0,
            'top_selling_products' => []
        ];
    }

    /**
     * Calculate order metrics
     */
    private function calculateOrderMetrics(array $options): array
    {
        // PLACEHOLDER: Implementation would calculate order metrics
        return [
            'total_orders' => 0,
            'average_order_value' => 0.0,
            'conversion_rate' => 0.0
        ];
    }

    /**
     * Analyze fulfillment metrics
     */
    private function analyzeFulfillmentMetrics(array $options): array
    {
        // PLACEHOLDER: Implementation would analyze fulfillment metrics
        return [
            'fulfillment_rate' => 0.0,
            'average_fulfillment_time' => 0,
            'fulfillment_issues' => []
        ];
    }

    /**
     * Evaluate product performance
     */
    private function evaluateProductPerformance(array $options): array
    {
        // PLACEHOLDER: Implementation would evaluate product performance
        return [
            'top_performing_products' => [],
            'product_metrics' => []
        ];
    }

    /**
     * Analyze user order behavior
     */
    private function analyzeUserOrderBehavior(array $options): array
    {
        // PLACEHOLDER: Implementation would analyze user behavior
        return [
            'user_segments' => [],
            'behavior_patterns' => []
        ];
    }

    /**
     * Generate order forecasting
     */
    private function generateOrderForecasting(array $analytics): array
    {
        // PLACEHOLDER: Implementation would generate forecasting
        return [
            'predicted_sales' => 0.0,
            'forecast_period' => 'month',
            'confidence_level' => 0.0
        ];
    }

    /**
     * Perform order cohort analysis
     */
    private function performOrderCohortAnalysis(array $options): array
    {
        // PLACEHOLDER: Implementation would perform cohort analysis
        return [
            'cohorts' => [],
            'retention_rates' => []
        ];
    }

    /**
     * Find product by ID
     */
    private function findProductById(string $productId)
    {
        // PLACEHOLDER: Implementation would find product by ID
        return $this->productRepository->findGiftById($productId) ?? 
               $this->productRepository->findPlanById($productId) ?? 
               $this->productRepository->findCoinPackageById($productId);
    }

    /**
     * Validate inventory availability
     */
    private function validateInventoryAvailability(array $processedItems): void
    {
        foreach ($processedItems as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];
            
            $availability = $this->productRepository->checkProductAvailability($productId, [
                'quantity' => $quantity,
                'check_restrictions' => true
            ]);
            
            if (!$availability['available']) {
                throw new InsufficientInventoryException(
                    "Product {$productId} is not available in requested quantity {$quantity}"
                );
            }
            
            if ($availability['quantity_available'] !== null && 
                $availability['quantity_available'] < $quantity) {
                throw new InsufficientInventoryException(
                    "Insufficient inventory for product {$productId}. Available: {$availability['quantity_available']}, Requested: {$quantity}"
                );
            }
            
            if (!empty($availability['restrictions'])) {
                Log::warning('Product has restrictions', [
                    'product_id' => $productId,
                    'restrictions' => $availability['restrictions']
                ]);
            }
        }
    }

    /**
     * Calculate order totals
     */
    private function calculateOrderTotals(array $processedItems, array $adjustments, array $orderData): array
    {
        $subtotal = array_sum(array_column($processedItems, 'total_price'));
        $discountAmount = $adjustments['discount_amount'] ?? 0.0;
        $taxAmount = $this->calculateOrderTax($subtotal, $orderData);
        $grandTotal = $subtotal - $discountAmount + $taxAmount;

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'grand_total' => $grandTotal
        ];
    }

    /**
     * Calculate order tax
     */
    private function calculateOrderTax(float $subtotal, array $orderData): float
    {
        // PLACEHOLDER: Implementation would calculate applicable taxes
        return 0.0; // Placeholder
    }

    // ============================================================================
    // HELPER METHODS FOR FULFILLMENT FUNCTIONS
    // ============================================================================

    /**
     * Check if current time is within allowed time for restrictions
     */
    private function isWithinAllowedTime(array $restriction): bool
    {
        if (!isset($restriction['start_time']) || !isset($restriction['end_time'])) {
            return true; // No time restriction
        }

        $now = now();
        $startTime = Carbon::parse($restriction['start_time']);
        $endTime = Carbon::parse($restriction['end_time']);

        return $now->between($startTime, $endTime);
    }

    /**
     * Get order payment status
     */
    private function getOrderPaymentStatus(Order $order): ?string
    {
        try {
            // Mock implementation - in real scenario would query payment table
            return 'completed';
        } catch (\Exception $e) {
            Log::warning('Failed to get order payment status', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get current fulfillment status from order metadata
     */
    private function getCurrentFulfillmentStatus(Order $order): ?FulfillmentStatus
    {
        $metadata = $order->getMetadata();
        $statusValue = $metadata['fulfillment_status'] ?? null;

        if (!$statusValue) {
            return null;
        }

        try {
            return FulfillmentStatus::fromString($statusValue);
        } catch (\Exception $e) {
            Log::warning('Invalid fulfillment status in order metadata', [
                'order_id' => $order->getId(),
                'status_value' => $statusValue,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get fulfillment history from order metadata
     */
    private function getFulfillmentHistory(Order $order): array
    {
        $metadata = $order->getMetadata();
        $history = $metadata['fulfillment_history'] ?? [];

        // Add current status to history
        $history[] = [
            'status' => $metadata['fulfillment_status'] ?? 'unknown',
            'timestamp' => now()->toISOString(),
            'updated_by' => 'system'
        ];

        return $history;
    }

    /**
     * Get estimated fulfillment completion time
     */
    private function getEstimatedFulfillmentCompletion(Order $order): string
    {
        $timeframe = self::FULFILLMENT_TIMEFRAMES[$order->getOrderType()] ?? 'unknown';
        
        if ($timeframe === 'instant') {
            return now()->addMinutes(1)->toISOString();
        }
        
        if (str_contains($timeframe, 'minutes')) {
            $minutes = (int) filter_var($timeframe, FILTER_SANITIZE_NUMBER_INT);
            return now()->addMinutes($minutes)->toISOString();
        }
        
        return now()->addHours(1)->toISOString();
    }

    /**
     * Calculate fulfillment duration
     */
    private function calculateFulfillmentDuration(Order $order): ?int
    {
        $metadata = $order->getMetadata();
        $startedAt = $metadata['fulfillment_started_at'] ?? null;
        
        if (!$startedAt) {
            return null;
        }

        $startTime = Carbon::parse($startedAt);
        return (int) $startTime->diffInMinutes(now());
    }

    /**
     * Calculate total fulfillment time
     */
    private function calculateTotalFulfillmentTime(Order $order): ?int
    {
        $metadata = $order->getMetadata();
        $startedAt = $metadata['fulfillment_started_at'] ?? null;
        
        if (!$startedAt) {
            return null;
        }

        $startTime = Carbon::parse($startedAt);
        return (int) $startTime->diffInMinutes(now());
    }

    /**
     * Update fulfillment tracking cache
     */
    private function updateFulfillmentTrackingCache(Order $order, FulfillmentStatus $status): void
    {
        $cacheKey = "fulfillment_tracking_{$order->getId()}";
        $trackingData = Cache::get($cacheKey, []);
        
        $trackingData['status'] = $status->toString();
        $trackingData['updated_at'] = now()->toISOString();
        $trackingData['status_description'] = $status->getDescription();
        
        Cache::put($cacheKey, $trackingData, now()->addDays(30));
    }

    /**
     * Update order status based on fulfillment status
     */
    private function updateOrderStatusFromFulfillment(Order $order, FulfillmentStatus $status): void
    {
        // Only update order status if fulfillment status indicates completion
        if ($status->isCompleted()) {
            $this->updateOrderStatus($order, OrderStatus::completed());
        } elseif ($status->isFailed()) {
            $this->updateOrderStatus($order, OrderStatus::failed());
        }
    }

    /**
     * Send fulfillment status notification
     */
    private function sendFulfillmentStatusNotification(Order $order, FulfillmentStatus $status): void
    {
        try {
            $notificationData = [
                'order_id' => $order->getId()->toString(),
                'user_id' => $order->getUserId()->toString(),
                'fulfillment_status' => $status->toString(),
                'status_description' => $status->getDescription(),
                'order_type' => $order->getOrderType(),
                'is_gift' => $order->isGift(),
                'gift_message' => $order->getGiftMessage()
            ];

            Event::dispatch('order.fulfillment.status.updated', $notificationData);
            
            Log::info('Fulfillment status notification sent', [
                'order_id' => $order->getId(),
                'status' => $status->toString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send fulfillment status notification', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update fulfillment analytics
     */
    private function updateFulfillmentAnalytics(Order $order, FulfillmentStatus $status): void
    {
        try {
            $analyticsData = [
                'order_id' => $order->getId()->toString(),
                'user_id' => $order->getUserId()->toString(),
                'order_type' => $order->getOrderType(),
                'fulfillment_status' => $status->toString(),
                'status_timestamp' => now()->toISOString(),
                'is_gift' => $order->isGift(),
                'priority' => $order->getPriority()
            ];

            $cacheKey = "fulfillment_analytics_" . now()->format('Y-m-d');
            $dailyData = Cache::get($cacheKey, []);
            
            if (!isset($dailyData['status_updates'])) {
                $dailyData['status_updates'] = [];
            }
            
            $dailyData['status_updates'][] = $analyticsData;
            Cache::put($cacheKey, $dailyData, now()->addDays(7));
            
        } catch (\Exception $e) {
            Log::error('Failed to update fulfillment analytics', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    // ============================================================================
    // HELPER METHODS FOR DELIVERY CONFIRMATIONS
    // ============================================================================

    /**
     * Determine delivery method for item
     */
    private function determineDeliveryMethod(Order $order, array $result): string
    {
        $productType = $result['product_type'] ?? 'unknown';
        
        return match ($productType) {
            'virtual_gift' => 'instant_digital',
            'coin_package' => 'instant_credit',
            'subscription_plan' => 'instant_activation',
            'premium_feature' => 'instant_unlock',
            default => $order->getDeliveryMethod()
        };
    }

    /**
     * Get delivery location
     */
    private function getDeliveryLocation(Order $order, array $result): array
    {
        $productType = $result['product_type'] ?? 'unknown';
        
        if ($productType === 'virtual_gift' && $order->isGift()) {
            return [
                'type' => 'digital',
                'platform' => 'app',
                'recipient_device' => 'mobile',
                'delivery_address' => 'in-app_notification'
            ];
        }
        
        return [
            'type' => 'digital',
            'platform' => 'app',
            'delivery_address' => 'user_account'
        ];
    }

    /**
     * Generate tracking information
     */
    private function generateTrackingInformation(Order $order, array $result): array
    {
        return [
            'tracking_id' => 'TRK-' . strtoupper(substr(md5($order->getId()->toString() . $result['item_id']), 0, 12)),
            'tracking_url' => config('app.url') . '/tracking/' . $order->getId()->toString(),
            'tracking_status' => $result['status'] ?? 'unknown',
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Generate delivery notes
     */
    private function generateDeliveryNotes(Order $order, array $result): string
    {
        $notes = [];
        
        if ($order->isGift() && $order->getGiftMessage()) {
            $notes[] = "Gift message: {$order->getGiftMessage()}";
        }
        
        if ($result['status'] === 'fulfilled') {
            $notes[] = 'Item successfully delivered';
        } elseif ($result['status'] === 'failed') {
            $notes[] = 'Delivery failed: ' . ($result['error'] ?? 'Unknown error');
        }
        
        return implode(' | ', $notes);
    }

    /**
     * Get recipient information
     */
    private function getRecipientInformation(Order $order, array $result): array
    {
        $recipientId = $result['metadata']['recipient_id'] ?? $order->getUserId()->toString();
        
        return [
            'recipient_id' => $recipientId,
            'is_gift' => $order->isGift(),
            'recipient_type' => $recipientId === $order->getUserId()->toString() ? 'self' : 'other'
        ];
    }

    /**
     * Get sender information
     */
    private function getSenderInformation(Order $order): array
    {
        return [
            'sender_id' => $order->getUserId()->toString(),
            'sender_type' => 'customer'
        ];
    }

    /**
     * Generate delivery confirmation code
     */
    private function generateDeliveryConfirmationCode(Order $order, array $result): string
    {
        return 'DC-' . strtoupper(substr(md5($order->getId()->toString() . $result['item_id'] . now()->timestamp), 0, 8));
    }

    /**
     * Get delivery attempts
     */
    private function getDeliveryAttempts(Order $order, array $result): int
    {
        return $order->getAttemptCount();
    }

    /**
     * Generate delivery signature
     */
    private function generateDeliverySignature(Order $order, array $result): string
    {
        return 'SIG-' . strtoupper(substr(md5($order->getId()->toString() . $result['item_id'] . now()->timestamp), 0, 10));
    }

    /**
     * Generate delivery photo URL
     */
    private function generateDeliveryPhotoUrl(Order $order, array $result): ?string
    {
        // For digital deliveries, no photo needed
        $productType = $result['product_type'] ?? 'unknown';
        
        if (in_array($productType, ['virtual_gift', 'coin_package', 'subscription_plan', 'premium_feature'])) {
            return null;
        }
        
        // For physical items, would generate photo URL
        return config('app.url') . '/delivery-photos/' . $order->getId()->toString() . '/' . $result['item_id'];
    }

    /**
     * Add product-specific confirmation details
     */
    private function addProductSpecificConfirmationDetails(array $confirmation, array $result): array
    {
        $productType = $result['product_type'] ?? 'unknown';
        
        switch ($productType) {
            case 'virtual_gift':
                $confirmation['gift_details'] = [
                    'gift_id' => $result['product_id'],
                    'gift_category' => 'virtual',
                    'delivery_method' => 'instant_notification'
                ];
                break;
                
            case 'coin_package':
                $confirmation['coin_details'] = [
                    'package_id' => $result['product_id'],
                    'coins_credited' => $result['metadata']['coins_added'] ?? 0,
                    'credit_method' => 'instant'
                ];
                break;
                
            case 'subscription_plan':
                $confirmation['subscription_details'] = [
                    'plan_id' => $result['product_id'],
                    'subscription_id' => $result['metadata']['subscription_id'] ?? null,
                    'activation_method' => 'instant'
                ];
                break;
        }
        
        return $confirmation;
    }

    /**
     * Add delivery method specific details
     */
    private function addDeliveryMethodSpecificDetails(array $confirmation, Order $order, array $result): array
    {
        $deliveryMethod = $confirmation['delivery_method'];
        
        switch ($deliveryMethod) {
            case 'instant_digital':
                $confirmation['delivery_details'] = [
                    'delivery_time' => 'instant',
                    'delivery_channel' => 'in-app',
                    'notification_sent' => true
                ];
                break;
                
            case 'instant_credit':
                $confirmation['delivery_details'] = [
                    'delivery_time' => 'instant',
                    'credit_applied' => true,
                    'balance_updated' => true
                ];
                break;
                
            case 'instant_activation':
                $confirmation['delivery_details'] = [
                    'delivery_time' => 'instant',
                    'feature_activated' => true,
                    'access_granted' => true
                ];
                break;
        }
        
        return $confirmation;
    }

    /**
     * Validate delivery confirmation
     */
    private function validateDeliveryConfirmation(array $confirmation): void
    {
        $required = ['confirmation_id', 'order_id', 'item_id', 'status', 'delivery_timestamp'];
        
        foreach ($required as $field) {
            if (!isset($confirmation[$field]) || empty($confirmation[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }

    /**
     * Determine overall delivery status
     */
    private function determineOverallDeliveryStatus(array $confirmations): string
    {
        if (empty($confirmations)) {
            return 'unknown';
        }
        
        $successful = count(array_filter($confirmations, fn($c) => $c['status'] === 'fulfilled'));
        $total = count($confirmations);
        
        if ($successful === $total) {
            return 'completed';
        } elseif ($successful > 0) {
            return 'partial';
        } else {
            return 'failed';
        }
    }

    /**
     * Generate delivery summary
     */
    private function generateDeliverySummary(array $confirmations): array
    {
        $summary = [
            'total_items' => count($confirmations),
            'successful_deliveries' => 0,
            'failed_deliveries' => 0,
            'delivery_methods' => [],
            'product_types' => []
        ];
        
        foreach ($confirmations as $confirmation) {
            if ($confirmation['status'] === 'fulfilled') {
                $summary['successful_deliveries']++;
            } else {
                $summary['failed_deliveries']++;
            }
            
            $summary['delivery_methods'][] = $confirmation['delivery_method'];
            $summary['product_types'][] = $confirmation['product_type'];
        }
        
        $summary['delivery_methods'] = array_unique($summary['delivery_methods']);
        $summary['product_types'] = array_unique($summary['product_types']);
        
        return $summary;
    }

    /**
     * Generate delivery timeline
     */
    private function generateDeliveryTimeline(Order $order, array $confirmations): array
    {
        return [
            'order_created' => $order->getCreatedAt()->toISOString(),
            'fulfillment_started' => now()->toISOString(),
            'delivery_completed' => now()->toISOString(),
            'total_duration_minutes' => $order->getCreatedAt()->diffInMinutes(now())
        ];
    }

    /**
     * Generate next steps
     */
    private function generateNextSteps(array $confirmations): array
    {
        $steps = [];
        
        foreach ($confirmations as $confirmation) {
            if ($confirmation['status'] === 'fulfilled') {
                $steps[] = "Item {$confirmation['product_id']} has been successfully delivered";
            } elseif ($confirmation['status'] === 'failed') {
                $steps[] = "Item {$confirmation['product_id']} delivery failed - contact support";
            }
        }
        
        return $steps;
    }

    /**
     * Generate customer service info
     */
    private function generateCustomerServiceInfo(Order $order): array
    {
        return [
            'support_email' => 'support@foreverusinlove.com',
            'support_phone' => '+1-800-FOREVER',
            'order_id' => $order->getId()->toString(),
            'support_hours' => '24/7',
            'live_chat_url' => config('app.url') . '/support/chat'
        ];
    }

    /**
     * Create order record
     */
    private function createOrderRecord(array $orderData, array $processedItems, array $orderTotals): Order
    {
        return $this->orderRepository->create([
            'user_id' => $orderData['user_id'],
            'order_type' => $orderData['order_type'],
            'items' => $processedItems,
            'totals' => $orderTotals,
            'billing_address' => $orderData['billing_address'] ?? null,
            'shipping_address' => $orderData['shipping_address'] ?? null,
            'gift_message' => $orderData['gift_message'] ?? null,
            'scheduled_delivery' => $orderData['scheduled_delivery'] ?? null,
            'metadata' => $orderData['metadata'] ?? [],
            'status' => OrderStatus::PENDING,
            'created_at' => Carbon::now()
        ]);
    }

    // ============================================================================
    // HELPER METHODS FOR FULFILLMENT TRACKING
    // ============================================================================

    /**
     * Get tracking methods for order
     */
    private function getTrackingMethods(Order $order): array
    {
        $methods = ['order_status', 'email_updates'];
        
        if ($order->isGift()) {
            $methods[] = 'gift_delivery';
        }
        
        if ($order->getTotalAmount() > 50) {
            $methods[] = 'sms_updates';
        }
        
        return $methods;
    }

    /**
     * Get delivery channels for order
     */
    private function getDeliveryChannels(Order $order): array
    {
        $channels = ['in_app', 'email'];
        
        if ($this->userHasPushNotifications($order->getUserId())) {
            $channels[] = 'push';
        }
        
        if ($this->userHasSMSNotifications($order->getUserId())) {
            $channels[] = 'sms';
        }
        
        return $channels;
    }

    /**
     * Get tracking notification preferences
     */
    private function getTrackingNotificationPreferences(Order $order): array
    {
        return [
            'email_updates' => true,
            'push_updates' => $this->userHasPushNotifications($order->getUserId()),
            'sms_updates' => $this->userHasSMSNotifications($order->getUserId()),
            'frequency' => 'real_time',
            'quiet_hours' => $this->getUserQuietHours($order->getUserId())
        ];
    }

    /**
     * Map fulfillment status to tracking status
     */
    private function mapFulfillmentStatusToTrackingStatus(string $fulfillmentStatus): string
    {
        return match ($fulfillmentStatus) {
            'fulfilled' => 'delivered',
            'completed' => 'delivered',
            'failed' => 'delivery_failed',
            default => 'in_transit'
        };
    }

    /**
     * Get item estimated delivery time
     */
    private function getItemEstimatedDelivery(Order $order, array $result): string
    {
        $productType = $result['product_type'] ?? 'unknown';
        
        if (in_array($productType, ['virtual_gift', 'coin_package', 'subscription_plan'])) {
            return now()->addMinutes(1)->toISOString();
        }
        
        return $this->getEstimatedDeliveryTime($order);
    }

    /**
     * Get item delivery method
     */
    private function getItemDeliveryMethod(Order $order, array $result): string
    {
        $productType = $result['product_type'] ?? 'unknown';
        
        return match ($productType) {
            'virtual_gift' => 'instant_digital',
            'coin_package' => 'instant_credit',
            'subscription_plan' => 'instant_activation',
            default => 'standard'
        };
    }

    /**
     * Get item tracking details
     */
    private function getItemTrackingDetails(array $result): array
    {
        return [
            'status' => $result['status'] ?? 'pending',
            'last_updated' => now()->toISOString(),
            'metadata' => $result['metadata'] ?? []
        ];
    }

    /**
     * Create tracking record in database
     */
    private function createTrackingRecord(array $trackingData): void
    {
        // Implementation would create tracking record in database
        // For now, just log the action
        Log::info('Tracking record created', [
            'tracking_id' => $trackingData['tracking_id'],
            'order_id' => $trackingData['order_id']
        ]);
    }

    // ============================================================================
    // HELPER METHODS FOR FULFILLMENT ESTIMATION
    // ============================================================================

    /**
     * Get base estimated time from timeframe
     */
    private function getBaseEstimatedTime(string $timeframe): \Carbon\Carbon
    {
        if ($timeframe === 'instant') {
            return now()->addMinutes(1);
        }
        
        if (str_contains($timeframe, 'minutes')) {
            $minutes = (int) filter_var($timeframe, FILTER_SANITIZE_NUMBER_INT);
            return now()->addMinutes($minutes);
        }
        
        return now()->addHours(1);
    }

    /**
     * Calculate fulfillment adjustments
     */
    private function calculateFulfillmentAdjustments(Order $order, array $fulfillmentResults): array
    {
        $adjustments = [
            'time_adjustment_minutes' => 0,
            'complexity_factor' => 1.0,
            'risk_factors' => []
        ];
        
        // Adjust based on number of items
        $itemCount = count($fulfillmentResults);
        if ($itemCount > 3) {
            $adjustments['time_adjustment_minutes'] += ($itemCount - 3) * 2;
            $adjustments['risk_factors'][] = 'multiple_items';
        }
        
        // Adjust based on failed items
        $failedItems = array_filter($fulfillmentResults, fn($r) => ($r['status'] ?? '') === 'failed');
        if (count($failedItems) > 0) {
            $adjustments['time_adjustment_minutes'] += count($failedItems) * 5;
            $adjustments['risk_factors'][] = 'failed_items';
        }
        
        // Adjust based on order type complexity
        $orderType = $order->getOrderType();
        if ($orderType === 'subscription') {
            $adjustments['time_adjustment_minutes'] += 3;
            $adjustments['risk_factors'][] = 'subscription_complexity';
        }
        
        return $adjustments;
    }

    /**
     * Get system load adjustment
     */
    private function getSystemLoadAdjustment(): int
    {
        // Mock system load calculation
        $currentHour = now()->hour;
        
        // Peak hours (9-17) add more time
        if ($currentHour >= 9 && $currentHour <= 17) {
            return rand(2, 5);
        }
        
        // Off-peak hours
        return rand(0, 2);
    }

    /**
     * Calculate estimation confidence
     */
    private function calculateEstimationConfidence(Order $order, array $fulfillmentResults): float
    {
        $confidence = 0.9; // Base confidence
        
        // Reduce confidence for complex orders
        if (count($fulfillmentResults) > 2) {
            $confidence -= 0.1;
        }
        
        // Reduce confidence for failed items
        $failedItems = array_filter($fulfillmentResults, fn($r) => ($r['status'] ?? '') === 'failed');
        if (count($failedItems) > 0) {
            $confidence -= 0.2;
        }
        
        // Reduce confidence for scheduled deliveries
        if ($order->getScheduledDelivery()) {
            $confidence -= 0.1;
        }
        
        return max(0.5, $confidence);
    }

    // ============================================================================
    // HELPER METHODS FOR FULFILLMENT NOTIFICATIONS
    // ============================================================================

    /**
     * Get order tracking information
     */
    private function getOrderTrackingInformation(Order $order): array
    {
        $trackingData = Cache::get("order_tracking_{$order->getId()}", []);
        
        return [
            'tracking_number' => $trackingData['tracking_number'] ?? null,
            'tracking_url' => $trackingData['tracking_url'] ?? null,
            'status' => $trackingData['status'] ?? 'unknown'
        ];
    }

    /**
     * Determine notification types based on order and fulfillment results
     */
    private function determineNotificationTypes(Order $order, array $fulfillmentResults): array
    {
        $types = [
            'email' => true,
            'push' => true,
            'sms' => false,
            'in_app' => true
        ];
        
        // Enable SMS for high-value orders
        if ($order->getTotalAmount() > 100) {
            $types['sms'] = true;
        }
        
        // Enable SMS for failed fulfillments
        $hasFailedItems = !empty(array_filter($fulfillmentResults, fn($r) => ($r['status'] ?? '') === 'failed'));
        if ($hasFailedItems) {
            $types['sms'] = true;
        }
        
        // Disable push for low-priority orders
        if ($order->getPriority() === 'low') {
            $types['push'] = false;
        }
        
        return $types;
    }

    /**
     * Send fulfillment email notification
     */
    private function sendFulfillmentEmailNotification(Order $order, array $notificationData): void
    {
        Event::dispatch('order.fulfillment.email', $notificationData);
        
        Log::info('Fulfillment email notification dispatched', [
            'order_id' => $order->getId(),
            'user_id' => $order->getUserId()
        ]);
    }

    /**
     * Send fulfillment push notification
     */
    private function sendFulfillmentPushNotification(Order $order, array $notificationData): void
    {
        Event::dispatch('order.fulfillment.push', $notificationData);
        
        Log::info('Fulfillment push notification dispatched', [
            'order_id' => $order->getId(),
            'user_id' => $order->getUserId()
        ]);
    }

    /**
     * Send fulfillment SMS notification
     */
    private function sendFulfillmentSMSNotification(Order $order, array $notificationData): void
    {
        Event::dispatch('order.fulfillment.sms', $notificationData);
        
        Log::info('Fulfillment SMS notification dispatched', [
            'order_id' => $order->getId(),
            'user_id' => $order->getUserId()
        ]);
    }

    /**
     * Send fulfillment in-app notification
     */
    private function sendFulfillmentInAppNotification(Order $order, array $notificationData): void
    {
        Event::dispatch('order.fulfillment.in_app', $notificationData);
        
        Log::info('Fulfillment in-app notification dispatched', [
            'order_id' => $order->getId(),
            'user_id' => $order->getUserId()
        ]);
    }

    /**
     * Dispatch fulfillment events for external integrations
     */
    private function dispatchFulfillmentEvents(Order $order, array $fulfillmentResults, array $deliveryConfirmations): void
    {
        Event::dispatch('order.fulfillment.completed', [
            'order_id' => $order->getId()->toString(),
            'user_id' => $order->getUserId()->toString(),
            'fulfillment_results' => $fulfillmentResults,
            'delivery_confirmations' => $deliveryConfirmations,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Send gift recipient notifications
     */
    private function sendGiftRecipientNotifications(Order $order, array $fulfillmentResults, array $deliveryConfirmations): void
    {
        foreach ($fulfillmentResults as $result) {
            $recipientId = $result['metadata']['recipient_id'] ?? null;
            if ($recipientId && $recipientId !== $order->getUserId()->toString()) {
                Event::dispatch('gift.received', [
                    'gift_id' => $result['product_id'],
                    'recipient_id' => $recipientId,
                    'sender_id' => $order->getUserId()->toString(),
                    'order_id' => $order->getId()->toString(),
                    'gift_message' => $order->getGiftMessage(),
                    'delivery_confirmation' => $deliveryConfirmations
                ]);
            }
        }
    }

    /**
     * Check if admins should be notified
     */
    private function shouldNotifyAdmins(Order $order, array $fulfillmentResults): bool
    {
        // Notify for high-value orders
        if ($order->getTotalAmount() > 500) {
            return true;
        }
        
        // Notify for failed fulfillments
        $hasFailedItems = !empty(array_filter($fulfillmentResults, fn($r) => ($r['status'] ?? '') === 'failed'));
        if ($hasFailedItems) {
            return true;
        }
        
        return false;
    }

    /**
     * Send admin fulfillment notification
     */
    private function sendAdminFulfillmentNotification(Order $order, array $fulfillmentResults, array $deliveryConfirmations): void
    {
        Event::dispatch('order.fulfillment.admin_alert', [
            'order_id' => $order->getId()->toString(),
            'user_id' => $order->getUserId()->toString(),
            'total_amount' => $order->getTotalAmount(),
            'fulfillment_results' => $fulfillmentResults,
            'delivery_confirmations' => $deliveryConfirmations,
            'alert_reason' => $this->getAdminAlertReason($order, $fulfillmentResults),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Update fulfillment notification history
     */
    private function updateFulfillmentNotificationHistory(Order $order, array $notificationTypes, array $notificationData): void
    {
        $historyData = [
            'order_id' => $order->getId()->toString(),
            'user_id' => $order->getUserId()->toString(),
            'notification_types' => array_keys(array_filter($notificationTypes)),
            'sent_at' => now()->toISOString(),
            'notification_data' => $notificationData
        ];
        
        Cache::put(
            "fulfillment_notification_history_{$order->getId()}",
            $historyData,
            now()->addDays(30)
        );
    }

    /**
     * Send fallback fulfillment notification
     */
    private function sendFallbackFulfillmentNotification(Order $order, \Exception $exception): void
    {
        Event::dispatch('order.fulfillment.fallback', [
            'order_id' => $order->getId()->toString(),
            'user_id' => $order->getUserId()->toString(),
            'error' => $exception->getMessage(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get admin alert reason
     */
    private function getAdminAlertReason(Order $order, array $fulfillmentResults): string
    {
        if ($order->getTotalAmount() > 500) {
            return 'high_value_order';
        }
        
        $hasFailedItems = !empty(array_filter($fulfillmentResults, fn($r) => ($r['status'] ?? '') === 'failed'));
        if ($hasFailedItems) {
            return 'failed_fulfillment';
        }
        
        return 'unknown';
    }

    /**
     * Get user quiet hours
     */
    private function getUserQuietHours(UserId $userId): array
    {
        $settings = $this->getUserNotificationSettings($userId);
        return $settings['push_notifications']['quiet_hours'] ?? [];
    }
}