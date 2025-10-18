<?php

namespace Database\Seeders\Domains\Notification;

use App\Domains\Notification\Models\Notification;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔔 Seeding Notification records...');
        
        // Ensure we have enough users for notification relationships
        $userCount = User::count();
        if ($userCount < 20) {
            $this->command->warn("⚠️  Not enough users ($userCount) for realistic notification seeding. Creating additional users...");
            User::factory(50)->create();
        }
        
        // Get users for seeding
        $users = User::inRandomOrder()->limit(100)->get();
        
        DB::transaction(function () use ($users) {
            
            // 1. Create dating activity notifications (150 notifications)
            $this->command->info('Creating dating activity notifications...');
            
            Notification::factory()
                ->count(150)
                ->datingActivity()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 2. Create messaging notifications (120 notifications)
            $this->command->info('Creating messaging notifications...');
            
            Notification::factory()
                ->count(120)
                ->messaging()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 3. Create system notifications (80 notifications)
            $this->command->info('Creating system notifications...');
            
            Notification::factory()
                ->count(80)
                ->system()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 4. Create marketing notifications (100 notifications)
            $this->command->info('Creating marketing notifications...');
            
            Notification::factory()
                ->count(100)
                ->marketing()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 5. Create high priority notifications (60 notifications)
            $this->command->info('Creating high priority notifications...');
            
            Notification::factory()
                ->count(60)
                ->highPriority()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 6. Create read notifications (100 notifications)
            $this->command->info('Creating read notifications...');
            
            Notification::factory()
                ->count(100)
                ->read()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 7. Create scheduled notifications (40 notifications)
            $this->command->info('Creating scheduled notifications...');
            
            Notification::factory()
                ->count(40)
                ->scheduled()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 8. Create failed notifications (25 notifications)
            $this->command->info('Creating failed notifications...');
            
            Notification::factory()
                ->count(25)
                ->failed()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 9. Create campaign notifications with A/B testing (70 notifications)
            $this->command->info('Creating campaign notifications...');
            
            Notification::factory()
                ->count(70)
                ->campaign()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 10. Create safety/critical notifications (30 notifications)
            $this->command->info('Creating safety critical notifications...');
            
            Notification::factory()
                ->count(30)
                ->safetyCritical()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 11. Create commerce notifications (45 notifications)
            $this->command->info('Creating commerce notifications...');
            
            Notification::factory()
                ->count(45)
                ->commerce()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 12. Create realistic user patterns
            $this->command->info('Creating realistic user notification patterns...');
            
            // High-engagement users (receive many notifications)
            $highEngagementUsers = $users->random(8);
            foreach ($highEngagementUsers as $user) {
                Notification::factory()
                    ->count(rand(15, 25))
                    ->create([
                        'user_id' => $user->id,
                        'type' => fake()->randomElement([
                            Notification::TYPE_NEW_MATCH,
                            Notification::TYPE_MATCH_MESSAGE,
                            Notification::TYPE_DAILY_MATCHES,
                            Notification::TYPE_PROFILE_VISIT
                        ]),
                        'status' => fake()->weightedElement([
                            Notification::STATUS_READ => 70,
                            Notification::STATUS_DELIVERED => 20,
                            Notification::STATUS_SENT => 10
                        ])
                    ]);
            }
            
            // New users (mostly system notifications)
            $newUsers = $users->random(6);
            foreach ($newUsers as $user) {
                Notification::factory()
                    ->count(rand(5, 10))
                    ->create([
                        'user_id' => $user->id,
                        'type' => fake()->randomElement([
                            Notification::TYPE_WELCOME,
                            Notification::TYPE_PROFILE_APPROVED,
                            Notification::TYPE_EMAIL_VERIFICATION,
                            Notification::TYPE_FEATURE_ANNOUNCEMENT
                        ]),
                        'category' => Notification::CATEGORY_SYSTEM,
                        'priority' => fake()->randomElement([
                            Notification::PRIORITY_HIGH,
                            Notification::PRIORITY_NORMAL
                        ])
                    ]);
            }
            
            // Premium users (commerce + premium features)
            $premiumUsers = $users->random(10);
            foreach ($premiumUsers as $user) {
                Notification::factory()
                    ->count(rand(8, 15))
                    ->create([
                        'user_id' => $user->id,
                        'type' => fake()->randomElement([
                            Notification::TYPE_SUBSCRIPTION_EXPIRING,
                            Notification::TYPE_PAYMENT_SUCCESS,
                            Notification::TYPE_PREMIUM_TRIAL,
                            Notification::TYPE_SUPER_LIKE_RECEIVED
                        ])
                    ]);
            }
            
            $this->command->info('✅ Created diverse notification scenarios');
        });
        
        // 13. Display seeding statistics
        $this->displayStatistics();
    }
    
    /**
     * Display comprehensive seeding statistics
     */
    private function displayStatistics(): void
    {
        $this->command->info('📊 Notification Seeding Statistics:');
        
        // Overall statistics
        $totalNotifications = Notification::count();
        $readNotifications = Notification::whereNotNull('read_at')->count();
        $unreadNotifications = Notification::whereNull('read_at')->count();
        $sentNotifications = Notification::whereNotNull('sent_at')->count();
        
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Total Notifications', number_format($totalNotifications)],
                ['Read Notifications', number_format($readNotifications)],
                ['Unread Notifications', number_format($unreadNotifications)],
                ['Sent Notifications', number_format($sentNotifications)],
            ]
        );
        
        // Status distribution
        $statusStats = Notification::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('📈 Status Distribution:');
        $this->command->table(
            ['Status', 'Count', 'Percentage'],
            $statusStats->map(function ($stat) use ($totalNotifications) {
                return [
                    strtoupper($stat->status),
                    number_format($stat->count),
                    number_format(($stat->count / $totalNotifications) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Type distribution (top 10)
        $typeStats = Notification::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
        
        $this->command->info('🎯 Top Notification Types:');
        $this->command->table(
            ['Type', 'Count', 'Percentage'],
            $typeStats->map(function ($stat) use ($totalNotifications) {
                return [
                    strtoupper(str_replace('_', ' ', $stat->type)),
                    number_format($stat->count),
                    number_format(($stat->count / $totalNotifications) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Category distribution
        $categoryStats = Notification::select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('🏷️ Category Distribution:');
        $this->command->table(
            ['Category', 'Count', 'Percentage'],
            $categoryStats->map(function ($stat) use ($totalNotifications) {
                return [
                    strtoupper($stat->category ?? 'UNKNOWN'),
                    number_format($stat->count),
                    number_format(($stat->count / $totalNotifications) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Priority distribution
        $priorityStats = Notification::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('⚡ Priority Distribution:');
        $this->command->table(
            ['Priority', 'Count', 'Percentage'],
            $priorityStats->map(function ($stat) use ($totalNotifications) {
                return [
                    strtoupper($stat->priority),
                    number_format($stat->count),
                    number_format(($stat->count / $totalNotifications) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Channel distribution
        $channelStats = [];
        $allNotifications = Notification::whereNotNull('channels')->get();
        
        foreach ($allNotifications as $notification) {
            foreach ($notification->channels as $channel) {
                $channelStats[$channel] = ($channelStats[$channel] ?? 0) + 1;
            }
        }
        
        arsort($channelStats);
        
        $this->command->info('📡 Channel Distribution:');
        $this->command->table(
            ['Channel', 'Count', 'Percentage'],
            collect($channelStats)->map(function ($count, $channel) use ($totalNotifications) {
                return [
                    strtoupper($channel),
                    number_format($count),
                    number_format(($count / $totalNotifications) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Read rate analysis
        $readRate = $totalNotifications > 0 ? 
            round(($readNotifications / $totalNotifications) * 100, 2) : 0;
        
        $this->command->info('📖 Engagement Metrics:');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Overall Read Rate', $readRate . '%'],
                ['Average Channels per Notification', 
                    number_format($allNotifications->avg(function ($n) {
                        return count($n->channels);
                    }), 1)
                ],
                ['Notifications with Clicks', 
                    number_format(Notification::whereNotNull('clicked_at')->count())
                ],
            ]
        );
        
        // Most active users (top 5)
        $activeUsers = DB::table('notifications')
            ->select('user_id', 'users.name', DB::raw('count(*) as notification_count'))
            ->join('users', 'notifications.user_id', '=', 'users.id')
            ->groupBy('user_id', 'users.name')
            ->orderByDesc('notification_count')
            ->limit(5)
            ->get();
        
        if ($activeUsers->isNotEmpty()) {
            $this->command->info('👤 Most Active Users:');
            $this->command->table(
                ['User', 'Notifications Received'],
                $activeUsers->map(function ($user) {
                    return [
                        $user->name,
                        number_format($user->notification_count)
                    ];
                })->toArray()
            );
        }
        
        // Campaign statistics
        $campaignCount = Notification::whereNotNull('campaign_id')->count();
        $abTestCount = Notification::whereNotNull('ab_test_variant')->count();
        $scheduledCount = Notification::where('status', Notification::STATUS_QUEUED)
            ->whereNotNull('scheduled_at')
            ->count();
        
        $this->command->info('🎯 Campaign & Testing:');
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Campaign Notifications', number_format($campaignCount)],
                ['A/B Test Notifications', number_format($abTestCount)],
                ['Scheduled Notifications', number_format($scheduledCount)],
            ]
        );
        
        // Temporal analysis
        $recentNotifications = Notification::where('created_at', '>=', now()->subDays(7))->count();
        $failedNotifications = Notification::where('status', Notification::STATUS_FAILED)->count();
        $expiredNotifications = Notification::where('status', Notification::STATUS_EXPIRED)->count();
        
        $this->command->info('⏰ Temporal Statistics:');
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Notifications in Last 7 Days', number_format($recentNotifications)],
                ['Failed Notifications', number_format($failedNotifications)],
                ['Expired Notifications', number_format($expiredNotifications)],
            ]
        );
        
        $this->command->info('✅ Notification seeding completed successfully!');
    }
}