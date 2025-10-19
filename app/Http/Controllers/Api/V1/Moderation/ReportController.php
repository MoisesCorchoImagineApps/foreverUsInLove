<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Domain\Moderation\Services\ReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Moderation\CreateReportRequest;
use App\Http\Requests\Moderation\ProcessReportRequest;
use App\Http\Requests\Moderation\SearchReportRequest;
use App\Http\Resources\Moderation\ReportResource;
use App\Http\Resources\Moderation\ReportStatisticsResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para la gestión de reportes de usuarios en ForeverUsInLove
 * 
 * Este controlador expone endpoints para crear, consultar y procesar reportes
 * de usuarios que violan las normas de la comunidad. Actúa como capa de presentación
 * delegando toda la lógica de negocio al ReportService.
 * 
 * Endpoints disponibles:
 * - POST   /api/v1/moderation/reports              - Crear nuevo reporte
 * - GET    /api/v1/moderation/reports              - Listar reportes del usuario
 * - GET    /api/v1/moderation/reports/{id}         - Obtener reporte específico
 * - GET    /api/v1/moderation/reports/pending      - Reportes pendientes (moderadores)
 * - POST   /api/v1/moderation/reports/{id}/process - Procesar reporte (moderadores)
 * - POST   /api/v1/moderation/reports/{id}/escalate - Escalar reporte
 * - GET    /api/v1/moderation/reports/statistics   - Estadísticas de reportes
 * - POST   /api/v1/moderation/reports/search       - Búsqueda avanzada
 * - GET    /api/v1/moderation/reports/history/{userId} - Historial de usuario
 * - GET    /api/v1/moderation/reports/{id}/similar - Reportes similares
 * 
 * @package App\Http\Controllers\Api\V1\Moderation
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class ReportController extends Controller
{
    use ApiResponseTrait;

    /**
     * Constructor con inyección de dependencias
     */
    public function __construct(
        private readonly ReportService $reportService
    ) {
        // Middleware de autenticación para todos los endpoints
        $this->middleware('auth:sanctum');
        
        // Middleware para verificar que el perfil esté completo
        $this->middleware('profile.complete');
        
        // Middleware para moderadores en endpoints específicos
        $this->middleware('role:moderator,admin')->only([
            'pending',
            'process',
            'statistics',
            'search'
        ]);
        
        // Rate limiting diferenciado
        $this->middleware('throttle:reports')->only('store');
        $this->middleware('throttle:api')->except('store');
    }

    /**
     * Crea un nuevo reporte de usuario
     * 
     * @group Moderation
     * @authenticated
     * 
     * @bodyParam reported_id integer required ID del usuario a reportar. Example: 123
     * @bodyParam type string required Tipo de reporte. Example: harassment
     * @bodyParam description string required Descripción del reporte (mín 20 caracteres). Example: Este usuario me ha estado enviando mensajes ofensivos repetidamente
     * @bodyParam evidence array optional Evidencia del reporte (screenshots, mensajes, etc.)
     * @bodyParam evidence.screenshots array optional URLs de capturas de pantalla
     * @bodyParam evidence.message_ids array optional IDs de mensajes relacionados
     * 
     * @response 201 {
     *   "success": true,
     *   "message": "Reporte creado exitosamente",
     *   "data": {
     *     "report_id": 12345,
     *     "status": "pending",
     *     "priority": "high",
     *     "reference_number": "REP-202501-012345-A7F9",
     *     "estimated_review_time": "4 hours",
     *     "next_steps": [
     *       "Revisión inicial por moderador",
     *       "Investigación del caso",
     *       "Toma de decisión"
     *     ]
     *   }
     * }
     * 
     * @response 422 {
     *   "success": false,
     *   "message": "Validation error",
     *   "errors": {
     *     "reported_id": ["El usuario a reportar no existe"],
     *     "description": ["La descripción debe tener al menos 20 caracteres"]
     *   }
     * }
     * 
     * @response 429 {
     *   "success": false,
     *   "message": "Ya has reportado a este usuario recientemente"
     * }
     */
    public function store(CreateReportRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            Log::info('Usuario creando reporte', [
                'reporter_id' => $userId,
                'reported_id' => $request->reported_id,
                'type' => $request->type
            ]);

            $result = $this->reportService->createReport(
                reporterId: $userId,
                reportedId: $request->reported_id,
                type: $request->type,
                description: $request->description,
                evidence: $request->evidence ?? [],
                metadata: [
                    'platform' => $request->header('User-Agent'),
                    'app_version' => $request->header('X-App-Version'),
                    'device_info' => $request->header('X-Device-Info')
                ]
            );

            return $this->successResponse(
                data: $result,
                message: 'Reporte creado exitosamente. Nuestro equipo revisará tu reporte pronto.',
                code: 201
            );

        } catch (\App\Exceptions\DuplicateReportException $e) {
            return $this->errorResponse(
                message: 'Ya has reportado a este usuario por esta razón recientemente. Por favor espera antes de enviar otro reporte.',
                code: 429
            );
        } catch (\App\Exceptions\ReportValidationException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: 422
            );
        } catch (\Exception $e) {
            Log::error('Error al crear reporte', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al procesar el reporte. Por favor intenta nuevamente.',
                code: 500
            );
        }
    }

    /**
     * Obtiene los reportes realizados por el usuario autenticado
     * 
     * @group Moderation
     * @authenticated
     * 
     * @queryParam status string optional Filtrar por estado. Example: pending
     * @queryParam type string optional Filtrar por tipo. Example: harassment
     * @queryParam per_page integer optional Elementos por página (default: 15). Example: 20
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "reports": [...],
     *     "pagination": {...}
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
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to')
            ];
            
            $perPage = (int)$request->query('per_page', 15);

            $reports = $this->reportService->getUserReports(
                userId: $userId,
                role: 'reporter',
                filters: array_filter($filters),
                perPage: $perPage
            );

            return $this->successResponse(
                data: [
                    'reports' => ReportResource::collection($reports->items()),
                    'pagination' => [
                        'current_page' => $reports->currentPage(),
                        'total_pages' => $reports->lastPage(),
                        'per_page' => $reports->perPage(),
                        'total' => $reports->total()
                    ]
                ],
                message: 'Reportes obtenidos exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener reportes', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener los reportes',
                code: 500
            );
        }
    }

    /**
     * Obtiene reportes pendientes de moderación
     * 
     * @group Moderation - Moderators Only
     * @authenticated
     * 
     * @queryParam priority string optional Filtrar por prioridad. Example: high
     * @queryParam type string optional Filtrar por tipo. Example: harassment
     * @queryParam assigned_to_me boolean optional Solo reportes asignados al moderador. Example: true
     * @queryParam per_page integer optional Elementos por página (default: 20). Example: 25
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "pending_reports": [...],
     *     "summary": {
     *       "total_pending": 45,
     *       "critical": 3,
     *       "high": 12,
     *       "medium": 20,
     *       "low": 10
     *     },
     *     "pagination": {...}
     *   }
     * }
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $filters = [
                'priority' => $request->query('priority'),
                'type' => $request->query('type')
            ];
            
            $perPage = (int)$request->query('per_page', 20);
            
            // Si el moderador solo quiere sus reportes asignados
            if ($request->boolean('assigned_to_me')) {
                $filters['assigned_to'] = Auth::id();
            }

            $reports = $this->reportService->getPendingReports(
                filters: array_filter($filters),
                perPage: $perPage
            );

            // Obtener summary de reportes pendientes
            $summary = [
                'total_pending' => $reports->total(),
                'critical' => $reports->where('priority', 'critical')->count(),
                'high' => $reports->where('priority', 'high')->count(),
                'medium' => $reports->where('priority', 'medium')->count(),
                'low' => $reports->where('priority', 'low')->count()
            ];

            return $this->successResponse(
                data: [
                    'pending_reports' => ReportResource::collection($reports->items()),
                    'summary' => $summary,
                    'pagination' => [
                        'current_page' => $reports->currentPage(),
                        'total_pages' => $reports->lastPage(),
                        'per_page' => $reports->perPage(),
                        'total' => $reports->total()
                    ]
                ],
                message: 'Reportes pendientes obtenidos exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener reportes pendientes', [
                'moderator_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener reportes pendientes',
                code: 500
            );
        }
    }

    /**
     * Procesa un reporte (acción de moderación)
     * 
     * @group Moderation - Moderators Only
     * @authenticated
     * 
     * @urlParam id integer required ID del reporte. Example: 12345
     * @bodyParam action string required Acción a tomar. Example: warning_issued
     * @bodyParam notes string required Notas del moderador. Example: Usuario advertido por comportamiento inapropiado
     * @bodyParam suspension_days integer optional Días de suspensión (si aplica). Example: 7
     * @bodyParam content_ids array optional IDs de contenido a remover. Example: [1,2,3]
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Reporte procesado exitosamente",
     *   "data": {
     *     "report_id": 12345,
     *     "action_taken": "warning_issued",
     *     "status": "resolved",
     *     "processed_at": "2025-01-16T10:30:00Z"
     *   }
     * }
     */
    public function process(int $id, ProcessReportRequest $request): JsonResponse
    {
        try {
            $moderatorId = Auth::id();
            
            Log::info('Moderador procesando reporte', [
                'moderator_id' => $moderatorId,
                'report_id' => $id,
                'action' => $request->action
            ]);

            $result = $this->reportService->processReport(
                reportId: $id,
                moderatorId: $moderatorId,
                action: $request->action,
                notes: $request->notes,
                additionalData: [
                    'suspension_days' => $request->suspension_days,
                    'content_ids' => $request->content_ids ?? [],
                    'requires_verification' => $request->boolean('requires_verification', false)
                ]
            );

            return $this->successResponse(
                data: $result,
                message: 'Reporte procesado exitosamente'
            );

        } catch (\App\Exceptions\ReportNotFoundException $e) {
            return $this->errorResponse(
                message: 'Reporte no encontrado',
                code: 404
            );
        } catch (\App\Exceptions\InvalidReportStatusException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                code: 422
            );
        } catch (\Exception $e) {
            Log::error('Error al procesar reporte', [
                'report_id' => $id,
                'moderator_id' => $moderatorId,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al procesar el reporte',
                code: 500
            );
        }
    }

    /**
     * Escala un reporte a un nivel superior de moderación
     * 
     * @group Moderation - Moderators Only
     * @authenticated
     * 
     * @urlParam id integer required ID del reporte. Example: 12345
     * @bodyParam reason string required Razón del escalamiento. Example: Caso complejo que requiere revisión por supervisor
     * 
     * @response 200 {
     *   "success": true,
     *   "message": "Reporte escalado exitosamente",
     *   "data": {
     *     "report_id": 12345,
     *     "new_status": "escalated",
     *     "new_priority": "critical",
     *     "escalated_at": "2025-01-16T10:30:00Z"
     *   }
     * }
     */
    public function escalate(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:10|max:500'
        ]);

        try {
            $moderatorId = Auth::id();
            
            $result = $this->reportService->escalateReport(
                reportId: $id,
                reason: $request->reason,
                escalatedBy: $moderatorId
            );

            return $this->successResponse(
                data: $result,
                message: 'Reporte escalado exitosamente al equipo supervisor'
            );

        } catch (\App\Exceptions\ReportNotFoundException $e) {
            return $this->errorResponse(
                message: 'Reporte no encontrado',
                code: 404
            );
        } catch (\Exception $e) {
            Log::error('Error al escalar reporte', [
                'report_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al escalar el reporte',
                code: 500
            );
        }
    }

    /**
     * Obtiene estadísticas detalladas de reportes
     * 
     * @group Moderation - Moderators Only
     * @authenticated
     * 
     * @queryParam period string optional Período de análisis. Example: month
     * @queryParam type string optional Filtrar por tipo de reporte. Example: harassment
     * @queryParam moderator_id integer optional Estadísticas de moderador específico. Example: 5
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "period": "month",
     *     "total_reports": 450,
     *     "pending_reports": 45,
     *     "resolved_reports": 380,
     *     "resolution_rate": 84.44,
     *     "average_resolution_time": "6.5 hours",
     *     "reports_by_type": {...},
     *     "reports_by_priority": {...},
     *     "trend_analysis": {...}
     *   }
     * }
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $filters = [
                'type' => $request->query('type'),
                'moderator_id' => $request->query('moderator_id'),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to')
            ];
            
            $period = $request->query('period', 'month');

            $stats = $this->reportService->getReportStatistics(
                filters: array_filter($filters),
                period: $period
            );

            return $this->successResponse(
                data: new ReportStatisticsResource($stats),
                message: 'Estadísticas obtenidas exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener estadísticas',
                code: 500
            );
        }
    }

    /**
     * Búsqueda avanzada de reportes
     * 
     * @group Moderation - Moderators Only
     * @authenticated
     * 
     * @bodyParam criteria object required Criterios de búsqueda
     * @bodyParam criteria.keyword string optional Palabra clave en descripción
     * @bodyParam criteria.type string optional Tipo de reporte
     * @bodyParam criteria.status string optional Estado del reporte
     * @bodyParam criteria.priority string optional Prioridad
     * @bodyParam criteria.reporter_id integer optional ID del reportador
     * @bodyParam criteria.reported_id integer optional ID del reportado
     * @bodyParam criteria.created_from string optional Fecha desde (Y-m-d)
     * @bodyParam criteria.created_to string optional Fecha hasta (Y-m-d)
     * @bodyParam per_page integer optional Elementos por página (default: 20)
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "reports": [...],
     *     "total_found": 25,
     *     "pagination": {...}
     *   }
     * }
     */
    public function search(SearchReportRequest $request): JsonResponse
    {
        try {
            $perPage = (int)$request->input('per_page', 20);
            
            $reports = $this->reportService->searchReports(
                criteria: $request->criteria,
                perPage: $perPage
            );

            return $this->successResponse(
                data: [
                    'reports' => ReportResource::collection($reports->items()),
                    'total_found' => $reports->total(),
                    'pagination' => [
                        'current_page' => $reports->currentPage(),
                        'total_pages' => $reports->lastPage(),
                        'per_page' => $reports->perPage(),
                        'total' => $reports->total()
                    ]
                ],
                message: 'Búsqueda completada exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error en búsqueda de reportes', [
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
     * Obtiene el historial completo de reportes de un usuario
     * 
     * @group Moderation - Moderators Only
     * @authenticated
     * 
     * @urlParam userId integer required ID del usuario. Example: 123
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "user_id": 123,
     *     "profile_info": {...},
     *     "total_reports_received": 5,
     *     "reports_by_status": {...},
     *     "reports_by_type": {...},
     *     "risk_score": 65,
     *     "account_status": "active",
     *     "patterns_detected": [...]
     *   }
     * }
     */
    public function history(int $userId): JsonResponse
    {
        try {
            $history = $this->reportService->getUserReportHistory($userId);

            return $this->successResponse(
                data: $history,
                message: 'Historial obtenido exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener historial', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener el historial',
                code: 500
            );
        }
    }

    /**
     * Obtiene reportes similares para detectar patrones
     * 
     * @group Moderation - Moderators Only
     * @authenticated
     * 
     * @urlParam id integer required ID del reporte base. Example: 12345
     * @queryParam limit integer optional Límite de resultados (default: 10). Example: 15
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "base_report_id": 12345,
     *     "similar_reports": [...],
     *     "pattern_score": 75,
     *     "risk_indicators": [...]
     *   }
     * }
     */
    public function similar(int $id, Request $request): JsonResponse
    {
        try {
            $limit = (int)$request->query('limit', 10);
            
            $similarReports = $this->reportService->getSimilarReports(
                reportId: $id,
                limit: $limit
            );

            return $this->successResponse(
                data: $similarReports,
                message: 'Reportes similares obtenidos exitosamente'
            );

        } catch (\App\Exceptions\ReportNotFoundException $e) {
            return $this->errorResponse(
                message: 'Reporte no encontrado',
                code: 404
            );
        } catch (\Exception $e) {
            Log::error('Error al obtener reportes similares', [
                'report_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener reportes similares',
                code: 500
            );
        }
    }

    /**
     * Obtiene detalles de un reporte específico
     * 
     * @group Moderation
     * @authenticated
     * 
     * @urlParam id integer required ID del reporte. Example: 12345
     * 
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 12345,
     *     "type": "harassment",
     *     "status": "under_review",
     *     "priority": "high",
     *     "created_at": "2025-01-15T14:30:00Z",
     *     "reporter_info": {...},
     *     "reported_user_info": {...},
     *     "description": "...",
     *     "evidence": [...],
     *     "moderator_notes": "...",
     *     "action_taken": null
     *   }
     * }
     */
    public function show(int $id): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Aquí deberías agregar lógica para verificar que el usuario
            // tenga permisos para ver este reporte (es el reportador o es moderador)
            
            $report = $this->reportService->getReportById($id);
            
            if (!$report) {
                return $this->errorResponse(
                    message: 'Reporte no encontrado',
                    code: 404
                );
            }

            return $this->successResponse(
                data: new ReportResource($report),
                message: 'Reporte obtenido exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener reporte', [
                'report_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                message: 'Error al obtener el reporte',
                code: 500
            );
        }
    }
}
