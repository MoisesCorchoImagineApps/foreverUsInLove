<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * ChatCapacityExceededException
 * 
 * Exception thrown when chat has reached maximum capacity
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class ChatCapacityExceededException extends Exception
{
    public function __construct(string $message = "Chat capacity exceeded", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
