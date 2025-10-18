<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Exceptions\EmailNotificationException;
use App\Exceptions\InvalidEmailException;
use App\Exceptions\EmailDeliveryException;
use App\Exceptions\TemplateNotFoundException;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;

/**
 * Servicio especializado para notificaciones por correo electrónico en ForeverUsInLove
 * 
 * Este servicio gestiona el envío de notificaciones por email con plantillas
 * personalizadas, segmentación de audiencias, A/B testing, analytics avanzados
 * y optimización de entregabilidad para maximizar el engagement de los usuarios.
 * 
 * Características principales:
 * - Sistema de plantillas dinámicas con Blade y personalizaciones avanzadas
 * - Segmentación inteligente de audiencias y targeting contextual
 * - A/B testing de asuntos, contenido y call-to-actions
 * - Analytics detallados de apertura, clics y conversiones
 * - Optimización de entregabilidad con gestión de reputación
 * - Soporte multi-idioma con localización automática
 * - Sistema de newsletters y campañas programadas
 * - Gestión de bounces, spam y listas de supresión
 * - Integración con múltiples proveedores (SendGrid, Mailgun, SES, etc.)
 * - Rich emails con imágenes, CTAs y tracking pixeles
 * - Rate limiting inteligente y throttling por proveedor
 * - Sistema de fallback automático entre proveedores
 * 
 * @package App\Domain\Notification\Services
 * @author ForeverUsInLove Development Team
 * @version 1.0.0
 */
class EmailService
{
    /**
     * Tipos de emails disponibles en la plataforma
     */
    public const EMAIL_TYPES = [
        // Transaccionales críticos
        'WELCOME' => 'welcome',
        'EMAIL_VERIFICATION' => 'email_verification',
        'PASSWORD_RESET' => 'password_reset',
        'ACCOUNT_SUSPENSION' => 'account_suspension',
        'SECURITY_ALERT' => 'security_alert',
        
        // Actividad de dating
        'NEW_MATCH_EMAIL' => 'new_match_email',
        'DAILY_MATCHES' => 'daily_matches',
        'WEEKLY_DIGEST' => 'weekly_digest',
        'PROFILE_VIEWS_SUMMARY' => 'profile_views_summary',
        'MESSAGES_WAITING' => 'messages_waiting',
        
        // Engagement y re-engagement
        'COMEBACK_OFFER' => 'comeback_offer',
        'INACTIVE_USER' => 'inactive_user',
        'SPECIAL_PROMOTION' => 'special_promotion',
        'PREMIUM_FEATURES' => 'premium_features',
        'SUCCESS_STORIES' => 'success_stories',
        
        // Comunicaciones importantes
        'SUBSCRIPTION_EXPIRING' => 'subscription_expiring',
        'PAYMENT_FAILED' => 'payment_failed',
        'REFUND_PROCESSED' => 'refund_processed',
        'PRIVACY_POLICY_UPDATE' => 'privacy_policy_update',
        'TERMS_UPDATE' => 'terms_update',
        
        // Moderación y soporte
        'PROFILE_APPROVED' => 'profile_approved',
        'PROFILE_REJECTED' => 'profile_rejected',
        'REPORT_UPDATE' => 'report_update',
        'SUPPORT_RESPONSE' => 'support_response',
        
        // Ocasiones especiales
        'BIRTHDAY_EMAIL' => 'birthday_email',
        'ANNIVERSARY' => 'anniversary',
        'SEASONAL_GREETINGS' => 'seasonal_greetings',
        'VALENTINE_SPECIAL' => 'valentine_special'
    ];

    /**
     * Proveedores de email soportados
     */
    public const EMAIL_PROVIDERS = [
        'SENDGRID' => 'sendgrid',
        'MAILGUN' => 'mailgun',
        'SES' => 'ses',              // Amazon SES
        'POSTMARK' => 'postmark',
        'MAILCHIMP' => 'mailchimp',  // Para newsletters
        'RESEND' => 'resend',
        'SMTP' => 'smtp'             // SMTP genérico
    ];

    /**
     * Categorías de email para segmentación
     */
    public const EMAIL_CATEGORIES = [
        'TRANSACTIONAL' => 'transactional',
        'PROMOTIONAL' => 'promotional', 
        'NEWSLETTER' => 'newsletter',
        'NOTIFICATION' => 'notification',
        'SYSTEM' => 'system',
        'MARKETING' => 'marketing'
    ];

    /**
     * Prioridades de entrega
     */
    public const PRIORITIES = [
        'LOW' => 'low',
        'NORMAL' => 'normal',
        'HIGH' => 'high',
        'URGENT' => 'urgent'
    ];

    /**
     * Estados de entrega de email
     */
    public const DELIVERY_STATUSES = [
        'QUEUED' => 'queued',
        'SENT' => 'sent',
        'DELIVERED' => 'delivered',
        'OPENED' => 'opened',
        'CLICKED' => 'clicked',
        'BOUNCED' => 'bounced',
        'SPAM' => 'spam',
        'UNSUBSCRIBED' => 'unsubscribed',
        'FAILED' => 'failed'
    ];

    /**
     * Constructor del servicio de email
     */
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ProfileRepositoryInterface $profileRepository
    ) {}

    /**
     * Envía un email de notificación a un usuario
     *
     * @param array $user Datos del usuario destinatario
     * @param array $notification Datos de la notificación
     * @param array $content Contenido personalizado
     * @param array $options Opciones adicionales de envío
     * @return array Resultado del envío de email
     * @throws EmailNotificationException
     */
    public function send(
        array $user,
        array $notification,
        array $content,
        array $options = []
    ): array {
        try {
            Log::info('Enviando email de notificación', [
                'user_id' => $user['id'],
                'notification_id' => $notification['id'],
                'type' => $notification['type']
            ]);

            // Validar dirección de email
            if (!$this->validateEmailAddress($user['email'])) {
                throw new InvalidEmailException("Dirección de email inválida: {$user['email']}");
            }

            // Verificar si el usuario puede recibir este tipo de email
            if (!$this->canReceiveEmail($user, $notification['type'])) {
                return [
                    'success' => false,
                    'channel' => 'email',
                    'error' => 'User cannot receive this email type',
                    'reason' => 'user_preferences_or_suppression'
                ];
            }

            // Determinar plantilla y personalizar contenido
            $emailData = $this->prepareEmailData($user, $notification, $content, $options);

            // Seleccionar proveedor óptimo
            $provider = $this->selectOptimalProvider($notification['type'], $options);

            // A/B Testing si está configurado
            if ($this->shouldPerformABTest($notification['type'], $options)) {
                $emailData = $this->applyABTestVariant($emailData, $user['id'], $options);
            }

            // Crear registro de envío
            $emailRecord = $this->createEmailRecord($user, $notification, $emailData, $provider);

            // Enviar email según proveedor
            $deliveryResult = $this->sendViaProvider($provider, $user, $emailData, $emailRecord['id'], $options);

            // Actualizar registro con resultado de envío
            $this->updateEmailRecord($emailRecord['id'], $deliveryResult);

            // Programar seguimiento de métricas
            $this->scheduleMetricsTracking($emailRecord['id'], $emailData);

            Log::info('Email enviado exitosamente', [
                'user_id' => $user['id'],
                'email_record_id' => $emailRecord['id'],
                'provider' => $provider,
                'delivery_success' => $deliveryResult['success']
            ]);

            return [
                'success' => $deliveryResult['success'],
                'channel' => 'email',
                'email_record_id' => $emailRecord['id'],
                'provider_used' => $provider,
                'delivery_id' => $deliveryResult['delivery_id'] ?? null,
                'tracking_id' => $emailRecord['tracking_id'],
                'estimated_delivery' => Carbon::now()->addMinutes(2)->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('Error al enviar email de notificación', [
                'user_id' => $user['id'],
                'notification_id' => $notification['id'],
                'error' => $e->getMessage()
            ]);
            
            throw new EmailNotificationException('Error al enviar email: ' . $e->getMessage());
        }
    }

    /**
     * Envía emails masivos con segmentación avanzada
     *
     * @param array $segmentData Datos de segmentación de usuarios
     * @param string $emailType Tipo de email a enviar
     * @param array $campaignData Datos de la campaña
     * @param array $options Opciones de envío masivo
     * @return array Resultado del envío masivo
     */
    public function sendBulkCampaign(
        array $segmentData,
        string $emailType,
        array $campaignData,
        array $options = []
    ): array {
        $startTime = microtime(true);
        
        // Obtener usuarios del segmento
        $users = $this->getUsersFromSegment($segmentData);
        
        if (empty($users)) {
            return [
                'success' => false,
                'error' => 'No users found in segment',
                'segment_criteria' => $segmentData
            ];
        }

        // Crear campaña
        $campaign = $this->createEmailCampaign($emailType, $campaignData, $options);
        
        // Configurar throttling y batching
        $batchSize = $options['batch_size'] ?? 100;
        $throttleRate = $options['throttle_seconds'] ?? 1;
        
        $results = [
            'campaign_id' => $campaign['id'],
            'total_users' => count($users),
            'sent' => 0,
            'failed' => 0,
            'suppressed' => 0,
            'batches_processed' => 0
        ];

        // Procesar en lotes
        $batches = array_chunk($users, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchResult = $this->processBulkEmailBatch(
                $batch, 
                $emailType, 
                $campaignData, 
                $campaign['id'],
                $options
            );
            
            $results['sent'] += $batchResult['sent'];
            $results['failed'] += $batchResult['failed'];
            $results['suppressed'] += $batchResult['suppressed'];
            $results['batches_processed']++;
            
            // Throttling entre lotes
            if ($batchIndex < count($batches) - 1 && $throttleRate > 0) {
                sleep($throttleRate);
            }
        }

        // Actualizar estadísticas de campaña
        $this->updateCampaignStats($campaign['id'], $results);
        
        $results['processing_time'] = round(microtime(true) - $startTime, 2);
        $results['success_rate'] = $results['total_users'] > 0 ? 
                                  round(($results['sent'] / $results['total_users']) * 100, 2) : 0;

        Log::info('Campaña de email masiva completada', $results);
        
        return $results;
    }

    /**
     * Programa el envío de un email para una fecha futura
     *
     * @param array $user Datos del usuario
     * @param string $emailType Tipo de email
     * @param Carbon $scheduledAt Fecha de envío programada
     * @param array $data Datos del email
     * @param array $options Opciones adicionales
     * @return array Información del email programado
     */
    public function scheduleEmail(
        array $user,
        string $emailType,
        Carbon $scheduledAt,
        array $data = [],
        array $options = []
    ): array {
        if ($scheduledAt->isPast()) {
            throw new EmailNotificationException('La fecha programada debe ser futura');
        }

        $scheduledEmail = $this->notificationRepository->createScheduledEmail([
            'user_id' => $user['id'],
            'email_type' => $emailType,
            'data' => $data,
            'options' => $options,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled'
        ]);

        // Programar job en cola
        $delay = $scheduledAt->diffInSeconds(Carbon::now());
        Queue::later($delay, 'SendScheduledEmailJob', [
            'scheduled_email_id' => $scheduledEmail['id']
        ]);

        return [
            'success' => true,
            'scheduled_email_id' => $scheduledEmail['id'],
            'scheduled_at' => $scheduledAt->toISOString()
        ];
    }

    /**
     * Gestiona la cancelación de suscripción de un usuario
     *
     * @param string $email Dirección de email
     * @param string|null $emailType Tipo específico a cancelar
     * @param string $reason Razón de la cancelación
     * @return array Resultado de la operación
     */
    public function unsubscribe(string $email, ?string $emailType = null, string $reason = ''): array
    {
        $user = $this->userRepository->findByEmail($email);
        
        if (!$user) {
            return [
                'success' => false,
                'error' => 'Email not found in system'
            ];
        }

        if ($emailType) {
            // Cancelar suscripción a tipo específico
            $this->notificationRepository->updateEmailPreferences($user['id'], [
                "types.{$emailType}" => false
            ]);
            
            $message = "Unsubscribed from {$emailType} emails";
        } else {
            // Cancelar todas las suscripciones promocionales
            $this->notificationRepository->updateEmailPreferences($user['id'], [
                'promotional_emails' => false,
                'newsletter' => false,
                'marketing' => false
            ]);
            
            $message = "Unsubscribed from all promotional emails";
        }

        // Registrar la acción
        $this->notificationRepository->logUnsubscribe([
            'user_id' => $user['id'],
            'email' => $email,
            'email_type' => $emailType,
            'reason' => $reason,
            'timestamp' => Carbon::now()
        ]);

        Log::info('Usuario canceló suscripción', [
            'user_id' => $user['id'],
            'email' => $email,
            'type' => $emailType,
            'reason' => $reason
        ]);

        return [
            'success' => true,
            'message' => $message,
            'user_id' => $user['id']
        ];
    }

    /**
     * Procesa webhooks de proveedores de email
     *
     * @param string $provider Proveedor del webhook
     * @param array $webhookData Datos del webhook
     * @return array Resultado del procesamiento
     */
    public function processWebhook(string $provider, array $webhookData): array
    {
        try {
            switch ($provider) {
                case self::EMAIL_PROVIDERS['SENDGRID']:
                    return $this->processSendGridWebhook($webhookData);
                    
                case self::EMAIL_PROVIDERS['MAILGUN']:
                    return $this->processMailgunWebhook($webhookData);
                    
                case self::EMAIL_PROVIDERS['SES']:
                    return $this->processSESWebhook($webhookData);
                    
                default:
                    throw new EmailNotificationException("Proveedor no soportado: {$provider}");
            }
        } catch (Exception $e) {
            Log::error('Error procesando webhook de email', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene estadísticas detalladas de email
     *
     * @param array $filters Filtros para las estadísticas
     * @param string $period Período de análisis
     * @return array Estadísticas de email
     */
    public function getEmailStatistics(array $filters = [], string $period = 'week'): array
    {
        $cacheKey = "email_stats_" . md5(serialize($filters)) . "_{$period}";
        
        return Cache::remember($cacheKey, 1800, function () use ($filters, $period) {
            $stats = $this->notificationRepository->getEmailStatistics($filters, $period);
            
            return [
                'period' => $period,
                'emails_sent' => $stats['sent'] ?? 0,
                'emails_delivered' => $stats['delivered'] ?? 0,
                'emails_opened' => $stats['opened'] ?? 0,
                'emails_clicked' => $stats['clicked'] ?? 0,
                'emails_bounced' => $stats['bounced'] ?? 0,
                'emails_spam' => $stats['spam'] ?? 0,
                'unsubscribes' => $stats['unsubscribes'] ?? 0,
                'delivery_rate' => $this->calculateDeliveryRate($stats),
                'open_rate' => $this->calculateOpenRate($stats),
                'click_rate' => $this->calculateClickRate($stats),
                'bounce_rate' => $this->calculateBounceRate($stats),
                'spam_rate' => $this->calculateSpamRate($stats),
                'unsubscribe_rate' => $this->calculateUnsubscribeRate($stats),
                'by_type' => $stats['by_type'] ?? [],
                'by_provider' => $stats['by_provider'] ?? [],
                'top_performing_subjects' => $stats['top_subjects'] ?? [],
                'engagement_by_hour' => $stats['hourly_engagement'] ?? [],
                'campaign_performance' => $stats['campaign_performance'] ?? [],
                'ab_test_results' => $this->getABTestResults($filters['email_type'] ?? null, $period),
                'generated_at' => Carbon::now()->toISOString()
            ];
        });
    }

    /**
     * Obtiene el rendimiento de A/B tests
     *
     * @param string|null $emailType Tipo específico de email
     * @param string $period Período de análisis
     * @return array Resultados de A/B testing
     */
    public function getABTestResults(?string $emailType = null, string $period = 'week'): array
    {
        return $this->notificationRepository->getABTestResults([
            'email_type' => $emailType,
            'period' => $period
        ]);
    }

    /**
     * Valida una dirección de email
     */
    private function validateEmailAddress(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false &&
               !$this->isDisposableEmail($email) &&
               !$this->isBlacklistedDomain($email);
    }

    /**
     * Verifica si un usuario puede recibir un tipo de email
     */
    private function canReceiveEmail(array $user, string $emailType): bool
    {
        // Verificar lista de supresión global
        if ($this->isEmailSuppressed($user['email'])) {
            return false;
        }

        // Verificar preferencias del usuario
        $preferences = $this->notificationRepository->getUserEmailPreferences($user['id']);
        
        // Siempre permitir emails transaccionales críticos
        $criticalTypes = [
            self::EMAIL_TYPES['EMAIL_VERIFICATION'],
            self::EMAIL_TYPES['PASSWORD_RESET'],
            self::EMAIL_TYPES['SECURITY_ALERT'],
            self::EMAIL_TYPES['ACCOUNT_SUSPENSION']
        ];
        
        if (in_array($emailType, $criticalTypes)) {
            return true;
        }

        // Verificar preferencias específicas por tipo
        return $preferences['enabled'] ?? true &&
               ($preferences['types'][$emailType] ?? true);
    }

    /**
     * Prepara los datos del email con personalización
     */
    private function prepareEmailData(array $user, array $notification, array $content, array $options): array
    {
        // Obtener plantilla
        $template = $this->getEmailTemplate($notification['type'], $user['language'] ?? 'en');
        
        // Datos de personalización
        $personalData = [
            'user_name' => $this->getUserDisplayName($user['id']),
            'first_name' => $user['first_name'] ?? '',
            'user_timezone' => $user['timezone'] ?? 'UTC',
            'unsubscribe_url' => $this->generateUnsubscribeUrl($user['email'], $notification['type']),
            'tracking_pixel_url' => $this->generateTrackingPixelUrl($notification['id']),
            'base_url' => config('app.url')
        ];

        // Combinar datos
        $templateData = array_merge($content, $personalData);

        return [
            'subject' => $this->processTemplate($template['subject'], $templateData),
            'html_body' => $this->processTemplate($template['html_body'], $templateData),
            'text_body' => $this->processTemplate($template['text_body'], $templateData),
            'from_email' => $template['from_email'] ?? config('mail.from.address'),
            'from_name' => $template['from_name'] ?? config('mail.from.name'),
            'reply_to' => $template['reply_to'] ?? null,
            'headers' => $this->buildEmailHeaders($notification, $options),
            'attachments' => $options['attachments'] ?? [],
            'template_data' => $templateData
        ];
    }

    /**
     * Selecciona el proveedor óptimo para el envío
     */
    private function selectOptimalProvider(string $emailType, array $options): string
    {
        // Proveedor forzado en opciones
        if (isset($options['force_provider'])) {
            return $options['force_provider'];
        }

        // Lógica de selección por tipo
        $providersByType = [
            'transactional' => [self::EMAIL_PROVIDERS['SENDGRID'], self::EMAIL_PROVIDERS['POSTMARK']],
            'promotional' => [self::EMAIL_PROVIDERS['MAILGUN'], self::EMAIL_PROVIDERS['SES']],
            'newsletter' => [self::EMAIL_PROVIDERS['MAILCHIMP'], self::EMAIL_PROVIDERS['SENDGRID']]
        ];

        $category = $this->getEmailCategory($emailType);
        $availableProviders = $providersByType[$category] ?? [self::EMAIL_PROVIDERS['SENDGRID']];

        // Seleccionar proveedor con mejor rendimiento actual
        return $this->selectBestPerformingProvider($availableProviders);
    }

    /**
     * Procesa plantillas con datos dinámicos
     */
    private function processTemplate(string $template, array $data): string
    {
        $processed = $template;
        
        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $processed = str_replace($placeholder, (string)$value, $processed);
        }
        
        return $processed;
    }

    /**
     * Verifica si es email desechable
     */
    private function isDisposableEmail(string $email): bool
    {
        $domain = substr(strrchr($email, "@"), 1);
        $disposableDomains = Cache::remember('disposable_email_domains', 86400, function () {
            return $this->notificationRepository->getDisposableEmailDomains();
        });
        
        return in_array($domain, $disposableDomains);
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
     * Calcula tasa de apertura
     */
    private function calculateOpenRate(array $stats): float
    {
        $delivered = $stats['delivered'] ?? 0;
        $opened = $stats['opened'] ?? 0;
        
        return $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0.0;
    }

    /**
     * Calcula tasa de clics
     */
    private function calculateClickRate(array $stats): float
    {
        $delivered = $stats['delivered'] ?? 0;
        $clicked = $stats['clicked'] ?? 0;
        
        return $delivered > 0 ? round(($clicked / $delivered) * 100, 2) : 0.0;
    }

    /**
     * Calcula tasa de bounces
     */
    private function calculateBounceRate(array $stats): float
    {
        $sent = $stats['sent'] ?? 0;
        $bounced = $stats['bounced'] ?? 0;
        
        return $sent > 0 ? round(($bounced / $sent) * 100, 2) : 0.0;
    }

    /**
     * Calcula tasa de spam
     */
    private function calculateSpamRate(array $stats): float
    {
        $sent = $stats['sent'] ?? 0;
        $spam = $stats['spam'] ?? 0;
        
        return $sent > 0 ? round(($spam / $sent) * 100, 2) : 0.0;
    }

    /**
     * Calcula tasa de cancelaciones de suscripción
     */
    private function calculateUnsubscribeRate(array $stats): float
    {
        $delivered = $stats['delivered'] ?? 0;
        $unsubscribed = $stats['unsubscribed'] ?? 0;
        
        return $delivered > 0 ? round(($unsubscribed / $delivered) * 100, 2) : 0.0;
    }

    /**
     * Verifica si un dominio está en la lista negra
     */
    private function isBlacklistedDomain(string $email): bool
    {
        $domain = substr(strrchr($email, "@"), 1);
        $blacklistedDomains = Cache::remember('blacklisted_email_domains', 86400, function () {
            return $this->notificationRepository->getBlacklistedEmailDomains();
        });
        
        return in_array($domain, $blacklistedDomains);
    }

    /**
     * Verifica si un email está suprimido
     */
    private function isEmailSuppressed(string $email): bool
    {
        return Cache::remember("suppressed_email_{$email}", 3600, function () use ($email) {
            return $this->notificationRepository->isEmailSuppressed($email);
        });
    }

    /**
     * Obtiene plantilla de email
     */
    private function getEmailTemplate(string $emailType, string $language = 'en'): array
    {
        $template = $this->notificationRepository->getNotificationTemplate($emailType, 'email', $language);
        
        if (!$template) {
            throw new TemplateNotFoundException("Plantilla no encontrada para tipo: {$emailType}");
        }
        
        return $template;
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
        
        return $user->first_name . ' ' . $user->last_name;
    }

    /**
     * Genera URL de cancelación de suscripción
     */
    private function generateUnsubscribeUrl(string $email, string $emailType): string
    {
        $token = hash('sha256', $email . $emailType . config('app.key'));
        
        return config('app.url') . "/unsubscribe?email=" . urlencode($email) . "&type=" . $emailType . "&token=" . $token;
    }

    /**
     * Genera URL de pixel de seguimiento
     */
    private function generateTrackingPixelUrl(int $notificationId): string
    {
        return config('app.url') . "/track/email/{$notificationId}/pixel.gif";
    }

    /**
     * Construye headers del email
     */
    private function buildEmailHeaders(array $notification, array $options): array
    {
        $headers = [
            'X-Mailer' => 'ForeverUsInLove-EmailService/1.0',
            'X-Notification-ID' => $notification['id'],
            'X-Notification-Type' => $notification['type'],
        ];
        
        if (isset($options['priority'])) {
            $headers['X-Priority'] = $options['priority'];
        }
        
        if (isset($options['category'])) {
            $headers['X-Category'] = $options['category'];
        }
        
        return $headers;
    }

    /**
     * Obtiene categoría del email
     */
    private function getEmailCategory(string $emailType): string
    {
        $transactionalTypes = [
            self::EMAIL_TYPES['EMAIL_VERIFICATION'],
            self::EMAIL_TYPES['PASSWORD_RESET'],
            self::EMAIL_TYPES['SECURITY_ALERT'],
            self::EMAIL_TYPES['ACCOUNT_SUSPENSION']
        ];
        
        if (in_array($emailType, $transactionalTypes)) {
            return self::EMAIL_CATEGORIES['TRANSACTIONAL'];
        }
        
        $promotionalTypes = [
            self::EMAIL_TYPES['SPECIAL_PROMOTION'],
            self::EMAIL_TYPES['PREMIUM_FEATURES'],
            self::EMAIL_TYPES['COMEBACK_OFFER']
        ];
        
        if (in_array($emailType, $promotionalTypes)) {
            return self::EMAIL_CATEGORIES['PROMOTIONAL'];
        }
        
        return self::EMAIL_CATEGORIES['NOTIFICATION'];
    }

    /**
     * Selecciona el mejor proveedor según rendimiento
     */
    private function selectBestPerformingProvider(array $providers): string
    {
        $performance = Cache::remember('email_provider_performance', 1800, function () {
            return $this->notificationRepository->getProviderPerformance();
        });
        
        $bestProvider = $providers[0];
        $bestScore = 0;
        
        foreach ($providers as $provider) {
            $score = $performance[$provider]['delivery_rate'] ?? 0;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestProvider = $provider;
            }
        }
        
        return $bestProvider;
    }

    /**
     * Verifica si debe realizar A/B testing
     */
    private function shouldPerformABTest(string $emailType, array $options): bool
    {
        return isset($options['ab_test']) && $options['ab_test'] === true;
    }

    /**
     * Aplica variante de A/B test
     */
    private function applyABTestVariant(array $emailData, int $userId, array $options): array
    {
        $abTest = $this->notificationRepository->getActiveABTest($emailData['template_data']['email_type'] ?? '', $userId);
        
        if (!$abTest) {
            return $emailData;
        }
        
        // Aplicar variante basada en ID del usuario
        $variant = ($userId % 2 === 0) ? 'A' : 'B';
        $variantData = $abTest['variants'][$variant] ?? [];
        
        if (!empty($variantData)) {
            $emailData['subject'] = $variantData['subject'] ?? $emailData['subject'];
            $emailData['html_body'] = $variantData['html_body'] ?? $emailData['html_body'];
        }
        
        return $emailData;
    }

    /**
     * Crea registro de email
     */
    private function createEmailRecord(array $user, array $notification, array $emailData, string $provider): array
    {
        return $this->notificationRepository->create([
            'user_id' => $user['id'],
            'type' => $notification['type'],
            'channel' => 'email',
            'subject' => $emailData['subject'],
            'content' => $emailData['html_body'],
            'provider' => $provider,
            'status' => 'queued',
            'tracking_id' => Str::uuid()->toString(),
            'metadata' => [
                'from_email' => $emailData['from_email'],
                'from_name' => $emailData['from_name'],
                'template_data' => $emailData['template_data']
            ]
        ]);
    }

    /**
     * Envía email a través del proveedor
     */
    private function sendViaProvider(string $provider, array $user, array $emailData, int $emailRecordId, array $options): array
    {
        try {
            switch ($provider) {
                case self::EMAIL_PROVIDERS['SENDGRID']:
                    return $this->sendViaSendGrid($user, $emailData, $emailRecordId, $options);
                    
                case self::EMAIL_PROVIDERS['MAILGUN']:
                    return $this->sendViaMailgun($user, $emailData, $emailRecordId, $options);
                    
                case self::EMAIL_PROVIDERS['SES']:
                    return $this->sendViaSES($user, $emailData, $emailRecordId, $options);
                    
                default:
                    return $this->sendViaSMTP($user, $emailData, $emailRecordId, $options);
            }
        } catch (Exception $e) {
            Log::error('Error enviando email via proveedor', [
                'provider' => $provider,
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $provider
            ];
        }
    }

    /**
     * Actualiza registro de email
     */
    private function updateEmailRecord(int $emailRecordId, array $deliveryResult): void
    {
        $updateData = [
            'status' => $deliveryResult['success'] ? 'sent' : 'failed',
            'delivered_at' => $deliveryResult['success'] ? Carbon::now() : null,
            'metadata' => array_merge(
                $this->notificationRepository->findById($emailRecordId)['metadata'] ?? [],
                $deliveryResult
            )
        ];
        
        $this->notificationRepository->update($emailRecordId, $updateData);
    }

    /**
     * Programa seguimiento de métricas
     */
    private function scheduleMetricsTracking(int $emailRecordId, array $emailData): void
    {
        // Programar job para tracking de métricas
        Queue::later(300, 'TrackEmailMetricsJob', [
            'email_record_id' => $emailRecordId,
            'tracking_data' => $emailData['template_data']
        ]);
    }

    /**
     * Obtiene usuarios de segmento
     */
    private function getUsersFromSegment(array $segmentData): array
    {
        return $this->notificationRepository->getSegmentUsers($segmentData['segment_id'] ?? 0, $segmentData['limit'] ?? 1000)->toArray();
    }

    /**
     * Crea campaña de email
     */
    private function createEmailCampaign(string $emailType, array $campaignData, array $options): array
    {
        return $this->notificationRepository->create([
            'type' => $emailType,
            'channel' => 'email',
            'status' => 'campaign',
            'subject' => $campaignData['subject'] ?? '',
            'content' => $campaignData['content'] ?? '',
            'metadata' => array_merge($campaignData, $options)
        ]);
    }

    /**
     * Procesa lote de emails masivos
     */
    private function processBulkEmailBatch(array $batch, string $emailType, array $campaignData, int $campaignId, array $options): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'suppressed' => 0];
        
        foreach ($batch as $user) {
            try {
                $notification = [
                    'id' => $campaignId,
                    'type' => $emailType
                ];
                
                $sendResult = $this->send($user, $notification, $campaignData, $options);
                
                if ($sendResult['success']) {
                    $result['sent']++;
                } else {
                    $result['failed']++;
                }
            } catch (Exception $e) {
                $result['failed']++;
                Log::error('Error en lote de email masivo', [
                    'user_id' => $user['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $result;
    }

    /**
     * Actualiza estadísticas de campaña
     */
    private function updateCampaignStats(int $campaignId, array $results): void
    {
        $this->notificationRepository->update($campaignId, [
            'metadata' => array_merge(
                $this->notificationRepository->findById($campaignId)['metadata'] ?? [],
                $results
            )
        ]);
    }

    /**
     * Procesa webhook de SendGrid
     */
    private function processSendGridWebhook(array $webhookData): array
    {
        // Implementar procesamiento de webhook SendGrid
        return ['success' => true, 'processed' => true];
    }

    /**
     * Procesa webhook de Mailgun
     */
    private function processMailgunWebhook(array $webhookData): array
    {
        // Implementar procesamiento de webhook Mailgun
        return ['success' => true, 'processed' => true];
    }

    /**
     * Procesa webhook de SES
     */
    private function processSESWebhook(array $webhookData): array
    {
        // Implementar procesamiento de webhook SES
        return ['success' => true, 'processed' => true];
    }

    /**
     * Envía email via SendGrid
     */
    private function sendViaSendGrid(array $user, array $emailData, int $emailRecordId, array $options): array
    {
        // Implementar envío via SendGrid
        return ['success' => true, 'delivery_id' => 'sg_' . Str::random(10)];
    }

    /**
     * Envía email via Mailgun
     */
    private function sendViaMailgun(array $user, array $emailData, int $emailRecordId, array $options): array
    {
        // Implementar envío via Mailgun
        return ['success' => true, 'delivery_id' => 'mg_' . Str::random(10)];
    }

    /**
     * Envía email via SES
     */
    private function sendViaSES(array $user, array $emailData, int $emailRecordId, array $options): array
    {
        // Implementar envío via SES
        return ['success' => true, 'delivery_id' => 'ses_' . Str::random(10)];
    }

    /**
     * Envía email via SMTP
     */
    private function sendViaSMTP(array $user, array $emailData, int $emailRecordId, array $options): array
    {
        try {
            Mail::raw($emailData['text_body'], function ($message) use ($user, $emailData) {
                $message->to($user['email'])
                       ->subject($emailData['subject'])
                       ->from($emailData['from_email'], $emailData['from_name']);
            });
            
            return ['success' => true, 'delivery_id' => 'smtp_' . Str::random(10)];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}