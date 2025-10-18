<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Shared\ValueObjects\UserId;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * CoinsCredited Event
 * 
 * Event fired when coins are credited to a user's account.
 * This event is used for notifications, analytics, and other side effects.
 * 
 * @package App\Domain\Commerce\Events
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class CoinsCredited
{
    use Dispatchable, SerializesModels;

    /**
     * The user who received the coins
     */
    public readonly UserId $userId;

    /**
     * The amount of coins credited
     */
    public readonly int $amount;

    /**
     * The source of the coins
     */
    public readonly string $source;

    /**
     * Additional metadata about the credit
     */
    public readonly array $metadata;

    /**
     * Create a new event instance
     * 
     * @param UserId $userId The user who received the coins
     * @param int $amount The amount of coins credited
     * @param string $source The source of the coins
     * @param array $metadata Additional metadata
     */
    public function __construct(UserId $userId, int $amount, string $source, array $metadata = [])
    {
        $this->userId = $userId;
        $this->amount = $amount;
        $this->source = $source;
        $this->metadata = $metadata;
    }
}
