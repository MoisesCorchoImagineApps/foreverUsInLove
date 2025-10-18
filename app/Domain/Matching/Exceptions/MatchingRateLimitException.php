<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * MatchingRateLimitException - Exception for matching rate limits
 * 
 * Exception thrown when matching operations exceed rate limits in the
 * ForeverUsInLove dating application. This includes daily limits,
 * session limits, and other rate limiting mechanisms.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2024-01-01
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class MatchingRateLimitException extends Exception
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
     * Create exception for daily matching limit exceeded
     * 
     * @param string $userId User ID
     * @param int $limit Daily limit
     * @param int $current Current usage
     * @return self New MatchingRateLimitException instance
     */
    public static function dailyLimitExceeded(string $userId, int $limit, int $current): self
    {
        return new self(
            "Daily matching limit exceeded for user {$userId}. Limit: {$limit}, Current: {$current}"
        );
    }

    /**
     * Create exception for session limit exceeded
     * 
     * @param string $userId User ID
     * @param int $limit Session limit
     * @param int $current Current usage
     * @return self New MatchingRateLimitException instance
     */
    public static function sessionLimitExceeded(string $userId, int $limit, int $current): self
    {
        return new self(
            "Session matching limit exceeded for user {$userId}. Limit: {$limit}, Current: {$current}"
        );
    }

    /**
     * Create exception for hourly limit exceeded
     * 
     * @param string $userId User ID
     * @param int $limit Hourly limit
     * @param int $current Current usage
     * @return self New MatchingRateLimitException instance
     */
    public static function hourlyLimitExceeded(string $userId, int $limit, int $current): self
    {
        return new self(
            "Hourly matching limit exceeded for user {$userId}. Limit: {$limit}, Current: {$current}"
        );
    }

    /**
     * Create exception for premium limit exceeded
     * 
     * @param string $userId User ID
     * @param int $limit Premium limit
     * @param int $current Current usage
     * @return self New MatchingRateLimitException instance
     */
    public static function premiumLimitExceeded(string $userId, int $limit, int $current): self
    {
        return new self(
            "Premium matching limit exceeded for user {$userId}. Limit: {$limit}, Current: {$current}"
        );
    }

    /**
     * Create exception for free user limit exceeded
     * 
     * @param string $userId User ID
     * @param int $limit Free user limit
     * @param int $current Current usage
     * @return self New MatchingRateLimitException instance
     */
    public static function freeUserLimitExceeded(string $userId, int $limit, int $current): self
    {
        return new self(
            "Free user matching limit exceeded for user {$userId}. Limit: {$limit}, Current: {$current}. Upgrade to premium for higher limits."
        );
    }

    /**
     * Create exception for rate limit with retry time
     * 
     * @param string $userId User ID
     * @param int $retryAfterSeconds Seconds until retry is allowed
     * @return self New MatchingRateLimitException instance
     */
    public static function rateLimitWithRetry(string $userId, int $retryAfterSeconds): self
    {
        return new self(
            "Rate limit exceeded for user {$userId}. Please try again in {$retryAfterSeconds} seconds."
        );
    }

    /**
     * Create exception for suspicious activity detected
     * 
     * @param string $userId User ID
     * @param string $reason Reason for suspicion
     * @return self New MatchingRateLimitException instance
     */
    public static function suspiciousActivity(string $userId, string $reason): self
    {
        return new self(
            "Suspicious matching activity detected for user {$userId}: {$reason}. Account temporarily restricted."
        );
    }

    /**
     * Create exception for new user limit
     * 
     * @param string $userId User ID
     * @param int $daysRequired Days required before full access
     * @return self New MatchingRateLimitException instance
     */
    public static function newUserLimit(string $userId, int $daysRequired): self
    {
        return new self(
            "New user matching limit for user {$userId}. Full access available after {$daysRequired} days."
        );
    }

    /**
     * Create exception for verification required
     * 
     * @param string $userId User ID
     * @return self New MatchingRateLimitException instance
     */
    public static function verificationRequired(string $userId): self
    {
        return new self(
            "Account verification required for user {$userId} to access matching features."
        );
    }

    /**
     * Create exception for temporary restriction
     * 
     * @param string $userId User ID
     * @param string $reason Reason for restriction
     * @param int $durationMinutes Duration in minutes
     * @return self New MatchingRateLimitException instance
     */
    public static function temporaryRestriction(string $userId, string $reason, int $durationMinutes): self
    {
        return new self(
            "Temporary matching restriction for user {$userId}: {$reason}. Duration: {$durationMinutes} minutes."
        );
    }
}
