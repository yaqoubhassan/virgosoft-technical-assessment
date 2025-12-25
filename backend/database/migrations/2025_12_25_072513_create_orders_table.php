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
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('symbol', 10); // e.g., BTC, ETH
            $table->enum('side', ['buy', 'sell']);
            $table->decimal('price', 18, 8); // Price per unit
            $table->decimal('amount', 18, 8); // Quantity
            $table->decimal('locked_usd', 18, 8)->default(0); // USD locked for buy orders
            $table->tinyInteger('status')->default(1); // 1=open, 2=filled, 3=cancelled
            $table->timestamps();

            $table->index(['symbol', 'side', 'status', 'price']);
            $table->index(['user_id', 'status']);
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
