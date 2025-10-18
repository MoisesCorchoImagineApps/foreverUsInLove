<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * PaymentMethodNotFoundException
 * 
 * Exception thrown when a payment method is not found or invalid.
 * Used when attempting to process payments with non-existent or
 * invalid payment method references.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 */
class PaymentMethodNotFoundException extends Exception
{
    public function __construct(
        string $message = 'Payment method not found',
        int $code = 404,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for specific payment method ID
     */
    public static function forPaymentMethodId(string $paymentMethodId): self
    {
        return new self("Payment method not found: {$paymentMethodId}");
    }

    /**
     * Create exception for expired payment method
     */
    public static function expired(string $paymentMethodId): self
    {
        return new self("Payment method has expired: {$paymentMethodId}", 410);
    }

    /**
     * Create exception for invalid payment method
     */
    public static function invalid(string $paymentMethodId): self
    {
        return new self("Invalid payment method: {$paymentMethodId}", 422);
    }

    /**
     * Create exception for unsupported payment method type
     */
    public static function unsupportedType(string $type): self
    {
        return new self("Unsupported payment method type: {$type}", 422);
    }
}
