<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * ProductId Value Object
 * 
 * Representa el identificador único de un producto en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del identificador de producto en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para identificar
 * productos genéricos que pueden incluir regalos, planes, paquetes de monedas y otros
 * elementos comerciales en la aplicación.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores enteros positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos ProductId con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y productos
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class ProductId implements JsonSerializable
{
    /**
     * El valor del identificador de producto
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
     * Factory method para crear una nueva instancia de ProductId
     *
     * @param int $value Valor del identificador
     * @return self Nueva instancia de ProductId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromInt(int $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear ProductId desde string
     *
     * @param string $value Valor del identificador como string
     * @return self Nueva instancia de ProductId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "ProductId debe ser un número entero válido, se recibió: {$value}"
            );
        }

        $intValue = (int) $value;
        return self::fromInt($intValue);
    }

    /**
     * Factory method para crear ProductId desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el ID
     * @param string $key Clave del array que contiene el ID
     * @return self Nueva instancia de ProductId
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
     * Verifica si este ProductId es igual a otro
     *
     * @param self $other Otro ProductId para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este ProductId es mayor que otro
     *
     * @param self $other Otro ProductId para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este ProductId es menor que otro
     *
     * @param self $other Otro ProductId para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Verifica si este ProductId es mayor o igual que otro
     *
     * @param self $other Otro ProductId para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Verifica si este ProductId es menor o igual que otro
     *
     * @param self $other Otro ProductId para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * Verifica si este ProductId está en un rango específico
     *
     * @param self $min ProductId mínimo (inclusivo)
     * @param self $max ProductId máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Crea un nuevo ProductId incrementado en 1
     *
     * @return self Nuevo ProductId incrementado
     */
    public function increment(): self
    {
        return new self($this->value + 1);
    }

    /**
     * Crea un nuevo ProductId decrementado en 1
     *
     * @return self Nuevo ProductId decrementado
     */
    public function decrement(): self
    {
        return new self($this->value - 1);
    }

    /**
     * Verifica si este ProductId representa un producto básico (ID 1-1000)
     * (productos estándar del sistema)
     *
     * @return bool True si es un producto básico
     */
    public function isBasicProduct(): bool
    {
        return $this->value >= 1 && $this->value <= 1000;
    }

    /**
     * Verifica si este ProductId representa un producto premium (ID 1001-5000)
     * (productos con características avanzadas)
     *
     * @return bool True si es un producto premium
     */
    public function isPremiumProduct(): bool
    {
        return $this->value >= 1001 && $this->value <= 5000;
    }

    /**
     * Verifica si este ProductId representa un producto VIP (ID 5001-10000)
     * (productos exclusivos para usuarios VIP)
     *
     * @return bool True si es un producto VIP
     */
    public function isVipProduct(): bool
    {
        return $this->value >= 5001 && $this->value <= 10000;
    }

    /**
     * Verifica si este ProductId representa un producto promocional (ID 10000-19999)
     * (productos con ofertas y descuentos especiales)
     *
     * @return bool True si es un producto promocional
     */
    public function isPromotionalProduct(): bool
    {
        return $this->value >= 10000 && $this->value <= 19999;
    }

    /**
     * Verifica si este ProductId representa un producto estacional (ID 20000-29999)
     * (productos disponibles solo en temporadas específicas)
     *
     * @return bool True si es un producto estacional
     */
    public function isSeasonalProduct(): bool
    {
        return $this->value >= 20000 && $this->value <= 29999;
    }

    /**
     * Verifica si este ProductId representa un producto limitado (ID 30000-39999)
     * (productos con disponibilidad limitada)
     *
     * @return bool True si es un producto limitado
     */
    public function isLimitedProduct(): bool
    {
        return $this->value >= 30000 && $this->value <= 39999;
    }

    /**
     * Verifica si este ProductId representa un producto del sistema (ID <= 1000)
     *
     * @return bool True si es un producto del sistema
     */
    public function isSystemProduct(): bool
    {
        return $this->value <= 1000;
    }

    /**
     * Verifica si este ProductId representa un producto beta (ID 1-100)
     * (productos en fase de prueba)
     *
     * @return bool True si es un producto beta
     */
    public function isBetaProduct(): bool
    {
        return $this->value >= 1 && $this->value <= 100;
    }

    /**
     * Obtiene el tipo de producto basado en el ID
     *
     * @return string Tipo de producto (beta, basic, premium, vip, promotional, seasonal, limited, system)
     */
    public function getProductType(): string
    {
        if ($this->isBetaProduct()) {
            return 'beta';
        }

        if ($this->isLimitedProduct()) {
            return 'limited';
        }

        if ($this->isSeasonalProduct()) {
            return 'seasonal';
        }

        if ($this->isPromotionalProduct()) {
            return 'promotional';
        }

        if ($this->isVipProduct()) {
            return 'vip';
        }

        if ($this->isPremiumProduct()) {
            return 'premium';
        }

        if ($this->isBasicProduct()) {
            return 'basic';
        }

        if ($this->isSystemProduct()) {
            return 'system';
        }

        return 'custom';
    }

    /**
     * Verifica si este ProductId representa un producto activo
     * (productos disponibles para compra)
     *
     * @return bool True si es un producto activo
     */
    public function isActiveProduct(): bool
    {
        return $this->isBasicProduct() || $this->isPremiumProduct() || $this->isVipProduct();
    }

    /**
     * Verifica si este ProductId representa un producto de pago
     * (excluye productos gratuitos y del sistema)
     *
     * @return bool True si es un producto de pago
     */
    public function isPaidProduct(): bool
    {
        return $this->isActiveProduct() && !$this->isSystemProduct() && !$this->isBetaProduct();
    }

    /**
     * Verifica si este ProductId representa un producto gratuito
     * (productos básicos y del sistema)
     *
     * @return bool True si es un producto gratuito
     */
    public function isFreeProduct(): bool
    {
        return $this->isBasicProduct() || $this->isSystemProduct() || $this->isBetaProduct();
    }

    /**
     * Verifica si este ProductId representa un producto con descuento
     * (productos promocionales y estacionales)
     *
     * @return bool True si tiene descuento
     */
    public function hasDiscount(): bool
    {
        return $this->isPromotionalProduct() || $this->isSeasonalProduct();
    }

    /**
     * Verifica si este ProductId representa un producto limitado en tiempo
     * (productos promocionales, estacionales y limitados)
     *
     * @return bool True si es limitado en tiempo
     */
    public function isTimeLimited(): bool
    {
        return $this->isLimitedProduct() || $this->isPromotionalProduct() || $this->isSeasonalProduct();
    }

    /**
     * Obtiene el nivel del producto (1-5, donde 5 es el más alto)
     *
     * @return int Nivel del producto
     */
    public function getProductLevel(): int
    {
        if ($this->isVipProduct()) {
            return 5;
        }

        if ($this->isPremiumProduct()) {
            return 4;
        }

        if ($this->isLimitedProduct()) {
            return 3;
        }

        if ($this->isBasicProduct()) {
            return 2;
        }

        if ($this->isPromotionalProduct() || $this->isSeasonalProduct()) {
            return 1;
        }

        return 1; // Default level
    }

    /**
     * Verifica si este ProductId tiene características específicas
     *
     * @param string $feature Característica a verificar
     * @return bool True si tiene la característica
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->getProductFeatures();
        return in_array($feature, $features);
    }

    /**
     * Obtiene las características del producto basadas en el tipo
     *
     * @return array Lista de características del producto
     */
    public function getProductFeatures(): array
    {
        $features = [];

        if ($this->isBasicProduct()) {
            $features = ['standard_quality', 'basic_support', 'regular_pricing'];
        } elseif ($this->isPremiumProduct()) {
            $features = ['high_quality', 'priority_support', 'premium_pricing', 'advanced_features'];
        } elseif ($this->isVipProduct()) {
            $features = ['highest_quality', 'dedicated_support', 'vip_pricing', 'exclusive_features', 'vip_benefits'];
        } elseif ($this->isPromotionalProduct()) {
            $features = ['discounted_pricing', 'limited_time', 'special_bonus'];
        } elseif ($this->isSeasonalProduct()) {
            $features = ['seasonal_themed', 'holiday_special', 'limited_availability', 'special_bonus'];
        } elseif ($this->isLimitedProduct()) {
            $features = ['limited_quantity', 'exclusive_access', 'special_pricing'];
        } elseif ($this->isBetaProduct()) {
            $features = ['beta_version', 'testing_purpose', 'limited_availability'];
        }

        return $features;
    }

    /**
     * Convierte el ProductId a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getProductType(),
            'level' => $this->getProductLevel(),
            'is_active' => $this->isActiveProduct(),
            'is_paid' => $this->isPaidProduct(),
            'is_free' => $this->isFreeProduct(),
            'has_discount' => $this->hasDiscount(),
            'is_time_limited' => $this->isTimeLimited(),
            'features' => $this->getProductFeatures(),
        ];
    }

    /**
     * Representación en string del ProductId
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
            'type' => $this->getProductType(),
            'level' => $this->getProductLevel(),
            'is_active' => $this->isActiveProduct(),
            'is_paid' => $this->isPaidProduct(),
            'has_discount' => $this->hasDiscount(),
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
                "ProductId debe ser un número entero positivo, se recibió: {$value}"
            );
        }

        if ($value > PHP_INT_MAX) {
            throw new InvalidArgumentException(
                "ProductId excede el valor máximo permitido: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un ProductId válido
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
     * Crea un ProductId desde un valor mixto (int, string, array)
     *
     * @param mixed $value Valor del identificador
     * @param string $arrayKey Clave para arrays (por defecto 'id')
     * @return self Nueva instancia de ProductId
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
            'ProductId solo puede crearse desde int, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Verifica si este ProductId corresponde a un producto con características específicas
     * (útil para filtros y búsquedas)
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['type'])) {
            if ($this->getProductType() !== $criteria['type']) {
                return false;
            }
        }

        if (isset($criteria['level'])) {
            if ($this->getProductLevel() !== $criteria['level']) {
                return false;
            }
        }

        if (isset($criteria['paid_only']) && $criteria['paid_only']) {
            if (!$this->isPaidProduct()) {
                return false;
            }
        }

        if (isset($criteria['free_only']) && $criteria['free_only']) {
            if (!$this->isFreeProduct()) {
                return false;
            }
        }

        if (isset($criteria['active_only']) && $criteria['active_only']) {
            if (!$this->isActiveProduct()) {
                return false;
            }
        }

        if (isset($criteria['discount_only']) && $criteria['discount_only']) {
            if (!$this->hasDiscount()) {
                return false;
            }
        }

        if (isset($criteria['time_limited_only']) && $criteria['time_limited_only']) {
            if (!$this->isTimeLimited()) {
                return false;
            }
        }

        if (isset($criteria['feature'])) {
            if (!$this->hasFeature($criteria['feature'])) {
                return false;
            }
        }

        if (isset($criteria['min_level'])) {
            if ($this->getProductLevel() < $criteria['min_level']) {
                return false;
            }
        }

        if (isset($criteria['max_level'])) {
            if ($this->getProductLevel() > $criteria['max_level']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del ProductId
     *
     * @return array Estadísticas del producto
     */
    public function getStats(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getProductType(),
            'level' => $this->getProductLevel(),
            'is_active' => $this->isActiveProduct(),
            'is_paid' => $this->isPaidProduct(),
            'is_free' => $this->isFreeProduct(),
            'has_discount' => $this->hasDiscount(),
            'is_time_limited' => $this->isTimeLimited(),
            'features_count' => count($this->getProductFeatures()),
            'features' => $this->getProductFeatures(),
        ];
    }

    /**
     * Genera un hash único para este ProductId (útil para cache keys)
     *
     * @return string Hash único del ProductId
     */
    public function getHash(): string
    {
        return 'product_' . $this->value . '_' . substr(md5((string) $this->value), 0, 8);
    }

    /**
     * Verifica si este ProductId es compatible con el sistema de analytics
     * (productos que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Todos los productos activos pueden generar analytics
        return $this->isActiveProduct();
    }

    /**
     * Verifica si este ProductId es compatible con el sistema de facturación
     * (productos que pueden generar facturas)
     *
     * @return bool True si es compatible con facturación
     */
    public function isBillingCompatible(): bool
    {
        // Solo productos de pago pueden generar facturas
        return $this->isPaidProduct();
    }

    /**
     * Obtiene la prioridad de procesamiento del producto
     * (útil para colas de procesamiento)
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        if ($this->isVipProduct()) {
            return 1; // Alta prioridad para productos VIP
        }

        if ($this->isPremiumProduct()) {
            return 2; // Alta prioridad para productos premium
        }

        if ($this->isLimitedProduct()) {
            return 2; // Alta prioridad para productos limitados
        }

        if ($this->isPromotionalProduct() || $this->isSeasonalProduct()) {
            return 3; // Prioridad media para productos promocionales
        }

        if ($this->isBasicProduct()) {
            return 4; // Prioridad media-baja para productos básicos
        }

        return 5; // Baja prioridad para productos del sistema y beta
    }
}
