<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'superAdmin'], true);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['category_id' => ['required', 'integer', Rule::exists('categories', 'id')]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['category_id.*' => 'Pilih kategori yang tersedia.'];
    }
}
