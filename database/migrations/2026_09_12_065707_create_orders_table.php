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
            $table->uuid('id')->primary();
            $table->foreignId('table_id')->constrained('tables');
            $table->string('order_status', 50)->default('pending');
            $table->string('payment_type', 50)->nullable();
            $table->string('payment_status', 50)->default('unpaid');
            $table->decimal('total', 20, 2)->default(0);
            $table->dateTime('payment_time')->nullable();
            $table->timestamps();
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
