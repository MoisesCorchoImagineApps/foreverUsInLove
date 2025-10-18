<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * GroupLimitExceededException - Exception for group limit violations
 * 
 * Thrown when group-related limits are exceeded, such as:
 * - Maximum number of members reached
 * - Maximum number of groups per user reached
 * - Other group-specific capacity or quota limits
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class GroupLimitExceededException extends GroupChatException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Group limit exceeded', int $code = 422, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
