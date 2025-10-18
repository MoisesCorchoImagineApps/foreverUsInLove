<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * InsufficientDataException - Exception for insufficient user data
 * 
 * Exception thrown when user data is insufficient for matching operations
 * in the ForeverUsInLove dating application. This includes incomplete
 * profiles, missing required information, or invalid user states.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2024-01-01
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class InsufficientDataException extends Exception
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
     * Create exception for incomplete user profile
     * 
     * @param string $userId User ID
     * @param array $missingFields Missing profile fields
     * @return self New InsufficientDataException instance
     */
    public static function incompleteProfile(string $userId, array $missingFields = []): self
    {
        $message = "User profile is incomplete for user {$userId}";
        if (!empty($missingFields)) {
            $message .= ". Missing fields: " . implode(', ', $missingFields);
        }
        return new self($message);
    }

    /**
     * Create exception for missing user profile
     * 
     * @param string $userId User ID
     * @return self New InsufficientDataException instance
     */
    public static function missingProfile(string $userId): self
    {
        return new self("User profile not found for user {$userId}");
    }

    /**
     * Create exception for invalid user state
     * 
     * @param string $userId User ID
     * @param string $reason Reason for invalid state
     * @return self New InsufficientDataException instance
     */
    public static function invalidUserState(string $userId, string $reason): self
    {
        return new self("User {$userId} is in invalid state for matching: {$reason}");
    }

    /**
     * Create exception for missing compatibility data
     * 
     * @param string $userId1 First user ID
     * @param string $userId2 Second user ID
     * @return self New InsufficientDataException instance
     */
    public static function missingCompatibilityData(string $userId1, string $userId2): self
    {
        return new self("Insufficient data to calculate compatibility between users {$userId1} and {$userId2}");
    }

    /**
     * Create exception for missing matching criteria
     * 
     * @return self New InsufficientDataException instance
     */
    public static function missingMatchingCriteria(): self
    {
        return new self("Matching criteria are required but not provided");
    }

    /**
     * Create exception for missing user preferences
     * 
     * @param string $userId User ID
     * @return self New InsufficientDataException instance
     */
    public static function missingUserPreferences(string $userId): self
    {
        return new self("User preferences are required for matching but not found for user {$userId}");
    }

    /**
     * Create exception for missing location data
     * 
     * @param string $userId User ID
     * @return self New InsufficientDataException instance
     */
    public static function missingLocationData(string $userId): self
    {
        return new self("Location data is required for matching but not found for user {$userId}");
    }

    /**
     * Create exception for missing interest data
     * 
     * @param string $userId User ID
     * @return self New InsufficientDataException instance
     */
    public static function missingInterestData(string $userId): self
    {
        return new self("Interest data is required for matching but not found for user {$userId}");
    }

    /**
     * Create exception for missing age information
     * 
     * @param string $userId User ID
     * @return self New InsufficientDataException instance
     */
    public static function missingAgeInformation(string $userId): self
    {
        return new self("Age information is required for matching but not found for user {$userId}");
    }

    /**
     * Create exception for profile not eligible for matching
     * 
     * @param string $userId User ID
     * @param string $reason Reason for ineligibility
     * @return self New InsufficientDataException instance
     */
    public static function profileNotEligible(string $userId, string $reason): self
    {
        return new self("User profile is not eligible for matching: {$reason}");
    }
}
