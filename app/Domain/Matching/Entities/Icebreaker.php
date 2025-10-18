<?php

declare(strict_types=1);

namespace App\Domain\Matching\Entities;

use App\Domain\Matching\ValueObjects\IcebreakerId;
use App\Domain\Matching\ValueObjects\IcebreakerType;
use App\Domain\Matching\ValueObjects\IcebreakerCategory;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\CarbonInterface;

/**
 * Icebreaker Entity
 * 
 * Representa un icebreaker en el sistema ForeverUsInLove.
 * Implementa el patrón Entity para encapsular la lógica de negocio
 * relacionada con los icebreakers y rompehielos de conversación.
 * 
 * Este Entity es específico del dominio Matching y se utiliza para manejar
 * los icebreakers generados, incluyendo mensajes personalizados, categorización,
 * efectividad y datos de personalización.
 * 
 * Características:
 * - Encapsula la lógica de negocio de icebreakers
 * - Maneja mensajes de conversación personalizados
 * - Proporciona categorización y tipificación
 * - Gestiona datos de efectividad y personalización
 * - Domain-specific: Específico para el dominio de matching
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class Icebreaker
{
    /**
     * Identificador único del icebreaker
     */
    private readonly IcebreakerId $id;

    /**
     * ID del usuario que envía el icebreaker
     */
    private readonly UserId $userId;

    /**
     * ID del usuario objetivo del icebreaker
     */
    private readonly UserId $targetUserId;

    /**
     * Mensaje del icebreaker
     */
    private readonly string $message;

    /**
     * Tipo del icebreaker
     */
    private readonly IcebreakerType $type;

    /**
     * Categoría del icebreaker
     */
    private readonly IcebreakerCategory $category;

    /**
     * Score de confianza del icebreaker
     */
    private readonly float $confidenceScore;

    /**
     * Factores de personalización utilizados
     */
    private readonly array $personalizationFactors;

    /**
     * Fecha de creación del icebreaker
     */
    private readonly CarbonInterface $createdAt;

    /**
     * Indica si es un icebreaker personalizado
     */
    private readonly bool $isCustom;

    /**
     * ID de la plantilla utilizada (si aplica)
     */
    private readonly ?IcebreakerId $templateId;

    /**
     * Datos de efectividad del icebreaker
     */
    private readonly ?array $effectiveness;

    /**
     * Constructor de la entidad Icebreaker
     */
    public function __construct(
        IcebreakerId $id,
        UserId $userId,
        UserId $targetUserId,
        string $message,
        IcebreakerType $type,
        IcebreakerCategory $category,
        float $confidenceScore = 0.0,
        array $personalizationFactors = [],
        ?CarbonInterface $createdAt = null,
        bool $isCustom = false,
        ?IcebreakerId $templateId = null,
        ?array $effectiveness = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->targetUserId = $targetUserId;
        $this->message = $message;
        $this->type = $type;
        $this->category = $category;
        $this->confidenceScore = $confidenceScore;
        $this->personalizationFactors = $personalizationFactors;
        $this->createdAt = $createdAt ?? now();
        $this->isCustom = $isCustom;
        $this->templateId = $templateId;
        $this->effectiveness = $effectiveness;
    }

    /**
     * Obtiene el ID del icebreaker
     *
     * @return IcebreakerId ID del icebreaker
     */
    public function getId(): IcebreakerId
    {
        return $this->id;
    }

    /**
     * Obtiene el ID del usuario que envía
     *
     * @return UserId ID del usuario
     */
    public function getUserId(): UserId
    {
        return $this->userId;
    }

    /**
     * Obtiene el ID del usuario objetivo
     *
     * @return UserId ID del usuario objetivo
     */
    public function getTargetUserId(): UserId
    {
        return $this->targetUserId;
    }

    /**
     * Obtiene el mensaje del icebreaker
     *
     * @return string Mensaje del icebreaker
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Obtiene el tipo del icebreaker
     *
     * @return IcebreakerType Tipo del icebreaker
     */
    public function getType(): IcebreakerType
    {
        return $this->type;
    }

    /**
     * Obtiene la categoría del icebreaker
     *
     * @return IcebreakerCategory Categoría del icebreaker
     */
    public function getCategory(): IcebreakerCategory
    {
        return $this->category;
    }

    /**
     * Obtiene el score de confianza
     *
     * @return float Score de confianza
     */
    public function getConfidenceScore(): float
    {
        return $this->confidenceScore;
    }

    /**
     * Obtiene los factores de personalización
     *
     * @return array Factores de personalización
     */
    public function getPersonalizationFactors(): array
    {
        return $this->personalizationFactors;
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
     * Verifica si es personalizado
     *
     * @return bool True si es personalizado
     */
    public function isCustom(): bool
    {
        return $this->isCustom;
    }

    /**
     * Obtiene el ID de la plantilla
     *
     * @return IcebreakerId|null ID de la plantilla
     */
    public function getTemplateId(): ?IcebreakerId
    {
        return $this->templateId;
    }

    /**
     * Obtiene los datos de efectividad
     *
     * @return array|null Datos de efectividad
     */
    public function getEffectiveness(): ?array
    {
        return $this->effectiveness;
    }

    /**
     * Verifica si tiene factores de personalización
     *
     * @return bool True si tiene factores
     */
    public function hasPersonalizationFactors(): bool
    {
        return !empty($this->personalizationFactors);
    }

    /**
     * Verifica si tiene datos de efectividad
     *
     * @return bool True si tiene datos de efectividad
     */
    public function hasEffectiveness(): bool
    {
        return $this->effectiveness !== null && !empty($this->effectiveness);
    }

    /**
     * Verifica si usa una plantilla
     *
     * @return bool True si usa plantilla
     */
    public function usesTemplate(): bool
    {
        return $this->templateId !== null;
    }

    /**
     * Obtiene un factor de personalización específico
     *
     * @param string $key Clave del factor
     * @param mixed $default Valor por defecto
     * @return mixed Valor del factor
     */
    public function getPersonalizationFactor(string $key, $default = null)
    {
        return $this->personalizationFactors[$key] ?? $default;
    }

    /**
     * Obtiene un dato de efectividad específico
     *
     * @param string $key Clave del dato
     * @param mixed $default Valor por defecto
     * @return mixed Valor del dato
     */
    public function getEffectivenessValue(string $key, $default = null)
    {
        return $this->effectiveness[$key] ?? $default;
    }

    /**
     * Verifica si es de alta confianza
     *
     * @return bool True si es de alta confianza
     */
    public function isHighConfidence(): bool
    {
        return $this->confidenceScore >= 0.8;
    }

    /**
     * Verifica si es de confianza media
     *
     * @return bool True si es de confianza media
     */
    public function isMediumConfidence(): bool
    {
        return $this->confidenceScore >= 0.5 && $this->confidenceScore < 0.8;
    }

    /**
     * Verifica si es de baja confianza
     *
     * @return bool True si es de baja confianza
     */
    public function isLowConfidence(): bool
    {
        return $this->confidenceScore < 0.5;
    }

    /**
     * Obtiene el nivel de confianza como string
     *
     * @return string Nivel de confianza
     */
    public function getConfidenceLevel(): string
    {
        if ($this->isHighConfidence()) {
            return 'high';
        }

        if ($this->isMediumConfidence()) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Verifica si es premium
     *
     * @return bool True si es premium
     */
    public function isPremium(): bool
    {
        return $this->type->isPremium();
    }

    /**
     * Verifica si es gratuito
     *
     * @return bool True si es gratuito
     */
    public function isFree(): bool
    {
        return $this->type->isFree();
    }

    /**
     * Verifica si requiere datos de perfil
     *
     * @return bool True si requiere datos de perfil
     */
    public function requiresProfileData(): bool
    {
        return $this->type->requiresProfileData();
    }

    /**
     * Verifica si es interactivo
     *
     * @return bool True si es interactivo
     */
    public function isInteractive(): bool
    {
        return $this->type->isInteractive();
    }

    /**
     * Verifica si es positivo
     *
     * @return bool True si es positivo
     */
    public function isPositive(): bool
    {
        return $this->type->isPositive();
    }

    /**
     * Obtiene el número de factores de personalización
     *
     * @return int Número de factores
     */
    public function getPersonalizationFactorsCount(): int
    {
        return count($this->personalizationFactors);
    }

    /**
     * Obtiene la longitud del mensaje
     *
     * @return int Longitud del mensaje
     */
    public function getMessageLength(): int
    {
        return strlen($this->message);
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
     * Obtiene el nivel de personalización
     *
     * @return int Nivel de personalización
     */
    public function getPersonalizationLevel(): int
    {
        return $this->type->getPersonalizationLevel();
    }

    /**
     * Convierte el icebreaker a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->jsonSerialize(),
            'user_id' => $this->userId->jsonSerialize(),
            'target_user_id' => $this->targetUserId->jsonSerialize(),
            'message' => $this->message,
            'type' => $this->type->toArray(),
            'category' => $this->category->toArray(),
            'confidence_score' => $this->confidenceScore,
            'confidence_level' => $this->getConfidenceLevel(),
            'personalization_factors' => $this->personalizationFactors,
            'personalization_factors_count' => $this->getPersonalizationFactorsCount(),
            'personalization_level' => $this->getPersonalizationLevel(),
            'created_at' => $this->createdAt->toISOString(),
            'is_custom' => $this->isCustom,
            'template_id' => $this->templateId?->jsonSerialize(),
            'effectiveness' => $this->effectiveness,
            'has_personalization_factors' => $this->hasPersonalizationFactors(),
            'has_effectiveness' => $this->hasEffectiveness(),
            'uses_template' => $this->usesTemplate(),
            'is_premium' => $this->isPremium(),
            'is_free' => $this->isFree(),
            'requires_profile_data' => $this->requiresProfileData(),
            'is_interactive' => $this->isInteractive(),
            'is_positive' => $this->isPositive(),
            'message_length' => $this->getMessageLength(),
            'is_short_message' => $this->isShortMessage(),
            'is_long_message' => $this->isLongMessage(),
            'is_high_confidence' => $this->isHighConfidence(),
            'is_medium_confidence' => $this->isMediumConfidence(),
            'is_low_confidence' => $this->isLowConfidence(),
        ];
    }

    /**
     * Representación en string del icebreaker
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return sprintf(
            'Icebreaker(id=%s, type=%s, category=%s, confidence=%s)',
            $this->id->toString(),
            $this->type->toString(),
            $this->category->toString(),
            $this->getConfidenceLevel()
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
            'target_user_id' => $this->targetUserId->toString(),
            'type' => $this->type->toString(),
            'category' => $this->category->toString(),
            'confidence_score' => $this->confidenceScore,
            'confidence_level' => $this->getConfidenceLevel(),
            'is_custom' => $this->isCustom,
            'is_premium' => $this->isPremium(),
            'has_personalization_factors' => $this->hasPersonalizationFactors(),
            'has_effectiveness' => $this->hasEffectiveness(),
            'uses_template' => $this->usesTemplate(),
            'message_length' => $this->getMessageLength(),
            'personalization_level' => $this->getPersonalizationLevel(),
        ];
    }
}
