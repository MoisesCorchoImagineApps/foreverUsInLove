<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Amount Value Object
 * 
 * Representa un monto monetario en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de los montos en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para manejar
 * montos de pagos, precios, y transacciones financieras.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta montos válidos (positivos, con decimales apropiados)
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos Amount con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y pagos
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class Amount implements JsonSerializable
{
    /**
     * El valor del monto en centavos para evitar problemas de precisión de punto flotante
     */
    private readonly int $cents;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(int $cents)
    {
        $this->cents = $cents;
    }

    /**
     * Factory method para crear Amount desde float
     *
     * @param float $amount Monto en unidades principales
     * @return self Nueva instancia de Amount
     * @throws InvalidArgumentException Si el monto no es válido
     */
    public static function fromFloat(float $amount): self
    {
        self::validate($amount);
        $cents = (int) round($amount * 100);
        return new self($cents);
    }

    /**
     * Factory method para crear Amount desde int (centavos)
     *
     * @param int $cents Monto en centavos
     * @return self Nueva instancia de Amount
     * @throws InvalidArgumentException Si el monto no es válido
     */
    public static function fromCents(int $cents): self
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Amount no puede ser negativo');
        }
        return new self($cents);
    }

    /**
     * Factory method para crear Amount desde string
     *
     * @param string $amount Monto como string
     * @return self Nueva instancia de Amount
     * @throws InvalidArgumentException Si el monto no es válido
     */
    public static function fromString(string $amount): self
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Amount debe ser un número válido');
        }
        
        $floatAmount = (float) $amount;
        return self::fromFloat($floatAmount);
    }

    /**
     * Factory method para crear Amount desde array
     *
     * @param array $data Datos que contienen el monto
     * @param string $key Clave del array que contiene el monto
     * @return self Nueva instancia de Amount
     * @throws InvalidArgumentException Si el monto no es válido
     */
    public static function fromArray(array $data, string $key = 'amount'): self
    {
        if (!isset($data[$key])) {
            throw new InvalidArgumentException(
                "Clave '{$key}' no encontrada en los datos proporcionados"
            );
        }

        $value = $data[$key];
        
        if (is_float($value)) {
            return self::fromFloat($value);
        }
        
        if (is_int($value)) {
            return self::fromCents($value);
        }
        
        if (is_string($value)) {
            return self::fromString($value);
        }

        throw new InvalidArgumentException(
            'Amount debe ser float, int o string, se recibió: ' . gettype($value)
        );
    }

    /**
     * Factory method para crear Amount cero
     *
     * @return self Nueva instancia de Amount con valor cero
     */
    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Obtiene el monto como float
     *
     * @return float Monto en unidades principales
     */
    public function toFloat(): float
    {
        return $this->cents / 100.0;
    }

    /**
     * Obtiene el monto en centavos
     *
     * @return int Monto en centavos
     */
    public function toCents(): int
    {
        return $this->cents;
    }

    /**
     * Obtiene el monto como string
     *
     * @return string Monto como string
     */
    public function toString(): string
    {
        return (string) $this->toFloat();
    }

    /**
     * Verifica si este Amount es igual a otro
     *
     * @param self $other Otro Amount para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    /**
     * Verifica si este Amount es mayor que otro
     *
     * @param self $other Otro Amount para comparar
     * @return bool True si es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    /**
     * Verifica si este Amount es menor que otro
     *
     * @param self $other Otro Amount para comparar
     * @return bool True si es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->cents < $other->cents;
    }

    /**
     * Verifica si este Amount es mayor o igual que otro
     *
     * @param self $other Otro Amount para comparar
     * @return bool True si es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->cents >= $other->cents;
    }

    /**
     * Verifica si este Amount es menor o igual que otro
     *
     * @param self $other Otro Amount para comparar
     * @return bool True si es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->cents <= $other->cents;
    }

    /**
     * Verifica si este Amount es cero
     *
     * @return bool True si es cero
     */
    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    /**
     * Verifica si este Amount es positivo
     *
     * @return bool True si es positivo
     */
    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    /**
     * Verifica si este Amount es negativo
     *
     * @return bool True si es negativo
     */
    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    /**
     * Suma otro Amount a este
     *
     * @param self $other Amount a sumar
     * @return self Nuevo Amount con el resultado
     */
    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    /**
     * Resta otro Amount de este
     *
     * @param self $other Amount a restar
     * @return self Nuevo Amount con el resultado
     */
    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    /**
     * Multiplica este Amount por un factor
     *
     * @param float $factor Factor de multiplicación
     * @return self Nuevo Amount con el resultado
     */
    public function multiply(float $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('El factor de multiplicación no puede ser negativo');
        }
        
        $result = (int) round($this->cents * $factor);
        return new self($result);
    }

    /**
     * Divide este Amount por un divisor
     *
     * @param float $divisor Divisor
     * @return self Nuevo Amount con el resultado
     */
    public function divide(float $divisor): self
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException('El divisor debe ser mayor que cero');
        }
        
        $result = (int) round($this->cents / $divisor);
        return new self($result);
    }

    /**
     * Calcula el porcentaje de este Amount
     *
     * @param float $percentage Porcentaje (ej: 10.5 para 10.5%)
     * @return self Nuevo Amount con el porcentaje calculado
     */
    public function percentage(float $percentage): self
    {
        if ($percentage < 0) {
            throw new InvalidArgumentException('El porcentaje no puede ser negativo');
        }
        
        return $this->multiply($percentage / 100.0);
    }

    /**
     * Obtiene el valor absoluto de este Amount
     *
     * @return self Nuevo Amount con valor absoluto
     */
    public function abs(): self
    {
        return new self(abs($this->cents));
    }

    /**
     * Verifica si este Amount es un monto alto (>= $100)
     *
     * @return bool True si es un monto alto
     */
    public function isHighAmount(): bool
    {
        return $this->cents >= 10000; // $100.00 en centavos
    }

    /**
     * Verifica si este Amount es un monto medio ($10 - $99.99)
     *
     * @return bool True si es un monto medio
     */
    public function isMediumAmount(): bool
    {
        return $this->cents >= 1000 && $this->cents < 10000; // $10.00 - $99.99
    }

    /**
     * Verifica si este Amount es un monto bajo (< $10)
     *
     * @return bool True si es un monto bajo
     */
    public function isLowAmount(): bool
    {
        return $this->cents > 0 && $this->cents < 1000; // $0.01 - $9.99
    }

    /**
     * Verifica si este Amount es un monto micro (< $1)
     *
     * @return bool True si es un monto micro
     */
    public function isMicroAmount(): bool
    {
        return $this->cents > 0 && $this->cents < 100; // $0.01 - $0.99
    }

    /**
     * Formatea este Amount con una moneda
     *
     * @param Currency $currency Moneda para formatear
     * @param bool $showSymbol Si mostrar el símbolo de la moneda
     * @return string Monto formateado
     */
    public function formatWithCurrency(Currency $currency, bool $showSymbol = true): string
    {
        return $currency->formatAmount($this->toFloat(), $showSymbol);
    }

    /**
     * Convierte el Amount a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->toFloat(),
            'cents' => $this->cents,
            'string' => $this->toString(),
            'is_zero' => $this->isZero(),
            'is_positive' => $this->isPositive(),
            'is_negative' => $this->isNegative(),
            'is_high_amount' => $this->isHighAmount(),
            'is_medium_amount' => $this->isMediumAmount(),
            'is_low_amount' => $this->isLowAmount(),
            'is_micro_amount' => $this->isMicroAmount(),
        ];
    }

    /**
     * Representación en string del Amount
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
     * @return float Valor para JSON
     */
    public function jsonSerialize(): float
    {
        return $this->toFloat();
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'value' => $this->toFloat(),
            'cents' => $this->cents,
            'is_zero' => $this->isZero(),
            'is_positive' => $this->isPositive(),
        ];
    }

    /**
     * Valida que el monto sea válido
     *
     * @param float $amount Monto a validar
     * @throws InvalidArgumentException Si el monto no es válido
     */
    private static function validate(float $amount): void
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount no puede ser negativo');
        }
        
        if ($amount > 999999.99) {
            throw new InvalidArgumentException('Amount no puede ser mayor a $999,999.99');
        }
        
        if (!is_finite($amount)) {
            throw new InvalidArgumentException('Amount debe ser un número finito');
        }
    }

    /**
     * Verifica si un valor es un Amount válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if (is_float($value)) {
                self::validate($value);
                return true;
            }
            
            if (is_int($value) && $value >= 0) {
                return true;
            }
            
            if (is_string($value) && is_numeric($value)) {
                self::validate((float) $value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea un Amount desde un valor mixto
     *
     * @param mixed $value Valor del monto
     * @return self Nueva instancia de Amount
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value): self
    {
        if (is_float($value)) {
            return self::fromFloat($value);
        }
        
        if (is_int($value)) {
            return self::fromCents($value);
        }
        
        if (is_string($value)) {
            return self::fromString($value);
        }
        
        if (is_array($value)) {
            return self::fromArray($value);
        }

        throw new InvalidArgumentException(
            'Amount solo puede crearse desde float, int, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Obtiene el máximo entre dos Amount
     *
     * @param self $a Primer Amount
     * @param self $b Segundo Amount
     * @return self Amount mayor
     */
    public static function max(self $a, self $b): self
    {
        return $a->isGreaterThan($b) ? $a : $b;
    }

    /**
     * Obtiene el mínimo entre dos Amount
     *
     * @param self $a Primer Amount
     * @param self $b Segundo Amount
     * @return self Amount menor
     */
    public static function min(self $a, self $b): self
    {
        return $a->isLessThan($b) ? $a : $b;
    }

    /**
     * Suma múltiples Amount
     *
     * @param self ...$amounts Amounts a sumar
     * @return self Suma total
     */
    public static function sum(self ...$amounts): self
    {
        $totalCents = 0;
        foreach ($amounts as $amount) {
            $totalCents += $amount->cents;
        }
        return new self($totalCents);
    }
}
