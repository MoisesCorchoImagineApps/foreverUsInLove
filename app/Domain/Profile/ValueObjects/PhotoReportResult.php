<?php

declare(strict_types=1);

namespace App\Domain\Profile\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * PhotoReportResult Value Object
 * 
 * Representa el resultado de un reporte de foto en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de los resultados de reportes de fotos en toda la aplicación.
 * 
 * Tipos de reporte disponibles:
 * - inappropriate_content: Contenido inapropiado o explícito
 * - spam: Contenido spam o promocional
 * - fake_profile: Foto falsa o que no corresponde al usuario
 * - harassment: Acoso o contenido ofensivo
 * - violence: Contenido violento o peligroso
 * - underage: Contenido de menores de edad
 * - copyright_violation: Violación de derechos de autor
 * - other: Otro motivo de reporte
 * 
 * Niveles de severidad:
 * - low: Severidad baja, revisión estándar
 * - medium: Severidad media, revisión prioritaria
 * - high: Severidad alta, revisión urgente
 * - critical: Severidad crítica, acción inmediata
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta tipos de reporte válidos predefinidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos PhotoReportResult con el mismo valor son iguales
 * - Integrado con el dominio Profile: Específico para reportes de fotos de perfil
 * - Compatible con sistema de moderación: Se integra con PhotoModerationResult
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
final class PhotoReportResult implements JsonSerializable
{
    /**
     * Tipos de reporte válidos para fotos
     */
    private const VALID_REPORT_TYPES = [
        'inappropriate_content' => 'Contenido inapropiado',
        'spam' => 'Spam o promocional',
        'fake_profile' => 'Perfil falso',
        'harassment' => 'Acoso u ofensivo',
        'violence' => 'Contenido violento',
        'underage' => 'Menor de edad',
        'copyright_violation' => 'Violación de derechos de autor',
        'other' => 'Otro motivo',
    ];

    /**
     * Niveles de severidad válidos
     */
    private const VALID_SEVERITY_LEVELS = [
        'low' => 'Baja',
        'medium' => 'Media',
        'high' => 'Alta',
        'critical' => 'Crítica',
    ];

    /**
     * Tipos que requieren acción inmediata
     */
    private const IMMEDIATE_ACTION_TYPES = [
        'violence',
        'underage',
        'harassment',
    ];

    /**
     * Tipos que requieren revisión humana obligatoria
     */
    private const HUMAN_REVIEW_REQUIRED_TYPES = [
        'inappropriate_content',
        'fake_profile',
        'harassment',
    ];

    /**
     * Tipos que pueden ser procesados automáticamente
     */
    private const AUTO_PROCESSABLE_TYPES = [
        'spam',
        'copyright_violation',
    ];

    /**
     * El tipo de reporte
     */
    private readonly string $reportType;

    /**
     * Nivel de severidad del reporte
     */
    private readonly string $severity;

    /**
     * Descripción adicional del reporte
     */
    private readonly ?string $description;

    /**
     * Detalles técnicos del análisis
     */
    private readonly array $analysisDetails;

    /**
     * Timestamp del reporte
     */
    private readonly \DateTimeImmutable $reportedAt;

    /**
     * ID del usuario que reporta
     */
    private readonly int $reporterId;

    /**
     * ID del usuario propietario de la foto
     */
    private readonly int $photoOwnerId;

    /**
     * ID de la foto reportada
     */
    private readonly int $photoId;

    /**
     * Constructor privado para forzar el uso de factory methods
     */
    private function __construct(
        string $reportType,
        string $severity,
        ?string $description = null,
        array $analysisDetails = [],
        int $reporterId = 0,
        int $photoOwnerId = 0,
        int $photoId = 0
    ) {
        self::validateReportType($reportType);
        self::validateSeverity($severity);
        
        $this->reportType = $reportType;
        $this->severity = $severity;
        $this->description = $description;
        $this->analysisDetails = $analysisDetails;
        $this->reportedAt = new \DateTimeImmutable();
        $this->reporterId = $reporterId;
        $this->photoOwnerId = $photoOwnerId;
        $this->photoId = $photoId;
    }

    /**
     * Factory method para crear reporte de contenido inapropiado
     *
     * @param string $description Descripción del contenido inapropiado
     * @param string $severity Nivel de severidad
     * @param array $analysisDetails Detalles del análisis
     * @param int $reporterId ID del usuario que reporta
     * @param int $photoOwnerId ID del propietario de la foto
     * @param int $photoId ID de la foto
     * @return self Nueva instancia de PhotoReportResult
     */
    public static function inappropriateContent(
        string $description,
        string $severity = 'medium',
        array $analysisDetails = [],
        int $reporterId = 0,
        int $photoOwnerId = 0,
        int $photoId = 0
    ): self {
        return new self(
            'inappropriate_content',
            $severity,
            $description,
            $analysisDetails,
            $reporterId,
            $photoOwnerId,
            $photoId
        );
    }

    /**
     * Factory method para crear reporte de spam
     *
     * @param string $description Descripción del spam
     * @param string $severity Nivel de severidad
     * @param array $analysisDetails Detalles del análisis
     * @param int $reporterId ID del usuario que reporta
     * @param int $photoOwnerId ID del propietario de la foto
     * @param int $photoId ID de la foto
     * @return self Nueva instancia de PhotoReportResult
     */
    public static function spam(
        string $description,
        string $severity = 'low',
        array $analysisDetails = [],
        int $reporterId = 0,
        int $photoOwnerId = 0,
        int $photoId = 0
    ): self {
        return new self(
            'spam',
            $severity,
            $description,
            $analysisDetails,
            $reporterId,
            $photoOwnerId,
            $photoId
        );
    }

    /**
     * Factory method para crear reporte de perfil falso
     *
     * @param string $description Descripción del perfil falso
     * @param string $severity Nivel de severidad
     * @param array $analysisDetails Detalles del análisis
     * @param int $reporterId ID del usuario que reporta
     * @param int $photoOwnerId ID del propietario de la foto
     * @param int $photoId ID de la foto
     * @return self Nueva instancia de PhotoReportResult
     */
    public static function fakeProfile(
        string $description,
        string $severity = 'high',
        array $analysisDetails = [],
        int $reporterId = 0,
        int $photoOwnerId = 0,
        int $photoId = 0
    ): self {
        return new self(
            'fake_profile',
            $severity,
            $description,
            $analysisDetails,
            $reporterId,
            $photoOwnerId,
            $photoId
        );
    }

    /**
     * Factory method para crear reporte de acoso
     *
     * @param string $description Descripción del acoso
     * @param string $severity Nivel de severidad
     * @param array $analysisDetails Detalles del análisis
     * @param int $reporterId ID del usuario que reporta
     * @param int $photoOwnerId ID del propietario de la foto
     * @param int $photoId ID de la foto
     * @return self Nueva instancia de PhotoReportResult
     */
    public static function harassment(
        string $description,
        string $severity = 'high',
        array $analysisDetails = [],
        int $reporterId = 0,
        int $photoOwnerId = 0,
        int $photoId = 0
    ): self {
        return new self(
            'harassment',
            $severity,
            $description,
            $analysisDetails,
            $reporterId,
            $photoOwnerId,
            $photoId
        );
    }

    /**
     * Factory method para crear reporte de violencia
     *
     * @param string $description Descripción del contenido violento
     * @param string $severity Nivel de severidad
     * @param array $analysisDetails Detalles del análisis
     * @param int $reporterId ID del usuario que reporta
     * @param int $photoOwnerId ID del propietario de la foto
     * @param int $photoId ID de la foto
     * @return self Nueva instancia de PhotoReportResult
     */
    public static function violence(
        string $description,
        string $severity = 'critical',
        array $analysisDetails = [],
        int $reporterId = 0,
        int $photoOwnerId = 0,
        int $photoId = 0
    ): self {
        return new self(
            'violence',
            $severity,
            $description,
            $analysisDetails,
            $reporterId,
            $photoOwnerId,
            $photoId
        );
    }

    /**
     * Factory method para crear reporte de menor de edad
     *
     * @param string $description Descripción del contenido de menor
     * @param string $severity Nivel de severidad
     * @param array $analysisDetails Detalles del análisis
     * @param int $reporterId ID del usuario que reporta
     * @param int $photoOwnerId ID del propietario de la foto
     * @param int $photoId ID de la foto
     * @return self Nueva instancia de PhotoReportResult
     */
    public static function underage(
        string $description,
        string $severity = 'critical',
        array $analysisDetails = [],
        int $reporterId = 0,
        int $photoOwnerId = 0,
        int $photoId = 0
    ): self {
        return new self(
            'underage',
            $severity,
            $description,
            $analysisDetails,
            $reporterId,
            $photoOwnerId,
            $photoId
        );
    }

    /**
     * Factory method para crear reporte de violación de derechos de autor
     *
     * @param string $description Descripción de la violación
     * @param string $severity Nivel de severidad
     * @param array $analysisDetails Detalles del análisis
     * @param int $reporterId ID del usuario que reporta
     * @param int $photoOwnerId ID del propietario de la foto
     * @param int $photoId ID de la foto
     * @return self Nueva instancia de PhotoReportResult
     */
    public static function copyrightViolation(
        string $description,
        string $severity = 'medium',
        array $analysisDetails = [],
        int $reporterId = 0,
        int $photoOwnerId = 0,
        int $photoId = 0
    ): self {
        return new self(
            'copyright_violation',
            $severity,
            $description,
            $analysisDetails,
            $reporterId,
            $photoOwnerId,
            $photoId
        );
    }

    /**
     * Factory method para crear reporte de otro tipo
     *
     * @param string $description Descripción del reporte
     * @param string $severity Nivel de severidad
     * @param array $analysisDetails Detalles del análisis
     * @param int $reporterId ID del usuario que reporta
     * @param int $photoOwnerId ID del propietario de la foto
     * @param int $photoId ID de la foto
     * @return self Nueva instancia de PhotoReportResult
     */
    public static function other(
        string $description,
        string $severity = 'low',
        array $analysisDetails = [],
        int $reporterId = 0,
        int $photoOwnerId = 0,
        int $photoId = 0
    ): self {
        return new self(
            'other',
            $severity,
            $description,
            $analysisDetails,
            $reporterId,
            $photoOwnerId,
            $photoId
        );
    }

    /**
     * Factory method para crear desde string
     *
     * @param string $reportType Tipo de reporte
     * @param array $options Opciones adicionales
     * @return self Nueva instancia de PhotoReportResult
     * @throws InvalidArgumentException Si el tipo no es válido
     */
    public static function fromString(string $reportType, array $options = []): self
    {
        self::validateReportType($reportType);
        
        return new self(
            $reportType,
            $options['severity'] ?? 'low',
            $options['description'] ?? null,
            $options['analysis_details'] ?? [],
            $options['reporter_id'] ?? 0,
            $options['photo_owner_id'] ?? 0,
            $options['photo_id'] ?? 0
        );
    }

    /**
     * Factory method para crear desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el reporte
     * @return self Nueva instancia de PhotoReportResult
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['report_type'])) {
            throw new InvalidArgumentException('Clave "report_type" no encontrada en los datos proporcionados');
        }

        return new self(
            $data['report_type'],
            $data['severity'] ?? 'low',
            $data['description'] ?? null,
            $data['analysis_details'] ?? [],
            $data['reporter_id'] ?? 0,
            $data['photo_owner_id'] ?? 0,
            $data['photo_id'] ?? 0
        );
    }

    /**
     * Factory method para crear desde un modelo UsersReport del proyecto antiguo
     *
     * @param \App\Models\UsersReport $report Modelo UsersReport
     * @return self Nueva instancia de PhotoReportResult
     * @throws InvalidArgumentException Si el modelo no tiene datos válidos
     */
    public static function fromUsersReport(\App\Models\UsersReport $report): self
    {
        if (!$report->exists) {
            throw new InvalidArgumentException('El modelo UsersReport debe existir');
        }

        $reportType = $report->report_reason ?? 'other';
        $severity = self::mapReportReasonToSeverity($reportType);

        return new self(
            $reportType,
            $severity,
            $report->message,
            [],
            $report->reporter_id ?? 0,
            $report->user_id ?? 0,
            0 // El modelo antiguo no tiene photo_id específico
        );
    }

    /**
     * Obtiene el tipo de reporte
     *
     * @return string Tipo de reporte
     */
    public function getReportType(): string
    {
        return $this->reportType;
    }

    /**
     * Obtiene la descripción legible del tipo de reporte
     *
     * @return string Descripción del tipo de reporte
     */
    public function getReportTypeDescription(): string
    {
        return self::VALID_REPORT_TYPES[$this->reportType] ?? 'Tipo de reporte desconocido';
    }

    /**
     * Obtiene el nivel de severidad
     *
     * @return string Nivel de severidad
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * Obtiene la descripción legible del nivel de severidad
     *
     * @return string Descripción del nivel de severidad
     */
    public function getSeverityDescription(): string
    {
        return self::VALID_SEVERITY_LEVELS[$this->severity] ?? 'Nivel de severidad desconocido';
    }

    /**
     * Obtiene la descripción del reporte
     *
     * @return string|null Descripción del reporte
     */
    public function getDescription(): ?string
    {
        return $this->description;
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
     * Obtiene el timestamp del reporte
     *
     * @return \DateTimeImmutable Timestamp del reporte
     */
    public function getReportedAt(): \DateTimeImmutable
    {
        return $this->reportedAt;
    }

    /**
     * Obtiene el ID del usuario que reporta
     *
     * @return int ID del usuario que reporta
     */
    public function getReporterId(): int
    {
        return $this->reporterId;
    }

    /**
     * Obtiene el ID del propietario de la foto
     *
     * @return int ID del propietario de la foto
     */
    public function getPhotoOwnerId(): int
    {
        return $this->photoOwnerId;
    }

    /**
     * Obtiene el ID de la foto reportada
     *
     * @return int ID de la foto reportada
     */
    public function getPhotoId(): int
    {
        return $this->photoId;
    }

    /**
     * Verifica si este PhotoReportResult es igual a otro
     *
     * @param self $other Otro PhotoReportResult para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->reportType === $other->reportType &&
               $this->severity === $other->severity &&
               $this->description === $other->description;
    }

    /**
     * Verifica si el reporte requiere acción inmediata
     *
     * @return bool True si requiere acción inmediata
     */
    public function requiresImmediateAction(): bool
    {
        return in_array($this->reportType, self::IMMEDIATE_ACTION_TYPES, true) ||
               $this->severity === 'critical';
    }

    /**
     * Verifica si el reporte requiere revisión humana
     *
     * @return bool True si requiere revisión humana
     */
    public function requiresHumanReview(): bool
    {
        return in_array($this->reportType, self::HUMAN_REVIEW_REQUIRED_TYPES, true) ||
               $this->severity === 'high' ||
               $this->severity === 'critical';
    }

    /**
     * Verifica si el reporte puede ser procesado automáticamente
     *
     * @return bool True si puede ser procesado automáticamente
     */
    public function isAutoProcessable(): bool
    {
        return in_array($this->reportType, self::AUTO_PROCESSABLE_TYPES, true) &&
               $this->severity === 'low';
    }

    /**
     * Verifica si el reporte es de alta prioridad
     *
     * @return bool True si es de alta prioridad
     */
    public function isHighPriority(): bool
    {
        return $this->severity === 'high' || $this->severity === 'critical';
    }

    /**
     * Verifica si el reporte es de baja prioridad
     *
     * @return bool True si es de baja prioridad
     */
    public function isLowPriority(): bool
    {
        return $this->severity === 'low';
    }

    /**
     * Obtiene el color asociado al tipo de reporte (para UI)
     *
     * @return string Código de color
     */
    public function getColor(): string
    {
        $colors = [
            'inappropriate_content' => '#f59e0b', // amber
            'spam' => '#6b7280', // gray
            'fake_profile' => '#ef4444', // red
            'harassment' => '#dc2626', // red-600
            'violence' => '#991b1b', // red-800
            'underage' => '#7c2d12', // orange-900
            'copyright_violation' => '#1d4ed8', // blue-700
            'other' => '#6b7280', // gray
        ];

        return $colors[$this->reportType] ?? '#6b7280';
    }

    /**
     * Obtiene el icono asociado al tipo de reporte (para UI)
     *
     * @return string Nombre del icono
     */
    public function getIcon(): string
    {
        $icons = [
            'inappropriate_content' => 'exclamation-triangle',
            'spam' => 'spam',
            'fake_profile' => 'user-x',
            'harassment' => 'shield-exclamation',
            'violence' => 'ban',
            'underage' => 'child',
            'copyright_violation' => 'copyright',
            'other' => 'flag',
        ];

        return $icons[$this->reportType] ?? 'question-mark-circle';
    }

    /**
     * Convierte el PhotoReportResult a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'report_type' => $this->reportType,
            'report_type_description' => $this->getReportTypeDescription(),
            'severity' => $this->severity,
            'severity_description' => $this->getSeverityDescription(),
            'description' => $this->description,
            'analysis_details' => $this->analysisDetails,
            'reported_at' => $this->reportedAt->format('Y-m-d H:i:s'),
            'reporter_id' => $this->reporterId,
            'photo_owner_id' => $this->photoOwnerId,
            'photo_id' => $this->photoId,
            'requires_immediate_action' => $this->requiresImmediateAction(),
            'requires_human_review' => $this->requiresHumanReview(),
            'is_auto_processable' => $this->isAutoProcessable(),
            'is_high_priority' => $this->isHighPriority(),
            'is_low_priority' => $this->isLowPriority(),
            'color' => $this->getColor(),
            'icon' => $this->getIcon(),
        ];
    }

    /**
     * Representación en string del PhotoReportResult
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return $this->getReportType();
    }

    /**
     * Serialización para JSON
     *
     * @return string Valor para JSON
     */
    public function jsonSerialize(): string
    {
        return $this->reportType;
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'report_type' => $this->reportType,
            'report_type_description' => $this->getReportTypeDescription(),
            'severity' => $this->severity,
            'severity_description' => $this->getSeverityDescription(),
            'description' => $this->description,
            'requires_immediate_action' => $this->requiresImmediateAction(),
            'requires_human_review' => $this->requiresHumanReview(),
            'is_high_priority' => $this->isHighPriority(),
        ];
    }

    /**
     * Convierte el reporte a formato compatible con PhotoModerationResult
     *
     * @return PhotoModerationResult Resultado de moderación compatible
     */
    public function toPhotoModerationResult(): PhotoModerationResult
    {
        $resultMapping = [
            'inappropriate_content' => 'rejected',
            'spam' => 'rejected',
            'fake_profile' => 'rejected',
            'harassment' => 'rejected',
            'violence' => 'rejected',
            'underage' => 'rejected',
            'copyright_violation' => 'rejected',
            'other' => 'pending_review',
        ];

        $resultValue = $resultMapping[$this->reportType] ?? 'pending_review';

        return PhotoModerationResult::fromString($resultValue, [
            'reason' => $this->description,
            'confidence' => $this->isHighPriority() ? 0.9 : 0.7,
            'analysis_details' => $this->analysisDetails,
        ]);
    }

    /**
     * Convierte el reporte a formato compatible con el modelo UserImage
     *
     * @return array Datos compatibles con UserImage
     */
    public function toUserImageFormat(): array
    {
        $statusMapping = [
            'inappropriate_content' => 'rejected',
            'spam' => 'rejected',
            'fake_profile' => 'rejected',
            'harassment' => 'rejected',
            'violence' => 'rejected',
            'underage' => 'rejected',
            'copyright_violation' => 'rejected',
            'other' => 'pending_moderation',
        ];

        $status = $statusMapping[$this->reportType] ?? 'pending_moderation';

        return [
            'status' => $status,
            'rejection_reason' => $this->description,
            'moderation_score' => $this->isHighPriority() ? 0.2 : 0.5,
            'moderation_results' => array_merge($this->analysisDetails, [
                'report_type' => $this->reportType,
                'severity' => $this->severity,
                'reported_at' => $this->reportedAt->format('Y-m-d H:i:s'),
                'reporter_id' => $this->reporterId,
            ]),
            'moderated_at' => $this->reportedAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convierte el reporte a formato compatible con el modelo UsersReport del proyecto antiguo
     *
     * @return array Datos compatibles con UsersReport
     */
    public function toUsersReportFormat(): array
    {
        return [
            'user_id' => $this->photoOwnerId,
            'reporter_id' => $this->reporterId,
            'report_reason' => $this->reportType,
            'message' => $this->description,
            'type' => 'photo',
        ];
    }

    /**
     * Valida que el tipo de reporte sea válido
     *
     * @param string $reportType Tipo de reporte a validar
     * @throws InvalidArgumentException Si el tipo no es válido
     */
    private static function validateReportType(string $reportType): void
    {
        if (!array_key_exists($reportType, self::VALID_REPORT_TYPES)) {
            $validTypes = implode(', ', array_keys(self::VALID_REPORT_TYPES));
            throw new InvalidArgumentException(
                "PhotoReportResult debe ser uno de: {$validTypes}, se recibió: {$reportType}"
            );
        }
    }

    /**
     * Valida que el nivel de severidad sea válido
     *
     * @param string $severity Nivel de severidad a validar
     * @throws InvalidArgumentException Si el nivel no es válido
     */
    private static function validateSeverity(string $severity): void
    {
        if (!array_key_exists($severity, self::VALID_SEVERITY_LEVELS)) {
            $validSeverities = implode(', ', array_keys(self::VALID_SEVERITY_LEVELS));
            throw new InvalidArgumentException(
                "El nivel de severidad debe ser uno de: {$validSeverities}, se recibió: {$severity}"
            );
        }
    }

    /**
     * Mapea el tipo de reporte del proyecto antiguo a nivel de severidad
     *
     * @param string $reportReason Razón del reporte del modelo antiguo
     * @return string Nivel de severidad mapeado
     */
    private static function mapReportReasonToSeverity(string $reportReason): string
    {
        $severityMapping = [
            'inappropriate_content' => 'medium',
            'spam' => 'low',
            'fake_profile' => 'high',
            'harassment' => 'high',
            'violence' => 'critical',
            'underage' => 'critical',
            'copyright_violation' => 'medium',
            'other' => 'low',
        ];

        return $severityMapping[$reportReason] ?? 'low';
    }

    /**
     * Verifica si un valor es un PhotoReportResult válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if (is_string($value)) {
                self::validateReportType($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Obtiene todos los tipos de reporte válidos
     *
     * @return array Array con todos los tipos de reporte válidos
     */
    public static function getAllValidReportTypes(): array
    {
        return array_keys(self::VALID_REPORT_TYPES);
    }

    /**
     * Obtiene todos los tipos de reporte con sus descripciones
     *
     * @return array Array con tipos de reporte y descripciones
     */
    public static function getAllReportTypesWithDescriptions(): array
    {
        return self::VALID_REPORT_TYPES;
    }

    /**
     * Obtiene todos los niveles de severidad válidos
     *
     * @return array Array con todos los niveles de severidad válidos
     */
    public static function getAllValidSeverityLevels(): array
    {
        return array_keys(self::VALID_SEVERITY_LEVELS);
    }

    /**
     * Obtiene todos los niveles de severidad con sus descripciones
     *
     * @return array Array con niveles de severidad y descripciones
     */
    public static function getAllSeverityLevelsWithDescriptions(): array
    {
        return self::VALID_SEVERITY_LEVELS;
    }

    /**
     * Obtiene tipos que requieren acción inmediata
     *
     * @return array Array de tipos que requieren acción inmediata
     */
    public static function getImmediateActionTypes(): array
    {
        return self::IMMEDIATE_ACTION_TYPES;
    }

    /**
     * Obtiene tipos que requieren revisión humana
     *
     * @return array Array de tipos que requieren revisión humana
     */
    public static function getHumanReviewRequiredTypes(): array
    {
        return self::HUMAN_REVIEW_REQUIRED_TYPES;
    }

    /**
     * Obtiene tipos que pueden ser procesados automáticamente
     *
     * @return array Array de tipos que pueden ser procesados automáticamente
     */
    public static function getAutoProcessableTypes(): array
    {
        return self::AUTO_PROCESSABLE_TYPES;
    }

    /**
     * Verifica si este PhotoReportResult corresponde a un reporte con características específicas
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['requires_immediate_action']) && $criteria['requires_immediate_action']) {
            if (!$this->requiresImmediateAction()) {
                return false;
            }
        }

        if (isset($criteria['requires_human_review']) && $criteria['requires_human_review']) {
            if (!$this->requiresHumanReview()) {
                return false;
            }
        }

        if (isset($criteria['is_auto_processable']) && $criteria['is_auto_processable']) {
            if (!$this->isAutoProcessable()) {
                return false;
            }
        }

        if (isset($criteria['is_high_priority']) && $criteria['is_high_priority']) {
            if (!$this->isHighPriority()) {
                return false;
            }
        }

        if (isset($criteria['report_types'])) {
            if (!in_array($this->reportType, $criteria['report_types'], true)) {
                return false;
            }
        }

        if (isset($criteria['severities'])) {
            if (!in_array($this->severity, $criteria['severities'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del PhotoReportResult
     *
     * @return array Estadísticas del reporte
     */
    public function getStats(): array
    {
        return [
            'report_type' => $this->reportType,
            'report_type_description' => $this->getReportTypeDescription(),
            'severity' => $this->severity,
            'severity_description' => $this->getSeverityDescription(),
            'description' => $this->description,
            'requires_immediate_action' => $this->requiresImmediateAction(),
            'requires_human_review' => $this->requiresHumanReview(),
            'is_auto_processable' => $this->isAutoProcessable(),
            'is_high_priority' => $this->isHighPriority(),
            'is_low_priority' => $this->isLowPriority(),
            'analysis_details_count' => count($this->analysisDetails),
            'reporter_id' => $this->reporterId,
            'photo_owner_id' => $this->photoOwnerId,
            'photo_id' => $this->photoId,
            'reported_at' => $this->reportedAt->format('Y-m-d H:i:s'),
            'color' => $this->getColor(),
            'icon' => $this->getIcon(),
        ];
    }
}
