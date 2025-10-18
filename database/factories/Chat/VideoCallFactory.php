<?php

namespace Database\Factories;

use App\Models\Chat\VideoCall;
use App\Models\Chat\Chat;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoCallFactory extends Factory
{
    protected $model = VideoCall::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['private', 'group', 'conference', 'emergency']);
        $status = $this->faker->randomElement(['initiating', 'ringing', 'connecting', 'connected', 'ended', 'failed']);
        $createdAt = $this->faker->dateTimeBetween('-2 months', 'now');
        
        return [
            'caller_id' => User::factory(),
            'chat_id' => Chat::factory(),
            'type' => $type,
            'status' => $status,
            'session_id' => $this->generateSessionId(),
            'signaling_server' => $this->faker->randomElement([
                'wss://signal1.foreverusinlove.com',
                'wss://signal2.foreverusinlove.com',
                'wss://signal3.foreverusinlove.com',
            ]),
            'webrtc_config' => $this->generateWebRTCConfig(),
            'quality_settings' => $this->generateQualitySettings(),
            'metadata' => $this->generateMetadata(),
            'recording_data' => $this->faker->optional(0.15)->randomElements([
                'format' => 'mp4',
                'quality' => $this->faker->randomElement(['medium', 'high']),
                'started_at' => $createdAt->toIso8601String(),
            ]),
            'participant_count' => $this->getParticipantCount($type),
            'max_participants' => $this->getMaxParticipants($type),
            'started_at' => $this->getStartedAt($status, $createdAt),
            'connected_at' => $this->getConnectedAt($status, $createdAt),
            'ended_at' => $this->getEndedAt($status, $createdAt),
            'duration_seconds' => $this->getDurationSeconds($status, $createdAt),
            'end_reason' => $this->getEndReason($status),
            'quality_metrics' => $this->generateQualityMetrics($status),
            'average_quality_score' => $this->faker->optional(0.7)->randomFloat(2, 30, 100),
            'is_recorded' => $this->faker->boolean(15),
            'is_video_enabled' => $this->faker->boolean(85),
            'is_audio_enabled' => $this->faker->boolean(95),
            'is_screen_shared' => $this->faker->boolean(20),
            'created_at' => $createdAt,
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    private function generateSessionId(): string
    {
        return 'vc_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    private function getParticipantCount(string $type): int
    {
        return match($type) {
            'private' => 2,
            'group' => $this->faker->numberBetween(3, 8),
            'conference' => $this->faker->numberBetween(5, 12),
            'emergency' => $this->faker->numberBetween(2, 6),
            default => 2
        };
    }

    private function getMaxParticipants(string $type): int
    {
        return match($type) {
            'private' => 2,
            'group' => $this->faker->numberBetween(8, 12),
            'conference' => 12,
            'emergency' => 6,
            default => 4
        };
    }

    private function getStartedAt(string $status, $createdAt)
    {
        if (in_array($status, ['ringing', 'connecting', 'connected', 'ended'])) {
            return $this->faker->dateTimeBetween($createdAt, $createdAt->copy()->addMinutes(2));
        }
        return null;
    }

    private function getConnectedAt(string $status, $createdAt)
    {
        if (in_array($status, ['connected', 'ended'])) {
            $startedAt = $this->getStartedAt($status, $createdAt);
            return $this->faker->dateTimeBetween(
                $startedAt ?? $createdAt, 
                ($startedAt ?? $createdAt)->copy()->addMinutes(3)
            );
        }
        return null;
    }

    private function getEndedAt(string $status, $createdAt)
    {
        if ($status === 'ended' || $status === 'failed') {
            return $this->faker->dateTimeBetween($createdAt, 'now');
        }
        return null;
    }

    private function getDurationSeconds(string $status, $createdAt): ?int
    {
        if ($status === 'ended') {
            return $this->faker->numberBetween(30, 7200); // 30 seconds to 2 hours
        }
        return null;
    }

    private function getEndReason(string $status): ?string
    {
        if ($status === 'ended') {
            return $this->faker->randomElement(['normal', 'timeout', 'declined', 'busy']);
        }
        if ($status === 'failed') {
            return $this->faker->randomElement(['error', 'no_answer', 'canceled']);
        }
        return null;
    }

    private function generateWebRTCConfig(): array
    {
        return [
            'ice_servers' => [
                ['urls' => 'stun:stun.l.google.com:19302'],
                ['urls' => 'stun:stun1.l.google.com:19302'],
                [
                    'urls' => 'turn:turn.foreverusinlove.com:3478',
                    'username' => 'user' . $this->faker->numberBetween(1000, 9999),
                    'credential' => $this->faker->sha256(),
                ],
            ],
            'ice_candidate_pool_size' => 10,
            'bundle_policy' => 'max-bundle',
            'rtcp_mux_policy' => 'require',
            'certificate_type' => 'ecdsa',
        ];
    }

    private function generateQualitySettings(): array
    {
        $quality = $this->faker->randomElement(['auto', 'low', 'medium', 'high', 'hd']);
        
        return [
            'quality' => $quality,
            'video_enabled' => $this->faker->boolean(85),
            'audio_enabled' => $this->faker->boolean(95),
            'screen_share_enabled' => $this->faker->boolean(20),
            'recording_enabled' => $this->faker->boolean(15),
            'noise_cancellation' => $this->faker->boolean(80),
            'echo_cancellation' => $this->faker->boolean(90),
            'auto_gain_control' => $this->faker->boolean(85),
            'bandwidth_limit' => $this->getBandwidthLimit($quality),
            'fps' => $this->getFPS($quality),
            'resolution' => $this->getResolution($quality),
        ];
    }

    private function getBandwidthLimit(string $quality): int
    {
        return match($quality) {
            'low' => 500, // kbps
            'medium' => 1000,
            'high' => 2000,
            'hd' => 4000,
            default => 1500 // auto
        };
    }

    private function getFPS(string $quality): int
    {
        return match($quality) {
            'low' => 15,
            'medium' => 24,
            'high' => 30,
            'hd' => 30,
            default => 24 // auto
        };
    }

    private function getResolution(string $quality): array
    {
        return match($quality) {
            'low' => ['width' => 640, 'height' => 480],
            'medium' => ['width' => 1280, 'height' => 720],
            'high' => ['width' => 1920, 'height' => 1080],
            'hd' => ['width' => 2560, 'height' => 1440],
            default => ['width' => 1280, 'height' => 720] // auto
        };
    }

    private function generateMetadata(): array
    {
        return [
            'created_ip' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'platform' => $this->faker->randomElement(['ios', 'android', 'web', 'windows', 'mac']),
            'browser' => $this->faker->randomElement(['chrome', 'firefox', 'safari', 'edge']),
            'device_type' => $this->faker->randomElement(['mobile', 'tablet', 'desktop']),
            'network_type' => $this->faker->randomElement(['wifi', '4g', '5g', 'ethernet']),
            'app_version' => $this->faker->regexify('12\.[0-9]\.[0-9]'),
            'webrtc_version' => $this->faker->regexify('1\.[0-9]\.[0-9]'),
            'codec_support' => [
                'video' => $this->faker->randomElements(['h264', 'vp8', 'vp9', 'av1'], 2),
                'audio' => $this->faker->randomElements(['opus', 'g722', 'pcmu', 'pcma'], 2),
            ],
        ];
    }

    private function generateQualityMetrics(string $status): ?array
    {
        if (!in_array($status, ['connected', 'ended'])) {
            return null;
        }

        return [
            'connection_quality' => $this->faker->randomElement(['excellent', 'good', 'fair', 'poor']),
            'packet_loss' => $this->faker->randomFloat(2, 0, 5), // percentage
            'jitter' => $this->faker->numberBetween(1, 50), // milliseconds
            'latency' => $this->faker->numberBetween(20, 300), // milliseconds
            'bandwidth_used' => $this->faker->numberBetween(200, 3000), // kbps
            'audio_quality_score' => $this->faker->numberBetween(60, 100),
            'video_quality_score' => $this->faker->numberBetween(50, 100),
            'connection_stability' => $this->faker->randomFloat(2, 0.7, 1.0),
            'frame_rate_actual' => $this->faker->numberBetween(15, 30),
            'resolution_actual' => [
                'width' => $this->faker->numberBetween(640, 1920),
                'height' => $this->faker->numberBetween(480, 1080),
            ],
            'cpu_usage' => $this->faker->numberBetween(20, 80), // percentage
            'memory_usage' => $this->faker->numberBetween(100, 500), // MB
        ];
    }

    // Estados específicos
    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'connected',
            'started_at' => $this->faker->dateTimeBetween('-2 hours', '-5 minutes'),
            'connected_at' => $this->faker->dateTimeBetween('-2 hours', '-5 minutes'),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ended',
            'duration_seconds' => $this->faker->numberBetween(60, 3600),
            'end_reason' => 'normal',
        ]);
    }
}