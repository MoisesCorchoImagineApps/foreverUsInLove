<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * CoinPackageId Value Object
 * 
 * Representa el identificador único de un paquete de monedas en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del identificador de paquete de monedas en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para identificar
 * paquetes de monedas virtuales que los usuarios pueden comprar para usar en la aplicación.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores enteros positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos CoinPackageId con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y paquetes de monedas
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class CoinPackageId implements JsonSerializable
{
    /**
     * El valor del identificador de paquete de monedas
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
     * Factory method para crear una nueva instancia de CoinPackageId
     *
     * @param int $value Valor del identificador
     * @return self Nueva instancia de CoinPackageId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromInt(int $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear CoinPackageId desde string
     *
     * @param string $value Valor del identificador como string
     * @return self Nueva instancia de CoinPackageId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "CoinPackageId debe ser un número entero válido, se recibió: {$value}"
            );
        }

        $intValue = (int) $value;
        return self::fromInt($intValue);
    }

    /**
     * Factory method para crear CoinPackageId desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el ID
     * @param string $key Clave del array que contiene el ID
     * @return self Nueva instancia de CoinPackageId
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
     * Verifica si este CoinPackageId es igual a otro
     *
     * @param self $other Otro CoinPackageId para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este CoinPackageId es mayor que otro
     *
     * @param self $other Otro CoinPackageId para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este CoinPackageId es menor que otro
     *
     * @param self $other Otro CoinPackageId para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Verifica si este CoinPackageId es mayor o igual que otro
     *
     * @param self $other Otro CoinPackageId para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Verifica si este CoinPackageId es menor o igual que otro
     *
     * @param self $other Otro CoinPackageId para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * Verifica si este CoinPackageId está en un rango específico
     *
     * @param self $min CoinPackageId mínimo (inclusivo)
     * @param self $max CoinPackageId máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Crea un nuevo CoinPackageId incrementado en 1
     *
     * @return self Nuevo CoinPackageId incrementado
     */
    public function increment(): self
    {
        return new self($this->value + 1);
    }

    /**
     * Crea un nuevo CoinPackageId decrementado en 1
     *
     * @return self Nuevo CoinPackageId decrementado
     */
    public function decrement(): self
    {
        return new self($this->value - 1);
    }

    /**
     * Verifica si este CoinPackageId representa un paquete pequeño (ID 1-10)
     * (paquetes con pocas monedas)
     *
     * @return bool True si es un paquete pequeño
     */
    public function isSmallPackage(): bool
    {
        return $this->value >= 1 && $this->value <= 10;
    }

    /**
     * Verifica si este CoinPackageId representa un paquete mediano (ID 11-20)
     * (paquetes con cantidad media de monedas)
     *
     * @return bool True si es un paquete mediano
     */
    public function isMediumPackage(): bool
    {
        return $this->value >= 11 && $this->value <= 20;
    }

    /**
     * Verifica si este CoinPackageId representa un paquete grande (ID 21-30)
     * (paquetes con muchas monedas)
     *
     * @return bool True si es un paquete grande
     */
    public function isLargePackage(): bool
    {
        return $this->value >= 21 && $this->value <= 30;
    }

    /**
     * Verifica si este CoinPackageId representa un paquete premium (ID 31-40)
     * (paquetes con bonus y características especiales)
     *
     * @return bool True si es un paquete premium
     */
    public function isPremiumPackage(): bool
    {
        return $this->value >= 31 && $this->value <= 40;
    }

    /**
     * Verifica si este CoinPackageId representa un paquete promocional (ID 100-199)
     * (paquetes con descuentos y ofertas especiales)
     *
     * @return bool True si es un paquete promocional
     */
    public function isPromotionalPackage(): bool
    {
        return $this->value >= 100 && $this->value <= 199;
    }

    /**
     * Verifica si este CoinPackageId representa un paquete estacional (ID 200-299)
     * (paquetes disponibles solo en temporadas específicas)
     *
     * @return bool True si es un paquete estacional
     */
    public function isSeasonalPackage(): bool
    {
        return $this->value >= 200 && $this->value <= 299;
    }

    /**
     * Verifica si este CoinPackageId representa un paquete del sistema (ID <= 1000)
     *
     * @return bool True si es un paquete del sistema
     */
    public function isSystemPackage(): bool
    {
        return $this->value <= 1000;
    }

    /**
     * Verifica si este CoinPackageId representa un paquete beta (ID 1-5)
     * (paquetes de prueba durante desarrollo)
     *
     * @return bool True si es un paquete beta
     */
    public function isBetaPackage(): bool
    {
        return $this->value >= 1 && $this->value <= 5;
    }

    /**
     * Obtiene el tipo de paquete basado en el ID
     *
     * @return string Tipo de paquete (beta, small, medium, large, premium, promotional, seasonal, system)
     */
    public function getPackageType(): string
    {
        if ($this->isBetaPackage()) {
            return 'beta';
        }

        if ($this->isSeasonalPackage()) {
            return 'seasonal';
        }

        if ($this->isPromotionalPackage()) {
            return 'promotional';
        }

        if ($this->isPremiumPackage()) {
            return 'premium';
        }

        if ($this->isLargePackage()) {
            return 'large';
        }

        if ($this->isMediumPackage()) {
            return 'medium';
        }

        if ($this->isSmallPackage()) {
            return 'small';
        }

        if ($this->isSystemPackage()) {
            return 'system';
        }

        return 'custom';
    }

    /**
     * Verifica si este CoinPackageId representa un paquete activo
     * (paquetes disponibles para compra)
     *
     * @return bool True si es un paquete activo
     */
    public function isActivePackage(): bool
    {
        return $this->isSmallPackage() || $this->isMediumPackage() || $this->isLargePackage() || $this->isPremiumPackage();
    }

    /**
     * Verifica si este CoinPackageId representa un paquete con bonus
     * (paquetes premium y promocionales suelen tener bonus)
     *
     * @return bool True si tiene bonus
     */
    public function hasBonus(): bool
    {
        return $this->isPremiumPackage() || $this->isPromotionalPackage() || $this->isSeasonalPackage();
    }

    /**
     * Verifica si este CoinPackageId representa un paquete limitado
     * (paquetes promocionales y estacionales son limitados)
     *
     * @return bool True si es un paquete limitado
     */
    public function isLimitedPackage(): bool
    {
        return $this->isPromotionalPackage() || $this->isSeasonalPackage();
    }

    /**
     * Obtiene el nivel del paquete (1-5, donde 5 es el más alto)
     *
     * @return int Nivel del paquete
     */
    public function getPackageLevel(): int
    {
        if ($this->isPremiumPackage()) {
            return 5;
        }

        if ($this->isLargePackage()) {
            return 4;
        }

        if ($this->isMediumPackage()) {
            return 3;
        }

        if ($this->isSmallPackage()) {
            return 2;
        }

        if ($this->isPromotionalPackage() || $this->isSeasonalPackage()) {
            return 1;
        }

        return 1; // Default level
    }

    /**
     * Verifica si este CoinPackageId tiene características específicas
     *
     * @param string $feature Característica a verificar
     * @return bool True si tiene la característica
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->getPackageFeatures();
        return in_array($feature, $features);
    }

    /**
     * Obtiene las características del paquete basadas en el tipo
     *
     * @return array Lista de características del paquete
     */
    public function getPackageFeatures(): array
    {
        $features = [];

        if ($this->isSmallPackage()) {
            $features = ['basic_coins', 'standard_pricing'];
        } elseif ($this->isMediumPackage()) {
            $features = ['medium_coins', 'better_pricing', 'small_bonus'];
        } elseif ($this->isLargePackage()) {
            $features = ['large_coins', 'good_pricing', 'medium_bonus'];
        } elseif ($this->isPremiumPackage()) {
            $features = ['premium_coins', 'best_pricing', 'large_bonus', 'exclusive_features'];
        } elseif ($this->isPromotionalPackage()) {
            $features = ['discounted_coins', 'limited_time', 'special_bonus'];
        } elseif ($this->isSeasonalPackage()) {
            $features = ['seasonal_coins', 'holiday_themed', 'special_bonus', 'limited_availability'];
        } elseif ($this->isBetaPackage()) {
            $features = ['beta_coins', 'testing_purpose', 'limited_availability'];
        }

        return $features;
    }

    /**
     * Convierte el CoinPackageId a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getPackageType(),
            'level' => $this->getPackageLevel(),
            'is_active' => $this->isActivePackage(),
            'has_bonus' => $this->hasBonus(),
            'is_limited' => $this->isLimitedPackage(),
            'features' => $this->getPackageFeatures(),
        ];
    }

    /**
     * Representación en string del CoinPackageId
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
            'type' => $this->getPackageType(),
            'level' => $this->getPackageLevel(),
            'is_active' => $this->isActivePackage(),
            'has_bonus' => $this->hasBonus(),
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
                "CoinPackageId debe ser un número entero positivo, se recibió: {$value}"
            );
        }

        if ($value > PHP_INT_MAX) {
            throw new InvalidArgumentException(
                "CoinPackageId excede el valor máximo permitido: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un CoinPackageId válido
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
     * Crea un CoinPackageId desde un valor mixto (int, string, array)
     *
     * @param mixed $value Valor del identificador
     * @param string $arrayKey Clave para arrays (por defecto 'id')
     * @return self Nueva instancia de CoinPackageId
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
            'CoinPackageId solo puede crearse desde int, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Verifica si este CoinPackageId corresponde a un paquete con características específicas
     * (útil para filtros y búsquedas)
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['type'])) {
            if ($this->getPackageType() !== $criteria['type']) {
                return false;
            }
        }

        if (isset($criteria['level'])) {
            if ($this->getPackageLevel() !== $criteria['level']) {
                return false;
            }
        }

        if (isset($criteria['active_only']) && $criteria['active_only']) {
            if (!$this->isActivePackage()) {
                return false;
            }
        }

        if (isset($criteria['bonus_only']) && $criteria['bonus_only']) {
            if (!$this->hasBonus()) {
                return false;
            }
        }

        if (isset($criteria['limited_only']) && $criteria['limited_only']) {
            if (!$this->isLimitedPackage()) {
                return false;
            }
        }

        if (isset($criteria['feature'])) {
            if (!$this->hasFeature($criteria['feature'])) {
                return false;
            }
        }

        if (isset($criteria['min_level'])) {
            if ($this->getPackageLevel() < $criteria['min_level']) {
                return false;
            }
        }

        if (isset($criteria['max_level'])) {
            if ($this->getPackageLevel() > $criteria['max_level']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del CoinPackageId
     *
     * @return array Estadísticas del paquete
     */
    public function getStats(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getPackageType(),
            'level' => $this->getPackageLevel(),
            'is_active' => $this->isActivePackage(),
            'has_bonus' => $this->hasBonus(),
            'is_limited' => $this->isLimitedPackage(),
            'features_count' => count($this->getPackageFeatures()),
            'features' => $this->getPackageFeatures(),
        ];
    }

    /**
     * Genera un hash único para este CoinPackageId (útil para cache keys)
     *
     * @return string Hash único del CoinPackageId
     */
    public function getHash(): string
    {
        return 'coin_package_' . $this->value . '_' . substr(md5((string) $this->value), 0, 8);
    }

    /**
     * Verifica si este CoinPackageId es compatible con el sistema de analytics
     * (paquetes que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Todos los paquetes activos pueden generar analytics
        return $this->isActivePackage();
    }

    /**
     * Verifica si este CoinPackageId es compatible con el sistema de facturación
     * (paquetes que pueden generar facturas)
     *
     * @return bool True si es compatible con facturación
     */
    public function isBillingCompatible(): bool
    {
        // Todos los paquetes pueden generar facturas (incluso los gratuitos)
        return true;
    }

    /**
     * Obtiene la prioridad de procesamiento del paquete
     * (útil para colas de procesamiento)
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        if ($this->isPremiumPackage()) {
            return 1; // Alta prioridad para paquetes premium
        }

        if ($this->isLargePackage()) {
            return 2; // Alta prioridad para paquetes grandes
        }

        if ($this->isLimitedPackage()) {
            return 2; // Alta prioridad para paquetes limitados
        }

        if ($this->isMediumPackage()) {
            return 3; // Prioridad media para paquetes medianos
        }

        if ($this->isSmallPackage()) {
            return 4; // Prioridad media-baja para paquetes pequeños
        }

        return 5; // Baja prioridad para paquetes del sistema y beta
    }
}
