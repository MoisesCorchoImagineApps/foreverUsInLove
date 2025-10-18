<?php

namespace Database\Seeders\Domains\Moderation;

use App\Domains\Moderation\Models\Report;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📢 Seeding Report records...');
        
        // Ensure we have enough users for report relationships
        $userCount = User::count();
        if ($userCount < 25) {
            $this->command->warn("⚠️  Not enough users ($userCount) for realistic report seeding. Creating additional users...");
            User::factory(40)->create();
        }
        
        // Get users and designate moderators
        $users = User::inRandomOrder()->limit(100)->get();
        $moderators = $users->random(10); // 10 moderators for review
        $seniorModerators = $moderators->random(4); // 4 senior moderators for escalation
        
        DB::transaction(function () use ($users, $moderators, $seniorModerators) {
            
            // 1. Create high-priority reports requiring immediate attention (45 reports)
            $this->command->info('Creating high-priority reports...');
            
            Report::factory()
                ->count(30)
                ->highPriority()
                ->create([
                    'reporter_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reported_user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reviewed_by' => function () use ($moderators) {
                        return $moderators->random()->id;
                    }
                ]);
            
            Report::factory()
                ->count(15)
                ->escalated()
                ->create([
                    'reporter_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reported_user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reviewed_by' => function () use ($moderators) {
                        return $moderators->random()->id;
                    },
                    'escalated_to' => function () use ($seniorModerators) {
                        return $seniorModerators->random()->id;
                    }
                ]);
            
            // 2. Create resolved reports with various outcomes (80 reports)
            $this->command->info('Creating resolved reports...');
            
            Report::factory()
                ->count(80)
                ->resolved()
                ->create([
                    'reporter_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reported_user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reviewed_by' => function () use ($moderators) {
                        return $moderators->random()->id;
                    }
                ]);
            
            // 3. Create report-type specific scenarios
            $this->command->info('Creating report-type specific scenarios...');
            
            // Harassment reports (40 reports)
            Report::factory()
                ->count(40)
                ->harassment()
                ->create([
                    'reporter_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reported_user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // Spam reports (35 reports)
            Report::factory()
                ->count(35)
                ->spam()
                ->create([
                    'reporter_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reported_user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // Fake profile reports (30 reports)
            Report::factory()
                ->count(30)
                ->fakeProfile()
                ->create([
                    'reporter_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reported_user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // Safety concern reports (25 reports)
            Report::factory()
                ->count(25)
                ->safetyConcern()
                ->create([
                    'reporter_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reported_user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'escalated_to' => function () use ($seniorModerators) {
                        return $seniorModerators->random()->id;
                    }
                ]);
            
            // 4. Create dismissed reports (20 reports)
            $this->command->info('Creating dismissed reports...');
            
            Report::factory()
                ->count(20)
                ->dismissed()
                ->create([
                    'reporter_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reported_user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reviewed_by' => function () use ($moderators) {
                        return $moderators->random()->id;
                    }
                ]);
            
            // 5. Create pending reports (40 reports)
            $this->command->info('Creating pending reports...');
            
            Report::factory()
                ->count(40)
                ->pending()
                ->create([
                    'reporter_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reported_user_id' => function () use ($users) {
                        return $users->random()->id;
                    }
                ]);
            
            // 6. Create under investigation reports (30 reports)
            $this->command->info('Creating under investigation reports...');
            
            Report::factory()
                ->count(30)
                ->underInvestigation()
                ->create([
                    'reporter_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reported_user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reviewed_by' => function () use ($moderators) {
                        return $moderators->random()->id;
                    }
                ]);
            
            // 7. Create realistic reporting patterns
            $this->command->info('Creating realistic reporting patterns...');
            
            // Users who report frequently (maybe overly sensitive or vigilant)
            $frequentReporters = $users->random(6);
            foreach ($frequentReporters as $reporter) {
                $targets = $users->where('id', '!=', $reporter->id)->random(rand(8, 15));
                foreach ($targets as $target) {
                    // Avoid duplicate reports from same user about same target
                    if (!Report::where('reporter_id', $reporter->id)
                             ->where('reported_user_id', $target->id)
                             ->exists()) {
                        Report::factory()->create([
                            'reporter_id' => $reporter->id,
                            'reported_user_id' => $target->id,
                            'report_type' => fake()->randomElement([
                                'HARASSMENT', 'INAPPROPRIATE_CONTENT', 'SPAM'
                            ])
                        ]);
                    }
                }
            }
            
            // Users who get reported frequently (problematic users)
            $problematicUsers = $users->random(4);
            foreach ($problematicUsers as $problematic) {
                $reporters = $users->where('id', '!=', $problematic->id)->random(rand(15, 25));
                foreach ($reporters as $reporter) {
                    // Avoid duplicate reports from same user about same target
                    if (!Report::where('reporter_id', $reporter->id)
                             ->where('reported_user_id', $problematic->id)
                             ->exists()) {
                        Report::factory()->create([
                            'reporter_id' => $reporter->id,
                            'reported_user_id' => $problematic->id,
                            'report_type' => fake()->randomElement([
                                'HARASSMENT', 'SPAM', 'FAKE_PROFILE', 'INAPPROPRIATE_CONTENT'
                            ]),
                            'similar_reports_count' => fake()->numberBetween(3, 20)
                        ]);
                    }
                }
            }
            
            // 8. Create high-activity moderators with many reviews
            $this->command->info('Creating high-activity moderator assignments...');
            
            $activeModerators = $moderators->random(4);
            foreach ($activeModerators as $moderator) {
                Report::factory()
                    ->count(rand(20, 35))
                    ->create([
                        'reporter_id' => function () use ($users) {
                            return $users->random()->id;
                        },
                        'reported_user_id' => function () use ($users) {
                            return $users->random()->id;
                        },
                        'reviewed_by' => $moderator->id,
                        'status' => fake()->randomElement([
                            'IN_REVIEW', 'RESOLVED', 'DISMISSED', 'UNDER_INVESTIGATION'
                        ])
                    ]);
            }
            
            // 9. Create reports with appeals (15 reports)
            $this->command->info('Creating reports with appeals...');
            
            Report::factory()
                ->count(15)
                ->create([
                    'reporter_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reported_user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reviewed_by' => function () use ($moderators) {
                        return $moderators->random()->id;
                    },
                    'status' => 'RESOLVED',
                    'appeal_submitted' => true,
                    'appeal_submitted_at' => fake()->dateTimeBetween('-2 weeks', 'now'),
                    'appeal_data' => [
                        'reason' => fake()->sentence(),
                        'additional_evidence' => fake()->paragraph(),
                        'status' => fake()->randomElement(['PENDING', 'APPROVED', 'DENIED'])
                    ]
                ]);
            
            // 10. Create time-sensitive patterns
            $this->command->info('Creating temporal report patterns...');
            
            // Recent reports (last 2 weeks)
            Report::factory()
                ->count(50)
                ->create([
                    'reporter_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'reported_user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'created_at' => fake()->dateTimeBetween('-2 weeks', 'now')
                ]);
            
            $this->command->info('✅ Created diverse report scenarios');
        });
        
        // 11. Display seeding statistics
        $this->displayStatistics();
    }
    
    /**
     * Display comprehensive seeding statistics
     */
    private function displayStatistics(): void
    {
        $this->command->info('📊 Report Seeding Statistics:');
        
        // Overall statistics
        $totalReports = Report::count();
        $reviewedReports = Report::whereNotNull('reviewed_by')->count();
        $unreviewedReports = Report::whereNull('reviewed_by')->count();
        $escalatedReports = Report::whereNotNull('escalated_to')->count();
        
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Total Reports', number_format($totalReports)],
                ['Reviewed Reports', number_format($reviewedReports)],
                ['Unreviewed Reports', number_format($unreviewedReports)],
                ['Escalated Reports', number_format($escalatedReports)],
            ]
        );
        
        // Status distribution
        $statusStats = Report::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('📈 Status Distribution:');
        $this->command->table(
            ['Status', 'Count', 'Percentage'],
            $statusStats->map(function ($stat) use ($totalReports) {
                return [
                    $stat->status,
                    number_format($stat->count),
                    number_format(($stat->count / $totalReports) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Report type distribution
        $typeStats = Report::select('report_type', DB::raw('count(*) as count'))
            ->groupBy('report_type')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('🎯 Report Type Distribution:');
        $this->command->table(
            ['Report Type', 'Count', 'Percentage'],
            $typeStats->map(function ($stat) use ($totalReports) {
                return [
                    $stat->report_type,
                    number_format($stat->count),
                    number_format(($stat->count / $totalReports) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Priority distribution
        $priorityStats = Report::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('⚡ Priority Distribution:');
        $this->command->table(
            ['Priority', 'Count', 'Percentage'],
            $priorityStats->map(function ($stat) use ($totalReports) {
                return [
                    $stat->priority,
                    number_format($stat->count),
                    number_format(($stat->count / $totalReports) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // ML Analysis statistics
        $avgConfidence = Report::whereNotNull('ml_analysis->confidence_score')
            ->avg('ml_analysis->confidence_score');
        $highConfidenceReports = Report::whereRaw("JSON_EXTRACT(ml_analysis, '$.confidence_score') > 0.8")->count();
        $autoEscalatedReports = Report::where('auto_escalated', true)->count();
        
        $this->command->info('🤖 ML Analysis Statistics:');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Average ML Confidence', number_format($avgConfidence ?? 0, 3)],
                ['High Confidence Reports (>0.8)', number_format($highConfidenceReports)],
                ['Auto-Escalated Reports', number_format($autoEscalatedReports)],
            ]
        );
        
        // Most active moderators (top 5)
        $activeModerators = DB::table('reports')
            ->select('reviewed_by', 'users.name', DB::raw('count(*) as review_count'))
            ->join('users', 'reports.reviewed_by', '=', 'users.id')
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
        
        // Most reported users (top 5)
        $mostReported = DB::table('reports')
            ->select('reported_user_id', 'users.name', DB::raw('count(*) as report_count'))
            ->join('users', 'reports.reported_user_id', '=', 'users.id')
            ->groupBy('reported_user_id', 'users.name')
            ->orderByDesc('report_count')
            ->limit(5)
            ->get();
        
        if ($mostReported->isNotEmpty()) {
            $this->command->info('🚨 Most Reported Users:');
            $this->command->table(
                ['User', 'Reports'],
                $mostReported->map(function ($user) {
                    return [
                        $user->name,
                        number_format($user->report_count)
                    ];
                })->toArray()
            );
        }
        
        // Most active reporters (top 5)
        $activeReporters = DB::table('reports')
            ->select('reporter_id', 'users.name', DB::raw('count(*) as report_count'))
            ->join('users', 'reports.reporter_id', '=', 'users.id')
            ->groupBy('reporter_id', 'users.name')
            ->orderByDesc('report_count')
            ->limit(5)
            ->get();
        
        if ($activeReporters->isNotEmpty()) {
            $this->command->info('📢 Most Active Reporters:');
            $this->command->table(
                ['Reporter', 'Reports Made'],
                $activeReporters->map(function ($reporter) {
                    return [
                        $reporter->name,
                        number_format($reporter->report_count)
                    ];
                })->toArray()
            );
        }
        
        // Performance metrics
        $avgResponseTime = Report::whereNotNull('response_time_hours')
            ->avg('response_time_hours');
        $avgResolutionTime = Report::whereNotNull('resolution_time_hours')
            ->avg('resolution_time_hours');
        $slaMetReports = Report::where('sla_met', true)->count();
        $totalWithSLA = Report::whereNotNull('sla_met')->count();
        
        $this->command->info('⏰ Performance Metrics:');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Average Response Time', number_format($avgResponseTime ?? 0, 1) . ' hours'],
                ['Average Resolution Time', number_format($avgResolutionTime ?? 0, 1) . ' hours'],
                ['SLA Compliance Rate', $totalWithSLA > 0 ? number_format(($slaMetReports / $totalWithSLA) * 100, 1) . '%' : 'N/A'],
            ]
        );
        
        // Appeals statistics
        $appealsSubmitted = Report::where('appeal_submitted', true)->count();
        $recentReports = Report::where('reported_at', '>=', now()->subDays(14))->count();
        $resolvedReports = Report::where('status', 'RESOLVED')->count();
        $dismissedReports = Report::where('status', 'DISMISSED')->count();
        
        $this->command->info('📋 Additional Statistics:');
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Reports with Appeals', number_format($appealsSubmitted)],
                ['Reports in Last 14 Days', number_format($recentReports)],
                ['Resolved Reports', number_format($resolvedReports)],
                ['Dismissed Reports', number_format($dismissedReports)],
            ]
        );
        
        $this->command->info('✅ Report seeding completed successfully!');
    }
}