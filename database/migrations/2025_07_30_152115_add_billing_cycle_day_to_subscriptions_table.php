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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add billing_cycle_day to store the original day of month for billing cycles
            // This field is immutable once set during subscription creation
            // Range: 1-31 for monthly cycles, null for non-monthly cycles
            $table->integer('billing_cycle_day')->nullable()->after('billing_interval')
                ->comment('Original day of month for billing cycles (1-31), immutable after creation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_cycle_day');
        });
    }
};
