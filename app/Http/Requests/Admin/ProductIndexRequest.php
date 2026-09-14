<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class ProductIndexRequest extends ListRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'category' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'category.integer' => 'Pilih kategori yang valid.',
            'category.exists' => 'Kategori yang dipilih tidak ditemukan.',
        ]);
    }
}
