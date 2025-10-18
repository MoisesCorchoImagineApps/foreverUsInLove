<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * DiscoveryRadius Value Object
 * 
 * Representa el radio de descubrimiento en kilómetros en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del radio de descubrimiento en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para manejar
 * radios de búsqueda geográfica para descubrimiento de perfiles basado en ubicación
 * en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores positivos válidos en kilómetros
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos DiscoveryRadius con el mismo valor son iguales
 * - Matching-specific: Específico para el dominio de matching y descubrimiento geográfico
 * - Unit conversion: Soporte para conversión entre kilómetros y metros
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class DiscoveryRadius implements JsonSerializable
{
    /**
     * El valor del radio en kilómetros
     */
    private readonly float $kilometers;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(float $kilometers)
    {
        $this->kilometers = $kilometers;
    }

    /**
     * Factory method para crear una nueva instancia de DiscoveryRadius desde kilómetros
     *
     * @param float $kilometers Valor del radio en kilómetros
     * @return self Nueva instancia de DiscoveryRadius
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromKilometers(float $kilometers): self
    {
        self::validate($kilometers);
        return new self($kilometers);
    }

    /**
     * Factory method para crear DiscoveryRadius desde metros
     *
     * @param float $meters Valor del radio en metros
     * @return self Nueva instancia de DiscoveryRadius
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromMeters(float $meters): self
    {
        if ($meters < 0) {
            throw new InvalidArgumentException(
                "El radio en metros debe ser no negativo, se recibió: {$meters}"
            );
        }

        $kilometers = $meters / 1000.0;
        return new self($kilometers);
    }

    /**
     * Factory method para crear DiscoveryRadius desde string
     *
     * @param string $value Valor del radio como string
     * @return self Nueva instancia de DiscoveryRadius
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "DiscoveryRadius debe ser un número válido, se recibió: {$value}"
            );
        }

        $floatValue = (float) $value;
        return self::fromKilometers($floatValue);
    }

    /**
     * Factory method para crear DiscoveryRadius desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el radio
     * @param string $key Clave del array que contiene el radio
     * @return self Nueva instancia de DiscoveryRadius
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromArray(array $data, string $key = 'radius'): self
    {
        if (!isset($data[$key])) {
            throw new InvalidArgumentException(
                "Clave '{$key}' no encontrada en los datos proporcionados"
            );
        }

        return self::fromKilometers($data[$key]);
    }

    /**
     * Factory methods para crear radios predefinidos comunes
     */
    public static function veryClose(): self
    {
        return new self(1.0); // 1 km
    }

    public static function close(): self
    {
        return new self(5.0); // 5 km
    }

    public static function nearby(): self
    {
        return new self(10.0); // 10 km
    }

    public static function local(): self
    {
        return new self(25.0); // 25 km
    }

    public static function regional(): self
    {
        return new self(50.0); // 50 km
    }

    public static function extended(): self
    {
        return new self(100.0); // 100 km
    }

    public static function wide(): self
    {
        return new self(250.0); // 250 km
    }

    public static function global(): self
    {
        return new self(500.0); // 500 km
    }

    /**
     * Obtiene el valor del radio en kilómetros
     *
     * @return float Valor del radio en kilómetros
     */
    public function getKilometers(): float
    {
        return $this->kilometers;
    }

    /**
     * Obtiene el valor del radio en metros
     *
     * @return float Valor del radio en metros
     */
    public function getMeters(): float
    {
        return $this->kilometers * 1000.0;
    }

    /**
     * Obtiene el valor como string formateado
     *
     * @param int $decimals Número de decimales (por defecto 1)
     * @return string Valor del radio como string formateado
     */
    public function toString(int $decimals = 1): string
    {
        return number_format($this->kilometers, $decimals) . ' km';
    }

    /**
     * Verifica si este DiscoveryRadius es igual a otro
     *
     * @param self $other Otro DiscoveryRadius para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return abs($this->kilometers - $other->kilometers) < 0.01; // Tolerancia de 10 metros
    }

    /**
     * Verifica si este DiscoveryRadius es mayor que otro
     *
     * @param self $other Otro DiscoveryRadius para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->kilometers > $other->kilometers;
    }

    /**
     * Verifica si este DiscoveryRadius es menor que otro
     *
     * @param self $other Otro DiscoveryRadius para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->kilometers < $other->kilometers;
    }

    /**
     * Verifica si este DiscoveryRadius es mayor o igual que otro
     *
     * @param self $other Otro DiscoveryRadius para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->kilometers >= $other->kilometers;
    }

    /**
     * Verifica si este DiscoveryRadius es menor o igual que otro
     *
     * @param self $other Otro DiscoveryRadius para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->kilometers <= $other->kilometers;
    }

    /**
     * Verifica si este DiscoveryRadius está en un rango específico
     *
     * @param self $min Radio mínimo (inclusivo)
     * @param self $max Radio máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Suma otro radio a este radio
     *
     * @param self $other Otro radio a sumar
     * @return self Nuevo radio con la suma
     */
    public function add(self $other): self
    {
        return new self($this->kilometers + $other->kilometers);
    }

    /**
     * Resta otro radio de este radio
     *
     * @param self $other Otro radio a restar
     * @return self Nuevo radio con la resta
     */
    public function subtract(self $other): self
    {
        $result = $this->kilometers - $other->kilometers;
        if ($result < 0) {
            throw new InvalidArgumentException(
                "El resultado de la resta no puede ser negativo: {$result} km"
            );
        }
        return new self($result);
    }

    /**
     * Multiplica este radio por un factor
     *
     * @param float $factor Factor de multiplicación
     * @return self Nuevo radio multiplicado
     */
    public function multiply(float $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException(
                "El factor de multiplicación no puede ser negativo: {$factor}"
            );
        }
        return new self($this->kilometers * $factor);
    }

    /**
     * Divide este radio por un divisor
     *
     * @param float $divisor Divisor
     * @return self Nuevo radio dividido
     */
    public function divide(float $divisor): self
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException(
                "El divisor debe ser positivo: {$divisor}"
            );
        }
        return new self($this->kilometers / $divisor);
    }

    /**
     * Verifica si este DiscoveryRadius es muy cercano (≤ 1 km)
     *
     * @return bool True si es muy cercano
     */
    public function isVeryClose(): bool
    {
        return $this->kilometers <= 1.0;
    }

    /**
     * Verifica si este DiscoveryRadius es cercano (≤ 5 km)
     *
     * @return bool True si es cercano
     */
    public function isClose(): bool
    {
        return $this->kilometers <= 5.0;
    }

    /**
     * Verifica si este DiscoveryRadius es cercano (≤ 10 km)
     *
     * @return bool True si es cercano
     */
    public function isNearby(): bool
    {
        return $this->kilometers <= 10.0;
    }

    /**
     * Verifica si este DiscoveryRadius es local (≤ 25 km)
     *
     * @return bool True si es local
     */
    public function isLocal(): bool
    {
        return $this->kilometers <= 25.0;
    }

    /**
     * Verifica si este DiscoveryRadius es regional (≤ 50 km)
     *
     * @return bool True si es regional
     */
    public function isRegional(): bool
    {
        return $this->kilometers <= 50.0;
    }

    /**
     * Verifica si este DiscoveryRadius es extendido (≤ 100 km)
     *
     * @return bool True si es extendido
     */
    public function isExtended(): bool
    {
        return $this->kilometers <= 100.0;
    }

    /**
     * Verifica si este DiscoveryRadius es amplio (≤ 250 km)
     *
     * @return bool True si es amplio
     */
    public function isWide(): bool
    {
        return $this->kilometers <= 250.0;
    }

    /**
     * Verifica si este DiscoveryRadius es global (> 250 km)
     *
     * @return bool True si es global
     */
    public function isGlobal(): bool
    {
        return $this->kilometers > 250.0;
    }

    /**
     * Obtiene la categoría del radio basada en el valor
     *
     * @return string Categoría del radio (very_close, close, nearby, local, regional, extended, wide, global)
     */
    public function getCategory(): string
    {
        if ($this->isVeryClose()) {
            return 'very_close';
        }

        if ($this->isClose()) {
            return 'close';
        }

        if ($this->isNearby()) {
            return 'nearby';
        }

        if ($this->isLocal()) {
            return 'local';
        }

        if ($this->isRegional()) {
            return 'regional';
        }

        if ($this->isExtended()) {
            return 'extended';
        }

        if ($this->isWide()) {
            return 'wide';
        }

        return 'global';
    }

    /**
     * Obtiene la descripción legible del radio
     *
     * @return string Descripción del radio
     */
    public function getDescription(): string
    {
        return match ($this->getCategory()) {
            'very_close' => 'Muy cercano (≤ 1 km)',
            'close' => 'Cercano (≤ 5 km)',
            'nearby' => 'Cercano (≤ 10 km)',
            'local' => 'Local (≤ 25 km)',
            'regional' => 'Regional (≤ 50 km)',
            'extended' => 'Extendido (≤ 100 km)',
            'wide' => 'Amplio (≤ 250 km)',
            'global' => 'Global (> 250 km)',
            default => 'Radio personalizado',
        };
    }

    /**
     * Obtiene el nivel de prioridad del radio
     * (1 = alta prioridad, 5 = baja prioridad)
     *
     * @return int Nivel de prioridad
     */
    public function getPriorityLevel(): int
    {
        return match ($this->getCategory()) {
            'very_close' => 1, // Máxima prioridad para radios muy cercanos
            'close' => 2, // Alta prioridad para radios cercanos
            'nearby' => 2, // Alta prioridad para radios cercanos
            'local' => 3, // Prioridad media para radios locales
            'regional' => 3, // Prioridad media para radios regionales
            'extended' => 4, // Prioridad media-baja para radios extendidos
            'wide' => 4, // Prioridad media-baja para radios amplios
            'global' => 5, // Baja prioridad para radios globales
            default => 3,
        };
    }

    /**
     * Verifica si este DiscoveryRadius es adecuado para matching local
     *
     * @return bool True si es adecuado para matching local
     */
    public function isSuitableForLocalMatching(): bool
    {
        return $this->isLocal() || $this->isRegional();
    }

    /**
     * Verifica si este DiscoveryRadius es adecuado para matching regional
     *
     * @return bool True si es adecuado para matching regional
     */
    public function isSuitableForRegionalMatching(): bool
    {
        return $this->isRegional() || $this->isExtended();
    }

    /**
     * Verifica si este DiscoveryRadius es adecuado para matching global
     *
     * @return bool True si es adecuado para matching global
     */
    public function isSuitableForGlobalMatching(): bool
    {
        return $this->isWide() || $this->isGlobal();
    }

    /**
     * Obtiene el radio recomendado para el siguiente nivel
     *
     * @return self Radio recomendado para el siguiente nivel
     */
    public function getNextLevel(): self
    {
        return match ($this->getCategory()) {
            'very_close' => self::close(),
            'close' => self::nearby(),
            'nearby' => self::local(),
            'local' => self::regional(),
            'regional' => self::extended(),
            'extended' => self::wide(),
            'wide' => self::global(),
            'global' => self::global(), // Ya es el máximo
            default => self::local(),
        };
    }

    /**
     * Obtiene el radio recomendado para el nivel anterior
     *
     * @return self Radio recomendado para el nivel anterior
     */
    public function getPreviousLevel(): self
    {
        return match ($this->getCategory()) {
            'very_close' => self::veryClose(), // Ya es el mínimo
            'close' => self::veryClose(),
            'nearby' => self::close(),
            'local' => self::nearby(),
            'regional' => self::local(),
            'extended' => self::regional(),
            'wide' => self::extended(),
            'global' => self::wide(),
            default => self::local(),
        };
    }

    /**
     * Convierte el DiscoveryRadius a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'kilometers' => $this->kilometers,
            'meters' => $this->getMeters(),
            'formatted' => $this->toString(),
            'category' => $this->getCategory(),
            'description' => $this->getDescription(),
            'priority_level' => $this->getPriorityLevel(),
            'is_very_close' => $this->isVeryClose(),
            'is_close' => $this->isClose(),
            'is_nearby' => $this->isNearby(),
            'is_local' => $this->isLocal(),
            'is_regional' => $this->isRegional(),
            'is_extended' => $this->isExtended(),
            'is_wide' => $this->isWide(),
            'is_global' => $this->isGlobal(),
            'suitable_for_local_matching' => $this->isSuitableForLocalMatching(),
            'suitable_for_regional_matching' => $this->isSuitableForRegionalMatching(),
            'suitable_for_global_matching' => $this->isSuitableForGlobalMatching(),
        ];
    }

    /**
     * Representación en string del DiscoveryRadius
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Serialización para JSON
     *
     * @return float Valor para JSON
     */
    public function jsonSerialize(): float
    {
        return $this->kilometers;
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'kilometers' => $this->kilometers,
            'meters' => $this->getMeters(),
            'formatted' => $this->toString(),
            'category' => $this->getCategory(),
            'description' => $this->getDescription(),
            'priority_level' => $this->getPriorityLevel(),
        ];
    }

    /**
     * Valida que el valor del radio sea válido
     *
     * @param float $kilometers Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(float $kilometers): void
    {
        if ($kilometers < 0) {
            throw new InvalidArgumentException(
                "DiscoveryRadius debe ser un número no negativo, se recibió: {$kilometers}"
            );
        }

        if (!is_finite($kilometers)) {
            throw new InvalidArgumentException(
                "DiscoveryRadius debe ser un número finito, se recibió: {$kilometers}"
            );
        }

        // Validar que no exceda un límite razonable (ej: 1000 km)
        if ($kilometers > 1000) {
            throw new InvalidArgumentException(
                "DiscoveryRadius excede el valor máximo permitido (1000 km): {$kilometers}"
            );
        }
    }

    /**
     * Verifica si un valor es un DiscoveryRadius válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if (is_float($value)) {
                self::validate($value);
                return true;
            }

            if (is_string($value) && is_numeric($value)) {
                self::validate((float) $value);
                return true;
            }

            if (is_int($value)) {
                self::validate((float) $value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea un DiscoveryRadius desde un valor mixto (int, float, string, array)
     *
     * @param mixed $value Valor del radio
     * @param string $arrayKey Clave para arrays (por defecto 'radius')
     * @return self Nueva instancia de DiscoveryRadius
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value, string $arrayKey = 'radius'): self
    {
        if (is_float($value)) {
            return self::fromKilometers($value);
        }

        if (is_int($value)) {
            return self::fromKilometers((float) $value);
        }

        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_array($value)) {
            return self::fromArray($value, $arrayKey);
        }

        throw new InvalidArgumentException(
            'DiscoveryRadius solo puede crearse desde int, float, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para este DiscoveryRadius (útil para cache keys)
     *
     * @return string Hash único del DiscoveryRadius
     */
    public function getHash(): string
    {
        return 'discovery_radius_' . (int) ($this->kilometers * 100) . '_' . substr(md5((string) $this->kilometers), 0, 8);
    }

    /**
     * Verifica si este DiscoveryRadius es compatible con el sistema de analytics
     * (todos los radios pueden generar analytics)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si este DiscoveryRadius es compatible con el sistema de caché
     * (todos los radios pueden ser cacheados)
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
