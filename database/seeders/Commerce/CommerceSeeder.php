<?php

namespace Database\Seeders;

use Database\Seeders\Commerce\CoinSeeder;
use Database\Seeders\Commerce\OrderSeeder;
use Database\Seeders\Commerce\PaymentSeeder;
use Database\Seeders\Commerce\PlanSeeder;
use Database\Seeders\Commerce\ProductSeeder;
use Database\Seeders\Commerce\SubscriptionSeeder;
use Database\Seeders\Commerce\WalletSeeder;
use Illuminate\Database\Seeder;

class CommerceSeeder extends Seeder
{
    /**
     * Run the database seeds for the Commerce domain.
     * 
     * This seeder orchestrates the creation of all commerce-related data
     * in the correct order to maintain referential integrity.
     */
    public function run(): void
    {
        $this->command->info('🛒 Seeding Commerce Domain...');

        // Step 1: Core Product Definitions (no dependencies)
        $this->command->info('   📦 Creating product catalog...');
        $this->call([
            CoinSeeder::class,
            PlanSeeder::class,
        ]);

        // Step 2: User Wallets (depends on users existing)
        $this->command->info('   💰 Setting up user wallets...');
        $this->call(WalletSeeder::class);

        // Step 3: Products (polymorphic wrapper around coins/plans)
        $this->command->info('   🏷️  Creating product entries...');
        $this->call(ProductSeeder::class);

        // Step 4: Subscriptions (depends on users and plans)
        $this->command->info('   📋 Creating user subscriptions...');
        $this->call(SubscriptionSeeder::class);

        // Step 5: Orders (depends on users, products, subscriptions)
        $this->command->info('   📄 Generating order history...');
        $this->call(OrderSeeder::class);

        // Step 6: Payments (depends on orders and users)
        $this->command->info('   💳 Processing payment records...');
        $this->call(PaymentSeeder::class);

        $this->command->info('✅ Commerce Domain seeding completed successfully!');
    }
}