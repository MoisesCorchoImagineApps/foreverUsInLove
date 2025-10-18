<?php

namespace Database\Factories;

use App\Models\Chat\CallHistory;
use App\Models\Chat\VideoCall;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallHistoryFactory extends Factory
{
    protected $model = CallHistory::class;

    public function definition(): array
    {
        $callType = $this->faker->randomElement(['private', 'group', 'conference', 'emergency']);
        $callStatus = $this->faker->randomElement(['connected', 'ended', 'failed']);
        $userRole = $this->faker->randomElement(['caller', 'participant']);
        $createdAt = $this->faker->dateTimeBetween('-6 months', 'now');
        
        return [
            'video_call_id' => VideoCall::factory(),
            'user_id' => User::factory(),
            'call_type' => $callType,
            'call_status' => $callStatus,
            'user_role' => $userRole,
            'duration_seconds' => $this->getDurationSeconds($callStatus),
            'end_reason' => $this->getEndReason($callStatus),
            'quality_score' => $this->faker->optional(0.8)->randomFloat(2, 30, 100),
            'quality_metrics' => $this->generateQualityMetrics($callStatus),
            'engagement_metrics' => $this->generateEngagementMetrics($callStatus),
            'rating' => $this->faker->optional(0.4)->numberBetween(1, 5),
            'feedback' => $this->faker->optional(0.2)->sentence(),
            'metadata' => $this->generateMetadata(),
            'participant_summary' => $this->generateParticipantSummary($callType),
            'was_video_enabled' => $this->faker->boolean(80),
            'was_audio_enabled' => $this->faker->boolean(95),
            'was_screen_shared' => $this->faker->boolean(25),
            'was_recorded' => $this->faker->boolean(20),
            'call_started_at' => $this->faker->dateTimeBetween($createdAt, $createdAt->copy()->addMinutes(2)),
            'call_ended_at' => $this->faker->optional(0.9)->dateTimeBetween($createdAt, 'now'),
            'created_at' => $createdAt,
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    private function getDurationSeconds(string $callStatus): ?int
    {
        return match($callStatus) {
            'connected', 'ended' => $this->faker->numberBetween(30, 7200), // 30 seconds to 2 hours
            'failed' => null,
            default => $this->faker->optional(0.3)->numberBetween(10, 300)
        };
    }

    private function getEndReason(string $callStatus): ?string
    {
        return match($callStatus) {
            'ended' => $this->faker->randomElement(['normal', 'timeout']),
            'failed' => $this->faker->randomElement(['error', 'declined', 'busy', 'no_answer', 'canceled']),
            default => $this->faker->optional()->randomElement(['normal', 'timeout', 'error'])
        };
    }

    private function generateQualityMetrics(string $callStatus): ?array
    {
        if ($callStatus === 'failed') {
            return null;
        }

        return [
            'connection_quality' => $this->faker->randomElement(['excellent', 'good', 'fair', 'poor']),
            'packet_loss_percentage' => $this->faker->randomFloat(2, 0, 8),
            'jitter_ms' => $this->faker->numberBetween(1, 100),
            'latency_ms' => $this->faker->numberBetween(20, 400),
            'bandwidth_used_kbps' => $this->faker->numberBetween(200, 3500),
            'video_resolution' => [
                'width' => $this->faker->randomElement([640, 1280, 1920]),
                'height' => $this->faker->randomElement([480, 720, 1080]),
            ],
            'audio_codec' => $this->faker->randomElement(['opus', 'g722', 'pcmu']),
            'video_codec' => $this->faker->randomElement(['h264', 'vp8', 'vp9']),
            'frame_rate_avg' => $this->faker->numberBetween(15, 30),
            'connection_drops' => $this->faker->numberBetween(0, 5),
            'reconnection_attempts' => $this->faker->numberBetween(0, 3),
        ];
    }

    private function generateEngagementMetrics(string $callStatus): ?array
    {
        if ($callStatus === 'failed') {
            return [
                'time_to_answer_seconds' => null,
                'interaction_score' => 0,
                'engagement_level' => 'none',
            ];
        }

        $durationSeconds = $this->getDurationSeconds($callStatus);
        
        return [
            'time_to_answer_seconds' => $this->faker->numberBetween(2, 30),
            'video_toggle_count' => $this->faker->numberBetween(0, 8),
            'audio_toggle_count' => $this->faker->numberBetween(0, 3),
            'screen_share_duration_seconds' => $this->faker->optional(0.3)->numberBetween(30, min(1800, $durationSeconds ?? 300)),
            'chat_messages_sent' => $this->faker->numberBetween(0, 15),
            'interaction_score' => $this->faker->numberBetween(1, 100),
            'engagement_level' => $this->faker->randomElement(['low', 'medium', 'high', 'very_high']),
            'user_initiated_features' => $this->faker->randomElements([
                'mute_toggle', 'video_toggle', 'screen_share', 'chat', 'recording', 'background_blur'
            ], rand(0, 4)),
            'technical_issues_reported' => $this->faker->numberBetween(0, 3),
            'call_quality_rating' => $this->faker->optional(0.6)->numberBetween(1, 5),
        ];
    }

    private function generateMetadata(): array
    {
        return [
            'participant_role' => $this->faker->randomElement(['host', 'guest', 'moderator', 'participant']),
            'join_method' => $this->faker->randomElement(['direct_call', 'invitation', 'link_join', 'phone_dial_in']),
            'device_info' => [
                'platform' => $this->faker->randomElement(['ios', 'android', 'web', 'windows', 'mac']),
                'browser' => $this->faker->optional()->randomElement(['chrome', 'firefox', 'safari', 'edge']),
                'app_version' => $this->faker->regexify('12\.[0-9]\.[0-9]'),
                'device_model' => $this->faker->optional()->randomElement([
                    'iPhone 14 Pro', 'Samsung Galaxy S23', 'iPad Pro', 'MacBook Pro', 'Windows PC'
                ]),
            ],
            'network_info' => [
                'connection_type' => $this->faker->randomElement(['wifi', '4g', '5g', 'ethernet']),
                'ip_address' => $this->faker->ipv4(),
                'location' => [
                    'country' => $this->faker->country(),
                    'city' => $this->faker->city(),
                    'timezone' => $this->faker->timezone(),
                ],
            ],
            'feature_usage' => [
                'virtual_background_used' => $this->faker->boolean(40),
                'noise_cancellation_used' => $this->faker->boolean(70),
                'hand_raise_used' => $this->faker->boolean(20),
                'reactions_used' => $this->faker->boolean(60),
                'breakout_rooms_joined' => $this->faker->boolean(15),
            ],
        ];
    }

    private function generateParticipantSummary(string $callType): array
    {
        $participantCount = match($callType) {
            'private' => 2,
            'group' => $this->faker->numberBetween(3, 8),
            'conference' => $this->faker->numberBetween(5, 50),
            'emergency' => $this->faker->numberBetween(2, 6),
            default => 2
        };

        return [
            'count' => $participantCount,
            'max_concurrent' => $participantCount,
            'joined_late_count' => $this->faker->numberBetween(0, max(1, intval($participantCount * 0.3))),
            'left_early_count' => $this->faker->numberBetween(0, max(1, intval($participantCount * 0.4))),
            'average_duration_seconds' => $this->faker->numberBetween(300, 3600),
            'host_duration_seconds' => $this->faker->numberBetween(600, 4000),
            'participants_with_video' => $this->faker->numberBetween(1, $participantCount),
            'participants_with_audio' => $this->faker->numberBetween(intval($participantCount * 0.8), $participantCount),
            'total_speaking_time_seconds' => $this->faker->numberBetween(60, 1800),
            'participant_engagement_scores' => array_fill(0, $participantCount, $this->faker->numberBetween(20, 100)),
        ];
    }

    // Estados específicos para diferentes tipos de historial
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_status' => 'ended',
            'duration_seconds' => $this->faker->numberBetween(60, 3600),
            'end_reason' => 'normal',
            'quality_score' => $this->faker->numberBetween(60, 100),
            'rating' => $this->faker->numberBetween(3, 5),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_status' => 'failed',
            'duration_seconds' => null,
            'end_reason' => $this->faker->randomElement(['error', 'declined', 'no_answer', 'busy']),
            'quality_score' => null,
            'quality_metrics' => null,
            'rating' => null,
        ]);
    }

    public function missed(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_status' => 'failed',
            'user_role' => 'participant',
            'duration_seconds' => null,
            'end_reason' => 'no_answer',
            'quality_score' => null,
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_status' => 'failed',
            'end_reason' => 'declined',
            'duration_seconds' => null,
            'engagement_metrics' => [
                'time_to_answer_seconds' => null,
                'interaction_score' => 0,
                'engagement_level' => 'none',
            ],
        ]);
    }

    public function shortCall(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_status' => 'ended',
            'duration_seconds' => $this->faker->numberBetween(10, 180),
            'end_reason' => $this->faker->randomElement(['normal', 'timeout']),
        ]);
    }

    public function longCall(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_status' => 'ended',
            'duration_seconds' => $this->faker->numberBetween(1800, 7200), // 30 minutes to 2 hours
            'end_reason' => 'normal',
            'quality_score' => $this->faker->numberBetween(70, 100),
            'engagement_metrics' => array_merge($attributes['engagement_metrics'] ?? [], [
                'engagement_level' => 'high',
                'interaction_score' => $this->faker->numberBetween(70, 100),
            ]),
        ]);
    }

    public function highQuality(): static
    {
        return $this->state(fn (array $attributes) => [
            'quality_score' => $this->faker->numberBetween(80, 100),
            'quality_metrics' => array_merge($attributes['quality_metrics'] ?? [], [
                'connection_quality' => 'excellent',
                'packet_loss_percentage' => $this->faker->randomFloat(2, 0, 1),
                'jitter_ms' => $this->faker->numberBetween(1, 15),
                'latency_ms' => $this->faker->numberBetween(20, 80),
            ]),
            'rating' => $this->faker->numberBetween(4, 5),
        ]);
    }

    public function lowQuality(): static
    {
        return $this->state(fn (array $attributes) => [
            'quality_score' => $this->faker->numberBetween(10, 40),
            'quality_metrics' => array_merge($attributes['quality_metrics'] ?? [], [
                'connection_quality' => 'poor',
                'packet_loss_percentage' => $this->faker->randomFloat(2, 3, 15),
                'jitter_ms' => $this->faker->numberBetween(50, 200),
                'latency_ms' => $this->faker->numberBetween(200, 500),
                'connection_drops' => $this->faker->numberBetween(2, 8),
            ]),
            'rating' => $this->faker->numberBetween(1, 2),
            'feedback' => $this->faker->randomElement([
                'Muy mala calidad de audio',
                'Video se cortaba constantemente',
                'Problemas de conexión durante toda la llamada',
                'No se podía escuchar bien'
            ]),
        ]);
    }

    public function caller(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_role' => 'caller',
            'engagement_metrics' => array_merge($attributes['engagement_metrics'] ?? [], [
                'time_to_answer_seconds' => 0, // El caller no tiene tiempo de respuesta
                'call_initiated' => true,
            ]),
        ]);
    }

    public function participant(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_role' => 'participant',
            'engagement_metrics' => array_merge($attributes['engagement_metrics'] ?? [], [
                'time_to_answer_seconds' => $this->faker->numberBetween(2, 30),
                'call_initiated' => false,
            ]),
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_type' => 'private',
            'participant_summary' => [
                'count' => 2,
                'max_concurrent' => 2,
                'joined_late_count' => 0,
                'left_early_count' => 0,
                'participants_with_video' => $this->faker->numberBetween(1, 2),
                'participants_with_audio' => 2,
            ],
        ]);
    }

    public function group(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_type' => 'group',
            'participant_summary' => $this->generateParticipantSummary('group'),
        ]);
    }

    public function conference(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_type' => 'conference',
            'was_recorded' => $this->faker->boolean(80),
            'participant_summary' => $this->generateParticipantSummary('conference'),
        ]);
    }

    public function emergency(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_type' => 'emergency',
            'was_recorded' => $this->faker->boolean(95), // Emergency calls usually recorded
            'priority_level' => 'high',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'emergency_type' => $this->faker->randomElement(['medical', 'safety', 'technical', 'urgent']),
                'response_time_seconds' => $this->faker->numberBetween(5, 60),
            ]),
        ]);
    }

    public function withRating(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $this->faker->numberBetween(1, 5),
            'feedback' => $this->faker->optional(0.7)->randomElement([
                'Excelente calidad de llamada',
                'Muy buena experiencia',
                'Audio claro y sin interrupciones',
                'Video de alta calidad',
                'Algunas interferencias menores',
                'Calidad aceptable',
                'Problemas técnicos ocasionales',
                'Mala calidad de conexión',
                'Muchos cortes durante la llamada'
            ]),
        ]);
    }

    public function recorded(): static
    {
        return $this->state(fn (array $attributes) => [
            'was_recorded' => true,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'recording_duration_seconds' => $attributes['duration_seconds'] ?? $this->faker->numberBetween(300, 3600),
                'recording_size_mb' => $this->faker->numberBetween(50, 500),
                'recording_quality' => $this->faker->randomElement(['medium', 'high']),
                'storage_location' => 'recordings/' . $this->faker->uuid() . '.mp4',
            ]),
        ]);
    }

    public function withScreenShare(): static
    {
        return $this->state(fn (array $attributes) => [
            'was_screen_shared' => true,
            'engagement_metrics' => array_merge($attributes['engagement_metrics'] ?? [], [
                'screen_share_duration_seconds' => $this->faker->numberBetween(60, min(1800, $attributes['duration_seconds'] ?? 600)),
                'screen_share_quality' => $this->faker->randomElement(['good', 'excellent']),
            ]),
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function highEngagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'engagement_metrics' => array_merge($attributes['engagement_metrics'] ?? [], [
                'engagement_level' => 'very_high',
                'interaction_score' => $this->faker->numberBetween(80, 100),
                'video_toggle_count' => $this->faker->numberBetween(0, 3),
                'chat_messages_sent' => $this->faker->numberBetween(5, 20),
                'user_initiated_features' => [
                    'screen_share', 'chat', 'mute_toggle', 'video_toggle', 'reactions'
                ],
            ]),
            'rating' => $this->faker->numberBetween(4, 5),
        ]);
    }

    public function lowEngagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'engagement_metrics' => array_merge($attributes['engagement_metrics'] ?? [], [
                'engagement_level' => 'low',
                'interaction_score' => $this->faker->numberBetween(1, 30),
                'video_toggle_count' => 0,
                'chat_messages_sent' => 0,
                'user_initiated_features' => [],
            ]),
            'rating' => $this->faker->optional(0.3)->numberBetween(1, 3),
        ]);
    }
}