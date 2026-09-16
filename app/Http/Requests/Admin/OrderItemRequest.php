<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OrderItemRequest extends FormRequest
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
        if ($this->routeIs('admin.orders.items.destroy')) {
            return [];
        }
        $rules = ['quantity' => ['required', 'integer', 'min:1', 'max:99']];
        if ($this->routeIs('admin.orders.items.store')) {
            $rules['product_id'] = ['required', 'integer', 'min:1'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['quantity.*' => 'Jumlah harus berupa bilangan bulat antara 1 dan 99.', 'product_id.*' => 'Pilih menu yang tersedia.'];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.orders.show', $this->route('order'));
    }
}
