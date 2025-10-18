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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            
            // Payment Identification
            $table->string('payment_id')->unique(); // Internal payment ID
            $table->uuid('uuid')->unique(); // External reference UUID
            $table->string('transaction_id')->nullable(); // Gateway transaction ID
            
            // Relationships
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->morphs('payable'); // Polymorphic - what was paid for
            
            // Payment Gateway Information
            $table->enum('gateway', [
                'stripe', 'paypal', 'apple_pay', 'google_pay', 
                'bank_transfer', 'crypto', 'wallet', 'manual'
            ])->index();
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->string('gateway_customer_id')->nullable();
            $table->string('gateway_payment_method_id')->nullable();
            
            // Payment Method Details
            $table->enum('payment_method', [
                'credit_card', 'debit_card', 'paypal', 'bank_transfer',
                'apple_pay', 'google_pay', 'cryptocurrency', 'wallet_balance',
                'gift_card', 'store_credit'
            ])->index();
            
            $table->json('payment_method_details')->nullable(); // Card last 4, PayPal email, etc.
            
            // Amount Information
            $table->decimal('amount', 12, 2); // Payment amount
            $table->decimal('fee_amount', 10, 2)->default(0); // Gateway fees
            $table->decimal('net_amount', 12, 2); // Amount after fees
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 10, 4)->nullable(); // If currency conversion
            $table->decimal('original_amount', 12, 2)->nullable(); // In original currency
            $table->string('original_currency', 3)->nullable();
            
            // Payment Status & Processing
            $table->enum('status', [
                'pending', 'processing', 'requires_action', 'requires_confirmation',
                'succeeded', 'failed', 'cancelled', 'refunded', 'partially_refunded',
                'disputed', 'chargeback'
            ])->default('pending')->index();
            
            $table->enum('capture_method', ['automatic', 'manual'])->default('automatic');
            $table->boolean('captured')->default(false);
            $table->timestamp('captured_at')->nullable();
            
            // Authorization & Security
            $table->string('authorization_code')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('authorization_expires_at')->nullable();
            
            // 3D Secure & Authentication
            $table->boolean('requires_3d_secure')->default(false);
            $table->enum('three_d_secure_status', [
                'not_required', 'pending', 'authenticated', 'failed', 'bypassed'
            ])->nullable();
            $table->json('three_d_secure_details')->nullable();
            
            // Fraud Detection & Risk
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->enum('fraud_status', ['clean', 'review', 'suspicious', 'blocked'])->default('clean');
            $table->json('fraud_details')->nullable();
            $table->json('fraud_indicators')->nullable();
            
            // Gateway Response & Errors
            $table->json('gateway_response')->nullable(); // Full gateway response
            $table->string('gateway_status')->nullable(); // Gateway-specific status
            $table->string('failure_code')->nullable();
            $table->string('failure_message')->nullable();
            $table->text('decline_reason')->nullable();
            
            // Webhook & Notifications
            $table->json('webhook_events')->nullable(); // Received webhook events
            $table->timestamp('last_webhook_at')->nullable();
            $table->json('notification_history')->nullable();
            
            // Refund Information
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->decimal('refundable_amount', 12, 2)->nullable(); // Max refundable
            $table->json('refund_history')->nullable();
            $table->text('refund_reason')->nullable();
            $table->timestamp('refund_deadline')->nullable();
            
            // Dispute & Chargeback
            $table->boolean('disputed')->default(false);
            $table->json('dispute_details')->nullable();
            $table->decimal('dispute_amount', 12, 2)->nullable();
            $table->timestamp('dispute_deadline')->nullable();
            $table->enum('dispute_status', ['open', 'under_review', 'won', 'lost'])->nullable();
            
            // Recurring & Subscription
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_token')->nullable(); // For future payments
            $table->integer('installment_number')->nullable();
            $table->integer('total_installments')->nullable();
            
            // Customer Information (at time of payment)
            $table->json('billing_details')->nullable();
            $table->string('customer_ip')->nullable();
            $table->string('customer_user_agent')->nullable();
            $table->json('device_fingerprint')->nullable();
            
            // Compliance & Regulations
            $table->json('compliance_data')->nullable(); // PCI, GDPR, etc.
            $table->boolean('requires_receipt')->default(true);
            $table->string('receipt_number')->nullable();
            $table->timestamp('receipt_sent_at')->nullable();
            
            // Analytics & Attribution
            $table->string('source')->nullable(); // Payment source/origin
            $table->json('utm_parameters')->nullable();
            $table->json('analytics_data')->nullable();
            
            // Processing Timeline
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('processing_time_ms')->nullable(); // Performance tracking
            
            // Retry Logic
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->json('retry_history')->nullable();
            $table->boolean('auto_retry_enabled')->default(true);
            
            // Integration & External Systems
            $table->json('external_references')->nullable(); // Other system IDs
            $table->json('custom_fields')->nullable();
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            
            // Audit & Logging
            $table->json('audit_trail')->nullable(); // Payment lifecycle events
            $table->timestamp('expires_at')->nullable(); // Payment window expiry
            
            // Standard Laravel timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Performance Indexes
            $table->index(['user_id', 'status']);
            $table->index(['gateway', 'status']);
            $table->index(['payment_method', 'created_at']);
            $table->index(['gateway_transaction_id']);
            $table->index(['fraud_status', 'risk_score']);
            $table->index(['is_recurring', 'recurring_token']);
            $table->index(['disputed', 'dispute_status']);
            $table->index(['captured', 'captured_at']);
            $table->index(['amount', 'currency']);
            $table->index(['created_at', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};