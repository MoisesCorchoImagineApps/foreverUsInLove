<?php

namespace Database\Factories;

use App\Models\Match;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MatchFactory extends Factory
{
    protected $model = Match::class;

    public function definition(): array
    {
        $createdAt = $this->faker->dateTimeBetween('-2 months', 'now');
        $status = $this->faker->randomElement(['active', 'expired', 'unmatched', 'blocked', 'reported']);
        
        return [
            'id' => Str::uuid(),
            'user_id' => User::factory(),
            'matched_user_id' => User::factory(),
            'created_at' => $createdAt,
            'status' => $status,
            'compatibility_score' => $this->faker->numberBetween(60, 98),
            'match_source' => $this->faker->randomElement([
                'mutual_like', 'super_like', 'boost_like', 'second_chance', 
                'algorithm_match', 'interest_match', 'proximity_match', 'compatibility_match'
            ]),
            'first_message_sent' => $this->faker->boolean(40),
            'first_message_at' => $this->generateFirstMessageTime($createdAt),
            'last_interaction_at' => $this->generateLastInteractionTime($createdAt, $status),
            'messages_count' => $this->generateMessagesCount($status),
            'mutual_interest_score' => $this->faker->numberBetween(1, 10),
            'engagement_level' => $this->faker->randomElement(['low', 'medium', 'high', 'very_high']),
            'conversation_starter_used' => $this->faker->boolean(25),
            'icebreaker_type' => $this->faker->optional()->randomElement([
                'question', 'compliment', 'shared_interest', 'joke', 'observation', 'gif'
            ]),
            'match_quality_indicators' => $this->generateQualityIndicators(),
            'interaction_patterns' => $this->generateInteractionPatterns(),
            'shared_interests' => $this->generateSharedInterests(),
            'location_data' => $this->generateLocationData(),
            'time_to_first_message_hours' => $this->generateTimeToFirstMessage(),
            'response_time_avg_minutes' => $this->generateAverageResponseTime($status),
            'conversation_depth_score' => $this->faker->numberBetween(1, 10),
            'date_suggested' => $this->faker->boolean(15),
            'date_planned' => $this->faker->boolean(5),
            'social_media_connected' => $this->faker->boolean(8),
            'phone_number_shared' => $this->faker->boolean(3),
            'video_call_made' => $this->faker->boolean(2),
            'in_person_meeting' => $this->faker->boolean(1),
            'relationship_potential_score' => $this->faker->numberBetween(1, 10),
            'ai_compatibility_analysis' => $this->generateAIAnalysis(),
            'premium_features_used' => $this->generatePremiumFeatures(),
            'expiry_date' => $this->generateExpiryDate($createdAt, $status),
            'expired_at' => $status === 'expired' ? $this->faker->dateTimeBetween($createdAt, 'now') : null,
            'unmatched_at' => $status === 'unmatched' ? $this->faker->dateTimeBetween($createdAt, 'now') : null,
            'unmatched_by' => $status === 'unmatched' ? $this->faker->randomElement(['user', 'matched_user']) : null,
            'unmatch_reason' => $status === 'unmatched' ? $this->faker->randomElement([
                'no_response', 'inappropriate_behavior', 'not_interested', 'found_someone', 
                'different_expectations', 'spam', 'fake_profile', 'other'
            ]) : null,
            'blocked_at' => $status === 'blocked' ? $this->faker->dateTimeBetween($createdAt, 'now') : null,
            'blocked_by' => $status === 'blocked' ? $this->faker->randomElement(['user', 'matched_user']) : null,
            'reported_at' => $status === 'reported' ? $this->faker->dateTimeBetween($createdAt, 'now') : null,
            'reported_by' => $status === 'reported' ? $this->faker->randomElement(['user', 'matched_user']) : null,
            'report_reason' => $status === 'reported' ? $this->faker->randomElement([
                'harassment', 'spam', 'fake_profile', 'inappropriate_photos', 
                'scam', 'underage', 'violence', 'other'
            ]) : null,
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    private function generateFirstMessageTime($createdAt)
    {
        if ($this->faker->boolean(40)) {
            return $this->faker->dateTimeBetween($createdAt, (clone $createdAt)->modify('+7 days'));
        }
        return null;
    }

    private function generateLastInteractionTime($createdAt, string $status)
    {
        if (in_array($status, ['active', 'expired', 'unmatched', 'blocked'])) {
            return $this->faker->dateTimeBetween($createdAt, 'now');
        }
        return null;
    }

    private function generateMessagesCount(string $status): int
    {
        return match($status) {
            'active' => $this->faker->numberBetween(1, 200),
            'expired' => $this->faker->numberBetween(0, 5),
            'unmatched' => $this->faker->numberBetween(0, 50),
            'blocked' => $this->faker->numberBetween(0, 20),
            'reported' => $this->faker->numberBetween(0, 10),
            default => 0
        };
    }

    private function generateQualityIndicators(): array
    {
        return [
            'profile_completion_both' => $this->faker->numberBetween(60, 100),
            'photo_quality_score' => $this->faker->numberBetween(1, 10),
            'mutual_friends_count' => $this->faker->numberBetween(0, 25),
            'common_interests_count' => $this->faker->numberBetween(1, 15),
            'education_compatibility' => $this->faker->boolean(60),
            'age_compatibility_score' => $this->faker->numberBetween(1, 10),
            'location_proximity_score' => $this->faker->numberBetween(1, 10),
            'activity_level_match' => $this->faker->numberBetween(1, 10),
            'response_consistency' => $this->faker->numberBetween(1, 10),
            'conversation_balance' => $this->faker->numberBetween(1, 10),
        ];
    }

    private function generateInteractionPatterns(): array
    {
        return [
            'message_frequency' => $this->faker->randomElement(['very_low', 'low', 'medium', 'high', 'very_high']),
            'response_time_consistency' => $this->faker->numberBetween(1, 10),
            'conversation_initiation_balance' => $this->faker->numberBetween(1, 10),
            'emoji_usage' => $this->faker->randomElement(['none', 'minimal', 'moderate', 'frequent']),
            'question_asking_frequency' => $this->faker->numberBetween(1, 10),
            'personal_sharing_level' => $this->faker->randomElement(['minimal', 'moderate', 'open', 'very_open']),
            'humor_compatibility' => $this->faker->numberBetween(1, 10),
            'flirtation_level' => $this->faker->randomElement(['none', 'subtle', 'moderate', 'obvious']),
            'conversation_topics' => $this->faker->randomElements([
                'work', 'hobbies', 'travel', 'food', 'movies', 'music', 'sports', 'books',
                'family', 'friends', 'goals', 'experiences', 'current_events', 'lifestyle'
            ], rand(3, 8)),
            'weekend_activity_alignment' => $this->faker->numberBetween(1, 10),
        ];
    }

    private function generateSharedInterests(): array
    {
        return $this->faker->randomElements([
            'travel', 'music', 'sports', 'art', 'cooking', 'reading', 'gaming', 'fitness',
            'photography', 'dancing', 'movies', 'hiking', 'yoga', 'technology', 'fashion',
            'wine', 'coffee', 'concerts', 'theater', 'museums', 'beaches', 'mountains',
            'cycling', 'running', 'swimming', 'skiing', 'surfing', 'meditation', 'gardening'
        ], rand(2, 12));
    }

    private function generateLocationData(): array
    {
        return [
            'distance_km' => $this->faker->numberBetween(1, 100),
            'same_city' => $this->faker->boolean(70),
            'same_neighborhood' => $this->faker->boolean(30),
            'frequent_locations_overlap' => $this->faker->numberBetween(0, 5),
            'travel_pattern_similarity' => $this->faker->numberBetween(1, 10),
            'timezone_difference' => $this->faker->numberBetween(0, 12),
        ];
    }

    private function generateTimeToFirstMessage(): ?int
    {
        if ($this->faker->boolean(40)) {
            return $this->faker->numberBetween(1, 168); // 1 hour to 1 week
        }
        return null;
    }

    private function generateAverageResponseTime(string $status): ?int
    {
        if (in_array($status, ['active', 'expired', 'unmatched']) && $this->faker->boolean(60)) {
            return $this->faker->numberBetween(15, 1440); // 15 minutes to 24 hours
        }
        return null;
    }

    private function generateAIAnalysis(): array
    {
        return [
            'personality_compatibility' => $this->faker->numberBetween(1, 10),
            'communication_style_match' => $this->faker->numberBetween(1, 10),
            'lifestyle_alignment' => $this->faker->numberBetween(1, 10),
            'long_term_potential' => $this->faker->numberBetween(1, 10),
            'conversation_flow_score' => $this->faker->numberBetween(1, 10),
            'emotional_intelligence_match' => $this->faker->numberBetween(1, 10),
            'conflict_resolution_compatibility' => $this->faker->numberBetween(1, 10),
            'humor_alignment' => $this->faker->numberBetween(1, 10),
            'ambition_level_match' => $this->faker->numberBetween(1, 10),
            'relationship_readiness_score' => $this->faker->numberBetween(1, 10),
        ];
    }

    private function generatePremiumFeatures(): array
    {
        return [
            'boost_used' => $this->faker->boolean(20),
            'super_like_involved' => $this->faker->boolean(15),
            'read_receipts_enabled' => $this->faker->boolean(30),
            'priority_matching' => $this->faker->boolean(25),
            'enhanced_filters_used' => $this->faker->boolean(35),
            'unlimited_swipes_used' => $this->faker->boolean(40),
            'rewind_used' => $this->faker->boolean(20),
            'passport_feature_used' => $this->faker->boolean(10),
        ];
    }

    private function generateExpiryDate($createdAt, string $status): ?\DateTime
    {
        if ($status === 'active') {
            return (clone $createdAt)->modify('+21 days'); // Matches expire after 21 days
        }
        return null;
    }

    // Estados específicos para diferentes tipos de matches
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expired_at' => null,
            'unmatched_at' => null,
            'blocked_at' => null,
            'reported_at' => null,
            'expiry_date' => (clone $attributes['created_at'])->modify('+21 days'),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expired_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'messages_count' => $this->faker->numberBetween(0, 5),
            'first_message_sent' => $this->faker->boolean(20),
        ]);
    }

    public function unmatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'unmatched',
            'unmatched_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'unmatched_by' => $this->faker->randomElement(['user', 'matched_user']),
            'unmatch_reason' => $this->faker->randomElement([
                'no_response', 'inappropriate_behavior', 'not_interested', 'found_someone'
            ]),
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'blocked',
            'blocked_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'blocked_by' => $this->faker->randomElement(['user', 'matched_user']),
        ]);
    }

    public function reported(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reported',
            'reported_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'reported_by' => $this->faker->randomElement(['user', 'matched_user']),
            'report_reason' => $this->faker->randomElement([
                'harassment', 'spam', 'fake_profile', 'inappropriate_photos'
            ]),
        ]);
    }

    public function withFirstMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_message_sent' => true,
            'first_message_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'time_to_first_message_hours' => $this->faker->numberBetween(1, 48),
            'messages_count' => $this->faker->numberBetween(1, 100),
        ]);
    }

    public function withoutMessages(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_message_sent' => false,
            'first_message_at' => null,
            'messages_count' => 0,
            'last_interaction_at' => $attributes['created_at'],
            'conversation_depth_score' => 1,
        ]);
    }

    public function highCompatibility(): static
    {
        return $this->state(fn (array $attributes) => [
            'compatibility_score' => $this->faker->numberBetween(85, 98),
            'mutual_interest_score' => $this->faker->numberBetween(7, 10),
            'relationship_potential_score' => $this->faker->numberBetween(7, 10),
            'shared_interests' => $this->faker->randomElements([
                'travel', 'music', 'sports', 'art', 'cooking', 'reading', 'fitness', 'movies'
            ], rand(5, 8)),
        ]);
    }

    public function lowCompatibility(): static
    {
        return $this->state(fn (array $attributes) => [
            'compatibility_score' => $this->faker->numberBetween(60, 75),
            'mutual_interest_score' => $this->faker->numberBetween(1, 4),
            'relationship_potential_score' => $this->faker->numberBetween(1, 4),
            'shared_interests' => $this->faker->randomElements([
                'travel', 'music', 'movies'
            ], rand(1, 3)),
        ]);
    }

    public function highEngagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'engagement_level' => 'very_high',
            'messages_count' => $this->faker->numberBetween(50, 200),
            'conversation_depth_score' => $this->faker->numberBetween(7, 10),
            'response_time_avg_minutes' => $this->faker->numberBetween(15, 120),
            'date_suggested' => $this->faker->boolean(60),
            'social_media_connected' => $this->faker->boolean(40),
        ]);
    }

    public function lowEngagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'engagement_level' => 'low',
            'messages_count' => $this->faker->numberBetween(0, 10),
            'conversation_depth_score' => $this->faker->numberBetween(1, 3),
            'response_time_avg_minutes' => $this->faker->numberBetween(480, 1440),
        ]);
    }

    public function superLikeMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_source' => 'super_like',
            'premium_features_used' => array_merge($attributes['premium_features_used'], [
                'super_like_involved' => true,
            ]),
            'first_message_sent' => $this->faker->boolean(70),
            'compatibility_score' => $this->faker->numberBetween(75, 95),
        ]);
    }

    public function boostMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_source' => 'boost_like',
            'premium_features_used' => array_merge($attributes['premium_features_used'], [
                'boost_used' => true,
            ]),
            'compatibility_score' => $this->faker->numberBetween(70, 90),
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function thisWeek(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function nearExpiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'created_at' => $this->faker->dateTimeBetween('-20 days', '-18 days'),
            'expiry_date' => $this->faker->dateTimeBetween('-2 days', '+2 days'),
        ]);
    }

    public function conversationStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'conversation_starter_used' => true,
            'icebreaker_type' => $this->faker->randomElement([
                'question', 'compliment', 'shared_interest', 'joke'
            ]),
            'first_message_sent' => true,
            'messages_count' => $this->faker->numberBetween(2, 20),
        ]);
    }

    public function progressedToDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'engagement_level' => 'very_high',
            'date_suggested' => true,
            'date_planned' => $this->faker->boolean(70),
            'messages_count' => $this->faker->numberBetween(20, 100),
            'phone_number_shared' => $this->faker->boolean(60),
            'social_media_connected' => $this->faker->boolean(50),
            'relationship_potential_score' => $this->faker->numberBetween(7, 10),
        ]);
    }

    public function longDistance(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_data' => array_merge($attributes['location_data'], [
                'distance_km' => $this->faker->numberBetween(50, 500),
                'same_city' => false,
                'same_neighborhood' => false,
                'timezone_difference' => $this->faker->numberBetween(1, 8),
            ]),
            'premium_features_used' => array_merge($attributes['premium_features_used'], [
                'passport_feature_used' => true,
            ]),
        ]);
    }

    public function local(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_data' => array_merge($attributes['location_data'], [
                'distance_km' => $this->faker->numberBetween(1, 15),
                'same_city' => true,
                'same_neighborhood' => $this->faker->boolean(60),
                'timezone_difference' => 0,
            ]),
            'match_source' => 'proximity_match',
        ]);
    }
}