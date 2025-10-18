<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * InterestFilter Value Object
 * 
 * Representa un filtro de intereses en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de filtros de intereses en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para manejar
 * filtros basados en intereses, hobbies y actividades en búsquedas y matching.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta intereses válidos y configuraciones coherentes
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos InterestFilter con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y filtros de intereses
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class InterestFilter implements JsonSerializable
{
    /**
     * Intereses requeridos (deben estar presentes)
     */
    private readonly array $requiredInterests;

    /**
     * Intereses preferidos (opcionales pero valorados)
     */
    private readonly array $preferredInterests;

    /**
     * Intereses excluidos (no deben estar presentes)
     */
    private readonly array $excludedInterests;

    /**
     * Si el filtro usa ponderación
     */
    private readonly bool $weighted;

    /**
     * Umbral mínimo de coincidencia (0.0-1.0)
     */
    private readonly float $minMatchThreshold;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(
        array $requiredInterests,
        array $preferredInterests,
        array $excludedInterests,
        bool $weighted,
        float $minMatchThreshold
    ) {
        $this->requiredInterests = $requiredInterests;
        $this->preferredInterests = $preferredInterests;
        $this->excludedInterests = $excludedInterests;
        $this->weighted = $weighted;
        $this->minMatchThreshold = $minMatchThreshold;
    }

    /**
     * Factory method para crear una nueva instancia de InterestFilter
     *
     * @param array $requiredInterests Intereses requeridos
     * @param array $preferredInterests Intereses preferidos
     * @param array $excludedInterests Intereses excluidos
     * @param bool $weighted Si usa ponderación
     * @param float $minMatchThreshold Umbral mínimo de coincidencia
     * @return self Nueva instancia de InterestFilter
     * @throws InvalidArgumentException Si los valores no son válidos
     */
    public static function create(
        array $requiredInterests = [],
        array $preferredInterests = [],
        array $excludedInterests = [],
        bool $weighted = false,
        float $minMatchThreshold = 0.2
    ): self {
        self::validateInterests($requiredInterests, $preferredInterests, $excludedInterests);
        self::validateThreshold($minMatchThreshold);
        return new self($requiredInterests, $preferredInterests, $excludedInterests, $weighted, $minMatchThreshold);
    }

    /**
     * Factory method para crear InterestFilter desde array
     *
     * @param array $filter Array con configuración del filtro
     * @return self Nueva instancia de InterestFilter
     * @throws InvalidArgumentException Si el array no es válido
     */
    public static function fromArray(array $filter): self
    {
        $required = $filter['required'] ?? $filter['required_interests'] ?? [];
        $preferred = $filter['preferred'] ?? $filter['preferred_interests'] ?? [];
        $excluded = $filter['excluded'] ?? $filter['excluded_interests'] ?? [];
        $weighted = $filter['weighted'] ?? false;
        $threshold = $filter['min_match_threshold'] ?? $filter['threshold'] ?? 0.2;

        return self::create($required, $preferred, $excluded, $weighted, $threshold);
    }

    /**
     * Factory methods para filtros predefinidos comunes
     */
    public static function strict(array $interests): self
    {
        return new self($interests, [], [], true, 0.8);
    }

    public static function flexible(array $interests): self
    {
        return new self([], $interests, [], false, 0.3);
    }

    public static function balanced(array $required, array $preferred): self
    {
        return new self($required, $preferred, [], true, 0.5);
    }

    public static function exclusive(array $excluded): self
    {
        return new self([], [], $excluded, false, 0.0);
    }

    public static function open(): self
    {
        return new self([], [], [], false, 0.0);
    }

    /**
     * Obtiene los intereses requeridos
     *
     * @return array Intereses requeridos
     */
    public function getRequiredInterests(): array
    {
        return $this->requiredInterests;
    }

    /**
     * Obtiene los intereses preferidos
     *
     * @return array Intereses preferidos
     */
    public function getPreferredInterests(): array
    {
        return $this->preferredInterests;
    }

    /**
     * Obtiene los intereses excluidos
     *
     * @return array Intereses excluidos
     */
    public function getExcludedInterests(): array
    {
        return $this->excludedInterests;
    }

    /**
     * Verifica si el filtro usa ponderación
     *
     * @return bool True si usa ponderación
     */
    public function isWeighted(): bool
    {
        return $this->weighted;
    }

    /**
     * Obtiene el umbral mínimo de coincidencia
     *
     * @return float Umbral mínimo (0.0-1.0)
     */
    public function getMinMatchThreshold(): float
    {
        return $this->minMatchThreshold;
    }

    /**
     * Verifica si el filtro es estricto
     *
     * @return bool True si es estricto
     */
    public function isStrict(): bool
    {
        return !empty($this->requiredInterests) && $this->minMatchThreshold >= 0.7;
    }

    /**
     * Verifica si el filtro es flexible
     *
     * @return bool True si es flexible
     */
    public function isFlexible(): bool
    {
        return empty($this->requiredInterests) && $this->minMatchThreshold <= 0.3;
    }

    /**
     * Verifica si el filtro es balanceado
     *
     * @return bool True si es balanceado
     */
    public function isBalanced(): bool
    {
        return !empty($this->preferredInterests) && $this->minMatchThreshold >= 0.4 && $this->minMatchThreshold <= 0.6;
    }

    /**
     * Verifica si el filtro es excluyente
     *
     * @return bool True si es excluyente
     */
    public function isExclusive(): bool
    {
        return !empty($this->excludedInterests);
    }

    /**
     * Verifica si el filtro está vacío (sin restricciones)
     *
     * @return bool True si está vacío
     */
    public function isEmpty(): bool
    {
        return empty($this->requiredInterests) && 
               empty($this->preferredInterests) && 
               empty($this->excludedInterests);
    }

    /**
     * Obtiene el total de intereses en el filtro
     *
     * @return int Total de intereses únicos
     */
    public function getTotalInterests(): int
    {
        $allInterests = array_unique(array_merge(
            $this->requiredInterests,
            $this->preferredInterests,
            $this->excludedInterests
        ));
        
        return count($allInterests);
    }

    /**
     * Obtiene la categoría del filtro
     *
     * @return string Categoría (strict, flexible, balanced, exclusive, open)
     */
    public function getCategory(): string
    {
        if ($this->isStrict()) {
            return 'strict';
        }

        if ($this->isExclusive()) {
            return 'exclusive';
        }

        if ($this->isBalanced()) {
            return 'balanced';
        }

        if ($this->isFlexible()) {
            return 'flexible';
        }

        if ($this->isEmpty()) {
            return 'open';
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
        if ($this->isEmpty()) {
            return 5; // Muy permisivo
        }

        if ($this->isStrict()) {
            return 1; // Muy selectivo
        }

        if ($this->isExclusive()) {
            return 2; // Selectivo
        }

        if ($this->isBalanced()) {
            return 3; // Balanceado
        }

        if ($this->isFlexible()) {
            return 4; // Permisivo
        }

        return 3; // Balanceado por defecto
    }

    /**
     * Verifica si un conjunto de intereses cumple con este filtro
     *
     * @param array $candidateInterests Intereses del candidato
     * @return bool True si cumple con el filtro
     */
    public function matches(array $candidateInterests): bool
    {
        // Verificar intereses excluidos
        if (!empty($this->excludedInterests)) {
            $hasExcluded = !empty(array_intersect($this->excludedInterests, $candidateInterests));
            if ($hasExcluded) {
                return false;
            }
        }

        // Verificar intereses requeridos
        if (!empty($this->requiredInterests)) {
            $hasAllRequired = count(array_intersect($this->requiredInterests, $candidateInterests)) === count($this->requiredInterests);
            if (!$hasAllRequired) {
                return false;
            }
        }

        // Verificar umbral de coincidencia para intereses preferidos
        if (!empty($this->preferredInterests)) {
            $matchScore = $this->calculateMatchScore($candidateInterests);
            return $matchScore >= $this->minMatchThreshold;
        }

        return true;
    }

    /**
     * Calcula el puntaje de coincidencia con intereses preferidos
     *
     * @param array $candidateInterests Intereses del candidato
     * @return float Puntaje de coincidencia (0.0-1.0)
     */
    public function calculateMatchScore(array $candidateInterests): float
    {
        if (empty($this->preferredInterests) || empty($candidateInterests)) {
            return 0.0;
        }

        $intersection = array_intersect($this->preferredInterests, $candidateInterests);
        $union = array_unique(array_merge($this->preferredInterests, $candidateInterests));

        if (empty($union)) {
            return 0.0;
        }

        $basicScore = count($intersection) / count($union);

        if (!$this->weighted) {
            return $basicScore;
        }

        // Aplicar ponderación por categorías de interés
        $weightedScore = 0.0;
        $totalWeight = 0.0;

        foreach ($intersection as $interest) {
            $weight = $this->getInterestWeight($interest);
            $weightedScore += $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weightedScore / $totalWeight : $basicScore;
    }

    /**
     * Obtiene el peso de un interés específico
     *
     * @param string $interest Interés a ponderar
     * @return float Peso del interés
     */
    private function getInterestWeight(string $interest): float
    {
        $weights = [
            // Intereses de alta compatibilidad
            'música' => 1.5, 'music' => 1.5,
            'viajes' => 1.4, 'travel' => 1.4,
            'deportes' => 1.3, 'sports' => 1.3,
            'arte' => 1.3, 'art' => 1.3,
            'cocina' => 1.2, 'cooking' => 1.2,
            
            // Intereses de compatibilidad media
            'lectura' => 1.0, 'reading' => 1.0,
            'cine' => 1.0, 'movies' => 1.0,
            'fotografía' => 1.0, 'photography' => 1.0,
            'naturaleza' => 1.0, 'nature' => 1.0,
            
            // Intereses básicos
            'tecnología' => 0.8, 'technology' => 0.8,
            'juegos' => 0.8, 'gaming' => 0.8,
            'moda' => 0.7, 'fashion' => 0.7,
        ];

        return $weights[$interest] ?? 1.0;
    }

    /**
     * Verifica si este filtro es igual a otro
     *
     * @param self $other Otro filtro para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->requiredInterests === $other->requiredInterests &&
               $this->preferredInterests === $other->preferredInterests &&
               $this->excludedInterests === $other->excludedInterests &&
               $this->weighted === $other->weighted &&
               abs($this->minMatchThreshold - $other->minMatchThreshold) < 0.01;
    }

    /**
     * Obtiene la descripción legible del filtro
     *
     * @return string Descripción del filtro
     */
    public function getDescription(): string
    {
        $parts = [];

        if (!empty($this->requiredInterests)) {
            $parts[] = 'Requeridos: ' . implode(', ', $this->requiredInterests);
        }

        if (!empty($this->preferredInterests)) {
            $parts[] = 'Preferidos: ' . implode(', ', $this->preferredInterests);
        }

        if (!empty($this->excludedInterests)) {
            $parts[] = 'Excluidos: ' . implode(', ', $this->excludedInterests);
        }

        if ($this->weighted) {
            $parts[] = 'Con ponderación';
        }

        $parts[] = 'Umbral: ' . round($this->minMatchThreshold * 100) . '%';

        return implode('; ', $parts);
    }

    /**
     * Convierte el filtro a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'required_interests' => $this->requiredInterests,
            'preferred_interests' => $this->preferredInterests,
            'excluded_interests' => $this->excludedInterests,
            'weighted' => $this->weighted,
            'min_match_threshold' => $this->minMatchThreshold,
            'category' => $this->getCategory(),
            'selectivity_level' => $this->getSelectivityLevel(),
            'total_interests' => $this->getTotalInterests(),
            'description' => $this->getDescription(),
            'is_strict' => $this->isStrict(),
            'is_flexible' => $this->isFlexible(),
            'is_balanced' => $this->isBalanced(),
            'is_exclusive' => $this->isExclusive(),
            'is_empty' => $this->isEmpty(),
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
            'required' => $this->requiredInterests,
            'preferred' => $this->preferredInterests,
            'excluded' => $this->excludedInterests,
            'weighted' => $this->weighted,
            'min_match_threshold' => $this->minMatchThreshold
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
            'required_count' => count($this->requiredInterests),
            'preferred_count' => count($this->preferredInterests),
            'excluded_count' => count($this->excludedInterests),
            'weighted' => $this->weighted,
            'threshold' => $this->minMatchThreshold,
            'category' => $this->getCategory(),
        ];
    }

    /**
     * Valida que los intereses sean válidos
     *
     * @param array $requiredInterests Intereses requeridos a validar
     * @param array $preferredInterests Intereses preferidos a validar
     * @param array $excludedInterests Intereses excluidos a validar
     * @throws InvalidArgumentException Si los intereses no son válidos
     */
    private static function validateInterests(array $requiredInterests, array $preferredInterests, array $excludedInterests): void
    {
        $allInterests = array_merge($requiredInterests, $preferredInterests, $excludedInterests);
        
        foreach ($allInterests as $interest) {
            if (!is_string($interest) || empty(trim($interest))) {
                throw new InvalidArgumentException(
                    "Cada interés debe ser una cadena no vacía"
                );
            }
        }

        if (count($allInterests) > 50) {
            throw new InvalidArgumentException(
                "No se pueden especificar más de 50 intereses en total"
            );
        }

        // Verificar que no haya conflictos entre requeridos y excluidos
        $conflicts = array_intersect($requiredInterests, $excludedInterests);
        if (!empty($conflicts)) {
            throw new InvalidArgumentException(
                "No se puede requerir y excluir el mismo interés: " . implode(', ', $conflicts)
            );
        }
    }

    /**
     * Valida que el umbral sea válido
     *
     * @param float $threshold Umbral a validar
     * @throws InvalidArgumentException Si el umbral no es válido
     */
    private static function validateThreshold(float $threshold): void
    {
        if ($threshold < 0.0 || $threshold > 1.0) {
            throw new InvalidArgumentException(
                "El umbral de coincidencia debe estar entre 0.0 y 1.0, se recibió: {$threshold}"
            );
        }
    }

    /**
     * Verifica si un valor es un InterestFilter válido
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
     * Crea InterestFilter desde un valor mixto
     *
     * @param mixed $value Valor del filtro de intereses
     * @return self Nueva instancia de InterestFilter
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
            'InterestFilter solo puede crearse desde array o instancia de InterestFilter, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para este filtro (útil para cache keys)
     *
     * @return string Hash único del filtro
     */
    public function getHash(): string
    {
        $data = [
            'required' => $this->requiredInterests,
            'preferred' => $this->preferredInterests,
            'excluded' => $this->excludedInterests,
            'weighted' => $this->weighted,
            'threshold' => $this->minMatchThreshold,
        ];

        return 'interest_filter_' . substr(md5(serialize($data)), 0, 12);
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
