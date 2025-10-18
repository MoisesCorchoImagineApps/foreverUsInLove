<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * LikeRateLimitException
 * 
 * Exception thrown when a user exceeds their like rate limits in the
 * ForeverUsInLove platform. This includes daily limits and rapid-fire
 * rate limiting to prevent spam and abuse.
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class LikeRateLimitException extends Exception
{
    public function __construct(string $message = 'Like rate limit exceeded', int $code = 429, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
