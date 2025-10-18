<?php

namespace Database\Seeders;

use App\Models\Auth\User;
use App\Models\User\PersonalityTest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PersonalityTestSeeder - Creates comprehensive personality test data
 *
 * Generates personality tests for existing users to enhance matching algorithms
 * Creates realistic distribution of personality types and test completion rates
 */
class PersonalityTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🧠 Creating personality test ecosystem...');

        // Disable query log for performance
        DB::connection()->disableQueryLog();

        $this->createCompletedTests();
        $this->createInProgressTests();
        $this->createDiverseTestTypes();
        $this->createRetakeScenarios();

        $this->command->info('✅ Personality test ecosystem created successfully!');
    }

    /**
     * Create completed tests for users who don't have them
     */
    private function createCompletedTests(): void
    {
        $this->command->info('📝 Creating completed personality tests...');

        // Get active users without MBTI tests
        $usersWithoutMbti = User::whereHas('profile')
            ->where('status', 'active')
            ->whereDoesntHave('personalityTests', function ($query) {
                $query->where('test_type', 'mbti')->where('status', 'completed');
            })
            ->limit(100)
            ->get();

        foreach ($usersWithoutMbti as $user) {
            PersonalityTest::factory()
                ->for($user)
                ->mbti()
                ->completed()
                ->create();
        }

        // Get users without Love Language tests
        $usersWithoutLoveLanguage = User::whereHas('profile')
            ->where('status', 'active')
            ->whereDoesntHave('personalityTests', function ($query) {
                $query->where('test_type', 'love_language')->where('status', 'completed');
            })
            ->limit(80)
            ->get();

        foreach ($usersWithoutLoveLanguage as $user) {
            PersonalityTest::factory()
                ->for($user)
                ->loveLanguage()
                ->completed()
                ->create();
        }

        // Add Big Five tests to premium users
        $premiumUsers = User::whereHas('settings', function ($query) {
            $query->where('is_premium', true);
        })
            ->whereDoesntHave('personalityTests', function ($query) {
                $query->where('test_type', 'big_five')->where('status', 'completed');
            })
            ->limit(50)
            ->get();

        foreach ($premiumUsers as $user) {
            PersonalityTest::factory()
                ->for($user)
                ->bigFive()
                ->completed()
                ->highAccuracy()
                ->create();
        }

        $this->command->info('  ✓ Created completed tests for existing users');
    }

    /**
     * Create in-progress tests to simulate realistic completion rates
     */
    private function createInProgressTests(): void
    {
        $this->command->info('⏳ Creating in-progress personality tests...');

        // Get random active users for in-progress tests
        $activeUsers = User::where('status', 'active')
            ->whereHas('profile')
            ->inRandomOrder()
            ->limit(30)
            ->get();

        foreach ($activeUsers as $user) {
            // Create random in-progress test
            $testTypes = ['mbti', 'big_five', 'attachment_style', 'enneagram'];
            $testType = fake()->randomElement($testTypes);

            PersonalityTest::factory()
                ->for($user)
                ->state(['test_type' => $testType])
                ->inProgress()
                ->create();
        }

        $this->command->info('  ✓ Created 30 in-progress tests');
    }

    /**
     * Create diverse test types for comprehensive personality profiles
     */
    private function createDiverseTestTypes(): void
    {
        $this->command->info('🎭 Creating diverse personality test types...');

        // Get highly engaged users for comprehensive testing
        $engagedUsers = User::whereHas('profile', function ($query) {
            $query->where('profile_views_count', '>', 100);
        })
            ->orWhereHas('settings', function ($query) {
                $query->where('is_premium', true);
            })
            ->limit(40)
            ->get();

        foreach ($engagedUsers as $user) {
            $existingTypes = $user->personalityTests()
                ->where('status', 'completed')
                ->pluck('test_type')
                ->toArray();

            $allTypes = ['mbti', 'big_five', 'love_language', 'attachment_style', 'enneagram'];
            $missingTypes = array_diff($allTypes, $existingTypes);

            // Add 1-3 missing test types
            $typesToAdd = fake()->randomElements(
                $missingTypes,
                min(fake()->numberBetween(1, 3), count($missingTypes))
            );

            foreach ($typesToAdd as $testType) {
                PersonalityTest::factory()
                    ->for($user)
                    ->state(['test_type' => $testType])
                    ->completed()
                    ->create([
                        'accuracy_score' => fake()->randomFloat(3, 0.75, 0.98),
                        'is_public' => fake()->boolean(85),
                        'use_for_matching' => fake()->boolean(90),
                    ]);
            }
        }

        $this->command->info('  ✓ Created diverse test types for engaged users');
    }

    /**
     * Create retake scenarios for testing user behavior
     */
    private function createRetakeScenarios(): void
    {
        $this->command->info('🔄 Creating test retake scenarios...');

        // Get users with existing tests for retakes
        $usersWithTests = User::whereHas('personalityTests', function ($query) {
            $query->where('status', 'completed');
        })
            ->limit(20)
            ->get();

        foreach ($usersWithTests as $user) {
            $existingTest = $user->personalityTests()
                ->where('status', 'completed')
                ->first();

            if ($existingTest) {
                // Create a retake with different results
                PersonalityTest::factory()
                    ->for($user)
                    ->state(['test_type' => $existingTest->test_type])
                    ->completed()
                    ->create([
                        'retake_count' => fake()->numberBetween(1, 3),
                        'accuracy_score' => fake()->randomFloat(3, 0.70, 0.95),
                        'created_at' => fake()->dateTimeBetween($existingTest->created_at, 'now'),
                    ]);
            }
        }

        $this->command->info('  ✓ Created retake scenarios');
    }

    /**
     * Create specific personality type distributions for testing
     */
    private function createSpecificDistributions(): void
    {
        $this->command->info('📊 Creating specific personality distributions...');

        // Create MBTI type distribution similar to real-world statistics
        $mbtiDistribution = [
            'ISFJ' => 14,
            'ESFJ' => 12,
            'ISTJ' => 12,
            'ISFP' => 9,
            'ESFP' => 8,
            'INFP' => 4,
            'ISTP' => 5,
            'INTP' => 3,
            'ESTP' => 4,
            'ESTJ' => 8,
            'ENFP' => 8,
            'INFJ' => 2,
            'ENFJ' => 2,
            'ENTP' => 2,
            'ENTJ' => 2,
            'INTJ' => 2,
        ];

        $remainingUsers = User::where('status', 'active')
            ->whereHas('profile')
            ->whereDoesntHave('personalityTests', function ($query) {
                $query->where('test_type', 'mbti');
            })
            ->limit(100)
            ->get();

        $userIndex = 0;
        foreach ($mbtiDistribution as $type => $percentage) {
            $count = intval($percentage);

            for ($i = 0; $i < $count && $userIndex < $remainingUsers->count(); $i++) {
                PersonalityTest::factory()
                    ->for($remainingUsers[$userIndex])
                    ->completed()
                    ->create([
                        'test_type' => 'mbti',
                        'primary_result' => $type,
                        'trait_scores' => $this->generateMbtiScoresForType($type),
                    ]);

                $userIndex++;
            }
        }

        $this->command->info('  ✓ Created realistic MBTI distribution');
    }

    /**
     * Generate MBTI scores for specific type
     */
    private function generateMbtiScoresForType(string $type): array
    {
        $scores = [];

        // Generate scores that match the type
        $scores['E'] = str_contains($type, 'E') ? fake()->numberBetween(60, 85) : fake()->numberBetween(15, 45);
        $scores['I'] = str_contains($type, 'I') ? fake()->numberBetween(60, 85) : fake()->numberBetween(15, 45);
        $scores['S'] = str_contains($type, 'S') ? fake()->numberBetween(60, 85) : fake()->numberBetween(15, 45);
        $scores['N'] = str_contains($type, 'N') ? fake()->numberBetween(60, 85) : fake()->numberBetween(15, 45);
        $scores['T'] = str_contains($type, 'T') ? fake()->numberBetween(60, 85) : fake()->numberBetween(15, 45);
        $scores['F'] = str_contains($type, 'F') ? fake()->numberBetween(60, 85) : fake()->numberBetween(15, 45);
        $scores['J'] = str_contains($type, 'J') ? fake()->numberBetween(60, 85) : fake()->numberBetween(15, 45);
        $scores['P'] = str_contains($type, 'P') ? fake()->numberBetween(60, 85) : fake()->numberBetween(15, 45);

        return $scores;
    }
}
