<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * IcebreakerType Value Object
 * 
 * Representa el tipo de icebreaker en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * del tipo de icebreaker en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para clasificar
 * diferentes tipos de icebreakers y rompehielos en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta tipos válidos predefinidos
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos IcebreakerType con el mismo valor son iguales
 * - Matching-specific: Específico para el dominio de matching y conversación
 * 
 * Tipos disponibles:
 * - template_based: Icebreaker basado en plantillas predefinidas
 * - ai_generated: Icebreaker generado por inteligencia artificial
 * - custom: Icebreaker personalizado creado por el usuario
 * - shared_interest: Icebreaker basado en intereses compartidos
 * - humor: Icebreaker con enfoque humorístico
 * - question: Icebreaker en formato de pregunta
 * - compliment: Icebreaker basado en cumplidos
 * - observation: Icebreaker basado en observaciones del perfil
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class IcebreakerType implements JsonSerializable
{
    /**
     * Tipos válidos para icebreakers
     */
    public const TEMPLATE_BASED = 'template_based';
    public const AI_GENERATED = 'ai_generated';
    public const CUSTOM = 'custom';
    public const SHARED_INTEREST = 'shared_interest';
    public const HUMOR = 'humor';
    public const QUESTION = 'question';
    public const COMPLIMENT = 'compliment';
    public const OBSERVATION = 'observation';

    /**
     * Lista de todos los tipos válidos
     */
    private const VALID_TYPES = [
        self::TEMPLATE_BASED,
        self::AI_GENERATED,
        self::CUSTOM,
        self::SHARED_INTEREST,
        self::HUMOR,
        self::QUESTION,
        self::COMPLIMENT,
        self::OBSERVATION,
    ];

    /**
     * El valor del tipo de icebreaker
     */
    private readonly string $value;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Factory method para crear una nueva instancia de IcebreakerType
     *
     * @param string $value Valor del tipo
     * @return self Nueva instancia de IcebreakerType
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear IcebreakerType desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen el tipo
     * @param string $key Clave del array que contiene el tipo
     * @return self Nueva instancia de IcebreakerType
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromArray(array $data, string $key = 'type'): self
    {
        if (!isset($data[$key])) {
            throw new InvalidArgumentException(
                "Clave '{$key}' no encontrada en los datos proporcionados"
            );
        }

        return self::fromString($data[$key]);
    }

    /**
     * Factory methods para crear tipos específicos
     */
    public static function templateBased(): self
    {
        return new self(self::TEMPLATE_BASED);
    }

    public static function aiGenerated(): self
    {
        return new self(self::AI_GENERATED);
    }

    public static function custom(): self
    {
        return new self(self::CUSTOM);
    }

    public static function sharedInterest(): self
    {
        return new self(self::SHARED_INTEREST);
    }

    public static function humor(): self
    {
        return new self(self::HUMOR);
    }

    public static function question(): self
    {
        return new self(self::QUESTION);
    }

    public static function compliment(): self
    {
        return new self(self::COMPLIMENT);
    }

    public static function observation(): self
    {
        return new self(self::OBSERVATION);
    }

    /**
     * Obtiene el valor del tipo
     *
     * @return string Valor del tipo
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Obtiene el valor del tipo (alias para getValue)
     *
     * @return string Valor del tipo
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Verifica si este IcebreakerType es igual a otro
     *
     * @param self $other Otro IcebreakerType para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si este IcebreakerType es basado en plantillas
     *
     * @return bool True si es basado en plantillas
     */
    public function isTemplateBased(): bool
    {
        return $this->value === self::TEMPLATE_BASED;
    }

    /**
     * Verifica si este IcebreakerType es generado por IA
     *
     * @return bool True si es generado por IA
     */
    public function isAiGenerated(): bool
    {
        return $this->value === self::AI_GENERATED;
    }

    /**
     * Verifica si este IcebreakerType es personalizado
     *
     * @return bool True si es personalizado
     */
    public function isCustom(): bool
    {
        return $this->value === self::CUSTOM;
    }

    /**
     * Verifica si este IcebreakerType es basado en intereses compartidos
     *
     * @return bool True si es basado en intereses compartidos
     */
    public function isSharedInterest(): bool
    {
        return $this->value === self::SHARED_INTEREST;
    }

    /**
     * Verifica si este IcebreakerType es humorístico
     *
     * @return bool True si es humorístico
     */
    public function isHumor(): bool
    {
        return $this->value === self::HUMOR;
    }

    /**
     * Verifica si este IcebreakerType es una pregunta
     *
     * @return bool True si es una pregunta
     */
    public function isQuestion(): bool
    {
        return $this->value === self::QUESTION;
    }

    /**
     * Verifica si este IcebreakerType es un cumplido
     *
     * @return bool True si es un cumplido
     */
    public function isCompliment(): bool
    {
        return $this->value === self::COMPLIMENT;
    }

    /**
     * Verifica si este IcebreakerType es una observación
     *
     * @return bool True si es una observación
     */
    public function isObservation(): bool
    {
        return $this->value === self::OBSERVATION;
    }

    /**
     * Verifica si este IcebreakerType es premium
     * (AI_GENERATED y CUSTOM requieren características premium)
     *
     * @return bool True si es premium
     */
    public function isPremium(): bool
    {
        return $this->isAiGenerated() || $this->isCustom();
    }

    /**
     * Verifica si este IcebreakerType es gratuito
     * (todos excepto AI_GENERATED y CUSTOM son gratuitos)
     *
     * @return bool True si es gratuito
     */
    public function isFree(): bool
    {
        return !$this->isPremium();
    }

    /**
     * Verifica si este IcebreakerType requiere datos de perfil
     * (SHARED_INTEREST y OBSERVATION requieren análisis de perfil)
     *
     * @return bool True si requiere datos de perfil
     */
    public function requiresProfileData(): bool
    {
        return $this->isSharedInterest() || $this->isObservation();
    }

    /**
     * Verifica si este IcebreakerType requiere datos de compatibilidad
     * (SHARED_INTEREST requiere análisis de compatibilidad)
     *
     * @return bool True si requiere datos de compatibilidad
     */
    public function requiresCompatibilityData(): bool
    {
        return $this->isSharedInterest();
    }

    /**
     * Verifica si este IcebreakerType es interactivo
     * (QUESTION requiere respuesta del usuario)
     *
     * @return bool True si es interactivo
     */
    public function isInteractive(): bool
    {
        return $this->isQuestion();
    }

    /**
     * Verifica si este IcebreakerType es positivo
     * (COMPLIMENT y HUMOR tienen tono positivo)
     *
     * @return bool True si es positivo
     */
    public function isPositive(): bool
    {
        return $this->isCompliment() || $this->isHumor();
    }

    /**
     * Obtiene el nivel de personalización del tipo
     * (1 = alta personalización, 5 = baja personalización)
     *
     * @return int Nivel de personalización
     */
    public function getPersonalizationLevel(): int
    {
        return match ($this->value) {
            self::AI_GENERATED => 1, // Máxima personalización para IA
            self::CUSTOM => 1, // Máxima personalización para personalizado
            self::SHARED_INTEREST => 2, // Alta personalización para intereses compartidos
            self::OBSERVATION => 2, // Alta personalización para observaciones
            self::COMPLIMENT => 3, // Personalización media para cumplidos
            self::QUESTION => 3, // Personalización media para preguntas
            self::HUMOR => 4, // Personalización media-baja para humor
            self::TEMPLATE_BASED => 5, // Baja personalización para plantillas
            default => 3,
        };
    }

    /**
     * Obtiene la categoría del tipo
     *
     * @return string Categoría del tipo (premium, profile_based, interactive, positive, template_based)
     */
    public function getCategory(): string
    {
        return match ($this->value) {
            self::AI_GENERATED, self::CUSTOM => 'premium',
            self::SHARED_INTEREST, self::OBSERVATION => 'profile_based',
            self::QUESTION => 'interactive',
            self::COMPLIMENT, self::HUMOR => 'positive',
            self::TEMPLATE_BASED => 'template_based',
            default => 'unknown',
        };
    }

    /**
     * Obtiene la descripción legible del tipo
     *
     * @return string Descripción del tipo
     */
    public function getDescription(): string
    {
        return match ($this->value) {
            self::TEMPLATE_BASED => 'Icebreaker basado en plantillas predefinidas',
            self::AI_GENERATED => 'Icebreaker generado por inteligencia artificial',
            self::CUSTOM => 'Icebreaker personalizado creado por el usuario',
            self::SHARED_INTEREST => 'Icebreaker basado en intereses compartidos',
            self::HUMOR => 'Icebreaker con enfoque humorístico',
            self::QUESTION => 'Icebreaker en formato de pregunta',
            self::COMPLIMENT => 'Icebreaker basado en cumplidos',
            self::OBSERVATION => 'Icebreaker basado en observaciones del perfil',
            default => 'Tipo desconocido',
        };
    }

    /**
     * Obtiene las características asociadas al tipo
     *
     * @return array Características del tipo
     */
    public function getFeatures(): array
    {
        return match ($this->value) {
            self::TEMPLATE_BASED => ['predefined', 'reusable', 'standard'],
            self::AI_GENERATED => ['personalized', 'intelligent', 'premium'],
            self::CUSTOM => ['user_created', 'unique', 'premium'],
            self::SHARED_INTEREST => ['compatibility_focused', 'personalized', 'engaging'],
            self::HUMOR => ['fun', 'lighthearted', 'engaging'],
            self::QUESTION => ['interactive', 'conversation_starting', 'engaging'],
            self::COMPLIMENT => ['positive', 'appreciative', 'engaging'],
            self::OBSERVATION => ['profile_based', 'personalized', 'thoughtful'],
            default => [],
        };
    }

    /**
     * Verifica si este IcebreakerType tiene una característica específica
     *
     * @param string $feature Característica a verificar
     * @return bool True si tiene la característica
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->getFeatures());
    }

    /**
     * Obtiene el peso de efectividad del tipo
     * (basado en estadísticas de éxito)
     *
     * @return float Peso de efectividad (0.0 - 1.0)
     */
    public function getEffectivenessWeight(): float
    {
        return match ($this->value) {
            self::SHARED_INTEREST => 0.95, // Máxima efectividad para intereses compartidos
            self::AI_GENERATED => 0.90, // Alta efectividad para IA
            self::OBSERVATION => 0.85, // Alta efectividad para observaciones
            self::QUESTION => 0.80, // Buena efectividad para preguntas
            self::COMPLIMENT => 0.75, // Buena efectividad para cumplidos
            self::HUMOR => 0.70, // Efectividad media para humor
            self::CUSTOM => 0.65, // Efectividad media para personalizado
            self::TEMPLATE_BASED => 0.60, // Efectividad media-baja para plantillas
            default => 0.50,
        };
    }

    /**
     * Convierte el IcebreakerType a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'description' => $this->getDescription(),
            'category' => $this->getCategory(),
            'personalization_level' => $this->getPersonalizationLevel(),
            'features' => $this->getFeatures(),
            'effectiveness_weight' => $this->getEffectivenessWeight(),
            'is_premium' => $this->isPremium(),
            'requires_profile_data' => $this->requiresProfileData(),
            'requires_compatibility_data' => $this->requiresCompatibilityData(),
            'is_interactive' => $this->isInteractive(),
            'is_positive' => $this->isPositive(),
        ];
    }

    /**
     * Representación en string del IcebreakerType
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
     * @return string Valor para JSON
     */
    public function jsonSerialize(): string
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
            'description' => $this->getDescription(),
            'category' => $this->getCategory(),
            'personalization_level' => $this->getPersonalizationLevel(),
            'is_premium' => $this->isPremium(),
            'effectiveness_weight' => $this->getEffectivenessWeight(),
        ];
    }

    /**
     * Valida que el valor del tipo sea válido
     *
     * @param string $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(string $value): void
    {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(
                "IcebreakerType debe ser uno de los valores válidos: " . implode(', ', self::VALID_TYPES) . 
                ", se recibió: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es un IcebreakerType válido
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válido
     */
    public static function isValid(mixed $value): bool
    {
        try {
            if (is_string($value)) {
                self::validate($value);
                return true;
            }

            return false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Crea un IcebreakerType desde un valor mixto (string, array)
     *
     * @param mixed $value Valor del tipo
     * @param string $arrayKey Clave para arrays (por defecto 'type')
     * @return self Nueva instancia de IcebreakerType
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value, string $arrayKey = 'type'): self
    {
        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_array($value)) {
            return self::fromArray($value, $arrayKey);
        }

        throw new InvalidArgumentException(
            'IcebreakerType solo puede crearse desde string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Obtiene todos los tipos válidos
     *
     * @return array Lista de todos los tipos válidos
     */
    public static function getValidTypes(): array
    {
        return self::VALID_TYPES;
    }

    /**
     * Genera un hash único para este IcebreakerType (útil para cache keys)
     *
     * @return string Hash único del IcebreakerType
     */
    public function getHash(): string
    {
        return 'icebreaker_type_' . $this->value . '_' . substr(md5($this->value), 0, 8);
    }

    /**
     * Verifica si este IcebreakerType es compatible con el sistema de analytics
     * (todos los tipos pueden generar analytics)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si este IcebreakerType es compatible con el sistema de caché
     * (todos los tipos pueden ser cacheados)
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
