<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * DiscoveryBoost Value Object
 * 
 * Representa la configuración de boost de descubrimiento en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de la configuración de boost de descubrimiento en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para manejar
 * configuraciones de boost que aumentan temporalmente la visibilidad de perfiles
 * en las sesiones de descubrimiento de otros usuarios.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores válidos para cada propiedad
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos DiscoveryBoost con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y boost de descubrimiento
 * 
 * Propiedades soportadas:
 * - type: Tipo de boost (premium, standard, super, ultra)
 * - multiplier: Multiplicador de visibilidad (1.0 - 10.0)
 * - target_modes: Modos de descubrimiento objetivo para el boost
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class DiscoveryBoost implements JsonSerializable
{
    /**
     * Tipos válidos de boost
     */
    public const TYPE_STANDARD = 'standard';
    public const TYPE_PREMIUM = 'premium';
    public const TYPE_SUPER = 'super';
    public const TYPE_ULTRA = 'ultra';

    /**
     * Lista de todos los tipos válidos
     */
    private const VALID_TYPES = [
        self::TYPE_STANDARD,
        self::TYPE_PREMIUM,
        self::TYPE_SUPER,
        self::TYPE_ULTRA,
    ];

    /**
     * El tipo de boost
     */
    private readonly string $type;

    /**
     * El multiplicador de visibilidad
     */
    private readonly float $multiplier;

    /**
     * Los modos de descubrimiento objetivo
     */
    private readonly array $targetModes;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(
        string $type,
        float $multiplier,
        array $targetModes
    ) {
        $this->type = $type;
        $this->multiplier = $multiplier;
        $this->targetModes = $targetModes;
    }

    /**
     * Factory method para crear una nueva instancia de DiscoveryBoost desde array
     *
     * @param array $data Datos de configuración de boost
     * @return self Nueva instancia de DiscoveryBoost
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function fromArray(array $data): self
    {
        // Validar y procesar tipo
        $type = $data['type'] ?? self::TYPE_STANDARD;
        self::validateType($type);

        // Validar y procesar multiplicador
        $multiplier = $data['multiplier'] ?? 2.0;
        self::validateMultiplier($multiplier);

        // Validar y procesar modos objetivo
        $targetModes = $data['target_modes'] ?? ['standard'];
        self::validateTargetModes($targetModes);

        return new self($type, $multiplier, $targetModes);
    }

    /**
     * Factory method para crear DiscoveryBoost desde string JSON
     *
     * @param string $json Datos JSON de configuración de boost
     * @return self Nueva instancia de DiscoveryBoost
     * @throws InvalidArgumentException Si el JSON no es válido
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                "JSON inválido para DiscoveryBoost: " . json_last_error_msg()
            );
        }

        return self::fromArray($data);
    }

    /**
     * Factory methods para crear boosts predefinidos comunes
     */
    public static function standard(): self
    {
        return new self(
            self::TYPE_STANDARD,
            2.0,
            ['standard']
        );
    }

    public static function premium(): self
    {
        return new self(
            self::TYPE_PREMIUM,
            3.5,
            ['standard', 'explore']
        );
    }

    public static function super(): self
    {
        return new self(
            self::TYPE_SUPER,
            5.0,
            ['standard', 'explore', 'local']
        );
    }

    public static function ultra(): self
    {
        return new self(
            self::TYPE_ULTRA,
            7.5,
            ['standard', 'explore', 'local', 'interest']
        );
    }

    /**
     * Obtiene el tipo de boost
     *
     * @return string Tipo de boost
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Obtiene el multiplicador de visibilidad
     *
     * @return float Multiplicador de visibilidad
     */
    public function getMultiplier(): float
    {
        return $this->multiplier;
    }

    /**
     * Obtiene los modos de descubrimiento objetivo
     *
     * @return array Modos objetivo
     */
    public function getTargetModes(): array
    {
        return $this->targetModes;
    }

    /**
     * Verifica si este DiscoveryBoost es igual a otro
     *
     * @param self $other Otro DiscoveryBoost para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->type === $other->type &&
               abs($this->multiplier - $other->multiplier) < 0.01 &&
               $this->targetModes === $other->targetModes;
    }

    /**
     * Verifica si este DiscoveryBoost es estándar
     *
     * @return bool True si es estándar
     */
    public function isStandard(): bool
    {
        return $this->type === self::TYPE_STANDARD;
    }

    /**
     * Verifica si este DiscoveryBoost es premium
     *
     * @return bool True si es premium
     */
    public function isPremium(): bool
    {
        return $this->type === self::TYPE_PREMIUM;
    }

    /**
     * Verifica si este DiscoveryBoost es super
     *
     * @return bool True si es super
     */
    public function isSuper(): bool
    {
        return $this->type === self::TYPE_SUPER;
    }

    /**
     * Verifica si este DiscoveryBoost es ultra
     *
     * @return bool True si es ultra
     */
    public function isUltra(): bool
    {
        return $this->type === self::TYPE_ULTRA;
    }

    /**
     * Verifica si este DiscoveryBoost incluye un modo específico
     *
     * @param string $mode Modo a verificar
     * @return bool True si incluye el modo
     */
    public function includesMode(string $mode): bool
    {
        return in_array($mode, $this->targetModes, true);
    }

    /**
     * Verifica si este DiscoveryBoost es de alta potencia
     *
     * @return bool True si es de alta potencia
     */
    public function isHighPower(): bool
    {
        return $this->multiplier >= 5.0;
    }

    /**
     * Verifica si este DiscoveryBoost es de potencia media
     *
     * @return bool True si es de potencia media
     */
    public function isMediumPower(): bool
    {
        return $this->multiplier >= 2.5 && $this->multiplier < 5.0;
    }

    /**
     * Verifica si este DiscoveryBoost es de baja potencia
     *
     * @return bool True si es de baja potencia
     */
    public function isLowPower(): bool
    {
        return $this->multiplier < 2.5;
    }

    /**
     * Obtiene el nivel de potencia del boost
     * (1 = baja potencia, 5 = máxima potencia)
     *
     * @return int Nivel de potencia
     */
    public function getPowerLevel(): int
    {
        if ($this->multiplier >= 7.0) {
            return 5; // Máxima potencia
        }

        if ($this->multiplier >= 5.0) {
            return 4; // Alta potencia
        }

        if ($this->multiplier >= 3.0) {
            return 3; // Potencia media
        }

        if ($this->multiplier >= 2.0) {
            return 2; // Potencia media-baja
        }

        return 1; // Baja potencia
    }

    /**
     * Obtiene la categoría del boost
     *
     * @return string Categoría (standard, premium, super, ultra)
     */
    public function getCategory(): string
    {
        return $this->type;
    }

    /**
     * Obtiene la descripción legible del boost
     *
     * @return string Descripción del boost
     */
    public function getDescription(): string
    {
        $parts = [];

        $parts[] = ucfirst($this->type) . " Boost";
        $parts[] = "Multiplicador: {$this->multiplier}x";
        $parts[] = "Modos: " . implode(', ', $this->targetModes);

        return implode(' - ', $parts);
    }

    /**
     * Obtiene el costo estimado del boost en puntos/moneda virtual
     *
     * @return int Costo estimado
     */
    public function getEstimatedCost(): int
    {
        return match ($this->type) {
            self::TYPE_STANDARD => 100,
            self::TYPE_PREMIUM => 250,
            self::TYPE_SUPER => 500,
            self::TYPE_ULTRA => 1000,
            default => 100,
        };
    }

    /**
     * Obtiene la duración recomendada del boost en minutos
     *
     * @return int Duración recomendada en minutos
     */
    public function getRecommendedDuration(): int
    {
        return match ($this->type) {
            self::TYPE_STANDARD => 30,
            self::TYPE_PREMIUM => 60,
            self::TYPE_SUPER => 90,
            self::TYPE_ULTRA => 120,
            default => 30,
        };
    }

    /**
     * Obtiene el número de modos objetivo
     *
     * @return int Número de modos objetivo
     */
    public function getTargetModeCount(): int
    {
        return count($this->targetModes);
    }

    /**
     * Verifica si este boost es multi-modo (más de un modo objetivo)
     *
     * @return bool True si es multi-modo
     */
    public function isMultiMode(): bool
    {
        return $this->getTargetModeCount() > 1;
    }

    /**
     * Convierte el boost a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'multiplier' => $this->multiplier,
            'target_modes' => $this->targetModes,
            'category' => $this->getCategory(),
            'power_level' => $this->getPowerLevel(),
            'description' => $this->getDescription(),
            'estimated_cost' => $this->getEstimatedCost(),
            'recommended_duration' => $this->getRecommendedDuration(),
            'target_mode_count' => $this->getTargetModeCount(),
            'is_standard' => $this->isStandard(),
            'is_premium' => $this->isPremium(),
            'is_super' => $this->isSuper(),
            'is_ultra' => $this->isUltra(),
            'is_high_power' => $this->isHighPower(),
            'is_medium_power' => $this->isMediumPower(),
            'is_low_power' => $this->isLowPower(),
            'is_multi_mode' => $this->isMultiMode(),
        ];
    }

    /**
     * Representación en string del boost
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
            'type' => $this->type,
            'multiplier' => $this->multiplier,
            'target_modes' => $this->targetModes,
            'power_level' => $this->getPowerLevel(),
            'category' => $this->getCategory(),
            'estimated_cost' => $this->getEstimatedCost(),
            'recommended_duration' => $this->getRecommendedDuration(),
        ];
    }

    /**
     * Valida el tipo de boost
     *
     * @param string $type Tipo a validar
     * @throws InvalidArgumentException Si el tipo no es válido
     */
    private static function validateType(string $type): void
    {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(
                "DiscoveryBoost debe ser uno de los tipos válidos: " . implode(', ', self::VALID_TYPES) . 
                ", se recibió: {$type}"
            );
        }
    }

    /**
     * Valida el multiplicador
     *
     * @param mixed $multiplier Multiplicador a validar
     * @throws InvalidArgumentException Si el multiplicador no es válido
     */
    private static function validateMultiplier(mixed $multiplier): void
    {
        if (!is_numeric($multiplier)) {
            throw new InvalidArgumentException(
                "El multiplicador debe ser un número"
            );
        }

        $multiplier = (float) $multiplier;

        if ($multiplier < 1.0) {
            throw new InvalidArgumentException(
                "El multiplicador no puede ser menor a 1.0: {$multiplier}"
            );
        }

        if ($multiplier > 10.0) {
            throw new InvalidArgumentException(
                "El multiplicador no puede exceder 10.0: {$multiplier}"
            );
        }
    }

    /**
     * Valida los modos objetivo
     *
     * @param mixed $targetModes Modos objetivo a validar
     * @throws InvalidArgumentException Si los modos no son válidos
     */
    private static function validateTargetModes(mixed $targetModes): void
    {
        if (!is_array($targetModes)) {
            throw new InvalidArgumentException(
                "Los modos objetivo deben ser un array"
            );
        }

        if (empty($targetModes)) {
            throw new InvalidArgumentException(
                "Debe especificar al menos un modo objetivo"
            );
        }

        foreach ($targetModes as $mode) {
            if (!is_string($mode) || empty(trim($mode))) {
                throw new InvalidArgumentException(
                    "Cada modo objetivo debe ser una cadena no vacía"
                );
            }
        }

        if (count($targetModes) > 8) {
            throw new InvalidArgumentException(
                "No se pueden especificar más de 8 modos objetivo"
            );
        }
    }

    /**
     * Verifica si un valor es válido para DiscoveryBoost
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
     * Crea DiscoveryBoost desde un valor mixto
     *
     * @param mixed $value Valor del boost
     * @return self Nueva instancia de DiscoveryBoost
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
            'DiscoveryBoost solo puede crearse desde array o JSON string, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para este boost (útil para cache keys)
     *
     * @return string Hash único del boost
     */
    public function getHash(): string
    {
        $data = [
            'type' => $this->type,
            'multiplier' => $this->multiplier,
            'target_modes' => $this->targetModes,
        ];

        return 'discovery_boost_' . substr(md5(serialize($data)), 0, 12);
    }

    /**
     * Verifica si este boost es compatible con el sistema de analytics
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si este boost es compatible con el sistema de caché
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
