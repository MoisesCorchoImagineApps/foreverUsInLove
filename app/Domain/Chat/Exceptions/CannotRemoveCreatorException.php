<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * CannotRemoveCreatorException
 * 
 * Exception thrown when trying to remove chat creator
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class CannotRemoveCreatorException extends Exception
{
    public function __construct(string $message = "Cannot remove chat creator", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
