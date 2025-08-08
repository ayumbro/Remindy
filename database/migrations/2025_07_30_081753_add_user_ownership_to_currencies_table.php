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
        Schema::table('currencies', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('is_active')->constrained()->onDelete('cascade');
            $table->boolean('is_system_default')->default(false)->after('user_id');
        });

        // Mark existing currencies as system defaults
        DB::table('currencies')->update([
            'is_system_default' => true,
            'user_id' => null,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'is_system_default']);
        });
    }
};
