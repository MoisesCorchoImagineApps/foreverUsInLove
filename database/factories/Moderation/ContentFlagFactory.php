<?php

namespace Database\Factories\Models\Moderation;

use App\Models\Moderation\ContentFlag;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Moderation\ContentFlag>
 */
class ContentFlagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ContentFlag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $contentType = $this->faker->randomElement(ContentFlag::CONTENT_TYPES);
        $violationCategories = $this->faker->randomElements(ContentFlag::VIOLATION_CATEGORIES, $this->faker->numberBetween(1, 3));
        $confidenceScore = $this->faker->randomFloat(2, 0.3, 0.95);
        $threatLevel = $this->determineThreatLevel($violationCategories, $confidenceScore);
        $action = $this->determineAction($confidenceScore, $threatLevel);
        
        return [
            'user_id' => User::factory(),
            'flaggable_id' => $this->faker->numberBetween(1, 1000),
            'flaggable_type' => $this->faker->randomElement([
                'App\Models\Chat\Message',
                'App\Models\User\UserImage',
                'App\Models\User\Profile',
                'App\Models\Chat\GroupChat',
                'App\Models\User\UserVideo'
            ]),
            'content_type' => $contentType,
            'content_snapshot' => $contentType === ContentFlag::CONTENT_TYPE_TEXT 
                ? $this->faker->paragraph(3) 
                : null,
            'violation_categories' => $violationCategories,
            'confidence_score' => $confidenceScore,
            'threat_level' => $threatLevel,
            'moderation_action' => $action,
            'action_taken_at' => in_array($action, [ContentFlag::ACTION_ALLOW, ContentFlag::ACTION_REQUIRE_REVIEW]) 
                ? null 
                : $this->faker->dateTimeBetween('-1 hour', 'now'),
            'automatic_analysis' => [
                'model_version' => '2.1.0',
                'processing_time_ms' => $this->faker->numberBetween(150, 2000),
                'feature_scores' => [
                    'toxicity' => $this->faker->randomFloat(2, 0, 1),
                    'sentiment' => $this->faker->randomFloat(2, -1, 1),
                    'adult_content' => $this->faker->randomFloat(2, 0, 1),
                    'spam_likelihood' => $this->faker->randomFloat(2, 0, 1)
                ],
                'rule_matches' => array_map(function($violation) {
                    return [
                        'rule' => $violation,
                        'confidence' => $this->faker->randomFloat(2, 0.5, 0.95),
                        'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical'])
                    ];
                }, $violationCategories),
                'context_analysis' => [
                    'user_history_clean' => $this->faker->boolean(70),
                    'content_frequency' => $this->faker->randomElement(['normal', 'high', 'suspicious']),
                    'peer_comparison' => $this->faker->randomElement(['typical', 'outlier', 'concerning'])
                ]
            ],
            'human_reviewed' => $this->faker->boolean(30),
            'reviewer_id' => $this->faker->boolean(30) ? User::factory() : null,
            'reviewed_at' => $this->faker->boolean(30) 
                ? $this->faker->dateTimeBetween('-1 week', 'now') 
                : null,
            'review_notes' => $this->faker->boolean(30) 
                ? $this->faker->sentence() 
                : null,
            'flagged_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'metadata' => [
                'detection_method' => $this->faker->randomElement(['ml_automatic', 'rule_based', 'user_report', 'admin_flag']),
                'content_context' => $this->faker->randomElement(ContentFlag::MODERATION_CONTEXTS),
                'user_relationship' => $this->faker->randomElement(['strangers', 'matched', 'chatting', 'blocked']),
                'platform_source' => $this->faker->randomElement(['web', 'ios', 'android']),
                'geolocation' => [
                    'country' => $this->faker->countryCode(),
                    'timezone' => $this->faker->timezone(),
                    'ip_hash' => hash('sha256', $this->faker->ipv4())
                ],
                'technical_details' => [
                    'user_agent' => $this->faker->userAgent(),
                    'session_id' => $this->faker->uuid(),
                    'content_length' => $this->faker->numberBetween(10, 5000),
                    'upload_timestamp' => $this->faker->unixTime()
                ]
            ],
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * State for high-confidence violations requiring immediate action.
     */
    public function highRisk(): static
    {
        return $this->state(function (array $attributes) {
            $criticalViolations = [
                ContentFlag::VIOLATION_UNDERAGE_CONTENT,
                ContentFlag::VIOLATION_ILLEGAL_CONTENT,
                ContentFlag::VIOLATION_THREATS,
                ContentFlag::VIOLATION_VIOLENCE
            ];
            
            return [
                'violation_categories' => $this->faker->randomElements($criticalViolations, $this->faker->numberBetween(1, 2)),
                'confidence_score' => $this->faker->randomFloat(2, 0.85, 0.98),
                'threat_level' => $this->faker->randomElement(['high', 'critical']),
                'moderation_action' => $this->faker->randomElement([
                    ContentFlag::ACTION_BLOCK,
                    ContentFlag::ACTION_HIDE,
                    ContentFlag::ACTION_ESCALATE
                ]),
                'action_taken_at' => now()->subMinutes($this->faker->numberBetween(1, 30)),
                'requires_human_review' => true,
                'automatic_analysis' => array_merge($attributes['automatic_analysis'] ?? [], [
                    'risk_indicators' => [
                        'severity_score' => $this->faker->numberBetween(85, 100),
                        'threat_assessment' => 'immediate_action_required',
                        'legal_implications' => $this->faker->boolean(60),
                        'user_safety_risk' => 'high'
                    ]
                ]),
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'priority_level' => 'critical',
                    'escalation_required' => true,
                    'law_enforcement_notification' => $this->faker->boolean(30),
                    'emergency_response_triggered' => $this->faker->boolean(20)
                ])
            ];
        });
    }

    /**
     * State for content that has been reviewed by humans.
     */
    public function humanReviewed(): static
    {
        return $this->state(function (array $attributes) {
            $reviewerDecision = $this->faker->randomElement([
                ContentFlag::ACTION_ALLOW,
                ContentFlag::ACTION_FLAG,
                ContentFlag::ACTION_HIDE,
                ContentFlag::ACTION_BLOCK,
                ContentFlag::ACTION_WARN_USER
            ]);
            
            return [
                'human_reviewed' => true,
                'reviewer_id' => User::factory(),
                'reviewed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
                'moderation_action' => $reviewerDecision,
                'review_notes' => $this->generateReviewNotes($reviewerDecision),
                'action_taken_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'review_time_minutes' => $this->faker->numberBetween(1, 45),
                    'reviewer_experience_level' => $this->faker->randomElement(['junior', 'senior', 'expert']),
                    'review_complexity' => $this->faker->randomElement(['simple', 'moderate', 'complex']),
                    'ml_human_agreement' => $this->faker->boolean(75),
                    'quality_score' => $this->faker->randomFloat(1, 3.5, 5.0)
                ])
            ];
        });
    }

    /**
     * State for false positives (wrongly flagged content).
     */
    public function falsePositive(): static
    {
        return $this->state(fn (array $attributes) => [
            'violation_categories' => [$this->faker->randomElement([
                ContentFlag::VIOLATION_SPAM,
                ContentFlag::VIOLATION_COMMERCIAL_CONTENT,
                ContentFlag::VIOLATION_MISLEADING_INFO
            ])],
            'confidence_score' => $this->faker->randomFloat(2, 0.3, 0.65),
            'threat_level' => 'low',
            'moderation_action' => ContentFlag::ACTION_ALLOW,
            'human_reviewed' => true,
            'reviewer_id' => User::factory(),
            'reviewed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'review_notes' => 'False positive - content is acceptable',
            'automatic_analysis' => array_merge($attributes['automatic_analysis'] ?? [], [
                'false_positive_indicators' => [
                    'edge_case' => true,
                    'context_misunderstanding' => $this->faker->boolean(60),
                    'cultural_nuance_missed' => $this->faker->boolean(40),
                    'sarcasm_detection_failed' => $this->faker->boolean(30)
                ]
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'model_improvement_candidate' => true,
                'training_data_candidate' => true,
                'feedback_provided' => true
            ])
        ]);
    }

    /**
     * State for pending manual review.
     */
    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence_score' => $this->faker->randomFloat(2, 0.4, 0.75),
            'moderation_action' => ContentFlag::ACTION_REQUIRE_REVIEW,
            'human_reviewed' => false,
            'reviewer_id' => null,
            'reviewed_at' => null,
            'review_notes' => null,
            'action_taken_at' => null,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'review_queue_position' => $this->faker->numberBetween(1, 100),
                'estimated_review_time' => $this->faker->randomElement(['< 1 hour', '1-4 hours', '4-24 hours']),
                'queue_priority' => $this->faker->randomElement(['normal', 'high', 'urgent']),
                'reviewer_assignment_pending' => true
            ])
        ]);
    }

    /**
     * State for text content moderation.
     */
    public function textContent(): static
    {
        return $this->state(function (array $attributes) {
            $textViolations = [
                ContentFlag::VIOLATION_HATE_SPEECH,
                ContentFlag::VIOLATION_HARASSMENT,
                ContentFlag::VIOLATION_SPAM,
                ContentFlag::VIOLATION_THREATS,
                ContentFlag::VIOLATION_SOLICITATION
            ];
            
            return [
                'content_type' => ContentFlag::CONTENT_TYPE_TEXT,
                'flaggable_type' => $this->faker->randomElement([
                    'App\Models\Chat\Message',
                    'App\Models\User\Profile'
                ]),
                'content_snapshot' => $this->faker->paragraph(2),
                'violation_categories' => $this->faker->randomElements($textViolations, $this->faker->numberBetween(1, 2)),
                'automatic_analysis' => array_merge($attributes['automatic_analysis'] ?? [], [
                    'language_detection' => $this->faker->randomElement(['en', 'es', 'fr', 'de', 'pt']),
                    'sentiment_analysis' => [
                        'overall_sentiment' => $this->faker->randomFloat(2, -1, 1),
                        'emotional_tone' => $this->faker->randomElement(['neutral', 'positive', 'negative', 'aggressive']),
                        'formality_level' => $this->faker->randomElement(['casual', 'formal', 'slang'])
                    ],
                    'linguistic_features' => [
                        'profanity_count' => $this->faker->numberBetween(0, 10),
                        'caps_lock_ratio' => $this->faker->randomFloat(2, 0, 1),
                        'exclamation_count' => $this->faker->numberBetween(0, 5),
                        'repeated_characters' => $this->faker->boolean(30)
                    ]
                ])
            ];
        });
    }

    /**
     * State for image content moderation.
     */
    public function imageContent(): static
    {
        return $this->state(function (array $attributes) {
            $imageViolations = [
                ContentFlag::VIOLATION_ADULT_CONTENT,
                ContentFlag::VIOLATION_NUDITY,
                ContentFlag::VIOLATION_INAPPROPRIATE_PHOTOS,
                ContentFlag::VIOLATION_FAKE_IDENTITY,
                ContentFlag::VIOLATION_UNDERAGE_CONTENT
            ];
            
            return [
                'content_type' => ContentFlag::CONTENT_TYPE_IMAGE,
                'flaggable_type' => 'App\Models\User\UserImage',
                'content_snapshot' => null,
                'violation_categories' => $this->faker->randomElements($imageViolations, $this->faker->numberBetween(1, 2)),
                'automatic_analysis' => array_merge($attributes['automatic_analysis'] ?? [], [
                    'image_analysis' => [
                        'nsfw_score' => $this->faker->randomFloat(2, 0, 1),
                        'face_detection' => [
                            'faces_count' => $this->faker->numberBetween(0, 5),
                            'age_estimation' => $this->faker->numberBetween(16, 65),
                            'expression' => $this->faker->randomElement(['neutral', 'happy', 'serious', 'provocative'])
                        ],
                        'object_detection' => [
                            'inappropriate_objects' => $this->faker->randomElements(['weapon', 'drug', 'alcohol', 'nudity'], 0, 2),
                            'location_clues' => $this->faker->randomElement(['indoor', 'outdoor', 'bedroom', 'bathroom', 'public'])
                        ],
                        'technical_quality' => [
                            'resolution' => $this->faker->randomElement(['low', 'medium', 'high']),
                            'blur_level' => $this->faker->randomFloat(2, 0, 1),
                            'lighting' => $this->faker->randomElement(['poor', 'adequate', 'good']),
                            'color_saturation' => $this->faker->randomFloat(2, 0, 1)
                        ]
                    ]
                ])
            ];
        });
    }

    /**
     * State for behavior pattern analysis.
     */
    public function behaviorAnalysis(): static
    {
        return $this->state(function (array $attributes) {
            $behaviorViolations = [
                ContentFlag::VIOLATION_SPAM,
                ContentFlag::VIOLATION_FAKE_IDENTITY,
                ContentFlag::VIOLATION_SCAM,
                ContentFlag::VIOLATION_HARASSMENT
            ];
            
            return [
                'content_type' => ContentFlag::CONTENT_TYPE_USER_BEHAVIOR,
                'flaggable_type' => 'App\Models\User\User',
                'content_snapshot' => null,
                'violation_categories' => $this->faker->randomElements($behaviorViolations, $this->faker->numberBetween(1, 2)),
                'automatic_analysis' => array_merge($attributes['automatic_analysis'] ?? [], [
                    'behavior_patterns' => [
                        'message_frequency' => $this->faker->randomElement(['normal', 'high', 'excessive']),
                        'profile_changes_frequency' => $this->faker->numberBetween(0, 20),
                        'match_request_pattern' => $this->faker->randomElement(['selective', 'indiscriminate', 'suspicious']),
                        'response_rate' => $this->faker->randomFloat(2, 0, 1),
                        'session_duration' => $this->faker->randomElement(['normal', 'brief', 'extended']),
                        'geographical_inconsistency' => $this->faker->boolean(20)
                    ],
                    'risk_indicators' => [
                        'multiple_accounts' => $this->faker->boolean(15),
                        'device_sharing' => $this->faker->boolean(10),
                        'vpn_usage' => $this->faker->boolean(25),
                        'automation_detected' => $this->faker->boolean(5)
                    ]
                ])
            ];
        });
    }

    /**
     * State for escalated content requiring admin review.
     */
    public function escalated(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'moderation_action' => ContentFlag::ACTION_ESCALATE,
                'confidence_score' => $this->faker->randomFloat(2, 0.6, 0.9),
                'threat_level' => $this->faker->randomElement(['high', 'critical']),
                'human_reviewed' => false,
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'escalation_reason' => $this->faker->randomElement([
                        'legal_implications',
                        'user_safety_concern',
                        'policy_ambiguity',
                        'repeat_offender',
                        'high_profile_user'
                    ]),
                    'escalated_to' => $this->faker->randomElement(['senior_moderator', 'admin_team', 'legal_team']),
                    'escalation_timestamp' => now()->subHours($this->faker->numberBetween(1, 24))->toISOString(),
                    'urgency_level' => $this->faker->randomElement(['medium', 'high', 'critical']),
                    'estimated_resolution_time' => $this->faker->randomElement(['24h', '48h', '72h', '1 week'])
                ])
            ];
        });
    }

    /**
     * Helper method to determine threat level based on violations and confidence.
     */
    private function determineThreatLevel(array $violations, float $confidence): string
    {
        $highThreatViolations = [
            ContentFlag::VIOLATION_UNDERAGE_CONTENT,
            ContentFlag::VIOLATION_ILLEGAL_CONTENT,
            ContentFlag::VIOLATION_THREATS,
            ContentFlag::VIOLATION_VIOLENCE
        ];

        $hasHighThreat = !empty(array_intersect($violations, $highThreatViolations));
        
        if ($hasHighThreat && $confidence > 0.8) {
            return 'critical';
        } elseif ($hasHighThreat || $confidence > 0.7) {
            return 'high';
        } elseif ($confidence > 0.5) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Helper method to determine moderation action based on confidence and threat.
     */
    private function determineAction(float $confidence, string $threatLevel): string
    {
        if ($confidence < 0.6) {
            return ContentFlag::ACTION_REQUIRE_REVIEW;
        }

        return match($threatLevel) {
            'critical' => $this->faker->randomElement([ContentFlag::ACTION_BLOCK, ContentFlag::ACTION_ESCALATE]),
            'high' => $this->faker->randomElement([ContentFlag::ACTION_HIDE, ContentFlag::ACTION_BLOCK]),
            'medium' => $this->faker->randomElement([ContentFlag::ACTION_FLAG, ContentFlag::ACTION_BLUR]),
            'low' => ContentFlag::ACTION_ALLOW,
            default => ContentFlag::ACTION_REQUIRE_REVIEW
        };
    }

    /**
     * Helper method to generate realistic review notes.
     */
    private function generateReviewNotes(string $decision): string
    {
        return match($decision) {
            ContentFlag::ACTION_ALLOW => 'Content reviewed and approved - no policy violations found',
            ContentFlag::ACTION_FLAG => 'Content flagged for monitoring - borderline case',
            ContentFlag::ACTION_HIDE => 'Content hidden due to policy violation - user notified',
            ContentFlag::ACTION_BLOCK => 'Content blocked - serious policy violation confirmed',
            ContentFlag::ACTION_WARN_USER => 'Warning issued to user - minor policy violation',
            default => 'Content requires further review'
        };
    }
}