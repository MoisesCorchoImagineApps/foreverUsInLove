<?php

declare(strict_types=1);

namespace App\Domain\Profile\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Carbon\CarbonInterface;

/**
 * PhotoMetadata Value Object
 * 
 * Represents comprehensive metadata for a photo within the ForeverUsInLove dating platform.
 * Encapsulates technical specifications, EXIF data, AI analysis results, and processing information
 * following Domain-Driven Design principles and immutable value object patterns.
 * 
 * Key Responsibilities:
 * - Encapsulate technical photo specifications (dimensions, format, size)
 * - Store EXIF data and camera information
 * - Manage AI analysis results and content detection
 * - Track processing status and optimization results
 * - Support content moderation and quality assessment
 * - Ensure data integrity and validation
 * - Provide compatibility with existing UserImage model
 * 
 * @package ForeverUsInLove\Domain\Profile\ValueObjects
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove
 * @license Proprietary
 * @version 1.0.0
 * @since 2024-01-01
 * 
 * @phpstan-consistent-constructor
 */
final class PhotoMetadata implements JsonSerializable
{
    // ================================================================
    // CONSTANTS
    // ================================================================
    
    /**
     * Maximum file size in bytes (10MB)
     */
    private const MAX_FILE_SIZE = 10485760;
    
    /**
     * Minimum image dimensions in pixels
     */
    private const MIN_DIMENSIONS = 400;
    
    /**
     * Maximum image dimensions in pixels
     */
    private const MAX_DIMENSIONS = 4000;
    
    /**
     * Supported image formats
     */
    private const SUPPORTED_FORMATS = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/webp'
    ];
    
    /**
     * Quality score thresholds
     */
    private const HIGH_QUALITY_THRESHOLD = 8.0;
    private const MEDIUM_QUALITY_THRESHOLD = 6.0;
    private const LOW_QUALITY_THRESHOLD = 4.0;

    // ================================================================
    // PROPERTIES
    // ================================================================
    
    /**
     * @var array<string, int> Image dimensions (width, height)
     */
    private array $dimensions;
    
    /**
     * @var int File size in bytes
     */
    private int $fileSize;
    
    /**
     * @var string MIME type of the image
     */
    private string $mimeType;
    
    /**
     * @var string Original filename
     */
    private string $filename;
    
    /**
     * @var float Aspect ratio (width/height)
     */
    private float $aspectRatio;
    
    /**
     * @var array<string, mixed> EXIF data from the image
     */
    private array $exifData;
    
    /**
     * @var array<string, mixed> Camera and device information
     */
    private array $cameraInfo;
    
    /**
     * @var array<string, mixed> Location data (GPS coordinates, etc.)
     */
    private array $locationData;
    
    /**
     * @var array<string, mixed> AI analysis results
     */
    private array $aiAnalysis;
    
    /**
     * @var array<string, mixed> Content detection results
     */
    private array $contentDetection;
    
    /**
     * @var array<string, mixed> Face detection results
     */
    private array $faceDetection;
    
    /**
     * @var array<string, mixed> Object detection results
     */
    private array $objectDetection;
    
    /**
     * @var array<string, mixed> Scene classification results
     */
    private array $sceneClassification;
    
    /**
     * @var array<string, mixed> Quality assessment results
     */
    private array $qualityAssessment;
    
    /**
     * @var array<string, mixed> Processing pipeline results
     */
    private array $processingResults;
    
    /**
     * @var array<string, mixed> Optimization results
     */
    private array $optimizationResults;
    
    /**
     * @var array<string, mixed> Thumbnail generation results
     */
    private array $thumbnailResults;
    
    /**
     * @var array<string, mixed> Color analysis results
     */
    private array $colorAnalysis;
    
    /**
     * @var array<string, mixed> Technical specifications
     */
    private array $technicalSpecs;
    
    /**
     * @var CarbonInterface When metadata was created
     */
    private CarbonInterface $createdAt;
    
    /**
     * @var CarbonInterface When metadata was last updated
     */
    private CarbonInterface $updatedAt;
    
    /**
     * @var string Version of metadata schema
     */
    private string $schemaVersion;

    // ================================================================
    // CONSTRUCTOR
    // ================================================================
    
    /**
     * Create a new PhotoMetadata instance
     * 
     * @param array<string, mixed> $metadataData Raw metadata data
     * 
     * @throws InvalidArgumentException If metadata data is invalid
     */
    public function __construct(array $metadataData)
    {
        $this->validateAndSetMetadataData($metadataData);
        $this->calculateDerivedProperties();
        $this->setTimestamps();
        $this->schemaVersion = '1.0.0';
    }

    // ================================================================
    // GETTER METHODS
    // ================================================================
    
    /**
     * Get image dimensions
     * 
     * @return array<string, int> Dimensions array with width and height
     */
    public function getDimensions(): array
    {
        return $this->dimensions;
    }
    
    /**
     * Get image width
     * 
     * @return int Image width in pixels
     */
    public function getWidth(): int
    {
        return $this->dimensions['width'] ?? 0;
    }
    
    /**
     * Get image height
     * 
     * @return int Image height in pixels
     */
    public function getHeight(): int
    {
        return $this->dimensions['height'] ?? 0;
    }
    
    /**
     * Get file size in bytes
     * 
     * @return int File size in bytes
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }
    
    /**
     * Get formatted file size
     * 
     * @return string Human-readable file size
     */
    public function getFormattedFileSize(): string
    {
        $bytes = $this->fileSize;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' bytes';
    }
    
    /**
     * Get MIME type
     * 
     * @return string MIME type
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }
    
    /**
     * Get original filename
     * 
     * @return string Original filename
     */
    public function getFilename(): string
    {
        return $this->filename;
    }
    
    /**
     * Get aspect ratio
     * 
     * @return float Aspect ratio (width/height)
     */
    public function getAspectRatio(): float
    {
        return $this->aspectRatio;
    }
    
    /**
     * Get EXIF data
     * 
     * @return array<string, mixed> EXIF data
     */
    public function getExifData(): array
    {
        return $this->exifData;
    }
    
    /**
     * Get camera information
     * 
     * @return array<string, mixed> Camera and device info
     */
    public function getCameraInfo(): array
    {
        return $this->cameraInfo;
    }
    
    /**
     * Get location data
     * 
     * @return array<string, mixed> GPS and location data
     */
    public function getLocationData(): array
    {
        return $this->locationData;
    }
    
    /**
     * Get AI analysis results
     * 
     * @return array<string, mixed> AI analysis data
     */
    public function getAiAnalysis(): array
    {
        return $this->aiAnalysis;
    }
    
    /**
     * Get content detection results
     * 
     * @return array<string, mixed> Content detection data
     */
    public function getContentDetection(): array
    {
        return $this->contentDetection;
    }
    
    /**
     * Get face detection results
     * 
     * @return array<string, mixed> Face detection data
     */
    public function getFaceDetection(): array
    {
        return $this->faceDetection;
    }
    
    /**
     * Get object detection results
     * 
     * @return array<string, mixed> Object detection data
     */
    public function getObjectDetection(): array
    {
        return $this->objectDetection;
    }
    
    /**
     * Get scene classification results
     * 
     * @return array<string, mixed> Scene classification data
     */
    public function getSceneClassification(): array
    {
        return $this->sceneClassification;
    }
    
    /**
     * Get quality assessment results
     * 
     * @return array<string, mixed> Quality assessment data
     */
    public function getQualityAssessment(): array
    {
        return $this->qualityAssessment;
    }
    
    /**
     * Get processing results
     * 
     * @return array<string, mixed> Processing pipeline results
     */
    public function getProcessingResults(): array
    {
        return $this->processingResults;
    }
    
    /**
     * Get optimization results
     * 
     * @return array<string, mixed> Optimization results
     */
    public function getOptimizationResults(): array
    {
        return $this->optimizationResults;
    }
    
    /**
     * Get thumbnail results
     * 
     * @return array<string, mixed> Thumbnail generation results
     */
    public function getThumbnailResults(): array
    {
        return $this->thumbnailResults;
    }
    
    /**
     * Get color analysis results
     * 
     * @return array<string, mixed> Color analysis data
     */
    public function getColorAnalysis(): array
    {
        return $this->colorAnalysis;
    }
    
    /**
     * Get technical specifications
     * 
     * @return array<string, mixed> Technical specs
     */
    public function getTechnicalSpecs(): array
    {
        return $this->technicalSpecs;
    }
    
    /**
     * Get creation timestamp
     * 
     * @return CarbonInterface Creation timestamp
     */
    public function getCreatedAt(): CarbonInterface
    {
        return $this->createdAt;
    }
    
    /**
     * Get last updated timestamp
     * 
     * @return CarbonInterface Last updated timestamp
     */
    public function getUpdatedAt(): CarbonInterface
    {
        return $this->updatedAt;
    }
    
    /**
     * Get schema version
     * 
     * @return string Schema version
     */
    public function getSchemaVersion(): string
    {
        return $this->schemaVersion;
    }

    // ================================================================
    // CALCULATED PROPERTIES
    // ================================================================
    
    /**
     * Check if image is landscape orientation
     * 
     * @return bool True if landscape
     */
    public function isLandscape(): bool
    {
        return $this->getWidth() > $this->getHeight();
    }
    
    /**
     * Check if image is portrait orientation
     * 
     * @return bool True if portrait
     */
    public function isPortrait(): bool
    {
        return $this->getHeight() > $this->getWidth();
    }
    
    /**
     * Check if image is square
     * 
     * @return bool True if square
     */
    public function isSquare(): bool
    {
        return $this->getWidth() === $this->getHeight();
    }
    
    /**
     * Check if image meets minimum size requirements
     * 
     * @return bool True if meets requirements
     */
    public function meetsMinimumSize(): bool
    {
        return $this->getWidth() >= self::MIN_DIMENSIONS && 
               $this->getHeight() >= self::MIN_DIMENSIONS;
    }
    
    /**
     * Check if image exceeds maximum size limits
     * 
     * @return bool True if exceeds limits
     */
    public function exceedsMaximumSize(): bool
    {
        return $this->getWidth() > self::MAX_DIMENSIONS || 
               $this->getHeight() > self::MAX_DIMENSIONS;
    }
    
    /**
     * Check if file size is acceptable
     * 
     * @return bool True if file size is acceptable
     */
    public function hasAcceptableFileSize(): bool
    {
        return $this->fileSize <= self::MAX_FILE_SIZE;
    }
    
    /**
     * Check if format is supported
     * 
     * @return bool True if format is supported
     */
    public function hasSupportedFormat(): bool
    {
        return in_array($this->mimeType, self::SUPPORTED_FORMATS, true);
    }
    
    /**
     * Get overall quality score
     * 
     * @return float Quality score (0-10)
     */
    public function getOverallQualityScore(): float
    {
        $qualityData = $this->qualityAssessment;
        return $qualityData['overall_score'] ?? 7.0;
    }
    
    /**
     * Check if image has high quality
     * 
     * @return bool True if high quality
     */
    public function hasHighQuality(): bool
    {
        return $this->getOverallQualityScore() >= self::HIGH_QUALITY_THRESHOLD;
    }
    
    /**
     * Check if image has medium quality
     * 
     * @return bool True if medium quality
     */
    public function hasMediumQuality(): bool
    {
        $score = $this->getOverallQualityScore();
        return $score >= self::MEDIUM_QUALITY_THRESHOLD && $score < self::HIGH_QUALITY_THRESHOLD;
    }
    
    /**
     * Check if image has low quality
     * 
     * @return bool True if low quality
     */
    public function hasLowQuality(): bool
    {
        return $this->getOverallQualityScore() < self::LOW_QUALITY_THRESHOLD;
    }
    
    /**
     * Get number of faces detected
     * 
     * @return int Number of faces
     */
    public function getFaceCount(): int
    {
        $faceData = $this->faceDetection;
        return $faceData['face_count'] ?? 0;
    }
    
    /**
     * Check if image contains faces
     * 
     * @return bool True if contains faces
     */
    public function hasFaces(): bool
    {
        return $this->getFaceCount() > 0;
    }
    
    /**
     * Get primary face information
     * 
     * @return array<string, mixed> Primary face data
     */
    public function getPrimaryFace(): array
    {
        $faceData = $this->faceDetection;
        return $faceData['primary_face'] ?? [];
    }
    
    /**
     * Get detected objects
     * 
     * @return array<string, mixed> Detected objects
     */
    public function getDetectedObjects(): array
    {
        $objectData = $this->objectDetection;
        return $objectData['objects'] ?? [];
    }
    
    /**
     * Get scene classification
     * 
     * @return array<string, mixed> Scene classification
     */
    public function getSceneType(): array
    {
        $sceneData = $this->sceneClassification;
        return $sceneData['primary_scene'] ?? [];
    }
    
    /**
     * Get dominant colors
     * 
     * @return array<string, mixed> Dominant colors
     */
    public function getDominantColors(): array
    {
        $colorData = $this->colorAnalysis;
        return $colorData['dominant_colors'] ?? [];
    }
    
    /**
     * Get processing status
     * 
     * @return string Processing status
     */
    public function getProcessingStatus(): string
    {
        $processingData = $this->processingResults;
        return $processingData['status'] ?? 'pending';
    }
    
    /**
     * Check if processing is complete
     * 
     * @return bool True if processing is complete
     */
    public function isProcessingComplete(): bool
    {
        return $this->getProcessingStatus() === 'completed';
    }
    
    /**
     * Get optimization level
     * 
     * @return string Optimization level
     */
    public function getOptimizationLevel(): string
    {
        $optimizationData = $this->optimizationResults;
        return $optimizationData['level'] ?? 'none';
    }
    
    /**
     * Get thumbnail URLs
     * 
     * @return array<string, string> Thumbnail URLs by size
     */
    public function getThumbnailUrls(): array
    {
        $thumbnailData = $this->thumbnailResults;
        return $thumbnailData['urls'] ?? [];
    }

    // ================================================================
    // FACTORY METHODS
    // ================================================================
    
    /**
     * Create PhotoMetadata from database record
     * 
     * @param array<string, mixed> $record Database record
     * @return self
     */
    public static function fromDatabaseRecord(array $record): self
    {
        return new self($record);
    }
    
    /**
     * Create PhotoMetadata from UserImage model
     * 
     * @param \App\Models\User\UserImage $userImage UserImage model
     * @return self
     */
    public static function fromUserImage(\App\Models\User\UserImage $userImage): self
    {
        $metadata = $userImage->metadata ?? [];
        
        // Extract basic information from UserImage
        $metadataData = array_merge($metadata, [
            'dimensions' => [
                'width' => $userImage->width ?? 0,
                'height' => $userImage->height ?? 0,
            ],
            'file_size' => $userImage->size_bytes ?? 0,
            'mime_type' => $userImage->mime_type ?? 'image/jpeg',
            'filename' => $userImage->filename ?? '',
        ]);
        
        return new self($metadataData);
    }
    
    /**
     * Create PhotoMetadata with default values
     * 
     * @param array<string, mixed> $initialData Initial data
     * @return self
     */
    public static function withDefaults(array $initialData = []): self
    {
        $defaults = [
            'dimensions' => ['width' => 0, 'height' => 0],
            'file_size' => 0,
            'mime_type' => 'image/jpeg',
            'filename' => '',
            'exif_data' => [],
            'camera_info' => [],
            'location_data' => [],
            'ai_analysis' => [],
            'content_detection' => [],
            'face_detection' => [],
            'object_detection' => [],
            'scene_classification' => [],
            'quality_assessment' => [],
            'processing_results' => [],
            'optimization_results' => [],
            'thumbnail_results' => [],
            'color_analysis' => [],
            'technical_specs' => [],
        ];
        
        return new self(array_merge($defaults, $initialData));
    }
    
    /**
     * Create PhotoMetadata from uploaded file
     * 
     * @param \Illuminate\Http\UploadedFile $file Uploaded file
     * @param array<string, mixed> $additionalData Additional metadata
     * @return self
     */
    public static function fromUploadedFile(\Illuminate\Http\UploadedFile $file, array $additionalData = []): self
    {
        $imageInfo = getimagesize($file->getPathname());
        
        $metadataData = array_merge([
            'dimensions' => [
                'width' => $imageInfo[0] ?? 0,
                'height' => $imageInfo[1] ?? 0,
            ],
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'filename' => $file->getClientOriginalName(),
            'exif_data' => self::extractExifData($file),
        ], $additionalData);
        
        return new self($metadataData);
    }

    // ================================================================
    // SERIALIZATION
    // ================================================================
    
    /**
     * Convert to array representation
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dimensions' => $this->dimensions,
            'file_size' => $this->fileSize,
            'formatted_file_size' => $this->getFormattedFileSize(),
            'mime_type' => $this->mimeType,
            'filename' => $this->filename,
            'aspect_ratio' => $this->aspectRatio,
            'exif_data' => $this->exifData,
            'camera_info' => $this->cameraInfo,
            'location_data' => $this->locationData,
            'ai_analysis' => $this->aiAnalysis,
            'content_detection' => $this->contentDetection,
            'face_detection' => $this->faceDetection,
            'object_detection' => $this->objectDetection,
            'scene_classification' => $this->sceneClassification,
            'quality_assessment' => $this->qualityAssessment,
            'processing_results' => $this->processingResults,
            'optimization_results' => $this->optimizationResults,
            'thumbnail_results' => $this->thumbnailResults,
            'color_analysis' => $this->colorAnalysis,
            'technical_specs' => $this->technicalSpecs,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
            'schema_version' => $this->schemaVersion,
            'calculated_properties' => [
                'is_landscape' => $this->isLandscape(),
                'is_portrait' => $this->isPortrait(),
                'is_square' => $this->isSquare(),
                'meets_minimum_size' => $this->meetsMinimumSize(),
                'exceeds_maximum_size' => $this->exceedsMaximumSize(),
                'has_acceptable_file_size' => $this->hasAcceptableFileSize(),
                'has_supported_format' => $this->hasSupportedFormat(),
                'overall_quality_score' => $this->getOverallQualityScore(),
                'has_high_quality' => $this->hasHighQuality(),
                'has_medium_quality' => $this->hasMediumQuality(),
                'has_low_quality' => $this->hasLowQuality(),
                'face_count' => $this->getFaceCount(),
                'has_faces' => $this->hasFaces(),
                'primary_face' => $this->getPrimaryFace(),
                'detected_objects' => $this->getDetectedObjects(),
                'scene_type' => $this->getSceneType(),
                'dominant_colors' => $this->getDominantColors(),
                'processing_status' => $this->getProcessingStatus(),
                'is_processing_complete' => $this->isProcessingComplete(),
                'optimization_level' => $this->getOptimizationLevel(),
                'thumbnail_urls' => $this->getThumbnailUrls(),
            ]
        ];
    }
    
    /**
     * Create from array representation
     * 
     * @param array<string, mixed> $data Array data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    // ================================================================
    // COMPARISON METHODS
    // ================================================================
    
    /**
     * Check if this PhotoMetadata equals another
     * 
     * @param PhotoMetadata $other Other PhotoMetadata to compare
     * @return bool True if equal
     */
    public function equals(PhotoMetadata $other): bool
    {
        return $this->dimensions === $other->dimensions &&
               $this->fileSize === $other->fileSize &&
               $this->mimeType === $other->mimeType &&
               $this->filename === $other->filename;
    }
    
    /**
     * Compare technical specifications with another PhotoMetadata
     * 
     * @param PhotoMetadata $other Other PhotoMetadata to compare
     * @return array<string, mixed> Comparison results
     */
    public function compareWith(PhotoMetadata $other): array
    {
        return [
            'dimensions_difference' => [
                'width' => $this->getWidth() - $other->getWidth(),
                'height' => $this->getHeight() - $other->getHeight(),
            ],
            'file_size_difference' => $this->getFileSize() - $other->getFileSize(),
            'quality_score_difference' => $this->getOverallQualityScore() - $other->getOverallQualityScore(),
            'face_count_difference' => $this->getFaceCount() - $other->getFaceCount(),
            'format_comparison' => [
                'this_format' => $this->getMimeType(),
                'other_format' => $other->getMimeType(),
                'same_format' => $this->getMimeType() === $other->getMimeType(),
            ],
            'orientation_comparison' => [
                'this_orientation' => $this->isLandscape() ? 'landscape' : ($this->isPortrait() ? 'portrait' : 'square'),
                'other_orientation' => $other->isLandscape() ? 'landscape' : ($other->isPortrait() ? 'portrait' : 'square'),
            ],
        ];
    }

    // ================================================================
    // PRIVATE METHODS
    // ================================================================
    
    /**
     * Validate and set metadata data from input array
     * 
     * @param array<string, mixed> $data Input data
     * @throws InvalidArgumentException
     */
    private function validateAndSetMetadataData(array $data): void
    {
        $this->dimensions = $this->validateDimensions($data['dimensions'] ?? []);
        $this->fileSize = $this->validateInt($data['file_size'] ?? 0, 'file_size');
        $this->mimeType = $this->validateString($data['mime_type'] ?? 'image/jpeg', 'mime_type');
        $this->filename = $this->validateString($data['filename'] ?? '', 'filename');
        
        $this->exifData = $this->validateArray($data['exif_data'] ?? [], 'exif_data');
        $this->cameraInfo = $this->validateArray($data['camera_info'] ?? [], 'camera_info');
        $this->locationData = $this->validateArray($data['location_data'] ?? [], 'location_data');
        $this->aiAnalysis = $this->validateArray($data['ai_analysis'] ?? [], 'ai_analysis');
        $this->contentDetection = $this->validateArray($data['content_detection'] ?? [], 'content_detection');
        $this->faceDetection = $this->validateArray($data['face_detection'] ?? [], 'face_detection');
        $this->objectDetection = $this->validateArray($data['object_detection'] ?? [], 'object_detection');
        $this->sceneClassification = $this->validateArray($data['scene_classification'] ?? [], 'scene_classification');
        $this->qualityAssessment = $this->validateArray($data['quality_assessment'] ?? [], 'quality_assessment');
        $this->processingResults = $this->validateArray($data['processing_results'] ?? [], 'processing_results');
        $this->optimizationResults = $this->validateArray($data['optimization_results'] ?? [], 'optimization_results');
        $this->thumbnailResults = $this->validateArray($data['thumbnail_results'] ?? [], 'thumbnail_results');
        $this->colorAnalysis = $this->validateArray($data['color_analysis'] ?? [], 'color_analysis');
        $this->technicalSpecs = $this->validateArray($data['technical_specs'] ?? [], 'technical_specs');
    }
    
    /**
     * Calculate derived properties from base data
     */
    private function calculateDerivedProperties(): void
    {
        $width = $this->getWidth();
        $height = $this->getHeight();
        
        if ($height > 0) {
            $this->aspectRatio = round($width / $height, 3);
        } else {
            $this->aspectRatio = 0.0;
        }
    }
    
    /**
     * Set timestamps
     */
    private function setTimestamps(): void
    {
        $now = now();
        $this->createdAt = $now->copy();
        $this->updatedAt = $now->copy();
    }
    
    /**
     * Extract EXIF data from uploaded file
     * 
     * @param \Illuminate\Http\UploadedFile $file Uploaded file
     * @return array<string, mixed> EXIF data
     */
    private static function extractExifData(\Illuminate\Http\UploadedFile $file): array
    {
        if (!function_exists('exif_read_data')) {
            return [];
        }
        
        try {
            $exif = exif_read_data($file->getPathname());
            return $exif ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Validate dimensions array
     * 
     * @param mixed $dimensions Dimensions data
     * @return array<string, int> Validated dimensions
     * @throws InvalidArgumentException
     */
    private function validateDimensions($dimensions): array
    {
        if (!is_array($dimensions)) {
            throw new InvalidArgumentException('Dimensions must be an array');
        }
        
        $width = $this->validateInt($dimensions['width'] ?? 0, 'width');
        $height = $this->validateInt($dimensions['height'] ?? 0, 'height');
        
        if ($width < 0 || $height < 0) {
            throw new InvalidArgumentException('Dimensions cannot be negative');
        }
        
        return ['width' => $width, 'height' => $height];
    }
    
    /**
     * Validate integer value
     * 
     * @param mixed $value Value to validate
     * @param string $fieldName Field name for error messages
     * @return int Validated integer
     * @throws InvalidArgumentException
     */
    private function validateInt($value, string $fieldName): int
    {
        if (!is_int($value) && !is_numeric($value)) {
            throw new InvalidArgumentException("Invalid integer value for field '{$fieldName}'");
        }
        
        $intValue = (int) $value;
        if ($intValue < 0) {
            throw new InvalidArgumentException("Field '{$fieldName}' cannot be negative");
        }
        
        return $intValue;
    }
    
    /**
     * Validate string value
     * 
     * @param mixed $value Value to validate
     * @param string $fieldName Field name for error messages
     * @return string Validated string
     * @throws InvalidArgumentException
     */
    private function validateString($value, string $fieldName): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException("Invalid string value for field '{$fieldName}'");
        }
        
        return $value;
    }
    
    /**
     * Validate array value
     * 
     * @param mixed $value Value to validate
     * @param string $fieldName Field name for error messages
     * @return array Validated array
     * @throws InvalidArgumentException
     */
    private function validateArray($value, string $fieldName): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("Invalid array value for field '{$fieldName}'");
        }
        
        return $value;
    }

    // ================================================================
    // COMPATIBILITY METHODS
    // ================================================================
    
    /**
     * Convert to format compatible with UserImage model
     * 
     * @return array<string, mixed> UserImage compatible format
     */
    public function toUserImageFormat(): array
    {
        return [
            'width' => $this->getWidth(),
            'height' => $this->getHeight(),
            'size_bytes' => $this->getFileSize(),
            'mime_type' => $this->getMimeType(),
            'metadata' => $this->toArray(),
        ];
    }
    
    /**
     * Convert to format compatible with PhotoRepositoryInterface
     * 
     * @return array<string, mixed> Repository compatible format
     */
    public function toRepositoryFormat(): array
    {
        return [
            'dimensions' => $this->dimensions,
            'file_size' => $this->fileSize,
            'mime_type' => $this->mimeType,
            'filename' => $this->filename,
            'aspect_ratio' => $this->aspectRatio,
            'exif_data' => $this->exifData,
            'ai_analysis' => $this->aiAnalysis,
            'quality_assessment' => $this->qualityAssessment,
            'processing_results' => $this->processingResults,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    // ================================================================
    // MAGIC METHODS
    // ================================================================
    
    /**
     * String representation
     * 
     * @return string String representation
     */
    public function __toString(): string
    {
        return "PhotoMetadata({$this->getWidth()}x{$this->getHeight()}, {$this->getFormattedFileSize()}, {$this->getMimeType()})";
    }
    
    /**
     * JSON serialization
     * 
     * @return array<string, mixed> JSON representation
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
    
    /**
     * Debug information
     * 
     * @return array<string, mixed> Debug information
     */
    public function __debugInfo(): array
    {
        return [
            'dimensions' => $this->dimensions,
            'file_size' => $this->fileSize,
            'mime_type' => $this->mimeType,
            'filename' => $this->filename,
            'aspect_ratio' => $this->aspectRatio,
            'quality_score' => $this->getOverallQualityScore(),
            'face_count' => $this->getFaceCount(),
            'processing_status' => $this->getProcessingStatus(),
        ];
    }
}
