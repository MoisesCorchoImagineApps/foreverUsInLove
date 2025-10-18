<?php

declare(strict_types=1);

namespace App\Domain\Common\Exceptions;

use Exception;

/**
 * ValidationException
 * 
 * Exception thrown when validation fails in the ForeverUsInLove
 * dating application.
 * 
 * @package App\Domain\Common\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class ValidationException extends Exception
{
    public function __construct(string $message = "Validation failed", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
