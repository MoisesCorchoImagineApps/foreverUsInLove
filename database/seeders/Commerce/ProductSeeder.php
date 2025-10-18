<?php

namespace Database\Seeders\Commerce;

use App\Models\Commerce\Coin;
use App\Models\Commerce\Plan;
use App\Models\Commerce\Product;
use Database\Factories\Models\Commerce\ProductFactory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating products from existing coins and plans...');

        // Create products for all existing coins
        $this->createCoinProducts();

        // Create products for all existing plans
        $this->createPlanProducts();

        // Create additional feature products (boosts, gifts, etc.)
        $this->createFeatureProducts();

        // Create some inactive/draft products for testing
        $this->createTestProducts();

        $this->command->info('✅ Product seeding completed!');
    }

    /**
     * Create product entries for all existing coins.
     */
    private function createCoinProducts(): void
    {
        $coins = Coin::all();
        
        foreach ($coins as $coin) {
            Product::factory()->coinProduct()->create([
                'productable_type' => 'App\Models\Commerce\Coin',
                'productable_id' => $coin->id,
                'name' => $coin->name,
                'description' => $coin->description,
                'price' => $coin->price,
                'sale_price' => $coin->sale_price,
                'currency' => $coin->currency,
                'status' => $coin->status === 'active' ? 'active' : 'inactive',
                'visibility' => $coin->visibility,
                'is_featured' => $coin->is_featured,
                'sort_order' => $coin->sort_order,
                'sku' => $coin->sku,
                'tags' => array_merge(
                    ['coins', $coin->tier],
                    $coin->is_promotional ? ['promotional', 'limited_time'] : [],
                    $coin->is_popular ? ['popular'] : [],
                    $coin->is_featured ? ['featured'] : []
                ),
                'attributes' => [
                    'category' => 'coins',
                    'tier' => $coin->tier,
                    'coin_details' => [
                        'base_amount' => $coin->amount,
                        'bonus_amount' => $coin->bonus_amount,
                        'total_amount' => $coin->amount + $coin->bonus_amount,
                        'value_per_coin' => round($coin->price / ($coin->amount + $coin->bonus_amount), 4)
                    ],
                    'promotional' => $coin->is_promotional ? [
                        'is_promotional' => true,
                        'promotion_starts_at' => $coin->promotion_starts_at?->format('Y-m-d H:i:s'),
                        'promotion_ends_at' => $coin->promotion_ends_at?->format('Y-m-d H:i:s'),
                        'discount_percentage' => $coin->promotion_discount_percentage
                    ] : null,
                    'target_audience' => match($coin->tier) {
                        'starter' => 'new_users',
                        'popular' => 'active_users',
                        'value' => 'regular_users',
                        'premium' => 'premium_users',
                        'elite' => 'vip_users',
                        default => 'all_users'
                    }
                ],
                'metadata' => array_merge($coin->metadata ?? [], [
                    'source_model' => 'coin',
                    'original_coin_data' => [
                        'tier' => $coin->tier,
                        'amount' => $coin->amount,
                        'bonus_amount' => $coin->bonus_amount
                    ]
                ])
            ]);
        }

        $this->command->info("   ✓ Created products for {$coins->count()} coins");
    }

    /**
     * Create product entries for all existing plans.
     */
    private function createPlanProducts(): void
    {
        $plans = Plan::all();
        
        foreach ($plans as $plan) {
            // Create products for each billing cycle
            $pricing = $plan->pricing ?? [];
            
            foreach ($pricing as $cycle => $priceData) {
                $cyclePrice = $priceData['price'] ?? 0;
                $setupFee = $priceData['setup_fee'] ?? 0;
                $savings = $priceData['savings'] ?? 0;
                
                Product::factory()->subscriptionProduct()->create([
                    'productable_type' => 'App\Models\Commerce\Plan',
                    'productable_id' => $plan->id,
                    'name' => "{$plan->name} ({$cycle})",
                    'description' => $plan->description,
                    'short_description' => $plan->short_description,
                    'price' => $cyclePrice + $setupFee,
                    'sale_price' => $savings > 0 ? ($cyclePrice + $setupFee - $savings) : null,
                    'currency' => $plan->currency,
                    'is_recurring' => true,
                    'recurring_interval' => $cycle === 'quarterly' ? 'monthly' : $cycle,
                    'recurring_interval_count' => $cycle === 'quarterly' ? 3 : 1,
                    'trial_period_days' => $plan->trial_period_days,
                    'status' => $plan->status === 'active' ? 'active' : 'inactive',
                    'visibility' => match($plan->availability) {
                        'public' => 'public',
                        'private' => 'private',
                        'invite_only' => 'members_only',
                        'grandfathered' => 'hidden',
                        default => 'public'
                    },
                    'is_featured' => $plan->is_popular || $plan->is_recommended,
                    'sort_order' => $plan->sort_order + ($cycle === 'monthly' ? 0 : ($cycle === 'quarterly' ? 1 : 2)),
                    'sku' => $plan->slug . '-' . $cycle,
                    'slug' => $plan->slug . '-' . $cycle,
                    'tags' => array_merge(
                        ['subscription', $plan->tier, $cycle],
                        $plan->is_popular ? ['popular'] : [],
                        $plan->is_recommended ? ['recommended'] : [],
                        $plan->is_promotional ? ['promotional'] : [],
                        $savings > 0 ? ['savings'] : []
                    ),
                    'attributes' => [
                        'category' => 'subscription',
                        'tier' => $plan->tier,
                        'billing_cycle' => $cycle,
                        'plan_details' => [
                            'features' => $plan->features ?? [],
                            'limits' => $plan->limits ?? [],
                            'billing_interval' => $plan->billing_interval,
                            'setup_fee' => $setupFee,
                            'savings_amount' => $savings,
                            'trial_period' => $plan->trial_period_days
                        ],
                        'enterprise' => $plan->requires_approval ? [
                            'requires_approval' => true,
                            'custom_contract' => $plan->custom_contract,
                            'enterprise_features' => $plan->enterprise_features ?? []
                        ] : null,
                        'target_audience' => match($plan->tier) {
                            'basic' => 'new_users',
                            'premium' => 'active_users',
                            'vip' => 'premium_users',
                            'elite' => 'vip_users',
                            default => 'all_users'
                        }
                    ],
                    'metadata' => array_merge($plan->metadata ?? [], [
                        'source_model' => 'plan',
                        'original_plan_data' => [
                            'tier' => $plan->tier,
                            'billing_cycle' => $cycle,
                            'full_pricing' => $pricing
                        ],
                        'promotional' => $plan->is_promotional ? [
                            'promotion_starts_at' => $plan->promotion_starts_at?->format('Y-m-d H:i:s'),
                            'promotion_ends_at' => $plan->promotion_ends_at?->format('Y-m-d H:i:s'),
                            'promotion_details' => $plan->promotion_details
                        ] : null
                    ])
                ]);
            }
        }

        $this->command->info("   ✓ Created products for {$plans->count()} plans");
    }

    /**
     * Create products for additional features like boosts, gifts, etc.
     */
    private function createFeatureProducts(): void
    {
        // Create boost products
        $boostProducts = [
            [
                'name' => 'Profile Boost (30 min)',
                'description' => 'Boost your profile visibility for 30 minutes and get more matches!',
                'price' => 2.99,
                'type' => 'profile_boost',
                'duration' => 30
            ],
            [
                'name' => 'Super Boost (60 min)',
                'description' => 'Super boost your profile for 60 minutes with 5x visibility!',
                'price' => 4.99,
                'type' => 'super_boost',
                'duration' => 60
            ],
            [
                'name' => 'Turbo Boost (120 min)',
                'description' => 'Maximum visibility with turbo boost for 2 hours!',
                'price' => 9.99,
                'type' => 'turbo_boost',
                'duration' => 120
            ]
        ];

        foreach ($boostProducts as $boostData) {
            Product::factory()->boostProduct()->create([
                'productable_type' => 'App\Models\Features\Boost',
                'productable_id' => fake()->numberBetween(1, 50),
                'name' => $boostData['name'],
                'description' => $boostData['description'],
                'price' => $boostData['price'],
                'attributes' => [
                    'category' => 'boosts',
                    'boost_details' => [
                        'type' => $boostData['type'],
                        'duration_minutes' => $boostData['duration'],
                        'visibility_multiplier' => match($boostData['type']) {
                            'profile_boost' => 3,
                            'super_boost' => 5,
                            'turbo_boost' => 10
                        }
                    ]
                ]
            ]);
        }

        // Create gift products
        $giftProducts = [
            [
                'name' => 'Virtual Rose',
                'description' => 'Send a beautiful virtual rose to someone special',
                'price' => 0.99,
                'type' => 'virtual_rose'
            ],
            [
                'name' => 'Digital Chocolate',
                'description' => 'Sweet digital chocolate to show you care',
                'price' => 1.99,
                'type' => 'digital_chocolate'
            ],
            [
                'name' => 'Love Letter',
                'description' => 'Express your feelings with a romantic love letter',
                'price' => 2.99,
                'type' => 'love_letter'
            ],
            [
                'name' => 'Premium Gift Box',
                'description' => 'Luxury digital gift box with special animations',
                'price' => 4.99,
                'type' => 'premium_gift'
            ],
            [
                'name' => 'Exclusive Diamond Ring',
                'description' => 'Ultra-rare exclusive diamond ring - show ultimate interest',
                'price' => 9.99,
                'type' => 'exclusive_gift'
            ]
        ];

        foreach ($giftProducts as $giftData) {
            Product::factory()->giftProduct()->create([
                'productable_type' => 'App\Models\Features\Gift',
                'productable_id' => fake()->numberBetween(1, 100),
                'name' => $giftData['name'],
                'description' => $giftData['description'],
                'price' => $giftData['price'],
                'attributes' => [
                    'category' => 'gifts',
                    'gift_details' => [
                        'type' => $giftData['type'],
                        'rarity' => match($giftData['type']) {
                            'virtual_rose' => 'common',
                            'digital_chocolate', 'love_letter' => 'uncommon',
                            'premium_gift' => 'rare',
                            'exclusive_gift' => 'legendary'
                        }
                    ]
                ]
            ]);
        }

        $this->command->info("   ✓ Created feature products (boosts and gifts)");
    }

    /**
     * Create test products in various states for development.
     */
    private function createTestProducts(): void
    {
        // Create some inactive products
        Product::factory()->inactive()->count(5)->create([
            'productable_type' => 'App\Models\Commerce\Coin',
            'productable_id' => Coin::factory()->create(['status' => 'inactive'])->id
        ]);

        // Create some featured products
        Product::factory()->featured()->count(3)->create([
            'productable_type' => 'App\Models\Commerce\Plan',
            'productable_id' => Plan::factory()->create(['is_popular' => true])->id
        ]);

        // Create limited time products
        Product::factory()->limitedTime()->count(2)->create([
            'productable_type' => 'App\Models\Commerce\Coin',
            'productable_id' => Coin::factory()->promotional()->create()->id
        ]);

        $this->command->info("   ✓ Created test products for development");
    }
}