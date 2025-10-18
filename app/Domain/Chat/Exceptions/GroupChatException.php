<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * GroupChatException - Base exception for group chat-related errors
 * 
 * Base exception class for all group chat-related errors in the ForeverUsInLove
 * dating application. Provides a common foundation for group-specific
 * exception handling and error reporting.
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class GroupChatException extends ChatException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Group chat error occurred', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
