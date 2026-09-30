<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'resto_id' => ['required', 'integer', Rule::exists('master_resto', 'id')],
            'product_name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'disc' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'is_available' => ['required', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
            'img' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'resto_id.integer' => 'Pilih resto yang valid.',
            'resto_id.exists' => 'Resto yang dipilih tidak ditemukan.',
            'category_id.integer' => 'Pilih kategori yang valid.',
            'category_id.exists' => 'Kategori yang dipilih tidak ditemukan.',
            'product_name.max' => 'Product Name maksimal 255 karakter.',
            'price.numeric' => 'Price harus berupa angka.',
            'price.decimal' => 'Price maksimal memiliki 2 angka desimal.',
            'price.min' => 'Price tidak boleh negatif.',
            'price.max' => 'Price maksimal 9.999.999.999,99.',
            'disc.*' => 'Diskon harus berupa persentase 0–100 dengan maksimal 2 angka desimal.',
            'is_available.boolean' => 'Pilih status ketersediaan yang valid.',
            'image.*' => 'Foto harus berupa JPG, JPEG, PNG, atau WebP yang valid, maksimal 2 MB.',
            'remove_image.*' => 'Pilihan hapus foto tidak valid.',
            'img.*' => 'Gunakan input Foto Produk untuk mengunggah foto.',
        ];
    }

    public function attributes(): array
    {
        return [
            'product_code' => 'Product Code',
            'category_id' => 'Category',
            'resto_id' => 'Resto / Penyedia',
            'product_name' => 'Product Name',
            'price' => 'Price',
            'is_available' => 'Is Available',
        ];
    }
}
