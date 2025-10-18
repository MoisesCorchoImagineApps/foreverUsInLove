<?php

namespace Database\Seeders\Commerce;

use App\Models\Commerce\Plan;
use Database\Factories\Models\Commerce\PlanFactory;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create basic tier plans
        Plan::factory()->basic()->count(2)->create([
            'is_popular' => false,
            'sort_order' => 100
        ]);

        // Create premium tier plans (most popular)
        Plan::factory()->premium()->count(3)->create([
            'is_popular' => true,
            'is_recommended' => true,
            'sort_order' => 200
        ]);

        // Create VIP tier plans
        Plan::factory()->vip()->count(2)->create([
            'is_recommended' => true,
            'sort_order' => 300
        ]);

        // Create elite tier plans
        Plan::factory()->elite()->count(2)->create([
            'requires_approval' => true,
            'sort_order' => 400
        ]);

        // Create trial plans
        Plan::factory()->trial()->count(2)->create([
            'sort_order' => 50
        ]);

        // Create enterprise plans
        Plan::factory()->enterprise()->count(1)->create([
            'requires_approval' => true,
            'custom_contract' => true,
            'sort_order' => 500
        ]);

        // Create some archived plans
        Plan::factory()->count(2)->create([
            'status' => 'archived',
            'availability' => 'grandfathered'
        ]);

        // Create realistic subscription plans
        $this->createRealisticPlans();
    }

    /**
     * Create realistic subscription plans based on dating app industry standards.
     */
    private function createRealisticPlans(): void
    {
        // Basic Monthly Plan
        Plan::factory()->create([
            'name' => 'Basic Monthly',
            'description' => 'Essential features to enhance your dating experience',
            'short_description' => 'Basic features, monthly billing',
            'tier' => 'basic',
            'pricing' => [
                'monthly' => ['price' => 9.99, 'setup_fee' => 0],
                'quarterly' => ['price' => 24.99, 'setup_fee' => 0, 'savings' => 5.00],
                'yearly' => ['price' => 89.99, 'setup_fee' => 0, 'savings' => 30.00]
            ],
            'billing_cycle' => 'monthly',
            'trial_period_days' => 7,
            'features' => [
                'unlimited_likes',
                'see_who_liked_you',
                'basic_filters',
                'message_read_receipts',
                '5_super_likes_daily'
            ],
            'limits' => [
                'super_likes_per_day' => 5,
                'boosts_per_month' => 0,
                'rewinds_per_month' => 3,
                'profile_views_limit' => 100
            ],
            'is_popular' => false,
            'is_recommended' => false,
            'sort_order' => 101,
            'slug' => 'basic-monthly',
            'tags' => ['basic', 'starter', 'monthly'],
            'metadata' => [
                'target_audience' => 'casual_users',
                'recommended_usage' => 'light_dating',
                'comparison_highlight' => 'Perfect for getting started'
            ]
        ]);

        // Premium Monthly Plan (Most Popular)
        Plan::factory()->create([
            'name' => 'Premium Monthly',
            'description' => 'Our most popular plan with advanced features and better visibility',
            'short_description' => 'Most popular - advanced features',
            'tier' => 'premium',
            'pricing' => [
                'monthly' => ['price' => 19.99, 'setup_fee' => 0],
                'quarterly' => ['price' => 54.99, 'setup_fee' => 0, 'savings' => 15.00],
                'yearly' => ['price' => 199.99, 'setup_fee' => 0, 'savings' => 60.00]
            ],
            'billing_cycle' => 'monthly',
            'trial_period_days' => 14,
            'features' => [
                'unlimited_likes',
                'see_who_liked_you',
                'advanced_filters',
                'message_read_receipts',
                '10_super_likes_daily',
                'monthly_boost',
                'passport_mode',
                'incognito_mode',
                'priority_likes'
            ],
            'limits' => [
                'super_likes_per_day' => 10,
                'boosts_per_month' => 1,
                'rewinds_per_month' => 10,
                'profile_views_limit' => -1, // unlimited
                'distance_override' => true
            ],
            'is_popular' => true,
            'is_recommended' => true,
            'sort_order' => 201,
            'slug' => 'premium-monthly',
            'tags' => ['premium', 'popular', 'recommended', 'monthly'],
            'metadata' => [
                'target_audience' => 'active_daters',
                'recommended_usage' => 'regular_dating',
                'comparison_highlight' => 'Most popular choice',
                'badge' => 'BEST VALUE'
            ]
        ]);

        // Premium Annual Plan (Best Savings)
        Plan::factory()->create([
            'name' => 'Premium Annual',
            'description' => 'Save big with our annual premium plan - all premium features included',
            'short_description' => 'Premium features, best savings',
            'tier' => 'premium',
            'pricing' => [
                'yearly' => ['price' => 119.99, 'setup_fee' => 0, 'savings' => 120.00]
            ],
            'billing_cycle' => 'yearly',
            'trial_period_days' => 30,
            'features' => [
                'unlimited_likes',
                'see_who_liked_you',
                'advanced_filters',
                'message_read_receipts',
                '10_super_likes_daily',
                'monthly_boost',
                'passport_mode',
                'incognito_mode',
                'priority_likes',
                'profile_verification_priority'
            ],
            'limits' => [
                'super_likes_per_day' => 10,
                'boosts_per_month' => 1,
                'rewinds_per_month' => 10,
                'profile_views_limit' => -1,
                'distance_override' => true
            ],
            'is_popular' => true,
            'is_recommended' => true,
            'sort_order' => 202,
            'slug' => 'premium-annual',
            'tags' => ['premium', 'annual', 'savings', 'recommended'],
            'metadata' => [
                'target_audience' => 'committed_daters',
                'recommended_usage' => 'serious_dating',
                'comparison_highlight' => 'Save 50% with annual billing',
                'badge' => 'BEST SAVINGS',
                'monthly_equivalent' => 9.99
            ]
        ]);

        // VIP Monthly Plan
        Plan::factory()->create([
            'name' => 'VIP Monthly',
            'description' => 'VIP treatment with exclusive features and maximum visibility',
            'short_description' => 'VIP features and priority support',
            'tier' => 'vip',
            'pricing' => [
                'monthly' => ['price' => 39.99, 'setup_fee' => 0],
                'quarterly' => ['price' => 109.99, 'setup_fee' => 0, 'savings' => 30.00],
                'yearly' => ['price' => 399.99, 'setup_fee' => 0, 'savings' => 80.00]
            ],
            'billing_cycle' => 'monthly',
            'trial_period_days' => 21,
            'features' => [
                'unlimited_everything',
                'see_who_liked_you',
                'premium_filters',
                'message_read_receipts',
                '20_super_likes_daily',
                '4_boosts_monthly',
                'passport_mode',
                'incognito_mode',
                'priority_likes',
                'read_receipts',
                'message_before_matching',
                'exclusive_vip_events',
                'priority_customer_support',
                'profile_verification_fast_track'
            ],
            'limits' => [
                'super_likes_per_day' => 20,
                'boosts_per_month' => 4,
                'rewinds_per_month' => -1, // unlimited
                'profile_views_limit' => -1,
                'distance_override' => true,
                'age_range_override' => true
            ],
            'is_popular' => false,
            'is_recommended' => true,
            'sort_order' => 301,
            'slug' => 'vip-monthly',
            'tags' => ['vip', 'exclusive', 'premium_support'],
            'metadata' => [
                'target_audience' => 'power_users',
                'recommended_usage' => 'intensive_dating',
                'comparison_highlight' => 'VIP treatment with exclusive perks',
                'badge' => 'VIP ACCESS'
            ]
        ]);

        // Elite Platinum Plan
        Plan::factory()->create([
            'name' => 'Elite Platinum',
            'description' => 'The ultimate dating experience with personal matchmaker and concierge service',
            'short_description' => 'Ultimate experience with personal matchmaker',
            'tier' => 'elite',
            'pricing' => [
                'monthly' => ['price' => 99.99, 'setup_fee' => 49.99],
                'quarterly' => ['price' => 279.99, 'setup_fee' => 49.99, 'savings' => 90.00],
                'yearly' => ['price' => 999.99, 'setup_fee' => 0, 'savings' => 250.00]
            ],
            'billing_cycle' => 'monthly',
            'trial_period_days' => 30,
            'requires_approval' => true,
            'features' => [
                'unlimited_everything',
                'personal_matchmaker',
                'concierge_dating_service',
                'exclusive_elite_events',
                'background_verification',
                'professional_photo_review',
                'profile_optimization_service',
                'white_glove_customer_service',
                'priority_matching_algorithm',
                'exclusive_high_quality_matches',
                '50_super_likes_daily',
                'unlimited_boosts',
                'incognito_mode',
                'message_before_matching',
                'video_date_scheduling'
            ],
            'limits' => [
                'super_likes_per_day' => 50,
                'boosts_per_month' => -1, // unlimited
                'rewinds_per_month' => -1,
                'profile_views_limit' => -1,
                'personal_matchmaker_hours' => 2, // per month
                'concierge_requests' => 5 // per month
            ],
            'is_popular' => false,
            'is_recommended' => false,
            'sort_order' => 401,
            'slug' => 'elite-platinum',
            'availability' => 'invite_only',
            'tags' => ['elite', 'platinum', 'matchmaker', 'concierge'],
            'enterprise_features' => [
                'dedicated_account_manager',
                'monthly_performance_reports',
                'custom_matching_criteria',
                'exclusive_networking_events',
                'professional_photography_session'
            ],
            'metadata' => [
                'target_audience' => 'high_net_worth',
                'recommended_usage' => 'luxury_dating',
                'comparison_highlight' => 'Personal matchmaker and concierge',
                'badge' => 'ELITE ONLY',
                'application_required' => true,
                'income_verification' => true
            ]
        ]);

        // Student Discount Plan
        Plan::factory()->create([
            'name' => 'Student Premium',
            'description' => 'Special student pricing for premium features - verify your student status',
            'short_description' => 'Student discount on premium features',
            'tier' => 'premium',
            'pricing' => [
                'monthly' => ['price' => 9.99, 'setup_fee' => 0],
                'quarterly' => ['price' => 27.99, 'setup_fee' => 0, 'savings' => 2.00],
                'yearly' => ['price' => 99.99, 'setup_fee' => 0, 'savings' => 20.00]
            ],
            'billing_cycle' => 'monthly',
            'trial_period_days' => 14,
            'features' => [
                'unlimited_likes',
                'see_who_liked_you',
                'advanced_filters',
                'message_read_receipts',
                '10_super_likes_daily',
                'monthly_boost',
                'passport_mode',
                'student_community_access'
            ],
            'limits' => [
                'super_likes_per_day' => 10,
                'boosts_per_month' => 1,
                'rewinds_per_month' => 10,
                'profile_views_limit' => -1,
                'age_range' => [18, 25] // Student age restriction
            ],
            'targeting_rules' => [
                'requires_student_verification' => true,
                'max_age' => 25,
                'valid_edu_email_required' => true
            ],
            'is_popular' => false,
            'is_recommended' => false,
            'sort_order' => 150,
            'slug' => 'student-premium',
            'tags' => ['student', 'discount', 'premium', 'education'],
            'metadata' => [
                'target_audience' => 'students',
                'verification_required' => 'student_id_or_edu_email',
                'comparison_highlight' => '50% off for verified students',
                'badge' => 'STUDENT DISCOUNT'
            ]
        ]);

        // Free Trial Plan (Entry Point)
        Plan::factory()->create([
            'name' => 'Free Trial',
            'description' => 'Try premium features free for 7 days - no commitment required',
            'short_description' => '7-day free trial of premium features',
            'tier' => 'basic',
            'pricing' => [
                'trial' => ['price' => 0.00, 'setup_fee' => 0]
            ],
            'billing_cycle' => 'monthly',
            'trial_period_days' => 7,
            'trial_requires_payment_method' => false,
            'features' => [
                'unlimited_likes',
                'see_who_liked_you',
                'basic_filters',
                'message_read_receipts',
                '5_super_likes_daily'
            ],
            'limits' => [
                'super_likes_per_day' => 5,
                'boosts_per_month' => 0,
                'rewinds_per_month' => 1,
                'trial_duration_days' => 7
            ],
            'is_popular' => false,
            'is_recommended' => false,
            'sort_order' => 1,
            'slug' => 'free-trial',
            'availability' => 'public',
            'tags' => ['free', 'trial', 'no_commitment'],
            'metadata' => [
                'target_audience' => 'trial_users',
                'conversion_focus' => true,
                'comparison_highlight' => 'Try premium features free',
                'badge' => 'FREE TRIAL',
                'auto_convert_to' => 'premium-monthly'
            ]
        ]);
    }
}