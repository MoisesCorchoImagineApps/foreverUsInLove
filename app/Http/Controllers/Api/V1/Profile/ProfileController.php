<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Models\{User, UserProfile, UserPhoto, UserPreference};
use App\Infrastructure\External\{FirebaseService, SendGridService};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Auth, DB, Log, Cache, Validator};
use Illuminate\Validation\Rule;

/**
 * ProfileController - Gestión completa de perfiles de usuario
 * 
 * Servicios externos integrados:
 * - SendGridService: Notificaciones email de cambios importantes
 * - FirebaseService: Notificaciones push de eventos de perfil
 * 
 * Características:
 * - Gestión completa de datos de perfil
 * - Validación de bio, intereses, ubicación
 * - Pausa temporal de perfil (hide mode)
 * - Estadísticas de perfil
 * - Verificación de completitud de perfil
 * - Cache de perfiles para rendimiento
 * - Logging exhaustivo
 * - Notificaciones de cambios importantes
 * 
 * @package App\Http\Controllers\Api\V1\Profile
 */
class ProfileController extends Controller
{
    protected SendGridService $sendgrid;
    protected FirebaseService $firebase;

    /**
     * Constructor con inyección de dependencias
     */
    public function __construct(
        SendGridService $sendgrid,
        FirebaseService $firebase
    ) {
        $this->sendgrid = $sendgrid;
        $this->firebase = $firebase;

        // Todas las rutas requieren autenticación
        $this->middleware('auth:sanctum');
    }

    /**
     * Obtener perfil completo del usuario autenticado
     * 
     * @return JsonResponse
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();

        try {
            // Cache por 5 minutos
            $profileData = Cache::remember(
                "user_profile:{$user->id}",
                300,
                function () use ($user) {
                    $user->load([
                        'profile',
                        'photos' => fn($q) => $q->orderBy('order', 'asc'),
                        'preferences'
                    ]);

                    return [
                        'user' => [
                            'id' => $user->id,
                            'username' => $user->username,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'email_verified' => $user->email_verified_at !== null,
                            'phone_verified' => $user->phone_verified_at !== null,
                            'face_id_enabled' => $user->face_id_enabled,
                            'status' => $user->status,
                            'profile_complete' => $user->profile_complete,
                            'created_at' => $user->created_at->toIso8601String()
                        ],
                        'profile' => $user->profile ? [
                            'first_name' => $user->profile->first_name,
                            'last_name' => $user->profile->last_name,
                            'date_of_birth' => $user->profile->date_of_birth,
                            'age' => $user->profile->age,
                            'gender' => $user->profile->gender,
                            'bio' => $user->profile->bio,
                            'occupation' => $user->profile->occupation,
                            'education' => $user->profile->education,
                            'height_cm' => $user->profile->height_cm,
                            'location' => [
                                'city' => $user->profile->city,
                                'state' => $user->profile->state,
                                'country' => $user->profile->country,
                                'latitude' => $user->profile->latitude,
                                'longitude' => $user->profile->longitude
                            ],
                            'interests' => $user->profile->interests,
                            'languages' => $user->profile->languages,
                            'relationship_status' => $user->profile->relationship_status,
                            'has_children' => $user->profile->has_children,
                            'wants_children' => $user->profile->wants_children,
                            'smoking' => $user->profile->smoking,
                            'drinking' => $user->profile->drinking,
                            'religion' => $user->profile->religion,
                            'political_views' => $user->profile->political_views,
                            'is_paused' => $user->profile->is_paused,
                            'paused_until' => $user->profile->paused_until,
                            'last_active_at' => $user->profile->last_active_at?->toIso8601String()
                        ] : null,
                        'photos' => $user->photos->map(fn($photo) => [
                            'id' => $photo->id,
                            'url' => $photo->url,
                            'thumbnail_url' => $photo->thumbnail_url,
                            'medium_url' => $photo->medium_url,
                            'is_primary' => $photo->is_primary,
                            'order' => $photo->order,
                            'caption' => $photo->caption
                        ]),
                        'preferences' => $user->preferences ? [
                            'looking_for_gender' => $user->preferences->looking_for_gender,
                            'age_range_min' => $user->preferences->age_range_min,
                            'age_range_max' => $user->preferences->age_range_max,
                            'distance_km' => $user->preferences->distance_km,
                            'show_me' => $user->preferences->show_me,
                            'incognito_mode' => $user->preferences->incognito_mode
                        ] : null,
                        'stats' => [
                            'profile_views' => $user->profile_views_count ?? 0,
                            'likes_given' => $user->likes_given_count ?? 0,
                            'likes_received' => $user->likes_received_count ?? 0,
                            'matches' => $user->matches_count ?? 0,
                            'completeness' => $this->calculateProfileCompleteness($user)
                        ]
                    ];
                }
            );

            Log::info('Profile retrieved', [
                'user_id' => $user->id,
                'from_cache' => true
            ]);

            return response()->json([
                'success' => true,
                'data' => $profileData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve profile', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar el perfil'
            ], 500);
        }
    }

    /**
     * Crear o actualizar perfil
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            // VALIDACIÓN
            $validator = Validator::make($request->all(), [
                // Datos personales
                'first_name' => 'sometimes|string|min:2|max:50|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/',
                'last_name' => 'sometimes|string|min:2|max:50|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/',
                'date_of_birth' => 'sometimes|date|before:18 years ago|after:100 years ago',
                'gender' => ['sometimes', Rule::in(['male', 'female', 'non_binary', 'other', 'prefer_not_to_say'])],
                'bio' => 'sometimes|string|min:20|max:500',
                
                // Información adicional
                'occupation' => 'sometimes|string|max:100',
                'education' => ['sometimes', Rule::in(['high_school', 'some_college', 'bachelors', 'masters', 'phd', 'trade_school', 'prefer_not_to_say'])],
                'height_cm' => 'sometimes|integer|min:120|max:250',
                
                // Ubicación
                'city' => 'sometimes|string|max:100',
                'state' => 'sometimes|string|max:100',
                'country' => 'sometimes|string|max:100',
                'latitude' => 'sometimes|numeric|between:-90,90',
                'longitude' => 'sometimes|numeric|between:-180,180',
                
                // Intereses y personalidad
                'interests' => 'sometimes|array|min:3|max:15',
                'interests.*' => 'string|max:50',
                'languages' => 'sometimes|array|min:1|max:10',
                'languages.*' => 'string|max:50',
                
                // Estilo de vida
                'relationship_status' => ['sometimes', Rule::in(['single', 'divorced', 'widowed', 'separated'])],
                'has_children' => ['sometimes', Rule::in(['yes', 'no', 'prefer_not_to_say'])],
                'wants_children' => ['sometimes', Rule::in(['yes', 'no', 'maybe', 'prefer_not_to_say'])],
                'smoking' => ['sometimes', Rule::in(['never', 'occasionally', 'regularly', 'prefer_not_to_say'])],
                'drinking' => ['sometimes', Rule::in(['never', 'socially', 'regularly', 'prefer_not_to_say'])],
                'religion' => 'sometimes|string|max:100',
                'political_views' => ['sometimes', Rule::in(['liberal', 'moderate', 'conservative', 'other', 'prefer_not_to_say'])]
            ]);

            if ($validator->fails()) {
                Log::warning('Profile update validation failed', [
                    'user_id' => $user->id,
                    'errors' => $validator->errors()->toArray()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            try {
                // Crear o actualizar perfil
                $profileData = $request->only([
                    'first_name', 'last_name', 'date_of_birth', 'gender', 'bio',
                    'occupation', 'education', 'height_cm',
                    'city', 'state', 'country', 'latitude', 'longitude',
                    'interests', 'languages',
                    'relationship_status', 'has_children', 'wants_children',
                    'smoking', 'drinking', 'religion', 'political_views'
                ]);

                // Calcular edad si se proporciona fecha de nacimiento
                if (isset($profileData['date_of_birth'])) {
                    $profileData['age'] = \Carbon\Carbon::parse($profileData['date_of_birth'])->age;
                }

                $profile = UserProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData
                );

                // Verificar completitud del perfil
                $completeness = $this->calculateProfileCompleteness($user->fresh());
                
                if ($completeness >= 80 && !$user->profile_complete) {
                    $user->update(['profile_complete' => true]);
                    
                    Log::info('Profile marked as complete', [
                        'user_id' => $user->id,
                        'completeness' => $completeness
                    ]);
                }

                DB::commit();

                Log::info('Profile updated successfully', [
                    'user_id' => $user->id,
                    'updated_fields' => array_keys($profileData),
                    'completeness' => $completeness
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Invalidar cache
            $this->clearProfileCache($user->id);

            // Enviar notificación push
            try {
                $this->firebase->sendToUser($user->id, [
                    'title' => '✅ Perfil actualizado',
                    'body' => 'Tus cambios se han guardado exitosamente',
                    'data' => [
                        'type' => 'profile_updated',
                        'completeness' => $completeness
                    ]
                ]);
            } catch (\Exception $e) {
                Log::warning('Push notification failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado exitosamente',
                'data' => [
                    'profile' => $profile,
                    'completeness' => $completeness,
                    'profile_complete' => $user->profile_complete
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Profile update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el perfil'
            ], 500);
        }
    }

    /**
     * Pausar perfil temporalmente (hide mode)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function pause(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $validator = Validator::make($request->all(), [
                'duration_days' => 'sometimes|integer|min:1|max:90',
                'reason' => 'sometimes|string|max:200'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $profile = UserProfile::where('user_id', $user->id)->first();

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perfil no encontrado'
                ], 404);
            }

            if ($profile->is_paused) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu perfil ya está pausado',
                    'paused_until' => $profile->paused_until?->toIso8601String()
                ], 422);
            }

            $durationDays = $request->input('duration_days', 7); // Default 7 días
            $pausedUntil = now()->addDays($durationDays);

            DB::beginTransaction();
            try {
                $profile->update([
                    'is_paused' => true,
                    'paused_at' => now(),
                    'paused_until' => $pausedUntil,
                    'pause_reason' => $request->input('reason')
                ]);

                // Actualizar status del usuario
                $user->update(['status' => 'inactive']);

                DB::commit();

                Log::info('Profile paused', [
                    'user_id' => $user->id,
                    'duration_days' => $durationDays,
                    'paused_until' => $pausedUntil->toIso8601String()
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Invalidar cache
            $this->clearProfileCache($user->id);

            // Enviar email de confirmación
            try {
                $this->sendgrid->sendEmail([
                    'to' => $user->email,
                    'subject' => 'Perfil pausado - ForeverUsInLove',
                    'html' => view('emails.profile.paused', [
                        'user' => $user,
                        'paused_until' => $pausedUntil,
                        'duration_days' => $durationDays
                    ])->render()
                ]);
            } catch (\Exception $e) {
                Log::warning('Email notification failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Perfil pausado por {$durationDays} días",
                'data' => [
                    'is_paused' => true,
                    'paused_until' => $pausedUntil->toIso8601String(),
                    'duration_days' => $durationDays,
                    'can_resume_at' => $pausedUntil->toIso8601String()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Profile pause failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al pausar el perfil'
            ], 500);
        }
    }

    /**
     * Reactivar perfil pausado
     * 
     * @return JsonResponse
     */
    public function resume(): JsonResponse
    {
        $user = Auth::user();

        try {
            $profile = UserProfile::where('user_id', $user->id)->first();

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perfil no encontrado'
                ], 404);
            }

            if (!$profile->is_paused) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu perfil no está pausado'
                ], 422);
            }

            DB::beginTransaction();
            try {
                $profile->update([
                    'is_paused' => false,
                    'paused_at' => null,
                    'paused_until' => null,
                    'pause_reason' => null,
                    'last_active_at' => now()
                ]);

                // Actualizar status del usuario
                $user->update(['status' => 'active']);

                DB::commit();

                Log::info('Profile resumed', [
                    'user_id' => $user->id
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Invalidar cache
            $this->clearProfileCache($user->id);

            // Enviar notificación push
            try {
                $this->firebase->sendToUser($user->id, [
                    'title' => '🎉 ¡Bienvenido de vuelta!',
                    'body' => 'Tu perfil está activo nuevamente',
                    'data' => [
                        'type' => 'profile_resumed'
                    ]
                ]);
            } catch (\Exception $e) {
                Log::warning('Push notification failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Perfil reactivado exitosamente',
                'data' => [
                    'is_paused' => false,
                    'status' => 'active'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Profile resume failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al reactivar el perfil'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas del perfil
     * 
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        $user = Auth::user();

        try {
            $profile = UserProfile::where('user_id', $user->id)->first();

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perfil no encontrado'
                ], 404);
            }

            $stats = [
                'profile' => [
                    'completeness' => $this->calculateProfileCompleteness($user),
                    'is_complete' => $user->profile_complete,
                    'is_paused' => $profile->is_paused,
                    'paused_until' => $profile->paused_until?->toIso8601String(),
                    'last_active_at' => $profile->last_active_at?->toIso8601String()
                ],
                'verification' => [
                    'email_verified' => $user->email_verified_at !== null,
                    'phone_verified' => $user->phone_verified_at !== null,
                    'identity_verified' => $user->identity_verified ?? false,
                    'face_id_enabled' => $user->face_id_enabled
                ],
                'photos' => [
                    'total' => UserPhoto::where('user_id', $user->id)->count(),
                    'has_primary' => UserPhoto::where('user_id', $user->id)
                        ->where('is_primary', true)
                        ->exists()
                ],
                'activity' => [
                    'profile_views' => $user->profile_views_count ?? 0,
                    'likes_given' => $user->likes_given_count ?? 0,
                    'likes_received' => $user->likes_received_count ?? 0,
                    'matches' => $user->matches_count ?? 0,
                    'messages_sent' => $user->messages_sent_count ?? 0
                ],
                'recommendations' => $this->getProfileRecommendations($user)
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get profile stats', [
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
     * Calcular completitud del perfil (0-100%)
     * 
     * @param User $user
     * @return int
     */
    protected function calculateProfileCompleteness(User $user): int
    {
        $score = 0;
        $profile = $user->profile;

        // Información básica (30 puntos)
        if ($user->email_verified_at) $score += 5;
        if ($user->phone_verified_at) $score += 5;
        if ($profile?->first_name) $score += 5;
        if ($profile?->date_of_birth) $score += 5;
        if ($profile?->gender) $score += 5;
        if ($profile?->bio && strlen($profile->bio) >= 50) $score += 5;

        // Fotos (25 puntos)
        $photosCount = UserPhoto::where('user_id', $user->id)->count();
        if ($photosCount >= 1) $score += 10;
        if ($photosCount >= 3) $score += 10;
        if ($photosCount >= 5) $score += 5;

        // Información adicional (25 puntos)
        if ($profile?->occupation) $score += 5;
        if ($profile?->education) $score += 5;
        if ($profile?->interests && count($profile->interests) >= 5) $score += 5;
        if ($profile?->languages && count($profile->languages) >= 1) $score += 5;
        if ($profile?->city && $profile?->country) $score += 5;

        // Estilo de vida (20 puntos)
        if ($profile?->relationship_status) $score += 5;
        if ($profile?->wants_children) $score += 5;
        if ($profile?->smoking) $score += 5;
        if ($profile?->drinking) $score += 5;

        return min($score, 100);
    }

    /**
     * Obtener recomendaciones para mejorar el perfil
     * 
     * @param User $user
     * @return array
     */
    protected function getProfileRecommendations(User $user): array
    {
        $recommendations = [];
        $profile = $user->profile;

        if (!$user->email_verified_at) {
            $recommendations[] = [
                'type' => 'verification',
                'priority' => 'high',
                'message' => 'Verifica tu email para aumentar tu credibilidad'
            ];
        }

        if (!$user->phone_verified_at) {
            $recommendations[] = [
                'type' => 'verification',
                'priority' => 'high',
                'message' => 'Verifica tu teléfono para mayor seguridad'
            ];
        }

        $photosCount = UserPhoto::where('user_id', $user->id)->count();
        if ($photosCount < 3) {
            $recommendations[] = [
                'type' => 'photos',
                'priority' => 'high',
                'message' => 'Sube al menos 3 fotos para destacar más'
            ];
        }

        if (!$profile?->bio || strlen($profile->bio) < 50) {
            $recommendations[] = [
                'type' => 'profile',
                'priority' => 'medium',
                'message' => 'Escribe una bio más detallada (mínimo 50 caracteres)'
            ];
        }

        if (!$profile?->interests || count($profile->interests) < 5) {
            $recommendations[] = [
                'type' => 'profile',
                'priority' => 'medium',
                'message' => 'Agrega más intereses para mejores matches (mínimo 5)'
            ];
        }

        if (!$profile?->occupation) {
            $recommendations[] = [
                'type' => 'profile',
                'priority' => 'low',
                'message' => 'Completa tu ocupación para atraer perfiles compatibles'
            ];
        }

        return $recommendations;
    }

    /**
     * Limpiar cache del perfil
     * 
     * @param int $userId
     * @return void
     */
    protected function clearProfileCache(int $userId): void
    {
        Cache::forget("user_profile:{$userId}");
        Cache::forget("user_photos:{$userId}");
    }
}
