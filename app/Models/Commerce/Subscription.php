<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Events\Commerce\PlanSubscribed;
use App\Events\Commerce\SubscriptionRenewed;
use App\Events\Commerce\SubscriptionCancelled;
use App\Events\Commerce\SubscriptionExpired;

/**
 * Subscription Model - User Subscription Management
 * 
 * Manages user subscriptions to plans with billing cycles and lifecycle
 * Handles trials, renewals, cancellations, and upgrades/downgrades
 * 
 * @property int $id
 * @property string $subscription_number Unique identifier (SUB-XXXXXX)
 * @property int $user_id
 * @property int $plan_id
 * @property string $billing_cycle (monthly, quarterly, annual)
 * @property string $status (trial, active, cancelled, expired, suspended, past_due)
 * @property decimal $price Subscription price
 * @property string $currency Default USD
 * @property datetime $trial_ends_at
 * @property datetime $started_at
 * @property datetime $current_period_start
 * @property datetime $current_period_end
 * @property datetime $ends_at Cancellation/expiry date
 * @property datetime $cancelled_at
 * @property string $cancellation_reason
 * @property bool $cancel_at_period_end
 * @property datetime $renewed_at
 * @property int $renewal_count
 * @property bool $auto_renew
 * @property string $payment_method_id
 * @property array $features_snapshot Features at subscription time
 * @property array $metadata Additional data
 * @property array $usage_stats Feature usage tracking
 * @property datetime $last_payment_at
 * @property datetime $next_payment_at
 * @property decimal $next_payment_amount
 * @property timestamps created_at, updated_at
 * @property softDeletes deleted_at
 */
class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'subscriptions';

    protected $fillable = [
        'subscription_number',
        'user_id',
        'plan_id',
        'billing_cycle',
        'status',
        'price',
        'currency',
        'trial_ends_at',
        'started_at',
        'current_period_start',
        'current_period_end',
        'ends_at',
        'cancelled_at',
        'cancellation_reason',
        'cancel_at_period_end',
        'renewed_at',
        'renewal_count',
        'auto_renew',
        'payment_method_id',
        'features_snapshot',
        'metadata',
        'usage_stats',
        'last_payment_at',
        'next_payment_at',
        'next_payment_amount',
    ];

    protected $hidden = [
        'metadata',
        'usage_stats',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'next_payment_amount' => 'decimal:2',
        'cancel_at_period_end' => 'boolean',
        'auto_renew' => 'boolean',
        'renewal_count' => 'integer',
        'features_snapshot' => 'array',
        'metadata' => 'array',
        'usage_stats' => 'array',
        'trial_ends_at' => 'datetime',
        'started_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'renewed_at' => 'datetime',
        'last_payment_at' => 'datetime',
        'next_payment_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Subscription Status
    const STATUS_TRIAL = 'trial';
    const STATUS_ACTIVE = 'active';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_PAST_DUE = 'past_due';

    // Billing Cycles (match Plan cycles)
    const CYCLE_MONTHLY = 'monthly';
    const CYCLE_QUARTERLY = 'quarterly';
    const CYCLE_ANNUAL = 'annual';

    /**
     * ===============================================
     * RELATIONSHIPS
     * ===============================================
     */

    /**
     * User who owns the subscription
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Subscribed plan
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Payment method
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * ===============================================
     * SCOPES
     * ===============================================
     */

    /**
     * Scope by status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for active subscriptions
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('current_period_end', '>=', now());
    }

    /**
     * Scope for trial subscriptions
     */
    public function scopeTrial(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TRIAL)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>=', now());
    }

    /**
     * Scope for cancelled subscriptions
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope for expired subscriptions
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * Scope for ending soon (within 7 days)
     */
    public function scopeEndingSoon(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereBetween('current_period_end', [now(), now()->addDays(7)]);
    }

    /**
     * Scope for user's subscriptions
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope by plan
     */
    public function scopeByPlan(Builder $query, int $planId): Builder
    {
        return $query->where('plan_id', $planId);
    }

    /**
     * Scope by billing cycle
     */
    public function scopeByBillingCycle(Builder $query, string $cycle): Builder
    {
        return $query->where('billing_cycle', $cycle);
    }

    /**
     * Scope for auto-renewing subscriptions
     */
    public function scopeAutoRenew(Builder $query): Builder
    {
        return $query->where('auto_renew', true);
    }

    /**
     * ===============================================
     * ACCESSORS & MUTATORS
     * ===============================================
     */

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Check if subscription is on trial
     */
    public function getIsOnTrialAttribute(): bool
    {
        return $this->status === self::STATUS_TRIAL &&
               $this->trial_ends_at &&
               now()->lt($this->trial_ends_at);
    }

    /**
     * Check if subscription is active
     */
    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_TRIAL, self::STATUS_ACTIVE]) &&
               ($this->current_period_end === null || now()->lte($this->current_period_end));
    }

    /**
     * Check if subscription is cancelled
     */
    public function getIsCancelledAttribute(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if subscription is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->status === self::STATUS_EXPIRED ||
               ($this->ends_at && now()->gt($this->ends_at));
    }

    /**
     * Check if ending soon
     */
    public function getIsEndingSoonAttribute(): bool
    {
        return $this->is_active &&
               $this->current_period_end &&
               now()->addDays(7)->gte($this->current_period_end);
    }

    /**
     * Get days remaining in current period
     */
    public function getDaysRemainingAttribute(): int
    {
        if (!$this->current_period_end) {
            return 0;
        }

        return max(0, now()->diffInDays($this->current_period_end, false));
    }

    /**
     * Get days remaining in trial
     */
    public function getTrialDaysRemainingAttribute(): int
    {
        if (!$this->is_on_trial || !$this->trial_ends_at) {
            return 0;
        }

        return max(0, now()->diffInDays($this->trial_ends_at, false));
    }

    /**
     * ===============================================
     * BUSINESS LOGIC METHODS
     * ===============================================
     */

    /**
     * Start subscription
     */
    public function start(): bool
    {
        try {
            DB::beginTransaction();

            $now = now();
            $periodEnd = $this->calculatePeriodEnd($now);

            // Check if trial available
            $trialDays = $this->plan->trial_days ?? 0;
            $trialEnds = $trialDays > 0 ? $now->copy()->addDays($trialDays) : null;

            $this->update([
                'status' => $trialEnds ? self::STATUS_TRIAL : self::STATUS_ACTIVE,
                'started_at' => $now,
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
                'trial_ends_at' => $trialEnds,
                'next_payment_at' => $trialEnds ?? $periodEnd,
                'next_payment_amount' => $this->price,
                'features_snapshot' => $this->plan->features,
                'auto_renew' => true,
            ]);

            // Increment plan subscriber count
            $this->plan->incrementSubscribers();

            DB::commit();

            event(new PlanSubscribed($this));

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Subscription start failed', [
                'subscription_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Renew subscription
     */
    public function renew(): bool
    {
        if (!$this->auto_renew) {
            return false;
        }

        try {
            DB::beginTransaction();

            // Process payment
            $payment = $this->processRenewalPayment();
            
            if (!$payment || !$payment->is_successful) {
                $this->update(['status' => self::STATUS_PAST_DUE]);
                DB::commit();
                return false;
            }

            $now = now();
            $newPeriodEnd = $this->calculatePeriodEnd($now);

            $this->update([
                'status' => self::STATUS_ACTIVE,
                'current_period_start' => $now,
                'current_period_end' => $newPeriodEnd,
                'renewed_at' => $now,
                'last_payment_at' => $now,
                'next_payment_at' => $newPeriodEnd,
                'renewal_count' => $this->renewal_count + 1,
            ]);

            DB::commit();

            event(new SubscriptionRenewed($this));

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Subscription renewal failed', [
                'subscription_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel(string $reason = null, bool $immediately = false): bool
    {
        try {
            DB::beginTransaction();

            if ($immediately) {
                // Cancel immediately
                $this->update([
                    'status' => self::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                    'ends_at' => now(),
                    'auto_renew' => false,
                ]);
            } else {
                // Cancel at period end
                $this->update([
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                    'cancel_at_period_end' => true,
                    'ends_at' => $this->current_period_end,
                    'auto_renew' => false,
                ]);
            }

            // Decrement plan subscriber count
            $this->plan->decrementSubscribers();

            DB::commit();

            event(new SubscriptionCancelled($this));

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Subscription cancellation failed', [
                'subscription_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Resume cancelled subscription
     */
    public function resume(): bool
    {
        if (!$this->is_cancelled || $this->is_expired) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_ACTIVE,
            'cancel_at_period_end' => false,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'ends_at' => null,
            'auto_renew' => true,
        ]);

        // Increment plan subscriber count
        $this->plan->incrementSubscribers();

        return true;
    }

    /**
     * Expire subscription
     */
    public function expire(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
            'ends_at' => now(),
            'auto_renew' => false,
        ]);

        event(new SubscriptionExpired($this));
    }

    /**
     * Suspend subscription (for payment failure, violation)
     */
    public function suspend(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_SUSPENDED,
            'metadata' => array_merge($this->metadata ?? [], [
                'suspended_at' => now()->toIso8601String(),
                'suspension_reason' => $reason,
            ]),
            'auto_renew' => false,
        ]);
    }

    /**
     * Unsuspend subscription
     */
    public function unsuspend(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'auto_renew' => true,
        ]);
    }

    /**
     * Change to a different plan
     */
    public function changePlan(Plan $newPlan, bool $prorated = true): bool
    {
        try {
            DB::beginTransaction();

            $oldPlan = $this->plan;

            // Calculate proration if upgrading
            $proration = $prorated ? $newPlan->calculateProration($this, $this->billing_cycle) : null;

            // Process payment for upgrade difference
            if ($proration && $proration['amount_due'] > 0) {
                $payment = $this->processUpgradePayment($proration['amount_due']);
                if (!$payment || !$payment->is_successful) {
                    DB::rollBack();
                    return false;
                }
            }

            // Update price based on billing cycle
            $newPrice = match($this->billing_cycle) {
                self::CYCLE_MONTHLY => $newPlan->price_monthly,
                self::CYCLE_QUARTERLY => $newPlan->price_quarterly,
                self::CYCLE_ANNUAL => $newPlan->price_annual,
                default => $newPlan->price_monthly,
            };

            // Update subscription
            $this->update([
                'plan_id' => $newPlan->id,
                'price' => $newPrice,
                'features_snapshot' => $newPlan->features,
                'next_payment_amount' => $newPrice,
                'metadata' => array_merge($this->metadata ?? [], [
                    'previous_plan_id' => $oldPlan->id,
                    'changed_at' => now()->toIso8601String(),
                    'proration' => $proration,
                ]),
            ]);

            // Update subscriber counts
            $oldPlan->decrementSubscribers();
            $newPlan->incrementSubscribers();

            DB::commit();

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Plan change failed', [
                'subscription_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Change billing cycle
     */
    public function changeBillingCycle(string $newCycle): bool
    {
        if (!in_array($newCycle, [self::CYCLE_MONTHLY, self::CYCLE_QUARTERLY, self::CYCLE_ANNUAL])) {
            return false;
        }

        $newPrice = match($newCycle) {
            self::CYCLE_MONTHLY => $this->plan->price_monthly,
            self::CYCLE_QUARTERLY => $this->plan->price_quarterly,
            self::CYCLE_ANNUAL => $this->plan->price_annual,
            default => $this->plan->price_monthly,
        };

        $newPeriodEnd = $this->calculatePeriodEnd(now(), $newCycle);

        $this->update([
            'billing_cycle' => $newCycle,
            'price' => $newPrice,
            'current_period_end' => $newPeriodEnd,
            'next_payment_at' => $newPeriodEnd,
            'next_payment_amount' => $newPrice,
        ]);

        return true;
    }

    /**
     * Calculate period end date
     */
    protected function calculatePeriodEnd(\DateTime $start, ?string $cycle = null): \DateTime
    {
        $cycle = $cycle ?? $this->billing_cycle;
        $end = (clone $start);

        return match($cycle) {
            self::CYCLE_MONTHLY => $end->modify('+1 month'),
            self::CYCLE_QUARTERLY => $end->modify('+3 months'),
            self::CYCLE_ANNUAL => $end->modify('+1 year'),
            default => $end->modify('+1 month'),
        };
    }

    /**
     * Process renewal payment (mock)
     */
    protected function processRenewalPayment(): ?Payment
    {
        // This would integrate with actual payment processing
        // Mock implementation
        return Payment::create([
            'user_id' => $this->user_id,
            'order_id' => null, // Subscription renewals may not have orders
            'gateway' => 'stripe',
            'status' => Payment::STATUS_CAPTURED,
            'amount' => $this->next_payment_amount,
            'payment_type' => Payment::TYPE_SUBSCRIPTION,
            'payment_method_id' => $this->payment_method_id,
        ]);
    }

    /**
     * Process upgrade payment (mock)
     */
    protected function processUpgradePayment(float $amount): ?Payment
    {
        return Payment::create([
            'user_id' => $this->user_id,
            'gateway' => 'stripe',
            'status' => Payment::STATUS_CAPTURED,
            'amount' => $amount,
            'payment_type' => Payment::TYPE_ONE_TIME,
            'payment_method_id' => $this->payment_method_id,
        ]);
    }

    /**
     * Track feature usage
     */
    public function trackUsage(string $feature, int $amount = 1): void
    {
        $usage = $this->usage_stats ?? [];
        $usage[$feature] = ($usage[$feature] ?? 0) + $amount;
        
        $this->update(['usage_stats' => $usage]);
    }

    /**
     * Get usage for feature
     */
    public function getUsage(string $feature): int
    {
        return $this->usage_stats[$feature] ?? 0;
    }

    /**
     * Check if feature limit reached
     */
    public function hasReachedLimit(string $feature): bool
    {
        $limit = $this->plan->getLimit($feature);
        
        // Unlimited
        if ($limit === -1 || $limit === true) {
            return false;
        }

        // Boolean features
        if (is_bool($limit)) {
            return !$limit;
        }

        // Numeric limits
        if (is_numeric($limit)) {
            return $this->getUsage($feature) >= $limit;
        }

        return false;
    }

    /**
     * ===============================================
     * ANALYTICS METHODS
     * ===============================================
     */

    /**
     * Get subscription analytics for user
     */
    public static function getAnalyticsForUser(int $userId): array
    {
        $subscriptions = self::forUser($userId)->get();

        return [
            'total_subscriptions' => $subscriptions->count(),
            'active_subscription' => $subscriptions->where('status', self::STATUS_ACTIVE)->first(),
            'total_spent' => $subscriptions->sum('price') * $subscriptions->sum('renewal_count'),
            'current_plan' => $subscriptions->where('status', self::STATUS_ACTIVE)->first()?->plan,
            'subscription_history' => $subscriptions->sortByDesc('created_at')->values(),
        ];
    }

    /**
     * Get overall subscription analytics
     */
    public static function getOverallAnalytics(int $days = 30): array
    {
        $subscriptions = self::where('created_at', '>=', now()->subDays($days))->get();

        return [
            'total_subscriptions' => $subscriptions->count(),
            'active_subscriptions' => self::active()->count(),
            'trial_subscriptions' => self::trial()->count(),
            'cancelled_subscriptions' => $subscriptions->where('status', self::STATUS_CANCELLED)->count(),
            'churn_rate' => $subscriptions->count() > 0
                ? ($subscriptions->where('status', self::STATUS_CANCELLED)->count() / $subscriptions->count()) * 100
                : 0,
            'mrr' => self::active()->sum('price'), // Monthly Recurring Revenue
            'arr' => self::active()->sum('price') * 12, // Annual Recurring Revenue
            'avg_subscription_length' => self::expired()->avg(function($sub) {
                return $sub->started_at->diffInDays($sub->ends_at);
            }),
            'by_billing_cycle' => $subscriptions->groupBy('billing_cycle')->map->count(),
        ];
    }

    /**
     * ===============================================
     * MODEL EVENTS
     * ===============================================
     */

    protected static function booted(): void
    {
        // Generate subscription number on creation
        static::creating(function (Subscription $subscription) {
            if (empty($subscription->subscription_number)) {
                $subscription->subscription_number = self::generateSubscriptionNumber();
            }

            // Set defaults
            $subscription->status = $subscription->status ?? self::STATUS_TRIAL;
            $subscription->billing_cycle = $subscription->billing_cycle ?? self::CYCLE_MONTHLY;
            $subscription->currency = $subscription->currency ?? 'USD';
            $subscription->renewal_count = $subscription->renewal_count ?? 0;
            $subscription->auto_renew = $subscription->auto_renew ?? true;
        });

        // Check for expiring trials and periods
        static::updated(function (Subscription $subscription) {
            // Convert trial to active when trial ends
            if ($subscription->status === self::STATUS_TRIAL && 
                $subscription->trial_ends_at && 
                now()->gte($subscription->trial_ends_at)) {
                $subscription->update(['status' => self::STATUS_ACTIVE]);
            }

            // Expire subscription if period ended
            if ($subscription->status === self::STATUS_ACTIVE &&
                $subscription->current_period_end &&
                now()->gt($subscription->current_period_end) &&
                !$subscription->auto_renew) {
                $subscription->expire();
            }
        });
    }

    /**
     * Generate unique subscription number
     */
    protected static function generateSubscriptionNumber(): string
    {
        return 'SUB-' . strtoupper(uniqid());
    }
}