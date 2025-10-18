<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PushNotification;
use App\Models\Notification;
use Carbon\Carbon;

class PushNotificationSeeder extends Seeder
{
    /**
     * Run the database seeds for PushNotification domain.
     * Creates comprehensive push notification data with multi-platform support,
     * rich media, interactive features, and device management.
     */
    public function run(): void
    {
        $this->command->info('📱 Creating PushNotification records...');
        
        // Clear existing records for fresh seed
        PushNotification::truncate();
        
        // Get all existing notifications for relationship mapping
        $notifications = Notification::all();
        
        if ($notifications->isEmpty()) {
            $this->command->warn('⚠️  No Notification records found. Run NotificationSeeder first.');
            return;
        }
        
        $startTime = microtime(true);
        $totalRecords = 0;
        
        // 1. DATING ACTIVITY PUSH NOTIFICATIONS (Core app functionality)
        $this->command->info('💕 Creating dating activity push notifications...');
        $datingPushes = $this->createDatingActivityPushes($notifications);
        $totalRecords += count($datingPushes);
        
        // 2. REAL-TIME MESSAGING NOTIFICATIONS
        $this->command->info('💬 Creating real-time messaging push notifications...');
        $messagingPushes = $this->createMessagingPushes($notifications);
        $totalRecords += count($messagingPushes);
        
        // 3. SYSTEM & SECURITY NOTIFICATIONS
        $this->command->info('🔒 Creating system and security push notifications...');
        $systemPushes = $this->createSystemSecurityPushes($notifications);
        $totalRecords += count($systemPushes);
        
        // 4. MARKETING & ENGAGEMENT CAMPAIGNS
        $this->command->info('📈 Creating marketing campaign push notifications...');
        $marketingPushes = $this->createMarketingCampaignPushes($notifications);
        $totalRecords += count($marketingPushes);
        
        // 5. RICH MEDIA NOTIFICATIONS (Images, videos, interactive)
        $this->command->info('🎨 Creating rich media push notifications...');
        $richMediaPushes = $this->createRichMediaPushes($notifications);
        $totalRecords += count($richMediaPushes);
        
        // 6. INTERACTIVE NOTIFICATIONS (Action buttons, quick replies)
        $this->command->info('🎮 Creating interactive push notifications...');
        $interactivePushes = $this->createInteractivePushes($notifications);
        $totalRecords += count($interactivePushes);
        
        // 7. LOCATION-BASED NOTIFICATIONS
        $this->command->info('📍 Creating location-based push notifications...');
        $locationPushes = $this->createLocationBasedPushes($notifications);
        $totalRecords += count($locationPushes);
        
        // 8. CROSS-PLATFORM CONSISTENCY TESTING
        $this->command->info('🔄 Creating cross-platform push notifications...');
        $crossPlatformPushes = $this->createCrossPlatformPushes($notifications);
        $totalRecords += count($crossPlatformPushes);
        
        // 9. FAILED & EXPIRED NOTIFICATIONS (For testing edge cases)
        $this->command->info('⚠️ Creating failed and expired push notifications...');
        $failedPushes = $this->createFailedExpiredPushes($notifications);
        $totalRecords += count($failedPushes);
        
        // 10. A/B TEST PUSH CAMPAIGNS
        $this->command->info('🧪 Creating A/B test push notifications...');
        $abTestPushes = $this->createABTestPushes($notifications);
        $totalRecords += count($abTestPushes);
        
        // Calculate performance metrics
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        $this->displaySeedingStats($totalRecords, $executionTime);
    }
    
    /**
     * Create dating activity push notifications for core app functions
     */
    private function createDatingActivityPushes($notifications): array
    {
        $datingActivities = [
            'new_match', 'new_like', 'super_like_received', 'profile_view',
            'match_message', 'conversation_starter', 'date_reminder',
            'match_expiring', 'profile_boost_active', 'daily_picks_ready'
        ];
        
        $pushes = [];
        
        foreach ($datingActivities as $activity) {
            for ($i = 0; $i < rand(25, 45); $i++) {
                $notification = $notifications->where('type', $activity)->first() 
                    ?? $notifications->where('type', 'dating_activity')->random();
                
                $platform = fake()->randomElement(['android', 'ios', 'web']);
                $sentAt = Carbon::now()->subMinutes(rand(1, 10080)); // Last week
                
                // High delivery rates for dating activities (90-98%)
                $delivered = fake()->boolean(94);
                $deliveredAt = $delivered ? $sentAt->copy()->addSeconds(rand(1, 30)) : null;
                
                // Good interaction rates (15-35%)
                $clicked = $delivered && fake()->boolean(25);
                $clickedAt = $clicked ? $deliveredAt->copy()->addSeconds(rand(1, 300)) : null;
                
                $pushes[] = PushNotification::factory()->create([
                    'notification_id' => $notification->id,
                    'device_token' => $this->generateDeviceToken($platform),
                    'platform' => $platform,
                    'title' => $this->generateDatingActivityTitle($activity),
                    'body' => $this->generateDatingActivityBody($activity),
                    'status' => $delivered ? 'delivered' : fake()->randomElement(['pending', 'failed']),
                    'sent_at' => $sentAt,
                    'delivered_at' => $deliveredAt,
                    'clicked_at' => $clickedAt,
                    'data_payload' => $this->generateDatingDataPayload($activity),
                    'metadata' => [
                        'notification_type' => 'dating_activity',
                        'activity_type' => $activity,
                        'priority' => fake()->randomElement(['high', 'normal']),
                        'deep_link' => $this->generateDatingDeepLink($activity),
                        'user_segment' => fake()->randomElement(['premium', 'free', 'trial']),
                        'personalized' => fake()->boolean(80)
                    ]
                ]);
            }
        }
        
        return $pushes;
    }
    
    /**
     * Create real-time messaging push notifications
     */
    private function createMessagingPushes($notifications): array
    {
        $messagingTypes = [
            'new_message', 'message_delivered', 'message_read',
            'typing_indicator', 'voice_message', 'photo_message',
            'gif_message', 'video_call_request', 'missed_call'
        ];
        
        $pushes = [];
        
        foreach ($messagingTypes as $type) {
            for ($i = 0; $i < rand(30, 60); $i++) {
                $notification = $notifications->where('type', 'messaging')->first()
                    ?? $notifications->where('type', 'new_message')->random();
                
                $platform = fake()->randomElement(['android', 'ios']);
                $sentAt = Carbon::now()->subMinutes(rand(1, 1440)); // Last 24 hours
                
                // Very high delivery rates for messages (95-99%)
                $delivered = fake()->boolean(97);
                $deliveredAt = $delivered ? $sentAt->copy()->addSeconds(rand(1, 5)) : null;
                
                // High interaction rates for messages (40-70%)
                $clicked = $delivered && fake()->boolean(55);
                $clickedAt = $clicked ? $deliveredAt->copy()->addSeconds(rand(1, 60)) : null;
                
                $pushes[] = PushNotification::factory()->create([
                    'notification_id' => $notification->id,
                    'device_token' => $this->generateDeviceToken($platform),
                    'platform' => $platform,
                    'title' => $this->generateMessagingTitle($type),
                    'body' => $this->generateMessagingBody($type),
                    'status' => $delivered ? 'delivered' : 'failed',
                    'sent_at' => $sentAt,
                    'delivered_at' => $deliveredAt,
                    'clicked_at' => $clickedAt,
                    'badge_count' => rand(1, 15),
                    'sound' => $this->getMessagingSound($type),
                    'data_payload' => $this->generateMessagingDataPayload($type),
                    'metadata' => [
                        'notification_type' => 'messaging',
                        'message_type' => $type,
                        'priority' => 'high',
                        'real_time' => true,
                        'conversation_id' => fake('en_US')->uuid(),
                        'sender_id' => rand(1000, 9999),
                        'encryption_enabled' => fake()->boolean(90)
                    ]
                ]);
            }
        }
        
        return $pushes;
    }
    
    /**
     * Create system and security push notifications
     */
    private function createSystemSecurityPushes($notifications): array
    {
        $systemTypes = [
            'login_alert', 'password_changed', 'new_device_login',
            'suspicious_activity', 'account_verification', 'subscription_expiry',
            'payment_failed', 'app_update_available', 'maintenance_notice'
        ];
        
        $pushes = [];
        
        foreach ($systemTypes as $type) {
            for ($i = 0; $i < rand(8, 20); $i++) {
                $notification = $notifications->where('type', 'system')->first()
                    ?? $notifications->where('type', 'security')->random();
                
                $platform = fake()->randomElement(['android', 'ios', 'web']);
                $sentAt = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 24));
                
                // High delivery rates for system notifications (85-95%)
                $delivered = fake()->boolean(90);
                $deliveredAt = $delivered ? $sentAt->copy()->addSeconds(rand(1, 60)) : null;
                
                // Moderate to high interaction rates (30-60%)
                $clicked = $delivered && fake()->boolean(45);
                $clickedAt = $clicked ? $deliveredAt->copy()->addMinutes(rand(1, 60)) : null;
                
                $pushes[] = PushNotification::factory()->create([
                    'notification_id' => $notification->id,
                    'device_token' => $this->generateDeviceToken($platform),
                    'platform' => $platform,
                    'title' => $this->generateSystemSecurityTitle($type),
                    'body' => $this->generateSystemSecurityBody($type),
                    'status' => $delivered ? 'delivered' : 'failed',
                    'sent_at' => $sentAt,
                    'delivered_at' => $deliveredAt,
                    'clicked_at' => $clickedAt,
                    'sound' => $type === 'suspicious_activity' ? 'alert.caf' : 'default',
                    'data_payload' => $this->generateSystemDataPayload($type),
                    'metadata' => [
                        'notification_type' => 'system',
                        'system_type' => $type,
                        'priority' => $this->getSystemPriority($type),
                        'security_level' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
                        'auto_expire' => fake()->boolean(40),
                        'compliance_required' => fake()->boolean(30)
                    ]
                ]);
            }
        }
        
        return $pushes;
    }
    
    /**
     * Create marketing campaign push notifications
     */
    private function createMarketingCampaignPushes($notifications): array
    {
        $marketingCampaigns = [
            'premium_offer', 'feature_announcement', 'success_story',
            'dating_tips', 'app_rating_request', 'referral_program',
            'seasonal_promotion', 'event_invitation', 'survey_request'
        ];
        
        $pushes = [];
        
        foreach ($marketingCampaigns as $campaign) {
            // Create campaign batches
            for ($batch = 0; $batch < rand(2, 4); $batch++) {
                $batchSize = rand(40, 80);
                
                for ($i = 0; $i < $batchSize; $i++) {
                    $notification = $notifications->where('type', 'marketing')->random();
                    
                    $platform = fake()->randomElement(['android', 'ios', 'web']);
                    $sentAt = Carbon::now()->subDays(rand(1, 60))->subHours(rand(9, 21)); // Business hours
                    
                    // Lower delivery rates for marketing (70-85%)
                    $delivered = fake()->boolean(78);
                    $deliveredAt = $delivered ? $sentAt->copy()->addMinutes(rand(1, 15)) : null;
                    
                    // Lower interaction rates for marketing (8-25%)
                    $clicked = $delivered && fake()->boolean(16);
                    $clickedAt = $clicked ? $deliveredAt->copy()->addMinutes(rand(1, 120)) : null;
                    
                    $pushes[] = PushNotification::factory()->create([
                        'notification_id' => $notification->id,
                        'device_token' => $this->generateDeviceToken($platform),
                        'platform' => $platform,
                        'title' => $this->generateMarketingTitle($campaign),
                        'body' => $this->generateMarketingBody($campaign),
                        'status' => $delivered ? 'delivered' : 'failed',
                        'sent_at' => $sentAt,
                        'delivered_at' => $deliveredAt,
                        'clicked_at' => $clickedAt,
                        'data_payload' => $this->generateMarketingDataPayload($campaign),
                        'metadata' => [
                            'notification_type' => 'marketing',
                            'campaign_name' => $campaign,
                            'batch_id' => "batch_{$batch}",
                            'campaign_id' => "push_camp_" . date('Y_m', $sentAt->timestamp),
                            'user_segment' => fake()->randomElement(['new_users', 'active_users', 'inactive_users', 'premium_users']),
                            'ab_test_variant' => fake()->randomElement(['A', 'B', null, null]),
                            'personalization_level' => fake()->randomElement(['none', 'basic', 'advanced'])
                        ]
                    ]);
                }
            }
        }
        
        return $pushes;
    }
    
    /**
     * Create rich media push notifications
     */
    private function createRichMediaPushes($notifications): array
    {
        $mediaTypes = [
            'match_photo_showcase', 'success_story_video', 'dating_tip_image',
            'event_photo_invite', 'app_feature_demo', 'profile_suggestion_carousel'
        ];
        
        $pushes = [];
        
        foreach ($mediaTypes as $mediaType) {
            for ($i = 0; $i < rand(15, 30); $i++) {
                $notification = $notifications->random();
                
                // Rich media primarily on iOS and Android
                $platform = fake()->randomElement(['android', 'ios']);
                $sentAt = Carbon::now()->subDays(rand(1, 14))->subHours(rand(0, 24));
                
                // Good delivery rates for rich media (80-90%)
                $delivered = fake()->boolean(85);
                $deliveredAt = $delivered ? $sentAt->copy()->addMinutes(rand(1, 10)) : null;
                
                // Higher interaction rates for rich media (25-45%)
                $clicked = $delivered && fake()->boolean(35);
                $clickedAt = $clicked ? $deliveredAt->copy()->addMinutes(rand(1, 30)) : null;
                
                $pushes[] = PushNotification::factory()->create([
                    'notification_id' => $notification->id,
                    'device_token' => $this->generateDeviceToken($platform),
                    'platform' => $platform,
                    'title' => $this->generateRichMediaTitle($mediaType),
                    'body' => $this->generateRichMediaBody($mediaType),
                    'status' => $delivered ? 'delivered' : 'failed',
                    'sent_at' => $sentAt,
                    'delivered_at' => $deliveredAt,
                    'clicked_at' => $clickedAt,
                    'image_url' => $this->generateMediaUrl($mediaType, 'image'),
                    'video_url' => str_contains($mediaType, 'video') ? $this->generateMediaUrl($mediaType, 'video') : null,
                    'data_payload' => $this->generateRichMediaDataPayload($mediaType),
                    'metadata' => [
                        'notification_type' => 'rich_media',
                        'media_type' => $mediaType,
                        'content_type' => str_contains($mediaType, 'video') ? 'video' : 'image',
                        'media_size_mb' => round(fake()->randomFloat(2, 0.5, 5.0), 2),
                        'compression_applied' => fake()->boolean(80),
                        'loading_time_ms' => rand(500, 3000)
                    ]
                ]);
            }
        }
        
        return $pushes;
    }
    
    /**
     * Create interactive push notifications with action buttons
     */
    private function createInteractivePushes($notifications): array
    {
        $interactiveTypes = [
            'match_quick_actions', 'message_quick_reply', 'date_confirmation',
            'profile_rating', 'app_review_request', 'survey_response',
            'event_rsvp', 'subscription_renewal'
        ];
        
        $pushes = [];
        
        foreach ($interactiveTypes as $type) {
            for ($i = 0; $i < rand(20, 35); $i++) {
                $notification = $notifications->random();
                
                // Interactive features mainly on iOS and Android
                $platform = fake()->randomElement(['android', 'ios']);
                $sentAt = Carbon::now()->subDays(rand(1, 21))->subHours(rand(0, 24));
                
                // Good delivery rates (85-92%)
                $delivered = fake()->boolean(88);
                $deliveredAt = $delivered ? $sentAt->copy()->addSeconds(rand(10, 120)) : null;
                
                // High interaction rates for interactive (30-55%)
                $clicked = $delivered && fake()->boolean(42);
                $clickedAt = $clicked ? $deliveredAt->copy()->addMinutes(rand(1, 15)) : null;
                
                $actionButtons = $this->generateActionButtons($type);
                
                $pushes[] = PushNotification::factory()->create([
                    'notification_id' => $notification->id,
                    'device_token' => $this->generateDeviceToken($platform),
                    'platform' => $platform,
                    'title' => $this->generateInteractiveTitle($type),
                    'body' => $this->generateInteractiveBody($type),
                    'status' => $delivered ? 'delivered' : 'failed',
                    'sent_at' => $sentAt,
                    'delivered_at' => $deliveredAt,
                    'clicked_at' => $clickedAt,
                    'action_buttons' => $actionButtons,
                    'data_payload' => $this->generateInteractiveDataPayload($type),
                    'metadata' => [
                        'notification_type' => 'interactive',
                        'interactive_type' => $type,
                        'button_count' => count($actionButtons),
                        'requires_app_open' => fake()->boolean(40),
                        'background_processing' => fake()->boolean(60),
                        'action_tracking_enabled' => true
                    ]
                ]);
            }
        }
        
        return $pushes;
    }
    
    /**
     * Create location-based push notifications
     */
    private function createLocationBasedPushes($notifications): array
    {
        $locationTypes = [
            'nearby_matches', 'local_events', 'popular_dating_spots',
            'safety_check_in', 'date_location_arrived', 'geo_fence_trigger'
        ];
        
        $pushes = [];
        
        foreach ($locationTypes as $type) {
            for ($i = 0; $i < rand(12, 25); $i++) {
                $notification = $notifications->random();
                
                $platform = fake()->randomElement(['android', 'ios']);
                $sentAt = Carbon::now()->subDays(rand(1, 7))->subHours(rand(0, 24));
                
                // Good delivery rates for location-based (82-90%)
                $delivered = fake()->boolean(86);
                $deliveredAt = $delivered ? $sentAt->copy()->addMinutes(rand(1, 5)) : null;
                
                // Moderate interaction rates (20-40%)
                $clicked = $delivered && fake()->boolean(30);
                $clickedAt = $clicked ? $deliveredAt->copy()->addMinutes(rand(1, 45)) : null;
                
                $pushes[] = PushNotification::factory()->create([
                    'notification_id' => $notification->id,
                    'device_token' => $this->generateDeviceToken($platform),
                    'platform' => $platform,
                    'title' => $this->generateLocationTitle($type),
                    'body' => $this->generateLocationBody($type),
                    'status' => $delivered ? 'delivered' : 'failed',
                    'sent_at' => $sentAt,
                    'delivered_at' => $deliveredAt,
                    'clicked_at' => $clickedAt,
                    'data_payload' => $this->generateLocationDataPayload($type),
                    'metadata' => [
                        'notification_type' => 'location_based',
                        'location_type' => $type,
                        'geofence_id' => fake('en_US')->uuid(),
                        'latitude' => fake()->latitude(25, 49), // US bounds
                        'longitude' => fake()->longitude(-125, -66), // US bounds
                        'radius_meters' => rand(100, 5000),
                        'location_accuracy' => rand(5, 50),
                        'privacy_consent' => true
                    ]
                ]);
            }
        }
        
        return $pushes;
    }
    
    /**
     * Create cross-platform consistency test notifications
     */
    private function createCrossPlatformPushes($notifications): array
    {
        $testTypes = [
            'consistency_test_basic', 'consistency_test_rich_media',
            'consistency_test_interactive', 'consistency_test_deep_link'
        ];
        
        $pushes = [];
        
        foreach ($testTypes as $testType) {
            // Create same notification across all platforms
            $baseNotification = $notifications->random();
            $baseContent = [
                'title' => "Cross-Platform Test: {$testType}",  
                'body' => 'Testing notification consistency across platforms',
                'sent_at' => Carbon::now()->subHours(rand(1, 48))
            ];
            
            $platforms = ['android', 'ios', 'web', 'huawei'];
            
            foreach ($platforms as $platform) {
                for ($i = 0; $i < rand(3, 8); $i++) {
                    $sentAt = Carbon::parse($baseContent['sent_at'])->addSeconds($i * 2);
                    
                    // Platform-specific delivery rates
                    $deliveryRates = [
                        'android' => 92,
                        'ios' => 94,
                        'web' => 85,
                        'huawei' => 88
                    ];
                    
                    $delivered = fake()->boolean($deliveryRates[$platform]);
                    $deliveredAt = $delivered ? $sentAt->copy()->addSeconds(rand(1, 30)) : null;
                    
                    $clicked = $delivered && fake()->boolean(25);
                    $clickedAt = $clicked ? $deliveredAt->copy()->addMinutes(rand(1, 10)) : null;
                    
                    $pushes[] = PushNotification::factory()->create([
                        'notification_id' => $baseNotification->id,
                        'device_token' => $this->generateDeviceToken($platform),
                        'platform' => $platform,
                        'title' => $baseContent['title'],
                        'body' => $baseContent['body'],
                        'status' => $delivered ? 'delivered' : 'failed',
                        'sent_at' => $sentAt,
                        'delivered_at' => $deliveredAt,
                        'clicked_at' => $clickedAt,
                        'data_payload' => $this->generateCrossPlatformDataPayload($testType, $platform),
                        'metadata' => [
                            'notification_type' => 'cross_platform_test',
                            'test_type' => $testType,
                            'test_batch_id' => "cross_platform_" . date('Ymd_His', $sentAt->timestamp),
                            'platform_variant' => $platform,
                            'consistency_tracking' => true,
                            'baseline_platform' => 'android'
                        ]
                    ]);
                }
            }
        }
        
        return $pushes;
    }
    
    /**
     * Create failed and expired push notifications for edge case testing
     */
    private function createFailedExpiredPushes($notifications): array
    {
        $failureTypes = [
            'invalid_token', 'app_uninstalled', 'device_unreachable',
            'payload_too_large', 'rate_limit_exceeded', 'expired_certificate',
            'network_timeout', 'platform_rejection'
        ];
        
        $pushes = [];
        
        foreach ($failureTypes as $failureType) {
            for ($i = 0; $i < rand(5, 15); $i++) {
                $notification = $notifications->random();
                $platform = fake()->randomElement(['android', 'ios', 'web', 'huawei']);
                $sentAt = Carbon::now()->subDays(rand(1, 30))->subHours(rand(0, 24));
                
                $pushes[] = PushNotification::factory()->create([
                    'notification_id' => $notification->id,
                    'device_token' => $this->generateDeviceToken($platform),
                    'platform' => $platform,
                    'title' => 'Test Notification - Will Fail',
                    'body' => "Testing failure scenario: {$failureType}",
                    'status' => 'failed',
                    'sent_at' => $sentAt,
                    'error_code' => $this->getFailureErrorCode($failureType),
                    'error_message' => $this->getFailureErrorMessage($failureType),
                    'data_payload' => ['test_failure_type' => $failureType],
                    'metadata' => [
                        'notification_type' => 'failure_test',
                        'failure_type' => $failureType,
                        'retry_attempts' => rand(0, 3),
                        'retry_scheduled' => fake()->boolean(40),
                        'token_invalidated' => in_array($failureType, ['invalid_token', 'app_uninstalled'])
                    ]
                ]);
            }
        }
        
        // Create expired notifications
        for ($i = 0; $i < 20; $i++) {
            $notification = $notifications->random();
            $platform = fake()->randomElement(['android', 'ios']);
            $sentAt = Carbon::now()->subDays(rand(15, 45)); // Old notifications
            
            $pushes[] = PushNotification::factory()->create([
                'notification_id' => $notification->id,
                'device_token' => $this->generateDeviceToken($platform),
                'platform' => $platform,
                'title' => 'Expired Notification Test',
                'body' => 'This notification has expired',
                'status' => 'expired',
                'sent_at' => $sentAt,
                'expired_at' => $sentAt->copy()->addDays(7),
                'data_payload' => ['expiry_test' => true],
                'metadata' => [
                    'notification_type' => 'expiry_test',
                    'ttl_seconds' => 604800, // 7 days
                    'auto_expired' => true
                ]
            ]);
        }
        
        return $pushes;
    }
    
    /**
     * Create A/B test push notifications
     */
    private function createABTestPushes($notifications): array
    {
        $abTests = [
            'emoji_vs_no_emoji',
            'short_vs_long_body',
            'personalized_vs_generic',
            'urgent_vs_casual_tone',
            'question_vs_statement'
        ];
        
        $pushes = [];
        
        foreach ($abTests as $testName) {
            // Create A and B variants
            foreach (['A', 'B'] as $variant) {
                for ($i = 0; $i < rand(30, 50); $i++) {
                    $notification = $notifications->where('type', 'marketing')->random();
                    $platform = fake()->randomElement(['android', 'ios']);
                    $sentAt = Carbon::now()->subDays(rand(1, 14))->subHours(rand(0, 24));
                    
                    // Simulate A/B test performance differences
                    $performance = $this->getABTestPerformance($testName, $variant);
                    
                    $delivered = fake()->boolean($performance['delivery_rate']);
                    $deliveredAt = $delivered ? $sentAt->copy()->addSeconds(rand(1, 60)) : null;
                    
                    $clicked = $delivered && fake()->boolean($performance['click_rate']);
                    $clickedAt = $clicked ? $deliveredAt->copy()->addMinutes(rand(1, 20)) : null;
                    
                    $content = $this->getABTestContent($testName, $variant);
                    
                    $pushes[] = PushNotification::factory()->create([
                        'notification_id' => $notification->id,
                        'device_token' => $this->generateDeviceToken($platform),
                        'platform' => $platform,
                        'title' => $content['title'],
                        'body' => $content['body'],
                        'status' => $delivered ? 'delivered' : 'failed',
                        'sent_at' => $sentAt,
                        'delivered_at' => $deliveredAt,
                        'clicked_at' => $clickedAt,
                        'data_payload' => [
                            'ab_test' => $testName,
                            'variant' => $variant,
                            'test_hypothesis' => $this->getABTestHypothesis($testName)
                        ],
                        'metadata' => [
                            'notification_type' => 'ab_test',
                            'test_name' => $testName,
                            'variant' => $variant,
                            'test_batch_id' => "ab_push_{$testName}_2024",
                            'statistical_significance' => fake()->boolean(60),
                            'confidence_level' => fake()->randomElement([90, 95, 99])
                        ]
                    ]);
                }
            }
        }
        
        return $pushes;
    }
    
    // Helper methods for generating realistic content and data
    
    private function generateDeviceToken($platform): string
    {
        switch ($platform) {
            case 'android':
                return 'fcm_' . fake('en_US')->regexify('[A-Za-z0-9_-]{152}');
            case 'ios':
                return 'apns_' . fake('en_US')->regexify('[a-f0-9]{64}');
            case 'web':
                return 'web_' . fake('en_US')->regexify('[A-Za-z0-9_-]{88}');
            case 'huawei':
                return 'hms_' . fake('en_US')->regexify('[A-Za-z0-9_-]{100}');
            default:
                return 'token_' . fake('en_US')->uuid();
        }
    }
    
    private function generateDatingActivityTitle($activity): string
    {
        $titles = [
            'new_match' => ['🎉 New Match!', 'You have a new match!', 'Someone special is here!'],
            'new_like' => ['❤️ New Like!', 'Someone liked you!', 'You caught someone\'s eye!'],
            'super_like_received' => ['⭐ Super Like!', 'WOW! Super Like received!', 'You got a Super Like!'],
            'profile_view' => ['👀 Profile View', 'Someone viewed your profile', 'New profile visitor'],
            'match_message' => ['💌 New Message', 'Message from your match', 'You have a new message'],
            'conversation_starter' => ['💬 Conversation Starter', 'Break the ice!', 'Perfect conversation starter'],
            'date_reminder' => ['📅 Date Reminder', 'Your date is coming up!', 'Don\'t forget your date'],
            'match_expiring' => ['⏰ Match Expiring', 'Act fast!', 'Match expires soon'],
            'profile_boost_active' => ['🚀 Boost Active', 'Your profile is boosted!', 'Boost in progress'],
            'daily_picks_ready' => ['🎯 Daily Picks', 'New picks are ready!', 'Fresh matches for you']
        ];
        
        return fake()->randomElement($titles[$activity] ?? ['Dating Update']);
    }
    
    private function generateDatingActivityBody($activity): string
    {
        $bodies = [
            'new_match' => 'You and Sarah both liked each other! Start chatting now.',
            'new_like' => 'Someone thinks you\'re amazing! Check out who liked you.',
            'super_like_received' => 'Alex Super Liked your profile. This means they really like you!',
            'profile_view' => 'Jessica viewed your profile 2 minutes ago.',
            'match_message' => '"Hey! How\'s your day going?" - Reply to keep the conversation flowing.',
            'conversation_starter' => 'Ask about their travel photos! People love sharing adventure stories.',
            'date_reminder' => 'Coffee date with Emma tomorrow at 3 PM at Starbucks downtown.',
            'match_expiring' => 'Your match with Mike expires in 4 hours. Send a message!',
            'profile_boost_active' => 'Your profile is being shown to more people. 15x more views expected!',
            'daily_picks_ready' => '5 new carefully selected matches based on your preferences.'
        ];
        
        return $bodies[$activity] ?? 'Check out the latest updates in your dating journey!';
    }
    
    private function generateDatingDataPayload($activity): array
    {
        return [
            'type' => 'dating_activity',
            'activity' => $activity,
            'deep_link' => $this->generateDatingDeepLink($activity),
            'user_id' => rand(1000, 9999),
            'match_id' => fake('en_US')->uuid(),
            'timestamp' => Carbon::now()->toISOString()
        ];
    }
    
    private function generateDatingDeepLink($activity): string
    {
        $deepLinks = [
            'new_match' => 'foreverlove://matches/new',
            'new_like' => 'foreverlove://likes/received',
            'super_like_received' => 'foreverlove://super-likes/received',
            'profile_view' => 'foreverlove://profile/views',
            'match_message' => 'foreverlove://chat/conversations',
            'conversation_starter' => 'foreverlove://chat/starters',
            'date_reminder' => 'foreverlove://dates/upcoming',
            'match_expiring' => 'foreverlove://matches/expiring',
            'profile_boost_active' => 'foreverlove://profile/boost',
            'daily_picks_ready' => 'foreverlove://matches/daily-picks'
        ];
        
        return $deepLinks[$activity] ?? 'foreverlove://home';
    }
    
    private function generateMessagingTitle($type): string
    {
        $names = ['Alex', 'Sarah', 'Mike', 'Emma', 'Chris', 'Lisa', 'David', 'Anna'];
        $name = fake()->randomElement($names);
        
        $titles = [
            'new_message' => "Message from {$name}",
            'message_delivered' => "Message delivered to {$name}",
            'message_read' => "{$name} read your message",
            'typing_indicator' => "{$name} is typing...",
            'voice_message' => "Voice message from {$name}",
            'photo_message' => "{$name} sent a photo",
            'gif_message' => "{$name} sent a GIF",
            'video_call_request' => "{$name} is calling you",
            'missed_call' => "Missed call from {$name}"
        ];
        
        return $titles[$type] ?? 'Message Update';
    }
    
    private function generateMessagingBody($type): string
    {
        $bodies = [
            'new_message' => '"How was your weekend? I tried that restaurant you recommended!"',
            'message_delivered' => 'Your message was delivered successfully.',
            'message_read' => 'Your message has been read.',
            'typing_indicator' => 'Someone is typing a response...',
            'voice_message' => 'Tap to listen to the voice message.',
            'photo_message' => 'Tap to view the photo.',
            'gif_message' => 'Someone sent you a funny GIF!',
            'video_call_request' => 'Incoming video call - Tap to answer',
            'missed_call' => 'You missed a video call. Call back?'
        ];
        
        return $bodies[$type] ?? 'New message activity';
    }
    
    private function generateMessagingDataPayload($type): array
    {
        return [
            'type' => 'messaging',
            'message_type' => $type,
            'conversation_id' => fake('en_US')->uuid(),
            'sender_id' => rand(1000, 9999),
            'deep_link' => 'foreverlove://chat/conversation/' . fake('en_US')->uuid(),
            'encrypted' => true,
            'priority' => 'high'
        ];
    }
    
    private function getMessagingSound($type): string
    {
        $sounds = [
            'new_message' => 'message.caf',
            'voice_message' => 'voice_message.caf', 
            'video_call_request' => 'ringtone.caf',
            'missed_call' => 'missed_call.caf'
        ];
        
        return $sounds[$type] ?? 'default';
    }
    
    private function generateSystemSecurityTitle($type): string
    {
        $titles = [
            'login_alert' => '🔐 New Login Detected',
            'password_changed' => '✅ Password Changed',
            'new_device_login' => '📱 New Device Login',
            'suspicious_activity' => '🚨 Suspicious Activity',
            'account_verification' => '✅ Account Verified',
            'subscription_expiry' => '⏰ Subscription Expiring',
            'payment_failed' => '⚠️ Payment Failed',
            'app_update_available' => '📲 App Update Available',
            'maintenance_notice' => '🔧 Maintenance Notice'
        ];
        
        return $titles[$type] ?? 'System Notification';
    }
    
    private function generateSystemSecurityBody($type): string
    {
        $bodies = [
            'login_alert' => 'New login from iPhone in New York. If this wasn\'t you, secure your account immediately.',
            'password_changed' => 'Your password was successfully changed from your iPhone.',
            'new_device_login' => 'Someone logged into your account from a new Android device.',
            'suspicious_activity' => 'We detected unusual activity on your account. Please review immediately.',
            'account_verification' => 'Congratulations! Your account is now fully verified.',
            'subscription_expiry' => 'Your Premium subscription expires in 3 days. Renew to keep features.',
            'payment_failed' => 'We couldn\'t process your payment. Please update your payment method.',
            'app_update_available' => 'Version 2.1.5 is available with bug fixes and new features.',
            'maintenance_notice' => 'Scheduled maintenance tonight 2-4 AM EST. App may be unavailable.'
        ];
        
        return $bodies[$type] ?? 'System update';
    }
    
    private function generateSystemDataPayload($type): array
    {
        return [
            'type' => 'system',
            'system_type' => $type,
            'deep_link' => $this->getSystemDeepLink($type),
            'priority' => $this->getSystemPriority($type),
            'requires_action' => in_array($type, ['suspicious_activity', 'payment_failed']),
            'timestamp' => Carbon::now()->toISOString()
        ];
    }
    
    private function getSystemPriority($type): string
    {
        $priorities = [
            'suspicious_activity' => 'critical',
            'login_alert' => 'high',
            'password_changed' => 'high',
            'new_device_login' => 'high',
            'payment_failed' => 'high',
            'subscription_expiry' => 'medium',
            'account_verification' => 'medium',
            'app_update_available' => 'low',
            'maintenance_notice' => 'medium'
        ];
        
        return $priorities[$type] ?? 'normal';
    }
    
    private function getSystemDeepLink($type): string
    {
        $deepLinks = [
            'login_alert' => 'foreverlove://security/logins',
            'password_changed' => 'foreverlove://security/password',
            'new_device_login' => 'foreverlove://security/devices',
            'suspicious_activity' => 'foreverlove://security/alerts',
            'account_verification' => 'foreverlove://profile/verification',
            'subscription_expiry' => 'foreverlove://premium/renew',
            'payment_failed' => 'foreverlove://billing/payment',
            'app_update_available' => 'foreverlove://settings/update',
            'maintenance_notice' => 'foreverlove://support/maintenance'
        ];
        
        return $deepLinks[$type] ?? 'foreverlove://home';
    }
    
    // Additional helper methods...
    private function generateMarketingTitle($campaign): string
    {
        $titles = [
            'premium_offer' => '🌟 50% Off Premium!',
            'feature_announcement' => '🚀 New Feature Alert!',
            'success_story' => '❤️ Love Story Inspiration',
            'dating_tips' => '💡 Pro Dating Tips',
            'app_rating_request' => '⭐ Rate ForeverUsInLove',
            'referral_program' => '🎁 Refer Friends, Get Premium',
            'seasonal_promotion' => '🌸 Spring Dating Special',
            'event_invitation' => '🎉 Singles Event Invitation',
            'survey_request' => '📝 Quick Survey Request'
        ];
        
        return $titles[$campaign] ?? 'Special Offer';
    }
    
    private function generateMarketingBody($campaign): string
    {
        $bodies = [
            'premium_offer' => 'Limited time: Get Premium for half the price! Unlock unlimited likes and more.',
            'feature_announcement' => 'Video calls are now live! Connect face-to-face with your matches.',
            'success_story' => 'Sarah and Mike found love through our app! Read their beautiful story.',
            'dating_tips' => '5 conversation starters that work every time. Improve your success rate!',
            'app_rating_request' => 'Loving the app? A 5-star review helps us improve and grow!',
            'referral_program' => 'Invite friends and both get 1 month free Premium. Win-win!',
            'seasonal_promotion' => 'Spring into love with 30% off all Premium plans this month.',
            'event_invitation' => 'Join our speed dating event this Saturday in downtown. Limited spots!',
            'survey_request' => '2-minute survey to help us make dating better for you. Your opinion matters!'
        ];
        
        return $bodies[$campaign] ?? 'Special offer just for you!';
    }
    
    private function generateMarketingDataPayload($campaign): array
    {
        return [
            'type' => 'marketing',
            'campaign' => $campaign,
            'deep_link' => $this->getMarketingDeepLink($campaign),
            'offer_id' => fake('en_US')->uuid(),
            'expiry_date' => Carbon::now()->addDays(rand(1, 30))->toISOString(),
            'tracking_code' => 'push_' . $campaign . '_' . date('Ymd')
        ];
    }
    
    private function getMarketingDeepLink($campaign): string
    {
        $deepLinks = [
            'premium_offer' => 'foreverlove://premium/offer',
            'feature_announcement' => 'foreverlove://features/video-calls',
            'success_story' => 'foreverlove://stories/featured',
            'dating_tips' => 'foreverlove://tips/conversation',
            'app_rating_request' => 'foreverlove://app-store/rate',
            'referral_program' => 'foreverlove://referral/invite',
            'seasonal_promotion' => 'foreverlove://premium/seasonal',
            'event_invitation' => 'foreverlove://events/speed-dating',
            'survey_request' => 'foreverlove://survey/feedback'
        ];
        
        return $deepLinks[$campaign] ?? 'foreverlove://promotions';
    }
    
    private function generateRichMediaTitle($mediaType): string
    {
        $titles = [
            'match_photo_showcase' => '📸 Check Out Your Matches!',
            'success_story_video' => '🎥 Amazing Love Story',
            'dating_tip_image' => '💡 Visual Dating Tip',
            'event_photo_invite' => '📷 Event Photos Inside',
            'app_feature_demo' => '🎬 See New Features',
            'profile_suggestion_carousel' => '🖼️ Profile Photo Ideas'
        ];
        
        return $titles[$mediaType] ?? 'Visual Content';
    }
    
    private function generateRichMediaBody($mediaType): string
    {
        $bodies = [
            'match_photo_showcase' => 'Swipe through photos of your top 5 matches this week!',
            'success_story_video' => 'Watch how Emma and Jake found love through our app.',
            'dating_tip_image' => 'The perfect first message formula - see the visual guide!', 
            'event_photo_invite' => 'See photos from our last singles mixer - you could be next!',
            'app_feature_demo' => 'Watch how the new video chat feature works.',
            'profile_suggestion_carousel' => 'Get inspiration for better profile photos.'
        ];
        
        return $bodies[$mediaType] ?? 'Rich media content available';
    }
    
    private function generateMediaUrl($mediaType, $contentType): string
    {
        $baseUrl = 'https://cdn.foreverlove.com/';
        
        if ($contentType === 'video') {
            return $baseUrl . "videos/{$mediaType}/" . fake('en_US')->uuid() . '.mp4';
        } else {
            return $baseUrl . "images/{$mediaType}/" . fake('en_US')->uuid() . '.jpg';
        }
    }
    
    private function generateRichMediaDataPayload($mediaType): array
    {
        return [
            'type' => 'rich_media',
            'media_type' => $mediaType,
            'content_id' => fake('en_US')->uuid(),
            'deep_link' => 'foreverlove://media/' . $mediaType,
            'auto_download' => fake()->boolean(30),
            'cache_duration' => rand(3600, 86400) // 1 hour to 1 day
        ];
    }
    
    private function generateInteractiveTitle($type): string
    {
        $titles = [
            'match_quick_actions' => '⚡ Quick Match Actions',
            'message_quick_reply' => '💬 Quick Reply Options',
            'date_confirmation' => '📅 Confirm Your Date',
            'profile_rating' => '⭐ Rate This Profile',
            'app_review_request' => '📝 Quick App Review',
            'survey_response' => '📊 One-Tap Survey',
            'event_rsvp' => '🎉 RSVP for Event',
            'subscription_renewal' => '🔄 Renew Subscription'
        ];
        
        return $titles[$type] ?? 'Interactive Notification';
    }
    
    private function generateInteractiveBody($type): string
    {
        $bodies = [
            'match_quick_actions' => 'Like, pass, or super like directly from this notification!',
            'message_quick_reply' => 'Tap to reply with preset messages or type your own.',
            'date_confirmation' => 'Coffee tomorrow at 3 PM? Confirm, reschedule, or cancel.',
            'profile_rating' => 'How compatible do you think you are with Alex?',
            'app_review_request' => 'Quick rating: How likely are you to recommend our app?',
            'survey_response' => 'One question: What\'s your favorite app feature?',
            'event_rsvp' => 'Speed dating event this Saturday - Yes, Maybe, or No?',
            'subscription_renewal' => 'Your Premium expires tomorrow. Renew with one tap!'
        ];
        
        return $bodies[$type] ?? 'Interactive options available';
    }
    
    private function generateActionButtons($type): array
    {
        $buttons = [
            'match_quick_actions' => [
                ['title' => '❤️ Like', 'action' => 'like_match'],
                ['title' => '✨ Super Like', 'action' => 'super_like_match'],
                ['title' => '👎 Pass', 'action' => 'pass_match']
            ],
            'message_quick_reply' => [
                ['title' => '👋 Hey there!', 'action' => 'quick_reply_1'],
                ['title' => '😊 Sounds good!', 'action' => 'quick_reply_2'],
                ['title' => '💬 Type reply', 'action' => 'open_chat']
            ],
            'date_confirmation' => [
                ['title' => '✅ Confirm', 'action' => 'confirm_date'],
                ['title' => '📅 Reschedule', 'action' => 'reschedule_date'],
                ['title' => '❌ Cancel', 'action' => 'cancel_date']
            ],
            'profile_rating' => [
                ['title' => '💚 High', 'action' => 'rate_high'],
                ['title' => '💛 Medium', 'action' => 'rate_medium'],
                ['title' => '💔 Low', 'action' => 'rate_low']
            ],
            'app_review_request' => [
                ['title' => '⭐⭐⭐⭐⭐', 'action' => 'rate_5_stars'],
                ['title' => '⭐⭐⭐⭐', 'action' => 'rate_4_stars'],
                ['title' => '📝 Feedback', 'action' => 'write_review']
            ],
            'survey_response' => [
                ['title' => '💕 Matching', 'action' => 'survey_matching'],
                ['title' => '💬 Messaging', 'action' => 'survey_messaging'],
                ['title' => '📱 Interface', 'action' => 'survey_interface']
            ],
            'event_rsvp' => [
                ['title' => '✅ Yes', 'action' => 'rsvp_yes'],
                ['title' => '🤔 Maybe', 'action' => 'rsvp_maybe'],
                ['title' => '❌ No', 'action' => 'rsvp_no']
            ],
            'subscription_renewal' => [
                ['title' => '🔄 Renew Now', 'action' => 'renew_subscription'],
                ['title' => '⏰ Remind Later', 'action' => 'remind_later'],
                ['title' => 'ℹ️ Learn More', 'action' => 'subscription_info']
            ]
        ];
        
        return $buttons[$type] ?? [
            ['title' => '✅ Yes', 'action' => 'confirm'],
            ['title' => '❌ No', 'action' => 'cancel']
        ];
    }
    
    private function generateInteractiveDataPayload($type): array
    {
        return [
            'type' => 'interactive',
            'interactive_type' => $type,
            'action_tracking_id' => fake('en_US')->uuid(),
            'deep_link' => 'foreverlove://interactive/' . $type,
            'requires_auth' => fake()->boolean(70),
            'background_processing' => true
        ];
    }
    
    private function generateLocationTitle($type): string
    {
        $titles = [
            'nearby_matches' => '📍 Matches Nearby!',
            'local_events' => '🎉 Local Dating Events',
            'popular_dating_spots' => '☕ Popular Spots Nearby',
            'safety_check_in' => '🛡️ Safety Check-In',
            'date_location_arrived' => '📍 Date Location',
            'geo_fence_trigger' => '📍 Location Alert'
        ];
        
        return $titles[$type] ?? 'Location Update';
    }
    
    private function generateLocationBody($type): string
    {
        $bodies = [
            'nearby_matches' => '5 great matches within 2 miles of your location!',
            'local_events' => 'Speed dating event at Central Park tonight at 7 PM.',
            'popular_dating_spots' => 'Coffee shops and restaurants perfect for first dates nearby.',
            'safety_check_in' => 'Let friends know you\'re safe during your date.',
            'date_location_arrived' => 'Your date location is 2 blocks away. Safe travels!',
            'geo_fence_trigger' => 'You\'re near a popular dating spot. Check it out!'
        ];
        
        return $bodies[$type] ?? 'Location-based update';
    }
    
    private function generateLocationDataPayload($type): array
    {
        return [
            'type' => 'location',
            'location_type' => $type,
            'latitude' => fake()->latitude(25, 49),
            'longitude' => fake()->longitude(-125, -66),
            'accuracy' => rand(5, 50),
            'deep_link' => 'foreverlove://location/' . $type,
            'privacy_consented' => true
        ];
    }
    
    private function generateCrossPlatformDataPayload($testType, $platform): array
    {
        return [
            'type' => 'cross_platform_test',
            'test_type' => $testType,
            'platform' => $platform,
            'test_id' => fake('en_US')->uuid(),
            'baseline_comparison' => true,
            'performance_tracking' => true
        ];
    }
    
    private function getFailureErrorCode($failureType): string
    {
        $codes = [
            'invalid_token' => 'INVALID_REGISTRATION_TOKEN',
            'app_uninstalled' => 'UNREGISTERED_DEVICE',
            'device_unreachable' => 'DEVICE_MESSAGE_RATE_EXCEEDED',
            'payload_too_large' => 'MESSAGE_TOO_BIG',
            'rate_limit_exceeded' => 'QUOTA_EXCEEDED',
            'expired_certificate' => 'INVALID_CERTIFICATE',
            'network_timeout' => 'NETWORK_TIMEOUT',
            'platform_rejection' => 'PLATFORM_ERROR'
        ];
        
        return $codes[$failureType] ?? 'UNKNOWN_ERROR';
    }
    
    private function getFailureErrorMessage($failureType): string
    {
        $messages = [
            'invalid_token' => 'The registration token is not valid anymore',
            'app_uninstalled' => 'App has been uninstalled from device',
            'device_unreachable' => 'Device message rate exceeded',
            'payload_too_large' => 'Message payload exceeds size limits',
            'rate_limit_exceeded' => 'Sending rate quota exceeded',
            'expired_certificate' => 'Push certificate has expired',
            'network_timeout' => 'Network connection timeout',
            'platform_rejection' => 'Platform rejected the message'
        ];
        
        return $messages[$failureType] ?? 'Unknown error occurred';
    }
    
    private function getABTestPerformance($testName, $variant): array
    {
        $performance = [
            'emoji_vs_no_emoji' => [
                'A' => ['delivery_rate' => 88, 'click_rate' => 18], // No emoji
                'B' => ['delivery_rate' => 90, 'click_rate' => 25]  // With emoji
            ],
            'short_vs_long_body' => [
                'A' => ['delivery_rate' => 89, 'click_rate' => 22], // Short
                'B' => ['delivery_rate' => 87, 'click_rate' => 19]  // Long
            ],
            'personalized_vs_generic' => [
                'A' => ['delivery_rate' => 86, 'click_rate' => 15], // Generic
                'B' => ['delivery_rate' => 91, 'click_rate' => 28]  // Personalized
            ],
            'urgent_vs_casual_tone' => [
                'A' => ['delivery_rate' => 85, 'click_rate' => 24], // Urgent
                'B' => ['delivery_rate' => 88, 'click_rate' => 20]  // Casual
            ],
            'question_vs_statement' => [
                'A' => ['delivery_rate' => 87, 'click_rate' => 21], // Statement
                'B' => ['delivery_rate' => 89, 'click_rate' => 26]  // Question
            ]
        ];
        
        return $performance[$testName][$variant] ?? ['delivery_rate' => 85, 'click_rate' => 20];
    }
    
    private function getABTestContent($testName, $variant): array
    {
        $content = [
            'emoji_vs_no_emoji' => [
                'A' => ['title' => 'New matches available', 'body' => 'Check out your latest matches now'],
                'B' => ['title' => '💕 New matches available!', 'body' => '✨ Check out your latest matches now 🎉']
            ],
            'short_vs_long_body' => [
                'A' => ['title' => 'Match update', 'body' => '3 new matches'],
                'B' => ['title' => 'Match update', 'body' => 'You have 3 new carefully selected matches based on your preferences and dating history']
            ],
            'personalized_vs_generic' => [
                'A' => ['title' => 'Dating update', 'body' => 'New activity in the app'],
                'B' => ['title' => 'Hey Sarah!', 'body' => 'Someone from your neighborhood liked your profile']
            ],
            'urgent_vs_casual_tone' => [
                'A' => ['title' => 'ACT NOW!', 'body' => 'Limited time offer expires in 2 hours!'],
                'B' => ['title' => 'Special offer', 'body' => 'Check out this great deal when you have time']
            ],
            'question_vs_statement' => [
                'A' => ['title' => 'Premium features available', 'body' => 'Premium features can improve your dating success'],
                'B' => ['title' => 'Ready to level up?', 'body' => 'Want to see who likes you and get unlimited matches?']
            ]
        ];
        
        return $content[$testName][$variant] ?? ['title' => 'Test Notification', 'body' => 'A/B test content'];
    }
    
    private function getABTestHypothesis($testName): string
    {
        $hypotheses = [
            'emoji_vs_no_emoji' => 'Emojis in push notifications increase engagement rates',
            'short_vs_long_body' => 'Shorter notification messages have higher click rates',
            'personalized_vs_generic' => 'Personalized notifications perform better than generic ones',
            'urgent_vs_casual_tone' => 'Urgent language increases immediate action rates',
            'question_vs_statement' => 'Questions in notifications drive more engagement than statements'
        ];
        
        return $hypotheses[$testName] ?? 'Testing notification optimization';
    }
    
    /**
     * Display comprehensive seeding statistics
     */
    private function displaySeedingStats($totalRecords, $executionTime): void
    {
        $this->command->newLine();
        $this->command->info('📊 ===== PUSH NOTIFICATION SEEDING COMPLETED =====');
        
        // Calculate breakdown
        $delivered = PushNotification::where('status', 'delivered')->count();
        $failed = PushNotification::where('status', 'failed')->count();
        $expired = PushNotification::where('status', 'expired')->count();
        $clicked = PushNotification::whereNotNull('clicked_at')->count();
        
        // Calculate rates
        $deliveryRate = $totalRecords > 0 ? round(($delivered / $totalRecords) * 100, 1) : 0;
        $clickRate = $delivered > 0 ? round(($clicked / $delivered) * 100, 1) : 0;
        $failureRate = $totalRecords > 0 ? round(($failed / $totalRecords) * 100, 1) : 0;
        
        $this->command->table(
            ['Metric', 'Value', 'Percentage'],
            [
                ['Total Push Records', number_format($totalRecords), '100%'],
                ['Delivered', number_format($delivered), $deliveryRate . '%'],
                ['Clicked', number_format($clicked), $clickRate . '%'],
                ['Failed', number_format($failed), $failureRate . '%'],
                ['Expired', number_format($expired), round(($expired/$totalRecords)*100, 1) . '%'],
            ]
        );
        
        // Platform breakdown
        $platforms = PushNotification::selectRaw('platform, COUNT(*) as count')
            ->groupBy('platform')
            ->orderBy('count', 'desc')
            ->get();
            
        $this->command->info('📱 Platform Distribution:');
        foreach ($platforms as $platform) {
            $percentage = round(($platform->count / $totalRecords) * 100, 1);
            $this->command->line("   • {$platform->platform}: {$platform->count} ({$percentage}%)");
        }
        
        // Notification type breakdown
        $notificationTypes = PushNotification::selectRaw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.notification_type")) as notification_type, COUNT(*) as count')
            ->whereRaw('JSON_EXTRACT(metadata, "$.notification_type") IS NOT NULL')
            ->groupBy('notification_type')
            ->orderBy('count', 'desc')
            ->get();
            
        $this->command->info('🔔 Notification Type Distribution:');
        foreach ($notificationTypes as $type) {
            $percentage = round(($type->count / $totalRecords) * 100, 1);
            $this->command->line("   • {$type->notification_type}: {$type->count} ({$percentage}%)");
        }
        
        // Performance metrics
        $avgDeliveryTime = PushNotification::whereNotNull('delivered_at')
            ->whereNotNull('sent_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, sent_at, delivered_at)) as avg_seconds')
            ->value('avg_seconds');
            
        $this->command->newLine();
        $this->command->info("⚡ Execution time: {$executionTime}s");
        $this->command->info("🎯 Performance: " . number_format($totalRecords / $executionTime, 0) . " records/second");
        if ($avgDeliveryTime) {
            $this->command->info("📬 Average delivery time: " . round($avgDeliveryTime, 2) . " seconds");
        }
        $this->command->info('✅ PushNotification seeding completed successfully!');
    }
}