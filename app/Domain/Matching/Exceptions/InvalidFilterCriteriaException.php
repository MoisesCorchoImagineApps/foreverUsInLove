<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

/**
 * InvalidFilterCriteriaException - Exception for invalid filter criteria
 * 
 * Thrown when filter criteria validation fails, such as invalid age ranges,
 * distance values, or other filter parameters that don't meet business rules.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class InvalidFilterCriteriaException extends FilterException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(string $message = '', int $code = 0, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
