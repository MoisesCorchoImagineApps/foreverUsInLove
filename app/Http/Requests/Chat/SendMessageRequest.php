<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\Chat\Message;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SendMessageRequest - Validación para envío de mensajes
 * 
 * Este FormRequest maneja la validación de datos para el envío de mensajes
 * en la aplicación ForeverUsInLove, incluyendo validaciones específicas para
 * diferentes tipos de mensajes, archivos adjuntos y metadatos.
 * 
 * Integrado con:
 * - MessageService para lógica de negocio
 * - MessageSent event para notificaciones
 * - ChatService para validaciones de chat
 * - MessageRepository para persistencia
 * 
 * @package App\Http\Requests\Chat
 * @version 1.0.0
 * @author ForeverUsInLove Team
 */
class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // El usuario debe estar autenticado
        $user = $this->user();
        if (!$user) {
            return false;
        }

        // El usuario debe tener un perfil activo
        if (!$user->profile || $user->profile->status !== 'active') {
            return false;
        }

        // El usuario debe ser participante del chat
        $chatId = $this->route('chat');
        if ($chatId) {
            $chat = \App\Models\Chat\Chat::find($chatId);
            return $chat && $chat->hasParticipant($user->id) && $chat->isActive();
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * 
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // ========================================
            // DATOS BÁSICOS DEL MENSAJE
            // ========================================
            
            'chat_id' => [
                'required',
                'integer',
                'exists:chats,id',
                function ($attribute, $value, $fail) {
                    $chat = \App\Models\Chat\Chat::find($value);
                    if ($chat && !$chat->isActive()) {
                        $fail('El chat no está activo.');
                    }
                },
            ],
            
            'type' => [
                'required',
                'string',
                Rule::in([
                    Message::TYPE_TEXT,
                    Message::TYPE_IMAGE,
                    Message::TYPE_VIDEO,
                    Message::TYPE_VOICE,
                    Message::TYPE_FILE,
                    Message::TYPE_STICKER,
                    Message::TYPE_GIF,
                    Message::TYPE_ICEBREAKER,
                ]),
            ],
            
            'content' => [
                'required_if:type,' . Message::TYPE_TEXT,
                'nullable',
                'string',
                'max:' . Message::MAX_TEXT_LENGTH,
                'min:1',
                function ($attribute, $value, $fail) {
                    if ($this->get('type') === Message::TYPE_TEXT && empty(trim($value))) {
                        $fail('El contenido del mensaje no puede estar vacío.');
                    }
                },
            ],
            
            // ========================================
            // ARCHIVOS ADJUNTOS
            // ========================================
            
            'attachments' => [
                'sometimes',
                'array',
                'max:10', // Máximo 10 archivos por mensaje
            ],
            
            'attachments.*.file' => [
                'required_with:attachments',
                'file',
                'max:' . (Message::MAX_FILE_SIZE_MB * 1024), // Convertir MB a KB
                function ($attribute, $value, $fail) {
                    $messageType = $this->get('type');
                    $mimeType = $value->getMimeType();
                    
                    // Validar tipo de archivo según el tipo de mensaje
                    switch ($messageType) {
                        case Message::TYPE_IMAGE:
                            if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                                $fail('Solo se permiten archivos de imagen (JPEG, PNG, GIF, WebP).');
                            }
                            break;
                        case Message::TYPE_VIDEO:
                            if (!in_array($mimeType, ['video/mp4', 'video/quicktime', 'video/avi', 'video/webm'])) {
                                $fail('Solo se permiten archivos de video (MP4, MOV, AVI, WebM).');
                            }
                            break;
                        case Message::TYPE_VOICE:
                            if (!in_array($mimeType, ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/m4a'])) {
                                $fail('Solo se permiten archivos de audio (MP3, WAV, OGG, M4A).');
                            }
                            break;
                        case Message::TYPE_FILE:
                            // Permitir archivos de documentos comunes
                            $allowedMimes = [
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'text/plain',
                                'application/zip',
                                'application/x-rar-compressed',
                            ];
                            if (!in_array($mimeType, $allowedMimes)) {
                                $fail('Tipo de archivo no permitido.');
                            }
                            break;
                    }
                },
            ],
            
            'attachments.*.filename' => [
                'sometimes',
                'string',
                'max:255',
            ],
            
            'attachments.*.description' => [
                'sometimes',
                'string',
                'max:500',
            ],
            
            // ========================================
            // MENSAJE COMO RESPUESTA
            // ========================================
            
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:messages,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $parentMessage = Message::find($value);
                        if (!$parentMessage || $parentMessage->chat_id != $this->get('chat_id')) {
                            $fail('El mensaje al que intentas responder no existe en este chat.');
                        }
                    }
                },
            ],
            
            // ========================================
            // PROGRAMACIÓN Y EXPIRACIÓN
            // ========================================
            
            'scheduled_at' => [
                'sometimes',
                'nullable',
                'date',
                'after:now',
                'before:' . now()->addDays(30)->format('Y-m-d H:i:s'),
            ],
            
            'expires_at' => [
                'sometimes',
                'nullable',
                'date',
                'after:now',
                'before:' . now()->addDays(7)->format('Y-m-d H:i:s'),
            ],
            
            // ========================================
            // CONFIGURACIÓN DE PRIVACIDAD
            // ========================================
            
            'is_encrypted' => [
                'sometimes',
                'boolean',
            ],
            
            'is_system_message' => [
                'sometimes',
                'boolean',
            ],
            
            // ========================================
            // METADATOS ADICIONALES
            // ========================================
            
            'metadata' => [
                'sometimes',
                'array',
            ],
            
            'metadata.location' => [
                'sometimes',
                'array',
            ],
            
            'metadata.location.latitude' => [
                'required_with:metadata.location',
                'numeric',
                'between:-90,90',
            ],
            
            'metadata.location.longitude' => [
                'required_with:metadata.location',
                'numeric',
                'between:-180,180',
            ],
            
            'metadata.icebreaker_data' => [
                'sometimes',
                'array',
            ],
            
            'metadata.icebreaker_data.question_id' => [
                'required_with:metadata.icebreaker_data',
                'integer',
                'exists:icebreaker_questions,id',
            ],
            
            'metadata.icebreaker_data.answer' => [
                'required_with:metadata.icebreaker_data',
                'string',
                'max:500',
            ],
            
            // ========================================
            // CONFIGURACIÓN DE NOTIFICACIONES
            // ========================================
            
            'notification_settings' => [
                'sometimes',
                'array',
            ],
            
            'notification_settings.silent' => [
                'sometimes',
                'boolean',
            ],
            
            'notification_settings.priority' => [
                'sometimes',
                'string',
                Rule::in(['low', 'normal', 'high']),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     * 
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Chat ID
            'chat_id.required' => 'El ID del chat es obligatorio.',
            'chat_id.exists' => 'El chat especificado no existe.',
            
            // Tipo de mensaje
            'type.required' => 'El tipo de mensaje es obligatorio.',
            'type.in' => 'El tipo de mensaje no es válido.',
            
            // Contenido
            'content.required_if' => 'El contenido es obligatorio para mensajes de texto.',
            'content.max' => 'El mensaje no puede exceder los ' . Message::MAX_TEXT_LENGTH . ' caracteres.',
            'content.min' => 'El mensaje debe tener al menos 1 carácter.',
            
            // Archivos adjuntos
            'attachments.array' => 'Los archivos adjuntos deben ser un array.',
            'attachments.max' => 'No se pueden adjuntar más de 10 archivos por mensaje.',
            'attachments.*.file.required_with' => 'El archivo es obligatorio cuando se especifican adjuntos.',
            'attachments.*.file.file' => 'El archivo adjunto no es válido.',
            'attachments.*.file.max' => 'El archivo no puede exceder los ' . Message::MAX_FILE_SIZE_MB . ' MB.',
            'attachments.*.filename.max' => 'El nombre del archivo no puede exceder los 255 caracteres.',
            'attachments.*.description.max' => 'La descripción del archivo no puede exceder los 500 caracteres.',
            
            // Mensaje padre
            'parent_id.exists' => 'El mensaje al que intentas responder no existe.',
            
            // Programación
            'scheduled_at.date' => 'La fecha de programación debe ser una fecha válida.',
            'scheduled_at.after' => 'La fecha de programación debe ser en el futuro.',
            'scheduled_at.before' => 'No se puede programar un mensaje más de 30 días en el futuro.',
            
            // Expiración
            'expires_at.date' => 'La fecha de expiración debe ser una fecha válida.',
            'expires_at.after' => 'La fecha de expiración debe ser en el futuro.',
            'expires_at.before' => 'Un mensaje no puede expirar más de 7 días en el futuro.',
            
            // Ubicación
            'metadata.location.latitude.required_with' => 'La latitud es obligatoria cuando se proporciona ubicación.',
            'metadata.location.latitude.between' => 'La latitud debe estar entre -90 y 90 grados.',
            'metadata.location.longitude.required_with' => 'La longitud es obligatoria cuando se proporciona ubicación.',
            'metadata.location.longitude.between' => 'La longitud debe estar entre -180 y 180 grados.',
            
            // Icebreaker
            'metadata.icebreaker_data.question_id.required_with' => 'El ID de la pregunta es obligatorio para mensajes icebreaker.',
            'metadata.icebreaker_data.question_id.exists' => 'La pregunta icebreaker no existe.',
            'metadata.icebreaker_data.answer.required_with' => 'La respuesta es obligatoria para mensajes icebreaker.',
            'metadata.icebreaker_data.answer.max' => 'La respuesta icebreaker no puede exceder los 500 caracteres.',
            
            // Notificaciones
            'notification_settings.priority.in' => 'La prioridad de notificación debe ser: low, normal o high.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     * 
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'chat_id' => 'ID del chat',
            'type' => 'tipo de mensaje',
            'content' => 'contenido',
            'attachments' => 'archivos adjuntos',
            'attachments.*.file' => 'archivo adjunto',
            'attachments.*.filename' => 'nombre del archivo',
            'attachments.*.description' => 'descripción del archivo',
            'parent_id' => 'mensaje padre',
            'scheduled_at' => 'fecha de programación',
            'expires_at' => 'fecha de expiración',
            'is_encrypted' => 'mensaje encriptado',
            'is_system_message' => 'mensaje del sistema',
            'metadata' => 'metadatos',
            'metadata.location.latitude' => 'latitud',
            'metadata.location.longitude' => 'longitud',
            'metadata.icebreaker_data.question_id' => 'ID de pregunta icebreaker',
            'metadata.icebreaker_data.answer' => 'respuesta icebreaker',
            'notification_settings' => 'configuración de notificaciones',
            'notification_settings.silent' => 'notificación silenciosa',
            'notification_settings.priority' => 'prioridad de notificación',
        ];
    }

    /**
     * Configure the validator instance.
     * 
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validaciones adicionales después de las reglas básicas
            
            $user = $this->user();
            $chatId = $this->get('chat_id');
            $messageType = $this->get('type');
            $attachments = $this->get('attachments', []);
            
            // Validar que el usuario tenga permisos para enviar mensajes en este chat
            if ($chatId && $user) {
                $chat = \App\Models\Chat\Chat::find($chatId);
                if ($chat) {
                    // Verificar si el chat está bloqueado
                    if ($chat->isBlocked()) {
                        $validator->errors()->add('chat_id', 'No puedes enviar mensajes a este chat porque está bloqueado.');
                    }
                    
                    // Verificar si el usuario está bloqueado en el chat
                    $participant = $chat->participants()->where('user_id', $user->id)->first();
                    if ($participant && $participant->pivot->status === 'blocked') {
                        $validator->errors()->add('chat_id', 'No puedes enviar mensajes a este chat.');
                    }
                    
                    // Verificar límites de rate limiting
                    $this->validateRateLimiting($validator, $user, $chat);
                }
            }
            
            // Validar que el tipo de mensaje sea consistente con los archivos adjuntos
            if ($messageType === Message::TYPE_TEXT && !empty($attachments)) {
                $validator->errors()->add('attachments', 'Los mensajes de texto no pueden tener archivos adjuntos.');
            }
            
            if (in_array($messageType, [Message::TYPE_IMAGE, Message::TYPE_VIDEO, Message::TYPE_VOICE, Message::TYPE_FILE]) && empty($attachments)) {
                $validator->errors()->add('attachments', 'Este tipo de mensaje requiere archivos adjuntos.');
            }
            
            // Validar contenido según el tipo de mensaje
            if ($messageType === Message::TYPE_ICEBREAKER && !$this->has('metadata.icebreaker_data')) {
                $validator->errors()->add('metadata.icebreaker_data', 'Los mensajes icebreaker requieren datos de icebreaker.');
            }
            
            // Validar que no se programe un mensaje con expiración
            if ($this->has('scheduled_at') && $this->has('expires_at')) {
                $scheduledAt = \Carbon\Carbon::parse($this->get('scheduled_at'));
                $expiresAt = \Carbon\Carbon::parse($this->get('expires_at'));
                
                if ($expiresAt->lte($scheduledAt)) {
                    $validator->errors()->add('expires_at', 'La fecha de expiración debe ser posterior a la fecha de programación.');
                }
            }
            
            // Validar tamaño total de archivos adjuntos
            if (!empty($attachments)) {
                $totalSize = 0;
                foreach ($attachments as $attachment) {
                    if (isset($attachment['file'])) {
                        $totalSize += $attachment['file']->getSize();
                    }
                }
                
                $maxTotalSize = Message::MAX_FILE_SIZE_MB * 1024 * 1024 * 5; // 5 veces el límite individual
                if ($totalSize > $maxTotalSize) {
                    $validator->errors()->add('attachments', 'El tamaño total de los archivos adjuntos excede el límite permitido.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     * 
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Limpiar y normalizar datos antes de la validación
        
        $data = $this->all();
        
        // Limpiar espacios en blanco del contenido
        if (isset($data['content'])) {
            $data['content'] = trim($data['content']);
        }
        
        // Normalizar tipo de mensaje
        if (isset($data['type'])) {
            $data['type'] = strtolower(trim($data['type']));
        }
        
        // Limpiar metadatos
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = array_filter($data['metadata'], function ($value) {
                return !is_null($value) && $value !== '';
            });
        }
        
        // Establecer valores por defecto
        $data['is_encrypted'] = $data['is_encrypted'] ?? false;
        $data['is_system_message'] = $data['is_system_message'] ?? false;
        
        $this->merge($data);
    }

    /**
     * Get the validated data from the request.
     * 
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);
        
        // Agregar información adicional para el proceso de envío
        $validated['sender_id'] = $this->user()->id;
        $validated['status'] = Message::STATUS_SENDING;
        $validated['metadata'] = array_merge(
            $validated['metadata'] ?? [],
            [
                'ip_address' => $this->ip(),
                'user_agent' => $this->userAgent(),
                'platform' => $this->detectPlatform(),
                'timestamp' => now()->toIso8601String(),
            ]
        );
        
        return $validated;
    }

    /**
     * Get message data in a standardized format compatible with MessageService.
     * 
     * @return array<string, mixed>
     */
    public function getMessageData(): array
    {
        $validated = $this->validated();
        $user = $this->user();
        
        // Datos principales del mensaje
        $messageData = [
            'chat_id' => $validated['chat_id'],
            'sender_id' => $user->id,
            'type' => $validated['type'],
            'content' => $validated['content'] ?? null,
            'status' => Message::STATUS_SENDING,
            'metadata' => $validated['metadata'] ?? [],
        ];
        
        // Agregar parent_id si es una respuesta
        if (isset($validated['parent_id'])) {
            $messageData['parent_id'] = $validated['parent_id'];
        }
        
        // Agregar programación si se especifica
        if (isset($validated['scheduled_at'])) {
            $messageData['scheduled_at'] = $validated['scheduled_at'];
        }
        
        // Agregar expiración si se especifica
        if (isset($validated['expires_at'])) {
            $messageData['expires_at'] = $validated['expires_at'];
        }
        
        // Configuración de privacidad
        $messageData['is_encrypted'] = $validated['is_encrypted'] ?? false;
        $messageData['is_system_message'] = $validated['is_system_message'] ?? false;
        
        // Procesar archivos adjuntos
        if (!empty($validated['attachments'])) {
            $messageData['attachments'] = $this->processAttachments($validated['attachments']);
        }
        
        return $messageData;
    }

    /**
     * Process and validate attachments.
     * 
     * @param array $attachments
     * @return array
     */
    private function processAttachments(array $attachments): array
    {
        $processedAttachments = [];
        
        foreach ($attachments as $attachment) {
            $file = $attachment['file'];
            $filename = $attachment['filename'] ?? $file->getClientOriginalName();
            $description = $attachment['description'] ?? null;
            
            $processedAttachments[] = [
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'description' => $description,
                'uploaded_at' => now()->toIso8601String(),
            ];
        }
        
        return $processedAttachments;
    }

    /**
     * Validate rate limiting for message sending.
     * 
     * @param \Illuminate\Validation\Validator $validator
     * @param \App\Models\User\User $user
     * @param \App\Models\Chat\Chat $chat
     * @return void
     */
    private function validateRateLimiting($validator, $user, $chat): void
    {
        // Obtener el plan del usuario (free o premium)
        $userPlan = $user->subscription?->plan_type ?? 'free';
        
        // Definir límites según el plan
        $messagesPerMinute = $userPlan === 'premium' 
            ? Message::RATE_LIMIT_MESSAGES_PER_MINUTE_PREMIUM 
            : Message::RATE_LIMIT_MESSAGES_PER_MINUTE_FREE;
            
        $messagesPerHour = $userPlan === 'premium' 
            ? Message::RATE_LIMIT_MESSAGES_PER_HOUR_PREMIUM 
            : Message::RATE_LIMIT_MESSAGES_PER_HOUR_FREE;
        
        // Contar mensajes enviados en el último minuto
        $messagesLastMinute = Message::where('sender_id', $user->id)
            ->where('created_at', '>=', now()->subMinute())
            ->count();
            
        if ($messagesLastMinute >= $messagesPerMinute) {
            $validator->errors()->add('rate_limit', 'Has alcanzado el límite de mensajes por minuto.');
        }
        
        // Contar mensajes enviados en la última hora
        $messagesLastHour = Message::where('sender_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
            
        if ($messagesLastHour >= $messagesPerHour) {
            $validator->errors()->add('rate_limit', 'Has alcanzado el límite de mensajes por hora.');
        }
        
        // Contar mensajes en este chat específico en la última hora
        $messagesInChatLastHour = Message::where('sender_id', $user->id)
            ->where('chat_id', $chat->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
            
        if ($messagesInChatLastHour >= 100) { // Límite por chat
            $validator->errors()->add('rate_limit', 'Has enviado demasiados mensajes en este chat.');
        }
    }

    /**
     * Detect platform from user agent.
     * 
     * @return string
     */
    private function detectPlatform(): string
    {
        $userAgent = $this->userAgent() ?? '';

        if (stripos($userAgent, 'android') !== false) {
            return 'android';
        } elseif (stripos($userAgent, 'iphone') !== false || stripos($userAgent, 'ipad') !== false) {
            return 'ios';
        } elseif (stripos($userAgent, 'windows') !== false) {
            return 'windows';
        } elseif (stripos($userAgent, 'mac') !== false) {
            return 'mac';
        } elseif (stripos($userAgent, 'linux') !== false) {
            return 'linux';
        }

        return 'web';
    }

    /**
     * Check if this is a text message.
     * 
     * @return bool
     */
    public function isTextMessage(): bool
    {
        return $this->get('type') === Message::TYPE_TEXT;
    }

    /**
     * Check if this is a media message.
     * 
     * @return bool
     */
    public function isMediaMessage(): bool
    {
        return in_array($this->get('type'), [
            Message::TYPE_IMAGE,
            Message::TYPE_VIDEO,
            Message::TYPE_VOICE,
            Message::TYPE_FILE,
        ]);
    }

    /**
     * Check if this is an icebreaker message.
     * 
     * @return bool
     */
    public function isIcebreakerMessage(): bool
    {
        return $this->get('type') === Message::TYPE_ICEBREAKER;
    }

    /**
     * Check if this message is scheduled.
     * 
     * @return bool
     */
    public function isScheduled(): bool
    {
        return $this->has('scheduled_at') && !empty($this->get('scheduled_at'));
    }

    /**
     * Check if this message has expiration.
     * 
     * @return bool
     */
    public function hasExpiration(): bool
    {
        return $this->has('expires_at') && !empty($this->get('expires_at'));
    }

    /**
     * Check if this message is encrypted.
     * 
     * @return bool
     */
    public function isEncrypted(): bool
    {
        return $this->get('is_encrypted', false);
    }

    /**
     * Get notification settings for MessageSent event.
     * 
     * @return array
     */
    public function getNotificationSettings(): array
    {
        return [
            'silent' => $this->get('notification_settings.silent', false),
            'priority' => $this->get('notification_settings.priority', 'normal'),
            'send_push' => true,
            'send_email' => false,
            'send_sms' => false,
        ];
    }

    /**
     * Get message context for MessageSent event.
     * 
     * @return array
     */
    public function getMessageContext(): array
    {
        return [
            'message_type' => $this->get('type'),
            'is_reply' => $this->has('parent_id'),
            'parent_message_id' => $this->get('parent_id'),
            'is_scheduled' => $this->isScheduled(),
            'scheduled_at' => $this->get('scheduled_at'),
            'has_expiration' => $this->hasExpiration(),
            'expires_at' => $this->get('expires_at'),
            'is_encrypted' => $this->isEncrypted(),
            'is_system_message' => $this->get('is_system_message', false),
            'has_attachments' => $this->has('attachments') && !empty($this->get('attachments')),
            'attachment_count' => count($this->get('attachments', [])),
            'content_length' => strlen($this->get('content', '')),
            'platform' => $this->detectPlatform(),
            'user_agent' => $this->userAgent(),
            'ip_address' => $this->ip(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
