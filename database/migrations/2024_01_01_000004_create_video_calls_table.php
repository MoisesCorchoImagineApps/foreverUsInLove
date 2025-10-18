<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Initiator and basic info
            $table->uuid('initiated_by');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('type')->default('private'); // private, group, broadcast, conference
            
            // Call status and timing
            $table->string('status')->default('pending'); // pending, active, ended, cancelled, failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            
            // Participant management
            $table->integer('max_participants')->default(10);
            $table->integer('current_participant_count')->default(0);
            $table->json('participant_limits')->nullable(); // Per-tier limits
            $table->boolean('allow_join_anytime')->default(true);
            $table->boolean('require_permission_to_join')->default(false);
            
            // WebRTC and connection settings
            $table->json('webrtc_config')->nullable(); // ICE servers, STUN/TURN config
            $table->string('room_id')->unique(); // Unique room identifier
            $table->string('session_token')->nullable();
            $table->json('connection_settings')->nullable();
            
            // Quality and media settings
            $table->string('video_quality')->default('hd'); // sd, hd, fhd, 4k
            $table->string('audio_quality')->default('high'); // low, medium, high, hifi
            $table->boolean('video_enabled')->default(true);
            $table->boolean('audio_enabled')->default(true);
            $table->boolean('screen_sharing_enabled')->default(true);
            $table->json('media_settings')->nullable();
            
            // Recording and storage
            $table->boolean('is_recorded')->default(false);
            $table->string('recording_url')->nullable();
            $table->integer('recording_size')->nullable(); // in bytes
            $table->string('recording_format')->nullable();
            $table->json('recording_settings')->nullable();
            $table->boolean('auto_record')->default(false);
            
            // Security and privacy
            $table->boolean('is_encrypted')->default(true);
            $table->string('encryption_key')->nullable();
            $table->boolean('waiting_room_enabled')->default(false);
            $table->string('password')->nullable();
            $table->boolean('require_authentication')->default(false);
            
            // Permissions and controls
            $table->json('host_controls')->nullable(); // Mute all, kick participants, etc.
            $table->json('participant_permissions')->nullable(); // Who can share screen, etc.
            $table->boolean('allow_chat')->default(true);
            $table->boolean('allow_reactions')->default(true);
            $table->boolean('allow_breakout_rooms')->default(false);
            
            // Connection quality and metrics
            $table->json('connection_metrics')->nullable(); // Latency, packet loss, etc.
            $table->json('quality_metrics')->nullable(); // Video/audio quality scores
            $table->integer('average_connection_quality')->nullable(); // 1-5 scale
            $table->json('bandwidth_usage')->nullable();
            $table->json('device_metrics')->nullable(); // Camera, mic, speaker info
            
            // Features and integrations
            $table->boolean('background_blur_enabled')->default(false);
            $table->boolean('virtual_backgrounds_enabled')->default(false);
            $table->boolean('noise_cancellation_enabled')->default(false);
            $table->json('ai_features')->nullable(); // Transcription, translation, etc.
            $table->boolean('live_captions_enabled')->default(false);
            
            // Breakout rooms (for group calls)
            $table->json('breakout_rooms')->nullable(); // Room configurations
            $table->boolean('auto_assign_breakouts')->default(false);
            $table->integer('breakout_duration')->nullable(); // in minutes
            
            // Analytics and engagement
            $table->integer('total_join_attempts')->default(0);
            $table->integer('successful_connections')->default(0);
            $table->json('engagement_metrics')->nullable(); // Speaking time, reactions, etc.
            $table->json('technical_issues')->nullable(); // Connection problems, drops, etc.
            
            // Scheduling and recurrence
            $table->boolean('is_recurring')->default(false);
            $table->json('recurrence_settings')->nullable(); // Daily, weekly, etc.
            $table->uuid('recurring_series_id')->nullable();
            $table->boolean('send_reminders')->default(false);
            $table->json('reminder_settings')->nullable();
            
            // Platform and compatibility
            $table->json('supported_platforms')->nullable(); // Web, iOS, Android, etc.
            $table->string('created_from_platform')->nullable();
            $table->json('browser_requirements')->nullable();
            $table->json('device_requirements')->nullable();
            
            // Costs and billing (for premium features)
            $table->string('billing_tier')->default('free'); // free, premium, enterprise
            $table->decimal('cost_per_minute', 8, 4)->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->json('billing_details')->nullable();
            
            // Emergency and safety
            $table->boolean('emergency_recording')->default(false);
            $table->json('safety_features')->nullable(); // Report, block, etc.
            $table->boolean('content_moderation')->default(false);
            $table->json('moderation_logs')->nullable();
            
            // Cache and performance
            $table->json('cached_participant_data')->nullable();
            $table->json('performance_metrics')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->json('custom_settings')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('initiated_by')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index(['initiated_by', 'created_at']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['type', 'status']);
            $table->index('room_id');
            $table->index(['started_at', 'ended_at']);
            $table->index(['is_recurring', 'recurring_series_id']);
            $table->index('billing_tier');
            $table->index(['scheduled_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_calls');
    }
};