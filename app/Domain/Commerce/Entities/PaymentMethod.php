<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Entities;

use Carbon\Carbon;

/**
 * PaymentMethod Entity
 * 
 * Core domain entity representing a payment method in the ForeverUsInLove dating application.
 * Encapsulates payment method information, validation, and security features.
 * 
 * @package App\Domain\Commerce\Entities
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class PaymentMethod
{
    // Payment Method Types
    public const TYPE_CREDIT_CARD = 'credit_card';
    public const TYPE_DEBIT_CARD = 'debit_card';
    public const TYPE_BANK_TRANSFER = 'bank_transfer';
    public const TYPE_DIGITAL_WALLET = 'digital_wallet';
    public const TYPE_CRYPTOCURRENCY = 'cryptocurrency';
    public const TYPE_PREPAID_CARD = 'prepaid_card';

    // Card Brands
    public const BRAND_VISA = 'visa';
    public const BRAND_MASTERCARD = 'mastercard';
    public const BRAND_AMERICAN_EXPRESS = 'american_express';
    public const BRAND_DISCOVER = 'discover';
    public const BRAND_JCB = 'jcb';
    public const BRAND_DINERS_CLUB = 'diners_club';

    // Verification Status
    public const VERIFICATION_PENDING = 'pending';
    public const VERIFICATION_VERIFIED = 'verified';
    public const VERIFICATION_FAILED = 'failed';
    public const VERIFICATION_EXPIRED = 'expired';

    private function __construct(
        private string $type,
        private string $gatewayToken,
        private ?string $lastFour,
        private ?string $brand,
        private ?Carbon $expiryDate,
        private ?string $holderName,
        private bool $isDefault,
        private string $verificationStatus,
        private ?string $verificationToken,
        private ?Carbon $verifiedAt,
        private ?string $failureReason,
        private bool $isActive,
        private bool $isInternational,
        private ?array $metadata,
        private Carbon $createdAt,
        private Carbon $updatedAt
    ) {}

    public static function create(
        string $type,
        string $gatewayToken,
        ?string $lastFour = null,
        ?string $brand = null,
        ?Carbon $expiryDate = null,
        ?string $holderName = null,
        bool $isDefault = false,
        string $verificationStatus = self::VERIFICATION_PENDING,
        bool $isInternational = false,
        ?array $metadata = null
    ): self {
        $now = now();
        
        return new self(
            type: $type,
            gatewayToken: $gatewayToken,
            lastFour: $lastFour,
            brand: $brand,
            expiryDate: $expiryDate,
            holderName: $holderName,
            isDefault: $isDefault,
            verificationStatus: $verificationStatus,
            verificationToken: null,
            verifiedAt: null,
            failureReason: null,
            isActive: true,
            isInternational: $isInternational,
            metadata: $metadata,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public static function fromRepositoryData(array $data): self
    {
        return new self(
            type: $data['type'],
            gatewayToken: $data['gateway_token'],
            lastFour: $data['last_four'] ?? null,
            brand: $data['brand'] ?? null,
            expiryDate: isset($data['expiry_date']) ? Carbon::parse($data['expiry_date']) : null,
            holderName: $data['holder_name'] ?? null,
            isDefault: $data['is_default'] ?? false,
            verificationStatus: $data['verification_status'] ?? self::VERIFICATION_PENDING,
            verificationToken: $data['verification_token'] ?? null,
            verifiedAt: isset($data['verified_at']) ? Carbon::parse($data['verified_at']) : null,
            failureReason: $data['failure_reason'] ?? null,
            isActive: $data['is_active'] ?? true,
            isInternational: $data['is_international'] ?? false,
            metadata: $data['metadata'] ?? null,
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at'])
        );
    }

    // Getters
    public function type(): string
    {
        return $this->type;
    }

    public function gatewayToken(): string
    {
        return $this->gatewayToken;
    }

    public function lastFour(): ?string
    {
        return $this->lastFour;
    }

    public function brand(): ?string
    {
        return $this->brand;
    }

    public function expiryDate(): ?Carbon
    {
        return $this->expiryDate;
    }

    public function holderName(): ?string
    {
        return $this->holderName;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function verificationStatus(): string
    {
        return $this->verificationStatus;
    }

    public function verificationToken(): ?string
    {
        return $this->verificationToken;
    }

    public function verifiedAt(): ?Carbon
    {
        return $this->verifiedAt;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isInternational(): bool
    {
        return $this->isInternational;
    }

    public function metadata(): ?array
    {
        return $this->metadata;
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
    public function isCreditCard(): bool
    {
        return $this->type === self::TYPE_CREDIT_CARD;
    }

    public function isDebitCard(): bool
    {
        return $this->type === self::TYPE_DEBIT_CARD;
    }

    public function isBankTransfer(): bool
    {
        return $this->type === self::TYPE_BANK_TRANSFER;
    }

    public function isDigitalWallet(): bool
    {
        return $this->type === self::TYPE_DIGITAL_WALLET;
    }

    public function isCryptocurrency(): bool
    {
        return $this->type === self::TYPE_CRYPTOCURRENCY;
    }

    public function isPrepaidCard(): bool
    {
        return $this->type === self::TYPE_PREPAID_CARD;
    }

    public function isCard(): bool
    {
        return $this->isCreditCard() || $this->isDebitCard() || $this->isPrepaidCard();
    }

    public function isVerified(): bool
    {
        return $this->verificationStatus === self::VERIFICATION_VERIFIED;
    }

    public function isPendingVerification(): bool
    {
        return $this->verificationStatus === self::VERIFICATION_PENDING;
    }

    public function isVerificationFailed(): bool
    {
        return $this->verificationStatus === self::VERIFICATION_FAILED;
    }

    public function isVerificationExpired(): bool
    {
        return $this->verificationStatus === self::VERIFICATION_EXPIRED;
    }

    public function isExpired(): bool
    {
        if (!$this->expiryDate) {
            return false;
        }

        return $this->expiryDate->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expiryDate) {
            return false;
        }

        return $this->expiryDate->isBefore(now()->addDays($days));
    }

    public function isNew(): bool
    {
        return $this->createdAt->isAfter(now()->subDays(30));
    }

    public function isOld(): bool
    {
        return $this->createdAt->isBefore(now()->subMonths(6));
    }

    public function canBeUsed(): bool
    {
        return $this->isActive && $this->isVerified() && !$this->isExpired();
    }

    public function requiresVerification(): bool
    {
        return $this->isPendingVerification() || $this->isVerificationExpired();
    }

    public function getMaskedNumber(): string
    {
        if (!$this->lastFour) {
            return '****';
        }

        return '**** **** **** ' . $this->lastFour;
    }

    public function getDisplayName(): string
    {
        $displayName = ucfirst(str_replace('_', ' ', $this->type));
        
        if ($this->brand) {
            $displayName .= ' (' . ucfirst($this->brand) . ')';
        }
        
        if ($this->lastFour) {
            $displayName .= ' ' . $this->getMaskedNumber();
        }
        
        return $displayName;
    }

    // State Management Methods
    public function markAsVerified(?string $verificationToken = null): void
    {
        $this->verificationStatus = self::VERIFICATION_VERIFIED;
        $this->verificationToken = $verificationToken;
        $this->verifiedAt = now();
        $this->updatedAt = now();
    }

    public function markAsVerificationFailed(string $reason): void
    {
        $this->verificationStatus = self::VERIFICATION_FAILED;
        $this->failureReason = $reason;
        $this->updatedAt = now();
    }

    public function markAsVerificationExpired(): void
    {
        $this->verificationStatus = self::VERIFICATION_EXPIRED;
        $this->updatedAt = now();
    }

    public function setAsDefault(): void
    {
        $this->isDefault = true;
        $this->updatedAt = now();
    }

    public function unsetAsDefault(): void
    {
        $this->isDefault = false;
        $this->updatedAt = now();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = now();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = now();
    }

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata ?? [], $metadata);
        $this->updatedAt = now();
    }

    /**
     * Get secure data for broadcasting/API responses
     * Returns sanitized payment method information without sensitive data
     */
    public function getSecureData(): array
    {
        return [
            'type' => $this->type,
            'last_four' => $this->lastFour,
            'brand' => $this->brand,
            'expiry_date' => $this->expiryDate?->toISOString(),
            'holder_name' => $this->holderName,
            'is_default' => $this->isDefault,
            'verification_status' => $this->verificationStatus,
            'is_active' => $this->isActive,
            'is_international' => $this->isInternational,
            'masked_number' => $this->getMaskedNumber(),
            'display_name' => $this->getDisplayName(),
            'is_expired' => $this->isExpired(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'can_be_used' => $this->canBeUsed(),
            'requires_verification' => $this->requiresVerification(),
        ];
    }

    // Conversion Methods
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'gateway_token' => $this->gatewayToken,
            'last_four' => $this->lastFour,
            'brand' => $this->brand,
            'expiry_date' => $this->expiryDate?->toISOString(),
            'holder_name' => $this->holderName,
            'is_default' => $this->isDefault,
            'verification_status' => $this->verificationStatus,
            'verification_token' => $this->verificationToken,
            'verified_at' => $this->verifiedAt?->toISOString(),
            'failure_reason' => $this->failureReason,
            'is_active' => $this->isActive,
            'is_international' => $this->isInternational,
            'metadata' => $this->metadata,
            'masked_number' => $this->getMaskedNumber(),
            'display_name' => $this->getDisplayName(),
            'is_expired' => $this->isExpired(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'can_be_used' => $this->canBeUsed(),
            'requires_verification' => $this->requiresVerification(),
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }

    public function toRepositoryArray(): array
    {
        return [
            'type' => $this->type,
            'gateway_token' => $this->gatewayToken,
            'last_four' => $this->lastFour,
            'brand' => $this->brand,
            'expiry_date' => $this->expiryDate,
            'holder_name' => $this->holderName,
            'is_default' => $this->isDefault,
            'verification_status' => $this->verificationStatus,
            'verification_token' => $this->verificationToken,
            'verified_at' => $this->verifiedAt,
            'failure_reason' => $this->failureReason,
            'is_active' => $this->isActive,
            'is_international' => $this->isInternational,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function toApiArray(): array
    {
        return [
            'type' => $this->type,
            'last_four' => $this->lastFour,
            'brand' => $this->brand,
            'expiry_date' => $this->expiryDate?->format('m/Y'),
            'holder_name' => $this->holderName,
            'is_default' => $this->isDefault,
            'verification_status' => $this->verificationStatus,
            'is_active' => $this->isActive,
            'masked_number' => $this->getMaskedNumber(),
            'display_name' => $this->getDisplayName(),
            'is_expired' => $this->isExpired(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'can_be_used' => $this->canBeUsed(),
            'requires_verification' => $this->requiresVerification(),
        ];
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }

    public function __debugInfo(): array
    {
        return [
            'type' => $this->type,
            'brand' => $this->brand,
            'last_four' => $this->lastFour,
            'is_default' => $this->isDefault,
            'verification_status' => $this->verificationStatus,
            'is_active' => $this->isActive,
            'is_expired' => $this->isExpired(),
            'can_be_used' => $this->canBeUsed(),
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
