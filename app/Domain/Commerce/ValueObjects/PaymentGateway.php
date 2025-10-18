<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * PaymentGateway Value Object
 * 
 * Representa una pasarela de pago en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de las pasarelas de pago en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para manejar
 * diferentes pasarelas de pago en el sistema de pagos.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta pasarelas válidas predefinidas
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos PaymentGateway con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y pagos
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class PaymentGateway implements JsonSerializable
{
    /**
     * Pasarelas de pago soportadas
     */
    public const STRIPE = 'stripe';
    public const PAYPAL = 'paypal';
    public const APPLE_PAY = 'apple_pay';
    public const GOOGLE_PAY = 'google_pay';
    public const BRAINTREE = 'braintree';
    public const SQUARE = 'square';
    public const ADYEN = 'adyen';
    public const RAZORPAY = 'razorpay';

    /**
     * Lista de todas las pasarelas válidas
     */
    private const VALID_GATEWAYS = [
        self::STRIPE,
        self::PAYPAL,
        self::APPLE_PAY,
        self::GOOGLE_PAY,
        self::BRAINTREE,
        self::SQUARE,
        self::ADYEN,
        self::RAZORPAY,
    ];

    /**
     * Información de las pasarelas (nombre, región, características)
     */
    private const GATEWAY_INFO = [
        self::STRIPE => [
            'name' => 'Stripe',
            'region' => 'global',
            'supports_cards' => true,
            'supports_wallets' => true,
            'supports_bank_transfers' => true,
            'supports_subscriptions' => true,
            'processing_time' => 'instant',
            'fees' => '2.9% + 30¢',
        ],
        self::PAYPAL => [
            'name' => 'PayPal',
            'region' => 'global',
            'supports_cards' => true,
            'supports_wallets' => true,
            'supports_bank_transfers' => true,
            'supports_subscriptions' => true,
            'processing_time' => 'instant',
            'fees' => '2.9% + fixed fee',
        ],
        self::APPLE_PAY => [
            'name' => 'Apple Pay',
            'region' => 'global',
            'supports_cards' => true,
            'supports_wallets' => true,
            'supports_bank_transfers' => false,
            'supports_subscriptions' => false,
            'processing_time' => 'instant',
            'fees' => 'varies by card',
        ],
        self::GOOGLE_PAY => [
            'name' => 'Google Pay',
            'region' => 'global',
            'supports_cards' => true,
            'supports_wallets' => true,
            'supports_bank_transfers' => false,
            'supports_subscriptions' => false,
            'processing_time' => 'instant',
            'fees' => 'varies by card',
        ],
        self::BRAINTREE => [
            'name' => 'Braintree',
            'region' => 'global',
            'supports_cards' => true,
            'supports_wallets' => true,
            'supports_bank_transfers' => true,
            'supports_subscriptions' => true,
            'processing_time' => 'instant',
            'fees' => '2.9% + 30¢',
        ],
        self::SQUARE => [
            'name' => 'Square',
            'region' => 'global',
            'supports_cards' => true,
            'supports_wallets' => true,
            'supports_bank_transfers' => false,
            'supports_subscriptions' => true,
            'processing_time' => 'instant',
            'fees' => '2.6% + 10¢',
        ],
        self::ADYEN => [
            'name' => 'Adyen',
            'region' => 'global',
            'supports_cards' => true,
            'supports_wallets' => true,
            'supports_bank_transfers' => true,
            'supports_subscriptions' => true,
            'processing_time' => 'instant',
            'fees' => 'varies by region',
        ],
        self::RAZORPAY => [
            'name' => 'Razorpay',
            'region' => 'india',
            'supports_cards' => true,
            'supports_wallets' => true,
            'supports_bank_transfers' => true,
            'supports_subscriptions' => true,
            'processing_time' => 'instant',
            'fees' => '2% + ₹3',
        ],
    ];

    /**
     * El valor de la pasarela de pago
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
     * Factory method para crear PaymentGateway desde string
     *
     * @param string $value Valor de la pasarela
     * @return self Nueva instancia de PaymentGateway
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory methods para cada pasarela específica
     */
    public static function stripe(): self
    {
        return new self(self::STRIPE);
    }

    public static function paypal(): self
    {
        return new self(self::PAYPAL);
    }

    public static function applePay(): self
    {
        return new self(self::APPLE_PAY);
    }

    public static function googlePay(): self
    {
        return new self(self::GOOGLE_PAY);
    }

    public static function braintree(): self
    {
        return new self(self::BRAINTREE);
    }

    public static function square(): self
    {
        return new self(self::SQUARE);
    }

    public static function adyen(): self
    {
        return new self(self::ADYEN);
    }

    public static function razorpay(): self
    {
        return new self(self::RAZORPAY);
    }

    /**
     * Obtiene el valor de la pasarela
     *
     * @return string Valor de la pasarela
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Obtiene el nombre de la pasarela
     *
     * @return string Nombre de la pasarela
     */
    public function getName(): string
    {
        return self::GATEWAY_INFO[$this->value]['name'];
    }

    /**
     * Obtiene la región de la pasarela
     *
     * @return string Región de la pasarela
     */
    public function getRegion(): string
    {
        return self::GATEWAY_INFO[$this->value]['region'];
    }

    /**
     * Verifica si la pasarela soporta tarjetas
     *
     * @return bool True si soporta tarjetas
     */
    public function supportsCards(): bool
    {
        return self::GATEWAY_INFO[$this->value]['supports_cards'];
    }

    /**
     * Verifica si la pasarela soporta wallets digitales
     *
     * @return bool True si soporta wallets
     */
    public function supportsWallets(): bool
    {
        return self::GATEWAY_INFO[$this->value]['supports_wallets'];
    }

    /**
     * Verifica si la pasarela soporta transferencias bancarias
     *
     * @return bool True si soporta transferencias bancarias
     */
    public function supportsBankTransfers(): bool
    {
        return self::GATEWAY_INFO[$this->value]['supports_bank_transfers'];
    }

    /**
     * Verifica si la pasarela soporta suscripciones
     *
     * @return bool True si soporta suscripciones
     */
    public function supportsSubscriptions(): bool
    {
        return self::GATEWAY_INFO[$this->value]['supports_subscriptions'];
    }

    /**
     * Obtiene el tiempo de procesamiento
     *
     * @return string Tiempo de procesamiento
     */
    public function getProcessingTime(): string
    {
        return self::GATEWAY_INFO[$this->value]['processing_time'];
    }

    /**
     * Obtiene la información de comisiones
     *
     * @return string Información de comisiones
     */
    public function getFees(): string
    {
        return self::GATEWAY_INFO[$this->value]['fees'];
    }

    /**
     * Verifica si esta PaymentGateway es igual a otra
     *
     * @param self $other Otra PaymentGateway para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si esta PaymentGateway es Stripe
     *
     * @return bool True si es Stripe
     */
    public function isStripe(): bool
    {
        return $this->value === self::STRIPE;
    }

    /**
     * Verifica si esta PaymentGateway es PayPal
     *
     * @return bool True si es PayPal
     */
    public function isPayPal(): bool
    {
        return $this->value === self::PAYPAL;
    }

    /**
     * Verifica si esta PaymentGateway es Apple Pay
     *
     * @return bool True si es Apple Pay
     */
    public function isApplePay(): bool
    {
        return $this->value === self::APPLE_PAY;
    }

    /**
     * Verifica si esta PaymentGateway es Google Pay
     *
     * @return bool True si es Google Pay
     */
    public function isGooglePay(): bool
    {
        return $this->value === self::GOOGLE_PAY;
    }

    /**
     * Verifica si esta PaymentGateway es una pasarela global
     *
     * @return bool True si es global
     */
    public function isGlobal(): bool
    {
        return $this->getRegion() === 'global';
    }

    /**
     * Verifica si esta PaymentGateway es una pasarela regional
     *
     * @return bool True si es regional
     */
    public function isRegional(): bool
    {
        return $this->getRegion() !== 'global';
    }

    /**
     * Obtiene la prioridad de procesamiento de la pasarela
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        return match ($this->value) {
            self::STRIPE => 1, // Máxima prioridad para Stripe
            self::PAYPAL => 2, // Alta prioridad para PayPal
            self::BRAINTREE => 3, // Prioridad alta para Braintree
            self::ADYEN => 3, // Prioridad alta para Adyen
            self::SQUARE => 4, // Prioridad media para Square
            self::APPLE_PAY, self::GOOGLE_PAY => 4, // Prioridad media para wallets
            self::RAZORPAY => 5, // Prioridad baja para pasarelas regionales
            default => 3,
        };
    }

    /**
     * Verifica si esta PaymentGateway es compatible con el tipo de pago
     *
     * @param string $paymentType Tipo de pago (card, wallet, bank_transfer, subscription)
     * @return bool True si es compatible
     */
    public function isCompatibleWith(string $paymentType): bool
    {
        return match ($paymentType) {
            'card' => $this->supportsCards(),
            'wallet' => $this->supportsWallets(),
            'bank_transfer' => $this->supportsBankTransfers(),
            'subscription' => $this->supportsSubscriptions(),
            default => false,
        };
    }

    /**
     * Convierte el PaymentGateway a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'name' => $this->getName(),
            'region' => $this->getRegion(),
            'supports_cards' => $this->supportsCards(),
            'supports_wallets' => $this->supportsWallets(),
            'supports_bank_transfers' => $this->supportsBankTransfers(),
            'supports_subscriptions' => $this->supportsSubscriptions(),
            'processing_time' => $this->getProcessingTime(),
            'fees' => $this->getFees(),
            'processing_priority' => $this->getProcessingPriority(),
            'is_global' => $this->isGlobal(),
            'is_regional' => $this->isRegional(),
        ];
    }

    /**
     * Representación en string del PaymentGateway
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
            'name' => $this->getName(),
            'region' => $this->getRegion(),
            'processing_priority' => $this->getProcessingPriority(),
        ];
    }

    /**
     * Valida que el valor de la pasarela sea válido
     *
     * @param string $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(string $value): void
    {
        if (!in_array($value, self::VALID_GATEWAYS, true)) {
            throw new InvalidArgumentException(
                "PaymentGateway debe ser uno de los valores válidos: " . implode(', ', self::VALID_GATEWAYS) . 
                ", se recibió: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es una PaymentGateway válida
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
     * Crea un PaymentGateway desde un valor mixto
     *
     * @param mixed $value Valor de la pasarela
     * @return self Nueva instancia de PaymentGateway
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
            'PaymentGateway solo puede crearse desde string o instancia de PaymentGateway, se recibió: ' . gettype($value)
        );
    }

    /**
     * Obtiene todas las pasarelas válidas
     *
     * @return array Lista de pasarelas válidas
     */
    public static function getValidGateways(): array
    {
        return self::VALID_GATEWAYS;
    }

    /**
     * Obtiene las pasarelas por región
     *
     * @param string $region Región a filtrar
     * @return array Pasarelas de la región
     */
    public static function getByRegion(string $region): array
    {
        $gateways = [];
        
        foreach (self::VALID_GATEWAYS as $value) {
            $gateway = new self($value);
            if ($gateway->getRegion() === $region) {
                $gateways[] = $gateway;
            }
        }
        
        return $gateways;
    }

    /**
     * Obtiene las pasarelas globales
     *
     * @return array Pasarelas globales
     */
    public static function getGlobalGateways(): array
    {
        return self::getByRegion('global');
    }

    /**
     * Obtiene las pasarelas que soportan suscripciones
     *
     * @return array Pasarelas que soportan suscripciones
     */
    public static function getSubscriptionGateways(): array
    {
        $gateways = [];
        
        foreach (self::VALID_GATEWAYS as $value) {
            $gateway = new self($value);
            if ($gateway->supportsSubscriptions()) {
                $gateways[] = $gateway;
            }
        }
        
        return $gateways;
    }
}
