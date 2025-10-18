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
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->uuid('token_id')->primary();
            
            // Relationships
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('User owning the device token');
            
            // Token Information
            $table->text('token')
                  ->comment('Device push notification token');
            
            $table->enum('platform', ['android', 'ios', 'web', 'huawei'])
                  ->index()
                  ->comment('Device platform');
            
            $table->enum('status', ['active', 'inactive', 'expired', 'invalid'])
                  ->default('active')
                  ->index()
                  ->comment('Token validity status');
            
            // Device Information
            $table->json('device_info')
                  ->nullable()
                  ->comment('Device details (model, OS version, app version)');
            
            // Usage Tracking
            $table->timestamp('last_used_at')
                  ->nullable()
                  ->index()
                  ->comment('When token was last used for push');
            
            $table->timestamp('expires_at')
                  ->nullable()
                  ->index()
                  ->comment('When token expires');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'platform', 'status']);
            $table->index(['token', 'status']); // For token lookup
            $table->index(['platform', 'status', 'last_used_at']);
            $table->index(['expires_at', 'status']);
            
            // Unique constraint on token
            $table->unique('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};