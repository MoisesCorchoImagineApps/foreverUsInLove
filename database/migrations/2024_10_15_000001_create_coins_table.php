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
        Schema::create('coins', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('tier')->index(); // starter, popular, value, premium, elite
            
            // Coin Details
            $table->integer('amount'); // Base coin amount
            $table->integer('bonus_amount')->default(0); // Bonus coins
            $table->integer('total_amount')->storedAs('amount + bonus_amount'); // Computed column
            
            // Pricing
            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('value_per_coin', 8, 4)->storedAs('price / (amount + bonus_amount)'); // Computed
            
            // Regional Pricing
            $table->json('regional_pricing')->nullable(); // Different prices by region
            
            // Promotional
            $table->boolean('is_promotional')->default(false);
            $table->timestamp('promotion_starts_at')->nullable();
            $table->timestamp('promotion_ends_at')->nullable();
            $table->decimal('promotion_discount_percentage', 5, 2)->nullable();
            
            // Business Logic
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_gift_eligible')->default(true);
            $table->integer('sort_order')->default(0);
            
            // Purchase Restrictions
            $table->integer('max_purchases_per_user')->nullable(); // Purchase limits
            $table->integer('max_purchases_per_day')->nullable();
            $table->json('availability_schedule')->nullable(); // Time-based availability
            
            // Analytics & Marketing
            $table->json('tags')->nullable(); // Marketing tags
            $table->json('metadata')->nullable(); // Additional data
            $table->string('sku')->unique()->nullable();
            
            // Status & Visibility
            $table->enum('status', ['active', 'inactive', 'draft', 'archived'])->default('active');
            $table->enum('visibility', ['public', 'private', 'members_only'])->default('public');
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['tier', 'status']);
            $table->index(['is_popular', 'is_featured']);
            $table->index(['promotion_starts_at', 'promotion_ends_at']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coins');
    }
};