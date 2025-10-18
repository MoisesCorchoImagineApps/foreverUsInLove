<?php

declare(strict_types=1);

namespace App\Domain\Profile\Entities;

use App\Domain\Profile\ValueObjects\ProfileId;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * PersonalityTestResult Entity
 * 
 * Representa los resultados de un test de personalidad en el sistema ForeverUsInLove
 * siguiendo los principios de Domain-Driven Design. Esta entidad encapsula toda la
 * lógica de negocio relacionada con los resultados de tests de personalidad.
 * 
 * Características principales:
 * - Entidad de dominio inmutable con métodos de negocio específicos
 * - Compatible con PersonalityTestRepositoryInterface para persistencia
 * - Integración completa con ValueObjects del dominio Profile
 * - Soporte para diferentes tipos de tests (Big Five, MBTI, etc.)
 * - Análisis de compatibilidad y insights
 * - Métodos de conversión para compatibilidad con modelos existentes
 * 
 * @package ForeverUsInLove\Domain\Profile\Entities
 * @author ForeverUsInLove Development Team
 * @copyright 2024 ForeverUsInLove
 * @license Proprietary
 * @version 1.0.0
 * @since 2024-01-01
 */
final class PersonalityTestResult
{
    // ================================================================
    // PROPERTIES
    // ================================================================
    
    /**
     * @var int ID único del resultado
     */
    private int $id;
    
    /**
     * @var ProfileId Identificador del perfil al que pertenece
     */
    private ProfileId $profileId;
    
    /**
     * @var UserId Identificador del usuario propietario
     */
    private UserId $userId;
    
    /**
     * @var string Tipo de test realizado
     */
    private string $testType;
    
    /**
     * @var string Versión del test
     */
    private string $testVersion;
    
    /**
     * @var array Scores de dimensiones de personalidad
     */
    private array $dimensionScores;
    
    /**
     * @var array Perfil de personalidad generado
     */
    private array $personalityProfile;
    
    /**
     * @var array Insights y análisis generados
     */
    private array $insights;
    
    /**
     * @var float Score de confiabilidad del resultado (0-1)
     */
    private float $accuracyScore;
    
    /**
     * @var int Porcentaje de completitud del test (0-100)
     */
    private int $completionPercentage;
    
    /**
     * @var int Tiempo total en completar el test (segundos)
     */
    private int $timeSpentSeconds;
    
    /**
     * @var bool Indica si el resultado es público
     */
    private bool $isPublic;
    
    /**
     * @var bool Indica si se usa para matching
     */
    private bool $useForMatching;
    
    /**
     * @var array Notas de compatibilidad
     */
    private array $compatibilityNotes;
    
    /**
     * @var array Recomendaciones generadas
     */
    private array $recommendations;
    
    /**
     * @var CarbonInterface Fecha de creación
     */
    private CarbonInterface $createdAt;
    
    /**
     * @var CarbonInterface Fecha de última actualización
     */
    private CarbonInterface $updatedAt;

    // ================================================================
    // CONSTRUCTOR
    // ================================================================
    
    /**
     * Constructor privado para forzar el uso de factory methods
     */
    private function __construct(
        int $id,
        ProfileId $profileId,
        UserId $userId,
        string $testType,
        string $testVersion,
        array $dimensionScores,
        array $personalityProfile,
        array $insights,
        float $accuracyScore,
        int $completionPercentage,
        int $timeSpentSeconds
    ) {
        $this->id = $id;
        $this->profileId = $profileId;
        $this->userId = $userId;
        $this->testType = $testType;
        $this->testVersion = $testVersion;
        $this->dimensionScores = $dimensionScores;
        $this->personalityProfile = $personalityProfile;
        $this->insights = $insights;
        $this->accuracyScore = $accuracyScore;
        $this->completionPercentage = $completionPercentage;
        $this->timeSpentSeconds = $timeSpentSeconds;
        
        // Valores por defecto
        $this->isPublic = false;
        $this->useForMatching = true;
        $this->compatibilityNotes = [];
        $this->recommendations = [];
        
        // Timestamps
        $now = now();
        $this->createdAt = $now->copy();
        $this->updatedAt = $now->copy();
    }

    // ================================================================
    // FACTORY METHODS
    // ================================================================
    
    /**
     * Crear nuevo resultado desde test completado
     */
    public static function createFromCompletedTest(
        int $id,
        ProfileId $profileId,
        UserId $userId,
        string $testType,
        string $testVersion,
        array $dimensionScores,
        array $personalityProfile,
        array $insights,
        float $accuracyScore,
        int $completionPercentage,
        int $timeSpentSeconds
    ): self {
        return new self(
            $id,
            $profileId,
            $userId,
            $testType,
            $testVersion,
            $dimensionScores,
            $personalityProfile,
            $insights,
            $accuracyScore,
            $completionPercentage,
            $timeSpentSeconds
        );
    }
    
    /**
     * Crear resultado desde datos de repositorio
     */
    public static function fromRepositoryData(array $data): self
    {
        $profileId = ProfileId::fromInt($data['profile_id']);
        $userId = UserId::fromInt($data['user_id']);
        
        $result = new self(
            $data['id'],
            $profileId,
            $userId,
            $data['test_type'],
            $data['test_version'],
            $data['dimension_scores'] ?? [],
            $data['personality_profile'] ?? [],
            $data['insights'] ?? [],
            $data['accuracy_score'] ?? 0.0,
            $data['completion_percentage'] ?? 0,
            $data['time_spent_seconds'] ?? 0
        );
        
        // Restaurar propiedades adicionales
        $result->isPublic = $data['is_public'] ?? false;
        $result->useForMatching = $data['use_for_matching'] ?? true;
        $result->compatibilityNotes = $data['compatibility_notes'] ?? [];
        $result->recommendations = $data['recommendations'] ?? [];
        $result->createdAt = $data['created_at'] ?? now();
        $result->updatedAt = $data['updated_at'] ?? now();
        
        return $result;
    }

    // ================================================================
    // GETTERS
    // ================================================================
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getProfileId(): ProfileId
    {
        return $this->profileId;
    }
    
    public function getUserId(): UserId
    {
        return $this->userId;
    }
    
    public function getTestType(): string
    {
        return $this->testType;
    }
    
    public function getTestVersion(): string
    {
        return $this->testVersion;
    }
    
    public function getDimensionScores(): array
    {
        return $this->dimensionScores;
    }
    
    public function getPersonalityProfile(): array
    {
        return $this->personalityProfile;
    }
    
    public function getInsights(): array
    {
        return $this->insights;
    }
    
    public function getAccuracyScore(): float
    {
        return $this->accuracyScore;
    }
    
    public function getCompletionPercentage(): int
    {
        return $this->completionPercentage;
    }
    
    public function getTimeSpentSeconds(): int
    {
        return $this->timeSpentSeconds;
    }
    
    public function isPublic(): bool
    {
        return $this->isPublic;
    }
    
    public function useForMatching(): bool
    {
        return $this->useForMatching;
    }
    
    public function getCompatibilityNotes(): array
    {
        return $this->compatibilityNotes;
    }
    
    public function getRecommendations(): array
    {
        return $this->recommendations;
    }
    
    public function getCreatedAt(): CarbonInterface
    {
        return $this->createdAt;
    }
    
    public function getUpdatedAt(): CarbonInterface
    {
        return $this->updatedAt;
    }

    // ================================================================
    // BUSINESS LOGIC METHODS
    // ================================================================
    
    /**
     * Hacer resultado público
     */
    public function makePublic(): void
    {
        $this->isPublic = true;
        $this->updatedAt = now();
    }
    
    /**
     * Hacer resultado privado
     */
    public function makePrivate(): void
    {
        $this->isPublic = false;
        $this->updatedAt = now();
    }
    
    /**
     * Habilitar para matching
     */
    public function enableForMatching(): void
    {
        $this->useForMatching = true;
        $this->updatedAt = now();
    }
    
    /**
     * Deshabilitar para matching
     */
    public function disableForMatching(): void
    {
        $this->useForMatching = false;
        $this->updatedAt = now();
    }
    
    /**
     * Agregar notas de compatibilidad
     */
    public function addCompatibilityNote(string $note): void
    {
        $this->compatibilityNotes[] = [
            'note' => $note,
            'added_at' => now()->toISOString()
        ];
        $this->updatedAt = now();
    }
    
    /**
     * Agregar recomendaciones
     */
    public function addRecommendations(array $recommendations): void
    {
        $this->recommendations = array_merge($this->recommendations, $recommendations);
        $this->updatedAt = now();
    }
    
    /**
     * Actualizar score de precisión
     */
    public function updateAccuracyScore(float $score): void
    {
        if ($score < 0 || $score > 1) {
            throw new InvalidArgumentException('El score de precisión debe estar entre 0 y 1');
        }
        
        $this->accuracyScore = $score;
        $this->updatedAt = now();
    }
    
    /**
     * Obtener score de dimensión específica
     */
    public function getDimensionScore(string $dimension): ?float
    {
        return $this->dimensionScores[$dimension] ?? null;
    }
    
    /**
     * Obtener traits principales
     */
    public function getPrimaryTraits(): array
    {
        return $this->personalityProfile['primary_traits'] ?? [];
    }
    
    /**
     * Obtener insights específicos
     */
    public function getInsightByCategory(string $category): ?array
    {
        return $this->insights[$category] ?? null;
    }

    // ================================================================
    // QUERY METHODS
    // ================================================================
    
    /**
     * Verificar si el resultado es de alta calidad
     */
    public function isHighQuality(): bool
    {
        return $this->accuracyScore >= 0.8 && $this->completionPercentage >= 90;
    }
    
    /**
     * Verificar si el resultado es confiable
     */
    public function isReliable(): bool
    {
        return $this->accuracyScore >= 0.7;
    }
    
    /**
     * Verificar si el resultado está completo
     */
    public function isComplete(): bool
    {
        return $this->completionPercentage >= 100;
    }
    
    /**
     * Verificar si se puede usar para matching
     */
    public function canBeUsedForMatching(): bool
    {
        return $this->useForMatching && $this->isReliable() && $this->isComplete();
    }
    
    /**
     * Verificar si el resultado es reciente
     */
    public function isRecent(int $days = 365): bool
    {
        return $this->createdAt->diffInDays(now()) <= $days;
    }
    
    /**
     * Obtener nivel de compatibilidad con otro resultado
     */
    public function getCompatibilityLevel(PersonalityTestResult $otherResult): string
    {
        if ($this->testType !== $otherResult->testType) {
            return 'incompatible';
        }
        
        $compatibility = $this->calculateCompatibilityScore($otherResult);
        
        if ($compatibility >= 0.8) return 'high';
        if ($compatibility >= 0.6) return 'medium';
        if ($compatibility >= 0.4) return 'low';
        return 'very_low';
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
            'id' => $this->id,
            'profile_id' => $this->profileId->toInt(),
            'user_id' => $this->userId->toInt(),
            'test_type' => $this->testType,
            'test_version' => $this->testVersion,
            'dimension_scores' => $this->dimensionScores,
            'personality_profile' => $this->personalityProfile,
            'insights' => $this->insights,
            'accuracy_score' => $this->accuracyScore,
            'completion_percentage' => $this->completionPercentage,
            'time_spent_seconds' => $this->timeSpentSeconds,
            'is_public' => $this->isPublic,
            'use_for_matching' => $this->useForMatching,
            'compatibility_notes' => $this->compatibilityNotes,
            'recommendations' => $this->recommendations,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
    
    /**
     * Convertir a array para API
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'test_type' => $this->testType,
            'test_version' => $this->testVersion,
            'dimension_scores' => $this->dimensionScores,
            'personality_profile' => $this->personalityProfile,
            'insights' => $this->insights,
            'accuracy_score' => $this->accuracyScore,
            'completion_percentage' => $this->completionPercentage,
            'time_spent_seconds' => $this->timeSpentSeconds,
            'time_spent_formatted' => $this->getFormattedTimeSpent(),
            'is_public' => $this->isPublic,
            'use_for_matching' => $this->useForMatching,
            'quality_level' => $this->isHighQuality() ? 'high' : ($this->isReliable() ? 'medium' : 'low'),
            'reliability' => $this->isReliable(),
            'is_complete' => $this->isComplete(),
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }
    
    /**
     * Convertir a formato para sharing
     */
    public function toSharingArray(): array
    {
        return [
            'test_type' => $this->testType,
            'primary_traits' => $this->getPrimaryTraits(),
            'personality_summary' => $this->personalityProfile['personality_summary'] ?? '',
            'strengths' => $this->personalityProfile['strengths'] ?? [],
            'created_at' => $this->createdAt->toISOString(),
        ];
    }

    // ================================================================
    // PRIVATE HELPER METHODS
    // ================================================================
    
    /**
     * Calcular score de compatibilidad con otro resultado
     */
    private function calculateCompatibilityScore(PersonalityTestResult $otherResult): float
    {
        if ($this->testType !== $otherResult->testType) {
            return 0.0;
        }
        
        $totalScore = 0;
        $dimensionCount = 0;
        
        foreach ($this->dimensionScores as $dimension => $score) {
            if (isset($otherResult->dimensionScores[$dimension])) {
                $otherScore = $otherResult->dimensionScores[$dimension];
                $similarity = 1 - abs($score - $otherScore) / 100;
                $totalScore += max(0, $similarity);
                $dimensionCount++;
            }
        }
        
        return $dimensionCount > 0 ? $totalScore / $dimensionCount : 0.0;
    }
    
    /**
     * Obtener tiempo formateado
     */
    private function getFormattedTimeSpent(): string
    {
        $minutes = floor($this->timeSpentSeconds / 60);
        $seconds = $this->timeSpentSeconds % 60;
        
        if ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        }
        
        return "{$seconds}s";
    }

    // ================================================================
    // MAGIC METHODS
    // ================================================================
    
    /**
     * Representación en string
     */
    public function __toString(): string
    {
        return "PersonalityTestResult({$this->id}, {$this->testType}, {$this->accuracyScore})";
    }
    
    /**
     * Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'id' => $this->id,
            'profile_id' => $this->profileId->toInt(),
            'user_id' => $this->userId->toInt(),
            'test_type' => $this->testType,
            'accuracy_score' => $this->accuracyScore,
            'completion_percentage' => $this->completionPercentage,
            'is_public' => $this->isPublic,
            'use_for_matching' => $this->useForMatching,
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
