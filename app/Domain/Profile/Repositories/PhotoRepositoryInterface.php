<?php

declare(strict_types=1);

namespace App\Domain\Profile\Repositories;

use App\Domain\Profile\Entities\Photo;
use App\Domain\Profile\ValueObjects\PhotoId;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Profile\ValueObjects\ProfileId;
use App\Domain\Profile\ValueObjects\PhotoQualityScore;
use App\Domain\Profile\ValueObjects\PhotoStatus;
use App\Domain\Profile\ValueObjects\PhotoModerationResult;
use App\Domain\Profile\ValueObjects\PhotoAnalytics;
use App\Domain\Profile\ValueObjects\PhotoProcessingStatus;
use App\Domain\Profile\ValueObjects\PhotoMetadata;
use App\Domain\Profile\ValueObjects\PhotoReportResult;
use App\Domain\Profile\ValueObjects\PhotoEngagementMetrics;
use Illuminate\Support\Collection;
use Carbon\CarbonInterface;

/**
 * PhotoRepositoryInterface
 * 
 * Repository contract for photo management operations within the ForeverUsInLove dating platform.
 * Provides comprehensive photo storage, retrieval, moderation, analytics, and lifecycle management
 * following Domain-Driven Design principles and Clean Architecture patterns.
 * 
 * Key Responsibilities:
 * - Photo CRUD operations with version management
 * - Photo moderation workflow and approval processes
 * - Photo quality analysis and scoring
 * - Photo engagement metrics and analytics
 * - Photo privacy and visibility management
 * - Photo backup and recovery operations
 * - Compliance and audit trail management
 * 
 * @package App\Domain\Profile\Repositories
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove
 * @license Proprietary
 * @version 1.0.0
 * @since 2024-01-01
 * 
 * @phpstan-consistent-constructor
 */
interface PhotoRepositoryInterface
{
    // ================================================================
    // BASIC CRUD OPERATIONS
    // ================================================================
    
    /**
     * Save a photo to the repository
     * 
     * Persists a photo entity with full audit trail and versioning support.
     * Handles both new photo creation and existing photo updates with
     * automatic metadata management and change tracking.
     * 
     * @param Photo $photo The photo entity to save
     * @return Photo The saved photo with updated metadata
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoSaveException
     * @throws \App\Domain\Profile\Exceptions\PhotoStorageException
     * @throws \App\Domain\Profile\Exceptions\InvalidPhotoDataException
     * 
     * @example
     * ```php
     * $photo = new Photo($photoId, $profileId, $originalUrl, $metadata);
     * $savedPhoto = $repository->save($photo);
     * ```
     */
    public function save(Photo $photo): Photo;

    /**
     * Find a photo by its unique identifier
     * 
     * Retrieves a photo entity by its PhotoId with optional eager loading
     * of related data for performance optimization.
     * 
     * @param PhotoId $photoId The unique photo identifier
     * @return Photo|null The photo entity or null if not found
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoAccessException
     * 
     * @example
     * ```php
     * $photoId = PhotoId::fromString('550e8400-e29b-41d4-a716-446655440000');
     * $photo = $repository->findById($photoId);
     * ```
     */
    public function findById(PhotoId $photoId): ?Photo;

    /**
     * Find multiple photos by their identifiers
     * 
     * Efficiently retrieves multiple photos in a single query operation
     * with consistent ordering and metadata loading.
     * 
     * @param array<PhotoId> $photoIds Array of photo identifiers
     * @return Collection<Photo> Collection of found photos
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoAccessException
     * 
     * @example
     * ```php
     * $photoIds = [PhotoId::fromString('id1'), PhotoId::fromString('id2')];
     * $photos = $repository->findByIds($photoIds);
     * ```
     */
    public function findByIds(array $photoIds): Collection;

    /**
     * Delete a photo by its identifier
     * 
     * Performs soft delete with audit trail preservation and automatic
     * cleanup of associated resources and metadata.
     * 
     * @param PhotoId $photoId The photo identifier to delete
     * @return bool True if deletion was successful
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoDeleteException
     * @throws \App\Domain\Profile\Exceptions\PhotoNotDeletableException
     * 
     * @example
     * ```php
     * $photoId = PhotoId::fromString('550e8400-e29b-41d4-a716-446655440000');
     * $success = $repository->deleteById($photoId);
     * ```
     */
    public function deleteById(PhotoId $photoId): bool;

    /**
     * Check if a photo exists by identifier
     * 
     * Efficiently checks photo existence without loading full entity data.
     * Useful for validation and permission checking operations.
     * 
     * @param PhotoId $photoId The photo identifier to check
     * @return bool True if photo exists
     * 
     * @example
     * ```php
     * $exists = $repository->exists($photoId);
     * if ($exists) {
     *     // Proceed with photo operations
     * }
     * ```
     */
    public function exists(PhotoId $photoId): bool;

    /**
     * Create a new photo record
     * 
     * Creates a new photo entity from array data with automatic
     * validation and metadata initialization.
     * 
     * @param array<string, mixed> $data Photo data array
     * @return Photo The created photo entity
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoCreateException
     * 
     * @example
     * ```php
     * $photo = $repository->create([
     *     'user_id' => 123,
     *     'filename' => 'photo.jpg',
     *     'status' => 'pending'
     * ]);
     * ```
     */
    public function create(array $data): Photo;

    /**
     * Update photo by ID
     * 
     * Updates an existing photo with new data and automatically
     * invalidates related cache entries.
     * 
     * @param int $photoId The photo ID to update
     * @param array<string, mixed> $data Update data array
     * @return bool True if update was successful
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoUpdateException
     * 
     * @example
     * ```php
     * $success = $repository->update(123, ['status' => 'approved']);
     * ```
     */
    public function update(int $photoId, array $data): bool;

    /**
     * Delete photo by ID
     * 
     * Soft deletes a photo by integer ID with audit trail preservation.
     * 
     * @param int $photoId The photo ID to delete
     * @return bool True if deletion was successful
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoDeleteException
     * 
     * @example
     * ```php
     * $success = $repository->delete(123);
     * ```
     */
    public function delete(int $photoId): bool;

    /**
     * Count photos by user ID
     * 
     * Efficiently counts total photos for a user with optional
     * status filtering for validation operations.
     * 
     * @param int $userId The user ID to count photos for
     * @return int Number of photos for the user
     * 
     * @example
     * ```php
     * $photoCount = $repository->countByUserId(123);
     * if ($photoCount >= 9) {
     *     // User has reached photo limit
     * }
     * ```
     */
    public function countByUserId(int $userId): int;

    /**
     * Find photos by user ID
     * 
     * Retrieves all photos for a specific user ordered by display order
     * with automatic entity conversion.
     * 
     * @param int $userId The user ID to find photos for
     * @return Collection<Photo> Collection of user photos
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoAccessException
     * 
     * @example
     * ```php
     * $userPhotos = $repository->findByUserId(123);
     * foreach ($userPhotos as $photo) {
     *     // Process each photo
     * }
     * ```
     */
    public function findByUserId(int $userId): Collection;

    /**
     * Update photos with conditions
     * 
     * Updates multiple photos matching specific conditions in a single
     * operation for bulk updates and maintenance tasks.
     * 
     * @param array<string, mixed> $conditions Update conditions
     * @param array<string, mixed> $data Update data
     * @return bool True if any photos were updated
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoBulkUpdateException
     * 
     * @example
     * ```php
     * $updated = $repository->updateWhere(
     *     ['user_id' => 123, 'is_primary' => true],
     *     ['is_primary' => false]
     * );
     * ```
     */
    public function updateWhere(array $conditions, array $data): bool;

    /**
     * Find next available photo for primary
     * 
     * Finds the next suitable photo to promote as primary when the
     * current primary photo is deleted or hidden.
     * 
     * @param int $userId The user ID to find next photo for
     * @return Photo|null The next available photo or null
     * 
     * @example
     * ```php
     * $nextPhoto = $repository->findNextAvailablePhoto(123);
     * if ($nextPhoto) {
     *     $repository->setPrimaryPhoto($profileId, $nextPhoto->getId());
     * }
     * ```
     */
    public function findNextAvailablePhoto(int $userId): ?Photo;

    /**
     * Find photos by user ID ordered by display order
     * 
     * Retrieves user photos sorted by their display order for
     * reordering operations and gallery management.
     * 
     * @param int $userId The user ID to find photos for
     * @return Collection<Photo> Photos ordered by display order
     * 
     * @example
     * ```php
     * $orderedPhotos = $repository->findByUserIdOrderByDisplay(123);
     * // Use for reordering operations
     * ```
     */
    public function findByUserIdOrderByDisplay(int $userId): Collection;

    // ================================================================
    // PROFILE-BASED PHOTO OPERATIONS
    // ================================================================
    
    /**
     * Find all photos for a specific profile
     * 
     * Retrieves all photos associated with a profile, ordered by position
     * and filtered by visibility and moderation status.
     * 
     * @param ProfileId $profileId The profile identifier
     * @param array<string> $statuses Optional status filter (approved, pending, rejected)
     * @param bool $includePrivate Whether to include private photos
     * @return Collection<Photo> Ordered collection of profile photos
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoAccessException
     * 
     * @example
     * ```php
     * $photos = $repository->findByProfile($profileId, ['approved'], false);
     * foreach ($photos as $photo) {
     *     // Process each approved public photo
     * }
     * ```
     */
    public function findByProfile(
        ProfileId $profileId, 
        array $statuses = [], 
        bool $includePrivate = false
    ): Collection;

    /**
     * Find photos by user across all their profiles
     * 
     * Retrieves photos from all profiles owned by a user with optional
     * filtering and pagination support.
     * 
     * @param UserId $userId The user identifier
     * @param array<string> $statuses Optional status filter
     * @param int $limit Maximum number of photos to return
     * @param int $offset Pagination offset
     * @return Collection<Photo> Collection of user photos
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoAccessException
     * 
     * @example
     * ```php
     * $userPhotos = $repository->findByUser($userId, ['approved'], 20, 0);
     * ```
     */
    public function findByUser(
        UserId $userId, 
        array $statuses = [], 
        int $limit = 50, 
        int $offset = 0
    ): Collection;

    /**
     * Get the primary photo for a profile
     * 
     * Retrieves the designated primary photo for profile display with
     * fallback logic for automatic primary selection.
     * 
     * @param ProfileId $profileId The profile identifier
     * @return Photo|null The primary photo or null if none exists
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoAccessException
     * 
     * @example
     * ```php
     * $primaryPhoto = $repository->getPrimaryPhoto($profileId);
     * if ($primaryPhoto) {
     *     $url = $primaryPhoto->getOptimizedUrl('large');
     * }
     * ```
     */
    public function getPrimaryPhoto(ProfileId $profileId): ?Photo;

    /**
     * Set a photo as the primary photo for a profile
     * 
     * Updates the primary photo designation with automatic reordering
     * and cache invalidation for profile display optimization.
     * 
     * @param ProfileId $profileId The profile identifier
     * @param PhotoId $photoId The photo to set as primary
     * @return bool True if primary photo was set successfully
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoUpdateException
     * @throws \App\Domain\Profile\Exceptions\PhotoNotOwnedException
     * 
     * @example
     * ```php
     * $success = $repository->setPrimaryPhoto($profileId, $photoId);
     * ```
     */
    public function setPrimaryPhoto(ProfileId $profileId, PhotoId $photoId): bool;

    /**
     * Reorder photos for a profile
     * 
     * Updates photo position ordering with validation and automatic
     * gap handling for consistent display ordering.
     * 
     * @param ProfileId $profileId The profile identifier
     * @param array<PhotoId> $photoOrder Array of photo IDs in desired order
     * @return bool True if reordering was successful
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoReorderException
     * @throws \App\Domain\Profile\Exceptions\InvalidPhotoOrderException
     * 
     * @example
     * ```php
     * $newOrder = [$photoId1, $photoId2, $photoId3];
     * $success = $repository->reorderPhotos($profileId, $newOrder);
     * ```
     */
    public function reorderPhotos(ProfileId $profileId, array $photoOrder): bool;

    /**
     * Count photos for a profile by status
     * 
     * Efficiently counts photos with optional status filtering for
     * profile completeness and validation operations.
     * 
     * @param ProfileId $profileId The profile identifier
     * @param array<string> $statuses Optional status filter
     * @return int Number of matching photos
     * 
     * @example
     * ```php
     * $approvedCount = $repository->countByProfile($profileId, ['approved']);
     * $pendingCount = $repository->countByProfile($profileId, ['pending']);
     * ```
     */
    public function countByProfile(ProfileId $profileId, array $statuses = []): int;

    // ================================================================
    // PHOTO MODERATION AND QUALITY OPERATIONS
    // ================================================================
    
    /**
     * Find photos pending moderation
     * 
     * Retrieves photos awaiting moderation review with priority ordering
     * and optional content type filtering for efficient moderation workflows.
     * 
     * @param int $limit Maximum number of photos to return
     * @param string|null $contentType Optional content type filter
     * @param string $orderBy Ordering criteria (newest, oldest, priority)
     * @return Collection<Photo> Photos pending moderation
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoAccessException
     * 
     * @example
     * ```php
     * $pendingPhotos = $repository->findPendingModeration(50, null, 'priority');
     * foreach ($pendingPhotos as $photo) {
     *     // Process moderation queue
     * }
     * ```
     */
    public function findPendingModeration(
        int $limit = 100, 
        ?string $contentType = null, 
        string $orderBy = 'newest'
    ): Collection;

    /**
     * Update photo moderation status
     * 
     * Updates moderation status with comprehensive audit trail and
     * automatic notification triggering for status changes.
     * 
     * @param PhotoId $photoId The photo identifier
     * @param PhotoModerationResult $moderationResult The moderation decision
     * @param UserId|null $moderatorId The moderator making the decision
     * @return bool True if status was updated successfully
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoModerationException
     * 
     * @example
     * ```php
     * $result = new PhotoModerationResult('approved', 'Quality content');
     * $success = $repository->updateModerationStatus($photoId, $result, $moderatorId);
     * ```
     */
    public function updateModerationStatus(
        PhotoId $photoId, 
        PhotoModerationResult $moderationResult, 
        ?UserId $moderatorId = null
    ): bool;

    /**
     * Find photos by moderation status
     * 
     * Retrieves photos filtered by moderation status with pagination
     * and sorting support for moderation management interfaces.
     * 
     * @param string $status The moderation status (pending, approved, rejected)
     * @param int $limit Maximum number of photos to return
     * @param int $offset Pagination offset
     * @param string $orderBy Ordering criteria
     * @return Collection<Photo> Photos matching moderation status
     * 
     * @example
     * ```php
     * $rejectedPhotos = $repository->findByModerationStatus('rejected', 20, 0);
     * ```
     */
    public function findByModerationStatus(
        string $status, 
        int $limit = 50, 
        int $offset = 0, 
        string $orderBy = 'created_at'
    ): Collection;

    /**
     * Update photo quality score
     * 
     * Updates calculated quality score with historical tracking and
     * automatic visibility adjustments based on quality thresholds.
     * 
     * @param PhotoId $photoId The photo identifier
     * @param PhotoQualityScore $qualityScore The calculated quality score
     * @return bool True if score was updated successfully
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoUpdateException
     * 
     * @example
     * ```php
     * $qualityScore = new PhotoQualityScore(8.5, ['clarity' => 9, 'composition' => 8]);
     * $success = $repository->updateQualityScore($photoId, $qualityScore);
     * ```
     */
    public function updateQualityScore(PhotoId $photoId, PhotoQualityScore $qualityScore): bool;

    /**
     * Find photos by minimum quality score
     * 
     * Retrieves photos meeting quality thresholds for featured content
     * and high-visibility placement optimization.
     * 
     * @param float $minScore Minimum quality score threshold
     * @param int $limit Maximum number of photos to return
     * @param array<string> $statuses Optional status filter
     * @return Collection<Photo> High-quality photos
     * 
     * @example
     * ```php
     * $highQualityPhotos = $repository->findByMinQuality(8.0, 100, ['approved']);
     * ```
     */
    public function findByMinQuality(
        float $minScore, 
        int $limit = 100, 
        array $statuses = ['approved']
    ): Collection;

    // ================================================================
    // PHOTO ANALYTICS AND ENGAGEMENT
    // ================================================================
    
    /**
     * Update photo engagement metrics
     * 
     * Updates engagement statistics with incremental counters and
     * automatic trend analysis for recommendation optimization.
     * 
     * @param PhotoId $photoId The photo identifier
     * @param PhotoEngagementMetrics $metrics The updated engagement metrics
     * @return bool True if metrics were updated successfully
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoUpdateException
     * 
     * @example
     * ```php
     * $metrics = new PhotoEngagementMetrics([
     *     'views' => 150,
     *     'likes' => 25,
     *     'comments' => 5
     * ]);
     * $success = $repository->updateEngagementMetrics($photoId, $metrics);
     * ```
     */
    public function updateEngagementMetrics(PhotoId $photoId, PhotoEngagementMetrics $metrics): bool;

    /**
     * Get photo analytics data
     * 
     * Retrieves comprehensive analytics including engagement trends,
     * performance metrics, and comparative analysis data.
     * 
     * @param PhotoId $photoId The photo identifier
     * @param CarbonInterface|null $startDate Optional start date for analytics period
     * @param CarbonInterface|null $endDate Optional end date for analytics period
     * @return PhotoAnalytics Comprehensive photo analytics data
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoAccessException
     * 
     * @example
     * ```php
     * $analytics = $repository->getPhotoAnalytics($photoId, $startDate, $endDate);
     * $viewCount = $analytics->getTotalViews();
     * $engagementRate = $analytics->getEngagementRate();
     * ```
     */
    public function getPhotoAnalytics(
        PhotoId $photoId, 
        ?CarbonInterface $startDate = null, 
        ?CarbonInterface $endDate = null
    ): PhotoAnalytics;

    /**
     * Find top performing photos
     * 
     * Retrieves photos with highest engagement metrics for featured
     * content and success pattern analysis.
     * 
     * @param string $metric The metric to optimize for (views, likes, engagement_rate)
     * @param int $limit Maximum number of photos to return
     * @param CarbonInterface|null $since Optional date threshold for recent performance
     * @param array<string> $statuses Optional status filter
     * @return Collection<Photo> Top performing photos
     * 
     * @example
     * ```php
     * $topPhotos = $repository->findTopPerforming('engagement_rate', 50, $since);
     * ```
     */
    public function findTopPerforming(
        string $metric = 'engagement_rate', 
        int $limit = 50, 
        ?CarbonInterface $since = null,
        array $statuses = ['approved']
    ): Collection;

    /**
     * Get profile photo performance summary
     * 
     * Generates aggregate performance metrics for all photos in a profile
     * with comparative analysis and optimization recommendations.
     * 
     * @param ProfileId $profileId The profile identifier
     * @param CarbonInterface|null $since Optional date threshold for recent data
     * @return array<string, mixed> Performance summary data
     * 
     * @example
     * ```php
     * $summary = $repository->getProfilePhotoPerformance($profileId, $since);
     * $averageEngagement = $summary['average_engagement_rate'];
     * $bestPerformingPhoto = $summary['best_performing_photo_id'];
     * ```
     */
    public function getProfilePhotoPerformance(
        ProfileId $profileId, 
        ?CarbonInterface $since = null
    ): array;

    // ================================================================
    // PHOTO PROCESSING AND STATUS MANAGEMENT
    // ================================================================
    
    /**
     * Update photo processing status
     * 
     * Updates processing pipeline status with progress tracking and
     * automatic error handling for failed processing operations.
     * 
     * @param PhotoId $photoId The photo identifier
     * @param PhotoProcessingStatus $status The current processing status
     * @return bool True if status was updated successfully
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoUpdateException
     * 
     * @example
     * ```php
     * $status = new PhotoProcessingStatus('completed', ['thumbnails_generated' => true]);
     * $success = $repository->updateProcessingStatus($photoId, $status);
     * ```
     */
    public function updateProcessingStatus(PhotoId $photoId, PhotoProcessingStatus $status): bool;

    /**
     * Find photos by processing status
     * 
     * Retrieves photos in specific processing states for pipeline
     * management and error recovery operations.
     * 
     * @param string $status The processing status (pending, processing, completed, failed)
     * @param int $limit Maximum number of photos to return
     * @param CarbonInterface|null $olderThan Optional age threshold for stuck processing
     * @return Collection<Photo> Photos matching processing status
     * 
     * @example
     * ```php
     * $stuckPhotos = $repository->findByProcessingStatus('processing', 100, $olderThan);
     * ```
     */
    public function findByProcessingStatus(
        string $status, 
        int $limit = 100, 
        ?CarbonInterface $olderThan = null
    ): Collection;

    /**
     * Update photo metadata
     * 
     * Updates comprehensive photo metadata including EXIF data, AI analysis
     * results, and technical specifications with versioning support.
     * 
     * @param PhotoId $photoId The photo identifier
     * @param PhotoMetadata $metadata The updated metadata
     * @return bool True if metadata was updated successfully
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoUpdateException
     * 
     * @example
     * ```php
     * $metadata = new PhotoMetadata([
     *     'dimensions' => ['width' => 1920, 'height' => 1080],
     *     'ai_tags' => ['outdoor', 'smiling', 'casual'],
     *     'technical_quality' => 8.5
     * ]);
     * $success = $repository->updateMetadata($photoId, $metadata);
     * ```
     */
    public function updateMetadata(PhotoId $photoId, PhotoMetadata $metadata): bool;

    // ================================================================
    // PHOTO SEARCH AND FILTERING
    // ================================================================
    
    /**
     * Search photos by criteria
     * 
     * Advanced photo search with multiple filter criteria including
     * AI-generated tags, metadata attributes, and engagement metrics.
     * 
     * @param array<string, mixed> $criteria Search criteria and filters
     * @param int $limit Maximum number of results to return
     * @param int $offset Pagination offset
     * @param string $orderBy Ordering criteria
     * @return Collection<Photo> Search results
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoSearchException
     * 
     * @example
     * ```php
     * $criteria = [
     *     'tags' => ['outdoor', 'smiling'],
     *     'min_quality' => 7.0,
     *     'status' => ['approved'],
     *     'has_faces' => true
     * ];
     * $results = $repository->searchByCriteria($criteria, 50, 0);
     * ```
     */
    public function searchByCriteria(
        array $criteria, 
        int $limit = 50, 
        int $offset = 0, 
        string $orderBy = 'created_at'
    ): Collection;

    /**
     * Find photos by AI-generated tags
     * 
     * Retrieves photos matching specific AI-generated content tags
     * for content discovery and recommendation systems.
     * 
     * @param array<string> $tags Array of tags to match
     * @param bool $matchAll Whether to match all tags (AND) or any tag (OR)
     * @param int $limit Maximum number of photos to return
     * @return Collection<Photo> Photos matching tag criteria
     * 
     * @example
     * ```php
     * $photos = $repository->findByTags(['outdoor', 'smiling'], true, 100);
     * ```
     */
    public function findByTags(array $tags, bool $matchAll = false, int $limit = 100): Collection;

    /**
     * Find photos by date range
     * 
     * Retrieves photos uploaded within a specific date range with
     * optional status and quality filtering.
     * 
     * @param CarbonInterface $startDate Start of date range
     * @param CarbonInterface $endDate End of date range
     * @param array<string> $statuses Optional status filter
     * @param int $limit Maximum number of photos to return
     * @return Collection<Photo> Photos within date range
     * 
     * @example
     * ```php
     * $recentPhotos = $repository->findByDateRange($startDate, $endDate, ['approved']);
     * ```
     */
    public function findByDateRange(
        CarbonInterface $startDate, 
        CarbonInterface $endDate, 
        array $statuses = [], 
        int $limit = 100
    ): Collection;

    // ================================================================
    // PHOTO REPORTING AND COMPLIANCE
    // ================================================================
    
    /**
     * Record photo report
     * 
     * Records user reports against photos with comprehensive tracking
     * and automatic escalation based on report severity and frequency.
     * 
     * @param PhotoId $photoId The reported photo identifier
     * @param UserId $reporterId The user making the report
     * @param PhotoReportResult $reportData The report details and classification
     * @return bool True if report was recorded successfully
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoReportException
     * 
     * @example
     * ```php
     * $reportData = new PhotoReportResult('inappropriate_content', 'Explicit imagery');
     * $success = $repository->recordReport($photoId, $reporterId, $reportData);
     * ```
     */
    public function recordReport(PhotoId $photoId, UserId $reporterId, PhotoReportResult $reportData): bool;

    /**
     * Find reported photos
     * 
     * Retrieves photos with active reports for moderation review
     * with priority ordering based on report severity and frequency.
     * 
     * @param int $limit Maximum number of photos to return
     * @param string $severity Optional severity filter (low, medium, high, critical)
     * @param bool $unresolved Whether to include only unresolved reports
     * @return Collection<Photo> Reported photos requiring review
     * 
     * @example
     * ```php
     * $reportedPhotos = $repository->findReported(50, 'high', true);
     * ```
     */
    public function findReported(int $limit = 100, ?string $severity = null, bool $unresolved = true): Collection;

    /**
     * Get photo audit trail
     * 
     * Retrieves comprehensive audit history for compliance and
     * investigation purposes with detailed action tracking.
     * 
     * @param PhotoId $photoId The photo identifier
     * @param int $limit Maximum number of audit entries to return
     * @return array<array<string, mixed>> Audit trail entries
     * 
     * @example
     * ```php
     * $auditTrail = $repository->getAuditTrail($photoId, 100);
     * foreach ($auditTrail as $entry) {
     *     echo $entry['action'] . ' by ' . $entry['user_id'] . ' at ' . $entry['timestamp'];
     * }
     * ```
     */
    public function getAuditTrail(PhotoId $photoId, int $limit = 100): array;

    // ================================================================
    // BULK OPERATIONS AND MAINTENANCE
    // ================================================================
    
    /**
     * Bulk update photo statuses
     * 
     * Efficiently updates multiple photos with the same status change
     * for batch moderation and administrative operations.
     * 
     * @param array<PhotoId> $photoIds Array of photo identifiers
     * @param PhotoStatus $newStatus The status to apply to all photos
     * @param UserId|null $moderatorId Optional moderator identifier
     * @return int Number of photos successfully updated
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoBulkUpdateException
     * 
     * @example
     * ```php
     * $photoIds = [$photoId1, $photoId2, $photoId3];
     * $newStatus = new PhotoStatus('approved');
     * $updated = $repository->bulkUpdateStatus($photoIds, $newStatus, $moderatorId);
     * ```
     */
    public function bulkUpdateStatus(array $photoIds, PhotoStatus $newStatus, ?UserId $moderatorId = null): int;

    /**
     * Cleanup orphaned photos
     * 
     * Identifies and removes photos not associated with active profiles
     * with comprehensive safety checks and audit trail maintenance.
     * 
     * @param int $olderThanDays Age threshold for orphaned photo cleanup
     * @param bool $dryRun Whether to perform actual deletion or return count only
     * @return int Number of orphaned photos found/cleaned
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoCleanupException
     * 
     * @example
     * ```php
     * // Check how many orphaned photos exist
     * $orphanedCount = $repository->cleanupOrphanedPhotos(30, true);
     * 
     * // Actually clean them up
     * $cleaned = $repository->cleanupOrphanedPhotos(30, false);
     * ```
     */
    public function cleanupOrphanedPhotos(int $olderThanDays = 30, bool $dryRun = false): int;

    /**
     * Archive old photos
     * 
     * Moves old inactive photos to archive storage with metadata
     * preservation for potential restoration and compliance requirements.
     * 
     * @param CarbonInterface $olderThan Threshold date for archiving
     * @param array<string> $excludeStatuses Statuses to exclude from archiving
     * @param bool $dryRun Whether to perform actual archiving or return count only
     * @return int Number of photos archived or eligible for archiving
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoArchiveException
     * 
     * @example
     * ```php
     * $thresholdDate = now()->subYears(2);
     * $archived = $repository->archiveOldPhotos($thresholdDate, ['approved'], false);
     * ```
     */
    public function archiveOldPhotos(
        CarbonInterface $olderThan, 
        array $excludeStatuses = [], 
        bool $dryRun = false
    ): int;

    // ================================================================
    // STATISTICS AND REPORTING
    // ================================================================
    
    /**
     * Get photo statistics summary
     * 
     * Generates comprehensive statistics including counts by status,
     * quality distribution, and processing metrics for dashboard display.
     * 
     * @param CarbonInterface|null $since Optional date threshold for recent statistics
     * @return array<string, mixed> Comprehensive statistics data
     * 
     * @example
     * ```php
     * $stats = $repository->getPhotoStatistics($since);
     * $totalPhotos = $stats['total_photos'];
     * $approvalRate = $stats['approval_rate'];
     * $averageQuality = $stats['average_quality_score'];
     * ```
     */
    public function getPhotoStatistics(?CarbonInterface $since = null): array;

    /**
     * Get moderation queue statistics
     * 
     * Provides real-time moderation queue metrics for workflow
     * optimization and resource allocation planning.
     * 
     * @return array<string, mixed> Moderation queue statistics
     * 
     * @example
     * ```php
     * $queueStats = $repository->getModerationQueueStats();
     * $pendingCount = $queueStats['pending_count'];
     * $averageProcessingTime = $queueStats['average_processing_time'];
     * $backlogAge = $queueStats['oldest_pending_hours'];
     * ```
     */
    public function getModerationQueueStats(): array;

    /**
     * Get quality distribution report
     * 
     * Analyzes photo quality score distribution for quality improvement
     * initiatives and content strategy optimization.
     * 
     * @param CarbonInterface|null $since Optional date threshold for recent analysis
     * @return array<string, mixed> Quality distribution analysis
     * 
     * @example
     * ```php
     * $qualityReport = $repository->getQualityDistribution($since);
     * $highQualityPercentage = $qualityReport['scores_8_plus_percentage'];
     * $improvementTrend = $qualityReport['quality_trend'];
     * ```
     */
    public function getQualityDistribution(?CarbonInterface $since = null): array;

    // ================================================================
    // PRIVACY AND DATA MANAGEMENT
    // ================================================================
    
    /**
     * Anonymize user photos
     * 
     * Removes personally identifiable information from photos for
     * GDPR compliance and user privacy protection with audit trail.
     * 
     * @param UserId $userId The user whose photos to anonymize
     * @param bool $preserveAnalytics Whether to preserve anonymized analytics data
     * @return int Number of photos anonymized
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoPrivacyException
     * 
     * @example
     * ```php
     * $anonymized = $repository->anonymizeUserPhotos($userId, true);
     * ```
     */
    public function anonymizeUserPhotos(UserId $userId, bool $preserveAnalytics = true): int;

    /**
     * Export user photo data
     * 
     * Generates comprehensive export of user's photo data for GDPR
     * data portability requirements with structured metadata inclusion.
     * 
     * @param UserId $userId The user requesting data export
     * @param bool $includeAnalytics Whether to include engagement analytics
     * @param bool $includeMetadata Whether to include technical metadata
     * @return array<string, mixed> Exportable photo data structure
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoExportException
     * 
     * @example
     * ```php
     * $exportData = $repository->exportUserPhotoData($userId, true, true);
     * file_put_contents('user_photos_export.json', json_encode($exportData));
     * ```
     */
    public function exportUserPhotoData(
        UserId $userId, 
        bool $includeAnalytics = false, 
        bool $includeMetadata = false
    ): array;

    /**
     * Hard delete user photos
     * 
     * Permanently removes all photo data for a user for GDPR
     * right to erasure with comprehensive cleanup and audit logging.
     * 
     * @param UserId $userId The user whose photos to delete
     * @param bool $preserveAuditTrail Whether to preserve anonymized audit records
     * @return int Number of photos permanently deleted
     * 
     * @throws \App\Domain\Profile\Exceptions\PhotoDeletionException
     * 
     * @example
     * ```php
     * $deleted = $repository->hardDeleteUserPhotos($userId, true);
     * ```
     */
    public function hardDeleteUserPhotos(UserId $userId, bool $preserveAuditTrail = true): int;
}