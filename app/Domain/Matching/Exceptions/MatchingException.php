<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * MatchingException - Base exception for matching service errors
 * 
 * Base exception class for all matching service-related errors in the ForeverUsInLove
 * dating application. Provides a common foundation for matching-specific
 * exception handling and error reporting.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2024-01-01
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class MatchingException extends Exception
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

    /**
     * Create exception for match generation failure
     * 
     * @param string $reason Reason for failure
     * @return self New MatchingException instance
     */
    public static function matchGenerationFailed(string $reason): self
    {
        return new self("Match generation failed: {$reason}");
    }

    /**
     * Create exception for compatibility calculation failure
     * 
     * @param string $reason Reason for failure
     * @return self New MatchingException instance
     */
    public static function compatibilityCalculationFailed(string $reason): self
    {
        return new self("Compatibility calculation failed: {$reason}");
    }

    /**
     * Create exception for match retrieval failure
     * 
     * @param string $reason Reason for failure
     * @return self New MatchingException instance
     */
    public static function matchRetrievalFailed(string $reason): self
    {
        return new self("Match retrieval failed: {$reason}");
    }

    /**
     * Create exception for match creation failure
     * 
     * @param string $reason Reason for failure
     * @return self New MatchingException instance
     */
    public static function matchCreationFailed(string $reason): self
    {
        return new self("Match creation failed: {$reason}");
    }

    /**
     * Create exception for unmatch operation failure
     * 
     * @param string $reason Reason for failure
     * @return self New MatchingException instance
     */
    public static function unmatchOperationFailed(string $reason): self
    {
        return new self("Unmatch operation failed: {$reason}");
    }

    /**
     * Create exception for algorithm optimization failure
     * 
     * @param string $reason Reason for failure
     * @return self New MatchingException instance
     */
    public static function algorithmOptimizationFailed(string $reason): self
    {
        return new self("Algorithm optimization failed: {$reason}");
    }

    /**
     * Create exception for analytics generation failure
     * 
     * @param string $reason Reason for failure
     * @return self New MatchingException instance
     */
    public static function analyticsGenerationFailed(string $reason): self
    {
        return new self("Analytics generation failed: {$reason}");
    }
}
