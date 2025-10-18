<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * AgeRange Value Object
 * 
 * Representa un rango de edad en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del rango de edad en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para manejar
 * rangos de edad válidos en filtros de búsqueda y matching.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta rangos de edad válidos (18-100 años)
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos AgeRange con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y filtros
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class AgeRange implements JsonSerializable
{
    /**
     * La edad mínima del rango
     */
    private readonly int $minAge;

    /**
     * La edad máxima del rango
     */
    private readonly int $maxAge;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(int $minAge, int $maxAge)
    {
        $this->minAge = $minAge;
        $this->maxAge = $maxAge;
    }

    /**
     * Factory method para crear una nueva instancia de AgeRange
     *
     * @param int $minAge Edad mínima (18-100)
     * @param int $maxAge Edad máxima (18-100)
     * @return self Nueva instancia de AgeRange
     * @throws InvalidArgumentException Si los valores no son válidos
     */
    public static function create(int $minAge, int $maxAge): self
    {
        self::validateRange($minAge, $maxAge);
        return new self($minAge, $maxAge);
    }

    /**
     * Factory method para crear AgeRange desde array
     *
     * @param array $range Array con ['min' => int, 'max' => int] o [min, max]
     * @return self Nueva instancia de AgeRange
     * @throws InvalidArgumentException Si el array no es válido
     */
    public static function fromArray(array $range): self
    {
        if (isset($range['min']) && isset($range['max'])) {
            return self::create($range['min'], $range['max']);
        }

        if (count($range) === 2) {
            return self::create($range[0], $range[1]);
        }

        throw new InvalidArgumentException(
            'Array de rango de edad debe contener min/max o dos valores [min, max]'
        );
    }

    /**
     * Factory methods para rangos predefinidos comunes
     */
    public static function young(): self
    {
        return new self(18, 25);
    }

    public static function youngAdult(): self
    {
        return new self(20, 30);
    }

    public static function adult(): self
    {
        return new self(25, 40);
    }

    public static function mature(): self
    {
        return new self(30, 50);
    }

    public static function senior(): self
    {
        return new self(45, 65);
    }

    public static function open(): self
    {
        return new self(18, 100);
    }

    /**
     * Obtiene la edad mínima
     *
     * @return int Edad mínima
     */
    public function getMinAge(): int
    {
        return $this->minAge;
    }

    /**
     * Obtiene la edad máxima
     *
     * @return int Edad máxima
     */
    public function getMaxAge(): int
    {
        return $this->maxAge;
    }

    /**
     * Verifica si una edad está dentro del rango
     *
     * @param int $age Edad a verificar
     * @return bool True si está dentro del rango
     */
    public function contains(int $age): bool
    {
        return $age >= $this->minAge && $age <= $this->maxAge;
    }

    /**
     * Verifica si este rango se superpone con otro
     *
     * @param self $other Otro rango de edad
     * @return bool True si se superponen
     */
    public function overlaps(self $other): bool
    {
        return $this->minAge <= $other->maxAge && $this->maxAge >= $other->minAge;
    }

    /**
     * Verifica si este rango está completamente dentro de otro
     *
     * @param self $other Otro rango de edad
     * @return bool True si está completamente dentro
     */
    public function isWithin(self $other): bool
    {
        return $this->minAge >= $other->minAge && $this->maxAge <= $other->maxAge;
    }

    /**
     * Verifica si este rango contiene completamente a otro
     *
     * @param self $other Otro rango de edad
     * @return bool True si contiene completamente al otro
     */
    public function containsRange(self $other): bool
    {
        return $this->minAge <= $other->minAge && $this->maxAge >= $other->maxAge;
    }

    /**
     * Obtiene la intersección con otro rango
     *
     * @param self $other Otro rango de edad
     * @return self|null Rango de intersección o null si no se superponen
     */
    public function intersection(self $other): ?self
    {
        if (!$this->overlaps($other)) {
            return null;
        }

        $intersectionMin = max($this->minAge, $other->minAge);
        $intersectionMax = min($this->maxAge, $other->maxAge);

        return new self($intersectionMin, $intersectionMax);
    }

    /**
     * Obtiene la unión con otro rango
     *
     * @param self $other Otro rango de edad
     * @return self Rango de unión
     */
    public function union(self $other): self
    {
        $unionMin = min($this->minAge, $other->minAge);
        $unionMax = max($this->maxAge, $other->maxAge);

        return new self($unionMin, $unionMax);
    }

    /**
     * Obtiene el ancho del rango en años
     *
     * @return int Ancho del rango
     */
    public function getWidth(): int
    {
        return $this->maxAge - $this->minAge;
    }

    /**
     * Obtiene el punto medio del rango
     *
     * @return float Punto medio
     */
    public function getMidpoint(): float
    {
        return ($this->minAge + $this->maxAge) / 2;
    }

    /**
     * Verifica si este rango es igual a otro
     *
     * @param self $other Otro rango para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->minAge === $other->minAge && $this->maxAge === $other->maxAge;
    }

    /**
     * Verifica si este es un rango joven
     *
     * @return bool True si es un rango joven
     */
    public function isYoung(): bool
    {
        return $this->maxAge <= 25;
    }

    /**
     * Verifica si este es un rango adulto
     *
     * @return bool True si es un rango adulto
     */
    public function isAdult(): bool
    {
        return $this->minAge >= 25 && $this->maxAge <= 50;
    }

    /**
     * Verifica si este es un rango maduro
     *
     * @return bool True si es un rango maduro
     */
    public function isMature(): bool
    {
        return $this->minAge >= 40;
    }

    /**
     * Verifica si este es un rango amplio
     *
     * @return bool True si el rango es amplio (más de 20 años)
     */
    public function isWide(): bool
    {
        return $this->getWidth() > 20;
    }

    /**
     * Verifica si este es un rango estrecho
     *
     * @return bool True si el rango es estrecho (menos de 10 años)
     */
    public function isNarrow(): bool
    {
        return $this->getWidth() < 10;
    }

    /**
     * Obtiene la categoría del rango
     *
     * @return string Categoría (young, adult, mature, wide, narrow, balanced)
     */
    public function getCategory(): string
    {
        if ($this->isYoung()) {
            return 'young';
        }

        if ($this->isMature()) {
            return 'mature';
        }

        if ($this->isWide()) {
            return 'wide';
        }

        if ($this->isNarrow()) {
            return 'narrow';
        }

        if ($this->isAdult()) {
            return 'adult';
        }

        return 'balanced';
    }

    /**
     * Obtiene la descripción legible del rango
     *
     * @return string Descripción del rango
     */
    public function getDescription(): string
    {
        return "{$this->minAge}-{$this->maxAge} años";
    }

    /**
     * Convierte el rango a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'min' => $this->minAge,
            'max' => $this->maxAge,
            'width' => $this->getWidth(),
            'midpoint' => $this->getMidpoint(),
            'category' => $this->getCategory(),
            'description' => $this->getDescription(),
            'is_young' => $this->isYoung(),
            'is_adult' => $this->isAdult(),
            'is_mature' => $this->isMature(),
            'is_wide' => $this->isWide(),
            'is_narrow' => $this->isNarrow(),
        ];
    }

    /**
     * Representación en string del rango
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
            'min' => $this->minAge,
            'max' => $this->maxAge
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
            'min_age' => $this->minAge,
            'max_age' => $this->maxAge,
            'width' => $this->getWidth(),
            'category' => $this->getCategory(),
        ];
    }

    /**
     * Valida que el rango de edad sea válido
     *
     * @param int $minAge Edad mínima a validar
     * @param int $maxAge Edad máxima a validar
     * @throws InvalidArgumentException Si el rango no es válido
     */
    private static function validateRange(int $minAge, int $maxAge): void
    {
        if ($minAge < 18 || $minAge > 100) {
            throw new InvalidArgumentException(
                "La edad mínima debe estar entre 18 y 100 años, se recibió: {$minAge}"
            );
        }

        if ($maxAge < 18 || $maxAge > 100) {
            throw new InvalidArgumentException(
                "La edad máxima debe estar entre 18 y 100 años, se recibió: {$maxAge}"
            );
        }

        if ($minAge > $maxAge) {
            throw new InvalidArgumentException(
                "La edad mínima no puede ser mayor que la máxima: {$minAge} > {$maxAge}"
            );
        }
    }

    /**
     * Verifica si un valor es un AgeRange válido
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
     * Crea AgeRange desde un valor mixto
     *
     * @param mixed $value Valor del rango de edad
     * @return self Nueva instancia de AgeRange
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
            'AgeRange solo puede crearse desde array o instancia de AgeRange, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para este rango (útil para cache keys)
     *
     * @return string Hash único del rango
     */
    public function getHash(): string
    {
        return 'age_range_' . $this->minAge . '_' . $this->maxAge;
    }

    /**
     * Verifica si este rango es compatible con el sistema de analytics
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si este rango es compatible con el sistema de caché
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
