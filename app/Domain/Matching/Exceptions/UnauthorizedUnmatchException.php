<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

/**
 * UnauthorizedUnmatchException - Exception for unauthorized unmatch attempts
 * 
 * Thrown when a user attempts to unmatch from a match they are not part of.
 * This ensures proper authorization for unmatch operations and maintains security.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class UnauthorizedUnmatchException extends MatchException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'User is not authorized to unmatch from this match', int $code = 403, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
