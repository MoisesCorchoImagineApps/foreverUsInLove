<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_chats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Basic information
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // public, private, happy_hour, themed, event_based
            $table->string('status')->default('active'); // active, inactive, suspended, archived
            
            // Owner and admin management
            $table->unsignedBigInteger('owner_id');
            $table->json('admin_ids')->nullable(); // Array of admin user IDs
            $table->json('moderator_ids')->nullable(); // Array of moderator user IDs
            
            // Theme and customization
            $table->string('theme')->nullable();
            $table->string('color_scheme')->nullable();
            $table->string('avatar')->nullable();
            $table->string('banner_image')->nullable();
            $table->json('custom_emojis')->nullable();
            
            // Member management
            $table->integer('max_members')->default(50);
            $table->integer('current_member_count')->default(0);
            $table->boolean('require_approval')->default(false);
            $table->json('member_roles')->nullable(); // Custom role definitions
            
            // Permissions and settings
            $table->json('permissions')->nullable(); // Who can invite, post, etc.
            $table->boolean('allow_invites')->default(true);
            $table->boolean('public_join')->default(false);
            $table->string('invite_link')->nullable()->unique();
            $table->timestamp('invite_link_expires_at')->nullable();
            
            // Content settings
            $table->boolean('allow_media_sharing')->default(true);
            $table->boolean('allow_file_sharing')->default(true);
            $table->boolean('allow_voice_messages')->default(true);
            $table->integer('message_limit_per_hour')->nullable();
            
            // Moderation
            $table->boolean('auto_moderation')->default(false);
            $table->json('moderation_rules')->nullable();
            $table->json('banned_words')->nullable();
            $table->boolean('require_message_approval')->default(false);
            $table->json('spam_protection')->nullable();
            
            // Event-based features (for event_based type)
            $table->timestamp('event_starts_at')->nullable();
            $table->timestamp('event_ends_at')->nullable();
            $table->string('event_location')->nullable();
            $table->json('event_details')->nullable();
            $table->boolean('auto_archive_after_event')->default(false);
            
            // Happy hour features (for happy_hour type)
            $table->time('happy_hour_start')->nullable();
            $table->time('happy_hour_end')->nullable();
            $table->json('happy_hour_days')->nullable(); // Array of days
            $table->string('timezone')->nullable();
            $table->boolean('auto_activate_happy_hour')->default(false);
            
            // Themed features (for themed type)
            $table->string('topic')->nullable();
            $table->json('topic_tags')->nullable();
            $table->boolean('strict_topic_enforcement')->default(false);
            $table->json('topic_guidelines')->nullable();
            
            // Analytics and engagement
            $table->integer('total_messages')->default(0);
            $table->integer('active_members_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->json('engagement_metrics')->nullable();
            
            // Premium features (tier-based)
            $table->string('tier')->default('basic'); // basic, premium, vip
            $table->json('premium_features')->nullable();
            $table->boolean('priority_support')->default(false);
            
            // Cache and performance
            $table->json('member_cache')->nullable(); // Recent member activity
            $table->json('message_cache')->nullable(); // Recent messages summary
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->json('settings')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index(['type', 'status']);
            $table->index('owner_id');
            $table->index(['public_join', 'status']);
            $table->index('created_at');
            $table->index('last_activity_at');
            $table->index('tier');
            $table->index(['event_starts_at', 'event_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_chats');
    }
};