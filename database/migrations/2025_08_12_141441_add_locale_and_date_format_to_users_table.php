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
            // Add locale column if it doesn't exist
            if (!Schema::hasColumn('users', 'locale')) {
                $table->string('locale')->default('en')->after('updated_at');
            }

            // Add date_format column if it doesn't exist
            if (!Schema::hasColumn('users', 'date_format')) {
                $table->string('date_format')->default('Y-m-d')->after('locale');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only drop columns if they exist and were added by this migration
            if (Schema::hasColumn('users', 'date_format')) {
                $table->dropColumn('date_format');
            }

            if (Schema::hasColumn('users', 'locale')) {
                $table->dropColumn('locale');
            }
        });
    }
};
