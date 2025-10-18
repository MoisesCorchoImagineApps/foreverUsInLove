<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * IcebreakerGenerationException - Exception for icebreaker generation failures
 * 
 * Thrown when AI-powered or template-based icebreaker generation fails.
 * This includes AI service failures, template processing errors, and
 * other generation-related issues.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class IcebreakerGenerationException extends IcebreakerException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Failed to generate icebreakers', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
