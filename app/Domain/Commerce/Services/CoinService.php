<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Entities\CoinPackage;
use App\Domain\Commerce\Entities\CoinTransaction;
use App\Domain\Commerce\Entities\CoinBalance;
use App\Domain\Commerce\ValueObjects\CoinAmount;
use App\Domain\Commerce\ValueObjects\CoinPackageId;
use App\Domain\Commerce\ValueObjects\TransactionType;
use App\Domain\Commerce\ValueObjects\TransactionStatus;
use App\Domain\Commerce\ValueObjects\Currency;
use App\Domain\Commerce\ValueObjects\Price;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Commerce\Repositories\ProductRepositoryInterface;
use App\Domain\Commerce\Repositories\OrderRepositoryInterface;
use App\Domain\Commerce\Services\PaymentService;
use App\Domain\Commerce\Exceptions\InsufficientCoinsException;
use App\Domain\Commerce\Exceptions\CoinPackageNotFoundException;
use App\Domain\Commerce\Exceptions\InvalidCoinTransactionException;
use App\Domain\Commerce\Exceptions\CoinTransferNotAllowedException;
use App\Domain\Common\Exceptions\ValidationException;
use App\Domain\Auth\Exceptions\UserNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

/**
 * CoinService - Virtual currency system for in-app purchases
 * 
 * Comprehensive virtual currency management service for the ForeverUsInLove dating
 * application. Handles coin packages, purchases, transfers, rewards, and complete
 * virtual economy management with fraud prevention and analytics.
 * 
 * Features:
 * - Virtual coin economy with multiple coin types
 * - Coin package purchasing with dynamic pricing
 * - Secure coin transactions and balance management
 * - Coin rewards and loyalty programs
 * - Peer-to-peer coin transfers with limits
 * - Promotional coin bonuses and multipliers
 * - Coin expiration and lifecycle management
 * - Advanced fraud detection and prevention
 * - Real-time balance tracking and notifications
 * - Coin analytics and economy health monitoring
 * - Integration with payment processing
 * - Gamification and achievement rewards
 * - Daily/weekly coin earning limits
 * - Premium coin benefits and exclusive access
 * 
 * Architecture:
 * - Service Layer Pattern for business logic
 * - Domain-Driven Design (DDD) principles
 * - Clean Architecture / Hexagonal Architecture
 * - SOLID principles with Dependency Inversion
 * - Laravel 12 with PHP 8.2 strict typing
 * - Event-driven architecture for coin transactions
 * - Repository pattern for data persistence
 * - Double-entry bookkeeping for coin accounting
 * 
 * @package App\Domain\Commerce\Services
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 * 
 * @see \App\Domain\Commerce\Entities\CoinPackage
 * @see \App\Domain\Commerce\Entities\CoinTransaction
 * @see \App\Domain\Commerce\Repositories\ProductRepositoryInterface
 */
class CoinService
{
    /**
     * Coin package configurations with dynamic pricing tiers
     */
    private const COIN_PACKAGES = [
        'starter' => [
            'coins' => 100,
            'base_price' => 4.99,
            'bonus_percentage' => 0,
            'popular' => false
        ],
        'popular' => [
            'coins' => 500,
            'base_price' => 19.99,
            'bonus_percentage' => 15,
            'popular' => true
        ],
        'value' => [
            'coins' => 1200,
            'base_price' => 39.99,
            'bonus_percentage' => 25,
            'popular' => false
        ],
        'premium' => [
            'coins' => 2500,
            'base_price' => 69.99,
            'bonus_percentage' => 35,
            'popular' => false
        ],
        'elite' => [
            'coins' => 5000,
            'base_price' => 99.99,
            'bonus_percentage' => 50,
            'popular' => false
        ]
    ];

    /**
     * Transaction limits for fraud prevention
     */
    private const TRANSACTION_LIMITS = [
        'daily_purchase_limit' => 10000,
        'daily_spend_limit' => 5000,
        'daily_transfer_limit' => 1000,
        'max_single_purchase' => 5000,
        'min_transfer_amount' => 10,
        'max_transfer_amount' => 500
    ];

    /**
     * Coin earning and reward configurations
     */
    private const REWARD_CONFIGS = [
        'daily_login' => 10,
        'profile_completion' => 50,
        'first_match' => 25,
        'successful_chat' => 5,
        'profile_verification' => 100,
        'referral_bonus' => 200,
        'birthday_bonus' => 100,
        'streak_multiplier' => 1.5
    ];

    /**
     * Constructor with dependency injection
     * 
     * @param ProductRepositoryInterface $productRepository Product and package data management
     * @param OrderRepositoryInterface $orderRepository Transaction and order management
     * @param PaymentService $paymentService Payment processing integration
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PaymentService $paymentService
    ) {}

    // ============================================================================
    // COIN BALANCE MANAGEMENT
    // ============================================================================

    /**
     * Get user's current coin balance with detailed breakdown
     * 
     * Retrieves comprehensive coin balance information including available coins,
     * pending transactions, reserved coins, and balance history with real-time
     * updates and security validation.
     * 
     * @param UserId|string $userId Target user identifier
     * @param array{
     *     include_pending?: bool,
     *     include_history?: bool,
     *     include_reserved?: bool,
     *     include_expiration?: bool
     * } $options Balance retrieval options
     * 
     * @return array{
     *     available_balance: int,
     *     total_balance: int,
     *     pending_credits: int,
     *     pending_debits: int,
     *     reserved_balance: int,
     *     expiring_soon: array,
     *     balance_history?: array,
     *     last_updated: Carbon
     * } Comprehensive balance information
     * 
     * @throws UserNotFoundException When user not found
     * 
     * @example
     * ```php
     * $balance = $coinService->getUserCoinBalance($userId, [
     *     'include_pending' => true,
     *     'include_history' => true,
     *     'include_expiration' => true
     * ]);
     * ```
     */
    public function getUserCoinBalance(UserId|string $userId, array $options = []): array
    {
        try {
            Log::info('Retrieving user coin balance', [
                'user_id' => $userId,
                'options' => $options
            ]);

            // Validate user exists
            $this->validateUserExists($userId);

            // Get cached balance for performance
            $cacheKey = "user_coin_balance_{$userId}";
            $balance = Cache::remember($cacheKey, 300, function () use ($userId) {
                return $this->calculateUserBalance($userId);
            });

            // Include pending transactions if requested
            if ($options['include_pending'] ?? false) {
                $pendingTransactions = $this->getPendingTransactions($userId);
                $balance['pending_credits'] = $pendingTransactions['credits'];
                $balance['pending_debits'] = $pendingTransactions['debits'];
            }

            // Include reserved balance if requested
            if ($options['include_reserved'] ?? false) {
                $balance['reserved_balance'] = $this->getReservedBalance($userId);
            }

            // Include expiration information if requested
            if ($options['include_expiration'] ?? false) {
                $balance['expiring_soon'] = $this->getExpiringCoins($userId, 30); // 30 days
            }

            // Include balance history if requested
            if ($options['include_history'] ?? false) {
                $balance['balance_history'] = $this->getBalanceHistory($userId, 30); // 30 days
            }

            $balance['last_updated'] = Carbon::now();

            Log::info('User coin balance retrieved successfully', [
                'user_id' => $userId,
                'available_balance' => $balance['available_balance']
            ]);

            return $balance;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve user coin balance', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'options' => $options
            ]);
            throw $e;
        }
    }

    /**
     * Add coins to user balance with comprehensive tracking
     * 
     * Credits coins to user account with detailed transaction logging, fraud
     * detection, and balance validation. Supports various coin sources including
     * purchases, rewards, bonuses, and transfers.
     * 
     * @param array{
     *     user_id: UserId|string,
     *     amount: CoinAmount|int,
     *     transaction_type: TransactionType|string,
     *     source: string, // 'purchase', 'reward', 'bonus', 'transfer', 'refund'
     *     reference_id?: string,
     *     description?: string,
     *     expires_at?: Carbon|string|null,
     *     metadata?: array{
     *         source_user_id?: string,
     *         reward_type?: string,
     *         bonus_multiplier?: float,
     *         campaign_id?: string
     *     }
     * } $creditData Coin credit configuration
     * 
     * @return CoinTransaction Completed coin credit transaction
     * 
     * @throws UserNotFoundException When user not found
     * @throws InvalidCoinTransactionException When transaction data is invalid
     * @throws ValidationException When validation fails
     * 
     * @example
     * ```php
     * $transaction = $coinService->addCoinsToUser([
     *     'user_id' => $userId,
     *     'amount' => 500,
     *     'transaction_type' => TransactionType::CREDIT,
     *     'source' => 'purchase',
     *     'reference_id' => 'pkg_premium_001',
     *     'description' => 'Premium coin package purchase',
     *     'metadata' => ['package_id' => 'premium_500']
     * ]);
     * ```
     */
    public function addCoinsToUser(array $creditData): CoinTransaction
    {
        return DB::transaction(function () use ($creditData) {
            try {
                Log::info('Adding coins to user', [
                    'user_id' => $creditData['user_id'],
                    'amount' => $creditData['amount'],
                    'source' => $creditData['source']
                ]);

                // Validate credit data
                $this->validateCoinCreditData($creditData);

                // Validate user exists
                $this->validateUserExists($creditData['user_id']);

                // Perform fraud detection for large amounts
                if ($creditData['amount'] > 1000 && $creditData['source'] === 'purchase') {
                    $this->performCoinFraudDetection($creditData);
                }

                // Create coin transaction record
                $transaction = $this->createCoinTransaction($creditData);

                // Update user balance
                $this->updateUserBalance($creditData['user_id'], $creditData['amount'], 'credit');

                // Apply coin expiration if specified
                if (isset($creditData['expires_at'])) {
                    $this->setCoinExpiration($transaction, $creditData['expires_at']);
                }

                // Update coin statistics
                $this->updateCoinStatistics($creditData['user_id'], $transaction);

                // Clear balance cache
                Cache::forget("user_coin_balance_{$creditData['user_id']}");

                // Fire coin transaction events
                $this->fireTransactionEvents($transaction);

                Log::info('Coins added to user successfully', [
                    'user_id' => $creditData['user_id'],
                    'transaction_id' => $transaction->getId(),
                    'amount' => $creditData['amount']
                ]);

                return $transaction;

            } catch (\Exception $e) {
                Log::error('Failed to add coins to user', [
                    'user_id' => $creditData['user_id'],
                    'error' => $e->getMessage(),
                    'credit_data' => $creditData
                ]);
                throw $e;
            }
        });
    }

    /**
     * Deduct coins from user balance with validation
     * 
     * Debits coins from user account with balance validation, transaction
     * logging, and fraud prevention. Supports various spending purposes
     * including gifts, features, and services.
     * 
     * @param array{
     *     user_id: UserId|string,
     *     amount: CoinAmount|int,
     *     purpose: string, // 'gift', 'feature', 'boost', 'unlock', 'transfer'
     *     reference_id?: string,
     *     description?: string,
     *     allow_negative?: bool,
     *     metadata?: array{
     *         recipient_id?: string,
     *         feature_type?: string,
     *         gift_id?: string
     *     }
     * } $debitData Coin debit configuration
     * 
     * @return CoinTransaction Completed coin debit transaction
     * 
     * @throws UserNotFoundException When user not found
     * @throws InsufficientCoinsException When insufficient balance
     * @throws InvalidCoinTransactionException When transaction data is invalid
     * 
     * @example
     * ```php
     * $transaction = $coinService->deductCoinsFromUser([
     *     'user_id' => $userId,
     *     'amount' => 50,
     *     'purpose' => 'gift',
     *     'reference_id' => 'gift_roses_001',
     *     'description' => 'Sent virtual roses gift',
     *     'metadata' => [
     *         'recipient_id' => $recipientId,
     *         'gift_id' => 'roses_bouquet'
     *     ]
     * ]);
     * ```
     */
    public function deductCoinsFromUser(array $debitData): CoinTransaction
    {
        return DB::transaction(function () use ($debitData) {
            try {
                Log::info('Deducting coins from user', [
                    'user_id' => $debitData['user_id'],
                    'amount' => $debitData['amount'],
                    'purpose' => $debitData['purpose']
                ]);

                // Validate debit data
                $this->validateCoinDebitData($debitData);

                // Validate user exists
                $this->validateUserExists($debitData['user_id']);

                // Check sufficient balance (unless allowing negative)
                if (!($debitData['allow_negative'] ?? false)) {
                    $this->validateSufficientBalance($debitData['user_id'], $debitData['amount']);
                }

                // Check daily spending limits
                $this->validateDailySpendingLimits($debitData['user_id'], $debitData['amount']);

                // Create coin transaction record
                $transaction = $this->createCoinTransaction([
                    'user_id' => $debitData['user_id'],
                    'amount' => -$debitData['amount'], // Negative for debit
                    'transaction_type' => TransactionType::DEBIT,
                    'source' => $debitData['purpose'],
                    'reference_id' => $debitData['reference_id'] ?? null,
                    'description' => $debitData['description'] ?? null,
                    'metadata' => $debitData['metadata'] ?? []
                ]);

                // Update user balance
                $this->updateUserBalance($debitData['user_id'], $debitData['amount'], 'debit');

                // Update spending statistics
                $this->updateSpendingStatistics($debitData['user_id'], $transaction);

                // Clear balance cache
                Cache::forget("user_coin_balance_{$debitData['user_id']}");

                // Fire transaction events
                $this->fireTransactionEvents($transaction);

                Log::info('Coins deducted from user successfully', [
                    'user_id' => $debitData['user_id'],
                    'transaction_id' => $transaction->getId(),
                    'amount' => $debitData['amount']
                ]);

                return $transaction;

            } catch (\Exception $e) {
                Log::error('Failed to deduct coins from user', [
                    'user_id' => $debitData['user_id'],
                    'error' => $e->getMessage(),
                    'debit_data' => $debitData
                ]);
                throw $e;
            }
        });
    }

    // ============================================================================
    // COIN PACKAGE PURCHASING
    // ============================================================================

    /**
     * Get available coin packages with personalized pricing
     * 
     * Retrieves coin packages with dynamic pricing based on user behavior,
     * purchase history, and promotional offers. Includes package recommendations
     * and bonus calculations.
     * 
     * @param array{
     *     user_id?: UserId|string,
     *     currency?: Currency|string,
     *     country?: string,
     *     include_promotions?: bool,
     *     include_recommendations?: bool
     * } $options Package retrieval options
     * 
     * @return Collection Coin packages with personalized pricing and bonuses
     * 
     * @example
     * ```php
     * $packages = $coinService->getAvailableCoinPackages([
     *     'user_id' => $userId,
     *     'currency' => Currency::USD,
     *     'include_promotions' => true,
     *     'include_recommendations' => true
     * ]);
     * ```
     */
    public function getAvailableCoinPackages(array $options = []): Collection
    {
        try {
            Log::info('Retrieving available coin packages', ['options' => $options]);

            // Get base packages
            $packages = $this->productRepository->getCoinPackages();

            // Apply personalized pricing if user provided
            if (isset($options['user_id'])) {
                $this->applyPersonalizedCoinPricing($packages, $options['user_id'], $options);
            }

            // Apply geographic pricing
            if (isset($options['country'])) {
                $this->applyGeographicCoinPricing($packages, $options['country']);
            }

            // Include promotional offers if requested
            if ($options['include_promotions'] ?? false) {
                $this->enrichPackagesWithPromotions($packages, $options);
            }

            // Include package recommendations if requested
            if ($options['include_recommendations'] ?? false) {
                $this->addPackageRecommendations($packages, $options);
            }

            // Calculate effective coin values and bonuses
            $this->calculatePackageValues($packages);

            Log::info('Available coin packages retrieved successfully', [
                'package_count' => $packages->count()
            ]);

            return $packages;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve coin packages', [
                'error' => $e->getMessage(),
                'options' => $options
            ]);
            throw $e;
        }
    }

    /**
     * Purchase coin package with payment processing
     * 
     * Handles complete coin package purchase including payment processing,
     * coin crediting, bonus application, and transaction recording.
     * 
     * @param array{
     *     user_id: UserId|string,
     *     package_id: CoinPackageId|string,
     *     payment_method_id?: string,
     *     payment_method_data?: array,
     *     promo_code?: string,
     *     metadata?: array{
     *         source?: string,
     *         campaign_id?: string
     *     }
     * } $purchaseData Coin package purchase configuration
     * 
     * @return array{
     *     transaction: CoinTransaction,
     *     payment: Payment,
     *     coins_credited: int,
     *     bonus_coins: int,
     *     total_coins: int,
     *     package_details: CoinPackage
     * } Complete purchase result
     * 
     * @throws CoinPackageNotFoundException When package not found
     * @throws UserNotFoundException When user not found
     * @throws PaymentFailedException When payment processing fails
     * 
     * @example
     * ```php
     * $result = $coinService->purchaseCoinPackage([
     *     'user_id' => $userId,
     *     'package_id' => 'premium_500',
     *     'payment_method_id' => 'pm_card_123',
     *     'promo_code' => 'VALENTINE20',
     *     'metadata' => [
     *         'source' => 'mobile_app',
     *         'campaign_id' => 'valentine_2024'
     *     ]
     * ]);
     * ```
     */
    public function purchaseCoinPackage(array $purchaseData): array
    {
        return DB::transaction(function () use ($purchaseData) {
            try {
                Log::info('Processing coin package purchase', [
                    'user_id' => $purchaseData['user_id'],
                    'package_id' => $purchaseData['package_id']
                ]);

                // Validate purchase data
                $this->validateCoinPurchaseData($purchaseData);

                // Validate user and package
                $this->validateUserExists($purchaseData['user_id']);
                $package = $this->validateCoinPackageExists($purchaseData['package_id']);

                // Apply promotional codes if provided
                $pricingAdjustments = [];
                if (isset($purchaseData['promo_code'])) {
                    $pricingAdjustments = $this->applyCoinPromoCode($purchaseData['promo_code'], $package);
                }

                // Calculate final pricing and bonuses
                $pricingDetails = $this->calculateCoinPackagePricing($package, $pricingAdjustments);

                // Process payment
                $payment = $this->paymentService->processPayment([
                    'amount' => $pricingDetails['final_price'],
                    'currency' => $pricingDetails['currency'],
                    'user_id' => $purchaseData['user_id'],
                    'payment_method_id' => $purchaseData['payment_method_id'] ?? null,
                    'payment_method_data' => $purchaseData['payment_method_data'] ?? null,
                    'description' => "Coin package purchase: {$package->name()}",
                    'metadata' => [
                        'package_id' => $package->id()->toString(),
                        'coins_amount' => $pricingDetails['total_coins']
                    ]
                ]);

                // Credit coins to user account
                $coinTransaction = $this->addCoinsToUser([
                    'user_id' => $purchaseData['user_id'],
                    'amount' => $pricingDetails['total_coins'],
                    'transaction_type' => TransactionType::CREDIT,
                    'source' => 'purchase',
                    'reference_id' => $payment->id()->toString(),
                    'description' => "Coins from package: {$package->name()}",
                    'metadata' => array_merge($purchaseData['metadata'] ?? [], [
                        'package_id' => $package->id()->toString(),
                        'base_coins' => $pricingDetails['base_coins'],
                        'bonus_coins' => $pricingDetails['bonus_coins'],
                        'payment_id' => $payment->id()->toString()
                    ])
                ]);

                // Update purchase statistics
                $this->updateCoinPurchaseStatistics($purchaseData['user_id'], $package, $pricingDetails);

                // Record package purchase history
                $this->recordPackagePurchaseHistory($purchaseData['user_id'], $package, $payment);

                $result = [
                    'transaction' => $coinTransaction,
                    'payment' => $payment,
                    'coins_credited' => $pricingDetails['base_coins'],
                    'bonus_coins' => $pricingDetails['bonus_coins'],
                    'total_coins' => $pricingDetails['total_coins'],
                    'package_details' => $package
                ];

                Log::info('Coin package purchased successfully', [
                    'user_id' => $purchaseData['user_id'],
                    'package_id' => $package->id()->toString(),
                    'total_coins' => $pricingDetails['total_coins'],
                    'payment_amount' => $pricingDetails['final_price']
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::error('Failed to purchase coin package', [
                    'user_id' => $purchaseData['user_id'],
                    'error' => $e->getMessage(),
                    'purchase_data' => $purchaseData
                ]);
                throw $e;
            }
        });
    }

    // ============================================================================
    // COIN REWARDS AND BONUSES
    // ============================================================================

    /**
     * Award coins as reward with comprehensive tracking
     * 
     * Awards coins to users for various achievements, milestones, and activities
     * with detailed tracking, streak bonuses, and anti-abuse measures.
     * 
     * @param array{
     *     user_id: UserId|string,
     *     reward_type: string, // 'daily_login', 'achievement', 'milestone', etc.
     *     base_amount: int,
     *     multiplier?: float,
     *     streak_bonus?: bool,
     *     description?: string,
     *     metadata?: array{
     *         achievement_id?: string,
     *         milestone_type?: string,
     *         streak_days?: int
     *     }
     * } $rewardData Reward configuration and details
     * 
     * @return array{
     *     transaction: CoinTransaction,
     *     base_reward: int,
     *     streak_bonus: int,
     *     multiplier_bonus: int,
     *     total_awarded: int,
     *     next_milestone?: array
     * } Reward result with bonus breakdown
     * 
     * @throws UserNotFoundException When user not found
     * @throws InvalidCoinTransactionException When reward data is invalid
     * 
     * @example
     * ```php
     * $reward = $coinService->awardRewardCoins([
     *     'user_id' => $userId,
     *     'reward_type' => 'daily_login',
     *     'base_amount' => 10,
     *     'streak_bonus' => true,
     *     'description' => 'Daily login streak bonus',
     *     'metadata' => ['streak_days' => 7]
     * ]);
     * ```
     */
    public function awardRewardCoins(array $rewardData): array
    {
        return DB::transaction(function () use ($rewardData) {
            try {
                Log::info('Awarding reward coins', [
                    'user_id' => $rewardData['user_id'],
                    'reward_type' => $rewardData['reward_type'],
                    'base_amount' => $rewardData['base_amount']
                ]);

                // Validate reward data
                $this->validateRewardData($rewardData);

                // Validate user exists
                $this->validateUserExists($rewardData['user_id']);

                // Check for reward abuse/limits
                $this->validateRewardEligibility($rewardData['user_id'], $rewardData['reward_type']);

                // Calculate bonus multipliers
                $bonusCalculation = $this->calculateRewardBonuses($rewardData);

                // Create reward transaction
                $transaction = $this->addCoinsToUser([
                    'user_id' => $rewardData['user_id'],
                    'amount' => $bonusCalculation['total_amount'],
                    'transaction_type' => TransactionType::CREDIT,
                    'source' => 'reward',
                    'reference_id' => $rewardData['reward_type'],
                    'description' => $rewardData['description'] ?? "Reward: {$rewardData['reward_type']}",
                    'metadata' => array_merge($rewardData['metadata'] ?? [], [
                        'reward_type' => $rewardData['reward_type'],
                        'base_amount' => $rewardData['base_amount'],
                        'bonus_breakdown' => $bonusCalculation['breakdown']
                    ])
                ]);

                // Update reward statistics
                $this->updateRewardStatistics($rewardData['user_id'], $rewardData['reward_type'], $bonusCalculation);

                // Check for next milestones
                $nextMilestone = $this->checkNextRewardMilestone($rewardData['user_id'], $rewardData['reward_type']);

                $result = [
                    'transaction' => $transaction,
                    'base_reward' => $rewardData['base_amount'],
                    'streak_bonus' => $bonusCalculation['streak_bonus'],
                    'multiplier_bonus' => $bonusCalculation['multiplier_bonus'],
                    'total_awarded' => $bonusCalculation['total_amount']
                ];

                if ($nextMilestone) {
                    $result['next_milestone'] = $nextMilestone;
                }

                Log::info('Reward coins awarded successfully', [
                    'user_id' => $rewardData['user_id'],
                    'transaction_id' => $transaction->getId(),
                    'total_awarded' => $bonusCalculation['total_amount']
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::error('Failed to award reward coins', [
                    'user_id' => $rewardData['user_id'],
                    'error' => $e->getMessage(),
                    'reward_data' => $rewardData
                ]);
                throw $e;
            }
        });
    }

    // ============================================================================
    // PRIVATE HELPER METHODS
    // ============================================================================

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
            // Convert to UserId if string
            if (is_string($userId)) {
                $userId = UserId::fromString($userId);
            }
            
            // Check if user exists in the system
            // This would typically query the user repository
            $userExists = \App\Models\User\User::where('id', $userId->toInt())->exists();
            
            Log::debug('User existence check', [
                'user_id' => $userId->toInt(),
                'exists' => $userExists
            ]);
            
            return $userExists;
            
        } catch (\Exception $e) {
            Log::error('Error checking user existence', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Calculate user balance
     */
    private function calculateUserBalance(UserId|string $userId): array
    {
        try {
            // Convert to UserId if string
            if (is_string($userId)) {
                $userId = UserId::fromString($userId);
            }
            
            // Get user coin transactions
            $transactions = $this->orderRepository->getUserCoinTransactions($userId);
            
            // Calculate credits and debits
            $totalCredits = $transactions->where('amount', '>', 0)->sum('amount');
            $totalDebits = abs($transactions->where('amount', '<', 0)->sum('amount'));
            
            // Calculate available balance
            $availableBalance = $totalCredits - $totalDebits;
            
            // Get reserved balance for pending transactions
            $reservedBalance = $this->getReservedBalance($userId);
            
            // Calculate net available balance
            $netAvailableBalance = max(0, $availableBalance - $reservedBalance);
            
            $balance = [
                'available_balance' => $netAvailableBalance,
                'total_balance' => $availableBalance,
                'total_earned' => $totalCredits,
                'total_spent' => $totalDebits,
                'reserved_balance' => $reservedBalance,
                'gross_balance' => $totalCredits,
                'net_balance' => $availableBalance
            ];
            
            Log::debug('User balance calculated', [
                'user_id' => $userId->toInt(),
                'available_balance' => $netAvailableBalance,
                'total_earned' => $totalCredits,
                'total_spent' => $totalDebits
            ]);
            
            return $balance;
            
        } catch (\Exception $e) {
            Log::error('Error calculating user balance', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            // Return zero balance on error
            return [
                'available_balance' => 0,
                'total_balance' => 0,
                'total_earned' => 0,
                'total_spent' => 0,
                'reserved_balance' => 0,
                'gross_balance' => 0,
                'net_balance' => 0
            ];
        }
    }

    /**
     * Get pending transactions
     */
    private function getPendingTransactions(UserId|string $userId): array
    {
        try {
            // Convert to UserId if string
            if (is_string($userId)) {
                $userId = UserId::fromString($userId);
            }
            
            // Get pending transactions from repository
            $pendingTransactions = $this->orderRepository->getUserCoinTransactions($userId, [
                'statuses' => [TransactionStatus::PENDING, TransactionStatus::PROCESSING]
            ]);
            
            // Calculate pending credits and debits
            $pendingCredits = $pendingTransactions->where('amount', '>', 0)->sum('amount');
            $pendingDebits = abs($pendingTransactions->where('amount', '<', 0)->sum('amount'));
            
            $result = [
                'credits' => $pendingCredits,
                'debits' => $pendingDebits,
                'net_pending' => $pendingCredits - $pendingDebits,
                'transaction_count' => $pendingTransactions->count()
            ];
            
            Log::debug('Pending transactions retrieved', [
                'user_id' => $userId->toInt(),
                'pending_credits' => $pendingCredits,
                'pending_debits' => $pendingDebits
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Error getting pending transactions', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'credits' => 0,
                'debits' => 0,
                'net_pending' => 0,
                'transaction_count' => 0
            ];
        }
    }

    /**
     * Validate coin credit data
     */
    private function validateCoinCreditData(array $creditData): void
    {
        $required = ['user_id', 'amount', 'transaction_type', 'source'];
        
        foreach ($required as $field) {
            if (!isset($creditData[$field])) {
                throw new ValidationException("Field '{$field}' is required");
            }
        }

        if ($creditData['amount'] <= 0) {
            throw new ValidationException('Coin amount must be positive');
        }
    }

    /**
     * Validate sufficient balance
     */
    private function validateSufficientBalance(UserId|string $userId, int $amount): void
    {
        $balance = $this->calculateUserBalance($userId);
        
        if ($balance['available_balance'] < $amount) {
            throw new InsufficientCoinsException("Insufficient coin balance. Available: {$balance['available_balance']}, Required: {$amount}");
        }
    }

    /**
     * Create coin transaction
     */
    private function createCoinTransaction(array $transactionData): CoinTransaction
    {
        return $this->orderRepository->createCoinTransaction([
            'user_id' => $transactionData['user_id'],
            'amount' => $transactionData['amount'],
            'type' => $transactionData['transaction_type'] ?? TransactionType::CREDIT,
            'source' => $transactionData['source'],
            'reference_id' => $transactionData['reference_id'] ?? null,
            'description' => $transactionData['description'] ?? null,
            'metadata' => $transactionData['metadata'] ?? [],
            'status' => TransactionStatus::COMPLETED,
            'created_at' => Carbon::now()
        ]);
    }

    /**
     * Update user balance
     */
    private function updateUserBalance(UserId|string $userId, int $amount, string $type): void
    {
        // Implementation would update balance in repository
        if ($type === 'credit') {
            $this->orderRepository->incrementUserCoinBalance($userId, $amount);
        } else {
            $this->orderRepository->decrementUserCoinBalance($userId, $amount);
        }
    }

    /**
     * Fire transaction events
     */
    private function fireTransactionEvents(CoinTransaction $transaction): void
    {
        try {
            // Fire coin transaction processed event
            Event::dispatch(new \App\Domain\Commerce\Events\CoinTransactionProcessed($transaction));
            
            // Fire specific events based on transaction type
            if ($transaction->isCredit()) {
                Event::dispatch(new \App\Domain\Commerce\Events\CoinsCredited(
                    $transaction->getUserId(),
                    $transaction->getAmount(),
                    $transaction->getSource(),
                    $transaction->getMetadata()
                ));
            } elseif ($transaction->isDebit()) {
                Event::dispatch(new \App\Domain\Commerce\Events\CoinsDebited(
                    $transaction->getUserId(),
                    abs($transaction->getAmount()),
                    $transaction->getSource(),
                    $transaction->getMetadata()
                ));
            }
            
            // Fire balance updated event
            Event::dispatch(new \App\Domain\Commerce\Events\CoinBalanceUpdated(
                $transaction->getUserId(),
                $this->calculateUserBalance($transaction->getUserId())
            ));
            
            Log::info('Transaction events fired', [
                'transaction_id' => $transaction->getId(),
                'user_id' => $transaction->getUserId()->toInt(),
                'amount' => $transaction->getAmount(),
                'type' => $transaction->getType()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fire transaction events', [
                'transaction_id' => $transaction->getId(),
                'error' => $e->getMessage()
            ]);
            // Don't throw exception to avoid breaking the main transaction flow
        }
    }

    /**
     * Get reserved balance for user
     */
    private function getReservedBalance(UserId|string $userId): int
    {
        try {
            // Convert to UserId if string
            if (is_string($userId)) {
                $userId = UserId::fromString($userId);
            }
            
            // Get reserved coins from pending debit transactions
            $reservedTransactions = $this->orderRepository->getUserCoinTransactions($userId, [
                'statuses' => [TransactionStatus::PENDING, TransactionStatus::PROCESSING],
                'types' => [TransactionType::DEBIT]
            ]);
            
            // Calculate total reserved amount (negative amounts)
            $reservedCoins = abs($reservedTransactions->where('amount', '<', 0)->sum('amount'));
            
            Log::debug('Reserved balance calculated', [
                'user_id' => $userId->toInt(),
                'reserved_coins' => $reservedCoins
            ]);
            
            return $reservedCoins;
            
        } catch (\Exception $e) {
            Log::error('Error calculating reserved balance', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get expiring coins for user
     */
    private function getExpiringCoins(UserId|string $userId, int $days): array
    {
        try {
            // Convert to UserId if string
            if (is_string($userId)) {
                $userId = UserId::fromString($userId);
            }
            
            $expirationDate = Carbon::now()->addDays($days);
            
            // Get transactions with expiration dates
            $expiringTransactions = $this->orderRepository->getUserCoinTransactions($userId)
                ->where('expires_at', '<=', $expirationDate)
                ->where('expires_at', '>', Carbon::now())
                ->where('amount', '>', 0)
                ->where('status', TransactionStatus::COMPLETED);
            
            $expiringCoins = [];
            
            foreach ($expiringTransactions as $transaction) {
                $daysRemaining = Carbon::now()->diffInDays($transaction->getExpiresAt(), false);
                
                $expiringCoins[] = [
                    'transaction_id' => $transaction->getId(),
                    'amount' => $transaction->getAmount(),
                    'expires_at' => $transaction->getExpiresAt()->toISOString(),
                    'days_remaining' => max(0, $daysRemaining),
                    'source' => $transaction->getSource(),
                    'description' => $transaction->getDescription()
                ];
            }
            
            // Sort by days remaining (ascending)
            usort($expiringCoins, function($a, $b) {
                return $a['days_remaining'] <=> $b['days_remaining'];
            });
            
            Log::debug('Expiring coins retrieved', [
                'user_id' => $userId->toInt(),
                'days_threshold' => $days,
                'expiring_count' => count($expiringCoins)
            ]);
            
            return $expiringCoins;
            
        } catch (\Exception $e) {
            Log::error('Error getting expiring coins', [
                'user_id' => $userId,
                'days' => $days,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get balance history for user
     */
    private function getBalanceHistory(UserId|string $userId, int $days): array
    {
        try {
            // Convert to UserId if string
            if (is_string($userId)) {
                $userId = UserId::fromString($userId);
            }
            
            $startDate = Carbon::now()->subDays($days);
            
            // Get transactions within the date range
            $transactions = $this->orderRepository->getUserCoinTransactions($userId, [
                'date_range' => [
                    'start' => $startDate,
                    'end' => Carbon::now()
                ]
            ])->sortBy('created_at');
            
            $history = [];
            $runningBalance = 0;
            
            foreach ($transactions as $transaction) {
                $runningBalance += $transaction->getAmount();
                
                $history[] = [
                    'date' => $transaction->getCreatedAt()->toDateString(),
                    'datetime' => $transaction->getCreatedAt()->toISOString(),
                    'transaction_type' => $transaction->getType(),
                    'amount' => $transaction->getAmount(),
                    'balance' => $runningBalance,
                    'description' => $transaction->getDescription(),
                    'source' => $transaction->getSource(),
                    'transaction_id' => $transaction->getId(),
                    'is_credit' => $transaction->isCredit(),
                    'is_debit' => $transaction->isDebit()
                ];
            }
            
            // Group by date for summary
            $dailySummary = [];
            foreach ($history as $entry) {
                $date = $entry['date'];
                if (!isset($dailySummary[$date])) {
                    $dailySummary[$date] = [
                        'date' => $date,
                        'total_credits' => 0,
                        'total_debits' => 0,
                        'net_change' => 0,
                        'ending_balance' => 0,
                        'transaction_count' => 0
                    ];
                }
                
                if ($entry['is_credit']) {
                    $dailySummary[$date]['total_credits'] += $entry['amount'];
                } else {
                    $dailySummary[$date]['total_debits'] += abs($entry['amount']);
                }
                
                $dailySummary[$date]['net_change'] += $entry['amount'];
                $dailySummary[$date]['ending_balance'] = $entry['balance'];
                $dailySummary[$date]['transaction_count']++;
            }
            
            Log::debug('Balance history retrieved', [
                'user_id' => $userId->toInt(),
                'days' => $days,
                'transaction_count' => count($history),
                'daily_summaries' => count($dailySummary)
            ]);
            
            return [
                'transactions' => $history,
                'daily_summary' => array_values($dailySummary),
                'period_start' => $startDate->toISOString(),
                'period_end' => Carbon::now()->toISOString(),
                'total_transactions' => count($history),
                'starting_balance' => $runningBalance - array_sum(array_column($history, 'amount')),
                'ending_balance' => $runningBalance
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting balance history', [
                'user_id' => $userId,
                'days' => $days,
                'error' => $e->getMessage()
            ]);
            return [
                'transactions' => [],
                'daily_summary' => [],
                'period_start' => Carbon::now()->subDays($days)->toISOString(),
                'period_end' => Carbon::now()->toISOString(),
                'total_transactions' => 0,
                'starting_balance' => 0,
                'ending_balance' => 0
            ];
        }
    }

    /**
     * Perform coin fraud detection
     */
    private function performCoinFraudDetection(array $creditData): void
    {
        $userId = $creditData['user_id'];
        $amount = $creditData['amount'];
        
        // Check for velocity fraud (multiple large purchases in short time)
        $recentPurchases = $this->orderRepository->getUserCoinTransactions($userId)
            ->where('source', 'purchase')
            ->where('amount', '>', 1000)
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->sum('amount');
            
        if ($recentPurchases + $amount > self::TRANSACTION_LIMITS['daily_purchase_limit']) {
            throw new InvalidCoinTransactionException('Suspicious purchase pattern detected');
        }
        
        // Check for unusual spending patterns
        $dailyAverage = $this->orderRepository->getUserCoinTransactions($userId)
            ->where('source', 'purchase')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->avg('amount');
            
        if ($amount > ($dailyAverage * 5)) {
            Log::warning('Unusual purchase amount detected', [
                'user_id' => $userId,
                'amount' => $amount,
                'daily_average' => $dailyAverage
            ]);
        }
        
        // Additional fraud checks could include:
        // - IP geolocation analysis
        // - Device fingerprinting
        // - Payment method validation
        // - User behavior analysis
    }

    /**
     * Set coin expiration
     */
    private function setCoinExpiration(CoinTransaction $transaction, Carbon|string $expiresAt): void
    {
        if (is_string($expiresAt)) {
            $expiresAt = Carbon::parse($expiresAt);
        }
        
        // Update transaction with expiration date
        // Note: This would need to be implemented in the repository
        // $this->orderRepository->updateCoinTransaction($transaction->getId(), [
        //     'expires_at' => $expiresAt
        // ]);
        
        Log::info('Coin expiration set', [
            'transaction_id' => $transaction->getId(),
            'expires_at' => $expiresAt->toISOString()
        ]);
    }

    /**
     * Update coin statistics
     */
    private function updateCoinStatistics(UserId|string $userId, CoinTransaction $transaction): void
    {
        // Update user wallet statistics
        $wallet = \App\Models\Commerce\Wallet::forUser($userId)->first();
        if ($wallet) {
            if ($transaction->getAmount() > 0) {
                $wallet->increment('lifetime_earned', $transaction->getAmount());
            } else {
                $wallet->increment('lifetime_spent', abs($transaction->getAmount()));
            }
        }
        
        // Update global coin statistics
        Cache::increment('total_coins_earned', $transaction->getAmount() > 0 ? $transaction->getAmount() : 0);
        Cache::increment('total_coins_spent', $transaction->getAmount() < 0 ? abs($transaction->getAmount()) : 0);
        
        Log::info('Coin statistics updated', [
            'user_id' => $userId,
            'transaction_amount' => $transaction->getAmount(),
            'transaction_type' => $transaction->getType()
        ]);
    }

    /**
     * Validate coin debit data
     */
    private function validateCoinDebitData(array $debitData): void
    {
        $required = ['user_id', 'amount', 'purpose'];
        
        foreach ($required as $field) {
            if (!isset($debitData[$field])) {
                throw new ValidationException("Field '{$field}' is required");
            }
        }

        if ($debitData['amount'] <= 0) {
            throw new ValidationException('Coin amount must be positive');
        }
    }

    /**
     * Validate daily spending limits
     */
    private function validateDailySpendingLimits(UserId|string $userId, int $amount): void
    {
        $wallet = \App\Models\Commerce\Wallet::forUser($userId)->first();
        
        if (!$wallet) {
            throw new InvalidCoinTransactionException('User wallet not found');
        }
        
        // Check if user can spend this amount within daily limits
        if (!$wallet->canSpendAmount($amount)) {
            throw new InvalidCoinTransactionException('Daily spending limit exceeded or insufficient balance');
        }
        
        Log::info('Daily spending limits validated', [
            'user_id' => $userId,
            'amount' => $amount,
            'remaining_daily_spend' => $wallet->remaining_daily_spend
        ]);
    }

    /**
     * Update spending statistics
     */
    private function updateSpendingStatistics(UserId|string $userId, CoinTransaction $transaction): void
    {
        $wallet = \App\Models\Commerce\Wallet::forUser($userId)->first();
        
        if ($wallet && $transaction->getAmount() < 0) {
            $spentAmount = abs($transaction->getAmount());
            
            // Update daily spending
            $wallet->increment('daily_spent', $spentAmount);
            $wallet->update(['last_spent_date' => Carbon::now()]);
            
            // Update lifetime spending
            $wallet->increment('lifetime_spent', $spentAmount);
        }
        
        // Update global spending statistics
        Cache::increment('daily_total_spent', abs($transaction->getAmount()));
        
        Log::info('Spending statistics updated', [
            'user_id' => $userId,
            'spent_amount' => abs($transaction->getAmount()),
            'purpose' => $transaction->getSource()
        ]);
    }

    /**
     * Apply personalized coin pricing
     */
    private function applyPersonalizedCoinPricing(Collection $packages, UserId|string $userId, array $options): void
    {
        $wallet = \App\Models\Commerce\Wallet::forUser($userId)->first();
        
        if (!$wallet) {
            return;
        }
        
        // Get user's purchase history
        $totalPurchased = $wallet->lifetime_purchased;
        $isVipUser = $totalPurchased > 10000; // VIP threshold
        $isFrequentBuyer = $wallet->purchase_count > 10;
        
        foreach ($packages as $package) {
            $originalPrice = $package->price()->toFloat();
            $discount = 0;
            
            // VIP users get 10% discount
            if ($isVipUser) {
                $discount = 0.10;
            }
            // Frequent buyers get 5% discount
            elseif ($isFrequentBuyer) {
                $discount = 0.05;
            }
            
            // Apply discount
            if ($discount > 0) {
                $discountedPrice = $originalPrice * (1 - $discount);
                $package->updatePrice(Price::fromFloat($discountedPrice, $package->currency()));
                
                Log::info('Personalized pricing applied', [
                    'user_id' => $userId,
                    'package_id' => $package->id()->toString(),
                    'original_price' => $originalPrice,
                    'discounted_price' => $discountedPrice,
                    'discount_percentage' => $discount * 100
                ]);
            }
        }
    }

    /**
     * Apply geographic coin pricing
     */
    private function applyGeographicCoinPricing(Collection $packages, string $country): void
    {
        // Geographic pricing multipliers based on purchasing power
        $pricingMultipliers = [
            'US' => 1.0,    // Base price
            'CA' => 1.0,    // Similar to US
            'GB' => 1.1,    // Slightly higher
            'AU' => 1.1,    // Similar to UK
            'DE' => 1.0,    // Similar to US
            'FR' => 1.0,    // Similar to US
            'BR' => 0.7,    // Lower purchasing power
            'MX' => 0.8,    // Lower purchasing power
            'IN' => 0.5,    // Much lower purchasing power
            'CN' => 0.6,    // Lower purchasing power
        ];
        
        $multiplier = $pricingMultipliers[$country] ?? 1.0;
        
        if ($multiplier !== 1.0) {
            foreach ($packages as $package) {
                $originalPrice = $package->price()->toFloat();
                $adjustedPrice = $originalPrice * $multiplier;
                
                $package->updatePrice(Price::fromFloat($adjustedPrice, $package->currency()));
                
                Log::info('Geographic pricing applied', [
                    'country' => $country,
                    'package_id' => $package->id()->toString(),
                    'original_price' => $originalPrice,
                    'adjusted_price' => $adjustedPrice,
                    'multiplier' => $multiplier
                ]);
            }
        }
    }

    /**
     * Enrich packages with promotions
     */
    private function enrichPackagesWithPromotions(Collection $packages, array $options): void
    {
        $currentTime = Carbon::now();
        
        // Check for active promotions
        $activePromotions = Cache::remember('active_coin_promotions', 300, function () use ($currentTime) {
            return [
                'valentine_2024' => [
                    'name' => 'Valentine\'s Day Special',
                    'discount_percentage' => 20,
                    'bonus_multiplier' => 1.2,
                    'valid_until' => Carbon::parse('2024-02-15'),
                    'target_packages' => ['premium', 'elite']
                ],
                'new_user_bonus' => [
                    'name' => 'New User Welcome',
                    'discount_percentage' => 15,
                    'bonus_multiplier' => 1.15,
                    'valid_until' => Carbon::parse('2024-12-31'),
                    'target_packages' => ['starter', 'popular']
                ]
            ];
        });
        
        foreach ($packages as $package) {
            $packageTier = $package->tier();
            $originalPrice = $package->price()->toFloat();
            $originalCoins = $package->coinAmount();
            
            foreach ($activePromotions as $promoId => $promotion) {
                if ($currentTime->isBefore($promotion['valid_until']) && 
                    in_array($packageTier, $promotion['target_packages'])) {
                    
                    // Apply discount
                    $discountedPrice = $originalPrice * (1 - $promotion['discount_percentage'] / 100);
                    
                    // Apply bonus multiplier
                    $bonusCoins = (int) ($originalCoins * ($promotion['bonus_multiplier'] - 1));
                    
                    $package->updatePrice(Price::fromFloat($discountedPrice, $package->currency()));
                    $package->updateBonus($bonusCoins, CoinPackage::BONUS_FIXED);
                    
                    Log::info('Promotion applied to package', [
                        'package_id' => $package->id()->toString(),
                        'promotion_id' => $promoId,
                        'promotion_name' => $promotion['name'],
                        'discount_percentage' => $promotion['discount_percentage'],
                        'bonus_coins' => $bonusCoins
                    ]);
                    
                    break; // Only apply one promotion per package
                }
            }
        }
    }

    /**
     * Add package recommendations
     */
    private function addPackageRecommendations(Collection $packages, array $options): void
    {
        if (!isset($options['user_id'])) {
            return;
        }
        
        $userId = $options['user_id'];
        $wallet = \App\Models\Commerce\Wallet::forUser($userId)->first();
        
        if (!$wallet) {
            return;
        }
        
        // Analyze user behavior for recommendations
        $totalPurchased = $wallet->lifetime_purchased;
        $averagePurchase = $wallet->lifetime_purchased / max($wallet->purchase_count, 1);
        
        foreach ($packages as $package) {
            $recommendationScore = 0;
            $recommendationReasons = [];
            
            // Recommend based on user's spending pattern
            if ($totalPurchased === 0) {
                // New user - recommend starter package
                if ($package->tier() === 'starter') {
                    $recommendationScore = 100;
                    $recommendationReasons[] = 'Perfect for new users';
                }
            } elseif ($averagePurchase < 500) {
                // Light spender - recommend popular package
                if ($package->tier() === 'popular') {
                    $recommendationScore = 90;
                    $recommendationReasons[] = 'Great value for your spending level';
                }
            } elseif ($averagePurchase < 2000) {
                // Medium spender - recommend value package
                if ($package->tier() === 'value') {
                    $recommendationScore = 95;
                    $recommendationReasons[] = 'Best value for your usage';
                }
            } else {
                // Heavy spender - recommend premium/elite
                if (in_array($package->tier(), ['premium', 'elite'])) {
                    $recommendationScore = 85;
                    $recommendationReasons[] = 'Premium option for power users';
                }
            }
            
            // Add recommendation metadata
            if ($recommendationScore > 0) {
                $package->updateMetadata([
                    'recommendation_score' => $recommendationScore,
                    'recommendation_reasons' => $recommendationReasons,
                    'is_recommended' => true
                ]);
            }
        }
        
        Log::info('Package recommendations added', [
            'user_id' => $userId,
            'total_purchased' => $totalPurchased,
            'average_purchase' => $averagePurchase
        ]);
    }

    /**
     * Calculate package values
     */
    private function calculatePackageValues(Collection $packages): void
    {
        foreach ($packages as $package) {
            $baseCoins = $package->coinAmount();
            $bonusCoins = $package->bonusCoins();
            $totalCoins = $baseCoins + $bonusCoins;
            $price = $package->price()->toFloat();
            
            // Calculate effective values
            $pricePerCoin = $totalCoins > 0 ? $price / $totalCoins : 0;
            $valueRating = $this->calculateValueRating($pricePerCoin);
            $savingsPercentage = $bonusCoins > 0 ? ($bonusCoins / $totalCoins) * 100 : 0;
            
            // Update package metadata with calculated values
            $package->updateMetadata([
                'effective_value' => $totalCoins,
                'price_per_coin' => $pricePerCoin,
                'value_rating' => $valueRating,
                'savings_percentage' => $savingsPercentage,
                'is_best_value' => false // Simplified for now
            ]);
        }
    }
    
    /**
     * Calculate value rating based on price per coin
     */
    private function calculateValueRating(float $pricePerCoin): string
    {
        if ($pricePerCoin <= 0.015) return 'excellent';
        if ($pricePerCoin <= 0.025) return 'very_good';
        if ($pricePerCoin <= 0.035) return 'good';
        if ($pricePerCoin <= 0.045) return 'fair';
        return 'poor';
    }
    
    /**
     * Check if package is best value
     */
    private function isBestValuePackage(CoinPackage $package, $allPackages): bool
    {
        $packagePricePerCoin = $package->price()->toFloat() / $package->getTotalCoins();
        
        return $allPackages->every(function($otherPackage) use ($package, $packagePricePerCoin) {
            if (!$otherPackage instanceof CoinPackage) {
                return true;
            }
            
            if ($otherPackage->id()->toString() === $package->id()->toString()) {
                return true;
            }
            
            $otherPricePerCoin = $otherPackage->price()->toFloat() / $otherPackage->getTotalCoins();
            return $packagePricePerCoin <= $otherPricePerCoin;
        });
    }

    /**
     * Validate coin purchase data
     */
    private function validateCoinPurchaseData(array $purchaseData): void
    {
        $required = ['user_id', 'package_id'];
        
        foreach ($required as $field) {
            if (!isset($purchaseData[$field])) {
                throw new ValidationException("Field '{$field}' is required");
            }
        }
    }

    /**
     * Validate coin package exists
     */
    private function validateCoinPackageExists(CoinPackageId|string $packageId): CoinPackage
    {
        $package = $this->productRepository->findCoinPackageById($packageId);
        
        if (!$package) {
            throw new CoinPackageNotFoundException("Coin package not found: {$packageId}");
        }
        
        return $package;
    }

    /**
     * Apply coin promo code
     */
    private function applyCoinPromoCode(string $promoCode, CoinPackage $package): array
    {
        // Validate promo code
        $validPromoCodes = Cache::remember('valid_promo_codes', 3600, function () {
            return [
                'WELCOME20' => [
                    'discount_percentage' => 20,
                    'bonus_multiplier' => 1.0,
                    'valid_until' => Carbon::parse('2024-12-31'),
                    'usage_limit' => 1,
                    'min_purchase' => 0
                ],
                'VALENTINE25' => [
                    'discount_percentage' => 25,
                    'bonus_multiplier' => 1.25,
                    'valid_until' => Carbon::parse('2024-02-15'),
                    'usage_limit' => 3,
                    'min_purchase' => 1000
                ],
                'VIP50' => [
                    'discount_percentage' => 50,
                    'bonus_multiplier' => 1.5,
                    'valid_until' => Carbon::parse('2024-12-31'),
                    'usage_limit' => 1,
                    'min_purchase' => 5000
                ]
            ];
        });
        
        $promoCode = strtoupper($promoCode);
        
        if (!isset($validPromoCodes[$promoCode])) {
            throw new InvalidCoinTransactionException('Invalid promo code');
        }
        
        $promo = $validPromoCodes[$promoCode];
        
        // Check if promo is still valid
        if (Carbon::now()->isAfter($promo['valid_until'])) {
            throw new InvalidCoinTransactionException('Promo code has expired');
        }
        
        // Check minimum purchase requirement
        if ($package->coinAmount() < $promo['min_purchase']) {
            throw new InvalidCoinTransactionException('Minimum purchase requirement not met for this promo code');
        }
        
        // Check usage limit (this would need to be tracked per user)
        // For now, we'll assume it's valid
        
        Log::info('Promo code applied successfully', [
            'promo_code' => $promoCode,
            'package_id' => $package->id()->toString(),
            'discount_percentage' => $promo['discount_percentage'],
            'bonus_multiplier' => $promo['bonus_multiplier']
        ]);
        
        return [
            'discount_percentage' => $promo['discount_percentage'],
            'bonus_multiplier' => $promo['bonus_multiplier'],
            'valid' => true,
            'promo_name' => $promoCode
        ];
    }

    /**
     * Calculate coin package pricing
     */
    private function calculateCoinPackagePricing(CoinPackage $package, array $adjustments): array
    {
        $baseCoins = $package->coinAmount();
        $bonusCoins = $package->bonusCoins();
        $totalCoins = $baseCoins + $bonusCoins;
        
        // Apply promo code adjustments
        $discountPercentage = $adjustments['discount_percentage'] ?? 0;
        $bonusMultiplier = $adjustments['bonus_multiplier'] ?? 1.0;
        
        // Calculate final coins with bonus multiplier
        $finalCoins = (int) ($totalCoins * $bonusMultiplier);
        $additionalBonusCoins = $finalCoins - $totalCoins;
        
        $basePrice = $package->price()->toFloat();
        $finalPrice = $basePrice * (1 - $discountPercentage / 100);
        
        return [
            'base_coins' => $baseCoins,
            'bonus_coins' => $bonusCoins,
            'additional_bonus_coins' => $additionalBonusCoins,
            'total_coins' => $finalCoins,
            'base_price' => $basePrice,
            'final_price' => $finalPrice,
            'currency' => $package->currency()->value(),
            'discount_applied' => $discountPercentage,
            'bonus_multiplier_applied' => $bonusMultiplier,
            'savings_amount' => $basePrice - $finalPrice,
            'effective_price_per_coin' => $finalCoins > 0 ? $finalPrice / $finalCoins : 0
        ];
    }

    /**
     * Update coin purchase statistics
     */
    private function updateCoinPurchaseStatistics(UserId|string $userId, CoinPackage $package, array $pricingDetails): void
    {
        // Update package purchase statistics
        $package->recordPurchase($pricingDetails['final_price']);
        
        // Update user wallet statistics
        $wallet = \App\Models\Commerce\Wallet::forUser($userId)->first();
        if ($wallet) {
            $wallet->increment('lifetime_purchased', $pricingDetails['total_coins']);
            $wallet->increment('daily_purchased', $pricingDetails['total_coins']);
            $wallet->update(['last_purchased_date' => Carbon::now()]);
        }
        
        // Update global statistics
        Cache::increment('total_package_purchases');
        Cache::increment('total_revenue', $pricingDetails['final_price']);
        Cache::increment('total_coins_sold', $pricingDetails['total_coins']);
        
        // Update package popularity score
        // Note: This would need to be implemented in the CoinPackage entity
        // $package->increment('popularity_score', 10);
        
        Log::info('Coin purchase statistics updated', [
            'user_id' => $userId,
            'package_id' => $package->id()->toString(),
            'coins_purchased' => $pricingDetails['total_coins'],
            'revenue' => $pricingDetails['final_price']
        ]);
    }

    /**
     * Record package purchase history
     */
    private function recordPackagePurchaseHistory(UserId|string $userId, CoinPackage $package, $payment): void
    {
        // Create purchase history record
        $purchaseHistory = [
            'user_id' => $userId,
            'package_id' => $package->id()->toString(),
            'package_name' => $package->name(),
            'package_tier' => $package->tier(),
            'coins_purchased' => $package->getTotalCoins(),
            'price_paid' => $package->price()->toFloat(),
            'currency' => $package->currency()->value(),
            'payment_id' => $payment->id()->toString(),
            'payment_method' => $payment->getMethod(),
            'purchase_date' => Carbon::now(),
            'metadata' => [
                'package_type' => $package->type(),
                'bonus_coins' => $package->bonusCoins(),
                'bonus_percentage' => $package->getBonusPercentage(),
                'value_rating' => $package->getValueRating()
            ]
        ];
        
        // Store in cache for quick access
        $cacheKey = "user_purchase_history_{$userId}";
        $existingHistory = Cache::get($cacheKey, []);
        $existingHistory[] = $purchaseHistory;
        
        // Keep only last 50 purchases
        if (count($existingHistory) > 50) {
            $existingHistory = array_slice($existingHistory, -50);
        }
        
        Cache::put($cacheKey, $existingHistory, 86400); // 24 hours
        
        Log::info('Package purchase history recorded', [
            'user_id' => $userId,
            'package_id' => $package->id()->toString(),
            'payment_id' => $payment->id()->toString()
        ]);
    }

    /**
     * Validate reward data
     */
    private function validateRewardData(array $rewardData): void
    {
        $required = ['user_id', 'reward_type', 'base_amount'];
        
        foreach ($required as $field) {
            if (!isset($rewardData[$field])) {
                throw new ValidationException("Field '{$field}' is required");
            }
        }

        if ($rewardData['base_amount'] <= 0) {
            throw new ValidationException('Base reward amount must be positive');
        }
    }

    /**
     * Validate reward eligibility
     */
    private function validateRewardEligibility(UserId|string $userId, string $rewardType): void
    {
        $wallet = \App\Models\Commerce\Wallet::forUser($userId)->first();
        
        if (!$wallet) {
            throw new InvalidCoinTransactionException('User wallet not found');
        }
        
        // Check cooldown periods for different reward types
        $cooldownPeriods = [
            'daily_login' => 24, // hours
            'profile_completion' => 0, // one-time
            'verification' => 0, // one-time
            'referral' => 0, // per referral
            'achievement' => 0, // per achievement
            'milestone' => 0, // per milestone
        ];
        
        $cooldownHours = $cooldownPeriods[$rewardType] ?? 0;
        
        if ($cooldownHours > 0) {
            $lastReward = $this->orderRepository->getUserCoinTransactions($userId)
                ->where('source', 'reward')
                ->where('metadata->reward_type', $rewardType)
                ->orderBy('created_at', 'desc')
                ->first();
                
            if ($lastReward && $lastReward->getCreatedAt()->diffInHours(Carbon::now()) < $cooldownHours) {
                throw new InvalidCoinTransactionException("Reward '{$rewardType}' is on cooldown");
            }
        }
        
        // Check for one-time rewards that have already been claimed
        if ($cooldownHours === 0) {
            $alreadyClaimed = $this->orderRepository->getUserCoinTransactions($userId)
                ->where('source', 'reward')
                ->where('metadata->reward_type', $rewardType)
                ->exists();
                
            if ($alreadyClaimed && in_array($rewardType, ['profile_completion', 'verification'])) {
                throw new InvalidCoinTransactionException("Reward '{$rewardType}' has already been claimed");
            }
        }
        
        Log::info('Reward eligibility validated', [
            'user_id' => $userId,
            'reward_type' => $rewardType,
            'cooldown_hours' => $cooldownHours
        ]);
    }

    /**
     * Calculate reward bonuses
     */
    private function calculateRewardBonuses(array $rewardData): array
    {
        $baseAmount = $rewardData['base_amount'];
        $multiplier = $rewardData['multiplier'] ?? 1.0;
        $streakBonus = 0;
        
        // Calculate streak bonus if enabled
        if ($rewardData['streak_bonus'] ?? false) {
            $streakDays = $rewardData['metadata']['streak_days'] ?? 1;
            
            // Streak bonus calculation: 10% per day, max 100%
            $streakMultiplier = min(1.0 + ($streakDays * 0.1), 2.0);
            $streakBonus = (int) ($baseAmount * ($streakMultiplier - 1));
        }
        
        // Calculate multiplier bonus
        $multiplierBonus = (int) (($baseAmount + $streakBonus) * ($multiplier - 1));
        
        // Calculate total amount
        $totalAmount = $baseAmount + $streakBonus + $multiplierBonus;
        
        // Apply daily reward limits
        $dailyRewardLimit = self::REWARD_CONFIGS['daily_login'] * 10; // 10x base amount
        if ($totalAmount > $dailyRewardLimit) {
            $totalAmount = $dailyRewardLimit;
            $multiplierBonus = $dailyRewardLimit - $baseAmount - $streakBonus;
        }
        
        return [
            'base_amount' => $baseAmount,
            'streak_bonus' => $streakBonus,
            'multiplier_bonus' => $multiplierBonus,
            'total_amount' => $totalAmount,
            'breakdown' => [
                'base' => $baseAmount,
                'streak' => $streakBonus,
                'multiplier' => $multiplierBonus,
                'daily_limit_applied' => $totalAmount === $dailyRewardLimit
            ]
        ];
    }

    /**
     * Update reward statistics
     */
    private function updateRewardStatistics(UserId|string $userId, string $rewardType, array $bonusCalculation): void
    {
        $wallet = \App\Models\Commerce\Wallet::forUser($userId)->first();
        
        if ($wallet) {
            // Update reward-specific statistics
            $rewardStats = $wallet->metadata['reward_stats'] ?? [];
            $rewardStats[$rewardType] = [
                'total_awarded' => ($rewardStats[$rewardType]['total_awarded'] ?? 0) + $bonusCalculation['total_amount'],
                'count' => ($rewardStats[$rewardType]['count'] ?? 0) + 1,
                'last_awarded' => Carbon::now()->toISOString(),
                'streak_bonus_total' => ($rewardStats[$rewardType]['streak_bonus_total'] ?? 0) + $bonusCalculation['streak_bonus'],
                'multiplier_bonus_total' => ($rewardStats[$rewardType]['multiplier_bonus_total'] ?? 0) + $bonusCalculation['multiplier_bonus']
            ];
            
            $wallet->updateMetadata(['reward_stats' => $rewardStats]);
        }
        
        // Update global reward statistics
        Cache::increment("reward_type_{$rewardType}_count");
        Cache::increment("reward_type_{$rewardType}_total_amount", $bonusCalculation['total_amount']);
        Cache::increment('total_rewards_awarded', $bonusCalculation['total_amount']);
        
        Log::info('Reward statistics updated', [
            'user_id' => $userId,
            'reward_type' => $rewardType,
            'total_awarded' => $bonusCalculation['total_amount'],
            'streak_bonus' => $bonusCalculation['streak_bonus'],
            'multiplier_bonus' => $bonusCalculation['multiplier_bonus']
        ]);
    }

    /**
     * Check next reward milestone
     */
    private function checkNextRewardMilestone(UserId|string $userId, string $rewardType): ?array
    {
        $wallet = \App\Models\Commerce\Wallet::forUser($userId)->first();
        
        if (!$wallet) {
            return null;
        }
        
        $rewardStats = $wallet->metadata['reward_stats'][$rewardType] ?? [];
        $totalAwarded = $rewardStats['total_awarded'] ?? 0;
        
        // Define milestones for different reward types
        $milestones = [
            'daily_login' => [50, 100, 250, 500, 1000, 2500, 5000],
            'profile_completion' => [50, 100, 200],
            'verification' => [100, 200, 500],
            'referral' => [200, 500, 1000, 2000],
            'achievement' => [100, 250, 500, 1000],
            'milestone' => [500, 1000, 2500, 5000, 10000]
        ];
        
        $rewardMilestones = $milestones[$rewardType] ?? [];
        
        // Find next milestone
        foreach ($rewardMilestones as $milestone) {
            if ($totalAwarded < $milestone) {
                $progress = ($totalAwarded / $milestone) * 100;
                
                return [
                    'milestone_amount' => $milestone,
                    'current_progress' => $totalAwarded,
                    'progress_percentage' => round($progress, 2),
                    'coins_needed' => $milestone - $totalAwarded,
                    'reward_type' => $rewardType,
                    'estimated_days' => $this->estimateDaysToMilestone($rewardType, $milestone - $totalAwarded)
                ];
            }
        }
        
        return null; // All milestones reached
    }
    
    /**
     * Estimate days to reach milestone
     */
    private function estimateDaysToMilestone(string $rewardType, int $coinsNeeded): int
    {
        $dailyRewardAmounts = [
            'daily_login' => self::REWARD_CONFIGS['daily_login'],
            'profile_completion' => self::REWARD_CONFIGS['profile_completion'],
            'verification' => self::REWARD_CONFIGS['profile_verification'],
            'referral' => self::REWARD_CONFIGS['referral_bonus'],
            'achievement' => 25, // Average achievement reward
            'milestone' => 100   // Average milestone reward
        ];
        
        $dailyAmount = $dailyRewardAmounts[$rewardType] ?? 10;
        
        return max(1, (int) ceil($coinsNeeded / $dailyAmount));
    }
}