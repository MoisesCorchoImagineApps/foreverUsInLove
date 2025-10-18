<?php

namespace Database\Factories\User;

use App\Models\User\PersonalityTest;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * PersonalityTestFactory - Factory for PersonalityTest Model
 * 
 * Generates comprehensive personality test data with realistic results
 * Handles all User model relationships and psychology business logic
 * Supports MBTI, Big Five, Love Languages, Attachment Styles, and Enneagram tests
 */
class PersonalityTestFactory extends Factory
{
    protected $model = PersonalityTest::class;

    // MBTI Types with descriptions and traits
    private static $mbtiTypes = [
        'INTJ' => ['Architect', 'Strategic', 'Independent', 'Analytical'],
        'INTP' => ['Thinker', 'Curious', 'Flexible', 'Logical'],
        'ENTJ' => ['Commander', 'Bold', 'Strong-willed', 'Strategic'],
        'ENTP' => ['Debater', 'Quick', 'Ingenious', 'Stimulating'],
        'INFJ' => ['Advocate', 'Insightful', 'Principled', 'Compassionate'],
        'INFP' => ['Mediator', 'Empathetic', 'Generous', 'Open-minded'],
        'ENFJ' => ['Protagonist', 'Charismatic', 'Reliable', 'Altruistic'],
        'ENFP' => ['Campaigner', 'Enthusiastic', 'Creative', 'Sociable'],
        'ISTJ' => ['Logistician', 'Practical', 'Fact-minded', 'Reliable'],
        'ISFJ' => ['Protector', 'Warm-hearted', 'Conscientious', 'Harmonious'],
        'ESTJ' => ['Executive', 'Organized', 'Traditional', 'Clear'],
        'ESFJ' => ['Consul', 'Caring', 'Social', 'Popular'],
        'ISTP' => ['Virtuoso', 'Bold', 'Practical', 'Experimental'],
        'ISFP' => ['Adventurer', 'Flexible', 'Charming', 'Artistic'],
        'ESTP' => ['Entrepreneur', 'Energetic', 'Perceptive', 'Spontaneous'],
        'ESFP' => ['Entertainer', 'Spontaneous', 'Enthusiastic', 'People-focused'],
    ];

    // Big Five traits with descriptions
    private static $bigFiveTraits = [
        'openness' => ['Creative', 'Imaginative', 'Intellectual', 'Artistic'],
        'conscientiousness' => ['Organized', 'Responsible', 'Dependable', 'Persistent'],
        'extraversion' => ['Outgoing', 'Energetic', 'Assertive', 'Sociable'],
        'agreeableness' => ['Cooperative', 'Trusting', 'Good-natured', 'Helpful'],
        'neuroticism' => ['Emotional', 'Stress-sensitive', 'Moody', 'Anxious'],
    ];

    // Love Languages
    private static $loveLanguages = [
        'words_of_affirmation' => ['Verbal appreciation', 'Compliments', 'Encouraging words'],
        'acts_of_service' => ['Helpful actions', 'Doing tasks', 'Practical support'],
        'receiving_gifts' => ['Thoughtful presents', 'Symbolic gestures', 'Surprises'],
        'quality_time' => ['Undivided attention', 'Shared activities', 'Deep conversation'],
        'physical_touch' => ['Affection', 'Closeness', 'Physical connection'],
    ];

    // Attachment Styles
    private static $attachmentStyles = [
        'secure' => ['Comfortable with intimacy', 'Self-reliant', 'Trusting'],
        'anxious' => ['Seeks closeness', 'Worries about relationships', 'Sensitive'],
        'avoidant' => ['Values independence', 'Uncomfortable with closeness', 'Self-sufficient'],
        'fearful_avoidant' => ['Wants close relationships', 'Fears being hurt', 'Mixed feelings'],
    ];

    // Enneagram Types
    private static $enneagramTypes = [
        1 => ['Perfectionist', 'Principled', 'Purposeful', 'Self-controlled'],
        2 => ['Helper', 'Generous', 'People-pleasing', 'Possessive'],
        3 => ['Achiever', 'Adaptable', 'Driven', 'Image-conscious'],
        4 => ['Individualist', 'Expressive', 'Dramatic', 'Self-absorbed'],
        5 => ['Investigator', 'Perceptive', 'Innovative', 'Secretive'],
        6 => ['Loyalist', 'Engaging', 'Responsible', 'Anxious'],
        7 => ['Enthusiast', 'Spontaneous', 'Versatile', 'Scattered'],
        8 => ['Challenger', 'Self-confident', 'Decisive', 'Willful'],
        9 => ['Peacemaker', 'Receptive', 'Reassuring', 'Complacent'],
    ];

    public function definition(): array
    {
        $testType = $this->faker->randomElement([
            'mbti', 'big_five', 'love_language', 'attachment_style', 'enneagram'
        ]);

        $isCompleted = $this->faker->boolean(70); // 70% completed tests
        $testData = $this->generateTestData($testType, $isCompleted);

        return [
            'user_id' => User::factory(),
            'test_type' => $testType,
            'test_version' => $this->generateTestVersion($testType),
            'questions' => $testData['questions'],
            'answers' => $testData['answers'],
            'results' => $testData['results'],
            'primary_result' => $testData['primary_result'],
            'secondary_results' => $testData['secondary_results'],
            'trait_scores' => $testData['trait_scores'],
            'completion_percentage' => $testData['completion_percentage'],
            'status' => $testData['status'],
            'total_questions' => $testData['total_questions'],
            'answered_questions' => $testData['answered_questions'],
            'time_spent_seconds' => $testData['time_spent_seconds'],
            'insights' => $testData['insights'],
            'compatibility_notes' => $testData['compatibility_notes'],
            'accuracy_score' => $testData['accuracy_score'],
            'started_at' => $testData['started_at'],
            'completed_at' => $testData['completed_at'],
            'expires_at' => $testData['expires_at'],
            'is_public' => $this->faker->boolean(80),
            'use_for_matching' => $this->faker->boolean(85),
            'retake_count' => $this->faker->numberBetween(0, 3),
            'created_at' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Generate test version based on type
     */
    private function generateTestVersion(string $testType): string
    {
        return match($testType) {
            'mbti' => $this->faker->randomElement(['v2.1', 'v2.0', 'v1.9']),
            'big_five' => $this->faker->randomElement(['v3.0', 'v2.8', 'v2.7']),
            'love_language' => $this->faker->randomElement(['v1.5', 'v1.4']),
            'attachment_style' => $this->faker->randomElement(['v2.0', 'v1.8']),
            'enneagram' => $this->faker->randomElement(['v1.7', 'v1.6']),
            default => 'v1.0',
        };
    }

    /**
     * Generate comprehensive test data based on type and completion status
     */
    private function generateTestData(string $testType, bool $isCompleted): array
    {
        $totalQuestions = $this->getQuestionCount($testType);
        $answeredQuestions = $isCompleted ? $totalQuestions : $this->faker->numberBetween(1, $totalQuestions - 1);
        $completionPercentage = (int) round(($answeredQuestions / $totalQuestions) * 100);
        
        $startedAt = $this->faker->dateTimeBetween('-6 months', '-1 day');
        $timeSpent = $isCompleted 
            ? $this->faker->numberBetween(600, 2400) // 10-40 minutes
            : $this->faker->numberBetween(60, 600);   // 1-10 minutes for incomplete
            
        $completedAt = $isCompleted 
            ? Carbon::parse($startedAt)->addSeconds($timeSpent)
            : null;

        $data = [
            'total_questions' => $totalQuestions,
            'answered_questions' => $answeredQuestions,
            'completion_percentage' => $completionPercentage,
            'status' => $isCompleted ? 'completed' : 'in_progress',
            'time_spent_seconds' => $timeSpent,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'expires_at' => $isCompleted 
                ? Carbon::parse($completedAt)->addYears(2)
                : Carbon::parse($startedAt)->addMonths(6),
        ];

        return match($testType) {
            'mbti' => array_merge($data, $this->generateMbtiData($isCompleted, $answeredQuestions)),
            'big_five' => array_merge($data, $this->generateBigFiveData($isCompleted, $answeredQuestions)),
            'love_language' => array_merge($data, $this->generateLoveLanguageData($isCompleted, $answeredQuestions)),
            'attachment_style' => array_merge($data, $this->generateAttachmentStyleData($isCompleted, $answeredQuestions)),
            'enneagram' => array_merge($data, $this->generateEnneagramData($isCompleted, $answeredQuestions)),
            default => array_merge($data, $this->generateGenericData($isCompleted)),
        };
    }

    /**
     * Get question count for each test type
     */
    private function getQuestionCount(string $testType): int
    {
        return match($testType) {
            'mbti' => 60,
            'big_five' => 50,
            'love_language' => 30,
            'attachment_style' => 36,
            'enneagram' => 144,
            default => 20,
        };
    }

    /**
     * Generate MBTI test data
     */
    private function generateMbtiData(bool $isCompleted, int $answeredQuestions): array
    {
        $questions = $this->generateMbtiQuestions();
        $answers = $this->generateMbtiAnswers($answeredQuestions);
        
        if (!$isCompleted) {
            return [
                'questions' => $questions,
                'answers' => $answers,
                'results' => null,
                'primary_result' => null,
                'secondary_results' => null,
                'trait_scores' => null,
                'insights' => null,
                'compatibility_notes' => null,
                'accuracy_score' => null,
            ];
        }

        $type = $this->faker->randomElement(array_keys(self::$mbtiTypes));
        $scores = $this->generateMbtiScores($type);
        
        return [
            'questions' => $questions,
            'answers' => $answers,
            'results' => $scores,
            'primary_result' => $type,
            'secondary_results' => [
                'dominant_function' => $this->getMbtiDominantFunction($type),
                'auxiliary_function' => $this->getMbtiAuxiliaryFunction($type),
            ],
            'trait_scores' => $scores,
            'insights' => $this->generateMbtiInsights($type),
            'compatibility_notes' => $this->generateMbtiCompatibility($type),
            'accuracy_score' => $this->faker->randomFloat(3, 0.75, 0.95),
        ];
    }

    /**
     * Generate Big Five test data
     */
    private function generateBigFiveData(bool $isCompleted, int $answeredQuestions): array
    {
        $questions = $this->generateBigFiveQuestions();
        $answers = $this->generateBigFiveAnswers($answeredQuestions);
        
        if (!$isCompleted) {
            return [
                'questions' => $questions,
                'answers' => $answers,
                'results' => null,
                'primary_result' => null,
                'secondary_results' => null,
                'trait_scores' => null,
                'insights' => null,
                'compatibility_notes' => null,
                'accuracy_score' => null,
            ];
        }

        $scores = $this->generateBigFiveScores();
        $primaryTrait = array_key_first(array_slice(arsort($scores), 0, 1, true));
        
        return [
            'questions' => $questions,
            'answers' => $answers,
            'results' => $scores,
            'primary_result' => ucfirst($primaryTrait),
            'secondary_results' => array_slice($scores, 1, 2, true),
            'trait_scores' => $scores,
            'insights' => $this->generateBigFiveInsights($scores),
            'compatibility_notes' => $this->generateBigFiveCompatibility($scores),
            'accuracy_score' => $this->faker->randomFloat(3, 0.80, 0.92),
        ];
    }

    /**
     * Generate Love Language test data
     */
    private function generateLoveLanguageData(bool $isCompleted, int $answeredQuestions): array
    {
        $questions = $this->generateLoveLanguageQuestions();
        $answers = $this->generateLoveLanguageAnswers($answeredQuestions);
        
        if (!$isCompleted) {
            return [
                'questions' => $questions,
                'answers' => $answers,
                'results' => null,
                'primary_result' => null,
                'secondary_results' => null,
                'trait_scores' => null,
                'insights' => null,
                'compatibility_notes' => null,
                'accuracy_score' => null,
            ];
        }

        $scores = $this->generateLoveLanguageScores();
        arsort($scores);
        $primary = array_key_first($scores);
        
        return [
            'questions' => $questions,
            'answers' => $answers,
            'results' => $scores,
            'primary_result' => str_replace('_', ' ', ucwords($primary, '_')),
            'secondary_results' => array_slice($scores, 1, 2, true),
            'trait_scores' => $scores,
            'insights' => $this->generateLoveLanguageInsights($primary),
            'compatibility_notes' => $this->generateLoveLanguageCompatibility($primary),
            'accuracy_score' => $this->faker->randomFloat(3, 0.85, 0.95),
        ];
    }

    /**
     * Generate Attachment Style test data
     */
    private function generateAttachmentStyleData(bool $isCompleted, int $answeredQuestions): array
    {
        $questions = $this->generateAttachmentStyleQuestions();
        $answers = $this->generateAttachmentStyleAnswers($answeredQuestions);
        
        if (!$isCompleted) {
            return [
                'questions' => $questions,
                'answers' => $answers,
                'results' => null,
                'primary_result' => null,
                'secondary_results' => null,
                'trait_scores' => null,
                'insights' => null,
                'compatibility_notes' => null,
                'accuracy_score' => null,
            ];
        }

        $scores = $this->generateAttachmentStyleScores();
        arsort($scores);
        $primary = array_key_first($scores);
        
        return [
            'questions' => $questions,
            'answers' => $answers,
            'results' => $scores,
            'primary_result' => str_replace('_', ' ', ucwords($primary, '_')),
            'secondary_results' => array_slice($scores, 1, 1, true),
            'trait_scores' => $scores,
            'insights' => $this->generateAttachmentStyleInsights($primary),
            'compatibility_notes' => $this->generateAttachmentStyleCompatibility($primary),
            'accuracy_score' => $this->faker->randomFloat(3, 0.78, 0.90),
        ];
    }

    /**
     * Generate Enneagram test data
     */
    private function generateEnneagramData(bool $isCompleted, int $answeredQuestions): array
    {
        $questions = $this->generateEnneagramQuestions();
        $answers = $this->generateEnneagramAnswers($answeredQuestions);
        
        if (!$isCompleted) {
            return [
                'questions' => $questions,
                'answers' => $answers,
                'results' => null,
                'primary_result' => null,
                'secondary_results' => null,
                'trait_scores' => null,
                'insights' => null,
                'compatibility_notes' => null,
                'accuracy_score' => null,
            ];
        }

        $scores = $this->generateEnneagramScores();
        arsort($scores);
        $primaryType = array_key_first($scores);
        
        return [
            'questions' => $questions,
            'answers' => $answers,
            'results' => $scores,
            'primary_result' => "Type {$primaryType}",
            'secondary_results' => [
                'wing' => $this->getEnneagramWing($primaryType),
                'instinctual_variant' => $this->faker->randomElement(['sp', 'so', 'sx']),
            ],
            'trait_scores' => $scores,
            'insights' => $this->generateEnneagramInsights($primaryType),
            'compatibility_notes' => $this->generateEnneagramCompatibility($primaryType),
            'accuracy_score' => $this->faker->randomFloat(3, 0.72, 0.88),
        ];
    }

    /**
     * Generate generic test data for custom tests
     */
    private function generateGenericData(bool $isCompleted): array
    {
        return [
            'questions' => ['Generic question set'],
            'answers' => $isCompleted ? ['Generic answers'] : [],
            'results' => $isCompleted ? ['custom_result' => 'Custom Result'] : null,
            'primary_result' => $isCompleted ? 'Custom Test Result' : null,
            'secondary_results' => null,
            'trait_scores' => null,
            'insights' => null,
            'compatibility_notes' => null,
            'accuracy_score' => $isCompleted ? $this->faker->randomFloat(3, 0.60, 0.85) : null,
        ];
    }

    // Helper methods for generating specific test content...

    private function generateMbtiQuestions(): array
    {
        return [
            ['id' => 1, 'text' => 'You prefer to work:', 'options' => ['Alone', 'In a team']],
            ['id' => 2, 'text' => 'When making decisions, you rely more on:', 'options' => ['Logic', 'Feelings']],
            // ... more questions would be here in production
        ];
    }

    private function generateMbtiAnswers(int $count): array
    {
        $answers = [];
        for ($i = 1; $i <= $count; $i++) {
            $answers[$i] = $this->faker->randomElement(['A', 'B']);
        }
        return $answers;
    }

    private function generateMbtiScores(string $type): array
    {
        // Generate scores that match the MBTI type
        return [
            'E' => str_contains($type, 'E') ? $this->faker->numberBetween(60, 85) : $this->faker->numberBetween(15, 40),
            'I' => str_contains($type, 'I') ? $this->faker->numberBetween(60, 85) : $this->faker->numberBetween(15, 40),
            'S' => str_contains($type, 'S') ? $this->faker->numberBetween(60, 85) : $this->faker->numberBetween(15, 40),
            'N' => str_contains($type, 'N') ? $this->faker->numberBetween(60, 85) : $this->faker->numberBetween(15, 40),
            'T' => str_contains($type, 'T') ? $this->faker->numberBetween(60, 85) : $this->faker->numberBetween(15, 40),
            'F' => str_contains($type, 'F') ? $this->faker->numberBetween(60, 85) : $this->faker->numberBetween(15, 40),
            'J' => str_contains($type, 'J') ? $this->faker->numberBetween(60, 85) : $this->faker->numberBetween(15, 40),
            'P' => str_contains($type, 'P') ? $this->faker->numberBetween(60, 85) : $this->faker->numberBetween(15, 40),
        ];
    }

    private function getMbtiDominantFunction(string $type): string
    {
        $functions = [
            'INTJ' => 'Ni', 'INTP' => 'Ti', 'ENTJ' => 'Te', 'ENTP' => 'Ne',
            'INFJ' => 'Ni', 'INFP' => 'Fi', 'ENFJ' => 'Fe', 'ENFP' => 'Ne',
            'ISTJ' => 'Si', 'ISFJ' => 'Si', 'ESTJ' => 'Te', 'ESFJ' => 'Fe',
            'ISTP' => 'Ti', 'ISFP' => 'Fi', 'ESTP' => 'Se', 'ESFP' => 'Se',
        ];
        return $functions[$type] ?? 'Unknown';
    }

    private function getMbtiAuxiliaryFunction(string $type): string
    {
        $functions = [
            'INTJ' => 'Te', 'INTP' => 'Ne', 'ENTJ' => 'Ni', 'ENTP' => 'Ti',
            'INFJ' => 'Fe', 'INFP' => 'Ne', 'ENFJ' => 'Ni', 'ENFP' => 'Fi',
            'ISTJ' => 'Te', 'ISFJ' => 'Fe', 'ESTJ' => 'Si', 'ESFJ' => 'Si',
            'ISTP' => 'Se', 'ISFP' => 'Se', 'ESTP' => 'Ti', 'ESFP' => 'Fi',
        ];
        return $functions[$type] ?? 'Unknown';
    }

    private function generateMbtiInsights(string $type): array
    {
        return [
            'strengths' => self::$mbtiTypes[$type] ?? ['Unique'],
            'growth_areas' => $this->faker->randomElements([
                'Communication', 'Patience', 'Flexibility', 'Empathy', 'Planning'
            ], 2),
            'ideal_partner_traits' => $this->getIdealPartnerTraits($type),
            'relationship_style' => $this->getRelationshipStyle($type),
        ];
    }

    private function generateMbtiCompatibility(string $type): array
    {
        $highCompatibility = [
            'INTJ' => ['ENFP', 'ENTP'], 'INTP' => ['ENFJ', 'ENTJ'],
            'ENTJ' => ['INFP', 'INTP'], 'ENTP' => ['INFJ', 'INTJ'],
            'INFJ' => ['ENFP', 'ENTP'], 'INFP' => ['ENFJ', 'ENTJ'],
            'ENFJ' => ['INFP', 'INTP'], 'ENFP' => ['INFJ', 'INTJ'],
        ];

        return [
            'most_compatible' => $highCompatibility[$type] ?? ['Various types'],
            'compatibility_factors' => [
                'Complementary strengths',
                'Shared values',
                'Different perspectives',
            ],
        ];
    }

    // Similar helper methods would exist for other test types...
    // (Abbreviated for space - in production these would be fully implemented)

    private function generateBigFiveQuestions(): array { return []; }
    private function generateBigFiveAnswers(int $count): array { return []; }
    private function generateBigFiveScores(): array 
    {
        return [
            'openness' => $this->faker->numberBetween(20, 95),
            'conscientiousness' => $this->faker->numberBetween(20, 95),
            'extraversion' => $this->faker->numberBetween(20, 95),
            'agreeableness' => $this->faker->numberBetween(20, 95),
            'neuroticism' => $this->faker->numberBetween(20, 95),
        ];
    }
    private function generateBigFiveInsights(array $scores): array { return []; }
    private function generateBigFiveCompatibility(array $scores): array { return []; }

    private function generateLoveLanguageQuestions(): array { return []; }
    private function generateLoveLanguageAnswers(int $count): array { return []; }
    private function generateLoveLanguageScores(): array 
    {
        return [
            'words_of_affirmation' => $this->faker->numberBetween(10, 30),
            'acts_of_service' => $this->faker->numberBetween(10, 30),
            'receiving_gifts' => $this->faker->numberBetween(10, 30),
            'quality_time' => $this->faker->numberBetween(10, 30),
            'physical_touch' => $this->faker->numberBetween(10, 30),
        ];
    }
    private function generateLoveLanguageInsights(string $primary): array { return []; }
    private function generateLoveLanguageCompatibility(string $primary): array { return []; }

    private function generateAttachmentStyleQuestions(): array { return []; }
    private function generateAttachmentStyleAnswers(int $count): array { return []; }
    private function generateAttachmentStyleScores(): array 
    {
        return [
            'secure' => $this->faker->numberBetween(20, 80),
            'anxious' => $this->faker->numberBetween(20, 80),
            'avoidant' => $this->faker->numberBetween(20, 80),
            'fearful_avoidant' => $this->faker->numberBetween(20, 80),
        ];
    }
    private function generateAttachmentStyleInsights(string $primary): array { return []; }
    private function generateAttachmentStyleCompatibility(string $primary): array { return []; }

    private function generateEnneagramQuestions(): array { return []; }
    private function generateEnneagramAnswers(int $count): array { return []; }
    private function generateEnneagramScores(): array 
    {
        $scores = [];
        for ($i = 1; $i <= 9; $i++) {
            $scores[$i] = $this->faker->numberBetween(10, 90);
        }
        return $scores;
    }
    private function getEnneagramWing(int $type): string 
    {
        $wings = [
            1 => ['9w1', '1w2'], 2 => ['1w2', '2w3'], 3 => ['2w3', '3w4'],
            4 => ['3w4', '4w5'], 5 => ['4w5', '5w6'], 6 => ['5w6', '6w7'],
            7 => ['6w7', '7w8'], 8 => ['7w8', '8w9'], 9 => ['8w9', '9w1'],
        ];
        return $this->faker->randomElement($wings[$type] ?? ['Unknown']);
    }
    private function generateEnneagramInsights(int $type): array { return []; }
    private function generateEnneagramCompatibility(int $type): array { return []; }

    private function getIdealPartnerTraits(string $type): array { return ['Complementary']; }
    private function getRelationshipStyle(string $type): string { return 'Balanced'; }

    /**
     * Factory State: Completed Test
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $testType = $attributes['test_type'] ?? 'mbti';
            $totalQuestions = $this->getQuestionCount($testType);
            $testData = $this->generateTestData($testType, true);
            
            return array_merge($testData, [
                'completion_percentage' => 100,
                'status' => 'completed',
                'answered_questions' => $totalQuestions,
                'use_for_matching' => true,
                'is_public' => true,
            ]);
        });
    }

    /**
     * Factory State: In Progress Test
     */
    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            $testType = $attributes['test_type'] ?? 'mbti';
            $totalQuestions = $this->getQuestionCount($testType);
            $answeredQuestions = $this->faker->numberBetween(1, $totalQuestions - 1);
            
            return [
                'completion_percentage' => (int) round(($answeredQuestions / $totalQuestions) * 100),
                'status' => 'in_progress',
                'answered_questions' => $answeredQuestions,
                'completed_at' => null,
                'results' => null,
                'primary_result' => null,
                'secondary_results' => null,
                'trait_scores' => null,
                'insights' => null,
                'compatibility_notes' => null,
                'accuracy_score' => null,
            ];
        });
    }

    /**
     * Factory State: MBTI Type
     */
    public function mbti(): static
    {
        return $this->state(function (array $attributes) {
            return array_merge(
                $this->generateTestData('mbti', true),
                ['test_type' => 'mbti']
            );
        });
    }

    /**
     * Factory State: Big Five
     */
    public function bigFive(): static
    {
        return $this->state(function (array $attributes) {
            return array_merge(
                $this->generateTestData('big_five', true),
                ['test_type' => 'big_five']
            );
        });
    }

    /**
     * Factory State: Love Language
     */
    public function loveLanguage(): static
    {
        return $this->state(function (array $attributes) {
            return array_merge(
                $this->generateTestData('love_language', true),
                ['test_type' => 'love_language']
            );
        });
    }

    /**
     * Factory State: High Accuracy
     */
    public function highAccuracy(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'accuracy_score' => $this->faker->randomFloat(3, 0.90, 0.98),
                'status' => 'completed',
                'time_spent_seconds' => $this->faker->numberBetween(1200, 2400), // Longer time = higher accuracy
            ];
        });
    }
}