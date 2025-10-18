<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use App\Domain\Shared\ValueObjects\UserId;
use InvalidArgumentException;
use JsonSerializable;

/**
 * PersonalizationContext Value Object
 * 
 * Representa el contexto de personalización para icebreakers en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del contexto de personalización en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para encapsular
 * toda la información necesaria para personalizar icebreakers basados en perfiles
 * de usuarios y factores de compatibilidad.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta datos válidos de contexto
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos PersonalizationContext con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y personalización
 * 
 * Contexto incluye:
 * - user_id: ID del usuario que solicita el icebreaker
 * - target_user_id: ID del usuario objetivo
 * - shared_interests: Intereses compartidos entre usuarios
 * - compatibility_factors: Factores de compatibilidad
 * - personality_insights: Insights de personalidad
 * - lifestyle_alignment: Alineación de estilo de vida
 * - communication_preferences: Preferencias de comunicación
 * - cultural_context: Contexto cultural
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class PersonalizationContext implements JsonSerializable
{
    /**
     * ID del usuario que solicita el icebreaker
     */
    private readonly UserId $userId;

    /**
     * ID del usuario objetivo
     */
    private readonly UserId $targetUserId;

    /**
     * Intereses compartidos entre usuarios
     */
    private readonly array $sharedInterests;

    /**
     * Factores de compatibilidad
     */
    private readonly array $compatibilityFactors;

    /**
     * Insights de personalidad
     */
    private readonly array $personalityInsights;

    /**
     * Alineación de estilo de vida
     */
    private readonly array $lifestyleAlignment;

    /**
     * Preferencias de comunicación
     */
    private readonly array $communicationPreferences;

    /**
     * Contexto cultural
     */
    private readonly array $culturalContext;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(
        UserId $userId,
        UserId $targetUserId,
        array $sharedInterests,
        array $compatibilityFactors,
        array $personalityInsights,
        array $lifestyleAlignment,
        array $communicationPreferences,
        array $culturalContext
    ) {
        $this->userId = $userId;
        $this->targetUserId = $targetUserId;
        $this->sharedInterests = $sharedInterests;
        $this->compatibilityFactors = $compatibilityFactors;
        $this->personalityInsights = $personalityInsights;
        $this->lifestyleAlignment = $lifestyleAlignment;
        $this->communicationPreferences = $communicationPreferences;
        $this->culturalContext = $culturalContext;
    }

    /**
     * Factory method para crear una nueva instancia de PersonalizationContext
     *
     * @param array $data Datos del contexto de personalización
     * @return self Nueva instancia de PersonalizationContext
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function fromArray(array $data): self
    {
        self::validateData($data);

        return new self(
            userId: UserId::fromString($data['user_id']),
            targetUserId: UserId::fromString($data['target_user_id']),
            sharedInterests: $data['shared_interests'] ?? [],
            compatibilityFactors: $data['compatibility_factors'] ?? [],
            personalityInsights: $data['personality_insights'] ?? [],
            lifestyleAlignment: $data['lifestyle_alignment'] ?? [],
            communicationPreferences: $data['communication_preferences'] ?? [],
            culturalContext: $data['cultural_context'] ?? []
        );
    }

    /**
     * Factory method para crear PersonalizationContext desde datos de perfiles
     *
     * @param UserId $userId ID del usuario
     * @param UserId $targetUserId ID del usuario objetivo
     * @param array $contextData Datos adicionales del contexto
     * @return self Nueva instancia de PersonalizationContext
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function create(
        UserId $userId,
        UserId $targetUserId,
        array $contextData = []
    ): self {
        return new self(
            userId: $userId,
            targetUserId: $targetUserId,
            sharedInterests: $contextData['shared_interests'] ?? [],
            compatibilityFactors: $contextData['compatibility_factors'] ?? [],
            personalityInsights: $contextData['personality_insights'] ?? [],
            lifestyleAlignment: $contextData['lifestyle_alignment'] ?? [],
            communicationPreferences: $contextData['communication_preferences'] ?? [],
            culturalContext: $contextData['cultural_context'] ?? []
        );
    }

    /**
     * Obtiene el ID del usuario
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
     * Obtiene los intereses compartidos
     *
     * @return array Intereses compartidos
     */
    public function getSharedInterests(): array
    {
        return $this->sharedInterests;
    }

    /**
     * Obtiene los factores de compatibilidad
     *
     * @return array Factores de compatibilidad
     */
    public function getCompatibilityFactors(): array
    {
        return $this->compatibilityFactors;
    }

    /**
     * Obtiene los insights de personalidad
     *
     * @return array Insights de personalidad
     */
    public function getPersonalityInsights(): array
    {
        return $this->personalityInsights;
    }

    /**
     * Obtiene la alineación de estilo de vida
     *
     * @return array Alineación de estilo de vida
     */
    public function getLifestyleAlignment(): array
    {
        return $this->lifestyleAlignment;
    }

    /**
     * Obtiene las preferencias de comunicación
     *
     * @return array Preferencias de comunicación
     */
    public function getCommunicationPreferences(): array
    {
        return $this->communicationPreferences;
    }

    /**
     * Obtiene el contexto cultural
     *
     * @return array Contexto cultural
     */
    public function getCulturalContext(): array
    {
        return $this->culturalContext;
    }

    /**
     * Verifica si este PersonalizationContext es igual a otro
     *
     * @param self $other Otro PersonalizationContext para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->userId->equals($other->userId) &&
               $this->targetUserId->equals($other->targetUserId) &&
               $this->sharedInterests === $other->sharedInterests &&
               $this->compatibilityFactors === $other->compatibilityFactors &&
               $this->personalityInsights === $other->personalityInsights &&
               $this->lifestyleAlignment === $other->lifestyleAlignment &&
               $this->communicationPreferences === $other->communicationPreferences &&
               $this->culturalContext === $other->culturalContext;
    }

    /**
     * Obtiene el puntaje de contexto (0.0 - 1.0)
     * Indica qué tan completo es el contexto para personalización
     *
     * @return float Puntaje de contexto
     */
    public function getContextScore(): float
    {
        $scores = [
            'shared_interests' => count($this->sharedInterests) > 0 ? 0.3 : 0.0,
            'compatibility_factors' => count($this->compatibilityFactors) > 0 ? 0.2 : 0.0,
            'personality_insights' => count($this->personalityInsights) > 0 ? 0.2 : 0.0,
            'lifestyle_alignment' => count($this->lifestyleAlignment) > 0 ? 0.15 : 0.0,
            'communication_preferences' => count($this->communicationPreferences) > 0 ? 0.1 : 0.0,
            'cultural_context' => count($this->culturalContext) > 0 ? 0.05 : 0.0,
        ];

        return min(1.0, array_sum($scores));
    }

    /**
     * Verifica si el contexto es suficiente para personalización
     *
     * @param float $threshold Umbral mínimo (por defecto 0.3)
     * @return bool True si es suficiente
     */
    public function hasSufficientContext(float $threshold = 0.3): bool
    {
        return $this->getContextScore() >= $threshold;
    }

    /**
     * Verifica si hay intereses compartidos
     *
     * @return bool True si hay intereses compartidos
     */
    public function hasSharedInterests(): bool
    {
        return count($this->sharedInterests) > 0;
    }

    /**
     * Verifica si hay factores de compatibilidad
     *
     * @return bool True si hay factores de compatibilidad
     */
    public function hasCompatibilityFactors(): bool
    {
        return count($this->compatibilityFactors) > 0;
    }

    /**
     * Verifica si hay insights de personalidad
     *
     * @return bool True si hay insights de personalidad
     */
    public function hasPersonalityInsights(): bool
    {
        return count($this->personalityInsights) > 0;
    }

    /**
     * Verifica si hay alineación de estilo de vida
     *
     * @return bool True si hay alineación de estilo de vida
     */
    public function hasLifestyleAlignment(): bool
    {
        return count($this->lifestyleAlignment) > 0;
    }

    /**
     * Verifica si hay preferencias de comunicación
     *
     * @return bool True si hay preferencias de comunicación
     */
    public function hasCommunicationPreferences(): bool
    {
        return count($this->communicationPreferences) > 0;
    }

    /**
     * Verifica si hay contexto cultural
     *
     * @return bool True si hay contexto cultural
     */
    public function hasCulturalContext(): bool
    {
        return count($this->culturalContext) > 0;
    }

    /**
     * Obtiene el número total de elementos de contexto
     *
     * @return int Número total de elementos
     */
    public function getTotalContextElements(): int
    {
        return count($this->sharedInterests) +
               count($this->compatibilityFactors) +
               count($this->personalityInsights) +
               count($this->lifestyleAlignment) +
               count($this->communicationPreferences) +
               count($this->culturalContext);
    }

    /**
     * Obtiene el nivel de personalización
     * (1 = alta personalización, 5 = baja personalización)
     *
     * @return int Nivel de personalización
     */
    public function getPersonalizationLevel(): int
    {
        $score = $this->getContextScore();

        return match (true) {
            $score >= 0.8 => 1, // Alta personalización
            $score >= 0.6 => 2, // Personalización alta-media
            $score >= 0.4 => 3, // Personalización media
            $score >= 0.2 => 4, // Personalización media-baja
            default => 5, // Baja personalización
        };
    }

    /**
     * Obtiene la categoría de contexto
     *
     * @return string Categoría del contexto (rich, moderate, basic, minimal)
     */
    public function getContextCategory(): string
    {
        $score = $this->getContextScore();

        return match (true) {
            $score >= 0.8 => 'rich',
            $score >= 0.6 => 'moderate',
            $score >= 0.3 => 'basic',
            default => 'minimal',
        };
    }

    /**
     * Obtiene las características disponibles del contexto
     *
     * @return array Características disponibles
     */
    public function getAvailableFeatures(): array
    {
        $features = [];

        if ($this->hasSharedInterests()) {
            $features[] = 'shared_interests';
        }

        if ($this->hasCompatibilityFactors()) {
            $features[] = 'compatibility_factors';
        }

        if ($this->hasPersonalityInsights()) {
            $features[] = 'personality_insights';
        }

        if ($this->hasLifestyleAlignment()) {
            $features[] = 'lifestyle_alignment';
        }

        if ($this->hasCommunicationPreferences()) {
            $features[] = 'communication_preferences';
        }

        if ($this->hasCulturalContext()) {
            $features[] = 'cultural_context';
        }

        return $features;
    }

    /**
     * Obtiene un resumen del contexto para logging
     *
     * @return array Resumen del contexto
     */
    public function getSummary(): array
    {
        return [
            'user_id' => $this->userId->toString(),
            'target_user_id' => $this->targetUserId->toString(),
            'context_score' => $this->getContextScore(),
            'personalization_level' => $this->getPersonalizationLevel(),
            'context_category' => $this->getContextCategory(),
            'total_elements' => $this->getTotalContextElements(),
            'available_features' => $this->getAvailableFeatures(),
            'has_shared_interests' => $this->hasSharedInterests(),
            'has_compatibility_factors' => $this->hasCompatibilityFactors(),
            'has_personality_insights' => $this->hasPersonalityInsights(),
            'has_lifestyle_alignment' => $this->hasLifestyleAlignment(),
            'has_communication_preferences' => $this->hasCommunicationPreferences(),
            'has_cultural_context' => $this->hasCulturalContext(),
        ];
    }

    /**
     * Convierte el PersonalizationContext a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId->toString(),
            'target_user_id' => $this->targetUserId->toString(),
            'shared_interests' => $this->sharedInterests,
            'compatibility_factors' => $this->compatibilityFactors,
            'personality_insights' => $this->personalityInsights,
            'lifestyle_alignment' => $this->lifestyleAlignment,
            'communication_preferences' => $this->communicationPreferences,
            'cultural_context' => $this->culturalContext,
            'context_score' => $this->getContextScore(),
            'personalization_level' => $this->getPersonalizationLevel(),
            'context_category' => $this->getContextCategory(),
            'total_elements' => $this->getTotalContextElements(),
            'available_features' => $this->getAvailableFeatures(),
        ];
    }

    /**
     * Representación en string del PersonalizationContext
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return sprintf(
            'PersonalizationContext(user_id=%s, target_user_id=%s, score=%.2f)',
            $this->userId->toString(),
            $this->targetUserId->toString(),
            $this->getContextScore()
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
     * Valida que los datos del contexto sean válidos
     *
     * @param array $data Datos a validar
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    private static function validateData(array $data): void
    {
        if (!isset($data['user_id'])) {
            throw new InvalidArgumentException('user_id es requerido en PersonalizationContext');
        }

        if (!isset($data['target_user_id'])) {
            throw new InvalidArgumentException('target_user_id es requerido en PersonalizationContext');
        }

        if (!is_string($data['user_id'])) {
            throw new InvalidArgumentException('user_id debe ser string en PersonalizationContext');
        }

        if (!is_string($data['target_user_id'])) {
            throw new InvalidArgumentException('target_user_id debe ser string en PersonalizationContext');
        }
    }

    /**
     * Verifica si un valor es un PersonalizationContext válido
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
                self::validateData($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea un PersonalizationContext desde un valor mixto
     *
     * @param mixed $value Valor del contexto
     * @return self Nueva instancia de PersonalizationContext
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
            'PersonalizationContext solo puede crearse desde array o instancia de PersonalizationContext, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para este contexto (útil para cache keys)
     *
     * @return string Hash único del contexto
     */
    public function getHash(): string
    {
        $data = [
            $this->userId->toString(),
            $this->targetUserId->toString(),
            $this->sharedInterests,
            $this->compatibilityFactors,
            $this->personalityInsights,
            $this->lifestyleAlignment,
            $this->communicationPreferences,
            $this->culturalContext,
        ];

        return 'personalization_context_' . substr(md5(serialize($data)), 0, 16);
    }

    /**
     * Verifica si este contexto es compatible con el sistema de analytics
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si este contexto es compatible con el sistema de caché
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
