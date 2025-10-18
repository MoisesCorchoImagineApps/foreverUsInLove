<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * InvalidSubscriptionException
 * 
 * Exception thrown when a subscription is in an invalid state for the requested operation.
 * Used when subscription data is malformed or operation is not allowed.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 */
class InvalidSubscriptionException extends Exception
{
    public function __construct(
        string $message = 'Invalid subscription',
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for invalid status
     */
    public static function invalidStatus(string $status): self
    {
        return new self("Invalid subscription status: {$status}");
    }

    /**
     * Create exception for expired subscription
     */
    public static function expired(string $subscriptionId): self
    {
        return new self("Subscription has expired: {$subscriptionId}", 410);
    }

    /**
     * Create exception for cancelled subscription
     */
    public static function cancelled(string $subscriptionId): self
    {
        return new self("Subscription has been cancelled: {$subscriptionId}", 410);
    }

    /**
     * Create exception for invalid plan change
     */
    public static function invalidPlanChange(string $fromPlan, string $toPlan): self
    {
        return new self("Invalid plan change from {$fromPlan} to {$toPlan}");
    }

    /**
     * Create exception for invalid billing cycle
     */
    public static function invalidBillingCycle(string $billingCycle): self
    {
        return new self("Invalid billing cycle: {$billingCycle}");
    }
}
