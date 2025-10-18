<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Polymorphic relationship
            $table->nullableUuidMorphs('chatable'); // chatable_type, chatable_id
            
            // Basic chat information
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('type')->default('private'); // private, group, video_call
            $table->string('status')->default('active'); // active, archived, deleted, suspended
            
            // Encryption and security
            $table->boolean('is_encrypted')->default(true);
            $table->string('encryption_key')->nullable();
            $table->boolean('auto_delete_messages')->default(false);
            $table->integer('auto_delete_duration')->nullable(); // in hours
            
            // Message settings
            $table->json('message_settings')->nullable(); // typing indicators, read receipts, etc.
            $table->boolean('allow_media')->default(true);
            $table->boolean('allow_voice_messages')->default(true);
            $table->boolean('allow_file_sharing')->default(true);
            
            // Moderation
            $table->boolean('moderation_enabled')->default(false);
            $table->json('moderation_settings')->nullable();
            $table->json('blocked_words')->nullable();
            $table->boolean('auto_moderate')->default(false);
            
            // Cache and performance
            $table->integer('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->uuid('last_message_id')->nullable();
            $table->json('cache_data')->nullable(); // last messages, participant info, etc.
            
            // Privacy and permissions
            $table->json('privacy_settings')->nullable();
            $table->json('notification_settings')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('invite_code')->nullable()->unique();
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->json('custom_settings')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['type', 'status']);
            $table->index('last_message_at');
            $table->index('created_at');
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};