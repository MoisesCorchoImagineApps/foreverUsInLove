<?php

declare(strict_types=1);

namespace App\Models\Chat;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Class VideoCall
 *
 * Modelo de videollamadas con soporte WebRTC, múltiples participantes,
 * configuración de calidad y tracking de métricas de conexión.
 *
 * @package App\Models\Chat
 *
 * @property int $id
 * @property int $caller_id
 * @property int $chat_id
 * @property string $type // private, group, conference, emergency
 * @property string $status // initiating, ringing, connecting, connected, ended, failed
 * @property string $session_id
 * @property string|null $signaling_server
 * @property array $webrtc_config
 * @property array $quality_settings
 * @property array $metadata
 * @property array|null $recording_data
 * @property int $participant_count
 * @property int $max_participants
 * @property Carbon|null $started_at
 * @property Carbon|null $connected_at
 * @property Carbon|null $ended_at
 * @property int|null $duration_seconds
 * @property string|null $end_reason // normal, timeout, error, declined, busy, no_answer
 * @property array|null $quality_metrics
 * @property float|null $average_quality_score
 * @property bool $is_recorded
 * @property bool $is_video_enabled
 * @property bool $is_audio_enabled
 * @property bool $is_screen_shared
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read User $caller
 * @property-read Chat $chat
 * @property-read Collection|User[] $participants
 * @property-read Collection|CallHistory[] $callHistories
 */
class VideoCall extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'video_calls';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'caller_id',
        'chat_id',
        'type',
        'status',
        'session_id',
        'signaling_server',
        'webrtc_config',
        'quality_settings',
        'metadata',
        'recording_data',
        'participant_count',
        'max_participants',
        'started_at',
        'connected_at',
        'ended_at',
        'duration_seconds',
        'end_reason',
        'quality_metrics',
        'average_quality_score',
        'is_recorded',
        'is_video_enabled',
        'is_audio_enabled',
        'is_screen_shared',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'webrtc_config' => 'array',
        'quality_settings' => 'array',
        'metadata' => 'array',
        'recording_data' => 'array',
        'quality_metrics' => 'array',
        'participant_count' => 'integer',
        'max_participants' => 'integer',
        'started_at' => 'datetime',
        'connected_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'average_quality_score' => 'float',
        'is_recorded' => 'boolean',
        'is_video_enabled' => 'boolean',
        'is_audio_enabled' => 'boolean',
        'is_screen_shared' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'webrtc_config',
        'deleted_at',
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
     * Quality levels
     */
    public const QUALITY_AUTO = 'auto';
    public const QUALITY_LOW = 'low'; // 480p
    public const QUALITY_MEDIUM = 'medium'; // 720p
    public const QUALITY_HIGH = 'high'; // 1080p
    public const QUALITY_HD = 'hd'; // 1440p

    /**
     * Quality resolutions
     */
    public const RESOLUTIONS = [
        self::QUALITY_LOW => ['width' => 640, 'height' => 480],
        self::QUALITY_MEDIUM => ['width' => 1280, 'height' => 720],
        self::QUALITY_HIGH => ['width' => 1920, 'height' => 1080],
        self::QUALITY_HD => ['width' => 2560, 'height' => 1440],
    ];

    /**
     * Max participants by tier
     */
    public const MAX_PARTICIPANTS_FREE = 4;
    public const MAX_PARTICIPANTS_PREMIUM = 12;

    /**
     * Duration limits (seconds) by tier
     */
    public const MAX_DURATION_FREE = 7200; // 2 hours
    public const MAX_DURATION_PREMIUM = 14400; // 4 hours

    /**
     * Timeouts (seconds)
     */
    public const RING_TIMEOUT = 60;
    public const CONNECTION_TIMEOUT = 30;

    /**
     * Default quality settings
     */
    public const DEFAULT_QUALITY_SETTINGS = [
        'quality' => self::QUALITY_AUTO,
        'video_enabled' => true,
        'audio_enabled' => true,
        'screen_share_enabled' => false,
        'recording_enabled' => false,
        'noise_cancellation' => true,
        'echo_cancellation' => true,
        'auto_gain_control' => true,
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (VideoCall $videoCall) {
            if (!$videoCall->session_id) {
                $videoCall->session_id = self::generateSessionId();
            }

            if (empty($videoCall->quality_settings)) {
                $videoCall->quality_settings = self::DEFAULT_QUALITY_SETTINGS;
            }

            if (empty($videoCall->metadata)) {
                $videoCall->metadata = [
                    'created_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'platform' => self::detectPlatform(),
                ];
            }

            // Set default video/audio state from quality settings
            $videoCall->is_video_enabled = $videoCall->quality_settings['video_enabled'] ?? true;
            $videoCall->is_audio_enabled = $videoCall->quality_settings['audio_enabled'] ?? true;
        });

        static::created(function (VideoCall $videoCall) {
            // Create associated Chat if not exists
            if (!$videoCall->chat_id) {
                $chat = Chat::create([
                    'creator_id' => $videoCall->caller_id,
                    'type' => Chat::TYPE_VIDEO_CALL,
                    'status' => Chat::STATUS_ACTIVE,
                    'chatable_type' => self::class,
                    'chatable_id' => $videoCall->id,
                ]);
                $videoCall->update(['chat_id' => $chat->id]);
            }
        });

        static::updated(function (VideoCall $videoCall) {
            $videoCall->clearCache();
        });

        static::deleted(function (VideoCall $videoCall) {
            // Create call history records for all participants
            foreach ($videoCall->participants as $participant) {
                CallHistory::create([
                    'video_call_id' => $videoCall->id,
                    'user_id' => $participant->id,
                    'duration_seconds' => $videoCall->duration_seconds,
                    'call_type' => $videoCall->type,
                    'call_status' => $videoCall->status,
                    'end_reason' => $videoCall->end_reason,
                    'quality_score' => $videoCall->average_quality_score,
                    'metadata' => [
                        'participant_role' => $participant->pivot->role ?? 'participant',
                        'joined_at' => $participant->pivot->joined_at,
                        'left_at' => $participant->pivot->left_at,
                    ],
                ]);
            }

            $videoCall->clearCache();
        });
    }

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Get the caller (initiator) of the call
     */
    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    /**
     * Get the associated chat
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }

    /**
     * Get all participants of the call
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'video_call_participants', 'video_call_id', 'user_id')
            ->withPivot([
                'status',
                'role',
                'joined_at',
                'left_at',
                'connection_quality',
                'video_enabled',
                'audio_enabled',
                'screen_sharing',
                'duration_seconds',
                'metadata',
            ])
            ->withTimestamps();
    }

    /**
     * Get call histories
     */
    public function callHistories(): HasMany
    {
        return $this->hasMany(CallHistory::class, 'video_call_id');
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope for active calls
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_RINGING,
            self::STATUS_CONNECTING,
            self::STATUS_CONNECTED,
        ]);
    }

    /**
     * Scope for ongoing calls
     */
    public function scopeOngoing($query)
    {
        return $query->where('status', self::STATUS_CONNECTED);
    }

    /**
     * Scope for ended calls
     */
    public function scopeEnded($query)
    {
        return $query->where('status', self::STATUS_ENDED);
    }

    /**
     * Scope for failed calls
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for private calls
     */
    public function scopePrivate($query)
    {
        return $query->where('type', self::TYPE_PRIVATE);
    }

    /**
     * Scope for group calls
     */
    public function scopeGroup($query)
    {
        return $query->where('type', self::TYPE_GROUP);
    }

    /**
     * Scope for recorded calls
     */
    public function scopeRecorded($query)
    {
        return $query->where('is_recorded', true);
    }

    /**
     * Scope for calls by user (caller or participant)
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('caller_id', $userId)
                ->orWhereHas('participants', function ($q2) use ($userId) {
                    $q2->where('user_id', $userId);
                });
        });
    }

    /**
     * Scope for recent calls
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // ========================================
    // BUSINESS LOGIC METHODS
    // ========================================

    /**
     * Check if call is active
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_RINGING,
            self::STATUS_CONNECTING,
            self::STATUS_CONNECTED,
        ]);
    }

    /**
     * Check if call is ongoing
     */
    public function isOngoing(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    /**
     * Check if call is ended
     */
    public function isEnded(): bool
    {
        return $this->status === self::STATUS_ENDED;
    }

    /**
     * Check if call is private
     */
    public function isPrivate(): bool
    {
        return $this->type === self::TYPE_PRIVATE;
    }

    /**
     * Check if call is group
     */
    public function isGroup(): bool
    {
        return in_array($this->type, [self::TYPE_GROUP, self::TYPE_CONFERENCE]);
    }

    /**
     * Check if user is participant
     */
    public function hasParticipant(int $userId): bool
    {
        return $this->caller_id === $userId || 
               $this->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Check if call is at max capacity
     */
    public function isFull(): bool
    {
        return $this->participant_count >= $this->max_participants;
    }

    /**
     * Start ringing
     */
    public function startRinging(): bool
    {
        return $this->update([
            'status' => self::STATUS_RINGING,
            'started_at' => now(),
        ]);
    }

    /**
     * Start connecting
     */
    public function startConnecting(): bool
    {
        return $this->update([
            'status' => self::STATUS_CONNECTING,
        ]);
    }

    /**
     * Mark as connected
     */
    public function markAsConnected(): bool
    {
        return $this->update([
            'status' => self::STATUS_CONNECTED,
            'connected_at' => now(),
        ]);
    }

    /**
     * End call
     */
    public function end(string $reason = self::END_REASON_NORMAL): bool
    {
        if ($this->isEnded()) {
            return false;
        }

        $duration = null;
        if ($this->connected_at) {
            $duration = $this->connected_at->diffInSeconds(now());
        }

        return $this->update([
            'status' => self::STATUS_ENDED,
            'ended_at' => now(),
            'end_reason' => $reason,
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $reason = self::END_REASON_ERROR): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'ended_at' => now(),
            'end_reason' => $reason,
        ]);
    }

    /**
     * Add participant to call
     */
    public function addParticipant(int $userId, array $attributes = []): bool
    {
        if ($this->hasParticipant($userId)) {
            return false;
        }

        if ($this->isFull()) {
            return false;
        }

        $defaults = [
            'status' => 'joined',
            'role' => $userId === $this->caller_id ? 'caller' : 'participant',
            'joined_at' => now(),
            'video_enabled' => $this->is_video_enabled,
            'audio_enabled' => $this->is_audio_enabled,
            'screen_sharing' => false,
        ];

        $this->participants()->attach($userId, array_merge($defaults, $attributes));
        $this->increment('participant_count');

        return true;
    }

    /**
     * Remove participant from call
     */
    public function removeParticipant(int $userId): bool
    {
        if (!$this->hasParticipant($userId)) {
            return false;
        }

        // Calculate participant duration
        $participant = $this->participants()->where('user_id', $userId)->first();
        $joinedAt = $participant?->pivot?->joined_at;
        $duration = $joinedAt ? Carbon::parse($joinedAt)->diffInSeconds(now()) : 0;

        $this->participants()->updateExistingPivot($userId, [
            'status' => 'left',
            'left_at' => now(),
            'duration_seconds' => $duration,
        ]);

        $this->decrement('participant_count');

        // End call if no participants left
        if ($this->participant_count <= 0) {
            $this->end(self::END_REASON_NORMAL);
        }

        return true;
    }

    /**
     * Toggle video for call
     */
    public function toggleVideo(bool $enabled): bool
    {
        return $this->update(['is_video_enabled' => $enabled]);
    }

    /**
     * Toggle audio for call
     */
    public function toggleAudio(bool $enabled): bool
    {
        return $this->update(['is_audio_enabled' => $enabled]);
    }

    /**
     * Toggle screen share
     */
    public function toggleScreenShare(bool $enabled): bool
    {
        return $this->update(['is_screen_shared' => $enabled]);
    }

    /**
     * Start recording
     */
    public function startRecording(array $config = []): bool
    {
        if ($this->is_recorded) {
            return false;
        }

        return $this->update([
            'is_recorded' => true,
            'recording_data' => array_merge([
                'started_at' => now()->toIso8601String(),
                'format' => 'mp4',
                'quality' => $this->quality_settings['quality'] ?? self::QUALITY_MEDIUM,
            ], $config),
        ]);
    }

    /**
     * Stop recording
     */
    public function stopRecording(): bool
    {
        if (!$this->is_recorded) {
            return false;
        }

        $recordingData = $this->recording_data ?? [];
        $recordingData['ended_at'] = now()->toIso8601String();

        return $this->update([
            'recording_data' => $recordingData,
        ]);
    }

    /**
     * Update quality metrics
     */
    public function updateQualityMetrics(array $metrics): bool
    {
        $currentMetrics = $this->quality_metrics ?? [];
        $updatedMetrics = array_merge($currentMetrics, [
            'timestamp' => now()->toIso8601String(),
            'data' => $metrics,
        ]);

        // Calculate average quality score
        $avgScore = $this->calculateAverageQualityScore($metrics);

        return $this->update([
            'quality_metrics' => $updatedMetrics,
            'average_quality_score' => $avgScore,
        ]);
    }

    /**
     * Calculate average quality score (0-100)
     */
    protected function calculateAverageQualityScore(array $metrics): float
    {
        $score = 100;

        // Penalize based on packet loss
        if (isset($metrics['packet_loss'])) {
            $score -= min(40, $metrics['packet_loss'] * 4);
        }

        // Penalize based on jitter
        if (isset($metrics['jitter'])) {
            $score -= min(30, $metrics['jitter'] / 10);
        }

        // Penalize based on latency
        if (isset($metrics['latency'])) {
            $score -= min(30, $metrics['latency'] / 20);
        }

        return max(0, round($score, 2));
    }

    /**
     * Get call statistics
     */
    public function getStatistics(): array
    {
        return [
            'participant_count' => $this->participant_count,
            'max_participants' => $this->max_participants,
            'duration_seconds' => $this->duration_seconds,
            'duration_formatted' => $this->getFormattedDuration(),
            'average_quality_score' => $this->average_quality_score,
            'is_recorded' => $this->is_recorded,
            'is_video_enabled' => $this->is_video_enabled,
            'is_audio_enabled' => $this->is_audio_enabled,
            'is_screen_shared' => $this->is_screen_shared,
            'quality_level' => $this->quality_settings['quality'] ?? self::QUALITY_AUTO,
        ];
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

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    // ========================================
    // CACHE METHODS
    // ========================================

    /**
     * Get cache key
     */
    public function getCacheKey(string $suffix = ''): string
    {
        $key = "video_call:{$this->id}";
        return $suffix ? "{$key}:{$suffix}" : $key;
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        Cache::forget($this->getCacheKey());
        Cache::forget($this->getCacheKey('participants'));
        Cache::forget($this->getCacheKey('statistics'));
    }

    // ========================================
    // STATIC HELPER METHODS
    // ========================================

    /**
     * Generate unique session ID
     */
    protected static function generateSessionId(): string
    {
        return 'vc_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

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
        $array['is_active'] = $this->isActive();
        $array['is_full'] = $this->isFull();
        $array['duration_formatted'] = $this->getFormattedDuration();

        return $array;
    }
}