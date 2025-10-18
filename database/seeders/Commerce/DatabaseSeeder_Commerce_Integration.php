<?php

namespace Database\Seeders;

use Database\Seeders\CommerceSeeder;
use Illuminate\Database\Seeder;

/**
 * Updated DatabaseSeeder with Commerce Domain Integration
 * 
 * This file shows how to integrate the Commerce domain seeding
 * into your existing DatabaseSeeder.php file.
 * 
 * INSTRUCTIONS:
 * 1. Copy the contents of the run() method below
 * 2. Replace or merge with your existing DatabaseSeeder.php run() method
 * 3. Ensure the Commerce seeder runs after User seeding but before any dependent seeders
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting ForeverUsInLove Database Seeding...');
        
        // ========================================
        // CORE DOMAIN SEEDING (Dependencies First)
        // ========================================
        
        $this->command->info('📊 Seeding Core Domains...');
        
        // 1. User Domain (Foundation - Required by all other domains)
        $this->command->info('👥 Seeding User Domain...');
        $this->call([
            // Add your existing User seeders here
            // UserSeeder::class,
            // ProfileSeeder::class,
            // etc.
        ]);
        
        // 2. Matching Domain (User interactions)
        $this->command->info('💝 Seeding Matching Domain...');
        $this->call([
            // Add your existing Matching seeders here
            // MatchSeeder::class,
            // LikeSeeder::class,
            // etc.
        ]);
        
        // 3. Chat Domain (Communication)
        $this->command->info('💬 Seeding Chat Domain...');
        $this->call([
            // Add your existing Chat seeders here
            // ConversationSeeder::class,
            // MessageSeeder::class,
            // etc.
        ]);
        
        // ========================================
        // COMMERCE DOMAIN SEEDING (New Addition)
        // ========================================
        
        $this->command->info('🛒 Seeding Commerce Domain...');
        $this->call(CommerceSeeder::class);
        
        // ========================================
        // ADDITIONAL DOMAINS (If any)
        // ========================================
        
        $this->command->info('⚙️ Seeding Additional Domains...');
        $this->call([
            // Add any other domain seeders here
            // NotificationSeeder::class,
            // AdminSeeder::class,
            // etc.
        ]);
        
        // ========================================
        // POST-SEEDING TASKS
        // ========================================
        
        $this->command->info('🔧 Running Post-Seeding Tasks...');
        $this->postSeedingTasks();
        
        $this->command->info('✅ Database seeding completed successfully!');
        $this->showSeedingSummary();
    }
    
    /**
     * Execute post-seeding tasks like cache warming, index optimization, etc.
     */
    private function postSeedingTasks(): void
    {
        // Example post-seeding tasks:
        
        // 1. Update search indexes
        // $this->command->info('   🔍 Updating search indexes...');
        // Artisan::call('scout:import', ['model' => 'App\Models\User\User']);
        
        // 2. Cache warming
        // $this->command->info('   🔥 Warming caches...');
        // Cache::tags(['plans', 'coins'])->flush();
        
        // 3. Generate analytics snapshots
        // $this->command->info('   📈 Generating initial analytics...');
        
        $this->command->info('   ✅ Post-seeding tasks completed');
    }
    
    /**
     * Display a summary of what was seeded.
     */
    private function showSeedingSummary(): void
    {
        $this->command->info('');
        $this->command->info('📋 SEEDING SUMMARY');
        $this->command->info('==================');
        
        // Get counts from database
        try {
            $userCount = \App\Models\User\User::count();
            $coinCount = \App\Models\Commerce\Coin::count();
            $planCount = \App\Models\Commerce\Plan::count();
            $productCount = \App\Models\Commerce\Product::count();
            $walletCount = \App\Models\Commerce\Wallet::count();
            $subscriptionCount = \App\Models\Commerce\Subscription::count();
            $orderCount = \App\Models\Commerce\Order::count();
            $paymentCount = \App\Models\Commerce\Payment::count();
            
            $this->command->info("👥 Users: {$userCount}");
            $this->command->info("🪙 Coin Packages: {$coinCount}");
            $this->command->info("📋 Subscription Plans: {$planCount}");
            $this->command->info("🏷️  Products: {$productCount}");
            $this->command->info("💰 Wallets: {$walletCount}");
            $this->command->info("📑 Subscriptions: {$subscriptionCount}");
            $this->command->info("📄 Orders: {$orderCount}");
            $this->command->info("💳 Payments: {$paymentCount}");
            
        } catch (\Exception $e) {
            $this->command->warn('Could not fetch seeding summary - some models may not exist yet.');
        }
        
        $this->command->info('');
        $this->command->info('🚀 Your ForeverUsInLove application is ready for testing!');
        $this->command->info('');
        
        // Helpful next steps
        $this->command->info('💡 NEXT STEPS:');
        $this->command->info('   • Run: php artisan serve');
        $this->command->info('   • Visit: http://localhost:8000');
        $this->command->info('   • Test commerce features with seeded data');
        $this->command->info('   • Check admin panel for commerce analytics');
        $this->command->info('');
    }
}

/**
 * INTEGRATION INSTRUCTIONS:
 * =========================
 * 
 * 1. BACKUP YOUR CURRENT DatabaseSeeder.php:
 *    cp database/seeders/DatabaseSeeder.php database/seeders/DatabaseSeeder.php.backup
 * 
 * 2. UPDATE YOUR DatabaseSeeder.php:
 *    - Copy the run() method above
 *    - Replace the existing run() method in your DatabaseSeeder.php
 *    - Add your existing seeder calls in the appropriate sections
 * 
 * 3. COPY COMMERCE SEEDER FILES:
 *    Copy all the seeder files to your database/seeders directory:
 *    - CommerceSeeder.php → database/seeders/
 *    - CoinSeeder.php → database/seeders/Commerce/
 *    - PlanSeeder.php → database/seeders/Commerce/
 *    - ProductSeeder.php → database/seeders/Commerce/
 *    - WalletSeeder.php → database/seeders/Commerce/
 *    - SubscriptionSeeder.php → database/seeders/Commerce/
 *    - OrderSeeder.php → database/seeders/Commerce/
 *    - PaymentSeeder.php → database/seeders/Commerce/
 * 
 * 4. COPY FACTORY FILES:
 *    Copy all factory files to your database/factories directory:
 *    - CoinFactory.php → database/factories/Models/Commerce/
 *    - PlanFactory.php → database/factories/Models/Commerce/
 *    - ProductFactory.php → database/factories/Models/Commerce/
 *    - WalletFactory.php → database/factories/Models/Commerce/
 *    - SubscriptionFactory.php → database/factories/Models/Commerce/
 *    - OrderFactory.php → database/factories/Models/Commerce/
 *    - PaymentFactory.php → database/factories/Models/Commerce/
 * 
 * 5. COPY MIGRATION FILES:
 *    Copy all migration files to your database/migrations directory:
 *    - 2024_10_15_000001_create_coins_table.php
 *    - 2024_10_15_000002_create_plans_table.php
 *    - 2024_10_15_000003_create_orders_table.php
 *    - 2024_10_15_000004_create_payments_table.php
 *    - 2024_10_15_000005_create_subscriptions_table.php
 *    - 2024_10_15_000006_create_wallets_table.php
 *    - 2024_10_15_000007_create_products_table.php
 * 
 * 6. RUN MIGRATIONS AND SEEDING:
 *    php artisan migrate:fresh --seed
 * 
 * 7. VERIFY INSTALLATION:
 *    - Check that all tables were created
 *    - Verify data was seeded correctly
 *    - Test commerce functionality
 * 
 * TROUBLESHOOTING:
 * ================
 * 
 * If you encounter issues:
 * 
 * 1. Model Not Found Errors:
 *    - Ensure your Commerce models exist in app/Models/Commerce/
 *    - Check namespace declarations in factories and seeders
 * 
 * 2. Migration Errors:
 *    - Verify foreign key constraints are correct
 *    - Check that referenced tables exist
 *    - Adjust migration timestamps if needed
 * 
 * 3. Seeding Errors:
 *    - Run seeders individually to identify issues
 *    - Check that dependencies are seeded first (Users before Commerce)
 *    - Verify factory states match your model requirements
 * 
 * 4. Performance Issues:
 *    - Reduce seeding quantities for development
 *    - Use database transactions for faster seeding
 *    - Consider using chunks for large datasets
 * 
 * CUSTOMIZATION:
 * ==============
 * 
 * To customize the seeding for your needs:
 * 
 * 1. Adjust quantities in individual seeders
 * 2. Modify factory states to match your business logic  
 * 3. Add custom seeding scenarios for your specific use cases
 * 4. Update distributions to match your expected user behavior
 * 
 * Remember: This seeding data is for development and testing.
 * Do not use these seeders in production environments.
 */