<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;

/**
 * MatchingCriteria Value Object
 * 
 * Represents the criteria used for matching users in the ForeverUsInLove platform.
 * Contains age ranges, distance preferences, compatibility thresholds, and other
 * filtering criteria that determine how matches are generated.
 * 
 * This Value Object encapsulates all matching preferences and requirements,
 * ensuring consistent application of matching rules across the platform.
 * 
 * @package App\Domain\Matching\ValueObjects
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 * @since 2024-01-01
 */
final class MatchingCriteria
{
    private const MIN_AGE = 18;
    private const MAX_AGE = 100;
    private const MIN_DISTANCE_KM = 1.0;
    private const MAX_DISTANCE_KM = 500.0;
    private const MIN_COMPATIBILITY_SCORE = 0.0;
    private const MAX_COMPATIBILITY_SCORE = 1.0;

    /**
     * @param array $criteria Matching criteria configuration
     */
    private function __construct(
        private readonly array $criteria
    ) {
        $this->validateCriteria($criteria);
    }

    /**
     * Create new MatchingCriteria from array
     * 
     * @param array $criteria Criteria configuration
     * @return self New MatchingCriteria instance
     */
    public static function fromArray(array $criteria): self
    {
        return new self($criteria);
    }

    /**
     * Create default MatchingCriteria
     * 
     * @param int $userAge User's age for default age range
     * @return self New MatchingCriteria instance
     */
    public static function createDefault(int $userAge): self
    {
        return new self([
            'age_range' => [
                max(self::MIN_AGE, $userAge - 10),
                min(self::MAX_AGE, $userAge + 10)
            ],
            'distance_km' => 25.0,
            'min_compatibility_score' => 0.6,
            'exclude_blocked' => true,
            'exclude_already_matched' => true,
            'require_photos' => true,
            'require_verified' => false,
            'preferred_interests' => [],
            'lifestyle_preferences' => [],
            'personality_traits' => []
        ]);
    }

    /**
     * Create MatchingCriteria for premium users
     * 
     * @param int $userAge User's age
     * @return self New MatchingCriteria instance
     */
    public static function createPremium(int $userAge): self
    {
        return new self([
            'age_range' => [
                max(self::MIN_AGE, $userAge - 15),
                min(self::MAX_AGE, $userAge + 15)
            ],
            'distance_km' => 50.0,
            'min_compatibility_score' => 0.5,
            'exclude_blocked' => true,
            'exclude_already_matched' => true,
            'require_photos' => false,
            'require_verified' => false,
            'preferred_interests' => [],
            'lifestyle_preferences' => [],
            'personality_traits' => [],
            'premium_features' => [
                'unlimited_distance' => false,
                'advanced_filters' => true,
                'priority_matching' => true
            ]
        ]);
    }

    /**
     * Get age range
     * 
     * @return array{int, int} Age range [min, max]
     */
    public function getAgeRange(): array
    {
        return $this->criteria['age_range'] ?? [self::MIN_AGE, self::MAX_AGE];
    }

    /**
     * Get distance radius
     * 
     * @return DistanceRadius Distance radius
     */
    public function getDistanceRadius(): DistanceRadius
    {
        if (isset($this->criteria['distance_radius']) && $this->criteria['distance_radius'] instanceof DistanceRadius) {
            return $this->criteria['distance_radius'];
        }
        
        $distanceKm = $this->criteria['distance_km'] ?? 25.0;
        return DistanceRadius::fromKilometers($distanceKm);
    }

    /**
     * Get minimum compatibility score
     * 
     * @return float Minimum compatibility score
     */
    public function getMinCompatibilityScore(): float
    {
        return $this->criteria['min_compatibility_score'] ?? 0.6;
    }

    /**
     * Check if blocked users should be excluded
     * 
     * @return bool True if blocked users should be excluded
     */
    public function shouldExcludeBlocked(): bool
    {
        return $this->criteria['exclude_blocked'] ?? true;
    }

    /**
     * Check if already matched users should be excluded
     * 
     * @return bool True if already matched users should be excluded
     */
    public function shouldExcludeAlreadyMatched(): bool
    {
        return $this->criteria['exclude_already_matched'] ?? true;
    }

    /**
     * Check if photos are required
     * 
     * @return bool True if photos are required
     */
    public function requiresPhotos(): bool
    {
        return $this->criteria['require_photos'] ?? true;
    }

    /**
     * Check if verification is required
     * 
     * @return bool True if verification is required
     */
    public function requiresVerification(): bool
    {
        return $this->criteria['require_verified'] ?? false;
    }

    /**
     * Get preferred interests
     * 
     * @return array<string> Preferred interests
     */
    public function getPreferredInterests(): array
    {
        return $this->criteria['preferred_interests'] ?? [];
    }

    /**
     * Get lifestyle preferences
     * 
     * @return array<string> Lifestyle preferences
     */
    public function getLifestylePreferences(): array
    {
        return $this->criteria['lifestyle_preferences'] ?? [];
    }

    /**
     * Get personality traits
     * 
     * @return array<string> Personality traits
     */
    public function getPersonalityTraits(): array
    {
        return $this->criteria['personality_traits'] ?? [];
    }

    /**
     * Get premium features
     * 
     * @return array<string, mixed> Premium features configuration
     */
    public function getPremiumFeatures(): array
    {
        return $this->criteria['premium_features'] ?? [];
    }

    /**
     * Check if a specific premium feature is enabled
     * 
     * @param string $feature Feature name
     * @return bool True if feature is enabled
     */
    public function hasPremiumFeature(string $feature): bool
    {
        $premiumFeatures = $this->getPremiumFeatures();
        return $premiumFeatures[$feature] ?? false;
    }

    /**
     * Check if user age is within range
     * 
     * @param int $age User age to check
     * @return bool True if age is within range
     */
    public function isAgeInRange(int $age): bool
    {
        [$minAge, $maxAge] = $this->getAgeRange();
        return $age >= $minAge && $age <= $maxAge;
    }

    /**
     * Check if distance is within radius
     * 
     * @param float $distanceKm Distance in kilometers
     * @return bool True if distance is within radius
     */
    public function isDistanceInRange(float $distanceKm): bool
    {
        return $distanceKm <= $this->getDistanceRadius()->getKilometers();
    }

    /**
     * Check if compatibility score meets minimum requirement
     * 
     * @param float $score Compatibility score
     * @return bool True if score meets requirement
     */
    public function meetsCompatibilityThreshold(float $score): bool
    {
        return $score >= $this->getMinCompatibilityScore();
    }

    /**
     * Check if interests match preferences
     * 
     * @param array<string> $userInterests User's interests
     * @return bool True if interests match
     */
    public function interestsMatch(array $userInterests): bool
    {
        $preferredInterests = $this->getPreferredInterests();
        
        if (empty($preferredInterests)) {
            return true; // No specific preferences
        }

        return !empty(array_intersect($preferredInterests, $userInterests));
    }

    /**
     * Check if lifestyle matches preferences
     * 
     * @param array<string> $userLifestyle User's lifestyle
     * @return bool True if lifestyle matches
     */
    public function lifestyleMatches(array $userLifestyle): bool
    {
        $preferredLifestyle = $this->getLifestylePreferences();
        
        if (empty($preferredLifestyle)) {
            return true; // No specific preferences
        }

        return !empty(array_intersect($preferredLifestyle, $userLifestyle));
    }

    /**
     * Check if personality matches traits
     * 
     * @param array<string> $userPersonality User's personality traits
     * @return bool True if personality matches
     */
    public function personalityMatches(array $userPersonality): bool
    {
        $preferredTraits = $this->getPersonalityTraits();
        
        if (empty($preferredTraits)) {
            return true; // No specific preferences
        }

        return !empty(array_intersect($preferredTraits, $userPersonality));
    }

    /**
     * Get criteria as array
     * 
     * @return array Criteria array
     */
    public function toArray(): array
    {
        $array = $this->criteria;
        
        // Convert DistanceRadius to array if present
        if (isset($array['distance_radius']) && $array['distance_radius'] instanceof DistanceRadius) {
            $array['distance_radius'] = $array['distance_radius']->toArray();
        }

        return $array;
    }

    /**
     * Merge with another MatchingCriteria
     * 
     * @param MatchingCriteria $other Other criteria to merge
     * @return self New merged criteria
     */
    public function merge(MatchingCriteria $other): self
    {
        $mergedCriteria = array_merge($this->criteria, $other->criteria);
        return new self($mergedCriteria);
    }

    /**
     * Create a copy with updated criteria
     * 
     * @param array $updates Criteria updates
     * @return self New criteria with updates
     */
    public function withUpdates(array $updates): self
    {
        $updatedCriteria = array_merge($this->criteria, $updates);
        return new self($updatedCriteria);
    }

    /**
     * Check if criteria are strict (high requirements)
     * 
     * @return bool True if criteria are strict
     */
    public function isStrict(): bool
    {
        return $this->requiresPhotos() && 
               $this->requiresVerification() && 
               $this->getMinCompatibilityScore() >= 0.8;
    }

    /**
     * Check if criteria are relaxed (low requirements)
     * 
     * @return bool True if criteria are relaxed
     */
    public function isRelaxed(): bool
    {
        return !$this->requiresPhotos() && 
               !$this->requiresVerification() && 
               $this->getMinCompatibilityScore() <= 0.4;
    }

    /**
     * Get criteria summary
     * 
     * @return array Criteria summary
     */
    public function getSummary(): array
    {
        return [
            'age_range' => $this->getAgeRange(),
            'distance_km' => $this->getDistanceRadius()->getKilometers(),
            'min_compatibility' => $this->getMinCompatibilityScore(),
            'exclude_blocked' => $this->shouldExcludeBlocked(),
            'exclude_matched' => $this->shouldExcludeAlreadyMatched(),
            'require_photos' => $this->requiresPhotos(),
            'require_verified' => $this->requiresVerification(),
            'preferred_interests_count' => count($this->getPreferredInterests()),
            'lifestyle_preferences_count' => count($this->getLifestylePreferences()),
            'personality_traits_count' => count($this->getPersonalityTraits()),
            'is_strict' => $this->isStrict(),
            'is_relaxed' => $this->isRelaxed(),
            'premium_features' => array_keys(array_filter($this->getPremiumFeatures()))
        ];
    }

    /**
     * String representation
     * 
     * @return string String representation
     */
    public function __toString(): string
    {
        [$minAge, $maxAge] = $this->getAgeRange();
        return sprintf(
            'MatchingCriteria(age:%d-%d, distance:%dkm, score:%.2f)',
            $minAge,
            $maxAge,
            $this->getDistanceRadius()->getKilometers(),
            $this->getMinCompatibilityScore()
        );
    }

    /**
     * Validate criteria configuration
     * 
     * @param array $criteria Criteria to validate
     * @throws InvalidArgumentException If criteria are invalid
     */
    private function validateCriteria(array $criteria): void
    {
        // Validate age range
        if (isset($criteria['age_range'])) {
            if (!is_array($criteria['age_range']) || count($criteria['age_range']) !== 2) {
                throw new InvalidArgumentException('Age range must be an array with exactly 2 elements');
            }

            [$minAge, $maxAge] = $criteria['age_range'];
            if (!is_int($minAge) || !is_int($maxAge)) {
                throw new InvalidArgumentException('Age range values must be integers');
            }

            if ($minAge < self::MIN_AGE || $maxAge > self::MAX_AGE || $minAge > $maxAge) {
                throw new InvalidArgumentException(
                    sprintf('Age range must be between %d and %d, with min <= max', self::MIN_AGE, self::MAX_AGE)
                );
            }
        }

        // Validate distance radius
        if (isset($criteria['distance_radius'])) {
            if (!$criteria['distance_radius'] instanceof DistanceRadius) {
                throw new InvalidArgumentException('Distance radius must be a DistanceRadius instance');
            }
        }

        // Validate distance_km
        if (isset($criteria['distance_km'])) {
            $distanceKm = $criteria['distance_km'];
            if (!is_float($distanceKm) && !is_int($distanceKm)) {
                throw new InvalidArgumentException('Distance in kilometers must be a number');
            }

            if ($distanceKm < self::MIN_DISTANCE_KM || $distanceKm > self::MAX_DISTANCE_KM) {
                throw new InvalidArgumentException(
                    sprintf('Distance must be between %.1f and %.1f km', self::MIN_DISTANCE_KM, self::MAX_DISTANCE_KM)
                );
            }
        }

        // Validate compatibility score
        if (isset($criteria['min_compatibility_score'])) {
            $score = $criteria['min_compatibility_score'];
            if (!is_float($score) && !is_int($score)) {
                throw new InvalidArgumentException('Minimum compatibility score must be a number');
            }

            if ($score < self::MIN_COMPATIBILITY_SCORE || $score > self::MAX_COMPATIBILITY_SCORE) {
                throw new InvalidArgumentException(
                    sprintf('Compatibility score must be between %.1f and %.1f', self::MIN_COMPATIBILITY_SCORE, self::MAX_COMPATIBILITY_SCORE)
                );
            }
        }

        // Validate boolean flags
        $booleanFields = [
            'exclude_blocked',
            'exclude_already_matched',
            'require_photos',
            'require_verified'
        ];

        foreach ($booleanFields as $field) {
            if (isset($criteria[$field]) && !is_bool($criteria[$field])) {
                throw new InvalidArgumentException("Field '{$field}' must be a boolean");
            }
        }

        // Validate array fields
        $arrayFields = [
            'preferred_interests',
            'lifestyle_preferences',
            'personality_traits'
        ];

        foreach ($arrayFields as $field) {
            if (isset($criteria[$field]) && !is_array($criteria[$field])) {
                throw new InvalidArgumentException("Field '{$field}' must be an array");
            }
        }
    }
}
