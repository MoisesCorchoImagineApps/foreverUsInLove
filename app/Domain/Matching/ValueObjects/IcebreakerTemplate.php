<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use App\Domain\Shared\ValueObjects\UserId;
use InvalidArgumentException;
use JsonSerializable;

/**
 * IcebreakerTemplate Value Object
 * 
 * Representa una plantilla de icebreaker en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de la plantilla de icebreaker en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para representar
 * plantillas de icebreakers que pueden ser reutilizadas y personalizadas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta plantillas válidas con formato correcto
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos IcebreakerTemplate con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y plantillas
 * 
 * Plantilla incluye:
 * - id: Identificador único de la plantilla
 * - name: Nombre de la plantilla
 * - message_template: Plantilla del mensaje con placeholders
 * - category: Categoría de la plantilla
 * - placeholders: Lista de placeholders disponibles
 * - usage_count: Número de veces que se ha usado
 * - effectiveness_score: Puntaje de efectividad
 * - is_active: Si la plantilla está activa
 * - created_by: Usuario que creó la plantilla
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class IcebreakerTemplate implements JsonSerializable
{
    /**
     * Identificador único de la plantilla
     */
    private readonly IcebreakerId $id;

    /**
     * Nombre de la plantilla
     */
    private readonly string $name;

    /**
     * Plantilla del mensaje con placeholders
     */
    private readonly string $messageTemplate;

    /**
     * Categoría de la plantilla
     */
    private readonly IcebreakerCategory $category;

    /**
     * Lista de placeholders disponibles
     */
    private readonly array $placeholders;

    /**
     * Número de veces que se ha usado
     */
    private readonly int $usageCount;

    /**
     * Puntaje de efectividad
     */
    private readonly ?float $effectivenessScore;

    /**
     * Si la plantilla está activa
     */
    private readonly bool $isActive;

    /**
     * Usuario que creó la plantilla
     */
    private readonly ?UserId $createdBy;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(
        IcebreakerId $id,
        string $name,
        string $messageTemplate,
        IcebreakerCategory $category,
        array $placeholders,
        int $usageCount,
        ?float $effectivenessScore,
        bool $isActive,
        ?UserId $createdBy
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->messageTemplate = $messageTemplate;
        $this->category = $category;
        $this->placeholders = $placeholders;
        $this->usageCount = $usageCount;
        $this->effectivenessScore = $effectivenessScore;
        $this->isActive = $isActive;
        $this->createdBy = $createdBy;
    }

    /**
     * Factory method para crear una nueva instancia de IcebreakerTemplate
     *
     * @param array $data Datos de la plantilla
     * @return self Nueva instancia de IcebreakerTemplate
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function fromArray(array $data): self
    {
        self::validateData($data);

        return new self(
            id: IcebreakerId::fromString($data['id']),
            name: $data['name'],
            messageTemplate: $data['message_template'],
            category: IcebreakerCategory::fromString($data['category']),
            placeholders: $data['placeholders'] ?? [],
            usageCount: $data['usage_count'] ?? 0,
            effectivenessScore: $data['effectiveness_score'] ?? null,
            isActive: $data['is_active'] ?? true,
            createdBy: isset($data['created_by']) ? UserId::fromString($data['created_by']) : null
        );
    }

    /**
     * Factory method para crear IcebreakerTemplate desde datos básicos
     *
     * @param string $name Nombre de la plantilla
     * @param string $messageTemplate Plantilla del mensaje
     * @param IcebreakerCategory $category Categoría de la plantilla
     * @param array $options Opciones adicionales
     * @return self Nueva instancia de IcebreakerTemplate
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function create(
        string $name,
        string $messageTemplate,
        IcebreakerCategory $category,
        array $options = []
    ): self {
        self::validateTemplate($name, $messageTemplate);

        return new self(
            id: IcebreakerId::generate(),
            name: $name,
            messageTemplate: $messageTemplate,
            category: $category,
            placeholders: self::extractPlaceholders($messageTemplate),
            usageCount: 0,
            effectivenessScore: null,
            isActive: $options['is_active'] ?? true,
            createdBy: isset($options['created_by']) ? UserId::fromString($options['created_by']) : null
        );
    }

    /**
     * Obtiene el ID de la plantilla
     *
     * @return IcebreakerId ID de la plantilla
     */
    public function getId(): IcebreakerId
    {
        return $this->id;
    }

    /**
     * Obtiene el nombre de la plantilla
     *
     * @return string Nombre de la plantilla
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Obtiene la plantilla del mensaje
     *
     * @return string Plantilla del mensaje
     */
    public function getMessageTemplate(): string
    {
        return $this->messageTemplate;
    }

    /**
     * Obtiene la categoría de la plantilla
     *
     * @return IcebreakerCategory Categoría de la plantilla
     */
    public function getCategory(): IcebreakerCategory
    {
        return $this->category;
    }

    /**
     * Obtiene los placeholders de la plantilla
     *
     * @return array Placeholders de la plantilla
     */
    public function getPlaceholders(): array
    {
        return $this->placeholders;
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
     * Obtiene el puntaje de efectividad
     *
     * @return float|null Puntaje de efectividad
     */
    public function getEffectivenessScore(): ?float
    {
        return $this->effectivenessScore;
    }

    /**
     * Verifica si la plantilla está activa
     *
     * @return bool True si está activa
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Obtiene el usuario que creó la plantilla
     *
     * @return UserId|null Usuario que creó la plantilla
     */
    public function getCreatedBy(): ?UserId
    {
        return $this->createdBy;
    }

    /**
     * Verifica si este IcebreakerTemplate es igual a otro
     *
     * @param self $other Otro IcebreakerTemplate para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }

    /**
     * Verifica si la plantilla tiene placeholders
     *
     * @return bool True si tiene placeholders
     */
    public function hasPlaceholders(): bool
    {
        return count($this->placeholders) > 0;
    }

    /**
     * Verifica si la plantilla tiene un placeholder específico
     *
     * @param string $placeholder Placeholder a verificar
     * @return bool True si tiene el placeholder
     */
    public function hasPlaceholder(string $placeholder): bool
    {
        return in_array($placeholder, $this->placeholders, true);
    }

    /**
     * Obtiene el número de placeholders
     *
     * @return int Número de placeholders
     */
    public function getPlaceholderCount(): int
    {
        return count($this->placeholders);
    }

    /**
     * Verifica si la plantilla es personalizada
     * (creada por un usuario específico)
     *
     * @return bool True si es personalizada
     */
    public function isCustom(): bool
    {
        return $this->createdBy !== null;
    }

    /**
     * Verifica si la plantilla es del sistema
     * (no creada por un usuario específico)
     *
     * @return bool True si es del sistema
     */
    public function isSystem(): bool
    {
        return $this->createdBy === null;
    }

    /**
     * Verifica si la plantilla es efectiva
     * (tiene un puntaje de efectividad alto)
     *
     * @param float $threshold Umbral de efectividad (por defecto 0.7)
     * @return bool True si es efectiva
     */
    public function isEffective(float $threshold = 0.7): bool
    {
        return $this->effectivenessScore !== null && $this->effectivenessScore >= $threshold;
    }

    /**
     * Verifica si la plantilla es popular
     * (tiene muchos usos)
     *
     * @param int $threshold Umbral de popularidad (por defecto 100)
     * @return bool True si es popular
     */
    public function isPopular(int $threshold = 100): bool
    {
        return $this->usageCount >= $threshold;
    }

    /**
     * Obtiene el nivel de complejidad de la plantilla
     * (1 = simple, 5 = compleja)
     *
     * @return int Nivel de complejidad
     */
    public function getComplexityLevel(): int
    {
        $placeholderCount = $this->getPlaceholderCount();
        $messageLength = strlen($this->messageTemplate);

        return match (true) {
            $placeholderCount === 0 && $messageLength < 50 => 1, // Simple
            $placeholderCount <= 1 && $messageLength < 100 => 2, // Simple-media
            $placeholderCount <= 2 && $messageLength < 150 => 3, // Media
            $placeholderCount <= 3 && $messageLength < 200 => 4, // Media-compleja
            default => 5, // Compleja
        };
    }

    /**
     * Obtiene la categoría de complejidad
     *
     * @return string Categoría de complejidad (simple, moderate, complex)
     */
    public function getComplexityCategory(): string
    {
        $level = $this->getComplexityLevel();

        return match (true) {
            $level <= 2 => 'simple',
            $level <= 3 => 'moderate',
            default => 'complex',
        };
    }

    /**
     * Obtiene el nivel de personalización requerido
     * (basado en el número de placeholders)
     *
     * @return int Nivel de personalización (1 = alta, 5 = baja)
     */
    public function getPersonalizationLevel(): int
    {
        $placeholderCount = $this->getPlaceholderCount();

        return match (true) {
            $placeholderCount >= 4 => 1, // Alta personalización
            $placeholderCount >= 3 => 2, // Personalización alta-media
            $placeholderCount >= 2 => 3, // Personalización media
            $placeholderCount >= 1 => 4, // Personalización media-baja
            default => 5, // Baja personalización
        };
    }

    /**
     * Obtiene el peso de efectividad de la plantilla
     * (combinación de efectividad y popularidad)
     *
     * @return float Peso de efectividad (0.0 - 1.0)
     */
    public function getEffectivenessWeight(): float
    {
        $effectivenessScore = $this->effectivenessScore ?? 0.5;
        $popularityScore = min(1.0, $this->usageCount / 1000.0);

        return ($effectivenessScore * 0.7) + ($popularityScore * 0.3);
    }

    /**
     * Obtiene un resumen de la plantilla para logging
     *
     * @return array Resumen de la plantilla
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id->toString(),
            'name' => $this->name,
            'category' => $this->category->getValue(),
            'placeholder_count' => $this->getPlaceholderCount(),
            'usage_count' => $this->usageCount,
            'effectiveness_score' => $this->effectivenessScore,
            'is_active' => $this->isActive,
            'is_custom' => $this->isCustom(),
            'complexity_level' => $this->getComplexityLevel(),
            'personalization_level' => $this->getPersonalizationLevel(),
            'effectiveness_weight' => $this->getEffectivenessWeight(),
        ];
    }

    /**
     * Convierte la IcebreakerTemplate a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'name' => $this->name,
            'message_template' => $this->messageTemplate,
            'category' => $this->category->getValue(),
            'placeholders' => $this->placeholders,
            'usage_count' => $this->usageCount,
            'effectiveness_score' => $this->effectivenessScore,
            'is_active' => $this->isActive,
            'created_by' => $this->createdBy?->toString(),
            'complexity_level' => $this->getComplexityLevel(),
            'personalization_level' => $this->getPersonalizationLevel(),
            'effectiveness_weight' => $this->getEffectivenessWeight(),
        ];
    }

    /**
     * Representación en string de la IcebreakerTemplate
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return sprintf(
            'IcebreakerTemplate(id=%s, name=%s, category=%s)',
            $this->id->toString(),
            $this->name,
            $this->category->getValue()
        );
    }

    /**
     * Serialización para JSON
     *
     * @return array Valor para JSON
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return $this->getSummary();
    }

    /**
     * Valida que los datos de la plantilla sean válidos
     *
     * @param array $data Datos a validar
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    private static function validateData(array $data): void
    {
        if (!isset($data['id'])) {
            throw new InvalidArgumentException('id es requerido en IcebreakerTemplate');
        }

        if (!isset($data['name'])) {
            throw new InvalidArgumentException('name es requerido en IcebreakerTemplate');
        }

        if (!isset($data['message_template'])) {
            throw new InvalidArgumentException('message_template es requerido en IcebreakerTemplate');
        }

        if (!isset($data['category'])) {
            throw new InvalidArgumentException('category es requerido en IcebreakerTemplate');
        }

        self::validateTemplate($data['name'], $data['message_template']);
    }

    /**
     * Valida que la plantilla sea válida
     *
     * @param string $name Nombre de la plantilla
     * @param string $messageTemplate Plantilla del mensaje
     * @throws InvalidArgumentException Si la plantilla no es válida
     */
    private static function validateTemplate(string $name, string $messageTemplate): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('El nombre de la plantilla no puede estar vacío');
        }

        if (strlen($name) > 100) {
            throw new InvalidArgumentException('El nombre de la plantilla no puede exceder 100 caracteres');
        }

        if (empty(trim($messageTemplate))) {
            throw new InvalidArgumentException('La plantilla del mensaje no puede estar vacía');
        }

        if (strlen($messageTemplate) > 500) {
            throw new InvalidArgumentException('La plantilla del mensaje no puede exceder 500 caracteres');
        }
    }

    /**
     * Extrae los placeholders de la plantilla del mensaje
     *
     * @param string $messageTemplate Plantilla del mensaje
     * @return array Lista de placeholders encontrados
     */
    private static function extractPlaceholders(string $messageTemplate): array
    {
        preg_match_all('/\{([^}]+)\}/', $messageTemplate, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Verifica si un valor es una IcebreakerTemplate válida
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válida
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if ($value instanceof self) {
                return true;
            }

            if (is_array($value)) {
                self::validateData($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea una IcebreakerTemplate desde un valor mixto
     *
     * @param mixed $value Valor de la plantilla
     * @return self Nueva instancia de IcebreakerTemplate
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
            'IcebreakerTemplate solo puede crearse desde array o instancia de IcebreakerTemplate, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para esta plantilla (útil para cache keys)
     *
     * @return string Hash único de la plantilla
     */
    public function getHash(): string
    {
        $data = [
            $this->id->toString(),
            $this->name,
            $this->messageTemplate,
            $this->category->getValue(),
        ];

        return 'icebreaker_template_' . substr(md5(serialize($data)), 0, 16);
    }

    /**
     * Verifica si esta plantilla es compatible con el sistema de analytics
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si esta plantilla es compatible con el sistema de caché
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
