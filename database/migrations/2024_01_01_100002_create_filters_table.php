<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name');
            $table->enum('category', [
                'basic', 'demographic', 'lifestyle', 'interest', 
                'relationship', 'premium', 'behavioral', 'custom'
            ])->index();
            $table->json('criteria');
            $table->json('weights');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('priority_order')->default(1);
            $table->integer('usage_count')->default(0);
            $table->integer('match_success_rate')->default(0); // percentage
            $table->timestamp('last_used_at')->nullable();
            $table->json('settings')->nullable();
            $table->json('compatibility_factors')->nullable();
            $table->json('exclusion_rules')->nullable();
            $table->json('advanced_options')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for better query performance
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'is_default']);
            $table->index(['user_id', 'priority_order']);
            $table->index(['category', 'is_active']);
            $table->index(['usage_count', 'match_success_rate']);
            $table->index('last_used_at');
            $table->index(['user_id', 'created_at']);

            // Unique constraint to ensure only one default filter per user per category
            $table->unique(['user_id', 'category', 'is_default'], 'unique_default_filter_per_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filters');
    }
};