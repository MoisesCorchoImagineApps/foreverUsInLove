<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * PlanNotAvailableException
 * 
 * Exception thrown when a plan exists but is not available for subscription.
 * Used when plans are disabled, region-restricted, or temporarily unavailable.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2025 ForeverUsInLove. All rights reserved.
 */
class PlanNotAvailableException extends Exception
{
    public function __construct(
        string $message = 'Plan not available',
        int $code = 403,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for disabled plan
     */
    public static function disabled(string $planId): self
    {
        return new self("Plan is disabled: {$planId}");
    }

    /**
     * Create exception for region-restricted plan
     */
    public static function regionRestricted(string $planId, string $region): self
    {
        return new self("Plan not available in region {$region}: {$planId}");
    }

    /**
     * Create exception for temporarily unavailable plan
     */
    public static function temporarilyUnavailable(string $planId): self
    {
        return new self("Plan temporarily unavailable: {$planId}", 503);
    }

    /**
     * Create exception for maintenance mode
     */
    public static function maintenanceMode(string $planId): self
    {
        return new self("Plan under maintenance: {$planId}", 503);
    }

    /**
     * Create exception for user not eligible
     */
    public static function userNotEligible(string $planId, string $userId): self
    {
        return new self("User not eligible for plan {$planId}: {$userId}");
    }
}
