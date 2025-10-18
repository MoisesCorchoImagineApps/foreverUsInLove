<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;

/**
 * DistanceRadius Value Object
 * 
 * Represents a distance radius for location-based matching in the ForeverUsInLove platform.
 * Encapsulates distance measurements in different units (kilometers, miles) and provides
 * validation and conversion methods for distance-based matching criteria.
 * 
 * This Value Object ensures consistent handling of distance measurements across
 * the matching system, preventing unit confusion and providing accurate
 * location-based filtering.
 * 
 * @package App\Domain\Matching\ValueObjects
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 * @since 2024-01-01
 */
final class DistanceRadius
{
    private const MIN_DISTANCE_KM = 0.1;
    private const MAX_DISTANCE_KM = 500.0;
    private const KM_TO_MILES_RATIO = 0.621371;
    private const MILES_TO_KM_RATIO = 1.609344;

    /**
     * @param float $kilometers Distance in kilometers
     */
    private function __construct(
        private readonly float $kilometers
    ) {
        $this->validateDistance($kilometers);
    }

    /**
     * Create DistanceRadius from kilometers
     * 
     * @param float $kilometers Distance in kilometers
     * @return self New DistanceRadius instance
     */
    public static function fromKilometers(float $kilometers): self
    {
        return new self($kilometers);
    }

    /**
     * Create DistanceRadius from miles
     * 
     * @param float $miles Distance in miles
     * @return self New DistanceRadius instance
     */
    public static function fromMiles(float $miles): self
    {
        $kilometers = $miles * self::MILES_TO_KM_RATIO;
        return new self($kilometers);
    }

    /**
     * Create DistanceRadius from meters
     * 
     * @param float $meters Distance in meters
     * @return self New DistanceRadius instance
     */
    public static function fromMeters(float $meters): self
    {
        $kilometers = $meters / 1000;
        return new self($kilometers);
    }

    /**
     * Create DistanceRadius from array
     * 
     * @param array $data Distance data
     * @return self New DistanceRadius instance
     */
    public static function fromArray(array $data): self
    {
        if (isset($data['kilometers'])) {
            return self::fromKilometers($data['kilometers']);
        } elseif (isset($data['miles'])) {
            return self::fromMiles($data['miles']);
        } elseif (isset($data['meters'])) {
            return self::fromMeters($data['meters']);
        }

        throw new InvalidArgumentException('Distance data must contain kilometers, miles, or meters');
    }

    /**
     * Create default DistanceRadius (25 km)
     * 
     * @return self New DistanceRadius instance
     */
    public static function createDefault(): self
    {
        return new self(25.0);
    }

    /**
     * Create small DistanceRadius (5 km)
     * 
     * @return self New DistanceRadius instance
     */
    public static function createSmall(): self
    {
        return new self(5.0);
    }

    /**
     * Create medium DistanceRadius (25 km)
     * 
     * @return self New DistanceRadius instance
     */
    public static function createMedium(): self
    {
        return new self(25.0);
    }

    /**
     * Create large DistanceRadius (50 km)
     * 
     * @return self New DistanceRadius instance
     */
    public static function createLarge(): self
    {
        return new self(50.0);
    }

    /**
     * Create extra large DistanceRadius (100 km)
     * 
     * @return self New DistanceRadius instance
     */
    public static function createExtraLarge(): self
    {
        return new self(100.0);
    }

    /**
     * Get distance in kilometers
     * 
     * @return float Distance in kilometers
     */
    public function getKilometers(): float
    {
        return $this->kilometers;
    }

    /**
     * Get distance in miles
     * 
     * @return float Distance in miles
     */
    public function getMiles(): float
    {
        return $this->kilometers * self::KM_TO_MILES_RATIO;
    }

    /**
     * Get distance in meters
     * 
     * @return float Distance in meters
     */
    public function getMeters(): float
    {
        return $this->kilometers * 1000;
    }

    /**
     * Check if this is a small radius
     * 
     * @return bool True if radius <= 10 km
     */
    public function isSmall(): bool
    {
        return $this->kilometers <= 10.0;
    }

    /**
     * Check if this is a medium radius
     * 
     * @return bool True if radius > 10 km and <= 50 km
     */
    public function isMedium(): bool
    {
        return $this->kilometers > 10.0 && $this->kilometers <= 50.0;
    }

    /**
     * Check if this is a large radius
     * 
     * @return bool True if radius > 50 km
     */
    public function isLarge(): bool
    {
        return $this->kilometers > 50.0;
    }

    /**
     * Check if this is a very small radius (walking distance)
     * 
     * @return bool True if radius <= 2 km
     */
    public function isVerySmall(): bool
    {
        return $this->kilometers <= 2.0;
    }

    /**
     * Check if this is a very large radius (long distance)
     * 
     * @return bool True if radius > 100 km
     */
    public function isVeryLarge(): bool
    {
        return $this->kilometers > 100.0;
    }

    /**
     * Get radius category
     * 
     * @return string Radius category (very_small, small, medium, large, very_large)
     */
    public function getCategory(): string
    {
        if ($this->isVerySmall()) {
            return 'very_small';
        } elseif ($this->isSmall()) {
            return 'small';
        } elseif ($this->isMedium()) {
            return 'medium';
        } elseif ($this->isLarge()) {
            return 'large';
        } else {
            return 'very_large';
        }
    }

    /**
     * Get radius description
     * 
     * @return string Human-readable description
     */
    public function getDescription(): string
    {
        $descriptions = [
            'very_small' => 'Very close (walking distance)',
            'small' => 'Close (local area)',
            'medium' => 'Moderate distance',
            'large' => 'Wide area',
            'very_large' => 'Long distance'
        ];

        return $descriptions[$this->getCategory()] ?? 'Unknown';
    }

    /**
     * Check if a distance is within this radius
     * 
     * @param float $distanceKm Distance to check in kilometers
     * @return bool True if distance is within radius
     */
    public function containsDistance(float $distanceKm): bool
    {
        return $distanceKm <= $this->kilometers;
    }

    /**
     * Check if another DistanceRadius is within this radius
     * 
     * @param DistanceRadius $other Other radius to check
     * @return bool True if other radius is within this radius
     */
    public function contains(DistanceRadius $other): bool
    {
        return $other->kilometers <= $this->kilometers;
    }

    /**
     * Check if this radius overlaps with another
     * 
     * @param DistanceRadius $other Other radius to check
     * @return bool True if radii overlap
     */
    public function overlaps(DistanceRadius $other): bool
    {
        return $this->kilometers > 0 && $other->kilometers > 0;
    }

    /**
     * Check if this radius is larger than another
     * 
     * @param DistanceRadius $other Other radius to compare
     * @return bool True if this radius is larger
     */
    public function isLargerThan(DistanceRadius $other): bool
    {
        return $this->kilometers > $other->kilometers;
    }

    /**
     * Check if this radius is smaller than another
     * 
     * @param DistanceRadius $other Other radius to compare
     * @return bool True if this radius is smaller
     */
    public function isSmallerThan(DistanceRadius $other): bool
    {
        return $this->kilometers < $other->kilometers;
    }

    /**
     * Check if this radius equals another
     * 
     * @param DistanceRadius $other Other radius to compare
     * @return bool True if radii are equal
     */
    public function equals(DistanceRadius $other): bool
    {
        return abs($this->kilometers - $other->kilometers) < 0.001; // Float comparison with tolerance
    }

    /**
     * Get the difference between this radius and another
     * 
     * @param DistanceRadius $other Other radius to compare
     * @return DistanceRadius Difference radius
     */
    public function difference(DistanceRadius $other): DistanceRadius
    {
        $differenceKm = abs($this->kilometers - $other->kilometers);
        return new self($differenceKm);
    }

    /**
     * Get the average of this radius and another
     * 
     * @param DistanceRadius $other Other radius to average
     * @return DistanceRadius Average radius
     */
    public function average(DistanceRadius $other): DistanceRadius
    {
        $averageKm = ($this->kilometers + $other->kilometers) / 2;
        return new self($averageKm);
    }

    /**
     * Get the maximum of this radius and another
     * 
     * @param DistanceRadius $other Other radius to compare
     * @return DistanceRadius Maximum radius
     */
    public function max(DistanceRadius $other): DistanceRadius
    {
        return $this->kilometers >= $other->kilometers ? $this : $other;
    }

    /**
     * Get the minimum of this radius and another
     * 
     * @param DistanceRadius $other Other radius to compare
     * @return DistanceRadius Minimum radius
     */
    public function min(DistanceRadius $other): DistanceRadius
    {
        return $this->kilometers <= $other->kilometers ? $this : $other;
    }

    /**
     * Scale this radius by a factor
     * 
     * @param float $factor Scale factor
     * @return DistanceRadius Scaled radius
     */
    public function scale(float $factor): DistanceRadius
    {
        if ($factor <= 0) {
            throw new InvalidArgumentException('Scale factor must be positive');
        }

        $scaledKm = $this->kilometers * $factor;
        return new self($scaledKm);
    }

    /**
     * Get radius for different user types
     * 
     * @param string $userType User type (free, premium, vip)
     * @return DistanceRadius Appropriate radius for user type
     */
    public static function forUserType(string $userType): DistanceRadius
    {
        return match ($userType) {
            'free' => self::createSmall(),
            'premium' => self::createMedium(),
            'vip' => self::createLarge(),
            default => self::createDefault()
        };
    }

    /**
     * Get radius for different matching modes
     * 
     * @param string $mode Matching mode (local, regional, national)
     * @return DistanceRadius Appropriate radius for mode
     */
    public static function forMatchingMode(string $mode): DistanceRadius
    {
        return match ($mode) {
            'local' => self::createSmall(),
            'regional' => self::createMedium(),
            'national' => self::createExtraLarge(),
            default => self::createDefault()
        };
    }

    /**
     * Convert to array representation
     * 
     * @return array Array representation
     */
    public function toArray(): array
    {
        return [
            'kilometers' => $this->kilometers,
            'miles' => $this->getMiles(),
            'meters' => $this->getMeters(),
            'category' => $this->getCategory(),
            'description' => $this->getDescription(),
            'is_small' => $this->isSmall(),
            'is_medium' => $this->isMedium(),
            'is_large' => $this->isLarge()
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
        return sprintf('DistanceRadius(%.1f km, %.1f mi)', $this->kilometers, $this->getMiles());
    }

    /**
     * Validate distance value
     * 
     * @param float $kilometers Distance to validate
     * @throws InvalidArgumentException If distance is invalid
     */
    private function validateDistance(float $kilometers): void
    {
        if ($kilometers < self::MIN_DISTANCE_KM) {
            throw new InvalidArgumentException(
                sprintf('Distance must be at least %.1f km, got %.3f km', self::MIN_DISTANCE_KM, $kilometers)
            );
        }

        if ($kilometers > self::MAX_DISTANCE_KM) {
            throw new InvalidArgumentException(
                sprintf('Distance cannot exceed %.1f km, got %.3f km', self::MAX_DISTANCE_KM, $kilometers)
            );
        }

        if (!is_finite($kilometers)) {
            throw new InvalidArgumentException('Distance must be a finite number');
        }
    }
}
