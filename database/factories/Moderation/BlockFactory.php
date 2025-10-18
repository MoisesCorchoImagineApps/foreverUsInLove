<?php

namespace Database\Factories\Models\Moderation;

use App\Models\Moderation\Block;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Moderation\Block>
 */
class BlockFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Block::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $blockType = $this->faker->randomElement(Block::BLOCK_TYPES);
        $reason = $this->faker->randomElement(Block::BLOCK_REASONS);
        
        return [
            'blocker_id' => User::factory(),
            'blocked_id' => User::factory(),
            'block_type' => $blockType,
            'reason' => $reason,
            'status' => $this->faker->randomElement(Block::STATUSES),
            'is_mutual' => $this->faker->boolean(15),
            'is_temporary' => $blockType === Block::BLOCK_TYPE_TEMPORARY,
            'blocked_until' => $blockType === Block::BLOCK_TYPE_TEMPORARY 
                ? $this->faker->dateTimeBetween('now', '+30 days') 
                : null,
            'description' => $this->faker->optional(0.6)->paragraph(2),
            'context' => [
                'source' => $this->faker->randomElement(['profile', 'chat', 'report', 'system']),
                'interaction_count' => $this->faker->numberBetween(1, 50),
                'last_interaction' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d H:i:s'),
                'platform' => $this->faker->randomElement(['web', 'ios', 'android'])
            ],
            'automatic_unblock_at' => $blockType === Block::BLOCK_TYPE_TEMPORARY
                ? $this->faker->dateTimeBetween('now', '+30 days')
                : null,
            'consequences' => [
                'profile_visibility' => 'blocked',
                'message_blocking' => true,
                'match_prevention' => true,
                'search_visibility' => false
            ],
            'metadata' => [
                'severity_level' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
                'block_source' => $this->faker->randomElement(['user_initiated', 'system_automated', 'admin_action']),
                'related_reports' => $this->faker->optional(0.3)->numberBetween(1, 5),
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
                'device_info' => [
                    'platform' => $this->faker->randomElement(['iOS', 'Android', 'Web']),
                    'version' => $this->faker->randomFloat(1, 10, 16),
                    'device_id' => $this->faker->uuid()
                ]
            ],
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * State for mutual blocks (both users block each other).
     */
    public function mutual(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_mutual' => true,
            'block_type' => Block::BLOCK_TYPE_FULL,
            'reason' => $this->faker->randomElement([
                Block::REASON_HARASSMENT,
                Block::REASON_INAPPROPRIATE_BEHAVIOR,
                Block::REASON_FAKE_PROFILE,
                Block::REASON_SPAM_SCAM
            ]),
            'status' => Block::STATUS_ACTIVE,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'mutual_block' => true,
                'escalation_level' => 'high',
                'requires_admin_review' => true,
                'block_pattern' => 'mutual_conflict'
            ])
        ]);
    }

    /**
     * State for temporary blocks with specific duration.
     */
    public function temporary(): static
    {
        return $this->state(function (array $attributes) {
            $duration = $this->faker->randomElement([1, 3, 7, 14, 30]); // days
            $blockedUntil = now()->addDays($duration);
            
            return [
                'block_type' => Block::BLOCK_TYPE_TEMPORARY,
                'is_temporary' => true,
                'blocked_until' => $blockedUntil,
                'automatic_unblock_at' => $blockedUntil,
                'reason' => $this->faker->randomElement([
                    Block::REASON_COOLING_OFF,
                    Block::REASON_INAPPROPRIATE_CONTENT,
                    Block::REASON_SPAM_SCAM
                ]),
                'status' => Block::STATUS_ACTIVE,
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'temporary_duration_days' => $duration,
                    'auto_unblock_scheduled' => true,
                    'cooling_off_period' => true,
                    'escalation_prevention' => true
                ])
            ];
        });
    }

    /**
     * State for shadow bans (user doesn't know they're blocked).
     */
    public function shadowBan(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_type' => Block::BLOCK_TYPE_SHADOW,
            'reason' => $this->faker->randomElement([
                Block::REASON_SPAM_SCAM,
                Block::REASON_FAKE_PROFILE,
                Block::REASON_INAPPROPRIATE_BEHAVIOR,
                Block::REASON_POLICY_VIOLATION
            ]),
            'status' => Block::STATUS_ACTIVE,
            'is_temporary' => false,
            'description' => 'Shadow ban applied due to suspicious activity patterns',
            'consequences' => [
                'profile_visibility' => 'shadow_banned',
                'message_delivery' => 'delayed',
                'match_frequency' => 'reduced',
                'search_ranking' => 'lowered',
                'notification_suppression' => true
            ],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'shadow_ban' => true,
                'stealth_mode' => true,
                'user_awareness' => false,
                'behavior_modification_target' => true,
                'pattern_analysis' => [
                    'spam_score' => $this->faker->randomFloat(2, 0.6, 0.95),
                    'fake_probability' => $this->faker->randomFloat(2, 0.5, 0.9),
                    'violation_frequency' => $this->faker->numberBetween(3, 10)
                ]
            ])
        ]);
    }

    /**
     * State for partial blocks (limited interactions).
     */
    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_type' => Block::BLOCK_TYPE_PARTIAL,
            'reason' => $this->faker->randomElement([
                Block::REASON_MINOR_VIOLATIONS,
                Block::REASON_INAPPROPRIATE_CONTENT,
                Block::REASON_EXCESSIVE_REPORTING
            ]),
            'status' => Block::STATUS_ACTIVE,
            'consequences' => [
                'profile_visibility' => 'limited',
                'message_blocking' => false,
                'match_prevention' => false,
                'search_visibility' => true,
                'feature_restrictions' => [
                    'super_likes_disabled' => $this->faker->boolean(60),
                    'boost_disabled' => $this->faker->boolean(40),
                    'gif_messages_disabled' => $this->faker->boolean(30),
                    'photo_sharing_limited' => $this->faker->boolean(50)
                ]
            ],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'partial_restrictions' => true,
                'graduated_punishment' => true,
                'warning_issued' => true,
                'escalation_path' => 'partial_to_full',
                'review_scheduled' => now()->addDays(7)->format('Y-m-d H:i:s')
            ])
        ]);
    }

    /**
     * State for expired blocks.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Block::STATUS_EXPIRED,
            'is_temporary' => true,
            'blocked_until' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            'automatic_unblock_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'expired_naturally' => true,
                'duration_completed' => true,
                'auto_unblocked' => true,
                'post_expiry_monitoring' => $this->faker->boolean(70)
            ])
        ]);
    }

    /**
     * State for admin-initiated blocks.
     */
    public function adminBlock(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_type' => Block::BLOCK_TYPE_FULL,
            'reason' => $this->faker->randomElement([
                Block::REASON_TERMS_VIOLATION,
                Block::REASON_POLICY_VIOLATION,
                Block::REASON_LEGAL_ISSUES,
                Block::REASON_SAFETY_CONCERNS
            ]),
            'status' => Block::STATUS_ACTIVE,
            'description' => 'Administrative block due to policy violations',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'admin_initiated' => true,
                'admin_id' => $this->faker->numberBetween(1, 10),
                'admin_notes' => 'Blocked for serious policy violations',
                'requires_admin_review' => true,
                'escalation_level' => 'administrative',
                'appeal_allowed' => $this->faker->boolean(60),
                'compliance_case' => 'COMP-' . $this->faker->numerify('####-####')
            ])
        ]);
    }

    /**
     * State for system-automated blocks.
     */
    public function systemBlock(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_type' => $this->faker->randomElement([
                Block::BLOCK_TYPE_SHADOW,
                Block::BLOCK_TYPE_PARTIAL,
                Block::BLOCK_TYPE_TEMPORARY
            ]),
            'reason' => $this->faker->randomElement([
                Block::REASON_SPAM_SCAM,
                Block::REASON_FAKE_PROFILE,
                Block::REASON_POLICY_VIOLATION
            ]),
            'status' => Block::STATUS_ACTIVE,
            'description' => 'Automated system block based on behavioral analysis',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'system_automated' => true,
                'ai_detection' => true,
                'confidence_score' => $this->faker->randomFloat(2, 0.75, 0.99),
                'ml_model_version' => '2.1.0',
                'detection_rules' => [
                    'spam_threshold_exceeded' => $this->faker->boolean(60),
                    'fake_profile_indicators' => $this->faker->boolean(40),
                    'behavioral_anomalies' => $this->faker->boolean(30)
                ],
                'auto_review_scheduled' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'human_review_required' => $this->faker->boolean(40)
            ])
        ]);
    }

    /**
     * State for harassment-related blocks.
     */
    public function harassment(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_type' => Block::BLOCK_TYPE_FULL,
            'reason' => Block::REASON_HARASSMENT,
            'status' => Block::STATUS_ACTIVE,
            'description' => 'Block due to harassment behavior reported by user',
            'context' => array_merge($attributes['context'] ?? [], [
                'harassment_type' => $this->faker->randomElement([
                    'verbal_abuse',
                    'sexual_harassment',
                    'cyberbullying',
                    'stalking_behavior',
                    'threatening_messages'
                ]),
                'evidence_count' => $this->faker->numberBetween(1, 10),
                'witness_reports' => $this->faker->numberBetween(0, 3)
            ]),
            'consequences' => [
                'profile_visibility' => 'blocked',
                'message_blocking' => true,
                'match_prevention' => true,
                'search_visibility' => false,
                'report_generated' => true,
                'safety_measures_applied' => true
            ],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'harassment_case' => true,
                'safety_priority' => 'high',
                'victim_protection_enabled' => true,
                'counseling_resources_offered' => true,
                'legal_compliance_required' => $this->faker->boolean(30)
            ])
        ]);
    }

    /**
     * State for blocks due to fake profiles.
     */
    public function fakeProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'block_type' => $this->faker->randomElement([
                Block::BLOCK_TYPE_SHADOW,
                Block::BLOCK_TYPE_FULL
            ]),
            'reason' => Block::REASON_FAKE_PROFILE,
            'status' => Block::STATUS_ACTIVE,
            'description' => 'Profile identified as fake or using stolen photos',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'fake_profile_indicators' => [
                    'stolen_photos' => $this->faker->boolean(70),
                    'inconsistent_information' => $this->faker->boolean(60),
                    'suspicious_creation_pattern' => $this->faker->boolean(50),
                    'reverse_image_search_matches' => $this->faker->numberBetween(1, 5)
                ],
                'verification_required' => true,
                'photo_verification_needed' => true,
                'identity_documents_required' => $this->faker->boolean(80),
                'catfish_probability' => $this->faker->randomFloat(2, 0.6, 0.95)
            ])
        ]);
    }

    /**
     * State for inactive blocks (no longer active).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement([
                Block::STATUS_INACTIVE,
                Block::STATUS_REVERSED,
                Block::STATUS_EXPIRED
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'deactivation_reason' => $this->faker->randomElement([
                    'user_unblocked',
                    'admin_reversed',
                    'appeal_successful',
                    'time_expired',
                    'system_error'
                ]),
                'deactivated_at' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d H:i:s'),
                'active_duration_days' => $this->faker->numberBetween(1, 365)
            ])
        ]);
    }
}