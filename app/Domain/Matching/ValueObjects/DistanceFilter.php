<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * DistanceFilter Value Object
 * 
 * Representa un filtro de distancia en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de filtros de distancia en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para manejar
 * filtros de distancia geográfica en búsquedas y matching.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta distancias válidas (0-500 km)
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos DistanceFilter con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y filtros geográficos
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class DistanceFilter implements JsonSerializable
{
    /**
     * La distancia máxima en kilómetros
     */
    private readonly float $maxDistance;

    /**
     * La unidad de distancia
     */
    private readonly string $unit;

    /**
     * Si el filtro es estricto
     */
    private readonly bool $strict;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(float $maxDistance, string $unit, bool $strict)
    {
        $this->maxDistance = $maxDistance;
        $this->unit = $unit;
        $this->strict = $strict;
    }

    /**
     * Factory method para crear una nueva instancia de DistanceFilter
     *
     * @param float $maxDistance Distancia máxima (0-500)
     * @param string $unit Unidad de distancia ('km' o 'miles')
     * @param bool $strict Si el filtro es estricto
     * @return self Nueva instancia de DistanceFilter
     * @throws InvalidArgumentException Si los valores no son válidos
     */
    public static function create(float $maxDistance, string $unit = 'km', bool $strict = false): self
    {
        self::validateDistance($maxDistance, $unit);
        return new self($maxDistance, $unit, $strict);
    }

    /**
     * Factory method para crear DistanceFilter desde array
     *
     * @param array $filter Array con configuración del filtro
     * @return self Nueva instancia de DistanceFilter
     * @throws InvalidArgumentException Si el array no es válido
     */
    public static function fromArray(array $filter): self
    {
        $maxDistance = $filter['max_distance'] ?? $filter['distance'] ?? 50.0;
        $unit = $filter['unit'] ?? 'km';
        $strict = $filter['strict'] ?? false;

        return self::create($maxDistance, $unit, $strict);
    }

    /**
     * Factory methods para distancias predefinidas comunes
     */
    public static function local(): self
    {
        return new self(5.0, 'km', true);
    }

    public static function nearby(): self
    {
        return new self(15.0, 'km', false);
    }

    public static function city(): self
    {
        return new self(25.0, 'km', false);
    }

    public static function regional(): self
    {
        return new self(50.0, 'km', false);
    }

    public static function extended(): self
    {
        return new self(100.0, 'km', false);
    }

    public static function national(): self
    {
        return new self(200.0, 'km', false);
    }

    public static function global(): self
    {
        return new self(500.0, 'km', false);
    }

    /**
     * Obtiene la distancia máxima en kilómetros
     *
     * @return float Distancia máxima en kilómetros
     */
    public function getMaxDistance(): float
    {
        return $this->unit === 'km' ? $this->maxDistance : $this->maxDistance * 1.60934;
    }

    /**
     * Obtiene la distancia máxima en millas
     *
     * @return float Distancia máxima en millas
     */
    public function getMaxDistanceInMiles(): float
    {
        return $this->unit === 'miles' ? $this->maxDistance : $this->maxDistance * 0.621371;
    }

    /**
     * Obtiene la distancia máxima en la unidad original
     *
     * @return float Distancia máxima en la unidad original
     */
    public function getMaxDistanceInOriginalUnit(): float
    {
        return $this->maxDistance;
    }

    /**
     * Obtiene la unidad de distancia
     *
     * @return string Unidad de distancia
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    /**
     * Verifica si el filtro es estricto
     *
     * @return bool True si es estricto
     */
    public function isStrict(): bool
    {
        return $this->strict;
    }

    /**
     * Verifica si una distancia está dentro del filtro
     *
     * @param float $distance Distancia a verificar (en km)
     * @return bool True si está dentro del filtro
     */
    public function allowsDistance(float $distance): bool
    {
        $maxKm = $this->getMaxDistance();
        
        if ($this->strict) {
            return $distance <= $maxKm;
        }
        
        // Permitir 10% de tolerancia si no es estricto
        return $distance <= $maxKm * 1.1;
    }

    /**
     * Verifica si este filtro es local
     *
     * @return bool True si es local (≤ 10 km)
     */
    public function isLocal(): bool
    {
        return $this->getMaxDistance() <= 10;
    }

    /**
     * Verifica si este filtro es regional
     *
     * @return bool True si es regional (≤ 50 km)
     */
    public function isRegional(): bool
    {
        return $this->getMaxDistance() <= 50;
    }

    /**
     * Verifica si este filtro es nacional
     *
     * @return bool True si es nacional (≤ 200 km)
     */
    public function isNational(): bool
    {
        return $this->getMaxDistance() <= 200;
    }

    /**
     * Verifica si este filtro es global
     *
     * @return bool True si es global (> 200 km)
     */
    public function isGlobal(): bool
    {
        return $this->getMaxDistance() > 200;
    }

    /**
     * Verifica si este filtro es muy restrictivo
     *
     * @return bool True si es muy restrictivo (≤ 5 km)
     */
    public function isVeryRestrictive(): bool
    {
        return $this->getMaxDistance() <= 5;
    }

    /**
     * Verifica si este filtro es muy permisivo
     *
     * @return bool True si es muy permisivo (≥ 100 km)
     */
    public function isVeryPermissive(): bool
    {
        return $this->getMaxDistance() >= 100;
    }

    /**
     * Obtiene la categoría del filtro
     *
     * @return string Categoría (very_restrictive, local, regional, national, global, very_permissive)
     */
    public function getCategory(): string
    {
        if ($this->isVeryRestrictive()) {
            return 'very_restrictive';
        }

        if ($this->isLocal()) {
            return 'local';
        }

        if ($this->isRegional()) {
            return 'regional';
        }

        if ($this->isNational()) {
            return 'national';
        }

        if ($this->isGlobal()) {
            return 'global';
        }

        if ($this->isVeryPermissive()) {
            return 'very_permissive';
        }

        return 'balanced';
    }

    /**
     * Obtiene el nivel de selectividad del filtro
     * (1 = muy selectivo, 5 = muy permisivo)
     *
     * @return int Nivel de selectividad
     */
    public function getSelectivityLevel(): int
    {
        $distanceKm = $this->getMaxDistance();

        if ($distanceKm <= 5) {
            return 1; // Muy selectivo
        } elseif ($distanceKm <= 15) {
            return 2; // Selectivo
        } elseif ($distanceKm <= 50) {
            return 3; // Balanceado
        } elseif ($distanceKm <= 100) {
            return 4; // Permisivo
        } else {
            return 5; // Muy permisivo
        }
    }

    /**
     * Obtiene la descripción legible del filtro
     *
     * @return string Descripción del filtro
     */
    public function getDescription(): string
    {
        $distance = $this->getMaxDistanceInOriginalUnit();
        $unit = $this->unit === 'km' ? 'km' : 'millas';
        $strictText = $this->strict ? ' (estricto)' : '';

        return "Hasta {$distance} {$unit}{$strictText}";
    }

    /**
     * Verifica si este filtro es igual a otro
     *
     * @param self $other Otro filtro para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->getMaxDistance() === $other->getMaxDistance() &&
               $this->unit === $other->unit &&
               $this->strict === $other->strict;
    }

    /**
     * Obtiene la intersección con otro filtro de distancia
     *
     * @param self $other Otro filtro de distancia
     * @return self Filtro de distancia más restrictivo
     */
    public function intersection(self $other): self
    {
        $minDistance = min($this->getMaxDistance(), $other->getMaxDistance());
        $strict = $this->strict || $other->strict;

        return new self($minDistance, 'km', $strict);
    }

    /**
     * Obtiene la unión con otro filtro de distancia
     *
     * @param self $other Otro filtro de distancia
     * @return self Filtro de distancia más permisivo
     */
    public function union(self $other): self
    {
        $maxDistance = max($this->getMaxDistance(), $other->getMaxDistance());
        $strict = $this->strict && $other->strict;

        return new self($maxDistance, 'km', $strict);
    }

    /**
     * Crea un filtro más restrictivo
     *
     * @param float $factor Factor de reducción (0.1-1.0)
     * @return self Nuevo filtro más restrictivo
     */
    public function makeMoreRestrictive(float $factor = 0.8): self
    {
        $newDistance = $this->getMaxDistance() * $factor;
        return new self($newDistance, 'km', $this->strict);
    }

    /**
     * Crea un filtro más permisivo
     *
     * @param float $factor Factor de expansión (1.0-5.0)
     * @return self Nuevo filtro más permisivo
     */
    public function makeMorePermissive(float $factor = 1.5): self
    {
        $newDistance = min($this->getMaxDistance() * $factor, 500.0);
        return new self($newDistance, 'km', false); // Menos estricto
    }

    /**
     * Convierte el filtro a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'max_distance' => $this->getMaxDistance(),
            'max_distance_original' => $this->getMaxDistanceInOriginalUnit(),
            'max_distance_miles' => $this->getMaxDistanceInMiles(),
            'unit' => $this->unit,
            'strict' => $this->strict,
            'category' => $this->getCategory(),
            'selectivity_level' => $this->getSelectivityLevel(),
            'description' => $this->getDescription(),
            'is_local' => $this->isLocal(),
            'is_regional' => $this->isRegional(),
            'is_national' => $this->isNational(),
            'is_global' => $this->isGlobal(),
            'is_very_restrictive' => $this->isVeryRestrictive(),
            'is_very_permissive' => $this->isVeryPermissive(),
        ];
    }

    /**
     * Representación en string del filtro
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return $this->getDescription();
    }

    /**
     * Serialización para JSON
     *
     * @return array Valor para JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'max_distance' => $this->getMaxDistanceInOriginalUnit(),
            'unit' => $this->unit,
            'strict' => $this->strict
        ];
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'max_distance_km' => $this->getMaxDistance(),
            'unit' => $this->unit,
            'strict' => $this->strict,
            'category' => $this->getCategory(),
            'selectivity_level' => $this->getSelectivityLevel(),
        ];
    }

    /**
     * Valida que la distancia sea válida
     *
     * @param float $distance Distancia a validar
     * @param string $unit Unidad a validar
     * @throws InvalidArgumentException Si la distancia no es válida
     */
    private static function validateDistance(float $distance, string $unit): void
    {
        if ($distance < 0) {
            throw new InvalidArgumentException(
                "La distancia no puede ser negativa: {$distance}"
            );
        }

        if ($distance > 500) {
            throw new InvalidArgumentException(
                "La distancia no puede exceder 500 km: {$distance}"
            );
        }

        if (!in_array($unit, ['km', 'miles'])) {
            throw new InvalidArgumentException(
                "La unidad debe ser 'km' o 'miles', se recibió: {$unit}"
            );
        }
    }

    /**
     * Verifica si un valor es un DistanceFilter válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if ($value instanceof self) {
                return true;
            }

            if (is_array($value)) {
                self::fromArray($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea DistanceFilter desde un valor mixto
     *
     * @param mixed $value Valor del filtro de distancia
     * @return self Nueva instancia de DistanceFilter
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_array($value)) {
            return self::fromArray($value);
        }

        throw new InvalidArgumentException(
            'DistanceFilter solo puede crearse desde array o instancia de DistanceFilter, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para este filtro (útil para cache keys)
     *
     * @return string Hash único del filtro
     */
    public function getHash(): string
    {
        $distance = $this->getMaxDistanceInOriginalUnit();
        return 'distance_filter_' . $distance . '_' . $this->unit . '_' . ($this->strict ? 'strict' : 'flexible');
    }

    /**
     * Verifica si este filtro es compatible con el sistema de analytics
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si este filtro es compatible con el sistema de caché
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
