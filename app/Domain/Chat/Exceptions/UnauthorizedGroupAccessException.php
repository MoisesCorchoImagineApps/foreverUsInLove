<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * UnauthorizedGroupAccessException - Exception for unauthorized group access
 * 
 * Thrown when a user attempts to access a group chat they are not authorized to view
 * or participate in. This includes scenarios where users try to access groups
 * they are not members of, or when group access permissions have been revoked.
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class UnauthorizedGroupAccessException extends GroupChatException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Unauthorized access to group chat', int $code = 403, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
