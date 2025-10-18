<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * SubscriptionId Value Object
 * 
 * Representa el identificador único de una suscripción en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del identificador de suscripción en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para identificar
 * suscripciones de usuarios a planes de pago (básico, premium, vip, etc.) en la aplicación.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores enteros positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos SubscriptionId con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y suscripciones
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class SubscriptionId implements JsonSerializable
{
    /**
     * El valor del identificador de suscripción
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
     * Factory method para crear una nueva instancia de SubscriptionId
     *
     * @param int $value Valor del identificador
     * @return self Nueva instancia de SubscriptionId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromInt(int $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear SubscriptionId desde string
     *
     * @param string $value Valor del identificador como string
     * @return self Nueva instancia de SubscriptionId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "SubscriptionId debe ser un número entero válido, se recibió: {$value}"
            );
        }

        $intValue = (int) $value;
        return self::fromInt($intValue);
    }

    /**
     * Factory method para crear SubscriptionId desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el ID
     * @param string $key Clave del array que contiene el ID
     * @return self Nueva instancia de SubscriptionId
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
     * Verifica si este SubscriptionId es igual a otro
     *
     * @param self $other Otro SubscriptionId para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este SubscriptionId es mayor que otro
     *
     * @param self $other Otro SubscriptionId para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este SubscriptionId es menor que otro
     *
     * @param self $other Otro SubscriptionId para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Verifica si este SubscriptionId es mayor o igual que otro
     *
     * @param self $other Otro SubscriptionId para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Verifica si este SubscriptionId es menor o igual que otro
     *
     * @param self $other Otro SubscriptionId para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * Verifica si este SubscriptionId está en un rango específico
     *
     * @param self $min SubscriptionId mínimo (inclusivo)
     * @param self $max SubscriptionId máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Crea un nuevo SubscriptionId incrementado en 1
     *
     * @return self Nuevo SubscriptionId incrementado
     */
    public function increment(): self
    {
        return new self($this->value + 1);
    }

    /**
     * Crea un nuevo SubscriptionId decrementado en 1
     *
     * @return self Nuevo SubscriptionId decrementado
     */
    public function decrement(): self
    {
        return new self($this->value - 1);
    }

    /**
     * Verifica si este SubscriptionId representa una suscripción básica (ID 1-1000)
     * (suscripciones a planes básicos)
     *
     * @return bool True si es una suscripción básica
     */
    public function isBasicSubscription(): bool
    {
        return $this->value >= 1 && $this->value <= 1000;
    }

    /**
     * Verifica si este SubscriptionId representa una suscripción premium (ID 1001-5000)
     * (suscripciones a planes premium)
     *
     * @return bool True si es una suscripción premium
     */
    public function isPremiumSubscription(): bool
    {
        return $this->value >= 1001 && $this->value <= 5000;
    }

    /**
     * Verifica si este SubscriptionId representa una suscripción VIP (ID 5001-10000)
     * (suscripciones a planes VIP)
     *
     * @return bool True si es una suscripción VIP
     */
    public function isVipSubscription(): bool
    {
        return $this->value >= 5001 && $this->value <= 10000;
    }

    /**
     * Verifica si este SubscriptionId representa una suscripción empresarial (ID 10001-20000)
     * (suscripciones a planes empresariales)
     *
     * @return bool True si es una suscripción empresarial
     */
    public function isEnterpriseSubscription(): bool
    {
        return $this->value >= 10001 && $this->value <= 20000;
    }

    /**
     * Verifica si este SubscriptionId representa una suscripción de prueba (ID 20001-30000)
     * (suscripciones de prueba gratuitas)
     *
     * @return bool True si es una suscripción de prueba
     */
    public function isTrialSubscription(): bool
    {
        return $this->value >= 20001 && $this->value <= 30000;
    }

    /**
     * Verifica si este SubscriptionId representa una suscripción promocional (ID 30001-40000)
     * (suscripciones con descuentos especiales)
     *
     * @return bool True si es una suscripción promocional
     */
    public function isPromotionalSubscription(): bool
    {
        return $this->value >= 30001 && $this->value <= 40000;
    }

    /**
     * Verifica si este SubscriptionId representa una suscripción del sistema (ID <= 100)
     * (suscripciones internas del sistema)
     *
     * @return bool True si es una suscripción del sistema
     */
    public function isSystemSubscription(): bool
    {
        return $this->value <= 100;
    }

    /**
     * Obtiene el tipo de suscripción basado en el ID
     *
     * @return string Tipo de suscripción (system, basic, premium, vip, enterprise, trial, promotional)
     */
    public function getSubscriptionType(): string
    {
        if ($this->isSystemSubscription()) {
            return 'system';
        }

        if ($this->isTrialSubscription()) {
            return 'trial';
        }

        if ($this->isPromotionalSubscription()) {
            return 'promotional';
        }

        if ($this->isEnterpriseSubscription()) {
            return 'enterprise';
        }

        if ($this->isVipSubscription()) {
            return 'vip';
        }

        if ($this->isPremiumSubscription()) {
            return 'premium';
        }

        if ($this->isBasicSubscription()) {
            return 'basic';
        }

        return 'custom';
    }

    /**
     * Verifica si este SubscriptionId representa una suscripción activa
     * (suscripciones que están en uso)
     *
     * @return bool True si es una suscripción activa
     */
    public function isActiveSubscription(): bool
    {
        return $this->isBasicSubscription() || $this->isPremiumSubscription() || $this->isVipSubscription() || $this->isEnterpriseSubscription();
    }

    /**
     * Verifica si este SubscriptionId representa una suscripción de pago
     * (excluye suscripciones de prueba y promocionales)
     *
     * @return bool True si es una suscripción de pago
     */
    public function isPaidSubscription(): bool
    {
        return $this->isActiveSubscription() && !$this->isTrialSubscription() && !$this->isPromotionalSubscription();
    }

    /**
     * Verifica si este SubscriptionId representa una suscripción gratuita
     * (suscripciones básicas y de prueba)
     *
     * @return bool True si es una suscripción gratuita
     */
    public function isFreeSubscription(): bool
    {
        return $this->isBasicSubscription() || $this->isTrialSubscription();
    }

    /**
     * Obtiene el nivel de la suscripción (1-5, donde 5 es el más alto)
     *
     * @return int Nivel de la suscripción
     */
    public function getSubscriptionLevel(): int
    {
        if ($this->isEnterpriseSubscription()) {
            return 5;
        }

        if ($this->isVipSubscription()) {
            return 4;
        }

        if ($this->isPremiumSubscription()) {
            return 3;
        }

        if ($this->isBasicSubscription()) {
            return 2;
        }

        if ($this->isTrialSubscription() || $this->isPromotionalSubscription()) {
            return 1;
        }

        return 1; // Default level
    }

    /**
     * Verifica si este SubscriptionId tiene características específicas
     *
     * @param string $feature Característica a verificar
     * @return bool True si tiene la característica
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->getSubscriptionFeatures();
        return in_array($feature, $features);
    }

    /**
     * Obtiene las características de la suscripción basadas en el tipo
     *
     * @return array Lista de características de la suscripción
     */
    public function getSubscriptionFeatures(): array
    {
        $features = [];

        if ($this->isBasicSubscription()) {
            $features = ['basic_matching', 'limited_messages', 'basic_filters'];
        } elseif ($this->isPremiumSubscription()) {
            $features = ['unlimited_matching', 'unlimited_messages', 'advanced_filters', 'priority_support'];
        } elseif ($this->isVipSubscription()) {
            $features = ['unlimited_matching', 'unlimited_messages', 'advanced_filters', 'priority_support', 'vip_features', 'exclusive_events'];
        } elseif ($this->isEnterpriseSubscription()) {
            $features = ['unlimited_matching', 'unlimited_messages', 'advanced_filters', 'priority_support', 'vip_features', 'exclusive_events', 'enterprise_features', 'dedicated_support'];
        } elseif ($this->isTrialSubscription()) {
            $features = ['trial_matching', 'trial_messages', 'basic_filters'];
        } elseif ($this->isPromotionalSubscription()) {
            $features = ['promotional_matching', 'promotional_messages', 'promotional_features'];
        }

        return $features;
    }

    /**
     * Convierte el SubscriptionId a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getSubscriptionType(),
            'level' => $this->getSubscriptionLevel(),
            'is_active' => $this->isActiveSubscription(),
            'is_paid' => $this->isPaidSubscription(),
            'is_free' => $this->isFreeSubscription(),
            'features' => $this->getSubscriptionFeatures(),
        ];
    }

    /**
     * Representación en string del SubscriptionId
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
            'type' => $this->getSubscriptionType(),
            'level' => $this->getSubscriptionLevel(),
            'is_active' => $this->isActiveSubscription(),
            'is_paid' => $this->isPaidSubscription(),
        ];
    }

    /**
     * Valida que el valor del ID sea válido
     *
     * @param int $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(int $value): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException(
                "SubscriptionId debe ser un número entero positivo, se recibió: {$value}"
            );
        }

        if ($value > PHP_INT_MAX) {
            throw new InvalidArgumentException(
                "SubscriptionId excede el valor máximo permitido: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un SubscriptionId válido
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

            if (is_string($value) && is_numeric($value)) {
                self::validate((int) $value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea un SubscriptionId desde un valor mixto (int, string, array)
     *
     * @param mixed $value Valor del identificador
     * @param string $arrayKey Clave para arrays (por defecto 'id')
     * @return self Nueva instancia de SubscriptionId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value, string $arrayKey = 'id'): self
    {
        if (is_int($value)) {
            return self::fromInt($value);
        }

        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_array($value)) {
            return self::fromArray($value, $arrayKey);
        }

        throw new InvalidArgumentException(
            'SubscriptionId solo puede crearse desde int, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Verifica si este SubscriptionId corresponde a una suscripción con características específicas
     * (útil para filtros y búsquedas)
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['type'])) {
            if ($this->getSubscriptionType() !== $criteria['type']) {
                return false;
            }
        }

        if (isset($criteria['level'])) {
            if ($this->getSubscriptionLevel() !== $criteria['level']) {
                return false;
            }
        }

        if (isset($criteria['paid_only']) && $criteria['paid_only']) {
            if (!$this->isPaidSubscription()) {
                return false;
            }
        }

        if (isset($criteria['free_only']) && $criteria['free_only']) {
            if (!$this->isFreeSubscription()) {
                return false;
            }
        }

        if (isset($criteria['active_only']) && $criteria['active_only']) {
            if (!$this->isActiveSubscription()) {
                return false;
            }
        }

        if (isset($criteria['feature'])) {
            if (!$this->hasFeature($criteria['feature'])) {
                return false;
            }
        }

        if (isset($criteria['min_level'])) {
            if ($this->getSubscriptionLevel() < $criteria['min_level']) {
                return false;
            }
        }

        if (isset($criteria['max_level'])) {
            if ($this->getSubscriptionLevel() > $criteria['max_level']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del SubscriptionId
     *
     * @return array Estadísticas de la suscripción
     */
    public function getStats(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getSubscriptionType(),
            'level' => $this->getSubscriptionLevel(),
            'is_active' => $this->isActiveSubscription(),
            'is_paid' => $this->isPaidSubscription(),
            'is_free' => $this->isFreeSubscription(),
            'features_count' => count($this->getSubscriptionFeatures()),
            'features' => $this->getSubscriptionFeatures(),
        ];
    }

    /**
     * Genera un hash único para este SubscriptionId (útil para cache keys)
     *
     * @return string Hash único del SubscriptionId
     */
    public function getHash(): string
    {
        return 'subscription_' . $this->value . '_' . substr(md5((string) $this->value), 0, 8);
    }

    /**
     * Verifica si este SubscriptionId es compatible con el sistema de analytics
     * (suscripciones que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Todas las suscripciones activas pueden generar analytics
        return $this->isActiveSubscription();
    }

    /**
     * Verifica si este SubscriptionId es compatible con el sistema de facturación
     * (suscripciones que pueden generar facturas)
     *
     * @return bool True si es compatible con facturación
     */
    public function isBillingCompatible(): bool
    {
        // Solo suscripciones de pago pueden generar facturas
        return $this->isPaidSubscription();
    }

    /**
     * Obtiene la prioridad de procesamiento de la suscripción
     * (útil para colas de procesamiento)
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        if ($this->isEnterpriseSubscription()) {
            return 1; // Alta prioridad para suscripciones empresariales
        }

        if ($this->isVipSubscription()) {
            return 2; // Alta prioridad para suscripciones VIP
        }

        if ($this->isPremiumSubscription()) {
            return 3; // Prioridad media para suscripciones premium
        }

        if ($this->isBasicSubscription()) {
            return 4; // Prioridad media-baja para suscripciones básicas
        }

        return 5; // Baja prioridad para suscripciones de prueba y promocionales
    }
}
