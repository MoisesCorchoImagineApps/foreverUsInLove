<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

/**
 * EventCapacityExceededException - Exception for event capacity exceeded
 * 
 * Exception thrown when a Happy Hour event has reached its maximum
 * participant capacity and cannot accept new members. This ensures
 * proper event management and prevents overcrowding.
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class EventCapacityExceededException extends HappyHourException
{
    /**
     * Constructor
     * 
     * @param string $message Custom error message
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Event has reached maximum capacity', int $code = 0, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
