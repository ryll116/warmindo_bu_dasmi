<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventorySessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'superAdmin', 'kasir'], true);
    }

    public function rules(): array
    {
        return $this->routeIs('admin.inventory.open')
            ? ['shift_type' => ['required', Rule::in(array_keys(config('attendance.shifts', [])))]]
            : ['manual_usage_complete' => ['required', 'accepted']];
    }
}
