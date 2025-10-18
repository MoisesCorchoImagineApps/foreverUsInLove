<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relationships
            $table->uuid('chat_id');
            $table->uuid('user_id');
            
            // Participation details
            $table->string('role')->default('member'); // member, admin, moderator, owner
            $table->string('status')->default('active'); // active, inactive, banned, left, kicked
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->uuid('invited_by')->nullable();
            $table->string('join_method')->nullable(); // invited, link, qr_code, search
            
            // Permissions
            $table->boolean('can_send_messages')->default(true);
            $table->boolean('can_send_media')->default(true);
            $table->boolean('can_invite_others')->default(false);
            $table->boolean('can_edit_chat_info')->default(false);
            $table->boolean('can_delete_messages')->default(false);
            $table->json('custom_permissions')->nullable();
            
            // Message tracking
            $table->uuid('last_read_message_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->integer('unread_message_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            
            // Notification settings
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('mention_notifications')->default(true);
            $table->boolean('sound_notifications')->default(true);
            $table->string('notification_frequency')->default('all'); // all, important, mentions, none
            $table->json('notification_settings')->nullable();
            
            // Privacy settings
            $table->boolean('show_online_status')->default(true);
            $table->boolean('show_read_receipts')->default(true);
            $table->boolean('show_typing_indicator')->default(true);
            $table->json('privacy_settings')->nullable();
            
            // Engagement metrics
            $table->integer('total_messages_sent')->default(0);
            $table->integer('total_reactions_given')->default(0);
            $table->integer('total_mentions_received')->default(0);
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            
            // Moderation and behavior
            $table->boolean('is_muted')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->uuid('muted_by')->nullable();
            $table->text('mute_reason')->nullable();
            $table->integer('warning_count')->default(0);
            $table->json('moderation_history')->nullable();
            
            // Custom settings per participant
            $table->string('custom_nickname')->nullable(); // Custom name in this chat
            $table->string('custom_color')->nullable(); // Custom color for this participant
            $table->json('custom_settings')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('chat_id')->references('id')->on('chats')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invited_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('last_read_message_id')->references('id')->on('messages')->onDelete('set null');
            $table->foreign('muted_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['chat_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['chat_id', 'role']);
            $table->index(['chat_id', 'last_activity_at']);
            $table->index('joined_at');
            $table->index(['status', 'role']);
            $table->index('unread_message_count');
            
            // Unique constraint
            $table->unique(['chat_id', 'user_id'], 'unique_chat_participant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_participants');
    }
};