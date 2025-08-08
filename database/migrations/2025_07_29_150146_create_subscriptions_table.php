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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "Netflix Premium", "Spotify Family"
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2); // Default price
            $table->foreignId('currency_id')->constrained();
            $table->foreignId('payment_method_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('billing_cycle', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->integer('billing_interval')->default(1); // e.g., every 2 months
            $table->date('start_date');
            $table->date('next_billing_date');
            $table->date('end_date')->nullable(); // For canceled subscriptions
            $table->enum('status', ['active', 'paused', 'canceled'])->default('active');
            $table->string('website_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['next_billing_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
