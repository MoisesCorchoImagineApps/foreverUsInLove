<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * DiscoveryCardId Value Object
 * 
 * Representa el identificador único de una tarjeta de descubrimiento en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del identificador de tarjeta de descubrimiento en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para identificar
 * tarjetas de descubrimiento, exploración de perfiles y algoritmos de matching
 * en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores enteros positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos DiscoveryCardId con el mismo valor son iguales
 * - Matching-specific: Específico para el dominio de matching y descubrimiento
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class DiscoveryCardId implements JsonSerializable
{
    /**
     * El valor del identificador de tarjeta de descubrimiento
     */
    private readonly int $value;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(int $value)
    {
        $this->value = $value;
    }

    /**
     * Factory method para crear una nueva instancia de DiscoveryCardId
     *
     * @param int $value Valor del identificador
     * @return self Nueva instancia de DiscoveryCardId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromInt(int $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear DiscoveryCardId desde string
     *
     * @param string $value Valor del identificador como string
     * @return self Nueva instancia de DiscoveryCardId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "DiscoveryCardId debe ser un número entero válido, se recibió: {$value}"
            );
        }

        $intValue = (int) $value;
        return self::fromInt($intValue);
    }

    /**
     * Factory method para crear DiscoveryCardId desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el ID
     * @param string $key Clave del array que contiene el ID
     * @return self Nueva instancia de DiscoveryCardId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromArray(array $data, string $key = 'id'): self
    {
        if (!isset($data[$key])) {
            throw new InvalidArgumentException(
                "Clave '{$key}' no encontrada en los datos proporcionados"
            );
        }

        return self::fromInt($data[$key]);
    }

    /**
     * Factory method para generar un nuevo DiscoveryCardId único
     * 
     * @return self Nueva instancia con ID único generado
     */
    public static function generate(): self
    {
        return new self(mt_rand(1000000, 9999999));
    }

    /**
     * Obtiene el valor entero del identificador
     *
     * @return int Valor del identificador
     */
    public function toInt(): int
    {
        return $this->value;
    }

    /**
     * Obtiene el valor como string
     *
     * @return string Valor del identificador como string
     */
    public function toString(): string
    {
        return (string) $this->value;
    }

    /**
     * Verifica si este DiscoveryCardId es igual a otro
     *
     * @param self $other Otro DiscoveryCardId para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este DiscoveryCardId es mayor que otro
     *
     * @param self $other Otro DiscoveryCardId para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este DiscoveryCardId es menor que otro
     *
     * @param self $other Otro DiscoveryCardId para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Valida que el valor sea un entero positivo válido
     *
     * @param int $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(int $value): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException(
                "DiscoveryCardId debe ser un número entero positivo, se recibió: {$value}"
            );
        }

        if ($value > 999999999) {
            throw new InvalidArgumentException(
                "DiscoveryCardId debe ser menor a 999999999, se recibió: {$value}"
            );
        }
    }

    /**
     * Representación string del DiscoveryCardId
     *
     * @return string Representación string del identificador
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Implementación de JsonSerializable para serialización JSON
     *
     * @return int Valor del identificador para JSON
     */
    public function jsonSerialize(): int
    {
        return $this->value;
    }

    /**
     * Información de debug del DiscoveryCardId
     *
     * @return array<string, mixed> Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'value' => $this->value,
            'type' => 'DiscoveryCardId'
        ];
    }
}
