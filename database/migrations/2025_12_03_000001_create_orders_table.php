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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('order_number')->unique()->nullable();
            $table->string('customer_name');
            $table->string('phone', 20);
            $table->text('address');
            $table->string('payment_method', 50)->default('cash_on_delivery');
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->date('delivery_date')->nullable();
            $table->time('preferred_time')->nullable();
            $table->enum('status', ['Pending', 'Dispatched', 'Completed', 'Cancelled'])->default('Pending');
            $table->string('transaction_id')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('order_date')->useCurrent();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['phone']);
            $table->index(['order_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
