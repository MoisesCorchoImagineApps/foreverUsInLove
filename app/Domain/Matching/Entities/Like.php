<?php

declare(strict_types=1);

namespace App\Domain\Matching\Entities;

use App\Domain\Matching\ValueObjects\LikeId;
use App\Domain\Matching\ValueObjects\LikeType;
use App\Domain\Matching\ValueObjects\LikeSource;
use App\Domain\Matching\ValueObjects\InteractionContext;
use App\Domain\Matching\ValueObjects\MatchId;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;

/**
 * Like Entity
 * 
 * Representa una interacción de like entre usuarios en la plataforma
 * ForeverUsInLove. Implementa el patrón Entity siguiendo principios
 * de Domain-Driven Design.
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class Like
{
    public function __construct(
        private readonly LikeId $id,
        private readonly UserId $likerId,
        private readonly UserId $likedId,
        private readonly LikeType $type,
        private readonly LikeSource $source,
        private readonly Carbon $createdAt,
        private readonly ?InteractionContext $context = null,
        private readonly ?string $message = null,
        private bool $isActive = true,
        private ?MatchId $matchId = null
    ) {}

    /**
     * Obtener el ID del like
     */
    public function getId(): LikeId
    {
        return $this->id;
    }

    /**
     * Obtener el ID del usuario que dio el like
     */
    public function getLikerId(): UserId
    {
        return $this->likerId;
    }

    /**
     * Obtener el ID del usuario que recibió el like
     */
    public function getLikedId(): UserId
    {
        return $this->likedId;
    }

    /**
     * Obtener el tipo de like
     */
    public function getType(): LikeType
    {
        return $this->type;
    }

    /**
     * Obtener la fuente del like
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
     * Verificar si el like está activo
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Desactivar el like
     */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /**
     * Activar el like
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
     * Verificar si tiene mensaje
     */
    public function hasMessage(): bool
    {
        return !empty($this->message);
    }

    /**
     * Verificar si es un like mutuo
     */
    public function isMutual(): bool
    {
        return $this->matchId !== null;
    }

    /**
     * Verificar si es un like premium
     */
    public function isPremium(): bool
    {
        return $this->type->isPremium();
    }

    /**
     * Obtener la edad del like en minutos
     */
    public function getAgeInMinutes(): int
    {
        return (int) Carbon::now()->diffInMinutes($this->createdAt);
    }

    /**
     * Verificar si el like puede ser deshecho
     */
    public function canBeUndone(): bool
    {
        return $this->isActive() && $this->getAgeInMinutes() <= 10;
    }
}
