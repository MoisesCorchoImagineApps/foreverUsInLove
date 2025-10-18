<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('content_flags', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('User who owns the flagged content');
            
            $table->foreignId('flagged_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('User who reported the content (null for system flags)');
            
            $table->foreignId('reviewed_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('Moderator who reviewed the flag');
            
            // Content Identification
            $table->enum('content_type', [
                'TEXT', 'IMAGE', 'VIDEO', 'AUDIO', 'PROFILE', 'BEHAVIOR'
            ])->comment('Type of content being flagged');
            
            $table->string('content_id', 100)
                  ->nullable()
                  ->index()
                  ->comment('ID of the specific content item');
            
            $table->text('content_excerpt')
                  ->nullable()
                  ->comment('Sample of the flagged content for context');
            
            // Flag Classification
            $table->enum('flag_category', [
                'SPAM', 'HARASSMENT', 'HATE_SPEECH', 'VIOLENCE', 'ADULT_CONTENT',
                'NUDITY', 'FAKE_PROFILE', 'SCAM', 'COPYRIGHT', 'PRIVACY_VIOLATION',
                'UNDERAGE', 'DRUG_CONTENT', 'SELF_HARM', 'TERRORISM', 'MISINFORMATION',
                'IDENTITY_THEFT', 'IMPERSONATION', 'SOLICITATION', 'OTHER'
            ])->comment('Category of policy violation');
            
            $table->text('description')
                  ->nullable()
                  ->comment('Detailed description of the violation');
            
            // ML Analysis
            $table->decimal('confidence_score', 3, 2)
                  ->nullable()
                  ->comment('ML confidence score (0.00-1.00)');
            
            $table->enum('confidence_level', [
                'VERY_LOW', 'LOW', 'MEDIUM', 'HIGH', 'VERY_HIGH'
            ])->nullable()
              ->comment('Categorized confidence level');
            
            $table->json('ml_analysis')
                  ->nullable()
                  ->comment('Detailed ML analysis results and metadata');
            
            // Status and Actions
            $table->enum('status', [
                'PENDING', 'UNDER_REVIEW', 'CONFIRMED', 'DISMISSED', 
                'ESCALATED', 'RESOLVED', 'APPEALED', 'APPEAL_RESOLVED'
            ])->default('PENDING')
              ->index()
              ->comment('Current status of the flag');
            
            $table->enum('action_taken', [
                'NONE', 'WARNING', 'HIDE_CONTENT', 'REMOVE_CONTENT',
                'SUSPEND_USER', 'BAN_USER', 'ESCALATE', 'REQUIRE_VERIFICATION'
            ])->nullable()
              ->comment('Action taken based on the flag');
            
            // Priority and Urgency
            $table->enum('priority', [
                'LOW', 'MEDIUM', 'HIGH', 'URGENT', 'CRITICAL'
            ])->default('MEDIUM')
              ->index()
              ->comment('Priority level for review');
            
            $table->boolean('requires_human_review')
                  ->default(false)
                  ->index()
                  ->comment('Whether human review is required');
            
            $table->boolean('auto_escalated')
                  ->default(false)
                  ->index()
                  ->comment('Whether the flag was automatically escalated');
            
            // Evidence and Context
            $table->json('evidence')
                  ->nullable()
                  ->comment('Supporting evidence and metadata');
            
            $table->integer('report_count')
                  ->default(1)
                  ->comment('Number of times this content has been reported');
            
            // Resolution
            $table->text('moderator_notes')
                  ->nullable()
                  ->comment('Notes from the reviewing moderator');
            
            $table->text('resolution_reason')
                  ->nullable()
                  ->comment('Explanation of the resolution decision');
            
            $table->json('appeal_data')
                  ->nullable()
                  ->comment('Appeal information if user contests the flag');
            
            // Timestamps
            $table->timestamp('flagged_at')
                  ->useCurrent()
                  ->comment('When the content was initially flagged');
            
            $table->timestamp('reviewed_at')
                  ->nullable()
                  ->comment('When the flag was reviewed by a moderator');
            
            $table->timestamp('resolved_at')
                  ->nullable()
                  ->comment('When the flag was resolved');
            
            $table->timestamp('appealed_at')
                  ->nullable()
                  ->comment('When an appeal was submitted');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['content_type', 'status']);
            $table->index(['flag_category', 'status']);
            $table->index(['priority', 'status']);
            $table->index(['confidence_score', 'status']);
            $table->index(['requires_human_review', 'status']);
            $table->index(['auto_escalated', 'status']);
            $table->index('flagged_at');
            $table->index('reviewed_at');
            
            // Composite indexes for common queries
            $table->index(['status', 'priority', 'flagged_at']);
            $table->index(['content_type', 'flag_category', 'status']);
            $table->index(['user_id', 'content_type', 'status']);
            $table->index(['reviewed_by', 'status', 'reviewed_at']);
            
            // Full-text search on content and descriptions
            $table->fullText(['description', 'content_excerpt', 'moderator_notes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_flags');
    }
};