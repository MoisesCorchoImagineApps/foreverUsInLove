<?php

namespace Database\Factories\Profile;

use App\Models\Profile\Profile;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * ProfileFactory - Factory for Profile Model
 * 
 * Generates comprehensive demographic and matching preference data
 * Handles all User model relationships and business logic
 * Supports realistic dating app profile scenarios
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    // Realistic location data for international dating app
    private static $cities = [
        'US' => [
            ['New York', 'NY', 40.7128, -74.0060],
            ['Los Angeles', 'CA', 34.0522, -118.2437],
            ['Chicago', 'IL', 41.8781, -87.6298],
            ['Houston', 'TX', 29.7604, -95.3698],
            ['Phoenix', 'AZ', 33.4484, -112.0740],
            ['Philadelphia', 'PA', 39.9526, -75.1652],
            ['San Antonio', 'TX', 29.4241, -98.4936],
            ['San Diego', 'CA', 32.7157, -117.1611],
            ['Dallas', 'TX', 32.7767, -96.7970],
            ['Austin', 'TX', 30.2672, -97.7431],
        ],
        'CA' => [
            ['Toronto', 'ON', 43.6532, -79.3832],
            ['Vancouver', 'BC', 49.2827, -123.1207],
            ['Montreal', 'QC', 45.5017, -73.5673],
            ['Calgary', 'AB', 51.0447, -114.0719],
            ['Ottawa', 'ON', 45.4215, -75.6972],
        ],
        'GB' => [
            ['London', 'ENG', 51.5074, -0.1278],
            ['Birmingham', 'ENG', 52.4862, -1.8904],
            ['Manchester', 'ENG', 53.4808, -2.2426],
            ['Glasgow', 'SCT', 55.8642, -4.2518],
            ['Liverpool', 'ENG', 53.4084, -2.9916],
        ],
        'AU' => [
            ['Sydney', 'NSW', -33.8688, 151.2093],
            ['Melbourne', 'VIC', -37.8136, 144.9631],
            ['Brisbane', 'QLD', -27.4698, 153.0251],
            ['Perth', 'WA', -31.9505, 115.8605],
            ['Adelaide', 'SA', -34.9285, 138.6007],
        ],
    ];

    private static $interests = [
        // Lifestyle & Hobbies
        'travel', 'photography', 'hiking', 'cooking', 'reading', 'music', 'movies', 'art', 'dancing',
        'yoga', 'meditation', 'fitness', 'running', 'cycling', 'swimming', 'rock_climbing', 'surfing',
        'skiing', 'snowboarding', 'camping', 'fishing', 'gardening', 'painting', 'writing', 'blogging',
        
        // Professional & Academic
        'entrepreneurship', 'technology', 'science', 'medicine', 'law', 'education', 'research',
        'marketing', 'design', 'architecture', 'engineering', 'finance', 'consulting', 'startup',
        
        // Social & Cultural
        'volunteering', 'politics', 'environment', 'social_justice', 'religion', 'spirituality',
        'philosophy', 'history', 'languages', 'cultural_events', 'festivals', 'wine_tasting',
        'foodie', 'craft_beer', 'coffee', 'tea',
        
        // Entertainment & Games
        'gaming', 'board_games', 'karaoke', 'comedy', 'theater', 'concerts', 'sports', 'football',
        'basketball', 'baseball', 'soccer', 'tennis', 'golf', 'martial_arts', 'boxing', 'crossfit',
        
        // Creative & Artistic
        'music_production', 'djing', 'singing', 'acting', 'modeling', 'fashion', 'jewelry_making',
        'pottery', 'woodworking', 'crafts', 'knitting', 'sewing', 'interior_design', 'collecting'
    ];

    private static $values = [
        'family', 'career', 'health', 'friendship', 'love', 'adventure', 'security', 'creativity',
        'independence', 'honesty', 'loyalty', 'ambition', 'kindness', 'humor', 'intelligence',
        'spirituality', 'tradition', 'innovation', 'justice', 'freedom', 'stability', 'growth',
        'authenticity', 'compassion', 'respect', 'integrity', 'balance', 'passion', 'wisdom'
    ];

    public function definition(): array
    {
        $country = $this->faker->randomElement(['US', 'CA', 'GB', 'AU']);
        $cityData = $this->faker->randomElement(self::$cities[$country]);
        [$city, $state, $lat, $lng] = $cityData;
        
        // Calculate age from birth_date for consistency
        $birthDate = $this->faker->dateTimeBetween('-65 years', '-18 years');
        $age = Carbon::parse($birthDate)->age;
        
        // Generate realistic height based on gender demographics
        $gender = $this->faker->randomElement(['male', 'female', 'non_binary']);
        $height = $this->generateRealisticHeight($gender);
        
        // Generate education level with realistic distribution
        $educationLevel = $this->faker->randomElement([
            'high_school' => 25,
            'some_college' => 15,
            'bachelor' => 35,
            'master' => 20,
            'doctorate' => 5
        ]);

        return [
            'user_id' => User::factory(),
            
            // Basic Demographics
            'birth_date' => $birthDate,
            'age' => $age,
            'gender' => $gender,
            'height_cm' => $height,
            'height_ft_in' => $this->convertToFeetInches($height),
            
            // Location Data
            'country' => $country,
            'state_province' => $state,
            'city' => $city,
            'latitude' => $lat + $this->faker->randomFloat(4, -0.1, 0.1), // Add some variance
            'longitude' => $lng + $this->faker->randomFloat(4, -0.1, 0.1),
            'location_visible' => $this->faker->boolean(85), // Most users show location
            'max_distance_km' => $this->faker->randomElement([5, 10, 25, 50, 100, 250, 500]),
            
            // Professional Information
            'occupation' => $this->faker->jobTitle(),
            'company' => $this->faker->optional(0.7)->company(),
            'education_level' => $educationLevel,
            'school' => $this->faker->optional(0.6)->randomElement([
                'Harvard University', 'Stanford University', 'MIT', 'Oxford University',
                'Cambridge University', 'University of Toronto', 'UCLA', 'NYU',
                'University of Sydney', 'Local Community College', 'State University'
            ]),
            'annual_income_range' => $this->generateIncomeRange($educationLevel, $age),
            
            // Physical Attributes
            'ethnicity' => $this->faker->randomElement([
                'caucasian', 'african_american', 'hispanic', 'asian', 'middle_eastern',
                'native_american', 'pacific_islander', 'mixed', 'other', 'prefer_not_say'
            ]),
            'body_type' => $this->faker->randomElement([
                'slim', 'athletic', 'average', 'curvy', 'muscular', 'plus_size', 'prefer_not_say'
            ]),
            'eye_color' => $this->faker->randomElement([
                'brown', 'blue', 'green', 'hazel', 'gray', 'amber', 'other'
            ]),
            'hair_color' => $this->faker->randomElement([
                'black', 'brown', 'blonde', 'red', 'gray', 'bald', 'other'
            ]),
            
            // Lifestyle Preferences
            'relationship_type' => $this->faker->randomElement([
                'serious', 'casual', 'friendship', 'open_to_both', 'marriage', 'hookup'
            ]),
            'smoking' => $this->faker->randomElement(['never', 'socially', 'regularly', 'trying_to_quit']),
            'drinking' => $this->faker->randomElement(['never', 'socially', 'regularly', 'frequently']),
            'drugs' => $this->faker->randomElement(['never', 'socially', 'regularly', 'medical_only']),
            'exercise_frequency' => $this->faker->randomElement([
                'never', 'rarely', 'sometimes', 'regularly', 'daily', 'athlete'
            ]),
            'diet' => $this->faker->randomElement([
                'omnivore', 'vegetarian', 'vegan', 'pescatarian', 'keto', 'paleo', 'other'
            ]),
            
            // Family & Relationship Preferences
            'has_children' => $this->faker->boolean(35),
            'wants_children' => $this->faker->randomElement([
                'yes', 'no', 'maybe', 'open_to_it', 'have_and_want_more', 'have_and_dont_want_more'
            ]),
            'family_plans' => $this->faker->randomElement([
                'want_many', 'want_few', 'undecided', 'no_kids', 'adoption_open', 'step_kids_ok'
            ]),
            
            // Religious & Political Views
            'religion' => $this->faker->randomElement([
                'christian', 'catholic', 'jewish', 'muslim', 'hindu', 'buddhist', 'atheist',
                'agnostic', 'spiritual', 'other', 'prefer_not_say'
            ]),
            'religion_importance' => $this->faker->randomElement([
                'very_important', 'somewhat_important', 'not_important', 'prefer_not_say'
            ]),
            'political_views' => $this->faker->randomElement([
                'liberal', 'conservative', 'moderate', 'libertarian', 'progressive',
                'independent', 'apolitical', 'prefer_not_say'
            ]),
            
            // Profile Content
            'bio' => $this->generateRealisticBio($gender, $age, $educationLevel),
            'looking_for' => $this->generateLookingFor(),
            'interests' => json_encode($this->faker->randomElements(
                self::$interests, 
                $this->faker->numberBetween(3, 12)
            )),
            'values' => json_encode($this->faker->randomElements(
                self::$values, 
                $this->faker->numberBetween(3, 8)
            )),
            
            // Matching Preferences
            'age_range_min' => max(18, $age - $this->faker->numberBetween(5, 15)),
            'age_range_max' => min(80, $age + $this->faker->numberBetween(5, 20)),
            'preferred_gender' => $this->faker->randomElement([
                'male', 'female', 'non_binary', 'any', 'male_and_female'
            ]),
            'preferred_relationship_type' => $this->faker->randomElement([
                'serious', 'casual', 'friendship', 'any', 'marriage_minded'
            ]),
            'dealbreakers' => json_encode($this->generateDealbreakers()),
            
            // Social Media & Verification
            'instagram_username' => $this->faker->optional(0.6)->userName(),
            'spotify_connected' => $this->faker->boolean(40),
            'facebook_connected' => $this->faker->boolean(25),
            'linkedin_connected' => $this->faker->boolean(30),
            'verified_education' => $this->faker->boolean(15),
            'verified_occupation' => $this->faker->boolean(20),
            
            // Profile Metrics
            'profile_completeness' => $this->faker->numberBetween(60, 100),
            'last_active_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'visibility_status' => $this->faker->randomElement([
                'public', 'limited', 'private', 'hidden', 'boost'
            ]),
            'boost_expires_at' => $this->faker->optional(0.1)->dateTimeBetween('now', '+7 days'),
            
            // Premium Features
            'is_premium' => $this->faker->boolean(15),
            'premium_expires_at' => $this->faker->optional(0.15)->dateTimeBetween('now', '+1 year'),
            'super_likes_remaining' => $this->faker->numberBetween(0, 5),
            'boosts_remaining' => $this->faker->numberBetween(0, 3),
            
            // Matching Algorithm Data
            'matching_algorithm_version' => $this->faker->randomElement(['v1.0', 'v1.1', 'v2.0', 'v2.1']),
            'compatibility_scores' => json_encode([
                'personality' => $this->faker->numberBetween(60, 95),
                'lifestyle' => $this->faker->numberBetween(50, 90),
                'values' => $this->faker->numberBetween(70, 100),
                'interests' => $this->faker->numberBetween(40, 85)
            ]),
            'search_preferences' => json_encode([
                'distance_weight' => $this->faker->randomFloat(2, 0.1, 1.0),
                'age_weight' => $this->faker->randomFloat(2, 0.1, 1.0),
                'compatibility_weight' => $this->faker->randomFloat(2, 0.5, 1.0),
                'activity_weight' => $this->faker->randomFloat(2, 0.1, 0.8)
            ]),
            
            // Timestamps
            'created_at' => $this->faker->dateTimeBetween('-2 years', '-1 day'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Generate realistic height based on gender demographics
     */
    private function generateRealisticHeight(string $gender): int
    {
        switch ($gender) {
            case 'male':
                // Male average: 175cm (5'9"), range: 160-200cm
                return $this->faker->numberBetween(160, 200);
            case 'female':
                // Female average: 162cm (5'4"), range: 150-185cm
                return $this->faker->numberBetween(150, 185);
            default:
                // Non-binary: broader range
                return $this->faker->numberBetween(150, 200);
        }
    }

    /**
     * Convert height from cm to feet/inches format
     */
    private function convertToFeetInches(int $heightCm): string
    {
        $inches = round($heightCm / 2.54);
        $feet = intval($inches / 12);
        $remainingInches = $inches % 12;
        return "{$feet}'{$remainingInches}\"";
    }

    /**
     * Generate realistic income range based on education and age
     */
    private function generateIncomeRange(string $education, int $age): string
    {
        $baseRanges = [
            'high_school' => ['25000-35000', '35000-45000', '45000-55000'],
            'some_college' => ['30000-40000', '40000-50000', '50000-65000'],
            'bachelor' => ['45000-60000', '60000-80000', '80000-100000', '100000-150000'],
            'master' => ['60000-80000', '80000-120000', '120000-180000', '180000+'],
            'doctorate' => ['80000-120000', '120000-180000', '180000-250000', '250000+']
        ];
        
        // Adjust for age (career progression)
        $ageMultiplier = min(1.5, 1 + ($age - 22) * 0.02); // Cap growth
        $ranges = $baseRanges[$education] ?? $baseRanges['bachelor'];
        
        if ($age > 40 && count($ranges) > 2) {
            // Older professionals tend toward higher ranges
            return $this->faker->randomElement(array_slice($ranges, -2));
        }
        
        return $this->faker->randomElement($ranges);
    }

    /**
     * Generate realistic bio based on demographics
     */
    private function generateRealisticBio(string $gender, int $age, string $education): string
    {
        $bioTemplates = [
            "Adventure seeker who loves {activity} and {hobby}. Looking for someone to explore {location_type} with! {personality_trait}",
            "{profession} by day, {hobby} enthusiast by night. Love {activity} and good {food_drink}. {relationship_goal}",
            "Life's too short for {dislike}. Passionate about {hobby} and {cause}. Let's {activity} together!",
            "{personality_trait} {profession} who enjoys {hobby}, {activity}, and {entertainment}. {relationship_goal}",
            "Recently moved to {city_mention} and loving it! Into {hobby}, {activity}, and {interest}. {personality_trait}"
        ];

        $activities = ['hiking', 'traveling', 'cooking', 'dancing', 'rock climbing', 'surfing', 'photography'];
        $hobbies = ['yoga', 'reading', 'music', 'art', 'gaming', 'fitness', 'gardening'];
        $locations = ['new cities', 'hidden gems', 'local spots', 'nature', 'the outdoors'];
        $personality = ['Always laughing and making others smile', 'Genuine and down-to-earth', 'Optimistic and adventurous'];
        $goals = ['Looking for something real', 'Here for meaningful connections', 'Ready for the next chapter'];
        
        $template = $this->faker->randomElement($bioTemplates);
        
        return strtr($template, [
            '{activity}' => $this->faker->randomElement($activities),
            '{hobby}' => $this->faker->randomElement($hobbies),
            '{location_type}' => $this->faker->randomElement($locations),
            '{personality_trait}' => $this->faker->randomElement($personality),
            '{profession}' => strtolower($this->faker->jobTitle()),
            '{food_drink}' => $this->faker->randomElement(['wine', 'coffee', 'food', 'craft beer']),
            '{relationship_goal}' => $this->faker->randomElement($goals),
            '{dislike}' => $this->faker->randomElement(['drama', 'negativity', 'small talk']),
            '{cause}' => $this->faker->randomElement(['environmental causes', 'social justice', 'animal welfare']),
            '{entertainment}' => $this->faker->randomElement(['live music', 'movies', 'comedy shows']),
            '{city_mention}' => 'the city',
            '{interest}' => $this->faker->randomElement(['good conversation', 'new experiences', 'learning'])
        ]);
    }

    /**
     * Generate what user is looking for
     */
    private function generateLookingFor(): string
    {
        $lookingForOptions = [
            "Someone genuine who shares my love for adventure and good conversation.",
            "A partner in crime for life's adventures. Must love {interest} and have a good sense of humor!",
            "Looking for someone who values {value} and enjoys {activity}. Bonus points for {bonus}!",
            "Seeking a connection with someone who's {trait} and loves {hobby}.",
            "Someone who can make me laugh and isn't afraid to {activity}. Let's see where it goes!",
            "A genuine person who values {value} and enjoys {interest}. Chemistry is everything!"
        ];

        $template = $this->faker->randomElement($lookingForOptions);
        
        return strtr($template, [
            '{interest}' => $this->faker->randomElement(['travel', 'good food', 'music', 'art', 'nature']),
            '{value}' => $this->faker->randomElement(['honesty', 'kindness', 'family', 'growth', 'adventure']),
            '{activity}' => $this->faker->randomElement(['try new things', 'explore the city', 'be spontaneous']),
            '{trait}' => $this->faker->randomElement(['ambitious', 'kind-hearted', 'adventurous', 'genuine']),
            '{hobby}' => $this->faker->randomElement(['hiking', 'cooking', 'reading', 'traveling']),
            '{bonus}' => $this->faker->randomElement(['dog lover', 'good cook', 'travel stories', 'dad jokes'])
        ]);
    }

    /**
     * Generate realistic dealbreakers
     */
    private function generateDealbreakers(): array
    {
        $possibleDealbreakers = [
            'smoking', 'excessive_drinking', 'drugs', 'dishonesty', 'no_ambition',
            'poor_hygiene', 'rude_to_service_staff', 'constantly_negative', 
            'no_sense_of_humor', 'different_life_goals', 'incompatible_values',
            'emotional_unavailability', 'poor_communication', 'infidelity',
            'financial_irresponsibility', 'extreme_political_views'
        ];

        return $this->faker->randomElements(
            $possibleDealbreakers, 
            $this->faker->numberBetween(2, 6)
        );
    }

    /**
     * Factory State: Young Professional (22-30)
     */
    public function youngProfessional(): static
    {
        return $this->state(function (array $attributes) {
            $age = $this->faker->numberBetween(22, 30);
            return [
                'birth_date' => Carbon::now()->subYears($age),
                'age' => $age,
                'education_level' => $this->faker->randomElement(['bachelor', 'master']),
                'annual_income_range' => $this->faker->randomElement([
                    '45000-60000', '60000-80000', '80000-100000'
                ]),
                'relationship_type' => $this->faker->randomElement(['casual', 'serious', 'open_to_both']),
                'has_children' => false,
                'wants_children' => $this->faker->randomElement(['yes', 'maybe', 'undecided']),
            ];
        });
    }

    /**
     * Factory State: Established Professional (30-45)
     */
    public function establishedProfessional(): static
    {
        return $this->state(function (array $attributes) {
            $age = $this->faker->numberBetween(30, 45);
            return [
                'birth_date' => Carbon::now()->subYears($age),
                'age' => $age,
                'education_level' => $this->faker->randomElement(['bachelor', 'master', 'doctorate']),
                'annual_income_range' => $this->faker->randomElement([
                    '80000-120000', '120000-180000', '180000+'
                ]),
                'relationship_type' => $this->faker->randomElement(['serious', 'marriage']),
                'has_children' => $this->faker->boolean(60),
                'is_premium' => $this->faker->boolean(35),
                'profile_completeness' => $this->faker->numberBetween(85, 100),
            ];
        });
    }

    /**
     * Factory State: Premium User
     */
    public function premium(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_premium' => true,
                'premium_expires_at' => $this->faker->dateTimeBetween('now', '+1 year'),
                'super_likes_remaining' => 5,
                'boosts_remaining' => 3,
                'profile_completeness' => $this->faker->numberBetween(90, 100),
                'verified_education' => $this->faker->boolean(70),
                'verified_occupation' => $this->faker->boolean(80),
            ];
        });
    }

    /**
     * Factory State: Highly Compatible (for testing matching)
     */
    public function highlyCompatible(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'compatibility_scores' => json_encode([
                    'personality' => $this->faker->numberBetween(85, 100),
                    'lifestyle' => $this->faker->numberBetween(80, 95),
                    'values' => $this->faker->numberBetween(90, 100),
                    'interests' => $this->faker->numberBetween(75, 90)
                ]),
                'profile_completeness' => $this->faker->numberBetween(95, 100),
                'last_active_at' => $this->faker->dateTimeBetween('-3 days', 'now'),
            ];
        });
    }

    /**
     * Factory State: Recently Active
     */
    public function recentlyActive(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'last_active_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
                'visibility_status' => 'public',
            ];
        });
    }

    /**
     * Factory State: Verified Profile
     */
    public function verified(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'verified_education' => true,
                'verified_occupation' => true,
                'instagram_username' => $this->faker->userName(),
                'linkedin_connected' => true,
                'profile_completeness' => 100,
            ];
        });
    }

    /**
     * Factory State: International User
     */
    public function international(): static
    {
        return $this->state(function (array $attributes) {
            $countries = ['FR', 'DE', 'IT', 'ES', 'BR', 'JP', 'KR', 'IN', 'MX'];
            $country = $this->faker->randomElement($countries);
            
            return [
                'country' => $country,
                'city' => $this->faker->city(),
                'state_province' => $this->faker->state(),
                'latitude' => $this->faker->latitude(),
                'longitude' => $this->faker->longitude(),
            ];
        });
    }
}