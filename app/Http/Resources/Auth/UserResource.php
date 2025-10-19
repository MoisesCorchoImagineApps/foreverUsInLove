<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserResource - API Resource para datos de usuario
 * 
 * Resource especializado para transformar el modelo User en respuestas API
 * estructuradas y consistentes para la aplicación ForeverUsInLove.
 * 
 * Implementa diferentes niveles de información según el contexto:
 * - Basic: Información mínima para listados públicos
 * - Profile: Información completa del perfil para matching
 * - Private: Información completa para el propio usuario
 * - Admin: Información administrativa completa
 * 
 * Características:
 * - Respuestas consistentes y estructuradas
 * - Filtrado de datos sensibles según contexto
 * - Inclusión condicional de relaciones
 * - Formateo automático de datos
 * - Soporte para diferentes niveles de privacidad
 * - Optimización de rendimiento con eager loading
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class UserResource extends JsonResource
{
    /**
     * Contexto de la respuesta (basic, profile, private, admin)
     */
    protected string $context = 'basic';

    /**
     * Indica si incluir relaciones adicionales
     */
    protected bool $includeRelations = false;

    /**
     * Indica si es el propio usuario
     */
    protected bool $isOwnProfile = false;

    /**
     * Create a new resource instance.
     *
     * @param User $resource
     * @param string $context Contexto de la respuesta
     * @param bool $includeRelations Incluir relaciones adicionales
     * @param bool $isOwnProfile Si es el propio perfil del usuario
     */
    public function __construct($resource, string $context = 'basic', bool $includeRelations = false, bool $isOwnProfile = false)
    {
        parent::__construct($resource);
        
        $this->context = $context;
        $this->includeRelations = $includeRelations;
        $this->isOwnProfile = $isOwnProfile;
    }

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return match ($this->context) {
            'basic' => $this->getBasicData(),
            'profile' => $this->getProfileData(),
            'private' => $this->getPrivateData(),
            'admin' => $this->getAdminData(),
            default => $this->getBasicData(),
        };
    }

    /**
     * Obtiene datos básicos del usuario (para listados públicos)
     *
     * @return array<string, mixed>
     */
    protected function getBasicData(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->profile?->display_name ?? $this->username,
            'age' => $this->profile?->age,
            'location' => $this->getLocationData(),
            'primary_photo' => $this->getPrimaryPhotoData(),
            'is_verified' => $this->profile?->is_verified ?? false,
            'is_online' => $this->isOnline(),
            'last_active' => $this->getLastActiveData(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Obtiene datos del perfil (para matching y discovery)
     *
     * @return array<string, mixed>
     */
    protected function getProfileData(): array
    {
        $basicData = $this->getBasicData();
        
        return array_merge($basicData, [
            'bio' => $this->profile?->bio,
            'tagline' => $this->profile?->tagline,
            'gender' => $this->profile?->gender,
            'sexual_orientation' => $this->profile?->sexual_orientation,
            'relationship_status' => $this->profile?->relationship_status,
            'looking_for' => $this->profile?->looking_for,
            'height' => $this->getHeightData(),
            'body_type' => $this->profile?->body_type,
            'education' => $this->profile?->education,
            'occupation' => $this->profile?->occupation,
            'interests' => $this->profile?->interests ?? [],
            'languages' => $this->profile?->languages ?? [],
            'photos' => $this->getPhotosData(),
            'compatibility_score' => $this->getCompatibilityScore(),
            'distance_km' => $this->getDistanceData(),
            'profile_completion' => $this->profile?->completeness_percentage ?? 0,
            'profile_views' => $this->profile?->profile_views_count ?? 0,
            'profile_likes' => $this->profile?->profile_likes_count ?? 0,
            'status' => $this->profile?->status ?? 'incomplete',
        ]);
    }

    /**
     * Obtiene datos privados (para el propio usuario)
     *
     * @return array<string, mixed>
     */
    protected function getPrivateData(): array
    {
        $profileData = $this->getProfileData();
        
        return array_merge($profileData, [
            'email' => $this->email,
            'phone' => $this->isOwnProfile ? $this->phone : null,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'phone_verified_at' => $this->phone_verified_at?->toISOString(),
            'account_status' => $this->status,
            'user_type' => $this->user_type ?? 'user',
            'login_count' => $this->login_count ?? 0,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'last_login_ip' => $this->isOwnProfile ? $this->last_login_ip : null,
            'face_id_enabled' => $this->face_id_enabled ?? false,
            'two_factor_enabled' => $this->two_factor_enabled ?? false,
            'device_token' => $this->isOwnProfile ? $this->device_token : null,
            'marketing_emails_consent' => $this->marketing_emails_consent ?? false,
            'gdpr_consents' => $this->isOwnProfile ? ($this->gdpr_consents ?? []) : null,
            'gdpr_consent_date' => $this->isOwnProfile ? $this->gdpr_consent_date?->toISOString() : null,
            'settings' => $this->getSettingsData(),
            'subscription' => $this->getSubscriptionData(),
            'security' => $this->getSecurityData(),
            'analytics' => $this->getAnalyticsData(),
        ]);
    }

    /**
     * Obtiene datos administrativos (para panel admin)
     *
     * @return array<string, mixed>
     */
    protected function getAdminData(): array
    {
        $privateData = $this->getPrivateData();
        
        return array_merge($privateData, [
            'admin_notes' => $this->admin_notes ?? null,
            'moderation_status' => $this->moderation_status ?? 'approved',
            'risk_score' => $this->risk_score ?? 0,
            'login_attempts' => $this->login_attempts ?? 0,
            'locked_until' => $this->locked_until?->toISOString(),
            'rate_limit_hits' => $this->rate_limit_hits ?? 0,
            'rate_limit_reset_at' => $this->rate_limit_reset_at?->toISOString(),
            'last_logout_at' => $this->last_logout_at?->toISOString(),
            'password_changed_at' => $this->password_changed_at?->toISOString(),
            'email_verification_code' => $this->email_verification_code,
            'phone_verification_code' => $this->phone_verification_code,
            'password_reset_code' => $this->password_reset_code,
            'password_reset_token' => $this->password_reset_token,
            'deleted_at' => $this->deleted_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'relationships' => $this->getRelationshipsData(),
            'moderation_history' => $this->getModerationHistoryData(),
            'billing_info' => $this->getBillingInfoData(),
        ]);
    }

    /**
     * Obtiene datos de ubicación formateados
     *
     * @return array<string, mixed>|null
     */
    protected function getLocationData(): ?array
    {
        if (!$this->profile) {
            return null;
        }

        return [
            'city' => $this->profile->city,
            'state' => $this->profile->state,
            'country' => $this->profile->country,
            'coordinates' => $this->profile->latitude && $this->profile->longitude ? [
                'latitude' => $this->profile->latitude,
                'longitude' => $this->profile->longitude,
            ] : null,
        ];
    }

    /**
     * Obtiene datos de la foto principal
     *
     * @return array<string, mixed>|null
     */
    protected function getPrimaryPhotoData(): ?array
    {
        $primaryImage = $this->resource->images()->where('is_primary', true)->where('status', 'active')->first();
        
        if (!$primaryImage) {
            return null;
        }

        return [
            'id' => $primaryImage->id,
            'url' => $primaryImage->getFullUrl(),
            'thumbnail_url' => $primaryImage->getThumbnailUrl(),
            'width' => $primaryImage->width,
            'height' => $primaryImage->height,
            'aspect_ratio' => $primaryImage->aspect_ratio,
            'is_landscape' => $primaryImage->is_landscape,
            'is_portrait' => $primaryImage->is_portrait,
            'is_square' => $primaryImage->is_square,
        ];
    }

    /**
     * Obtiene datos de todas las fotos
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getPhotosData(): array
    {
        if (!$this->includeRelations) {
            return [];
        }

        return $this->resource->images()
            ->where('status', 'active')
            ->orderBy('order')
            ->get()
            ->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => $image->getFullUrl(),
                    'thumbnail_url' => $image->getThumbnailUrl(),
                    'is_primary' => $image->is_primary,
                    'order' => $image->order,
                    'width' => $image->width,
                    'height' => $image->height,
                    'aspect_ratio' => $image->aspect_ratio,
                    'views_count' => $image->views_count,
                    'likes_count' => $image->likes_count,
                    'created_at' => $image->created_at?->toISOString(),
                ];
            })
            ->toArray();
    }

    /**
     * Obtiene datos de altura formateados
     *
     * @return array<string, mixed>|null
     */
    protected function getHeightData(): ?array
    {
        if (!$this->profile?->height_cm) {
            return null;
        }

        return [
            'cm' => $this->profile->height_cm,
            'feet_inches' => $this->profile->height_imperial,
        ];
    }

    /**
     * Obtiene datos de última actividad
     *
     * @return array<string, mixed>|null
     */
    protected function getLastActiveData(): ?array
    {
        $lastActive = $this->last_active_at ?? $this->last_login_at;
        
        if (!$lastActive) {
            return null;
        }

        return [
            'timestamp' => $lastActive->toISOString(),
            'human_readable' => $lastActive->diffForHumans(),
            'is_online' => $this->isOnline(),
        ];
    }

    /**
     * Verifica si el usuario está en línea
     *
     * @return bool
     */
    protected function isOnline(): bool
    {
        $lastActive = $this->last_active_at ?? $this->last_login_at;
        
        if (!$lastActive) {
            return false;
        }

        // Considerar en línea si la última actividad fue hace menos de 5 minutos
        return $lastActive->isAfter(now()->subMinutes(5));
    }

    /**
     * Obtiene score de compatibilidad (placeholder)
     *
     * @return int|null
     */
    protected function getCompatibilityScore(): ?int
    {
        // Este método debería calcular la compatibilidad con el usuario actual
        // Por ahora retornamos null, pero en implementación real se calcularía
        return null;
    }

    /**
     * Obtiene datos de distancia (placeholder)
     *
     * @return float|null
     */
    protected function getDistanceData(): ?float
    {
        // Este método debería calcular la distancia con el usuario actual
        // Por ahora retornamos null, pero en implementación real se calcularía
        return null;
    }

    /**
     * Obtiene datos de configuraciones
     *
     * @return array<string, mixed>|null
     */
    protected function getSettingsData(): ?array
    {
        if (!$this->includeRelations || !$this->settings) {
            return null;
        }

        return [
            'discovery' => [
                'mode' => $this->settings->discovery_mode,
                'show_me_in_discovery' => $this->settings->show_me_in_discovery,
                'min_age_preference' => $this->settings->min_age_preference,
                'max_age_preference' => $this->settings->max_age_preference,
                'max_distance_km' => $this->settings->max_distance_km,
                'gender_preferences' => $this->settings->gender_preferences ?? [],
                'show_verified_only' => $this->settings->show_verified_only,
            ],
            'notifications' => [
                'enabled' => $this->settings->notifications_enabled,
                'email' => $this->settings->email_notifications,
                'push' => $this->settings->push_notifications,
                'sms' => $this->settings->sms_notifications,
                'new_matches' => $this->settings->notify_new_matches,
                'new_messages' => $this->settings->notify_new_messages,
                'new_likes' => $this->settings->notify_new_likes,
                'do_not_disturb' => $this->settings->do_not_disturb,
                'dnd_hours' => $this->settings->do_not_disturb ? [
                    'start' => $this->settings->do_not_disturb_start,
                    'end' => $this->settings->do_not_disturb_end,
                ] : null,
            ],
            'privacy' => [
                'profile_visibility' => $this->settings->profile_visibility,
                'show_online_status' => $this->settings->show_online_status,
                'show_last_active' => $this->settings->show_last_active,
                'show_read_receipts' => $this->settings->show_read_receipts,
                'incognito_mode' => $this->settings->incognito_mode,
                'hide_from_contacts' => $this->settings->hide_profile_from_contacts,
            ],
            'appearance' => [
                'language' => $this->settings->language,
                'timezone' => $this->settings->timezone,
                'theme' => $this->settings->theme,
                'distance_unit' => $this->settings->distance_unit,
                'reduce_motion' => $this->settings->reduce_motion,
                'high_contrast' => $this->settings->high_contrast,
            ],
        ];
    }

    /**
     * Obtiene datos de suscripción
     *
     * @return array<string, mixed>|null
     */
    protected function getSubscriptionData(): ?array
    {
        if (!$this->includeRelations || !$this->settings) {
            return null;
        }

        return [
            'is_premium' => $this->settings->is_premium,
            'tier' => $this->settings->subscription_tier,
            'expires_at' => $this->settings->subscription_expires_at?->toISOString(),
            'auto_renew' => $this->settings->auto_renew_subscription,
            'features' => [
                'unlimited_likes' => $this->settings->unlimited_likes,
                'read_receipts' => $this->settings->read_receipts_enabled,
                'passport' => $this->settings->passport_enabled,
                'boost' => $this->settings->boost_enabled,
            ],
            'remaining' => [
                'super_likes' => $this->settings->super_likes_remaining,
                'rewinds' => $this->settings->rewinds_remaining,
            ],
        ];
    }

    /**
     * Obtiene datos de seguridad
     *
     * @return array<string, mixed>|null
     */
    protected function getSecurityData(): ?array
    {
        if (!$this->isOwnProfile) {
            return null;
        }

        return [
            'two_factor_enabled' => $this->two_factor_enabled ?? false,
            'face_id_enabled' => $this->face_id_enabled ?? false,
            'login_attempts' => $this->login_attempts ?? 0,
            'locked_until' => $this->locked_until?->toISOString(),
            'last_login_ip' => $this->last_login_ip,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'password_changed_at' => $this->password_changed_at?->toISOString(),
            'device_token' => $this->device_token,
            'active_sessions' => $this->getActiveSessionsData(),
        ];
    }

    /**
     * Obtiene datos de sesiones activas
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getActiveSessionsData(): array
    {
        if (!$this->includeRelations) {
            return [];
        }

        return $this->resource->tokens()
            ->select(['id', 'name', 'last_used_at', 'created_at'])
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'device_name' => $token->name,
                    'last_used_at' => $token->last_used_at?->toISOString(),
                    'created_at' => $token->created_at?->toISOString(),
                    'is_current' => false, // TODO: Implement proper current session detection
                ];
            })
            ->toArray();
    }

    /**
     * Obtiene datos de analytics
     *
     * @return array<string, mixed>|null
     */
    protected function getAnalyticsData(): ?array
    {
        if (!$this->isOwnProfile) {
            return null;
        }

        return [
            'profile_views' => $this->profile?->profile_views_count ?? 0,
            'profile_likes' => $this->profile?->profile_likes_count ?? 0,
            'login_count' => $this->login_count ?? 0,
            'account_age_days' => $this->created_at?->diffInDays(now()) ?? 0,
            'last_active_at' => $this->last_active_at?->toISOString(),
            'profile_completion' => $this->profile?->completeness_percentage ?? 0,
            'photos_count' => $this->resource->images()->where('status', 'active')->count(),
        ];
    }

    /**
     * Obtiene datos de relaciones (solo admin)
     *
     * @return array<string, mixed>|null
     */
    protected function getRelationshipsData(): ?array
    {
        if (!$this->includeRelations) {
            return null;
        }

        return [
            'profile' => $this->profile ? [
                'id' => $this->profile->id,
                'status' => $this->profile->status,
                'completeness_percentage' => $this->profile->completeness_percentage,
                'is_verified' => $this->profile->is_verified,
                'created_at' => $this->profile->created_at?->toISOString(),
                'updated_at' => $this->profile->updated_at?->toISOString(),
            ] : null,
            'images_count' => $this->resource->images()->count(),
            'active_images_count' => $this->resource->images()->where('status', 'active')->count(),
            'settings' => $this->settings ? [
                'id' => $this->settings->id,
                'subscription_tier' => $this->settings->subscription_tier,
                'is_premium' => $this->settings->is_premium,
                'created_at' => $this->settings->created_at?->toISOString(),
                'updated_at' => $this->settings->updated_at?->toISOString(),
            ] : null,
        ];
    }

    /**
     * Obtiene historial de moderación (solo admin)
     *
     * @return array<string, mixed>|null
     */
    protected function getModerationHistoryData(): ?array
    {
        // Placeholder para historial de moderación
        // En implementación real, esto vendría de una tabla de historial
        return null;
    }

    /**
     * Obtiene información de facturación (solo admin)
     *
     * @return array<string, mixed>|null
     */
    protected function getBillingInfoData(): ?array
    {
        // Placeholder para información de facturación
        // En implementación real, esto vendría de una tabla de facturación
        return null;
    }

    /**
     * Obtiene datos adicionales para incluir en la respuesta
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'context' => $this->context,
                'include_relations' => $this->includeRelations,
                'is_own_profile' => $this->isOwnProfile,
                'timestamp' => now()->toISOString(),
                'version' => '1.0',
            ],
        ];
    }

    /**
     * Método estático para crear resource con contexto básico
     *
     * @param User $user
     * @return static
     */
    public static function basic(User $user): static
    {
        return new static($user, 'basic', false, false);
    }

    /**
     * Método estático para crear resource con contexto de perfil
     *
     * @param User $user
     * @param bool $includeRelations
     * @return static
     */
    public static function profile(User $user, bool $includeRelations = false): static
    {
        return new static($user, 'profile', $includeRelations, false);
    }

    /**
     * Método estático para crear resource con contexto privado
     *
     * @param User $user
     * @param bool $includeRelations
     * @param bool $isOwnProfile
     * @return static
     */
    public static function private(User $user, bool $includeRelations = true, bool $isOwnProfile = true): static
    {
        return new static($user, 'private', $includeRelations, $isOwnProfile);
    }

    /**
     * Método estático para crear resource con contexto administrativo
     *
     * @param User $user
     * @param bool $includeRelations
     * @return static
     */
    public static function admin(User $user, bool $includeRelations = true): static
    {
        return new static($user, 'admin', $includeRelations, false);
    }
}
