<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Currency Value Object
 * 
 * Representa una moneda en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de las monedas en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para manejar
 * diferentes monedas en el sistema de pagos y precios.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta códigos de moneda válidos ISO 4217
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos Currency con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y pagos
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class Currency implements JsonSerializable
{
    /**
     * Monedas soportadas
     */
    public const USD = 'USD'; // Dólar estadounidense
    public const EUR = 'EUR'; // Euro
    public const GBP = 'GBP'; // Libra esterlina
    public const CAD = 'CAD'; // Dólar canadiense
    public const AUD = 'AUD'; // Dólar australiano
    public const JPY = 'JPY'; // Yen japonés
    public const CHF = 'CHF'; // Franco suizo
    public const CNY = 'CNY'; // Yuan chino
    public const INR = 'INR'; // Rupia india
    public const BRL = 'BRL'; // Real brasileño
    public const MXN = 'MXN'; // Peso mexicano
    public const ARS = 'ARS'; // Peso argentino
    public const CLP = 'CLP'; // Peso chileno
    public const COP = 'COP'; // Peso colombiano
    public const PEN = 'PEN'; // Sol peruano
    public const UYU = 'UYU'; // Peso uruguayo
    public const VEF = 'VEF'; // Bolívar venezolano

    /**
     * Todas las monedas válidas
     */
    private const VALID_CURRENCIES = [
        self::USD,
        self::EUR,
        self::GBP,
        self::CAD,
        self::AUD,
        self::JPY,
        self::CHF,
        self::CNY,
        self::INR,
        self::BRL,
        self::MXN,
        self::ARS,
        self::CLP,
        self::COP,
        self::PEN,
        self::UYU,
        self::VEF,
    ];

    /**
     * Información de las monedas (código, símbolo, nombre, decimales)
     */
    private const CURRENCY_INFO = [
        self::USD => ['symbol' => '$', 'name' => 'US Dollar', 'decimals' => 2],
        self::EUR => ['symbol' => '€', 'name' => 'Euro', 'decimals' => 2],
        self::GBP => ['symbol' => '£', 'name' => 'British Pound', 'decimals' => 2],
        self::CAD => ['symbol' => 'C$', 'name' => 'Canadian Dollar', 'decimals' => 2],
        self::AUD => ['symbol' => 'A$', 'name' => 'Australian Dollar', 'decimals' => 2],
        self::JPY => ['symbol' => '¥', 'name' => 'Japanese Yen', 'decimals' => 0],
        self::CHF => ['symbol' => 'CHF', 'name' => 'Swiss Franc', 'decimals' => 2],
        self::CNY => ['symbol' => '¥', 'name' => 'Chinese Yuan', 'decimals' => 2],
        self::INR => ['symbol' => '₹', 'name' => 'Indian Rupee', 'decimals' => 2],
        self::BRL => ['symbol' => 'R$', 'name' => 'Brazilian Real', 'decimals' => 2],
        self::MXN => ['symbol' => 'MX$', 'name' => 'Mexican Peso', 'decimals' => 2],
        self::ARS => ['symbol' => '$', 'name' => 'Argentine Peso', 'decimals' => 2],
        self::CLP => ['symbol' => '$', 'name' => 'Chilean Peso', 'decimals' => 0],
        self::COP => ['symbol' => '$', 'name' => 'Colombian Peso', 'decimals' => 2],
        self::PEN => ['symbol' => 'S/', 'name' => 'Peruvian Sol', 'decimals' => 2],
        self::UYU => ['symbol' => '$U', 'name' => 'Uruguayan Peso', 'decimals' => 2],
        self::VEF => ['symbol' => 'Bs', 'name' => 'Venezuelan Bolívar', 'decimals' => 2],
    ];

    /**
     * El código de la moneda
     */
    private readonly string $code;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(string $code)
    {
        $this->code = $code;
    }

    /**
     * Factory method para crear Currency desde string
     *
     * @param string $code Código de la moneda
     * @return self Nueva instancia de Currency
     * @throws InvalidArgumentException Si el código no es válido
     */
    public static function fromString(string $code): self
    {
        self::validate($code);
        return new self($code);
    }

    /**
     * Factory methods para cada moneda específica
     */
    public static function usd(): self
    {
        return new self(self::USD);
    }

    public static function eur(): self
    {
        return new self(self::EUR);
    }

    public static function gbp(): self
    {
        return new self(self::GBP);
    }

    public static function cad(): self
    {
        return new self(self::CAD);
    }

    public static function aud(): self
    {
        return new self(self::AUD);
    }

    public static function jpy(): self
    {
        return new self(self::JPY);
    }

    public static function chf(): self
    {
        return new self(self::CHF);
    }

    public static function cny(): self
    {
        return new self(self::CNY);
    }

    public static function inr(): self
    {
        return new self(self::INR);
    }

    public static function brl(): self
    {
        return new self(self::BRL);
    }

    public static function mxn(): self
    {
        return new self(self::MXN);
    }

    public static function ars(): self
    {
        return new self(self::ARS);
    }

    public static function clp(): self
    {
        return new self(self::CLP);
    }

    public static function cop(): self
    {
        return new self(self::COP);
    }

    public static function pen(): self
    {
        return new self(self::PEN);
    }

    public static function uyu(): self
    {
        return new self(self::UYU);
    }

    public static function vef(): self
    {
        return new self(self::VEF);
    }

    /**
     * Obtiene el código de la moneda
     *
     * @return string Código de la moneda
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Obtiene el código de la moneda (alias para getCode)
     *
     * @return string Código de la moneda
     */
    public function toString(): string
    {
        return $this->code;
    }

    /**
     * Obtiene el código de la moneda (alias para getCode)
     *
     * @return string Código de la moneda
     */
    public function value(): string
    {
        return $this->code;
    }

    /**
     * Obtiene el símbolo de la moneda
     *
     * @return string Símbolo de la moneda
     */
    public function getSymbol(): string
    {
        return self::CURRENCY_INFO[$this->code]['symbol'];
    }

    /**
     * Obtiene el nombre completo de la moneda
     *
     * @return string Nombre de la moneda
     */
    public function getName(): string
    {
        return self::CURRENCY_INFO[$this->code]['name'];
    }

    /**
     * Obtiene el número de decimales para la moneda
     *
     * @return int Número de decimales
     */
    public function getDecimals(): int
    {
        return self::CURRENCY_INFO[$this->code]['decimals'];
    }

    /**
     * Verifica si esta Currency es igual a otra
     *
     * @param self $other Otra Currency para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    /**
     * Verifica si esta Currency es USD
     *
     * @return bool True si es USD
     */
    public function isUsd(): bool
    {
        return $this->code === self::USD;
    }

    /**
     * Verifica si esta Currency es EUR
     *
     * @return bool True si es EUR
     */
    public function isEur(): bool
    {
        return $this->code === self::EUR;
    }

    /**
     * Verifica si esta Currency es una moneda mayor (USD, EUR, GBP)
     *
     * @return bool True si es una moneda mayor
     */
    public function isMajor(): bool
    {
        return in_array($this->code, [self::USD, self::EUR, self::GBP]);
    }

    /**
     * Verifica si esta Currency es una moneda latinoamericana
     *
     * @return bool True si es una moneda latinoamericana
     */
    public function isLatinAmerican(): bool
    {
        return in_array($this->code, [self::BRL, self::MXN, self::ARS, self::CLP, self::COP, self::PEN, self::UYU, self::VEF]);
    }

    /**
     * Verifica si esta Currency es una moneda asiática
     *
     * @return bool True si es una moneda asiática
     */
    public function isAsian(): bool
    {
        return in_array($this->code, [self::JPY, self::CNY, self::INR]);
    }

    /**
     * Verifica si esta Currency es una moneda europea
     *
     * @return bool True si es una moneda europea
     */
    public function isEuropean(): bool
    {
        return in_array($this->code, [self::EUR, self::GBP, self::CHF]);
    }

    /**
     * Verifica si esta Currency es una moneda americana (Norte y Sur)
     *
     * @return bool True si es una moneda americana
     */
    public function isAmerican(): bool
    {
        return in_array($this->code, [self::USD, self::CAD, self::BRL, self::MXN, self::ARS, self::CLP, self::COP, self::PEN, self::UYU, self::VEF]);
    }

    /**
     * Obtiene la región de la moneda
     *
     * @return string Región de la moneda
     */
    public function getRegion(): string
    {
        if ($this->isLatinAmerican()) {
            return 'latin_america';
        }

        if ($this->isAsian()) {
            return 'asia';
        }

        if ($this->isEuropean()) {
            return 'europe';
        }

        if (in_array($this->code, [self::USD, self::CAD])) {
            return 'north_america';
        }

        if (in_array($this->code, [self::AUD])) {
            return 'oceania';
        }

        return 'other';
    }

    /**
     * Obtiene el nivel de estabilidad de la moneda
     *
     * @return string Nivel de estabilidad (high, medium, low)
     */
    public function getStabilityLevel(): string
    {
        if ($this->isMajor()) {
            return 'high';
        }

        if (in_array($this->code, [self::CAD, self::AUD, self::JPY, self::CHF])) {
            return 'high';
        }

        if (in_array($this->code, [self::CNY, self::BRL, self::MXN])) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Verifica si esta Currency es estable
     *
     * @return bool True si es estable
     */
    public function isStable(): bool
    {
        return $this->getStabilityLevel() === 'high';
    }

    /**
     * Obtiene la prioridad de procesamiento de la moneda
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        if ($this->isUsd()) {
            return 1; // Máxima prioridad para USD
        }

        if ($this->isMajor()) {
            return 2; // Alta prioridad para monedas mayores
        }

        if ($this->isStable()) {
            return 3; // Prioridad media para monedas estables
        }

        if ($this->getStabilityLevel() === 'medium') {
            return 4; // Prioridad media-baja para monedas medianamente estables
        }

        return 5; // Baja prioridad para monedas inestables
    }

    /**
     * Formatea un monto con la moneda
     *
     * @param float $amount Monto a formatear
     * @param bool $showSymbol Si mostrar el símbolo
     * @return string Monto formateado
     */
    public function formatAmount(float $amount, bool $showSymbol = true): string
    {
        $decimals = $this->getDecimals();
        $formatted = number_format($amount, $decimals, '.', ',');
        
        if ($showSymbol) {
            return $this->getSymbol() . $formatted;
        }
        
        return $formatted . ' ' . $this->code;
    }

    /**
     * Convierte el Currency a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'symbol' => $this->getSymbol(),
            'name' => $this->getName(),
            'decimals' => $this->getDecimals(),
            'region' => $this->getRegion(),
            'stability_level' => $this->getStabilityLevel(),
            'is_major' => $this->isMajor(),
            'is_stable' => $this->isStable(),
        ];
    }

    /**
     * Representación en string del Currency
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
        return $this->code;
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'code' => $this->code,
            'symbol' => $this->getSymbol(),
            'name' => $this->getName(),
            'region' => $this->getRegion(),
            'stability_level' => $this->getStabilityLevel(),
        ];
    }

    /**
     * Valida que el código de moneda sea válido
     *
     * @param string $code Código a validar
     * @throws InvalidArgumentException Si el código no es válido
     */
    private static function validate(string $code): void
    {
        if (!in_array($code, self::VALID_CURRENCIES)) {
            throw new InvalidArgumentException(
                "Currency debe ser uno de los códigos válidos: " . implode(', ', self::VALID_CURRENCIES) . 
                ", se recibió: {$code}"
            );
        }
    }

    /**
     * Verifica si un valor es una Currency válida
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
     * Obtiene todas las monedas válidas
     *
     * @return array Lista de monedas válidas
     */
    public static function getValidCurrencies(): array
    {
        return self::VALID_CURRENCIES;
    }

    /**
     * Crea una Currency desde un valor mixto
     *
     * @param mixed $value Valor de la moneda
     * @return self Nueva instancia de Currency
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value): self
    {
        if (is_string($value)) {
            return self::fromString($value);
        }

        if ($value instanceof self) {
            return $value;
        }

        throw new InvalidArgumentException(
            'Currency solo puede crearse desde string o instancia de Currency, se recibió: ' . gettype($value)
        );
    }

    /**
     * Obtiene las monedas por región
     *
     * @param string $region Región a filtrar
     * @return array Monedas de la región
     */
    public static function getByRegion(string $region): array
    {
        $currencies = [];
        
        foreach (self::VALID_CURRENCIES as $code) {
            $currency = new self($code);
            if ($currency->getRegion() === $region) {
                $currencies[] = $currency;
            }
        }
        
        return $currencies;
    }

    /**
     * Obtiene las monedas estables
     *
     * @return array Monedas estables
     */
    public static function getStableCurrencies(): array
    {
        $currencies = [];
        
        foreach (self::VALID_CURRENCIES as $code) {
            $currency = new self($code);
            if ($currency->isStable()) {
                $currencies[] = $currency;
            }
        }
        
        return $currencies;
    }

    /**
     * Obtiene las monedas principales
     *
     * @return array Monedas principales
     */
    public static function getMajorCurrencies(): array
    {
        $currencies = [];
        
        foreach (self::VALID_CURRENCIES as $code) {
            $currency = new self($code);
            if ($currency->isMajor()) {
                $currencies[] = $currency;
            }
        }
        
        return $currencies;
    }
}
