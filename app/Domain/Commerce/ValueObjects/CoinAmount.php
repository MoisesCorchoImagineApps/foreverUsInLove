<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * CoinAmount Value Object
 * 
 * Representa la cantidad de monedas virtuales en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de las cantidades de monedas en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para manejar
 * cantidades de monedas virtuales en transacciones, balances, compras y recompensas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores enteros positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos CoinAmount con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y monedas virtuales
 * - Precision handling: Maneja cantidades enteras para evitar problemas de precisión
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class CoinAmount implements JsonSerializable
{
    /**
     * El valor de la cantidad de monedas
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
     * Factory method para crear una nueva instancia de CoinAmount
     *
     * @param int $value Valor de la cantidad de monedas
     * @return self Nueva instancia de CoinAmount
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromInt(int $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear CoinAmount desde string
     *
     * @param string $value Valor de la cantidad como string
     * @return self Nueva instancia de CoinAmount
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value) || !ctype_digit($value)) {
            throw new InvalidArgumentException(
                "CoinAmount debe ser un número entero positivo válido, se recibió: {$value}"
            );
        }

        $intValue = (int) $value;
        return self::fromInt($intValue);
    }

    /**
     * Factory method para crear CoinAmount desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen la cantidad
     * @param string $key Clave del array que contiene la cantidad
     * @return self Nueva instancia de CoinAmount
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromArray(array $data, string $key = 'amount'): self
    {
        if (!isset($data[$key])) {
            throw new InvalidArgumentException(
                "Clave '{$key}' no encontrada en los datos proporcionados"
            );
        }

        return self::fromInt($data[$key]);
    }

    /**
     * Factory method para crear CoinAmount desde float (redondeando)
     *
     * @param float $value Valor como float
     * @return self Nueva instancia de CoinAmount
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromFloat(float $value): self
    {
        if ($value < 0) {
            throw new InvalidArgumentException(
                "CoinAmount debe ser un número no negativo, se recibió: {$value}"
            );
        }

        return new self((int) round($value));
    }

    /**
     * Obtiene el valor entero de la cantidad de monedas
     *
     * @return int Valor de la cantidad
     */
    public function toInt(): int
    {
        return $this->value;
    }

    /**
     * Obtiene el valor como string formateado
     *
     * @param bool $withSeparator Si incluir separadores de miles
     * @return string Valor de la cantidad como string formateado
     */
    public function toString(bool $withSeparator = true): string
    {
        if ($withSeparator) {
            return number_format($this->value, 0, '.', ',');
        }
        
        return (string) $this->value;
    }

    /**
     * Verifica si este CoinAmount es igual a otro
     *
     * @param self $other Otro CoinAmount para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este CoinAmount es mayor que otro
     *
     * @param self $other Otro CoinAmount para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este CoinAmount es menor que otro
     *
     * @param self $other Otro CoinAmount para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Verifica si este CoinAmount es mayor o igual que otro
     *
     * @param self $other Otro CoinAmount para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Verifica si este CoinAmount es menor o igual que otro
     *
     * @param self $other Otro CoinAmount para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * Verifica si este CoinAmount está en un rango específico
     *
     * @param self $min CoinAmount mínimo (inclusivo)
     * @param self $max CoinAmount máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Suma otra cantidad a esta cantidad
     *
     * @param self $other Otra cantidad a sumar
     * @return self Nueva cantidad con la suma
     */
    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    /**
     * Resta otra cantidad de esta cantidad
     *
     * @param self $other Otra cantidad a restar
     * @return self Nueva cantidad con la resta
     * @throws InvalidArgumentException Si el resultado sería negativo
     */
    public function subtract(self $other): self
    {
        $result = $this->value - $other->value;
        if ($result < 0) {
            throw new InvalidArgumentException(
                "El resultado de la resta no puede ser negativo: {$result}"
            );
        }
        return new self($result);
    }

    /**
     * Multiplica esta cantidad por un factor
     *
     * @param float $factor Factor de multiplicación
     * @return self Nueva cantidad multiplicada
     * @throws InvalidArgumentException Si el factor es negativo
     */
    public function multiply(float $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException(
                "El factor de multiplicación no puede ser negativo: {$factor}"
            );
        }
        return new self((int) round($this->value * $factor));
    }

    /**
     * Divide esta cantidad por un divisor
     *
     * @param float $divisor Divisor
     * @return self Nueva cantidad dividida
     * @throws InvalidArgumentException Si el divisor es negativo o cero
     */
    public function divide(float $divisor): self
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException(
                "El divisor debe ser positivo: {$divisor}"
            );
        }
        return new self((int) round($this->value / $divisor));
    }

    /**
     * Aplica un porcentaje de bonificación
     *
     * @param float $bonusPercent Porcentaje de bonificación (0-100)
     * @return self Nueva cantidad con bonificación aplicada
     */
    public function applyBonus(float $bonusPercent): self
    {
        if ($bonusPercent < 0) {
            throw new InvalidArgumentException(
                "El porcentaje de bonificación no puede ser negativo: {$bonusPercent}"
            );
        }

        $bonusAmount = $this->value * ($bonusPercent / 100);
        return new self($this->value + (int) round($bonusAmount));
    }

    /**
     * Verifica si este CoinAmount es cero
     *
     * @return bool True si es cero
     */
    public function isZero(): bool
    {
        return $this->value === 0;
    }

    /**
     * Verifica si este CoinAmount es pequeño (≤ 100)
     *
     * @return bool True si es pequeño
     */
    public function isSmall(): bool
    {
        return $this->value <= 100;
    }

    /**
     * Verifica si este CoinAmount es moderado (101-1000)
     *
     * @return bool True si es moderado
     */
    public function isModerate(): bool
    {
        return $this->value > 100 && $this->value <= 1000;
    }

    /**
     * Verifica si este CoinAmount es grande (1001-5000)
     *
     * @return bool True si es grande
     */
    public function isLarge(): bool
    {
        return $this->value > 1000 && $this->value <= 5000;
    }

    /**
     * Verifica si este CoinAmount es muy grande (> 5000)
     *
     * @return bool True si es muy grande
     */
    public function isVeryLarge(): bool
    {
        return $this->value > 5000;
    }

    /**
     * Obtiene la categoría de la cantidad basada en el valor
     *
     * @return string Categoría de la cantidad (zero, small, moderate, large, very_large)
     */
    public function getAmountCategory(): string
    {
        if ($this->isZero()) {
            return 'zero';
        }

        if ($this->isSmall()) {
            return 'small';
        }

        if ($this->isModerate()) {
            return 'moderate';
        }

        if ($this->isLarge()) {
            return 'large';
        }

        if ($this->isVeryLarge()) {
            return 'very_large';
        }

        return 'custom';
    }

    /**
     * Convierte el CoinAmount a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'formatted' => $this->toString(),
            'category' => $this->getAmountCategory(),
            'is_zero' => $this->isZero(),
            'is_small' => $this->isSmall(),
            'is_moderate' => $this->isModerate(),
            'is_large' => $this->isLarge(),
            'is_very_large' => $this->isVeryLarge(),
        ];
    }

    /**
     * Representación en string del CoinAmount
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
     * @return int Valor para JSON
     */
    public function jsonSerialize(): int
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
            'formatted' => $this->toString(),
            'category' => $this->getAmountCategory(),
        ];
    }

    /**
     * Valida que el valor de la cantidad sea válido
     *
     * @param int $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(int $value): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(
                "CoinAmount debe ser un número no negativo, se recibió: {$value}"
            );
        }

        // Validar que no exceda un límite razonable (ej: 1 millón)
        if ($value > 1000000) {
            throw new InvalidArgumentException(
                "CoinAmount excede el valor máximo permitido (1,000,000): {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un CoinAmount válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if (is_int($value)) {
                self::validate($value);
                return true;
            }

            if (is_string($value) && ctype_digit($value)) {
                self::validate((int) $value);
                return true;
            }

            if (is_float($value) && $value >= 0) {
                self::validate((int) round($value));
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea un CoinAmount desde un valor mixto (int, float, string, array)
     *
     * @param mixed $value Valor de la cantidad
     * @param string $arrayKey Clave para arrays (por defecto 'amount')
     * @return self Nueva instancia de CoinAmount
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value, string $arrayKey = 'amount'): self
    {
        if (is_int($value)) {
            return self::fromInt($value);
        }

        if (is_float($value)) {
            return self::fromFloat($value);
        }

        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_array($value)) {
            return self::fromArray($value, $arrayKey);
        }

        throw new InvalidArgumentException(
            'CoinAmount solo puede crearse desde int, float, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Verifica si este CoinAmount corresponde a una cantidad con características específicas
     * (útil para filtros y búsquedas)
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['category'])) {
            if ($this->getAmountCategory() !== $criteria['category']) {
                return false;
            }
        }

        if (isset($criteria['zero_only']) && $criteria['zero_only']) {
            if (!$this->isZero()) {
                return false;
            }
        }

        if (isset($criteria['small_only']) && $criteria['small_only']) {
            if (!$this->isSmall()) {
                return false;
            }
        }

        if (isset($criteria['large_only']) && $criteria['large_only']) {
            if (!$this->isLarge()) {
                return false;
            }
        }

        if (isset($criteria['max_amount'])) {
            if ($this->value > $criteria['max_amount']) {
                return false;
            }
        }

        if (isset($criteria['min_amount'])) {
            if ($this->value < $criteria['min_amount']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del CoinAmount
     *
     * @return array Estadísticas de la cantidad
     */
    public function getStats(): array
    {
        return [
            'value' => $this->value,
            'formatted' => $this->toString(),
            'category' => $this->getAmountCategory(),
            'is_zero' => $this->isZero(),
            'is_small' => $this->isSmall(),
            'is_moderate' => $this->isModerate(),
            'is_large' => $this->isLarge(),
            'is_very_large' => $this->isVeryLarge(),
        ];
    }

    /**
     * Genera un hash único para este CoinAmount (útil para cache keys)
     *
     * @return string Hash único del CoinAmount
     */
    public function getHash(): string
    {
        return 'coin_amount_' . $this->value . '_' . substr(md5((string) $this->value), 0, 8);
    }

    /**
     * Verifica si este CoinAmount es compatible con el sistema de analytics
     * (cantidades que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Todas las cantidades pueden generar analytics
        return true;
    }

    /**
     * Verifica si este CoinAmount es compatible con el sistema de transacciones
     * (cantidades que pueden generar transacciones)
     *
     * @return bool True si es compatible con transacciones
     */
    public function isTransactionCompatible(): bool
    {
        // Todas las cantidades pueden generar transacciones (incluso cero)
        return true;
    }

    /**
     * Obtiene la prioridad de procesamiento de la cantidad
     * (útil para colas de procesamiento)
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        if ($this->isVeryLarge()) {
            return 1; // Alta prioridad para cantidades muy grandes
        }

        if ($this->isLarge()) {
            return 2; // Alta prioridad para cantidades grandes
        }

        if ($this->isModerate()) {
            return 3; // Prioridad media para cantidades moderadas
        }

        if ($this->isSmall()) {
            return 4; // Prioridad media-baja para cantidades pequeñas
        }

        return 5; // Baja prioridad para cantidades cero
    }
}
