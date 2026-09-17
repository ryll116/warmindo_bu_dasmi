<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user instanceof User ? $user : null)],
            'role' => ['required', Rule::in(['admin', 'kasir'])],
            'password' => [$user instanceof User ? 'nullable' : 'required', 'string', 'max:255', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'email.unique' => 'Email sudah digunakan oleh akun lain.',
            'role.in' => 'Role harus admin atau kasir.',
            'password.confirmed' => 'Konfirmasi password tidak sesuai.',
            'password.min' => 'Password minimal 8 karakter.',
        ];
    }
}
