<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Services;

use App\Domain\Moderation\Events\UserReported;
use App\Domain\Moderation\Events\ContentFlagged;
use App\Domain\Moderation\Repositories\ReportRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Chat\Repositories\MessageRepositoryInterface;
use App\Exceptions\ReportNotFoundException;
use App\Exceptions\ReportValidationException;
use App\Exceptions\DuplicateReportException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\InvalidReportStatusException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Servicio integral para la gestión de reportes de usuarios en ForeverUsInLove
 * 
 * Este servicio maneja la creación, procesamiento, resolución y seguimiento de reportes
 * relacionados con comportamiento inapropiado, contenido ofensivo, perfiles falsos,
 * acoso, spam y otras violaciones de las normas de la comunidad.
 * 
 * Características principales:
 * - Sistema de categorización automática de reportes
 * - Detección de patrones de comportamiento sospechoso
 * - Escalamiento automático de casos críticos
 * - Moderación colaborativa con moderadores humanos
 * - Analytics de seguridad y tendencias de reportes
 * - Sistema de apelaciones y revisiones
 * - Integración con sistemas de machine learning para detección automática
 * - Notificaciones en tiempo real para moderadores
 * 
 * @package App\Domain\Moderation\Services
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class ReportService
{
    /**
     * Tipos de reportes disponibles en la plataforma
     */
    public const REPORT_TYPES = [
        'INAPPROPRIATE_BEHAVIOR' => 'inappropriate_behavior',
        'FAKE_PROFILE' => 'fake_profile', 
        'HARASSMENT' => 'harassment',
        'SPAM' => 'spam',
        'OFFENSIVE_CONTENT' => 'offensive_content',
        'INAPPROPRIATE_PHOTOS' => 'inappropriate_photos',
        'SCAM_FRAUD' => 'scam_fraud',
        'UNDERAGE_USER' => 'underage_user',
        'VIOLENCE_THREATS' => 'violence_threats',
        'HATE_SPEECH' => 'hate_speech',
        'CATFISH' => 'catfish',
        'SOLICITATION' => 'solicitation'
    ];

    /**
     * Estados posibles de un reporte
     */
    public const REPORT_STATUSES = [
        'PENDING' => 'pending',
        'UNDER_REVIEW' => 'under_review',
        'INVESTIGATING' => 'investigating',
        'RESOLVED' => 'resolved',
        'DISMISSED' => 'dismissed',
        'ESCALATED' => 'escalated',
        'APPEALED' => 'appealed',
        'CLOSED' => 'closed'
    ];

    /**
     * Niveles de prioridad de reportes
     */
    public const PRIORITY_LEVELS = [
        'LOW' => 'low',
        'MEDIUM' => 'medium', 
        'HIGH' => 'high',
        'CRITICAL' => 'critical',
        'EMERGENCY' => 'emergency'
    ];

    /**
     * Acciones que pueden tomarse sobre un reporte
     */
    public const MODERATION_ACTIONS = [
        'WARNING_ISSUED' => 'warning_issued',
        'CONTENT_REMOVED' => 'content_removed',
        'PROFILE_SUSPENDED' => 'profile_suspended',
        'ACCOUNT_BANNED' => 'account_banned',
        'NO_ACTION_NEEDED' => 'no_action_needed',
        'REQUIRE_VERIFICATION' => 'require_verification',
        'SHADOWBAN_APPLIED' => 'shadowban_applied',
        'FEATURES_RESTRICTED' => 'features_restricted'
    ];

    /**
     * Constructor del servicio de reportes
     */
    public function __construct(
        private readonly ReportRepositoryInterface $reportRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly MessageRepositoryInterface $messageRepository
    ) {}

    /**
     * Crea un nuevo reporte de usuario o contenido
     *
     * @param int $reporterId ID del usuario que hace el reporte
     * @param int $reportedId ID del usuario reportado
     * @param string $type Tipo de reporte
     * @param string $description Descripción detallada del reporte
     * @param array $evidence Evidencia adicional (screenshots, mensajes, etc.)
     * @param array $metadata Metadatos adicionales
     * @return array Información del reporte creado
     * @throws ReportValidationException
     * @throws DuplicateReportException
     */
    public function createReport(
        int $reporterId,
        int $reportedId,
        string $type,
        string $description,
        array $evidence = [],
        array $metadata = []
    ): array {
        try {
            Log::info('Creando nuevo reporte', [
                'reporter_id' => $reporterId,
                'reported_id' => $reportedId,
                'type' => $type
            ]);

            // Validar que el tipo de reporte sea válido
            if (!in_array($type, self::REPORT_TYPES)) {
                throw new ReportValidationException("Tipo de reporte inválido: {$type}");
            }

            // Validar que los usuarios existan
            if (!$this->userRepository->existsById($reporterId) || !$this->userRepository->existsById($reportedId)) {
                throw new ReportValidationException('Uno o ambos usuarios no existen');
            }

            // Validar que no sea un auto-reporte
            if ($reporterId === $reportedId) {
                throw new ReportValidationException('No puedes reportarte a ti mismo');
            }

            // Verificar duplicados recientes (últimas 24 horas)
            if ($this->hasDuplicateReport($reporterId, $reportedId, $type)) {
                throw new DuplicateReportException('Ya has reportado a este usuario por esta razón recientemente');
            }

            // Determinar prioridad automáticamente basada en el tipo y historial
            $priority = $this->calculateReportPriority($type, $reportedId, $evidence);

            // Preparar datos del reporte
            $reportData = [
                'reporter_id' => $reporterId,
                'reported_id' => $reportedId,
                'type' => $type,
                'description' => $description,
                'priority' => $priority,
                'status' => self::REPORT_STATUSES['PENDING'],
                'evidence' => $evidence,
                'metadata' => array_merge($metadata, [
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => Carbon::now()->toISOString(),
                    'auto_priority' => true
                ])
            ];

            // Crear el reporte
            $report = $this->reportRepository->create($reportData);

            // Análisis automático del contenido reportado
            $this->performAutomaticAnalysis($report['id'], $type, $reportedId);

            // Disparar evento de usuario reportado
            Event::dispatch(new UserReported(
                $report['id'],
                $reporterId,
                $reportedId,
                $type,
                $priority
            ));

            // Escalamiento automático para casos críticos
            if ($priority === self::PRIORITY_LEVELS['CRITICAL'] || $priority === self::PRIORITY_LEVELS['EMERGENCY']) {
                $this->escalateReport($report['id'], 'Escalamiento automático por prioridad crítica');
            }

            // Incrementar estadísticas del usuario reportado
            $this->updateUserReportStats($reportedId);

            // Limpiar cache relevante
            $this->clearReportCache($reportedId);

            Log::info('Reporte creado exitosamente', ['report_id' => $report['id']]);

            return [
                'success' => true,
                'report_id' => $report['id'],
                'status' => $report['status'],
                'priority' => $priority,
                'reference_number' => $this->generateReferenceNumber($report['id']),
                'estimated_review_time' => $this->getEstimatedReviewTime($priority),
                'next_steps' => $this->getReportNextSteps($priority, $type)
            ];

        } catch (Exception $e) {
            Log::error('Error al crear reporte', [
                'error' => $e->getMessage(),
                'reporter_id' => $reporterId,
                'reported_id' => $reportedId
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene los reportes de un usuario específico (como reportador o reportado)
     *
     * @param int $userId ID del usuario
     * @param string $role Rol ('reporter' o 'reported')
     * @param array $filters Filtros adicionales
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator
     */
    public function getUserReports(
        int $userId,
        string $role = 'reporter',
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $cacheKey = "user_reports_{$userId}_{$role}_" . md5(serialize($filters)) . "_{$perPage}";
        
        return Cache::remember($cacheKey, 300, function () use ($userId, $role, $filters, $perPage) {
            $queryFilters = array_merge($filters, [
                $role . '_id' => $userId
            ]);

            return $this->reportRepository->getReports($queryFilters, $perPage);
        });
    }

    /**
     * Obtiene reportes pendientes de moderación
     *
     * @param array $filters Filtros de búsqueda
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator
     */
    public function getPendingReports(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $filters['status'] = [
            self::REPORT_STATUSES['PENDING'],
            self::REPORT_STATUSES['UNDER_REVIEW']
        ];

        // Ordenar por prioridad y fecha
        $filters['order_by'] = [
            'priority' => 'desc',
            'created_at' => 'asc'
        ];

        return $this->reportRepository->getReports($filters, $perPage);
    }

    /**
     * Procesa un reporte (revisión de moderador)
     *
     * @param int $reportId ID del reporte
     * @param int $moderatorId ID del moderador
     * @param string $action Acción a tomar
     * @param string $notes Notas del moderador
     * @param array $additionalData Datos adicionales
     * @return array Resultado del procesamiento
     * @throws ReportNotFoundException
     * @throws InvalidReportStatusException
     */
    public function processReport(
        int $reportId,
        int $moderatorId,
        string $action,
        string $notes = '',
        array $additionalData = []
    ): array {
        try {
            $report = $this->reportRepository->findById($reportId);
            
            if (!$report) {
                throw new ReportNotFoundException("Reporte no encontrado: {$reportId}");
            }

            // Validar que el reporte pueda ser procesado
            if (!in_array($report['status'], [
                self::REPORT_STATUSES['PENDING'],
                self::REPORT_STATUSES['UNDER_REVIEW']
            ])) {
                throw new InvalidReportStatusException(
                    "El reporte no puede ser procesado en su estado actual: {$report['status']}"
                );
            }

            // Validar acción
            if (!in_array($action, self::MODERATION_ACTIONS)) {
                throw new InvalidArgumentException("Acción de moderación inválida: {$action}");
            }

            DB::beginTransaction();

            // Actualizar estado del reporte
            $updateData = [
                'status' => self::REPORT_STATUSES['UNDER_REVIEW'],
                'moderator_id' => $moderatorId,
                'processed_at' => Carbon::now(),
                'action_taken' => $action,
                'moderator_notes' => $notes,
                'resolution_data' => $additionalData
            ];

            $this->reportRepository->update($reportId, $updateData);

            // Aplicar la acción correspondiente al usuario reportado
            $actionResult = $this->applyModerationAction(
                $report['reported_id'],
                $action,
                $reportId,
                $notes,
                $additionalData
            );

            // Actualizar estadísticas de moderación
            $this->updateModerationStats($moderatorId, $action, $report['type']);

            // Notificar a las partes involucradas
            $this->sendReportResolutionNotifications($report, $action, $actionResult);

            DB::commit();

            // Limpiar caches
            $this->clearReportCache($report['reported_id']);
            Cache::forget("report_details_{$reportId}");

            Log::info('Reporte procesado exitosamente', [
                'report_id' => $reportId,
                'moderator_id' => $moderatorId,
                'action' => $action
            ]);

            return [
                'success' => true,
                'report_id' => $reportId,
                'action_taken' => $action,
                'action_result' => $actionResult,
                'status' => self::REPORT_STATUSES['RESOLVED'],
                'processed_at' => Carbon::now()->toISOString()
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al procesar reporte', [
                'report_id' => $reportId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Escala un reporte a un nivel superior de moderación
     *
     * @param int $reportId ID del reporte
     * @param string $reason Razón del escalamiento
     * @param int|null $escalatedBy ID del moderador que escala
     * @return array Resultado del escalamiento
     */
    public function escalateReport(int $reportId, string $reason, ?int $escalatedBy = null): array
    {
        $report = $this->reportRepository->findById($reportId);
        
        if (!$report) {
            throw new ReportNotFoundException("Reporte no encontrado: {$reportId}");
        }

        $updateData = [
            'status' => self::REPORT_STATUSES['ESCALATED'],
            'priority' => self::PRIORITY_LEVELS['CRITICAL'],
            'escalated_at' => Carbon::now(),
            'escalated_by' => $escalatedBy,
            'escalation_reason' => $reason
        ];

        $this->reportRepository->update($reportId, $updateData);

        // Notificar a supervisores y administradores
        $this->notifyEscalation($reportId, $reason, $escalatedBy);

        Log::warning('Reporte escalado', [
            'report_id' => $reportId,
            'reason' => $reason,
            'escalated_by' => $escalatedBy
        ]);

        return [
            'success' => true,
            'report_id' => $reportId,
            'new_status' => self::REPORT_STATUSES['ESCALATED'],
            'new_priority' => self::PRIORITY_LEVELS['CRITICAL'],
            'escalated_at' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Obtiene estadísticas detalladas de reportes
     *
     * @param array $filters Filtros para las estadísticas
     * @param string $period Período de tiempo ('day', 'week', 'month', 'year')
     * @return array Estadísticas de reportes
     */
    public function getReportStatistics(array $filters = [], string $period = 'month'): array
    {
        $cacheKey = "report_stats_" . md5(serialize($filters)) . "_{$period}";
        
        return Cache::remember($cacheKey, 3600, function () use ($filters, $period) {
            $stats = $this->reportRepository->getStatistics($filters, $period);
            
            return [
                'period' => $period,
                'total_reports' => $stats['total_reports'] ?? 0,
                'pending_reports' => $stats['pending_reports'] ?? 0,
                'resolved_reports' => $stats['resolved_reports'] ?? 0,
                'escalated_reports' => $stats['escalated_reports'] ?? 0,
                'resolution_rate' => $this->calculateResolutionRate($stats),
                'average_resolution_time' => $stats['avg_resolution_time'] ?? 0,
                'reports_by_type' => $stats['reports_by_type'] ?? [],
                'reports_by_priority' => $stats['reports_by_priority'] ?? [],
                'top_reported_users' => $stats['top_reported_users'] ?? [],
                'moderator_performance' => $stats['moderator_performance'] ?? [],
                'trend_analysis' => $this->analyzeTrends($stats, $period),
                'generated_at' => Carbon::now()->toISOString()
            ];
        });
    }

    /**
     * Obtiene el historial de un usuario reportado
     *
     * @param int $userId ID del usuario
     * @return array Historial de reportes del usuario
     */
    public function getUserReportHistory(int $userId): array
    {
        $cacheKey = "user_report_history_{$userId}";
        
        return Cache::remember($cacheKey, 600, function () use ($userId) {
            $reports = $this->reportRepository->getUserReportHistory($userId);
            $profile = $this->profileRepository->findByUserId($userId);
            
            return [
                'user_id' => $userId,
                'profile_info' => $profile ? [
                    'display_name' => $profile['display_name'],
                    'age' => $profile['age'],
                    'location' => $profile['location']
                ] : null,
                'total_reports_received' => count($reports),
                'reports_by_status' => $this->groupReportsByStatus($reports),
                'reports_by_type' => $this->groupReportsByType($reports),
                'recent_reports' => array_slice($reports, 0, 10),
                'risk_score' => $this->calculateUserRiskScore($reports),
                'account_status' => $this->getUserAccountStatus($userId),
                'last_report_date' => $reports[0]['created_at'] ?? null,
                'patterns_detected' => $this->detectReportPatterns($reports)
            ];
        });
    }

    /**
     * Busca reportes con criterios avanzados
     *
     * @param array $criteria Criterios de búsqueda
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator
     */
    public function searchReports(array $criteria, int $perPage = 20): LengthAwarePaginator
    {
        // Validar criterios de búsqueda
        $validatedCriteria = $this->validateSearchCriteria($criteria);
        
        return $this->reportRepository->search($validatedCriteria, $perPage);
    }

    /**
     * Obtiene reportes similares para detectar patrones
     *
     * @param int $reportId ID del reporte base
     * @param int $limit Límite de resultados
     * @return array Reportes similares
     */
    public function getSimilarReports(int $reportId, int $limit = 10): array
    {
        $report = $this->reportRepository->findById($reportId);
        
        if (!$report) {
            throw new ReportNotFoundException("Reporte no encontrado: {$reportId}");
        }

        $similarReports = $this->reportRepository->findSimilarReports(
            $report['reported_id'],
            $report['type'],
            $report['created_at'],
            $limit
        );

        return [
            'base_report_id' => $reportId,
            'similar_reports' => $similarReports,
            'pattern_score' => $this->calculatePatternScore($report, $similarReports),
            'risk_indicators' => $this->identifyRiskIndicators($report, $similarReports)
        ];
    }

    /**
     * Calcula la prioridad automática de un reporte
     */
    private function calculateReportPriority(string $type, int $reportedId, array $evidence): string
    {
        $priority = self::PRIORITY_LEVELS['MEDIUM']; // Por defecto
        
        // Tipos de alta prioridad
        $highPriorityTypes = [
            self::REPORT_TYPES['VIOLENCE_THREATS'],
            self::REPORT_TYPES['HARASSMENT'],
            self::REPORT_TYPES['UNDERAGE_USER']
        ];
        
        // Tipos críticos
        $criticalTypes = [
            self::REPORT_TYPES['SCAM_FRAUD'],
            self::REPORT_TYPES['HATE_SPEECH']
        ];

        if (in_array($type, $criticalTypes)) {
            $priority = self::PRIORITY_LEVELS['CRITICAL'];
        } elseif (in_array($type, $highPriorityTypes)) {
            $priority = self::PRIORITY_LEVELS['HIGH'];
        }

        // Ajustar por historial del usuario
        $userReportCount = $this->reportRepository->countUserReports($reportedId);
        if ($userReportCount >= 5) {
            $priority = self::PRIORITY_LEVELS['CRITICAL'];
        } elseif ($userReportCount >= 3) {
            $priority = self::PRIORITY_LEVELS['HIGH'];
        }

        // Ajustar por evidencia
        if (!empty($evidence['screenshots']) && count($evidence['screenshots']) >= 3) {
            $priority = $this->upgradePriority($priority);
        }

        return $priority;
    }

    /**
     * Verifica si existe un reporte duplicado reciente
     */
    private function hasDuplicateReport(int $reporterId, int $reportedId, string $type): bool
    {
        return $this->reportRepository->hasDuplicateReport(
            $reporterId,
            $reportedId, 
            $type,
            Carbon::now()->subHours(24)
        );
    }

    /**
     * Realiza análisis automático del contenido reportado
     */
    private function performAutomaticAnalysis(int $reportId, string $type, int $reportedId): void
    {
        // Análisis del perfil del usuario reportado
        $profile = $this->profileRepository->findByUserId($reportedId);
        
        if ($profile) {
            $analysisData = [
                'profile_completeness' => $this->calculateProfileCompleteness($profile),
                'photo_analysis' => $this->analyzeProfilePhotos($profile),
                'bio_analysis' => $this->analyzeBioContent($profile),
                'suspicious_indicators' => $this->detectSuspiciousIndicators($profile)
            ];
            
            $this->reportRepository->updateAnalysis($reportId, $analysisData);
        }

        // Si es un reporte de contenido, analizar mensajes recientes
        if ($type === self::REPORT_TYPES['OFFENSIVE_CONTENT']) {
            $this->analyzeUserMessages($reportId, $reportedId);
        }
    }

    /**
     * Aplica una acción de moderación específica
     */
    private function applyModerationAction(
        int $userId,
        string $action,
        int $reportId,
        string $notes = '',
        array $additionalData = []
    ): array {
        switch ($action) {
            case self::MODERATION_ACTIONS['WARNING_ISSUED']:
                return $this->issueWarning($userId, $reportId, $notes);
                
            case self::MODERATION_ACTIONS['PROFILE_SUSPENDED']:
                return $this->suspendProfile($userId, $reportId, $additionalData['suspension_days'] ?? 7);
                
            case self::MODERATION_ACTIONS['ACCOUNT_BANNED']:
                return $this->banAccount($userId, $reportId, $notes);
                
            case self::MODERATION_ACTIONS['CONTENT_REMOVED']:
                return $this->removeContent($userId, $additionalData['content_ids'] ?? []);
                
            case self::MODERATION_ACTIONS['REQUIRE_VERIFICATION']:
                return $this->requireVerification($userId, $reportId);
                
            default:
                return ['action' => $action, 'applied' => true, 'details' => 'Acción registrada'];
        }
    }

    /**
     * Genera un número de referencia único para el reporte
     */
    private function generateReferenceNumber(int $reportId): string
    {
        return 'REP-' . str_pad((string)$reportId, 6, '0', STR_PAD_LEFT) . '-' . 
               strtoupper(substr(md5((string)$reportId . time()), 0, 4));
    }

    /**
     * Obtiene el tiempo estimado de revisión basado en la prioridad
     */
    private function getEstimatedReviewTime(string $priority): string
    {
        return match($priority) {
            self::PRIORITY_LEVELS['EMERGENCY'] => '15 minutos',
            self::PRIORITY_LEVELS['CRITICAL'] => '1 hora',
            self::PRIORITY_LEVELS['HIGH'] => '4 horas',
            self::PRIORITY_LEVELS['MEDIUM'] => '24 horas',
            self::PRIORITY_LEVELS['LOW'] => '72 horas',
            default => '24 horas'
        };
    }

    /**
     * Actualiza las estadísticas de reportes del usuario
     */
    private function updateUserReportStats(int $userId): void
    {
        $stats = Cache::get("user_report_stats_{$userId}", [
            'total_reports' => 0,
            'last_report' => null
        ]);
        
        $stats['total_reports']++;
        $stats['last_report'] = Carbon::now()->toISOString();
        
        Cache::put("user_report_stats_{$userId}", $stats, 86400);
    }

    /**
     * Limpia el cache relacionado con reportes
     */
    private function clearReportCache(int $userId): void
    {
        $patterns = [
            "user_reports_{$userId}_*",
            "user_report_history_{$userId}",
            "user_report_stats_{$userId}",
            "report_stats_*"
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Calcula la tasa de resolución de reportes
     */
    private function calculateResolutionRate(array $stats): float
    {
        $total = $stats['total_reports'] ?? 0;
        $resolved = $stats['resolved_reports'] ?? 0;
        
        return $total > 0 ? round(($resolved / $total) * 100, 2) : 0.0;
    }

    /**
     * Analiza tendencias en los reportes
     */
    private function analyzeTrends(array $stats, string $period): array
    {
        // Análisis básico de tendencias
        return [
            'trending_types' => $this->identifyTrendingReportTypes($stats),
            'peak_hours' => $this->identifyPeakReportingHours($stats),
            'seasonal_patterns' => $this->identifySeasonalPatterns($stats, $period),
            'risk_indicators' => $this->identifySystemRiskIndicators($stats)
        ];
    }

    /**
     * Obtiene los próximos pasos para un reporte
     */
    private function getReportNextSteps(string $priority, string $type): array
    {
        $steps = [
            'Revisión inicial por moderador',
            'Investigación del caso',
            'Toma de decisión'
        ];

        if ($priority === self::PRIORITY_LEVELS['CRITICAL']) {
            array_unshift($steps, 'Revisión inmediata por supervisor');
        }

        if (in_array($type, [self::REPORT_TYPES['VIOLENCE_THREATS'], self::REPORT_TYPES['UNDERAGE_USER']])) {
            array_unshift($steps, 'Notificación a autoridades competentes');
        }

        return $steps;
    }

    /**
     * Valida los criterios de búsqueda
     */
    private function validateSearchCriteria(array $criteria): array
    {
        $allowed = [
            'type', 'status', 'priority', 'reporter_id', 'reported_id',
            'moderator_id', 'created_from', 'created_to', 'keyword'
        ];
        
        return array_intersect_key($criteria, array_flip($allowed));
    }

    /**
     * Mejora el nivel de prioridad
     */
    private function upgradePriority(string $currentPriority): string
    {
        return match($currentPriority) {
            self::PRIORITY_LEVELS['LOW'] => self::PRIORITY_LEVELS['MEDIUM'],
            self::PRIORITY_LEVELS['MEDIUM'] => self::PRIORITY_LEVELS['HIGH'],
            self::PRIORITY_LEVELS['HIGH'] => self::PRIORITY_LEVELS['CRITICAL'],
            default => $currentPriority
        };
    }
}