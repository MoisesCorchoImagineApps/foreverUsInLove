<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Shared\ValueObjects\UserId;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * CoinBalanceUpdated Event
 * 
 * Event fired when a user's coin balance has been updated.
 * This event is used for notifications, analytics, and other side effects.
 * 
 * @package App\Domain\Commerce\Events
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class CoinBalanceUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * The user whose balance was updated
     */
    public readonly UserId $userId;

    /**
     * The updated balance information
     */
    public readonly array $balance;

    /**
     * Create a new event instance
     * 
     * @param UserId $userId The user whose balance was updated
     * @param array $balance The updated balance information
     */
    public function __construct(UserId $userId, array $balance)
    {
        $this->userId = $userId;
        $this->balance = $balance;
    }
}
