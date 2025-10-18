<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

/**
 * UnauthorizedEventAccessException - Exception for unauthorized event access
 * 
 * Exception thrown when a user attempts to access a Happy Hour event
 * they are not authorized to join or interact with. This includes
 * private events where the user is not invited, or events with
 * access restrictions.
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class UnauthorizedEventAccessException extends HappyHourException
{
    /**
     * Constructor
     * 
     * @param string $message Custom error message
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Event is private and user is not invited', int $code = 0, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
