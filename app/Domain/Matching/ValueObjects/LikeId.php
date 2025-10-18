<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

/**
 * LikeId Value Object
 * 
 * Representa el identificador único de un like en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del identificador de like en toda la aplicación.
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
final class LikeId implements JsonSerializable
{
    private readonly string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Generar un nuevo LikeId único
     */
    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    /**
     * Crear LikeId desde string
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Obtener el valor como string
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Verificar si dos LikeId son iguales
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
     * Serialización para JSON
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    /**
     * Validar el valor del ID
     */
    private static function validate(string $value): void
    {
        if (empty($value)) {
            throw new InvalidArgumentException('LikeId no puede estar vacío');
        }

        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException('LikeId debe ser un UUID válido');
        }
    }
}
