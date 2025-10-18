<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Entities;

use App\Domain\Commerce\ValueObjects\SubscriptionId;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;

/**
 * Subscription Entity
 * 
 * Core domain entity representing a subscription in the ForeverUsInLove dating application.
 * Encapsulates subscription lifecycle, billing cycles, and subscription management.
 * 
 * @package App\Domain\Commerce\Entities
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class Subscription
{
    // Subscription Statuses
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PENDING = 'pending';
    public const STATUS_TRIAL = 'trial';

    // Billing Cycles
    public const CYCLE_MONTHLY = 'monthly';
    public const CYCLE_QUARTERLY = 'quarterly';
    public const CYCLE_YEARLY = 'yearly';
    public const CYCLE_WEEKLY = 'weekly';
    public const CYCLE_DAILY = 'daily';

    // Subscription Types
    public const TYPE_BASIC = 'basic';
    public const TYPE_PREMIUM = 'premium';
    public const TYPE_VIP = 'vip';
    public const TYPE_ENTERPRISE = 'enterprise';
    public const TYPE_TRIAL = 'trial';
    public const TYPE_PROMOTIONAL = 'promotional';

    private function __construct(
        private readonly SubscriptionId $id,
        private UserId $userId,
        private string $planId,
        private string $billingCycle,
        private array $pricingDetails,
        private bool $autoRenew,
        private string $status,
        private ?Carbon $startDate,
        private ?Carbon $endDate,
        private ?Carbon $nextBillingDate,
        private ?Carbon $cancelledAt,
        private ?string $cancellationReason,
        private ?Carbon $suspendedAt,
        private ?string $suspensionReason,
        private ?array $metadata,
        private Carbon $createdAt,
        private Carbon $updatedAt
    ) {}

    public static function create(
        SubscriptionId $id,
        UserId $userId,
        string $planId,
        string $billingCycle,
        array $pricingDetails,
        bool $autoRenew = true,
        string $status = self::STATUS_ACTIVE,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?array $metadata = null
    ): self {
        $now = now();
        $startDate = $startDate ?? $now;
        
        return new self(
            id: $id,
            userId: $userId,
            planId: $planId,
            billingCycle: $billingCycle,
            pricingDetails: $pricingDetails,
            autoRenew: $autoRenew,
            status: $status,
            startDate: $startDate,
            endDate: $endDate,
            nextBillingDate: self::calculateNextBillingDate($startDate, $billingCycle),
            cancelledAt: null,
            cancellationReason: null,
            suspendedAt: null,
            suspensionReason: null,
            metadata: $metadata,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public static function fromRepositoryData(array $data): self
    {
        $subscriptionId = SubscriptionId::fromInt($data['id']);
        $userId = UserId::fromInt($data['user_id']);
        
        return new self(
            id: $subscriptionId,
            userId: $userId,
            planId: $data['plan_id'],
            billingCycle: $data['billing_cycle'],
            pricingDetails: $data['pricing_details'] ?? [],
            autoRenew: $data['auto_renew'] ?? true,
            status: $data['status'] ?? self::STATUS_ACTIVE,
            startDate: isset($data['start_date']) ? Carbon::parse($data['start_date']) : null,
            endDate: isset($data['end_date']) ? Carbon::parse($data['end_date']) : null,
            nextBillingDate: isset($data['next_billing_date']) ? Carbon::parse($data['next_billing_date']) : null,
            cancelledAt: isset($data['cancelled_at']) ? Carbon::parse($data['cancelled_at']) : null,
            cancellationReason: $data['cancellation_reason'] ?? null,
            suspendedAt: isset($data['suspended_at']) ? Carbon::parse($data['suspended_at']) : null,
            suspensionReason: $data['suspension_reason'] ?? null,
            metadata: $data['metadata'] ?? null,
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at'])
        );
    }

    // Getters
    public function getId(): SubscriptionId
    {
        return $this->id;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getPlanId(): string
    {
        return $this->planId;
    }

    public function getBillingCycle(): string
    {
        return $this->billingCycle;
    }

    public function getPricingDetails(): array
    {
        return $this->pricingDetails;
    }

    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStartDate(): ?Carbon
    {
        return $this->startDate;
    }

    public function getEndDate(): ?Carbon
    {
        return $this->endDate;
    }

    public function getNextBillingDate(): ?Carbon
    {
        return $this->nextBillingDate;
    }

    public function getCancelledAt(): ?Carbon
    {
        return $this->cancelledAt;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function getSuspendedAt(): ?Carbon
    {
        return $this->suspendedAt;
    }

    public function getSuspensionReason(): ?string
    {
        return $this->suspensionReason;
    }

    public function getMetadata(): ?array
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
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isTrial(): bool
    {
        return $this->status === self::STATUS_TRIAL;
    }

    public function isMonthly(): bool
    {
        return $this->billingCycle === self::CYCLE_MONTHLY;
    }

    public function isQuarterly(): bool
    {
        return $this->billingCycle === self::CYCLE_QUARTERLY;
    }

    public function isYearly(): bool
    {
        return $this->billingCycle === self::CYCLE_YEARLY;
    }

    public function isWeekly(): bool
    {
        return $this->billingCycle === self::CYCLE_WEEKLY;
    }

    public function isDaily(): bool
    {
        return $this->billingCycle === self::CYCLE_DAILY;
    }

    public function isBasic(): bool
    {
        return $this->planId === self::TYPE_BASIC;
    }

    public function isPremium(): bool
    {
        return $this->planId === self::TYPE_PREMIUM;
    }

    public function isVip(): bool
    {
        return $this->planId === self::TYPE_VIP;
    }

    public function isEnterprise(): bool
    {
        return $this->planId === self::TYPE_ENTERPRISE;
    }

    public function isPromotional(): bool
    {
        return $this->planId === self::TYPE_PROMOTIONAL;
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        if (!$this->endDate) {
            return false;
        }

        return $this->endDate->isBefore(now()->addDays($days));
    }


    public function canBeCancelled(): bool
    {
        return $this->isActive() && !$this->isCancelled();
    }

    public function canBeSuspended(): bool
    {
        return $this->isActive() && !$this->isSuspended();
    }

    public function canBeRenewed(): bool
    {
        return $this->isActive() && $this->autoRenew;
    }

    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->endDate) {
            return null;
        }

        return max(0, now()->diffInDays($this->endDate, false));
    }

    public function getDaysUntilNextBilling(): ?int
    {
        if (!$this->nextBillingDate) {
            return null;
        }

        return max(0, now()->diffInDays($this->nextBillingDate, false));
    }

    public function getPrice(): float
    {
        return $this->pricingDetails['price'] ?? 0.0;
    }

    public function getCurrency(): string
    {
        return $this->pricingDetails['currency'] ?? 'USD';
    }

    public function getDiscount(): float
    {
        return $this->pricingDetails['discount'] ?? 0.0;
    }

    public function getTaxRate(): float
    {
        return $this->pricingDetails['tax_rate'] ?? 0.0;
    }

    public function getTotalPrice(): float
    {
        $price = $this->getPrice();
        $discount = $this->getDiscount();
        $taxRate = $this->getTaxRate();
        
        $subtotal = $price - $discount;
        $tax = $subtotal * ($taxRate / 100);
        
        return $subtotal + $tax;
    }

    // State Management Methods
    public function activate(): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->updatedAt = now();
    }

    public function cancel(?string $reason): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelledAt = now();
        $this->cancellationReason = $reason;
        $this->autoRenew = false;
        $this->updatedAt = now();
    }

    public function suspend(?string $reason): void
    {
        $this->status = self::STATUS_SUSPENDED;
        $this->suspendedAt = now();
        $this->suspensionReason = $reason;
        $this->updatedAt = now();
    }

    public function resume(): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->suspendedAt = null;
        $this->suspensionReason = null;
        $this->updatedAt = now();
    }

    public function expire(): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->autoRenew = false;
        $this->updatedAt = now();
    }

    public function renew(): void
    {
        if (!$this->canBeRenewed()) {
            throw new \InvalidArgumentException('Subscription cannot be renewed');
        }

        $this->endDate = $this->calculateNextBillingDate($this->endDate ?? now(), $this->billingCycle);
        $this->nextBillingDate = $this->calculateNextBillingDate($this->endDate, $this->billingCycle);
        $this->updatedAt = now();
    }

    public function updatePricing(array $pricingDetails): void
    {
        $this->pricingDetails = array_merge($this->pricingDetails, $pricingDetails);
        $this->updatedAt = now();
    }

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata ?? [], $metadata);
        $this->updatedAt = now();
    }

    public function setAutoRenew(bool $autoRenew): void
    {
        $this->autoRenew = $autoRenew;
        $this->updatedAt = now();
    }

    // Utility Methods
    private static function calculateNextBillingDate(Carbon $fromDate, string $billingCycle): Carbon
    {
        return match ($billingCycle) {
            self::CYCLE_DAILY => $fromDate->copy()->addDay(),
            self::CYCLE_WEEKLY => $fromDate->copy()->addWeek(),
            self::CYCLE_MONTHLY => $fromDate->copy()->addMonth(),
            self::CYCLE_QUARTERLY => $fromDate->copy()->addMonths(3),
            self::CYCLE_YEARLY => $fromDate->copy()->addYear(),
            default => $fromDate->copy()->addMonth(),
        };
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'user_id' => $this->userId->toInt(),
            'plan_id' => $this->planId,
            'billing_cycle' => $this->billingCycle,
            'pricing_details' => $this->pricingDetails,
            'auto_renew' => $this->autoRenew,
            'status' => $this->status,
            'start_date' => $this->startDate?->toISOString(),
            'end_date' => $this->endDate?->toISOString(),
            'next_billing_date' => $this->nextBillingDate?->toISOString(),
            'cancelled_at' => $this->cancelledAt?->toISOString(),
            'cancellation_reason' => $this->cancellationReason,
            'suspended_at' => $this->suspendedAt?->toISOString(),
            'suspension_reason' => $this->suspensionReason,
            'metadata' => $this->metadata,
            'price' => $this->getPrice(),
            'currency' => $this->getCurrency(),
            'total_price' => $this->getTotalPrice(),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'days_until_next_billing' => $this->getDaysUntilNextBilling(),
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_be_suspended' => $this->canBeSuspended(),
            'can_be_renewed' => $this->canBeRenewed(),
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }

    public function toRepositoryArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'user_id' => $this->userId->toInt(),
            'plan_id' => $this->planId,
            'billing_cycle' => $this->billingCycle,
            'pricing_details' => $this->pricingDetails,
            'auto_renew' => $this->autoRenew,
            'status' => $this->status,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'next_billing_date' => $this->nextBillingDate,
            'cancelled_at' => $this->cancelledAt,
            'cancellation_reason' => $this->cancellationReason,
            'suspended_at' => $this->suspendedAt,
            'suspension_reason' => $this->suspensionReason,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'plan_id' => $this->planId,
            'billing_cycle' => $this->billingCycle,
            'status' => $this->status,
            'pricing' => [
                'price' => $this->getPrice(),
                'currency' => $this->getCurrency(),
                'discount' => $this->getDiscount(),
                'tax_rate' => $this->getTaxRate(),
                'total_price' => $this->getTotalPrice(),
            ],
            'dates' => [
                'start_date' => $this->startDate?->toISOString(),
                'end_date' => $this->endDate?->toISOString(),
                'next_billing_date' => $this->nextBillingDate?->toISOString(),
                'cancelled_at' => $this->cancelledAt?->toISOString(),
                'suspended_at' => $this->suspendedAt?->toISOString(),
            ],
            'flags' => [
                'auto_renew' => $this->autoRenew,
                'is_active' => $this->isActive(),
                'is_expired' => $this->isExpired(),
                'is_expiring_soon' => $this->isExpiringSoon(),
                'can_be_cancelled' => $this->canBeCancelled(),
                'can_be_suspended' => $this->canBeSuspended(),
                'can_be_renewed' => $this->canBeRenewed(),
            ],
            'timing' => [
                'days_until_expiration' => $this->getDaysUntilExpiration(),
                'days_until_next_billing' => $this->getDaysUntilNextBilling(),
            ],
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }

    public function __toString(): string
    {
        return "Subscription {$this->id->toString()} - {$this->planId} ({$this->status})";
    }

    public function __debugInfo(): array
    {
        return [
            'id' => $this->id->toString(),
            'plan_id' => $this->planId,
            'status' => $this->status,
            'billing_cycle' => $this->billingCycle,
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'auto_renew' => $this->autoRenew,
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
