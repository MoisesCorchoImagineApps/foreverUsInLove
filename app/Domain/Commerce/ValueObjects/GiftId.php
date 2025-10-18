<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * GiftId Value Object
 * 
 * Representa el identificador único de un regalo en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del identificador de regalo en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para identificar
 * regalos virtuales que los usuarios pueden enviarse entre sí en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores enteros positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos GiftId con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y regalos
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class GiftId implements JsonSerializable
{
    /**
     * El valor del identificador de regalo
     */
    private readonly int $value;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(int $value)
    {
        $this->value = $value;
    }

    /**
     * Factory method para crear una nueva instancia de GiftId
     *
     * @param int $value Valor del identificador
     * @return self Nueva instancia de GiftId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromInt(int $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear GiftId desde string
     *
     * @param string $value Valor del identificador como string
     * @return self Nueva instancia de GiftId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "GiftId debe ser un número entero válido, se recibió: {$value}"
            );
        }

        $intValue = (int) $value;
        return self::fromInt($intValue);
    }

    /**
     * Factory method para crear GiftId desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el ID
     * @param string $key Clave del array que contiene el ID
     * @return self Nueva instancia de GiftId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromArray(array $data, string $key = 'id'): self
    {
        if (!isset($data[$key])) {
            throw new InvalidArgumentException(
                "Clave '{$key}' no encontrada en los datos proporcionados"
            );
        }

        return self::fromInt($data[$key]);
    }

    /**
     * Obtiene el valor entero del identificador
     *
     * @return int Valor del identificador
     */
    public function toInt(): int
    {
        return $this->value;
    }

    /**
     * Obtiene el valor como string
     *
     * @return string Valor del identificador como string
     */
    public function toString(): string
    {
        return (string) $this->value;
    }

    /**
     * Verifica si este GiftId es igual a otro
     *
     * @param self $other Otro GiftId para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este GiftId es mayor que otro
     *
     * @param self $other Otro GiftId para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este GiftId es menor que otro
     *
     * @param self $other Otro GiftId para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Verifica si este GiftId es mayor o igual que otro
     *
     * @param self $other Otro GiftId para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Verifica si este GiftId es menor o igual que otro
     *
     * @param self $other Otro GiftId para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * Verifica si este GiftId está en un rango específico
     *
     * @param self $min GiftId mínimo (inclusivo)
     * @param self $max GiftId máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Crea un nuevo GiftId incrementado en 1
     *
     * @return self Nuevo GiftId incrementado
     */
    public function increment(): self
    {
        return new self($this->value + 1);
    }

    /**
     * Crea un nuevo GiftId decrementado en 1
     *
     * @return self Nuevo GiftId decrementado
     */
    public function decrement(): self
    {
        return new self($this->value - 1);
    }

    /**
     * Verifica si este GiftId representa un regalo del sistema (ID <= 1000)
     * Estos suelen ser regalos por defecto, de prueba o del sistema
     *
     * @return bool True si es un regalo del sistema
     */
    public function isSystemGift(): bool
    {
        return $this->value <= 1000;
    }

    /**
     * Verifica si este GiftId representa un regalo regular de usuario (ID > 1000)
     *
     * @return bool True si es un regalo regular
     */
    public function isRegularGift(): bool
    {
        return $this->value > 1000;
    }

    /**
     * Verifica si este GiftId representa un regalo premium (ID alto, regalos especiales)
     *
     * @return bool True si es un regalo premium
     */
    public function isPremiumGift(): bool
    {
        return $this->value > 50000;
    }

    /**
     * Verifica si este GiftId representa un regalo limitado (ID en rangos específicos)
     *
     * @return bool True si es un regalo limitado
     */
    public function isLimitedGift(): bool
    {
        return $this->value >= 10000 && $this->value <= 20000;
    }

    /**
     * Verifica si este GiftId representa un regalo estacional (ID en rangos específicos)
     *
     * @return bool True si es un regalo estacional
     */
    public function isSeasonalGift(): bool
    {
        return $this->value >= 20000 && $this->value <= 30000;
    }

    /**
     * Verifica si este GiftId representa un regalo beta (ID bajo, regalos de prueba)
     *
     * @return bool True si es un regalo beta
     */
    public function isBetaGift(): bool
    {
        return $this->value >= 1 && $this->value <= 100;
    }

    /**
     * Obtiene el tipo de regalo basado en el ID
     *
     * @return string Tipo de regalo (beta, system, regular, premium, limited, seasonal)
     */
    public function getGiftType(): string
    {
        if ($this->isBetaGift()) {
            return 'beta';
        }

        if ($this->isSystemGift()) {
            return 'system';
        }

        if ($this->isLimitedGift()) {
            return 'limited';
        }

        if ($this->isSeasonalGift()) {
            return 'seasonal';
        }

        if ($this->isPremiumGift()) {
            return 'premium';
        }

        return 'regular';
    }

    /**
     * Verifica si este GiftId representa un regalo reciente
     * (IDs altos suelen indicar regalos más recientes)
     *
     * @return bool True si es un regalo reciente
     */
    public function isRecentGift(): bool
    {
        return $this->value > 10000;
    }

    /**
     * Verifica si este GiftId representa un regalo popular
     * (IDs en rangos específicos pueden indicar regalos populares)
     *
     * @return bool True si es un regalo popular
     */
    public function isPopularGift(): bool
    {
        return $this->value >= 5000 && $this->value <= 15000;
    }

    /**
     * Obtiene el año aproximado de creación basado en el ID
     * (útil para análisis de tendencias)
     *
     * @return int Año aproximado de creación
     */
    public function getEstimatedCreationYear(): int
    {
        // Estimación basada en patrones de IDs
        if ($this->value <= 1000) {
            return 2023; // Regalos del sistema y beta
        } elseif ($this->value <= 10000) {
            return 2024; // Regalos regulares tempranos
        } elseif ($this->value <= 50000) {
            return 2024; // Regalos regulares
        } else {
            return 2025; // Regalos premium y recientes
        }
    }

    /**
     * Verifica si este GiftId está en un rango de alta calidad
     * (regalos que han pasado por procesos de calidad)
     *
     * @return bool True si está en rango de alta calidad
     */
    public function isHighQualityRange(): bool
    {
        return $this->value >= 20000 && $this->value <= 80000;
    }

    /**
     * Convierte el GiftId a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getGiftType(),
            'is_premium' => $this->isPremiumGift(),
            'is_system' => $this->isSystemGift(),
            'is_limited' => $this->isLimitedGift(),
            'is_seasonal' => $this->isSeasonalGift(),
            'is_popular' => $this->isPopularGift(),
            'estimated_creation_year' => $this->getEstimatedCreationYear(),
        ];
    }

    /**
     * Representación en string del GiftId
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
     * @return int Valor para JSON
     */
    public function jsonSerialize(): int
    {
        return $this->value;
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'value' => $this->value,
            'type' => $this->getGiftType(),
            'is_premium' => $this->isPremiumGift(),
            'is_system' => $this->isSystemGift(),
            'is_popular' => $this->isPopularGift(),
            'estimated_creation_year' => $this->getEstimatedCreationYear(),
        ];
    }

    /**
     * Valida que el valor del ID sea válido
     *
     * @param int $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(int $value): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException(
                "GiftId debe ser un número entero positivo, se recibió: {$value}"
            );
        }

        if ($value > PHP_INT_MAX) {
            throw new InvalidArgumentException(
                "GiftId excede el valor máximo permitido: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un GiftId válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if (is_int($value)) {
                self::validate($value);
                return true;
            }

            if (is_string($value) && is_numeric($value)) {
                self::validate((int) $value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea un GiftId desde un valor mixto (int, string, array)
     *
     * @param mixed $value Valor del identificador
     * @param string $arrayKey Clave para arrays (por defecto 'id')
     * @return self Nueva instancia de GiftId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value, string $arrayKey = 'id'): self
    {
        if (is_int($value)) {
            return self::fromInt($value);
        }

        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_array($value)) {
            return self::fromArray($value, $arrayKey);
        }

        throw new InvalidArgumentException(
            'GiftId solo puede crearse desde int, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Verifica si este GiftId corresponde a un regalo con características específicas
     * (útil para filtros y búsquedas)
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['type'])) {
            if ($this->getGiftType() !== $criteria['type']) {
                return false;
            }
        }

        if (isset($criteria['premium_only']) && $criteria['premium_only']) {
            if (!$this->isPremiumGift()) {
                return false;
            }
        }

        if (isset($criteria['system_only']) && $criteria['system_only']) {
            if (!$this->isSystemGift()) {
                return false;
            }
        }

        if (isset($criteria['limited_only']) && $criteria['limited_only']) {
            if (!$this->isLimitedGift()) {
                return false;
            }
        }

        if (isset($criteria['seasonal_only']) && $criteria['seasonal_only']) {
            if (!$this->isSeasonalGift()) {
                return false;
            }
        }

        if (isset($criteria['popular_only']) && $criteria['popular_only']) {
            if (!$this->isPopularGift()) {
                return false;
            }
        }

        if (isset($criteria['recent_only']) && $criteria['recent_only']) {
            if (!$this->isRecentGift()) {
                return false;
            }
        }

        if (isset($criteria['high_quality_only']) && $criteria['high_quality_only']) {
            if (!$this->isHighQualityRange()) {
                return false;
            }
        }

        if (isset($criteria['min_id'])) {
            if ($this->value < $criteria['min_id']) {
                return false;
            }
        }

        if (isset($criteria['max_id'])) {
            if ($this->value > $criteria['max_id']) {
                return false;
            }
        }

        if (isset($criteria['year'])) {
            if ($this->getEstimatedCreationYear() !== $criteria['year']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del GiftId
     *
     * @return array Estadísticas del regalo
     */
    public function getStats(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getGiftType(),
            'is_premium' => $this->isPremiumGift(),
            'is_system' => $this->isSystemGift(),
            'is_regular' => $this->isRegularGift(),
            'is_limited' => $this->isLimitedGift(),
            'is_seasonal' => $this->isSeasonalGift(),
            'is_beta' => $this->isBetaGift(),
            'is_popular' => $this->isPopularGift(),
            'is_recent' => $this->isRecentGift(),
            'is_high_quality' => $this->isHighQualityRange(),
            'estimated_creation_year' => $this->getEstimatedCreationYear(),
            'creation_era' => $this->getCreationEra(),
        ];
    }

    /**
     * Obtiene la era de creación del regalo
     *
     * @return string Era de creación (beta, system, early, mid, recent, premium)
     */
    private function getCreationEra(): string
    {
        if ($this->value <= 100) {
            return 'beta';
        } elseif ($this->value <= 1000) {
            return 'system';
        } elseif ($this->value <= 10000) {
            return 'early';
        } elseif ($this->value <= 50000) {
            return 'mid';
        } else {
            return 'recent';
        }
    }

    /**
     * Genera un hash único para este GiftId (útil para cache keys)
     *
     * @return string Hash único del GiftId
     */
    public function getHash(): string
    {
        return 'gift_' . $this->value . '_' . substr(md5((string) $this->value), 0, 8);
    }

    /**
     * Verifica si este GiftId es compatible con el sistema de analytics
     * (regalos que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Los regalos beta y del sistema pueden no generar analytics completos
        return !$this->isBetaGift() && !$this->isSystemGift();
    }

    /**
     * Verifica si este GiftId es compatible con el sistema de popularidad
     * (regalos que pueden recibir likes, views, etc.)
     *
     * @return bool True si es compatible con popularidad
     */
    public function isPopularityCompatible(): bool
    {
        // Solo regalos regulares, premium y especiales pueden tener popularidad
        return $this->isRegularGift() || $this->isPremiumGift() || $this->isLimitedGift();
    }

    /**
     * Obtiene la prioridad de procesamiento del regalo
     * (útil para colas de procesamiento)
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        if ($this->isPremiumGift()) {
            return 1; // Alta prioridad para regalos premium
        }

        if ($this->isLimitedGift()) {
            return 2; // Alta prioridad para regalos limitados
        }

        if ($this->isSeasonalGift()) {
            return 2; // Alta prioridad para regalos estacionales
        }

        if ($this->isRecentGift()) {
            return 3; // Prioridad media para regalos recientes
        }

        if ($this->isRegularGift()) {
            return 4; // Prioridad media-baja para regalos regulares
        }

        return 5; // Muy baja prioridad para regalos del sistema y beta
    }

    /**
     * Obtiene el nivel de calidad esperado para este regalo
     *
     * @return string Nivel de calidad (low, medium, high, premium)
     */
    public function getExpectedQualityLevel(): string
    {
        if ($this->isPremiumGift()) {
            return 'premium';
        }

        if ($this->isLimitedGift() || $this->isSeasonalGift()) {
            return 'high';
        }

        if ($this->isHighQualityRange()) {
            return 'high';
        }

        if ($this->isRecentGift()) {
            return 'high';
        }

        if ($this->isRegularGift()) {
            return 'medium';
        }

        if ($this->isSystemGift()) {
            return 'low';
        }

        return 'medium';
    }
}
