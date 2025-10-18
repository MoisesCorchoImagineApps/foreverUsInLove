<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * FulfillmentStatus Value Object
 * 
 * Representa el estado de cumplimiento de una orden en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del estado de cumplimiento en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para manejar
 * los diferentes estados que puede tener el cumplimiento de una orden durante su
 * procesamiento en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta estados válidos predefinidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos FulfillmentStatus con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y estados de cumplimiento
 * 
 * Estados disponibles:
 * - pending: Cumplimiento pendiente
 * - in_progress: Cumplimiento en progreso
 * - fulfilled: Cumplimiento completado
 * - delivered: Entregado al usuario
 * - completed: Cumplimiento finalizado exitosamente
 * - failed: Cumplimiento fallido
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class FulfillmentStatus implements JsonSerializable
{
    /**
     * Estados válidos para el cumplimiento
     */
    public const PENDING = 'pending';
    public const IN_PROGRESS = 'in_progress';
    public const FULFILLED = 'fulfilled';
    public const DELIVERED = 'delivered';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    /**
     * Lista de todos los estados válidos
     */
    private const VALID_STATUSES = [
        self::PENDING,
        self::IN_PROGRESS,
        self::FULFILLED,
        self::DELIVERED,
        self::COMPLETED,
        self::FAILED,
    ];

    /**
     * El valor del estado de cumplimiento
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
     * Factory method para crear una nueva instancia de FulfillmentStatus
     *
     * @param string $value Valor del estado
     * @return self Nueva instancia de FulfillmentStatus
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear FulfillmentStatus desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el estado
     * @param string $key Clave del array que contiene el estado
     * @return self Nueva instancia de FulfillmentStatus
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

    public static function inProgress(): self
    {
        return new self(self::IN_PROGRESS);
    }

    public static function fulfilled(): self
    {
        return new self(self::FULFILLED);
    }

    public static function delivered(): self
    {
        return new self(self::DELIVERED);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
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
     * Verifica si este FulfillmentStatus es igual a otro
     *
     * @param self $other Otro FulfillmentStatus para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este FulfillmentStatus está pendiente
     *
     * @return bool True si está pendiente
     */
    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    /**
     * Verifica si este FulfillmentStatus está en progreso
     *
     * @return bool True si está en progreso
     */
    public function isInProgress(): bool
    {
        return $this->value === self::IN_PROGRESS;
    }

    /**
     * Verifica si este FulfillmentStatus está cumplido
     *
     * @return bool True si está cumplido
     */
    public function isFulfilled(): bool
    {
        return $this->value === self::FULFILLED;
    }

    /**
     * Verifica si este FulfillmentStatus está entregado
     *
     * @return bool True si está entregado
     */
    public function isDelivered(): bool
    {
        return $this->value === self::DELIVERED;
    }

    /**
     * Verifica si este FulfillmentStatus está completado
     *
     * @return bool True si está completado
     */
    public function isCompleted(): bool
    {
        return $this->value === self::COMPLETED;
    }

    /**
     * Verifica si este FulfillmentStatus falló
     *
     * @return bool True si falló
     */
    public function isFailed(): bool
    {
        return $this->value === self::FAILED;
    }

    /**
     * Verifica si este FulfillmentStatus está activo
     * (pendiente o en progreso)
     *
     * @return bool True si está activo
     */
    public function isActive(): bool
    {
        return $this->isPending() || $this->isInProgress();
    }

    /**
     * Verifica si este FulfillmentStatus está finalizado
     * (completado, entregado o fallido)
     *
     * @return bool True si está finalizado
     */
    public function isFinalized(): bool
    {
        return $this->isCompleted() || $this->isDelivered() || $this->isFailed();
    }

    /**
     * Verifica si este FulfillmentStatus es exitoso
     * (cumplido, entregado o completado)
     *
     * @return bool True si es exitoso
     */
    public function isSuccessful(): bool
    {
        return $this->isFulfilled() || $this->isDelivered() || $this->isCompleted();
    }

    /**
     * Verifica si este FulfillmentStatus es fallido
     * (fallido)
     *
     * @return bool True si es fallido
     */
    public function isUnsuccessful(): bool
    {
        return $this->isFailed();
    }

    /**
     * Verifica si este FulfillmentStatus puede ser modificado
     * (solo pendiente y en progreso pueden ser modificados)
     *
     * @return bool True si puede ser modificado
     */
    public function canBeModified(): bool
    {
        return $this->isActive();
    }

    /**
     * Verifica si este FulfillmentStatus puede ser cancelado
     * (solo pendiente y en progreso pueden ser cancelados)
     *
     * @return bool True si puede ser cancelado
     */
    public function canBeCancelled(): bool
    {
        return $this->isActive();
    }

    /**
     * Verifica si este FulfillmentStatus puede ser reintentado
     * (solo fallido puede ser reintentado)
     *
     * @return bool True si puede ser reintentado
     */
    public function canBeRetried(): bool
    {
        return $this->isFailed();
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
            self::IN_PROGRESS => 1, // Alta prioridad para cumplimientos en progreso
            self::PENDING => 2, // Prioridad alta para cumplimientos pendientes
            self::FULFILLED => 3, // Prioridad media para cumplimientos cumplidos
            self::DELIVERED => 4, // Prioridad media-baja para entregados
            self::COMPLETED => 5, // Baja prioridad para completados
            self::FAILED => 6, // Baja prioridad para fallidos
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
            self::PENDING => 'Cumplimiento pendiente',
            self::IN_PROGRESS => 'Cumplimiento en progreso',
            self::FULFILLED => 'Cumplimiento completado',
            self::DELIVERED => 'Entregado al usuario',
            self::COMPLETED => 'Cumplimiento finalizado exitosamente',
            self::FAILED => 'Cumplimiento fallido',
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
            self::PENDING => [self::IN_PROGRESS, self::FAILED],
            self::IN_PROGRESS => [self::FULFILLED, self::DELIVERED, self::FAILED],
            self::FULFILLED => [self::DELIVERED, self::COMPLETED],
            self::DELIVERED => [self::COMPLETED],
            self::COMPLETED, self::FAILED => [], // Estados finales
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
     * Convierte el FulfillmentStatus a array para serialización
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
            'can_be_retried' => $this->canBeRetried(),
            'next_valid_statuses' => $this->getNextValidStatuses(),
        ];
    }

    /**
     * Representación en string del FulfillmentStatus
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
                "FulfillmentStatus debe ser uno de los valores válidos: " . implode(', ', self::VALID_STATUSES) . 
                ", se recibió: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un FulfillmentStatus válido
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
     * Crea un FulfillmentStatus desde un valor mixto (string, array)
     *
     * @param mixed $value Valor del estado
     * @param string $arrayKey Clave para arrays (por defecto 'status')
     * @return self Nueva instancia de FulfillmentStatus
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
            'FulfillmentStatus solo puede crearse desde string o array, se recibió: ' . gettype($value)
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
     * Genera un hash único para este FulfillmentStatus (útil para cache keys)
     *
     * @return string Hash único del FulfillmentStatus
     */
    public function getHash(): string
    {
        return 'fulfillment_status_' . $this->value . '_' . substr(md5($this->value), 0, 8);
    }

    /**
     * Verifica si este FulfillmentStatus es compatible con el sistema de analytics
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
     * Verifica si este FulfillmentStatus es compatible con el sistema de notificaciones
     * (estados que pueden generar notificaciones)
     *
     * @return bool True si es compatible con notificaciones
     */
    public function isNotificationCompatible(): bool
    {
        // Estados que requieren notificación al usuario
        return in_array($this->value, [
            self::FULFILLED,
            self::DELIVERED,
            self::COMPLETED,
            self::FAILED
        ]);
    }
}
