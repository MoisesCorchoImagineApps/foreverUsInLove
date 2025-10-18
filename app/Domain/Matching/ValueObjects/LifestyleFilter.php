<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * LifestyleFilter Value Object
 * 
 * Representa un filtro de estilo de vida en el sistema ForeverUsInLove.
 * Implementa el patrón Value Object para garantizar la inmutabilidad y validación
 * de filtros de estilo de vida en toda la aplicación.
 * 
 * Este Value Object es específico del dominio Matching y se utiliza para manejar
 * filtros basados en hábitos de vida, preferencias personales y comportamientos
 * en búsquedas y matching.
 * 
 * Características:
 * - Inmutable: Una vez creado, no puede ser modificado
 * - Validado: Solo acepta configuraciones de estilo de vida válidas
 * - Type-safe: Previene errores de tipo en tiempo de compilación
 * - Serializable: Compatible con JSON y almacenamiento en caché
 * - Comparación por valor: Dos LifestyleFilter con los mismos valores son iguales
 * - Matching-specific: Específico para el dominio de matching y filtros de estilo de vida
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class LifestyleFilter implements JsonSerializable
{
    /**
     * Preferencias de fumar
     */
    private readonly ?string $smokingPreference;

    /**
     * Preferencias de beber
     */
    private readonly ?string $drinkingPreference;

    /**
     * Preferencias de ejercicio
     */
    private readonly ?string $exercisePreference;

    /**
     * Preferencias de dieta
     */
    private readonly ?string $dietPreference;

    /**
     * Preferencias de trabajo
     */
    private readonly ?string $workPreference;

    /**
     * Preferencias de horarios
     */
    private readonly ?string $schedulePreference;

    /**
     * Preferencias de mascotas
     */
    private readonly ?string $petPreference;

    /**
     * Preferencias de viajes
     */
    private readonly ?string $travelPreference;

    /**
     * Constructor privado para forzar el uso del factory method
     */
    private function __construct(
        ?string $smokingPreference = null,
        ?string $drinkingPreference = null,
        ?string $exercisePreference = null,
        ?string $dietPreference = null,
        ?string $workPreference = null,
        ?string $schedulePreference = null,
        ?string $petPreference = null,
        ?string $travelPreference = null
    ) {
        $this->smokingPreference = $smokingPreference;
        $this->drinkingPreference = $drinkingPreference;
        $this->exercisePreference = $exercisePreference;
        $this->dietPreference = $dietPreference;
        $this->workPreference = $workPreference;
        $this->schedulePreference = $schedulePreference;
        $this->petPreference = $petPreference;
        $this->travelPreference = $travelPreference;
    }

    /**
     * Factory method para crear una nueva instancia de LifestyleFilter
     *
     * @param array $preferences Array con preferencias de estilo de vida
     * @return self Nueva instancia de LifestyleFilter
     * @throws InvalidArgumentException Si las preferencias no son válidas
     */
    public static function create(array $preferences = []): self
    {
        self::validatePreferences($preferences);
        
        return new self(
            $preferences['smoking'] ?? $preferences['smoking_preference'] ?? null,
            $preferences['drinking'] ?? $preferences['drinking_preference'] ?? null,
            $preferences['exercise'] ?? $preferences['exercise_preference'] ?? null,
            $preferences['diet'] ?? $preferences['diet_preference'] ?? null,
            $preferences['work'] ?? $preferences['work_preference'] ?? null,
            $preferences['schedule'] ?? $preferences['schedule_preference'] ?? null,
            $preferences['pets'] ?? $preferences['pet_preference'] ?? null,
            $preferences['travel'] ?? $preferences['travel_preference'] ?? null
        );
    }

    /**
     * Factory method para crear LifestyleFilter desde array
     *
     * @param array $filter Array con configuración del filtro
     * @return self Nueva instancia de LifestyleFilter
     * @throws InvalidArgumentException Si el array no es válido
     */
    public static function fromArray(array $filter): self
    {
        return self::create($filter);
    }

    /**
     * Factory methods para estilos de vida predefinidos comunes
     */
    public static function healthConscious(): self
    {
        return new self(
            'never',      // No fuma
            'occasionally', // Bebe ocasionalmente
            'regularly',   // Ejercicio regular
            'balanced',    // Dieta balanceada
            'balanced',    // Trabajo balanceado
            'flexible',    // Horarios flexibles
            'love',        // Ama las mascotas
            'frequently'   // Viaja frecuentemente
        );
    }

    public static function active(): self
    {
        return new self(
            'never',       // No fuma
            'moderately',  // Bebe moderadamente
            'daily',       // Ejercicio diario
            'healthy',     // Dieta saludable
            'flexible',    // Trabajo flexible
            'early_bird',  // Madrugador
            'like',        // Le gustan las mascotas
            'regularly'    // Viaja regularmente
        );
    }

    public static function relaxed(): self
    {
        return new self(
            'occasionally', // Fuma ocasionalmente
            'regularly',    // Bebe regularmente
            'occasionally', // Ejercicio ocasional
            'flexible',     // Dieta flexible
            'traditional',  // Trabajo tradicional
            'night_owl',    // Noctámbulo
            'neutral',      // Neutral con mascotas
            'occasionally'  // Viaja ocasionalmente
        );
    }

    public static function professional(): self
    {
        return new self(
            'never',        // No fuma
            'occasionally', // Bebe ocasionalmente
            'regularly',    // Ejercicio regular
            'balanced',     // Dieta balanceada
            'intensive',    // Trabajo intensivo
            'structured',   // Horarios estructurados
            'prefer_not',   // Prefiere no tener mascotas
            'business'      // Viajes de negocios
        );
    }

    public static function open(): self
    {
        return new self(); // Sin preferencias específicas
    }

    /**
     * Obtiene la preferencia de fumar
     *
     * @return string|null Preferencia de fumar
     */
    public function getSmokingPreference(): ?string
    {
        return $this->smokingPreference;
    }

    /**
     * Obtiene la preferencia de beber
     *
     * @return string|null Preferencia de beber
     */
    public function getDrinkingPreference(): ?string
    {
        return $this->drinkingPreference;
    }

    /**
     * Obtiene la preferencia de ejercicio
     *
     * @return string|null Preferencia de ejercicio
     */
    public function getExercisePreference(): ?string
    {
        return $this->exercisePreference;
    }

    /**
     * Obtiene la preferencia de dieta
     *
     * @return string|null Preferencia de dieta
     */
    public function getDietPreference(): ?string
    {
        return $this->dietPreference;
    }

    /**
     * Obtiene la preferencia de trabajo
     *
     * @return string|null Preferencia de trabajo
     */
    public function getWorkPreference(): ?string
    {
        return $this->workPreference;
    }

    /**
     * Obtiene la preferencia de horarios
     *
     * @return string|null Preferencia de horarios
     */
    public function getSchedulePreference(): ?string
    {
        return $this->schedulePreference;
    }

    /**
     * Obtiene la preferencia de mascotas
     *
     * @return string|null Preferencia de mascotas
     */
    public function getPetPreference(): ?string
    {
        return $this->petPreference;
    }

    /**
     * Obtiene la preferencia de viajes
     *
     * @return string|null Preferencia de viajes
     */
    public function getTravelPreference(): ?string
    {
        return $this->travelPreference;
    }

    /**
     * Verifica si el filtro está vacío (sin preferencias)
     *
     * @return bool True si está vacío
     */
    public function isEmpty(): bool
    {
        return $this->smokingPreference === null &&
               $this->drinkingPreference === null &&
               $this->exercisePreference === null &&
               $this->dietPreference === null &&
               $this->workPreference === null &&
               $this->schedulePreference === null &&
               $this->petPreference === null &&
               $this->travelPreference === null;
    }

    /**
     * Obtiene el número total de preferencias definidas
     *
     * @return int Número de preferencias definidas
     */
    public function getPreferenceCount(): int
    {
        $count = 0;
        $preferences = [
            $this->smokingPreference,
            $this->drinkingPreference,
            $this->exercisePreference,
            $this->dietPreference,
            $this->workPreference,
            $this->schedulePreference,
            $this->petPreference,
            $this->travelPreference
        ];

        foreach ($preferences as $preference) {
            if ($preference !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Verifica si el filtro es saludable
     *
     * @return bool True si promueve un estilo de vida saludable
     */
    public function isHealthFocused(): bool
    {
        return $this->smokingPreference === 'never' &&
               ($this->drinkingPreference === 'occasionally' || $this->drinkingPreference === 'never') &&
               ($this->exercisePreference === 'regularly' || $this->exercisePreference === 'daily') &&
               ($this->dietPreference === 'healthy' || $this->dietPreference === 'balanced');
    }

    /**
     * Verifica si el filtro es activo
     *
     * @return bool True si promueve un estilo de vida activo
     */
    public function isActive(): bool
    {
        return $this->exercisePreference === 'daily' || $this->exercisePreference === 'regularly';
    }

    /**
     * Verifica si el filtro es profesional
     *
     * @return bool True si promueve un estilo de vida profesional
     */
    public function isProfessional(): bool
    {
        return $this->workPreference === 'intensive' || $this->workPreference === 'traditional' &&
               $this->schedulePreference === 'structured' || $this->schedulePreference === 'early_bird';
    }

    /**
     * Verifica si el filtro es relajado
     *
     * @return bool True si promueve un estilo de vida relajado
     */
    public function isRelaxed(): bool
    {
        return ($this->workPreference === 'flexible' || $this->workPreference === 'balanced') &&
               ($this->schedulePreference === 'flexible' || $this->schedulePreference === 'night_owl');
    }

    /**
     * Obtiene la categoría del filtro
     *
     * @return string Categoría (health_focused, active, professional, relaxed, balanced, open)
     */
    public function getCategory(): string
    {
        if ($this->isEmpty()) {
            return 'open';
        }

        if ($this->isHealthFocused()) {
            return 'health_focused';
        }

        if ($this->isActive()) {
            return 'active';
        }

        if ($this->isProfessional()) {
            return 'professional';
        }

        if ($this->isRelaxed()) {
            return 'relaxed';
        }

        return 'balanced';
    }

    /**
     * Obtiene el nivel de selectividad del filtro
     * (1 = muy selectivo, 5 = muy permisivo)
     *
     * @return int Nivel de selectividad
     */
    public function getSelectivityLevel(): int
    {
        if ($this->isEmpty()) {
            return 5; // Muy permisivo
        }

        $count = $this->getPreferenceCount();
        
        if ($count >= 7) {
            return 1; // Muy selectivo
        } elseif ($count >= 5) {
            return 2; // Selectivo
        } elseif ($count >= 3) {
            return 3; // Balanceado
        } elseif ($count >= 1) {
            return 4; // Permisivo
        }

        return 5; // Muy permisivo
    }

    /**
     * Verifica si un perfil de estilo de vida cumple con este filtro
     *
     * @param array $candidateLifestyle Estilo de vida del candidato
     * @return bool True si cumple con el filtro
     */
    public function matches(array $candidateLifestyle): bool
    {
        $preferences = [
            'smoking' => $this->smokingPreference,
            'drinking' => $this->drinkingPreference,
            'exercise' => $this->exercisePreference,
            'diet' => $this->dietPreference,
            'work' => $this->workPreference,
            'schedule' => $this->schedulePreference,
            'pets' => $this->petPreference,
            'travel' => $this->travelPreference
        ];

        foreach ($preferences as $key => $preference) {
            if ($preference !== null && isset($candidateLifestyle[$key])) {
                if (!$this->isCompatible($preference, $candidateLifestyle[$key])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Verifica si dos preferencias de estilo de vida son compatibles
     *
     * @param string $filterPreference Preferencia del filtro
     * @param string $candidatePreference Preferencia del candidato
     * @return bool True si son compatibles
     */
    private function isCompatible(string $filterPreference, string $candidatePreference): bool
    {
        // Matriz de compatibilidad básica
        $compatibility = [
            'smoking' => [
                'never' => ['never'],
                'occasionally' => ['never', 'occasionally'],
                'regularly' => ['occasionally', 'regularly'],
                'heavy' => ['regularly', 'heavy']
            ],
            'drinking' => [
                'never' => ['never', 'occasionally'],
                'occasionally' => ['never', 'occasionally', 'moderately'],
                'moderately' => ['occasionally', 'moderately', 'regularly'],
                'regularly' => ['moderately', 'regularly'],
                'heavy' => ['regularly', 'heavy']
            ],
            'exercise' => [
                'never' => ['never', 'occasionally'],
                'occasionally' => ['never', 'occasionally', 'regularly'],
                'regularly' => ['occasionally', 'regularly', 'daily'],
                'daily' => ['regularly', 'daily']
            ],
            'diet' => [
                'flexible' => ['flexible', 'balanced', 'healthy', 'vegetarian', 'vegan'],
                'balanced' => ['flexible', 'balanced', 'healthy'],
                'healthy' => ['balanced', 'healthy', 'vegetarian', 'vegan'],
                'vegetarian' => ['vegetarian', 'vegan'],
                'vegan' => ['vegan']
            ],
            'work' => [
                'flexible' => ['flexible', 'balanced', 'traditional', 'intensive'],
                'balanced' => ['flexible', 'balanced', 'traditional'],
                'traditional' => ['flexible', 'balanced', 'traditional', 'intensive'],
                'intensive' => ['traditional', 'intensive']
            ],
            'schedule' => [
                'flexible' => ['flexible', 'early_bird', 'night_owl', 'structured'],
                'early_bird' => ['early_bird', 'structured', 'flexible'],
                'night_owl' => ['night_owl', 'flexible'],
                'structured' => ['early_bird', 'structured', 'flexible']
            ],
            'pets' => [
                'love' => ['love', 'like', 'neutral'],
                'like' => ['love', 'like', 'neutral'],
                'neutral' => ['love', 'like', 'neutral', 'prefer_not'],
                'prefer_not' => ['neutral', 'prefer_not', 'allergic']
            ],
            'travel' => [
                'never' => ['never', 'occasionally'],
                'occasionally' => ['never', 'occasionally', 'regularly'],
                'regularly' => ['occasionally', 'regularly', 'frequently'],
                'frequently' => ['regularly', 'frequently'],
                'business' => ['business', 'frequently', 'regularly']
            ]
        ];

        // Determinar el tipo de preferencia basado en los valores comunes
        $category = $this->getPreferenceCategory($filterPreference, $candidatePreference);
        
        if ($category && isset($compatibility[$category])) {
            return isset($compatibility[$category][$filterPreference]) &&
                   in_array($candidatePreference, $compatibility[$category][$filterPreference]);
        }

        // Si no hay matriz específica, usar comparación exacta
        return $filterPreference === $candidatePreference;
    }

    /**
     * Determina la categoría de preferencia basada en los valores
     *
     * @param string $filterValue Valor del filtro
     * @param string $candidateValue Valor del candidato
     * @return string|null Categoría de preferencia
     */
    private function getPreferenceCategory(string $filterValue, string $candidateValue): ?string
    {
        $categories = [
            'smoking' => ['never', 'occasionally', 'regularly', 'heavy'],
            'drinking' => ['never', 'occasionally', 'moderately', 'regularly', 'heavy'],
            'exercise' => ['never', 'occasionally', 'regularly', 'daily'],
            'diet' => ['flexible', 'balanced', 'healthy', 'vegetarian', 'vegan'],
            'work' => ['flexible', 'balanced', 'traditional', 'intensive'],
            'schedule' => ['flexible', 'early_bird', 'night_owl', 'structured'],
            'pets' => ['love', 'like', 'neutral', 'prefer_not', 'allergic'],
            'travel' => ['never', 'occasionally', 'regularly', 'frequently', 'business']
        ];

        foreach ($categories as $category => $values) {
            if (in_array($filterValue, $values) || in_array($candidateValue, $values)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Verifica si este filtro es igual a otro
     *
     * @param self $other Otro filtro para comparar
     * @return bool True si son iguales
     */
    public function equals(self $other): bool
    {
        return $this->smokingPreference === $other->smokingPreference &&
               $this->drinkingPreference === $other->drinkingPreference &&
               $this->exercisePreference === $other->exercisePreference &&
               $this->dietPreference === $other->dietPreference &&
               $this->workPreference === $other->workPreference &&
               $this->schedulePreference === $other->schedulePreference &&
               $this->petPreference === $other->petPreference &&
               $this->travelPreference === $other->travelPreference;
    }

    /**
     * Obtiene la descripción legible del filtro
     *
     * @return string Descripción del filtro
     */
    public function getDescription(): string
    {
        if ($this->isEmpty()) {
            return 'Sin preferencias de estilo de vida específicas';
        }

        $descriptions = [];

        if ($this->smokingPreference) {
            $descriptions[] = "Fumar: {$this->smokingPreference}";
        }

        if ($this->drinkingPreference) {
            $descriptions[] = "Beber: {$this->drinkingPreference}";
        }

        if ($this->exercisePreference) {
            $descriptions[] = "Ejercicio: {$this->exercisePreference}";
        }

        if ($this->dietPreference) {
            $descriptions[] = "Dieta: {$this->dietPreference}";
        }

        if ($this->workPreference) {
            $descriptions[] = "Trabajo: {$this->workPreference}";
        }

        if ($this->schedulePreference) {
            $descriptions[] = "Horarios: {$this->schedulePreference}";
        }

        if ($this->petPreference) {
            $descriptions[] = "Mascotas: {$this->petPreference}";
        }

        if ($this->travelPreference) {
            $descriptions[] = "Viajes: {$this->travelPreference}";
        }

        return implode(', ', $descriptions);
    }

    /**
     * Convierte el filtro a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'smoking_preference' => $this->smokingPreference,
            'drinking_preference' => $this->drinkingPreference,
            'exercise_preference' => $this->exercisePreference,
            'diet_preference' => $this->dietPreference,
            'work_preference' => $this->workPreference,
            'schedule_preference' => $this->schedulePreference,
            'pet_preference' => $this->petPreference,
            'travel_preference' => $this->travelPreference,
            'category' => $this->getCategory(),
            'selectivity_level' => $this->getSelectivityLevel(),
            'preference_count' => $this->getPreferenceCount(),
            'description' => $this->getDescription(),
            'is_health_focused' => $this->isHealthFocused(),
            'is_active' => $this->isActive(),
            'is_professional' => $this->isProfessional(),
            'is_relaxed' => $this->isRelaxed(),
            'is_empty' => $this->isEmpty(),
        ];
    }

    /**
     * Representación en string del filtro
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return $this->getDescription();
    }

    /**
     * Serialización para JSON
     *
     * @return array Valor para JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'smoking' => $this->smokingPreference,
            'drinking' => $this->drinkingPreference,
            'exercise' => $this->exercisePreference,
            'diet' => $this->dietPreference,
            'work' => $this->workPreference,
            'schedule' => $this->schedulePreference,
            'pets' => $this->petPreference,
            'travel' => $this->travelPreference
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
            'preference_count' => $this->getPreferenceCount(),
            'category' => $this->getCategory(),
            'selectivity_level' => $this->getSelectivityLevel(),
            'is_health_focused' => $this->isHealthFocused(),
            'is_active' => $this->isActive(),
            'is_professional' => $this->isProfessional(),
            'is_relaxed' => $this->isRelaxed(),
        ];
    }

    /**
     * Valida que las preferencias sean válidas
     *
     * @param array $preferences Preferencias a validar
     * @throws InvalidArgumentException Si las preferencias no son válidas
     */
    private static function validatePreferences(array $preferences): void
    {
        $validValues = [
            'smoking' => ['never', 'occasionally', 'regularly', 'heavy'],
            'drinking' => ['never', 'occasionally', 'moderately', 'regularly', 'heavy'],
            'exercise' => ['never', 'occasionally', 'regularly', 'daily'],
            'diet' => ['flexible', 'balanced', 'healthy', 'vegetarian', 'vegan'],
            'work' => ['flexible', 'balanced', 'traditional', 'intensive'],
            'schedule' => ['flexible', 'early_bird', 'night_owl', 'structured'],
            'pets' => ['love', 'like', 'neutral', 'prefer_not', 'allergic'],
            'travel' => ['never', 'occasionally', 'regularly', 'frequently', 'business']
        ];

        foreach ($preferences as $key => $value) {
            if ($value !== null) {
                // Normalizar clave
                $normalizedKey = str_replace(['_preference', '_'], ['', '_'], $key);
                
                if (isset($validValues[$normalizedKey])) {
                    if (!in_array($value, $validValues[$normalizedKey])) {
                        throw new InvalidArgumentException(
                            "Valor inválido para {$key}: {$value}. Valores válidos: " . implode(', ', $validValues[$normalizedKey])
                        );
                    }
                }
            }
        }
    }

    /**
     * Verifica si un valor es un LifestyleFilter válido
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
     * Crea LifestyleFilter desde un valor mixto
     *
     * @param mixed $value Valor del filtro de estilo de vida
     * @return self Nueva instancia de LifestyleFilter
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
            'LifestyleFilter solo puede crearse desde array o instancia de LifestyleFilter, se recibió: ' . gettype($value)
        );
    }

    /**
     * Genera un hash único para este filtro (útil para cache keys)
     *
     * @return string Hash único del filtro
     */
    public function getHash(): string
    {
        $data = [
            'smoking' => $this->smokingPreference,
            'drinking' => $this->drinkingPreference,
            'exercise' => $this->exercisePreference,
            'diet' => $this->dietPreference,
            'work' => $this->workPreference,
            'schedule' => $this->schedulePreference,
            'pets' => $this->petPreference,
            'travel' => $this->travelPreference
        ];

        return 'lifestyle_filter_' . substr(md5(serialize($data)), 0, 12);
    }

    /**
     * Verifica si este filtro es compatible con el sistema de analytics
     *
     * @return bool True si es compatible con analytics
     */
    public function isAnalyticsCompatible(): bool
    {
        return true;
    }

    /**
     * Verifica si este filtro es compatible con el sistema de caché
     *
     * @return bool True si es compatible con caché
     */
    public function isCacheCompatible(): bool
    {
        return true;
    }
}
