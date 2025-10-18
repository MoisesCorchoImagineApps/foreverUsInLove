<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * FilterCriteria Value Object
 * 
 * Representa los criterios de filtrado completos en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de criterios de filtrado en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para combinar
 * múltiples tipos de filtros (edad, distancia, intereses, estilo de vida, etc.)
 * en un conjunto coherente de criterios de búsqueda.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta combinaciones válidas de criterios
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos FilterCriteria con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y filtros
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class FilterCriteria implements JsonSerializable
{
    /**
     * Filtro de rango de edad
     */
    private readonly ?AgeRange $ageRange;

    /**
     * Filtro de distancia
     */
    private readonly ?DistanceFilter $distanceFilter;

    /**
     * Filtro de intereses
     */
    private readonly ?InterestFilter $interestFilter;

    /**
     * Filtro de estilo de vida
     */
    private readonly ?LifestyleFilter $lifestyleFilter;

    /**
     * Filtro demográfico
     */
    private readonly ?DemographicFilter $demographicFilter;

    /**
     * Filtro personalizado
     */
    private readonly ?CustomFilter $customFilter;

    /**
     * Preferencias de género
     */
    private readonly array $genderPreferences;

    /**
     * Restricciones básicas
     */
    private readonly array $basicConstraints;

    /**
     * Filtros premium
     */
    private readonly array $premiumFilters;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(
        ?AgeRange $ageRange = null,
        ?DistanceFilter $distanceFilter = null,
        ?InterestFilter $interestFilter = null,
        ?LifestyleFilter $lifestyleFilter = null,
        ?DemographicFilter $demographicFilter = null,
        ?CustomFilter $customFilter = null,
        array $genderPreferences = [],
        array $basicConstraints = [],
        array $premiumFilters = []
    ) {
        $this->ageRange = $ageRange;
        $this->distanceFilter = $distanceFilter;
        $this->interestFilter = $interestFilter;
        $this->lifestyleFilter = $lifestyleFilter;
        $this->demographicFilter = $demographicFilter;
        $this->customFilter = $customFilter;
        $this->genderPreferences = $genderPreferences;
        $this->basicConstraints = $basicConstraints;
        $this->premiumFilters = $premiumFilters;
    }

    /**
     * Factory method para crear una nueva instancia de FilterCriteria
     *
     * @param array $criteria Array con criterios de filtrado
     * @return self Nueva instancia de FilterCriteria
     * @throws InvalidArgumentException Si los criterios no son válidos
     */
    public static function create(array $criteria = []): self
    {
        self::validateCriteria($criteria);
        
        return new self(
            $criteria['age_range'] ?? $criteria['age'] ?? null,
            $criteria['distance'] ?? $criteria['distance_filter'] ?? null,
            $criteria['interests'] ?? $criteria['interest_filter'] ?? null,
            $criteria['lifestyle'] ?? $criteria['lifestyle_filter'] ?? null,
            $criteria['demographics'] ?? $criteria['demographic_filter'] ?? null,
            $criteria['custom'] ?? $criteria['custom_filter'] ?? null,
            $criteria['gender_preferences'] ?? $criteria['gender'] ?? [],
            $criteria['basic_constraints'] ?? $criteria['constraints'] ?? [],
            $criteria['premium_filters'] ?? $criteria['premium'] ?? []
        );
    }

    /**
     * Factory method para crear FilterCriteria desde array
     *
     * @param array $data Array con datos de criterios
     * @return self Nueva instancia de FilterCriteria
     * @throws InvalidArgumentException Si el array no es válido
     */
    public static function fromArray(array $data): self
    {
        return self::create($data);
    }

    /**
     * Factory methods para criterios predefinidos comunes
     */
    public static function basic(): self
    {
        return new self(
            AgeRange::open(),
            DistanceFilter::regional(),
            null,
            null,
            null,
            null,
            ['male', 'female'],
            ['active_only' => true],
            []
        );
    }

    public static function strict(): self
    {
        return new self(
            AgeRange::adult(),
            DistanceFilter::nearby(),
            InterestFilter::strict(['music', 'travel']),
            LifestyleFilter::healthConscious(),
            DemographicFilter::professional(),
            null,
            ['male', 'female'],
            ['verified_only' => true, 'active_only' => true],
            ['high_compatibility_only' => true]
        );
    }

    public static function flexible(): self
    {
        return new self(
            AgeRange::open(),
            DistanceFilter::global(),
            InterestFilter::flexible([]),
            null,
            null,
            null,
            ['male', 'female'],
            [],
            []
        );
    }

    public static function professional(): self
    {
        return new self(
            AgeRange::mature(),
            DistanceFilter::city(),
            null,
            LifestyleFilter::professional(),
            DemographicFilter::professional(),
            null,
            ['male', 'female'],
            ['verified_only' => true],
            []
        );
    }

    public static function open(): self
    {
        return new self(); // Sin restricciones
    }

    /**
     * Obtiene el filtro de rango de edad
     *
     * @return AgeRange|null Filtro de edad
     */
    public function getAgeRange(): ?AgeRange
    {
        return $this->ageRange;
    }

    /**
     * Obtiene el filtro de distancia
     *
     * @return DistanceFilter|null Filtro de distancia
     */
    public function getDistanceFilter(): ?DistanceFilter
    {
        return $this->distanceFilter;
    }

    /**
     * Obtiene el filtro de intereses
     *
     * @return InterestFilter|null Filtro de intereses
     */
    public function getInterestFilter(): ?InterestFilter
    {
        return $this->interestFilter;
    }

    /**
     * Obtiene el filtro de estilo de vida
     *
     * @return LifestyleFilter|null Filtro de estilo de vida
     */
    public function getLifestyleFilter(): ?LifestyleFilter
    {
        return $this->lifestyleFilter;
    }

    /**
     * Obtiene el filtro demográfico
     *
     * @return DemographicFilter|null Filtro demográfico
     */
    public function getDemographicFilter(): ?DemographicFilter
    {
        return $this->demographicFilter;
    }

    /**
     * Obtiene el filtro personalizado
     *
     * @return CustomFilter|null Filtro personalizado
     */
    public function getCustomFilter(): ?CustomFilter
    {
        return $this->customFilter;
    }

    /**
     * Obtiene las preferencias de género
     *
     * @return array Preferencias de género
     */
    public function getGenderPreferences(): array
    {
        return $this->genderPreferences;
    }

    /**
     * Obtiene las restricciones básicas
     *
     * @return array Restricciones básicas
     */
    public function getBasicConstraints(): array
    {
        return $this->basicConstraints;
    }

    /**
     * Obtiene los filtros premium
     *
     * @return array Filtros premium
     */
    public function getPremiumFilters(): array
    {
        return $this->premiumFilters;
    }

    /**
     * Verifica si tiene filtro de edad
     *
     * @return bool True si tiene filtro de edad
     */
    public function hasAgeFilter(): bool
    {
        return $this->ageRange !== null;
    }

    /**
     * Verifica si tiene filtro de distancia
     *
     * @return bool True si tiene filtro de distancia
     */
    public function hasDistanceFilter(): bool
    {
        return $this->distanceFilter !== null;
    }

    /**
     * Verifica si tiene filtro de intereses
     *
     * @return bool True si tiene filtro de intereses
     */
    public function hasInterestFilter(): bool
    {
        return $this->interestFilter !== null;
    }

    /**
     * Verifica si tiene filtro de estilo de vida
     *
     * @return bool True si tiene filtro de estilo de vida
     */
    public function hasLifestyleFilter(): bool
    {
        return $this->lifestyleFilter !== null;
    }

    /**
     * Verifica si tiene filtro demográfico
     *
     * @return bool True si tiene filtro demográfico
     */
    public function hasDemographicFilter(): bool
    {
        return $this->demographicFilter !== null;
    }

    /**
     * Verifica si tiene filtro personalizado
     *
     * @return bool True si tiene filtro personalizado
     */
    public function hasCustomFilter(): bool
    {
        return $this->customFilter !== null;
    }

    /**
     * Verifica si tiene filtros premium
     *
     * @return bool True si tiene filtros premium
     */
    public function hasPremiumFilters(): bool
    {
        return !empty($this->premiumFilters);
    }

    /**
     * Obtiene el número total de filtros activos
     *
     * @return int Número de filtros activos
     */
    public function getFilterCount(): int
    {
        $count = 0;
        
        if ($this->hasAgeFilter()) $count++;
        if ($this->hasDistanceFilter()) $count++;
        if ($this->hasInterestFilter()) $count++;
        if ($this->hasLifestyleFilter()) $count++;
        if ($this->hasDemographicFilter()) $count++;
        if ($this->hasCustomFilter()) $count++;
        if ($this->hasPremiumFilters()) $count++;
        
        return $count;
    }

    /**
     * Obtiene la lista de filtros activos
     *
     * @return array Lista de nombres de filtros activos
     */
    public function getActiveFilters(): array
    {
        $filters = [];
        
        if ($this->hasAgeFilter()) $filters[] = 'age';
        if ($this->hasDistanceFilter()) $filters[] = 'distance';
        if ($this->hasInterestFilter()) $filters[] = 'interests';
        if ($this->hasLifestyleFilter()) $filters[] = 'lifestyle';
        if ($this->hasDemographicFilter()) $filters[] = 'demographics';
        if ($this->hasCustomFilter()) $filters[] = 'custom';
        if ($this->hasPremiumFilters()) $filters[] = 'premium';
        
        return $filters;
    }

    /**
     * Verifica si los criterios están vacíos
     *
     * @return bool True si están vacíos
     */
    public function isEmpty(): bool
    {
        return $this->getFilterCount() === 0 && 
               empty($this->genderPreferences) && 
               empty($this->basicConstraints) && 
               empty($this->premiumFilters);
    }

    /**
     * Verifica si los criterios son básicos
     *
     * @return bool True si son básicos
     */
    public function isBasic(): bool
    {
        return $this->getFilterCount() <= 2 && 
               !$this->hasPremiumFilters() && 
               !$this->hasCustomFilter();
    }

    /**
     * Verifica si los criterios son estrictos
     *
     * @return bool True si son estrictos
     */
    public function isStrict(): bool
    {
        return $this->getFilterCount() >= 4 || 
               $this->hasPremiumFilters() || 
               $this->hasCustomFilter();
    }

    /**
     * Verifica si los criterios son flexibles
     *
     * @return bool True si son flexibles
     */
    public function isFlexible(): bool
    {
        return $this->getFilterCount() <= 2 && 
               !$this->hasPremiumFilters() && 
               !$this->hasCustomFilter() &&
               empty($this->basicConstraints);
    }

    /**
     * Obtiene la categoría de los criterios
     *
     * @return string Categoría (basic, strict, flexible, premium, custom, balanced)
     */
    public function getCategory(): string
    {
        if ($this->isEmpty()) {
            return 'open';
        }

        if ($this->hasCustomFilter()) {
            return 'custom';
        }

        if ($this->hasPremiumFilters()) {
            return 'premium';
        }

        if ($this->isStrict()) {
            return 'strict';
        }

        if ($this->isFlexible()) {
            return 'flexible';
        }

        if ($this->isBasic()) {
            return 'basic';
        }

        return 'balanced';
    }

    /**
     * Obtiene el nivel de selectividad de los criterios
     * (1 = muy selectivo, 5 = muy permisivo)
     *
     * @return int Nivel de selectividad
     */
    public function getSelectivityLevel(): int
    {
        if ($this->isEmpty()) {
            return 5; // Muy permisivo
        }

        $baseLevel = 3; // Balanceado base
        
        // Ajustar por número de filtros
        $filterCount = $this->getFilterCount();
        if ($filterCount >= 5) {
            $baseLevel -= 2; // Muy selectivo
        } elseif ($filterCount >= 3) {
            $baseLevel -= 1; // Selectivo
        } elseif ($filterCount <= 1) {
            $baseLevel += 1; // Permisivo
        }

        // Ajustar por filtros premium
        if ($this->hasPremiumFilters()) {
            $baseLevel -= 1;
        }

        // Ajustar por filtro personalizado
        if ($this->hasCustomFilter()) {
            $baseLevel -= 1;
        }

        return max(1, min(5, $baseLevel));
    }

    /**
     * Verifica si estos criterios son iguales a otros
     *
     * @param self $other Otros criterios para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->ageRange === $other->ageRange &&
               $this->distanceFilter === $other->distanceFilter &&
               $this->interestFilter === $other->interestFilter &&
               $this->lifestyleFilter === $other->lifestyleFilter &&
               $this->demographicFilter === $other->demographicFilter &&
               $this->customFilter === $other->customFilter &&
               $this->genderPreferences === $other->genderPreferences &&
               $this->basicConstraints === $other->basicConstraints &&
               $this->premiumFilters === $other->premiumFilters;
    }

    /**
     * Obtiene la descripción legible de los criterios
     *
     * @return string Descripción de los criterios
     */
    public function getDescription(): string
    {
        if ($this->isEmpty()) {
            return 'Sin filtros específicos';
        }

        $parts = [];

        if ($this->hasAgeFilter()) {
            $parts[] = "Edad: {$this->ageRange}";
        }

        if ($this->hasDistanceFilter()) {
            $parts[] = "Distancia: {$this->distanceFilter}";
        }

        if ($this->hasInterestFilter()) {
            $parts[] = "Intereses: {$this->interestFilter}";
        }

        if ($this->hasLifestyleFilter()) {
            $parts[] = "Estilo de vida: {$this->lifestyleFilter}";
        }

        if ($this->hasDemographicFilter()) {
            $parts[] = "Demografía: {$this->demographicFilter}";
        }

        if ($this->hasCustomFilter()) {
            $parts[] = "Personalizado: {$this->customFilter}";
        }

        if ($this->hasPremiumFilters()) {
            $parts[] = "Premium: " . count($this->premiumFilters) . " filtros";
        }

        if (!empty($this->genderPreferences)) {
            $parts[] = "Género: " . implode(', ', $this->genderPreferences);
        }

        return implode('; ', $parts);
    }

    /**
     * Convierte los criterios a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'age_range' => $this->ageRange?->toArray(),
            'distance_filter' => $this->distanceFilter?->toArray(),
            'interest_filter' => $this->interestFilter?->toArray(),
            'lifestyle_filter' => $this->lifestyleFilter?->toArray(),
            'demographic_filter' => $this->demographicFilter?->toArray(),
            'custom_filter' => $this->customFilter?->toArray(),
            'gender_preferences' => $this->genderPreferences,
            'basic_constraints' => $this->basicConstraints,
            'premium_filters' => $this->premiumFilters,
            'category' => $this->getCategory(),
            'selectivity_level' => $this->getSelectivityLevel(),
            'filter_count' => $this->getFilterCount(),
            'active_filters' => $this->getActiveFilters(),
            'description' => $this->getDescription(),
            'is_basic' => $this->isBasic(),
            'is_strict' => $this->isStrict(),
            'is_flexible' => $this->isFlexible(),
            'is_empty' => $this->isEmpty(),
        ];
    }

    /**
     * Representación en string de los criterios
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
            'age_range' => $this->ageRange,
            'distance_filter' => $this->distanceFilter,
            'interest_filter' => $this->interestFilter,
            'lifestyle_filter' => $this->lifestyleFilter,
            'demographic_filter' => $this->demographicFilter,
            'custom_filter' => $this->customFilter,
            'gender_preferences' => $this->genderPreferences,
            'basic_constraints' => $this->basicConstraints,
            'premium_filters' => $this->premiumFilters
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
            'filter_count' => $this->getFilterCount(),
            'active_filters' => $this->getActiveFilters(),
            'category' => $this->getCategory(),
            'selectivity_level' => $this->getSelectivityLevel(),
            'is_basic' => $this->isBasic(),
            'is_strict' => $this->isStrict(),
            'is_flexible' => $this->isFlexible(),
        ];
    }

    /**
     * Valida que los criterios sean válidos
     *
     * @param array $criteria Criterios a validar
     * @throws InvalidArgumentException Si los criterios no son válidos
     */
    private static function validateCriteria(array $criteria): void
    {
        // Validar preferencias de género
        if (isset($criteria['gender_preferences']) || isset($criteria['gender'])) {
            $genderPrefs = $criteria['gender_preferences'] ?? $criteria['gender'] ?? [];
            if (!is_array($genderPrefs)) {
                throw new InvalidArgumentException(
                    "Las preferencias de género deben ser un array"
                );
            }
            
            $validGenders = ['male', 'female', 'non_binary', 'other'];
            foreach ($genderPrefs as $gender) {
                if (!in_array($gender, $validGenders)) {
                    throw new InvalidArgumentException(
                        "Género inválido: {$gender}. Válidos: " . implode(', ', $validGenders)
                    );
                }
            }
        }

        // Validar restricciones básicas
        if (isset($criteria['basic_constraints']) || isset($criteria['constraints'])) {
            $constraints = $criteria['basic_constraints'] ?? $criteria['constraints'] ?? [];
            if (!is_array($constraints)) {
                throw new InvalidArgumentException(
                    "Las restricciones básicas deben ser un array"
                );
            }
        }

        // Validar filtros premium
        if (isset($criteria['premium_filters']) || isset($criteria['premium'])) {
            $premium = $criteria['premium_filters'] ?? $criteria['premium'] ?? [];
            if (!is_array($premium)) {
                throw new InvalidArgumentException(
                    "Los filtros premium deben ser un array"
                );
            }
        }
    }

    /**
     * Verifica si un valor es un FilterCriteria válido
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
     * Crea FilterCriteria desde un valor mixto
     *
     * @param mixed $value Valor de los criterios
     * @return self Nueva instancia de FilterCriteria
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
            'FilterCriteria solo puede crearse desde array o instancia de FilterCriteria, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para estos criterios (útil para cache keys)
     *
     * @return string Hash único de los criterios
     */
    public function getHash(): string
    {
        $data = [
            'age' => $this->ageRange?->toArray(),
            'distance' => $this->distanceFilter?->toArray(),
            'interests' => $this->interestFilter?->toArray(),
            'lifestyle' => $this->lifestyleFilter?->toArray(),
            'demographics' => $this->demographicFilter?->toArray(),
            'custom' => $this->customFilter?->toArray(),
            'gender' => $this->genderPreferences,
            'constraints' => $this->basicConstraints,
            'premium' => $this->premiumFilters,
        ];

        return 'filter_criteria_' . substr(md5(serialize($data)), 0, 12);
    }

    /**
     * Verifica si estos criterios son compatibles con el sistema de analytics
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si estos criterios son compatibles con el sistema de caché
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
