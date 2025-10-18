<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use Exception;

/**
 * GiftNotFoundException
 * 
 * Exception thrown when a requested gift is not found in the ForeverUsInLove
 * dating application gift catalog.
 * 
 * @package App\Domain\Commerce\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class GiftNotFoundException extends Exception
{
    public function __construct(string $message = "Gift not found", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
