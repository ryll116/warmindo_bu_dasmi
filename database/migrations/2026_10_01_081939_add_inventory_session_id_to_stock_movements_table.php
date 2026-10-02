<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('inventory_session_id')
                ->nullable()
                ->after('inventory_item_id')
                ->constrained('inventory_sessions')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(
                ['inventory_session_id', 'movement_type'],
                'stock_movements_session_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(
                ['inventory_session_id']
            );

            $table->dropIndex(
                'stock_movements_session_type_index'
            );

            $table->dropColumn('inventory_session_id');
        });
    }
};