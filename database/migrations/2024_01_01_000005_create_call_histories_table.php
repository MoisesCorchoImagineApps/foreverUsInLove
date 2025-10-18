<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Call relationship
            $table->uuid('video_call_id');
            $table->uuid('user_id');
            
            // Participation details
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            $table->string('join_method')->nullable(); // invite_link, direct_dial, scheduled, etc.
            $table->string('leave_reason')->nullable(); // user_left, kicked, connection_lost, etc.
            
            // Connection and quality metrics
            $table->string('connection_type')->nullable(); // wifi, cellular, ethernet
            $table->integer('connection_quality_score')->nullable(); // 1-5 scale
            $table->decimal('average_latency', 8, 2)->nullable(); // in ms
            $table->decimal('packet_loss_percentage', 5, 2)->nullable();
            $table->json('bandwidth_stats')->nullable(); // upload/download speeds
            $table->json('quality_fluctuations')->nullable(); // Quality changes during call
            
            // Audio metrics
            $table->boolean('audio_enabled')->default(true);
            $table->integer('audio_quality_score')->nullable(); // 1-5 scale
            $table->decimal('audio_bitrate', 8, 2)->nullable(); // kbps
            $table->integer('audio_drops')->default(0);
            $table->boolean('microphone_used')->default(true);
            $table->boolean('speaker_used')->default(true);
            $table->json('audio_issues')->nullable(); // Echo, feedback, etc.
            
            // Video metrics
            $table->boolean('video_enabled')->default(true);
            $table->integer('video_quality_score')->nullable(); // 1-5 scale
            $table->string('video_resolution')->nullable(); // 720p, 1080p, etc.
            $table->decimal('video_bitrate', 8, 2)->nullable(); // kbps
            $table->integer('video_fps')->nullable();
            $table->integer('video_drops')->default(0);
            $table->boolean('camera_used')->default(true);
            $table->json('video_issues')->nullable(); // Pixelation, freezing, etc.
            
            // Device and platform information
            $table->string('device_type')->nullable(); // mobile, desktop, tablet
            $table->string('platform')->nullable(); // iOS, Android, Windows, Mac, Web
            $table->string('browser')->nullable(); // Chrome, Safari, Firefox, etc.
            $table->string('browser_version')->nullable();
            $table->json('device_capabilities')->nullable(); // Camera, mic, speaker specs
            $table->json('system_info')->nullable(); // OS version, hardware specs
            
            // Engagement metrics
            $table->integer('speaking_time')->default(0); // in seconds
            $table->decimal('speaking_percentage', 5, 2)->nullable(); // % of call duration
            $table->integer('reactions_sent')->default(0);
            $table->integer('messages_sent')->default(0);
            $table->boolean('screen_shared')->default(false);
            $table->integer('screen_share_duration')->default(0); // in seconds
            
            // Features used
            $table->boolean('used_chat')->default(false);
            $table->boolean('used_reactions')->default(false);
            $table->boolean('used_screen_share')->default(false);
            $table->boolean('used_virtual_background')->default(false);
            $table->boolean('used_background_blur')->default(false);
            $table->boolean('used_noise_cancellation')->default(false);
            $table->json('features_usage')->nullable(); // Detailed feature usage stats
            
            // Issues and troubleshooting
            $table->integer('connection_drops')->default(0);
            $table->integer('reconnection_attempts')->default(0);
            $table->json('technical_issues')->nullable(); // Detailed issue log
            $table->json('error_messages')->nullable();
            $table->boolean('required_support')->default(false);
            $table->text('support_notes')->nullable();
            
            // User feedback and ratings
            $table->integer('overall_rating')->nullable(); // 1-5 scale
            $table->integer('audio_quality_rating')->nullable(); // 1-5 scale
            $table->integer('video_quality_rating')->nullable(); // 1-5 scale
            $table->integer('ease_of_use_rating')->nullable(); // 1-5 scale
            $table->text('feedback_comments')->nullable();
            $table->json('detailed_ratings')->nullable(); // Granular ratings
            
            // Call events and interactions
            $table->json('call_events')->nullable(); // Mute/unmute, video on/off, etc.
            $table->json('interaction_timeline')->nullable(); // Timeline of user actions
            $table->integer('interruptions_caused')->default(0);
            $table->integer('interruptions_received')->default(0);
            
            // Network and performance data
            $table->json('network_changes')->nullable(); // Network switching during call
            $table->decimal('cpu_usage_avg', 5, 2)->nullable(); // Average CPU usage %
            $table->decimal('memory_usage_avg', 8, 2)->nullable(); // Average memory usage MB
            $table->json('performance_alerts')->nullable(); // Performance warnings
            $table->boolean('hardware_acceleration_used')->default(false);
            
            // Privacy and security
            $table->boolean('recording_consented')->default(false);
            $table->boolean('data_collection_consented')->default(true);
            $table->json('privacy_settings_used')->nullable();
            $table->boolean('end_to_end_encrypted')->default(false);
            
            // Cost and billing (for premium users)
            $table->decimal('call_cost', 8, 4)->nullable(); // Cost for this user's participation
            $table->string('billing_tier')->default('free');
            $table->json('premium_features_used')->nullable();
            
            // Analytics and insights
            $table->json('behavioral_metrics')->nullable(); // Patterns, preferences
            $table->json('usage_patterns')->nullable(); // When, how long, frequency
            $table->boolean('first_time_user')->default(false);
            $table->integer('total_calls_by_user')->nullable(); // User's total call count
            
            // Metadata and custom data
            $table->json('metadata')->nullable();
            $table->json('custom_metrics')->nullable();
            $table->json('experimental_data')->nullable(); // A/B testing data
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('video_call_id')->references('id')->on('video_calls')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index(['video_call_id', 'user_id']);
            $table->index(['user_id', 'joined_at']);
            $table->index(['connection_quality_score', 'created_at']);
            $table->index(['overall_rating', 'created_at']);
            $table->index(['duration', 'created_at']);
            $table->index(['platform', 'device_type']);
            $table->index('joined_at');
            $table->index(['billing_tier', 'call_cost']);
            
            // Unique constraint to prevent duplicate entries
            $table->unique(['video_call_id', 'user_id', 'joined_at'], 'unique_call_participation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_histories');
    }
};