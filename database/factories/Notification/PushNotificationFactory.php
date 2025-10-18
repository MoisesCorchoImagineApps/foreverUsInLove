<?php

namespace Database\Factories\Domains\Notification;

use App\Domains\Notification\Models\PushNotification;
use App\Domains\Notification\Models\Notification;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domains\Notification\Models\PushNotification>
 */
class PushNotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PushNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platform = $this->faker->randomElement(PushNotification::PLATFORMS);
        $provider = $this->getProviderForPlatform($platform);
        $pushType = $this->faker->randomElement(PushNotification::PUSH_TYPES);
        $priority = $this->faker->weightedElement([
            PushNotification::PRIORITY_LOW => 20,
            PushNotification::PRIORITY_NORMAL => 60,
            PushNotification::PRIORITY_HIGH => 20
        ]);

        $totalTokens = $this->faker->numberBetween(1, 5);
        $sentCount = $this->faker->numberBetween(0, $totalTokens);
        $failedCount = $totalTokens - $sentCount;

        $createdAt = $this->faker->dateTimeBetween('-6 months', 'now');
        $sentAt = $sentCount > 0 ? $this->faker->dateTimeBetween($createdAt, 'now') : null;
        $deliveredAt = $sentAt && $this->faker->boolean(85) ? 
            $this->faker->dateTimeBetween($sentAt, 'now') : null;
        $openedAt = $deliveredAt && $this->faker->boolean(30) ? 
            $this->faker->dateTimeBetween($deliveredAt, 'now') : null;

        return [
            'push_id' => $this->faker->uuid(),
            'notification_id' => Notification::factory(),
            'user_id' => User::factory(),
            'platform' => $platform,
            'provider' => $provider,
            'push_type' => $pushType,
            'priority' => $priority,
            'total_tokens' => $totalTokens,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'platform_results' => $this->generatePlatformResults($platform, $sentCount, $failedCount),
            'title' => $this->generateTitle($platform, $pushType),
            'body' => $this->generateBody($platform, $pushType),
            'image_url' => $this->faker->boolean(25) ? $this->faker->imageUrl(400, 300, 'people') : null,
            'icon_url' => $this->faker->boolean(40) ? $this->faker->imageUrl(64, 64, 'abstract') : null,
            'deep_link' => $this->generateDeepLink($pushType),
            'badge_count' => $platform === PushNotification::PLATFORM_IOS ? 
                $this->faker->numberBetween(1, 99) : null,
            'sound' => $this->generateSound($platform, $pushType),
            'action_buttons' => $this->generateActionButtons($pushType),
            'data' => $this->generateData($platform, $pushType),
            'metadata' => $this->generateMetadata($platform, $provider, $priority),
            'sent_at' => $sentAt,
            'delivered_at' => $deliveredAt,
            'opened_at' => $openedAt,
            'created_at' => $createdAt,
            'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    /**
     * Get optimal provider for platform
     */
    private function getProviderForPlatform(string $platform): string
    {
        $config = PushNotification::PLATFORM_CONFIGS[$platform];
        
        // 80% use default provider, 20% use fallback
        if ($this->faker->boolean(80)) {
            return $config['default_provider'];
        }
        
        return $config['fallback_provider'] ?? $config['default_provider'];
    }

    /**
     * Generate platform-specific title
     */
    private function generateTitle(string $platform, string $pushType): string
    {
        if ($pushType === PushNotification::TYPE_SILENT || $pushType === PushNotification::TYPE_BADGE) {
            return '';
        }

        $titles = [
            'dating' => [
                'You have a new match! 💖',
                'Someone liked you back!',
                'New connection waiting 💕',
                'It\'s a match! Say hello 👋',
                'Someone super liked you! ⭐'
            ],
            'messaging' => [
                'New message 💬',
                'Someone sent you a message',
                'You have unread messages',
                'Your match replied!',
                'Voice message received 🎵'
            ],
            'engagement' => [
                'Your daily matches are ready!',
                'We miss you! Come back 💕',
                'Someone viewed your profile',
                'Time to find love again',
                'Fresh matches waiting for you'
            ],
            'system' => [
                'Account update',
                'Security alert 🔒',
                'Profile approved! 🎉',
                'Payment successful 💳',
                'Welcome to premium!'
            ]
        ];

        $category = $this->faker->randomElement(['dating', 'messaging', 'engagement', 'system']);
        return $this->faker->randomElement($titles[$category]);
    }

    /**
     * Generate platform-specific body text
     */
    private function generateBody(string $platform, string $pushType): string
    {
        if ($pushType === PushNotification::TYPE_SILENT || $pushType === PushNotification::TYPE_BADGE) {
            return '';
        }

        $bodies = [
            'You both swiped right! Start chatting now.',
            'Someone special is waiting to hear from you.',
            'Check out your new matches and start connecting.',
            'Don\'t keep them waiting - reply to your messages.',
            'Your perfect match might be just one swipe away.',
            'Complete your profile to get better matches.',
            'Premium features are now available to you.',
            'Update your preferences to find better matches.',
            'Someone thinks you\'re special! Check it out.',
            'Your account has been successfully verified.'
        ];

        $body = $this->faker->randomElement($bodies);
        
        // Limit body length based on platform
        $maxLength = match($platform) {
            PushNotification::PLATFORM_IOS => 178,
            PushNotification::PLATFORM_ANDROID => 240,
            PushNotification::PLATFORM_WEB => 160,
            default => 200
        };

        return strlen($body) > $maxLength ? substr($body, 0, $maxLength - 3) . '...' : $body;
    }

    /**
     * Generate deep link URL
     */
    private function generateDeepLink(string $pushType): ?string
    {
        if ($pushType === PushNotification::TYPE_SILENT) {
            return null;
        }

        $deepLinks = [
            'foreverusinlove://matches',
            'foreverusinlove://messages',
            'foreverusinlove://profile',
            'foreverusinlove://discover',
            'foreverusinlove://settings',
            'foreverusinlove://premium',
            'foreverusinlove://match/' . $this->faker->uuid(),
            'foreverusinlove://conversation/' . $this->faker->uuid(),
            'foreverusinlove://user/' . $this->faker->uuid()
        ];

        return $this->faker->randomElement($deepLinks);
    }

    /**
     * Generate sound configuration
     */
    private function generateSound(string $platform, string $pushType): ?string
    {
        if ($pushType === PushNotification::TYPE_SILENT) {
            return null;
        }

        if ($pushType === PushNotification::TYPE_BADGE) {
            return null;
        }

        $sounds = match($platform) {
            PushNotification::PLATFORM_IOS => [
                'default', 'ding.caf', 'love_notification.caf', 
                'match_sound.caf', 'message_tone.caf'
            ],
            PushNotification::PLATFORM_ANDROID => [
                'default', 'notification', 'love_tone', 
                'match_alert', 'message_sound'
            ],
            default => ['default']
        };

        return $this->faker->randomElement($sounds);
    }

    /**
     * Generate interactive action buttons
     */
    private function generateActionButtons(string $pushType): ?array
    {
        if ($pushType !== PushNotification::TYPE_INTERACTIVE) {
            return null;
        }

        $buttonSets = [
            // Match notification buttons
            [
                [
                    'id' => 'view_profile',
                    'title' => 'View Profile',
                    'action' => 'view'
                ],
                [
                    'id' => 'send_message',
                    'title' => 'Send Message',
                    'action' => 'message'
                ]
            ],
            // Message notification buttons
            [
                [
                    'id' => 'reply',
                    'title' => 'Reply',
                    'action' => 'reply'
                ],
                [
                    'id' => 'view_conversation',
                    'title' => 'View Chat',
                    'action' => 'view'
                ]
            ],
            // General engagement buttons
            [
                [
                    'id' => 'open_app',
                    'title' => 'Open App',
                    'action' => 'open'
                ],
                [
                    'id' => 'dismiss',
                    'title' => 'Later',
                    'action' => 'dismiss'
                ]
            ]
        ];

        return $this->faker->randomElement($buttonSets);
    }

    /**
     * Generate custom data payload
     */
    private function generateData(string $platform, string $pushType): array
    {
        $baseData = [
            'push_id' => $this->faker->uuid(),
            'timestamp' => now()->toISOString(),
            'app_version' => $this->faker->semver(),
            'platform' => $platform,
            'push_type' => $pushType
        ];

        // Add type-specific data
        $typeSpecificData = match($pushType) {
            PushNotification::TYPE_ALERT, PushNotification::TYPE_INTERACTIVE => [
                'notification_type' => $this->faker->randomElement([
                    'new_match', 'new_message', 'profile_view', 'super_like'
                ]),
                'entity_id' => $this->faker->uuid(),
                'entity_type' => $this->faker->randomElement(['match', 'message', 'user', 'event']),
                'priority_score' => $this->faker->numberBetween(1, 100)
            ],
            PushNotification::TYPE_RICH => [
                'media_type' => $this->faker->randomElement(['image', 'gif', 'video']),
                'media_url' => $this->faker->imageUrl(400, 300),
                'media_size' => $this->faker->numberBetween(50000, 500000)
            ],
            PushNotification::TYPE_SILENT => [
                'background_task' => $this->faker->randomElement([
                    'sync_matches', 'update_profile', 'refresh_data', 'check_messages'
                ]),
                'sync_timestamp' => now()->toISOString()
            ],
            default => []
        };

        // Add platform-specific data
        $platformSpecificData = match($platform) {
            PushNotification::PLATFORM_IOS => [
                'aps' => [
                    'category' => $this->faker->randomElement(['match', 'message', 'general']),
                    'thread-id' => $this->faker->uuid()
                ]
            ],
            PushNotification::PLATFORM_ANDROID => [
                'android' => [
                    'channel_id' => $this->faker->randomElement(['matches', 'messages', 'general']),
                    'notification_priority' => $this->faker->randomElement(['default', 'high', 'low'])
                ]
            ],
            PushNotification::PLATFORM_WEB => [
                'web' => [
                    'icon' => $this->faker->imageUrl(64, 64),
                    'badge' => $this->faker->imageUrl(72, 96),
                    'requireInteraction' => $this->faker->boolean()
                ]
            ],
            default => []
        };

        return array_merge($baseData, $typeSpecificData, $platformSpecificData);
    }

    /**
     * Generate platform results
     */
    private function generatePlatformResults(string $platform, int $sentCount, int $failedCount): array
    {
        $results = [
            $platform => [
                'sent' => $sentCount,
                'failed' => $failedCount,
                'success_rate' => $sentCount + $failedCount > 0 ? 
                    round(($sentCount / ($sentCount + $failedCount)) * 100, 2) : 0,
                'provider_response' => $sentCount > 0 ? [
                    'message_id' => $this->faker->regexify('[A-Z0-9]{20}'),
                    'response_time_ms' => $this->faker->numberBetween(100, 2000),
                    'status' => 'success'
                ] : [
                    'error_code' => $this->faker->randomElement(['invalid_token', 'rate_limit', 'service_error']),
                    'error_message' => $this->faker->sentence(),
                    'retry_after' => $this->faker->numberBetween(60, 3600)
                ]
            ]
        ];

        // Add failure details if any failed
        if ($failedCount > 0) {
            $results[$platform]['failures'] = [];
            for ($i = 0; $i < min($failedCount, 3); $i++) {
                $results[$platform]['failures'][] = [
                    'token_index' => $i,
                    'error' => $this->faker->randomElement([
                        'NotRegistered', 'InvalidRegistration', 'MessageTooBig',
                        'InvalidDataKey', 'InvalidTtl', 'Unavailable'
                    ]),
                    'description' => $this->faker->sentence()
                ];
            }
        }

        return $results;
    }

    /**
     * Generate metadata
     */
    private function generateMetadata(string $platform, string $provider, string $priority): array
    {
        return [
            'platform_config' => PushNotification::PLATFORM_CONFIGS[$platform],
            'provider_config' => [
                'provider' => $provider,
                'api_version' => $this->faker->randomElement(['v1', 'v2', 'v3']),
                'endpoint' => $this->getProviderEndpoint($provider),
                'authentication_type' => $this->faker->randomElement(['api_key', 'oauth', 'jwt'])
            ],
            'delivery_optimization' => [
                'optimal_time' => $this->faker->boolean(60),
                'timezone_adjusted' => $this->faker->boolean(80),
                'frequency_capped' => $this->faker->boolean(40),
                'user_active_hours' => $this->faker->boolean(70)
            ],
            'device_info' => [
                'os_version' => $this->generateOSVersion($platform),
                'app_version' => $this->faker->semver(),
                'device_model' => $this->generateDeviceModel($platform),
                'screen_resolution' => $this->faker->randomElement([
                    '1080x1920', '1440x2560', '828x1792', '1170x2532', '1284x2778'
                ])
            ],
            'engagement_prediction' => [
                'open_probability' => $this->faker->randomFloat(2, 0.1, 0.9),
                'engagement_score' => $this->faker->numberBetween(1, 100),
                'best_send_time' => $this->faker->time('H:i'),
                'user_segment' => $this->faker->randomElement([
                    'high_engagement', 'medium_engagement', 'low_engagement', 'new_user'
                ])
            ],
            'performance_metrics' => [
                'processing_time_ms' => $this->faker->numberBetween(50, 500),
                'queue_time_ms' => $this->faker->numberBetween(10, 200),
                'delivery_attempt' => $this->faker->numberBetween(1, 3),
                'retry_count' => $this->faker->numberBetween(0, 2)
            ]
        ];
    }

    /**
     * Get provider endpoint
     */
    private function getProviderEndpoint(string $provider): string
    {
        return match($provider) {
            PushNotification::PROVIDER_FCM => 'https://fcm.googleapis.com/fcm/send',
            PushNotification::PROVIDER_APNS => 'https://api.push.apple.com/3/device/',
            PushNotification::PROVIDER_HMS => 'https://push-api.cloud.huawei.com/v1/',
            PushNotification::PROVIDER_ONESIGNAL => 'https://onesignal.com/api/v1/notifications',
            default => 'https://api.pushservice.com/v1/send'
        };
    }

    /**
     * Generate OS version based on platform
     */
    private function generateOSVersion(string $platform): string
    {
        return match($platform) {
            PushNotification::PLATFORM_IOS => $this->faker->randomElement([
                '16.0', '16.1', '16.2', '16.3', '16.4', '17.0', '17.1', '17.2'
            ]),
            PushNotification::PLATFORM_ANDROID => $this->faker->randomElement([
                '11', '12', '13', '14', '10'
            ]),
            PushNotification::PLATFORM_WEB => $this->faker->randomElement([
                'Chrome 118', 'Firefox 119', 'Safari 17', 'Edge 118'
            ]),
            default => '1.0'
        };
    }

    /**
     * Generate device model based on platform
     */
    private function generateDeviceModel(string $platform): string
    {
        return match($platform) {
            PushNotification::PLATFORM_IOS => $this->faker->randomElement([
                'iPhone14,2', 'iPhone14,3', 'iPhone13,2', 'iPhone13,3', 
                'iPhone12,1', 'iPhone12,3', 'iPhone11,2', 'iPhone15,2'
            ]),
            PushNotification::PLATFORM_ANDROID => $this->faker->randomElement([
                'SM-G991B', 'Pixel 7', 'SM-A525F', 'OnePlus 9', 
                'Xiaomi 12', 'Huawei P50', 'Sony Xperia 1'
            ]),
            PushNotification::PLATFORM_WEB => $this->faker->randomElement([
                'Desktop-Windows', 'Desktop-macOS', 'Desktop-Linux', 'Mobile-PWA'
            ]),
            default => 'Unknown'
        };
    }

    /**
     * Android push notifications
     */
    public function android(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => PushNotification::PLATFORM_ANDROID,
            'provider' => $this->faker->randomElement([
                PushNotification::PROVIDER_FCM,
                PushNotification::PROVIDER_ONESIGNAL
            ]),
            'push_type' => $this->faker->weightedElement([
                PushNotification::TYPE_ALERT => 60,
                PushNotification::TYPE_RICH => 20,
                PushNotification::TYPE_INTERACTIVE => 15,
                PushNotification::TYPE_SILENT => 5
            ])
        ]);
    }

    /**
     * iOS push notifications
     */
    public function ios(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => PushNotification::PLATFORM_IOS,
            'provider' => $this->faker->randomElement([
                PushNotification::PROVIDER_APNS,
                PushNotification::PROVIDER_FCM
            ]),
            'badge_count' => $this->faker->numberBetween(1, 99),
            'sound' => $this->faker->randomElement([
                'default', 'ding.caf', 'love_notification.caf'
            ])
        ]);
    }

    /**
     * Web push notifications
     */
    public function web(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => PushNotification::PLATFORM_WEB,
            'provider' => PushNotification::PROVIDER_FCM,
            'push_type' => $this->faker->weightedElement([
                PushNotification::TYPE_ALERT => 70,
                PushNotification::TYPE_RICH => 20,
                PushNotification::TYPE_INTERACTIVE => 10
            ]),
            'icon_url' => $this->faker->imageUrl(64, 64, 'abstract'),
            'badge_count' => null // Web doesn't support badge
        ]);
    }

    /**
     * High priority push notifications
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => PushNotification::PRIORITY_HIGH,
            'push_type' => $this->faker->randomElement([
                PushNotification::TYPE_ALERT,
                PushNotification::TYPE_INTERACTIVE
            ]),
            'sent_count' => $attributes['total_tokens'], // All tokens successful
            'failed_count' => 0
        ]);
    }

    /**
     * Rich media push notifications
     */
    public function richMedia(): static
    {
        return $this->state(fn (array $attributes) => [
            'push_type' => PushNotification::TYPE_RICH,
            'image_url' => $this->faker->imageUrl(400, 300, 'people'),
            'icon_url' => $this->faker->imageUrl(64, 64, 'abstract'),
            'title' => 'New match with photos! 📸',
            'body' => 'Check out their amazing profile pictures'
        ]);
    }

    /**
     * Interactive push notifications with action buttons
     */
    public function interactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'push_type' => PushNotification::TYPE_INTERACTIVE,
            'action_buttons' => [
                [
                    'id' => 'view_profile',
                    'title' => 'View Profile',
                    'action' => 'view'
                ],
                [
                    'id' => 'send_message',
                    'title' => 'Message',
                    'action' => 'message'
                ]
            ]
        ]);
    }

    /**
     * Silent background push notifications
     */
    public function silent(): static
    {
        return $this->state(fn (array $attributes) => [
            'push_type' => PushNotification::TYPE_SILENT,
            'title' => '',
            'body' => '',
            'sound' => null,
            'data' => array_merge($attributes['data'] ?? [], [
                'background_task' => $this->faker->randomElement([
                    'sync_matches', 'update_profile', 'refresh_data'
                ]),
                'content_available' => 1
            ])
        ]);
    }

    /**
     * Failed push notifications
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_count' => 0,
            'failed_count' => $attributes['total_tokens'],
            'sent_at' => null,
            'delivered_at' => null,
            'opened_at' => null,
            'platform_results' => [
                $attributes['platform'] => [
                    'sent' => 0,
                    'failed' => $attributes['total_tokens'],
                    'success_rate' => 0,
                    'error_code' => $this->faker->randomElement([
                        'invalid_token', 'service_unavailable', 'rate_limit_exceeded'
                    ]),
                    'error_message' => $this->faker->sentence()
                ]
            ]
        ]);
    }

    /**
     * Successfully opened push notifications
     */
    public function opened(): static
    {
        $sentAt = $this->faker->dateTimeBetween('-1 month', '-1 day');
        $deliveredAt = $this->faker->dateTimeBetween($sentAt, '-12 hours');
        $openedAt = $this->faker->dateTimeBetween($deliveredAt, 'now');
        
        return $this->state(fn (array $attributes) => [
            'sent_count' => $attributes['total_tokens'],
            'failed_count' => 0,
            'sent_at' => $sentAt,
            'delivered_at' => $deliveredAt,
            'opened_at' => $openedAt
        ]);
    }

    /**
     * Badge count updates (iOS only)
     */
    public function badgeUpdate(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => PushNotification::PLATFORM_IOS,
            'push_type' => PushNotification::TYPE_BADGE,
            'title' => '',
            'body' => '',
            'badge_count' => $this->faker->numberBetween(1, 99),
            'sound' => null,
            'data' => [
                'badge_update' => true,
                'content_available' => 1
            ]
        ]);
    }

    /**
     * Dating activity notifications (matches, likes)
     */
    public function datingActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->randomElement([
                'You have a new match! 💖',
                'Someone liked you back!',
                'It\'s a match! Say hello 👋',
                'Someone super liked you! ⭐'
            ]),
            'body' => $this->faker->randomElement([
                'You both swiped right! Start chatting now.',
                'Someone special is waiting to hear from you.',
                'Time to break the ice with a message!'
            ]),
            'deep_link' => 'foreverusinlove://matches',
            'priority' => PushNotification::PRIORITY_HIGH,
            'image_url' => $this->faker->imageUrl(300, 300, 'people')
        ]);
    }

    /**
     * Message notifications
     */
    public function messaging(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->randomElement([
                'New message 💬',
                'Your match replied!',
                'Someone sent you a message'
            ]),
            'body' => $this->faker->randomElement([
                'Don\'t keep them waiting - reply now!',
                'Check out what they said.',
                'Continue your conversation.'
            ]),
            'deep_link' => 'foreverusinlove://messages',
            'priority' => PushNotification::PRIORITY_HIGH,
            'push_type' => PushNotification::TYPE_INTERACTIVE,
            'action_buttons' => [
                [
                    'id' => 'reply',
                    'title' => 'Reply',
                    'action' => 'reply'
                ],
                [
                    'id' => 'view_chat',
                    'title' => 'View Chat',
                    'action' => 'view'
                ]
            ]
        ]);
    }

    /**
     * Multi-platform push (sent to multiple platforms)
     */
    public function multiPlatform(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_tokens' => $this->faker->numberBetween(3, 10),
            'platform_results' => [
                PushNotification::PLATFORM_ANDROID => [
                    'sent' => $this->faker->numberBetween(1, 3),
                    'failed' => $this->faker->numberBetween(0, 1),
                    'success_rate' => $this->faker->randomFloat(2, 70, 100)
                ],
                PushNotification::PLATFORM_IOS => [
                    'sent' => $this->faker->numberBetween(1, 3),
                    'failed' => $this->faker->numberBetween(0, 1),
                    'success_rate' => $this->faker->randomFloat(2, 70, 100)
                ],
                PushNotification::PLATFORM_WEB => [
                    'sent' => $this->faker->numberBetween(0, 2),
                    'failed' => $this->faker->numberBetween(0, 1),
                    'success_rate' => $this->faker->randomFloat(2, 60, 95)
                ]
            ]
        ]);
    }
}