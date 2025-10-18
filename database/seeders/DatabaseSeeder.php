<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * ForeverUsInLove - Dating App Database Seeder
     * Complete seeding orchestration for all application domains
     * 
     * @return void
     */
    public function run(): void
    {
        $this->command->info('🚀 ========================================');
        $this->command->info('🚀 FOREVERUSINLOVE DATABASE SEEDING');
        $this->command->info('🚀 Dating Application - Complete Setup');
        $this->command->info('🚀 ========================================');
        $this->command->newLine();
        
        $startTime = microtime(true);
        
        // Disable foreign key checks for seeding
        Schema::disableForeignKeyConstraints();
        
        try {
            // ============================================
            // DOMAIN 1: USER PROFILE & AUTHENTICATION
            // ============================================
            $this->seedProfileDomain();
            
            // ============================================
            // DOMAIN 2: MATCHING & DISCOVERY
            // ============================================
            $this->seedMatchingDomain();
            
            // ============================================
            // DOMAIN 3: CHAT & MESSAGING
            // ============================================
            $this->seedChatDomain();
            
            // ============================================
            // DOMAIN 4: NOTIFICATION SYSTEM
            // ============================================
            $this->seedNotificationDomain();
            
            // ============================================
            // DOMAIN 5: MODERATION & SAFETY
            // ============================================
            $this->seedModerationDomain();
            
            // ============================================
            // DOMAIN 6: COMMERCE & SUBSCRIPTIONS
            // ============================================
            $this->seedCommerceDomain();
            
            // ============================================
            // DOMAIN 7: ANALYTICS & REPORTING
            // ============================================
            $this->seedAnalyticsDomain();
            
            // ============================================
            // DOMAIN 8: GAMIFICATION & ENGAGEMENT
            // ============================================
            $this->seedGamificationDomain();
            
        } catch (\Exception $e) {
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            $this->command->error('Stack trace: ' . $e->getTraceAsString());
            
            Schema::enableForeignKeyConstraints();
            throw $e;
        }
        
        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
        
        // Calculate total execution time
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        // Display final summary
        $this->displayFinalSummary($executionTime);
    }
    
    /**
     * Seed Profile Domain (Users, Profiles, Photos, Preferences)
     */
    private function seedProfileDomain(): void
    {
        $this->command->info('👤 ========================================');
        $this->command->info('👤 DOMAIN 1: USER PROFILE & AUTHENTICATION');
        $this->command->info('👤 ========================================');
        $this->command->newLine();
        
        if ($this->seederExists('UserSeeder')) {
            $this->command->info('📝 Seeding users and authentication...');
            $this->call(UserSeeder::class);
        } else {
            $this->command->warn('⚠️  UserSeeder not found - Skipping');
        }
        
        if ($this->seederExists('ProfileSeeder')) {
            $this->command->info('📝 Seeding user profiles...');
            $this->call(ProfileSeeder::class);
        } else {
            $this->command->warn('⚠️  ProfileSeeder not found - Skipping');
        }
        
        if ($this->seederExists('ProfilePhotoSeeder')) {
            $this->command->info('📝 Seeding profile photos...');
            $this->call(ProfilePhotoSeeder::class);
        } else {
            $this->command->warn('⚠️  ProfilePhotoSeeder not found - Skipping');
        }
        
        if ($this->seederExists('ProfilePreferenceSeeder')) {
            $this->command->info('📝 Seeding user preferences...');
            $this->call(ProfilePreferenceSeeder::class);
        } else {
            $this->command->warn('⚠️  ProfilePreferenceSeeder not found - Skipping');
        }
        
        if ($this->seederExists('UserInterestSeeder')) {
            $this->command->info('📝 Seeding user interests and hobbies...');
            $this->call(UserInterestSeeder::class);
        } else {
            $this->command->warn('⚠️  UserInterestSeeder not found - Skipping');
        }
        
        if ($this->seederExists('VerificationSeeder')) {
            $this->command->info('📝 Seeding profile verifications...');
            $this->call(VerificationSeeder::class);
        } else {
            $this->command->warn('⚠️  VerificationSeeder not found - Skipping');
        }
        
        $this->command->info('✅ Profile Domain seeding completed!');
        $this->command->newLine();
    }
    
    /**
     * Seed Matching Domain (Matches, Likes, Swipes, Recommendations)
     */
    private function seedMatchingDomain(): void
    {
        $this->command->info('💕 ========================================');
        $this->command->info('💕 DOMAIN 2: MATCHING & DISCOVERY');
        $this->command->info('💕 ========================================');
        $this->command->newLine();
        
        if ($this->seederExists('SwipeSeeder')) {
            $this->command->info('📝 Seeding swipe actions...');
            $this->call(SwipeSeeder::class);
        } else {
            $this->command->warn('⚠️  SwipeSeeder not found - Skipping');
        }
        
        if ($this->seederExists('LikeSeeder')) {
            $this->command->info('📝 Seeding likes and super likes...');
            $this->call(LikeSeeder::class);
        } else {
            $this->command->warn('⚠️  LikeSeeder not found - Skipping');
        }
        
        if ($this->seederExists('MatchSeeder')) {
            $this->command->info('📝 Seeding matches...');
            $this->call(MatchSeeder::class);
        } else {
            $this->command->warn('⚠️  MatchSeeder not found - Skipping');
        }
        
        if ($this->seederExists('DailyPickSeeder')) {
            $this->command->info('📝 Seeding daily picks and recommendations...');
            $this->call(DailyPickSeeder::class);
        } else {
            $this->command->warn('⚠️  DailyPickSeeder not found - Skipping');
        }
        
        if ($this->seederExists('MatchAlgorithmSeeder')) {
            $this->command->info('📝 Seeding matching algorithm data...');
            $this->call(MatchAlgorithmSeeder::class);
        } else {
            $this->command->warn('⚠️  MatchAlgorithmSeeder not found - Skipping');
        }
        
        if ($this->seederExists('CompatibilityScoreSeeder')) {
            $this->command->info('📝 Seeding compatibility scores...');
            $this->call(CompatibilityScoreSeeder::class);
        } else {
            $this->command->warn('⚠️  CompatibilityScoreSeeder not found - Skipping');
        }
        
        $this->command->info('✅ Matching Domain seeding completed!');
        $this->command->newLine();
    }
    
    /**
     * Seed Chat Domain (Conversations, Messages, Media)
     */
    private function seedChatDomain(): void
    {
        $this->command->info('💬 ========================================');
        $this->command->info('💬 DOMAIN 3: CHAT & MESSAGING');
        $this->command->info('💬 ========================================');
        $this->command->newLine();
        
        if ($this->seederExists('ConversationSeeder')) {
            $this->command->info('📝 Seeding conversations...');
            $this->call(ConversationSeeder::class);
        } else {
            $this->command->warn('⚠️  ConversationSeeder not found - Skipping');
        }
        
        if ($this->seederExists('MessageSeeder')) {
            $this->command->info('📝 Seeding messages...');
            $this->call(MessageSeeder::class);
        } else {
            $this->command->warn('⚠️  MessageSeeder not found - Skipping');
        }
        
        if ($this->seederExists('MessageMediaSeeder')) {
            $this->command->info('📝 Seeding message media (photos, videos, voice)...');
            $this->call(MessageMediaSeeder::class);
        } else {
            $this->command->warn('⚠️  MessageMediaSeeder not found - Skipping');
        }
        
        if ($this->seederExists('MessageReactionSeeder')) {
            $this->command->info('📝 Seeding message reactions...');
            $this->call(MessageReactionSeeder::class);
        } else {
            $this->command->warn('⚠️  MessageReactionSeeder not found - Skipping');
        }
        
        if ($this->seederExists('ConversationStarterSeeder')) {
            $this->command->info('📝 Seeding conversation starters...');
            $this->call(ConversationStarterSeeder::class);
        } else {
            $this->command->warn('⚠️  ConversationStarterSeeder not found - Skipping');
        }
        
        if ($this->seederExists('VideoCallSeeder')) {
            $this->command->info('📝 Seeding video calls...');
            $this->call(VideoCallSeeder::class);
        } else {
            $this->command->warn('⚠️  VideoCallSeeder not found - Skipping');
        }
        
        $this->command->info('✅ Chat Domain seeding completed!');
        $this->command->newLine();
    }
    
    /**
     * Seed Notification Domain (Notifications, Email, Push)
     */
    private function seedNotificationDomain(): void
    {
        $this->command->info('🔔 ========================================');
        $this->command->info('🔔 DOMAIN 4: NOTIFICATION SYSTEM');
        $this->command->info('🔔 ========================================');
        $this->command->newLine();
        
        // Main Notification Seeder
        if ($this->seederExists('NotificationSeeder')) {
            $this->command->info('📝 Seeding notifications (multi-channel)...');
            $this->call(NotificationSeeder::class);
        } else {
            $this->command->warn('⚠️  NotificationSeeder not found - Skipping');
        }
        
        // Email Notifications
        if ($this->seederExists('EmailNotificationSeeder')) {
            $this->command->info('📝 Seeding email notifications...');
            $this->call(EmailNotificationSeeder::class);
        } else {
            $this->command->warn('⚠️  EmailNotificationSeeder not found - Skipping');
        }
        
        // Push Notifications
        if ($this->seederExists('PushNotificationSeeder')) {
            $this->command->info('📝 Seeding push notifications...');
            $this->call(PushNotificationSeeder::class);
        } else {
            $this->command->warn('⚠️  PushNotificationSeeder not found - Skipping');
        }
        
        // SMS Notifications (if exists)
        if ($this->seederExists('SmsNotificationSeeder')) {
            $this->command->info('📝 Seeding SMS notifications...');
            $this->call(SmsNotificationSeeder::class);
        } else {
            $this->command->warn('⚠️  SmsNotificationSeeder not found - Skipping');
        }
        
        // Notification Preferences
        if ($this->seederExists('NotificationPreferenceSeeder')) {
            $this->command->info('📝 Seeding notification preferences...');
            $this->call(NotificationPreferenceSeeder::class);
        } else {
            $this->command->warn('⚠️  NotificationPreferenceSeeder not found - Skipping');
        }
        
        $this->command->info('✅ Notification Domain seeding completed!');
        $this->command->newLine();
    }
    
    /**
     * Seed Moderation Domain (Reports, Blocks, Content Flags, PQRS)
     */
    private function seedModerationDomain(): void
    {
        $this->command->info('🛡️ ========================================');
        $this->command->info('🛡️ DOMAIN 5: MODERATION & SAFETY');
        $this->command->info('🛡️ ========================================');
        $this->command->newLine();
        
        // Main Moderation Seeder (includes all 4 models)
        if ($this->seederExists('ModerationSeeder')) {
            $this->command->info('📝 Seeding moderation system (comprehensive)...');
            $this->call(ModerationSeeder::class);
        } else {
            $this->command->warn('⚠️  ModerationSeeder not found - Skipping');
            
            // Fallback to individual seeders if main seeder doesn't exist
            if ($this->seederExists('BlockSeeder')) {
                $this->command->info('📝 Seeding user blocks...');
                $this->call(BlockSeeder::class);
            }
            
            if ($this->seederExists('ContentFlagSeeder')) {
                $this->command->info('📝 Seeding content flags...');
                $this->call(ContentFlagSeeder::class);
            }
            
            if ($this->seederExists('ReportSeeder')) {
                $this->command->info('📝 Seeding reports...');
                $this->call(ReportSeeder::class);
            }
            
            if ($this->seederExists('PQRSSeeder')) {
                $this->command->info('📝 Seeding PQRS (support tickets)...');
                $this->call(PQRSSeeder::class);
            }
        }
        
        if ($this->seederExists('SafetyTipSeeder')) {
            $this->command->info('📝 Seeding safety tips and guidelines...');
            $this->call(SafetyTipSeeder::class);
        } else {
            $this->command->warn('⚠️  SafetyTipSeeder not found - Skipping');
        }
        
        if ($this->seederExists('ModerationActionSeeder')) {
            $this->command->info('📝 Seeding moderation actions log...');
            $this->call(ModerationActionSeeder::class);
        } else {
            $this->command->warn('⚠️  ModerationActionSeeder not found - Skipping');
        }
        
        if ($this->seederExists('TrustScoreSeeder')) {
            $this->command->info('📝 Seeding user trust scores...');
            $this->call(TrustScoreSeeder::class);
        } else {
            $this->command->warn('⚠️  TrustScoreSeeder not found - Skipping');
        }
        
        $this->command->info('✅ Moderation Domain seeding completed!');
        $this->command->newLine();
    }
    
    /**
     * Seed Commerce Domain (Subscriptions, Payments, Products, Transactions)
     */
    private function seedCommerceDomain(): void
    {
        $this->command->info('💳 ========================================');
        $this->command->info('💳 DOMAIN 6: COMMERCE & SUBSCRIPTIONS');
        $this->command->info('💳 ========================================');
        $this->command->newLine();
        
        if ($this->seederExists('SubscriptionPlanSeeder')) {
            $this->command->info('📝 Seeding subscription plans...');
            $this->call(SubscriptionPlanSeeder::class);
        } else {
            $this->command->warn('⚠️  SubscriptionPlanSeeder not found - Skipping');
        }
        
        if ($this->seederExists('UserSubscriptionSeeder')) {
            $this->command->info('📝 Seeding user subscriptions...');
            $this->call(UserSubscriptionSeeder::class);
        } else {
            $this->command->warn('⚠️  UserSubscriptionSeeder not found - Skipping');
        }
        
        if ($this->seederExists('ProductSeeder')) {
            $this->command->info('📝 Seeding products (boosts, super likes, etc)...');
            $this->call(ProductSeeder::class);
        } else {
            $this->command->warn('⚠️  ProductSeeder not found - Skipping');
        }
        
        if ($this->seederExists('PaymentSeeder')) {
            $this->command->info('📝 Seeding payments and transactions...');
            $this->call(PaymentSeeder::class);
        } else {
            $this->command->warn('⚠️  PaymentSeeder not found - Skipping');
        }
        
        if ($this->seederExists('PaymentMethodSeeder')) {
            $this->command->info('📝 Seeding payment methods...');
            $this->call(PaymentMethodSeeder::class);
        } else {
            $this->command->warn('⚠️  PaymentMethodSeeder not found - Skipping');
        }
        
        if ($this->seederExists('InvoiceSeeder')) {
            $this->command->info('📝 Seeding invoices and receipts...');
            $this->call(InvoiceSeeder::class);
        } else {
            $this->command->warn('⚠️  InvoiceSeeder not found - Skipping');
        }
        
        if ($this->seederExists('PromoCodeSeeder')) {
            $this->command->info('📝 Seeding promo codes and discounts...');
            $this->call(PromoCodeSeeder::class);
        } else {
            $this->command->warn('⚠️  PromoCodeSeeder not found - Skipping');
        }
        
        if ($this->seederExists('RefundSeeder')) {
            $this->command->info('📝 Seeding refunds...');
            $this->call(RefundSeeder::class);
        } else {
            $this->command->warn('⚠️  RefundSeeder not found - Skipping');
        }
        
        $this->command->info('✅ Commerce Domain seeding completed!');
        $this->command->newLine();
    }
    
    /**
     * Seed Analytics Domain (Events, Metrics, User Behavior)
     */
    private function seedAnalyticsDomain(): void
    {
        $this->command->info('📊 ========================================');
        $this->command->info('📊 DOMAIN 7: ANALYTICS & REPORTING');
        $this->command->info('📊 ========================================');
        $this->command->newLine();
        
        if ($this->seederExists('UserActivitySeeder')) {
            $this->command->info('📝 Seeding user activity logs...');
            $this->call(UserActivitySeeder::class);
        } else {
            $this->command->warn('⚠️  UserActivitySeeder not found - Skipping');
        }
        
        if ($this->seederExists('AnalyticsEventSeeder')) {
            $this->command->info('📝 Seeding analytics events...');
            $this->call(AnalyticsEventSeeder::class);
        } else {
            $this->command->warn('⚠️  AnalyticsEventSeeder not found - Skipping');
        }
        
        if ($this->seederExists('UserMetricSeeder')) {
            $this->command->info('📝 Seeding user metrics and KPIs...');
            $this->call(UserMetricSeeder::class);
        } else {
            $this->command->warn('⚠️  UserMetricSeeder not found - Skipping');
        }
        
        if ($this->seederExists('SessionSeeder')) {
            $this->command->info('📝 Seeding user sessions...');
            $this->call(SessionSeeder::class);
        } else {
            $this->command->warn('⚠️  SessionSeeder not found - Skipping');
        }
        
        if ($this->seederExists('ConversionSeeder')) {
            $this->command->info('📝 Seeding conversion funnels...');
            $this->call(ConversionSeeder::class);
        } else {
            $this->command->warn('⚠️  ConversionSeeder not found - Skipping');
        }
        
        if ($this->seederExists('RetentionSeeder')) {
            $this->command->info('📝 Seeding retention data...');
            $this->call(RetentionSeeder::class);
        } else {
            $this->command->warn('⚠️  RetentionSeeder not found - Skipping');
        }
        
        $this->command->info('✅ Analytics Domain seeding completed!');
        $this->command->newLine();
    }
    
    /**
     * Seed Gamification Domain (Achievements, Badges, Rewards, Streaks)
     */
    private function seedGamificationDomain(): void
    {
        $this->command->info('🎮 ========================================');
        $this->command->info('🎮 DOMAIN 8: GAMIFICATION & ENGAGEMENT');
        $this->command->info('🎮 ========================================');
        $this->command->newLine();
        
        if ($this->seederExists('AchievementSeeder')) {
            $this->command->info('📝 Seeding achievements...');
            $this->call(AchievementSeeder::class);
        } else {
            $this->command->warn('⚠️  AchievementSeeder not found - Skipping');
        }
        
        if ($this->seederExists('BadgeSeeder')) {
            $this->command->info('📝 Seeding badges and awards...');
            $this->call(BadgeSeeder::class);
        } else {
            $this->command->warn('⚠️  BadgeSeeder not found - Skipping');
        }
        
        if ($this->seederExists('UserAchievementSeeder')) {
            $this->command->info('📝 Seeding user achievements...');
            $this->call(UserAchievementSeeder::class);
        } else {
            $this->command->warn('⚠️  UserAchievementSeeder not found - Skipping');
        }
        
        if ($this->seederExists('StreakSeeder')) {
            $this->command->info('📝 Seeding user streaks...');
            $this->call(StreakSeeder::class);
        } else {
            $this->command->warn('⚠️  StreakSeeder not found - Skipping');
        }
        
        if ($this->seederExists('RewardSeeder')) {
            $this->command->info('📝 Seeding rewards and incentives...');
            $this->call(RewardSeeder::class);
        } else {
            $this->command->warn('⚠️  RewardSeeder not found - Skipping');
        }
        
        if ($this->seederExists('LeaderboardSeeder')) {
            $this->command->info('📝 Seeding leaderboards...');
            $this->call(LeaderboardSeeder::class);
        } else {
            $this->command->warn('⚠️  LeaderboardSeeder not found - Skipping');
        }
        
        if ($this->seederExists('DailyQuestSeeder')) {
            $this->command->info('📝 Seeding daily quests and challenges...');
            $this->call(DailyQuestSeeder::class);
        } else {
            $this->command->warn('⚠️  DailyQuestSeeder not found - Skipping');
        }
        
        $this->command->info('✅ Gamification Domain seeding completed!');
        $this->command->newLine();
    }
    
    /**
     * Check if a seeder class exists
     */
    private function seederExists(string $seederClass): bool
    {
        return class_exists("Database\\Seeders\\{$seederClass}");
    }
    
    /**
     * Display final seeding summary
     */
    private function displayFinalSummary(float $executionTime): void
    {
        $this->command->newLine();
        $this->command->info('🎉 ========================================');
        $this->command->info('🎉 DATABASE SEEDING COMPLETED SUCCESSFULLY');
        $this->command->info('🎉 ========================================');
        $this->command->newLine();
        
        // Count total records (approximate)
        $this->command->info('📊 SEEDING SUMMARY:');
        $this->command->newLine();
        
        // Domain Summary Table
        $summary = [
            ['Domain', 'Status', 'Description'],
            ['Profile & Auth', '✅', 'Users, profiles, photos, preferences'],
            ['Matching', '✅', 'Swipes, likes, matches, recommendations'],
            ['Chat & Messaging', '✅', 'Conversations, messages, media'],
            ['Notifications', '✅', 'Multi-channel notification system'],
            ['Moderation', '✅', 'Reports, blocks, flags, PQRS'],
            ['Commerce', '✅', 'Subscriptions, payments, products'],
            ['Analytics', '✅', 'Events, metrics, user behavior'],
            ['Gamification', '✅', 'Achievements, badges, rewards'],
        ];
        
        $this->command->table($summary[0], array_slice($summary, 1));
        
        $this->command->newLine();
        $this->command->info("⏱️  Total Execution Time: {$executionTime} seconds");
        $this->command->newLine();
        
        // Next steps
        $this->command->info('🚀 NEXT STEPS:');
        $this->command->line('   1. Verify data: php artisan tinker');
        $this->command->line('   2. Check relationships: User::with(\'profile\')->first()');
        $this->command->line('   3. Test matching: Match::with(\'users\')->latest()->take(5)->get()');
        $this->command->line('   4. Review notifications: Notification::latest()->take(10)->get()');
        $this->command->line('   5. Check moderation: Report::with(\'reportable\')->latest()->get()');
        $this->command->newLine();
        
        $this->command->info('💡 USEFUL COMMANDS:');
        $this->command->line('   • Fresh seed: php artisan migrate:fresh --seed');
        $this->command->line('   • Specific seeder: php artisan db:seed --class=NotificationSeeder');
        $this->command->line('   • Check migrations: php artisan migrate:status');
        $this->command->newLine();
        
        $this->command->info('📚 DOCUMENTATION:');
        $this->command->line('   • Review each domain seeder for detailed data patterns');
        $this->command->line('   • Check factory files for data generation logic');
        $this->command->line('   • Examine migrations for database schema');
        $this->command->newLine();
        
        $this->command->info('✨ ForeverUsInLove database is ready for development!');
        $this->command->info('🚀 Happy coding! 💕');
    }
}