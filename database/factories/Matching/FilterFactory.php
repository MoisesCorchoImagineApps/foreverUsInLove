<?php

namespace Database\Factories;

use App\Models\Filter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FilterFactory extends Factory
{
    protected $model = Filter::class;

    public function definition(): array
    {
        $categories = ['basic', 'demographic', 'lifestyle', 'interest', 'relationship', 'premium', 'behavioral', 'custom'];
        $category = $this->faker->randomElement($categories);
        
        return [
            'id' => Str::uuid(),
            'user_id' => User::factory(),
            'name' => $this->generateFilterName($category),
            'category' => $category,
            'criteria' => $this->generateCriteria($category),
            'weights' => $this->generateWeights($category),
            'is_active' => $this->faker->boolean(85),
            'is_default' => $this->faker->boolean(15),
            'priority_order' => $this->faker->numberBetween(1, 10),
            'usage_count' => $this->faker->numberBetween(0, 500),
            'match_success_rate' => $this->faker->numberBetween(5, 95),
            'last_used_at' => $this->faker->optional(0.8)->dateTimeBetween('-30 days', 'now'),
            'settings' => $this->generateSettings($category),
            'compatibility_factors' => $this->generateCompatibilityFactors($category),
            'exclusion_rules' => $this->generateExclusionRules($category),
            'advanced_options' => $this->generateAdvancedOptions($category),
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    private function generateFilterName(string $category): string
    {
        $names = [
            'basic' => [
                'Búsqueda Básica', 'Filtro Estándar', 'Preferencias Generales', 'Búsqueda Simple',
                'Filtro Principal', 'Configuración Base', 'Búsqueda Rápida'
            ],
            'demographic' => [
                'Perfil Demográfico', 'Características Básicas', 'Datos Personales', 
                'Información Demográfica', 'Perfil Estadístico', 'Demografía Avanzada'
            ],
            'lifestyle' => [
                'Estilo de Vida', 'Hábitos y Costumbres', 'Preferencias de Vida', 
                'Lifestyle Match', 'Compatibilidad de Vida', 'Rutinas Compartidas'
            ],
            'interest' => [
                'Intereses Comunes', 'Hobbies Compartidos', 'Pasiones Similares', 
                'Actividades Favoritas', 'Gustos Compatibles', 'Aficiones Mutuas'
            ],
            'relationship' => [
                'Tipo de Relación', 'Intenciones Románticas', 'Objetivos de Pareja', 
                'Expectativas Amorosas', 'Compromiso Deseado', 'Metas Relacionales'
            ],
            'premium' => [
                'Filtros Premium', 'Búsqueda Avanzada', 'Criterios Exclusivos', 
                'Matching Profesional', 'Filtros VIP', 'Selección Premium'
            ],
            'behavioral' => [
                'Patrones de Comportamiento', 'Actividad en App', 'Comportamiento Online', 
                'Uso de Plataforma', 'Engagement Score', 'Actividad Reciente'
            ],
            'custom' => [
                'Filtro Personalizado', 'Criterios Únicos', 'Búsqueda Especial', 
                'Preferencias Específicas', 'Filtro a Medida', 'Configuración Personal'
            ]
        ];

        return $this->faker->randomElement($names[$category]);
    }

    private function generateCriteria(string $category): array
    {
        switch ($category) {
            case 'basic':
                return [
                    'age_min' => $this->faker->numberBetween(18, 25),
                    'age_max' => $this->faker->numberBetween(26, 50),
                    'distance_max' => $this->faker->numberBetween(5, 100),
                    'gender_preference' => $this->faker->randomElement(['men', 'women', 'both', 'non_binary']),
                    'show_me' => $this->faker->randomElement(['everyone', 'verified_only', 'active_recently']),
                ];

            case 'demographic':
                return [
                    'education_level' => $this->faker->randomElements([
                        'high_school', 'some_college', 'bachelor', 'master', 'phd', 'trade_school'
                    ], rand(1, 3)),
                    'height_min' => $this->faker->numberBetween(150, 170),
                    'height_max' => $this->faker->numberBetween(171, 200),
                    'ethnicity' => $this->faker->optional()->randomElements([
                        'caucasian', 'hispanic', 'african', 'asian', 'middle_eastern', 'mixed', 'other'
                    ], rand(1, 4)),
                    'languages' => $this->faker->randomElements([
                        'spanish', 'english', 'french', 'portuguese', 'italian', 'german', 'mandarin'
                    ], rand(1, 3)),
                    'occupation_category' => $this->faker->optional()->randomElements([
                        'healthcare', 'technology', 'education', 'business', 'creative', 'service', 'other'
                    ], rand(1, 2)),
                ];

            case 'lifestyle':
                return [
                    'smoking_preference' => $this->faker->randomElement(['never', 'occasionally', 'regularly', 'no_preference']),
                    'drinking_preference' => $this->faker->randomElement(['never', 'socially', 'regularly', 'no_preference']),
                    'exercise_frequency' => $this->faker->randomElement(['never', 'rarely', 'sometimes', 'regularly', 'daily']),
                    'diet_type' => $this->faker->optional()->randomElements([
                        'omnivore', 'vegetarian', 'vegan', 'pescatarian', 'keto', 'paleo'
                    ], rand(1, 2)),
                    'pet_preference' => $this->faker->randomElement(['love_pets', 'allergic', 'no_pets', 'no_preference']),
                    'travel_frequency' => $this->faker->randomElement(['never', 'rarely', 'sometimes', 'often', 'constantly']),
                    'living_situation' => $this->faker->optional()->randomElements([
                        'alone', 'roommates', 'family', 'partner'
                    ], rand(1, 2)),
                ];

            case 'interest':
                return [
                    'interests' => $this->faker->randomElements([
                        'travel', 'music', 'sports', 'art', 'cooking', 'reading', 'gaming', 'fitness',
                        'photography', 'dancing', 'movies', 'hiking', 'yoga', 'technology', 'fashion',
                        'wine', 'coffee', 'concerts', 'theater', 'museums', 'beaches', 'mountains'
                    ], rand(3, 8)),
                    'music_genres' => $this->faker->randomElements([
                        'pop', 'rock', 'hip_hop', 'jazz', 'classical', 'electronic', 'country', 'latin'
                    ], rand(2, 4)),
                    'movie_genres' => $this->faker->randomElements([
                        'action', 'comedy', 'drama', 'horror', 'romance', 'sci_fi', 'documentary'
                    ], rand(2, 4)),
                    'sports' => $this->faker->optional()->randomElements([
                        'football', 'basketball', 'tennis', 'swimming', 'running', 'cycling', 'gym'
                    ], rand(1, 3)),
                ];

            case 'relationship':
                return [
                    'relationship_type' => $this->faker->randomElement([
                        'casual_dating', 'serious_relationship', 'marriage', 'friendship', 'hookup'
                    ]),
                    'children_preference' => $this->faker->randomElement([
                        'none', 'has_children', 'wants_children', 'maybe_children', 'no_preference'
                    ]),
                    'commitment_level' => $this->faker->randomElement([
                        'not_sure', 'casual', 'exclusive', 'long_term', 'marriage_minded'
                    ]),
                    'timeline_preference' => $this->faker->randomElement([
                        'take_it_slow', 'see_what_happens', 'ready_to_settle', 'no_rush'
                    ]),
                    'communication_style' => $this->faker->optional()->randomElements([
                        'texter', 'caller', 'video_chat', 'in_person'
                    ], rand(1, 2)),
                ];

            case 'premium':
                return [
                    'verified_only' => $this->faker->boolean(60),
                    'premium_users_only' => $this->faker->boolean(30),
                    'income_range_min' => $this->faker->optional()->numberBetween(30000, 50000),
                    'income_range_max' => $this->faker->optional()->numberBetween(75000, 200000),
                    'mutual_connections' => $this->faker->boolean(40),
                    'shared_interests_min' => $this->faker->numberBetween(2, 5),
                    'compatibility_score_min' => $this->faker->numberBetween(60, 85),
                    'photo_count_min' => $this->faker->numberBetween(2, 5),
                    'profile_completeness_min' => $this->faker->numberBetween(70, 95),
                ];

            case 'behavioral':
                return [
                    'activity_status' => $this->faker->randomElement([
                        'active_now', 'active_today', 'active_week', 'active_month'
                    ]),
                    'response_rate_min' => $this->faker->numberBetween(20, 80),
                    'avg_response_time_max' => $this->faker->numberBetween(60, 1440), // minutes
                    'message_quality_score_min' => $this->faker->numberBetween(3, 9),
                    'profile_views_frequency' => $this->faker->randomElement([
                        'low', 'medium', 'high', 'very_high'
                    ]),
                    'swipe_selectivity' => $this->faker->randomElement([
                        'very_selective', 'selective', 'moderate', 'open'
                    ]),
                ];

            case 'custom':
                return [
                    'custom_fields' => $this->generateCustomFields(),
                    'special_criteria' => $this->faker->optional()->sentences(rand(1, 3)),
                    'dealbreakers' => $this->faker->optional()->randomElements([
                        'smoking', 'no_job', 'bad_hygiene', 'rude_behavior', 'different_values'
                    ], rand(1, 3)),
                    'must_haves' => $this->faker->optional()->randomElements([
                        'sense_of_humor', 'ambition', 'kindness', 'intelligence', 'similar_interests'
                    ], rand(2, 4)),
                ];

            default:
                return [];
        }
    }

    private function generateWeights(string $category): array
    {
        $weights = [
            'age_importance' => $this->faker->numberBetween(1, 10),
            'distance_importance' => $this->faker->numberBetween(1, 10),
            'education_importance' => $this->faker->numberBetween(1, 10),
            'lifestyle_importance' => $this->faker->numberBetween(1, 10),
            'interests_importance' => $this->faker->numberBetween(1, 10),
        ];

        // Ajustar pesos basados en categoría
        switch ($category) {
            case 'demographic':
                $weights['education_importance'] = $this->faker->numberBetween(7, 10);
                $weights['height_importance'] = $this->faker->numberBetween(3, 8);
                break;
            case 'lifestyle':
                $weights['lifestyle_importance'] = $this->faker->numberBetween(8, 10);
                break;
            case 'interest':
                $weights['interests_importance'] = $this->faker->numberBetween(8, 10);
                break;
            case 'premium':
                $weights['compatibility_importance'] = $this->faker->numberBetween(7, 10);
                $weights['verification_importance'] = $this->faker->numberBetween(6, 9);
                break;
        }

        return $weights;
    }

    private function generateSettings(string $category): array
    {
        return [
            'auto_apply' => $this->faker->boolean(60),
            'save_searches' => $this->faker->boolean(80),
            'notify_matches' => $this->faker->boolean(90),
            'flexible_criteria' => $this->faker->boolean(40),
            'learning_mode' => $this->faker->boolean(30),
            'strict_mode' => $category === 'premium' ? $this->faker->boolean(70) : $this->faker->boolean(20),
        ];
    }

    private function generateCompatibilityFactors(string $category): array
    {
        $factors = [
            'personality_match' => $this->faker->numberBetween(1, 10),
            'lifestyle_compatibility' => $this->faker->numberBetween(1, 10),
            'interest_overlap' => $this->faker->numberBetween(1, 10),
            'communication_style' => $this->faker->numberBetween(1, 10),
            'relationship_goals' => $this->faker->numberBetween(1, 10),
        ];

        if ($category === 'premium' || $category === 'behavioral') {
            $factors['activity_compatibility'] = $this->faker->numberBetween(1, 10);
            $factors['response_patterns'] = $this->faker->numberBetween(1, 10);
        }

        return $factors;
    }

    private function generateExclusionRules(string $category): array
    {
        $rules = [];

        if ($this->faker->boolean(30)) {
            $rules['blocked_users'] = $this->faker->boolean(80);
        }

        if ($this->faker->boolean(40)) {
            $rules['previous_matches'] = $this->faker->randomElement(['include', 'exclude', 'deprioritize']);
        }

        if ($this->faker->boolean(25)) {
            $rules['distance_exceptions'] = [
                'allow_premium_distance' => $this->faker->boolean(60),
                'traveler_mode' => $this->faker->boolean(30),
            ];
        }

        return $rules;
    }

    private function generateAdvancedOptions(string $category): array
    {
        $options = [
            'boost_compatible_profiles' => $this->faker->boolean(40),
            'adaptive_learning' => $this->faker->boolean(50),
            'seasonal_adjustments' => $this->faker->boolean(20),
        ];

        if ($category === 'premium') {
            $options['priority_queue'] = $this->faker->boolean(80);
            $options['enhanced_matching'] = $this->faker->boolean(90);
        }

        if ($category === 'behavioral') {
            $options['activity_prediction'] = $this->faker->boolean(60);
            $options['engagement_optimization'] = $this->faker->boolean(70);
        }

        return $options;
    }

    private function generateCustomFields(): array
    {
        return [
            'field_' . $this->faker->word => $this->faker->sentence,
            'preference_' . $this->faker->word => $this->faker->randomElement(['high', 'medium', 'low']),
            'custom_' . $this->faker->word => $this->faker->numberBetween(1, 100),
        ];
    }

    // Estados específicos para diferentes tipos de filtros
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'last_used_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'usage_count' => $this->faker->numberBetween(10, 100),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'last_used_at' => $this->faker->optional(0.3)->dateTimeBetween('-60 days', '-8 days'),
        ]);
    }

    public function defaultFilter(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
            'is_active' => true,
            'priority_order' => 1,
            'usage_count' => $this->faker->numberBetween(50, 300),
        ]);
    }

    public function basicCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'basic',
            'name' => $this->faker->randomElement(['Búsqueda Básica', 'Filtro Estándar', 'Preferencias Generales']),
        ]);
    }

    public function demographicCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'demographic',
            'name' => $this->faker->randomElement(['Perfil Demográfico', 'Características Básicas', 'Datos Personales']),
        ]);
    }

    public function lifestyleCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'lifestyle',
            'name' => $this->faker->randomElement(['Estilo de Vida', 'Hábitos y Costumbres', 'Preferencias de Vida']),
        ]);
    }

    public function interestCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'interest',
            'name' => $this->faker->randomElement(['Intereses Comunes', 'Hobbies Compartidos', 'Pasiones Similares']),
        ]);
    }

    public function relationshipCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'relationship',
            'name' => $this->faker->randomElement(['Tipo de Relación', 'Intenciones Románticas', 'Objetivos de Pareja']),
        ]);
    }

    public function premiumCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'premium',
            'name' => $this->faker->randomElement(['Filtros Premium', 'Búsqueda Avanzada', 'Criterios Exclusivos']),
            'match_success_rate' => $this->faker->numberBetween(60, 95),
        ]);
    }

    public function behavioralCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'behavioral',
            'name' => $this->faker->randomElement(['Patrones de Comportamiento', 'Actividad en App', 'Comportamiento Online']),
        ]);
    }

    public function customCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'custom',
            'name' => $this->faker->randomElement(['Filtro Personalizado', 'Criterios Únicos', 'Búsqueda Especial']),
        ]);
    }

    public function highSuccessRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_success_rate' => $this->faker->numberBetween(70, 95),
            'usage_count' => $this->faker->numberBetween(20, 200),
        ]);
    }

    public function lowSuccessRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_success_rate' => $this->faker->numberBetween(5, 30),
            'usage_count' => $this->faker->numberBetween(1, 15),
        ]);
    }

    public function frequentlyUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $this->faker->numberBetween(50, 500),
            'last_used_at' => $this->faker->dateTimeBetween('-3 days', 'now'),
            'is_active' => true,
        ]);
    }
}