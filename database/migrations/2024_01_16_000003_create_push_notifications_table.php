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
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->uuid('push_id')->primary();
            
            // Relationships
            $table->foreignUuid('notification_id')
                  ->constrained('notifications', 'notification_id')
                  ->onDelete('cascade')
                  ->comment('Parent notification reference');
            
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('User receiving the push notification');
            
            // Platform & Provider Configuration
            $table->enum('platform', ['android', 'ios', 'web', 'huawei'])
                  ->index()
                  ->comment('Target platform for push notification');
            
            $table->enum('provider', [
                'fcm', 'apns', 'hms', 'wns', 'pushy', 'onesignal'
            ])->index()
              ->comment('Push notification service provider');
            
            $table->enum('push_type', [
                'alert', 'badge', 'sound', 'silent', 'rich', 'interactive'
            ])->default('alert')
              ->index()
              ->comment('Type of push notification');
            
            $table->enum('priority', ['low', 'normal', 'high'])
                  ->default('normal')
                  ->index()
                  ->comment('Delivery priority level');
            
            // Delivery Statistics
            $table->integer('total_tokens')
                  ->default(0)
                  ->comment('Total device tokens targeted');
            
            $table->integer('sent_count')
                  ->default(0)
                  ->comment('Number of successfully sent notifications');
            
            $table->integer('failed_count')
                  ->default(0)
                  ->comment('Number of failed delivery attempts');
            
            $table->json('platform_results')
                  ->nullable()
                  ->comment('Detailed results by platform (encrypted)');
            
            // Content & Media
            $table->text('title')
                  ->comment('Push notification title');
            
            $table->text('body')
                  ->comment('Push notification body text');
            
            $table->string('image_url', 500)
                  ->nullable()
                  ->comment('Rich media image URL');
            
            $table->string('icon_url', 500)
                  ->nullable()
                  ->comment('Notification icon URL');
            
            $table->string('deep_link', 500)
                  ->nullable()
                  ->index()
                  ->comment('Deep link URL for app navigation');
            
            // Platform-Specific Features
            $table->integer('badge_count')
                  ->nullable()
                  ->comment('Badge count for iOS notifications');
            
            $table->string('sound', 100)
                  ->nullable()
                  ->comment('Notification sound file');
            
            $table->json('action_buttons')
                  ->nullable()
                  ->comment('Interactive action buttons configuration');
            
            $table->json('data')
                  ->nullable()
                  ->comment('Custom push notification payload (encrypted)');
            
            $table->json('metadata')
                  ->nullable()
                  ->comment('Additional metadata and analytics (encrypted)');
            
            // Delivery & Engagement Tracking
            $table->timestamp('sent_at')
                  ->nullable()
                  ->index()
                  ->comment('When push notification was sent');
            
            $table->timestamp('delivered_at')
                  ->nullable()
                  ->index()
                  ->comment('When push was delivered to device');
            
            $table->timestamp('opened_at')
                  ->nullable()
                  ->index()
                  ->comment('When user opened the notification');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Performance indexes
            $table->index(['user_id', 'platform']);
            $table->index(['user_id', 'push_type']);
            $table->index(['user_id', 'sent_at']);
            $table->index(['platform', 'provider']);
            $table->index(['platform', 'push_type']);
            $table->index(['provider', 'sent_at']);
            $table->index(['priority', 'created_at']);
            
            // Analytics indexes
            $table->index(['sent_count', 'failed_count']);
            $table->index(['total_tokens', 'sent_count']);
            $table->index(['platform', 'sent_at', 'opened_at']);
            $table->index(['user_id', 'opened_at', 'sent_at']);
            
            // Composite indexes for common queries
            $table->index(['user_id', 'platform', 'sent_at']);
            $table->index(['platform', 'provider', 'created_at']);
            $table->index(['push_type', 'priority', 'created_at']);
            $table->index(['user_id', 'push_type', 'opened_at']);
            
            // Engagement tracking
            $table->index(['opened_at', 'platform']);
            $table->index(['sent_at', 'delivered_at', 'opened_at']);
            
            // Deep link tracking
            $table->index(['deep_link', 'opened_at']);
            
            // Full-text search on push content
            $table->fullText(['title', 'body']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};