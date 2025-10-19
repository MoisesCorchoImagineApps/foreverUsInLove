<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Infrastructure\External\{FirebaseService, CloudinaryService};
use App\Domain\Services\MessageService;
use App\Http\Requests\Chat\{StoreMessageRequest, UpdateMessageRequest, SearchMessagesRequest};
use Illuminate\Http\{JsonResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Log, DB, Storage};
use Illuminate\Support\Str;

/**
 * MessageController
 * 
 * Controlador para gestión de mensajes con integración de:
 * - FirebaseService: Push notifications en tiempo real
 * - CloudinaryService: Storage de multimedia
 * 
 * @package App\Http\Controllers\Chat
 * @author ForeverUsInLove Dev Team
 * @version 2.0.0 - Refactorizado con servicios externos
 */
class MessageController extends Controller
{
    /**
     * Constructor con inyección de dependencias
     * 
     * @param MessageService $messageService Servicio de dominio
     * @param FirebaseService $firebaseService Servicio Firebase (push notifications)
     * @param CloudinaryService $cloudinaryService Servicio Cloudinary (multimedia storage)
     */
    public function __construct(
        protected MessageService $messageService,
        protected FirebaseService $firebaseService,
        protected CloudinaryService $cloudinaryService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Listar mensajes de una conversación
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'conversation_id' => 'required|exists:conversations,id',
                'per_page' => 'integer|min:1|max:100',
                'page' => 'integer|min:1'
            ]);

            $messages = $this->messageService->getMessages(
                $request->conversation_id,
                $request->per_page ?? 50
            );

            return response()->json([
                'success' => true,
                'messages' => $messages
            ]);

        } catch (\Exception $e) {
            Log::error('MessageController: Failed to list messages', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener mensajes'
            ], 500);
        }
    }

    /**
     * Crear nuevo mensaje de texto
     * 
     * Envía mensaje y notifica al destinatario vía push.
     * 
     * @param StoreMessageRequest $request
     * @return JsonResponse
     */
    public function store(StoreMessageRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            Log::info('MessageController: Creating message', [
                'user_id' => $user->id,
                'conversation_id' => $request->conversation_id,
                'message_type' => 'text'
            ]);

            DB::beginTransaction();

            // 1. Crear mensaje en DB
            $message = $this->messageService->createMessage(
                $request->conversation_id,
                $user->id,
                $request->validated()
            );

            DB::commit();

            // 2. Obtener destinatario
            $conversation = $message->conversation;
            $recipient = $conversation->getOtherParticipant($user->id);

            if (!$recipient) {
                Log::warning('MessageController: No recipient found for message', [
                    'message_id' => $message->id,
                    'conversation_id' => $request->conversation_id
                ]);

                return response()->json($message, 201);
            }

            // 3. Enviar push notification
            try {
                $this->firebaseService->sendToUser(
                    userId: $recipient->id,
                    notification: [
                        'title' => $user->name,
                        'body' => $this->getNotificationBody($message),
                        'image' => $user->avatar_url
                    ],
                    data: [
                        'type' => 'new_message',
                        'message_id' => $message->id,
                        'conversation_id' => $message->conversation_id,
                        'sender_id' => $user->id,
                        'sender_name' => $user->name,
                        'sender_avatar' => $user->avatar_url,
                        'message_type' => $message->type,
                        'message_preview' => Str::limit($message->content ?? '', 100)
                    ],
                    options: [
                        'priority' => 'high',
                        'ttl' => 86400 // 24 horas
                    ]
                );

                Log::info('MessageController: Push notification sent', [
                    'message_id' => $message->id,
                    'recipient_id' => $recipient->id
                ]);

            } catch (\Exception $e) {
                // No fallar la creación del mensaje si la notificación falla
                Log::error('MessageController: Failed to send push notification', [
                    'error' => $e->getMessage(),
                    'message_id' => $message->id,
                    'recipient_id' => $recipient->id
                ]);
            }

            Log::info('MessageController: Message created successfully', [
                'message_id' => $message->id
            ]);

            return response()->json($message, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('MessageController: Failed to create message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar el mensaje'
            ], 500);
        }
    }

    /**
     * Ver un mensaje específico
     * 
     * @param Request $request
     * @param int $messageId
     * @return JsonResponse
     */
    public function show(Request $request, int $messageId): JsonResponse
    {
        try {
            $message = $this->messageService->getMessage($messageId);

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mensaje no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('MessageController: Failed to get message', [
                'error' => $e->getMessage(),
                'message_id' => $messageId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener mensaje'
            ], 500);
        }
    }

    /**
     * Agregar reacción a un mensaje
     * 
     * Notifica al autor del mensaje.
     * 
     * @param Request $request
     * @param int $messageId
     * @return JsonResponse
     */
    public function addReaction(Request $request, int $messageId): JsonResponse
    {
        try {
            $user = $request->user();
            
            $request->validate([
                'emoji' => 'required|string|max:10'
            ]);

            Log::info('MessageController: Adding reaction', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'emoji' => $request->emoji
            ]);

            DB::beginTransaction();

            // 1. Agregar reacción
            $reaction = $this->messageService->addReaction(
                $messageId,
                $user->id,
                $request->emoji
            );

            DB::commit();

            // 2. Obtener mensaje original
            $message = $this->messageService->getMessage($messageId);

            // 3. Notificar al autor del mensaje (si no es el mismo usuario)
            if ($message->user_id !== $user->id) {
                try {
                    $this->firebaseService->sendToUser(
                        userId: $message->user_id,
                        notification: [
                            'title' => 'Nueva reacción',
                            'body' => "{$user->name} reaccionó {$request->emoji} a tu mensaje"
                        ],
                        data: [
                            'type' => 'message_reaction',
                            'message_id' => $messageId,
                            'reactor_id' => $user->id,
                            'reactor_name' => $user->name,
                            'reactor_avatar' => $user->avatar_url,
                            'emoji' => $request->emoji,
                            'conversation_id' => $message->conversation_id
                        ],
                        options: [
                            'priority' => 'normal' // Baja prioridad para reacciones
                        ]
                    );

                    Log::info('MessageController: Reaction notification sent', [
                        'message_id' => $messageId,
                        'recipient_id' => $message->user_id
                    ]);

                } catch (\Exception $e) {
                    Log::error('MessageController: Failed to send reaction notification', [
                        'error' => $e->getMessage(),
                        'message_id' => $messageId
                    ]);
                }
            }

            Log::info('MessageController: Reaction added successfully', [
                'message_id' => $messageId,
                'reaction_id' => $reaction->id
            ]);

            return response()->json($reaction, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('MessageController: Failed to add reaction', [
                'error' => $e->getMessage(),
                'message_id' => $messageId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo agregar la reacción'
            ], 500);
        }
    }

    /**
     * Remover reacción de un mensaje
     * 
     * @param Request $request
     * @param int $messageId
     * @return JsonResponse
     */
    public function removeReaction(Request $request, int $messageId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('MessageController: Removing reaction', [
                'user_id' => $user->id,
                'message_id' => $messageId
            ]);

            $this->messageService->removeReaction($messageId, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Reacción eliminada'
            ]);

        } catch (\Exception $e) {
            Log::error('MessageController: Failed to remove reaction', [
                'error' => $e->getMessage(),
                'message_id' => $messageId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar reacción'
            ], 500);
        }
    }

    /**
     * Responder a un mensaje (threading)
     * 
     * Notifica al autor del mensaje padre.
     * 
     * @param StoreMessageRequest $request
     * @param int $parentMessageId
     * @return JsonResponse
     */
    public function reply(StoreMessageRequest $request, int $parentMessageId): JsonResponse
    {
        try {
            $user = $request->user();
            
            Log::info('MessageController: Creating reply', [
                'user_id' => $user->id,
                'parent_message_id' => $parentMessageId
            ]);

            DB::beginTransaction();

            // 1. Crear respuesta
            $reply = $this->messageService->replyToMessage(
                $parentMessageId,
                $user->id,
                $request->validated()
            );

            DB::commit();

            // 2. Obtener mensaje padre
            $parentMessage = $this->messageService->getMessage($parentMessageId);

            // 3. Notificar al autor del mensaje padre (si no es el mismo usuario)
            if ($parentMessage->user_id !== $user->id) {
                try {
                    $this->firebaseService->sendToUser(
                        userId: $parentMessage->user_id,
                        notification: [
                            'title' => "{$user->name} respondió",
                            'body' => $this->getNotificationBody($reply)
                        ],
                        data: [
                            'type' => 'message_reply',
                            'message_id' => $reply->id,
                            'parent_message_id' => $parentMessageId,
                            'replier_id' => $user->id,
                            'replier_name' => $user->name,
                            'replier_avatar' => $user->avatar_url,
                            'conversation_id' => $reply->conversation_id
                        ],
                        options: [
                            'priority' => 'high'
                        ]
                    );

                    Log::info('MessageController: Reply notification sent', [
                        'reply_id' => $reply->id,
                        'recipient_id' => $parentMessage->user_id
                    ]);

                } catch (\Exception $e) {
                    Log::error('MessageController: Failed to send reply notification', [
                        'error' => $e->getMessage(),
                        'reply_id' => $reply->id
                    ]);
                }
            }

            Log::info('MessageController: Reply created successfully', [
                'reply_id' => $reply->id,
                'parent_message_id' => $parentMessageId
            ]);

            return response()->json($reply, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('MessageController: Failed to create reply', [
                'error' => $e->getMessage(),
                'parent_message_id' => $parentMessageId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo crear la respuesta'
            ], 500);
        }
    }

    /**
     * Enviar imagen
     * 
     * Sube la imagen a Cloudinary y notifica con preview.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function sendImage(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $request->validate([
                'conversation_id' => 'required|exists:conversations,id',
                'image' => 'required|image|max:10240', // 10MB max
                'caption' => 'nullable|string|max:500'
            ]);

            Log::info('MessageController: Sending image', [
                'user_id' => $user->id,
                'conversation_id' => $request->conversation_id
            ]);

            DB::beginTransaction();

            // 1. Subir imagen a Cloudinary
            $uploadResult = $this->cloudinaryService->uploadImage(
                $request->file('image'),
                folder: 'chat/images',
                options: [
                    'transformation' => [
                        ['quality' => 'auto', 'fetch_format' => 'auto']
                    ]
                ]
            );

            // 2. Crear mensaje de tipo imagen
            $message = $this->messageService->createMediaMessage(
                $request->conversation_id,
                $user->id,
                'image',
                $uploadResult['secure_url'],
                $request->caption,
                [
                    'width' => $uploadResult['width'],
                    'height' => $uploadResult['height'],
                    'format' => $uploadResult['format'],
                    'size' => $uploadResult['bytes'],
                    'cloudinary_id' => $uploadResult['public_id']
                ]
            );

            DB::commit();

            // 3. Obtener destinatario
            $conversation = $message->conversation;
            $recipient = $conversation->getOtherParticipant($user->id);

            if ($recipient) {
                // 4. Enviar push con preview de imagen
                try {
                    $this->firebaseService->sendToUser(
                        userId: $recipient->id,
                        notification: [
                            'title' => $user->name,
                            'body' => $request->caption ?: '📷 Envió una foto',
                            'image' => $uploadResult['secure_url'] // Preview en notificación
                        ],
                        data: [
                            'type' => 'new_media_message',
                            'media_type' => 'image',
                            'message_id' => $message->id,
                            'conversation_id' => $message->conversation_id,
                            'sender_id' => $user->id,
                            'sender_name' => $user->name,
                            'media_url' => $uploadResult['secure_url'],
                            'caption' => $request->caption
                        ],
                        options: [
                            'priority' => 'high'
                        ]
                    );

                    Log::info('MessageController: Image notification sent', [
                        'message_id' => $message->id,
                        'recipient_id' => $recipient->id
                    ]);

                } catch (\Exception $e) {
                    Log::error('MessageController: Failed to send image notification', [
                        'error' => $e->getMessage(),
                        'message_id' => $message->id
                    ]);
                }
            }

            Log::info('MessageController: Image sent successfully', [
                'message_id' => $message->id,
                'image_url' => $uploadResult['secure_url']
            ]);

            return response()->json($message, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('MessageController: Failed to send image', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar la imagen'
            ], 500);
        }
    }

    /**
     * Enviar video
     * 
     * Sube el video a Cloudinary y notifica con preview.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function sendVideo(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $request->validate([
                'conversation_id' => 'required|exists:conversations,id',
                'video' => 'required|mimes:mp4,mov,avi,wmv|max:51200', // 50MB max
                'caption' => 'nullable|string|max:500',
                'thumbnail' => 'nullable|image|max:2048' // 2MB max
            ]);

            Log::info('MessageController: Sending video', [
                'user_id' => $user->id,
                'conversation_id' => $request->conversation_id
            ]);

            DB::beginTransaction();

            // 1. Subir video a Cloudinary
            $uploadResult = $this->cloudinaryService->uploadVideo(
                $request->file('video'),
                folder: 'chat/videos',
                options: [
                    'resource_type' => 'video',
                    'eager' => [
                        ['streaming_profile' => 'hd', 'format' => 'm3u8']
                    ]
                ]
            );

            // 2. Subir thumbnail si se proporciona
            $thumbnailUrl = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailResult = $this->cloudinaryService->uploadImage(
                    $request->file('thumbnail'),
                    folder: 'chat/video-thumbnails'
                );
                $thumbnailUrl = $thumbnailResult['secure_url'];
            } else {
                // Usar thumbnail generado automáticamente por Cloudinary
                $thumbnailUrl = $this->cloudinaryService->getVideoThumbnail($uploadResult['public_id']);
            }

            // 3. Crear mensaje de tipo video
            $message = $this->messageService->createMediaMessage(
                $request->conversation_id,
                $user->id,
                'video',
                $uploadResult['secure_url'],
                $request->caption,
                [
                    'width' => $uploadResult['width'] ?? null,
                    'height' => $uploadResult['height'] ?? null,
                    'duration' => $uploadResult['duration'] ?? null,
                    'format' => $uploadResult['format'],
                    'size' => $uploadResult['bytes'],
                    'thumbnail_url' => $thumbnailUrl,
                    'cloudinary_id' => $uploadResult['public_id']
                ]
            );

            DB::commit();

            // 4. Obtener destinatario y notificar
            $conversation = $message->conversation;
            $recipient = $conversation->getOtherParticipant($user->id);

            if ($recipient) {
                try {
                    $this->firebaseService->sendToUser(
                        userId: $recipient->id,
                        notification: [
                            'title' => $user->name,
                            'body' => $request->caption ?: '🎥 Envió un video',
                            'image' => $thumbnailUrl
                        ],
                        data: [
                            'type' => 'new_media_message',
                            'media_type' => 'video',
                            'message_id' => $message->id,
                            'conversation_id' => $message->conversation_id,
                            'sender_id' => $user->id,
                            'sender_name' => $user->name,
                            'media_url' => $uploadResult['secure_url'],
                            'thumbnail_url' => $thumbnailUrl,
                            'caption' => $request->caption
                        ],
                        options: [
                            'priority' => 'high'
                        ]
                    );

                } catch (\Exception $e) {
                    Log::error('MessageController: Failed to send video notification', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('MessageController: Video sent successfully', [
                'message_id' => $message->id
            ]);

            return response()->json($message, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('MessageController: Failed to send video', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar el video'
            ], 500);
        }
    }

    /**
     * Enviar nota de voz
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function sendVoice(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $request->validate([
                'conversation_id' => 'required|exists:conversations,id',
                'audio' => 'required|mimes:mp3,wav,ogg,m4a|max:10240', // 10MB max
                'duration' => 'required|integer|min:1|max:300' // Max 5 minutos
            ]);

            Log::info('MessageController: Sending voice message', [
                'user_id' => $user->id,
                'conversation_id' => $request->conversation_id,
                'duration' => $request->duration
            ]);

            DB::beginTransaction();

            // 1. Subir audio a Cloudinary
            $uploadResult = $this->cloudinaryService->uploadAudio(
                $request->file('audio'),
                folder: 'chat/voice'
            );

            // 2. Crear mensaje de tipo voz
            $message = $this->messageService->createMediaMessage(
                $request->conversation_id,
                $user->id,
                'voice',
                $uploadResult['secure_url'],
                null,
                [
                    'duration' => $request->duration,
                    'format' => $uploadResult['format'],
                    'size' => $uploadResult['bytes'],
                    'cloudinary_id' => $uploadResult['public_id']
                ]
            );

            DB::commit();

            // 3. Notificar destinatario
            $conversation = $message->conversation;
            $recipient = $conversation->getOtherParticipant($user->id);

            if ($recipient) {
                try {
                    $this->firebaseService->sendToUser(
                        userId: $recipient->id,
                        notification: [
                            'title' => $user->name,
                            'body' => "🎤 Envió una nota de voz ({$request->duration}s)"
                        ],
                        data: [
                            'type' => 'new_media_message',
                            'media_type' => 'voice',
                            'message_id' => $message->id,
                            'conversation_id' => $message->conversation_id,
                            'sender_id' => $user->id,
                            'media_url' => $uploadResult['secure_url'],
                            'duration' => $request->duration
                        ],
                        options: [
                            'priority' => 'high'
                        ]
                    );

                } catch (\Exception $e) {
                    Log::error('MessageController: Failed to send voice notification', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json($message, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('MessageController: Failed to send voice', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar la nota de voz'
            ], 500);
        }
    }

    /**
     * Marcar mensaje como leído
     * 
     * @param Request $request
     * @param int $messageId
     * @return JsonResponse
     */
    public function markAsRead(Request $request, int $messageId): JsonResponse
    {
        try {
            $user = $request->user();

            $this->messageService->markAsRead($messageId, $user->id);

            // Enviar silent push para actualizar UI del remitente
            $message = $this->messageService->getMessage($messageId);
            
            if ($message && $message->user_id !== $user->id) {
                try {
                    $this->firebaseService->sendSilentPush(
                        tokens: $this->getUsersTokens([$message->user_id]),
                        data: [
                            'type' => 'message_read',
                            'message_id' => $messageId,
                            'read_by' => $user->id,
                            'conversation_id' => $message->conversation_id
                        ]
                    );

                } catch (\Exception $e) {
                    Log::error('MessageController: Failed to send read receipt', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Mensaje marcado como leído'
            ]);

        } catch (\Exception $e) {
            Log::error('MessageController: Failed to mark as read', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar como leído'
            ], 500);
        }
    }

    /**
     * Marcar todos los mensajes de una conversación como leídos
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'conversation_id' => 'required|exists:conversations,id'
            ]);

            $count = $this->messageService->markAllAsRead($request->conversation_id, $user->id);

            return response()->json([
                'success' => true,
                'marked_count' => $count
            ]);

        } catch (\Exception $e) {
            Log::error('MessageController: Failed to mark all as read', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar mensajes como leídos'
            ], 500);
        }
    }

    /**
     * Contar mensajes no leídos
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'conversation_id' => 'nullable|exists:conversations,id'
            ]);

            if ($request->conversation_id) {
                $count = $this->messageService->getUnreadCount($request->conversation_id, $user->id);
            } else {
                $count = $this->messageService->getTotalUnreadCount($user->id);
            }

            return response()->json([
                'success' => true,
                'unread_count' => $count
            ]);

        } catch (\Exception $e) {
            Log::error('MessageController: Failed to get unread count', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al contar mensajes no leídos'
            ], 500);
        }
    }

    /**
     * Buscar mensajes
     * 
     * @param SearchMessagesRequest $request
     * @return JsonResponse
     */
    public function search(SearchMessagesRequest $request): JsonResponse
    {
        try {
            $messages = $this->messageService->searchMessages(
                $request->user()->id,
                $request->query,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'messages' => $messages
            ]);

        } catch (\Exception $e) {
            Log::error('MessageController: Failed to search messages', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al buscar mensajes'
            ], 500);
        }
    }

    /**
     * Helper: Generar cuerpo de notificación según tipo de mensaje
     * 
     * @param mixed $message
     * @return string
     */
    private function getNotificationBody($message): string
    {
        return match($message->type) {
            'text' => Str::limit($message->content, 100),
            'image' => '📷 Foto',
            'video' => '🎥 Video',
            'voice' => '🎤 Nota de voz',
            'file' => '📎 ' . ($message->file_name ?? 'Archivo'),
            'sticker' => '😊 Sticker',
            'gif' => '🎬 GIF',
            'location' => '📍 Ubicación compartida',
            'icebreaker' => '💬 ' . Str::limit($message->content, 100),
            default => 'Nuevo mensaje'
        };
    }

    /**
     * Helper: Obtener device tokens de usuarios
     * 
     * @param array $userIds
     * @return array
     */
    private function getUsersTokens(array $userIds): array
    {
        try {
            $tokens = DB::table('user_devices')
                ->whereIn('user_id', $userIds)
                ->where('is_active', true)
                ->whereNotNull('fcm_token')
                ->pluck('fcm_token')
                ->toArray();

            return $tokens;

        } catch (\Exception $e) {
            Log::error('MessageController: Failed to get user tokens', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }
}