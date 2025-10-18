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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('short_description')->nullable();
            $table->enum('tier', ['basic', 'premium', 'vip', 'elite'])->index();
            
            // Pricing Structure
            $table->json('pricing'); // Multiple billing cycles with prices
            $table->string('currency', 3)->default('USD');
            $table->decimal('setup_fee', 10, 2)->default(0);
            
            // Subscription Configuration
            $table->enum('billing_cycle', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->integer('billing_interval')->default(1); // Every X cycles
            $table->integer('trial_period_days')->default(0);
            $table->boolean('trial_requires_payment_method')->default(true);
            
            // Features & Limits
            $table->json('features'); // List of included features
            $table->json('limits'); // Usage limits and quotas
            $table->json('permissions'); // What user can do
            
            // Business Rules
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_recommended')->default(false);
            $table->boolean('allow_downgrades')->default(true);
            $table->boolean('allow_upgrades')->default(true);
            $table->boolean('allow_cancellation')->default(true);
            
            // Promotional
            $table->boolean('is_promotional')->default(false);
            $table->timestamp('promotion_starts_at')->nullable();
            $table->timestamp('promotion_ends_at')->nullable();
            $table->json('promotion_details')->nullable(); // Discount info, special offers
            
            // Grace Periods & Retention
            $table->integer('grace_period_days')->default(3); // Before suspension
            $table->integer('retention_period_days')->default(30); // Before deletion
            $table->json('cancellation_flow')->nullable(); // Custom cancellation steps
            
            // Analytics & Tracking
            $table->json('analytics_config')->nullable(); // What to track
            $table->json('conversion_tracking')->nullable();
            $table->decimal('target_conversion_rate', 5, 2)->nullable();
            
            // Marketing
            $table->json('marketing_materials')->nullable(); // Images, copy, etc.
            $table->json('targeting_rules')->nullable(); // Who can see this plan
            $table->integer('sort_order')->default(0);
            
            // SEO & Content
            $table->string('slug')->unique()->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->json('seo_keywords')->nullable();
            
            // Enterprise Features (for elite plans)
            $table->boolean('requires_approval')->default(false);
            $table->boolean('custom_contract')->default(false);
            $table->json('enterprise_features')->nullable();
            
            // API & Integration
            $table->string('stripe_price_id')->nullable();
            $table->string('paypal_plan_id')->nullable();
            $table->json('third_party_ids')->nullable(); // Other payment processors
            
            // Status & Availability
            $table->enum('status', ['active', 'inactive', 'draft', 'archived'])->default('active');
            $table->enum('availability', ['public', 'private', 'invite_only', 'grandfathered'])->default('public');
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['tier', 'status']);
            $table->index(['is_popular', 'is_recommended']);
            $table->index(['billing_cycle', 'status']);
            $table->index(['promotion_starts_at', 'promotion_ends_at']);
            $table->index(['available_from', 'available_until']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};