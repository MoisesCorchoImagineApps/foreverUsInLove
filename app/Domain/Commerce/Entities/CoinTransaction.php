<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Entities;

use App\Domain\Commerce\ValueObjects\TransactionStatus;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;

/**
 * CoinTransaction Entity
 * 
 * Core domain entity representing a coin transaction in the ForeverUsInLove dating application.
 * Encapsulates virtual currency transactions, coin balance management, and transaction lifecycle.
 * 
 * @package App\Domain\Commerce\Entities
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class CoinTransaction
{
    // Transaction Types
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_EARNED = 'earned';
    public const TYPE_SPENT = 'spent';
    public const TYPE_REFUND = 'refund';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_PENALTY = 'penalty';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_EXPIRED = 'expired';

    // Transaction Sources
    public const SOURCE_PURCHASE = 'purchase';
    public const SOURCE_DAILY_LOGIN = 'daily_login';
    public const SOURCE_PROFILE_COMPLETION = 'profile_completion';
    public const SOURCE_REFERRAL = 'referral';
    public const SOURCE_PREMIUM_FEATURE = 'premium_feature';
    public const SOURCE_GIFT_SENDING = 'gift_sending';
    public const SOURCE_SUPER_LIKE = 'super_like';
    public const SOURCE_BOOST = 'boost';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_SYSTEM = 'system';

    private function __construct(
        private readonly int $id,
        private UserId $userId,
        private int $amount,
        private string $type,
        private string $source,
        private TransactionStatus $status,
        private ?string $referenceId,
        private ?string $description,
        private ?array $metadata,
        private ?Carbon $processedAt,
        private ?Carbon $expiresAt,
        private Carbon $createdAt,
        private Carbon $updatedAt
    ) {}

    public static function create(
        int $id,
        UserId $userId,
        int $amount,
        string $type,
        string $source,
        TransactionStatus $status,
        ?string $referenceId = null,
        ?string $description = null,
        ?array $metadata = null,
        ?Carbon $expiresAt = null
    ): self {
        $now = now();
        
        return new self(
            id: $id,
            userId: $userId,
            amount: $amount,
            type: $type,
            source: $source,
            status: $status,
            referenceId: $referenceId,
            description: $description,
            metadata: $metadata,
            processedAt: null,
            expiresAt: $expiresAt,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public static function fromRepositoryData(array $data): self
    {
        $userId = UserId::fromInt($data['user_id']);
        $status = TransactionStatus::fromString($data['status']);
        
        return new self(
            id: $data['id'],
            userId: $userId,
            amount: $data['amount'],
            type: $data['type'],
            source: $data['source'],
            status: $status,
            referenceId: $data['reference_id'] ?? null,
            description: $data['description'] ?? null,
            metadata: $data['metadata'] ?? null,
            processedAt: isset($data['processed_at']) ? Carbon::parse($data['processed_at']) : null,
            expiresAt: isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at'])
        );
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
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

    public function getExpiresAt(): ?Carbon
    {
        return $this->expiresAt;
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
    public function isPurchase(): bool
    {
        return $this->type === self::TYPE_PURCHASE;
    }

    public function isEarned(): bool
    {
        return $this->type === self::TYPE_EARNED;
    }

    public function isSpent(): bool
    {
        return $this->type === self::TYPE_SPENT;
    }

    public function isRefund(): bool
    {
        return $this->type === self::TYPE_REFUND;
    }

    public function isBonus(): bool
    {
        return $this->type === self::TYPE_BONUS;
    }

    public function isPenalty(): bool
    {
        return $this->type === self::TYPE_PENALTY;
    }

    public function isTransfer(): bool
    {
        return $this->type === self::TYPE_TRANSFER;
    }

    public function isExpired(): bool
    {
        return $this->type === self::TYPE_EXPIRED;
    }

    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    public function isNeutral(): bool
    {
        return $this->amount === 0;
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

    public function getProcessingTime(): ?int
    {
        if (!$this->processedAt) {
            return null;
        }

        return (int) $this->createdAt->diffInSeconds($this->processedAt);
    }

    public function isHighValue(): bool
    {
        return abs($this->amount) >= 1000;
    }

    public function isMediumValue(): bool
    {
        $absAmount = abs($this->amount);
        return $absAmount >= 100 && $absAmount < 1000;
    }

    public function isLowValue(): bool
    {
        return abs($this->amount) < 100;
    }

    public function isRecent(): bool
    {
        return $this->createdAt->isAfter(now()->subDays(7));
    }

    public function isOld(): bool
    {
        return $this->createdAt->isBefore(now()->subMonths(6));
    }

    public function getAbsoluteAmount(): int
    {
        return abs($this->amount);
    }

    public function getFormattedAmount(): string
    {
        $sign = $this->amount >= 0 ? '+' : '-';
        return $sign . number_format($this->getAbsoluteAmount()) . ' coins';
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
        $this->processedAt = now();
        $this->updatedAt = now();
    }

    public function markAsFailed(?string $reason): void
    {
        $this->status = TransactionStatus::failed();
        $this->updatedAt = now();
        
        if ($reason) {
            $this->metadata = array_merge($this->metadata ?? [], ['failure_reason' => $reason]);
        }
    }

    public function markAsCancelled(?string $reason): void
    {
        $this->status = TransactionStatus::cancelled();
        $this->updatedAt = now();
        
        if ($reason) {
            $this->metadata = array_merge($this->metadata ?? [], ['cancellation_reason' => $reason]);
        }
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

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata ?? [], $metadata);
        $this->updatedAt = now();
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
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
        
        if ($this->source !== self::SOURCE_PURCHASE) {
            $displayName .= ' (' . ucfirst(str_replace('_', ' ', $this->source)) . ')';
        }
        
        return $displayName;
    }

    public function getRiskScore(): int
    {
        $score = 0;
        
        // High value transactions are riskier
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
        
        // Penalty transactions are high risk
        if ($this->isPenalty()) {
            $score += 50;
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
            'user_id' => $this->userId->toInt(),
            'amount' => $this->amount,
            'type' => $this->type,
            'source' => $this->source,
            'status' => $this->status->toString(),
            'reference_id' => $this->referenceId,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'processed_at' => $this->processedAt?->toISOString(),
            'expires_at' => $this->expiresAt?->toISOString(),
            'absolute_amount' => $this->getAbsoluteAmount(),
            'formatted_amount' => $this->getFormattedAmount(),
            'display_name' => $this->getDisplayName(),
            'is_credit' => $this->isCredit(),
            'is_debit' => $this->isDebit(),
            'is_high_value' => $this->isHighValue(),
            'is_medium_value' => $this->isMediumValue(),
            'is_low_value' => $this->isLowValue(),
            'is_pending' => $this->isPending(),
            'is_processing' => $this->isProcessing(),
            'is_completed' => $this->isCompleted(),
            'is_failed' => $this->isFailed(),
            'is_cancelled' => $this->isCancelled(),
            'is_refunded' => $this->isRefunded(),
            'is_expired' => $this->isTransactionExpired(),
            'is_active' => $this->isActive(),
            'is_finalized' => $this->isFinalized(),
            'is_successful' => $this->isSuccessful(),
            'is_unsuccessful' => $this->isUnsuccessful(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_be_refunded' => $this->canBeRefunded(),
            'can_be_modified' => $this->canBeModified(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'has_expired' => $this->hasExpired(),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'processing_time' => $this->getProcessingTime(),
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
            'user_id' => $this->userId->toInt(),
            'amount' => $this->amount,
            'type' => $this->type,
            'source' => $this->source,
            'status' => $this->status->toString(),
            'reference_id' => $this->referenceId,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'processed_at' => $this->processedAt,
            'expires_at' => $this->expiresAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'formatted_amount' => $this->getFormattedAmount(),
            'type' => $this->type,
            'source' => $this->source,
            'status' => [
                'value' => $this->status->toString(),
                'description' => $this->status->getDescription(),
                'category' => $this->status->getCategory(),
            ],
            'reference_id' => $this->referenceId,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'timestamps' => [
                'processed_at' => $this->processedAt?->toISOString(),
                'expires_at' => $this->expiresAt?->toISOString(),
                'created_at' => $this->createdAt->toISOString(),
                'updated_at' => $this->updatedAt->toISOString(),
            ],
            'flags' => [
                'is_credit' => $this->isCredit(),
                'is_debit' => $this->isDebit(),
                'is_pending' => $this->isPending(),
                'is_processing' => $this->isProcessing(),
                'is_completed' => $this->isCompleted(),
                'is_failed' => $this->isFailed(),
                'is_cancelled' => $this->isCancelled(),
                'is_refunded' => $this->isRefunded(),
                'is_expired' => $this->isTransactionExpired(),
                'is_active' => $this->isActive(),
                'is_finalized' => $this->isFinalized(),
                'is_successful' => $this->isSuccessful(),
                'can_be_cancelled' => $this->canBeCancelled(),
                'can_be_refunded' => $this->canBeRefunded(),
                'can_be_modified' => $this->canBeModified(),
            ],
            'value' => [
                'absolute_amount' => $this->getAbsoluteAmount(),
                'is_high_value' => $this->isHighValue(),
                'is_medium_value' => $this->isMediumValue(),
                'is_low_value' => $this->isLowValue(),
            ],
            'timing' => [
                'is_expiring_soon' => $this->isExpiringSoon(),
                'has_expired' => $this->hasExpired(),
                'days_until_expiration' => $this->getDaysUntilExpiration(),
                'processing_time' => $this->getProcessingTime(),
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
        ];
    }

    public function __toString(): string
    {
        return "CoinTransaction {$this->id} - {$this->getFormattedAmount()} ({$this->status->toString()})";
    }

    public function __debugInfo(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'type' => $this->type,
            'source' => $this->source,
            'status' => $this->status->toString(),
            'is_credit' => $this->isCredit(),
            'is_debit' => $this->isDebit(),
            'is_completed' => $this->isCompleted(),
            'is_failed' => $this->isFailed(),
            'risk_score' => $this->getRiskScore(),
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
