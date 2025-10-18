<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

/**
 * MatchNotFoundException - Exception for when a match is not found
 * 
 * Thrown when attempting to access or modify a match that doesn't exist.
 * This ensures proper error handling when working with non-existent matches.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class MatchNotFoundException extends MatchException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Match not found', int $code = 404, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
