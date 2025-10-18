<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * ExplorationCriteria Value Object
 * 
 * Representa los criterios de exploración en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de los criterios de exploración en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para manejar
 * parámetros de exploración que amplían los criterios de búsqueda estándar para
 * descubrir perfiles fuera de las preferencias típicas del usuario.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores válidos para cada criterio
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos ExplorationCriteria con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y exploración
 * 
 * Propiedades soportadas:
 * - expand_age_range: Años adicionales para expandir el rango de edad
 * - expand_radius: Kilómetros adicionales para expandir el radio de búsqueda
 * - include_different_interests: Incluir perfiles con intereses diferentes
 * - base_preferences: Preferencias base para la exploración
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class ExplorationCriteria implements JsonSerializable
{
    /**
     * Años adicionales para expandir el rango de edad
     */
    private readonly int $expandAgeRange;

    /**
     * Kilómetros adicionales para expandir el radio de búsqueda
     */
    private readonly float $expandRadius;

    /**
     * Incluir perfiles con intereses diferentes
     */
    private readonly bool $includeDifferentInterests;

    /**
     * Preferencias base para la exploración
     */
    private readonly array $basePreferences;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(
        int $expandAgeRange,
        float $expandRadius,
        bool $includeDifferentInterests,
        array $basePreferences
    ) {
        $this->expandAgeRange = $expandAgeRange;
        $this->expandRadius = $expandRadius;
        $this->includeDifferentInterests = $includeDifferentInterests;
        $this->basePreferences = $basePreferences;
    }

    /**
     * Factory method para crear una nueva instancia de ExplorationCriteria desde array
     *
     * @param array $data Datos de criterios de exploración
     * @return self Nueva instancia de ExplorationCriteria
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function fromArray(array $data): self
    {
        // Validar y procesar expansión de edad
        $expandAgeRange = $data['expand_age_range'] ?? 5;
        self::validateExpandAgeRange($expandAgeRange);

        // Validar y procesar expansión de radio
        $expandRadius = $data['expand_radius'] ?? 25.0;
        self::validateExpandRadius($expandRadius);

        // Validar y procesar intereses diferentes
        $includeDifferentInterests = $data['include_different_interests'] ?? true;

        // Validar y procesar preferencias base
        $basePreferences = $data['base_preferences'] ?? [];
        self::validateBasePreferences($basePreferences);

        return new self(
            $expandAgeRange,
            $expandRadius,
            $includeDifferentInterests,
            $basePreferences
        );
    }

    /**
     * Factory method para crear ExplorationCriteria desde string JSON
     *
     * @param string $json Datos JSON de criterios de exploración
     * @return self Nueva instancia de ExplorationCriteria
     * @throws InvalidArgumentException Si el JSON no es válido
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                "JSON inválido para ExplorationCriteria: " . json_last_error_msg()
            );
        }

        return self::fromArray($data);
    }

    /**
     * Factory methods para crear criterios predefinidos comunes
     */
    public static function conservative(): self
    {
        return new self(
            3, // 3 años adicionales
            10.0, // 10 km adicionales
            false, // No incluir intereses diferentes
            []
        );
    }

    public static function moderate(): self
    {
        return new self(
            5, // 5 años adicionales
            25.0, // 25 km adicionales
            true, // Incluir intereses diferentes
            []
        );
    }

    public static function adventurous(): self
    {
        return new self(
            10, // 10 años adicionales
            50.0, // 50 km adicionales
            true, // Incluir intereses diferentes
            []
        );
    }

    public static function extreme(): self
    {
        return new self(
            15, // 15 años adicionales
            100.0, // 100 km adicionales
            true, // Incluir intereses diferentes
            []
        );
    }

    /**
     * Obtiene la expansión del rango de edad en años
     *
     * @return int Años adicionales para expandir el rango de edad
     */
    public function getExpandAgeRange(): int
    {
        return $this->expandAgeRange;
    }

    /**
     * Obtiene la expansión del radio en kilómetros
     *
     * @return float Kilómetros adicionales para expandir el radio
     */
    public function getExpandRadius(): float
    {
        return $this->expandRadius;
    }

    /**
     * Verifica si incluye perfiles con intereses diferentes
     *
     * @return bool True si incluye intereses diferentes
     */
    public function isIncludeDifferentInterests(): bool
    {
        return $this->includeDifferentInterests;
    }

    /**
     * Obtiene las preferencias base para la exploración
     *
     * @return array Preferencias base
     */
    public function getBasePreferences(): array
    {
        return $this->basePreferences;
    }

    /**
     * Verifica si estos criterios son iguales a otros
     *
     * @param self $other Otros criterios para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->expandAgeRange === $other->expandAgeRange &&
               abs($this->expandRadius - $other->expandRadius) < 0.01 &&
               $this->includeDifferentInterests === $other->includeDifferentInterests &&
               $this->basePreferences === $other->basePreferences;
    }

    /**
     * Verifica si estos criterios son conservadores
     *
     * @return bool True si son conservadores
     */
    public function isConservative(): bool
    {
        return $this->expandAgeRange <= 3 && 
               $this->expandRadius <= 15.0 && 
               !$this->includeDifferentInterests;
    }

    /**
     * Verifica si estos criterios son moderados
     *
     * @return bool True si son moderados
     */
    public function isModerate(): bool
    {
        return $this->expandAgeRange <= 8 && 
               $this->expandRadius <= 35.0 && 
               $this->includeDifferentInterests;
    }

    /**
     * Verifica si estos criterios son aventureros
     *
     * @return bool True si son aventureros
     */
    public function isAdventurous(): bool
    {
        return $this->expandAgeRange <= 12 && 
               $this->expandRadius <= 75.0 && 
               $this->includeDifferentInterests;
    }

    /**
     * Verifica si estos criterios son extremos
     *
     * @return bool True si son extremos
     */
    public function isExtreme(): bool
    {
        return $this->expandAgeRange > 12 || 
               $this->expandRadius > 75.0;
    }

    /**
     * Obtiene el nivel de exploración de los criterios
     * (1 = conservador, 5 = extremo)
     *
     * @return int Nivel de exploración
     */
    public function getExplorationLevel(): int
    {
        if ($this->isConservative()) {
            return 1;
        }

        if ($this->isModerate()) {
            return 2;
        }

        if ($this->isAdventurous()) {
            return 3;
        }

        if ($this->isExtreme()) {
            return 4;
        }

        return 5; // Ultra extremo
    }

    /**
     * Obtiene la categoría de los criterios
     *
     * @return string Categoría (conservative, moderate, adventurous, extreme, ultra_extreme)
     */
    public function getCategory(): string
    {
        if ($this->isConservative()) {
            return 'conservative';
        }

        if ($this->isModerate()) {
            return 'moderate';
        }

        if ($this->isAdventurous()) {
            return 'adventurous';
        }

        if ($this->isExtreme()) {
            return 'extreme';
        }

        return 'ultra_extreme';
    }

    /**
     * Obtiene la descripción legible de los criterios
     *
     * @return string Descripción de los criterios
     */
    public function getDescription(): string
    {
        $parts = [];

        $parts[] = "Expansión edad: +{$this->expandAgeRange} años";
        $parts[] = "Expansión radio: +{$this->expandRadius} km";

        if ($this->includeDifferentInterests) {
            $parts[] = "Incluye intereses diferentes";
        } else {
            $parts[] = "Solo intereses similares";
        }

        if (!empty($this->basePreferences)) {
            $parts[] = "Con preferencias base";
        }

        return implode(', ', $parts);
    }

    /**
     * Calcula el rango de edad expandido basado en un rango base
     *
     * @param array $baseAgeRange Rango de edad base [min, max]
     * @return array Rango de edad expandido [min, max]
     */
    public function calculateExpandedAgeRange(array $baseAgeRange): array
    {
        [$minAge, $maxAge] = $baseAgeRange;
        
        $expandedMin = max(18, $minAge - $this->expandAgeRange);
        $expandedMax = min(100, $maxAge + $this->expandAgeRange);
        
        return [$expandedMin, $expandedMax];
    }

    /**
     * Calcula el radio expandido basado en un radio base
     *
     * @param float $baseRadius Radio base en kilómetros
     * @return float Radio expandido en kilómetros
     */
    public function calculateExpandedRadius(float $baseRadius): float
    {
        return $baseRadius + $this->expandRadius;
    }

    /**
     * Convierte los criterios a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'expand_age_range' => $this->expandAgeRange,
            'expand_radius' => $this->expandRadius,
            'include_different_interests' => $this->includeDifferentInterests,
            'base_preferences' => $this->basePreferences,
            'category' => $this->getCategory(),
            'exploration_level' => $this->getExplorationLevel(),
            'description' => $this->getDescription(),
            'is_conservative' => $this->isConservative(),
            'is_moderate' => $this->isModerate(),
            'is_adventurous' => $this->isAdventurous(),
            'is_extreme' => $this->isExtreme(),
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
        return $this->toArray();
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'expand_age_range' => $this->expandAgeRange,
            'expand_radius' => $this->expandRadius,
            'include_different_interests' => $this->includeDifferentInterests,
            'base_preferences_count' => count($this->basePreferences),
            'category' => $this->getCategory(),
            'exploration_level' => $this->getExplorationLevel(),
        ];
    }

    /**
     * Valida la expansión del rango de edad
     *
     * @param mixed $expandAgeRange Expansión del rango de edad a validar
     * @throws InvalidArgumentException Si la expansión no es válida
     */
    private static function validateExpandAgeRange(mixed $expandAgeRange): void
    {
        if (!is_int($expandAgeRange)) {
            throw new InvalidArgumentException(
                "La expansión del rango de edad debe ser un entero"
            );
        }

        if ($expandAgeRange < 0) {
            throw new InvalidArgumentException(
                "La expansión del rango de edad no puede ser negativa: {$expandAgeRange}"
            );
        }

        if ($expandAgeRange > 20) {
            throw new InvalidArgumentException(
                "La expansión del rango de edad no puede exceder 20 años: {$expandAgeRange}"
            );
        }
    }

    /**
     * Valida la expansión del radio
     *
     * @param mixed $expandRadius Expansión del radio a validar
     * @throws InvalidArgumentException Si la expansión no es válida
     */
    private static function validateExpandRadius(mixed $expandRadius): void
    {
        if (!is_numeric($expandRadius)) {
            throw new InvalidArgumentException(
                "La expansión del radio debe ser un número"
            );
        }

        $expandRadius = (float) $expandRadius;

        if ($expandRadius < 0) {
            throw new InvalidArgumentException(
                "La expansión del radio no puede ser negativa: {$expandRadius}"
            );
        }

        if ($expandRadius > 200) {
            throw new InvalidArgumentException(
                "La expansión del radio no puede exceder 200 km: {$expandRadius}"
            );
        }
    }

    /**
     * Valida las preferencias base
     *
     * @param mixed $basePreferences Preferencias base a validar
     * @throws InvalidArgumentException Si las preferencias no son válidas
     */
    private static function validateBasePreferences(mixed $basePreferences): void
    {
        if (!is_array($basePreferences)) {
            throw new InvalidArgumentException(
                "Las preferencias base deben ser un array"
            );
        }
    }

    /**
     * Verifica si un valor es válido para ExplorationCriteria
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
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
     * Crea ExplorationCriteria desde un valor mixto
     *
     * @param mixed $value Valor de los criterios
     * @return self Nueva instancia de ExplorationCriteria
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value): self
    {
        if (is_array($value)) {
            return self::fromArray($value);
        }

        if (is_string($value)) {
            return self::fromJson($value);
        }

        throw new InvalidArgumentException(
            'ExplorationCriteria solo puede crearse desde array o JSON string, se recibió: ' . gettype($value)
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
            'expand_age_range' => $this->expandAgeRange,
            'expand_radius' => $this->expandRadius,
            'include_different_interests' => $this->includeDifferentInterests,
            'base_preferences' => $this->basePreferences,
        ];

        return 'exploration_criteria_' . substr(md5(serialize($data)), 0, 12);
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
