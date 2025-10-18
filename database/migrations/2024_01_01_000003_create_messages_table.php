<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Chat relationship
            $table->uuid('chat_id');
            $table->uuid('sender_id');
            
            // Message content
            $table->string('type'); // text, image, video, voice, file, sticker, gif, system, icebreaker
            $table->text('content')->nullable();
            $table->text('encrypted_content')->nullable();
            $table->json('attachments')->nullable(); // File attachments with metadata
            
            // Message threading and replies
            $table->uuid('reply_to_message_id')->nullable();
            $table->boolean('is_thread_starter')->default(false);
            $table->integer('thread_message_count')->default(0);
            $table->json('thread_participants')->nullable();
            
            // Delivery and status tracking
            $table->string('status')->default('sent'); // sent, delivered, read, failed, deleted
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('read_by')->nullable(); // Array of user_id => timestamp
            $table->json('delivery_status')->nullable(); // Per-user delivery status
            
            // Encryption and security
            $table->boolean('is_encrypted')->default(true);
            $table->string('encryption_key')->nullable();
            $table->boolean('is_forwarded')->default(false);
            $table->uuid('original_message_id')->nullable();
            
            // Moderation
            $table->boolean('is_moderated')->default(false);
            $table->string('moderation_status')->nullable(); // approved, rejected, pending, flagged
            $table->text('moderation_reason')->nullable();
            $table->uuid('moderated_by')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->json('moderation_metadata')->nullable();
            
            // Content-specific fields
            // For images/videos
            $table->string('media_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->integer('media_duration')->nullable(); // in seconds for video/voice
            $table->json('media_metadata')->nullable(); // dimensions, size, format, etc.
            
            // For voice messages
            $table->integer('voice_duration')->nullable(); // in seconds
            $table->string('voice_transcription')->nullable();
            $table->boolean('is_transcribed')->default(false);
            
            // For files
            $table->string('file_name')->nullable();
            $table->string('file_type')->nullable();
            $table->integer('file_size')->nullable(); // in bytes
            $table->string('file_url')->nullable();
            
            // For stickers/gifs
            $table->string('sticker_pack_id')->nullable();
            $table->string('sticker_id')->nullable();
            $table->string('gif_url')->nullable();
            $table->json('animation_metadata')->nullable();
            
            // For system messages
            $table->string('system_action')->nullable(); // user_joined, user_left, settings_changed, etc.
            $table->json('system_data')->nullable(); // Action-specific data
            
            // For icebreaker messages
            $table->string('icebreaker_type')->nullable();
            $table->json('icebreaker_options')->nullable();
            $table->boolean('icebreaker_responded')->default(false);
            
            // Engagement and reactions
            $table->json('reactions')->nullable(); // emoji reactions with user counts
            $table->integer('reaction_count')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_important')->default(false);
            $table->json('mentions')->nullable(); // Array of mentioned user IDs
            
            // Analytics and metrics
            $table->integer('view_count')->default(0);
            $table->json('view_metrics')->nullable(); // Who viewed and when
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->json('edit_history')->nullable();
            
            // Scheduling and automation
            $table->timestamp('scheduled_for')->nullable();
            $table->boolean('is_scheduled')->default(false);
            $table->boolean('is_automated')->default(false);
            $table->string('automation_trigger')->nullable();
            
            // Cache and performance
            $table->json('cached_data')->nullable(); // Frequently accessed data
            $table->boolean('is_searchable')->default(true);
            $table->text('search_content')->nullable(); // Processed content for search
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->json('client_metadata')->nullable(); // Client-specific data
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('chat_id')->references('id')->on('chats')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reply_to_message_id')->references('id')->on('messages')->onDelete('set null');
            $table->foreign('moderated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['chat_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
            $table->index(['type', 'status']);
            $table->index('reply_to_message_id');
            $table->index(['is_pinned', 'chat_id']);
            $table->index(['moderation_status', 'created_at']);
            $table->index(['scheduled_for', 'is_scheduled']);
            $table->index('is_searchable');
            $table->fullText(['content', 'search_content']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};