<?php

declare(strict_types=1);

namespace App\Http\Resources\Profile;

use App\Models\User\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * ProfileResource - Resource para serialización de datos de perfil
 * 
 * Este resource maneja la transformación y serialización de datos del modelo Profile
 * para respuestas de API, incluyendo filtrado de información sensible, formateo
 * de datos y agregación de información relacionada.
 * 
 * Funcionalidades:
 * - Transformación de datos del perfil para API
 * - Filtrado de información sensible según contexto
 * - Formateo de fechas y datos especiales
 * - Agregación de información de usuario y fotos
 * - Cálculo de métricas en tiempo real
 * - Soporte para diferentes contextos de visualización
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class ProfileResource extends JsonResource
{
    /**
     * Contextos de visualización disponibles
     */
    public const CONTEXT_OWN = 'own';           // Propio perfil
    public const CONTEXT_PUBLIC = 'public';     // Perfil público para matching
    public const CONTEXT_ADMIN = 'admin';       // Vista administrativa
    public const CONTEXT_PREVIEW = 'preview';   // Vista previa limitada

    /**
     * Campos que nunca se deben exponer públicamente
     */
    private const PRIVATE_FIELDS = [
        'user_id',
        'latitude',
        'longitude',
        'income_range',
        'paused_at',
        'pause_reason',
        'last_updated_at',
        'profile_views_count',
        'profile_likes_count'
    ];

    /**
     * Campos sensibles que solo se muestran al propietario
     */
    private const SENSITIVE_FIELDS = [
        'email',
        'phone',
        'address',
        'emergency_contact'
    ];

    /**
     * Campos que requieren verificación para mostrarse
     */
    private const VERIFICATION_REQUIRED_FIELDS = [
        'instagram_handle',
        'spotify_connected'
    ];

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $context = $this->determineContext($request);
        
        return [
            // Información básica del perfil
            'id' => $this->id,
            'profile_uuid' => $this->profile_uuid ?? null,
            'status' => $this->status,
            'completeness_percentage' => $this->completeness_percentage,
            'is_complete' => $this->is_complete,
            'is_verified' => $this->is_verified,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Información personal
            'personal_info' => $this->getPersonalInfo($context),
            
            // Información demográfica
            'demographics' => $this->getDemographics($context),
            
            // Ubicación y geografía
            'location' => $this->getLocationInfo($context),
            
            // Características físicas
            'physical_characteristics' => $this->getPhysicalCharacteristics($context),
            
            // Educación y trabajo
            'education_work' => $this->getEducationWork($context),
            
            // Estilo de vida
            'lifestyle' => $this->getLifestyle($context),
            
            // Intereses y preferencias
            'interests_preferences' => $this->getInterestsPreferences($context),
            
            // Personalidad y valores
            'personality_values' => $this->getPersonalityValues($context),
            
            // Redes sociales y conectividad
            'social_connections' => $this->getSocialConnections($context),
            
            // Configuraciones de privacidad
            'privacy_settings' => $this->getPrivacySettings($context),
            
            // Información relacionada
            'related_data' => $this->getRelatedData($context),
            
            // Métricas y estadísticas
            'metrics' => $this->getMetrics($context),
            
            // Recomendaciones y sugerencias
            'recommendations' => $this->getRecommendations($context),
        ];
    }

    /**
     * Obtiene información personal del perfil
     */
    private function getPersonalInfo(string $context): array
    {
        $data = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'full_name' => $this->full_name,
            'bio' => $this->bio,
            'tagline' => $this->tagline,
        ];

        // En contexto público, ocultar información sensible
        if ($context === self::CONTEXT_PUBLIC) {
            unset($data['last_name']);
            if (!$this->show_age && $this->age) {
                $data['age_range'] = $this->getAgeRange($this->age);
                unset($data['age']);
            }
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información demográfica
     */
    private function getDemographics(string $context): array
    {
        $data = [
            'age' => $this->age,
            'date_of_birth' => $this->formatDate($this->date_of_birth),
            'gender' => $this->gender,
            'gender_identity' => $this->gender_identity,
            'sexual_orientation' => $this->sexual_orientation,
            'relationship_status' => $this->relationship_status,
            'looking_for' => $this->looking_for,
            'ethnicity' => $this->ethnicity,
        ];

        // En contexto público, aplicar filtros de privacidad
        if ($context === self::CONTEXT_PUBLIC) {
            if (!$this->show_age && $this->age) {
                $data['age_range'] = $this->getAgeRange($this->age);
                unset($data['age'], $data['date_of_birth']);
            }
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de ubicación
     */
    private function getLocationInfo(string $context): array
    {
        $data = [
            'location' => $this->location,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
        ];

        // Solo mostrar coordenadas en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['latitude'] = $this->latitude;
            $data['longitude'] = $this->longitude;
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene características físicas
     */
    private function getPhysicalCharacteristics(string $context): array
    {
        return $this->filterNullValues([
            'height_cm' => $this->height_cm,
            'height_imperial' => $this->height_imperial,
            'body_type' => $this->body_type,
        ]);
    }

    /**
     * Obtiene información de educación y trabajo
     */
    private function getEducationWork(string $context): array
    {
        $data = [
            'education' => $this->education,
            'occupation' => $this->occupation,
            'company' => $this->company,
            'school' => $this->school,
        ];

        // Solo mostrar ingresos en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['income_range'] = $this->income_range;
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de estilo de vida
     */
    private function getLifestyle(string $context): array
    {
        return $this->filterNullValues([
            'religion' => $this->religion,
            'smoking' => $this->smoking,
            'drinking' => $this->drinking,
            'has_children' => $this->has_children,
            'wants_children' => $this->wants_children,
            'languages' => $this->languages,
        ]);
    }

    /**
     * Obtiene intereses y preferencias
     */
    private function getInterestsPreferences(string $context): array
    {
        return $this->filterNullValues([
            'interests' => $this->interests,
            'hobbies' => $this->hobbies,
            'music_preferences' => $this->music_preferences,
            'movie_preferences' => $this->movie_preferences,
            'book_preferences' => $this->book_preferences,
        ]);
    }

    /**
     * Obtiene información de personalidad y valores
     */
    private function getPersonalityValues(string $context): array
    {
        return $this->filterNullValues([
            'personality_type' => $this->personality_type,
            'values' => $this->values,
            'zodiac_sign' => $this->zodiac_sign,
        ]);
    }

    /**
     * Obtiene conexiones sociales
     */
    private function getSocialConnections(string $context): array
    {
        $data = [];

        // Solo mostrar redes sociales si están verificadas o es contexto propio
        if ($context === self::CONTEXT_OWN || $this->is_verified) {
            $data = array_merge($data, [
                'instagram_handle' => $this->instagram_handle,
                'spotify_connected' => $this->spotify_connected,
            ]);
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene configuraciones de privacidad
     */
    private function getPrivacySettings(string $context): array
    {
        // Solo mostrar en contexto propio
        if ($context !== self::CONTEXT_OWN) {
            return [];
        }

        return $this->filterNullValues([
            'show_age' => $this->show_age,
            'show_distance' => $this->show_distance,
            'visibility_radius_km' => $this->visibility_radius_km,
        ]);
    }

    /**
     * Obtiene datos relacionados (usuario, fotos, etc.)
     */
    private function getRelatedData(string $context): array
    {
        $data = [];

        // Información del usuario
        if ($this->relationLoaded('user')) {
            $data['user'] = [
                'id' => $this->user->id,
                'username' => $this->user->username,
                'email' => $context === self::CONTEXT_OWN ? $this->user->email : null,
                'phone' => $context === self::CONTEXT_OWN ? $this->user->phone : null,
                'is_verified' => $this->user->is_verified ?? false,
                'last_active_at' => $this->formatDateTime($this->user->last_active_at),
            ];
        }

        // Fotos del perfil
        if ($this->relationLoaded('user.images')) {
            $data['photos'] = $this->user->images->map(function ($image) use ($context) {
                return [
                    'id' => $image->id,
                    'url' => $image->url,
                    'thumbnail_url' => $image->thumbnail_url,
                    'is_primary' => $image->is_primary,
                    'order' => $image->order,
                    'created_at' => $this->formatDateTime($image->created_at),
                ];
            });
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene métricas del perfil
     */
    private function getMetrics(string $context): array
    {
        $data = [];

        // Métricas básicas siempre disponibles
        $data['completeness'] = [
            'percentage' => $this->completeness_percentage,
            'is_complete' => $this->is_complete,
            'missing_fields' => $this->getMissingFields(),
        ];

        // Métricas detalladas solo para contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['engagement'] = [
                'profile_views_count' => $this->profile_views_count,
                'profile_likes_count' => $this->profile_likes_count,
                'last_updated_at' => $this->formatDateTime($this->last_updated_at),
            ];

            if ($context === self::CONTEXT_ADMIN) {
                $data['admin'] = [
                    'paused_at' => $this->formatDateTime($this->paused_at),
                    'pause_reason' => $this->pause_reason,
                ];
            }
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene recomendaciones para mejorar el perfil
     */
    private function getRecommendations(string $context): array
    {
        // Solo mostrar recomendaciones en contexto propio
        if ($context !== self::CONTEXT_OWN) {
            return [];
        }

        $recommendations = [];

        // Recomendaciones basadas en completitud
        if ($this->completeness_percentage < 80) {
            $recommendations[] = [
                'type' => 'completeness',
                'priority' => 'high',
                'title' => 'Completa tu perfil',
                'description' => 'Un perfil completo tiene 3x más probabilidades de recibir matches',
                'action' => 'complete_profile'
            ];
        }

        // Recomendaciones específicas por campos faltantes
        if (empty($this->bio) || strlen($this->bio) < 50) {
            $recommendations[] = [
                'type' => 'bio',
                'priority' => 'medium',
                'title' => 'Mejora tu biografía',
                'description' => 'Escribe una biografía más detallada para atraer mejores matches',
                'action' => 'improve_bio'
            ];
        }

        if (empty($this->interests) || count($this->interests) < 3) {
            $recommendations[] = [
                'type' => 'interests',
                'priority' => 'medium',
                'title' => 'Agrega más intereses',
                'description' => 'Los intereses ayudan a encontrar personas compatibles',
                'action' => 'add_interests'
            ];
        }

        // Recomendaciones de fotos
        $photoCount = $this->user->images->count() ?? 0;
        if ($photoCount < 3) {
            $recommendations[] = [
                'type' => 'photos',
                'priority' => 'high',
                'title' => 'Sube más fotos',
                'description' => 'Los perfiles con 3+ fotos reciben 5x más likes',
                'action' => 'upload_photos'
            ];
        }

        return $recommendations;
    }

    /**
     * Determina el contexto de visualización basado en la request
     */
    private function determineContext(Request $request): string
    {
        $user = $request->user();
        
        // Si no hay usuario autenticado, es contexto público
        if (!$user) {
            return self::CONTEXT_PUBLIC;
        }

        // Si el perfil pertenece al usuario autenticado, es contexto propio
        if ($this->user_id === $user->id) {
            return self::CONTEXT_OWN;
        }

        // Si es admin, contexto administrativo
        if ($user->is_admin ?? false) {
            return self::CONTEXT_ADMIN;
        }

        // Por defecto, contexto público
        return self::CONTEXT_PUBLIC;
    }

    /**
     * Formatea una fecha para la respuesta
     */
    private function formatDate(?Carbon $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    /**
     * Formatea una fecha y hora para la respuesta
     */
    private function formatDateTime(?Carbon $dateTime): ?string
    {
        return $dateTime?->toISOString();
    }

    /**
     * Obtiene rango de edad para mostrar en lugar de edad exacta
     */
    private function getAgeRange(int $age): string
    {
        if ($age < 25) return '18-24';
        if ($age < 35) return '25-34';
        if ($age < 45) return '35-44';
        if ($age < 55) return '45-54';
        return '55+';
    }

    /**
     * Obtiene campos faltantes del perfil
     */
    private function getMissingFields(): array
    {
        $missing = [];
        $requiredFields = [
            'first_name', 'bio', 'date_of_birth', 'gender', 
            'location', 'occupation', 'interests'
        ];

        foreach ($requiredFields as $field) {
            if (empty($this->$field)) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Filtra valores nulos de un array
     */
    private function filterNullValues(array $data): array
    {
        return array_filter($data, function ($value) {
            return $value !== null;
        });
    }

    /**
     * Obtiene metadatos adicionales para la respuesta
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'context' => $this->determineContext($request),
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0',
                'cache_ttl' => $this->getCacheTTL(),
            ]
        ];
    }

    /**
     * Obtiene el TTL de cache basado en el contexto
     */
    private function getCacheTTL(): int
    {
        $context = $this->determineContext(request());
        
        return match ($context) {
            self::CONTEXT_OWN => 300,      // 5 minutos para perfil propio
            self::CONTEXT_PUBLIC => 1800,  // 30 minutos para perfil público
            self::CONTEXT_ADMIN => 60,     // 1 minuto para vista admin
            default => 900,                // 15 minutos por defecto
        };
    }
}
