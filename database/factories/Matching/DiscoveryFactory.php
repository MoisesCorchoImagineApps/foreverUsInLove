<?php

namespace Database\Factories;

use App\Models\Discovery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DiscoveryFactory extends Factory
{
    protected $model = Discovery::class;

    public function definition(): array
    {
        $modes = ['standard', 'explore', 'boost', 'local', 'global', 'interest', 'second_chance', 'trending'];
        $mode = $this->faker->randomElement($modes);
        
        // Configuraciones específicas por modo
        $modeConfigs = [
            'standard' => ['cards_per_session' => 20, 'time_limit' => 600, 'radius' => 25],
            'explore' => ['cards_per_session' => 15, 'time_limit' => 450, 'radius' => 50],
            'boost' => ['cards_per_session' => 30, 'time_limit' => 900, 'radius' => 15],
            'local' => ['cards_per_session' => 25, 'time_limit' => 750, 'radius' => 10],
            'global' => ['cards_per_session' => 40, 'time_limit' => 1200, 'radius' => null],
            'interest' => ['cards_per_session' => 18, 'time_limit' => 540, 'radius' => 30],
            'second_chance' => ['cards_per_session' => 12, 'time_limit' => 360, 'radius' => 20],
            'trending' => ['cards_per_session' => 35, 'time_limit' => 1050, 'radius' => 40]
        ];
        
        $config = $modeConfigs[$mode];
        $startedAt = $this->faker->dateTimeBetween('-2 hours', 'now');
        $timeElapsed = $this->faker->numberBetween(60, $config['time_limit'] - 60);
        
        return [
            'id' => Str::uuid(),
            'user_id' => User::factory(),
            'mode' => $mode,
            'started_at' => $startedAt,
            'ended_at' => $this->faker->boolean(70) ? 
                (clone $startedAt)->modify("+{$timeElapsed} seconds") : null,
            'cards_shown' => $this->faker->numberBetween(1, $config['cards_per_session']),
            'cards_per_session' => $config['cards_per_session'],
            'time_limit' => $config['time_limit'],
            'current_position' => $this->faker->numberBetween(0, 15),
            'filters_applied' => $this->generateFiltersApplied($mode),
            'preferences' => $this->generatePreferences($mode),
            'location_lat' => $this->faker->latitude(),
            'location_lng' => $this->faker->longitude(),
            'radius' => $config['radius'],
            'is_completed' => $this->faker->boolean(65),
            'completion_reason' => $this->faker->randomElement([
                'time_limit', 'cards_exhausted', 'user_exit', 'manual_stop', 
                'no_more_cards', 'app_background', 'network_error'
            ]),
            'session_quality_score' => $this->faker->numberBetween(1, 100),
            'engagement_metrics' => $this->generateEngagementMetrics(),
            'created_at' => $startedAt,
            'updated_at' => $this->faker->dateTimeBetween($startedAt, 'now'),
        ];
    }

    private function generateFiltersApplied(string $mode): array
    {
        $baseFilters = [
            'age_range' => [$this->faker->numberBetween(18, 25), $this->faker->numberBetween(26, 45)],
            'distance' => $this->faker->numberBetween(5, 50),
        ];

        $advancedFilters = [
            'education_level' => $this->faker->randomElement(['high_school', 'bachelor', 'master', 'phd']),
            'height_range' => [$this->faker->numberBetween(150, 170), $this->faker->numberBetween(171, 200)],
            'body_type' => $this->faker->randomElements(['slim', 'athletic', 'average', 'curvy'], rand(1, 2)),
            'smoking' => $this->faker->randomElement(['never', 'occasionally', 'regularly']),
            'drinking' => $this->faker->randomElement(['never', 'socially', 'regularly']),
            'religion' => $this->faker->randomElement(['christian', 'muslim', 'jewish', 'buddhist', 'atheist', 'agnostic']),
            'politics' => $this->faker->randomElement(['liberal', 'moderate', 'conservative']),
            'children' => $this->faker->randomElement(['none', 'have_children', 'want_children']),
        ];

        switch ($mode) {
            case 'interest':
                $baseFilters['interests'] = $this->faker->randomElements([
                    'travel', 'music', 'sports', 'art', 'cooking', 'reading', 'gaming', 
                    'fitness', 'photography', 'dancing', 'movies', 'hiking'
                ], rand(2, 5));
                break;
            
            case 'boost':
                $baseFilters = array_merge($baseFilters, [
                    'online_status' => 'active_recently',
                    'verified_profile' => true,
                    'premium_user' => $this->faker->boolean(30),
                ]);
                break;
                
            case 'local':
                $baseFilters['distance'] = $this->faker->numberBetween(1, 15);
                $baseFilters['location_precision'] = 'high';
                break;
                
            case 'explore':
                $baseFilters = array_merge($baseFilters, 
                    $this->faker->randomElements($advancedFilters, rand(2, 4), false)
                );
                break;
        }

        return $baseFilters;
    }

    private function generatePreferences(string $mode): array
    {
        $basePrefs = [
            'auto_advance' => $this->faker->boolean(40),
            'show_distance' => $this->faker->boolean(80),
            'show_last_seen' => $this->faker->boolean(60),
            'haptic_feedback' => $this->faker->boolean(70),
        ];

        $modeSpecificPrefs = [
            'standard' => ['conservative_swiping' => true, 'detailed_view' => false],
            'explore' => ['show_compatibility' => true, 'detailed_profiles' => true],
            'boost' => ['priority_matching' => true, 'enhanced_visibility' => true],
            'local' => ['real_time_location' => true, 'nearby_alerts' => true],
            'global' => ['show_country' => true, 'language_preference' => 'any'],
            'interest' => ['highlight_common_interests' => true, 'interest_matching_weight' => 0.8],
            'second_chance' => ['show_previous_interaction' => true, 'second_look_mode' => true],
            'trending' => ['show_popularity_score' => true, 'trending_indicators' => true],
        ];

        return array_merge($basePrefs, $modeSpecificPrefs[$mode] ?? []);
    }

    private function generateEngagementMetrics(): array
    {
        return [
            'total_swipes' => $swipes = $this->faker->numberBetween(5, 50),
            'right_swipes' => $rightSwipes = $this->faker->numberBetween(1, (int)($swipes * 0.6)),
            'left_swipes' => $swipes - $rightSwipes,
            'super_likes_used' => $this->faker->numberBetween(0, 3),
            'profile_views_detailed' => $this->faker->numberBetween(0, 15),
            'average_decision_time' => $this->faker->numberBetween(2000, 15000), // milliseconds
            'backtrack_uses' => $this->faker->numberBetween(0, 5),
            'session_interruptions' => $this->faker->numberBetween(0, 3),
            'cards_skipped' => $this->faker->numberBetween(0, 8),
            'time_per_card_avg' => $this->faker->numberBetween(3000, 25000),
        ];
    }

    // Estados específicos para diferentes tipos de sesiones
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
            'is_completed' => false,
            'completion_reason' => null,
            'current_position' => $this->faker->numberBetween(1, 10),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'ended_at' => $this->faker->dateTimeBetween($attributes['started_at'], 'now'),
            'completion_reason' => $this->faker->randomElement(['time_limit', 'cards_exhausted', 'manual_stop']),
            'current_position' => $attributes['cards_per_session'],
        ]);
    }

    public function standardMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => 'standard',
            'cards_per_session' => 20,
            'time_limit' => 600,
            'radius' => 25,
        ]);
    }

    public function exploreMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => 'explore',
            'cards_per_session' => 15,
            'time_limit' => 450,
            'radius' => 50,
        ]);
    }

    public function boostMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => 'boost',
            'cards_per_session' => 30,
            'time_limit' => 900,
            'radius' => 15,
            'preferences' => array_merge($attributes['preferences'] ?? [], [
                'priority_matching' => true,
                'enhanced_visibility' => true,
            ]),
        ]);
    }

    public function localMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => 'local',
            'cards_per_session' => 25,
            'time_limit' => 750,
            'radius' => 10,
        ]);
    }

    public function globalMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => 'global',
            'cards_per_session' => 40,
            'time_limit' => 1200,
            'radius' => null,
        ]);
    }

    public function interestBasedMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => 'interest',
            'cards_per_session' => 18,
            'time_limit' => 540,
            'radius' => 30,
        ]);
    }

    public function secondChanceMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => 'second_chance',
            'cards_per_session' => 12,
            'time_limit' => 360,
            'radius' => 20,
        ]);
    }

    public function trendingMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => 'trending',
            'cards_per_session' => 35,
            'time_limit' => 1050,
            'radius' => 40,
        ]);
    }

    public function highEngagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_quality_score' => $this->faker->numberBetween(80, 100),
            'engagement_metrics' => array_merge($attributes['engagement_metrics'], [
                'total_swipes' => $this->faker->numberBetween(20, 50),
                'average_decision_time' => $this->faker->numberBetween(2000, 8000),
                'profile_views_detailed' => $this->faker->numberBetween(8, 20),
                'time_per_card_avg' => $this->faker->numberBetween(8000, 20000),
            ]),
        ]);
    }

    public function lowEngagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_quality_score' => $this->faker->numberBetween(1, 40),
            'engagement_metrics' => array_merge($attributes['engagement_metrics'], [
                'total_swipes' => $this->faker->numberBetween(1, 10),
                'average_decision_time' => $this->faker->numberBetween(1000, 3000),
                'profile_views_detailed' => $this->faker->numberBetween(0, 3),
                'session_interruptions' => $this->faker->numberBetween(2, 5),
            ]),
        ]);
    }

    public function premiumUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => $this->faker->randomElement(['boost', 'global', 'trending']),
            'cards_per_session' => $this->faker->numberBetween(30, 50),
            'time_limit' => $this->faker->numberBetween(900, 1500),
            'preferences' => array_merge($attributes['preferences'] ?? [], [
                'priority_matching' => true,
                'enhanced_visibility' => true,
                'unlimited_swipes' => true,
            ]),
        ]);
    }
}