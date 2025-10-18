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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            
            // Subscription Identification
            $table->string('subscription_id')->unique(); // Internal subscription ID
            $table->uuid('uuid')->unique(); // External reference UUID
            
            // Relationships
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('plans')->onDelete('restrict');
            $table->foreignId('current_order_id')->nullable()->constrained('orders')->onDelete('set null');
            
            // Subscription Status & Lifecycle
            $table->enum('status', [
                'trial', 'active', 'past_due', 'cancelled', 'expired', 
                'paused', 'suspended', 'pending_cancellation'
            ])->default('trial')->index();
            
            $table->enum('billing_status', [
                'current', 'past_due', 'unpaid', 'cancelled', 'incomplete'
            ])->default('current')->index();
            
            // Trial Information
            $table->boolean('is_trial')->default(false)->index();
            $table->timestamp('trial_starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->integer('trial_days_remaining')->nullable();
            $table->boolean('trial_requires_payment_method')->default(true);
            
            // Billing Cycle & Dates
            $table->enum('billing_cycle', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])->index();
            $table->integer('billing_interval')->default(1); // Every X cycles
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('next_billing_date')->nullable()->index();
            
            // Pricing & Payment
            $table->decimal('price', 10, 2); // Current price
            $table->decimal('original_price', 10, 2)->nullable(); // Price when subscribed
            $table->string('currency', 3)->default('USD');
            $table->string('payment_method')->nullable();
            $table->string('gateway_subscription_id')->nullable(); // Stripe/PayPal sub ID
            
            // Usage & Limits (current period)
            $table->json('usage_limits')->nullable(); // Plan limits
            $table->json('current_usage')->nullable(); // Current period usage
            $table->decimal('usage_percentage', 5, 2)->default(0); // Overall usage %
            $table->boolean('usage_exceeded')->default(false);
            
            // Feature Access
            $table->json('enabled_features')->nullable(); // Currently enabled features
            $table->json('feature_usage')->nullable(); // Feature-specific usage
            $table->timestamp('features_last_updated_at')->nullable();
            
            // Lifecycle Timestamps
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('last_payment_at')->nullable();
            $table->timestamp('next_payment_due_at')->nullable();
            
            // Cancellation & Renewal
            $table->boolean('auto_renew')->default(true);
            $table->enum('cancellation_reason', [
                'user_request', 'payment_failed', 'fraud', 'violation', 
                'downgrade', 'upgrade', 'trial_ended', 'other'
            ])->nullable();
            $table->text('cancellation_note')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('scheduled_cancellation_at')->nullable();
            
            // Payment History & Billing
            $table->integer('successful_payments')->default(0);
            $table->integer('failed_payments')->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 10, 2)->default(0);
            $table->timestamp('last_billing_attempt')->nullable();
            $table->integer('consecutive_failed_payments')->default(0);
            
            // Dunning & Recovery
            $table->enum('dunning_status', ['none', 'soft', 'hard', 'final'])->default('none');
            $table->timestamp('dunning_started_at')->nullable();
            $table->integer('dunning_attempts')->default(0);
            $table->timestamp('next_dunning_attempt')->nullable();
            
            // Proration & Changes
            $table->decimal('proration_credit', 10, 2)->default(0);
            $table->json('pending_changes')->nullable(); // Scheduled plan changes
            $table->timestamp('pending_change_effective_date')->nullable();
            $table->json('change_history')->nullable(); // Plan change history
            
            // Discounts & Promotions
            $table->json('applied_discounts')->nullable();
            $table->string('coupon_code')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->timestamp('discount_expires_at')->nullable();
            
            // Enterprise & Custom Features
            $table->boolean('is_enterprise')->default(false);
            $table->json('custom_features')->nullable();
            $table->json('custom_limits')->nullable();
            $table->string('account_manager_id')->nullable();
            $table->json('sla_terms')->nullable();
            
            // Analytics & Engagement
            $table->integer('login_count_current_period')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->json('engagement_metrics')->nullable();
            $table->decimal('customer_satisfaction_score', 3, 2)->nullable();
            $table->integer('support_tickets_count')->default(0);
            
            // Notifications & Communication
            $table->json('notification_preferences')->nullable();
            $table->timestamp('last_notification_sent_at')->nullable();
            $table->boolean('renewal_reminder_sent')->default(false);
            $table->boolean('payment_reminder_sent')->default(false);
            
            // Risk & Compliance
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->json('compliance_flags')->nullable();
            $table->boolean('requires_manual_review')->default(false);
            
            // Integration & External Systems
            $table->json('external_ids')->nullable(); // Third-party system references
            $table->json('webhook_endpoints')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->json('sync_errors')->nullable();
            
            // Customer Success & Health
            $table->enum('health_score', ['excellent', 'good', 'fair', 'poor', 'critical'])->nullable();
            $table->json('health_indicators')->nullable();
            $table->timestamp('health_last_calculated_at')->nullable();
            $table->boolean('at_risk_of_churn')->default(false);
            $table->decimal('churn_probability', 5, 2)->nullable();
            
            // Metadata & Custom Data
            $table->json('metadata')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('tags')->nullable();
            
            // Audit & History
            $table->json('status_history')->nullable(); // Track all status changes
            $table->json('audit_trail')->nullable(); // Important events log
            
            // Standard Laravel timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Performance Indexes
            $table->index(['user_id', 'status']);
            $table->index(['plan_id', 'status']);
            $table->index(['status', 'billing_status']);
            $table->index(['next_billing_date']);
            $table->index(['trial_ends_at']);
            $table->index(['expires_at']);
            $table->index(['is_trial', 'status']);
            $table->index(['auto_renew', 'next_billing_date']);
            $table->index(['dunning_status', 'next_dunning_attempt']);
            $table->index(['billing_cycle', 'current_period_ends_at']);
            $table->index(['at_risk_of_churn', 'health_score']);
            $table->index(['gateway_subscription_id']);
            $table->index(['created_at', 'total_paid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};