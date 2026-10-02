<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'superAdmin', 'kasir'], true);
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'inventory_session_id' => ['required', 'integer', 'exists:inventory_sessions,id'],
            'movement_type' => ['required', Rule::in(['stock_in', 'manual_usage', 'waste', 'adjustment', 'opening', 'closing'])],
            'quantity' => ['required_unless:movement_type,opening,closing', 'nullable', 'string', 'regex:/\A-?\d{1,12}(?:\.\d{1,3})?\z/'],
            'physical_stock' => ['required_if:movement_type,opening,closing', 'nullable', 'string', 'regex:/\A\d{1,12}(?:\.\d{1,3})?\z/'],
            'notes' => ['required_if:movement_type,adjustment', 'nullable', 'string', 'max:2000'],
            'reference_key' => ['required_unless:movement_type,opening,closing', 'nullable', 'string', 'max:150', 'regex:/\A[a-zA-Z0-9:_-]+\z/'],
        ];
    }
}
