<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

/**
 * ParticipantRole Value Object
 * 
 * Represents the role of a participant in a chat within the ForeverUsInLove
 * dating application. Defines all supported participant roles with validation.
 * 
 * @package App\Domain\Chat\ValueObjects
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
final class ParticipantRole
{
    public const MEMBER = 'member';
    public const MODERATOR = 'moderator';
    public const ADMIN = 'admin';
    public const CREATOR = 'creator';

    private const VALID_ROLES = [
        self::MEMBER,
        self::MODERATOR,
        self::ADMIN,
        self::CREATOR,
    ];

    private const ROLE_HIERARCHY = [
        self::MEMBER => 1,
        self::MODERATOR => 2,
        self::ADMIN => 3,
        self::CREATOR => 4,
    ];

    private function __construct(
        private readonly string $value
    ) {
        if (!in_array($value, self::VALID_ROLES, true)) {
            throw new \InvalidArgumentException("Invalid participant role: {$value}");
        }
    }

    public static function member(): self
    {
        return new self(self::MEMBER);
    }

    public static function moderator(): self
    {
        return new self(self::MODERATOR);
    }

    public static function admin(): self
    {
        return new self(self::ADMIN);
    }

    public static function creator(): self
    {
        return new self(self::CREATOR);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isMember(): bool
    {
        return $this->value === self::MEMBER;
    }

    public function isModerator(): bool
    {
        return $this->value === self::MODERATOR;
    }

    public function isAdmin(): bool
    {
        return $this->value === self::ADMIN;
    }

    public function isCreator(): bool
    {
        return $this->value === self::CREATOR;
    }

    public function hasPermission(string $permission): bool
    {
        return match ($this->value) {
            self::CREATOR => true, // Creator has all permissions
            self::ADMIN => in_array($permission, ['manage_participants', 'moderate_content', 'edit_chat']),
            self::MODERATOR => in_array($permission, ['moderate_content']),
            self::MEMBER => in_array($permission, ['send_messages']),
            default => false,
        };
    }

    public function canManage(ParticipantRole $otherRole): bool
    {
        return self::ROLE_HIERARCHY[$this->value] > self::ROLE_HIERARCHY[$otherRole->value];
    }

    public function equals(ParticipantRole $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
