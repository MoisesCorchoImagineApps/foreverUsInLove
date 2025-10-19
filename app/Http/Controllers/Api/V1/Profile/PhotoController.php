<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPhoto;
use App\Infrastructure\External\CloudinaryService;
use App\Infrastructure\External\TensorFlowService;
use App\Infrastructure\External\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Auth, DB, Log, Cache, Validator};
use Illuminate\Validation\ValidationException;

/**
 * PhotoController - Gestión de fotos de perfil
 * 
 * Servicios externos integrados:
 * - CloudinaryService: Upload, transformación y almacenamiento de imágenes
 * - TensorFlowService: Validación de calidad facial y detección de rostros
 * - FirebaseService: Notificaciones push de eventos de fotos
 * 
 * Características:
 * - Upload con validación de calidad facial obligatoria
 * - Máximo 6 fotos por usuario (regla negocio)
 * - Al menos 1 foto obligatoria para perfil activo
 * - Transformaciones automáticas: thumbnail, medium, large
 * - Detección automática de rostros múltiples o no válidos
 * - Cache de stats y listados
 * - Logging exhaustivo de operaciones
 * - Rate limiting por usuario
 * 
 * @package App\Http\Controllers\Api\V1\Profile
 */
class PhotoController extends Controller
{
    protected CloudinaryService $cloudinary;
    protected TensorFlowService $tensorflow;
    protected FirebaseService $firebase;

    /**
     * Constructor con inyección de dependencias
     */
    public function __construct(
        CloudinaryService $cloudinary,
        TensorFlowService $tensorflow,
        FirebaseService $firebase
    ) {
        $this->cloudinary = $cloudinary;
        $this->tensorflow = $tensorflow;
        $this->firebase = $firebase;

        // Rate limiting: 20 uploads por hora
        $this->middleware('throttle:20,60')->only(['upload']);
        
        // Todas las rutas requieren autenticación
        $this->middleware('auth:sanctum');
    }

    /**
     * Upload de nueva foto de perfil
     * 
     * Proceso:
     * 1. Validación archivo imagen (tipo, tamaño, dimensiones)
     * 2. Validación calidad facial con TensorFlow (obligatorio)
     * 3. Verificación límite máximo 6 fotos
     * 4. Upload a Cloudinary con transformaciones
     * 5. Guardar metadata en BD
     * 6. Si es primera foto, marcar como primaria automáticamente
     * 7. Notificación push de foto nueva
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        try {
            // 1. VALIDACIÓN DE REQUEST
            $validator = Validator::make($request->all(), [
                'photo' => [
                    'required',
                    'image',
                    'mimes:jpeg,png,jpg,webp',
                    'max:10240', // Max 10MB
                    'dimensions:min_width=800,min_height=800,max_width=6000,max_height=6000'
                ],
                'is_primary' => 'sometimes|boolean',
                'caption' => 'sometimes|string|max:200',
                'skip_face_validation' => 'sometimes|boolean' // Solo admin puede saltarse
            ]);

            if ($validator->fails()) {
                Log::warning('Photo upload validation failed', [
                    'user_id' => $user->id,
                    'errors' => $validator->errors()->toArray(),
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de imagen inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 2. VERIFICAR LÍMITE MÁXIMO DE FOTOS (6)
            $currentPhotosCount = UserPhoto::where('user_id', $user->id)->count();
            
            if ($currentPhotosCount >= 6) {
                Log::info('Photo upload rejected - max limit reached', [
                    'user_id' => $user->id,
                    'current_count' => $currentPhotosCount
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Has alcanzado el límite máximo de 6 fotos de perfil',
                    'current_count' => $currentPhotosCount,
                    'max_allowed' => 6
                ], 422);
            }

            $photo = $request->file('photo');
            $skipFaceValidation = $request->boolean('skip_face_validation', false);

            // 3. VALIDACIÓN DE CALIDAD FACIAL CON TENSORFLOW (OBLIGATORIA)
            if (!$skipFaceValidation || !$user->hasRole('admin')) {
                Log::info('Starting TensorFlow face validation', [
                    'user_id' => $user->id,
                    'file_size' => $photo->getSize(),
                    'mime_type' => $photo->getMimeType()
                ]);

                $faceValidation = $this->tensorflow->validateFaceQuality($photo);

                if (!$faceValidation['is_valid']) {
                    Log::warning('Photo rejected - face validation failed', [
                        'user_id' => $user->id,
                        'reason' => $faceValidation['reason'],
                        'quality_score' => $faceValidation['quality_score'] ?? null,
                        'faces_detected' => $faceValidation['faces_detected'] ?? 0
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'La foto no cumple con los requisitos de calidad',
                        'validation_result' => [
                            'reason' => $faceValidation['reason'],
                            'quality_score' => $faceValidation['quality_score'] ?? null,
                            'faces_detected' => $faceValidation['faces_detected'] ?? 0,
                            'requirements' => [
                                'Debe mostrar claramente un rostro',
                                'Solo una persona en la foto',
                                'Buena iluminación y enfoque',
                                'Sin filtros extremos o distorsiones',
                                'Resolución mínima 800x800px'
                            ]
                        ]
                    ], 422);
                }

                Log::info('TensorFlow face validation passed', [
                    'user_id' => $user->id,
                    'quality_score' => $faceValidation['quality_score'],
                    'confidence' => $faceValidation['confidence']
                ]);
            }

            // 4. UPLOAD A CLOUDINARY CON TRANSFORMACIONES
            Log::info('Starting Cloudinary upload', [
                'user_id' => $user->id,
                'folder' => "users/{$user->id}/profile_photos"
            ]);

            $uploadResult = $this->cloudinary->uploadImage($photo, [
                'folder' => "users/{$user->id}/profile_photos",
                'public_id' => 'photo_' . now()->timestamp,
                'transformation' => [
                    // Optimización automática
                    'quality' => 'auto:good',
                    'fetch_format' => 'auto',
                ],
                'eager' => [
                    // Thumbnail 200x200
                    [
                        'width' => 200,
                        'height' => 200,
                        'crop' => 'fill',
                        'gravity' => 'face',
                        'quality' => 'auto:good'
                    ],
                    // Medium 600x600
                    [
                        'width' => 600,
                        'height' => 600,
                        'crop' => 'fill',
                        'gravity' => 'face',
                        'quality' => 'auto:good'
                    ],
                    // Large 1200x1200
                    [
                        'width' => 1200,
                        'height' => 1200,
                        'crop' => 'fill',
                        'gravity' => 'face',
                        'quality' => 'auto:best'
                    ]
                ],
                'eager_async' => false, // Generar transformaciones inmediatamente
                'resource_type' => 'image',
                'allowed_formats' => ['jpg', 'png', 'webp']
            ]);

            if (!$uploadResult['success']) {
                Log::error('Cloudinary upload failed', [
                    'user_id' => $user->id,
                    'error' => $uploadResult['error']
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al subir la imagen al servidor',
                    'error' => 'CLOUDINARY_UPLOAD_FAILED'
                ], 500);
            }

            Log::info('Cloudinary upload successful', [
                'user_id' => $user->id,
                'public_id' => $uploadResult['public_id'],
                'url' => $uploadResult['url'],
                'transformations_generated' => count($uploadResult['eager'] ?? [])
            ]);

            // 5. GUARDAR METADATA EN BD
            DB::beginTransaction();
            try {
                // Determinar si es foto primaria
                $isPrimary = $request->boolean('is_primary', false);
                
                // Si es la primera foto, forzar como primaria
                if ($currentPhotosCount === 0) {
                    $isPrimary = true;
                }

                // Si se marca como primaria, desmarcar las demás
                if ($isPrimary) {
                    UserPhoto::where('user_id', $user->id)
                        ->update(['is_primary' => false]);
                }

                // Calcular orden (última posición)
                $maxOrder = UserPhoto::where('user_id', $user->id)
                    ->max('order') ?? 0;

                $userPhoto = UserPhoto::create([
                    'user_id' => $user->id,
                    'cloudinary_public_id' => $uploadResult['public_id'],
                    'url' => $uploadResult['url'],
                    'secure_url' => $uploadResult['secure_url'],
                    'thumbnail_url' => $uploadResult['eager'][0]['url'] ?? null,
                    'medium_url' => $uploadResult['eager'][1]['url'] ?? null,
                    'large_url' => $uploadResult['eager'][2]['url'] ?? null,
                    'width' => $uploadResult['width'],
                    'height' => $uploadResult['height'],
                    'format' => $uploadResult['format'],
                    'size_bytes' => $uploadResult['bytes'],
                    'is_primary' => $isPrimary,
                    'order' => $maxOrder + 1,
                    'caption' => $request->input('caption'),
                    'face_validation_passed' => true,
                    'face_quality_score' => $faceValidation['quality_score'] ?? null,
                    'uploaded_at' => now()
                ]);

                // Actualizar profile_complete en User si es primera foto
                if ($currentPhotosCount === 0) {
                    $user->update(['profile_complete' => true]);
                }

                DB::commit();

                Log::info('Photo saved to database', [
                    'user_id' => $user->id,
                    'photo_id' => $userPhoto->id,
                    'is_primary' => $isPrimary,
                    'order' => $userPhoto->order
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                
                // Eliminar imagen de Cloudinary si falla BD
                $this->cloudinary->deleteImage($uploadResult['public_id']);
                
                Log::error('Database save failed - photo deleted from Cloudinary', [
                    'user_id' => $user->id,
                    'public_id' => $uploadResult['public_id'],
                    'error' => $e->getMessage()
                ]);

                throw $e;
            }

            // 6. INVALIDAR CACHE
            $this->clearPhotoCache($user->id);

            // 7. ENVIAR NOTIFICACIÓN PUSH
            try {
                $this->firebase->sendToUser($user->id, [
                    'title' => '📸 Nueva foto agregada',
                    'body' => 'Tu foto de perfil se ha subido exitosamente',
                    'data' => [
                        'type' => 'photo_uploaded',
                        'photo_id' => $userPhoto->id,
                        'is_primary' => $isPrimary
                    ]
                ]);
            } catch (\Exception $e) {
                // No fallar si falla notificación
                Log::warning('Push notification failed for photo upload', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Foto subida exitosamente',
                'data' => [
                    'photo' => [
                        'id' => $userPhoto->id,
                        'url' => $userPhoto->url,
                        'thumbnail_url' => $userPhoto->thumbnail_url,
                        'medium_url' => $userPhoto->medium_url,
                        'large_url' => $userPhoto->large_url,
                        'is_primary' => $userPhoto->is_primary,
                        'order' => $userPhoto->order,
                        'caption' => $userPhoto->caption,
                        'uploaded_at' => $userPhoto->uploaded_at->toIso8601String()
                    ],
                    'stats' => [
                        'total_photos' => $currentPhotosCount + 1,
                        'remaining_slots' => 6 - ($currentPhotosCount + 1)
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Photo upload failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la imagen',
                'error' => config('app.debug') ? $e->getMessage() : 'INTERNAL_SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * Listar todas las fotos del usuario autenticado
     * 
     * @return JsonResponse
     */
    public function list(): JsonResponse
    {
        $user = Auth::user();

        try {
            // Cache por 5 minutos
            $photos = Cache::remember(
                "user_photos:{$user->id}",
                300,
                fn() => UserPhoto::where('user_id', $user->id)
                    ->orderBy('order', 'asc')
                    ->get()
            );

            Log::info('Photos listed', [
                'user_id' => $user->id,
                'count' => $photos->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'photos' => $photos->map(fn($photo) => [
                        'id' => $photo->id,
                        'url' => $photo->url,
                        'thumbnail_url' => $photo->thumbnail_url,
                        'medium_url' => $photo->medium_url,
                        'large_url' => $photo->large_url,
                        'is_primary' => $photo->is_primary,
                        'order' => $photo->order,
                        'caption' => $photo->caption,
                        'width' => $photo->width,
                        'height' => $photo->height,
                        'size_kb' => round($photo->size_bytes / 1024, 2),
                        'uploaded_at' => $photo->uploaded_at->toIso8601String()
                    ]),
                    'stats' => [
                        'total' => $photos->count(),
                        'remaining_slots' => 6 - $photos->count(),
                        'has_primary' => $photos->where('is_primary', true)->isNotEmpty()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to list photos', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las fotos'
            ], 500);
        }
    }

    /**
     * Eliminar foto de perfil
     * 
     * Proceso:
     * 1. Verificar que la foto pertenece al usuario
     * 2. Si es la única foto, rechazar (mínimo 1 foto obligatoria)
     * 3. Eliminar de Cloudinary
     * 4. Eliminar de BD
     * 5. Si era primaria, asignar otra como primaria
     * 6. Reordenar fotos restantes
     * 
     * @param int $photoId
     * @return JsonResponse
     */
    public function delete(int $photoId): JsonResponse
    {
        $user = Auth::user();

        try {
            $photo = UserPhoto::where('id', $photoId)
                ->where('user_id', $user->id)
                ->first();

            if (!$photo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Foto no encontrada'
                ], 404);
            }

            // Verificar que no sea la única foto
            $totalPhotos = UserPhoto::where('user_id', $user->id)->count();
            
            if ($totalPhotos === 1) {
                Log::warning('Photo deletion rejected - last photo', [
                    'user_id' => $user->id,
                    'photo_id' => $photoId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No puedes eliminar tu única foto. Debes tener al menos 1 foto de perfil.',
                    'min_required' => 1
                ], 422);
            }

            DB::beginTransaction();
            try {
                $wasPrimary = $photo->is_primary;
                $cloudinaryPublicId = $photo->cloudinary_public_id;

                // Eliminar de BD primero
                $photo->delete();

                // Si era primaria, asignar la primera foto restante como primaria
                if ($wasPrimary) {
                    $newPrimary = UserPhoto::where('user_id', $user->id)
                        ->orderBy('order', 'asc')
                        ->first();
                    
                    if ($newPrimary) {
                        $newPrimary->update(['is_primary' => true]);
                        
                        Log::info('New primary photo assigned', [
                            'user_id' => $user->id,
                            'new_primary_id' => $newPrimary->id
                        ]);
                    }
                }

                // Reordenar fotos restantes
                $remainingPhotos = UserPhoto::where('user_id', $user->id)
                    ->orderBy('order', 'asc')
                    ->get();

                foreach ($remainingPhotos as $index => $p) {
                    $p->update(['order' => $index + 1]);
                }

                DB::commit();

                Log::info('Photo deleted from database', [
                    'user_id' => $user->id,
                    'photo_id' => $photoId,
                    'was_primary' => $wasPrimary
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Eliminar de Cloudinary (después del commit)
            try {
                $deleteResult = $this->cloudinary->deleteImage($cloudinaryPublicId);
                
                if ($deleteResult['success']) {
                    Log::info('Photo deleted from Cloudinary', [
                        'user_id' => $user->id,
                        'public_id' => $cloudinaryPublicId
                    ]);
                } else {
                    Log::warning('Failed to delete photo from Cloudinary', [
                        'user_id' => $user->id,
                        'public_id' => $cloudinaryPublicId,
                        'error' => $deleteResult['error']
                    ]);
                }
            } catch (\Exception $e) {
                // No fallar si falla eliminación en Cloudinary
                Log::error('Cloudinary deletion error', [
                    'user_id' => $user->id,
                    'public_id' => $cloudinaryPublicId,
                    'error' => $e->getMessage()
                ]);
            }

            // Invalidar cache
            $this->clearPhotoCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Foto eliminada exitosamente',
                'data' => [
                    'remaining_photos' => $totalPhotos - 1,
                    'remaining_slots' => 6 - ($totalPhotos - 1)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Photo deletion failed', [
                'user_id' => $user->id,
                'photo_id' => $photoId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la foto'
            ], 500);
        }
    }

    /**
     * Establecer foto como primaria
     * 
     * @param int $photoId
     * @return JsonResponse
     */
    public function setPrimary(int $photoId): JsonResponse
    {
        $user = Auth::user();

        try {
            $photo = UserPhoto::where('id', $photoId)
                ->where('user_id', $user->id)
                ->first();

            if (!$photo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Foto no encontrada'
                ], 404);
            }

            if ($photo->is_primary) {
                return response()->json([
                    'success' => true,
                    'message' => 'Esta foto ya es tu foto principal'
                ]);
            }

            DB::beginTransaction();
            try {
                // Desmarcar todas las fotos como primarias
                UserPhoto::where('user_id', $user->id)
                    ->update(['is_primary' => false]);

                // Marcar la seleccionada como primaria
                $photo->update(['is_primary' => true]);

                DB::commit();

                Log::info('Primary photo changed', [
                    'user_id' => $user->id,
                    'photo_id' => $photoId
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Invalidar cache
            $this->clearPhotoCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Foto principal actualizada exitosamente',
                'data' => [
                    'photo' => [
                        'id' => $photo->id,
                        'url' => $photo->url,
                        'thumbnail_url' => $photo->thumbnail_url,
                        'is_primary' => true
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to set primary photo', [
                'user_id' => $user->id,
                'photo_id' => $photoId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar foto principal'
            ], 500);
        }
    }

    /**
     * Reordenar fotos
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reorder(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                'photo_ids' => 'required|array|min:1|max:6',
                'photo_ids.*' => 'required|integer|exists:user_photos,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $photoIds = $request->input('photo_ids');

            // Verificar que todas las fotos pertenecen al usuario
            $userPhotos = UserPhoto::where('user_id', $user->id)
                ->whereIn('id', $photoIds)
                ->pluck('id')
                ->toArray();

            if (count($userPhotos) !== count($photoIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Una o más fotos no te pertenecen'
                ], 403);
            }

            DB::beginTransaction();
            try {
                // Actualizar orden
                foreach ($photoIds as $index => $photoId) {
                    UserPhoto::where('id', $photoId)
                        ->update(['order' => $index + 1]);
                }

                DB::commit();

                Log::info('Photos reordered', [
                    'user_id' => $user->id,
                    'new_order' => $photoIds
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Invalidar cache
            $this->clearPhotoCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Orden de fotos actualizado exitosamente',
                'data' => [
                    'new_order' => $photoIds
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reorder photos', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al reordenar fotos'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de fotos
     * 
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        $user = Auth::user();

        try {
            $photos = UserPhoto::where('user_id', $user->id)->get();

            $stats = [
                'total_photos' => $photos->count(),
                'remaining_slots' => 6 - $photos->count(),
                'has_primary' => $photos->where('is_primary', true)->isNotEmpty(),
                'total_size_mb' => round($photos->sum('size_bytes') / 1024 / 1024, 2),
                'average_quality_score' => round($photos->avg('face_quality_score'), 2),
                'photos_per_slot' => [
                    'slot_1' => $photos->where('order', 1)->count(),
                    'slot_2' => $photos->where('order', 2)->count(),
                    'slot_3' => $photos->where('order', 3)->count(),
                    'slot_4' => $photos->where('order', 4)->count(),
                    'slot_5' => $photos->where('order', 5)->count(),
                    'slot_6' => $photos->where('order', 6)->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get photo stats', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas'
            ], 500);
        }
    }

    /**
     * Limpiar cache de fotos del usuario
     * 
     * @param int $userId
     * @return void
     */
    protected function clearPhotoCache(int $userId): void
    {
        Cache::forget("user_photos:{$userId}");
        Cache::forget("user_profile:{$userId}");
    }
}
