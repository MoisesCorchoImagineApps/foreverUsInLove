<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discoveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->enum('mode', [
                'standard', 'explore', 'boost', 'local', 
                'global', 'interest', 'second_chance', 'trending'
            ])->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('cards_shown')->default(0);
            $table->integer('cards_per_session')->default(20);
            $table->integer('time_limit')->default(600); // seconds
            $table->integer('current_position')->default(0);
            $table->json('filters_applied')->nullable();
            $table->json('preferences')->nullable();
            $table->decimal('location_lat', 10, 8)->nullable();
            $table->decimal('location_lng', 11, 8)->nullable();
            $table->integer('radius')->nullable(); // kilometers
            $table->boolean('is_completed')->default(false);
            $table->enum('completion_reason', [
                'time_limit', 'cards_exhausted', 'user_exit', 
                'manual_stop', 'no_more_cards', 'app_background', 'network_error'
            ])->nullable();
            $table->integer('session_quality_score')->default(0);
            $table->json('engagement_metrics')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for better query performance
            $table->index(['user_id', 'mode']);
            $table->index(['user_id', 'created_at']);
            $table->index(['mode', 'created_at']);
            $table->index(['user_id', 'is_completed']);
            $table->index(['started_at', 'ended_at']);
            $table->index(['location_lat', 'location_lng']);
            $table->index('session_quality_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discoveries');
    }
};