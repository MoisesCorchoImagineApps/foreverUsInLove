<?php

namespace Database\Factories\Commerce;

use App\Models\Commerce\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commerce\Order>
 */
class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([
            Order::TYPE_GIFT,
            Order::TYPE_SUBSCRIPTION,
            Order::TYPE_COINS,
            Order::TYPE_FEATURE,
            Order::TYPE_BOOST
        ]);

        $status = fake()->randomElement([
            Order::STATUS_PENDING,
            Order::STATUS_PROCESSING,
            Order::STATUS_FULFILLED,
            Order::STATUS_COMPLETED,
            Order::STATUS_FAILED,
            Order::STATUS_CANCELLED,
            Order::STATUS_REFUNDED
        ]);

        // Generate realistic amounts based on order type
        $subtotal = $this->getSubtotalByType($type);
        $tax = round($subtotal * 0.08, 2); // 8% tax
        $discount = fake()->boolean(30) ? round($subtotal * fake()->randomFloat(2, 0.05, 0.25), 2) : 0;
        $total = $subtotal + $tax - $discount;

        $paymentStatus = $this->getPaymentStatusByOrderStatus($status);
        $fulfillmentStatus = $this->getFulfillmentStatusByOrderStatus($status);

        $createdAt = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'order_number' => 'ORD-' . strtoupper(fake()->unique()->bothify('######')),
            'user_id' => User::factory(),
            'type' => $type,
            'status' => $status,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
            'currency' => 'USD',
            'payment_status' => $paymentStatus,
            'fulfillment_status' => $fulfillmentStatus,
            'fulfillment_type' => fake()->randomElement([
                Order::FULFILLMENT_INSTANT,
                Order::FULFILLMENT_SCHEDULED,
                Order::FULFILLMENT_MANUAL
            ]),
            'fulfilled_at' => in_array($status, [Order::STATUS_FULFILLED, Order::STATUS_COMPLETED]) 
                ? fake()->dateTimeBetween($createdAt, 'now') 
                : null,
            'billing_address' => [
                'name' => fake()->name(),
                'address_line_1' => fake()->streetAddress(),
                'address_line_2' => fake()->boolean(30) ? fake()->secondaryAddress() : null,
                'city' => fake()->city(),
                'state' => fake()->stateAbbr(),
                'postal_code' => fake()->postcode(),
                'country' => fake()->countryCode(),
            ],
            'metadata' => [
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'referrer' => fake()->boolean(60) ? fake()->url() : null,
                'utm_source' => fake()->randomElement(['google', 'facebook', 'instagram', 'email', 'direct']),
                'utm_medium' => fake()->randomElement(['cpc', 'social', 'email', 'organic']),
                'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
                'browser' => fake()->randomElement(['Chrome', 'Safari', 'Firefox', 'Edge']),
                'os' => fake()->randomElement(['Windows', 'macOS', 'iOS', 'Android', 'Linux']),
            ],
            'fulfillment_data' => $this->getFulfillmentData($type, $status),
            'error_log' => $status === Order::STATUS_FAILED ? [
                [
                    'error' => fake()->randomElement([
                        'Payment processing failed',
                        'Insufficient inventory',
                        'Invalid billing address',
                        'Card declined',
                        'Network timeout'
                    ]),
                    'timestamp' => fake()->dateTimeBetween($createdAt, 'now')->format('Y-m-d H:i:s'),
                    'details' => fake()->sentence()
                ]
            ] : null,
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
            'admin_notes' => fake()->boolean(15) ? 'Admin: ' . fake()->sentence() : null,
            'retry_count' => $status === Order::STATUS_FAILED ? fake()->numberBetween(0, 3) : 0,
            'expires_at' => $status === Order::STATUS_PENDING 
                ? fake()->dateTimeBetween('now', '+24 hours') 
                : null,
            'cancelled_at' => $status === Order::STATUS_CANCELLED 
                ? fake()->dateTimeBetween($createdAt, 'now') 
                : null,
            'cancellation_reason' => $status === Order::STATUS_CANCELLED 
                ? fake()->randomElement([
                    'Customer request',
                    'Payment failed',
                    'Fraud detected',
                    'Out of stock',
                    'System error'
                ]) 
                : null,
            'refunded_at' => $status === Order::STATUS_REFUNDED 
                ? fake()->dateTimeBetween($createdAt, 'now') 
                : null,
            'refund_reason' => $status === Order::STATUS_REFUNDED 
                ? fake()->randomElement([
                    'Customer not satisfied',
                    'Technical issues',
                    'Billing dispute',
                    'Defective product',
                    'Cancelled by merchant'
                ]) 
                : null,
            'created_at' => $createdAt,
            'updated_at' => fake()->dateTimeBetween($createdAt, 'now'),
        ];
    }

    /**
     * Get subtotal amount based on order type
     */
    private function getSubtotalByType(string $type): float
    {
        return match($type) {
            Order::TYPE_GIFT => fake()->randomFloat(2, 5.99, 99.99),
            Order::TYPE_SUBSCRIPTION => fake()->randomFloat(2, 19.99, 199.99),
            Order::TYPE_COINS => fake()->randomFloat(2, 4.99, 149.99),
            Order::TYPE_FEATURE => fake()->randomFloat(2, 2.99, 49.99),
            Order::TYPE_BOOST => fake()->randomFloat(2, 1.99, 29.99),
            default => fake()->randomFloat(2, 9.99, 99.99),
        };
    }

    /**
     * Get payment status based on order status
     */
    private function getPaymentStatusByOrderStatus(string $orderStatus): string
    {
        return match($orderStatus) {
            Order::STATUS_PENDING => Order::PAYMENT_PENDING,
            Order::STATUS_PROCESSING => fake()->randomElement([Order::PAYMENT_AUTHORIZED, Order::PAYMENT_CAPTURED]),
            Order::STATUS_FULFILLED, Order::STATUS_COMPLETED => Order::PAYMENT_CAPTURED,
            Order::STATUS_FAILED => Order::PAYMENT_FAILED,
            Order::STATUS_CANCELLED => fake()->randomElement([Order::PAYMENT_PENDING, Order::PAYMENT_FAILED]),
            Order::STATUS_REFUNDED => Order::PAYMENT_REFUNDED,
            default => Order::PAYMENT_PENDING,
        };
    }

    /**
     * Get fulfillment status based on order status
     */
    private function getFulfillmentStatusByOrderStatus(string $orderStatus): string
    {
        return match($orderStatus) {
            Order::STATUS_PENDING => Order::FULFILLMENT_PENDING,
            Order::STATUS_PROCESSING => Order::FULFILLMENT_PROCESSING,
            Order::STATUS_FULFILLED, Order::STATUS_COMPLETED => Order::FULFILLMENT_FULFILLED,
            Order::STATUS_FAILED => Order::FULFILLMENT_FAILED,
            Order::STATUS_CANCELLED => Order::FULFILLMENT_PENDING,
            Order::STATUS_REFUNDED => Order::FULFILLMENT_FULFILLED, // Was fulfilled before refund
            default => Order::FULFILLMENT_PENDING,
        };
    }

    /**
     * Get fulfillment data based on type and status
     */
    private function getFulfillmentData(string $type, string $status): ?array
    {
        if (!in_array($status, [Order::STATUS_FULFILLED, Order::STATUS_COMPLETED])) {
            return null;
        }

        return match($type) {
            Order::TYPE_COINS => [
                'coins_added' => fake()->numberBetween(100, 5000),
                'bonus_coins' => fake()->numberBetween(0, 1000),
                'transaction_id' => 'coin_' . fake()->uuid(),
            ],
            Order::TYPE_SUBSCRIPTION => [
                'plan_id' => fake()->numberBetween(1, 10),
                'subscription_id' => 'sub_' . fake()->uuid(),
                'billing_cycle' => fake()->randomElement(['monthly', 'quarterly', 'annual']),
                'next_billing_date' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            ],
            Order::TYPE_GIFT => [
                'recipient_id' => fake()->numberBetween(1, 10000),
                'gift_message' => fake()->sentence(),
                'delivery_date' => fake()->dateTimeBetween('now', '+7 days')->format('Y-m-d'),
                'gift_type' => fake()->randomElement(['rose', 'diamond', 'heart', 'star']),
            ],
            Order::TYPE_BOOST => [
                'boost_type' => fake()->randomElement(['profile', 'super_like', 'rewind']),
                'boost_duration' => fake()->numberBetween(30, 1440), // minutes
                'activated_at' => fake()->dateTimeThisMonth()->format('Y-m-d H:i:s'),
            ],
            Order::TYPE_FEATURE => [
                'feature_type' => fake()->randomElement(['incognito', 'read_receipts', 'priority_likes']),
                'valid_until' => fake()->dateTimeBetween('+1 week', '+1 month')->format('Y-m-d'),
                'usage_count' => fake()->numberBetween(0, 100),
            ],
            default => ['fulfilled_at' => fake()->dateTimeThisMonth()->format('Y-m-d H:i:s')],
        };
    }

    /**
     * Gift order state
     */
    public function gift(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Order::TYPE_GIFT,
            'subtotal' => fake()->randomFloat(2, 5.99, 49.99),
            'fulfillment_type' => Order::FULFILLMENT_INSTANT,
        ]);
    }

    /**
     * Subscription order state
     */
    public function subscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Order::TYPE_SUBSCRIPTION,
            'subtotal' => fake()->randomFloat(2, 19.99, 199.99),
            'fulfillment_type' => Order::FULFILLMENT_SCHEDULED,
        ]);
    }

    /**
     * Coins order state
     */
    public function coins(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Order::TYPE_COINS,
            'subtotal' => fake()->randomFloat(2, 4.99, 149.99),
            'fulfillment_type' => Order::FULFILLMENT_INSTANT,
        ]);
    }

    /**
     * Feature order state
     */
    public function feature(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Order::TYPE_FEATURE,
            'subtotal' => fake()->randomFloat(2, 2.99, 49.99),
            'fulfillment_type' => Order::FULFILLMENT_INSTANT,
        ]);
    }

    /**
     * Boost order state
     */
    public function boost(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Order::TYPE_BOOST,
            'subtotal' => fake()->randomFloat(2, 1.99, 29.99),
            'fulfillment_type' => Order::FULFILLMENT_INSTANT,
        ]);
    }

    /**
     * Pending order state
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'fulfillment_status' => Order::FULFILLMENT_PENDING,
            'fulfilled_at' => null,
            'expires_at' => fake()->dateTimeBetween('now', '+24 hours'),
        ]);
    }

    /**
     * Processing order state
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PROCESSING,
            'payment_status' => fake()->randomElement([Order::PAYMENT_AUTHORIZED, Order::PAYMENT_CAPTURED]),
            'fulfillment_status' => Order::FULFILLMENT_PROCESSING,
            'fulfilled_at' => null,
        ]);
    }

    /**
     * Completed order state
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_CAPTURED,
            'fulfillment_status' => Order::FULFILLMENT_FULFILLED,
            'fulfilled_at' => fake()->dateTimeBetween($attributes['created_at'] ?? '-1 month', 'now'),
        ]);
    }

    /**
     * Failed order state
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_FAILED,
            'payment_status' => Order::PAYMENT_FAILED,
            'fulfillment_status' => Order::FULFILLMENT_FAILED,
            'error_log' => [
                [
                    'error' => fake()->randomElement([
                        'Payment declined by bank',
                        'Insufficient funds',
                        'Invalid card number',
                        'Card has expired',
                        'Fraud detection triggered'
                    ]),
                    'timestamp' => fake()->dateTimeThisMonth()->format('Y-m-d H:i:s'),
                    'error_code' => fake()->randomElement(['E001', 'E002', 'E003', 'E004', 'E005']),
                ]
            ],
            'retry_count' => fake()->numberBetween(1, 3),
        ]);
    }

    /**
     * Cancelled order state
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => fake()->randomElement([Order::PAYMENT_PENDING, Order::PAYMENT_FAILED]),
            'fulfillment_status' => Order::FULFILLMENT_PENDING,
            'cancelled_at' => fake()->dateTimeBetween($attributes['created_at'] ?? '-1 month', 'now'),
            'cancellation_reason' => fake()->randomElement([
                'Customer requested cancellation',
                'Payment processing timeout',
                'Inventory unavailable',
                'Fraud prevention',
                'Technical error'
            ]),
        ]);
    }

    /**
     * Refunded order state
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_REFUNDED,
            'payment_status' => Order::PAYMENT_REFUNDED,
            'fulfillment_status' => Order::FULFILLMENT_FULFILLED,
            'fulfilled_at' => fake()->dateTimeBetween($attributes['created_at'] ?? '-2 months', '-1 week'),
            'refunded_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'refund_reason' => fake()->randomElement([
                'Customer not satisfied with service',
                'Technical issues with product',
                'Billing error',
                'Accidental purchase',
                'Service not as described'
            ]),
        ]);
    }

    /**
     * High value order state
     */
    public function highValue(): static
    {
        return $this->state(fn (array $attributes) => [
            'subtotal' => fake()->randomFloat(2, 200, 1000),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'high_value_flag' => true,
                'fraud_check_score' => fake()->randomFloat(2, 0.1, 0.3),
                'vip_customer' => fake()->boolean(60),
            ]),
        ]);
    }

    /**
     * With discount state
     */
    public function withDiscount(): static
    {
        return $this->state(function (array $attributes) {
            $subtotal = $attributes['subtotal'] ?? 50.00;
            $discountPercent = fake()->randomFloat(2, 0.10, 0.50);
            $discount = round($subtotal * $discountPercent, 2);
            
            return [
                'discount' => $discount,
                'total' => $subtotal + ($attributes['tax'] ?? ($subtotal * 0.08)) - $discount,
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'discount_code' => fake()->randomElement(['SAVE20', 'FIRST10', 'WELCOME25', 'HOLIDAY30']),
                    'discount_type' => fake()->randomElement(['percentage', 'fixed_amount', 'promotional']),
                    'discount_campaign' => fake()->randomElement(['Black Friday', 'New User', 'Loyalty Reward', 'Flash Sale']),
                ]),
            ];
        });
    }

    /**
     * Expedited order state
     */
    public function expedited(): static
    {
        return $this->state(fn (array $attributes) => [
            'fulfillment_type' => Order::FULFILLMENT_INSTANT,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'expedited' => true,
                'priority_level' => fake()->randomElement(['high', 'urgent', 'critical']),
                'processing_time' => fake()->randomElement(['<1min', '<5min', '<15min']),
            ]),
        ]);
    }

    /**
     * Recurring order state (subscription-based)
     */
    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Order::TYPE_SUBSCRIPTION,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'is_recurring' => true,
                'billing_cycle' => fake()->randomElement(['monthly', 'quarterly', 'annual']),
                'next_billing_date' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
                'subscription_start_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            ]),
        ]);
    }

    /**
     * International order state
     */
    public function international(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => fake()->randomElement(['EUR', 'GBP', 'CAD', 'AUD', 'JPY']),
            'billing_address' => [
                'name' => fake()->name(),
                'address_line_1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->state(),
                'postal_code' => fake()->postcode(),
                'country' => fake()->randomElement(['CA', 'GB', 'AU', 'DE', 'FR', 'JP']),
            ],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'international' => true,
                'exchange_rate' => fake()->randomFloat(4, 0.5, 2.0),
                'local_amount' => fake()->randomFloat(2, 10, 500),
                'tax_rate' => fake()->randomFloat(3, 0.05, 0.25),
            ]),
        ]);
    }
}