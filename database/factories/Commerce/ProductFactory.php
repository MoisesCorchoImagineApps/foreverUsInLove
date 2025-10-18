<?php

namespace Database\Factories\Models\Commerce;

use App\Models\Commerce\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commerce\Product>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $basePrice = $this->faker->randomFloat(2, 0.99, 999.99);
        $salePrice = $this->faker->optional(0.3)->randomFloat(2, $basePrice * 0.5, $basePrice * 0.9);
        
        return [
            'productable_type' => $this->faker->randomElement([
                'App\Models\Commerce\Coin',
                'App\Models\Commerce\Plan',
                'App\Models\Features\Boost',
                'App\Models\Features\Gift',
                'App\Models\Features\Premium'
            ]),
            'productable_id' => $this->faker->numberBetween(1, 100),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(3),
            'short_description' => $this->faker->sentence(),
            'sku' => strtoupper($this->faker->bothify('PRD-###-???')),
            'price' => $basePrice,
            'sale_price' => $salePrice,
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'CAD', 'AUD']),
            'is_digital' => $this->faker->boolean(85), // Most products are digital
            'is_recurring' => $this->faker->boolean(40),
            'recurring_interval' => $this->faker->optional(0.4)->randomElement(['daily', 'weekly', 'monthly', 'quarterly', 'yearly']),
            'recurring_interval_count' => $this->faker->optional(0.4)->numberBetween(1, 12),
            'trial_period_days' => $this->faker->optional(0.2)->numberBetween(3, 30),
            'inventory_tracking' => $this->faker->boolean(20), // Most digital products don't need tracking
            'stock_quantity' => $this->faker->optional(0.2)->numberBetween(0, 1000),
            'low_stock_threshold' => $this->faker->optional(0.2)->numberBetween(5, 50),
            'allow_backorder' => $this->faker->boolean(30),
            'weight' => $this->faker->optional(0.15)->randomFloat(2, 0.1, 10.0), // Rarely used for digital
            'dimensions' => $this->faker->optional(0.15)->passthrough([
                'length' => $this->faker->randomFloat(2, 1, 50),
                'width' => $this->faker->randomFloat(2, 1, 50),
                'height' => $this->faker->randomFloat(2, 1, 50),
                'unit' => $this->faker->randomElement(['cm', 'in'])
            ]),
            'status' => $this->faker->randomElement(['active', 'inactive', 'draft', 'archived']),
            'visibility' => $this->faker->randomElement(['public', 'private', 'hidden', 'members_only']),
            'featured' => $this->faker->boolean(15),
            'sort_order' => $this->faker->numberBetween(0, 1000),
            'seo_title' => $this->faker->optional(0.6)->sentence(),
            'seo_description' => $this->faker->optional(0.6)->paragraph(),
            'seo_keywords' => $this->faker->optional(0.5)->words(8, true),
            'images' => $this->faker->optional(0.7)->passthrough([
                'primary' => $this->faker->imageUrl(800, 600, 'business'),
                'gallery' => array_map(fn() => $this->faker->imageUrl(600, 400, 'business'), range(1, $this->faker->numberBetween(0, 5))),
                'thumbnail' => $this->faker->imageUrl(200, 200, 'business')
            ]),
            'attributes' => [
                'category' => $this->faker->randomElement(['subscription', 'coins', 'boosts', 'gifts', 'features']),
                'tier' => $this->faker->randomElement(['basic', 'premium', 'vip', 'elite']),
                'target_audience' => $this->faker->randomElement(['new_users', 'active_users', 'premium_users', 'all_users']),
                'feature_flags' => [
                    'instant_delivery' => $this->faker->boolean(80),
                    'gift_eligible' => $this->faker->boolean(60),
                    'bulk_discount' => $this->faker->boolean(30),
                    'subscription_discount' => $this->faker->boolean(45)
                ],
                'analytics' => [
                    'conversion_tracking' => $this->faker->boolean(70),
                    'revenue_tracking' => $this->faker->boolean(90),
                    'engagement_tracking' => $this->faker->boolean(50)
                ],
                'restrictions' => [
                    'max_quantity_per_user' => $this->faker->optional(0.3)->numberBetween(1, 10),
                    'geographic_restrictions' => $this->faker->optional(0.2)->randomElements(['US', 'EU', 'UK', 'CA', 'AU'], $this->faker->numberBetween(1, 3)),
                    'age_restrictions' => $this->faker->optional(0.4)->numberBetween(13, 21),
                    'subscription_required' => $this->faker->boolean(25)
                ]
            ],
            'metadata' => [
                'marketing' => [
                    'launch_date' => $this->faker->optional(0.5)->dateTimeBetween('-1 year', '+3 months')->format('Y-m-d'),
                    'promotion_eligible' => $this->faker->boolean(70),
                    'cross_sell_products' => $this->faker->optional(0.4)->randomElements(['coins', 'boosts', 'subscriptions'], $this->faker->numberBetween(1, 3)),
                    'upsell_products' => $this->faker->optional(0.3)->randomElements(['premium_plan', 'vip_coins', 'elite_boost'], $this->faker->numberBetween(1, 2)),
                    'seasonal_availability' => $this->faker->optional(0.2)->randomElement(['holiday', 'summer', 'back_to_school', 'valentines'])
                ],
                'analytics' => [
                    'total_sales' => $this->faker->numberBetween(0, 10000),
                    'total_revenue' => $this->faker->randomFloat(2, 0, 500000),
                    'avg_rating' => $this->faker->optional(0.6)->randomFloat(1, 3.5, 5.0),
                    'review_count' => $this->faker->optional(0.6)->numberBetween(0, 1000),
                    'conversion_rate' => $this->faker->optional(0.7)->randomFloat(2, 0.5, 15.0)
                ],
                'operational' => [
                    'fulfillment_method' => $this->faker->randomElement(['instant', 'manual', 'batch', 'scheduled']),
                    'delivery_time' => $this->faker->randomElement(['instant', '1-24h', '1-3d', '3-7d']),
                    'support_level' => $this->faker->randomElement(['basic', 'standard', 'premium', 'white_glove']),
                    'refund_policy' => $this->faker->randomElement(['no_refund', '7_day', '14_day', '30_day', 'custom'])
                ]
            ],
            'tags' => $this->faker->optional(0.6)->randomElements([
                'popular', 'trending', 'new', 'bestseller', 'limited_time', 'exclusive', 
                'premium', 'value', 'recommended', 'seasonal', 'gift', 'bundle'
            ], $this->faker->numberBetween(1, 4)),
            'is_active' => $this->faker->boolean(85),
            'created_at' => $this->faker->dateTimeBetween('-2 years', '-1 month'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * State for coin products.
     */
    public function coinProduct(): static
    {
        return $this->state(function (array $attributes) {
            $coinAmount = $this->faker->randomElement([100, 500, 1000, 2500, 5000, 10000]);
            $bonusAmount = $this->faker->numberBetween(0, $coinAmount * 0.3);
            
            return [
                'productable_type' => 'App\Models\Commerce\Coin',
                'productable_id' => $this->faker->numberBetween(1, 20),
                'name' => "{$coinAmount} Coins" . ($bonusAmount > 0 ? " + {$bonusAmount} Bonus" : ""),
                'description' => "Get {$coinAmount} coins for your dating adventures" . ($bonusAmount > 0 ? " plus {$bonusAmount} bonus coins!" : "."),
                'short_description' => "{$coinAmount} coins package" . ($bonusAmount > 0 ? " with bonus" : ""),
                'sku' => "COIN-{$coinAmount}" . ($bonusAmount > 0 ? "-BONUS" : ""),
                'price' => $coinAmount / 100, // $1 per 100 coins base rate
                'is_digital' => true,
                'is_recurring' => false,
                'inventory_tracking' => false,
                'status' => 'active',
                'visibility' => 'public',
                'attributes' => [
                    'category' => 'coins',
                    'tier' => match(true) {
                        $coinAmount <= 500 => 'basic',
                        $coinAmount <= 2500 => 'premium',
                        $coinAmount <= 5000 => 'vip',
                        default => 'elite'
                    },
                    'coin_details' => [
                        'base_amount' => $coinAmount,
                        'bonus_amount' => $bonusAmount,
                        'total_amount' => $coinAmount + $bonusAmount,
                        'value_per_coin' => round(($attributes['price'] ?? 0) / ($coinAmount + $bonusAmount), 4)
                    ],
                    'target_audience' => 'all_users',
                    'feature_flags' => [
                        'instant_delivery' => true,
                        'gift_eligible' => true,
                        'bulk_discount' => $coinAmount >= 2500,
                        'subscription_discount' => false
                    ]
                ],
                'tags' => array_filter([
                    'coins',
                    $bonusAmount > 0 ? 'bonus' : null,
                    $coinAmount >= 5000 ? 'bestseller' : null,
                    $coinAmount >= 10000 ? 'premium' : null
                ])
            ];
        });
    }

    /**
     * State for subscription plan products.
     */
    public function subscriptionProduct(): static
    {
        return $this->state(function (array $attributes) {
            $planType = $this->faker->randomElement(['basic', 'premium', 'vip', 'elite']);
            $interval = $this->faker->randomElement(['monthly', 'quarterly', 'yearly']);
            
            $basePrices = [
                'basic' => ['monthly' => 9.99, 'quarterly' => 24.99, 'yearly' => 89.99],
                'premium' => ['monthly' => 19.99, 'quarterly' => 54.99, 'yearly' => 199.99],
                'vip' => ['monthly' => 39.99, 'quarterly' => 109.99, 'yearly' => 399.99],
                'elite' => ['monthly' => 79.99, 'quarterly' => 219.99, 'yearly' => 799.99]
            ];
            
            return [
                'productable_type' => 'App\Models\Commerce\Plan',
                'productable_id' => $this->faker->numberBetween(1, 10),
                'name' => ucfirst($planType) . " Plan ({$interval})",
                'description' => "Unlock premium features with our {$planType} {$interval} subscription plan.",
                'short_description' => "{$planType} subscription - {$interval} billing",
                'sku' => strtoupper("PLAN-{$planType}-{$interval}"),
                'price' => $basePrices[$planType][$interval],
                'sale_price' => $interval === 'yearly' ? $basePrices[$planType][$interval] * 0.8 : null,
                'is_digital' => true,
                'is_recurring' => true,
                'recurring_interval' => $interval === 'quarterly' ? 'monthly' : $interval,
                'recurring_interval_count' => $interval === 'quarterly' ? 3 : 1,
                'trial_period_days' => $planType === 'basic' ? 7 : ($planType === 'premium' ? 14 : 30),
                'inventory_tracking' => false,
                'status' => 'active',
                'visibility' => 'public',
                'featured' => $planType === 'premium',
                'attributes' => [
                    'category' => 'subscription',
                    'tier' => $planType,
                    'plan_details' => [
                        'billing_cycle' => $interval,
                        'features' => match($planType) {
                            'basic' => ['unlimited_likes', 'basic_filters', 'see_who_liked'],
                            'premium' => ['unlimited_likes', 'advanced_filters', 'see_who_liked', 'boost_monthly', 'super_likes'],
                            'vip' => ['unlimited_likes', 'premium_filters', 'see_who_liked', 'boost_weekly', 'super_likes', 'message_read_receipts'],
                            'elite' => ['unlimited_everything', 'exclusive_features', 'priority_support', 'personal_matchmaker']
                        },
                        'limits' => match($planType) {
                            'basic' => ['super_likes_per_day' => 5, 'boosts_per_month' => 0],
                            'premium' => ['super_likes_per_day' => 10, 'boosts_per_month' => 1],
                            'vip' => ['super_likes_per_day' => 20, 'boosts_per_month' => 4],
                            'elite' => ['super_likes_per_day' => -1, 'boosts_per_month' => -1] // unlimited
                        }
                    ],
                    'target_audience' => match($planType) {
                        'basic' => 'new_users',
                        'premium' => 'active_users',
                        'vip' => 'premium_users',
                        'elite' => 'premium_users'
                    },
                    'feature_flags' => [
                        'instant_delivery' => true,
                        'gift_eligible' => $planType !== 'basic',
                        'bulk_discount' => false,
                        'subscription_discount' => $interval === 'yearly'
                    ]
                ],
                'tags' => array_filter([
                    'subscription',
                    $planType,
                    $interval === 'yearly' ? 'annual_discount' : null,
                    $planType === 'premium' ? 'popular' : null,
                    $planType === 'elite' ? 'exclusive' : null
                ])
            ];
        });
    }

    /**
     * State for boost products.
     */
    public function boostProduct(): static
    {
        return $this->state(function (array $attributes) {
            $boostType = $this->faker->randomElement(['profile_boost', 'super_boost', 'turbo_boost']);
            $duration = $this->faker->randomElement([30, 60, 120, 180]); // minutes
            
            $prices = [
                'profile_boost' => 2.99,
                'super_boost' => 4.99,
                'turbo_boost' => 9.99
            ];
            
            return [
                'productable_type' => 'App\Models\Features\Boost',
                'productable_id' => $this->faker->numberBetween(1, 15),
                'name' => str_replace('_', ' ', ucwords($boostType)) . " ({$duration} min)",
                'description' => "Boost your profile visibility for {$duration} minutes and get more matches!",
                'short_description' => "{$duration}-minute " . str_replace('_', ' ', $boostType),
                'sku' => strtoupper("BOOST-{$boostType}-{$duration}M"),
                'price' => $prices[$boostType],
                'is_digital' => true,
                'is_recurring' => false,
                'inventory_tracking' => false,
                'status' => 'active',
                'visibility' => 'public',
                'attributes' => [
                    'category' => 'boosts',
                    'tier' => match($boostType) {
                        'profile_boost' => 'basic',
                        'super_boost' => 'premium',
                        'turbo_boost' => 'elite'
                    },
                    'boost_details' => [
                        'type' => $boostType,
                        'duration_minutes' => $duration,
                        'visibility_multiplier' => match($boostType) {
                            'profile_boost' => 3,
                            'super_boost' => 5,
                            'turbo_boost' => 10
                        },
                        'priority_queue' => $boostType !== 'profile_boost'
                    ],
                    'target_audience' => 'active_users',
                    'feature_flags' => [
                        'instant_delivery' => true,
                        'gift_eligible' => true,
                        'bulk_discount' => true,
                        'subscription_discount' => false
                    ]
                ],
                'tags' => array_filter([
                    'boost',
                    $boostType,
                    $duration >= 120 ? 'extended' : null,
                    $boostType === 'super_boost' ? 'popular' : null
                ])
            ];
        });
    }

    /**
     * State for gift products.
     */
    public function giftProduct(): static
    {
        return $this->state(function (array $attributes) {
            $giftType = $this->faker->randomElement(['virtual_rose', 'digital_chocolate', 'love_letter', 'premium_gift', 'exclusive_gift']);
            
            $prices = [
                'virtual_rose' => 0.99,
                'digital_chocolate' => 1.99,
                'love_letter' => 2.99,
                'premium_gift' => 4.99,
                'exclusive_gift' => 9.99
            ];
            
            return [
                'productable_type' => 'App\Models\Features\Gift',
                'productable_id' => $this->faker->numberBetween(1, 25),
                'name' => str_replace('_', ' ', ucwords($giftType)),
                'description' => "Send a special " . str_replace('_', ' ', $giftType) . " to someone you're interested in!",
                'short_description' => "Digital " . str_replace('_', ' ', $giftType),
                'sku' => strtoupper("GIFT-{$giftType}"),
                'price' => $prices[$giftType],
                'is_digital' => true,
                'is_recurring' => false,
                'inventory_tracking' => false,
                'status' => 'active',
                'visibility' => 'public',
                'attributes' => [
                    'category' => 'gifts',
                    'tier' => match($giftType) {
                        'virtual_rose', 'digital_chocolate' => 'basic',
                        'love_letter', 'premium_gift' => 'premium',
                        'exclusive_gift' => 'elite'
                    },
                    'gift_details' => [
                        'type' => $giftType,
                        'animation' => $this->faker->boolean(80),
                        'sound_effect' => $this->faker->boolean(60),
                        'custom_message' => $giftType !== 'virtual_rose',
                        'rarity' => match($giftType) {
                            'virtual_rose' => 'common',
                            'digital_chocolate', 'love_letter' => 'uncommon',
                            'premium_gift' => 'rare',
                            'exclusive_gift' => 'legendary'
                        }
                    ],
                    'target_audience' => 'all_users',
                    'feature_flags' => [
                        'instant_delivery' => true,
                        'gift_eligible' => false, // Gifts can't be gifted
                        'bulk_discount' => $giftType === 'virtual_rose',
                        'subscription_discount' => false
                    ]
                ],
                'tags' => array_filter([
                    'gift',
                    $giftType,
                    in_array($giftType, ['premium_gift', 'exclusive_gift']) ? 'premium' : null,
                    $giftType === 'virtual_rose' ? 'popular' : null
                ])
            ];
        });
    }

    /**
     * State for featured/popular products.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
            'sort_order' => $this->faker->numberBetween(1, 10), // Higher priority
            'status' => 'active',
            'visibility' => 'public',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'marketing' => array_merge($attributes['metadata']['marketing'] ?? [], [
                    'promotion_eligible' => true,
                    'featured_until' => $this->faker->optional(0.3)->dateTimeBetween('now', '+3 months')->format('Y-m-d')
                ]),
                'analytics' => array_merge($attributes['metadata']['analytics'] ?? [], [
                    'total_sales' => $this->faker->numberBetween(1000, 50000),
                    'total_revenue' => $this->faker->randomFloat(2, 10000, 500000),
                    'avg_rating' => $this->faker->randomFloat(1, 4.0, 5.0),
                    'review_count' => $this->faker->numberBetween(100, 5000),
                    'conversion_rate' => $this->faker->randomFloat(2, 5.0, 25.0)
                ])
            ]),
            'tags' => array_merge($attributes['tags'] ?? [], ['featured', 'popular', 'bestseller'])
        ]);
    }

    /**
     * State for inactive/draft products.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement(['inactive', 'draft', 'archived']),
            'visibility' => $this->faker->randomElement(['private', 'hidden']),
            'is_active' => false,
            'featured' => false,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'operational' => array_merge($attributes['metadata']['operational'] ?? [], [
                    'deactivation_reason' => $this->faker->randomElement([
                        'seasonal_product',
                        'low_performance',
                        'inventory_issues',
                        'compliance_review',
                        'updating_content'
                    ]),
                    'deactivated_at' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d H:i:s')
                ])
            ])
        ]);
    }

    /**
     * State for limited-time/seasonal products.
     */
    public function limitedTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_price' => $attributes['price'] * $this->faker->randomFloat(2, 0.6, 0.9),
            'status' => 'active',
            'featured' => $this->faker->boolean(60),
            'attributes' => array_merge($attributes['attributes'] ?? [], [
                'restrictions' => array_merge($attributes['attributes']['restrictions'] ?? [], [
                    'available_until' => $this->faker->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
                    'max_quantity_per_user' => $this->faker->numberBetween(1, 5)
                ])
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'marketing' => array_merge($attributes['metadata']['marketing'] ?? [], [
                    'seasonal_availability' => $this->faker->randomElement(['holiday', 'valentines', 'summer', 'black_friday']),
                    'urgency_messaging' => true,
                    'countdown_timer' => true
                ])
            ]),
            'tags' => array_merge($attributes['tags'] ?? [], ['limited_time', 'sale', 'seasonal'])
        ]);
    }
}