<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Entities;

use App\Domain\Commerce\ValueObjects\PaymentId;
use App\Domain\Commerce\ValueObjects\PaymentStatus;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;

/**
 * Payment Entity
 * 
 * Core domain entity representing a payment transaction in the ForeverUsInLove dating application.
 * Encapsulates payment processing, gateway integration, and payment lifecycle management.
 * 
 * @package App\Domain\Commerce\Entities
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class Payment
{
    // Payment Types
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_SUBSCRIPTION = 'subscription';
    public const TYPE_RENEWAL = 'renewal';
    public const TYPE_UPGRADE = 'upgrade';
    public const TYPE_DOWNGRADE = 'downgrade';
    public const TYPE_REFUND = 'refund';
    public const TYPE_CHARGEBACK = 'chargeback';
    public const TYPE_DISPUTE = 'dispute';

    // Payment Gateways
    public const GATEWAY_STRIPE = 'stripe';
    public const GATEWAY_PAYPAL = 'paypal';
    public const GATEWAY_APPLE_PAY = 'apple_pay';
    public const GATEWAY_GOOGLE_PAY = 'google_pay';
    public const GATEWAY_RAZORPAY = 'razorpay';
    public const GATEWAY_SQUARE = 'square';
    public const GATEWAY_ADYEN = 'adyen';

    // Payment Methods
    public const METHOD_CREDIT_CARD = 'credit_card';
    public const METHOD_DEBIT_CARD = 'debit_card';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_DIGITAL_WALLET = 'digital_wallet';
    public const METHOD_CRYPTOCURRENCY = 'cryptocurrency';
    public const METHOD_PREPAID_CARD = 'prepaid_card';

    private function __construct(
        private readonly PaymentId $id,
        private float $amount,
        private string $currency,
        private UserId $userId,
        private string $gateway,
        private PaymentMethod $paymentMethod,
        private PaymentStatus $status,
        private string $type,
        private ?string $gatewayTransactionId,
        private ?string $gatewayResponse,
        private ?array $gatewayMetadata,
        private ?array $taxData,
        private ?array $metadata,
        private ?Carbon $processedAt,
        private ?Carbon $failedAt,
        private ?string $failureReason,
        private ?string $refundId,
        private ?float $refundAmount,
        private ?Carbon $refundedAt,
        private ?string $chargebackId,
        private ?Carbon $chargebackAt,
        private ?string $disputeId,
        private ?Carbon $disputeAt,
        private Carbon $createdAt,
        private Carbon $updatedAt
    ) {}

    public static function create(
        PaymentId $id,
        float $amount,
        string $currency,
        UserId $userId,
        string $gateway,
        PaymentMethod $paymentMethod,
        PaymentStatus $status,
        string $type = self::TYPE_PURCHASE,
        ?string $gatewayTransactionId = null,
        ?string $gatewayResponse = null,
        ?array $gatewayMetadata = null,
        ?array $taxData = null,
        ?array $metadata = null
    ): self {
        $now = now();
        
        return new self(
            id: $id,
            amount: $amount,
            currency: $currency,
            userId: $userId,
            gateway: $gateway,
            paymentMethod: $paymentMethod,
            status: $status,
            type: $type,
            gatewayTransactionId: $gatewayTransactionId,
            gatewayResponse: $gatewayResponse,
            gatewayMetadata: $gatewayMetadata,
            taxData: $taxData,
            metadata: $metadata,
            processedAt: null,
            failedAt: null,
            failureReason: null,
            refundId: null,
            refundAmount: null,
            refundedAt: null,
            chargebackId: null,
            chargebackAt: null,
            disputeId: null,
            disputeAt: null,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public static function fromRepositoryData(array $data): self
    {
        $paymentId = PaymentId::fromInt($data['id']);
        $userId = UserId::fromInt($data['user_id']);
        $status = PaymentStatus::fromString($data['status']);
        
        // Create PaymentMethod from data
        $paymentMethod = PaymentMethod::fromRepositoryData($data['payment_method'] ?? []);

        $payment = new self(
            id: $paymentId,
            amount: $data['amount'],
            currency: $data['currency'],
            userId: $userId,
            gateway: $data['gateway'],
            paymentMethod: $paymentMethod,
            status: $status,
            type: $data['type'] ?? self::TYPE_PURCHASE,
            gatewayTransactionId: $data['gateway_transaction_id'] ?? null,
            gatewayResponse: $data['gateway_response'] ?? null,
            gatewayMetadata: $data['gateway_metadata'] ?? null,
            taxData: $data['tax_data'] ?? null,
            metadata: $data['metadata'] ?? null,
            processedAt: isset($data['processed_at']) ? Carbon::parse($data['processed_at']) : null,
            failedAt: isset($data['failed_at']) ? Carbon::parse($data['failed_at']) : null,
            failureReason: $data['failure_reason'] ?? null,
            refundId: $data['refund_id'] ?? null,
            refundAmount: $data['refund_amount'] ?? null,
            refundedAt: isset($data['refunded_at']) ? Carbon::parse($data['refunded_at']) : null,
            chargebackId: $data['chargeback_id'] ?? null,
            chargebackAt: isset($data['chargeback_at']) ? Carbon::parse($data['chargeback_at']) : null,
            disputeId: $data['dispute_id'] ?? null,
            disputeAt: isset($data['dispute_at']) ? Carbon::parse($data['dispute_at']) : null,
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at'])
        );

        return $payment;
    }

    // Getters
    public function id(): PaymentId
    {
        return $this->id;
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function gateway(): string
    {
        return $this->gateway;
    }

    public function paymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function gatewayTransactionId(): ?string
    {
        return $this->gatewayTransactionId;
    }

    public function gatewayResponse(): ?string
    {
        return $this->gatewayResponse;
    }

    public function gatewayMetadata(): ?array
    {
        return $this->gatewayMetadata;
    }

    public function taxData(): ?array
    {
        return $this->taxData;
    }

    public function metadata(): ?array
    {
        return $this->metadata;
    }

    public function processedAt(): ?Carbon
    {
        return $this->processedAt;
    }

    public function failedAt(): ?Carbon
    {
        return $this->failedAt;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function refundId(): ?string
    {
        return $this->refundId;
    }

    public function refundAmount(): ?float
    {
        return $this->refundAmount;
    }

    public function refundedAt(): ?Carbon
    {
        return $this->refundedAt;
    }

    public function chargebackId(): ?string
    {
        return $this->chargebackId;
    }

    public function chargebackAt(): ?Carbon
    {
        return $this->chargebackAt;
    }

    public function disputeId(): ?string
    {
        return $this->disputeId;
    }

    public function disputeAt(): ?Carbon
    {
        return $this->disputeAt;
    }

    public function createdAt(): Carbon
    {
        return $this->createdAt;
    }

    public function updatedAt(): Carbon
    {
        return $this->updatedAt;
    }

    // Business Logic Methods
    public function isSuccessful(): bool
    {
        return $this->status->isCompleted();
    }

    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isProcessing(): bool
    {
        return $this->status->isProcessing();
    }

    public function isRefunded(): bool
    {
        return $this->status->isRefunded();
    }

    public function isCancelled(): bool
    {
        return $this->status->isCancelled();
    }

    public function isExpired(): bool
    {
        return $this->status->isExpired();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isFinalized(): bool
    {
        return $this->status->isFinalized();
    }

    public function canBeRefunded(): bool
    {
        return $this->isSuccessful() && !$this->isRefunded() && !$this->hasChargeback();
    }

    public function canBeCancelled(): bool
    {
        return $this->isActive();
    }

    public function hasChargeback(): bool
    {
        return $this->chargebackId !== null;
    }

    public function hasDispute(): bool
    {
        return $this->disputeId !== null;
    }

    public function hasRefund(): bool
    {
        return $this->refundId !== null;
    }

    public function getNetAmount(): float
    {
        $netAmount = $this->amount;
        
        if ($this->refundAmount) {
            $netAmount -= $this->refundAmount;
        }
        
        return $netAmount;
    }

    public function getTaxAmount(): float
    {
        if (!$this->taxData) {
            return 0.0;
        }
        
        return $this->taxData['amount'] ?? 0.0;
    }

    public function getTaxRate(): float
    {
        if (!$this->taxData || $this->amount === 0) {
            return 0.0;
        }
        
        return ($this->getTaxAmount() / $this->amount) * 100;
    }

    public function getProcessingFee(): float
    {
        if (!$this->gatewayMetadata) {
            return 0.0;
        }
        
        return $this->gatewayMetadata['processing_fee'] ?? 0.0;
    }

    public function getGatewayFee(): float
    {
        if (!$this->gatewayMetadata) {
            return 0.0;
        }
        
        return $this->gatewayMetadata['gateway_fee'] ?? 0.0;
    }

    public function getTotalFees(): float
    {
        return $this->getProcessingFee() + $this->getGatewayFee();
    }

    public function getNetRevenue(): float
    {
        return $this->getNetAmount() - $this->getTotalFees();
    }

    public function isHighValue(): bool
    {
        return $this->amount >= 100.0;
    }

    public function isLowValue(): bool
    {
        return $this->amount <= 10.0;
    }

    public function isMediumValue(): bool
    {
        return $this->amount > 10.0 && $this->amount < 100.0;
    }

    public function isSubscriptionPayment(): bool
    {
        return $this->type === self::TYPE_SUBSCRIPTION || $this->type === self::TYPE_RENEWAL;
    }

    public function isPurchasePayment(): bool
    {
        return $this->type === self::TYPE_PURCHASE;
    }

    public function isUpgradePayment(): bool
    {
        return $this->type === self::TYPE_UPGRADE;
    }

    public function isDowngradePayment(): bool
    {
        return $this->type === self::TYPE_DOWNGRADE;
    }

    public function isRefundPayment(): bool
    {
        return $this->type === self::TYPE_REFUND;
    }

    public function isChargebackPayment(): bool
    {
        return $this->type === self::TYPE_CHARGEBACK;
    }

    public function isDisputePayment(): bool
    {
        return $this->type === self::TYPE_DISPUTE;
    }

    public function isStripePayment(): bool
    {
        return $this->gateway === self::GATEWAY_STRIPE;
    }

    public function isPayPalPayment(): bool
    {
        return $this->gateway === self::GATEWAY_PAYPAL;
    }

    public function isApplePayPayment(): bool
    {
        return $this->gateway === self::GATEWAY_APPLE_PAY;
    }

    public function isGooglePayPayment(): bool
    {
        return $this->gateway === self::GATEWAY_GOOGLE_PAY;
    }

    public function isCreditCardPayment(): bool
    {
        return $this->paymentMethod->type() === self::METHOD_CREDIT_CARD;
    }

    public function isDebitCardPayment(): bool
    {
        return $this->paymentMethod->type() === self::METHOD_DEBIT_CARD;
    }

    public function isDigitalWalletPayment(): bool
    {
        return $this->paymentMethod->type() === self::METHOD_DIGITAL_WALLET;
    }

    public function isBankTransferPayment(): bool
    {
        return $this->paymentMethod->type() === self::METHOD_BANK_TRANSFER;
    }

    public function getProcessingTime(): ?int
    {
        if (!$this->processedAt) {
            return null;
        }
        
        return (int) $this->createdAt->diffInSeconds($this->processedAt);
    }

    public function getFailureTime(): ?int
    {
        if (!$this->failedAt) {
            return null;
        }
        
        return (int) $this->createdAt->diffInSeconds($this->failedAt);
    }

    public function getRefundTime(): ?int
    {
        if (!$this->refundedAt) {
            return null;
        }
        
        return $this->processedAt?->diffInSeconds($this->refundedAt);
    }

    public function getChargebackTime(): ?int
    {
        if (!$this->chargebackAt) {
            return null;
        }
        
        return $this->processedAt?->diffInSeconds($this->chargebackAt);
    }

    public function getDisputeTime(): ?int
    {
        if (!$this->disputeAt) {
            return null;
        }
        
        return $this->processedAt?->diffInSeconds($this->disputeAt);
    }

    public function isRecent(): bool
    {
        return $this->createdAt->isAfter(now()->subDays(7));
    }

    public function isOld(): bool
    {
        return $this->createdAt->isBefore(now()->subMonths(6));
    }

    public function getRiskScore(): int
    {
        $score = 0;
        
        // High value payments are riskier
        if ($this->isHighValue()) {
            $score += 30;
        }
        
        // Failed payments increase risk
        if ($this->isFailed()) {
            $score += 40;
        }
        
        // Chargebacks are high risk
        if ($this->hasChargeback()) {
            $score += 50;
        }
        
        // Disputes increase risk
        if ($this->hasDispute()) {
            $score += 35;
        }
        
        // New payment methods are riskier
        if ($this->paymentMethod->isNew()) {
            $score += 20;
        }
        
        // International payments might be riskier
        if ($this->paymentMethod->isInternational()) {
            $score += 15;
        }
        
        return min($score, 100);
    }

    public function isHighRisk(): bool
    {
        return $this->getRiskScore() >= 70;
    }

    public function isMediumRisk(): bool
    {
        $score = $this->getRiskScore();
        return $score >= 40 && $score < 70;
    }

    public function isLowRisk(): bool
    {
        return $this->getRiskScore() < 40;
    }

    // State Management Methods
    public function markAsProcessing(): void
    {
        $this->status = PaymentStatus::processing();
        $this->updatedAt = now();
    }

    public function markAsCompleted(string $gatewayTransactionId, ?string $gatewayResponse = null, ?array $gatewayMetadata = null): void
    {
        $this->status = PaymentStatus::completed();
        $this->gatewayTransactionId = $gatewayTransactionId;
        $this->gatewayResponse = $gatewayResponse;
        $this->gatewayMetadata = $gatewayMetadata;
        $this->processedAt = now();
        $this->updatedAt = now();
    }

    public function markAsFailed(string $failureReason, ?string $gatewayResponse = null): void
    {
        $this->status = PaymentStatus::failed();
        $this->failureReason = $failureReason;
        $this->gatewayResponse = $gatewayResponse;
        $this->failedAt = now();
        $this->updatedAt = now();
    }

    public function markAsCancelled(?string $reason = null): void
    {
        $this->status = PaymentStatus::cancelled();
        $this->failureReason = $reason;
        $this->updatedAt = now();
    }

    public function markAsExpired(): void
    {
        $this->status = PaymentStatus::expired();
        $this->updatedAt = now();
    }

    public function processRefund(string $refundId, float $refundAmount, ?string $reason = null): void
    {
        $this->refundId = $refundId;
        $this->refundAmount = $refundAmount;
        $this->refundedAt = now();
        $this->status = PaymentStatus::refunded();
        $this->updatedAt = now();
        
        if ($reason) {
            $this->metadata = array_merge($this->metadata ?? [], ['refund_reason' => $reason]);
        }
    }

    public function processChargeback(string $chargebackId, ?string $reason = null): void
    {
        $this->chargebackId = $chargebackId;
        $this->chargebackAt = now();
        $this->updatedAt = now();
        
        if ($reason) {
            $this->metadata = array_merge($this->metadata ?? [], ['chargeback_reason' => $reason]);
        }
    }

    public function processDispute(string $disputeId, ?string $reason = null): void
    {
        $this->disputeId = $disputeId;
        $this->disputeAt = now();
        $this->updatedAt = now();
        
        if ($reason) {
            $this->metadata = array_merge($this->metadata ?? [], ['dispute_reason' => $reason]);
        }
    }

    public function updateGatewayResponse(string $response, ?array $metadata = null): void
    {
        $this->gatewayResponse = $response;
        $this->gatewayMetadata = array_merge($this->gatewayMetadata ?? [], $metadata ?? []);
        $this->updatedAt = now();
    }

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata ?? [], $metadata);
        $this->updatedAt = now();
    }

    // Conversion Methods
    public function toArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'user_id' => $this->userId->toInt(),
            'gateway' => $this->gateway,
            'payment_method' => $this->paymentMethod->toArray(),
            'status' => $this->status->toString(),
            'type' => $this->type,
            'gateway_transaction_id' => $this->gatewayTransactionId,
            'gateway_response' => $this->gatewayResponse,
            'gateway_metadata' => $this->gatewayMetadata,
            'tax_data' => $this->taxData,
            'metadata' => $this->metadata,
            'processed_at' => $this->processedAt?->toISOString(),
            'failed_at' => $this->failedAt?->toISOString(),
            'failure_reason' => $this->failureReason,
            'refund_id' => $this->refundId,
            'refund_amount' => $this->refundAmount,
            'refunded_at' => $this->refundedAt?->toISOString(),
            'chargeback_id' => $this->chargebackId,
            'chargeback_at' => $this->chargebackAt?->toISOString(),
            'dispute_id' => $this->disputeId,
            'dispute_at' => $this->disputeAt?->toISOString(),
            'net_amount' => $this->getNetAmount(),
            'tax_amount' => $this->getTaxAmount(),
            'tax_rate' => $this->getTaxRate(),
            'processing_fee' => $this->getProcessingFee(),
            'gateway_fee' => $this->getGatewayFee(),
            'total_fees' => $this->getTotalFees(),
            'net_revenue' => $this->getNetRevenue(),
            'processing_time' => $this->getProcessingTime(),
            'failure_time' => $this->getFailureTime(),
            'refund_time' => $this->getRefundTime(),
            'chargeback_time' => $this->getChargebackTime(),
            'dispute_time' => $this->getDisputeTime(),
            'risk_score' => $this->getRiskScore(),
            'is_high_risk' => $this->isHighRisk(),
            'is_medium_risk' => $this->isMediumRisk(),
            'is_low_risk' => $this->isLowRisk(),
            'is_high_value' => $this->isHighValue(),
            'is_medium_value' => $this->isMediumValue(),
            'is_low_value' => $this->isLowValue(),
            'is_successful' => $this->isSuccessful(),
            'is_failed' => $this->isFailed(),
            'is_pending' => $this->isPending(),
            'is_processing' => $this->isProcessing(),
            'is_refunded' => $this->isRefunded(),
            'is_cancelled' => $this->isCancelled(),
            'is_expired' => $this->isExpired(),
            'is_active' => $this->isActive(),
            'is_finalized' => $this->isFinalized(),
            'can_be_refunded' => $this->canBeRefunded(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'has_chargeback' => $this->hasChargeback(),
            'has_dispute' => $this->hasDispute(),
            'has_refund' => $this->hasRefund(),
            'is_subscription_payment' => $this->isSubscriptionPayment(),
            'is_purchase_payment' => $this->isPurchasePayment(),
            'is_upgrade_payment' => $this->isUpgradePayment(),
            'is_downgrade_payment' => $this->isDowngradePayment(),
            'is_refund_payment' => $this->isRefundPayment(),
            'is_chargeback_payment' => $this->isChargebackPayment(),
            'is_dispute_payment' => $this->isDisputePayment(),
            'is_stripe_payment' => $this->isStripePayment(),
            'is_paypal_payment' => $this->isPayPalPayment(),
            'is_apple_pay_payment' => $this->isApplePayPayment(),
            'is_google_pay_payment' => $this->isGooglePayPayment(),
            'is_credit_card_payment' => $this->isCreditCardPayment(),
            'is_debit_card_payment' => $this->isDebitCardPayment(),
            'is_digital_wallet_payment' => $this->isDigitalWalletPayment(),
            'is_bank_transfer_payment' => $this->isBankTransferPayment(),
            'is_recent' => $this->isRecent(),
            'is_old' => $this->isOld(),
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }

    public function toRepositoryArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'user_id' => $this->userId->toInt(),
            'gateway' => $this->gateway,
            'payment_method' => $this->paymentMethod->toRepositoryArray(),
            'status' => $this->status->toString(),
            'type' => $this->type,
            'gateway_transaction_id' => $this->gatewayTransactionId,
            'gateway_response' => $this->gatewayResponse,
            'gateway_metadata' => $this->gatewayMetadata,
            'tax_data' => $this->taxData,
            'metadata' => $this->metadata,
            'processed_at' => $this->processedAt,
            'failed_at' => $this->failedAt,
            'failure_reason' => $this->failureReason,
            'refund_id' => $this->refundId,
            'refund_amount' => $this->refundAmount,
            'refunded_at' => $this->refundedAt,
            'chargeback_id' => $this->chargebackId,
            'chargeback_at' => $this->chargebackAt,
            'dispute_id' => $this->disputeId,
            'dispute_at' => $this->disputeAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted_amount' => $this->getFormattedAmount(),
            'gateway' => $this->gateway,
            'payment_method' => $this->paymentMethod->toApiArray(),
            'status' => [
                'value' => $this->status->toString(),
                'description' => $this->status->getDescription(),
                'category' => $this->status->getCategory(),
            ],
            'type' => $this->type,
            'gateway_transaction_id' => $this->gatewayTransactionId,
            'tax_data' => $this->taxData,
            'metadata' => $this->metadata,
            'timestamps' => [
                'processed_at' => $this->processedAt?->toISOString(),
                'failed_at' => $this->failedAt?->toISOString(),
                'refunded_at' => $this->refundedAt?->toISOString(),
                'chargeback_at' => $this->chargebackAt?->toISOString(),
                'dispute_at' => $this->disputeAt?->toISOString(),
                'created_at' => $this->createdAt->toISOString(),
                'updated_at' => $this->updatedAt->toISOString(),
            ],
            'financial' => [
                'net_amount' => $this->getNetAmount(),
                'tax_amount' => $this->getTaxAmount(),
                'tax_rate' => $this->getTaxRate(),
                'processing_fee' => $this->getProcessingFee(),
                'gateway_fee' => $this->getGatewayFee(),
                'total_fees' => $this->getTotalFees(),
                'net_revenue' => $this->getNetRevenue(),
            ],
            'risk' => [
                'risk_score' => $this->getRiskScore(),
                'is_high_risk' => $this->isHighRisk(),
                'is_medium_risk' => $this->isMediumRisk(),
                'is_low_risk' => $this->isLowRisk(),
            ],
            'flags' => [
                'is_successful' => $this->isSuccessful(),
                'is_failed' => $this->isFailed(),
                'is_pending' => $this->isPending(),
                'is_processing' => $this->isProcessing(),
                'is_refunded' => $this->isRefunded(),
                'is_cancelled' => $this->isCancelled(),
                'is_expired' => $this->isExpired(),
                'is_active' => $this->isActive(),
                'is_finalized' => $this->isFinalized(),
                'can_be_refunded' => $this->canBeRefunded(),
                'can_be_cancelled' => $this->canBeCancelled(),
                'has_chargeback' => $this->hasChargeback(),
                'has_dispute' => $this->hasDispute(),
                'has_refund' => $this->hasRefund(),
            ],
            'processing' => [
                'processing_time' => $this->getProcessingTime(),
                'failure_time' => $this->getFailureTime(),
                'refund_time' => $this->getRefundTime(),
                'chargeback_time' => $this->getChargebackTime(),
                'dispute_time' => $this->getDisputeTime(),
            ],
        ];
    }

    public function getFormattedAmount(): string
    {
        return number_format($this->amount, 2) . ' ' . strtoupper($this->currency);
    }

    public function getDisplayName(): string
    {
        $displayName = $this->getFormattedAmount();
        
        if ($this->isSuccessful()) {
            $displayName .= ' (Completed)';
        } elseif ($this->isFailed()) {
            $displayName .= ' (Failed)';
        } elseif ($this->isPending()) {
            $displayName .= ' (Pending)';
        } elseif ($this->isProcessing()) {
            $displayName .= ' (Processing)';
        }
        
        return $displayName;
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }

    public function __debugInfo(): array
    {
        return [
            'id' => $this->id->toString(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'gateway' => $this->gateway,
            'status' => $this->status->toString(),
            'type' => $this->type,
            'is_successful' => $this->isSuccessful(),
            'is_failed' => $this->isFailed(),
            'risk_score' => $this->getRiskScore(),
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
