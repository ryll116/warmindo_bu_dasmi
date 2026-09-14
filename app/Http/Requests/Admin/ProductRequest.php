<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
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
        $product = $this->route('product');

        return [
            'product_code' => ['required', 'string', 'max:255', Rule::unique('products', 'product_code')->ignore($product instanceof Product ? $product : null)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'product_name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'is_available' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'product_code.unique' => 'Product Code sudah digunakan oleh produk lain.',
            'category_id.integer' => 'Pilih kategori yang valid.',
            'category_id.exists' => 'Kategori yang dipilih tidak ditemukan.',
            'product_code.max' => 'Product Code maksimal 255 karakter.',
            'product_name.max' => 'Product Name maksimal 255 karakter.',
            'price.numeric' => 'Price harus berupa angka.',
            'price.decimal' => 'Price maksimal memiliki 2 angka desimal.',
            'price.min' => 'Price tidak boleh negatif.',
            'price.max' => 'Price maksimal 9.999.999.999,99.',
            'is_available.boolean' => 'Pilih status ketersediaan yang valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'product_code' => 'Product Code',
            'category_id' => 'Category',
            'product_name' => 'Product Name',
            'price' => 'Price',
            'is_available' => 'Is Available',
        ];
    }
}
