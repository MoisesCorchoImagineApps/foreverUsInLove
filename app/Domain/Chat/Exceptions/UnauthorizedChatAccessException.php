<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * UnauthorizedChatAccessException - Exception for unauthorized chat access
 * 
 * Thrown when a user attempts to access a chat they are not authorized to view
 * or participate in. This includes scenarios where users try to access chats
 * they are not participants of, or when access permissions have been revoked.
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class UnauthorizedChatAccessException extends ChatException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Unauthorized access to chat', int $code = 403, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
