<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * PlanNotFoundException
 * 
 * Exception thrown when a subscription plan is not found.
 * Used when attempting to access or process non-existent plans.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 */
class PlanNotFoundException extends Exception
{
    public function __construct(
        string $message = 'Plan not found',
        int $code = 404,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for specific plan ID
     */
    public static function forPlanId(string $planId): self
    {
        return new self("Plan not found: {$planId}");
    }

    /**
     * Create exception for plan type
     */
    public static function forPlanType(string $planType): self
    {
        return new self("Plan type not found: {$planType}");
    }

    /**
     * Create exception for billing cycle
     */
    public static function forBillingCycle(string $planType, string $billingCycle): self
    {
        return new self("Plan not found for {$planType} with {$billingCycle} billing cycle");
    }
}
