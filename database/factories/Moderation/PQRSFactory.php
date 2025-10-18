<?php

namespace Database\Factories\Models\Moderation;

use App\Models\Moderation\PQRS;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Moderation\PQRS>
 */
class PQRSFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PQRS::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(PQRS::TYPES);
        $category = $this->faker->randomElement(PQRS::CATEGORIES);
        $priority = PQRS::CATEGORY_DEFAULT_PRIORITY[$category] ?? PQRS::PRIORITY_MEDIUM;
        $status = $this->faker->randomElement(PQRS::STATUSES);
        $submittedAt = $this->faker->dateTimeBetween('-3 months', 'now');
        $slaHours = PQRS::SLA_HOURS[$priority] ?? 72;
        
        return [
            'ticket_number' => null, // Will be auto-generated
            'user_id' => User::factory(),
            'assigned_to' => $this->faker->boolean(60) ? User::factory() : null,
            'type' => $type,
            'category' => $category,
            'priority' => $priority,
            'status' => $status,
            'channel' => $this->faker->randomElement(PQRS::CHANNELS),
            'subject' => $this->generateSubject($type, $category),
            'description' => $this->generateDescription($type, $category),
            'attachments' => $this->faker->optional(0.3)->passthrough([
                $this->faker->imageUrl(800, 600, 'business'),
                $this->faker->optional(0.5)->imageUrl(400, 300, 'business'),
                $this->faker->optional(0.3)->url() . '/document.pdf'
            ]),
            'submitted_at' => $submittedAt,
            'assigned_at' => $this->faker->boolean(60) 
                ? $this->faker->dateTimeBetween($submittedAt, 'now') 
                : null,
            'resolved_at' => $status === PQRS::STATUS_RESOLVED 
                ? $this->faker->dateTimeBetween($submittedAt, 'now') 
                : null,
            'closed_at' => $status === PQRS::STATUS_CLOSED 
                ? $this->faker->dateTimeBetween($submittedAt, 'now') 
                : null,
            'sla_deadline' => $submittedAt->copy()->addHours($slaHours),
            'response_count' => $this->faker->numberBetween(0, 8),
            'satisfaction_rating' => $status === PQRS::STATUS_RESOLVED 
                ? $this->faker->optional(0.7)->numberBetween(1, 5) 
                : null,
            'metadata' => [
                'source_details' => [
                    'user_agent' => $this->faker->userAgent(),
                    'ip_address' => $this->faker->ipv4(),
                    'referrer' => $this->faker->optional(0.6)->url(),
                    'session_id' => $this->faker->uuid()
                ],
                'customer_info' => [
                    'account_age_days' => $this->faker->numberBetween(1, 1095),
                    'subscription_status' => $this->faker->randomElement(['free', 'basic', 'premium', 'vip']),
                    'previous_tickets' => $this->faker->numberBetween(0, 10),
                    'satisfaction_history' => $this->faker->optional(0.6)->randomFloat(1, 2.5, 5.0)
                ],
                'technical_context' => [
                    'app_version' => $this->faker->semver(),
                    'operating_system' => $this->faker->randomElement(['iOS 17.1', 'Android 14', 'Windows 11', 'macOS 14']),
                    'device_type' => $this->faker->randomElement(['iPhone', 'Android Phone', 'Desktop', 'Tablet']),
                    'connection_type' => $this->faker->randomElement(['WiFi', '4G', '5G', 'Ethernet'])
                ],
                'business_impact' => [
                    'severity_assessment' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
                    'affected_features' => $this->faker->optional(0.7)->randomElements(['messaging', 'matching', 'payments', 'profile'], $this->faker->numberBetween(1, 3)),
                    'estimated_users_affected' => $this->faker->optional(0.4)->numberBetween(1, 10000),
                    'revenue_impact' => $this->faker->optional(0.3)->randomFloat(2, 0, 5000)
                ]
            ],
            'created_at' => $submittedAt,
            'updated_at' => $this->faker->dateTimeBetween($submittedAt, 'now'),
        ];
    }

    /**
     * State for high priority PQRS requiring immediate attention.
     */
    public function highPriority(): static
    {
        return $this->state(function (array $attributes) {
            $urgentCategories = [
                PQRS::CATEGORY_ACCOUNT_SUSPENSION,
                PQRS::CATEGORY_PAYMENT_FAILED,
                PQRS::CATEGORY_LOGIN_PROBLEMS,
                PQRS::CATEGORY_SAFETY_CONCERN
            ];
            
            $category = $this->faker->randomElement($urgentCategories);
            $priority = PQRS::CATEGORY_DEFAULT_PRIORITY[$category];
            $submittedAt = $this->faker->dateTimeBetween('-24 hours', 'now');
            
            return [
                'category' => $category,
                'priority' => $priority,
                'status' => $this->faker->randomElement([
                    PQRS::STATUS_SUBMITTED,
                    PQRS::STATUS_IN_REVIEW,
                    PQRS::STATUS_ASSIGNED
                ]),
                'subject' => $this->generateUrgentSubject($category),
                'description' => $this->generateUrgentDescription($category),
                'submitted_at' => $submittedAt,
                'sla_deadline' => $submittedAt->copy()->addHours(PQRS::SLA_HOURS[$priority]),
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'urgency_indicators' => [
                        'user_premium' => $this->faker->boolean(70),
                        'business_critical' => $this->faker->boolean(80),
                        'escalation_potential' => 'high',
                        'customer_value' => $this->faker->randomElement(['high', 'vip', 'enterprise'])
                    ],
                    'notifications_sent' => [
                        'slack_alert' => true,
                        'email_manager' => true,
                        'sms_oncall' => $priority === PQRS::PRIORITY_CRITICAL
                    ]
                ])
            ];
        });
    }

    /**
     * State for resolved PQRS with satisfaction ratings.
     */
    public function resolved(): static
    {
        return $this->state(function (array $attributes) {
            $submittedAt = $this->faker->dateTimeBetween('-2 months', '-1 week');
            $resolvedAt = $this->faker->dateTimeBetween($submittedAt, '-1 day');
            $rating = $this->faker->numberBetween(1, 5);
            
            return [
                'status' => PQRS::STATUS_RESOLVED,
                'submitted_at' => $submittedAt,
                'assigned_to' => User::factory(),
                'assigned_at' => $this->faker->dateTimeBetween($submittedAt, $resolvedAt),
                'resolved_at' => $resolvedAt,
                'response_count' => $this->faker->numberBetween(1, 6),
                'satisfaction_rating' => $rating,
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'resolution_details' => [
                        'resolution_time_hours' => $submittedAt->diffInHours($resolvedAt),
                        'escalation_count' => $this->faker->numberBetween(0, 2),
                        'solution_category' => $this->faker->randomElement([
                            'technical_fix',
                            'user_education',
                            'policy_clarification',
                            'account_adjustment',
                            'system_update'
                        ]),
                        'follow_up_required' => $this->faker->boolean(20)
                    ],
                    'satisfaction_details' => [
                        'rating_provided_at' => $resolvedAt->copy()->addDays($this->faker->numberBetween(1, 7))->format('Y-m-d H:i:s'),
                        'feedback_text' => $rating >= 4 
                            ? $this->faker->randomElement([
                                'Great service, resolved quickly!',
                                'Very helpful and professional',
                                'Problem solved efficiently'
                            ])
                            : $this->faker->randomElement([
                                'Took too long to resolve',
                                'Could have been clearer',
                                'Had to follow up multiple times'
                            ]),
                        'recommendation_likelihood' => $this->faker->numberBetween(1, 10)
                    ]
                ])
            ];
        });
    }

    /**
     * State for escalated PQRS requiring management attention.
     */
    public function escalated(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => PQRS::STATUS_ESCALATED,
                'priority' => $this->faker->randomElement([
                    PQRS::PRIORITY_HIGH,
                    PQRS::PRIORITY_URGENT,
                    PQRS::PRIORITY_CRITICAL
                ]),
                'assigned_to' => User::factory(),
                'response_count' => $this->faker->numberBetween(3, 10),
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'escalation_history' => [
                        'escalated_at' => $this->faker->dateTimeBetween('-48 hours', 'now')->format('Y-m-d H:i:s'),
                        'escalated_by' => $this->faker->randomElement(['system', 'agent', 'customer']),
                        'escalation_reason' => $this->faker->randomElement([
                            'sla_breach',
                            'customer_dissatisfaction',
                            'complex_technical_issue',
                            'policy_exception_required',
                            'legal_implications'
                        ]),
                        'previous_agents' => $this->faker->numberBetween(1, 3),
                        'escalation_level' => $this->faker->randomElement(['tier_2', 'manager', 'director'])
                    ],
                    'management_notes' => [
                        'requires_director_approval' => $this->faker->boolean(30),
                        'legal_review_needed' => $this->faker->boolean(20),
                        'policy_update_candidate' => $this->faker->boolean(15),
                        'training_opportunity' => $this->faker->boolean(25)
                    ]
                ])
            ];
        });
    }

    /**
     * State for technical issues requiring specialized support.
     */
    public function technicalIssue(): static
    {
        return $this->state(function (array $attributes) {
            $techCategories = [
                PQRS::CATEGORY_TECHNICAL_ISSUE,
                PQRS::CATEGORY_APP_BUG,
                PQRS::CATEGORY_PERFORMANCE_ISSUE,
                PQRS::CATEGORY_LOGIN_PROBLEMS
            ];
            
            $category = $this->faker->randomElement($techCategories);
            
            return [
                'type' => PQRS::TYPE_COMPLAINT,
                'category' => $category,
                'channel' => $this->faker->randomElement([PQRS::CHANNEL_IN_APP, PQRS::CHANNEL_EMAIL]),
                'subject' => $this->generateTechnicalSubject($category),
                'description' => $this->generateTechnicalDescription($category),
                'attachments' => [
                    $this->faker->imageUrl(1200, 800, 'technics') . '?screenshot',
                    $this->faker->optional(0.6)->url() . '/error_log.txt',
                    $this->faker->optional(0.4)->url() . '/network_trace.har'
                ],
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'technical_details' => [
                        'error_code' => $this->faker->optional(0.7)->bothify('ERR-####-??##'),
                        'stack_trace' => $this->faker->optional(0.5)->boolean(),
                        'reproduction_steps' => $this->faker->boolean(80),
                        'browser_version' => $this->faker->optional(0.6)->chrome(),
                        'screen_resolution' => $this->faker->randomElement(['1920x1080', '1366x768', '414x896', '393x851']),
                        'memory_usage' => $this->faker->optional(0.4)->numberBetween(100, 2000) . 'MB'
                    ],
                    'diagnostic_info' => [
                        'network_type' => $this->faker->randomElement(['WiFi', '4G', '5G', 'Ethernet']),
                        'connection_speed' => $this->faker->randomElement(['slow', 'medium', 'fast']),
                        'server_region' => $this->faker->randomElement(['us-east', 'us-west', 'eu-central', 'asia-pacific']),
                        'load_time_ms' => $this->faker->numberBetween(500, 15000)
                    ]
                ])
            ];
        });
    }

    /**
     * State for billing and payment related PQRS.
     */
    public function billingIssue(): static
    {
        return $this->state(function (array $attributes) {
            $billingCategories = [
                PQRS::CATEGORY_BILLING_ISSUE,
                PQRS::CATEGORY_REFUND_REQUEST,
                PQRS::CATEGORY_SUBSCRIPTION_PROBLEM,
                PQRS::CATEGORY_PAYMENT_FAILED
            ];
            
            $category = $this->faker->randomElement($billingCategories);
            
            return [
                'type' => $this->faker->randomElement([PQRS::TYPE_COMPLAINT, PQRS::TYPE_CLAIM]),
                'category' => $category,
                'priority' => PQRS::CATEGORY_DEFAULT_PRIORITY[$category],
                'subject' => $this->generateBillingSubject($category),
                'description' => $this->generateBillingDescription($category),
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'billing_context' => [
                        'transaction_id' => $this->faker->bothify('TXN-########-????'),
                        'amount' => $this->faker->randomFloat(2, 4.99, 99.99),
                        'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'CAD']),
                        'payment_method' => $this->faker->randomElement(['credit_card', 'paypal', 'apple_pay', 'google_pay']),
                        'subscription_plan' => $this->faker->optional(0.8)->randomElement(['basic', 'premium', 'vip', 'elite']),
                        'billing_cycle' => $this->faker->randomElement(['monthly', 'quarterly', 'yearly'])
                    ],
                    'financial_impact' => [
                        'disputed_amount' => $this->faker->randomFloat(2, 4.99, 99.99),
                        'refund_eligible' => $this->faker->boolean(70),
                        'chargeback_risk' => $this->faker->randomElement(['low', 'medium', 'high']),
                        'customer_lifetime_value' => $this->faker->randomFloat(2, 50, 500)
                    ]
                ])
            ];
        });
    }

    /**
     * State for safety and moderation concerns.
     */
    public function safetyIssue(): static
    {
        return $this->state(function (array $attributes) {
            $safetyCategories = [
                PQRS::CATEGORY_SAFETY_CONCERN,
                PQRS::CATEGORY_HARASSMENT_REPORT,
                PQRS::CATEGORY_FAKE_PROFILE,
                PQRS::CATEGORY_INAPPROPRIATE_BEHAVIOR
            ];
            
            $category = $this->faker->randomElement($safetyCategories);
            
            return [
                'type' => PQRS::TYPE_COMPLAINT,
                'category' => $category,
                'priority' => PQRS::PRIORITY_URGENT,
                'status' => $this->faker->randomElement([
                    PQRS::STATUS_SUBMITTED,
                    PQRS::STATUS_IN_REVIEW,
                    PQRS::STATUS_INVESTIGATING
                ]),
                'subject' => $this->generateSafetySubject($category),
                'description' => $this->generateSafetyDescription($category),
                'attachments' => [
                    $this->faker->imageUrl(800, 600, 'people') . '?evidence1',
                    $this->faker->optional(0.8)->imageUrl(800, 600, 'people') . '?evidence2',
                    $this->faker->optional(0.6)->imageUrl(800, 600, 'people') . '?evidence3'
                ],
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'safety_context' => [
                        'reported_user_id' => $this->faker->numberBetween(1000, 9999),
                        'incident_type' => $this->faker->randomElement([
                            'harassment', 'fake_profile', 'inappropriate_content',
                            'scam_attempt', 'underage_user', 'safety_threat'
                        ]),
                        'evidence_count' => $this->faker->numberBetween(1, 5),
                        'witness_available' => $this->faker->boolean(30),
                        'law_enforcement_contacted' => $this->faker->boolean(10)
                    ],
                    'risk_assessment' => [
                        'severity_level' => $this->faker->randomElement(['medium', 'high', 'critical']),
                        'immediate_action_required' => $this->faker->boolean(60),
                        'user_safety_risk' => $this->faker->randomElement(['low', 'medium', 'high']),
                        'platform_reputation_risk' => $this->faker->randomElement(['low', 'medium', 'high'])
                    ]
                ])
            ];
        });
    }

    /**
     * State for feature requests and suggestions.
     */
    public function featureRequest(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => PQRS::TYPE_SUGGESTION,
                'category' => PQRS::CATEGORY_FEATURE_REQUEST,
                'priority' => PQRS::PRIORITY_LOW,
                'status' => $this->faker->randomElement([
                    PQRS::STATUS_SUBMITTED,
                    PQRS::STATUS_RECEIVED,
                    PQRS::STATUS_IN_REVIEW
                ]),
                'subject' => $this->generateFeatureSubject(),
                'description' => $this->generateFeatureDescription(),
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'feature_details' => [
                        'feature_category' => $this->faker->randomElement([
                            'matching_algorithm', 'user_interface', 'messaging',
                            'profile_customization', 'search_filters', 'notifications'
                        ]),
                        'complexity_estimate' => $this->faker->randomElement(['low', 'medium', 'high']),
                        'user_impact' => $this->faker->randomElement(['small', 'medium', 'large']),
                        'business_value' => $this->faker->randomElement(['low', 'medium', 'high']),
                        'similar_requests' => $this->faker->numberBetween(0, 25)
                    ],
                    'product_roadmap' => [
                        'roadmap_consideration' => $this->faker->boolean(40),
                        'quarter_target' => $this->faker->optional(0.3)->randomElement(['Q1', 'Q2', 'Q3', 'Q4']),
                        'team_assignment' => $this->faker->optional(0.2)->randomElement(['mobile', 'web', 'backend', 'ai']),
                        'competitive_analysis_needed' => $this->faker->boolean(30)
                    ]
                ])
            ];
        });
    }

    /**
     * State for PQRS that are overdue (past SLA).
     */
    public function overdue(): static
    {
        return $this->state(function (array $attributes) {
            $priority = $this->faker->randomElement([
                PQRS::PRIORITY_MEDIUM,
                PQRS::PRIORITY_HIGH,
                PQRS::PRIORITY_URGENT
            ]);
            $slaHours = PQRS::SLA_HOURS[$priority];
            $submittedAt = $this->faker->dateTimeBetween('-1 week', "-{$slaHours} hours");
            
            return [
                'priority' => $priority,
                'status' => $this->faker->randomElement([
                    PQRS::STATUS_IN_REVIEW,
                    PQRS::STATUS_ASSIGNED,
                    PQRS::STATUS_IN_PROGRESS
                ]),
                'submitted_at' => $submittedAt,
                'sla_deadline' => $submittedAt->copy()->addHours($slaHours),
                'assigned_to' => User::factory(),
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'sla_breach' => [
                        'hours_overdue' => now()->diffInHours($submittedAt->copy()->addHours($slaHours)),
                        'breach_notifications_sent' => $this->faker->numberBetween(1, 5),
                        'escalation_triggered' => $this->faker->boolean(80),
                        'manager_notified' => $this->faker->boolean(90),
                        'customer_apology_sent' => $this->faker->boolean(60)
                    ]
                ])
            ];
        });
    }

    // Helper methods for generating realistic content
    private function generateSubject(string $type, string $category): string
    {
        $templates = [
            PQRS::TYPE_PETITION => [
                PQRS::CATEGORY_FEATURE_REQUEST => 'Request for new feature: %s',
                PQRS::CATEGORY_GENERAL_INQUIRY => 'Question about %s',
                'default' => 'Request regarding %s'
            ],
            PQRS::TYPE_COMPLAINT => [
                PQRS::CATEGORY_TECHNICAL_ISSUE => 'Technical problem with %s',
                PQRS::CATEGORY_BILLING_ISSUE => 'Billing issue: %s',
                PQRS::CATEGORY_PERFORMANCE_ISSUE => 'App performance problem',
                'default' => 'Issue with %s'
            ],
            PQRS::TYPE_CLAIM => [
                PQRS::CATEGORY_REFUND_REQUEST => 'Refund request for %s',
                PQRS::CATEGORY_ACCOUNT_SUSPENSION => 'Account suspension appeal',
                'default' => 'Claim regarding %s'
            ],
            PQRS::TYPE_SUGGESTION => [
                PQRS::CATEGORY_FEATURE_REQUEST => 'Suggestion: New %s feature',
                PQRS::CATEGORY_FEEDBACK => 'Feedback on %s',
                'default' => 'Suggestion for %s'
            ]
        ];

        $template = $templates[$type][$category] ?? $templates[$type]['default'] ?? 'Inquiry about %s';
        $subject = $this->faker->randomElement(['messaging system', 'user profiles', 'matching algorithm', 'app performance', 'subscription service']);
        
        return sprintf($template, $subject);
    }

    private function generateDescription(string $type, string $category): string
    {
        $descriptions = [
            PQRS::TYPE_COMPLAINT => [
                'I am experiencing issues with the application and need assistance resolving this problem.',
                'There seems to be a problem with the service that is affecting my user experience.',
                'I have encountered a technical difficulty that prevents me from using the app properly.'
            ],
            PQRS::TYPE_PETITION => [
                'I would like to request information about this feature or service.',
                'Could you please provide clarification on how this works?',
                'I am interested in learning more about this aspect of your service.'
            ],
            PQRS::TYPE_SUGGESTION => [
                'I have a suggestion that could improve the user experience.',
                'Here is an idea that might benefit other users as well.',
                'I believe this enhancement would add value to your platform.'
            ]
        ];

        return $this->faker->randomElement($descriptions[$type] ?? ['I need assistance with this matter.']);
    }

    private function generateUrgentSubject(string $category): string
    {
        return match($category) {
            PQRS::CATEGORY_ACCOUNT_SUSPENSION => 'URGENT: Account suspended without warning',
            PQRS::CATEGORY_PAYMENT_FAILED => 'URGENT: Payment failed but subscription cancelled',
            PQRS::CATEGORY_LOGIN_PROBLEMS => 'URGENT: Cannot access my account',
            PQRS::CATEGORY_SAFETY_CONCERN => 'URGENT: Safety concern requires immediate attention',
            default => 'URGENT: Immediate assistance required'
        };
    }

    private function generateUrgentDescription(string $category): string
    {
        return match($category) {
            PQRS::CATEGORY_ACCOUNT_SUSPENSION => 'My account was suspended without any explanation or warning. I need immediate assistance to resolve this issue as I have an active subscription.',
            PQRS::CATEGORY_PAYMENT_FAILED => 'My payment failed but my subscription was cancelled anyway. I need this resolved immediately as I depend on this service.',
            PQRS::CATEGORY_LOGIN_PROBLEMS => 'I cannot access my account despite multiple attempts. This is preventing me from using your service.',
            PQRS::CATEGORY_SAFETY_CONCERN => 'I have a serious safety concern that requires immediate attention from your moderation team.',
            default => 'This is an urgent matter that requires immediate attention and resolution.'
        };
    }

    private function generateTechnicalSubject(string $category): string
    {
        return match($category) {
            PQRS::CATEGORY_TECHNICAL_ISSUE => 'Technical Issue: ' . $this->faker->randomElement(['App crashes on startup', 'Features not working', 'Data sync problems']),
            PQRS::CATEGORY_APP_BUG => 'Bug Report: ' . $this->faker->randomElement(['UI display issue', 'Function not responding', 'Incorrect behavior']),
            PQRS::CATEGORY_PERFORMANCE_ISSUE => 'Performance Issue: ' . $this->faker->randomElement(['Slow loading times', 'App freezing', 'High memory usage']),
            PQRS::CATEGORY_LOGIN_PROBLEMS => 'Login Problem: ' . $this->faker->randomElement(['Cannot sign in', 'Password reset not working', 'Two-factor auth issues']),
            default => 'Technical support needed'
        };
    }

    private function generateTechnicalDescription(string $category): string
    {
        return match($category) {
            PQRS::CATEGORY_TECHNICAL_ISSUE => 'I am experiencing technical difficulties that prevent normal use of the application. The issue occurs consistently and affects core functionality.',
            PQRS::CATEGORY_APP_BUG => 'I have discovered what appears to be a bug in the application. The behavior is not working as expected and may affect other users as well.',
            PQRS::CATEGORY_PERFORMANCE_ISSUE => 'The app is performing poorly with slow response times and occasional freezing. This significantly impacts the user experience.',
            PQRS::CATEGORY_LOGIN_PROBLEMS => 'I am unable to log into my account despite using the correct credentials. This is preventing me from accessing your service.',
            default => 'I need technical assistance to resolve this issue.'
        };
    }

    private function generateBillingSubject(string $category): string
    {
        return match($category) {
            PQRS::CATEGORY_BILLING_ISSUE => 'Billing Issue: ' . $this->faker->randomElement(['Incorrect charge', 'Duplicate billing', 'Wrong amount charged']),
            PQRS::CATEGORY_REFUND_REQUEST => 'Refund Request: ' . $this->faker->randomElement(['Subscription cancellation', 'Service not delivered', 'Unsatisfied with service']),
            PQRS::CATEGORY_SUBSCRIPTION_PROBLEM => 'Subscription Problem: ' . $this->faker->randomElement(['Auto-renewal issue', 'Plan change problem', 'Billing cycle confusion']),
            PQRS::CATEGORY_PAYMENT_FAILED => 'Payment Failed: ' . $this->faker->randomElement(['Card declined', 'Payment processing error', 'Billing address issue']),
            default => 'Billing inquiry'
        };
    }

    private function generateBillingDescription(string $category): string
    {
        return match($category) {
            PQRS::CATEGORY_BILLING_ISSUE => 'I have noticed an issue with my billing that needs to be resolved. The charge does not match what I expected based on my subscription.',
            PQRS::CATEGORY_REFUND_REQUEST => 'I would like to request a refund for my recent purchase. I believe I am eligible for a refund based on your terms of service.',
            PQRS::CATEGORY_SUBSCRIPTION_PROBLEM => 'I am having problems with my subscription billing. The charges do not match my selected plan or billing cycle.',
            PQRS::CATEGORY_PAYMENT_FAILED => 'My payment failed to process but I believe the issue may be on your end. Please help me resolve this payment issue.',
            default => 'I need assistance with a billing matter.'
        };
    }

    private function generateSafetySubject(string $category): string
    {
        return match($category) {
            PQRS::CATEGORY_SAFETY_CONCERN => 'Safety Concern: ' . $this->faker->randomElement(['Threatening behavior', 'Inappropriate contact', 'Suspicious activity']),
            PQRS::CATEGORY_HARASSMENT_REPORT => 'Harassment Report: ' . $this->faker->randomElement(['Unwanted messages', 'Abusive behavior', 'Stalking concerns']),
            PQRS::CATEGORY_FAKE_PROFILE => 'Fake Profile Report: ' . $this->faker->randomElement(['Stolen photos', 'False information', 'Catfish profile']),
            PQRS::CATEGORY_INAPPROPRIATE_BEHAVIOR => 'Inappropriate Behavior: ' . $this->faker->randomElement(['Offensive messages', 'Inappropriate content', 'Policy violation']),
            default => 'Safety concern requiring attention'
        };
    }

    private function generateSafetyDescription(string $category): string
    {
        return match($category) {
            PQRS::CATEGORY_SAFETY_CONCERN => 'I have a serious safety concern about another user\'s behavior that requires immediate attention from your moderation team.',
            PQRS::CATEGORY_HARASSMENT_REPORT => 'I am being harassed by another user and need this behavior to be addressed immediately for my safety and peace of mind.',
            PQRS::CATEGORY_FAKE_PROFILE => 'I have identified what appears to be a fake profile using stolen photos or false information. This needs to be investigated.',
            PQRS::CATEGORY_INAPPROPRIATE_BEHAVIOR => 'Another user is displaying inappropriate behavior that violates your community guidelines and needs to be addressed.',
            default => 'This safety issue requires prompt attention from your moderation team.'
        };
    }

    private function generateFeatureSubject(): string
    {
        return 'Feature Request: ' . $this->faker->randomElement([
            'Enhanced search filters',
            'Better matching algorithm',
            'Video chat integration',
            'Profile verification system',
            'Advanced messaging features',
            'Improved notification system'
        ]);
    }

    private function generateFeatureDescription(): string
    {
        return 'I have a suggestion for a new feature that I believe would improve the user experience and add value to your platform. This enhancement would benefit many users and align with current market trends.';
    }
}