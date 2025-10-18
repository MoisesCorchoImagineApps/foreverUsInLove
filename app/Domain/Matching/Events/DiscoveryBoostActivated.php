<?php

declare(strict_types=1);

namespace App\Domain\Matching\Events;

use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Matching\ValueObjects\DiscoveryBoost;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\CarbonInterface;

/**
 * DiscoveryBoostActivated Event
 * 
 * Evento que se dispara cuando un usuario activa un boost de discovery.
 * Este evento es utilizado para notificar a otros servicios sobre la activación
 * de boosts y para realizar acciones relacionadas como analytics, notificaciones,
 * y actualizaciones de visibilidad del perfil.
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class DiscoveryBoostActivated
{
    use Dispatchable, SerializesModels;

    /**
     * Constructor del evento
     *
     * @param UserId $userId ID del usuario que activó el boost
     * @param DiscoveryBoost $boost Configuración del boost activado
     * @param CarbonInterface $expiresAt Fecha de expiración del boost
     */
    public function __construct(
        public readonly UserId $userId,
        public readonly DiscoveryBoost $boost,
        public readonly CarbonInterface $expiresAt
    ) {}

    /**
     * Obtiene el ID del usuario como entero
     *
     * @return int ID del usuario
     */
    public function getUserIdInt(): int
    {
        return $this->userId->toInt();
    }

    /**
     * Obtiene el tipo de boost activado
     *
     * @return string Tipo de boost
     */
    public function getBoostType(): string
    {
        return $this->boost->getType();
    }

    /**
     * Obtiene el multiplicador del boost
     *
     * @return float Multiplicador del boost
     */
    public function getBoostMultiplier(): float
    {
        return $this->boost->getMultiplier();
    }

    /**
     * Obtiene los modos de discovery objetivo del boost
     *
     * @return array Modos objetivo
     */
    public function getTargetModes(): array
    {
        return $this->boost->getTargetModes();
    }

    /**
     * Verifica si el boost está dirigido a un modo específico
     *
     * @param string $mode Modo a verificar
     * @return bool True si está dirigido a ese modo
     */
    public function targetsMode(string $mode): bool
    {
        return in_array($mode, $this->getTargetModes());
    }

    /**
     * Obtiene la duración del boost en minutos
     *
     * @return int Duración en minutos
     */
    public function getDurationMinutes(): int
    {
        return (int) $this->expiresAt->diffInMinutes(now());
    }

    /**
     * Verifica si el boost aún está activo
     *
     * @return bool True si está activo
     */
    public function isActive(): bool
    {
        return $this->expiresAt->isFuture();
    }

    /**
     * Obtiene los datos del evento para logging
     *
     * @return array Datos del evento
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->getUserIdInt(),
            'boost_type' => $this->getBoostType(),
            'boost_multiplier' => $this->getBoostMultiplier(),
            'target_modes' => $this->getTargetModes(),
            'expires_at' => $this->expiresAt->toISOString(),
            'duration_minutes' => $this->getDurationMinutes(),
            'is_active' => $this->isActive(),
        ];
    }
}
