<?php

declare(strict_types=1);

namespace App\Domain\Chat\Exceptions;

/**
 * EventNotFoundException - Exception for Happy Hour events not found
 * 
 * Exception thrown when a Happy Hour event cannot be found by the provided
 * identifier. This typically occurs when attempting to access, join, or
 * interact with a non-existent event.
 * 
 * @package App\Domain\Chat\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class EventNotFoundException extends HappyHourException
{
    /**
     * Constructor
     * 
     * @param string $eventId Event identifier that was not found
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(string $eventId, int $code = 0, ?\Exception $previous = null)
    {
        $message = "Happy Hour event not found: {$eventId}";
        parent::__construct($message, $code, $previous);
    }
}
