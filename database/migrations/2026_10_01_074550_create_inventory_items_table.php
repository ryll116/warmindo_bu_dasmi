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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('resto_id')
                ->nullable()
                ->constrained('master_resto')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('item_code', 50)->unique();
            $table->string('item_name', 150);
            $table->string('unit', 30);

            $table->decimal('current_stock', 15, 3)->default(0);
            $table->decimal('minimum_stock', 15, 3)->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['resto_id', 'is_active']);
            $table->index('item_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
