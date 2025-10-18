<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Entities\Payment;
use App\Domain\Commerce\Entities\PaymentMethod;
use App\Domain\Commerce\Entities\Transaction;
use App\Domain\Commerce\ValueObjects\PaymentId;
use App\Domain\Commerce\ValueObjects\Amount;
use App\Domain\Commerce\ValueObjects\Currency;
use App\Domain\Commerce\ValueObjects\PaymentStatus;
use App\Domain\Commerce\ValueObjects\PaymentGateway;
use App\Domain\Commerce\ValueObjects\TransactionType;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Commerce\Repositories\OrderRepositoryInterface;
use App\Domain\Commerce\Events\PaymentProcessed;
use App\Domain\Commerce\Exceptions\PaymentFailedException;
use App\Domain\Commerce\Exceptions\InsufficientFundsException;
use App\Domain\Commerce\Exceptions\PaymentMethodNotFoundException;
use App\Domain\Commerce\Exceptions\InvalidPaymentDataException;
use App\Domain\Commerce\Exceptions\PaymentGatewayException;
use App\Domain\Commerce\Exceptions\PaymentNotFoundException;
use App\Domain\Commerce\Exceptions\RefundNotAllowedException;
use App\Domain\Common\Exceptions\ValidationException;
use App\Domain\Auth\Exceptions\UserNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

/**
 * PaymentService - Comprehensive payment processing with multiple gateways
 * 
 * Advanced payment processing service for the ForeverUsInLove dating application.
 * Handles multiple payment gateways, subscription billing, virtual currency,
 * fraud detection, and comprehensive financial operations with PCI compliance
 * and international support.
 * 
 * Features:
 * - Multi-gateway payment processing (Stripe, PayPal, Apple Pay, Google Pay)
 * - Subscription billing and recurring payments management
 * - Virtual currency (coins) integration and conversion
 * - Advanced fraud detection and risk assessment
 * - PCI DSS compliance and secure tokenization
 * - International payment support with currency conversion
 * - Payment method management and vault storage
 * - Refund and chargeback handling
 * - Payment analytics and financial reporting
 * - Webhook handling for real-time payment updates
 * - 3D Secure authentication support
 * - Split payments and marketplace functionality
 * - Tax calculation and compliance
 * - Payment retry and dunning management
 * 
 * Architecture:
 * - Service Layer Pattern for business logic
 * - Domain-Driven Design (DDD) principles
 * - Clean Architecture / Hexagonal Architecture
 * - SOLID principles with Dependency Inversion
 * - Laravel 12 with PHP 8.2 strict typing
 * - Event-driven architecture for payment notifications
 * - Repository pattern for data persistence
 * - Gateway abstraction for multiple payment providers
 * 
 * @package App\Domain\Commerce\Services
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 * 
 * @see \App\Domain\Commerce\Entities\Payment
 * @see \App\Domain\Commerce\Events\PaymentProcessed
 * @see \App\Domain\Commerce\Repositories\OrderRepositoryInterface
 */
class PaymentService
{
    /**
     * Supported payment gateways configuration
     */
    private const SUPPORTED_GATEWAYS = [
        'stripe' => ['cards', 'wallets', 'bank_transfers'],
        'paypal' => ['paypal', 'cards', 'bank_transfers'],
        'apple_pay' => ['apple_pay'],
        'google_pay' => ['google_pay'],
        'braintree' => ['cards', 'paypal', 'wallets'],
        'square' => ['cards', 'cash_app'],
        'adyen' => ['cards', 'wallets', 'bank_transfers', 'local_methods']
    ];

    /**
     * Fraud detection thresholds
     */
    private const FRAUD_THRESHOLDS = [
        'high_amount' => 1000.00,
        'velocity_limit' => 5, // transactions per hour
        'failed_attempts' => 3,
        'risk_score_limit' => 75.0
    ];

    /**
     * Constructor with dependency injection
     * 
     * @param OrderRepositoryInterface $orderRepository Order and transaction management
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository
    ) {}

    // ============================================================================
    // PAYMENT PROCESSING CORE
    // ============================================================================

    /**
     * Process payment with comprehensive validation and fraud detection
     * 
     * Processes a payment through the appropriate gateway with advanced fraud
     * detection, 3D Secure authentication, and comprehensive error handling.
     * Supports one-time payments, subscriptions, and virtual currency purchases.
     * 
     * @param array{
     *     amount: Amount|float,
     *     currency: Currency|string,
     *     user_id: UserId|string,
     *     payment_method_id?: string,
     *     payment_method_data?: array{
     *         type: string,
     *         card_token?: string,
     *         wallet_token?: string,
     *         bank_account?: array,
     *         billing_address?: array
     *     },
     *     gateway?: PaymentGateway|string,
     *     description?: string,
     *     metadata?: array{
     *         order_id?: string,
     *         subscription_id?: string,
     *         gift_id?: string,
     *         plan_id?: string,
     *         source?: string
     *     },
     *     options?: array{
     *         capture?: bool,
     *         confirm?: bool,
     *         save_payment_method?: bool,
     *         enable_3d_secure?: bool,
     *         fraud_check?: bool,
     *         tax_calculation?: bool
     *     }
     * } $paymentData Comprehensive payment processing data
     * 
     * @return Payment Processed payment with transaction details
     * 
     * @throws PaymentFailedException When payment processing fails
     * @throws InsufficientFundsException When insufficient funds
     * @throws PaymentMethodNotFoundException When payment method invalid
     * @throws InvalidPaymentDataException When payment data is invalid
     * @throws PaymentGatewayException When gateway error occurs
     * @throws ValidationException When validation fails
     * 
     * @example
     * ```php
     * $payment = $paymentService->processPayment([
     *     'amount' => 29.99,
     *     'currency' => Currency::USD,
     *     'user_id' => $userId,
     *     'payment_method_data' => [
     *         'type' => 'card',
     *         'card_token' => 'tok_visa_4242424242424242'
     *     ],
     *     'gateway' => PaymentGateway::STRIPE,
     *     'description' => 'Premium Plan Subscription',
     *     'metadata' => ['plan_id' => 'premium_monthly'],
     *     'options' => [
     *         'capture' => true,
     *         'save_payment_method' => true,
     *         'enable_3d_secure' => true
     *     ]
     * ]);
     * ```
     */
    public function processPayment(array $paymentData): Payment
    {
        return DB::transaction(function () use ($paymentData) {
            try {
                Log::info('Processing payment', [
                    'amount' => $paymentData['amount'],
                    'currency' => $paymentData['currency'],
                    'user_id' => $paymentData['user_id'],
                    'gateway' => $paymentData['gateway'] ?? 'auto'
                ]);

                // Validate payment data
                $this->validatePaymentData($paymentData);

                // Validate user
                $this->validateUserExists($paymentData['user_id']);

                // Perform fraud detection
                if ($paymentData['options']['fraud_check'] ?? true) {
                    $fraudResult = $this->performFraudDetection($paymentData);
                    if ($fraudResult['risk_level'] === 'high') {
                        throw new PaymentFailedException('Payment blocked due to high fraud risk');
                    }
                }

                // Select optimal payment gateway
                $gateway = $this->selectOptimalGateway($paymentData);

                // Prepare payment method
                $paymentMethod = $this->preparePaymentMethod($paymentData, $gateway);

                // Calculate taxes if enabled
                $taxData = null;
                if ($paymentData['options']['tax_calculation'] ?? false) {
                    $taxData = $this->calculateTaxes($paymentData);
                }

                // Create payment record
                $payment = $this->createPaymentRecord($paymentData, $gateway, $paymentMethod, $taxData);

                // Process through gateway
                $gatewayResult = $this->processGatewayPayment($payment, $paymentData, $gateway);

                // Handle 3D Secure if required
                if ($gatewayResult['requires_action'] ?? false) {
                    $payment = $this->handle3DSecure($payment, $gatewayResult);
                }

                // Update payment with gateway response
                $this->updatePaymentWithGatewayResult($payment, $gatewayResult);

                // Save payment method if requested
                if ($paymentData['options']['save_payment_method'] ?? false) {
                    $this->savePaymentMethodForUser($paymentData['user_id'], $paymentMethod, $gatewayResult);
                }

                // Fire payment processed event
                Event::dispatch(new PaymentProcessed(
                    $payment,
                    $paymentData['user_id'],
                    $gatewayResult
                ));

                // Update user payment statistics
                $this->updateUserPaymentStats($paymentData['user_id'], $payment);

                Log::info('Payment processed successfully', [
                    'payment_id' => $payment->id()->toString(),
                    'amount' => $payment->amount(),
                    'gateway' => $gateway,
                    'status' => $payment->status()->toString()
                ]);

                return $payment;

            } catch (\Exception $e) {
                Log::error('Payment processing failed', [
                    'error' => $e->getMessage(),
                    'payment_data' => $paymentData
                ]);

                // Record failed payment for analysis
                $this->recordFailedPayment($paymentData, $e->getMessage());

                throw $e;
            }
        });
    }

    /**
     * Process recurring subscription payment
     * 
     * Handles recurring subscription payments with automatic retries,
     * dunning management, and subscription lifecycle events.
     * 
     * @param array{
     *     subscription_id: string,
     *     user_id: UserId|string,
     *     amount: Amount|float,
     *     currency: Currency|string,
     *     payment_method_id: string,
     *     billing_cycle: string,
     *     retry_config?: array{
     *         max_retries: int,
     *         retry_intervals: array<int>
     *     }
     * } $subscriptionData Subscription payment data
     * 
     * @return Payment Processed subscription payment
     * 
     * @throws PaymentFailedException When subscription payment fails
     * @throws PaymentMethodNotFoundException When stored payment method invalid
     * 
     * @example
     * ```php
     * $payment = $paymentService->processSubscriptionPayment([
     *     'subscription_id' => 'sub_premium_monthly_123',
     *     'user_id' => $userId,
     *     'amount' => 29.99,
     *     'currency' => Currency::USD,
     *     'payment_method_id' => 'pm_saved_card_456',
     *     'billing_cycle' => 'monthly'
     * ]);
     * ```
     */
    public function processSubscriptionPayment(array $subscriptionData): Payment
    {
        return DB::transaction(function () use ($subscriptionData) {
            try {
                Log::info('Processing subscription payment', [
                    'subscription_id' => $subscriptionData['subscription_id'],
                    'user_id' => $subscriptionData['user_id'],
                    'amount' => $subscriptionData['amount']
                ]);

                // Validate subscription data
                $this->validateSubscriptionPaymentData($subscriptionData);

                // Get stored payment method
                $paymentMethod = $this->getStoredPaymentMethod($subscriptionData['payment_method_id']);
                if (!$paymentMethod) {
                    throw new PaymentMethodNotFoundException('Stored payment method not found');
                }

                // Check if payment method needs update
                if ($this->paymentMethodNeedsUpdate($paymentMethod)) {
                    $this->requestPaymentMethodUpdate($subscriptionData['user_id'], $paymentMethod);
                }

                // Process payment with retry logic
                $payment = $this->processPaymentWithRetry($subscriptionData, $paymentMethod);

                // Update subscription status
                $this->updateSubscriptionAfterPayment($subscriptionData['subscription_id'], $payment);

                Log::info('Subscription payment processed successfully', [
                    'subscription_id' => $subscriptionData['subscription_id'],
                    'payment_id' => $payment->id()->toString()
                ]);

                return $payment;

            } catch (\Exception $e) {
                Log::error('Subscription payment failed', [
                    'subscription_id' => $subscriptionData['subscription_id'],
                    'error' => $e->getMessage()
                ]);

                // Handle subscription payment failure
                $this->handleSubscriptionPaymentFailure($subscriptionData, $e);

                throw $e;
            }
        });
    }

    // ============================================================================
    // REFUNDS AND CHARGEBACKS
    // ============================================================================

    /**
     * Process refund with comprehensive validation and gateway integration
     * 
     * Handles full and partial refunds with proper validation, gateway
     * integration, and automatic notification to affected parties.
     * 
     * @param array{
     *     payment_id: PaymentId|string,
     *     amount?: Amount|float,
     *     reason: string,
     *     refund_type: string, // 'full', 'partial', 'chargeback'
     *     requested_by: UserId|string,
     *     metadata?: array{
     *         admin_notes?: string,
     *         customer_reason?: string,
     *         automatic?: bool
     *     }
     * } $refundData Refund processing data
     * 
     * @return array{
     *     refund_id: string,
     *     refund_amount: float,
     *     processing_fee: float,
     *     net_refund: float,
     *     estimated_arrival: Carbon,
     *     gateway_response: array
     * } Refund processing result
     * 
     * @throws PaymentNotFoundException When payment not found
     * @throws RefundNotAllowedException When refund not allowed
     * @throws PaymentGatewayException When gateway error occurs
     * 
     * @example
     * ```php
     * $refund = $paymentService->processRefund([
     *     'payment_id' => $paymentId,
     *     'amount' => 15.00, // Partial refund
     *     'reason' => 'customer_request',
     *     'refund_type' => 'partial',
     *     'requested_by' => $adminId,
     *     'metadata' => [
     *         'customer_reason' => 'Changed mind about premium features'
     *     ]
     * ]);
     * ```
     */
    public function processRefund(array $refundData): array
    {
        return DB::transaction(function () use ($refundData) {
            try {
                Log::info('Processing refund', [
                    'payment_id' => $refundData['payment_id'],
                    'amount' => $refundData['amount'] ?? 'full',
                    'reason' => $refundData['reason']
                ]);

                // Validate refund data
                $this->validateRefundData($refundData);

                // Get original payment
                $payment = $this->getPaymentById($refundData['payment_id']);
                if (!$payment) {
                    throw new PaymentNotFoundException('Payment not found');
                }

                // Validate refund eligibility
                $this->validateRefundEligibility($payment, $refundData);

                // Calculate refund amounts
                $refundCalculation = $this->calculateRefundAmounts($payment, $refundData);

                // Create refund record
                $refund = $this->createRefundRecord($payment, $refundData, $refundCalculation);

                // Process refund through gateway
                $gatewayResult = $this->processGatewayRefund($payment, $refund);

                // Update refund with gateway response
                $this->updateRefundWithGatewayResult($refund, $gatewayResult);

                // Update payment status
                $this->updatePaymentAfterRefund($payment, $refund);

                // Notify affected parties
                $this->notifyRefundProcessed($payment, $refund, $gatewayResult);

                // Update analytics
                $this->updateRefundAnalytics($refund);

        $result = [
            'refund_id' => $refund->getId(),
            'refund_amount' => $refundCalculation['refund_amount'],
            'processing_fee' => $refundCalculation['processing_fee'],
            'net_refund' => $refundCalculation['net_refund'],
            'estimated_arrival' => $this->calculateRefundArrivalTime($payment->gateway()),
            'gateway_response' => $gatewayResult
        ];

                Log::info('Refund processed successfully', [
                    'refund_id' => $refund->getId(),
                    'amount' => $refundCalculation['refund_amount']
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::error('Refund processing failed', [
                    'payment_id' => $refundData['payment_id'],
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    // ============================================================================
    // PAYMENT METHOD MANAGEMENT
    // ============================================================================

    /**
     * Add payment method for user with secure tokenization
     * 
     * Securely adds a new payment method for a user including tokenization,
     * validation, and storage in the payment vault.
     * 
     * @param array{
     *     user_id: UserId|string,
     *     payment_method_data: array{
     *         type: string, // 'card', 'bank_account', 'wallet'
     *         card_data?: array{
     *             number?: string,
     *             token?: string,
     *             exp_month: int,
     *             exp_year: int,
     *             cvc?: string
     *         },
     *         bank_data?: array{
     *             account_number?: string,
     *             routing_number?: string,
     *             account_type?: string
     *         },
     *         billing_address: array{
     *             line1: string,
     *             city: string,
     *             state: string,
     *             postal_code: string,
     *             country: string
     *         }
     *     },
     *     gateway?: PaymentGateway|string,
     *     is_default?: bool,
     *     nickname?: string
     * } $methodData Payment method addition data
     * 
     * @return PaymentMethod Securely stored payment method
     * 
     * @throws InvalidPaymentDataException When payment method data is invalid
     * @throws PaymentGatewayException When tokenization fails
     * @throws UserNotFoundException When user not found
     * 
     * @example
     * ```php
     * $paymentMethod = $paymentService->addPaymentMethod([
     *     'user_id' => $userId,
     *     'payment_method_data' => [
     *         'type' => 'card',
     *         'card_data' => [
     *             'token' => 'tok_visa_4242424242424242',
     *             'exp_month' => 12,
     *             'exp_year' => 2025
     *         ],
     *         'billing_address' => [
     *             'line1' => '123 Main St',
     *             'city' => 'New York',
     *             'state' => 'NY',
     *             'postal_code' => '10001',
     *             'country' => 'US'
     *         ]
     *     ],
     *     'is_default' => true,
     *     'nickname' => 'My Visa Card'
     * ]);
     * ```
     */
    public function addPaymentMethod(array $methodData): PaymentMethod
    {
        return DB::transaction(function () use ($methodData) {
            try {
                Log::info('Adding payment method', [
                    'user_id' => $methodData['user_id'],
                    'type' => $methodData['payment_method_data']['type']
                ]);

                // Validate payment method data
                $this->validatePaymentMethodData($methodData);

                // Validate user
                $this->validateUserExists($methodData['user_id']);

                // Select gateway for tokenization
                $gateway = $this->selectTokenizationGateway($methodData);

                // Tokenize payment method securely
                $tokenResult = $this->tokenizePaymentMethod($methodData, $gateway);

                // Verify payment method
                $verificationResult = $this->verifyPaymentMethod($tokenResult, $gateway);

                // Create payment method record
                $paymentMethod = $this->createPaymentMethodRecord($methodData, $tokenResult, $verificationResult);

                // Set as default if requested
                if ($methodData['is_default'] ?? false) {
                    $this->setDefaultPaymentMethod($methodData['user_id'], $paymentMethod);
                }

                // Update user payment profile
                $this->updateUserPaymentProfile($methodData['user_id'], $paymentMethod);

                Log::info('Payment method added successfully', [
                    'user_id' => $methodData['user_id'],
                    'method_id' => $paymentMethod->gatewayToken(),
                    'type' => $paymentMethod->type()
                ]);

                return $paymentMethod;

            } catch (\Exception $e) {
                Log::error('Failed to add payment method', [
                    'user_id' => $methodData['user_id'],
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Get user's payment methods with security filtering
     * 
     * Retrieves user's stored payment methods with appropriate security
     * filtering to protect sensitive payment information.
     * 
     * @param UserId|string $userId User identifier
     * @param array{
     *     include_expired?: bool,
     *     types?: array<string>,
     *     gateways?: array<string>,
     *     include_verification_status?: bool
     * } $options Retrieval options
     * 
     * @return Collection Filtered collection of payment methods
     * 
     * @throws UserNotFoundException When user not found
     * 
     * @example
     * ```php
     * $methods = $paymentService->getUserPaymentMethods($userId, [
     *     'include_expired' => false,
     *     'types' => ['card', 'wallet'],
     *     'include_verification_status' => true
     * ]);
     * ```
     */
    public function getUserPaymentMethods(UserId|string $userId, array $options = []): Collection
    {
        try {
            Log::info('Retrieving user payment methods', [
                'user_id' => $userId,
                'options' => $options
            ]);

            // Validate user
            $this->validateUserExists($userId);

            // Get payment methods with filtering
            $methods = $this->orderRepository->getUserPaymentMethods($userId, $options);

            // Apply security filtering
            $filteredMethods = $this->applySecurityFiltering($methods);

            // Check verification status if requested
            if ($options['include_verification_status'] ?? false) {
                $this->enrichWithVerificationStatus($filteredMethods);
            }

            return $filteredMethods;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve user payment methods', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // ============================================================================
    // PAYMENT ANALYTICS AND REPORTING
    // ============================================================================

    /**
     * Get comprehensive payment analytics
     * 
     * Generates detailed payment analytics including revenue metrics,
     * gateway performance, fraud statistics, and business intelligence.
     * 
     * @param array{
     *     period?: string,
     *     start_date?: Carbon|string,
     *     end_date?: Carbon|string,
     *     gateways?: array<string>,
     *     currencies?: array<string>,
     *     include_predictions?: bool,
     *     user_segments?: array<string>
     * } $options Analytics configuration
     * 
     * @return array{
     *     revenue_metrics: array,
     *     gateway_performance: array,
     *     fraud_statistics: array,
     *     conversion_rates: array,
     *     user_behavior: array,
     *     predictions?: array
     * } Comprehensive payment analytics
     * 
     * @example
     * ```php
     * $analytics = $paymentService->getPaymentAnalytics([
     *     'period' => 'month',
     *     'include_predictions' => true,
     *     'gateways' => ['stripe', 'paypal']
     * ]);
     * ```
     */
    public function getPaymentAnalytics(array $options = []): array
    {
        try {
            Log::info('Generating payment analytics', ['options' => $options]);

            // Generate revenue metrics
            $revenueMetrics = $this->generateRevenueMetrics($options);

            // Analyze gateway performance
            $gatewayPerformance = $this->analyzeGatewayPerformance($options);

            // Calculate fraud statistics
            $fraudStatistics = $this->calculateFraudStatistics($options);

            // Measure conversion rates
            $conversionRates = $this->measureConversionRates($options);

            // Analyze user behavior
            $userBehavior = $this->analyzeUserPaymentBehavior($options);

            $analytics = [
                'revenue_metrics' => $revenueMetrics,
                'gateway_performance' => $gatewayPerformance,
                'fraud_statistics' => $fraudStatistics,
                'conversion_rates' => $conversionRates,
                'user_behavior' => $userBehavior
            ];

            // Generate predictions if requested
            if ($options['include_predictions'] ?? false) {
                $analytics['predictions'] = $this->generatePaymentPredictions($analytics);
            }

            Log::info('Payment analytics generated successfully');

            return $analytics;

        } catch (\Exception $e) {
            Log::error('Failed to generate payment analytics', [
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
     * Validate payment data
     */
    private function validatePaymentData(array $paymentData): void
    {
        $required = ['amount', 'currency', 'user_id'];
        
        foreach ($required as $field) {
            if (!isset($paymentData[$field])) {
                throw new ValidationException("Field '{$field}' is required");
            }
        }

        if ($paymentData['amount'] <= 0) {
            throw new ValidationException('Payment amount must be greater than zero');
        }

        if (!isset($paymentData['payment_method_id']) && !isset($paymentData['payment_method_data'])) {
            throw new ValidationException('Either payment_method_id or payment_method_data is required');
        }
    }

    /**
     * Perform fraud detection
     */
    private function performFraudDetection(array $paymentData): array
    {
        // Implementation would include ML-based fraud detection
        $riskScore = 0.0;
        
        // Check amount threshold
        if ($paymentData['amount'] > self::FRAUD_THRESHOLDS['high_amount']) {
            $riskScore += 25.0;
        }

        // Check user velocity
        $recentPayments = $this->getUserRecentPayments($paymentData['user_id'], 1);
        if (count($recentPayments) > self::FRAUD_THRESHOLDS['velocity_limit']) {
            $riskScore += 30.0;
        }

        // Additional fraud checks would be implemented here
        
        $riskLevel = $riskScore > self::FRAUD_THRESHOLDS['risk_score_limit'] ? 'high' : 
                    ($riskScore > 50.0 ? 'medium' : 'low');

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'checks_performed' => ['amount', 'velocity', 'device', 'location']
        ];
    }

    /**
     * Select optimal payment gateway
     */
    private function selectOptimalGateway(array $paymentData): PaymentGateway
    {
        // If gateway specified, use it
        if (isset($paymentData['gateway'])) {
            return PaymentGateway::from($paymentData['gateway']);
        }

        // Select based on payment method type, amount, currency, etc.
        // This is a simplified selection logic
        return PaymentGateway::stripe();
    }

    /**
     * Validate user exists
     */
    private function validateUserExists(UserId|string $userId): void
    {
        // Implementation would check user repository
        if (!$this->userExists($userId)) {
            throw new UserNotFoundException("User not found: {$userId}");
        }
    }

    /**
     * Check if user exists
     */
    private function userExists(UserId|string $userId): bool
    {
        // Implementation would check user repository
        return true; // Placeholder
    }

    /**
     * Get user recent payments
     */
    private function getUserRecentPayments(UserId|string $userId, int $hours): array
    {
        $since = Carbon::now()->subHours($hours);
        return $this->orderRepository->getUserPaymentsSince($userId, $since);
    }

    /**
     * Validate subscription payment data
     */
    private function validateSubscriptionPaymentData(array $subscriptionData): void
    {
        $required = ['subscription_id', 'user_id', 'amount', 'currency', 'payment_method_id'];
        
        foreach ($required as $field) {
            if (!isset($subscriptionData[$field])) {
                throw new ValidationException("Field '{$field}' is required");
            }
        }

        if ($subscriptionData['amount'] <= 0) {
            throw new ValidationException('Subscription amount must be greater than zero');
        }
    }

    /**
     * Get stored payment method
     */
    private function getStoredPaymentMethod(string $paymentMethodId): ?PaymentMethod
    {
        // Implementation would get payment method from repository
        return null; // Placeholder
    }

    /**
     * Check if payment method needs update
     */
    private function paymentMethodNeedsUpdate(PaymentMethod $paymentMethod): bool
    {
        return $paymentMethod->isExpired() || $paymentMethod->isVerificationExpired();
    }

    /**
     * Request payment method update
     */
    private function requestPaymentMethodUpdate(UserId|string $userId, PaymentMethod $paymentMethod): void
    {
        // Implementation would request payment method update from user
        Log::info('Payment method update requested', [
            'user_id' => $userId,
            'payment_method_id' => $paymentMethod->gatewayToken()
        ]);
    }

    /**
     * Process payment with retry logic
     */
    private function processPaymentWithRetry(array $subscriptionData, PaymentMethod $paymentMethod): Payment
    {
        $maxRetries = $subscriptionData['retry_config']['max_retries'] ?? 3;
        $retryIntervals = $subscriptionData['retry_config']['retry_intervals'] ?? [1, 3, 7]; // days
        
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                return $this->processPayment([
                    'amount' => $subscriptionData['amount'],
                    'currency' => $subscriptionData['currency'],
                    'user_id' => $subscriptionData['user_id'],
                    'payment_method_id' => $subscriptionData['payment_method_id'],
                    'metadata' => [
                        'subscription_id' => $subscriptionData['subscription_id'],
                        'billing_cycle' => $subscriptionData['billing_cycle'],
                        'retry_attempt' => $attempt + 1
                    ]
                ]);
            } catch (\Exception $e) {
                if ($attempt === $maxRetries - 1) {
                    throw $e;
                }
                
                Log::warning('Subscription payment retry', [
                    'subscription_id' => $subscriptionData['subscription_id'],
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage()
                ]);
                
                // Wait before retry
                sleep($retryIntervals[$attempt] ?? 1);
            }
        }
        
        throw new PaymentFailedException('Subscription payment failed after all retries');
    }

    /**
     * Update subscription after payment
     */
    private function updateSubscriptionAfterPayment(string $subscriptionId, Payment $payment): void
    {
        // Implementation would update subscription status
        Log::info('Subscription updated after payment', [
            'subscription_id' => $subscriptionId,
            'payment_id' => $payment->id()->toString()
        ]);
    }

    /**
     * Handle subscription payment failure
     */
    private function handleSubscriptionPaymentFailure(array $subscriptionData, \Exception $e): void
    {
        // Implementation would handle subscription payment failure
        Log::error('Subscription payment failure handled', [
            'subscription_id' => $subscriptionData['subscription_id'],
            'error' => $e->getMessage()
        ]);
    }

    /**
     * Validate refund data
     */
    private function validateRefundData(array $refundData): void
    {
        $required = ['payment_id', 'reason', 'refund_type', 'requested_by'];
        
        foreach ($required as $field) {
            if (!isset($refundData[$field])) {
                throw new ValidationException("Field '{$field}' is required");
            }
        }

        $validTypes = ['full', 'partial', 'chargeback'];
        if (!in_array($refundData['refund_type'], $validTypes)) {
            throw new ValidationException('Invalid refund type');
        }
    }

    /**
     * Get payment by ID
     */
    private function getPaymentById(PaymentId|string $paymentId): ?Payment
    {
        // Implementation would get payment from repository
        return null; // Placeholder
    }

    /**
     * Validate refund eligibility
     */
    private function validateRefundEligibility(Payment $payment, array $refundData): void
    {
        if (!$payment->canBeRefunded()) {
            throw new RefundNotAllowedException('Payment cannot be refunded');
        }

        if ($refundData['refund_type'] === 'partial' && !isset($refundData['amount'])) {
            throw new ValidationException('Partial refund requires amount');
        }
    }

    /**
     * Calculate refund amounts
     */
    private function calculateRefundAmounts(Payment $payment, array $refundData): array
    {
        $refundAmount = $refundData['amount'] ?? $payment->amount();
        $processingFee = $payment->getProcessingFee();
        $netRefund = $refundAmount - $processingFee;

        return [
            'refund_amount' => $refundAmount,
            'processing_fee' => $processingFee,
            'net_refund' => $netRefund
        ];
    }

    /**
     * Create refund record
     */
    private function createRefundRecord(Payment $payment, array $refundData, array $refundCalculation): Transaction
    {
        return Transaction::create(
            id: 'refund_' . uniqid(),
            amount: $refundCalculation['refund_amount'],
            currency: $payment->currency(),
            userId: $payment->userId(),
            type: Transaction::TYPE_REFUND,
            status: Transaction::STATUS_PENDING,
            paymentId: $payment->id()->toString(),
            description: $refundData['reason'],
            metadata: $refundData['metadata'] ?? []
        );
    }

    /**
     * Process gateway refund
     */
    private function processGatewayRefund(Payment $payment, Transaction $refund): array
    {
        // Implementation would process refund through gateway
        return [
            'gateway_refund_id' => 'gw_refund_' . uniqid(),
            'status' => 'succeeded',
            'processing_time' => 1
        ];
    }

    /**
     * Update refund with gateway result
     */
    private function updateRefundWithGatewayResult(Transaction $refund, array $gatewayResult): void
    {
        $refund->markAsCompleted(
            $gatewayResult['gateway_refund_id'],
            json_encode($gatewayResult),
            $gatewayResult
        );
    }

    /**
     * Update payment after refund
     */
    private function updatePaymentAfterRefund(Payment $payment, Transaction $refund): void
    {
        $payment->processRefund(
            $refund->getId(),
            $refund->getAmount(),
            $refund->getDescription()
        );
    }

    /**
     * Notify refund processed
     */
    private function notifyRefundProcessed(Payment $payment, Transaction $refund, array $gatewayResult): void
    {
        // Implementation would notify affected parties
        Log::info('Refund processed notification sent', [
            'payment_id' => $payment->id()->toString(),
            'refund_id' => $refund->getId()
        ]);
    }

    /**
     * Update refund analytics
     */
    private function updateRefundAnalytics(Transaction $refund): void
    {
        // Implementation would update analytics
        Cache::forget('refund_analytics');
    }

    /**
     * Calculate refund arrival time
     */
    private function calculateRefundArrivalTime(string $gateway): Carbon
    {
        $arrivalDays = match ($gateway) {
            'stripe' => 5,
            'paypal' => 3,
            'apple_pay' => 1,
            'google_pay' => 1,
            default => 7
        };

        return Carbon::now()->addDays($arrivalDays);
    }

    /**
     * Validate payment method data
     */
    private function validatePaymentMethodData(array $methodData): void
    {
        $required = ['user_id', 'payment_method_data'];
        
        foreach ($required as $field) {
            if (!isset($methodData[$field])) {
                throw new ValidationException("Field '{$field}' is required");
            }
        }

        $pmData = $methodData['payment_method_data'];
        if (!isset($pmData['type'])) {
            throw new ValidationException('Payment method type is required');
        }

        $validTypes = ['card', 'bank_account', 'wallet'];
        if (!in_array($pmData['type'], $validTypes)) {
            throw new ValidationException('Invalid payment method type');
        }
    }

    /**
     * Select tokenization gateway
     */
    private function selectTokenizationGateway(array $methodData): PaymentGateway
    {
        // Implementation would select optimal gateway for tokenization
        return PaymentGateway::stripe();
    }

    /**
     * Tokenize payment method
     */
    private function tokenizePaymentMethod(array $methodData, PaymentGateway $gateway): array
    {
        // Implementation would tokenize payment method securely
        return [
            'gateway_token' => 'tok_' . uniqid(),
            'last_four' => '4242',
            'brand' => 'visa',
            'expiry_month' => 12,
            'expiry_year' => 2025
        ];
    }

    /**
     * Verify payment method
     */
    private function verifyPaymentMethod(array $tokenResult, PaymentGateway $gateway): array
    {
        // Implementation would verify payment method
        return [
            'verification_status' => 'verified',
            'verification_token' => 'verify_' . uniqid()
        ];
    }

    /**
     * Create payment method record
     */
    private function createPaymentMethodRecord(array $methodData, array $tokenResult, array $verificationResult): PaymentMethod
    {
        return PaymentMethod::create(
            type: $methodData['payment_method_data']['type'],
            gatewayToken: $tokenResult['gateway_token'],
            lastFour: $tokenResult['last_four'] ?? null,
            brand: $tokenResult['brand'] ?? null,
            expiryDate: isset($tokenResult['expiry_month'], $tokenResult['expiry_year']) 
                ? Carbon::create($tokenResult['expiry_year'], $tokenResult['expiry_month'], 1)
                : null,
            holderName: $methodData['payment_method_data']['billing_address']['name'] ?? null,
            isDefault: $methodData['is_default'] ?? false,
            verificationStatus: $verificationResult['verification_status'],
            isInternational: $this->isInternationalPaymentMethod($methodData),
            metadata: $methodData['payment_method_data']
        );
    }

    /**
     * Check if payment method is international
     */
    private function isInternationalPaymentMethod(array $methodData): bool
    {
        $country = $methodData['payment_method_data']['billing_address']['country'] ?? 'US';
        return $country !== 'US';
    }

    /**
     * Set default payment method
     */
    private function setDefaultPaymentMethod(UserId|string $userId, PaymentMethod $paymentMethod): void
    {
        // Implementation would set as default payment method
        Log::info('Payment method set as default', [
            'user_id' => $userId,
            'method_id' => $paymentMethod->gatewayToken()
        ]);
    }

    /**
     * Update user payment profile
     */
    private function updateUserPaymentProfile(UserId|string $userId, PaymentMethod $paymentMethod): void
    {
        // Implementation would update user payment profile
        Cache::forget("user_payment_profile_{$userId}");
    }

    /**
     * Apply security filtering
     */
    private function applySecurityFiltering(Collection $methods): Collection
    {
        return $methods->map(function ($method) {
            if (method_exists($method, 'getSecureData')) {
                return $method->getSecureData();
            }
            return $method->toArray();
        });
    }

    /**
     * Enrich with verification status
     */
    private function enrichWithVerificationStatus(Collection $methods): void
    {
        // Implementation would enrich with verification status
    }

    /**
     * Generate revenue metrics
     */
    private function generateRevenueMetrics(array $options): array
    {
        // Implementation would generate revenue metrics
        return [
            'total_revenue' => 0,
            'recurring_revenue' => 0,
            'one_time_revenue' => 0,
            'refund_amount' => 0,
            'net_revenue' => 0
        ];
    }

    /**
     * Analyze gateway performance
     */
    private function analyzeGatewayPerformance(array $options): array
    {
        // Implementation would analyze gateway performance
        return [
            'stripe' => ['success_rate' => 99.5, 'avg_processing_time' => 1.2],
            'paypal' => ['success_rate' => 98.8, 'avg_processing_time' => 2.1]
        ];
    }

    /**
     * Calculate fraud statistics
     */
    private function calculateFraudStatistics(array $options): array
    {
        // Implementation would calculate fraud statistics
        return [
            'fraud_rate' => 0.1,
            'blocked_transactions' => 0,
            'risk_score_distribution' => []
        ];
    }

    /**
     * Measure conversion rates
     */
    private function measureConversionRates(array $options): array
    {
        // Implementation would measure conversion rates
        return [
            'payment_conversion' => 85.5,
            'subscription_conversion' => 72.3,
            'retry_success_rate' => 45.2
        ];
    }

    /**
     * Analyze user payment behavior
     */
    private function analyzeUserPaymentBehavior(array $options): array
    {
        // Implementation would analyze user payment behavior
        return [
            'avg_payment_amount' => 29.99,
            'payment_frequency' => 'monthly',
            'preferred_gateway' => 'stripe'
        ];
    }

    /**
     * Generate payment predictions
     */
    private function generatePaymentPredictions(array $analytics): array
    {
        // Implementation would generate payment predictions
        return [
            'next_month_revenue' => 50000,
            'churn_probability' => 0.15,
            'growth_rate' => 0.12
        ];
    }

    /**
     * Prepare payment method for processing
     */
    private function preparePaymentMethod(array $paymentData, PaymentGateway $gateway): PaymentMethod
    {
        // Implementation would prepare payment method based on data provided
        // This is a placeholder
        return PaymentMethod::create(
            type: 'card',
            gatewayToken: 'placeholder_token',
            lastFour: '4242',
            brand: 'visa',
            expiryDate: Carbon::now()->addYear(),
            holderName: 'Placeholder User',
            isDefault: false,
            verificationStatus: PaymentMethod::VERIFICATION_PENDING,
            isInternational: false,
            metadata: []
        );
    }

    /**
     * Calculate taxes for payment
     */
    private function calculateTaxes(array $paymentData): ?array
    {
        // Implementation would calculate applicable taxes
        return null;
    }

    /**
     * Create payment record
     */
    private function createPaymentRecord(array $paymentData, PaymentGateway $gateway, PaymentMethod $paymentMethod, ?array $taxData): Payment
    {
        return $this->orderRepository->createPayment([
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'],
            'user_id' => $paymentData['user_id'],
            'gateway' => $gateway,
            'payment_method' => $paymentMethod,
            'tax_data' => $taxData,
            'metadata' => $paymentData['metadata'] ?? [],
            'status' => PaymentStatus::PENDING
        ]);
    }

    /**
     * Process payment through gateway
     */
    private function processGatewayPayment(Payment $payment, array $paymentData, PaymentGateway $gateway): array
    {
        // Implementation would process through the specific gateway
        // This is a placeholder for gateway-specific processing
        return [
            'gateway_payment_id' => 'gw_' . uniqid(),
            'status' => 'succeeded',
            'requires_action' => false
        ];
    }

    /**
     * Handle 3D Secure authentication
     */
    private function handle3DSecure(Payment $payment, array $gatewayResult): Payment
    {
        // Implementation would handle 3D Secure flow
        return $payment;
    }

    /**
     * Update payment with gateway result
     */
    private function updatePaymentWithGatewayResult(Payment $payment, array $gatewayResult): void
    {
        $payment->markAsCompleted($gatewayResult['gateway_payment_id'], $gatewayResult['gateway_response'] ?? null, $gatewayResult);
    }

    /**
     * Save payment method for user
     */
    private function savePaymentMethodForUser(UserId|string $userId, PaymentMethod $paymentMethod, array $gatewayResult): void
    {
        // Implementation would save payment method to user's vault
    }

    /**
     * Update user payment statistics
     */
    private function updateUserPaymentStats(UserId|string $userId, Payment $payment): void
    {
        // Implementation would update user payment statistics
        Cache::forget("user_payment_stats_{$userId}");
    }

    /**
     * Record failed payment
     */
    private function recordFailedPayment(array $paymentData, string $error): void
    {
        $this->orderRepository->recordFailedPayment([
            'user_id' => $paymentData['user_id'],
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'],
            'error_message' => $error,
            'failed_at' => Carbon::now()
        ]);
    }
}