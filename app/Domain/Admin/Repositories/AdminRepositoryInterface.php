<?php

declare(strict_types=1);

namespace App\Domain\Admin\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Admin Repository Interface
 * 
 * Contrato completo para operaciones de repositorio del sistema administrativo.
 * Define todos los métodos necesarios para soportar dashboard, gestión de usuarios,
 * moderación de contenido y analytics avanzados.
 * 
 * Funcionalidades principales:
 * - Consultas de dashboard y métricas en tiempo real
 * - Gestión completa de usuarios y perfiles
 * - Operaciones de moderación y contenido
 * - Analytics avanzados y reportes ejecutivos
 * - Auditoría y logging de acciones administrativas
 * - Configuraciones del sistema y políticas
 * - Machine Learning y análisis predictivo
 * - Business Intelligence y KPIs
 * - Gestión de experimentos A/B
 * - Compliance y seguridad
 * 
 * @package App\Domain\Admin\Repositories
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 * @since Laravel 12.0 / PHP 8.2
 */
interface AdminRepositoryInterface
{
    // ===========================================
    // Dashboard & Overview Metrics
    // ===========================================

    /**
     * Obtener conteo total de usuarios
     * 
     * @param array $filters Filtros aplicables
     * @return int
     */
    public function getTotalUsersCount(array $filters = []): int;

    /**
     * Obtener conteo de usuarios activos
     * 
     * @param array $filters Filtros aplicables
     * @return int
     */
    public function getActiveUsersCount(array $filters = []): int;

    /**
     * Obtener conteo de nuevos usuarios
     * 
     * @param array $filters Filtros aplicables
     * @return int
     */
    public function getNewUsersCount(array $filters = []): int;

    /**
     * Obtener conteo total de matches
     * 
     * @param array $filters Filtros aplicables
     * @return int
     */
    public function getTotalMatchesCount(array $filters = []): int;

    /**
     * Obtener conteo total de mensajes
     * 
     * @param array $filters Filtros aplicables
     * @return int
     */
    public function getTotalMessagesCount(array $filters = []): int;

    /**
     * Obtener ingresos totales
     * 
     * @param array $filters Filtros aplicables
     * @return float
     */
    public function getTotalRevenue(array $filters = []): float;

    /**
     * Obtener usuarios online actualmente
     * 
     * @return int
     */
    public function getOnlineUsersCount(): int;

    /**
     * Obtener conversaciones activas
     * 
     * @return int
     */
    public function getActiveConversationsCount(): int;

    /**
     * Obtener registros recientes
     * 
     * @param int $minutes Minutos hacia atrás
     * @return int
     */
    public function getRecentRegistrations(int $minutes = 60): int;

    /**
     * Obtener matches recientes
     * 
     * @param int $minutes Minutos hacia atrás
     * @return int
     */
    public function getRecentMatches(int $minutes = 60): int;

    /**
     * Obtener mensajes recientes
     * 
     * @param int $minutes Minutos hacia atrás
     * @return int
     */
    public function getRecentMessages(int $minutes = 60): int;

    /**
     * Obtener compras recientes
     * 
     * @param int $minutes Minutos hacia atrás
     * @return int
     */
    public function getRecentPurchases(int $minutes = 60): int;

    // ===========================================
    // Time-based Metrics
    // ===========================================

    /**
     * Obtener registros por período
     * 
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @return int
     */
    public function getRegistrationsByPeriod(Carbon $start, Carbon $end): int;

    /**
     * Obtener matches por período
     * 
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @return int
     */
    public function getMatchesByPeriod(Carbon $start, Carbon $end): int;

    /**
     * Obtener mensajes por período
     * 
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @return int
     */
    public function getMessagesByPeriod(Carbon $start, Carbon $end): int;

    /**
     * Obtener ingresos por período
     * 
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @param array $filters Filtros adicionales
     * @return float
     */
    public function getRevenueByPeriod(Carbon $start, Carbon $end, array $filters = []): float;

    /**
     * Obtener usuarios activos por período
     * 
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @return int
     */
    public function getActiveUsersByPeriod(Carbon $start, Carbon $end): int;

    /**
     * Obtener duración promedio de sesión
     * 
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @return float
     */
    public function getAverageSessionDuration(Carbon $start, Carbon $end): float;

    /**
     * Obtener tasa de conversión
     * 
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @return float
     */
    public function getConversionRate(Carbon $start, Carbon $end): float;

    /**
     * Obtener tasa de churn
     * 
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @return float
     */
    public function getChurnRate(Carbon $start, Carbon $end): float;

    // ===========================================
    // KPI Metrics
    // ===========================================

    /**
     * Obtener DAU (Daily Active Users)
     * 
     * @return int
     */
    public function getDAU(): int;

    /**
     * Obtener WAU (Weekly Active Users)
     * 
     * @return int
     */
    public function getWAU(): int;

    /**
     * Obtener MAU (Monthly Active Users)
     * 
     * @return int
     */
    public function getMAU(): int;

    /**
     * Obtener tasa de nuevos usuarios
     * 
     * @return float
     */
    public function getNewUserRate(): float;

    /**
     * Obtener tasa de retención de usuarios
     * 
     * @return float
     */
    public function getUserRetentionRate(): float;

    /**
     * Obtener tasa de conversión de registro
     * 
     * @return float
     */
    public function getRegistrationConversionRate(): float;

    /**
     * Obtener tasa de éxito de matches
     * 
     * @return float
     */
    public function getMatchSuccessRate(): float;

    /**
     * Obtener tasa de inicio de conversación
     * 
     * @return float
     */
    public function getConversationStartRate(): float;

    /**
     * Obtener tasa de respuesta de mensajes
     * 
     * @return float
     */
    public function getMessageResponseRate(): float;

    /**
     * Obtener duración promedio de sesión
     * 
     * @return float
     */
    public function getAverageSessionLength(): float;

    /**
     * Obtener páginas por sesión
     * 
     * @return float
     */
    public function getPagesPerSession(): float;

    /**
     * Obtener tasa de rebote
     * 
     * @return float
     */
    public function getBounceRate(): float;

    /**
     * Obtener MRR (Monthly Recurring Revenue)
     * 
     * @param array $filters Filtros aplicables
     * @return float
     */
    public function getMRR(array $filters = []): float;

    /**
     * Obtener ARR (Annual Recurring Revenue)
     * 
     * @param array $filters Filtros aplicables
     * @return float
     */
    public function getARR(array $filters = []): float;

    /**
     * Obtener ARPU (Average Revenue Per User)
     * 
     * @param array $filters Filtros aplicables
     * @return float
     */
    public function getARPU(array $filters = []): float;

    /**
     * Obtener LTV (Lifetime Value)
     * 
     * @param array $filters Filtros aplicables
     * @return float
     */
    public function getLTV(array $filters = []): float;

    /**
     * Obtener CAC (Customer Acquisition Cost)
     * 
     * @return float
     */
    public function getCAC(): float;

    /**
     * Obtener tasa de conversión de suscripción
     * 
     * @return float
     */
    public function getSubscriptionConversionRate(): float;

    /**
     * Obtener tasa de completación de perfil
     * 
     * @return float
     */
    public function getProfileCompletionRate(): float;

    /**
     * Obtener tasa de subida de fotos
     * 
     * @return float
     */
    public function getPhotoUploadRate(): float;

    /**
     * Obtener tasa de moderación de contenido
     * 
     * @return float
     */
    public function getContentModerationRate(): float;

    /**
     * Obtener estadísticas de contenido generado por usuarios
     * 
     * @return array
     */
    public function getUserGeneratedContentStats(): array;

    // ===========================================
    // User Management
    // ===========================================

    /**
     * Buscar usuarios con filtros avanzados
     * 
     * @param array $filters Filtros de búsqueda
     * @param array $sorting Opciones de ordenamiento
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator
     */
    public function searchUsers(array $filters, array $sorting, int $perPage): LengthAwarePaginator;

    /**
     * Obtener usuario por ID con relaciones
     * 
     * @param int $userId ID del usuario
     * @param bool $includeRelations Incluir relaciones
     * @return array|null
     */
    public function getUserById(int $userId, bool $includeRelations = false): ?array;

    /**
     * Obtener usuarios problemáticos
     * 
     * @param array $criteria Criterios de detección
     * @return Collection
     */
    public function getProblematicUsers(array $criteria): Collection;

    /**
     * Suspender usuario
     * 
     * @param int $userId ID del usuario
     * @param array $suspensionData Datos de suspensión
     * @return array
     */
    public function suspendUser(int $userId, array $suspensionData): array;

    /**
     * Reactivar usuario
     * 
     * @param int $userId ID del usuario
     * @param array $reactivationData Datos de reactivación
     * @return bool
     */
    public function reactivateUser(int $userId, array $reactivationData): bool;

    /**
     * Eliminar cuenta de usuario
     * 
     * @param int $userId ID del usuario
     * @param array $deletionData Datos de eliminación
     * @return array
     */
    public function deleteUserAccount(int $userId, array $deletionData): array;

    /**
     * Verificar usuario
     * 
     * @param int $userId ID del usuario
     * @param array $verificationData Datos de verificación
     * @return array
     */
    public function verifyUser(int $userId, array $verificationData): array;

    /**
     * Revocar verificación de usuario
     * 
     * @param int $userId ID del usuario
     * @param array $revocationData Datos de revocación
     * @return bool
     */
    public function revokeUserVerification(int $userId, array $revocationData): bool;

    /**
     * Enviar mensaje a usuario
     * 
     * @param int $userId ID del usuario
     * @param array $messageData Datos del mensaje
     * @return array
     */
    public function sendUserMessage(int $userId, array $messageData): array;

    /**
     * Agregar nota administrativa
     * 
     * @param int $userId ID del usuario
     * @param array $noteData Datos de la nota
     * @return array
     */
    public function addAdminNote(int $userId, array $noteData): array;

    /**
     * Obtener metadata administrativa de usuario
     * 
     * @param int $userId ID del usuario
     * @return array
     */
    public function getUserAdminMetadata(int $userId): array;

    /**
     * Evaluar riesgo de usuario
     * 
     * @param int $userId ID del usuario
     * @return array
     */
    public function assessUserRisk(int $userId): array;

    /**
     * Verificar indicadores de fraude
     * 
     * @param int $userId ID del usuario
     * @return array
     */
    public function checkFraudIndicators(int $userId): array;

    // ===========================================
    // Content Moderation
    // ===========================================

    /**
     * Obtener cola de moderación
     * 
     * @param array $filters Filtros de búsqueda
     * @param array $sorting Opciones de ordenamiento
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator
     */
    public function getModerationQueue(array $filters, array $sorting, int $perPage): LengthAwarePaginator;

    /**
     * Obtener conteo de contenido pendiente
     * 
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getPendingContentCount(array $filters = []): int;

    /**
     * Obtener conteo de contenido de alta prioridad
     * 
     * @return int
     */
    public function getHighPriorityContentCount(): int;

    /**
     * Obtener conteo de contenido auto-marcado
     * 
     * @return int
     */
    public function getAutoFlaggedContentCount(): int;

    /**
     * Obtener conteo de contenido reportado por usuarios
     * 
     * @return int
     */
    public function getUserReportedContentCount(): int;

    /**
     * Obtener tiempo promedio de revisión
     * 
     * @return float
     */
    public function getAverageReviewTime(): float;

    /**
     * Obtener carga de trabajo de moderadores
     * 
     * @return array
     */
    public function getModeratorWorkload(): array;

    /**
     * Obtener conteo de casos escalados
     * 
     * @return int
     */
    public function getEscalatedCasesCount(): int;

    /**
     * Obtener conteo procesado hoy
     * 
     * @return int
     */
    public function getProcessedTodayCount(): int;

    /**
     * Obtener horas de atraso en cola
     * 
     * @return float
     */
    public function getBacklogHours(): float;

    /**
     * Obtener conteo de contenido por tipo
     * 
     * @return array
     */
    public function getContentCountByType(): array;

    /**
     * Obtener conteo de contenido por tipo de violación
     * 
     * @return array
     */
    public function getContentCountByViolationType(): array;

    /**
     * Obtener contenido por ID
     * 
     * @param int $contentId ID del contenido
     * @return array|null
     */
    public function getContentById(int $contentId): ?array;

    /**
     * Revisar contenido
     * 
     * @param int $contentId ID del contenido
     * @param array $reviewData Datos de la revisión
     * @return array
     */
    public function reviewContent(int $contentId, array $reviewData): array;

    /**
     * Aprobar contenido
     * 
     * @param int $contentId ID del contenido
     * @param array $approvalData Datos de aprobación
     * @return bool
     */
    public function approveContent(int $contentId, array $approvalData): bool;

    /**
     * Rechazar contenido
     * 
     * @param int $contentId ID del contenido
     * @param array $rejectionData Datos de rechazo
     * @return bool
     */
    public function rejectContent(int $contentId, array $rejectionData): bool;

    /**
     * Actualizar configuración de moderación AI
     * 
     * @param array $config Configuración AI
     * @return bool
     */
    public function updateAIModerationConfig(array $config): bool;

    /**
     * Guardar análisis AI
     * 
     * @param int $contentId ID del contenido
     * @param array $analysis Análisis AI
     * @return bool
     */
    public function saveAIAnalysis(int $contentId, array $analysis): bool;

    /**
     * Obtener políticas de contenido
     * 
     * @param string|null $category Categoría específica
     * @return array
     */
    public function getContentPolicies(?string $category = null): array;

    /**
     * Actualizar política de contenido
     * 
     * @param string $policyId ID de la política
     * @param array $policyData Datos de la política
     * @return bool
     */
    public function updateContentPolicy(string $policyId, array $policyData): bool;

    /**
     * Obtener apelaciones de contenido
     * 
     * @param array $filters Filtros de búsqueda
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator
     */
    public function getContentAppeals(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Obtener apelación por ID
     * 
     * @param int $appealId ID de la apelación
     * @return array|null
     */
    public function getAppealById(int $appealId): ?array;

    /**
     * Procesar apelación
     * 
     * @param int $appealId ID de la apelación
     * @param array $decision Decisión de la apelación
     * @return bool
     */
    public function processAppeal(int $appealId, array $decision): bool;

    // ===========================================
    // Analytics & Reporting
    // ===========================================

    /**
     * Obtener nuevos usuarios en período
     * 
     * @param array $dateRange Rango de fechas
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getNewUsersInPeriod(array $dateRange, array $filters = []): int;

    /**
     * Obtener usuarios activos en período
     * 
     * @param array $dateRange Rango de fechas
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getActiveUsersInPeriod(array $dateRange, array $filters = []): int;

    /**
     * Obtener usuarios que abandonaron en período
     * 
     * @param array $dateRange Rango de fechas
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getChurnedUsersInPeriod(array $dateRange, array $filters = []): int;

    /**
     * Obtener usuarios reactivados en período
     * 
     * @param array $dateRange Rango de fechas
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getReactivatedUsersInPeriod(array $dateRange, array $filters = []): int;

    /**
     * Obtener usuarios de cohorte
     * 
     * @param string $cohortType Tipo de cohorte
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @return array
     */
    public function getCohortUsers(string $cohortType, Carbon $start, Carbon $end): array;

    /**
     * Obtener retención de cohorte
     * 
     * @param array $cohortUsers Usuarios de la cohorte
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @return int
     */
    public function getCohortRetention(array $cohortUsers, Carbon $start, Carbon $end): int;

    /**
     * Obtener ingresos de cohorte
     * 
     * @param array $cohortUsers Usuarios de la cohorte
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @return int
     */
    public function getCohortRevenue(array $cohortUsers, Carbon $start, Carbon $end): int;

    /**
     * Obtener engagement de cohorte
     * 
     * @param array $cohortUsers Usuarios de la cohorte
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @return int
     */
    public function getCohortEngagement(array $cohortUsers, Carbon $start, Carbon $end): int;

    /**
     * Obtener matches de cohorte
     * 
     * @param array $cohortUsers Usuarios de la cohorte
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @return int
     */
    public function getCohortMatches(array $cohortUsers, Carbon $start, Carbon $end): int;

    // ===========================================
    // Revenue Analytics
    // ===========================================

    /**
     * Obtener ingresos por suscripción
     * 
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @param array $filters Filtros adicionales
     * @return float
     */
    public function getSubscriptionRevenue(Carbon $start, Carbon $end, array $filters = []): float;

    /**
     * Obtener compras únicas
     * 
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @param array $filters Filtros adicionales
     * @return float
     */
    public function getOneTimePurchases(Carbon $start, Carbon $end, array $filters = []): float;

    /**
     * Obtener ingresos por regalos
     * 
     * @param Carbon $start Fecha inicio
     * @param Carbon $end Fecha fin
     * @param array $filters Filtros adicionales
     * @return float
     */
    public function getGiftRevenue(Carbon $start, Carbon $end, array $filters = []): float;

    /**
     * Obtener ingresos por plan
     * 
     * @param array $filters Filtros adicionales
     * @return array
     */
    public function getRevenueByPlan(array $filters = []): array;

    /**
     * Obtener ingresos por país
     * 
     * @param array $filters Filtros adicionales
     * @return array
     */
    public function getRevenueByCountry(array $filters = []): array;

    /**
     * Obtener desglose de métodos de pago
     * 
     * @param array $filters Filtros adicionales
     * @return array
     */
    public function getPaymentMethodsBreakdown(array $filters = []): array;

    /**
     * Obtener reembolsos y contracargos
     * 
     * @param array $filters Filtros adicionales
     * @return array
     */
    public function getRefundsAndChargebacks(array $filters = []): array;

    /**
     * Obtener nuevas suscripciones
     * 
     * @param array $dateRange Rango de fechas
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getNewSubscriptions(array $dateRange, array $filters = []): int;

    /**
     * Obtener churn de suscripción
     * 
     * @param array $dateRange Rango de fechas
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getSubscriptionChurn(array $dateRange, array $filters = []): int;

    /**
     * Obtener ingresos de expansión
     * 
     * @param array $dateRange Rango de fechas
     * @param array $filters Filtros adicionales
     * @return float
     */
    public function getExpansionRevenue(array $dateRange, array $filters = []): float;

    /**
     * Obtener ingresos de contracción
     * 
     * @param array $dateRange Rango de fechas
     * @param array $filters Filtros adicionales
     * @return float
     */
    public function getContractionRevenue(array $dateRange, array $filters = []): float;

    // ===========================================
    // System & Configuration
    // ===========================================

    /**
     * Obtener alertas del sistema
     * 
     * @return array
     */
    public function getSystemAlerts(): array;

    /**
     * Obtener configuraciones de la app
     * 
     * @return array
     */
    public function getAppSettings(): array;

    /**
     * Obtener feature flags
     * 
     * @return array
     */
    public function getFeatureFlags(): array;

    /**
     * Obtener configuraciones de rate limits
     * 
     * @return array
     */
    public function getRateLimitSettings(): array;

    /**
     * Obtener configuraciones de notificaciones
     * 
     * @return array
     */
    public function getNotificationSettings(): array;

    /**
     * Obtener configuraciones de seguridad
     * 
     * @return array
     */
    public function getSecuritySettings(): array;

    /**
     * Obtener configuraciones de pagos
     * 
     * @return array
     */
    public function getPaymentSettings(): array;

    /**
     * Obtener políticas de contenido
     * 
     * @return array
     */
    public function getContentPoliciesSettings(): array;

    /**
     * Obtener configuraciones de localización
     * 
     * @return array
     */
    public function getLocalizationSettings(): array;

    /**
     * Actualizar configuraciones del sistema
     * 
     * @param string $category Categoría de configuración
     * @param array $settings Nuevas configuraciones
     * @return bool
     */
    public function updateSystemSettings(string $category, array $settings): bool;

    // ===========================================
    // Logging & Audit Trail
    // ===========================================

    /**
     * Registrar acción administrativa
     * 
     * @param string $action Acción realizada
     * @param array $data Datos de la acción
     * @return bool
     */
    public function logAdminAction(string $action, array $data): bool;

    /**
     * Registrar acción de moderación
     * 
     * @param string $action Acción de moderación
     * @param array $data Datos de la acción
     * @return bool
     */
    public function logModerationAction(string $action, array $data): bool;

    // ===========================================
    // Reports & Export
    // ===========================================

    /**
     * Generar reporte de usuario
     * 
     * @param array $reportData Datos del reporte
     * @param string $format Formato del reporte
     * @return array
     */
    public function generateUserReport(array $reportData, string $format): array;

    /**
     * Guardar reporte generado
     * 
     * @param array $report Datos del reporte
     * @return array
     */
    public function saveGeneratedReport(array $report): array;

    /**
     * Guardar reporte personalizado
     * 
     * @param array $reportData Datos del reporte
     * @return array
     */
    public function saveCustomReport(array $reportData): array;

    // ===========================================
    // Geographic & Demographic Data
    // ===========================================

    /**
     * Obtener usuarios por país
     * 
     * @return array
     */
    public function getUsersByCountry(): array;

    /**
     * Obtener usuarios por ciudad
     * 
     * @return array
     */
    public function getUsersByCity(): array;

    /**
     * Obtener usuarios por región
     * 
     * @return array
     */
    public function getUsersByRegion(): array;

    /**
     * Obtener crecimiento por ubicación
     * 
     * @return array
     */
    public function getGrowthByLocation(): array;

    /**
     * Obtener engagement por ubicación
     * 
     * @return array
     */
    public function getEngagementByLocation(): array;

    /**
     * Obtener ingresos por ubicación
     * 
     * @return array
     */
    public function getRevenueByLocation(): array;

    /**
     * Obtener métricas de localización
     * 
     * @return array
     */
    public function getLocalizationMetrics(): array;

    // ===========================================
    // Device & Platform Analytics
    // ===========================================

    /**
     * Obtener distribución de tipos de dispositivo
     * 
     * @return array
     */
    public function getDeviceTypeDistribution(): array;

    /**
     * Obtener distribución de sistemas operativos
     * 
     * @return array
     */
    public function getOSDistribution(): array;

    /**
     * Obtener distribución de navegadores
     * 
     * @return array
     */
    public function getBrowserDistribution(): array;

    /**
     * Obtener distribución de versiones de app
     * 
     * @return array
     */
    public function getAppVersionDistribution(): array;

    /**
     * Obtener distribución de resoluciones de pantalla
     * 
     * @return array
     */
    public function getScreenResolutionDistribution(): array;

    /**
     * Obtener rendimiento por dispositivo
     * 
     * @return array
     */
    public function getPerformanceByDevice(): array;

    /**
     * Obtener reportes de crash por dispositivo
     * 
     * @return array
     */
    public function getCrashReportsByDevice(): array;

    // ===========================================
    // Content Statistics
    // ===========================================

    /**
     * Obtener conteo total de perfiles
     * 
     * @return int
     */
    public function getTotalProfilesCount(): int;

    /**
     * Obtener conteo de perfiles completados
     * 
     * @return int
     */
    public function getCompletedProfilesCount(): int;

    /**
     * Obtener conteo de perfiles verificados
     * 
     * @return int
     */
    public function getVerifiedProfilesCount(): int;

    /**
     * Obtener promedio de fotos por perfil
     * 
     * @return float
     */
    public function getAveragePhotosPerProfile(): float;

    /**
     * Obtener subidas de contenido diarias
     * 
     * @return array
     */
    public function getDailyContentUploads(): array;

    /**
     * Obtener distribución de tipos de contenido
     * 
     * @return array
     */
    public function getContentTypeDistribution(): array;

    /**
     * Obtener conteo de cola de moderación
     * 
     * @return int
     */
    public function getModerationQueueCount(): int;

    /**
     * Obtener tasa de aprobación de contenido
     * 
     * @return float
     */
    public function getContentApprovalRate(): float;

    /**
     * Obtener razones de rechazo
     * 
     * @return array
     */
    public function getRejectionReasons(): array;

    /**
     * Obtener revisiones pendientes
     * 
     * @return array
     */
    public function getPendingReviews(): array;

    /**
     * Obtener cola de moderación prioritaria
     * 
     * @return array
     */
    public function getPriorityModerationQueue(): array;

    /**
     * Obtener estadísticas de moderación automatizada
     * 
     * @return array
     */
    public function getAutomatedModerationStats(): array;

    /**
     * Obtener casos escalados
     * 
     * @return array
     */
    public function getEscalatedCases(): array;

    /**
     * Obtener tiempos de procesamiento de moderación
     * 
     * @return array
     */
    public function getModerationProcessingTimes(): array;

    // ===========================================
    // Additional Methods for Content Stats
    // ===========================================

    /**
     * Obtener conteo de contenido total
     * 
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getTotalContentCount(array $filters = []): int;

    /**
     * Obtener conteo de contenido aprobado
     * 
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getApprovedContentCount(array $filters = []): int;

    /**
     * Obtener conteo de contenido rechazado
     * 
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getRejectedContentCount(array $filters = []): int;

    /**
     * Obtener conteo de contenido por tipo específico
     * 
     * @param string $type Tipo de contenido
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getContentCountByType(string $type, array $filters = []): int;

    /**
     * Obtener conteo auto-aprobado
     * 
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getAutoApprovedCount(array $filters = []): int;

    /**
     * Obtener conteo auto-rechazado
     * 
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getAutoRejectedCount(array $filters = []): int;

    /**
     * Obtener conteo revisado por humanos
     * 
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getHumanReviewedCount(array $filters = []): int;

    /**
     * Obtener conteo escalado
     * 
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getEscalatedCount(array $filters = []): int;

    /**
     * Obtener conteo de apelaciones
     * 
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getAppealsCount(array $filters = []): int;

    /**
     * Obtener conteo de violación por tipo
     * 
     * @param string $violationType Tipo de violación
     * @param array $filters Filtros adicionales
     * @return int
     */
    public function getViolationCount(string $violationType, array $filters = []): int;

    /**
     * Obtener productividad de moderadores
     * 
     * @param array $filters Filtros adicionales
     * @return array
     */
    public function getModeratorProductivity(array $filters = []): array;

    /**
     * Obtener horas de atraso en cola
     * 
     * @param array $filters Filtros adicionales
     * @return float
     */
    public function getQueueBacklogHours(array $filters = []): float;

    /**
     * Obtener tasa de precisión de AI
     * 
     * @param array $filters Filtros adicionales
     * @return float
     */
    public function getAIAccuracyRate(array $filters = []): float;

    /**
     * Obtener tasa de éxito de apelaciones
     * 
     * @param array $filters Filtros adicionales
     * @return float
     */
    public function getAppealSuccessRate(array $filters = []): float;

    /**
     * Obtener tendencias de contenido diario
     * 
     * @param array $filters Filtros adicionales
     * @return array
     */
    public function getDailyContentTrends(array $filters = []): array;

    /**
     * Obtener tendencias semanales de violaciones
     * 
     * @param array $filters Filtros adicionales
     * @return array
     */
    public function getWeeklyViolationTrends(array $filters = []): array;

    /**
     * Obtener patrones mensuales de contenido
     * 
     * @param array $filters Filtros adicionales
     * @return array
     */
    public function getMonthlyContentPatterns(array $filters = []): array;

    /**
     * Obtener precisión de detección de AI
     * 
     * @param array $filters Filtros adicionales
     * @return float
     */
    public function getAIDetectionAccuracy(array $filters = []): float;

    /**
     * Obtener tasa de falsos positivos de AI
     * 
     * @param array $filters Filtros adicionales
     * @return float
     */
    public function getAIFalsePositiveRate(array $filters = []): float;

    /**
     * Obtener distribución de confianza de AI
     * 
     * @param array $filters Filtros adicionales
     * @return array
     */
    public function getAIConfidenceDistribution(array $filters = []): array;

    /**
     * Obtener rendimiento de AI por categoría
     * 
     * @param array $filters Filtros adicionales
     * @return array
     */
    public function getAICategoryPerformance(array $filters = []): array;
}