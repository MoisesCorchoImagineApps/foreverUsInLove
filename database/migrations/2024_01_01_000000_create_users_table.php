<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Users Table Migration
 * 
 * Creates the main users table for ForeverUsInLove dating application
 * Includes authentication, verification, security, and GDPR compliance features
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            
            // Core Authentication Fields
            $table->string('email')->unique();
            $table->string('username', 50)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password');
            
            // Email & Phone Verification
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            
            // Account Status & Security
            $table->enum('status', [
                'active', 
                'inactive', 
                'suspended', 
                'banned', 
                'pending_verification'
            ])->default('pending_verification');
            
            // Login Tracking & Rate Limiting
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable(); // IPv6 support
            $table->unsignedSmallInteger('login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            
            // Biometric Authentication (Face ID)
            $table->text('face_id_data')->nullable(); // Encrypted biometric data
            $table->boolean('face_id_enabled')->default(false);
            
            // Two-Factor Authentication
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->json('two_factor_recovery_codes')->nullable();
            
            // Device & Notification Management
            $table->string('device_token')->nullable(); // Push notifications
            $table->string('remember_token')->nullable();
            
            // GDPR Compliance
            $table->json('gdpr_consents')->nullable(); // Consent tracking
            $table->timestamp('gdpr_consent_date')->nullable();
            $table->boolean('marketing_emails_consent')->default(false);
            
            // Activity & Rate Limiting
            $table->timestamp('last_active_at')->nullable();
            $table->unsignedSmallInteger('rate_limit_hits')->default(0);
            $table->timestamp('rate_limit_reset_at')->nullable();
            
            // Standard Laravel Timestamps
            $table->timestamps();
            $table->softDeletes(); // Soft delete for GDPR compliance
            
            // Indexes for Performance
            $table->index(['email', 'status']);
            $table->index(['username', 'status']);
            $table->index(['phone', 'status']);
            $table->index('status');
            $table->index('last_active_at');
            $table->index('email_verified_at');
            $table->index('phone_verified_at');
            $table->index(['locked_until', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};