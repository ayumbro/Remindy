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
            $table->foreignId('default_currency_id')->nullable()->after('locale')->constrained('currencies')->onDelete('set null');
            $table->json('enabled_currencies')->nullable()->after('default_currency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_currency_id']);
            $table->dropColumn(['default_currency_id', 'enabled_currencies']);
        });
    }
};
