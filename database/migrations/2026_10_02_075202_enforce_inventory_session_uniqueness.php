<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('inventory_sessions')->select('session_date', 'shift_type')
            ->groupBy('session_date', 'shift_type')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates || DB::table('inventory_sessions')->where('status', 'open')->count() > 1) {
            throw new RuntimeException('Duplicate tanggal/shift atau beberapa session open ditemukan. Tidak ada data yang diubah.');
        }
        if (! Schema::hasIndex('inventory_sessions', ['session_date', 'shift_type'], 'unique')) {
            Schema::table('inventory_sessions', function (Blueprint $table): void {
                $table->unique(['session_date', 'shift_type']);
            });
        }
        if (! Schema::hasColumn('inventory_sessions', 'active_guard')) {
            Schema::table('inventory_sessions', function (Blueprint $table): void {
                $table->unsignedTinyInteger('active_guard')->nullable()
                    ->storedAs("CASE WHEN status = 'open' THEN 1 ELSE NULL END");
            });
        }
        if (! Schema::hasIndex('inventory_sessions', ['active_guard'], 'unique')) {
            Schema::table('inventory_sessions', function (Blueprint $table): void {
                $table->unique('active_guard');
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Proteksi session tidak dihapus lewat rollback. Gunakan corrective migration.');
    }
};
