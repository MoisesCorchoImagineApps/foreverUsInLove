<?php

namespace Database\Seeders\Commerce;

use App\Models\Commerce\Coin;
use Database\Factories\Models\Commerce\CoinFactory;
use Illuminate\Database\Seeder;

class CoinSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create starter tier coins (entry level)
        Coin::factory()->starter()->count(3)->create([
            'is_popular' => false,
            'sort_order' => 100
        ]);

        // Create popular tier coins (most common)
        Coin::factory()->popular()->count(4)->create([
            'is_popular' => true,
            'sort_order' => 200
        ]);

        // Create value tier coins (best value)
        Coin::factory()->value()->count(3)->create([
            'is_featured' => true,
            'sort_order' => 300
        ]);

        // Create premium tier coins (high-end)
        Coin::factory()->premium()->count(3)->create([
            'is_featured' => true,
            'sort_order' => 400
        ]);

        // Create elite tier coins (luxury)
        Coin::factory()->elite()->count(2)->create([
            'is_featured' => true,
            'sort_order' => 500
        ]);

        // Create promotional coins (limited time offers)
        Coin::factory()->promotional()->count(2)->create([
            'is_popular' => true,
            'is_featured' => true,
            'sort_order' => 50 // Higher priority
        ]);

        // Create some archived/inactive coins for testing
        Coin::factory()->count(3)->create([
            'status' => 'archived',
            'visibility' => 'private'
        ]);

        // Create coins with different regional pricing
        Coin::factory()->count(2)->create([
            'regional_pricing' => [
                'USD' => ['price' => 9.99, 'currency' => 'USD'],
                'EUR' => ['price' => 8.99, 'currency' => 'EUR'],
                'GBP' => ['price' => 7.99, 'currency' => 'GBP'],
                'CAD' => ['price' => 12.99, 'currency' => 'CAD'],
                'AUD' => ['price' => 13.99, 'currency' => 'AUD']
            ]
        ]);

        // Create specific coin packages that mirror real dating app offerings
        $this->createRealisticCoinPackages();
    }

    /**
     * Create realistic coin packages based on industry standards.
     */
    private function createRealisticCoinPackages(): void
    {
        // Small starter pack
        Coin::factory()->create([
            'name' => '100 Coins',
            'description' => 'Perfect for trying out premium features',
            'tier' => 'starter',
            'amount' => 100,
            'bonus_amount' => 0,
            'price' => 4.99,
            'currency' => 'USD',
            'is_popular' => false,
            'is_featured' => false,
            'sort_order' => 101,
            'tags' => ['starter', 'trial'],
            'metadata' => [
                'recommended_for' => 'new_users',
                'features_unlocked' => ['super_likes', 'basic_boosts'],
                'estimated_usage' => '1-2 weeks casual use'
            ]
        ]);

        // Most popular pack
        Coin::factory()->create([
            'name' => '500 Coins + 100 Bonus',
            'description' => 'Our most popular coin package with bonus coins included!',
            'tier' => 'popular',
            'amount' => 500,
            'bonus_amount' => 100,
            'price' => 19.99,
            'currency' => 'USD',
            'is_popular' => true,
            'is_featured' => true,
            'sort_order' => 201,
            'tags' => ['popular', 'bonus', 'bestseller'],
            'metadata' => [
                'recommended_for' => 'active_users',
                'features_unlocked' => ['super_likes', 'boosts', 'gifts'],
                'estimated_usage' => '1-2 months active use',
                'bonus_promotion' => '20% extra coins free'
            ]
        ]);

        // Best value pack
        Coin::factory()->create([
            'name' => '1000 Coins + 300 Bonus',
            'description' => 'Best value! Get the most coins for your money with huge bonus.',
            'tier' => 'value',
            'amount' => 1000,
            'bonus_amount' => 300,
            'price' => 34.99,
            'currency' => 'USD',
            'is_popular' => true,
            'is_featured' => true,
            'sort_order' => 301,
            'tags' => ['value', 'bonus', 'recommended'],
            'metadata' => [
                'recommended_for' => 'regular_users',
                'features_unlocked' => ['all_features'],
                'estimated_usage' => '2-3 months heavy use',
                'bonus_promotion' => '30% extra coins free',
                'savings' => 'Save $15 compared to smaller packs'
            ]
        ]);

        // Premium pack
        Coin::factory()->create([
            'name' => '2500 Coins + 750 Bonus',
            'description' => 'Premium coin package for serious daters with massive bonus!',
            'tier' => 'premium',
            'amount' => 2500,
            'bonus_amount' => 750,
            'price' => 79.99,
            'currency' => 'USD',
            'is_popular' => false,
            'is_featured' => true,
            'sort_order' => 401,
            'tags' => ['premium', 'bonus', 'exclusive'],
            'metadata' => [
                'recommended_for' => 'premium_users',
                'features_unlocked' => ['all_features', 'exclusive_content'],
                'estimated_usage' => '3-6 months intensive use',
                'bonus_promotion' => '30% extra coins free',
                'savings' => 'Save $40 compared to smaller packs',
                'exclusive_perks' => ['priority_support', 'early_feature_access']
            ]
        ]);

        // Elite VIP pack
        Coin::factory()->create([
            'name' => '5000 Coins + 2000 Bonus',
            'description' => 'Ultimate VIP coin package with massive bonus for the most dedicated users!',
            'tier' => 'elite',
            'amount' => 5000,
            'bonus_amount' => 2000,
            'price' => 149.99,
            'currency' => 'USD',
            'is_popular' => false,
            'is_featured' => true,
            'sort_order' => 501,
            'tags' => ['elite', 'vip', 'bonus', 'ultimate'],
            'metadata' => [
                'recommended_for' => 'vip_users',
                'features_unlocked' => ['everything', 'vip_exclusive'],
                'estimated_usage' => '6+ months unlimited use',
                'bonus_promotion' => '40% extra coins free',
                'savings' => 'Save $100 compared to smaller packs',
                'vip_perks' => [
                    'dedicated_support',
                    'exclusive_events',
                    'profile_verification_priority',
                    'custom_profile_themes'
                ]
            ]
        ]);

        // Limited time holiday special
        Coin::factory()->create([
            'name' => 'Holiday Special - 1500 Coins + 600 Bonus',
            'description' => 'Limited time holiday offer! Extra bonus coins and special pricing.',
            'tier' => 'value',
            'amount' => 1500,
            'bonus_amount' => 600,
            'price' => 39.99,
            'sale_price' => 29.99,
            'currency' => 'USD',
            'is_promotional' => true,
            'promotion_starts_at' => now()->subDays(5),
            'promotion_ends_at' => now()->addDays(25),
            'promotion_discount_percentage' => 25.00,
            'is_popular' => true,
            'is_featured' => true,
            'sort_order' => 51,
            'tags' => ['holiday', 'limited_time', 'special_offer', 'bonus'],
            'metadata' => [
                'promotion_type' => 'seasonal',
                'original_bonus' => 450,
                'extra_holiday_bonus' => 150,
                'recommended_for' => 'all_users',
                'urgency_message' => 'Limited time - ends soon!',
                'countdown_timer' => true
            ]
        ]);
    }
}