<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * DiscoveryPreferences Value Object
 * 
 * Representa las preferencias de descubrimiento en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de las preferencias de descubrimiento en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para manejar
 * las preferencias de usuario para el descubrimiento de perfiles, incluyendo criterios
 * geográficos, demográficos y de comportamiento en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores válidos para cada preferencia
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos DiscoveryPreferences con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y descubrimiento
 * 
 * Propiedades soportadas:
 * - radius: Radio de búsqueda geográfica (DiscoveryRadius)
 * - age_range: Rango de edad [min, max] en años
 * - interests: Lista de intereses para matching basado en hobbies
 * - include_verified_only: Solo incluir perfiles verificados
 * - exclude_seen_profiles: Excluir perfiles ya vistos
 * - prioritize_active_users: Priorizar usuarios activos recientemente
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class DiscoveryPreferences implements JsonSerializable
{
    /**
     * El radio de búsqueda geográfica
     */
    private readonly DiscoveryRadius $radius;

    /**
     * El rango de edad [min, max] en años
     */
    private readonly array $ageRange;

    /**
     * Lista de intereses para matching
     */
    private readonly array $interests;

    /**
     * Solo incluir perfiles verificados
     */
    private readonly bool $includeVerifiedOnly;

    /**
     * Excluir perfiles ya vistos
     */
    private readonly bool $excludeSeenProfiles;

    /**
     * Priorizar usuarios activos recientemente
     */
    private readonly bool $prioritizeActiveUsers;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(
        DiscoveryRadius $radius,
        array $ageRange,
        array $interests,
        bool $includeVerifiedOnly,
        bool $excludeSeenProfiles,
        bool $prioritizeActiveUsers
    ) {
        $this->radius = $radius;
        $this->ageRange = $ageRange;
        $this->interests = $interests;
        $this->includeVerifiedOnly = $includeVerifiedOnly;
        $this->excludeSeenProfiles = $excludeSeenProfiles;
        $this->prioritizeActiveUsers = $prioritizeActiveUsers;
    }

    /**
     * Factory method para crear una nueva instancia de DiscoveryPreferences desde array
     *
     * @param array $data Datos de preferencias
     * @return self Nueva instancia de DiscoveryPreferences
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function fromArray(array $data): self
    {
        // Validar y procesar radio
        $radius = $data['radius'] ?? DiscoveryRadius::local();
        if (!$radius instanceof DiscoveryRadius) {
            if (is_numeric($radius)) {
                $radius = DiscoveryRadius::fromKilometers((float) $radius);
            } else {
                throw new InvalidArgumentException(
                    "El radio debe ser una instancia de DiscoveryRadius o un número válido"
                );
            }
        }

        // Validar y procesar rango de edad
        $ageRange = $data['age_range'] ?? [18, 80];
        self::validateAgeRange($ageRange);

        // Validar y procesar intereses
        $interests = $data['interests'] ?? [];
        self::validateInterests($interests);

        // Validar y procesar booleanos
        $includeVerifiedOnly = $data['include_verified_only'] ?? false;
        $excludeSeenProfiles = $data['exclude_seen_profiles'] ?? true;
        $prioritizeActiveUsers = $data['prioritize_active_users'] ?? true;

        return new self(
            $radius,
            $ageRange,
            $interests,
            $includeVerifiedOnly,
            $excludeSeenProfiles,
            $prioritizeActiveUsers
        );
    }

    /**
     * Factory method para crear DiscoveryPreferences desde string JSON
     *
     * @param string $json Datos JSON de preferencias
     * @return self Nueva instancia de DiscoveryPreferences
     * @throws InvalidArgumentException Si el JSON no es válido
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                "JSON inválido para DiscoveryPreferences: " . json_last_error_msg()
            );
        }

        return self::fromArray($data);
    }

    /**
     * Factory methods para crear preferencias predefinidas comunes
     */
    public static function local(): self
    {
        return new self(
            DiscoveryRadius::local(),
            [18, 80],
            [],
            false,
            true,
            true
        );
    }

    public static function regional(): self
    {
        return new self(
            DiscoveryRadius::regional(),
            [18, 80],
            [],
            false,
            true,
            true
        );
    }

    public static function global(): self
    {
        return new self(
            DiscoveryRadius::global(),
            [18, 80],
            [],
            false,
            true,
            true
        );
    }

    public static function verifiedOnly(): self
    {
        return new self(
            DiscoveryRadius::local(),
            [18, 80],
            [],
            true,
            true,
            true
        );
    }

    public static function withInterests(array $interests): self
    {
        self::validateInterests($interests);
        return new self(
            DiscoveryRadius::local(),
            [18, 80],
            $interests,
            false,
            true,
            true
        );
    }

    /**
     * Obtiene el radio de búsqueda
     *
     * @return DiscoveryRadius Radio de búsqueda
     */
    public function getRadius(): DiscoveryRadius
    {
        return $this->radius;
    }

    /**
     * Obtiene el rango de edad
     *
     * @return array Rango de edad [min, max]
     */
    public function getAgeRange(): array
    {
        return $this->ageRange;
    }

    /**
     * Obtiene la edad mínima
     *
     * @return int Edad mínima
     */
    public function getMinAge(): int
    {
        return $this->ageRange[0];
    }

    /**
     * Obtiene la edad máxima
     *
     * @return int Edad máxima
     */
    public function getMaxAge(): int
    {
        return $this->ageRange[1];
    }

    /**
     * Obtiene la lista de intereses
     *
     * @return array Lista de intereses
     */
    public function getInterests(): array
    {
        return $this->interests;
    }

    /**
     * Verifica si solo incluye perfiles verificados
     *
     * @return bool True si solo incluye verificados
     */
    public function isIncludeVerifiedOnly(): bool
    {
        return $this->includeVerifiedOnly;
    }

    /**
     * Verifica si excluye perfiles ya vistos
     *
     * @return bool True si excluye perfiles vistos
     */
    public function isExcludeSeenProfiles(): bool
    {
        return $this->excludeSeenProfiles;
    }

    /**
     * Verifica si prioriza usuarios activos
     *
     * @return bool True si prioriza usuarios activos
     */
    public function isPrioritizeActiveUsers(): bool
    {
        return $this->prioritizeActiveUsers;
    }

    /**
     * Convierte las preferencias a ExplorationCriteria
     *
     * @return ExplorationCriteria Criterios de exploración
     */
    public function toExplorationCriteria(): ExplorationCriteria
    {
        return ExplorationCriteria::fromArray([
            'expand_age_range' => 5,
            'expand_radius' => $this->radius->getKilometers() * 2,
            'include_different_interests' => true,
            'base_preferences' => $this->toArray()
        ]);
    }

    /**
     * Verifica si estas preferencias son iguales a otras
     *
     * @param self $other Otras preferencias para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->radius->equals($other->radius) &&
               $this->ageRange === $other->ageRange &&
               $this->interests === $other->interests &&
               $this->includeVerifiedOnly === $other->includeVerifiedOnly &&
               $this->excludeSeenProfiles === $other->excludeSeenProfiles &&
               $this->prioritizeActiveUsers === $other->prioritizeActiveUsers;
    }

    /**
     * Verifica si estas preferencias son locales (radio pequeño)
     *
     * @return bool True si son locales
     */
    public function isLocal(): bool
    {
        return $this->radius->isLocal();
    }

    /**
     * Verifica si estas preferencias son regionales (radio medio)
     *
     * @return bool True si son regionales
     */
    public function isRegional(): bool
    {
        return $this->radius->isRegional();
    }

    /**
     * Verifica si estas preferencias son globales (radio grande)
     *
     * @return bool True si son globales
     */
    public function isGlobal(): bool
    {
        return $this->radius->isGlobal();
    }

    /**
     * Verifica si estas preferencias tienen intereses específicos
     *
     * @return bool True si tiene intereses específicos
     */
    public function hasSpecificInterests(): bool
    {
        return !empty($this->interests);
    }

    /**
     * Verifica si estas preferencias son restrictivas
     *
     * @return bool True si son restrictivas
     */
    public function isRestrictive(): bool
    {
        return $this->includeVerifiedOnly || 
               $this->excludeSeenProfiles || 
               $this->hasSpecificInterests();
    }

    /**
     * Verifica si estas preferencias son expansivas
     *
     * @return bool True si son expansivas
     */
    public function isExpansive(): bool
    {
        return $this->isGlobal() && 
               !$this->includeVerifiedOnly && 
               !$this->hasSpecificInterests();
    }

    /**
     * Obtiene el nivel de selectividad de las preferencias
     * (1 = muy selectivo, 5 = muy permisivo)
     *
     * @return int Nivel de selectividad
     */
    public function getSelectivityLevel(): int
    {
        $level = 3; // Base neutral

        // Ajustar por radio
        if ($this->isLocal()) {
            $level -= 1; // Más selectivo
        } elseif ($this->isGlobal()) {
            $level += 1; // Menos selectivo
        }

        // Ajustar por verificados
        if ($this->includeVerifiedOnly) {
            $level -= 1; // Más selectivo
        }

        // Ajustar por intereses específicos
        if ($this->hasSpecificInterests()) {
            $level -= 1; // Más selectivo
        }

        // Ajustar por exclusión de vistos
        if ($this->excludeSeenProfiles) {
            $level -= 1; // Más selectivo
        }

        return max(1, min(5, $level));
    }

    /**
     * Obtiene la categoría de las preferencias
     *
     * @return string Categoría (local, regional, global, restrictive, expansive, balanced)
     */
    public function getCategory(): string
    {
        if ($this->isRestrictive()) {
            return 'restrictive';
        }

        if ($this->isExpansive()) {
            return 'expansive';
        }

        if ($this->isLocal()) {
            return 'local';
        }

        if ($this->isRegional()) {
            return 'regional';
        }

        if ($this->isGlobal()) {
            return 'global';
        }

        return 'balanced';
    }

    /**
     * Obtiene la descripción legible de las preferencias
     *
     * @return string Descripción de las preferencias
     */
    public function getDescription(): string
    {
        $parts = [];

        $parts[] = "Radio: {$this->radius->toString()}";
        $parts[] = "Edad: {$this->getMinAge()}-{$this->getMaxAge()} años";

        if ($this->hasSpecificInterests()) {
            $parts[] = "Intereses: " . implode(', ', $this->interests);
        }

        if ($this->includeVerifiedOnly) {
            $parts[] = "Solo verificados";
        }

        if ($this->excludeSeenProfiles) {
            $parts[] = "Excluir vistos";
        }

        if ($this->prioritizeActiveUsers) {
            $parts[] = "Priorizar activos";
        }

        return implode(', ', $parts);
    }

    /**
     * Convierte las preferencias a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'radius' => $this->radius->toArray(),
            'age_range' => $this->ageRange,
            'interests' => $this->interests,
            'include_verified_only' => $this->includeVerifiedOnly,
            'exclude_seen_profiles' => $this->excludeSeenProfiles,
            'prioritize_active_users' => $this->prioritizeActiveUsers,
            'category' => $this->getCategory(),
            'selectivity_level' => $this->getSelectivityLevel(),
            'description' => $this->getDescription(),
            'is_local' => $this->isLocal(),
            'is_regional' => $this->isRegional(),
            'is_global' => $this->isGlobal(),
            'has_specific_interests' => $this->hasSpecificInterests(),
            'is_restrictive' => $this->isRestrictive(),
            'is_expansive' => $this->isExpansive(),
        ];
    }

    /**
     * Representación en string de las preferencias
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
            'radius' => $this->radius->getKilometers(),
            'age_range' => $this->ageRange,
            'interests_count' => count($this->interests),
            'include_verified_only' => $this->includeVerifiedOnly,
            'exclude_seen_profiles' => $this->excludeSeenProfiles,
            'prioritize_active_users' => $this->prioritizeActiveUsers,
            'category' => $this->getCategory(),
            'selectivity_level' => $this->getSelectivityLevel(),
        ];
    }

    /**
     * Valida el rango de edad
     *
     * @param array $ageRange Rango de edad a validar
     * @throws InvalidArgumentException Si el rango no es válido
     */
    private static function validateAgeRange(array $ageRange): void
    {
        if (count($ageRange) !== 2) {
            throw new InvalidArgumentException(
                "El rango de edad debe tener exactamente 2 elementos [min, max]"
            );
        }

        [$min, $max] = $ageRange;

        if (!is_int($min) || !is_int($max)) {
            throw new InvalidArgumentException(
                "Los valores del rango de edad deben ser enteros"
            );
        }

        if ($min < 18 || $min > 100) {
            throw new InvalidArgumentException(
                "La edad mínima debe estar entre 18 y 100 años, se recibió: {$min}"
            );
        }

        if ($max < 18 || $max > 100) {
            throw new InvalidArgumentException(
                "La edad máxima debe estar entre 18 y 100 años, se recibió: {$max}"
            );
        }

        if ($min > $max) {
            throw new InvalidArgumentException(
                "La edad mínima no puede ser mayor que la máxima: {$min} > {$max}"
            );
        }
    }

    /**
     * Valida la lista de intereses
     *
     * @param array $interests Lista de intereses a validar
     * @throws InvalidArgumentException Si la lista no es válida
     */
    private static function validateInterests(array $interests): void
    {
        if (!is_array($interests)) {
            throw new InvalidArgumentException(
                "Los intereses deben ser un array"
            );
        }

        foreach ($interests as $interest) {
            if (!is_string($interest) || empty(trim($interest))) {
                throw new InvalidArgumentException(
                    "Cada interés debe ser una cadena no vacía"
                );
            }
        }

        if (count($interests) > 20) {
            throw new InvalidArgumentException(
                "No se pueden especificar más de 20 intereses"
            );
        }
    }

    /**
     * Verifica si un valor es válido para DiscoveryPreferences
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
     * Crea DiscoveryPreferences desde un valor mixto
     *
     * @param mixed $value Valor de las preferencias
     * @return self Nueva instancia de DiscoveryPreferences
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
            'DiscoveryPreferences solo puede crearse desde array o JSON string, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para estas preferencias (útil para cache keys)
     *
     * @return string Hash único de las preferencias
     */
    public function getHash(): string
    {
        $data = [
            'radius' => $this->radius->getKilometers(),
            'age_range' => $this->ageRange,
            'interests' => $this->interests,
            'verified_only' => $this->includeVerifiedOnly,
            'exclude_seen' => $this->excludeSeenProfiles,
            'prioritize_active' => $this->prioritizeActiveUsers,
        ];

        return 'discovery_prefs_' . substr(md5(serialize($data)), 0, 12);
    }

    /**
     * Verifica si estas preferencias son compatibles con el sistema de analytics
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si estas preferencias son compatibles con el sistema de caché
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
