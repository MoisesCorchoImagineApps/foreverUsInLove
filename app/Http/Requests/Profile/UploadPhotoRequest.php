<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * UploadPhotoRequest - Validación para subida de fotos de perfil
 * 
 * Este FormRequest maneja la validación de archivos de imagen para la subida
 * de fotos de perfil de usuario, incluyendo validaciones de tamaño, formato,
 * dimensiones, límites de usuario y moderación de contenido.
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class UploadPhotoRequest extends FormRequest
{
    /**
     * Configuración de validación de fotos (debe coincidir con PhotoUploadService)
     */
    private const MAX_PHOTOS_PER_USER = 9;
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    private const MIN_RESOLUTION = 400; // 400x400 mínimo
    private const MAX_RESOLUTION = 4000; // 4000x4000 máximo

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        
        // El usuario debe estar autenticado
        if (!$user) {
            return false;
        }

        // Verificar límite de fotos por usuario
        $currentPhotoCount = $user->images()->count();
        
        if ($currentPhotoCount >= self::MAX_PHOTOS_PER_USER) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Archivo de imagen principal
            'photo' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,webp',
                'max:' . (self::MAX_FILE_SIZE / 1024), // Convertir a KB
                'image',
                function ($attribute, $value, $fail) {
                    if (!$value instanceof UploadedFile) {
                        $fail('El archivo no es válido.');
                        return;
                    }

                    // Validar dimensiones de imagen
                    $this->validateImageDimensions($value, $fail);
                    
                    // Validar contenido de imagen
                    $this->validateImageContent($value, $fail);
                }
            ],

            // Metadatos opcionales
            'is_primary' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:5'],
            'tags.*' => ['string', 'max:30', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s._-]+$/'],
            
            // Configuración de privacidad
            'visibility' => ['nullable', 'in:public,friends_only,private'],
            
            // Opciones de procesamiento
            'auto_crop' => ['nullable', 'boolean'],
            'enhance_quality' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Mensajes para archivo de imagen
            'photo.required' => 'Debes seleccionar una imagen para subir.',
            'photo.file' => 'El archivo seleccionado no es válido.',
            'photo.mimes' => 'La imagen debe ser de tipo JPEG, JPG, PNG o WebP.',
            'photo.max' => 'La imagen no puede ser mayor a ' . (self::MAX_FILE_SIZE / 1024 / 1024) . 'MB.',
            'photo.image' => 'El archivo debe ser una imagen válida.',
            
            // Mensajes para metadatos
            'description.max' => 'La descripción no puede exceder los 255 caracteres.',
            'tags.max' => 'Puedes agregar máximo 5 etiquetas.',
            'tags.*.max' => 'Cada etiqueta no puede exceder los 30 caracteres.',
            'tags.*.regex' => 'Las etiquetas contienen caracteres no válidos.',
            
            // Mensajes para configuración
            'visibility.in' => 'La visibilidad debe ser: público, solo amigos o privado.',
            'is_primary.boolean' => 'El campo foto principal debe ser verdadero o falso.',
            'auto_crop.boolean' => 'El campo recorte automático debe ser verdadero o falso.',
            'enhance_quality.boolean' => 'El campo mejorar calidad debe ser verdadero o falso.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'photo' => 'imagen',
            'is_primary' => 'foto principal',
            'description' => 'descripción',
            'tags' => 'etiquetas',
            'visibility' => 'visibilidad',
            'auto_crop' => 'recorte automático',
            'enhance_quality' => 'mejorar calidad',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            
            if (!$user) {
                return;
            }

            // Validar límite de fotos por usuario
            $currentPhotoCount = $user->images()->count();
            
            if ($currentPhotoCount >= self::MAX_PHOTOS_PER_USER) {
                $validator->errors()->add('photo', 
                    'Has alcanzado el límite máximo de ' . self::MAX_PHOTOS_PER_USER . ' fotos por perfil.'
                );
            }

            // Validar archivo específico
            if ($this->hasFile('photo')) {
                $file = $this->file('photo');
                $this->performAdvancedValidation($file, $validator);
            }

            // Validar si es primera foto y se marca como no principal
            if ($currentPhotoCount === 0 && $this->boolean('is_primary') === false) {
                $validator->errors()->add('is_primary', 
                    'La primera foto debe ser marcada como principal.'
                );
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $data = $this->all();

        // Limpiar y formatear datos
        if (isset($data['description'])) {
            $data['description'] = trim($data['description']);
            if ($data['description'] === '') {
                $data['description'] = null;
            }
        }

        // Limpiar etiquetas
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = array_filter(array_map('trim', $data['tags']));
            $data['tags'] = array_unique($data['tags']);
            
            if (empty($data['tags'])) {
                $data['tags'] = null;
            }
        }

        // Convertir valores booleanos
        $booleanFields = ['is_primary', 'auto_crop', 'enhance_quality'];
        foreach ($booleanFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        $this->merge($data);
    }

    /**
     * Get the validated data from the request.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        if (is_array($validated)) {
            // Agregar metadatos adicionales
            $validated['upload_ip'] = $this->ip();
            $validated['user_agent'] = $this->userAgent();
            $validated['upload_timestamp'] = now();
            
            // Agregar información del archivo
            if ($this->hasFile('photo')) {
                $file = $this->file('photo');
                $validated['original_filename'] = $file->getClientOriginalName();
                $validated['file_size'] = $file->getSize();
                $validated['mime_type'] = $file->getMimeType();
            }
        }
        
        return $validated;
    }

    /**
     * Validar dimensiones de la imagen.
     */
    private function validateImageDimensions(UploadedFile $file, callable $fail): void
    {
        try {
            $imageSize = getimagesize($file->getPathname());
            
            if (!$imageSize) {
                $fail('No se pudo leer la información de la imagen.');
                return;
            }

            [$width, $height] = $imageSize;

            // Validar resolución mínima
            if ($width < self::MIN_RESOLUTION || $height < self::MIN_RESOLUTION) {
                $fail('La imagen es demasiado pequeña. Mínimo requerido: ' . 
                      self::MIN_RESOLUTION . 'x' . self::MIN_RESOLUTION . ' píxeles.');
            }

            // Validar resolución máxima
            if ($width > self::MAX_RESOLUTION || $height > self::MAX_RESOLUTION) {
                $fail('La imagen es demasiado grande. Máximo permitido: ' . 
                      self::MAX_RESOLUTION . 'x' . self::MAX_RESOLUTION . ' píxeles.');
            }

            // Validar proporción (no demasiado alargada)
            $aspectRatio = $width / $height;
            if ($aspectRatio > 3 || $aspectRatio < 0.33) {
                $fail('La imagen tiene una proporción muy extrema. Usa imágenes más cuadradas o rectangulares normales.');
            }

        } catch (\Exception $e) {
            $fail('Error al procesar la imagen: ' . $e->getMessage());
        }
    }

    /**
     * Validar contenido de la imagen.
     */
    private function validateImageContent(UploadedFile $file, callable $fail): void
    {
        try {
            // Verificar que el archivo no esté corrupto
            $image = imagecreatefromstring(file_get_contents($file->getPathname()));
            
            if (!$image) {
                $fail('La imagen está corrupta o no es válida.');
                return;
            }

            // Liberar memoria
            imagedestroy($image);

            // Validaciones adicionales de contenido
            $this->validateImageFileIntegrity($file, $fail);

        } catch (\Exception $e) {
            $fail('Error al validar el contenido de la imagen: ' . $e->getMessage());
        }
    }

    /**
     * Validar integridad del archivo de imagen.
     */
    private function validateImageFileIntegrity(UploadedFile $file, callable $fail): void
    {
        // Verificar que el archivo no esté vacío
        if ($file->getSize() === 0) {
            $fail('El archivo está vacío.');
            return;
        }

        // Verificar tamaño mínimo (al menos 1KB)
        if ($file->getSize() < 1024) {
            $fail('El archivo es demasiado pequeño para ser una imagen válida.');
            return;
        }

        // Verificar que el tipo MIME coincida con la extensión
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());
        
        $validMimeExtensions = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp']
        ];

        if (isset($validMimeExtensions[$mimeType])) {
            if (!in_array($extension, $validMimeExtensions[$mimeType])) {
                $fail('El tipo de archivo no coincide con su extensión.');
                return;
            }
        }
    }

    /**
     * Realizar validaciones avanzadas.
     */
    private function performAdvancedValidation(UploadedFile $file, $validator): void
    {
        // Validar nombre de archivo
        $filename = $file->getClientOriginalName();
        
        // Verificar caracteres peligrosos en el nombre
        if (preg_match('/[<>:"\/\\|?*\x00-\x1f]/', $filename)) {
            $validator->errors()->add('photo', 
                'El nombre del archivo contiene caracteres no válidos.'
            );
        }

        // Verificar longitud del nombre de archivo
        if (strlen($filename) > 255) {
            $validator->errors()->add('photo', 
                'El nombre del archivo es demasiado largo.'
            );
        }

        // Validar que no sea un archivo ejecutable disfrazado
        $dangerousExtensions = ['exe', 'bat', 'cmd', 'com', 'scr', 'pif'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($extension, $dangerousExtensions)) {
            $validator->errors()->add('photo', 
                'Tipo de archivo no permitido por seguridad.'
            );
        }

        // Validar que la imagen no sea un GIF animado (por políticas de contenido)
        if ($file->getMimeType() === 'image/gif') {
            $validator->errors()->add('photo', 
                'Los GIFs animados no están permitidos. Usa imágenes estáticas.'
            );
        }
    }

    /**
     * Obtener el archivo de imagen validado.
     */
    public function getValidatedPhoto(): ?UploadedFile
    {
        return $this->hasFile('photo') ? $this->file('photo') : null;
    }

    /**
     * Obtener metadatos de la imagen.
     */
    public function getPhotoMetadata(): array
    {
        $photo = $this->getValidatedPhoto();
        
        if (!$photo) {
            return [];
        }

        try {
            $imageSize = getimagesize($photo->getPathname());
            [$width, $height] = $imageSize;

            return [
                'width' => $width,
                'height' => $height,
                'aspect_ratio' => round($width / $height, 2),
                'file_size' => $photo->getSize(),
                'mime_type' => $photo->getMimeType(),
                'original_filename' => $photo->getClientOriginalName(),
                'extension' => strtolower($photo->getClientOriginalExtension()),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Verificar si el usuario puede subir más fotos.
     */
    public function canUploadMorePhotos(): bool
    {
        $user = $this->user();
        
        if (!$user) {
            return false;
        }

        $currentPhotoCount = $user->images()->count();
        return $currentPhotoCount < self::MAX_PHOTOS_PER_USER;
    }

    /**
     * Obtener información de límites de fotos.
     */
    public function getPhotoLimits(): array
    {
        $user = $this->user();
        $currentPhotoCount = $user ? $user->images()->count() : 0;

        return [
            'current_count' => $currentPhotoCount,
            'max_allowed' => self::MAX_PHOTOS_PER_USER,
            'remaining' => max(0, self::MAX_PHOTOS_PER_USER - $currentPhotoCount),
            'can_upload' => $currentPhotoCount < self::MAX_PHOTOS_PER_USER,
        ];
    }
}
