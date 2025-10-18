<?php

namespace Database\Factories\Commerce;

use App\Models\Commerce\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commerce\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Plan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tier = fake()->randomElement([
            Plan::TIER_BASIC,
            Plan::TIER_PREMIUM,
            Plan::TIER_VIP,
            Plan::TIER_ELITE
        ]);

        $type = fake()->randomElement([
            Plan::TYPE_MONTHLY,
            Plan::TYPE_QUARTERLY,
            Plan::TYPE_ANNUAL
        ]);

        // Tier configurations
        $tierConfig = $this->getTierConfiguration($tier);
        
        $name = $tierConfig['name'];
        $features = $tierConfig['features'];
        $limits = $tierConfig['limits'];
        $priceMonthly = $tierConfig['price_monthly'];

        // Calculate quarterly and annual pricing with discounts
        $priceQuarterly = round($priceMonthly * 3 * 0.95, 2); // 5% discount
        $priceAnnual = round($priceMonthly * 12 * 0.85, 2); // 15% discount

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name . '-' . $type),
            'tier' => $tier,
            'type' => $type,
            'description' => $this->generateDescription($tier),
            'features' => $features,
            'limits' => $limits,
            'price_monthly' => $priceMonthly,
            'price_quarterly' => $priceQuarterly,
            'price_annual' => $priceAnnual,
            'currency' => 'USD',
            'pricing_tiers' => [
                [
                    'region' => 'US',
                    'currency' => 'USD',
                    'monthly' => $priceMonthly,
                    'quarterly' => $priceQuarterly,
                    'annual' => $priceAnnual,
                ],
                [
                    'region' => 'EU',
                    'currency' => 'EUR',
                    'monthly' => round($priceMonthly * 0.85, 2),
                    'quarterly' => round($priceQuarterly * 0.85, 2),
                    'annual' => round($priceAnnual * 0.85, 2),
                ],
            ],
            'trial_days' => $this->getTrialDays($tier),
            'is_featured' => $tier === Plan::TIER_ELITE ? fake()->boolean(80) : fake()->boolean(20),
            'is_popular' => $tier === Plan::TIER_PREMIUM ? fake()->boolean(90) : fake()->boolean(15),
            'is_active' => fake()->boolean(95),
            'sort_order' => array_search($tier, [
                Plan::TIER_BASIC,
                Plan::TIER_PREMIUM,
                Plan::TIER_VIP,
                Plan::TIER_ELITE
            ]) + 1,
            'subscriber_count' => $this->getSubscriberCount($tier),
            'metadata' => [
                'created_by' => 'system',
                'tier_benefits' => $this->getTierBenefits($tier),
                'target_audience' => $this->getTargetAudience($tier),
                'conversion_rate' => fake()->randomFloat(2, 0.05, 0.25),
                'churn_rate' => fake()->randomFloat(2, 0.02, 0.15),
            ],
        ];
    }

    /**
     * Get tier-specific configuration
     */
    private function getTierConfiguration(string $tier): array
    {
        return match($tier) {
            Plan::TIER_BASIC => [
                'name' => 'Basic',
                'price_monthly' => 0,
                'features' => [Plan::FEATURE_DAILY_LIKES, Plan::FEATURE_SUPER_LIKES],
                'limits' => Plan::LIMITS_BASIC,
            ],
            Plan::TIER_PREMIUM => [
                'name' => 'Premium',
                'price_monthly' => fake()->randomFloat(2, 25.99, 34.99),
                'features' => [
                    Plan::FEATURE_DAILY_LIKES,
                    Plan::FEATURE_SUPER_LIKES,
                    Plan::FEATURE_BOOST,
                    Plan::FEATURE_UNLIMITED_MESSAGING,
                    Plan::FEATURE_ADVANCED_FILTERS,
                    Plan::FEATURE_READ_RECEIPTS,
                    Plan::FEATURE_VIDEO_CALLS,
                ],
                'limits' => Plan::LIMITS_PREMIUM,
            ],
            Plan::TIER_VIP => [
                'name' => 'VIP',
                'price_monthly' => fake()->randomFloat(2, 45.99, 59.99),
                'features' => [
                    Plan::FEATURE_DAILY_LIKES,
                    Plan::FEATURE_SUPER_LIKES,
                    Plan::FEATURE_BOOST,
                    Plan::FEATURE_UNLIMITED_MESSAGING,
                    Plan::FEATURE_ADVANCED_FILTERS,
                    Plan::FEATURE_READ_RECEIPTS,
                    Plan::FEATURE_VIDEO_CALLS,
                    Plan::FEATURE_INCOGNITO_MODE,
                    Plan::FEATURE_TRAVEL_MODE,
                    Plan::FEATURE_PRIORITY_SUPPORT,
                ],
                'limits' => Plan::LIMITS_VIP,
            ],
            Plan::TIER_ELITE => [
                'name' => 'Elite',
                'price_monthly' => fake()->randomFloat(2, 89.99, 119.99),
                'features' => [
                    Plan::FEATURE_DAILY_LIKES,
                    Plan::FEATURE_SUPER_LIKES,
                    Plan::FEATURE_BOOST,
                    Plan::FEATURE_UNLIMITED_MESSAGING,
                    Plan::FEATURE_ADVANCED_FILTERS,
                    Plan::FEATURE_READ_RECEIPTS,
                    Plan::FEATURE_VIDEO_CALLS,
                    Plan::FEATURE_INCOGNITO_MODE,
                    Plan::FEATURE_TRAVEL_MODE,
                    Plan::FEATURE_PRIORITY_SUPPORT,
                    Plan::FEATURE_PERSONAL_MATCHMAKER,
                    Plan::FEATURE_EXCLUSIVE_EVENTS,
                ],
                'limits' => Plan::LIMITS_ELITE,
            ],
            default => [
                'name' => 'Custom',
                'price_monthly' => 19.99,
                'features' => [Plan::FEATURE_DAILY_LIKES],
                'limits' => Plan::LIMITS_BASIC,
            ],
        };
    }

    /**
     * Generate tier-specific description
     */
    private function generateDescription(string $tier): string
    {
        return match($tier) {
            Plan::TIER_BASIC => 'Perfect for new users to explore the platform with essential features.',
            Plan::TIER_PREMIUM => 'Unlock advanced features and boost your dating experience with premium tools.',
            Plan::TIER_VIP => 'Get VIP treatment with exclusive features, priority support, and advanced matching.',
            Plan::TIER_ELITE => 'The ultimate dating experience with unlimited features, personal matchmaker, and exclusive events.',
            default => 'A customized plan tailored to your specific needs.',
        };
    }

    /**
     * Get trial days by tier
     */
    private function getTrialDays(string $tier): int
    {
        return match($tier) {
            Plan::TIER_BASIC => 0,
            Plan::TIER_PREMIUM => 7,
            Plan::TIER_VIP => 14,
            Plan::TIER_ELITE => 30,
            default => 7,
        };
    }

    /**
     * Get subscriber count by tier
     */
    private function getSubscriberCount(string $tier): int
    {
        return match($tier) {
            Plan::TIER_BASIC => fake()->numberBetween(50000, 200000),
            Plan::TIER_PREMIUM => fake()->numberBetween(10000, 50000),
            Plan::TIER_VIP => fake()->numberBetween(2000, 15000),
            Plan::TIER_ELITE => fake()->numberBetween(500, 5000),
            default => fake()->numberBetween(0, 1000),
        };
    }

    /**
     * Get tier-specific benefits
     */
    private function getTierBenefits(string $tier): array
    {
        return match($tier) {
            Plan::TIER_BASIC => [
                'Free to use',
                'Basic matching',
                'Limited daily likes',
            ],
            Plan::TIER_PREMIUM => [
                'Most popular choice',
                'Advanced matching algorithms',
                'Unlimited messaging',
                'See who liked you',
                'Video chat capabilities',
            ],
            Plan::TIER_VIP => [
                'VIP badge on profile',
                'Priority in matching',
                'Incognito browsing',
                'Travel to any city',
                'Priority customer support',
            ],
            Plan::TIER_ELITE => [
                'Elite status badge',
                'Unlimited everything',
                'Personal matchmaker service',
                'Exclusive events access',
                'White-glove support',
                'Custom features',
            ],
            default => ['Basic features'],
        };
    }

    /**
     * Get target audience by tier
     */
    private function getTargetAudience(string $tier): string
    {
        return match($tier) {
            Plan::TIER_BASIC => 'New users, casual daters, price-conscious users',
            Plan::TIER_PREMIUM => 'Active daters, professionals, committed users',
            Plan::TIER_VIP => 'Serious daters, high-income users, privacy-focused',
            Plan::TIER_ELITE => 'Luxury seekers, celebrities, ultra-high-net-worth individuals',
            default => 'General users',
        };
    }

    /**
     * Basic tier plan state
     */
    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Plan::TIER_BASIC,
            'name' => 'Basic',
            'price_monthly' => 0,
            'price_quarterly' => 0,
            'price_annual' => 0,
            'trial_days' => 0,
            'is_popular' => false,
            'is_featured' => false,
            'sort_order' => 1,
        ]);
    }

    /**
     * Premium tier plan state
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Plan::TIER_PREMIUM,
            'name' => 'Premium',
            'price_monthly' => 29.99,
            'price_quarterly' => 84.99,
            'price_annual' => 299.99,
            'trial_days' => 7,
            'is_popular' => true,
            'is_featured' => false,
            'sort_order' => 2,
        ]);
    }

    /**
     * VIP tier plan state
     */
    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Plan::TIER_VIP,
            'name' => 'VIP',
            'price_monthly' => 49.99,
            'price_quarterly' => 139.99,
            'price_annual' => 499.99,
            'trial_days' => 14,
            'is_popular' => false,
            'is_featured' => true,
            'sort_order' => 3,
        ]);
    }

    /**
     * Elite tier plan state
     */
    public function elite(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => Plan::TIER_ELITE,
            'name' => 'Elite',
            'price_monthly' => 99.99,
            'price_quarterly' => 279.99,
            'price_annual' => 999.99,
            'trial_days' => 30,
            'is_popular' => false,
            'is_featured' => true,
            'sort_order' => 4,
        ]);
    }

    /**
     * Monthly billing type
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Plan::TYPE_MONTHLY,
        ]);
    }

    /**
     * Quarterly billing type
     */
    public function quarterly(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Plan::TYPE_QUARTERLY,
        ]);
    }

    /**
     * Annual billing type
     */
    public function annual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Plan::TYPE_ANNUAL,
        ]);
    }

    /**
     * Popular plan state
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_popular' => true,
            'subscriber_count' => fake()->numberBetween(20000, 80000),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'conversion_rate' => fake()->randomFloat(2, 0.15, 0.35),
                'customer_satisfaction' => fake()->randomFloat(1, 4.2, 4.8),
            ]),
        ]);
    }

    /**
     * Featured plan state
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'featured_since' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
                'featured_reason' => fake()->randomElement([
                    'Best value for money',
                    'Most comprehensive features',
                    'Highest customer satisfaction',
                    'Premium tier promotion',
                ]),
            ]),
        ]);
    }

    /**
     * Inactive plan state
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'subscriber_count' => 0,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'deactivated_at' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d H:i:s'),
                'deactivation_reason' => fake()->randomElement([
                    'Low subscription rate',
                    'Replaced by new plan',
                    'Pricing restructure',
                    'Feature consolidation',
                ]),
            ]),
        ]);
    }

    /**
     * High subscriber count state
     */
    public function highSubscribers(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscriber_count' => fake()->numberBetween(50000, 150000),
            'is_popular' => fake()->boolean(80),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'growth_rate' => fake()->randomFloat(2, 0.10, 0.50),
                'customer_lifetime_value' => fake()->numberBetween(500, 2000),
            ]),
        ]);
    }

    /**
     * Custom pricing state
     */
    public function customPricing(): static
    {
        return $this->state(function (array $attributes) {
            $monthlyPrice = fake()->randomFloat(2, 15.99, 199.99);
            
            return [
                'price_monthly' => $monthlyPrice,
                'price_quarterly' => round($monthlyPrice * 3 * fake()->randomFloat(2, 0.90, 0.98), 2),
                'price_annual' => round($monthlyPrice * 12 * fake()->randomFloat(2, 0.75, 0.90), 2),
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'pricing_strategy' => fake()->randomElement([
                        'Penetration pricing',
                        'Premium positioning',
                        'Competitive parity',
                        'Value-based pricing',
                    ]),
                    'price_testing' => fake()->boolean(40),
                ]),
            ];
        });
    }

    /**
     * Extended trial state
     */
    public function extendedTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_days' => fake()->numberBetween(14, 60),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'trial_promotion' => true,
                'trial_conversion_rate' => fake()->randomFloat(2, 0.20, 0.45),
            ]),
        ]);
    }

    /**
     * Enterprise features state
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => array_merge($attributes['features'] ?? [], [
                'api_access',
                'white_labeling',
                'custom_branding',
                'dedicated_support',
                'advanced_analytics',
                'bulk_operations',
            ]),
            'limits' => array_merge($attributes['limits'] ?? [], [
                'api_calls' => 100000,
                'team_members' => 50,
                'custom_fields' => 100,
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'enterprise_features' => true,
                'dedicated_account_manager' => true,
                'sla_guarantee' => '99.9%',
            ]),
        ]);
    }
}