<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * InvalidGiftDataException
 * 
 * Exception thrown when gift data is invalid or malformed in the ForeverUsInLove
 * dating application gift system.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class InvalidGiftDataException extends Exception
{
    public function __construct(string $message = "Invalid gift data", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
