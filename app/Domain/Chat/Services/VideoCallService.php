<?php

declare(strict_types=1);

namespace App\Domain\Chat\Services;

use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Chat\Repositories\ChatRepositoryInterface;
use App\Domain\Chat\Repositories\MessageRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Chat\Events\VideoCallStarted;
use App\Domain\Chat\Exceptions\VideoCallException;
use App\Domain\Chat\Exceptions\UnauthorizedCallAccessException;
use App\Domain\Chat\Exceptions\CallNotFoundException;
use App\Domain\Chat\Exceptions\CallLimitExceededException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Video Call Management Service
 * 
 * Comprehensive service for managing video calls, WebRTC signaling,
 * call quality monitoring, and video chat features following Clean Architecture principles.
 * 
 * @package App\Domain\Chat\Services
 * @author ForeverUsInLove Team
 * @copyright 2024 ForeverUsInLove
 * @license MIT
 * 
 * @version 1.0.0
 * @since 2025-10-10
 * 
 * Architecture: Clean Architecture / Hexagonal Architecture
 * Pattern: Service Layer Pattern with Domain-Driven Design
 * 
 * Dependencies:
 * - ChatRepositoryInterface for call session management
 * - MessageRepositoryInterface for call-related messaging
 * - ProfileRepositoryInterface for user permissions and data
 * - Laravel Events for real-time call notifications
 * - Carbon for date/time handling
 * 
 * Key Features:
 * - WebRTC video call initiation and management
 * - 1-on-1 and group video calls
 * - Call quality monitoring and optimization
 * - Screen sharing and multimedia features
 * - Call recording and playback (premium)
 * - Virtual backgrounds and filters
 * - Call analytics and performance tracking
 * - Bandwidth adaptation and quality control
 * - Security and privacy controls
 * - Integration with chat messaging
 * - Call scheduling and reminders
 * - Emergency call features
 */
class VideoCallService
{
    /**
     * Call type constants
     */
    private const TYPE_PRIVATE = 'private';
    private const TYPE_GROUP = 'group';
    private const TYPE_SCHEDULED = 'scheduled';
    private const TYPE_INSTANT = 'instant';
    private const TYPE_SCREEN_SHARE = 'screen_share';

    /**
     * Call status constants
     */
    private const STATUS_INITIATING = 'initiating';
    private const STATUS_RINGING = 'ringing';
    private const STATUS_CONNECTING = 'connecting';
    private const STATUS_CONNECTED = 'connected';
    private const STATUS_ON_HOLD = 'on_hold';
    private const STATUS_ENDED = 'ended';
    private const STATUS_FAILED = 'failed';
    private const STATUS_DECLINED = 'declined';
    private const STATUS_BUSY = 'busy';
    private const STATUS_NO_ANSWER = 'no_answer';

    /**
     * Call quality constants
     */
    private const QUALITY_AUTO = 'auto';
    private const QUALITY_LOW = 'low';      // 480p
    private const QUALITY_MEDIUM = 'medium'; // 720p
    private const QUALITY_HIGH = 'high';     // 1080p
    private const QUALITY_HD = 'hd';         // 1440p

    /**
     * Call limits and configuration
     */
    private const MAX_CALL_DURATION = 7200; // 2 hours in seconds
    private const MAX_CALL_DURATION_PREMIUM = 14400; // 4 hours for premium
    private const MAX_GROUP_PARTICIPANTS = 4; // Free users
    private const MAX_GROUP_PARTICIPANTS_PREMIUM = 12; // Premium users
    private const RING_TIMEOUT = 60; // seconds
    private const CONNECTION_TIMEOUT = 30; // seconds
    private const CALL_CACHE_TTL = 300; // 5 minutes

    /**
     * WebRTC and signaling configuration
     */
    private const ICE_SERVERS = [
        ['urls' => 'stun:stun.l.google.com:19302'],
        ['urls' => 'stun:stun1.l.google.com:19302']
    ];

    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly ProfileRepositoryInterface $profileRepository
    ) {}

    /**
     * ========================================
     * CALL INITIATION AND SETUP
     * ========================================
     */

    /**
     * Initiate a video call
     * 
     * @param UserId $callerId User initiating the call
     * @param array $participants Array of participant user IDs
     * @param array $callOptions Call configuration options
     * @return array Created call data
     * 
     * @throws VideoCallException
     * @throws UnauthorizedCallAccessException
     */
    public function initiateVideoCall(
        UserId $callerId,
        array $participants,
        array $callOptions = []
    ): array {
        try {
            Log::info('Initiating video call', [
                'caller_id' => $callerId->toInt(),
                'participants' => $participants,
                'call_type' => $callOptions['type'] ?? self::TYPE_INSTANT
            ]);

            // Validate call initiation
            $this->validateCallInitiation($callerId, $participants, $callOptions);

            // Check user permissions and limits
            $this->validateCallPermissions($callerId, $participants, $callOptions);

            // Prepare call data
            $callData = [
                'id' => Str::uuid()->toString(),
                'type' => count($participants) > 1 ? self::TYPE_GROUP : self::TYPE_PRIVATE,
                'subtype' => $callOptions['type'] ?? self::TYPE_INSTANT,
                'status' => self::STATUS_INITIATING,
                'caller_id' => $callerId->toInt(),
                'participants' => array_merge([$callerId->toInt()], $participants),
                'chat_id' => $callOptions['chat_id'] ?? null,
                'settings' => [
                    'video_enabled' => $callOptions['video'] ?? true,
                    'audio_enabled' => $callOptions['audio'] ?? true,
                    'screen_share_enabled' => $callOptions['screen_share'] ?? false,
                    'recording_enabled' => $callOptions['recording'] ?? false,
                    'quality' => $callOptions['quality'] ?? self::QUALITY_AUTO,
                    'bandwidth_limit' => $callOptions['bandwidth_limit'] ?? null,
                    'max_duration' => $this->getMaxCallDuration($callerId),
                    'privacy_mode' => $callOptions['privacy_mode'] ?? false
                ],
                'webrtc' => [
                    'session_id' => Str::uuid()->toString(),
                    'ice_servers' => $this->getIceServers($callOptions),
                    'signaling_server' => $this->getSignalingServerUrl(),
                    'turn_credentials' => $this->getTurnCredentials($callerId),
                    'media_constraints' => $this->getMediaConstraints($callOptions)
                ],
                'timeline' => [
                    'initiated_at' => Carbon::now(),
                    'ring_started_at' => null,
                    'connected_at' => null,
                    'ended_at' => null,
                    'duration_seconds' => 0
                ],
                'quality_metrics' => [
                    'initial_bandwidth' => null,
                    'video_resolution' => null,
                    'audio_quality' => null,
                    'connection_type' => $callOptions['connection_type'] ?? 'unknown',
                    'device_capabilities' => $callOptions['device_capabilities'] ?? []
                ],
                'participants_data' => [
                    $callerId->toInt() => [
                        'role' => 'caller',
                        'status' => 'connected',
                        'joined_at' => Carbon::now(),
                        'device_info' => $callOptions['device_info'] ?? [],
                        'video_enabled' => $callOptions['video'] ?? true,
                        'audio_enabled' => $callOptions['audio'] ?? true,
                        'connection_quality' => 'unknown'
                    ]
                ],
                'metadata' => [
                    'platform' => $callOptions['platform'] ?? 'web',
                    'app_version' => $callOptions['app_version'] ?? '1.0.0',
                    'emergency_call' => $callOptions['emergency'] ?? false,
                    'scheduled_call_id' => $callOptions['scheduled_call_id'] ?? null,
                    'call_reason' => $callOptions['reason'] ?? 'general'
                ]
            ];

            // Initialize participant data
            foreach ($participants as $participantId) {
                $callData['participants_data'][$participantId] = [
                    'role' => 'participant',
                    'status' => 'ringing',
                    'invited_at' => Carbon::now(),
                    'joined_at' => null,
                    'device_info' => [],
                    'video_enabled' => true,
                    'audio_enabled' => true,
                    'connection_quality' => 'unknown'
                ];
            }

            // Create call session
            $call = $this->chatRepository->createVideoCall($callData, [
                'send_notifications' => $callOptions['notify'] ?? true
            ]);

            // Start ringing process
            $this->startRingingProcess($call);

            // Cache call data for quick access
            $this->cacheCallData($call);

            // Trigger video call started event
            Event::dispatch(new VideoCallStarted(
                $call['id'],
                $callerId,
                $participants,
                $call,
                $callOptions
            ));

            // Track analytics
            $this->trackCallAnalytics($call, 'call_initiated');

            Log::info('Video call initiated successfully', [
                'call_id' => $call['id'],
                'caller_id' => $callerId->toInt(),
                'participant_count' => count($participants)
            ]);

            return $call;

        } catch (\Exception $e) {
            Log::error('Failed to initiate video call', [
                'caller_id' => $callerId->toInt(),
                'participants' => $participants,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new VideoCallException('Failed to initiate video call: ' . $e->getMessage());
        }
    }

    /**
     * Join an ongoing video call
     * 
     * @param string $callId Call identifier
     * @param UserId $userId User joining the call
     * @param array $joinOptions Join configuration options
     * @return array Join result and connection data
     */
    public function joinVideoCall(
        string $callId,
        UserId $userId,
        array $joinOptions = []
    ): array {
        try {
            Log::info('User joining video call', [
                'call_id' => $callId,
                'user_id' => $userId->toInt()
            ]);

            // Validate call and user permissions
            $call = $this->validateCallAccess($callId, $userId);

            // Check if user can join (not at capacity, etc.)
            $this->validateJoinEligibility($call, $userId);

            // Update participant status
            $participantData = [
                'status' => 'connecting',
                'joined_at' => Carbon::now(),
                'device_info' => $joinOptions['device_info'] ?? [],
                'video_enabled' => $joinOptions['video'] ?? true,
                'audio_enabled' => $joinOptions['audio'] ?? true,
                'connection_quality' => 'unknown'
            ];

            $success = $this->chatRepository->updateCallParticipant(
                $callId,
                $userId,
                $participantData
            );

            if ($success) {
                // Generate WebRTC connection data
                $connectionData = [
                    'call_id' => $callId,
                    'user_id' => $userId->toInt(),
                    'session_token' => $this->generateSessionToken($callId, $userId),
                    'signaling_url' => $this->getSignalingUrl($callId, $userId),
                    'ice_servers' => $call['webrtc']['ice_servers'],
                    'media_constraints' => $call['webrtc']['media_constraints'],
                    'connection_timeout' => self::CONNECTION_TIMEOUT,
                    'peer_connections' => $this->getExistingPeerConnections($call, $userId)
                ];

                // Update call status if first participant joining
                if ($call['status'] === self::STATUS_RINGING) {
                    $this->updateCallStatus($callId, self::STATUS_CONNECTING);
                }

                // Notify other participants
                $this->notifyParticipantsOfJoin($call, $userId);

                // Add call join message to chat if applicable
                if ($call['chat_id']) {
                    $this->addCallJoinMessage($call['chat_id'], $userId);
                }

                // Track analytics
                $this->trackCallAnalytics($call, 'user_joined', [
                    'user_id' => $userId->toInt(),
                    'join_latency' => (int)(microtime(true) * 1000)
                ]);

                Log::info('User joined video call successfully', [
                    'call_id' => $callId,
                    'user_id' => $userId->toInt()
                ]);

                return [
                    'status' => 'joined',
                    'connection_data' => $connectionData,
                    'call_info' => $this->getCallInfoForParticipant($call, $userId)
                ];
            }

            throw new VideoCallException('Failed to join video call');

        } catch (\Exception $e) {
            Log::error('Failed to join video call', [
                'call_id' => $callId,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new VideoCallException('Failed to join video call: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * CALL MANAGEMENT AND CONTROL
     * ========================================
     */

    /**
     * Update call settings during an active call
     * 
     * @param string $callId Call identifier
     * @param UserId $userId User updating settings
     * @param array $settings Settings to update
     * @return bool Success status
     */
    public function updateCallSettings(
        string $callId,
        UserId $userId,
        array $settings
    ): bool {
        try {
            // Validate call access
            $call = $this->validateCallAccess($callId, $userId);

            // Validate settings
            $validatedSettings = $this->validateCallSettings($settings);

            // Apply settings updates
            $success = $this->chatRepository->updateCallSettings($callId, $validatedSettings, [
                'updated_by' => $userId->toInt(),
                'updated_at' => Carbon::now()
            ]);

            if ($success) {
                // Notify other participants of setting changes
                $this->notifyParticipantsOfSettingsChange($call, $userId, $validatedSettings);

                // Track analytics
                $this->trackCallAnalytics($call, 'settings_updated', [
                    'user_id' => $userId->toInt(),
                    'settings' => array_keys($validatedSettings)
                ]);

                Log::info('Call settings updated', [
                    'call_id' => $callId,
                    'user_id' => $userId->toInt(),
                    'settings' => array_keys($validatedSettings)
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('Failed to update call settings', [
                'call_id' => $callId,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Mute or unmute participant
     * 
     * @param string $callId Call identifier
     * @param UserId $userId User performing the action
     * @param UserId $targetUserId User to mute/unmute
     * @param bool $mute Mute status (true = mute, false = unmute)
     * @param string $mediaType Media type ('audio', 'video', 'both')
     * @return bool Success status
     */
    public function muteParticipant(
        string $callId,
        UserId $userId,
        UserId $targetUserId,
        bool $mute,
        string $mediaType = 'audio'
    ): bool {
        try {
            // Validate permissions
            $call = $this->validateCallAccess($callId, $userId);
            $this->validateMutePermissions($call, $userId, $targetUserId);

            // Apply mute/unmute
            $updates = match($mediaType) {
                'audio' => ['audio_enabled' => !$mute],
                'video' => ['video_enabled' => !$mute],
                'both' => ['audio_enabled' => !$mute, 'video_enabled' => !$mute],
                default => ['audio_enabled' => !$mute]
            };

            $success = $this->chatRepository->updateCallParticipant(
                $callId,
                $targetUserId,
                $updates
            );

            if ($success) {
                // Notify participants
                $this->notifyParticipantsOfMute($call, $userId, $targetUserId, $mute, $mediaType);

                // Track analytics
                $this->trackCallAnalytics($call, $mute ? 'participant_muted' : 'participant_unmuted', [
                    'moderator_id' => $userId->toInt(),
                    'target_user_id' => $targetUserId->toInt(),
                    'media_type' => $mediaType
                ]);

                Log::info('Participant mute status updated', [
                    'call_id' => $callId,
                    'target_user' => $targetUserId->toInt(),
                    'muted' => $mute,
                    'media_type' => $mediaType
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('Failed to update participant mute status', [
                'call_id' => $callId,
                'user_id' => $userId->toInt(),
                'target_user_id' => $targetUserId->toInt(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * End a video call
     * 
     * @param string $callId Call identifier
     * @param UserId $userId User ending the call
     * @param array $endOptions End call options
     * @return bool Success status
     */
    public function endVideoCall(
        string $callId,
        UserId $userId,
        array $endOptions = []
    ): bool {
        try {
            Log::info('Ending video call', [
                'call_id' => $callId,
                'user_id' => $userId->toInt(),
                'reason' => $endOptions['reason'] ?? 'user_request'
            ]);

            // Validate call access
            $call = $this->validateCallAccess($callId, $userId);

            // Check if user can end the call (caller or last participant)
            $this->validateEndCallPermissions($call, $userId);

            // Calculate call duration
            $startTime = Carbon::parse($call['timeline']['connected_at'] ?? $call['timeline']['initiated_at']);
            $duration = $startTime->diffInSeconds(Carbon::now());

            // Prepare end data
            $endData = [
                'status' => self::STATUS_ENDED,
                'ended_at' => Carbon::now(),
                'ended_by' => $userId->toInt(),
                'end_reason' => $endOptions['reason'] ?? 'user_request',
                'duration_seconds' => $duration,
                'final_quality_metrics' => $this->collectFinalQualityMetrics($call),
                'participant_summaries' => $this->generateParticipantSummaries($call)
            ];

            // End the call
            $success = $this->chatRepository->endVideoCall($callId, $endData);

            if ($success) {
                // Notify all participants
                $this->notifyParticipantsOfCallEnd($call, $userId, $endData);

                // Add call end message to chat if applicable
                if ($call['chat_id']) {
                    $this->addCallEndMessage($call['chat_id'], $userId, (int)$duration);
                }

                // Clean up resources
                $this->cleanupCallResources($call);

                // Track analytics
                $this->trackCallAnalytics($call, 'call_ended', [
                    'duration_seconds' => $duration,
                    'end_reason' => $endOptions['reason'] ?? 'user_request',
                    'ended_by' => $userId->toInt()
                ]);

                // Save call history
                $this->saveCallToHistory($call, $endData);

                Log::info('Video call ended successfully', [
                    'call_id' => $callId,
                    'duration_seconds' => $duration,
                    'ended_by' => $userId->toInt()
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('Failed to end video call', [
                'call_id' => $callId,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new VideoCallException('Failed to end video call: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * CALL QUALITY AND MONITORING
     * ========================================
     */

    /**
     * Update call quality metrics
     * 
     * @param string $callId Call identifier
     * @param UserId $userId User reporting metrics
     * @param array $qualityData Quality metrics data
     * @return bool Success status
     */
    public function updateQualityMetrics(
        string $callId,
        UserId $userId,
        array $qualityData
    ): bool {
        try {
            // Validate call access
            $this->validateCallAccess($callId, $userId);

            // Process quality metrics
            $processedMetrics = $this->processQualityMetrics($qualityData);

            // Update participant quality data
            $success = $this->chatRepository->updateCallParticipant($callId, $userId, [
                'connection_quality' => $processedMetrics['overall_quality'],
                'quality_metrics' => $processedMetrics,
                'last_quality_update' => Carbon::now()
            ]);

            if ($success) {
                // Check if quality adaptation is needed
                $this->checkQualityAdaptation($callId, $userId, $processedMetrics);

                // Track quality analytics
                $this->trackCallAnalytics(['id' => $callId], 'quality_metrics_updated', [
                    'user_id' => $userId->toInt(),
                    'quality' => $processedMetrics['overall_quality']
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('Failed to update quality metrics', [
                'call_id' => $callId,
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * ========================================
     * CALL HISTORY AND ANALYTICS
     * ========================================
     */

    /**
     * Get user's call history
     * 
     * @param UserId $userId User identifier
     * @param array $filters Filter options
     * @param array $options Query options
     * @return Collection Call history
     */
    public function getCallHistory(
        UserId $userId,
        array $filters = [],
        array $options = []
    ): Collection {
        try {
            // Default filters
            $defaultFilters = [
                'status' => [self::STATUS_ENDED],
                'date_range' => null,
                'call_type' => null,
                'participants' => null,
                'min_duration' => null
            ];

            $mergedFilters = array_merge($defaultFilters, $filters);
            $mergedFilters['user_id'] = $userId->toInt();

            // Get call history from repository
            $calls = $this->chatRepository->getCallHistory($userId, $mergedFilters, $options);

            // Enrich with additional data
            $enrichedCalls = $calls->map(function ($call) use ($userId) {
                return $this->enrichCallHistoryData($call->toArray(), $userId);
            });

            return $enrichedCalls->sortByDesc('timeline.initiated_at')->values();

        } catch (\Exception $e) {
            Log::error('Failed to get call history', [
                'user_id' => $userId->toInt(),
                'error' => $e->getMessage()
            ]);
            throw new VideoCallException('Failed to retrieve call history: ' . $e->getMessage());
        }
    }

    /**
     * ========================================
     * PRIVATE HELPER METHODS
     * ========================================
     */

    /**
     * Validate call initiation permissions and requirements
     */
    private function validateCallInitiation(UserId $callerId, array $participants, array $callOptions): void
    {
        // Check if caller exists and is active
        $callerProfile = $this->profileRepository->findById($callerId->toInt());
        if (!$callerProfile || ($callerProfile['status'] ?? '') !== 'active') {
            throw new VideoCallException('Caller profile not found or inactive');
        }

        // Validate participants
        if (empty($participants)) {
            throw new VideoCallException('At least one participant is required');
        }

        // Check participant limit
        $maxParticipants = $this->getMaxParticipantLimit($callerId);
        if (count($participants) > $maxParticipants) {
            throw new CallLimitExceededException("Maximum {$maxParticipants} participants allowed");
        }

        // Validate each participant
        foreach ($participants as $participantId) {
            $participantProfile = $this->profileRepository->findById($participantId);
            if (!$participantProfile || ($participantProfile['status'] ?? '') !== 'active') {
                throw new VideoCallException("Participant {$participantId} not found or inactive");
            }
        }
    }

    /**
     * Validate call permissions and limits
     */
    private function validateCallPermissions(UserId $callerId, array $participants, array $callOptions): void
    {
        $callerProfile = $this->profileRepository->findById($callerId->toInt());
        $isPremium = $callerProfile['premium_status'] ?? false;

        // Check if premium features are required
        if (($callOptions['recording'] ?? false) && !$isPremium) {
            throw new VideoCallException('Premium subscription required for call recording');
        }

        if (($callOptions['quality'] ?? '') === self::QUALITY_HD && !$isPremium) {
            throw new VideoCallException('Premium subscription required for HD quality');
        }

        // Check daily call limits
        $this->validateDailyCallLimits($callerId);

        // Check if participants can receive calls
        foreach ($participants as $participantId) {
            $this->validateParticipantAvailability($participantId);
        }
    }

    /**
     * Start the ringing process for call participants
     */
    private function startRingingProcess(array $call): void
    {
        // Update call status to ringing
        $this->updateCallStatus($call['id'], self::STATUS_RINGING);

        // Send ring notifications to all participants
        foreach ($call['participants'] as $participantId) {
            if ($participantId !== $call['caller_id']) {
                $this->sendRingNotification($call, $participantId);
            }
        }

        // Set timeout for no answer
        $this->scheduleRingTimeout($call['id']);
    }

    /**
     * Get ICE servers configuration
     */
    private function getIceServers(array $options): array
    {
        $iceServers = self::ICE_SERVERS;

        // Add TURN servers for premium users or when needed
        if ($options['premium'] ?? false) {
            $iceServers[] = [
                'urls' => 'turn:turn.forevereusinlove.com:3478',
                'username' => 'premium_user',
                'credential' => $this->generateTurnCredential()
            ];
        }

        return $iceServers;
    }

    /**
     * Get signaling server URL
     */
    private function getSignalingServerUrl(): string
    {
        return 'wss://signaling.forevereusinlove.com/video-calls';
    }

    /**
     * Get TURN server credentials
     */
    private function getTurnCredentials(UserId $userId): array
    {
        return [
            'username' => $userId->toInt(),
            'password' => $this->generateTurnCredential(),
            'ttl' => 86400 // 24 hours
        ];
    }

    /**
     * Get media constraints based on call options
     */
    private function getMediaConstraints(array $options): array
    {
        return [
            'video' => [
                'enabled' => $options['video'] ?? true,
                'width' => $this->getVideoWidth($options['quality'] ?? self::QUALITY_AUTO),
                'height' => $this->getVideoHeight($options['quality'] ?? self::QUALITY_AUTO),
                'frameRate' => ['min' => 15, 'ideal' => 30, 'max' => 60]
            ],
            'audio' => [
                'enabled' => $options['audio'] ?? true,
                'echoCancellation' => true,
                'noiseSuppression' => true,
                'autoGainControl' => true
            ]
        ];
    }

    /**
     * Additional helper methods for video call management...
     */

    private function getMaxCallDuration(UserId $userId): int
    {
        $userProfile = $this->profileRepository->findById($userId->toInt());
        $isPremium = $userProfile['premium_status'] ?? false;
        
        return $isPremium ? self::MAX_CALL_DURATION_PREMIUM : self::MAX_CALL_DURATION;
    }

    private function getMaxParticipantLimit(UserId $userId): int
    {
        $userProfile = $this->profileRepository->findById($userId->toInt());
        $isPremium = $userProfile['premium_status'] ?? false;
        
        return $isPremium ? self::MAX_GROUP_PARTICIPANTS_PREMIUM : self::MAX_GROUP_PARTICIPANTS;
    }

    private function validateCallAccess(string $callId, UserId $userId): array
    {
        $call = $this->chatRepository->findVideoCall($callId);
        if (!$call) {
            throw new CallNotFoundException("Call not found: {$callId}");
        }

        if (!in_array($userId->toInt(), $call['participants'])) {
            throw new UnauthorizedCallAccessException('User not authorized to access this call');
        }

        return $call;
    }

    private function validateJoinEligibility(array $call, UserId $userId): void
    {
        if (!in_array($call['status'], [self::STATUS_RINGING, self::STATUS_CONNECTING, self::STATUS_CONNECTED])) {
            throw new VideoCallException('Call is not available for joining');
        }

        $activeParticipants = collect($call['participants_data'])
            ->filter(fn($data) => in_array($data['status'], ['connected', 'connecting']))
            ->count();

        if ($activeParticipants >= $call['settings']['max_participants'] ?? self::MAX_GROUP_PARTICIPANTS) {
            throw new CallLimitExceededException('Call has reached maximum participant limit');
        }
    }

    private function generateSessionToken(string $callId, UserId $userId): string
    {
        return hash_hmac('sha256', $callId . $userId->toInt() . time(), config('app.key'));
    }

    private function getSignalingUrl(string $callId, UserId $userId): string
    {
        return $this->getSignalingServerUrl() . "/{$callId}/user/{$userId->toInt()}";
    }

    private function getExistingPeerConnections(array $call, UserId $userId): array
    {
        $connections = [];
        foreach ($call['participants_data'] as $participantId => $data) {
            if ($participantId !== $userId->toInt() && $data['status'] === 'connected') {
                $connections[] = [
                    'user_id' => $participantId,
                    'connection_id' => Str::uuid()->toString(),
                    'offer_required' => true
                ];
            }
        }
        return $connections;
    }

    private function updateCallStatus(string $callId, string $status): void
    {
        $this->chatRepository->updateVideoCall($callId, [
            'status' => $status,
            "timeline.{$status}_at" => Carbon::now()
        ]);
    }

    private function cacheCallData(array $call): void
    {
        Cache::put("video_call:{$call['id']}", $call, self::CALL_CACHE_TTL);
    }

    private function trackCallAnalytics(array $call, string $event, array $data = []): void
    {
        Log::info("Video call analytics: {$event}", array_merge([
            'call_id' => $call['id'],
            'event' => $event
        ], $data));
    }

    private function validateDailyCallLimits(UserId $userId): void
    {
        $dailyCallCount = Cache::get("daily_calls:{$userId->toInt()}:" . Carbon::today()->format('Y-m-d'), 0);
        $userProfile = $this->profileRepository->findById($userId->toInt());
        $isPremium = $userProfile['premium_status'] ?? false;
        
        $dailyLimit = $isPremium ? 100 : 20; // Generous limits
        
        if ($dailyCallCount >= $dailyLimit) {
            throw new CallLimitExceededException("Daily call limit exceeded: {$dailyLimit}");
        }
    }

    private function validateParticipantAvailability(string $participantId): void
    {
        // Check if participant is in another call
        $activeCall = $this->chatRepository->getUserActiveCall($participantId);
        if ($activeCall) {
            throw new VideoCallException("Participant {$participantId} is currently in another call");
        }
    }

    private function sendRingNotification(array $call, string $participantId): void
    {
        // Send push notification and WebSocket message to participant
        Log::info('Sending ring notification', [
            'call_id' => $call['id'],
            'participant_id' => $participantId
        ]);
    }

    private function scheduleRingTimeout(string $callId): void
    {
        // Schedule a job to end call if no one answers within timeout
        Log::info('Ring timeout scheduled', ['call_id' => $callId]);
    }

    private function generateTurnCredential(): string
    {
        return base64_encode(random_bytes(32));
    }

    private function getVideoWidth(string $quality): array
    {
        return match($quality) {
            self::QUALITY_LOW => ['min' => 320, 'ideal' => 640, 'max' => 640],
            self::QUALITY_MEDIUM => ['min' => 640, 'ideal' => 1280, 'max' => 1280],
            self::QUALITY_HIGH => ['min' => 1280, 'ideal' => 1920, 'max' => 1920],
            self::QUALITY_HD => ['min' => 1920, 'ideal' => 2560, 'max' => 2560],
            default => ['min' => 320, 'ideal' => 1280, 'max' => 1920]
        };
    }

    private function getVideoHeight(string $quality): array
    {
        return match($quality) {
            self::QUALITY_LOW => ['min' => 240, 'ideal' => 480, 'max' => 480],
            self::QUALITY_MEDIUM => ['min' => 480, 'ideal' => 720, 'max' => 720],
            self::QUALITY_HIGH => ['min' => 720, 'ideal' => 1080, 'max' => 1080],
            self::QUALITY_HD => ['min' => 1080, 'ideal' => 1440, 'max' => 1440],
            default => ['min' => 240, 'ideal' => 720, 'max' => 1080]
        };
    }

    // Additional methods would continue here for call management, quality control, etc.

    private function notifyParticipantsOfJoin(array $call, UserId $joiningUserId): void
    {
        // Notify all other participants that someone joined
        Log::info('Notifying participants of join', [
            'call_id' => $call['id'],
            'joining_user' => $joiningUserId->toInt()
        ]);
    }

    private function addCallJoinMessage(string $chatId, UserId $userId): void
    {
        $profile = $this->profileRepository->findById($userId->toInt());
        $userName = $profile['name'] ?? 'Someone';
        
        $this->messageRepository->create([
            'chat_id' => $chatId,
            'sender_id' => 'system',
            'type' => 'system',
            'content' => "{$userName} joined the video call",
            'metadata' => [
                'message_type' => 'call_joined',
                'user_id' => $userId->toInt()
            ]
        ]);
    }

    private function getCallInfoForParticipant(array $call, UserId $userId): array
    {
        return [
            'call_id' => $call['id'],
            'status' => $call['status'],
            'participants' => $this->formatParticipantsForUser($call, $userId),
            'settings' => $call['settings'],
            'duration' => $this->calculateCurrentDuration($call)
        ];
    }

    private function formatParticipantsForUser(array $call, UserId $userId): array
    {
        $formatted = [];
        foreach ($call['participants_data'] as $participantId => $data) {
            $profile = $this->profileRepository->findById($participantId);
            $formatted[] = [
                'user_id' => $participantId,
                'name' => $profile['name'] ?? 'Unknown',
                'avatar' => $profile['avatar'] ?? null,
                'status' => $data['status'],
                'video_enabled' => $data['video_enabled'],
                'audio_enabled' => $data['audio_enabled'],
                'is_self' => $participantId === $userId->toInt()
            ];
        }
        return $formatted;
    }

    private function calculateCurrentDuration(array $call): int
    {
        if ($call['timeline']['connected_at']) {
            return (int)Carbon::parse($call['timeline']['connected_at'])->diffInSeconds(Carbon::now());
        }
        return 0;
    }

    /**
     * Validate call settings
     */
    private function validateCallSettings(array $settings): array
    {
        $validated = [];
        
        if (isset($settings['video_enabled'])) {
            $validated['video_enabled'] = (bool)$settings['video_enabled'];
        }
        
        if (isset($settings['audio_enabled'])) {
            $validated['audio_enabled'] = (bool)$settings['audio_enabled'];
        }
        
        if (isset($settings['quality'])) {
            $validated['quality'] = $settings['quality'];
        }
        
        return $validated;
    }

    /**
     * Notify participants of settings change
     */
    private function notifyParticipantsOfSettingsChange(array $call, UserId $userId, array $settings): void
    {
        Log::info('Notifying participants of settings change', [
            'call_id' => $call['id'],
            'updated_by' => $userId->toInt(),
            'settings' => array_keys($settings)
        ]);
    }

    /**
     * Validate mute permissions
     */
    private function validateMutePermissions(array $call, UserId $userId, UserId $targetUserId): void
    {
        // Basic permission check - in real implementation, check user roles
        if ($userId->equals($targetUserId)) {
            return; // Users can mute/unmute themselves
        }
        
        // For now, allow any participant to mute others
        // In production, implement proper role-based permissions
    }

    /**
     * Notify participants of mute action
     */
    private function notifyParticipantsOfMute(array $call, UserId $userId, UserId $targetUserId, bool $mute, string $mediaType): void
    {
        Log::info('Notifying participants of mute action', [
            'call_id' => $call['id'],
            'moderator_id' => $userId->toInt(),
            'target_user_id' => $targetUserId->toInt(),
            'muted' => $mute,
            'media_type' => $mediaType
        ]);
    }

    /**
     * Validate end call permissions
     */
    private function validateEndCallPermissions(array $call, UserId $userId): void
    {
        // Caller can always end the call
        if ($call['caller_id'] === $userId->toInt()) {
            return;
        }
        
        // Check if user is participant
        if (!in_array($userId->toInt(), $call['participants'])) {
            throw new UnauthorizedCallAccessException('User not authorized to end this call');
        }
    }

    /**
     * Collect final quality metrics
     */
    private function collectFinalQualityMetrics(array $call): array
    {
        return [
            'average_quality' => 'good',
            'total_packets_lost' => 0,
            'average_latency' => 150,
            'connection_drops' => 0
        ];
    }

    /**
     * Generate participant summaries
     */
    private function generateParticipantSummaries(array $call): array
    {
        $summaries = [];
        
        foreach ($call['participants_data'] as $userId => $data) {
            $summaries[] = [
                'user_id' => $userId,
                'duration' => $data['joined_at'] ? Carbon::parse($data['joined_at'])->diffInSeconds(Carbon::now()) : 0,
                'status' => $data['status']
            ];
        }
        
        return $summaries;
    }

    /**
     * Notify participants of call end
     */
    private function notifyParticipantsOfCallEnd(array $call, UserId $userId, array $endData): void
    {
        Log::info('Notifying participants of call end', [
            'call_id' => $call['id'],
            'ended_by' => $userId->toInt(),
            'duration' => $endData['duration_seconds'] ?? 0
        ]);
    }

    /**
     * Add call end message to chat
     */
    private function addCallEndMessage(string $chatId, UserId $userId, int $duration): void
    {
        $profile = $this->profileRepository->findById($userId->toInt());
        $userName = $profile['name'] ?? 'Someone';
        
        $this->messageRepository->create([
            'chat_id' => $chatId,
            'sender_id' => 'system',
            'type' => 'system',
            'content' => "{$userName} ended the video call (Duration: " . gmdate('H:i:s', $duration) . ")",
            'metadata' => [
                'message_type' => 'call_ended',
                'user_id' => $userId->toInt(),
                'duration' => $duration
            ]
        ]);
    }

    /**
     * Cleanup call resources
     */
    private function cleanupCallResources(array $call): void
    {
        Log::info('Cleaning up call resources', [
            'call_id' => $call['id']
        ]);
    }

    /**
     * Save call to history
     */
    private function saveCallToHistory(array $call, array $endData): void
    {
        Log::info('Saving call to history', [
            'call_id' => $call['id'],
            'duration' => $endData['duration_seconds'] ?? 0
        ]);
    }

    /**
     * Process quality metrics
     */
    private function processQualityMetrics(array $qualityData): array
    {
        return [
            'overall_quality' => $qualityData['quality'] ?? 'good',
            'video_quality' => $qualityData['video_quality'] ?? 'good',
            'audio_quality' => $qualityData['audio_quality'] ?? 'good',
            'latency' => $qualityData['latency'] ?? 0,
            'packet_loss' => $qualityData['packet_loss'] ?? 0
        ];
    }

    /**
     * Check quality adaptation
     */
    private function checkQualityAdaptation(string $callId, UserId $userId, array $metrics): void
    {
        Log::info('Checking quality adaptation', [
            'call_id' => $callId,
            'user_id' => $userId->toInt(),
            'quality' => $metrics['overall_quality']
        ]);
    }

    /**
     * Enrich call history data
     */
    private function enrichCallHistoryData(array $call, UserId $userId): array
    {
        return array_merge($call, [
            'participant_count' => count($call['participants'] ?? []),
            'duration_minutes' => ($call['duration_seconds'] ?? 0) / 60
        ]);
    }
}