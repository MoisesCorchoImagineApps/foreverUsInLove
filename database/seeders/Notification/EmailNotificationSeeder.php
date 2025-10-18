<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EmailNotification;
use App\Models\Notification;
use Carbon\Carbon;

class EmailNotificationSeeder extends Seeder
{
    /**
     * Run the database seeds for EmailNotification domain.
     * Creates comprehensive email notification data with realistic patterns,
     * multiple providers, campaign tracking, and engagement analytics.
     */
    public function run(): void
    {
        $this->command->info('🔧 Creating EmailNotification records...');
        
        // Clear existing records for fresh seed
        EmailNotification::truncate();
        
        // Get all existing notifications for relationship mapping
        $notifications = Notification::all();
        
        if ($notifications->isEmpty()) {
            $this->command->warn('⚠️  No Notification records found. Run NotificationSeeder first.');
            return;
        }
        
        $startTime = microtime(true);
        $totalRecords = 0;
        
        // 1. TRANSACTIONAL EMAILS (High Priority - Dating App Core Functions)
        $this->command->info('📧 Creating transactional email notifications...');
        $transactionalEmails = $this->createTransactionalEmails($notifications);
        $totalRecords += count($transactionalEmails);
        
        // 2. MARKETING CAMPAIGNS (Dating App Engagement)  
        $this->command->info('📈 Creating marketing campaign emails...');
        $marketingEmails = $this->createMarketingCampaigns($notifications);
        $totalRecords += count($marketingEmails);
        
        // 3. WELCOME & ONBOARDING SERIES
        $this->command->info('👋 Creating welcome and onboarding emails...');
        $welcomeEmails = $this->createWelcomeOnboardingSeries($notifications);
        $totalRecords += count($welcomeEmails);
        
        // 4. DATING ACTIVITY EMAILS (Match notifications, messages, etc)
        $this->command->info('💕 Creating dating activity emails...');
        $datingEmails = $this->createDatingActivityEmails($notifications);
        $totalRecords += count($datingEmails);
        
        // 5. SYSTEM & SECURITY EMAILS
        $this->command->info('🔒 Creating system and security emails...');
        $systemEmails = $this->createSystemSecurityEmails($notifications);
        $totalRecords += count($systemEmails);
        
        // 6. BOUNCED & FAILED EMAILS (Testing deliverability issues)
        $this->command->info('⚠️ Creating bounced and failed email records...');
        $failedEmails = $this->createFailedBouncedEmails($notifications);
        $totalRecords += count($failedEmails);
        
        // 7. A/B TEST CAMPAIGNS (Dating app optimization)
        $this->command->info('🧪 Creating A/B test campaign emails...');
        $abTestEmails = $this->createABTestCampaigns($notifications);
        $totalRecords += count($abTestEmails);
        
        // 8. RE-ENGAGEMENT CAMPAIGNS (Win back inactive users)
        $this->command->info('🔄 Creating re-engagement campaign emails...');
        $reengagementEmails = $this->createReengagementCampaigns($notifications);
        $totalRecords += count($reengagementEmails);
        
        // Calculate performance metrics
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        $this->displaySeedingStats($totalRecords, $executionTime);
    }
    
    /**
     * Create transactional emails for core dating app functions
     */
    private function createTransactionalEmails($notifications): array
    {
        $transactionalTypes = [
            'new_match', 'new_message', 'profile_view', 'like_received',
            'super_like_received', 'subscription_confirmed', 'payment_receipt',
            'password_reset', 'email_verification', 'account_suspended'
        ];
        
        $providers = ['sendgrid', 'mailgun', 'ses', 'postmark'];
        $templates = [
            'new_match' => 'dating/new-match-celebration',
            'new_message' => 'dating/new-message-alert',
            'profile_view' => 'dating/profile-view-notification',
            'like_received' => 'dating/like-received',
            'super_like_received' => 'dating/super-like-special',
            'subscription_confirmed' => 'billing/subscription-welcome',
            'payment_receipt' => 'billing/payment-receipt',
            'password_reset' => 'auth/password-reset-secure',
            'email_verification' => 'auth/verify-email',
            'account_suspended' => 'moderation/account-suspended'
        ];
        
        $emails = [];
        
        foreach ($transactionalTypes as $type) {
            // Create multiple variations for each type
            for ($i = 0; $i < rand(15, 25); $i++) {
                $notification = $notifications->where('type', $type)->random();
                
                $sentAt = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 24));
                $openedAt = null;
                $clickedAt = null;
                
                // High open rates for transactional emails (75-95%)
                if (rand(1, 100) <= 85) {
                    $openedAt = $sentAt->copy()->addMinutes(rand(1, 180));
                    
                    // Good click rates for opened emails (25-45%)
                    if (rand(1, 100) <= 35) {
                        $clickedAt = $openedAt->copy()->addMinutes(rand(1, 30));
                    }
                }
                
                $emails[] = EmailNotification::factory()->create([
                    'notification_id' => $notification->id,
                    'email_address' => fake()->unique()->safeEmail(),
                    'subject' => $this->generateTransactionalSubject($type),
                    'provider' => fake()->randomElement($providers),
                    'template_id' => $templates[$type] ?? 'default/notification',
                    'status' => $openedAt ? 'delivered' : fake()->randomElement(['delivered', 'pending']),
                    'sent_at' => $sentAt,
                    'opened_at' => $openedAt,
                    'clicked_at' => $clickedAt,
                    'provider_message_id' => 'txn_' . fake('en_US')->uuid(),
                    'metadata' => [
                        'campaign_type' => 'transactional',
                        'priority' => 'high',
                        'automated' => true,
                        'user_segment' => fake()->randomElement(['premium', 'free', 'trial']),
                        'trigger_event' => $type
                    ]
                ]);
            }
        }
        
        return $emails;
    }
    
    /**
     * Create marketing campaign emails for user engagement
     */
    private function createMarketingCampaigns($notifications): array
    {
        $campaignTypes = [
            'weekly_matches_digest', 'premium_upgrade_offer', 'dating_tips_newsletter',
            'success_stories', 'app_feature_announcement', 'seasonal_promotion',
            'location_based_events', 'profile_optimization_tips'
        ];
        
        $campaigns = [
            'summer_love_2024' => 'Find Your Summer Romance 🌅',
            'valentine_special' => 'Valentine\'s Day Special Matches 💕',
            'premium_trial' => 'Try Premium Free for 7 Days',
            'weekly_digest' => 'Your Weekly Dating Recap',
            'feature_update' => 'New Features to Help You Connect',
            'success_stories' => 'Real Love Stories from Our Community',
            'dating_tips' => 'Expert Dating Tips & Advice',
            'local_events' => 'Singles Events Near You'
        ];
        
        $emails = [];
        
        foreach ($campaignTypes as $type) {
            // Create campaign batches
            for ($batch = 0; $batch < rand(3, 6); $batch++) {
                $campaignName = fake()->randomElement(array_keys($campaigns));
                $batchSize = rand(50, 150);
                
                for ($i = 0; $i < $batchSize; $i++) {
                    $notification = $notifications->where('type', 'marketing')->random();
                    
                    $sentAt = Carbon::now()->subDays(rand(1, 45))->subHours(rand(0, 24));
                    $openedAt = null;
                    $clickedAt = null;
                    
                    // Lower open rates for marketing (35-65%)
                    if (rand(1, 100) <= 50) {
                        $openedAt = $sentAt->copy()->addHours(rand(1, 48));
                        
                        // Moderate click rates (15-30%)
                        if (rand(1, 100) <= 22) {
                            $clickedAt = $openedAt->copy()->addMinutes(rand(5, 120));
                        }
                    }
                    
                    $emails[] = EmailNotification::factory()->create([
                        'notification_id' => $notification->id,
                        'email_address' => fake()->unique()->safeEmail(),
                        'subject' => $campaigns[$campaignName],
                        'provider' => fake()->randomElement(['sendgrid', 'mailgun']),
                        'template_id' => "marketing/{$campaignName}",
                        'campaign_id' => "camp_" . $campaignName . "_" . date('Y_m', $sentAt->timestamp),
                        'status' => 'delivered',
                        'sent_at' => $sentAt,
                        'opened_at' => $openedAt,
                        'clicked_at' => $clickedAt,
                        'provider_message_id' => 'mkt_' . fake('en_US')->uuid(),
                        'metadata' => [
                            'campaign_type' => 'marketing',
                            'campaign_name' => $campaignName,
                            'batch_id' => "batch_{$batch}",
                            'segment' => fake()->randomElement(['engaged_users', 'inactive_users', 'premium_users', 'free_users']),
                            'ab_test_variant' => fake()->randomElement(['A', 'B', null, null]),
                            'send_time_optimization' => fake()->boolean(30)
                        ]
                    ]);
                }
            }
        }
        
        return $emails;
    }
    
    /**
     * Create welcome and onboarding email series
     */
    private function createWelcomeOnboardingSeries($notifications): array
    {
        $onboardingSequence = [
            ['day' => 0, 'type' => 'welcome', 'subject' => 'Welcome to ForeverUsInLove! 💕'],
            ['day' => 1, 'type' => 'profile_setup', 'subject' => 'Complete Your Profile for Better Matches'],
            ['day' => 3, 'type' => 'first_tips', 'subject' => '5 Tips for Dating Success'],
            ['day' => 7, 'type' => 'engagement_check', 'subject' => 'How\'s Your First Week Going?'],
            ['day' => 14, 'type' => 'premium_intro', 'subject' => 'Unlock More Matches with Premium'],
            ['day' => 30, 'type' => 'success_metrics', 'subject' => 'Your First Month Dating Stats']
        ];
        
        $emails = [];
        
        // Create onboarding series for new users
        for ($user = 0; $user < 100; $user++) {
            $userEmail = fake()->unique()->safeEmail();
            $signupDate = Carbon::now()->subDays(rand(0, 60));
            
            foreach ($onboardingSequence as $step) {
                $notification = $notifications->where('type', 'onboarding')->random();
                
                $scheduledDate = $signupDate->copy()->addDays($step['day']);
                
                // Only create if scheduled date is in the past
                if ($scheduledDate->isPast()) {
                    $sentAt = $scheduledDate->addHours(rand(10, 18)); // Send during business hours
                    $openedAt = null;
                    $clickedAt = null;
                    
                    // High open rates for welcome series (65-85%)
                    $openRate = $step['day'] === 0 ? 90 : (85 - ($step['day'] * 2)); // Declining over time
                    
                    if (rand(1, 100) <= $openRate) {
                        $openedAt = $sentAt->copy()->addMinutes(rand(10, 300));
                        
                        // Good click rates (30-50%)
                        if (rand(1, 100) <= 40) {
                            $clickedAt = $openedAt->copy()->addMinutes(rand(1, 60));
                        }
                    }
                    
                    $emails[] = EmailNotification::factory()->create([
                        'notification_id' => $notification->id,
                        'email_address' => $userEmail,
                        'subject' => $step['subject'],
                        'provider' => 'sendgrid', // Consistent provider for series
                        'template_id' => "onboarding/{$step['type']}",
                        'campaign_id' => 'onboarding_series_2024',
                        'status' => 'delivered',
                        'sent_at' => $sentAt,
                        'opened_at' => $openedAt,
                        'clicked_at' => $clickedAt,
                        'provider_message_id' => 'onb_' . fake('en_US')->uuid(),
                        'metadata' => [
                            'campaign_type' => 'onboarding',
                            'sequence_step' => $step['day'],
                            'sequence_type' => $step['type'],
                            'user_cohort' => $signupDate->format('Y-m'),
                            'automation_id' => 'welcome_series_v2'
                        ]
                    ]);
                }
            }
        }
        
        return $emails;
    }
    
    /**
     * Create dating activity specific emails
     */
    private function createDatingActivityEmails($notifications): array
    {
        $datingActivities = [
            'new_match_celebration',
            'conversation_starter_suggestions',
            'date_planning_assistance',
            'match_expiring_soon',
            'conversation_revival',
            'date_feedback_request',
            'safety_reminder',
            'profile_boost_results'
        ];
        
        $emails = [];
        
        foreach ($datingActivities as $activity) {
            for ($i = 0; $i < rand(20, 40); $i++) {
                $notification = $notifications->where('type', 'dating_activity')->random();
                
                $sentAt = Carbon::now()->subDays(rand(0, 21))->subHours(rand(0, 24));
                $openedAt = null;
                $clickedAt = null;
                
                // Moderate to high open rates (55-75%)
                if (rand(1, 100) <= 65) {
                    $openedAt = $sentAt->copy()->addMinutes(rand(5, 240));
                    
                    // Good click rates for dating content (35-55%)
                    if (rand(1, 100) <= 45) {
                        $clickedAt = $openedAt->copy()->addMinutes(rand(1, 45));
                    }
                }
                
                $emails[] = EmailNotification::factory()->create([
                    'notification_id' => $notification->id,
                    'email_address' => fake()->unique()->safeEmail(),
                    'subject' => $this->generateDatingActivitySubject($activity),
                    'provider' => fake()->randomElement(['sendgrid', 'mailgun', 'postmark']),
                    'template_id' => "dating/{$activity}",
                    'status' => 'delivered',
                    'sent_at' => $sentAt,
                    'opened_at' => $openedAt,
                    'clicked_at' => $clickedAt,
                    'provider_message_id' => 'dat_' . fake('en_US')->uuid(),
                    'metadata' => [
                        'campaign_type' => 'dating_activity',
                        'activity_type' => $activity,
                        'personalization_level' => fake()->randomElement(['high', 'medium', 'low']),
                        'trigger_based' => true,
                        'urgency_level' => fake()->randomElement(['low', 'medium', 'high'])
                    ]
                ]);
            }
        }
        
        return $emails;
    }
    
    /**
     * Create system and security emails
     */
    private function createSystemSecurityEmails($notifications): array
    {
        $systemTypes = [
            'security_alert',
            'login_attempt',
            'password_changed',
            'email_changed',
            'subscription_renewal',
            'payment_failed',
            'account_verification',
            'privacy_policy_update',
            'terms_update',
            'data_export_ready'
        ];
        
        $emails = [];
        
        foreach ($systemTypes as $type) {
            for ($i = 0; $i < rand(8, 20); $i++) {
                $notification = $notifications->where('type', 'system')->random();
                
                $sentAt = Carbon::now()->subDays(rand(0, 90))->subHours(rand(0, 24));
                $openedAt = null;
                $clickedAt = null;
                
                // Very high open rates for security (80-95%)
                if (rand(1, 100) <= 88) {
                    $openedAt = $sentAt->copy()->addMinutes(rand(1, 60));
                    
                    // High click rates for security (40-70%)
                    if (rand(1, 100) <= 55) {
                        $clickedAt = $openedAt->copy()->addMinutes(rand(1, 20));
                    }
                }
                
                $emails[] = EmailNotification::factory()->create([
                    'notification_id' => $notification->id,
                    'email_address' => fake()->unique()->safeEmail(),
                    'subject' => $this->generateSystemSecuritySubject($type),
                    'provider' => 'ses', // AWS SES for system emails
                    'template_id' => "system/{$type}",
                    'status' => 'delivered',
                    'sent_at' => $sentAt,
                    'opened_at' => $openedAt,
                    'clicked_at' => $clickedAt,
                    'provider_message_id' => 'sys_' . fake('en_US')->uuid(),
                    'metadata' => [
                        'campaign_type' => 'system',
                        'security_level' => fake()->randomElement(['critical', 'high', 'medium', 'low']),
                        'automated' => true,
                        'compliance_required' => fake()->boolean(40),
                        'notification_type' => $type
                    ]
                ]);
            }
        }
        
        return $emails;
    }
    
    /**
     * Create bounced and failed email records for testing
     */
    private function createFailedBouncedEmails($notifications): array
    {
        $bounceTypes = ['hard_bounce', 'soft_bounce', 'complaint', 'suppressed'];
        $failureReasons = [
            'invalid_email',
            'mailbox_full',
            'domain_not_found',
            'spam_complaint',
            'unsubscribed',
            'blocked_by_provider',
            'temporary_failure',
            'reputation_issue'
        ];
        
        $emails = [];
        
        // Create bounced emails
        for ($i = 0; $i < 50; $i++) {
            $notification = $notifications->random();
            $bounceType = fake()->randomElement($bounceTypes);
            
            $sentAt = Carbon::now()->subDays(rand(1, 30))->subHours(rand(0, 24));
            $bouncedAt = $sentAt->copy()->addMinutes(rand(1, 60));
            
            $emails[] = EmailNotification::factory()->create([
                'notification_id' => $notification->id,
                'email_address' => fake()->unique()->safeEmail(),
                'subject' => fake()->sentence(6),
                'provider' => fake()->randomElement(['sendgrid', 'mailgun', 'ses']),
                'template_id' => 'default/notification',
                'status' => 'bounced',
                'sent_at' => $sentAt,
                'bounced_at' => $bouncedAt,
                'provider_message_id' => 'bnc_' . fake('en_US')->uuid(),
                'bounce_type' => $bounceType,
                'bounce_reason' => fake()->randomElement($failureReasons),
                'metadata' => [
                    'bounce_category' => $bounceType,
                    'retry_attempts' => rand(0, 3),
                    'suppression_list' => fake()->boolean(60)
                ]
            ]);
        }
        
        // Create failed emails
        for ($i = 0; $i < 30; $i++) {
            $notification = $notifications->random();
            
            $emails[] = EmailNotification::factory()->create([
                'notification_id' => $notification->id,
                'email_address' => fake()->unique()->safeEmail(),
                'subject' => fake()->sentence(6),
                'provider' => fake()->randomElement(['sendgrid', 'mailgun', 'ses']),
                'template_id' => 'default/notification',
                'status' => 'failed',
                'provider_message_id' => 'fld_' . fake('en_US')->uuid(),
                'bounce_reason' => fake()->randomElement($failureReasons),
                'metadata' => [
                    'failure_type' => 'send_failure',
                    'error_code' => fake()->randomElement(['SMTP_500', 'SMTP_550', 'SMTP_421', 'API_ERROR']),
                    'retry_scheduled' => fake()->boolean(30)
                ]
            ]);
        }
        
        return $emails;
    }
    
    /**
     * Create A/B test campaign emails
     */
    private function createABTestCampaigns($notifications): array
    {
        $testCampaigns = [
            'subject_line_test_premium_offer',
            'send_time_optimization_test',
            'personalization_level_test', 
            'cta_button_color_test',
            'email_length_test',
            'image_vs_text_test'
        ];
        
        $emails = [];
        
        foreach ($testCampaigns as $campaign) {
            // Create A and B variants
            foreach (['A', 'B'] as $variant) {
                for ($i = 0; $i < rand(25, 50); $i++) {
                    $notification = $notifications->where('type', 'marketing')->random();
                    
                    $sentAt = Carbon::now()->subDays(rand(1, 14))->subHours(rand(0, 24));
                    $openedAt = null;
                    $clickedAt = null;
                    
                    // Simulate A/B test results
                    $openRate = $variant === 'A' ? 45 : 52; // B performing better
                    $clickRate = $variant === 'A' ? 20 : 28; // B performing better
                    
                    if (rand(1, 100) <= $openRate) {
                        $openedAt = $sentAt->copy()->addMinutes(rand(10, 480));
                        
                        if (rand(1, 100) <= $clickRate) {
                            $clickedAt = $openedAt->copy()->addMinutes(rand(1, 30));
                        }
                    }
                    
                    $emails[] = EmailNotification::factory()->create([
                        'notification_id' => $notification->id,
                        'email_address' => fake()->unique()->safeEmail(),
                        'subject' => $this->generateABTestSubject($campaign, $variant),
                        'provider' => 'sendgrid',
                        'template_id' => "ab_test/{$campaign}_{$variant}",
                        'campaign_id' => "ab_test_{$campaign}_2024",
                        'status' => 'delivered',
                        'sent_at' => $sentAt,
                        'opened_at' => $openedAt,
                        'clicked_at' => $clickedAt,
                        'provider_message_id' => 'abt_' . fake('en_US')->uuid(),
                        'metadata' => [
                            'campaign_type' => 'ab_test',
                            'test_name' => $campaign,
                            'variant' => $variant,
                            'test_hypothesis' => $this->getABTestHypothesis($campaign),
                            'statistical_significance' => fake()->boolean(70)
                        ]
                    ]);
                }
            }
        }
        
        return $emails;
    }
    
    /**
     * Create re-engagement campaign emails
     */
    private function createReengagementCampaigns($notifications): array
    {
        $reengagementTypes = [
            'we_miss_you',
            'whats_new_since_you_left',
            'special_comeback_offer',
            'your_matches_are_waiting',
            'last_chance_before_deletion',
            'success_stories_inspiration'
        ];
        
        $emails = [];
        
        foreach ($reengagementTypes as $type) {
            for ($i = 0; $i < rand(15, 30); $i++) {
                $notification = $notifications->where('type', 'reengagement')->random();
                
                $sentAt = Carbon::now()->subDays(rand(1, 60))->subHours(rand(0, 24));
                $openedAt = null;
                $clickedAt = null;
                
                // Lower open rates for inactive users (25-45%)
                if (rand(1, 100) <= 35) {
                    $openedAt = $sentAt->copy()->addHours(rand(1, 72));
                    
                    // High click rates when they do open (40-60%)
                    if (rand(1, 100) <= 50) {
                        $clickedAt = $openedAt->copy()->addMinutes(rand(2, 60));
                    }
                }
                
                $emails[] = EmailNotification::factory()->create([
                    'notification_id' => $notification->id,
                    'email_address' => fake()->unique()->safeEmail(),
                    'subject' => $this->generateReengagementSubject($type),
                    'provider' => fake()->randomElement(['sendgrid', 'mailgun']),
                    'template_id' => "reengagement/{$type}",
                    'campaign_id' => "reengagement_q4_2024",
                    'status' => 'delivered',
                    'sent_at' => $sentAt,
                    'opened_at' => $openedAt,
                    'clicked_at' => $clickedAt,
                    'provider_message_id' => 'ree_' . fake('en_US')->uuid(),
                    'metadata' => [
                        'campaign_type' => 'reengagement',
                        'inactive_days' => rand(30, 180),
                        'last_activity' => fake()->randomElement(['message', 'match', 'profile_view', 'login']),
                        'incentive_offered' => fake()->boolean(70),
                        'urgency_level' => fake()->randomElement(['low', 'medium', 'high'])
                    ]
                ]);
            }
        }
        
        return $emails;
    }
    
    // Helper methods for generating realistic subjects
    private function generateTransactionalSubject($type): string
    {
        $subjects = [
            'new_match' => ['🎉 You have a new match!', 'Someone special is waiting for you!', 'Your perfect match is here! 💕'],
            'new_message' => ['💌 New message from your match', 'You\'ve got mail! New message waiting', 'Someone sent you a message 📩'],
            'profile_view' => ['👀 Someone viewed your profile!', 'You caught someone\'s eye today', 'Profile view notification'],
            'like_received' => ['❤️ Someone liked your profile!', 'You got a like! Check it out', 'New like on your profile'],
            'super_like_received' => ['⭐ You received a Super Like!', 'WOW! Someone Super Liked you!', 'Super Like notification 🌟'],
            'subscription_confirmed' => ['Welcome to Premium! 🎊', 'Your Premium subscription is active', 'Premium features unlocked!'],
            'payment_receipt' => ['Payment Receipt - ForeverUsInLove', 'Your payment was successful', 'Receipt for your Premium subscription'],
            'password_reset' => ['🔒 Password Reset Request', 'Reset your ForeverUsInLove password', 'Security: Password reset requested'],
            'email_verification' => ['✅ Please verify your email address', 'Confirm your email - ForeverUsInLove', 'Email verification required'],
            'account_suspended' => ['⚠️ Account Security Notice', 'Important: Account temporarily suspended', 'Action required on your account']
        ];
        
        return fake()->randomElement($subjects[$type] ?? ['Notification from ForeverUsInLove']);
    }
    
    private function generateDatingActivitySubject($activity): string
    {
        $subjects = [
            'new_match_celebration' => ['🎉 Celebrate your new match!', 'Your love story begins now!', 'Time to make the first move! 💕'],
            'conversation_starter_suggestions' => ['💬 Perfect conversation starters for you', 'Break the ice with these tips!', 'Start amazing conversations'],
            'date_planning_assistance' => ['📅 Plan the perfect first date', 'Date ideas just for you two', 'Make your date unforgettable'],
            'match_expiring_soon' => ['⏰ Your match expires soon!', 'Don\'t lose this connection!', 'Act fast - match expiring!'],
            'conversation_revival' => ['💌 Revive your conversation', 'Get the chat going again!', 'Reconnect with your match'],
            'date_feedback_request' => ['📝 How was your date?', 'Tell us about your experience', 'Rate your recent date'],
            'safety_reminder' => ['🛡️ Stay safe while dating', 'Dating safety tips', 'Your safety is our priority'],
            'profile_boost_results' => ['📈 Your profile boost results!', 'See who viewed your profile', 'Boost performance report']
        ];
        
        return fake()->randomElement($subjects[$activity] ?? ['Dating update from ForeverUsInLove']);
    }
    
    private function generateSystemSecuritySubject($type): string
    {
        $subjects = [
            'security_alert' => ['🚨 Security Alert - Action Required', 'Unusual activity detected', 'Important security notice'],
            'login_attempt' => ['🔐 New login to your account', 'Login notification', 'Account access alert'],
            'password_changed' => ['✅ Password successfully changed', 'Password update confirmation', 'Security: Password modified'],
            'email_changed' => ['📧 Email address updated', 'Email change confirmation', 'Account email modified'],
            'subscription_renewal' => ['🔄 Subscription renewed successfully', 'Auto-renewal confirmation', 'Premium subscription continued'],
            'payment_failed' => ['⚠️ Payment method declined', 'Update payment information', 'Subscription payment failed'],
            'account_verification' => ['✅ Account verification complete', 'Welcome! Account verified', 'Verification successful'],
            'privacy_policy_update' => ['📋 Privacy Policy Updated', 'Important policy changes', 'Review our updated privacy policy'],
            'terms_update' => ['📝 Terms of Service Updated', 'Updated terms and conditions', 'Please review our new terms'],
            'data_export_ready' => ['📦 Your data export is ready', 'Download your personal data', 'Data export completed']
        ];
        
        return fake()->randomElement($subjects[$type] ?? ['ForeverUsInLove Account Notice']);
    }
    
    private function generateABTestSubject($campaign, $variant): string
    {
        $subjects = [
            'subject_line_test_premium_offer' => [
                'A' => 'Upgrade to Premium Today',
                'B' => '🌟 Unlock Premium Features - Limited Time!'
            ],
            'send_time_optimization_test' => [
                'A' => 'Your weekly match summary',
                'B' => 'Your weekly match summary'
            ],
            'personalization_level_test' => [
                'A' => 'New matches available',
                'B' => 'Sarah, 3 perfect matches are waiting for you!'
            ],
            'cta_button_color_test' => [
                'A' => 'Don\'t miss out on these matches',
                'B' => 'Don\'t miss out on these matches'
            ],
            'email_length_test' => [
                'A' => 'Quick update: New matches',
                'B' => 'Your personalized dating insights and new match recommendations'
            ],
            'image_vs_text_test' => [
                'A' => 'Meet your potential soulmates',
                'B' => 'Meet your potential soulmates'
            ]
        ];
        
        return $subjects[$campaign][$variant] ?? "Test Email Variant {$variant}";
    }
    
    private function generateReengagementSubject($type): string
    {
        $subjects = [
            'we_miss_you' => ['We miss you! 💔', 'Come back to love', 'Your matches are waiting...'],
            'whats_new_since_you_left' => ['Look what you\'ve been missing!', 'Amazing updates while you were away', 'New features just for you!'],
            'special_comeback_offer' => ['🎁 Special offer just for you!', 'Welcome back bonus inside!', 'Exclusive comeback deal'],
            'your_matches_are_waiting' => ['💕 Your matches miss you too', 'Love is still looking for you', 'Don\'t keep love waiting'],
            'last_chance_before_deletion' => ['⚠️ Account deletion in 7 days', 'Final notice: Account inactive', 'Last chance to save your account'],
            'success_stories_inspiration' => ['❤️ Love stories to inspire you', 'Real couples, real love', 'Your love story could be next']
        ];
        
        return fake()->randomElement($subjects[$type] ?? ['Come back to ForeverUsInLove']);
    }
    
    private function getABTestHypothesis($campaign): string
    {
        $hypotheses = [
            'subject_line_test_premium_offer' => 'Emoji and urgency in subject lines increase open rates',
            'send_time_optimization_test' => 'Evening sends (6-8 PM) perform better than morning sends',
            'personalization_level_test' => 'Personalized subject lines increase engagement rates',
            'cta_button_color_test' => 'Red CTA buttons outperform blue buttons',
            'email_length_test' => 'Shorter emails have higher click-through rates', 
            'image_vs_text_test' => 'Image-heavy emails increase engagement over text-only'
        ];
        
        return $hypotheses[$campaign] ?? 'Testing email performance optimization';
    }
    
    /**
     * Display comprehensive seeding statistics
     */
    private function displaySeedingStats($totalRecords, $executionTime): void
    {
        $this->command->newLine();
        $this->command->info('📊 ===== EMAIL NOTIFICATION SEEDING COMPLETED =====');
        
        // Calculate breakdown
        $delivered = EmailNotification::where('status', 'delivered')->count();
        $bounced = EmailNotification::where('status', 'bounced')->count();
        $failed = EmailNotification::where('status', 'failed')->count();
        $opened = EmailNotification::whereNotNull('opened_at')->count();
        $clicked = EmailNotification::whereNotNull('clicked_at')->count();
        
        // Calculate rates
        $openRate = $delivered > 0 ? round(($opened / $delivered) * 100, 1) : 0;
        $clickRate = $opened > 0 ? round(($clicked / $opened) * 100, 1) : 0;
        $bounceRate = $totalRecords > 0 ? round(($bounced / $totalRecords) * 100, 1) : 0;
        
        $this->command->table(
            ['Metric', 'Value', 'Percentage'],
            [
                ['Total Email Records', number_format($totalRecords), '100%'],
                ['Delivered', number_format($delivered), round(($delivered/$totalRecords)*100, 1) . '%'],
                ['Opened', number_format($opened), $openRate . '%'],
                ['Clicked', number_format($clicked), $clickRate . '%'],
                ['Bounced', number_format($bounced), $bounceRate . '%'],
                ['Failed', number_format($failed), round(($failed/$totalRecords)*100, 1) . '%'],
            ]
        );
        
        // Provider breakdown
        $providers = EmailNotification::selectRaw('provider, COUNT(*) as count')
            ->groupBy('provider')
            ->orderBy('count', 'desc')
            ->get();
            
        $this->command->info('📤 Provider Distribution:');
        foreach ($providers as $provider) {
            $percentage = round(($provider->count / $totalRecords) * 100, 1);
            $this->command->line("   • {$provider->provider}: {$provider->count} ({$percentage}%)");
        }
        
        // Campaign type breakdown
        $campaignTypes = EmailNotification::selectRaw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.campaign_type")) as campaign_type, COUNT(*) as count')
            ->whereRaw('JSON_EXTRACT(metadata, "$.campaign_type") IS NOT NULL')
            ->groupBy('campaign_type')
            ->orderBy('count', 'desc')
            ->get();
            
        $this->command->info('📈 Campaign Type Distribution:');
        foreach ($campaignTypes as $type) {
            $percentage = round(($type->count / $totalRecords) * 100, 1);
            $this->command->line("   • {$type->campaign_type}: {$type->count} ({$percentage}%)");
        }
        
        $this->command->newLine();
        $this->command->info("⚡ Execution time: {$executionTime}s");
        $this->command->info("🎯 Performance: " . number_format($totalRecords / $executionTime, 0) . " records/second");
        $this->command->info('✅ EmailNotification seeding completed successfully!');
    }
}