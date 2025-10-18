<?php

namespace Database\Seeders\Commerce;

use App\Models\Commerce\Wallet;
use App\Models\User\User;
use Database\Factories\Models\Commerce\WalletFactory;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all existing users to create wallets for
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        $this->command->info("Creating wallets for {$users->count()} users...");

        // Create wallets for existing users with different profiles
        $userCount = $users->count();
        $processed = 0;

        foreach ($users as $user) {
            $walletType = $this->determineWalletType($user, $processed, $userCount);
            
            Wallet::factory()
                ->$walletType()
                ->create(['user_id' => $user->id]);
                
            $processed++;
        }

        // Create some additional specific wallet scenarios for testing
        $this->createSpecificWalletScenarios();

        $this->command->info('✅ Wallet seeding completed!');
    }

    /**
     * Determine wallet type based on user characteristics and distribution.
     */
    private function determineWalletType(User $user, int $processed, int $total): string
    {
        $percentage = ($processed / $total) * 100;

        // Distribute wallet types realistically
        return match (true) {
            // First 5% get VIP wallets
            $percentage < 5 => 'vip',
            
            // Next 10% get premium wallets
            $percentage < 15 => 'premium',
            
            // Next 15% get high activity wallets
            $percentage < 30 => 'highActivity',
            
            // Next 20% get new wallets (recently joined)
            $percentage < 50 => 'newWallet',
            
            // 5% get suspended wallets
            $percentage >= 95 && $percentage < 97 => 'suspended',
            
            // 2% get frozen wallets
            $percentage >= 97 && $percentage < 99 => 'frozen',
            
            // 1% get limited wallets
            $percentage >= 99 => 'limited',
            
            // Remaining 35% get standard wallets
            default => 'create'
        };
    }

    /**
     * Create specific wallet scenarios for comprehensive testing.
     */
    private function createSpecificWalletScenarios(): void
    {
        // Create additional users if needed for specific scenarios
        $testUsers = User::factory()->count(10)->create();

        // High-value VIP wallet with extensive history
        Wallet::factory()->vip()->create([
            'user_id' => $testUsers[0]->id,
            'balance' => 50000.00,
            'bonus_balance' => 15000.00,
            'total_spent' => 250000.00,
            'total_earned' => 75000.00,
            'transaction_count' => 1500,
            'daily_limit' => 100000.00,
            'verification_level' => 'premium',
            'loyalty_tier' => 'platinum',
            'metadata' => [
                'vip_privileges' => [
                    'dedicated_account_manager' => true,
                    'custom_limits' => true,
                    'priority_support' => true,
                    'exclusive_features' => true
                ],
                'spending_categories' => [
                    'subscriptions' => 75000,
                    'coins' => 100000,
                    'gifts' => 50000,
                    'boosts' => 20000,
                    'features' => 5000
                ],
                'analytics' => [
                    'lifetime_value' => 250000,
                    'avg_transaction_amount' => 166.67,
                    'loyalty_tier' => 'platinum',
                    'spending_trend' => 'stable'
                ]
            ]
        ]);

        // Suspended wallet with fraud indicators
        Wallet::factory()->suspended()->create([
            'user_id' => $testUsers[1]->id,
            'balance' => 5000.00,
            'risk_score' => 85.5,
            'locked_reason' => 'fraud_detection',
            'metadata' => [
                'suspension_details' => [
                    'suspended_at' => now()->subDays(3)->format('Y-m-d H:i:s'),
                    'reason_code' => 'SUSP001',
                    'suspicious_transactions' => [
                        'rapid_spending' => true,
                        'unusual_patterns' => true,
                        'multiple_failed_payments' => true
                    ],
                    'investigation_status' => 'pending',
                    'estimated_resolution' => now()->addDays(7)->format('Y-m-d')
                ]
            ]
        ]);

        // New user wallet with recent signup
        Wallet::factory()->newWallet()->create([
            'user_id' => $testUsers[2]->id,
            'balance' => 25.00,
            'bonus_balance' => 10.00,
            'total_spent' => 50.00,
            'transaction_count' => 3,
            'created_at' => now()->subDays(2),
            'metadata' => [
                'onboarding_status' => 'in_progress',
                'welcome_bonus_claimed' => true,
                'first_purchase_completed' => true,
                'recommended_actions' => [
                    'complete_profile_verification',
                    'set_up_auto_reload',
                    'explore_premium_features'
                ]
            ]
        ]);

        // High activity wallet with recent heavy usage
        Wallet::factory()->highActivity()->create([
            'user_id' => $testUsers[3]->id,
            'balance' => 2500.00,
            'daily_spent' => 150.00,
            'total_spent' => 15000.00,
            'transaction_count' => 350,
            'last_transaction_at' => now()->subMinutes(30),
            'metadata' => [
                'activity_metrics' => [
                    'transactions_today' => 8,
                    'transactions_this_week' => 45,
                    'peak_usage_time' => '20:00-22:00',
                    'favorite_features' => ['super_likes', 'boosts', 'gifts'],
                    'spending_velocity' => 'high'
                ]
            ]
        ]);

        // Limited wallet with verification issues
        Wallet::factory()->limited()->create([
            'user_id' => $testUsers[4]->id,
            'daily_limit' => 100.00,
            'verification_level' => 'basic',
            'status' => 'limited',
            'metadata' => [
                'limitations' => [
                    'reason' => 'incomplete_verification',
                    'restrictions' => [
                        'max_single_transaction' => 50.00,
                        'daily_limit_reduced' => true,
                        'premium_features_restricted' => true
                    ],
                    'required_documents' => [
                        'government_id',
                        'proof_of_address'
                    ],
                    'lift_restrictions_by' => now()->addDays(14)->format('Y-m-d')
                ]
            ]
        ]);

        // Frozen wallet with pending dispute
        Wallet::factory()->frozen()->create([
            'user_id' => $testUsers[5]->id,
            'balance' => 1200.00,
            'locked_reason' => 'dispute_resolution',
            'metadata' => [
                'freeze_details' => [
                    'dispute_amount' => 500.00,
                    'chargeback_case' => 'CB-2024-001234',
                    'estimated_resolution' => now()->addDays(10)->format('Y-m-d'),
                    'frozen_transactions' => [
                        'subscription_renewal_blocked' => true,
                        'coin_purchases_blocked' => true,
                        'withdrawals_blocked' => true
                    ]
                ]
            ]
        ]);

        // Premium wallet with auto-reload configured
        Wallet::factory()->premium()->create([
            'user_id' => $testUsers[6]->id,
            'balance' => 500.00,
            'auto_reload_enabled' => true,
            'auto_reload_threshold' => 100.00,
            'auto_reload_amount' => 500.00,
            'auto_reload_payment_method' => 'stripe_card_ending_4242',
            'metadata' => [
                'auto_reload_history' => [
                    'last_reload' => now()->subDays(5)->format('Y-m-d H:i:s'),
                    'total_reloads' => 12,
                    'total_reload_amount' => 6000.00,
                    'average_reload_frequency_days' => 15
                ],
                'payment_preferences' => [
                    'preferred_method' => 'credit_card',
                    'backup_methods' => ['paypal', 'apple_pay'],
                    'receipt_notifications' => true
                ]
            ]
        ]);

        // Enterprise wallet for business user
        Wallet::factory()->premium()->create([
            'user_id' => $testUsers[7]->id,
            'balance' => 10000.00,
            'daily_limit' => 25000.00,
            'is_enterprise' => true,
            'verification_level' => 'premium',
            'metadata' => [
                'enterprise_features' => [
                    'bulk_transactions' => true,
                    'api_access' => true,
                    'custom_reporting' => true,
                    'dedicated_support' => true
                ],
                'business_info' => [
                    'company_name' => 'TechStartup Inc.',
                    'business_type' => 'corporate_account',
                    'employee_count' => 50,
                    'monthly_budget' => 15000.00
                ]
            ]
        ]);

        // Wallet with multiple currency support
        Wallet::factory()->create([
            'user_id' => $testUsers[8]->id,
            'balance' => 1000.00,
            'currency' => 'EUR',
            'metadata' => [
                'multi_currency' => [
                    'primary_currency' => 'EUR',
                    'supported_currencies' => ['EUR', 'USD', 'GBP'],
                    'currency_preferences' => [
                        'auto_convert' => true,
                        'display_currency' => 'EUR',
                        'conversion_fees_waived' => true
                    ],
                    'regional_pricing' => [
                        'region' => 'EU',
                        'vat_applicable' => true,
                        'local_payment_methods' => ['sepa', 'ideal', 'sofort']
                    ]
                ]
            ]
        ]);

        // Wallet with loyalty program maxed out
        Wallet::factory()->create([
            'user_id' => $testUsers[9]->id,
            'balance' => 750.00,
            'loyalty_tier' => 'platinum',
            'loyalty_points' => 25000.00,
            'cashback_earned' => 500.00,
            'total_spent' => 50000.00,
            'metadata' => [
                'loyalty_program' => [
                    'tier' => 'platinum',
                    'points_balance' => 25000,
                    'tier_progress' => [
                        'current_tier' => 'platinum',
                        'next_tier' => null,
                        'benefits_unlocked' => [
                            'premium_support',
                            'exclusive_offers',
                            'birthday_bonuses',
                            'referral_bonuses',
                            'cashback_multiplier' => 2.5
                        ]
                    ],
                    'lifetime_stats' => [
                        'total_cashback' => 1250.00,
                        'total_bonuses' => 5000.00,
                        'tier_upgrades' => 4,
                        'member_since' => now()->subYears(2)->format('Y-m-d')
                    ]
                ]
            ]
        ]);
    }
}