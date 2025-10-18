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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic Relationship - What this product represents
            $table->morphs('productable'); // productable_type, productable_id
            
            // Basic Product Information
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('sku')->unique()->nullable(); // Stock Keeping Unit
            
            // Pricing Information
            $table->decimal('price', 12, 2); // Base price
            $table->decimal('sale_price', 12, 2)->nullable(); // Discounted price
            $table->decimal('cost_price', 12, 2)->nullable(); // Cost to company
            $table->string('currency', 3)->default('USD');
            $table->decimal('margin_percentage', 5, 2)->nullable(); // Profit margin
            
            // Digital vs Physical Product
            $table->boolean('is_digital')->default(true); // Most products are digital
            $table->boolean('requires_shipping')->default(false);
            $table->boolean('is_downloadable')->default(false);
            $table->json('download_files')->nullable(); // Digital assets
            
            // Subscription & Recurring
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurring_interval', [
                'daily', 'weekly', 'monthly', 'quarterly', 'yearly'
            ])->nullable();
            $table->integer('recurring_interval_count')->default(1); // Every X intervals
            $table->integer('trial_period_days')->nullable();
            $table->boolean('trial_requires_payment_method')->default(true);
            
            // Inventory Management
            $table->boolean('inventory_tracking')->default(false);
            $table->integer('stock_quantity')->nullable();
            $table->integer('reserved_quantity')->default(0); // In pending orders
            $table->integer('low_stock_threshold')->nullable();
            $table->boolean('allow_backorder')->default(false);
            $table->boolean('track_quantity')->default(false);
            
            // Physical Properties (rarely used for digital products)
            $table->decimal('weight', 8, 3)->nullable(); // in kg
            $table->json('dimensions')->nullable(); // length, width, height, unit
            $table->string('shipping_class')->nullable();
            
            // Product Status & Visibility
            $table->enum('status', ['active', 'inactive', 'draft', 'archived'])->default('draft')->index();
            $table->enum('visibility', [
                'public', 'private', 'hidden', 'members_only', 'tier_restricted'
            ])->default('public')->index();
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            
            // SEO & Marketing
            $table->string('slug')->unique()->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->json('meta_data')->nullable();
            
            // Media & Assets
            $table->json('images')->nullable(); // Product images URLs
            $table->string('featured_image')->nullable();
            $table->json('gallery_images')->nullable();
            $table->json('videos')->nullable(); // Product videos
            $table->json('documents')->nullable(); // Brochures, manuals, etc.
            
            // Categories & Classification
            $table->json('categories')->nullable(); // Product categories
            $table->json('tags')->nullable(); // Marketing tags
            $table->string('type')->nullable(); // Product type
            $table->string('brand')->nullable();
            $table->string('vendor')->nullable();
            
            // Pricing Rules & Restrictions
            $table->decimal('minimum_price', 12, 2)->nullable();
            $table->decimal('maximum_price', 12, 2)->nullable();
            $table->integer('minimum_quantity')->default(1);
            $table->integer('maximum_quantity')->nullable();
            $table->integer('quantity_step')->default(1); // Purchase in multiples
            
            // Access & Restrictions
            $table->json('access_restrictions')->nullable(); // Who can buy
            $table->json('geographic_restrictions')->nullable(); // Country/region limits
            $table->integer('age_restriction')->nullable(); // Minimum age
            $table->boolean('requires_verification')->default(false);
            $table->json('required_user_attributes')->nullable();
            
            // Bundling & Cross-selling
            $table->boolean('is_bundle')->default(false);
            $table->json('bundle_products')->nullable(); // Products in bundle
            $table->json('cross_sell_products')->nullable(); // Recommended products
            $table->json('up_sell_products')->nullable(); // Higher tier alternatives
            $table->json('related_products')->nullable();
            
            // Promotional & Discounting
            $table->boolean('is_on_sale')->default(false);
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->boolean('allow_coupons')->default(true);
            $table->json('promotional_badges')->nullable();
            
            // Launch & Lifecycle
            $table->timestamp('launch_date')->nullable();
            $table->timestamp('discontinue_date')->nullable();
            $table->enum('lifecycle_stage', [
                'development', 'pre_launch', 'active', 'mature', 'decline', 'discontinued'
            ])->default('development');
            
            // Customer Reviews & Ratings
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('rating_count')->default(0);
            $table->integer('review_count')->default(0);
            $table->boolean('allow_reviews')->default(true);
            $table->boolean('reviews_require_purchase')->default(true);
            
            // Analytics & Performance
            $table->integer('view_count')->default(0);
            $table->integer('purchase_count')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->json('performance_metrics')->nullable();
            
            // Fulfillment & Delivery
            $table->enum('fulfillment_method', [
                'instant', 'manual', 'batch', 'scheduled', 'third_party'
            ])->default('instant');
            $table->json('fulfillment_config')->nullable();
            $table->enum('delivery_method', [
                'digital', 'email', 'in_app', 'physical', 'pickup'
            ])->default('digital');
            $table->string('estimated_delivery_time')->nullable();
            
            // Customer Support & Policies
            $table->enum('support_level', [
                'none', 'basic', 'standard', 'premium', 'white_glove'
            ])->default('basic');
            $table->enum('refund_policy', [
                'no_refund', '7_day', '14_day', '30_day', '90_day', 'lifetime', 'custom'
            ])->default('30_day');
            $table->text('terms_of_service')->nullable();
            $table->json('warranty_info')->nullable();
            
            // Taxation & Compliance
            $table->string('tax_class')->nullable();
            $table->boolean('tax_exempt')->default(false);
            $table->json('tax_rates')->nullable(); // By jurisdiction
            $table->json('compliance_requirements')->nullable();
            
            // A/B Testing & Variations
            $table->json('variants')->nullable(); // Product variations
            $table->string('parent_product_id')->nullable(); // For variants
            $table->boolean('is_variant')->default(false);
            $table->json('variation_attributes')->nullable(); // Size, color, etc.
            
            // Integration & External Systems
            $table->json('external_ids')->nullable(); // Third-party system IDs
            $table->json('api_endpoints')->nullable(); // Integration points
            $table->json('webhook_urls')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            
            // Custom Fields & Flexibility
            $table->json('attributes')->nullable(); // Custom product attributes
            $table->json('metadata')->nullable(); // Additional flexible data
            $table->json('custom_fields')->nullable(); // User-defined fields
            
            // Audit & Versioning
            $table->integer('version')->default(1);
            $table->json('change_log')->nullable(); // Track product changes
            $table->timestamp('last_modified_at')->nullable();
            $table->string('last_modified_by')->nullable();
            
            // Standard Laravel timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Performance Indexes
            $table->index(['productable_type', 'productable_id']);
            $table->index(['status', 'visibility']);
            $table->index(['is_featured', 'sort_order']);
            $table->index(['is_on_sale', 'sale_starts_at', 'sale_ends_at']);
            $table->index(['price', 'currency']);
            $table->index(['average_rating', 'rating_count']);
            $table->index(['inventory_tracking', 'stock_quantity']);
            $table->index(['is_recurring', 'recurring_interval']);
            $table->index(['launch_date', 'lifecycle_stage']);
            $table->index(['created_at', 'total_revenue']);
            $table->index('sku');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};