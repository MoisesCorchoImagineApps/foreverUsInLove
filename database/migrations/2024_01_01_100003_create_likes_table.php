<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('target_user_id');
            $table->enum('type', ['like', 'pass', 'super_like'])->index();
            $table->boolean('is_mutual')->default(false);
            $table->timestamp('matched_at')->nullable();
            $table->enum('source', [
                'discovery_standard', 'discovery_boost', 'discovery_local', 'discovery_global',
                'search_results', 'suggested_matches', 'mutual_friends', 'nearby_users',
                'second_chance', 'trending_now', 'recently_active', 'compatibility_match'
            ])->index();
            $table->uuid('session_id')->nullable();
            $table->enum('discovery_mode', [
                'standard', 'explore', 'boost', 'local', 
                'global', 'interest', 'second_chance', 'trending'
            ])->nullable();
            $table->enum('swipe_direction', ['left', 'right'])->index();
            $table->integer('decision_time_ms'); // milliseconds
            $table->json('context')->nullable();
            $table->json('location')->nullable();
            $table->json('device_info')->nullable();
            $table->integer('interaction_quality_score')->default(0);
            $table->integer('confidence_level')->default(5); // 1-10 scale
            $table->boolean('is_undo_available')->default(true);
            $table->boolean('undo_used')->default(false);
            $table->timestamp('undo_at')->nullable();
            $table->boolean('boost_applied')->default(false);
            $table->boolean('premium_feature_used')->default(false);
            $table->boolean('notification_sent')->default(false);
            $table->boolean('notification_read')->default(false);
            $table->timestamp('notification_read_at')->nullable();
            $table->json('analytics_data')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for better query performance
            $table->index(['user_id', 'type']);
            $table->index(['target_user_id', 'type']);
            $table->index(['user_id', 'created_at']);
            $table->index(['target_user_id', 'created_at']);
            $table->index(['is_mutual', 'matched_at']);
            $table->index(['session_id', 'created_at']);
            $table->index(['source', 'type']);
            $table->index(['discovery_mode', 'type']);
            $table->index(['swipe_direction', 'type']);
            $table->index(['boost_applied', 'type']);
            $table->index(['premium_feature_used', 'created_at']);
            $table->index(['notification_sent', 'notification_read']);
            $table->index('interaction_quality_score');
            $table->index('decision_time_ms');

            // Unique constraint to prevent duplicate likes between same users
            $table->unique(['user_id', 'target_user_id'], 'unique_user_target_like');

            // Check constraint to ensure user cannot like themselves
            // This will be handled at application level as MySQL doesn't support check constraints with subqueries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};