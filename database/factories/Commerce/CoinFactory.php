<?php

namespace Database\Factories\Commerce;

use App\Models\Commerce\Coin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commerce\Coin>
 */
class CoinFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Coin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tier = fake()->randomElement([
            Coin::TIER_STARTER,
            Coin::TIER_POPULAR, 
            Coin::TIER_VALUE,
            Coin::TIER_PREMIUM,
            Coin::TIER_ELITE
        ]);

        // Base coin amounts by tier
        $baseCoinAmounts = [
            Coin::TIER_STARTER => fake()->numberBetween(50, 150),
            Coin::TIER_POPULAR => fake()->numberBetween(300, 700),
            Coin::TIER_VALUE => fake()->numberBetween(800, 1200),
            Coin::TIER_PREMIUM => fake()->numberBetween(2000, 3000),
            Coin::TIER_ELITE => fake()->numberBetween(4500, 6000),
        ];

        // Bonus percentages by tier
        $bonusPercentages = [
            Coin::TIER_STARTER => 0,
            Coin::TIER_POPULAR => fake()->randomFloat(1, 10, 20),
            Coin::TIER_VALUE => fake()->randomFloat(1, 20, 30),
            Coin::TIER_PREMIUM => fake()->randomFloat(1, 30, 40),
            Coin::TIER_ELITE => fake()->randomFloat(1, 45, 55),
        ];

        // Pricing by tier
        $basePrices = [
            Coin::TIER_STARTER => fake()->randomFloat(2, 2.99, 7.99),
            Coin::TIER_POPULAR => fake()->randomFloat(2, 15.99, 25.99),
            Coin::TIER_VALUE => fake()->randomFloat(2, 29.99, 39.99),
            Coin::TIER_PREMIUM => fake()->randomFloat(2, 69.99, 89.99),
            Coin::TIER_ELITE => fake()->randomFloat(2, 95.99, 149.99),
        ];

        $coins = $baseCoinAmounts[$tier];
        $bonusPercentage = $bonusPercentages[$tier];
        $bonusCoins = intval($coins * ($bonusPercentage / 100));
        $totalCoins = $coins + $bonusCoins;
        $price = $basePrices[$tier];

        $tierNames = [
            Coin::TIER_STARTER => 'Starter Pack',
            Coin::TIER_POPULAR => fake()->randomElement(['Popular Pack', 'Best Value Pack', 'Favorite Pack']),
            Coin::TIER_VALUE => fake()->randomElement(['Value Pack', 'Great Deal Pack', 'Smart Pack']),
            Coin::TIER_PREMIUM => fake()->randomElement(['Premium Pack', 'Power Pack', 'Pro Pack']),
            Coin::TIER_ELITE => fake()->randomElement(['Elite Pack', 'Ultimate Pack', 'VIP Pack', 'Mega Pack']),
        ];

        $name = $tierNames[$tier];

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name . '-' . $tier),
            'tier' => $tier,
            'coins' => $coins,
            'bonus_coins' => $bonusCoins,
            'total_coins' => $totalCoins,
            'bonus_percentage' => $bonusPercentage,
            'price' => $price,
            'currency' => 'USD',
            'pricing_tiers' => [
                [
                    'region' => 'US',
                    'currency' => 'USD',
                    'price' => $price,
                ],
                [
                    'region' => 'EU',
                    'currency' => 'EUR',
                    'price' => round($price * 0.85, 2),
                ],
                [
                    'region' => 'UK',
                    'currency' => 'GBP',
                    'price' => round($price * 0.75, 2),
                ],
            ],
            'description' => "Get {$totalCoins} coins" . ($bonusCoins > 0 ? " ({$coins} base + {$bonusCoins} bonus)" : '') . " for {$price}",
            'is_featured' => $tier === Coin::TIER_ELITE ? fake()->boolean(80) : fake()->boolean(20),
            'is_popular' => $tier === Coin::TIER_POPULAR ? fake()->boolean(90) : fake()->boolean(10),
            'is_active' => fake()->boolean(95),
            'sort_order' => array_search($tier, [
                Coin::TIER_STARTER,
                Coin::TIER_POPULAR, 
                Coin::TIER_VALUE,
                Coin::TIER_PREMIUM,
                Coin::TIER_ELITE
            ]) + 1,
            'purchase_count' => fake()->numberBetween(0, 10000),
            'popularity_score' => fake()->numberBetween(0, 5000),
            'metadata' => [
                'created_by' => 'system',
                'tier_benefits' => $this->getTierBenefits($tier),
                'promotional' => fake()->boolean(30),
                'seasonal' => fake()->boolean(15),
            ],
        ];
    }

    /**
     * Get tier-specific benefits
     */
    private function getTierBenefits(string $tier): array
    {
        return match($tier) {
            Coin::TIER_STARTER => [
                'Perfect for new users',
                'Try premium features',
            ],
            Coin::TIER_POPULAR => [
                'Most chosen by users',
                'Great value for money',
                'Best for regular users',
            ],
            Coin::TIER_VALUE => [
                'Maximum savings',
                'Bulk discount included',
                'Perfect for active users',
            ],
            Coin::TIER_PREMIUM => [
                'Premium user favorite',
                'Substantial coin boost',
                'Best for power users',
            ],
            Coin::TIER_ELITE => [
                'Ultimate coin package',
                'Maximum bonus coins',
                'VIP user exclusive',
                'Best value per coin',
            ],
            default => [],
        };
    }

    /**
     * Starter tier package state
     */
    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Coin::TIER_STARTER,
            'coins' => 100,
            'bonus_coins' => 0,
            'total_coins' => 100,
            'bonus_percentage' => 0,
            'price' => 4.99,
            'is_featured' => false,
            'is_popular' => false,
            'sort_order' => 1,
        ]);
    }

    /**
     * Popular tier package state
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Coin::TIER_POPULAR,
            'coins' => 500,
            'bonus_coins' => 75,
            'total_coins' => 575,
            'bonus_percentage' => 15,
            'price' => 19.99,
            'is_featured' => false,
            'is_popular' => true,
            'sort_order' => 2,
            'purchase_count' => fake()->numberBetween(5000, 15000),
        ]);
    }

    /**
     * Value tier package state
     */
    public function value(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Coin::TIER_VALUE,
            'coins' => 1000,
            'bonus_coins' => 250,
            'total_coins' => 1250,
            'bonus_percentage' => 25,
            'price' => 34.99,
            'is_featured' => false,
            'is_popular' => false,
            'sort_order' => 3,
            'purchase_count' => fake()->numberBetween(2000, 8000),
        ]);
    }

    /**
     * Premium tier package state
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Coin::TIER_PREMIUM,
            'coins' => 2500,
            'bonus_coins' => 875,
            'total_coins' => 3375,
            'bonus_percentage' => 35,
            'price' => 74.99,
            'is_featured' => false,
            'is_popular' => false,
            'sort_order' => 4,
            'purchase_count' => fake()->numberBetween(1000, 5000),
        ]);
    }

    /**
     * Elite tier package state
     */
    public function elite(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Coin::TIER_ELITE,
            'coins' => 5000,
            'bonus_coins' => 2500,
            'total_coins' => 7500,
            'bonus_percentage' => 50,
            'price' => 99.99,
            'is_featured' => true,
            'is_popular' => false,
            'sort_order' => 5,
            'purchase_count' => fake()->numberBetween(500, 2000),
            'popularity_score' => fake()->numberBetween(3000, 5000),
        ]);
    }

    /**
     * Featured package state
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
            'popularity_score' => fake()->numberBetween(2000, 5000),
            'purchase_count' => fake()->numberBetween(1000, 10000),
        ]);
    }

    /**
     * Inactive package state
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'deactivated_at' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d H:i:s'),
                'deactivation_reason' => fake()->randomElement([
                    'Low sales performance',
                    'Seasonal package ended',
                    'Replaced by new package',
                    'Pricing restructure',
                ]),
            ]),
        ]);
    }

    /**
     * High popularity state
     */
    public function highPopularity(): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_count' => fake()->numberBetween(8000, 20000),
            'popularity_score' => fake()->numberBetween(4000, 5000),
            'is_popular' => fake()->boolean(70),
            'is_featured' => fake()->boolean(50),
        ]);
    }

    /**
     * Promotional package state
     */
    public function promotional(): static
    {
        $originalPrice = fake()->randomFloat(2, 20, 100);
        $discountPrice = $originalPrice * fake()->randomFloat(2, 0.6, 0.85);

        return $this->state(fn (array $attributes) => [
            'price' => $discountPrice,
            'is_featured' => true,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'promotional' => true,
                'original_price' => $originalPrice,
                'discount_percentage' => round(((($originalPrice - $discountPrice) / $originalPrice) * 100), 1),
                'promotion_starts' => fake()->dateTimeBetween('-1 week', 'now')->format('Y-m-d H:i:s'),
                'promotion_ends' => fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d H:i:s'),
                'promotion_type' => fake()->randomElement([
                    'Flash Sale',
                    'Weekend Special',
                    'Holiday Promotion',
                    'Limited Time Offer',
                ]),
            ]),
        ]);
    }

    /**
     * Regional pricing variant
     */
    public function withRegionalPricing(): static
    {
        return $this->state(function (array $attributes) {
            $basePrice = $attributes['price'] ?? 19.99;
            
            return [
                'pricing_tiers' => [
                    [
                        'region' => 'US',
                        'currency' => 'USD',
                        'price' => $basePrice,
                    ],
                    [
                        'region' => 'EU',
                        'currency' => 'EUR',
                        'price' => round($basePrice * 0.85, 2),
                    ],
                    [
                        'region' => 'UK',
                        'currency' => 'GBP',
                        'price' => round($basePrice * 0.75, 2),
                    ],
                    [
                        'region' => 'CA',
                        'currency' => 'CAD',
                        'price' => round($basePrice * 1.25, 2),
                    ],
                    [
                        'region' => 'AU',
                        'currency' => 'AUD',
                        'price' => round($basePrice * 1.35, 2),
                    ],
                    [
                        'region' => 'JP',
                        'currency' => 'JPY',
                        'price' => round($basePrice * 110, 0),
                    ],
                ],
            ];
        });
    }

    /**
     * Low performance package
     */
    public function lowPerformance(): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_count' => fake()->numberBetween(0, 100),
            'popularity_score' => fake()->numberBetween(0, 500),
            'is_featured' => false,
            'is_popular' => false,
        ]);
    }
}