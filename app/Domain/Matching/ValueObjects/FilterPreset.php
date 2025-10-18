<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use App\Domain\Shared\ValueObjects\UserId;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use JsonSerializable;

/**
 * FilterPreset Value Object
 * 
 * Representa un preset de filtros guardado en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de presets de filtros en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para manejar
 * combinaciones predefinidas de filtros guardadas por los usuarios.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta presets de filtros válidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos FilterPreset con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y filtros
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class FilterPreset implements JsonSerializable
{
    /**
     * ID único del preset
     */
    private readonly string $id;

    /**
     * ID del usuario propietario
     */
    private readonly UserId $userId;

    /**
     * Nombre del preset
     */
    private readonly string $name;

    /**
     * Criterios de filtro del preset
     */
    private readonly FilterCriteria $criteria;

    /**
     * Si es el preset por defecto
     */
    private readonly bool $isDefault;

    /**
     * Fecha de creación
     */
    private readonly CarbonInterface $createdAt;

    /**
     * Fecha de última actualización
     */
    private readonly CarbonInterface $updatedAt;

    /**
     * Número de veces usado
     */
    private readonly int $usageCount;

    /**
     * Descripción opcional
     */
    private readonly ?string $description;

    /**
     * Etiquetas del preset
     */
    private readonly array $tags;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(
        string $id,
        UserId $userId,
        string $name,
        FilterCriteria $criteria,
        bool $isDefault,
        CarbonInterface $createdAt,
        CarbonInterface $updatedAt,
        int $usageCount = 0,
        ?string $description = null,
        array $tags = []
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->name = $name;
        $this->criteria = $criteria;
        $this->isDefault = $isDefault;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->usageCount = $usageCount;
        $this->description = $description;
        $this->tags = $tags;
    }

    /**
     * Factory method para crear una nueva instancia de FilterPreset
     *
     * @param UserId $userId ID del usuario propietario
     * @param string $name Nombre del preset
     * @param FilterCriteria $criteria Criterios de filtro
     * @param bool $isDefault Si es el preset por defecto
     * @param CarbonInterface $createdAt Fecha de creación
     * @param CarbonInterface $updatedAt Fecha de actualización
     * @param int $usageCount Número de usos
     * @param string|null $description Descripción opcional
     * @param array $tags Etiquetas del preset
     * @return self Nueva instancia de FilterPreset
     * @throws InvalidArgumentException Si los valores no son válidos
     */
    public static function create(
        UserId $userId,
        string $name,
        FilterCriteria $criteria,
        bool $isDefault = false,
        CarbonInterface $createdAt,
        CarbonInterface $updatedAt,
        int $usageCount = 0,
        ?string $description = null,
        array $tags = []
    ): self {
        self::validateName($name);
        self::validateDescription($description);
        self::validateTags($tags);
        
        $id = self::generateId($userId, $name);
        
        return new self(
            $id,
            $userId,
            $name,
            $criteria,
            $isDefault,
            $createdAt,
            $updatedAt,
            $usageCount,
            $description,
            $tags
        );
    }

    /**
     * Factory method para crear FilterPreset desde array
     *
     * @param array $data Array con datos del preset
     * @return self Nueva instancia de FilterPreset
     * @throws InvalidArgumentException Si el array no es válido
     */
    public static function fromArray(array $data): self
    {
        $userId = UserId::from($data['user_id']);
        $name = $data['name'];
        $criteria = FilterCriteria::from($data['criteria']);
        $isDefault = $data['is_default'] ?? false;
        $createdAt = \Carbon\Carbon::parse($data['created_at']);
        $updatedAt = \Carbon\Carbon::parse($data['updated_at']);
        $usageCount = $data['usage_count'] ?? 0;
        $description = $data['description'] ?? null;
        $tags = $data['tags'] ?? [];

        return self::create(
            $userId,
            $name,
            $criteria,
            $isDefault,
            $createdAt,
            $updatedAt,
            $usageCount,
            $description,
            $tags
        );
    }

    /**
     * Factory methods para presets predefinidos comunes
     */
    public static function nearbyYoung(): self
    {
        $criteria = FilterCriteria::create([
            'age_range' => AgeRange::young(),
            'distance' => DistanceFilter::nearby(),
            'lifestyle' => LifestyleFilter::active()
        ]);

        return new self(
            'preset_nearby_young',
            UserId::fromInt(0), // Preset del sistema
            'Jóvenes Cercanos',
            $criteria,
            false,
            \Carbon\Carbon::now(),
            \Carbon\Carbon::now(),
            0,
            'Busca jóvenes activos cerca de tu ubicación'
        );
    }

    public static function professionalMature(): self
    {
        $criteria = FilterCriteria::create([
            'age_range' => AgeRange::mature(),
            'demographics' => DemographicFilter::professional(),
            'lifestyle' => LifestyleFilter::professional()
        ]);

        return new self(
            'preset_professional_mature',
            UserId::fromInt(0), // Preset del sistema
            'Profesionales Maduros',
            $criteria,
            false,
            \Carbon\Carbon::now(),
            \Carbon\Carbon::now(),
            0,
            'Busca profesionales maduros con educación superior'
        );
    }

    public static function familyOriented(): self
    {
        $criteria = FilterCriteria::create([
            'demographics' => DemographicFilter::familyOriented(),
            'lifestyle' => LifestyleFilter::relaxed()
        ]);

        return new self(
            'preset_family_oriented',
            UserId::fromInt(0), // Preset del sistema
            'Orientados a la Familia',
            $criteria,
            false,
            \Carbon\Carbon::now(),
            \Carbon\Carbon::now(),
            0,
            'Busca personas orientadas a formar familia'
        );
    }

    /**
     * Obtiene el ID del preset
     *
     * @return string ID del preset
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Obtiene el ID del usuario propietario
     *
     * @return UserId ID del usuario
     */
    public function getUserId(): UserId
    {
        return $this->userId;
    }

    /**
     * Obtiene el nombre del preset
     *
     * @return string Nombre del preset
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Obtiene los criterios de filtro
     *
     * @return FilterCriteria Criterios de filtro
     */
    public function getCriteria(): FilterCriteria
    {
        return $this->criteria;
    }

    /**
     * Verifica si es el preset por defecto
     *
     * @return bool True si es el preset por defecto
     */
    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /**
     * Obtiene la fecha de creación
     *
     * @return CarbonInterface Fecha de creación
     */
    public function getCreatedAt(): CarbonInterface
    {
        return $this->createdAt;
    }

    /**
     * Obtiene la fecha de última actualización
     *
     * @return CarbonInterface Fecha de actualización
     */
    public function getUpdatedAt(): CarbonInterface
    {
        return $this->updatedAt;
    }

    /**
     * Obtiene el número de usos
     *
     * @return int Número de usos
     */
    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    /**
     * Obtiene la descripción
     *
     * @return string|null Descripción del preset
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Obtiene las etiquetas
     *
     * @return array Etiquetas del preset
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Verifica si el preset es del sistema
     *
     * @return bool True si es del sistema
     */
    public function isSystemPreset(): bool
    {
        return $this->userId->toInt() === 0;
    }

    /**
     * Verifica si el preset es personalizado
     *
     * @return bool True si es personalizado
     */
    public function isCustomPreset(): bool
    {
        return !$this->isSystemPreset();
    }

    /**
     * Verifica si el preset es popular
     *
     * @return bool True si es popular (más de 100 usos)
     */
    public function isPopular(): bool
    {
        return $this->usageCount > 100;
    }

    /**
     * Verifica si el preset es reciente
     *
     * @return bool True si fue creado en los últimos 30 días
     */
    public function isRecent(): bool
    {
        return $this->createdAt->diffInDays(\Carbon\Carbon::now()) <= 30;
    }

    /**
     * Verifica si el preset está inactivo
     *
     * @return bool True si no se ha usado en 90 días
     */
    public function isInactive(): bool
    {
        return $this->updatedAt->diffInDays(\Carbon\Carbon::now()) > 90;
    }

    /**
     * Obtiene la categoría del preset
     *
     * @return string Categoría (system, custom, popular, recent, inactive)
     */
    public function getCategory(): string
    {
        if ($this->isSystemPreset()) {
            return 'system';
        }

        if ($this->isInactive()) {
            return 'inactive';
        }

        if ($this->isPopular()) {
            return 'popular';
        }

        if ($this->isRecent()) {
            return 'recent';
        }

        return 'custom';
    }

    /**
     * Obtiene el nivel de efectividad del preset
     * (basado en uso y características)
     *
     * @return string Nivel (low, medium, high, very_high)
     */
    public function getEffectivenessLevel(): string
    {
        if ($this->isSystemPreset() && $this->isPopular()) {
            return 'very_high';
        }

        if ($this->usageCount > 50) {
            return 'high';
        }

        if ($this->usageCount > 10) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Obtiene el score de efectividad
     *
     * @return float Score de efectividad (0.0-1.0)
     */
    public function getEffectivenessScore(): float
    {
        $score = 0.0;

        // Score basado en uso
        if ($this->usageCount > 0) {
            $score += min($this->usageCount / 100.0, 0.5);
        }

        // Score basado en antigüedad (presets más antiguos son más probados)
        $ageScore = min($this->createdAt->diffInDays(\Carbon\Carbon::now()) / 365.0, 0.3);
        $score += $ageScore;

        // Score basado en si es default
        if ($this->isDefault) {
            $score += 0.2;
        }

        return min($score, 1.0);
    }

    /**
     * Verifica si este preset es igual a otro
     *
     * @param self $other Otro preset para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->id === $other->id &&
               $this->userId->equals($other->userId) &&
               $this->name === $other->name &&
               $this->criteria->equals($other->criteria) &&
               $this->isDefault === $other->isDefault;
    }

    /**
     * Obtiene la descripción legible del preset
     *
     * @return string Descripción del preset
     */
    public function getReadableDescription(): string
    {
        $parts = [];

        $parts[] = "Preset: {$this->name}";
        
        if ($this->description) {
            $parts[] = $this->description;
        }

        $parts[] = "Usos: {$this->usageCount}";
        $parts[] = "Creado: " . $this->createdAt->format('d/m/Y');

        if ($this->isDefault) {
            $parts[] = "(Por defecto)";
        }

        return implode(' - ', $parts);
    }

    /**
     * Convierte el preset a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId->toInt(),
            'name' => $this->name,
            'criteria' => $this->criteria->toArray(),
            'is_default' => $this->isDefault,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
            'usage_count' => $this->usageCount,
            'description' => $this->description,
            'tags' => $this->tags,
            'category' => $this->getCategory(),
            'effectiveness_level' => $this->getEffectivenessLevel(),
            'effectiveness_score' => $this->getEffectivenessScore(),
            'is_system' => $this->isSystemPreset(),
            'is_custom' => $this->isCustomPreset(),
            'is_popular' => $this->isPopular(),
            'is_recent' => $this->isRecent(),
            'is_inactive' => $this->isInactive(),
        ];
    }

    /**
     * Representación en string del preset
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return $this->getReadableDescription();
    }

    /**
     * Serialización para JSON
     *
     * @return array Valor para JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'criteria' => $this->criteria,
            'is_default' => $this->isDefault,
            'usage_count' => $this->usageCount,
            'description' => $this->description,
            'tags' => $this->tags
        ];
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'user_id' => $this->userId->toInt(),
            'usage_count' => $this->usageCount,
            'is_default' => $this->isDefault,
            'category' => $this->getCategory(),
            'effectiveness_score' => $this->getEffectivenessScore(),
        ];
    }

    /**
     * Valida que el nombre sea válido
     *
     * @param string $name Nombre a validar
     * @throws InvalidArgumentException Si el nombre no es válido
     */
    private static function validateName(string $name): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException(
                "El nombre del preset no puede estar vacío"
            );
        }

        if (strlen($name) > 100) {
            throw new InvalidArgumentException(
                "El nombre del preset no puede exceder 100 caracteres"
            );
        }
    }

    /**
     * Valida que la descripción sea válida
     *
     * @param string|null $description Descripción a validar
     * @throws InvalidArgumentException Si la descripción no es válida
     */
    private static function validateDescription(?string $description): void
    {
        if ($description !== null && strlen($description) > 500) {
            throw new InvalidArgumentException(
                "La descripción del preset no puede exceder 500 caracteres"
            );
        }
    }

    /**
     * Valida que las etiquetas sean válidas
     *
     * @param array $tags Etiquetas a validar
     * @throws InvalidArgumentException Si las etiquetas no son válidas
     */
    private static function validateTags(array $tags): void
    {
        if (count($tags) > 10) {
            throw new InvalidArgumentException(
                "No se pueden especificar más de 10 etiquetas por preset"
            );
        }

        foreach ($tags as $tag) {
            if (!is_string($tag) || empty(trim($tag))) {
                throw new InvalidArgumentException(
                    "Cada etiqueta debe ser una cadena no vacía"
                );
            }

            if (strlen($tag) > 50) {
                throw new InvalidArgumentException(
                    "Cada etiqueta no puede exceder 50 caracteres"
                );
            }
        }
    }

    /**
     * Genera un ID único para el preset
     *
     * @param UserId $userId ID del usuario
     * @param string $name Nombre del preset
     * @return string ID único generado
     */
    private static function generateId(UserId $userId, string $name): string
    {
        $timestamp = time();
        $hash = substr(md5($userId->toString() . $name . $timestamp), 0, 8);
        return "preset_{$userId->toString()}_{$hash}";
    }

    /**
     * Verifica si un valor es un FilterPreset válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if ($value instanceof self) {
                return true;
            }

            if (is_array($value)) {
                self::fromArray($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea FilterPreset desde un valor mixto
     *
     * @param mixed $value Valor del preset de filtros
     * @return self Nueva instancia de FilterPreset
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_array($value)) {
            return self::fromArray($value);
        }

        throw new InvalidArgumentException(
            'FilterPreset solo puede crearse desde array o instancia de FilterPreset, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para este preset (útil para cache keys)
     *
     * @return string Hash único del preset
     */
    public function getHash(): string
    {
        return 'filter_preset_' . $this->id;
    }

    /**
     * Verifica si este preset es compatible con el sistema de analytics
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si este preset es compatible con el sistema de caché
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
