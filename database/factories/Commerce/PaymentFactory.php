<?php

namespace Database\Factories\Commerce;

use App\Models\Commerce\Payment;
use App\Models\Commerce\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commerce\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gateway = fake()->randomElement([
            Payment::GATEWAY_STRIPE,
            Payment::GATEWAY_PAYPAL,
            Payment::GATEWAY_APPLE_PAY,
            Payment::GATEWAY_GOOGLE_PAY,
            Payment::GATEWAY_BRAINTREE,
            Payment::GATEWAY_SQUARE,
            Payment::GATEWAY_ADYEN
        ]);

        $status = fake()->randomElement([
            Payment::STATUS_PENDING,
            Payment::STATUS_AUTHORIZED,
            Payment::STATUS_CAPTURED,
            Payment::STATUS_FAILED,
            Payment::STATUS_REFUNDED,
            Payment::STATUS_CANCELLED
        ]);

        $amount = fake()->randomFloat(2, 5.99, 999.99);
        $fee = round($amount * Payment::FEE_PERCENTAGE + Payment::FEE_FIXED, 2);
        $netAmount = $amount - $fee;

        $createdAt = fake()->dateTimeBetween('-6 months', 'now');
        $cardBrands = ['Visa', 'Mastercard', 'American Express', 'Discover', 'Diners Club'];

        return [
            'payment_number' => 'PAY-' . strtoupper(fake()->unique()->bothify('########')),
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'gateway' => $gateway,
            'gateway_transaction_id' => $this->generateGatewayTransactionId($gateway),
            'payment_method_id' => fake()->randomElement(['pm_' . fake()->randomLetter() . fake()->numerify('##########'), null]),
            'status' => $status,
            'amount' => $amount,
            'fee' => $fee,
            'net_amount' => $netAmount,
            'currency' => 'USD',
            'payment_type' => fake()->randomElement([
                Payment::TYPE_ONE_TIME,
                Payment::TYPE_RECURRING,
                Payment::TYPE_SUBSCRIPTION
            ]),
            'billing_details' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'phone' => fake()->phoneNumber(),
                'address' => [
                    'line1' => fake()->streetAddress(),
                    'line2' => fake()->boolean(30) ? fake()->secondaryAddress() : null,
                    'city' => fake()->city(),
                    'state' => fake()->stateAbbr(),
                    'postal_code' => fake()->postcode(),
                    'country' => fake()->countryCode(),
                ],
            ],
            'card_details' => [
                'last4' => fake()->numerify('####'),
                'brand' => fake()->randomElement($cardBrands),
                'exp_month' => fake()->numberBetween(1, 12),
                'exp_year' => fake()->numberBetween(date('Y'), date('Y') + 10),
                'cvv_check' => fake()->randomElement(['pass', 'fail', 'unavailable']),
                'address_check' => fake()->randomElement(['pass', 'fail', 'unavailable']),
                'funding' => fake()->randomElement(['credit', 'debit', 'prepaid']),
                'country' => fake()->countryCode(),
                'fingerprint' => fake()->sha256(),
            ],
            'gateway_response' => $this->generateGatewayResponse($gateway, $status),
            'metadata' => [
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'device_fingerprint' => fake()->sha256(),
                'session_id' => fake()->uuid(),
                'referrer' => fake()->boolean(60) ? fake()->url() : null,
            ],
            'fraud_check' => $this->generateFraudCheck($amount),
            'risk_score' => fake()->randomFloat(1, 0, 100),
            'requires_3ds' => fake()->boolean(25),
            'three_ds_status' => fake()->randomElement([
                Payment::THREE_DS_NOT_REQUIRED,
                Payment::THREE_DS_AUTHENTICATED,
                Payment::THREE_DS_FAILED,
                Payment::THREE_DS_PENDING
            ]),
            'three_ds_redirect_url' => fake()->boolean(20) ? fake()->url() : null,
            'is_test' => fake()->boolean(10),
            'failure_code' => $status === Payment::STATUS_FAILED 
                ? fake()->randomElement(['card_declined', 'insufficient_funds', 'expired_card', 'incorrect_cvc', 'processing_error'])
                : null,
            'failure_message' => $status === Payment::STATUS_FAILED 
                ? fake()->randomElement([
                    'Your card was declined.',
                    'Your card has insufficient funds.',
                    'Your card has expired.',
                    'Your card\'s security code is incorrect.',
                    'An error occurred processing your payment.'
                ])
                : null,
            'retry_count' => $status === Payment::STATUS_FAILED ? fake()->numberBetween(0, 3) : 0,
            'authorized_at' => in_array($status, [Payment::STATUS_AUTHORIZED, Payment::STATUS_CAPTURED])
                ? fake()->dateTimeBetween($createdAt, 'now')
                : null,
            'captured_at' => $status === Payment::STATUS_CAPTURED
                ? fake()->dateTimeBetween($createdAt, 'now')
                : null,
            'failed_at' => $status === Payment::STATUS_FAILED
                ? fake()->dateTimeBetween($createdAt, 'now')
                : null,
            'refunded_at' => $status === Payment::STATUS_REFUNDED
                ? fake()->dateTimeBetween($createdAt, 'now')
                : null,
            'refunded_amount' => $status === Payment::STATUS_REFUNDED
                ? fake()->randomFloat(2, $amount * 0.1, $amount)
                : null,
            'refund_reason' => $status === Payment::STATUS_REFUNDED
                ? fake()->randomElement([
                    'Customer request',
                    'Fraudulent transaction',
                    'Duplicate charge',
                    'Product not received',
                    'Service not as described'
                ])
                : null,
            'created_at' => $createdAt,
            'updated_at' => fake()->dateTimeBetween($createdAt, 'now'),
        ];
    }

    /**
     * Generate gateway-specific transaction ID
     */
    private function generateGatewayTransactionId(string $gateway): string
    {
        return match($gateway) {
            Payment::GATEWAY_STRIPE => 'ch_' . fake()->randomLetter() . fake()->numerify('###################'),
            Payment::GATEWAY_PAYPAL => fake()->numerify('#############'),
            Payment::GATEWAY_APPLE_PAY => 'ap_' . fake()->bothify('##########'),
            Payment::GATEWAY_GOOGLE_PAY => 'gp_' . fake()->bothify('##########'),
            Payment::GATEWAY_BRAINTREE => fake()->uuid(),
            Payment::GATEWAY_SQUARE => 'sq_' . fake()->bothify('##########'),
            Payment::GATEWAY_ADYEN => fake()->numerify('################'),
            default => fake()->uuid(),
        };
    }

    /**
     * Generate gateway-specific response
     */
    private function generateGatewayResponse(string $gateway, string $status): array
    {
        $baseResponse = [
            'gateway' => $gateway,
            'status' => $status,
            'timestamp' => fake()->dateTimeThisMonth()->format('Y-m-d H:i:s'),
            'response_code' => $status === Payment::STATUS_CAPTURED ? '00' : '05',
            'response_message' => $status === Payment::STATUS_CAPTURED ? 'Approved' : 'Declined',
        ];

        return match($gateway) {
            Payment::GATEWAY_STRIPE => array_merge($baseResponse, [
                'balance_transaction' => 'txn_' . fake()->bothify('##################'),
                'network_status' => fake()->randomElement(['approved_by_network', 'declined_by_network']),
                'risk_level' => fake()->randomElement(['normal', 'elevated', 'highest']),
            ]),
            Payment::GATEWAY_PAYPAL => array_merge($baseResponse, [
                'correlation_id' => fake()->uuid(),
                'ack' => $status === Payment::STATUS_CAPTURED ? 'Success' : 'Failure',
                'payer_id' => fake()->bothify('##########'),
            ]),
            Payment::GATEWAY_APPLE_PAY => array_merge($baseResponse, [
                'device_id' => fake()->uuid(),
                'wallet_type' => 'apple_pay',
                'payment_network' => fake()->randomElement(['Visa', 'Mastercard', 'Amex']),
            ]),
            Payment::GATEWAY_GOOGLE_PAY => array_merge($baseResponse, [
                'google_transaction_id' => fake()->uuid(),
                'wallet_type' => 'google_pay',
                'payment_method_type' => 'CARD',
            ]),
            default => $baseResponse,
        };
    }

    /**
     * Generate fraud check results
     */
    private function generateFraudCheck(float $amount): array
    {
        return [
            'high_amount' => $amount >= Payment::FRAUD_HIGH_AMOUNT,
            'velocity_check' => fake()->boolean(15),
            'failed_attempts' => fake()->boolean(10),
            'unusual_location' => fake()->boolean(20),
            'card_verification' => fake()->boolean(85),
            'device_reputation' => fake()->randomElement(['trusted', 'neutral', 'suspicious']),
            'email_reputation' => fake()->randomElement(['trusted', 'neutral', 'suspicious']),
            'phone_reputation' => fake()->randomElement(['trusted', 'neutral', 'suspicious']),
            'billing_verification' => fake()->randomElement(['match', 'partial', 'no_match']),
        ];
    }

    /**
     * Stripe payment state
     */
    public function stripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => Payment::GATEWAY_STRIPE,
            'gateway_transaction_id' => 'ch_' . fake()->randomLetter() . fake()->numerify('###################'),
            'gateway_response' => array_merge($attributes['gateway_response'] ?? [], [
                'balance_transaction' => 'txn_' . fake()->bothify('##################'),
                'network_status' => 'approved_by_network',
                'risk_level' => 'normal',
            ]),
        ]);
    }

    /**
     * PayPal payment state
     */
    public function paypal(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => Payment::GATEWAY_PAYPAL,
            'gateway_transaction_id' => fake()->numerify('#############'),
            'gateway_response' => array_merge($attributes['gateway_response'] ?? [], [
                'correlation_id' => fake()->uuid(),
                'ack' => 'Success',
                'payer_id' => fake()->bothify('##########'),
            ]),
        ]);
    }

    /**
     * Apple Pay payment state
     */
    public function applePay(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => Payment::GATEWAY_APPLE_PAY,
            'gateway_transaction_id' => 'ap_' . fake()->bothify('##########'),
            'card_details' => array_merge($attributes['card_details'] ?? [], [
                'wallet_type' => 'apple_pay',
                'device_type' => fake()->randomElement(['iPhone', 'iPad', 'Mac', 'Apple Watch']),
            ]),
        ]);
    }

    /**
     * Google Pay payment state
     */
    public function googlePay(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => Payment::GATEWAY_GOOGLE_PAY,
            'gateway_transaction_id' => 'gp_' . fake()->bothify('##########'),
            'card_details' => array_merge($attributes['card_details'] ?? [], [
                'wallet_type' => 'google_pay',
                'device_type' => 'Android',
            ]),
        ]);
    }

    /**
     * Successful payment state
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_CAPTURED,
            'authorized_at' => fake()->dateTimeBetween($attributes['created_at'] ?? '-1 month', 'now'),
            'captured_at' => fake()->dateTimeBetween($attributes['created_at'] ?? '-1 month', 'now'),
            'risk_score' => fake()->randomFloat(1, 0, 30),
            'requires_3ds' => false,
            'three_ds_status' => Payment::THREE_DS_NOT_REQUIRED,
            'failure_code' => null,
            'failure_message' => null,
        ]);
    }

    /**
     * Failed payment state
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_FAILED,
            'authorized_at' => null,
            'captured_at' => null,
            'failed_at' => fake()->dateTimeBetween($attributes['created_at'] ?? '-1 month', 'now'),
            'failure_code' => fake()->randomElement([
                'card_declined',
                'insufficient_funds',
                'expired_card',
                'incorrect_cvc',
                'processing_error',
                'fraud_detected'
            ]),
            'failure_message' => fake()->randomElement([
                'Your card was declined by your bank.',
                'Your card has insufficient funds to complete this purchase.',
                'Your card has expired. Please update your payment method.',
                'The security code you entered is incorrect.',
                'We encountered an error processing your payment. Please try again.',
                'This transaction has been flagged for potential fraud.'
            ]),
            'retry_count' => fake()->numberBetween(1, 3),
            'risk_score' => fake()->randomFloat(1, 60, 100),
        ]);
    }

    /**
     * High risk payment state
     */
    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_score' => fake()->randomFloat(1, 75, 100),
            'requires_3ds' => true,
            'three_ds_status' => fake()->randomElement([
                Payment::THREE_DS_PENDING,
                Payment::THREE_DS_AUTHENTICATED,
                Payment::THREE_DS_FAILED
            ]),
            'fraud_check' => [
                'high_amount' => fake()->boolean(80),
                'velocity_check' => fake()->boolean(60),
                'failed_attempts' => fake()->boolean(40),
                'unusual_location' => fake()->boolean(70),
                'card_verification' => fake()->boolean(30),
                'device_reputation' => fake()->randomElement(['suspicious', 'neutral']),
                'email_reputation' => fake()->randomElement(['suspicious', 'neutral']),
            ],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'fraud_flags' => fake()->randomElements([
                    'high_velocity',
                    'unusual_location',
                    'suspicious_device',
                    'multiple_failed_attempts',
                    'high_amount'
                ], fake()->numberBetween(1, 3)),
            ]),
        ]);
    }

    /**
     * Refunded payment state
     */
    public function refunded(): static
    {
        return $this->state(function (array $attributes) {
            $originalAmount = $attributes['amount'] ?? 50.00;
            $refundAmount = fake()->randomFloat(2, $originalAmount * 0.1, $originalAmount);
            
            return [
                'status' => Payment::STATUS_REFUNDED,
                'authorized_at' => fake()->dateTimeBetween($attributes['created_at'] ?? '-2 months', '-1 month'),
                'captured_at' => fake()->dateTimeBetween($attributes['created_at'] ?? '-2 months', '-1 month'),
                'refunded_at' => fake()->dateTimeBetween('-1 month', 'now'),
                'refunded_amount' => $refundAmount,
                'refund_reason' => fake()->randomElement([
                    'Customer requested refund',
                    'Product was defective',
                    'Service not delivered',
                    'Billing error',
                    'Fraudulent transaction detected'
                ]),
            ];
        });
    }

    /**
     * Subscription payment state
     */
    public function subscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => Payment::TYPE_SUBSCRIPTION,
            'status' => Payment::STATUS_CAPTURED,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'subscription_id' => 'sub_' . fake()->bothify('##################'),
                'billing_cycle' => fake()->randomElement(['monthly', 'quarterly', 'annual']),
                'subscription_period' => fake()->randomElement(['trial', 'active', 'past_due']),
            ]),
        ]);
    }

    /**
     * Large amount payment state
     */
    public function largeAmount(): static
    {
        return $this->state(function (array $attributes) {
            $amount = fake()->randomFloat(2, 1000, 10000);
            $fee = round($amount * Payment::FEE_PERCENTAGE + Payment::FEE_FIXED, 2);
            
            return [
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $amount - $fee,
                'risk_score' => fake()->randomFloat(1, 40, 80),
                'requires_3ds' => fake()->boolean(70),
                'metadata' => array_merge($attributes['metadata'] ?? [], [
                    'high_value' => true,
                    'manual_review' => fake()->boolean(40),
                ]),
            ];
        });
    }

    /**
     * International payment state
     */
    public function international(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => fake()->randomElement(['EUR', 'GBP', 'CAD', 'AUD', 'JPY']),
            'billing_details' => array_merge($attributes['billing_details'] ?? [], [
                'address' => array_merge($attributes['billing_details']['address'] ?? [], [
                    'country' => fake()->randomElement(['CA', 'GB', 'AU', 'DE', 'FR', 'JP']),
                ]),
            ]),
            'card_details' => array_merge($attributes['card_details'] ?? [], [
                'country' => fake()->randomElement(['CA', 'GB', 'AU', 'DE', 'FR', 'JP']),
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'international' => true,
                'exchange_rate' => fake()->randomFloat(4, 0.5, 2.0),
                'converted_amount' => fake()->randomFloat(2, 10, 500),
            ]),
        ]);
    }

    /**
     * Test payment state
     */
    public function test(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_test' => true,
            'gateway_transaction_id' => 'test_' . fake()->bothify('##########'),
            'card_details' => array_merge($attributes['card_details'] ?? [], [
                'last4' => '4242',
                'brand' => 'Visa',
            ]),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'test_mode' => true,
                'test_scenario' => fake()->randomElement([
                    'successful_payment',
                    'declined_card',
                    'insufficient_funds',
                    'expired_card'
                ]),
            ]),
        ]);
    }

    /**
     * 3D Secure payment state
     */
    public function threeDSecure(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_3ds' => true,
            'three_ds_status' => fake()->randomElement([
                Payment::THREE_DS_AUTHENTICATED,
                Payment::THREE_DS_FAILED,
                Payment::THREE_DS_PENDING
            ]),
            'three_ds_redirect_url' => fake()->url(),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                '3ds_version' => fake()->randomElement(['1.0', '2.0', '2.1']),
                '3ds_authentication_flow' => fake()->randomElement(['challenge', 'frictionless']),
            ]),
        ]);
    }
}