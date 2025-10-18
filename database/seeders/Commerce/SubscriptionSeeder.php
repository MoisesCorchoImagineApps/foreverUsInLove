<?php

namespace Database\Seeders\Commerce;

use App\Models\Commerce\Plan;
use App\Models\Commerce\Subscription;
use App\Models\User\User;
use Database\Factories\Models\Commerce\SubscriptionFactory;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $plans = Plan::where('status', 'active')->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        if ($plans->isEmpty()) {
            $this->command->warn('No active plans found. Please run PlanSeeder first.');
            return;
        }

        $this->command->info("Creating subscriptions for users...");

        // Create subscriptions with realistic distribution
        $this->createRealisticSubscriptionDistribution($users, $plans);

        // Create specific subscription scenarios for testing
        $this->createSpecificSubscriptionScenarios($users, $plans);

        $this->command->info('✅ Subscription seeding completed!');
    }

    /**
     * Create subscriptions with realistic distribution across different plans and statuses.
     */
    private function createRealisticSubscriptionDistribution($users, $plans): void
    {
        $subscriberPercentage = 35; // 35% of users have subscriptions
        $subscriberCount = intval(($subscriberPercentage / 100) * $users->count());
        
        $subscribers = $users->random($subscriberCount);

        $statusDistribution = [
            'active' => 60,      // 60% active subscriptions
            'trial' => 20,       // 20% trial subscriptions
            'cancelled' => 10,   // 10% cancelled subscriptions
            'expired' => 5,      // 5% expired subscriptions
            'paused' => 3,       // 3% paused subscriptions
            'past_due' => 2      // 2% past due subscriptions
        ];

        foreach ($statusDistribution as $status => $percentage) {
            $count = intval(($percentage / 100) * $subscriberCount);
            $statusUsers = $subscribers->random(min($count, $subscribers->count()));
            
            foreach ($statusUsers as $user) {
                $plan = $this->selectPlanForUser($user, $plans, $status);
                $subscriptionData = [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'price' => $this->getPlanPrice($plan),
                    'currency' => $plan->currency
                ];

                $factory = Subscription::factory();
                
                // Apply status-specific factory state
                switch ($status) {
                    case 'active':
                        $factory->active();
                        break;
                    case 'trial':
                        $factory->trial();
                        break;
                    case 'cancelled':
                        $factory->cancelled();
                        break;
                    case 'expired':
                        $factory->expired();
                        break;
                    case 'paused':
                        $factory->paused();
                        break;
                    case 'past_due':
                        $factory->pastDue();
                        break;
                }

                $factory->create($subscriptionData);
                
                // Remove user from pool to avoid duplicates
                $subscribers = $subscribers->reject(function ($item) use ($user) {
                    return $item->id === $user->id;
                });
                
                if ($subscribers->isEmpty()) break 2;
            }
        }

        $this->command->info("   ✓ Created {$subscriberCount} subscriptions with realistic distribution");
    }

    /**
     * Create specific subscription scenarios for comprehensive testing.
     */
    private function createSpecificSubscriptionScenarios($users, $plans): void
    {
        $testUsers = $users->take(15);

        // Long-term active premium subscriber
        Subscription::factory()->active()->create([
            'user_id' => $testUsers[0]->id,
            'plan_id' => $plans->where('tier', 'premium')->first()?->id ?? $plans->first()->id,
            'price' => 19.99,
            'billing_cycle' => 'monthly',
            'activated_at' => now()->subMonths(18),
            'current_period_starts_at' => now()->startOfMonth(),
            'current_period_ends_at' => now()->endOfMonth(),
            'next_billing_date' => now()->addMonth()->startOfDay(),
            'successful_payments' => 18,
            'total_paid' => 359.82,
            'last_payment_at' => now()->subDays(5),
            'engagement_metrics' => [
                'login_frequency' => 'daily',
                'feature_usage_rate' => 85,
                'customer_satisfaction' => 4.8,
                'support_tickets' => 2
            ],
            'health_score' => 'excellent',
            'at_risk_of_churn' => false,
            'churn_probability' => 5.2,
            'metadata' => [
                'subscriber_type' => 'loyal_customer',
                'lifetime_value' => 359.82,
                'renewal_likelihood' => 'very_high'
            ]
        ]);

        // VIP annual subscriber with custom features
        Subscription::factory()->active()->create([
            'user_id' => $testUsers[1]->id,
            'plan_id' => $plans->where('tier', 'vip')->first()?->id ?? $plans->first()->id,
            'price' => 399.99,
            'billing_cycle' => 'yearly',
            'activated_at' => now()->subMonths(8),
            'current_period_starts_at' => now()->subMonths(8),
            'current_period_ends_at' => now()->addMonths(4),
            'next_billing_date' => now()->addMonths(4),
            'is_enterprise' => false,
            'custom_features' => [
                'priority_matching' => true,
                'exclusive_events_access' => true,
                'personal_dating_coach' => true,
                'advanced_analytics' => true
            ],
            'engagement_metrics' => [
                'login_frequency' => 'daily',
                'feature_usage_rate' => 95,
                'premium_feature_adoption' => 100,
                'referrals_made' => 5
            ],
            'health_score' => 'excellent',
            'metadata' => [
                'subscriber_type' => 'vip_customer',
                'account_value' => 'high',
                'special_treatment' => true
            ]
        ]);

        // Elite enterprise subscriber
        Subscription::factory()->active()->create([
            'user_id' => $testUsers[2]->id,
            'plan_id' => $plans->where('tier', 'elite')->first()?->id ?? $plans->first()->id,
            'price' => 999.99,
            'billing_cycle' => 'yearly',
            'is_enterprise' => true,
            'account_manager_id' => 'AM_' . fake()->numerify('####'),
            'custom_features' => [
                'white_label_branding' => true,
                'api_access' => true,
                'bulk_operations' => true,
                'custom_matching_algorithm' => true,
                'dedicated_infrastructure' => true
            ],
            'custom_limits' => [
                'profiles_per_day' => -1, // unlimited
                'messages_per_day' => -1,
                'api_calls_per_hour' => 10000,
                'concurrent_sessions' => 50
            ],
            'sla_terms' => [
                'uptime_guarantee' => 99.9,
                'response_time_hours' => 2,
                'dedicated_support' => true
            ],
            'metadata' => [
                'subscriber_type' => 'enterprise',
                'contract_value' => 999.99,
                'renewal_terms' => 'annual_only',
                'business_critical' => true
            ]
        ]);

        // Trial subscription about to convert
        Subscription::factory()->trial()->create([
            'user_id' => $testUsers[3]->id,
            'plan_id' => $plans->where('tier', 'premium')->first()?->id ?? $plans->first()->id,
            'price' => 19.99,
            'is_trial' => true,
            'trial_starts_at' => now()->subDays(12),
            'trial_ends_at' => now()->addDays(2),
            'trial_days_remaining' => 2,
            'engagement_metrics' => [
                'trial_feature_usage' => 75,
                'daily_active_days' => 10,  // out of 12
                'conversion_signals' => [
                    'completed_profile' => true,
                    'uploaded_photos' => true,
                    'sent_messages' => 15,
                    'received_matches' => 8
                ]
            ],
            'health_score' => 'good',
            'metadata' => [
                'trial_engagement' => 'high',
                'conversion_probability' => 78.5,
                'trial_extension_eligible' => false
            ]
        ]);

        // Recently cancelled subscription (churned customer)
        Subscription::factory()->cancelled()->create([
            'user_id' => $testUsers[4]->id,
            'plan_id' => $plans->where('tier', 'premium')->first()?->id ?? $plans->first()->id,
            'price' => 19.99,
            'billing_cycle' => 'monthly',
            'activated_at' => now()->subMonths(6),
            'cancelled_at' => now()->subDays(10),
            'cancellation_reason' => 'price_too_high',
            'cancellation_note' => 'Found the subscription too expensive for current budget',
            'successful_payments' => 6,
            'total_paid' => 119.94,
            'engagement_metrics' => [
                'usage_decline_period_weeks' => 3,
                'last_active_days_ago' => 15,
                'support_tickets_before_cancel' => 1
            ],
            'health_score' => 'poor',
            'metadata' => [
                'churn_reason' => 'price_sensitivity',
                'win_back_eligible' => true,
                'discount_offer_sent' => true,
                'exit_survey_completed' => true
            ]
        ]);

        // Past due subscription with failed payments
        Subscription::factory()->pastDue()->create([
            'user_id' => $testUsers[5]->id,
            'plan_id' => $plans->where('tier', 'basic')->first()?->id ?? $plans->first()->id,
            'price' => 9.99,
            'billing_cycle' => 'monthly',
            'billing_status' => 'past_due',
            'next_billing_date' => now()->subDays(5),
            'failed_payments' => 3,
            'consecutive_failed_payments' => 3,
            'last_billing_attempt' => now()->subHours(6),
            'outstanding_balance' => 29.97, // 3 failed payments
            'dunning_status' => 'hard',
            'dunning_started_at' => now()->subDays(15),
            'dunning_attempts' => 6,
            'next_dunning_attempt' => now()->addHours(12),
            'metadata' => [
                'payment_failure_reason' => 'expired_card',
                'customer_contacted' => true,
                'suspend_date' => now()->addDays(2)->format('Y-m-d'),
                'recovery_campaign' => 'expired_card_update'
            ]
        ]);

        // Paused subscription (temporary hold)
        Subscription::factory()->paused()->create([
            'user_id' => $testUsers[6]->id,
            'plan_id' => $plans->where('tier', 'premium')->first()?->id ?? $plans->first()->id,
            'price' => 19.99,
            'billing_cycle' => 'monthly',
            'status' => 'paused',
            'paused_at' => now()->subDays(20),
            'scheduled_cancellation_at' => now()->addDays(40), // 60-day pause limit
            'auto_renew' => false,
            'metadata' => [
                'pause_reason' => 'temporary_break',
                'pause_duration_days' => 60,
                'resume_date' => now()->addDays(40)->format('Y-m-d'),
                'customer_initiated' => true,
                'pause_count' => 1 // First time pausing
            ]
        ]);

        // Expired subscription (grace period ended)
        Subscription::factory()->expired()->create([
            'user_id' => $testUsers[7]->id,
            'plan_id' => $plans->where('tier', 'basic')->first()?->id ?? $plans->first()->id,
            'price' => 9.99,
            'billing_cycle' => 'monthly',
            'status' => 'expired',
            'expires_at' => now()->subDays(10),
            'cancelled_at' => now()->subDays(40),
            'cancellation_reason' => 'payment_failed',
            'failed_payments' => 5,
            'total_paid' => 59.94, // 6 months before expiry
            'metadata' => [
                'expiry_reason' => 'payment_failure_exceeded_retry_limit',
                'final_attempt_date' => now()->subDays(10)->format('Y-m-d'),
                'account_downgraded_to' => 'free_tier',
                'data_retention_days' => 90
            ]
        ]);

        // High-risk subscription flagged for review
        Subscription::factory()->active()->create([
            'user_id' => $testUsers[8]->id,
            'plan_id' => $plans->where('tier', 'vip')->first()?->id ?? $plans->first()->id,
            'price' => 39.99,
            'risk_score' => 85.5,
            'risk_level' => 'high',
            'requires_manual_review' => true,
            'compliance_flags' => [
                'unusual_payment_pattern' => true,
                'geographic_risk' => false,
                'velocity_check_failed' => true,
                'fraud_score_high' => true
            ],
            'metadata' => [
                'review_reason' => 'unusual_upgrade_pattern',
                'risk_factors' => [
                    'rapid_plan_changes' => true,
                    'multiple_failed_payments' => false,
                    'suspicious_usage_pattern' => true
                ],
                'review_assigned_to' => 'compliance_team',
                'review_deadline' => now()->addDays(3)->format('Y-m-d')
            ]
        ]);

        // Student subscription with verification
        Subscription::factory()->active()->create([
            'user_id' => $testUsers[9]->id,
            'plan_id' => $plans->where('name', 'LIKE', '%Student%')->first()?->id ?? $plans->first()->id,
            'price' => 9.99, // Student discount price
            'billing_cycle' => 'monthly',
            'metadata' => [
                'subscriber_type' => 'student',
                'verification_status' => 'verified',
                'student_email' => fake()->email(),
                'university' => fake()->randomElement(['Harvard', 'MIT', 'Stanford', 'UCLA']),
                'graduation_year' => now()->addYears(2)->year,
                'discount_percentage' => 50,
                'verification_expires' => now()->addYear()->format('Y-m-d')
            ]
        ]);

        // Subscription with pending plan change
        Subscription::factory()->active()->create([
            'user_id' => $testUsers[10]->id,
            'plan_id' => $plans->where('tier', 'basic')->first()?->id ?? $plans->first()->id,
            'price' => 9.99,
            'billing_cycle' => 'monthly',
            'pending_changes' => [
                'new_plan_id' => $plans->where('tier', 'premium')->first()?->id ?? $plans->skip(1)->first()->id,
                'new_price' => 19.99,
                'change_type' => 'upgrade',
                'effective_date' => now()->addDays(5)->format('Y-m-d'),
                'proration_amount' => 15.32,
                'change_reason' => 'user_requested_upgrade'
            ],
            'pending_change_effective_date' => now()->addDays(5),
            'proration_credit' => -15.32, // Will be charged extra
            'metadata' => [
                'upgrade_initiated_at' => now()->subDays(2)->format('Y-m-d H:i:s'),
                'upgrade_reason' => 'wants_more_features',
                'customer_notified' => true
            ]
        ]);

        // International subscription with multiple currencies
        Subscription::factory()->active()->create([
            'user_id' => $testUsers[11]->id,
            'plan_id' => $plans->first()->id,
            'price' => 17.99,
            'currency' => 'EUR',
            'billing_cycle' => 'monthly',
            'metadata' => [
                'original_currency' => 'USD',
                'original_price' => 19.99,
                'exchange_rate_at_signup' => 0.90,
                'region' => 'EU',
                'vat_rate' => 20.0,
                'vat_amount' => 3.60,
                'payment_method_region' => 'germany'
            ]
        ]);

        // Family/group subscription
        Subscription::factory()->active()->create([
            'user_id' => $testUsers[12]->id,
            'plan_id' => $plans->where('tier', 'premium')->first()?->id ?? $plans->first()->id,
            'price' => 49.99, // Family plan pricing
            'billing_cycle' => 'monthly',
            'metadata' => [
                'subscription_type' => 'family',
                'max_family_members' => 4,
                'current_family_members' => 3,
                'family_members' => [
                    ['user_id' => $testUsers[13]->id, 'relation' => 'spouse', 'added_at' => now()->subMonths(3)->format('Y-m-d')],
                    ['user_id' => $testUsers[14]->id, 'relation' => 'child', 'added_at' => now()->subMonths(1)->format('Y-m-d')]
                ],
                'family_controls' => [
                    'parental_controls' => true,
                    'spending_limits' => ['child' => 20.00],
                    'content_filtering' => true
                ]
            ]
        ]);

        $this->command->info("   ✓ Created specific subscription test scenarios");
    }

    /**
     * Select appropriate plan for user based on status and characteristics.
     */
    private function selectPlanForUser($user, $plans, $status): Plan
    {
        // Logic to select plans based on user characteristics and subscription status
        return match ($status) {
            'trial' => $plans->where('tier', 'premium')->first() ?? $plans->first(),
            'cancelled', 'expired' => $plans->where('tier', 'basic')->first() ?? $plans->first(),
            'past_due' => $plans->where('tier', 'basic')->first() ?? $plans->first(),
            default => $plans->random()
        };
    }

    /**
     * Get appropriate price for plan based on billing cycle.
     */
    private function getPlanPrice($plan): float
    {
        $pricing = $plan->pricing ?? [];
        
        // Default to monthly pricing, or first available price
        return $pricing['monthly']['price'] 
            ?? $pricing['yearly']['price'] 
            ?? array_values($pricing)[0]['price'] 
            ?? 19.99;
    }
}