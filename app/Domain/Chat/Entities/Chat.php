<?php

declare(strict_types=1);

namespace App\Domain\Chat\Entities;

use App\Domain\Chat\ValueObjects\ChatId;
use App\Domain\Chat\ValueObjects\ChatType;
use App\Domain\Chat\ValueObjects\ChatStatus;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;

/**
 * Chat Entity
 * 
 * Core domain entity representing a chat in the ForeverUsInLove dating application.
 * Encapsulates chat business logic, state management, and domain rules.
 * 
 * @package App\Domain\Chat\Entities
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class Chat
{
    private function __construct(
        private readonly ChatId $id,
        private readonly ChatType $type,
        private ChatStatus $status,
        private readonly UserId $creatorId,
        private string $name,
        private ?string $description,
        private array $participants,
        private array $settings,
        private array $metadata,
        private ?Carbon $expiresAt,
        private Carbon $createdAt,
        private Carbon $updatedAt,
        private ?Carbon $lastActivityAt
    ) {}

    public static function create(
        ChatId $id,
        ChatType $type,
        UserId $creatorId,
        string $name,
        ?string $description = null,
        array $participants = [],
        array $settings = [],
        array $metadata = [],
        ?Carbon $expiresAt = null
    ): self {
        $now = now();
        
        return new self(
            id: $id,
            type: $type,
            status: ChatStatus::active(),
            creatorId: $creatorId,
            name: $name,
            description: $description,
            participants: $participants,
            settings: $settings,
            metadata: $metadata,
            expiresAt: $expiresAt,
            createdAt: $now,
            updatedAt: $now,
            lastActivityAt: $now
        );
    }

    public function id(): ChatId
    {
        return $this->id;
    }

    public function type(): ChatType
    {
        return $this->type;
    }

    public function status(): ChatStatus
    {
        return $this->status;
    }

    public function creatorId(): UserId
    {
        return $this->creatorId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function participants(): array
    {
        return $this->participants;
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function expiresAt(): ?Carbon
    {
        return $this->expiresAt;
    }

    public function createdAt(): Carbon
    {
        return $this->createdAt;
    }

    public function updatedAt(): Carbon
    {
        return $this->updatedAt;
    }

    public function lastActivityAt(): ?Carbon
    {
        return $this->lastActivityAt;
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = now();
    }

    public function updateDescription(?string $description): void
    {
        $this->description = $description;
        $this->updatedAt = now();
    }

    public function updateSettings(array $settings): void
    {
        $this->settings = array_merge($this->settings, $settings);
        $this->updatedAt = now();
    }

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        $this->updatedAt = now();
    }

    public function archive(): void
    {
        $this->status = ChatStatus::archived();
        $this->updatedAt = now();
    }

    public function suspend(): void
    {
        $this->status = ChatStatus::suspended();
        $this->updatedAt = now();
    }

    public function activate(): void
    {
        $this->status = ChatStatus::active();
        $this->updatedAt = now();
    }

    public function updateLastActivity(): void
    {
        $this->lastActivityAt = now();
        $this->updatedAt = now();
    }

    public function isExpired(): bool
    {
        return $this->expiresAt && $this->expiresAt->isPast();
    }

    public function isActive(): bool
    {
        return $this->status->isActive() && !$this->isExpired();
    }

    public function canParticipantJoin(UserId $userId): bool
    {
        if ($this->isExpired() || !$this->status->isActive()) {
            return false;
        }

        $maxParticipants = $this->settings['max_participants'] ?? 100;
        if (count($this->participants) >= $maxParticipants) {
            return false;
        }

        return !in_array($userId->toInt(), array_column($this->participants, 'user_id'));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'type' => $this->type->value(),
            'status' => $this->status->value(),
            'creator_id' => $this->creatorId->toInt(),
            'name' => $this->name,
            'description' => $this->description,
            'participants' => $this->participants,
            'settings' => $this->settings,
            'metadata' => $this->metadata,
            'expires_at' => $this->expiresAt?->toDateTimeString(),
            'created_at' => $this->createdAt->toDateTimeString(),
            'updated_at' => $this->updatedAt->toDateTimeString(),
            'last_activity_at' => $this->lastActivityAt?->toDateTimeString(),
        ];
    }
}
