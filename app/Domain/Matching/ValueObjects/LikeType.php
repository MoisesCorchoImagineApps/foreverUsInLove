<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;

/**
 * LikeType Value Object
 * 
 * Representa los diferentes tipos de likes disponibles en la plataforma
 * ForeverUsInLove. Implementa el patrón Value Object para garantizar
 * la inmutabilidad y validación de los tipos de like.
 * 
 * Tipos disponibles:
 * - STANDARD: Like básico estándar
 * - SUPER_LIKE: Like premium con mayor visibilidad
 * - PREMIUM_LIKE: Like con características premium adicionales
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
final class LikeType
{
    public const STANDARD = 'standard';
    public const SUPER_LIKE = 'super_like';
    public const PREMIUM_LIKE = 'premium_like';

    private const VALID_TYPES = [
        self::STANDARD,
        self::SUPER_LIKE,
        self::PREMIUM_LIKE,
    ];

    private readonly string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Crear LikeType desde string
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Crear LikeType estándar
     */
    public static function standard(): self
    {
        return new self(self::STANDARD);
    }

    /**
     * Crear LikeType super like
     */
    public static function superLike(): self
    {
        return new self(self::SUPER_LIKE);
    }

    /**
     * Crear LikeType premium
     */
    public static function premiumLike(): self
    {
        return new self(self::PREMIUM_LIKE);
    }

    /**
     * Obtener el valor del tipo
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Verificar si es un like estándar
     */
    public function isStandard(): bool
    {
        return $this->value === self::STANDARD;
    }

    /**
     * Verificar si es un super like
     */
    public function isSuperLike(): bool
    {
        return $this->value === self::SUPER_LIKE;
    }

    /**
     * Verificar si es un like premium
     */
    public function isPremiumLike(): bool
    {
        return $this->value === self::PREMIUM_LIKE;
    }

    /**
     * Verificar si es un like premium (super like o premium like)
     */
    public function isPremium(): bool
    {
        return $this->isSuperLike() || $this->isPremiumLike();
    }

    /**
     * Obtener el nivel de prioridad del like
     */
    public function getPriority(): int
    {
        return match($this->value) {
            self::SUPER_LIKE => 3,
            self::PREMIUM_LIKE => 2,
            self::STANDARD => 1,
            default => 0
        };
    }

    /**
     * Obtener el costo en créditos del like
     */
    public function getCost(): int
    {
        return match($this->value) {
            self::SUPER_LIKE => 1,
            self::PREMIUM_LIKE => 0,
            self::STANDARD => 0,
            default => 0
        };
    }

    /**
     * Obtener el multiplicador de visibilidad
     */
    public function getVisibilityMultiplier(): float
    {
        return match($this->value) {
            self::SUPER_LIKE => 2.0,
            self::PREMIUM_LIKE => 1.5,
            self::STANDARD => 1.0,
            default => 1.0
        };
    }

    /**
     * Verificar si requiere notificación inmediata
     */
    public function requiresImmediateNotification(): bool
    {
        return $this->isPremium();
    }

    /**
     * Obtener el tiempo de expiración en horas
     */
    public function getExpirationHours(): int
    {
        return match($this->value) {
            self::SUPER_LIKE => 24,
            self::PREMIUM_LIKE => 12,
            self::STANDARD => 6,
            default => 6
        };
    }

    /**
     * Verificar si dos tipos son iguales
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
     * Validar el valor del tipo
     */
    private static function validate(string $value): void
    {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(
                "Tipo de like inválido: {$value}. Tipos válidos: " . implode(', ', self::VALID_TYPES)
            );
        }
    }
}
