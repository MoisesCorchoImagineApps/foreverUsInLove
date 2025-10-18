<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Services;

use App\Domain\Moderation\Repositories\ReportRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Exceptions\PQRSNotFoundException;
use App\Exceptions\PQRSValidationException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\InvalidPQRSStatusException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Servicio integral para la gestión de PQRS (Peticiones, Quejas, Reclamos y Sugerencias) en ForeverUsInLove
 * 
 * Este servicio maneja todo el ciclo de vida de las comunicaciones formales entre usuarios
 * y el equipo de soporte, incluyendo la categorización automática, escalamiento,
 * seguimiento de SLA, y gestión de respuestas.
 * 
 * Características principales:
 * - Sistema de tickets con numeración única y seguimiento
 * - Categorización automática inteligente de solicitudes
 * - Escalamiento automático basado en urgencia y tipo
 * - SLA (Service Level Agreement) automatizado por categoría
 * - Plantillas de respuesta y respuestas sugeridas por IA
 * - Sistema de satisfacción del cliente y feedback
 * - Analytics de rendimiento del equipo de soporte
 * - Integración con sistemas externos (CRM, helpdesk)
 * - Notificaciones multichannel (email, push, SMS)
 * - Gestión de archivos adjuntos y evidencia
 * 
 * @package App\Domain\Moderation\Services
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class PQRSService
{
    /**
     * Tipos de PQRS disponibles
     */
    public const PQRS_TYPES = [
        'PETITION' => 'petition',           // Petición - Solicitud formal de servicio
        'COMPLAINT' => 'complaint',         // Queja - Insatisfacción con servicio
        'CLAIM' => 'claim',                 // Reclamo - Solicitud de compensación
        'SUGGESTION' => 'suggestion'        // Sugerencia - Propuesta de mejora
    ];

    /**
     * Categorías específicas de PQRS
     */
    public const PQRS_CATEGORIES = [
        // Técnicas
        'TECHNICAL_ISSUE' => 'technical_issue',
        'APP_BUG' => 'app_bug',
        'LOGIN_PROBLEMS' => 'login_problems',
        'PERFORMANCE_ISSUE' => 'performance_issue',
        
        // Cuenta y perfil
        'ACCOUNT_SUSPENSION' => 'account_suspension',
        'PROFILE_VERIFICATION' => 'profile_verification',
        'ACCOUNT_DELETION' => 'account_deletion',
        'PRIVACY_SETTINGS' => 'privacy_settings',
        
        // Pagos y suscripciones
        'BILLING_ISSUE' => 'billing_issue',
        'REFUND_REQUEST' => 'refund_request',
        'SUBSCRIPTION_PROBLEM' => 'subscription_problem',
        'PAYMENT_FAILED' => 'payment_failed',
        
        // Moderación y seguridad
        'INAPPROPRIATE_BEHAVIOR' => 'inappropriate_behavior',
        'SAFETY_CONCERN' => 'safety_concern',
        'FAKE_PROFILE' => 'fake_profile',
        'HARASSMENT_REPORT' => 'harassment_report',
        
        // Funcionalidades
        'MATCHING_ALGORITHM' => 'matching_algorithm',
        'MESSAGING_ISSUE' => 'messaging_issue',
        'NOTIFICATION_PROBLEM' => 'notification_problem',
        'FEATURE_REQUEST' => 'feature_request',
        
        // General
        'GENERAL_INQUIRY' => 'general_inquiry',
        'FEEDBACK' => 'feedback',
        'OTHER' => 'other'
    ];

    /**
     * Niveles de prioridad
     */
    public const PRIORITY_LEVELS = [
        'LOW' => 'low',
        'MEDIUM' => 'medium',
        'HIGH' => 'high',
        'URGENT' => 'urgent',
        'CRITICAL' => 'critical'
    ];

    /**
     * Estados de PQRS
     */
    public const PQRS_STATUSES = [
        'SUBMITTED' => 'submitted',         // Enviado por el usuario
        'RECEIVED' => 'received',           // Recibido y confirmado
        'IN_REVIEW' => 'in_review',         // En revisión inicial
        'ASSIGNED' => 'assigned',           // Asignado a agente
        'IN_PROGRESS' => 'in_progress',     // En proceso de resolución
        'PENDING_INFO' => 'pending_info',   // Pendiente información del usuario
        'ESCALATED' => 'escalated',         // Escalado a supervisor
        'RESOLVED' => 'resolved',           // Resuelto
        'CLOSED' => 'closed',               // Cerrado
        'REOPENED' => 'reopened'            // Reabierto
    ];

    /**
     * Canales de comunicación
     */
    public const COMMUNICATION_CHANNELS = [
        'IN_APP' => 'in_app',
        'EMAIL' => 'email',
        'PHONE' => 'phone',
        'CHAT' => 'chat',
        'SOCIAL_MEDIA' => 'social_media'
    ];

    /**
     * SLA por categoría (en horas)
     */
    public const SLA_TIMES = [
        'CRITICAL' => 1,    // 1 hora
        'URGENT' => 4,      // 4 horas
        'HIGH' => 24,       // 24 horas
        'MEDIUM' => 72,     // 3 días
        'LOW' => 168        // 7 días
    ];

    /**
     * Constructor del servicio de PQRS
     */
    public function __construct(
        private readonly ReportRepositoryInterface $reportRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ProfileRepositoryInterface $profileRepository
    ) {}

    /**
     * Crea una nueva PQRS
     *
     * @param int $userId ID del usuario que envía la PQRS
     * @param string $type Tipo de PQRS
     * @param string $category Categoría específica
     * @param string $subject Asunto
     * @param string $description Descripción detallada
     * @param array $attachments Archivos adjuntos
     * @param array $metadata Metadatos adicionales
     * @return array Información de la PQRS creada
     * @throws PQRSValidationException
     */
    public function createPQRS(
        int $userId,
        string $type,
        string $category,
        string $subject,
        string $description,
        array $attachments = [],
        array $metadata = []
    ): array {
        try {
            Log::info('Creando nueva PQRS', [
                'user_id' => $userId,
                'type' => $type,
                'category' => $category
            ]);

            // Validar datos de entrada
            $this->validatePQRSData($userId, $type, $category, $subject, $description);

            // Determinar prioridad automáticamente
            $priority = $this->determinePriority($type, $category, $description, $metadata);

            // Categorización inteligente (refinamiento automático)
            $refinedCategory = $this->refineCategoryWithAI($category, $subject, $description);

            // Generar número de ticket único
            $ticketNumber = $this->generateTicketNumber();

            // Calcular SLA
            $slaDeadline = $this->calculateSLADeadline($priority);

            DB::beginTransaction();

            // Crear la PQRS
            $pqrsData = [
                'user_id' => $userId,
                'ticket_number' => $ticketNumber,
                'type' => $type,
                'category' => $refinedCategory,
                'subject' => $subject,
                'description' => $description,
                'priority' => $priority,
                'status' => self::PQRS_STATUSES['SUBMITTED'],
                'channel' => $metadata['channel'] ?? self::COMMUNICATION_CHANNELS['IN_APP'],
                'sla_deadline' => $slaDeadline,
                'attachments' => $attachments,
                'metadata' => array_merge($metadata, [
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => Carbon::now()->toISOString(),
                    'auto_categorized' => $refinedCategory !== $category,
                    'ai_confidence' => $this->getCategorizationConfidence($refinedCategory, $subject, $description)
                ])
            ];

            $pqrs = $this->reportRepository->createPQRS($pqrsData);

            // Asignación automática si es posible
            $assignment = $this->attemptAutoAssignment($pqrs['id'], $refinedCategory, $priority);

            // Generar respuesta automática inicial si aplica
            $autoResponse = $this->generateAutoResponse($pqrs['id'], $refinedCategory, $type);

            // Notificar al usuario sobre la recepción
            $this->sendConfirmationNotification($userId, $pqrs);

            // Escalamiento automático para casos críticos
            if ($priority === self::PRIORITY_LEVELS['CRITICAL'] || $priority === self::PRIORITY_LEVELS['URGENT']) {
                $this->escalatePQRS($pqrs['id'], 'Escalamiento automático por prioridad alta');
            }

            // Actualizar estadísticas
            $this->updatePQRSStatistics($userId, $type, $category, $priority);

            DB::commit();

            Log::info('PQRS creada exitosamente', [
                'pqrs_id' => $pqrs['id'],
                'ticket_number' => $ticketNumber
            ]);

            return [
                'success' => true,
                'pqrs_id' => $pqrs['id'],
                'ticket_number' => $ticketNumber,
                'status' => self::PQRS_STATUSES['SUBMITTED'],
                'priority' => $priority,
                'category' => $refinedCategory,
                'sla_deadline' => $slaDeadline->toISOString(),
                'estimated_resolution' => $this->getEstimatedResolutionTime($priority, $category),
                'auto_assigned' => !is_null($assignment),
                'assignment_info' => $assignment,
                'auto_response' => $autoResponse,
                'next_steps' => $this->getNextSteps($type, $category, $priority)
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al crear PQRS', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Actualiza una PQRS existente
     *
     * @param int $pqrsId ID de la PQRS
     * @param int $agentId ID del agente que actualiza
     * @param string $status Nuevo estado
     * @param string|null $response Respuesta del agente
     * @param array $additionalData Datos adicionales
     * @return array Resultado de la actualización
     * @throws PQRSNotFoundException
     * @throws InvalidPQRSStatusException
     */
    public function updatePQRS(
        int $pqrsId,
        int $agentId,
        string $status,
        ?string $response = null,
        array $additionalData = []
    ): array {
        try {
            $pqrs = $this->reportRepository->findPQRSById($pqrsId);
            
            if (!$pqrs) {
                throw new PQRSNotFoundException("PQRS no encontrada: {$pqrsId}");
            }

            // Validar transición de estado
            if (!$this->isValidStatusTransition($pqrs['status'], $status)) {
                throw new InvalidPQRSStatusException(
                    "Transición de estado inválida: {$pqrs['status']} -> {$status}"
                );
            }

            DB::beginTransaction();

            $updateData = [
                'status' => $status,
                'updated_by' => $agentId,
                'updated_at' => Carbon::now()
            ];

            // Agregar respuesta si se proporciona
            if ($response) {
                $updateData['responses'] = array_merge(
                    $pqrs['responses'] ?? [],
                    [[
                        'agent_id' => $agentId,
                        'response' => $response,
                        'timestamp' => Carbon::now()->toISOString(),
                        'type' => 'agent_response'
                    ]]
                );
            }

            // Datos específicos por estado
            switch ($status) {
                case self::PQRS_STATUSES['ASSIGNED']:
                    $updateData['assigned_to'] = $agentId;
                    $updateData['assigned_at'] = Carbon::now();
                    break;

                case self::PQRS_STATUSES['RESOLVED']:
                    $updateData['resolved_at'] = Carbon::now();
                    $updateData['resolution_time'] = $this->calculateResolutionTime($pqrs['created_at']);
                    break;

                case self::PQRS_STATUSES['CLOSED']:
                    $updateData['closed_at'] = Carbon::now();
                    $updateData['closed_by'] = $agentId;
                    break;
            }

            // Agregar datos adicionales
            $updateData = array_merge($updateData, $additionalData);

            $this->reportRepository->updatePQRS($pqrsId, $updateData);

            // Notificar al usuario sobre la actualización
            $this->sendUpdateNotification($pqrs['user_id'], $pqrs, $status, $response);

            // Actualizar métricas de SLA
            $this->updateSLAMetrics($pqrsId, $status);

            // Generar tareas de seguimiento si es necesario
            $followUpTasks = $this->generateFollowUpTasks($pqrs, $status, $additionalData);

            DB::commit();

            Log::info('PQRS actualizada exitosamente', [
                'pqrs_id' => $pqrsId,
                'new_status' => $status,
                'agent_id' => $agentId
            ]);

            return [
                'success' => true,
                'pqrs_id' => $pqrsId,
                'previous_status' => $pqrs['status'],
                'new_status' => $status,
                'updated_at' => Carbon::now()->toISOString(),
                'sla_compliant' => $this->checkSLACompliance($pqrs, $status),
                'follow_up_tasks' => $followUpTasks,
                'next_actions' => $this->getNextActions($status, $pqrs)
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar PQRS', [
                'pqrs_id' => $pqrsId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene las PQRS de un usuario
     *
     * @param int $userId ID del usuario
     * @param array $filters Filtros adicionales
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator
     */
    public function getUserPQRS(
        int $userId,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $cacheKey = "user_pqrs_{$userId}_" . md5(serialize($filters)) . "_{$perPage}";
        
        return Cache::remember($cacheKey, 300, function () use ($userId, $filters, $perPage) {
            $queryFilters = array_merge($filters, ['user_id' => $userId]);
            return $this->reportRepository->getPQRS($queryFilters, $perPage);
        });
    }

    /**
     * Obtiene PQRS pendientes para agentes
     *
     * @param int|null $agentId ID del agente (null para todas)
     * @param array $filters Filtros adicionales
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator
     */
    public function getPendingPQRS(
        ?int $agentId = null,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $filters['status'] = [
            self::PQRS_STATUSES['SUBMITTED'],
            self::PQRS_STATUSES['IN_REVIEW'],
            self::PQRS_STATUSES['ASSIGNED'],
            self::PQRS_STATUSES['IN_PROGRESS']
        ];

        if ($agentId) {
            $filters['assigned_to'] = $agentId;
        }

        // Ordenar por prioridad y SLA
        $filters['order_by'] = [
            'priority' => 'desc',
            'sla_deadline' => 'asc'
        ];

        return $this->reportRepository->getPQRS($filters, $perPage);
    }

    /**
     * Obtiene estadísticas detalladas de PQRS
     *
     * @param array $filters Filtros para las estadísticas
     * @param string $period Período de tiempo
     * @return array Estadísticas de PQRS
     */
    public function getPQRSStatistics(array $filters = [], string $period = 'month'): array
    {
        $cacheKey = "pqrs_stats_" . md5(serialize($filters)) . "_{$period}";
        
        return Cache::remember($cacheKey, 3600, function () use ($filters, $period) {
            $stats = $this->reportRepository->getPQRSStatistics($filters, $period);
            
            return [
                'period' => $period,
                'total_pqrs' => $stats['total_pqrs'] ?? 0,
                'pending_pqrs' => $stats['pending_pqrs'] ?? 0,
                'resolved_pqrs' => $stats['resolved_pqrs'] ?? 0,
                'overdue_pqrs' => $stats['overdue_pqrs'] ?? 0,
                'resolution_rate' => $this->calculateResolutionRate($stats),
                'average_resolution_time' => $stats['avg_resolution_time'] ?? 0,
                'sla_compliance_rate' => $this->calculateSLACompliance($stats),
                'pqrs_by_type' => $stats['pqrs_by_type'] ?? [],
                'pqrs_by_category' => $stats['pqrs_by_category'] ?? [],
                'pqrs_by_priority' => $stats['pqrs_by_priority'] ?? [],
                'agent_performance' => $stats['agent_performance'] ?? [],
                'satisfaction_scores' => $stats['satisfaction_scores'] ?? [],
                'trending_issues' => $this->identifyTrendingIssues($stats),
                'generated_at' => Carbon::now()->toISOString()
            ];
        });
    }

    /**
     * Escala una PQRS a un nivel superior
     *
     * @param int $pqrsId ID de la PQRS
     * @param string $reason Razón del escalamiento
     * @param int|null $escalatedBy ID del agente que escala
     * @return array Resultado del escalamiento
     */
    public function escalatePQRS(int $pqrsId, string $reason, ?int $escalatedBy = null): array
    {
        $pqrs = $this->reportRepository->findPQRSById($pqrsId);
        
        if (!$pqrs) {
            throw new PQRSNotFoundException("PQRS no encontrada: {$pqrsId}");
        }

        $updateData = [
            'status' => self::PQRS_STATUSES['ESCALATED'],
            'priority' => $this->upgradePriority($pqrs['priority']),
            'escalated_at' => Carbon::now(),
            'escalated_by' => $escalatedBy,
            'escalation_reason' => $reason,
            'sla_deadline' => $this->calculateSLADeadline(self::PRIORITY_LEVELS['URGENT']) // Nuevo SLA urgente
        ];

        $this->reportRepository->updatePQRS($pqrsId, $updateData);

        // Notificar a supervisores
        $this->notifyEscalation($pqrsId, $reason, $escalatedBy);

        Log::warning('PQRS escalada', [
            'pqrs_id' => $pqrsId,
            'reason' => $reason,
            'escalated_by' => $escalatedBy
        ]);

        return [
            'success' => true,
            'pqrs_id' => $pqrsId,
            'new_status' => self::PQRS_STATUSES['ESCALATED'],
            'new_priority' => $updateData['priority'],
            'new_sla_deadline' => $updateData['sla_deadline']->toISOString(),
            'escalated_at' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Busca PQRS con criterios avanzados
     *
     * @param array $criteria Criterios de búsqueda
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator
     */
    public function searchPQRS(array $criteria, int $perPage = 20): LengthAwarePaginator
    {
        $validatedCriteria = $this->validateSearchCriteria($criteria);
        return $this->reportRepository->searchPQRS($validatedCriteria, $perPage);
    }

    /**
     * Obtiene el historial de una PQRS específica
     *
     * @param int $pqrsId ID de la PQRS
     * @return array Historial completo de la PQRS
     */
    public function getPQRSHistory(int $pqrsId): array
    {
        $cacheKey = "pqrs_history_{$pqrsId}";
        
        return Cache::remember($cacheKey, 600, function () use ($pqrsId) {
            $pqrs = $this->reportRepository->findPQRSById($pqrsId);
            
            if (!$pqrs) {
                throw new PQRSNotFoundException("PQRS no encontrada: {$pqrsId}");
            }

            return [
                'pqrs_id' => $pqrsId,
                'ticket_number' => $pqrs['ticket_number'],
                'basic_info' => [
                    'type' => $pqrs['type'],
                    'category' => $pqrs['category'],
                    'priority' => $pqrs['priority'],
                    'status' => $pqrs['status']
                ],
                'timeline' => $this->buildPQRSTimeline($pqrs),
                'responses' => $pqrs['responses'] ?? [],
                'attachments' => $pqrs['attachments'] ?? [],
                'sla_info' => [
                    'deadline' => $pqrs['sla_deadline'],
                    'is_overdue' => Carbon::now()->gt(Carbon::parse($pqrs['sla_deadline'])),
                    'time_remaining' => $this->calculateTimeRemaining($pqrs['sla_deadline'])
                ],
                'escalation_history' => $pqrs['escalation_history'] ?? [],
                'satisfaction_rating' => $pqrs['satisfaction_rating'] ?? null,
                'related_items' => $this->findRelatedPQRS($pqrs)
            ];
        });
    }

    /**
     * Valida los datos de entrada para crear PQRS
     */
    private function validatePQRSData(int $userId, string $type, string $category, string $subject, string $description): void
    {
        if (!$this->userRepository->existsById($userId)) {
            throw new PQRSValidationException('Usuario no existe');
        }

        if (!in_array($type, self::PQRS_TYPES)) {
            throw new PQRSValidationException("Tipo de PQRS inválido: {$type}");
        }

        if (!in_array($category, self::PQRS_CATEGORIES)) {
            throw new PQRSValidationException("Categoría inválida: {$category}");
        }

        if (empty(trim($subject)) || strlen($subject) < 10) {
            throw new PQRSValidationException('El asunto debe tener al menos 10 caracteres');
        }

        if (empty(trim($description)) || strlen($description) < 20) {
            throw new PQRSValidationException('La descripción debe tener al menos 20 caracteres');
        }
    }

    /**
     * Determina la prioridad automáticamente
     */
    private function determinePriority(string $type, string $category, string $description, array $metadata): string
    {
        $priority = self::PRIORITY_LEVELS['MEDIUM']; // Por defecto

        // Categorías de alta prioridad
        $highPriorityCategories = [
            self::PQRS_CATEGORIES['ACCOUNT_SUSPENSION'],
            self::PQRS_CATEGORIES['BILLING_ISSUE'],
            self::PQRS_CATEGORIES['SAFETY_CONCERN']
        ];

        // Categorías críticas
        $criticalCategories = [
            self::PQRS_CATEGORIES['HARASSMENT_REPORT'],
            self::PQRS_CATEGORIES['PAYMENT_FAILED']
        ];

        if (in_array($category, $criticalCategories)) {
            $priority = self::PRIORITY_LEVELS['CRITICAL'];
        } elseif (in_array($category, $highPriorityCategories)) {
            $priority = self::PRIORITY_LEVELS['HIGH'];
        } elseif ($type === self::PQRS_TYPES['CLAIM']) {
            $priority = self::PRIORITY_LEVELS['HIGH'];
        }

        // Palabras clave que indican urgencia
        $urgentKeywords = ['urgente', 'emergency', 'inmediato', 'crítico', 'bloqueado', 'no puedo'];
        $descriptionLower = strtolower($description);
        
        foreach ($urgentKeywords as $keyword) {
            if (str_contains($descriptionLower, $keyword)) {
                $priority = $this->upgradePriority($priority);
                break;
            }
        }

        return $priority;
    }

    /**
     * Genera número de ticket único
     */
    private function generateTicketNumber(): string
    {
        $date = Carbon::now()->format('Ymd');
        $sequence = $this->reportRepository->getNextPQRSSequence();
        
        return "PQRS-{$date}-" . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calcula el deadline de SLA
     */
    private function calculateSLADeadline(string $priority): Carbon
    {
        $hours = self::SLA_TIMES[$priority] ?? self::SLA_TIMES['MEDIUM'];
        return Carbon::now()->addHours($hours);
    }

    /**
     * Refina la categoría usando IA
     */
    private function refineCategoryWithAI(string $originalCategory, string $subject, string $description): string
    {
        // Simulación de análisis con IA - en implementación real sería una llamada a ML
        $text = $subject . ' ' . $description;
        $textLower = strtolower($text);

        // Palabras clave para recategorización
        $keywordMap = [
            ['keywords' => ['pago', 'cobro', 'factura', 'billing'], 'category' => self::PQRS_CATEGORIES['BILLING_ISSUE']],
            ['keywords' => ['bug', 'error', 'falla', 'no funciona'], 'category' => self::PQRS_CATEGORIES['APP_BUG']],
            ['keywords' => ['acoso', 'harassment', 'molesta'], 'category' => self::PQRS_CATEGORIES['HARASSMENT_REPORT']],
            ['keywords' => ['login', 'contraseña', 'password'], 'category' => self::PQRS_CATEGORIES['LOGIN_PROBLEMS']],
            ['keywords' => ['reembolso', 'refund', 'devolver'], 'category' => self::PQRS_CATEGORIES['REFUND_REQUEST']]
        ];

        foreach ($keywordMap as $mapping) {
            foreach ($mapping['keywords'] as $keyword) {
                if (str_contains($textLower, $keyword)) {
                    return $mapping['category'];
                }
            }
        }

        return $originalCategory;
    }

    /**
     * Intenta asignación automática
     */
    private function attemptAutoAssignment(int $pqrsId, string $category, string $priority): ?array
    {
        // Lógica de asignación automática basada en disponibilidad y especialización
        $availableAgents = $this->reportRepository->getAvailableAgents($category);
        
        if (empty($availableAgents)) {
            return null;
        }

        // Seleccionar agente con menor carga de trabajo
        $selectedAgent = $availableAgents[0];

        $this->reportRepository->updatePQRS($pqrsId, [
            'status' => self::PQRS_STATUSES['ASSIGNED'],
            'assigned_to' => $selectedAgent['id'],
            'assigned_at' => Carbon::now()
        ]);

        return [
            'agent_id' => $selectedAgent['id'],
            'agent_name' => $selectedAgent['name'],
            'specialization' => $selectedAgent['specialization'],
            'assigned_at' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Valida transición de estado
     */
    private function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            self::PQRS_STATUSES['SUBMITTED'] => [
                self::PQRS_STATUSES['RECEIVED'],
                self::PQRS_STATUSES['IN_REVIEW']
            ],
            self::PQRS_STATUSES['RECEIVED'] => [
                self::PQRS_STATUSES['IN_REVIEW'],
                self::PQRS_STATUSES['ASSIGNED']
            ],
            self::PQRS_STATUSES['IN_REVIEW'] => [
                self::PQRS_STATUSES['ASSIGNED'],
                self::PQRS_STATUSES['ESCALATED'],
                self::PQRS_STATUSES['PENDING_INFO']
            ],
            self::PQRS_STATUSES['ASSIGNED'] => [
                self::PQRS_STATUSES['IN_PROGRESS'],
                self::PQRS_STATUSES['ESCALATED'],
                self::PQRS_STATUSES['PENDING_INFO']
            ],
            self::PQRS_STATUSES['IN_PROGRESS'] => [
                self::PQRS_STATUSES['RESOLVED'],
                self::PQRS_STATUSES['ESCALATED'],
                self::PQRS_STATUSES['PENDING_INFO']
            ],
            self::PQRS_STATUSES['PENDING_INFO'] => [
                self::PQRS_STATUSES['IN_PROGRESS'],
                self::PQRS_STATUSES['CLOSED']
            ],
            self::PQRS_STATUSES['ESCALATED'] => [
                self::PQRS_STATUSES['IN_PROGRESS'],
                self::PQRS_STATUSES['RESOLVED']
            ],
            self::PQRS_STATUSES['RESOLVED'] => [
                self::PQRS_STATUSES['CLOSED'],
                self::PQRS_STATUSES['REOPENED']
            ],
            self::PQRS_STATUSES['CLOSED'] => [
                self::PQRS_STATUSES['REOPENED']
            ]
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    /**
     * Mejora el nivel de prioridad
     */
    private function upgradePriority(string $currentPriority): string
    {
        return match($currentPriority) {
            self::PRIORITY_LEVELS['LOW'] => self::PRIORITY_LEVELS['MEDIUM'],
            self::PRIORITY_LEVELS['MEDIUM'] => self::PRIORITY_LEVELS['HIGH'],
            self::PRIORITY_LEVELS['HIGH'] => self::PRIORITY_LEVELS['URGENT'],
            self::PRIORITY_LEVELS['URGENT'] => self::PRIORITY_LEVELS['CRITICAL'],
            default => $currentPriority
        };
    }
}