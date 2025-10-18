<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('viewer_id');
            $table->uuid('viewed_user_id');
            $table->enum('view_type', [
                'profile_card', 'detailed_profile', 'photo_focus', 'bio_focus', 'quick_glance'
            ])->index();
            $table->integer('duration_seconds');
            $table->enum('source', [
                'discovery_swipe', 'search_results', 'match_list', 'liked_you', 'mutual_friends',
                'nearby_users', 'suggested_profiles', 'boost_visibility', 'super_like_notification',
                'second_chance', 'trending_profiles', 'compatibility_match', 'interest_match'
            ])->index();
            $table->uuid('discovery_session_id')->nullable();
            $table->enum('device_type', ['mobile_ios', 'mobile_android', 'web_desktop', 'web_mobile'])->index();
            $table->json('interaction_data')->nullable();
            $table->integer('engagement_score')->default(0); // 0-100
            $table->json('photos_viewed')->nullable();
            $table->json('profile_sections_viewed')->nullable();
            $table->integer('scroll_depth_percentage')->default(0); // 0-100
            $table->integer('time_spent_on_photos_seconds')->default(0);
            $table->integer('time_spent_on_bio_seconds')->default(0);
            $table->integer('time_spent_on_interests_seconds')->default(0);
            $table->enum('conversion_action', [
                'liked', 'passed', 'super_liked', 'messaged', 'shared_profile', 'reported', 'blocked'
            ])->nullable();
            $table->integer('conversion_time_seconds')->nullable();
            $table->json('geographic_data')->nullable();
            $table->enum('referrer_source', [
                'push_notification', 'app_icon', 'deep_link', 'widget', 'share_link', 'advertisement'
            ])->nullable();
            $table->json('session_context')->nullable();
            $table->json('behavioral_signals')->nullable();
            $table->json('quality_indicators')->nullable();
            $table->json('personalization_data')->nullable();
            $table->enum('a_b_test_variant', ['control', 'variant_a', 'variant_b', 'variant_c'])->nullable();
            $table->boolean('is_return_visitor')->default(false);
            $table->integer('previous_view_count')->default(0);
            $table->integer('view_sequence_number')->default(1);
            $table->integer('time_since_last_view_hours')->nullable();
            $table->enum('cohort_group', ['new_user', 'active_user', 'returning_user', 'premium_user'])->nullable();
            $table->json('feature_flags')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('viewer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('viewed_user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for better query performance
            $table->index(['viewer_id', 'view_type']);
            $table->index(['viewed_user_id', 'view_type']);
            $table->index(['viewer_id', 'created_at']);
            $table->index(['viewed_user_id', 'created_at']);
            $table->index(['view_type', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index(['device_type', 'created_at']);
            $table->index(['discovery_session_id', 'created_at']);
            $table->index(['engagement_score', 'view_type']);
            $table->index(['duration_seconds', 'engagement_score']);
            $table->index(['conversion_action', 'conversion_time_seconds']);
            $table->index(['is_return_visitor', 'previous_view_count']);
            $table->index(['viewer_id', 'viewed_user_id', 'created_at']);
            $table->index(['referrer_source', 'created_at']);
            $table->index(['cohort_group', 'engagement_score']);
            $table->index(['a_b_test_variant', 'conversion_action']);
            $table->index(['scroll_depth_percentage', 'duration_seconds']);

            // Composite indexes for common query patterns
            $table->index(['viewer_id', 'conversion_action', 'created_at'], 'idx_viewer_conversion_date');
            $table->index(['viewed_user_id', 'engagement_score', 'created_at'], 'idx_viewed_engagement_date');
            $table->index(['source', 'view_type', 'conversion_action'], 'idx_source_type_conversion');

            // Check constraint to ensure viewer cannot view themselves
            // This will be handled at application level as MySQL doesn't support check constraints with subqueries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('views');
    }
};