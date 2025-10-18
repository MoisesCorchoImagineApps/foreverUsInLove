<?php

namespace Database\Seeders;

use App\Models\Auth\User;
use App\Models\User\Profile;
use App\Models\User\UserImage;
use App\Models\User\UserSetting;
use App\Models\User\PersonalityTest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * UserSeeder - Creates realistic user ecosystem for ForeverUsInLove
 * 
 * Generates diverse user base with complete relationship data
 * Includes all user types and scenarios for comprehensive testing
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('👥 Creating main user ecosystem...');
        
        // Disable query log for performance
        DB::connection()->disableQueryLog();
        
        // Create different user segments
        $this->createPremiumUsers();
        $this->createActiveUsers(); 
        $this->createNewUsers();
        $this->createInternationalUsers();
        $this->createHighEngagementUsers();
        $this->createVerifiedUsers();
        $this->createDiverseUserTypes();
        $this->createInactiveUsers();
        
        $this->command->info('✅ User ecosystem created successfully!');
    }
    
    /**
     * Create premium users with all advanced features
     */
    private function createPremiumUsers(): void
    {
        $this->command->info('💎 Creating premium users...');
        
        User::factory()
            ->premium()
            ->count(25)
            ->create();
            
        $this->command->info('  ✓ Created 25 premium users with complete profiles');
    }
    
    /**
     * Create active users with complete profiles
     */
    private function createActiveUsers(): void
    {
        $this->command->info('🎯 Creating active users...');
        
        User::factory()
            ->withCompleteProfile()
            ->count(150)
            ->create();
            
        $this->command->info('  ✓ Created 150 active users with complete profiles');
    }
    
    /**
     * Create new users still setting up their profiles
     */
    private function createNewUsers(): void
    {
        $this->command->info('🆕 Creating new users...');
        
        User::factory()
            ->newUser()
            ->count(50)
            ->create();
            
        $this->command->info('  ✓ Created 50 new users with incomplete profiles');
    }
    
    /**
     * Create international users from different countries
     */
    private function createInternationalUsers(): void
    {
        $this->command->info('🌍 Creating international users...');
        
        User::factory()
            ->international()
            ->count(75)
            ->create();
            
        $this->command->info('  ✓ Created 75 international users');
    }
    
    /**
     * Create high engagement users (popular profiles)
     */
    private function createHighEngagementUsers(): void
    {
        $this->command->info('⭐ Creating high engagement users...');
        
        User::factory()
            ->highEngagement()
            ->count(30)
            ->create();
            
        $this->command->info('  ✓ Created 30 high engagement users');
    }
    
    /**
     * Create verified users with basic profiles
     */
    private function createVerifiedUsers(): void
    {
        $this->command->info('✅ Creating verified users...');
        
        User::factory()
            ->verified()
            ->count(100)
            ->afterCreating(function (User $user) {
                // Create basic profile
                Profile::factory()
                    ->for($user)
                    ->create();
                    
                // Create settings
                UserSetting::factory()
                    ->for($user)
                    ->create();
                    
                // Add some photos
                if (fake()->boolean(70)) {
                    UserImage::factory()
                        ->for($user)
                        ->count(fake()->numberBetween(1, 3))
                        ->sequence(
                            fn($sequence) => [
                                'is_primary' => $sequence->index === 0,
                                'order' => $sequence->index,
                            ]
                        )
                        ->approved()
                        ->create();
                }
                
                // Add personality test (50% chance)
                if (fake()->boolean(50)) {
                    PersonalityTest::factory()
                        ->for($user)
                        ->completed()
                        ->create();
                }
            })
            ->create();
            
        $this->command->info('  ✓ Created 100 verified users');
    }
    
    /**
     * Create diverse user types for testing different scenarios
     */
    private function createDiverseUserTypes(): void
    {
        $this->command->info('🎭 Creating diverse user types...');
        
        // Privacy-focused users
        User::factory()
            ->verified()
            ->count(20)
            ->afterCreating(function (User $user) {
                Profile::factory()
                    ->for($user)
                    ->create();
                    
                UserSetting::factory()
                    ->for($user)
                    ->privacyFocused()
                    ->create();
            })
            ->create();
            
        // Social/active daters
        User::factory()
            ->verified()
            ->count(30)
            ->afterCreating(function (User $user) {
                Profile::factory()
                    ->for($user)
                    ->recentlyActive()
                    ->create();
                    
                UserSetting::factory()
                    ->for($user)
                    ->social()
                    ->activeDater()
                    ->create();
                    
                // More photos for social users
                UserImage::factory()
                    ->for($user)
                    ->count(fake()->numberBetween(3, 6))
                    ->sequence(
                        fn($sequence) => [
                            'is_primary' => $sequence->index === 0,
                            'order' => $sequence->index,
                        ]
                    )
                    ->approved()
                    ->create();
            })
            ->create();
            
        // Selective users (high standards)
        User::factory()
            ->verified()
            ->count(15)
            ->afterCreating(function (User $user) {
                Profile::factory()
                    ->for($user)
                    ->verified()
                    ->create();
                    
                UserSetting::factory()
                    ->for($user)
                    ->selective()
                    ->create();
                    
                // High quality photos
                UserImage::factory()
                    ->for($user)
                    ->count(fake()->numberBetween(2, 4))
                    ->highQuality()
                    ->professional()
                    ->sequence(
                        fn($sequence) => [
                            'is_primary' => $sequence->index === 0,
                            'order' => $sequence->index,
                        ]
                    )
                    ->create();
                    
                // Multiple personality tests
                PersonalityTest::factory()
                    ->for($user)
                    ->count(fake()->numberBetween(2, 4))
                    ->sequence(
                        ['test_type' => 'mbti'],
                        ['test_type' => 'big_five'],
                        ['test_type' => 'love_language'],
                        ['test_type' => 'attachment_style']
                    )
                    ->completed()
                    ->create();
            })
            ->create();
            
        $this->command->info('  ✓ Created diverse user types (65 total)');
    }
    
    /**
     * Create inactive/suspended users for testing moderation
     */
    private function createInactiveUsers(): void
    {
        $this->command->info('😴 Creating inactive users...');
        
        // Suspended users
        User::factory()
            ->suspended()
            ->count(10)
            ->create();
            
        // Inactive users (haven't logged in recently)
        User::factory()
            ->state([
                'status' => 'inactive',
                'last_active_at' => fake()->dateTimeBetween('-6 months', '-2 months'),
                'last_login_at' => fake()->dateTimeBetween('-6 months', '-2 months'),
            ])
            ->count(25)
            ->afterCreating(function (User $user) {
                Profile::factory()
                    ->for($user)
                    ->state(['status' => 'paused'])
                    ->create();
                    
                UserSetting::factory()
                    ->for($user)
                    ->create();
            })
            ->create();
            
        $this->command->info('  ✓ Created 35 inactive/suspended users');
    }
}