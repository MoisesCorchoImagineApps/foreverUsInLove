<?php

declare(strict_types=1);

namespace App\Domain\Profile\ValueObjects;

use Carbon\CarbonInterface;
use InvalidArgumentException;
use JsonSerializable;

/**
 * PhotoEngagementMetrics Value Object
 * 
 * Represents comprehensive engagement metrics for a photo within the ForeverUsInLove dating platform.
 * Encapsulates user interaction data, performance indicators, and behavioral analytics
 * following Domain-Driven Design principles and immutable value object patterns.
 * 
 * Key Responsibilities:
 * - Encapsulate photo engagement statistics
 * - Provide interaction rate calculations
 * - Track user behavior patterns
 * - Calculate performance indicators
 * - Support recommendation algorithms
 * - Ensure data integrity and validation
 * - Maintain compatibility with existing UserImage model
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
final class PhotoEngagementMetrics implements JsonSerializable
{
    // ================================================================
    // CONSTANTS
    // ================================================================
    
    /**
     * High engagement threshold (likes + shares) / views
     */
    private const HIGH_ENGAGEMENT_THRESHOLD = 0.15; // 15%
    
    /**
     * Excellent engagement threshold
     */
    private const EXCELLENT_ENGAGEMENT_THRESHOLD = 0.25; // 25%
    
    /**
     * Minimum views for reliable metrics
     */
    private const MIN_VIEWS_FOR_RELIABILITY = 10;
    
    /**
     * Viral threshold for shares
     */
    private const VIRAL_SHARE_THRESHOLD = 50;
    
    /**
     * High view time threshold in seconds
     */
    private const HIGH_VIEW_TIME_THRESHOLD = 10.0;

    // ================================================================
    // PROPERTIES
    // ================================================================
    
    /**
     * @var int Total number of views
     */
    private int $totalViews;
    
    /**
     * @var int Total number of likes
     */
    private int $totalLikes;
    
    /**
     * @var int Total number of dislikes
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
     * @var int Total number of reports against this photo
     */
    private int $totalReports;
    
    /**
     * @var int Total number of blocks/hides by users
     */
    private int $totalBlocks;
    
    /**
     * @var float Average time users spend viewing this photo (seconds)
     */
    private float $averageViewTime;
    
    /**
     * @var float Median view time (seconds)
     */
    private float $medianViewTime;
    
    /**
     * @var float View completion rate (0-1)
     */
    private float $viewCompletionRate;
    
    /**
     * @var float Bounce rate (users who leave immediately)
     */
    private float $bounceRate;
    
    /**
     * @var array<string, int> Views breakdown by time periods
     */
    private array $viewsByPeriod;
    
    /**
     * @var array<string, int> Engagement breakdown by user demographics
     */
    private array $engagementByDemographics;
    
    /**
     * @var array<string, int> Engagement breakdown by time of day
     */
    private array $engagementByTimeOfDay;
    
    /**
     * @var array<string, int> Engagement breakdown by day of week
     */
    private array $engagementByDayOfWeek;
    
    /**
     * @var array<string, float> Interaction rates by user type
     */
    private array $interactionRatesByUserType;
    
    /**
     * @var array<string, mixed> A/B testing data
     */
    private array $abTestingData;
    
    /**
     * @var CarbonInterface When metrics were last updated
     */
    private CarbonInterface $lastUpdatedAt;
    
    /**
     * @var CarbonInterface Metrics period start date
     */
    private CarbonInterface $metricsPeriodStart;
    
    /**
     * @var CarbonInterface Metrics period end date
     */
    private CarbonInterface $metricsPeriodEnd;

    // ================================================================
    // CONSTRUCTOR
    // ================================================================
    
    /**
     * Create a new PhotoEngagementMetrics instance
     * 
     * @param array<string, mixed> $metricsData Raw engagement metrics data
     * 
     * @throws InvalidArgumentException If metrics data is invalid
     */
    public function __construct(array $metricsData)
    {
        $this->validateAndSetMetricsData($metricsData);
        $this->setMetricsPeriod();
    }

    // ================================================================
    // GETTER METHODS
    // ================================================================
    
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
     * Get total reports count
     * 
     * @return int
     */
    public function getTotalReports(): int
    {
        return $this->totalReports;
    }
    
    /**
     * Get total blocks count
     * 
     * @return int
     */
    public function getTotalBlocks(): int
    {
        return $this->totalBlocks;
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
     * Get median view time in seconds
     * 
     * @return float
     */
    public function getMedianViewTime(): float
    {
        return $this->medianViewTime;
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
     * Get bounce rate
     * 
     * @return float
     */
    public function getBounceRate(): float
    {
        return $this->bounceRate;
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
     * Get engagement breakdown by time of day
     * 
     * @return array<string, int>
     */
    public function getEngagementByTimeOfDay(): array
    {
        return $this->engagementByTimeOfDay;
    }
    
    /**
     * Get engagement breakdown by day of week
     * 
     * @return array<string, int>
     */
    public function getEngagementByDayOfWeek(): array
    {
        return $this->engagementByDayOfWeek;
    }
    
    /**
     * Get interaction rates by user type
     * 
     * @return array<string, float>
     */
    public function getInteractionRatesByUserType(): array
    {
        return $this->interactionRatesByUserType;
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
     * Get metrics period start date
     * 
     * @return CarbonInterface
     */
    public function getMetricsPeriodStart(): CarbonInterface
    {
        return $this->metricsPeriodStart;
    }
    
    /**
     * Get metrics period end date
     * 
     * @return CarbonInterface
     */
    public function getMetricsPeriodEnd(): CarbonInterface
    {
        return $this->metricsPeriodEnd;
    }

    // ================================================================
    // CALCULATED PROPERTIES
    // ================================================================
    
    /**
     * Calculate engagement rate (likes + shares) / views
     * 
     * @return float
     */
    public function getEngagementRate(): float
    {
        if ($this->totalViews === 0) {
            return 0.0;
        }
        
        return round(($this->totalLikes + $this->totalShares) / $this->totalViews, 4);
    }
    
    /**
     * Calculate like rate (likes / views)
     * 
     * @return float
     */
    public function getLikeRate(): float
    {
        if ($this->totalViews === 0) {
            return 0.0;
        }
        
        return round($this->totalLikes / $this->totalViews, 4);
    }
    
    /**
     * Calculate share rate (shares / views)
     * 
     * @return float
     */
    public function getShareRate(): float
    {
        if ($this->totalViews === 0) {
            return 0.0;
        }
        
        return round($this->totalShares / $this->totalViews, 4);
    }
    
    /**
     * Calculate conversion rate (matches + messages) / views
     * 
     * @return float
     */
    public function getConversionRate(): float
    {
        if ($this->totalViews === 0) {
            return 0.0;
        }
        
        return round(($this->totalMatchesInitiated + $this->totalMessagesInitiated) / $this->totalViews, 4);
    }
    
    /**
     * Calculate profile visit rate (profile visits / views)
     * 
     * @return float
     */
    public function getProfileVisitRate(): float
    {
        if ($this->totalViews === 0) {
            return 0.0;
        }
        
        return round($this->totalProfileVisits / $this->totalViews, 4);
    }
    
    /**
     * Calculate risk score based on reports and blocks
     * 
     * @return float
     */
    public function getRiskScore(): float
    {
        $totalInteractions = $this->totalViews + $this->totalLikes + $this->totalShares;
        
        if ($totalInteractions === 0) {
            return 0.0;
        }
        
        $riskRatio = ($this->totalReports + $this->totalBlocks) / $totalInteractions;
        
        return round(min($riskRatio * 100, 100.0), 2);
    }
    
    /**
     * Check if photo has high engagement
     * 
     * @return bool
     */
    public function hasHighEngagement(): bool
    {
        return $this->getEngagementRate() >= self::HIGH_ENGAGEMENT_THRESHOLD;
    }
    
    /**
     * Check if photo has excellent engagement
     * 
     * @return bool
     */
    public function hasExcellentEngagement(): bool
    {
        return $this->getEngagementRate() >= self::EXCELLENT_ENGAGEMENT_THRESHOLD;
    }
    
    /**
     * Check if metrics are reliable (enough views)
     * 
     * @return bool
     */
    public function hasReliableMetrics(): bool
    {
        return $this->totalViews >= self::MIN_VIEWS_FOR_RELIABILITY;
    }
    
    /**
     * Check if photo is going viral
     * 
     * @return bool
     */
    public function isGoingViral(): bool
    {
        return $this->totalShares >= self::VIRAL_SHARE_THRESHOLD;
    }
    
    /**
     * Check if photo has high view time
     * 
     * @return bool
     */
    public function hasHighViewTime(): bool
    {
        return $this->averageViewTime >= self::HIGH_VIEW_TIME_THRESHOLD;
    }
    
    /**
     * Check if photo has high risk
     * 
     * @return bool
     */
    public function hasHighRisk(): bool
    {
        return $this->getRiskScore() > 10.0;
    }
    
    /**
     * Get overall performance score (0-100)
     * 
     * @return float
     */
    public function getOverallPerformanceScore(): float
    {
        $engagementWeight = 0.4;
        $conversionWeight = 0.3;
        $viewTimeWeight = 0.2;
        $riskWeight = 0.1;
        
        $engagementScore = min($this->getEngagementRate() * 100, 100);
        $conversionScore = min($this->getConversionRate() * 100, 100);
        $viewTimeScore = min(($this->averageViewTime / 20) * 100, 100); // Normalize to 20 seconds max
        $riskScore = max(100 - $this->getRiskScore(), 0);
        
        $score = (
            $engagementScore * $engagementWeight +
            $conversionScore * $conversionWeight +
            $viewTimeScore * $viewTimeWeight +
            $riskScore * $riskWeight
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
        $recentViews = $this->viewsByPeriod['last_24_hours'] ?? 0;
        $recentEngagement = $this->viewsByPeriod['last_24_hours_likes'] ?? 0;
        
        // Simple trending calculation based on recent activity
        $trendScore = ($recentViews * 0.7) + ($recentEngagement * 3); // Weight engagement higher
        
        return round(min($trendScore / 100, 100), 2); // Normalize to 0-100
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
     * Get time-based performance insights
     * 
     * @return array<string, mixed>
     */
    public function getTimeBasedInsights(): array
    {
        return [
            'peak_hour' => $this->getPeakTime('hour'),
            'peak_day' => $this->getPeakTime('day'),
            'engagement_by_time_of_day' => $this->engagementByTimeOfDay,
            'engagement_by_day_of_week' => $this->engagementByDayOfWeek,
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
        
        if ($this->getEngagementRate() < 0.05) {
            $recommendations[] = [
                'type' => 'engagement',
                'priority' => 'high',
                'message' => 'Low engagement rate. Consider improving photo quality or content.',
                'suggested_actions' => ['improve_lighting', 'better_composition', 'update_content']
            ];
        }
        
        if ($this->getConversionRate() < 0.02) {
            $recommendations[] = [
                'type' => 'conversion',
                'priority' => 'medium',
                'message' => 'Low conversion rate. Photo may not be compelling enough for matches.',
                'suggested_actions' => ['add_bio_context', 'show_personality', 'update_style']
            ];
        }
        
        if ($this->hasHighRisk()) {
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
        
        if ($this->bounceRate > 0.7) {
            $recommendations[] = [
                'type' => 'retention',
                'priority' => 'medium',
                'message' => 'High bounce rate. Users are leaving quickly after viewing.',
                'suggested_actions' => ['improve_first_impression', 'better_thumbnail', 'optimize_loading']
            ];
        }
        
        return $recommendations;
    }

    // ================================================================
    // FACTORY METHODS
    // ================================================================
    
    /**
     * Create PhotoEngagementMetrics from database record
     * 
     * @param array<string, mixed> $record
     * @return self
     */
    public static function fromDatabaseRecord(array $record): self
    {
        return new self($record);
    }
    
    /**
     * Create PhotoEngagementMetrics from UserImage model
     * 
     * @param \App\Models\User\UserImage $userImage
     * @return self
     */
    public static function fromUserImage(\App\Models\User\UserImage $userImage): self
    {
        $metricsData = [
            'total_views' => $userImage->views_count ?? 0,
            'total_likes' => $userImage->likes_count ?? 0,
            'total_dislikes' => 0,
            'total_shares' => 0,
            'total_profile_visits' => 0,
            'total_matches_initiated' => 0,
            'total_messages_initiated' => 0,
            'total_reports' => 0,
            'total_blocks' => 0,
            'average_view_time' => 5.0,
            'median_view_time' => 5.0,
            'view_completion_rate' => 0.8,
            'bounce_rate' => 0.2,
            'views_by_period' => [],
            'engagement_by_demographics' => [],
            'engagement_by_time_of_day' => [],
            'engagement_by_day_of_week' => [],
            'interaction_rates_by_user_type' => [],
            'ab_testing_data' => [],
        ];
        
        return new self($metricsData);
    }
    
    /**
     * Create empty PhotoEngagementMetrics for new photo
     * 
     * @return self
     */
    public static function empty(): self
    {
        return new self([]);
    }
    
    /**
     * Create PhotoEngagementMetrics with default values
     * 
     * @param array<string, mixed> $initialData
     * @return self
     */
    public static function withDefaults(array $initialData = []): self
    {
        $defaults = [
            'total_views' => 0,
            'total_likes' => 0,
            'total_dislikes' => 0,
            'total_shares' => 0,
            'total_profile_visits' => 0,
            'total_matches_initiated' => 0,
            'total_messages_initiated' => 0,
            'total_reports' => 0,
            'total_blocks' => 0,
            'average_view_time' => 5.0,
            'median_view_time' => 5.0,
            'view_completion_rate' => 0.8,
            'bounce_rate' => 0.2,
            'views_by_period' => [],
            'engagement_by_demographics' => [],
            'engagement_by_time_of_day' => [],
            'engagement_by_day_of_week' => [],
            'interaction_rates_by_user_type' => [],
            'ab_testing_data' => [],
        ];
        
        return new self(array_merge($defaults, $initialData));
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
            'total_views' => $this->totalViews,
            'total_likes' => $this->totalLikes,
            'total_dislikes' => $this->totalDislikes,
            'total_shares' => $this->totalShares,
            'total_profile_visits' => $this->totalProfileVisits,
            'total_matches_initiated' => $this->totalMatchesInitiated,
            'total_messages_initiated' => $this->totalMessagesInitiated,
            'total_reports' => $this->totalReports,
            'total_blocks' => $this->totalBlocks,
            'average_view_time' => $this->averageViewTime,
            'median_view_time' => $this->medianViewTime,
            'view_completion_rate' => $this->viewCompletionRate,
            'bounce_rate' => $this->bounceRate,
            'views_by_period' => $this->viewsByPeriod,
            'engagement_by_demographics' => $this->engagementByDemographics,
            'engagement_by_time_of_day' => $this->engagementByTimeOfDay,
            'engagement_by_day_of_week' => $this->engagementByDayOfWeek,
            'interaction_rates_by_user_type' => $this->interactionRatesByUserType,
            'ab_testing_data' => $this->abTestingData,
            'last_updated_at' => $this->lastUpdatedAt->toISOString(),
            'metrics_period_start' => $this->metricsPeriodStart->toISOString(),
            'metrics_period_end' => $this->metricsPeriodEnd->toISOString(),
            'calculated_metrics' => [
                'engagement_rate' => $this->getEngagementRate(),
                'like_rate' => $this->getLikeRate(),
                'share_rate' => $this->getShareRate(),
                'conversion_rate' => $this->getConversionRate(),
                'profile_visit_rate' => $this->getProfileVisitRate(),
                'risk_score' => $this->getRiskScore(),
                'overall_performance_score' => $this->getOverallPerformanceScore(),
                'trending_score' => $this->getTrendingScore(),
                'has_high_engagement' => $this->hasHighEngagement(),
                'has_excellent_engagement' => $this->hasExcellentEngagement(),
                'has_reliable_metrics' => $this->hasReliableMetrics(),
                'is_going_viral' => $this->isGoingViral(),
                'has_high_view_time' => $this->hasHighViewTime(),
                'has_high_risk' => $this->hasHighRisk(),
                'demographic_performance' => $this->getDemographicPerformance(),
                'time_based_insights' => $this->getTimeBasedInsights(),
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
        return new self($data);
    }

    // ================================================================
    // COMPARISON METHODS
    // ================================================================
    
    /**
     * Check if this metrics instance equals another
     * 
     * @param PhotoEngagementMetrics $other
     * @return bool
     */
    public function equals(PhotoEngagementMetrics $other): bool
    {
        return $this->totalViews === $other->totalViews &&
               $this->totalLikes === $other->totalLikes &&
               $this->totalShares === $other->totalShares &&
               $this->averageViewTime === $other->averageViewTime;
    }
    
    /**
     * Compare performance with another photo metrics
     * 
     * @param PhotoEngagementMetrics $other
     * @return array<string, mixed>
     */
    public function compareWith(PhotoEngagementMetrics $other): array
    {
        return [
            'engagement_rate_difference' => $this->getEngagementRate() - $other->getEngagementRate(),
            'conversion_rate_difference' => $this->getConversionRate() - $other->getConversionRate(),
            'view_time_difference' => $this->getAverageViewTime() - $other->getAverageViewTime(),
            'performance_ranking' => $this->getOverallPerformanceScore() > $other->getOverallPerformanceScore() ? 'better' : 'worse',
            'relative_performance' => [
                'this_photo' => $this->getOverallPerformanceScore(),
                'other_photo' => $other->getOverallPerformanceScore(),
                'difference_percentage' => $other->getOverallPerformanceScore() > 0 
                    ? round((($this->getOverallPerformanceScore() - $other->getOverallPerformanceScore()) / $other->getOverallPerformanceScore()) * 100, 2)
                    : 0
            ]
        ];
    }

    // ================================================================
    // PRIVATE METHODS
    // ================================================================
    
    /**
     * Validate and set metrics data from input array
     * 
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    private function validateAndSetMetricsData(array $data): void
    {
        $this->totalViews = $this->validateInt($data['total_views'] ?? 0, 'total_views');
        $this->totalLikes = $this->validateInt($data['total_likes'] ?? 0, 'total_likes');
        $this->totalDislikes = $this->validateInt($data['total_dislikes'] ?? 0, 'total_dislikes');
        $this->totalShares = $this->validateInt($data['total_shares'] ?? 0, 'total_shares');
        $this->totalProfileVisits = $this->validateInt($data['total_profile_visits'] ?? 0, 'total_profile_visits');
        $this->totalMatchesInitiated = $this->validateInt($data['total_matches_initiated'] ?? 0, 'total_matches_initiated');
        $this->totalMessagesInitiated = $this->validateInt($data['total_messages_initiated'] ?? 0, 'total_messages_initiated');
        $this->totalReports = $this->validateInt($data['total_reports'] ?? 0, 'total_reports');
        $this->totalBlocks = $this->validateInt($data['total_blocks'] ?? 0, 'total_blocks');
        
        $this->averageViewTime = $this->validateFloat($data['average_view_time'] ?? 5.0, 'average_view_time', 0);
        $this->medianViewTime = $this->validateFloat($data['median_view_time'] ?? 5.0, 'median_view_time', 0);
        $this->viewCompletionRate = $this->validateFloat($data['view_completion_rate'] ?? 0.8, 'view_completion_rate', 0, 1);
        $this->bounceRate = $this->validateFloat($data['bounce_rate'] ?? 0.2, 'bounce_rate', 0, 1);
        
        $this->viewsByPeriod = $this->validateArray($data['views_by_period'] ?? [], 'views_by_period');
        $this->engagementByDemographics = $this->validateArray($data['engagement_by_demographics'] ?? [], 'engagement_by_demographics');
        $this->engagementByTimeOfDay = $this->validateArray($data['engagement_by_time_of_day'] ?? [], 'engagement_by_time_of_day');
        $this->engagementByDayOfWeek = $this->validateArray($data['engagement_by_day_of_week'] ?? [], 'engagement_by_day_of_week');
        $this->interactionRatesByUserType = $this->validateArray($data['interaction_rates_by_user_type'] ?? [], 'interaction_rates_by_user_type');
        $this->abTestingData = $this->validateArray($data['ab_testing_data'] ?? [], 'ab_testing_data');
        
        $this->lastUpdatedAt = $this->validateCarbon($data['last_updated_at'] ?? null, 'last_updated_at');
    }
    
    /**
     * Set metrics period dates
     */
    private function setMetricsPeriod(): void
    {
        $now = now();
        $this->metricsPeriodStart = $now->copy()->subDays(30); // Default 30-day period
        $this->metricsPeriodEnd = $now->copy();
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
     * Get peak time for a specific category
     * 
     * @param string $category
     * @return string|null
     */
    private function getPeakTime(string $category): ?string
    {
        $timeData = $category === 'hour' ? $this->engagementByTimeOfDay : $this->engagementByDayOfWeek;
        
        if (empty($timeData) || !is_array($timeData)) {
            return null;
        }
        
        arsort($timeData);
        $firstKey = array_key_first($timeData);
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

    // ================================================================
    // COMPATIBILITY METHODS
    // ================================================================
    
    /**
     * Convert to format compatible with UserImage model
     * 
     * @return array<string, mixed>
     */
    public function toUserImageFormat(): array
    {
        return [
            'views_count' => $this->getTotalViews(),
            'likes_count' => $this->getTotalLikes(),
        ];
    }
    
    /**
     * Convert to format compatible with PhotoRepositoryInterface
     * 
     * @return array<string, mixed>
     */
    public function toRepositoryFormat(): array
    {
        return [
            'total_views' => $this->totalViews,
            'total_likes' => $this->totalLikes,
            'total_dislikes' => $this->totalDislikes,
            'total_shares' => $this->totalShares,
            'total_profile_visits' => $this->totalProfileVisits,
            'total_matches_initiated' => $this->totalMatchesInitiated,
            'total_messages_initiated' => $this->totalMessagesInitiated,
            'total_reports' => $this->totalReports,
            'total_blocks' => $this->totalBlocks,
            'average_view_time' => $this->averageViewTime,
            'median_view_time' => $this->medianViewTime,
            'view_completion_rate' => $this->viewCompletionRate,
            'bounce_rate' => $this->bounceRate,
            'views_by_period' => $this->viewsByPeriod,
            'engagement_by_demographics' => $this->engagementByDemographics,
            'engagement_by_time_of_day' => $this->engagementByTimeOfDay,
            'engagement_by_day_of_week' => $this->engagementByDayOfWeek,
            'interaction_rates_by_user_type' => $this->interactionRatesByUserType,
            'ab_testing_data' => $this->abTestingData,
            'last_updated_at' => $this->lastUpdatedAt,
        ];
    }

    // ================================================================
    // MAGIC METHODS
    // ================================================================
    
    /**
     * String representation
     * 
     * @return string
     */
    public function __toString(): string
    {
        return "PhotoEngagementMetrics(Views: {$this->totalViews}, Likes: {$this->totalLikes}, Engagement: " . 
               round($this->getEngagementRate() * 100, 1) . "%)";
    }
    
    /**
     * JSON serialization
     * 
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
    
    /**
     * Debug information
     * 
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'total_views' => $this->totalViews,
            'total_likes' => $this->totalLikes,
            'total_shares' => $this->totalShares,
            'engagement_rate' => $this->getEngagementRate(),
            'conversion_rate' => $this->getConversionRate(),
            'average_view_time' => $this->averageViewTime,
            'overall_performance_score' => $this->getOverallPerformanceScore(),
            'risk_score' => $this->getRiskScore(),
        ];
    }
}
