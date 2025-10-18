<?php

declare(strict_types=1);

namespace App\Domain\Notification\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Notification Repository Interface
 * 
 * Contrato completo para operaciones de repositorio del sistema de notificaciones.
 * Implementa patrones de Clean Architecture con soporte para múltiples canales,
 * analytics avanzados, y gestión de preferencias de usuario.
 * 
 * Funcionalidades principales:
 * - CRUD completo de notificaciones con soft deletes
 * - Gestión de preferencias y suscripciones por canal
 * - Analytics y métricas de engagement en tiempo real
 * - Sistema de plantillas con i18n y personalización
 * - Rate limiting y compliance (TCPA, GDPR, CAN-SPAM)
 * - Tokens de dispositivos móviles y web push
 * - Campañas masivas con segmentación avanzada
 * - A/B testing y optimización de contenido
 * - Blacklist y gestión de opt-outs
 * - Archivado automático y retención de datos
 * 
 * @package App\Domain\Notification\Repositories
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 * @since Laravel 12.0 / PHP 8.2
 */
interface NotificationRepositoryInterface
{
    // ===========================================
    // CRUD Operations - Notifications
    // ===========================================

    /**
     * Crear nueva notificación con validación completa
     * 
     * @param array $data Datos de la notificación
     * @return array Notificación creada con ID y metadata
     */
    public function create(array $data): array;

    /**
     * Buscar notificación por ID con relaciones opcionales
     * 
     * @param int $id ID de la notificación
     * @param array $with Relaciones a cargar
     * @return array|null Datos de la notificación o null
     */
    public function findById(int $id, array $with = []): ?array;

    /**
     * Buscar notificación por UUID único
     * 
     * @param string $uuid UUID de la notificación
     * @param array $with Relaciones a cargar
     * @return array|null Datos de la notificación o null
     */
    public function findByUuid(string $uuid, array $with = []): ?array;

    /**
     * Actualizar notificación existente
     * 
     * @param int $id ID de la notificación
     * @param array $data Datos a actualizar
     * @return array Notificación actualizada
     */
    public function update(int $id, array $data): array;

    /**
     * Eliminar notificación (soft delete)
     * 
     * @param int $id ID de la notificación
     * @return bool Éxito de la operación
     */
    public function delete(int $id): bool;

    /**
     * Restaurar notificación eliminada
     * 
     * @param int $id ID de la notificación
     * @return bool Éxito de la operación
     */
    public function restore(int $id): bool;

    /**
     * Eliminar notificación permanentemente
     * 
     * @param int $id ID de la notificación
     * @return bool Éxito de la operación
     */
    public function forceDelete(int $id): bool;

    // ===========================================
    // User Notifications - Queries & Filters
    // ===========================================

    /**
     * Obtener notificaciones de usuario con paginación y filtros
     * 
     * @param string $userId ID del usuario
     * @param array $filters Filtros de búsqueda
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator Notificaciones paginadas
     */
    public function getUserNotifications(string $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Obtener notificaciones no leídas del usuario
     * 
     * @param string $userId ID del usuario
     * @param array $channels Canales específicos (opcional)
     * @return Collection Notificaciones no leídas
     */
    public function getUnreadNotifications(string $userId, array $channels = []): Collection;

    /**
     * Contar notificaciones no leídas por canal
     * 
     * @param string $userId ID del usuario
     * @return array Conteo por canal
     */
    public function getUnreadCountsByChannel(string $userId): array;

    /**
     * Obtener notificaciones recientes del usuario
     * 
     * @param string $userId ID del usuario
     * @param int $limit Límite de resultados
     * @param array $types Tipos específicos (opcional)
     * @return Collection Notificaciones recientes
     */
    public function getRecentNotifications(string $userId, int $limit = 50, array $types = []): Collection;

    /**
     * Buscar notificaciones por criterios avanzados
     * 
     * @param array $criteria Criterios de búsqueda
     * @param array $orderBy Ordenamiento
     * @param int $limit Límite de resultados
     * @return Collection Notificaciones encontradas
     */
    public function searchNotifications(array $criteria, array $orderBy = [], int $limit = 100): Collection;

    // ===========================================
    // Status Management
    // ===========================================

    /**
     * Marcar notificación como leída
     * 
     * @param int $notificationId ID de la notificación
     * @param string $userId ID del usuario
     * @param Carbon|null $readAt Timestamp de lectura
     * @return bool Éxito de la operación
     */
    public function markAsRead(int $notificationId, string $userId, ?Carbon $readAt = null): bool;

    /**
     * Marcar múltiples notificaciones como leídas
     * 
     * @param array $notificationIds IDs de notificaciones
     * @param string $userId ID del usuario
     * @return int Cantidad marcadas
     */
    public function markMultipleAsRead(array $notificationIds, string $userId): int;

    /**
     * Marcar todas las notificaciones del usuario como leídas
     * 
     * @param string $userId ID del usuario
     * @param array $channels Canales específicos (opcional)
     * @return int Cantidad marcadas
     */
    public function markAllAsRead(string $userId, array $channels = []): int;

    /**
     * Marcar notificación como entregada
     * 
     * @param int $notificationId ID de la notificación
     * @param string $channel Canal de entrega
     * @param array $metadata Metadata de entrega
     * @return bool Éxito de la operación
     */
    public function markAsDelivered(int $notificationId, string $channel, array $metadata = []): bool;

    /**
     * Marcar notificación como fallida
     * 
     * @param int $notificationId ID de la notificación
     * @param string $channel Canal que falló
     * @param string $reason Razón del fallo
     * @param array $metadata Metadata adicional
     * @return bool Éxito de la operación
     */
    public function markAsFailed(int $notificationId, string $channel, string $reason, array $metadata = []): bool;

    // ===========================================
    // User Preferences Management
    // ===========================================

    /**
     * Obtener preferencias de notificación del usuario
     * 
     * @param string $userId ID del usuario
     * @return array Preferencias por canal y tipo
     */
    public function getUserPreferences(string $userId): array;

    /**
     * Actualizar preferencias de notificación
     * 
     * @param string $userId ID del usuario
     * @param array $preferences Nuevas preferencias
     * @return bool Éxito de la operación
     */
    public function updateUserPreferences(string $userId, array $preferences): bool;

    /**
     * Verificar si el usuario puede recibir notificación
     * 
     * @param string $userId ID del usuario
     * @param string $type Tipo de notificación
     * @param string $channel Canal de envío
     * @return bool Si puede recibir la notificación
     */
    public function canReceiveNotification(string $userId, string $type, string $channel): bool;

    /**
     * Obtener usuarios suscritos a tipo de notificación
     * 
     * @param string $type Tipo de notificación
     * @param string $channel Canal específico
     * @param array $filters Filtros adicionales
     * @return Collection Usuarios suscritos
     */
    public function getSubscribedUsers(string $type, string $channel, array $filters = []): Collection;

    /**
     * Procesar opt-out de usuario
     * 
     * @param string $userId ID del usuario
     * @param string $channel Canal del opt-out
     * @param string $reason Razón del opt-out
     * @return bool Éxito de la operación
     */
    public function processOptOut(string $userId, string $channel, string $reason): bool;

    // ===========================================
    // Device Token Management
    // ===========================================

    /**
     * Registrar token de dispositivo
     * 
     * @param string $userId ID del usuario
     * @param string $token Token del dispositivo
     * @param string $platform Plataforma (android/ios/web)
     * @param array $metadata Metadata del dispositivo
     * @return array Token registrado
     */
    public function registerDeviceToken(string $userId, string $token, string $platform, array $metadata = []): array;

    /**
     * Obtener tokens activos de usuario
     * 
     * @param string $userId ID del usuario
     * @param string|null $platform Plataforma específica
     * @return Collection Tokens activos
     */
    public function getUserTokens(string $userId, ?string $platform = null): Collection;

    /**
     * Invalidar token de dispositivo
     * 
     * @param string $token Token a invalidar
     * @param string $reason Razón de invalidación
     * @return bool Éxito de la operación
     */
    public function invalidateToken(string $token, string $reason): bool;

    /**
     * Limpiar tokens expirados
     * 
     * @param Carbon|null $before Fecha límite
     * @return int Tokens eliminados
     */
    public function cleanupExpiredTokens(?Carbon $before = null): int;

    /**
     * Actualizar estadísticas de token
     * 
     * @param string $token Token a actualizar
     * @param array $stats Nuevas estadísticas
     * @return bool Éxito de la operación
     */
    public function updateTokenStats(string $token, array $stats): bool;

    // ===========================================
    // Templates Management
    // ===========================================

    /**
     * Obtener plantilla de notificación
     * 
     * @param string $type Tipo de notificación
     * @param string $channel Canal de entrega
     * @param string $locale Idioma (opcional)
     * @return array|null Datos de la plantilla
     */
    public function getNotificationTemplate(string $type, string $channel, string $locale = 'en'): ?array;

    /**
     * Crear o actualizar plantilla
     * 
     * @param array $templateData Datos de la plantilla
     * @return array Plantilla creada/actualizada
     */
    public function saveNotificationTemplate(array $templateData): array;

    /**
     * Obtener todas las plantillas de un tipo
     * 
     * @param string $type Tipo de notificación
     * @return Collection Plantillas del tipo
     */
    public function getTemplatesByType(string $type): Collection;

    /**
     * Eliminar plantilla
     * 
     * @param int $templateId ID de la plantilla
     * @return bool Éxito de la operación
     */
    public function deleteTemplate(int $templateId): bool;

    /**
     * Obtener plantillas por A/B test
     * 
     * @param string $type Tipo de notificación
     * @param string $testGroup Grupo de prueba
     * @return array Plantillas del test
     */
    public function getTemplatesForABTest(string $type, string $testGroup): array;

    // ===========================================
    // Bulk Operations & Campaigns
    // ===========================================

    /**
     * Crear notificaciones masivas
     * 
     * @param array $notifications Array de notificaciones
     * @param int $batchSize Tamaño del lote
     * @return array Resultado de la operación
     */
    public function createBulkNotifications(array $notifications, int $batchSize = 1000): array;

    /**
     * Procesar cola de notificaciones pendientes
     * 
     * @param int $limit Límite de procesamiento
     * @param array $channels Canales a procesar
     * @return array Resultado del procesamiento
     */
    public function processPendingNotifications(int $limit = 500, array $channels = []): array;

    /**
     * Obtener estadísticas de campaña
     * 
     * @param string $campaignId ID de la campaña
     * @return array Estadísticas completas
     */
    public function getCampaignStats(string $campaignId): array;

    /**
     * Crear segmento de usuarios
     * 
     * @param array $criteria Criterios de segmentación
     * @param string $name Nombre del segmento
     * @return array Segmento creado
     */
    public function createUserSegment(array $criteria, string $name): array;

    /**
     * Obtener usuarios de un segmento
     * 
     * @param int $segmentId ID del segmento
     * @param int $limit Límite de resultados
     * @return Collection Usuarios del segmento
     */
    public function getSegmentUsers(int $segmentId, int $limit = 1000): Collection;

    // ===========================================
    // Analytics & Metrics
    // ===========================================

    /**
     * Registrar interacción con notificación
     * 
     * @param int $notificationId ID de la notificación
     * @param string $userId ID del usuario
     * @param string $action Acción realizada
     * @param array $metadata Metadata de la interacción
     * @return bool Éxito de la operación
     */
    public function recordInteraction(int $notificationId, string $userId, string $action, array $metadata = []): bool;

    /**
     * Obtener métricas de engagement
     * 
     * @param array $filters Filtros de consulta
     * @param string $groupBy Agrupación (day/week/month)
     * @return array Métricas de engagement
     */
    public function getEngagementMetrics(array $filters = [], string $groupBy = 'day'): array;

    /**
     * Obtener estadísticas de entrega por canal
     * 
     * @param Carbon $startDate Fecha de inicio
     * @param Carbon $endDate Fecha de fin
     * @param array $channels Canales específicos
     * @return array Estadísticas por canal
     */
    public function getDeliveryStatsByChannel(Carbon $startDate, Carbon $endDate, array $channels = []): array;

    /**
     * Calcular tasa de conversión
     * 
     * @param string $type Tipo de notificación
     * @param Carbon $startDate Fecha de inicio
     * @param Carbon $endDate Fecha de fin
     * @return array Métricas de conversión
     */
    public function getConversionMetrics(string $type, Carbon $startDate, Carbon $endDate): array;

    /**
     * Obtener top performers (mejores notificaciones)
     * 
     * @param string $metric Métrica a evaluar
     * @param int $limit Límite de resultados
     * @param array $filters Filtros adicionales
     * @return Collection Top performers
     */
    public function getTopPerformers(string $metric, int $limit = 10, array $filters = []): Collection;

    /**
     * Generar reporte de actividad
     * 
     * @param array $filters Filtros del reporte
     * @param string $format Formato de salida
     * @return array Datos del reporte
     */
    public function generateActivityReport(array $filters = [], string $format = 'array'): array;

    // ===========================================
    // Rate Limiting & Compliance
    // ===========================================

    /**
     * Verificar límites de rate limiting
     * 
     * @param string $userId ID del usuario
     * @param string $channel Canal de verificación
     * @param string $timeWindow Ventana de tiempo
     * @return array Estado del rate limiting
     */
    public function checkRateLimits(string $userId, string $channel, string $timeWindow = '1hour'): array;

    /**
     * Registrar envío para rate limiting
     * 
     * @param string $userId ID del usuario
     * @param string $channel Canal de envío
     * @param Carbon|null $sentAt Timestamp de envío
     * @return bool Éxito del registro
     */
    public function recordSendForRateLimit(string $userId, string $channel, ?Carbon $sentAt = null): bool;

    /**
     * Verificar compliance de usuario
     * 
     * @param string $userId ID del usuario
     * @param string $channel Canal a verificar
     * @return array Estado de compliance
     */
    public function checkUserCompliance(string $userId, string $channel): array;

    /**
     * Obtener usuarios en blacklist
     * 
     * @param string $channel Canal específico
     * @return Collection Usuarios en blacklist
     */
    public function getBlacklistedUsers(string $channel): Collection;

    /**
     * Agregar usuario a blacklist
     * 
     * @param string $userId ID del usuario
     * @param string $channel Canal de blacklist
     * @param string $reason Razón del bloqueo
     * @return bool Éxito de la operación
     */
    public function addToBlacklist(string $userId, string $channel, string $reason): bool;

    /**
     * Remover usuario de blacklist
     * 
     * @param string $userId ID del usuario
     * @param string $channel Canal de blacklist
     * @return bool Éxito de la operación
     */
    public function removeFromBlacklist(string $userId, string $channel): bool;

    // ===========================================
    // Scheduled Notifications
    // ===========================================

    /**
     * Programar notificación para envío futuro
     * 
     * @param array $notificationData Datos de la notificación
     * @param Carbon $scheduledAt Fecha/hora programada
     * @param array $options Opciones de programación
     * @return array Notificación programada
     */
    public function scheduleNotification(array $notificationData, Carbon $scheduledAt, array $options = []): array;

    /**
     * Obtener notificaciones programadas
     * 
     * @param Carbon|null $until Hasta qué fecha
     * @param array $statuses Estados específicos
     * @return Collection Notificaciones programadas
     */
    public function getScheduledNotifications(?Carbon $until = null, array $statuses = []): Collection;

    /**
     * Cancelar notificación programada
     * 
     * @param int $scheduledId ID de la notificación programada
     * @param string $reason Razón de cancelación
     * @return bool Éxito de la operación
     */
    public function cancelScheduledNotification(int $scheduledId, string $reason): bool;

    /**
     * Actualizar programación de notificación
     * 
     * @param int $scheduledId ID de la notificación programada
     * @param Carbon $newScheduledAt Nueva fecha/hora
     * @return bool Éxito de la operación
     */
    public function rescheduleNotification(int $scheduledId, Carbon $newScheduledAt): bool;

    /**
     * Procesar notificaciones listas para envío
     * 
     * @param int $batchSize Tamaño del lote
     * @return array Resultado del procesamiento
     */
    public function processReadyScheduledNotifications(int $batchSize = 100): array;

    // ===========================================
    // A/B Testing & Optimization
    // ===========================================

    /**
     * Crear test A/B para notificaciones
     * 
     * @param array $testConfig Configuración del test
     * @return array Test A/B creado
     */
    public function createABTest(array $testConfig): array;

    /**
     * Obtener configuración de test A/B activo
     * 
     * @param string $type Tipo de notificación
     * @param string $userId ID del usuario (para asignación)
     * @return array|null Configuración del test
     */
    public function getActiveABTest(string $type, string $userId): ?array;

    /**
     * Registrar resultado de test A/B
     * 
     * @param int $testId ID del test
     * @param int $notificationId ID de la notificación
     * @param string $variant Variante utilizada
     * @param array $results Resultados obtenidos
     * @return bool Éxito del registro
     */
    public function recordABTestResult(int $testId, int $notificationId, string $variant, array $results): bool;

    /**
     * Obtener estadísticas de test A/B
     * 
     * @param int $testId ID del test
     * @return array Estadísticas del test
     */
    public function getABTestStats(int $testId): array;

    /**
     * Finalizar test A/B y aplicar ganador
     * 
     * @param int $testId ID del test
     * @param string $winningVariant Variante ganadora
     * @return bool Éxito de la operación
     */
    public function finalizeABTest(int $testId, string $winningVariant): bool;

    // ===========================================
    // Data Archival & Cleanup
    // ===========================================

    /**
     * Archivar notificaciones antiguas
     * 
     * @param Carbon $olderThan Fecha límite para archivado
     * @param array $types Tipos específicos a archivar
     * @return array Resultado del archivado
     */
    public function archiveOldNotifications(Carbon $olderThan, array $types = []): array;

    /**
     * Limpiar datos temporales y logs antiguos
     * 
     * @param Carbon $olderThan Fecha límite para limpieza
     * @return array Resultado de la limpieza
     */
    public function cleanupOldData(Carbon $olderThan): array;

    /**
     * Obtener estadísticas de uso de almacenamiento
     * 
     * @return array Estadísticas de almacenamiento
     */
    public function getStorageStats(): array;

    /**
     * Exportar datos de notificaciones
     * 
     * @param array $filters Filtros de exportación
     * @param string $format Formato de exportación
     * @return array Datos exportados o ruta de archivo
     */
    public function exportNotificationData(array $filters = [], string $format = 'json'): array;

    /**
     * Obtener resumen de actividad diaria
     * 
     * @param Carbon $date Fecha específica
     * @return array Resumen de actividad
     */
    public function getDailyActivitySummary(Carbon $date): array;

    // ===========================================
    // System Health & Monitoring
    // ===========================================

    /**
     * Verificar salud del sistema de notificaciones
     * 
     * @return array Estado de salud del sistema
     */
    public function checkSystemHealth(): array;

    /**
     * Obtener métricas de rendimiento
     * 
     * @param string $timeframe Marco temporal
     * @return array Métricas de rendimiento
     */
    public function getPerformanceMetrics(string $timeframe = '24h'): array;

    /**
     * Obtener notificaciones con errores recurrentes
     * 
     * @param int $errorThreshold Umbral de errores
     * @param string $timeframe Marco temporal
     * @return Collection Notificaciones problemáticas
     */
    public function getProblematicNotifications(int $errorThreshold = 5, string $timeframe = '1h'): Collection;

    /**
     * Generar reporte de estado del sistema
     * 
     * @return array Reporte completo del sistema
     */
    public function generateSystemStatusReport(): array;

    // ===========================================
    // Integration & Webhooks
    // ===========================================

    /**
     * Registrar webhook de terceros
     * 
     * @param array $webhookData Datos del webhook
     * @return array Webhook registrado
     */
    public function registerWebhook(array $webhookData): array;

    /**
     * Procesar webhook entrante
     * 
     * @param string $provider Proveedor del webhook
     * @param array $payload Datos recibidos
     * @return array Resultado del procesamiento
     */
    public function processIncomingWebhook(string $provider, array $payload): array;

    /**
     * Obtener configuración de integraciones
     * 
     * @param string|null $provider Proveedor específico
     * @return array Configuraciones de integración
     */
    public function getIntegrationConfigs(?string $provider = null): array;

    /**
     * Actualizar estado de integración
     * 
     * @param string $provider Proveedor
     * @param string $status Nuevo estado
     * @param array $metadata Metadata adicional
     * @return bool Éxito de la actualización
     */
    public function updateIntegrationStatus(string $provider, string $status, array $metadata = []): bool;

    // ===========================================
    // Email Specific Methods
    // ===========================================

    /**
     * Obtener dominios de email desechables
     * 
     * @return array Lista de dominios desechables
     */
    public function getDisposableEmailDomains(): array;

    /**
     * Obtener dominios de email en lista negra
     * 
     * @return array Lista de dominios en lista negra
     */
    public function getBlacklistedEmailDomains(): array;

    /**
     * Verificar si un email está suprimido
     * 
     * @param string $email Email a verificar
     * @return bool Si está suprimido
     */
    public function isEmailSuppressed(string $email): bool;

    /**
     * Obtener preferencias de email del usuario
     * 
     * @param string $userId ID del usuario
     * @return array Preferencias de email
     */
    public function getUserEmailPreferences(string $userId): array;

    /**
     * Actualizar preferencias de email del usuario
     * 
     * @param string $userId ID del usuario
     * @param array $preferences Preferencias a actualizar
     * @return bool Éxito de la operación
     */
    public function updateEmailPreferences(string $userId, array $preferences): bool;

    /**
     * Registrar cancelación de suscripción
     * 
     * @param array $unsubscribeData Datos de cancelación
     * @return bool Éxito de la operación
     */
    public function logUnsubscribe(array $unsubscribeData): bool;

    /**
     * Crear email programado
     * 
     * @param array $emailData Datos del email programado
     * @return array Email programado creado
     */
    public function createScheduledEmail(array $emailData): array;

    /**
     * Obtener estadísticas de email
     * 
     * @param array $filters Filtros para las estadísticas
     * @param string $period Período de análisis
     * @return array Estadísticas de email
     */
    public function getEmailStatistics(array $filters = [], string $period = 'week'): array;

    /**
     * Obtener rendimiento de proveedores
     * 
     * @return array Rendimiento por proveedor
     */
    public function getProviderPerformance(): array;

    /**
     * Obtener resultados de A/B tests
     * 
     * @param array $filters Filtros para los resultados
     * @return array Resultados de A/B tests
     */
    public function getABTestResults(array $filters = []): array;

    // ===========================================
    // SMS Specific Methods
    // ===========================================

    /**
     * Crear registro de SMS
     * 
     * @param array $smsData Datos del SMS
     * @return array Registro de SMS creado
     */
    public function createSMSRecord(array $smsData): array;

    /**
     * Agregar número a lista de supresión SMS
     * 
     * @param string $phoneNumber Número telefónico
     * @param array $metadata Metadata de supresión
     * @return bool Éxito de la operación
     */
    public function addToSMSSuppressionList(string $phoneNumber, array $metadata): bool;

    /**
     * Obtener estadísticas de SMS
     * 
     * @param array $filters Filtros para las estadísticas
     * @param string $period Período de análisis
     * @return array Estadísticas de SMS
     */
    public function getSMSStatistics(array $filters = [], string $period = 'week'): array;

    // ===========================================
    // Push Notification Specific Methods
    // ===========================================

    /**
     * Obtener tokens de múltiples usuarios para envío masivo
     * 
     * @param array $userIds IDs de usuarios
     * @param string $channel Canal específico
     * @return array Tokens de usuarios
     */
    public function getBulkUserTokens(array $userIds, string $channel): array;

    /**
     * Buscar token por valor
     * 
     * @param string $token Valor del token
     * @return array|null Datos del token
     */
    public function findTokenByValue(string $token): ?array;

    /**
     * Actualizar usuario de un token
     * 
     * @param string $token Token a actualizar
     * @param int $userId Nuevo ID de usuario
     * @return bool Éxito de la operación
     */
    public function updateTokenUser(string $token, string $userId): bool;

    /**
     * Actualizar actividad de token
     * 
     * @param string $token Token a actualizar
     * @return bool Éxito de la operación
     */
    public function updateTokenActivity(string $token): bool;

    /**
     * Crear token push
     * 
     * @param array $tokenData Datos del token
     * @return array Token creado
     */
    public function createPushToken(array $tokenData): array;

    /**
     * Desactivar token push
     * 
     * @param string $token Token a desactivar
     * @param int|null $userId ID del usuario (opcional)
     * @return bool Éxito de la operación
     */
    public function deactivatePushToken(string $token, ?string $userId = null): bool;

    /**
     * Obtener tokens push de usuario
     * 
     * @param string $userId ID del usuario
     * @param string $status Estado del token
     * @return array Tokens del usuario
     */
    public function getUserPushTokens(string $userId, string $status): array;

    /**
     * Obtener estadísticas de push notifications
     * 
     * @param array $filters Filtros para las estadísticas
     * @param string $period Período de análisis
     * @return array Estadísticas de push
     */
    public function getPushStatistics(array $filters = [], string $period = 'week'): array;

    // ===========================================
    // Métodos adicionales requeridos por NotificationService
    // ===========================================

    /**
     * Verificar si un usuario existe por ID
     * 
     * @param string $userId ID del usuario
     * @return bool True si existe
     */
    public function existsById(string $userId): bool;

    /**
     * Buscar notificación por ID y usuario
     * 
     * @param int $notificationId ID de la notificación
     * @param string $userId ID del usuario
     * @return array|null Notificación encontrada
     */
    public function findByIdAndUser(int $notificationId, string $userId): ?array;

    /**
     * Obtener notificaciones con filtros
     * 
     * @param array $filters Filtros de búsqueda
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator Notificaciones paginadas
     */
    public function getNotifications(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Obtener conteo de notificaciones no leídas
     * 
     * @param string $userId ID del usuario
     * @return int Conteo de no leídas
     */
    public function getUnreadCount(string $userId): int;

    /**
     * Obtener estadísticas de notificaciones
     * 
     * @param array $filters Filtros para estadísticas
     * @param string $period Período de análisis
     * @return array Estadísticas
     */
    public function getStatistics(array $filters = [], string $period = 'week'): array;

    /**
     * Crear notificación programada
     * 
     * @param array $data Datos de la notificación programada
     * @return array Notificación programada creada
     */
    public function createScheduled(array $data): array;

    /**
     * Obtener notificaciones programadas listas para envío
     * 
     * @return Collection Notificaciones listas
     */
    public function getReadyScheduledNotifications(): Collection;

    /**
     * Marcar notificación programada como enviada
     * 
     * @param int $scheduledId ID de la notificación programada
     * @param int $notificationId ID de la notificación enviada
     * @return bool Éxito de la operación
     */
    public function markScheduledAsSent(int $scheduledId, int $notificationId): bool;

    /**
     * Cancelar notificación programada
     * 
     * @param int $scheduledNotificationId ID de la notificación programada
     * @param string $userId ID del usuario
     * @return bool Éxito de la operación
     */
    public function cancelScheduled(int $scheduledNotificationId, string $userId): bool;
}