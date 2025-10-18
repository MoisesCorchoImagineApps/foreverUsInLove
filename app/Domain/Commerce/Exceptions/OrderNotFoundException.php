<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * OrderNotFoundException
 * 
 * Exception thrown when an order cannot be found in the ForeverUsInLove
 * dating application. This typically occurs when attempting to retrieve,
 * process, or manipulate an order that doesn't exist in the system.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class OrderNotFoundException extends Exception
{
    public function __construct(string $message = "Order not found", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
