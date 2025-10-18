<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;

/**
 * CompatibilityFactor Value Object
 * 
 * Represents an individual compatibility factor between two users in the ForeverUsInLove platform.
 * Each factor represents a specific aspect of compatibility (age, location, interests, etc.)
 * with its own score and metadata.
 * 
 * This Value Object encapsulates individual compatibility calculations, allowing for
 * detailed analysis of why users are compatible and providing insights for
 * algorithm improvement.
 * 
 * @package App\Domain\Matching\ValueObjects
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 * @since 2024-01-01
 */
final class CompatibilityFactor
{
    private const MIN_SCORE = 0.0;
    private const MAX_SCORE = 1.0;
    private const MIN_WEIGHT = 0.0;
    private const MAX_WEIGHT = 1.0;

    /**
     * @param string $name Factor name (e.g., 'age_compatibility', 'shared_interests')
     * @param float $score Factor score (0.0 - 1.0)
     * @param float $weight Factor weight in overall calculation (0.0 - 1.0)
     * @param array<string, mixed> $metadata Additional factor metadata
     */
    private function __construct(
        private readonly string $name,
        private readonly float $score,
        private readonly float $weight,
        private readonly array $metadata
    ) {
        $this->validateName($name);
        $this->validateScore($score);
        $this->validateWeight($weight);
    }

    /**
     * Create a new CompatibilityFactor
     * 
     * @param string $name Factor name
     * @param float $score Factor score
     * @param float $weight Factor weight
     * @param array<string, mixed> $metadata Additional metadata
     * @return self New CompatibilityFactor instance
     */
    public static function create(
        string $name,
        float $score,
        float $weight = 1.0,
        array $metadata = []
    ): self {
        return new self($name, $score, $weight, $metadata);
    }

    /**
     * Create CompatibilityFactor from array
     * 
     * @param array $data Factor data
     * @return self New CompatibilityFactor instance
     */
    public static function fromArray(array $data): self
    {
        return self::create(
            $data['name'] ?? '',
            $data['score'] ?? 0.0,
            $data['weight'] ?? 1.0,
            $data['metadata'] ?? []
        );
    }

    /**
     * Create age compatibility factor
     * 
     * @param float $score Age compatibility score
     * @param int $ageDifference Age difference in years
     * @return self New CompatibilityFactor instance
     */
    public static function createAgeCompatibility(float $score, int $ageDifference): self
    {
        return self::create(
            'age_compatibility',
            $score,
            0.15,
            ['age_difference' => $ageDifference]
        );
    }

    /**
     * Create location proximity factor
     * 
     * @param float $score Location compatibility score
     * @param float $distanceKm Distance in kilometers
     * @return self New CompatibilityFactor instance
     */
    public static function createLocationProximity(float $score, float $distanceKm): self
    {
        return self::create(
            'location_proximity',
            $score,
            0.20,
            ['distance_km' => $distanceKm]
        );
    }

    /**
     * Create shared interests factor
     * 
     * @param float $score Interests compatibility score
     * @param array<string> $sharedInterests Shared interests
     * @param int $totalInterests Total interests count
     * @return self New CompatibilityFactor instance
     */
    public static function createSharedInterests(float $score, array $sharedInterests, int $totalInterests): self
    {
        return self::create(
            'shared_interests',
            $score,
            0.25,
            [
                'shared_interests' => $sharedInterests,
                'shared_count' => count($sharedInterests),
                'total_count' => $totalInterests,
                'match_percentage' => $totalInterests > 0 ? (count($sharedInterests) / $totalInterests) * 100 : 0
            ]
        );
    }

    /**
     * Create lifestyle alignment factor
     * 
     * @param float $score Lifestyle compatibility score
     * @param array<string> $alignedAspects Aligned lifestyle aspects
     * @return self New CompatibilityFactor instance
     */
    public static function createLifestyleAlignment(float $score, array $alignedAspects): self
    {
        return self::create(
            'lifestyle_alignment',
            $score,
            0.20,
            [
                'aligned_aspects' => $alignedAspects,
                'alignment_count' => count($alignedAspects)
            ]
        );
    }

    /**
     * Create personality match factor
     * 
     * @param float $score Personality compatibility score
     * @param array<string> $matchedTraits Matched personality traits
     * @return self New CompatibilityFactor instance
     */
    public static function createPersonalityMatch(float $score, array $matchedTraits): self
    {
        return self::create(
            'personality_match',
            $score,
            0.20,
            [
                'matched_traits' => $matchedTraits,
                'trait_count' => count($matchedTraits)
            ]
        );
    }

    /**
     * Get factor name
     * 
     * @return string Factor name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get factor score
     * 
     * @return float Factor score (0.0 - 1.0)
     */
    public function getScore(): float
    {
        return $this->score;
    }

    /**
     * Get factor weight
     * 
     * @return float Factor weight (0.0 - 1.0)
     */
    public function getWeight(): float
    {
        return $this->weight;
    }

    /**
     * Get factor metadata
     * 
     * @return array<string, mixed> Factor metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get a specific metadata value
     * 
     * @param string $key Metadata key
     * @param mixed $default Default value if key not found
     * @return mixed Metadata value or default
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get weighted score (score * weight)
     * 
     * @return float Weighted score
     */
    public function getWeightedScore(): float
    {
        return $this->score * $this->weight;
    }

    /**
     * Check if this is a high-scoring factor
     * 
     * @return bool True if score >= 0.8
     */
    public function isHighScore(): bool
    {
        return $this->score >= 0.8;
    }

    /**
     * Check if this is a medium-scoring factor
     * 
     * @return bool True if score >= 0.5 and < 0.8
     */
    public function isMediumScore(): bool
    {
        return $this->score >= 0.5 && $this->score < 0.8;
    }

    /**
     * Check if this is a low-scoring factor
     * 
     * @return bool True if score < 0.5
     */
    public function isLowScore(): bool
    {
        return $this->score < 0.5;
    }

    /**
     * Check if this is a high-weight factor
     * 
     * @return bool True if weight >= 0.2
     */
    public function isHighWeight(): bool
    {
        return $this->weight >= 0.2;
    }

    /**
     * Check if this is a low-weight factor
     * 
     * @return bool True if weight < 0.1
     */
    public function isLowWeight(): bool
    {
        return $this->weight < 0.1;
    }

    /**
     * Get factor category
     * 
     * @return string Factor category (demographic, behavioral, preference)
     */
    public function getCategory(): string
    {
        $demographicFactors = ['age_compatibility', 'location_proximity'];
        $behavioralFactors = ['personality_match', 'lifestyle_alignment'];
        $preferenceFactors = ['shared_interests', 'activity_preferences'];

        if (in_array($this->name, $demographicFactors)) {
            return 'demographic';
        } elseif (in_array($this->name, $behavioralFactors)) {
            return 'behavioral';
        } elseif (in_array($this->name, $preferenceFactors)) {
            return 'preference';
        }

        return 'other';
    }

    /**
     * Get factor importance level
     * 
     * @return string Importance level (high, medium, low)
     */
    public function getImportanceLevel(): string
    {
        $weightedScore = $this->getWeightedScore();
        
        if ($weightedScore >= 0.2) {
            return 'high';
        } elseif ($weightedScore >= 0.1) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Check if this factor is better than another
     * 
     * @param CompatibilityFactor $other Other factor to compare
     * @return bool True if this factor is better
     */
    public function isBetterThan(CompatibilityFactor $other): bool
    {
        return $this->score > $other->score;
    }

    /**
     * Check if this factor is worse than another
     * 
     * @param CompatibilityFactor $other Other factor to compare
     * @return bool True if this factor is worse
     */
    public function isWorseThan(CompatibilityFactor $other): bool
    {
        return $this->score < $other->score;
    }

    /**
     * Check if this factor equals another
     * 
     * @param CompatibilityFactor $other Other factor to compare
     * @return bool True if factors are equal
     */
    public function equals(CompatibilityFactor $other): bool
    {
        return $this->name === $other->name && 
               abs($this->score - $other->score) < 0.001 &&
               abs($this->weight - $other->weight) < 0.001;
    }

    /**
     * Get factor description
     * 
     * @return string Human-readable description
     */
    public function getDescription(): string
    {
        $descriptions = [
            'age_compatibility' => 'Age compatibility',
            'location_proximity' => 'Location proximity',
            'shared_interests' => 'Shared interests',
            'lifestyle_alignment' => 'Lifestyle alignment',
            'personality_match' => 'Personality match',
            'activity_preferences' => 'Activity preferences',
            'communication_style' => 'Communication style',
            'relationship_goals' => 'Relationship goals',
            'family_values' => 'Family values',
            'career_ambitions' => 'Career ambitions'
        ];

        return $descriptions[$this->name] ?? ucwords(str_replace('_', ' ', $this->name));
    }

    /**
     * Get factor insights
     * 
     * @return array Factor insights
     */
    public function getInsights(): array
    {
        $insights = [
            'name' => $this->name,
            'description' => $this->getDescription(),
            'score' => $this->score,
            'weight' => $this->weight,
            'weighted_score' => $this->getWeightedScore(),
            'category' => $this->getCategory(),
            'importance_level' => $this->getImportanceLevel(),
            'score_level' => $this->isHighScore() ? 'high' : ($this->isMediumScore() ? 'medium' : 'low'),
            'weight_level' => $this->isHighWeight() ? 'high' : ($this->isLowWeight() ? 'low' : 'medium')
        ];

        // Add specific insights based on factor type
        switch ($this->name) {
            case 'age_compatibility':
                $ageDiff = $this->getMetadataValue('age_difference', 0);
                $insights['age_difference'] = $ageDiff;
                $insights['age_compatibility_level'] = $ageDiff <= 5 ? 'excellent' : ($ageDiff <= 10 ? 'good' : 'fair');
                break;

            case 'location_proximity':
                $distance = $this->getMetadataValue('distance_km', 0);
                $insights['distance_km'] = $distance;
                $insights['proximity_level'] = $distance <= 10 ? 'very_close' : ($distance <= 25 ? 'close' : ($distance <= 50 ? 'moderate' : 'far'));
                break;

            case 'shared_interests':
                $sharedCount = $this->getMetadataValue('shared_count', 0);
                $totalCount = $this->getMetadataValue('total_count', 0);
                $matchPercentage = $this->getMetadataValue('match_percentage', 0);
                $insights['shared_count'] = $sharedCount;
                $insights['total_count'] = $totalCount;
                $insights['match_percentage'] = $matchPercentage;
                $insights['interest_match_level'] = $matchPercentage >= 50 ? 'high' : ($matchPercentage >= 25 ? 'medium' : 'low');
                break;
        }

        return $insights;
    }

    /**
     * Convert to array representation
     * 
     * @return array Array representation
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'score' => $this->score,
            'weight' => $this->weight,
            'weighted_score' => $this->getWeightedScore(),
            'metadata' => $this->metadata,
            'category' => $this->getCategory(),
            'importance_level' => $this->getImportanceLevel(),
            'description' => $this->getDescription()
        ];
    }

    /**
     * Convert to JSON representation
     * 
     * @return string JSON string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * String representation
     * 
     * @return string String representation
     */
    public function __toString(): string
    {
        return sprintf(
            'CompatibilityFactor(%s: %.3f, weight: %.2f)',
            $this->name,
            $this->score,
            $this->weight
        );
    }

    /**
     * Validate factor name
     * 
     * @param string $name Name to validate
     * @throws InvalidArgumentException If name is invalid
     */
    private function validateName(string $name): void
    {
        if (empty($name)) {
            throw new InvalidArgumentException('CompatibilityFactor name cannot be empty');
        }

        if (strlen($name) > 50) {
            throw new InvalidArgumentException('CompatibilityFactor name cannot exceed 50 characters');
        }

        if (!preg_match('/^[a-z_]+$/', $name)) {
            throw new InvalidArgumentException('CompatibilityFactor name must contain only lowercase letters and underscores');
        }
    }

    /**
     * Validate factor score
     * 
     * @param float $score Score to validate
     * @throws InvalidArgumentException If score is invalid
     */
    private function validateScore(float $score): void
    {
        if ($score < self::MIN_SCORE || $score > self::MAX_SCORE) {
            throw new InvalidArgumentException(
                sprintf('CompatibilityFactor score must be between %.1f and %.1f, got %.3f', self::MIN_SCORE, self::MAX_SCORE, $score)
            );
        }
    }

    /**
     * Validate factor weight
     * 
     * @param float $weight Weight to validate
     * @throws InvalidArgumentException If weight is invalid
     */
    private function validateWeight(float $weight): void
    {
        if ($weight < self::MIN_WEIGHT || $weight > self::MAX_WEIGHT) {
            throw new InvalidArgumentException(
                sprintf('CompatibilityFactor weight must be between %.1f and %.1f, got %.3f', self::MIN_WEIGHT, self::MAX_WEIGHT, $weight)
            );
        }
    }
}
