<?php

declare(strict_types=1);

namespace App\Domain\Profile\ValueObjects;

use InvalidArgumentException;

/**
 * PhotoQualityScore Value Object
 * 
 * Representa el score de calidad de una foto en el sistema ForeverUsInLove.
 * Encapsula la lógica de evaluación de calidad de fotos con múltiples criterios
 * y proporciona métodos para análisis y comparación de calidad.
 * 
 * Características principales:
 * - Score principal de calidad (0-10)
 * - Desglose por categorías específicas
 * - Métodos de validación y normalización
 * - Compatibilidad con sistema de moderación existente
 * - Integración con AI/ML para evaluación automática
 * - Soporte para scoring manual y automático
 * 
 * @package ForeverUsInLove\Domain\Profile\ValueObjects
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove
 * @license Proprietary
 * @version 1.0.0
 * @since 2024-01-01
 */
final class PhotoQualityScore
{
    // ================================================================
    // CONSTANTS
    // ================================================================
    
    /**
     * Score mínimo válido
     */
    public const MIN_SCORE = 0.0;
    
    /**
     * Score máximo válido
     */
    public const MAX_SCORE = 10.0;
    
    /**
     * Score considerado de alta calidad
     */
    public const HIGH_QUALITY_THRESHOLD = 7.0;
    
    /**
     * Score considerado de baja calidad
     */
    public const LOW_QUALITY_THRESHOLD = 4.0;
    
    /**
     * Categorías de evaluación disponibles
     */
    public const CATEGORIES = [
        'clarity' => 'Claridad y nitidez',
        'composition' => 'Composición y encuadre',
        'lighting' => 'Iluminación',
        'subject_visibility' => 'Visibilidad del sujeto',
        'background' => 'Calidad del fondo',
        'technical_quality' => 'Calidad técnica',
        'aesthetic_appeal' => 'Atractivo estético',
        'face_detection' => 'Detección de rostros',
        'content_appropriateness' => 'Apropiado del contenido',
        'resolution' => 'Resolución y tamaño'
    ];
    
    // ================================================================
    // PROPERTIES
    // ================================================================
    
    /**
     * @var float Score principal de calidad (0-10)
     */
    private float $overallScore;
    
    /**
     * @var array<string, float> Scores por categoría
     */
    private array $categoryScores;
    
    /**
     * @var string|null Fuente del score (ai, manual, hybrid)
     */
    private ?string $source;
    
    /**
     * @var array<string, mixed> Metadata adicional del análisis
     */
    private array $metadata;
    
    /**
     * @var bool Indica si el score fue calculado automáticamente
     */
    private bool $isAutoCalculated;
    
    /**
     * @var float Confianza del algoritmo en el score (0-1)
     */
    private float $confidence;
    
    /**
     * @var array<string> Tags AI generados
     */
    private array $aiTags;
    
    /**
     * @var array<string> Problemas detectados
     */
    private array $issues;
    
    /**
     * @var array<string> Recomendaciones de mejora
     */
    private array $recommendations;

    // ================================================================
    // CONSTRUCTOR
    // ================================================================
    
    /**
     * Constructor privado para forzar el uso de factory methods
     */
    private function __construct(
        float $overallScore,
        array $categoryScores = [],
        ?string $source = null,
        array $metadata = [],
        bool $isAutoCalculated = true,
        float $confidence = 1.0,
        array $aiTags = [],
        array $issues = [],
        array $recommendations = []
    ) {
        $this->overallScore = $this->validateAndNormalizeScore($overallScore);
        $this->categoryScores = $this->validateCategoryScores($categoryScores);
        $this->source = $source;
        $this->metadata = $metadata;
        $this->isAutoCalculated = $isAutoCalculated;
        $this->confidence = $this->validateConfidence($confidence);
        $this->aiTags = $aiTags;
        $this->issues = $issues;
        $this->recommendations = $recommendations;
    }

    // ================================================================
    // FACTORY METHODS
    // ================================================================
    
    /**
     * Crear score desde análisis AI/ML
     */
    public static function fromAiAnalysis(
        float $overallScore,
        array $categoryScores = [],
        float $confidence = 1.0,
        array $aiTags = [],
        array $metadata = []
    ): self {
        $issues = [];
        $recommendations = [];
        
        // Generar issues y recomendaciones basadas en scores bajos
        foreach ($categoryScores as $category => $score) {
            if ($score < self::LOW_QUALITY_THRESHOLD) {
                $issues[] = self::generateIssueForCategory($category, $score);
                $recommendations[] = self::generateRecommendationForCategory($category);
            }
        }
        
        return new self(
            $overallScore,
            $categoryScores,
            'ai',
            $metadata,
            true,
            $confidence,
            $aiTags,
            $issues,
            $recommendations
        );
    }
    
    /**
     * Crear score desde evaluación manual
     */
    public static function fromManualEvaluation(
        float $overallScore,
        array $categoryScores = [],
        array $metadata = []
    ): self {
        return new self(
            $overallScore,
            $categoryScores,
            'manual',
            $metadata,
            false,
            1.0,
            [],
            [],
            []
        );
    }
    
    /**
     * Crear score híbrido (AI + Manual)
     */
    public static function fromHybridEvaluation(
        float $overallScore,
        array $categoryScores = [],
        float $aiWeight = 0.7,
        float $manualWeight = 0.3,
        array $metadata = []
    ): self {
        return new self(
            $overallScore,
            $categoryScores,
            'hybrid',
            array_merge($metadata, [
                'ai_weight' => $aiWeight,
                'manual_weight' => $manualWeight
            ]),
            true,
            0.9, // Alta confianza en evaluación híbrida
            [],
            [],
            []
        );
    }
    
    /**
     * Crear score desde datos del modelo UserImage
     */
    public static function fromUserImage(\App\Models\User\UserImage $userImage): self
    {
        $moderationScore = $userImage->moderation_score ?? 0.0;
        $overallScore = $moderationScore * 10; // Convertir de 0-1 a 0-10
        
        $categoryScores = [];
        if ($userImage->moderation_results) {
            foreach ($userImage->moderation_results as $category => $score) {
                if (is_numeric($score)) {
                    $categoryScores[$category] = (float) $score;
                }
            }
        }
        
        return new self(
            $overallScore,
            $categoryScores,
            'legacy',
            [
                'user_image_id' => $userImage->id,
                'migrated_from' => 'UserImage'
            ],
            true,
            0.8
        );
    }
    
    /**
     * Crear score desde datos de repositorio
     */
    public static function fromRepositoryData(array $data): self
    {
        return new self(
            $data['overall_score'] ?? 0.0,
            $data['category_scores'] ?? [],
            $data['source'] ?? null,
            $data['metadata'] ?? [],
            $data['is_auto_calculated'] ?? true,
            $data['confidence'] ?? 1.0,
            $data['ai_tags'] ?? [],
            $data['issues'] ?? [],
            $data['recommendations'] ?? []
        );
    }
    
    /**
     * Crear score por defecto
     */
    public static function default(): self
    {
        return new self(
            5.0, // Score neutral
            [],
            'default',
            [],
            true,
            0.5
        );
    }

    // ================================================================
    // GETTERS
    // ================================================================
    
    public function getOverallScore(): float
    {
        return $this->overallScore;
    }
    
    public function getCategoryScores(): array
    {
        return $this->categoryScores;
    }
    
    public function getSource(): ?string
    {
        return $this->source;
    }
    
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    
    public function isAutoCalculated(): bool
    {
        return $this->isAutoCalculated;
    }
    
    public function getConfidence(): float
    {
        return $this->confidence;
    }
    
    public function getAiTags(): array
    {
        return $this->aiTags;
    }
    
    public function getIssues(): array
    {
        return $this->issues;
    }
    
    public function getRecommendations(): array
    {
        return $this->recommendations;
    }

    // ================================================================
    // BUSINESS LOGIC METHODS
    // ================================================================
    
    /**
     * Verificar si la foto tiene alta calidad
     */
    public function isHighQuality(): bool
    {
        return $this->overallScore >= self::HIGH_QUALITY_THRESHOLD;
    }
    
    /**
     * Verificar si la foto tiene baja calidad
     */
    public function isLowQuality(): bool
    {
        return $this->overallScore <= self::LOW_QUALITY_THRESHOLD;
    }
    
    /**
     * Verificar si la foto tiene calidad aceptable
     */
    public function isAcceptableQuality(): bool
    {
        return $this->overallScore >= self::LOW_QUALITY_THRESHOLD;
    }
    
    /**
     * Obtener nivel de calidad como string
     */
    public function getQualityLevel(): string
    {
        if ($this->isHighQuality()) {
            return 'high';
        } elseif ($this->isLowQuality()) {
            return 'low';
        } else {
            return 'medium';
        }
    }
    
    /**
     * Obtener score de una categoría específica
     */
    public function getCategoryScore(string $category): ?float
    {
        return $this->categoryScores[$category] ?? null;
    }
    
    /**
     * Obtener categorías con score bajo
     */
    public function getLowScoreCategories(): array
    {
        $lowCategories = [];
        foreach ($this->categoryScores as $category => $score) {
            if ($score < self::LOW_QUALITY_THRESHOLD) {
                $lowCategories[$category] = $score;
            }
        }
        return $lowCategories;
    }
    
    /**
     * Obtener categorías con score alto
     */
    public function getHighScoreCategories(): array
    {
        $highCategories = [];
        foreach ($this->categoryScores as $category => $score) {
            if ($score >= self::HIGH_QUALITY_THRESHOLD) {
                $highCategories[$category] = $score;
            }
        }
        return $highCategories;
    }
    
    /**
     * Calcular score promedio de categorías
     */
    public function getAverageCategoryScore(): float
    {
        if (empty($this->categoryScores)) {
            return $this->overallScore;
        }
        
        return array_sum($this->categoryScores) / count($this->categoryScores);
    }
    
    /**
     * Verificar si hay issues críticos
     */
    public function hasCriticalIssues(): bool
    {
        return !empty($this->issues) || $this->overallScore < 3.0;
    }
    
    /**
     * Obtener número de issues
     */
    public function getIssueCount(): int
    {
        return count($this->issues);
    }
    
    /**
     * Obtener número de recomendaciones
     */
    public function getRecommendationCount(): int
    {
        return count($this->recommendations);
    }
    
    /**
     * Comparar con otro score
     */
    public function compareTo(PhotoQualityScore $other): int
    {
        if ($this->overallScore === $other->overallScore) {
            return 0;
        }
        
        return $this->overallScore > $other->overallScore ? 1 : -1;
    }
    
    /**
     * Calcular diferencia con otro score
     */
    public function differenceWith(PhotoQualityScore $other): float
    {
        return abs($this->overallScore - $other->overallScore);
    }
    
    /**
     * Verificar si es significativamente mejor que otro score
     */
    public function isSignificantlyBetterThan(PhotoQualityScore $other, float $threshold = 1.0): bool
    {
        return ($this->overallScore - $other->overallScore) >= $threshold;
    }
    
    /**
     * Obtener score normalizado para moderación (0-1)
     */
    public function getModerationScore(): float
    {
        return $this->overallScore / self::MAX_SCORE;
    }
    
    /**
     * Actualizar score de una categoría
     */
    public function updateCategoryScore(string $category, float $score): self
    {
        $newCategoryScores = $this->categoryScores;
        $newCategoryScores[$category] = $this->validateAndNormalizeScore($score);
        
        // Recalcular score general si es auto-calculado
        if ($this->isAutoCalculated) {
            $newOverallScore = $this->calculateOverallScore($newCategoryScores);
        } else {
            $newOverallScore = $this->overallScore;
        }
        
        return new self(
            $newOverallScore,
            $newCategoryScores,
            $this->source,
            $this->metadata,
            $this->isAutoCalculated,
            $this->confidence,
            $this->aiTags,
            $this->issues,
            $this->recommendations
        );
    }
    
    /**
     * Agregar tag AI
     */
    public function addAiTag(string $tag): self
    {
        $newTags = $this->aiTags;
        if (!in_array($tag, $newTags)) {
            $newTags[] = $tag;
        }
        
        return new self(
            $this->overallScore,
            $this->categoryScores,
            $this->source,
            $this->metadata,
            $this->isAutoCalculated,
            $this->confidence,
            $newTags,
            $this->issues,
            $this->recommendations
        );
    }
    
    /**
     * Agregar issue
     */
    public function addIssue(string $issue): self
    {
        $newIssues = $this->issues;
        if (!in_array($issue, $newIssues)) {
            $newIssues[] = $issue;
        }
        
        return new self(
            $this->overallScore,
            $this->categoryScores,
            $this->source,
            $this->metadata,
            $this->isAutoCalculated,
            $this->confidence,
            $this->aiTags,
            $newIssues,
            $this->recommendations
        );
    }
    
    /**
     * Agregar recomendación
     */
    public function addRecommendation(string $recommendation): self
    {
        $newRecommendations = $this->recommendations;
        if (!in_array($recommendation, $newRecommendations)) {
            $newRecommendations[] = $recommendation;
        }
        
        return new self(
            $this->overallScore,
            $this->categoryScores,
            $this->source,
            $this->metadata,
            $this->isAutoCalculated,
            $this->confidence,
            $this->aiTags,
            $this->issues,
            $newRecommendations
        );
    }

    // ================================================================
    // CONVERSION METHODS
    // ================================================================
    
    /**
     * Convertir a array para repositorio
     */
    public function toRepositoryArray(): array
    {
        return [
            'overall_score' => $this->overallScore,
            'category_scores' => $this->categoryScores,
            'source' => $this->source,
            'metadata' => $this->metadata,
            'is_auto_calculated' => $this->isAutoCalculated,
            'confidence' => $this->confidence,
            'ai_tags' => $this->aiTags,
            'issues' => $this->issues,
            'recommendations' => $this->recommendations,
        ];
    }
    
    /**
     * Convertir a array para API
     */
    public function toApiArray(): array
    {
        return [
            'overall_score' => $this->overallScore,
            'quality_level' => $this->getQualityLevel(),
            'category_scores' => $this->categoryScores,
            'average_category_score' => $this->getAverageCategoryScore(),
            'source' => $this->source,
            'confidence' => $this->confidence,
            'is_auto_calculated' => $this->isAutoCalculated,
            'ai_tags' => $this->aiTags,
            'issues' => $this->issues,
            'recommendations' => $this->recommendations,
            'issue_count' => $this->getIssueCount(),
            'recommendation_count' => $this->getRecommendationCount(),
            'has_critical_issues' => $this->hasCriticalIssues(),
            'moderation_score' => $this->getModerationScore(),
        ];
    }
    
    /**
     * Convertir a formato compatible con UserImage
     */
    public function toUserImageArray(): array
    {
        return [
            'moderation_score' => $this->getModerationScore(),
            'moderation_results' => $this->categoryScores,
            'quality_analysis' => [
                'overall_score' => $this->overallScore,
                'source' => $this->source,
                'confidence' => $this->confidence,
                'ai_tags' => $this->aiTags,
                'issues' => $this->issues,
                'recommendations' => $this->recommendations,
            ]
        ];
    }

    // ================================================================
    // PRIVATE HELPER METHODS
    // ================================================================
    
    /**
     * Validar y normalizar score
     */
    private function validateAndNormalizeScore(float $score): float
    {
        if ($score < self::MIN_SCORE || $score > self::MAX_SCORE) {
            throw new InvalidArgumentException(
                sprintf('Score must be between %s and %s, got %s', self::MIN_SCORE, self::MAX_SCORE, $score)
            );
        }
        
        return round($score, 2);
    }
    
    /**
     * Validar scores de categorías
     */
    private function validateCategoryScores(array $categoryScores): array
    {
        $validated = [];
        foreach ($categoryScores as $category => $score) {
            if (!is_string($category)) {
                continue;
            }
            
            $validated[$category] = $this->validateAndNormalizeScore($score);
        }
        
        return $validated;
    }
    
    /**
     * Validar confianza
     */
    private function validateConfidence(float $confidence): float
    {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new InvalidArgumentException(
                sprintf('Confidence must be between 0.0 and 1.0, got %s', $confidence)
            );
        }
        
        return round($confidence, 3);
    }
    
    /**
     * Calcular score general desde categorías
     */
    private function calculateOverallScore(array $categoryScores): float
    {
        if (empty($categoryScores)) {
            return 5.0; // Score neutral por defecto
        }
        
        // Ponderación por categoría (puede ser configurada)
        $weights = [
            'clarity' => 0.20,
            'composition' => 0.15,
            'lighting' => 0.15,
            'subject_visibility' => 0.15,
            'background' => 0.10,
            'technical_quality' => 0.10,
            'aesthetic_appeal' => 0.10,
            'face_detection' => 0.05,
        ];
        
        $weightedSum = 0.0;
        $totalWeight = 0.0;
        
        foreach ($categoryScores as $category => $score) {
            $weight = $weights[$category] ?? 0.05; // Peso por defecto
            $weightedSum += $score * $weight;
            $totalWeight += $weight;
        }
        
        if ($totalWeight > 0) {
            return round($weightedSum / $totalWeight, 2);
        }
        
        return round(array_sum($categoryScores) / count($categoryScores), 2);
    }
    
    /**
     * Generar issue para una categoría
     */
    private static function generateIssueForCategory(string $category, float $score): string
    {
        $categoryNames = self::CATEGORIES;
        $categoryName = $categoryNames[$category] ?? $category;
        
        return sprintf('Baja calidad en %s (%.1f/10)', $categoryName, $score);
    }
    
    /**
     * Generar recomendación para una categoría
     */
    private static function generateRecommendationForCategory(string $category): string
    {
        $recommendations = [
            'clarity' => 'Mejora la nitidez de la imagen usando mejor iluminación o cámara',
            'composition' => 'Ajusta el encuadre siguiendo la regla de los tercios',
            'lighting' => 'Usa mejor iluminación natural o artificial',
            'subject_visibility' => 'Asegúrate de que el sujeto principal sea claramente visible',
            'background' => 'Considera un fondo más simple y menos distractor',
            'technical_quality' => 'Verifica la resolución y formato de la imagen',
            'aesthetic_appeal' => 'Mejora el atractivo visual general de la foto',
            'face_detection' => 'Asegúrate de que el rostro sea claramente visible',
            'content_appropriateness' => 'Verifica que el contenido sea apropiado',
            'resolution' => 'Usa una imagen de mayor resolución'
        ];
        
        return $recommendations[$category] ?? 'Mejora la calidad general de la imagen';
    }

    // ================================================================
    // MAGIC METHODS
    // ================================================================
    
    /**
     * Representación en string
     */
    public function __toString(): string
    {
        return sprintf('PhotoQualityScore(%.2f/10, %s, %.1f%% confidence)', 
            $this->overallScore, 
            $this->getQualityLevel(),
            $this->confidence * 100
        );
    }
    
    /**
     * Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'overall_score' => $this->overallScore,
            'quality_level' => $this->getQualityLevel(),
            'source' => $this->source,
            'confidence' => $this->confidence,
            'is_auto_calculated' => $this->isAutoCalculated,
            'category_count' => count($this->categoryScores),
            'issue_count' => count($this->issues),
            'recommendation_count' => count($this->recommendations),
            'ai_tag_count' => count($this->aiTags),
        ];
    }
}
