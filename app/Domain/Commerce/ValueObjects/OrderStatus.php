<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * OrderStatus Value Object
 * 
 * Representa el estado de una orden en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del estado de orden en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para manejar
 * los diferentes estados que puede tener una orden durante su ciclo de vida
 * en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta estados válidos predefinidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos OrderStatus con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y estados de orden
 * 
 * Estados disponibles:
 * - pending: Orden pendiente de procesamiento
 * - processing: Orden en proceso
 * - completed: Orden completada exitosamente
 * - cancelled: Orden cancelada
 * - failed: Orden fallida
 * - refunded: Orden reembolsada
 * - expired: Orden expirada
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class OrderStatus implements JsonSerializable
{
    /**
     * Estados válidos para una orden
     */
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const FULFILLED = 'fulfilled';
    public const ACTIVE = 'active';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const FAILED = 'failed';
    public const REFUNDED = 'refunded';
    public const EXPIRED = 'expired';
    public const RENEWED = 'renewed';

    /**
     * Lista de todos los estados válidos
     */
    private const VALID_STATUSES = [
        self::PENDING,
        self::PROCESSING,
        self::FULFILLED,
        self::ACTIVE,
        self::COMPLETED,
        self::CANCELLED,
        self::FAILED,
        self::REFUNDED,
        self::EXPIRED,
        self::RENEWED,
    ];

    /**
     * El valor del estado de la orden
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
     * Factory method para crear una nueva instancia de OrderStatus
     *
     * @param string $value Valor del estado
     * @return self Nueva instancia de OrderStatus
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear OrderStatus desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el estado
     * @param string $key Clave del array que contiene el estado
     * @return self Nueva instancia de OrderStatus
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromArray(array $data, string $key = 'status'): self
    {
        if (!isset($data[$key])) {
            throw new InvalidArgumentException(
                "Clave '{$key}' no encontrada en los datos proporcionados"
            );
        }

        return self::fromString($data[$key]);
    }

    /**
     * Factory methods para crear estados específicos
     */
    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function processing(): self
    {
        return new self(self::PROCESSING);
    }

    public static function fulfilled(): self
    {
        return new self(self::FULFILLED);
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function refunded(): self
    {
        return new self(self::REFUNDED);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    public static function renewed(): self
    {
        return new self(self::RENEWED);
    }

    /**
     * Obtiene el valor del estado
     *
     * @return string Valor del estado
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Verifica si este OrderStatus es igual a otro
     *
     * @param self $other Otro OrderStatus para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este OrderStatus es pendiente
     *
     * @return bool True si es pendiente
     */
    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    /**
     * Verifica si este OrderStatus está en proceso
     *
     * @return bool True si está en proceso
     */
    public function isProcessing(): bool
    {
        return $this->value === self::PROCESSING;
    }

    /**
     * Verifica si este OrderStatus está cumplido
     *
     * @return bool True si está cumplido
     */
    public function isFulfilled(): bool
    {
        return $this->value === self::FULFILLED;
    }

    /**
     * Verifica si este OrderStatus está activo (estado específico)
     *
     * @return bool True si está activo
     */
    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    /**
     * Verifica si este OrderStatus está completado
     *
     * @return bool True si está completado
     */
    public function isCompleted(): bool
    {
        return $this->value === self::COMPLETED;
    }

    /**
     * Verifica si este OrderStatus está cancelado
     *
     * @return bool True si está cancelado
     */
    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    /**
     * Verifica si este OrderStatus falló
     *
     * @return bool True si falló
     */
    public function isFailed(): bool
    {
        return $this->value === self::FAILED;
    }

    /**
     * Verifica si este OrderStatus fue reembolsado
     *
     * @return bool True si fue reembolsado
     */
    public function isRefunded(): bool
    {
        return $this->value === self::REFUNDED;
    }

    /**
     * Verifica si este OrderStatus expiró
     *
     * @return bool True si expiró
     */
    public function isExpired(): bool
    {
        return $this->value === self::EXPIRED;
    }

    /**
     * Verifica si este OrderStatus fue renovado
     *
     * @return bool True si fue renovado
     */
    public function isRenewed(): bool
    {
        return $this->value === self::RENEWED;
    }

    /**
     * Verifica si este OrderStatus está en estado activo general
     * (pendiente, en proceso, cumplido o activo)
     *
     * @return bool True si está en estado activo general
     */
    public function isInActiveState(): bool
    {
        return $this->isPending() || $this->isProcessing() || $this->isFulfilled() || $this->value === self::ACTIVE;
    }

    /**
     * Verifica si este OrderStatus está finalizado
     * (completado, cancelado, fallido, reembolsado, expirado o renovado)
     *
     * @return bool True si está finalizado
     */
    public function isFinalized(): bool
    {
        return $this->isCompleted() || $this->isCancelled() || $this->isFailed() || 
               $this->isRefunded() || $this->isExpired() || $this->isRenewed();
    }

    /**
     * Verifica si este OrderStatus es exitoso
     * (completado)
     *
     * @return bool True si es exitoso
     */
    public function isSuccessful(): bool
    {
        return $this->isCompleted();
    }

    /**
     * Verifica si este OrderStatus es fallido
     * (cancelado, fallido, expirado)
     *
     * @return bool True si es fallido
     */
    public function isUnsuccessful(): bool
    {
        return $this->isCancelled() || $this->isFailed() || $this->isExpired();
    }

    /**
     * Verifica si este OrderStatus puede ser modificado
     * (solo pendiente y en proceso pueden ser modificados)
     *
     * @return bool True si puede ser modificado
     */
    public function canBeModified(): bool
    {
        return $this->isActive();
    }

    /**
     * Verifica si este OrderStatus puede ser cancelado
     * (solo pendiente y en proceso pueden ser cancelados)
     *
     * @return bool True si puede ser cancelado
     */
    public function canBeCancelled(): bool
    {
        return $this->isActive();
    }

    /**
     * Verifica si este OrderStatus puede ser reembolsado
     * (solo completado puede ser reembolsado)
     *
     * @return bool True si puede ser reembolsado
     */
    public function canBeRefunded(): bool
    {
        return $this->isCompleted();
    }

    /**
     * Obtiene el nivel de prioridad del estado
     * (1 = alta prioridad, 5 = baja prioridad)
     *
     * @return int Nivel de prioridad
     */
    public function getPriorityLevel(): int
    {
        return match ($this->value) {
            self::PROCESSING => 1, // Alta prioridad para órdenes en proceso
            self::PENDING => 2, // Prioridad alta para órdenes pendientes
            self::FULFILLED => 3, // Prioridad media para órdenes cumplidas
            self::ACTIVE => 3, // Prioridad media para órdenes activas
            self::COMPLETED => 4, // Prioridad media-baja para órdenes completadas
            self::FAILED => 5, // Prioridad baja para órdenes fallidas
            self::CANCELLED, self::REFUNDED, self::EXPIRED, self::RENEWED => 6, // Baja prioridad para estados finales
            default => 3,
        };
    }

    /**
     * Obtiene la categoría del estado
     *
     * @return string Categoría del estado (active, finalized, successful, unsuccessful)
     */
    public function getCategory(): string
    {
        if ($this->isActive()) {
            return 'active';
        }

        if ($this->isSuccessful()) {
            return 'successful';
        }

        if ($this->isUnsuccessful()) {
            return 'unsuccessful';
        }

        return 'finalized';
    }

    /**
     * Obtiene la descripción legible del estado
     *
     * @return string Descripción del estado
     */
    public function getDescription(): string
    {
        return match ($this->value) {
            self::PENDING => 'Orden pendiente de procesamiento',
            self::PROCESSING => 'Orden en proceso',
            self::FULFILLED => 'Orden cumplida',
            self::ACTIVE => 'Orden activa',
            self::COMPLETED => 'Orden completada exitosamente',
            self::CANCELLED => 'Orden cancelada',
            self::FAILED => 'Orden fallida',
            self::REFUNDED => 'Orden reembolsada',
            self::EXPIRED => 'Orden expirada',
            self::RENEWED => 'Orden renovada',
            default => 'Estado desconocido',
        };
    }

    /**
     * Obtiene los estados válidos siguientes desde el estado actual
     *
     * @return array Lista de estados válidos siguientes
     */
    public function getNextValidStatuses(): array
    {
        return match ($this->value) {
            self::PENDING => [self::PROCESSING, self::CANCELLED, self::EXPIRED],
            self::PROCESSING => [self::FULFILLED, self::ACTIVE, self::COMPLETED, self::FAILED, self::CANCELLED],
            self::FULFILLED => [self::COMPLETED, self::REFUNDED],
            self::ACTIVE => [self::CANCELLED, self::EXPIRED],
            self::COMPLETED => [self::REFUNDED],
            self::EXPIRED => [self::RENEWED],
            self::CANCELLED, self::FAILED, self::REFUNDED, self::RENEWED => [], // Estados finales
            default => [],
        };
    }

    /**
     * Verifica si un estado es válido como siguiente estado
     *
     * @param self $nextStatus Estado siguiente a verificar
     * @return bool True si es válido
     */
    public function canTransitionTo(self $nextStatus): bool
    {
        $validNextStatuses = $this->getNextValidStatuses();
        return in_array($nextStatus->toString(), $validNextStatuses);
    }

    /**
     * Convierte el OrderStatus a array para serialización
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
            'is_active' => $this->isActive(),
            'is_finalized' => $this->isFinalized(),
            'is_successful' => $this->isSuccessful(),
            'can_be_modified' => $this->canBeModified(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_be_refunded' => $this->canBeRefunded(),
            'next_valid_statuses' => $this->getNextValidStatuses(),
        ];
    }

    /**
     * Representación en string del OrderStatus
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
            'is_active' => $this->isActive(),
            'is_finalized' => $this->isFinalized(),
        ];
    }

    /**
     * Valida que el valor del estado sea válido
     *
     * @param string $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(string $value): void
    {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                "OrderStatus debe ser uno de los valores válidos: " . implode(', ', self::VALID_STATUSES) . 
                ", se recibió: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un OrderStatus válido
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
     * Crea un OrderStatus desde un valor mixto (string, array)
     *
     * @param mixed $value Valor del estado
     * @param string $arrayKey Clave para arrays (por defecto 'status')
     * @return self Nueva instancia de OrderStatus
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value, string $arrayKey = 'status'): self
    {
        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_array($value)) {
            return self::fromArray($value, $arrayKey);
        }

        throw new InvalidArgumentException(
            'OrderStatus solo puede crearse desde string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Obtiene todos los estados válidos
     *
     * @return array Lista de todos los estados válidos
     */
    public static function getValidStatuses(): array
    {
        return self::VALID_STATUSES;
    }

    /**
     * Genera un hash único para este OrderStatus (útil para cache keys)
     *
     * @return string Hash único del OrderStatus
     */
    public function getHash(): string
    {
        return 'order_status_' . $this->value . '_' . substr(md5($this->value), 0, 8);
    }

    /**
     * Verifica si este OrderStatus es compatible con el sistema de analytics
     * (estados que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Todos los estados pueden generar analytics
        return true;
    }

    /**
     * Verifica si este OrderStatus es compatible con el sistema de facturación
     * (estados que pueden generar facturas)
     *
     * @return bool True si es compatible con facturación
     */
    public function isBillingCompatible(): bool
    {
        // Solo estados completados pueden generar facturas
        return $this->isCompleted();
    }
}
