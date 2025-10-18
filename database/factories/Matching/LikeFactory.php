<?php

namespace Database\Factories;

use App\Models\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LikeFactory extends Factory
{
    protected $model = Like::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['like', 'pass', 'super_like']);
        $createdAt = $this->faker->dateTimeBetween('-3 months', 'now');
        
        return [
            'id' => Str::uuid(),
            'user_id' => User::factory(),
            'target_user_id' => User::factory(),
            'type' => $type,
            'created_at' => $createdAt,
            'is_mutual' => false, // Se actualizará con lógica de negocio
            'matched_at' => null, // Se actualizará si hay match
            'source' => $this->faker->randomElement([
                'discovery_standard', 'discovery_boost', 'discovery_local', 'discovery_global',
                'search_results', 'suggested_matches', 'mutual_friends', 'nearby_users',
                'second_chance', 'trending_now', 'recently_active', 'compatibility_match'
            ]),
            'session_id' => $this->faker->optional(0.7)->uuid(),
            'discovery_mode' => $this->faker->randomElement([
                'standard', 'explore', 'boost', 'local', 'global', 'interest', 'second_chance', 'trending'
            ]),
            'swipe_direction' => $this->getSwipeDirection($type),
            'decision_time_ms' => $this->getDecisionTime($type),
            'context' => $this->generateContext(),
            'location' => $this->generateLocation(),
            'device_info' => $this->generateDeviceInfo(),
            'interaction_quality_score' => $this->faker->numberBetween(1, 100),
            'confidence_level' => $this->faker->numberBetween(1, 10),
            'is_undo_available' => $this->faker->boolean(80),
            'undo_used' => false,
            'undo_at' => null,
            'boost_applied' => $type === 'super_like' ? $this->faker->boolean(30) : false,
            'premium_feature_used' => $this->faker->boolean(20),
            'notification_sent' => $type !== 'pass' ? $this->faker->boolean(85) : false,
            'notification_read' => false,
            'notification_read_at' => null,
            'analytics_data' => $this->generateAnalyticsData($type),
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    private function getSwipeDirection(string $type): string
    {
        return match($type) {
            'like', 'super_like' => 'right',
            'pass' => 'left',
            default => 'right'
        };
    }

    private function getDecisionTime(string $type): int
    {
        // Tiempo en milisegundos basado en el tipo de acción
        return match($type) {
            'super_like' => $this->faker->numberBetween(5000, 30000), // Más tiempo para super likes
            'like' => $this->faker->numberBetween(2000, 15000),       // Tiempo moderado para likes
            'pass' => $this->faker->numberBetween(500, 8000),        // Menos tiempo para rechazos
            default => $this->faker->numberBetween(1000, 10000)
        };
    }

    private function generateContext(): array
    {
        return [
            'app_state' => $this->faker->randomElement(['foreground', 'background_recent']),
            'battery_level' => $this->faker->numberBetween(10, 100),
            'connection_type' => $this->faker->randomElement(['wifi', '4g', '5g', '3g']),
            'time_of_day' => $this->faker->randomElement(['morning', 'afternoon', 'evening', 'night']),
            'day_of_week' => $this->faker->dayOfWeek,
            'user_mood' => $this->faker->optional()->randomElement([
                'exploring', 'selective', 'casual', 'serious', 'playful', 'focused'
            ]),
            'session_duration_before_action' => $this->faker->numberBetween(30, 1800), // seconds
            'cards_seen_in_session' => $this->faker->numberBetween(1, 50),
            'recent_matches_count' => $this->faker->numberBetween(0, 10),
        ];
    }

    private function generateLocation(): array
    {
        return [
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'accuracy' => $this->faker->numberBetween(5, 50), // meters
            'city' => $this->faker->city,
            'country' => $this->faker->country,
            'is_traveling' => $this->faker->boolean(10),
            'location_method' => $this->faker->randomElement(['gps', 'network', 'passive', 'manual']),
        ];
    }

    private function generateDeviceInfo(): array
    {
        return [
            'platform' => $this->faker->randomElement(['ios', 'android', 'web']),
            'app_version' => $this->faker->regexify('12\.[0-9]\.[0-9]'),
            'device_model' => $this->faker->randomElement([
                'iPhone 14 Pro', 'iPhone 13', 'Samsung Galaxy S23', 'Pixel 7', 'OnePlus 11'
            ]),
            'os_version' => $this->faker->randomElement(['iOS 17.1', 'Android 14', 'Android 13']),
            'screen_size' => $this->faker->randomElement(['small', 'medium', 'large', 'extra_large']),
            'orientation' => $this->faker->randomElement(['portrait', 'landscape']),
        ];
    }

    private function generateAnalyticsData(string $type): array
    {
        $baseData = [
            'view_duration_ms' => $this->faker->numberBetween(1000, 60000),
            'photos_viewed' => $this->faker->numberBetween(1, 8),
            'profile_scrolled' => $this->faker->boolean(60),
            'bio_read' => $this->faker->boolean(40),
            'interests_viewed' => $this->faker->boolean(30),
            'mutual_friends_checked' => $this->faker->boolean(15),
            'distance_noted' => $this->faker->boolean(50),
            'last_active_checked' => $this->faker->boolean(25),
        ];

        // Datos específicos por tipo de like
        if ($type === 'super_like') {
            $baseData['contemplation_time_ms'] = $this->faker->numberBetween(3000, 20000);
            $baseData['photos_reviewed_multiple'] = $this->faker->boolean(80);
            $baseData['profile_details_examined'] = $this->faker->boolean(90);
        } elseif ($type === 'pass') {
            $baseData['quick_decision'] = $this->faker->boolean(70);
            $baseData['dealbreaker_identified'] = $this->faker->optional()->randomElement([
                'age', 'distance', 'photos', 'interests', 'lifestyle', 'bio_content'
            ]);
        }

        return $baseData;
    }

    // Estados específicos para diferentes tipos de likes
    public function like(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'like',
            'swipe_direction' => 'right',
            'decision_time_ms' => $this->faker->numberBetween(2000, 15000),
            'interaction_quality_score' => $this->faker->numberBetween(40, 85),
            'confidence_level' => $this->faker->numberBetween(5, 8),
        ]);
    }

    public function pass(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'pass',
            'swipe_direction' => 'left',
            'decision_time_ms' => $this->faker->numberBetween(500, 8000),
            'interaction_quality_score' => $this->faker->numberBetween(10, 40),
            'confidence_level' => $this->faker->numberBetween(6, 10),
            'notification_sent' => false,
        ]);
    }

    public function superLike(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'super_like',
            'swipe_direction' => 'right',
            'decision_time_ms' => $this->faker->numberBetween(5000, 30000),
            'interaction_quality_score' => $this->faker->numberBetween(70, 100),
            'confidence_level' => $this->faker->numberBetween(8, 10),
            'premium_feature_used' => $this->faker->boolean(60),
            'boost_applied' => $this->faker->boolean(40),
            'notification_sent' => true,
        ]);
    }

    public function mutual(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_mutual' => true,
            'matched_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'notification_sent' => true,
            'notification_read' => $this->faker->boolean(90),
            'notification_read_at' => $this->faker->optional(0.9)->dateTimeBetween($attributes['created_at'], 'now'),
        ]);
    }

    public function withUndo(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_undo_available' => true,
            'undo_used' => $this->faker->boolean(30),
            'undo_at' => $this->faker->boolean(30) ? 
                $this->faker->dateTimeBetween($attributes['created_at'], 'now') : null,
        ]);
    }

    public function fromDiscoveryStandard(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'discovery_standard',
            'discovery_mode' => 'standard',
            'session_id' => $this->faker->uuid(),
        ]);
    }

    public function fromDiscoveryBoost(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'discovery_boost',
            'discovery_mode' => 'boost',
            'boost_applied' => true,
            'premium_feature_used' => true,
            'session_id' => $this->faker->uuid(),
        ]);
    }

    public function fromDiscoveryLocal(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'discovery_local',
            'discovery_mode' => 'local',
            'location' => array_merge($attributes['location'], [
                'accuracy' => $this->faker->numberBetween(5, 20), // Mayor precisión para local
            ]),
        ]);
    }

    public function fromSecondChance(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'second_chance',
            'discovery_mode' => 'second_chance',
            'context' => array_merge($attributes['context'], [
                'is_second_chance' => true,
                'original_interaction_date' => $this->faker->dateTimeBetween('-3 months', '-1 month'),
            ]),
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('today', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('today', 'now'),
        ]);
    }

    public function thisWeek(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function premiumUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'premium_feature_used' => true,
            'boost_applied' => $this->faker->boolean(50),
            'source' => $this->faker->randomElement([
                'discovery_boost', 'discovery_global', 'suggested_matches'
            ]),
        ]);
    }

    public function highQualityInteraction(): static
    {
        return $this->state(fn (array $attributes) => [
            'interaction_quality_score' => $this->faker->numberBetween(70, 100),
            'decision_time_ms' => $this->faker->numberBetween(5000, 25000),
            'confidence_level' => $this->faker->numberBetween(7, 10),
            'analytics_data' => array_merge($attributes['analytics_data'], [
                'view_duration_ms' => $this->faker->numberBetween(10000, 60000),
                'photos_viewed' => $this->faker->numberBetween(3, 8),
                'bio_read' => true,
                'interests_viewed' => true,
            ]),
        ]);
    }

    public function quickDecision(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision_time_ms' => $this->faker->numberBetween(500, 3000),
            'analytics_data' => array_merge($attributes['analytics_data'], [
                'view_duration_ms' => $this->faker->numberBetween(1000, 5000),
                'photos_viewed' => $this->faker->numberBetween(1, 2),
                'quick_decision' => true,
            ]),
        ]);
    }

    public function mobileApp(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_info' => array_merge($attributes['device_info'], [
                'platform' => $this->faker->randomElement(['ios', 'android']),
                'orientation' => 'portrait',
            ]),
        ]);
    }

    public function webApp(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_info' => array_merge($attributes['device_info'], [
                'platform' => 'web',
                'screen_size' => $this->faker->randomElement(['medium', 'large', 'extra_large']),
                'orientation' => $this->faker->randomElement(['portrait', 'landscape']),
            ]),
        ]);
    }

    public function withNotificationRead(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_sent' => true,
            'notification_read' => true,
            'notification_read_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
        ]);
    }

    public function duringPeakHours(): static
    {
        return $this->state(fn (array $attributes) => [
            'context' => array_merge($attributes['context'], [
                'time_of_day' => $this->faker->randomElement(['evening', 'night']),
                'day_of_week' => $this->faker->randomElement(['Friday', 'Saturday', 'Sunday']),
            ]),
        ]);
    }

    public function traveling(): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => array_merge($attributes['location'], [
                'is_traveling' => true,
                'accuracy' => $this->faker->numberBetween(20, 100),
            ]),
            'source' => 'discovery_global',
            'discovery_mode' => 'global',
        ]);
    }
}