<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * OrderId Value Object
 * 
 * Representa el identificador único de una orden en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del identificador de orden en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para identificar
 * órdenes de compra, suscripciones, paquetes de monedas y otros elementos comerciales
 * en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores enteros positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos OrderId con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y órdenes
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class OrderId implements JsonSerializable
{
    /**
     * El valor del identificador de orden
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
     * Factory method para crear una nueva instancia de OrderId
     *
     * @param int $value Valor del identificador
     * @return self Nueva instancia de OrderId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromInt(int $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear OrderId desde string
     *
     * @param string $value Valor del identificador como string
     * @return self Nueva instancia de OrderId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "OrderId debe ser un número entero válido, se recibió: {$value}"
            );
        }

        $intValue = (int) $value;
        return self::fromInt($intValue);
    }

    /**
     * Factory method para crear OrderId desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el ID
     * @param string $key Clave del array que contiene el ID
     * @return self Nueva instancia de OrderId
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
     * Verifica si este OrderId es igual a otro
     *
     * @param self $other Otro OrderId para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este OrderId es mayor que otro
     *
     * @param self $other Otro OrderId para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este OrderId es menor que otro
     *
     * @param self $other Otro OrderId para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Verifica si este OrderId es mayor o igual que otro
     *
     * @param self $other Otro OrderId para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Verifica si este OrderId es menor o igual que otro
     *
     * @param self $other Otro OrderId para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * Verifica si este OrderId está en un rango específico
     *
     * @param self $min OrderId mínimo (inclusivo)
     * @param self $max OrderId máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Crea un nuevo OrderId incrementado en 1
     *
     * @return self Nuevo OrderId incrementado
     */
    public function increment(): self
    {
        return new self($this->value + 1);
    }

    /**
     * Crea un nuevo OrderId decrementado en 1
     *
     * @return self Nuevo OrderId decrementado
     */
    public function decrement(): self
    {
        return new self($this->value - 1);
    }

    /**
     * Verifica si este OrderId representa una orden básica (ID 1-10000)
     * (órdenes estándar del sistema)
     *
     * @return bool True si es una orden básica
     */
    public function isBasicOrder(): bool
    {
        return $this->value >= 1 && $this->value <= 10000;
    }

    /**
     * Verifica si este OrderId representa una orden premium (ID 10001-50000)
     * (órdenes con características avanzadas)
     *
     * @return bool True si es una orden premium
     */
    public function isPremiumOrder(): bool
    {
        return $this->value >= 10001 && $this->value <= 50000;
    }

    /**
     * Verifica si este OrderId representa una orden VIP (ID 50001-100000)
     * (órdenes exclusivas para usuarios VIP)
     *
     * @return bool True si es una orden VIP
     */
    public function isVipOrder(): bool
    {
        return $this->value >= 50001 && $this->value <= 100000;
    }

    /**
     * Verifica si este OrderId representa una orden promocional (ID 100000-199999)
     * (órdenes con ofertas y descuentos especiales)
     *
     * @return bool True si es una orden promocional
     */
    public function isPromotionalOrder(): bool
    {
        return $this->value >= 100000 && $this->value <= 199999;
    }

    /**
     * Verifica si este OrderId representa una orden estacional (ID 200000-299999)
     * (órdenes disponibles solo en temporadas específicas)
     *
     * @return bool True si es una orden estacional
     */
    public function isSeasonalOrder(): bool
    {
        return $this->value >= 200000 && $this->value <= 299999;
    }

    /**
     * Verifica si este OrderId representa una orden del sistema (ID <= 1000)
     *
     * @return bool True si es una orden del sistema
     */
    public function isSystemOrder(): bool
    {
        return $this->value <= 1000;
    }

    /**
     * Verifica si este OrderId representa una orden beta (ID 1-100)
     * (órdenes en fase de prueba)
     *
     * @return bool True si es una orden beta
     */
    public function isBetaOrder(): bool
    {
        return $this->value >= 1 && $this->value <= 100;
    }

    /**
     * Obtiene el tipo de orden basado en el ID
     *
     * @return string Tipo de orden (beta, basic, premium, vip, promotional, seasonal, system)
     */
    public function getOrderType(): string
    {
        if ($this->isBetaOrder()) {
            return 'beta';
        }

        if ($this->isSeasonalOrder()) {
            return 'seasonal';
        }

        if ($this->isPromotionalOrder()) {
            return 'promotional';
        }

        if ($this->isVipOrder()) {
            return 'vip';
        }

        if ($this->isPremiumOrder()) {
            return 'premium';
        }

        if ($this->isBasicOrder()) {
            return 'basic';
        }

        if ($this->isSystemOrder()) {
            return 'system';
        }

        return 'custom';
    }

    /**
     * Verifica si este OrderId representa una orden activa
     * (órdenes disponibles para procesamiento)
     *
     * @return bool True si es una orden activa
     */
    public function isActiveOrder(): bool
    {
        return $this->isBasicOrder() || $this->isPremiumOrder() || $this->isVipOrder();
    }

    /**
     * Verifica si este OrderId representa una orden de pago
     * (excluye órdenes gratuitas y del sistema)
     *
     * @return bool True si es una orden de pago
     */
    public function isPaidOrder(): bool
    {
        return $this->isActiveOrder() && !$this->isSystemOrder() && !$this->isBetaOrder();
    }

    /**
     * Verifica si este OrderId representa una orden gratuita
     * (órdenes básicas y del sistema)
     *
     * @return bool True si es una orden gratuita
     */
    public function isFreeOrder(): bool
    {
        return $this->isBasicOrder() || $this->isSystemOrder() || $this->isBetaOrder();
    }

    /**
     * Verifica si este OrderId representa una orden con descuento
     * (órdenes promocionales y estacionales)
     *
     * @return bool True si tiene descuento
     */
    public function hasDiscount(): bool
    {
        return $this->isPromotionalOrder() || $this->isSeasonalOrder();
    }

    /**
     * Verifica si este OrderId representa una orden limitada en tiempo
     * (órdenes promocionales y estacionales)
     *
     * @return bool True si es limitada en tiempo
     */
    public function isTimeLimited(): bool
    {
        return $this->isPromotionalOrder() || $this->isSeasonalOrder();
    }

    /**
     * Obtiene el nivel de la orden (1-5, donde 5 es el más alto)
     *
     * @return int Nivel de la orden
     */
    public function getOrderLevel(): int
    {
        if ($this->isVipOrder()) {
            return 5;
        }

        if ($this->isPremiumOrder()) {
            return 4;
        }

        if ($this->isBasicOrder()) {
            return 3;
        }

        if ($this->isPromotionalOrder() || $this->isSeasonalOrder()) {
            return 2;
        }

        return 1; // Default level
    }

    /**
     * Convierte el OrderId a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getOrderType(),
            'level' => $this->getOrderLevel(),
            'is_active' => $this->isActiveOrder(),
            'is_paid' => $this->isPaidOrder(),
            'is_free' => $this->isFreeOrder(),
            'has_discount' => $this->hasDiscount(),
            'is_time_limited' => $this->isTimeLimited(),
        ];
    }

    /**
     * Representación en string del OrderId
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
            'type' => $this->getOrderType(),
            'level' => $this->getOrderLevel(),
            'is_active' => $this->isActiveOrder(),
            'is_paid' => $this->isPaidOrder(),
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
                "OrderId debe ser un número entero positivo, se recibió: {$value}"
            );
        }

        if ($value > PHP_INT_MAX) {
            throw new InvalidArgumentException(
                "OrderId excede el valor máximo permitido: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un OrderId válido
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
     * Crea un OrderId desde un valor mixto (int, string, array)
     *
     * @param mixed $value Valor del identificador
     * @param string $arrayKey Clave para arrays (por defecto 'id')
     * @return self Nueva instancia de OrderId
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
            'OrderId solo puede crearse desde int, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para este OrderId (útil para cache keys)
     *
     * @return string Hash único del OrderId
     */
    public function getHash(): string
    {
        return 'order_' . $this->value . '_' . substr(md5((string) $this->value), 0, 8);
    }

    /**
     * Verifica si este OrderId es compatible con el sistema de analytics
     * (órdenes que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Todas las órdenes activas pueden generar analytics
        return $this->isActiveOrder();
    }

    /**
     * Verifica si este OrderId es compatible con el sistema de facturación
     * (órdenes que pueden generar facturas)
     *
     * @return bool True si es compatible con facturación
     */
    public function isBillingCompatible(): bool
    {
        // Solo órdenes de pago pueden generar facturas
        return $this->isPaidOrder();
    }

    /**
     * Obtiene la prioridad de procesamiento de la orden
     * (útil para colas de procesamiento)
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        if ($this->isVipOrder()) {
            return 1; // Alta prioridad para órdenes VIP
        }

        if ($this->isPremiumOrder()) {
            return 2; // Alta prioridad para órdenes premium
        }

        if ($this->isPromotionalOrder() || $this->isSeasonalOrder()) {
            return 3; // Prioridad media para órdenes promocionales
        }

        if ($this->isBasicOrder()) {
            return 4; // Prioridad media-baja para órdenes básicas
        }

        return 5; // Baja prioridad para órdenes del sistema y beta
    }
}
