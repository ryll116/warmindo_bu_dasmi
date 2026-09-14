<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'category_name' => ['required', 'string', 'max:255', Rule::unique('categories', 'category_name')->ignore($category instanceof Category ? $category : null)],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'category_name.required' => 'Category Name wajib diisi.',
            'category_name.string' => 'Category Name harus berupa teks.',
            'category_name.max' => 'Category Name maksimal 255 karakter.',
            'category_name.unique' => 'Category Name sudah digunakan.',
            'status.required' => 'Status wajib dipilih.',
            'status.string' => 'Pilih status yang valid.',
            'status.in' => 'Pilih status active atau inactive.',
        ];
    }
}
