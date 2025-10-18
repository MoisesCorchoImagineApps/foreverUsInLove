<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * MessageValidationException
 * 
 * Exception thrown when message validation fails in the ForeverUsInLove
 * dating application.
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class MessageValidationException extends Exception
{
    public function __construct(string $message = "Message validation failed", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
