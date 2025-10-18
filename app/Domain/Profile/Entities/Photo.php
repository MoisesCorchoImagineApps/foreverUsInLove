<?php

declare(strict_types=1);

namespace App\Domain\Profile\Entities;

use App\Domain\Profile\ValueObjects\PhotoId;
use App\Domain\Profile\ValueObjects\ProfileId;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Profile\ValueObjects\PhotoStatus;
use App\Domain\Profile\ValueObjects\PhotoMetadata;
use App\Domain\Profile\ValueObjects\PhotoModerationResult;
use App\Domain\Profile\ValueObjects\PhotoAnalytics;
use App\Domain\Profile\ValueObjects\PhotoProcessingStatus;
use App\Domain\Profile\ValueObjects\PhotoEngagementMetrics;
use App\Domain\Profile\ValueObjects\PhotoReportResult;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Photo Entity
 * 
 * Representa una foto de perfil en el sistema ForeverUsInLove siguiendo
 * los principios de Domain-Driven Design. Esta entidad encapsula toda la
 * lógica de negocio relacionada con las fotos de perfil de usuario.
 * 
 * Características principales:
 * - Entidad de dominio inmutable con métodos de negocio específicos
 * - Compatible con PhotoRepositoryInterface para persistencia
 * - Integración completa con ValueObjects del dominio Profile
 * - Soporte para moderación, analytics y engagement
 * - Compatibilidad con modelos UserImage existentes
 * - Migración desde proyecto antiguo (UserImages)
 * 
 * @package ForeverUsInLove\Domain\Profile\Entities
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove
 * @license Proprietary
 * @version 1.0.0
 * @since 2024-01-01
 */
final class Photo
{
    // ================================================================
    // PROPERTIES
    // ================================================================
    
    /**
     * @var PhotoId Identificador único de la foto
     */
    private PhotoId $id;
    
    /**
     * @var ProfileId Identificador del perfil al que pertenece
     */
    private ProfileId $profileId;
    
    /**
     * @var UserId Identificador del usuario propietario
     */
    private UserId $userId;
    
    /**
     * @var string URL original de la foto
     */
    private string $originalUrl;
    
    /**
     * @var string Ruta del archivo en storage
     */
    private string $filePath;
    
    /**
     * @var string Nombre del archivo original
     */
    private string $filename;
    
    /**
     * @var PhotoStatus Estado actual de la foto
     */
    private PhotoStatus $status;
    
    /**
     * @var PhotoMetadata Metadatos técnicos y de análisis
     */
    private PhotoMetadata $metadata;
    
    /**
     * @var PhotoModerationResult|null Resultado de moderación
     */
    private ?PhotoModerationResult $moderationResult;
    
    /**
     * @var PhotoAnalytics|null Analytics de engagement
     */
    private ?PhotoAnalytics $analytics;
    
    /**
     * @var PhotoProcessingStatus|null Estado del procesamiento
     */
    private ?PhotoProcessingStatus $processingStatus;
    
    /**
     * @var PhotoEngagementMetrics|null Métricas de engagement
     */
    private ?PhotoEngagementMetrics $engagementMetrics;
    
    /**
     * @var bool Indica si es la foto principal del perfil
     */
    private bool $isPrimary;
    
    /**
     * @var int Orden de visualización (0-based)
     */
    private int $displayOrder;
    
    /**
     * @var bool Indica si la foto es privada
     */
    private bool $isPrivate;
    
    /**
     * @var string|null Razón de rechazo por moderación
     */
    private ?string $rejectionReason;
    
    /**
     * @var UserId|null ID del moderador que procesó la foto
     */
    private ?UserId $moderatedBy;
    
    /**
     * @var CarbonInterface Fecha de creación
     */
    private CarbonInterface $createdAt;
    
    /**
     * @var CarbonInterface Fecha de última actualización
     */
    private CarbonInterface $updatedAt;
    
    /**
     * @var CarbonInterface|null Fecha de moderación
     */
    private ?CarbonInterface $moderatedAt;
    
    /**
     * @var array<string, string> URLs optimizadas por tamaño
     */
    private array $optimizedUrls;
    
    /**
     * @var array<string, mixed> Tags AI generados
     */
    private array $aiTags;
    
    /**
     * @var float Score de calidad de la foto (0-10)
     */
    private float $qualityScore;
    
    /**
     * @var int Número de reportes recibidos
     */
    private int $reportCount;
    
    /**
     * @var array<PhotoReportResult> Reportes recibidos
     */
    private array $reports;

    // ================================================================
    // CONSTRUCTOR
    // ================================================================
    
    /**
     * Constructor privado para forzar el uso de factory methods
     */
    private function __construct(
        PhotoId $id,
        ProfileId $profileId,
        UserId $userId,
        string $originalUrl,
        string $filePath,
        string $filename,
        PhotoStatus $status,
        PhotoMetadata $metadata
    ) {
        $this->id = $id;
        $this->profileId = $profileId;
        $this->userId = $userId;
        $this->originalUrl = $originalUrl;
        $this->filePath = $filePath;
        $this->filename = $filename;
        $this->status = $status;
        $this->metadata = $metadata;
        
        // Valores por defecto
        $this->moderationResult = null;
        $this->analytics = null;
        $this->processingStatus = null;
        $this->engagementMetrics = null;
        $this->isPrimary = false;
        $this->displayOrder = 0;
        $this->isPrivate = false;
        $this->rejectionReason = null;
        $this->moderatedBy = null;
        $this->moderatedAt = null;
        $this->optimizedUrls = [];
        $this->aiTags = [];
        $this->qualityScore = 0.0;
        $this->reportCount = 0;
        $this->reports = [];
        
        // Timestamps
        $now = now();
        $this->createdAt = $now->copy();
        $this->updatedAt = $now->copy();
    }

    // ================================================================
    // FACTORY METHODS
    // ================================================================
    
    /**
     * Crear nueva foto desde datos de upload
     */
    public static function createFromUpload(
        PhotoId $id,
        ProfileId $profileId,
        UserId $userId,
        string $originalUrl,
        string $filePath,
        string $filename,
        PhotoMetadata $metadata
    ): self {
        return new self(
            $id,
            $profileId,
            $userId,
            $originalUrl,
            $filePath,
            $filename,
            PhotoStatus::pendingModeration(),
            $metadata
        );
    }
    
    /**
     * Crear foto desde modelo UserImage existente
     */
    public static function fromUserImage(\App\Models\User\UserImage $userImage): self
    {
        $photoId = PhotoId::fromInt($userImage->id);
        $profileId = ProfileId::fromInt($userImage->user_id); // Asumiendo 1:1 user-profile
        $userId = UserId::fromInt($userImage->user_id);
        $status = PhotoStatus::fromString($userImage->status ?? 'pending_moderation');
        $metadata = PhotoMetadata::fromUserImage($userImage);
        
        $photo = new self(
            $photoId,
            $profileId,
            $userId,
            $userImage->url ?? '',
            $userImage->path ?? '',
            $userImage->filename ?? '',
            $status,
            $metadata
        );
        
        // Configurar propiedades específicas del UserImage
        $photo->isPrimary = $userImage->is_primary ?? false;
        $photo->displayOrder = $userImage->order ?? 0;
        $photo->rejectionReason = $userImage->rejection_reason;
        $photo->qualityScore = $userImage->moderation_score ?? 0.0;
        $photo->reportCount = 0; // No disponible en UserImage
        $photo->createdAt = $userImage->created_at ?? now();
        $photo->updatedAt = $userImage->updated_at ?? now();
        $photo->moderatedAt = $userImage->moderated_at;
        
        if ($userImage->moderated_by_user_id) {
            $photo->moderatedBy = UserId::fromInt($userImage->moderated_by_user_id);
        }
        
        return $photo;
    }
    
    /**
     * Crear foto desde datos del proyecto antiguo
     */
    public static function fromLegacyUserImages(\App\Models\UserImages $legacyImage): self
    {
        $photoId = PhotoId::fromInt($legacyImage->id);
        $profileId = ProfileId::fromInt($legacyImage->user_id);
        $userId = UserId::fromInt($legacyImage->user_id);
        $status = PhotoStatus::active(); // Asumir activas las fotos legacy
        $metadata = PhotoMetadata::withDefaults([
            'filename' => $legacyImage->filename ?? '',
            'file_size' => $legacyImage->size_bytes ?? 0,
        ]);
        
        $photo = new self(
            $photoId,
            $profileId,
            $userId,
            $legacyImage->url ?? '',
            $legacyImage->path ?? '',
            $legacyImage->filename ?? '',
            $status,
            $metadata
        );
        
        $photo->isPrimary = $legacyImage->is_primary ?? false;
        $photo->displayOrder = $legacyImage->order ?? 0;
        $photo->createdAt = $legacyImage->created_at ?? now();
        $photo->updatedAt = $legacyImage->updated_at ?? now();
        
        return $photo;
    }
    
    /**
     * Reconstruir foto desde datos de repositorio
     */
    public static function fromRepositoryData(array $data): self
    {
        $photoId = PhotoId::fromInt($data['id']);
        $profileId = ProfileId::fromInt($data['profile_id']);
        $userId = UserId::fromInt($data['user_id']);
        $status = PhotoStatus::fromString($data['status']);
        $metadata = PhotoMetadata::fromArray($data['metadata'] ?? []);
        
        $photo = new self(
            $photoId,
            $profileId,
            $userId,
            $data['original_url'],
            $data['file_path'],
            $data['filename'],
            $status,
            $metadata
        );
        
        // Restaurar propiedades adicionales
        $photo->isPrimary = $data['is_primary'] ?? false;
        $photo->displayOrder = $data['display_order'] ?? 0;
        $photo->isPrivate = $data['is_private'] ?? false;
        $photo->rejectionReason = $data['rejection_reason'] ?? null;
        $photo->qualityScore = $data['quality_score'] ?? 0.0;
        $photo->reportCount = $data['report_count'] ?? 0;
        $photo->optimizedUrls = $data['optimized_urls'] ?? [];
        $photo->aiTags = $data['ai_tags'] ?? [];
        $photo->createdAt = $data['created_at'] ?? now();
        $photo->updatedAt = $data['updated_at'] ?? now();
        $photo->moderatedAt = $data['moderated_at'] ?? null;
        
        if ($data['moderated_by']) {
            $photo->moderatedBy = UserId::fromInt($data['moderated_by']);
        }
        
        return $photo;
    }

    // ================================================================
    // GETTERS
    // ================================================================
    
    public function getId(): PhotoId
    {
        return $this->id;
    }
    
    public function getProfileId(): ProfileId
    {
        return $this->profileId;
    }
    
    public function getUserId(): UserId
    {
        return $this->userId;
    }
    
    public function getOriginalUrl(): string
    {
        return $this->originalUrl;
    }
    
    public function getFilePath(): string
    {
        return $this->filePath;
    }
    
    public function getFilename(): string
    {
        return $this->filename;
    }
    
    public function getStatus(): PhotoStatus
    {
        return $this->status;
    }
    
    public function getMetadata(): PhotoMetadata
    {
        return $this->metadata;
    }
    
    public function getModerationResult(): ?PhotoModerationResult
    {
        return $this->moderationResult;
    }
    
    public function getAnalytics(): ?PhotoAnalytics
    {
        return $this->analytics;
    }
    
    public function getProcessingStatus(): ?PhotoProcessingStatus
    {
        return $this->processingStatus;
    }
    
    public function getEngagementMetrics(): ?PhotoEngagementMetrics
    {
        return $this->engagementMetrics;
    }
    
    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }
    
    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }
    
    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }
    
    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }
    
    public function getModeratedBy(): ?UserId
    {
        return $this->moderatedBy;
    }
    
    public function getCreatedAt(): CarbonInterface
    {
        return $this->createdAt;
    }
    
    public function getUpdatedAt(): CarbonInterface
    {
        return $this->updatedAt;
    }
    
    public function getModeratedAt(): ?CarbonInterface
    {
        return $this->moderatedAt;
    }
    
    public function getOptimizedUrls(): array
    {
        return $this->optimizedUrls;
    }
    
    public function getAiTags(): array
    {
        return $this->aiTags;
    }
    
    public function getQualityScore(): float
    {
        return $this->qualityScore;
    }
    
    public function getReportCount(): int
    {
        return $this->reportCount;
    }
    
    public function getReports(): array
    {
        return $this->reports;
    }

    // ================================================================
    // BUSINESS LOGIC METHODS
    // ================================================================
    
    /**
     * Aprobar foto después de moderación
     */
    public function approve(PhotoModerationResult $moderationResult, ?UserId $moderatorId = null): void
    {
        if (!$this->status->canTransitionTo(PhotoStatus::active())) {
            throw new InvalidArgumentException('No se puede aprobar la foto en su estado actual');
        }
        
        $this->status = PhotoStatus::active();
        $this->moderationResult = $moderationResult;
        $this->moderatedBy = $moderatorId;
        $this->moderatedAt = now();
        $this->rejectionReason = null;
        $this->updatedAt = now();
    }
    
    /**
     * Rechazar foto después de moderación
     */
    public function reject(string $reason, PhotoModerationResult $moderationResult, ?UserId $moderatorId = null): void
    {
        if (!$this->status->canTransitionTo(PhotoStatus::rejected())) {
            throw new InvalidArgumentException('No se puede rechazar la foto en su estado actual');
        }
        
        $this->status = PhotoStatus::rejected();
        $this->moderationResult = $moderationResult;
        $this->moderatedBy = $moderatorId;
        $this->moderatedAt = now();
        $this->rejectionReason = $reason;
        $this->updatedAt = now();
    }
    
    /**
     * Ocultar foto
     */
    public function hide(): void
    {
        if (!$this->status->canTransitionTo(PhotoStatus::hidden())) {
            throw new InvalidArgumentException('No se puede ocultar la foto en su estado actual');
        }
        
        $this->status = PhotoStatus::hidden();
        $this->updatedAt = now();
    }
    
    /**
     * Hacer foto visible
     */
    public function show(): void
    {
        if (!$this->status->canTransitionTo(PhotoStatus::active())) {
            throw new InvalidArgumentException('No se puede mostrar la foto en su estado actual');
        }
        
        $this->status = PhotoStatus::active();
        $this->updatedAt = now();
    }
    
    /**
     * Marcar como foto principal
     */
    public function setAsPrimary(): void
    {
        $this->isPrimary = true;
        $this->updatedAt = now();
    }
    
    /**
     * Desmarcar como foto principal
     */
    public function unsetAsPrimary(): void
    {
        $this->isPrimary = false;
        $this->updatedAt = now();
    }
    
    /**
     * Actualizar orden de visualización
     */
    public function updateDisplayOrder(int $order): void
    {
        if ($order < 0) {
            throw new InvalidArgumentException('El orden debe ser mayor o igual a 0');
        }
        
        $this->displayOrder = $order;
        $this->updatedAt = now();
    }
    
    /**
     * Actualizar estado de procesamiento
     */
    public function updateProcessingStatus(PhotoProcessingStatus $status): void
    {
        $this->processingStatus = $status;
        $this->updatedAt = now();
    }
    
    /**
     * Actualizar metadatos
     */
    public function updateMetadata(PhotoMetadata $metadata): void
    {
        $this->metadata = $metadata;
        $this->updatedAt = now();
    }
    
    /**
     * Actualizar analytics
     */
    public function updateAnalytics(PhotoAnalytics $analytics): void
    {
        $this->analytics = $analytics;
        $this->updatedAt = now();
    }
    
    /**
     * Actualizar métricas de engagement
     */
    public function updateEngagementMetrics(PhotoEngagementMetrics $metrics): void
    {
        $this->engagementMetrics = $metrics;
        $this->updatedAt = now();
    }
    
    /**
     * Actualizar score de calidad
     */
    public function updateQualityScore(float $score): void
    {
        if ($score < 0 || $score > 10) {
            throw new InvalidArgumentException('El score de calidad debe estar entre 0 y 10');
        }
        
        $this->qualityScore = $score;
        $this->updatedAt = now();
    }
    
    /**
     * Agregar tags AI
     */
    public function addAiTags(array $tags): void
    {
        $this->aiTags = array_merge($this->aiTags, $tags);
        $this->updatedAt = now();
    }
    
    /**
     * Actualizar URLs optimizadas
     */
    public function updateOptimizedUrls(array $urls): void
    {
        $this->optimizedUrls = $urls;
        $this->updatedAt = now();
    }
    
    /**
     * Obtener URL optimizada por tamaño
     */
    public function getOptimizedUrl(string $size = 'medium'): string
    {
        return $this->optimizedUrls[$size] ?? $this->originalUrl;
    }
    
    /**
     * Reportar foto
     */
    public function report(PhotoReportResult $report): void
    {
        $this->reports[] = $report;
        $this->reportCount++;
        $this->updatedAt = now();
    }
    
    /**
     * Marcar como privada
     */
    public function makePrivate(): void
    {
        $this->isPrivate = true;
        $this->updatedAt = now();
    }
    
    /**
     * Marcar como pública
     */
    public function makePublic(): void
    {
        $this->isPrivate = false;
        $this->updatedAt = now();
    }

    // ================================================================
    // QUERY METHODS
    // ================================================================
    
    /**
     * Verificar si la foto es visible públicamente
     */
    public function isPubliclyVisible(): bool
    {
        return $this->status->isPubliclyVisible() && !$this->isPrivate;
    }
    
    /**
     * Verificar si la foto puede ser editada
     */
    public function canBeEdited(): bool
    {
        return $this->status->canBeEdited();
    }
    
    /**
     * Verificar si la foto puede ser eliminada
     */
    public function canBeDeleted(): bool
    {
        return $this->status->canBeDeleted();
    }
    
    /**
     * Verificar si la foto requiere moderación
     */
    public function requiresModeration(): bool
    {
        return $this->status->requiresModeration();
    }
    
    /**
     * Verificar si la foto está en procesamiento
     */
    public function isProcessing(): bool
    {
        return $this->status->isProcessing();
    }
    
    /**
     * Verificar si la foto tiene alta calidad
     */
    public function hasHighQuality(): bool
    {
        return $this->qualityScore >= 7.0;
    }
    
    /**
     * Verificar si la foto tiene engagement
     */
    public function hasEngagement(): bool
    {
        return $this->engagementMetrics !== null && $this->engagementMetrics->hasHighEngagement();
    }
    
    /**
     * Verificar si la foto tiene reportes
     */
    public function hasReports(): bool
    {
        return $this->reportCount > 0;
    }
    
    /**
     * Verificar si la foto necesita atención de moderación
     */
    public function needsModerationAttention(): bool
    {
        return $this->requiresModeration() || $this->hasReports();
    }

    // ================================================================
    // CONVERSION METHODS
    // ================================================================
    
    /**
     * Convertir a array para repositorio
     */
    public function toRepositoryArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'profile_id' => $this->profileId->toInt(),
            'user_id' => $this->userId->toInt(),
            'original_url' => $this->originalUrl,
            'file_path' => $this->filePath,
            'filename' => $this->filename,
            'status' => $this->status->toString(),
            'metadata' => $this->metadata->toArray(),
            'moderation_result' => $this->moderationResult?->toArray(),
            'analytics' => $this->analytics?->toArray(),
            'processing_status' => $this->processingStatus?->toArray(),
            'engagement_metrics' => $this->engagementMetrics?->toArray(),
            'is_primary' => $this->isPrimary,
            'display_order' => $this->displayOrder,
            'is_private' => $this->isPrivate,
            'rejection_reason' => $this->rejectionReason,
            'moderated_by' => $this->moderatedBy?->toInt(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'moderated_at' => $this->moderatedAt,
            'optimized_urls' => $this->optimizedUrls,
            'ai_tags' => $this->aiTags,
            'quality_score' => $this->qualityScore,
            'report_count' => $this->reportCount,
            'reports' => array_map(fn($report) => $report->toArray(), $this->reports),
        ];
    }
    
    /**
     * Convertir a formato compatible con UserImage
     */
    public function toUserImageArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'user_id' => $this->userId->toInt(),
            'filename' => $this->filename,
            'path' => $this->filePath,
            'url' => $this->originalUrl,
            'is_primary' => $this->isPrimary,
            'order' => $this->displayOrder,
            'status' => $this->status->toString(),
            'rejection_reason' => $this->rejectionReason,
            'width' => $this->metadata->getWidth(),
            'height' => $this->metadata->getHeight(),
            'size_bytes' => $this->metadata->getFileSize(),
            'mime_type' => $this->metadata->getMimeType(),
            'metadata' => $this->metadata->toArray(),
            'moderation_results' => $this->moderationResult?->toArray(),
            'moderation_score' => $this->qualityScore,
            'moderated_at' => $this->moderatedAt,
            'moderated_by_user_id' => $this->moderatedBy?->toInt(),
            'views_count' => $this->engagementMetrics?->getTotalViews() ?? 0,
            'likes_count' => $this->engagementMetrics?->getTotalLikes() ?? 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
    
    /**
     * Convertir a array para API
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id->toInt(),
            'profile_id' => $this->profileId->toInt(),
            'user_id' => $this->userId->toInt(),
            'original_url' => $this->originalUrl,
            'filename' => $this->filename,
            'status' => $this->status->toString(),
            'is_primary' => $this->isPrimary,
            'display_order' => $this->displayOrder,
            'is_private' => $this->isPrivate,
            'quality_score' => $this->qualityScore,
            'report_count' => $this->reportCount,
            'optimized_urls' => $this->optimizedUrls,
            'ai_tags' => $this->aiTags,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
            'moderated_at' => $this->moderatedAt?->toISOString(),
            'metadata' => [
                'dimensions' => $this->metadata->getDimensions(),
                'file_size' => $this->metadata->getFileSize(),
                'formatted_file_size' => $this->metadata->getFormattedFileSize(),
                'mime_type' => $this->metadata->getMimeType(),
                'aspect_ratio' => $this->metadata->getAspectRatio(),
                'orientation' => $this->metadata->isLandscape() ? 'landscape' : 
                               ($this->metadata->isPortrait() ? 'portrait' : 'square'),
            ],
            'engagement' => $this->engagementMetrics?->toArray(),
            'analytics' => $this->analytics?->toArray(),
        ];
    }

    // ================================================================
    // MAGIC METHODS
    // ================================================================
    
    /**
     * Representación en string
     */
    public function __toString(): string
    {
        return "Photo({$this->id->toString()}, {$this->filename}, {$this->status->toString()})";
    }
    
    /**
     * Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'id' => $this->id->toInt(),
            'profile_id' => $this->profileId->toInt(),
            'user_id' => $this->userId->toInt(),
            'filename' => $this->filename,
            'status' => $this->status->toString(),
            'is_primary' => $this->isPrimary,
            'display_order' => $this->displayOrder,
            'quality_score' => $this->qualityScore,
            'report_count' => $this->reportCount,
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
