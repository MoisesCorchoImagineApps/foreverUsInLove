<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * SubscriptionNotFoundException
 * 
 * Exception thrown when a subscription is not found.
 * Used when attempting to access or process non-existent subscriptions.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 */
class SubscriptionNotFoundException extends Exception
{
    public function __construct(
        string $message = 'Subscription not found',
        int $code = 404,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for specific subscription ID
     */
    public static function forSubscriptionId(string $subscriptionId): self
    {
        return new self("Subscription not found: {$subscriptionId}");
    }

    /**
     * Create exception for user subscription
     */
    public static function forUser(string $userId): self
    {
        return new self("No subscription found for user: {$userId}");
    }

    /**
     * Create exception for active subscription
     */
    public static function activeForUser(string $userId): self
    {
        return new self("No active subscription found for user: {$userId}");
    }
}
