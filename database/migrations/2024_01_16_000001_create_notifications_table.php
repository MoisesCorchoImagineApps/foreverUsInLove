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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->unique();
            
            // Relationships
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('User receiving the notification');
            
            // Polymorphic relationship to any notifiable entity
            $table->string('notifiable_type', 100)
                  ->nullable()
                  ->index()
                  ->comment('Type of related entity (matches, messages, etc.)');
            
            $table->uuid('notifiable_id')
                  ->nullable()
                  ->index()
                  ->comment('ID of related entity');
            
            // Notification Classification
            $table->string('type', 50)
                  ->index()
                  ->comment('Notification type (new_match, message, etc.)');
            
            $table->json('channels')
                  ->comment('Delivery channels (push, email, sms, in_app)');
            
            $table->enum('priority', ['low', 'normal', 'high', 'urgent', 'critical'])
                  ->default('normal')
                  ->index()
                  ->comment('Notification priority level');
            
            $table->enum('status', [
                'queued', 'processing', 'sent', 'delivered', 
                'read', 'failed', 'cancelled', 'expired'
            ])->default('queued')
              ->index()
              ->comment('Current notification status');
            
            $table->string('category', 30)
                  ->nullable()
                  ->index()
                  ->comment('Notification category (dating, messaging, system, etc.)');
            
            // Content
            $table->string('title')
                  ->comment('Notification title/headline');
            
            $table->text('message')
                  ->comment('Notification message content');
            
            $table->json('data')
                  ->nullable()
                  ->comment('Custom notification data (encrypted)');
            
            // Delivery Tracking
            $table->json('delivery_results')
                  ->nullable()
                  ->comment('Results from each delivery channel (encrypted)');
            
            $table->json('preferences_snapshot')
                  ->nullable()
                  ->comment('User preferences at time of creation');
            
            // Campaign & Testing
            $table->string('template_id', 50)
                  ->nullable()
                  ->index()
                  ->comment('Template identifier used');
            
            $table->string('campaign_id', 50)
                  ->nullable()
                  ->index()
                  ->comment('Campaign identifier for marketing notifications');
            
            $table->string('ab_test_variant', 20)
                  ->nullable()
                  ->index()
                  ->comment('A/B test variant (A, B, C, control)');
            
            // Personalization
            $table->json('personalization')
                  ->nullable()
                  ->comment('Personalization data for dynamic content');
            
            // External References
            $table->string('reference_id', 100)
                  ->nullable()
                  ->index()
                  ->comment('External system reference ID');
            
            $table->json('metadata')
                  ->nullable()
                  ->comment('Additional metadata (encrypted)');
            
            // Timing & Scheduling
            $table->timestamp('scheduled_at')
                  ->nullable()
                  ->index()
                  ->comment('When notification should be sent');
            
            $table->timestamp('sent_at')
                  ->nullable()
                  ->index()
                  ->comment('When notification was actually sent');
            
            $table->timestamp('delivered_at')
                  ->nullable()
                  ->index()
                  ->comment('When notification was delivered');
            
            $table->timestamp('read_at')
                  ->nullable()
                  ->index()
                  ->comment('When notification was read by user');
            
            $table->timestamp('clicked_at')
                  ->nullable()
                  ->index()
                  ->comment('When notification was clicked/interacted with');
            
            $table->timestamp('expires_at')
                  ->nullable()
                  ->index()
                  ->comment('When notification expires');
            
            // Error Handling & Retry
            $table->integer('retry_count')
                  ->default(0)
                  ->comment('Number of retry attempts made');
            
            $table->text('failure_reason')
                  ->nullable()
                  ->comment('Reason for failure if status is failed');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance optimization
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'read_at']);
            $table->index(['type', 'status']);
            $table->index(['category', 'status']);
            $table->index(['priority', 'status']);
            $table->index(['created_at', 'status']);
            $table->index(['scheduled_at', 'status']);
            
            // Composite indexes for common queries
            $table->index(['user_id', 'status', 'priority']);
            $table->index(['user_id', 'type', 'status']);
            $table->index(['user_id', 'category', 'read_at']);
            $table->index(['campaign_id', 'ab_test_variant', 'status']);
            $table->index(['status', 'scheduled_at', 'created_at']);
            $table->index(['type', 'category', 'created_at']);
            
            // Polymorphic relationship index
            $table->index(['notifiable_type', 'notifiable_id']);
            
            // Full-text search on content
            $table->fullText(['title', 'message']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};