<?php

declare(strict_types=1);

namespace App\Domain\Matching\Entities;

use App\Domain\Matching\ValueObjects\LikeId;
use App\Domain\Matching\ValueObjects\LikeSource;
use App\Domain\Matching\ValueObjects\InteractionContext;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;

/**
 * Pass Entity
 * 
 * Representa una interacción de pass/dislike entre usuarios en la plataforma
 * ForeverUsInLove. Los passes indican que un usuario no está interesado
 * en otro usuario y no debería aparecer en futuras sesiones de descubrimiento.
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2024
 */
class Pass
{
    public function __construct(
        private readonly LikeId $id,
        private readonly UserId $passerId,
        private readonly UserId $passedId,
        private readonly LikeSource $source,
        private readonly Carbon $createdAt,
        private readonly ?InteractionContext $context = null,
        private readonly ?string $reason = null,
        private bool $isActive = true
    ) {}

    /**
     * Obtener el ID del pass
     */
    public function getId(): LikeId
    {
        return $this->id;
    }

    /**
     * Obtener el ID del usuario que hizo el pass
     */
    public function getPasserId(): UserId
    {
        return $this->passerId;
    }

    /**
     * Obtener el ID del usuario que recibió el pass
     */
    public function getPassedId(): UserId
    {
        return $this->passedId;
    }

    /**
     * Obtener la fuente del pass
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
     * Obtener la razón del pass
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * Verificar si el pass está activo
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Desactivar el pass
     */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /**
     * Activar el pass
     */
    public function activate(): void
    {
        $this->isActive = true;
    }

    /**
     * Verificar si tiene razón especificada
     */
    public function hasReason(): bool
    {
        return !empty($this->reason);
    }

    /**
     * Obtener la edad del pass en minutos
     */
    public function getAgeInMinutes(): int
    {
        return (int) Carbon::now()->diffInMinutes($this->createdAt);
    }

    /**
     * Verificar si el pass puede ser deshecho
     */
    public function canBeUndone(): bool
    {
        return $this->isActive() && $this->getAgeInMinutes() <= 10;
    }

    /**
     * Obtener un resumen del pass
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id->toString(),
            'passer_id' => $this->passerId->toString(),
            'passed_id' => $this->passedId->toString(),
            'source' => $this->source->getValue(),
            'reason' => $this->reason,
            'created_at' => $this->createdAt->toISOString(),
            'is_active' => $this->isActive
        ];
    }
}
