<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * PaymentId Value Object
 * 
 * Representa el identificador único de un pago en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del identificador de pago en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para identificar
 * pagos, transacciones financieras y operaciones de cobro en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores enteros positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos PaymentId con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y pagos
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class PaymentId implements JsonSerializable
{
    /**
     * El valor del identificador de pago
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
     * Factory method para crear una nueva instancia de PaymentId
     *
     * @param int $value Valor del identificador
     * @return self Nueva instancia de PaymentId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromInt(int $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear PaymentId desde string
     *
     * @param string $value Valor del identificador como string
     * @return self Nueva instancia de PaymentId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "PaymentId debe ser un número entero válido, se recibió: {$value}"
            );
        }

        $intValue = (int) $value;
        return self::fromInt($intValue);
    }

    /**
     * Factory method para crear PaymentId desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el ID
     * @param string $key Clave del array que contiene el ID
     * @return self Nueva instancia de PaymentId
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
     * Verifica si este PaymentId es igual a otro
     *
     * @param self $other Otro PaymentId para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este PaymentId es mayor que otro
     *
     * @param self $other Otro PaymentId para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este PaymentId es menor que otro
     *
     * @param self $other Otro PaymentId para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Verifica si este PaymentId es mayor o igual que otro
     *
     * @param self $other Otro PaymentId para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Verifica si este PaymentId es menor o igual que otro
     *
     * @param self $other Otro PaymentId para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * Verifica si este PaymentId está en un rango específico
     *
     * @param self $min PaymentId mínimo (inclusivo)
     * @param self $max PaymentId máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Crea un nuevo PaymentId incrementado en 1
     *
     * @return self Nuevo PaymentId incrementado
     */
    public function increment(): self
    {
        return new self($this->value + 1);
    }

    /**
     * Crea un nuevo PaymentId decrementado en 1
     *
     * @return self Nuevo PaymentId decrementado
     */
    public function decrement(): self
    {
        return new self($this->value - 1);
    }

    /**
     * Verifica si este PaymentId representa un pago básico (ID 1-50000)
     * (pagos estándar del sistema)
     *
     * @return bool True si es un pago básico
     */
    public function isBasicPayment(): bool
    {
        return $this->value >= 1 && $this->value <= 50000;
    }

    /**
     * Verifica si este PaymentId representa un pago premium (ID 50001-200000)
     * (pagos con características avanzadas)
     *
     * @return bool True si es un pago premium
     */
    public function isPremiumPayment(): bool
    {
        return $this->value >= 50001 && $this->value <= 200000;
    }

    /**
     * Verifica si este PaymentId representa un pago VIP (ID 200001-500000)
     * (pagos exclusivos para usuarios VIP)
     *
     * @return bool True si es un pago VIP
     */
    public function isVipPayment(): bool
    {
        return $this->value >= 200001 && $this->value <= 500000;
    }

    /**
     * Verifica si este PaymentId representa un pago promocional (ID 500000-999999)
     * (pagos con ofertas y descuentos especiales)
     *
     * @return bool True si es un pago promocional
     */
    public function isPromotionalPayment(): bool
    {
        return $this->value >= 500000 && $this->value <= 999999;
    }

    /**
     * Verifica si este PaymentId representa un pago del sistema (ID <= 1000)
     *
     * @return bool True si es un pago del sistema
     */
    public function isSystemPayment(): bool
    {
        return $this->value <= 1000;
    }

    /**
     * Verifica si este PaymentId representa un pago beta (ID 1-100)
     * (pagos en fase de prueba)
     *
     * @return bool True si es un pago beta
     */
    public function isBetaPayment(): bool
    {
        return $this->value >= 1 && $this->value <= 100;
    }

    /**
     * Obtiene el tipo de pago basado en el ID
     *
     * @return string Tipo de pago (beta, basic, premium, vip, promotional, system)
     */
    public function getPaymentType(): string
    {
        if ($this->isBetaPayment()) {
            return 'beta';
        }

        if ($this->isPromotionalPayment()) {
            return 'promotional';
        }

        if ($this->isVipPayment()) {
            return 'vip';
        }

        if ($this->isPremiumPayment()) {
            return 'premium';
        }

        if ($this->isBasicPayment()) {
            return 'basic';
        }

        if ($this->isSystemPayment()) {
            return 'system';
        }

        return 'custom';
    }

    /**
     * Verifica si este PaymentId representa un pago activo
     * (pagos disponibles para procesamiento)
     *
     * @return bool True si es un pago activo
     */
    public function isActivePayment(): bool
    {
        return $this->isBasicPayment() || $this->isPremiumPayment() || $this->isVipPayment();
    }

    /**
     * Verifica si este PaymentId representa un pago de alto valor
     * (pagos VIP y premium)
     *
     * @return bool True si es un pago de alto valor
     */
    public function isHighValuePayment(): bool
    {
        return $this->isVipPayment() || $this->isPremiumPayment();
    }

    /**
     * Verifica si este PaymentId representa un pago con descuento
     * (pagos promocionales)
     *
     * @return bool True si tiene descuento
     */
    public function hasDiscount(): bool
    {
        return $this->isPromotionalPayment();
    }

    /**
     * Obtiene el nivel del pago (1-5, donde 5 es el más alto)
     *
     * @return int Nivel del pago
     */
    public function getPaymentLevel(): int
    {
        if ($this->isVipPayment()) {
            return 5;
        }

        if ($this->isPremiumPayment()) {
            return 4;
        }

        if ($this->isBasicPayment()) {
            return 3;
        }

        if ($this->isPromotionalPayment()) {
            return 2;
        }

        return 1; // Default level
    }

    /**
     * Convierte el PaymentId a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getPaymentType(),
            'level' => $this->getPaymentLevel(),
            'is_active' => $this->isActivePayment(),
            'is_high_value' => $this->isHighValuePayment(),
            'has_discount' => $this->hasDiscount(),
        ];
    }

    /**
     * Representación en string del PaymentId
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
            'type' => $this->getPaymentType(),
            'level' => $this->getPaymentLevel(),
            'is_active' => $this->isActivePayment(),
            'is_high_value' => $this->isHighValuePayment(),
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
                "PaymentId debe ser un número entero positivo, se recibió: {$value}"
            );
        }

        if ($value > PHP_INT_MAX) {
            throw new InvalidArgumentException(
                "PaymentId excede el valor máximo permitido: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un PaymentId válido
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
     * Crea un PaymentId desde un valor mixto (int, string, array)
     *
     * @param mixed $value Valor del identificador
     * @param string $arrayKey Clave para arrays (por defecto 'id')
     * @return self Nueva instancia de PaymentId
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
            'PaymentId solo puede crearse desde int, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para este PaymentId (útil para cache keys)
     *
     * @return string Hash único del PaymentId
     */
    public function getHash(): string
    {
        return 'payment_' . $this->value . '_' . substr(md5((string) $this->value), 0, 8);
    }

    /**
     * Verifica si este PaymentId es compatible con el sistema de analytics
     * (pagos que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Todos los pagos activos pueden generar analytics
        return $this->isActivePayment();
    }

    /**
     * Verifica si este PaymentId es compatible con el sistema de facturación
     * (pagos que pueden generar facturas)
     *
     * @return bool True si es compatible con facturación
     */
    public function isBillingCompatible(): bool
    {
        // Todos los pagos pueden generar facturas
        return true;
    }

    /**
     * Obtiene la prioridad de procesamiento del pago
     * (útil para colas de procesamiento)
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        if ($this->isVipPayment()) {
            return 1; // Alta prioridad para pagos VIP
        }

        if ($this->isPremiumPayment()) {
            return 2; // Alta prioridad para pagos premium
        }

        if ($this->isPromotionalPayment()) {
            return 3; // Prioridad media para pagos promocionales
        }

        if ($this->isBasicPayment()) {
            return 4; // Prioridad media-baja para pagos básicos
        }

        return 5; // Baja prioridad para pagos del sistema y beta
    }
}
