<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

/**
 * UsersNotCompatibleException - Exception for incompatible users
 * 
 * Thrown when two users cannot be matched due to compatibility issues,
 * such as both users being inactive, one not existing, or other business rules.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class UsersNotCompatibleException extends MatchException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Users are not compatible for matching', int $code = 422, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
