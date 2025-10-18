<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * DuplicateLikeException
 * 
 * Exception thrown when attempting to create a duplicate like interaction
 * in the ForeverUsInLove platform. Users cannot like the same person
 * multiple times.
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class DuplicateLikeException extends Exception
{
    public function __construct(string $message = 'Duplicate like interaction', int $code = 409, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
