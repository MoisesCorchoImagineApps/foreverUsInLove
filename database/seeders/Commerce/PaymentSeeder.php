<?php

namespace Database\Seeders\Commerce;

use App\Models\Commerce\Order;
use App\Models\Commerce\Payment;
use App\Models\User\User;
use Database\Factories\Models\Commerce\PaymentFactory;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orders = Order::all();
        $users = User::all();

        if ($orders->isEmpty()) {
            $this->command->warn('No orders found. Creating standalone payments for testing.');
            $this->createStandalonePayments($users);
            return;
        }

        $this->command->info("Creating payments for {$orders->count()} orders...");

        // Create payments for existing orders
        $this->createOrderPayments($orders);

        // Create additional standalone payments for testing
        $this->createStandalonePayments($users->take(10));

        // Create specific payment scenarios
        $this->createSpecificPaymentScenarios($orders, $users);

        $this->command->info('✅ Payment seeding completed!');
    }

    /**
     * Create payments for existing orders based on their status.
     */
    private function createOrderPayments($orders): void
    {
        foreach ($orders as $order) {
            $paymentData = [
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'currency' => $order->currency
            ];

            // Create payment based on order status
            $factory = Payment::factory();

            switch ($order->payment_status) {
                case 'paid':
                    $factory->succeeded();
                    break;
                case 'failed':
                    $factory->failed();
                    break;
                case 'refunded':
                    $factory->refunded();
                    $paymentData['refunded_amount'] = $order->refunded_amount ?? $order->total_amount;
                    break;
                case 'partially_refunded':
                    $factory->partiallyRefunded();
                    $paymentData['refunded_amount'] = $order->total_amount * 0.5;
                    break;
                case 'pending':
                default:
                    $factory->pending();
                    break;
            }

            // Set gateway based on order metadata or random
            $gateway = $this->determineGateway($order);
            $factory->$gateway();

            $factory->create($paymentData);
        }

        $this->command->info("   ✓ Created payments for {$orders->count()} orders");
    }

    /**
     * Create standalone payments not tied to orders.
     */
    private function createStandalonePayments($users): void
    {
        if ($users->isEmpty()) return;

        // Create wallet top-up payments
        foreach ($users->take(5) as $user) {
            Payment::factory()->stripe()->succeeded()->create([
                'user_id' => $user->id,
                'order_id' => null,
                'payable_type' => 'App\Models\Commerce\Wallet',
                'payable_id' => $user->wallet?->id ?? 1,
                'amount' => fake()->randomFloat(2, 10, 500),
                'metadata' => [
                    'payment_purpose' => 'wallet_topup',
                    'auto_reload' => fake()->boolean(30)
                ]
            ]);
        }

        $this->command->info("   ✓ Created standalone payments for wallet top-ups");
    }

    /**
     * Create specific payment scenarios for comprehensive testing.
     */
    private function createSpecificPaymentScenarios($orders, $users): void
    {
        $testUsers = $users->take(10);
        $testOrders = $orders->take(5);

        // High-value successful Stripe payment
        Payment::factory()->stripe()->succeeded()->create([
            'user_id' => $testUsers[0]->id,
            'order_id' => $testOrders[0]->id ?? null,
            'amount' => 999.99,
            'currency' => 'USD',
            'payment_method_details' => [
                'type' => 'card',
                'card' => [
                    'brand' => 'visa',
                    'last4' => '4242',
                    'exp_month' => 12,
                    'exp_year' => 2025,
                    'country' => 'US'
                ]
            ],
            'captured' => true,
            'captured_at' => now()->subMinutes(30),
            'metadata' => [
                'customer_type' => 'vip',
                'payment_source' => 'mobile_app',
                'processing_time_ms' => 1250
            ]
        ]);

        // Failed payment with retry attempts
        Payment::factory()->stripe()->failed()->create([
            'user_id' => $testUsers[1]->id,
            'order_id' => $testOrders[1]->id ?? null,
            'amount' => 19.99,
            'currency' => 'USD',
            'failure_code' => 'card_declined',
            'failure_message' => 'Your card was declined.',
            'decline_reason' => 'insufficient_funds',
            'retry_count' => 3,
            'next_retry_at' => now()->addHours(24),
            'retry_history' => [
                [
                    'attempt' => 1,
                    'attempted_at' => now()->subHours(48)->format('Y-m-d H:i:s'),
                    'result' => 'failed',
                    'error' => 'insufficient_funds'
                ],
                [
                    'attempt' => 2,
                    'attempted_at' => now()->subHours(24)->format('Y-m-d H:i:s'),
                    'result' => 'failed',
                    'error' => 'card_declined'
                ],
                [
                    'attempt' => 3,
                    'attempted_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
                    'result' => 'failed',
                    'error' => 'insufficient_funds'
                ]
            ],
            'metadata' => [
                'dunning_campaign' => 'soft_decline_recovery',
                'notification_sent' => true
            ]
        ]);

        // PayPal payment with 3D Secure
        Payment::factory()->paypal()->succeeded()->create([
            'user_id' => $testUsers[2]->id,
            'order_id' => $testOrders[2]->id ?? null,
            'amount' => 49.99,
            'currency' => 'USD',
            'requires_3d_secure' => true,
            'three_d_secure_status' => 'authenticated',
            'three_d_secure_details' => [
                'version' => '2.1.0',
                'authentication_flow' => 'challenge',
                'result' => 'authenticated',
                'liability_shift' => 'yes'
            ],
            'payment_method_details' => [
                'type' => 'paypal',
                'paypal' => [
                    'payer_email' => fake()->email(),
                    'payer_id' => 'PAYPAL' . fake()->numerify('########'),
                    'account_status' => 'verified'
                ]
            ],
            'metadata' => [
                'security_level' => 'high',
                '3ds_challenge_completed' => true
            ]
        ]);

        // Apple Pay transaction
        Payment::factory()->applePay()->succeeded()->create([
            'user_id' => $testUsers[3]->id,
            'order_id' => $testOrders[3]->id ?? null,
            'amount' => 9.99,
            'currency' => 'USD',
            'payment_method_details' => [
                'type' => 'apple_pay',
                'apple_pay' => [
                    'device_type' => 'iPhone',
                    'payment_network' => 'visa',
                    'last4' => '1234',
                    'country' => 'US'
                ]
            ],
            'processing_time_ms' => 850,
            'metadata' => [
                'platform' => 'ios',
                'device_id' => fake()->uuid(),
                'biometric_auth' => 'face_id'
            ]
        ]);

        // Google Pay transaction
        Payment::factory()->googlePay()->succeeded()->create([
            'user_id' => $testUsers[4]->id,
            'order_id' => $testOrders[4]->id ?? null,
            'amount' => 29.99,
            'currency' => 'USD',
            'payment_method_details' => [
                'type' => 'google_pay',
                'google_pay' => [
                    'device_type' => 'Android',
                    'payment_network' => 'mastercard',
                    'last4' => '5678',
                    'country' => 'US'
                ]
            ],
            'processing_time_ms' => 920,
            'metadata' => [
                'platform' => 'android',
                'device_id' => fake()->uuid(),
                'biometric_auth' => 'fingerprint'
            ]
        ]);

        // Refunded payment with full refund
        Payment::factory()->stripe()->refunded()->create([
            'user_id' => $testUsers[5]->id,
            'order_id' => $testOrders[0]->id ?? null,
            'amount' => 79.99,
            'currency' => 'USD',
            'refunded_amount' => 79.99,
            'refund_reason' => 'Customer requested refund within return period',
            'refund_history' => [
                [
                    'refund_id' => 'ref_' . fake()->bothify('##??##??##??'),
                    'amount' => 79.99,
                    'reason' => 'requested_by_customer',
                    'processed_at' => now()->subDays(1)->format('Y-m-d H:i:s'),
                    'status' => 'succeeded'
                ]
            ],
            'metadata' => [
                'refund_method' => 'original_payment_method',
                'refund_processing_days' => 1,
                'customer_service_ticket' => 'CS-2024-001234'
            ]
        ]);

        // Disputed payment with chargeback
        Payment::factory()->stripe()->create([
            'user_id' => $testUsers[6]->id,
            'order_id' => $testOrders[1]->id ?? null,
            'amount' => 149.99,
            'currency' => 'USD',
            'status' => 'disputed',
            'disputed' => true,
            'dispute_details' => [
                'dispute_id' => 'dp_' . fake()->bothify('##??##??##??'),
                'reason' => 'fraudulent',
                'status' => 'under_review',
                'amount' => 149.99,
                'currency' => 'USD',
                'created_at' => now()->subDays(5)->format('Y-m-d H:i:s'),
                'evidence_due_by' => now()->addDays(2)->format('Y-m-d H:i:s')
            ],
            'dispute_amount' => 149.99,
            'dispute_deadline' => now()->addDays(2),
            'dispute_status' => 'under_review',
            'metadata' => [
                'chargeback_reason' => 'fraud',
                'merchant_response_required' => true,
                'evidence_submitted' => false
            ]
        ]);

        // International payment with currency conversion
        Payment::factory()->stripe()->succeeded()->create([
            'user_id' => $testUsers[7]->id,
            'order_id' => $testOrders[2]->id ?? null,
            'amount' => 17.99,
            'currency' => 'EUR',
            'original_amount' => 19.99,
            'original_currency' => 'USD',
            'exchange_rate' => 0.8995,
            'fee_amount' => 0.89,
            'net_amount' => 17.10,
            'billing_details' => [
                'name' => fake()->name(),
                'email' => fake()->email(),
                'address' => [
                    'country' => 'DE',
                    'city' => 'Munich',
                    'line1' => fake()->streetAddress(),
                    'postal_code' => fake()->postcode()
                ]
            ],
            'metadata' => [
                'international_payment' => true,
                'currency_conversion_applied' => true,
                'region' => 'EU',
                'vat_applicable' => true
            ]
        ]);

        // Cryptocurrency payment
        Payment::factory()->create([
            'user_id' => $testUsers[8]->id,
            'order_id' => $testOrders[3]->id ?? null,
            'gateway' => 'crypto',
            'payment_method' => 'cryptocurrency',
            'amount' => 99.99,
            'currency' => 'USD',
            'status' => 'succeeded',
            'gateway_transaction_id' => 'btc_' . fake()->bothify('##??##??##??##??'),
            'payment_method_details' => [
                'type' => 'cryptocurrency',
                'crypto' => [
                    'currency' => 'BTC',
                    'amount' => '0.00234567',
                    'wallet_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
                    'transaction_hash' => fake()->sha256(),
                    'confirmations' => 6
                ]
            ],
            'processing_time_ms' => 45000, // Slower for crypto
            'metadata' => [
                'blockchain' => 'bitcoin',
                'network_fee' => 0.0001,
                'confirmation_required' => 6
            ]
        ]);

        // Wallet balance payment (internal transfer)
        Payment::factory()->create([
            'user_id' => $testUsers[9]->id,
            'order_id' => $testOrders[4]->id ?? null,
            'gateway' => 'wallet',
            'payment_method' => 'wallet_balance',
            'amount' => 24.99,
            'currency' => 'USD',
            'status' => 'succeeded',
            'captured' => true,
            'captured_at' => now()->subMinutes(5),
            'processing_time_ms' => 150, // Very fast for wallet
            'payment_method_details' => [
                'type' => 'wallet',
                'wallet' => [
                    'balance_before' => 100.00,
                    'balance_after' => 75.01,
                    'transaction_type' => 'debit'
                ]
            ],
            'metadata' => [
                'internal_transfer' => true,
                'wallet_transaction_id' => 'wt_' . fake()->bothify('##??##??##??'),
                'instant_processing' => true
            ]
        ]);

        $this->command->info("   ✓ Created specific payment test scenarios");
    }

    /**
     * Determine appropriate payment gateway based on order characteristics.
     */
    private function determineGateway($order): string
    {
        // Logic to determine gateway based on order amount, user location, etc.
        $amount = $order->total_amount;
        
        return match (true) {
            $amount > 500 => 'stripe', // High-value orders use Stripe
            $amount < 10 => fake()->randomElement(['applePay', 'googlePay']), // Mobile payments for small amounts
            $order->currency !== 'USD' => 'paypal', // International orders
            default => fake()->randomElement(['stripe', 'paypal', 'applePay', 'googlePay'])
        };
    }
}