<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $roles = Role::query()->pluck('name')->all();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'document_id' => ['nullable', 'string', 'max:32', Rule::unique('users', 'document_id')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'organization' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'current_operativo_id' => ['nullable', 'integer', 'exists:operativos,id'],
            'role' => ['sometimes', 'string', Rule::in($roles)],
        ];
    }
}
