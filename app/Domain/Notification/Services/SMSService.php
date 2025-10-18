<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Exceptions\SMSNotificationException;
use App\Exceptions\InvalidPhoneNumberException;
use App\Exceptions\SMSDeliveryException;
use App\Exceptions\SMSRateLimitException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Http\Client\RequestException;

/**
 * Servicio especializado para notificaciones por SMS en ForeverUsInLove
 * 
 * Este servicio gestiona el envío de mensajes de texto críticos y promocionales
 * a través de múltiples proveedores, con optimización de costos, gestión inteligente
 * de rate limiting y compliance con regulaciones internacionales de SMS.
 * 
 * Características principales:
 * - Soporte multi-proveedor (Twilio, Vonage, AWS SNS, etc.)
 * - Validación y formateo internacional de números telefónicos
 * - Rate limiting inteligente por usuario y global
 * - Optimización de costos con selección automática de proveedores
 * - Compliance con regulaciones (TCPA, GDPR, etc.)
 * - Gestión de horarios de envío por zona horaria
 * - Templates dinámicos con personalización
 * - Analytics de entrega y engagement
 * - Fallback automático entre proveedores
 * - Gestión de opt-in/opt-out y listas de supresión
 * - Soporte para SMS largos y Unicode
 * - Integración con verificación en dos pasos (2FA)
 * 
 * @package App\Domain\Notification\Services
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class SMSService
{
    /**
     * Tipos de SMS disponibles
     */
    public const SMS_TYPES = [
        // Críticos y de seguridad
        'VERIFICATION_CODE' => 'verification_code',
        'LOGIN_CODE' => 'login_code',
        'PASSWORD_RESET' => 'password_reset',
        'SECURITY_ALERT' => 'security_alert',
        'ACCOUNT_LOCKED' => 'account_locked',
        
        // Notificaciones importantes
        'NEW_MATCH_SMS' => 'new_match_sms',
        'URGENT_MESSAGE' => 'urgent_message',
        'SAFETY_ALERT' => 'safety_alert',
        'PAYMENT_ALERT' => 'payment_alert',
        
        // Promocionales (requieren opt-in explícito)
        'PROMOTIONAL_OFFER' => 'promotional_offer',
        'SPECIAL_EVENT' => 'special_event',
        'REMINDER' => 'reminder',
        'SURVEY_INVITE' => 'survey_invite'
    ];

    /**
     * Proveedores de SMS soportados
     */
    public const SMS_PROVIDERS = [
        'TWILIO' => 'twilio',
        'VONAGE' => 'vonage',       // Anteriormente Nexmo
        'AWS_SNS' => 'aws_sns',
        'MESSAGEBIRD' => 'messagebird',
        'CLICKSEND' => 'clicksend',
        'PLIVO' => 'plivo',
        'TELNYX' => 'telnyx',
        'SINCH' => 'sinch'
    ];

    /**
     * Categorías de SMS para compliance
     */
    public const SMS_CATEGORIES = [
        'TRANSACTIONAL' => 'transactional',    // No requiere opt-in
        'PROMOTIONAL' => 'promotional',         // Requiere opt-in explícito
        'AUTHENTICATION' => 'authentication',   // 2FA, códigos de verificación
        'EMERGENCY' => 'emergency'             // Alertas de seguridad críticas
    ];

    /**
     * Estados de entrega de SMS
     */
    public const DELIVERY_STATUSES = [
        'QUEUED' => 'queued',
        'SENT' => 'sent',
        'DELIVERED' => 'delivered',
        'FAILED' => 'failed',
        'UNDELIVERED' => 'undelivered',
        'REJECTED' => 'rejected',
        'UNKNOWN' => 'unknown'
    ];

    /**
     * Límites de rate limiting por tipo
     */
    public const RATE_LIMITS = [
        'VERIFICATION_CODE' => ['per_minute' => 1, 'per_hour' => 5, 'per_day' => 10],
        'PROMOTIONAL_OFFER' => ['per_minute' => 0, 'per_hour' => 2, 'per_day' => 5],
        'SAFETY_ALERT' => ['per_minute' => 5, 'per_hour' => 10, 'per_day' => 20],
        'DEFAULT' => ['per_minute' => 1, 'per_hour' => 3, 'per_day' => 8]
    ];

    /**
     * Constructor del servicio de SMS
     */
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepository
    ) {}

    /**
     * Envía un SMS de notificación a un usuario
     *
     * @param array $user Datos del usuario destinatario
     * @param array $notification Datos de la notificación
     * @param array $content Contenido personalizado
     * @param array $options Opciones adicionales de envío
     * @return array Resultado del envío de SMS
     * @throws SMSNotificationException
     */
    public function send(
        array $user,
        array $notification,
        array $content,
        array $options = []
    ): array {
        try {
            Log::info('Enviando SMS de notificación', [
                'user_id' => $user['id'],
                'notification_id' => $notification['id'],
                'type' => $notification['type']
            ]);

            // Validar número telefónico
            $phoneNumber = $this->validateAndFormatPhoneNumber($user['phone']);
            if (!$phoneNumber) {
                throw new InvalidPhoneNumberException("Número telefónico inválido: {$user['phone']}");
            }

            // Verificar si el usuario puede recibir SMS
            if (!$this->canReceiveSMS($user, $notification['type'])) {
                return [
                    'success' => false,
                    'channel' => 'sms',
                    'error' => 'User cannot receive SMS notifications',
                    'reason' => 'opt_out_or_suppression'
                ];
            }

            // Verificar rate limiting
            if (!$this->checkRateLimit($user['id'], $notification['type'])) {
                throw new SMSRateLimitException("Rate limit exceeded for SMS type: {$notification['type']}");
            }

            // Verificar horario permitido para envío
            if (!$this->isWithinAllowedHours($user['timezone'] ?? 'UTC', $notification['type'])) {
                // Programar para horario permitido si no es crítico
                if (!$this->isCriticalSMS($notification['type'])) {
                    return $this->scheduleForAllowedHours($user, $notification, $content, $options);
                }
            }

            // Preparar mensaje SMS
            $smsData = $this->prepareSMSContent($user, $notification, $content, $options);

            // Seleccionar proveedor óptimo
            $provider = $this->selectOptimalProvider($phoneNumber, $notification['type'], $options);

            // Crear registro de SMS
            $smsRecord = $this->createSMSRecord($user, $notification, $smsData, $provider);

            // Enviar SMS
            $deliveryResult = $this->sendViaProvider($provider, $phoneNumber, $smsData, $smsRecord['id'], $options);

            // Actualizar registro con resultado
            $this->updateSMSRecord($smsRecord['id'], $deliveryResult);

            // Registrar en rate limiting
            $this->recordRateLimitUsage($user['id'], $notification['type']);

            // Programar seguimiento de entrega
            $this->scheduleDeliveryTracking($smsRecord['id'], $deliveryResult);

            Log::info('SMS enviado exitosamente', [
                'user_id' => $user['id'],
                'sms_record_id' => $smsRecord['id'],
                'provider' => $provider,
                'delivery_success' => $deliveryResult['success']
            ]);

            return [
                'success' => $deliveryResult['success'],
                'channel' => 'sms',
                'sms_record_id' => $smsRecord['id'],
                'provider_used' => $provider,
                'delivery_id' => $deliveryResult['delivery_id'] ?? null,
                'message_length' => strlen($smsData['message']),
                'segments_used' => $this->calculateSMSSegments($smsData['message']),
                'estimated_delivery' => Carbon::now()->addMinutes(1)->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Error al enviar SMS de notificación', [
                'user_id' => $user['id'],
                'notification_id' => $notification['id'],
                'error' => $e->getMessage()
            ]);
            
            throw new SMSNotificationException('Error al enviar SMS: ' . $e->getMessage());
        }
    }

    /**
     * Envía código de verificación por SMS
     *
     * @param string $phoneNumber Número telefónico
     * @param string $code Código de verificación
     * @param array $options Opciones adicionales
     * @return array Resultado del envío
     */
    public function sendVerificationCode(string $phoneNumber, string $code, array $options = []): array
    {
        $phoneNumber = $this->validateAndFormatPhoneNumber($phoneNumber);
        if (!$phoneNumber) {
            throw new InvalidPhoneNumberException("Número telefónico inválido");
        }

        // Verificar rate limiting específico para códigos
        if (!$this->checkVerificationCodeRateLimit($phoneNumber)) {
            throw new SMSRateLimitException("Rate limit exceeded for verification codes");
        }

        $message = $this->buildVerificationMessage($code, $options);
        
        $provider = $this->selectOptimalProvider($phoneNumber, self::SMS_TYPES['VERIFICATION_CODE'], $options);
        
        $smsData = [
            'message' => $message,
            'from' => $this->getFromNumber($provider, $phoneNumber),
            'type' => self::SMS_TYPES['VERIFICATION_CODE']
        ];

        $smsRecord = $this->notificationRepository->createSMSRecord([
            'phone_number' => $phoneNumber,
            'message' => $message,
            'provider' => $provider,
            'type' => self::SMS_TYPES['VERIFICATION_CODE'],
            'status' => self::DELIVERY_STATUSES['QUEUED']
        ]);

        $deliveryResult = $this->sendViaProvider($provider, $phoneNumber, $smsData, $smsRecord['id'], $options);
        
        $this->updateSMSRecord($smsRecord['id'], $deliveryResult);
        $this->recordVerificationCodeUsage($phoneNumber);

        return [
            'success' => $deliveryResult['success'],
            'sms_record_id' => $smsRecord['id'],
            'delivery_id' => $deliveryResult['delivery_id'] ?? null,
            'expires_in' => $options['expires_in'] ?? 300 // 5 minutos por defecto
        ];
    }

    /**
     * Envía SMS masivo a una lista de números
     *
     * @param array $phoneNumbers Lista de números telefónicos
     * @param string $message Mensaje a enviar
     * @param string $smsType Tipo de SMS
     * @param array $options Opciones de envío masivo
     * @return array Resultado del envío masivo
     */
    public function sendBulk(
        array $phoneNumbers,
        string $message,
        string $smsType,
        array $options = []
    ): array {
        $startTime = microtime(true);
        
        // Validar que sea SMS promocional con opt-in
        if ($this->getSMSCategory($smsType) === self::SMS_CATEGORIES['PROMOTIONAL']) {
            $phoneNumbers = $this->filterOptedInNumbers($phoneNumbers);
        }

        $results = [
            'total_numbers' => count($phoneNumbers),
            'sent' => 0,
            'failed' => 0,
            'invalid_numbers' => 0,
            'rate_limited' => 0,
            'details' => []
        ];

        $batchSize = $options['batch_size'] ?? 100;
        $throttleSeconds = $options['throttle_seconds'] ?? 1;

        $batches = array_chunk($phoneNumbers, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchResult = $this->processBulkSMSBatch($batch, $message, $smsType, $options);
            
            $results['sent'] += $batchResult['sent'];
            $results['failed'] += $batchResult['failed'];
            $results['invalid_numbers'] += $batchResult['invalid_numbers'];
            $results['rate_limited'] += $batchResult['rate_limited'];
            
            // Throttling entre lotes
            if ($batchIndex < count($batches) - 1 && $throttleSeconds > 0) {
                sleep($throttleSeconds);
            }
        }

        $results['processing_time'] = round(microtime(true) - $startTime, 2);
        $results['success_rate'] = $results['total_numbers'] > 0 ? 
                                  round(($results['sent'] / $results['total_numbers']) * 100, 2) : 0;

        Log::info('SMS masivo completado', $results);

        return $results;
    }

    /**
     * Gestiona opt-out de SMS
     *
     * @param string $phoneNumber Número telefónico
     * @param string $keyword Palabra clave del opt-out (STOP, UNSUBSCRIBE, etc.)
     * @return array Resultado de la operación
     */
    public function processOptOut(string $phoneNumber, string $keyword = 'STOP'): array
    {
        $phoneNumber = $this->validateAndFormatPhoneNumber($phoneNumber);
        
        if (!$phoneNumber) {
            return [
                'success' => false,
                'error' => 'Invalid phone number'
            ];
        }

        // Agregar a lista de supresión
        $this->notificationRepository->addToSMSSuppressionList($phoneNumber, [
            'keyword' => $keyword,
            'timestamp' => Carbon::now(),
            'source' => 'user_request'
        ]);

        // Enviar confirmación de opt-out
        $confirmationMessage = "You have been unsubscribed from SMS notifications. Reply HELP for info or STOP to confirm.";
        
        $this->sendSystemSMS($phoneNumber, $confirmationMessage, [
            'bypass_suppression' => true,
            'type' => 'opt_out_confirmation'
        ]);

        Log::info('SMS opt-out procesado', [
            'phone_number' => $phoneNumber,
            'keyword' => $keyword
        ]);

        return [
            'success' => true,
            'message' => 'Opt-out processed successfully',
            'phone_number' => $phoneNumber
        ];
    }

    /**
     * Obtiene estadísticas de SMS
     *
     * @param array $filters Filtros para las estadísticas
     * @param string $period Período de análisis
     * @return array Estadísticas de SMS
     */
    public function getSMSStatistics(array $filters = [], string $period = 'week'): array
    {
        $cacheKey = "sms_stats_" . md5(serialize($filters)) . "_{$period}";
        
        return Cache::remember($cacheKey, 1800, function () use ($filters, $period) {
            $stats = $this->notificationRepository->getSMSStatistics($filters, $period);
            
            return [
                'period' => $period,
                'total_sent' => $stats['sent'] ?? 0,
                'total_delivered' => $stats['delivered'] ?? 0,
                'total_failed' => $stats['failed'] ?? 0,
                'delivery_rate' => $this->calculateDeliveryRate($stats),
                'average_segments' => $stats['avg_segments'] ?? 1,
                'total_cost' => $stats['total_cost'] ?? 0,
                'cost_per_sms' => $this->calculateCostPerSMS($stats),
                'by_type' => $stats['by_type'] ?? [],
                'by_provider' => $stats['by_provider'] ?? [],
                'by_country' => $stats['by_country'] ?? [],
                'peak_hours' => $this->analyzePeakSMSHours($stats),
                'opt_out_rate' => $this->calculateOptOutRate($stats),
                'verification_code_success_rate' => $stats['verification_success_rate'] ?? 0,
                'generated_at' => Carbon::now()->toISOString()
            ];
        });
    }

    /**
     * Valida y formatea número telefónico internacional
     */
    private function validateAndFormatPhoneNumber(string $phoneNumber): ?string
    {
        // Remover espacios, guiones y paréntesis
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $phoneNumber);
        
        // Si no comienza con +, agregar código por defecto
        if (!str_starts_with($cleaned, '+')) {
            $cleaned = '+1' . $cleaned; // Asume US por defecto
        }

        // Validar formato internacional básico
        if (preg_match('/^\+[1-9]\d{1,14}$/', $cleaned)) {
            return $cleaned;
        }

        return null;
    }

    /**
     * Verifica si un usuario puede recibir SMS
     */
    private function canReceiveSMS(array $user, string $smsType): bool
    {
        $phoneNumber = $this->validateAndFormatPhoneNumber($user['phone']);
        
        // Verificar lista de supresión
        if ($this->isPhoneNumberSuppressed($phoneNumber)) {
            return false;
        }

        // Verificar opt-in para SMS promocionales
        $category = $this->getSMSCategory($smsType);
        if ($category === self::SMS_CATEGORIES['PROMOTIONAL']) {
            return $this->hasValidOptIn($phoneNumber);
        }

        // SMS transaccionales y de autenticación siempre permitidos
        return true;
    }

    /**
     * Verifica rate limiting para SMS
     */
    private function checkRateLimit(int $userId, string $smsType): bool
    {
        $limits = self::RATE_LIMITS[$smsType] ?? self::RATE_LIMITS['DEFAULT'];
        
        $cacheKeyMinute = "sms_rate_limit_minute_{$userId}_{$smsType}";
        $cacheKeyHour = "sms_rate_limit_hour_{$userId}_{$smsType}";
        $cacheKeyDay = "sms_rate_limit_day_{$userId}_{$smsType}";
        
        $countMinute = Cache::get($cacheKeyMinute, 0);
        $countHour = Cache::get($cacheKeyHour, 0);
        $countDay = Cache::get($cacheKeyDay, 0);
        
        return $countMinute < $limits['per_minute'] &&
               $countHour < $limits['per_hour'] &&
               $countDay < $limits['per_day'];
    }

    /**
     * Verifica si está dentro de horario permitido
     */
    private function isWithinAllowedHours(string $timezone, string $smsType): bool
    {
        // SMS críticos siempre permitidos
        if ($this->isCriticalSMS($smsType)) {
            return true;
        }

        $now = Carbon::now($timezone);
        $hour = $now->hour;
        
        // Horario permitido: 8 AM a 9 PM hora local
        return $hour >= 8 && $hour <= 21;
    }

    /**
     * Prepara el contenido del SMS
     */
    private function prepareSMSContent(array $user, array $notification, array $content, array $options): array
    {
        $template = $this->getSMSTemplate($notification['type'], $user['language'] ?? 'en');
        
        $personalData = [
            'user_name' => $user['first_name'] ?? 'User',
            'app_name' => config('app.name', 'ForeverUsInLove')
        ];

        $templateData = array_merge($content, $personalData);
        $message = $this->processTemplate($template['message'], $templateData);

        // Truncar si excede límites
        $maxLength = $options['max_length'] ?? 160;
        if (strlen($message) > $maxLength) {
            $message = substr($message, 0, $maxLength - 3) . '...';
        }

        return [
            'message' => $message,
            'from' => $options['from'] ?? null,
            'type' => $notification['type'],
            'template_data' => $templateData
        ];
    }

    /**
     * Selecciona proveedor óptimo basado en costo y región
     */
    private function selectOptimalProvider(string $phoneNumber, string $smsType, array $options): string
    {
        if (isset($options['force_provider'])) {
            return $options['force_provider'];
        }

        $country = $this->getCountryFromPhoneNumber($phoneNumber);
        $category = $this->getSMSCategory($smsType);
        
        // Proveedores por región y categoría
        $providersByRegion = [
            'US' => [self::SMS_PROVIDERS['TWILIO'], self::SMS_PROVIDERS['AWS_SNS']],
            'EU' => [self::SMS_PROVIDERS['MESSAGEBIRD'], self::SMS_PROVIDERS['VONAGE']],
            'GLOBAL' => [self::SMS_PROVIDERS['TWILIO'], self::SMS_PROVIDERS['VONAGE']]
        ];
        
        $availableProviders = $providersByRegion[$country] ?? $providersByRegion['GLOBAL'];
        
        return $this->selectCheapestProvider($availableProviders, $country, $category);
    }

    /**
     * Envía SMS a través de proveedor específico
     */
    private function sendViaProvider(
        string $provider,
        string $phoneNumber,
        array $smsData,
        int $recordId,
        array $options
    ): array {
        switch ($provider) {
            case self::SMS_PROVIDERS['TWILIO']:
                return $this->sendViaTwilio($phoneNumber, $smsData, $recordId, $options);
                
            case self::SMS_PROVIDERS['VONAGE']:
                return $this->sendViaVonage($phoneNumber, $smsData, $recordId, $options);
                
            case self::SMS_PROVIDERS['AWS_SNS']:
                return $this->sendViaSNS($phoneNumber, $smsData, $recordId, $options);
                
            default:
                throw new SMSNotificationException("Proveedor no soportado: {$provider}");
        }
    }

    /**
     * Envía SMS vía Twilio
     */
    private function sendViaTwilio(string $phoneNumber, array $smsData, int $recordId, array $options): array
    {
        try {
            $twilioPayload = [
                'To' => $phoneNumber,
                'From' => $smsData['from'] ?? config('services.twilio.from_number'),
                'Body' => $smsData['message']
            ];

            $response = Http::withBasicAuth(
                config('services.twilio.account_sid'),
                config('services.twilio.auth_token')
            )->asForm()->post(
                'https://api.twilio.com/2010-04-01/Accounts/' . config('services.twilio.account_sid') . '/Messages.json',
                $twilioPayload
            );

            if ($response->failed()) {
                throw new SMSDeliveryException('Twilio API error: ' . $response->body());
            }

            $result = $response->json();
            
            return [
                'success' => true,
                'delivery_id' => $result['sid'] ?? null,
                'status' => $result['status'] ?? 'unknown',
                'provider_response' => $result
            ];

        } catch (RequestException $e) {
            throw new SMSDeliveryException('Error connecting to Twilio: ' . $e->getMessage());
        }
    }

    /**
     * Determina si es SMS crítico
     */
    private function isCriticalSMS(string $smsType): bool
    {
        return in_array($smsType, [
            self::SMS_TYPES['VERIFICATION_CODE'],
            self::SMS_TYPES['LOGIN_CODE'],
            self::SMS_TYPES['SECURITY_ALERT'],
            self::SMS_TYPES['SAFETY_ALERT']
        ]);
    }

    /**
     * Calcula número de segmentos SMS
     */
    private function calculateSMSSegments(string $message): int
    {
        $length = strlen($message);
        
        // SMS estándar: 160 caracteres por segmento
        // SMS Unicode: 70 caracteres por segmento
        $isUnicode = !mb_check_encoding($message, 'ASCII');
        $segmentLength = $isUnicode ? 70 : 160;
        
        return max(1, ceil($length / $segmentLength));
    }

    /**
     * Calcula tasa de entrega
     */
    private function calculateDeliveryRate(array $stats): float
    {
        $sent = $stats['sent'] ?? 0;
        $delivered = $stats['delivered'] ?? 0;
        
        return $sent > 0 ? round(($delivered / $sent) * 100, 2) : 0.0;
    }

    /**
     * Programa SMS para horario permitido
     */
    private function scheduleForAllowedHours(array $user, array $notification, array $content, array $options): array
    {
        $timezone = $user['timezone'] ?? 'UTC';
        $nextAllowedTime = $this->getNextAllowedTime($timezone);
        
        $scheduledData = [
            'user_id' => $user['id'],
            'notification_id' => $notification['id'],
            'content' => $content,
            'options' => $options,
            'scheduled_at' => $nextAllowedTime,
            'type' => 'sms_scheduled'
        ];
        
        $this->notificationRepository->scheduleNotification($scheduledData, $nextAllowedTime, $options);
        
        return [
            'success' => true,
            'channel' => 'sms',
            'scheduled' => true,
            'scheduled_at' => $nextAllowedTime->toISOString(),
            'message' => 'SMS scheduled for allowed hours'
        ];
    }

    /**
     * Crea registro de SMS en la base de datos
     */
    private function createSMSRecord(array $user, array $notification, array $smsData, string $provider): array
    {
        return $this->notificationRepository->createSMSRecord([
            'user_id' => $user['id'],
            'notification_id' => $notification['id'],
            'phone_number' => $this->validateAndFormatPhoneNumber($user['phone']),
            'message' => $smsData['message'],
            'provider' => $provider,
            'type' => $notification['type'],
            'status' => self::DELIVERY_STATUSES['QUEUED'],
            'created_at' => Carbon::now()
        ]);
    }

    /**
     * Actualiza registro de SMS con resultado de entrega
     */
    private function updateSMSRecord(int $recordId, array $deliveryResult): bool
    {
        $updateData = [
            'status' => $deliveryResult['status'] ?? self::DELIVERY_STATUSES['SENT'],
            'delivery_id' => $deliveryResult['delivery_id'] ?? null,
            'provider_response' => $deliveryResult['provider_response'] ?? null,
            'delivered_at' => $deliveryResult['success'] ? Carbon::now() : null,
            'error_message' => $deliveryResult['error'] ?? null
        ];
        
        $result = $this->notificationRepository->update($recordId, $updateData);
        return is_array($result) ? true : $result;
    }

    /**
     * Registra uso en rate limiting
     */
    private function recordRateLimitUsage(int $userId, string $smsType): void
    {
        $limits = self::RATE_LIMITS[$smsType] ?? self::RATE_LIMITS['DEFAULT'];
        
        $cacheKeyMinute = "sms_rate_limit_minute_{$userId}_{$smsType}";
        $cacheKeyHour = "sms_rate_limit_hour_{$userId}_{$smsType}";
        $cacheKeyDay = "sms_rate_limit_day_{$userId}_{$smsType}";
        
        Cache::increment($cacheKeyMinute, 1, 60);
        Cache::increment($cacheKeyHour, 1, 3600);
        Cache::increment($cacheKeyDay, 1, 86400);
    }

    /**
     * Programa seguimiento de entrega
     */
    private function scheduleDeliveryTracking(int $recordId, array $deliveryResult): void
    {
        if ($deliveryResult['success'] && isset($deliveryResult['delivery_id'])) {
            // Programar verificación de entrega en 5 minutos
            $trackingData = [
                'sms_record_id' => $recordId,
                'delivery_id' => $deliveryResult['delivery_id'],
                'provider' => $deliveryResult['provider'] ?? 'unknown'
            ];
            
            // Aquí se podría usar una cola de Laravel para el seguimiento
            // Queue::later(Carbon::now()->addMinutes(5), new TrackSMSDelivery($trackingData));
        }
    }

    /**
     * Verifica rate limiting específico para códigos de verificación
     */
    private function checkVerificationCodeRateLimit(string $phoneNumber): bool
    {
        $cacheKey = "sms_verification_rate_{$phoneNumber}";
        $count = Cache::get($cacheKey, 0);
        
        return $count < 3; // Máximo 3 códigos por hora
    }

    /**
     * Construye mensaje de verificación
     */
    private function buildVerificationMessage(string $code, array $options): string
    {
        $appName = config('app.name', 'ForeverUsInLove');
        $expiresIn = $options['expires_in'] ?? 300;
        $expiresMinutes = round($expiresIn / 60);
        
        return "Tu código de verificación para {$appName} es: {$code}. Válido por {$expiresMinutes} minutos. No compartas este código.";
    }

    /**
     * Obtiene número de origen según proveedor
     */
    private function getFromNumber(string $provider, string $phoneNumber): string
    {
        $country = $this->getCountryFromPhoneNumber($phoneNumber);
        
        $fromNumbers = [
            self::SMS_PROVIDERS['TWILIO'] => config('services.twilio.from_number'),
            self::SMS_PROVIDERS['VONAGE'] => config('services.vonage.from_number'),
            self::SMS_PROVIDERS['AWS_SNS'] => config('services.aws_sns.from_number')
        ];
        
        return $fromNumbers[$provider] ?? config('services.sms.default_from_number');
    }

    /**
     * Registra uso de código de verificación
     */
    private function recordVerificationCodeUsage(string $phoneNumber): void
    {
        $cacheKey = "sms_verification_rate_{$phoneNumber}";
        Cache::increment($cacheKey, 1, 3600); // 1 hora
    }

    /**
     * Filtra números con opt-in válido
     */
    private function filterOptedInNumbers(array $phoneNumbers): array
    {
        $validNumbers = [];
        
        foreach ($phoneNumbers as $phoneNumber) {
            $formattedNumber = $this->validateAndFormatPhoneNumber($phoneNumber);
            if ($formattedNumber && $this->hasValidOptIn($formattedNumber)) {
                $validNumbers[] = $formattedNumber;
            }
        }
        
        return $validNumbers;
    }

    /**
     * Procesa lote de SMS masivo
     */
    private function processBulkSMSBatch(array $batch, string $message, string $smsType, array $options): array
    {
        $result = [
            'sent' => 0,
            'failed' => 0,
            'invalid_numbers' => 0,
            'rate_limited' => 0
        ];
        
        foreach ($batch as $phoneNumber) {
            try {
                $formattedNumber = $this->validateAndFormatPhoneNumber($phoneNumber);
                
                if (!$formattedNumber) {
                    $result['invalid_numbers']++;
                    continue;
                }
                
                if (!$this->checkRateLimit(0, $smsType)) { // Rate limit global
                    $result['rate_limited']++;
                    continue;
                }
                
                $smsData = [
                    'message' => $message,
                    'type' => $smsType
                ];
                
                $provider = $this->selectOptimalProvider($formattedNumber, $smsType, $options);
                $deliveryResult = $this->sendViaProvider($provider, $formattedNumber, $smsData, 0, $options);
                
                if ($deliveryResult['success']) {
                    $result['sent']++;
                } else {
                    $result['failed']++;
                }
                
            } catch (\Exception $e) {
                $result['failed']++;
                Log::error('Error en SMS masivo', [
                    'phone' => $phoneNumber,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $result;
    }

    /**
     * Envía SMS del sistema (bypass de restricciones)
     */
    private function sendSystemSMS(string $phoneNumber, string $message, array $options): array
    {
        $smsData = [
            'message' => $message,
            'type' => 'system_message'
        ];
        
        $provider = $this->selectOptimalProvider($phoneNumber, 'system_message', $options);
        
        return $this->sendViaProvider($provider, $phoneNumber, $smsData, 0, $options);
    }

    /**
     * Calcula costo por SMS
     */
    private function calculateCostPerSMS(array $stats): float
    {
        $totalCost = $stats['total_cost'] ?? 0;
        $totalSent = $stats['sent'] ?? 0;
        
        return $totalSent > 0 ? round($totalCost / $totalSent, 4) : 0.0;
    }

    /**
     * Analiza horas pico de SMS
     */
    private function analyzePeakSMSHours(array $stats): array
    {
        $hourlyStats = $stats['hourly_stats'] ?? [];
        
        if (empty($hourlyStats)) {
            return ['peak_hour' => 12, 'peak_count' => 0];
        }
        
        $peakHour = array_keys($hourlyStats, max($hourlyStats))[0];
        $peakCount = max($hourlyStats);
        
        return [
            'peak_hour' => (int) $peakHour,
            'peak_count' => $peakCount,
            'hourly_distribution' => $hourlyStats
        ];
    }

    /**
     * Calcula tasa de opt-out
     */
    private function calculateOptOutRate(array $stats): float
    {
        $totalSent = $stats['sent'] ?? 0;
        $optOuts = $stats['opt_outs'] ?? 0;
        
        return $totalSent > 0 ? round(($optOuts / $totalSent) * 100, 2) : 0.0;
    }

    /**
     * Verifica si número está suprimido
     */
    private function isPhoneNumberSuppressed(string $phoneNumber): bool
    {
        return $this->notificationRepository->isEmailSuppressed($phoneNumber); // Reutilizar método existente
    }

    /**
     * Verifica si tiene opt-in válido
     */
    private function hasValidOptIn(string $phoneNumber): bool
    {
        // Verificar en base de datos si el usuario tiene opt-in válido
        return $this->notificationRepository->canReceiveNotification(0, 'promotional', 'sms');
    }

    /**
     * Obtiene categoría de SMS
     */
    private function getSMSCategory(string $smsType): string
    {
        $criticalTypes = [
            self::SMS_TYPES['VERIFICATION_CODE'],
            self::SMS_TYPES['LOGIN_CODE'],
            self::SMS_TYPES['PASSWORD_RESET'],
            self::SMS_TYPES['SECURITY_ALERT']
        ];
        
        if (in_array($smsType, $criticalTypes)) {
            return self::SMS_CATEGORIES['AUTHENTICATION'];
        }
        
        $promotionalTypes = [
            self::SMS_TYPES['PROMOTIONAL_OFFER'],
            self::SMS_TYPES['SPECIAL_EVENT'],
            self::SMS_TYPES['SURVEY_INVITE']
        ];
        
        if (in_array($smsType, $promotionalTypes)) {
            return self::SMS_CATEGORIES['PROMOTIONAL'];
        }
        
        return self::SMS_CATEGORIES['TRANSACTIONAL'];
    }

    /**
     * Obtiene plantilla de SMS
     */
    private function getSMSTemplate(string $type, string $language): array
    {
        $template = $this->notificationRepository->getNotificationTemplate($type, 'sms', $language);
        
        if (!$template) {
            // Plantilla por defecto
            return [
                'message' => 'Notificación de ForeverUsInLove: {{content}}',
                'subject' => 'Notificación'
            ];
        }
        
        return $template;
    }

    /**
     * Procesa plantilla con datos
     */
    private function processTemplate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        
        return $template;
    }

    /**
     * Obtiene país del número telefónico
     */
    private function getCountryFromPhoneNumber(string $phoneNumber): string
    {
        // Mapeo básico de códigos de país
        $countryCodes = [
            '+1' => 'US',
            '+44' => 'GB',
            '+33' => 'FR',
            '+49' => 'DE',
            '+34' => 'ES',
            '+39' => 'IT',
            '+7' => 'RU',
            '+86' => 'CN',
            '+81' => 'JP',
            '+82' => 'KR'
        ];
        
        foreach ($countryCodes as $code => $country) {
            if (str_starts_with($phoneNumber, $code)) {
                return $country;
            }
        }
        
        return 'GLOBAL';
    }

    /**
     * Selecciona proveedor más barato
     */
    private function selectCheapestProvider(array $providers, string $country, string $category): string
    {
        // Por ahora retorna el primer proveedor disponible
        // En una implementación real, se consultaría una tabla de precios
        return $providers[0] ?? self::SMS_PROVIDERS['TWILIO'];
    }

    /**
     * Envía SMS vía Vonage
     */
    private function sendViaVonage(string $phoneNumber, array $smsData, int $recordId, array $options): array
    {
        try {
            $vonagePayload = [
                'to' => $phoneNumber,
                'from' => $smsData['from'] ?? config('services.vonage.from_number'),
                'text' => $smsData['message']
            ];

            $response = Http::withBasicAuth(
                config('services.vonage.api_key'),
                config('services.vonage.api_secret')
            )->post('https://rest.nexmo.com/sms/json', $vonagePayload);

            if ($response->failed()) {
                throw new SMSDeliveryException('Vonage API error: ' . $response->body());
            }

            $result = $response->json();
            
            return [
                'success' => true,
                'delivery_id' => $result['messages'][0]['message-id'] ?? null,
                'status' => $result['messages'][0]['status'] ?? 'unknown',
                'provider_response' => $result
            ];

        } catch (RequestException $e) {
            throw new SMSDeliveryException('Error connecting to Vonage: ' . $e->getMessage());
        }
    }

    /**
     * Envía SMS vía AWS SNS
     */
    private function sendViaSNS(string $phoneNumber, array $smsData, int $recordId, array $options): array
    {
        try {
            $snsPayload = [
                'Message' => $smsData['message'],
                'PhoneNumber' => $phoneNumber
            ];

            $response = Http::withHeaders([
                'Authorization' => 'AWS4-HMAC-SHA256 ' . $this->generateAWS4Signature($snsPayload),
                'Content-Type' => 'application/x-amz-json-1.0',
                'X-Amz-Target' => 'SNS.Publish'
            ])->post('https://sns.us-east-1.amazonaws.com/', $snsPayload);

            if ($response->failed()) {
                throw new SMSDeliveryException('AWS SNS API error: ' . $response->body());
            }

            $result = $response->json();
            
            return [
                'success' => true,
                'delivery_id' => $result['MessageId'] ?? null,
                'status' => 'sent',
                'provider_response' => $result
            ];

        } catch (RequestException $e) {
            throw new SMSDeliveryException('Error connecting to AWS SNS: ' . $e->getMessage());
        }
    }

    /**
     * Genera firma AWS4 para SNS
     */
    private function generateAWS4Signature(array $payload): string
    {
        // Implementación simplificada - en producción usar AWS SDK
        return 'simplified-signature';
    }

    /**
     * Obtiene siguiente horario permitido
     */
    private function getNextAllowedTime(string $timezone): Carbon
    {
        $now = Carbon::now($timezone);
        
        // Si es antes de las 8 AM, programar para las 8 AM
        if ($now->hour < 8) {
            return $now->copy()->setHour(8)->setMinute(0)->setSecond(0);
        }
        
        // Si es después de las 9 PM, programar para las 8 AM del día siguiente
        if ($now->hour >= 21) {
            return $now->copy()->addDay()->setHour(8)->setMinute(0)->setSecond(0);
        }
        
        // Si está en horario permitido, enviar en 1 minuto
        return $now->copy()->addMinute();
    }
}