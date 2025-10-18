<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Price Value Object
 * 
 * Representa el valor monetario de un producto en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del precio en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para manejar
 * precios de productos, regalos, planes de suscripción y paquetes de monedas
 * con precisión decimal y validación de rangos.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores decimales positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos Price con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y precios
 * - Precision handling: Maneja precisión decimal para cálculos monetarios
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class Price implements JsonSerializable
{
    /**
     * El valor del precio en formato decimal
     */
    private readonly float $value;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(float $value)
    {
        $this->value = $value;
    }

    /**
     * Factory method para crear una nueva instancia de Price
     *
     * @param float $value Valor del precio
     * @return self Nueva instancia de Price
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromFloat(float $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear Price desde string
     *
     * @param string $value Valor del precio como string
     * @return self Nueva instancia de Price
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "Price debe ser un número decimal válido, se recibió: {$value}"
            );
        }

        $floatValue = (float) $value;
        return self::fromFloat($floatValue);
    }

    /**
     * Factory method para crear Price desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el precio
     * @param string $key Clave del array que contiene el precio
     * @return self Nueva instancia de Price
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromArray(array $data, string $key = 'price'): self
    {
        if (!isset($data[$key])) {
            throw new InvalidArgumentException(
                "Clave '{$key}' no encontrada en los datos proporcionados"
            );
        }

        return self::fromFloat($data[$key]);
    }

    /**
     * Factory method para crear Price desde entero (centavos)
     *
     * @param int $cents Valor en centavos
     * @return self Nueva instancia de Price
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromCents(int $cents): self
    {
        if ($cents < 0) {
            throw new InvalidArgumentException(
                "El precio en centavos debe ser no negativo, se recibió: {$cents}"
            );
        }

        return new self($cents / 100.0);
    }

    /**
     * Obtiene el valor decimal del precio
     *
     * @return float Valor del precio
     */
    public function toFloat(): float
    {
        return $this->value;
    }

    /**
     * Obtiene el valor como string formateado
     *
     * @param int $decimals Número de decimales (por defecto 2)
     * @return string Valor del precio como string formateado
     */
    public function toString(int $decimals = 2): string
    {
        return number_format($this->value, $decimals);
    }

    /**
     * Obtiene el valor en centavos
     *
     * @return int Valor en centavos
     */
    public function toCents(): int
    {
        return (int) round($this->value * 100);
    }

    /**
     * Verifica si este Price es igual a otro
     *
     * @param self $other Otro Price para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return abs($this->value - $other->value) < 0.01; // Tolerancia de 1 centavo
    }

    /**
     * Verifica si este Price es mayor que otro
     *
     * @param self $other Otro Price para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este Price es menor que otro
     *
     * @param self $other Otro Price para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Verifica si este Price es mayor o igual que otro
     *
     * @param self $other Otro Price para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Verifica si este Price es menor o igual que otro
     *
     * @param self $other Otro Price para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * Verifica si este Price está en un rango específico
     *
     * @param self $min Price mínimo (inclusivo)
     * @param self $max Price máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Suma otro precio a este precio
     *
     * @param self $other Otro precio a sumar
     * @return self Nuevo precio con la suma
     */
    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    /**
     * Resta otro precio de este precio
     *
     * @param self $other Otro precio a restar
     * @return self Nuevo precio con la resta
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
     * Multiplica este precio por un factor
     *
     * @param float $factor Factor de multiplicación
     * @return self Nuevo precio multiplicado
     */
    public function multiply(float $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException(
                "El factor de multiplicación no puede ser negativo: {$factor}"
            );
        }
        return new self($this->value * $factor);
    }

    /**
     * Divide este precio por un divisor
     *
     * @param float $divisor Divisor
     * @return self Nuevo precio dividido
     */
    public function divide(float $divisor): self
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException(
                "El divisor debe ser positivo: {$divisor}"
            );
        }
        return new self($this->value / $divisor);
    }

    /**
     * Aplica un descuento porcentual
     *
     * @param float $discountPercent Porcentaje de descuento (0-100)
     * @return self Nuevo precio con descuento aplicado
     */
    public function applyDiscount(float $discountPercent): self
    {
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new InvalidArgumentException(
                "El porcentaje de descuento debe estar entre 0 y 100: {$discountPercent}"
            );
        }

        $discountAmount = $this->value * ($discountPercent / 100);
        return new self($this->value - $discountAmount);
    }

    /**
     * Aplica un impuesto porcentual
     *
     * @param float $taxPercent Porcentaje de impuesto (0-100)
     * @return self Nuevo precio con impuesto aplicado
     */
    public function applyTax(float $taxPercent): self
    {
        if ($taxPercent < 0) {
            throw new InvalidArgumentException(
                "El porcentaje de impuesto no puede ser negativo: {$taxPercent}"
            );
        }

        $taxAmount = $this->value * ($taxPercent / 100);
        return new self($this->value + $taxAmount);
    }

    /**
     * Verifica si este Price es gratuito (0)
     *
     * @return bool True si es gratuito
     */
    public function isFree(): bool
    {
        return $this->value == 0.0;
    }

    /**
     * Verifica si este Price es premium (alto)
     *
     * @return bool True si es premium
     */
    public function isPremium(): bool
    {
        return $this->value > 50.0;
    }

    /**
     * Verifica si este Price es económico (bajo)
     *
     * @return bool True si es económico
     */
    public function isEconomical(): bool
    {
        return $this->value > 0.0 && $this->value <= 10.0;
    }

    /**
     * Verifica si este Price es moderado (medio)
     *
     * @return bool True si es moderado
     */
    public function isModerate(): bool
    {
        return $this->value > 10.0 && $this->value <= 50.0;
    }

    /**
     * Verifica si este Price es costoso (muy alto)
     *
     * @return bool True si es costoso
     */
    public function isExpensive(): bool
    {
        return $this->value > 100.0;
    }

    /**
     * Obtiene la categoría del precio basada en el valor
     *
     * @return string Categoría del precio (free, economical, moderate, premium, expensive)
     */
    public function getPriceCategory(): string
    {
        if ($this->isFree()) {
            return 'free';
        }

        if ($this->isEconomical()) {
            return 'economical';
        }

        if ($this->isModerate()) {
            return 'moderate';
        }

        if ($this->isPremium()) {
            return 'premium';
        }

        if ($this->isExpensive()) {
            return 'expensive';
        }

        return 'custom';
    }

    /**
     * Convierte el Price a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'cents' => $this->toCents(),
            'formatted' => $this->toString(),
            'category' => $this->getPriceCategory(),
            'is_free' => $this->isFree(),
            'is_premium' => $this->isPremium(),
            'is_economical' => $this->isEconomical(),
        ];
    }

    /**
     * Representación en string del Price
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
            'cents' => $this->toCents(),
            'formatted' => $this->toString(),
            'category' => $this->getPriceCategory(),
        ];
    }

    /**
     * Valida que el valor del precio sea válido
     *
     * @param float $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(float $value): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(
                "Price debe ser un número no negativo, se recibió: {$value}"
            );
        }

        if (!is_finite($value)) {
            throw new InvalidArgumentException(
                "Price debe ser un número finito, se recibió: {$value}"
            );
        }

        // Validar que no exceda un límite razonable (ej: 1 millón)
        if ($value > 1000000) {
            throw new InvalidArgumentException(
                "Price excede el valor máximo permitido (1,000,000): {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un Price válido
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

            if (is_string($value) && is_numeric($value)) {
                self::validate((float) $value);
                return true;
            }

            if (is_int($value)) {
                self::validate((float) $value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea un Price desde un valor mixto (int, float, string, array)
     *
     * @param mixed $value Valor del precio
     * @param string $arrayKey Clave para arrays (por defecto 'price')
     * @return self Nueva instancia de Price
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value, string $arrayKey = 'price'): self
    {
        if (is_float($value)) {
            return self::fromFloat($value);
        }

        if (is_int($value)) {
            return self::fromFloat((float) $value);
        }

        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_array($value)) {
            return self::fromArray($value, $arrayKey);
        }

        throw new InvalidArgumentException(
            'Price solo puede crearse desde int, float, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Verifica si este Price corresponde a un precio con características específicas
     * (útil para filtros y búsquedas)
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['category'])) {
            if ($this->getPriceCategory() !== $criteria['category']) {
                return false;
            }
        }

        if (isset($criteria['free_only']) && $criteria['free_only']) {
            if (!$this->isFree()) {
                return false;
            }
        }

        if (isset($criteria['premium_only']) && $criteria['premium_only']) {
            if (!$this->isPremium()) {
                return false;
            }
        }

        if (isset($criteria['economical_only']) && $criteria['economical_only']) {
            if (!$this->isEconomical()) {
                return false;
            }
        }

        if (isset($criteria['max_price'])) {
            if ($this->value > $criteria['max_price']) {
                return false;
            }
        }

        if (isset($criteria['min_price'])) {
            if ($this->value < $criteria['min_price']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del Price
     *
     * @return array Estadísticas del precio
     */
    public function getStats(): array
    {
        return [
            'value' => $this->value,
            'cents' => $this->toCents(),
            'formatted' => $this->toString(),
            'category' => $this->getPriceCategory(),
            'is_free' => $this->isFree(),
            'is_premium' => $this->isPremium(),
            'is_economical' => $this->isEconomical(),
            'is_moderate' => $this->isModerate(),
            'is_expensive' => $this->isExpensive(),
        ];
    }

    /**
     * Genera un hash único para este Price (útil para cache keys)
     *
     * @return string Hash único del Price
     */
    public function getHash(): string
    {
        return 'price_' . $this->toCents() . '_' . substr(md5((string) $this->value), 0, 8);
    }

    /**
     * Verifica si este Price es compatible con el sistema de analytics
     * (precios que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Todos los precios pueden generar analytics
        return true;
    }

    /**
     * Verifica si este Price es compatible con el sistema de facturación
     * (precios que pueden generar facturas)
     *
     * @return bool True si es compatible con facturación
     */
    public function isBillingCompatible(): bool
    {
        // Todos los precios pueden generar facturas (incluso los gratuitos)
        return true;
    }

    /**
     * Obtiene la prioridad de procesamiento del precio
     * (útil para colas de procesamiento)
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        if ($this->isExpensive()) {
            return 1; // Alta prioridad para precios altos
        }

        if ($this->isPremium()) {
            return 2; // Alta prioridad para precios premium
        }

        if ($this->isModerate()) {
            return 3; // Prioridad media para precios moderados
        }

        if ($this->isEconomical()) {
            return 4; // Prioridad media-baja para precios económicos
        }

        return 5; // Baja prioridad para precios gratuitos
    }
}
