<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * InsufficientInventoryException
 * 
 * Exception thrown when there is insufficient inventory to fulfill an order
 * in the ForeverUsInLove dating application. This includes cases where
 * products are out of stock or inventory levels are below required quantities.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class InsufficientInventoryException extends Exception
{
    public function __construct(string $message = "Insufficient inventory", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
