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
 * UserLoggedIn Event
 * 
 * Evento disparado cuando un usuario inicia sesión exitosamente
 * en la aplicación de citas ForeverUsInLove.
 * 
 * Este evento permite a otros componentes del sistema reaccionar
 * al inicio de sesión para:
 * - Actualizar estado de presencia en línea
 * - Registrar actividad de usuario
 * - Detectar inicios de sesión sospechosos
 * - Sincronizar datos entre dispositivos
 * - Activar notificaciones push
 * - Registrar métricas de engagement
 * - Actualizar última actividad
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class UserLoggedIn implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Usuario que inició sesión
     */
    public readonly User $user;
    
    /**
     * Timestamp del inicio de sesión
     */
    public readonly Carbon $timestamp;
    
    /**
     * Método de autenticación utilizado
     */
    public readonly string $authenticationMethod;
    
    /**
     * Información del dispositivo/navegador
     */
    public readonly ?array $deviceInfo;
    
    /**
     * Datos de ubicación del inicio de sesión
     */
    public readonly ?array $locationData;
    
    /**
     * ID de la sesión generada
     */
    public readonly string $sessionId;
    
    /**
     * Token de autenticación generado
     */
    public readonly ?string $accessToken;
    
    /**
     * Información adicional del contexto de login
     */
    public readonly array $loginContext;
    
    /**
     * Indica si es un inicio de sesión desde un dispositivo nuevo
     */
    public readonly bool $isNewDevice;
    
    /**
     * Indica si es un inicio de sesión desde una ubicación nueva
     */
    public readonly bool $isNewLocation;

    /**
     * Create a new event instance.
     *
     * @param User $user Usuario que inició sesión
     * @param string $authenticationMethod Método usado (password, face_id, social, sms, etc.)
     * @param string $sessionId ID de la sesión generada
     * @param string|null $accessToken Token de acceso generado (opcional por seguridad)
     * @param array|null $deviceInfo Información del dispositivo
     * @param array|null $locationData Datos de ubicación
     * @param array $loginContext Contexto adicional del login
     * @param bool $isNewDevice Si es un dispositivo nuevo
     * @param bool $isNewLocation Si es una ubicación nueva
     */
    public function __construct(
        User $user,
        string $authenticationMethod,
        string $sessionId,
        ?string $accessToken = null,
        ?array $deviceInfo = null,
        ?array $locationData = null,
        array $loginContext = [],
        bool $isNewDevice = false,
        bool $isNewLocation = false
    ) {
        $this->user = $user;
        $this->timestamp = now();
        $this->authenticationMethod = $authenticationMethod;
        $this->sessionId = $sessionId;
        $this->accessToken = $accessToken; // Solo para uso interno, no se transmite
        $this->deviceInfo = $deviceInfo;
        $this->locationData = $locationData;
        $this->loginContext = $loginContext;
        $this->isNewDevice = $isNewDevice;
        $this->isNewLocation = $isNewLocation;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Canal privado para el usuario - notificaciones de seguridad
            new PrivateChannel("user.{$this->user->id}.security"),
            
            // Canal para otras sesiones del usuario - sincronización
            new PrivateChannel("user.{$this->user->id}.sessions"),
            
            // Canal de presencia para mostrar estado en línea
            new PresenceChannel("online-users"),
            
            // Canal administrativo para monitoreo
            new PrivateChannel('admin.user-logins'),
            
            // Canal para métricas en tiempo real
            new PrivateChannel('analytics.user-activity')
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
            'timestamp' => $this->timestamp->toISOString(),
            'session_id' => $this->sessionId,
            
            // Información de autenticación (sin datos sensibles)
            'authentication' => [
                'method' => $this->authenticationMethod,
                'is_two_factor' => $this->loginContext['two_factor_used'] ?? false,
                'remember_me' => $this->loginContext['remember_me'] ?? false
            ],
            
            // Estado de presencia
            'presence' => [
                'status' => 'online',
                'last_seen' => $this->timestamp->toISOString(),
                'active_sessions' => $this->loginContext['active_sessions_count'] ?? 1
            ],
            
            // Información de dispositivo (datos públicos)
            'device' => [
                'type' => $this->deviceInfo['type'] ?? 'unknown',
                'platform' => $this->deviceInfo['platform'] ?? 'unknown',
                'browser' => $this->deviceInfo['browser'] ?? null,
                'app_version' => $this->deviceInfo['app_version'] ?? null,
                'is_mobile' => $this->deviceInfo['is_mobile'] ?? false
            ],
            
            // Ubicación general (sin datos precisos por privacidad)
            'location' => [
                'country' => $this->locationData['country'] ?? null,
                'timezone' => $this->locationData['timezone'] ?? null,
                'is_new_location' => $this->isNewLocation
            ],
            
            // Alertas de seguridad
            'security_alerts' => [
                'new_device' => $this->isNewDevice,
                'new_location' => $this->isNewLocation,
                'suspicious_activity' => $this->loginContext['suspicious_activity'] ?? false,
                'requires_verification' => $this->loginContext['requires_verification'] ?? false
            ],
            
            // Datos para sincronización entre dispositivos
            'sync_data' => [
                'last_activity' => $this->user->last_activity_at?->toISOString(),
                'unread_messages' => $this->loginContext['unread_messages'] ?? 0,
                'pending_matches' => $this->loginContext['pending_matches'] ?? 0,
                'profile_completion' => $this->loginContext['profile_completion'] ?? 0
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
        return 'user.logged_in';
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
            'event_type' => 'user_logged_in',
            'event_version' => '2.0',
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'username' => $this->user->username,
            'timestamp' => $this->timestamp->toISOString(),
            'session_id' => $this->sessionId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            
            // Datos de autenticación
            'authentication' => [
                'method' => $this->authenticationMethod,
                'two_factor_enabled' => $this->user->two_factor_secret !== null,
                'two_factor_used' => $this->loginContext['two_factor_used'] ?? false,
                'remember_me' => $this->loginContext['remember_me'] ?? false,
                'login_attempts' => $this->loginContext['login_attempts'] ?? 1,
                'authentication_duration' => $this->loginContext['auth_duration_ms'] ?? null
            ],
            
            // Datos del dispositivo y navegador
            'device' => array_merge($this->deviceInfo ?? [], [
                'fingerprint' => $this->loginContext['device_fingerprint'] ?? null,
                'screen_resolution' => $this->loginContext['screen_resolution'] ?? null,
                'timezone_offset' => $this->loginContext['timezone_offset'] ?? null,
                'language' => $this->loginContext['browser_language'] ?? null
            ]),
            
            // Datos de ubicación y red
            'location' => array_merge($this->locationData ?? [], [
                'is_vpn' => $this->loginContext['is_vpn'] ?? false,
                'is_proxy' => $this->loginContext['is_proxy'] ?? false,
                'isp' => $this->locationData['isp'] ?? null,
                'connection_type' => $this->loginContext['connection_type'] ?? null
            ]),
            
            // Análisis de riesgo y seguridad
            'security' => [
                'risk_score' => $this->loginContext['risk_score'] ?? 0,
                'is_new_device' => $this->isNewDevice,
                'is_new_location' => $this->isNewLocation,
                'suspicious_activity' => $this->loginContext['suspicious_activity'] ?? false,
                'failed_attempts_before' => $this->loginContext['failed_attempts'] ?? 0,
                'last_login_at' => $this->user->last_login_at?->toISOString(),
                'days_since_last_login' => $this->user->last_login_at ? 
                    $this->timestamp->diffInDays($this->user->last_login_at) : null
            ],
            
            // Datos de sesión y actividad
            'session' => [
                'active_sessions_count' => $this->loginContext['active_sessions_count'] ?? 1,
                'concurrent_sessions' => $this->loginContext['concurrent_sessions'] ?? [],
                'session_duration_limit' => $this->loginContext['session_timeout'] ?? null,
                'remember_token_expires' => $this->loginContext['remember_token_expires'] ?? null
            ],
            
            // Contexto de la aplicación
            'app_context' => [
                'app_version' => $this->deviceInfo['app_version'] ?? null,
                'api_version' => $this->loginContext['api_version'] ?? null,
                'login_source' => $this->loginContext['login_source'] ?? 'direct',
                'referrer_url' => $this->loginContext['referrer'] ?? null,
                'utm_parameters' => $this->loginContext['utm_parameters'] ?? []
            ],
            
            // Datos de usuario relevantes
            'user_data' => [
                'account_type' => $this->user->account_type ?? 'free',
                'email_verified' => $this->user->hasVerifiedEmail(),
                'phone_verified' => $this->user->phone_verified_at !== null,
                'profile_completion' => $this->loginContext['profile_completion'] ?? 0,
                'subscription_status' => $this->loginContext['subscription_status'] ?? 'free',
                'account_age_days' => $this->user->created_at->diffInDays($this->timestamp)
            ]
        ];
    }

    /**
     * Get security-related data for threat detection
     *
     * @return array
     */
    public function getSecurityData(): array
    {
        return [
            'user_id' => $this->user->id,
            'timestamp' => $this->timestamp->timestamp,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'authentication_method' => $this->authenticationMethod,
            'is_new_device' => $this->isNewDevice,
            'is_new_location' => $this->isNewLocation,
            'risk_indicators' => [
                'suspicious_activity' => $this->loginContext['suspicious_activity'] ?? false,
                'is_vpn' => $this->loginContext['is_vpn'] ?? false,
                'is_proxy' => $this->loginContext['is_proxy'] ?? false,
                'risk_score' => $this->loginContext['risk_score'] ?? 0,
                'failed_attempts' => $this->loginContext['failed_attempts'] ?? 0
            ],
            'device_fingerprint' => $this->loginContext['device_fingerprint'] ?? null,
            'session_id' => $this->sessionId,
            'concurrent_sessions' => count($this->loginContext['concurrent_sessions'] ?? [])
        ];
    }

    /**
     * Get analytics data for user behavior tracking
     *
     * @return array
     */
    public function getAnalyticsData(): array
    {
        return [
            'event' => 'user_login',
            'user_id' => $this->user->id,
            'timestamp' => $this->timestamp->timestamp,
            'properties' => [
                'login_method' => $this->authenticationMethod,
                'device_type' => $this->deviceInfo['type'] ?? 'unknown',
                'platform' => $this->deviceInfo['platform'] ?? 'unknown',
                'browser' => $this->deviceInfo['browser'] ?? 'unknown',
                'is_mobile' => $this->deviceInfo['is_mobile'] ?? false,
                'app_version' => $this->deviceInfo['app_version'] ?? null,
                'country' => $this->locationData['country'] ?? null,
                'timezone' => $this->locationData['timezone'] ?? null,
                'is_new_device' => $this->isNewDevice,
                'is_new_location' => $this->isNewLocation,
                'two_factor_used' => $this->loginContext['two_factor_used'] ?? false,
                'remember_me' => $this->loginContext['remember_me'] ?? false,
                'session_count' => $this->loginContext['active_sessions_count'] ?? 1,
                'days_since_last_login' => $this->user->last_login_at ? 
                    $this->timestamp->diffInDays($this->user->last_login_at) : null,
                'account_age_days' => $this->user->created_at->diffInDays($this->timestamp),
                'profile_completion' => $this->loginContext['profile_completion'] ?? 0,
                'login_source' => $this->loginContext['login_source'] ?? 'direct'
            ]
        ];
    }

    /**
     * Get presence data for real-time user status
     *
     * @return array
     */
    public function getPresenceData(): array
    {
        return [
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'status' => 'online',
            'last_seen' => $this->timestamp->toISOString(),
            'device_type' => $this->deviceInfo['type'] ?? 'unknown',
            'is_mobile' => $this->deviceInfo['is_mobile'] ?? false,
            'timezone' => $this->locationData['timezone'] ?? null,
            'active_sessions' => $this->loginContext['active_sessions_count'] ?? 1,
            'can_receive_messages' => $this->loginContext['can_receive_messages'] ?? true,
            'show_online_status' => $this->user->privacy_settings['show_online_status'] ?? true
        ];
    }

    /**
     * Get notification data for push notifications and alerts
     *
     * @return array
     */
    public function getNotificationData(): array
    {
        $notifications = [];
        
        // Notificación de nuevo dispositivo
        if ($this->isNewDevice) {
            $notifications[] = [
                'type' => 'security_alert',
                'title' => 'Nuevo dispositivo detectado',
                'message' => "Se detectó un inicio de sesión desde un nuevo dispositivo: {$this->deviceInfo['type']} en " . ($this->locationData['city'] ?? 'ubicación desconocida'),
                'priority' => 'high',
                'category' => 'security'
            ];
        }
        
        // Notificación de nueva ubicación
        if ($this->isNewLocation) {
            $notifications[] = [
                'type' => 'security_alert',
                'title' => 'Nueva ubicación detectada',
                'message' => "Se detectó un inicio de sesión desde {$this->locationData['city']}, {$this->locationData['country']}",
                'priority' => 'medium',
                'category' => 'security'
            ];
        }
        
        // Notificación de actividad sospechosa
        if ($this->loginContext['suspicious_activity'] ?? false) {
            $notifications[] = [
                'type' => 'security_warning',
                'title' => 'Actividad sospechosa detectada',
                'message' => 'Se detectó actividad inusual en tu cuenta. Revisa tu configuración de seguridad.',
                'priority' => 'critical',
                'category' => 'security'
            ];
        }
        
        return $notifications;
    }

    /**
     * Check if login requires additional verification
     *
     * @return bool
     */
    public function requiresAdditionalVerification(): bool
    {
        return $this->isNewDevice || 
               $this->isNewLocation || 
               ($this->loginContext['risk_score'] ?? 0) > 0.7 ||
               ($this->loginContext['suspicious_activity'] ?? false);
    }

    /**
     * Get recommended security actions for the user
     *
     * @return array
     */
    public function getRecommendedSecurityActions(): array
    {
        $actions = [];
        
        if ($this->isNewDevice) {
            $actions[] = [
                'action' => 'verify_device',
                'priority' => 'high',
                'description' => 'Verificar y autorizar el nuevo dispositivo'
            ];
        }
        
        if (!$this->user->two_factor_secret && 
            ($this->isNewDevice || $this->isNewLocation)) {
            $actions[] = [
                'action' => 'enable_2fa',
                'priority' => 'high',
                'description' => 'Activar autenticación de dos factores'
            ];
        }
        
        if (($this->loginContext['active_sessions_count'] ?? 1) > 3) {
            $actions[] = [
                'action' => 'review_sessions',
                'priority' => 'medium',
                'description' => 'Revisar y cerrar sesiones no utilizadas'
            ];
        }
        
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
            'user_id' => $this->user->id,
            'timestamp' => $this->timestamp->toISOString(),
            'authentication_method' => $this->authenticationMethod,
            'session_id' => $this->sessionId,
            'device_info' => $this->deviceInfo,
            'location_data' => $this->locationData,
            'login_context' => $this->loginContext,
            'is_new_device' => $this->isNewDevice,
            'is_new_location' => $this->isNewLocation
        ];
    }
}