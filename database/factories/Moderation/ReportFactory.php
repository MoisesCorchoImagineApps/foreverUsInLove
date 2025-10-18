<?php

namespace Database\Factories\Domains\Moderation;

use App\Domains\Moderation\Models\Report;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Moderation\Models\Report>
 */
class ReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reportType = $this->faker->randomElement([
            'HARASSMENT', 'SPAM', 'FAKE_PROFILE', 'INAPPROPRIATE_CONTENT',
            'HATE_SPEECH', 'VIOLENCE', 'NUDITY', 'SCAM', 'UNDERAGE',
            'IDENTITY_THEFT', 'COPYRIGHT', 'OTHER'
        ]);

        $status = $this->faker->randomElement([
            'PENDING', 'IN_REVIEW', 'UNDER_INVESTIGATION', 'RESOLVED',
            'DISMISSED', 'ESCALATED', 'CLOSED', 'REOPENED'
        ]);

        $priority = $this->faker->randomElement(['LOW', 'MEDIUM', 'HIGH', 'URGENT', 'CRITICAL']);
        
        // ML Analysis simulation
        $mlConfidenceScore = $this->faker->randomFloat(2, 0.1, 0.99);
        $riskLevel = $mlConfidenceScore > 0.8 ? 'HIGH' : ($mlConfidenceScore > 0.5 ? 'MEDIUM' : 'LOW');
        
        $createdAt = $this->faker->dateTimeBetween('-6 months', 'now');
        $reviewedAt = $status !== 'PENDING' ? 
            $this->faker->dateTimeBetween($createdAt, 'now') : null;
        
        // Evidence collection
        $evidenceTypes = $this->faker->randomElements([
            'screenshot', 'message_history', 'profile_data', 'conversation_log',
            'image_content', 'video_content', 'audio_content', 'metadata'
        ], $this->faker->numberBetween(1, 4));

        // Auto-escalation logic
        $autoEscalated = $mlConfidenceScore > 0.85 || 
                        in_array($reportType, ['HARASSMENT', 'HATE_SPEECH', 'VIOLENCE', 'UNDERAGE']) ||
                        $priority === 'CRITICAL';

        return [
            'reporter_id' => User::factory(),
            'reported_user_id' => User::factory(),
            'report_type' => $reportType,
            'status' => $status,
            'priority' => $priority,
            'description' => $this->generateReportDescription($reportType),
            'evidence' => [
                'types' => $evidenceTypes,
                'screenshots_count' => $this->faker->numberBetween(0, 5),
                'messages_count' => $this->faker->numberBetween(0, 20),
                'additional_context' => $this->faker->sentence()
            ],
            'ml_analysis' => [
                'confidence_score' => $mlConfidenceScore,
                'risk_level' => $riskLevel,
                'predicted_violation' => $reportType,
                'analysis_timestamp' => $createdAt->format('Y-m-d H:i:s'),
                'model_version' => 'v2.1.0',
                'features_analyzed' => $this->faker->randomElements([
                    'text_content', 'image_analysis', 'behavior_pattern',
                    'account_history', 'interaction_frequency', 'report_history'
                ], $this->faker->numberBetween(2, 4))
            ],
            'moderator_notes' => $status !== 'PENDING' ? 
                $this->faker->sentence() : null,
            'resolution' => $status === 'RESOLVED' ? [
                'action_taken' => $this->faker->randomElement([
                    'WARNING_ISSUED', 'CONTENT_REMOVED', 'ACCOUNT_SUSPENDED',
                    'ACCOUNT_BANNED', 'NO_ACTION', 'EDUCATIONAL_CONTENT_SENT'
                ]),
                'explanation' => $this->faker->sentence(),
                'follow_up_required' => $this->faker->boolean(30)
            ] : null,
            'auto_escalated' => $autoEscalated,
            'escalation_reason' => $autoEscalated ? 
                $this->faker->randomElement([
                    'HIGH_ML_CONFIDENCE', 'SEVERE_VIOLATION_TYPE', 'CRITICAL_PRIORITY',
                    'REPEAT_OFFENDER', 'MULTIPLE_REPORTS', 'SAFETY_CONCERN'
                ]) : null,
            'reported_at' => $createdAt,
            'reviewed_at' => $reviewedAt,
            'resolved_at' => $status === 'RESOLVED' ? 
                $this->faker->dateTimeBetween($reviewedAt ?? $createdAt, 'now') : null,
            'created_at' => $createdAt,
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    /**
     * Generate realistic report description based on type
     */
    private function generateReportDescription(string $reportType): string
    {
        $descriptions = [
            'HARASSMENT' => [
                'This user has been sending me inappropriate messages repeatedly.',
                'Continuous unwanted contact despite being told to stop.',
                'Threatening and intimidating behavior in conversations.',
                'Making me feel unsafe with persistent messaging.'
            ],
            'SPAM' => [
                'Sending the same promotional message to multiple users.',
                'Profile contains suspicious links and promotional content.',
                'Repeatedly sharing commercial content in inappropriate contexts.',
                'Automated-looking messages promoting external services.'
            ],
            'FAKE_PROFILE' => [
                'Using stolen photos from social media accounts.',
                'Profile information seems fabricated and inconsistent.',
                'Suspected catfishing with fake identity.',
                'Photos appear to be from modeling/stock photo websites.'
            ],
            'INAPPROPRIATE_CONTENT' => [
                'Sharing explicit content without consent.',
                'Profile pictures contain inappropriate material.',
                'Sending unsolicited inappropriate images.',
                'Content violates community standards.'
            ],
            'HATE_SPEECH' => [
                'Using discriminatory language based on race/religion.',
                'Making hateful comments about sexual orientation.',
                'Promoting intolerance and discrimination.',
                'Expressing extremist views and hate toward groups.'
            ],
            'VIOLENCE' => [
                'Making threats of physical harm.',
                'Describing violent fantasies in conversations.',
                'Promoting or glorifying violence.',
                'Threatening behavior that makes me fear for safety.'
            ],
            'NUDITY' => [
                'Profile contains explicit nudity.',
                'Sharing nude images without permission.',
                'Inappropriate sexual content in public areas.',
                'Adult content visible to all users.'
            ],
            'SCAM' => [
                'Asking for money or financial information.',
                'Suspicious requests for personal details.',
                'Potential romance scam behavior.',
                'Requesting gift cards or financial assistance.'
            ],
            'UNDERAGE' => [
                'Profile suggests user may be under 18.',
                'Inconsistent age information provided.',
                'Concerns about minor using adult platform.',
                'Behavior and language suggests underage user.'
            ],
            'IDENTITY_THEFT' => [
                'Using someone else\'s photos and information.',
                'Impersonating a real person I know.',
                'Stolen identity from public figure.',
                'False representation of identity.'
            ],
            'COPYRIGHT' => [
                'Using copyrighted images without permission.',
                'Profile contains trademarked content.',
                'Unauthorized use of branded materials.',
                'Violation of intellectual property rights.'
            ],
            'OTHER' => [
                'Behavior that doesn\'t fit other categories but violates terms.',
                'Multiple minor violations that create negative experience.',
                'Concerning pattern of behavior.',
                'General violation of community guidelines.'
            ]
        ];

        return $this->faker->randomElement($descriptions[$reportType]);
    }

    /**
     * Indicate that the report is high priority with ML escalation
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'CRITICAL',
            'ml_analysis' => array_merge($attributes['ml_analysis'] ?? [], [
                'confidence_score' => $this->faker->randomFloat(2, 0.85, 0.99),
                'risk_level' => 'HIGH'
            ]),
            'auto_escalated' => true,
            'escalation_reason' => 'HIGH_ML_CONFIDENCE'
        ]);
    }

    /**
     * Indicate that the report has been resolved
     */
    public function resolved(): static
    {
        $resolvedAt = $this->faker->dateTimeBetween('-1 month', 'now');
        
        return $this->state(fn (array $attributes) => [
            'status' => 'RESOLVED',
            'reviewed_at' => $this->faker->dateTimeBetween($attributes['created_at'], $resolvedAt),
            'resolved_at' => $resolvedAt,
            'resolution' => [
                'action_taken' => $this->faker->randomElement([
                    'WARNING_ISSUED', 'CONTENT_REMOVED', 'ACCOUNT_SUSPENDED',
                    'ACCOUNT_BANNED', 'NO_ACTION'
                ]),
                'explanation' => 'Report thoroughly investigated and appropriate action taken.',
                'follow_up_required' => false
            ],
            'moderator_notes' => 'Case resolved after thorough investigation.'
        ]);
    }

    /**
     * Indicate that the report has been escalated
     */
    public function escalated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ESCALATED',
            'priority' => $this->faker->randomElement(['HIGH', 'URGENT', 'CRITICAL']),
            'auto_escalated' => true,
            'escalation_reason' => $this->faker->randomElement([
                'SEVERE_VIOLATION_TYPE', 'REPEAT_OFFENDER', 'SAFETY_CONCERN'
            ]),
            'reviewed_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'moderator_notes' => 'Case escalated due to severity and safety concerns.'
        ]);
    }

    /**
     * Indicate harassment-specific report
     */
    public function harassment(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => 'HARASSMENT',
            'priority' => $this->faker->randomElement(['HIGH', 'URGENT']),
            'description' => $this->faker->randomElement([
                'This user has been sending me inappropriate messages repeatedly.',
                'Continuous unwanted contact despite being told to stop.',
                'Threatening and intimidating behavior in conversations.'
            ]),
            'evidence' => array_merge($attributes['evidence'] ?? [], [
                'types' => ['message_history', 'screenshot', 'conversation_log'],
                'messages_count' => $this->faker->numberBetween(5, 25)
            ]),
            'auto_escalated' => true,
            'escalation_reason' => 'SEVERE_VIOLATION_TYPE'
        ]);
    }

    /**
     * Indicate spam-specific report
     */
    public function spam(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => 'SPAM',
            'ml_analysis' => array_merge($attributes['ml_analysis'] ?? [], [
                'confidence_score' => $this->faker->randomFloat(2, 0.75, 0.95),
                'predicted_violation' => 'SPAM',
                'features_analyzed' => ['text_content', 'behavior_pattern', 'interaction_frequency']
            ]),
            'description' => 'Sending the same promotional message to multiple users.',
            'evidence' => array_merge($attributes['evidence'] ?? [], [
                'types' => ['message_history', 'profile_data'],
                'messages_count' => $this->faker->numberBetween(10, 50)
            ])
        ]);
    }

    /**
     * Indicate fake profile report  
     */
    public function fakeProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => 'FAKE_PROFILE',
            'priority' => 'HIGH',
            'description' => 'Using stolen photos from social media accounts.',
            'evidence' => array_merge($attributes['evidence'] ?? [], [
                'types' => ['screenshot', 'image_content', 'profile_data'],
                'screenshots_count' => $this->faker->numberBetween(2, 8)
            ]),
            'ml_analysis' => array_merge($attributes['ml_analysis'] ?? [], [
                'features_analyzed' => ['image_analysis', 'account_history', 'behavior_pattern']
            ])
        ]);
    }

    /**
     * Indicate safety concern report
     */
    public function safetyConcern(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => $this->faker->randomElement(['HARASSMENT', 'VIOLENCE', 'HATE_SPEECH']),
            'priority' => 'CRITICAL',
            'auto_escalated' => true,
            'escalation_reason' => 'SAFETY_CONCERN',
            'status' => 'ESCALATED',
            'reviewed_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'moderator_notes' => 'Immediate escalation due to safety concerns.'
        ]);
    }

    /**
     * Indicate dismissed report
     */
    public function dismissed(): static
    {
        $reviewedAt = $this->faker->dateTimeBetween('-2 weeks', 'now');
        
        return $this->state(fn (array $attributes) => [
            'status' => 'DISMISSED',
            'reviewed_at' => $reviewedAt,
            'resolved_at' => $reviewedAt,
            'ml_analysis' => array_merge($attributes['ml_analysis'] ?? [], [
                'confidence_score' => $this->faker->randomFloat(2, 0.1, 0.4),
                'risk_level' => 'LOW'
            ]),
            'resolution' => [
                'action_taken' => 'NO_ACTION',
                'explanation' => 'After investigation, no violation was found.',
                'follow_up_required' => false
            ],
            'moderator_notes' => 'Report dismissed - no policy violation detected.'
        ]);
    }

    /**
     * Indicate pending review report
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'PENDING',
            'reviewed_at' => null,
            'resolved_at' => null,
            'moderator_notes' => null,
            'resolution' => null,
            'created_at' => $this->faker->dateTimeBetween('-1 week', 'now')
        ]);
    }

    /**
     * Indicate under investigation report
     */
    public function underInvestigation(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'UNDER_INVESTIGATION',
            'priority' => $this->faker->randomElement(['MEDIUM', 'HIGH']),
            'reviewed_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'moderator_notes' => 'Case under detailed investigation by moderation team.',
            'evidence' => array_merge($attributes['evidence'] ?? [], [
                'additional_context' => 'Extended investigation required for complex case.'
            ])
        ]);
    }
}