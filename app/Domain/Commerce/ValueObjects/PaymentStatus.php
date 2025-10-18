<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * PaymentStatus Value Object
 * 
 * Representa el estado de un pago en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del estado de pago en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para manejar
 * los diferentes estados que puede tener un pago durante su ciclo de vida
 * en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta estados válidos predefinidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos PaymentStatus con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y estados de pago
 * 
 * Estados disponibles:
 * - pending: Pago pendiente de procesamiento
 * - processing: Pago en proceso
 * - completed: Pago completado exitosamente
 * - failed: Pago fallido
 * - cancelled: Pago cancelado
 * - refunded: Pago reembolsado
 * - expired: Pago expirado
 * - disputed: Pago en disputa
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class PaymentStatus implements JsonSerializable
{
    /**
     * Estados válidos para un pago
     */
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const REFUNDED = 'refunded';
    public const EXPIRED = 'expired';
    public const DISPUTED = 'disputed';

    /**
     * Lista de todos los estados válidos
     */
    private const VALID_STATUSES = [
        self::PENDING,
        self::PROCESSING,
        self::COMPLETED,
        self::FAILED,
        self::CANCELLED,
        self::REFUNDED,
        self::EXPIRED,
        self::DISPUTED,
    ];

    /**
     * El valor del estado del pago
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
     * Factory method para crear una nueva instancia de PaymentStatus
     *
     * @param string $value Valor del estado
     * @return self Nueva instancia de PaymentStatus
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear PaymentStatus desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el estado
     * @param string $key Clave del array que contiene el estado
     * @return self Nueva instancia de PaymentStatus
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

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function refunded(): self
    {
        return new self(self::REFUNDED);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    public static function disputed(): self
    {
        return new self(self::DISPUTED);
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
     * Verifica si este PaymentStatus es igual a otro
     *
     * @param self $other Otro PaymentStatus para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este PaymentStatus es pendiente
     *
     * @return bool True si es pendiente
     */
    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    /**
     * Verifica si este PaymentStatus está en proceso
     *
     * @return bool True si está en proceso
     */
    public function isProcessing(): bool
    {
        return $this->value === self::PROCESSING;
    }

    /**
     * Verifica si este PaymentStatus está completado
     *
     * @return bool True si está completado
     */
    public function isCompleted(): bool
    {
        return $this->value === self::COMPLETED;
    }

    /**
     * Verifica si este PaymentStatus falló
     *
     * @return bool True si falló
     */
    public function isFailed(): bool
    {
        return $this->value === self::FAILED;
    }

    /**
     * Verifica si este PaymentStatus está cancelado
     *
     * @return bool True si está cancelado
     */
    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    /**
     * Verifica si este PaymentStatus fue reembolsado
     *
     * @return bool True si fue reembolsado
     */
    public function isRefunded(): bool
    {
        return $this->value === self::REFUNDED;
    }

    /**
     * Verifica si este PaymentStatus expiró
     *
     * @return bool True si expiró
     */
    public function isExpired(): bool
    {
        return $this->value === self::EXPIRED;
    }

    /**
     * Verifica si este PaymentStatus está en disputa
     *
     * @return bool True si está en disputa
     */
    public function isDisputed(): bool
    {
        return $this->value === self::DISPUTED;
    }

    /**
     * Verifica si este PaymentStatus está activo
     * (pendiente o en proceso)
     *
     * @return bool True si está activo
     */
    public function isActive(): bool
    {
        return $this->isPending() || $this->isProcessing();
    }

    /**
     * Verifica si este PaymentStatus está finalizado
     * (completado, fallido, cancelado, reembolsado, expirado o en disputa)
     *
     * @return bool True si está finalizado
     */
    public function isFinalized(): bool
    {
        return $this->isCompleted() || $this->isFailed() || $this->isCancelled() || 
               $this->isRefunded() || $this->isExpired() || $this->isDisputed();
    }

    /**
     * Verifica si este PaymentStatus es exitoso
     * (completado)
     *
     * @return bool True si es exitoso
     */
    public function isSuccessful(): bool
    {
        return $this->isCompleted();
    }

    /**
     * Verifica si este PaymentStatus es fallido
     * (fallido, cancelado, expirado)
     *
     * @return bool True si es fallido
     */
    public function isUnsuccessful(): bool
    {
        return $this->isFailed() || $this->isCancelled() || $this->isExpired();
    }

    /**
     * Verifica si este PaymentStatus puede ser modificado
     * (solo pendiente puede ser modificado)
     *
     * @return bool True si puede ser modificado
     */
    public function canBeModified(): bool
    {
        return $this->isPending();
    }

    /**
     * Verifica si este PaymentStatus puede ser cancelado
     * (solo pendiente y en proceso pueden ser cancelados)
     *
     * @return bool True si puede ser cancelado
     */
    public function canBeCancelled(): bool
    {
        return $this->isPending() || $this->isProcessing();
    }

    /**
     * Verifica si este PaymentStatus puede ser reembolsado
     * (solo completado puede ser reembolsado)
     *
     * @return bool True si puede ser reembolsado
     */
    public function canBeRefunded(): bool
    {
        return $this->isCompleted();
    }

    /**
     * Verifica si este PaymentStatus puede ser disputado
     * (solo completado puede ser disputado)
     *
     * @return bool True si puede ser disputado
     */
    public function canBeDisputed(): bool
    {
        return $this->isCompleted();
    }

    /**
     * Verifica si este PaymentStatus requiere atención
     * (fallido, en disputa)
     *
     * @return bool True si requiere atención
     */
    public function requiresAttention(): bool
    {
        return $this->isFailed() || $this->isDisputed();
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
            self::DISPUTED => 1, // Máxima prioridad para disputas
            self::FAILED => 2, // Alta prioridad para pagos fallidos
            self::PROCESSING => 3, // Prioridad alta para pagos en proceso
            self::PENDING => 4, // Prioridad media para pagos pendientes
            self::COMPLETED, self::CANCELLED, self::REFUNDED, self::EXPIRED => 5, // Baja prioridad para estados finales
            default => 3,
        };
    }

    /**
     * Obtiene la categoría del estado
     *
     * @return string Categoría del estado (active, finalized, successful, unsuccessful, disputed)
     */
    public function getCategory(): string
    {
        if ($this->isDisputed()) {
            return 'disputed';
        }

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
            self::PENDING => 'Pago pendiente de procesamiento',
            self::PROCESSING => 'Pago en proceso',
            self::COMPLETED => 'Pago completado exitosamente',
            self::FAILED => 'Pago fallido',
            self::CANCELLED => 'Pago cancelado',
            self::REFUNDED => 'Pago reembolsado',
            self::EXPIRED => 'Pago expirado',
            self::DISPUTED => 'Pago en disputa',
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
            self::PROCESSING => [self::COMPLETED, self::FAILED, self::CANCELLED],
            self::COMPLETED => [self::REFUNDED, self::DISPUTED],
            self::FAILED => [self::CANCELLED],
            self::CANCELLED, self::REFUNDED, self::EXPIRED, self::DISPUTED => [], // Estados finales
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
     * Convierte el PaymentStatus a array para serialización
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
            'requires_attention' => $this->requiresAttention(),
            'can_be_modified' => $this->canBeModified(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_be_refunded' => $this->canBeRefunded(),
            'can_be_disputed' => $this->canBeDisputed(),
            'next_valid_statuses' => $this->getNextValidStatuses(),
        ];
    }

    /**
     * Representación en string del PaymentStatus
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
            'requires_attention' => $this->requiresAttention(),
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
                "PaymentStatus debe ser uno de los valores válidos: " . implode(', ', self::VALID_STATUSES) . 
                ", se recibió: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un PaymentStatus válido
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
     * Crea un PaymentStatus desde un valor mixto (string, array)
     *
     * @param mixed $value Valor del estado
     * @param string $arrayKey Clave para arrays (por defecto 'status')
     * @return self Nueva instancia de PaymentStatus
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
            'PaymentStatus solo puede crearse desde string o array, se recibió: ' . gettype($value)
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
     * Genera un hash único para este PaymentStatus (útil para cache keys)
     *
     * @return string Hash único del PaymentStatus
     */
    public function getHash(): string
    {
        return 'payment_status_' . $this->value . '_' . substr(md5($this->value), 0, 8);
    }

    /**
     * Verifica si este PaymentStatus es compatible con el sistema de analytics
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
     * Verifica si este PaymentStatus es compatible con el sistema de facturación
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
