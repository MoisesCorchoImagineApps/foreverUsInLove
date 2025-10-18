<?php

namespace Database\Seeders\Domains\Moderation;

use App\Domains\Moderation\Models\Block;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BlockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚫 Seeding Block records...');
        
        // Ensure we have enough users for blocking relationships
        $userCount = User::count();
        if ($userCount < 10) {
            $this->command->warn("⚠️  Not enough users ($userCount) for realistic block seeding. Creating additional users...");
            User::factory(20)->create();
        }
        
        // Get users for seeding
        $users = User::inRandomOrder()->limit(50)->get();
        
        DB::transaction(function () use ($users) {
            
            // 1. Create diverse block scenarios (200 blocks)
            $this->command->info('Creating diverse block scenarios...');
            
            // High-priority harassment blocks (20 blocks)
            Block::factory()
                ->count(20)
                ->harassment()
                ->create();
            
            // Temporary blocks that expire (25 blocks)
            Block::factory()
                ->count(25)
                ->temporary()
                ->create();
            
            // Shadow bans for subtle violations (15 blocks)
            Block::factory()
                ->count(15)
                ->shadowBan()
                ->create();
            
            // Partial blocks with selective features (20 blocks)
            Block::factory()
                ->count(20)
                ->partial()
                ->create();
            
            // Mutual blocks between users (10 pairs = 20 blocks)
            for ($i = 0; $i < 10; $i++) {
                $user1 = $users->random();
                $user2 = $users->where('id', '!=', $user1->id)->random();
                
                Block::factory()
                    ->mutual()
                    ->create([
                        'blocker_id' => $user1->id,
                        'blocked_id' => $user2->id,
                    ]);
                
                Block::factory()
                    ->mutual()
                    ->create([
                        'blocker_id' => $user2->id,
                        'blocked_id' => $user1->id,
                    ]);
            }
            
            // Administrative blocks (15 blocks)
            Block::factory()
                ->count(15)
                ->adminBlock()
                ->create();
            
            // System-generated blocks (12 blocks)
            Block::factory()
                ->count(12)
                ->systemBlock()
                ->create();
            
            // Fake profile blocks (18 blocks)
            Block::factory()
                ->count(18)
                ->fakeProfile()
                ->create();
            
            // Expired blocks (inactive) (25 blocks)
            Block::factory()
                ->count(25)
                ->expired()
                ->create();
            
            // Standard blocks (remaining)
            Block::factory()
                ->count(40)
                ->create();
            
            $this->command->info('✅ Created diverse block scenarios');
            
            // 2. Create realistic blocking patterns
            $this->command->info('Creating realistic blocking patterns...');
            
            // Users who block frequently (problem reporters or overly sensitive)
            $frequentBlockers = $users->random(5);
            foreach ($frequentBlockers as $blocker) {
                $targets = $users->where('id', '!=', $blocker->id)->random(8);
                foreach ($targets as $target) {
                    if (!Block::where('blocker_id', $blocker->id)
                             ->where('blocked_id', $target->id)
                             ->exists()) {
                        Block::factory()->create([
                            'blocker_id' => $blocker->id,
                            'blocked_id' => $target->id,
                            'reason' => fake()->randomElement([
                                'HARASSMENT', 'INAPPROPRIATE_BEHAVIOR', 'UNWANTED_CONTACT'
                            ])
                        ]);
                    }
                }
            }
            
            // Users who get blocked frequently (problematic users)
            $frequentlyBlocked = $users->random(3);
            foreach ($frequentlyBlocked as $problematic) {
                $blockers = $users->where('id', '!=', $problematic->id)->random(12);
                foreach ($blockers as $blocker) {
                    if (!Block::where('blocker_id', $blocker->id)
                             ->where('blocked_id', $problematic->id)
                             ->exists()) {
                        Block::factory()->create([
                            'blocker_id' => $blocker->id,
                            'blocked_id' => $problematic->id,
                            'reason' => fake()->randomElement([
                                'HARASSMENT', 'SPAM', 'OFFENSIVE_CONTENT', 'FAKE_PROFILE'
                            ])
                        ]);
                    }
                }
            }
            
            $this->command->info('✅ Created realistic blocking patterns');
        });
        
        // 3. Display seeding statistics
        $this->displayStatistics();
    }
    
    /**
     * Display comprehensive seeding statistics
     */
    private function displayStatistics(): void
    {
        $this->command->info('📊 Block Seeding Statistics:');
        
        // Overall statistics
        $totalBlocks = Block::count();
        $activeBlocks = Block::where('is_active', true)->count();
        $expiredBlocks = Block::where('is_active', false)->count();
        
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Total Blocks', number_format($totalBlocks)],
                ['Active Blocks', number_format($activeBlocks)],
                ['Expired/Inactive Blocks', number_format($expiredBlocks)],
            ]
        );
        
        // Block type distribution
        $typeStats = Block::select('block_type', DB::raw('count(*) as count'))
            ->groupBy('block_type')
            ->orderByDesc('count')
            ->get();
        
        $this->command->info('📈 Block Type Distribution:');
        $this->command->table(
            ['Block Type', 'Count', 'Percentage'],
            $typeStats->map(function ($stat) use ($totalBlocks) {
                return [
                    $stat->block_type,
                    number_format($stat->count),
                    number_format(($stat->count / $totalBlocks) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Reason distribution
        $reasonStats = Block::select('reason', DB::raw('count(*) as count'))
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit(8)
            ->get();
        
        $this->command->info('🎯 Top Block Reasons:');
        $this->command->table(
            ['Reason', 'Count', 'Percentage'],
            $reasonStats->map(function ($stat) use ($totalBlocks) {
                return [
                    $stat->reason,
                    number_format($stat->count),
                    number_format(($stat->count / $totalBlocks) * 100, 1) . '%'
                ];
            })->toArray()
        );
        
        // Administrative vs User blocks
        $adminBlocks = Block::where('is_admin_block', true)->count();
        $systemBlocks = Block::where('is_system_block', true)->count();
        $userBlocks = Block::where('is_admin_block', false)
                          ->where('is_system_block', false)
                          ->count();
        
        $this->command->info('👥 Block Origin Distribution:');
        $this->command->table(
            ['Origin', 'Count', 'Percentage'],
            [
                ['User-Initiated', number_format($userBlocks), number_format(($userBlocks / $totalBlocks) * 100, 1) . '%'],
                ['Admin-Initiated', number_format($adminBlocks), number_format(($adminBlocks / $totalBlocks) * 100, 1) . '%'],
                ['System-Initiated', number_format($systemBlocks), number_format(($systemBlocks / $totalBlocks) * 100, 1) . '%'],
            ]
        );
        
        // Most blocked users (top 5)
        $mostBlocked = DB::table('blocks')
            ->select('blocked_id', 'users.name', DB::raw('count(*) as block_count'))
            ->join('users', 'blocks.blocked_id', '=', 'users.id')
            ->where('blocks.is_active', true)
            ->groupBy('blocked_id', 'users.name')
            ->orderByDesc('block_count')
            ->limit(5)
            ->get();
        
        if ($mostBlocked->isNotEmpty()) {
            $this->command->info('🚫 Most Blocked Users:');
            $this->command->table(
                ['User', 'Times Blocked'],
                $mostBlocked->map(function ($user) {
                    return [
                        $user->name,
                        number_format($user->block_count)
                    ];
                })->toArray()
            );
        }
        
        // Temporal analysis
        $recentBlocks = Block::where('created_at', '>=', now()->subDays(7))->count();
        $mutualBlocks = Block::where('is_mutual', true)->count();
        $temporaryBlocks = Block::whereNotNull('expires_at')->count();
        
        $this->command->info('⏰ Temporal Statistics:');
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Blocks in Last 7 Days', number_format($recentBlocks)],
                ['Mutual Blocks', number_format($mutualBlocks)],
                ['Temporary Blocks', number_format($temporaryBlocks)],
            ]
        );
        
        $this->command->info('✅ Block seeding completed successfully!');
    }
}