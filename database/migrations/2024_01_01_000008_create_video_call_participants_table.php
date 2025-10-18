<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_call_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relationships
            $table->uuid('video_call_id');
            $table->uuid('user_id');
            
            // Participation status
            $table->string('status')->default('invited'); // invited, joined, left, kicked, declined, no_answer
            $table->string('role')->default('participant'); // host, co_host, moderator, participant, observer
            $table->timestamp('invited_at');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->uuid('invited_by')->nullable();
            
            // Connection details
            $table->string('connection_status')->default('disconnected'); // connecting, connected, reconnecting, disconnected
            $table->string('join_method')->nullable(); // direct_link, meeting_id, phone_dial_in, mobile_app
            $table->integer('join_attempts')->default(0);
            $table->timestamp('first_join_attempt_at')->nullable();
            $table->timestamp('last_connection_at')->nullable();
            
            // Audio/Video settings and status
            $table->boolean('audio_enabled')->default(true);
            $table->boolean('video_enabled')->default(true);
            $table->boolean('is_muted')->default(false);
            $table->boolean('is_video_off')->default(false);
            $table->boolean('is_screen_sharing')->default(false);
            $table->timestamp('screen_share_started_at')->nullable();
            $table->integer('screen_share_duration')->default(0); // in seconds
            
            // Permissions and capabilities
            $table->boolean('can_unmute_self')->default(true);
            $table->boolean('can_enable_video')->default(true);
            $table->boolean('can_share_screen')->default(true);
            $table->boolean('can_use_chat')->default(true);
            $table->boolean('can_use_reactions')->default(true);
            $table->boolean('can_record')->default(false);
            $table->boolean('can_invite_others')->default(false);
            $table->json('host_permissions')->nullable(); // For hosts/co-hosts
            
            // Device and platform information
            $table->string('device_type')->nullable(); // mobile, desktop, tablet, phone
            $table->string('platform')->nullable(); // iOS, Android, Windows, Mac, Linux, Web
            $table->string('browser')->nullable(); // Chrome, Safari, Firefox, Edge
            $table->string('app_version')->nullable();
            $table->json('device_capabilities')->nullable(); // Camera, microphone, speaker info
            $table->json('system_requirements_met')->nullable();
            
            // Connection quality and metrics
            $table->integer('connection_quality')->nullable(); // 1-5 scale
            $table->decimal('average_latency', 8, 2)->nullable(); // in ms
            $table->decimal('packet_loss_rate', 5, 2)->nullable(); // percentage
            $table->string('connection_type')->nullable(); // wifi, cellular, ethernet
            $table->json('bandwidth_stats')->nullable(); // upload/download speeds
            $table->integer('connection_drops')->default(0);
            $table->integer('reconnection_attempts')->default(0);
            
            // Audio quality metrics
            $table->integer('audio_quality_score')->nullable(); // 1-5 scale
            $table->boolean('audio_issues_reported')->default(false);
            $table->json('audio_problems')->nullable(); // echo, feedback, static, etc.
            $table->boolean('microphone_working')->default(true);
            $table->boolean('speakers_working')->default(true);
            $table->string('audio_codec')->nullable();
            
            // Video quality metrics
            $table->integer('video_quality_score')->nullable(); // 1-5 scale
            $table->string('video_resolution')->nullable(); // 720p, 1080p, 4K
            $table->integer('video_framerate')->nullable();
            $table->boolean('video_issues_reported')->default(false);
            $table->json('video_problems')->nullable(); // pixelation, freezing, etc.
            $table->boolean('camera_working')->default(true);
            $table->string('video_codec')->nullable();
            
            // Engagement and interaction
            $table->integer('speaking_time')->default(0); // in seconds
            $table->decimal('speaking_percentage', 5, 2)->nullable();
            $table->integer('reactions_sent')->default(0);
            $table->integer('chat_messages_sent')->default(0);
            $table->integer('times_muted_by_host')->default(0);
            $table->integer('times_unmuted_by_host')->default(0);
            $table->json('interaction_timeline')->nullable();
            
            // Features usage
            $table->boolean('used_virtual_background')->default(false);
            $table->boolean('used_background_blur')->default(false);
            $table->boolean('used_noise_cancellation')->default(false);
            $table->boolean('used_hand_raising')->default(false);
            $table->boolean('used_breakout_rooms')->default(false);
            $table->integer('breakout_rooms_joined')->default(0);
            $table->json('features_usage_stats')->nullable();
            
            // Waiting room (if enabled)
            $table->boolean('went_through_waiting_room')->default(false);
            $table->timestamp('waiting_room_entry_at')->nullable();
            $table->timestamp('waiting_room_exit_at')->nullable();
            $table->integer('waiting_room_duration')->nullable(); // in seconds
            $table->uuid('admitted_by')->nullable();
            
            // Recording and consent
            $table->boolean('consented_to_recording')->default(false);
            $table->timestamp('recording_consent_at')->nullable();
            $table->boolean('appears_in_recording')->default(false);
            $table->json('recording_preferences')->nullable();
            
            // Moderation and behavior
            $table->boolean('was_kicked')->default(false);
            $table->uuid('kicked_by')->nullable();
            $table->text('kick_reason')->nullable();
            $table->timestamp('kicked_at')->nullable();
            $table->boolean('was_muted_by_host')->default(false);
            $table->boolean('disruptive_behavior_reported')->default(false);
            $table->json('moderation_actions')->nullable();
            
            // Technical issues and support
            $table->json('technical_issues')->nullable();
            $table->boolean('required_technical_support')->default(false);
            $table->text('support_notes')->nullable();
            $table->json('error_logs')->nullable();
            $table->integer('crash_count')->default(0);
            
            // Feedback and ratings
            $table->integer('call_experience_rating')->nullable(); // 1-5 scale
            $table->integer('audio_quality_rating')->nullable(); // 1-5 scale
            $table->integer('video_quality_rating')->nullable(); // 1-5 scale
            $table->integer('ease_of_use_rating')->nullable(); // 1-5 scale
            $table->text('feedback_comments')->nullable();
            $table->json('detailed_feedback')->nullable();
            
            // Network and performance analytics
            $table->json('network_changes')->nullable(); // WiFi switching, etc.
            $table->decimal('cpu_usage_average', 5, 2)->nullable();
            $table->decimal('memory_usage_average', 8, 2)->nullable(); // in MB
            $table->boolean('performance_issues_detected')->default(false);
            $table->json('performance_metrics')->nullable();
            
            // Billing and costs (for premium features)
            $table->decimal('participation_cost', 8, 4)->nullable();
            $table->string('billing_tier')->default('free');
            $table->json('premium_features_used')->nullable();
            $table->boolean('charged_for_participation')->default(false);
            
            // Call events and timeline
            $table->json('call_events')->nullable(); // Detailed timeline of actions
            $table->json('state_changes')->nullable(); // Status/permission changes
            $table->integer('total_interactions')->default(0);
            $table->timestamp('most_recent_interaction')->nullable();
            
            // Security and privacy
            $table->boolean('end_to_end_encryption_used')->default(false);
            $table->json('security_settings')->nullable();
            $table->boolean('data_collection_consented')->default(true);
            $table->json('privacy_preferences')->nullable();
            
            // Analytics and insights
            $table->json('participation_patterns')->nullable();
            $table->json('engagement_analytics')->nullable();
            $table->boolean('first_time_participant')->default(false);
            $table->json('comparison_metrics')->nullable(); // Vs previous calls
            
            // Custom data and metadata
            $table->json('custom_participant_data')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('video_call_id')->references('id')->on('video_calls')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invited_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('kicked_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('admitted_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['video_call_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['video_call_id', 'role']);
            $table->index(['video_call_id', 'joined_at']);
            $table->index(['status', 'role']);
            $table->index('connection_status');
            $table->index(['invited_at', 'status']);
            $table->index(['billing_tier', 'participation_cost']);
            $table->index('connection_quality');
            
            // Unique constraint
            $table->unique(['video_call_id', 'user_id'], 'unique_call_participant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_call_participants');
    }
};