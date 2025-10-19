<?php

declare(strict_types=1);

namespace App\Http\Resources\Chat;

use App\Models\Chat\Message;
use App\Models\Chat\Chat;
use App\Http\Resources\Auth\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * MessageResource - Resource para serialización de datos de mensajes
 * 
 * Este resource maneja la transformación y serialización de datos del modelo Message
 * para respuestas de API, incluyendo filtrado de información sensible, formateo
 * de datos y agregación de información relacionada.
 * 
 * Funcionalidades:
 * - Transformación de datos de mensaje para API
 * - Filtrado de información sensible según contexto
 * - Formateo de fechas y datos especiales
 * - Agregación de información de sender y chat
 * - Cálculo de métricas en tiempo real
 * - Soporte para diferentes contextos de visualización
 * - Compatibilidad con diferentes tipos de mensaje (text, media, system, etc.)
 * - Manejo de threading de respuestas y reacciones
 * - Soporte para moderación y delivery tracking
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class MessageResource extends JsonResource
{
    /**
     * Contextos de visualización disponibles
     */
    public const CONTEXT_OWN = 'own';           // Mensaje propio del usuario
    public const CONTEXT_PUBLIC = 'public';     // Mensaje público para discovery
    public const CONTEXT_ADMIN = 'admin';       // Vista administrativa
    public const CONTEXT_ANALYTICS = 'analytics'; // Vista de analytics

    /**
     * Campos que nunca se deben exponer públicamente
     */
    private const PRIVATE_FIELDS = [
        'encrypted_content',
        'moderation_data',
        'moderated_by_user_id',
        'failure_reason',
        'metadata',
        'edit_history',
        'sentiment_score',
        'spam_probability'
    ];

    /**
     * Campos sensibles que solo se muestran al propietario
     */
    private const SENSITIVE_FIELDS = [
        'read_by',
        'delivered_to',
        'delivered_at',
        'read_at',
        'failed_at',
        'scheduled_at',
        'expires_at'
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
            // Información básica del mensaje
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'content' => $this->getContentForContext($context),
            'is_encrypted' => $this->isEncryptedForContext($context),
            'is_edited' => $this->is_edited,
            'is_deleted_by_sender' => $this->is_deleted_by_sender,
            'is_system_message' => $this->is_system_message,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Información del remitente
            'sender' => $this->getSenderData($context),
            
            // Información del chat
            'chat' => $this->getChatData($context),
            
            // Información de threading (respuestas)
            'threading' => $this->getThreadingData($context),
            
            // Información de attachments
            'attachments' => $this->getAttachmentsData($context),
            
            // Información de delivery tracking
            'delivery' => $this->getDeliveryData($context),
            
            // Información de moderación
            'moderation' => $this->getModerationData($context),
            
            // Información de engagement
            'engagement' => $this->getEngagementData($context),
            
            // Información temporal
            'timeline' => $this->getTimelineData($context),
            
            // Información de configuración
            'configuration' => $this->getConfigurationData($context),
            
            // Información relacionada
            'related_data' => $this->getRelatedData($context),
        ];
    }

    /**
     * Obtiene datos del remitente del mensaje
     */
    private function getSenderData(string $context): ?array
    {
        if (!$this->relationLoaded('sender')) {
            return [
                'id' => $this->sender_id,
                'username' => null,
                'display_name' => null,
            ];
        }

        return match ($context) {
            self::CONTEXT_OWN => UserResource::profile($this->sender, true)->toArray(request()),
            self::CONTEXT_ADMIN => UserResource::admin($this->sender, true)->toArray(request()),
            default => UserResource::basic($this->sender)->toArray(request()),
        };
    }

    /**
     * Obtiene datos del chat
     */
    private function getChatData(string $context): ?array
    {
        if (!$this->relationLoaded('chat')) {
            return [
                'id' => $this->chat_id,
                'type' => null,
                'status' => null,
            ];
        }

        $chatData = [
            'id' => $this->chat->id,
            'type' => $this->chat->type,
            'status' => $this->chat->status,
            'is_encrypted' => $this->chat->is_encrypted,
            'participant_count' => $this->chat->participant_count,
        ];

        // Solo mostrar información detallada en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $chatData['creator_id'] = $this->chat->creator_id;
            $chatData['message_count'] = $this->chat->message_count;
            $chatData['last_message_at'] = $this->formatDateTime($this->chat->last_message_at);
            $chatData['last_activity_at'] = $this->formatDateTime($this->chat->last_activity_at);
        }

        return $this->filterNullValues($chatData);
    }

    /**
     * Obtiene información de threading (respuestas)
     */
    private function getThreadingData(string $context): array
    {
        $data = [
            'is_reply' => $this->isReply(),
            'has_replies' => $this->hasReplies(),
            'reply_count' => $this->reply_count,
        ];

        // Información del mensaje padre (si es una respuesta)
        if ($this->isReply() && $this->relationLoaded('parent')) {
            $data['parent_message'] = [
                'id' => $this->parent->id,
                'content' => $this->getContentForContext($context, $this->parent),
                'type' => $this->parent->type,
                'sender_id' => $this->parent->sender_id,
                'sender_name' => $this->parent->relationLoaded('sender') ? $this->parent->sender->username : null,
                'created_at' => $this->formatDateTime($this->parent->created_at),
            ];
        }

        // Información de respuestas (solo en contexto propio o admin)
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN]) && $this->relationLoaded('replies')) {
            $data['replies'] = $this->replies
                ->take(5) // Solo las primeras 5 respuestas
                ->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'content' => $reply->is_deleted_by_sender ? '[Mensaje eliminado]' : $reply->content,
                        'type' => $reply->type,
                        'sender_id' => $reply->sender_id,
                        'sender_name' => $reply->relationLoaded('sender') ? $reply->sender->username : null,
                        'created_at' => $this->formatDateTime($reply->created_at),
                        'status' => $reply->status,
                        'is_edited' => $reply->is_edited,
                    ];
                })
                ->toArray();
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de attachments
     */
    private function getAttachmentsData(string $context): array
    {
        if (!$this->hasAttachments()) {
            return [];
        }

        $attachments = $this->attachments ?? [];
        $processedAttachments = [];

        foreach ($attachments as $attachment) {
            $attachmentData = [
                'type' => $attachment['type'] ?? null,
                'filename' => $attachment['filename'] ?? null,
                'size' => $attachment['size'] ?? null,
                'mime_type' => $attachment['mime_type'] ?? null,
            ];

            // Solo mostrar URLs en contexto propio o admin
            if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
                $attachmentData['url'] = $attachment['url'] ?? null;
                $attachmentData['thumbnail_url'] = $attachment['thumbnail_url'] ?? null;
                $attachmentData['duration'] = $attachment['duration'] ?? null;
                $attachmentData['width'] = $attachment['width'] ?? null;
                $attachmentData['height'] = $attachment['height'] ?? null;
            }

            $processedAttachments[] = $this->filterNullValues($attachmentData);
        }

        return $processedAttachments;
    }

    /**
     * Obtiene información de delivery tracking
     */
    private function getDeliveryData(string $context): array
    {
        $data = [
            'status' => $this->status,
            'is_sent' => $this->isSent(),
            'is_delivered' => $this->isDelivered(),
            'is_read' => $this->isRead(),
            'is_failed' => $this->isFailed(),
        ];

        // Solo mostrar información detallada de delivery en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['delivered_at'] = $this->formatDateTime($this->delivered_at);
            $data['read_at'] = $this->formatDateTime($this->read_at);
            $data['failed_at'] = $this->formatDateTime($this->failed_at);
            $data['failure_reason'] = $this->failure_reason;
            
            // Estadísticas de delivery
            $data['delivery_stats'] = $this->getDeliveryStats();
            
            // Información de lectura por usuario
            if ($this->read_by) {
                $data['read_by'] = array_map(function ($timestamp) {
                    return $this->formatDateTime(Carbon::parse($timestamp));
                }, $this->read_by);
            }
            
            // Información de entrega por usuario
            if ($this->delivered_to) {
                $data['delivered_to'] = array_map(function ($timestamp) {
                    return $this->formatDateTime(Carbon::parse($timestamp));
                }, $this->delivered_to);
            }
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
            'moderation_status' => $this->moderation_status,
            'moderated_at' => $this->formatDateTime($this->moderated_at),
            'moderated_by_user_id' => $this->moderated_by_user_id,
            'moderation_data' => $this->moderation_data,
        ];

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de engagement
     */
    private function getEngagementData(string $context): array
    {
        $data = [
            'reply_count' => $this->reply_count,
            'reaction_count' => $this->reaction_count,
            'is_edited' => $this->is_edited,
            'edit_count' => count($this->edit_history ?? []),
        ];

        // Solo mostrar análisis detallado en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['engagement_metrics'] = $this->getEngagementMetrics();
            $data['sentiment_score'] = $this->sentiment_score;
            $data['spam_probability'] = $this->spam_probability;
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información temporal del mensaje
     */
    private function getTimelineData(string $context): array
    {
        $data = [
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            'age_in_minutes' => $this->created_at->diffInMinutes(now()),
            'can_be_edited' => $this->canBeEdited(),
            'can_be_deleted' => $this->canBeDeleted(),
        ];

        // Solo mostrar información de edición en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['edited_at'] = $this->formatDateTime($this->edited_at);
            $data['deleted_by_sender_at'] = $this->formatDateTime($this->deleted_by_sender_at);
            $data['scheduled_at'] = $this->formatDateTime($this->scheduled_at);
            $data['expires_at'] = $this->formatDateTime($this->expires_at);
            $data['is_scheduled'] = $this->isScheduled();
            $data['is_expired'] = $this->isExpired();
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de configuración
     */
    private function getConfigurationData(string $context): array
    {
        $data = [
            'type_info' => $this->getMessageTypeInfo(),
            'limits' => $this->getMessageLimits(),
        ];

        // Solo mostrar configuración detallada en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data['metadata'] = $this->metadata;
            $data['edit_history'] = $this->edit_history;
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene datos relacionados
     */
    private function getRelatedData(string $context): array
    {
        $data = [];

        // Información del chatable (si el chat es grupal o videollamada)
        if ($this->relationLoaded('chat') && $this->chat->relationLoaded('chatable') && $this->chat->chatable) {
            $data['chatable'] = [
                'type' => get_class($this->chat->chatable),
                'id' => $this->chat->chatable->id,
            ];

            // Agregar información específica según el tipo
            if ($this->chat->chatable instanceof \App\Models\Chat\GroupChat) {
                $data['chatable']['name'] = $this->chat->chatable->name;
                $data['chatable']['description'] = $this->chat->chatable->description;
            } elseif ($this->chat->chatable instanceof \App\Models\Chat\VideoCall) {
                $data['chatable']['status'] = $this->chat->chatable->status;
                $data['chatable']['duration_seconds'] = $this->chat->chatable->duration_seconds;
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

        // Si el mensaje es del usuario autenticado, es contexto propio
        if ($this->sender_id === $user->id) {
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

        // Si el usuario es participante del chat, contexto propio
        if ($this->relationLoaded('chat') && $this->chat->hasParticipant($user->id)) {
            return self::CONTEXT_OWN;
        }

        // Por defecto, contexto público
        return self::CONTEXT_PUBLIC;
    }

    /**
     * Obtiene el contenido del mensaje según el contexto
     */
    private function getContentForContext(string $context, ?Message $message = null): ?string
    {
        $targetMessage = $message ?? $this;
        
        // Si el mensaje fue eliminado por el remitente
        if ($targetMessage->is_deleted_by_sender) {
            return '[Mensaje eliminado]';
        }

        // Si es mensaje del sistema, siempre mostrar contenido
        if ($targetMessage->is_system_message) {
            return $targetMessage->content;
        }

        // En contexto público, sanitizar contenido
        if ($context === self::CONTEXT_PUBLIC) {
            return $this->sanitizeMessageContent($targetMessage->content);
        }

        // En otros contextos, mostrar contenido completo
        return $targetMessage->content;
    }

    /**
     * Verifica si el mensaje está encriptado para el contexto dado
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
        if (strlen($content) > 100) {
            return substr($content, 0, 100) . '...';
        }

        return $content;
    }

    /**
     * Obtiene información del tipo de mensaje
     */
    private function getMessageTypeInfo(): array
    {
        return [
            'type' => $this->type,
            'description' => $this->getMessageTypeDescription(),
            'features' => $this->getMessageTypeFeatures(),
            'is_media' => $this->isMedia(),
            'has_attachments' => $this->hasAttachments(),
        ];
    }

    /**
     * Obtiene descripción del tipo de mensaje
     */
    private function getMessageTypeDescription(): string
    {
        return match ($this->type) {
            Message::TYPE_TEXT => 'Mensaje de texto',
            Message::TYPE_IMAGE => 'Imagen',
            Message::TYPE_VIDEO => 'Video',
            Message::TYPE_VOICE => 'Mensaje de voz',
            Message::TYPE_FILE => 'Archivo',
            Message::TYPE_STICKER => 'Sticker',
            Message::TYPE_GIF => 'GIF',
            Message::TYPE_SYSTEM => 'Mensaje del sistema',
            Message::TYPE_ICEBREAKER => 'Rompehielos',
            default => 'Tipo desconocido',
        };
    }

    /**
     * Obtiene características del tipo de mensaje
     */
    private function getMessageTypeFeatures(): array
    {
        return match ($this->type) {
            Message::TYPE_TEXT => [
                'editable' => true,
                'deletable' => true,
                'replyable' => true,
                'reactable' => true,
            ],
            Message::TYPE_IMAGE => [
                'editable' => false,
                'deletable' => true,
                'replyable' => true,
                'reactable' => true,
                'downloadable' => true,
            ],
            Message::TYPE_VIDEO => [
                'editable' => false,
                'deletable' => true,
                'replyable' => true,
                'reactable' => true,
                'downloadable' => true,
                'streamable' => true,
            ],
            Message::TYPE_VOICE => [
                'editable' => false,
                'deletable' => true,
                'replyable' => true,
                'reactable' => true,
                'playable' => true,
            ],
            Message::TYPE_FILE => [
                'editable' => false,
                'deletable' => true,
                'replyable' => true,
                'reactable' => true,
                'downloadable' => true,
            ],
            Message::TYPE_STICKER => [
                'editable' => false,
                'deletable' => true,
                'replyable' => true,
                'reactable' => true,
            ],
            Message::TYPE_GIF => [
                'editable' => false,
                'deletable' => true,
                'replyable' => true,
                'reactable' => true,
                'animated' => true,
            ],
            Message::TYPE_SYSTEM => [
                'editable' => false,
                'deletable' => false,
                'replyable' => false,
                'reactable' => false,
            ],
            Message::TYPE_ICEBREAKER => [
                'editable' => false,
                'deletable' => true,
                'replyable' => true,
                'reactable' => true,
                'special' => true,
            ],
            default => [],
        };
    }

    /**
     * Obtiene límites del tipo de mensaje
     */
    private function getMessageLimits(): array
    {
        return match ($this->type) {
            Message::TYPE_TEXT => [
                'max_length' => Message::MAX_TEXT_LENGTH,
                'edit_window_minutes' => Message::EDIT_WINDOW_MINUTES,
                'delete_window_hours' => Message::DELETE_WINDOW_HOURS,
            ],
            Message::TYPE_IMAGE => [
                'max_size_mb' => Message::MAX_IMAGE_SIZE_MB,
                'delete_window_hours' => Message::DELETE_WINDOW_HOURS,
            ],
            Message::TYPE_VIDEO => [
                'max_size_mb' => Message::MAX_VIDEO_SIZE_MB,
                'delete_window_hours' => Message::DELETE_WINDOW_HOURS,
            ],
            Message::TYPE_VOICE => [
                'max_duration_seconds' => Message::MAX_VOICE_DURATION_SECONDS,
                'delete_window_hours' => Message::DELETE_WINDOW_HOURS,
            ],
            Message::TYPE_FILE => [
                'max_size_mb' => Message::MAX_FILE_SIZE_MB,
                'delete_window_hours' => Message::DELETE_WINDOW_HOURS,
            ],
            default => [],
        };
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
                'message_type_info' => $this->getMessageTypeInfo(),
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
            self::CONTEXT_OWN => 60,           // 1 minuto para mensaje propio
            self::CONTEXT_PUBLIC => 1800,      // 30 minutos para mensaje público
            self::CONTEXT_ADMIN => 30,         // 30 segundos para vista admin
            self::CONTEXT_ANALYTICS => 900,    // 15 minutos para analytics
            default => 300,                    // 5 minutos por defecto
        };
    }

    /**
     * Método estático para crear resource con contexto básico
     *
     * @param Message $message
     * @return static
     */
    public static function basic(Message $message): static
    {
        return new static($message);
    }

    /**
     * Método estático para crear resource con contexto de chat
     *
     * @param Message $message
     * @param bool $includeRelations
     * @return static
     */
    public static function chat(Message $message, bool $includeRelations = false): static
    {
        return new static($message);
    }

    /**
     * Método estático para crear resource con contexto administrativo
     *
     * @param Message $message
     * @param bool $includeRelations
     * @return static
     */
    public static function admin(Message $message, bool $includeRelations = true): static
    {
        return new static($message);
    }
}
