<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * PlanId Value Object
 * 
 * Representa el identificador único de un plan de suscripción en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del identificador de plan en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para identificar
 * planes de suscripción (básico, premium, vip, etc.) que los usuarios pueden adquirir.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores enteros positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos PlanId con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y planes de suscripción
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class PlanId implements JsonSerializable
{
    /**
     * El valor del identificador de plan
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
     * Factory method para crear una nueva instancia de PlanId
     *
     * @param int $value Valor del identificador
     * @return self Nueva instancia de PlanId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromInt(int $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear PlanId desde string
     *
     * @param string $value Valor del identificador como string
     * @return self Nueva instancia de PlanId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "PlanId debe ser un número entero válido, se recibió: {$value}"
            );
        }

        $intValue = (int) $value;
        return self::fromInt($intValue);
    }

    /**
     * Factory method para crear PlanId desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el ID
     * @param string $key Clave del array que contiene el ID
     * @return self Nueva instancia de PlanId
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
     * Verifica si este PlanId es igual a otro
     *
     * @param self $other Otro PlanId para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este PlanId es mayor que otro
     *
     * @param self $other Otro PlanId para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este PlanId es menor que otro
     *
     * @param self $other Otro PlanId para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Verifica si este PlanId es mayor o igual que otro
     *
     * @param self $other Otro PlanId para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Verifica si este PlanId es menor o igual que otro
     *
     * @param self $other Otro PlanId para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * Verifica si este PlanId está en un rango específico
     *
     * @param self $min PlanId mínimo (inclusivo)
     * @param self $max PlanId máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Crea un nuevo PlanId incrementado en 1
     *
     * @return self Nuevo PlanId incrementado
     */
    public function increment(): self
    {
        return new self($this->value + 1);
    }

    /**
     * Crea un nuevo PlanId decrementado en 1
     *
     * @return self Nuevo PlanId decrementado
     */
    public function decrement(): self
    {
        return new self($this->value - 1);
    }

    /**
     * Verifica si este PlanId representa un plan básico (ID 1-10)
     *
     * @return bool True si es un plan básico
     */
    public function isBasicPlan(): bool
    {
        return $this->value >= 1 && $this->value <= 10;
    }

    /**
     * Verifica si este PlanId representa un plan premium (ID 11-20)
     *
     * @return bool True si es un plan premium
     */
    public function isPremiumPlan(): bool
    {
        return $this->value >= 11 && $this->value <= 20;
    }

    /**
     * Verifica si este PlanId representa un plan VIP (ID 21-30)
     *
     * @return bool True si es un plan VIP
     */
    public function isVipPlan(): bool
    {
        return $this->value >= 21 && $this->value <= 30;
    }

    /**
     * Verifica si este PlanId representa un plan empresarial (ID 31-40)
     *
     * @return bool True si es un plan empresarial
     */
    public function isEnterprisePlan(): bool
    {
        return $this->value >= 31 && $this->value <= 40;
    }

    /**
     * Verifica si este PlanId representa un plan de prueba (ID 100-199)
     *
     * @return bool True si es un plan de prueba
     */
    public function isTrialPlan(): bool
    {
        return $this->value >= 100 && $this->value <= 199;
    }

    /**
     * Verifica si este PlanId representa un plan promocional (ID 200-299)
     *
     * @return bool True si es un plan promocional
     */
    public function isPromotionalPlan(): bool
    {
        return $this->value >= 200 && $this->value <= 299;
    }

    /**
     * Verifica si este PlanId representa un plan del sistema (ID <= 1000)
     *
     * @return bool True si es un plan del sistema
     */
    public function isSystemPlan(): bool
    {
        return $this->value <= 1000;
    }

    /**
     * Obtiene el tipo de plan basado en el ID
     *
     * @return string Tipo de plan (basic, premium, vip, enterprise, trial, promotional, system)
     */
    public function getPlanType(): string
    {
        if ($this->isTrialPlan()) {
            return 'trial';
        }

        if ($this->isPromotionalPlan()) {
            return 'promotional';
        }

        if ($this->isEnterprisePlan()) {
            return 'enterprise';
        }

        if ($this->isVipPlan()) {
            return 'vip';
        }

        if ($this->isPremiumPlan()) {
            return 'premium';
        }

        if ($this->isBasicPlan()) {
            return 'basic';
        }

        if ($this->isSystemPlan()) {
            return 'system';
        }

        return 'custom';
    }

    /**
     * Verifica si este PlanId representa un plan activo
     * (planes del sistema y principales están activos)
     *
     * @return bool True si es un plan activo
     */
    public function isActivePlan(): bool
    {
        return $this->isBasicPlan() || $this->isPremiumPlan() || $this->isVipPlan() || $this->isEnterprisePlan();
    }

    /**
     * Verifica si este PlanId representa un plan de pago
     * (excluye planes de prueba y promocionales)
     *
     * @return bool True si es un plan de pago
     */
    public function isPaidPlan(): bool
    {
        return $this->isActivePlan() && !$this->isTrialPlan() && !$this->isPromotionalPlan();
    }

    /**
     * Verifica si este PlanId representa un plan gratuito
     * (planes básicos y de prueba)
     *
     * @return bool True si es un plan gratuito
     */
    public function isFreePlan(): bool
    {
        return $this->isBasicPlan() || $this->isTrialPlan();
    }

    /**
     * Obtiene el nivel del plan (1-5, donde 5 es el más alto)
     *
     * @return int Nivel del plan
     */
    public function getPlanLevel(): int
    {
        if ($this->isEnterprisePlan()) {
            return 5;
        }

        if ($this->isVipPlan()) {
            return 4;
        }

        if ($this->isPremiumPlan()) {
            return 3;
        }

        if ($this->isBasicPlan()) {
            return 2;
        }

        if ($this->isTrialPlan() || $this->isPromotionalPlan()) {
            return 1;
        }

        return 1; // Default level
    }

    /**
     * Verifica si este PlanId tiene características específicas
     *
     * @param string $feature Característica a verificar
     * @return bool True si tiene la característica
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->getPlanFeatures();
        return in_array($feature, $features);
    }

    /**
     * Obtiene las características del plan basadas en el tipo
     *
     * @return array Lista de características del plan
     */
    public function getPlanFeatures(): array
    {
        $features = [];

        if ($this->isBasicPlan()) {
            $features = ['basic_matching', 'limited_messages', 'basic_filters'];
        } elseif ($this->isPremiumPlan()) {
            $features = ['unlimited_matching', 'unlimited_messages', 'advanced_filters', 'priority_support'];
        } elseif ($this->isVipPlan()) {
            $features = ['unlimited_matching', 'unlimited_messages', 'advanced_filters', 'priority_support', 'vip_features', 'exclusive_events'];
        } elseif ($this->isEnterprisePlan()) {
            $features = ['unlimited_matching', 'unlimited_messages', 'advanced_filters', 'priority_support', 'vip_features', 'exclusive_events', 'enterprise_features', 'dedicated_support'];
        } elseif ($this->isTrialPlan()) {
            $features = ['trial_matching', 'trial_messages', 'basic_filters'];
        } elseif ($this->isPromotionalPlan()) {
            $features = ['promotional_matching', 'promotional_messages', 'promotional_features'];
        }

        return $features;
    }

    /**
     * Convierte el PlanId a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getPlanType(),
            'level' => $this->getPlanLevel(),
            'is_active' => $this->isActivePlan(),
            'is_paid' => $this->isPaidPlan(),
            'is_free' => $this->isFreePlan(),
            'features' => $this->getPlanFeatures(),
        ];
    }

    /**
     * Representación en string del PlanId
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
            'type' => $this->getPlanType(),
            'level' => $this->getPlanLevel(),
            'is_active' => $this->isActivePlan(),
            'is_paid' => $this->isPaidPlan(),
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
                "PlanId debe ser un número entero positivo, se recibió: {$value}"
            );
        }

        if ($value > PHP_INT_MAX) {
            throw new InvalidArgumentException(
                "PlanId excede el valor máximo permitido: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un PlanId válido
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
     * Crea un PlanId desde un valor mixto (int, string, array)
     *
     * @param mixed $value Valor del identificador
     * @param string $arrayKey Clave para arrays (por defecto 'id')
     * @return self Nueva instancia de PlanId
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
            'PlanId solo puede crearse desde int, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Verifica si este PlanId corresponde a un plan con características específicas
     * (útil para filtros y búsquedas)
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['type'])) {
            if ($this->getPlanType() !== $criteria['type']) {
                return false;
            }
        }

        if (isset($criteria['level'])) {
            if ($this->getPlanLevel() !== $criteria['level']) {
                return false;
            }
        }

        if (isset($criteria['paid_only']) && $criteria['paid_only']) {
            if (!$this->isPaidPlan()) {
                return false;
            }
        }

        if (isset($criteria['free_only']) && $criteria['free_only']) {
            if (!$this->isFreePlan()) {
                return false;
            }
        }

        if (isset($criteria['active_only']) && $criteria['active_only']) {
            if (!$this->isActivePlan()) {
                return false;
            }
        }

        if (isset($criteria['feature'])) {
            if (!$this->hasFeature($criteria['feature'])) {
                return false;
            }
        }

        if (isset($criteria['min_level'])) {
            if ($this->getPlanLevel() < $criteria['min_level']) {
                return false;
            }
        }

        if (isset($criteria['max_level'])) {
            if ($this->getPlanLevel() > $criteria['max_level']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del PlanId
     *
     * @return array Estadísticas del plan
     */
    public function getStats(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getPlanType(),
            'level' => $this->getPlanLevel(),
            'is_active' => $this->isActivePlan(),
            'is_paid' => $this->isPaidPlan(),
            'is_free' => $this->isFreePlan(),
            'features_count' => count($this->getPlanFeatures()),
            'features' => $this->getPlanFeatures(),
        ];
    }

    /**
     * Genera un hash único para este PlanId (útil para cache keys)
     *
     * @return string Hash único del PlanId
     */
    public function getHash(): string
    {
        return 'plan_' . $this->value . '_' . substr(md5((string) $this->value), 0, 8);
    }

    /**
     * Verifica si este PlanId es compatible con el sistema de analytics
     * (planes que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Todos los planes activos pueden generar analytics
        return $this->isActivePlan();
    }

    /**
     * Verifica si este PlanId es compatible con el sistema de facturación
     * (planes que pueden generar facturas)
     *
     * @return bool True si es compatible con facturación
     */
    public function isBillingCompatible(): bool
    {
        // Solo planes de pago pueden generar facturas
        return $this->isPaidPlan();
    }

    /**
     * Obtiene la prioridad de procesamiento del plan
     * (útil para colas de procesamiento)
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        if ($this->isEnterprisePlan()) {
            return 1; // Alta prioridad para planes empresariales
        }

        if ($this->isVipPlan()) {
            return 2; // Alta prioridad para planes VIP
        }

        if ($this->isPremiumPlan()) {
            return 3; // Prioridad media para planes premium
        }

        if ($this->isBasicPlan()) {
            return 4; // Prioridad media-baja para planes básicos
        }

        return 5; // Baja prioridad para planes de prueba y promocionales
    }
}
