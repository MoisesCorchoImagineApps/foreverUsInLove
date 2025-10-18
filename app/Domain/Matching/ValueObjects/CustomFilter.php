<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use App\Domain\Shared\ValueObjects\UserId;
use InvalidArgumentException;
use JsonSerializable;

/**
 * CustomFilter Value Object
 * 
 * Representa un filtro personalizado en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de filtros personalizados en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para manejar
 * filtros completamente personalizados definidos por los usuarios con criterios
 * específicos y ponderaciones personalizadas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta filtros personalizados válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos CustomFilter con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y filtros personalizados
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class CustomFilter implements JsonSerializable
{
    /**
     * ID único del filtro personalizado
     */
    private readonly string $id;

    /**
     * ID del usuario propietario
     */
    private readonly UserId $userId;

    /**
     * Nombre del filtro personalizado
     */
    private readonly string $name;

    /**
     * Criterios personalizados del filtro
     */
    private readonly array $customCriteria;

    /**
     * Ponderaciones personalizadas para cada criterio
     */
    private readonly array $weights;

    /**
     * Reglas de combinación de criterios
     */
    private readonly array $combinationRules;

    /**
     * Umbral mínimo de compatibilidad
     */
    private readonly float $minCompatibilityScore;

    /**
     * Si el filtro está activo
     */
    private readonly bool $isActive;

    /**
     * Metadatos adicionales del filtro
     */
    private readonly array $metadata;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(
        string $id,
        UserId $userId,
        string $name,
        array $customCriteria,
        array $weights,
        array $combinationRules,
        float $minCompatibilityScore,
        bool $isActive,
        array $metadata
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->name = $name;
        $this->customCriteria = $customCriteria;
        $this->weights = $weights;
        $this->combinationRules = $combinationRules;
        $this->minCompatibilityScore = $minCompatibilityScore;
        $this->isActive = $isActive;
        $this->metadata = $metadata;
    }

    /**
     * Factory method para crear una nueva instancia de CustomFilter
     *
     * @param UserId $userId ID del usuario propietario
     * @param string $name Nombre del filtro
     * @param array $customCriteria Criterios personalizados
     * @param array $weights Ponderaciones de criterios
     * @param array $combinationRules Reglas de combinación
     * @param float $minCompatibilityScore Umbral mínimo de compatibilidad
     * @param bool $isActive Si está activo
     * @param array $metadata Metadatos adicionales
     * @return self Nueva instancia de CustomFilter
     * @throws InvalidArgumentException Si los valores no son válidos
     */
    public static function create(
        UserId $userId,
        string $name,
        array $customCriteria,
        array $weights = [],
        array $combinationRules = [],
        float $minCompatibilityScore = 0.5,
        bool $isActive = true,
        array $metadata = []
    ): self {
        self::validateName($name);
        self::validateCustomCriteria($customCriteria);
        self::validateWeights($weights);
        self::validateCombinationRules($combinationRules);
        self::validateCompatibilityScore($minCompatibilityScore);
        
        $id = self::generateId($userId, $name);
        
        return new self(
            $id,
            $userId,
            $name,
            $customCriteria,
            $weights,
            $combinationRules,
            $minCompatibilityScore,
            $isActive,
            $metadata
        );
    }

    /**
     * Factory method para crear CustomFilter desde array
     *
     * @param array $data Array con datos del filtro
     * @return self Nueva instancia de CustomFilter
     * @throws InvalidArgumentException Si el array no es válido
     */
    public static function fromArray(array $data): self
    {
        $userId = UserId::from($data['user_id']);
        $name = $data['name'];
        $customCriteria = $data['custom_criteria'] ?? [];
        $weights = $data['weights'] ?? [];
        $combinationRules = $data['combination_rules'] ?? [];
        $minCompatibilityScore = $data['min_compatibility_score'] ?? 0.5;
        $isActive = $data['is_active'] ?? true;
        $metadata = $data['metadata'] ?? [];

        return self::create(
            $userId,
            $name,
            $customCriteria,
            $weights,
            $combinationRules,
            $minCompatibilityScore,
            $isActive,
            $metadata
        );
    }

    /**
     * Factory methods para filtros personalizados predefinidos comunes
     */
    public static function compatibilityFocused(UserId $userId): self
    {
        $criteria = [
            'personality_match' => ['weight' => 0.3, 'required' => true],
            'interest_overlap' => ['weight' => 0.25, 'min_threshold' => 0.6],
            'lifestyle_compatibility' => ['weight' => 0.2, 'required' => true],
            'communication_style' => ['weight' => 0.15, 'required' => false],
            'life_goals' => ['weight' => 0.1, 'required' => true]
        ];

        return new self(
            self::generateId($userId, 'Compatibility Focused'),
            $userId,
            'Compatibility Focused',
            $criteria,
            array_combine(array_keys($criteria), array_column($criteria, 'weight')),
            ['logic' => 'weighted_average', 'min_required' => 3],
            0.7,
            true,
            ['type' => 'compatibility', 'description' => 'Enfocado en compatibilidad general']
        );
    }

    public static function activityBased(UserId $userId): self
    {
        $criteria = [
            'shared_activities' => ['weight' => 0.4, 'required' => true],
            'activity_level' => ['weight' => 0.3, 'required' => true],
            'schedule_compatibility' => ['weight' => 0.2, 'required' => false],
            'location_proximity' => ['weight' => 0.1, 'max_distance' => 30]
        ];

        return new self(
            self::generateId($userId, 'Activity Based'),
            $userId,
            'Activity Based',
            $criteria,
            array_combine(array_keys($criteria), array_column($criteria, 'weight')),
            ['logic' => 'weighted_average', 'min_required' => 2],
            0.6,
            true,
            ['type' => 'activity', 'description' => 'Basado en actividades compartidas']
        );
    }

    public static function relationshipGoals(UserId $userId): self
    {
        $criteria = [
            'relationship_goals' => ['weight' => 0.5, 'required' => true],
            'family_plans' => ['weight' => 0.3, 'required' => true],
            'lifestyle_alignment' => ['weight' => 0.2, 'required' => false]
        ];

        return new self(
            self::generateId($userId, 'Relationship Goals'),
            $userId,
            'Relationship Goals',
            $criteria,
            array_combine(array_keys($criteria), array_column($criteria, 'weight')),
            ['logic' => 'all_required', 'min_required' => 2],
            0.8,
            true,
            ['type' => 'relationship', 'description' => 'Enfocado en objetivos de relación']
        );
    }

    /**
     * Obtiene el ID del filtro
     *
     * @return string ID del filtro
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Obtiene el ID del usuario propietario
     *
     * @return UserId ID del usuario
     */
    public function getUserId(): UserId
    {
        return $this->userId;
    }

    /**
     * Obtiene el nombre del filtro
     *
     * @return string Nombre del filtro
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Obtiene los criterios personalizados
     *
     * @return array Criterios personalizados
     */
    public function getCustomCriteria(): array
    {
        return $this->customCriteria;
    }

    /**
     * Obtiene las ponderaciones
     *
     * @return array Ponderaciones de criterios
     */
    public function getWeights(): array
    {
        return $this->weights;
    }

    /**
     * Obtiene las reglas de combinación
     *
     * @return array Reglas de combinación
     */
    public function getCombinationRules(): array
    {
        return $this->combinationRules;
    }

    /**
     * Obtiene el umbral mínimo de compatibilidad
     *
     * @return float Umbral mínimo
     */
    public function getMinCompatibilityScore(): float
    {
        return $this->minCompatibilityScore;
    }

    /**
     * Verifica si el filtro está activo
     *
     * @return bool True si está activo
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Obtiene los metadatos
     *
     * @return array Metadatos del filtro
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Verifica si el filtro tiene criterios requeridos
     *
     * @return bool True si tiene criterios requeridos
     */
    public function hasRequiredCriteria(): bool
    {
        foreach ($this->customCriteria as $criterion) {
            if ($criterion['required'] ?? false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene el número de criterios definidos
     *
     * @return int Número de criterios
     */
    public function getCriteriaCount(): int
    {
        return count($this->customCriteria);
    }

    /**
     * Obtiene el número de criterios requeridos
     *
     * @return int Número de criterios requeridos
     */
    public function getRequiredCriteriaCount(): int
    {
        $count = 0;
        foreach ($this->customCriteria as $criterion) {
            if ($criterion['required'] ?? false) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Verifica si el filtro es complejo
     *
     * @return bool True si es complejo (más de 5 criterios)
     */
    public function isComplex(): bool
    {
        return $this->getCriteriaCount() > 5;
    }

    /**
     * Verifica si el filtro es estricto
     *
     * @return bool True si es estricto (muchos criterios requeridos)
     */
    public function isStrict(): bool
    {
        $requiredRatio = $this->getRequiredCriteriaCount() / max($this->getCriteriaCount(), 1);
        return $requiredRatio > 0.7 && $this->minCompatibilityScore > 0.7;
    }

    /**
     * Verifica si el filtro es flexible
     *
     * @return bool True si es flexible (pocos criterios requeridos)
     */
    public function isFlexible(): bool
    {
        $requiredRatio = $this->getRequiredCriteriaCount() / max($this->getCriteriaCount(), 1);
        return $requiredRatio < 0.3 && $this->minCompatibilityScore < 0.5;
    }

    /**
     * Obtiene la categoría del filtro
     *
     * @return string Categoría (compatibility, activity, relationship, complex, strict, flexible)
     */
    public function getCategory(): string
    {
        if (isset($this->metadata['type'])) {
            return $this->metadata['type'];
        }

        if ($this->isStrict()) {
            return 'strict';
        }

        if ($this->isFlexible()) {
            return 'flexible';
        }

        if ($this->isComplex()) {
            return 'complex';
        }

        return 'custom';
    }

    /**
     * Obtiene el nivel de selectividad del filtro
     * (1 = muy selectivo, 5 = muy permisivo)
     *
     * @return int Nivel de selectividad
     */
    public function getSelectivityLevel(): int
    {
        if ($this->isStrict()) {
            return 1; // Muy selectivo
        }

        if ($this->isComplex() && !$this->isFlexible()) {
            return 2; // Selectivo
        }

        if ($this->getCriteriaCount() >= 3) {
            return 3; // Balanceado
        }

        if ($this->isFlexible()) {
            return 4; // Permisivo
        }

        return 5; // Muy permisivo
    }

    /**
     * Calcula el score de compatibilidad para un candidato
     *
     * @param array $candidateData Datos del candidato
     * @return float Score de compatibilidad (0.0-1.0)
     */
    public function calculateCompatibilityScore(array $candidateData): float
    {
        if (!$this->isActive || empty($this->customCriteria)) {
            return 0.0;
        }

        $totalScore = 0.0;
        $totalWeight = 0.0;
        $requiredCriteriaMet = 0;

        foreach ($this->customCriteria as $criterionName => $criterionConfig) {
            $weight = $this->weights[$criterionName] ?? 1.0;
            $isRequired = $criterionConfig['required'] ?? false;
            
            $criterionScore = $this->calculateCriterionScore($criterionName, $criterionConfig, $candidateData);
            
            if ($isRequired && $criterionScore < ($criterionConfig['min_threshold'] ?? 0.5)) {
                return 0.0; // Falla criterio requerido
            }

            if ($isRequired && $criterionScore >= ($criterionConfig['min_threshold'] ?? 0.5)) {
                $requiredCriteriaMet++;
            }

            $totalScore += $criterionScore * $weight;
            $totalWeight += $weight;
        }

        // Verificar criterios requeridos mínimos
        $minRequired = $this->combinationRules['min_required'] ?? $this->getRequiredCriteriaCount();
        if ($requiredCriteriaMet < $minRequired) {
            return 0.0;
        }

        return $totalWeight > 0 ? $totalScore / $totalWeight : 0.0;
    }

    /**
     * Calcula el score para un criterio específico
     *
     * @param string $criterionName Nombre del criterio
     * @param array $criterionConfig Configuración del criterio
     * @param array $candidateData Datos del candidato
     * @return float Score del criterio (0.0-1.0)
     */
    private function calculateCriterionScore(string $criterionName, array $criterionConfig, array $candidateData): float
    {
        // Implementación básica - puede ser extendida según necesidades específicas
        if (!isset($candidateData[$criterionName])) {
            return 0.0;
        }

        $candidateValue = $candidateData[$criterionName];
        $expectedValue = $criterionConfig['value'] ?? null;
        
        if ($expectedValue === null) {
            return 1.0; // Sin valor esperado específico
        }

        // Comparación simple - puede ser más sofisticada
        if ($candidateValue === $expectedValue) {
            return 1.0;
        }

        // Para arrays, calcular overlap
        if (is_array($candidateValue) && is_array($expectedValue)) {
            $intersection = array_intersect($candidateValue, $expectedValue);
            $union = array_unique(array_merge($candidateValue, $expectedValue));
            return empty($union) ? 0.0 : count($intersection) / count($union);
        }

        return 0.0;
    }

    /**
     * Verifica si un candidato cumple con este filtro
     *
     * @param array $candidateData Datos del candidato
     * @return bool True si cumple con el filtro
     */
    public function matches(array $candidateData): bool
    {
        return $this->calculateCompatibilityScore($candidateData) >= $this->minCompatibilityScore;
    }

    /**
     * Verifica si este filtro es igual a otro
     *
     * @param self $other Otro filtro para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->id === $other->id &&
               $this->userId->equals($other->userId) &&
               $this->name === $other->name &&
               $this->customCriteria === $other->customCriteria;
    }

    /**
     * Obtiene la descripción legible del filtro
     *
     * @return string Descripción del filtro
     */
    public function getDescription(): string
    {
        $parts = [];

        $parts[] = "Filtro: {$this->name}";
        
        if (isset($this->metadata['description'])) {
            $parts[] = $this->metadata['description'];
        }

        $parts[] = "Criterios: {$this->getCriteriaCount()}";
        $parts[] = "Umbral: " . round($this->minCompatibilityScore * 100) . '%';

        if ($this->hasRequiredCriteria()) {
            $parts[] = "Requeridos: {$this->getRequiredCriteriaCount()}";
        }

        return implode(' - ', $parts);
    }

    /**
     * Convierte el filtro a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId->toInt(),
            'name' => $this->name,
            'custom_criteria' => $this->customCriteria,
            'weights' => $this->weights,
            'combination_rules' => $this->combinationRules,
            'min_compatibility_score' => $this->minCompatibilityScore,
            'is_active' => $this->isActive,
            'metadata' => $this->metadata,
            'category' => $this->getCategory(),
            'selectivity_level' => $this->getSelectivityLevel(),
            'criteria_count' => $this->getCriteriaCount(),
            'required_criteria_count' => $this->getRequiredCriteriaCount(),
            'description' => $this->getDescription(),
            'is_complex' => $this->isComplex(),
            'is_strict' => $this->isStrict(),
            'is_flexible' => $this->isFlexible(),
            'has_required_criteria' => $this->hasRequiredCriteria(),
        ];
    }

    /**
     * Representación en string del filtro
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return $this->getDescription();
    }

    /**
     * Serialización para JSON
     *
     * @return array Valor para JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'custom_criteria' => $this->customCriteria,
            'weights' => $this->weights,
            'min_compatibility_score' => $this->minCompatibilityScore,
            'is_active' => $this->isActive,
            'metadata' => $this->metadata
        ];
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'criteria_count' => $this->getCriteriaCount(),
            'required_count' => $this->getRequiredCriteriaCount(),
            'min_score' => $this->minCompatibilityScore,
            'is_active' => $this->isActive,
            'category' => $this->getCategory(),
        ];
    }

    /**
     * Valida que el nombre sea válido
     *
     * @param string $name Nombre a validar
     * @throws InvalidArgumentException Si el nombre no es válido
     */
    private static function validateName(string $name): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException(
                "El nombre del filtro personalizado no puede estar vacío"
            );
        }

        if (strlen($name) > 100) {
            throw new InvalidArgumentException(
                "El nombre del filtro personalizado no puede exceder 100 caracteres"
            );
        }
    }

    /**
     * Valida que los criterios personalizados sean válidos
     *
     * @param array $criteria Criterios a validar
     * @throws InvalidArgumentException Si los criterios no son válidos
     */
    private static function validateCustomCriteria(array $criteria): void
    {
        if (count($criteria) > 20) {
            throw new InvalidArgumentException(
                "No se pueden especificar más de 20 criterios personalizados"
            );
        }

        foreach ($criteria as $name => $config) {
            if (!is_string($name) || empty(trim($name))) {
                throw new InvalidArgumentException(
                    "El nombre de cada criterio debe ser una cadena no vacía"
                );
            }

            if (!is_array($config)) {
                throw new InvalidArgumentException(
                    "La configuración de cada criterio debe ser un array"
                );
            }
        }
    }

    /**
     * Valida que las ponderaciones sean válidas
     *
     * @param array $weights Ponderaciones a validar
     * @throws InvalidArgumentException Si las ponderaciones no son válidas
     */
    private static function validateWeights(array $weights): void
    {
        foreach ($weights as $name => $weight) {
            if (!is_numeric($weight) || $weight < 0) {
                throw new InvalidArgumentException(
                    "Cada ponderación debe ser un número no negativo, se recibió: {$weight} para {$name}"
                );
            }
        }
    }

    /**
     * Valida que las reglas de combinación sean válidas
     *
     * @param array $rules Reglas a validar
     * @throws InvalidArgumentException Si las reglas no son válidas
     */
    private static function validateCombinationRules(array $rules): void
    {
        $validLogics = ['weighted_average', 'all_required', 'any_required', 'custom'];
        
        if (isset($rules['logic']) && !in_array($rules['logic'], $validLogics)) {
            throw new InvalidArgumentException(
                "Lógica de combinación inválida: {$rules['logic']}. Válidas: " . implode(', ', $validLogics)
            );
        }
    }

    /**
     * Valida que el score de compatibilidad sea válido
     *
     * @param float $score Score a validar
     * @throws InvalidArgumentException Si el score no es válido
     */
    private static function validateCompatibilityScore(float $score): void
    {
        if ($score < 0.0 || $score > 1.0) {
            throw new InvalidArgumentException(
                "El score mínimo de compatibilidad debe estar entre 0.0 y 1.0, se recibió: {$score}"
            );
        }
    }

    /**
     * Genera un ID único para el filtro
     *
     * @param UserId $userId ID del usuario
     * @param string $name Nombre del filtro
     * @return string ID único generado
     */
    private static function generateId(UserId $userId, string $name): string
    {
        $timestamp = time();
        $hash = substr(md5($userId->toString() . $name . $timestamp), 0, 8);
        return "custom_filter_{$userId->toString()}_{$hash}";
    }

    /**
     * Verifica si un valor es un CustomFilter válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if ($value instanceof self) {
                return true;
            }

            if (is_array($value)) {
                self::fromArray($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea CustomFilter desde un valor mixto
     *
     * @param mixed $value Valor del filtro personalizado
     * @return self Nueva instancia de CustomFilter
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_array($value)) {
            return self::fromArray($value);
        }

        throw new InvalidArgumentException(
            'CustomFilter solo puede crearse desde array o instancia de CustomFilter, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para este filtro (útil para cache keys)
     *
     * @return string Hash único del filtro
     */
    public function getHash(): string
    {
        return 'custom_filter_' . $this->id;
    }

    /**
     * Verifica si este filtro es compatible con el sistema de analytics
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si este filtro es compatible con el sistema de caché
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
