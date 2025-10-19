<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Infrastructure\External\FirebaseService;
use App\Domain\Services\HappyHourService;
use App\Http\Requests\Chat\{
    StartHappyHourRequest,
    JoinHappyHourRequest,
    SendHappyHourMessageRequest
};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Log, DB, Cache};

/**
 * HappyHourController
 * 
 * Controlador para gestión de Happy Hour (sesiones de chat rápido/eventos especiales) con integración de:
 * - FirebaseService: Push notifications para eventos de Happy Hour
 * 
 * Happy Hour es una funcionalidad especial donde:
 * - Usuarios pueden crear salas temporales de chat
 * - Tiempo limitado (ej: 1 hora)
 * - Participación masiva con límites
 * - Mensajes rápidos y efímeros
 * - Gamification con puntos/badges
 * 
 * @package App\Http\Controllers\Chat
 * @author ForeverUsInLove Dev Team
 * @version 2.0.0 - Refactorizado con servicios externos
 */
class HappyHourController extends Controller
{
    /**
     * Constructor con inyección de dependencias
     * 
     * @param HappyHourService $happyHourService Servicio de dominio para Happy Hour
     * @param FirebaseService $firebaseService Servicio Firebase (push notifications)
     */
    public function __construct(
        protected HappyHourService $happyHourService,
        protected FirebaseService $firebaseService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Listar Happy Hours activos
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'status' => 'nullable|in:active,upcoming,ended',
                'category' => 'nullable|string',
                'per_page' => 'integer|min:1|max:100'
            ]);

            Log::info('HappyHourController: Listing Happy Hours', [
                'user_id' => $user->id,
                'status' => $request->status ?? 'active'
            ]);

            $happyHours = $this->happyHourService->getHappyHours(
                $request->status ?? 'active',
                $request->category,
                $request->per_page ?? 20
            );

            return response()->json([
                'success' => true,
                'happy_hours' => $happyHours
            ]);

        } catch (\Exception $e) {
            Log::error('HappyHourController: Failed to list Happy Hours', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener Happy Hours'
            ], 500);
        }
    }

    /**
     * Crear nuevo Happy Hour
     * 
     * @param StartHappyHourRequest $request
     * @return JsonResponse
     */
    public function store(StartHappyHourRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('HappyHourController: Creating Happy Hour', [
                'user_id' => $user->id,
                'title' => $request->title,
                'duration_minutes' => $request->duration_minutes ?? 60
            ]);

            // Validar límites (ej: un usuario solo puede crear X Happy Hours por día)
            if (!$this->happyHourService->canCreateHappyHour($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Has alcanzado el límite de Happy Hours por hoy'
                ], 429);
            }

            DB::beginTransaction();

            // Crear Happy Hour
            $happyHour = $this->happyHourService->createHappyHour(
                $user->id,
                $request->title,
                $request->description ?? null,
                $request->category ?? 'general',
                $request->duration_minutes ?? 60,
                $request->max_participants ?? 100,
                $request->validated()
            );

            DB::commit();

            // Notificar a usuarios interesados (topic-based)
            try {
                $topic = "happy_hour_{$request->category ?? 'general'}";

                $this->firebaseService->sendToTopic(
                    topic: $topic,
                    notification: [
                        'title' => '🎉 Nuevo Happy Hour',
                        'body' => "{$request->title} - ¡Únete ahora!",
                        'image' => $user->avatar_url
                    ],
                    data: [
                        'type' => 'new_happy_hour',
                        'happy_hour_id' => $happyHour->id,
                        'title' => $request->title,
                        'category' => $request->category ?? 'general',
                        'host_id' => $user->id,
                        'host_name' => $user->name,
                        'starts_at' => $happyHour->starts_at->toIso8601String(),
                        'ends_at' => $happyHour->ends_at->toIso8601String()
                    ],
                    options: [
                        'priority' => 'high'
                    ]
                );

                Log::info('HappyHourController: Happy Hour announcement sent', [
                    'happy_hour_id' => $happyHour->id,
                    'topic' => $topic
                ]);

            } catch (\Exception $e) {
                Log::error('HappyHourController: Failed to send Happy Hour announcement', [
                    'error' => $e->getMessage(),
                    'happy_hour_id' => $happyHour->id
                ]);
            }

            Log::info('HappyHourController: Happy Hour created successfully', [
                'happy_hour_id' => $happyHour->id
            ]);

            return response()->json([
                'success' => true,
                'happy_hour' => $happyHour
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('HappyHourController: Failed to create Happy Hour', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear Happy Hour'
            ], 500);
        }
    }

    /**
     * Ver un Happy Hour específico
     * 
     * @param Request $request
     * @param int $happyHourId
     * @return JsonResponse
     */
    public function show(Request $request, int $happyHourId): JsonResponse
    {
        try {
            $happyHour = $this->happyHourService->getHappyHour($happyHourId);

            if (!$happyHour) {
                return response()->json([
                    'success' => false,
                    'message' => 'Happy Hour no encontrado'
                ], 404);
            }

            // Incrementar vistas
            $this->happyHourService->incrementViews($happyHourId);

            return response()->json([
                'success' => true,
                'happy_hour' => $happyHour
            ]);

        } catch (\Exception $e) {
            Log::error('HappyHourController: Failed to get Happy Hour', [
                'error' => $e->getMessage(),
                'happy_hour_id' => $happyHourId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener Happy Hour'
            ], 500);
        }
    }

    /**
     * Unirse a un Happy Hour
     * 
     * @param JoinHappyHourRequest $request
     * @param int $happyHourId
     * @return JsonResponse
     */
    public function join(JoinHappyHourRequest $request, int $happyHourId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('HappyHourController: User joining Happy Hour', [
                'user_id' => $user->id,
                'happy_hour_id' => $happyHourId
            ]);

            $happyHour = $this->happyHourService->getHappyHour($happyHourId);

            if (!$happyHour) {
                return response()->json([
                    'success' => false,
                    'message' => 'Happy Hour no encontrado'
                ], 404);
            }

            // Validar estado
            if ($happyHour->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este Happy Hour ya finalizó o aún no comienza'
                ], 400);
            }

            // Validar capacidad
            if ($happyHour->participants_count >= $happyHour->max_participants) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este Happy Hour está lleno'
                ], 400);
            }

            DB::beginTransaction();

            // Unirse
            $participant = $this->happyHourService->joinHappyHour($happyHourId, $user->id);

            DB::commit();

            // Notificar al host y otros participantes
            try {
                // Notificar al host
                $this->firebaseService->sendToUser(
                    userId: $happyHour->host_id,
                    notification: [],
                    data: [
                        'type' => 'happy_hour_new_participant',
                        'happy_hour_id' => $happyHourId,
                        'participant_id' => $user->id,
                        'participant_name' => $user->name,
                        'total_participants' => $happyHour->participants_count + 1
                    ]
                );

                // Broadcast a todos los participantes (opcional, para actualizar contador)
                $participantIds = $happyHour->participants->pluck('id')->toArray();
                if (!empty($participantIds)) {
                    $this->firebaseService->sendSilentPush(
                        tokens: $this->getUsersTokens($participantIds),
                        data: [
                            'type' => 'happy_hour_participant_joined',
                            'happy_hour_id' => $happyHourId,
                            'participant_id' => $user->id,
                            'total_participants' => $happyHour->participants_count + 1
                        ]
                    );
                }

                Log::info('HappyHourController: Join notifications sent', [
                    'happy_hour_id' => $happyHourId,
                    'user_id' => $user->id
                ]);

            } catch (\Exception $e) {
                Log::error('HappyHourController: Failed to send join notifications', [
                    'error' => $e->getMessage()
                ]);
            }

            Log::info('HappyHourController: User joined Happy Hour successfully', [
                'happy_hour_id' => $happyHourId,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'happy_hour' => $happyHour->fresh(),
                'participant' => $participant
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('HappyHourController: Failed to join Happy Hour', [
                'error' => $e->getMessage(),
                'happy_hour_id' => $happyHourId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al unirse al Happy Hour'
            ], 500);
        }
    }

    /**
     * Salir de un Happy Hour
     * 
     * @param Request $request
     * @param int $happyHourId
     * @return JsonResponse
     */
    public function leave(Request $request, int $happyHourId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('HappyHourController: User leaving Happy Hour', [
                'user_id' => $user->id,
                'happy_hour_id' => $happyHourId
            ]);

            DB::beginTransaction();

            $this->happyHourService->leaveHappyHour($happyHourId, $user->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Saliste del Happy Hour'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('HappyHourController: Failed to leave Happy Hour', [
                'error' => $e->getMessage(),
                'happy_hour_id' => $happyHourId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al salir del Happy Hour'
            ], 500);
        }
    }

    /**
     * Enviar mensaje en Happy Hour
     * 
     * @param SendHappyHourMessageRequest $request
     * @param int $happyHourId
     * @return JsonResponse
     */
    public function sendMessage(SendHappyHourMessageRequest $request, int $happyHourId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('HappyHourController: Sending message in Happy Hour', [
                'user_id' => $user->id,
                'happy_hour_id' => $happyHourId
            ]);

            // Validar que sea participante
            if (!$this->happyHourService->isParticipant($happyHourId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes unirte al Happy Hour para enviar mensajes'
                ], 403);
            }

            DB::beginTransaction();

            // Crear mensaje
            $message = $this->happyHourService->createMessage(
                $happyHourId,
                $user->id,
                $request->content,
                $request->type ?? 'text'
            );

            DB::commit();

            // Notificar a participantes (silent push para actualizar chat en tiempo real)
            try {
                $happyHour = $this->happyHourService->getHappyHour($happyHourId);
                $participantIds = $happyHour->participants
                    ->pluck('id')
                    ->reject(fn($id) => $id === $user->id)
                    ->toArray();

                if (!empty($participantIds)) {
                    $this->firebaseService->sendSilentPush(
                        tokens: $this->getUsersTokens($participantIds),
                        data: [
                            'type' => 'happy_hour_new_message',
                            'happy_hour_id' => $happyHourId,
                            'message_id' => $message->id,
                            'sender_id' => $user->id,
                            'sender_name' => $user->name,
                            'content_preview' => \Str::limit($request->content, 50)
                        ]
                    );
                }

            } catch (\Exception $e) {
                Log::error('HappyHourController: Failed to send message notification', [
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('HappyHourController: Failed to send message', [
                'error' => $e->getMessage(),
                'happy_hour_id' => $happyHourId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar mensaje'
            ], 500);
        }
    }

    /**
     * Obtener mensajes del Happy Hour
     * 
     * @param Request $request
     * @param int $happyHourId
     * @return JsonResponse
     */
    public function getMessages(Request $request, int $happyHourId): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'per_page' => 'integer|min:1|max:100',
                'before_id' => 'nullable|integer'
            ]);

            // Validar que sea participante
            if (!$this->happyHourService->isParticipant($happyHourId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes unirte al Happy Hour para ver mensajes'
                ], 403);
            }

            $messages = $this->happyHourService->getMessages(
                $happyHourId,
                $request->per_page ?? 50,
                $request->before_id
            );

            return response()->json([
                'success' => true,
                'messages' => $messages
            ]);

        } catch (\Exception $e) {
            Log::error('HappyHourController: Failed to get messages', [
                'error' => $e->getMessage(),
                'happy_hour_id' => $happyHourId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener mensajes'
            ], 500);
        }
    }

    /**
     * Finalizar Happy Hour (solo host)
     * 
     * @param Request $request
     * @param int $happyHourId
     * @return JsonResponse
     */
    public function end(Request $request, int $happyHourId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('HappyHourController: Ending Happy Hour', [
                'user_id' => $user->id,
                'happy_hour_id' => $happyHourId
            ]);

            $happyHour = $this->happyHourService->getHappyHour($happyHourId);

            if (!$happyHour) {
                return response()->json([
                    'success' => false,
                    'message' => 'Happy Hour no encontrado'
                ], 404);
            }

            // Solo el host puede finalizar
            if ($happyHour->host_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo el host puede finalizar el Happy Hour'
                ], 403);
            }

            DB::beginTransaction();

            // Obtener participantes antes de finalizar
            $participantIds = $happyHour->participants->pluck('id')->toArray();

            $this->happyHourService->endHappyHour($happyHourId);

            DB::commit();

            // Notificar a participantes
            try {
                if (!empty($participantIds)) {
                    $this->firebaseService->sendBatch(
                        tokens: $this->getUsersTokens($participantIds),
                        notification: [
                            'title' => 'Happy Hour finalizado',
                            'body' => "\"{$happyHour->title}\" ha terminado. ¡Gracias por participar!"
                        ],
                        data: [
                            'type' => 'happy_hour_ended',
                            'happy_hour_id' => $happyHourId,
                            'ended_by' => $user->id
                        ]
                    );
                }

            } catch (\Exception $e) {
                Log::error('HappyHourController: Failed to send end notifications', [
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Happy Hour finalizado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('HappyHourController: Failed to end Happy Hour', [
                'error' => $e->getMessage(),
                'happy_hour_id' => $happyHourId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar Happy Hour'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas del Happy Hour
     * 
     * @param Request $request
     * @param int $happyHourId
     * @return JsonResponse
     */
    public function stats(Request $request, int $happyHourId): JsonResponse
    {
        try {
            $stats = $this->happyHourService->getStats($happyHourId);

            if (!$stats) {
                return response()->json([
                    'success' => false,
                    'message' => 'Happy Hour no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('HappyHourController: Failed to get stats', [
                'error' => $e->getMessage(),
                'happy_hour_id' => $happyHourId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas'
            ], 500);
        }
    }

    /**
     * Suscribirse a notificaciones de una categoría de Happy Hour
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function subscribeToCategory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'category' => 'required|string',
                'device_token' => 'required|string'
            ]);

            Log::info('HappyHourController: Subscribing to category', [
                'user_id' => $user->id,
                'category' => $request->category
            ]);

            $topic = "happy_hour_{$request->category}";

            $this->firebaseService->subscribeToTopic(
                tokens: [$request->device_token],
                topic: $topic
            );

            // Guardar preferencia en DB
            $this->happyHourService->subscribeToCategory($user->id, $request->category);

            return response()->json([
                'success' => true,
                'message' => 'Suscrito exitosamente',
                'category' => $request->category
            ]);

        } catch (\Exception $e) {
            Log::error('HappyHourController: Failed to subscribe to category', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al suscribirse'
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
            Log::error('HappyHourController: Failed to get user tokens', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }
}