<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function authenticate(): void
    {
        $key = 'login:'.hash('sha256', Str::lower($this->string('email')->trim()->toString()).'|'.$this->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan login. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }

        if (! Auth::guard('web')->attempt($this->only('email', 'password'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email atau password tidak sesuai.']);
        }

        RateLimiter::clear($key);
    }
}
