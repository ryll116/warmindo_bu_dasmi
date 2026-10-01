<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start' => ['nullable', 'required_with:end', 'date_format:Y-m-d'],
            'end' => ['nullable', 'required_with:start', 'date_format:Y-m-d', 'after_or_equal:start'],
            'employee' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereNotIn('role', User::ATTENDANCE_EXEMPT_ROLES))],
            'condition' => ['nullable', Rule::in(['present', 'late', 'missing_clock_out'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'start.*' => 'Isi tanggal mulai yang valid (YYYY-MM-DD).',
            'end.*' => 'Isi tanggal akhir yang valid, tidak sebelum tanggal mulai.',
            'employee.*' => 'Pilih karyawan yang wajib absensi.',
            'condition.*' => 'Pilih kondisi absensi yang tersedia.',
            'page.*' => 'Nomor halaman harus bilangan bulat minimal 1.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.reports.attendance');
    }
}
