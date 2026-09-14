<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['is_available' => ['required', 'boolean']];
    }

    public function messages(): array
    {
        return [
            'is_available.required' => 'Status ketersediaan wajib diisi.',
            'is_available.boolean' => 'Pilih status ketersediaan yang valid.',
        ];
    }
}
