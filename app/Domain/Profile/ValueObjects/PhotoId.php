<?php

declare(strict_types=1);

namespace App\Domain\Profile\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * PhotoId Value Object
 * 
 * Representa el identificador único de una foto en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del identificador de foto en toda la aplicación.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores enteros positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos PhotoId con el mismo valor son iguales
 * - Integrado con el dominio Profile: Específico para fotos de perfil de usuario
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
final class PhotoId implements JsonSerializable
{
    /**
     * El valor del identificador de foto
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
     * Factory method para crear una nueva instancia de PhotoId
     *
     * @param int $value Valor del identificador
     * @return self Nueva instancia de PhotoId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromInt(int $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear PhotoId desde string
     *
     * @param string $value Valor del identificador como string
     * @return self Nueva instancia de PhotoId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "PhotoId debe ser un número entero válido, se recibió: {$value}"
            );
        }

        $intValue = (int) $value;
        return self::fromInt($intValue);
    }

    /**
     * Factory method para crear PhotoId desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el ID
     * @param string $key Clave del array que contiene el ID
     * @return self Nueva instancia de PhotoId
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
     * Verifica si este PhotoId es igual a otro
     *
     * @param self $other Otro PhotoId para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este PhotoId es mayor que otro
     *
     * @param self $other Otro PhotoId para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este PhotoId es menor que otro
     *
     * @param self $other Otro PhotoId para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Verifica si este PhotoId es mayor o igual que otro
     *
     * @param self $other Otro PhotoId para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Verifica si este PhotoId es menor o igual que otro
     *
     * @param self $other Otro PhotoId para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * Verifica si este PhotoId está en un rango específico
     *
     * @param self $min PhotoId mínimo (inclusivo)
     * @param self $max PhotoId máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Crea un nuevo PhotoId incrementado en 1
     *
     * @return self Nuevo PhotoId incrementado
     */
    public function increment(): self
    {
        return new self($this->value + 1);
    }

    /**
     * Crea un nuevo PhotoId decrementado en 1
     *
     * @return self Nuevo PhotoId decrementado
     */
    public function decrement(): self
    {
        return new self($this->value - 1);
    }

    /**
     * Verifica si este PhotoId representa una foto del sistema (ID <= 1000)
     * Estas suelen ser fotos por defecto, de prueba o del sistema
     *
     * @return bool True si es una foto del sistema
     */
    public function isSystemPhoto(): bool
    {
        return $this->value <= 1000;
    }

    /**
     * Verifica si este PhotoId representa una foto regular de usuario (ID > 1000)
     *
     * @return bool True si es una foto regular
     */
    public function isRegularPhoto(): bool
    {
        return $this->value > 1000;
    }

    /**
     * Verifica si este PhotoId representa una foto premium (ID alto, usuarios premium)
     *
     * @return bool True si es una foto premium
     */
    public function isPremiumPhoto(): bool
    {
        return $this->value > 50000;
    }

    /**
     * Verifica si este PhotoId representa una foto beta (ID bajo, usuarios de prueba)
     *
     * @return bool True si es una foto beta
     */
    public function isBetaPhoto(): bool
    {
        return $this->value >= 1 && $this->value <= 100;
    }

    /**
     * Obtiene el tipo de foto basado en el ID
     *
     * @return string Tipo de foto (beta, system, regular, premium)
     */
    public function getPhotoType(): string
    {
        if ($this->isBetaPhoto()) {
            return 'beta';
        }

        if ($this->isSystemPhoto()) {
            return 'system';
        }

        if ($this->isPremiumPhoto()) {
            return 'premium';
        }

        return 'regular';
    }

    /**
     * Verifica si este PhotoId representa una foto reciente
     * (IDs altos suelen indicar fotos más recientes)
     *
     * @return bool True si es una foto reciente
     */
    public function isRecentPhoto(): bool
    {
        return $this->value > 10000;
    }

    /**
     * Verifica si este PhotoId representa una foto antigua
     * (IDs bajos suelen indicar fotos más antiguas)
     *
     * @return bool True si es una foto antigua
     */
    public function isLegacyPhoto(): bool
    {
        return $this->value <= 5000;
    }

    /**
     * Obtiene el año aproximado de creación basado en el ID
     * (útil para análisis de tendencias y migración)
     *
     * @return int Año aproximado de creación
     */
    public function getEstimatedCreationYear(): int
    {
        // Estimación basada en patrones de IDs (esto puede ajustarse según la lógica del negocio)
        if ($this->value <= 1000) {
            return 2023; // Fotos del sistema y beta
        } elseif ($this->value <= 10000) {
            return 2024; // Fotos regulares tempranas
        } elseif ($this->value <= 50000) {
            return 2024; // Fotos regulares
        } else {
            return 2025; // Fotos premium y recientes
        }
    }

    /**
     * Verifica si este PhotoId está en un rango de alta calidad
     * (fotos que han pasado por procesos de calidad)
     *
     * @return bool True si está en rango de alta calidad
     */
    public function isHighQualityRange(): bool
    {
        return $this->value >= 20000 && $this->value <= 80000;
    }

    /**
     * Verifica si este PhotoId corresponde a una foto que necesita migración
     * (fotos del proyecto antiguo que pueden necesitar procesamiento)
     *
     * @return bool True si necesita migración
     */
    public function needsMigration(): bool
    {
        // Fotos con IDs bajos del proyecto antiguo
        return $this->value <= 10000;
    }

    /**
     * Convierte el PhotoId a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getPhotoType(),
            'is_premium' => $this->isPremiumPhoto(),
            'is_system' => $this->isSystemPhoto(),
            'is_recent' => $this->isRecentPhoto(),
            'is_legacy' => $this->isLegacyPhoto(),
            'estimated_creation_year' => $this->getEstimatedCreationYear(),
            'needs_migration' => $this->needsMigration(),
        ];
    }

    /**
     * Representación en string del PhotoId
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
            'type' => $this->getPhotoType(),
            'is_premium' => $this->isPremiumPhoto(),
            'is_system' => $this->isSystemPhoto(),
            'is_recent' => $this->isRecentPhoto(),
            'estimated_creation_year' => $this->getEstimatedCreationYear(),
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
                "PhotoId debe ser un número entero positivo, se recibió: {$value}"
            );
        }

        if ($value > PHP_INT_MAX) {
            throw new InvalidArgumentException(
                "PhotoId excede el valor máximo permitido: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un PhotoId válido
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
     * Crea un PhotoId desde un valor mixto (int, string, array)
     *
     * @param mixed $value Valor del identificador
     * @param string $arrayKey Clave para arrays (por defecto 'id')
     * @return self Nueva instancia de PhotoId
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
            'PhotoId solo puede crearse desde int, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Crea un PhotoId desde un modelo Eloquent UserImage
     *
     * @param \App\Models\User\UserImage $image Modelo UserImage
     * @return self Nueva instancia de PhotoId
     * @throws InvalidArgumentException Si el modelo no tiene ID válido
     */
    public static function fromUserImage(\App\Models\User\UserImage $image): self
    {
        if (!$image->exists || !$image->getKey()) {
            throw new InvalidArgumentException(
                'El modelo UserImage debe existir y tener un ID válido'
            );
        }

        return self::fromInt($image->getKey());
    }

    /**
     * Crea un PhotoId desde el modelo UserImages del proyecto antiguo
     * (para compatibilidad con migración)
     *
     * @param \App\Models\UserImages $image Modelo UserImages del proyecto antiguo
     * @return self Nueva instancia de PhotoId
     * @throws InvalidArgumentException Si el modelo no tiene ID válido
     */
    public static function fromLegacyUserImages(\App\Models\UserImages $image): self
    {
        if (!$image->exists || !$image->getKey()) {
            throw new InvalidArgumentException(
                'El modelo UserImages debe existir y tener un ID válido'
            );
        }

        return self::fromInt($image->getKey());
    }

    /**
     * Verifica si este PhotoId corresponde a una foto con características específicas
     * (útil para filtros y búsquedas)
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['type'])) {
            if ($this->getPhotoType() !== $criteria['type']) {
                return false;
            }
        }

        if (isset($criteria['premium_only']) && $criteria['premium_only']) {
            if (!$this->isPremiumPhoto()) {
                return false;
            }
        }

        if (isset($criteria['system_only']) && $criteria['system_only']) {
            if (!$this->isSystemPhoto()) {
                return false;
            }
        }

        if (isset($criteria['recent_only']) && $criteria['recent_only']) {
            if (!$this->isRecentPhoto()) {
                return false;
            }
        }

        if (isset($criteria['legacy_only']) && $criteria['legacy_only']) {
            if (!$this->isLegacyPhoto()) {
                return false;
            }
        }

        if (isset($criteria['high_quality_only']) && $criteria['high_quality_only']) {
            if (!$this->isHighQualityRange()) {
                return false;
            }
        }

        if (isset($criteria['needs_migration']) && $criteria['needs_migration']) {
            if (!$this->needsMigration()) {
                return false;
            }
        }

        if (isset($criteria['min_id'])) {
            if ($this->value < $criteria['min_id']) {
                return false;
            }
        }

        if (isset($criteria['max_id'])) {
            if ($this->value > $criteria['max_id']) {
                return false;
            }
        }

        if (isset($criteria['year'])) {
            if ($this->getEstimatedCreationYear() !== $criteria['year']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas básicas del PhotoId
     *
     * @return array Estadísticas de la foto
     */
    public function getStats(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getPhotoType(),
            'is_premium' => $this->isPremiumPhoto(),
            'is_system' => $this->isSystemPhoto(),
            'is_regular' => $this->isRegularPhoto(),
            'is_beta' => $this->isBetaPhoto(),
            'is_recent' => $this->isRecentPhoto(),
            'is_legacy' => $this->isLegacyPhoto(),
            'is_high_quality' => $this->isHighQualityRange(),
            'estimated_creation_year' => $this->getEstimatedCreationYear(),
            'needs_migration' => $this->needsMigration(),
            'creation_era' => $this->getCreationEra(),
        ];
    }

    /**
     * Obtiene la era de creación de la foto
     *
     * @return string Era de creación (legacy, early, mid, recent, premium)
     */
    private function getCreationEra(): string
    {
        if ($this->value <= 100) {
            return 'beta';
        } elseif ($this->value <= 1000) {
            return 'system';
        } elseif ($this->value <= 10000) {
            return 'legacy';
        } elseif ($this->value <= 50000) {
            return 'early';
        } elseif ($this->value <= 100000) {
            return 'mid';
        } else {
            return 'recent';
        }
    }

    /**
     * Verifica si este PhotoId es compatible con el sistema de moderación
     * (fotos que pueden pasar por el flujo de moderación)
     *
     * @return bool True si es compatible con moderación
     */
    public function isModerationCompatible(): bool
    {
        // Las fotos del sistema no necesitan moderación
        return !$this->isSystemPhoto();
    }

    /**
     * Verifica si este PhotoId es compatible con el sistema de analytics
     * (fotos que pueden generar métricas)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        // Las fotos beta y del sistema pueden no generar analytics completos
        return !$this->isBetaPhoto() && !$this->isSystemPhoto();
    }

    /**
     * Verifica si este PhotoId es compatible con el sistema de engagement
     * (fotos que pueden recibir likes, views, etc.)
     *
     * @return bool True si es compatible con engagement
     */
    public function isEngagementCompatible(): bool
    {
        // Solo fotos regulares y premium pueden tener engagement
        return $this->isRegularPhoto() || $this->isPremiumPhoto();
    }

    /**
     * Obtiene la prioridad de procesamiento de la foto
     * (útil para colas de procesamiento)
     *
     * @return int Prioridad (1 = alta, 5 = baja)
     */
    public function getProcessingPriority(): int
    {
        if ($this->isPremiumPhoto()) {
            return 1; // Alta prioridad para fotos premium
        }

        if ($this->isRecentPhoto()) {
            return 2; // Alta prioridad para fotos recientes
        }

        if ($this->isRegularPhoto()) {
            return 3; // Prioridad media para fotos regulares
        }

        if ($this->isLegacyPhoto()) {
            return 4; // Baja prioridad para fotos legacy
        }

        return 5; // Muy baja prioridad para fotos del sistema y beta
    }

    /**
     * Obtiene el nivel de calidad esperado para esta foto
     *
     * @return string Nivel de calidad (low, medium, high, premium)
     */
    public function getExpectedQualityLevel(): string
    {
        if ($this->isPremiumPhoto()) {
            return 'premium';
        }

        if ($this->isHighQualityRange()) {
            return 'high';
        }

        if ($this->isRecentPhoto()) {
            return 'high';
        }

        if ($this->isLegacyPhoto()) {
            return 'medium';
        }

        if ($this->isSystemPhoto()) {
            return 'low';
        }

        return 'medium';
    }
}
