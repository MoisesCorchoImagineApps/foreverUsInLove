<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

/**
 * MessageType Value Object
 * 
 * Represents the type of message in the ForeverUsInLove dating application.
 * Defines all supported message types with validation and type safety.
 * 
 * @package App\Domain\Chat\ValueObjects
 * @version 1.0.0
 * @since 2025-01-15
 * 
 * @author ForeverUsInLove Development Team
 */
final class MessageType
{
    public const TEXT = 'text';
    public const IMAGE = 'image';
    public const VIDEO = 'video';
    public const AUDIO = 'audio';
    public const LOCATION = 'location';
    public const SYSTEM = 'system';
    public const POLL = 'poll';
    public const FILE = 'file';
    public const STICKER = 'sticker';
    public const GIF = 'gif';

    private const VALID_TYPES = [
        self::TEXT,
        self::IMAGE,
        self::VIDEO,
        self::AUDIO,
        self::LOCATION,
        self::SYSTEM,
        self::POLL,
        self::FILE,
        self::STICKER,
        self::GIF,
    ];

    private const CONTENT_TYPES = [
        self::TEXT,
        self::IMAGE,
        self::VIDEO,
        self::AUDIO,
        self::FILE,
        self::STICKER,
        self::GIF,
    ];

    private const INTERACTIVE_TYPES = [
        self::POLL,
        self::LOCATION,
    ];

    private function __construct(
        private readonly string $value
    ) {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid message type: {$value}");
        }
    }

    public static function text(): self
    {
        return new self(self::TEXT);
    }

    public static function image(): self
    {
        return new self(self::IMAGE);
    }

    public static function video(): self
    {
        return new self(self::VIDEO);
    }

    public static function audio(): self
    {
        return new self(self::AUDIO);
    }

    public static function location(): self
    {
        return new self(self::LOCATION);
    }

    public static function system(): self
    {
        return new self(self::SYSTEM);
    }

    public static function poll(): self
    {
        return new self(self::POLL);
    }

    public static function file(): self
    {
        return new self(self::FILE);
    }

    public static function sticker(): self
    {
        return new self(self::STICKER);
    }

    public static function gif(): self
    {
        return new self(self::GIF);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isText(): bool
    {
        return $this->value === self::TEXT;
    }

    public function isImage(): bool
    {
        return $this->value === self::IMAGE;
    }

    public function isVideo(): bool
    {
        return $this->value === self::VIDEO;
    }

    public function isAudio(): bool
    {
        return $this->value === self::AUDIO;
    }

    public function isLocation(): bool
    {
        return $this->value === self::LOCATION;
    }

    public function isSystem(): bool
    {
        return $this->value === self::SYSTEM;
    }

    public function isPoll(): bool
    {
        return $this->value === self::POLL;
    }

    public function isFile(): bool
    {
        return $this->value === self::FILE;
    }

    public function isSticker(): bool
    {
        return $this->value === self::STICKER;
    }

    public function isGif(): bool
    {
        return $this->value === self::GIF;
    }

    public function hasContent(): bool
    {
        return in_array($this->value, self::CONTENT_TYPES, true);
    }

    public function isInteractive(): bool
    {
        return in_array($this->value, self::INTERACTIVE_TYPES, true);
    }

    public function requiresModeration(): bool
    {
        return in_array($this->value, [self::TEXT, self::IMAGE, self::VIDEO], true);
    }

    public function equals(MessageType $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
