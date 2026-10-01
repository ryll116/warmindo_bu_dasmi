<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_recipes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->decimal('quantity', 15, 3);

            $table->timestamps();

            $table->unique(
                ['product_id', 'inventory_item_id'],
                'product_recipes_product_item_unique'
            );

            $table->index('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recipes');
    }
};