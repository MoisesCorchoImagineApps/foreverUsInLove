<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * MessageNotFoundException
 * 
 * Exception thrown when a message is not found in the ForeverUsInLove
 * dating application.
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class MessageNotFoundException extends Exception
{
    public function __construct(string $message = "Message not found", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
