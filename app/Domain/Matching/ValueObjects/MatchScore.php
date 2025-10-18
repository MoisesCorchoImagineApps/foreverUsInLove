<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use Carbon\CarbonInterface;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * MatchScore Value Object
 * 
 * Represents a compatibility score between two users in the ForeverUsInLove platform.
 * Contains the overall compatibility score, individual factor breakdowns, confidence level,
 * and metadata about the calculation process.
 * 
 * This Value Object encapsulates all scoring-related data for matches, ensuring
 * consistency in compatibility calculations and providing detailed insights into
 * why users are matched together.
 * 
 * @package App\Domain\Matching\ValueObjects
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 * @since 2024-01-01
 */
final class MatchScore
{
    private const MIN_SCORE = 0.0;
    private const MAX_SCORE = 1.0;
    private const MIN_CONFIDENCE = 0.0;
    private const MAX_CONFIDENCE = 1.0;

    /**
     * @param float $value Overall compatibility score (0.0 - 1.0)
     * @param array<CompatibilityFactor> $factors Individual compatibility factors
     * @param CarbonInterface $calculatedAt When the score was calculated
     * @param string $algorithmVersion Version of the algorithm used
     * @param float $confidenceLevel Confidence in the score accuracy (0.0 - 1.0)
     */
    private function __construct(
        private readonly float $value,
        private readonly array $factors,
        private readonly CarbonInterface $calculatedAt,
        private readonly string $algorithmVersion,
        private readonly float $confidenceLevel
    ) {
        $this->validateScore($value);
        $this->validateConfidence($confidenceLevel);
        $this->validateFactors($factors);
    }

    /**
     * Create a new MatchScore
     * 
     * @param float $value Overall compatibility score
     * @param array<CompatibilityFactor> $factors Individual factors
     * @param CarbonInterface|null $calculatedAt Calculation timestamp
     * @param string $algorithmVersion Algorithm version
     * @param float $confidenceLevel Confidence level
     * @return self New MatchScore instance
     */
    public static function create(
        float $value,
        array $factors = [],
        ?CarbonInterface $calculatedAt = null,
        string $algorithmVersion = '2.1',
        float $confidenceLevel = 0.8
    ): self {
        return new self(
            $value,
            $factors,
            $calculatedAt ?? Carbon::now(),
            $algorithmVersion,
            $confidenceLevel
        );
    }

    /**
     * Create a simple MatchScore with just the value
     * 
     * @param float $value Compatibility score
     * @return self New MatchScore instance
     */
    public static function fromValue(float $value): self
    {
        return self::create($value);
    }

    /**
     * Create MatchScore from array data
     * 
     * @param array $data Score data
     * @return self New MatchScore instance
     */
    public static function fromArray(array $data): self
    {
        $factors = [];
        if (isset($data['factors']) && is_array($data['factors'])) {
            foreach ($data['factors'] as $factorData) {
                $factors[] = CompatibilityFactor::fromArray($factorData);
            }
        }

        return self::create(
            $data['value'] ?? 0.0,
            $factors,
            isset($data['calculated_at']) ? Carbon::parse($data['calculated_at']) : null,
            $data['algorithm_version'] ?? '2.1',
            $data['confidence_level'] ?? 0.8
        );
    }

    /**
     * Get the overall compatibility score
     * 
     * @return float Compatibility score (0.0 - 1.0)
     */
    public function getValue(): float
    {
        return $this->value;
    }

    /**
     * Get individual compatibility factors
     * 
     * @return array<CompatibilityFactor> Individual factors
     */
    public function getFactors(): array
    {
        return $this->factors;
    }

    /**
     * Get a specific factor by name
     * 
     * @param string $factorName Factor name
     * @return CompatibilityFactor|null The factor or null if not found
     */
    public function getFactor(string $factorName): ?CompatibilityFactor
    {
        foreach ($this->factors as $factor) {
            if ($factor->getName() === $factorName) {
                return $factor;
            }
        }
        return null;
    }

    /**
     * Get the calculation timestamp
     * 
     * @return CarbonInterface When the score was calculated
     */
    public function getCalculatedAt(): CarbonInterface
    {
        return $this->calculatedAt;
    }

    /**
     * Get the algorithm version used
     * 
     * @return string Algorithm version
     */
    public function getAlgorithmVersion(): string
    {
        return $this->algorithmVersion;
    }

    /**
     * Get the confidence level
     * 
     * @return float Confidence level (0.0 - 1.0)
     */
    public function getConfidenceLevel(): float
    {
        return $this->confidenceLevel;
    }

    /**
     * Check if this is a high-quality match
     * 
     * @return bool True if score >= 0.8
     */
    public function isHighQuality(): bool
    {
        return $this->value >= 0.8;
    }

    /**
     * Check if this is a medium-quality match
     * 
     * @return bool True if score >= 0.6 and < 0.8
     */
    public function isMediumQuality(): bool
    {
        return $this->value >= 0.6 && $this->value < 0.8;
    }

    /**
     * Check if this is a low-quality match
     * 
     * @return bool True if score < 0.6
     */
    public function isLowQuality(): bool
    {
        return $this->value < 0.6;
    }

    /**
     * Check if this score is reliable (high confidence)
     * 
     * @return bool True if confidence >= 0.7
     */
    public function isReliable(): bool
    {
        return $this->confidenceLevel >= 0.7;
    }

    /**
     * Get the quality category
     * 
     * @return string Quality category (high, medium, low)
     */
    public function getQualityCategory(): string
    {
        if ($this->isHighQuality()) {
            return 'high';
        } elseif ($this->isMediumQuality()) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get the primary compatibility factor (highest scoring)
     * 
     * @return CompatibilityFactor|null Primary factor or null if no factors
     */
    public function getPrimaryFactor(): ?CompatibilityFactor
    {
        if (empty($this->factors)) {
            return null;
        }

        $primaryFactor = $this->factors[0];
        foreach ($this->factors as $factor) {
            if ($factor->getScore() > $primaryFactor->getScore()) {
                $primaryFactor = $factor;
            }
        }

        return $primaryFactor;
    }

    /**
     * Get the weakest compatibility factor (lowest scoring)
     * 
     * @return CompatibilityFactor|null Weakest factor or null if no factors
     */
    public function getWeakestFactor(): ?CompatibilityFactor
    {
        if (empty($this->factors)) {
            return null;
        }

        $weakestFactor = $this->factors[0];
        foreach ($this->factors as $factor) {
            if ($factor->getScore() < $weakestFactor->getScore()) {
                $weakestFactor = $factor;
            }
        }

        return $weakestFactor;
    }

    /**
     * Get factors above a threshold
     * 
     * @param float $threshold Minimum score threshold
     * @return array<CompatibilityFactor> Factors above threshold
     */
    public function getFactorsAboveThreshold(float $threshold): array
    {
        return array_filter($this->factors, fn(CompatibilityFactor $factor) => $factor->getScore() >= $threshold);
    }

    /**
     * Get factors below a threshold
     * 
     * @param float $threshold Maximum score threshold
     * @return array<CompatibilityFactor> Factors below threshold
     */
    public function getFactorsBelowThreshold(float $threshold): array
    {
        return array_filter($this->factors, fn(CompatibilityFactor $factor) => $factor->getScore() < $threshold);
    }

    /**
     * Check if this score is better than another
     * 
     * @param MatchScore $other Other MatchScore to compare
     * @return bool True if this score is better
     */
    public function isBetterThan(MatchScore $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Check if this score is worse than another
     * 
     * @param MatchScore $other Other MatchScore to compare
     * @return bool True if this score is worse
     */
    public function isWorseThan(MatchScore $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Check if this score equals another
     * 
     * @param MatchScore $other Other MatchScore to compare
     * @return bool True if scores are equal
     */
    public function equals(MatchScore $other): bool
    {
        return abs($this->value - $other->value) < 0.001; // Float comparison with tolerance
    }

    /**
     * Get the age of this score calculation
     * 
     * @return int Age in minutes
     */
    public function getAgeInMinutes(): int
    {
        return (int) Carbon::now()->diffInMinutes($this->calculatedAt);
    }

    /**
     * Check if this score is stale (older than specified minutes)
     * 
     * @param int $maxAgeMinutes Maximum age in minutes
     * @return bool True if score is stale
     */
    public function isStale(int $maxAgeMinutes = 60): bool
    {
        return $this->getAgeInMinutes() > $maxAgeMinutes;
    }

    /**
     * Convert to array representation
     * 
     * @return array Array representation
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'factors' => array_map(fn(CompatibilityFactor $factor) => $factor->toArray(), $this->factors),
            'calculated_at' => $this->calculatedAt->toISOString(),
            'algorithm_version' => $this->algorithmVersion,
            'confidence_level' => $this->confidenceLevel,
            'quality_category' => $this->getQualityCategory(),
            'is_reliable' => $this->isReliable(),
            'age_minutes' => $this->getAgeInMinutes()
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
        return sprintf('MatchScore(%.3f, %s, %.2f)', $this->value, $this->getQualityCategory(), $this->confidenceLevel);
    }

    /**
     * Validate the compatibility score
     * 
     * @param float $value Score to validate
     * @throws InvalidArgumentException If score is invalid
     */
    private function validateScore(float $value): void
    {
        if ($value < self::MIN_SCORE || $value > self::MAX_SCORE) {
            throw new InvalidArgumentException(
                sprintf('MatchScore must be between %.1f and %.1f, got %.3f', self::MIN_SCORE, self::MAX_SCORE, $value)
            );
        }
    }

    /**
     * Validate the confidence level
     * 
     * @param float $confidence Confidence to validate
     * @throws InvalidArgumentException If confidence is invalid
     */
    private function validateConfidence(float $confidence): void
    {
        if ($confidence < self::MIN_CONFIDENCE || $confidence > self::MAX_CONFIDENCE) {
            throw new InvalidArgumentException(
                sprintf('Confidence level must be between %.1f and %.1f, got %.3f', self::MIN_CONFIDENCE, self::MAX_CONFIDENCE, $confidence)
            );
        }
    }

    /**
     * Validate compatibility factors
     * 
     * @param array $factors Factors to validate
     * @throws InvalidArgumentException If factors are invalid
     */
    private function validateFactors(array $factors): void
    {
        foreach ($factors as $factor) {
            if (!$factor instanceof CompatibilityFactor) {
                throw new InvalidArgumentException('All factors must be CompatibilityFactor instances');
            }
        }
    }
}
