<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Shared\ValueObjects\UserId;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * CoinsDebited Event
 * 
 * Event fired when coins are debited from a user's account.
 * This event is used for notifications, analytics, and other side effects.
 * 
 * @package App\Domain\Commerce\Events
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class CoinsDebited
{
    use Dispatchable, SerializesModels;

    /**
     * The user who had coins debited
     */
    public readonly UserId $userId;

    /**
     * The amount of coins debited
     */
    public readonly int $amount;

    /**
     * The purpose of the debit
     */
    public readonly string $purpose;

    /**
     * Additional metadata about the debit
     */
    public readonly array $metadata;

    /**
     * Create a new event instance
     * 
     * @param UserId $userId The user who had coins debited
     * @param int $amount The amount of coins debited
     * @param string $purpose The purpose of the debit
     * @param array $metadata Additional metadata
     */
    public function __construct(UserId $userId, int $amount, string $purpose, array $metadata = [])
    {
        $this->userId = $userId;
        $this->amount = $amount;
        $this->purpose = $purpose;
        $this->metadata = $metadata;
    }
}
