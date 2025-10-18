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
        Schema::create('email_bounces', function (Blueprint $table) {
            $table->id();
            
            // Email Information
            $table->string('email')
                  ->index()
                  ->comment('Email address that bounced');
            
            $table->enum('bounce_type', ['hard', 'soft', 'complaint'])
                  ->index()
                  ->comment('Type of bounce (hard=permanent, soft=temporary)');
            
            $table->text('reason')
                  ->comment('Detailed bounce reason');
            
            $table->string('provider', 50)
                  ->nullable()
                  ->index()
                  ->comment('Email provider that reported the bounce');
            
            // Bounce Details
            $table->json('bounce_data')
                  ->nullable()
                  ->comment('Full bounce notification data from provider');
            
            $table->timestamp('bounced_at')
                  ->index()
                  ->comment('When the bounce occurred');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['email', 'bounce_type']);
            $table->index(['bounce_type', 'bounced_at']);
            $table->index(['provider', 'bounce_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_bounces');
    }
};