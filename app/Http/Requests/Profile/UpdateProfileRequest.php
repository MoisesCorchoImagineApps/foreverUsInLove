<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateProfileRequest - Validación para actualización de perfil
 * 
 * Este FormRequest maneja la validación de datos para la actualización
 * del perfil de usuario, incluyendo validaciones específicas para cada
 * campo del modelo Profile.
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // El usuario solo puede actualizar su propio perfil
        $user = $this->user();
        
        return $user && 
               $user->profile && 
               $user->profile->user_id === $user->id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Información personal básica
            'first_name' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/'],
            'last_name' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/'],
            'display_name' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s._-]+$/'],
            'bio' => ['nullable', 'string', 'max:500', 'min:10'],
            'tagline' => ['nullable', 'string', 'max:100'],
            
            // Información demográfica
            'date_of_birth' => [
                'nullable', 
                'date', 
                'before:' . now()->subYears(18)->format('Y-m-d'),
                'after:' . now()->subYears(100)->format('Y-m-d')
            ],
            'gender' => ['nullable', Rule::in(['male', 'female', 'non_binary', 'other', 'prefer_not_to_say'])],
            'gender_identity' => ['nullable', 'string', 'max:100'],
            'sexual_orientation' => [
                'nullable', 
                Rule::in(['straight', 'gay', 'lesbian', 'bisexual', 'pansexual', 'asexual', 'other'])
            ],
            
            // Estado de relación y búsqueda
            'relationship_status' => [
                'nullable', 
                Rule::in(['single', 'divorced', 'widowed', 'separated'])
            ],
            'looking_for' => [
                'nullable', 
                Rule::in(['relationship', 'friendship', 'casual', 'marriage'])
            ],
            
            // Ubicación
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            
            // Características físicas
            'height_cm' => ['nullable', 'integer', 'between:100,250'],
            'body_type' => [
                'nullable', 
                Rule::in(['slim', 'athletic', 'average', 'curvy', 'heavyset'])
            ],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            
            // Educación y trabajo
            'education' => [
                'nullable', 
                Rule::in(['high_school', 'some_college', 'bachelors', 'masters', 'phd', 'trade_school'])
            ],
            'occupation' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:100'],
            'school' => ['nullable', 'string', 'max:100'],
            'income_range' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            
            // Estilo de vida
            'religion' => ['nullable', 'string', 'max:100'],
            'smoking' => [
                'nullable', 
                Rule::in(['never', 'occasionally', 'regularly', 'trying_to_quit'])
            ],
            'drinking' => [
                'nullable', 
                Rule::in(['never', 'socially', 'regularly', 'prefer_not_to_say'])
            ],
            'has_children' => ['nullable', 'boolean'],
            'wants_children' => ['nullable', 'boolean'],
            
            // Idiomas
            'languages' => ['nullable', 'array', 'max:10'],
            'languages.*' => ['string', 'max:50', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/'],
            
            // Intereses y preferencias
            'interests' => ['nullable', 'array', 'max:20'],
            'interests.*' => ['string', 'max:50', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s._-]+$/'],
            'hobbies' => ['nullable', 'array', 'max:15'],
            'hobbies.*' => ['string', 'max:50'],
            'values' => ['nullable', 'array', 'max:10'],
            'values.*' => ['string', 'max:100'],
            
            // Preferencias de entretenimiento
            'music_preferences' => ['nullable', 'array', 'max:15'],
            'music_preferences.*' => ['string', 'max:50'],
            'movie_preferences' => ['nullable', 'array', 'max:15'],
            'movie_preferences.*' => ['string', 'max:50'],
            'book_preferences' => ['nullable', 'array', 'max:15'],
            'book_preferences.*' => ['string', 'max:50'],
            
            // Personalidad
            'personality_type' => ['nullable', 'string', 'max:50'],
            'zodiac_sign' => [
                'nullable', 
                Rule::in([
                    'aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo',
                    'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces'
                ])
            ],
            
            // Redes sociales y conectividad
            'instagram_handle' => ['nullable', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._]+$/'],
            'spotify_connected' => ['nullable', 'string', 'max:100'],
            
            // Configuraciones de privacidad y visibilidad
            'show_age' => ['nullable', 'boolean'],
            'show_distance' => ['nullable', 'boolean'],
            'visibility_radius_km' => ['nullable', 'integer', 'between:1,500'],
            
            // Estado del perfil (solo para administradores)
            'status' => ['nullable', Rule::in(['active', 'paused', 'incomplete', 'under_review'])],
            'pause_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Mensajes personalizados para campos de información personal
            'first_name.regex' => 'El nombre solo puede contener letras y espacios.',
            'last_name.regex' => 'El apellido solo puede contener letras y espacios.',
            'display_name.regex' => 'El nombre de pantalla contiene caracteres no válidos.',
            'bio.min' => 'La biografía debe tener al menos 10 caracteres.',
            'bio.max' => 'La biografía no puede exceder los 500 caracteres.',
            'tagline.max' => 'La frase destacada no puede exceder los 100 caracteres.',
            
            // Mensajes para fecha de nacimiento
            'date_of_birth.before' => 'Debes ser mayor de 18 años para usar la aplicación.',
            'date_of_birth.after' => 'La fecha de nacimiento no puede ser anterior a 100 años.',
            
            // Mensajes para ubicación
            'latitude.between' => 'La latitud debe estar entre -90 y 90 grados.',
            'longitude.between' => 'La longitud debe estar entre -180 y 180 grados.',
            
            // Mensajes para características físicas
            'height_cm.between' => 'La altura debe estar entre 100 y 250 centímetros.',
            
            // Mensajes para ingresos
            'income_range.min' => 'Los ingresos no pueden ser negativos.',
            'income_range.max' => 'Los ingresos exceden el límite permitido.',
            
            // Mensajes para arrays
            'languages.max' => 'Puedes seleccionar máximo 10 idiomas.',
            'languages.*.regex' => 'Los idiomas solo pueden contener letras y espacios.',
            'interests.max' => 'Puedes seleccionar máximo 20 intereses.',
            'interests.*.regex' => 'Los intereses contienen caracteres no válidos.',
            'hobbies.max' => 'Puedes seleccionar máximo 15 hobbies.',
            'values.max' => 'Puedes seleccionar máximo 10 valores.',
            'music_preferences.max' => 'Puedes seleccionar máximo 15 preferencias musicales.',
            'movie_preferences.max' => 'Puedes seleccionar máximo 15 preferencias de películas.',
            'book_preferences.max' => 'Puedes seleccionar máximo 15 preferencias de libros.',
            
            // Mensajes para redes sociales
            'instagram_handle.regex' => 'El handle de Instagram contiene caracteres no válidos.',
            
            // Mensajes para configuración de visibilidad
            'visibility_radius_km.between' => 'El radio de visibilidad debe estar entre 1 y 500 km.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'display_name' => 'nombre de pantalla',
            'date_of_birth' => 'fecha de nacimiento',
            'sexual_orientation' => 'orientación sexual',
            'relationship_status' => 'estado civil',
            'looking_for' => 'lo que buscas',
            'height_cm' => 'altura',
            'body_type' => 'tipo de cuerpo',
            'has_children' => 'tienes hijos',
            'wants_children' => 'quieres hijos',
            'music_preferences' => 'preferencias musicales',
            'movie_preferences' => 'preferencias de películas',
            'book_preferences' => 'preferencias de libros',
            'personality_type' => 'tipo de personalidad',
            'zodiac_sign' => 'signo zodiacal',
            'instagram_handle' => 'usuario de Instagram',
            'visibility_radius_km' => 'radio de visibilidad',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validación adicional: si se proporciona fecha de nacimiento, calcular edad
            if ($this->filled('date_of_birth')) {
                $age = \Carbon\Carbon::parse($this->date_of_birth)->age;
                
                if ($age < 18) {
                    $validator->errors()->add('date_of_birth', 'Debes ser mayor de 18 años para usar la aplicación.');
                }
                
                if ($age > 100) {
                    $validator->errors()->add('date_of_birth', 'La edad no puede exceder los 100 años.');
                }
            }

            // Validación adicional: si se proporcionan coordenadas, deben ser consistentes
            if ($this->filled('latitude') && $this->filled('longitude')) {
                $lat = $this->latitude;
                $lng = $this->longitude;
                
                // Validar que las coordenadas sean realistas (no 0,0 que es en el océano)
                if ($lat == 0 && $lng == 0) {
                    $validator->errors()->add('latitude', 'Por favor proporciona una ubicación válida.');
                }
            }

            // Validación adicional: si se proporciona Instagram handle, debe ser válido
            if ($this->filled('instagram_handle')) {
                $handle = $this->instagram_handle;
                
                // Remover @ si está presente
                $handle = ltrim($handle, '@');
                
                if (strlen($handle) < 1) {
                    $validator->errors()->add('instagram_handle', 'El usuario de Instagram no puede estar vacío.');
                }
                
                // Actualizar el valor sin @
                $this->merge(['instagram_handle' => $handle]);
            }

            // Validación adicional: arrays no pueden estar vacíos si se proporcionan
            $arrayFields = ['languages', 'interests', 'hobbies', 'values', 'music_preferences', 'movie_preferences', 'book_preferences'];
            
            foreach ($arrayFields as $field) {
                if ($this->filled($field) && is_array($this->$field) && empty($this->$field)) {
                    $validator->errors()->add($field, "El campo {$field} no puede estar vacío.");
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Limpiar y formatear datos antes de la validación
        $data = $this->all();

        // Limpiar espacios en blanco de strings
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
                
                // Convertir strings vacíos a null
                if ($data[$key] === '') {
                    $data[$key] = null;
                }
            }
        }

        // Formatear Instagram handle
        if (isset($data['instagram_handle'])) {
            $data['instagram_handle'] = ltrim($data['instagram_handle'], '@');
        }

        $this->merge($data);
    }

    /**
     * Get the validated data from the request.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Limpiar datos adicionales después de la validación
        if (is_array($validated)) {
            // Remover campos que no deben actualizarse directamente
            unset($validated['status'], $validated['pause_reason']);
            
            // Agregar timestamp de última actualización
            $validated['last_updated_at'] = now();
        }
        
        return $validated;
    }
}
