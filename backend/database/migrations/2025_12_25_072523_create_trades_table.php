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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buy_order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('sell_order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->string('symbol', 10);
            $table->decimal('price', 18, 8); // Execution price
            $table->decimal('amount', 18, 8); // Executed quantity
            $table->decimal('total', 18, 8); // Total USD value (price * amount)
            $table->decimal('commission', 18, 8)->default(0); // 1.5% commission
            $table->timestamps();

            $table->index(['symbol', 'created_at']);
            $table->index(['buyer_id']);
            $table->index(['seller_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
