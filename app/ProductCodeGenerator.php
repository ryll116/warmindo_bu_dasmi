<?php

namespace App;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class ProductCodeGenerator
{
    public function next(Category $category, bool $lock = false): string
    {
        $prefix = $category->category_code;
        if (! is_string($prefix) || ! preg_match('/^[A-Z0-9]{3}$/D', $prefix)) {
            throw ValidationException::withMessages(['category_id' => 'Kode kategori harus diisi dengan 3 huruf kapital atau angka. Hubungi pengelola kategori.']);
        }
        if (Category::where('id', '!=', $category->id)->whereRaw('UPPER(TRIM(category_code)) = ?', [$prefix])->exists()) {
            throw ValidationException::withMessages(['category_id' => 'Kode kategori digunakan oleh lebih dari satu kategori. Perbaiki kode kategori terlebih dahulu.']);
        }
        $query = Product::whereLike('product_code', $prefix.'-%');
        if ($lock) {
            $query->lockForUpdate();
        }
        $highest = '0';
        foreach ($query->pluck('product_code') as $code) {
            if (! preg_match('/^'.preg_quote($prefix, '/').'-(\d{3,})$/iD', $code, $matches)) {
                continue;
            }
            $number = ltrim($matches[1], '0') ?: '0';
            if (strlen($number) > strlen($highest) || (strlen($number) === strlen($highest) && strcmp($number, $highest) > 0)) {
                $highest = $number;
            }
        }
        $next = $this->increment($highest);
        $code = $prefix.'-'.str_pad($next, 3, '0', STR_PAD_LEFT);
        if (strlen($code) > 255) {
            throw ValidationException::withMessages(['category_id' => 'Nomor produk kategori ini sudah melampaui panjang kode yang tersedia.']);
        }

        return $code;
    }

    private function increment(string $number): string
    {
        for ($index = strlen($number) - 1; $index >= 0; $index--) {
            if ($number[$index] !== '9') {
                $number[$index] = (string) ((int) $number[$index] + 1);

                return $number;
            }
            $number[$index] = '0';
        }

        return '1'.$number;
    }
}
