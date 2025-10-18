<?php

declare(strict_types=1);

namespace App\Domain\Matching\ValueObjects;

use InvalidArgumentException;

/**
 * InteractionContext Value Object
 * 
 * Representa el contexto de una interacción entre usuarios en la plataforma
 * ForeverUsInLove. Contiene información adicional sobre cómo y dónde
 * ocurrió la interacción.
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
final class InteractionContext
{
    private readonly array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Obtener la fuente de la interacción
     */
    public function getSource(): ?LikeSource
    {
        $source = $this->data['source'] ?? null;
        return $source ? LikeSource::fromString($source) : null;
    }

    /**
     * Obtener el ID de la sesión
     */
    public function getSessionId(): ?string
    {
        return $this->data['session_id'] ?? null;
    }

    /**
     * Obtener la posición de la tarjeta
     */
    public function getCardPosition(): ?int
    {
        return $this->data['card_position'] ?? null;
    }

    /**
     * Obtener el ID de la tarjeta de descubrimiento
     */
    public function getDiscoveryCardId(): ?string
    {
        return $this->data['discovery_card_id'] ?? null;
    }

    /**
     * Obtener el método de descubrimiento
     */
    public function getDiscoveryMethod(): ?string
    {
        return $this->data['discovery_method'] ?? null;
    }

    /**
     * Obtener el algoritmo de recomendación usado
     */
    public function getRecommendationAlgorithm(): ?string
    {
        return $this->data['recommendation_algorithm'] ?? null;
    }

    /**
     * Obtener la confianza de la recomendación
     */
    public function getRecommendationConfidence(): ?float
    {
        return $this->data['recommendation_confidence'] ?? null;
    }

    /**
     * Obtener el tiempo de interacción
     */
    public function getInteractionTime(): ?int
    {
        return $this->data['interaction_time'] ?? null;
    }

    /**
     * Obtener el dispositivo usado
     */
    public function getDevice(): ?string
    {
        return $this->data['device'] ?? null;
    }

    /**
     * Obtener la plataforma
     */
    public function getPlatform(): ?string
    {
        return $this->data['platform'] ?? null;
    }

    /**
     * Obtener la versión de la app
     */
    public function getAppVersion(): ?string
    {
        return $this->data['app_version'] ?? null;
    }

    /**
     * Obtener la ubicación geográfica
     */
    public function getLocation(): ?array
    {
        return $this->data['location'] ?? null;
    }

    /**
     * Obtener datos adicionales personalizados
     */
    public function getCustomData(): array
    {
        return $this->data['custom'] ?? [];
    }

    /**
     * Obtener un valor específico por clave
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Verificar si existe una clave
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Convertir a array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Crear contexto desde array
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Crear contexto para tarjeta de descubrimiento
     */
    public static function forDiscoveryCard(
        string $sessionId,
        int $cardPosition,
        ?string $cardId = null,
        ?string $method = null
    ): self {
        return new self([
            'source' => LikeSource::DISCOVERY_CARD,
            'session_id' => $sessionId,
            'card_position' => $cardPosition,
            'discovery_card_id' => $cardId,
            'discovery_method' => $method,
        ]);
    }

    /**
     * Crear contexto para vista de perfil
     */
    public static function forProfileView(
        ?string $sessionId = null,
        ?string $device = null,
        ?string $platform = null
    ): self {
        return new self([
            'source' => LikeSource::PROFILE_VIEW,
            'session_id' => $sessionId,
            'device' => $device,
            'platform' => $platform,
        ]);
    }

    /**
     * Crear contexto para recomendaciones
     */
    public static function forRecommendation(
        string $algorithm,
        float $confidence,
        ?string $sessionId = null
    ): self {
        return new self([
            'source' => LikeSource::RECOMMENDATION,
            'recommendation_algorithm' => $algorithm,
            'recommendation_confidence' => $confidence,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Crear contexto para búsqueda
     */
    public static function forSearch(
        ?string $query = null,
        ?array $filters = null,
        ?string $sessionId = null
    ): self {
        return new self([
            'source' => LikeSource::SEARCH,
            'search_query' => $query,
            'search_filters' => $filters,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Crear contexto manual
     */
    public static function forManual(
        ?string $device = null,
        ?string $platform = null
    ): self {
        return new self([
            'source' => LikeSource::MANUAL,
            'device' => $device,
            'platform' => $platform,
        ]);
    }

    /**
     * Agregar datos personalizados
     */
    public function withCustomData(array $customData): self
    {
        $newData = $this->data;
        $newData['custom'] = array_merge($newData['custom'] ?? [], $customData);
        return new self($newData);
    }

    /**
     * Agregar ubicación
     */
    public function withLocation(array $location): self
    {
        $newData = $this->data;
        $newData['location'] = $location;
        return new self($newData);
    }

    /**
     * Agregar información de dispositivo
     */
    public function withDeviceInfo(string $device, string $platform, ?string $appVersion = null): self
    {
        $newData = $this->data;
        $newData['device'] = $device;
        $newData['platform'] = $platform;
        if ($appVersion) {
            $newData['app_version'] = $appVersion;
        }
        return new self($newData);
    }

    /**
     * Verificar si el contexto es válido
     */
    public function isValid(): bool
    {
        try {
            $source = $this->getSource();
            return $source !== null;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Obtener un resumen del contexto
     */
    public function getSummary(): array
    {
        return [
            'source' => $this->getSource()?->getValue(),
            'session_id' => $this->getSessionId(),
            'card_position' => $this->getCardPosition(),
            'device' => $this->getDevice(),
            'platform' => $this->getPlatform(),
            'has_location' => $this->getLocation() !== null,
            'has_custom_data' => !empty($this->getCustomData()),
        ];
    }
}
