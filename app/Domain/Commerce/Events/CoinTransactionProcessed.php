<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\Entities\CoinTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * CoinTransactionProcessed Event
 * 
 * Event fired when a coin transaction has been processed successfully.
 * This event is used for notifications, analytics, and other side effects.
 * 
 * @package App\Domain\Commerce\Events
 * @version 1.0.0
 * @since 2025
 * 
 * @author ForeverUsInLove Development Team
 */
class CoinTransactionProcessed
{
    use Dispatchable, SerializesModels;

    /**
     * The processed coin transaction
     */
    public readonly CoinTransaction $transaction;

    /**
     * Create a new event instance
     * 
     * @param CoinTransaction $transaction The processed transaction
     */
    public function __construct(CoinTransaction $transaction)
    {
        $this->transaction = $transaction;
    }
}
