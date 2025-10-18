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
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('blocker_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('User who initiated the block');
            
            $table->foreignId('blocked_id')
                  ->constrained('users')  
                  ->onDelete('cascade')
                  ->comment('User who is being blocked');
            
            // Block Configuration
            $table->enum('block_type', [
                'FULL', 'PARTIAL', 'SHADOW', 'TEMPORARY', 'MUTUAL', 'SELECTIVE'
            ])->default('FULL')
              ->comment('Type of block applied');
            
            $table->enum('reason', [
                'HARASSMENT', 'SPAM', 'INAPPROPRIATE_BEHAVIOR', 'FAKE_PROFILE',
                'OFFENSIVE_CONTENT', 'SAFETY_CONCERN', 'PRIVACY_VIOLATION',
                'UNWANTED_CONTACT', 'CATFISH', 'SCAMMER', 'VIOLENT_THREAT',
                'HATE_SPEECH', 'UNDERAGE', 'OTHER'
            ])->comment('Reason for blocking');
            
            $table->text('description')
                  ->nullable()
                  ->comment('Additional details about the block');
            
            // Temporal Configuration
            $table->timestamp('expires_at')
                  ->nullable()
                  ->comment('When temporary blocks expire (null = permanent)');
            
            $table->boolean('is_active')
                  ->default(true)
                  ->index()
                  ->comment('Whether the block is currently active');
            
            // Block Scope (for SELECTIVE type)
            $table->json('blocked_features')
                  ->nullable()
                  ->comment('Specific features blocked (messages, profile_view, matching, etc.)');
            
            // Administrative
            $table->boolean('is_admin_block')
                  ->default(false)
                  ->index()
                  ->comment('Whether this is an administrative block');
            
            $table->boolean('is_system_block')
                  ->default(false)
                  ->index()
                  ->comment('Whether this is an automated system block');
            
            $table->boolean('is_mutual')
                  ->default(false)
                  ->index()
                  ->comment('Whether this is a mutual block');
            
            // Metadata
            $table->json('metadata')
                  ->nullable()
                  ->comment('Additional block metadata and context');
            
            // Audit Trail
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('Admin who created the block (if admin block)');
            
            $table->timestamp('blocked_at')
                  ->useCurrent()
                  ->comment('When the block was initiated');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['blocker_id', 'blocked_id']);
            $table->index(['blocked_id', 'is_active']);
            $table->index(['blocker_id', 'is_active']);
            $table->index(['block_type', 'is_active']);
            $table->index(['expires_at', 'is_active']);
            $table->index(['is_admin_block', 'is_active']);
            $table->index(['is_system_block', 'is_active']);
            $table->index('blocked_at');
            
            // Composite indexes for common queries
            $table->index(['blocker_id', 'block_type', 'is_active']);
            $table->index(['blocked_id', 'block_type', 'is_active']);
            
            // Unique constraint to prevent duplicate active blocks
            $table->unique(['blocker_id', 'blocked_id', 'is_active'], 'unique_active_block');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};