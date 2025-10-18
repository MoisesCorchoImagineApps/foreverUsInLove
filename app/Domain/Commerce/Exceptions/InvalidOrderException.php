<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * InvalidOrderException
 * 
 * Exception thrown when order data is invalid or malformed in the ForeverUsInLove
 * dating application. This includes validation errors for order creation,
 * modification, or processing operations.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class InvalidOrderException extends Exception
{
    public function __construct(string $message = "Invalid order data", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
