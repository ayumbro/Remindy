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
        Schema::table('payment_methods', function (Blueprint $table) {
            // Remove unwanted columns
            $table->dropColumn(['type', 'last_four_digits', 'notes']);

            // Add image path column
            $table->string('image_path')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            // Add back the removed columns
            $table->string('type')->nullable()->after('name');
            $table->string('last_four_digits', 4)->nullable()->after('type');
            $table->text('notes')->nullable()->after('description');

            // Remove the image path column
            $table->dropColumn('image_path');
        });
    }
};
