<?php

namespace App\Http\Requests\Admin;

use App\Models\Table;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $table = $this->route('table');

        return [
            'table_no' => ['required', 'integer', 'min:1', 'max:2147483647', Rule::unique('tables', 'table_no')->ignore($table instanceof Table ? $table : null)],
            'is_available' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'table_no.required' => 'Nomor Meja wajib diisi.',
            'table_no.integer' => 'Nomor Meja harus berupa bilangan bulat.',
            'table_no.min' => 'Nomor Meja minimal 1.',
            'table_no.max' => 'Nomor Meja maksimal 2.147.483.647.',
            'table_no.unique' => 'Nomor Meja sudah digunakan.',
            'is_available.required' => 'Status wajib dipilih.',
            'is_available.boolean' => 'Pilih status yang valid.',
        ];
    }
}
