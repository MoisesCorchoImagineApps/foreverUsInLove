<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;

/**
 * RegisterRequest - Validación para el proceso de registro de usuarios
 * 
 * Maneja la validación completa del registro de usuarios en la aplicación
 * ForeverUsInLove siguiendo Clean Architecture y Domain-Driven Design.
 * 
 * Integrado con:
 * - RegistrationService para lógica de negocio
 * - UserRegistered event para notificaciones
 * - FaceIdVerificationService para autenticación biométrica
 * - UserRepository para validaciones de unicidad
 * 
 * @package App\Http\Requests\Auth
 * @version 2.0.0
 * @author ForeverUsInLove Team
 */
class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Cualquier usuario puede intentar registrarse
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
            // DATOS PERSONALES BÁSICOS (OBLIGATORIOS)
            // ========================================
            
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                'unique:users,email',
                'confirmed', // Requiere email_confirmation
            ],
            
            'email_confirmation' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                'same:email',
            ],
            
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'unique:users,username',
                'alpha_dash',
                'regex:/^[a-zA-Z0-9_-]+$/', // Solo letras, números, guiones y guiones bajos
                function ($attribute, $value, $fail) {
                    // Validar que no contenga palabras prohibidas
                    $forbiddenWords = ['admin', 'root', 'system', 'api', 'www', 'support', 'help'];
                    if (in_array(strtolower($value), $forbiddenWords)) {
                        $fail('Este nombre de usuario no está disponible.');
                    }
                },
            ],
            
            'password' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'confirmed', // Requiere password_confirmation
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
            
            'password_confirmation' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'same:password',
            ],
            
            // ========================================
            // INFORMACIÓN PERSONAL ADICIONAL
            // ========================================
            
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
                'regex:/^[a-zA-ZÀ-ÿ\s]+$/', // Solo letras y espacios
            ],
            
            'date_of_birth' => [
                'required',
                'date',
                'before:' . now()->subYears(18)->format('Y-m-d'), // Mayor de 18 años
                'after:' . now()->subYears(100)->format('Y-m-d'), // Menor de 100 años
            ],
            
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'unique:users,phone',
                'regex:/^[0-9+\-\s()]{10,20}$/',
            ],
            
            // ========================================
            // FACE ID Y AUTENTICACIÓN BIOMÉTRICA
            // ========================================
            
            'face_id_data' => [
                'sometimes',
                'string',
                'max:10000', // Datos biométricos pueden ser grandes
                function ($attribute, $value, $fail) {
                    // Validar que los datos de Face ID sean válidos
                    if ($value && !$this->isValidFaceIdData($value)) {
                        $fail('Los datos de Face ID no son válidos.');
                    }
                },
            ],
            
            'face_id_enabled' => [
                'sometimes',
                'boolean',
            ],
            
            'biometric_fallback_pin' => [
                'required_with:face_id_data',
                'string',
                'size:6',
                'regex:/^[0-9]{6}$/',
            ],
            
            // ========================================
            // INFORMACIÓN DEL DISPOSITIVO
            // ========================================
            
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
            
            'device_info.device_id' => [
                'required_with:face_id_data',
                'string',
                'min:10',
                'max:100',
            ],
            
            'device_token' => [
                'sometimes',
                'string',
                'max:500',
            ],
            
            // ========================================
            // UBICACIÓN Y GEOGRAFÍA
            // ========================================
            
            'location' => [
                'sometimes',
                'array',
            ],
            
            'location.country' => [
                'required_with:location',
                'string',
                'size:2', // Código ISO de país
                'regex:/^[A-Z]{2}$/',
            ],
            
            'location.city' => [
                'sometimes',
                'string',
                'max:100',
            ],
            
            'location.latitude' => [
                'sometimes',
                'numeric',
                'between:-90,90',
            ],
            
            'location.longitude' => [
                'sometimes',
                'numeric',
                'between:-180,180',
            ],
            
            'location.timezone' => [
                'sometimes',
                'string',
                'max:50',
                'regex:/^[A-Za-z\/_]+$/',
            ],
            
            // ========================================
            // PREFERENCIAS Y CONFIGURACIÓN
            // ========================================
            
            'marketing_emails_consent' => [
                'sometimes',
                'boolean',
            ],
            
            'terms_accepted' => [
                'required',
                'boolean',
                'accepted',
            ],
            
            'privacy_policy_accepted' => [
                'required',
                'boolean',
                'accepted',
            ],
            
            'gdpr_consent' => [
                'sometimes',
                'boolean',
            ],
            
            'age_verification' => [
                'required',
                'boolean',
                'accepted',
            ],
            
            // ========================================
            // DATOS DE REFERENCIA Y MARKETING
            // ========================================
            
            'referral_code' => [
                'sometimes',
                'string',
                'max:50',
                'alpha_dash',
            ],
            
            'utm_source' => [
                'sometimes',
                'string',
                'max:100',
            ],
            
            'utm_medium' => [
                'sometimes',
                'string',
                'max:100',
            ],
            
            'utm_campaign' => [
                'sometimes',
                'string',
                'max:100',
            ],
            
            'utm_term' => [
                'sometimes',
                'string',
                'max:100',
            ],
            
            'utm_content' => [
                'sometimes',
                'string',
                'max:100',
            ],
            
            // ========================================
            // CONFIGURACIÓN DE REGISTRO
            // ========================================
            
            'registration_source' => [
                'sometimes',
                'string',
                'in:web,mobile_app,api,social',
            ],
            
            'registration_channel' => [
                'sometimes',
                'string',
                'in:email,phone,facebook,google,apple',
            ],
            
            'language' => [
                'sometimes',
                'string',
                'size:2',
                'in:en,es,fr,de,it,pt,ru,zh,ja,ko',
            ],
            
            'timezone_offset' => [
                'sometimes',
                'integer',
                'between:-720,720', // -12 a +12 horas
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
            // Email
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El formato del email no es válido.',
            'email.unique' => 'Este email ya está registrado.',
            'email.confirmed' => 'El email y la confirmación no coinciden.',
            'email_confirmation.required' => 'La confirmación del email es obligatoria.',
            'email_confirmation.same' => 'El email de confirmación no coincide.',
            
            // Username
            'username.required' => 'El nombre de usuario es obligatorio.',
            'username.min' => 'El nombre de usuario debe tener al menos 3 caracteres.',
            'username.max' => 'El nombre de usuario no puede tener más de 30 caracteres.',
            'username.unique' => 'Este nombre de usuario ya está en uso.',
            'username.alpha_dash' => 'El nombre de usuario solo puede contener letras, números, guiones y guiones bajos.',
            'username.regex' => 'El nombre de usuario contiene caracteres no válidos.',
            
            // Password
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La contraseña y la confirmación no coinciden.',
            'password.regex' => 'La contraseña debe contener al menos: una letra minúscula, una mayúscula, un número y un carácter especial.',
            'password_confirmation.required' => 'La confirmación de contraseña es obligatoria.',
            'password_confirmation.same' => 'La confirmación de contraseña no coincide.',
            
            // Name
            'name.required' => 'El nombre es obligatorio.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
            
            // Date of birth
            'date_of_birth.required' => 'La fecha de nacimiento es obligatoria.',
            'date_of_birth.before' => 'Debes ser mayor de 18 años para registrarte.',
            'date_of_birth.after' => 'La fecha de nacimiento no es válida.',
            
            // Phone
            'phone.unique' => 'Este número de teléfono ya está registrado.',
            'phone.regex' => 'El formato del número de teléfono no es válido.',
            
            // Face ID
            'face_id_data.max' => 'Los datos de Face ID exceden el tamaño máximo permitido.',
            'biometric_fallback_pin.required_with' => 'El PIN de fallback es obligatorio cuando se registra Face ID.',
            'biometric_fallback_pin.size' => 'El PIN de fallback debe tener exactamente 6 dígitos.',
            'biometric_fallback_pin.regex' => 'El PIN de fallback debe contener solo números.',
            
            // Device info
            'device_info.platform.required_with' => 'La plataforma del dispositivo es obligatoria.',
            'device_info.platform.in' => 'La plataforma debe ser: ios, android o web.',
            'device_info.version.required_with' => 'La versión del dispositivo es obligatoria.',
            'device_info.device_id.required_with' => 'El ID del dispositivo es obligatorio para Face ID.',
            
            // Location
            'location.country.required_with' => 'El país es obligatorio cuando se proporciona ubicación.',
            'location.country.size' => 'El código de país debe tener 2 caracteres.',
            'location.country.regex' => 'El código de país debe ser en formato ISO (ej: ES, US).',
            'location.latitude.between' => 'La latitud debe estar entre -90 y 90.',
            'location.longitude.between' => 'La longitud debe estar entre -180 y 180.',
            
            // Terms and conditions
            'terms_accepted.required' => 'Debes aceptar los términos y condiciones.',
            'terms_accepted.accepted' => 'Debes aceptar los términos y condiciones.',
            'privacy_policy_accepted.required' => 'Debes aceptar la política de privacidad.',
            'privacy_policy_accepted.accepted' => 'Debes aceptar la política de privacidad.',
            'age_verification.required' => 'Debes verificar que eres mayor de edad.',
            'age_verification.accepted' => 'Debes verificar que eres mayor de edad.',
            
            // Marketing
            'referral_code.alpha_dash' => 'El código de referido contiene caracteres no válidos.',
            
            // Registration settings
            'registration_source.in' => 'La fuente de registro debe ser: web, mobile_app, api o social.',
            'registration_channel.in' => 'El canal de registro debe ser: email, phone, facebook, google o apple.',
            'language.size' => 'El código de idioma debe tener 2 caracteres.',
            'language.in' => 'El idioma seleccionado no está disponible.',
            'timezone_offset.between' => 'El offset de zona horaria debe estar entre -720 y 720 minutos.',
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
            'email' => 'email',
            'email_confirmation' => 'confirmación de email',
            'username' => 'nombre de usuario',
            'password' => 'contraseña',
            'password_confirmation' => 'confirmación de contraseña',
            'name' => 'nombre completo',
            'date_of_birth' => 'fecha de nacimiento',
            'phone' => 'teléfono',
            'face_id_data' => 'datos de Face ID',
            'biometric_fallback_pin' => 'PIN de fallback biométrico',
            'device_info.platform' => 'plataforma del dispositivo',
            'device_info.version' => 'versión del dispositivo',
            'device_info.model' => 'modelo del dispositivo',
            'device_info.device_id' => 'ID del dispositivo',
            'device_token' => 'token del dispositivo',
            'location.country' => 'país',
            'location.city' => 'ciudad',
            'location.latitude' => 'latitud',
            'location.longitude' => 'longitud',
            'location.timezone' => 'zona horaria',
            'marketing_emails_consent' => 'consentimiento de emails de marketing',
            'terms_accepted' => 'términos y condiciones',
            'privacy_policy_accepted' => 'política de privacidad',
            'gdpr_consent' => 'consentimiento GDPR',
            'age_verification' => 'verificación de edad',
            'referral_code' => 'código de referido',
            'utm_source' => 'fuente UTM',
            'utm_medium' => 'medio UTM',
            'utm_campaign' => 'campaña UTM',
            'utm_term' => 'término UTM',
            'utm_content' => 'contenido UTM',
            'registration_source' => 'fuente de registro',
            'registration_channel' => 'canal de registro',
            'language' => 'idioma',
            'timezone_offset' => 'offset de zona horaria',
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
            
            // Validar edad mínima basada en fecha de nacimiento
            if ($this->has('date_of_birth')) {
                $age = Carbon::parse($this->get('date_of_birth'))->age;
                if ($age < 18) {
                    $validator->errors()->add(
                        'date_of_birth', 
                        'Debes ser mayor de 18 años para registrarte en la aplicación.'
                    );
                }
            }
            
            // Validar que si se proporciona Face ID, también se proporcione device_id
            if ($this->has('face_id_data') && !$this->has('device_info.device_id')) {
                $validator->errors()->add(
                    'device_info.device_id', 
                    'El ID del dispositivo es obligatorio cuando se registra Face ID.'
                );
            }
            
            // Validar que si se proporciona location, tenga al menos país
            if ($this->has('location') && !$this->has('location.country')) {
                $validator->errors()->add(
                    'location.country', 
                    'El país es obligatorio cuando se proporciona información de ubicación.'
                );
            }
            
            // Validar consistencia de coordenadas
            if ($this->has('location.latitude') && !$this->has('location.longitude')) {
                $validator->errors()->add(
                    'location.longitude', 
                    'La longitud es obligatoria cuando se proporciona latitud.'
                );
            }
            
            if ($this->has('location.longitude') && !$this->has('location.latitude')) {
                $validator->errors()->add(
                    'location.latitude', 
                    'La latitud es obligatoria cuando se proporciona longitud.'
                );
            }
            
            // Validar que los términos y políticas sean aceptados
            if (!$this->get('terms_accepted')) {
                $validator->errors()->add(
                    'terms_accepted', 
                    'Debes aceptar los términos y condiciones para continuar.'
                );
            }
            
            if (!$this->get('privacy_policy_accepted')) {
                $validator->errors()->add(
                    'privacy_policy_accepted', 
                    'Debes aceptar la política de privacidad para continuar.'
                );
            }
            
            if (!$this->get('age_verification')) {
                $validator->errors()->add(
                    'age_verification', 
                    'Debes confirmar que eres mayor de 18 años.'
                );
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
        
        // Normalizar email
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->get('email'))),
            ]);
        }
        
        if ($this->has('email_confirmation')) {
            $this->merge([
                'email_confirmation' => strtolower(trim($this->get('email_confirmation'))),
            ]);
        }
        
        // Normalizar username
        if ($this->has('username')) {
            $this->merge([
                'username' => strtolower(trim($this->get('username'))),
            ]);
        }
        
        // Normalizar nombre
        if ($this->has('name')) {
            $this->merge([
                'name' => ucwords(strtolower(trim($this->get('name')))),
            ]);
        }
        
        // Normalizar teléfono
        if ($this->has('phone')) {
            $phone = preg_replace('/[^0-9+]/', '', $this->get('phone'));
            $this->merge(['phone' => $phone]);
        }
        
        // Normalizar código de referido
        if ($this->has('referral_code')) {
            $this->merge([
                'referral_code' => strtoupper(trim($this->get('referral_code'))),
            ]);
        }
        
        // Establecer valores por defecto
        $this->merge([
            'registration_source' => $this->get('registration_source', 'web'),
            'registration_channel' => $this->get('registration_channel', 'email'),
            'language' => $this->get('language', 'es'),
            'marketing_emails_consent' => $this->get('marketing_emails_consent', false),
            'gdpr_consent' => $this->get('gdpr_consent', false),
            'face_id_enabled' => $this->has('face_id_data'),
        ]);
        
        // Limpiar información del dispositivo
        if ($this->has('device_info') && is_array($this->get('device_info'))) {
            $deviceInfo = array_map('trim', $this->get('device_info'));
            $this->merge(['device_info' => $deviceInfo]);
        }
    }

    /**
     * Get the validated data from the request.
     * 
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);
        
        // Agregar información adicional para el proceso de registro
        $validated['ip_address'] = $this->ip();
        $validated['user_agent'] = $this->userAgent();
        $validated['timestamp'] = now();
        $validated['session_id'] = session()->getId();
        
        return $validated;
    }

    /**
     * Get registration data in a standardized format compatible with RegistrationService.
     * 
     * @return array<string, mixed>
     */
    public function getRegistrationData(): array
    {
        $validated = $this->validated();
        
        // Datos principales del usuario
        $userData = [
            'email' => $validated['email'],
            'username' => $validated['username'],
            'password' => $validated['password'],
            'name' => $validated['name'],
            'date_of_birth' => $validated['date_of_birth'],
            'phone' => $validated['phone'] ?? null,
            'marketing_emails_consent' => $validated['marketing_emails_consent'] ?? false,
            'device_token' => $validated['device_token'] ?? null,
        ];
        
        // Datos de Face ID si se proporcionan
        if (isset($validated['face_id_data'])) {
            $userData['face_id_data'] = $validated['face_id_data'];
            $userData['face_id_enabled'] = true;
            $userData['biometric_fallback_pin'] = $validated['biometric_fallback_pin'];
        }
        
        // Información del dispositivo
        $deviceInfo = array_merge(
            $validated['device_info'] ?? [],
            [
                'ip_address' => $validated['ip_address'],
                'user_agent' => $validated['user_agent'],
                'timestamp' => $validated['timestamp'],
                'session_id' => $validated['session_id'],
            ]
        );
        
        // Datos de ubicación
        $locationData = $validated['location'] ?? null;
        
        // Datos de marketing y referencias
        $marketingData = [
            'referral_code' => $validated['referral_code'] ?? null,
            'utm_source' => $validated['utm_source'] ?? null,
            'utm_medium' => $validated['utm_medium'] ?? null,
            'utm_campaign' => $validated['utm_campaign'] ?? null,
            'utm_term' => $validated['utm_term'] ?? null,
            'utm_content' => $validated['utm_content'] ?? null,
        ];
        
        return [
            'user_data' => $userData,
            'device_info' => $deviceInfo,
            'location_data' => $locationData,
            'marketing_data' => $marketingData,
            'registration_source' => $validated['registration_source'],
            'registration_channel' => $validated['registration_channel'],
            'language' => $validated['language'],
            'gdpr_consent' => $validated['gdpr_consent'] ?? false,
        ];
    }

    /**
     * Check if Face ID registration is requested.
     * 
     * @return bool
     */
    public function hasFaceIdRegistration(): bool
    {
        return $this->has('face_id_data') && !empty($this->get('face_id_data'));
    }

    /**
     * Check if phone registration is requested.
     * 
     * @return bool
     */
    public function hasPhoneRegistration(): bool
    {
        return $this->has('phone') && !empty($this->get('phone'));
    }

    /**
     * Get the registration method for UserRegistered event.
     * 
     * @return string
     */
    public function getRegistrationMethod(): string
    {
        if ($this->hasFaceIdRegistration()) {
            return 'face_id';
        }
        
        if ($this->hasPhoneRegistration()) {
            return 'phone';
        }
        
        return 'email';
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
     * Get UTM parameters for analytics.
     * 
     * @return array
     */
    public function getUtmParameters(): array
    {
        return [
            'utm_source' => $this->get('utm_source'),
            'utm_medium' => $this->get('utm_medium'),
            'utm_campaign' => $this->get('utm_campaign'),
            'utm_term' => $this->get('utm_term'),
            'utm_content' => $this->get('utm_content'),
        ];
    }

    /**
     * Get registration context for UserRegistered event.
     * 
     * @return array
     */
    public function getRegistrationContext(): array
    {
        return [
            'registration_method' => $this->getRegistrationMethod(),
            'registration_source' => $this->get('registration_source', 'web'),
            'registration_channel' => $this->get('registration_channel', 'email'),
            'device_fingerprint' => $this->getDeviceFingerprint(),
            'language' => $this->get('language', 'es'),
            'timezone_offset' => $this->get('timezone_offset'),
            'referral_code' => $this->get('referral_code'),
            'utm_parameters' => $this->getUtmParameters(),
            'marketing_consent' => $this->get('marketing_emails_consent', false),
            'gdpr_consent' => $this->get('gdpr_consent', false),
            'face_id_enabled' => $this->hasFaceIdRegistration(),
            'phone_provided' => $this->hasPhoneRegistration(),
            'location_provided' => $this->has('location'),
            'terms_accepted_at' => now(),
            'privacy_policy_accepted_at' => now(),
            'age_verified_at' => now(),
        ];
    }

    /**
     * Validate Face ID data format.
     * 
     * @param string $faceIdData
     * @return bool
     */
    private function isValidFaceIdData(string $faceIdData): bool
    {
        // Validación básica de datos biométricos
        // En un entorno real, esto sería más complejo
        return !empty($faceIdData) && 
               strlen($faceIdData) > 100 && 
               strlen($faceIdData) < 10000 &&
               is_string($faceIdData);
    }

    /**
     * Calculate user age from date of birth.
     * 
     * @return int|null
     */
    public function getUserAge(): ?int
    {
        if (!$this->has('date_of_birth')) {
            return null;
        }
        
        return Carbon::parse($this->get('date_of_birth'))->age;
    }

    /**
     * Check if user meets age requirements.
     * 
     * @return bool
     */
    public function meetsAgeRequirements(): bool
    {
        $age = $this->getUserAge();
        return $age !== null && $age >= 18;
    }

    /**
     * Get GDPR consent data.
     * 
     * @return array
     */
    public function getGdprConsentData(): array
    {
        return [
            'marketing_emails' => $this->get('marketing_emails_consent', false),
            'data_processing' => true, // Implícito al registrarse
            'analytics' => true, // Implícito al registrarse
            'personalization' => $this->get('marketing_emails_consent', false),
            'consent_date' => now(),
            'ip_address' => $this->ip(),
            'user_agent' => $this->userAgent(),
        ];
    }
}
