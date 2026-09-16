<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends FormRequest
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
        if ($this->routeIs('admin.orders.status')) {
            return ['order_status' => ['required', Rule::in(Order::STATUSES)]];
        }
        if ($this->routeIs('admin.orders.payment')) {
            return ['payment_type' => ['required', Rule::in(array_keys(Order::PAYMENT_TYPES))]];
        }

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'tab' => ['nullable', Rule::in(['active', 'completed', 'all'])],
            'order_status' => ['nullable', Rule::in(Order::STATUSES)],
            'payment_status' => ['nullable', Rule::in(['unpaid', 'paid'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.*' => 'Pencarian harus berupa teks maksimal 255 karakter.',
            'tab.*' => 'Pilih tab Aktif, Selesai, atau Semua.',
            'order_status.*' => 'Pilih status pesanan yang valid.',
            'payment_status.*' => 'Pilih status pembayaran yang valid.',
            'payment_type.*' => 'Pilih Cash atau QRIS Manual.',
            'page.*' => 'Nomor halaman harus berupa bilangan bulat minimal 1.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->routeIs('admin.orders.index') ? route('admin.orders.index') : route('admin.orders.show', $this->route('order'));
    }
}
