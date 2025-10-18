<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * InsufficientCoinsException
 * 
 * Exception thrown when a user has insufficient coin balance to perform
 * a transaction in the ForeverUsInLove dating application.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class InsufficientCoinsException extends Exception
{
    public function __construct(string $message = "Insufficient coin balance", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
