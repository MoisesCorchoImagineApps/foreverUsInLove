<?php

declare(strict_types=1);

namespace App\Domain\Moderation\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Interfaz del repositorio para operaciones de moderación en ForeverUsInLove
 * 
 * Esta interfaz define todos los contratos para el acceso a datos relacionados
 * con la moderación, incluyendo reportes, bloqueos, PQRS, moderación de contenido
 * y análisis de patrones de comportamiento.
 * 
 * Operaciones cubiertas:
 * - Gestión completa de reportes de usuarios
 * - Sistema de bloqueos entre usuarios
 * - PQRS (Peticiones, Quejas, Reclamos y Sugerencias)
 * - Moderación automática de contenido
 * - Analytics y estadísticas de moderación
 * - Gestión de moderadores y asignaciones
 * - Patrones de comportamiento y detección de fraude
 * - Configuración de reglas de moderación
 * - Auditoría y trazabilidad
 * 
 * @package App\Domain\Moderation\Repositories
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
interface ReportRepositoryInterface
{
    // ======================================================
    // OPERACIONES DE REPORTES
    // ======================================================

    /**
     * Crea un nuevo reporte de usuario
     *
     * @param array $reportData Datos del reporte a crear
     * @return array Reporte creado con su ID asignado
     */
    public function create(array $reportData): array;

    /**
     * Busca un reporte por su ID
     *
     * @param int $reportId ID del reporte
     * @return array|null Datos del reporte o null si no existe
     */
    public function findById(int $reportId): ?array;

    /**
     * Actualiza un reporte existente
     *
     * @param int $reportId ID del reporte a actualizar
     * @param array $updateData Datos a actualizar
     * @return bool True si la actualización fue exitosa
     */
    public function update(int $reportId, array $updateData): bool;

    /**
     * Obtiene reportes con filtros y paginación
     *
     * @param array $filters Filtros de búsqueda
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator Reportes paginados
     */
    public function getReports(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Verifica si existe un reporte duplicado reciente
     *
     * @param int $reporterId ID del usuario reportador
     * @param int $reportedId ID del usuario reportado
     * @param string $type Tipo de reporte
     * @param Carbon $since Fecha desde la cual verificar
     * @return bool True si existe un duplicado
     */
    public function hasDuplicateReport(int $reporterId, int $reportedId, string $type, Carbon $since): bool;

    /**
     * Cuenta el número de reportes de un usuario
     *
     * @param int $userId ID del usuario
     * @param string $role Rol del usuario ('reporter' o 'reported')
     * @return int Número de reportes
     */
    public function countUserReports(int $userId, string $role = 'reported'): int;

    /**
     * Obtiene el historial de reportes de un usuario
     *
     * @param int $userId ID del usuario
     * @param int $limit Límite de resultados
     * @return array Historial de reportes
     */
    public function getUserReportHistory(int $userId, int $limit = 50): array;

    /**
     * Busca reportes similares para detección de patrones
     *
     * @param int $reportedId ID del usuario reportado
     * @param string $type Tipo de reporte
     * @param Carbon $timeframe Marco temporal
     * @param int $limit Límite de resultados
     * @return array Reportes similares
     */
    public function findSimilarReports(int $reportedId, string $type, Carbon $timeframe, int $limit = 10): array;

    /**
     * Obtiene reportes recientes de un usuario hacia otro
     *
     * @param int $reporterId ID del reportador
     * @param int $reportedId ID del reportado
     * @param Carbon $since Fecha desde la cual buscar
     * @return array Reportes recientes
     */
    public function getRecentReports(int $reporterId, int $reportedId, Carbon $since): array;

    /**
     * Actualiza el análisis automático de un reporte
     *
     * @param int $reportId ID del reporte
     * @param array $analysisData Datos del análisis
     * @return bool True si la actualización fue exitosa
     */
    public function updateAnalysis(int $reportId, array $analysisData): bool;

    // ======================================================
    // OPERACIONES DE BLOQUEOS
    // ======================================================

    /**
     * Crea un nuevo bloqueo entre usuarios
     *
     * @param array $blockData Datos del bloqueo
     * @return array Bloqueo creado
     */
    public function createBlock(array $blockData): array;

    /**
     * Actualiza un bloqueo existente
     *
     * @param int $blockId ID del bloqueo
     * @param array $updateData Datos a actualizar
     * @return bool True si la actualización fue exitosa
     */
    public function updateBlock(int $blockId, array $updateData): bool;

    /**
     * Verifica si un usuario está bloqueado por otro
     *
     * @param int $blockerId ID del usuario que bloquea
     * @param int $blockedId ID del usuario bloqueado
     * @param string|null $type Tipo específico de bloqueo
     * @return bool True si está bloqueado
     */
    public function isUserBlocked(int $blockerId, int $blockedId, ?string $type = null): bool;

    /**
     * Obtiene un bloqueo activo específico
     *
     * @param int $blockerId ID del usuario que bloquea
     * @param int $blockedId ID del usuario bloqueado
     * @return array|null Datos del bloqueo o null
     */
    public function getActiveBlock(int $blockerId, int $blockedId): ?array;

    /**
     * Obtiene lista de bloqueos con filtros
     *
     * @param array $filters Filtros de búsqueda
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator Bloqueos paginados
     */
    public function getBlocks(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Obtiene lista simple de bloqueos con filtros
     *
     * @param array $filters Filtros de búsqueda
     * @return array Lista de bloqueos
     */
    public function getBlocksList(array $filters = []): array;

    /**
     * Obtiene bloqueos temporales expirados
     *
     * @return array Lista de bloqueos expirados
     */
    public function getExpiredBlocks(): array;

    /**
     * Obtiene estadísticas de bloqueos de un usuario
     *
     * @param int $userId ID del usuario
     * @return array Estadísticas de bloqueos
     */
    public function getUserBlockStatistics(int $userId): array;

    /**
     * Obtiene usuarios frecuentemente reportados por un usuario
     *
     * @param int $userId ID del usuario
     * @param int $limit Límite de resultados
     * @return array Lista de usuarios reportados
     */
    public function getFrequentlyReportedUsers(int $userId, int $limit = 10): array;

    // ======================================================
    // OPERACIONES DE PQRS
    // ======================================================

    /**
     * Crea una nueva PQRS
     *
     * @param array $pqrsData Datos de la PQRS
     * @return array PQRS creada
     */
    public function createPQRS(array $pqrsData): array;

    /**
     * Busca una PQRS por su ID
     *
     * @param int $pqrsId ID de la PQRS
     * @return array|null Datos de la PQRS o null
     */
    public function findPQRSById(int $pqrsId): ?array;

    /**
     * Actualiza una PQRS existente
     *
     * @param int $pqrsId ID de la PQRS
     * @param array $updateData Datos a actualizar
     * @return bool True si la actualización fue exitosa
     */
    public function updatePQRS(int $pqrsId, array $updateData): bool;

    /**
     * Obtiene PQRS con filtros y paginación
     *
     * @param array $filters Filtros de búsqueda
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator PQRS paginadas
     */
    public function getPQRS(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Busca PQRS con criterios avanzados
     *
     * @param array $criteria Criterios de búsqueda
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator PQRS encontradas
     */
    public function searchPQRS(array $criteria, int $perPage = 20): LengthAwarePaginator;

    /**
     * Obtiene el siguiente número de secuencia para PQRS
     *
     * @return int Próximo número de secuencia
     */
    public function getNextPQRSSequence(): int;

    /**
     * Obtiene agentes disponibles para una categoría
     *
     * @param string $category Categoría de PQRS
     * @return array Lista de agentes disponibles
     */
    public function getAvailableAgents(string $category): array;

    // ======================================================
    // MODERACIÓN DE CONTENIDO
    // ======================================================

    /**
     * Almacena resultado de moderación de contenido
     *
     * @param array $moderationData Datos de la moderación
     * @return array Resultado almacenado
     */
    public function storeModerationResult(array $moderationData): array;

    /**
     * Almacena feedback de moderación humana
     *
     * @param array $feedbackData Datos del feedback
     * @return bool True si se almacenó correctamente
     */
    public function storeModerationFeedback(array $feedbackData): bool;

    /**
     * Obtiene palabras de profanidad con sus pesos
     *
     * @return array Lista de palabras con pesos
     */
    public function getProfanityWords(): array;

    /**
     * Obtiene patrones de discurso de odio
     *
     * @return array Patrones con sus pesos
     */
    public function getHateSpeechPatterns(): array;

    /**
     * Actualiza reglas de moderación
     *
     * @param string $ruleType Tipo de regla
     * @param array $rules Nuevas reglas
     * @return bool True si se actualizaron correctamente
     */
    public function updateModerationRules(string $ruleType, array $rules): bool;

    /**
     * Obtiene reglas de moderación actuales
     *
     * @param string $ruleType Tipo de regla
     * @return array Reglas actuales
     */
    public function getModerationRules(string $ruleType): array;

    // ======================================================
    // ESTADÍSTICAS Y ANALYTICS
    // ======================================================

    /**
     * Obtiene estadísticas generales de reportes
     *
     * @param array $filters Filtros aplicables
     * @param string $period Período de análisis
     * @return array Estadísticas de reportes
     */
    public function getStatistics(array $filters = [], string $period = 'month'): array;

    /**
     * Obtiene estadísticas de PQRS
     *
     * @param array $filters Filtros aplicables
     * @param string $period Período de análisis
     * @return array Estadísticas de PQRS
     */
    public function getPQRSStatistics(array $filters = [], string $period = 'month'): array;

    /**
     * Obtiene estadísticas de moderación de contenido
     *
     * @param array $filters Filtros aplicables
     * @param string $period Período de análisis
     * @return array Estadísticas de moderación
     */
    public function getModerationStatistics(array $filters = [], string $period = 'week'): array;

    /**
     * Obtiene métricas de rendimiento de moderadores
     *
     * @param int|null $moderatorId ID del moderador específico
     * @param string $period Período de análisis
     * @return array Métricas de rendimiento
     */
    public function getModeratorPerformanceMetrics(?int $moderatorId = null, string $period = 'month'): array;

    /**
     * Obtiene tendencias de violaciones por categoría
     *
     * @param string $period Período de análisis
     * @return array Tendencias de violaciones
     */
    public function getViolationTrends(string $period = 'month'): array;

    // ======================================================
    // GESTIÓN DE MODERADORES
    // ======================================================

    /**
     * Crea un nuevo moderador
     *
     * @param array $moderatorData Datos del moderador
     * @return array Moderador creado
     */
    public function createModerator(array $moderatorData): array;

    /**
     * Actualiza información de un moderador
     *
     * @param int $moderatorId ID del moderador
     * @param array $updateData Datos a actualizar
     * @return bool True si la actualización fue exitosa
     */
    public function updateModerator(int $moderatorId, array $updateData): bool;

    /**
     * Obtiene lista de moderadores activos
     *
     * @param array $filters Filtros de búsqueda
     * @return Collection Lista de moderadores
     */
    public function getActiveModerators(array $filters = []): Collection;

    /**
     * Asigna un reporte a un moderador
     *
     * @param int $reportId ID del reporte
     * @param int $moderatorId ID del moderador
     * @return bool True si la asignación fue exitosa
     */
    public function assignReportToModerator(int $reportId, int $moderatorId): bool;

    /**
     * Obtiene carga de trabajo de un moderador
     *
     * @param int $moderatorId ID del moderador
     * @return array Carga de trabajo actual
     */
    public function getModeratorWorkload(int $moderatorId): array;

    // ======================================================
    // DETECCIÓN DE PATRONES Y ANÁLISIS
    // ======================================================

    /**
     * Detecta patrones de comportamiento sospechoso
     *
     * @param int $userId ID del usuario
     * @param string $timeframe Marco temporal
     * @return array Patrones detectados
     */
    public function detectBehaviorPatterns(int $userId, string $timeframe = '7d'): array;

    /**
     * Obtiene historial de comportamiento de un usuario
     *
     * @param int $userId ID del usuario
     * @param string $timeframe Marco temporal
     * @return array Historial de comportamiento
     */
    public function getUserBehaviorHistory(int $userId, string $timeframe): array;

    /**
     * Detecta actividad coordinada entre usuarios
     *
     * @param array $userIds IDs de usuarios a analizar
     * @param string $timeframe Marco temporal
     * @return array Indicadores de actividad coordinada
     */
    public function detectCoordinatedActivity(array $userIds, string $timeframe = '24h'): array;

    /**
     * Obtiene análisis de red de un usuario
     *
     * @param int $userId ID del usuario
     * @return array Análisis de red social
     */
    public function getUserNetworkAnalysis(int $userId): array;

    /**
     * Detecta anomalías en patrones de uso
     *
     * @param int $userId ID del usuario
     * @param array $currentBehavior Comportamiento actual
     * @return array Anomalías detectadas
     */
    public function detectUsageAnomalies(int $userId, array $currentBehavior): array;

    // ======================================================
    // CONFIGURACIÓN Y ADMINISTRACIÓN
    // ======================================================

    /**
     * Obtiene configuración de moderación
     *
     * @param string|null $key Clave específica o null para toda la config
     * @return array|string|null Configuración solicitada
     */
    public function getModerationConfig(?string $key = null): array|string|null;

    /**
     * Actualiza configuración de moderación
     *
     * @param string $key Clave de configuración
     * @param mixed $value Nuevo valor
     * @return bool True si se actualizó correctamente
     */
    public function updateModerationConfig(string $key, mixed $value): bool;

    /**
     * Obtiene lista de categorías de violación activas
     *
     * @return array Lista de categorías
     */
    public function getActiveViolationCategories(): array;

    /**
     * Actualiza categorías de violación
     *
     * @param array $categories Nuevas categorías
     * @return bool True si se actualizaron correctamente
     */
    public function updateViolationCategories(array $categories): bool;

    // ======================================================
    // AUDITORÍA Y TRAZABILIDAD
    // ======================================================

    /**
     * Registra acción de auditoría
     *
     * @param array $auditData Datos de la acción
     * @return bool True si se registró correctamente
     */
    public function logAuditAction(array $auditData): bool;

    /**
     * Obtiene log de auditoría
     *
     * @param array $filters Filtros de búsqueda
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator Log de auditoría paginado
     */
    public function getAuditLog(array $filters = [], int $perPage = 50): LengthAwarePaginator;

    /**
     * Obtiene historial de acciones sobre un reporte
     *
     * @param int $reportId ID del reporte
     * @return array Historial de acciones
     */
    public function getReportActionHistory(int $reportId): array;

    /**
     * Obtiene métricas de cumplimiento SLA
     *
     * @param string $period Período de análisis
     * @return array Métricas SLA
     */
    public function getSLAComplianceMetrics(string $period = 'month'): array;

    // ======================================================
    // OPERACIONES DE LIMPIEZA Y MANTENIMIENTO
    // ======================================================

    /**
     * Limpia reportes antiguos según políticas de retención
     *
     * @param Carbon $olderThan Fecha límite para limpieza
     * @return int Número de registros eliminados
     */
    public function cleanupOldReports(Carbon $olderThan): int;

    /**
     * Archiva reportes resueltos antiguos
     *
     * @param Carbon $olderThan Fecha límite para archivo
     * @return int Número de reportes archivados
     */
    public function archiveResolvedReports(Carbon $olderThan): int;

    /**
     * Optimiza índices de base de datos para moderación
     *
     * @return bool True si la optimización fue exitosa
     */
    public function optimizeModerationIndexes(): bool;

    /**
     * Obtiene estadísticas de almacenamiento
     *
     * @return array Estadísticas de uso de almacenamiento
     */
    public function getStorageStatistics(): array;

    // ======================================================
    // OPERACIONES ESPECIALES
    // ======================================================

    /**
     * Ejecuta análisis masivo de contenido
     *
     * @param array $contentIds IDs de contenido a analizar
     * @param array $options Opciones de análisis
     * @return array Resultados del análisis masivo
     */
    public function bulkContentAnalysis(array $contentIds, array $options = []): array;

    /**
     * Genera reporte de moderación personalizado
     *
     * @param array $parameters Parámetros del reporte
     * @return array Datos del reporte generado
     */
    public function generateCustomModerationReport(array $parameters): array;

    /**
     * Exporta datos de moderación para análisis externo
     *
     * @param array $criteria Criterios de exportación
     * @param string $format Formato de exportación ('csv', 'json', 'xlsx')
     * @return string Ruta del archivo exportado
     */
    public function exportModerationData(array $criteria, string $format = 'csv'): string;

    /**
     * Importa reglas de moderación desde archivo externo
     *
     * @param string $filePath Ruta del archivo a importar
     * @param string $ruleType Tipo de reglas a importar
     * @return array Resultado de la importación
     */
    public function importModerationRules(string $filePath, string $ruleType): array;

    /**
     * Ejecuta migración de datos de moderación
     *
     * @param string $migrationType Tipo de migración
     * @param array $options Opciones de migración
     * @return array Resultado de la migración
     */
    public function executeModerationDataMigration(string $migrationType, array $options = []): array;
}