<?php

declare(strict_types=1);

namespace App\Domain\Matching\Entities;

use App\Domain\Matching\ValueObjects\DiscoveryCardId;
use App\Models\User\Profile;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\CarbonInterface;

/**
 * DiscoveryCard Entity
 * 
 * Representa una tarjeta de descubrimiento en el sistema ForeverUsInLove.
 * Implementa el patrón Entity para encapsular la lógica de negocio
 * relacionada con las tarjetas de descubrimiento de perfiles.
 * 
 * Este Entity es específico del dominio Matching y se utiliza para manejar
 * la presentación y información de perfiles en las sesiones de descubrimiento,
 * incluyendo insights, compatibilidad y datos de matching.
 * 
 * Características:
 * - Encapsula la lógica de negocio de tarjetas de descubrimiento
 * - Maneja información del perfil objetivo
 * - Proporciona insights de compatibilidad
 * - Gestiona datos de matching y scoring
 * - Domain-specific: Específico para el dominio de matching
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class DiscoveryCard
{
    /**
     * Identificador único de la tarjeta
     */
    private readonly DiscoveryCardId $id;

    /**
     * Perfil objetivo de la tarjeta
     */
    private readonly Profile $profile;

    /**
     * Insights de compatibilidad
     */
    private readonly array $insights;

    /**
     * Score de compatibilidad
     */
    private readonly float $compatibilityScore;

    /**
     * Razones de compatibilidad
     */
    private readonly array $compatibilityReasons;

    /**
     * Datos adicionales de la tarjeta
     */
    private readonly array $metadata;

    /**
     * Fecha de creación de la tarjeta
     */
    private readonly CarbonInterface $createdAt;

    /**
     * Constructor de la entidad DiscoveryCard
     */
    public function __construct(
        DiscoveryCardId $id,
        Profile $profile,
        array $insights = [],
        float $compatibilityScore = 0.0,
        array $compatibilityReasons = [],
        array $metadata = [],
        ?CarbonInterface $createdAt = null
    ) {
        $this->id = $id;
        $this->profile = $profile;
        $this->insights = $insights;
        $this->compatibilityScore = $compatibilityScore;
        $this->compatibilityReasons = $compatibilityReasons;
        $this->metadata = $metadata;
        $this->createdAt = $createdAt ?? now();
    }

    /**
     * Obtiene el ID de la tarjeta
     *
     * @return DiscoveryCardId ID de la tarjeta
     */
    public function getId(): DiscoveryCardId
    {
        return $this->id;
    }

    /**
     * Obtiene el perfil de la tarjeta
     *
     * @return Profile Perfil de la tarjeta
     */
    public function getProfile(): Profile
    {
        return $this->profile;
    }

    /**
     * Obtiene los insights de compatibilidad
     *
     * @return array Insights de compatibilidad
     */
    public function getInsights(): array
    {
        return $this->insights;
    }

    /**
     * Obtiene el score de compatibilidad
     *
     * @return float Score de compatibilidad
     */
    public function getCompatibilityScore(): float
    {
        return $this->compatibilityScore;
    }

    /**
     * Obtiene las razones de compatibilidad
     *
     * @return array Razones de compatibilidad
     */
    public function getCompatibilityReasons(): array
    {
        return $this->compatibilityReasons;
    }

    /**
     * Obtiene los datos adicionales
     *
     * @return array Datos adicionales
     */
    public function getMetadata(): array
    {
        return $this->metadata;
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
     * Obtiene el ID del usuario del perfil
     *
     * @return UserId ID del usuario
     */
    public function getUserId(): UserId
    {
        return UserId::fromInt($this->profile->user_id);
    }

    /**
     * Verifica si la tarjeta tiene insights
     *
     * @return bool True si tiene insights
     */
    public function hasInsights(): bool
    {
        return !empty($this->insights);
    }

    /**
     * Verifica si la tarjeta tiene razones de compatibilidad
     *
     * @return bool True si tiene razones
     */
    public function hasCompatibilityReasons(): bool
    {
        return !empty($this->compatibilityReasons);
    }

    /**
     * Obtiene un insight específico
     *
     * @param string $key Clave del insight
     * @param mixed $default Valor por defecto
     * @return mixed Valor del insight
     */
    public function getInsight(string $key, $default = null)
    {
        return $this->insights[$key] ?? $default;
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
     * Verifica si la tarjeta es de alta compatibilidad
     *
     * @return bool True si es de alta compatibilidad
     */
    public function isHighCompatibility(): bool
    {
        return $this->compatibilityScore >= 0.8;
    }

    /**
     * Verifica si la tarjeta es de compatibilidad media
     *
     * @return bool True si es de compatibilidad media
     */
    public function isMediumCompatibility(): bool
    {
        return $this->compatibilityScore >= 0.5 && $this->compatibilityScore < 0.8;
    }

    /**
     * Verifica si la tarjeta es de baja compatibilidad
     *
     * @return bool True si es de baja compatibilidad
     */
    public function isLowCompatibility(): bool
    {
        return $this->compatibilityScore < 0.5;
    }

    /**
     * Obtiene el nivel de compatibilidad como string
     *
     * @return string Nivel de compatibilidad
     */
    public function getCompatibilityLevel(): string
    {
        if ($this->isHighCompatibility()) {
            return 'high';
        }

        if ($this->isMediumCompatibility()) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Obtiene la descripción de compatibilidad
     *
     * @return string Descripción de compatibilidad
     */
    public function getCompatibilityDescription(): string
    {
        return match ($this->getCompatibilityLevel()) {
            'high' => 'Alta compatibilidad',
            'medium' => 'Compatibilidad media',
            'low' => 'Baja compatibilidad',
            default => 'Compatibilidad desconocida',
        };
    }

    /**
     * Obtiene el número de razones de compatibilidad
     *
     * @return int Número de razones
     */
    public function getCompatibilityReasonsCount(): int
    {
        return count($this->compatibilityReasons);
    }

    /**
     * Obtiene el número de insights
     *
     * @return int Número de insights
     */
    public function getInsightsCount(): int
    {
        return count($this->insights);
    }

    /**
     * Convierte la tarjeta a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toArray(),
            'profile' => $this->profile->toArray(),
            'insights' => $this->insights,
            'compatibility_score' => $this->compatibilityScore,
            'compatibility_reasons' => $this->compatibilityReasons,
            'compatibility_level' => $this->getCompatibilityLevel(),
            'compatibility_description' => $this->getCompatibilityDescription(),
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->toISOString(),
            'has_insights' => $this->hasInsights(),
            'has_compatibility_reasons' => $this->hasCompatibilityReasons(),
            'is_high_compatibility' => $this->isHighCompatibility(),
            'is_medium_compatibility' => $this->isMediumCompatibility(),
            'is_low_compatibility' => $this->isLowCompatibility(),
            'insights_count' => $this->getInsightsCount(),
            'compatibility_reasons_count' => $this->getCompatibilityReasonsCount(),
        ];
    }

    /**
     * Representación en string de la tarjeta
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return sprintf(
            'DiscoveryCard(id=%s, user=%s, compatibility=%s)',
            $this->id->toString(),
            $this->profile->getUserId()->toString(),
            $this->getCompatibilityLevel()
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
            'user_id' => $this->profile->getUserId()->toString(),
            'compatibility_score' => $this->compatibilityScore,
            'compatibility_level' => $this->getCompatibilityLevel(),
            'has_insights' => $this->hasInsights(),
            'has_compatibility_reasons' => $this->hasCompatibilityReasons(),
            'insights_count' => $this->getInsightsCount(),
            'compatibility_reasons_count' => $this->getCompatibilityReasonsCount(),
        ];
    }
}
