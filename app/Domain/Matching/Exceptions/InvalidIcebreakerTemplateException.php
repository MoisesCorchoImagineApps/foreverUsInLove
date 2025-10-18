<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * InvalidIcebreakerTemplateException - Exception for invalid icebreaker templates
 * 
 * Thrown when icebreaker template validation fails. This includes invalid
 * template syntax, missing placeholders, inappropriate content, or
 * template structure violations.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class InvalidIcebreakerTemplateException extends IcebreakerException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(string $message = 'Invalid icebreaker template', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
