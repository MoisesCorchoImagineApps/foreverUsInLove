<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Repositories;

use App\Domain\Commerce\Entities\Order;
use App\Domain\Commerce\Entities\Payment;
use App\Domain\Commerce\Entities\PaymentMethod;
use App\Domain\Commerce\Entities\Subscription;
use App\Domain\Commerce\Entities\CoinTransaction;
use App\Domain\Commerce\Entities\GiftTransaction;
use App\Domain\Commerce\ValueObjects\OrderId;
use App\Domain\Commerce\ValueObjects\PaymentId;
use App\Domain\Commerce\ValueObjects\SubscriptionId;
use App\Domain\Commerce\ValueObjects\OrderStatus;
use App\Domain\Commerce\ValueObjects\PaymentStatus;
use App\Domain\Commerce\ValueObjects\TransactionStatus;
use App\Domain\Shared\ValueObjects\UserId;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

/**
 * OrderRepositoryInterface - Repository contracts for order operations
 * 
 * Comprehensive repository interface for order and transaction management in the
 * ForeverUsInLove dating application. Handles orders, payments, subscriptions,
 * coin transactions, gift transactions, and comprehensive financial operations
 * with full CRUD capabilities, advanced analytics, and performance optimization.
 * 
 * Features:
 * - Complete order lifecycle management with status tracking
 * - Payment processing and method management with security
 * - Subscription billing and lifecycle automation
 * - Virtual currency transaction processing and auditing
 * - Gift transaction management with delivery tracking
 * - Advanced search and filtering across all transaction types
 * - Financial analytics and reporting capabilities
 * - Fraud detection and compliance monitoring
 * - Performance optimization with caching and indexing
 * - Bulk operations for administrative efficiency
 * - Data export and compliance reporting
 * - Integration with external financial systems
 * - Real-time transaction monitoring and alerts
 * - Revenue tracking and business intelligence
 * 
 * Architecture:
 * - Repository Pattern implementation
 * - Domain-Driven Design (DDD) principles
 * - Clean Architecture / Hexagonal Architecture
 * - SOLID principles with Dependency Inversion
 * - Laravel 12 with PHP 8.2 strict typing
 * - Double-entry bookkeeping for financial accuracy
 * - Comprehensive audit trails and logging
 * 
 * @package App\Domain\Commerce\Repositories
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 * 
 * @see \App\Domain\Commerce\Entities\Order
 * @see \App\Domain\Commerce\Entities\Payment
 * @see \App\Domain\Commerce\Entities\Subscription
 * @see \App\Infrastructure\Persistence\Eloquent\OrderEloquentRepository
 */
interface OrderRepositoryInterface
{
    // ============================================================================
    // ORDER MANAGEMENT OPERATIONS
    // ============================================================================

    /**
     * Create a new order with comprehensive configuration
     * 
     * @param array{
     *     user_id: UserId|string,
     *     order_type: string,
     *     items: array,
     *     totals: array,
     *     billing_address?: array,
     *     shipping_address?: array,
     *     gift_message?: string,
     *     scheduled_delivery?: Carbon|string,
     *     metadata?: array,
     *     status: OrderStatus|string,
     *     created_at?: Carbon|string
     * } $orderData Comprehensive order creation data
     * 
     * @return Order Newly created order entity
     */
    public function create(array $orderData): Order;

    /**
     * Find order by ID with optional eager loading
     * 
     * @param OrderId|string $orderId Unique order identifier
     * @param array<string> $with Optional relations to eager load
     * 
     * @return Order|null Order entity if found, null otherwise
     */
    public function findById(OrderId|string $orderId, array $with = []): ?Order;

    /**
     * Update order with new data
     * 
     * @param OrderId|string $orderId Order to update
     * @param array $updateData Data to update
     * 
     * @return Order Updated order entity
     */
    public function update(OrderId|string $orderId, array $updateData): Order;

    /**
     * Get user's orders with comprehensive filtering
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     order_types?: array<string>,
     *     statuses?: array<OrderStatus|string>,
     *     date_from?: Carbon|string,
     *     date_to?: Carbon|string,
     *     include_items?: bool,
     *     include_payments?: bool,
     *     sort_by?: string,
     *     sort_direction?: string,
     *     per_page?: int,
     *     page?: int
     * } $filters Filtering and pagination options
     * 
     * @return LengthAwarePaginator Paginated order results
     */
    public function getUserOrders(UserId|string $userId, array $filters = []): LengthAwarePaginator;

    /**
     * Get orders by status with filtering
     * 
     * @param OrderStatus|string $status Target order status
     * @param array{
     *     order_types?: array<string>,
     *     date_range?: array{start: Carbon|string, end: Carbon|string},
     *     user_segments?: array<string>,
     *     priority?: string,
     *     per_page?: int
     * } $filters Additional filtering options
     * 
     * @return LengthAwarePaginator Orders matching status and filters
     */
    public function getOrdersByStatus(OrderStatus|string $status, array $filters = []): LengthAwarePaginator;

    // ============================================================================
    // PAYMENT MANAGEMENT OPERATIONS
    // ============================================================================

    /**
     * Create a new payment record
     * 
     * @param array{
     *     amount: float,
     *     currency: string,
     *     user_id: UserId|string,
     *     gateway: string,
     *     payment_method: PaymentMethod,
     *     tax_data?: array,
     *     metadata?: array,
     *     status: PaymentStatus|string
     * } $paymentData Payment creation data
     * 
     * @return Payment Created payment entity
     */
    public function createPayment(array $paymentData): Payment;

    /**
     * Update payment with gateway response
     * 
     * @param Payment $payment Payment to update
     * 
     * @return Payment Updated payment entity
     */
    public function updatePayment(Payment $payment): Payment;

    /**
     * Get user's payment history
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     status?: array<PaymentStatus|string>,
     *     gateways?: array<string>,
     *     date_range?: array{start: Carbon|string, end: Carbon|string},
     *     include_failed?: bool,
     *     per_page?: int
     * } $filters Payment filtering options
     * 
     * @return LengthAwarePaginator User's payment history
     */
    public function getUserPayments(UserId|string $userId, array $filters = []): LengthAwarePaginator;

    /**
     * Get payments since specific date for fraud detection
     * 
     * @param UserId|string $userId Target user identifier
     * @param Carbon $since Date threshold
     * 
     * @return array Recent payments for analysis
     */
    public function getUserPaymentsSince(UserId|string $userId, Carbon $since): array;

    /**
     * Record failed payment attempt
     * 
     * @param array{
     *     user_id: UserId|string,
     *     amount: float,
     *     currency: string,
     *     error_message: string,
     *     failed_at: Carbon|string
     * } $failureData Failed payment details
     * 
     * @return bool True if successfully recorded
     */
    public function recordFailedPayment(array $failureData): bool;

    // ============================================================================
    // PAYMENT METHOD MANAGEMENT
    // ============================================================================

    /**
     * Get user's stored payment methods
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     include_expired?: bool,
     *     types?: array<string>,
     *     gateways?: array<string>,
     *     include_verification_status?: bool
     * } $options Retrieval options
     * 
     * @return Collection User's payment methods
     */
    public function getUserPaymentMethods(UserId|string $userId, array $options = []): Collection;

    /**
     * Store new payment method for user
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     type: string,
     *     gateway_token: string,
     *     last_four?: string,
     *     expiry_date?: Carbon|string,
     *     is_default?: bool,
     *     metadata?: array
     * } $methodData Payment method details
     * 
     * @return PaymentMethod Stored payment method
     */
    public function storePaymentMethod(UserId|string $userId, array $methodData): PaymentMethod;

    // ============================================================================
    // SUBSCRIPTION MANAGEMENT
    // ============================================================================

    /**
     * Create a new subscription
     * 
     * @param array{
     *     user_id: UserId|string,
     *     plan_id: string,
     *     billing_cycle: string,
     *     pricing_details: array,
     *     auto_renew: bool,
     *     metadata?: array,
     *     status: string
     * } $subscriptionData Subscription creation data
     * 
     * @return Subscription Created subscription entity
     */
    public function createSubscription(array $subscriptionData): Subscription;

    /**
     * Get user's active subscription
     * 
     * @param UserId|string $userId Target user identifier
     * 
     * @return Subscription|null Active subscription if found
     */
    public function getUserActiveSubscription(UserId|string $userId): ?Subscription;

    /**
     * Get user's subscription history
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     include_cancelled?: bool,
     *     include_expired?: bool,
     *     per_page?: int
     * } $options History retrieval options
     * 
     * @return LengthAwarePaginator Subscription history
     */
    public function getUserSubscriptionHistory(UserId|string $userId, array $options = []): LengthAwarePaginator;

    /**
     * Update subscription status and details
     * 
     * @param SubscriptionId|string $subscriptionId Subscription to update
     * @param array $updateData Update data
     * 
     * @return Subscription Updated subscription
     */
    public function updateSubscription(SubscriptionId|string $subscriptionId, array $updateData): Subscription;

    // ============================================================================
    // COIN TRANSACTION MANAGEMENT
    // ============================================================================

    /**
     * Create coin transaction
     * 
     * @param array{
     *     user_id: UserId|string,
     *     amount: int,
     *     type: string,
     *     source: string,
     *     reference_id?: string,
     *     description?: string,
     *     metadata?: array,
     *     status: TransactionStatus|string,
     *     created_at: Carbon|string
     * } $transactionData Coin transaction data
     * 
     * @return CoinTransaction Created transaction
     */
    public function createCoinTransaction(array $transactionData): CoinTransaction;

    /**
     * Get user's coin transactions
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     types?: array<string>,
     *     sources?: array<string>,
     *     date_range?: array{start: Carbon|string, end: Carbon|string},
     *     per_page?: int
     * } $filters Transaction filtering options
     * 
     * @return LengthAwarePaginator User's coin transaction history
     */
    public function getUserCoinTransactions(UserId|string $userId, array $filters = []): LengthAwarePaginator;

    /**
     * Increment user coin balance
     * 
     * @param UserId|string $userId Target user
     * @param int $amount Amount to increment
     * 
     * @return bool True if successful
     */
    public function incrementUserCoinBalance(UserId|string $userId, int $amount): bool;

    /**
     * Decrement user coin balance
     * 
     * @param UserId|string $userId Target user
     * @param int $amount Amount to decrement
     * 
     * @return bool True if successful
     */
    public function decrementUserCoinBalance(UserId|string $userId, int $amount): bool;

    // ============================================================================
    // GIFT TRANSACTION MANAGEMENT
    // ============================================================================

    /**
     * Create gift transaction
     * 
     * @param array{
     *     gift_id: string,
     *     sender_id: UserId|string,
     *     recipient_id: UserId|string,
     *     payment_data: array,
     *     message?: string,
     *     metadata?: array,
     *     status: TransactionStatus|string
     * } $transactionData Gift transaction data
     * 
     * @return GiftTransaction Created gift transaction
     */
    public function createGiftTransaction(array $transactionData): GiftTransaction;

    /**
     * Get user's gift transactions (sent and received)
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     type?: string, // 'sent', 'received', or 'all'
     *     date_from?: Carbon|string,
     *     date_to?: Carbon|string,
     *     partner_id?: UserId|string,
     *     gift_categories?: array<string>,
     *     sort_by?: string,
     *     per_page?: int
     * } $filters Gift transaction filtering
     * 
     * @return LengthAwarePaginator User's gift transaction history
     */
    public function getUserGiftTransactions(UserId|string $userId, array $filters = []): LengthAwarePaginator;

    // ============================================================================
    // ANALYTICS AND REPORTING
    // ============================================================================

    /**
     * Get order analytics for specified period
     * 
     * @param array{
     *     start_date?: Carbon|string,
     *     end_date?: Carbon|string,
     *     order_types?: array<string>,
     *     user_segments?: array<string>
     * } $options Analytics configuration
     * 
     * @return array{
     *     total_orders: int,
     *     total_revenue: float,
     *     average_order_value: float,
     *     conversion_rate: float,
     *     top_products: array
     * } Comprehensive order analytics
     */
    public function getOrderAnalytics(array $options = []): array;

    /**
     * Get payment analytics and metrics
     * 
     * @param array{
     *     start_date?: Carbon|string,
     *     end_date?: Carbon|string,
     *     gateways?: array<string>
     * } $options Payment analytics configuration
     * 
     * @return array{
     *     total_processed: float,
     *     successful_payments: int,
     *     failed_payments: int,
     *     success_rate: float
     * } Payment performance analytics
     */
    public function getPaymentAnalytics(array $options = []): array;

    /**
     * Export financial data for reporting
     * 
     * @param array{
     *     data_types?: array<string>,
     *     start_date?: Carbon|string,
     *     end_date?: Carbon|string,
     *     format?: string
     * } $exportOptions Export configuration
     * 
     * @return array{
     *     file_path: string,
     *     record_count: int,
     *     export_date: Carbon
     * } Export result information
     */
    public function exportFinancialData(array $exportOptions): array;
}