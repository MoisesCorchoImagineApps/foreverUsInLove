<?php

declare(strict_types=1);

namespace App\Domain\Matching\Entities;

use App\Domain\Matching\ValueObjects\IcebreakerId;
use App\Domain\Matching\ValueObjects\IcebreakerCategory;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\CarbonInterface;

/**
 * CustomIcebreaker Entity
 * 
 * Representa un icebreaker personalizado creado por el usuario en el sistema ForeverUsInLove.
 * Implementa el patrón Entity para encapsular la lógica de negocio
 * relacionada con los icebreakers personalizados creados por usuarios.
 * 
 * Este Entity es específico del dominio Matching y se utiliza para manejar
 * los icebreakers personalizados, incluyendo plantillas de mensajes,
 * categorización, configuración de privacidad y datos de efectividad.
 * 
 * Características:
 * - Encapsula la lógica de negocio de icebreakers personalizados
 * - Maneja plantillas de mensajes con placeholders
 * - Proporciona configuración de privacidad y compartir
 * - Gestiona datos de efectividad y uso personalizado
 * - Domain-specific: Específico para el dominio de matching
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class CustomIcebreaker
{
    /**
     * Identificador único del icebreaker personalizado
     */
    private readonly IcebreakerId $id;

    /**
     * ID del usuario que creó el icebreaker
     */
    private readonly UserId $userId;

    /**
     * Nombre del icebreaker personalizado
     */
    private readonly string $name;

    /**
     * Plantilla de mensaje con placeholders
     */
    private readonly string $messageTemplate;

    /**
     * Categoría del icebreaker personalizado
     */
    private readonly IcebreakerCategory $category;

    /**
     * Indica si es público (compartible)
     */
    private readonly bool $isPublic;

    /**
     * Indica si es solo para usuarios premium
     */
    private readonly bool $isPremiumOnly;

    /**
     * Descripción del icebreaker personalizado
     */
    private readonly string $description;

    /**
     * Tags asociados al icebreaker
     */
    private readonly array $tags;

    /**
     * Fecha de creación
     */
    private readonly CarbonInterface $createdAt;

    /**
     * Fecha de última actualización
     */
    private readonly CarbonInterface $updatedAt;

    /**
     * Número de veces que se ha usado
     */
    private readonly int $usageCount;

    /**
     * Score de efectividad del icebreaker personalizado
     */
    private readonly ?float $effectivenessScore;

    /**
     * Metadatos adicionales
     */
    private readonly array $metadata;

    /**
     * Constructor de la entidad CustomIcebreaker
     */
    public function __construct(
        IcebreakerId $id,
        UserId $userId,
        string $name,
        string $messageTemplate,
        IcebreakerCategory $category,
        bool $isPublic = false,
        bool $isPremiumOnly = false,
        string $description = '',
        array $tags = [],
        ?CarbonInterface $createdAt = null,
        ?CarbonInterface $updatedAt = null,
        int $usageCount = 0,
        ?float $effectivenessScore = null,
        array $metadata = []
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->name = $name;
        $this->messageTemplate = $messageTemplate;
        $this->category = $category;
        $this->isPublic = $isPublic;
        $this->isPremiumOnly = $isPremiumOnly;
        $this->description = $description;
        $this->tags = $tags;
        $this->createdAt = $createdAt ?? now();
        $this->updatedAt = $updatedAt ?? now();
        $this->usageCount = $usageCount;
        $this->effectivenessScore = $effectivenessScore;
        $this->metadata = $metadata;
    }

    /**
     * Obtiene el ID del icebreaker personalizado
     *
     * @return IcebreakerId ID del icebreaker personalizado
     */
    public function getId(): IcebreakerId
    {
        return $this->id;
    }

    /**
     * Obtiene el ID del usuario creador
     *
     * @return UserId ID del usuario creador
     */
    public function getUserId(): UserId
    {
        return $this->userId;
    }

    /**
     * Obtiene el nombre del icebreaker personalizado
     *
     * @return string Nombre del icebreaker personalizado
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Obtiene la plantilla de mensaje
     *
     * @return string Plantilla de mensaje
     */
    public function getMessageTemplate(): string
    {
        return $this->messageTemplate;
    }

    /**
     * Obtiene la categoría del icebreaker personalizado
     *
     * @return IcebreakerCategory Categoría del icebreaker personalizado
     */
    public function getCategory(): IcebreakerCategory
    {
        return $this->category;
    }

    /**
     * Verifica si es público
     *
     * @return bool True si es público
     */
    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    /**
     * Verifica si es privado
     *
     * @return bool True si es privado
     */
    public function isPrivate(): bool
    {
        return !$this->isPublic;
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
     * Verifica si es gratuito
     *
     * @return bool True si es gratuito
     */
    public function isFree(): bool
    {
        return !$this->isPremiumOnly;
    }

    /**
     * Obtiene la descripción
     *
     * @return string Descripción
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Obtiene los tags
     *
     * @return array Tags
     */
    public function getTags(): array
    {
        return $this->tags;
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
     * Obtiene el número de usos
     *
     * @return int Número de usos
     */
    public function getUsageCount(): int
    {
        return $this->usageCount;
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
     * Obtiene los metadatos
     *
     * @return array Metadatos
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Verifica si tiene descripción
     *
     * @return bool True si tiene descripción
     */
    public function hasDescription(): bool
    {
        return !empty($this->description);
    }

    /**
     * Verifica si tiene tags
     *
     * @return bool True si tiene tags
     */
    public function hasTags(): bool
    {
        return !empty($this->tags);
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
     * Verifica si ha sido usado
     *
     * @return bool True si ha sido usado
     */
    public function hasBeenUsed(): bool
    {
        return $this->usageCount > 0;
    }

    /**
     * Obtiene un tag específico
     *
     * @param string $tag Tag a buscar
     * @return bool True si tiene el tag
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
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
     * Verifica si es compartible
     *
     * @return bool True si es compartible
     */
    public function isShareable(): bool
    {
        return $this->isPublic;
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
     * Verifica si es interactivo
     *
     * @return bool True si es interactivo
     */
    public function isInteractive(): bool
    {
        return $this->category->isInteractive();
    }

    /**
     * Verifica si es positivo
     *
     * @return bool True si es positivo
     */
    public function isPositive(): bool
    {
        return $this->category->isPositive();
    }

    /**
     * Obtiene el número de tags
     *
     * @return int Número de tags
     */
    public function getTagsCount(): int
    {
        return count($this->tags);
    }

    /**
     * Obtiene la longitud de la plantilla de mensaje
     *
     * @return int Longitud de la plantilla
     */
    public function getMessageTemplateLength(): int
    {
        return strlen($this->messageTemplate);
    }

    /**
     * Verifica si la plantilla es corta
     *
     * @return bool True si es corta
     */
    public function isShortTemplate(): bool
    {
        return $this->getMessageTemplateLength() <= 50;
    }

    /**
     * Verifica si la plantilla es larga
     *
     * @return bool True si es larga
     */
    public function isLongTemplate(): bool
    {
        return $this->getMessageTemplateLength() > 150;
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
     * Verifica si el icebreaker es popular
     *
     * @return bool True si es popular
     */
    public function isPopular(): bool
    {
        return $this->usageCount >= 50;
    }

    /**
     * Verifica si el icebreaker es nuevo
     *
     * @return bool True si es nuevo
     */
    public function isNew(): bool
    {
        return $this->usageCount <= 5;
    }

    /**
     * Verifica si el icebreaker es reciente
     *
     * @return bool True si es reciente (creado en los últimos 7 días)
     */
    public function isRecent(): bool
    {
        return $this->createdAt->isAfter(now()->subDays(7));
    }

    /**
     * Convierte el icebreaker personalizado a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->jsonSerialize(),
            'user_id' => $this->userId->jsonSerialize(),
            'name' => $this->name,
            'message_template' => $this->messageTemplate,
            'category' => $this->category->toArray(),
            'is_public' => $this->isPublic,
            'is_private' => $this->isPrivate(),
            'is_premium_only' => $this->isPremiumOnly,
            'is_free' => $this->isFree(),
            'description' => $this->description,
            'tags' => $this->tags,
            'tags_count' => $this->getTagsCount(),
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
            'usage_count' => $this->usageCount,
            'effectiveness_score' => $this->effectivenessScore,
            'effectiveness_level' => $this->getEffectivenessLevel(),
            'metadata' => $this->metadata,
            'has_description' => $this->hasDescription(),
            'has_tags' => $this->hasTags(),
            'has_effectiveness_data' => $this->hasEffectivenessData(),
            'has_been_used' => $this->hasBeenUsed(),
            'is_shareable' => $this->isShareable(),
            'requires_profile_data' => $this->requiresProfileData(),
            'is_interactive' => $this->isInteractive(),
            'is_positive' => $this->isPositive(),
            'message_template_length' => $this->getMessageTemplateLength(),
            'is_short_template' => $this->isShortTemplate(),
            'is_long_template' => $this->isLongTemplate(),
            'priority_level' => $this->getPriorityLevel(),
            'category_group' => $this->getCategoryGroup(),
            'is_popular' => $this->isPopular(),
            'is_new' => $this->isNew(),
            'is_recent' => $this->isRecent(),
            'is_high_effectiveness' => $this->isHighEffectiveness(),
            'is_medium_effectiveness' => $this->isMediumEffectiveness(),
            'is_low_effectiveness' => $this->isLowEffectiveness(),
        ];
    }

    /**
     * Representación en string del icebreaker personalizado
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return sprintf(
            'CustomIcebreaker(id=%s, name=%s, user=%s, category=%s, effectiveness=%s)',
            $this->id->toString(),
            $this->name,
            $this->userId->toString(),
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
            'user_id' => $this->userId->toString(),
            'name' => $this->name,
            'category' => $this->category->toString(),
            'is_public' => $this->isPublic,
            'is_premium_only' => $this->isPremiumOnly,
            'effectiveness_score' => $this->effectivenessScore,
            'effectiveness_level' => $this->getEffectivenessLevel(),
            'usage_count' => $this->usageCount,
            'has_description' => $this->hasDescription(),
            'has_tags' => $this->hasTags(),
            'has_effectiveness_data' => $this->hasEffectivenessData(),
            'has_been_used' => $this->hasBeenUsed(),
            'is_shareable' => $this->isShareable(),
            'priority_level' => $this->getPriorityLevel(),
            'category_group' => $this->getCategoryGroup(),
        ];
    }
}
