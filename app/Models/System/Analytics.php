<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class Analytics
 *
 * Eloquent Model for tracking and storing analytics metrics and events.
 * Supports user events, revenue tracking, engagement metrics, conversions, and retention analytics.
 *
 * @package App\Models\System
 * 
 * @property int $id
 * @property string $metric_id Unique metric identifier (UUID)
 * @property string $metric_name Name of the metric
 * @property string $metric_type Type of metric (user_event, revenue, engagement, conversion, retention)
 * @property float|int $value Numeric value of the metric
 * @property array|null $dimensions Dimensional breakdown (e.g., country, device, plan)
 * @property array|null $tags Categorization tags
 * @property \Illuminate\Support\Carbon $recorded_at When metric was recorded
 * @property string $period Period granularity (minute, hour, day, week, month, year)
 * @property string $aggregation_type Type of aggregation (sum, count, avg, min, max, distinct)
 * @property array|null $metadata Additional contextual data
 * @property int|null $user_id Related user ID
 * @property string|null $session_id Session identifier
 * @property string|null $event_name Specific event name
 * @property array|null $event_properties Event properties
 * @property float|null $revenue_amount Revenue amount (for revenue metrics)
 * @property string|null $currency Currency code
 * @property string|null $cohort_id Cohort identifier
 * @property string|null $experiment_id A/B test experiment ID
 * @property string|null $variant_id A/B test variant ID
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Analytics byMetricType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder|Analytics byPeriod(string $period)
 * @method static \Illuminate\Database\Eloquent\Builder|Analytics byDateRange(\Carbon\Carbon $start, \Carbon\Carbon $end)
 * @method static \Illuminate\Database\Eloquent\Builder|Analytics byUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder|Analytics byCohort(string $cohortId)
 * @method static \Illuminate\Database\Eloquent\Builder|Analytics byExperiment(string $experimentId)
 * @method static \Illuminate\Database\Eloquent\Builder|Analytics withDimension(string $key, string $value)
 * @method static \Illuminate\Database\Eloquent\Builder|Analytics withTag(string $tag)
 */
class Analytics extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'analytics';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'metric_id',
        'metric_name',
        'metric_type',
        'value',
        'dimensions',
        'tags',
        'recorded_at',
        'period',
        'aggregation_type',
        'metadata',
        'user_id',
        'session_id',
        'event_name',
        'event_properties',
        'revenue_amount',
        'currency',
        'cohort_id',
        'experiment_id',
        'variant_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'float',
        'dimensions' => 'array',
        'tags' => 'array',
        'recorded_at' => 'datetime',
        'metadata' => 'array',
        'user_id' => 'integer',
        'event_properties' => 'array',
        'revenue_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be indexed for searching.
     *
     * @var array<string>
     */
    protected $searchable = [
        'metric_name',
        'event_name',
    ];

    // ==================== CONSTANTS ====================

    /**
     * Metric types
     */
    const TYPE_USER_EVENT = 'user_event';
    const TYPE_REVENUE = 'revenue';
    const TYPE_ENGAGEMENT = 'engagement';
    const TYPE_CONVERSION = 'conversion';
    const TYPE_RETENTION = 'retention';
    const TYPE_PERFORMANCE = 'performance';
    const TYPE_ERROR = 'error';

    /**
     * Period granularities
     */
    const PERIOD_MINUTE = 'minute';
    const PERIOD_HOUR = 'hour';
    const PERIOD_DAY = 'day';
    const PERIOD_WEEK = 'week';
    const PERIOD_MONTH = 'month';
    const PERIOD_QUARTER = 'quarter';
    const PERIOD_YEAR = 'year';

    /**
     * Aggregation types
     */
    const AGG_SUM = 'sum';
    const AGG_COUNT = 'count';
    const AGG_AVG = 'avg';
    const AGG_MIN = 'min';
    const AGG_MAX = 'max';
    const AGG_DISTINCT = 'distinct';
    const AGG_PERCENTILE = 'percentile';

    /**
     * Common event names
     */
    const EVENT_USER_REGISTERED = 'user.registered';
    const EVENT_USER_LOGIN = 'user.login';
    const EVENT_USER_LOGOUT = 'user.logout';
    const EVENT_PROFILE_VIEWED = 'profile.viewed';
    const EVENT_PROFILE_UPDATED = 'profile.updated';
    const EVENT_MATCH_CREATED = 'match.created';
    const EVENT_MESSAGE_SENT = 'message.sent';
    const EVENT_PURCHASE_COMPLETED = 'purchase.completed';
    const EVENT_SUBSCRIPTION_STARTED = 'subscription.started';
    const EVENT_SUBSCRIPTION_CANCELLED = 'subscription.cancelled';
    const EVENT_VIDEO_CALL_STARTED = 'video_call.started';
    const EVENT_GIFT_SENT = 'gift.sent';

    /**
     * Common dimension keys
     */
    const DIM_COUNTRY = 'country';
    const DIM_CITY = 'city';
    const DIM_DEVICE_TYPE = 'device_type';
    const DIM_OS = 'operating_system';
    const DIM_BROWSER = 'browser';
    const DIM_APP_VERSION = 'app_version';
    const DIM_USER_SEGMENT = 'user_segment';
    const DIM_SUBSCRIPTION_PLAN = 'subscription_plan';
    const DIM_TRAFFIC_SOURCE = 'traffic_source';
    const DIM_CAMPAIGN = 'campaign';

    /**
     * Cache configuration
     */
    const CACHE_PREFIX = 'analytics:';
    const CACHE_TTL = 300; // 5 minutes (analytics data changes frequently)
    const CACHE_TAG = 'analytics';

    // ==================== QUERY SCOPES ====================

    /**
     * Scope a query to filter by metric type.
     *
     * @param Builder $query
     * @param string $type
     * @return Builder
     */
    public function scopeByMetricType(Builder $query, string $type): Builder
    {
        return $query->where('metric_type', $type);
    }

    /**
     * Scope a query to filter by period.
     *
     * @param Builder $query
     * @param string $period
     * @return Builder
     */
    public function scopeByPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    /**
     * Scope a query to filter by date range.
     *
     * @param Builder $query
     * @param \Carbon\Carbon $start
     * @param \Carbon\Carbon $end
     * @return Builder
     */
    public function scopeByDateRange(Builder $query, \Carbon\Carbon $start, \Carbon\Carbon $end): Builder
    {
        return $query->whereBetween('recorded_at', [$start, $end]);
    }

    /**
     * Scope a query to filter by user.
     *
     * @param Builder $query
     * @param int $userId
     * @return Builder
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by cohort.
     *
     * @param Builder $query
     * @param string $cohortId
     * @return Builder
     */
    public function scopeByCohort(Builder $query, string $cohortId): Builder
    {
        return $query->where('cohort_id', $cohortId);
    }

    /**
     * Scope a query to filter by A/B test experiment.
     *
     * @param Builder $query
     * @param string $experimentId
     * @return Builder
     */
    public function scopeByExperiment(Builder $query, string $experimentId): Builder
    {
        return $query->where('experiment_id', $experimentId);
    }

    /**
     * Scope a query to filter by dimension.
     *
     * @param Builder $query
     * @param string $key
     * @param string $value
     * @return Builder
     */
    public function scopeWithDimension(Builder $query, string $key, string $value): Builder
    {
        return $query->whereJsonContains("dimensions->{$key}", $value);
    }

    /**
     * Scope a query to filter by tag.
     *
     * @param Builder $query
     * @param string $tag
     * @return Builder
     */
    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    // ==================== ACCESSORS & MUTATORS ====================

    /**
     * Set the metric_id with UUID generation.
     *
     * @param string|null $value
     * @return void
     */
    public function setMetricIdAttribute(?string $value): void
    {
        $this->attributes['metric_id'] = $value ?? (string) Str::uuid();
    }

    /**
     * Get formatted value based on metric type.
     *
     * @return mixed
     */
    public function getFormattedValueAttribute()
    {
        return match ($this->metric_type) {
            self::TYPE_REVENUE => '$' . number_format($this->value, 2),
            self::TYPE_ENGAGEMENT => number_format($this->value, 1) . '%',
            self::TYPE_CONVERSION => number_format($this->value, 2) . '%',
            default => number_format($this->value, 0),
        };
    }

    // ==================== STATIC METHODS ====================

    /**
     * Record a new analytics metric.
     *
     * @param array $data
     * @return Analytics
     */
    public static function record(array $data): Analytics
    {
        $metric = new self();
        
        // Set required fields
        $metric->metric_name = $data['metric_name'];
        $metric->metric_type = $data['metric_type'];
        $metric->value = $data['value'];
        $metric->recorded_at = $data['recorded_at'] ?? now();
        $metric->period = $data['period'] ?? self::PERIOD_DAY;
        $metric->aggregation_type = $data['aggregation_type'] ?? self::AGG_SUM;

        // Set optional fields
        if (isset($data['dimensions'])) {
            $metric->dimensions = $data['dimensions'];
        }

        if (isset($data['tags'])) {
            $metric->tags = $data['tags'];
        }

        if (isset($data['metadata'])) {
            $metric->metadata = $data['metadata'];
        }

        if (isset($data['user_id'])) {
            $metric->user_id = $data['user_id'];
        }

        if (isset($data['session_id'])) {
            $metric->session_id = $data['session_id'];
        }

        if (isset($data['event_name'])) {
            $metric->event_name = $data['event_name'];
        }

        if (isset($data['event_properties'])) {
            $metric->event_properties = $data['event_properties'];
        }

        if (isset($data['revenue_amount'])) {
            $metric->revenue_amount = $data['revenue_amount'];
            $metric->currency = $data['currency'] ?? 'USD';
        }

        if (isset($data['cohort_id'])) {
            $metric->cohort_id = $data['cohort_id'];
        }

        if (isset($data['experiment_id'])) {
            $metric->experiment_id = $data['experiment_id'];
            $metric->variant_id = $data['variant_id'] ?? null;
        }

        $metric->save();

        // Clear relevant caches
        self::clearCache($metric->metric_type);

        return $metric;
    }

    /**
     * Aggregate metrics by dimension and period.
     *
     * @param string $metricName
     * @param string $aggregationType
     * @param string $period
     * @param \Carbon\Carbon $start
     * @param \Carbon\Carbon $end
     * @param array $dimensions
     * @return array
     */
    public static function aggregate(
        string $metricName,
        string $aggregationType,
        string $period,
        \Carbon\Carbon $start,
        \Carbon\Carbon $end,
        array $dimensions = []
    ): array {
        $cacheKey = self::CACHE_PREFIX . "agg:{$metricName}:{$aggregationType}:{$period}:" . 
                    $start->format('Y-m-d') . ':' . $end->format('Y-m-d') . ':' . md5(json_encode($dimensions));

        return Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use (
            $metricName, $aggregationType, $period, $start, $end, $dimensions
        ) {
            $query = self::where('metric_name', $metricName)
                        ->byPeriod($period)
                        ->byDateRange($start, $end);

            // Apply dimension filters
            foreach ($dimensions as $key => $value) {
                $query->withDimension($key, $value);
            }

            // Perform aggregation
            $result = match ($aggregationType) {
                self::AGG_SUM => $query->sum('value'),
                self::AGG_COUNT => $query->count(),
                self::AGG_AVG => $query->avg('value'),
                self::AGG_MIN => $query->min('value'),
                self::AGG_MAX => $query->max('value'),
                self::AGG_DISTINCT => $query->distinct('user_id')->count('user_id'),
                default => 0,
            };

            return [
                'metric_name' => $metricName,
                'aggregation_type' => $aggregationType,
                'period' => $period,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'dimensions' => $dimensions,
                'value' => $result,
            ];
        });
    }

    /**
     * Get metrics by period with time series data.
     *
     * @param string $metricName
     * @param string $period
     * @param \Carbon\Carbon $start
     * @param \Carbon\Carbon $end
     * @return array
     */
    public static function getByPeriod(
        string $metricName,
        string $period,
        \Carbon\Carbon $start,
        \Carbon\Carbon $end
    ): array {
        $cacheKey = self::CACHE_PREFIX . "period:{$metricName}:{$period}:" . 
                    $start->format('Y-m-d') . ':' . $end->format('Y-m-d');

        return Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use (
            $metricName, $period, $start, $end
        ) {
            return self::where('metric_name', $metricName)
                      ->byPeriod($period)
                      ->byDateRange($start, $end)
                      ->orderBy('recorded_at')
                      ->get()
                      ->groupBy(function ($metric) use ($period) {
                          return $metric->recorded_at->format(self::getPeriodFormat($period));
                      })
                      ->map(function ($group) {
                          return [
                              'date' => $group->first()->recorded_at->toDateString(),
                              'total' => $group->sum('value'),
                              'count' => $group->count(),
                              'avg' => $group->avg('value'),
                              'min' => $group->min('value'),
                              'max' => $group->max('value'),
                          ];
                      })
                      ->values()
                      ->toArray();
        });
    }

    /**
     * Get trending metrics.
     *
     * @param string $metricType
     * @param int $limit
     * @return array
     */
    public static function trending(string $metricType, int $limit = 10): array
    {
        $cacheKey = self::CACHE_PREFIX . "trending:{$metricType}:limit:{$limit}";

        return Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($metricType, $limit) {
            $now = now();
            $yesterday = $now->copy()->subDay();

            return self::byMetricType($metricType)
                      ->byDateRange($yesterday, $now)
                      ->select('metric_name', DB::raw('SUM(value) as total_value'), DB::raw('COUNT(*) as count'))
                      ->groupBy('metric_name')
                      ->orderByDesc('total_value')
                      ->limit($limit)
                      ->get()
                      ->toArray();
        });
    }

    /**
     * Compare two time periods.
     *
     * @param string $metricName
     * @param \Carbon\Carbon $currentStart
     * @param \Carbon\Carbon $currentEnd
     * @param \Carbon\Carbon $previousStart
     * @param \Carbon\Carbon $previousEnd
     * @return array
     */
    public static function compare(
        string $metricName,
        \Carbon\Carbon $currentStart,
        \Carbon\Carbon $currentEnd,
        \Carbon\Carbon $previousStart,
        \Carbon\Carbon $previousEnd
    ): array {
        $currentValue = self::where('metric_name', $metricName)
                           ->byDateRange($currentStart, $currentEnd)
                           ->sum('value');

        $previousValue = self::where('metric_name', $metricName)
                            ->byDateRange($previousStart, $previousEnd)
                            ->sum('value');

        $change = $previousValue > 0 
            ? (($currentValue - $previousValue) / $previousValue) * 100 
            : 0;

        return [
            'metric_name' => $metricName,
            'current_period' => [
                'start' => $currentStart->toDateString(),
                'end' => $currentEnd->toDateString(),
                'value' => $currentValue,
            ],
            'previous_period' => [
                'start' => $previousStart->toDateString(),
                'end' => $previousEnd->toDateString(),
                'value' => $previousValue,
            ],
            'change' => round($change, 2),
            'change_direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
        ];
    }

    /**
     * Get breakdown by dimension.
     *
     * @param string $metricName
     * @param string $dimensionKey
     * @param \Carbon\Carbon $start
     * @param \Carbon\Carbon $end
     * @return array
     */
    public static function getByDimension(
        string $metricName,
        string $dimensionKey,
        \Carbon\Carbon $start,
        \Carbon\Carbon $end
    ): array {
        $metrics = self::where('metric_name', $metricName)
                      ->byDateRange($start, $end)
                      ->whereNotNull('dimensions')
                      ->get();

        $breakdown = [];

        foreach ($metrics as $metric) {
            if (isset($metric->dimensions[$dimensionKey])) {
                $dimValue = $metric->dimensions[$dimensionKey];
                
                if (!isset($breakdown[$dimValue])) {
                    $breakdown[$dimValue] = [
                        'dimension_value' => $dimValue,
                        'total' => 0,
                        'count' => 0,
                    ];
                }

                $breakdown[$dimValue]['total'] += $metric->value;
                $breakdown[$dimValue]['count']++;
            }
        }

        return array_values($breakdown);
    }

    /**
     * Clear analytics cache.
     *
     * @param string|null $metricType
     * @return void
     */
    public static function clearCache(?string $metricType = null): void
    {
        Cache::tags([self::CACHE_TAG])->flush();
    }

    /**
     * Get period format for date grouping.
     *
     * @param string $period
     * @return string
     */
    protected static function getPeriodFormat(string $period): string
    {
        return match ($period) {
            self::PERIOD_MINUTE => 'Y-m-d H:i',
            self::PERIOD_HOUR => 'Y-m-d H:00',
            self::PERIOD_DAY => 'Y-m-d',
            self::PERIOD_WEEK => 'Y-W',
            self::PERIOD_MONTH => 'Y-m',
            self::PERIOD_QUARTER => 'Y-Q',
            self::PERIOD_YEAR => 'Y',
            default => 'Y-m-d',
        };
    }

    // ==================== MODEL EVENTS ====================

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate metric_id
        static::creating(function ($metric) {
            if (!$metric->metric_id) {
                $metric->metric_id = (string) Str::uuid();
            }

            // Set default recorded_at
            if (!$metric->recorded_at) {
                $metric->recorded_at = now();
            }
        });

        // Clear cache after saving
        static::saved(function ($metric) {
            self::clearCache($metric->metric_type);
        });
    }
}