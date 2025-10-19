<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Infrastructure\External\FirebaseService;
use App\Domain\Services\{ChatService, ConversationService};
use App\Http\Requests\Chat\{CreateConversationRequest, UpdateConversationRequest};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Log, DB};

/**
 * ChatController
 * 
 * Controlador para gestión de conversaciones y chats 1-on-1 con integración de:
 * - FirebaseService: Push notifications para eventos de chat
 * 
 * Funcionalidades:
 * - CRUD de conversaciones
 * - Iniciar nuevas conversaciones
 * - Bloquear/desbloquear usuarios
 * - Archivar conversaciones
 * - Typing indicators
 * - Online status
 * 
 * @package App\Http\Controllers\Chat
 * @author ForeverUsInLove Dev Team
 * @version 2.0.0 - Refactorizado con servicios externos
 */
class ChatController extends Controller
{
    /**
     * Constructor con inyección de dependencias
     * 
     * @param ChatService $chatService Servicio de dominio para chat
     * @param ConversationService $conversationService Servicio de conversaciones
     * @param FirebaseService $firebaseService Servicio Firebase (push notifications)
     */
    public function __construct(
        protected ChatService $chatService,
        protected ConversationService $conversationService,
        protected FirebaseService $firebaseService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Listar conversaciones del usuario
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'status' => 'nullable|in:active,archived,all',
                'per_page' => 'integer|min:1|max:100',
                'page' => 'integer|min:1'
            ]);

            Log::info('ChatController: Listing conversations', [
                'user_id' => $user->id,
                'status' => $request->status ?? 'active'
            ]);

            $conversations = $this->conversationService->getUserConversations(
                $user->id,
                $request->status ?? 'active',
                $request->per_page ?? 20
            );

            return response()->json([
                'success' => true,
                'conversations' => $conversations
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController: Failed to list conversations', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener conversaciones'
            ], 500);
        }
    }

    /**
     * Crear nueva conversación o obtener existente
     * 
     * Si ya existe una conversación entre los usuarios, la retorna.
     * 
     * @param CreateConversationRequest $request
     * @return JsonResponse
     */
    public function store(CreateConversationRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('ChatController: Creating conversation', [
                'user_id' => $user->id,
                'recipient_id' => $request->recipient_id
            ]);

            // Validar que no esté bloqueado
            if ($this->chatService->isBlocked($user->id, $request->recipient_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes iniciar conversación con este usuario'
                ], 403);
            }

            DB::beginTransaction();

            // Buscar conversación existente o crear nueva
            $conversation = $this->conversationService->findOrCreateConversation(
                $user->id,
                $request->recipient_id,
                $request->initial_message ?? null
            );

            DB::commit();

            // Si es nueva conversación, notificar al destinatario
            if ($conversation->wasRecentlyCreated && $request->initial_message) {
                try {
                    $recipient = $conversation->getOtherParticipant($user->id);

                    $this->firebaseService->sendToUser(
                        userId: $recipient->id,
                        notification: [
                            'title' => '💬 Nuevo mensaje',
                            'body' => "{$user->name}: {$request->initial_message}",
                            'image' => $user->avatar_url
                        ],
                        data: [
                            'type' => 'new_conversation',
                            'conversation_id' => $conversation->id,
                            'sender_id' => $user->id,
                            'sender_name' => $user->name,
                            'message_preview' => $request->initial_message
                        ],
                        options: [
                            'priority' => 'high',
                            'ttl' => 86400
                        ]
                    );

                    Log::info('ChatController: New conversation notification sent', [
                        'conversation_id' => $conversation->id,
                        'recipient_id' => $recipient->id
                    ]);

                } catch (\Exception $e) {
                    Log::error('ChatController: Failed to send new conversation notification', [
                        'error' => $e->getMessage(),
                        'conversation_id' => $conversation->id
                    ]);
                }
            }

            Log::info('ChatController: Conversation created/retrieved', [
                'conversation_id' => $conversation->id,
                'is_new' => $conversation->wasRecentlyCreated
            ]);

            return response()->json([
                'success' => true,
                'conversation' => $conversation,
                'is_new' => $conversation->wasRecentlyCreated ?? false
            ], $conversation->wasRecentlyCreated ? 201 : 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('ChatController: Failed to create conversation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear conversación'
            ], 500);
        }
    }

    /**
     * Ver una conversación específica
     * 
     * @param Request $request
     * @param int $conversationId
     * @return JsonResponse
     */
    public function show(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();

            $conversation = $this->conversationService->getConversation($conversationId);

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            // Validar que el usuario sea participante
            if (!$conversation->hasParticipant($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes acceso a esta conversación'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'conversation' => $conversation
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController: Failed to get conversation', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener conversación'
            ], 500);
        }
    }

    /**
     * Actualizar conversación (nombre, configuraciones)
     * 
     * @param UpdateConversationRequest $request
     * @param int $conversationId
     * @return JsonResponse
     */
    public function update(UpdateConversationRequest $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('ChatController: Updating conversation', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId
            ]);

            $conversation = $this->conversationService->updateConversation(
                $conversationId,
                $user->id,
                $request->validated()
            );

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada o sin permisos'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'conversation' => $conversation
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController: Failed to update conversation', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar conversación'
            ], 500);
        }
    }

    /**
     * Eliminar conversación (mover a papelera)
     * 
     * @param Request $request
     * @param int $conversationId
     * @return JsonResponse
     */
    public function destroy(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('ChatController: Deleting conversation', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId
            ]);

            $deleted = $this->conversationService->deleteConversation($conversationId, $user->id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Conversación eliminada'
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController: Failed to delete conversation', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar conversación'
            ], 500);
        }
    }

    /**
     * Archivar conversación
     * 
     * @param Request $request
     * @param int $conversationId
     * @return JsonResponse
     */
    public function archive(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('ChatController: Archiving conversation', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId
            ]);

            $conversation = $this->conversationService->archiveConversation($conversationId, $user->id);

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'conversation' => $conversation
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController: Failed to archive conversation', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al archivar conversación'
            ], 500);
        }
    }

    /**
     * Desarchivar conversación
     * 
     * @param Request $request
     * @param int $conversationId
     * @return JsonResponse
     */
    public function unarchive(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();

            $conversation = $this->conversationService->unarchiveConversation($conversationId, $user->id);

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'conversation' => $conversation
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController: Failed to unarchive conversation', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desarchivar conversación'
            ], 500);
        }
    }

    /**
     * Bloquear usuario
     * 
     * Impide enviar/recibir mensajes del usuario bloqueado.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function blockUser(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'blocked_user_id' => 'required|exists:users,id'
            ]);

            Log::info('ChatController: Blocking user', [
                'user_id' => $user->id,
                'blocked_user_id' => $request->blocked_user_id
            ]);

            // No se puede bloquear a sí mismo
            if ($user->id === $request->blocked_user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes bloquearte a ti mismo'
                ], 400);
            }

            $this->chatService->blockUser($user->id, $request->blocked_user_id);

            return response()->json([
                'success' => true,
                'message' => 'Usuario bloqueado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController: Failed to block user', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al bloquear usuario'
            ], 500);
        }
    }

    /**
     * Desbloquear usuario
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function unblockUser(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'blocked_user_id' => 'required|exists:users,id'
            ]);

            Log::info('ChatController: Unblocking user', [
                'user_id' => $user->id,
                'blocked_user_id' => $request->blocked_user_id
            ]);

            $this->chatService->unblockUser($user->id, $request->blocked_user_id);

            return response()->json([
                'success' => true,
                'message' => 'Usuario desbloqueado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController: Failed to unblock user', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desbloquear usuario'
            ], 500);
        }
    }

    /**
     * Listar usuarios bloqueados
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function blockedUsers(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $blockedUsers = $this->chatService->getBlockedUsers($user->id);

            return response()->json([
                'success' => true,
                'blocked_users' => $blockedUsers
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController: Failed to get blocked users', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios bloqueados'
            ], 500);
        }
    }

    /**
     * Enviar typing indicator (usuario está escribiendo)
     * 
     * Envía notificación silenciosa al destinatario.
     * 
     * @param Request $request
     * @param int $conversationId
     * @return JsonResponse
     */
    public function typing(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'is_typing' => 'required|boolean'
            ]);

            Log::info('ChatController: Typing indicator', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'is_typing' => $request->is_typing
            ]);

            $conversation = $this->conversationService->getConversation($conversationId);

            if (!$conversation || !$conversation->hasParticipant($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversación no encontrada'
                ], 404);
            }

            // Obtener destinatario
            $recipient = $conversation->getOtherParticipant($user->id);

            if ($recipient) {
                // Enviar silent push para actualizar UI
                try {
                    $this->firebaseService->sendSilentPush(
                        tokens: $this->getUsersTokens([$recipient->id]),
                        data: [
                            'type' => 'typing_indicator',
                            'conversation_id' => $conversationId,
                            'user_id' => $user->id,
                            'user_name' => $user->name,
                            'is_typing' => $request->is_typing
                        ]
                    );

                } catch (\Exception $e) {
                    Log::error('ChatController: Failed to send typing indicator', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController: Failed to process typing indicator', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar indicador'
            ], 500);
        }
    }

    /**
     * Actualizar estado online del usuario
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateOnlineStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'status' => 'required|in:online,offline,away'
            ]);

            Log::info('ChatController: Updating online status', [
                'user_id' => $user->id,
                'status' => $request->status
            ]);

            $this->chatService->updateOnlineStatus($user->id, $request->status);

            // Notificar a conversaciones activas (opcional)
            if ($request->notify_contacts ?? false) {
                try {
                    $activeConversations = $this->conversationService->getActiveConversations($user->id);
                    $recipientIds = $activeConversations->pluck('other_user_id')->toArray();

                    if (!empty($recipientIds)) {
                        $this->firebaseService->sendSilentPush(
                            tokens: $this->getUsersTokens($recipientIds),
                            data: [
                                'type' => 'online_status_change',
                                'user_id' => $user->id,
                                'status' => $request->status,
                                'last_seen' => now()->toIso8601String()
                            ]
                        );
                    }

                } catch (\Exception $e) {
                    Log::error('ChatController: Failed to notify online status', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'status' => $request->status
            ]);

        } catch (\Exception $e) {
            Log::error('ChatController: Failed to update online status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado'
            ], 500);
        }
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
            Log::error('ChatController: Failed to get user tokens', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }
}