<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Infrastructure\External\{AgoraService, FirebaseService};
use App\Domain\Services\VideoCallService;
use App\Http\Requests\Chat\{
    StartVideoCallRequest,
    JoinVideoCallRequest,
    UpdateVideoCallStatusRequest,
    UpdateVideoCallSettingsRequest
};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Log, DB, Cache};

/**
 * VideoCallController
 * 
 * Controlador para gestión de videollamadas con integración de:
 * - AgoraService: Videollamadas WebRTC, tokens, cloud recording
 * - FirebaseService: Push notifications en tiempo real
 * 
 * @package App\Http\Controllers\Chat
 * @author ForeverUsInLove Dev Team
 * @version 2.0.0 - Refactorizado con servicios externos
 */
class VideoCallController extends Controller
{
    /**
     * Constructor con inyección de dependencias
     * 
     * @param VideoCallService $videoCallService Servicio de dominio
     * @param AgoraService $agoraService Servicio Agora (videollamadas)
     * @param FirebaseService $firebaseService Servicio Firebase (push notifications)
     */
    public function __construct(
        protected VideoCallService $videoCallService,
        protected AgoraService $agoraService,
        protected FirebaseService $firebaseService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Iniciar nueva videollamada
     * 
     * Crea canal en Agora, genera token RTC y notifica al receptor.
     * 
     * @param StartVideoCallRequest $request
     * @return JsonResponse
     */
    public function start(StartVideoCallRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            Log::info('VideoCallController: Starting video call', [
                'user_id' => $user->id,
                'recipient_id' => $request->recipient_id,
                'call_type' => $request->call_type ?? 'video'
            ]);

            DB::beginTransaction();

            // 1. Crear videollamada en DB (lógica de dominio)
            $videoCall = $this->videoCallService->createVideoCall(
                $user->id,
                $request->recipient_id,
                $request->call_type ?? 'video',
                $request->validated()
            );

            // 2. Crear canal en Agora
            $channelInfo = $this->agoraService->createChannel(
                channelName: $videoCall->channel_id,
                settings: [
                    'max_participants' => $request->max_participants ?? 2,
                    'recording_enabled' => $request->enable_recording ?? false,
                    'video_profile' => $request->video_quality ?? '720p',
                    'audio_profile' => 'music_standard',
                    'low_latency' => true
                ]
            );

            // 3. Generar token RTC para el iniciador
            $rtcToken = $this->agoraService->generateRtcToken(
                channelName: $videoCall->channel_id,
                uid: $user->id,
                role: 'publisher',
                expirationTime: now()->addHours(24)->timestamp
            );

            // 4. Registrar participante en Agora
            $this->agoraService->joinChannel(
                channelName: $videoCall->channel_id,
                userId: $user->id,
                metadata: [
                    'name' => $user->name,
                    'avatar' => $user->avatar_url,
                    'role' => 'caller'
                ]
            );

            DB::commit();

            // 5. Notificar al receptor de la llamada entrante
            try {
                $this->firebaseService->sendToUser(
                    userId: $request->recipient_id,
                    notification: [
                        'title' => '📞 Videollamada entrante',
                        'body' => "{$user->name} te está llamando",
                        'image' => $user->avatar_url
                    ],
                    data: [
                        'type' => 'incoming_video_call',
                        'video_call_id' => $videoCall->id,
                        'channel_id' => $videoCall->channel_id,
                        'caller_id' => $user->id,
                        'caller_name' => $user->name,
                        'caller_avatar' => $user->avatar_url,
                        'call_type' => $videoCall->call_type
                    ],
                    options: [
                        'priority' => 'high',
                        'ttl' => 60, // Solo válido por 60 segundos
                        'badge_count' => 1
                    ]
                );

                Log::info('VideoCallController: Incoming call notification sent', [
                    'video_call_id' => $videoCall->id,
                    'recipient_id' => $request->recipient_id
                ]);

            } catch (\Exception $e) {
                // No fallar la creación de la llamada si la notificación falla
                Log::error('VideoCallController: Failed to send incoming call notification', [
                    'error' => $e->getMessage(),
                    'video_call_id' => $videoCall->id
                ]);
            }

            Log::info('VideoCallController: Video call started successfully', [
                'video_call_id' => $videoCall->id,
                'channel_id' => $videoCall->channel_id
            ]);

            return response()->json([
                'success' => true,
                'video_call' => $videoCall,
                'agora' => [
                    'app_id' => config('services.agora.app_id'),
                    'channel_name' => $videoCall->channel_id,
                    'token' => $rtcToken,
                    'uid' => $user->id,
                    'expires_at' => now()->addHours(24)->toIso8601String()
                ],
                'channel_info' => $channelInfo
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('VideoCallController: Failed to start video call', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo iniciar la videollamada. Por favor, intenta nuevamente.'
            ], 500);
        }
    }

    /**
     * Unirse a una videollamada existente
     * 
     * Genera token RTC para el participante y notifica a otros.
     * 
     * @param JoinVideoCallRequest $request
     * @param int $videoCallId
     * @return JsonResponse
     */
    public function join(JoinVideoCallRequest $request, int $videoCallId): JsonResponse
    {
        try {
            $user = $request->user();
            
            Log::info('VideoCallController: User joining video call', [
                'user_id' => $user->id,
                'video_call_id' => $videoCallId
            ]);

            // 1. Validar y obtener videollamada
            $videoCall = $this->videoCallService->getVideoCall($videoCallId);

            if (!$videoCall) {
                return response()->json([
                    'success' => false,
                    'message' => 'Videollamada no encontrada'
                ], 404);
            }

            // 2. Validar permisos
            if (!$this->videoCallService->canJoinVideoCall($user->id, $videoCall)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para unirte a esta llamada'
                ], 403);
            }

            // 3. Validar estado de la llamada
            if ($videoCall->status === 'ended') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta videollamada ya finalizó'
                ], 400);
            }

            DB::beginTransaction();

            // 4. Registrar participante en Agora
            $this->agoraService->joinChannel(
                channelName: $videoCall->channel_id,
                userId: $user->id,
                metadata: [
                    'name' => $user->name,
                    'avatar' => $user->avatar_url,
                    'role' => 'participant'
                ]
            );

            // 5. Generar token RTC para el participante
            $rtcToken = $this->agoraService->generateRtcToken(
                channelName: $videoCall->channel_id,
                uid: $user->id,
                role: 'publisher',
                expirationTime: now()->addHours(24)->timestamp
            );

            // 6. Actualizar estado en DB
            $this->videoCallService->addParticipant($videoCallId, $user->id);

            // 7. Actualizar estado de la llamada si es necesario
            if ($videoCall->status === 'pending') {
                $this->videoCallService->updateStatus($videoCallId, 'active');
            }

            DB::commit();

            // 8. Obtener participantes actuales
            $participants = $this->videoCallService->getParticipants($videoCallId);

            // 9. Notificar a otros participantes
            $otherParticipantIds = $participants->pluck('id')
                ->reject(fn($id) => $id === $user->id)
                ->toArray();

            if (!empty($otherParticipantIds)) {
                try {
                    $this->firebaseService->sendBatch(
                        tokens: $this->getUsersTokens($otherParticipantIds),
                        notification: [],
                        data: [
                            'type' => 'participant_joined',
                            'video_call_id' => $videoCallId,
                            'user_id' => $user->id,
                            'user_name' => $user->name,
                            'user_avatar' => $user->avatar_url,
                            'total_participants' => $participants->count()
                        ]
                    );

                    Log::info('VideoCallController: Participant joined notification sent', [
                        'video_call_id' => $videoCallId,
                        'joined_user_id' => $user->id,
                        'notified_count' => count($otherParticipantIds)
                    ]);

                } catch (\Exception $e) {
                    Log::error('VideoCallController: Failed to send participant joined notification', [
                        'error' => $e->getMessage(),
                        'video_call_id' => $videoCallId
                    ]);
                }
            }

            Log::info('VideoCallController: User joined video call successfully', [
                'user_id' => $user->id,
                'video_call_id' => $videoCallId,
                'total_participants' => $participants->count()
            ]);

            return response()->json([
                'success' => true,
                'video_call' => $videoCall->fresh(),
                'agora' => [
                    'app_id' => config('services.agora.app_id'),
                    'channel_name' => $videoCall->channel_id,
                    'token' => $rtcToken,
                    'uid' => $user->id,
                    'expires_at' => now()->addHours(24)->toIso8601String()
                ],
                'participants' => $participants
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('VideoCallController: Failed to join video call', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
                'video_call_id' => $videoCallId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo unir a la videollamada. Por favor, intenta nuevamente.'
            ], 500);
        }
    }

    /**
     * Salir de una videollamada
     * 
     * Registra la salida en Agora y notifica a participantes restantes.
     * 
     * @param Request $request
     * @param int $videoCallId
     * @return JsonResponse
     */
    public function leave(Request $request, int $videoCallId): JsonResponse
    {
        try {
            $user = $request->user();
            
            Log::info('VideoCallController: User leaving video call', [
                'user_id' => $user->id,
                'video_call_id' => $videoCallId
            ]);

            // 1. Obtener videollamada
            $videoCall = $this->videoCallService->getVideoCall($videoCallId);

            if (!$videoCall) {
                return response()->json([
                    'success' => false,
                    'message' => 'Videollamada no encontrada'
                ], 404);
            }

            DB::beginTransaction();

            // 2. Registrar salida en Agora
            $leaveInfo = $this->agoraService->leaveChannel(
                channelName: $videoCall->channel_id,
                userId: $user->id
            );

            // 3. Actualizar DB
            $this->videoCallService->removeParticipant($videoCallId, $user->id);

            // 4. Obtener participantes restantes
            $remainingParticipants = $this->videoCallService->getParticipants($videoCallId);

            // 5. Si no quedan participantes, finalizar llamada
            if ($remainingParticipants->isEmpty()) {
                $this->videoCallService->endVideoCall($videoCallId);
            }

            DB::commit();

            // 6. Notificar a participantes restantes
            if ($remainingParticipants->isNotEmpty()) {
                try {
                    $this->firebaseService->sendBatch(
                        tokens: $this->getUsersTokens($remainingParticipants->pluck('id')->toArray()),
                        notification: [],
                        data: [
                            'type' => 'participant_left',
                            'video_call_id' => $videoCallId,
                            'user_id' => $user->id,
                            'user_name' => $user->name,
                            'duration_seconds' => $leaveInfo['duration_seconds'] ?? 0,
                            'remaining_participants' => $remainingParticipants->count()
                        ]
                    );

                    Log::info('VideoCallController: Participant left notification sent', [
                        'video_call_id' => $videoCallId,
                        'left_user_id' => $user->id
                    ]);

                } catch (\Exception $e) {
                    Log::error('VideoCallController: Failed to send participant left notification', [
                        'error' => $e->getMessage(),
                        'video_call_id' => $videoCallId
                    ]);
                }
            }

            Log::info('VideoCallController: User left video call successfully', [
                'user_id' => $user->id,
                'video_call_id' => $videoCallId,
                'session_duration' => $leaveInfo['duration_seconds'] ?? 0,
                'remaining_participants' => $remainingParticipants->count()
            ]);

            return response()->json([
                'success' => true,
                'session_duration' => $leaveInfo['duration_seconds'] ?? 0,
                'remaining_participants' => $remainingParticipants->count(),
                'call_ended' => $remainingParticipants->isEmpty()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('VideoCallController: Failed to leave video call', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'video_call_id' => $videoCallId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al salir de la videollamada'
            ], 500);
        }
    }

    /**
     * Finalizar videollamada completamente
     * 
     * @param Request $request
     * @param int $videoCallId
     * @return JsonResponse
     */
    public function end(Request $request, int $videoCallId): JsonResponse
    {
        try {
            $user = $request->user();
            
            Log::info('VideoCallController: Ending video call', [
                'user_id' => $user->id,
                'video_call_id' => $videoCallId
            ]);

            $videoCall = $this->videoCallService->getVideoCall($videoCallId);

            if (!$videoCall) {
                return response()->json([
                    'success' => false,
                    'message' => 'Videollamada no encontrada'
                ], 404);
            }

            // Solo el iniciador puede finalizar la llamada
            if ($videoCall->caller_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo el iniciador puede finalizar la llamada'
                ], 403);
            }

            DB::beginTransaction();

            // Obtener participantes antes de finalizar
            $participants = $this->videoCallService->getParticipants($videoCallId);

            // Finalizar llamada
            $this->videoCallService->endVideoCall($videoCallId);

            DB::commit();

            // Notificar a todos los participantes
            if ($participants->isNotEmpty()) {
                try {
                    $this->firebaseService->sendBatch(
                        tokens: $this->getUsersTokens($participants->pluck('id')->toArray()),
                        notification: [
                            'title' => 'Llamada finalizada',
                            'body' => 'La videollamada ha terminado'
                        ],
                        data: [
                            'type' => 'video_call_ended',
                            'video_call_id' => $videoCallId,
                            'ended_by' => $user->id
                        ]
                    );

                } catch (\Exception $e) {
                    Log::error('VideoCallController: Failed to send call ended notification', [
                        'error' => $e->getMessage(),
                        'video_call_id' => $videoCallId
                    ]);
                }
            }

            Log::info('VideoCallController: Video call ended successfully', [
                'video_call_id' => $videoCallId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Videollamada finalizada'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('VideoCallController: Failed to end video call', [
                'error' => $e->getMessage(),
                'video_call_id' => $videoCallId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar la videollamada'
            ], 500);
        }
    }

    /**
     * Obtener o renovar token RTC
     * 
     * Usado cuando el token está próximo a expirar.
     * 
     * @param Request $request
     * @param int $videoCallId
     * @return JsonResponse
     */
    public function getAccessToken(Request $request, int $videoCallId): JsonResponse
    {
        try {
            $user = $request->user();
            
            Log::info('VideoCallController: Getting access token', [
                'user_id' => $user->id,
                'video_call_id' => $videoCallId
            ]);

            $videoCall = $this->videoCallService->getVideoCall($videoCallId);

            if (!$videoCall) {
                return response()->json([
                    'success' => false,
                    'message' => 'Videollamada no encontrada'
                ], 404);
            }

            // Validar que el usuario sea participante
            if (!$this->videoCallService->isParticipant($user->id, $videoCall)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No eres participante de esta llamada'
                ], 403);
            }

            // Generar nuevo token RTC
            $rtcToken = $this->agoraService->generateRtcToken(
                channelName: $videoCall->channel_id,
                uid: $user->id,
                role: $request->role ?? 'publisher',
                expirationTime: now()->addHours(24)->timestamp
            );

            Log::info('VideoCallController: Access token generated', [
                'user_id' => $user->id,
                'video_call_id' => $videoCallId
            ]);

            return response()->json([
                'success' => true,
                'token' => $rtcToken,
                'uid' => $user->id,
                'expires_at' => now()->addHours(24)->toIso8601String()
            ]);

        } catch (\Exception $e) {
            Log::error('VideoCallController: Failed to get access token', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'video_call_id' => $videoCallId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar el token'
            ], 500);
        }
    }

    /**
     * Iniciar grabación de videollamada
     * 
     * Inicia Cloud Recording en Agora.
     * 
     * @param Request $request
     * @param int $videoCallId
     * @return JsonResponse
     */
    public function startRecording(Request $request, int $videoCallId): JsonResponse
    {
        try {
            $user = $request->user();
            
            Log::info('VideoCallController: Starting recording', [
                'user_id' => $user->id,
                'video_call_id' => $videoCallId
            ]);

            $videoCall = $this->videoCallService->getVideoCall($videoCallId);

            if (!$videoCall) {
                return response()->json([
                    'success' => false,
                    'message' => 'Videollamada no encontrada'
                ], 404);
            }

            // Solo el iniciador puede iniciar grabación (ajustar según reglas de negocio)
            if ($videoCall->caller_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo el iniciador puede iniciar la grabación'
                ], 403);
            }

            // Verificar si ya hay grabación activa
            if ($this->videoCallService->hasActiveRecording($videoCallId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya hay una grabación activa'
                ], 400);
            }

            DB::beginTransaction();

            // Iniciar Cloud Recording en Agora
            $recordingInfo = $this->agoraService->startCloudRecording(
                channelName: $videoCall->channel_id,
                uid: 999999, // UID especial para el bot de grabación
                mode: $request->mode ?? 'mix', // 'mix' o 'individual'
                settings: [
                    'max_idle_time' => 30,
                    'transcoding_config' => [
                        'width' => $request->width ?? 1280,
                        'height' => $request->height ?? 720,
                        'fps' => $request->fps ?? 30,
                        'bitrate' => $request->bitrate ?? 2000
                    ]
                ]
            );

            // Guardar info de grabación en DB
            $this->videoCallService->startRecording($videoCallId, $recordingInfo);

            DB::commit();

            Log::info('VideoCallController: Recording started successfully', [
                'video_call_id' => $videoCallId,
                'resource_id' => $recordingInfo['resource_id'],
                'sid' => $recordingInfo['sid']
            ]);

            return response()->json([
                'success' => true,
                'recording' => $recordingInfo
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('VideoCallController: Failed to start recording', [
                'error' => $e->getMessage(),
                'video_call_id' => $videoCallId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo iniciar la grabación'
            ], 500);
        }
    }

    /**
     * Detener grabación de videollamada
     * 
     * Detiene Cloud Recording en Agora.
     * 
     * @param Request $request
     * @param int $videoCallId
     * @return JsonResponse
     */
    public function stopRecording(Request $request, int $videoCallId): JsonResponse
    {
        try {
            $user = $request->user();
            
            Log::info('VideoCallController: Stopping recording', [
                'user_id' => $user->id,
                'video_call_id' => $videoCallId
            ]);

            $videoCall = $this->videoCallService->getVideoCall($videoCallId);

            if (!$videoCall) {
                return response()->json([
                    'success' => false,
                    'message' => 'Videollamada no encontrada'
                ], 404);
            }

            $recordingData = $this->videoCallService->getRecordingData($videoCallId);

            if (!$recordingData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay grabación activa'
                ], 400);
            }

            DB::beginTransaction();

            // Detener Cloud Recording en Agora
            $result = $this->agoraService->stopCloudRecording(
                channelName: $videoCall->channel_id,
                sid: $recordingData['sid'],
                resourceId: $recordingData['resource_id'],
                uid: 999999,
                mode: $recordingData['mode'] ?? 'mix'
            );

            // Actualizar DB con archivos generados
            $this->videoCallService->stopRecording($videoCallId, $result);

            DB::commit();

            Log::info('VideoCallController: Recording stopped successfully', [
                'video_call_id' => $videoCallId,
                'file_count' => count($result['file_list'])
            ]);

            return response()->json([
                'success' => true,
                'recording_result' => $result
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('VideoCallController: Failed to stop recording', [
                'error' => $e->getMessage(),
                'video_call_id' => $videoCallId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo detener la grabación'
            ], 500);
        }
    }

    /**
     * Listar participantes de una videollamada
     * 
     * @param Request $request
     * @param int $videoCallId
     * @return JsonResponse
     */
    public function participants(Request $request, int $videoCallId): JsonResponse
    {
        try {
            $videoCall = $this->videoCallService->getVideoCall($videoCallId);

            if (!$videoCall) {
                return response()->json([
                    'success' => false,
                    'message' => 'Videollamada no encontrada'
                ], 404);
            }

            $participants = $this->videoCallService->getParticipants($videoCallId);

            return response()->json([
                'success' => true,
                'participants' => $participants,
                'count' => $participants->count()
            ]);

        } catch (\Exception $e) {
            Log::error('VideoCallController: Failed to get participants', [
                'error' => $e->getMessage(),
                'video_call_id' => $videoCallId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener participantes'
            ], 500);
        }
    }

    /**
     * Actualizar estado de videollamada
     * 
     * @param UpdateVideoCallStatusRequest $request
     * @param int $videoCallId
     * @return JsonResponse
     */
    public function updateStatus(UpdateVideoCallStatusRequest $request, int $videoCallId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('VideoCallController: Updating status', [
                'user_id' => $user->id,
                'video_call_id' => $videoCallId,
                'new_status' => $request->status
            ]);

            $videoCall = $this->videoCallService->updateStatus($videoCallId, $request->status);

            return response()->json([
                'success' => true,
                'video_call' => $videoCall
            ]);

        } catch (\Exception $e) {
            Log::error('VideoCallController: Failed to update status', [
                'error' => $e->getMessage(),
                'video_call_id' => $videoCallId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de calidad de llamada
     * 
     * @param Request $request
     * @param int $videoCallId
     * @return JsonResponse
     */
    public function stats(Request $request, int $videoCallId): JsonResponse
    {
        try {
            $videoCall = $this->videoCallService->getVideoCall($videoCallId);

            if (!$videoCall) {
                return response()->json([
                    'success' => false,
                    'message' => 'Videollamada no encontrada'
                ], 404);
            }

            // Obtener métricas de Agora
            $callQuality = $this->agoraService->getCallQuality(
                channelName: $videoCall->channel_id,
                startTime: $videoCall->started_at?->timestamp ?? time(),
                endTime: $videoCall->ended_at?->timestamp ?? time()
            );

            return response()->json([
                'success' => true,
                'stats' => $callQuality
            ]);

        } catch (\Exception $e) {
            Log::error('VideoCallController: Failed to get stats', [
                'error' => $e->getMessage(),
                'video_call_id' => $videoCallId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas'
            ], 500);
        }
    }

    /**
     * WebRTC signaling (para intercambio de ICE candidates, SDP offers/answers)
     * 
     * @param Request $request
     * @param int $videoCallId
     * @return JsonResponse
     */
    public function signal(Request $request, int $videoCallId): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'type' => 'required|in:offer,answer,ice_candidate',
                'data' => 'required|array'
            ]);

            Log::info('VideoCallController: WebRTC signal', [
                'user_id' => $user->id,
                'video_call_id' => $videoCallId,
                'signal_type' => $request->type
            ]);

            // Guardar señal en cache temporalmente (para otros participantes)
            $cacheKey = "video_call_signal:{$videoCallId}:{$user->id}:" . time();
            Cache::put($cacheKey, [
                'user_id' => $user->id,
                'type' => $request->type,
                'data' => $request->data
            ], 60); // 1 minuto

            // Notificar a otros participantes vía silent push
            $videoCall = $this->videoCallService->getVideoCall($videoCallId);
            $participants = $this->videoCallService->getParticipants($videoCallId);
            $otherParticipantIds = $participants->pluck('id')
                ->reject(fn($id) => $id === $user->id)
                ->toArray();

            if (!empty($otherParticipantIds)) {
                try {
                    $this->firebaseService->sendSilentPush(
                        tokens: $this->getUsersTokens($otherParticipantIds),
                        data: [
                            'type' => 'webrtc_signal',
                            'video_call_id' => $videoCallId,
                            'from_user_id' => $user->id,
                            'signal_type' => $request->type,
                            'cache_key' => $cacheKey
                        ]
                    );

                } catch (\Exception $e) {
                    Log::error('VideoCallController: Failed to send signal notification', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Señal enviada'
            ]);

        } catch (\Exception $e) {
            Log::error('VideoCallController: Failed to process signal', [
                'error' => $e->getMessage(),
                'video_call_id' => $videoCallId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar señal'
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
            Log::error('VideoCallController: Failed to get user tokens', [
                'error' => $e->getMessage(),
                'user_ids' => $userIds
            ]);

            return [];
        }
    }
}