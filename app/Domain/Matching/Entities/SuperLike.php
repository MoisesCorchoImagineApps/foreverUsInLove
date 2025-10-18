<?php

declare(strict_types=1);

namespace App\Domain\Matching\Entities;

use App\Domain\Matching\ValueObjects\LikeId;
use App\Domain\Matching\ValueObjects\LikeSource;
use App\Domain\Matching\ValueObjects\InteractionContext;
use App\Domain\Matching\ValueObjects\MatchId;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;

/**
 * SuperLike Entity
 * 
 * Representa una interacción de super like entre usuarios en la plataforma
 * ForeverUsInLove. Los super likes son likes premium con mayor visibilidad
 * y características especiales.
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class SuperLike
{
    public function __construct(
        private readonly LikeId $id,
        private readonly UserId $likerId,
        private readonly UserId $likedId,
        private readonly LikeSource $source,
        private readonly Carbon $createdAt,
        private readonly ?InteractionContext $context = null,
        private readonly ?string $message = null,
        private bool $isActive = true,
        private ?MatchId $matchId = null,
        private bool $notificationSent = false
    ) {}

    /**
     * Obtener el ID del super like
     */
    public function getId(): LikeId
    {
        return $this->id;
    }

    /**
     * Obtener el ID del usuario que dio el super like
     */
    public function getLikerId(): UserId
    {
        return $this->likerId;
    }

    /**
     * Obtener el ID del usuario que recibió el super like
     */
    public function getLikedId(): UserId
    {
        return $this->likedId;
    }

    /**
     * Obtener la fuente del super like
     */
    public function getSource(): LikeSource
    {
        return $this->source;
    }

    /**
     * Obtener la fecha de creación
     */
    public function getCreatedAt(): Carbon
    {
        return $this->createdAt;
    }

    /**
     * Obtener el contexto de la interacción
     */
    public function getContext(): ?InteractionContext
    {
        return $this->context;
    }

    /**
     * Obtener el mensaje adjunto
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Verificar si el super like está activo
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Desactivar el super like
     */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /**
     * Activar el super like
     */
    public function activate(): void
    {
        $this->isActive = true;
    }

    /**
     * Obtener el ID del match asociado
     */
    public function getMatchId(): ?MatchId
    {
        return $this->matchId;
    }

    /**
     * Establecer el ID del match asociado
     */
    public function setMatchId(MatchId $matchId): void
    {
        $this->matchId = $matchId;
    }

    /**
     * Verificar si la notificación fue enviada
     */
    public function isNotificationSent(): bool
    {
        return $this->notificationSent;
    }

    /**
     * Marcar notificación como enviada
     */
    public function markNotificationSent(): void
    {
        $this->notificationSent = true;
    }

    /**
     * Verificar si tiene mensaje
     */
    public function hasMessage(): bool
    {
        return !empty($this->message);
    }

    /**
     * Verificar si es un super like mutuo
     */
    public function isMutual(): bool
    {
        return $this->matchId !== null;
    }

    /**
     * Obtener la edad del super like en minutos
     */
    public function getAgeInMinutes(): int
    {
        return (int) Carbon::now()->diffInMinutes($this->createdAt);
    }

    /**
     * Verificar si el super like puede ser deshecho
     */
    public function canBeUndone(): bool
    {
        return $this->isActive() && $this->getAgeInMinutes() <= 10;
    }

    /**
     * Obtener el costo en créditos del super like
     */
    public function getCost(): int
    {
        return 1; // Los super likes cuestan 1 crédito
    }

    /**
     * Obtener el multiplicador de visibilidad
     */
    public function getVisibilityMultiplier(): float
    {
        return 2.0; // Los super likes tienen 2x visibilidad
    }
}
