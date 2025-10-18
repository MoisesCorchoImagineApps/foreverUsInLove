<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * MessageEditTimeExpiredException
 * 
 * Exception thrown when attempting to edit a message after the edit
 * time window has expired in the ForeverUsInLove dating application.
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class MessageEditTimeExpiredException extends Exception
{
    public function __construct(string $message = "Message edit time has expired", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
