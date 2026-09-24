<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('products')->whereNotNull('resto_id')
            ->whereNotIn('resto_id', DB::table('master_resto')->select('id'))->exists()) {
            throw new RuntimeException('Ada products.resto_id yang tidak ditemukan pada master_resto. Perbaiki referensi sebelum menjalankan migration.');
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('resto_id')->nullable()->change();
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->foreign('resto_id')->references('id')->on('master_resto')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('products')->where('resto_id', '>', 2147483647)->exists()) {
            throw new RuntimeException('resto_id melebihi kapasitas INT; rollback dibatalkan untuk menjaga data.');
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['resto_id']);
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->integer('resto_id')->nullable()->change();
        });
    }
};
