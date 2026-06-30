<?php

namespace App\Http\Requests\Operativo;

use App\Enums\OperativoStatus;
use App\Enums\OperativoType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperativoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operativos.manage') ?? false;
    }

    public function rules(): array
    {
        $operativoId = $this->route('operativo')?->id;

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('operativos', 'code')->ignore($operativoId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(OperativoType::class)],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(OperativoStatus::class)],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
