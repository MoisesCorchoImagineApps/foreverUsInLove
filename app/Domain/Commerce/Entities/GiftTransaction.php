<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Entities;

use App\Domain\Commerce\ValueObjects\GiftId;
use App\Domain\Commerce\ValueObjects\TransactionStatus;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Commerce\Entities\Gift;
use Carbon\Carbon;

/**
 * GiftTransaction Entity
 * 
 * Core domain entity representing a gift transaction in the ForeverUsInLove dating application.
 * Encapsulates gift sending/receiving transactions, delivery tracking, and gift transaction lifecycle.
 * 
 * @package App\Domain\Commerce\Entities
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class GiftTransaction
{
    // Transaction Types
    public const TYPE_SEND = 'send';
    public const TYPE_RECEIVE = 'receive';
    public const TYPE_REFUND = 'refund';
    public const TYPE_EXPIRED = 'expired';

    // Delivery Status
    public const DELIVERY_PENDING = 'pending';
    public const DELIVERY_SENT = 'sent';
    public const DELIVERY_DELIVERED = 'delivered';
    public const DELIVERY_FAILED = 'failed';
    public const DELIVERY_CANCELLED = 'cancelled';

    // Gift Categories
    public const CATEGORY_VIRTUAL = 'virtual';
    public const CATEGORY_PHYSICAL = 'physical';
    public const CATEGORY_EXPERIENCE = 'experience';
    public const CATEGORY_DIGITAL = 'digital';

    private function __construct(
        private readonly int $id,
        private GiftId $giftId,
        private UserId $senderId,
        private UserId $recipientId,
        private array $paymentData,
        private TransactionStatus $status,
        private string $type,
        private ?string $message,
        private string $deliveryStatus,
        private ?Carbon $deliveredAt,
        private ?Carbon $expiresAt,
        private ?string $failureReason,
        private ?array $metadata,
        private Carbon $createdAt,
        private Carbon $updatedAt
    ) {}

    public static function create(
        int $id,
        GiftId $giftId,
        UserId $senderId,
        UserId $recipientId,
        array $paymentData,
        TransactionStatus $status,
        string $type = self::TYPE_SEND,
        ?string $message = null,
        ?Carbon $expiresAt = null,
        ?array $metadata = null
    ): self {
        $now = now();
        
        return new self(
            id: $id,
            giftId: $giftId,
            senderId: $senderId,
            recipientId: $recipientId,
            paymentData: $paymentData,
            status: $status,
            type: $type,
            message: $message,
            deliveryStatus: self::DELIVERY_PENDING,
            deliveredAt: null,
            expiresAt: $expiresAt,
            failureReason: null,
            metadata: $metadata,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public static function fromRepositoryData(array $data): self
    {
        $giftId = GiftId::fromInt($data['gift_id']);
        $senderId = UserId::fromInt($data['sender_id']);
        $recipientId = UserId::fromInt($data['recipient_id']);
        $status = TransactionStatus::fromString($data['status']);
        
        return new self(
            id: $data['id'],
            giftId: $giftId,
            senderId: $senderId,
            recipientId: $recipientId,
            paymentData: $data['payment_data'] ?? [],
            status: $status,
            type: $data['type'] ?? self::TYPE_SEND,
            message: $data['message'] ?? null,
            deliveryStatus: $data['delivery_status'] ?? self::DELIVERY_PENDING,
            deliveredAt: isset($data['delivered_at']) ? Carbon::parse($data['delivered_at']) : null,
            expiresAt: isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            failureReason: $data['failure_reason'] ?? null,
            metadata: $data['metadata'] ?? null,
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at'])
        );
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getGiftId(): GiftId
    {
        return $this->giftId;
    }

    public function getSenderId(): UserId
    {
        return $this->senderId;
    }

    public function getRecipientId(): UserId
    {
        return $this->recipientId;
    }

    public function getPaymentData(): array
    {
        return $this->paymentData;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Get personal message (alias for getMessage)
     */
    public function getPersonalMessage(): ?string
    {
        return $this->message;
    }

    public function getDeliveryStatus(): string
    {
        return $this->deliveryStatus;
    }

    /**
     * Get delivery method
     */
    public function getDeliveryMethod(): string
    {
        return $this->metadata['delivery_method'] ?? 'instant';
    }

    public function getDeliveredAt(): ?Carbon
    {
        return $this->deliveredAt;
    }

    /**
     * Get scheduled delivery date
     */
    public function getScheduledDelivery(): ?Carbon
    {
        return $this->metadata['scheduled_delivery'] ?? null;
    }

    public function getExpiresAt(): ?Carbon
    {
        return $this->expiresAt;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * Check if gift is sent anonymously
     */
    public function isAnonymous(): bool
    {
        return $this->metadata['is_anonymous'] ?? false;
    }

    /**
     * Get the Gift entity associated with this transaction
     * Note: This method requires dependency injection of GiftService or ProductRepository
     * For now, returns null - should be implemented with proper service injection
     */
    public function getGift(): ?Gift
    {
        // This method should be implemented with proper service injection
        // For now, return null to avoid circular dependencies
        return null;
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
    public function isSend(): bool
    {
        return $this->type === self::TYPE_SEND;
    }

    public function isReceive(): bool
    {
        return $this->type === self::TYPE_RECEIVE;
    }

    public function isRefund(): bool
    {
        return $this->type === self::TYPE_REFUND;
    }

    public function isExpired(): bool
    {
        return $this->type === self::TYPE_EXPIRED;
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isProcessing(): bool
    {
        return $this->status->isProcessing();
    }

    public function isCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    public function isCancelled(): bool
    {
        return $this->status->isCancelled();
    }

    public function isRefunded(): bool
    {
        return $this->status->isRefunded();
    }

    public function isTransactionExpired(): bool
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

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function isUnsuccessful(): bool
    {
        return $this->status->isUnsuccessful();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    public function canBeRefunded(): bool
    {
        return $this->status->canBeRefunded();
    }

    public function canBeModified(): bool
    {
        return $this->status->canBeModified();
    }

    // Delivery Status Methods
    public function isDeliveryPending(): bool
    {
        return $this->deliveryStatus === self::DELIVERY_PENDING;
    }

    public function isDeliverySent(): bool
    {
        return $this->deliveryStatus === self::DELIVERY_SENT;
    }

    public function isDeliveryDelivered(): bool
    {
        return $this->deliveryStatus === self::DELIVERY_DELIVERED;
    }

    public function isDeliveryFailed(): bool
    {
        return $this->deliveryStatus === self::DELIVERY_FAILED;
    }

    public function isDeliveryCancelled(): bool
    {
        return $this->deliveryStatus === self::DELIVERY_CANCELLED;
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        if (!$this->expiresAt) {
            return false;
        }

        return $this->expiresAt->isBefore(now()->addDays($days));
    }

    public function hasExpired(): bool
    {
        if (!$this->expiresAt) {
            return false;
        }

        return $this->expiresAt->isPast();
    }

    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->expiresAt) {
            return null;
        }

        return max(0, now()->diffInDays($this->expiresAt, false));
    }

    public function getDeliveryTime(): ?int
    {
        if (!$this->deliveredAt) {
            return null;
        }

        return (int) $this->createdAt->diffInSeconds($this->deliveredAt);
    }

    public function getAmount(): float
    {
        return $this->paymentData['amount'] ?? 0.0;
    }

    public function getCurrency(): string
    {
        return $this->paymentData['currency'] ?? 'USD';
    }

    public function getFormattedAmount(): string
    {
        return number_format($this->getAmount(), 2) . ' ' . strtoupper($this->getCurrency());
    }

    public function isHighValue(): bool
    {
        return $this->getAmount() >= 50.0;
    }

    public function isMediumValue(): bool
    {
        $amount = $this->getAmount();
        return $amount >= 10.0 && $amount < 50.0;
    }

    public function isLowValue(): bool
    {
        return $this->getAmount() < 10.0;
    }

    public function isRecent(): bool
    {
        return $this->createdAt->isAfter(now()->subDays(7));
    }

    public function isOld(): bool
    {
        return $this->createdAt->isBefore(now()->subMonths(6));
    }

    // State Management Methods
    public function markAsProcessing(): void
    {
        $this->status = TransactionStatus::processing();
        $this->updatedAt = now();
    }

    public function markAsCompleted(): void
    {
        $this->status = TransactionStatus::completed();
        $this->updatedAt = now();
    }

    public function markAsFailed(?string $reason): void
    {
        $this->status = TransactionStatus::failed();
        $this->failureReason = $reason;
        $this->deliveryStatus = self::DELIVERY_FAILED;
        $this->updatedAt = now();
    }

    public function markAsCancelled(?string $reason): void
    {
        $this->status = TransactionStatus::cancelled();
        $this->failureReason = $reason;
        $this->deliveryStatus = self::DELIVERY_CANCELLED;
        $this->updatedAt = now();
    }

    public function markAsRefunded(): void
    {
        $this->status = TransactionStatus::refunded();
        $this->updatedAt = now();
    }

    public function markAsExpired(): void
    {
        $this->status = TransactionStatus::expired();
        $this->updatedAt = now();
    }

    public function markAsSent(): void
    {
        $this->deliveryStatus = self::DELIVERY_SENT;
        $this->updatedAt = now();
    }

    public function markAsDelivered(): void
    {
        $this->deliveryStatus = self::DELIVERY_DELIVERED;
        $this->deliveredAt = now();
        $this->updatedAt = now();
    }

    public function updateMessage(string $message): void
    {
        $this->message = $message;
        $this->updatedAt = now();
    }

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata ?? [], $metadata);
        $this->updatedAt = now();
    }

    public function setExpirationDate(Carbon $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
        $this->updatedAt = now();
    }

    // Utility Methods
    public function getDisplayName(): string
    {
        $displayName = ucfirst(str_replace('_', ' ', $this->type));
        
        if ($this->message) {
            $displayName .= ' with message';
        }
        
        return $displayName;
    }

    public function getRiskScore(): int
    {
        $score = 0;
        
        // High value gifts are riskier
        if ($this->isHighValue()) {
            $score += 30;
        }
        
        // Failed transactions increase risk
        if ($this->isFailed()) {
            $score += 40;
        }
        
        // Recent transactions might be riskier
        if ($this->isRecent()) {
            $score += 10;
        }
        
        // Delivery failures increase risk
        if ($this->isDeliveryFailed()) {
            $score += 25;
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gift_id' => $this->giftId->toInt(),
            'sender_id' => $this->senderId->toInt(),
            'recipient_id' => $this->recipientId->toInt(),
            'payment_data' => $this->paymentData,
            'status' => $this->status->toString(),
            'type' => $this->type,
            'message' => $this->message,
            'delivery_status' => $this->deliveryStatus,
            'delivered_at' => $this->deliveredAt?->toISOString(),
            'expires_at' => $this->expiresAt?->toISOString(),
            'failure_reason' => $this->failureReason,
            'metadata' => $this->metadata,
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrency(),
            'formatted_amount' => $this->getFormattedAmount(),
            'display_name' => $this->getDisplayName(),
            'is_send' => $this->isSend(),
            'is_receive' => $this->isReceive(),
            'is_refund' => $this->isRefund(),
            'is_expired' => $this->isExpired(),
            'is_pending' => $this->isPending(),
            'is_processing' => $this->isProcessing(),
            'is_completed' => $this->isCompleted(),
            'is_failed' => $this->isFailed(),
            'is_cancelled' => $this->isCancelled(),
            'is_refunded' => $this->isRefunded(),
            'is_transaction_expired' => $this->isTransactionExpired(),
            'is_active' => $this->isActive(),
            'is_finalized' => $this->isFinalized(),
            'is_successful' => $this->isSuccessful(),
            'is_unsuccessful' => $this->isUnsuccessful(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_be_refunded' => $this->canBeRefunded(),
            'can_be_modified' => $this->canBeModified(),
            'is_delivery_pending' => $this->isDeliveryPending(),
            'is_delivery_sent' => $this->isDeliverySent(),
            'is_delivery_delivered' => $this->isDeliveryDelivered(),
            'is_delivery_failed' => $this->isDeliveryFailed(),
            'is_delivery_cancelled' => $this->isDeliveryCancelled(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'has_expired' => $this->hasExpired(),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'delivery_time' => $this->getDeliveryTime(),
            'is_high_value' => $this->isHighValue(),
            'is_medium_value' => $this->isMediumValue(),
            'is_low_value' => $this->isLowValue(),
            'is_recent' => $this->isRecent(),
            'is_old' => $this->isOld(),
            'risk_score' => $this->getRiskScore(),
            'is_high_risk' => $this->isHighRisk(),
            'is_medium_risk' => $this->isMediumRisk(),
            'is_low_risk' => $this->isLowRisk(),
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }

    public function toRepositoryArray(): array
    {
        return [
            'id' => $this->id,
            'gift_id' => $this->giftId->toInt(),
            'sender_id' => $this->senderId->toInt(),
            'recipient_id' => $this->recipientId->toInt(),
            'payment_data' => $this->paymentData,
            'status' => $this->status->toString(),
            'type' => $this->type,
            'message' => $this->message,
            'delivery_status' => $this->deliveryStatus,
            'delivered_at' => $this->deliveredAt,
            'expires_at' => $this->expiresAt,
            'failure_reason' => $this->failureReason,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'gift_id' => $this->giftId->toString(),
            'sender_id' => $this->senderId->toString(),
            'recipient_id' => $this->recipientId->toString(),
            'status' => [
                'value' => $this->status->toString(),
                'description' => $this->status->getDescription(),
                'category' => $this->status->getCategory(),
            ],
            'type' => $this->type,
            'message' => $this->message,
            'delivery_status' => $this->deliveryStatus,
            'payment' => [
                'amount' => $this->getAmount(),
                'currency' => $this->getCurrency(),
                'formatted_amount' => $this->getFormattedAmount(),
            ],
            'timestamps' => [
                'delivered_at' => $this->deliveredAt?->toISOString(),
                'expires_at' => $this->expiresAt?->toISOString(),
                'created_at' => $this->createdAt->toISOString(),
                'updated_at' => $this->updatedAt->toISOString(),
            ],
            'flags' => [
                'is_send' => $this->isSend(),
                'is_receive' => $this->isReceive(),
                'is_refund' => $this->isRefund(),
                'is_expired' => $this->isExpired(),
                'is_pending' => $this->isPending(),
                'is_processing' => $this->isProcessing(),
                'is_completed' => $this->isCompleted(),
                'is_failed' => $this->isFailed(),
                'is_cancelled' => $this->isCancelled(),
                'is_refunded' => $this->isRefunded(),
                'is_active' => $this->isActive(),
                'is_finalized' => $this->isFinalized(),
                'is_successful' => $this->isSuccessful(),
                'can_be_cancelled' => $this->canBeCancelled(),
                'can_be_refunded' => $this->canBeRefunded(),
                'can_be_modified' => $this->canBeModified(),
            ],
            'delivery' => [
                'status' => $this->deliveryStatus,
                'is_pending' => $this->isDeliveryPending(),
                'is_sent' => $this->isDeliverySent(),
                'is_delivered' => $this->isDeliveryDelivered(),
                'is_failed' => $this->isDeliveryFailed(),
                'is_cancelled' => $this->isDeliveryCancelled(),
                'delivery_time' => $this->getDeliveryTime(),
            ],
            'value' => [
                'is_high_value' => $this->isHighValue(),
                'is_medium_value' => $this->isMediumValue(),
                'is_low_value' => $this->isLowValue(),
            ],
            'timing' => [
                'is_expiring_soon' => $this->isExpiringSoon(),
                'has_expired' => $this->hasExpired(),
                'days_until_expiration' => $this->getDaysUntilExpiration(),
                'is_recent' => $this->isRecent(),
                'is_old' => $this->isOld(),
            ],
            'risk' => [
                'risk_score' => $this->getRiskScore(),
                'is_high_risk' => $this->isHighRisk(),
                'is_medium_risk' => $this->isMediumRisk(),
                'is_low_risk' => $this->isLowRisk(),
            ],
            'display' => [
                'display_name' => $this->getDisplayName(),
            ],
            'failure_reason' => $this->failureReason,
            'metadata' => $this->metadata,
        ];
    }

    public function __toString(): string
    {
        return "GiftTransaction {$this->id} - {$this->getFormattedAmount()} ({$this->status->toString()})";
    }

    public function __debugInfo(): array
    {
        return [
            'id' => $this->id,
            'gift_id' => $this->giftId->toString(),
            'sender_id' => $this->senderId->toString(),
            'recipient_id' => $this->recipientId->toString(),
            'type' => $this->type,
            'status' => $this->status->toString(),
            'delivery_status' => $this->deliveryStatus,
            'amount' => $this->getAmount(),
            'is_completed' => $this->isCompleted(),
            'is_failed' => $this->isFailed(),
            'risk_score' => $this->getRiskScore(),
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
