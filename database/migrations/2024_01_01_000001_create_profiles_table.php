<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Profiles Table Migration
 * 
 * Creates the profiles table for ForeverUsInLove dating application
 * Includes comprehensive demographic data, preferences, and matching algorithms
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Basic Personal Information
            $table->string('first_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->string('display_name', 50)->nullable(); // Public display name
            $table->text('bio')->nullable(); // Profile description
            
            // Age & Birth Information
            $table->unsignedTinyInteger('age')->nullable();
            $table->date('date_of_birth')->nullable();
            
            // Gender & Sexual Orientation
            $table->enum('gender', [
                'male', 
                'female', 
                'non_binary', 
                'other', 
                'prefer_not_to_say'
            ])->nullable();
            $table->string('gender_identity', 50)->nullable(); // Free text for identity
            $table->enum('sexual_orientation', [
                'straight', 
                'gay', 
                'lesbian', 
                'bisexual', 
                'pansexual', 
                'asexual', 
                'other'
            ])->nullable();
            
            // Relationship Status & Goals
            $table->enum('relationship_status', [
                'single', 
                'divorced', 
                'widowed', 
                'separated'
            ])->nullable();
            $table->enum('looking_for', [
                'relationship', 
                'friendship', 
                'casual', 
                'marriage'
            ])->nullable();
            
            // Location Information
            $table->string('location')->nullable(); // Full address text
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 2)->nullable(); // ISO country code
            
            // Physical Attributes
            $table->unsignedSmallInteger('height_cm')->nullable(); // Height in centimeters
            $table->enum('body_type', [
                'slim', 
                'athletic', 
                'average', 
                'curvy', 
                'heavyset'
            ])->nullable();
            $table->string('ethnicity', 50)->nullable();
            $table->string('religion', 50)->nullable();
            
            // Education & Career
            $table->enum('education', [
                'high_school', 
                'some_college', 
                'bachelors', 
                'masters', 
                'phd', 
                'trade_school'
            ])->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('company', 100)->nullable();
            $table->string('school', 100)->nullable();
            $table->unsignedInteger('income_range')->nullable(); // Annual income in thousands
            
            // Family & Children
            $table->boolean('has_children')->nullable();
            $table->boolean('wants_children')->nullable();
            
            // Lifestyle Preferences
            $table->enum('smoking', [
                'never', 
                'occasionally', 
                'regularly', 
                'trying_to_quit'
            ])->nullable();
            $table->enum('drinking', [
                'never', 
                'socially', 
                'regularly', 
                'prefer_not_to_say'
            ])->nullable();
            
            // Interests & Preferences (JSON Arrays)
            $table->json('languages')->nullable(); // Spoken languages
            $table->json('interests')->nullable(); // Hobbies and interests
            $table->json('hobbies')->nullable(); // Specific hobbies
            $table->json('music_preferences')->nullable(); // Music genres/artists
            $table->json('movie_preferences')->nullable(); // Movie genres/titles
            $table->json('book_preferences')->nullable(); // Book genres/authors
            $table->string('personality_type', 20)->nullable(); // MBTI type
            $table->json('values')->nullable(); // Important values/beliefs
            $table->string('zodiac_sign', 20)->nullable();
            
            // Social Media Integration
            $table->string('instagram_handle', 50)->nullable();
            $table->boolean('spotify_connected')->default(false);
            
            // Profile Presentation
            $table->string('tagline')->nullable(); // Short catchy phrase
            
            // Profile Status & Management
            $table->enum('status', [
                'active', 
                'paused', 
                'incomplete', 
                'under_review'
            ])->default('incomplete');
            $table->unsignedTinyInteger('completeness_percentage')->default(0);
            $table->unsignedInteger('profile_views_count')->default(0);
            $table->unsignedInteger('profile_likes_count')->default(0);
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->text('pause_reason')->nullable();
            
            // Verification & Premium Features
            $table->boolean('is_verified')->default(false); // Verification badge
            
            // Privacy & Visibility Settings
            $table->boolean('show_age')->default(true);
            $table->boolean('show_distance')->default(true);
            $table->unsignedSmallInteger('visibility_radius_km')->default(50);
            
            // Standard Laravel Timestamps
            $table->timestamps();
            $table->softDeletes(); // Soft delete for data retention
            
            // Indexes for Performance & Queries
            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index(['gender', 'status']);
            $table->index(['age', 'status']);
            $table->index(['city', 'country', 'status']);
            $table->index(['country', 'status']);
            $table->index(['looking_for', 'status']);
            $table->index('completeness_percentage');
            $table->index('is_verified');
            $table->index('last_updated_at');
            $table->index(['latitude', 'longitude']); // Geospatial queries
            $table->index('created_at');
            
            // Composite indexes for common queries
            $table->index(['status', 'gender', 'age']);
            $table->index(['status', 'city', 'country']);
            $table->index(['status', 'completeness_percentage', 'is_verified']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};