<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * ChatNotFoundException
 * 
 * Exception thrown when chat is not found
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class ChatNotFoundException extends Exception
{
    public function __construct(string $message = "Chat not found", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
