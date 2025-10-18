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
        Schema::create('email_notifications', function (Blueprint $table) {
            $table->uuid('email_id')->primary();
            
            // Relationships
            $table->foreignUuid('notification_id')
                  ->constrained('notifications', 'notification_id')
                  ->onDelete('cascade')
                  ->comment('Parent notification reference');
            
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('User receiving the email');
            
            // Email Classification
            $table->string('email_type', 50)
                  ->index()
                  ->comment('Specific email type (welcome, payment_success, etc.)');
            
            $table->enum('provider', [
                'sendgrid', 'mailgun', 'ses', 'postmark', 'mailchimp', 'resend', 'smtp'
            ])->index()
              ->comment('Email service provider used');
            
            $table->enum('category', [
                'transactional', 'promotional', 'newsletter', 'notification', 'system', 'marketing'
            ])->index()
              ->comment('Email category for filtering and compliance');
            
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                  ->default('normal')
                  ->index()
                  ->comment('Email delivery priority');
            
            $table->enum('status', [
                'queued', 'sent', 'delivered', 'opened', 'clicked', 
                'bounced', 'spam', 'unsubscribed', 'failed'
            ])->default('queued')
              ->index()
              ->comment('Email delivery and engagement status');
            
            // Sender Information
            $table->string('from_email')
                  ->index()
                  ->comment('Sender email address');
            
            $table->string('from_name')
                  ->nullable()
                  ->comment('Sender display name');
            
            // Recipient Information
            $table->string('to_email')
                  ->index()
                  ->comment('Primary recipient email address');
            
            $table->string('to_name')
                  ->nullable()
                  ->comment('Primary recipient display name');
            
            $table->json('cc_emails')
                  ->nullable()
                  ->comment('CC recipient email addresses');
            
            $table->json('bcc_emails')
                  ->nullable()
                  ->comment('BCC recipient email addresses');
            
            // Content
            $table->text('subject')
                  ->comment('Email subject line');
            
            $table->text('preheader')
                  ->nullable()
                  ->comment('Email preheader/preview text');
            
            $table->longText('html_body')
                  ->comment('HTML email body content');
            
            $table->longText('text_body')
                  ->nullable()
                  ->comment('Plain text email body (fallback)');
            
            // Template & Campaign Management
            $table->string('template_id', 50)
                  ->nullable()
                  ->index()
                  ->comment('Email template identifier');
            
            $table->string('campaign_id', 50)
                  ->nullable()
                  ->index()
                  ->comment('Marketing campaign identifier');
            
            $table->string('ab_test_variant', 20)
                  ->nullable()
                  ->index()
                  ->comment('A/B test variant (A, B, C, control)');
            
            // Attachments & Media
            $table->json('attachments')
                  ->nullable()
                  ->comment('Email attachments metadata');
            
            $table->json('tags')
                  ->nullable()
                  ->comment('Email tags for organization and filtering');
            
            $table->json('metadata')
                  ->nullable()
                  ->comment('Additional email metadata (encrypted)');
            
            // Delivery & Engagement Tracking
            $table->timestamp('sent_at')
                  ->nullable()
                  ->index()
                  ->comment('When email was sent by provider');
            
            $table->timestamp('delivered_at')
                  ->nullable()
                  ->index()
                  ->comment('When email was delivered to recipient');
            
            $table->timestamp('opened_at')
                  ->nullable()
                  ->index()
                  ->comment('When email was first opened');
            
            $table->timestamp('clicked_at')
                  ->nullable()
                  ->index()
                  ->comment('When email links were first clicked');
            
            $table->timestamp('bounced_at')
                  ->nullable()
                  ->index()
                  ->comment('When email bounced (if applicable)');
            
            $table->timestamp('unsubscribed_at')
                  ->nullable()
                  ->index()
                  ->comment('When user unsubscribed from this email');
            
            // Engagement Metrics
            $table->integer('open_count')
                  ->default(0)
                  ->comment('Total number of opens');
            
            $table->integer('click_count')
                  ->default(0)
                  ->comment('Total number of clicks');
            
            // Provider Integration
            $table->string('provider_message_id', 100)
                  ->nullable()
                  ->index()
                  ->comment('Provider-specific message ID for tracking');
            
            $table->text('bounce_reason')
                  ->nullable()
                  ->comment('Reason for bounce if status is bounced');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Performance indexes
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'email_type']);
            $table->index(['user_id', 'category']);
            $table->index(['email_type', 'status']);
            $table->index(['category', 'status']);
            $table->index(['provider', 'status']);
            $table->index(['priority', 'created_at']);
            $table->index(['sent_at', 'status']);
            
            // Composite indexes for analytics
            $table->index(['campaign_id', 'ab_test_variant', 'status']);
            $table->index(['user_id', 'status', 'opened_at']);
            $table->index(['user_id', 'category', 'sent_at']);
            $table->index(['email_type', 'provider', 'status']);
            $table->index(['status', 'sent_at', 'opened_at']);
            $table->index(['category', 'priority', 'created_at']);
            
            // Engagement tracking indexes
            $table->index(['opened_at', 'status']);
            $table->index(['clicked_at', 'status']);
            $table->index(['bounced_at', 'bounce_reason']);
            $table->index(['unsubscribed_at', 'category']);
            
            // Template and campaign analysis
            $table->index(['template_id', 'status', 'opened_at']);
            $table->index(['campaign_id', 'sent_at', 'status']);
            
            // Full-text search on email content
            $table->fullText(['subject', 'html_body', 'text_body']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_notifications');
    }
};