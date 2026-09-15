<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->routeIs('customer.checkout.store')) {
            return ['checkout_token' => ['required', 'uuid'], 'notes' => ['nullable', 'string', 'max:1000']];
        }

        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Keranjang masih kosong. Tambahkan menu terlebih dahulu.',
            'items.array' => 'Data keranjang tidak valid. Silakan kembali ke menu.',
            'items.min' => 'Keranjang masih kosong.',
            'items.max' => 'Maksimal 100 jenis menu dalam satu pesanan.',
            'items.*.product_id.*' => 'Produk dalam keranjang tidak valid atau berulang.',
            'items.*.quantity.*' => 'Jumlah setiap produk harus berupa bilangan bulat antara 1 dan 99.',
            'checkout_token.*' => 'Sesi checkout tidak valid. Silakan review ulang dari keranjang.',
            'notes.*' => 'Catatan harus berupa teks, maksimal 1000 karakter.',
        ];
    }
}
