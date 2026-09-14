<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'Masukkan nama makanan atau minuman.',
            'search.max' => 'Pencarian maksimal 255 karakter.',
            'category.integer' => 'Pilih kategori yang tersedia.',
            'category.min' => 'Pilih kategori yang tersedia.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('customer.menu', ['qr_token' => $this->route('qr_token')]);
    }
}
