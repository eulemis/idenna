<?php

namespace App\Http\Requests\Operativo;

use App\Enums\OperativoStatus;
use App\Enums\OperativoType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperativoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operativos.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:operativos,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(OperativoType::class)],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(OperativoStatus::class)],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
