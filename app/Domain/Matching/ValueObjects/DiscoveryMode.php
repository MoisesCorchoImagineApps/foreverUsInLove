<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * DiscoveryMode Value Object
 * 
 * Representa el modo de descubrimiento en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del modo de descubrimiento en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para manejar
 * los diferentes modos de descubrimiento de perfiles en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta modos válidos predefinidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos DiscoveryMode con el mismo valor son iguales
 * - Matching-specific: Específico para el dominio de matching y descubrimiento
 * 
 * Modos disponibles:
 * - standard: Descubrimiento estándar con algoritmo regular de matching
 * - explore: Modo exploración que amplía horizontes con diferentes tipos de perfiles
 * - boost: Modo premium con descubrimiento mejorado y mayor visibilidad
 * - local: Enfoque en usuarios cercanos para matching basado en ubicación
 * - global: Expande el radio de búsqueda para conexiones internacionales
 * - interest: Descubrimiento basado en hobbies e intereses compartidos
 * - second_chance: Re-descubre perfiles previamente pasados
 * - trending: Descubre perfiles populares y altamente comprometidos
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class DiscoveryMode implements JsonSerializable
{
    /**
     * Modos válidos para descubrimiento
     */
    public const STANDARD = 'standard';
    public const EXPLORE = 'explore';
    public const BOOST = 'boost';
    public const LOCAL = 'local';
    public const GLOBAL = 'global';
    public const INTEREST = 'interest';
    public const SECOND_CHANCE = 'second_chance';
    public const TRENDING = 'trending';

    /**
     * Lista de todos los modos válidos
     */
    private const VALID_MODES = [
        self::STANDARD,
        self::EXPLORE,
        self::BOOST,
        self::LOCAL,
        self::GLOBAL,
        self::INTEREST,
        self::SECOND_CHANCE,
        self::TRENDING,
    ];

    /**
     * El valor del modo de descubrimiento
     */
    private readonly string $value;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Factory method para crear una nueva instancia de DiscoveryMode
     *
     * @param string $value Valor del modo
     * @return self Nueva instancia de DiscoveryMode
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear DiscoveryMode desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el modo
     * @param string $key Clave del array que contiene el modo
     * @return self Nueva instancia de DiscoveryMode
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromArray(array $data, string $key = 'mode'): self
    {
        if (!isset($data[$key])) {
            throw new InvalidArgumentException(
                "Clave '{$key}' no encontrada en los datos proporcionados"
            );
        }

        return self::fromString($data[$key]);
    }

    /**
     * Factory methods para crear modos específicos
     */
    public static function standard(): self
    {
        return new self(self::STANDARD);
    }

    public static function explore(): self
    {
        return new self(self::EXPLORE);
    }

    public static function boost(): self
    {
        return new self(self::BOOST);
    }

    public static function local(): self
    {
        return new self(self::LOCAL);
    }

    public static function global(): self
    {
        return new self(self::GLOBAL);
    }

    public static function interest(): self
    {
        return new self(self::INTEREST);
    }

    public static function secondChance(): self
    {
        return new self(self::SECOND_CHANCE);
    }

    public static function trending(): self
    {
        return new self(self::TRENDING);
    }

    /**
     * Obtiene el valor del modo
     *
     * @return string Valor del modo
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Obtiene el valor del modo (alias para getValue)
     *
     * @return string Valor del modo
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Verifica si este DiscoveryMode es igual a otro
     *
     * @param self $other Otro DiscoveryMode para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este DiscoveryMode es estándar
     *
     * @return bool True si es estándar
     */
    public function isStandard(): bool
    {
        return $this->value === self::STANDARD;
    }

    /**
     * Verifica si este DiscoveryMode es exploración
     *
     * @return bool True si es exploración
     */
    public function isExplore(): bool
    {
        return $this->value === self::EXPLORE;
    }

    /**
     * Verifica si este DiscoveryMode es boost
     *
     * @return bool True si es boost
     */
    public function isBoost(): bool
    {
        return $this->value === self::BOOST;
    }

    /**
     * Verifica si este DiscoveryMode es local
     *
     * @return bool True si es local
     */
    public function isLocal(): bool
    {
        return $this->value === self::LOCAL;
    }

    /**
     * Verifica si este DiscoveryMode es global
     *
     * @return bool True si es global
     */
    public function isGlobal(): bool
    {
        return $this->value === self::GLOBAL;
    }

    /**
     * Verifica si este DiscoveryMode es por intereses
     *
     * @return bool True si es por intereses
     */
    public function isInterest(): bool
    {
        return $this->value === self::INTEREST;
    }

    /**
     * Verifica si este DiscoveryMode es segunda oportunidad
     *
     * @return bool True si es segunda oportunidad
     */
    public function isSecondChance(): bool
    {
        return $this->value === self::SECOND_CHANCE;
    }

    /**
     * Verifica si este DiscoveryMode es trending
     *
     * @return bool True si es trending
     */
    public function isTrending(): bool
    {
        return $this->value === self::TRENDING;
    }

    /**
     * Verifica si este DiscoveryMode es premium
     * (boost requiere características premium)
     *
     * @return bool True si es premium
     */
    public function isPremium(): bool
    {
        return $this->isBoost();
    }

    /**
     * Verifica si este DiscoveryMode es gratuito
     * (todos excepto boost son gratuitos)
     *
     * @return bool True si es gratuito
     */
    public function isFree(): bool
    {
        return !$this->isPremium();
    }

    /**
     * Verifica si este DiscoveryMode requiere ubicación
     * (local requiere datos de ubicación)
     *
     * @return bool True si requiere ubicación
     */
    public function requiresLocation(): bool
    {
        return $this->isLocal();
    }

    /**
     * Verifica si este DiscoveryMode requiere datos históricos
     * (second_chance requiere datos de interacciones pasadas)
     *
     * @return bool True si requiere datos históricos
     */
    public function requiresHistoricalData(): bool
    {
        return $this->isSecondChance();
    }

    /**
     * Verifica si este DiscoveryMode requiere datos de actividad
     * (trending requiere datos de popularidad y engagement)
     *
     * @return bool True si requiere datos de actividad
     */
    public function requiresActivityData(): bool
    {
        return $this->isTrending();
    }

    /**
     * Verifica si este DiscoveryMode es expansivo
     * (explore y global amplían criterios de búsqueda)
     *
     * @return bool True si es expansivo
     */
    public function isExpansive(): bool
    {
        return $this->isExplore() || $this->isGlobal();
    }

    /**
     * Verifica si este DiscoveryMode es específico
     * (local e interest tienen criterios específicos)
     *
     * @return bool True si es específico
     */
    public function isSpecific(): bool
    {
        return $this->isLocal() || $this->isInterest();
    }

    /**
     * Obtiene el nivel de prioridad del modo
     * (1 = alta prioridad, 5 = baja prioridad)
     *
     * @return int Nivel de prioridad
     */
    public function getPriorityLevel(): int
    {
        return match ($this->value) {
            self::BOOST => 1, // Máxima prioridad para boost premium
            self::TRENDING => 2, // Alta prioridad para trending
            self::LOCAL => 2, // Alta prioridad para local
            self::INTEREST => 3, // Prioridad media para interest
            self::EXPLORE => 3, // Prioridad media para explore
            self::SECOND_CHANCE => 4, // Prioridad media-baja para second chance
            self::GLOBAL => 4, // Prioridad media-baja para global
            self::STANDARD => 5, // Baja prioridad para standard
            default => 3,
        };
    }

    /**
     * Obtiene la categoría del modo
     *
     * @return string Categoría del modo (premium, location_based, interest_based, exploratory, historical, trending, standard)
     */
    public function getCategory(): string
    {
        return match ($this->value) {
            self::BOOST => 'premium',
            self::LOCAL => 'location_based',
            self::INTEREST => 'interest_based',
            self::EXPLORE, self::GLOBAL => 'exploratory',
            self::SECOND_CHANCE => 'historical',
            self::TRENDING => 'trending',
            self::STANDARD => 'standard',
            default => 'unknown',
        };
    }

    /**
     * Obtiene la descripción legible del modo
     *
     * @return string Descripción del modo
     */
    public function getDescription(): string
    {
        return match ($this->value) {
            self::STANDARD => 'Descubrimiento estándar con algoritmo regular de matching',
            self::EXPLORE => 'Modo exploración que amplía horizontes con diferentes tipos de perfiles',
            self::BOOST => 'Modo premium con descubrimiento mejorado y mayor visibilidad',
            self::LOCAL => 'Enfoque en usuarios cercanos para matching basado en ubicación',
            self::GLOBAL => 'Expande el radio de búsqueda para conexiones internacionales',
            self::INTEREST => 'Descubrimiento basado en hobbies e intereses compartidos',
            self::SECOND_CHANCE => 'Re-descubre perfiles previamente pasados',
            self::TRENDING => 'Descubre perfiles populares y altamente comprometidos',
            default => 'Modo desconocido',
        };
    }

    /**
     * Obtiene las características asociadas al modo
     *
     * @return array Características del modo
     */
    public function getFeatures(): array
    {
        return match ($this->value) {
            self::STANDARD => ['basic', 'compatibility_focused', 'default'],
            self::EXPLORE => ['diverse', 'novelty_focused', 'expansive'],
            self::BOOST => ['premium', 'enhanced_visibility', 'priority'],
            self::LOCAL => ['location_based', 'proximity_focused', 'real_world'],
            self::GLOBAL => ['worldwide', 'cultural_diversity', 'expansive'],
            self::INTEREST => ['interest_based', 'hobby_focused', 'shared_activities'],
            self::SECOND_CHANCE => ['historical', 'reconsideration', 'improvement_focused'],
            self::TRENDING => ['popularity_based', 'engagement_focused', 'social_proof'],
            default => [],
        };
    }

    /**
     * Verifica si este DiscoveryMode tiene una característica específica
     *
     * @param string $feature Característica a verificar
     * @return bool True si tiene la característica
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->getFeatures());
    }

    /**
     * Obtiene los pesos del algoritmo para este modo
     * (basado en MODE_WEIGHTS del DiscoveryService)
     *
     * @return array Pesos del algoritmo
     */
    public function getAlgorithmWeights(): array
    {
        return match ($this->value) {
            self::STANDARD => ['compatibility' => 0.7, 'freshness' => 0.2, 'activity' => 0.1],
            self::EXPLORE => ['diversity' => 0.5, 'compatibility' => 0.3, 'novelty' => 0.2],
            self::BOOST => ['quality' => 0.4, 'compatibility' => 0.4, 'premium_priority' => 0.2],
            self::LOCAL => ['proximity' => 0.6, 'compatibility' => 0.3, 'activity' => 0.1],
            self::GLOBAL => ['compatibility' => 0.5, 'diversity' => 0.3, 'cultural_interest' => 0.2],
            self::INTEREST => ['shared_interests' => 0.6, 'compatibility' => 0.3, 'activity' => 0.1],
            self::SECOND_CHANCE => ['improvement' => 0.4, 'time_passed' => 0.3, 'compatibility' => 0.3],
            self::TRENDING => ['popularity' => 0.5, 'engagement' => 0.3, 'compatibility' => 0.2],
            default => ['compatibility' => 0.7, 'freshness' => 0.2, 'activity' => 0.1],
        };
    }

    /**
     * Convierte el DiscoveryMode a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'description' => $this->getDescription(),
            'category' => $this->getCategory(),
            'priority_level' => $this->getPriorityLevel(),
            'features' => $this->getFeatures(),
            'algorithm_weights' => $this->getAlgorithmWeights(),
            'is_premium' => $this->isPremium(),
            'requires_location' => $this->requiresLocation(),
            'requires_historical_data' => $this->requiresHistoricalData(),
            'requires_activity_data' => $this->requiresActivityData(),
            'is_expansive' => $this->isExpansive(),
            'is_specific' => $this->isSpecific(),
        ];
    }

    /**
     * Representación en string del DiscoveryMode
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Serialización para JSON
     *
     * @return string Valor para JSON
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'value' => $this->value,
            'description' => $this->getDescription(),
            'category' => $this->getCategory(),
            'priority_level' => $this->getPriorityLevel(),
            'is_premium' => $this->isPremium(),
            'requires_location' => $this->requiresLocation(),
            'requires_historical_data' => $this->requiresHistoricalData(),
            'requires_activity_data' => $this->requiresActivityData(),
        ];
    }

    /**
     * Valida que el valor del modo sea válido
     *
     * @param string $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(string $value): void
    {
        if (!in_array($value, self::VALID_MODES, true)) {
            throw new InvalidArgumentException(
                "DiscoveryMode debe ser uno de los valores válidos: " . implode(', ', self::VALID_MODES) . 
                ", se recibió: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un DiscoveryMode válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if (is_string($value)) {
                self::validate($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea un DiscoveryMode desde un valor mixto (string, array)
     *
     * @param mixed $value Valor del modo
     * @param string $arrayKey Clave para arrays (por defecto 'mode')
     * @return self Nueva instancia de DiscoveryMode
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value, string $arrayKey = 'mode'): self
    {
        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_array($value)) {
            return self::fromArray($value, $arrayKey);
        }

        throw new InvalidArgumentException(
            'DiscoveryMode solo puede crearse desde string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Obtiene todos los modos válidos
     *
     * @return array Lista de todos los modos válidos
     */
    public static function getValidModes(): array
    {
        return self::VALID_MODES;
    }

    /**
     * Genera un hash único para este DiscoveryMode (útil para cache keys)
     *
     * @return string Hash único del DiscoveryMode
     */
    public function getHash(): string
    {
        return 'discovery_mode_' . $this->value . '_' . substr(md5($this->value), 0, 8);
    }

    /**
     * Verifica si este DiscoveryMode es compatible con el sistema de analytics
     * (todos los modos pueden generar analytics)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si este DiscoveryMode es compatible con el sistema de caché
     * (todos los modos pueden ser cacheados)
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
