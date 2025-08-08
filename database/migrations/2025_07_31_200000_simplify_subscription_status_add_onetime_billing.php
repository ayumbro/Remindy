<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if status column exists and drop it
        if (Schema::hasColumn('subscriptions', 'status')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                // Drop indexes that include the status column
                $table->dropIndex(['user_id', 'status']);
                $table->dropIndex(['next_billing_date', 'status']);
                $table->dropColumn('status');
            });
        }

        // Update billing_cycle enum to include 'one-time'
        // Note: SQLite doesn't support modifying enums directly, so we need to recreate the column
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_cycle_temp')->nullable()->after('billing_cycle');
        });

        // Copy existing data to temp column
        DB::statement('UPDATE subscriptions SET billing_cycle_temp = billing_cycle');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('billing_cycle', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'one-time'])
                ->default('monthly')
                ->after('payment_method_id');
        });

        // Copy data back from temp column
        DB::statement('UPDATE subscriptions SET billing_cycle = billing_cycle_temp');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_cycle_temp');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add back status column
            $table->enum('status', ['active', 'paused', 'canceled'])->default('active')->after('end_date');
        });

        // Set all existing subscriptions to 'active' status
        DB::statement("UPDATE subscriptions SET status = 'active'");

        // Recreate the indexes
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index(['next_billing_date', 'status']);
        });

        // Restore billing_cycle enum without 'one-time'
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_cycle_temp')->after('billing_cycle');
        });

        DB::statement("UPDATE subscriptions SET billing_cycle_temp = CASE WHEN billing_cycle = 'one-time' THEN 'monthly' ELSE billing_cycle END");

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('billing_cycle', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])
                ->default('monthly')
                ->after('payment_method_id');
        });

        DB::statement('UPDATE subscriptions SET billing_cycle = billing_cycle_temp');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_cycle_temp');
        });

    }
};
