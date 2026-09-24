<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesReportRequest extends FormRequest
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
        return [
            'resto' => ['nullable', 'integer', Rule::exists('master_resto', 'id')],
            'period' => ['nullable', Rule::in(['today', 'yesterday', 'last7', 'month', 'custom'])],
            'start' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'end' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'resto.*' => 'Pilih resto yang tersedia.',
            'period.*' => 'Pilih periode laporan yang valid.',
            'start.*' => 'Isi tanggal awal yang valid (YYYY-MM-DD).',
            'end.*' => 'Isi tanggal akhir yang valid, tidak sebelum tanggal awal.',
            'search.*' => 'Pencarian harus berupa teks maksimal 100 karakter.',
            'page.*' => 'Nomor halaman harus berupa bilangan bulat minimal 1.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.reports.sales');
    }
}
