<?php

declare(strict_types=1);

namespace App\Domain\Profile\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * PhotoStatus Value Object
 * 
 * Representa el estado de una foto en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del estado de las fotos en toda la aplicación.
 * 
 * Estados disponibles:
 * - pending_moderation: Foto recién subida, pendiente de moderación
 * - active: Foto aprobada y visible públicamente
 * - rejected: Foto rechazada por violar políticas de contenido
 * - hidden: Foto ocultada por el usuario o moderación
 * - processing: Foto en proceso de análisis/optimización
 * - archived: Foto archivada (no visible pero conservada)
 * - deleted: Foto marcada para eliminación
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta estados válidos predefinidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos PhotoStatus con el mismo valor son iguales
 * - Integrado con el dominio Profile: Específico para gestión de fotos de perfil
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
final class PhotoStatus implements JsonSerializable
{
    /**
     * Estados válidos para las fotos
     */
    private const VALID_STATUSES = [
        'pending_moderation' => 'Pendiente de moderación',
        'active' => 'Activa',
        'rejected' => 'Rechazada',
        'hidden' => 'Oculta',
        'processing' => 'Procesando',
        'archived' => 'Archivada',
        'deleted' => 'Eliminada',
    ];

    /**
     * Estados que requieren moderación
     */
    private const MODERATION_REQUIRED_STATUSES = [
        'pending_moderation',
    ];

    /**
     * Estados que son visibles públicamente
     */
    private const PUBLIC_VISIBLE_STATUSES = [
        'active',
    ];

    /**
     * Estados que requieren procesamiento
     */
    private const PROCESSING_STATUSES = [
        'processing',
    ];

    /**
     * Estados finales (no pueden cambiar a otros estados directamente)
     */
    private const FINAL_STATUSES = [
        'deleted',
    ];

    /**
     * El valor del estado de la foto
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
     * Factory method para crear una nueva instancia de PhotoStatus
     *
     * @param string $value Valor del estado
     * @return self Nueva instancia de PhotoStatus
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear PhotoStatus desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el estado
     * @param string $key Clave del array que contiene el estado
     * @return self Nueva instancia de PhotoStatus
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
     * Crea un PhotoStatus para foto recién subida
     *
     * @return self PhotoStatus en estado pending_moderation
     */
    public static function pendingModeration(): self
    {
        return new self('pending_moderation');
    }

    /**
     * Crea un PhotoStatus para foto aprobada
     *
     * @return self PhotoStatus en estado active
     */
    public static function active(): self
    {
        return new self('active');
    }

    /**
     * Crea un PhotoStatus para foto rechazada
     *
     * @return self PhotoStatus en estado rejected
     */
    public static function rejected(): self
    {
        return new self('rejected');
    }

    /**
     * Crea un PhotoStatus para foto oculta
     *
     * @return self PhotoStatus en estado hidden
     */
    public static function hidden(): self
    {
        return new self('hidden');
    }

    /**
     * Crea un PhotoStatus para foto en procesamiento
     *
     * @return self PhotoStatus en estado processing
     */
    public static function processing(): self
    {
        return new self('processing');
    }

    /**
     * Crea un PhotoStatus para foto archivada
     *
     * @return self PhotoStatus en estado archived
     */
    public static function archived(): self
    {
        return new self('archived');
    }

    /**
     * Crea un PhotoStatus para foto eliminada
     *
     * @return self PhotoStatus en estado deleted
     */
    public static function deleted(): self
    {
        return new self('deleted');
    }

    /**
     * Obtiene el valor string del estado
     *
     * @return string Valor del estado
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Obtiene la descripción legible del estado
     *
     * @return string Descripción del estado
     */
    public function getDescription(): string
    {
        return self::VALID_STATUSES[$this->value] ?? 'Estado desconocido';
    }

    /**
     * Verifica si este PhotoStatus es igual a otro
     *
     * @param self $other Otro PhotoStatus para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si la foto requiere moderación
     *
     * @return bool True si requiere moderación
     */
    public function requiresModeration(): bool
    {
        return in_array($this->value, self::MODERATION_REQUIRED_STATUSES, true);
    }

    /**
     * Verifica si la foto es visible públicamente
     *
     * @return bool True si es visible públicamente
     */
    public function isPubliclyVisible(): bool
    {
        return in_array($this->value, self::PUBLIC_VISIBLE_STATUSES, true);
    }

    /**
     * Verifica si la foto está en procesamiento
     *
     * @return bool True si está en procesamiento
     */
    public function isProcessing(): bool
    {
        return in_array($this->value, self::PROCESSING_STATUSES, true);
    }

    /**
     * Verifica si la foto está en estado final
     *
     * @return bool True si está en estado final
     */
    public function isFinal(): bool
    {
        return in_array($this->value, self::FINAL_STATUSES, true);
    }

    /**
     * Verifica si la foto está activa
     *
     * @return bool True si está activa
     */
    public function isActive(): bool
    {
        return $this->value === 'active';
    }

    /**
     * Verifica si la foto está pendiente de moderación
     *
     * @return bool True si está pendiente
     */
    public function isPending(): bool
    {
        return $this->value === 'pending_moderation';
    }

    /**
     * Verifica si la foto fue rechazada
     *
     * @return bool True si fue rechazada
     */
    public function isRejected(): bool
    {
        return $this->value === 'rejected';
    }

    /**
     * Verifica si la foto está oculta
     *
     * @return bool True si está oculta
     */
    public function isHidden(): bool
    {
        return $this->value === 'hidden';
    }

    /**
     * Verifica si la foto está archivada
     *
     * @return bool True si está archivada
     */
    public function isArchived(): bool
    {
        return $this->value === 'archived';
    }

    /**
     * Verifica si la foto está marcada para eliminación
     *
     * @return bool True si está marcada para eliminación
     */
    public function isDeleted(): bool
    {
        return $this->value === 'deleted';
    }

    /**
     * Verifica si la foto puede ser vista por el propietario
     *
     * @return bool True si puede ser vista por el propietario
     */
    public function isVisibleToOwner(): bool
    {
        return !in_array($this->value, ['deleted'], true);
    }

    /**
     * Verifica si la foto puede ser editada
     *
     * @return bool True si puede ser editada
     */
    public function canBeEdited(): bool
    {
        return in_array($this->value, ['active', 'hidden', 'archived'], true);
    }

    /**
     * Verifica si la foto puede ser eliminada
     *
     * @return bool True si puede ser eliminada
     */
    public function canBeDeleted(): bool
    {
        return !in_array($this->value, ['deleted'], true);
    }

    /**
     * Verifica si la foto puede ser restaurada
     *
     * @return bool True si puede ser restaurada
     */
    public function canBeRestored(): bool
    {
        return in_array($this->value, ['hidden', 'archived'], true);
    }

    /**
     * Verifica si la foto puede cambiar al estado especificado
     *
     * @param self $newStatus Nuevo estado deseado
     * @return bool True si puede cambiar al nuevo estado
     */
    public function canTransitionTo(self $newStatus): bool
    {
        // Si ya está en estado final, no puede cambiar
        if ($this->isFinal()) {
            return false;
        }

        // Transiciones permitidas
        $allowedTransitions = [
            'pending_moderation' => ['active', 'rejected', 'processing', 'deleted'],
            'active' => ['hidden', 'archived', 'deleted'],
            'rejected' => ['pending_moderation', 'deleted'],
            'hidden' => ['active', 'archived', 'deleted'],
            'processing' => ['active', 'rejected', 'hidden', 'deleted'],
            'archived' => ['active', 'hidden', 'deleted'],
        ];

        return in_array($newStatus->value, $allowedTransitions[$this->value] ?? [], true);
    }

    /**
     * Obtiene los estados a los que puede transicionar
     *
     * @return array Array de PhotoStatus válidos para transición
     */
    public function getValidTransitions(): array
    {
        $allowedTransitions = [
            'pending_moderation' => ['active', 'rejected', 'processing', 'deleted'],
            'active' => ['hidden', 'archived', 'deleted'],
            'rejected' => ['pending_moderation', 'deleted'],
            'hidden' => ['active', 'archived', 'deleted'],
            'processing' => ['active', 'rejected', 'hidden', 'deleted'],
            'archived' => ['active', 'hidden', 'deleted'],
            'deleted' => [], // Estado final
        ];

        $transitions = $allowedTransitions[$this->value] ?? [];
        
        return array_map(fn(string $status) => new self($status), $transitions);
    }

    /**
     * Obtiene el color asociado al estado (para UI)
     *
     * @return string Código de color
     */
    public function getColor(): string
    {
        $colors = [
            'pending_moderation' => '#f59e0b', // amber
            'active' => '#10b981', // emerald
            'rejected' => '#ef4444', // red
            'hidden' => '#6b7280', // gray
            'processing' => '#3b82f6', // blue
            'archived' => '#8b5cf6', // violet
            'deleted' => '#dc2626', // red-600
        ];

        return $colors[$this->value] ?? '#6b7280';
    }

    /**
     * Obtiene el icono asociado al estado (para UI)
     *
     * @return string Nombre del icono
     */
    public function getIcon(): string
    {
        $icons = [
            'pending_moderation' => 'clock',
            'active' => 'check-circle',
            'rejected' => 'x-circle',
            'hidden' => 'eye-slash',
            'processing' => 'cog',
            'archived' => 'archive',
            'deleted' => 'trash',
        ];

        return $icons[$this->value] ?? 'question-mark-circle';
    }

    /**
     * Convierte el PhotoStatus a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'description' => $this->getDescription(),
            'requires_moderation' => $this->requiresModeration(),
            'is_publicly_visible' => $this->isPubliclyVisible(),
            'is_processing' => $this->isProcessing(),
            'is_final' => $this->isFinal(),
            'can_be_edited' => $this->canBeEdited(),
            'can_be_deleted' => $this->canBeDeleted(),
            'color' => $this->getColor(),
            'icon' => $this->getIcon(),
        ];
    }

    /**
     * Representación en string del PhotoStatus
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
            'requires_moderation' => $this->requiresModeration(),
            'is_publicly_visible' => $this->isPubliclyVisible(),
            'is_processing' => $this->isProcessing(),
            'is_final' => $this->isFinal(),
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
        if (!array_key_exists($value, self::VALID_STATUSES)) {
            $validStatuses = implode(', ', array_keys(self::VALID_STATUSES));
            throw new InvalidArgumentException(
                "PhotoStatus debe ser uno de: {$validStatuses}, se recibió: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un PhotoStatus válido
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
     * Crea un PhotoStatus desde un valor mixto (string, array)
     *
     * @param mixed $value Valor del estado
     * @param string $arrayKey Clave para arrays (por defecto 'status')
     * @return self Nueva instancia de PhotoStatus
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
            'PhotoStatus solo puede crearse desde string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Crea un PhotoStatus desde un modelo UserImage
     *
     * @param \App\Models\User\UserImage $image Modelo UserImage
     * @return self Nueva instancia de PhotoStatus
     * @throws InvalidArgumentException Si el modelo no tiene estado válido
     */
    public static function fromUserImage(\App\Models\User\UserImage $image): self
    {
        if (!$image->exists || !$image->status) {
            throw new InvalidArgumentException(
                'El modelo UserImage debe existir y tener un estado válido'
            );
        }

        return self::fromString($image->status);
    }

    /**
     * Obtiene todos los estados válidos
     *
     * @return array Array con todos los estados válidos
     */
    public static function getAllValidStatuses(): array
    {
        return array_keys(self::VALID_STATUSES);
    }

    /**
     * Obtiene todos los estados con sus descripciones
     *
     * @return array Array con estados y descripciones
     */
    public static function getAllStatusesWithDescriptions(): array
    {
        return self::VALID_STATUSES;
    }

    /**
     * Obtiene estados que requieren moderación
     *
     * @return array Array de estados que requieren moderación
     */
    public static function getModerationRequiredStatuses(): array
    {
        return self::MODERATION_REQUIRED_STATUSES;
    }

    /**
     * Obtiene estados públicamente visibles
     *
     * @return array Array de estados públicamente visibles
     */
    public static function getPubliclyVisibleStatuses(): array
    {
        return self::PUBLIC_VISIBLE_STATUSES;
    }

    /**
     * Verifica si este PhotoStatus corresponde a un estado con características específicas
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['requires_moderation']) && $criteria['requires_moderation']) {
            if (!$this->requiresModeration()) {
                return false;
            }
        }

        if (isset($criteria['publicly_visible']) && $criteria['publicly_visible']) {
            if (!$this->isPubliclyVisible()) {
                return false;
            }
        }

        if (isset($criteria['can_be_edited']) && $criteria['can_be_edited']) {
            if (!$this->canBeEdited()) {
                return false;
            }
        }

        if (isset($criteria['is_final']) && $criteria['is_final']) {
            if (!$this->isFinal()) {
                return false;
            }
        }

        if (isset($criteria['statuses'])) {
            if (!in_array($this->value, $criteria['statuses'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del PhotoStatus
     *
     * @return array Estadísticas del estado
     */
    public function getStats(): array
    {
        return [
            'value' => $this->value,
            'description' => $this->getDescription(),
            'requires_moderation' => $this->requiresModeration(),
            'is_publicly_visible' => $this->isPubliclyVisible(),
            'is_processing' => $this->isProcessing(),
            'is_final' => $this->isFinal(),
            'can_be_edited' => $this->canBeEdited(),
            'can_be_deleted' => $this->canBeDeleted(),
            'can_be_restored' => $this->canBeRestored(),
            'is_visible_to_owner' => $this->isVisibleToOwner(),
            'valid_transitions_count' => count($this->getValidTransitions()),
            'color' => $this->getColor(),
            'icon' => $this->getIcon(),
        ];
    }
}
