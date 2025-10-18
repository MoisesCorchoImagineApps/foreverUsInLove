<?php

declare(strict_types=1);

namespace App\Domain\Profile\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * PhotoModerationResult Value Object
 * 
 * Representa el resultado de la moderación de una foto en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de los resultados de moderación en toda la aplicación.
 * 
 * Tipos de resultado disponibles:
 * - approved: Foto aprobada y visible públicamente
 * - rejected: Foto rechazada por violar políticas de contenido
 * - pending_review: Foto pendiente de revisión manual
 * - auto_approved: Foto aprobada automáticamente por IA
 * - auto_rejected: Foto rechazada automáticamente por IA
 * - needs_human_review: Requiere revisión humana adicional
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta resultados válidos predefinidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos PhotoModerationResult con el mismo valor son iguales
 * - Integrado con el dominio Profile: Específico para moderación de fotos de perfil
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
final class PhotoModerationResult implements JsonSerializable
{
    /**
     * Resultados válidos para la moderación de fotos
     */
    private const VALID_RESULTS = [
        'approved' => 'Aprobada',
        'rejected' => 'Rechazada',
        'pending_review' => 'Pendiente de revisión',
        'auto_approved' => 'Aprobada automáticamente',
        'auto_rejected' => 'Rechazada automáticamente',
        'needs_human_review' => 'Requiere revisión humana',
    ];

    /**
     * Resultados que requieren acción manual
     */
    private const MANUAL_REVIEW_REQUIRED = [
        'pending_review',
        'needs_human_review',
    ];

    /**
     * Resultados que son aprobaciones
     */
    private const APPROVED_RESULTS = [
        'approved',
        'auto_approved',
    ];

    /**
     * Resultados que son rechazos
     */
    private const REJECTED_RESULTS = [
        'rejected',
        'auto_rejected',
    ];

    /**
     * Resultados automáticos
     */
    private const AUTOMATIC_RESULTS = [
        'auto_approved',
        'auto_rejected',
    ];

    /**
     * El valor del resultado de moderación
     */
    private readonly string $value;

    /**
     * Razón del resultado (opcional)
     */
    private readonly ?string $reason;

    /**
     * Nivel de confianza del resultado (0.0 - 1.0)
     */
    private readonly float $confidence;

    /**
     * Detalles técnicos del análisis
     */
    private readonly array $analysisDetails;

    /**
     * Timestamp del resultado
     */
    private readonly \DateTimeImmutable $timestamp;

    /**
     * ID del moderador (si fue moderación manual)
     */
    private readonly ?int $moderatorId;

    /**
     * Constructor privado para forzar el uso de factory methods
     */
    private function __construct(
        string $value,
        ?string $reason = null,
        float $confidence = 1.0,
        array $analysisDetails = [],
        ?int $moderatorId = null
    ) {
        self::validateValue($value);
        self::validateConfidence($confidence);
        
        $this->value = $value;
        $this->reason = $reason;
        $this->confidence = $confidence;
        $this->analysisDetails = $analysisDetails;
        $this->timestamp = new \DateTimeImmutable();
        $this->moderatorId = $moderatorId;
    }

    /**
     * Factory method para crear resultado de aprobación
     *
     * @param string|null $reason Razón de la aprobación
     * @param float $confidence Nivel de confianza
     * @param array $analysisDetails Detalles del análisis
     * @param int|null $moderatorId ID del moderador
     * @return self Nueva instancia de PhotoModerationResult
     */
    public static function approved(
        ?string $reason = null,
        float $confidence = 1.0,
        array $analysisDetails = [],
        ?int $moderatorId = null
    ): self {
        return new self('approved', $reason, $confidence, $analysisDetails, $moderatorId);
    }

    /**
     * Factory method para crear resultado de rechazo
     *
     * @param string $reason Razón del rechazo
     * @param float $confidence Nivel de confianza
     * @param array $analysisDetails Detalles del análisis
     * @param int|null $moderatorId ID del moderador
     * @return self Nueva instancia de PhotoModerationResult
     */
    public static function rejected(
        string $reason,
        float $confidence = 1.0,
        array $analysisDetails = [],
        ?int $moderatorId = null
    ): self {
        return new self('rejected', $reason, $confidence, $analysisDetails, $moderatorId);
    }

    /**
     * Factory method para crear resultado pendiente de revisión
     *
     * @param string|null $reason Razón por la que requiere revisión
     * @param float $confidence Nivel de confianza
     * @param array $analysisDetails Detalles del análisis
     * @return self Nueva instancia de PhotoModerationResult
     */
    public static function pendingReview(
        ?string $reason = null,
        float $confidence = 0.5,
        array $analysisDetails = []
    ): self {
        return new self('pending_review', $reason, $confidence, $analysisDetails);
    }

    /**
     * Factory method para crear resultado de aprobación automática
     *
     * @param float $confidence Nivel de confianza
     * @param array $analysisDetails Detalles del análisis
     * @return self Nueva instancia de PhotoModerationResult
     */
    public static function autoApproved(
        float $confidence = 0.9,
        array $analysisDetails = []
    ): self {
        return new self('auto_approved', 'Aprobada automáticamente por IA', $confidence, $analysisDetails);
    }

    /**
     * Factory method para crear resultado de rechazo automático
     *
     * @param string $reason Razón del rechazo automático
     * @param float $confidence Nivel de confianza
     * @param array $analysisDetails Detalles del análisis
     * @return self Nueva instancia de PhotoModerationResult
     */
    public static function autoRejected(
        string $reason,
        float $confidence = 0.8,
        array $analysisDetails = []
    ): self {
        return new self('auto_rejected', $reason, $confidence, $analysisDetails);
    }

    /**
     * Factory method para crear resultado que requiere revisión humana
     *
     * @param string $reason Razón por la que requiere revisión humana
     * @param float $confidence Nivel de confianza
     * @param array $analysisDetails Detalles del análisis
     * @return self Nueva instancia de PhotoModerationResult
     */
    public static function needsHumanReview(
        string $reason,
        float $confidence = 0.6,
        array $analysisDetails = []
    ): self {
        return new self('needs_human_review', $reason, $confidence, $analysisDetails);
    }

    /**
     * Factory method para crear desde string
     *
     * @param string $value Valor del resultado
     * @param array $options Opciones adicionales
     * @return self Nueva instancia de PhotoModerationResult
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value, array $options = []): self
    {
        self::validateValue($value);
        
        return new self(
            $value,
            $options['reason'] ?? null,
            $options['confidence'] ?? 1.0,
            $options['analysis_details'] ?? [],
            $options['moderator_id'] ?? null
        );
    }

    /**
     * Factory method para crear desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el resultado
     * @return self Nueva instancia de PhotoModerationResult
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['value'])) {
            throw new InvalidArgumentException('Clave "value" no encontrada en los datos proporcionados');
        }

        return new self(
            $data['value'],
            $data['reason'] ?? null,
            $data['confidence'] ?? 1.0,
            $data['analysis_details'] ?? [],
            $data['moderator_id'] ?? null
        );
    }

    /**
     * Factory method para crear desde un modelo UserImage
     *
     * @param \App\Models\User\UserImage $image Modelo UserImage
     * @return self Nueva instancia de PhotoModerationResult
     * @throws InvalidArgumentException Si el modelo no tiene datos válidos
     */
    public static function fromUserImage(\App\Models\User\UserImage $image): self
    {
        if (!$image->exists) {
            throw new InvalidArgumentException('El modelo UserImage debe existir');
        }

        $status = $image->status ?? 'pending_review';
        
        // Mapear estados del modelo a resultados de moderación
        $resultMapping = [
            'active' => 'approved',
            'rejected' => 'rejected',
            'pending_moderation' => 'pending_review',
            'hidden' => 'rejected',
        ];

        $resultValue = $resultMapping[$status] ?? 'pending_review';

        return new self(
            $resultValue,
            $image->rejection_reason,
            $image->moderation_score ?? 1.0,
            $image->moderation_results ?? [],
            $image->moderated_by_user_id
        );
    }

    /**
     * Obtiene el valor string del resultado
     *
     * @return string Valor del resultado
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Obtiene la descripción legible del resultado
     *
     * @return string Descripción del resultado
     */
    public function getDescription(): string
    {
        return self::VALID_RESULTS[$this->value] ?? 'Resultado desconocido';
    }

    /**
     * Obtiene la razón del resultado
     *
     * @return string|null Razón del resultado
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * Obtiene el nivel de confianza
     *
     * @return float Nivel de confianza (0.0 - 1.0)
     */
    public function getConfidence(): float
    {
        return $this->confidence;
    }

    /**
     * Obtiene los detalles del análisis
     *
     * @return array Detalles del análisis
     */
    public function getAnalysisDetails(): array
    {
        return $this->analysisDetails;
    }

    /**
     * Obtiene el timestamp del resultado
     *
     * @return \DateTimeImmutable Timestamp del resultado
     */
    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * Obtiene el ID del moderador
     *
     * @return int|null ID del moderador
     */
    public function getModeratorId(): ?int
    {
        return $this->moderatorId;
    }

    /**
     * Verifica si este PhotoModerationResult es igual a otro
     *
     * @param self $other Otro PhotoModerationResult para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value &&
               $this->reason === $other->reason &&
               $this->confidence === $other->confidence;
    }

    /**
     * Verifica si el resultado es una aprobación
     *
     * @return bool True si es aprobación
     */
    public function isApproved(): bool
    {
        return in_array($this->value, self::APPROVED_RESULTS, true);
    }

    /**
     * Verifica si el resultado es un rechazo
     *
     * @return bool True si es rechazo
     */
    public function isRejected(): bool
    {
        return in_array($this->value, self::REJECTED_RESULTS, true);
    }

    /**
     * Verifica si el resultado requiere revisión manual
     *
     * @return bool True si requiere revisión manual
     */
    public function requiresManualReview(): bool
    {
        return in_array($this->value, self::MANUAL_REVIEW_REQUIRED, true);
    }

    /**
     * Verifica si el resultado es automático
     *
     * @return bool True si es automático
     */
    public function isAutomatic(): bool
    {
        return in_array($this->value, self::AUTOMATIC_RESULTS, true);
    }

    /**
     * Verifica si el resultado es manual
     *
     * @return bool True si es manual
     */
    public function isManual(): bool
    {
        return !$this->isAutomatic() && $this->moderatorId !== null;
    }

    /**
     * Verifica si el resultado es definitivo
     *
     * @return bool True si es definitivo
     */
    public function isDefinitive(): bool
    {
        return in_array($this->value, ['approved', 'rejected'], true);
    }

    /**
     * Verifica si el resultado es temporal
     *
     * @return bool True si es temporal
     */
    public function isTemporary(): bool
    {
        return !$this->isDefinitive();
    }

    /**
     * Verifica si la confianza es alta
     *
     * @return bool True si la confianza es >= 0.8
     */
    public function hasHighConfidence(): bool
    {
        return $this->confidence >= 0.8;
    }

    /**
     * Verifica si la confianza es baja
     *
     * @return bool True si la confianza es < 0.5
     */
    public function hasLowConfidence(): bool
    {
        return $this->confidence < 0.5;
    }

    /**
     * Obtiene el nivel de confianza como texto
     *
     * @return string Nivel de confianza como texto
     */
    public function getConfidenceLevel(): string
    {
        if ($this->confidence >= 0.9) return 'muy_alta';
        if ($this->confidence >= 0.8) return 'alta';
        if ($this->confidence >= 0.6) return 'media';
        if ($this->confidence >= 0.4) return 'baja';
        return 'muy_baja';
    }

    /**
     * Obtiene el color asociado al resultado (para UI)
     *
     * @return string Código de color
     */
    public function getColor(): string
    {
        $colors = [
            'approved' => '#10b981', // emerald
            'rejected' => '#ef4444', // red
            'pending_review' => '#f59e0b', // amber
            'auto_approved' => '#22c55e', // green
            'auto_rejected' => '#dc2626', // red-600
            'needs_human_review' => '#f97316', // orange
        ];

        return $colors[$this->value] ?? '#6b7280'; // gray
    }

    /**
     * Obtiene el icono asociado al resultado (para UI)
     *
     * @return string Nombre del icono
     */
    public function getIcon(): string
    {
        $icons = [
            'approved' => 'check-circle',
            'rejected' => 'x-circle',
            'pending_review' => 'clock',
            'auto_approved' => 'robot',
            'auto_rejected' => 'robot-dead',
            'needs_human_review' => 'user-check',
        ];

        return $icons[$this->value] ?? 'question-mark-circle';
    }

    /**
     * Convierte el PhotoModerationResult a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'description' => $this->getDescription(),
            'reason' => $this->reason,
            'confidence' => $this->confidence,
            'confidence_level' => $this->getConfidenceLevel(),
            'analysis_details' => $this->analysisDetails,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'moderator_id' => $this->moderatorId,
            'is_approved' => $this->isApproved(),
            'is_rejected' => $this->isRejected(),
            'requires_manual_review' => $this->requiresManualReview(),
            'is_automatic' => $this->isAutomatic(),
            'is_manual' => $this->isManual(),
            'is_definitive' => $this->isDefinitive(),
            'is_temporary' => $this->isTemporary(),
            'has_high_confidence' => $this->hasHighConfidence(),
            'has_low_confidence' => $this->hasLowConfidence(),
            'color' => $this->getColor(),
            'icon' => $this->getIcon(),
        ];
    }

    /**
     * Representación en string del PhotoModerationResult
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
            'reason' => $this->reason,
            'confidence' => $this->confidence,
            'is_approved' => $this->isApproved(),
            'is_rejected' => $this->isRejected(),
            'requires_manual_review' => $this->requiresManualReview(),
            'is_definitive' => $this->isDefinitive(),
        ];
    }

    /**
     * Valida que el valor del resultado sea válido
     *
     * @param string $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validateValue(string $value): void
    {
        if (!array_key_exists($value, self::VALID_RESULTS)) {
            $validResults = implode(', ', array_keys(self::VALID_RESULTS));
            throw new InvalidArgumentException(
                "PhotoModerationResult debe ser uno de: {$validResults}, se recibió: {$value}"
            );
        }
    }

    /**
     * Valida que el nivel de confianza sea válido
     *
     * @param float $confidence Nivel de confianza a validar
     * @throws InvalidArgumentException Si el nivel no es válido
     */
    private static function validateConfidence(float $confidence): void
    {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new InvalidArgumentException(
                "El nivel de confianza debe estar entre 0.0 y 1.0, se recibió: {$confidence}"
            );
        }
    }

    /**
     * Verifica si un valor es un PhotoModerationResult válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if (is_string($value)) {
                $temp = new self($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Obtiene todos los resultados válidos
     *
     * @return array Array con todos los resultados válidos
     */
    public static function getAllValidResults(): array
    {
        return array_keys(self::VALID_RESULTS);
    }

    /**
     * Obtiene todos los resultados con sus descripciones
     *
     * @return array Array con resultados y descripciones
     */
    public static function getAllResultsWithDescriptions(): array
    {
        return self::VALID_RESULTS;
    }

    /**
     * Obtiene resultados que requieren revisión manual
     *
     * @return array Array de resultados que requieren revisión manual
     */
    public static function getManualReviewRequiredResults(): array
    {
        return self::MANUAL_REVIEW_REQUIRED;
    }

    /**
     * Obtiene resultados de aprobación
     *
     * @return array Array de resultados de aprobación
     */
    public static function getApprovedResults(): array
    {
        return self::APPROVED_RESULTS;
    }

    /**
     * Obtiene resultados de rechazo
     *
     * @return array Array de resultados de rechazo
     */
    public static function getRejectedResults(): array
    {
        return self::REJECTED_RESULTS;
    }

    /**
     * Obtiene resultados automáticos
     *
     * @return array Array de resultados automáticos
     */
    public static function getAutomaticResults(): array
    {
        return self::AUTOMATIC_RESULTS;
    }

    /**
     * Verifica si este PhotoModerationResult corresponde a un resultado con características específicas
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['is_approved']) && $criteria['is_approved']) {
            if (!$this->isApproved()) {
                return false;
            }
        }

        if (isset($criteria['is_rejected']) && $criteria['is_rejected']) {
            if (!$this->isRejected()) {
                return false;
            }
        }

        if (isset($criteria['requires_manual_review']) && $criteria['requires_manual_review']) {
            if (!$this->requiresManualReview()) {
                return false;
            }
        }

        if (isset($criteria['is_automatic']) && $criteria['is_automatic']) {
            if (!$this->isAutomatic()) {
                return false;
            }
        }

        if (isset($criteria['is_definitive']) && $criteria['is_definitive']) {
            if (!$this->isDefinitive()) {
                return false;
            }
        }

        if (isset($criteria['high_confidence']) && $criteria['high_confidence']) {
            if (!$this->hasHighConfidence()) {
                return false;
            }
        }

        if (isset($criteria['results'])) {
            if (!in_array($this->value, $criteria['results'], true)) {
                return false;
            }
        }

        if (isset($criteria['confidence_min'])) {
            if ($this->confidence < $criteria['confidence_min']) {
                return false;
            }
        }

        if (isset($criteria['confidence_max'])) {
            if ($this->confidence > $criteria['confidence_max']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del PhotoModerationResult
     *
     * @return array Estadísticas del resultado
     */
    public function getStats(): array
    {
        return [
            'value' => $this->value,
            'description' => $this->getDescription(),
            'reason' => $this->reason,
            'confidence' => $this->confidence,
            'confidence_level' => $this->getConfidenceLevel(),
            'is_approved' => $this->isApproved(),
            'is_rejected' => $this->isRejected(),
            'requires_manual_review' => $this->requiresManualReview(),
            'is_automatic' => $this->isAutomatic(),
            'is_manual' => $this->isManual(),
            'is_definitive' => $this->isDefinitive(),
            'is_temporary' => $this->isTemporary(),
            'has_high_confidence' => $this->hasHighConfidence(),
            'has_low_confidence' => $this->hasLowConfidence(),
            'analysis_details_count' => count($this->analysisDetails),
            'moderator_id' => $this->moderatorId,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'color' => $this->getColor(),
            'icon' => $this->getIcon(),
        ];
    }

    /**
     * Convierte el resultado a formato compatible con el modelo UserImage
     *
     * @return array Datos compatibles con UserImage
     */
    public function toUserImageFormat(): array
    {
        $status = 'pending_moderation';
        
        // Mapear resultado a estado del modelo
        $statusMapping = [
            'approved' => 'active',
            'rejected' => 'rejected',
            'pending_review' => 'pending_moderation',
            'auto_approved' => 'active',
            'auto_rejected' => 'rejected',
            'needs_human_review' => 'pending_moderation',
        ];

        $status = $statusMapping[$this->value] ?? 'pending_moderation';

        return [
            'status' => $status,
            'rejection_reason' => $this->isRejected() ? $this->reason : null,
            'moderation_score' => $this->confidence,
            'moderation_results' => $this->analysisDetails,
            'moderated_at' => $this->timestamp->format('Y-m-d H:i:s'),
            'moderated_by_user_id' => $this->moderatorId,
        ];
    }

    /**
     * Convierte el resultado a formato compatible con PhotoStatus
     *
     * @return PhotoStatus Estado de foto compatible
     */
    public function toPhotoStatus(): PhotoStatus
    {
        $statusMapping = [
            'approved' => 'active',
            'rejected' => 'rejected',
            'pending_review' => 'pending_moderation',
            'auto_approved' => 'active',
            'auto_rejected' => 'rejected',
            'needs_human_review' => 'pending_moderation',
        ];

        $statusValue = $statusMapping[$this->value] ?? 'pending_moderation';
        
        return PhotoStatus::fromString($statusValue);
    }
}
