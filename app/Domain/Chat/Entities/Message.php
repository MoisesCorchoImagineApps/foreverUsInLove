<?php

declare(strict_types=1);

namespace App\Domain\Chat\Entities;

use App\Domain\Chat\ValueObjects\MessageId;
use App\Domain\Chat\ValueObjects\MessageType;
use App\Domain\Chat\ValueObjects\MessageStatus;
use App\Domain\Chat\ValueObjects\ChatId;
use App\Domain\Shared\ValueObjects\UserId;
use Carbon\Carbon;

/**
 * Message Entity
 * 
 * Core domain entity representing a message in the ForeverUsInLove dating application.
 * Encapsulates message business logic, state management, and domain rules.
 * 
 * @package App\Domain\Chat\Entities
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
class Message
{
    private function __construct(
        private readonly MessageId $id,
        private readonly ChatId $chatId,
        private readonly UserId $senderId,
        private readonly MessageType $type,
        private MessageStatus $status,
        private ?string $content,
        private array $attachments,
        private ?MessageId $replyToId,
        private array $mentions,
        private array $metadata,
        private ?Carbon $scheduledAt,
        private ?Carbon $expiresAt,
        private bool $isEncrypted,
        private Carbon $createdAt,
        private Carbon $updatedAt,
        private ?Carbon $sentAt,
        private ?Carbon $deliveredAt,
        private ?Carbon $readAt,
        private ?Carbon $editedAt,
        private ?string $editReason
    ) {}

    public static function create(
        MessageId $id,
        ChatId $chatId,
        UserId $senderId,
        MessageType $type,
        ?string $content = null,
        array $attachments = [],
        ?MessageId $replyToId = null,
        array $mentions = [],
        array $metadata = [],
        ?Carbon $scheduledAt = null,
        ?Carbon $expiresAt = null,
        bool $isEncrypted = false
    ): self {
        $now = now();
        
        return new self(
            id: $id,
            chatId: $chatId,
            senderId: $senderId,
            type: $type,
            status: $scheduledAt ? MessageStatus::scheduled() : MessageStatus::pending(),
            content: $content,
            attachments: $attachments,
            replyToId: $replyToId,
            mentions: $mentions,
            metadata: $metadata,
            scheduledAt: $scheduledAt,
            expiresAt: $expiresAt,
            isEncrypted: $isEncrypted,
            createdAt: $now,
            updatedAt: $now,
            sentAt: null,
            deliveredAt: null,
            readAt: null,
            editedAt: null,
            editReason: null
        );
    }

    public function id(): MessageId
    {
        return $this->id;
    }

    public function chatId(): ChatId
    {
        return $this->chatId;
    }

    public function senderId(): UserId
    {
        return $this->senderId;
    }

    public function type(): MessageType
    {
        return $this->type;
    }

    public function status(): MessageStatus
    {
        return $this->status;
    }

    public function content(): ?string
    {
        return $this->content;
    }

    public function attachments(): array
    {
        return $this->attachments;
    }

    public function replyToId(): ?MessageId
    {
        return $this->replyToId;
    }

    public function mentions(): array
    {
        return $this->mentions;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function scheduledAt(): ?Carbon
    {
        return $this->scheduledAt;
    }

    public function expiresAt(): ?Carbon
    {
        return $this->expiresAt;
    }

    public function isEncrypted(): bool
    {
        return $this->isEncrypted;
    }

    public function createdAt(): Carbon
    {
        return $this->createdAt;
    }

    public function updatedAt(): Carbon
    {
        return $this->updatedAt;
    }

    public function sentAt(): ?Carbon
    {
        return $this->sentAt;
    }

    public function deliveredAt(): ?Carbon
    {
        return $this->deliveredAt;
    }

    public function readAt(): ?Carbon
    {
        return $this->readAt;
    }

    public function editedAt(): ?Carbon
    {
        return $this->editedAt;
    }

    public function editReason(): ?string
    {
        return $this->editReason;
    }

    public function markAsSent(): void
    {
        if (!$this->status->canTransitionTo(MessageStatus::sent())) {
            throw new \InvalidArgumentException("Cannot transition from {$this->status->value()} to sent");
        }

        $this->status = MessageStatus::sent();
        $this->sentAt = now();
        $this->updatedAt = now();
    }

    public function markAsDelivered(): void
    {
        if (!$this->status->canTransitionTo(MessageStatus::delivered())) {
            throw new \InvalidArgumentException("Cannot transition from {$this->status->value()} to delivered");
        }

        $this->status = MessageStatus::delivered();
        $this->deliveredAt = now();
        $this->updatedAt = now();
    }

    public function markAsRead(): void
    {
        if (!$this->status->canTransitionTo(MessageStatus::read())) {
            throw new \InvalidArgumentException("Cannot transition from {$this->status->value()} to read");
        }

        $this->status = MessageStatus::read();
        $this->readAt = now();
        $this->updatedAt = now();
    }

    public function markAsFailed(): void
    {
        if (!$this->status->canTransitionTo(MessageStatus::failed())) {
            throw new \InvalidArgumentException("Cannot transition from {$this->status->value()} to failed");
        }

        $this->status = MessageStatus::failed();
        $this->updatedAt = now();
    }

    public function editContent(string $newContent, ?string $reason = null): void
    {
        if ($this->isExpired()) {
            throw new \InvalidArgumentException("Cannot edit expired message");
        }

        if ($this->status->isDeleted()) {
            throw new \InvalidArgumentException("Cannot edit deleted message");
        }

        $this->content = $newContent;
        $this->editedAt = now();
        $this->editReason = $reason;
        $this->updatedAt = now();
    }

    public function addAttachment(array $attachment): void
    {
        $this->attachments[] = $attachment;
        $this->updatedAt = now();
    }

    public function removeAttachment(int $index): void
    {
        if (isset($this->attachments[$index])) {
            unset($this->attachments[$index]);
            $this->attachments = array_values($this->attachments);
            $this->updatedAt = now();
        }
    }

    public function addMention(UserId $userId): void
    {
        if (!in_array($userId->toInt(), $this->mentions)) {
            $this->mentions[] = $userId->toInt();
            $this->updatedAt = now();
        }
    }

    public function removeMention(UserId $userId): void
    {
        $this->mentions = array_filter($this->mentions, fn($id) => $id !== $userId->toInt());
        $this->updatedAt = now();
    }

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        $this->updatedAt = now();
    }

    public function isExpired(): bool
    {
        return $this->expiresAt && $this->expiresAt->isPast();
    }

    public function isScheduled(): bool
    {
        return $this->status->isScheduled() && $this->scheduledAt && $this->scheduledAt->isFuture();
    }

    public function isReadyToSend(): bool
    {
        return $this->status->isScheduled() && $this->scheduledAt && $this->scheduledAt->isPast();
    }

    public function canBeEdited(): bool
    {
        return !$this->isExpired() && 
               !$this->status->isDeleted() && 
               !$this->status->isFailed() &&
               $this->type->hasContent();
    }

    public function canBeDeleted(): bool
    {
        return !$this->status->isDeleted();
    }

    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    public function hasMentions(): bool
    {
        return !empty($this->mentions);
    }

    public function isReply(): bool
    {
        return $this->replyToId !== null;
    }

    public function isSystemMessage(): bool
    {
        return $this->type->isSystem();
    }

    public function requiresModeration(): bool
    {
        return $this->type->requiresModeration();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'chat_id' => $this->chatId->value(),
            'sender_id' => $this->senderId->toInt(),
            'type' => $this->type->value(),
            'status' => $this->status->value(),
            'content' => $this->content,
            'attachments' => $this->attachments,
            'reply_to_id' => $this->replyToId?->value(),
            'mentions' => $this->mentions,
            'metadata' => $this->metadata,
            'scheduled_at' => $this->scheduledAt?->toDateTimeString(),
            'expires_at' => $this->expiresAt?->toDateTimeString(),
            'is_encrypted' => $this->isEncrypted,
            'created_at' => $this->createdAt->toDateTimeString(),
            'updated_at' => $this->updatedAt->toDateTimeString(),
            'sent_at' => $this->sentAt?->toDateTimeString(),
            'delivered_at' => $this->deliveredAt?->toDateTimeString(),
            'read_at' => $this->readAt?->toDateTimeString(),
            'edited_at' => $this->editedAt?->toDateTimeString(),
            'edit_reason' => $this->editReason,
        ];
    }
}
