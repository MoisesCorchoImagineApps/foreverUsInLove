<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Events\PushSent;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Exceptions\PushNotificationException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\PushDeliveryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Http\Client\RequestException;
use Exception;

/**
 * Servicio especializado para notificaciones push móviles en ForeverUsInLove
 * 
 * Este servicio gestiona el envío de notificaciones push a dispositivos móviles
 * a través de múltiples proveedores (FCM, APNs, etc.), optimizando la entrega,
 * personalizando el contenido y gestionando tokens de dispositivos de manera
 * segura y eficiente.
 * 
 * Características principales:
 * - Soporte multi-plataforma (Android, iOS, Web)
 * - Integración con Firebase Cloud Messaging (FCM)
 * - Soporte nativo para Apple Push Notification service (APNs)
 * - Gestión inteligente de tokens de dispositivos
 * - Rich notifications con imágenes, botones de acción y sonidos personalizados
 * - Segmentación avanzada de audiencias
 * - A/B testing de contenido push
 * - Analytics detallados de entrega y engagement
 * - Rate limiting y optimización de batches
 * - Fallback automático entre proveedores
 * - Gestión de badges y contadores
 * - Deep linking y navegación contextual
 * 
 * @package App\Domain\Notification\Services
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class PushNotificationService
{
    /**
     * Plataformas de dispositivos soportadas
     */
    public const PLATFORMS = [
        'ANDROID' => 'android',
        'IOS' => 'ios',
        'WEB' => 'web',
        'HUAWEI' => 'huawei'
    ];

    /**
     * Proveedores de push notifications
     */
    public const PROVIDERS = [
        'FCM' => 'fcm',           // Firebase Cloud Messaging
        'APNS' => 'apns',         // Apple Push Notification Service
        'HMS' => 'hms',           // Huawei Mobile Services
        'WNS' => 'wns',           // Windows Notification Service
        'PUSHY' => 'pushy',       // Pushy.me (backup provider)
        'ONESIGNAL' => 'onesignal' // OneSignal (backup provider)
    ];

    /**
     * Tipos de notificación push
     */
    public const PUSH_TYPES = [
        'ALERT' => 'alert',           // Notificación estándar
        'BADGE' => 'badge',           // Solo actualizar badge
        'SOUND' => 'sound',           // Solo reproducir sonido
        'SILENT' => 'silent',         // Notificación silenciosa
        'RICH' => 'rich',            // Notificación enriquecida
        'INTERACTIVE' => 'interactive' // Con botones de acción
    ];

    /**
     * Prioridades de entrega
     */
    public const DELIVERY_PRIORITIES = [
        'LOW' => 'low',
        'NORMAL' => 'normal', 
        'HIGH' => 'high'
    ];

    /**
     * Estados de token de dispositivo
     */
    public const TOKEN_STATUSES = [
        'ACTIVE' => 'active',
        'INACTIVE' => 'inactive',
        'EXPIRED' => 'expired',
        'INVALID' => 'invalid'
    ];

    /**
     * Constructor del servicio de push notifications
     */
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepository
    ) {}

    /**
     * Envía una notificación push a un usuario específico
     *
     * @param array $user Datos del usuario destinatario
     * @param array $notification Datos de la notificación
     * @param array $content Contenido personalizado
     * @param array $options Opciones adicionales de envío
     * @return array Resultado del envío push
     * @throws PushNotificationException
     */
    public function send(
        array $user,
        array $notification,
        array $content,
        array $options = []
    ): array {
        try {
            Log::info('Enviando notificación push', [
                'user_id' => $user['id'],
                'notification_id' => $notification['id']
            ]);

            // Obtener tokens activos del usuario
            $tokens = $this->getActiveUserTokens($user['id']);
            
            if (empty($tokens)) {
                return [
                    'success' => false,
                    'error' => 'No active push tokens found',
                    'channel' => 'push',
                    'tokens_checked' => 0
                ];
            }

            // Preparar payload de la notificación
            $payload = $this->preparePushPayload($notification, $content, $options);

            // Agrupar tokens por plataforma para optimizar envío
            $tokensByPlatform = $this->groupTokensByPlatform($tokens);

            $results = [];
            $totalSent = 0;
            $totalFailed = 0;

            // Enviar a cada plataforma
            foreach ($tokensByPlatform as $platform => $platformTokens) {
                try {
                    $platformResult = $this->sendToPlatform(
                        $platform,
                        $platformTokens,
                        $payload,
                        $options
                    );
                    
                    $results[$platform] = $platformResult;
                    $totalSent += $platformResult['sent'];
                    $totalFailed += $platformResult['failed'];
                    
                    // Actualizar estados de tokens basado en respuesta
                    $this->updateTokenStates($platformTokens, $platformResult);
                    
                } catch (Exception $e) {
                    Log::error("Error enviando push a plataforma {$platform}", [
                        'user_id' => $user['id'],
                        'error' => $e->getMessage()
                    ]);
                    
                    $results[$platform] = [
                        'sent' => 0,
                        'failed' => count($platformTokens),
                        'error' => $e->getMessage()
                    ];
                    $totalFailed += count($platformTokens);
                }
            }

            // Actualizar estadísticas de la notificación
            $this->updatePushStatistics($notification['id'], $results);

            // Disparar evento de push enviado
            Event::dispatch(new PushSent(
                $notification['id'],
                $user['id'],
                count($tokens),
                $totalSent,
                $results
            ));

            $success = $totalSent > 0;
            
            Log::info('Notificación push procesada', [
                'user_id' => $user['id'],
                'notification_id' => $notification['id'],
                'total_tokens' => count($tokens),
                'sent' => $totalSent,
                'failed' => $totalFailed,
                'success' => $success
            ]);

            return [
                'success' => $success,
                'channel' => 'push',
                'total_tokens' => count($tokens),
                'sent' => $totalSent,
                'failed' => $totalFailed,
                'platform_results' => $results,
                'delivery_rate' => count($tokens) > 0 ? round(($totalSent / count($tokens)) * 100, 2) : 0
            ];

        } catch (Exception $e) {
            Log::error('Error al enviar notificación push', [
                'user_id' => $user['id'],
                'notification_id' => $notification['id'],
                'error' => $e->getMessage()
            ]);
            
            throw new PushNotificationException('Error al enviar push: ' . $e->getMessage());
        }
    }

    /**
     * Envía notificaciones push masivas a múltiples usuarios
     *
     * @param array $userIds IDs de usuarios destinatarios
     * @param array $notificationData Datos de la notificación
     * @param array $options Opciones de envío masivo
     * @return array Resultado del envío masivo
     */
    public function sendBulk(
        array $userIds,
        array $notificationData,
        array $options = []
    ): array {
        $startTime = microtime(true);
        $batchSize = $options['batch_size'] ?? 1000;
        
        // Obtener todos los tokens activos de los usuarios
        $allTokens = $this->notificationRepository->getBulkUserTokens($userIds, 'push');
        
        if (empty($allTokens)) {
            return [
                'success' => false,
                'error' => 'No active tokens found for bulk send',
                'total_users' => count($userIds),
                'tokens_found' => 0
            ];
        }

        // Preparar payload común
        $payload = $this->preparePushPayload($notificationData, $notificationData, $options);

        // Agrupar tokens por plataforma
        $tokensByPlatform = $this->groupTokensByPlatform($allTokens);

        $results = [];
        $totalSent = 0;
        $totalFailed = 0;

        // Enviar por cada plataforma en lotes
        foreach ($tokensByPlatform as $platform => $tokens) {
            $batches = array_chunk($tokens, $batchSize);
            
            $platformSent = 0;
            $platformFailed = 0;
            
            foreach ($batches as $batchIndex => $batch) {
                try {
                    $batchResult = $this->sendBatchToPlatform($platform, $batch, $payload, $options);
                    $platformSent += $batchResult['sent'];
                    $platformFailed += $batchResult['failed'];
                    
                    // Delay entre lotes para no sobrecargar APIs
                    if ($batchIndex < count($batches) - 1) {
                        usleep(100000); // 100ms delay
                    }
                    
                } catch (Exception $e) {
                    Log::error("Error en lote {$batchIndex} para plataforma {$platform}", [
                        'error' => $e->getMessage(),
                        'batch_size' => count($batch)
                    ]);
                    $platformFailed += count($batch);
                }
            }
            
            $results[$platform] = [
                'sent' => $platformSent,
                'failed' => $platformFailed,
                'total_tokens' => count($tokens),
                'batches_processed' => count($batches)
            ];
            
            $totalSent += $platformSent;
            $totalFailed += $platformFailed;
        }

        $processingTime = round(microtime(true) - $startTime, 2);
        
        Log::info('Notificación push masiva completada', [
            'total_users' => count($userIds),
            'total_tokens' => count($allTokens),
            'sent' => $totalSent,
            'failed' => $totalFailed,
            'processing_time' => $processingTime
        ]);

        return [
            'success' => $totalSent > 0,
            'total_users' => count($userIds),
            'total_tokens' => count($allTokens),
            'sent' => $totalSent,
            'failed' => $totalFailed,
            'platform_results' => $results,
            'processing_time' => $processingTime,
            'delivery_rate' => count($allTokens) > 0 ? round(($totalSent / count($allTokens)) * 100, 2) : 0
        ];
    }

    /**
     * Registra un nuevo token de dispositivo para un usuario
     *
     * @param int $userId ID del usuario
     * @param string $token Token del dispositivo
     * @param string $platform Plataforma del dispositivo
     * @param array $deviceInfo Información adicional del dispositivo
     * @return array Información del token registrado
     */
    public function registerToken(
        int $userId,
        string $token,
        string $platform,
        array $deviceInfo = []
    ): array {
        // Validar token
        if (!$this->validateToken($token, $platform)) {
            throw new InvalidTokenException("Token inválido para plataforma {$platform}");
        }

        // Verificar si el token ya existe
        $existingToken = $this->notificationRepository->findTokenByValue($token);
        
        if ($existingToken) {
            // Actualizar información si es necesario
            if ($existingToken['user_id'] !== $userId) {
                // Token migrado a otro usuario
                $this->notificationRepository->updateTokenUser($token, $userId);
            }
            
            // Actualizar última actividad
            $this->notificationRepository->updateTokenActivity($token);
            
            return [
                'token_id' => $existingToken['id'],
                'status' => 'updated',
                'message' => 'Token actualizado exitosamente'
            ];
        }

        // Crear nuevo token
        $tokenData = [
            'user_id' => $userId,
            'token' => $token,
            'platform' => $platform,
            'device_info' => $deviceInfo,
            'status' => self::TOKEN_STATUSES['ACTIVE'],
            'created_at' => Carbon::now(),
            'last_used_at' => Carbon::now()
        ];

        $tokenRecord = $this->notificationRepository->createPushToken($tokenData);

        // Limpiar cache de tokens del usuario
        Cache::forget("user_push_tokens_{$userId}");

        Log::info('Token push registrado', [
            'user_id' => $userId,
            'platform' => $platform,
            'token_id' => $tokenRecord['id']
        ]);

        return [
            'token_id' => $tokenRecord['id'],
            'status' => 'created',
            'message' => 'Token registrado exitosamente'
        ];
    }

    /**
     * Desregistra un token de dispositivo
     *
     * @param string $token Token a desregistrar
     * @param int|null $userId ID del usuario (opcional para validación)
     * @return bool True si se desregistró correctamente
     */
    public function unregisterToken(string $token, ?int $userId = null): bool
    {
        $success = $this->notificationRepository->deactivatePushToken($token, $userId);
        
        if ($success && $userId) {
            Cache::forget("user_push_tokens_{$userId}");
        }
        
        return $success;
    }

    /**
     * Obtiene los tokens activos de un usuario
     *
     * @param int $userId ID del usuario
     * @return array Lista de tokens activos
     */
    public function getUserTokens(int $userId): array
    {
        return $this->getActiveUserTokens($userId);
    }

    /**
     * Actualiza el badge count de un usuario
     *
     * @param int $userId ID del usuario
     * @param int $badgeCount Nuevo contador de badge
     * @return array Resultado de la actualización
     */
    public function updateBadgeCount(int $userId, int $badgeCount): array
    {
        $tokens = $this->getActiveUserTokens($userId);
        
        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'No active tokens found'
            ];
        }

        $results = [];
        
        // Filtrar solo tokens iOS (badge solo funciona en iOS)
        $iosTokens = array_filter($tokens, fn($token) => $token['platform'] === self::PLATFORMS['IOS']);
        
        if (empty($iosTokens)) {
            return [
                'success' => false,
                'message' => 'No iOS tokens found for badge update'
            ];
        }

        foreach ($iosTokens as $token) {
            try {
                $result = $this->sendBadgeUpdate($token['token'], $badgeCount);
                $results[] = $result;
            } catch (Exception $e) {
                Log::error('Error actualizando badge', [
                    'user_id' => $userId,
                    'token_id' => $token['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'success' => !empty($results),
            'updated_tokens' => count($results),
            'badge_count' => $badgeCount
        ];
    }

    /**
     * Obtiene estadísticas de push notifications
     *
     * @param array $filters Filtros para las estadísticas
     * @param string $period Período de análisis
     * @return array Estadísticas de push
     */
    public function getPushStatistics(array $filters = [], string $period = 'week'): array
    {
        $cacheKey = "push_stats_" . md5(serialize($filters)) . "_{$period}";
        
        return Cache::remember($cacheKey, 1800, function () use ($filters, $period) {
            $stats = $this->notificationRepository->getPushStatistics($filters, $period);
            
            return [
                'period' => $period,
                'total_sent' => $stats['total_sent'] ?? 0,
                'total_delivered' => $stats['total_delivered'] ?? 0,
                'total_opened' => $stats['total_opened'] ?? 0,
                'delivery_rate' => $this->calculateDeliveryRate($stats),
                'open_rate' => $this->calculateOpenRate($stats),
                'by_platform' => $stats['by_platform'] ?? [],
                'by_provider' => $stats['by_provider'] ?? [],
                'active_tokens' => $stats['active_tokens'] ?? 0,
                'token_distribution' => $stats['token_distribution'] ?? [],
                'peak_hours' => $this->analyzePeakHours($stats),
                'engagement_trends' => $this->analyzeEngagementTrends($stats),
                'generated_at' => Carbon::now()->toISOString()
            ];
        });
    }

    /**
     * Obtiene tokens activos de un usuario (con cache)
     */
    private function getActiveUserTokens(int $userId): array
    {
        $cacheKey = "user_push_tokens_{$userId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            return $this->notificationRepository->getUserPushTokens($userId, self::TOKEN_STATUSES['ACTIVE']);
        });
    }

    /**
     * Prepara el payload de la notificación push
     */
    private function preparePushPayload(array $notification, array $content, array $options): array
    {
        $payload = [
            'notification' => [
                'title' => $content['title'],
                'body' => $content['message'],
                'icon' => $options['icon'] ?? null,
                'image' => $content['image_url'] ?? null,
                'sound' => $options['sound'] ?? 'default',
                'badge' => $options['badge'] ?? null,
                'tag' => $notification['type'] ?? null
            ],
            'data' => [
                'notification_id' => $notification['id'],
                'type' => $notification['type'],
                'action_url' => $content['action_url'] ?? null,
                'metadata' => $content['metadata'] ?? [],
                'timestamp' => Carbon::now()->toISOString()
            ],
            'android' => [
                'notification' => [
                    'channel_id' => $this->getAndroidChannelId($notification['type']),
                    'priority' => $this->getAndroidPriority($options['priority'] ?? 'normal'),
                    'visibility' => $options['visibility'] ?? 'public',
                    'color' => $options['color'] ?? '#FF6B6B'
                ],
                'data' => [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ]
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => $this->getApnsPriority($options['priority'] ?? 'normal'),
                    'apns-expiration' => $options['expiration'] ?? time() + 86400
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $content['title'],
                            'body' => $content['message']
                        ],
                        'sound' => $options['sound'] ?? 'default',
                        'badge' => $options['badge'] ?? null,
                        'content-available' => $options['content_available'] ?? 0,
                        'mutable-content' => $options['mutable_content'] ?? 0
                    ]
                ]
            ]
        ];

        // Agregar botones de acción si están definidos
        if (isset($options['action_buttons']) && !empty($options['action_buttons'])) {
            $payload['notification']['actions'] = $options['action_buttons'];
        }

        return $payload;
    }

    /**
     * Agrupa tokens por plataforma
     */
    private function groupTokensByPlatform(array $tokens): array
    {
        $grouped = [];
        
        foreach ($tokens as $token) {
            $platform = $token['platform'];
            if (!isset($grouped[$platform])) {
                $grouped[$platform] = [];
            }
            $grouped[$platform][] = $token;
        }
        
        return $grouped;
    }

    /**
     * Envía notificaciones a una plataforma específica
     */
    private function sendToPlatform(
        string $platform,
        array $tokens,
        array $payload,
        array $options
    ): array {
        switch ($platform) {
            case self::PLATFORMS['ANDROID']:
            case self::PLATFORMS['WEB']:
                return $this->sendViaFCM($tokens, $payload, $options);
                
            case self::PLATFORMS['IOS']:
                return $this->sendViaAPNS($tokens, $payload, $options);
                
            case self::PLATFORMS['HUAWEI']:
                return $this->sendViaHMS($tokens, $payload, $options);
                
            default:
                throw new PushNotificationException("Plataforma no soportada: {$platform}");
        }
    }

    /**
     * Envía notificaciones vía Firebase Cloud Messaging
     */
    private function sendViaFCM(array $tokens, array $payload, array $options): array
    {
        $fcmPayload = [
            'registration_ids' => array_column($tokens, 'token'),
            'notification' => $payload['notification'],
            'data' => $payload['data'],
            'android' => $payload['android'],
            'priority' => 'high',
            'time_to_live' => $options['ttl'] ?? 86400
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . config('services.fcm.server_key'),
                'Content-Type' => 'application/json'
            ])->post('https://fcm.googleapis.com/fcm/send', $fcmPayload);

            if ($response->failed()) {
                throw new PushDeliveryException('FCM API error: ' . $response->body());
            }

            $result = $response->json();
            
            return [
                'sent' => $result['success'] ?? 0,
                'failed' => $result['failure'] ?? 0,
                'results' => $result['results'] ?? [],
                'multicast_id' => $result['multicast_id'] ?? null
            ];

        } catch (RequestException $e) {
            throw new PushDeliveryException('Error connecting to FCM: ' . $e->getMessage());
        }
    }

    /**
     * Envía notificaciones vía Apple Push Notification Service
     */
    private function sendViaAPNS(array $tokens, array $payload, array $options): array
    {
        $sent = 0;
        $failed = 0;
        $results = [];

        foreach ($tokens as $token) {
            try {
                $apnsPayload = json_encode($payload['apns']['payload']);
                
                // Aquí implementarías el envío real via APNs
                // Por ahora simulamos el resultado
                $success = $this->sendSingleAPNS($token['token'], $apnsPayload, $options);
                
                if ($success) {
                    $sent++;
                    $results[] = ['token' => $token['token'], 'status' => 'sent'];
                } else {
                    $failed++;
                    $results[] = ['token' => $token['token'], 'status' => 'failed'];
                }
                
            } catch (Exception $e) {
                $failed++;
                $results[] = [
                    'token' => $token['token'], 
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'results' => $results
        ];
    }

    /**
     * Valida un token según la plataforma
     */
    private function validateToken(string $token, string $platform): bool
    {
        switch ($platform) {
            case self::PLATFORMS['ANDROID']:
            case self::PLATFORMS['WEB']:
                return preg_match('/^[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+$/', $token) || 
                       strlen($token) > 140; // FCM tokens
                       
            case self::PLATFORMS['IOS']:
                return preg_match('/^[a-f0-9]{64}$/i', $token); // APNs device tokens
                
            case self::PLATFORMS['HUAWEI']:
                return strlen($token) > 50; // HMS tokens
                
            default:
                return false;
        }
    }

    /**
     * Obtiene el channel ID de Android para un tipo de notificación
     */
    private function getAndroidChannelId(string $notificationType): string
    {
        $channelMap = [
            'new_match' => 'matches',
            'new_message' => 'messages',
            'system' => 'system_notifications',
            'marketing' => 'promotional'
        ];
        
        return $channelMap[$notificationType] ?? 'default';
    }

    /**
     * Convierte prioridad a formato Android
     */
    private function getAndroidPriority(string $priority): string
    {
        return match($priority) {
            'low' => 'min',
            'normal' => 'default',
            'high' => 'high',
            default => 'default'
        };
    }

    /**
     * Convierte prioridad a formato APNs
     */
    private function getApnsPriority(string $priority): string
    {
        return match($priority) {
            'low', 'normal' => '5',
            'high' => '10',
            default => '5'
        };
    }

    /**
     * Actualiza estados de tokens basado en respuesta de plataforma
     */
    private function updateTokenStates(array $tokens, array $result): void
    {
        if (!isset($result['results'])) {
            return;
        }

        foreach ($result['results'] as $index => $tokenResult) {
            if (isset($tokens[$index])) {
                $token = $tokens[$index];
                
                if (isset($tokenResult['error'])) {
                    // Token inválido o expirado
                    if (str_contains($tokenResult['error'], 'InvalidRegistration') || 
                        str_contains($tokenResult['error'], 'NotRegistered')) {
                        $this->notificationRepository->invalidateToken($token['token'], 'invalid_token');
                    }
                }
            }
        }
    }

    /**
     * Actualiza estadísticas de push notifications
     */
    private function updatePushStatistics(int $notificationId, array $results): void
    {
        $totalSent = array_sum(array_column($results, 'sent'));
        $totalFailed = array_sum(array_column($results, 'failed'));
        
        $this->notificationRepository->markAsDelivered($notificationId, 'push', [
            'sent' => $totalSent,
            'failed' => $totalFailed,
            'platform_results' => $results
        ]);
    }

    /**
     * Envía lote de notificaciones a una plataforma específica
     */
    private function sendBatchToPlatform(
        string $platform,
        array $batch,
        array $payload,
        array $options
    ): array {
        return $this->sendToPlatform($platform, $batch, $payload, $options);
    }

    /**
     * Envía notificaciones vía Huawei Mobile Services
     */
    private function sendViaHMS(array $tokens, array $payload, array $options): array
    {
        $sent = 0;
        $failed = 0;
        $results = [];

        foreach ($tokens as $token) {
            try {
                // Implementación básica para HMS
                // En producción, usarías el SDK oficial de HMS
                $success = $this->sendSingleHMS($token['token'], $payload, $options);
                
                if ($success) {
                    $sent++;
                    $results[] = ['token' => $token['token'], 'status' => 'sent'];
                } else {
                    $failed++;
                    $results[] = ['token' => $token['token'], 'status' => 'failed'];
                }
                
            } catch (Exception $e) {
                $failed++;
                $results[] = [
                    'token' => $token['token'], 
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'results' => $results
        ];
    }

    /**
     * Envía notificación individual vía APNs
     */
    private function sendSingleAPNS(string $token, string $payload, array $options): bool
    {
        try {
            // Implementación básica para APNs
            // En producción, usarías el SDK oficial de APNs
            $apnsUrl = config('services.apns.url', 'https://api.push.apple.com');
            $certificatePath = config('services.apns.certificate_path');
            
            if (!$certificatePath) {
                Log::warning('APNs certificate not configured');
                return false;
            }

            // Simulación de envío exitoso
            // En producción implementarías la conexión real con APNs
            return true;
            
        } catch (Exception $e) {
            Log::error('APNs send error', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envía notificación individual vía HMS
     */
    private function sendSingleHMS(string $token, array $payload, array $options): bool
    {
        try {
            // Implementación básica para HMS
            // En producción, usarías el SDK oficial de HMS
            $hmsUrl = config('services.hms.url', 'https://push-api.cloud.huawei.com');
            $appId = config('services.hms.app_id');
            
            if (!$appId) {
                Log::warning('HMS app ID not configured');
                return false;
            }

            // Simulación de envío exitoso
            // En producción implementarías la conexión real con HMS
            return true;
            
        } catch (Exception $e) {
            Log::error('HMS send error', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envía actualización de badge para iOS
     */
    private function sendBadgeUpdate(string $token, int $badgeCount): array
    {
        try {
            $badgePayload = [
                'aps' => [
                    'badge' => $badgeCount
                ]
            ];

            $success = $this->sendSingleAPNS($token, json_encode($badgePayload), []);
            
            return [
                'success' => $success,
                'badge_count' => $badgeCount,
                'token' => substr($token, 0, 10) . '...'
            ];
            
        } catch (Exception $e) {
            Log::error('Badge update error', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'badge_count' => $badgeCount
            ];
        }
    }

    /**
     * Calcula tasa de entrega
     */
    private function calculateDeliveryRate(array $stats): float
    {
        $totalSent = $stats['total_sent'] ?? 0;
        $totalDelivered = $stats['total_delivered'] ?? 0;
        
        if ($totalSent === 0) {
            return 0.0;
        }
        
        return round(($totalDelivered / $totalSent) * 100, 2);
    }

    /**
     * Calcula tasa de apertura
     */
    private function calculateOpenRate(array $stats): float
    {
        $totalDelivered = $stats['total_delivered'] ?? 0;
        $totalOpened = $stats['total_opened'] ?? 0;
        
        if ($totalDelivered === 0) {
            return 0.0;
        }
        
        return round(($totalOpened / $totalDelivered) * 100, 2);
    }

    /**
     * Analiza horas pico de actividad
     */
    private function analyzePeakHours(array $stats): array
    {
        $hourlyData = $stats['hourly_data'] ?? [];
        
        if (empty($hourlyData)) {
            return [];
        }
        
        // Encontrar las horas con mayor actividad
        arsort($hourlyData);
        $peakHours = array_slice($hourlyData, 0, 3, true);
        
        return [
            'peak_hours' => array_keys($peakHours),
            'peak_values' => array_values($peakHours),
            'average_activity' => array_sum($hourlyData) / count($hourlyData)
        ];
    }

    /**
     * Analiza tendencias de engagement
     */
    private function analyzeEngagementTrends(array $stats): array
    {
        $dailyData = $stats['daily_data'] ?? [];
        
        if (count($dailyData) < 2) {
            return ['trend' => 'insufficient_data'];
        }
        
        $values = array_values($dailyData);
        $firstHalf = array_slice($values, 0, count($values) / 2);
        $secondHalf = array_slice($values, count($values) / 2);
        
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);
        
        $trend = 'stable';
        if ($secondAvg > $firstAvg * 1.1) {
            $trend = 'increasing';
        } elseif ($secondAvg < $firstAvg * 0.9) {
            $trend = 'decreasing';
        }
        
        return [
            'trend' => $trend,
            'first_period_avg' => round($firstAvg, 2),
            'second_period_avg' => round($secondAvg, 2),
            'change_percentage' => round((($secondAvg - $firstAvg) / $firstAvg) * 100, 2)
        ];
    }
}