<?php

namespace Database\Factories\Domains\Notification;

use App\Domains\Notification\Models\Notification;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Notification\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([
            // Dating Activity
            Notification::TYPE_NEW_MATCH, Notification::TYPE_MATCH_LIKED_YOU,
            Notification::TYPE_MATCH_MESSAGE, Notification::TYPE_SUPER_LIKE_RECEIVED,
            Notification::TYPE_PROFILE_VISIT, Notification::TYPE_PHOTO_LIKED,
            
            // Messaging
            Notification::TYPE_NEW_MESSAGE, Notification::TYPE_VOICE_MESSAGE,
            Notification::TYPE_VIDEO_CALL_INVITE, Notification::TYPE_MISSED_CALL,
            
            // System
            Notification::TYPE_PROFILE_APPROVED, Notification::TYPE_VERIFICATION_COMPLETE,
            Notification::TYPE_ACCOUNT_WARNING, Notification::TYPE_PASSWORD_CHANGED,
            
            // Commerce
            Notification::TYPE_SUBSCRIPTION_EXPIRING, Notification::TYPE_PAYMENT_SUCCESS,
            Notification::TYPE_PAYMENT_FAILED, Notification::TYPE_COINS_PURCHASED,
            
            // Marketing & Engagement
            Notification::TYPE_DAILY_MATCHES, Notification::TYPE_WEEKLY_DIGEST,
            Notification::TYPE_PROMOTIONAL_OFFER, Notification::TYPE_RE_ENGAGEMENT,
            
            // Moderation & Safety
            Notification::TYPE_SAFETY_ALERT, Notification::TYPE_REPORT_UPDATE,
            Notification::TYPE_MODERATION_ACTION
        ]);

        $priority = $this->faker->randomElement(Notification::PRIORITIES);
        $status = $this->faker->randomElement(Notification::STATUSES);
        $category = $this->getCategory($type);
        
        // Generate appropriate channels based on type and priority
        $channels = $this->generateChannels($type, $priority);
        
        $createdAt = $this->faker->dateTimeBetween('-6 months', 'now');
        $scheduledAt = $this->faker->boolean(20) ? $this->faker->dateTimeBetween($createdAt, '+1 week') : null;
        
        // Status-dependent timestamps
        $sentAt = $status !== 'queued' ? $this->faker->dateTimeBetween($scheduledAt ?? $createdAt, 'now') : null;
        $deliveredAt = in_array($status, ['delivered', 'read']) ? $this->faker->dateTimeBetween($sentAt ?? $createdAt, 'now') : null;
        $readAt = $status === 'read' ? $this->faker->dateTimeBetween($deliviredAt ?? $sentAt ?? $createdAt, 'now') : null;
        $clickedAt = $this->faker->boolean(15) && $readAt ? $this->faker->dateTimeBetween($readAt, 'now') : null;
        
        // Expiration logic
        $expiresAt = $this->faker->boolean(30) ? $this->faker->dateTimeBetween($createdAt, '+30 days') : null;
        
        return [
            'notification_id' => $this->faker->uuid(),
            'user_id' => User::factory(),
            'notifiable_type' => $this->faker->randomElement([
                'App\\Models\\User\\User',
                'App\\Models\\Match\\Match',
                'App\\Models\\Message\\Message',
                'App\\Models\\Commerce\\Subscription',
                null
            ]),
            'notifiable_id' => $this->faker->optional(0.7)->uuid(),
            'type' => $type,
            'channels' => $channels,
            'priority' => $priority,
            'status' => $status,
            'category' => $category,
            'title' => $this->generateTitle($type),
            'message' => $this->generateMessage($type),
            'data' => $this->generateData($type),
            'delivery_results' => $status !== 'queued' ? $this->generateDeliveryResults($channels, $status) : null,
            'preferences_snapshot' => $this->generatePreferencesSnapshot(),
            'template_id' => $this->faker->optional(0.4)->regexify('template_[0-9]{4}'),
            'campaign_id' => $this->faker->optional(0.3)->regexify('campaign_[0-9]{6}'),
            'ab_test_variant' => $this->faker->optional(0.2)->randomElement(['A', 'B', 'C', 'control']),
            'personalization' => $this->generatePersonalization($type),
            'reference_id' => $this->faker->optional(0.3)->regexify('ref_[A-Z0-9]{8}'),
            'metadata' => $this->generateMetadata($type, $priority),
            'scheduled_at' => $scheduledAt,
            'sent_at' => $sentAt,
            'delivered_at' => $deliveredAt,
            'read_at' => $readAt,
            'clicked_at' => $clickedAt,
            'expires_at' => $expiresAt,
            'retry_count' => $status === 'failed' ? $this->faker->numberBetween(0, 3) : 0,
            'failure_reason' => $status === 'failed' ? $this->faker->randomElement([
                'User opted out', 'Invalid device token', 'Rate limit exceeded',
                'Service unavailable', 'Invalid email address', 'User unsubscribed'
            ]) : null,
            'created_at' => $createdAt,
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    /**
     * Get category based on notification type
     */
    private function getCategory(string $type): string
    {
        $categoryMap = [
            // Dating
            Notification::TYPE_NEW_MATCH => Notification::CATEGORY_DATING,
            Notification::TYPE_MATCH_LIKED_YOU => Notification::CATEGORY_DATING,
            Notification::TYPE_MATCH_MESSAGE => Notification::CATEGORY_DATING,
            Notification::TYPE_SUPER_LIKE_RECEIVED => Notification::CATEGORY_DATING,
            Notification::TYPE_PROFILE_VISIT => Notification::CATEGORY_DATING,
            
            // Messaging
            Notification::TYPE_NEW_MESSAGE => Notification::CATEGORY_MESSAGING,
            Notification::TYPE_VOICE_MESSAGE => Notification::CATEGORY_MESSAGING,
            Notification::TYPE_VIDEO_CALL_INVITE => Notification::CATEGORY_MESSAGING,
            
            // System
            Notification::TYPE_PROFILE_APPROVED => Notification::CATEGORY_SYSTEM,
            Notification::TYPE_VERIFICATION_COMPLETE => Notification::CATEGORY_SYSTEM,
            Notification::TYPE_ACCOUNT_WARNING => Notification::CATEGORY_SYSTEM,
            
            // Marketing
            Notification::TYPE_DAILY_MATCHES => Notification::CATEGORY_MARKETING,
            Notification::TYPE_PROMOTIONAL_OFFER => Notification::CATEGORY_MARKETING,
            Notification::TYPE_RE_ENGAGEMENT => Notification::CATEGORY_MARKETING,
            
            // Moderation
            Notification::TYPE_SAFETY_ALERT => Notification::CATEGORY_MODERATION,
            Notification::TYPE_REPORT_UPDATE => Notification::CATEGORY_MODERATION,
        ];

        return $categoryMap[$type] ?? Notification::CATEGORY_SYSTEM;
    }

    /**
     * Generate appropriate channels based on type and priority
     */
    private function generateChannels(string $type, string $priority): array
    {
        $allChannels = [
            Notification::CHANNEL_PUSH,
            Notification::CHANNEL_EMAIL,
            Notification::CHANNEL_IN_APP,
            Notification::CHANNEL_SMS,
        ];

        // Critical notifications use all channels
        if ($priority === Notification::PRIORITY_CRITICAL) {
            return $this->faker->randomElements($allChannels, $this->faker->numberBetween(3, 4));
        }

        // Urgent notifications use multiple channels
        if ($priority === Notification::PRIORITY_URGENT) {
            return $this->faker->randomElements($allChannels, $this->faker->numberBetween(2, 3));
        }

        // Normal priority uses 1-2 channels
        if ($priority === Notification::PRIORITY_HIGH) {
            return $this->faker->randomElements($allChannels, $this->faker->numberBetween(1, 3));
        }

        // Low priority typically just in-app
        return $this->faker->randomElements([
            Notification::CHANNEL_IN_APP,
            Notification::CHANNEL_EMAIL
        ], $this->faker->numberBetween(1, 2));
    }

    /**
     * Generate notification title based on type
     */
    private function generateTitle(string $type): string
    {
        $titles = [
            Notification::TYPE_NEW_MATCH => [
                'You have a new match!',
                'It\'s a match! 💖',
                'Someone special matched with you',
                'New connection waiting for you!'
            ],
            Notification::TYPE_MATCH_MESSAGE => [
                'New message from your match',
                'Someone sent you a message',
                'You have a new message! 💬',
                'Your match is waiting for a reply'
            ],
            Notification::TYPE_SUPER_LIKE_RECEIVED => [
                'You received a Super Like! ⭐',
                'Someone Super Liked you!',
                'You caught someone\'s special attention',
                'Super Like alert! 🌟'
            ],
            Notification::TYPE_DAILY_MATCHES => [
                'Your daily matches are ready! 💕',
                'Fresh matches waiting for you',
                'New potential connections today',
                'Today\'s perfect matches'
            ],
            Notification::TYPE_PAYMENT_SUCCESS => [
                'Payment successful! 💳',
                'Your payment has been processed',
                'Transaction completed successfully',
                'Payment confirmation'
            ],
            Notification::TYPE_SAFETY_ALERT => [
                'Important safety update',
                'Safety alert - Please review',
                'Security notification',
                'Account safety notice'
            ],
            Notification::TYPE_RE_ENGAGEMENT => [
                'We miss you! Come back 💕',
                'Your matches are waiting',
                'Time to find love again',
                'Someone special might be waiting'
            ]
        ];

        return $this->faker->randomElement(
            $titles[$type] ?? ['Notification from ForeverUsInLove']
        );
    }

    /**
     * Generate notification message based on type
     */
    private function generateMessage(string $type): string
    {
        $messages = [
            Notification::TYPE_NEW_MATCH => [
                'You and someone special have liked each other! Start chatting now.',
                'Congratulations! You have a new match. Why not send the first message?',
                'Great news! Someone you liked has liked you back. Time to connect!',
                'It\'s official - you both swiped right! Break the ice with a message.'
            ],
            Notification::TYPE_SUPER_LIKE_RECEIVED => [
                'Someone used their Super Like on you! They must really be interested.',
                'You\'ve received a Super Like! This person is definitely interested in getting to know you.',
                'A Super Like means you really caught someone\'s eye. Check out their profile!',
                'Someone thinks you\'re super special! View your Super Like now.'
            ],
            Notification::TYPE_DAILY_MATCHES => [
                'We\'ve found some great potential matches for you today. Take a look!',
                'Your personalized matches are ready. Start swiping to find your connection!',
                'Fresh faces and new possibilities await. Check out today\'s matches!',
                'Based on your preferences, we think you\'ll love these new profiles.'
            ],
            Notification::TYPE_PAYMENT_SUCCESS => [
                'Your premium subscription has been activated. Enjoy unlimited features!',
                'Payment processed successfully. Your account has been upgraded!',
                'Thank you for your payment. Premium features are now available.',
                'Your subscription is now active. Start enjoying premium benefits!'
            ],
            Notification::TYPE_SAFETY_ALERT => [
                'We\'ve detected unusual activity on your account. Please review your security settings.',
                'For your safety, we recommend updating your password and reviewing recent activity.',
                'Your account security is important to us. Please verify your recent login activity.',
                'We\'re here to keep you safe. Please review this important security update.'
            ]
        ];

        return $this->faker->randomElement(
            $messages[$type] ?? ['You have a new notification from ForeverUsInLove.']
        );
    }

    /**
     * Generate notification data based on type
     */
    private function generateData(string $type): array
    {
        $baseData = [
            'app_version' => '2.1.0',
            'platform' => $this->faker->randomElement(['ios', 'android', 'web']),
            'locale' => $this->faker->randomElement(['en_US', 'es_ES', 'fr_FR', 'de_DE']),
            'timezone' => $this->faker->timezone()
        ];

        $typeSpecificData = match($type) {
            Notification::TYPE_NEW_MATCH => [
                'match_id' => $this->faker->uuid(),
                'match_profile_image' => $this->faker->imageUrl(300, 300, 'people'),
                'match_name' => $this->faker->firstName(),
                'match_age' => $this->faker->numberBetween(18, 45),
                'compatibility_score' => $this->faker->numberBetween(75, 99),
                'mutual_interests' => $this->faker->randomElements([
                    'travel', 'music', 'fitness', 'cooking', 'movies', 'art', 'sports'
                ], $this->faker->numberBetween(1, 4))
            ],
            Notification::TYPE_MATCH_MESSAGE => [
                'match_id' => $this->faker->uuid(),
                'message_id' => $this->faker->uuid(),
                'message_preview' => $this->faker->sentence(6),
                'sender_name' => $this->faker->firstName(),
                'message_type' => $this->faker->randomElement(['text', 'image', 'voice', 'gif'])
            ],
            Notification::TYPE_PAYMENT_SUCCESS => [
                'transaction_id' => $this->faker->regexify('txn_[A-Z0-9]{12}'),
                'amount' => $this->faker->numberBetween(999, 4999),
                'currency' => 'USD',
                'subscription_type' => $this->faker->randomElement(['premium', 'gold', 'platinum']),
                'billing_period' => $this->faker->randomElement(['monthly', 'quarterly', 'yearly'])
            ],
            Notification::TYPE_DAILY_MATCHES => [
                'match_count' => $this->faker->numberBetween(3, 12),
                'top_match_name' => $this->faker->firstName(),
                'top_match_image' => $this->faker->imageUrl(300, 300, 'people'),
                'compatibility_score' => $this->faker->numberBetween(85, 99)
            ],
            default => []
        };

        return array_merge($baseData, $typeSpecificData);
    }

    /**
     * Generate delivery results based on channels and status
     */
    private function generateDeliveryResults(array $channels, string $status): array
    {
        $results = [];
        
        foreach ($channels as $channel) {
            $success = $status !== 'failed' && $this->faker->boolean(85);
            
            $results[$channel] = [
                'success' => $success,
                'timestamp' => $this->faker->dateTimeThisMonth()->format('Y-m-d H:i:s'),
                'provider' => $this->getChannelProvider($channel),
                'response_time_ms' => $this->faker->numberBetween(100, 2000),
                'message_id' => $success ? $this->faker->regexify('msg_[A-Z0-9]{12}') : null,
                'error' => !$success ? $this->faker->randomElement([
                    'Rate limit exceeded', 'Invalid token', 'Service unavailable',
                    'User opted out', 'Delivery timeout'
                ]) : null
            ];
        }
        
        return $results;
    }

    /**
     * Get provider for channel
     */
    private function getChannelProvider(string $channel): string
    {
        return match($channel) {
            Notification::CHANNEL_EMAIL => $this->faker->randomElement(['sendgrid', 'mailgun', 'postmark']),
            Notification::CHANNEL_PUSH => $this->faker->randomElement(['fcm', 'apns', 'onesignal']),
            Notification::CHANNEL_SMS => $this->faker->randomElement(['twilio', 'nexmo', 'aws_sns']),
            default => 'internal'
        };
    }

    /**
     * Generate user preferences snapshot
     */
    private function generatePreferencesSnapshot(): array
    {
        return [
            'push_enabled' => $this->faker->boolean(80),
            'email_enabled' => $this->faker->boolean(70),
            'sms_enabled' => $this->faker->boolean(30),
            'quiet_hours_enabled' => $this->faker->boolean(60),
            'quiet_hours_start' => $this->faker->time('H:i', '23:59'),
            'quiet_hours_end' => $this->faker->time('H:i', '09:00'),
            'marketing_enabled' => $this->faker->boolean(50),
            'frequency_preference' => $this->faker->randomElement(['immediate', 'hourly', 'daily', 'weekly']),
            'language' => $this->faker->randomElement(['en', 'es', 'fr', 'de']),
            'notification_types_enabled' => $this->faker->randomElements([
                'new_matches', 'messages', 'profile_visits', 'super_likes',
                'marketing', 'system_updates', 'safety_alerts'
            ], $this->faker->numberBetween(3, 7))
        ];
    }

    /**
     * Generate personalization data
     */
    private function generatePersonalization(string $type): array
    {
        return [
            'user_name' => $this->faker->firstName(),
            'user_age' => $this->faker->numberBetween(18, 65),
            'user_location' => $this->faker->city(),
            'interests' => $this->faker->randomElements([
                'travel', 'music', 'fitness', 'cooking', 'movies', 'art', 'sports',
                'reading', 'dancing', 'photography', 'gaming', 'nature'
            ], $this->faker->numberBetween(2, 5)),
            'relationship_goal' => $this->faker->randomElement([
                'serious_relationship', 'casual_dating', 'friendship', 'not_sure'
            ]),
            'activity_level' => $this->faker->randomElement(['new', 'active', 'occasional', 'returning']),
            'premium_user' => $this->faker->boolean(25),
            'last_active' => $this->faker->dateTimeThisMonth()->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate metadata
     */
    private function generateMetadata(string $type, string $priority): array
    {
        return [
            'source' => $this->faker->randomElement(['system', 'user_action', 'scheduled', 'trigger']),
            'trigger_event' => $this->faker->randomElement([
                'user_login', 'profile_update', 'match_created', 'message_sent',
                'payment_processed', 'subscription_expiring', 'inactivity_detected'
            ]),
            'processing_time_ms' => $this->faker->numberBetween(50, 500),
            'priority_score' => $this->getPriorityScore($priority),
            'audience_segment' => $this->faker->randomElement([
                'new_users', 'active_users', 'premium_users', 'inactive_users',
                'high_engagement', 'low_engagement'
            ]),
            'experiment_id' => $this->faker->optional(0.3)->regexify('exp_[0-9]{4}'),
            'feature_flags' => $this->faker->randomElements([
                'rich_notifications', 'deep_links', 'interactive_buttons',
                'ai_personalization', 'smart_timing'
            ], $this->faker->numberBetween(1, 3)),
            'delivery_optimization' => [
                'optimal_time' => $this->faker->boolean(40),
                'user_timezone_adjusted' => $this->faker->boolean(80),
                'frequency_capped' => $this->faker->boolean(30)
            ]
        ];
    }

    /**
     * Get priority score for metadata
     */
    private function getPriorityScore(string $priority): int
    {
        return match($priority) {
            Notification::PRIORITY_LOW => $this->faker->numberBetween(1, 30),
            Notification::PRIORITY_NORMAL => $this->faker->numberBetween(31, 60),
            Notification::PRIORITY_HIGH => $this->faker->numberBetween(61, 80),
            Notification::PRIORITY_URGENT => $this->faker->numberBetween(81, 95),
            Notification::PRIORITY_CRITICAL => $this->faker->numberBetween(96, 100),
            default => 50
        };
    }

    /**
     * Dating activity notifications (matches, likes, visits)
     */
    public function datingActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $this->faker->randomElement([
                Notification::TYPE_NEW_MATCH,
                Notification::TYPE_MATCH_LIKED_YOU,
                Notification::TYPE_SUPER_LIKE_RECEIVED,
                Notification::TYPE_PROFILE_VISIT,
                Notification::TYPE_PHOTO_LIKED
            ]),
            'category' => Notification::CATEGORY_DATING,
            'priority' => $this->faker->weightedElement([
                Notification::PRIORITY_HIGH => 60,
                Notification::PRIORITY_NORMAL => 30,
                Notification::PRIORITY_URGENT => 10
            ]),
            'channels' => $this->faker->randomElements([
                Notification::CHANNEL_PUSH,
                Notification::CHANNEL_IN_APP,
                Notification::CHANNEL_EMAIL
            ], $this->faker->numberBetween(1, 3))
        ]);
    }

    /**
     * Messaging notifications
     */
    public function messaging(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $this->faker->randomElement([
                Notification::TYPE_NEW_MESSAGE,
                Notification::TYPE_VOICE_MESSAGE,
                Notification::TYPE_VIDEO_CALL_INVITE,
                Notification::TYPE_MISSED_CALL
            ]),
            'category' => Notification::CATEGORY_MESSAGING,
            'priority' => $this->faker->weightedElement([
                Notification::PRIORITY_HIGH => 50,
                Notification::PRIORITY_URGENT => 30,
                Notification::PRIORITY_NORMAL => 20
            ]),
            'channels' => [Notification::CHANNEL_PUSH, Notification::CHANNEL_IN_APP]
        ]);
    }

    /**
     * System notifications (security, account updates)
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $this->faker->randomElement([
                Notification::TYPE_PROFILE_APPROVED,
                Notification::TYPE_VERIFICATION_COMPLETE,
                Notification::TYPE_ACCOUNT_WARNING,
                Notification::TYPE_PASSWORD_CHANGED,
                Notification::TYPE_EMAIL_CHANGED
            ]),
            'category' => Notification::CATEGORY_SYSTEM,
            'priority' => $this->faker->weightedElement([
                Notification::PRIORITY_HIGH => 40,
                Notification::PRIORITY_URGENT => 35,
                Notification::PRIORITY_CRITICAL => 15,
                Notification::PRIORITY_NORMAL => 10
            ]),
            'channels' => [
                Notification::CHANNEL_EMAIL,
                Notification::CHANNEL_PUSH,
                Notification::CHANNEL_IN_APP
            ]
        ]);
    }

    /**
     * Marketing and engagement notifications
     */
    public function marketing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $this->faker->randomElement([
                Notification::TYPE_DAILY_MATCHES,
                Notification::TYPE_WEEKLY_DIGEST,
                Notification::TYPE_PROMOTIONAL_OFFER,
                Notification::TYPE_RE_ENGAGEMENT,
                Notification::TYPE_FEATURE_ANNOUNCEMENT
            ]),
            'category' => Notification::CATEGORY_MARKETING,
            'priority' => $this->faker->weightedElement([
                Notification::PRIORITY_LOW => 40,
                Notification::PRIORITY_NORMAL => 50,
                Notification::PRIORITY_HIGH => 10
            ]),
            'channels' => $this->faker->randomElements([
                Notification::CHANNEL_EMAIL,
                Notification::CHANNEL_PUSH,
                Notification::CHANNEL_IN_APP
            ], $this->faker->numberBetween(1, 2)),
            'campaign_id' => 'campaign_' . $this->faker->numberBetween(100000, 999999),
            'ab_test_variant' => $this->faker->randomElement(['A', 'B', 'control'])
        ]);
    }

    /**
     * High priority notifications (urgent, critical)
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $this->faker->randomElement([
                Notification::PRIORITY_HIGH,
                Notification::PRIORITY_URGENT,
                Notification::PRIORITY_CRITICAL
            ]),
            'channels' => [
                Notification::CHANNEL_PUSH,
                Notification::CHANNEL_EMAIL,
                Notification::CHANNEL_IN_APP,
                Notification::CHANNEL_SMS
            ],
            'status' => $this->faker->weightedElement([
                Notification::STATUS_SENT => 60,
                Notification::STATUS_DELIVERED => 25,
                Notification::STATUS_READ => 15
            ])
        ]);
    }

    /**
     * Failed notifications
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Notification::STATUS_FAILED,
            'retry_count' => $this->faker->numberBetween(1, 3),
            'failure_reason' => $this->faker->randomElement([
                'Service unavailable',
                'Invalid device token',
                'Rate limit exceeded',
                'User opted out',
                'Email bounced',
                'SMS delivery failed'
            ]),
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null
        ]);
    }

    /**
     * Read notifications
     */
    public function read(): static
    {
        $sentAt = $this->faker->dateTimeBetween('-1 month', '-1 day');
        $deliveredAt = $this->faker->dateTimeBetween($sentAt, '-12 hours');
        $readAt = $this->faker->dateTimeBetween($deliveredAt, 'now');
        
        return $this->state(fn (array $attributes) => [
            'status' => Notification::STATUS_READ,
            'sent_at' => $sentAt,
            'delivered_at' => $deliveredAt,
            'read_at' => $readAt,
            'clicked_at' => $this->faker->boolean(30) ? $this->faker->dateTimeBetween($readAt, 'now') : null
        ]);
    }

    /**
     * Scheduled notifications for future delivery
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Notification::STATUS_QUEUED,
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 week'),
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null
        ]);
    }

    /**
     * Campaign notifications with A/B testing
     */
    public function campaign(): static
    {
        return $this->state(fn (array $attributes) => [
            'campaign_id' => 'campaign_' . $this->faker->numberBetween(100000, 999999),
            'ab_test_variant' => $this->faker->randomElement(['A', 'B', 'C', 'control']),
            'template_id' => 'template_' . $this->faker->numberBetween(1000, 9999),
            'category' => Notification::CATEGORY_MARKETING
        ]);
    }

    /**
     * Critical safety notifications
     */
    public function safetyCritical(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $this->faker->randomElement([
                Notification::TYPE_SAFETY_ALERT,
                Notification::TYPE_ACCOUNT_WARNING,
                Notification::TYPE_MODERATION_ACTION
            ]),
            'category' => Notification::CATEGORY_MODERATION,
            'priority' => Notification::PRIORITY_CRITICAL,
            'channels' => [
                Notification::CHANNEL_PUSH,
                Notification::CHANNEL_EMAIL,
                Notification::CHANNEL_IN_APP,
                Notification::CHANNEL_SMS
            ],
            'status' => Notification::STATUS_SENT
        ]);
    }

    /**
     * Commerce notifications (payments, subscriptions)
     */
    public function commerce(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $this->faker->randomElement([
                Notification::TYPE_SUBSCRIPTION_EXPIRING,
                Notification::TYPE_PAYMENT_SUCCESS,
                Notification::TYPE_PAYMENT_FAILED,
                Notification::TYPE_COINS_PURCHASED
            ]),
            'category' => Notification::CATEGORY_SYSTEM,
            'priority' => $this->faker->weightedElement([
                Notification::PRIORITY_HIGH => 60,
                Notification::PRIORITY_URGENT => 25,
                Notification::PRIORITY_NORMAL => 15
            ]),
            'channels' => [Notification::CHANNEL_EMAIL, Notification::CHANNEL_IN_APP]
        ]);
    }
}