<?php

declare(strict_types=1);

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PersonalityTest Model - Tests de personalidad de usuario
 * 
 * Almacena los resultados de tests de personalidad (MBTI, Big Five, etc.)
 * para mejorar el matching y proporcionar insights sobre compatibilidad.
 * 
 * @property int $id
 * @property int $user_id
 * @property string $test_type Valores: mbti, big_five, love_language, attachment_style, enneagram, custom
 * @property string $test_version Versión del test
 * @property array $questions Array de preguntas y respuestas
 * @property array $answers Array de respuestas del usuario
 * @property array $results Resultados procesados del test
 * @property string|null $primary_result Resultado principal (ej: INTJ, Extraversion)
 * @property array|null $secondary_results Resultados secundarios o dimensiones
 * @property array|null $trait_scores Scores de traits individuales (0-100)
 * @property int $completion_percentage 0-100
 * @property string $status Valores: in_progress, completed, expired, invalid
 * @property int $total_questions
 * @property int $answered_questions
 * @property int $time_spent_seconds Tiempo total en completar el test
 * @property array|null $insights Insights generados por IA
 * @property array|null $compatibility_notes Notas sobre compatibilidad
 * @property float|null $accuracy_score Score de confiabilidad (0-1)
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at Tests pueden expirar
 * @property bool $is_public Mostrar en perfil público
 * @property bool $use_for_matching Usar para algoritmo de matching
 * @property int $retake_count Número de veces que se ha repetido
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property-read User $user
 * @property-read bool $is_completed
 * @property-read bool $is_expired
 * @property-read bool $is_valid
 * @property-read string $result_summary
 * 
 * @method static \Illuminate\Database\Eloquent\Builder completed()
 * @method static \Illuminate\Database\Eloquent\Builder inProgress()
 * @method static \Illuminate\Database\Eloquent\Builder byType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder valid()
 * @method static \Illuminate\Database\Eloquent\Builder forMatching()
 */
class PersonalityTest extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'personality_tests';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'test_type',
        'test_version',
        'questions',
        'answers',
        'results',
        'primary_result',
        'secondary_results',
        'trait_scores',
        'completion_percentage',
        'status',
        'total_questions',
        'answered_questions',
        'time_spent_seconds',
        'insights',
        'compatibility_notes',
        'accuracy_score',
        'started_at',
        'completed_at',
        'expires_at',
        'is_public',
        'use_for_matching',
        'retake_count',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'questions' => 'array',
        'answers' => 'array',
        'results' => 'array',
        'secondary_results' => 'array',
        'trait_scores' => 'array',
        'completion_percentage' => 'integer',
        'total_questions' => 'integer',
        'answered_questions' => 'integer',
        'time_spent_seconds' => 'integer',
        'insights' => 'array',
        'compatibility_notes' => 'array',
        'accuracy_score' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_public' => 'boolean',
        'use_for_matching' => 'boolean',
        'retake_count' => 'integer',
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PersonalityTest $test) {
            $test->status = $test->status ?? 'in_progress';
            $test->completion_percentage = 0;
            $test->answered_questions = 0;
            $test->time_spent_seconds = 0;
            $test->retake_count = 0;
            $test->started_at = now();
            $test->is_public = $test->is_public ?? true;
            $test->use_for_matching = $test->use_for_matching ?? true;
            
            // Set expiration (6 months for incomplete tests)
            if (!$test->expires_at) {
                $test->expires_at = now()->addMonths(6);
            }
        });

        static::saved(function (PersonalityTest $test) {
            // Update profile when test is completed
            if ($test->status === 'completed' && $test->use_for_matching) {
                $test->user->profile?->update([
                    'personality_type' => $test->primary_result,
                ]);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user that owns the personality test.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to filter completed tests.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to filter in-progress tests.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope to filter tests by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('test_type', $type);
    }

    /**
     * Scope to filter valid (not expired) tests.
     */
    public function scopeValid($query)
    {
        return $query->where('status', 'completed')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to filter tests used for matching.
     */
    public function scopeForMatching($query)
    {
        return $query->where('use_for_matching', true)
            ->where('status', 'completed');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Check if test is completed.
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if test is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if test results are valid.
     */
    public function getIsValidAttribute(): bool
    {
        return $this->status === 'completed' && !$this->is_expired;
    }

    /**
     * Get a human-readable summary of results.
     */
    public function getResultSummaryAttribute(): string
    {
        if (!$this->is_completed) {
            return 'Test not completed';
        }

        $summary = match($this->test_type) {
            'mbti' => $this->getMbtiSummary(),
            'big_five' => $this->getBigFiveSummary(),
            'love_language' => $this->getLoveLanguageSummary(),
            'attachment_style' => $this->getAttachmentStyleSummary(),
            'enneagram' => $this->getEnneagramSummary(),
            default => $this->primary_result ?? 'Results available',
        };

        return $summary;
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_questions === 0) {
            return 0;
        }

        return (int) round(($this->answered_questions / $this->total_questions) * 100);
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Record an answer to a question.
     */
    public function recordAnswer(int $questionId, $answer): void
    {
        $answers = $this->answers ?? [];
        $answers[$questionId] = $answer;

        $this->update([
            'answers' => $answers,
            'answered_questions' => count($answers),
            'completion_percentage' => (int) round((count($answers) / $this->total_questions) * 100),
        ]);

        // Auto-complete if all questions answered
        if (count($answers) === $this->total_questions) {
            $this->complete();
        }
    }

    /**
     * Complete the test and calculate results.
     */
    public function complete(): void
    {
        $results = $this->calculateResults();

        $this->update([
            'status' => 'completed',
            'completion_percentage' => 100,
            'completed_at' => now(),
            'results' => $results['results'],
            'primary_result' => $results['primary_result'],
            'secondary_results' => $results['secondary_results'] ?? null,
            'trait_scores' => $results['trait_scores'] ?? null,
            'accuracy_score' => $results['accuracy_score'] ?? null,
            'insights' => $results['insights'] ?? null,
            'expires_at' => now()->addYears(2), // Valid for 2 years
        ]);
    }

    /**
     * Calculate test results based on answers.
     */
    protected function calculateResults(): array
    {
        return match($this->test_type) {
            'mbti' => $this->calculateMbtiResults(),
            'big_five' => $this->calculateBigFiveResults(),
            'love_language' => $this->calculateLoveLanguageResults(),
            'attachment_style' => $this->calculateAttachmentStyleResults(),
            'enneagram' => $this->calculateEnneagramResults(),
            default => [
                'results' => $this->answers,
                'primary_result' => 'Custom Test Result',
            ],
        };
    }

    /**
     * Calculate MBTI results.
     */
    protected function calculateMbtiResults(): array
    {
        // Simplified MBTI calculation
        // In production, this would use proper scoring algorithm
        
        $scores = [
            'E' => 0, 'I' => 0, // Extraversion vs Introversion
            'S' => 0, 'N' => 0, // Sensing vs Intuition
            'T' => 0, 'F' => 0, // Thinking vs Feeling
            'J' => 0, 'P' => 0, // Judging vs Perceiving
        ];

        // Calculate scores from answers
        foreach ($this->answers as $questionId => $answer) {
            // Each question maps to a dimension
            // This is a simplified example
            if (is_array($answer)) {
                foreach ($answer as $dimension => $value) {
                    if (isset($scores[$dimension])) {
                        $scores[$dimension] += $value;
                    }
                }
            }
        }

        // Determine type
        $type = '';
        $type .= $scores['E'] > $scores['I'] ? 'E' : 'I';
        $type .= $scores['S'] > $scores['N'] ? 'S' : 'N';
        $type .= $scores['T'] > $scores['F'] ? 'T' : 'F';
        $type .= $scores['J'] > $scores['P'] ? 'J' : 'P';

        return [
            'results' => $scores,
            'primary_result' => $type,
            'trait_scores' => $scores,
            'accuracy_score' => 0.85,
            'insights' => $this->generateMbtiInsights($type),
        ];
    }

    /**
     * Calculate Big Five results.
     */
    protected function calculateBigFiveResults(): array
    {
        $traits = [
            'openness' => 0,
            'conscientiousness' => 0,
            'extraversion' => 0,
            'agreeableness' => 0,
            'neuroticism' => 0,
        ];

        // Calculate trait scores (0-100)
        // This is a simplified example
        foreach ($this->answers as $answer) {
            // Scoring logic here
        }

        $primaryTrait = array_search(max($traits), $traits);

        return [
            'results' => $traits,
            'primary_result' => ucfirst($primaryTrait),
            'trait_scores' => $traits,
            'accuracy_score' => 0.88,
        ];
    }

    /**
     * Calculate Love Language results.
     */
    protected function calculateLoveLanguageResults(): array
    {
        $languages = [
            'words_of_affirmation' => 0,
            'acts_of_service' => 0,
            'receiving_gifts' => 0,
            'quality_time' => 0,
            'physical_touch' => 0,
        ];

        // Calculate scores
        foreach ($this->answers as $answer) {
            // Scoring logic
        }

        arsort($languages);
        $primary = array_key_first($languages);

        return [
            'results' => $languages,
            'primary_result' => str_replace('_', ' ', ucwords($primary, '_')),
            'secondary_results' => array_slice($languages, 1, 2, true),
            'trait_scores' => $languages,
            'accuracy_score' => 0.90,
        ];
    }

    /**
     * Calculate Attachment Style results.
     */
    protected function calculateAttachmentStyleResults(): array
    {
        $styles = [
            'secure' => 0,
            'anxious' => 0,
            'avoidant' => 0,
            'fearful_avoidant' => 0,
        ];

        // Scoring logic here

        arsort($styles);
        $primary = array_key_first($styles);

        return [
            'results' => $styles,
            'primary_result' => str_replace('_', ' ', ucwords($primary, '_')),
            'trait_scores' => $styles,
            'accuracy_score' => 0.82,
        ];
    }

    /**
     * Calculate Enneagram results.
     */
    protected function calculateEnneagramResults(): array
    {
        $types = array_fill(1, 9, 0); // Types 1-9

        // Scoring logic here

        arsort($types);
        $primaryType = array_key_first($types);

        return [
            'results' => $types,
            'primary_result' => "Type {$primaryType}",
            'trait_scores' => $types,
            'accuracy_score' => 0.84,
        ];
    }

    /**
     * Generate MBTI insights.
     */
    protected function generateMbtiInsights(string $type): array
    {
        // In production, this would use AI or a comprehensive database
        $insights = [
            'INTJ' => ['Strategic', 'Independent', 'Analytical', 'Decisive'],
            'INFJ' => ['Insightful', 'Principled', 'Compassionate', 'Creative'],
            'ENTJ' => ['Bold', 'Imaginative', 'Strong-willed', 'Strategic'],
            'ENFJ' => ['Charismatic', 'Reliable', 'Altruistic', 'Natural leader'],
            // ... other types
        ];

        return $insights[$type] ?? ['Unique', 'Complex', 'Interesting'];
    }

    /**
     * Calculate compatibility with another test.
     */
    public function compatibilityWith(PersonalityTest $otherTest): array
    {
        if ($this->test_type !== $otherTest->test_type) {
            return [
                'compatible' => false,
                'reason' => 'Different test types',
            ];
        }

        $score = 0;

        switch ($this->test_type) {
            case 'mbti':
                $score = $this->mbtiCompatibility($otherTest);
                break;
            case 'big_five':
                $score = $this->bigFiveCompatibility($otherTest);
                break;
            default:
                $score = 50; // Neutral
        }

        return [
            'compatible' => $score >= 60,
            'score' => $score,
            'notes' => $this->generateCompatibilityNotes($otherTest, $score),
        ];
    }

    /**
     * Calculate MBTI compatibility score.
     */
    protected function mbtiCompatibility(PersonalityTest $otherTest): int
    {
        $type1 = $this->primary_result;
        $type2 = $otherTest->primary_result;

        // Simplified compatibility matrix
        $highCompatibility = [
            'INTJ' => ['ENFP', 'ENTP', 'INFJ'],
            'INFJ' => ['ENFP', 'ENTP', 'INTJ'],
            'ENTJ' => ['INFP', 'INTP'],
            'ENFJ' => ['INFP', 'ISFP'],
            // ... more combinations
        ];

        if (isset($highCompatibility[$type1]) && in_array($type2, $highCompatibility[$type1])) {
            return rand(80, 95);
        }

        return rand(40, 70);
    }

    /**
     * Calculate Big Five compatibility score.
     */
    protected function bigFiveCompatibility(PersonalityTest $otherTest): int
    {
        $scores1 = $this->trait_scores;
        $scores2 = $otherTest->trait_scores;

        $similarity = 0;
        foreach ($scores1 as $trait => $score) {
            $diff = abs($score - ($scores2[$trait] ?? 0));
            $similarity += (100 - $diff);
        }

        return (int) ($similarity / count($scores1));
    }

    /**
     * Generate compatibility notes.
     */
    protected function generateCompatibilityNotes(PersonalityTest $otherTest, int $score): array
    {
        return [
            'overall' => $score >= 75 ? 'Excellent match' : ($score >= 60 ? 'Good compatibility' : 'Moderate compatibility'),
            'strengths' => ['Complementary traits', 'Shared values'],
            'challenges' => $score < 60 ? ['Different communication styles'] : [],
        ];
    }

    /**
     * Get MBTI summary text.
     */
    protected function getMbtiSummary(): string
    {
        return "MBTI Type: {$this->primary_result}";
    }

    /**
     * Get Big Five summary text.
     */
    protected function getBigFiveSummary(): string
    {
        return "Primary Trait: {$this->primary_result}";
    }

    /**
     * Get Love Language summary text.
     */
    protected function getLoveLanguageSummary(): string
    {
        return "Love Language: {$this->primary_result}";
    }

    /**
     * Get Attachment Style summary text.
     */
    protected function getAttachmentStyleSummary(): string
    {
        return "Attachment Style: {$this->primary_result}";
    }

    /**
     * Get Enneagram summary text.
     */
    protected function getEnneagramSummary(): string
    {
        return "Enneagram {$this->primary_result}";
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get validation rules for test creation.
     */
    public static function validationRules(): array
    {
        return [
            'test_type' => ['required', 'in:mbti,big_five,love_language,attachment_style,enneagram,custom'],
            'test_version' => ['required', 'string', 'max:20'],
            'total_questions' => ['required', 'integer', 'min:1'],
        ];
    }
}