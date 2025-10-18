<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use Exception;

/**
 * InvalidMatchingCriteriaException - Exception for invalid matching criteria
 * 
 * Exception thrown when matching criteria are invalid or incompatible
 * in the ForeverUsInLove dating application. This includes invalid
 * age ranges, distance settings, compatibility thresholds, and other
 * matching parameters.
 * 
 * @package App\Domain\Matching\Exceptions
 * @version 1.0.0
 * @since 2024-01-01
 * 
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove. All rights reserved.
 */
class InvalidMatchingCriteriaException extends Exception
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
     * Create exception for invalid age range
     * 
     * @param int $minAge Minimum age
     * @param int $maxAge Maximum age
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function invalidAgeRange(int $minAge, int $maxAge): self
    {
        return new self(
            "Invalid age range: minimum age ({$minAge}) cannot be greater than maximum age ({$maxAge})"
        );
    }

    /**
     * Create exception for age out of bounds
     * 
     * @param int $age Age value
     * @param int $minAllowed Minimum allowed age
     * @param int $maxAllowed Maximum allowed age
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function ageOutOfBounds(int $age, int $minAllowed, int $maxAllowed): self
    {
        return new self(
            "Age {$age} is out of bounds. Allowed range: {$minAllowed}-{$maxAllowed}"
        );
    }

    /**
     * Create exception for invalid distance radius
     * 
     * @param float $distance Distance value
     * @param float $minAllowed Minimum allowed distance
     * @param float $maxAllowed Maximum allowed distance
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function invalidDistanceRadius(float $distance, float $minAllowed, float $maxAllowed): self
    {
        return new self(
            "Distance radius {$distance} is invalid. Allowed range: {$minAllowed}-{$maxAllowed} km"
        );
    }

    /**
     * Create exception for invalid compatibility score
     * 
     * @param float $score Compatibility score
     * @param float $minAllowed Minimum allowed score
     * @param float $maxAllowed Maximum allowed score
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function invalidCompatibilityScore(float $score, float $minAllowed, float $maxAllowed): self
    {
        return new self(
            "Compatibility score {$score} is invalid. Allowed range: {$minAllowed}-{$maxAllowed}"
        );
    }

    /**
     * Create exception for incompatible users
     * 
     * @param string $userId1 First user ID
     * @param string $userId2 Second user ID
     * @param string $reason Reason for incompatibility
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function incompatibleUsers(string $userId1, string $userId2, string $reason): self
    {
        return new self(
            "Users {$userId1} and {$userId2} are incompatible: {$reason}"
        );
    }

    /**
     * Create exception for blocked users
     * 
     * @param string $userId1 First user ID
     * @param string $userId2 Second user ID
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function blockedUsers(string $userId1, string $userId2): self
    {
        return new self(
            "Users {$userId1} and {$userId2} are blocked from matching with each other"
        );
    }

    /**
     * Create exception for existing match
     * 
     * @param string $userId1 First user ID
     * @param string $userId2 Second user ID
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function existingMatch(string $userId1, string $userId2): self
    {
        return new self(
            "Active match already exists between users {$userId1} and {$userId2}"
        );
    }

    /**
     * Create exception for missing mutual like
     * 
     * @param string $userId1 First user ID
     * @param string $userId2 Second user ID
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function missingMutualLike(string $userId1, string $userId2): self
    {
        return new self(
            "Mutual like required for match creation between users {$userId1} and {$userId2}"
        );
    }

    /**
     * Create exception for invalid interest criteria
     * 
     * @param array $interests Invalid interests
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function invalidInterestCriteria(array $interests): self
    {
        return new self(
            "Invalid interest criteria: " . implode(', ', $interests)
        );
    }

    /**
     * Create exception for invalid lifestyle criteria
     * 
     * @param array $lifestyle Invalid lifestyle preferences
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function invalidLifestyleCriteria(array $lifestyle): self
    {
        return new self(
            "Invalid lifestyle criteria: " . implode(', ', $lifestyle)
        );
    }

    /**
     * Create exception for invalid personality criteria
     * 
     * @param array $personality Invalid personality traits
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function invalidPersonalityCriteria(array $personality): self
    {
        return new self(
            "Invalid personality criteria: " . implode(', ', $personality)
        );
    }

    /**
     * Create exception for conflicting criteria
     * 
     * @param array $conflicts Conflicting criteria
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function conflictingCriteria(array $conflicts): self
    {
        return new self(
            "Conflicting matching criteria: " . implode(', ', $conflicts)
        );
    }

    /**
     * Create exception for unsupported criteria combination
     * 
     * @param array $criteria Unsupported criteria combination
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function unsupportedCriteriaCombination(array $criteria): self
    {
        return new self(
            "Unsupported criteria combination: " . implode(', ', $criteria)
        );
    }

    /**
     * Create exception for premium-only criteria
     * 
     * @param string $criteria Premium-only criteria
     * @return self New InvalidMatchingCriteriaException instance
     */
    public static function premiumOnlyCriteria(string $criteria): self
    {
        return new self(
            "Criteria '{$criteria}' is only available for premium users"
        );
    }
}
