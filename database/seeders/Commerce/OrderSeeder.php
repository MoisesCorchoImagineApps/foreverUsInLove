<?php

namespace Database\Seeders\Commerce;

use App\Models\Commerce\Order;
use App\Models\Commerce\Product;
use App\Models\Commerce\Subscription;
use App\Models\User\User;
use Database\Factories\Models\Commerce\OrderFactory;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $products = Product::where('status', 'active')->get();
        $subscriptions = Subscription::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Creating orders with factory defaults.');
        }

        $this->command->info("Creating orders for {$users->count()} users...");

        // Create different types of orders with realistic distribution
        $this->createRealisticOrderDistribution($users, $products, $subscriptions);

        // Create specific order scenarios for testing
        $this->createSpecificOrderScenarios($users, $products, $subscriptions);

        $this->command->info('✅ Order seeding completed!');
    }

    /**
     * Create orders with realistic distribution across different types and statuses.
     */
    private function createRealisticOrderDistribution($users, $products, $subscriptions): void
    {
        $totalOrders = $users->count() * 5; // Average 5 orders per user
        
        // Distribution percentages
        $distributions = [
            'coins' => 45,      // 45% coin purchases
            'subscription' => 25, // 25% subscription orders
            'gift' => 15,       // 15% gift orders
            'boost' => 10,      // 10% boost orders
            'feature' => 5      // 5% other features
        ];

        foreach ($distributions as $type => $percentage) {
            $count = intval(($percentage / 100) * $totalOrders);
            
            for ($i = 0; $i < $count; $i++) {
                $user = $users->random();
                
                $orderData = [
                    'user_id' => $user->id,
                    'type' => $type
                ];

                // Add specific data based on order type
                switch ($type) {
                    case 'subscription':
                        if ($subscriptions->isNotEmpty()) {
                            $subscription = $subscriptions->where('user_id', $user->id)->first()
                                ?? $subscriptions->random();
                            $orderData['subscription_id'] = $subscription->id;
                        }
                        break;
                        
                    case 'gift':
                        $recipient = $users->where('id', '!=', $user->id)->random();
                        $orderData['recipient_id'] = $recipient->id;
                        $orderData['is_gift'] = true;
                        break;
                }

                // Create order with appropriate state
                $factory = Order::factory();
                
                // 80% completed orders, 15% pending/processing, 5% failed/cancelled
                $statusRand = rand(1, 100);
                if ($statusRand <= 80) {
                    $factory->completed();
                } elseif ($statusRand <= 95) {
                    $factory->processing();
                } else {
                    $factory->failed();
                }

                $factory->create($orderData);
            }
        }

        $this->command->info("   ✓ Created {$totalOrders} orders with realistic distribution");
    }

    /**
     * Create specific order scenarios for comprehensive testing.
     */
    private function createSpecificOrderScenarios($users, $products, $subscriptions): void
    {
        $testUsers = $users->take(10);

        // Large completed order with multiple items
        Order::factory()->completed()->create([
            'user_id' => $testUsers[0]->id,
            'type' => 'coins',
            'items' => [
                [
                    'product_id' => $products->where('attributes->category', 'coins')->first()?->id ?? 1,
                    'name' => '5000 Coins + 2000 Bonus',
                    'quantity' => 1,
                    'unit_price' => 149.99,
                    'total_price' => 149.99
                ]
            ],
            'subtotal' => 149.99,
            'total_amount' => 149.99,
            'metadata' => [
                'order_notes' => 'High-value coin purchase',
                'customer_type' => 'premium_user',
                'acquisition_channel' => 'mobile_app'
            ]
        ]);

        // Gift order with message and scheduling
        Order::factory()->completed()->create([
            'user_id' => $testUsers[1]->id,
            'recipient_id' => $testUsers[2]->id,
            'type' => 'gift',
            'is_gift' => true,
            'gift_message' => 'Happy Valentine\'s Day! Hope this brings a smile to your face 💖',
            'gift_scheduled_for' => now()->addDays(2),
            'gift_opened' => false,
            'items' => [
                [
                    'product_id' => $products->where('attributes->category', 'gifts')->first()?->id ?? 1,
                    'name' => 'Premium Gift Box',
                    'quantity' => 1,
                    'unit_price' => 4.99,
                    'total_price' => 4.99
                ]
            ],
            'subtotal' => 4.99,
            'total_amount' => 4.99,
            'metadata' => [
                'gift_occasion' => 'valentines_day',
                'delivery_method' => 'in_app_notification',
                'sender_anonymous' => false
            ]
        ]);

        // Failed order with payment issues
        Order::factory()->failed()->create([
            'user_id' => $testUsers[3]->id,
            'type' => 'subscription',
            'payment_status' => 'failed',
            'status' => 'failed',
            'failed_at' => now()->subHours(2),
            'items' => [
                [
                    'product_id' => $products->where('attributes->category', 'subscription')->first()?->id ?? 1,
                    'name' => 'Premium Monthly Subscription',
                    'quantity' => 1,
                    'unit_price' => 19.99,
                    'total_price' => 19.99
                ]
            ],
            'subtotal' => 19.99,
            'total_amount' => 19.99,
            'metadata' => [
                'failure_reason' => 'insufficient_funds',
                'payment_attempts' => 3,
                'retry_scheduled' => now()->addDays(1)->format('Y-m-d H:i:s')
            ]
        ]);

        // Refunded order
        Order::factory()->refunded()->create([
            'user_id' => $testUsers[4]->id,
            'type' => 'coins',
            'payment_status' => 'refunded',
            'status' => 'refunded',
            'refunded_amount' => 34.99,
            'refund_reason' => 'Customer requested refund within 24 hours',
            'refund_processed_at' => now()->subDays(1),
            'items' => [
                [
                    'product_id' => $products->where('attributes->category', 'coins')->first()?->id ?? 1,
                    'name' => '1000 Coins + 300 Bonus',
                    'quantity' => 1,
                    'unit_price' => 34.99,
                    'total_price' => 34.99
                ]
            ],
            'subtotal' => 34.99,
            'total_amount' => 34.99,
            'metadata' => [
                'refund_method' => 'original_payment_method',
                'refund_processing_time_hours' => 24,
                'customer_satisfaction' => 'resolved'
            ]
        ]);

        // High-risk order under review
        Order::factory()->create([
            'user_id' => $testUsers[5]->id,
            'type' => 'coins',
            'status' => 'pending',
            'payment_status' => 'pending',
            'risk_score' => 85.50,
            'fraud_status' => 'review',
            'fraud_indicators' => [
                'high_velocity_purchases' => true,
                'unusual_payment_method' => true,
                'mismatched_billing_info' => false,
                'vpn_detected' => true
            ],
            'items' => [
                [
                    'product_id' => $products->where('attributes->category', 'coins')->first()?->id ?? 1,
                    'name' => '2500 Coins + 750 Bonus',
                    'quantity' => 1,
                    'unit_price' => 79.99,
                    'total_price' => 79.99
                ]
            ],
            'subtotal' => 79.99,
            'total_amount' => 79.99,
            'metadata' => [
                'review_status' => 'pending_manual_review',
                'review_assigned_to' => 'fraud_team',
                'estimated_review_time' => '24-48 hours'
            ]
        ]);

        // Bulk order with multiple items
        Order::factory()->completed()->create([
            'user_id' => $testUsers[6]->id,
            'type' => 'boost',
            'total_items' => 5,
            'items' => [
                [
                    'product_id' => $products->where('attributes->category', 'boosts')->first()?->id ?? 1,
                    'name' => 'Profile Boost (30 min)',
                    'quantity' => 3,
                    'unit_price' => 2.99,
                    'total_price' => 8.97
                ],
                [
                    'product_id' => $products->where('attributes->category', 'boosts')->skip(1)->first()?->id ?? 2,
                    'name' => 'Super Boost (60 min)',
                    'quantity' => 2,
                    'unit_price' => 4.99,
                    'total_price' => 9.98
                ]
            ],
            'subtotal' => 18.95,
            'discount_amount' => 2.00, // Bulk discount
            'total_amount' => 16.95,
            'applied_discounts' => [
                [
                    'type' => 'bulk_discount',
                    'code' => 'BULK5',
                    'amount' => 2.00,
                    'percentage' => 10.5
                ]
            ],
            'metadata' => [
                'bulk_purchase' => true,
                'discount_applied' => 'automatic_bulk_discount',
                'usage_pattern' => 'power_user'
            ]
        ]);

        // International order with tax
        Order::factory()->completed()->create([
            'user_id' => $testUsers[7]->id,
            'type' => 'subscription',
            'currency' => 'EUR',
            'items' => [
                [
                    'product_id' => $products->where('attributes->category', 'subscription')->first()?->id ?? 1,
                    'name' => 'Premium Monthly Subscription (EUR)',
                    'quantity' => 1,
                    'unit_price' => 17.99,
                    'total_price' => 17.99
                ]
            ],
            'subtotal' => 17.99,
            'tax_amount' => 3.60, // 20% VAT
            'total_amount' => 21.59,
            'billing_address' => [
                'country' => 'DE',
                'city' => 'Berlin',
                'postal_code' => '10115',
                'vat_number' => 'DE123456789'
            ],
            'metadata' => [
                'tax_jurisdiction' => 'EU_VAT',
                'tax_rate' => 20.0,
                'currency_conversion' => [
                    'original_currency' => 'USD',
                    'original_amount' => 19.99,
                    'exchange_rate' => 0.90
                ]
            ]
        ]);

        // Subscription renewal order
        if ($subscriptions->isNotEmpty()) {
            $subscription = $subscriptions->first();
            
            Order::factory()->completed()->create([
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'type' => 'subscription',
                'is_recurring' => true,
                'items' => [
                    [
                        'product_id' => $products->where('attributes->category', 'subscription')->first()?->id ?? 1,
                        'name' => 'Premium Monthly Subscription - Renewal',
                        'quantity' => 1,
                        'unit_price' => 19.99,
                        'total_price' => 19.99
                    ]
                ],
                'subtotal' => 19.99,
                'total_amount' => 19.99,
                'metadata' => [
                    'order_type' => 'subscription_renewal',
                    'billing_cycle' => 'monthly',
                    'auto_renewal' => true,
                    'next_billing_date' => now()->addMonth()->format('Y-m-d')
                ]
            ]);
        }

        // Promotional order with coupon
        Order::factory()->completed()->create([
            'user_id' => $testUsers[8]->id,
            'type' => 'coins',
            'items' => [
                [
                    'product_id' => $products->where('attributes->category', 'coins')->first()?->id ?? 1,
                    'name' => 'Holiday Special - 1500 Coins + 600 Bonus',
                    'quantity' => 1,
                    'unit_price' => 39.99,
                    'total_price' => 39.99
                ]
            ],
            'subtotal' => 39.99,
            'discount_amount' => 10.00,
            'total_amount' => 29.99,
            'applied_coupons' => [
                [
                    'code' => 'HOLIDAY25',
                    'type' => 'percentage',
                    'value' => 25,
                    'amount' => 10.00
                ]
            ],
            'metadata' => [
                'promotional_campaign' => 'holiday_2024',
                'coupon_source' => 'email_newsletter',
                'first_time_buyer' => false
            ]
        ]);

        $this->command->info("   ✓ Created specific test order scenarios");
    }
}