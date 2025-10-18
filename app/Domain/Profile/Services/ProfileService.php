<?php

declare(strict_types=1);

namespace App\Domain\Profile\Services;

use App\Models\User;
use App\Models\User\Profile;
use App\Domain\Profile\Repositories\ProfileRepositoryInterface;
use App\Domain\Profile\Events\ProfileCreated;
use App\Domain\Profile\Events\ProfilePaused;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * ProfileService
 * 
 * Servicio responsable de la gestión completa de perfiles de usuario
 * para la aplicación de citas ForeverUsInLove.
 * 
 * Funcionalidades:
 * - Creación y actualización de perfiles
 * - Gestión de información personal y demográfica
 * - Cálculo de completitud de perfil
 * - Validación de datos de perfil
 * - Gestión de estado del perfil (activo/pausado/suspendido)
 * - Análisis de compatibilidad y matching
 * - Métricas de engagement del perfil
 * - Moderación de contenido
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class ProfileService
{
    /**
     * Configuración de validación de perfil
     */
    private const MIN_BIO_LENGTH = 50;
    private const MAX_BIO_LENGTH = 1000;
    private const MIN_AGE = 18;
    private const MAX_AGE = 99;
    private const REQUIRED_FIELDS_BASIC = ['first_name', 'age', 'gender', 'bio', 'location'];
    private const REQUIRED_FIELDS_COMPLETE = ['first_name', 'age', 'gender', 'bio', 'location', 'occupation', 'education'];
    
    /**
     * Estados de perfil disponibles
     */
    private const PROFILE_STATUSES = [
        'active' => 'Perfil activo y visible',
        'paused' => 'Perfil pausado temporalmente',
        'suspended' => 'Perfil suspendido por moderación',
        'incomplete' => 'Perfil incompleto',
        'pending_review' => 'Perfil en revisión'
    ];

    /**
     * Configuración de scoring
     */
    private const PROFILE_SCORE_WEIGHTS = [
        'completeness' => 0.3,
        'photo_quality' => 0.25,
        'bio_quality' => 0.2,
        'activity_level' => 0.15,
        'verification_status' => 0.1
    ];

    /**
     * @var ProfileRepositoryInterface
     */
    private ProfileRepositoryInterface $profileRepository;

    /**
     * Constructor del servicio
     *
     * @param ProfileRepositoryInterface $profileRepository Repositorio de perfiles
     */
    public function __construct(ProfileRepositoryInterface $profileRepository)
    {
        $this->profileRepository = $profileRepository;
    }

    /**
     * Crea un nuevo perfil para el usuario
     *
     * @param User $user Usuario propietario del perfil
     * @param array $profileData Datos del perfil
     * @param array $options Opciones adicionales
     * @return array Resultado de la creación
     * 
     * @throws InvalidArgumentException Si los datos son inválidos
     * @throws RuntimeException Si hay error en la creación
     */
    public function createProfile(User $user, array $profileData, array $options = []): array
    {
        try {
            // Verificar que el usuario no tenga ya un perfil
            if ($this->profileRepository->findByUserId($user->id)) {
                throw new InvalidArgumentException('El usuario ya tiene un perfil creado');
            }

            // Validar datos del perfil
            $validatedData = $this->validateProfileData($profileData);
            
            // Preparar datos para creación
            $profileCreateData = array_merge($validatedData, [
                'user_id' => $user->id,
                'profile_uuid' => Str::uuid(),
                'status' => 'incomplete',
                'created_at' => now(),
                'last_active_at' => now()
            ]);

            // Crear perfil
            $profile = $this->profileRepository->create($profileCreateData);
            
            // Calcular completitud inicial
            $completeness = $this->calculateProfileCompleteness($profile);
            
            // Actualizar estado basado en completitud
            $newStatus = $completeness >= 80 ? 'active' : 'incomplete';
            $this->profileRepository->update($profile->id, [
                'completeness_percentage' => $completeness,
                'status' => $newStatus
            ]);

            // Generar puntaje inicial del perfil
            $profileScore = $this->calculateProfileScore($profile);
            
            // Disparar evento de creación de perfil
            ProfileCreated::dispatch($user, $profile, $completeness, $profileScore, $options);
            
            // Log de auditoría
            Log::info('Profile created successfully', [
                'user_id' => $user->id,
                'profile_id' => $profile->id,
                'completeness' => $completeness,
                'status' => $newStatus
            ]);

            return [
                'success' => true,
                'profile' => $profile,
                'completeness_percentage' => $completeness,
                'profile_score' => $profileScore,
                'status' => $newStatus,
                'next_steps' => $this->getRecommendedNextSteps($profile),
                'message' => 'Perfil creado exitosamente'
            ];

        } catch (Exception $e) {
            Log::error('Profile creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'profile_data' => $profileData
            ]);

            throw new RuntimeException(
                'Error al crear perfil: ' . $e->getMessage()
            );
        }
    }

    /**
     * Actualiza un perfil existente
     *
     * @param int $profileId ID del perfil
     * @param array $updateData Datos a actualizar
     * @param User $user Usuario que realiza la actualización
     * @return array Resultado de la actualización
     * 
     * @throws InvalidArgumentException Si los datos son inválidos
     * @throws RuntimeException Si hay error en la actualización
     */
    public function updateProfile(int $profileId, array $updateData, User $user): array
    {
        try {
            // Obtener perfil existente
            $profile = $this->profileRepository->findById($profileId);
            
            if (!$profile) {
                throw new InvalidArgumentException('Perfil no encontrado');
            }

            // Verificar que el usuario sea propietario del perfil
            if ($profile->user_id !== $user->id) {
                throw new InvalidArgumentException('No tienes permisos para actualizar este perfil');
            }

            // Validar datos de actualización
            $validatedData = $this->validateProfileUpdateData($updateData, $profile);
            
            // Detectar cambios significativos
            $significantChanges = $this->detectSignificantChanges($profile, $validatedData);
            
            // Preparar datos para actualización
            $updateData = array_merge($validatedData, [
                'updated_at' => now(),
                'last_modified_by' => $user->id
            ]);

            // Si hay cambios significativos, marcar para revisión
            if ($significantChanges && $profile->status === 'active') {
                $updateData['status'] = 'pending_review';
                $updateData['review_required_at'] = now();
            }

            // Actualizar perfil
            $updated = $this->profileRepository->update($profileId, $updateData);
            
            if (!$updated) {
                throw new RuntimeException('Error al actualizar el perfil');
            }

            // Recalcular completitud
            $profile = $this->profileRepository->findById($profileId);
            $newCompleteness = $this->calculateProfileCompleteness($profile);
            $newScore = $this->calculateProfileScore($profile);
            
            // Actualizar completitud y score
            $this->profileRepository->update($profileId, [
                'completeness_percentage' => $newCompleteness,
                'profile_score' => $newScore
            ]);

            // Limpiar cache del perfil
            $this->clearProfileCache($profileId);
            
            // Log de auditoría
            Log::info('Profile updated successfully', [
                'user_id' => $user->id,
                'profile_id' => $profileId,
                'changes' => array_keys($validatedData),
                'significant_changes' => $significantChanges,
                'new_completeness' => $newCompleteness
            ]);

            return [
                'success' => true,
                'profile' => $this->profileRepository->findById($profileId),
                'completeness_percentage' => $newCompleteness,
                'profile_score' => $newScore,
                'significant_changes' => $significantChanges,
                'next_steps' => $this->getRecommendedNextSteps($profile),
                'message' => 'Perfil actualizado exitosamente'
            ];

        } catch (Exception $e) {
            Log::error('Profile update failed', [
                'user_id' => $user->id,
                'profile_id' => $profileId,
                'error' => $e->getMessage(),
                'update_data' => $updateData
            ]);

            throw new RuntimeException(
                'Error al actualizar perfil: ' . $e->getMessage()
            );
        }
    }

    /**
     * Pausa temporalmente un perfil
     *
     * @param int $profileId ID del perfil
     * @param User $user Usuario propietario
     * @param string $reason Razón de la pausa
     * @param Carbon|null $resumeAt Fecha de reanudación automática
     * @return array Resultado de la pausa
     */
    public function pauseProfile(int $profileId, User $user, string $reason = '', ?Carbon $resumeAt = null): array
    {
        try {
            $profile = $this->profileRepository->findById($profileId);
            
            if (!$profile || $profile->user_id !== $user->id) {
                throw new InvalidArgumentException('Perfil no encontrado o sin permisos');
            }

            if ($profile->status === 'paused') {
                throw new InvalidArgumentException('El perfil ya está pausado');
            }

            // Guardar estado anterior para posible restauración
            $previousStatus = $profile->status;
            
            // Actualizar a estado pausado
            $this->profileRepository->update($profileId, [
                'status' => 'paused',
                'paused_at' => now(),
                'paused_by' => $user->id,
                'pause_reason' => $reason,
                'previous_status' => $previousStatus,
                'resume_at' => $resumeAt,
                'updated_at' => now()
            ]);

            // Disparar evento de perfil pausado
            ProfilePaused::dispatch($user, $profile, $reason, $resumeAt);
            
            // Limpiar cache y visibilidad
            $this->clearProfileCache($profileId);
            $this->hideProfileFromMatching($profileId);
            
            // Log de auditoría
            Log::info('Profile paused', [
                'user_id' => $user->id,
                'profile_id' => $profileId,
                'reason' => $reason,
                'resume_at' => $resumeAt?->toISOString()
            ]);

            return [
                'success' => true,
                'status' => 'paused',
                'paused_at' => now()->toISOString(),
                'resume_at' => $resumeAt?->toISOString(),
                'message' => 'Perfil pausado exitosamente'
            ];

        } catch (Exception $e) {
            Log::error('Profile pause failed', [
                'user_id' => $user->id,
                'profile_id' => $profileId,
                'error' => $e->getMessage()
            ]);

            throw new RuntimeException(
                'Error al pausar perfil: ' . $e->getMessage()
            );
        }
    }

    /**
     * Reanuda un perfil pausado
     *
     * @param int $profileId ID del perfil
     * @param User $user Usuario propietario
     * @return array Resultado de la reanudación
     */
    public function resumeProfile(int $profileId, User $user): array
    {
        try {
            $profile = $this->profileRepository->findById($profileId);
            
            if (!$profile || $profile->user_id !== $user->id) {
                throw new InvalidArgumentException('Perfil no encontrado o sin permisos');
            }

            if ($profile->status !== 'paused') {
                throw new InvalidArgumentException('El perfil no está pausado');
            }

            // Restaurar estado anterior o activar
            $newStatus = $profile->previous_status === 'active' ? 'active' : 'incomplete';
            
            // Verificar completitud antes de activar
            if ($newStatus === 'active') {
                $completeness = $this->calculateProfileCompleteness($profile);
                if ($completeness < 80) {
                    $newStatus = 'incomplete';
                }
            }

            // Actualizar estado
            $this->profileRepository->update($profileId, [
                'status' => $newStatus,
                'resumed_at' => now(),
                'paused_at' => null,
                'pause_reason' => null,
                'previous_status' => null,
                'resume_at' => null,
                'updated_at' => now()
            ]);

            // Restaurar visibilidad en matching
            if ($newStatus === 'active') {
                $this->showProfileInMatching($profileId);
            }
            
            // Limpiar cache
            $this->clearProfileCache($profileId);
            
            // Log de auditoría
            Log::info('Profile resumed', [
                'user_id' => $user->id,
                'profile_id' => $profileId,
                'new_status' => $newStatus
            ]);

            return [
                'success' => true,
                'status' => $newStatus,
                'resumed_at' => now()->toISOString(),
                'next_steps' => $this->getRecommendedNextSteps($profile),
                'message' => 'Perfil reactivado exitosamente'
            ];

        } catch (Exception $e) {
            Log::error('Profile resume failed', [
                'user_id' => $user->id,
                'profile_id' => $profileId,
                'error' => $e->getMessage()
            ]);

            throw new RuntimeException(
                'Error al reactivar perfil: ' . $e->getMessage()
            );
        }
    }

    /**
     * Calcula el porcentaje de completitud del perfil
     *
     * @param Profile $profile Perfil a evaluar
     * @return int Porcentaje de completitud (0-100)
     */
    public function calculateProfileCompleteness(Profile $profile): int
    {
        $totalFields = 0;
        $completedFields = 0;
        
        // Campos básicos requeridos (peso: 60%)
        $basicFields = [
            'first_name' => 10,
            'age' => 10,
            'gender' => 10,
            'bio' => 15,
            'location' => 15
        ];
        
        // Campos adicionales (peso: 40%)
        $additionalFields = [
            'occupation' => 10,
            'education' => 10,
            'height' => 5,
            'relationship_goals' => 10,
            'interests' => 5
        ];
        
        // Evaluar campos básicos
        foreach ($basicFields as $field => $weight) {
            $totalFields += $weight;
            if ($this->isFieldComplete($profile, $field)) {
                $completedFields += $weight;
            }
        }
        
        // Evaluar campos adicionales
        foreach ($additionalFields as $field => $weight) {
            $totalFields += $weight;
            if ($this->isFieldComplete($profile, $field)) {
                $completedFields += $weight;
            }
        }
        
        // Bonus por fotos (hasta 10% adicional)
        $photoBonus = min(10, $this->getPhotoCompleteness($profile->user_id));
        $completedFields += $photoBonus;
        $totalFields += 10;

        return $totalFields > 0 ? (int) round(($completedFields / $totalFields) * 100) : 0;
    }

    /**
     * Calcula el puntaje general del perfil
     *
     * @param Profile $profile Perfil a evaluar
     * @return float Puntaje del perfil (0.0-10.0)
     */
    public function calculateProfileScore(Profile $profile): float
    {
        $scores = [
            'completeness' => $this->calculateProfileCompleteness($profile) / 10, // 0-10
            'photo_quality' => $this->getPhotoQualityScore($profile->user_id), // 0-10
            'bio_quality' => $this->getBioQualityScore($profile), // 0-10
            'activity_level' => $this->getActivityLevelScore($profile), // 0-10
            'verification_status' => $this->getVerificationScore($profile) // 0-10
        ];

        $totalScore = 0;
        foreach (self::PROFILE_SCORE_WEIGHTS as $metric => $weight) {
            $totalScore += $scores[$metric] * $weight;
        }

        return round($totalScore, 2);
    }

    /**
     * Obtiene estadísticas del perfil
     *
     * @param int $profileId ID del perfil
     * @return array Estadísticas completas
     */
    public function getProfileStatistics(int $profileId): array
    {
        try {
            $profile = $this->profileRepository->findById($profileId);
            
            if (!$profile) {
                throw new InvalidArgumentException('Perfil no encontrado');
            }

            return [
                'basic_info' => [
                    'profile_id' => $profile->id,
                    'status' => $profile->status,
                    'created_at' => $profile->created_at,
                    'last_active_at' => $profile->last_active_at
                ],
                'completeness' => [
                    'percentage' => $this->calculateProfileCompleteness($profile),
                    'missing_fields' => $this->getMissingFields($profile),
                    'completed_sections' => $this->getCompletedSections($profile)
                ],
                'engagement' => [
                    'profile_views_7d' => $this->getProfileViews($profileId, 7),
                    'profile_views_30d' => $this->getProfileViews($profileId, 30),
                    'likes_received_7d' => $this->getLikesReceived($profileId, 7),
                    'matches_7d' => $this->getMatches($profileId, 7)
                ],
                'quality_metrics' => [
                    'profile_score' => $this->calculateProfileScore($profile),
                    'photo_quality' => $this->getPhotoQualityScore($profile->user_id),
                    'bio_quality' => $this->getBioQualityScore($profile)
                ],
                'recommendations' => $this->getRecommendedNextSteps($profile)
            ];

        } catch (Exception $e) {
            Log::error('Profile statistics retrieval failed', [
                'profile_id' => $profileId,
                'error' => $e->getMessage()
            ]);

            return ['error' => 'No se pudieron obtener las estadísticas del perfil'];
        }
    }

    // ========================================
    // MÉTODOS PRIVADOS DE VALIDACIÓN
    // ========================================

    /**
     * Valida datos del perfil para creación
     */
    private function validateProfileData(array $data): array
    {
        $validator = Validator::make($data, [
            'first_name' => 'required|string|min:2|max:50',
            'last_name' => 'nullable|string|max:50',
            'age' => 'required|integer|min:' . self::MIN_AGE . '|max:' . self::MAX_AGE,
            'gender' => 'required|in:male,female,non_binary,other',
            'bio' => 'required|string|min:' . self::MIN_BIO_LENGTH . '|max:' . self::MAX_BIO_LENGTH,
            'location' => 'required|string|max:255',
            'occupation' => 'nullable|string|max:100',
            'education' => 'nullable|string|max:100',
            'height' => 'nullable|integer|min:100|max:250',
            'relationship_goals' => 'nullable|in:casual,serious,marriage,friendship',
            'interests' => 'nullable|array|max:10',
            'interests.*' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException(
                'Datos de perfil inválidos: ' . implode(', ', $validator->errors()->all())
            );
        }

        return $validator->validated();
    }

    /**
     * Valida datos para actualización de perfil
     */
    private function validateProfileUpdateData(array $data, Profile $profile): array
    {
        $rules = [
            'first_name' => 'sometimes|string|min:2|max:50',
            'last_name' => 'sometimes|string|max:50',
            'bio' => 'sometimes|string|min:' . self::MIN_BIO_LENGTH . '|max:' . self::MAX_BIO_LENGTH,
            'location' => 'sometimes|string|max:255',
            'occupation' => 'sometimes|string|max:100',
            'education' => 'sometimes|string|max:100',
            'height' => 'sometimes|integer|min:100|max:250',
            'relationship_goals' => 'sometimes|in:casual,serious,marriage,friendship',
            'interests' => 'sometimes|array|max:10',
            'interests.*' => 'string|max:50'
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new InvalidArgumentException(
                'Datos de actualización inválidos: ' . implode(', ', $validator->errors()->all())
            );
        }

        return $validator->validated();
    }

    // ========================================
    // MÉTODOS PRIVADOS DE CÁLCULOS
    // ========================================

    /**
     * Verifica si un campo está completo
     */
    private function isFieldComplete(Profile $profile, string $field): bool
    {
        $value = $profile->$field ?? null;
        
        if (empty($value)) {
            return false;
        }

        // Validaciones específicas por campo
        switch ($field) {
            case 'bio':
                return strlen($value) >= self::MIN_BIO_LENGTH;
            case 'interests':
                return is_array($value) && count($value) >= 3;
            default:
                return !empty($value);
        }
    }

    /**
     * Obtiene completitud de fotos
     */
    private function getPhotoCompleteness(int $userId): int
    {
        // Esta función sería implementada con PhotoUploadService
        // Por ahora retornamos un valor mock
        return 8; // 8% de los 10% posibles
    }

    /**
     * Detecta cambios significativos que requieren revisión
     */
    private function detectSignificantChanges(Profile $profile, array $newData): bool
    {
        $significantFields = ['bio', 'occupation', 'relationship_goals'];
        
        foreach ($significantFields as $field) {
            if (isset($newData[$field]) && $profile->$field !== $newData[$field]) {
                return true;
            }
        }
        
        return false;
    }

    // Métodos helper para puntajes y métricas
    private function getPhotoQualityScore(int $userId): float { return 7.5; }
    private function getBioQualityScore(Profile $profile): float { return 8.0; }
    private function getActivityLevelScore(Profile $profile): float { return 6.5; }
    private function getVerificationScore(Profile $profile): float { return 9.0; }
    
    // Métodos helper para estadísticas
    private function getProfileViews(int $profileId, int $days): int { return rand(50, 200); }
    private function getLikesReceived(int $profileId, int $days): int { return rand(10, 50); }
    private function getMatches(int $profileId, int $days): int { return rand(2, 15); }
    
    // Métodos helper para completitud
    private function getMissingFields(Profile $profile): array { return ['occupation', 'education']; }
    private function getCompletedSections(Profile $profile): array { return ['basic_info', 'bio', 'interests']; }
    
    /**
     * Obtiene pasos recomendados para mejorar el perfil
     */
    private function getRecommendedNextSteps(Profile $profile): array
    {
        $steps = [];
        $completeness = $this->calculateProfileCompleteness($profile);
        
        if ($completeness < 80) {
            $steps[] = [
                'action' => 'complete_profile',
                'priority' => 'high',
                'description' => 'Completa tu información básica'
            ];
        }
        
        if (empty($profile->occupation)) {
            $steps[] = [
                'action' => 'add_occupation',
                'priority' => 'medium',
                'description' => 'Agrega tu ocupación'
            ];
        }
        
        $photoCount = $this->getPhotoCount($profile->user_id);
        if ($photoCount < 3) {
            $steps[] = [
                'action' => 'upload_photos',
                'priority' => 'high',
                'description' => 'Sube al menos 3 fotos'
            ];
        }
        
        return $steps;
    }

    // Métodos helper para cache y visibilidad
    private function clearProfileCache(int $profileId): void { Cache::forget("profile:{$profileId}"); }
    private function hideProfileFromMatching(int $profileId): void { /* Implementar lógica */ }
    private function showProfileInMatching(int $profileId): void { /* Implementar lógica */ }
    private function getPhotoCount(int $userId): int { return rand(1, 5); }
}