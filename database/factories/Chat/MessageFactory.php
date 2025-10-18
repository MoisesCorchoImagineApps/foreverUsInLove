<?php

namespace Database\Factories;

use App\Models\Chat\Message;
use App\Models\Chat\Chat;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement([
            'text', 'image', 'video', 'voice', 'file', 'sticker', 'gif', 'system', 'icebreaker'
        ]);
        $status = $this->faker->randomElement(['sending', 'sent', 'delivered', 'read', 'failed']);
        $createdAt = $this->faker->dateTimeBetween('-3 months', 'now');
        
        return [
            'chat_id' => Chat::factory(),
            'sender_id' => User::factory(),
            'parent_id' => $this->faker->optional(0.15)->randomNumber(), // 15% son replies
            'type' => $type,
            'status' => $status,
            'content' => $this->generateContent($type),
            'encrypted_content' => null, // Se genera si is_encrypted = true
            'attachments' => $this->generateAttachments($type),
            'metadata' => $this->generateMetadata(),
            'read_by' => $this->generateReadBy($status),
            'delivered_to' => $this->generateDeliveredTo($status),
            'moderation_status' => $this->faker->randomElement(['pending', 'approved', 'rejected', 'flagged']),
            'moderation_data' => $this->faker->optional(0.2)->randomElements([
                'content_flag', 'spam_detected', 'profanity_detected', 'manual_review'
            ], 1),
            'moderated_at' => $this->faker->optional(0.3)->dateTimeBetween($createdAt, 'now'),
            'moderated_by_user_id' => $this->faker->optional(0.3)->randomNumber(),
            'edited_at' => $this->faker->optional(0.1)->dateTimeBetween($createdAt, 'now'),
            'edit_history' => $this->faker->optional(0.1)->randomElements([
                ['content' => $this->faker->sentence(), 'edited_at' => $createdAt],
            ], 1),
            'deleted_by_sender_at' => $this->faker->optional(0.05)->dateTimeBetween($createdAt, 'now'),
            'delivered_at' => $status !== 'sending' ? $this->faker->dateTimeBetween($createdAt, 'now') : null,
            'read_at' => $status === 'read' ? $this->faker->dateTimeBetween($createdAt, 'now') : null,
            'failed_at' => $status === 'failed' ? $this->faker->dateTimeBetween($createdAt, 'now') : null,
            'failure_reason' => $status === 'failed' ? $this->faker->randomElement([
                'network_error', 'recipient_offline', 'content_blocked', 'size_limit_exceeded'
            ]) : null,
            'is_encrypted' => $this->faker->boolean(20),
            'is_edited' => $this->faker->boolean(10),
            'is_deleted_by_sender' => $this->faker->boolean(5),
            'is_system_message' => $type === 'system',
            'sentiment_score' => $type === 'text' ? $this->faker->randomFloat(2, -1, 1) : null,
            'spam_probability' => $this->faker->randomFloat(2, 0, 1),
            'reply_count' => $this->faker->numberBetween(0, 10),
            'reaction_count' => $this->faker->numberBetween(0, 15),
            'scheduled_at' => $this->faker->optional(0.02)->dateTimeBetween('now', '+7 days'),
            'expires_at' => $this->faker->optional(0.05)->dateTimeBetween('+1 day', '+30 days'),
            'created_at' => $createdAt,
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    private function generateContent(string $type): ?string
    {
        return match($type) {
            'text' => $this->faker->realText($this->faker->numberBetween(10, 300)),
            'icebreaker' => $this->faker->randomElement([
                '¡Hola! Me encanta tu perfil 😊',
                '¿Qué tal? Vi que también te gusta viajar ✈️',
                'Hola, ¿cómo está tu día?',
                '¡Hey! ¿Tienes planes para el fin de semana?',
                'Me gusta tu foto en la playa, ¿dónde fue?',
                'Hola, ¿también eres fan de la pizza? 🍕'
            ]),
            'system' => $this->faker->randomElement([
                'Usuario se unió al chat',
                'Usuario abandonó el chat',
                'Configuración de chat actualizada',
                'Mensaje eliminado por moderación',
                'Chat archivado automáticamente',
                'Llamada iniciada',
                'Llamada terminada'
            ]),
            'image', 'video', 'voice', 'file', 'sticker', 'gif' => null, // Contenido en attachments
            default => $this->faker->sentence()
        };
    }

    private function generateAttachments(string $type): array
    {
        if (!in_array($type, ['image', 'video', 'voice', 'file', 'sticker', 'gif'])) {
            return [];
        }

        return match($type) {
            'image' => [[
                'type' => 'image',
                'filename' => $this->faker->word() . '.jpg',
                'path' => 'chat/images/' . $this->faker->uuid() . '.jpg',
                'size' => $this->faker->numberBetween(100000, 5000000), // 100KB - 5MB
                'mime_type' => 'image/jpeg',
                'width' => $this->faker->numberBetween(400, 2000),
                'height' => $this->faker->numberBetween(400, 2000),
                'thumbnail_path' => 'chat/thumbnails/' . $this->faker->uuid() . '_thumb.jpg',
            ]],
            'video' => [[
                'type' => 'video',
                'filename' => $this->faker->word() . '.mp4',
                'path' => 'chat/videos/' . $this->faker->uuid() . '.mp4',
                'size' => $this->faker->numberBetween(5000000, 100000000), // 5MB - 100MB
                'mime_type' => 'video/mp4',
                'duration' => $this->faker->numberBetween(5, 300), // seconds
                'width' => $this->faker->randomElement([720, 1080, 1920]),
                'height' => $this->faker->randomElement([480, 720, 1080]),
                'thumbnail_path' => 'chat/thumbnails/' . $this->faker->uuid() . '_thumb.jpg',
            ]],
            'voice' => [[
                'type' => 'voice',
                'filename' => 'voice_' . $this->faker->uuid() . '.m4a',
                'path' => 'chat/voice/' . $this->faker->uuid() . '.m4a',
                'size' => $this->faker->numberBetween(50000, 2000000), // 50KB - 2MB
                'mime_type' => 'audio/m4a',
                'duration' => $this->faker->numberBetween(1, 300), // seconds
                'waveform' => array_fill(0, 50, $this->faker->numberBetween(0, 100)),
            ]],
            'file' => [[
                'type' => 'file',
                'filename' => $this->faker->word() . '.' . $this->faker->fileExtension(),
                'path' => 'chat/files/' . $this->faker->uuid(),
                'size' => $this->faker->numberBetween(1000, 50000000), // 1KB - 50MB
                'mime_type' => $this->faker->mimeType(),
            ]],
            'sticker' => [[
                'type' => 'sticker',
                'sticker_pack' => $this->faker->randomElement(['love', 'funny', 'animals', 'emotions']),
                'sticker_id' => $this->faker->numberBetween(1, 100),
                'filename' => 'sticker_' . $this->faker->uuid() . '.webp',
                'path' => 'stickers/' . $this->faker->uuid() . '.webp',
            ]],
            'gif' => [[
                'type' => 'gif',
                'filename' => $this->faker->word() . '.gif',
                'path' => 'chat/gifs/' . $this->faker->uuid() . '.gif',
                'size' => $this->faker->numberBetween(500000, 10000000), // 500KB - 10MB
                'width' => $this->faker->numberBetween(200, 600),
                'height' => $this->faker->numberBetween(200, 600),
                'duration' => $this->faker->numberBetween(1, 10), // seconds
            ]],
            default => []
        };
    }

    private function generateMetadata(): array
    {
        return [
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'platform' => $this->faker->randomElement(['ios', 'android', 'web', 'windows', 'mac']),
            'app_version' => $this->faker->regexify('12\.[0-9]\.[0-9]'),
            'location' => $this->faker->optional(0.3)->randomElements([
                'latitude' => $this->faker->latitude(),
                'longitude' => $this->faker->longitude(),
                'accuracy' => $this->faker->numberBetween(5, 100),
            ]),
            'typing_time_ms' => $this->faker->numberBetween(1000, 30000),
            'draft_count' => $this->faker->numberBetween(0, 5),
            'mentions' => $this->faker->optional(0.2)->randomElements(range(1, 20), 2),
            'hashtags' => $this->faker->optional(0.1)->words(3),
        ];
    }

    private function generateReadBy(string $status): array
    {
        if ($status !== 'read') {
            return [];
        }

        $readers = [];
        $userCount = $this->faker->numberBetween(1, 8);
        
        for ($i = 0; $i < $userCount; $i++) {
            $readers[(string)$this->faker->unique()->numberBetween(1, 100)] = 
                $this->faker->dateTimeBetween('-1 day', 'now')->toIso8601String();
        }

        return $readers;
    }

    private function generateDeliveredTo(string $status): array
    {
        if ($status === 'sending' || $status === 'failed') {
            return [];
        }

        $delivered = [];
        $userCount = $this->faker->numberBetween(1, 10);
        
        for ($i = 0; $i < $userCount; $i++) {
            $delivered[(string)$this->faker->unique()->numberBetween(1, 100)] = 
                $this->faker->dateTimeBetween('-2 days', 'now')->toIso8601String();
        }

        return $delivered;
    }

    // Estados específicos para diferentes tipos de mensajes
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'content' => $this->faker->realText($this->faker->numberBetween(10, 500)),
            'attachments' => [],
            'sentiment_score' => $this->faker->randomFloat(2, -1, 1),
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image',
            'content' => $this->faker->optional()->sentence(),
            'attachments' => $this->generateAttachments('image'),
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'video',
            'content' => $this->faker->optional()->sentence(),
            'attachments' => $this->generateAttachments('video'),
        ]);
    }

    public function voice(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'voice',
            'content' => null,
            'attachments' => $this->generateAttachments('voice'),
        ]);
    }

    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file',
            'content' => $this->faker->optional()->sentence(),
            'attachments' => $this->generateAttachments('file'),
        ]);
    }

    public function sticker(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sticker',
            'content' => null,
            'attachments' => $this->generateAttachments('sticker'),
        ]);
    }

    public function gif(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'gif',
            'content' => $this->faker->optional()->sentence(),
            'attachments' => $this->generateAttachments('gif'),
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'system',
            'is_system_message' => true,
            'content' => $this->faker->randomElement([
                'Usuario se unió al chat',
                'Usuario abandonó el chat',
                'Configuración actualizada',
                'Mensaje eliminado',
                'Chat archivado'
            ]),
            'attachments' => [],
        ]);
    }

    public function icebreaker(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'icebreaker',
            'content' => $this->faker->randomElement([
                '¡Hola! Me encanta tu perfil 😊',
                '¿Qué tal? Vi que también te gusta viajar ✈️',
                'Hola, ¿cómo está tu día?',
                '¡Hey! ¿Tienes planes para el fin de semana?',
            ]),
            'attachments' => [],
            'sentiment_score' => $this->faker->randomFloat(2, 0.5, 1),
        ]);
    }

    public function reply(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => Message::factory(),
        ]);
    }

    public function withReplies(): static
    {
        return $this->state(fn (array $attributes) => [
            'reply_count' => $this->faker->numberBetween(1, 20),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'delivered_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'failed_at' => null,
            'failure_reason' => null,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'delivered_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'delivered_to' => $this->generateDeliveredTo('delivered'),
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'read',
            'delivered_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'read_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'read_by' => $this->generateReadBy('read'),
            'delivered_to' => $this->generateDeliveredTo('read'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failed_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'failure_reason' => $this->faker->randomElement([
                'network_error', 'recipient_offline', 'content_blocked', 'size_limit_exceeded'
            ]),
            'delivered_at' => null,
            'read_at' => null,
        ]);
    }

    public function encrypted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_encrypted' => true,
            'encrypted_content' => base64_encode($attributes['content'] ?? ''),
        ]);
    }

    public function edited(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_edited' => true,
            'edited_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'edit_history' => [
                [
                    'content' => $this->faker->sentence(),
                    'edited_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now')->toIso8601String(),
                ],
            ],
        ]);
    }

    public function deletedBySender(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_deleted_by_sender' => true,
            'deleted_by_sender_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'content' => null,
            'attachments' => [],
        ]);
    }

    public function flagged(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => 'flagged',
            'moderation_data' => [
                'flag_type' => $this->faker->randomElement(['spam', 'inappropriate', 'harassment']),
                'flagged_by_user_id' => $this->faker->numberBetween(1, 100),
                'flag_reason' => $this->faker->sentence(),
            ],
            'moderated_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => 'approved',
            'moderated_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'moderated_by_user_id' => $this->faker->numberBetween(1, 20),
        ]);
    }

    public function withReactions(): static
    {
        return $this->state(fn (array $attributes) => [
            'reaction_count' => $this->faker->numberBetween(1, 25),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'reactions' => [
                    '❤️' => $this->faker->numberBetween(0, 10),
                    '😂' => $this->faker->numberBetween(0, 8),
                    '👍' => $this->faker->numberBetween(0, 6),
                    '😮' => $this->faker->numberBetween(0, 4),
                    '😢' => $this->faker->numberBetween(0, 3),
                ],
            ]),
        ]);
    }

    public function longText(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'content' => $this->faker->realText($this->faker->numberBetween(500, 1500)),
        ]);
    }

    public function shortText(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'content' => $this->faker->words($this->faker->numberBetween(1, 5), true),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+7 days'),
            'status' => 'sending',
        ]);
    }

    public function expiring(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('+1 hour', '+24 hours'),
        ]);
    }

    public function highEngagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'reply_count' => $this->faker->numberBetween(5, 30),
            'reaction_count' => $this->faker->numberBetween(10, 50),
            'sentiment_score' => $this->faker->randomFloat(2, 0.3, 1),
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }
}