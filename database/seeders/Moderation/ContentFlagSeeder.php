<?php

namespace Database\Seeders\Domains\Moderation;

use App\Domains\Moderation\Models\ContentFlag;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentFlagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚩 Seeding ContentFlag records...');
        
        // Ensure we have enough users and moderators
        $userCount = User::count();
        if ($userCount < 15) {
            $this->command->warn("⚠️  Not enough users ($userCount) for realistic content flag seeding. Creating additional users...");
            User::factory(25)->create();
        }
        
        // Get users and designate some as moderators
        $users = User::inRandomOrder()->limit(60)->get();
        $moderators = $users->random(8); // 8 moderators for review
        
        DB::transaction(function () use ($users, $moderators) {
            
            // 1. Create high-risk content flags requiring immediate attention (50 flags)
            $this->command->info('Creating high-risk content flags...');
            
            ContentFlag::factory()
                ->count(30)
                ->highRisk()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'flagged_by' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            ContentFlag::factory()
                ->count(20)
                ->escalated()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reviewed_by' => function () use ($moderators) {
                        return $moderators->random()->id;
                    }
                ]);
            
            // 2. Create content flags that have been human reviewed (80 flags)
            $this->command->info('Creating human-reviewed content flags...');
            
            ContentFlag::factory()
                ->count(80)
                ->humanReviewed()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'flagged_by' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reviewed_by' => function () use ($moderators) {
                        return $moderators->random()->id;
                    }
                ]);
            
            // 3. Create false positive flags (25 flags)
            $this->command->info('Creating false positive flags...');
            
            ContentFlag::factory()
                ->count(25)
                ->falsePositive()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reviewed_by' => function () use ($moderators) {
                        return $moderators->random()->id;
                    }
                ]);
            
            // 4. Create pending review flags (60 flags)
            $this->command->info('Creating pending review flags...');
            
            ContentFlag::factory()
                ->count(60)
                ->pendingReview()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'flagged_by' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 5. Create content-type specific flags
            $this->command->info('Creating content-type specific flags...');
            
            // Text content flags (40 flags)
            ContentFlag::factory()
                ->count(40)
                ->textContent()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'flagged_by' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // Image content flags (35 flags)
            ContentFlag::factory()
                ->count(35)
                ->imageContent()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'flagged_by' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // Behavior analysis flags (30 flags)
            ContentFlag::factory()
                ->count(30)
                ->behaviorAnalysis()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 6. Create realistic flag patterns
            $this->command->info('Creating realistic flag patterns...');
            
            // Some users who create frequently flagged content
            $problematicUsers = $users->random(5);
            foreach ($problematicUsers as $user) {
                ContentFlag::factory()
                    ->count(rand(8, 15))
                    ->create([
                        'user_id' => $user->id,
                        'flag_category' => fake()->randomElement([
                            'SPAM', 'HARASSMENT', 'INAPPROPRIATE_CONTENT', 'HATE_SPEECH'
                        ]),
                        'flagged_by' => function () use ($users, $user) {
                            return $users->where('id', '!=', $user->id)->random()->id;
                        }
                    ]);
            }
            
            // High-activity moderators with many reviews
            $activeModerators = $moderators->random(3);
            foreach ($activeModerators as $moderator) {
                ContentFlag::factory()
                    ->count(rand(25, 40))
                    ->create([
                        'user_id' => function () use ($users) {
                            return $users->random()->id;
                        },
                        'reviewed_by' => $moderator->id,
                        'status' => fake()->randomElement(['CONFIRMED', 'DISMISSED', 'RESOLVED'])
                    ]);
            }
            
            // 7. Create system-generated flags (ML-only, no human reporter)
            $this->command->info('Creating system-generated ML flags...');
            
            ContentFlag::factory()
                ->count(45)
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'flagged_by' => null, // System-generated
                    'confidence_score' => fake()->randomFloat(2, 0.7, 0.95),
                    'requires_human_review' => true,
                    'auto_escalated' => fake()->boolean(40)
                ]);
            
            $this->command->info('✅ Created diverse content flag scenarios');
        });
        
        // 8. Display seeding statistics
        $this->displayStatistics();
    }
    
    /**
     * Display comprehensive seeding statistics
     */
    private function displayStatistics(): void
    {
        $this->command->info('📊 ContentFlag Seeding Statistics:');
        
        // Overall statistics
        $totalFlags = ContentFlag::count();
        $systemFlags = ContentFlag::whereNull('flagged_by')->count();
        $userFlags = ContentFlag::whereNotNull('flagged_by')->count();
        $reviewedFlags = ContentFlag::whereNotNull('reviewed_by')->count();
        
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Total Content Flags', number_format($totalFlags)],
                ['System-Generated Flags', number_format($systemFlags)],
                ['User-Reported Flags', number_format($userFlags)],
                ['Reviewed Flags', number_format($reviewedFlags)],
            ]
        );
        
        // Status distribution
        $statusStats = ContentFlag::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('📈 Status Distribution:');
        $this->command->table(
            ['Status', 'Count', 'Percentage'],
            $statusStats->map(function ($stat) use ($totalFlags) {
                return [
                    $stat->status,
                    number_format($stat->count),
                    number_format(($stat->count / $totalFlags) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Content type distribution
        $contentTypeStats = ContentFlag::select('content_type', DB::raw('count(*) as count'))
            ->groupBy('content_type')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('🎯 Content Type Distribution:');
        $this->command->table(
            ['Content Type', 'Count', 'Percentage'],
            $contentTypeStats->map(function ($stat) use ($totalFlags) {
                return [
                    $stat->content_type,
                    number_format($stat->count),
                    number_format(($stat->count / $totalFlags) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Flag category distribution (top 8)
        $categoryStats = ContentFlag::select('flag_category', DB::raw('count(*) as count'))
            ->groupBy('flag_category')
            ->orderByDesc('count')
            ->limit(8)
            ->get();
        
        $this->command->info('🚨 Top Flag Categories:');
        $this->command->table(
            ['Category', 'Count', 'Percentage'],
            $categoryStats->map(function ($stat) use ($totalFlags) {
                return [
                    $stat->flag_category,
                    number_format($stat->count),
                    number_format(($stat->count / $totalFlags) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Priority distribution
        $priorityStats = ContentFlag::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('⚡ Priority Distribution:');
        $this->command->table(
            ['Priority', 'Count', 'Percentage'],
            $priorityStats->map(function ($stat) use ($totalFlags) {
                return [
                    $stat->priority,
                    number_format($stat->count),
                    number_format(($stat->count / $totalFlags) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // ML Analysis statistics
        $avgConfidence = ContentFlag::whereNotNull('confidence_score')
            ->avg('confidence_score');
        $highConfidenceFlags = ContentFlag::where('confidence_score', '>', 0.8)->count();
        $autoEscalatedFlags = ContentFlag::where('auto_escalated', true)->count();
        $humanReviewRequired = ContentFlag::where('requires_human_review', true)->count();
        
        $this->command->info('🤖 ML Analysis Statistics:');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Average ML Confidence', number_format($avgConfidence ?? 0, 3)],
                ['High Confidence Flags (>0.8)', number_format($highConfidenceFlags)],
                ['Auto-Escalated Flags', number_format($autoEscalatedFlags)],
                ['Requiring Human Review', number_format($humanReviewRequired)],
            ]
        );
        
        // Most active moderators (top 5)
        $activeModerators = DB::table('content_flags')
            ->select('reviewed_by', 'users.name', DB::raw('count(*) as review_count'))
            ->join('users', 'content_flags.reviewed_by', '=', 'users.id')
            ->whereNotNull('reviewed_by')
            ->groupBy('reviewed_by', 'users.name')
            ->orderByDesc('review_count')
            ->limit(5)
            ->get();
        
        if ($activeModerators->isNotEmpty()) {
            $this->command->info('👨‍💼 Most Active Moderators:');
            $this->command->table(
                ['Moderator', 'Reviews'],
                $activeModerators->map(function ($mod) {
                    return [
                        $mod->name,
                        number_format($mod->review_count)
                    ];
                })->toArray()
            );
        }
        
        // Users with most flagged content (top 5)
        $mostFlagged = DB::table('content_flags')
            ->select('user_id', 'users.name', DB::raw('count(*) as flag_count'))
            ->join('users', 'content_flags.user_id', '=', 'users.id')
            ->groupBy('user_id', 'users.name')
            ->orderByDesc('flag_count')
            ->limit(5)
            ->get();
        
        if ($mostFlagged->isNotEmpty()) {
            $this->command->info('🚩 Users with Most Flagged Content:');
            $this->command->table(
                ['User', 'Flags'],
                $mostFlagged->map(function ($user) {
                    return [
                        $user->name,
                        number_format($user->flag_count)
                    ];
                })->toArray()
            );
        }
        
        // Temporal analysis
        $recentFlags = ContentFlag::where('created_at', '>=', now()->subDays(7))->count();
        $resolvedFlags = ContentFlag::where('status', 'RESOLVED')->count();
        $pendingFlags = ContentFlag::whereIn('status', ['PENDING', 'UNDER_REVIEW'])->count();
        
        $this->command->info('⏰ Temporal Statistics:');
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Flags in Last 7 Days', number_format($recentFlags)],
                ['Resolved Flags', number_format($resolvedFlags)],
                ['Pending Review', number_format($pendingFlags)],
            ]
        );
        
        $this->command->info('✅ ContentFlag seeding completed successfully!');
    }
}