<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Infrastructure\External\{FirebaseService, CloudinaryService};
use App\Domain\Services\GroupChatService;
use App\Http\Requests\Chat\{
    CreateGroupRequest,
    UpdateGroupRequest,
    AddMembersRequest,
    RemoveMemberRequest
};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Log, DB};

/**
 * GroupChatController
 * 
 * Controlador para gestión de chats grupales con integración de:
 * - FirebaseService: Push notifications para eventos de grupo
 * - CloudinaryService: Almacenamiento de avatares de grupo
 * 
 * Funcionalidades:
 * - CRUD de grupos
 * - Gestión de miembros (agregar, remover, roles)
 * - Mensajería grupal
 * - Configuraciones de grupo
 * - Notificaciones grupales
 * 
 * @package App\Http\Controllers\Chat
 * @author ForeverUsInLove Dev Team
 * @version 2.0.0 - Refactorizado con servicios externos
 */
class GroupChatController extends Controller
{
    /**
     * Constructor con inyección de dependencias
     * 
     * @param GroupChatService $groupChatService Servicio de dominio para grupos
     * @param FirebaseService $firebaseService Servicio Firebase (push notifications)
     * @param CloudinaryService $cloudinaryService Servicio Cloudinary (multimedia)
     */
    public function __construct(
        protected GroupChatService $groupChatService,
        protected FirebaseService $firebaseService,
        protected CloudinaryService $cloudinaryService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Listar grupos del usuario
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'per_page' => 'integer|min:1|max:100'
            ]);

            $groups = $this->groupChatService->getUserGroups(
                $user->id,
                $request->per_page ?? 20
            );

            return response()->json([
                'success' => true,
                'groups' => $groups
            ]);

        } catch (\Exception $e) {
            Log::error('GroupChatController: Failed to list groups', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos'
            ], 500);
        }
    }

    /**
     * Crear nuevo grupo
     * 
     * @param CreateGroupRequest $request
     * @return JsonResponse
     */
    public function store(CreateGroupRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('GroupChatController: Creating group', [
                'user_id' => $user->id,
                'group_name' => $request->name
            ]);

            DB::beginTransaction();

            // 1. Subir avatar del grupo si se proporciona
            $avatarUrl = null;
            if ($request->hasFile('avatar')) {
                $uploadResult = $this->cloudinaryService->uploadImage(
                    $request->file('avatar'),
                    folder: 'chat/group-avatars',
                    options: [
                        'transformation' => [
                            ['width' => 500, 'height' => 500, 'crop' => 'fill', 'gravity' => 'face']
                        ]
                    ]
                );
                $avatarUrl = $uploadResult['secure_url'];
            }

            // 2. Crear grupo
            $group = $this->groupChatService->createGroup(
                $user->id,
                $request->name,
                $request->description ?? null,
                $avatarUrl,
                $request->member_ids ?? [],
                $request->validated()
            );

            DB::commit();

            // 3. Notificar a miembros agregados
            $memberIds = $request->member_ids ?? [];
            if (!empty($memberIds)) {
                try {
                    $this->firebaseService->sendBatch(
                        tokens: $this->getUsersTokens($memberIds),
                        notification: [
                            'title' => '👥 Nuevo grupo',
                            'body' => "{$user->name} te agregó al grupo \"{$request->name}\"",
                            'image' => $avatarUrl
                        ],
                        data: [
                            'type' => 'added_to_group',
                            'group_id' => $group->id,
                            'group_name' => $group->name,
                            'added_by' => $user->id,
                            'added_by_name' => $user->name
                        ],
                        options: [
                            'priority' => 'high'
                        ]
                    );

                    Log::info('GroupChatController: Group creation notifications sent', [
                        'group_id' => $group->id,
                        'member_count' => count($memberIds)
                    ]);

                } catch (\Exception $e) {
                    Log::error('GroupChatController: Failed to send group notifications', [
                        'error' => $e->getMessage(),
                        'group_id' => $group->id
                    ]);
                }
            }

            Log::info('GroupChatController: Group created successfully', [
                'group_id' => $group->id
            ]);

            return response()->json([
                'success' => true,
                'group' => $group
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('GroupChatController: Failed to create group', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear grupo'
            ], 500);
        }
    }

    /**
     * Ver un grupo específico
     * 
     * @param Request $request
     * @param int $groupId
     * @return JsonResponse
     */
    public function show(Request $request, int $groupId): JsonResponse
    {
        try {
            $user = $request->user();

            $group = $this->groupChatService->getGroup($groupId);

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            // Validar que el usuario sea miembro
            if (!$group->hasMember($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No eres miembro de este grupo'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'group' => $group
            ]);

        } catch (\Exception $e) {
            Log::error('GroupChatController: Failed to get group', [
                'error' => $e->getMessage(),
                'group_id' => $groupId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupo'
            ], 500);
        }
    }

    /**
     * Actualizar grupo
     * 
     * Solo administradores pueden actualizar.
     * 
     * @param UpdateGroupRequest $request
     * @param int $groupId
     * @return JsonResponse
     */
    public function update(UpdateGroupRequest $request, int $groupId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('GroupChatController: Updating group', [
                'user_id' => $user->id,
                'group_id' => $groupId
            ]);

            // Validar permisos de administrador
            if (!$this->groupChatService->isAdmin($groupId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo administradores pueden actualizar el grupo'
                ], 403);
            }

            DB::beginTransaction();

            // Actualizar avatar si se proporciona
            $avatarUrl = null;
            if ($request->hasFile('avatar')) {
                $uploadResult = $this->cloudinaryService->uploadImage(
                    $request->file('avatar'),
                    folder: 'chat/group-avatars'
                );
                $avatarUrl = $uploadResult['secure_url'];
            }

            $group = $this->groupChatService->updateGroup(
                $groupId,
                $user->id,
                $request->validated(),
                $avatarUrl
            );

            DB::commit();

            // Notificar a miembros del cambio
            try {
                $memberIds = $group->members->pluck('id')->toArray();
                
                $this->firebaseService->sendBatch(
                    tokens: $this->getUsersTokens($memberIds),
                    notification: [],
                    data: [
                        'type' => 'group_updated',
                        'group_id' => $groupId,
                        'updated_by' => $user->id,
                        'changes' => array_keys($request->validated())
                    ]
                );

            } catch (\Exception $e) {
                Log::error('GroupChatController: Failed to send update notifications', [
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'group' => $group
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('GroupChatController: Failed to update group', [
                'error' => $e->getMessage(),
                'group_id' => $groupId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar grupo'
            ], 500);
        }
    }

    /**
     * Eliminar grupo
     * 
     * Solo el creador puede eliminar.
     * 
     * @param Request $request
     * @param int $groupId
     * @return JsonResponse
     */
    public function destroy(Request $request, int $groupId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('GroupChatController: Deleting group', [
                'user_id' => $user->id,
                'group_id' => $groupId
            ]);

            $group = $this->groupChatService->getGroup($groupId);

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            // Solo el creador puede eliminar
            if ($group->creator_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo el creador puede eliminar el grupo'
                ], 403);
            }

            DB::beginTransaction();

            // Obtener miembros antes de eliminar
            $memberIds = $group->members->pluck('id')->toArray();

            $this->groupChatService->deleteGroup($groupId);

            DB::commit();

            // Notificar a miembros
            try {
                $this->firebaseService->sendBatch(
                    tokens: $this->getUsersTokens($memberIds),
                    notification: [
                        'title' => 'Grupo eliminado',
                        'body' => "El grupo \"{$group->name}\" fue eliminado"
                    ],
                    data: [
                        'type' => 'group_deleted',
                        'group_id' => $groupId,
                        'deleted_by' => $user->id
                    ]
                );

            } catch (\Exception $e) {
                Log::error('GroupChatController: Failed to send deletion notifications', [
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Grupo eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('GroupChatController: Failed to delete group', [
                'error' => $e->getMessage(),
                'group_id' => $groupId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar grupo'
            ], 500);
        }
    }

    /**
     * Agregar miembros al grupo
     * 
     * @param AddMembersRequest $request
     * @param int $groupId
     * @return JsonResponse
     */
    public function addMembers(AddMembersRequest $request, int $groupId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('GroupChatController: Adding members to group', [
                'user_id' => $user->id,
                'group_id' => $groupId,
                'new_members' => $request->member_ids
            ]);

            // Validar permisos
            if (!$this->groupChatService->canAddMembers($groupId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para agregar miembros'
                ], 403);
            }

            DB::beginTransaction();

            $group = $this->groupChatService->addMembers($groupId, $request->member_ids);

            DB::commit();

            // Notificar a nuevos miembros
            try {
                $this->firebaseService->sendBatch(
                    tokens: $this->getUsersTokens($request->member_ids),
                    notification: [
                        'title' => '👥 Agregado a grupo',
                        'body' => "{$user->name} te agregó a \"{$group->name}\"",
                        'image' => $group->avatar_url
                    ],
                    data: [
                        'type' => 'added_to_group',
                        'group_id' => $groupId,
                        'group_name' => $group->name,
                        'added_by' => $user->id
                    ],
                    options: [
                        'priority' => 'high'
                    ]
                );

                // Notificar a miembros existentes
                $existingMemberIds = $group->members
                    ->pluck('id')
                    ->reject(fn($id) => in_array($id, $request->member_ids))
                    ->toArray();

                if (!empty($existingMemberIds)) {
                    $this->firebaseService->sendBatch(
                        tokens: $this->getUsersTokens($existingMemberIds),
                        notification: [],
                        data: [
                            'type' => 'members_added',
                            'group_id' => $groupId,
                            'new_member_ids' => $request->member_ids,
                            'added_by' => $user->id
                        ]
                    );
                }

            } catch (\Exception $e) {
                Log::error('GroupChatController: Failed to send member notifications', [
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'group' => $group,
                'members_added' => count($request->member_ids)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('GroupChatController: Failed to add members', [
                'error' => $e->getMessage(),
                'group_id' => $groupId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al agregar miembros'
            ], 500);
        }
    }

    /**
     * Remover miembro del grupo
     * 
     * @param RemoveMemberRequest $request
     * @param int $groupId
     * @return JsonResponse
     */
    public function removeMember(RemoveMemberRequest $request, int $groupId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('GroupChatController: Removing member from group', [
                'user_id' => $user->id,
                'group_id' => $groupId,
                'member_id' => $request->member_id
            ]);

            // Validar permisos
            if (!$this->groupChatService->canRemoveMembers($groupId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para remover miembros'
                ], 403);
            }

            DB::beginTransaction();

            $group = $this->groupChatService->removeMember($groupId, $request->member_id);

            DB::commit();

            // Notificar al miembro removido
            try {
                $this->firebaseService->sendToUser(
                    userId: $request->member_id,
                    notification: [
                        'title' => 'Removido del grupo',
                        'body' => "Fuiste removido del grupo \"{$group->name}\""
                    ],
                    data: [
                        'type' => 'removed_from_group',
                        'group_id' => $groupId,
                        'removed_by' => $user->id
                    ]
                );

                // Notificar a miembros restantes
                $remainingMemberIds = $group->members->pluck('id')->toArray();
                if (!empty($remainingMemberIds)) {
                    $this->firebaseService->sendBatch(
                        tokens: $this->getUsersTokens($remainingMemberIds),
                        notification: [],
                        data: [
                            'type' => 'member_removed',
                            'group_id' => $groupId,
                            'removed_member_id' => $request->member_id,
                            'removed_by' => $user->id
                        ]
                    );
                }

            } catch (\Exception $e) {
                Log::error('GroupChatController: Failed to send removal notifications', [
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'group' => $group
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('GroupChatController: Failed to remove member', [
                'error' => $e->getMessage(),
                'group_id' => $groupId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al remover miembro'
            ], 500);
        }
    }

    /**
     * Salir del grupo (usuario sale voluntariamente)
     * 
     * @param Request $request
     * @param int $groupId
     * @return JsonResponse
     */
    public function leave(Request $request, int $groupId): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('GroupChatController: User leaving group', [
                'user_id' => $user->id,
                'group_id' => $groupId
            ]);

            DB::beginTransaction();

            $group = $this->groupChatService->leaveGroup($groupId, $user->id);

            DB::commit();

            // Notificar a miembros restantes
            try {
                $remainingMemberIds = $group->members->pluck('id')->toArray();
                
                if (!empty($remainingMemberIds)) {
                    $this->firebaseService->sendBatch(
                        tokens: $this->getUsersTokens($remainingMemberIds),
                        notification: [],
                        data: [
                            'type' => 'member_left',
                            'group_id' => $groupId,
                            'left_member_id' => $user->id,
                            'left_member_name' => $user->name
                        ]
                    );
                }

            } catch (\Exception $e) {
                Log::error('GroupChatController: Failed to send leave notifications', [
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Saliste del grupo exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('GroupChatController: Failed to leave group', [
                'error' => $e->getMessage(),
                'group_id' => $groupId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al salir del grupo'
            ], 500);
        }
    }

    /**
     * Promover miembro a administrador
     * 
     * @param Request $request
     * @param int $groupId
     * @return JsonResponse
     */
    public function promoteToAdmin(Request $request, int $groupId): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'member_id' => 'required|exists:users,id'
            ]);

            Log::info('GroupChatController: Promoting member to admin', [
                'user_id' => $user->id,
                'group_id' => $groupId,
                'member_id' => $request->member_id
            ]);

            // Solo admins pueden promover
            if (!$this->groupChatService->isAdmin($groupId, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo administradores pueden promover miembros'
                ], 403);
            }

            $this->groupChatService->promoteToAdmin($groupId, $request->member_id);

            // Notificar al miembro promovido
            try {
                $group = $this->groupChatService->getGroup($groupId);

                $this->firebaseService->sendToUser(
                    userId: $request->member_id,
                    notification: [
                        'title' => '⭐ Promoción a administrador',
                        'body' => "Ahora eres administrador de \"{$group->name}\""
                    ],
                    data: [
                        'type' => 'promoted_to_admin',
                        'group_id' => $groupId,
                        'promoted_by' => $user->id
                    ]
                );

            } catch (\Exception $e) {
                Log::error('GroupChatController: Failed to send promotion notification', [
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Miembro promovido a administrador'
            ]);

        } catch (\Exception $e) {
            Log::error('GroupChatController: Failed to promote member', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al promover miembro'
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
            Log::error('GroupChatController: Failed to get user tokens', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }
}