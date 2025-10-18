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
        Schema::create('pqrs', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('User who submitted the PQRS');
            
            $table->foreignId('assigned_to')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('Staff member assigned to handle the PQRS');
            
            $table->foreignId('escalated_to')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('Manager/supervisor if escalated');
            
            // PQRS Classification
            $table->enum('type', [
                'PETICION', 'QUEJA', 'RECLAMO', 'SUGERENCIA'
            ])->comment('Type of PQRS (Spanish legal classification)');
            
            $table->enum('category', [
                'TECHNICAL_ISSUE', 'BILLING_PROBLEM', 'ACCOUNT_ACCESS', 'PRIVACY_CONCERN',
                'SAFETY_ISSUE', 'FEATURE_REQUEST', 'BUG_REPORT', 'CONTENT_ISSUE',
                'USER_BEHAVIOR', 'SERVICE_QUALITY', 'REFUND_REQUEST', 'DATA_DELETION',
                'VERIFICATION_PROBLEM', 'MATCHING_ISSUE', 'NOTIFICATION_PROBLEM',
                'SUBSCRIPTION_ISSUE', 'PAYMENT_FAILURE', 'PROFILE_ISSUE', 'CHAT_PROBLEM',
                'PHOTO_UPLOAD_ISSUE', 'LOCATION_SERVICES', 'PUSH_NOTIFICATIONS',
                'ACCESSIBILITY', 'OTHER'
            ])->comment('Category of the issue or request');
            
            $table->string('subcategory', 100)
                  ->nullable()
                  ->comment('More specific subcategory');
            
            // Content
            $table->string('subject')
                  ->comment('Brief subject/title of the PQRS');
            
            $table->text('description')
                  ->comment('Detailed description of the issue or request');
            
            $table->json('attachments')
                  ->nullable()
                  ->comment('File attachments and evidence');
            
            // Priority and SLA
            $table->enum('priority', [
                'LOW', 'MEDIUM', 'HIGH', 'URGENT', 'CRITICAL'
            ])->default('MEDIUM')
              ->index()
              ->comment('Priority level determining response SLA');
            
            $table->integer('sla_hours')
                  ->comment('SLA response time in hours based on priority');
            
            $table->timestamp('sla_deadline')
                  ->index()
                  ->comment('Calculated SLA deadline for response');
            
            $table->boolean('sla_breached')
                  ->default(false)
                  ->index()
                  ->comment('Whether SLA deadline was missed');
            
            // Status Management
            $table->enum('status', [
                'CREATED', 'ASSIGNED', 'IN_PROGRESS', 'PENDING_INFO',
                'PENDING_APPROVAL', 'RESOLVED', 'CLOSED', 'ESCALATED',
                'REOPENED', 'CANCELLED'
            ])->default('CREATED')
              ->index()
              ->comment('Current status of the PQRS');
            
            $table->json('status_history')
                  ->nullable()
                  ->comment('History of status changes with timestamps');
            
            // Resolution
            $table->text('resolution')
                  ->nullable()
                  ->comment('Final resolution or response');
            
            $table->enum('resolution_type', [
                'SOLVED', 'WORKAROUND_PROVIDED', 'NOT_REPRODUCIBLE',
                'FEATURE_IMPLEMENTED', 'POLICY_EXPLAINED', 'ESCALATED_FURTHER',
                'DUPLICATE', 'INVALID', 'WONT_FIX', 'REQUIRES_UPDATE'
            ])->nullable()
              ->comment('Type of resolution provided');
            
            // Communication
            $table->integer('messages_count')
                  ->default(0)
                  ->comment('Number of messages in this PQRS thread');
            
            $table->timestamp('last_message_at')
                  ->nullable()
                  ->comment('Timestamp of the last message');
            
            $table->foreignId('last_message_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('Who sent the last message');
            
            // Customer Satisfaction
            $table->integer('satisfaction_rating')
                  ->nullable()
                  ->comment('Customer satisfaction rating (1-5)');
            
            $table->text('satisfaction_feedback')
                  ->nullable()
                  ->comment('Additional feedback from customer');
            
            // Analytics and ML
            $table->json('analytics_data')
                  ->nullable()
                  ->comment('Analytics and ML categorization data');
            
            $table->decimal('urgency_score', 3, 2)
                  ->nullable()
                  ->comment('ML-calculated urgency score (0.00-1.00)');
            
            $table->json('auto_suggestions')
                  ->nullable()
                  ->comment('Auto-generated response suggestions');
            
            // Escalation
            $table->boolean('auto_escalated')
                  ->default(false)
                  ->index()
                  ->comment('Whether automatically escalated by system');
            
            $table->string('escalation_reason', 200)
                  ->nullable()
                  ->comment('Reason for escalation');
            
            $table->timestamp('escalated_at')
                  ->nullable()
                  ->comment('When the PQRS was escalated');
            
            // Internal Notes
            $table->text('internal_notes')
                  ->nullable()
                  ->comment('Internal staff notes not visible to user');
            
            $table->json('tags')
                  ->nullable()
                  ->comment('Organizational tags for categorization');
            
            // Timestamps
            $table->timestamp('submitted_at')
                  ->useCurrent()
                  ->comment('When the PQRS was submitted');
            
            $table->timestamp('assigned_at')
                  ->nullable()
                  ->comment('When assigned to staff member');
            
            $table->timestamp('first_response_at')
                  ->nullable()
                  ->comment('When first response was provided');
            
            $table->timestamp('resolved_at')
                  ->nullable()
                  ->comment('When the PQRS was resolved');
            
            $table->timestamp('closed_at')
                  ->nullable()
                  ->comment('When the PQRS was closed');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
            $table->index(['category', 'status']);
            $table->index(['priority', 'status']); 
            $table->index(['assigned_to', 'status']);
            $table->index(['sla_deadline', 'status']);
            $table->index(['sla_breached', 'status']);
            $table->index('submitted_at');
            $table->index('resolved_at');
            
            // Composite indexes for common queries
            $table->index(['status', 'priority', 'sla_deadline']);
            $table->index(['assigned_to', 'status', 'priority']);
            $table->index(['type', 'category', 'status']);
            $table->index(['user_id', 'type', 'status']);
            $table->index(['auto_escalated', 'status', 'priority']);
            
            // Full-text search
            $table->fullText(['subject', 'description', 'resolution']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pqrs');
    }
};