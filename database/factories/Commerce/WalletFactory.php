<?php

namespace Database\Factories\Models\Commerce;

use App\Models\Commerce\Wallet;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commerce\Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Wallet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseBalance = $this->faker->numberBetween(0, 10000);
        $bonusBalance = $this->faker->numberBetween(0, 2000);
        $totalSpent = $this->faker->numberBetween(500, 50000);
        $totalEarned = $this->faker->numberBetween(1000, 25000);
        
        return [
            'user_id' => User::factory(),
            'balance' => $baseBalance,
            'bonus_balance' => $bonusBalance,
            'total_balance' => $baseBalance + $bonusBalance,
            'daily_limit' => $this->faker->randomElement([1000, 2500, 5000, 10000, 25000]),
            'daily_spent' => $this->faker->numberBetween(0, 1000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'CAD', 'AUD']),
            'status' => $this->faker->randomElement(['active', 'suspended', 'frozen', 'limited']),
            'last_transaction_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 month', 'now'),
            'verification_level' => $this->faker->randomElement(['unverified', 'basic', 'enhanced', 'premium']),
            'risk_score' => $this->faker->numberBetween(0, 100),
            'total_spent' => $totalSpent,
            'total_earned' => $totalEarned,
            'transaction_count' => $this->faker->numberBetween(5, 500),
            'metadata' => [
                'spending_categories' => [
                    'subscriptions' => $this->faker->numberBetween(200, 5000),
                    'coins' => $this->faker->numberBetween(100, 10000),
                    'gifts' => $this->faker->numberBetween(50, 2000),
                    'boosts' => $this->faker->numberBetween(25, 1500),
                    'features' => $this->faker->numberBetween(10, 800)
                ],
                'payment_preferences' => [
                    'auto_reload' => $this->faker->boolean(60),
                    'auto_reload_amount' => $this->faker->randomElement([500, 1000, 2500, 5000]),
                    'preferred_payment_method' => $this->faker->randomElement(['card', 'paypal', 'apple_pay', 'google_pay']),
                    'spending_notifications' => $this->faker->boolean(75)
                ],
                'security_settings' => [
                    'transaction_pin' => $this->faker->boolean(40),
                    'biometric_auth' => $this->faker->boolean(55),
                    'spending_alerts' => $this->faker->boolean(80),
                    'large_transaction_approval' => $this->faker->boolean(70)
                ],
                'analytics' => [
                    'avg_transaction_amount' => round($totalSpent / max(1, $this->faker->numberBetween(10, 100)), 2),
                    'most_active_day' => $this->faker->randomElement(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']),
                    'spending_trend' => $this->faker->randomElement(['increasing', 'stable', 'decreasing']),
                    'loyalty_tier' => $this->faker->randomElement(['bronze', 'silver', 'gold', 'platinum'])
                ]
            ],
            'is_locked' => $this->faker->boolean(5),
            'locked_until' => null,
            'locked_reason' => null,
            'created_at' => $this->faker->dateTimeBetween('-2 years', '-1 month'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * State for a new wallet with minimal balance.
     */
    public function newWallet(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $this->faker->numberBetween(0, 100),
            'bonus_balance' => $this->faker->numberBetween(0, 50),
            'total_balance' => $attributes['balance'] + $attributes['bonus_balance'],
            'daily_limit' => 1000,
            'daily_spent' => 0,
            'status' => 'active',
            'verification_level' => 'unverified',
            'risk_score' => $this->faker->numberBetween(0, 30),
            'total_spent' => $this->faker->numberBetween(0, 200),
            'total_earned' => $this->faker->numberBetween(0, 100),
            'transaction_count' => $this->faker->numberBetween(0, 5),
            'last_transaction_at' => null,
            'metadata' => [
                'spending_categories' => [
                    'subscriptions' => 0,
                    'coins' => $this->faker->numberBetween(0, 100),
                    'gifts' => 0,
                    'boosts' => 0,
                    'features' => 0
                ],
                'payment_preferences' => [
                    'auto_reload' => false,
                    'auto_reload_amount' => 500,
                    'preferred_payment_method' => null,
                    'spending_notifications' => true
                ],
                'security_settings' => [
                    'transaction_pin' => false,
                    'biometric_auth' => false,
                    'spending_alerts' => true,
                    'large_transaction_approval' => true
                ],
                'analytics' => [
                    'avg_transaction_amount' => 0,
                    'most_active_day' => null,
                    'spending_trend' => 'stable',
                    'loyalty_tier' => 'bronze'
                ]
            ],
            'is_locked' => false,
            'created_at' => now()->subDays($this->faker->numberBetween(1, 7)),
            'updated_at' => now()->subDays($this->faker->numberBetween(0, 3)),
        ]);
    }

    /**
     * State for a premium wallet with high balance and limits.
     */
    public function premium(): static
    {
        return $this->state(function (array $attributes) {
            $highBalance = $this->faker->numberBetween(25000, 100000);
            $premiumBonus = $this->faker->numberBetween(5000, 15000);
            $premiumSpent = $this->faker->numberBetween(50000, 200000);
            $premiumEarned = $this->faker->numberBetween(25000, 75000);
            
            return [
                'balance' => $highBalance,
                'bonus_balance' => $premiumBonus,
                'total_balance' => $highBalance + $premiumBonus,
                'daily_limit' => 50000,
                'daily_spent' => $this->faker->numberBetween(1000, 10000),
                'status' => 'active',
                'verification_level' => 'premium',
                'risk_score' => $this->faker->numberBetween(0, 25),
                'total_spent' => $premiumSpent,
                'total_earned' => $premiumEarned,
                'transaction_count' => $this->faker->numberBetween(200, 1000),
                'last_transaction_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
                'metadata' => [
                    'spending_categories' => [
                        'subscriptions' => $this->faker->numberBetween(10000, 30000),
                        'coins' => $this->faker->numberBetween(15000, 50000),
                        'gifts' => $this->faker->numberBetween(5000, 25000),
                        'boosts' => $this->faker->numberBetween(3000, 15000),
                        'features' => $this->faker->numberBetween(2000, 10000)
                    ],
                    'payment_preferences' => [
                        'auto_reload' => true,
                        'auto_reload_amount' => $this->faker->randomElement([10000, 25000, 50000]),
                        'preferred_payment_method' => $this->faker->randomElement(['card', 'paypal']),
                        'spending_notifications' => true
                    ],
                    'security_settings' => [
                        'transaction_pin' => true,
                        'biometric_auth' => true,
                        'spending_alerts' => true,
                        'large_transaction_approval' => true
                    ],
                    'analytics' => [
                        'avg_transaction_amount' => round($premiumSpent / max(1, $this->faker->numberBetween(200, 500)), 2),
                        'most_active_day' => $this->faker->randomElement(['Friday', 'Saturday', 'Sunday']),
                        'spending_trend' => $this->faker->randomElement(['increasing', 'stable']),
                        'loyalty_tier' => $this->faker->randomElement(['gold', 'platinum'])
                    ]
                ],
                'is_locked' => false,
            ];
        });
    }

    /**
     * State for a suspended wallet.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'risk_score' => $this->faker->numberBetween(70, 95),
            'is_locked' => true,
            'locked_until' => $this->faker->dateTimeBetween('now', '+30 days'),
            'locked_reason' => $this->faker->randomElement([
                'suspicious_activity',
                'fraud_detection',
                'compliance_review',
                'user_request',
                'chargeback_dispute'
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'suspension_details' => [
                    'suspended_at' => $this->faker->dateTimeBetween('-7 days', 'now')->format('Y-m-d H:i:s'),
                    'suspended_by' => 'system',
                    'reason_code' => $this->faker->randomElement(['SUSP001', 'SUSP002', 'SUSP003']),
                    'review_required' => true,
                    'escalation_level' => $this->faker->randomElement(['low', 'medium', 'high'])
                ]
            ])
        ]);
    }

    /**
     * State for a wallet with frozen funds.
     */
    public function frozen(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'frozen',
            'risk_score' => $this->faker->numberBetween(60, 90),
            'is_locked' => true,
            'locked_until' => $this->faker->dateTimeBetween('now', '+14 days'),
            'locked_reason' => $this->faker->randomElement([
                'pending_verification',
                'security_review',
                'dispute_resolution',
                'regulatory_hold'
            ]),
            'daily_spent' => $attributes['daily_limit'], // Hit daily limit
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'freeze_details' => [
                    'frozen_at' => $this->faker->dateTimeBetween('-3 days', 'now')->format('Y-m-d H:i:s'),
                    'frozen_amount' => $this->faker->numberBetween(1000, 10000),
                    'case_number' => 'FRZ-' . $this->faker->numerify('######'),
                    'estimated_resolution' => $this->faker->dateTimeBetween('now', '+14 days')->format('Y-m-d'),
                    'contact_required' => $this->faker->boolean(70)
                ]
            ])
        ]);
    }

    /**
     * State for a high-activity wallet.
     */
    public function highActivity(): static
    {
        return $this->state(function (array $attributes) {
            $activitySpent = $this->faker->numberBetween(100000, 500000);
            $activityEarned = $this->faker->numberBetween(50000, 150000);
            
            return [
                'daily_limit' => $this->faker->randomElement([25000, 50000, 100000]),
                'daily_spent' => $this->faker->numberBetween(5000, 25000),
                'verification_level' => $this->faker->randomElement(['enhanced', 'premium']),
                'risk_score' => $this->faker->numberBetween(0, 40),
                'total_spent' => $activitySpent,
                'total_earned' => $activityEarned,
                'transaction_count' => $this->faker->numberBetween(500, 2000),
                'last_transaction_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'activity_metrics' => [
                        'transactions_today' => $this->faker->numberBetween(10, 50),
                        'transactions_this_week' => $this->faker->numberBetween(50, 200),
                        'transactions_this_month' => $this->faker->numberBetween(200, 800),
                        'peak_spending_hour' => $this->faker->numberBetween(18, 23),
                        'favorite_category' => $this->faker->randomElement(['coins', 'subscriptions', 'gifts']),
                        'spending_velocity' => $this->faker->randomElement(['high', 'very_high'])
                    ]
                ])
            ];
        });
    }

    /**
     * State for a limited wallet (verification issues).
     */
    public function limited(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'limited',
            'daily_limit' => 500, // Reduced limit
            'verification_level' => 'basic',
            'risk_score' => $this->faker->numberBetween(40, 70),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'limitations' => [
                    'max_single_transaction' => 100,
                    'verification_required_for' => ['withdrawals', 'large_purchases'],
                    'restricted_features' => ['auto_reload', 'gifts_above_limit'],
                    'lift_restrictions_by' => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
                    'required_documents' => $this->faker->randomElements(['id_verification', 'address_proof', 'payment_verification'], 2)
                ]
            ])
        ]);
    }

    /**
     * State for a VIP wallet with maximum privileges.
     */
    public function vip(): static
    {
        return $this->state(function (array $attributes) {
            $vipBalance = $this->faker->numberBetween(100000, 1000000);
            $vipBonus = $this->faker->numberBetween(25000, 100000);
            $vipSpent = $this->faker->numberBetween(500000, 2000000);
            $vipEarned = $this->faker->numberBetween(100000, 500000);
            
            return [
                'balance' => $vipBalance,
                'bonus_balance' => $vipBonus,
                'total_balance' => $vipBalance + $vipBonus,
                'daily_limit' => 250000, // Very high limit
                'daily_spent' => $this->faker->numberBetween(10000, 50000),
                'status' => 'active',
                'verification_level' => 'premium',
                'risk_score' => $this->faker->numberBetween(0, 15), // Very low risk
                'total_spent' => $vipSpent,
                'total_earned' => $vipEarned,
                'transaction_count' => $this->faker->numberBetween(1000, 5000),
                'last_transaction_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
                'metadata' => [
                    'vip_privileges' => [
                        'priority_support' => true,
                        'exclusive_features' => true,
                        'custom_limits' => true,
                        'dedicated_account_manager' => true,
                        'instant_withdrawals' => true
                    ],
                    'spending_categories' => [
                        'subscriptions' => $this->faker->numberBetween(50000, 200000),
                        'coins' => $this->faker->numberBetween(100000, 500000),
                        'gifts' => $this->faker->numberBetween(50000, 300000),
                        'boosts' => $this->faker->numberBetween(25000, 150000),
                        'features' => $this->faker->numberBetween(20000, 100000)
                    ],
                    'payment_preferences' => [
                        'auto_reload' => true,
                        'auto_reload_amount' => $this->faker->randomElement([50000, 100000, 250000]),
                        'preferred_payment_method' => 'card',
                        'spending_notifications' => false, // VIPs prefer less notifications
                        'concierge_payments' => true
                    ],
                    'security_settings' => [
                        'transaction_pin' => true,
                        'biometric_auth' => true,
                        'spending_alerts' => false,
                        'large_transaction_approval' => false, // Pre-approved
                        'white_glove_security' => true
                    ],
                    'analytics' => [
                        'avg_transaction_amount' => round($vipSpent / max(1, $this->faker->numberBetween(1000, 2000)), 2),
                        'most_active_day' => 'Saturday',
                        'spending_trend' => 'increasing',
                        'loyalty_tier' => 'platinum',
                        'lifetime_value' => $this->faker->numberBetween(1000000, 5000000)
                    ]
                ],
                'is_locked' => false,
            ];
        });
    }
}