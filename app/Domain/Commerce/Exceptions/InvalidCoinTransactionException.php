<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * InvalidCoinTransactionException
 * 
 * Exception thrown when a coin transaction is invalid in the ForeverUsInLove
 * dating application.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class InvalidCoinTransactionException extends Exception
{
    public function __construct(string $message = "Invalid coin transaction", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
