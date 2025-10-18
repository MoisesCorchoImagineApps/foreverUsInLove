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
 * AccountDeleted Event
 * 
 * Evento disparado cuando una cuenta de usuario es eliminada
 * en la aplicación de citas ForeverUsInLove.
 * 
 * Este evento permite a otros componentes del sistema reaccionar
 * a la eliminación de cuentas para:
 * - Limpiar datos relacionados (fotos, mensajes, matches)
 * - Cancelar suscripciones activas
 * - Revocar tokens de acceso y sesiones
 * - Notificar a matches activos
 * - Registrar métricas de retención/churn
 * - Cumplir con regulaciones de privacidad (GDPR)
 * - Ejecutar procesos de cleanup y auditoría
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class AccountDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Datos del usuario eliminado (snapshot antes de eliminación)
     */
    public readonly array $userData;
    
    /**
     * Timestamp de la eliminación
     */
    public readonly Carbon $timestamp;
    
    /**
     * Método de eliminación utilizado
     */
    public readonly string $deletionMethod;
    
    /**
     * Razón de la eliminación
     */
    public readonly string $deletionReason;
    
    /**
     * Usuario que ejecutó la eliminación (admin, self, system)
     */
    public readonly ?User $deletedBy;
    
    /**
     * Información del contexto de eliminación
     */
    public readonly array $deletionContext;
    
    /**
     * Datos de los elementos relacionados que se eliminarán
     */
    public readonly array $relatedDataToDelete;
    
    /**
     * Configuración de retención de datos
     */
    public readonly array $dataRetentionConfig;
    
    /**
     * Indica si es eliminación física o soft delete
     */
    public readonly bool $isPermanentDeletion;
    
    /**
     * Período de gracia antes de eliminación permanente
     */
    public readonly ?Carbon $gracePeriodEnds;

    /**
     * Create a new event instance.
     *
     * @param array $userData Snapshot de datos del usuario antes de eliminación
     * @param string $deletionMethod Método usado (user_request, admin_action, automatic, gdpr_request)
     * @param string $deletionReason Razón específica de eliminación
     * @param User|null $deletedBy Usuario que ejecutó la eliminación
     * @param array $deletionContext Contexto adicional
     * @param array $relatedDataToDelete Datos relacionados a eliminar
     * @param array $dataRetentionConfig Configuración de retención
     * @param bool $isPermanentDeletion Si es eliminación permanente
     * @param Carbon|null $gracePeriodEnds Fin del período de gracia
     */
    public function __construct(
        array $userData,
        string $deletionMethod,
        string $deletionReason,
        ?User $deletedBy = null,
        array $deletionContext = [],
        array $relatedDataToDelete = [],
        array $dataRetentionConfig = [],
        bool $isPermanentDeletion = false,
        ?Carbon $gracePeriodEnds = null
    ) {
        $this->userData = $userData;
        $this->timestamp = now();
        $this->deletionMethod = $deletionMethod;
        $this->deletionReason = $deletionReason;
        $this->deletedBy = $deletedBy;
        $this->deletionContext = $deletionContext;
        $this->relatedDataToDelete = $relatedDataToDelete;
        $this->dataRetentionConfig = $dataRetentionConfig;
        $this->isPermanentDeletion = $isPermanentDeletion;
        $this->gracePeriodEnds = $gracePeriodEnds;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Canal administrativo para monitoreo de eliminaciones
            new PrivateChannel('admin.account-deletions'),
            
            // Canal para métricas y analytics
            new PrivateChannel('analytics.account-deletions'),
            
            // Canal para sistemas de cleanup
            new PrivateChannel('system.data-cleanup'),
            
            // Canal para notificaciones a matches (sin datos sensibles)
            new PrivateChannel('notifications.account-changes')
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
            'user_id' => $this->userData['id'],
            'username' => $this->userData['username'] ?? 'deleted_user',
            'timestamp' => $this->timestamp->toISOString(),
            
            // Información de eliminación (datos públicos)
            'deletion' => [
                'method' => $this->deletionMethod,
                'reason_category' => $this->getDeletionReasonCategory(),
                'is_permanent' => $this->isPermanentDeletion,
                'grace_period_ends' => $this->gracePeriodEnds?->toISOString(),
                'deleted_by_type' => $this->getDeletedByType()
            ],
            
            // Información para cleanup de datos relacionados
            'cleanup_required' => [
                'matches' => count($this->relatedDataToDelete['matches'] ?? []),
                'messages' => count($this->relatedDataToDelete['messages'] ?? []),
                'photos' => count($this->relatedDataToDelete['photos'] ?? []),
                'subscriptions' => count($this->relatedDataToDelete['subscriptions'] ?? []),
                'sessions' => count($this->relatedDataToDelete['sessions'] ?? [])
            ],
            
            // Configuración de retención
            'retention' => [
                'keep_analytics_data' => $this->dataRetentionConfig['keep_analytics'] ?? false,
                'retention_period_days' => $this->dataRetentionConfig['retention_days'] ?? 0,
                'anonymize_data' => $this->dataRetentionConfig['anonymize'] ?? true
            ],
            
            // Métricas para análisis
            'metrics' => [
                'account_age_days' => $this->getAccountAgeDays(),
                'activity_level' => $this->deletionContext['activity_level'] ?? 'unknown',
                'subscription_status' => $this->userData['subscription_status'] ?? 'free',
                'last_login_days_ago' => $this->getLastLoginDaysAgo()
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
        return 'account.deleted';
    }

    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function shouldBroadcast(): bool
    {
        // Solo hacer broadcast para sistemas internos
        return config('broadcasting.enabled', false) && 
               config('app.internal_notifications', false);
    }

    /**
     * Get comprehensive event metadata for logging and compliance
     *
     * @return array
     */
    public function getEventMetadata(): array
    {
        return [
            'event_type' => 'account_deleted',
            'event_version' => '2.0',
            'timestamp' => $this->timestamp->toISOString(),
            'user_id' => $this->userData['id'],
            'username' => $this->userData['username'] ?? 'deleted_user',
            'email' => $this->userData['email'] ?? 'deleted@domain.com',
            
            // Información de eliminación
            'deletion' => [
                'method' => $this->deletionMethod,
                'reason' => $this->deletionReason,
                'reason_category' => $this->getDeletionReasonCategory(),
                'is_permanent' => $this->isPermanentDeletion,
                'grace_period_ends' => $this->gracePeriodEnds?->toISOString(),
                'deleted_by' => [
                    'user_id' => $this->deletedBy?->id,
                    'username' => $this->deletedBy?->username,
                    'type' => $this->getDeletedByType()
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ],
            
            // Datos del usuario eliminado
            'user_data' => [
                'created_at' => $this->userData['created_at'] ?? null,
                'last_login_at' => $this->userData['last_login_at'] ?? null,
                'email_verified_at' => $this->userData['email_verified_at'] ?? null,
                'phone_verified_at' => $this->userData['phone_verified_at'] ?? null,
                'subscription_status' => $this->userData['subscription_status'] ?? 'free',
                'account_type' => $this->userData['account_type'] ?? 'free',
                'profile_completion' => $this->userData['profile_completion'] ?? 0,
                'age' => $this->userData['age'] ?? null,
                'gender' => $this->userData['gender'] ?? null,
                'location' => [
                    'country' => $this->userData['country'] ?? null,
                    'city' => $this->userData['city'] ?? null
                ]
            ],
            
            // Actividad y engagement del usuario
            'activity_metrics' => [
                'total_logins' => $this->deletionContext['total_logins'] ?? 0,
                'messages_sent' => $this->deletionContext['messages_sent'] ?? 0,
                'matches_made' => $this->deletionContext['matches_made'] ?? 0,
                'photos_uploaded' => $this->deletionContext['photos_uploaded'] ?? 0,
                'days_active' => $this->deletionContext['days_active'] ?? 0,
                'last_activity_at' => $this->userData['last_activity_at'] ?? null,
                'activity_level' => $this->deletionContext['activity_level'] ?? 'unknown'
            ],
            
            // Datos relacionados a eliminar
            'related_data' => [
                'matches' => [
                    'count' => count($this->relatedDataToDelete['matches'] ?? []),
                    'active_conversations' => count($this->relatedDataToDelete['active_conversations'] ?? [])
                ],
                'messages' => [
                    'sent_count' => count($this->relatedDataToDelete['messages_sent'] ?? []),
                    'received_count' => count($this->relatedDataToDelete['messages_received'] ?? [])
                ],
                'media' => [
                    'photos_count' => count($this->relatedDataToDelete['photos'] ?? []),
                    'videos_count' => count($this->relatedDataToDelete['videos'] ?? []),
                    'total_storage_mb' => $this->relatedDataToDelete['total_storage_mb'] ?? 0
                ],
                'financial' => [
                    'active_subscriptions' => count($this->relatedDataToDelete['subscriptions'] ?? []),
                    'payment_methods' => count($this->relatedDataToDelete['payment_methods'] ?? []),
                    'pending_charges' => $this->relatedDataToDelete['pending_charges'] ?? []
                ],
                'security' => [
                    'active_sessions' => count($this->relatedDataToDelete['sessions'] ?? []),
                    'api_tokens' => count($this->relatedDataToDelete['tokens'] ?? []),
                    'connected_devices' => count($this->relatedDataToDelete['devices'] ?? [])
                ]
            ],
            
            // Configuración de retención y compliance
            'compliance' => [
                'gdpr_request' => $this->deletionMethod === 'gdpr_request',
                'right_to_erasure' => $this->deletionContext['right_to_erasure'] ?? false,
                'data_retention_config' => $this->dataRetentionConfig,
                'anonymization_required' => $this->dataRetentionConfig['anonymize'] ?? true,
                'audit_trail_retention' => $this->dataRetentionConfig['audit_retention_years'] ?? 7
            ],
            
            // Contexto adicional
            'context' => array_merge($this->deletionContext, [
                'deletion_survey_response' => $this->deletionContext['survey_response'] ?? null,
                'feedback' => $this->deletionContext['user_feedback'] ?? null,
                'automated_trigger' => $this->deletionContext['automated_trigger'] ?? null,
                'policy_violation' => $this->deletionContext['policy_violation'] ?? null
            ])
        ];
    }

    /**
     * Get data for GDPR compliance and audit trails
     *
     * @return array
     */
    public function getComplianceData(): array
    {
        return [
            'deletion_request' => [
                'timestamp' => $this->timestamp->toISOString(),
                'user_id' => $this->userData['id'],
                'method' => $this->deletionMethod,
                'reason' => $this->deletionReason,
                'is_gdpr_request' => $this->deletionMethod === 'gdpr_request',
                'right_to_erasure_exercised' => $this->deletionContext['right_to_erasure'] ?? false
            ],
            'data_processing_record' => [
                'personal_data_categories' => $this->getPersonalDataCategories(),
                'processing_purposes' => $this->getDataProcessingPurposes(),
                'data_retention_periods' => $this->getDataRetentionPeriods(),
                'third_party_sharing' => $this->getThirdPartyDataSharing()
            ],
            'deletion_execution' => [
                'deletion_started_at' => $this->timestamp->toISOString(),
                'estimated_completion' => $this->getEstimatedDeletionCompletion(),
                'deletion_method' => $this->isPermanentDeletion ? 'hard_delete' : 'soft_delete',
                'grace_period' => $this->gracePeriodEnds?->toISOString(),
                'data_anonymization' => $this->dataRetentionConfig['anonymize'] ?? true
            ],
            'affected_systems' => $this->getAffectedSystems(),
            'compliance_verification' => [
                'audit_log_retention' => $this->dataRetentionConfig['audit_retention_years'] ?? 7,
                'verification_required' => $this->requiresComplianceVerification(),
                'data_controller_notified' => true,
                'data_processors_notified' => $this->deletionContext['processors_notified'] ?? false
            ]
        ];
    }

    /**
     * Get analytics data for churn analysis
     *
     * @return array
     */
    public function getAnalyticsData(): array
    {
        return [
            'event' => 'account_deleted',
            'user_id' => $this->userData['id'],
            'timestamp' => $this->timestamp->timestamp,
            'properties' => [
                'deletion_method' => $this->deletionMethod,
                'deletion_reason_category' => $this->getDeletionReasonCategory(),
                'account_age_days' => $this->getAccountAgeDays(),
                'last_login_days_ago' => $this->getLastLoginDaysAgo(),
                'subscription_status' => $this->userData['subscription_status'] ?? 'free',
                'account_type' => $this->userData['account_type'] ?? 'free',
                'user_age' => $this->userData['age'] ?? null,
                'user_gender' => $this->userData['gender'] ?? null,
                'user_country' => $this->userData['country'] ?? null,
                'profile_completion' => $this->userData['profile_completion'] ?? 0,
                'total_matches' => $this->deletionContext['matches_made'] ?? 0,
                'messages_sent' => $this->deletionContext['messages_sent'] ?? 0,
                'photos_uploaded' => $this->deletionContext['photos_uploaded'] ?? 0,
                'days_active' => $this->deletionContext['days_active'] ?? 0,
                'activity_level' => $this->deletionContext['activity_level'] ?? 'unknown',
                'had_premium_subscription' => $this->hadPremiumSubscription(),
                'total_revenue' => $this->deletionContext['total_revenue'] ?? 0,
                'churn_risk_score' => $this->deletionContext['churn_risk_score'] ?? null,
                'deleted_by_admin' => $this->getDeletedByType() === 'admin',
                'is_permanent_deletion' => $this->isPermanentDeletion
            ]
        ];
    }

    /**
     * Get cleanup tasks that need to be executed
     *
     * @return array
     */
    public function getCleanupTasks(): array
    {
        $tasks = [];
        
        // Cleanup de sesiones y tokens
        if (!empty($this->relatedDataToDelete['sessions'])) {
            $tasks[] = [
                'task' => 'revoke_sessions',
                'priority' => 'immediate',
                'data' => $this->relatedDataToDelete['sessions']
            ];
        }
        
        if (!empty($this->relatedDataToDelete['tokens'])) {
            $tasks[] = [
                'task' => 'revoke_tokens',
                'priority' => 'immediate',
                'data' => $this->relatedDataToDelete['tokens']
            ];
        }
        
        // Cleanup de suscripciones
        if (!empty($this->relatedDataToDelete['subscriptions'])) {
            $tasks[] = [
                'task' => 'cancel_subscriptions',
                'priority' => 'high',
                'data' => $this->relatedDataToDelete['subscriptions']
            ];
        }
        
        // Notificar a matches
        if (!empty($this->relatedDataToDelete['matches'])) {
            $tasks[] = [
                'task' => 'notify_matches',
                'priority' => 'medium',
                'data' => $this->relatedDataToDelete['matches']
            ];
        }
        
        // Cleanup de archivos multimedia
        if (!empty($this->relatedDataToDelete['photos']) || 
            !empty($this->relatedDataToDelete['videos'])) {
            $tasks[] = [
                'task' => 'delete_media_files',
                'priority' => 'low',
                'data' => [
                    'photos' => $this->relatedDataToDelete['photos'] ?? [],
                    'videos' => $this->relatedDataToDelete['videos'] ?? []
                ]
            ];
        }
        
        // Anonymización de datos si se requiere
        if ($this->dataRetentionConfig['anonymize'] ?? false) {
            $tasks[] = [
                'task' => 'anonymize_historical_data',
                'priority' => 'low',
                'data' => [
                    'user_id' => $this->userData['id'],
                    'retention_config' => $this->dataRetentionConfig
                ]
            ];
        }
        
        return $tasks;
    }

    /**
     * Get notifications that should be sent
     *
     * @return array
     */
    public function getNotifications(): array
    {
        $notifications = [];
        
        // Notificación de confirmación al usuario (si no es eliminación por admin)
        if ($this->deletionMethod !== 'admin_action' && !$this->isPermanentDeletion) {
            $notifications[] = [
                'type' => 'deletion_confirmation',
                'recipient' => $this->userData['email'],
                'template' => 'account_deletion_confirmation',
                'data' => [
                    'username' => $this->userData['username'],
                    'grace_period_ends' => $this->gracePeriodEnds?->format('Y-m-d H:i:s'),
                    'recovery_instructions' => true
                ]
            ];
        }
        
        // Notificaciones a matches activos (anonimizadas)
        if (!empty($this->relatedDataToDelete['active_conversations'])) {
            foreach ($this->relatedDataToDelete['active_conversations'] as $conversation) {
                $notifications[] = [
                    'type' => 'match_account_deleted',
                    'recipient_id' => $conversation['other_user_id'],
                    'template' => 'match_account_unavailable',
                    'data' => [
                        'conversation_id' => $conversation['id'],
                        'deleted_user_name' => 'Usuario eliminado'
                    ]
                ];
            }
        }
        
        // Notificación administrativa si es necesario
        if ($this->requiresAdminNotification()) {
            $notifications[] = [
                'type' => 'admin_account_deleted',
                'recipient' => config('app.admin_notifications_email'),
                'template' => 'admin_account_deletion',
                'data' => [
                    'user_id' => $this->userData['id'],
                    'deletion_method' => $this->deletionMethod,
                    'deletion_reason' => $this->deletionReason,
                    'account_value' => $this->getAccountValue()
                ]
            ];
        }
        
        return $notifications;
    }

    // ========================================
    // MÉTODOS PRIVADOS HELPER
    // ========================================

    /**
     * Get categorized deletion reason
     */
    private function getDeletionReasonCategory(): string
    {
        $reasonMap = [
            'user_request' => 'voluntary',
            'inactivity' => 'automatic',
            'policy_violation' => 'enforcement',
            'gdpr_request' => 'compliance',
            'admin_action' => 'administrative',
            'account_merge' => 'technical',
            'spam_detection' => 'security',
            'fraud_prevention' => 'security'
        ];
        
        return $reasonMap[$this->deletionReason] ?? 'other';
    }

    /**
     * Get type of user who deleted the account
     */
    private function getDeletedByType(): string
    {
        if (!$this->deletedBy) {
            return 'system';
        }
        
        if ($this->deletedBy->id === $this->userData['id']) {
            return 'self';
        }
        
        return 'admin';
    }

    /**
     * Calculate account age in days
     */
    private function getAccountAgeDays(): int
    {
        if (!isset($this->userData['created_at'])) {
            return 0;
        }
        
        $createdAt = Carbon::parse($this->userData['created_at']);
        return (int) $createdAt->diffInDays($this->timestamp);
    }

    /**
     * Calculate days since last login
     */
    private function getLastLoginDaysAgo(): ?int
    {
        if (!isset($this->userData['last_login_at'])) {
            return null;
        }
        
        $lastLogin = Carbon::parse($this->userData['last_login_at']);
        return (int) $lastLogin->diffInDays($this->timestamp);
    }

    /**
     * Check if user had premium subscription
     */
    private function hadPremiumSubscription(): bool
    {
        return in_array($this->userData['subscription_status'] ?? 'free', 
                       ['premium', 'gold', 'platinum']) ||
               ($this->deletionContext['total_revenue'] ?? 0) > 0;
    }

    /**
     * Get estimated deletion completion time
     */
    private function getEstimatedDeletionCompletion(): string
    {
        $delayHours = $this->isPermanentDeletion ? 72 : 24;
        return $this->timestamp->addHours($delayHours)->toISOString();
    }

    /**
     * Check if admin notification is required
     */
    private function requiresAdminNotification(): bool
    {
        return $this->hadPremiumSubscription() ||
               ($this->deletionContext['total_revenue'] ?? 0) > 100 ||
               $this->deletionMethod === 'gdpr_request' ||
               ($this->deletionContext['matches_made'] ?? 0) > 100;
    }

    /**
     * Get account monetary value
     */
    private function getAccountValue(): array
    {
        return [
            'total_revenue' => $this->deletionContext['total_revenue'] ?? 0,
            'subscription_status' => $this->userData['subscription_status'] ?? 'free',
            'lifetime_value_estimate' => $this->deletionContext['ltv_estimate'] ?? 0
        ];
    }

    /**
     * Check if compliance verification is required
     */
    private function requiresComplianceVerification(): bool
    {
        return $this->deletionMethod === 'gdpr_request' ||
               $this->deletionContext['right_to_erasure'] ?? false;
    }

    // Métodos helper adicionales para compliance
    private function getPersonalDataCategories(): array { return ['profile', 'messages', 'photos', 'location']; }
    private function getDataProcessingPurposes(): array { return ['matching', 'communication', 'analytics']; }
    private function getDataRetentionPeriods(): array { return $this->dataRetentionConfig['periods'] ?? []; }
    private function getThirdPartyDataSharing(): array { return $this->deletionContext['third_party_sharing'] ?? []; }
    private function getAffectedSystems(): array { return ['app', 'web', 'analytics', 'billing']; }

    /**
     * Convert event to array format for storage or serialization
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'user_data' => $this->userData,
            'timestamp' => $this->timestamp->toISOString(),
            'deletion_method' => $this->deletionMethod,
            'deletion_reason' => $this->deletionReason,
            'deleted_by' => $this->deletedBy?->only(['id', 'username']),
            'deletion_context' => $this->deletionContext,
            'related_data_to_delete' => $this->relatedDataToDelete,
            'data_retention_config' => $this->dataRetentionConfig,
            'is_permanent_deletion' => $this->isPermanentDeletion,
            'grace_period_ends' => $this->gracePeriodEnds?->toISOString()
        ];
    }
}