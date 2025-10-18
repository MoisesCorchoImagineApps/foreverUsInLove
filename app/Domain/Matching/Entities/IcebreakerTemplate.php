<?php

declare(strict_types=1);

namespace App\Domain\Matching\Entities;

use App\Domain\Matching\ValueObjects\IcebreakerId;
use App\Domain\Matching\ValueObjects\IcebreakerCategory;
use Carbon\CarbonInterface;

/**
 * IcebreakerTemplate Entity
 * 
 * Representa una plantilla de icebreaker en el sistema ForeverUsInLove.
 * Implementa el patrón Entity para encapsular la lógica de negocio
 * relacionada con las plantillas de icebreakers predefinidas.
 * 
 * Este Entity es específico del dominio Matching y se utiliza para manejar
 * las plantillas de icebreakers del sistema, incluyendo mensajes base,
 * categorización, efectividad y datos de personalización.
 * 
 * Características:
 * - Encapsula la lógica de negocio de plantillas de icebreakers
 * - Maneja mensajes base con placeholders
 * - Proporciona categorización y metadatos
 * - Gestiona datos de efectividad y uso
 * - Domain-specific: Específico para el dominio de matching
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class IcebreakerTemplate
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
     * Mensaje base de la plantilla
     */
    private readonly string $messageTemplate;

    /**
     * Categoría de la plantilla
     */
    private readonly IcebreakerCategory $category;

    /**
     * Descripción de la plantilla
     */
    private readonly string $description;

    /**
     * Placeholders disponibles en la plantilla
     */
    private readonly array $placeholders;

    /**
     * Indica si la plantilla está activa
     */
    private readonly bool $isActive;

    /**
     * Indica si es solo para usuarios premium
     */
    private readonly bool $isPremiumOnly;

    /**
     * Score de efectividad de la plantilla
     */
    private readonly ?float $effectivenessScore;

    /**
     * Número de veces que se ha usado
     */
    private readonly int $usageCount;

    /**
     * Fecha de creación de la plantilla
     */
    private readonly CarbonInterface $createdAt;

    /**
     * Fecha de última actualización
     */
    private readonly CarbonInterface $updatedAt;

    /**
     * Metadatos adicionales de la plantilla
     */
    private readonly array $metadata;

    /**
     * Constructor de la entidad IcebreakerTemplate
     */
    public function __construct(
        IcebreakerId $id,
        string $name,
        string $messageTemplate,
        IcebreakerCategory $category,
        string $description = '',
        array $placeholders = [],
        bool $isActive = true,
        bool $isPremiumOnly = false,
        ?float $effectivenessScore = null,
        int $usageCount = 0,
        ?CarbonInterface $createdAt = null,
        ?CarbonInterface $updatedAt = null,
        array $metadata = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->messageTemplate = $messageTemplate;
        $this->category = $category;
        $this->description = $description;
        $this->placeholders = $placeholders;
        $this->isActive = $isActive;
        $this->isPremiumOnly = $isPremiumOnly;
        $this->effectivenessScore = $effectivenessScore;
        $this->usageCount = $usageCount;
        $this->createdAt = $createdAt ?? now();
        $this->updatedAt = $updatedAt ?? now();
        $this->metadata = $metadata;
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
     * Obtiene el mensaje base de la plantilla
     *
     * @return string Mensaje base
     */
    public function getMessage(): string
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
     * Obtiene la descripción de la plantilla
     *
     * @return string Descripción de la plantilla
     */
    public function getDescription(): string
    {
        return $this->description;
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
     * Verifica si la plantilla está activa
     *
     * @return bool True si está activa
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Verifica si es solo para premium
     *
     * @return bool True si es solo para premium
     */
    public function isPremiumOnly(): bool
    {
        return $this->isPremiumOnly;
    }

    /**
     * Obtiene el score de efectividad
     *
     * @return float|null Score de efectividad
     */
    public function getEffectivenessScore(): ?float
    {
        return $this->effectivenessScore;
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
     * @return CarbonInterface Fecha de última actualización
     */
    public function getUpdatedAt(): CarbonInterface
    {
        return $this->updatedAt;
    }

    /**
     * Obtiene los metadatos
     *
     * @return array Metadatos
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Verifica si tiene placeholders
     *
     * @return bool True si tiene placeholders
     */
    public function hasPlaceholders(): bool
    {
        return !empty($this->placeholders);
    }

    /**
     * Verifica si tiene datos de efectividad
     *
     * @return bool True si tiene datos de efectividad
     */
    public function hasEffectivenessData(): bool
    {
        return $this->effectivenessScore !== null;
    }

    /**
     * Verifica si ha sido usada
     *
     * @return bool True si ha sido usada
     */
    public function hasBeenUsed(): bool
    {
        return $this->usageCount > 0;
    }

    /**
     * Obtiene un placeholder específico
     *
     * @param string $key Clave del placeholder
     * @param mixed $default Valor por defecto
     * @return mixed Valor del placeholder
     */
    public function getPlaceholder(string $key, $default = null)
    {
        return $this->placeholders[$key] ?? $default;
    }

    /**
     * Obtiene un dato de metadata específico
     *
     * @param string $key Clave del metadata
     * @param mixed $default Valor por defecto
     * @return mixed Valor del metadata
     */
    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Verifica si es de alta efectividad
     *
     * @return bool True si es de alta efectividad
     */
    public function isHighEffectiveness(): bool
    {
        return $this->effectivenessScore !== null && $this->effectivenessScore >= 0.8;
    }

    /**
     * Verifica si es de efectividad media
     *
     * @return bool True si es de efectividad media
     */
    public function isMediumEffectiveness(): bool
    {
        return $this->effectivenessScore !== null && 
               $this->effectivenessScore >= 0.5 && 
               $this->effectivenessScore < 0.8;
    }

    /**
     * Verifica si es de baja efectividad
     *
     * @return bool True si es de baja efectividad
     */
    public function isLowEffectiveness(): bool
    {
        return $this->effectivenessScore !== null && $this->effectivenessScore < 0.5;
    }

    /**
     * Obtiene el nivel de efectividad como string
     *
     * @return string Nivel de efectividad
     */
    public function getEffectivenessLevel(): string
    {
        if (!$this->hasEffectivenessData()) {
            return 'unknown';
        }

        if ($this->isHighEffectiveness()) {
            return 'high';
        }

        if ($this->isMediumEffectiveness()) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Verifica si es gratuita
     *
     * @return bool True si es gratuita
     */
    public function isFree(): bool
    {
        return !$this->isPremiumOnly;
    }

    /**
     * Verifica si requiere datos de perfil
     *
     * @return bool True si requiere datos de perfil
     */
    public function requiresProfileData(): bool
    {
        return $this->category->requiresProfileData();
    }

    /**
     * Verifica si es interactiva
     *
     * @return bool True si es interactiva
     */
    public function isInteractive(): bool
    {
        return $this->category->isInteractive();
    }

    /**
     * Verifica si es positiva
     *
     * @return bool True si es positiva
     */
    public function isPositive(): bool
    {
        return $this->category->isPositive();
    }

    /**
     * Obtiene el número de placeholders
     *
     * @return int Número de placeholders
     */
    public function getPlaceholdersCount(): int
    {
        return count($this->placeholders);
    }

    /**
     * Obtiene la longitud del mensaje base
     *
     * @return int Longitud del mensaje base
     */
    public function getMessageLength(): int
    {
        return strlen($this->messageTemplate);
    }

    /**
     * Verifica si el mensaje es corto
     *
     * @return bool True si es corto
     */
    public function isShortMessage(): bool
    {
        return $this->getMessageLength() <= 50;
    }

    /**
     * Verifica si el mensaje es largo
     *
     * @return bool True si es largo
     */
    public function isLongMessage(): bool
    {
        return $this->getMessageLength() > 150;
    }

    /**
     * Obtiene el nivel de prioridad de la categoría
     *
     * @return float Nivel de prioridad
     */
    public function getPriorityLevel(): float
    {
        return $this->category->getPriorityLevel();
    }

    /**
     * Obtiene el grupo de la categoría
     *
     * @return string Grupo de la categoría
     */
    public function getCategoryGroup(): string
    {
        return $this->category->getGroup();
    }

    /**
     * Verifica si la plantilla es popular
     *
     * @return bool True si es popular
     */
    public function isPopular(): bool
    {
        return $this->usageCount >= 100;
    }

    /**
     * Verifica si la plantilla es nueva
     *
     * @return bool True si es nueva
     */
    public function isNew(): bool
    {
        return $this->usageCount <= 10;
    }

    /**
     * Convierte la plantilla a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->jsonSerialize(),
            'name' => $this->name,
            'message_template' => $this->messageTemplate,
            'category' => $this->category->toArray(),
            'description' => $this->description,
            'placeholders' => $this->placeholders,
            'placeholders_count' => $this->getPlaceholdersCount(),
            'is_active' => $this->isActive,
            'is_premium_only' => $this->isPremiumOnly,
            'is_free' => $this->isFree(),
            'effectiveness_score' => $this->effectivenessScore,
            'effectiveness_level' => $this->getEffectivenessLevel(),
            'usage_count' => $this->usageCount,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
            'metadata' => $this->metadata,
            'has_placeholders' => $this->hasPlaceholders(),
            'has_effectiveness_data' => $this->hasEffectivenessData(),
            'has_been_used' => $this->hasBeenUsed(),
            'requires_profile_data' => $this->requiresProfileData(),
            'is_interactive' => $this->isInteractive(),
            'is_positive' => $this->isPositive(),
            'message_length' => $this->getMessageLength(),
            'is_short_message' => $this->isShortMessage(),
            'is_long_message' => $this->isLongMessage(),
            'priority_level' => $this->getPriorityLevel(),
            'category_group' => $this->getCategoryGroup(),
            'is_popular' => $this->isPopular(),
            'is_new' => $this->isNew(),
            'is_high_effectiveness' => $this->isHighEffectiveness(),
            'is_medium_effectiveness' => $this->isMediumEffectiveness(),
            'is_low_effectiveness' => $this->isLowEffectiveness(),
        ];
    }

    /**
     * Representación en string de la plantilla
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return sprintf(
            'IcebreakerTemplate(id=%s, name=%s, category=%s, effectiveness=%s)',
            $this->id->toString(),
            $this->name,
            $this->category->toString(),
            $this->getEffectivenessLevel()
        );
    }

    /**
     * Representación para debugging
     *
     * @return array Información de debug
     */
    public function __debugInfo(): array
    {
        return [
            'id' => $this->id->toString(),
            'name' => $this->name,
            'category' => $this->category->toString(),
            'is_active' => $this->isActive,
            'is_premium_only' => $this->isPremiumOnly,
            'effectiveness_score' => $this->effectivenessScore,
            'effectiveness_level' => $this->getEffectivenessLevel(),
            'usage_count' => $this->usageCount,
            'has_placeholders' => $this->hasPlaceholders(),
            'has_effectiveness_data' => $this->hasEffectivenessData(),
            'has_been_used' => $this->hasBeenUsed(),
            'priority_level' => $this->getPriorityLevel(),
            'category_group' => $this->getCategoryGroup(),
        ];
    }
}
