<?php

declare(strict_types=1);

namespace App\Http\Resources\Chat;

use App\Models\Chat\Chat;
use App\Models\Chat\GroupChat;
use App\Models\Chat\VideoCall;
use App\Models\Chat\Message;
use App\Http\Resources\Auth\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * ChatResource - Resource para serialización de datos de chat
 * 
 * Este resource maneja la transformación y serialización de datos del modelo Chat
 * para respuestas de API, incluyendo filtrado de información sensible, formateo
 * de datos y agregación de información relacionada.
 * 
 * Funcionalidades:
 * - Transformación de datos de chat para API
 * - Filtrado de información sensible según contexto
 * - Formateo de fechas y datos especiales
 * - Agregación de información de participantes y mensajes
 * - Cálculo de métricas en tiempo real
 * - Soporte para diferentes contextos de visualización
 * - Compatibilidad con diferentes tipos de chat (private, group, video_call)
 * - Manejo de configuraciones y permisos de chat
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class ChatResource extends JsonResource
{
    /**
     * Contextos de visualización disponibles
     */
    public const CONTEXT_OWN = 'own';           // Chat propio del usuario
    public const CONTEXT_PUBLIC = 'public';     // Chat público para discovery
    public const CONTEXT_ADMIN = 'admin';       // Vista administrativa
    public const CONTEXT_ANALYTICS = 'analytics'; // Vista de analytics

    /**
     * Campos que nunca se deben exponer públicamente
     */
    private const PRIVATE_FIELDS = [
        'blocked_by_user_id',
        'blocked_reason',
        'metadata',
        'archived_at',
        'blocked_at'
    ];

    /**
     * Campos sensibles que solo se muestran al propietario
     */
    private const SENSITIVE_FIELDS = [
        'unread_count',
        'settings',
        'is_encrypted'
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
            // Información básica del chat
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'is_encrypted' => $this->isEncryptedForContext($context),
            'is_moderated' => $this->is_moderated,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Información del creador
            'creator' => $this->getCreatorData($context),
            
            // Información de participantes
            'participants' => $this->getParticipantsData($context),
            
            // Información específica del tipo de chat
            'chat_specific' => $this->getChatSpecificData($context),
            
            // Configuraciones y permisos
            'settings' => $this->getSettingsData($context),
            
            // Información de actividad
            'activity' => $this->getActivityData($context),
            
            // Información de mensajes
            'messages' => $this->getMessagesData($context),
            
            // Información de moderación
            'moderation' => $this->getModerationData($context),
            
            // Métricas y estadísticas
            'metrics' => $this->getMetrics($context),
            
            // Información de engagement
            'engagement' => $this->getEngagementData($context),
            
            // Información temporal
            'timeline' => $this->getTimelineData($context),
            
            // Información relacionada
            'related_data' => $this->getRelatedData($context),
        ];
    }

    /**
     * Obtiene datos del creador del chat
     */
    private function getCreatorData(string $context): ?array
    {
        if (!$this->relationLoaded('creator')) {
            return null;
        }

        return match ($context) {
            self::CONTEXT_OWN => UserResource::profile($this->creator, true)->toArray(request()),
            self::CONTEXT_ADMIN => UserResource::admin($this->creator, true)->toArray(request()),
            default => UserResource::basic($this->creator)->toArray(request()),
        };
    }

    /**
     * Obtiene datos de participantes del chat
     */
    private function getParticipantsData(string $context): array
    {
        if (!$this->relationLoaded('participants')) {
            return [];
        }

        $participants = $this->participants->map(function ($participant) use ($context) {
            $userData = match ($context) {
                self::CONTEXT_OWN => UserResource::profile($participant, true)->toArray(request()),
                self::CONTEXT_ADMIN => UserResource::admin($participant, true)->toArray(request()),
                default => UserResource::basic($participant)->toArray(request()),
            };

            // Agregar información específica del participante
            $userData['participant_info'] = [
                'role' => $participant->pivot->role ?? 'member',
                'status' => $participant->pivot->status ?? 'active',
                'joined_at' => $this->formatDateTime($participant->pivot->joined_at),
                'left_at' => $this->formatDateTime($participant->pivot->left_at),
                'last_read_at' => $this->formatDateTime($participant->pivot->last_read_at),
                'unread_count' => $context === self::CONTEXT_OWN ? ($participant->pivot->unread_count ?? 0) : null,
                'is_muted' => $participant->pivot->is_muted ?? false,
                'is_pinned' => $participant->pivot->is_pinned ?? false,
                'is_archived' => $participant->pivot->is_archived ?? false,
                'notifications_enabled' => $participant->pivot->notifications_enabled ?? true,
            ];

            return $this->filterNullValues($userData);
        });

        return $participants->toArray();
    }

    /**
     * Obtiene información específica del tipo de chat
     */
    private function getChatSpecificData(string $context): array
    {
        $data = [];

        switch ($this->type) {
            case Chat::TYPE_GROUP:
                $data = $this->getGroupChatData($context);
                break;
            case Chat::TYPE_VIDEO_CALL:
                $data = $this->getVideoCallData($context);
                break;
            case Chat::TYPE_PRIVATE:
            default:
                $data = $this->getPrivateChatData($context);
                break;
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene datos específicos para chat grupal
     */
    private function getGroupChatData(string $context): array
    {
        if (!$this->relationLoaded('chatable') || !$this->chatable instanceof GroupChat) {
            return [];
        }

        $groupChat = $this->chatable;

        $data = [
            'group_info' => [
                'id' => $groupChat->id,
                'name' => $groupChat->name,
                'description' => $groupChat->description,
                'type' => $groupChat->type,
                'status' => $groupChat->status,
                'avatar_url' => $groupChat->avatar_url,
                'member_count' => $groupChat->member_count,
                'max_members' => $groupChat->max_members,
                'activity_score' => $groupChat->activity_score,
                'is_full' => $groupChat->isFull(),
                'has_available_slots' => $groupChat->hasAvailableSlots(),
            ]
        ];

        // Solo mostrar configuraciones detalladas en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['group_info']['privacy_settings'] = $groupChat->privacy_settings;
            $data['group_info']['moderation_settings'] = $groupChat->moderation_settings;
            $data['group_info']['theme_data'] = $groupChat->theme_data;
            $data['group_info']['event_data'] = $groupChat->event_data;
        }

        return $data;
    }

    /**
     * Obtiene datos específicos para videollamada
     */
    private function getVideoCallData(string $context): array
    {
        if (!$this->relationLoaded('chatable') || !$this->chatable instanceof VideoCall) {
            return [];
        }

        $videoCall = $this->chatable;

        $data = [
            'video_call_info' => [
                'id' => $videoCall->id,
                'type' => $videoCall->type,
                'status' => $videoCall->status,
                'session_id' => $videoCall->session_id,
                'participant_count' => $videoCall->participant_count,
                'max_participants' => $videoCall->max_participants,
                'duration_seconds' => $videoCall->duration_seconds,
                'duration_formatted' => $videoCall->getFormattedDuration(),
                'end_reason' => $videoCall->end_reason,
                'average_quality_score' => $videoCall->average_quality_score,
                'is_recorded' => $videoCall->is_recorded,
                'is_video_enabled' => $videoCall->is_video_enabled,
                'is_audio_enabled' => $videoCall->is_audio_enabled,
                'is_screen_shared' => $videoCall->is_screen_shared,
                'is_active' => $videoCall->isActive(),
                'is_full' => $videoCall->isFull(),
                'started_at' => $this->formatDateTime($videoCall->started_at),
                'connected_at' => $this->formatDateTime($videoCall->connected_at),
                'ended_at' => $this->formatDateTime($videoCall->ended_at),
            ]
        ];

        // Solo mostrar configuraciones técnicas en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['video_call_info']['quality_settings'] = $videoCall->quality_settings;
            $data['video_call_info']['recording_data'] = $videoCall->recording_data;
            $data['video_call_info']['quality_metrics'] = $videoCall->quality_metrics;
        }

        return $data;
    }

    /**
     * Obtiene datos específicos para chat privado
     */
    private function getPrivateChatData(string $context): array
    {
        return [
            'private_chat_info' => [
                'is_private' => true,
                'participant_count' => $this->participant_count,
            ]
        ];
    }

    /**
     * Obtiene configuraciones del chat
     */
    private function getSettingsData(string $context): array
    {
        // Solo mostrar configuraciones en contexto propio o admin
        if (!in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            return [];
        }

        return [
            'notifications_enabled' => $this->areNotificationsEnabled(),
            'sound_enabled' => $this->isSoundEnabled(),
            'typing_indicators' => $this->areTypingIndicatorsEnabled(),
            'read_receipts' => $this->areReadReceiptsEnabled(),
            'auto_archive_after_days' => $this->getSetting('auto_archive_after_days', 90),
            'message_retention_days' => $this->getSetting('message_retention_days', 365),
            'allow_media' => $this->getSetting('allow_media', true),
            'allow_voice' => $this->getSetting('allow_voice', true),
            'allow_files' => $this->getSetting('allow_files', true),
            'max_file_size_mb' => $this->getSetting('max_file_size_mb', 50),
        ];
    }

    /**
     * Obtiene información de actividad del chat
     */
    private function getActivityData(string $context): array
    {
        $data = [
            'last_message_at' => $this->formatDateTime($this->last_message_at),
            'last_activity_at' => $this->formatDateTime($this->last_activity_at),
            'is_session_active' => $this->isSessionActive(),
            'message_count' => $this->message_count,
            'participant_count' => $this->participant_count,
        ];

        // Solo mostrar conteos de no leídos en contexto propio
        if ($context === self::CONTEXT_OWN) {
            $data['unread_count'] = $this->unread_count;
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de mensajes
     */
    private function getMessagesData(string $context): array
    {
        $data = [];

        // Información del último mensaje
        if ($this->relationLoaded('lastMessage')) {
            $lastMessage = $this->lastMessage->first();
            if ($lastMessage) {
                $data['last_message'] = [
                    'id' => $lastMessage->id,
                    'content' => $context === self::CONTEXT_OWN ? $lastMessage->content : $this->sanitizeMessageContent($lastMessage->content),
                    'type' => $lastMessage->type,
                    'sender_id' => $lastMessage->sender_id,
                    'sender_name' => $lastMessage->relationLoaded('sender') ? $lastMessage->sender->username : null,
                    'created_at' => $this->formatDateTime($lastMessage->created_at),
                    'is_edited' => $lastMessage->is_edited,
                    'is_deleted_by_sender' => $lastMessage->is_deleted_by_sender,
                ];
            }
        }

        // Información de mensajes recientes (solo en contexto propio)
        if ($context === self::CONTEXT_OWN && $this->relationLoaded('messages')) {
            $data['recent_messages'] = $this->messages
                ->take(10)
                ->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'content' => $message->is_deleted_by_sender ? '[Mensaje eliminado]' : $message->content,
                        'type' => $message->type,
                        'sender_id' => $message->sender_id,
                        'created_at' => $this->formatDateTime($message->created_at),
                        'status' => $message->status,
                        'is_edited' => $message->is_edited,
                        'has_attachments' => $message->hasAttachments(),
                    ];
                })
                ->toArray();
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de moderación
     */
    private function getModerationData(string $context): array
    {
        // Solo mostrar información de moderación en contexto admin
        if ($context !== self::CONTEXT_ADMIN) {
            return [];
        }

        $data = [
            'is_moderated' => $this->is_moderated,
            'blocked_at' => $this->formatDateTime($this->blocked_at),
            'blocked_by_user_id' => $this->blocked_by_user_id,
            'blocked_reason' => $this->blocked_reason,
            'archived_at' => $this->formatDateTime($this->archived_at),
        ];

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene métricas del chat
     */
    private function getMetrics(string $context): array
    {
        $data = [
            'basic' => [
                'message_count' => $this->message_count,
                'participant_count' => $this->participant_count,
                'engagement_score' => $this->getEngagementScore(),
                'duration_days' => $this->created_at->diffInDays(now()),
            ]
        ];

        // Métricas detalladas solo para contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['detailed'] = [
                'unread_count' => $this->unread_count,
                'average_messages_per_day' => $this->getAverageMessagesPerDay(),
                'is_session_active' => $this->isSessionActive(),
                'statistics' => $this->getStatistics(),
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de engagement
     */
    private function getEngagementData(string $context): array
    {
        $data = [
            'engagement_score' => $this->getEngagementScore(),
            'is_active' => $this->isActive(),
            'activity_level' => $this->getActivityLevel(),
        ];

        // Solo mostrar análisis detallado en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['engagement_metrics'] = [
                'average_messages_per_day' => $this->getAverageMessagesPerDay(),
                'session_activity' => $this->isSessionActive(),
                'participant_engagement' => $this->getParticipantEngagement(),
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información temporal del chat
     */
    private function getTimelineData(string $context): array
    {
        $data = [
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            'last_message_at' => $this->formatDateTime($this->last_message_at),
            'last_activity_at' => $this->formatDateTime($this->last_activity_at),
            'age_in_days' => $this->created_at->diffInDays(now()),
        ];

        // Solo mostrar información de archivo en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['archived_at'] = $this->formatDateTime($this->archived_at);
            $data['blocked_at'] = $this->formatDateTime($this->blocked_at);
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene datos relacionados
     */
    private function getRelatedData(string $context): array
    {
        $data = [];

        // Información del chatable (GroupChat o VideoCall)
        if ($this->relationLoaded('chatable') && $this->chatable) {
            $data['chatable'] = [
                'type' => get_class($this->chatable),
                'id' => $this->chatable->id,
            ];

            // Agregar información específica según el tipo
            if ($this->chatable instanceof GroupChat) {
                $data['chatable']['name'] = $this->chatable->name;
                $data['chatable']['description'] = $this->chatable->description;
                $data['chatable']['member_count'] = $this->chatable->member_count;
            } elseif ($this->chatable instanceof VideoCall) {
                $data['chatable']['status'] = $this->chatable->status;
                $data['chatable']['duration_seconds'] = $this->chatable->duration_seconds;
                $data['chatable']['participant_count'] = $this->chatable->participant_count;
            }
        }

        return $this->filterNullValues($data);
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

        // Si el chat incluye al usuario autenticado, es contexto propio
        if ($this->hasParticipant($user->id)) {
            return self::CONTEXT_OWN;
        }

        // Si es admin, contexto administrativo
        if ($user->is_admin ?? false) {
            // Si está en ruta de analytics, contexto específico
            if (str_contains($request->path(), 'analytics')) {
                return self::CONTEXT_ANALYTICS;
            }
            return self::CONTEXT_ADMIN;
        }

        // Por defecto, contexto público
        return self::CONTEXT_PUBLIC;
    }

    /**
     * Verifica si el chat está encriptado para el contexto dado
     */
    private function isEncryptedForContext(string $context): ?bool
    {
        // Solo mostrar información de encriptación en contexto propio o admin
        if (!in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            return null;
        }

        return $this->is_encrypted;
    }

    /**
     * Sanitiza el contenido del mensaje para mostrar públicamente
     */
    private function sanitizeMessageContent(?string $content): ?string
    {
        if (!$content) {
            return null;
        }

        // Ocultar contenido sensible o personal
        if (strlen($content) > 50) {
            return substr($content, 0, 50) . '...';
        }

        return $content;
    }

    /**
     * Obtiene el nivel de actividad del chat
     */
    private function getActivityLevel(): string
    {
        $engagementScore = $this->getEngagementScore();
        
        if ($engagementScore >= 80) {
            return 'high';
        } elseif ($engagementScore >= 50) {
            return 'medium';
        } elseif ($engagementScore >= 20) {
            return 'low';
        }
        
        return 'inactive';
    }

    /**
     * Obtiene el engagement de participantes
     */
    private function getParticipantEngagement(): float
    {
        if (!$this->relationLoaded('participants') || $this->participant_count <= 1) {
            return 0.0;
        }

        $activeParticipants = $this->participants
            ->filter(function ($participant) {
                $lastReadAt = $participant->pivot->last_read_at ?? null;
                return $lastReadAt && Carbon::parse($lastReadAt)->isAfter(now()->subDays(7));
            })
            ->count();

        return round(($activeParticipants / $this->participant_count) * 100, 2);
    }

    /**
     * Formatea una fecha y hora para la respuesta
     */
    private function formatDateTime(?Carbon $dateTime): ?string
    {
        return $dateTime?->toISOString();
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
                'chat_type_info' => $this->getChatTypeInfo(),
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
            self::CONTEXT_OWN => 60,           // 1 minuto para chat propio
            self::CONTEXT_PUBLIC => 1800,      // 30 minutos para chat público
            self::CONTEXT_ADMIN => 30,         // 30 segundos para vista admin
            self::CONTEXT_ANALYTICS => 900,    // 15 minutos para analytics
            default => 300,                    // 5 minutos por defecto
        };
    }

    /**
     * Obtiene información del tipo de chat
     */
    private function getChatTypeInfo(): array
    {
        return [
            'type' => $this->type,
            'description' => $this->getChatTypeDescription(),
            'features' => $this->getChatTypeFeatures(),
            'limits' => $this->getChatTypeLimits(),
        ];
    }

    /**
     * Obtiene descripción del tipo de chat
     */
    private function getChatTypeDescription(): string
    {
        return match ($this->type) {
            Chat::TYPE_PRIVATE => 'Chat privado',
            Chat::TYPE_GROUP => 'Chat grupal',
            Chat::TYPE_VIDEO_CALL => 'Videollamada',
            default => 'Chat desconocido',
        };
    }

    /**
     * Obtiene características del tipo de chat
     */
    private function getChatTypeFeatures(): array
    {
        return match ($this->type) {
            Chat::TYPE_PRIVATE => [
                'encryption' => true,
                'video_calls' => true,
                'file_sharing' => true,
                'message_history' => true,
            ],
            Chat::TYPE_GROUP => [
                'multiple_participants' => true,
                'admin_controls' => true,
                'group_settings' => true,
                'moderation' => true,
            ],
            Chat::TYPE_VIDEO_CALL => [
                'real_time_video' => true,
                'screen_sharing' => true,
                'recording' => true,
                'quality_control' => true,
            ],
            default => [],
        };
    }

    /**
     * Obtiene límites del tipo de chat
     */
    private function getChatTypeLimits(): array
    {
        return match ($this->type) {
            Chat::TYPE_PRIVATE => [
                'max_participants' => 2,
                'max_message_length' => 2000,
                'max_file_size_mb' => 50,
            ],
            Chat::TYPE_GROUP => [
                'max_participants' => 50,
                'max_message_length' => 2000,
                'max_file_size_mb' => 50,
            ],
            Chat::TYPE_VIDEO_CALL => [
                'max_participants' => 12,
                'max_duration_hours' => 4,
                'max_recording_size_gb' => 5,
            ],
            default => [],
        };
    }
}
