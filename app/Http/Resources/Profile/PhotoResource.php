<?php

declare(strict_types=1);

namespace App\Http\Resources\Profile;

use App\Models\User\UserImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * PhotoResource - Resource para serialización de datos de fotos de perfil
 * 
 * Este resource maneja la transformación y serialización de datos del modelo UserImage
 * para respuestas de API, incluyendo filtrado de información sensible, formateo
 * de datos y agregación de información relacionada.
 * 
 * Funcionalidades:
 * - Transformación de datos de fotos para API
 * - Filtrado de información sensible según contexto
 * - Formateo de URLs y metadatos
 * - Agregación de información de moderación y calidad
 * - Cálculo de métricas en tiempo real
 * - Soporte para diferentes contextos de visualización
 * 
 * @author ForeverUsInLove Team
 * @version 1.0.0
 */
class PhotoResource extends JsonResource
{
    /**
     * Contextos de visualización disponibles
     */
    public const CONTEXT_OWN = 'own';           // Propia foto
    public const CONTEXT_PUBLIC = 'public';     // Foto pública para matching
    public const CONTEXT_ADMIN = 'admin';       // Vista administrativa
    public const CONTEXT_MODERATION = 'moderation'; // Vista de moderación

    /**
     * Campos que nunca se deben exponer públicamente
     */
    private const PRIVATE_FIELDS = [
        'user_id',
        'moderation_results',
        'moderated_by_user_id',
        'upload_ip',
        'original_filename'
    ];

    /**
     * Campos sensibles que solo se muestran al propietario
     */
    private const SENSITIVE_FIELDS = [
        'rejection_reason',
        'moderation_score',
        'views_count',
        'likes_count'
    ];

    /**
     * Campos que requieren verificación para mostrarse
     */
    private const VERIFICATION_REQUIRED_FIELDS = [
        'metadata',
        'exif_data'
    ];

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $context = $this->determineContext($request);
        
        return [
            // Información básica de la foto
            'id' => $this->id,
            'status' => $this->status,
            'is_primary' => $this->is_primary,
            'order' => $this->order,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // URLs y rutas de archivos
            'urls' => $this->getUrls($context),
            
            // Información técnica de la imagen
            'technical_info' => $this->getTechnicalInfo($context),
            
            // Metadatos y análisis
            'metadata' => $this->getMetadata($context),
            
            // Información de moderación
            'moderation' => $this->getModerationInfo($context),
            
            // Métricas de engagement
            'metrics' => $this->getMetrics($context),
            
            // Información de calidad
            'quality' => $this->getQualityInfo($context),
            
            // Información relacionada
            'related_data' => $this->getRelatedData($context),
            
            // Recomendaciones y sugerencias
            'recommendations' => $this->getRecommendations($context),
        ];
    }

    /**
     * Obtiene URLs de la foto según el contexto
     */
    private function getUrls(string $context): array
    {
        $data = [
            'original' => $this->getFullUrl(),
            'thumbnail' => $this->getThumbnailUrl(),
        ];

        // En contexto público, solo mostrar URLs aprobadas
        if ($context === self::CONTEXT_PUBLIC && !$this->is_approved) {
            return [
                'original' => null,
                'thumbnail' => null,
                'status_message' => 'Foto pendiente de aprobación'
            ];
        }

        // En contexto propio o admin, mostrar información adicional
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN, self::CONTEXT_MODERATION])) {
            $data['path'] = $this->path;
            $data['thumbnail_path'] = $this->thumbnail_path;
            $data['filename'] = $this->filename;
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información técnica de la imagen
     */
    private function getTechnicalInfo(string $context): array
    {
        $data = [
            'width' => $this->width,
            'height' => $this->height,
            'aspect_ratio' => $this->aspect_ratio,
            'orientation' => $this->getOrientation(),
            'size_formatted' => $this->size_formatted,
            'mime_type' => $this->mime_type,
        ];

        // En contexto público, ocultar información técnica sensible
        if ($context === self::CONTEXT_PUBLIC) {
            unset($data['mime_type']);
            $data['size_formatted'] = null;
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene metadatos de la foto
     */
    private function getMetadata(string $context): array
    {
        $data = [];

        // Solo mostrar metadatos en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN, self::CONTEXT_MODERATION])) {
            $data = [
                'metadata' => $this->metadata,
                'exif_data' => $this->getExifData(),
                'ai_analysis' => $this->getAiAnalysis(),
            ];
        }

        // En contexto público, solo mostrar información básica
        if ($context === self::CONTEXT_PUBLIC) {
            $data = [
                'tags' => $this->getPublicTags(),
                'color_analysis' => $this->getPublicColorAnalysis(),
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de moderación
     */
    private function getModerationInfo(string $context): array
    {
        $data = [];

        // Solo mostrar información de moderación en contextos apropiados
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN, self::CONTEXT_MODERATION])) {
            $data = [
                'status' => $this->status,
                'is_approved' => $this->is_approved,
                'is_pending' => $this->is_pending,
                'is_rejected' => $this->is_rejected,
                'rejection_reason' => $this->rejection_reason,
                'moderated_at' => $this->formatDateTime($this->moderated_at),
                'moderation_score' => $this->moderation_score,
            ];

            // Solo admin y moderación ven resultados completos
            if (in_array($context, [self::CONTEXT_ADMIN, self::CONTEXT_MODERATION])) {
                $data['moderation_results'] = $this->moderation_results;
                $data['moderated_by_user_id'] = $this->moderated_by_user_id;
            }
        }

        // En contexto público, solo mostrar estado básico
        if ($context === self::CONTEXT_PUBLIC) {
            $data = [
                'is_approved' => $this->is_approved,
                'status' => $this->is_approved ? 'approved' : 'pending'
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene métricas de engagement
     */
    private function getMetrics(string $context): array
    {
        $data = [];

        // Solo mostrar métricas en contexto propio o admin
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data = [
                'views_count' => $this->views_count,
                'likes_count' => $this->likes_count,
                'engagement_rate' => $this->calculateEngagementRate(),
                'performance_score' => $this->calculatePerformanceScore(),
            ];
        }

        // En contexto público, solo mostrar métricas agregadas
        if ($context === self::CONTEXT_PUBLIC && $this->is_approved) {
            $data = [
                'popularity_score' => $this->getPublicPopularityScore(),
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene información de calidad
     */
    private function getQualityInfo(string $context): array
    {
        $data = [];

        // Solo mostrar información de calidad en contextos apropiados
        if (in_array($context, [self::CONTEXT_OWN, self::CONTEXT_ADMIN])) {
            $data = [
                'quality_score' => $this->getQualityScore(),
                'quality_indicators' => $this->getQualityIndicators(),
                'recommendations' => $this->getQualityRecommendations(),
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene datos relacionados
     */
    private function getRelatedData(string $context): array
    {
        $data = [];

        // Información del usuario propietario
        if ($this->relationLoaded('user')) {
            $data['user'] = [
                'id' => $this->user->id,
                'username' => $context === self::CONTEXT_PUBLIC ? null : $this->user->username,
                'is_verified' => $this->user->is_verified ?? false,
            ];
        }

        // Información del perfil
        if ($this->relationLoaded('user.profile')) {
            $data['profile'] = [
                'id' => $this->user->profile->id,
                'display_name' => $context === self::CONTEXT_PUBLIC ? null : $this->user->profile->display_name,
                'is_verified' => $this->user->profile->is_verified,
            ];
        }

        return $this->filterNullValues($data);
    }

    /**
     * Obtiene recomendaciones para la foto
     */
    private function getRecommendations(string $context): array
    {
        // Solo mostrar recomendaciones en contexto propio
        if ($context !== self::CONTEXT_OWN) {
            return [];
        }

        $recommendations = [];

        // Recomendaciones basadas en calidad
        if ($this->getQualityScore() < 7.0) {
            $recommendations[] = [
                'type' => 'quality',
                'priority' => 'medium',
                'title' => 'Mejora la calidad de la foto',
                'description' => 'Una foto de mayor calidad puede aumentar tu visibilidad',
                'action' => 'improve_photo_quality'
            ];
        }

        // Recomendaciones basadas en moderación
        if ($this->is_rejected) {
            $recommendations[] = [
                'type' => 'moderation',
                'priority' => 'high',
                'title' => 'Foto rechazada',
                'description' => $this->rejection_reason ?: 'La foto no cumple con nuestras políticas',
                'action' => 'upload_replacement_photo'
            ];
        }

        // Recomendaciones basadas en engagement
        if ($this->calculateEngagementRate() < 5.0) {
            $recommendations[] = [
                'type' => 'engagement',
                'priority' => 'low',
                'title' => 'Bajo engagement',
                'description' => 'Considera reemplazar esta foto por una más atractiva',
                'action' => 'consider_photo_replacement'
            ];
        }

        // Recomendaciones de orden
        if (!$this->is_primary && $this->order > 3) {
            $recommendations[] = [
                'type' => 'ordering',
                'priority' => 'low',
                'title' => 'Orden de fotos',
                'description' => 'Las primeras fotos son más importantes para el matching',
                'action' => 'reorder_photos'
            ];
        }

        return $recommendations;
    }

    /**
     * Determina el contexto de visualización basado en la request
     */
    private function determineContext(Request $request): string
    {
        $user = $request->user();
        
        // Si no hay usuario autenticado, es contexto público
        if (!$user) {
            return self::CONTEXT_PUBLIC;
        }

        // Si la foto pertenece al usuario autenticado, es contexto propio
        if ($this->user_id === $user->id) {
            return self::CONTEXT_OWN;
        }

        // Si es admin, contexto administrativo
        if ($user->is_admin ?? false) {
            // Si está en ruta de moderación, contexto específico
            if (str_contains($request->path(), 'moderation')) {
                return self::CONTEXT_MODERATION;
            }
            return self::CONTEXT_ADMIN;
        }

        // Por defecto, contexto público
        return self::CONTEXT_PUBLIC;
    }

    /**
     * Formatea una fecha y hora para la respuesta
     */
    private function formatDateTime(?Carbon $dateTime): ?string
    {
        return $dateTime?->toISOString();
    }

    /**
     * Obtiene orientación de la imagen
     */
    private function getOrientation(): string
    {
        if ($this->is_landscape) return 'landscape';
        if ($this->is_portrait) return 'portrait';
        return 'square';
    }

    /**
     * Obtiene datos EXIF filtrados
     */
    private function getExifData(): ?array
    {
        if (!$this->metadata || !isset($this->metadata['exif'])) {
            return null;
        }

        $exif = $this->metadata['exif'];
        
        // Filtrar datos sensibles
        return [
            'camera_make' => $exif['camera_make'] ?? null,
            'camera_model' => $exif['camera_model'] ?? null,
            'date_taken' => $exif['date_taken'] ?? null,
            'gps_removed' => $exif['gps_removed'] ?? true,
        ];
    }

    /**
     * Obtiene análisis de IA
     */
    private function getAiAnalysis(): ?array
    {
        if (!$this->metadata || !isset($this->metadata['ai_analysis'])) {
            return null;
        }

        return $this->metadata['ai_analysis'];
    }

    /**
     * Obtiene tags públicos
     */
    private function getPublicTags(): array
    {
        if (!$this->metadata || !isset($this->metadata['ai_analysis']['tags'])) {
            return [];
        }

        // Filtrar tags sensibles
        $allTags = $this->metadata['ai_analysis']['tags'];
        $sensitiveTags = ['face', 'person', 'portrait', 'selfie'];
        
        return array_filter($allTags, function($tag) use ($sensitiveTags) {
            return !in_array(strtolower($tag), $sensitiveTags);
        });
    }

    /**
     * Obtiene análisis de colores público
     */
    private function getPublicColorAnalysis(): ?array
    {
        if (!$this->metadata || !isset($this->metadata['color_analysis'])) {
            return null;
        }

        return [
            'dominant_colors' => $this->metadata['color_analysis']['dominant_colors'] ?? null,
            'color_harmony' => $this->metadata['color_analysis']['color_harmony'] ?? null,
        ];
    }

    /**
     * Calcula tasa de engagement
     */
    private function calculateEngagementRate(): float
    {
        if ($this->views_count === 0) {
            return 0.0;
        }

        return round(($this->likes_count / $this->views_count) * 100, 2);
    }

    /**
     * Calcula puntaje de rendimiento
     */
    private function calculatePerformanceScore(): float
    {
        $engagementRate = $this->calculateEngagementRate();
        $qualityScore = $this->getQualityScore();
        
        // Peso: 60% engagement, 40% calidad
        return round(($engagementRate * 0.6) + ($qualityScore * 0.4), 2);
    }

    /**
     * Obtiene puntaje de popularidad público
     */
    private function getPublicPopularityScore(): int
    {
        // Escala de 1-5 basada en engagement
        $engagementRate = $this->calculateEngagementRate();
        
        if ($engagementRate >= 20) return 5;
        if ($engagementRate >= 15) return 4;
        if ($engagementRate >= 10) return 3;
        if ($engagementRate >= 5) return 2;
        return 1;
    }

    /**
     * Obtiene puntaje de calidad
     */
    private function getQualityScore(): float
    {
        if ($this->moderation_score !== null) {
            return round($this->moderation_score * 10, 1); // Convertir a escala 0-10
        }

        // Fallback basado en dimensiones
        if (!$this->width || !$this->height) {
            return 0.0;
        }

        $pixels = $this->width * $this->height;
        if ($pixels >= 2000000) return 9.0; // 2MP+
        if ($pixels >= 1000000) return 7.0; // 1MP+
        if ($pixels >= 500000) return 5.0;  // 0.5MP+
        return 3.0;
    }

    /**
     * Obtiene indicadores de calidad
     */
    private function getQualityIndicators(): array
    {
        return [
            'resolution_score' => $this->getResolutionScore(),
            'aspect_ratio_score' => $this->getAspectRatioScore(),
            'file_size_score' => $this->getFileSizeScore(),
        ];
    }

    /**
     * Obtiene recomendaciones de calidad
     */
    private function getQualityRecommendations(): array
    {
        $recommendations = [];

        if ($this->width < 800 || $this->height < 800) {
            $recommendations[] = 'Usa una foto de mayor resolución (mínimo 800x800px)';
        }

        if ($this->aspect_ratio < 0.75 || $this->aspect_ratio > 1.33) {
            $recommendations[] = 'Las fotos más cuadradas suelen tener mejor rendimiento';
        }

        if ($this->size_bytes > 5 * 1024 * 1024) { // 5MB
            $recommendations[] = 'Considera comprimir la foto para mejor carga';
        }

        return $recommendations;
    }

    /**
     * Obtiene puntaje de resolución
     */
    private function getResolutionScore(): float
    {
        if (!$this->width || !$this->height) return 0.0;
        
        $pixels = $this->width * $this->height;
        return min(10.0, $pixels / 200000); // 2MP = 10 puntos
    }

    /**
     * Obtiene puntaje de proporción
     */
    private function getAspectRatioScore(): float
    {
        if (!$this->aspect_ratio) return 5.0;
        
        // Las proporciones cercanas a 1:1 tienen mejor puntaje
        $deviation = abs($this->aspect_ratio - 1.0);
        return max(0.0, 10.0 - ($deviation * 10));
    }

    /**
     * Obtiene puntaje de tamaño de archivo
     */
    private function getFileSizeScore(): float
    {
        if (!$this->size_bytes) return 5.0;
        
        $mb = $this->size_bytes / (1024 * 1024);
        if ($mb <= 2) return 10.0;
        if ($mb <= 5) return 7.0;
        if ($mb <= 10) return 5.0;
        return 2.0;
    }

    /**
     * Filtra valores nulos de un array
     */
    private function filterNullValues(array $data): array
    {
        return array_filter($data, function ($value) {
            return $value !== null;
        });
    }

    /**
     * Obtiene metadatos adicionales para la respuesta
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'context' => $this->determineContext($request),
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0',
                'cache_ttl' => $this->getCacheTTL(),
                'photo_limits' => $this->getPhotoLimits(),
            ]
        ];
    }

    /**
     * Obtiene el TTL de cache basado en el contexto
     */
    private function getCacheTTL(): int
    {
        $context = $this->determineContext(request());
        
        return match ($context) {
            self::CONTEXT_OWN => 300,         // 5 minutos para foto propia
            self::CONTEXT_PUBLIC => 3600,     // 1 hora para foto pública
            self::CONTEXT_ADMIN => 60,        // 1 minuto para vista admin
            self::CONTEXT_MODERATION => 30,   // 30 segundos para moderación
            default => 900,                   // 15 minutos por defecto
        };
    }

    /**
     * Obtiene límites de fotos para el usuario
     */
    private function getPhotoLimits(): array
    {
        if (!$this->relationLoaded('user')) {
            return [];
        }

        $user = $this->user;
        $totalPhotos = $user->images()->count();
        
        return [
            'current_count' => $totalPhotos,
            'max_allowed' => 9,
            'remaining' => max(0, 9 - $totalPhotos),
            'can_upload_more' => $totalPhotos < 9,
        ];
    }
}
