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
        Schema::create('email_unsubscribes', function (Blueprint $table) {
            $table->id();
            
            // User Information
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('User who unsubscribed');
            
            // Unsubscribe Scope
            $table->string('email_type', 50)
                  ->nullable()
                  ->index()
                  ->comment('Specific email type unsubscribed from (null = all)');
            
            $table->string('category', 30)
                  ->nullable()
                  ->index()
                  ->comment('Email category unsubscribed from (null = all)');
            
            // Unsubscribe Details
            $table->enum('unsubscribe_method', [
                'email_link', 'user_settings', 'bounce', 'admin_action', 'global_opt_out'
            ])->default('email_link')
              ->index()
              ->comment('How the user unsubscribed');
            
            $table->text('reason')
                  ->nullable()
                  ->comment('User-provided reason for unsubscribing');
            
            $table->string('source_email_id', 36)
                  ->nullable()
                  ->index()
                  ->comment('Email ID that triggered the unsubscribe');
            
            $table->json('metadata')
                  ->nullable()
                  ->comment('Additional unsubscribe metadata');
            
            $table->timestamp('unsubscribed_at')
                  ->useCurrent()
                  ->index()
                  ->comment('When the unsubscribe occurred');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'email_type']);
            $table->index(['user_id', 'category']);
            $table->index(['email_type', 'unsubscribed_at']);
            $table->index(['category', 'unsubscribed_at']);
            $table->index(['unsubscribe_method', 'unsubscribed_at']);
            
            // Unique constraint to prevent duplicate unsubscribes
            $table->unique(['user_id', 'email_type', 'category'], 'unique_user_unsubscribe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_unsubscribes');
    }
};