<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * InsufficientFundsException
 * 
 * Exception thrown when a user has insufficient funds to complete a purchase
 * in the ForeverUsInLove dating application.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class InsufficientFundsException extends Exception
{
    public function __construct(string $message = "Insufficient funds", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
