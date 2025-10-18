<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

/**
 * MatchReadOnlyException - Exception for when a match cannot be modified
 * 
 * Thrown when attempting to modify a match that is in a read-only state,
 * such as an unmatched or archived match that cannot be updated.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class MatchReadOnlyException extends MatchException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Match is in read-only state and cannot be modified', int $code = 423, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
