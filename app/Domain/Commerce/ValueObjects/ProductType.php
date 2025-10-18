<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * ProductType Value Object
 * 
 * Representa el tipo de producto en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del tipo de producto en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para clasificar
 * diferentes tipos de productos disponibles en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta tipos de producto válidos predefinidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos ProductType con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y productos
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class ProductType implements JsonSerializable
{
    /**
     * Tipos de producto válidos
     */
    public const GIFT = 'gift';
    public const PLAN = 'plan';
    public const COIN_PACKAGE = 'coin_package';
    public const FEATURE = 'feature';
    public const SERVICE = 'service';
    public const PROMOTION = 'promotion';
    public const SUBSCRIPTION = 'subscription';
    public const ADDON = 'addon';

    /**
     * Todos los tipos válidos
     */
    private const VALID_TYPES = [
        self::GIFT,
        self::PLAN,
        self::COIN_PACKAGE,
        self::FEATURE,
        self::SERVICE,
        self::PROMOTION,
        self::SUBSCRIPTION,
        self::ADDON,
    ];

    /**
     * El valor del tipo de producto
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
     * Factory method para crear ProductType desde string
     *
     * @param string $value Valor del tipo
     * @return self Nueva instancia de ProductType
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory methods para cada tipo específico
     */
    public static function gift(): self
    {
        return new self(self::GIFT);
    }

    public static function plan(): self
    {
        return new self(self::PLAN);
    }

    public static function coinPackage(): self
    {
        return new self(self::COIN_PACKAGE);
    }

    public static function feature(): self
    {
        return new self(self::FEATURE);
    }

    public static function service(): self
    {
        return new self(self::SERVICE);
    }

    public static function promotion(): self
    {
        return new self(self::PROMOTION);
    }

    public static function subscription(): self
    {
        return new self(self::SUBSCRIPTION);
    }

    public static function addon(): self
    {
        return new self(self::ADDON);
    }

    /**
     * Obtiene el valor string del tipo
     *
     * @return string Valor del tipo
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Obtiene el valor string del tipo (alias para toString)
     *
     * @return string Valor del tipo
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Verifica si este ProductType es igual a otro
     *
     * @param self $other Otro ProductType para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este ProductType es un regalo
     *
     * @return bool True si es un regalo
     */
    public function isGift(): bool
    {
        return $this->value === self::GIFT;
    }

    /**
     * Verifica si este ProductType es un plan
     *
     * @return bool True si es un plan
     */
    public function isPlan(): bool
    {
        return $this->value === self::PLAN;
    }

    /**
     * Verifica si este ProductType es un paquete de monedas
     *
     * @return bool True si es un paquete de monedas
     */
    public function isCoinPackage(): bool
    {
        return $this->value === self::COIN_PACKAGE;
    }

    /**
     * Verifica si este ProductType es una característica
     *
     * @return bool True si es una característica
     */
    public function isFeature(): bool
    {
        return $this->value === self::FEATURE;
    }

    /**
     * Verifica si este ProductType es un servicio
     *
     * @return bool True si es un servicio
     */
    public function isService(): bool
    {
        return $this->value === self::SERVICE;
    }

    /**
     * Verifica si este ProductType es una promoción
     *
     * @return bool True si es una promoción
     */
    public function isPromotion(): bool
    {
        return $this->value === self::PROMOTION;
    }

    /**
     * Verifica si este ProductType es una suscripción
     *
     * @return bool True si es una suscripción
     */
    public function isSubscription(): bool
    {
        return $this->value === self::SUBSCRIPTION;
    }

    /**
     * Verifica si este ProductType es un addon
     *
     * @return bool True si es un addon
     */
    public function isAddon(): bool
    {
        return $this->value === self::ADDON;
    }

    /**
     * Verifica si este ProductType requiere pago
     *
     * @return bool True si requiere pago
     */
    public function requiresPayment(): bool
    {
        return $this->isPlan() || $this->isCoinPackage() || $this->isService() || $this->isSubscription();
    }

    /**
     * Verifica si este ProductType es gratuito
     *
     * @return bool True si es gratuito
     */
    public function isFree(): bool
    {
        return !$this->requiresPayment();
    }

    /**
     * Verifica si este ProductType es virtual/digital
     *
     * @return bool True si es virtual
     */
    public function isVirtual(): bool
    {
        return $this->isGift() || $this->isPlan() || $this->isCoinPackage() || $this->isFeature() || $this->isService();
    }

    /**
     * Verifica si este ProductType es físico
     *
     * @return bool True si es físico
     */
    public function isPhysical(): bool
    {
        return !$this->isVirtual();
    }

    /**
     * Verifica si este ProductType es recurrente
     *
     * @return bool True si es recurrente
     */
    public function isRecurring(): bool
    {
        return $this->isPlan() || $this->isSubscription();
    }

    /**
     * Verifica si este ProductType es de una sola compra
     *
     * @return bool True si es de una sola compra
     */
    public function isOneTime(): bool
    {
        return !$this->isRecurring();
    }

    /**
     * Obtiene la categoría principal del tipo de producto
     *
     * @return string Categoría principal
     */
    public function getCategory(): string
    {
        return match ($this->value) {
            self::GIFT => 'virtual_goods',
            self::PLAN, self::SUBSCRIPTION => 'subscriptions',
            self::COIN_PACKAGE => 'virtual_currency',
            self::FEATURE, self::ADDON => 'features',
            self::SERVICE => 'services',
            self::PROMOTION => 'promotions',
            default => 'other',
        };
    }

    /**
     * Obtiene el nivel de prioridad del tipo de producto
     *
     * @return int Nivel de prioridad (1 = alta, 5 = baja)
     */
    public function getPriority(): int
    {
        return match ($this->value) {
            self::PLAN, self::SUBSCRIPTION => 1, // Alta prioridad para suscripciones
            self::COIN_PACKAGE => 2, // Alta prioridad para monedas
            self::SERVICE => 2, // Alta prioridad para servicios
            self::GIFT => 3, // Prioridad media para regalos
            self::FEATURE, self::ADDON => 3, // Prioridad media para características
            self::PROMOTION => 4, // Prioridad media-baja para promociones
            default => 5, // Baja prioridad por defecto
        };
    }

    /**
     * Obtiene las características asociadas al tipo de producto
     *
     * @return array Características del tipo
     */
    public function getFeatures(): array
    {
        return match ($this->value) {
            self::GIFT => ['virtual', 'one_time', 'giftable', 'animated'],
            self::PLAN => ['recurring', 'subscription', 'tiered', 'features'],
            self::COIN_PACKAGE => ['virtual_currency', 'one_time', 'bonus_support'],
            self::FEATURE => ['addon', 'one_time', 'upgrade'],
            self::SERVICE => ['professional', 'one_time', 'consultation'],
            self::PROMOTION => ['temporary', 'discount', 'limited_time'],
            self::SUBSCRIPTION => ['recurring', 'automated', 'billing'],
            self::ADDON => ['extension', 'one_time', 'optional'],
            default => [],
        };
    }

    /**
     * Verifica si este ProductType tiene una característica específica
     *
     * @param string $feature Característica a verificar
     * @return bool True si tiene la característica
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->getFeatures());
    }

    /**
     * Convierte el ProductType a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'category' => $this->getCategory(),
            'priority' => $this->getPriority(),
            'features' => $this->getFeatures(),
            'requires_payment' => $this->requiresPayment(),
            'is_virtual' => $this->isVirtual(),
            'is_recurring' => $this->isRecurring(),
        ];
    }

    /**
     * Representación en string del ProductType
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
            'category' => $this->getCategory(),
            'priority' => $this->getPriority(),
            'requires_payment' => $this->requiresPayment(),
            'is_virtual' => $this->isVirtual(),
        ];
    }

    /**
     * Valida que el valor del tipo sea válido
     *
     * @param string $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(string $value): void
    {
        if (!in_array($value, self::VALID_TYPES)) {
            throw new InvalidArgumentException(
                "ProductType debe ser uno de los valores válidos: " . implode(', ', self::VALID_TYPES) . 
                ", se recibió: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un ProductType válido
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
     * Obtiene todos los tipos válidos
     *
     * @return array Lista de tipos válidos
     */
    public static function getValidTypes(): array
    {
        return self::VALID_TYPES;
    }

    /**
     * Crea un ProductType desde un valor mixto
     *
     * @param mixed $value Valor del tipo
     * @return self Nueva instancia de ProductType
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
            'ProductType solo puede crearse desde string o instancia de ProductType, se recibió: ' . gettype($value)
        );
    }

    /**
     * Verifica si este ProductType corresponde a un criterio específico
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['type']) && $criteria['type'] !== $this->value) {
            return false;
        }

        if (isset($criteria['category']) && $criteria['category'] !== $this->getCategory()) {
            return false;
        }

        if (isset($criteria['requires_payment']) && $criteria['requires_payment'] !== $this->requiresPayment()) {
            return false;
        }

        if (isset($criteria['is_virtual']) && $criteria['is_virtual'] !== $this->isVirtual()) {
            return false;
        }

        if (isset($criteria['is_recurring']) && $criteria['is_recurring'] !== $this->isRecurring()) {
            return false;
        }

        if (isset($criteria['feature']) && !$this->hasFeature($criteria['feature'])) {
            return false;
        }

        if (isset($criteria['min_priority']) && $this->getPriority() < $criteria['min_priority']) {
            return false;
        }

        if (isset($criteria['max_priority']) && $this->getPriority() > $criteria['max_priority']) {
            return false;
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del ProductType
     *
     * @return array Estadísticas del tipo
     */
    public function getStats(): array
    {
        return [
            'value' => $this->value,
            'category' => $this->getCategory(),
            'priority' => $this->getPriority(),
            'features_count' => count($this->getFeatures()),
            'features' => $this->getFeatures(),
            'requires_payment' => $this->requiresPayment(),
            'is_virtual' => $this->isVirtual(),
            'is_recurring' => $this->isRecurring(),
        ];
    }

    /**
     * Genera un hash único para este ProductType
     *
     * @return string Hash único del ProductType
     */
    public function getHash(): string
    {
        return 'product_type_' . $this->value . '_' . substr(md5($this->value), 0, 8);
    }

    /**
     * Verifica si este ProductType es compatible con el sistema de analytics
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Todos los tipos de producto pueden generar analytics
        return true;
    }

    /**
     * Verifica si este ProductType es compatible con el sistema de facturación
     *
     * @return bool True si es compatible con facturación
     */
    public function isBillingCompatible(): bool
    {
        // Solo los tipos que requieren pago son compatibles con facturación
        return $this->requiresPayment();
    }
}
