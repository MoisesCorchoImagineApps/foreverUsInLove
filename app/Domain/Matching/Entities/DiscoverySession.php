<?php

declare(strict_types=1);

namespace App\Domain\Matching\Entities;

use App\Domain\Matching\ValueObjects\DiscoverySessionId;
use App\Domain\Matching\ValueObjects\DiscoveryMode;
use App\Domain\Matching\ValueObjects\DiscoveryPreferences;
use App\Domain\Matching\ValueObjects\DiscoveryCardId;
use App\Domain\Shared\ValueObjects\UserId;
use Illuminate\Support\Collection;
use Carbon\CarbonInterface;

/**
 * DiscoverySession Entity
 * 
 * Representa una sesión de descubrimiento en el sistema ForeverUsInLove.
 * Implementa el patrón Entity para encapsular la lógica de negocio
 * relacionada con las sesiones de descubrimiento de perfiles.
 * 
 * Este Entity es específico del dominio Matching y se utiliza para manejar
 * el estado y comportamiento de las sesiones de descubrimiento, incluyendo
 * la gestión de tarjetas, progreso y expiración.
 * 
 * Características:
 * - Encapsula la lógica de negocio de sesiones de descubrimiento
 * - Maneja el estado de progreso y expiración
 * - Gestiona la colección de tarjetas de descubrimiento
 * - Proporciona métodos para interacción con tarjetas
 * - Tracking de progreso y analytics
 * - Domain-specific: Específico para el dominio de matching
 * 
 * @author ForeverUsInLove Team
 * @version Laravel 12.x
 * @since 2025
 */
final class DiscoverySession
{
    /**
     * Identificador único de la sesión
     */
    private readonly DiscoverySessionId $sessionId;

    /**
     * Usuario propietario de la sesión
     */
    private readonly UserId $userId;

    /**
     * Modo de descubrimiento
     */
    private readonly DiscoveryMode $mode;

    /**
     * Preferencias de descubrimiento
     */
    private readonly DiscoveryPreferences $preferences;

    /**
     * Colección de tarjetas de descubrimiento
     */
    private Collection $cards;

    /**
     * Tarjetas ya vistas
     */
    private Collection $viewedCards;

    /**
     * Fecha de inicio de la sesión
     */
    private readonly CarbonInterface $startedAt;

    /**
     * Fecha de expiración de la sesión
     */
    private readonly CarbonInterface $expiresAt;

    /**
     * Si la sesión es premium
     */
    private readonly bool $isPremium;

    /**
     * Número máximo de tarjetas
     */
    private readonly int $maxCards;

    /**
     * Constructor de la entidad DiscoverySession
     */
    public function __construct(
        DiscoverySessionId $sessionId,
        UserId $userId,
        DiscoveryMode $mode,
        DiscoveryPreferences $preferences,
        Collection $cards,
        CarbonInterface $startedAt,
        CarbonInterface $expiresAt,
        bool $isPremium,
        int $maxCards
    ) {
        $this->sessionId = $sessionId;
        $this->userId = $userId;
        $this->mode = $mode;
        $this->preferences = $preferences;
        $this->cards = $cards;
        $this->viewedCards = new Collection();
        $this->startedAt = $startedAt;
        $this->expiresAt = $expiresAt;
        $this->isPremium = $isPremium;
        $this->maxCards = $maxCards;
    }

    /**
     * Obtiene el ID de la sesión
     *
     * @return DiscoverySessionId ID de la sesión
     */
    public function getId(): DiscoverySessionId
    {
        return $this->sessionId;
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
     * Obtiene el modo de descubrimiento
     *
     * @return DiscoveryMode Modo de descubrimiento
     */
    public function getMode(): DiscoveryMode
    {
        return $this->mode;
    }

    /**
     * Obtiene las preferencias de descubrimiento
     *
     * @return DiscoveryPreferences Preferencias de descubrimiento
     */
    public function getPreferences(): DiscoveryPreferences
    {
        return $this->preferences;
    }

    /**
     * Obtiene las tarjetas de descubrimiento
     *
     * @return Collection Tarjetas de descubrimiento
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    /**
     * Obtiene las tarjetas ya vistas
     *
     * @return Collection Tarjetas ya vistas
     */
    public function getViewedCards(): Collection
    {
        return $this->viewedCards;
    }

    /**
     * Obtiene la fecha de inicio
     *
     * @return CarbonInterface Fecha de inicio
     */
    public function getStartedAt(): CarbonInterface
    {
        return $this->startedAt;
    }

    /**
     * Obtiene la fecha de expiración
     *
     * @return CarbonInterface Fecha de expiración
     */
    public function getExpiresAt(): CarbonInterface
    {
        return $this->expiresAt;
    }

    /**
     * Verifica si la sesión es premium
     *
     * @return bool True si es premium
     */
    public function isPremium(): bool
    {
        return $this->isPremium;
    }

    /**
     * Obtiene el número máximo de tarjetas
     *
     * @return int Número máximo de tarjetas
     */
    public function getMaxCards(): int
    {
        return $this->maxCards;
    }

    /**
     * Verifica si la sesión ha expirado
     *
     * @return bool True si ha expirado
     */
    public function isExpired(): bool
    {
        return now()->isAfter($this->expiresAt);
    }

    /**
     * Verifica si la sesión está activa
     *
     * @return bool True si está activa
     */
    public function isActive(): bool
    {
        return !$this->isExpired() && $this->hasRemainingCards();
    }

    /**
     * Verifica si hay tarjetas restantes
     *
     * @return bool True si hay tarjetas restantes
     */
    public function hasRemainingCards(): bool
    {
        return $this->getRemainingCardsCount() > 0;
    }

    /**
     * Obtiene el número de tarjetas restantes
     *
     * @return int Número de tarjetas restantes
     */
    public function getRemainingCardsCount(): int
    {
        return $this->cards->count() - $this->viewedCards->count();
    }

    /**
     * Obtiene el número de tarjetas vistas
     *
     * @return int Número de tarjetas vistas
     */
    public function getViewedCardsCount(): int
    {
        return $this->viewedCards->count();
    }

    /**
     * Obtiene el progreso de la sesión (porcentaje)
     *
     * @return float Progreso de la sesión (0-100)
     */
    public function getProgress(): float
    {
        if ($this->cards->isEmpty()) {
            return 0.0;
        }

        return round(($this->getViewedCardsCount() / $this->cards->count()) * 100, 2);
    }

    /**
     * Obtiene la siguiente tarjeta no vista
     *
     * @return DiscoveryCard|null Siguiente tarjeta o null si no hay más
     */
    public function getNextCard(): ?DiscoveryCard
    {
        if (!$this->isActive()) {
            return null;
        }

        $unviewedCards = $this->cards->reject(function (DiscoveryCard $card) {
            return $this->viewedCards->contains($card->getId());
        });

        return $unviewedCards->first();
    }

    /**
     * Marca una tarjeta como vista
     *
     * @param DiscoveryCardId $cardId ID de la tarjeta vista
     * @return void
     */
    public function markCardViewed(DiscoveryCardId $cardId): void
    {
        if (!$this->viewedCards->contains($cardId)) {
            $this->viewedCards->push($cardId);
        }
    }

    /**
     * Verifica si una tarjeta ha sido vista
     *
     * @param DiscoveryCardId $cardId ID de la tarjeta
     * @return bool True si ha sido vista
     */
    public function isCardViewed(DiscoveryCardId $cardId): bool
    {
        return $this->viewedCards->contains($cardId);
    }

    /**
     * Obtiene la duración de la sesión en minutos
     *
     * @return int Duración en minutos
     */
    public function getDurationMinutes(): int
    {
        return (int) $this->startedAt->diffInMinutes(now());
    }

    /**
     * Obtiene el tiempo restante en minutos
     *
     * @return int Tiempo restante en minutos
     */
    public function getRemainingTimeMinutes(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return max(0, now()->diffInMinutes($this->expiresAt));
    }

    /**
     * Obtiene el tiempo transcurrido en minutos
     *
     * @return int Tiempo transcurrido en minutos
     */
    public function getElapsedTimeMinutes(): int
    {
        return (int) $this->startedAt->diffInMinutes(now());
    }

    /**
     * Verifica si la sesión está completa
     *
     * @return bool True si está completa
     */
    public function isComplete(): bool
    {
        return !$this->hasRemainingCards() || $this->isExpired();
    }

    /**
     * Obtiene las estadísticas de la sesión
     *
     * @return array Estadísticas de la sesión
     */
    public function getStatistics(): array
    {
        return [
            'session_id' => $this->sessionId->toString(),
            'user_id' => $this->userId->toString(),
            'mode' => $this->mode->getValue(),
            'is_premium' => $this->isPremium,
            'total_cards' => $this->cards->count(),
            'viewed_cards' => $this->getViewedCardsCount(),
            'remaining_cards' => $this->getRemainingCardsCount(),
            'progress_percentage' => $this->getProgress(),
            'duration_minutes' => $this->getDurationMinutes(),
            'remaining_time_minutes' => $this->getRemainingTimeMinutes(),
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'is_complete' => $this->isComplete(),
            'started_at' => $this->startedAt->toISOString(),
            'expires_at' => $this->expiresAt->toISOString(),
        ];
    }

    /**
     * Convierte la sesión a array para serialización
     *
     * @return array Representación en array
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId->toArray(),
            'user_id' => $this->userId->toString(),
            'mode' => $this->mode->toArray(),
            'preferences' => $this->preferences->toArray(),
            'cards' => $this->cards->map(fn(DiscoveryCard $card) => $card->toArray())->toArray(),
            'viewed_cards' => $this->viewedCards->map(fn(DiscoveryCardId $id) => $id->toString())->toArray(),
            'started_at' => $this->startedAt->toISOString(),
            'expires_at' => $this->expiresAt->toISOString(),
            'is_premium' => $this->isPremium,
            'max_cards' => $this->maxCards,
            'statistics' => $this->getStatistics(),
        ];
    }

    /**
     * Representación en string de la sesión
     *
     * @return string Representación string
     */
    public function __toString(): string
    {
        return sprintf(
            'DiscoverySession(id=%s, user=%s, mode=%s, progress=%s%%)',
            $this->sessionId->toString(),
            $this->userId->toString(),
            $this->mode->getValue(),
            $this->getProgress()
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
            'session_id' => $this->sessionId->toString(),
            'user_id' => $this->userId->toString(),
            'mode' => $this->mode->getValue(),
            'is_premium' => $this->isPremium,
            'total_cards' => $this->cards->count(),
            'viewed_cards' => $this->getViewedCardsCount(),
            'remaining_cards' => $this->getRemainingCardsCount(),
            'progress_percentage' => $this->getProgress(),
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'is_complete' => $this->isComplete(),
        ];
    }
}
