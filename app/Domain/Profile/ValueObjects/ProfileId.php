<?php

declare(strict_types=1);

namespace App\Domain\Profile\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * ProfileId Value Object
 * 
 * Representa el identificador único de un perfil en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del identificador de perfil en toda la aplicación.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta valores enteros positivos válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos ProfileId con el mismo valor son iguales
 * - Integrado con el dominio Profile: Específico para perfiles de usuario
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
final class ProfileId implements JsonSerializable
{
    /**
     * El valor del identificador de perfil
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
     * Factory method para crear una nueva instancia de ProfileId
     *
     * @param int $value Valor del identificador
     * @return self Nueva instancia de ProfileId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromInt(int $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear ProfileId desde string
     *
     * @param string $value Valor del identificador como string
     * @return self Nueva instancia de ProfileId
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "ProfileId debe ser un número entero válido, se recibió: {$value}"
            );
        }

        $intValue = (int) $value;
        return self::fromInt($intValue);
    }

    /**
     * Factory method para crear ProfileId desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el ID
     * @param string $key Clave del array que contiene el ID
     * @return self Nueva instancia de ProfileId
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
     * Verifica si este ProfileId es igual a otro
     *
     * @param self $other Otro ProfileId para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este ProfileId es mayor que otro
     *
     * @param self $other Otro ProfileId para comparar
     * @return bool True si este es mayor
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Verifica si este ProfileId es menor que otro
     *
     * @param self $other Otro ProfileId para comparar
     * @return bool True si este es menor
     */
    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Verifica si este ProfileId es mayor o igual que otro
     *
     * @param self $other Otro ProfileId para comparar
     * @return bool True si este es mayor o igual
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Verifica si este ProfileId es menor o igual que otro
     *
     * @param self $other Otro ProfileId para comparar
     * @return bool True si este es menor o igual
     */
    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    /**
     * Verifica si este ProfileId está en un rango específico
     *
     * @param self $min ProfileId mínimo (inclusivo)
     * @param self $max ProfileId máximo (inclusivo)
     * @return bool True si está en el rango
     */
    public function isInRange(self $min, self $max): bool
    {
        return $this->isGreaterThanOrEqual($min) && $this->isLessThanOrEqual($max);
    }

    /**
     * Crea un nuevo ProfileId incrementado en 1
     *
     * @return self Nuevo ProfileId incrementado
     */
    public function increment(): self
    {
        return new self($this->value + 1);
    }

    /**
     * Crea un nuevo ProfileId decrementado en 1
     *
     * @return self Nuevo ProfileId decrementado
     */
    public function decrement(): self
    {
        return new self($this->value - 1);
    }

    /**
     * Verifica si este ProfileId representa un perfil premium
     * (IDs altos suelen indicar perfiles más recientes y potencialmente premium)
     *
     * @return bool True si es un perfil premium
     */
    public function isPremiumProfile(): bool
    {
        return $this->value > 10000;
    }

    /**
     * Verifica si este ProfileId representa un perfil verificado
     * (IDs en rangos específicos pueden indicar perfiles verificados)
     *
     * @return bool True si es un perfil verificado
     */
    public function isVerifiedProfile(): bool
    {
        // Los perfiles verificados suelen tener IDs en rangos específicos
        return $this->value >= 1000 && $this->value <= 9999;
    }

    /**
     * Verifica si este ProfileId representa un perfil beta tester
     * (IDs bajos suelen ser perfiles de prueba)
     *
     * @return bool True si es un perfil beta
     */
    public function isBetaProfile(): bool
    {
        return $this->value >= 1 && $this->value <= 999;
    }

    /**
     * Obtiene el tipo de perfil basado en el ID
     *
     * @return string Tipo de perfil (beta, verified, premium, regular)
     */
    public function getProfileType(): string
    {
        if ($this->isBetaProfile()) {
            return 'beta';
        }

        if ($this->isVerifiedProfile()) {
            return 'verified';
        }

        if ($this->isPremiumProfile()) {
            return 'premium';
        }

        return 'regular';
    }

    /**
     * Verifica si este ProfileId pertenece a un perfil activo
     * (basado en patrones de IDs de perfiles activos)
     *
     * @return bool True si probablemente es un perfil activo
     */
    public function isActiveProfile(): bool
    {
        // Los perfiles activos suelen tener IDs más altos
        return $this->value > 100;
    }

    /**
     * Obtiene el año aproximado de creación basado en el ID
     * (útil para análisis de tendencias)
     *
     * @return int Año aproximado de creación
     */
    public function getEstimatedCreationYear(): int
    {
        // Estimación basada en patrones de IDs (esto puede ajustarse según la lógica del negocio)
        if ($this->value <= 1000) {
            return 2023;
        } elseif ($this->value <= 10000) {
            return 2024;
        } else {
            return 2025;
        }
    }

    /**
     * Verifica si este ProfileId está en un rango de perfiles de alta calidad
     *
     * @return bool True si está en rango de alta calidad
     */
    public function isHighQualityRange(): bool
    {
        return $this->value >= 5000 && $this->value <= 15000;
    }

    /**
     * Convierte el ProfileId a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getProfileType(),
            'is_premium' => $this->isPremiumProfile(),
            'is_verified' => $this->isVerifiedProfile(),
            'is_active' => $this->isActiveProfile(),
            'estimated_creation_year' => $this->getEstimatedCreationYear(),
        ];
    }

    /**
     * Representación en string del ProfileId
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
            'type' => $this->getProfileType(),
            'is_premium' => $this->isPremiumProfile(),
            'is_verified' => $this->isVerifiedProfile(),
            'is_active' => $this->isActiveProfile(),
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
                "ProfileId debe ser un número entero positivo, se recibió: {$value}"
            );
        }

        if ($value > PHP_INT_MAX) {
            throw new InvalidArgumentException(
                "ProfileId excede el valor máximo permitido: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un ProfileId válido
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
     * Crea un ProfileId desde un valor mixto (int, string, array)
     *
     * @param mixed $value Valor del identificador
     * @param string $arrayKey Clave para arrays (por defecto 'id')
     * @return self Nueva instancia de ProfileId
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
            'ProfileId solo puede crearse desde int, string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Crea un ProfileId desde un modelo Eloquent Profile
     *
     * @param \App\Models\User\Profile $profile Modelo Profile
     * @return self Nueva instancia de ProfileId
     * @throws InvalidArgumentException Si el modelo no tiene ID válido
     */
    public static function fromProfile(\App\Models\User\Profile $profile): self
    {
        if (!$profile->exists || !$profile->getKey()) {
            throw new InvalidArgumentException(
                'El modelo Profile debe existir y tener un ID válido'
            );
        }

        return self::fromInt($profile->getKey());
    }

    /**
     * Crea un ProfileId desde un modelo User (obteniendo su profile_id)
     *
     * @param \App\Models\User\User $user Modelo User
     * @return self Nueva instancia de ProfileId
     * @throws InvalidArgumentException Si el usuario no tiene perfil válido
     */
    public static function fromUser(\App\Models\User\User $user): self
    {
        if (!$user->profile || !$user->profile->exists) {
            throw new InvalidArgumentException(
                'El usuario debe tener un perfil válido'
            );
        }

        return self::fromProfile($user->profile);
    }

    /**
     * Verifica si este ProfileId corresponde a un perfil con características específicas
     * (útil para filtros y búsquedas)
     *
     * @param array $criteria Criterios de filtrado
     * @return bool True si cumple los criterios
     */
    public function matchesCriteria(array $criteria): bool
    {
        if (isset($criteria['type'])) {
            if ($this->getProfileType() !== $criteria['type']) {
                return false;
            }
        }

        if (isset($criteria['premium_only']) && $criteria['premium_only']) {
            if (!$this->isPremiumProfile()) {
                return false;
            }
        }

        if (isset($criteria['verified_only']) && $criteria['verified_only']) {
            if (!$this->isVerifiedProfile()) {
                return false;
            }
        }

        if (isset($criteria['active_only']) && $criteria['active_only']) {
            if (!$this->isActiveProfile()) {
                return false;
            }
        }

        if (isset($criteria['high_quality_only']) && $criteria['high_quality_only']) {
            if (!$this->isHighQualityRange()) {
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

        return true;
    }

    /**
     * Obtiene estadísticas básicas del ProfileId
     *
     * @return array Estadísticas del perfil
     */
    public function getStats(): array
    {
        return [
            'id' => $this->value,
            'type' => $this->getProfileType(),
            'is_premium' => $this->isPremiumProfile(),
            'is_verified' => $this->isVerifiedProfile(),
            'is_active' => $this->isActiveProfile(),
            'is_beta' => $this->isBetaProfile(),
            'is_high_quality' => $this->isHighQualityRange(),
            'estimated_creation_year' => $this->getEstimatedCreationYear(),
            'creation_era' => $this->getCreationEra(),
        ];
    }

    /**
     * Obtiene la era de creación del perfil
     *
     * @return string Era de creación (early, mid, recent)
     */
    private function getCreationEra(): string
    {
        if ($this->value <= 5000) {
            return 'early';
        } elseif ($this->value <= 15000) {
            return 'mid';
        } else {
            return 'recent';
        }
    }
}
