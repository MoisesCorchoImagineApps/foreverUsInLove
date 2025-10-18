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
        Schema::create('email_clicks', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignUuid('email_id')
                  ->constrained('email_notifications', 'email_id')
                  ->onDelete('cascade')
                  ->comment('Email that was clicked');
            
            // Click Details
            $table->text('link_url')
                  ->comment('URL that was clicked');
            
            $table->string('link_label', 200)
                  ->nullable()
                  ->comment('Link text or label');
            
            $table->integer('click_position')
                  ->nullable()
                  ->comment('Position of link in email (1st, 2nd, etc.)');
            
            // User Agent & Context
            $table->text('user_agent')
                  ->nullable()
                  ->comment('Browser/client user agent');
            
            $table->string('ip_address', 45)
                  ->nullable()
                  ->index()
                  ->comment('IP address of clicker');
            
            $table->string('country', 2)
                  ->nullable()
                  ->index()
                  ->comment('Country code based on IP');
            
            $table->string('device_type', 20)
                  ->nullable()
                  ->index()
                  ->comment('Device type (desktop, mobile, tablet)');
            
            // Tracking Information
            $table->json('utm_parameters')
                  ->nullable()
                  ->comment('UTM tracking parameters');
            
            $table->timestamp('clicked_at')
                  ->useCurrent()
                  ->index()
                  ->comment('When the link was clicked');
            
            $table->timestamps();
            
            // Indexes for analytics
            $table->index(['email_id', 'clicked_at']);
            $table->index(['link_url', 'clicked_at']);
            $table->index(['device_type', 'clicked_at']);
            $table->index(['country', 'clicked_at']);
            $table->index(['ip_address', 'clicked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_clicks');
    }
};