<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create User Images Table Migration
 * 
 * Creates the user_images table for ForeverUsInLove dating application
 * Includes comprehensive photo management, moderation, and analytics features
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // File Information
            $table->string('filename'); // Original filename
            $table->string('path'); // Storage path
            $table->string('url'); // Public URL
            $table->string('thumbnail_path')->nullable(); // Thumbnail storage path
            $table->string('thumbnail_url')->nullable(); // Thumbnail public URL
            
            // Photo Organization
            $table->boolean('is_primary')->default(false); // Primary profile photo
            $table->unsignedTinyInteger('order')->default(0); // Display order
            
            // Moderation & Status
            $table->enum('status', [
                'active', 
                'pending_moderation', 
                'rejected', 
                'hidden'
            ])->default('pending_moderation');
            $table->text('rejection_reason')->nullable(); // Reason for rejection
            
            // Image Technical Properties
            $table->unsignedSmallInteger('width'); // Image width in pixels
            $table->unsignedSmallInteger('height'); // Image height in pixels
            $table->unsignedInteger('size_bytes'); // File size in bytes
            $table->string('mime_type', 50); // MIME type (image/jpeg, etc.)
            
            // Comprehensive Metadata (JSON)
            $table->json('metadata')->nullable(); // EXIF data, device info, etc.
            
            // AI Moderation Results
            $table->json('moderation_results')->nullable(); // AI analysis results
            $table->decimal('moderation_score', 4, 3)->nullable(); // 0.000 to 1.000
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by_user_id')->nullable()
                ->constrained('users')->onDelete('set null'); // Human moderator
            
            // Analytics & Engagement
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            
            // Standard Laravel Timestamps
            $table->timestamps();
            $table->softDeletes(); // Soft delete for content retention
            
            // Indexes for Performance
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'order']);
            $table->index(['user_id', 'is_primary']);
            $table->index('status');
            $table->index(['status', 'moderated_at']);
            $table->index('is_primary');
            $table->index('moderation_score');
            $table->index('moderated_at');
            $table->index('moderated_by_user_id');
            $table->index('created_at');
            
            // Composite indexes for common queries
            $table->index(['status', 'user_id', 'order']);
            $table->index(['status', 'is_primary', 'user_id']);
            
            // Unique constraint: only one primary photo per user
            $table->unique(['user_id', 'is_primary'], 'unique_primary_photo')
                ->where('is_primary', true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_images');
    }
};