<?php

declare(strict_types=1);

namespace App\Domain\System\Events;

use App\Models\System\Setting;
use App\Models\System\Admin;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

/**
 * SettingUpdated Event
 * 
 * Evento disparado cuando una configuración del sistema es actualizada exitosamente
 * en la aplicación de citas ForeverUsInLove.
 * 
 * Este evento permite a otros componentes del sistema reaccionar
 * a la actualización de configuraciones para:
 * - Invalidar caché de configuraciones
 * - Notificar a administradores sobre cambios críticos
 * - Registrar auditoría de cambios de configuración
 * - Actualizar sistemas dependientes
 * - Activar procesos de reinicio si es necesario
 * - Sincronizar configuraciones entre servicios
 * - Registrar métricas de cambios administrativos
 * - Ejecutar validaciones de configuración
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class SettingUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Configuración que fue actualizada
     */
    public readonly Setting $setting;
    
    /**
     * Administrador que actualizó la configuración
     */
    public readonly ?Admin $updatedBy;
    
    /**
     * Timestamp del evento
     */
    public readonly Carbon $timestamp;
    
    /**
     * Método de actualización (manual, api, bulk, import)
     */
    public readonly string $updateMethod;
    
    /**
     * Valor anterior de la configuración
     */
    public readonly mixed $previousValue;
    
    /**
     * Nuevo valor de la configuración
     */
    public readonly mixed $newValue;
    
    /**
     * Contexto adicional de la actualización
     */
    public readonly array $updateContext;
    
    /**
     * Si la configuración requiere reinicio de aplicación
     */
    public readonly bool $requiresRestart;
    
    /**
     * Si es una configuración crítica del sistema
     */
    public readonly bool $isCriticalSetting;

    /**
     * Create a new event instance.
     *
     * @param Setting $setting Configuración que fue actualizada
     * @param Admin|null $updatedBy Administrador que ejecutó la actualización
     * @param string $updateMethod Método usado (manual, api, bulk, import, scheduled)
     * @param mixed $previousValue Valor anterior de la configuración
     * @param mixed $newValue Nuevo valor de la configuración
     * @param array $updateContext Contexto adicional de la actualización
     * @param bool $requiresRestart Si la configuración requiere reinicio
     * @param bool $isCriticalSetting Si es una configuración crítica
     */
    public function __construct(
        Setting $setting,
        ?Admin $updatedBy = null,
        string $updateMethod = 'manual',
        mixed $previousValue = null,
        mixed $newValue = null,
        array $updateContext = [],
        bool $requiresRestart = false,
        bool $isCriticalSetting = false
    ) {
        $this->setting = $setting;
        $this->updatedBy = $updatedBy;
        $this->timestamp = now();
        $this->updateMethod = $updateMethod;
        $this->previousValue = $previousValue;
        $this->newValue = $newValue ?? $setting->decoded_value;
        $this->updateContext = $updateContext;
        $this->requiresRestart = $requiresRestart || $setting->requires_restart;
        $this->isCriticalSetting = $isCriticalSetting || $this->isSettingCritical($setting);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Canal administrativo para notificaciones de configuración
            new PrivateChannel('admin.settings-updates'),
            
            // Canal para sistemas de caché
            new PrivateChannel('system.cache-invalidation'),
            
            // Canal para métricas administrativas
            new PrivateChannel('analytics.admin-actions'),
            
            // Canal para sistemas de monitoreo
            new PrivateChannel('monitoring.configuration-changes'),
            
            // Canal específico de la categoría de configuración
            new PrivateChannel("settings.{$this->setting->category}.updated"),
            
            // Canal para configuraciones críticas
            ...($this->isCriticalSetting ? [new PrivateChannel('admin.critical-settings')] : [])
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
            'setting_id' => $this->setting->id,
            'setting_key' => $this->setting->setting_key,
            'category' => $this->setting->category,
            'group' => $this->setting->group,
            'type' => $this->setting->type,
            'description' => $this->setting->description,
            
            // Valores de configuración
            'previous_value' => $this->previousValue,
            'new_value' => $this->newValue,
            'default_value' => $this->setting->default_value,
            
            // Información del administrador
            'updated_by' => $this->updatedBy ? [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
                'email' => $this->updatedBy->email
            ] : null,
            
            // Contexto de la actualización
            'update_method' => $this->updateMethod,
            'requires_restart' => $this->requiresRestart,
            'is_critical' => $this->isCriticalSetting,
            'is_public' => $this->setting->is_public,
            'is_encrypted' => $this->setting->is_encrypted,
            
            // Métricas de configuración
            'setting_metrics' => [
                'has_validation_rules' => !empty($this->setting->validation_rules),
                'has_options' => !empty($this->setting->options),
                'display_order' => $this->setting->display_order,
                'is_active' => $this->setting->is_active
            ],
            
            // Timestamp del evento
            'event_timestamp' => $this->timestamp->toISOString(),
            'updated_at' => $this->setting->updated_at?->toISOString()
        ];
    }

    /**
     * Get the broadcast event name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'setting.updated';
    }

    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function shouldBroadcast(): bool
    {
        // Solo hacer broadcast si las notificaciones de configuración están habilitadas
        return config('broadcasting.enabled', false) && 
               config('app.settings_notifications', true);
    }

    /**
     * Get comprehensive event metadata for logging and analytics
     *
     * @return array
     */
    public function getEventMetadata(): array
    {
        return [
            'event_type' => 'setting_updated',
            'event_version' => '1.0',
            'setting_id' => $this->setting->id,
            'setting_key' => $this->setting->setting_key,
            'category' => $this->setting->category,
            'group' => $this->setting->group,
            'type' => $this->setting->type,
            'updated_by_admin_id' => $this->updatedBy?->id,
            'update_method' => $this->updateMethod,
            'requires_restart' => $this->requiresRestart,
            'is_critical' => $this->isCriticalSetting,
            'timestamp' => $this->timestamp->toISOString(),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'session_id' => session()->getId(),
            
            // Contexto adicional
            'update_context' => $this->updateContext,
            
            // Comparación de valores
            'value_changed' => $this->previousValue !== $this->newValue,
            'value_type' => gettype($this->newValue),
            
            // Métricas de configuración
            'setting_stats' => [
                'has_validation' => !empty($this->setting->validation_rules),
                'has_options' => !empty($this->setting->options),
                'is_public' => $this->setting->is_public,
                'is_encrypted' => $this->setting->is_encrypted,
                'is_active' => $this->setting->is_active
            ]
        ];
    }

    /**
     * Get the setting key for notifications
     *
     * @return string
     */
    public function getSettingKey(): string
    {
        return $this->setting->setting_key;
    }

    /**
     * Get a summary of the updated setting
     *
     * @return array
     */
    public function getSettingSummary(): array
    {
        return [
            'key' => $this->setting->setting_key,
            'category' => $this->setting->category,
            'group' => $this->setting->group,
            'description' => $this->setting->description,
            'type' => $this->setting->type,
            'previous_value' => $this->previousValue,
            'new_value' => $this->newValue,
            'requires_restart' => $this->requiresRestart,
            'is_critical' => $this->isCriticalSetting,
            'updated_at' => $this->setting->updated_at?->toISOString()
        ];
    }

    /**
     * Check if this setting update requires immediate attention
     *
     * @return bool
     */
    public function requiresImmediateAttention(): bool
    {
        return $this->isCriticalSetting || 
               $this->requiresRestart || 
               $this->setting->category === Setting::CATEGORY_SECURITY_SETTINGS ||
               $this->setting->category === Setting::CATEGORY_APP_SETTINGS;
    }

    /**
     * Get actions that should be performed after this update
     *
     * @return array
     */
    public function getPostUpdateActions(): array
    {
        $actions = [
            'invalidate_cache',
            'log_change',
            'notify_admins'
        ];

        if ($this->requiresRestart) {
            $actions[] = 'schedule_restart';
            $actions[] = 'notify_restart_required';
        }

        if ($this->isCriticalSetting) {
            $actions[] = 'verify_configuration';
            $actions[] = 'notify_critical_change';
        }

        if ($this->setting->category === Setting::CATEGORY_SECURITY_SETTINGS) {
            $actions[] = 'audit_security_change';
            $actions[] = 'notify_security_team';
        }

        if ($this->setting->category === Setting::CATEGORY_FEATURE_FLAGS) {
            $actions[] = 'update_feature_flags';
            $actions[] = 'notify_feature_change';
        }

        return $actions;
    }

    /**
     * Check if a setting is critical based on its key and category
     *
     * @param Setting $setting
     * @return bool
     */
    private function isSettingCritical(Setting $setting): bool
    {
        $criticalKeys = [
            Setting::KEY_APP_DEBUG,
            Setting::KEY_APP_MAINTENANCE,
            Setting::KEY_SEC_2FA_REQUIRED,
            Setting::KEY_SEC_MAX_LOGIN_ATTEMPTS,
            Setting::KEY_PAY_PROVIDER,
            Setting::KEY_CONTENT_AUTO_MODERATION
        ];

        $criticalCategories = [
            Setting::CATEGORY_SECURITY_SETTINGS,
            Setting::CATEGORY_APP_SETTINGS,
            Setting::CATEGORY_PAYMENT_SETTINGS
        ];

        return in_array($setting->setting_key, $criticalKeys) || 
               in_array($setting->category, $criticalCategories);
    }
}
