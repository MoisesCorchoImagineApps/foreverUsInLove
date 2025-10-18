<?php

namespace Database\Factories;

use App\Models\View;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ViewFactory extends Factory
{
    protected $model = View::class;

    public function definition(): array
    {
        $createdAt = $this->faker->dateTimeBetween('-3 months', 'now');
        $viewType = $this->faker->randomElement(['profile_card', 'detailed_profile', 'photo_focus', 'bio_focus', 'quick_glance']);
        
        return [
            'id' => Str::uuid(),
            'viewer_id' => User::factory(),
            'viewed_user_id' => User::factory(),
            'view_type' => $viewType,
            'created_at' => $createdAt,
            'duration_seconds' => $this->generateDuration($viewType),
            'source' => $this->faker->randomElement([
                'discovery_swipe', 'search_results', 'match_list', 'liked_you', 'mutual_friends',
                'nearby_users', 'suggested_profiles', 'boost_visibility', 'super_like_notification',
                'second_chance', 'trending_profiles', 'compatibility_match', 'interest_match'
            ]),
            'discovery_session_id' => $this->faker->optional(0.6)->uuid(),
            'device_type' => $this->faker->randomElement(['mobile_ios', 'mobile_android', 'web_desktop', 'web_mobile']),
            'interaction_data' => $this->generateInteractionData($viewType),
            'engagement_score' => $this->faker->numberBetween(1, 100),
            'photos_viewed' => $this->generatePhotosViewed(),
            'profile_sections_viewed' => $this->generateProfileSectionsViewed(),
            'scroll_depth_percentage' => $this->faker->numberBetween(0, 100),
            'time_spent_on_photos_seconds' => $this->faker->numberBetween(1, 60),
            'time_spent_on_bio_seconds' => $this->faker->numberBetween(0, 30),
            'time_spent_on_interests_seconds' => $this->faker->numberBetween(0, 20),
            'conversion_action' => $this->faker->optional(0.4)->randomElement([
                'liked', 'passed', 'super_liked', 'messaged', 'shared_profile', 'reported', 'blocked'
            ]),
            'conversion_time_seconds' => $this->generateConversionTime(),
            'geographic_data' => $this->generateGeographicData(),
            'referrer_source' => $this->faker->optional()->randomElement([
                'push_notification', 'app_icon', 'deep_link', 'widget', 'share_link', 'advertisement'
            ]),
            'session_context' => $this->generateSessionContext(),
            'behavioral_signals' => $this->generateBehavioralSignals($viewType),
            'quality_indicators' => $this->generateQualityIndicators(),
            'personalization_data' => $this->generatePersonalizationData(),
            'a_b_test_variant' => $this->faker->optional()->randomElement(['control', 'variant_a', 'variant_b', 'variant_c']),
            'is_return_visitor' => $this->faker->boolean(30),
            'previous_view_count' => $this->faker->numberBetween(0, 10),
            'view_sequence_number' => $this->faker->numberBetween(1, 100),
            'time_since_last_view_hours' => $this->faker->optional()->numberBetween(1, 168),
            'cohort_group' => $this->faker->optional()->randomElement(['new_user', 'active_user', 'returning_user', 'premium_user']),
            'feature_flags' => $this->generateFeatureFlags(),
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    private function generateDuration(string $viewType): int
    {
        return match($viewType) {
            'profile_card' => $this->faker->numberBetween(2, 15),      // Vista rápida de tarjeta
            'detailed_profile' => $this->faker->numberBetween(15, 120), // Vista detallada completa
            'photo_focus' => $this->faker->numberBetween(5, 45),       // Enfoque en fotos
            'bio_focus' => $this->faker->numberBetween(10, 60),        // Enfoque en biografía
            'quick_glance' => $this->faker->numberBetween(1, 5),       // Vistazo rápido
            default => $this->faker->numberBetween(3, 30)
        };
    }

    private function generatePhotosViewed(): array
    {
        $photoCount = $this->faker->numberBetween(1, 8);
        $photos = [];
        
        for ($i = 1; $i <= $photoCount; $i++) {
            $photos[] = [
                'photo_order' => $i,
                'view_duration_seconds' => $this->faker->numberBetween(1, 30),
                'zoom_used' => $this->faker->boolean(20),
                'full_screen_viewed' => $this->faker->boolean(15),
            ];
        }
        
        return $photos;
    }

    private function generateProfileSectionsViewed(): array
    {
        $sections = [];
        $availableSections = [
            'basic_info', 'photos', 'bio', 'interests', 'lifestyle', 'education_work',
            'location', 'height_details', 'relationship_type', 'personality_traits',
            'social_media', 'spotify_artists', 'instagram_photos', 'mutual_connections'
        ];
        
        $viewedSections = $this->faker->randomElements($availableSections, rand(3, 8));
        
        foreach ($viewedSections as $section) {
            $sections[$section] = [
                'viewed' => true,
                'time_spent_seconds' => $this->faker->numberBetween(1, 20),
                'interaction_count' => $this->faker->numberBetween(0, 5),
            ];
        }
        
        return $sections;
    }

    private function generateConversionTime(): ?int
    {
        if ($this->faker->boolean(40)) {
            return $this->faker->numberBetween(5, 300); // 5 seconds to 5 minutes
        }
        return null;
    }

    private function generateGeographicData(): array
    {
        return [
            'viewer_latitude' => $this->faker->latitude(),
            'viewer_longitude' => $this->faker->longitude(),
            'distance_km' => $this->faker->numberBetween(1, 100),
            'same_city' => $this->faker->boolean(60),
            'same_country' => $this->faker->boolean(85),
            'timezone_offset' => $this->faker->numberBetween(-12, 12),
        ];
    }

    private function generateSessionContext(): array
    {
        return [
            'session_start_time' => $this->faker->dateTimeBetween('-4 hours', 'now'),
            'session_duration_minutes' => $this->faker->numberBetween(5, 120),
            'profiles_viewed_in_session' => $this->faker->numberBetween(1, 50),
            'swipes_in_session' => $this->faker->numberBetween(0, 30),
            'matches_in_session' => $this->faker->numberBetween(0, 5),
            'app_version' => $this->faker->regexify('12\.[0-9]\.[0-9]'),
            'device_model' => $this->faker->randomElement([
                'iPhone 14 Pro', 'iPhone 13', 'Samsung Galaxy S23', 'Pixel 7', 'OnePlus 11'
            ]),
            'os_version' => $this->faker->randomElement(['iOS 17.1', 'Android 14', 'Android 13']),
            'network_type' => $this->faker->randomElement(['wifi', '4g', '5g', '3g']),
            'battery_level' => $this->faker->numberBetween(10, 100),
        ];
    }

    private function generateInteractionData(string $viewType): array
    {
        $baseData = [
            'scroll_events' => $this->faker->numberBetween(0, 20),
            'tap_events' => $this->faker->numberBetween(0, 15),
            'swipe_gestures' => $this->faker->numberBetween(0, 5),
            'zoom_gestures' => $this->faker->numberBetween(0, 3),
            'back_button_used' => $this->faker->boolean(30),
            'share_button_tapped' => $this->faker->boolean(5),
        ];

        // Ajustes específicos por tipo de vista
        if ($viewType === 'detailed_profile') {
            $baseData['scroll_events'] = $this->faker->numberBetween(5, 30);
            $baseData['section_expansions'] = $this->faker->numberBetween(1, 8);
        } elseif ($viewType === 'photo_focus') {
            $baseData['photo_navigation'] = $this->faker->numberBetween(2, 10);
            $baseData['zoom_gestures'] = $this->faker->numberBetween(1, 5);
        }

        return $baseData;
    }

    private function generateBehavioralSignals(string $viewType): array
    {
        return [
            'hesitation_pattern' => $this->faker->randomElement(['none', 'low', 'medium', 'high']),
            'browsing_speed' => $this->faker->randomElement(['very_fast', 'fast', 'normal', 'slow', 'very_slow']),
            'attention_focus' => $this->faker->randomElement(['photos', 'bio', 'interests', 'distributed']),
            'decision_confidence' => $this->faker->numberBetween(1, 10),
            'exploration_depth' => $this->faker->randomElement(['surface', 'moderate', 'thorough']),
            'interaction_enthusiasm' => $this->faker->numberBetween(1, 10),
            'profile_completion_viewed' => $this->faker->numberBetween(10, 100), // percentage
            'return_likelihood' => $this->faker->numberBetween(1, 10),
        ];
    }

    private function generateQualityIndicators(): array
    {
        return [
            'profile_authenticity_score' => $this->faker->numberBetween(1, 10),
            'photo_quality_average' => $this->faker->numberBetween(1, 10),
            'bio_completeness_score' => $this->faker->numberBetween(1, 10),
            'interests_diversity_score' => $this->faker->numberBetween(1, 10),
            'verification_badges_count' => $this->faker->numberBetween(0, 5),
            'mutual_connections_count' => $this->faker->numberBetween(0, 20),
            'activity_recency_score' => $this->faker->numberBetween(1, 10),
            'response_rate_estimate' => $this->faker->numberBetween(10, 90),
        ];
    }

    private function generatePersonalizationData(): array
    {
        return [
            'compatibility_factors' => [
                'age_preference_match' => $this->faker->boolean(80),
                'distance_preference_match' => $this->faker->boolean(70),
                'interest_overlap_score' => $this->faker->numberBetween(0, 10),
                'lifestyle_compatibility' => $this->faker->numberBetween(1, 10),
                'personality_match_score' => $this->faker->numberBetween(1, 10),
            ],
            'recommendation_reasons' => $this->faker->randomElements([
                'shared_interests', 'mutual_friends', 'similar_lifestyle', 'geographic_proximity',
                'education_match', 'age_compatibility', 'activity_level', 'personality_traits'
            ], rand(2, 5)),
            'user_preference_alignment' => $this->faker->numberBetween(1, 10),
            'algorithmic_confidence' => $this->faker->numberBetween(1, 10),
        ];
    }

    private function generateFeatureFlags(): array
    {
        return [
            'new_ui_enabled' => $this->faker->boolean(50),
            'enhanced_photos' => $this->faker->boolean(30),
            'ai_compatibility_visible' => $this->faker->boolean(40),
            'mutual_friends_highlighted' => $this->faker->boolean(60),
            'interest_matching_enhanced' => $this->faker->boolean(35),
            'premium_badges_visible' => $this->faker->boolean(70),
        ];
    }

    // Estados específicos para diferentes tipos de vistas
    public function profileCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'view_type' => 'profile_card',
            'duration_seconds' => $this->faker->numberBetween(2, 15),
            'scroll_depth_percentage' => $this->faker->numberBetween(0, 30),
        ]);
    }

    public function detailedProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'view_type' => 'detailed_profile',
            'duration_seconds' => $this->faker->numberBetween(15, 120),
            'scroll_depth_percentage' => $this->faker->numberBetween(70, 100),
            'engagement_score' => $this->faker->numberBetween(60, 100),
        ]);
    }

    public function photoFocus(): static
    {
        return $this->state(fn (array $attributes) => [
            'view_type' => 'photo_focus',
            'duration_seconds' => $this->faker->numberBetween(5, 45),
            'time_spent_on_photos_seconds' => $this->faker->numberBetween(15, 40),
        ]);
    }

    public function bioFocus(): static
    {
        return $this->state(fn (array $attributes) => [
            'view_type' => 'bio_focus',
            'duration_seconds' => $this->faker->numberBetween(10, 60),
            'time_spent_on_bio_seconds' => $this->faker->numberBetween(15, 45),
        ]);
    }

    public function quickGlance(): static
    {
        return $this->state(fn (array $attributes) => [
            'view_type' => 'quick_glance',
            'duration_seconds' => $this->faker->numberBetween(1, 5),
            'engagement_score' => $this->faker->numberBetween(1, 30),
        ]);
    }

    public function withConversion(): static
    {
        return $this->state(fn (array $attributes) => [
            'conversion_action' => $this->faker->randomElement(['liked', 'super_liked', 'messaged']),
            'conversion_time_seconds' => $this->faker->numberBetween(10, 120),
            'engagement_score' => $this->faker->numberBetween(60, 100),
        ]);
    }

    public function withoutConversion(): static
    {
        return $this->state(fn (array $attributes) => [
            'conversion_action' => null,
            'conversion_time_seconds' => null,
            'engagement_score' => $this->faker->numberBetween(1, 50),
        ]);
    }

    public function highEngagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'engagement_score' => $this->faker->numberBetween(70, 100),
            'duration_seconds' => $this->faker->numberBetween(30, 120),
            'scroll_depth_percentage' => $this->faker->numberBetween(80, 100),
            'photos_viewed' => $this->generatePhotosViewed(), // Al menos 3 fotos
            'behavioral_signals' => array_merge($attributes['behavioral_signals'], [
                'exploration_depth' => 'thorough',
                'interaction_enthusiasm' => $this->faker->numberBetween(7, 10),
            ]),
        ]);
    }

    public function lowEngagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'engagement_score' => $this->faker->numberBetween(1, 30),
            'duration_seconds' => $this->faker->numberBetween(1, 10),
            'scroll_depth_percentage' => $this->faker->numberBetween(0, 20),
            'behavioral_signals' => array_merge($attributes['behavioral_signals'], [
                'browsing_speed' => 'very_fast',
                'exploration_depth' => 'surface',
            ]),
        ]);
    }

    public function fromDiscovery(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'discovery_swipe',
            'discovery_session_id' => $this->faker->uuid(),
        ]);
    }

    public function fromSearch(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'search_results',
            'discovery_session_id' => null,
        ]);
    }

    public function fromLikedYou(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'liked_you',
            'engagement_score' => $this->faker->numberBetween(50, 100), // Mayor engagement esperado
        ]);
    }

    public function returnVisitor(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_return_visitor' => true,
            'previous_view_count' => $this->faker->numberBetween(1, 5),
            'time_since_last_view_hours' => $this->faker->numberBetween(1, 48),
        ]);
    }

    public function newVisitor(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_return_visitor' => false,
            'previous_view_count' => 0,
            'time_since_last_view_hours' => null,
            'view_sequence_number' => 1,
        ]);
    }

    public function mobileDevice(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => $this->faker->randomElement(['mobile_ios', 'mobile_android']),
        ]);
    }

    public function webDevice(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => $this->faker->randomElement(['web_desktop', 'web_mobile']),
        ]);
    }

    public function premiumViewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'cohort_group' => 'premium_user',
            'feature_flags' => array_merge($attributes['feature_flags'], [
                'premium_badges_visible' => true,
                'enhanced_photos' => true,
                'ai_compatibility_visible' => true,
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

    public function thisWeek(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function longSession(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_context' => array_merge($attributes['session_context'], [
                'session_duration_minutes' => $this->faker->numberBetween(60, 180),
                'profiles_viewed_in_session' => $this->faker->numberBetween(20, 100),
            ]),
        ]);
    }

    public function shortSession(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_context' => array_merge($attributes['session_context'], [
                'session_duration_minutes' => $this->faker->numberBetween(2, 15),
                'profiles_viewed_in_session' => $this->faker->numberBetween(1, 10),
            ]),
        ]);
    }
}