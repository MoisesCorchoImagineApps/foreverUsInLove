<?php

declare(strict_types=1);

namespace App\Models\Chat;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class CallHistory
 *
 * Modelo de historial de llamadas con tracking de duración,
 * calidad, métricas de participación y ratings por usuario.
 *
 * @package App\Models\Chat
 *
 * @property int $id
 * @property int $video_call_id
 * @property int $user_id
 * @property string $call_type // private, group, conference, emergency
 * @property string $call_status // initiating, ringing, connecting, connected, ended, failed
 * @property string $user_role // caller, participant
 * @property int|null $duration_seconds
 * @property string|null $end_reason // normal, timeout, error, declined, busy, no_answer, canceled
 * @property float|null $quality_score
 * @property array|null $quality_metrics
 * @property array|null $engagement_metrics
 * @property int|null $rating // 1-5
 * @property string|null $feedback
 * @property array $metadata
 * @property array|null $participant_summary
 * @property bool $was_video_enabled
 * @property bool $was_audio_enabled
 * @property bool $was_screen_shared
 * @property bool $was_recorded
 * @property Carbon|null $call_started_at
 * @property Carbon|null $call_ended_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read VideoCall $videoCall
 * @property-read User $user
 */
class CallHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'call_histories';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'video_call_id',
        'user_id',
        'call_type',
        'call_status',
        'user_role',
        'duration_seconds',
        'end_reason',
        'quality_score',
        'quality_metrics',
        'engagement_metrics',
        'rating',
        'feedback',
        'metadata',
        'participant_summary',
        'was_video_enabled',
        'was_audio_enabled',
        'was_screen_shared',
        'was_recorded',
        'call_started_at',
        'call_ended_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'duration_seconds' => 'integer',
        'quality_score' => 'float',
        'quality_metrics' => 'array',
        'engagement_metrics' => 'array',
        'rating' => 'integer',
        'metadata' => 'array',
        'participant_summary' => 'array',
        'was_video_enabled' => 'boolean',
        'was_audio_enabled' => 'boolean',
        'was_screen_shared' => 'boolean',
        'was_recorded' => 'boolean',
        'call_started_at' => 'datetime',
        'call_ended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Call types
     */
    public const TYPE_PRIVATE = 'private';
    public const TYPE_GROUP = 'group';
    public const TYPE_CONFERENCE = 'conference';
    public const TYPE_EMERGENCY = 'emergency';

    /**
     * Call status
     */
    public const STATUS_INITIATING = 'initiating';
    public const STATUS_RINGING = 'ringing';
    public const STATUS_CONNECTING = 'connecting';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_ENDED = 'ended';
    public const STATUS_FAILED = 'failed';

    /**
     * End reasons
     */
    public const END_REASON_NORMAL = 'normal';
    public const END_REASON_TIMEOUT = 'timeout';
    public const END_REASON_ERROR = 'error';
    public const END_REASON_DECLINED = 'declined';
    public const END_REASON_BUSY = 'busy';
    public const END_REASON_NO_ANSWER = 'no_answer';
    public const END_REASON_CANCELED = 'canceled';

    /**
     * User roles
     */
    public const ROLE_CALLER = 'caller';
    public const ROLE_PARTICIPANT = 'participant';

    /**
     * Rating values (1-5)
     */
    public const RATING_MIN = 1;
    public const RATING_MAX = 5;

    /**
     * Quality score thresholds
     */
    public const QUALITY_EXCELLENT = 90;
    public const QUALITY_GOOD = 70;
    public const QUALITY_FAIR = 50;
    public const QUALITY_POOR = 0;

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CallHistory $history) {
            if (empty($history->metadata)) {
                $history->metadata = [
                    'created_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'platform' => self::detectPlatform(),
                ];
            }

            // Calculate engagement metrics if duration is present
            if ($history->duration_seconds) {
                $history->engagement_metrics = $history->calculateEngagementMetrics();
            }
        });
    }

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Get the video call this history belongs to
     */
    public function videoCall(): BelongsTo
    {
        return $this->belongsTo(VideoCall::class, 'video_call_id');
    }

    /**
     * Get the user this history belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope for successful calls (connected)
     */
    public function scopeSuccessful($query)
    {
        return $query->where('call_status', self::STATUS_CONNECTED);
    }

    /**
     * Scope for failed calls
     */
    public function scopeFailed($query)
    {
        return $query->where('call_status', self::STATUS_FAILED);
    }

    /**
     * Scope for missed calls
     */
    public function scopeMissed($query)
    {
        return $query->whereIn('end_reason', [
            self::END_REASON_NO_ANSWER,
            self::END_REASON_DECLINED,
            self::END_REASON_BUSY,
        ]);
    }

    /**
     * Scope for incoming calls (user is not caller)
     */
    public function scopeIncoming($query)
    {
        return $query->where('user_role', self::ROLE_PARTICIPANT);
    }

    /**
     * Scope for outgoing calls (user is caller)
     */
    public function scopeOutgoing($query)
    {
        return $query->where('user_role', self::ROLE_CALLER);
    }

    /**
     * Scope for private calls
     */
    public function scopePrivate($query)
    {
        return $query->where('call_type', self::TYPE_PRIVATE);
    }

    /**
     * Scope for group calls
     */
    public function scopeGroup($query)
    {
        return $query->whereIn('call_type', [self::TYPE_GROUP, self::TYPE_CONFERENCE]);
    }

    /**
     * Scope for rated calls
     */
    public function scopeRated($query)
    {
        return $query->whereNotNull('rating');
    }

    /**
     * Scope for calls with minimum duration (in seconds)
     */
    public function scopeWithMinDuration($query, int $seconds)
    {
        return $query->where('duration_seconds', '>=', $seconds);
    }

    /**
     * Scope for recent calls
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for recorded calls
     */
    public function scopeRecorded($query)
    {
        return $query->where('was_recorded', true);
    }

    /**
     * Scope for high quality calls
     */
    public function scopeHighQuality($query)
    {
        return $query->where('quality_score', '>=', self::QUALITY_EXCELLENT);
    }

    /**
     * Scope for poor quality calls
     */
    public function scopePoorQuality($query)
    {
        return $query->where('quality_score', '<', self::QUALITY_FAIR);
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if call was successful
     */
    public function wasSuccessful(): bool
    {
        return $this->call_status === self::STATUS_CONNECTED;
    }

    /**
     * Check if call was missed
     */
    public function wasMissed(): bool
    {
        return in_array($this->end_reason, [
            self::END_REASON_NO_ANSWER,
            self::END_REASON_DECLINED,
            self::END_REASON_BUSY,
        ]);
    }

    /**
     * Check if user was the caller
     */
    public function wasCaller(): bool
    {
        return $this->user_role === self::ROLE_CALLER;
    }

    /**
     * Check if call was private
     */
    public function wasPrivate(): bool
    {
        return $this->call_type === self::TYPE_PRIVATE;
    }

    /**
     * Check if call was group
     */
    public function wasGroup(): bool
    {
        return in_array($this->call_type, [self::TYPE_GROUP, self::TYPE_CONFERENCE]);
    }

    /**
     * Check if call was rated
     */
    public function wasRated(): bool
    {
        return !is_null($this->rating);
    }

    /**
     * Add rating to call
     */
    public function addRating(int $rating, string $feedback = null): bool
    {
        if ($rating < self::RATING_MIN || $rating > self::RATING_MAX) {
            return false;
        }

        return $this->update([
            'rating' => $rating,
            'feedback' => $feedback,
        ]);
    }

    /**
     * Get formatted duration (HH:MM:SS)
     */
    public function getFormattedDuration(): ?string
    {
        if (!$this->duration_seconds) {
            return null;
        }

        $hours = floor($this->duration_seconds / 3600);
        $minutes = floor(($this->duration_seconds % 3600) / 60);
        $seconds = $this->duration_seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Get quality level label
     */
    public function getQualityLevel(): string
    {
        if (!$this->quality_score) {
            return 'unknown';
        }

        if ($this->quality_score >= self::QUALITY_EXCELLENT) {
            return 'excellent';
        } elseif ($this->quality_score >= self::QUALITY_GOOD) {
            return 'good';
        } elseif ($this->quality_score >= self::QUALITY_FAIR) {
            return 'fair';
        }

        return 'poor';
    }

    /**
     * Get call direction (incoming/outgoing)
     */
    public function getDirection(): string
    {
        return $this->wasCaller() ? 'outgoing' : 'incoming';
    }

    /**
     * Calculate engagement metrics
     */
    protected function calculateEngagementMetrics(): array
    {
        $metrics = [
            'duration_seconds' => $this->duration_seconds,
            'video_enabled' => $this->was_video_enabled,
            'audio_enabled' => $this->was_audio_enabled,
            'screen_shared' => $this->was_screen_shared,
        ];

        // Calculate engagement score (0-100)
        $score = 0;

        // Duration score (50 points max)
        if ($this->duration_seconds > 0) {
            $durationMinutes = $this->duration_seconds / 60;
            $score += min(50, $durationMinutes * 5);
        }

        // Feature usage (50 points max)
        if ($this->was_video_enabled) $score += 20;
        if ($this->was_audio_enabled) $score += 20;
        if ($this->was_screen_shared) $score += 10;

        $metrics['engagement_score'] = min(100, (int) round($score));

        return $metrics;
    }

    /**
     * Get call summary
     */
    public function getSummary(): array
    {
        return [
            'call_type' => $this->call_type,
            'call_status' => $this->call_status,
            'direction' => $this->getDirection(),
            'duration' => $this->getFormattedDuration(),
            'duration_seconds' => $this->duration_seconds,
            'quality_level' => $this->getQualityLevel(),
            'quality_score' => $this->quality_score,
            'was_successful' => $this->wasSuccessful(),
            'was_missed' => $this->wasMissed(),
            'end_reason' => $this->end_reason,
            'rating' => $this->rating,
            'has_feedback' => !empty($this->feedback),
            'features_used' => [
                'video' => $this->was_video_enabled,
                'audio' => $this->was_audio_enabled,
                'screen_share' => $this->was_screen_shared,
                'recording' => $this->was_recorded,
            ],
            'participant_count' => $this->participant_summary['count'] ?? null,
        ];
    }

    /**
     * Get engagement statistics
     */
    public function getEngagementStats(): array
    {
        $metrics = $this->engagement_metrics ?? [];

        return [
            'engagement_score' => $metrics['engagement_score'] ?? 0,
            'duration_seconds' => $this->duration_seconds,
            'duration_formatted' => $this->getFormattedDuration(),
            'features_used_count' => collect([
                $this->was_video_enabled,
                $this->was_audio_enabled,
                $this->was_screen_shared,
            ])->filter()->count(),
            'video_enabled' => $this->was_video_enabled,
            'audio_enabled' => $this->was_audio_enabled,
            'screen_shared' => $this->was_screen_shared,
        ];
    }

    // ========================================
    // STATIC QUERY METHODS
    // ========================================

    /**
     * Get call statistics for user
     */
    public static function getUserStatistics(int $userId, int $days = 30): array
    {
        $histories = self::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $totalCalls = $histories->count();
        $successfulCalls = $histories->where('call_status', self::STATUS_CONNECTED)->count();
        $missedCalls = $histories->filter(fn($h) => $h->wasMissed())->count();
        $totalDuration = $histories->sum('duration_seconds');
        $avgDuration = $totalCalls > 0 ? round($totalDuration / $totalCalls) : 0;
        $avgQuality = $histories->whereNotNull('quality_score')->avg('quality_score');
        $avgRating = $histories->whereNotNull('rating')->avg('rating');

        return [
            'period_days' => $days,
            'total_calls' => $totalCalls,
            'successful_calls' => $successfulCalls,
            'missed_calls' => $missedCalls,
            'failed_calls' => $histories->where('call_status', self::STATUS_FAILED)->count(),
            'outgoing_calls' => $histories->where('user_role', self::ROLE_CALLER)->count(),
            'incoming_calls' => $histories->where('user_role', self::ROLE_PARTICIPANT)->count(),
            'total_duration_seconds' => $totalDuration,
            'average_duration_seconds' => $avgDuration,
            'average_quality_score' => $avgQuality ? round($avgQuality, 2) : null,
            'average_rating' => $avgRating ? round($avgRating, 2) : null,
            'success_rate' => $totalCalls > 0 ? round(($successfulCalls / $totalCalls) * 100, 2) : 0,
        ];
    }

    /**
     * Get call trends for user
     */
    public static function getUserTrends(int $userId, int $days = 30): array
    {
        $histories = self::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get();

        $byDate = $histories->groupBy(fn($h) => $h->created_at->format('Y-m-d'));

        return [
            'daily_call_count' => $byDate->map(fn($group) => $group->count())->toArray(),
            'daily_duration' => $byDate->map(fn($group) => $group->sum('duration_seconds'))->toArray(),
            'quality_trend' => $histories->whereNotNull('quality_score')
                ->pluck('quality_score', 'created_at')
                ->toArray(),
        ];
    }

    // ========================================
    // STATIC HELPER METHODS
    // ========================================

    /**
     * Detect platform from user agent
     */
    protected static function detectPlatform(): string
    {
        $userAgent = request()->userAgent() ?? '';

        if (stripos($userAgent, 'android') !== false) {
            return 'android';
        } elseif (stripos($userAgent, 'iphone') !== false || stripos($userAgent, 'ipad') !== false) {
            return 'ios';
        } elseif (stripos($userAgent, 'windows') !== false) {
            return 'windows';
        } elseif (stripos($userAgent, 'mac') !== false) {
            return 'mac';
        }

        return 'web';
    }

    // ========================================
    // ARRAY / JSON SERIALIZATION
    // ========================================

    /**
     * Get the instance as an array
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Add computed attributes
        $array['duration_formatted'] = $this->getFormattedDuration();
        $array['quality_level'] = $this->getQualityLevel();
        $array['direction'] = $this->getDirection();
        $array['was_successful'] = $this->wasSuccessful();
        $array['was_missed'] = $this->wasMissed();

        return $array;
    }
}