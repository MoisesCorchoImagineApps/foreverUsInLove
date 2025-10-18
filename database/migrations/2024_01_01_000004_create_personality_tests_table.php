<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Personality Tests Table Migration
 * 
 * Creates the personality_tests table for ForeverUsInLove dating application
 * Includes comprehensive psychology test management and compatibility algorithms
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personality_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Test Identification & Version
            $table->enum('test_type', [
                'mbti', 
                'big_five', 
                'love_language', 
                'attachment_style', 
                'enneagram', 
                'custom'
            ]);
            $table->string('test_version', 20); // Version of the test algorithm
            
            // Test Content & Progress
            $table->json('questions'); // Array of test questions
            $table->json('answers'); // Array of user responses
            $table->json('results')->nullable(); // Processed test results
            $table->string('primary_result', 100)->nullable(); // Main result (e.g., INTJ, Extraversion)
            $table->json('secondary_results')->nullable(); // Additional results/dimensions
            $table->json('trait_scores')->nullable(); // Individual trait scores (0-100)
            
            // Completion Tracking
            $table->unsignedTinyInteger('completion_percentage')->default(0);
            $table->enum('status', [
                'in_progress', 
                'completed', 
                'expired', 
                'invalid'
            ])->default('in_progress');
            $table->unsignedSmallInteger('total_questions');
            $table->unsignedSmallInteger('answered_questions')->default(0);
            $table->unsignedInteger('time_spent_seconds')->default(0); // Total time in test
            
            // AI-Generated Insights & Analysis
            $table->json('insights')->nullable(); // Personality insights
            $table->json('compatibility_notes')->nullable(); // Compatibility analysis
            $table->decimal('accuracy_score', 4, 3)->nullable(); // Test reliability (0.000-1.000)
            
            // Test Timeline
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // When test results expire
            
            // Visibility & Usage
            $table->boolean('is_public')->default(true); // Show in public profile
            $table->boolean('use_for_matching')->default(true); // Use in matching algorithm
            $table->unsignedTinyInteger('retake_count')->default(0); // Number of retakes
            
            // Standard Laravel Timestamps
            $table->timestamps();
            
            // Indexes for Performance & Queries
            $table->index(['user_id', 'test_type']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'use_for_matching']);
            $table->index('test_type');
            $table->index('status');
            $table->index(['status', 'test_type']);
            $table->index(['status', 'expires_at']);
            $table->index('use_for_matching');
            $table->index('is_public');
            $table->index('completed_at');
            $table->index('expires_at');
            $table->index('accuracy_score');
            $table->index('created_at');
            
            // Composite indexes for common queries
            $table->index(['user_id', 'test_type', 'status']);
            $table->index(['test_type', 'status', 'use_for_matching']);
            $table->index(['status', 'completed_at', 'expires_at']);
            $table->index(['user_id', 'status', 'completion_percentage']);
            
            // Unique constraint: one active test per type per user
            $table->unique(['user_id', 'test_type', 'status'], 'unique_active_test_per_type')
                ->where('status', 'in_progress');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personality_tests');
    }
};