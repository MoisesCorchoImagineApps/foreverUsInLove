<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            
            // Order Identification
            $table->string('order_number')->unique(); // Human-readable order number
            $table->uuid('uuid')->unique(); // UUID for external references
            
            // Relationships
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('recipient_id')->nullable()->constrained('users')->onDelete('set null'); // For gifts
            
            // Order Type & Items
            $table->enum('type', ['gift', 'subscription', 'coins', 'feature', 'boost'])->index();
            $table->json('items'); // Order line items with product details
            $table->integer('total_items')->default(1);
            
            // Financial Information
            $table->decimal('subtotal', 12, 2); // Before taxes and fees
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2); // Final amount
            $table->string('currency', 3)->default('USD');
            
            // Payment Information
            $table->enum('payment_status', [
                'pending', 'processing', 'paid', 'partially_paid', 
                'failed', 'cancelled', 'refunded', 'partially_refunded'
            ])->default('pending')->index();
            $table->string('payment_method')->nullable(); // stripe, paypal, etc.
            $table->string('payment_reference')->nullable(); // External payment ID
            
            // Order Status & Fulfillment
            $table->enum('status', [
                'draft', 'pending', 'confirmed', 'processing', 
                'shipped', 'delivered', 'completed', 'cancelled', 
                'refunded', 'failed'
            ])->default('pending')->index();
            
            $table->enum('fulfillment_status', [
                'unfulfilled', 'partial', 'fulfilled', 'shipped', 
                'delivered', 'returned', 'exchanged'
            ])->default('unfulfilled')->index();
            
            // Timestamps for Order Lifecycle
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // For temporary orders
            
            // Shipping & Delivery (rarely used for digital products)
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->string('shipping_method')->nullable();
            $table->string('tracking_number')->nullable();
            $table->json('tracking_info')->nullable();
            
            // Promotional & Discounts
            $table->json('applied_coupons')->nullable();
            $table->json('applied_discounts')->nullable();
            $table->string('referral_code')->nullable();
            $table->decimal('loyalty_points_used', 10, 2)->default(0);
            $table->decimal('loyalty_points_earned', 10, 2)->default(0);
            
            // Gift Features
            $table->boolean('is_gift')->default(false)->index();
            $table->text('gift_message')->nullable();
            $table->timestamp('gift_scheduled_for')->nullable();
            $table->boolean('gift_opened')->default(false);
            $table->timestamp('gift_opened_at')->nullable();
            
            // Risk & Fraud
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->enum('fraud_status', ['clean', 'review', 'suspicious', 'blocked'])->default('clean');
            $table->json('fraud_indicators')->nullable();
            
            // Customer Service
            $table->text('customer_notes')->nullable();
            $table->json('internal_notes')->nullable();
            $table->json('status_history')->nullable(); // Track status changes
            
            // Subscription Related (for subscription orders)
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->onDelete('set null');
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_token')->nullable(); // For future charges
            
            // Refund Information
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->json('refund_details')->nullable();
            $table->text('refund_reason')->nullable();
            $table->timestamp('refund_processed_at')->nullable();
            
            // Analytics & Attribution
            $table->string('source')->nullable(); // web, mobile, api
            $table->string('campaign')->nullable(); // Marketing campaign
            $table->string('medium')->nullable(); // organic, paid, email
            $table->json('utm_parameters')->nullable();
            $table->string('device_type')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('geolocation')->nullable();
            
            // Integration & API
            $table->json('external_ids')->nullable(); // Third-party system IDs
            $table->json('webhook_deliveries')->nullable(); // Webhook status
            $table->json('api_metadata')->nullable();
            
            // Metadata & Custom Fields
            $table->json('metadata')->nullable(); // Flexible additional data
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            
            // Audit Trail
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for Performance
            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
            $table->index(['payment_status', 'fulfillment_status']);
            $table->index(['is_gift', 'gift_scheduled_for']);
            $table->index(['created_at', 'total_amount']);
            $table->index(['fraud_status', 'risk_score']);
            $table->index(['source', 'campaign']);
            $table->index('order_number');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};