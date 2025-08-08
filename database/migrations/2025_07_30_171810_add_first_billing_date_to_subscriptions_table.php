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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add first_billing_date field after start_date
            $table->date('first_billing_date')->after('start_date')->nullable();
        });

        // Populate first_billing_date with start_date for existing subscriptions
        DB::table('subscriptions')->update([
            'first_billing_date' => DB::raw('start_date'),
        ]);

        // Make first_billing_date non-nullable after populating data
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->date('first_billing_date')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('first_billing_date');
        });
    }
};
