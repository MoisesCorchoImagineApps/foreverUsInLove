<?php

declare(strict_types=1);

namespace App\Domain\Profile\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * PhotoProcessingStatus Value Object
 * 
 * Representa el estado de procesamiento de una foto en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del estado de procesamiento de las fotos en toda la aplicación.
 * 
 * Estados de procesamiento disponibles:
 * - queued: Foto en cola de procesamiento
 * - analyzing: Foto en proceso de análisis (detección de rostros, calidad, etc.)
 * - generating_thumbnails: Generando miniaturas en diferentes tamaños
 * - applying_filters: Aplicando filtros de mejora automática
 * - moderating: En proceso de moderación de contenido
 * - optimizing: Optimizando la imagen para almacenamiento
 * - uploading: Subiendo archivos procesados al CDN
 * - completed: Procesamiento completado exitosamente
 * - failed: Error en el procesamiento
 * - retrying: Reintentando procesamiento después de error
 * - cancelled: Procesamiento cancelado por el usuario o sistema
 * - timeout: Timeout en el procesamiento
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta estados válidos predefinidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos PhotoProcessingStatus con el mismo valor son iguales
 * - Integrado con el dominio Profile: Específico para procesamiento de fotos de perfil
 * - Compatible con PhotoStatus y PhotoModerationResult: Estados coordinados
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
final class PhotoProcessingStatus implements JsonSerializable
{
    /**
     * Estados válidos para el procesamiento de fotos
     */
    private const VALID_STATUSES = [
        'queued' => 'En cola de procesamiento',
        'analyzing' => 'Analizando imagen',
        'generating_thumbnails' => 'Generando miniaturas',
        'applying_filters' => 'Aplicando filtros',
        'moderating' => 'Moderando contenido',
        'optimizing' => 'Optimizando imagen',
        'uploading' => 'Subiendo archivos',
        'completed' => 'Procesamiento completado',
        'failed' => 'Error en procesamiento',
        'retrying' => 'Reintentando procesamiento',
        'cancelled' => 'Procesamiento cancelado',
        'timeout' => 'Timeout en procesamiento',
    ];

    /**
     * Estados que indican procesamiento activo
     */
    private const ACTIVE_PROCESSING_STATUSES = [
        'queued',
        'analyzing',
        'generating_thumbnails',
        'applying_filters',
        'moderating',
        'optimizing',
        'uploading',
    ];

    /**
     * Estados finales exitosos
     */
    private const SUCCESS_STATUSES = [
        'completed',
    ];

    /**
     * Estados de error o fallo
     */
    private const ERROR_STATUSES = [
        'failed',
        'timeout',
        'cancelled',
    ];

    /**
     * Estados que pueden ser reintentados
     */
    private const RETRYABLE_STATUSES = [
        'failed',
        'timeout',
    ];

    /**
     * Estados que requieren intervención manual
     */
    private const MANUAL_INTERVENTION_STATUSES = [
        'failed',
        'timeout',
        'cancelled',
    ];

    /**
     * El valor del estado de procesamiento
     */
    private readonly string $value;

    /**
     * Progreso del procesamiento (0-100)
     */
    private readonly int $progress;

    /**
     * Mensaje descriptivo del estado actual
     */
    private readonly ?string $message;

    /**
     * Detalles técnicos del procesamiento
     */
    private readonly array $details;

    /**
     * Timestamp del estado actual
     */
    private readonly \DateTimeImmutable $timestamp;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(
        string $value,
        int $progress = 0,
        ?string $message = null,
        array $details = []
    ) {
        self::validateValue($value);
        self::validateProgress($progress);
        
        $this->value = $value;
        $this->progress = $progress;
        $this->message = $message;
        $this->details = $details;
        $this->timestamp = new \DateTimeImmutable();
    }

    /**
     * Factory method para crear PhotoProcessingStatus en cola
     *
     * @param string|null $message Mensaje opcional
     * @param array $details Detalles adicionales
     * @return self Nueva instancia de PhotoProcessingStatus
     */
    public static function queued(?string $message = null, array $details = []): self
    {
        return new self('queued', 0, $message ?? 'Foto añadida a la cola de procesamiento', $details);
    }

    /**
     * Factory method para crear PhotoProcessingStatus analizando
     *
     * @param int $progress Progreso del análisis (0-100)
     * @param string|null $message Mensaje opcional
     * @param array $details Detalles del análisis
     * @return self Nueva instancia de PhotoProcessingStatus
     */
    public static function analyzing(int $progress = 10, ?string $message = null, array $details = []): self
    {
        return new self('analyzing', $progress, $message ?? 'Analizando imagen para detectar rostros y calidad', $details);
    }

    /**
     * Factory method para crear PhotoProcessingStatus generando miniaturas
     *
     * @param int $progress Progreso de generación (0-100)
     * @param string|null $message Mensaje opcional
     * @param array $details Detalles de las miniaturas
     * @return self Nueva instancia de PhotoProcessingStatus
     */
    public static function generatingThumbnails(int $progress = 30, ?string $message = null, array $details = []): self
    {
        return new self('generating_thumbnails', $progress, $message ?? 'Generando miniaturas en diferentes tamaños', $details);
    }

    /**
     * Factory method para crear PhotoProcessingStatus aplicando filtros
     *
     * @param int $progress Progreso de filtros (0-100)
     * @param string|null $message Mensaje opcional
     * @param array $details Detalles de los filtros aplicados
     * @return self Nueva instancia de PhotoProcessingStatus
     */
    public static function applyingFilters(int $progress = 50, ?string $message = null, array $details = []): self
    {
        return new self('applying_filters', $progress, $message ?? 'Aplicando filtros de mejora automática', $details);
    }

    /**
     * Factory method para crear PhotoProcessingStatus moderando
     *
     * @param int $progress Progreso de moderación (0-100)
     * @param string|null $message Mensaje opcional
     * @param array $details Detalles de la moderación
     * @return self Nueva instancia de PhotoProcessingStatus
     */
    public static function moderating(int $progress = 60, ?string $message = null, array $details = []): self
    {
        return new self('moderating', $progress, $message ?? 'Moderando contenido automáticamente', $details);
    }

    /**
     * Factory method para crear PhotoProcessingStatus optimizando
     *
     * @param int $progress Progreso de optimización (0-100)
     * @param string|null $message Mensaje opcional
     * @param array $details Detalles de la optimización
     * @return self Nueva instancia de PhotoProcessingStatus
     */
    public static function optimizing(int $progress = 70, ?string $message = null, array $details = []): self
    {
        return new self('optimizing', $progress, $message ?? 'Optimizando imagen para almacenamiento', $details);
    }

    /**
     * Factory method para crear PhotoProcessingStatus subiendo
     *
     * @param int $progress Progreso de subida (0-100)
     * @param string|null $message Mensaje opcional
     * @param array $details Detalles de la subida
     * @return self Nueva instancia de PhotoProcessingStatus
     */
    public static function uploading(int $progress = 80, ?string $message = null, array $details = []): self
    {
        return new self('uploading', $progress, $message ?? 'Subiendo archivos procesados al CDN', $details);
    }

    /**
     * Factory method para crear PhotoProcessingStatus completado
     *
     * @param string|null $message Mensaje opcional
     * @param array $details Detalles finales del procesamiento
     * @return self Nueva instancia de PhotoProcessingStatus
     */
    public static function completed(?string $message = null, array $details = []): self
    {
        return new self('completed', 100, $message ?? 'Procesamiento completado exitosamente', $details);
    }

    /**
     * Factory method para crear PhotoProcessingStatus fallido
     *
     * @param string $errorMessage Mensaje de error
     * @param array $details Detalles del error
     * @return self Nueva instancia de PhotoProcessingStatus
     */
    public static function failed(string $errorMessage, array $details = []): self
    {
        return new self('failed', 0, $errorMessage, $details);
    }

    /**
     * Factory method para crear PhotoProcessingStatus reintentando
     *
     * @param int $retryCount Número de intento
     * @param string|null $message Mensaje opcional
     * @param array $details Detalles del reintento
     * @return self Nueva instancia de PhotoProcessingStatus
     */
    public static function retrying(int $retryCount = 1, ?string $message = null, array $details = []): self
    {
        $message = $message ?? "Reintentando procesamiento (intento {$retryCount})";
        $details['retry_count'] = $retryCount;
        
        return new self('retrying', 0, $message, $details);
    }

    /**
     * Factory method para crear PhotoProcessingStatus cancelado
     *
     * @param string|null $reason Razón de la cancelación
     * @param array $details Detalles de la cancelación
     * @return self Nueva instancia de PhotoProcessingStatus
     */
    public static function cancelled(?string $reason = null, array $details = []): self
    {
        return new self('cancelled', 0, $reason ?? 'Procesamiento cancelado', $details);
    }

    /**
     * Factory method para crear PhotoProcessingStatus timeout
     *
     * @param string|null $message Mensaje opcional
     * @param array $details Detalles del timeout
     * @return self Nueva instancia de PhotoProcessingStatus
     */
    public static function timeout(?string $message = null, array $details = []): self
    {
        return new self('timeout', 0, $message ?? 'Timeout en el procesamiento', $details);
    }

    /**
     * Factory method para crear desde string
     *
     * @param string $value Valor del estado
     * @param array $options Opciones adicionales
     * @return self Nueva instancia de PhotoProcessingStatus
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value, array $options = []): self
    {
        self::validateValue($value);
        
        return new self(
            $value,
            $options['progress'] ?? 0,
            $options['message'] ?? null,
            $options['details'] ?? []
        );
    }

    /**
     * Factory method para crear desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el estado
     * @return self Nueva instancia de PhotoProcessingStatus
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['value'])) {
            throw new InvalidArgumentException('Clave "value" no encontrada en los datos proporcionados');
        }

        return new self(
            $data['value'],
            $data['progress'] ?? 0,
            $data['message'] ?? null,
            $data['details'] ?? []
        );
    }

    /**
     * Factory method para crear desde un modelo UserImage
     *
     * @param \App\Models\User\UserImage $image Modelo UserImage
     * @return self Nueva instancia de PhotoProcessingStatus
     * @throws InvalidArgumentException Si el modelo no tiene datos válidos
     */
    public static function fromUserImage(\App\Models\User\UserImage $image): self
    {
        if (!$image->exists) {
            throw new InvalidArgumentException('El modelo UserImage debe existir');
        }

        // Mapear estado del modelo a estado de procesamiento
        $statusMapping = [
            'pending_moderation' => 'queued',
            'active' => 'completed',
            'rejected' => 'failed',
            'hidden' => 'completed',
        ];

        $processingStatus = $statusMapping[$image->status] ?? 'queued';
        
        $details = [];
        if ($image->metadata) {
            $details['metadata'] = $image->metadata;
        }
        if ($image->moderation_results) {
            $details['moderation_results'] = $image->moderation_results;
        }

        return new self(
            $processingStatus,
            $processingStatus === 'completed' ? 100 : 0,
            $image->rejection_reason,
            $details
        );
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
     * Obtiene el progreso del procesamiento
     *
     * @return int Progreso (0-100)
     */
    public function getProgress(): int
    {
        return $this->progress;
    }

    /**
     * Obtiene el mensaje del estado
     *
     * @return string|null Mensaje del estado
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Obtiene los detalles del procesamiento
     *
     * @return array Detalles del procesamiento
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Obtiene el timestamp del estado
     *
     * @return \DateTimeImmutable Timestamp del estado
     */
    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * Verifica si este PhotoProcessingStatus es igual a otro
     *
     * @param self $other Otro PhotoProcessingStatus para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value &&
               $this->progress === $other->progress &&
               $this->message === $other->message;
    }

    /**
     * Verifica si el procesamiento está activo
     *
     * @return bool True si está en procesamiento activo
     */
    public function isActive(): bool
    {
        return in_array($this->value, self::ACTIVE_PROCESSING_STATUSES, true);
    }

    /**
     * Verifica si el procesamiento fue exitoso
     *
     * @return bool True si fue exitoso
     */
    public function isSuccessful(): bool
    {
        return in_array($this->value, self::SUCCESS_STATUSES, true);
    }

    /**
     * Verifica si el procesamiento falló
     *
     * @return bool True si falló
     */
    public function isFailed(): bool
    {
        return in_array($this->value, self::ERROR_STATUSES, true);
    }

    /**
     * Verifica si el procesamiento puede ser reintentado
     *
     * @return bool True si puede ser reintentado
     */
    public function isRetryable(): bool
    {
        return in_array($this->value, self::RETRYABLE_STATUSES, true);
    }

    /**
     * Verifica si el procesamiento requiere intervención manual
     *
     * @return bool True si requiere intervención manual
     */
    public function requiresManualIntervention(): bool
    {
        return in_array($this->value, self::MANUAL_INTERVENTION_STATUSES, true);
    }

    /**
     * Verifica si el procesamiento está completado
     *
     * @return bool True si está completado
     */
    public function isCompleted(): bool
    {
        return $this->value === 'completed';
    }

    /**
     * Verifica si el procesamiento está en cola
     *
     * @return bool True si está en cola
     */
    public function isQueued(): bool
    {
        return $this->value === 'queued';
    }

    /**
     * Verifica si el procesamiento está analizando
     *
     * @return bool True si está analizando
     */
    public function isAnalyzing(): bool
    {
        return $this->value === 'analyzing';
    }

    /**
     * Verifica si el procesamiento está generando miniaturas
     *
     * @return bool True si está generando miniaturas
     */
    public function isGeneratingThumbnails(): bool
    {
        return $this->value === 'generating_thumbnails';
    }

    /**
     * Verifica si el procesamiento está aplicando filtros
     *
     * @return bool True si está aplicando filtros
     */
    public function isApplyingFilters(): bool
    {
        return $this->value === 'applying_filters';
    }

    /**
     * Verifica si el procesamiento está moderando
     *
     * @return bool True si está moderando
     */
    public function isModerating(): bool
    {
        return $this->value === 'moderating';
    }

    /**
     * Verifica si el procesamiento está optimizando
     *
     * @return bool True si está optimizando
     */
    public function isOptimizing(): bool
    {
        return $this->value === 'optimizing';
    }

    /**
     * Verifica si el procesamiento está subiendo
     *
     * @return bool True si está subiendo
     */
    public function isUploading(): bool
    {
        return $this->value === 'uploading';
    }

    /**
     * Verifica si el procesamiento está reintentando
     *
     * @return bool True si está reintentando
     */
    public function isRetrying(): bool
    {
        return $this->value === 'retrying';
    }

    /**
     * Verifica si el procesamiento fue cancelado
     *
     * @return bool True si fue cancelado
     */
    public function isCancelled(): bool
    {
        return $this->value === 'cancelled';
    }

    /**
     * Verifica si el procesamiento tuvo timeout
     *
     * @return bool True si tuvo timeout
     */
    public function isTimeout(): bool
    {
        return $this->value === 'timeout';
    }

    /**
     * Obtiene el tiempo estimado restante en segundos
     *
     * @return int|null Tiempo estimado en segundos o null si no se puede estimar
     */
    public function getEstimatedTimeRemaining(): ?int
    {
        if (!$this->isActive() || $this->progress === 0) {
            return null;
        }

        // Estimación básica basada en el progreso
        $estimatedTotalTime = 60; // 60 segundos promedio
        $remainingProgress = 100 - $this->progress;
        
        return (int) round(($remainingProgress / $this->progress) * $estimatedTotalTime);
    }

    /**
     * Obtiene el color asociado al estado (para UI)
     *
     * @return string Código de color
     */
    public function getColor(): string
    {
        $colors = [
            'queued' => '#6b7280', // gray
            'analyzing' => '#3b82f6', // blue
            'generating_thumbnails' => '#8b5cf6', // violet
            'applying_filters' => '#06b6d4', // cyan
            'moderating' => '#f59e0b', // amber
            'optimizing' => '#10b981', // emerald
            'uploading' => '#6366f1', // indigo
            'completed' => '#22c55e', // green
            'failed' => '#ef4444', // red
            'retrying' => '#f97316', // orange
            'cancelled' => '#6b7280', // gray
            'timeout' => '#dc2626', // red-600
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
            'queued' => 'clock',
            'analyzing' => 'search',
            'generating_thumbnails' => 'photo',
            'applying_filters' => 'sparkles',
            'moderating' => 'shield-check',
            'optimizing' => 'cog',
            'uploading' => 'cloud-upload',
            'completed' => 'check-circle',
            'failed' => 'x-circle',
            'retrying' => 'arrow-path',
            'cancelled' => 'x-mark',
            'timeout' => 'clock',
        ];

        return $icons[$this->value] ?? 'question-mark-circle';
    }

    /**
     * Convierte el PhotoProcessingStatus a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'description' => $this->getDescription(),
            'progress' => $this->progress,
            'message' => $this->message,
            'details' => $this->details,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'is_active' => $this->isActive(),
            'is_successful' => $this->isSuccessful(),
            'is_failed' => $this->isFailed(),
            'is_retryable' => $this->isRetryable(),
            'requires_manual_intervention' => $this->requiresManualIntervention(),
            'estimated_time_remaining' => $this->getEstimatedTimeRemaining(),
            'color' => $this->getColor(),
            'icon' => $this->getIcon(),
        ];
    }

    /**
     * Representación en string del PhotoProcessingStatus
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
            'progress' => $this->progress,
            'message' => $this->message,
            'is_active' => $this->isActive(),
            'is_successful' => $this->isSuccessful(),
            'is_failed' => $this->isFailed(),
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Valida que el valor del estado sea válido
     *
     * @param string $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validateValue(string $value): void
    {
        if (!array_key_exists($value, self::VALID_STATUSES)) {
            $validStatuses = implode(', ', array_keys(self::VALID_STATUSES));
            throw new InvalidArgumentException(
                "PhotoProcessingStatus debe ser uno de: {$validStatuses}, se recibió: {$value}"
            );
        }
    }

    /**
     * Valida que el progreso esté en el rango válido
     *
     * @param int $progress Progreso a validar
     * @throws InvalidArgumentException Si el progreso no es válido
     */
    private static function validateProgress(int $progress): void
    {
        if ($progress < 0 || $progress > 100) {
            throw new InvalidArgumentException(
                "El progreso debe estar entre 0 y 100, se recibió: {$progress}"
            );
        }
    }

    /**
     * Verifica si un valor es un PhotoProcessingStatus válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if (is_string($value)) {
                self::validateValue($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
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
     * Obtiene estados de procesamiento activo
     *
     * @return array Array de estados activos
     */
    public static function getActiveProcessingStatuses(): array
    {
        return self::ACTIVE_PROCESSING_STATUSES;
    }

    /**
     * Obtiene estados de éxito
     *
     * @return array Array de estados de éxito
     */
    public static function getSuccessStatuses(): array
    {
        return self::SUCCESS_STATUSES;
    }

    /**
     * Obtiene estados de error
     *
     * @return array Array de estados de error
     */
    public static function getErrorStatuses(): array
    {
        return self::ERROR_STATUSES;
    }

    /**
     * Obtiene estados que pueden ser reintentados
     *
     * @return array Array de estados reintentables
     */
    public static function getRetryableStatuses(): array
    {
        return self::RETRYABLE_STATUSES;
    }

    /**
     * Verifica si este PhotoProcessingStatus corresponde a un estado con características específicas
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['is_active']) && $criteria['is_active']) {
            if (!$this->isActive()) {
                return false;
            }
        }

        if (isset($criteria['is_successful']) && $criteria['is_successful']) {
            if (!$this->isSuccessful()) {
                return false;
            }
        }

        if (isset($criteria['is_failed']) && $criteria['is_failed']) {
            if (!$this->isFailed()) {
                return false;
            }
        }

        if (isset($criteria['is_retryable']) && $criteria['is_retryable']) {
            if (!$this->isRetryable()) {
                return false;
            }
        }

        if (isset($criteria['requires_manual_intervention']) && $criteria['requires_manual_intervention']) {
            if (!$this->requiresManualIntervention()) {
                return false;
            }
        }

        if (isset($criteria['progress_min'])) {
            if ($this->progress < $criteria['progress_min']) {
                return false;
            }
        }

        if (isset($criteria['progress_max'])) {
            if ($this->progress > $criteria['progress_max']) {
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
     * Obtiene estadísticas básicas del PhotoProcessingStatus
     *
     * @return array Estadísticas del estado
     */
    public function getStats(): array
    {
        return [
            'value' => $this->value,
            'description' => $this->getDescription(),
            'progress' => $this->progress,
            'message' => $this->message,
            'details_count' => count($this->details),
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'is_active' => $this->isActive(),
            'is_successful' => $this->isSuccessful(),
            'is_failed' => $this->isFailed(),
            'is_retryable' => $this->isRetryable(),
            'requires_manual_intervention' => $this->requiresManualIntervention(),
            'estimated_time_remaining' => $this->getEstimatedTimeRemaining(),
            'color' => $this->getColor(),
            'icon' => $this->getIcon(),
        ];
    }

    /**
     * Convierte el estado de procesamiento a formato compatible con PhotoStatus
     *
     * @return PhotoStatus Estado de foto compatible
     */
    public function toPhotoStatus(): PhotoStatus
    {
        $statusMapping = [
            'queued' => 'processing',
            'analyzing' => 'processing',
            'generating_thumbnails' => 'processing',
            'applying_filters' => 'processing',
            'moderating' => 'pending_moderation',
            'optimizing' => 'processing',
            'uploading' => 'processing',
            'completed' => 'active',
            'failed' => 'rejected',
            'retrying' => 'processing',
            'cancelled' => 'hidden',
            'timeout' => 'rejected',
        ];

        $statusValue = $statusMapping[$this->value] ?? 'processing';
        
        return PhotoStatus::fromString($statusValue);
    }

    /**
     * Convierte el estado de procesamiento a formato compatible con PhotoModerationResult
     *
     * @return PhotoModerationResult Resultado de moderación compatible
     */
    public function toPhotoModerationResult(): PhotoModerationResult
    {
        $resultMapping = [
            'queued' => 'pending_review',
            'analyzing' => 'pending_review',
            'generating_thumbnails' => 'pending_review',
            'applying_filters' => 'pending_review',
            'moderating' => 'pending_review',
            'optimizing' => 'pending_review',
            'uploading' => 'pending_review',
            'completed' => 'approved',
            'failed' => 'rejected',
            'retrying' => 'pending_review',
            'cancelled' => 'rejected',
            'timeout' => 'needs_human_review',
        ];

        $resultValue = $resultMapping[$this->value] ?? 'pending_review';
        
        return PhotoModerationResult::fromString($resultValue, [
            'reason' => $this->message,
            'analysis_details' => $this->details,
        ]);
    }

    /**
     * Convierte el estado de procesamiento a formato compatible con el modelo UserImage
     *
     * @return array Datos compatibles con UserImage
     */
    public function toUserImageFormat(): array
    {
        $statusMapping = [
            'queued' => 'pending_moderation',
            'analyzing' => 'pending_moderation',
            'generating_thumbnails' => 'pending_moderation',
            'applying_filters' => 'pending_moderation',
            'moderating' => 'pending_moderation',
            'optimizing' => 'pending_moderation',
            'uploading' => 'pending_moderation',
            'completed' => 'active',
            'failed' => 'rejected',
            'retrying' => 'pending_moderation',
            'cancelled' => 'hidden',
            'timeout' => 'rejected',
        ];

        $status = $statusMapping[$this->value] ?? 'pending_moderation';

        return [
            'status' => $status,
            'rejection_reason' => $this->isFailed() ? $this->message : null,
            'metadata' => $this->details,
            'moderation_results' => $this->details,
        ];
    }
}
