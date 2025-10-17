<?php

namespace App\Models\Commerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Events\Commerce\PaymentProcessed;
use App\Events\Commerce\PaymentFailed;
use App\Events\Commerce\PaymentRefunded;

/**
 * Payment Model - Payment Transaction Management
 * 
 * Handles payment processing across multiple gateways with fraud detection
 * Supports: Stripe, PayPal, Apple Pay, Google Pay, Braintree, Square, Adyen
 * 
 * @property int $id
 * @property string $payment_number Unique identifier (PAY-XXXXXX)
 * @property int $user_id
 * @property int $order_id
 * @property string $gateway (stripe, paypal, apple_pay, google_pay, braintree, square, adyen)
 * @property string $gateway_transaction_id External transaction ID
 * @property string $payment_method_id Reference to stored payment method
 * @property string $status (pending, authorized, captured, failed, refunded, cancelled)
 * @property decimal $amount
 * @property decimal $fee Gateway processing fee
 * @property decimal $net_amount Amount after fees
 * @property string $currency Default USD
 * @property string $payment_type (one_time, recurring, subscription)
 * @property array $billing_details Customer billing info
 * @property array $card_details Last4, brand, expiry (encrypted)
 * @property array $gateway_response Raw gateway response
 * @property array $metadata Transaction context
 * @property array $fraud_check Fraud detection results
 * @property float $risk_score 0-100
 * @property bool $requires_3ds 3D Secure required
 * @property string $three_ds_status (not_required, pending, authenticated, failed)
 * @property string $three_ds_redirect_url
 * @property bool $is_test Test mode transaction
 * @property string $failure_code
 * @property string $failure_message
 * @property int $retry_count
 * @property datetime $authorized_at
 * @property datetime $captured_at
 * @property datetime $failed_at
 * @property datetime $refunded_at
 * @property decimal $refunded_amount
 * @property string $refund_reason
 * @property timestamps created_at, updated_at
 * @property softDeletes deleted_at
 */
class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payments';

    protected $fillable = [
        'payment_number',
        'user_id',
        'order_id',
        'gateway',
        'gateway_transaction_id',
        'payment_method_id',
        'status',
        'amount',
        'fee',
        'net_amount',
        'currency',
        'payment_type',
        'billing_details',
        'card_details',
        'gateway_response',
        'metadata',
        'fraud_check',
        'risk_score',
        'requires_3ds',
        'three_ds_status',
        'three_ds_redirect_url',
        'is_test',
        'failure_code',
        'failure_message',
        'retry_count',
        'authorized_at',
        'captured_at',
        'failed_at',
        'refunded_at',
        'refunded_amount',
        'refund_reason',
    ];

    protected $hidden = [
        'card_details',
        'gateway_response',
        'metadata',
        'fraud_check',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'risk_score' => 'float',
        'requires_3ds' => 'boolean',
        'is_test' => 'boolean',
        'retry_count' => 'integer',
        'billing_details' => 'encrypted:array',
        'card_details' => 'encrypted:array',
        'gateway_response' => 'encrypted:array',
        'metadata' => 'array',
        'fraud_check' => 'array',
        'authorized_at' => 'datetime',
        'captured_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Payment Gateways
    const GATEWAY_STRIPE = 'stripe';
    const GATEWAY_PAYPAL = 'paypal';
    const GATEWAY_APPLE_PAY = 'apple_pay';
    const GATEWAY_GOOGLE_PAY = 'google_pay';
    const GATEWAY_BRAINTREE = 'braintree';
    const GATEWAY_SQUARE = 'square';
    const GATEWAY_ADYEN = 'adyen';

    // Payment Status
    const STATUS_PENDING = 'pending';
    const STATUS_AUTHORIZED = 'authorized';
    const STATUS_CAPTURED = 'captured';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    // Payment Types
    const TYPE_ONE_TIME = 'one_time';
    const TYPE_RECURRING = 'recurring';
    const TYPE_SUBSCRIPTION = 'subscription';

    // 3D Secure Status
    const THREE_DS_NOT_REQUIRED = 'not_required';
    const THREE_DS_PENDING = 'pending';
    const THREE_DS_AUTHENTICATED = 'authenticated';
    const THREE_DS_FAILED = 'failed';

    // Fraud Detection Thresholds
    const FRAUD_HIGH_AMOUNT = 1000.00;
    const FRAUD_VELOCITY_LIMIT = 5; // transactions per hour
    const FRAUD_FAILED_ATTEMPTS = 3;
    const FRAUD_RISK_SCORE_LIMIT = 75.0;

    // Processing Fees (example Stripe rates)
    const FEE_PERCENTAGE = 0.029; // 2.9%
    const FEE_FIXED = 0.30; // $0.30

    /**
     * ===============================================
     * RELATIONSHIPS
     * ===============================================
     */

    /**
     * User who made the payment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Order this payment is for
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Payment method used
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Refund transactions
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
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
     * Scope for successful payments
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_AUTHORIZED, self::STATUS_CAPTURED]);
    }

    /**
     * Scope for failed payments
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for pending payments
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope by gateway
     */
    public function scopeByGateway(Builder $query, string $gateway): Builder
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Scope by payment type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('payment_type', $type);
    }

    /**
     * Scope for user's payments
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for high-risk payments
     */
    public function scopeHighRisk(Builder $query): Builder
    {
        return $query->where('risk_score', '>', self::FRAUD_RISK_SCORE_LIMIT);
    }

    /**
     * Scope for requiring 3DS
     */
    public function scopeRequires3DS(Builder $query): Builder
    {
        return $query->where('requires_3ds', true);
    }

    /**
     * Scope for test payments
     */
    public function scopeTest(Builder $query): Builder
    {
        return $query->where('is_test', true);
    }

    /**
     * Scope for production payments
     */
    public function scopeProduction(Builder $query): Builder
    {
        return $query->where('is_test', false);
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
     * Get formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    /**
     * Get formatted fee
     */
    public function getFormattedFeeAttribute(): string
    {
        return '$' . number_format($this->fee, 2);
    }

    /**
     * Get formatted net amount
     */
    public function getFormattedNetAmountAttribute(): string
    {
        return '$' . number_format($this->net_amount, 2);
    }

    /**
     * Check if payment is successful
     */
    public function getIsSuccessfulAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_AUTHORIZED, self::STATUS_CAPTURED]);
    }

    /**
     * Check if payment is captured
     */
    public function getIsCapturedAttribute(): bool
    {
        return $this->status === self::STATUS_CAPTURED;
    }

    /**
     * Check if payment is refunded
     */
    public function getIsRefundedAttribute(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    /**
     * Check if payment can be captured
     */
    public function getCanBeCapturedAttribute(): bool
    {
        return $this->status === self::STATUS_AUTHORIZED;
    }

    /**
     * Check if payment can be refunded
     */
    public function getCanBeRefundedAttribute(): bool
    {
        return $this->status === self::STATUS_CAPTURED &&
               $this->created_at->gt(now()->subDays(180)); // 180-day refund window
    }

    /**
     * Check if payment is high risk
     */
    public function getIsHighRiskAttribute(): bool
    {
        return $this->risk_score > self::FRAUD_RISK_SCORE_LIMIT;
    }

    /**
     * Get masked card number
     */
    public function getMaskedCardAttribute(): ?string
    {
        if (empty($this->card_details['last4'])) {
            return null;
        }

        return '**** **** **** ' . $this->card_details['last4'];
    }

    /**
     * ===============================================
     * BUSINESS LOGIC METHODS
     * ===============================================
     */

    /**
     * Calculate processing fee
     */
    public function calculateFee(): void
    {
        $this->fee = ($this->amount * self::FEE_PERCENTAGE) + self::FEE_FIXED;
        $this->net_amount = $this->amount - $this->fee;
        $this->save();
    }

    /**
     * Perform fraud check
     */
    public function performFraudCheck(): array
    {
        $checks = [
            'high_amount' => $this->amount >= self::FRAUD_HIGH_AMOUNT,
            'velocity_check' => $this->checkVelocity(),
            'failed_attempts' => $this->checkFailedAttempts(),
            'unusual_location' => $this->checkUnusualLocation(),
            'card_verification' => $this->verifyCard(),
        ];

        // Calculate risk score
        $riskScore = 0;
        $riskScore += $checks['high_amount'] ? 20 : 0;
        $riskScore += $checks['velocity_check'] ? 25 : 0;
        $riskScore += $checks['failed_attempts'] ? 30 : 0;
        $riskScore += $checks['unusual_location'] ? 15 : 0;
        $riskScore += $checks['card_verification'] ? 0 : 10;

        $this->update([
            'fraud_check' => $checks,
            'risk_score' => $riskScore,
            'requires_3ds' => $riskScore > 50, // Require 3DS for medium-high risk
        ]);

        return $checks;
    }

    /**
     * Check payment velocity (transactions per hour)
     */
    protected function checkVelocity(): bool
    {
        $recentPayments = self::forUser($this->user_id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $recentPayments >= self::FRAUD_VELOCITY_LIMIT;
    }

    /**
     * Check recent failed attempts
     */
    protected function checkFailedAttempts(): bool
    {
        $failedAttempts = self::forUser($this->user_id)
            ->failed()
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $failedAttempts >= self::FRAUD_FAILED_ATTEMPTS;
    }

    /**
     * Check for unusual location
     */
    protected function checkUnusualLocation(): bool
    {
        // Compare IP location with user's usual locations
        $currentIp = $this->metadata['ip'] ?? null;
        if (!$currentIp) return false;

        $userLocations = self::forUser($this->user_id)
            ->successful()
            ->whereNotNull('metadata->ip')
            ->pluck('metadata')
            ->pluck('ip')
            ->unique();

        return !$userLocations->contains($currentIp);
    }

    /**
     * Verify card details
     */
    protected function verifyCard(): bool
    {
        // Check CVV, expiry, address verification
        // This would integrate with actual gateway verification
        return !empty($this->card_details['cvv_check']) && 
               $this->card_details['cvv_check'] === 'pass';
    }

    /**
     * Authorize payment (reserve funds)
     */
    public function authorize(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        try {
            // Perform fraud check
            $this->performFraudCheck();

            // Check if high risk
            if ($this->is_high_risk) {
                Log::warning('High-risk payment detected', [
                    'payment_id' => $this->id,
                    'risk_score' => $this->risk_score,
                ]);
            }

            // Require 3DS if needed
            if ($this->requires_3ds && $this->three_ds_status !== self::THREE_DS_AUTHENTICATED) {
                $this->update([
                    'three_ds_status' => self::THREE_DS_PENDING,
                    'three_ds_redirect_url' => $this->generate3DSUrl(),
                ]);
                return false; // Wait for 3DS authentication
            }

            // Process with gateway (mock)
            $gatewayResponse = $this->processWithGateway('authorize');

            if ($gatewayResponse['success']) {
                $this->update([
                    'status' => self::STATUS_AUTHORIZED,
                    'gateway_transaction_id' => $gatewayResponse['transaction_id'],
                    'gateway_response' => $gatewayResponse,
                    'authorized_at' => now(),
                ]);

                return true;
            }

            throw new \Exception($gatewayResponse['error'] ?? 'Authorization failed');

        } catch (\Exception $e) {
            $this->fail($e->getMessage());
            return false;
        }
    }

    /**
     * Capture payment (complete transaction)
     */
    public function capture(): bool
    {
        if (!$this->can_be_captured) {
            return false;
        }

        try {
            // Process with gateway
            $gatewayResponse = $this->processWithGateway('capture');

            if ($gatewayResponse['success']) {
                $this->calculateFee();

                $this->update([
                    'status' => self::STATUS_CAPTURED,
                    'gateway_response' => array_merge($this->gateway_response ?? [], $gatewayResponse),
                    'captured_at' => now(),
                ]);

                // Update order payment status
                $this->order?->update(['payment_status' => Order::PAYMENT_CAPTURED]);

                event(new PaymentProcessed($this));

                return true;
            }

            throw new \Exception($gatewayResponse['error'] ?? 'Capture failed');

        } catch (\Exception $e) {
            $this->fail($e->getMessage());
            return false;
        }
    }

    /**
     * Refund payment
     */
    public function refund(?string $reason = null, ?float $amount = null): bool
    {
        if (!$this->can_be_refunded) {
            return false;
        }

        $refundAmount = $amount ?? $this->amount;

        try {
            DB::beginTransaction();

            // Process with gateway
            $gatewayResponse = $this->processWithGateway('refund', [
                'amount' => $refundAmount,
                'reason' => $reason,
            ]);

            if ($gatewayResponse['success']) {
                $this->update([
                    'status' => self::STATUS_REFUNDED,
                    'refunded_amount' => $refundAmount,
                    'refunded_at' => now(),
                    'refund_reason' => $reason,
                    'gateway_response' => array_merge($this->gateway_response ?? [], $gatewayResponse),
                ]);

                // Create refund record
                $this->refunds()->create([
                    'user_id' => $this->user_id,
                    'amount' => $refundAmount,
                    'reason' => $reason,
                    'gateway_refund_id' => $gatewayResponse['refund_id'] ?? null,
                ]);

                // Update order payment status
                $this->order?->update(['payment_status' => Order::PAYMENT_REFUNDED]);

                DB::commit();

                event(new PaymentRefunded($this));

                return true;
            }

            throw new \Exception($gatewayResponse['error'] ?? 'Refund failed');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment refund failed', [
                'payment_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Mark payment as failed
     */
    public function fail(string $reason, ?string $code = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_message' => $reason,
            'failure_code' => $code,
            'failed_at' => now(),
        ]);

        event(new PaymentFailed($this));
    }

    /**
     * Retry failed payment
     */
    public function retry(): bool
    {
        if ($this->status !== self::STATUS_FAILED) {
            return false;
        }

        if ($this->retry_count >= 3) {
            Log::warning('Max retry attempts reached', ['payment_id' => $this->id]);
            return false;
        }

        $this->increment('retry_count');
        
        $this->update([
            'status' => self::STATUS_PENDING,
            'failure_message' => null,
            'failure_code' => null,
        ]);

        return $this->authorize();
    }

    /**
     * Process with payment gateway (mock)
     */
    protected function processWithGateway(string $action, array $params = []): array
    {
        // This would integrate with actual payment gateways
        // Mock implementation for demonstration

        sleep(1); // Simulate network delay

        // Simulate success/failure
        $success = rand(1, 100) > 5; // 95% success rate

        if (!$success) {
            return [
                'success' => false,
                'error' => 'Payment declined',
                'error_code' => 'card_declined',
            ];
        }

        return [
            'success' => true,
            'transaction_id' => 'txn_' . uniqid(),
            'refund_id' => $action === 'refund' ? 'ref_' . uniqid() : null,
            'gateway' => $this->gateway,
            'action' => $action,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate 3D Secure URL (mock)
     */
    protected function generate3DSUrl(): string
    {
        return "https://3ds.gateway.com/authenticate?payment_id={$this->id}";
    }

    /**
     * ===============================================
     * ANALYTICS METHODS
     * ===============================================
     */

    /**
     * Get payment analytics
     */
    public static function getAnalytics(int $days = 30): array
    {
        $payments = self::byDateRange(now()->subDays($days), now())
            ->production()
            ->get();

        return [
            'total_payments' => $payments->count(),
            'successful_payments' => $payments->where('status', self::STATUS_CAPTURED)->count(),
            'failed_payments' => $payments->where('status', self::STATUS_FAILED)->count(),
            'total_revenue' => $payments->where('status', self::STATUS_CAPTURED)->sum('amount'),
            'total_fees' => $payments->where('status', self::STATUS_CAPTURED)->sum('fee'),
            'net_revenue' => $payments->where('status', self::STATUS_CAPTURED)->sum('net_amount'),
            'avg_transaction' => $payments->where('status', self::STATUS_CAPTURED)->avg('amount'),
            'success_rate' => $payments->count() > 0 
                ? ($payments->where('status', self::STATUS_CAPTURED)->count() / $payments->count()) * 100 
                : 0,
            'by_gateway' => $payments->groupBy('gateway')->map->count(),
            'high_risk_count' => $payments->where('risk_score', '>', self::FRAUD_RISK_SCORE_LIMIT)->count(),
            'three_ds_required' => $payments->where('requires_3ds', true)->count(),
            'refund_rate' => $payments->count() > 0
                ? ($payments->where('status', self::STATUS_REFUNDED)->count() / $payments->count()) * 100
                : 0,
        ];
    }

    /**
     * ===============================================
     * MODEL EVENTS
     * ===============================================
     */

    protected static function booted(): void
    {
        // Generate payment number on creation
        static::creating(function (Payment $payment) {
            if (empty($payment->payment_number)) {
                $payment->payment_number = self::generatePaymentNumber();
            }

            // Set defaults
            $payment->status = $payment->status ?? self::STATUS_PENDING;
            $payment->payment_type = $payment->payment_type ?? self::TYPE_ONE_TIME;
            $payment->currency = $payment->currency ?? 'USD';
            $payment->risk_score = $payment->risk_score ?? 0;
            $payment->requires_3ds = $payment->requires_3ds ?? false;
            $payment->three_ds_status = $payment->three_ds_status ?? self::THREE_DS_NOT_REQUIRED;
            $payment->is_test = $payment->is_test ?? config('app.env') !== 'production';
            $payment->retry_count = $payment->retry_count ?? 0;
        });
    }

    /**
     * Generate unique payment number
     */
    protected static function generatePaymentNumber(): string
    {
        return 'PAY-' . strtoupper(uniqid());
    }
}