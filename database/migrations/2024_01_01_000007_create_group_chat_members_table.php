<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_chat_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relationships
            $table->uuid('group_chat_id');
            $table->uuid('user_id');
            
            // Membership details
            $table->string('role')->default('member'); // owner, admin, moderator, member, guest
            $table->string('status')->default('active'); // active, inactive, banned, pending, left
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->uuid('invited_by')->nullable();
            $table->string('join_method')->nullable(); // invited, public_join, invite_link, qr_code
            
            // Role-specific permissions
            $table->boolean('can_invite_members')->default(false);
            $table->boolean('can_remove_members')->default(false);
            $table->boolean('can_promote_members')->default(false);
            $table->boolean('can_edit_group_info')->default(false);
            $table->boolean('can_pin_messages')->default(false);
            $table->boolean('can_delete_messages')->default(false);
            $table->boolean('can_moderate_content')->default(false);
            $table->boolean('can_manage_events')->default(false);
            $table->json('custom_permissions')->nullable();
            
            // Communication permissions
            $table->boolean('can_send_messages')->default(true);
            $table->boolean('can_send_media')->default(true);
            $table->boolean('can_send_voice_messages')->default(true);
            $table->boolean('can_share_files')->default(true);
            $table->boolean('can_use_stickers')->default(true);
            $table->boolean('can_react_to_messages')->default(true);
            $table->boolean('can_mention_all')->default(false);
            
            // Activity tracking
            $table->uuid('last_read_message_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->integer('unread_message_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_message_sent_at')->nullable();
            
            // Engagement metrics
            $table->integer('total_messages_sent')->default(0);
            $table->integer('total_reactions_given')->default(0);
            $table->integer('total_mentions_received')->default(0);
            $table->integer('total_voice_messages_sent')->default(0);
            $table->integer('total_media_shared')->default(0);
            $table->integer('events_attended')->default(0);
            
            // Notification preferences
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('mention_notifications')->default(true);
            $table->boolean('event_notifications')->default(true);
            $table->boolean('admin_notifications')->default(true);
            $table->string('notification_frequency')->default('all'); // all, important, mentions, events, none
            $table->json('notification_schedule')->nullable(); // Quiet hours, etc.
            
            // Group-specific settings
            $table->string('display_name')->nullable(); // Custom name in this group
            $table->string('member_color')->nullable(); // Custom color assigned
            $table->string('member_badge')->nullable(); // Special badge or title
            $table->json('group_specific_settings')->nullable();
            
            // Event participation (for event-based groups)
            $table->boolean('attending_current_event')->default(false);
            $table->string('event_rsvp_status')->nullable(); // attending, not_attending, maybe
            $table->json('event_participation_history')->nullable();
            $table->integer('events_organized')->default(0);
            
            // Happy hour participation (for happy_hour groups)
            $table->boolean('happy_hour_participant')->default(false);
            $table->json('happy_hour_preferences')->nullable();
            $table->integer('happy_hour_sessions_attended')->default(0);
            $table->timestamp('last_happy_hour_activity')->nullable();
            
            // Topic engagement (for themed groups)
            $table->json('topic_interests')->nullable(); // Specific topic tags user is interested in
            $table->integer('topic_contributions')->default(0);
            $table->decimal('topic_relevance_score', 5, 2)->nullable(); // How on-topic their messages are
            
            // Moderation and discipline
            $table->boolean('is_muted')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->uuid('muted_by')->nullable();
            $table->text('mute_reason')->nullable();
            $table->integer('warning_count')->default(0);
            $table->integer('violations_count')->default(0);
            $table->json('moderation_history')->nullable();
            $table->timestamp('last_warning_at')->nullable();
            
            // Approval and verification (for private groups)
            $table->boolean('requires_approval')->default(false);
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('join_request_message')->nullable();
            $table->text('approval_notes')->nullable();
            
            // Premium features and tier benefits
            $table->string('membership_tier')->default('basic'); // basic, premium, vip
            $table->json('tier_benefits')->nullable();
            $table->boolean('has_premium_features')->default(false);
            $table->timestamp('tier_expires_at')->nullable();
            
            // Contribution scoring
            $table->integer('contribution_score')->default(0);
            $table->json('contribution_breakdown')->nullable(); // Points from different activities
            $table->string('contributor_level')->nullable(); // newbie, regular, veteran, legend
            $table->json('achievements')->nullable(); // Group-specific achievements
            
            // Social connections within group
            $table->json('group_connections')->nullable(); // Who they interact with most
            $table->json('mentorship_relationships')->nullable(); // Mentoring/being mentored
            $table->boolean('is_group_ambassador')->default(false);
            
            // Privacy and visibility
            $table->boolean('show_online_status')->default(true);
            $table->boolean('show_profile_in_group')->default(true);
            $table->boolean('allow_direct_messages')->default(true);
            $table->json('visibility_settings')->nullable();
            
            // Analytics and insights
            $table->json('activity_patterns')->nullable(); // When they're most active
            $table->json('interaction_preferences')->nullable(); // Preferred communication types
            $table->decimal('group_satisfaction_score', 3, 2)->nullable(); // 1-5 rating
            
            // Custom fields and metadata
            $table->json('custom_fields')->nullable(); // Group-specific custom data
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('group_chat_id')->references('id')->on('group_chats')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invited_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('last_read_message_id')->references('id')->on('messages')->onDelete('set null');
            $table->foreign('muted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['group_chat_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['group_chat_id', 'role']);
            $table->index(['group_chat_id', 'last_activity_at']);
            $table->index(['status', 'role']);
            $table->index('joined_at');
            $table->index(['membership_tier', 'tier_expires_at']);
            $table->index('contribution_score');
            $table->index(['requires_approval', 'approved_at']);
            $table->index('unread_message_count');
            
            // Unique constraint
            $table->unique(['group_chat_id', 'user_id'], 'unique_group_member');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_chat_members');
    }
};