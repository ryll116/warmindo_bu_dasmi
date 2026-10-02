<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('stock_movements')->whereNotNull('created_by')->whereNotExists(function (Builder $query): void {
            $query->selectRaw('1')->from('users')->whereColumn('users.id', 'stock_movements.created_by');
        })->exists()) {
            throw new RuntimeException('Orphan created_by ditemukan. Rekonsiliasi manual diperlukan.');
        }

        if (! Schema::hasColumn('stock_movements', 'physical_stock')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->decimal('physical_stock', 15, 3)->nullable();
            });
        }
        if (! Schema::hasColumn('stock_movements', 'reference_key')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->string('reference_key', 150)->nullable();
            });
        }

        foreach ([['inventory_item_id', 'created_at'], ['movement_type'], ['inventory_session_id']] as $columns) {
            $equivalent = collect(Schema::getIndexes('stock_movements'))->contains(
                fn (array $index): bool => array_slice($index['columns'], 0, count($columns)) === $columns
            );
            if (! $equivalent) {
                Schema::table('stock_movements', function (Blueprint $table) use ($columns): void {
                    $table->index($columns);
                });
            }
        }
        if (! Schema::hasIndex('stock_movements', ['reference_key'], 'unique')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->unique('reference_key');
            });
        }
        $foreignKeys = collect(Schema::getForeignKeys('stock_movements'));
        if (! $foreignKeys->contains(fn (array $key): bool => $key['columns'] === ['created_by'])) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        $sessionKey = $foreignKeys->first(fn (array $key): bool => $key['columns'] === ['inventory_session_id']);
        if ($sessionKey && ! in_array(strtolower($sessionKey['on_delete']), ['restrict', 'no action'], true)) {
            Schema::table('stock_movements', function (Blueprint $table) use ($sessionKey): void {
                $table->dropForeign($sessionKey['name'] ?? ['inventory_session_id']);
            });
            $sessionKey = null;
        }
        if (! $sessionKey) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->foreign('inventory_session_id')->references('id')->on('inventory_sessions')->restrictOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Fondasi ledger audit tidak dihapus lewat rollback. Gunakan corrective migration.');
    }
};
