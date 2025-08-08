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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('daily_notification_enabled')->default(false)->after('webhook_headers');
            $table->timestamp('last_daily_notification_sent_at')->nullable()->after('daily_notification_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_notification_enabled', 'last_daily_notification_sent_at']);
        });
    }
};