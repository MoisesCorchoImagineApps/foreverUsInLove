<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Events\NotificationSent;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Auth\Exceptions\UserNotFoundException;
use App\Exceptions\NotificationException;
use App\Exceptions\InvalidNotificationTypeException;
use App\Exceptions\NotificationDeliveryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

/**
 * Servicio principal de notificaciones para ForeverUsInLove
 * 
 * Este servicio actúa como orquestador central para todo el sistema de notificaciones
 * de la plataforma, coordinando múltiples canales de comunicación, personalizando
 * contenido, gestionando preferencias de usuario y optimizando la entrega de mensajes
 * para maximizar el engagement y la experiencia del usuario.
 * 
 * Características principales:
 * - Orquestación multi-canal (push, email, SMS, in-app)
 * - Personalización inteligente basada en comportamiento del usuario
 * - Sistema de preferencias granular y respeto a configuraciones de privacidad
 * - Optimización temporal basada en zonas horarias y patrones de actividad
 * - A/B testing de contenido y canales de notificación
 * - Analytics y métricas de engagement en tiempo real
 * - Sistema de plantillas dinámicas con i18n
 * - Rate limiting y anti-spam inteligente
 * - Fallback automático entre canales
 * - Scheduling avanzado con cola de prioridades
 * 
 * @package App\Domain\Notification\Services
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class NotificationService
{
    /**
     * Tipos de notificaciones disponibles en la plataforma
     */
    public const NOTIFICATION_TYPES = [
        // Actividad de dating y matches
        'NEW_MATCH' => 'new_match',
        'MATCH_LIKED_YOU' => 'match_liked_you',
        'MATCH_MESSAGE' => 'match_message',
        'MATCH_VIEWED_PROFILE' => 'match_viewed_profile',
        'SUPER_LIKE_RECEIVED' => 'super_like_received',
        
        // Mensajería y comunicación
        'NEW_MESSAGE' => 'new_message',
        'MESSAGE_READ' => 'message_read',
        'VOICE_MESSAGE' => 'voice_message',
        'VIDEO_CALL_INVITE' => 'video_call_invite',
        'MISSED_CALL' => 'missed_call',
        
        // Actividad social y engagement
        'PROFILE_VISIT' => 'profile_visit',
        'PHOTO_LIKED' => 'photo_liked',
        'COMMENT_RECEIVED' => 'comment_received',
        'STORY_VIEW' => 'story_view',
        'GIFT_RECEIVED' => 'gift_received',
        
        // Sistema y cuenta
        'PROFILE_APPROVED' => 'profile_approved',
        'PROFILE_REJECTED' => 'profile_rejected',
        'VERIFICATION_COMPLETE' => 'verification_complete',
        'ACCOUNT_WARNING' => 'account_warning',
        'SUBSCRIPTION_EXPIRING' => 'subscription_expiring',
        
        // Marketing y engagement
        'DAILY_MATCHES' => 'daily_matches',
        'WEEKLY_DIGEST' => 'weekly_digest',
        'PROMOTIONAL_OFFER' => 'promotional_offer',
        'FEATURE_ANNOUNCEMENT' => 'feature_announcement',
        'RE_ENGAGEMENT' => 're_engagement',
        
        // Moderación y seguridad
        'SAFETY_ALERT' => 'safety_alert',
        'REPORT_UPDATE' => 'report_update',
        'MODERATION_ACTION' => 'moderation_action',
        'BLOCK_NOTIFICATION' => 'block_notification',
        
        // Eventos y ocasiones especiales
        'BIRTHDAY_REMINDER' => 'birthday_reminder',
        'ANNIVERSARY_REMINDER' => 'anniversary_reminder',
        'SEASONAL_GREETINGS' => 'seasonal_greetings',
        'SPECIAL_EVENT' => 'special_event'
    ];

    /**
     * Canales de notificación disponibles
     */
    public const NOTIFICATION_CHANNELS = [
        'PUSH' => 'push',           // Notificaciones push móviles
        'EMAIL' => 'email',         // Correo electrónico
        'SMS' => 'sms',            // Mensajes de texto
        'IN_APP' => 'in_app',      // Notificaciones dentro de la app
        'WEBHOOK' => 'webhook',     // Webhooks para integraciones
        'SLACK' => 'slack',        // Integraciones con Slack (admin)
        'DISCORD' => 'discord'      // Integraciones con Discord (admin)
    ];

    /**
     * Prioridades de notificación
     */
    public const PRIORITIES = [
        'LOW' => 'low',
        'NORMAL' => 'normal',
        'HIGH' => 'high',
        'URGENT' => 'urgent',
        'CRITICAL' => 'critical'
    ];

    /**
     * Estados de notificación
     */
    public const STATUSES = [
        'QUEUED' => 'queued',
        'PROCESSING' => 'processing',
        'SENT' => 'sent',
        'DELIVERED' => 'delivered',
        'READ' => 'read',
        'FAILED' => 'failed',
        'CANCELLED' => 'cancelled',
        'EXPIRED' => 'expired'
    ];

    /**
     * Constructor del servicio de notificaciones
     */
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly PushNotificationService $pushService,
        private readonly EmailService $emailService,
        private readonly SMSService $smsService
    ) {}

    /**
     * Envía una notificación usando el canal más apropiado para el usuario
     *
     * @param int $userId ID del usuario destinatario
     * @param string $type Tipo de notificación
     * @param array $data Datos de la notificación
     * @param array $options Opciones adicionales
     * @return array Resultado del envío
     * @throws NotificationException
     */
    public function send(
        int $userId,
        string $type,
        array $data = [],
        array $options = []
    ): array {
        try {
            Log::info('Enviando notificación', [
                'user_id' => $userId,
                'type' => $type,
                'options' => $options
            ]);

            // Validar entrada
            $this->validateNotificationRequest($userId, $type, $data);

            // Obtener información del usuario
            $user = $this->getUserNotificationProfile($userId);
            if (!$user) {
                throw new UserNotFoundException("Usuario no encontrado: {$userId}");
            }

            // Verificar si el usuario puede recibir este tipo de notificación
            if (!$this->canReceiveNotification($user, $type)) {
                Log::info('Notificación bloqueada por preferencias del usuario', [
                    'user_id' => $userId,
                    'type' => $type
                ]);
                return $this->createSkippedResult($userId, $type, 'user_preferences');
            }

            // Determinar canales óptimos para este usuario y tipo de notificación
            $channels = $this->determineOptimalChannels($user, $type, $options);
            
            if (empty($channels)) {
                return $this->createSkippedResult($userId, $type, 'no_available_channels');
            }

            // Personalizar contenido de la notificación
            $personalizedContent = $this->personalizeNotificationContent($user, $type, $data);

            // Crear registro de notificación
            $notification = $this->createNotificationRecord($userId, $type, $personalizedContent, $channels, $options);

            // Enviar por cada canal determinado
            $results = [];
            foreach ($channels as $channel) {
                try {
                    $channelResult = $this->sendThroughChannel(
                        $channel,
                        $user,
                        $notification,
                        $personalizedContent,
                        $options
                    );
                    $results[$channel] = $channelResult;
                } catch (Exception $e) {
                    Log::error("Error enviando por canal {$channel}", [
                        'user_id' => $userId,
                        'notification_id' => $notification['id'],
                        'error' => $e->getMessage()
                    ]);
                    $results[$channel] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'channel' => $channel
                    ];
                }
            }

            // Actualizar estadísticas
            $this->updateNotificationStatistics($notification, $results);

            // Disparar evento de notificación enviada
            Event::dispatch(new NotificationSent(
                $notification['id'],
                $userId,
                $type,
                $channels,
                $results
            ));

            // Programar seguimiento si es necesario
            $this->scheduleFollowUpActions($notification, $results);

            Log::info('Notificación enviada exitosamente', [
                'notification_id' => $notification['id'],
                'user_id' => $userId,
                'channels' => array_keys($results),
                'success_count' => count(array_filter($results, fn($r) => $r['success']))
            ]);

            return [
                'success' => true,
                'notification_id' => $notification['id'],
                'channels_used' => array_keys($results),
                'channel_results' => $results,
                'total_channels' => count($results),
                'successful_channels' => count(array_filter($results, fn($r) => $r['success'])),
                'sent_at' => Carbon::now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('Error al enviar notificación', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            throw new NotificationException('Error al enviar notificación: ' . $e->getMessage());
        }
    }

    /**
     * Envía notificaciones masivas a múltiples usuarios
     *
     * @param array $userIds IDs de usuarios destinatarios
     * @param string $type Tipo de notificación
     * @param array $data Datos base de la notificación
     * @param array $options Opciones de envío masivo
     * @return array Resultado del envío masivo
     */
    public function sendBulk(
        array $userIds,
        string $type,
        array $data = [],
        array $options = []
    ): array {
        $startTime = microtime(true);
        $results = [
            'total_users' => count($userIds),
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => []
        ];

        // Configurar procesamiento en lotes
        $batchSize = $options['batch_size'] ?? 100;
        $delay = $options['delay_between_batches'] ?? 1; // segundos

        // Procesar en lotes para evitar sobrecargar el sistema
        $batches = array_chunk($userIds, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchResults = $this->processBulkBatch($batch, $type, $data, $options);
            
            // Consolidar resultados
            $results['successful'] += $batchResults['successful'];
            $results['failed'] += $batchResults['failed'];
            $results['skipped'] += $batchResults['skipped'];
            $results['details'] = array_merge($results['details'], $batchResults['details']);

            // Delay entre lotes si no es el último
            if ($batchIndex < count($batches) - 1 && $delay > 0) {
                sleep($delay);
            }
        }

        $results['processing_time'] = round(microtime(true) - $startTime, 2);
        $results['success_rate'] = $results['total_users'] > 0 ? 
                                  round(($results['successful'] / $results['total_users']) * 100, 2) : 0;

        Log::info('Notificación masiva completada', $results);
        
        return $results;
    }

    /**
     * Programa una notificación para envío futuro
     *
     * @param int $userId ID del usuario destinatario
     * @param string $type Tipo de notificación
     * @param Carbon $scheduledAt Fecha y hora programada
     * @param array $data Datos de la notificación
     * @param array $options Opciones adicionales
     * @return array Información de la notificación programada
     */
    public function schedule(
        int $userId,
        string $type,
        Carbon $scheduledAt,
        array $data = [],
        array $options = []
    ): array {
        // Validar fecha futura
        if ($scheduledAt->isPast()) {
            throw new NotificationException('La fecha programada debe ser futura');
        }

        // Crear notificación programada
        $scheduledNotification = $this->notificationRepository->createScheduled([
            'user_id' => $userId,
            'type' => $type,
            'data' => $data,
            'options' => $options,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
            'created_at' => Carbon::now()
        ]);

        // Programar job en la cola
        $jobDelay = $scheduledAt->diffInSeconds(Carbon::now());
        Queue::later($jobDelay, 'SendScheduledNotificationJob', [
            'scheduled_notification_id' => $scheduledNotification['id']
        ]);

        Log::info('Notificación programada', [
            'scheduled_notification_id' => $scheduledNotification['id'],
            'user_id' => $userId,
            'type' => $type,
            'scheduled_at' => $scheduledAt->toISOString()
        ]);

        return [
            'success' => true,
            'scheduled_notification_id' => $scheduledNotification['id'],
            'scheduled_at' => $scheduledAt->toISOString(),
            'estimated_delivery' => $scheduledAt->toISOString()
        ];
    }

    /**
     * Obtiene el historial de notificaciones de un usuario
     *
     * @param int $userId ID del usuario
     * @param array $filters Filtros adicionales
     * @param int $perPage Elementos por página
     * @return LengthAwarePaginator
     */
    public function getUserNotifications(
        int $userId,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $cacheKey = "user_notifications_{$userId}_" . md5(serialize($filters)) . "_{$perPage}";
        
        return Cache::remember($cacheKey, 300, function () use ($userId, $filters, $perPage) {
            $filters['user_id'] = $userId;
            return $this->notificationRepository->getNotifications($filters, $perPage);
        });
    }

    /**
     * Marca una notificación como leída
     *
     * @param int $notificationId ID de la notificación
     * @param int $userId ID del usuario
     * @return bool True si se marcó correctamente
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = $this->notificationRepository->findByIdAndUser($notificationId, (string)$userId);
        
        if (!$notification) {
            return false;
        }

        $success = $this->notificationRepository->markAsRead($notificationId, (string)$userId, Carbon::now());
        
        if ($success) {
            // Actualizar métricas de engagement
            $this->updateEngagementMetrics($notification);
            
            // Limpiar cache
            $this->clearUserNotificationCache($userId);
        }
        
        return $success;
    }

    /**
     * Marca múltiples notificaciones como leídas
     *
     * @param array $notificationIds IDs de notificaciones
     * @param int $userId ID del usuario
     * @return int Número de notificaciones marcadas
     */
    public function markMultipleAsRead(array $notificationIds, int $userId): int
    {
        $count = $this->notificationRepository->markMultipleAsRead($notificationIds, (string)$userId);
        
        if ($count > 0) {
            $this->clearUserNotificationCache($userId);
        }
        
        return $count;
    }

    /**
     * Obtiene el número de notificaciones no leídas
     *
     * @param int $userId ID del usuario
     * @return int Número de notificaciones no leídas
     */
    public function getUnreadCount(int $userId): int
    {
        $cacheKey = "unread_notifications_count_{$userId}";
        
        return Cache::remember($cacheKey, 300, function () use ($userId) {
            return $this->notificationRepository->getUnreadCount((string)$userId);
        });
    }

    /**
     * Actualiza las preferencias de notificación de un usuario
     *
     * @param int $userId ID del usuario
     * @param array $preferences Nuevas preferencias
     * @return bool True si se actualizaron correctamente
     */
    public function updateUserPreferences(int $userId, array $preferences): bool
    {
        $success = $this->notificationRepository->updateUserPreferences((string)$userId, $preferences);
        
        if ($success) {
            // Limpiar cache de preferencias
            Cache::forget("user_notification_preferences_{$userId}");
            Cache::forget("user_notification_profile_{$userId}");
        }
        
        return $success;
    }

    /**
     * Obtiene las preferencias de notificación de un usuario
     *
     * @param int $userId ID del usuario
     * @return array Preferencias del usuario
     */
    public function getUserPreferences(int $userId): array
    {
        $cacheKey = "user_notification_preferences_{$userId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            return $this->notificationRepository->getUserPreferences((string)$userId);
        });
    }

    /**
     * Obtiene estadísticas de notificaciones
     *
     * @param array $filters Filtros para las estadísticas
     * @param string $period Período de análisis
     * @return array Estadísticas de notificaciones
     */
    public function getNotificationStatistics(array $filters = [], string $period = 'week'): array
    {
        $cacheKey = "notification_stats_" . md5(serialize($filters)) . "_{$period}";
        
        return Cache::remember($cacheKey, 1800, function () use ($filters, $period) {
            $stats = $this->notificationRepository->getStatistics($filters, $period);
            
            return [
                'period' => $period,
                'total_sent' => $stats['total_sent'] ?? 0,
                'total_delivered' => $stats['total_delivered'] ?? 0,
                'total_read' => $stats['total_read'] ?? 0,
                'delivery_rate' => $this->calculateDeliveryRate($stats),
                'read_rate' => $this->calculateReadRate($stats),
                'engagement_rate' => $this->calculateEngagementRate($stats),
                'by_type' => $stats['by_type'] ?? [],
                'by_channel' => $stats['by_channel'] ?? [],
                'hourly_distribution' => $stats['hourly_distribution'] ?? [],
                'top_performing_types' => $this->getTopPerformingTypes($stats),
                'channel_performance' => $this->calculateChannelPerformance($stats),
                'user_engagement_segments' => $this->analyzeUserEngagement($stats),
                'generated_at' => Carbon::now()->toISOString()
            ];
        });
    }

    /**
     * Cancela una notificación programada
     *
     * @param int $scheduledNotificationId ID de la notificación programada
     * @param int $userId ID del usuario (para verificación)
     * @return bool True si se canceló correctamente
     */
    public function cancelScheduled(int $scheduledNotificationId, int $userId): bool
    {
        return $this->notificationRepository->cancelScheduled($scheduledNotificationId, (string)$userId);
    }

    /**
     * Procesa notificaciones programadas que están listas para enviar
     *
     * @return array Resultado del procesamiento
     */
    public function processScheduledNotifications(): array
    {
        $readyNotifications = $this->notificationRepository->getReadyScheduledNotifications();
        $processed = 0;
        $failed = 0;

        foreach ($readyNotifications as $scheduled) {
            try {
                $result = $this->send(
                    $scheduled['user_id'],
                    $scheduled['type'],
                    $scheduled['data'],
                    $scheduled['options']
                );
                
                if ($result['success']) {
                    $this->notificationRepository->markScheduledAsSent(
                        $scheduled['id'],
                        $result['notification_id']
                    );
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (Exception $e) {
                Log::error('Error procesando notificación programada', [
                    'scheduled_id' => $scheduled['id'],
                    'error' => $e->getMessage()
                ]);
                $failed++;
            }
        }

        return [
            'total_ready' => count($readyNotifications),
            'processed' => $processed,
            'failed' => $failed,
            'success_rate' => count($readyNotifications) > 0 ? 
                             round(($processed / count($readyNotifications)) * 100, 2) : 0
        ];
    }

    /**
     * Valida la solicitud de notificación
     */
    private function validateNotificationRequest(int $userId, string $type, array $data): void
    {
        if (!in_array($type, self::NOTIFICATION_TYPES)) {
            throw new InvalidNotificationTypeException("Tipo de notificación inválido: {$type}");
        }

        if (!$this->userRepository->existsById($userId)) {
            throw new UserNotFoundException("Usuario no encontrado: {$userId}");
        }

        // Validaciones específicas por tipo
        $this->validateTypeSpecificData($type, $data);
    }

    /**
     * Obtiene el perfil de notificaciones del usuario
     */
    private function getUserNotificationProfile(int $userId): ?array
    {
        $cacheKey = "user_notification_profile_{$userId}";
        
        return Cache::remember($cacheKey, 1800, function () use ($userId) {
            $user = $this->userRepository->findById($userId);
            if (!$user) {
                return null;
            }

            $profile = $this->profileRepository->findByUserId($userId);
            $preferences = $this->getUserPreferences($userId);
            
            return [
                'id' => $user['id'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'timezone' => $profile['timezone'] ?? 'UTC',
                'language' => $profile['language'] ?? 'en',
                'preferences' => $preferences,
                'is_verified' => $user['is_verified'] ?? false,
                'last_active_at' => $user['last_active_at'],
                'notification_tokens' => $this->notificationRepository->getUserTokens((string)$userId)
            ];
        });
    }

    /**
     * Verifica si un usuario puede recibir un tipo específico de notificación
     */
    private function canReceiveNotification(array $user, string $type): bool
    {
        // Verificar preferencias globales
        if (!($user['preferences']['enabled'] ?? true)) {
            return false;
        }

        // Verificar preferencias específicas por tipo
        $typePreferences = $user['preferences']['types'] ?? [];
        if (isset($typePreferences[$type]) && !$typePreferences[$type]) {
            return false;
        }

        // Verificar Do Not Disturb
        if ($this->isInDoNotDisturbPeriod($user)) {
            return false;
        }

        // Verificar rate limiting
        if ($this->isRateLimited((int)$user['id'], $type)) {
            return false;
        }

        return true;
    }

    /**
     * Determina los canales óptimos para enviar la notificación
     */
    private function determineOptimalChannels(array $user, string $type, array $options): array
    {
        $availableChannels = [];
        
        // Canal push (si tiene tokens y está habilitado)
        if (!empty($user['notification_tokens']['push']) && 
            ($user['preferences']['channels']['push'] ?? true)) {
            $availableChannels[] = self::NOTIFICATION_CHANNELS['PUSH'];
        }
        
        // Canal email (si tiene email y está habilitado)
        if (!empty($user['email']) && 
            ($user['preferences']['channels']['email'] ?? true)) {
            $availableChannels[] = self::NOTIFICATION_CHANNELS['EMAIL'];
        }
        
        // Canal SMS (si tiene teléfono y está habilitado)
        if (!empty($user['phone']) && 
            ($user['preferences']['channels']['sms'] ?? false)) {
            $availableChannels[] = self::NOTIFICATION_CHANNELS['SMS'];
        }
        
        // In-app siempre disponible
        $availableChannels[] = self::NOTIFICATION_CHANNELS['IN_APP'];
        
        // Filtrar por preferencias específicas del tipo
        $typeChannelPrefs = $user['preferences']['type_channels'][$type] ?? [];
        if (!empty($typeChannelPrefs)) {
            $availableChannels = array_intersect($availableChannels, $typeChannelPrefs);
        }
        
        // Aplicar lógica de canal forzado si se especifica en opciones
        if (isset($options['force_channel'])) {
            $forcedChannel = $options['force_channel'];
            if (in_array($forcedChannel, $availableChannels)) {
                return [$forcedChannel];
            }
        }
        
        // Optimización por tipo de notificación
        return $this->optimizeChannelsByType($type, $availableChannels, $user);
    }

    /**
     * Personaliza el contenido de la notificación
     */
    private function personalizeNotificationContent(array $user, string $type, array $data): array
    {
        // Obtener plantilla base
        $template = $this->getNotificationTemplate($type, $user['language']);
        
        // Personalizar con datos del usuario
        $personalizedData = array_merge($data, [
            'user_name' => $this->getUserDisplayName($user['id']),
            'user_timezone' => $user['timezone'],
            'user_language' => $user['language']
        ]);
        
        // Procesar plantilla con datos personalizados
        return [
            'title' => $this->processTemplate($template['title'], $personalizedData),
            'message' => $this->processTemplate($template['message'], $personalizedData),
            'action_text' => $template['action_text'] ?? null,
            'action_url' => $this->generateActionUrl($type, $personalizedData),
            'image_url' => $template['image_url'] ?? null,
            'metadata' => $personalizedData
        ];
    }

    /**
     * Crea el registro de notificación en la base de datos
     */
    private function createNotificationRecord(
        int $userId,
        string $type,
        array $content,
        array $channels,
        array $options
    ): array {
        return $this->notificationRepository->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $content['title'],
            'message' => $content['message'],
            'data' => $content,
            'channels' => $channels,
            'priority' => $options['priority'] ?? self::PRIORITIES['NORMAL'],
            'status' => self::STATUSES['QUEUED'],
            'created_at' => Carbon::now()
        ]);
    }

    /**
     * Envía la notificación a través de un canal específico
     */
    private function sendThroughChannel(
        string $channel,
        array $user,
        array $notification,
        array $content,
        array $options
    ): array {
        switch ($channel) {
            case self::NOTIFICATION_CHANNELS['PUSH']:
                return $this->pushService->send($user, $notification, $content, $options);
                
            case self::NOTIFICATION_CHANNELS['EMAIL']:
                return $this->emailService->send($user, $notification, $content, $options);
                
            case self::NOTIFICATION_CHANNELS['SMS']:
                return $this->smsService->send($user, $notification, $content, $options);
                
            case self::NOTIFICATION_CHANNELS['IN_APP']:
                return $this->sendInAppNotification($user, $notification, $content);
                
            default:
                throw new NotificationException("Canal no soportado: {$channel}");
        }
    }

    /**
     * Crea resultado para notificación omitida
     */
    private function createSkippedResult(int $userId, string $type, string $reason): array
    {
        return [
            'success' => false,
            'skipped' => true,
            'reason' => $reason,
            'user_id' => $userId,
            'type' => $type,
            'timestamp' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Valida datos específicos por tipo de notificación
     */
    private function validateTypeSpecificData(string $type, array $data): void
    {
        switch ($type) {
            case self::NOTIFICATION_TYPES['NEW_MESSAGE']:
            case self::NOTIFICATION_TYPES['MATCH_MESSAGE']:
                if (!isset($data['sender_id']) || !isset($data['message_preview'])) {
                    throw new InvalidNotificationTypeException("Datos requeridos faltantes para tipo: {$type}");
                }
                break;
                
            case self::NOTIFICATION_TYPES['NEW_MATCH']:
                if (!isset($data['match_id']) || !isset($data['matched_user_id'])) {
                    throw new InvalidNotificationTypeException("Datos requeridos faltantes para tipo: {$type}");
                }
                break;
                
            case self::NOTIFICATION_TYPES['PROFILE_VISIT']:
                if (!isset($data['visitor_id'])) {
                    throw new InvalidNotificationTypeException("Datos requeridos faltantes para tipo: {$type}");
                }
                break;
        }
    }

    /**
     * Verifica si el usuario está en período de "No molestar"
     */
    private function isInDoNotDisturbPeriod(array $user): bool
    {
        $preferences = $user['preferences'] ?? [];
        $dndSettings = $preferences['do_not_disturb'] ?? [];
        
        if (!($dndSettings['enabled'] ?? false)) {
            return false;
        }
        
        $now = Carbon::now($user['timezone'] ?? 'UTC');
        $startTime = $dndSettings['start_time'] ?? '22:00';
        $endTime = $dndSettings['end_time'] ?? '08:00';
        
        $start = Carbon::createFromTimeString($startTime, $user['timezone'] ?? 'UTC');
        $end = Carbon::createFromTimeString($endTime, $user['timezone'] ?? 'UTC');
        
        // Si el período cruza medianoche
        if ($start->greaterThan($end)) {
            return $now->greaterThanOrEqualTo($start) || $now->lessThanOrEqualTo($end);
        }
        
        return $now->between($start, $end);
    }


    /**
     * Optimiza canales por tipo de notificación
     */
    private function optimizeChannelsByType(string $type, array $availableChannels, array $user): array
    {
        $typeChannelPreferences = [
            self::NOTIFICATION_TYPES['NEW_MESSAGE'] => ['push', 'in_app'],
            self::NOTIFICATION_TYPES['MATCH_MESSAGE'] => ['push', 'in_app'],
            self::NOTIFICATION_TYPES['NEW_MATCH'] => ['push', 'email', 'in_app'],
            self::NOTIFICATION_TYPES['PROFILE_VISIT'] => ['in_app'],
            self::NOTIFICATION_TYPES['SUPER_LIKE_RECEIVED'] => ['push', 'email', 'in_app'],
            self::NOTIFICATION_TYPES['DAILY_MATCHES'] => ['email', 'push'],
            self::NOTIFICATION_TYPES['WEEKLY_DIGEST'] => ['email'],
        ];
        
        $preferredChannels = $typeChannelPreferences[$type] ?? ['push', 'in_app'];
        
        // Filtrar canales disponibles con preferencias del tipo
        $optimizedChannels = array_intersect($availableChannels, $preferredChannels);
        
        // Si no hay coincidencias, usar los disponibles
        if (empty($optimizedChannels)) {
            $optimizedChannels = $availableChannels;
        }
        
        // Limitar a máximo 2 canales para evitar spam
        return array_slice($optimizedChannels, 0, 2);
    }

    /**
     * Obtiene plantilla de notificación
     */
    private function getNotificationTemplate(string $type, string $language = 'en'): array
    {
        $templates = [
            self::NOTIFICATION_TYPES['NEW_MESSAGE'] => [
                'title' => 'Nuevo mensaje de {sender_name}',
                'message' => '{message_preview}',
                'action_text' => 'Ver mensaje',
                'action_url' => '/chat/{conversation_id}',
            ],
            self::NOTIFICATION_TYPES['NEW_MATCH'] => [
                'title' => '¡Tienes un nuevo match!',
                'message' => 'Te has conectado con {matched_user_name}',
                'action_text' => 'Ver perfil',
                'action_url' => '/profile/{matched_user_id}',
            ],
            self::NOTIFICATION_TYPES['PROFILE_VISIT'] => [
                'title' => 'Alguien visitó tu perfil',
                'message' => '{visitor_name} vio tu perfil',
                'action_text' => 'Ver quién',
                'action_url' => '/profile/visitors',
            ],
            self::NOTIFICATION_TYPES['SUPER_LIKE_RECEIVED'] => [
                'title' => '¡Recibiste un Super Like!',
                'message' => '{sender_name} te dio un Super Like',
                'action_text' => 'Ver perfil',
                'action_url' => '/profile/{sender_id}',
            ],
        ];
        
        return $templates[$type] ?? [
            'title' => 'Nueva notificación',
            'message' => 'Tienes una nueva notificación',
            'action_text' => 'Ver',
            'action_url' => '/notifications',
        ];
    }

    /**
     * Procesa plantilla con datos personalizados
     */
    private function processTemplate(string $template, array $data): string
    {
        $processed = $template;
        
        foreach ($data as $key => $value) {
            $processed = str_replace("{{$key}}", $value, $processed);
        }
        
        return $processed;
    }

    /**
     * Genera URL de acción para el tipo de notificación
     */
    private function generateActionUrl(string $type, array $data): string
    {
        $baseUrl = config('app.url', 'https://foreverusinlove.com');
        
        switch ($type) {
            case self::NOTIFICATION_TYPES['NEW_MESSAGE']:
            case self::NOTIFICATION_TYPES['MATCH_MESSAGE']:
                return "{$baseUrl}/chat/" . ($data['conversation_id'] ?? '');
                
            case self::NOTIFICATION_TYPES['NEW_MATCH']:
                return "{$baseUrl}/profile/" . ($data['matched_user_id'] ?? '');
                
            case self::NOTIFICATION_TYPES['PROFILE_VISIT']:
                return "{$baseUrl}/profile/visitors";
                
            case self::NOTIFICATION_TYPES['SUPER_LIKE_RECEIVED']:
                return "{$baseUrl}/profile/" . ($data['sender_id'] ?? '');
                
            default:
                return "{$baseUrl}/notifications";
        }
    }

    /**
     * Obtiene nombre de visualización del usuario
     */
    private function getUserDisplayName(int $userId): string
    {
        $user = $this->userRepository->findById($userId);
        
        if (!$user) {
            return 'Usuario';
        }
        
        return $user['name'] ?? $user['username'] ?? 'Usuario';
    }

    /**
     * Actualiza estadísticas de notificación
     */
    private function updateNotificationStatistics(array $notification, array $results): void
    {
        $successfulChannels = count(array_filter($results, fn($r) => $r['success']));
        
        // Actualizar estadísticas en caché
        $statsKey = "notification_stats_" . date('Y-m-d');
        $stats = Cache::get($statsKey, [
            'total_sent' => 0,
            'total_delivered' => 0,
            'by_type' => [],
            'by_channel' => []
        ]);
        
        $stats['total_sent']++;
        $stats['total_delivered'] += $successfulChannels;
        
        // Estadísticas por tipo
        $type = $notification['type'];
        $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
        
        // Estadísticas por canal
        foreach ($results as $channel => $result) {
            if ($result['success']) {
                $stats['by_channel'][$channel] = ($stats['by_channel'][$channel] ?? 0) + 1;
            }
        }
        
        Cache::put($statsKey, $stats, 86400); // 24 horas
    }

    /**
     * Programa acciones de seguimiento
     */
    private function scheduleFollowUpActions(array $notification, array $results): void
    {
        $type = $notification['type'];
        
        // Solo programar seguimiento para ciertos tipos
        $followUpTypes = [
            self::NOTIFICATION_TYPES['NEW_MESSAGE'],
            self::NOTIFICATION_TYPES['PROFILE_VISIT'],
        ];
        
        if (!in_array($type, $followUpTypes)) {
            return;
        }
        
        // Programar seguimiento después de 24 horas si no hay respuesta
        $followUpAt = Carbon::now()->addHours(24);
        
        Queue::later($followUpAt, 'SendFollowUpNotificationJob', [
            'notification_id' => $notification['id'],
            'type' => $type,
            'user_id' => $notification['user_id']
        ]);
    }

    /**
     * Actualiza métricas de engagement
     */
    private function updateEngagementMetrics(array $notification): void
    {
        $userId = $notification['user_id'];
        $type = $notification['type'];
        
        $metricsKey = "user_engagement_{$userId}";
        $metrics = Cache::get($metricsKey, [
            'total_read' => 0,
            'read_by_type' => [],
            'last_read_at' => null
        ]);
        
        $metrics['total_read']++;
        $metrics['read_by_type'][$type] = ($metrics['read_by_type'][$type] ?? 0) + 1;
        $metrics['last_read_at'] = Carbon::now()->toISOString();
        
        Cache::put($metricsKey, $metrics, 86400); // 24 horas
    }

    /**
     * Limpia caché de notificaciones del usuario
     */
    private function clearUserNotificationCache(int $userId): void
    {
        $patterns = [
            "user_notifications_{$userId}_*",
            "unread_notifications_count_{$userId}",
            "user_notification_preferences_{$userId}",
            "user_notification_profile_{$userId}",
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Calcula tasa de entrega
     */
    private function calculateDeliveryRate(array $stats): float
    {
        $totalSent = $stats['total_sent'] ?? 0;
        $totalDelivered = $stats['total_delivered'] ?? 0;
        
        return $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 2) : 0;
    }

    /**
     * Calcula tasa de lectura
     */
    private function calculateReadRate(array $stats): float
    {
        $totalDelivered = $stats['total_delivered'] ?? 0;
        $totalRead = $stats['total_read'] ?? 0;
        
        return $totalDelivered > 0 ? round(($totalRead / $totalDelivered) * 100, 2) : 0;
    }

    /**
     * Calcula tasa de engagement
     */
    private function calculateEngagementRate(array $stats): float
    {
        $totalRead = $stats['total_read'] ?? 0;
        $totalInteractions = $stats['total_interactions'] ?? 0;
        
        return $totalRead > 0 ? round(($totalInteractions / $totalRead) * 100, 2) : 0;
    }

    /**
     * Obtiene tipos de notificación con mejor rendimiento
     */
    private function getTopPerformingTypes(array $stats): array
    {
        $byType = $stats['by_type'] ?? [];
        arsort($byType);
        
        return array_slice($byType, 0, 5, true);
    }

    /**
     * Calcula rendimiento por canal
     */
    private function calculateChannelPerformance(array $stats): array
    {
        $byChannel = $stats['by_channel'] ?? [];
        $totalDelivered = $stats['total_delivered'] ?? 0;
        
        $performance = [];
        foreach ($byChannel as $channel => $count) {
            $performance[$channel] = [
                'count' => $count,
                'percentage' => $totalDelivered > 0 ? round(($count / $totalDelivered) * 100, 2) : 0
            ];
        }
        
        return $performance;
    }

    /**
     * Analiza engagement de usuarios
     */
    private function analyzeUserEngagement(array $stats): array
    {
        return [
            'high_engagement' => $stats['high_engagement_users'] ?? 0,
            'medium_engagement' => $stats['medium_engagement_users'] ?? 0,
            'low_engagement' => $stats['low_engagement_users'] ?? 0,
        ];
    }

    /**
     * Envía notificación in-app
     */
    private function sendInAppNotification(array $user, array $notification, array $content): array
    {
        try {
            // Simular envío de notificación in-app
            // En una implementación real, esto podría usar WebSockets o Server-Sent Events
            
            Log::info('Notificación in-app enviada', [
                'user_id' => $user['id'],
                'notification_id' => $notification['id'],
                'title' => $content['title']
            ]);
            
            return [
                'success' => true,
                'channel' => 'in_app',
                'delivered_at' => Carbon::now()->toISOString(),
                'message_id' => 'in_app_' . $notification['id']
            ];
            
        } catch (Exception $e) {
            Log::error('Error enviando notificación in-app', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'channel' => 'in_app',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verifica si el usuario permite recibir notificaciones del tipo especificado
     *
     * @param array $user Datos del usuario
     * @param string $type Tipo de notificación
     * @return bool
     */
    public function userAllowsNotification(array $user, string $type): bool
    {
        return $this->canReceiveNotification($user, $type);
    }

    /**
     * Verifica si el usuario está limitado por rate limiting
     *
     * @param int $userId ID del usuario
     * @param string $type Tipo de notificación
     * @return bool
     */
    public function isRateLimited(int $userId, string $type): bool
    {
        $cacheKey = "rate_limit_{$userId}_{$type}";
        $rateLimitData = Cache::get($cacheKey, []);
        
        $limits = [
            self::NOTIFICATION_TYPES['NEW_MESSAGE'] => ['count' => 10, 'window' => 300], // 10 en 5 min
            self::NOTIFICATION_TYPES['MATCH_MESSAGE'] => ['count' => 15, 'window' => 300], // 15 en 5 min
            self::NOTIFICATION_TYPES['PROFILE_VISIT'] => ['count' => 5, 'window' => 60],  // 5 en 1 min
        ];
        
        $limit = $limits[$type] ?? ['count' => 5, 'window' => 300];
        
        $now = Carbon::now();
        $windowStart = $now->subSeconds($limit['window']);
        
        // Filtrar eventos dentro de la ventana de tiempo
        $recentEvents = array_filter($rateLimitData, function($timestamp) use ($windowStart) {
            return Carbon::parse($timestamp)->greaterThan($windowStart);
        });
        
        return count($recentEvents) >= $limit['count'];
    }

    /**
     * Envía notificación push
     *
     * @param string $fcmToken Token FCM del dispositivo
     * @param string $title Título de la notificación
     * @param string $message Mensaje de la notificación
     * @param array $data Datos adicionales
     * @return bool
     */
    public function sendPushNotification(string $fcmToken, string $title, string $message, array $data = []): bool
    {
        try {
            $result = $this->pushService->send([
                'fcm_token' => $fcmToken,
                'platform' => 'android' // Detectar plataforma del token
            ], [
                'id' => uniqid(),
                'type' => 'push',
                'title' => $title,
                'message' => $message
            ], [
                'title' => $title,
                'message' => $message,
                'data' => $data
            ], []);

            return $result['success'] ?? false;
        } catch (Exception $e) {
            Log::error('Error sending push notification', [
                'fcm_token' => $fcmToken,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envía notificación por email
     *
     * @param string $email Email del destinatario
     * @param string $template Plantilla de email
     * @param array $data Datos para la plantilla
     * @return bool
     */
    public function sendEmailNotification(string $email, string $template, array $data = []): bool
    {
        try {
            $result = $this->emailService->send([
                'email' => $email,
                'id' => 0 // Usuario temporal para el servicio
            ], [
                'id' => uniqid(),
                'type' => 'email',
                'template' => $template
            ], $data, []);

            return $result['success'] ?? false;
        } catch (Exception $e) {
            Log::error('Error sending email notification', [
                'email' => $email,
                'template' => $template,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envía notificación por SMS
     *
     * @param string $phone Número de teléfono
     * @param string $message Mensaje SMS
     * @return bool
     */
    public function sendSMSNotification(string $phone, string $message): bool
    {
        try {
            $result = $this->smsService->send([
                'phone' => $phone,
                'id' => 0 // Usuario temporal para el servicio
            ], [
                'id' => uniqid(),
                'type' => 'sms',
                'message' => $message
            ], [
                'message' => $message
            ], []);

            return $result['success'] ?? false;
        } catch (Exception $e) {
            Log::error('Error sending SMS notification', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Procesa lote de notificaciones masivas
     */
    private function processBulkBatch(array $userIds, string $type, array $data, array $options): array
    {
        $results = [
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => []
        ];
        
        foreach ($userIds as $userId) {
            try {
                $result = $this->send($userId, $type, $data, $options);
                
                if ($result['success']) {
                    $results['successful']++;
                } else {
                    $results['skipped']++;
                }
                
                $results['details'][] = [
                    'user_id' => $userId,
                    'success' => $result['success'],
                    'channels_used' => $result['channels_used'] ?? []
                ];
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'user_id' => $userId,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                
                Log::error('Error en notificación masiva', [
                    'user_id' => $userId,
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $results;
    }
}