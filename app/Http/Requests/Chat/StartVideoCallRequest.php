<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Models\Chat\VideoCall;
use App\Models\Chat\Chat;
use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StartVideoCallRequest - Validación para iniciar videollamadas
 * 
 * Este FormRequest maneja la validación de datos para iniciar videollamadas
 * en la aplicación ForeverUsInLove, incluyendo validaciones específicas para
 * diferentes tipos de llamadas, configuraciones de calidad, participantes
 * y opciones de WebRTC.
 * 
 * Integrado con:
 * - VideoCallService para lógica de negocio
 * - VideoCallStarted event para notificaciones
 * - ChatService para validaciones de chat
 * - VideoCallRepository para persistencia
 * 
 * @package App\Http\Requests\Chat
 * @version 1.0.0
 * @author ForeverUsInLove Team
 */
class StartVideoCallRequest extends FormRequest
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

        // Verificar que el usuario no esté en otra llamada activa
        $activeCall = VideoCall::active()->forUser($user->id)->first();
        if ($activeCall) {
            return false;
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
            // DATOS BÁSICOS DE LA LLAMADA
            // ========================================
            
            'chat_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:chats,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $chat = Chat::find($value);
                        if ($chat && !$chat->isActive()) {
                            $fail('El chat no está activo.');
                        }
                        
                        // Verificar que el usuario sea participante del chat
                        if ($chat && !$chat->hasParticipant($this->user()->id)) {
                            $fail('No eres participante de este chat.');
                        }
                    }
                },
            ],
            
            'type' => [
                'required',
                'string',
                Rule::in([
                    VideoCall::TYPE_PRIVATE,
                    VideoCall::TYPE_GROUP,
                    VideoCall::TYPE_CONFERENCE,
                    VideoCall::TYPE_EMERGENCY,
                ]),
            ],
            
            'participants' => [
                'required',
                'array',
                'min:1',
                'max:12', // Máximo para usuarios premium
                function ($attribute, $value, $fail) {
                    $user = $this->user();
                    $userPlan = $user->subscription?->plan_type ?? 'free';
                    $maxParticipants = $userPlan === 'premium' 
                        ? VideoCall::MAX_PARTICIPANTS_PREMIUM 
                        : VideoCall::MAX_PARTICIPANTS_FREE;
                    
                    if (count($value) > $maxParticipants) {
                        $fail("Máximo {$maxParticipants} participantes permitidos para tu plan.");
                    }
                    
                    // Verificar que todos los participantes existan y estén activos
                    foreach ($value as $participantId) {
                        $participant = User::find($participantId);
                        if (!$participant || !$participant->profile || $participant->profile->status !== 'active') {
                            $fail("El participante {$participantId} no existe o no está activo.");
                        }
                        
                        // Verificar que el participante no esté en otra llamada
                        $activeCall = VideoCall::active()->forUser($participantId)->first();
                        if ($activeCall) {
                            $fail("El participante {$participantId} está en otra llamada activa.");
                        }
                    }
                },
            ],
            
            'participants.*' => [
                'required',
                'integer',
                'exists:users,id',
                'different:' . $this->user()->id, // No puede llamarse a sí mismo
            ],
            
            // ========================================
            // CONFIGURACIÓN DE CALIDAD Y MEDIOS
            // ========================================
            
            'quality_settings' => [
                'sometimes',
                'array',
            ],
            
            'quality_settings.quality' => [
                'sometimes',
                'string',
                Rule::in([
                    VideoCall::QUALITY_AUTO,
                    VideoCall::QUALITY_LOW,
                    VideoCall::QUALITY_MEDIUM,
                    VideoCall::QUALITY_HIGH,
                    VideoCall::QUALITY_HD,
                ]),
                function ($attribute, $value, $fail) {
                    $user = $this->user();
                    $userPlan = $user->subscription?->plan_type ?? 'free';
                    
                    // HD quality solo para usuarios premium
                    if ($value === VideoCall::QUALITY_HD && $userPlan !== 'premium') {
                        $fail('La calidad HD está disponible solo para usuarios premium.');
                    }
                },
            ],
            
            'quality_settings.video_enabled' => [
                'sometimes',
                'boolean',
            ],
            
            'quality_settings.audio_enabled' => [
                'sometimes',
                'boolean',
            ],
            
            'quality_settings.screen_share_enabled' => [
                'sometimes',
                'boolean',
            ],
            
            'quality_settings.recording_enabled' => [
                'sometimes',
                'boolean',
                function ($attribute, $value, $fail) {
                    $user = $this->user();
                    $userPlan = $user->subscription?->plan_type ?? 'free';
                    
                    // Grabación solo para usuarios premium
                    if ($value && $userPlan !== 'premium') {
                        $fail('La grabación de llamadas está disponible solo para usuarios premium.');
                    }
                },
            ],
            
            'quality_settings.noise_cancellation' => [
                'sometimes',
                'boolean',
            ],
            
            'quality_settings.echo_cancellation' => [
                'sometimes',
                'boolean',
            ],
            
            'quality_settings.auto_gain_control' => [
                'sometimes',
                'boolean',
            ],
            
            // ========================================
            // CONFIGURACIÓN DE WEBRTC
            // ========================================
            
            'webrtc_config' => [
                'sometimes',
                'array',
            ],
            
            'webrtc_config.ice_servers' => [
                'sometimes',
                'array',
            ],
            
            'webrtc_config.ice_servers.*.urls' => [
                'required_with:webrtc_config.ice_servers',
                'string',
                'url',
            ],
            
            'webrtc_config.ice_servers.*.username' => [
                'sometimes',
                'string',
                'max:255',
            ],
            
            'webrtc_config.ice_servers.*.credential' => [
                'sometimes',
                'string',
                'max:255',
            ],
            
            'webrtc_config.media_constraints' => [
                'sometimes',
                'array',
            ],
            
            'webrtc_config.media_constraints.video' => [
                'sometimes',
                'array',
            ],
            
            'webrtc_config.media_constraints.video.width' => [
                'sometimes',
                'array',
            ],
            
            'webrtc_config.media_constraints.video.height' => [
                'sometimes',
                'array',
            ],
            
            'webrtc_config.media_constraints.audio' => [
                'sometimes',
                'array',
            ],
            
            // ========================================
            // CONFIGURACIÓN DE DISPOSITIVO
            // ========================================
            
            'device_info' => [
                'sometimes',
                'array',
            ],
            
            'device_info.platform' => [
                'sometimes',
                'string',
                Rule::in(['web', 'android', 'ios', 'windows', 'mac', 'linux']),
            ],
            
            'device_info.browser' => [
                'sometimes',
                'string',
                'max:100',
            ],
            
            'device_info.version' => [
                'sometimes',
                'string',
                'max:50',
            ],
            
            'device_info.capabilities' => [
                'sometimes',
                'array',
            ],
            
            'device_info.capabilities.camera' => [
                'sometimes',
                'boolean',
            ],
            
            'device_info.capabilities.microphone' => [
                'sometimes',
                'boolean',
            ],
            
            'device_info.capabilities.speakers' => [
                'sometimes',
                'boolean',
            ],
            
            'device_info.capabilities.screen_share' => [
                'sometimes',
                'boolean',
            ],
            
            // ========================================
            // CONFIGURACIÓN DE CONEXIÓN
            // ========================================
            
            'connection_type' => [
                'sometimes',
                'string',
                Rule::in(['wifi', 'cellular', 'ethernet', 'unknown']),
            ],
            
            'bandwidth_limit' => [
                'sometimes',
                'nullable',
                'integer',
                'min:64', // Mínimo 64 kbps
                'max:10000', // Máximo 10 Mbps
            ],
            
            'max_duration' => [
                'sometimes',
                'nullable',
                'integer',
                'min:60', // Mínimo 1 minuto
                function ($attribute, $value, $fail) {
                    $user = $this->user();
                    $userPlan = $user->subscription?->plan_type ?? 'free';
                    $maxDuration = $userPlan === 'premium' 
                        ? VideoCall::MAX_DURATION_PREMIUM 
                        : VideoCall::MAX_DURATION_FREE;
                    
                    if ($value && $value > $maxDuration) {
                        $fail("La duración máxima permitida es {$maxDuration} segundos para tu plan.");
                    }
                },
            ],
            
            // ========================================
            // CONFIGURACIÓN DE PRIVACIDAD
            // ========================================
            
            'privacy_mode' => [
                'sometimes',
                'boolean',
            ],
            
            'is_emergency' => [
                'sometimes',
                'boolean',
            ],
            
            'emergency_contact' => [
                'required_if:is_emergency,true',
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            
            // ========================================
            // CONFIGURACIÓN DE NOTIFICACIONES
            // ========================================
            
            'notification_settings' => [
                'sometimes',
                'array',
            ],
            
            'notification_settings.send_push' => [
                'sometimes',
                'boolean',
            ],
            
            'notification_settings.send_sms' => [
                'sometimes',
                'boolean',
            ],
            
            'notification_settings.send_email' => [
                'sometimes',
                'boolean',
            ],
            
            'notification_settings.priority' => [
                'sometimes',
                'string',
                Rule::in(['low', 'normal', 'high', 'urgent']),
            ],
            
            // ========================================
            // METADATOS ADICIONALES
            // ========================================
            
            'metadata' => [
                'sometimes',
                'array',
            ],
            
            'metadata.call_reason' => [
                'sometimes',
                'string',
                'max:500',
            ],
            
            'metadata.scheduled_call_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:scheduled_calls,id',
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
            
            'metadata.app_version' => [
                'sometimes',
                'string',
                'max:50',
            ],
            
            'metadata.build_number' => [
                'sometimes',
                'string',
                'max:50',
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
            // Datos básicos
            'chat_id.exists' => 'El chat especificado no existe.',
            'type.required' => 'El tipo de llamada es obligatorio.',
            'type.in' => 'El tipo de llamada no es válido.',
            'participants.required' => 'Los participantes son obligatorios.',
            'participants.array' => 'Los participantes deben ser un array.',
            'participants.min' => 'Debe haber al menos un participante.',
            'participants.max' => 'Demasiados participantes especificados.',
            'participants.*.required' => 'Cada participante es obligatorio.',
            'participants.*.integer' => 'El ID del participante debe ser un número entero.',
            'participants.*.exists' => 'Uno o más participantes no existen.',
            'participants.*.different' => 'No puedes llamarte a ti mismo.',
            
            // Configuración de calidad
            'quality_settings.quality.in' => 'La calidad especificada no es válida.',
            'quality_settings.recording_enabled.boolean' => 'La configuración de grabación debe ser verdadera o falsa.',
            
            // WebRTC
            'webrtc_config.ice_servers.*.urls.required_with' => 'La URL del servidor ICE es obligatoria.',
            'webrtc_config.ice_servers.*.urls.url' => 'La URL del servidor ICE no es válida.',
            
            // Dispositivo
            'device_info.platform.in' => 'La plataforma especificada no es válida.',
            'device_info.browser.max' => 'El nombre del navegador no puede exceder los 100 caracteres.',
            'device_info.version.max' => 'La versión no puede exceder los 50 caracteres.',
            
            // Conexión
            'connection_type.in' => 'El tipo de conexión especificado no es válido.',
            'bandwidth_limit.integer' => 'El límite de ancho de banda debe ser un número entero.',
            'bandwidth_limit.min' => 'El límite mínimo de ancho de banda es 64 kbps.',
            'bandwidth_limit.max' => 'El límite máximo de ancho de banda es 10 Mbps.',
            'max_duration.integer' => 'La duración máxima debe ser un número entero.',
            'max_duration.min' => 'La duración mínima es 60 segundos.',
            
            // Emergencia
            'emergency_contact.required_if' => 'El contacto de emergencia es obligatorio para llamadas de emergencia.',
            'emergency_contact.max' => 'El contacto de emergencia no puede exceder los 255 caracteres.',
            
            // Notificaciones
            'notification_settings.priority.in' => 'La prioridad debe ser: low, normal, high o urgent.',
            
            // Ubicación
            'metadata.location.latitude.required_with' => 'La latitud es obligatoria cuando se proporciona ubicación.',
            'metadata.location.latitude.between' => 'La latitud debe estar entre -90 y 90 grados.',
            'metadata.location.longitude.required_with' => 'La longitud es obligatoria cuando se proporciona ubicación.',
            'metadata.location.longitude.between' => 'La longitud debe estar entre -180 y 180 grados.',
            
            // Metadatos
            'metadata.call_reason.max' => 'La razón de la llamada no puede exceder los 500 caracteres.',
            'metadata.scheduled_call_id.exists' => 'La llamada programada especificada no existe.',
            'metadata.app_version.max' => 'La versión de la app no puede exceder los 50 caracteres.',
            'metadata.build_number.max' => 'El número de build no puede exceder los 50 caracteres.',
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
            'type' => 'tipo de llamada',
            'participants' => 'participantes',
            'participants.*' => 'participante',
            'quality_settings' => 'configuración de calidad',
            'quality_settings.quality' => 'calidad',
            'quality_settings.video_enabled' => 'video habilitado',
            'quality_settings.audio_enabled' => 'audio habilitado',
            'quality_settings.screen_share_enabled' => 'compartir pantalla habilitado',
            'quality_settings.recording_enabled' => 'grabación habilitada',
            'quality_settings.noise_cancellation' => 'cancelación de ruido',
            'quality_settings.echo_cancellation' => 'cancelación de eco',
            'quality_settings.auto_gain_control' => 'control automático de ganancia',
            'webrtc_config' => 'configuración WebRTC',
            'webrtc_config.ice_servers' => 'servidores ICE',
            'webrtc_config.media_constraints' => 'restricciones de medios',
            'device_info' => 'información del dispositivo',
            'device_info.platform' => 'plataforma',
            'device_info.browser' => 'navegador',
            'device_info.version' => 'versión',
            'device_info.capabilities' => 'capacidades',
            'connection_type' => 'tipo de conexión',
            'bandwidth_limit' => 'límite de ancho de banda',
            'max_duration' => 'duración máxima',
            'privacy_mode' => 'modo de privacidad',
            'is_emergency' => 'llamada de emergencia',
            'emergency_contact' => 'contacto de emergencia',
            'notification_settings' => 'configuración de notificaciones',
            'notification_settings.priority' => 'prioridad',
            'metadata' => 'metadatos',
            'metadata.call_reason' => 'razón de la llamada',
            'metadata.location' => 'ubicación',
            'metadata.location.latitude' => 'latitud',
            'metadata.location.longitude' => 'longitud',
            'metadata.app_version' => 'versión de la app',
            'metadata.build_number' => 'número de build',
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
            $participants = $this->get('participants', []);
            $type = $this->get('type');
            $chatId = $this->get('chat_id');
            
            // Validar consistencia entre tipo de llamada y número de participantes
            if ($type === VideoCall::TYPE_PRIVATE && count($participants) !== 1) {
                $validator->errors()->add('participants', 'Las llamadas privadas deben tener exactamente un participante.');
            }
            
            if (in_array($type, [VideoCall::TYPE_GROUP, VideoCall::TYPE_CONFERENCE]) && count($participants) < 2) {
                $validator->errors()->add('participants', 'Las llamadas grupales deben tener al menos dos participantes.');
            }
            
            // Validar límites de llamadas diarias
            $this->validateDailyCallLimits($validator, $user);
            
            // Validar que el chat sea consistente con los participantes
            if ($chatId) {
                $this->validateChatParticipants($validator, $chatId, $participants);
            }
            
            // Validar configuración de medios
            $this->validateMediaConfiguration($validator);
            
            // Validar configuración de emergencia
            if ($this->get('is_emergency')) {
                $this->validateEmergencyConfiguration($validator);
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
        
        // Normalizar tipo de llamada
        if (isset($data['type'])) {
            $data['type'] = strtolower(trim($data['type']));
        }
        
        // Establecer valores por defecto para configuración de calidad
        if (!isset($data['quality_settings'])) {
            $data['quality_settings'] = VideoCall::DEFAULT_QUALITY_SETTINGS;
        } else {
            $data['quality_settings'] = array_merge(
                VideoCall::DEFAULT_QUALITY_SETTINGS,
                $data['quality_settings']
            );
        }
        
        // Detectar plataforma automáticamente si no se especifica
        if (!isset($data['device_info']['platform'])) {
            $data['device_info']['platform'] = $this->detectPlatform();
        }
        
        // Establecer valores por defecto para configuración de dispositivo
        if (!isset($data['device_info']['capabilities'])) {
            $data['device_info']['capabilities'] = [
                'camera' => true,
                'microphone' => true,
                'speakers' => true,
                'screen_share' => false,
            ];
        }
        
        // Establecer valores por defecto para notificaciones
        if (!isset($data['notification_settings'])) {
            $data['notification_settings'] = [
                'send_push' => true,
                'send_sms' => false,
                'send_email' => false,
                'priority' => 'normal',
            ];
        }
        
        // Limpiar metadatos
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = array_filter($data['metadata'], function ($value) {
                return !is_null($value) && $value !== '';
            });
        }
        
        // Establecer valores por defecto
        $data['privacy_mode'] = $data['privacy_mode'] ?? false;
        $data['is_emergency'] = $data['is_emergency'] ?? false;
        
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
        
        // Agregar información adicional para el proceso de iniciación de llamada
        $validated['caller_id'] = $this->user()->id;
        $validated['status'] = VideoCall::STATUS_INITIATING;
        $validated['metadata'] = array_merge(
            $validated['metadata'] ?? [],
            [
                'ip_address' => $this->ip(),
                'user_agent' => $this->userAgent(),
                'platform' => $this->detectPlatform(),
                'timestamp' => now()->toIso8601String(),
                'app_version' => $validated['metadata']['app_version'] ?? '1.0.0',
                'build_number' => $validated['metadata']['build_number'] ?? '1',
            ]
        );
        
        return $validated;
    }

    /**
     * Get video call data in a standardized format compatible with VideoCallService.
     * 
     * @return array<string, mixed>
     */
    public function getVideoCallData(): array
    {
        $validated = $this->validated();
        $user = $this->user();
        
        // Datos principales de la llamada
        $callData = [
            'caller_id' => $user->id,
            'type' => $validated['type'],
            'status' => VideoCall::STATUS_INITIATING,
            'participants' => $validated['participants'],
            'quality_settings' => $validated['quality_settings'] ?? VideoCall::DEFAULT_QUALITY_SETTINGS,
            'metadata' => $validated['metadata'] ?? [],
        ];
        
        // Agregar chat_id si se especifica
        if (isset($validated['chat_id'])) {
            $callData['chat_id'] = $validated['chat_id'];
        }
        
        // Configuración WebRTC
        if (isset($validated['webrtc_config'])) {
            $callData['webrtc_config'] = $validated['webrtc_config'];
        }
        
        // Información del dispositivo
        if (isset($validated['device_info'])) {
            $callData['device_info'] = $validated['device_info'];
        }
        
        // Configuración de conexión
        if (isset($validated['connection_type'])) {
            $callData['connection_type'] = $validated['connection_type'];
        }
        
        if (isset($validated['bandwidth_limit'])) {
            $callData['bandwidth_limit'] = $validated['bandwidth_limit'];
        }
        
        if (isset($validated['max_duration'])) {
            $callData['max_duration'] = $validated['max_duration'];
        }
        
        // Configuración de privacidad
        $callData['privacy_mode'] = $validated['privacy_mode'] ?? false;
        $callData['is_emergency'] = $validated['is_emergency'] ?? false;
        
        if ($callData['is_emergency'] && isset($validated['emergency_contact'])) {
            $callData['emergency_contact'] = $validated['emergency_contact'];
        }
        
        // Configuración de notificaciones
        if (isset($validated['notification_settings'])) {
            $callData['notification_settings'] = $validated['notification_settings'];
        }
        
        return $callData;
    }

    /**
     * Validate daily call limits for the user.
     * 
     * @param \Illuminate\Validation\Validator $validator
     * @param \App\Models\User\User $user
     * @return void
     */
    private function validateDailyCallLimits($validator, $user): void
    {
        $userPlan = $user->subscription?->plan_type ?? 'free';
        
        // Definir límites según el plan
        $dailyCallLimit = $userPlan === 'premium' ? 100 : 20;
        
        // Contar llamadas iniciadas hoy
        $callsToday = VideoCall::where('caller_id', $user->id)
            ->whereDate('created_at', today())
            ->count();
            
        if ($callsToday >= $dailyCallLimit) {
            $validator->errors()->add('daily_limit', "Has alcanzado el límite diario de llamadas ({$dailyCallLimit}).");
        }
    }

    /**
     * Validate that chat participants match the call participants.
     * 
     * @param \Illuminate\Validation\Validator $validator
     * @param int $chatId
     * @param array $participants
     * @return void
     */
    private function validateChatParticipants($validator, $chatId, $participants): void
    {
        $chat = Chat::find($chatId);
        if (!$chat) {
            return;
        }
        
        $chatParticipantIds = $chat->participants()->pluck('user_id')->toArray();
        $callParticipantIds = array_merge([$this->user()->id], $participants);
        
        // Verificar que todos los participantes de la llamada sean participantes del chat
        foreach ($callParticipantIds as $participantId) {
            if (!in_array($participantId, $chatParticipantIds)) {
                $validator->errors()->add('participants', "El participante {$participantId} no es miembro del chat especificado.");
                break;
            }
        }
    }

    /**
     * Validate media configuration consistency.
     * 
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validateMediaConfiguration($validator): void
    {
        $qualitySettings = $this->get('quality_settings', []);
        
        // Verificar que al menos video o audio estén habilitados
        $videoEnabled = $qualitySettings['video_enabled'] ?? true;
        $audioEnabled = $qualitySettings['audio_enabled'] ?? true;
        
        if (!$videoEnabled && !$audioEnabled) {
            $validator->errors()->add('quality_settings', 'Al menos video o audio deben estar habilitados.');
        }
        
        // Verificar configuración de grabación
        $recordingEnabled = $qualitySettings['recording_enabled'] ?? false;
        if ($recordingEnabled && !$videoEnabled) {
            $validator->errors()->add('quality_settings', 'La grabación requiere que el video esté habilitado.');
        }
    }

    /**
     * Validate emergency call configuration.
     * 
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    private function validateEmergencyConfiguration($validator): void
    {
        $participants = $this->get('participants', []);
        
        // Las llamadas de emergencia deben ser privadas
        if ($this->get('type') !== VideoCall::TYPE_EMERGENCY) {
            $validator->errors()->add('type', 'Las llamadas de emergencia deben usar el tipo emergency.');
        }
        
        // Las llamadas de emergencia deben tener máximo un participante
        if (count($participants) > 1) {
            $validator->errors()->add('participants', 'Las llamadas de emergencia deben ser privadas (un solo participante).');
        }
        
        // Verificar que el contacto de emergencia esté especificado
        if (!$this->has('emergency_contact') || empty($this->get('emergency_contact'))) {
            $validator->errors()->add('emergency_contact', 'El contacto de emergencia es obligatorio para llamadas de emergencia.');
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
     * Check if this is a private call.
     * 
     * @return bool
     */
    public function isPrivateCall(): bool
    {
        return $this->get('type') === VideoCall::TYPE_PRIVATE;
    }

    /**
     * Check if this is a group call.
     * 
     * @return bool
     */
    public function isGroupCall(): bool
    {
        return in_array($this->get('type'), [VideoCall::TYPE_GROUP, VideoCall::TYPE_CONFERENCE]);
    }

    /**
     * Check if this is an emergency call.
     * 
     * @return bool
     */
    public function isEmergencyCall(): bool
    {
        return $this->get('is_emergency', false) || $this->get('type') === VideoCall::TYPE_EMERGENCY;
    }

    /**
     * Check if recording is enabled.
     * 
     * @return bool
     */
    public function isRecordingEnabled(): bool
    {
        return $this->get('quality_settings.recording_enabled', false);
    }

    /**
     * Check if screen sharing is enabled.
     * 
     * @return bool
     */
    public function isScreenShareEnabled(): bool
    {
        return $this->get('quality_settings.screen_share_enabled', false);
    }

    /**
     * Get call quality level.
     * 
     * @return string
     */
    public function getQualityLevel(): string
    {
        return $this->get('quality_settings.quality', VideoCall::QUALITY_AUTO);
    }

    /**
     * Get notification settings for VideoCallStarted event.
     * 
     * @return array
     */
    public function getNotificationSettings(): array
    {
        return [
            'send_push' => $this->get('notification_settings.send_push', true),
            'send_sms' => $this->get('notification_settings.send_sms', false),
            'send_email' => $this->get('notification_settings.send_email', false),
            'priority' => $this->get('notification_settings.priority', 'normal'),
        ];
    }

    /**
     * Get call context for VideoCallStarted event.
     * 
     * @return array
     */
    public function getCallContext(): array
    {
        return [
            'call_type' => $this->get('type'),
            'participant_count' => count($this->get('participants', [])),
            'is_emergency' => $this->isEmergencyCall(),
            'is_group_call' => $this->isGroupCall(),
            'is_private_call' => $this->isPrivateCall(),
            'recording_enabled' => $this->isRecordingEnabled(),
            'screen_share_enabled' => $this->isScreenShareEnabled(),
            'quality_level' => $this->getQualityLevel(),
            'has_chat' => $this->has('chat_id'),
            'chat_id' => $this->get('chat_id'),
            'platform' => $this->detectPlatform(),
            'user_agent' => $this->userAgent(),
            'ip_address' => $this->ip(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
