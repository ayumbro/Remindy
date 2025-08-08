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
        Schema::create('payment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_history_id')->constrained()->onDelete('cascade');
            $table->string('original_name'); // Original filename
            $table->string('file_path'); // Path to stored file
            $table->string('file_type'); // MIME type
            $table->unsignedBigInteger('file_size'); // File size in bytes
            $table->timestamps();

            $table->index(['payment_history_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_attachments');
    }
};
