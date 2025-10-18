<?php

namespace Database\Factories\Commerce;

use App\Models\Commerce\Subscription;
use App\Models\Commerce\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commerce\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement([
            Subscription::STATUS_TRIAL,
            Subscription::STATUS_ACTIVE,
            Subscription::STATUS_CANCELLED,
            Subscription::STATUS_EXPIRED,
            Subscription::STATUS_SUSPENDED,
            Subscription::STATUS_PAST_DUE
        ]);

        $billingCycle = fake()->randomElement([
            Subscription::CYCLE_MONTHLY,
            Subscription::CYCLE_QUARTERLY,
            Subscription::CYCLE_ANNUAL
        ]);

        $startedAt = fake()->dateTimeBetween('-1 year', 'now');
        $currentPeriodStart = fake()->dateTimeBetween($startedAt, 'now');
        
        // Calculate period end based on billing cycle
        $currentPeriodEnd = match($billingCycle) {
            Subscription::CYCLE_MONTHLY => (clone $currentPeriodStart)->modify('+1 month'),
            Subscription::CYCLE_QUARTERLY => (clone $currentPeriodStart)->modify('+3 months'),
            Subscription::CYCLE_ANNUAL => (clone $currentPeriodStart)->modify('+1 year'),
            default => (clone $currentPeriodStart)->modify('+1 month'),
        };

        // Price based on billing cycle
        $monthlyPrice = fake()->randomFloat(2, 19.99, 199.99);
        $price = match($billingCycle) {
            Subscription::CYCLE_MONTHLY => $monthlyPrice,
            Subscription::CYCLE_QUARTERLY => round($monthlyPrice * 3 * 0.95, 2),
            Subscription::CYCLE_ANNUAL => round($monthlyPrice * 12 * 0.85, 2),
            default => $monthlyPrice,
        };

        return [
            'subscription_number' => 'SUB-' . strtoupper(fake()->unique()->bothify('########')),
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'billing_cycle' => $billingCycle,
            'status' => $status,
            'price' => $price,
            'currency' => 'USD',
            'trial_ends_at' => $status === Subscription::STATUS_TRIAL 
                ? fake()->dateTimeBetween('now', '+30 days') 
                : null,
            'started_at' => $startedAt,
            'current_period_start' => $currentPeriodStart,
            'current_period_end' => $currentPeriodEnd,
            'ends_at' => in_array($status, [Subscription::STATUS_CANCELLED, Subscription::STATUS_EXPIRED])
                ? fake()->dateTimeBetween($currentPeriodEnd, '+1 month')
                : null,
            'cancelled_at' => $status === Subscription::STATUS_CANCELLED
                ? fake()->dateTimeBetween($startedAt, 'now')
                : null,
            'cancellation_reason' => $status === Subscription::STATUS_CANCELLED
                ? fake()->randomElement([
                    'Customer requested cancellation',
                    'Payment method failed',
                    'Found better alternative',
                    'No longer need service',
                    'Too expensive',
                    'Technical issues',
                    'Poor customer service'
                ])
                : null,
            'cancel_at_period_end' => $status === Subscription::STATUS_CANCELLED ? fake()->boolean(70) : false,
            'renewed_at' => in_array($status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
                ? fake()->dateTimeBetween($startedAt, 'now')
                : null,
            'renewal_count' => fake()->numberBetween(0, 24),
            'auto_renew' => !in_array($status, [Subscription::STATUS_CANCELLED, Subscription::STATUS_EXPIRED]),
            'payment_method_id' => fake()->randomElement(['pm_' . fake()->bothify('##################'), null]),
            'features_snapshot' => $this->generateFeaturesSnapshot(),
            'metadata' => [
                'signup_source' => fake()->randomElement(['web', 'mobile_app', 'referral', 'advertisement']),
                'promotion_code' => fake()->boolean(30) ? fake()->randomElement(['SAVE20', 'FIRST10', 'WELCOME25']) : null,
                'referrer_id' => fake()->boolean(20) ? fake()->numberBetween(1, 10000) : null,
                'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
                'browser' => fake()->randomElement(['Chrome', 'Safari', 'Firefox', 'Edge']),
                'ip_country' => fake()->countryCode(),
            ],
            'usage_stats' => $this->generateUsageStats(),
            'last_payment_at' => in_array($status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
                ? fake()->dateTimeBetween('-1 month', 'now')
                : null,
            'next_payment_at' => in_array($status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL])
                ? $currentPeriodEnd
                : null,
            'next_payment_amount' => in_array($status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL])
                ? $price
                : null,
            'created_at' => $startedAt,
            'updated_at' => fake()->dateTimeBetween($startedAt, 'now'),
        ];
    }

    /**
     * Generate features snapshot based on subscription tier
     */
    private function generateFeaturesSnapshot(): array
    {
        return fake()->randomElements([
            'unlimited_messaging',
            'advanced_filters',
            'read_receipts',
            'video_calls',
            'incognito_mode',
            'travel_mode',
            'priority_support',
            'boost_feature',
            'super_likes',
            'rewind_feature',
            'passport_feature',
            'premium_badges'
        ], fake()->numberBetween(3, 8));
    }

    /**
     * Generate usage statistics
     */
    private function generateUsageStats(): array
    {
        return [
            'daily_likes' => fake()->numberBetween(0, 100),
            'super_likes' => fake()->numberBetween(0, 10),
            'boost_used' => fake()->numberBetween(0, 5),
            'messages_sent' => fake()->numberBetween(0, 500),
            'video_calls_made' => fake()->numberBetween(0, 20),
            'profile_views' => fake()->numberBetween(0, 1000),
            'matches_made' => fake()->numberBetween(0, 50),
            'features_used' => fake()->randomElements([
                'advanced_search',
                'incognito_browsing',
                'read_receipts',
                'travel_mode'
            ], fake()->numberBetween(1, 4)),
        ];
    }

    /**
     * Trial subscription state
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Subscription::STATUS_TRIAL,
            'trial_ends_at' => fake()->dateTimeBetween('now', '+14 days'),
            'next_payment_at' => fake()->dateTimeBetween('+10 days', '+20 days'),
            'auto_renew' => true,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'trial_type' => fake()->randomElement(['standard', 'extended', 'promotional']),
                'trial_conversion_target' => fake()->boolean(60),
            ]),
        ]);
    }

    /**
     * Active subscription state
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => null,
            'last_payment_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'next_payment_at' => fake()->dateTimeBetween('now', '+1 month'),
            'auto_renew' => true,
            'renewal_count' => fake()->numberBetween(1, 24),
        ]);
    }

    /**
     * Cancelled subscription state
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'cancellation_reason' => fake()->randomElement([
                'Customer requested cancellation',
                'Payment method failed repeatedly',
                'Found competitor with better pricing',
                'No longer using the service',
                'Service did not meet expectations'
            ]),
            'cancel_at_period_end' => fake()->boolean(70),
            'auto_renew' => false,
            'ends_at' => fake()->dateTimeBetween('now', '+1 month'),
        ]);
    }

    /**
     * Expired subscription state
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Subscription::STATUS_EXPIRED,
            'ends_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'auto_renew' => false,
            'next_payment_at' => null,
            'usage_stats' => [], // Clear usage stats for expired subscriptions
        ]);
    }

    /**
     * Suspended subscription state
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Subscription::STATUS_SUSPENDED,
            'auto_renew' => false,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'suspended_at' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d H:i:s'),
                'suspension_reason' => fake()->randomElement([
                    'Payment failure',
                    'Terms of service violation',
                    'Fraudulent activity detected',
                    'Account security concerns'
                ]),
                'suspension_type' => fake()->randomElement(['temporary', 'indefinite']),
            ]),
        ]);
    }

    /**
     * Past due subscription state
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Subscription::STATUS_PAST_DUE,
            'last_payment_at' => fake()->dateTimeBetween('-2 months', '-1 month'),
            'next_payment_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'payment_attempts' => fake()->numberBetween(1, 5),
                'last_payment_attempt' => fake()->dateTimeBetween('-1 week', 'now')->format('Y-m-d H:i:s'),
                'dunning_stage' => fake()->numberBetween(1, 4),
            ]),
        ]);
    }

    /**
     * Monthly billing cycle
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'price' => fake()->randomFloat(2, 19.99, 49.99),
        ]);
    }

    /**
     * Quarterly billing cycle
     */
    public function quarterly(): static
    {
        return $this->state(function (array $attributes) {
            $monthlyPrice = fake()->randomFloat(2, 19.99, 49.99);
            $quarterlyPrice = round($monthlyPrice * 3 * 0.95, 2); // 5% discount
            
            return [
                'billing_cycle' => Subscription::CYCLE_QUARTERLY,
                'price' => $quarterlyPrice,
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'discount_applied' => '5%',
                    'monthly_equivalent' => $monthlyPrice,
                ]),
            ];
        });
    }

    /**
     * Annual billing cycle
     */
    public function annual(): static
    {
        return $this->state(function (array $attributes) {
            $monthlyPrice = fake()->randomFloat(2, 19.99, 49.99);
            $annualPrice = round($monthlyPrice * 12 * 0.85, 2); // 15% discount
            
            return [
                'billing_cycle' => Subscription::CYCLE_ANNUAL,
                'price' => $annualPrice,
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'discount_applied' => '15%',
                    'monthly_equivalent' => $monthlyPrice,
                    'savings_amount' => round($monthlyPrice * 12 - $annualPrice, 2),
                ]),
            ];
        });
    }

    /**
     * High usage subscription state
     */
    public function highUsage(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_stats' => [
                'daily_likes' => fake()->numberBetween(80, 100),
                'super_likes' => fake()->numberBetween(8, 10),
                'boost_used' => fake()->numberBetween(3, 5),
                'messages_sent' => fake()->numberBetween(300, 500),
                'video_calls_made' => fake()->numberBetween(15, 25),
                'profile_views' => fake()->numberBetween(800, 1200),
                'matches_made' => fake()->numberBetween(30, 60),
                'features_used' => [
                    'advanced_search',
                    'incognito_browsing',
                    'read_receipts',
                    'travel_mode',
                    'passport_feature',
                    'rewind_feature'
                ],
            ],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'power_user' => true,
                'engagement_level' => 'very_high',
                'feature_adoption_rate' => fake()->randomFloat(2, 0.80, 1.00),
            ]),
        ]);
    }

    /**
     * Low usage subscription state
     */
    public function lowUsage(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_stats' => [
                'daily_likes' => fake()->numberBetween(0, 20),
                'super_likes' => fake()->numberBetween(0, 2),
                'boost_used' => 0,
                'messages_sent' => fake()->numberBetween(0, 50),
                'video_calls_made' => fake()->numberBetween(0, 3),
                'profile_views' => fake()->numberBetween(0, 200),
                'matches_made' => fake()->numberBetween(0, 10),
                'features_used' => fake()->randomElements([
                    'advanced_search',
                    'read_receipts'
                ], fake()->numberBetween(0, 2)),
            ],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'engagement_level' => 'low',
                'at_risk_churn' => true,
                'feature_adoption_rate' => fake()->randomFloat(2, 0.10, 0.40),
            ]),
        ]);
    }

    /**
     * Long-term subscription state
     */
    public function longTerm(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => fake()->dateTimeBetween('-2 years', '-1 year'),
            'renewal_count' => fake()->numberBetween(12, 48),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'loyal_customer' => true,
                'tenure_months' => fake()->numberBetween(12, 36),
                'lifetime_value' => fake()->randomFloat(2, 500, 2000),
                'churn_risk' => 'low',
            ]),
        ]);
    }

    /**
     * Promotional subscription state
     */
    public function promotional(): static
    {
        return $this->state(function (array $attributes) {
            $originalPrice = fake()->randomFloat(2, 29.99, 99.99);
            $discountedPrice = round($originalPrice * fake()->randomFloat(2, 0.5, 0.8), 2);
            
            return [
                'price' => $discountedPrice,
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'promotional' => true,
                    'original_price' => $originalPrice,
                    'discount_percentage' => round(((($originalPrice - $discountedPrice) / $originalPrice) * 100), 1),
                    'promotion_code' => fake()->randomElement(['SAVE50', 'HOLIDAY25', 'WELCOME30', 'STUDENT20']),
                    'promotion_expires' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
                ]),
            ];
        });
    }

    /**
     * Enterprise subscription state
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => fake()->randomFloat(2, 500, 2000),
            'billing_cycle' => Subscription::CYCLE_ANNUAL,
            'features_snapshot' => [
                'unlimited_messaging',
                'advanced_filters',
                'read_receipts',
                'video_calls',
                'incognito_mode',
                'travel_mode',
                'priority_support',
                'boost_feature',
                'super_likes',
                'rewind_feature',
                'passport_feature',
                'premium_badges',
                'api_access',
                'white_labeling',
                'custom_branding',
                'dedicated_support',
                'advanced_analytics'
            ],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'enterprise' => true,
                'dedicated_account_manager' => true,
                'custom_contract' => true,
                'sla_tier' => 'platinum',
                'invoice_billing' => true,
            ]),
        ]);
    }

    /**
     * International subscription state
     */
    public function international(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => fake()->randomElement(['EUR', 'GBP', 'CAD', 'AUD', 'JPY']),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'international' => true,
                'ip_country' => fake()->randomElement(['CA', 'GB', 'AU', 'DE', 'FR', 'JP']),
                'timezone' => fake()->randomElement([
                    'America/Toronto',
                    'Europe/London', 
                    'Australia/Sydney',
                    'Europe/Berlin',
                    'Europe/Paris',
                    'Asia/Tokyo'
                ]),
                'local_payment_method' => fake()->randomElement(['SEPA', 'BACS', 'JCB', 'Bancontact']),
            ]),
        ]);
    }
}