<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * LikeException
 * 
 * Base exception for all like-related errors in the ForeverUsInLove platform.
 * Provides a common base class for all like operation exceptions.
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class LikeException extends Exception
{
    public function __construct(string $message = 'Like operation failed', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
