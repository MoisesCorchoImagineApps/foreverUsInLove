<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * DemographicFilter Value Object
 * 
 * Representa un filtro demográfico en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de filtros demográficos en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para manejar
 * filtros basados en características demográficas como educación, ingresos,
 * ocupación, etnia, etc. en búsquedas y matching.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta configuraciones demográficas válidas
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos DemographicFilter con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y filtros demográficos
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class DemographicFilter implements JsonSerializable
{
    /**
     * Niveles de educación permitidos
     */
    private readonly array $educationLevels;

    /**
     * Rangos de ingresos permitidos
     */
    private readonly array $incomeRanges;

    /**
     * Ocupaciones permitidas
     */
    private readonly array $occupations;

    /**
     * Etnias permitidas
     */
    private readonly array $ethnicities;

    /**
     * Religiones permitidas
     */
    private readonly array $religions;

    /**
     * Idiomas permitidos
     */
    private readonly array $languages;

    /**
     * Estados civiles permitidos
     */
    private readonly array $maritalStatuses;

    /**
     * Preferencias de hijos
     */
    private readonly array $childrenPreferences;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(
        array $educationLevels = [],
        array $incomeRanges = [],
        array $occupations = [],
        array $ethnicities = [],
        array $religions = [],
        array $languages = [],
        array $maritalStatuses = [],
        array $childrenPreferences = []
    ) {
        $this->educationLevels = $educationLevels;
        $this->incomeRanges = $incomeRanges;
        $this->occupations = $occupations;
        $this->ethnicities = $ethnicities;
        $this->religions = $religions;
        $this->languages = $languages;
        $this->maritalStatuses = $maritalStatuses;
        $this->childrenPreferences = $childrenPreferences;
    }

    /**
     * Factory method para crear una nueva instancia de DemographicFilter
     *
     * @param array $demographics Array con configuraciones demográficas
     * @return self Nueva instancia de DemographicFilter
     * @throws InvalidArgumentException Si las configuraciones no son válidas
     */
    public static function create(array $demographics = []): self
    {
        self::validateDemographics($demographics);
        
        return new self(
            $demographics['education'] ?? $demographics['education_levels'] ?? [],
            $demographics['income'] ?? $demographics['income_ranges'] ?? [],
            $demographics['occupation'] ?? $demographics['occupations'] ?? [],
            $demographics['ethnicity'] ?? $demographics['ethnicities'] ?? [],
            $demographics['religion'] ?? $demographics['religions'] ?? [],
            $demographics['language'] ?? $demographics['languages'] ?? [],
            $demographics['marital_status'] ?? $demographics['marital_statuses'] ?? [],
            $demographics['children'] ?? $demographics['children_preferences'] ?? []
        );
    }

    /**
     * Factory method para crear DemographicFilter desde array
     *
     * @param array $filter Array con configuración del filtro
     * @return self Nueva instancia de DemographicFilter
     * @throws InvalidArgumentException Si el array no es válido
     */
    public static function fromArray(array $filter): self
    {
        return self::create($filter);
    }

    /**
     * Factory methods para filtros demográficos predefinidos comunes
     */
    public static function professional(): self
    {
        return new self(
            ['bachelors', 'masters', 'phd'], // Educación superior
            ['middle', 'upper_middle', 'high'], // Ingresos medios-altos
            ['professional', 'business', 'tech', 'healthcare'], // Profesiones
            [], // Sin restricción de etnia
            [], // Sin restricción de religión
            ['spanish', 'english'], // Idiomas principales
            ['single', 'divorced'], // Estado civil
            ['open', 'wants', 'has'] // Abierto a hijos
        );
    }

    public static function educated(): self
    {
        return new self(
            ['bachelors', 'masters', 'phd'], // Educación superior
            [], // Sin restricción de ingresos
            [], // Sin restricción de ocupación
            [], // Sin restricción de etnia
            [], // Sin restricción de religión
            [], // Sin restricción de idioma
            [], // Sin restricción de estado civil
            [] // Sin restricción de hijos
        );
    }

    public static function familyOriented(): self
    {
        return new self(
            [], // Sin restricción de educación
            [], // Sin restricción de ingresos
            [], // Sin restricción de ocupación
            [], // Sin restricción de etnia
            [], // Sin restricción de religión
            [], // Sin restricción de idioma
            ['single', 'divorced'], // Estado civil
            ['wants', 'has'] // Quiere o tiene hijos
        );
    }

    public static function open(): self
    {
        return new self(); // Sin restricciones demográficas
    }

    /**
     * Obtiene los niveles de educación permitidos
     *
     * @return array Niveles de educación
     */
    public function getEducationLevels(): array
    {
        return $this->educationLevels;
    }

    /**
     * Obtiene los rangos de ingresos permitidos
     *
     * @return array Rangos de ingresos
     */
    public function getIncomeRanges(): array
    {
        return $this->incomeRanges;
    }

    /**
     * Obtiene las ocupaciones permitidas
     *
     * @return array Ocupaciones
     */
    public function getOccupations(): array
    {
        return $this->occupations;
    }

    /**
     * Obtiene las etnias permitidas
     *
     * @return array Etnias
     */
    public function getEthnicities(): array
    {
        return $this->ethnicities;
    }

    /**
     * Obtiene las religiones permitidas
     *
     * @return array Religiones
     */
    public function getReligions(): array
    {
        return $this->religions;
    }

    /**
     * Obtiene los idiomas permitidos
     *
     * @return array Idiomas
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * Obtiene los estados civiles permitidos
     *
     * @return array Estados civiles
     */
    public function getMaritalStatuses(): array
    {
        return $this->maritalStatuses;
    }

    /**
     * Obtiene las preferencias de hijos permitidas
     *
     * @return array Preferencias de hijos
     */
    public function getChildrenPreferences(): array
    {
        return $this->childrenPreferences;
    }

    /**
     * Verifica si el filtro está vacío (sin restricciones)
     *
     * @return bool True si está vacío
     */
    public function isEmpty(): bool
    {
        return empty($this->educationLevels) &&
               empty($this->incomeRanges) &&
               empty($this->occupations) &&
               empty($this->ethnicities) &&
               empty($this->religions) &&
               empty($this->languages) &&
               empty($this->maritalStatuses) &&
               empty($this->childrenPreferences);
    }

    /**
     * Obtiene el número total de restricciones definidas
     *
     * @return int Número de restricciones definidas
     */
    public function getRestrictionCount(): int
    {
        $count = 0;
        $restrictions = [
            $this->educationLevels,
            $this->incomeRanges,
            $this->occupations,
            $this->ethnicities,
            $this->religions,
            $this->languages,
            $this->maritalStatuses,
            $this->childrenPreferences
        ];

        foreach ($restrictions as $restriction) {
            if (!empty($restriction)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Verifica si el filtro es profesional
     *
     * @return bool True si se enfoca en aspectos profesionales
     */
    public function isProfessional(): bool
    {
        return !empty($this->educationLevels) || !empty($this->incomeRanges) || !empty($this->occupations);
    }

    /**
     * Verifica si el filtro es cultural
     *
     * @return bool True si se enfoca en aspectos culturales
     */
    public function isCultural(): bool
    {
        return !empty($this->ethnicities) || !empty($this->religions) || !empty($this->languages);
    }

    /**
     * Verifica si el filtro es familiar
     *
     * @return bool True si se enfoca en aspectos familiares
     */
    public function isFamilyOriented(): bool
    {
        return !empty($this->maritalStatuses) || !empty($this->childrenPreferences);
    }

    /**
     * Verifica si el filtro es restrictivo
     *
     * @return bool True si tiene muchas restricciones
     */
    public function isRestrictive(): bool
    {
        return $this->getRestrictionCount() >= 4;
    }

    /**
     * Verifica si el filtro es permisivo
     *
     * @return bool True si tiene pocas restricciones
     */
    public function isPermissive(): bool
    {
        return $this->getRestrictionCount() <= 1;
    }

    /**
     * Obtiene la categoría del filtro
     *
     * @return string Categoría (professional, cultural, family_oriented, restrictive, permissive, balanced, open)
     */
    public function getCategory(): string
    {
        if ($this->isEmpty()) {
            return 'open';
        }

        if ($this->isRestrictive()) {
            return 'restrictive';
        }

        if ($this->isPermissive()) {
            return 'permissive';
        }

        if ($this->isProfessional()) {
            return 'professional';
        }

        if ($this->isCultural()) {
            return 'cultural';
        }

        if ($this->isFamilyOriented()) {
            return 'family_oriented';
        }

        return 'balanced';
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

        $count = $this->getRestrictionCount();
        
        if ($count >= 6) {
            return 1; // Muy selectivo
        } elseif ($count >= 4) {
            return 2; // Selectivo
        } elseif ($count >= 2) {
            return 3; // Balanceado
        } else {
            return 4; // Permisivo
        }
    }

    /**
     * Verifica si un perfil demográfico cumple con este filtro
     *
     * @param array $candidateDemographics Demografía del candidato
     * @return bool True si cumple con el filtro
     */
    public function matches(array $candidateDemographics): bool
    {
        $restrictions = [
            'education' => $this->educationLevels,
            'income' => $this->incomeRanges,
            'occupation' => $this->occupations,
            'ethnicity' => $this->ethnicities,
            'religion' => $this->religions,
            'language' => $this->languages,
            'marital_status' => $this->maritalStatuses,
            'children' => $this->childrenPreferences
        ];

        foreach ($restrictions as $key => $allowedValues) {
            if (!empty($allowedValues) && isset($candidateDemographics[$key])) {
                $candidateValue = $candidateDemographics[$key];
                
                // Manejar arrays de valores del candidato
                if (is_array($candidateValue)) {
                    if (empty(array_intersect($allowedValues, $candidateValue))) {
                        return false;
                    }
                } else {
                    if (!in_array($candidateValue, $allowedValues)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Verifica si este filtro es igual a otro
     *
     * @param self $other Otro filtro para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->educationLevels === $other->educationLevels &&
               $this->incomeRanges === $other->incomeRanges &&
               $this->occupations === $other->occupations &&
               $this->ethnicities === $other->ethnicities &&
               $this->religions === $other->religions &&
               $this->languages === $other->languages &&
               $this->maritalStatuses === $other->maritalStatuses &&
               $this->childrenPreferences === $other->childrenPreferences;
    }

    /**
     * Obtiene la descripción legible del filtro
     *
     * @return string Descripción del filtro
     */
    public function getDescription(): string
    {
        if ($this->isEmpty()) {
            return 'Sin restricciones demográficas específicas';
        }

        $descriptions = [];

        if (!empty($this->educationLevels)) {
            $descriptions[] = 'Educación: ' . implode(', ', $this->educationLevels);
        }

        if (!empty($this->incomeRanges)) {
            $descriptions[] = 'Ingresos: ' . implode(', ', $this->incomeRanges);
        }

        if (!empty($this->occupations)) {
            $descriptions[] = 'Ocupación: ' . implode(', ', $this->occupations);
        }

        if (!empty($this->ethnicities)) {
            $descriptions[] = 'Etnia: ' . implode(', ', $this->ethnicities);
        }

        if (!empty($this->religions)) {
            $descriptions[] = 'Religión: ' . implode(', ', $this->religions);
        }

        if (!empty($this->languages)) {
            $descriptions[] = 'Idioma: ' . implode(', ', $this->languages);
        }

        if (!empty($this->maritalStatuses)) {
            $descriptions[] = 'Estado civil: ' . implode(', ', $this->maritalStatuses);
        }

        if (!empty($this->childrenPreferences)) {
            $descriptions[] = 'Hijos: ' . implode(', ', $this->childrenPreferences);
        }

        return implode('; ', $descriptions);
    }

    /**
     * Convierte el filtro a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'education_levels' => $this->educationLevels,
            'income_ranges' => $this->incomeRanges,
            'occupations' => $this->occupations,
            'ethnicities' => $this->ethnicities,
            'religions' => $this->religions,
            'languages' => $this->languages,
            'marital_statuses' => $this->maritalStatuses,
            'children_preferences' => $this->childrenPreferences,
            'category' => $this->getCategory(),
            'selectivity_level' => $this->getSelectivityLevel(),
            'restriction_count' => $this->getRestrictionCount(),
            'description' => $this->getDescription(),
            'is_professional' => $this->isProfessional(),
            'is_cultural' => $this->isCultural(),
            'is_family_oriented' => $this->isFamilyOriented(),
            'is_restrictive' => $this->isRestrictive(),
            'is_permissive' => $this->isPermissive(),
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
            'education' => $this->educationLevels,
            'income' => $this->incomeRanges,
            'occupation' => $this->occupations,
            'ethnicity' => $this->ethnicities,
            'religion' => $this->religions,
            'language' => $this->languages,
            'marital_status' => $this->maritalStatuses,
            'children' => $this->childrenPreferences
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
            'restriction_count' => $this->getRestrictionCount(),
            'category' => $this->getCategory(),
            'selectivity_level' => $this->getSelectivityLevel(),
            'is_professional' => $this->isProfessional(),
            'is_cultural' => $this->isCultural(),
            'is_family_oriented' => $this->isFamilyOriented(),
        ];
    }

    /**
     * Valida que las configuraciones demográficas sean válidas
     *
     * @param array $demographics Configuraciones a validar
     * @throws InvalidArgumentException Si las configuraciones no son válidas
     */
    private static function validateDemographics(array $demographics): void
    {
        $validValues = [
            'education' => ['high_school', 'associate', 'bachelors', 'masters', 'phd', 'professional'],
            'income' => ['low', 'lower_middle', 'middle', 'upper_middle', 'high', 'very_high'],
            'occupation' => ['student', 'unemployed', 'retired', 'service', 'sales', 'office', 'professional', 'business', 'tech', 'healthcare', 'education', 'arts', 'sports', 'other'],
            'ethnicity' => ['white', 'black', 'hispanic', 'asian', 'native_american', 'pacific_islander', 'mixed', 'other', 'prefer_not_to_say'],
            'religion' => ['christian', 'catholic', 'protestant', 'jewish', 'muslim', 'hindu', 'buddhist', 'atheist', 'agnostic', 'spiritual', 'other', 'prefer_not_to_say'],
            'language' => ['spanish', 'english', 'french', 'german', 'italian', 'portuguese', 'chinese', 'japanese', 'korean', 'arabic', 'russian', 'other'],
            'marital_status' => ['single', 'divorced', 'widowed', 'separated'],
            'children' => ['none', 'has', 'wants', 'open', 'doesnt_want']
        ];

        foreach ($demographics as $key => $values) {
            if (!empty($values)) {
                // Normalizar clave
                $normalizedKey = str_replace(['_levels', '_ranges', '_preferences', '_statuses'], '', $key);
                
                if (isset($validValues[$normalizedKey])) {
                    $valuesArray = is_array($values) ? $values : [$values];
                    
                    foreach ($valuesArray as $value) {
                        if (!in_array($value, $validValues[$normalizedKey])) {
                            throw new InvalidArgumentException(
                                "Valor inválido para {$key}: {$value}. Valores válidos: " . implode(', ', $validValues[$normalizedKey])
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Verifica si un valor es un DemographicFilter válido
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
     * Crea DemographicFilter desde un valor mixto
     *
     * @param mixed $value Valor del filtro demográfico
     * @return self Nueva instancia de DemographicFilter
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
            'DemographicFilter solo puede crearse desde array o instancia de DemographicFilter, se recibió: ' . gettype($value)
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
            'education' => $this->educationLevels,
            'income' => $this->incomeRanges,
            'occupation' => $this->occupations,
            'ethnicity' => $this->ethnicities,
            'religion' => $this->religions,
            'language' => $this->languages,
            'marital_status' => $this->maritalStatuses,
            'children' => $this->childrenPreferences
        ];

        return 'demographic_filter_' . substr(md5(serialize($data)), 0, 12);
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
