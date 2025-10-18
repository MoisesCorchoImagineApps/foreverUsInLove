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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            
            // Wallet Ownership
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Balance Information
            $table->decimal('balance', 15, 2)->default(0); // Current spendable balance
            $table->decimal('bonus_balance', 15, 2)->default(0); // Promotional/bonus balance
            $table->decimal('total_balance', 15, 2)->storedAs('balance + bonus_balance'); // Computed total
            $table->decimal('pending_balance', 15, 2)->default(0); // Pending transactions
            $table->decimal('reserved_balance', 15, 2)->default(0); // Reserved/held funds
            
            // Currency & Limits
            $table->string('currency', 3)->default('USD');
            $table->decimal('daily_limit', 12, 2)->default(1000); // Daily spending limit
            $table->decimal('daily_spent', 12, 2)->default(0); // Today's spending
            $table->decimal('monthly_limit', 12, 2)->nullable(); // Monthly spending limit
            $table->decimal('monthly_spent', 12, 2)->default(0); // This month's spending
            $table->date('daily_spent_date')->default(DB::raw('CURRENT_DATE')); // Track daily reset
            
            // Transaction Totals & Statistics
            $table->decimal('total_spent', 15, 2)->default(0); // Lifetime spending
            $table->decimal('total_earned', 15, 2)->default(0); // Lifetime earnings/deposits
            $table->integer('transaction_count')->default(0); // Total number of transactions
            $table->decimal('avg_transaction_amount', 10, 2)->default(0); // Average transaction
            $table->timestamp('last_transaction_at')->nullable();
            
            // Account Status & Security
            $table->enum('status', ['active', 'suspended', 'frozen', 'limited'])->default('active')->index();
            $table->enum('verification_level', [
                'unverified', 'basic', 'enhanced', 'premium'
            ])->default('unverified')->index();
            
            // Risk Management
            $table->decimal('risk_score', 5, 2)->default(0); // 0-100 risk score
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->json('risk_factors')->nullable(); // Risk indicators
            
            // Lock & Restrictions
            $table->boolean('is_locked')->default(false)->index();
            $table->timestamp('locked_until')->nullable();
            $table->enum('locked_reason', [
                'user_request', 'suspicious_activity', 'fraud_detection',
                'compliance_review', 'chargeback_dispute', 'system_maintenance'
            ])->nullable();
            $table->text('lock_note')->nullable();
            
            // Auto-reload Configuration
            $table->boolean('auto_reload_enabled')->default(false);
            $table->decimal('auto_reload_threshold', 10, 2)->nullable(); // Reload when below
            $table->decimal('auto_reload_amount', 10, 2)->nullable(); // Amount to reload
            $table->string('auto_reload_payment_method')->nullable();
            $table->timestamp('last_auto_reload_at')->nullable();
            $table->integer('auto_reload_count')->default(0);
            
            // Spending Categories & Analytics
            $table->json('spending_categories')->nullable(); // Breakdown by category
            $table->json('monthly_spending_history')->nullable(); // Last 12 months
            $table->string('most_spent_category')->nullable();
            $table->decimal('avg_monthly_spending', 10, 2)->default(0);
            
            // Loyalty & Rewards
            $table->decimal('loyalty_points', 10, 2)->default(0);
            $table->enum('loyalty_tier', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze');
            $table->decimal('cashback_earned', 10, 2)->default(0);
            $table->decimal('cashback_pending', 10, 2)->default(0);
            
            // Security Preferences
            $table->boolean('transaction_pin_enabled')->default(false);
            $table->boolean('biometric_auth_enabled')->default(false);
            $table->boolean('spending_alerts_enabled')->default(true);
            $table->boolean('large_transaction_approval_required')->default(false);
            $table->decimal('large_transaction_threshold', 10, 2)->default(100);
            
            // Notification Preferences
            $table->boolean('balance_notifications')->default(true);
            $table->boolean('transaction_notifications')->default(true);
            $table->boolean('security_notifications')->default(true);
            $table->boolean('promotional_notifications')->default(false);
            $table->decimal('low_balance_alert_threshold', 10, 2)->default(10);
            
            // Payment Preferences
            $table->string('preferred_payment_method')->nullable();
            $table->json('saved_payment_methods')->nullable();
            $table->boolean('save_payment_methods')->default(false);
            
            // Compliance & Regulatory
            $table->boolean('tax_reporting_enabled')->default(false);
            $table->string('tax_jurisdiction')->nullable();
            $table->json('compliance_flags')->nullable();
            $table->timestamp('last_compliance_check')->nullable();
            
            // Enterprise Features (for high-value wallets)
            $table->boolean('is_enterprise')->default(false);
            $table->string('account_manager_id')->nullable();
            $table->json('custom_limits')->nullable();
            $table->boolean('priority_support')->default(false);
            $table->boolean('white_glove_service')->default(false);
            
            // External Integration
            $table->json('external_wallet_ids')->nullable(); // Third-party wallet IDs
            $table->json('connected_accounts')->nullable(); // Bank accounts, cards, etc.
            $table->timestamp('last_sync_at')->nullable();
            
            // Fraud Prevention
            $table->json('device_fingerprints')->nullable(); // Known devices
            $table->json('ip_whitelist')->nullable(); // Trusted IP addresses
            $table->json('suspicious_activity_log')->nullable();
            $table->integer('failed_transaction_attempts')->default(0);
            $table->timestamp('last_failed_attempt_at')->nullable();
            
            // Backup & Recovery
            $table->string('recovery_code_hash')->nullable();
            $table->timestamp('recovery_code_generated_at')->nullable();
            $table->boolean('backup_enabled')->default(false);
            $table->json('backup_configuration')->nullable();
            
            // Analytics & Insights
            $table->json('spending_patterns')->nullable(); // AI insights
            $table->json('behavioral_analysis')->nullable(); // Usage patterns
            $table->decimal('predicted_monthly_spend', 10, 2)->nullable();
            $table->enum('spending_trend', ['increasing', 'stable', 'decreasing'])->nullable();
            
            // Seasonal & Temporal Data
            $table->json('seasonal_spending')->nullable(); // Holiday/seasonal patterns
            $table->string('peak_spending_day')->nullable(); // Day of week
            $table->integer('peak_spending_hour')->nullable(); // Hour of day
            
            // Metadata & Custom Fields
            $table->json('metadata')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('tags')->nullable();
            
            // Audit & History
            $table->json('balance_history')->nullable(); // Balance snapshots
            $table->json('limit_change_history')->nullable(); // Limit modifications
            $table->json('status_change_history')->nullable(); // Status transitions
            
            // Standard Laravel timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Performance Indexes
            $table->unique('user_id'); // One wallet per user
            $table->index(['status', 'verification_level']);
            $table->index(['is_locked', 'locked_until']);
            $table->index(['balance', 'currency']);
            $table->index(['daily_limit', 'daily_spent']);
            $table->index(['risk_score', 'risk_level']);
            $table->index(['loyalty_tier', 'loyalty_points']);
            $table->index(['auto_reload_enabled', 'auto_reload_threshold']);
            $table->index(['last_transaction_at']);
            $table->index(['total_spent', 'total_earned']);
            $table->index(['created_at', 'total_balance']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};