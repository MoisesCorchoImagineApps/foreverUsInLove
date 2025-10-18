<?php

namespace Database\Factories\User;

use App\Models\User\UserImage;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * UserImageFactory - Factory for UserImage Model
 * 
 * Generates comprehensive photo data with moderation states and metadata
 * Handles all User model relationships and photo business logic
 * Supports realistic dating app photo scenarios with AI moderation
 */
class UserImageFactory extends Factory
{
    protected $model = UserImage::class;

    // Realistic photo filenames and metadata
    private static $photoTypes = [
        'portrait' => ['selfie_', 'headshot_', 'portrait_', 'face_'],
        'full_body' => ['fullbody_', 'outfit_', 'standing_', 'photo_'],
        'activity' => ['hiking_', 'travel_', 'sports_', 'activity_'],
        'lifestyle' => ['lifestyle_', 'candid_', 'casual_', 'natural_'],
        'professional' => ['professional_', 'work_', 'business_', 'formal_'],
        'social' => ['party_', 'friends_', 'event_', 'social_'],
    ];

    private static $locations = [
        'studio', 'outdoors', 'home', 'office', 'restaurant', 'beach', 'park', 
        'gym', 'travel_destination', 'event', 'street', 'nature'
    ];

    private static $deviceModels = [
        'iPhone 15 Pro', 'iPhone 14', 'Samsung Galaxy S24', 'Google Pixel 8',
        'iPhone 13', 'Samsung Galaxy S23', 'OnePlus 11', 'Xiaomi 13',
        'DSLR Canon EOS R5', 'Sony A7 IV', 'Professional Camera'
    ];

    private static $rejectionReasons = [
        'Inappropriate content detected',
        'Face not clearly visible',
        'Multiple people in photo',
        'Poor image quality',
        'Blurry or dark image',
        'Contains text or watermarks',
        'Not a real person',
        'Inappropriate clothing',
        'Spam or promotional content',
        'Copyright violation suspected'
    ];

    public function definition(): array
    {
        $photoType = $this->faker->randomElement(array_keys(self::$photoTypes));
        $prefix = $this->faker->randomElement(self::$photoTypes[$photoType]);
        $filename = $prefix . $this->faker->numberBetween(1000, 9999) . '.jpg';
        
        // Generate realistic dimensions based on photo type
        [$width, $height] = $this->generateRealisticDimensions($photoType);
        $sizeBytes = $this->calculateFileSize($width, $height);

        // Generate realistic metadata
        $metadata = $this->generatePhotoMetadata($photoType);
        
        // Determine moderation results
        $moderationData = $this->generateModerationData();

        return [
            'user_id' => User::factory(),
            
            // File Information
            'filename' => $filename,
            'path' => "photos/users/{$this->faker->uuid()}/{$filename}",
            'url' => "https://cdn.foreverusinlove.com/photos/users/{$this->faker->uuid()}/{$filename}",
            'thumbnail_path' => "photos/thumbnails/{$this->faker->uuid()}/thumb_{$filename}",
            'thumbnail_url' => "https://cdn.foreverusinlove.com/photos/thumbnails/{$this->faker->uuid()}/thumb_{$filename}",
            
            // Photo Properties
            'is_primary' => false, // Will be set by model boot method
            'order' => $this->faker->numberBetween(0, 8),
            'width' => $width,
            'height' => $height,
            'size_bytes' => $sizeBytes,
            'mime_type' => $this->faker->randomElement([
                'image/jpeg', 'image/jpg', 'image/png', 'image/webp'
            ]),
            
            // Moderation & Status
            'status' => $moderationData['status'],
            'rejection_reason' => $moderationData['rejection_reason'],
            'moderation_results' => $moderationData['results'],
            'moderation_score' => $moderationData['score'],
            'moderated_at' => $moderationData['moderated_at'],
            'moderated_by_user_id' => $moderationData['moderated_by'],
            
            // Metadata & Analytics
            'metadata' => $metadata,
            'views_count' => $this->faker->numberBetween(0, 2500),
            'likes_count' => $this->faker->numberBetween(0, 500),
            
            // Timestamps
            'created_at' => $this->faker->dateTimeBetween('-2 years', '-1 day'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Generate realistic photo dimensions based on type
     */
    private function generateRealisticDimensions(string $photoType): array
    {
        return match($photoType) {
            'portrait' => [
                $this->faker->numberBetween(800, 1200),    // width
                $this->faker->numberBetween(1000, 1600)    // height (portrait)
            ],
            'full_body' => [
                $this->faker->numberBetween(900, 1400),
                $this->faker->numberBetween(1200, 2000)
            ],
            'lifestyle', 'social' => [
                $this->faker->numberBetween(1200, 2000),   // width (landscape)
                $this->faker->numberBetween(800, 1400)     // height
            ],
            'professional' => [
                $this->faker->numberBetween(1000, 1600),
                $this->faker->numberBetween(1200, 1800)
            ],
            default => [
                $this->faker->numberBetween(1000, 1600),
                $this->faker->numberBetween(1000, 1600)    // square-ish
            ]
        };
    }

    /**
     * Calculate realistic file size based on dimensions
     */
    private function calculateFileSize(int $width, int $height): int
    {
        $pixels = $width * $height;
        $bytesPerPixel = $this->faker->randomFloat(2, 0.5, 2.0); // Compression ratio
        return (int) ($pixels * $bytesPerPixel);
    }

    /**
     * Generate comprehensive photo metadata
     */
    private function generatePhotoMetadata(): array
    {
        return [
            // Camera/Device Info
            'device' => $this->faker->randomElement(self::$deviceModels),
            'camera_make' => $this->faker->randomElement(['Apple', 'Samsung', 'Google', 'Canon', 'Sony']),
            'camera_model' => $this->faker->randomElement(['iPhone Camera', 'Galaxy Camera', 'Professional DSLR']),
            
            // Technical Details
            'iso' => $this->faker->randomElement([100, 200, 400, 800, 1600]),
            'aperture' => $this->faker->randomElement(['f/1.8', 'f/2.4', 'f/2.8', 'f/4.0']),
            'shutter_speed' => $this->faker->randomElement(['1/60', '1/120', '1/250', '1/500']),
            'focal_length' => $this->faker->numberBetween(24, 85) . 'mm',
            
            // Location & Context
            'location_type' => $this->faker->randomElement(self::$locations),
            'lighting_condition' => $this->faker->randomElement([
                'natural_light', 'indoor_lighting', 'flash', 'golden_hour', 'studio_lighting'
            ]),
            'time_of_day' => $this->faker->randomElement(['morning', 'afternoon', 'evening', 'night']),
            
            // Photo Analysis
            'face_detected' => $this->faker->boolean(85),
            'face_count' => $this->faker->numberBetween(1, 3),
            'smile_detected' => $this->faker->boolean(70),
            'eyes_open' => $this->faker->boolean(90),
            'photo_quality_score' => $this->faker->numberBetween(60, 95),
            
            // Content Analysis
            'contains_text' => $this->faker->boolean(10),
            'background_type' => $this->faker->randomElement([
                'plain', 'nature', 'urban', 'indoor', 'blurred', 'complex'
            ]),
            'colors_dominant' => $this->faker->randomElements([
                'blue', 'green', 'red', 'yellow', 'black', 'white', 'brown'
            ], $this->faker->numberBetween(2, 4)),
            
            // Processing Info
            'edited' => $this->faker->boolean(40),
            'filters_applied' => $this->faker->boolean(35),
            'brightness_adjusted' => $this->faker->boolean(25),
            'contrast_adjusted' => $this->faker->boolean(20),
            
            // Upload Info
            'upload_source' => $this->faker->randomElement([
                'mobile_app', 'web_browser', 'desktop_app'
            ]),
            'original_filename' => 'IMG_' . $this->faker->numberBetween(1000, 9999) . '.jpg',
            'compression_applied' => $this->faker->boolean(80),
            'resize_applied' => $this->faker->boolean(60),
        ];
    }

    /**
     * Generate realistic moderation data
     */
    private function generateModerationData(): array
    {
        $score = $this->faker->randomFloat(3, 0.0, 1.0);
        
        // Determine status based on score
        if ($score >= 0.9) {
            $status = 'active';
            $rejectionReason = null;
            $moderatedBy = null;
        } elseif ($score >= 0.7) {
            $status = $this->faker->randomElement(['active', 'pending_moderation']);
            $rejectionReason = null;
            $moderatedBy = $status === 'active' ? $this->faker->numberBetween(1, 50) : null;
        } elseif ($score >= 0.5) {
            $status = $this->faker->randomElement(['pending_moderation', 'rejected']);
            $rejectionReason = $status === 'rejected' ? $this->faker->randomElement(self::$rejectionReasons) : null;
            $moderatedBy = $status === 'rejected' ? $this->faker->numberBetween(1, 50) : null;
        } else {
            $status = 'rejected';
            $rejectionReason = $this->faker->randomElement(self::$rejectionReasons);
            $moderatedBy = $this->faker->numberBetween(1, 50);
        }

        return [
            'status' => $status,
            'rejection_reason' => $rejectionReason,
            'score' => $score,
            'moderated_at' => $status !== 'pending_moderation' ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'moderated_by' => $moderatedBy,
            'results' => [
                // AI Moderation Results
                'adult_content' => [
                    'score' => $this->faker->randomFloat(3, 0.0, 0.3),
                    'detected' => $this->faker->boolean(5),
                ],
                'violence' => [
                    'score' => $this->faker->randomFloat(3, 0.0, 0.1),
                    'detected' => $this->faker->boolean(2),
                ],
                'face_detection' => [
                    'faces_count' => $this->faker->numberBetween(1, 3),
                    'main_face_confidence' => $this->faker->randomFloat(3, 0.7, 1.0),
                    'face_attributes' => [
                        'age_estimate' => $this->faker->numberBetween(18, 65),
                        'gender_confidence' => $this->faker->randomFloat(3, 0.8, 1.0),
                        'emotion_detected' => $this->faker->randomElement([
                            'happy', 'neutral', 'serious', 'smiling'
                        ]),
                    ],
                ],
                'quality_assessment' => [
                    'sharpness' => $this->faker->randomFloat(3, 0.4, 1.0),
                    'brightness' => $this->faker->randomFloat(3, 0.3, 1.0),
                    'contrast' => $this->faker->randomFloat(3, 0.3, 1.0),
                    'overall_quality' => $score,
                ],
                'content_analysis' => [
                    'contains_text' => $this->faker->boolean(10),
                    'watermark_detected' => $this->faker->boolean(5),
                    'brand_logos' => $this->faker->boolean(15),
                    'multiple_people' => $this->faker->boolean(20),
                ],
                'safety_check' => [
                    'safe_for_dating' => $score > 0.6,
                    'appropriate_clothing' => $score > 0.5,
                    'clear_face_visible' => $score > 0.7,
                    'professional_appropriate' => $score > 0.8,
                ],
                'moderation_flags' => $this->generateModerationFlags($score),
            ],
        ];
    }

    /**
     * Generate moderation flags based on score
     */
    private function generateModerationFlags(float $score): array
    {
        $flags = [];
        
        if ($score < 0.3) {
            $flags[] = 'inappropriate_content';
        }
        if ($score < 0.4) {
            $flags[] = 'poor_quality';
        }
        if ($score < 0.5 && $this->faker->boolean(30)) {
            $flags[] = 'face_not_visible';
        }
        if ($this->faker->boolean(10)) {
            $flags[] = 'needs_human_review';
        }
        if ($score > 0.95) {
            $flags[] = 'auto_approved';
        }

        return $flags;
    }

    /**
     * Factory State: Primary Photo
     */
    public function primary(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_primary' => true,
                'order' => 0,
                'status' => 'active',
                'moderation_score' => $this->faker->randomFloat(3, 0.8, 1.0),
                'views_count' => $this->faker->numberBetween(100, 5000),
                'likes_count' => $this->faker->numberBetween(20, 800),
            ];
        });
    }

    /**
     * Factory State: Approved Photo
     */
    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'rejection_reason' => null,
                'moderation_score' => $this->faker->randomFloat(3, 0.7, 1.0),
                'moderated_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
                'moderated_by_user_id' => $this->faker->numberBetween(1, 50),
            ];
        });
    }

    /**
     * Factory State: Pending Moderation
     */
    public function pendingModeration(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending_moderation',
                'rejection_reason' => null,
                'moderation_score' => $this->faker->randomFloat(3, 0.5, 0.8),
                'moderated_at' => null,
                'moderated_by_user_id' => null,
                'views_count' => 0,
                'likes_count' => 0,
            ];
        });
    }

    /**
     * Factory State: Rejected Photo
     */
    public function rejected(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'rejected',
                'rejection_reason' => $this->faker->randomElement(self::$rejectionReasons),
                'moderation_score' => $this->faker->randomFloat(3, 0.0, 0.5),
                'moderated_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
                'moderated_by_user_id' => $this->faker->numberBetween(1, 50),
                'views_count' => 0,
                'likes_count' => 0,
            ];
        });
    }

    /**
     * Factory State: High Quality Photo
     */
    public function highQuality(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'width' => $this->faker->numberBetween(1500, 3000),
                'height' => $this->faker->numberBetween(2000, 4000),
                'size_bytes' => $this->faker->numberBetween(2000000, 8000000), // 2-8MB
                'moderation_score' => $this->faker->randomFloat(3, 0.9, 1.0),
                'status' => 'active',
                'metadata' => array_merge($this->generatePhotoMetadata(), [
                    'photo_quality_score' => $this->faker->numberBetween(90, 100),
                    'device' => $this->faker->randomElement(['DSLR Canon EOS R5', 'Sony A7 IV']),
                    'professional_grade' => true,
                ]),
            ];
        });
    }

    /**
     * Factory State: Portrait Photo
     */
    public function portrait(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'width' => $this->faker->numberBetween(800, 1200),
                'height' => $this->faker->numberBetween(1200, 1800),
                'metadata' => array_merge($this->generatePhotoMetadata(), [
                    'face_detected' => true,
                    'face_count' => 1,
                    'smile_detected' => $this->faker->boolean(80),
                    'eyes_open' => true,
                    'background_type' => 'plain',
                ]),
            ];
        });
    }

    /**
     * Factory State: Full Body Photo
     */
    public function fullBody(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'width' => $this->faker->numberBetween(900, 1400),
                'height' => $this->faker->numberBetween(1600, 2400),
                'metadata' => array_merge($this->generatePhotoMetadata(), [
                    'location_type' => $this->faker->randomElement(['outdoors', 'studio', 'event']),
                    'lighting_condition' => 'natural_light',
                    'background_type' => $this->faker->randomElement(['nature', 'urban', 'plain']),
                ]),
            ];
        });
    }

    /**
     * Factory State: Activity Photo
     */
    public function activity(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'metadata' => array_merge($this->generatePhotoMetadata(), [
                    'location_type' => $this->faker->randomElement(['outdoors', 'gym', 'travel_destination']),
                    'background_type' => $this->faker->randomElement(['nature', 'complex', 'urban']),
                    'activity_detected' => true,
                    'activity_type' => $this->faker->randomElement([
                        'hiking', 'travel', 'sports', 'fitness', 'adventure'
                    ]),
                ]),
            ];
        });
    }

    /**
     * Factory State: Professional Photo
     */
    public function professional(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'moderation_score' => $this->faker->randomFloat(3, 0.85, 1.0),
                'metadata' => array_merge($this->generatePhotoMetadata(), [
                    'location_type' => $this->faker->randomElement(['studio', 'office', 'professional']),
                    'lighting_condition' => 'studio_lighting',
                    'background_type' => 'plain',
                    'professional_grade' => true,
                    'edited' => true,
                    'photo_quality_score' => $this->faker->numberBetween(85, 100),
                ]),
            ];
        });
    }

    /**
     * Factory State: Popular Photo (high engagement)
     */
    public function popular(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'views_count' => $this->faker->numberBetween(1000, 10000),
                'likes_count' => $this->faker->numberBetween(200, 2000),
                'moderation_score' => $this->faker->randomFloat(3, 0.9, 1.0),
                'metadata' => array_merge($this->generatePhotoMetadata(), [
                    'photo_quality_score' => $this->faker->numberBetween(85, 100),
                    'smile_detected' => true,
                    'eyes_open' => true,
                ]),
            ];
        });
    }
}