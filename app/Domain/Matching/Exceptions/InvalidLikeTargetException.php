<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * InvalidLikeTargetException
 * 
 * Exception thrown when attempting to like an invalid target user in the
 * ForeverUsInLove platform. This includes blocked users, inactive profiles,
 * or attempting to like oneself.
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class InvalidLikeTargetException extends Exception
{
    public function __construct(string $message = 'Invalid like target', int $code = 400, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
