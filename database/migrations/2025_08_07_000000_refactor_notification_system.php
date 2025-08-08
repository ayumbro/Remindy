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
        // Drop the notification_preferences table if it exists
        Schema::dropIfExists('notification_preferences');
        
        // Drop the reminder_schedules table if it exists
        Schema::dropIfExists('reminder_schedules');
        
        // Drop the notification_logs table if it exists  
        Schema::dropIfExists('notification_logs');
        
        // Update users table - remove timezone, add notification settings
        Schema::table('users', function (Blueprint $table) {
            // Drop timezone column if it exists
            if (Schema::hasColumn('users', 'timezone')) {
                $table->dropColumn('timezone');
            }
            
            // Add notification time in UTC
            $table->time('notification_time_utc')->default('09:00:00');
            
            // Add default notification settings
            $table->boolean('default_email_enabled')->default(true);
            $table->boolean('default_webhook_enabled')->default(false);
            $table->json('default_reminder_intervals')->default('[]');
            
            // Add notification-specific fields
            $table->string('notification_email')->nullable();
            $table->string('webhook_url')->nullable();
            $table->json('webhook_headers')->nullable();
        });
        
        // Update subscriptions table - add notification preferences
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add notification preference columns
            $table->boolean('notifications_enabled')->nullable();
            $table->boolean('email_enabled')->nullable();
            $table->boolean('webhook_enabled')->nullable();
            $table->json('reminder_intervals')->nullable();
            $table->boolean('use_default_notifications')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notification_time_utc',
                'default_email_enabled',
                'default_webhook_enabled',
                'default_reminder_intervals',
                'notification_email',
                'webhook_url',
                'webhook_headers'
            ]);
            
            // Re-add timezone column
            $table->string('timezone')->default('UTC');
        });
        
        // Restore subscriptions table
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'notifications_enabled',
                'email_enabled',
                'webhook_enabled',
                'reminder_intervals',
                'use_default_notifications'
            ]);
        });
        
        // Recreate notification_preferences table
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_id')->nullable()->constrained()->onDelete('cascade');
            $table->boolean('email_enabled')->default(true);
            $table->boolean('webhook_enabled')->default(false);
            $table->string('email_address')->nullable();
            $table->string('webhook_url')->nullable();
            $table->json('webhook_headers')->nullable();
            $table->json('reminder_intervals')->default('[]');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->index(['user_id', 'is_default']);
            $table->index(['user_id', 'subscription_id']);
            $table->unique(['user_id', 'subscription_id']);
        });
        
        // Recreate reminder_schedules table
        Schema::create('reminder_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->date('due_date');
            $table->integer('days_before');
            $table->enum('status', ['pending', 'sent', 'failed', 'cancelled'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'due_date']);
            $table->index(['user_id', 'subscription_id']);
        });
        
        // Recreate notification_logs table
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['email', 'webhook']);
            $table->enum('status', ['success', 'failed']);
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'subscription_id']);
            $table->index(['type', 'status']);
        });
    }
};