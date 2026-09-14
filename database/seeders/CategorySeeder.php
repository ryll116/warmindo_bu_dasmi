<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CategorySeeder extends Seeder
{
    /**
     * Seed kategori produk untuk Warkop Mbok Dasmi.
     * Kategori diambil dari Daftar Makanan & Daftar Minuman.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $categories = [
            'Aneka Olahan Nasi',
            'Aneka Bubur',
            'Aneka Olahan Telur',
            'Aneka Sate',
            'Aneka Mie',
            'Mie Instant',
            'Nasi Goreng',
            'Makanan Ringan',
            'Minuman',
        ];

        foreach ($categories as $name) {
            DB::table('categories')->updateOrInsert(
                ['category_name' => $name],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->command->info('CategorySeeder selesai: ' . count($categories) . ' kategori berhasil di-seed.');
    }
}   
