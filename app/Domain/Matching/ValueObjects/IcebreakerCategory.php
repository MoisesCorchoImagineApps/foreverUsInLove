<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * IcebreakerCategory Value Object
 * 
 * Representa la categoría de icebreaker en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de la categoría de icebreaker en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para clasificar
 * icebreakers por categorías temáticas en la aplicación de citas.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta categorías válidas predefinidas
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos IcebreakerCategory con el mismo valor son iguales
 * - Matching-specific: Específico para el dominio de matching y conversación
 * 
 * Categorías disponibles:
 * - shared_interest: Basado en intereses compartidos
 * - lifestyle: Relacionado con rutinas diarias y hábitos
 * - travel_adventure: Enfocado en experiencias de viaje y aventura
 * - entertainment: Películas, música, libros, juegos y cultura pop
 * - food_dining: Preferencias culinarias, cocina y experiencias gastronómicas
 * - career_ambition: Intereses profesionales y aspiraciones de carrera
 * - humor_fun: Ligero, divertido y juguetón
 * - deep_questions: Preguntas reflexivas para conversaciones significativas
 * - current_events: Discusiones sobre eventos recientes o tendencias
 * - personal_growth: Automejora, metas y desarrollo personal
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class IcebreakerCategory implements JsonSerializable
{
    /**
     * Categorías válidas para icebreakers
     */
    public const SHARED_INTEREST = 'shared_interest';
    public const LIFESTYLE = 'lifestyle';
    public const TRAVEL_ADVENTURE = 'travel_adventure';
    public const ENTERTAINMENT = 'entertainment';
    public const FOOD_DINING = 'food_dining';
    public const CAREER_AMBITION = 'career_ambition';
    public const HUMOR_FUN = 'humor_fun';
    public const DEEP_QUESTIONS = 'deep_questions';
    public const CURRENT_EVENTS = 'current_events';
    public const PERSONAL_GROWTH = 'personal_growth';

    /**
     * Lista de todas las categorías válidas
     */
    private const VALID_CATEGORIES = [
        self::SHARED_INTEREST,
        self::LIFESTYLE,
        self::TRAVEL_ADVENTURE,
        self::ENTERTAINMENT,
        self::FOOD_DINING,
        self::CAREER_AMBITION,
        self::HUMOR_FUN,
        self::DEEP_QUESTIONS,
        self::CURRENT_EVENTS,
        self::PERSONAL_GROWTH,
    ];

    /**
     * El valor de la categoría de icebreaker
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
     * Factory method para crear una nueva instancia de IcebreakerCategory
     *
     * @param string $value Valor de la categoría
     * @return self Nueva instancia de IcebreakerCategory
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromString(string $value): self
    {
        self::validate($value);
        return new self($value);
    }

    /**
     * Factory method para crear IcebreakerCategory desde array (útil para deserialización)
     *
     * @param array $data Datos que contienen la categoría
     * @param string $key Clave del array que contiene la categoría
     * @return self Nueva instancia de IcebreakerCategory
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function fromArray(array $data, string $key = 'category'): self
    {
        if (!isset($data[$key])) {
            throw new InvalidArgumentException(
                "Clave '{$key}' no encontrada en los datos proporcionados"
            );
        }

        return self::fromString($data[$key]);
    }

    /**
     * Factory methods para crear categorías específicas
     */
    public static function sharedInterest(): self
    {
        return new self(self::SHARED_INTEREST);
    }

    public static function lifestyle(): self
    {
        return new self(self::LIFESTYLE);
    }

    public static function travelAdventure(): self
    {
        return new self(self::TRAVEL_ADVENTURE);
    }

    public static function entertainment(): self
    {
        return new self(self::ENTERTAINMENT);
    }

    public static function foodDining(): self
    {
        return new self(self::FOOD_DINING);
    }

    public static function careerAmbition(): self
    {
        return new self(self::CAREER_AMBITION);
    }

    public static function humorFun(): self
    {
        return new self(self::HUMOR_FUN);
    }

    public static function deepQuestions(): self
    {
        return new self(self::DEEP_QUESTIONS);
    }

    public static function currentEvents(): self
    {
        return new self(self::CURRENT_EVENTS);
    }

    public static function personalGrowth(): self
    {
        return new self(self::PERSONAL_GROWTH);
    }

    /**
     * Obtiene el valor de la categoría
     *
     * @return string Valor de la categoría
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Obtiene el valor de la categoría (alias para getValue)
     *
     * @return string Valor de la categoría
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Verifica si este IcebreakerCategory es igual a otro
     *
     * @param self $other Otro IcebreakerCategory para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Verifica si esta categoría es de intereses compartidos
     *
     * @return bool True si es de intereses compartidos
     */
    public function isSharedInterest(): bool
    {
        return $this->value === self::SHARED_INTEREST;
    }

    /**
     * Verifica si esta categoría es de estilo de vida
     *
     * @return bool True si es de estilo de vida
     */
    public function isLifestyle(): bool
    {
        return $this->value === self::LIFESTYLE;
    }

    /**
     * Verifica si esta categoría es de viajes y aventura
     *
     * @return bool True si es de viajes y aventura
     */
    public function isTravelAdventure(): bool
    {
        return $this->value === self::TRAVEL_ADVENTURE;
    }

    /**
     * Verifica si esta categoría es de entretenimiento
     *
     * @return bool True si es de entretenimiento
     */
    public function isEntertainment(): bool
    {
        return $this->value === self::ENTERTAINMENT;
    }

    /**
     * Verifica si esta categoría es de comida y gastronomía
     *
     * @return bool True si es de comida y gastronomía
     */
    public function isFoodDining(): bool
    {
        return $this->value === self::FOOD_DINING;
    }

    /**
     * Verifica si esta categoría es de carrera y ambición
     *
     * @return bool True si es de carrera y ambición
     */
    public function isCareerAmbition(): bool
    {
        return $this->value === self::CAREER_AMBITION;
    }

    /**
     * Verifica si esta categoría es de humor y diversión
     *
     * @return bool True si es de humor y diversión
     */
    public function isHumorFun(): bool
    {
        return $this->value === self::HUMOR_FUN;
    }

    /**
     * Verifica si esta categoría es de preguntas profundas
     *
     * @return bool True si es de preguntas profundas
     */
    public function isDeepQuestions(): bool
    {
        return $this->value === self::DEEP_QUESTIONS;
    }

    /**
     * Verifica si esta categoría es de eventos actuales
     *
     * @return bool True si es de eventos actuales
     */
    public function isCurrentEvents(): bool
    {
        return $this->value === self::CURRENT_EVENTS;
    }

    /**
     * Verifica si esta categoría es de crecimiento personal
     *
     * @return bool True si es de crecimiento personal
     */
    public function isPersonalGrowth(): bool
    {
        return $this->value === self::PERSONAL_GROWTH;
    }

    /**
     * Verifica si esta categoría es ligera
     * (HUMOR_FUN, ENTERTAINMENT, FOOD_DINING son categorías ligeras)
     *
     * @return bool True si es ligera
     */
    public function isLight(): bool
    {
        return $this->isHumorFun() || $this->isEntertainment() || $this->isFoodDining();
    }

    /**
     * Verifica si esta categoría es profunda
     * (DEEP_QUESTIONS, PERSONAL_GROWTH, CAREER_AMBITION son categorías profundas)
     *
     * @return bool True si es profunda
     */
    public function isDeep(): bool
    {
        return $this->isDeepQuestions() || $this->isPersonalGrowth() || $this->isCareerAmbition();
    }

    /**
     * Verifica si esta categoría es personal
     * (SHARED_INTEREST, LIFESTYLE, PERSONAL_GROWTH son categorías personales)
     *
     * @return bool True si es personal
     */
    public function isPersonal(): bool
    {
        return $this->isSharedInterest() || $this->isLifestyle() || $this->isPersonalGrowth();
    }

    /**
     * Verifica si esta categoría es social
     * (TRAVEL_ADVENTURE, ENTERTAINMENT, CURRENT_EVENTS son categorías sociales)
     *
     * @return bool True si es social
     */
    public function isSocial(): bool
    {
        return $this->isTravelAdventure() || $this->isEntertainment() || $this->isCurrentEvents();
    }

    /**
     * Verifica si esta categoría requiere datos de perfil
     * (SHARED_INTEREST requiere análisis de perfil)
     *
     * @return bool True si requiere datos de perfil
     */
    public function requiresProfileData(): bool
    {
        return $this->isSharedInterest();
    }

    /**
     * Verifica si esta categoría requiere datos de compatibilidad
     * (SHARED_INTEREST requiere análisis de compatibilidad)
     *
     * @return bool True si requiere datos de compatibilidad
     */
    public function requiresCompatibilityData(): bool
    {
        return $this->isSharedInterest();
    }

    /**
     * Verifica si esta categoría es interactiva
     * (DEEP_QUESTIONS requiere respuesta del usuario)
     *
     * @return bool True si es interactiva
     */
    public function isInteractive(): bool
    {
        return $this->isDeepQuestions();
    }

    /**
     * Verifica si esta categoría es positiva
     * (HUMOR_FUN tiene tono positivo)
     *
     * @return bool True si es positiva
     */
    public function isPositive(): bool
    {
        return $this->isHumorFun();
    }

    /**
     * Obtiene el nivel de prioridad de la categoría
     * (basado en CATEGORY_PRIORITIES del IcebreakerService)
     *
     * @return float Nivel de prioridad (0.0 - 1.0)
     */
    public function getPriorityLevel(): float
    {
        return match ($this->value) {
            self::SHARED_INTEREST => 0.95,
            self::HUMOR_FUN => 0.85,
            self::LIFESTYLE => 0.80,
            self::TRAVEL_ADVENTURE => 0.75,
            self::ENTERTAINMENT => 0.70,
            self::FOOD_DINING => 0.65,
            self::CAREER_AMBITION => 0.60,
            self::DEEP_QUESTIONS => 0.55,
            self::CURRENT_EVENTS => 0.50,
            self::PERSONAL_GROWTH => 0.45,
            default => 0.50,
        };
    }

    /**
     * Obtiene el grupo de la categoría
     *
     * @return string Grupo de la categoría (light, deep, personal, social, professional)
     */
    public function getGroup(): string
    {
        return match ($this->value) {
            self::HUMOR_FUN, self::ENTERTAINMENT, self::FOOD_DINING => 'light',
            self::DEEP_QUESTIONS, self::PERSONAL_GROWTH => 'deep',
            self::SHARED_INTEREST, self::LIFESTYLE => 'personal',
            self::TRAVEL_ADVENTURE, self::CURRENT_EVENTS => 'social',
            self::CAREER_AMBITION => 'professional',
            default => 'general',
        };
    }

    /**
     * Obtiene la descripción legible de la categoría
     *
     * @return string Descripción de la categoría
     */
    public function getDescription(): string
    {
        return match ($this->value) {
            self::SHARED_INTEREST => 'Basado en intereses compartidos',
            self::LIFESTYLE => 'Relacionado con rutinas diarias y hábitos',
            self::TRAVEL_ADVENTURE => 'Enfocado en experiencias de viaje y aventura',
            self::ENTERTAINMENT => 'Películas, música, libros, juegos y cultura pop',
            self::FOOD_DINING => 'Preferencias culinarias, cocina y experiencias gastronómicas',
            self::CAREER_AMBITION => 'Intereses profesionales y aspiraciones de carrera',
            self::HUMOR_FUN => 'Ligero, divertido y juguetón',
            self::DEEP_QUESTIONS => 'Preguntas reflexivas para conversaciones significativas',
            self::CURRENT_EVENTS => 'Discusiones sobre eventos recientes o tendencias',
            self::PERSONAL_GROWTH => 'Automejora, metas y desarrollo personal',
            default => 'Categoría desconocida',
        };
    }

    /**
     * Obtiene las características asociadas a la categoría
     *
     * @return array Características de la categoría
     */
    public function getFeatures(): array
    {
        return match ($this->value) {
            self::SHARED_INTEREST => ['compatibility_focused', 'personalized', 'engaging'],
            self::LIFESTYLE => ['daily_routine', 'habits', 'practical'],
            self::TRAVEL_ADVENTURE => ['adventure', 'exploration', 'experiences'],
            self::ENTERTAINMENT => ['pop_culture', 'leisure', 'fun'],
            self::FOOD_DINING => ['culinary', 'social', 'experiences'],
            self::CAREER_AMBITION => ['professional', 'goals', 'ambition'],
            self::HUMOR_FUN => ['funny', 'lighthearted', 'engaging'],
            self::DEEP_QUESTIONS => ['thoughtful', 'meaningful', 'introspective'],
            self::CURRENT_EVENTS => ['topical', 'relevant', 'discussion'],
            self::PERSONAL_GROWTH => ['self_improvement', 'goals', 'development'],
            default => [],
        };
    }

    /**
     * Verifica si esta categoría tiene una característica específica
     *
     * @param string $feature Característica a verificar
     * @return bool True si tiene la característica
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->getFeatures());
    }

    /**
     * Obtiene el peso de efectividad de la categoría
     * (basado en estadísticas de éxito)
     *
     * @return float Peso de efectividad (0.0 - 1.0)
     */
    public function getEffectivenessWeight(): float
    {
        return $this->getPriorityLevel();
    }

    /**
     * Convierte la IcebreakerCategory a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'description' => $this->getDescription(),
            'group' => $this->getGroup(),
            'priority_level' => $this->getPriorityLevel(),
            'features' => $this->getFeatures(),
            'effectiveness_weight' => $this->getEffectivenessWeight(),
            'is_light' => $this->isLight(),
            'is_deep' => $this->isDeep(),
            'is_personal' => $this->isPersonal(),
            'is_social' => $this->isSocial(),
            'requires_profile_data' => $this->requiresProfileData(),
            'requires_compatibility_data' => $this->requiresCompatibilityData(),
            'is_interactive' => $this->isInteractive(),
            'is_positive' => $this->isPositive(),
        ];
    }

    /**
     * Representación en string de la IcebreakerCategory
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
            'group' => $this->getGroup(),
            'priority_level' => $this->getPriorityLevel(),
            'effectiveness_weight' => $this->getEffectivenessWeight(),
        ];
    }

    /**
     * Valida que el valor de la categoría sea válido
     *
     * @param string $value Valor a validar
     * @throws InvalidArgumentException Si el valor no es válido
     */
    private static function validate(string $value): void
    {
        if (!in_array($value, self::VALID_CATEGORIES, true)) {
            throw new InvalidArgumentException(
                "IcebreakerCategory debe ser uno de los valores válidos: " . implode(', ', self::VALID_CATEGORIES) . 
                ", se recibió: {$value}"
            );
        }
    }

    /**
     * Verifica si un valor es una IcebreakerCategory válida
     *
     * @param mixed $value Valor a verificar
     * @return bool True si es válida
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
     * Crea una IcebreakerCategory desde un valor mixto (string, array)
     *
     * @param mixed $value Valor de la categoría
     * @param string $arrayKey Clave para arrays (por defecto 'category')
     * @return self Nueva instancia de IcebreakerCategory
     * @throws InvalidArgumentException Si el valor no es válido
     */
    public static function from(mixed $value, string $arrayKey = 'category'): self
    {
        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_array($value)) {
            return self::fromArray($value, $arrayKey);
        }

        throw new InvalidArgumentException(
            'IcebreakerCategory solo puede crearse desde string o array, se recibió: ' . gettype($value)
        );
    }

    /**
     * Obtiene todas las categorías válidas
     *
     * @return array Lista de todas las categorías válidas
     */
    public static function getValidCategories(): array
    {
        return self::VALID_CATEGORIES;
    }

    /**
     * Genera un hash único para esta categoría (útil para cache keys)
     *
     * @return string Hash único de la categoría
     */
    public function getHash(): string
    {
        return 'icebreaker_category_' . $this->value . '_' . substr(md5($this->value), 0, 8);
    }

    /**
     * Verifica si esta categoría es compatible con el sistema de analytics
     * (todas las categorías pueden generar analytics)
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si esta categoría es compatible con el sistema de caché
     * (todas las categorías pueden ser cacheadas)
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
