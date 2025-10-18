<?php

namespace Database\Factories;

use App\Models\Chat\GroupChat;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupChatFactory extends Factory
{
    protected $model = GroupChat::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['public', 'private', 'happy_hour', 'themed', 'event_based']);
        $status = $this->faker->randomElement(['active', 'inactive', 'archived', 'suspended']);
        $theme = $this->faker->randomElement([
            'casual_chat', 'hobby_lovers', 'foodies', 'travelers', 'fitness',
            'movies_tv', 'music', 'books', 'games', 'professionals'
        ]);
        
        return [
            'creator_id' => User::factory(),
            'name' => $this->generateGroupName($type, $theme),
            'description' => $this->faker->optional(0.8)->paragraph(2),
            'type' => $type,
            'status' => $status,
            'avatar_url' => $this->faker->optional(0.6)->imageUrl(200, 200, 'people'),
            'settings' => $this->generateSettings($type),
            'metadata' => $this->generateMetadata(),
            'theme_data' => $this->generateThemeData($theme),
            'event_data' => $type === 'event_based' ? $this->generateEventData() : null,
            'privacy_settings' => $this->generatePrivacySettings($type),
            'moderation_settings' => $this->generateModerationSettings(),
            'banned_users' => $this->faker->optional(0.1)->randomElements(
                range(1, 20), $this->faker->numberBetween(1, 5)
            ),
            'muted_users' => $this->faker->optional(0.2)->randomElements(
                range(1, 20), $this->faker->numberBetween(1, 3)
            ),
            'member_count' => $this->getMemberCount($type),
            'max_members' => $this->getMaxMembers($type),
            'message_count' => $this->faker->numberBetween(0, 1500),
            'activity_score' => $this->faker->numberBetween(0, 100),
            'last_activity_at' => $this->faker->optional(0.9)->dateTimeBetween('-7 days', 'now'),
            'archived_at' => $status === 'archived' ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'event_starts_at' => $type === 'event_based' ? $this->faker->dateTimeBetween('now', '+30 days') : null,
            'event_ends_at' => $type === 'event_based' ? $this->faker->dateTimeBetween('+30 days', '+60 days') : null,
        ];
    }

    private function generateGroupName(string $type, string $theme): string
    {
        $templates = [
            'public' => [
                'Chat Público {theme}', 'Comunidad {theme}', 'Espacio {theme}', 
                'Hub {theme}', 'Foro {theme}', 'Plaza {theme}'
            ],
            'private' => [
                'Grupo Privado', 'Chat Cerrado', 'Círculo Íntimo', 
                'Espacio Personal', 'Reunión Privada'
            ],
            'happy_hour' => [
                'Happy Hour Virtual', 'After Work Chat', 'Hora Social', 
                'Encuentro Casual', 'Momento Relax', 'Coffee Break Digital'
            ],
            'themed' => [
                'Lovers de {theme}', 'Fanáticos {theme}', 'Club {theme}', 
                'Pasión {theme}', 'Mundo {theme}', 'Adictos a {theme}'
            ],
            'event_based' => [
                'Evento: {event}', 'Reunión {event}', 'Encuentro {event}',
                'Cita {event}', 'Actividad {event}'
            ],
        ];

        $template = $this->faker->randomElement($templates[$type]);
        
        return str_replace(
            ['{theme}', '{event}'],
            [ucfirst(str_replace('_', ' ', $theme)), $this->faker->words(2, true)],
            $template
        );
    }

    private function getMemberCount(string $type): int
    {
        return match($type) {
            'public' => $this->faker->numberBetween(5, 100),
            'private' => $this->faker->numberBetween(3, 20),
            'happy_hour' => $this->faker->numberBetween(8, 50),
            'themed' => $this->faker->numberBetween(10, 80),
            'event_based' => $this->faker->numberBetween(15, 200),
            default => $this->faker->numberBetween(3, 30)
        };
    }

    private function getMaxMembers(string $type): int
    {
        return match($type) {
            'public' => $this->faker->numberBetween(50, 200),
            'private' => $this->faker->numberBetween(10, 30),
            'happy_hour' => 100,
            'themed' => $this->faker->numberBetween(50, 150),
            'event_based' => 200,
            default => $this->faker->numberBetween(20, 50)
        };
    }

    private function generateSettings(string $type): array
    {
        $baseSettings = [
            'allow_member_invites' => $this->faker->boolean(80),
            'require_approval' => $type === 'private' ? $this->faker->boolean(90) : $this->faker->boolean(30),
            'allow_media' => $this->faker->boolean(95),
            'allow_voice' => $this->faker->boolean(85),
            'allow_files' => $this->faker->boolean(75),
            'auto_moderation' => $this->faker->boolean(40),
            'notification_level' => $this->faker->randomElement(['all', 'mentions', 'none']),
        ];

        // Ajustes específicos por tipo
        if ($type === 'happy_hour') {
            $baseSettings['time_limited'] = true;
            $baseSettings['duration_hours'] = $this->faker->numberBetween(1, 4);
        }

        if ($type === 'event_based') {
            $baseSettings['event_features'] = true;
            $baseSettings['countdown_enabled'] = true;
            $baseSettings['attendance_tracking'] = true;
        }

        return $baseSettings;
    }

    private function generateMetadata(): array
    {
        return [
            'created_ip' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'platform' => $this->faker->randomElement(['ios', 'android', 'web']),
            'creation_source' => $this->faker->randomElement([
                'manual', 'suggestion', 'event_import', 'template', 'migration'
            ]),
            'feature_usage' => [
                'voice_messages_used' => $this->faker->boolean(60),
                'file_sharing_used' => $this->faker->boolean(40),
                'polls_created' => $this->faker->numberBetween(0, 10),
                'events_scheduled' => $this->faker->numberBetween(0, 5),
            ],
        ];
    }

    private function generateThemeData(string $theme): array
    {
        $colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8'];
        
        return [
            'theme' => $theme,
            'primary_color' => $this->faker->randomElement($colors),
            'icon' => $this->getThemeIcon($theme),
            'keywords' => $this->getThemeKeywords($theme),
            'welcome_message' => $this->getWelcomeMessage($theme),
            'rules' => $this->getThemeRules($theme),
        ];
    }

    private function getThemeIcon(string $theme): string
    {
        return match($theme) {
            'casual_chat' => '💬',
            'hobby_lovers' => '🎯',
            'foodies' => '🍕',
            'travelers' => '✈️',
            'fitness' => '💪',
            'movies_tv' => '🎬',
            'music' => '🎵',
            'books' => '📚',
            'games' => '🎮',
            'professionals' => '💼',
            'artists' => '🎨',
            'entrepreneurs' => '🚀',
            default => '💭'
        };
    }

    private function getThemeKeywords(string $theme): array
    {
        $keywords = [
            'casual_chat' => ['conversación', 'amigos', 'charla', 'social'],
            'hobby_lovers' => ['aficiones', 'pasatiempos', 'intereses', 'creatividad'],
            'foodies' => ['comida', 'recetas', 'restaurantes', 'cocina'],
            'travelers' => ['viajes', 'aventuras', 'destinos', 'explorar'],
            'fitness' => ['ejercicio', 'salud', 'gimnasio', 'bienestar'],
            'movies_tv' => ['películas', 'series', 'entretenimiento', 'cine'],
            'music' => ['música', 'canciones', 'artistas', 'conciertos'],
            'books' => ['libros', 'lectura', 'literatura', 'escritores'],
            'games' => ['juegos', 'gaming', 'videojuegos', 'diversión'],
            'professionals' => ['trabajo', 'carrera', 'networking', 'negocios'],
        ];

        return $keywords[$theme] ?? ['general', 'chat', 'comunidad'];
    }

    private function getWelcomeMessage(string $theme): string
    {
        return match($theme) {
            'casual_chat' => '¡Bienvenido/a! Aquí puedes charlar de lo que quieras 😊',
            'foodies' => '¡Bienvenido/a al paraíso gastronómico! Comparte tus recetas favoritas 🍽️',
            'travelers' => '¡Aventurero/a! Comparte tus experiencias de viaje ✈️',
            'fitness' => '¡A moverse! Comparte tus rutinas y logros 💪',
            default => '¡Bienvenido/a al grupo! Esperamos que disfrutes la experiencia'
        };
    }

    private function getThemeRules(string $theme): array
    {
        return [
            'Mantén el respeto siempre',
            'Comparte contenido relevante al tema del grupo',
            'No spam o contenido promocional sin autorización',
            'Disfruta y sé parte activa de la comunidad',
        ];
    }

    private function generateEventData(): ?array
    {
        return [
            'event_type' => $this->faker->randomElement([
                'meetup', 'webinar', 'workshop', 'social_gathering', 'networking'
            ]),
            'location' => $this->faker->optional()->address(),
            'virtual_link' => $this->faker->optional()->url(),
            'capacity' => $this->faker->numberBetween(10, 200),
            'registration_required' => $this->faker->boolean(70),
            'cost' => $this->faker->optional(0.3)->randomFloat(2, 0, 100),
            'agenda' => $this->faker->optional()->sentences(3, true),
            'organizer_notes' => $this->faker->optional()->paragraph(),
        ];
    }

    private function generatePrivacySettings(string $type): array
    {
        return [
            'is_searchable' => $type === 'public' ? true : $this->faker->boolean(50),
            'show_member_list' => $type === 'private' ? $this->faker->boolean(30) : $this->faker->boolean(80),
            'allow_join_requests' => $type === 'public' ? true : $this->faker->boolean(60),
            'require_invitation' => $type === 'private' ? $this->faker->boolean(80) : $this->faker->boolean(20),
            'member_visibility' => $this->faker->randomElement(['public', 'members_only', 'admins_only']),
            'message_history_for_new_members' => $this->faker->boolean(70),
        ];
    }

    private function generateModerationSettings(): array
    {
        return [
            'content_filtering' => $this->faker->boolean(80),
            'spam_protection' => $this->faker->boolean(90),
            'profanity_filter' => $this->faker->boolean(60),
            'auto_ban_threshold' => $this->faker->numberBetween(2, 5),
            'mute_duration_minutes' => $this->faker->randomElement([15, 30, 60, 120, 240]),
            'require_admin_approval_for_media' => $this->faker->boolean(30),
            'limit_consecutive_messages' => $this->faker->boolean(40),
            'max_messages_per_minute' => $this->faker->numberBetween(5, 20),
        ];
    }

    // Estados específicos para diferentes tipos de grupos
    public function publicGroup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'public',
            'privacy_settings' => [
                'is_searchable' => true,
                'show_member_list' => true,
                'allow_join_requests' => true,
                'require_invitation' => false,
                'member_visibility' => 'public',
                'message_history_for_new_members' => true,
            ],
        ]);
    }

    public function privateGroup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'private',
            'member_count' => $this->faker->numberBetween(3, 15),
            'max_members' => $this->faker->numberBetween(15, 30),
            'privacy_settings' => [
                'is_searchable' => false,
                'show_member_list' => false,
                'allow_join_requests' => false,
                'require_invitation' => true,
                'member_visibility' => 'members_only',
                'message_history_for_new_members' => false,
            ],
        ]);
    }

    public function happyHour(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'happy_hour',
            'max_members' => 100,
            'settings' => array_merge($attributes['settings'] ?? [], [
                'time_limited' => true,
                'duration_hours' => $this->faker->numberBetween(1, 4),
                'auto_archive_after_event' => true,
            ]),
        ]);
    }

    public function themed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'themed',
            'theme_data' => $this->generateThemeData(
                $this->faker->randomElement(['foodies', 'travelers', 'fitness', 'music', 'books'])
            ),
        ]);
    }

    public function eventBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'event_based',
            'max_members' => 200,
            'event_starts_at' => $this->faker->dateTimeBetween('now', '+30 days'),
            'event_ends_at' => $this->faker->dateTimeBetween('+30 days', '+60 days'),
            'event_data' => $this->generateEventData(),
            'settings' => array_merge($attributes['settings'] ?? [], [
                'event_features' => true,
                'countdown_enabled' => true,
                'attendance_tracking' => true,
                'reminder_notifications' => true,
            ]),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'last_activity_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
            'activity_score' => $this->faker->numberBetween(60, 100),
            'archived_at' => null,
        ]);
    }

    public function highActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'message_count' => $this->faker->numberBetween(500, 2000),
            'activity_score' => $this->faker->numberBetween(80, 100),
            'last_activity_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'member_count' => $this->faker->numberBetween(20, 80),
        ]);
    }

    public function lowActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'message_count' => $this->faker->numberBetween(0, 50),
            'activity_score' => $this->faker->numberBetween(0, 30),
            'last_activity_at' => $this->faker->dateTimeBetween('-30 days', '-3 days'),
        ]);
    }

    public function moderated(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_settings' => [
                'content_filtering' => true,
                'spam_protection' => true,
                'profanity_filter' => true,
                'auto_ban_threshold' => 2,
                'require_admin_approval_for_media' => true,
                'limit_consecutive_messages' => true,
                'max_messages_per_minute' => 10,
            ],
        ]);
    }

    public function withBannedUsers(): static
    {
        return $this->state(fn (array $attributes) => [
            'banned_users' => $this->faker->randomElements(range(1, 50), $this->faker->numberBetween(1, 8)),
        ]);
    }

    public function withMutedUsers(): static
    {
        return $this->state(fn (array $attributes) => [
            'muted_users' => $this->faker->randomElements(range(1, 30), $this->faker->numberBetween(1, 5)),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
            'archived_at' => $this->faker->dateTimeBetween('-60 days', '-1 day'),
            'activity_score' => $this->faker->numberBetween(0, 20),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'suspension_reason' => $this->faker->randomElement([
                    'inappropriate_content', 'spam_reports', 'harassment_complaints', 'violation_of_terms'
                ]),
                'suspended_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
                'suspension_duration_days' => $this->faker->numberBetween(1, 30),
            ]),
        ]);
    }

    public function nearCapacity(): static
    {
        return $this->state(fn (array $attributes) => [
            'member_count' => $attributes['max_members'] - $this->faker->numberBetween(1, 5),
        ]);
    }

    public function upcomingEvent(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'event_based',
            'event_starts_at' => $this->faker->dateTimeBetween('+1 day', '+7 days'),
            'event_ends_at' => $this->faker->dateTimeBetween('+7 days', '+14 days'),
            'activity_score' => $this->faker->numberBetween(70, 100),
        ]);
    }
}