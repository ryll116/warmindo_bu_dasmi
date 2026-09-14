<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductSeeder extends Seeder
{
    /**
     * Seed data produk untuk Warkop Mbok Dasmi.
     * Data diambil dari Daftar Makanan & Daftar Minuman.
     *
     * CATATAN: Harga (price) tidak tercantum di sumber menu (PDF),
     * sehingga di-default ke 0. Silakan update harga secara manual
     * setelah seeding, misalnya lewat UPDATE query atau admin panel.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Pastikan CategorySeeder dijalankan lebih dulu
        $this->call(CategorySeeder::class);

        $data = [
            'Aneka Olahan Nasi' => [
                'Bubur Ayam Original',
                'Bubur Ayam Spesial',
                'Bubur Ayam Telur Ceplok',
                'Bubur Ayam Telur Orak Arik',
                'Bubur Ayam Telur Dadar',
                'Bubur Ayam Telur Rebus',
                'Bubur Ayam Telur Ayam Kampung',
                'Bubur Ayam Kornet',
                'Nasi Telur Dadar',
                'Nasi Telur Ceplok',
                'Nasi Telur Orak Arik',
                'Nasi Ayam Geprek',
                'Nasi Putih',
            ],

            'Aneka Bubur' => [
                'Bubur Kacang Ijo',
                'Bubur Ketan Hitam',
                'Bubur Kacang Ijo Ketan Hitam',
                'Bubur Kacang Ijo Roti Tawar',
                'Bubur Ketan Hitam Roti Tawar',
                'Bubur Kacang Ijo Ketan Hitam Roti Tawar',
                'Bubur Kacang Ijo Spesial',
                'Bubur Ketan Hitam Spesial',
            ],

            'Aneka Olahan Telur' => [
                'Telur Ayam Kampung',
                'Telur Ceplok',
                'Telur Dadar',
                'Telur Rebus',
            ],

            'Aneka Sate' => [
                'Sate Ati-Ampela / Usus',
                'Sate Telur Puyuh',
                'Kornet',
            ],

            'Aneka Mie' => [
                'Mie Nyemek',
                'Mie Dok-Dok',
                'Mie Tektek Oseng',
                'Mie Tektek Spesial',
                'Mie Bangladesh',
                'Kwetiau Goreng',
                'Kwetiau Kuah',
            ],

            'Mie Instant' => [
                'Indomie Goreng Original',
                'Indomie Rebus Original',
                'Indomie Rebus, Telur',
                'Indomie Rebus, Telur Ayam Kampung',
                'Indomie Goreng, Telur Ceplok',
                'Indomie Goreng, Telur Dadar',
                'Indomie Goreng / Rebus, Kornet',
                'Indomie Goreng Telur Kornet',
                'Indomie Rebus Telur Kornet',
                'Mie Omelet',
                'Indomie Double',
                'Indomie Goreng Telur',
            ],

            'Nasi Goreng' => [
                'Nasi Goreng Original',
                'Nasi Goreng Gila',
                'Nasi Goreng Ayam Suwir',
                'Nasi Goreng Spesial',
                'Nasi Goreng, Telur Ceplok',
                'Nasi Goreng, Telur Dadar',
                'Nasi Goreng, Ati Ampela',
                'Nasi Goreng, Kornet',
                'Nasi Goreng, Telur Kornet',
                'Nasi Goreng Magelangan',
            ],

            'Makanan Ringan' => [
                'Getuk Goreng',
                'Getuk Kukus',
                'Roti Bakar 1 Rasa - Coklat',
                'Roti Bakar 1 Rasa - Keju',
                'Roti Bakar 2 Rasa - Coklat Keju',
                'Pancong Lumer 1 Rasa Coklat',
                'Pancong Matang 1 Rasa Coklat',
                'Pancong Lumer 1 Rasa - Keju',
                'Pancong Matang 1 Rasa - Keju',
                'Pancong Lumer 2 Rasa Coklat Keju',
                'Pancong Matang 2 Rasa Coklat Keju',
                'Pancong Lumer Original',
                'Pisang Panggang, Coklat / Keju',
                'Pisang Panggang, Coklat & Keju',
                'Emping',
                'Macaroni',
                'Kulit Ayam Krispi',
                'Tahu Goreng',
                'Tempe Goreng',
                'Kentang Goreng',
            ],

            'Minuman' => [
                'Es Kopi Susu Gula Aren',
                'Es Suket Original',
                'Es Suket Vanila',
                'Es Suket Red Velvet',
                'Es Suket Thai Tea',
                'Es Suket Green Tea',
                'Es Suket Coklat',
                'Susu Soda',
                'Susu Soda Gembira',
                'Es Jeruk Peras',
                'Es Nutrisari - Anggur',
                'Es Nutrisari - Anggur Hijau',
                'Es Nutrisari - Blewah',
                'Es Nutrisari - Cocopandan',
                'Es Nutrisari - Jeruk Peras',
                'Es Nutrisari - Florida Orange',
                'Es Nutrisari - Leci',
                'Es Nutrisari - Strawberry',
                'Es Nutrisari - Sweet Mango',
                'Es Kopi Mix Indo-Coffee',
                'Es Kopi Capucino',
                'Kopi Capucino',
                'Es Beng Beng (Coklat)',
                'Es Milo',
                'Es Susu Dancow Putih',
                'Es Susu Dancow Coklat',
                'Es Teh Tawar',
                'Es Teh Manis',
                'Es Extra Joss',
                'Es Kuku Bima',
                'Aqua Botol 330 ml',
                'Le Minerale Botol 330 ml',
                'Es Batu Kristal',
                'Chocolatos Coklat',
                'Chocolatos Matcha',
                'Es Cincau',
                'Es Creamy Latte',
                'Es Extra Joss Susu',
                'Es Good Day Freeze',
                'Es Kuku Bima Susu',
                'Es Nutrisari - Jeruk Nipis',
                'Es Thai Tea',
                'Floridina',
                'Good Day Mocacinno',
                'Nipis Madu Lime Soda',
                'Ovaltine',
                'Susu Milku',
                'Teh Pucuk',
                'Teh Tarik',
                'Jahe Merah',
                'Susu Jahe',
                'Jeruk Peras',
                'Teh Manis',
                'Teh Talua',
                'Kopi Mix Indo-Coffee',
                'Kopi Hitam Kapal Api',
                'Kopi Susu Kapal Api',
                'Kopi Susu Abc',
                'Creamy Latte',
                'Aneka Kopi Panas',
                'Beng Beng (Coklat)',
                'Milo',
                'Susu Dancow Putih',
                'Susu Dancow Coklat',
                'Thai Tea Panas',
            ],
        ];

        // Prefix kode produk per kategori, dipakai untuk generate product_code
        $prefixMap = [
            'Aneka Olahan Nasi'   => 'NAS',
            'Aneka Bubur'         => 'BUB',
            'Aneka Olahan Telur' => 'TLR',
            'Aneka Sate'          => 'SAT',
            'Aneka Mie'           => 'MIE',
            'Mie Instant'         => 'MII',
            'Nasi Goreng'         => 'NGR',
            'Makanan Ringan'      => 'RNG',
            'Minuman'             => 'MIN',
        ];

        $totalInserted = 0;

        foreach ($data as $categoryName => $products) {
            $category = DB::table('categories')
                ->where('category_name', $categoryName)
                ->first();

            if (!$category) {
                $this->command->warn("Kategori '{$categoryName}' tidak ditemukan, dilewati.");
                continue;
            }

            $prefix = $prefixMap[$categoryName] ?? 'PRD';

            foreach ($products as $index => $productName) {
                $code = $prefix . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);

                DB::table('products')->updateOrInsert(
                    ['product_code' => $code],
                    [
                        'product_name'  => $productName,
                        'category_id'   => $category->id,
                        'price'         => 0, // TODO: update harga, tidak tersedia di sumber menu
                        'is_available'  => true,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]
                );

                $totalInserted++;
            }
        }

        $this->command->info("ProductSeeder selesai: {$totalInserted} produk berhasil di-seed.");
    }
}
