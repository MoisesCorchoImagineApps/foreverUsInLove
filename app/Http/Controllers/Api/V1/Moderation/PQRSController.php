<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Domain\Moderation\Services\PQRSService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Moderation\CreatePQRSRequest;
use App\Http\Requests\Moderation\UpdatePQRSRequest;
use App\Http\Resources\Moderation\PQRSResource;
use App\Http\Resources\Moderation\PQRSStatisticsResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para la gestión de PQRS (Peticiones, Quejas, Reclamos y Sugerencias)
 * 
 * Este controlador expone endpoints para crear, consultar y gestionar PQRS en ForeverUsInLove.
 * Actúa como capa de presentación delegando toda la lógica de negocio al PQRSService.
 * 
 * Endpoints disponibles:
 * - POST   /api/v1/moderation/pqrs                 - Crear nueva PQRS
 * - GET    /api/v1/moderation/pqrs                 - Listar PQRS del usuario
 * - GET    /api/v1/moderation/pqrs/{id}            - Obtener PQRS específica
 * - PUT    /api/v1/moderation/pqrs/{id}            - Actualizar PQRS (agentes)
 * - GET    /api/v1/moderation/pqrs/pending         - PQRS pendientes (agentes)
 * - POST   /api/v1/moderation/pqrs/{id}/escalate   - Escalar PQRS
 * - GET    /api/v1/moderation/pqrs/statistics      - Estadísticas de PQRS
 * - POST   /api/v1/moderation/pqrs/search          - Búsqueda avanzada
 * - GET    /api/v1/moderation/pqrs/{id}/history    - Historial de PQRS
 * 
 * Tipos de PQRS:
 * - petition: Petición - Solicitud formal de servicio
 * - complaint: Queja - Insatisfacción con servicio
 * - claim: Reclamo - Solicitud de compensación
 * - suggestion: Sugerencia - Propuesta de mejora
 * 
 * @package App\Http\Controllers\Api\V1\Moderation
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class PQRSController extends Controller
{
    use ApiResponseTrait;

    /**
     * Constructor con inyección de dependencias
     */
    public function __construct(
        private readonly PQRSService $pqrsService
    ) {
        // Middleware de autenticación para todos los endpoints
        $this->middleware('auth:sanctum');
        
        // Middleware para verificar que el perfil esté completo
        $this->middleware('profile.complete');
        
        // Middleware para agentes/moderadores en endpoints específicos
        $this->middleware('role:agent,moderator,admin')->only([
            'pending',
            'update',
            'statistics'
        ]);
        
        // Rate limiting diferenciado
        $this->middleware('throttle:pqrs')->only('store');
        $this->middleware('throttle:api')->except('store');
    }

    /**
     * Crea una nueva PQRS
     * 
     * @group Moderation - PQRS
     * @authenticated
     * 
     * @bodyParam type string required Tipo de PQRS. Example: complaint
     * @bodyParam category string required Categoría específica. Example: billing_issue
     * @bodyParam subject string required Asunto (mín 10 caracteres). Example: Problema con cargo duplicado en mi tarjeta
     * @bodyParam description string required Descripción detallada (mín 20 caracteres). Example: Me cobraron dos veces la suscripción premium este mes
     * @bodyParam attachments array optional Archivos adjuntos (screenshots, documentos)
     * @bodyParam attachments.*.url string URL del archivo adjunto
     * @bodyParam attachments.*.type string Tipo de archivo (image, document, etc.)
     * 
     * @response 201 {
     *   "success": true,
     *   "message": "PQRS creada exitosamente. Nuestro equipo te responderá pronto.",
     *   "data": {
     *     "pqrs_id": 12345,
     *     "ticket_number": "PQRS-20250116-0123",
     *     "status": "submitted",
     *     "priority": "high",
     *     "category": "billing_issue",
     *     "sla_deadline": "2025-01-17T14:30:00Z",
     *     "estimated_resolution": "24 hours",
     *     "auto_assigned": true,
     *     "assignment_info": {
     *       "agent_id": 5,
     *       "agent_name": "María González",
     *       "specialization": "billing_support"
     *     },
     *     "auto_response": {
     *       "message": "Hemos recibido tu solicitud sobre problema de facturación...",
     *       "sent_at": "2025-01-16T14:30:00Z"
     *     },
     *     "next_steps": [
     *       "Revisión inicial por agente",
     *       "Verificación de datos de facturación",
     *       "Resolución y respuesta"
     *     ]
     *   }
     * }
     * 
     * @response 422 {
     *   "success": false,
     *   "message": "Validation error",
     *   "errors": {
     *     "subject": ["El asunto debe tener al menos 10 caracteres"],
     *     "description": ["La descripción debe tener al menos 20 caracteres"]
     *   }
     * }
     */
    public function store(CreatePQRSRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            Log::info('Usuario creando PQRS', [
                'user_id' => $userId,
                'type' => $request->type,
                'category' => $request->category
            ]);

            $result = $this->pqrsService->createPQRS(
                userId: $userId,
                type: $request->type,
                category: $request->category,
                subject: $request->subject,
                description: $request->description,
                attachments: $request->attachments ?? [],
                metadata: [
                    'channel' => 'in_app',
                    'platform' => $request->header('User-Agent'),
                    'app_version' => $request->header('X-App-Version'),
                    'device_info' => $request->header('X-Device-Info')
                ]
            );

            return $this->successResponse(
                data: $result,
                message: 'PQRS creada exitosamente. Nuestro equipo te responderá según el tiempo estimado de resolución.',
                code: 201
            );

        } catch (\App\Exceptions\PQRSValidationException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: 422
            );
        } catch (\Exception $e) {
            Log::error('Error al crear PQRS', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al procesar tu solicitud. Por favor intenta nuevamente.',
                code: 500
            );
        }
    }

    /**
     * Obtiene las PQRS del usuario autenticado
     * 
     * @group Moderation - PQRS
     * @authenticated
     * 
     * @queryParam status string optional Filtrar por estado. Example: in_progress
     * @queryParam type string optional Filtrar por tipo. Example: complaint
     * @queryParam category string optional Filtrar por categoría. Example: billing_issue
     * @queryParam per_page integer optional Elementos por página (default: 15). Example: 20
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "pqrs": [
     *       {
     *         "id": 12345,
     *         "ticket_number": "PQRS-20250116-0123",
     *         "type": "complaint",
     *         "category": "billing_issue",
     *         "subject": "Problema con cargo duplicado",
     *         "status": "in_progress",
     *         "priority": "high",
     *         "created_at": "2025-01-16T14:30:00Z",
     *         "sla_deadline": "2025-01-17T14:30:00Z",
     *         "time_remaining": "23 hours",
     *         "assigned_agent": {
     *           "name": "María González",
     *           "specialization": "billing_support"
     *         }
     *       }
     *     ],
     *     "summary": {
     *       "total": 10,
     *       "pending": 2,
     *       "in_progress": 3,
     *       "resolved": 5
     *     },
     *     "pagination": {
     *       "current_page": 1,
     *       "total_pages": 1,
     *       "per_page": 15,
     *       "total": 10
     *     }
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            $filters = [
                'status' => $request->query('status'),
                'type' => $request->query('type'),
                'category' => $request->query('category'),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to')
            ];
            
            $perPage = (int)$request->query('per_page', 15);

            $pqrs = $this->pqrsService->getUserPQRS(
                userId: $userId,
                filters: array_filter($filters),
                perPage: $perPage
            );

            // Calcular summary
            $summary = [
                'total' => $pqrs->total(),
                'pending' => $pqrs->where('status', 'submitted')->count() + 
                           $pqrs->where('status', 'received')->count(),
                'in_progress' => $pqrs->where('status', 'in_progress')->count() + 
                               $pqrs->where('status', 'assigned')->count(),
                'resolved' => $pqrs->where('status', 'resolved')->count(),
                'closed' => $pqrs->where('status', 'closed')->count()
            ];

            return $this->successResponse(
                data: [
                    'pqrs' => PQRSResource::collection($pqrs->items()),
                    'summary' => $summary,
                    'pagination' => [
                        'current_page' => $pqrs->currentPage(),
                        'total_pages' => $pqrs->lastPage(),
                        'per_page' => $pqrs->perPage(),
                        'total' => $pqrs->total()
                    ]
                ],
                message: 'PQRS obtenidas exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener PQRS del usuario', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener las solicitudes',
                code: 500
            );
        }
    }

    /**
     * Obtiene PQRS pendientes para agentes
     * 
     * @group Moderation - PQRS (Agents Only)
     * @authenticated
     * 
     * @queryParam priority string optional Filtrar por prioridad. Example: high
     * @queryParam category string optional Filtrar por categoría. Example: billing_issue
     * @queryParam assigned_to_me boolean optional Solo PQRS asignadas al agente. Example: true
     * @queryParam per_page integer optional Elementos por página (default: 20). Example: 25
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "pending_pqrs": [...],
     *     "summary": {
     *       "total_pending": 45,
     *       "critical": 2,
     *       "urgent": 8,
     *       "high": 15,
     *       "medium": 12,
     *       "low": 8,
     *       "overdue": 3
     *     },
     *     "pagination": {...}
     *   }
     * }
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $agentId = Auth::id();
            
            $filters = [
                'priority' => $request->query('priority'),
                'category' => $request->query('category')
            ];
            
            // Si el agente solo quiere sus PQRS asignadas
            if ($request->boolean('assigned_to_me')) {
                $filters['assigned_to'] = $agentId;
            }
            
            $perPage = (int)$request->query('per_page', 20);

            $pqrs = $this->pqrsService->getPendingPQRS(
                agentId: $request->boolean('assigned_to_me') ? $agentId : null,
                filters: array_filter($filters),
                perPage: $perPage
            );

            // Summary de PQRS pendientes
            $now = now();
            $summary = [
                'total_pending' => $pqrs->total(),
                'critical' => $pqrs->where('priority', 'critical')->count(),
                'urgent' => $pqrs->where('priority', 'urgent')->count(),
                'high' => $pqrs->where('priority', 'high')->count(),
                'medium' => $pqrs->where('priority', 'medium')->count(),
                'low' => $pqrs->where('priority', 'low')->count(),
                'overdue' => $pqrs->filter(function($item) use ($now) {
                    return $now->gt($item->sla_deadline);
                })->count()
            ];

            return $this->successResponse(
                data: [
                    'pending_pqrs' => PQRSResource::collection($pqrs->items()),
                    'summary' => $summary,
                    'pagination' => [
                        'current_page' => $pqrs->currentPage(),
                        'total_pages' => $pqrs->lastPage(),
                        'per_page' => $pqrs->perPage(),
                        'total' => $pqrs->total()
                    ]
                ],
                message: 'PQRS pendientes obtenidas exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener PQRS pendientes', [
                'agent_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener PQRS pendientes',
                code: 500
            );
        }
    }

    /**
     * Actualiza una PQRS existente (acción de agente)
     * 
     * @group Moderation - PQRS (Agents Only)
     * @authenticated
     * 
     * @urlParam id integer required ID de la PQRS. Example: 12345
     * @bodyParam status string required Nuevo estado. Example: in_progress
     * @bodyParam response string optional Respuesta del agente. Example: Hemos revisado tu caso...
     * @bodyParam internal_notes string optional Notas internas (no visibles para el usuario)
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "PQRS actualizada exitosamente",
     *   "data": {
     *     "pqrs_id": 12345,
     *     "previous_status": "submitted",
     *     "new_status": "in_progress",
     *     "updated_at": "2025-01-16T15:30:00Z",
     *     "sla_compliant": true,
     *     "next_actions": [
     *       "Continuar investigación",
     *       "Contactar área de facturación",
     *       "Enviar actualización al usuario"
     *     ]
     *   }
     * }
     */
    public function update(int $id, UpdatePQRSRequest $request): JsonResponse
    {
        try {
            $agentId = Auth::id();
            
            Log::info('Agente actualizando PQRS', [
                'agent_id' => $agentId,
                'pqrs_id' => $id,
                'new_status' => $request->status
            ]);

            $result = $this->pqrsService->updatePQRS(
                pqrsId: $id,
                agentId: $agentId,
                status: $request->status,
                response: $request->response,
                additionalData: [
                    'internal_notes' => $request->internal_notes,
                    'resolution_details' => $request->resolution_details,
                    'follow_up_required' => $request->boolean('follow_up_required', false)
                ]
            );

            return $this->successResponse(
                data: $result,
                message: 'PQRS actualizada exitosamente'
            );

        } catch (\App\Exceptions\PQRSNotFoundException $e) {
            return $this->errorResponse(
                message: 'PQRS no encontrada',
                code: 404
            );
        } catch (\App\Exceptions\InvalidPQRSStatusException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: 422
            );
        } catch (\Exception $e) {
            Log::error('Error al actualizar PQRS', [
                'pqrs_id' => $id,
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al actualizar la PQRS',
                code: 500
            );
        }
    }

    /**
     * Escala una PQRS a un nivel superior
     * 
     * @group Moderation - PQRS (Agents Only)
     * @authenticated
     * 
     * @urlParam id integer required ID de la PQRS. Example: 12345
     * @bodyParam reason string required Razón del escalamiento. Example: Requiere aprobación de supervisor
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "PQRS escalada exitosamente al equipo supervisor",
     *   "data": {
     *     "pqrs_id": 12345,
     *     "new_status": "escalated",
     *     "new_priority": "urgent",
     *     "new_sla_deadline": "2025-01-16T19:30:00Z",
     *     "escalated_at": "2025-01-16T15:30:00Z"
     *   }
     * }
     */
    public function escalate(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:500'
        ]);

        try {
            $agentId = Auth::id();
            
            $result = $this->pqrsService->escalatePQRS(
                pqrsId: $id,
                reason: $request->reason,
                escalatedBy: $agentId
            );

            return $this->successResponse(
                data: $result,
                message: 'PQRS escalada exitosamente al equipo supervisor'
            );

        } catch (\App\Exceptions\PQRSNotFoundException $e) {
            return $this->errorResponse(
                message: 'PQRS no encontrada',
                code: 404
            );
        } catch (\Exception $e) {
            Log::error('Error al escalar PQRS', [
                'pqrs_id' => $id,
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al escalar la PQRS',
                code: 500
            );
        }
    }

    /**
     * Obtiene estadísticas detalladas de PQRS
     * 
     * @group Moderation - PQRS (Agents Only)
     * @authenticated
     * 
     * @queryParam period string optional Período de análisis. Example: month
     * @queryParam type string optional Filtrar por tipo de PQRS. Example: complaint
     * @queryParam category string optional Filtrar por categoría. Example: billing_issue
     * @queryParam agent_id integer optional Estadísticas de agente específico. Example: 5
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "period": "month",
     *     "total_pqrs": 450,
     *     "pending_pqrs": 45,
     *     "resolved_pqrs": 380,
     *     "overdue_pqrs": 8,
     *     "resolution_rate": 84.44,
     *     "average_resolution_time": "18.5 hours",
     *     "sla_compliance_rate": 92.5,
     *     "pqrs_by_type": {...},
     *     "pqrs_by_category": {...},
     *     "pqrs_by_priority": {...},
     *     "agent_performance": {...},
     *     "satisfaction_scores": {
     *       "average": 4.2,
     *       "total_responses": 320
     *     },
     *     "trending_issues": [...]
     *   }
     * }
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $filters = [
                'type' => $request->query('type'),
                'category' => $request->query('category'),
                'agent_id' => $request->query('agent_id'),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to')
            ];
            
            $period = $request->query('period', 'month');

            $stats = $this->pqrsService->getPQRSStatistics(
                filters: array_filter($filters),
                period: $period
            );

            return $this->successResponse(
                data: new PQRSStatisticsResource($stats),
                message: 'Estadísticas obtenidas exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de PQRS', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener estadísticas',
                code: 500
            );
        }
    }

    /**
     * Búsqueda avanzada de PQRS
     * 
     * @group Moderation - PQRS (Agents Only)
     * @authenticated
     * 
     * @bodyParam criteria object required Criterios de búsqueda
     * @bodyParam criteria.keyword string optional Palabra clave en asunto o descripción
     * @bodyParam criteria.type string optional Tipo de PQRS
     * @bodyParam criteria.category string optional Categoría
     * @bodyParam criteria.status string optional Estado
     * @bodyParam criteria.priority string optional Prioridad
     * @bodyParam criteria.user_id integer optional ID del usuario
     * @bodyParam criteria.assigned_to integer optional ID del agente asignado
     * @bodyParam criteria.created_from string optional Fecha desde (Y-m-d)
     * @bodyParam criteria.created_to string optional Fecha hasta (Y-m-d)
     * @bodyParam per_page integer optional Elementos por página (default: 20)
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "pqrs": [...],
     *     "total_found": 25,
     *     "pagination": {...}
     *   }
     * }
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'criteria' => 'required|array',
            'criteria.keyword' => 'nullable|string|min:3',
            'criteria.type' => 'nullable|string',
            'criteria.category' => 'nullable|string',
            'criteria.status' => 'nullable|string',
            'criteria.priority' => 'nullable|string',
            'criteria.user_id' => 'nullable|integer|exists:users,id',
            'criteria.assigned_to' => 'nullable|integer|exists:users,id',
            'criteria.created_from' => 'nullable|date',
            'criteria.created_to' => 'nullable|date|after_or_equal:criteria.created_from',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $perPage = (int)$request->input('per_page', 20);
            
            $pqrs = $this->pqrsService->searchPQRS(
                criteria: $request->criteria,
                perPage: $perPage
            );

            return $this->successResponse(
                data: [
                    'pqrs' => PQRSResource::collection($pqrs->items()),
                    'total_found' => $pqrs->total(),
                    'pagination' => [
                        'current_page' => $pqrs->currentPage(),
                        'total_pages' => $pqrs->lastPage(),
                        'per_page' => $pqrs->perPage(),
                        'total' => $pqrs->total()
                    ]
                ],
                message: 'Búsqueda completada exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error en búsqueda de PQRS', [
                'criteria' => $request->criteria,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al realizar la búsqueda',
                code: 500
            );
        }
    }

    /**
     * Obtiene el historial completo de una PQRS
     * 
     * @group Moderation - PQRS
     * @authenticated
     * 
     * @urlParam id integer required ID de la PQRS. Example: 12345
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "pqrs_id": 12345,
     *     "ticket_number": "PQRS-20250116-0123",
     *     "basic_info": {
     *       "type": "complaint",
     *       "category": "billing_issue",
     *       "priority": "high",
     *       "status": "resolved"
     *     },
     *     "timeline": [
     *       {
     *         "timestamp": "2025-01-16T14:30:00Z",
     *         "event": "created",
     *         "actor": "User #123",
     *         "details": "PQRS creada"
     *       },
     *       {
     *         "timestamp": "2025-01-16T14:35:00Z",
     *         "event": "assigned",
     *         "actor": "System",
     *         "details": "Asignada a María González"
     *       }
     *     ],
     *     "responses": [...],
     *     "attachments": [...],
     *     "sla_info": {
     *       "deadline": "2025-01-17T14:30:00Z",
     *       "is_overdue": false,
     *       "time_remaining": "22 hours"
     *     },
     *     "satisfaction_rating": 5,
     *     "related_items": []
     *   }
     * }
     */
    public function history(int $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            $history = $this->pqrsService->getPQRSHistory($id);
            
            // Verificar que el usuario tenga permisos para ver este historial
            // (es el creador o es un agente/moderador)
            $userRoles = Auth::user()->roles->pluck('name')->toArray();
            $isAuthorized = $history['user_id'] === $userId || 
                          !empty(array_intersect($userRoles, ['agent', 'moderator', 'admin']));
            
            if (!$isAuthorized) {
                return $this->errorResponse(
                    message: 'No tienes permisos para ver este historial',
                    code: 403
                );
            }

            return $this->successResponse(
                data: $history,
                message: 'Historial obtenido exitosamente'
            );

        } catch (\App\Exceptions\PQRSNotFoundException $e) {
            return $this->errorResponse(
                message: 'PQRS no encontrada',
                code: 404
            );
        } catch (\Exception $e) {
            Log::error('Error al obtener historial de PQRS', [
                'pqrs_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener el historial',
                code: 500
            );
        }
    }

    /**
     * Obtiene detalles de una PQRS específica
     * 
     * @group Moderation - PQRS
     * @authenticated
     * 
     * @urlParam id integer required ID de la PQRS. Example: 12345
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 12345,
     *     "ticket_number": "PQRS-20250116-0123",
     *     "type": "complaint",
     *     "category": "billing_issue",
     *     "subject": "Problema con cargo duplicado",
     *     "description": "Me cobraron dos veces...",
     *     "status": "in_progress",
     *     "priority": "high",
     *     "created_at": "2025-01-16T14:30:00Z",
     *     "sla_deadline": "2025-01-17T14:30:00Z",
     *     "assigned_agent": {...},
     *     "responses": [...],
     *     "attachments": [...]
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Aquí deberías implementar la lógica para obtener una PQRS por ID
            // y verificar permisos de acceso
            
            // Por ahora, simulamos la respuesta
            return $this->successResponse(
                data: [
                    'id' => $id,
                    'message' => 'Implementar método show en PQRSService'
                ],
                message: 'PQRS obtenida exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener PQRS', [
                'pqrs_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener la PQRS',
                code: 500
            );
        }
    }
}