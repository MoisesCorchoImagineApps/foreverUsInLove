<?php

namespace Database\Factories;

use App\Models\Chat\Chat;
use App\Models\Chat\GroupChat;
use App\Models\Chat\VideoCall;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatFactory extends Factory
{
    protected $model = Chat::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['private', 'group', 'video_call']);
        $status = $this->faker->randomElement(['active', 'archived', 'blocked', 'inactive']);
        $createdAt = $this->faker->dateTimeBetween('-6 months', 'now');
        
        return [
            'creator_id' => User::factory(),
            'type' => $type,
            'status' => $status,
            'chatable_type' => $this->getChatableType($type),
            'chatable_id' => null, // Se asignará después de crear el chatable
            'is_encrypted' => $this->faker->boolean(30),
            'is_moderated' => $this->faker->boolean(20),
            'settings' => $this->generateSettings(),
            'metadata' => $this->generateMetadata(),
            'last_message_at' => $this->faker->optional(0.8)->dateTimeBetween($createdAt, 'now'),
            'last_activity_at' => $this->faker->optional(0.9)->dateTimeBetween($createdAt, 'now'),
            'archived_at' => $status === 'archived' ? $this->faker->dateTimeBetween($createdAt, 'now') : null,
            'blocked_at' => $status === 'blocked' ? $this->faker->dateTimeBetween($createdAt, 'now') : null,
            'blocked_by_user_id' => $status === 'blocked' ? User::factory() : null,
            'blocked_reason' => $status === 'blocked' ? $this->faker->randomElement([
                'inappropriate_content', 'harassment', 'spam', 'fake_profile', 'other'
            ]) : null,
            'message_count' => $this->faker->numberBetween(0, 500),
            'unread_count' => $this->faker->numberBetween(0, 20),
            'participant_count' => $this->getParticipantCount($type),
            'created_at' => $createdAt,
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    private function getChatableType(string $type): ?string
    {
        return match($type) {
            'group' => GroupChat::class,
            'video_call' => VideoCall::class,
            default => null // private chats don't have chatable
        };
    }

    private function getParticipantCount(string $type): int
    {
        return match($type) {
            'private' => 2,
            'group' => $this->faker->numberBetween(3, 50),
            'video_call' => $this->faker->numberBetween(2, 12),
            default => 2
        };
    }

    private function generateSettings(): array
    {
        return [
            'notifications_enabled' => $this->faker->boolean(85),
            'sound_enabled' => $this->faker->boolean(75),
            'typing_indicators' => $this->faker->boolean(90),
            'read_receipts' => $this->faker->boolean(80),
            'auto_archive_after_days' => $this->faker->randomElement([30, 60, 90, 180, 365]),
            'message_retention_days' => $this->faker->randomElement([365, 730, 1095]),
            'allow_media' => $this->faker->boolean(95),
            'allow_voice' => $this->faker->boolean(90),
            'allow_files' => $this->faker->boolean(85),
            'max_file_size_mb' => $this->faker->randomElement([10, 25, 50, 100]),
        ];
    }

    private function generateMetadata(): array
    {
        return [
            'created_ip' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'platform' => $this->faker->randomElement(['ios', 'android', 'web', 'windows', 'mac']),
            'app_version' => $this->faker->regexify('12\.[0-9]\.[0-9]'),
            'feature_flags' => [
                'enhanced_encryption' => $this->faker->boolean(40),
                'ai_moderation' => $this->faker->boolean(30),
                'message_reactions' => $this->faker->boolean(80),
                'threaded_replies' => $this->faker->boolean(60),
            ],
        ];
    }

    // Estados específicos para diferentes tipos de chats
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'private',
            'chatable_type' => null,
            'chatable_id' => null,
            'participant_count' => 2,
            'is_encrypted' => $this->faker->boolean(60),
        ]);
    }

    public function group(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'group',
            'chatable_type' => GroupChat::class,
            'participant_count' => $this->faker->numberBetween(3, 50),
            'is_moderated' => $this->faker->boolean(40),
        ]);
    }

    public function videoCall(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'video_call',
            'chatable_type' => VideoCall::class,
            'participant_count' => $this->faker->numberBetween(2, 12),
            'is_encrypted' => true,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'archived_at' => null,
            'blocked_at' => null,
            'blocked_by_user_id' => null,
            'blocked_reason' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
            'archived_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'blocked',
            'blocked_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'blocked_by_user_id' => User::factory(),
            'blocked_reason' => $this->faker->randomElement([
                'inappropriate_content', 'harassment', 'spam', 'fake_profile'
            ]),
        ]);
    }

    public function withRecentActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_message_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
            'last_activity_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'message_count' => $this->faker->numberBetween(10, 100),
            'unread_count' => $this->faker->numberBetween(1, 10),
        ]);
    }

    public function withHighActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_count' => $this->faker->numberBetween(100, 1000),
            'last_message_at' => $this->faker->dateTimeBetween('-6 hours', 'now'),
            'last_activity_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    public function encrypted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_encrypted' => true,
            'settings' => array_merge($attributes['settings'] ?? [], [
                'enhanced_security' => true,
                'message_expiry_enabled' => $this->faker->boolean(60),
                'screenshot_protection' => $this->faker->boolean(40),
            ]),
        ]);
    }

    public function moderated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_moderated' => true,
            'settings' => array_merge($attributes['settings'] ?? [], [
                'auto_moderation' => true,
                'content_filtering' => true,
                'profanity_filter' => true,
            ]),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'last_activity_at' => $this->faker->dateTimeBetween('-30 days', '-7 days'),
            'message_count' => $this->faker->numberBetween(0, 10),
            'unread_count' => 0,
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_encrypted' => true,
            'settings' => array_merge($attributes['settings'] ?? [], [
                'max_file_size_mb' => 100,
                'message_retention_days' => 1095,
                'enhanced_features' => true,
                'priority_support' => true,
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'feature_flags' => [
                    'premium_features' => true,
                    'enhanced_encryption' => true,
                    'ai_moderation' => true,
                    'advanced_analytics' => true,
                ],
            ]),
        ]);
    }
}