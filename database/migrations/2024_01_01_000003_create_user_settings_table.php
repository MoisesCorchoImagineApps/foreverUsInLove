<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create User Settings Table Migration
 * 
 * Creates the user_settings table for ForeverUsInLove dating application
 * Includes comprehensive user preferences, privacy, and subscription management
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // DISCOVERY & MATCHING PREFERENCES
            $table->enum('discovery_mode', ['everyone', 'nearby', 'selected'])->default('everyone');
            $table->boolean('show_me_in_discovery')->default(true);
            $table->unsignedTinyInteger('min_age_preference')->default(18);
            $table->unsignedTinyInteger('max_age_preference')->default(99);
            $table->unsignedSmallInteger('max_distance_km')->default(50);
            $table->json('gender_preferences')->nullable(); // Array of preferred genders
            $table->json('interest_preferences')->nullable(); // Array of required interests
            $table->boolean('show_verified_only')->default(false);
            $table->boolean('show_profiles_with_photos_only')->default(false);
            $table->enum('distance_unit', ['km', 'miles'])->default('km');
            
            // NOTIFICATION PREFERENCES
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('email_notifications')->default(true);
            $table->boolean('push_notifications')->default(true);
            $table->boolean('sms_notifications')->default(false);
            $table->boolean('notify_new_matches')->default(true);
            $table->boolean('notify_new_messages')->default(true);
            $table->boolean('notify_new_likes')->default(true);
            $table->boolean('notify_profile_views')->default(false);
            $table->boolean('notify_super_likes')->default(true);
            $table->boolean('notify_new_followers')->default(false);
            $table->boolean('notify_mentions')->default(true);
            $table->boolean('notify_promotions')->default(false);
            $table->json('notification_schedule')->nullable(); // Weekly schedule
            $table->boolean('do_not_disturb')->default(false);
            $table->time('do_not_disturb_start')->nullable(); // DND start time
            $table->time('do_not_disturb_end')->nullable(); // DND end time
            
            // PRIVACY SETTINGS
            $table->enum('profile_visibility', ['public', 'friends', 'private'])->default('public');
            $table->boolean('show_online_status')->default(true);
            $table->boolean('show_last_active')->default(true);
            $table->boolean('show_read_receipts')->default(true);
            $table->boolean('show_typing_indicator')->default(true);
            $table->boolean('allow_messages_from_non_matches')->default(false);
            $table->boolean('incognito_mode')->default(false);
            $table->boolean('hide_profile_from_contacts')->default(false);
            $table->json('blocked_users_ids')->nullable(); // Array of blocked user IDs
            
            // CHAT & COMMUNICATION
            $table->boolean('auto_reply_enabled')->default(false);
            $table->text('auto_reply_message')->nullable();
            $table->boolean('message_sound_enabled')->default(true);
            $table->enum('message_preview', ['full', 'name_only', 'none'])->default('full');
            $table->unsignedSmallInteger('chat_retention_days')->default(90);
            
            // CONTENT PREFERENCES
            $table->string('language', 2)->default('en'); // ISO 639-1 code
            $table->string('timezone', 50)->default('UTC');
            $table->enum('theme', ['light', 'dark', 'auto'])->default('auto');
            $table->boolean('reduce_motion')->default(false); // Accessibility
            $table->boolean('high_contrast')->default(false); // Accessibility
            $table->enum('content_filter', ['none', 'moderate', 'strict'])->default('moderate');
            $table->boolean('show_explicit_content')->default(false);
            
            // SUBSCRIPTION & BILLING
            $table->boolean('is_premium')->default(false);
            $table->enum('subscription_tier', ['free', 'basic', 'premium', 'elite'])->default('free');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->boolean('auto_renew_subscription')->default(false);
            $table->string('preferred_currency', 3)->nullable(); // ISO 4217 code
            
            // ADVANCED FEATURES
            $table->boolean('boost_enabled')->default(false);
            $table->unsignedTinyInteger('super_likes_remaining')->default(5);
            $table->unsignedTinyInteger('rewinds_remaining')->default(3);
            $table->timestamp('last_boost_at')->nullable();
            $table->boolean('passport_enabled')->default(false); // Location changing
            $table->json('passport_locations')->nullable(); // Saved locations
            $table->boolean('read_receipts_enabled')->default(false);
            $table->boolean('unlimited_likes')->default(false);
            
            // SAFETY & SECURITY
            $table->boolean('photo_verification_required')->default(false);
            $table->boolean('safe_mode_enabled')->default(false);
            $table->boolean('share_location_enabled')->default(false);
            $table->boolean('panic_mode_enabled')->default(false);
            $table->json('trusted_contacts')->nullable(); // Emergency contacts
            
            // DATA & ANALYTICS
            $table->boolean('data_collection_consent')->default(true);
            $table->boolean('personalization_enabled')->default(true);
            $table->boolean('analytics_tracking')->default(true);
            $table->json('custom_preferences')->nullable(); // Additional user preferences
            
            // Standard Laravel Timestamps
            $table->timestamps();
            
            // Indexes for Performance
            $table->index('user_id');
            $table->index(['user_id', 'is_premium']);
            $table->index(['user_id', 'notifications_enabled']);
            $table->index(['user_id', 'incognito_mode']);
            $table->index('is_premium');
            $table->index('subscription_expires_at');
            $table->index(['subscription_tier', 'is_premium']);
            $table->index('discovery_mode');
            $table->index(['min_age_preference', 'max_age_preference']);
            $table->index('max_distance_km');
            $table->index('language');
            $table->index('timezone');
            $table->index('created_at');
            
            // Composite indexes for common queries
            $table->index(['user_id', 'discovery_mode', 'show_me_in_discovery']);
            $table->index(['is_premium', 'subscription_expires_at']);
            $table->index(['notifications_enabled', 'push_notifications']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};