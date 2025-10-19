<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Domain\Moderation\Services\BlockService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Moderation\BlockUserRequest;
use App\Http\Requests\Moderation\UnblockUserRequest;
use App\Http\Resources\Moderation\BlockResource;
use App\Http\Resources\Moderation\BlockStatisticsResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para la gestión de bloqueos entre usuarios en ForeverUsInLove
 * 
 * Este controlador expone endpoints para bloquear/desbloquear usuarios, consultar
 * listas de usuarios bloqueados y gestionar diferentes tipos de bloqueos. Actúa como
 * capa de presentación delegando toda la lógica de negocio al BlockService.
 * 
 * Endpoints disponibles:
 * - POST   /api/v1/moderation/blocks                    - Bloquear usuario
 * - DELETE /api/v1/moderation/blocks/{userId}           - Desbloquear usuario
 * - GET    /api/v1/moderation/blocks                    - Lista de usuarios bloqueados
 * - GET    /api/v1/moderation/blocks/blockers           - Lista de usuarios que te bloquearon
 * - GET    /api/v1/moderation/blocks/check/{userId}     - Verificar si hay bloqueo
 * - GET    /api/v1/moderation/blocks/mutual/{userId}    - Verificar bloqueo mutuo
 * - GET    /api/v1/moderation/blocks/statistics         - Estadísticas de bloqueos
 * - GET    /api/v1/moderation/blocks/recommendations    - Recomendaciones de bloqueo
 * - POST   /api/v1/moderation/blocks/mass               - Bloqueo masivo (múltiples usuarios)
 * 
 * Tipos de bloqueo soportados:
 * - complete: Bloqueo completo (todos los interactions)
 * - messaging: Solo bloqueo de mensajes
 * - profile_view: Bloqueo de visualización de perfil
 * - matching: Bloqueo de aparición en matches
 * - search_results: Bloqueo en resultados de búsqueda
 * - soft_block: Bloqueo silencioso (shadow ban)
 * 
 * @package App\Http\Controllers\Api\V1\Moderation
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class BlockController extends Controller
{
    use ApiResponseTrait;

    /**
     * Constructor con inyección de dependencias
     */
    public function __construct(
        private readonly BlockService $blockService
    ) {
        // Middleware de autenticación para todos los endpoints
        $this->middleware('auth:sanctum');
        
        // Middleware para verificar que el perfil esté completo
        $this->middleware('profile.complete');
        
        // Rate limiting diferenciado
        $this->middleware('throttle:blocks')->only(['store', 'massBlock']);
        $this->middleware('throttle:api')->except(['store', 'massBlock']);
    }

    /**
     * Bloquea a un usuario
     * 
     * @group Moderation - Blocks
     * @authenticated
     * 
     * @bodyParam blocked_id integer required ID del usuario a bloquear. Example: 123
     * @bodyParam type string optional Tipo de bloqueo (default: complete). Example: messaging
     * @bodyParam reason string optional Razón del bloqueo. Example: harassment
     * @bodyParam duration_hours integer optional Duración en horas (null = permanente). Example: 24
     * 
     * @response 201 {
     *   "success": true,
     *   "message": "Usuario bloqueado exitosamente",
     *   "data": {
     *     "block_id": 456,
     *     "type": "complete",
     *     "is_temporary": false,
     *     "expires_at": null,
     *     "consequences": {
     *       "removed_matches": 1,
     *       "hidden_conversations": 1,
     *       "removed_from_favorites": true,
     *       "search_visibility": false
     *     },
     *     "mutual_block_created": false,
     *     "reference_number": "BLK-000456-A7B2"
     *   }
     * }
     * 
     * @response 422 {
     *   "success": false,
     *   "message": "No puedes bloquearte a ti mismo"
     * }
     * 
     * @response 409 {
     *   "success": false,
     *   "message": "El usuario ya está bloqueado con este tipo de bloqueo"
     * }
     */
    public function store(BlockUserRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            Log::info('Usuario bloqueando a otro usuario', [
                'blocker_id' => $userId,
                'blocked_id' => $request->blocked_id,
                'type' => $request->type ?? 'complete'
            ]);

            $result = $this->blockService->blockUser(
                blockerId: $userId,
                blockedId: $request->blocked_id,
                type: $request->type ?? 'complete',
                reason: $request->reason,
                durationHours: $request->duration_hours,
                metadata: [
                    'platform' => $request->header('User-Agent'),
                    'app_version' => $request->header('X-App-Version')
                ]
            );

            return $this->successResponse(
                data: $result,
                message: 'Usuario bloqueado exitosamente. Ya no podrá interactuar contigo.',
                code: 201
            );

        } catch (\App\Exceptions\SelfBlockException $e) {
            return $this->errorResponse(
                message: 'No puedes bloquearte a ti mismo',
                code: 422
            );
        } catch (\App\Exceptions\DuplicateBlockException $e) {
            return $this->errorResponse(
                message: 'El usuario ya está bloqueado con este tipo de bloqueo',
                code: 409
            );
        } catch (\App\Exceptions\BlockValidationException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: 422
            );
        } catch (\Exception $e) {
            Log::error('Error al bloquear usuario', [
                'blocker_id' => $userId,
                'blocked_id' => $request->blocked_id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al bloquear usuario. Por favor intenta nuevamente.',
                code: 500
            );
        }
    }

    /**
     * Desbloquea a un usuario
     * 
     * @group Moderation - Blocks
     * @authenticated
     * 
     * @urlParam userId integer required ID del usuario a desbloquear. Example: 123
     * @bodyParam type string optional Tipo específico de bloqueo a remover. Example: messaging
     * @bodyParam reason string optional Razón del desbloqueo. Example: resolved_misunderstanding
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Usuario desbloqueado exitosamente",
     *   "data": {
     *     "unblocked_count": 1,
     *     "unblocked_types": ["complete"],
     *     "complete_unblock": true,
     *     "unblocked_at": "2025-01-16T10:30:00Z"
     *   }
     * }
     * 
     * @response 404 {
     *   "success": false,
     *   "message": "No se encontraron bloqueos activos para este usuario"
     * }
     */
    public function destroy(int $userId, UnblockUserRequest $request): JsonResponse
    {
        try {
            $currentUserId = Auth::id();
            
            Log::info('Usuario desbloqueando a otro usuario', [
                'blocker_id' => $currentUserId,
                'blocked_id' => $userId,
                'type' => $request->type
            ]);

            $result = $this->blockService->unblockUser(
                blockerId: $currentUserId,
                blockedId: $userId,
                type: $request->type,
                reason: $request->reason
            );

            return $this->successResponse(
                data: $result,
                message: 'Usuario desbloqueado exitosamente. Ahora podrá interactuar contigo de nuevo.'
            );

        } catch (\App\Exceptions\BlockNotFoundException $e) {
            return $this->errorResponse(
                message: 'No se encontraron bloqueos activos para este usuario',
                code: 404
            );
        } catch (\Exception $e) {
            Log::error('Error al desbloquear usuario', [
                'blocker_id' => $currentUserId,
                'blocked_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al desbloquear usuario. Por favor intenta nuevamente.',
                code: 500
            );
        }
    }

    /**
     * Obtiene la lista de usuarios bloqueados por el usuario autenticado
     * 
     * @group Moderation - Blocks
     * @authenticated
     * 
     * @queryParam type string optional Filtrar por tipo de bloqueo. Example: complete
     * @queryParam status string optional Filtrar por estado (default: active). Example: active
     * @queryParam per_page integer optional Elementos por página (default: 20). Example: 15
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "blocked_users": [
     *       {
     *         "block_id": 456,
     *         "blocked_user": {
     *           "id": 123,
     *           "name": "John Doe",
     *           "profile_photo": "https://..."
     *         },
     *         "type": "complete",
     *         "reason": "harassment",
     *         "blocked_at": "2025-01-10T14:30:00Z",
     *         "is_temporary": false,
     *         "expires_at": null
     *       }
     *     ],
     *     "total_blocked": 5,
     *     "pagination": {
     *       "current_page": 1,
     *       "total_pages": 1,
     *       "per_page": 20,
     *       "total": 5
     *     }
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            $filters = [
                'type' => $request->query('type'),
                'status' => $request->query('status', 'active')
            ];
            
            $perPage = (int)$request->query('per_page', 20);

            $blockedUsers = $this->blockService->getBlockedUsers(
                userId: $userId,
                filters: array_filter($filters),
                perPage: $perPage
            );

            return $this->successResponse(
                data: [
                    'blocked_users' => BlockResource::collection($blockedUsers->items()),
                    'total_blocked' => $blockedUsers->total(),
                    'pagination' => [
                        'current_page' => $blockedUsers->currentPage(),
                        'total_pages' => $blockedUsers->lastPage(),
                        'per_page' => $blockedUsers->perPage(),
                        'total' => $blockedUsers->total()
                    ]
                ],
                message: 'Lista de usuarios bloqueados obtenida exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener usuarios bloqueados', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener la lista de usuarios bloqueados',
                code: 500
            );
        }
    }

    /**
     * Obtiene la lista de usuarios que han bloqueado al usuario autenticado
     * 
     * @group Moderation - Blocks
     * @authenticated
     * 
     * @queryParam per_page integer optional Elementos por página (default: 20). Example: 15
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "blocking_users": [
     *       {
     *         "block_id": 789,
     *         "blocking_user": {
     *           "id": 456,
     *           "name": "Anonymous User"
     *         },
     *         "blocked_at": "2025-01-12T09:15:00Z"
     *       }
     *     ],
     *     "total_blocking": 2,
     *     "pagination": {...}
     *   }
     * }
     */
    public function blockers(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $perPage = (int)$request->query('per_page', 20);

            $blockingUsers = $this->blockService->getBlockingUsers(
                userId: $userId,
                filters: [],
                perPage: $perPage
            );

            return $this->successResponse(
                data: [
                    'blocking_users' => BlockResource::collection($blockingUsers->items()),
                    'total_blocking' => $blockingUsers->total(),
                    'pagination' => [
                        'current_page' => $blockingUsers->currentPage(),
                        'total_pages' => $blockingUsers->lastPage(),
                        'per_page' => $blockingUsers->perPage(),
                        'total' => $blockingUsers->total()
                    ]
                ],
                message: 'Lista de usuarios que te bloquearon obtenida exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener usuarios que bloquearon', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener la lista',
                code: 500
            );
        }
    }

    /**
     * Verifica si existe bloqueo entre el usuario autenticado y otro usuario
     * 
     * @group Moderation - Blocks
     * @authenticated
     * 
     * @urlParam userId integer required ID del usuario a verificar. Example: 123
     * @queryParam type string optional Tipo específico de bloqueo a verificar. Example: messaging
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "is_blocked": true,
     *     "block_type": "complete",
     *     "blocked_by_me": true,
     *     "blocked_by_them": false,
     *     "blocked_at": "2025-01-10T14:30:00Z"
     *   }
     * }
     */
    public function check(int $userId, Request $request): JsonResponse
    {
        try {
            $currentUserId = Auth::id();
            $type = $request->query('type');

            $isBlocked = $this->blockService->isUserBlocked(
                checkerId: $currentUserId,
                targetId: $userId,
                type: $type
            );

            // También verificar bloqueo inverso
            $isBlockedByThem = $this->blockService->isUserBlocked(
                checkerId: $userId,
                targetId: $currentUserId,
                type: $type
            );

            return $this->successResponse(
                data: [
                    'is_blocked' => $isBlocked,
                    'blocked_by_me' => $isBlocked,
                    'blocked_by_them' => $isBlockedByThem,
                    'has_any_block' => $isBlocked || $isBlockedByThem,
                    'can_interact' => !$isBlocked && !$isBlockedByThem
                ],
                message: 'Verificación de bloqueo completada'
            );

        } catch (\Exception $e) {
            Log::error('Error al verificar bloqueo', [
                'current_user' => $currentUserId,
                'target_user' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al verificar el bloqueo',
                code: 500
            );
        }
    }

    /**
     * Verifica si existe bloqueo mutuo entre usuarios
     * 
     * @group Moderation - Blocks
     * @authenticated
     * 
     * @urlParam userId integer required ID del usuario a verificar. Example: 123
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "has_mutual_block": true,
     *     "block_1_to_2": {
     *       "id": 456,
     *       "type": "complete",
     *       "blocked_at": "2025-01-10T14:30:00Z"
     *     },
     *     "block_2_to_1": {
     *       "id": 789,
     *       "type": "complete",
     *       "blocked_at": "2025-01-11T09:15:00Z"
     *     },
     *     "effective_block": "mutual"
     *   }
     * }
     */
    public function mutualCheck(int $userId): JsonResponse
    {
        try {
            $currentUserId = Auth::id();

            $mutualBlockInfo = $this->blockService->checkMutualBlock(
                userId1: $currentUserId,
                userId2: $userId
            );

            return $this->successResponse(
                data: $mutualBlockInfo,
                message: 'Verificación de bloqueo mutuo completada'
            );

        } catch (\Exception $e) {
            Log::error('Error al verificar bloqueo mutuo', [
                'user_1' => $currentUserId,
                'user_2' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al verificar el bloqueo mutuo',
                code: 500
            );
        }
    }

    /**
     * Obtiene estadísticas de bloqueos del usuario autenticado
     * 
     * @group Moderation - Blocks
     * @authenticated
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "user_id": 1,
     *     "total_blocks_made": 5,
     *     "total_blocks_received": 2,
     *     "active_blocks_made": 4,
     *     "active_blocks_received": 1,
     *     "blocks_by_type": {
     *       "complete": 3,
     *       "messaging": 2
     *     },
     *     "blocks_by_reason": {
     *       "harassment": 2,
     *       "spam": 1,
     *       "personal_preference": 2
     *     },
     *     "mutual_blocks": 1,
     *     "temporary_blocks": 0,
     *     "block_frequency": 0.17,
     *     "risk_score": 15,
     *     "last_block_date": "2025-01-15T10:30:00Z",
     *     "generated_at": "2025-01-16T12:00:00Z"
     *   }
     * }
     */
    public function statistics(): JsonResponse
    {
        try {
            $userId = Auth::id();

            $statistics = $this->blockService->getUserBlockStatistics($userId);

            return $this->successResponse(
                data: new BlockStatisticsResource($statistics),
                message: 'Estadísticas obtenidas exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de bloqueos', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener estadísticas',
                code: 500
            );
        }
    }

    /**
     * Obtiene recomendaciones de usuarios para bloquear basadas en patrones
     * 
     * @group Moderation - Blocks
     * @authenticated
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "user_id": 1,
     *     "recommended_blocks": [
     *       {
     *         "user_id": 456,
     *         "reason": "frequently_reported",
     *         "confidence_score": 85,
     *         "report_count": 8,
     *         "risk_indicators": [
     *           "multiple_harassment_reports",
     *           "suspicious_behavior"
     *         ]
     *       },
     *       {
     *         "user_id": 789,
     *         "reason": "similar_blockers",
     *         "confidence_score": 70,
     *         "blocked_by_similar_users": 5
     *       }
     *     ],
     *     "confidence_scores": {
     *       "average": 77.5,
     *       "high_confidence_count": 2
     *     },
     *     "generated_at": "2025-01-16T12:00:00Z"
     *   }
     * }
     */
    public function recommendations(): JsonResponse
    {
        try {
            $userId = Auth::id();

            $recommendations = $this->blockService->getBlockRecommendations($userId);

            return $this->successResponse(
                data: $recommendations,
                message: 'Recomendaciones obtenidas exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener recomendaciones de bloqueo', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener recomendaciones',
                code: 500
            );
        }
    }

    /**
     * Bloquea múltiples usuarios de forma masiva
     * 
     * @group Moderation - Blocks
     * @authenticated
     * 
     * @bodyParam user_ids array required IDs de usuarios a bloquear. Example: [123, 456, 789]
     * @bodyParam type string optional Tipo de bloqueo (default: complete). Example: spam
     * @bodyParam reason string required Razón del bloqueo masivo. Example: spam
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Bloqueo masivo completado",
     *   "data": {
     *     "successful_blocks": 3,
     *     "failed_blocks": 0,
     *     "already_blocked": 0,
     *     "errors": []
     *   }
     * }
     * 
     * @response 422 {
     *   "success": false,
     *   "message": "Validation error",
     *   "errors": {
     *     "user_ids": ["Debes proporcionar al menos un usuario para bloquear"],
     *     "reason": ["La razón es obligatoria para bloqueos masivos"]
     *   }
     * }
     */
    public function massBlock(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids' => 'required|array|min:1|max:50',
            'user_ids.*' => 'required|integer|exists:users,id',
            'type' => 'nullable|string|in:complete,messaging,profile_view,matching,search_results',
            'reason' => 'required|string|in:spam,harassment,fake_profile,scam_attempt'
        ]);

        try {
            $userId = Auth::id();
            
            Log::info('Usuario realizando bloqueo masivo', [
                'blocker_id' => $userId,
                'user_count' => count($request->user_ids),
                'type' => $request->type ?? 'complete'
            ]);

            // Verificar que el usuario no esté intentando bloquearse a sí mismo
            if (in_array($userId, $request->user_ids)) {
                return $this->errorResponse(
                    message: 'No puedes incluirte a ti mismo en la lista de bloqueos',
                    code: 422
                );
            }

            $result = $this->blockService->massBlock(
                blockerId: $userId,
                userIds: $request->user_ids,
                type: $request->type ?? 'complete',
                reason: $request->reason
            );

            $message = "Bloqueo masivo completado. {$result['successful_blocks']} usuario(s) bloqueado(s)";
            
            if ($result['already_blocked'] > 0) {
                $message .= ", {$result['already_blocked']} ya estaba(n) bloqueado(s)";
            }
            
            if ($result['failed_blocks'] > 0) {
                $message .= ", {$result['failed_blocks']} fallo(s)";
            }

            return $this->successResponse(
                data: $result,
                message: $message
            );

        } catch (\Exception $e) {
            Log::error('Error en bloqueo masivo', [
                'blocker_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al realizar el bloqueo masivo',
                code: 500
            );
        }
    }
}