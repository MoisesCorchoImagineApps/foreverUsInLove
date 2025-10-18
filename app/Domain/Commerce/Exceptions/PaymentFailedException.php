<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * PaymentFailedException
 * 
 * Exception thrown when payment processing fails in the ForeverUsInLove
 * dating application.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class PaymentFailedException extends Exception
{
    public function __construct(string $message = "Payment processing failed", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
