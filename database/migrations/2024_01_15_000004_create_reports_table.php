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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('reporter_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('User who made the report');
            
            $table->foreignId('reported_user_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('User being reported');
            
            $table->foreignId('reviewed_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('Moderator who reviewed the report');
            
            $table->foreignId('escalated_to')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('Senior moderator/manager if escalated');
            
            // Report Classification
            $table->enum('report_type', [
                'HARASSMENT', 'SPAM', 'FAKE_PROFILE', 'INAPPROPRIATE_CONTENT',
                'HATE_SPEECH', 'VIOLENCE', 'NUDITY', 'SCAM', 'UNDERAGE',
                'IDENTITY_THEFT', 'COPYRIGHT', 'OTHER'
            ])->comment('Type of violation being reported');
            
            $table->text('description')
                  ->comment('Detailed description of the issue');
            
            // Evidence Collection
            $table->json('evidence')
                  ->nullable()
                  ->comment('Evidence supporting the report (screenshots, logs, etc.)');
            
            // Status Management
            $table->enum('status', [
                'PENDING', 'IN_REVIEW', 'UNDER_INVESTIGATION', 'RESOLVED',
                'DISMISSED', 'ESCALATED', 'CLOSED', 'REOPENED'
            ])->default('PENDING')
              ->index()
              ->comment('Current status of the report');
            
            $table->enum('priority', [
                'LOW', 'MEDIUM', 'HIGH', 'URGENT', 'CRITICAL'
            ])->default('MEDIUM')
              ->index()
              ->comment('Priority level for investigation');
            
            // ML Analysis and Risk Assessment
            $table->json('ml_analysis')
                  ->nullable()
                  ->comment('Machine learning analysis results');
            
            $table->decimal('confidence_score', 3, 2)
                  ->nullable()
                  ->comment('ML confidence score (0.00-1.00)');
            
            $table->enum('risk_level', [
                'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'
            ])->nullable()
              ->index()
              ->comment('Assessed risk level');
            
            // Investigation and Resolution
            $table->text('moderator_notes')
                  ->nullable()
                  ->comment('Internal notes from moderators');
            
            $table->json('resolution')
                  ->nullable()
                  ->comment('Resolution details including actions taken');
            
            $table->enum('outcome', [
                'NO_VIOLATION', 'WARNING_ISSUED', 'CONTENT_REMOVED',
                'TEMPORARY_SUSPENSION', 'PERMANENT_BAN', 'ACCOUNT_RESTRICTED',
                'PROFILE_HIDDEN', 'FEATURES_LIMITED', 'ESCALATED', 'PENDING_APPEAL'
            ])->nullable()
              ->comment('Final outcome of the investigation');
            
            // Escalation Management
            $table->boolean('auto_escalated')
                  ->default(false)
                  ->index()
                  ->comment('Whether report was automatically escalated');
            
            $table->enum('escalation_reason', [
                'HIGH_ML_CONFIDENCE', 'SEVERE_VIOLATION_TYPE', 'CRITICAL_PRIORITY',
                'REPEAT_OFFENDER', 'MULTIPLE_REPORTS', 'SAFETY_CONCERN',
                'LEGAL_IMPLICATION', 'MANUAL_ESCALATION'
            ])->nullable()
              ->comment('Reason for escalation');
            
            // Timeline Tracking
            $table->timestamp('reported_at')
                  ->useCurrent()
                  ->comment('When the report was submitted');
            
            $table->timestamp('reviewed_at')
                  ->nullable()
                  ->comment('When review started');
            
            $table->timestamp('investigation_started_at')
                  ->nullable()
                  ->comment('When detailed investigation began');
            
            $table->timestamp('resolved_at')
                  ->nullable()
                  ->comment('When the report was resolved');
            
            $table->timestamp('escalated_at')
                  ->nullable()
                  ->comment('When the report was escalated');
            
            // SLA and Performance Metrics
            $table->integer('response_time_hours')
                  ->nullable()
                  ->comment('Hours taken for first response');
            
            $table->integer('resolution_time_hours')
                  ->nullable()
                  ->comment('Total hours to resolution');
            
            $table->boolean('sla_met')
                  ->nullable()
                  ->index()
                  ->comment('Whether SLA was met for resolution');
            
            // Pattern Detection
            $table->integer('similar_reports_count')
                  ->default(0)
                  ->comment('Number of similar reports against same user');
            
            $table->json('pattern_analysis')
                  ->nullable()
                  ->comment('Analysis of reporting patterns and trends');
            
            // Communication and Updates
            $table->boolean('reporter_notified')
                  ->default(false)
                  ->comment('Whether reporter was notified of resolution');
            
            $table->timestamp('reporter_notified_at')
                  ->nullable()
                  ->comment('When reporter was notified');
            
            $table->json('communication_log')
                  ->nullable()
                  ->comment('Log of communications with involved parties');
            
            // Appeals Process
            $table->boolean('appeal_submitted')
                  ->default(false)
                  ->index()
                  ->comment('Whether reported user submitted an appeal');
            
            $table->json('appeal_data')
                  ->nullable()
                  ->comment('Appeal information and resolution');
            
            $table->timestamp('appeal_submitted_at')
                  ->nullable()
                  ->comment('When appeal was submitted');
            
            // Metadata and Context
            $table->string('source_context', 100)
                  ->nullable()
                  ->comment('Where the reportable behavior occurred');
            
            $table->json('metadata')
                  ->nullable()
                  ->comment('Additional context and metadata');
            
            $table->json('tags')
                  ->nullable()
                  ->comment('Organizational tags for categorization');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['reporter_id', 'status']);
            $table->index(['reported_user_id', 'status']);
            $table->index(['report_type', 'status']);
            $table->index(['priority', 'status']);
            $table->index(['reviewed_by', 'status']);
            $table->index(['auto_escalated', 'status']);
            $table->index('reported_at');
            $table->index('resolved_at');
            
            // Composite indexes for common queries
            $table->index(['status', 'priority', 'reported_at']);
            $table->index(['reported_user_id', 'report_type', 'status']);
            $table->index(['risk_level', 'status', 'priority']);
            $table->index(['reviewed_by', 'status', 'reviewed_at']);
            $table->index(['auto_escalated', 'escalation_reason', 'status']);
            
            // Pattern detection indexes
            $table->index(['reported_user_id', 'reported_at']);
            $table->index(['reporter_id', 'reported_at']);
            
            // Full-text search
            $table->fullText(['description', 'moderator_notes']);
            
            // Prevent duplicate reports from same user about same target
            $table->unique(['reporter_id', 'reported_user_id', 'report_type'], 'unique_user_report_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};