<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('matched_user_id');
            $table->enum('status', ['active', 'expired', 'unmatched', 'blocked', 'reported'])->default('active')->index();
            $table->integer('compatibility_score')->default(0); // 0-100
            $table->enum('match_source', [
                'mutual_like', 'super_like', 'boost_like', 'second_chance',
                'algorithm_match', 'interest_match', 'proximity_match', 'compatibility_match'
            ])->index();
            $table->boolean('first_message_sent')->default(false);
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->integer('messages_count')->default(0);
            $table->integer('mutual_interest_score')->default(0); // 1-10 scale
            $table->enum('engagement_level', ['low', 'medium', 'high', 'very_high'])->default('low');
            $table->boolean('conversation_starter_used')->default(false);
            $table->enum('icebreaker_type', [
                'question', 'compliment', 'shared_interest', 'joke', 'observation', 'gif'
            ])->nullable();
            $table->json('match_quality_indicators')->nullable();
            $table->json('interaction_patterns')->nullable();
            $table->json('shared_interests')->nullable();
            $table->json('location_data')->nullable();
            $table->integer('time_to_first_message_hours')->nullable();
            $table->integer('response_time_avg_minutes')->nullable();
            $table->integer('conversation_depth_score')->default(1); // 1-10 scale
            $table->boolean('date_suggested')->default(false);
            $table->boolean('date_planned')->default(false);
            $table->boolean('social_media_connected')->default(false);
            $table->boolean('phone_number_shared')->default(false);
            $table->boolean('video_call_made')->default(false);
            $table->boolean('in_person_meeting')->default(false);
            $table->integer('relationship_potential_score')->default(0); // 1-10 scale
            $table->json('ai_compatibility_analysis')->nullable();
            $table->json('premium_features_used')->nullable();
            $table->timestamp('expiry_date')->nullable();
            
            // Status-specific timestamps
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('unmatched_at')->nullable();
            $table->enum('unmatched_by', ['user', 'matched_user'])->nullable();
            $table->enum('unmatch_reason', [
                'no_response', 'inappropriate_behavior', 'not_interested', 'found_someone',
                'different_expectations', 'spam', 'fake_profile', 'other'
            ])->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->enum('blocked_by', ['user', 'matched_user'])->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->enum('reported_by', ['user', 'matched_user'])->nullable();
            $table->enum('report_reason', [
                'harassment', 'spam', 'fake_profile', 'inappropriate_photos',
                'scam', 'underage', 'violence', 'other'
            ])->nullable();
            
            $table->timestamps();

            // Foreign Keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('matched_user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for better query performance
            $table->index(['user_id', 'status']);
            $table->index(['matched_user_id', 'status']); 
            $table->index(['user_id', 'created_at']);
            $table->index(['matched_user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['match_source', 'status']);
            $table->index(['engagement_level', 'status']);
            $table->index(['compatibility_score', 'status']);
            $table->index(['first_message_sent', 'first_message_at']);
            $table->index(['last_interaction_at', 'status']);
            $table->index(['expiry_date', 'status']);
            $table->index(['messages_count', 'engagement_level']);
            $table->index(['relationship_potential_score', 'status']);
            $table->index(['date_suggested', 'date_planned']);
            $table->index(['unmatched_at', 'unmatched_by']);
            $table->index(['blocked_at', 'blocked_by']);
            $table->index(['reported_at', 'reported_by']);

            // Unique constraint to prevent duplicate matches between same users
            $table->unique(['user_id', 'matched_user_id'], 'unique_user_match');

            // Additional unique constraint to prevent reverse duplicates
            // This will be handled at application level to ensure (A,B) and (B,A) don't both exist
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};