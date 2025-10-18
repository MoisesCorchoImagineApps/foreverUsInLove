<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * SubscriptionAlreadyActiveException
 * 
 * Exception thrown when attempting to create a new subscription
 * for a user who already has an active subscription.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 */
class SubscriptionAlreadyActiveException extends Exception
{
    public function __construct(
        string $message = 'User already has an active subscription',
        int $code = 409,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for specific user
     */
    public static function forUser(string $userId): self
    {
        return new self("User already has an active subscription: {$userId}");
    }

    /**
     * Create exception with subscription details
     */
    public static function withDetails(string $userId, string $subscriptionId, string $planId): self
    {
        return new self("User {$userId} already has active subscription {$subscriptionId} for plan {$planId}");
    }

    /**
     * Create exception for trial subscription
     */
    public static function trialActive(string $userId): self
    {
        return new self("User already has an active trial subscription: {$userId}");
    }

    /**
     * Create exception for premium subscription
     */
    public static function premiumActive(string $userId): self
    {
        return new self("User already has an active premium subscription: {$userId}");
    }
}
