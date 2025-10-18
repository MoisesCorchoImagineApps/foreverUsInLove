<?php

declare(strict_types=1);

namespace App\Domain\Profile\ValueObjects;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * PhotoAnalytics Value Object
 * 
 * Represents comprehensive analytics data for a photo within the ForeverUsInLove dating platform.
 * Encapsulates engagement metrics, performance indicators, quality scores, and behavioral data
 * following Domain-Driven Design principles and immutable value object patterns.
 * 
 * Key Responsibilities:
 * - Encapsulate photo performance metrics
 * - Provide engagement analytics calculations
 * - Track quality and moderation scores
 * - Calculate comparative performance indicators
 * - Support A/B testing and optimization data
 * - Ensure data integrity and validation
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
final class PhotoAnalytics
{
    // ================================================================
    // CONSTANTS
    // ================================================================
    
    /**
     * Minimum engagement rate threshold for high-performing photos
     */
    private const HIGH_ENGAGEMENT_THRESHOLD = 0.15; // 15%
    
    /**
     * Minimum quality score for premium photo placement
     */
    private const PREMIUM_QUALITY_THRESHOLD = 8.0;
    
    /**
     * Standard analytics period in days
     */
    private const STANDARD_ANALYTICS_PERIOD = 30;
    
    /**
     * Maximum views per day for trending calculation
     */
    private const MAX_DAILY_VIEWS_TREND = 1000;

    // ================================================================
    // PROPERTIES
    // ================================================================
    
    /**
     * @var ProfileId
     */
    private ProfileId $photoId;
    
    /**
     * @var int Total number of views across all time
     */
    private int $totalViews;
    
    /**
     * @var int Total number of likes received
     */
    private int $totalLikes;
    
    /**
     * @var int Total number of dislikes received
     */
    private int $totalDislikes;
    
    /**
     * @var int Total number of shares/saves
     */
    private int $totalShares;
    
    /**
     * @var int Total number of profile visits from this photo
     */
    private int $totalProfileVisits;
    
    /**
     * @var int Total number of matches initiated from this photo
     */
    private int $totalMatchesInitiated;
    
    /**
     * @var int Total number of messages sent after viewing this photo
     */
    private int $totalMessagesInitiated;
    
    /**
     * @var float Calculated engagement rate (likes + shares) / views
     */
    private float $engagementRate;
    
    /**
     * @var float Calculated conversion rate (matches + messages) / views
     */
    private float $conversionRate;
    
    /**
     * @var float Photo quality score from AI analysis (0-10)
     */
    private float $qualityScore;
    
    /**
     * @var float Photo attractiveness score from user feedback (0-10)
     */
    private float $attractivenessScore;
    
    /**
     * @var float Photo uniqueness score based on content analysis (0-10)
     */
    private float $uniquenessScore;
    
    /**
     * @var float Photo authenticity score from verification systems (0-10)
     */
    private float $authenticityScore;
    
    /**
     * @var int Number of times photo was reported
     */
    private int $reportCount;
    
    /**
     * @var int Number of times photo was blocked/hidden by users
     */
    private int $blockCount;
    
    /**
     * @var float Average time users spend viewing this photo (seconds)
     */
    private float $averageViewTime;
    
    /**
     * @var float Completion rate for users who started viewing this photo
     */
    private float $viewCompletionRate;
    
    /**
     * @var array<string, int> Views breakdown by time periods
     */
    private array $viewsByPeriod;
    
    /**
     * @var array<string, int> Engagement breakdown by demographics
     */
    private array $engagementByDemographics;
    
    /**
     * @var array<string, float> Performance comparison with similar photos
     */
    private array $comparativeMetrics;
    
    /**
     * @var array<string, mixed> A/B testing data and results
     */
    private array $abTestingData;
    
    /**
     * @var CarbonInterface When analytics data was last updated
     */
    private CarbonInterface $lastUpdatedAt;
    
    /**
     * @var CarbonInterface Analytics period start date
     */
    private CarbonInterface $analyticsPeriodStart;
    
    /**
     * @var CarbonInterface Analytics period end date
     */
    private CarbonInterface $analyticsPeriodEnd;

    // ================================================================
    // CONSTRUCTOR
    // ================================================================
    
    /**
     * Create a new PhotoAnalytics instance
     * 
     * @param ProfileId $photoId The photo identifier
     * @param array<string, mixed> $analyticsData Raw analytics data
     * 
     * @throws InvalidArgumentException If analytics data is invalid
     */
    public function __construct(ProfileId $photoId, array $analyticsData)
    {
        $this->photoId = $photoId;
        $this->validateAndSetAnalyticsData($analyticsData);
        $this->calculateDerivedMetrics();
        $this->setAnalyticsPeriod();
    }

    // ================================================================
    // GETTER METHODS
    // ================================================================
    
    /**
     * Get the photo identifier
     * 
     * @return ProfileId
     */
    public function getPhotoId(): ProfileId
    {
        return $this->photoId;
    }
    
    /**
     * Get total views count
     * 
     * @return int
     */
    public function getTotalViews(): int
    {
        return $this->totalViews;
    }
    
    /**
     * Get total likes count
     * 
     * @return int
     */
    public function getTotalLikes(): int
    {
        return $this->totalLikes;
    }
    
    /**
     * Get total dislikes count
     * 
     * @return int
     */
    public function getTotalDislikes(): int
    {
        return $this->totalDislikes;
    }
    
    /**
     * Get total shares count
     * 
     * @return int
     */
    public function getTotalShares(): int
    {
        return $this->totalShares;
    }
    
    /**
     * Get total profile visits from this photo
     * 
     * @return int
     */
    public function getTotalProfileVisits(): int
    {
        return $this->totalProfileVisits;
    }
    
    /**
     * Get total matches initiated from this photo
     * 
     * @return int
     */
    public function getTotalMatchesInitiated(): int
    {
        return $this->totalMatchesInitiated;
    }
    
    /**
     * Get total messages initiated from this photo
     * 
     * @return int
     */
    public function getTotalMessagesInitiated(): int
    {
        return $this->totalMessagesInitiated;
    }
    
    /**
     * Get calculated engagement rate
     * 
     * @return float
     */
    public function getEngagementRate(): float
    {
        return $this->engagementRate;
    }
    
    /**
     * Get calculated conversion rate
     * 
     * @return float
     */
    public function getConversionRate(): float
    {
        return $this->conversionRate;
    }
    
    /**
     * Get photo quality score
     * 
     * @return float
     */
    public function getQualityScore(): float
    {
        return $this->qualityScore;
    }
    
    /**
     * Get photo attractiveness score
     * 
     * @return float
     */
    public function getAttractivenessScore(): float
    {
        return $this->attractivenessScore;
    }
    
    /**
     * Get photo uniqueness score
     * 
     * @return float
     */
    public function getUniquenessScore(): float
    {
        return $this->uniquenessScore;
    }
    
    /**
     * Get photo authenticity score
     * 
     * @return float
     */
    public function getAuthenticityScore(): float
    {
        return $this->authenticityScore;
    }
    
    /**
     * Get report count
     * 
     * @return int
     */
    public function getReportCount(): int
    {
        return $this->reportCount;
    }
    
    /**
     * Get block count
     * 
     * @return int
     */
    public function getBlockCount(): int
    {
        return $this->blockCount;
    }
    
    /**
     * Get average view time in seconds
     * 
     * @return float
     */
    public function getAverageViewTime(): float
    {
        return $this->averageViewTime;
    }
    
    /**
     * Get view completion rate
     * 
     * @return float
     */
    public function getViewCompletionRate(): float
    {
        return $this->viewCompletionRate;
    }
    
    /**
     * Get views breakdown by time periods
     * 
     * @return array<string, int>
     */
    public function getViewsByPeriod(): array
    {
        return $this->viewsByPeriod;
    }
    
    /**
     * Get engagement breakdown by demographics
     * 
     * @return array<string, int>
     */
    public function getEngagementByDemographics(): array
    {
        return $this->engagementByDemographics;
    }
    
    /**
     * Get comparative metrics with similar photos
     * 
     * @return array<string, float>
     */
    public function getComparativeMetrics(): array
    {
        return $this->comparativeMetrics;
    }
    
    /**
     * Get A/B testing data
     * 
     * @return array<string, mixed>
     */
    public function getAbTestingData(): array
    {
        return $this->abTestingData;
    }
    
    /**
     * Get last updated timestamp
     * 
     * @return CarbonInterface
     */
    public function getLastUpdatedAt(): CarbonInterface
    {
        return $this->lastUpdatedAt;
    }
    
    /**
     * Get analytics period start date
     * 
     * @return CarbonInterface
     */
    public function getAnalyticsPeriodStart(): CarbonInterface
    {
        return $this->analyticsPeriodStart;
    }
    
    /**
     * Get analytics period end date
     * 
     * @return CarbonInterface
     */
    public function getAnalyticsPeriodEnd(): CarbonInterface
    {
        return $this->analyticsPeriodEnd;
    }

    // ================================================================
    // CALCULATED PROPERTIES
    // ================================================================
    
    /**
     * Check if photo has high engagement
     * 
     * @return bool
     */
    public function hasHighEngagement(): bool
    {
        return $this->engagementRate >= self::HIGH_ENGAGEMENT_THRESHOLD;
    }
    
    /**
     * Check if photo meets premium quality standards
     * 
     * @return bool
     */
    public function isPremiumQuality(): bool
    {
        return $this->qualityScore >= self::PREMIUM_QUALITY_THRESHOLD;
    }
    
    /**
     * Get overall performance score (0-100)
     * 
     * @return float
     */
    public function getOverallPerformanceScore(): float
    {
        $engagementWeight = 0.3;
        $conversionWeight = 0.3;
        $qualityWeight = 0.2;
        $authenticityWeight = 0.1;
        $uniquenessWeight = 0.1;
        
        $score = (
            ($this->engagementRate * 100) * $engagementWeight +
            ($this->conversionRate * 100) * $conversionWeight +
            ($this->qualityScore * 10) * $qualityWeight +
            ($this->authenticityScore * 10) * $authenticityWeight +
            ($this->uniquenessScore * 10) * $uniquenessWeight
        );
        
        return round($score, 2);
    }
    
    /**
     * Get trending score based on recent activity
     * 
     * @return float
     */
    public function getTrendingScore(): float
    {
        $recentViews = $this->viewsByPeriod['last_7_days'] ?? 0;
        $recentEngagement = $this->viewsByPeriod['last_7_days_likes'] ?? 0;
        
        $dailyAverage = $recentViews / 7;
        $trendScore = min($dailyAverage / self::MAX_DAILY_VIEWS_TREND, 1.0);
        
        return round($trendScore * 100, 2);
    }
    
    /**
     * Get risk score based on reports and blocks
     * 
     * @return float
     */
    public function getRiskScore(): float
    {
        $totalInteractions = $this->totalViews + $this->totalLikes + $this->totalShares;
        
        if ($totalInteractions === 0) {
            return 0.0;
        }
        
        $riskRatio = ($this->reportCount + $this->blockCount) / $totalInteractions;
        
        return round(min($riskRatio * 100, 100.0), 2);
    }
    
    /**
     * Get demographic performance summary
     * 
     * @return array<string, mixed>
     */
    public function getDemographicPerformance(): array
    {
        return [
            'top_performing_age_group' => $this->getTopPerformingDemographic('age_group'),
            'top_performing_gender' => $this->getTopPerformingDemographic('gender'),
            'top_performing_location' => $this->getTopPerformingDemographic('location'),
            'engagement_distribution' => $this->engagementByDemographics,
        ];
    }
    
    /**
     * Get optimization recommendations
     * 
     * @return array<string, mixed>
     */
    public function getOptimizationRecommendations(): array
    {
        $recommendations = [];
        
        if ($this->engagementRate < 0.05) {
            $recommendations[] = [
                'type' => 'engagement',
                'priority' => 'high',
                'message' => 'Low engagement rate. Consider improving photo quality or content.',
                'suggested_actions' => ['improve_lighting', 'better_composition', 'update_content']
            ];
        }
        
        if ($this->conversionRate < 0.02) {
            $recommendations[] = [
                'type' => 'conversion',
                'priority' => 'medium',
                'message' => 'Low conversion rate. Photo may not be compelling enough for matches.',
                'suggested_actions' => ['add_bio_context', 'show_personality', 'update_style']
            ];
        }
        
        if ($this->getRiskScore() > 10.0) {
            $recommendations[] = [
                'type' => 'content_moderation',
                'priority' => 'high',
                'message' => 'High risk score due to reports/blocks. Review photo content.',
                'suggested_actions' => ['review_content', 'remove_if_necessary', 'seek_feedback']
            ];
        }
        
        if ($this->averageViewTime < 3.0) {
            $recommendations[] = [
                'type' => 'engagement_time',
                'priority' => 'medium',
                'message' => 'Users spend little time viewing this photo. Consider making it more interesting.',
                'suggested_actions' => ['improve_visual_appeal', 'add_interesting_elements']
            ];
        }
        
        return $recommendations;
    }

    // ================================================================
    // FACTORY METHODS
    // ================================================================
    
    /**
     * Create PhotoAnalytics from database record
     * 
     * @param ProfileId $photoId
     * @param array<string, mixed> $record
     * @return self
     */
    public static function fromDatabaseRecord(ProfileId $photoId, array $record): self
    {
        return new self($photoId, $record);
    }
    
    /**
     * Create empty PhotoAnalytics for new photo
     * 
     * @param ProfileId $photoId
     * @return self
     */
    public static function empty(ProfileId $photoId): self
    {
        return new self($photoId, []);
    }
    
    /**
     * Create PhotoAnalytics with default values
     * 
     * @param ProfileId $photoId
     * @param array<string, mixed> $initialData
     * @return self
     */
    public static function withDefaults(ProfileId $photoId, array $initialData = []): self
    {
        $defaults = [
            'total_views' => 0,
            'total_likes' => 0,
            'total_dislikes' => 0,
            'total_shares' => 0,
            'total_profile_visits' => 0,
            'total_matches_initiated' => 0,
            'total_messages_initiated' => 0,
            'quality_score' => 7.0,
            'attractiveness_score' => 7.0,
            'uniqueness_score' => 7.0,
            'authenticity_score' => 8.0,
            'report_count' => 0,
            'block_count' => 0,
            'average_view_time' => 5.0,
            'view_completion_rate' => 0.8,
            'views_by_period' => [],
            'engagement_by_demographics' => [],
            'comparative_metrics' => [],
            'ab_testing_data' => [],
        ];
        
        return new self($photoId, array_merge($defaults, $initialData));
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
            'photo_id' => $this->photoId->toInt(),
            'total_views' => $this->totalViews,
            'total_likes' => $this->totalLikes,
            'total_dislikes' => $this->totalDislikes,
            'total_shares' => $this->totalShares,
            'total_profile_visits' => $this->totalProfileVisits,
            'total_matches_initiated' => $this->totalMatchesInitiated,
            'total_messages_initiated' => $this->totalMessagesInitiated,
            'engagement_rate' => $this->engagementRate,
            'conversion_rate' => $this->conversionRate,
            'quality_score' => $this->qualityScore,
            'attractiveness_score' => $this->attractivenessScore,
            'uniqueness_score' => $this->uniquenessScore,
            'authenticity_score' => $this->authenticityScore,
            'report_count' => $this->reportCount,
            'block_count' => $this->blockCount,
            'average_view_time' => $this->averageViewTime,
            'view_completion_rate' => $this->viewCompletionRate,
            'views_by_period' => $this->viewsByPeriod,
            'engagement_by_demographics' => $this->engagementByDemographics,
            'comparative_metrics' => $this->comparativeMetrics,
            'ab_testing_data' => $this->abTestingData,
            'last_updated_at' => $this->lastUpdatedAt->toISOString(),
            'analytics_period_start' => $this->analyticsPeriodStart->toISOString(),
            'analytics_period_end' => $this->analyticsPeriodEnd->toISOString(),
            'calculated_metrics' => [
                'overall_performance_score' => $this->getOverallPerformanceScore(),
                'trending_score' => $this->getTrendingScore(),
                'risk_score' => $this->getRiskScore(),
                'has_high_engagement' => $this->hasHighEngagement(),
                'is_premium_quality' => $this->isPremiumQuality(),
                'demographic_performance' => $this->getDemographicPerformance(),
                'optimization_recommendations' => $this->getOptimizationRecommendations(),
            ]
        ];
    }
    
    /**
     * Create from array representation
     * 
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $photoId = ProfileId::fromInt((int) $data['photo_id']);
        
        $analyticsData = $data;
        unset($analyticsData['photo_id']);
        
        return new self($photoId, $analyticsData);
    }

    // ================================================================
    // COMPARISON METHODS
    // ================================================================
    
    /**
     * Check if this analytics instance equals another
     * 
     * @param PhotoAnalytics $other
     * @return bool
     */
    public function equals(PhotoAnalytics $other): bool
    {
        return $this->photoId->equals($other->photoId) &&
               $this->totalViews === $other->totalViews &&
               $this->totalLikes === $other->totalLikes &&
               $this->engagementRate === $other->engagementRate &&
               $this->qualityScore === $other->qualityScore;
    }
    
    /**
     * Compare performance with another photo analytics
     * 
     * @param PhotoAnalytics $other
     * @return array<string, mixed>
     */
    public function compareWith(PhotoAnalytics $other): array
    {
        return [
            'engagement_rate_difference' => $this->engagementRate - $other->engagementRate,
            'conversion_rate_difference' => $this->conversionRate - $other->conversionRate,
            'quality_score_difference' => $this->qualityScore - $other->qualityScore,
            'performance_ranking' => $this->getOverallPerformanceScore() > $other->getOverallPerformanceScore() ? 'better' : 'worse',
            'relative_performance' => [
                'this_photo' => $this->getOverallPerformanceScore(),
                'other_photo' => $other->getOverallPerformanceScore(),
                'difference_percentage' => round((($this->getOverallPerformanceScore() - $other->getOverallPerformanceScore()) / $other->getOverallPerformanceScore()) * 100, 2)
            ]
        ];
    }

    // ================================================================
    // PRIVATE METHODS
    // ================================================================
    
    /**
     * Validate and set analytics data from input array
     * 
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    private function validateAndSetAnalyticsData(array $data): void
    {
        $this->totalViews = $this->validateInt($data['total_views'] ?? 0, 'total_views');
        $this->totalLikes = $this->validateInt($data['total_likes'] ?? 0, 'total_likes');
        $this->totalDislikes = $this->validateInt($data['total_dislikes'] ?? 0, 'total_dislikes');
        $this->totalShares = $this->validateInt($data['total_shares'] ?? 0, 'total_shares');
        $this->totalProfileVisits = $this->validateInt($data['total_profile_visits'] ?? 0, 'total_profile_visits');
        $this->totalMatchesInitiated = $this->validateInt($data['total_matches_initiated'] ?? 0, 'total_matches_initiated');
        $this->totalMessagesInitiated = $this->validateInt($data['total_messages_initiated'] ?? 0, 'total_messages_initiated');
        
        $this->qualityScore = $this->validateFloat($data['quality_score'] ?? 7.0, 'quality_score', 0, 10);
        $this->attractivenessScore = $this->validateFloat($data['attractiveness_score'] ?? 7.0, 'attractiveness_score', 0, 10);
        $this->uniquenessScore = $this->validateFloat($data['uniqueness_score'] ?? 7.0, 'uniqueness_score', 0, 10);
        $this->authenticityScore = $this->validateFloat($data['authenticity_score'] ?? 8.0, 'authenticity_score', 0, 10);
        
        $this->reportCount = $this->validateInt($data['report_count'] ?? 0, 'report_count');
        $this->blockCount = $this->validateInt($data['block_count'] ?? 0, 'block_count');
        
        $this->averageViewTime = $this->validateFloat($data['average_view_time'] ?? 5.0, 'average_view_time', 0);
        $this->viewCompletionRate = $this->validateFloat($data['view_completion_rate'] ?? 0.8, 'view_completion_rate', 0, 1);
        
        $this->viewsByPeriod = $this->validateArray($data['views_by_period'] ?? [], 'views_by_period');
        $this->engagementByDemographics = $this->validateArray($data['engagement_by_demographics'] ?? [], 'engagement_by_demographics');
        $this->comparativeMetrics = $this->validateArray($data['comparative_metrics'] ?? [], 'comparative_metrics');
        $this->abTestingData = $this->validateArray($data['ab_testing_data'] ?? [], 'ab_testing_data');
        
        $this->lastUpdatedAt = $this->validateCarbon($data['last_updated_at'] ?? null, 'last_updated_at');
    }
    
    /**
     * Calculate derived metrics from base data
     */
    private function calculateDerivedMetrics(): void
    {
        // Calculate engagement rate
        $this->engagementRate = $this->totalViews > 0 
            ? ($this->totalLikes + $this->totalShares) / $this->totalViews 
            : 0.0;
        
        // Calculate conversion rate
        $this->conversionRate = $this->totalViews > 0 
            ? ($this->totalMatchesInitiated + $this->totalMessagesInitiated) / $this->totalViews 
            : 0.0;
    }
    
    /**
     * Set analytics period dates
     */
    private function setAnalyticsPeriod(): void
    {
        $now = now();
        $this->analyticsPeriodStart = $now->copy()->subDays(self::STANDARD_ANALYTICS_PERIOD);
        $this->analyticsPeriodEnd = $now->copy();
    }
    
    /**
     * Get top performing demographic for a specific category
     * 
     * @param string $category
     * @return string|null
     */
    private function getTopPerformingDemographic(string $category): ?string
    {
        $demographicData = $this->engagementByDemographics[$category] ?? [];
        
        if (empty($demographicData) || !is_array($demographicData)) {
            return null;
        }
        
        arsort($demographicData);
        $firstKey = array_key_first($demographicData);
        return $firstKey !== null ? (string) $firstKey : null;
    }
    
    /**
     * Validate integer value
     * 
     * @param mixed $value
     * @param string $fieldName
     * @return int
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
     * Validate float value within range
     * 
     * @param mixed $value
     * @param string $fieldName
     * @param float $min
     * @param float|null $max
     * @return float
     * @throws InvalidArgumentException
     */
    private function validateFloat($value, string $fieldName, float $min = 0, ?float $max = null): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Invalid float value for field '{$fieldName}'");
        }
        
        $floatValue = (float) $value;
        
        if ($floatValue < $min) {
            throw new InvalidArgumentException("Field '{$fieldName}' cannot be less than {$min}");
        }
        
        if ($max !== null && $floatValue > $max) {
            throw new InvalidArgumentException("Field '{$fieldName}' cannot be greater than {$max}");
        }
        
        return $floatValue;
    }
    
    /**
     * Validate array value
     * 
     * @param mixed $value
     * @param string $fieldName
     * @return array
     * @throws InvalidArgumentException
     */
    private function validateArray($value, string $fieldName): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("Invalid array value for field '{$fieldName}'");
        }
        
        return $value;
    }
    
    /**
     * Validate Carbon date
     * 
     * @param mixed $value
     * @param string $fieldName
     * @return CarbonInterface
     * @throws InvalidArgumentException
     */
    private function validateCarbon($value, string $fieldName): CarbonInterface
    {
        if ($value === null) {
            return now();
        }
        
        if ($value instanceof CarbonInterface) {
            return $value;
        }
        
        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Invalid date value for field '{$fieldName}': {$e->getMessage()}");
        }
    }
}
