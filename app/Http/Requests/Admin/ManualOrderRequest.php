<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'kasir', 'superAdmin'], true);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'checkout_token' => ['required', 'uuid'],
            'table_id' => ['required', 'integer', Rule::exists('tables', 'id')->where('is_available', true)],
            'customer_name' => ['required', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'checkout_token.*' => 'Sesi pesanan tidak valid. Buka kembali form Buat Pesanan.',
            'table_id.*' => 'Pilih meja yang tersedia.',
            'customer_name.*' => 'Nama Pemesan wajib diisi berupa teks, maksimal 100 karakter.',
            'items.*' => 'Tambahkan minimal satu menu, maksimal 100 jenis menu.',
            'items.*.product_id.*' => 'Produk dalam pesanan tidak valid atau berulang.',
            'items.*.quantity.*' => 'Jumlah setiap produk harus berupa bilangan bulat antara 1 dan 99.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('customer_name'))) {
            $this->merge(['customer_name' => trim($this->input('customer_name'))]);
        }
    }
}
