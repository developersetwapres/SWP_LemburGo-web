<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminPegawaiUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin-panel') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string|Rule>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'jabatan' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nip' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
        ];
    }
}
