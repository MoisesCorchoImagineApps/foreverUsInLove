<?php

namespace Database\Factories\Domains\Notification;

use App\Domains\Notification\Models\EmailNotification;
use App\Domains\Notification\Models\Notification;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Notification\Models\EmailNotification>
 */
class EmailNotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EmailNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $emailType = $this->faker->randomElement([
            // Transactional
            EmailNotification::TYPE_WELCOME,
            EmailNotification::TYPE_EMAIL_VERIFICATION,
            EmailNotification::TYPE_PASSWORD_RESET,
            EmailNotification::TYPE_SECURITY_ALERT,
            
            // Dating Activity
            EmailNotification::TYPE_NEW_MATCH_EMAIL,
            EmailNotification::TYPE_DAILY_MATCHES,
            EmailNotification::TYPE_WEEKLY_DIGEST,
            EmailNotification::TYPE_MESSAGES_WAITING,
            
            // Engagement
            EmailNotification::TYPE_COMEBACK_OFFER,
            EmailNotification::TYPE_SPECIAL_PROMOTION,
            EmailNotification::TYPE_PREMIUM_FEATURES,
            
            // Commerce
            EmailNotification::TYPE_SUBSCRIPTION_EXPIRING,
            EmailNotification::TYPE_PAYMENT_SUCCESS,
            EmailNotification::TYPE_PAYMENT_FAILED,
            
            // Updates
            EmailNotification::TYPE_FEATURE_ANNOUNCEMENT,
            EmailNotification::TYPE_NEWSLETTER
        ]);

        $category = $this->getEmailCategory($emailType);
        $priority = $this->getEmailPriority($emailType, $category);
        $provider = $this->getProviderForCategory($category);
        $status = $this->faker->weightedElement([
            EmailNotification::STATUS_SENT => 40,
            EmailNotification::STATUS_DELIVERED => 25,
            EmailNotification::STATUS_OPENED => 20,
            EmailNotification::STATUS_CLICKED => 10,
            EmailNotification::STATUS_FAILED => 3,
            EmailNotification::STATUS_BOUNCED => 2
        ]);

        $createdAt = $this->faker->dateTimeBetween('-6 months', 'now');
        
        // Status-dependent timestamps
        $sentAt = $status !== EmailNotification::STATUS_QUEUED ? 
            $this->faker->dateTimeBetween($createdAt, 'now') : null;
        $deliveredAt = in_array($status, [
            EmailNotification::STATUS_DELIVERED, 
            EmailNotification::STATUS_OPENED, 
            EmailNotification::STATUS_CLICKED
        ]) ? $this->faker->dateTimeBetween($sentAt ?? $createdAt, 'now') : null;
        $openedAt = in_array($status, [
            EmailNotification::STATUS_OPENED, 
            EmailNotification::STATUS_CLICKED
        ]) ? $this->faker->dateTimeBetween($deliveredAt ?? $createdAt, 'now') : null;
        $clickedAt = $status === EmailNotification::STATUS_CLICKED ? 
            $this->faker->dateTimeBetween($openedAt ?? $createdAt, 'now') : null;

        // Bounce/unsubscribe timestamps
        $bouncedAt = $status === EmailNotification::STATUS_BOUNCED ? 
            $this->faker->dateTimeBetween($sentAt ?? $createdAt, 'now') : null;
        $unsubscribedAt = $this->faker->boolean(5) ? 
            $this->faker->dateTimeBetween($openedAt ?? $createdAt, 'now') : null;

        $subject = $this->generateSubject($emailType);
        $fromEmail = $this->getFromEmail($emailType, $category);
        $fromName = $this->getFromName($emailType, $category);

        return [
            'email_id' => $this->faker->uuid(),
            'notification_id' => Notification::factory(),
            'user_id' => User::factory(),
            'email_type' => $emailType,
            'provider' => $provider,
            'category' => $category,
            'priority' => $priority,
            'status' => $status,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'to_email' => $this->faker->safeEmail(),
            'to_name' => $this->faker->name(),
            'cc_emails' => $this->faker->boolean(15) ? $this->faker->randomElements([
                $this->faker->safeEmail(),
                $this->faker->safeEmail()
            ], $this->faker->numberBetween(1, 2)) : null,
            'bcc_emails' => $this->faker->boolean(5) ? [
                'analytics@foreverusinlove.com'
            ] : null,
            'subject' => $subject,
            'preheader' => $this->generatePreheader($emailType, $subject),
            'html_body' => $this->generateHtmlBody($emailType, $subject),
            'text_body' => $this->generateTextBody($emailType),
            'template_id' => $this->faker->optional(0.7)->regexify('template_[0-9]{4}'),
            'campaign_id' => $category === EmailNotification::CATEGORY_MARKETING ? 
                'campaign_' . $this->faker->numberBetween(100000, 999999) : null,
            'ab_test_variant' => $category === EmailNotification::CATEGORY_MARKETING ? 
                $this->faker->randomElement(['A', 'B', 'control']) : null,
            'attachments' => $this->generateAttachments($emailType),
            'tags' => $this->generateTags($emailType, $category),
            'metadata' => $this->generateMetadata($emailType, $category, $provider),
            'sent_at' => $sentAt,
            'delivered_at' => $deliveredAt,
            'opened_at' => $openedAt,
            'clicked_at' => $clickedAt,
            'bounced_at' => $bouncedAt,
            'unsubscribed_at' => $unsubscribedAt,
            'open_count' => $openedAt ? $this->faker->numberBetween(1, 5) : 0,
            'click_count' => $clickedAt ? $this->faker->numberBetween(1, 3) : 0,
            'provider_message_id' => $sentAt ? $this->faker->regexify('msg_[A-Z0-9]{16}') : null,
            'bounce_reason' => $status === EmailNotification::STATUS_BOUNCED ? 
                $this->faker->randomElement([
                    'Mailbox does not exist',
                    'Domain not found',
                    'Message too large',
                    'Spam detected',
                    'Recipient blocked sender'
                ]) : null,
            'created_at' => $createdAt,
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    /**
     * Get email category based on type
     */
    private function getEmailCategory(string $emailType): string
    {
        $categoryMap = [
            // Transactional
            EmailNotification::TYPE_WELCOME => EmailNotification::CATEGORY_TRANSACTIONAL,
            EmailNotification::TYPE_EMAIL_VERIFICATION => EmailNotification::CATEGORY_TRANSACTIONAL,
            EmailNotification::TYPE_PASSWORD_RESET => EmailNotification::CATEGORY_TRANSACTIONAL,
            EmailNotification::TYPE_SECURITY_ALERT => EmailNotification::CATEGORY_TRANSACTIONAL,
            EmailNotification::TYPE_PAYMENT_SUCCESS => EmailNotification::CATEGORY_TRANSACTIONAL,
            EmailNotification::TYPE_PAYMENT_FAILED => EmailNotification::CATEGORY_TRANSACTIONAL,
            
            // Promotional
            EmailNotification::TYPE_COMEBACK_OFFER => EmailNotification::CATEGORY_PROMOTIONAL,
            EmailNotification::TYPE_SPECIAL_PROMOTION => EmailNotification::CATEGORY_PROMOTIONAL,
            EmailNotification::TYPE_PREMIUM_FEATURES => EmailNotification::CATEGORY_PROMOTIONAL,
            
            // Newsletter
            EmailNotification::TYPE_NEWSLETTER => EmailNotification::CATEGORY_NEWSLETTER,
            EmailNotification::TYPE_WEEKLY_DIGEST => EmailNotification::CATEGORY_NEWSLETTER,
            
            // Marketing
            EmailNotification::TYPE_DAILY_MATCHES => EmailNotification::CATEGORY_MARKETING,
            EmailNotification::TYPE_RE_ENGAGEMENT => EmailNotification::CATEGORY_MARKETING,
            
            // Notification
            EmailNotification::TYPE_NEW_MATCH_EMAIL => EmailNotification::CATEGORY_NOTIFICATION,
            EmailNotification::TYPE_MESSAGES_WAITING => EmailNotification::CATEGORY_NOTIFICATION,
            
            // System
            EmailNotification::TYPE_FEATURE_ANNOUNCEMENT => EmailNotification::CATEGORY_SYSTEM,
        ];

        return $categoryMap[$emailType] ?? EmailNotification::CATEGORY_NOTIFICATION;
    }

    /**
     * Get priority based on email type and category
     */
    private function getEmailPriority(string $emailType, string $category): string
    {
        if ($category === EmailNotification::CATEGORY_TRANSACTIONAL) {
            return match($emailType) {
                EmailNotification::TYPE_SECURITY_ALERT => EmailNotification::PRIORITY_URGENT,
                EmailNotification::TYPE_PASSWORD_RESET => EmailNotification::PRIORITY_HIGH,
                EmailNotification::TYPE_EMAIL_VERIFICATION => EmailNotification::PRIORITY_HIGH,
                EmailNotification::TYPE_PAYMENT_FAILED => EmailNotification::PRIORITY_HIGH,
                default => EmailNotification::PRIORITY_NORMAL
            };
        }

        if ($category === EmailNotification::CATEGORY_PROMOTIONAL) {
            return EmailNotification::PRIORITY_LOW;
        }

        return EmailNotification::PRIORITY_NORMAL;
    }

    /**
     * Get provider based on category
     */
    private function getProviderForCategory(string $category): string
    {
        return match($category) {
            EmailNotification::CATEGORY_TRANSACTIONAL => $this->faker->randomElement([
                EmailNotification::PROVIDER_POSTMARK,
                EmailNotification::PROVIDER_SENDGRID,
                EmailNotification::PROVIDER_SES
            ]),
            EmailNotification::CATEGORY_MARKETING,
            EmailNotification::CATEGORY_PROMOTIONAL => $this->faker->randomElement([
                EmailNotification::PROVIDER_SENDGRID,
                EmailNotification::PROVIDER_MAILCHIMP,
                EmailNotification::PROVIDER_MAILGUN
            ]),
            default => $this->faker->randomElement(EmailNotification::PROVIDERS)
        };
    }

    /**
     * Get from email based on type and category
     */
    private function getFromEmail(string $emailType, string $category): string
    {
        return match($category) {
            EmailNotification::CATEGORY_TRANSACTIONAL => match($emailType) {
                EmailNotification::TYPE_SECURITY_ALERT => 'security@foreverusinlove.com',
                EmailNotification::TYPE_PASSWORD_RESET => 'auth@foreverusinlove.com',
                EmailNotification::TYPE_EMAIL_VERIFICATION => 'verify@foreverusinlove.com',
                EmailNotification::TYPE_PAYMENT_SUCCESS,
                EmailNotification::TYPE_PAYMENT_FAILED => 'billing@foreverusinlove.com',
                default => 'noreply@foreverusinlove.com'
            },
            EmailNotification::CATEGORY_MARKETING,
            EmailNotification::CATEGORY_PROMOTIONAL => 'hello@foreverusinlove.com',
            EmailNotification::CATEGORY_NEWSLETTER => 'newsletter@foreverusinlove.com',
            EmailNotification::CATEGORY_SYSTEM => 'updates@foreverusinlove.com',
            default => 'notifications@foreverusinlove.com'
        };
    }

    /**
     * Get from name based on type and category
     */
    private function getFromName(string $emailType, string $category): string
    {
        return match($category) {
            EmailNotification::CATEGORY_TRANSACTIONAL => match($emailType) {
                EmailNotification::TYPE_SECURITY_ALERT => 'ForeverUsInLove Security Team',
                EmailNotification::TYPE_PASSWORD_RESET => 'ForeverUsInLove Account Support',
                EmailNotification::TYPE_PAYMENT_SUCCESS,
                EmailNotification::TYPE_PAYMENT_FAILED => 'ForeverUsInLove Billing',
                default => 'ForeverUsInLove'
            },
            EmailNotification::CATEGORY_MARKETING,
            EmailNotification::CATEGORY_PROMOTIONAL => 'ForeverUsInLove Team',
            EmailNotification::CATEGORY_NEWSLETTER => 'ForeverUsInLove Newsletter',
            default => 'ForeverUsInLove'
        };
    }

    /**
     * Generate email subject based on type
     */
    private function generateSubject(string $emailType): string
    {
        $subjects = [
            EmailNotification::TYPE_WELCOME => [
                'Welcome to ForeverUsInLove! 💕',
                'Your love journey starts here',
                'Welcome to your new adventure in love',
                'Ready to find your perfect match?'
            ],
            EmailNotification::TYPE_NEW_MATCH_EMAIL => [
                'You have a new match! 💖',
                'Someone special matched with you',
                'New connection waiting for you',
                'It\'s a match! Say hello 👋'
            ],
            EmailNotification::TYPE_DAILY_MATCHES => [
                'Your daily matches are ready! 💕',
                'Fresh faces waiting to meet you',
                'Today\'s perfect matches inside',
                'New potential connections today'
            ],
            EmailNotification::TYPE_WEEKLY_DIGEST => [
                'Your week in love - matches, messages & more',
                'Weekly recap: Your dating highlights',
                'This week\'s activity summary',
                'See who\'s been checking you out this week'
            ],
            EmailNotification::TYPE_MESSAGES_WAITING => [
                'You have unread messages 💬',
                'Someone is waiting for your reply',
                'New messages from your matches',
                'Don\'t keep them waiting!'
            ],
            EmailNotification::TYPE_COMEBACK_OFFER => [
                'We miss you! Special offer inside 💕',
                'Come back and find love - 50% off',
                'Your matches are waiting for you',
                'Special comeback offer just for you'
            ],
            EmailNotification::TYPE_PREMIUM_FEATURES => [
                'Unlock premium features and find love faster',
                'See who likes you - upgrade today',
                'Premium benefits waiting for you',
                'Take your dating to the next level'
            ],
            EmailNotification::TYPE_SUBSCRIPTION_EXPIRING => [
                'Your premium subscription expires soon',
                'Don\'t lose your premium benefits',
                'Renew now to keep your advantages',
                'Your subscription expires in 3 days'
            ],
            EmailNotification::TYPE_PAYMENT_SUCCESS => [
                'Payment confirmed - Welcome to Premium! 🎉',
                'Your subscription is now active',
                'Payment successful - Enjoy premium features',
                'Welcome to premium dating'
            ],
            EmailNotification::TYPE_PAYMENT_FAILED => [
                'Payment failed - Update your payment method',
                'We couldn\'t process your payment',
                'Action required: Payment issue',
                'Update payment to continue premium'
            ],
            EmailNotification::TYPE_SECURITY_ALERT => [
                'Security alert: Unusual activity detected',
                'Important: Your account security',
                'Immediate action required for your account',
                'Security notification'
            ],
            EmailNotification::TYPE_PASSWORD_RESET => [
                'Reset your ForeverUsInLove password',
                'Password reset request',
                'Complete your password reset',
                'Your password reset link'
            ],
            EmailNotification::TYPE_EMAIL_VERIFICATION => [
                'Verify your email address',
                'Complete your registration',
                'One more step to join ForeverUsInLove',
                'Please verify your email'
            ]
        ];

        return $this->faker->randomElement(
            $subjects[$emailType] ?? ['Notification from ForeverUsInLove']
        );
    }

    /**
     * Generate preheader text
     */
    private function generatePreheader(string $emailType, string $subject): string
    {
        $preheaders = [
            EmailNotification::TYPE_WELCOME => [
                'Start swiping and find your perfect match today',
                'Complete your profile and start connecting',
                'Your love story begins now'
            ],
            EmailNotification::TYPE_NEW_MATCH_EMAIL => [
                'You both liked each other - start chatting now',
                'Break the ice with your new match',
                'Someone special is waiting to hear from you'
            ],
            EmailNotification::TYPE_DAILY_MATCHES => [
                'We found some great potential matches for you',
                'Based on your preferences, you\'ll love these profiles',
                'Fresh faces and new possibilities await'
            ],
            EmailNotification::TYPE_PAYMENT_FAILED => [
                'Update your payment method to continue using premium features',
                'Your subscription may be interrupted',
                'Secure your premium benefits now'
            ]
        ];

        return $this->faker->randomElement(
            $preheaders[$emailType] ?? [
                'Important update from ForeverUsInLove',
                'Don\'t miss out on this update',
                'Your dating app notification'
            ]
        );
    }

    /**
     * Generate HTML body content
     */
    private function generateHtmlBody(string $emailType, string $subject): string
    {
        $userName = $this->faker->firstName();
        
        $templates = [
            EmailNotification::TYPE_WELCOME => "
                <html><body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; text-align: center; border-radius: 10px 10px 0 0;'>
                        <h1 style='color: white; margin: 0;'>Welcome to ForeverUsInLove! 💕</h1>
                    </div>
                    <div style='padding: 30px; background: white;'>
                        <h2>Hi {$userName}!</h2>
                        <p>We're thrilled to have you join our community of love-seekers. Your journey to finding that special someone starts here!</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='#' style='background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; display: inline-block;'>Complete Your Profile</a>
                        </div>
                        <p>Complete your profile to start receiving high-quality matches tailored just for you.</p>
                    </div>
                </body></html>
            ",
            EmailNotification::TYPE_NEW_MATCH_EMAIL => "
                <html><body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #ff6b9d; padding: 40px; text-align: center; color: white;'>
                        <h1>It's a Match! 💖</h1>
                        <p>You and someone special have liked each other</p>
                    </div>
                    <div style='padding: 30px; background: white;'>
                        <h2>Hi {$userName}!</h2>
                        <p>Great news! You have a new match waiting for you. Why not break the ice with a friendly message?</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='#' style='background: #ff6b9d; color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px;'>Say Hello</a>
                        </div>
                    </div>
                </body></html>
            ",
            EmailNotification::TYPE_PAYMENT_FAILED => "
                <html><body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: #ff4757; padding: 40px; text-align: center; color: white;'>
                        <h1>Payment Issue</h1>
                        <p>We couldn't process your payment</p>
                    </div>
                    <div style='padding: 30px; background: white;'>
                        <h2>Hi {$userName},</h2>
                        <p>We had trouble processing your payment for your premium subscription. Please update your payment method to continue enjoying premium features.</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='#' style='background: #ff4757; color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px;'>Update Payment</a>
                        </div>
                    </div>
                </body></html>
            "
        ];

        return $templates[$emailType] ?? "
            <html><body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='padding: 40px; background: #f8f9fa;'>
                    <h1 style='color: #333;'>{$subject}</h1>
                    <p>Hi {$userName},</p>
                    <p>{$this->faker->paragraph()}</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='#' style='background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px;'>View Details</a>
                    </div>
                </div>
            </body></html>
        ";
    }

    /**
     * Generate plain text body
     */
    private function generateTextBody(string $emailType): string
    {
        $userName = $this->faker->firstName();
        
        $templates = [
            EmailNotification::TYPE_WELCOME => "
                Hi {$userName}!
                
                Welcome to ForeverUsInLove! We're thrilled to have you join our community.
                
                Your journey to finding that special someone starts here. Complete your profile to start receiving high-quality matches tailored just for you.
                
                Complete Your Profile: [link]
                
                Best regards,
                The ForeverUsInLove Team
            ",
            EmailNotification::TYPE_NEW_MATCH_EMAIL => "
                Hi {$userName}!
                
                It's a Match! 💖
                
                You and someone special have liked each other. Why not break the ice with a friendly message?
                
                Say Hello: [link]
                
                Happy dating!
                ForeverUsInLove
            "
        ];

        return trim($templates[$emailType] ?? "
            Hi {$userName},
            
            {$this->faker->paragraph()}
            
            View Details: [link]
            
            Best regards,
            ForeverUsInLove Team
        ");
    }

    /**
     * Generate attachments based on email type
     */
    private function generateAttachments(string $emailType): ?array
    {
        if (!$this->faker->boolean(10)) {
            return null;
        }

        $attachments = [];

        if (in_array($emailType, [
            EmailNotification::TYPE_PAYMENT_SUCCESS,
            EmailNotification::TYPE_SUBSCRIPTION_EXPIRING
        ])) {
            $attachments[] = [
                'name' => 'invoice_' . $this->faker->numberBetween(100000, 999999) . '.pdf',
                'size' => $this->faker->numberBetween(50000, 200000), // bytes
                'type' => 'application/pdf',
                'url' => $this->faker->url() . '/invoice.pdf'
            ];
        }

        if ($emailType === EmailNotification::TYPE_NEWSLETTER) {
            $attachments[] = [
                'name' => 'dating_tips.pdf',
                'size' => $this->faker->numberBetween(100000, 500000),
                'type' => 'application/pdf',
                'url' => $this->faker->url() . '/dating_tips.pdf'
            ];
        }

        return empty($attachments) ? null : $attachments;
    }

    /**
     * Generate email tags
     */
    private function generateTags(string $emailType, string $category): array
    {
        $baseTags = [$category, $emailType];
        
        $additionalTags = [];
        
        if ($category === EmailNotification::CATEGORY_MARKETING) {
            $additionalTags = ['campaign', 'engagement'];
        }
        
        if ($category === EmailNotification::CATEGORY_TRANSACTIONAL) {
            $additionalTags = ['important', 'account'];
        }
        
        if (in_array($emailType, [
            EmailNotification::TYPE_NEW_MATCH_EMAIL,
            EmailNotification::TYPE_DAILY_MATCHES
        ])) {
            $additionalTags = ['dating', 'matches'];
        }

        return array_merge($baseTags, $additionalTags);
    }

    /**
     * Generate metadata
     */
    private function generateMetadata(string $emailType, string $category, string $provider): array
    {
        return [
            'template_version' => $this->faker->semver(),
            'render_time_ms' => $this->faker->numberBetween(50, 300),
            'provider_config' => [
                'api_version' => $this->faker->randomElement(['v1', 'v2', 'v3']),
                'region' => $this->faker->randomElement(['us-east-1', 'eu-west-1', 'ap-south-1']),
                'retry_attempts' => $this->faker->numberBetween(0, 3)
            ],
            'personalization' => [
                'user_name' => $this->faker->firstName(),
                'user_age' => $this->faker->numberBetween(18, 65),
                'user_location' => $this->faker->city(),
                'premium_user' => $this->faker->boolean(25),
                'last_login' => $this->faker->dateTimeThisMonth()->format('Y-m-d H:i:s')
            ],
            'content_analysis' => [
                'word_count' => $this->faker->numberBetween(50, 500),
                'reading_time_seconds' => $this->faker->numberBetween(30, 180),
                'links_count' => $this->faker->numberBetween(1, 8),
                'images_count' => $this->faker->numberBetween(0, 5)
            ],
            'deliverability' => [
                'spam_score' => $this->faker->randomFloat(2, 0, 10),
                'authentication' => [
                    'spf' => $this->faker->boolean(95),
                    'dkim' => $this->faker->boolean(90),
                    'dmarc' => $this->faker->boolean(85)
                ]
            ],
            'tracking' => [
                'utm_source' => 'email',
                'utm_medium' => $category,
                'utm_campaign' => $emailType,
                'utm_content' => $this->faker->optional()->slug()
            ]
        ];
    }

    /**
     * Transactional emails (high priority, system critical)
     */
    public function transactional(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_type' => $this->faker->randomElement([
                EmailNotification::TYPE_WELCOME,
                EmailNotification::TYPE_EMAIL_VERIFICATION,
                EmailNotification::TYPE_PASSWORD_RESET,
                EmailNotification::TYPE_SECURITY_ALERT,
                EmailNotification::TYPE_PAYMENT_SUCCESS,
                EmailNotification::TYPE_PAYMENT_FAILED
            ]),
            'category' => EmailNotification::CATEGORY_TRANSACTIONAL,
            'priority' => $this->faker->weightedElement([
                EmailNotification::PRIORITY_HIGH => 60,
                EmailNotification::PRIORITY_URGENT => 30,
                EmailNotification::PRIORITY_NORMAL => 10
            ]),
            'provider' => $this->faker->randomElement([
                EmailNotification::PROVIDER_POSTMARK,
                EmailNotification::PROVIDER_SENDGRID,
                EmailNotification::PROVIDER_SES
            ]),
            'status' => $this->faker->weightedElement([
                EmailNotification::STATUS_DELIVERED => 70,
                EmailNotification::STATUS_OPENED => 20,
                EmailNotification::STATUS_SENT => 8,
                EmailNotification::STATUS_FAILED => 2
            ])
        ]);
    }

    /**
     * Marketing emails (campaigns, promotions)
     */
    public function marketing(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_type' => $this->faker->randomElement([
                EmailNotification::TYPE_DAILY_MATCHES,
                EmailNotification::TYPE_WEEKLY_DIGEST,
                EmailNotification::TYPE_COMEBACK_OFFER,
                EmailNotification::TYPE_SPECIAL_PROMOTION,
                EmailNotification::TYPE_PREMIUM_FEATURES
            ]),
            'category' => $this->faker->randomElement([
                EmailNotification::CATEGORY_MARKETING,
                EmailNotification::CATEGORY_PROMOTIONAL
            ]),
            'priority' => $this->faker->weightedElement([
                EmailNotification::PRIORITY_LOW => 50,
                EmailNotification::PRIORITY_NORMAL => 40,
                EmailNotification::PRIORITY_HIGH => 10
            ]),
            'provider' => $this->faker->randomElement([
                EmailNotification::PROVIDER_SENDGRID,
                EmailNotification::PROVIDER_MAILCHIMP,
                EmailNotification::PROVIDER_MAILGUN
            ]),
            'campaign_id' => 'campaign_' . $this->faker->numberBetween(100000, 999999),
            'ab_test_variant' => $this->faker->randomElement(['A', 'B', 'control']),
            'template_id' => 'template_' . $this->faker->numberBetween(1000, 9999)
        ]);
    }

    /**
     * High engagement emails (opened and clicked)
     */
    public function highEngagement(): static
    {
        $openedAt = $this->faker->dateTimeBetween('-1 month', '-1 day');
        $clickedAt = $this->faker->dateTimeBetween($openedAt, 'now');
        
        return $this->state(fn (array $attributes) => [
            'status' => EmailNotification::STATUS_CLICKED,
            'opened_at' => $openedAt,
            'clicked_at' => $clickedAt,
            'open_count' => $this->faker->numberBetween(2, 8),
            'click_count' => $this->faker->numberBetween(1, 4)
        ]);
    }

    /**
     * Bounced emails
     */
    public function bounced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmailNotification::STATUS_BOUNCED,
            'bounced_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'bounce_reason' => $this->faker->randomElement([
                'Mailbox does not exist',
                'Domain not found',
                'Message too large',
                'Spam detected',
                'Recipient blocked sender',
                'Mailbox full',
                'Server temporarily unavailable'
            ]),
            'delivered_at' => null,
            'opened_at' => null,
            'clicked_at' => null
        ]);
    }

    /**
     * Failed emails
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmailNotification::STATUS_FAILED,
            'sent_at' => null,
            'delivered_at' => null,
            'opened_at' => null,
            'clicked_at' => null,
            'bounce_reason' => $this->faker->randomElement([
                'Invalid API key',
                'Rate limit exceeded',
                'Service unavailable',
                'Invalid email format',
                'Blacklisted domain'
            ])
        ]);
    }

    /**
     * Newsletter emails
     */
    public function newsletter(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_type' => $this->faker->randomElement([
                EmailNotification::TYPE_NEWSLETTER,
                EmailNotification::TYPE_WEEKLY_DIGEST,
                EmailNotification::TYPE_FEATURE_ANNOUNCEMENT
            ]),
            'category' => EmailNotification::CATEGORY_NEWSLETTER,
            'priority' => EmailNotification::PRIORITY_LOW,
            'provider' => EmailNotification::PROVIDER_MAILCHIMP,
            'campaign_id' => 'newsletter_' . $this->faker->numberBetween(100000, 999999)
        ]);
    }

    /**
     * Welcome series emails
     */
    public function welcomeSeries(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_type' => EmailNotification::TYPE_WELCOME,
            'category' => EmailNotification::CATEGORY_TRANSACTIONAL,
            'priority' => EmailNotification::PRIORITY_HIGH,
            'status' => $this->faker->weightedElement([
                EmailNotification::STATUS_DELIVERED => 80,
                EmailNotification::STATUS_OPENED => 15,
                EmailNotification::STATUS_CLICKED => 5
            ]),
            'template_id' => 'welcome_' . $this->faker->numberBetween(1, 5), // Welcome series steps
            'campaign_id' => 'onboarding_' . date('Y_m')
        ]);
    }

    /**
     * Payment related emails
     */
    public function payment(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_type' => $this->faker->randomElement([
                EmailNotification::TYPE_PAYMENT_SUCCESS,
                EmailNotification::TYPE_PAYMENT_FAILED,
                EmailNotification::TYPE_SUBSCRIPTION_EXPIRING,
                EmailNotification::TYPE_REFUND_PROCESSED
            ]),
            'category' => EmailNotification::CATEGORY_TRANSACTIONAL,
            'priority' => EmailNotification::PRIORITY_HIGH,
            'from_email' => 'billing@foreverusinlove.com',
            'from_name' => 'ForeverUsInLove Billing',
            'attachments' => [
                [
                    'name' => 'invoice_' . $this->faker->numberBetween(100000, 999999) . '.pdf',
                    'size' => $this->faker->numberBetween(80000, 150000),
                    'type' => 'application/pdf',
                    'url' => $this->faker->url() . '/invoice.pdf'
                ]
            ]
        ]);
    }

    /**
     * A/B test emails
     */
    public function abTest(): static
    {
        return $this->state(fn (array $attributes) => [
            'campaign_id' => 'ab_test_' . $this->faker->numberBetween(1000, 9999),
            'ab_test_variant' => $this->faker->randomElement(['A', 'B', 'C']),
            'category' => EmailNotification::CATEGORY_MARKETING,
            'template_id' => 'ab_template_' . $this->faker->numberBetween(100, 999)
        ]);
    }

    /**
     * High priority urgent emails
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => EmailNotification::PRIORITY_URGENT,
            'email_type' => $this->faker->randomElement([
                EmailNotification::TYPE_SECURITY_ALERT,
                EmailNotification::TYPE_PAYMENT_FAILED,
                EmailNotification::TYPE_ACCOUNT_SUSPENSION
            ]),
            'category' => EmailNotification::CATEGORY_TRANSACTIONAL,
            'provider' => EmailNotification::PROVIDER_POSTMARK, // Most reliable for urgent
            'status' => $this->faker->weightedElement([
                EmailNotification::STATUS_DELIVERED => 90,
                EmailNotification::STATUS_OPENED => 8,
                EmailNotification::STATUS_SENT => 2
            ])
        ]);
    }
}