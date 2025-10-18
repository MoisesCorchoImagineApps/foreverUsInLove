<?php

namespace Database\Factories\User;

use App\Models\User\UserSetting;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * UserSettingFactory - Factory for UserSetting Model
 * 
 * Generates comprehensive user preference configurations
 * Handles all User model relationships and settings business logic
 * Supports realistic dating app preference combinations and premium features
 */
class UserSettingFactory extends Factory
{
    protected $model = UserSetting::class;

    // Realistic preference data for dating apps
    private static $genderPreferences = [
        'male' => [['male'], ['female'], ['male', 'female'], ['non_binary'], ['male', 'female', 'non_binary']],
        'female' => [['male'], ['female'], ['male', 'female'], ['non_binary'], ['male', 'female', 'non_binary']],
        'non_binary' => [['non_binary'], ['male', 'female', 'non_binary'], ['female'], ['male']],
    ];

    private static $interestCategories = [
        'lifestyle' => ['fitness', 'yoga', 'meditation', 'travel', 'food', 'wine', 'coffee'],
        'entertainment' => ['movies', 'music', 'concerts', 'theater', 'comedy', 'festivals'],
        'sports' => ['football', 'basketball', 'tennis', 'golf', 'running', 'cycling', 'swimming'],
        'creative' => ['art', 'photography', 'writing', 'music_production', 'crafts', 'design'],
        'intellectual' => ['reading', 'science', 'technology', 'politics', 'philosophy', 'languages'],
        'social' => ['volunteering', 'networking', 'parties', 'dining_out', 'community_events'],
        'outdoor' => ['hiking', 'camping', 'rock_climbing', 'surfing', 'skiing', 'fishing'],
        'cultural' => ['museums', 'history', 'cultural_events', 'international_cuisine', 'traditions'],
    ];

    private static $currencies = [
        'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'CHF', 'SEK', 'NOK', 'DKK', 'JPY'
    ];

    private static $timezones = [
        'America/New_York', 'America/Los_Angeles', 'America/Chicago', 'America/Toronto',
        'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Europe/Rome',
        'Asia/Tokyo', 'Asia/Shanghai', 'Australia/Sydney', 'Australia/Melbourne'
    ];

    private static $languages = [
        'en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh', 'ja', 'ko', 'ar', 'hi'
    ];

    public function definition(): array
    {
        // Generate user context for realistic preferences
        $userAge = $this->faker->numberBetween(18, 65);
        $isYoungUser = $userAge <= 30;
        $isPremium = $this->faker->boolean(15); // 15% premium users
        
        // Age preferences based on user's age
        $minAge = max(18, $userAge - $this->faker->numberBetween(3, 10));
        $maxAge = min(65, $userAge + $this->faker->numberBetween(5, 15));
        
        // Distance preferences (younger users tend to prefer smaller distances)
        $maxDistance = $isYoungUser 
            ? $this->faker->randomElement([10, 25, 50])
            : $this->faker->randomElement([25, 50, 100, 250]);

        return [
            'user_id' => User::factory(),
            
            // DISCOVERY & MATCHING PREFERENCES
            'discovery_mode' => $this->faker->randomElement(['everyone', 'nearby', 'selected']),
            'show_me_in_discovery' => $this->faker->boolean(90), // Most users want to be discoverable
            'min_age_preference' => $minAge,
            'max_age_preference' => $maxAge,
            'max_distance_km' => $maxDistance,
            'gender_preferences' => $this->generateGenderPreferences(),
            'interest_preferences' => $this->generateInterestPreferences(),
            'show_verified_only' => $this->faker->boolean(25),
            'show_profiles_with_photos_only' => $this->faker->boolean(80),
            'distance_unit' => $this->faker->randomElement(['km', 'miles']),
            
            // NOTIFICATION PREFERENCES
            'notifications_enabled' => $this->faker->boolean(85),
            'email_notifications' => $this->faker->boolean(60),
            'push_notifications' => $this->faker->boolean(80),
            'sms_notifications' => $this->faker->boolean(30),
            'notify_new_matches' => $this->faker->boolean(95),
            'notify_new_messages' => $this->faker->boolean(90),
            'notify_new_likes' => $this->faker->boolean(70),
            'notify_profile_views' => $this->faker->boolean(40),
            'notify_super_likes' => $this->faker->boolean(95),
            'notify_new_followers' => $this->faker->boolean(50),
            'notify_mentions' => $this->faker->boolean(80),
            'notify_promotions' => $this->faker->boolean(30),
            'notification_schedule' => $this->generateNotificationSchedule(),
            'do_not_disturb' => $this->faker->boolean(40),
            'do_not_disturb_start' => $this->faker->optional(0.4)->time('H:i'),
            'do_not_disturb_end' => $this->faker->optional(0.4)->time('H:i'),
            
            // PRIVACY SETTINGS
            'profile_visibility' => $this->faker->randomElement(['public', 'friends', 'private']),
            'show_online_status' => $this->faker->boolean(60),
            'show_last_active' => $this->faker->boolean(40),
            'show_read_receipts' => $this->faker->boolean(50),
            'show_typing_indicator' => $this->faker->boolean(70),
            'allow_messages_from_non_matches' => $this->faker->boolean(20),
            'incognito_mode' => $this->faker->boolean(10),
            'hide_profile_from_contacts' => $this->faker->boolean(60),
            'blocked_users_ids' => $this->generateBlockedUsers(),
            
            // CHAT & COMMUNICATION
            'auto_reply_enabled' => $this->faker->boolean(15),
            'auto_reply_message' => $this->faker->optional(0.15)->randomElement([
                "Thanks for your message! I'll get back to you soon.",
                "Hi! I'm currently busy but will respond when I can.",
                "Thanks for reaching out! Currently exploring connections.",
                "Hello! I appreciate your interest and will reply soon."
            ]),
            'message_sound_enabled' => $this->faker->boolean(70),
            'message_preview' => $this->faker->randomElement(['full', 'name_only', 'none']),
            'chat_retention_days' => $this->faker->randomElement([30, 60, 90, 180, 365]),
            
            // CONTENT PREFERENCES
            'language' => $this->faker->randomElement(self::$languages),
            'timezone' => $this->faker->randomElement(self::$timezones),
            'theme' => $this->faker->randomElement(['light', 'dark', 'auto']),
            'reduce_motion' => $this->faker->boolean(10), // Accessibility
            'high_contrast' => $this->faker->boolean(5),  // Accessibility
            'content_filter' => $this->faker->randomElement(['none', 'moderate', 'strict']),
            'show_explicit_content' => $this->faker->boolean(40),
            
            // SUBSCRIPTION & BILLING
            'is_premium' => $isPremium,
            'subscription_tier' => $isPremium ? 
                $this->faker->randomElement(['basic', 'premium', 'elite']) : 'free',
            'subscription_expires_at' => $isPremium ? 
                $this->faker->dateTimeBetween('now', '+1 year') : null,
            'auto_renew_subscription' => $isPremium ? $this->faker->boolean(70) : false,
            'preferred_currency' => $this->faker->randomElement(self::$currencies),
            
            // ADVANCED FEATURES
            'boost_enabled' => $isPremium ? $this->faker->boolean(30) : false,
            'super_likes_remaining' => $isPremium ? 
                $this->faker->numberBetween(10, 50) : $this->faker->numberBetween(0, 5),
            'rewinds_remaining' => $isPremium ? 
                $this->faker->numberBetween(10, 30) : $this->faker->numberBetween(0, 3),
            'last_boost_at' => $this->faker->optional(0.2)->dateTimeBetween('-7 days', 'now'),
            'passport_enabled' => $isPremium ? $this->faker->boolean(40) : false,
            'passport_locations' => $isPremium ? $this->generatePassportLocations() : null,
            'read_receipts_enabled' => $isPremium ? $this->faker->boolean(60) : false,
            'unlimited_likes' => $isPremium,
            
            // SAFETY & SECURITY
            'photo_verification_required' => $this->faker->boolean(30),
            'safe_mode_enabled' => $this->faker->boolean(20),
            'share_location_enabled' => $this->faker->boolean(50),
            'panic_mode_enabled' => $this->faker->boolean(15),
            'trusted_contacts' => $this->generateTrustedContacts(),
            
            // DATA & ANALYTICS
            'data_collection_consent' => $this->faker->boolean(70),
            'personalization_enabled' => $this->faker->boolean(80),
            'analytics_tracking' => $this->faker->boolean(60),
            'custom_preferences' => $this->generateCustomPreferences(),
            
            // Timestamps
            'created_at' => $this->faker->dateTimeBetween('-2 years', '-1 day'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Generate realistic gender preferences
     */
    private function generateGenderPreferences(): array
    {
        $userGender = $this->faker->randomElement(['male', 'female', 'non_binary']);
        $preferences = self::$genderPreferences[$userGender] ?? [['male', 'female']];
        
        return $this->faker->randomElement($preferences);
    }

    /**
     * Generate interest preferences from categories
     */
    private function generateInterestPreferences(): array
    {
        $selectedCategories = $this->faker->randomElements(
            array_keys(self::$interestCategories), 
            $this->faker->numberBetween(2, 5)
        );
        
        $interests = [];
        foreach ($selectedCategories as $category) {
            $categoryInterests = $this->faker->randomElements(
                self::$interestCategories[$category],
                $this->faker->numberBetween(1, 3)
            );
            $interests = array_merge($interests, $categoryInterests);
        }
        
        return array_unique($interests);
    }

    /**
     * Generate notification schedule preferences
     */
    private function generateNotificationSchedule(): array
    {
        return [
            'monday' => ['start' => '09:00', 'end' => '22:00', 'enabled' => true],
            'tuesday' => ['start' => '09:00', 'end' => '22:00', 'enabled' => true],
            'wednesday' => ['start' => '09:00', 'end' => '22:00', 'enabled' => true],
            'thursday' => ['start' => '09:00', 'end' => '22:00', 'enabled' => true],
            'friday' => ['start' => '09:00', 'end' => '23:00', 'enabled' => true],
            'saturday' => ['start' => '10:00', 'end' => '23:00', 'enabled' => true],
            'sunday' => ['start' => '10:00', 'end' => '22:00', 'enabled' => true],
        ];
    }

    /**
     * Generate blocked users list (small percentage have blocked users)
     */
    private function generateBlockedUsers(): ?array
    {
        if ($this->faker->boolean(20)) { // 20% have blocked users
            return $this->faker->randomElements(
                range(1, 10000), 
                $this->faker->numberBetween(1, 5)
            );
        }
        
        return null;
    }

    /**
     * Generate passport locations for premium users
     */
    private function generatePassportLocations(): ?array
    {
        $locations = [
            ['city' => 'New York', 'country' => 'US', 'lat' => 40.7128, 'lng' => -74.0060],
            ['city' => 'London', 'country' => 'GB', 'lat' => 51.5074, 'lng' => -0.1278],
            ['city' => 'Paris', 'country' => 'FR', 'lat' => 48.8566, 'lng' => 2.3522],
            ['city' => 'Tokyo', 'country' => 'JP', 'lat' => 35.6762, 'lng' => 139.6503],
            ['city' => 'Sydney', 'country' => 'AU', 'lat' => -33.8688, 'lng' => 151.2093],
            ['city' => 'Barcelona', 'country' => 'ES', 'lat' => 41.3851, 'lng' => 2.1734],
            ['city' => 'Miami', 'country' => 'US', 'lat' => 25.7617, 'lng' => -80.1918],
            ['city' => 'Berlin', 'country' => 'DE', 'lat' => 52.5200, 'lng' => 13.4050],
        ];
        
        return $this->faker->randomElements(
            $locations, 
            $this->faker->numberBetween(1, 3)
        );
    }

    /**
     * Generate trusted contacts for safety features
     */
    private function generateTrustedContacts(): ?array
    {
        if ($this->faker->boolean(30)) { // 30% have trusted contacts
            $contacts = [];
            $count = $this->faker->numberBetween(1, 3);
            
            for ($i = 0; $i < $count; $i++) {
                $contacts[] = [
                    'name' => $this->faker->name(),
                    'phone' => $this->faker->phoneNumber(),
                    'email' => $this->faker->email(),
                    'relationship' => $this->faker->randomElement([
                        'friend', 'family', 'partner', 'sibling', 'parent'
                    ]),
                    'primary' => $i === 0,
                ];
            }
            
            return $contacts;
        }
        
        return null;
    }

    /**
     * Generate custom preferences for advanced users
     */
    private function generateCustomPreferences(): ?array
    {
        if ($this->faker->boolean(40)) { // 40% have custom preferences
            return [
                'date_style_preference' => $this->faker->randomElement([
                    'casual', 'formal', 'outdoor', 'cultural', 'adventurous'
                ]),
                'communication_style' => $this->faker->randomElement([
                    'direct', 'casual', 'formal', 'playful', 'intellectual'
                ]),
                'relationship_pace' => $this->faker->randomElement([
                    'slow', 'moderate', 'fast', 'flexible'
                ]),
                'meeting_preference' => $this->faker->randomElement([
                    'coffee', 'drinks', 'dinner', 'activity', 'video_call_first'
                ]),
                'deal_breakers' => $this->faker->randomElements([
                    'smoking', 'drinking', 'no_career_goals', 'different_values',
                    'no_sense_of_humor', 'poor_communication'
                ], $this->faker->numberBetween(1, 3)),
                'preferred_age_gap' => $this->faker->randomElement([
                    'same_age', 'slightly_older', 'slightly_younger', 'significant_gap_ok'
                ]),
                'long_distance_ok' => $this->faker->boolean(30),
                'pets_preference' => $this->faker->randomElement([
                    'love_pets', 'allergic', 'no_preference', 'no_pets'
                ]),
                'family_importance' => $this->faker->randomElement([
                    'very_important', 'somewhat_important', 'not_important'
                ]),
            ];
        }
        
        return null;
    }

    /**
     * Factory State: Premium User
     */
    public function premium(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_premium' => true,
                'subscription_tier' => $this->faker->randomElement(['premium', 'elite']),
                'subscription_expires_at' => $this->faker->dateTimeBetween('now', '+1 year'),
                'auto_renew_subscription' => $this->faker->boolean(80),
                'unlimited_likes' => true,
                'read_receipts_enabled' => true,
                'passport_enabled' => true,
                'passport_locations' => $this->generatePassportLocations(),
                'super_likes_remaining' => $this->faker->numberBetween(20, 50),
                'rewinds_remaining' => $this->faker->numberBetween(15, 30),
                'boost_enabled' => $this->faker->boolean(50),
            ];
        });
    }

    /**
     * Factory State: Free User
     */
    public function free(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_premium' => false,
                'subscription_tier' => 'free',
                'subscription_expires_at' => null,
                'auto_renew_subscription' => false,
                'unlimited_likes' => false,
                'read_receipts_enabled' => false,
                'passport_enabled' => false,
                'passport_locations' => null,
                'super_likes_remaining' => $this->faker->numberBetween(0, 5),
                'rewinds_remaining' => $this->faker->numberBetween(0, 3),
                'boost_enabled' => false,
            ];
        });
    }

    /**
     * Factory State: Privacy Focused User
     */
    public function privacyFocused(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'profile_visibility' => 'private',
                'show_online_status' => false,
                'show_last_active' => false,
                'show_read_receipts' => false,
                'allow_messages_from_non_matches' => false,
                'incognito_mode' => true,
                'hide_profile_from_contacts' => true,
                'data_collection_consent' => false,
                'analytics_tracking' => false,
                'safe_mode_enabled' => true,
                'photo_verification_required' => true,
            ];
        });
    }

    /**
     * Factory State: Social User (high engagement)
     */
    public function social(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'profile_visibility' => 'public',
                'show_online_status' => true,
                'show_last_active' => true,
                'show_read_receipts' => true,
                'show_typing_indicator' => true,
                'allow_messages_from_non_matches' => true,
                'notifications_enabled' => true,
                'push_notifications' => true,
                'notify_new_matches' => true,
                'notify_new_messages' => true,
                'notify_new_likes' => true,
                'notify_profile_views' => true,
            ];
        });
    }

    /**
     * Factory State: Selective User (high standards)
     */
    public function selective(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'discovery_mode' => 'selected',
                'show_verified_only' => true,
                'show_profiles_with_photos_only' => true,
                'photo_verification_required' => true,
                'max_distance_km' => $this->faker->randomElement([10, 25]),
                'interest_preferences' => $this->faker->randomElements(
                    array_merge(...array_values(self::$interestCategories)),
                    $this->faker->numberBetween(5, 8)
                ),
            ];
        });
    }

    /**
     * Factory State: International User
     */
    public function international(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'language' => $this->faker->randomElement(['es', 'fr', 'de', 'pt', 'it']),
                'timezone' => $this->faker->randomElement([
                    'Europe/Madrid', 'Europe/Paris', 'Europe/Berlin', 
                    'America/Sao_Paulo', 'Asia/Shanghai'
                ]),
                'preferred_currency' => $this->faker->randomElement(['EUR', 'GBP', 'CHF']),
                'max_distance_km' => $this->faker->randomElement([100, 250, 500]),
                'passport_enabled' => $this->faker->boolean(60),
            ];
        });
    }

    /**
     * Factory State: Active Dater
     */
    public function activeDater(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'discovery_mode' => 'everyone',
                'show_me_in_discovery' => true,
                'max_distance_km' => $this->faker->randomElement([50, 100]),
                'notifications_enabled' => true,
                'super_likes_remaining' => $this->faker->numberBetween(3, 20),
                'last_boost_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
                'message_sound_enabled' => true,
                'personalization_enabled' => true,
            ];
        });
    }

    /**
     * Factory State: Accessibility Needs
     */
    public function accessibilityNeeds(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'reduce_motion' => true,
                'high_contrast' => true,
                'theme' => 'light', // Better for screen readers
                'message_sound_enabled' => true,
                'message_preview' => 'full',
                'notifications_enabled' => true,
                'push_notifications' => true,
            ];
        });
    }
}