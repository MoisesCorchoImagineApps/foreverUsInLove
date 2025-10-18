<?php

declare(strict_types=1);

namespace App\Domain\Chat\Events;

use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Video Call Started Event
 * 
 * Comprehensive event for real-time video call initiation, participant notifications,
 * WebRTC signaling coordination, and cross-system integration when video calls begin.
 * 
 * @package App\Domain\Chat\Events
 * @author ForeverUsInLove Team
 * @copyright 2024 ForeverUsInLove
 * @license MIT
 * 
 * @version 1.0.0
 * @since 2025-10-10
 * 
 * Architecture: Clean Architecture / Hexagonal Architecture
 * Pattern: Domain Events with Laravel Broadcasting
 * 
 * Key Features:
 * - Real-time call initiation broadcasting
 * - WebRTC signaling coordination
 * - Multi-device call notifications
 * - Call quality monitoring setup
 * - Analytics and performance tracking
 * - Premium feature activation
 * - Emergency call handling
 * - Cross-platform synchronization
 * - Bandwidth optimization triggers
 * - Security and privacy controls
 */
class VideoCallStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Event data
     */
    public readonly string $callId;
    public readonly UserId $callerId;
    public readonly array $participantIds;
    public readonly array $callData;
    public readonly array $callOptions;
    public readonly Carbon $timestamp;

    /**
     * Broadcasting configuration
     */
    private array $broadcastChannels = [];
    private array $signalingData = [];
    private array $notificationTargets = [];
    private array $analyticsData = [];

    /**
     * Create a new event instance
     * 
     * @param string $callId Video call unique identifier
     * @param UserId $callerId User who initiated the call
     * @param array $participantIds Array of participant user IDs
     * @param array $callData Complete call configuration data
     * @param array $callOptions Call processing options
     */
    public function __construct(
        string $callId,
        UserId $callerId,
        array $participantIds,
        array $callData,
        array $callOptions = []
    ) {
        $this->callId = $callId;
        $this->callerId = $callerId;
        $this->participantIds = $participantIds;
        $this->callData = $callData;
        $this->callOptions = $callOptions;
        $this->timestamp = Carbon::now();

        // Initialize WebRTC signaling data
        $this->initializeSignalingData();

        // Initialize broadcasting configuration
        $this->initializeBroadcastingConfig();

        // Prepare analytics data
        $this->prepareAnalyticsData();

        Log::info('VideoCallStarted event created', [
            'call_id' => $this->callId,
            'caller_id' => $this->callerId->toInt(),
            'participant_count' => count($this->participantIds),
            'call_type' => $this->callData['type'] ?? 'private'
        ]);
    }

    /**
     * Get the channels the event should broadcast on
     * 
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return $this->broadcastChannels;
    }

    /**
     * Get the data to broadcast
     * 
     * @return array Broadcast payload
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'video_call.started',
            'call' => [
                'id' => $this->callId,
                'type' => $this->getCallType(),
                'status' => 'ringing',
                'caller_id' => $this->callerId->toInt(),
                'participants' => $this->getParticipantData(),
                'started_at' => $this->timestamp->toISOString(),
                'estimated_duration' => $this->getEstimatedDuration(),
                'quality_settings' => $this->getQualitySettings(),
                'features_enabled' => $this->getEnabledFeatures()
            ],
            'caller' => [
                'id' => $this->callerId->toInt(),
                'name' => $this->getCallerName(),
                'avatar' => $this->getCallerAvatar(),
                'call_preference' => $this->getCallerPreferences()
            ],
            'webrtc' => [
                'session_id' => $this->getSessionId(),
                'signaling_url' => $this->getSignalingUrl(),
                'ice_servers' => $this->getIceServers(),
                'media_constraints' => $this->getMediaConstraints(),
                'connection_timeout' => $this->getConnectionTimeout()
            ],
            'ring_settings' => [
                'ring_tone' => $this->getRingTone(),
                'ring_duration' => $this->getRingDuration(),
                'vibration_pattern' => $this->getVibrationPattern(),
                'custom_caller_id' => $this->getCustomCallerId()
            ],
            'call_controls' => [
                'decline_enabled' => true,
                'accept_enabled' => true,
                'video_toggle_enabled' => $this->isVideoToggleEnabled(),
                'audio_toggle_enabled' => $this->isAudioToggleEnabled(),
                'screen_share_available' => $this->isScreenShareAvailable(),
                'recording_available' => $this->isRecordingAvailable()
            ],
            'notifications' => [
                'push_notification' => $this->shouldSendPushNotification(),
                'sound_notification' => $this->shouldPlayNotificationSound(),
                'banner_notification' => $this->shouldShowBanner(),
                'priority' => $this->getNotificationPriority()
            ],
            'privacy' => [
                'caller_visible' => $this->isCallerVisible(),
                'location_shared' => $this->isLocationShared(),
                'call_encrypted' => $this->isCallEncrypted(),
                'recording_consent_required' => $this->isRecordingConsentRequired()
            ],
            'emergency' => [
                'is_emergency_call' => $this->isEmergencyCall(),
                'emergency_contacts_notified' => $this->areEmergencyContactsNotified(),
                'location_services_enabled' => $this->areLocationServicesEnabled()
            ]
        ];
    }

    /**
     * Get broadcast event name
     * 
     * @return string Event name
     */
    public function broadcastAs(): string
    {
        return 'video_call.started';
    }

    /**
     * Determine if the event should be queued
     * 
     * @return bool Whether to queue the broadcast
     */
    public function shouldQueue(): bool
    {
        // Video call events need immediate broadcasting
        return false;
    }

    /**
     * Get event tags for queue monitoring
     * 
     * @return array Queue tags
     */
    public function tags(): array
    {
        return [
            'call:' . $this->callId,
            'caller:' . $this->callerId->toInt(),
            'type:' . $this->getCallType(),
            'participants:' . count($this->participantIds)
        ];
    }

    /**
     * ========================================
     * EVENT DATA GETTERS
     * ========================================
     */

    /**
     * Get call ID
     */
    public function getCallId(): string
    {
        return $this->callId;
    }

    /**
     * Get caller ID
     */
    public function getCallerId(): UserId
    {
        return $this->callerId;
    }

    /**
     * Get participant IDs
     */
    public function getParticipantIds(): array
    {
        return $this->participantIds;
    }

    /**
     * Get complete call data
     */
    public function getCallData(): array
    {
        return $this->callData;
    }

    /**
     * Get call options
     */
    public function getCallOptions(): array
    {
        return $this->callOptions;
    }

    /**
     * Get event timestamp
     */
    public function getTimestamp(): Carbon
    {
        return $this->timestamp;
    }

    /**
     * Get signaling data for WebRTC
     */
    public function getSignalingData(): array
    {
        return $this->signalingData;
    }

    /**
     * Get notification targets
     */
    public function getNotificationTargets(): array
    {
        return $this->notificationTargets;
    }

    /**
     * Get analytics data
     */
    public function getAnalyticsData(): array
    {
        return $this->analyticsData;
    }

    /**
     * ========================================
     * CALL CONFIGURATION GETTERS
     * ========================================
     */

    /**
     * Get call type (private, group, conference, etc.)
     */
    public function getCallType(): string
    {
        return $this->callData['type'] ?? 'private';
    }

    /**
     * Get call subtype (instant, scheduled, emergency, etc.)
     */
    public function getCallSubtype(): string
    {
        return $this->callData['subtype'] ?? 'instant';
    }

    /**
     * Check if this is a group call
     */
    public function isGroupCall(): bool
    {
        return $this->getCallType() === 'group' || count($this->participantIds) > 1;
    }

    /**
     * Check if this is a scheduled call
     */
    public function isScheduledCall(): bool
    {
        return $this->getCallSubtype() === 'scheduled';
    }

    /**
     * Check if this is an emergency call
     */
    public function isEmergencyCall(): bool
    {
        return $this->callData['metadata']['emergency_call'] ?? false;
    }

    /**
     * Get estimated call duration in minutes
     */
    public function getEstimatedDuration(): int
    {
        return $this->callOptions['estimated_duration'] ?? 30; // Default 30 minutes
    }

    /**
     * Get video quality settings
     */
    public function getQualitySettings(): array
    {
        return [
            'video_quality' => $this->callData['settings']['quality'] ?? 'auto',
            'audio_quality' => $this->callData['settings']['audio_quality'] ?? 'high',
            'bandwidth_limit' => $this->callData['settings']['bandwidth_limit'] ?? null,
            'adaptive_quality' => $this->callData['settings']['adaptive_quality'] ?? true
        ];
    }

    /**
     * Get enabled call features
     */
    public function getEnabledFeatures(): array
    {
        $settings = $this->callData['settings'] ?? [];
        
        return [
            'video' => $settings['video_enabled'] ?? true,
            'audio' => $settings['audio_enabled'] ?? true,
            'screen_share' => $settings['screen_share_enabled'] ?? false,
            'recording' => $settings['recording_enabled'] ?? false,
            'chat' => $settings['chat_enabled'] ?? true,
            'file_sharing' => $settings['file_sharing_enabled'] ?? false,
            'virtual_background' => $settings['virtual_background_enabled'] ?? false,
            'noise_cancellation' => $settings['noise_cancellation_enabled'] ?? true
        ];
    }

    /**
     * ========================================
     * WEBRTC AND SIGNALING GETTERS
     * ========================================
     */

    /**
     * Get WebRTC session ID
     */
    public function getSessionId(): string
    {
        return $this->callData['webrtc']['session_id'] ?? $this->callId;
    }

    /**
     * Get signaling server URL
     */
    public function getSignalingUrl(): string
    {
        return $this->callData['webrtc']['signaling_server'] ?? 'wss://signaling.forevereusinlove.com/video-calls';
    }

    /**
     * Get ICE servers configuration
     */
    public function getIceServers(): array
    {
        return $this->callData['webrtc']['ice_servers'] ?? [
            ['urls' => 'stun:stun.l.google.com:19302']
        ];
    }

    /**
     * Get media constraints
     */
    public function getMediaConstraints(): array
    {
        return $this->callData['webrtc']['media_constraints'] ?? [
            'video' => ['width' => 1280, 'height' => 720],
            'audio' => true
        ];
    }

    /**
     * Get connection timeout in seconds
     */
    public function getConnectionTimeout(): int
    {
        return $this->callOptions['connection_timeout'] ?? 30;
    }

    /**
     * ========================================
     * NOTIFICATION AND UI GETTERS
     * ========================================
     */

    /**
     * Get caller display name
     */
    public function getCallerName(): string
    {
        return $this->callData['caller_name'] ?? 'Unknown Caller';
    }

    /**
     * Get caller avatar URL
     */
    public function getCallerAvatar(): ?string
    {
        return $this->callData['caller_avatar'] ?? null;
    }

    /**
     * Get caller preferences for this call
     */
    public function getCallerPreferences(): array
    {
        return [
            'prefers_video' => $this->callData['settings']['video_enabled'] ?? true,
            'prefers_audio_only' => !($this->callData['settings']['video_enabled'] ?? true),
            'quality_preference' => $this->callData['settings']['quality'] ?? 'auto',
            'noise_cancellation' => $this->callData['settings']['noise_cancellation_enabled'] ?? true
        ];
    }

    /**
     * Get participant data for broadcasting
     */
    public function getParticipantData(): array
    {
        $participants = [];
        
        foreach ($this->participantIds as $participantId) {
            $participants[] = [
                'user_id' => $participantId,
                'name' => $this->getParticipantName($participantId),
                'avatar' => $this->getParticipantAvatar($participantId),
                'status' => 'ringing',
                'call_preferences' => $this->getParticipantCallPreferences($participantId)
            ];
        }
        
        return $participants;
    }

    /**
     * Get ring tone configuration
     */
    public function getRingTone(): array
    {
        return [
            'tone_type' => $this->callOptions['ring_tone'] ?? 'default',
            'volume' => $this->callOptions['ring_volume'] ?? 0.8,
            'custom_tone_url' => $this->callOptions['custom_ring_tone'] ?? null
        ];
    }

    /**
     * Get ring duration in seconds
     */
    public function getRingDuration(): int
    {
        return $this->callOptions['ring_duration'] ?? 60; // 60 seconds default
    }

    /**
     * Get vibration pattern for mobile devices
     */
    public function getVibrationPattern(): array
    {
        return $this->callOptions['vibration_pattern'] ?? [200, 100, 200, 100, 200];
    }

    /**
     * Get custom caller ID information
     */
    public function getCustomCallerId(): ?array
    {
        if (!($this->callOptions['custom_caller_id'] ?? false)) {
            return null;
        }

        return [
            'display_name' => $this->getCallerName(),
            'subtitle' => $this->getCallTypeDisplayName(),
            'image_url' => $this->getCallerAvatar(),
            'urgency_level' => $this->isEmergencyCall() ? 'high' : 'normal'
        ];
    }

    /**
     * ========================================
     * FEATURE AND PERMISSION GETTERS
     * ========================================
     */

    /**
     * Check if video toggle is enabled during call
     */
    public function isVideoToggleEnabled(): bool
    {
        return $this->callData['settings']['video_toggle_enabled'] ?? true;
    }

    /**
     * Check if audio toggle is enabled during call
     */
    public function isAudioToggleEnabled(): bool
    {
        return $this->callData['settings']['audio_toggle_enabled'] ?? true;
    }

    /**
     * Check if screen sharing is available
     */
    public function isScreenShareAvailable(): bool
    {
        $isPremium = $this->isCallerPremium();
        return $isPremium && ($this->callData['settings']['screen_share_enabled'] ?? false);
    }

    /**
     * Check if call recording is available
     */
    public function isRecordingAvailable(): bool
    {
        $isPremium = $this->isCallerPremium();
        return $isPremium && ($this->callData['settings']['recording_enabled'] ?? false);
    }

    /**
     * Check if caller has premium status
     */
    public function isCallerPremium(): bool
    {
        return $this->callData['caller_premium'] ?? false;
    }

    /**
     * ========================================
     * NOTIFICATION CONTROL GETTERS
     * ========================================
     */

    /**
     * Check if push notifications should be sent
     */
    public function shouldSendPushNotification(): bool
    {
        return $this->callOptions['send_push_notifications'] ?? true;
    }

    /**
     * Check if notification sound should be played
     */
    public function shouldPlayNotificationSound(): bool
    {
        return $this->callOptions['play_notification_sound'] ?? true;
    }

    /**
     * Check if banner notification should be shown
     */
    public function shouldShowBanner(): bool
    {
        return $this->callOptions['show_banner_notification'] ?? true;
    }

    /**
     * Get notification priority level
     */
    public function getNotificationPriority(): string
    {
        if ($this->isEmergencyCall()) {
            return 'critical';
        }

        if (!$this->isGroupCall()) {
            return 'high'; // 1-on-1 calls are high priority
        }

        return 'medium';
    }

    /**
     * ========================================
     * PRIVACY AND SECURITY GETTERS
     * ========================================
     */

    /**
     * Check if caller information is visible to participants
     */
    public function isCallerVisible(): bool
    {
        return $this->callData['privacy']['caller_visible'] ?? true;
    }

    /**
     * Check if location is shared in this call
     */
    public function isLocationShared(): bool
    {
        return $this->callData['privacy']['location_shared'] ?? false;
    }

    /**
     * Check if call is encrypted
     */
    public function isCallEncrypted(): bool
    {
        return $this->callData['encryption']['encrypted'] ?? true;
    }

    /**
     * Check if recording consent is required from all participants
     */
    public function isRecordingConsentRequired(): bool
    {
        return $this->callData['privacy']['recording_consent_required'] ?? true;
    }

    /**
     * Check if emergency contacts have been notified
     */
    public function areEmergencyContactsNotified(): bool
    {
        return $this->isEmergencyCall() && ($this->callOptions['notify_emergency_contacts'] ?? true);
    }

    /**
     * Check if location services are enabled for emergency calls
     */
    public function areLocationServicesEnabled(): bool
    {
        return $this->isEmergencyCall() && ($this->callOptions['enable_location_services'] ?? true);
    }

    /**
     * ========================================
     * ANALYTICS AND TRACKING GETTERS
     * ========================================
     */

    /**
     * Check if call analytics should be tracked
     */
    public function shouldTrackAnalytics(): bool
    {
        return $this->callOptions['track_analytics'] ?? true;
    }

    /**
     * Check if quality monitoring should be enabled
     */
    public function shouldMonitorQuality(): bool
    {
        return $this->callOptions['monitor_quality'] ?? true;
    }

    /**
     * Get A/B testing variant if applicable
     */
    public function getABTestVariant(): ?string
    {
        return $this->callOptions['ab_test_variant'] ?? null;
    }

    /**
     * ========================================
     * PRIVATE HELPER METHODS
     * ========================================
     */

    /**
     * Initialize WebRTC signaling data
     */
    private function initializeSignalingData(): void
    {
        $this->signalingData = [
            'session_id' => $this->getSessionId(),
            'caller_id' => $this->callerId->toInt(),
            'participants' => $this->participantIds,
            'ice_servers' => $this->getIceServers(),
            'media_constraints' => $this->getMediaConstraints(),
            'signaling_url' => $this->getSignalingUrl(),
            'connection_timeout' => $this->getConnectionTimeout(),
            'call_type' => $this->getCallType(),
            'features_enabled' => $this->getEnabledFeatures(),
            'encryption_key' => $this->generateEncryptionKey(),
            'bandwidth_optimization' => $this->getBandwidthOptimization()
        ];
    }

    /**
     * Initialize broadcasting configuration
     */
    private function initializeBroadcastingConfig(): void
    {
        // Main call channel for WebRTC signaling
        $this->broadcastChannels[] = new PrivateChannel("call.{$this->callId}");

        // Individual participant channels for call notifications
        foreach ($this->participantIds as $participantId) {
            $this->broadcastChannels[] = new PrivateChannel("user.{$participantId}.calls");
            $this->broadcastChannels[] = new PrivateChannel("user.{$participantId}.notifications");
            
            // Add to notification targets
            $this->notificationTargets[] = [
                'user_id' => $participantId,
                'channels' => ['push', 'websocket', 'sms'],
                'priority' => $this->getNotificationPriority(),
                'delay' => 0, // Immediate for calls
                'ring_settings' => $this->getRingTone(),
                'vibration' => $this->getVibrationPattern()
            ];
        }

        // Caller channel for call status updates
        $this->broadcastChannels[] = new PrivateChannel("user.{$this->callerId->toInt()}.calls");

        // WebRTC signaling channel
        $this->broadcastChannels[] = new PrivateChannel("webrtc.{$this->callId}.signaling");

        // Group presence channel for group calls
        if ($this->isGroupCall()) {
            $this->broadcastChannels[] = new PresenceChannel("call.{$this->callId}.presence");
        }

        // Emergency services channel if applicable
        if ($this->isEmergencyCall()) {
            $this->broadcastChannels[] = new Channel('emergency.calls.dispatch');
        }

        // Analytics and monitoring channels
        if ($this->shouldTrackAnalytics()) {
            $this->broadcastChannels[] = new Channel('analytics.calls.started');
        }

        // Quality monitoring channel
        if ($this->shouldMonitorQuality()) {
            $this->broadcastChannels[] = new Channel('quality.monitoring.calls');
        }

        // Admin monitoring for suspicious activity
        $this->broadcastChannels[] = new PrivateChannel('admin.calls.monitoring');
    }

    /**
     * Prepare analytics data for tracking
     */
    private function prepareAnalyticsData(): void
    {
        $this->analyticsData = [
            'event_type' => 'video_call_started',
            'call_id' => $this->callId,
            'caller_id' => $this->callerId->toInt(),
            'participant_ids' => $this->participantIds,
            'participant_count' => count($this->participantIds),
            'call_type' => $this->getCallType(),
            'call_subtype' => $this->getCallSubtype(),
            'is_group_call' => $this->isGroupCall(),
            'is_scheduled_call' => $this->isScheduledCall(),
            'is_emergency_call' => $this->isEmergencyCall(),
            'caller_premium' => $this->isCallerPremium(),
            'features_enabled' => $this->getEnabledFeatures(),
            'quality_settings' => $this->getQualitySettings(),
            'estimated_duration' => $this->getEstimatedDuration(),
            'timestamp' => $this->timestamp->toISOString(),
            'platform' => $this->callOptions['platform'] ?? 'web',
            'device_info' => $this->callOptions['device_info'] ?? [],
            'connection_type' => $this->callOptions['connection_type'] ?? 'unknown',
            'bandwidth_estimate' => $this->callOptions['bandwidth_estimate'] ?? null,
            'ab_test_variant' => $this->getABTestVariant(),
            'call_source' => $this->callOptions['call_source'] ?? 'manual',
            'previous_call_attempts' => $this->callOptions['previous_attempts'] ?? 0,
            'caller_location' => $this->getCallerLocation(),
            'privacy_settings' => $this->getPrivacySettings()
        ];
    }

    /**
     * Get participant display name
     */
    private function getParticipantName(string $participantId): string
    {
        return $this->callData['participant_names'][$participantId] ?? 'Unknown User';
    }

    /**
     * Get participant avatar
     */
    private function getParticipantAvatar(string $participantId): ?string
    {
        return $this->callData['participant_avatars'][$participantId] ?? null;
    }

    /**
     * Get participant call preferences
     */
    private function getParticipantCallPreferences(string $participantId): array
    {
        return [
            'auto_answer' => false,
            'video_preference' => 'enabled',
            'audio_preference' => 'enabled'
        ];
    }

    /**
     * Get call type display name for UI
     */
    private function getCallTypeDisplayName(): string
    {
        return match($this->getCallType()) {
            'group' => 'Group Video Call',
            'conference' => 'Conference Call',
            'emergency' => 'Emergency Call',
            default => 'Video Call'
        };
    }

    /**
     * Generate encryption key for the call
     */
    private function generateEncryptionKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Get bandwidth optimization settings
     */
    private function getBandwidthOptimization(): array
    {
        return [
            'adaptive_bitrate' => true,
            'low_bandwidth_mode' => $this->callOptions['low_bandwidth_mode'] ?? false,
            'quality_scaling' => true,
            'frame_rate_adaptation' => true
        ];
    }

    /**
     * Get caller location if available
     */
    private function getCallerLocation(): ?array
    {
        return $this->callOptions['caller_location'] ?? null;
    }

    /**
     * Get privacy settings summary
     */
    private function getPrivacySettings(): array
    {
        return [
            'caller_visible' => $this->isCallerVisible(),
            'location_shared' => $this->isLocationShared(),
            'call_encrypted' => $this->isCallEncrypted(),
            'recording_consent_required' => $this->isRecordingConsentRequired(),
            'analytics_enabled' => $this->shouldTrackAnalytics()
        ];
    }
}