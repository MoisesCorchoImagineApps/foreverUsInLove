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
 * AdminUserSeeder - Creates administrative and moderator accounts
 *
 * Creates special accounts for app administration, moderation, and testing
 * These accounts have specific configurations for management purposes
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('👨‍💼 Creating administrative accounts...');

        $this->createSuperAdmin();
        $this->createModerators();
        $this->createCustomerSupport();
        $this->createContentModerators();

        $this->command->info('✅ Administrative accounts created successfully!');
    }

    /**
     * Create super admin account
     */
    private function createSuperAdmin(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@foreverusinlove.com',
            'username' => 'superadmin',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'phone' => '+1-555-000-0001',
            'two_factor_enabled' => true,
            'two_factor_secret' => 'ADMIN2FASECRET123456789012345',
            'face_id_enabled' => false,
            'gdpr_consents' => [
                'data_processing' => true,
                'marketing_communications' => false,
                'analytics_tracking' => true,
                'third_party_sharing' => false,
                'terms_of_service' => true,
                'privacy_policy' => true,
            ],
            'last_active_at' => now(),
            'created_at' => now()->subYears(2),
        ]);

        // Create admin profile
        Profile::factory()->for($admin)->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'display_name' => 'System Administrator',
            'bio' => 'ForeverUsInLove System Administrator',
            'age' => 35,
            'date_of_birth' => now()->subYears(35),
            'gender' => 'prefer_not_to_say',
            'status' => 'active',
            'completeness_percentage' => 100,
            'is_verified' => true,
            'city' => 'San Francisco',
            'state' => 'CA',
            'country' => 'US',
            'occupation' => 'System Administrator',
            'company' => 'ForeverUsInLove',
        ]);

        // Create admin settings
        UserSetting::factory()->for($admin)->create([
            'is_premium' => true,
            'subscription_tier' => 'elite',
            'unlimited_likes' => true,
            'read_receipts_enabled' => true,
            'passport_enabled' => true,
            'notifications_enabled' => true,
            'email_notifications' => true,
            'push_notifications' => true,
            'profile_visibility' => 'private',
            'incognito_mode' => true,
            'data_collection_consent' => true,
            'analytics_tracking' => true,
        ]);

        $this->command->info('  ✓ Created Super Admin (admin@foreverusinlove.com)');
    }

    /**
     * Create moderator accounts
     */
    private function createModerators(): void
    {
        $moderators = [
            [
                'email' => 'moderator1@foreverusinlove.com',
                'username' => 'moderator1',
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
            ],
            [
                'email' => 'moderator2@foreverusinlove.com',
                'username' => 'moderator2',
                'first_name' => 'Michael',
                'last_name' => 'Chen',
            ],
            [
                'email' => 'moderator3@foreverusinlove.com',
                'username' => 'moderator3',
                'first_name' => 'Emma',
                'last_name' => 'Rodriguez',
            ],
        ];

        foreach ($moderators as $index => $moderatorData) {
            $moderator = User::factory()->create([
                'email' => $moderatorData['email'],
                'username' => $moderatorData['username'],
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now()->subMonths(6),
                'phone_verified_at' => now()->subMonths(6),
                'phone' => '+1-555-001-000' . ($index + 1),
                'two_factor_enabled' => true,
                'last_active_at' => fake()->dateTimeBetween('-24 hours', 'now'),
                'created_at' => now()->subMonths(rand(6, 18)),
            ]);

            // Create moderator profile
            Profile::factory()->for($moderator)->create([
                'first_name' => $moderatorData['first_name'],
                'last_name' => $moderatorData['last_name'],
                'display_name' => $moderatorData['first_name'] . ' ' . substr($moderatorData['last_name'], 0, 1) . '.',
                'bio' => 'Content Moderator at ForeverUsInLove',
                'age' => fake()->numberBetween(25, 45),
                'date_of_birth' => fake()->dateTimeBetween('-45 years', '-25 years'),
                'gender' => fake()->randomElement(['male', 'female']),
                'status' => 'active',
                'completeness_percentage' => 95,
                'is_verified' => true,
                'occupation' => 'Content Moderator',
                'company' => 'ForeverUsInLove',
                'education' => fake()->randomElement(['bachelors', 'masters']),
                'city' => fake()->randomElement(['San Francisco', 'New York', 'Austin', 'Seattle']),
                'state' => fake()->randomElement(['CA', 'NY', 'TX', 'WA']),
                'country' => 'US',
            ]);

            // Create moderator settings
            UserSetting::factory()->for($moderator)->create([
                'is_premium' => true,
                'subscription_tier' => 'premium',
                'notifications_enabled' => true,
                'email_notifications' => true,
                'push_notifications' => true,
                'profile_visibility' => 'private',
                'show_online_status' => false,
                'incognito_mode' => true,
            ]);

            // Add professional photo
            UserImage::factory()->for($moderator)->professional()->primary()->create();
        }

        $this->command->info('  ✓ Created 3 Content Moderators');
    }

    /**
     * Create customer support accounts
     */
    private function createCustomerSupport(): void
    {
        $supportAgents = [
            [
                'email' => 'support1@foreverusinlove.com',
                'username' => 'support_agent1',
                'first_name' => 'Jessica',
                'last_name' => 'Williams',
            ],
            [
                'email' => 'support2@foreverusinlove.com',
                'username' => 'support_agent2',
                'first_name' => 'David',
                'last_name' => 'Brown',
            ],
        ];

        foreach ($supportAgents as $index => $agentData) {
            $agent = User::factory()->create([
                'email' => $agentData['email'],
                'username' => $agentData['username'],
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now()->subMonths(3),
                'phone_verified_at' => now()->subMonths(3),
                'phone' => '+1-555-002-000' . ($index + 1),
                'last_active_at' => fake()->dateTimeBetween('-8 hours', 'now'),
                'created_at' => now()->subMonths(rand(3, 12)),
            ]);

            // Create support agent profile
            Profile::factory()->for($agent)->create([
                'first_name' => $agentData['first_name'],
                'last_name' => $agentData['last_name'],
                'display_name' => $agentData['first_name'],
                'bio' => 'Customer Support Specialist at ForeverUsInLove',
                'age' => fake()->numberBetween(22, 35),
                'date_of_birth' => fake()->dateTimeBetween('-35 years', '-22 years'),
                'gender' => fake()->randomElement(['male', 'female']),
                'status' => 'active',
                'completeness_percentage' => 90,
                'is_verified' => true,
                'occupation' => 'Customer Support Specialist',
                'company' => 'ForeverUsInLove',
                'education' => fake()->randomElement(['some_college', 'bachelors']),
                'city' => fake()->randomElement(['Phoenix', 'Denver', 'Miami', 'Portland']),
                'country' => 'US',
            ]);

            // Create support agent settings
            UserSetting::factory()->for($agent)->create([
                'notifications_enabled' => true,
                'email_notifications' => true,
                'push_notifications' => true,
                'profile_visibility' => 'private',
                'auto_reply_enabled' => true,
                'auto_reply_message' => 'Hi! I\'m a ForeverUsInLove support agent. How can I help you today?',
            ]);
        }

        $this->command->info('  ✓ Created 2 Customer Support Agents');
    }

    /**
     * Create content moderators for photo/profile review
     */
    private function createContentModerators(): void
    {
        $contentMods = [
            [
                'email' => 'content_mod1@foreverusinlove.com',
                'username' => 'content_moderator1',
                'first_name' => 'Alex',
                'last_name' => 'Taylor',
            ],
            [
                'email' => 'content_mod2@foreverusinlove.com',
                'username' => 'content_moderator2',
                'first_name' => 'Jordan',
                'last_name' => 'Martinez',
            ],
        ];

        foreach ($contentMods as $index => $modData) {
            $mod = User::factory()->create([
                'email' => $modData['email'],
                'username' => $modData['username'],
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now()->subMonths(4),
                'phone_verified_at' => now()->subMonths(4),
                'phone' => '+1-555-003-000' . ($index + 1),
                'last_active_at' => fake()->dateTimeBetween('-12 hours', 'now'),
                'created_at' => now()->subMonths(rand(4, 15)),
            ]);

            // Create content moderator profile
            Profile::factory()->for($mod)->create([
                'first_name' => $modData['first_name'],
                'last_name' => $modData['last_name'],
                'display_name' => $modData['first_name'],
                'bio' => 'Photo & Content Moderator',
                'age' => fake()->numberBetween(24, 40),
                'date_of_birth' => fake()->dateTimeBetween('-40 years', '-24 years'),
                'gender' => 'non_binary',
                'status' => 'active',
                'completeness_percentage' => 85,
                'is_verified' => true,
                'occupation' => 'Content Moderator',
                'company' => 'ForeverUsInLove',
                'education' => 'bachelors',
                'city' => fake()->randomElement(['Chicago', 'Boston', 'Atlanta', 'Las Vegas']),
                'country' => 'US',
            ]);

            // Create content moderator settings
            UserSetting::factory()->for($mod)->create([
                'notifications_enabled' => true,
                'email_notifications' => true,
                'push_notifications' => true,
                'profile_visibility' => 'private',
                'incognito_mode' => true,
                'safe_mode_enabled' => true,
                'content_filter' => 'none', // They need to see all content for moderation
            ]);
        }

        $this->command->info('  ✓ Created 2 Content Moderators');
    }
}
