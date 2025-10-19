<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * FaceIdRequest - Validación para operaciones de Face ID
 * 
 * Maneja la validación completa de operaciones relacionadas con Face ID
 * en la aplicación ForeverUsInLove siguiendo Clean Architecture.
 * 
 * Operaciones soportadas:
 * - Registro de plantillas biométricas
 * - Verificación de identidad
 * - Verificación con PIN de fallback
 * - Eliminación de plantillas
 * - Consulta de estadísticas
 * 
 * Integrado con:
 * - FaceIdVerificationService para lógica de negocio
 * - User model para gestión de datos biométricos
 * - Sistema de rate limiting y seguridad
 * - Logging y auditoría de eventos
 * 
 * @package App\Http\Requests\Auth
 * @version 1.0.0
 * @author ForeverUsInLove Team
 */
class FaceIdRequest extends FormRequest
{
    /**
     * Tipos de operaciones Face ID soportadas
     */
    private const OPERATION_TYPES = [
        'register' => 'Registro de plantilla biométrica',
        'verify' => 'Verificación de identidad',
        'fallback' => 'Verificación con PIN de fallback',
        'remove' => 'Eliminación de plantilla',
        'statistics' => 'Consulta de estadísticas',
        'update' => 'Actualización de plantilla',
        'validate' => 'Validación de calidad'
    ];

    /**
     * Tipos de verificación Face ID
     */
    private const VERIFICATION_TYPES = [
        'login' => 'Inicio de sesión',
        'transaction' => 'Confirmación de transacción',
        'profile_change' => 'Cambio de perfil',
        'account_deletion' => 'Eliminación de cuenta',
        'sensitive_action' => 'Acción sensible',
        'payment' => 'Confirmación de pago',
        'settings_change' => 'Cambio de configuración'
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el controlador
    }

    /**
     * Get the validation rules that apply to the request.
     * 
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $operation = $this->get('operation', 'verify');
        
        return array_merge([
            // ========================================
            // CAMPOS COMUNES PARA TODAS LAS OPERACIONES
            // ========================================
            
            'operation' => [
                'required',
                'string',
                'in:' . implode(',', array_keys(self::OPERATION_TYPES)),
            ],
            
            'device_id' => [
                'required',
                'string',
                'min:10',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/',
            ],
            
            'device_info' => [
                'sometimes',
                'array',
            ],
            
            'device_info.platform' => [
                'required_with:device_info',
                'string',
                'in:ios,android,web',
            ],
            
            'device_info.version' => [
                'required_with:device_info',
                'string',
                'max:50',
            ],
            
            'device_info.model' => [
                'sometimes',
                'string',
                'max:100',
            ],
            
            'device_info.fingerprint' => [
                'sometimes',
                'string',
                'max:500',
            ],
            
            // ========================================
            // CAMPOS ESPECÍFICOS POR OPERACIÓN
            // ========================================
            
        ], $this->getOperationSpecificRules($operation));
    }

    /**
     * Get operation-specific validation rules
     */
    private function getOperationSpecificRules(string $operation): array
    {
        switch ($operation) {
            case 'register':
                return $this->getRegisterRules();
            
            case 'verify':
                return $this->getVerifyRules();
            
            case 'fallback':
                return $this->getFallbackRules();
            
            case 'remove':
                return $this->getRemoveRules();
            
            case 'update':
                return $this->getUpdateRules();
            
            case 'validate':
                return $this->getValidateRules();
            
            default:
                return [];
        }
    }

    /**
     * Rules for register operation
     */
    private function getRegisterRules(): array
    {
        return [
            'biometric_data' => [
                'required',
                'string',
                'min:100',
                'max:10240', // MAX_TEMPLATE_SIZE del servicio
            ],
            
            'quality_score' => [
                'required',
                'numeric',
                'between:0.85,1.0', // MIN_CONFIDENCE_SCORE del servicio
            ],
            
            'feature_points' => [
                'sometimes',
                'array',
                'max:100',
            ],
            
            'feature_points.*' => [
                'sometimes',
                'array',
                'size:3', // x, y, confidence
            ],
            
            'feature_points.*.x' => [
                'required_with:feature_points.*',
                'numeric',
                'between:0,1',
            ],
            
            'feature_points.*.y' => [
                'required_with:feature_points.*',
                'numeric',
                'between:0,1',
            ],
            
            'feature_points.*.confidence' => [
                'required_with:feature_points.*',
                'numeric',
                'between:0,1',
            ],
            
            'metadata' => [
                'sometimes',
                'array',
            ],
            
            'metadata.resolution' => [
                'sometimes',
                'string',
                'max:50',
                'regex:/^\d+x\d+$/', // Ej: 1920x1080
            ],
            
            'metadata.lighting_conditions' => [
                'sometimes',
                'string',
                'in:good,fair,poor',
            ],
            
            'metadata.capture_angle' => [
                'sometimes',
                'numeric',
                'between:-45,45', // Grados
            ],
            
            'metadata.face_visibility' => [
                'sometimes',
                'numeric',
                'between:0,1',
            ],
            
            'fallback_pin' => [
                'sometimes',
                'string',
                'size:6',
                'regex:/^[0-9]{6}$/',
            ],
        ];
    }

    /**
     * Rules for verify operation
     */
    private function getVerifyRules(): array
    {
        return [
            'biometric_data' => [
                'required',
                'string',
                'min:100',
                'max:10240',
            ],
            
            'verification_type' => [
                'required',
                'string',
                'in:' . implode(',', array_keys(self::VERIFICATION_TYPES)),
            ],
            
            'context' => [
                'sometimes',
                'array',
            ],
            
            'context.transaction_id' => [
                'required_with:context',
                'sometimes',
                'string',
                'max:100',
            ],
            
            'context.amount' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            
            'context.currency' => [
                'sometimes',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],
            
            'context.ip_address' => [
                'sometimes',
                'ip',
            ],
            
            'context.location' => [
                'sometimes',
                'array',
            ],
            
            'context.location.latitude' => [
                'required_with:context.location',
                'numeric',
                'between:-90,90',
            ],
            
            'context.location.longitude' => [
                'required_with:context.location',
                'numeric',
                'between:-180,180',
            ],
            
            'context.location.accuracy' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            
            'force_verification' => [
                'sometimes',
                'boolean',
            ],
            
            'skip_rate_limit' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Rules for fallback operation
     */
    private function getFallbackRules(): array
    {
        return [
            'fallback_pin' => [
                'required',
                'string',
                'size:6',
                'regex:/^[0-9]{6}$/',
            ],
            
            'verification_type' => [
                'sometimes',
                'string',
                'in:' . implode(',', array_keys(self::VERIFICATION_TYPES)),
            ],
            
            'reason' => [
                'sometimes',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Rules for remove operation
     */
    private function getRemoveRules(): array
    {
        return [
            'template_id' => [
                'sometimes',
                'string',
                'max:50',
                'regex:/^fid_\d+_[a-f0-9]{12}$/',
            ],
            
            'remove_all' => [
                'sometimes',
                'boolean',
            ],
            
            'remove_device_only' => [
                'sometimes',
                'boolean',
            ],
            
            'confirmation_code' => [
                'required',
                'string',
                'size:6',
                'regex:/^[0-9]{6}$/',
            ],
        ];
    }

    /**
     * Rules for update operation
     */
    private function getUpdateRules(): array
    {
        return [
            'template_id' => [
                'required',
                'string',
                'max:50',
                'regex:/^fid_\d+_[a-f0-9]{12}$/',
            ],
            
            'biometric_data' => [
                'sometimes',
                'string',
                'min:100',
                'max:10240',
            ],
            
            'quality_score' => [
                'sometimes',
                'numeric',
                'between:0.85,1.0',
            ],
            
            'metadata' => [
                'sometimes',
                'array',
            ],
            
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Rules for validate operation
     */
    private function getValidateRules(): array
    {
        return [
            'biometric_data' => [
                'required',
                'string',
                'min:100',
                'max:10240',
            ],
            
            'validation_type' => [
                'sometimes',
                'string',
                'in:quality,format,completeness',
            ],
            
            'min_quality_threshold' => [
                'sometimes',
                'numeric',
                'between:0,1',
            ],
            
            'check_lighting' => [
                'sometimes',
                'boolean',
            ],
            
            'check_angle' => [
                'sometimes',
                'boolean',
            ],
            
            'check_visibility' => [
                'sometimes',
                'boolean',
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
            // Campos comunes
            'operation.required' => 'El tipo de operación es obligatorio.',
            'operation.in' => 'El tipo de operación no es válido.',
            'device_id.required' => 'El ID del dispositivo es obligatorio.',
            'device_id.min' => 'El ID del dispositivo debe tener al menos 10 caracteres.',
            'device_id.regex' => 'El ID del dispositivo contiene caracteres no válidos.',
            
            // Device info
            'device_info.platform.required_with' => 'La plataforma del dispositivo es obligatoria.',
            'device_info.platform.in' => 'La plataforma debe ser: ios, android o web.',
            'device_info.version.required_with' => 'La versión del dispositivo es obligatoria.',
            
            // Registro
            'biometric_data.required' => 'Los datos biométricos son obligatorios.',
            'biometric_data.min' => 'Los datos biométricos son demasiado pequeños.',
            'biometric_data.max' => 'Los datos biométricos exceden el tamaño máximo.',
            'quality_score.required' => 'La puntuación de calidad es obligatoria.',
            'quality_score.between' => 'La puntuación de calidad debe estar entre 0.85 y 1.0.',
            
            // Feature points
            'feature_points.array' => 'Los puntos de características deben ser un array.',
            'feature_points.max' => 'No se pueden tener más de 100 puntos de características.',
            'feature_points.*.size' => 'Cada punto de característica debe tener exactamente 3 valores.',
            'feature_points.*.x.required_with' => 'La coordenada X es obligatoria.',
            'feature_points.*.x.between' => 'La coordenada X debe estar entre 0 y 1.',
            'feature_points.*.y.required_with' => 'La coordenada Y es obligatoria.',
            'feature_points.*.y.between' => 'La coordenada Y debe estar entre 0 y 1.',
            'feature_points.*.confidence.required_with' => 'La confianza es obligatoria.',
            'feature_points.*.confidence.between' => 'La confianza debe estar entre 0 y 1.',
            
            // Metadatos
            'metadata.resolution.regex' => 'La resolución debe tener el formato WIDTHxHEIGHT (ej: 1920x1080).',
            'metadata.lighting_conditions.in' => 'Las condiciones de iluminación deben ser: good, fair o poor.',
            'metadata.capture_angle.between' => 'El ángulo de captura debe estar entre -45 y 45 grados.',
            'metadata.face_visibility.between' => 'La visibilidad facial debe estar entre 0 y 1.',
            
            // Verificación
            'verification_type.required' => 'El tipo de verificación es obligatorio.',
            'verification_type.in' => 'El tipo de verificación no es válido.',
            'context.transaction_id.required_with' => 'El ID de transacción es obligatorio en el contexto.',
            'context.currency.size' => 'La moneda debe tener 3 caracteres (ej: USD, EUR).',
            'context.currency.regex' => 'La moneda debe estar en formato ISO (ej: USD, EUR).',
            'context.ip_address.ip' => 'La dirección IP no es válida.',
            'context.location.latitude.required_with' => 'La latitud es obligatoria cuando se proporciona ubicación.',
            'context.location.latitude.between' => 'La latitud debe estar entre -90 y 90.',
            'context.location.longitude.required_with' => 'La longitud es obligatoria cuando se proporciona ubicación.',
            'context.location.longitude.between' => 'La longitud debe estar entre -180 y 180.',
            'context.location.accuracy.min' => 'La precisión de ubicación debe ser mayor a 0.',
            
            // Fallback
            'fallback_pin.required' => 'El PIN de fallback es obligatorio.',
            'fallback_pin.size' => 'El PIN de fallback debe tener exactamente 6 dígitos.',
            'fallback_pin.regex' => 'El PIN de fallback debe contener solo números.',
            'reason.max' => 'La razón no puede tener más de 255 caracteres.',
            
            // Eliminación
            'template_id.regex' => 'El ID de plantilla no tiene el formato correcto.',
            'confirmation_code.required' => 'El código de confirmación es obligatorio para eliminar plantillas.',
            'confirmation_code.size' => 'El código de confirmación debe tener exactamente 6 dígitos.',
            'confirmation_code.regex' => 'El código de confirmación debe contener solo números.',
            
            // Validación
            'validation_type.in' => 'El tipo de validación debe ser: quality, format o completeness.',
            'min_quality_threshold.between' => 'El umbral mínimo de calidad debe estar entre 0 y 1.',
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
            'operation' => 'tipo de operación',
            'device_id' => 'ID del dispositivo',
            'device_info.platform' => 'plataforma del dispositivo',
            'device_info.version' => 'versión del dispositivo',
            'device_info.model' => 'modelo del dispositivo',
            'device_info.fingerprint' => 'huella digital del dispositivo',
            'biometric_data' => 'datos biométricos',
            'quality_score' => 'puntuación de calidad',
            'feature_points' => 'puntos de características',
            'feature_points.*.x' => 'coordenada X',
            'feature_points.*.y' => 'coordenada Y',
            'feature_points.*.confidence' => 'confianza',
            'metadata' => 'metadatos',
            'metadata.resolution' => 'resolución',
            'metadata.lighting_conditions' => 'condiciones de iluminación',
            'metadata.capture_angle' => 'ángulo de captura',
            'metadata.face_visibility' => 'visibilidad facial',
            'fallback_pin' => 'PIN de fallback',
            'verification_type' => 'tipo de verificación',
            'context' => 'contexto',
            'context.transaction_id' => 'ID de transacción',
            'context.amount' => 'monto',
            'context.currency' => 'moneda',
            'context.ip_address' => 'dirección IP',
            'context.location' => 'ubicación',
            'context.location.latitude' => 'latitud',
            'context.location.longitude' => 'longitud',
            'context.location.accuracy' => 'precisión de ubicación',
            'force_verification' => 'forzar verificación',
            'skip_rate_limit' => 'omitir límite de velocidad',
            'template_id' => 'ID de plantilla',
            'remove_all' => 'eliminar todo',
            'remove_device_only' => 'eliminar solo dispositivo',
            'confirmation_code' => 'código de confirmación',
            'is_active' => 'activo',
            'validation_type' => 'tipo de validación',
            'min_quality_threshold' => 'umbral mínimo de calidad',
            'check_lighting' => 'verificar iluminación',
            'check_angle' => 'verificar ángulo',
            'check_visibility' => 'verificar visibilidad',
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
            $operation = $this->get('operation');
            
            // Validaciones específicas por operación
            switch ($operation) {
                case 'register':
                    $this->validateRegisterOperation($validator);
                    break;
                
                case 'verify':
                    $this->validateVerifyOperation($validator);
                    break;
                
                case 'fallback':
                    $this->validateFallbackOperation($validator);
                    break;
                
                case 'remove':
                    $this->validateRemoveOperation($validator);
                    break;
                
                case 'update':
                    $this->validateUpdateOperation($validator);
                    break;
                
                case 'validate':
                    $this->validateValidationOperation($validator);
                    break;
            }
            
            // Validaciones comunes
            $this->validateCommonRules($validator);
        });
    }

    /**
     * Validate register operation specific rules
     */
    private function validateRegisterOperation($validator): void
    {
        // Validar que la calidad sea suficiente para registro
        $qualityScore = $this->get('quality_score');
        if ($qualityScore && $qualityScore < 0.85) {
            $validator->errors()->add(
                'quality_score',
                'La calidad de captura es insuficiente para el registro. Mínimo requerido: 0.85'
            );
        }
        
        // Validar que no se exceda el límite de plantillas por usuario
        $userId = Auth::id();
        if ($userId) {
            $userTemplatesKey = "user_templates:{$userId}";
            $templateCount = count(Cache::get($userTemplatesKey, []));
            
            if ($templateCount >= 5) {
                $validator->errors()->add(
                    'operation',
                    'Límite de plantillas Face ID alcanzado. Máximo permitido: 5'
                );
            }
        }
    }

    /**
     * Validate verify operation specific rules
     */
    private function validateVerifyOperation($validator): void
    {
        // Validar contexto según tipo de verificación
        $verificationType = $this->get('verification_type');
        $context = $this->get('context', []);
        
        if (in_array($verificationType, ['transaction', 'payment']) && empty($context['transaction_id'])) {
            $validator->errors()->add(
                'context.transaction_id',
                'El ID de transacción es obligatorio para verificación de tipo: ' . $verificationType
            );
        }
        
        if (in_array($verificationType, ['payment']) && empty($context['amount'])) {
            $validator->errors()->add(
                'context.amount',
                'El monto es obligatorio para verificación de pago'
            );
        }
        
        if (in_array($verificationType, ['payment']) && empty($context['currency'])) {
            $validator->errors()->add(
                'context.currency',
                'La moneda es obligatoria para verificación de pago'
            );
        }
    }

    /**
     * Validate fallback operation specific rules
     */
    private function validateFallbackOperation($validator): void
    {
        // Validar que el usuario tenga PIN de fallback configurado
        $userId = Auth::id();
        if ($userId) {
            $fallbackPinExists = Cache::has("biometric_fallback:{$userId}");
            
            if (!$fallbackPinExists) {
                $validator->errors()->add(
                    'fallback_pin',
                    'No hay PIN de fallback configurado para este usuario'
                );
            }
        }
    }

    /**
     * Validate remove operation specific rules
     */
    private function validateRemoveOperation($validator): void
    {
        $templateId = $this->get('template_id');
        $removeAll = $this->get('remove_all', false);
        $removeDeviceOnly = $this->get('remove_device_only', false);
        
        // Validar que se proporcione al menos una opción de eliminación
        if (!$templateId && !$removeAll && !$removeDeviceOnly) {
            $validator->errors()->add(
                'template_id',
                'Debe especificar qué plantillas eliminar: template_id, remove_all o remove_device_only'
            );
        }
        
        // Validar que no se proporcionen opciones conflictivas
        if (($removeAll && $removeDeviceOnly) || 
            ($removeAll && $templateId) || 
            ($removeDeviceOnly && $templateId)) {
            $validator->errors()->add(
                'operation',
                'No se pueden especificar múltiples opciones de eliminación al mismo tiempo'
            );
        }
    }

    /**
     * Validate update operation specific rules
     */
    private function validateUpdateOperation($validator): void
    {
        $templateId = $this->get('template_id');
        $userId = Auth::id();
        
        if ($templateId && $userId) {
            // Validar que la plantilla pertenezca al usuario
            $template = Cache::get("biometric_template:{$templateId}");
            
            if (!$template || $template['user_id'] !== $userId) {
                $validator->errors()->add(
                    'template_id',
                    'La plantilla especificada no existe o no pertenece al usuario'
                );
            }
        }
    }

    /**
     * Validate validation operation specific rules
     */
    private function validateValidationOperation($validator): void
    {
        $validationType = $this->get('validation_type', 'quality');
        $minQualityThreshold = $this->get('min_quality_threshold');
        
        // Validar que el umbral de calidad sea coherente con el tipo de validación
        if ($validationType === 'quality' && $minQualityThreshold && $minQualityThreshold < 0.85) {
            $validator->errors()->add(
                'min_quality_threshold',
                'El umbral mínimo de calidad debe ser al menos 0.85 para validación de calidad'
            );
        }
    }

    /**
     * Validate common rules for all operations
     */
    private function validateCommonRules($validator): void
    {
        // Validar rate limiting
        $this->validateRateLimit($validator);
        
        // Validar integridad de datos biométricos
        $this->validateBiometricDataIntegrity($validator);
        
        // Validar contexto de seguridad
        $this->validateSecurityContext($validator);
    }

    /**
     * Validate rate limiting
     */
    private function validateRateLimit($validator): void
    {
        $operation = $this->get('operation');
        $deviceId = $this->get('device_id');
        $userId = Auth::id();
        
        if (!$userId || !$deviceId) {
            return;
        }
        
        $skipRateLimit = $this->get('skip_rate_limit', false);
        if ($skipRateLimit) {
            return; // Skip rate limit validation if explicitly requested
        }
        
        // Verificar límites por operación
        switch ($operation) {
            case 'register':
                $key = "face_register_attempts:{$userId}:{$deviceId}";
                $maxAttempts = 3; // Máximo 3 registros por día por dispositivo
                break;
            
            case 'verify':
                $key = "face_verify_attempts:{$userId}:{$deviceId}";
                $maxAttempts = 5; // Máximo 5 verificaciones por sesión
                break;
            
            case 'fallback':
                $key = "face_fallback_attempts:{$userId}:{$deviceId}";
                $maxAttempts = 3; // Máximo 3 intentos de fallback por sesión
                break;
            
            default:
                return;
        }
        
        $attempts = Cache::get($key, 0);
        if ($attempts >= $maxAttempts) {
            $validator->errors()->add(
                'operation',
                "Demasiados intentos de {$operation}. Intente nuevamente más tarde."
            );
        }
    }

    /**
     * Validate biometric data integrity
     */
    private function validateBiometricDataIntegrity($validator): void
    {
        $biometricData = $this->get('biometric_data');
        
        if (!$biometricData) {
            return;
        }
        
        // Validar que los datos no estén corruptos
        if (strlen($biometricData) < 100) {
            $validator->errors()->add(
                'biometric_data',
                'Los datos biométricos parecen estar corruptos o incompletos'
            );
        }
        
        // Validar que contengan caracteres válidos para datos encriptados
        if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $biometricData)) {
            $validator->errors()->add(
                'biometric_data',
                'Los datos biométricos no tienen un formato válido'
            );
        }
    }

    /**
     * Validate security context
     */
    private function validateSecurityContext($validator): void
    {
        $deviceId = $this->get('device_id');
        $deviceInfo = $this->get('device_info', []);
        
        // Validar que el device_id sea único y no sea reutilizado
        if ($deviceId && strlen($deviceId) < 15) {
            $validator->errors()->add(
                'device_id',
                'El ID del dispositivo debe ser más único y seguro'
            );
        }
        
        // Validar que la información del dispositivo sea consistente
        if (!empty($deviceInfo['platform']) && !empty($deviceInfo['version'])) {
            $platform = $deviceInfo['platform'];
            $version = $deviceInfo['version'];
            
            // Validaciones específicas por plataforma
            switch ($platform) {
                case 'ios':
                    if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $version)) {
                        $validator->errors()->add(
                            'device_info.version',
                            'La versión de iOS debe tener el formato X.Y o X.Y.Z'
                        );
                    }
                    break;
                
                case 'android':
                    if (!preg_match('/^\d+$/', $version)) {
                        $validator->errors()->add(
                            'device_info.version',
                            'La versión de Android debe ser un número de API level'
                        );
                    }
                    break;
                
                case 'web':
                    if (!preg_match('/^[\d\.]+$/', $version)) {
                        $validator->errors()->add(
                            'device_info.version',
                            'La versión web debe ser un número de versión válido'
                        );
                    }
                    break;
            }
        }
    }

    /**
     * Prepare the data for validation.
     * 
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Normalizar datos antes de la validación
        
        // Normalizar device_id
        if ($this->has('device_id')) {
            $this->merge([
                'device_id' => strtolower(trim($this->get('device_id'))),
            ]);
        }
        
        // Normalizar device_info
        if ($this->has('device_info') && is_array($this->get('device_info'))) {
            $deviceInfo = $this->get('device_info');
            
            if (isset($deviceInfo['platform'])) {
                $deviceInfo['platform'] = strtolower(trim($deviceInfo['platform']));
            }
            
            if (isset($deviceInfo['version'])) {
                $deviceInfo['version'] = trim($deviceInfo['version']);
            }
            
            if (isset($deviceInfo['model'])) {
                $deviceInfo['model'] = trim($deviceInfo['model']);
            }
            
            $this->merge(['device_info' => $deviceInfo]);
        }
        
        // Establecer valores por defecto
        $this->merge([
            'operation' => $this->get('operation', 'verify'),
            'verification_type' => $this->get('verification_type', 'login'),
            'force_verification' => $this->get('force_verification', false),
            'skip_rate_limit' => $this->get('skip_rate_limit', false),
            'remove_all' => $this->get('remove_all', false),
            'remove_device_only' => $this->get('remove_device_only', false),
            'is_active' => $this->get('is_active', true),
            'check_lighting' => $this->get('check_lighting', true),
            'check_angle' => $this->get('check_angle', true),
            'check_visibility' => $this->get('check_visibility', true),
        ]);
    }

    /**
     * Get the validated data from the request.
     * 
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);
        
        // Agregar información adicional para el procesamiento
        $validated['ip_address'] = $this->ip();
        $validated['user_agent'] = $this->userAgent();
        $validated['timestamp'] = now();
        $validated['session_id'] = session()->getId();
        $validated['user_id'] = Auth::id();
        
        return $validated;
    }

    /**
     * Get Face ID operation data in a standardized format compatible with FaceIdVerificationService.
     * 
     * @return array<string, mixed>
     */
    public function getFaceIdOperationData(): array
    {
        $validated = $this->validated();
        $operation = $validated['operation'];
        
        $baseData = [
            'user_id' => $validated['user_id'],
            'device_id' => $validated['device_id'],
            'device_info' => $validated['device_info'] ?? [],
            'ip_address' => $validated['ip_address'],
            'user_agent' => $validated['user_agent'],
            'timestamp' => $validated['timestamp'],
            'session_id' => $validated['session_id'],
        ];
        
        switch ($operation) {
            case 'register':
                return array_merge($baseData, [
                    'face_template' => [
                        'biometric_data' => $validated['biometric_data'],
                        'quality_score' => $validated['quality_score'],
                        'feature_points' => $validated['feature_points'] ?? [],
                    ],
                    'metadata' => $validated['metadata'] ?? [],
                    'fallback_pin' => $validated['fallback_pin'] ?? null,
                ]);
            
            case 'verify':
                return array_merge($baseData, [
                    'captured_face' => [
                        'biometric_data' => $validated['biometric_data'],
                    ],
                    'verification_type' => $validated['verification_type'],
                    'context' => $validated['context'] ?? [],
                    'force_verification' => $validated['force_verification'] ?? false,
                ]);
            
            case 'fallback':
                return array_merge($baseData, [
                    'fallback_pin' => $validated['fallback_pin'],
                    'verification_type' => $validated['verification_type'] ?? 'login',
                    'reason' => $validated['reason'] ?? null,
                ]);
            
            case 'remove':
                return array_merge($baseData, [
                    'template_id' => $validated['template_id'] ?? null,
                    'remove_all' => $validated['remove_all'] ?? false,
                    'remove_device_only' => $validated['remove_device_only'] ?? false,
                    'confirmation_code' => $validated['confirmation_code'],
                ]);
            
            case 'update':
                return array_merge($baseData, [
                    'template_id' => $validated['template_id'],
                    'biometric_data' => $validated['biometric_data'] ?? null,
                    'quality_score' => $validated['quality_score'] ?? null,
                    'metadata' => $validated['metadata'] ?? [],
                    'is_active' => $validated['is_active'] ?? true,
                ]);
            
            case 'validate':
                return array_merge($baseData, [
                    'biometric_data' => $validated['biometric_data'],
                    'validation_type' => $validated['validation_type'] ?? 'quality',
                    'min_quality_threshold' => $validated['min_quality_threshold'] ?? 0.85,
                    'check_lighting' => $validated['check_lighting'] ?? true,
                    'check_angle' => $validated['check_angle'] ?? true,
                    'check_visibility' => $validated['check_visibility'] ?? true,
                ]);
            
            default:
                return $baseData;
        }
    }

    /**
     * Check if this is a registration operation.
     * 
     * @return bool
     */
    public function isRegistrationOperation(): bool
    {
        return $this->get('operation') === 'register';
    }

    /**
     * Check if this is a verification operation.
     * 
     * @return bool
     */
    public function isVerificationOperation(): bool
    {
        return $this->get('operation') === 'verify';
    }

    /**
     * Check if this is a fallback operation.
     * 
     * @return bool
     */
    public function isFallbackOperation(): bool
    {
        return $this->get('operation') === 'fallback';
    }

    /**
     * Check if this is a removal operation.
     * 
     * @return bool
     */
    public function isRemovalOperation(): bool
    {
        return $this->get('operation') === 'remove';
    }

    /**
     * Check if this is an update operation.
     * 
     * @return bool
     */
    public function isUpdateOperation(): bool
    {
        return $this->get('operation') === 'update';
    }

    /**
     * Check if this is a validation operation.
     * 
     * @return bool
     */
    public function isValidationOperation(): bool
    {
        return $this->get('operation') === 'validate';
    }

    /**
     * Get the operation type for logging.
     * 
     * @return string
     */
    public function getOperationType(): string
    {
        return $this->get('operation', 'verify');
    }

    /**
     * Get the verification type for the operation.
     * 
     * @return string
     */
    public function getVerificationType(): string
    {
        return $this->get('verification_type', 'login');
    }

    /**
     * Get device fingerprint for security tracking.
     * 
     * @return string|null
     */
    public function getDeviceFingerprint(): ?string
    {
        $deviceInfo = $this->get('device_info', []);
        return $deviceInfo['fingerprint'] ?? null;
    }

    /**
     * Get security context for the operation.
     * 
     * @return array
     */
    public function getSecurityContext(): array
    {
        return [
            'operation_type' => $this->getOperationType(),
            'verification_type' => $this->getVerificationType(),
            'device_id' => $this->get('device_id'),
            'device_fingerprint' => $this->getDeviceFingerprint(),
            'ip_address' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'session_id' => session()->getId(),
            'timestamp' => now(),
            'force_verification' => $this->get('force_verification', false),
            'skip_rate_limit' => $this->get('skip_rate_limit', false),
            'context' => $this->get('context', []),
        ];
    }

    /**
     * Increment rate limit counter for the operation.
     * 
     * @return void
     */
    public function incrementRateLimit(): void
    {
        $operation = $this->getOperationType();
        $deviceId = $this->get('device_id');
        $userId = Auth::id();
        
        if (!$userId || !$deviceId) {
            return;
        }
        
        $key = "face_{$operation}_attempts:{$userId}:{$deviceId}";
        $attempts = Cache::get($key, 0) + 1;
        
        // Establecer expiración según el tipo de operación
        $expiration = match($operation) {
            'register' => now()->addDay(), // 1 día para registros
            'verify' => now()->addMinutes(30), // 30 minutos para verificaciones
            'fallback' => now()->addMinutes(15), // 15 minutos para fallback
            default => now()->addHour(), // 1 hora por defecto
        };
        
        Cache::put($key, $attempts, $expiration);
    }

    /**
     * Clear rate limit for the operation.
     * 
     * @return void
     */
    public function clearRateLimit(): void
    {
        $operation = $this->getOperationType();
        $deviceId = $this->get('device_id');
        $userId = Auth::id();
        
        if (!$userId || !$deviceId) {
            return;
        }
        
        $key = "face_{$operation}_attempts:{$userId}:{$deviceId}";
        Cache::forget($key);
    }

    /**
     * Get remaining attempts for the operation.
     * 
     * @return int
     */
    public function getRemainingAttempts(): int
    {
        $operation = $this->getOperationType();
        $deviceId = $this->get('device_id');
        $userId = Auth::id();
        
        if (!$userId || !$deviceId) {
            return 0;
        }
        
        $key = "face_{$operation}_attempts:{$userId}:{$deviceId}";
        $attempts = Cache::get($key, 0);
        
        $maxAttempts = match($operation) {
            'register' => 3,
            'verify' => 5,
            'fallback' => 3,
            default => 5,
        };
        
        return max(0, $maxAttempts - $attempts);
    }
}
