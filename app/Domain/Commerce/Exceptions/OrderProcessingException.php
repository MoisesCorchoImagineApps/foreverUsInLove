<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * OrderProcessingException
 * 
 * Exception thrown when order processing fails in the ForeverUsInLove
 * dating application. This includes payment processing failures, fulfillment
 * errors, or any other issues during order workflow execution.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class OrderProcessingException extends Exception
{
    public function __construct(string $message = "Order processing failed", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
