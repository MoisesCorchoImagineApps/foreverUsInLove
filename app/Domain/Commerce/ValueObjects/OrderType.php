<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * OrderType Value Object
 * 
 * Representa el tipo de orden en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del tipo de orden en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para manejar
 * los diferentes tipos de órdenes que pueden ser procesadas en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta tipos válidos predefinidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos OrderType con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y tipos de orden
 * 
 * Tipos disponibles:
 * - gift: Orden de regalo virtual
 * - subscription: Orden de suscripción premium
 * - coins: Orden de compra de monedas virtuales
 * - feature: Orden de compra de características premium
 * - boost: Orden de impulso de perfil
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class OrderType implements JsonSerializable
{
    /**
     * Tipos válidos para una orden
     */
    public const GIFT = 'gift';
    public const SUBSCRIPTION = 'subscription';
    public const COINS = 'coins';
    public const FEATURE = 'feature';
    public const BOOST = 'boost';

    /**
     * Lista de todos los tipos válidos
     */
    private const VALID_TYPES = [
        self::GIFT,
        self::SUBSCRIPTION,
        self::COINS,
        self::FEATURE,
        self::BOOST,
    ];

    /**
     * El valor del tipo de orden
     */
    private readonly string $value;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Factory method para crear una nueva instancia de OrderType
     *
     * @param string $value Valor del tipo
     * @return self Nueva instancia de OrderType
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear OrderType desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el tipo
     * @param string $key Clave del array que contiene el tipo
     * @return self Nueva instancia de OrderType
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromArray(array $data, string $key = 'type'): self
    {
        if (!isset($data[$key])) {
            throw new InvalidArgumentException(
                "Clave '{$key}' no encontrada en los datos proporcionados"
            );
        }

        return self::fromString($data[$key]);
    }

    /**
     * Factory methods para crear tipos específicos
     */
    public static function gift(): self
    {
        return new self(self::GIFT);
    }

    public static function subscription(): self
    {
        return new self(self::SUBSCRIPTION);
    }

    public static function coins(): self
    {
        return new self(self::COINS);
    }

    public static function feature(): self
    {
        return new self(self::FEATURE);
    }

    public static function boost(): self
    {
        return new self(self::BOOST);
    }

    /**
     * Obtiene el valor del tipo
     *
     * @return string Valor del tipo
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Verifica si este OrderType es igual a otro
     *
     * @param self $other Otro OrderType para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este OrderType es de regalo
     *
     * @return bool True si es de regalo
     */
    public function isGift(): bool
    {
        return $this->value === self::GIFT;
    }

    /**
     * Verifica si este OrderType es de suscripción
     *
     * @return bool True si es de suscripción
     */
    public function isSubscription(): bool
    {
        return $this->value === self::SUBSCRIPTION;
    }

    /**
     * Verifica si este OrderType es de monedas
     *
     * @return bool True si es de monedas
     */
    public function isCoins(): bool
    {
        return $this->value === self::COINS;
    }

    /**
     * Verifica si este OrderType es de característica
     *
     * @return bool True si es de característica
     */
    public function isFeature(): bool
    {
        return $this->value === self::FEATURE;
    }

    /**
     * Verifica si este OrderType es de impulso
     *
     * @return bool True si es de impulso
     */
    public function isBoost(): bool
    {
        return $this->value === self::BOOST;
    }

    /**
     * Verifica si este OrderType requiere procesamiento instantáneo
     *
     * @return bool True si requiere procesamiento instantáneo
     */
    public function requiresInstantProcessing(): bool
    {
        return in_array($this->value, [
            self::GIFT,
            self::COINS,
            self::FEATURE,
            self::BOOST
        ]);
    }

    /**
     * Verifica si este OrderType requiere procesamiento asíncrono
     *
     * @return bool True si requiere procesamiento asíncrono
     */
    public function requiresAsyncProcessing(): bool
    {
        return $this->value === self::SUBSCRIPTION;
    }

    /**
     * Verifica si este OrderType requiere validación de inventario
     *
     * @return bool True si requiere validación de inventario
     */
    public function requiresInventoryValidation(): bool
    {
        return in_array($this->value, [
            self::GIFT,
            self::COINS,
            self::FEATURE
        ]);
    }

    /**
     * Verifica si este OrderType requiere procesamiento de pago
     *
     * @return bool True si requiere procesamiento de pago
     */
    public function requiresPaymentProcessing(): bool
    {
        // Todos los tipos requieren procesamiento de pago
        return true;
    }

    /**
     * Obtiene el tiempo estimado de procesamiento
     *
     * @return string Tiempo estimado de procesamiento
     */
    public function getEstimatedProcessingTime(): string
    {
        return match ($this->value) {
            self::GIFT, self::COINS, self::FEATURE, self::BOOST => 'instant',
            self::SUBSCRIPTION => '1-2 minutes',
            default => 'unknown',
        };
    }

    /**
     * Obtiene la descripción legible del tipo
     *
     * @return string Descripción del tipo
     */
    public function getDescription(): string
    {
        return match ($this->value) {
            self::GIFT => 'Regalo virtual',
            self::SUBSCRIPTION => 'Suscripción premium',
            self::COINS => 'Compra de monedas virtuales',
            self::FEATURE => 'Característica premium',
            self::BOOST => 'Impulso de perfil',
            default => 'Tipo desconocido',
        };
    }

    /**
     * Obtiene la categoría del tipo
     *
     * @return string Categoría del tipo (virtual, subscription, premium)
     */
    public function getCategory(): string
    {
        return match ($this->value) {
            self::GIFT => 'virtual',
            self::COINS => 'virtual',
            self::SUBSCRIPTION => 'subscription',
            self::FEATURE, self::BOOST => 'premium',
            default => 'unknown',
        };
    }

    /**
     * Obtiene el nivel de prioridad del tipo
     * (1 = alta prioridad, 5 = baja prioridad)
     *
     * @return int Nivel de prioridad
     */
    public function getPriorityLevel(): int
    {
        return match ($this->value) {
            self::COINS => 1, // Alta prioridad para compras de monedas
            self::GIFT => 2, // Prioridad alta para regalos
            self::BOOST => 3, // Prioridad media para impulsos
            self::FEATURE => 4, // Prioridad media-baja para características
            self::SUBSCRIPTION => 5, // Baja prioridad para suscripciones
            default => 3,
        };
    }

    /**
     * Convierte el OrderType a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'description' => $this->getDescription(),
            'category' => $this->getCategory(),
            'priority_level' => $this->getPriorityLevel(),
            'estimated_processing_time' => $this->getEstimatedProcessingTime(),
            'requires_instant_processing' => $this->requiresInstantProcessing(),
            'requires_async_processing' => $this->requiresAsyncProcessing(),
            'requires_inventory_validation' => $this->requiresInventoryValidation(),
            'requires_payment_processing' => $this->requiresPaymentProcessing(),
        ];
    }

    /**
     * Representación en string del OrderType
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
     * @return string Valor para JSON
     */
    public function jsonSerialize(): string
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
            'description' => $this->getDescription(),
            'category' => $this->getCategory(),
            'priority_level' => $this->getPriorityLevel(),
            'estimated_processing_time' => $this->getEstimatedProcessingTime(),
        ];
    }

    /**
     * Valida que el valor del tipo sea válido
     *
     * @param string $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(string $value): void
    {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(
                "OrderType debe ser uno de los valores válidos: " . implode(', ', self::VALID_TYPES) . 
                ", se recibió: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un OrderType válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if (is_string($value)) {
                self::validate($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea un OrderType desde un valor mixto (string, array)
     *
     * @param mixed $value Valor del tipo
     * @param string $arrayKey Clave para arrays (por defecto 'type')
     * @return self Nueva instancia de OrderType
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value, string $arrayKey = 'type'): self
    {
        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_array($value)) {
            return self::fromArray($value, $arrayKey);
        }

        throw new InvalidArgumentException(
            'OrderType solo puede crearse desde string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Obtiene todos los tipos válidos
     *
     * @return array Lista de todos los tipos válidos
     */
    public static function getValidTypes(): array
    {
        return self::VALID_TYPES;
    }

    /**
     * Genera un hash único para este OrderType (útil para cache keys)
     *
     * @return string Hash único del OrderType
     */
    public function getHash(): string
    {
        return 'order_type_' . $this->value . '_' . substr(md5($this->value), 0, 8);
    }

    /**
     * Verifica si este OrderType es compatible con el sistema de analytics
     * (tipos que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Todos los tipos pueden generar analytics
        return true;
    }

    /**
     * Verifica si este OrderType es compatible con el sistema de facturación
     * (tipos que pueden generar facturas)
     *
     * @return bool True si es compatible con facturación
     */
    public function isBillingCompatible(): bool
    {
        // Todos los tipos pueden generar facturas
        return true;
    }
}
