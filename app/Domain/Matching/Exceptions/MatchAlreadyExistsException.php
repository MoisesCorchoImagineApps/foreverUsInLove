<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

/**
 * MatchAlreadyExistsException - Exception for when a match already exists
 * 
 * Thrown when attempting to create a match between two users who are already matched.
 * This prevents duplicate matches and maintains data integrity in the matching system.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class MatchAlreadyExistsException extends MatchException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Match already exists between users', int $code = 409, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
