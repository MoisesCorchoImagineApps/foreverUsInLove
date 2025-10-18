<?php

namespace Database\Seeders\Domains\Moderation;

use App\Domains\Moderation\Models\PQRS;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PQRSSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📋 Seeding PQRS records...');
        
        // Ensure we have enough users for PQRS relationships
        $userCount = User::count();
        if ($userCount < 20) {
            $this->command->warn("⚠️  Not enough users ($userCount) for realistic PQRS seeding. Creating additional users...");
            User::factory(30)->create();
        }
        
        // Get users and designate staff members
        $users = User::inRandomOrder()->limit(80)->get();
        $staffMembers = $users->random(12); // 12 staff members for assignment
        $managers = $staffMembers->random(4); // 4 managers for escalation
        
        DB::transaction(function () use ($users, $staffMembers, $managers) {
            
            // 1. Create high-priority PQRS requiring urgent attention (40 tickets)
            $this->command->info('Creating high-priority PQRS tickets...');
            
            PQRS::factory()
                ->count(25)
                ->highPriority()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'assigned_to' => function () use ($staffMembers) {
                        return $staffMembers->random()->id;
                    }
                ]);
            
            PQRS::factory()
                ->count(15)
                ->escalated()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'assigned_to' => function () use ($staffMembers) {
                        return $staffMembers->random()->id;
                    },
                    'escalated_to' => function () use ($managers) {
                        return $managers->random()->id;
                    }
                ]);
            
            // 2. Create resolved PQRS with various resolution types (100 tickets)
            $this->command->info('Creating resolved PQRS tickets...');
            
            PQRS::factory()
                ->count(100)
                ->resolved()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'assigned_to' => function () use ($staffMembers) {
                        return $staffMembers->random()->id;
                    }
                ]);
            
            // 3. Create category-specific PQRS
            $this->command->info('Creating category-specific PQRS tickets...');
            
            // Technical issues (50 tickets)
            PQRS::factory()
                ->count(50)
                ->technicalIssue()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'assigned_to' => function () use ($staffMembers) {
                        return $staffMembers->random()->id;
                    }
                ]);
            
            // Billing problems (35 tickets)
            PQRS::factory()
                ->count(35)
                ->billingIssue()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'assigned_to' => function () use ($staffMembers) {
                        return $staffMembers->random()->id;
                    }
                ]);
            
            // Safety issues (20 tickets)
            PQRS::factory()
                ->count(20)
                ->safetyIssue()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'assigned_to' => function () use ($staffMembers) {
                        return $staffMembers->random()->id;
                    }
                ]);
            
            // Feature requests (30 tickets)
            PQRS::factory()
                ->count(30)
                ->featureRequest()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'assigned_to' => function () use ($staffMembers) {
                        return $staffMembers->random()->id;
                    }
                ]);
            
            // 4. Create overdue PQRS (SLA breached) (25 tickets)
            $this->command->info('Creating overdue PQRS tickets...');
            
            PQRS::factory()
                ->count(25)
                ->overdue()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'assigned_to' => function () use ($staffMembers) {
                        return $staffMembers->random()->id;
                    }
                ]);
            
            // 5. Create realistic PQRS patterns
            $this->command->info('Creating realistic PQRS patterns...');
            
            // Heavy users who create multiple tickets
            $heavyUsers = $users->random(8);
            foreach ($heavyUsers as $user) {
                PQRS::factory()
                    ->count(rand(5, 12))
                    ->create([
                        'user_id' => $user->id,
                        'type' => fake()->randomElement(['PETICION', 'QUEJA', 'RECLAMO']),
                        'assigned_to' => function () use ($staffMembers) {
                            return $staffMembers->random()->id;
                        }
                    ]);
            }
            
            // High-activity staff members
            $activeStaff = $staffMembers->random(4);
            foreach ($activeStaff as $staff) {
                PQRS::factory()
                    ->count(rand(20, 35))
                    ->create([
                        'user_id' => function () use ($users) {
                            return $users->random()->id;
                        },
                        'assigned_to' => $staff->id,
                        'status' => fake()->randomElement([
                            'IN_PROGRESS', 'PENDING_INFO', 'RESOLVED', 'CLOSED'
                        ])
                    ]);
            }
            
            // 6. Create PQRS with specific characteristics
            $this->command->info('Creating specialized PQRS scenarios...');
            
            // Recently created unassigned tickets (20 tickets)
            PQRS::factory()
                ->count(20)
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'assigned_to' => null,
                    'status' => 'CREATED',
                    'created_at' => fake()->dateTimeBetween('-3 days', 'now')
                ]);
            
            // Tickets with high satisfaction ratings (15 tickets)
            PQRS::factory()
                ->count(15)
                ->resolved()
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'assigned_to' => function () use ($staffMembers) {
                        return $staffMembers->random()->id;
                    },
                    'satisfaction_rating' => fake()->numberBetween(4, 5),
                    'satisfaction_feedback' => fake()->sentence()
                ]);
            
            // Tickets that were reopened (10 tickets)
            PQRS::factory()
                ->count(10)
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'assigned_to' => function () use ($staffMembers) {
                        return $staffMembers->random()->id;
                    },
                    'status' => 'REOPENED'
                ]);
            
            // 7. Create seasonal/temporal patterns
            $this->command->info('Creating temporal PQRS patterns...');
            
            // Recent tickets (last 30 days) with various statuses
            PQRS::factory()
                ->count(60)
                ->create([
                    'user_id' => function () use ($users) {
                        return $users->random()->id;
                    },
                    'assigned_to' => function () use ($staffMembers) {
                        return fake()->boolean(80) ? $staffMembers->random()->id : null;
                    },
                    'created_at' => fake()->dateTimeBetween('-30 days', 'now')
                ]);
            
            $this->command->info('✅ Created diverse PQRS scenarios');
        });
        
        // 8. Display seeding statistics
        $this->displayStatistics();
    }
    
    /**
     * Display comprehensive seeding statistics
     */
    private function displayStatistics(): void
    {
        $this->command->info('📊 PQRS Seeding Statistics:');
        
        // Overall statistics
        $totalPQRS = PQRS::count();
        $assignedPQRS = PQRS::whereNotNull('assigned_to')->count();
        $unassignedPQRS = PQRS::whereNull('assigned_to')->count();
        $escalatedPQRS = PQRS::whereNotNull('escalated_to')->count();
        
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Total PQRS', number_format($totalPQRS)],
                ['Assigned PQRS', number_format($assignedPQRS)],
                ['Unassigned PQRS', number_format($unassignedPQRS)],
                ['Escalated PQRS', number_format($escalatedPQRS)],
            ]
        );
        
        // Type distribution (Spanish legal classification)
        $typeStats = PQRS::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('📈 PQRS Type Distribution:');
        $this->command->table(
            ['Type', 'Count', 'Percentage'],
            $typeStats->map(function ($stat) use ($totalPQRS) {
                return [
                    $stat->type,
                    number_format($stat->count),
                    number_format(($stat->count / $totalPQRS) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Status distribution
        $statusStats = PQRS::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('🎯 Status Distribution:');
        $this->command->table(
            ['Status', 'Count', 'Percentage'],
            $statusStats->map(function ($stat) use ($totalPQRS) {
                return [
                    $stat->status,
                    number_format($stat->count),
                    number_format(($stat->count / $totalPQRS) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Category distribution (top 10)
        $categoryStats = PQRS::select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
        
        $this->command->info('🏷️ Top Categories:');
        $this->command->table(
            ['Category', 'Count', 'Percentage'],
            $categoryStats->map(function ($stat) use ($totalPQRS) {
                return [
                    $stat->category,
                    number_format($stat->count),
                    number_format(($stat->count / $totalPQRS) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Priority distribution
        $priorityStats = PQRS::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('⚡ Priority Distribution:');
        $this->command->table(
            ['Priority', 'Count', 'Percentage'],
            $priorityStats->map(function ($stat) use ($totalPQRS) {
                return [
                    $stat->priority,
                    number_format($stat->count),
                    number_format(($stat->count / $totalPQRS) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // SLA Performance
        $slaBreached = PQRS::where('sla_breached', true)->count();
        $avgSLAHours = PQRS::avg('sla_hours');
        $resolvedOnTime = PQRS::where('status', 'RESOLVED')
            ->where('sla_breached', false)
            ->count();
        
        $this->command->info('⏰ SLA Performance:');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['SLA Breached', number_format($slaBreached)],
                ['Average SLA Hours', number_format($avgSLAHours ?? 0, 1)],
                ['Resolved On Time', number_format($resolvedOnTime)],
                ['SLA Compliance Rate', number_format((($totalPQRS - $slaBreached) / $totalPQRS) * 100, 1) . '%'],
            ]
        );
        
        // Most active staff members (top 6)
        $activeStaff = DB::table('pqrs')
            ->select('assigned_to', 'users.name', DB::raw('count(*) as ticket_count'))
            ->join('users', 'pqrs.assigned_to', '=', 'users.id')
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to', 'users.name')
            ->orderByDesc('ticket_count')
            ->limit(6)
            ->get();
        
        if ($activeStaff->isNotEmpty()) {
            $this->command->info('👨‍💼 Most Active Staff Members:');
            $this->command->table(
                ['Staff Member', 'Assigned Tickets'],
                $activeStaff->map(function ($staff) {
                    return [
                        $staff->name,
                        number_format($staff->ticket_count)
                    ];
                })->toArray()
            );
        }
        
        // Users with most tickets (top 5)
        $heavyUsers = DB::table('pqrs')
            ->select('user_id', 'users.name', DB::raw('count(*) as ticket_count'))
            ->join('users', 'pqrs.user_id', '=', 'users.id')
            ->groupBy('user_id', 'users.name')
            ->orderByDesc('ticket_count')
            ->limit(5)
            ->get();
        
        if ($heavyUsers->isNotEmpty()) {
            $this->command->info('📋 Users with Most Tickets:');
            $this->command->table(
                ['User', 'Tickets Created'],
                $heavyUsers->map(function ($user) {
                    return [
                        $user->name,
                        number_format($user->ticket_count)
                    ];
                })->toArray()
            );
        }
        
        // Satisfaction analysis
        $avgSatisfaction = PQRS::whereNotNull('satisfaction_rating')
            ->avg('satisfaction_rating');
        $satisfactionCount = PQRS::whereNotNull('satisfaction_rating')->count();
        $highSatisfaction = PQRS::where('satisfaction_rating', '>=', 4)->count();
        
        $this->command->info('😊 Customer Satisfaction:');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Responses with Rating', number_format($satisfactionCount)],
                ['Average Rating', number_format($avgSatisfaction ?? 0, 2)],
                ['High Satisfaction (4-5)', number_format($highSatisfaction)],
                ['Satisfaction Rate', $satisfactionCount > 0 ? number_format(($highSatisfaction / $satisfactionCount) * 100, 1) . '%' : 'N/A'],
            ]
        );
        
        // Temporal analysis
        $recentPQRS = PQRS::where('created_at', '>=', now()->subDays(30))->count();
        $resolvedPQRS = PQRS::where('status', 'RESOLVED')->count();
        $pendingPQRS = PQRS::whereIn('status', ['CREATED', 'ASSIGNED', 'IN_PROGRESS'])->count();
        $autoEscalated = PQRS::where('auto_escalated', true)->count();
        
        $this->command->info('📅 Temporal Statistics:');
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['PQRS in Last 30 Days', number_format($recentPQRS)],
                ['Resolved PQRS', number_format($resolvedPQRS)],
                ['Pending PQRS', number_format($pendingPQRS)],
                ['Auto-Escalated', number_format($autoEscalated)],
            ]
        );
        
        $this->command->info('✅ PQRS seeding completed successfully!');
    }
}