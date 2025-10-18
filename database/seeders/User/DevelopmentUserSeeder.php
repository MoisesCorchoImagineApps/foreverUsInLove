<?php

namespace Database\Seeders\User;

use App\Models\Auth\User;
use App\Models\User\Profile;
use App\Models\User\UserImage;
use App\Models\User\UserSetting;
use App\Models\User\PersonalityTest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DevelopmentUserSeeder - Creates specific test accounts for development
 *
 * Creates well-known test accounts with specific characteristics
 * for consistent development and testing scenarios
 */
class DevelopmentUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🧪 Creating development test accounts...');

        $this->createMainTestUser();
        $this->createPremiumTestUser();
        $this->createNewUserTestAccount();
        $this->createInternationalTestUsers();
        $this->createMatchingTestUsers();
        $this->createEdgeCaseTestUsers();

        $this->command->info('✅ Development test accounts created successfully!');
    }

    /**
     * Create main test user account
     */
    private function createMainTestUser(): void
    {
        $testUser = User::factory()->create([
            'email' => 'test@foreverusinlove.com',
            'username' => 'testuser',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now()->subDays(30),
            'phone_verified_at' => now()->subDays(25),
            'phone' => '+1-555-TEST-001',
            'last_active_at' => now()->subHour(),
            'created_at' => now()->subMonths(6),
        ]);

        // Create complete test profile
        Profile::factory()->for($testUser)->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'display_name' => 'Test User',
            'bio' => 'This is a test user account for development and testing purposes. I love coding, coffee, and creating amazing dating experiences!',
            'age' => 28,
            'date_of_birth' => now()->subYears(28),
            'gender' => 'male',
            'sexual_orientation' => 'straight',
            'relationship_status' => 'single',
            'looking_for' => 'relationship',
            'city' => 'San Francisco',
            'state' => 'CA',
            'country' => 'US',
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'height_cm' => 180,
            'body_type' => 'athletic',
            'ethnicity' => 'mixed',
            'religion' => 'agnostic',
            'education' => 'masters',
            'occupation' => 'Software Engineer',
            'company' => 'ForeverUsInLove',
            'school' => 'Stanford University',
            'has_children' => false,
            'wants_children' => true,
            'smoking' => 'never',
            'drinking' => 'socially',
            'languages' => ['en', 'es', 'fr'],
            'interests' => [
                'technology',
                'travel',
                'photography',
                'hiking',
                'cooking',
                'music',
                'reading',
                'fitness',
                'movies',
                'art'
            ],
            'hobbies' => ['coding', 'rock_climbing', 'guitar', 'chess'],
            'music_preferences' => ['rock', 'electronic', 'indie', 'jazz'],
            'movie_preferences' => ['sci-fi', 'thriller', 'comedy', 'documentary'],
            'personality_type' => 'INTJ',
            'values' => ['honesty', 'growth', 'adventure', 'family', 'creativity'],
            'zodiac_sign' => 'virgo',
            'tagline' => 'Building the future, one line of code at a time ⚡',
            'status' => 'active',
            'completeness_percentage' => 100,
            'is_verified' => true,
            'visibility_radius_km' => 50,
        ]);

        // Create test user settings
        UserSetting::factory()->for($testUser)->create([
            'discovery_mode' => 'everyone',
            'min_age_preference' => 22,
            'max_age_preference' => 35,
            'max_distance_km' => 50,
            'gender_preferences' => ['female'],
            'is_premium' => false,
            'subscription_tier' => 'free',
            'notifications_enabled' => true,
            'push_notifications' => true,
            'email_notifications' => true,
        ]);

        // Create test photos
        UserImage::factory()->for($testUser)->count(4)->sequence(
            ['is_primary' => true, 'order' => 0, 'status' => 'active'],
            ['is_primary' => false, 'order' => 1, 'status' => 'active'],
            ['is_primary' => false, 'order' => 2, 'status' => 'active'],
            ['is_primary' => false, 'order' => 3, 'status' => 'pending_moderation']
        )->create();

        // Create personality tests
        PersonalityTest::factory()->for($testUser)->mbti()->completed()->create();
        PersonalityTest::factory()->for($testUser)->loveLanguage()->completed()->create();

        $this->command->info('  ✓ Created Main Test User (test@foreverusinlove.com)');
    }

    /**
     * Create premium test user
     */
    private function createPremiumTestUser(): void
    {
        $premiumUser = User::factory()->create([
            'email' => 'premium@foreverusinlove.com',
            'username' => 'premiumuser',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now()->subMonths(3),
            'phone_verified_at' => now()->subMonths(3),
            'phone' => '+1-555-PREM-001',
            'two_factor_enabled' => true,
            'face_id_enabled' => true,
            'last_active_at' => now()->subMinutes(30),
            'created_at' => now()->subMonths(8),
        ]);

        // Create premium profile
        Profile::factory()->for($premiumUser)->establishedProfessional()->verified()->create([
            'first_name' => 'Premium',
            'last_name' => 'User',
            'display_name' => 'Premium User',
            'bio' => 'Premium subscriber testing all the advanced features of ForeverUsInLove. Looking for meaningful connections!',
            'occupation' => 'Product Manager',
            'company' => 'Tech Startup',
            'annual_income_range' => '120000-180000',
        ]);

        // Create premium settings
        UserSetting::factory()->for($premiumUser)->premium()->create([
            'passport_enabled' => true,
            'unlimited_likes' => true,
            'super_likes_remaining' => 50,
            'rewinds_remaining' => 30,
            'boost_enabled' => true,
            'read_receipts_enabled' => true,
        ]);

        // Create high-quality photos
        UserImage::factory()->for($premiumUser)->count(6)->sequence(
            fn($sequence) => [
                'is_primary' => $sequence->index === 0,
                'order' => $sequence->index,
                'status' => 'active'
            ]
        )->highQuality()->create();

        // Create comprehensive personality profile
        PersonalityTest::factory()->for($premiumUser)->count(4)->sequence(
            ['test_type' => 'mbti'],
            ['test_type' => 'big_five'],
            ['test_type' => 'love_language'],
            ['test_type' => 'attachment_style']
        )->completed()->create();

        $this->command->info('  ✓ Created Premium Test User (premium@foreverusinlove.com)');
    }

    /**
     * Create new user test account
     */
    private function createNewUserTestAccount(): void
    {
        $newUser = User::factory()->create([
            'email' => 'newuser@foreverusinlove.com',
            'username' => 'newbie',
            'password' => Hash::make('password'),
            'status' => 'pending_verification',
            'email_verified_at' => null,
            'phone_verified_at' => null,
            'phone' => '+1-555-NEW-0001',
            'last_active_at' => now()->subMinutes(15),
            'created_at' => now()->subHours(2),
        ]);

        // Create incomplete profile
        Profile::factory()->for($newUser)->create([
            'first_name' => 'New',
            'last_name' => null,
            'display_name' => null,
            'bio' => null,
            'age' => 24,
            'date_of_birth' => now()->subYears(24),
            'gender' => 'female',
            'status' => 'incomplete',
            'completeness_percentage' => 25,
        ]);

        // Create default settings
        UserSetting::factory()->for($newUser)->free()->create();

        $this->command->info('  ✓ Created New User Test Account (newuser@foreverusinlove.com)');
    }

    /**
     * Create international test users
     */
    private function createInternationalTestUsers(): void
    {
        $internationalUsers = [
            [
                'email' => 'london@foreverusinlove.com',
                'username' => 'londonuser',
                'city' => 'London',
                'country' => 'GB',
                'phone' => '+44-20-1234-5678',
                'language' => 'en',
                'currency' => 'GBP',
            ],
            [
                'email' => 'paris@foreverusinlove.com',
                'username' => 'parisuser',
                'city' => 'Paris',
                'country' => 'FR',
                'phone' => '+33-1-23-45-67-89',
                'language' => 'fr',
                'currency' => 'EUR',
            ],
            [
                'email' => 'tokyo@foreverusinlove.com',
                'username' => 'tokyouser',
                'city' => 'Tokyo',
                'country' => 'JP',
                'phone' => '+81-3-1234-5678',
                'language' => 'ja',
                'currency' => 'JPY',
            ],
        ];

        foreach ($internationalUsers as $userData) {
            $user = User::factory()->create([
                'email' => $userData['email'],
                'username' => $userData['username'],
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now()->subDays(rand(10, 90)),
                'phone' => $userData['phone'],
                'phone_verified_at' => now()->subDays(rand(5, 85)),
                'created_at' => now()->subDays(rand(30, 180)),
            ]);

            Profile::factory()->for($user)->international()->create([
                'city' => $userData['city'],
                'country' => $userData['country'],
            ]);

            UserSetting::factory()->for($user)->international()->create([
                'language' => $userData['language'],
                'preferred_currency' => $userData['currency'],
            ]);
        }

        $this->command->info('  ✓ Created International Test Users (London, Paris, Tokyo)');
    }

    /**
     * Create users specifically for testing matching algorithms
     */
    private function createMatchingTestUsers(): void
    {
        // Create highly compatible users for testing
        $matchingUsers = User::factory()->count(10)->create([
            'status' => 'active',
            'email_verified_at' => now()->subDays(rand(1, 30)),
        ]);

        foreach ($matchingUsers as $index => $user) {
            $user->update([
                'email' => "match_test_{$index}@foreverusinlove.com",
                'username' => "match_test_user_{$index}",
            ]);

            Profile::factory()->for($user)->highlyCompatible()->create([
                'age' => 25 + ($index % 10), // Varying ages
                'city' => 'San Francisco', // Same city for distance testing
                'interests' => ['technology', 'travel', 'fitness', 'music'], // Common interests
                'values' => ['honesty', 'growth', 'adventure'], // Common values
            ]);

            UserSetting::factory()->for($user)->activeDater()->create([
                'min_age_preference' => 22,
                'max_age_preference' => 35,
                'max_distance_km' => 25,
            ]);

            // Add photos for visual matching
            UserImage::factory()->for($user)->count(3)->approved()->sequence(
                fn($sequence) => [
                    'is_primary' => $sequence->index === 0,
                    'order' => $sequence->index,
                ]
            )->create();

            // Add personality test for compatibility
            PersonalityTest::factory()->for($user)->mbti()->completed()->create();
        }

        $this->command->info('  ✓ Created 10 Matching Test Users');
    }

    /**
     * Create edge case test users for testing error scenarios
     */
    private function createEdgeCaseTestUsers(): void
    {
        // User with no profile
        $userNoProfile = User::factory()->create([
            'email' => 'noprofile@foreverusinlove.com',
            'username' => 'noprofile',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // User with incomplete profile
        $userIncomplete = User::factory()->create([
            'email' => 'incomplete@foreverusinlove.com',
            'username' => 'incomplete',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Profile::factory()->for($userIncomplete)->create([
            'first_name' => 'Incomplete',
            'status' => 'incomplete',
            'completeness_percentage' => 15,
        ]);

        // Suspended user
        $suspendedUser = User::factory()->suspended()->create([
            'email' => 'suspended@foreverusinlove.com',
            'username' => 'suspended',
            'password' => Hash::make('password'),
        ]);

        // User with all photos rejected
        $rejectedPhotos = User::factory()->create([
            'email' => 'rejectedphotos@foreverusinlove.com',
            'username' => 'rejectedphotos',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Profile::factory()->for($rejectedPhotos)->create();
        UserImage::factory()->for($rejectedPhotos)->count(3)->rejected()->create();

        $this->command->info('  ✓ Created Edge Case Test Users');
    }
}
