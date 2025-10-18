<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * FulfillmentException
 * 
 * Exception thrown when order fulfillment fails in the ForeverUsInLove
 * dating application. This includes delivery failures, inventory issues,
 * or any problems during the fulfillment process.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class FulfillmentException extends Exception
{
    public function __construct(string $message = "Order fulfillment failed", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
