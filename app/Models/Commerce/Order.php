<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Events\Commerce\OrderCreated;
use App\Events\Commerce\OrderProcessed;
use App\Events\Commerce\OrderFulfilled;
use App\Events\Commerce\OrderCompleted;
use App\Events\Commerce\OrderCancelled;
use App\Events\Commerce\OrderRefunded;

/**
 * Order Model - Purchase Order Management
 * 
 * Manages the complete order lifecycle from creation to fulfillment
 * Supports multiple order types: gifts, subscriptions, coins, features, boosts
 * 
 * @property int $id
 * @property string $order_number Unique order identifier (ORD-XXXXXX)
 * @property int $user_id
 * @property string $type (gift, subscription, coins, feature, boost)
 * @property string $status (pending, processing, fulfilled, completed, failed, cancelled, refunded)
 * @property decimal $subtotal
 * @property decimal $tax
 * @property decimal $discount
 * @property decimal $total
 * @property string $currency Default USD
 * @property string $payment_status (pending, authorized, captured, failed, refunded)
 * @property string $fulfillment_status (pending, processing, fulfilled, failed, partial)
 * @property string $fulfillment_type (instant, scheduled, manual)
 * @property datetime $fulfilled_at
 * @property array $billing_address
 * @property array $metadata Order context (ip, user_agent, etc)
 * @property array $fulfillment_data Fulfillment details
 * @property array $error_log Error tracking
 * @property string $notes Customer notes
 * @property string $admin_notes Internal notes
 * @property int $retry_count For failed fulfillments
 * @property datetime $expires_at For time-limited orders
 * @property datetime $cancelled_at
 * @property string $cancellation_reason
 * @property datetime $refunded_at
 * @property string $refund_reason
 * @property timestamps created_at, updated_at
 * @property softDeletes deleted_at
 */
class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'order_number',
        'user_id',
        'type',
        'status',
        'subtotal',
        'tax',
        'discount',
        'total',
        'currency',
        'payment_status',
        'fulfillment_status',
        'fulfillment_type',
        'fulfilled_at',
        'billing_address',
        'metadata',
        'fulfillment_data',
        'error_log',
        'notes',
        'admin_notes',
        'retry_count',
        'expires_at',
        'cancelled_at',
        'cancellation_reason',
        'refunded_at',
        'refund_reason',
    ];

    protected $hidden = [
        'metadata',
        'error_log',
        'admin_notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'billing_address' => 'array',
        'metadata' => 'array',
        'fulfillment_data' => 'array',
        'error_log' => 'array',
        'retry_count' => 'integer',
        'fulfilled_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Order Types
    const TYPE_GIFT = 'gift';
    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_COINS = 'coins';
    const TYPE_FEATURE = 'feature';
    const TYPE_BOOST = 'boost';

    // Order Status
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_FULFILLED = 'fulfilled';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    // Payment Status
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_AUTHORIZED = 'authorized';
    const PAYMENT_CAPTURED = 'captured';
    const PAYMENT_FAILED = 'failed';
    const PAYMENT_REFUNDED = 'refunded';

    // Fulfillment Status
    const FULFILLMENT_PENDING = 'pending';
    const FULFILLMENT_PROCESSING = 'processing';
    const FULFILLMENT_FULFILLED = 'fulfilled';
    const FULFILLMENT_FAILED = 'failed';
    const FULFILLMENT_PARTIAL = 'partial';

    // Fulfillment Type
    const FULFILLMENT_INSTANT = 'instant';
    const FULFILLMENT_SCHEDULED = 'scheduled';
    const FULFILLMENT_MANUAL = 'manual';

    // Max Chats Limits
    const MAX_CHATS_FREE = 50;
    const MAX_CHATS_PREMIUM = 200;

    /**
     * ===============================================
     * RELATIONSHIPS
     * ===============================================
     */

    /**
     * User who placed the order
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Order line items
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Payment for this order
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Invoice for this order
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * ===============================================
     * SCOPES
     * ===============================================
     */

    /**
     * Scope by order type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for pending orders
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for processing orders
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope for fulfilled orders
     */
    public function scopeFulfilled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FULFILLED);
    }

    /**
     * Scope for completed orders
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed orders
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for paid orders
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', self::PAYMENT_CAPTURED);
    }

    /**
     * Scope for user's orders
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for recent orders
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for expired orders
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope by date range
     */
    public function scopeByDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * ===============================================
     * ACCESSORS & MUTATORS
     * ===============================================
     */

    /**
     * Get formatted total
     */
    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format($this->total, 2);
    }

    /**
     * Get formatted subtotal
     */
    public function getFormattedSubtotalAttribute(): string
    {
        return '$' . number_format($this->subtotal, 2);
    }

    /**
     * Check if order is paid
     */
    public function getIsPaidAttribute(): bool
    {
        return $this->payment_status === self::PAYMENT_CAPTURED;
    }

    /**
     * Check if order is fulfilled
     */
    public function getIsFulfilledAttribute(): bool
    {
        return $this->fulfillment_status === self::FULFILLMENT_FULFILLED;
    }

    /**
     * Check if order is completed
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if order can be cancelled
     */
    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING
        ]) && !$this->is_fulfilled;
    }

    /**
     * Check if order can be refunded
     */
    public function getCanBeRefundedAttribute(): bool
    {
        return $this->is_paid && 
               in_array($this->status, [self::STATUS_FULFILLED, self::STATUS_COMPLETED]) &&
               $this->created_at->gt(now()->subDays(30)); // 30-day refund window
    }

    /**
     * Check if order is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && now()->gt($this->expires_at);
    }

    /**
     * Get estimated fulfillment time
     */
    public function getEstimatedFulfillmentAttribute(): string
    {
        return match($this->type) {
            self::TYPE_GIFT, self::TYPE_COINS => 'Instant',
            self::TYPE_SUBSCRIPTION => '1-2 minutes',
            self::TYPE_FEATURE, self::TYPE_BOOST => '2-5 minutes',
            default => 'Variable',
        };
    }

    /**
     * ===============================================
     * BUSINESS LOGIC METHODS
     * ===============================================
     */

    /**
     * Calculate order totals
     */
    public function calculateTotals(): void
    {
        $this->subtotal = $this->items()->sum(DB::raw('quantity * price'));
        
        // Calculate tax (example: 10%)
        $this->tax = $this->subtotal * 0.10;
        
        // Apply discount if any
        $this->discount = $this->discount ?? 0;
        
        // Calculate total
        $this->total = $this->subtotal + $this->tax - $this->discount;
        
        $this->save();
    }

    /**
     * Add item to order
     */
    public function addItem(Product $product, int $quantity = 1, array $options = []): OrderItem
    {
        $item = $this->items()->create([
            'product_id' => $product->id,
            'product_type' => get_class($product->productable),
            'product_name' => $product->name,
            'quantity' => $quantity,
            'price' => $product->price,
            'subtotal' => $product->price * $quantity,
            'options' => $options,
        ]);

        $this->calculateTotals();

        return $item;
    }

    /**
     * Process the order
     */
    public function process(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_PROCESSING,
            'fulfillment_status' => self::FULFILLMENT_PROCESSING,
        ]);

        event(new OrderProcessed($this));

        return true;
    }

    /**
     * Fulfill the order
     */
    public function fulfill(): bool
    {
        if (!$this->is_paid) {
            $this->logError('Cannot fulfill unpaid order');
            return false;
        }

        try {
            DB::beginTransaction();

            // Fulfill each item
            foreach ($this->items as $item) {
                $item->fulfill();
            }

            $this->update([
                'status' => self::STATUS_FULFILLED,
                'fulfillment_status' => self::FULFILLMENT_FULFILLED,
                'fulfilled_at' => now(),
                'fulfillment_data' => [
                    'fulfilled_by' => 'system',
                    'fulfilled_at' => now()->toIso8601String(),
                    'items_fulfilled' => $this->items->count(),
                ],
            ]);

            DB::commit();

            event(new OrderFulfilled($this));

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->update([
                'status' => self::STATUS_FAILED,
                'fulfillment_status' => self::FULFILLMENT_FAILED,
            ]);

            $this->logError($e->getMessage());
            
            return false;
        }
    }

    /**
     * Complete the order
     */
    public function complete(): bool
    {
        if (!$this->is_fulfilled) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
        ]);

        event(new OrderCompleted($this));

        return true;
    }

    /**
     * Cancel the order
     */
    public function cancel(string $reason = null): bool
    {
        if (!$this->can_be_cancelled) {
            return false;
        }

        try {
            DB::beginTransaction();

            // Release inventory reservations
            foreach ($this->items as $item) {
                $item->product?->releaseInventory($item->quantity);
            }

            // Refund if already paid
            if ($this->is_paid) {
                $this->payment?->refund($reason ?? 'Order cancelled');
            }

            $this->update([
                'status' => self::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            DB::commit();

            event(new OrderCancelled($this));

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError($e->getMessage());
            return false;
        }
    }

    /**
     * Refund the order
     */
    public function refund(string $reason = null): bool
    {
        if (!$this->can_be_refunded) {
            return false;
        }

        try {
            DB::beginTransaction();

            // Process payment refund
            if ($this->payment && !$this->payment->refund($reason)) {
                throw new \Exception('Payment refund failed');
            }

            // Reverse fulfillment (e.g., deduct coins, cancel subscription)
            foreach ($this->items as $item) {
                $item->reverse();
            }

            $this->update([
                'status' => self::STATUS_REFUNDED,
                'payment_status' => self::PAYMENT_REFUNDED,
                'refunded_at' => now(),
                'refund_reason' => $reason,
            ]);

            DB::commit();

            event(new OrderRefunded($this));

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError($e->getMessage());
            return false;
        }
    }

    /**
     * Retry failed fulfillment
     */
    public function retryFulfillment(): bool
    {
        if ($this->status !== self::STATUS_FAILED) {
            return false;
        }

        if ($this->retry_count >= 3) {
            $this->logError('Max retry attempts reached');
            return false;
        }

        $this->increment('retry_count');
        
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'fulfillment_status' => self::FULFILLMENT_PROCESSING,
        ]);

        return $this->fulfill();
    }

    /**
     * Log error
     */
    public function logError(string $message): void
    {
        $errors = $this->error_log ?? [];
        
        $errors[] = [
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
        ];

        $this->update(['error_log' => $errors]);
    }

    /**
     * Get order summary
     */
    public function getSummary(): array
    {
        return [
            'order_number' => $this->order_number,
            'type' => $this->type,
            'status' => $this->status,
            'total' => $this->formatted_total,
            'items_count' => $this->items->count(),
            'payment_status' => $this->payment_status,
            'fulfillment_status' => $this->fulfillment_status,
            'created_at' => $this->created_at->toDateTimeString(),
            'fulfilled_at' => $this->fulfilled_at?->toDateTimeString(),
        ];
    }

    /**
     * Generate invoice
     */
    public function generateInvoice(): Invoice
    {
        if ($this->invoice) {
            return $this->invoice;
        }

        return $this->invoice()->create([
            'invoice_number' => self::generateInvoiceNumber(),
            'user_id' => $this->user_id,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'discount' => $this->discount,
            'total' => $this->total,
            'currency' => $this->currency,
            'billing_address' => $this->billing_address,
            'issued_at' => now(),
        ]);
    }

    /**
     * ===============================================
     * ANALYTICS METHODS
     * ===============================================
     */

    /**
     * Get order analytics for user
     */
    public static function getAnalyticsForUser(int $userId): array
    {
        $orders = self::forUser($userId)->get();

        return [
            'total_orders' => $orders->count(),
            'total_spent' => $orders->sum('total'),
            'avg_order_value' => $orders->avg('total'),
            'completed_orders' => $orders->where('status', self::STATUS_COMPLETED)->count(),
            'cancelled_orders' => $orders->where('status', self::STATUS_CANCELLED)->count(),
            'refunded_orders' => $orders->where('status', self::STATUS_REFUNDED)->count(),
            'by_type' => $orders->groupBy('type')->map->count(),
            'recent_orders' => $orders->sortByDesc('created_at')->take(5)->values(),
        ];
    }

    /**
     * Get overall order analytics
     */
    public static function getOverallAnalytics(int $days = 30): array
    {
        $orders = self::recent($days)->get();

        return [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->where('status', self::STATUS_COMPLETED)->sum('total'),
            'avg_order_value' => $orders->avg('total'),
            'completion_rate' => $orders->count() > 0 
                ? ($orders->where('status', self::STATUS_COMPLETED)->count() / $orders->count()) * 100 
                : 0,
            'cancellation_rate' => $orders->count() > 0
                ? ($orders->where('status', self::STATUS_CANCELLED)->count() / $orders->count()) * 100
                : 0,
            'by_type' => $orders->groupBy('type')->map->count(),
            'by_status' => $orders->groupBy('status')->map->count(),
            'fulfillment_stats' => [
                'instant' => $orders->where('fulfillment_type', self::FULFILLMENT_INSTANT)->count(),
                'avg_fulfillment_time' => $orders->whereNotNull('fulfilled_at')
                    ->avg(function($order) {
                        return $order->fulfilled_at->diffInSeconds($order->created_at);
                    }),
            ],
        ];
    }

    /**
     * ===============================================
     * MODEL EVENTS
     * ===============================================
     */

    protected static function booted(): void
    {
        // Generate order number on creation
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }

            // Set defaults
            $order->status = $order->status ?? self::STATUS_PENDING;
            $order->payment_status = $order->payment_status ?? self::PAYMENT_PENDING;
            $order->fulfillment_status = $order->fulfillment_status ?? self::FULFILLMENT_PENDING;
            $order->fulfillment_type = $order->fulfillment_type ?? self::FULFILLMENT_INSTANT;
            $order->currency = $order->currency ?? 'USD';
            $order->retry_count = $order->retry_count ?? 0;
        });

        // Dispatch event on creation
        static::created(function (Order $order) {
            event(new OrderCreated($order));
        });

        // Auto-complete fulfilled orders
        static::updated(function (Order $order) {
            if ($order->wasChanged('fulfillment_status') && 
                $order->fulfillment_status === self::FULFILLMENT_FULFILLED &&
                $order->status !== self::STATUS_COMPLETED) {
                $order->complete();
            }
        });
    }

    /**
     * Generate unique order number
     */
    protected static function generateOrderNumber(): string
    {
        return 'ORD-' . strtoupper(uniqid());
    }

    /**
     * Generate unique invoice number
     */
    protected static function generateInvoiceNumber(): string
    {
        return 'INV-' . strtoupper(uniqid());
    }
}