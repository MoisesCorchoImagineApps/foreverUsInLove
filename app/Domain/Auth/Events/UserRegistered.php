<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use App\Models\User\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

/**
 * UserRegistered Event
 * 
 * Evento disparado cuando un nuevo usuario se registra exitosamente
 * en la aplicación de citas ForeverUsInLove.
 * 
 * Este evento permite a otros componentes del sistema reaccionar
 * al registro de usuarios para:
 * - Enviar emails de bienvenida
 * - Crear perfiles iniciales
 * - Configurar preferencias por defecto
 * - Registrar métricas de conversión
 * - Activar campañas de onboarding
 * - Generar reportes de crecimiento
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class UserRegistered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Usuario recién registrado
     */
    public readonly User $user;
    
    /**
     * Información adicional del registro
     */
    public readonly array $registrationData;
    
    /**
     * Timestamp del evento
     */
    public readonly Carbon $timestamp;
    
    /**
     * Fuente del registro (web, app, api)
     */
    public readonly string $source;
    
    /**
     * Canal de registro utilizado (email, phone, social)
     */
    public readonly string $registrationChannel;
    
    /**
     * Datos de ubicación del usuario
     */
    public readonly ?array $locationData;
    
    /**
     * Información del dispositivo/navegador
     */
    public readonly ?array $deviceInfo;

    /**
     * Create a new event instance.
     *
     * @param User $user Usuario recién registrado
     * @param array $registrationData Datos adicionales del proceso de registro
     * @param string $source Fuente del registro (web, mobile_app, api)
     * @param string $registrationChannel Canal usado (email, phone, facebook, google, apple)
     * @param array|null $locationData Información de ubicación del usuario
     * @param array|null $deviceInfo Información del dispositivo/navegador
     */
    public function __construct(
        User $user,
        array $registrationData = [],
        string $source = 'web',
        string $registrationChannel = 'email',
        ?array $locationData = null,
        ?array $deviceInfo = null
    ) {
        $this->user = $user;
        $this->registrationData = $registrationData;
        $this->timestamp = now();
        $this->source = $source;
        $this->registrationChannel = $registrationChannel;
        $this->locationData = $locationData;
        $this->deviceInfo = $deviceInfo;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Canal privado para notificaciones administrativas
            new PrivateChannel('admin.user-registrations'),
            
            // Canal para métricas en tiempo real
            new PrivateChannel('analytics.registrations'),
            
            // Canal específico del usuario para onboarding
            new PrivateChannel("user.{$this->user->id}.onboarding")
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'email' => $this->user->email,
            'age' => $this->user->age,
            'gender' => $this->user->gender ?? null,
            'location' => [
                'city' => $this->locationData['city'] ?? null,
                'country' => $this->locationData['country'] ?? null,
                'timezone' => $this->locationData['timezone'] ?? null
            ],
            'registration' => [
                'source' => $this->source,
                'channel' => $this->registrationChannel,
                'timestamp' => $this->timestamp->toISOString(),
                'completed_steps' => $this->registrationData['completed_steps'] ?? [],
                'verification_status' => [
                    'email_verified' => $this->user->email_verified_at !== null,
                    'phone_verified' => $this->user->phone_verified_at !== null
                ]
            ],
            'device' => [
                'type' => $this->deviceInfo['type'] ?? 'unknown',
                'platform' => $this->deviceInfo['platform'] ?? 'unknown',
                'browser' => $this->deviceInfo['browser'] ?? null,
                'app_version' => $this->deviceInfo['app_version'] ?? null
            ],
            'metrics' => [
                'registration_duration' => $this->registrationData['duration_seconds'] ?? null,
                'referral_source' => $this->registrationData['referral_source'] ?? null,
                'utm_parameters' => $this->registrationData['utm_parameters'] ?? [],
                'ab_test_groups' => $this->registrationData['ab_test_groups'] ?? []
            ]
        ];
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'user.registered';
    }

    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function shouldBroadcast(): bool
    {
        // Solo hacer broadcast si las notificaciones en tiempo real están habilitadas
        return config('broadcasting.enabled', false) && 
               config('app.real_time_notifications', false);
    }

    /**
     * Get additional event metadata for logging and analytics
     *
     * @return array
     */
    public function getEventMetadata(): array
    {
        return [
            'event_type' => 'user_registered',
            'event_version' => '2.0',
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'username' => $this->user->username,
            'timestamp' => $this->timestamp->toISOString(),
            'source' => $this->source,
            'registration_channel' => $this->registrationChannel,
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'session_id' => session()->getId(),
            
            // Datos demográficos básicos
            'demographics' => [
                'age' => $this->user->age,
                'gender' => $this->user->gender ?? 'not_specified',
                'location' => $this->locationData
            ],
            
            // Datos de conversión y marketing
            'conversion' => [
                'referral_source' => $this->registrationData['referral_source'] ?? null,
                'landing_page' => $this->registrationData['landing_page'] ?? null,
                'campaign_id' => $this->registrationData['campaign_id'] ?? null,
                'utm_parameters' => $this->registrationData['utm_parameters'] ?? [],
                'registration_flow' => $this->registrationData['registration_flow'] ?? 'standard'
            ],
            
            // Datos técnicos
            'technical' => [
                'registration_duration' => $this->registrationData['duration_seconds'] ?? null,
                'steps_completed' => $this->registrationData['completed_steps'] ?? [],
                'device_info' => $this->deviceInfo,
                'browser_features' => $this->registrationData['browser_features'] ?? [],
                'connection_type' => $this->registrationData['connection_type'] ?? null
            ],
            
            // Estado de verificación
            'verification' => [
                'email_verified' => $this->user->email_verified_at !== null,
                'phone_verified' => $this->user->phone_verified_at !== null,
                'requires_email_verification' => !$this->user->hasVerifiedEmail(),
                'requires_phone_verification' => empty($this->user->phone_verified_at)
            ],
            
            // Configuraciones iniciales
            'initial_settings' => [
                'notification_preferences' => $this->registrationData['notification_preferences'] ?? [],
                'privacy_settings' => $this->registrationData['privacy_settings'] ?? [],
                'matching_preferences' => $this->registrationData['matching_preferences'] ?? []
            ]
        ];
    }

    /**
     * Get user data safe for public consumption (sin información sensible)
     *
     * @return array
     */
    public function getPublicUserData(): array
    {
        return [
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'age' => $this->user->age,
            'gender' => $this->user->gender ?? 'not_specified',
            'city' => $this->locationData['city'] ?? null,
            'country' => $this->locationData['country'] ?? null,
            'registration_date' => $this->timestamp->format('Y-m-d'),
            'is_verified' => $this->user->hasVerifiedEmail(),
            'account_type' => $this->user->account_type ?? 'free'
        ];
    }

    /**
     * Get data for analytics and reporting systems
     *
     * @return array
     */
    public function getAnalyticsData(): array
    {
        return [
            'event' => 'user_registration_completed',
            'user_id' => $this->user->id,
            'timestamp' => $this->timestamp->timestamp,
            'properties' => [
                'registration_method' => $this->registrationChannel,
                'source' => $this->source,
                'user_age' => $this->user->age,
                'user_gender' => $this->user->gender ?? 'not_specified',
                'user_location_country' => $this->locationData['country'] ?? null,
                'user_location_city' => $this->locationData['city'] ?? null,
                'device_type' => $this->deviceInfo['type'] ?? 'unknown',
                'platform' => $this->deviceInfo['platform'] ?? 'unknown',
                'referral_source' => $this->registrationData['referral_source'] ?? null,
                'campaign_id' => $this->registrationData['campaign_id'] ?? null,
                'registration_duration_seconds' => $this->registrationData['duration_seconds'] ?? null,
                'completed_steps' => count($this->registrationData['completed_steps'] ?? []),
                'ab_test_groups' => $this->registrationData['ab_test_groups'] ?? []
            ]
        ];
    }

    /**
     * Check if user registration is complete and valid
     *
     * @return bool
     */
    public function isRegistrationComplete(): bool
    {
        return !empty($this->user->email) &&
               !empty($this->user->username) &&
               isset($this->user->age) &&
               $this->user->age >= 18;
    }

    /**
     * Get recommended next actions for the user
     *
     * @return array
     */
    public function getRecommendedNextActions(): array
    {
        $actions = [];

        // Verificación de email si no está verificado
        if (!$this->user->hasVerifiedEmail()) {
            $actions[] = [
                'action' => 'verify_email',
                'priority' => 'high',
                'description' => 'Verificar dirección de email',
                'required' => true
            ];
        }

        // Verificación de teléfono si se proporcionó pero no está verificado
        if (!empty($this->user->phone) && empty($this->user->phone_verified_at)) {
            $actions[] = [
                'action' => 'verify_phone',
                'priority' => 'medium',
                'description' => 'Verificar número de teléfono',
                'required' => false
            ];
        }

        // Completar perfil básico
        $actions[] = [
            'action' => 'complete_profile',
            'priority' => 'high',
            'description' => 'Completar información del perfil',
            'required' => true
        ];

        // Configurar preferencias de matching
        $actions[] = [
            'action' => 'set_preferences',
            'priority' => 'medium',
            'description' => 'Configurar preferencias de búsqueda',
            'required' => false
        ];

        // Subir fotos de perfil
        $actions[] = [
            'action' => 'upload_photos',
            'priority' => 'high',
            'description' => 'Agregar fotos al perfil',
            'required' => true
        ];

        return $actions;
    }

    /**
     * Convert event to array format for storage or serialization
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'registration_data' => $this->registrationData,
            'timestamp' => $this->timestamp->toISOString(),
            'source' => $this->source,
            'registration_channel' => $this->registrationChannel,
            'location_data' => $this->locationData,
            'device_info' => $this->deviceInfo
        ];
    }
}