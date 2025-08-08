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
        Schema::create('payment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->foreignId('currency_id')->constrained();
            $table->foreignId('payment_method_id')->nullable()->constrained()->onDelete('set null');
            $table->date('payment_date');
            $table->enum('status', ['paid', 'pending', 'failed', 'refunded'])->default('paid');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'payment_date']);
            $table->index(['payment_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_histories');
    }
};
