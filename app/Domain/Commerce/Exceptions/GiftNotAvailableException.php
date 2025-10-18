<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * GiftNotAvailableException
 * 
 * Exception thrown when a gift is not available for purchase in the ForeverUsInLove
 * dating application (e.g., out of stock, disabled, or restricted).
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class GiftNotAvailableException extends Exception
{
    public function __construct(string $message = "Gift is not available", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
