<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * LoginRequest - Validación para el proceso de login
 * 
 * Maneja la validación de credenciales para autenticación de usuarios
 * en la aplicación ForeverUsInLove siguiendo Clean Architecture.
 * 
 * @package App\Http\Requests\Auth
 * @version 1.0.0
 * @author ForeverUsInLove Team
 */
class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Cualquier usuario puede intentar hacer login
    }

    /**
     * Get the validation rules that apply to the request.
     * 
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Campo de login (puede ser email, username o phone)
            'login' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Validar que sea un formato válido para email, username o phone
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL) && 
                        !preg_match('/^[a-zA-Z0-9_-]+$/', $value) && 
                        !preg_match('/^[0-9+\-\s()]{10,20}$/', $value)) {
                        $fail('El campo :attribute debe ser un email válido, username o número de teléfono.');
                    }
                },
            ],
            
            // Contraseña
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
            ],
            
            // Recordar sesión (opcional)
            'remember' => [
                'sometimes',
                'boolean',
            ],
            
            // Token de dispositivo para notificaciones push (opcional)
            'device_token' => [
                'sometimes',
                'string',
                'max:500',
            ],
            
            // Datos de Face ID para autenticación biométrica (opcional)
            'face_id_data' => [
                'sometimes',
                'string',
                'max:1000',
            ],
            
            // Código de verificación de dos factores (opcional)
            'two_factor_code' => [
                'sometimes',
                'string',
                'size:6',
                'regex:/^[0-9]{6}$/',
            ],
            
            // Código de verificación de email (opcional)
            'email_verification_code' => [
                'sometimes',
                'string',
                'size:6',
                'regex:/^[0-9]{6}$/',
            ],
            
            // Código de verificación de teléfono (opcional)
            'phone_verification_code' => [
                'sometimes',
                'string',
                'size:6',
                'regex:/^[0-9]{6}$/',
            ],
            
            // Tipo de login (email, username, phone, face_id)
            'login_type' => [
                'sometimes',
                'string',
                'in:email,username,phone,face_id',
            ],
            
            // Información del dispositivo (opcional)
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
            'login.required' => 'El campo de login es obligatorio.',
            'login.string' => 'El campo de login debe ser una cadena de texto.',
            'login.max' => 'El campo de login no puede tener más de 255 caracteres.',
            
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser una cadena de texto.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede tener más de 255 caracteres.',
            
            'remember.boolean' => 'El campo recordar debe ser verdadero o falso.',
            
            'device_token.string' => 'El token del dispositivo debe ser una cadena de texto.',
            'device_token.max' => 'El token del dispositivo no puede tener más de 500 caracteres.',
            
            'face_id_data.string' => 'Los datos de Face ID deben ser una cadena de texto.',
            'face_id_data.max' => 'Los datos de Face ID no pueden tener más de 1000 caracteres.',
            
            'login_type.in' => 'El tipo de login debe ser: email, username, phone o face_id.',
            
            'device_info.array' => 'La información del dispositivo debe ser un array.',
            'device_info.platform.required_with' => 'La plataforma del dispositivo es obligatoria cuando se proporciona información del dispositivo.',
            'device_info.platform.in' => 'La plataforma del dispositivo debe ser: ios, android o web.',
            'device_info.version.required_with' => 'La versión del dispositivo es obligatoria cuando se proporciona información del dispositivo.',
            'device_info.version.max' => 'La versión del dispositivo no puede tener más de 50 caracteres.',
            'device_info.model.max' => 'El modelo del dispositivo no puede tener más de 100 caracteres.',
            
            'two_factor_code.size' => 'El código de dos factores debe tener exactamente 6 dígitos.',
            'two_factor_code.regex' => 'El código de dos factores debe contener solo números.',
            
            'email_verification_code.size' => 'El código de verificación de email debe tener exactamente 6 dígitos.',
            'email_verification_code.regex' => 'El código de verificación de email debe contener solo números.',
            
            'phone_verification_code.size' => 'El código de verificación de teléfono debe tener exactamente 6 dígitos.',
            'phone_verification_code.regex' => 'El código de verificación de teléfono debe contener solo números.',
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
            'login' => 'email, usuario o teléfono',
            'password' => 'contraseña',
            'remember' => 'recordar sesión',
            'device_token' => 'token del dispositivo',
            'face_id_data' => 'datos de Face ID',
            'login_type' => 'tipo de login',
            'device_info' => 'información del dispositivo',
            'device_info.platform' => 'plataforma del dispositivo',
            'device_info.version' => 'versión del dispositivo',
            'device_info.model' => 'modelo del dispositivo',
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
            
            // Si se proporciona face_id_data, debe incluir login_type
            if ($this->has('face_id_data') && !$this->has('login_type')) {
                $validator->errors()->add(
                    'login_type', 
                    'El tipo de login es obligatorio cuando se usan datos de Face ID.'
                );
            }
            
            // Si login_type es face_id, debe proporcionar face_id_data
            if ($this->get('login_type') === 'face_id' && !$this->has('face_id_data')) {
                $validator->errors()->add(
                    'face_id_data', 
                    'Los datos de Face ID son obligatorios cuando el tipo de login es face_id.'
                );
            }
            
            // Validar formato específico según el tipo de login
            $loginValue = $this->get('login');
            $loginType = $this->get('login_type');
            
            if ($loginType === 'email' && !filter_var($loginValue, FILTER_VALIDATE_EMAIL)) {
                $validator->errors()->add('login', 'El formato del email no es válido.');
            }
            
            if ($loginType === 'username' && !preg_match('/^[a-zA-Z0-9_-]+$/', $loginValue)) {
                $validator->errors()->add('login', 'El formato del nombre de usuario no es válido.');
            }
            
            if ($loginType === 'phone' && !preg_match('/^[0-9+\-\s()]{10,20}$/', $loginValue)) {
                $validator->errors()->add('login', 'El formato del número de teléfono no es válido.');
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
        
        // Normalizar el campo de login
        if ($this->has('login')) {
            $this->merge([
                'login' => strtolower(trim($this->get('login'))),
            ]);
        }
        
        // Detectar automáticamente el tipo de login si no se proporciona
        if (!$this->has('login_type') && $this->has('login')) {
            $loginValue = $this->get('login');
            
            if (filter_var($loginValue, FILTER_VALIDATE_EMAIL)) {
                $this->merge(['login_type' => 'email']);
            } elseif (preg_match('/^[0-9+\-\s()]{10,20}$/', $loginValue)) {
                $this->merge(['login_type' => 'phone']);
            } else {
                $this->merge(['login_type' => 'username']);
            }
        }
        
        // Limpiar información del dispositivo
        if ($this->has('device_info')) {
            $deviceInfo = $this->get('device_info');
            if (is_array($deviceInfo)) {
                $this->merge([
                    'device_info' => array_map('trim', $deviceInfo),
                ]);
            }
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
        
        // Agregar información adicional para el proceso de login
        $validated['ip_address'] = $this->ip();
        $validated['user_agent'] = $this->userAgent();
        $validated['timestamp'] = now();
        
        return $validated;
    }

    /**
     * Get the login credentials in a standardized format compatible with AuthenticationService.
     * 
     * @return array<string, mixed>
     */
    public function getLoginCredentials(): array
    {
        $validated = $this->validated();
        $loginType = $validated['login_type'] ?? 'email';
        $loginValue = $validated['login'];
        
        // Mapear a los campos esperados por AuthenticationService
        $credentials = [
            'password' => $validated['password'],
            'ip_address' => $validated['ip_address'],
            'user_agent' => $validated['user_agent'],
            'timestamp' => $validated['timestamp'],
        ];
        
        // Agregar el campo correcto según el tipo de login
        switch ($loginType) {
            case 'email':
                $credentials['email'] = $loginValue;
                break;
            case 'phone':
                $credentials['phone'] = $loginValue;
                break;
            case 'username':
                $credentials['username'] = $loginValue;
                break;
            case 'face_id':
                $credentials['face_id_data'] = $validated['face_id_data'];
                break;
        }
        
        return [
            'credentials' => $credentials,
            'login_type' => $loginType,
            'remember' => $validated['remember'] ?? false,
            'device_token' => $validated['device_token'] ?? null,
            'device_info' => $validated['device_info'] ?? null,
            'face_id_data' => $validated['face_id_data'] ?? null,
            'two_factor_code' => $validated['two_factor_code'] ?? null,
            'email_verification_code' => $validated['email_verification_code'] ?? null,
            'phone_verification_code' => $validated['phone_verification_code'] ?? null,
        ];
    }

    /**
     * Check if this is a Face ID login attempt.
     * 
     * @return bool
     */
    public function isFaceIdLogin(): bool
    {
        return $this->get('login_type') === 'face_id' || $this->has('face_id_data');
    }

    /**
     * Check if this is a phone number login attempt.
     * 
     * @return bool
     */
    public function isPhoneLogin(): bool
    {
        return $this->get('login_type') === 'phone';
    }

    /**
     * Check if this is an email login attempt.
     * 
     * @return bool
     */
    public function isEmailLogin(): bool
    {
        return $this->get('login_type') === 'email';
    }

    /**
     * Check if this is a username login attempt.
     * 
     * @return bool
     */
    public function isUsernameLogin(): bool
    {
        return $this->get('login_type') === 'username';
    }

    /**
     * Check if this login requires two-factor authentication.
     * 
     * @return bool
     */
    public function requiresTwoFactor(): bool
    {
        return $this->has('two_factor_code') || 
               $this->has('email_verification_code') || 
               $this->has('phone_verification_code');
    }

    /**
     * Get the authentication method for UserLoggedIn event.
     * 
     * @return string
     */
    public function getAuthenticationMethod(): string
    {
        if ($this->isFaceIdLogin()) {
            return 'face_id';
        }
        
        if ($this->requiresTwoFactor()) {
            return 'two_factor';
        }
        
        return $this->get('login_type', 'email');
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
     * Get location data for UserLoggedIn event.
     * 
     * @return array
     */
    public function getLocationData(): array
    {
        return [
            'ip_address' => $this->ip(),
            'country' => $this->get('country'),
            'city' => $this->get('city'),
            'timezone' => $this->get('timezone'),
            'coordinates' => [
                'latitude' => $this->get('latitude'),
                'longitude' => $this->get('longitude'),
            ],
        ];
    }

    /**
     * Get login context for UserLoggedIn event.
     * 
     * @return array
     */
    public function getLoginContext(): array
    {
        return [
            'two_factor_used' => $this->requiresTwoFactor(),
            'remember_me' => $this->get('remember', false),
            'device_fingerprint' => $this->getDeviceFingerprint(),
            'login_source' => $this->get('login_source', 'direct'),
            'api_version' => $this->get('api_version', 'v1'),
            'app_version' => $this->get('device_info.app_version'),
            'browser_language' => $this->get('browser_language'),
            'screen_resolution' => $this->get('screen_resolution'),
            'timezone_offset' => $this->get('timezone_offset'),
            'is_vpn' => $this->get('is_vpn', false),
            'is_proxy' => $this->get('is_proxy', false),
            'connection_type' => $this->get('connection_type'),
            'risk_score' => $this->get('risk_score', 0),
            'suspicious_activity' => $this->get('suspicious_activity', false),
            'failed_attempts' => $this->get('failed_attempts', 0),
            'auth_duration_ms' => $this->get('auth_duration_ms'),
            'active_sessions_count' => $this->get('active_sessions_count', 1),
            'concurrent_sessions' => $this->get('concurrent_sessions', []),
            'session_timeout' => $this->get('session_timeout'),
            'remember_token_expires' => $this->get('remember_token_expires'),
            'requires_verification' => $this->get('requires_verification', false),
            'unread_messages' => $this->get('unread_messages', 0),
            'pending_matches' => $this->get('pending_matches', 0),
            'profile_completion' => $this->get('profile_completion', 0),
            'subscription_status' => $this->get('subscription_status', 'free'),
            'referrer' => $this->get('referrer'),
            'utm_parameters' => $this->get('utm_parameters', []),
            'can_receive_messages' => $this->get('can_receive_messages', true),
        ];
    }

    /**
     * Generate session ID for UserLoggedIn event.
     * 
     * @return string
     */
    public function generateSessionId(): string
    {
        return 'sess_' . uniqid() . '_' . time();
    }

    /**
     * Check if this is a new device based on fingerprint.
     * 
     * @return bool
     */
    public function isNewDevice(): bool
    {
        // Esta lógica debería consultar la base de datos
        // Por ahora retornamos false como placeholder
        return false;
    }

    /**
     * Check if this is a new location based on IP.
     * 
     * @return bool
     */
    public function isNewLocation(): bool
    {
        // Esta lógica debería consultar la base de datos
        // Por ahora retornamos false como placeholder
        return false;
    }
}
