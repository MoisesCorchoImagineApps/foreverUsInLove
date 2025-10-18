<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use Exception;

/**
 * UserNotFoundException
 * 
 * Exception thrown when a user is not found in the ForeverUsInLove
 * dating application.
 * 
 * @package App\Domain\Auth\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class UserNotFoundException extends Exception
{
    public function __construct(string $message = "User not found", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
