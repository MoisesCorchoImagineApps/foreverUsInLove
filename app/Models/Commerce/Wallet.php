<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\User;
use App\Events\Commerce\CoinsAdded;
use App\Events\Commerce\CoinsDeducted;
use App\Events\Commerce\CoinsTransferred;

/**
 * Wallet Model - User Virtual Currency Wallet
 * 
 * Manages user coin balance with transaction history and daily limits
 * Implements double-entry bookkeeping for accuracy
 * 
 * @property int $id
 * @property int $user_id
 * @property int $balance Current coin balance
 * @property int $lifetime_earned Total coins earned
 * @property int $lifetime_spent Total coins spent
 * @property int $lifetime_purchased Total coins purchased
 * @property int $lifetime_gifted Total coins received as gifts
 * @property int $daily_spent Today's spending
 * @property int $daily_purchased Today's purchases
 * @property int $daily_transferred Today's transfers
 * @property date $last_spent_date
 * @property date $last_purchased_date
 * @property date $last_transferred_date
 * @property array $metadata Additional data
 * @property timestamps created_at, updated_at
 * @property softDeletes deleted_at
 */
class Wallet extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'wallets';

    protected $fillable = [
        'user_id',
        'balance',
        'lifetime_earned',
        'lifetime_spent',
        'lifetime_purchased',
        'lifetime_gifted',
        'daily_spent',
        'daily_purchased',
        'daily_transferred',
        'last_spent_date',
        'last_purchased_date',
        'last_transferred_date',
        'metadata',
    ];

    protected $hidden = [
        'metadata',
    ];

    protected $casts = [
        'balance' => 'integer',
        'lifetime_earned' => 'integer',
        'lifetime_spent' => 'integer',
        'lifetime_purchased' => 'integer',
        'lifetime_gifted' => 'integer',
        'daily_spent' => 'integer',
        'daily_purchased' => 'integer',
        'daily_transferred' => 'integer',
        'last_spent_date' => 'date',
        'last_purchased_date' => 'date',
        'last_transferred_date' => 'date',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Daily Limits (from CoinService)
    const DAILY_LIMIT_PURCHASE = 10000;
    const DAILY_LIMIT_SPEND = 5000;
    const DAILY_LIMIT_TRANSFER = 1000;

    // Reward Amounts (from CoinService)
    const REWARD_DAILY_LOGIN = 10;
    const REWARD_PROFILE_COMPLETION = 50;
    const REWARD_VERIFICATION = 100;
    const REWARD_REFERRAL = 200;

    // Transaction Types
    const TYPE_PURCHASE = 'purchase';
    const TYPE_REWARD = 'reward';
    const TYPE_GIFT_RECEIVED = 'gift_received';
    const TYPE_GIFT_SENT = 'gift_sent';
    const TYPE_TRANSFER_IN = 'transfer_in';
    const TYPE_TRANSFER_OUT = 'transfer_out';
    const TYPE_REFUND = 'refund';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_FEATURE = 'feature';

    /**
     * ===============================================
     * RELATIONSHIPS
     * ===============================================
     */

    /**
     * User who owns the wallet
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Transaction history
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CoinTransaction::class);
    }

    /**
     * Recent transactions
     */
    public function recentTransactions(int $limit = 10): Collection
    {
        return $this->transactions()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * ===============================================
     * SCOPES
     * ===============================================
     */

    /**
     * Scope for user's wallet
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for wallets with balance
     */
    public function scopeWithBalance(Builder $query): Builder
    {
        return $query->where('balance', '>', 0);
    }

    /**
     * Scope for active wallets (recent activity)
     */
    public function scopeActive(Builder $query, int $days = 30): Builder
    {
        return $query->where(function($q) use ($days) {
            $q->where('last_spent_date', '>=', now()->subDays($days))
              ->orWhere('last_purchased_date', '>=', now()->subDays($days));
        });
    }

    /**
     * ===============================================
     * ACCESSORS & MUTATORS
     * ===============================================
     */

    /**
     * Get formatted balance
     */
    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance) . ' coins';
    }

    /**
     * Get remaining daily purchase limit
     */
    public function getRemainingDailyPurchaseAttribute(): int
    {
        $this->resetDailyLimitsIfNeeded();
        return max(0, self::DAILY_LIMIT_PURCHASE - $this->daily_purchased);
    }

    /**
     * Get remaining daily spend limit
     */
    public function getRemainingDailySpendAttribute(): int
    {
        $this->resetDailyLimitsIfNeeded();
        return max(0, self::DAILY_LIMIT_SPEND - $this->daily_spent);
    }

    /**
     * Get remaining daily transfer limit
     */
    public function getRemainingDailyTransferAttribute(): int
    {
        $this->resetDailyLimitsIfNeeded();
        return max(0, self::DAILY_LIMIT_TRANSFER - $this->daily_transferred);
    }

    /**
     * Check if user can purchase
     */
    public function getCanPurchaseAttribute(): bool
    {
        return $this->remaining_daily_purchase > 0;
    }

    /**
     * Check if user can spend
     */
    public function getCanSpendAttribute(): bool
    {
        return $this->remaining_daily_spend > 0 && $this->balance > 0;
    }

    /**
     * Check if user can transfer
     */
    public function getCanTransferAttribute(): bool
    {
        return $this->remaining_daily_transfer > 0 && $this->balance > 0;
    }

    /**
     * ===============================================
     * BUSINESS LOGIC METHODS
     * ===============================================
     */

    /**
     * Add coins to wallet
     */
    public function addCoins(int $amount, string $type, array $metadata = []): bool
    {
        if ($amount <= 0) {
            return false;
        }

        try {
            DB::beginTransaction();

            // Update balance
            $newBalance = $this->balance + $amount;
            
            $updates = [
                'balance' => $newBalance,
                'lifetime_earned' => $this->lifetime_earned + $amount,
            ];

            // Track specific types
            if ($type === self::TYPE_PURCHASE) {
                $this->resetDailyLimitsIfNeeded();
                
                if ($this->daily_purchased + $amount > self::DAILY_LIMIT_PURCHASE) {
                    throw new \Exception('Daily purchase limit exceeded');
                }

                $updates['daily_purchased'] = $this->daily_purchased + $amount;
                $updates['last_purchased_date'] = now();
                $updates['lifetime_purchased'] = $this->lifetime_purchased + $amount;
            } elseif ($type === self::TYPE_GIFT_RECEIVED) {
                $updates['lifetime_gifted'] = $this->lifetime_gifted + $amount;
            }

            $this->update($updates);

            // Create transaction record
            $this->transactions()->create([
                'user_id' => $this->user_id,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $this->balance - $amount,
                'balance_after' => $newBalance,
                'status' => 'completed',
                'metadata' => $metadata,
            ]);

            DB::commit();

            event(new CoinsAdded($this, $amount, $type));

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to add coins', [
                'wallet_id' => $this->id,
                'amount' => $amount,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Deduct coins from wallet
     */
    public function deductCoins(int $amount, string $type, array $metadata = []): bool
    {
        if ($amount <= 0) {
            return false;
        }

        if ($this->balance < $amount) {
            \Log::warning('Insufficient balance', [
                'wallet_id' => $this->id,
                'balance' => $this->balance,
                'requested' => $amount,
            ]);
            return false;
        }

        try {
            DB::beginTransaction();

            // Check daily spend limit for certain types
            if (in_array($type, [self::TYPE_GIFT_SENT, self::TYPE_FEATURE])) {
                $this->resetDailyLimitsIfNeeded();
                
                if ($this->daily_spent + $amount > self::DAILY_LIMIT_SPEND) {
                    throw new \Exception('Daily spend limit exceeded');
                }
            }

            // Update balance
            $newBalance = $this->balance - $amount;
            
            $updates = [
                'balance' => $newBalance,
                'lifetime_spent' => $this->lifetime_spent + $amount,
            ];

            // Track daily spending
            if (in_array($type, [self::TYPE_GIFT_SENT, self::TYPE_FEATURE])) {
                $updates['daily_spent'] = $this->daily_spent + $amount;
                $updates['last_spent_date'] = now();
            }

            $this->update($updates);

            // Create transaction record
            $this->transactions()->create([
                'user_id' => $this->user_id,
                'type' => $type,
                'amount' => -$amount,
                'balance_before' => $this->balance + $amount,
                'balance_after' => $newBalance,
                'status' => 'completed',
                'metadata' => $metadata,
            ]);

            DB::commit();

            event(new CoinsDeducted($this, $amount, $type));

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to deduct coins', [
                'wallet_id' => $this->id,
                'amount' => $amount,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Transfer coins to another user
     */
    public function transferTo(Wallet $recipientWallet, int $amount, array $metadata = []): bool
    {
        if ($amount <= 0) {
            return false;
        }

        if ($this->balance < $amount) {
            return false;
        }

        $this->resetDailyLimitsIfNeeded();

        if ($this->daily_transferred + $amount > self::DAILY_LIMIT_TRANSFER) {
            \Log::warning('Daily transfer limit exceeded', [
                'wallet_id' => $this->id,
                'daily_transferred' => $this->daily_transferred,
                'requested' => $amount,
            ]);
            return false;
        }

        try {
            DB::beginTransaction();

            // Deduct from sender
            $senderDeducted = $this->deductCoins($amount, self::TYPE_TRANSFER_OUT, array_merge($metadata, [
                'recipient_id' => $recipientWallet->user_id,
            ]));

            if (!$senderDeducted) {
                throw new \Exception('Failed to deduct coins from sender');
            }

            // Add to recipient
            $recipientAdded = $recipientWallet->addCoins($amount, self::TYPE_TRANSFER_IN, array_merge($metadata, [
                'sender_id' => $this->user_id,
            ]));

            if (!$recipientAdded) {
                throw new \Exception('Failed to add coins to recipient');
            }

            // Update transfer tracking
            $this->update([
                'daily_transferred' => $this->daily_transferred + $amount,
                'last_transferred_date' => now(),
            ]);

            DB::commit();

            event(new CoinsTransferred($this, $recipientWallet, $amount));

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to transfer coins', [
                'sender_wallet_id' => $this->id,
                'recipient_wallet_id' => $recipientWallet->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Award reward coins
     */
    public function awardReward(string $rewardType, array $metadata = []): bool
    {
        $amount = match($rewardType) {
            'daily_login' => self::REWARD_DAILY_LOGIN,
            'profile_completion' => self::REWARD_PROFILE_COMPLETION,
            'verification' => self::REWARD_VERIFICATION,
            'referral' => self::REWARD_REFERRAL,
            default => 0,
        };

        if ($amount <= 0) {
            return false;
        }

        return $this->addCoins($amount, self::TYPE_REWARD, array_merge($metadata, [
            'reward_type' => $rewardType,
        ]));
    }

    /**
     * Process refund
     */
    public function refund(int $amount, array $metadata = []): bool
    {
        return $this->addCoins($amount, self::TYPE_REFUND, $metadata);
    }

    /**
     * Admin adjustment (can be positive or negative)
     */
    public function adjust(int $amount, string $reason): bool
    {
        if ($amount > 0) {
            return $this->addCoins($amount, self::TYPE_ADJUSTMENT, ['reason' => $reason]);
        } elseif ($amount < 0) {
            return $this->deductCoins(abs($amount), self::TYPE_ADJUSTMENT, ['reason' => $reason]);
        }

        return false;
    }

    /**
     * Check if user has sufficient balance
     */
    public function hasSufficientBalance(int $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Check if user can purchase amount within daily limit
     */
    public function canPurchaseAmount(int $amount): bool
    {
        $this->resetDailyLimitsIfNeeded();
        return $this->daily_purchased + $amount <= self::DAILY_LIMIT_PURCHASE;
    }

    /**
     * Check if user can spend amount within daily limit
     */
    public function canSpendAmount(int $amount): bool
    {
        $this->resetDailyLimitsIfNeeded();
        return $this->hasSufficientBalance($amount) && 
               ($this->daily_spent + $amount <= self::DAILY_LIMIT_SPEND);
    }

    /**
     * Check if user can transfer amount within daily limit
     */
    public function canTransferAmount(int $amount): bool
    {
        $this->resetDailyLimitsIfNeeded();
        return $this->hasSufficientBalance($amount) && 
               ($this->daily_transferred + $amount <= self::DAILY_LIMIT_TRANSFER);
    }

    /**
     * Reset daily limits if new day
     */
    protected function resetDailyLimitsIfNeeded(): void
    {
        $today = now()->toDateString();
        $needsReset = false;

        if ($this->last_spent_date && $this->last_spent_date->toDateString() !== $today) {
            $this->daily_spent = 0;
            $needsReset = true;
        }

        if ($this->last_purchased_date && $this->last_purchased_date->toDateString() !== $today) {
            $this->daily_purchased = 0;
            $needsReset = true;
        }

        if ($this->last_transferred_date && $this->last_transferred_date->toDateString() !== $today) {
            $this->daily_transferred = 0;
            $needsReset = true;
        }

        if ($needsReset) {
            $this->saveQuietly(); // Save without firing events
        }
    }

    /**
     * Get transaction history summary
     */
    public function getTransactionSummary(int $days = 30): array
    {
        $transactions = $this->transactions()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        return [
            'total_transactions' => $transactions->count(),
            'total_earned' => $transactions->where('amount', '>', 0)->sum('amount'),
            'total_spent' => abs($transactions->where('amount', '<', 0)->sum('amount')),
            'by_type' => $transactions->groupBy('type')->map(function($typeTransactions) {
                return [
                    'count' => $typeTransactions->count(),
                    'total' => $typeTransactions->sum('amount'),
                ];
            }),
            'largest_transaction' => $transactions->sortByDesc('amount')->first(),
            'recent' => $transactions->sortByDesc('created_at')->take(5)->values(),
        ];
    }

    /**
     * Get wallet statistics
     */
    public function getStatistics(): array
    {
        return [
            'current_balance' => $this->balance,
            'lifetime_earned' => $this->lifetime_earned,
            'lifetime_spent' => $this->lifetime_spent,
            'lifetime_purchased' => $this->lifetime_purchased,
            'lifetime_gifted' => $this->lifetime_gifted,
            'net_lifetime' => $this->lifetime_earned - $this->lifetime_spent,
            'daily_limits' => [
                'purchase' => [
                    'used' => $this->daily_purchased,
                    'remaining' => $this->remaining_daily_purchase,
                    'limit' => self::DAILY_LIMIT_PURCHASE,
                ],
                'spend' => [
                    'used' => $this->daily_spent,
                    'remaining' => $this->remaining_daily_spend,
                    'limit' => self::DAILY_LIMIT_SPEND,
                ],
                'transfer' => [
                    'used' => $this->daily_transferred,
                    'remaining' => $this->remaining_daily_transfer,
                    'limit' => self::DAILY_LIMIT_TRANSFER,
                ],
            ],
            'transaction_summary' => $this->getTransactionSummary(),
        ];
    }

    /**
     * ===============================================
     * STATIC METHODS
     * ===============================================
     */

    /**
     * Get overall wallet analytics
     */
    public static function getOverallAnalytics(): array
    {
        return [
            'total_wallets' => self::count(),
            'active_wallets' => self::active()->count(),
            'total_balance' => self::sum('balance'),
            'total_lifetime_earned' => self::sum('lifetime_earned'),
            'total_lifetime_spent' => self::sum('lifetime_spent'),
            'avg_balance' => self::avg('balance'),
            'wallets_with_balance' => self::withBalance()->count(),
            'top_balances' => self::orderByDesc('balance')->limit(10)->get(),
        ];
    }

    /**
     * ===============================================
     * MODEL EVENTS
     * ===============================================
     */

    protected static function booted(): void
    {
        // Initialize wallet on creation
        static::creating(function (Wallet $wallet) {
            $wallet->balance = $wallet->balance ?? 0;
            $wallet->lifetime_earned = $wallet->lifetime_earned ?? 0;
            $wallet->lifetime_spent = $wallet->lifetime_spent ?? 0;
            $wallet->lifetime_purchased = $wallet->lifetime_purchased ?? 0;
            $wallet->lifetime_gifted = $wallet->lifetime_gifted ?? 0;
            $wallet->daily_spent = $wallet->daily_spent ?? 0;
            $wallet->daily_purchased = $wallet->daily_purchased ?? 0;
            $wallet->daily_transferred = $wallet->daily_transferred ?? 0;
        });
    }
}