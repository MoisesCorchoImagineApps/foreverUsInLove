<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * GiftCategoryId Value Object
 * 
 * Representa el identificador único de una categoría de regalos en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del identificador de categoría de regalos en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Commerce y se utiliza para identificar
 * categorías de regalos virtuales que organizan y clasifican los regalos en la aplicación.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores enteros positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos GiftCategoryId con el mismo valor son iguales
 * - Commerce-specific: Específico para el dominio de comercio y categorías de regalos
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class GiftCategoryId implements JsonSerializable
{
    /**
     * El valor del identificador de categoría de regalos
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
     * Factory method para crear una nueva instancia de GiftCategoryId
     *
     * @param int $value Valor del identificador
     * @return self Nueva instancia de GiftCategoryId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromInt(int $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear GiftCategoryId desde string
     *
     * @param string $value Valor del identificador como string
     * @return self Nueva instancia de GiftCategoryId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "GiftCategoryId debe ser un número entero válido, se recibió: {$value}"
            );
        }

        $intValue = (int) $value;
        return self::fromInt($intValue);
    }

    /**
     * Factory method para crear GiftCategoryId desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el ID
     * @param string $key Clave del array que contiene el ID
     * @return self Nueva instancia de GiftCategoryId
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
     * Verifica si este GiftCategoryId es igual a otro
     *
     * @param self $other Otro GiftCategoryId para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este GiftCategoryId es mayor que otro
     *
     * @param self $other Otro GiftCategoryId para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este GiftCategoryId es menor que otro
     *
     * @param self $other Otro GiftCategoryId para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Verifica si este GiftCategoryId es mayor o igual que otro
     *
     * @param self $other Otro GiftCategoryId para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Verifica si este GiftCategoryId es menor o igual que otro
     *
     * @param self $other Otro GiftCategoryId para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * Verifica si este GiftCategoryId está en un rango específico
     *
     * @param self $min GiftCategoryId mínimo (inclusivo)
     * @param self $max GiftCategoryId máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Crea un nuevo GiftCategoryId incrementado en 1
     *
     * @return self Nuevo GiftCategoryId incrementado
     */
    public function increment(): self
    {
        return new self($this->value + 1);
    }

    /**
     * Crea un nuevo GiftCategoryId decrementado en 1
     *
     * @return self Nuevo GiftCategoryId decrementado
     */
    public function decrement(): self
    {
        return new self($this->value - 1);
    }

    /**
     * Verifica si este GiftCategoryId representa una categoría del sistema (ID 1-10)
     * (categorías básicas predefinidas)
     *
     * @return bool True si es una categoría del sistema
     */
    public function isSystemCategory(): bool
    {
        return $this->value >= 1 && $this->value <= 10;
    }

    /**
     * Verifica si este GiftCategoryId representa una categoría personalizada (ID 11-100)
     * (categorías creadas por administradores)
     *
     * @return bool True si es una categoría personalizada
     */
    public function isCustomCategory(): bool
    {
        return $this->value >= 11 && $this->value <= 100;
    }

    /**
     * Verifica si este GiftCategoryId representa una categoría premium (ID 101-200)
     * (categorías con regalos premium y exclusivos)
     *
     * @return bool True si es una categoría premium
     */
    public function isPremiumCategory(): bool
    {
        return $this->value >= 101 && $this->value <= 200;
    }

    /**
     * Verifica si este GiftCategoryId representa una categoría estacional (ID 201-300)
     * (categorías disponibles solo en temporadas específicas)
     *
     * @return bool True si es una categoría estacional
     */
    public function isSeasonalCategory(): bool
    {
        return $this->value >= 201 && $this->value <= 300;
    }

    /**
     * Verifica si este GiftCategoryId representa una categoría promocional (ID 301-400)
     * (categorías con descuentos y ofertas especiales)
     *
     * @return bool True si es una categoría promocional
     */
    public function isPromotionalCategory(): bool
    {
        return $this->value >= 301 && $this->value <= 400;
    }

    /**
     * Verifica si este GiftCategoryId representa una categoría beta (ID 1-5)
     * (categorías de prueba durante desarrollo)
     *
     * @return bool True si es una categoría beta
     */
    public function isBetaCategory(): bool
    {
        return $this->value >= 1 && $this->value <= 5;
    }

    /**
     * Obtiene el tipo de categoría basado en el ID
     *
     * @return string Tipo de categoría (system, custom, premium, seasonal, promotional, beta)
     */
    public function getCategoryType(): string
    {
        if ($this->isBetaCategory()) {
            return 'beta';
        }

        if ($this->isPromotionalCategory()) {
            return 'promotional';
        }

        if ($this->isSeasonalCategory()) {
            return 'seasonal';
        }

        if ($this->isPremiumCategory()) {
            return 'premium';
        }

        if ($this->isCustomCategory()) {
            return 'custom';
        }

        if ($this->isSystemCategory()) {
            return 'system';
        }

        return 'unknown';
    }

    /**
     * Verifica si este GiftCategoryId representa una categoría activa
     * (categorías disponibles para uso)
     *
     * @return bool True si es una categoría activa
     */
    public function isActiveCategory(): bool
    {
        return $this->isSystemCategory() || $this->isCustomCategory() || $this->isPremiumCategory();
    }

    /**
     * Verifica si este GiftCategoryId representa una categoría con características especiales
     * (categorías premium y promocionales suelen tener características especiales)
     *
     * @return bool True si tiene características especiales
     */
    public function hasSpecialFeatures(): bool
    {
        return $this->isPremiumCategory() || $this->isPromotionalCategory() || $this->isSeasonalCategory();
    }

    /**
     * Verifica si este GiftCategoryId representa una categoría limitada
     * (categorías promocionales y estacionales son limitadas)
     *
     * @return bool True si es una categoría limitada
     */
    public function isLimitedCategory(): bool
    {
        return $this->isPromotionalCategory() || $this->isSeasonalCategory();
    }

    /**
     * Obtiene el nivel de la categoría (1-5, donde 5 es el más alto)
     *
     * @return int Nivel de la categoría
     */
    public function getCategoryLevel(): int
    {
        if ($this->isPremiumCategory()) {
            return 5;
        }

        if ($this->isCustomCategory()) {
            return 4;
        }

        if ($this->isSystemCategory()) {
            return 3;
        }

        if ($this->isPromotionalCategory() || $this->isSeasonalCategory()) {
            return 2;
        }

        if ($this->isBetaCategory()) {
            return 1;
        }

        return 1; // Default level
    }

    /**
     * Verifica si este GiftCategoryId tiene características específicas
     *
     * @param string $feature Característica a verificar
     * @return bool True si tiene la característica
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->getCategoryFeatures();
        return in_array($feature, $features);
    }

    /**
     * Obtiene las características de la categoría basadas en el tipo
     *
     * @return array Lista de características de la categoría
     */
    public function getCategoryFeatures(): array
    {
        $features = [];

        if ($this->isSystemCategory()) {
            $features = ['basic_gifts', 'standard_organization', 'default_visibility'];
        } elseif ($this->isCustomCategory()) {
            $features = ['custom_gifts', 'flexible_organization', 'admin_controlled'];
        } elseif ($this->isPremiumCategory()) {
            $features = ['premium_gifts', 'exclusive_organization', 'vip_access', 'special_effects'];
        } elseif ($this->isPromotionalCategory()) {
            $features = ['discounted_gifts', 'limited_time', 'special_pricing'];
        } elseif ($this->isSeasonalCategory()) {
            $features = ['seasonal_gifts', 'holiday_themed', 'limited_availability', 'special_bonuses'];
        } elseif ($this->isBetaCategory()) {
            $features = ['beta_gifts', 'testing_purpose', 'limited_availability'];
        }

        return $features;
    }

    /**
     * Convierte el GiftCategoryId a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getCategoryType(),
            'level' => $this->getCategoryLevel(),
            'is_active' => $this->isActiveCategory(),
            'has_special_features' => $this->hasSpecialFeatures(),
            'is_limited' => $this->isLimitedCategory(),
            'features' => $this->getCategoryFeatures(),
        ];
    }

    /**
     * Representación en string del GiftCategoryId
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
            'type' => $this->getCategoryType(),
            'level' => $this->getCategoryLevel(),
            'is_active' => $this->isActiveCategory(),
            'has_special_features' => $this->hasSpecialFeatures(),
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
                "GiftCategoryId debe ser un número entero positivo, se recibió: {$value}"
            );
        }

        if ($value > PHP_INT_MAX) {
            throw new InvalidArgumentException(
                "GiftCategoryId excede el valor máximo permitido: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un GiftCategoryId válido
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
     * Crea un GiftCategoryId desde un valor mixto (int, string, array)
     *
     * @param mixed $value Valor del identificador
     * @param string $arrayKey Clave para arrays (por defecto 'id')
     * @return self Nueva instancia de GiftCategoryId
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
            'GiftCategoryId solo puede crearse desde int, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Verifica si este GiftCategoryId corresponde a una categoría con características específicas
     * (útil para filtros y búsquedas)
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['type'])) {
            if ($this->getCategoryType() !== $criteria['type']) {
                return false;
            }
        }

        if (isset($criteria['level'])) {
            if ($this->getCategoryLevel() !== $criteria['level']) {
                return false;
            }
        }

        if (isset($criteria['active_only']) && $criteria['active_only']) {
            if (!$this->isActiveCategory()) {
                return false;
            }
        }

        if (isset($criteria['special_features_only']) && $criteria['special_features_only']) {
            if (!$this->hasSpecialFeatures()) {
                return false;
            }
        }

        if (isset($criteria['limited_only']) && $criteria['limited_only']) {
            if (!$this->isLimitedCategory()) {
                return false;
            }
        }

        if (isset($criteria['feature'])) {
            if (!$this->hasFeature($criteria['feature'])) {
                return false;
            }
        }

        if (isset($criteria['min_level'])) {
            if ($this->getCategoryLevel() < $criteria['min_level']) {
                return false;
            }
        }

        if (isset($criteria['max_level'])) {
            if ($this->getCategoryLevel() > $criteria['max_level']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del GiftCategoryId
     *
     * @return array Estadísticas de la categoría
     */
    public function getStats(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getCategoryType(),
            'level' => $this->getCategoryLevel(),
            'is_active' => $this->isActiveCategory(),
            'has_special_features' => $this->hasSpecialFeatures(),
            'is_limited' => $this->isLimitedCategory(),
            'features_count' => count($this->getCategoryFeatures()),
            'features' => $this->getCategoryFeatures(),
        ];
    }

    /**
     * Genera un hash único para este GiftCategoryId (útil para cache keys)
     *
     * @return string Hash único del GiftCategoryId
     */
    public function getHash(): string
    {
        return 'gift_category_' . $this->value . '_' . substr(md5((string) $this->value), 0, 8);
    }

    /**
     * Verifica si este GiftCategoryId es compatible con el sistema de analytics
     * (categorías que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Todas las categorías activas pueden generar analytics
        return $this->isActiveCategory();
    }

    /**
     * Verifica si este GiftCategoryId es compatible con el sistema de recomendaciones
     * (categorías que pueden aparecer en recomendaciones)
     *
     * @return bool True si es compatible con recomendaciones
     */
    public function isRecommendationCompatible(): bool
    {
        // Todas las categorías activas pueden aparecer en recomendaciones
        return $this->isActiveCategory();
    }

    /**
     * Obtiene la prioridad de procesamiento de la categoría
     * (útil para colas de procesamiento)
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        if ($this->isPremiumCategory()) {
            return 1; // Alta prioridad para categorías premium
        }

        if ($this->isLimitedCategory()) {
            return 2; // Alta prioridad para categorías limitadas
        }

        if ($this->isCustomCategory()) {
            return 3; // Prioridad media para categorías personalizadas
        }

        if ($this->isSystemCategory()) {
            return 4; // Prioridad media-baja para categorías del sistema
        }

        return 5; // Baja prioridad para categorías beta
    }
}
