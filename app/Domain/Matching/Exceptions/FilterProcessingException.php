<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

/**
 * FilterProcessingException - Exception for filter processing errors
 * 
 * Thrown when filter processing encounters unexpected errors during
 * the application of filters to candidate profiles, such as database
 * errors, timeout issues, or other processing failures.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class FilterProcessingException extends FilterException
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
