<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

use Exception;

/**
 * ParticipantAlreadyExistsException
 * 
 * Exception thrown when trying to add a participant that already exists
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class ParticipantAlreadyExistsException extends Exception
{
    public function __construct(string $message = "Participant already exists", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
