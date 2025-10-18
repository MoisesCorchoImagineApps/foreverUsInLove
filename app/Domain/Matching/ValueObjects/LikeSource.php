<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;

/**
 * LikeSource Value Object
 * 
 * Representa la fuente de origen de un like en la plataforma ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de la fuente del like.
 * 
 * Fuentes disponibles:
 * - DISCOVERY_CARD: Like desde tarjeta de descubrimiento
 * - PROFILE_VIEW: Like desde vista de perfil
 * - MANUAL: Like manual directo
 * - RECOMMENDATION: Like desde recomendaciones
 * - SEARCH: Like desde búsqueda
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
final class LikeSource
{
    public const DISCOVERY_CARD = 'discovery_card';
    public const PROFILE_VIEW = 'profile_view';
    public const MANUAL = 'manual';
    public const RECOMMENDATION = 'recommendation';
    public const SEARCH = 'search';

    private const VALID_SOURCES = [
        self::DISCOVERY_CARD,
        self::PROFILE_VIEW,
        self::MANUAL,
        self::RECOMMENDATION,
        self::SEARCH,
    ];

    private readonly string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Crear LikeSource desde string
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Crear LikeSource desde tarjeta de descubrimiento
     */
    public static function discoveryCard(): self
    {
        return new self(self::DISCOVERY_CARD);
    }

    /**
     * Crear LikeSource desde vista de perfil
     */
    public static function profileView(): self
    {
        return new self(self::PROFILE_VIEW);
    }

    /**
     * Crear LikeSource manual
     */
    public static function manual(): self
    {
        return new self(self::MANUAL);
    }

    /**
     * Crear LikeSource desde recomendaciones
     */
    public static function recommendation(): self
    {
        return new self(self::RECOMMENDATION);
    }

    /**
     * Crear LikeSource desde búsqueda
     */
    public static function search(): self
    {
        return new self(self::SEARCH);
    }

    /**
     * Obtener el valor de la fuente
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Verificar si es desde tarjeta de descubrimiento
     */
    public function isDiscoveryCard(): bool
    {
        return $this->value === self::DISCOVERY_CARD;
    }

    /**
     * Verificar si es desde vista de perfil
     */
    public function isProfileView(): bool
    {
        return $this->value === self::PROFILE_VIEW;
    }

    /**
     * Verificar si es manual
     */
    public function isManual(): bool
    {
        return $this->value === self::MANUAL;
    }

    /**
     * Verificar si es desde recomendaciones
     */
    public function isRecommendation(): bool
    {
        return $this->value === self::RECOMMENDATION;
    }

    /**
     * Verificar si es desde búsqueda
     */
    public function isSearch(): bool
    {
        return $this->value === self::SEARCH;
    }

    /**
     * Obtener el peso de la fuente para algoritmos
     */
    public function getWeight(): float
    {
        return match($this->value) {
            self::DISCOVERY_CARD => 1.0,
            self::PROFILE_VIEW => 1.2,
            self::MANUAL => 1.5,
            self::RECOMMENDATION => 0.8,
            self::SEARCH => 1.1,
            default => 1.0
        };
    }

    /**
     * Verificar si dos fuentes son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Representación en string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Validar el valor de la fuente
     */
    private static function validate(string $value): void
    {
        if (!in_array($value, self::VALID_SOURCES, true)) {
            throw new InvalidArgumentException(
                "Fuente de like inválida: {$value}. Fuentes válidas: " . implode(', ', self::VALID_SOURCES)
            );
        }
    }
}
