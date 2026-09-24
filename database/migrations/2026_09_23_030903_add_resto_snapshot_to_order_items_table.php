<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('resto_id')->nullable()->index()->constrained('master_resto')->restrictOnDelete();
            $table->string('resto_name', 100)->nullable();
        });

        /** Development backfill reflects current ownership, not verified historical ownership. */
        DB::table('order_items')->whereNull('resto_id')->whereNull('resto_name')->update([
            'resto_id' => DB::raw('(SELECT products.resto_id FROM products WHERE products.id = order_items.product_id)'),
            'resto_name' => DB::raw('(SELECT master_resto.resto_name FROM products JOIN master_resto ON master_resto.id = products.resto_id WHERE products.id = order_items.product_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign(['resto_id']);
            $table->dropIndex(['resto_id']);
            $table->dropColumn(['resto_id', 'resto_name']);
        });
    }
};
