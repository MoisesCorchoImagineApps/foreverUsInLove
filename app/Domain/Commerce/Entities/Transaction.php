<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Entities;

use App\Domain\Commerce\ValueObjects\TransactionType;
use App\Domain\Commerce\ValueObjects\TransactionStatus;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;

/**
 * Transaction Entity
 * 
 * Core domain entity representing a transaction in the ForeverUsInLove dating application.
 * Encapsulates transaction processing, status management, and transaction lifecycle.
 * 
 * @package App\Domain\Commerce\Entities
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class Transaction
{
    // Transaction Types
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_REFUND = 'refund';
    public const TYPE_CHARGEBACK = 'chargeback';
    public const TYPE_DISPUTE = 'dispute';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_FEE = 'fee';
    public const TYPE_COMMISSION = 'commission';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_PENALTY = 'penalty';

    // Transaction Statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_EXPIRED = 'expired';

    private function __construct(
        private readonly string $id,
        private float $amount,
        private string $currency,
        private UserId $userId,
        private string $type,
        private string $status,
        private ?string $paymentId,
        private ?string $orderId,
        private ?string $gatewayTransactionId,
        private ?string $gatewayResponse,
        private ?array $gatewayMetadata,
        private ?string $description,
        private ?array $metadata,
        private ?Carbon $processedAt,
        private ?Carbon $failedAt,
        private ?string $failureReason,
        private Carbon $createdAt,
        private Carbon $updatedAt
    ) {}

    public static function create(
        string $id,
        float $amount,
        string $currency,
        UserId $userId,
        string $type,
        string $status = self::STATUS_PENDING,
        ?string $paymentId = null,
        ?string $orderId = null,
        ?string $gatewayTransactionId = null,
        ?string $gatewayResponse = null,
        ?array $gatewayMetadata = null,
        ?string $description = null,
        ?array $metadata = null
    ): self {
        $now = now();
        
        return new self(
            id: $id,
            amount: $amount,
            currency: $currency,
            userId: $userId,
            type: $type,
            status: $status,
            paymentId: $paymentId,
            orderId: $orderId,
            gatewayTransactionId: $gatewayTransactionId,
            gatewayResponse: $gatewayResponse,
            gatewayMetadata: $gatewayMetadata,
            description: $description,
            metadata: $metadata,
            processedAt: null,
            failedAt: null,
            failureReason: null,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public static function fromRepositoryData(array $data): self
    {
        $userId = UserId::fromInt($data['user_id']);

        return new self(
            id: $data['id'],
            amount: $data['amount'],
            currency: $data['currency'],
            userId: $userId,
            type: $data['type'],
            status: $data['status'],
            paymentId: $data['payment_id'] ?? null,
            orderId: $data['order_id'] ?? null,
            gatewayTransactionId: $data['gateway_transaction_id'] ?? null,
            gatewayResponse: $data['gateway_response'] ?? null,
            gatewayMetadata: $data['gateway_metadata'] ?? null,
            description: $data['description'] ?? null,
            metadata: $data['metadata'] ?? null,
            processedAt: isset($data['processed_at']) ? Carbon::parse($data['processed_at']) : null,
            failedAt: isset($data['failed_at']) ? Carbon::parse($data['failed_at']) : null,
            failureReason: $data['failure_reason'] ?? null,
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at'])
        );
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function getGatewayTransactionId(): ?string
    {
        return $this->gatewayTransactionId;
    }

    public function getGatewayResponse(): ?string
    {
        return $this->gatewayResponse;
    }

    public function getGatewayMetadata(): ?array
    {
        return $this->gatewayMetadata;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getProcessedAt(): ?Carbon
    {
        return $this->processedAt;
    }

    public function getFailedAt(): ?Carbon
    {
        return $this->failedAt;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getCreatedAt(): Carbon
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): Carbon
    {
        return $this->updatedAt;
    }

    // Business Logic Methods
    public function isPayment(): bool
    {
        return $this->type === self::TYPE_PAYMENT;
    }

    public function isRefund(): bool
    {
        return $this->type === self::TYPE_REFUND;
    }

    public function isChargeback(): bool
    {
        return $this->type === self::TYPE_CHARGEBACK;
    }

    public function isDispute(): bool
    {
        return $this->type === self::TYPE_DISPUTE;
    }

    public function isAdjustment(): bool
    {
        return $this->type === self::TYPE_ADJUSTMENT;
    }

    public function isFee(): bool
    {
        return $this->type === self::TYPE_FEE;
    }

    public function isCommission(): bool
    {
        return $this->type === self::TYPE_COMMISSION;
    }

    public function isBonus(): bool
    {
        return $this->type === self::TYPE_BONUS;
    }

    public function isPenalty(): bool
    {
        return $this->type === self::TYPE_PENALTY;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isActive(): bool
    {
        return $this->isPending() || $this->isProcessing();
    }

    public function isFinalized(): bool
    {
        return $this->isCompleted() || $this->isFailed() || $this->isCancelled() || 
               $this->isReversed() || $this->isExpired();
    }

    public function isSuccessful(): bool
    {
        return $this->isCompleted();
    }

    public function isUnsuccessful(): bool
    {
        return $this->isFailed() || $this->isCancelled() || $this->isExpired();
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending() || $this->isProcessing();
    }

    public function canBeReversed(): bool
    {
        return $this->isCompleted();
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

    public function isRecent(): bool
    {
        return $this->createdAt->isAfter(now()->subDays(7));
    }

    public function isOld(): bool
    {
        return $this->createdAt->isBefore(now()->subMonths(6));
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

    public function getFormattedAmount(): string
    {
        return number_format($this->amount, 2) . ' ' . strtoupper($this->currency);
    }

    public function getDisplayName(): string
    {
        $displayName = $this->getFormattedAmount();
        
        if ($this->isCompleted()) {
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

    // State Management Methods
    public function markAsProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->updatedAt = now();
    }

    public function markAsCompleted(?string $gatewayTransactionId = null, ?string $gatewayResponse = null, ?array $gatewayMetadata = null): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->gatewayTransactionId = $gatewayTransactionId;
        $this->gatewayResponse = $gatewayResponse;
        $this->gatewayMetadata = $gatewayMetadata;
        $this->processedAt = now();
        $this->updatedAt = now();
    }

    public function markAsFailed(string $failureReason, ?string $gatewayResponse = null): void
    {
        $this->status = self::STATUS_FAILED;
        $this->failureReason = $failureReason;
        $this->gatewayResponse = $gatewayResponse;
        $this->failedAt = now();
        $this->updatedAt = now();
    }

    public function markAsCancelled(?string $reason = null): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->failureReason = $reason;
        $this->updatedAt = now();
    }

    public function markAsReversed(?string $reason = null): void
    {
        $this->status = self::STATUS_REVERSED;
        $this->failureReason = $reason;
        $this->updatedAt = now();
    }

    public function markAsExpired(): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->updatedAt = now();
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
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'user_id' => $this->userId->toInt(),
            'type' => $this->type,
            'status' => $this->status,
            'payment_id' => $this->paymentId,
            'order_id' => $this->orderId,
            'gateway_transaction_id' => $this->gatewayTransactionId,
            'gateway_response' => $this->gatewayResponse,
            'gateway_metadata' => $this->gatewayMetadata,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'processed_at' => $this->processedAt?->toISOString(),
            'failed_at' => $this->failedAt?->toISOString(),
            'failure_reason' => $this->failureReason,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
            'formatted_amount' => $this->getFormattedAmount(),
            'display_name' => $this->getDisplayName(),
            'processing_time' => $this->getProcessingTime(),
            'failure_time' => $this->getFailureTime(),
            'is_high_value' => $this->isHighValue(),
            'is_medium_value' => $this->isMediumValue(),
            'is_low_value' => $this->isLowValue(),
            'is_successful' => $this->isSuccessful(),
            'is_failed' => $this->isFailed(),
            'is_pending' => $this->isPending(),
            'is_processing' => $this->isProcessing(),
            'is_cancelled' => $this->isCancelled(),
            'is_reversed' => $this->isReversed(),
            'is_expired' => $this->isExpired(),
            'is_active' => $this->isActive(),
            'is_finalized' => $this->isFinalized(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_be_reversed' => $this->canBeReversed(),
            'is_recent' => $this->isRecent(),
            'is_old' => $this->isOld(),
        ];
    }

    public function toRepositoryArray(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'user_id' => $this->userId->toInt(),
            'type' => $this->type,
            'status' => $this->status,
            'payment_id' => $this->paymentId,
            'order_id' => $this->orderId,
            'gateway_transaction_id' => $this->gatewayTransactionId,
            'gateway_response' => $this->gatewayResponse,
            'gateway_metadata' => $this->gatewayMetadata,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'processed_at' => $this->processedAt,
            'failed_at' => $this->failedAt,
            'failure_reason' => $this->failureReason,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted_amount' => $this->getFormattedAmount(),
            'type' => $this->type,
            'status' => [
                'value' => $this->status,
                'description' => $this->getStatusDescription(),
                'category' => $this->getStatusCategory(),
            ],
            'payment_id' => $this->paymentId,
            'order_id' => $this->orderId,
            'gateway_transaction_id' => $this->gatewayTransactionId,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'timestamps' => [
                'processed_at' => $this->processedAt?->toISOString(),
                'failed_at' => $this->failedAt?->toISOString(),
                'created_at' => $this->createdAt->toISOString(),
                'updated_at' => $this->updatedAt->toISOString(),
            ],
            'flags' => [
                'is_successful' => $this->isSuccessful(),
                'is_failed' => $this->isFailed(),
                'is_pending' => $this->isPending(),
                'is_processing' => $this->isProcessing(),
                'is_cancelled' => $this->isCancelled(),
                'is_reversed' => $this->isReversed(),
                'is_expired' => $this->isExpired(),
                'is_active' => $this->isActive(),
                'is_finalized' => $this->isFinalized(),
                'can_be_cancelled' => $this->canBeCancelled(),
                'can_be_reversed' => $this->canBeReversed(),
            ],
            'processing' => [
                'processing_time' => $this->getProcessingTime(),
                'failure_time' => $this->getFailureTime(),
            ],
        ];
    }

    private function getStatusDescription(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Transaction pending',
            self::STATUS_PROCESSING => 'Transaction processing',
            self::STATUS_COMPLETED => 'Transaction completed',
            self::STATUS_FAILED => 'Transaction failed',
            self::STATUS_CANCELLED => 'Transaction cancelled',
            self::STATUS_REVERSED => 'Transaction reversed',
            self::STATUS_EXPIRED => 'Transaction expired',
            default => 'Unknown status',
        };
    }

    private function getStatusCategory(): string
    {
        if ($this->isActive()) {
            return 'active';
        }

        if ($this->isSuccessful()) {
            return 'successful';
        }

        if ($this->isUnsuccessful()) {
            return 'unsuccessful';
        }

        return 'finalized';
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }

    public function __debugInfo(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'type' => $this->type,
            'status' => $this->status,
            'is_successful' => $this->isSuccessful(),
            'is_failed' => $this->isFailed(),
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
