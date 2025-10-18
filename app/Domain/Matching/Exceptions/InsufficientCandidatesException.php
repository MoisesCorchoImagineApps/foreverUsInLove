<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * InsufficientCandidatesException - Exception for insufficient candidate profiles
 * 
 * Exception thrown when there are not enough suitable candidate profiles
 * available for discovery sessions in the ForeverUsInLove dating application.
 * 
 * This exception is specific to the Matching domain and is used when:
 * - Discovery sessions cannot find enough profiles matching criteria
 * - Geographic or demographic filters result in empty candidate pools
 * - Premium discovery modes cannot generate sufficient results
 * - Second chance discovery has no previously passed profiles to reconsider
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class InsufficientCandidatesException extends Exception
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
