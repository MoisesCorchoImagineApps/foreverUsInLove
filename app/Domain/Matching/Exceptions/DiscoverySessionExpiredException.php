<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * DiscoverySessionExpiredException - Exception for expired discovery sessions
 * 
 * Exception thrown when a discovery session has expired and can no longer
 * be used for profile discovery in the ForeverUsInLove dating application.
 * 
 * This exception is specific to the Matching domain and is used when:
 * - Discovery sessions exceed their time limits (30 min free, 60 min premium)
 * - Users attempt to access expired discovery sessions
 * - Session cleanup processes encounter expired sessions
 * - Card retrieval is attempted on expired sessions
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class DiscoverySessionExpiredException extends Exception
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
