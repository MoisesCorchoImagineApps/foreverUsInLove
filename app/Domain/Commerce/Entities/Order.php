<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Entities;

use App\Domain\Commerce\ValueObjects\OrderId;
use App\Domain\Commerce\ValueObjects\OrderStatus;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;

/**
 * Order Entity
 * 
 * Core domain entity representing an order in the ForeverUsInLove dating application.
 * Encapsulates order business logic, state management, and domain rules for all order types.
 * 
 * @package App\Domain\Commerce\Entities
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class Order
{
    // Order Types
    public const TYPE_COIN_PACKAGE = 'coin_package';
    public const TYPE_SUBSCRIPTION = 'subscription';
    public const TYPE_GIFT = 'gift';
    public const TYPE_PREMIUM_FEATURE = 'premium_feature';
    public const TYPE_SERVICE = 'service';
    public const TYPE_BUNDLE = 'bundle';

    // Order Priorities
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    // Payment Methods
    public const PAYMENT_METHOD_CREDIT_CARD = 'credit_card';
    public const PAYMENT_METHOD_DEBIT_CARD = 'debit_card';
    public const PAYMENT_METHOD_PAYPAL = 'paypal';
    public const PAYMENT_METHOD_APPLE_PAY = 'apple_pay';
    public const PAYMENT_METHOD_GOOGLE_PAY = 'google_pay';
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_METHOD_CRYPTOCURRENCY = 'cryptocurrency';

    // Delivery Methods
    public const DELIVERY_INSTANT = 'instant';
    public const DELIVERY_SCHEDULED = 'scheduled';
    public const DELIVERY_EMAIL = 'email';
    public const DELIVERY_PUSH_NOTIFICATION = 'push_notification';

    private function __construct(
        private readonly OrderId $id,
        private UserId $userId,
        private string $orderType,
        private OrderStatus $status,
        private array $items,
        private array $totals,
        private ?array $billingAddress,
        private ?array $shippingAddress,
        private ?string $giftMessage,
        private ?Carbon $scheduledDelivery,
        private string $priority,
        private string $paymentMethod,
        private string $deliveryMethod,
        private bool $isGift,
        private bool $isRecurring,
        private bool $isProcessed,
        private bool $isRefundable,
        private int $attemptCount,
        private ?string $failureReason,
        private ?Carbon $processedAt,
        private ?Carbon $deliveredAt,
        private ?Carbon $cancelledAt,
        private ?Carbon $refundedAt,
        private array $metadata,
        private Carbon $createdAt,
        private Carbon $updatedAt
    ) {}

    public static function create(
        OrderId $id,
        UserId $userId,
        string $orderType,
        OrderStatus $status,
        array $items,
        array $totals,
        string $priority = self::PRIORITY_NORMAL,
        string $paymentMethod = self::PAYMENT_METHOD_CREDIT_CARD,
        string $deliveryMethod = self::DELIVERY_INSTANT,
        ?array $billingAddress = null,
        ?array $shippingAddress = null,
        ?string $giftMessage = null,
        ?Carbon $scheduledDelivery = null,
        bool $isGift = false,
        bool $isRecurring = false,
        array $metadata = []
    ): self {
        $now = now();
        
        return new self(
            id: $id,
            userId: $userId,
            orderType: $orderType,
            status: $status,
            items: $items,
            totals: $totals,
            billingAddress: $billingAddress,
            shippingAddress: $shippingAddress,
            giftMessage: $giftMessage,
            scheduledDelivery: $scheduledDelivery,
            priority: $priority,
            paymentMethod: $paymentMethod,
            deliveryMethod: $deliveryMethod,
            isGift: $isGift,
            isRecurring: $isRecurring,
            isProcessed: false,
            isRefundable: true,
            attemptCount: 0,
            failureReason: null,
            processedAt: null,
            deliveredAt: null,
            cancelledAt: null,
            refundedAt: null,
            metadata: $metadata,
            createdAt: $now,
            updatedAt: $now
        );
    }

    // Getters
    public function getId(): OrderId
    {
        return $this->id;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getOrderType(): string
    {
        return $this->orderType;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotals(): array
    {
        return $this->totals;
    }

    public function getBillingAddress(): ?array
    {
        return $this->billingAddress;
    }

    public function getShippingAddress(): ?array
    {
        return $this->shippingAddress;
    }

    public function getGiftMessage(): ?string
    {
        return $this->giftMessage;
    }

    public function getScheduledDelivery(): ?Carbon
    {
        return $this->scheduledDelivery;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function getDeliveryMethod(): string
    {
        return $this->deliveryMethod;
    }

    public function isGift(): bool
    {
        return $this->isGift;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function isProcessed(): bool
    {
        return $this->isProcessed;
    }

    public function isRefundable(): bool
    {
        return $this->isRefundable;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getProcessedAt(): ?Carbon
    {
        return $this->processedAt;
    }

    public function getDeliveredAt(): ?Carbon
    {
        return $this->deliveredAt;
    }

    public function getCancelledAt(): ?Carbon
    {
        return $this->cancelledAt;
    }

    public function getRefundedAt(): ?Carbon
    {
        return $this->refundedAt;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
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
    public function updateStatus(OrderStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$this->status->toString()} to {$newStatus->toString()}"
            );
        }

        $this->status = $newStatus;
        $this->updatedAt = now();

        // Update timestamps based on status
        if ($newStatus->isCompleted()) {
            $this->processedAt = now();
            $this->isProcessed = true;
        } elseif ($newStatus->isCancelled()) {
            $this->cancelledAt = now();
        } elseif ($newStatus->isRefunded()) {
            $this->refundedAt = now();
        }
    }

    public function process(): void
    {
        if ($this->isProcessed) {
            throw new \InvalidArgumentException('Order is already processed');
        }

        $this->updateStatus(OrderStatus::completed());
    }

    public function cancel(?string $reason = null): void
    {
        if (!$this->status->canBeCancelled()) {
            throw new \InvalidArgumentException('Order cannot be cancelled in current status');
        }

        $this->failureReason = $reason;
        $this->updateStatus(OrderStatus::cancelled());
    }

    public function refund(): void
    {
        if (!$this->status->canBeRefunded()) {
            throw new \InvalidArgumentException('Order cannot be refunded in current status');
        }

        $this->updateStatus(OrderStatus::refunded());
    }

    public function markAsDelivered(): void
    {
        if (!$this->status->isCompleted()) {
            throw new \InvalidArgumentException('Order must be completed before marking as delivered');
        }

        $this->deliveredAt = now();
        $this->updatedAt = now();
    }

    public function incrementAttemptCount(): void
    {
        $this->attemptCount++;
        $this->updatedAt = now();
    }

    public function setFailureReason(string $reason): void
    {
        $this->failureReason = $reason;
        $this->updatedAt = now();
    }

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        $this->updatedAt = now();
    }

    public function setGiftMessage(string $message): void
    {
        $this->giftMessage = $message;
        $this->isGift = true;
        $this->updatedAt = now();
    }

    public function scheduleDelivery(Carbon $deliveryDate): void
    {
        $this->scheduledDelivery = $deliveryDate;
        $this->deliveryMethod = self::DELIVERY_SCHEDULED;
        $this->updatedAt = now();
    }

    // Status Check Methods
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

    public function isCancelled(): bool
    {
        return $this->status->isCancelled();
    }

    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    public function isRefunded(): bool
    {
        return $this->status->isRefunded();
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

    // Business Rules
    public function canBeModified(): bool
    {
        return $this->status->canBeModified();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    public function canBeRefunded(): bool
    {
        return $this->status->canBeRefunded() && $this->isRefundable;
    }

    public function requiresAttention(): bool
    {
        return $this->status->isFailed() || $this->attemptCount > 3;
    }

    public function isHighPriority(): bool
    {
        return $this->priority === self::PRIORITY_HIGH || $this->priority === self::PRIORITY_URGENT;
    }

    public function isInstantDelivery(): bool
    {
        return $this->deliveryMethod === self::DELIVERY_INSTANT;
    }

    public function isScheduledDelivery(): bool
    {
        return $this->deliveryMethod === self::DELIVERY_SCHEDULED;
    }

    // Calculation Methods
    public function getTotalAmount(): float
    {
        return $this->totals['total'] ?? 0.0;
    }

    public function getSubtotal(): float
    {
        return $this->totals['subtotal'] ?? 0.0;
    }

    public function getTaxAmount(): float
    {
        return $this->totals['tax'] ?? 0.0;
    }

    public function getDiscountAmount(): float
    {
        return $this->totals['discount'] ?? 0.0;
    }

    public function getShippingCost(): float
    {
        return $this->totals['shipping'] ?? 0.0;
    }

    public function getItemCount(): int
    {
        return count($this->items);
    }

    // Utility Methods
    public function toArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'user_id' => $this->userId->toInt(),
            'order_type' => $this->orderType,
            'status' => $this->status->toString(),
            'items' => $this->items,
            'totals' => $this->totals,
            'billing_address' => $this->billingAddress,
            'shipping_address' => $this->shippingAddress,
            'gift_message' => $this->giftMessage,
            'scheduled_delivery' => $this->scheduledDelivery?->toISOString(),
            'priority' => $this->priority,
            'payment_method' => $this->paymentMethod,
            'delivery_method' => $this->deliveryMethod,
            'is_gift' => $this->isGift,
            'is_recurring' => $this->isRecurring,
            'is_processed' => $this->isProcessed,
            'is_refundable' => $this->isRefundable,
            'attempt_count' => $this->attemptCount,
            'failure_reason' => $this->failureReason,
            'processed_at' => $this->processedAt?->toISOString(),
            'delivered_at' => $this->deliveredAt?->toISOString(),
            'cancelled_at' => $this->cancelledAt?->toISOString(),
            'refunded_at' => $this->refundedAt?->toISOString(),
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }

    public function getHash(): string
    {
        return 'order_' . $this->id->toInt() . '_' . substr(md5($this->id->toString()), 0, 8);
    }
}
