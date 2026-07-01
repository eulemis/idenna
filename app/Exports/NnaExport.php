<?php

namespace App\Exports;

use App\Models\NnaRegistration;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class NnaExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly ?int $operativoId = null) {}

    public function query(): Builder
    {
        return NnaRegistration::query()
            ->when($this->operativoId, fn ($q) => $q->where('operativo_id', $this->operativoId))
            ->select([
                'id',
                'registration_code',
                'uuid',
                'first_name',
                'last_name',
                'age_years',
                'birth_date',
                'status',
                'registered_at',
                'notes',
            ])
            ->orderByDesc('registered_at');
    }

    public function headings(): array
    {
        return [
            'Código',
            'Nombres',
            'Apellidos',
            'Edad',
            'Fecha nacimiento',
            'Estado registro',
            'Fecha registro',
            'Notas',
        ];
    }

    public function map($nna): array
    {
        return [
            $nna->registration_code ?? $nna->uuid,
            $nna->first_name,
            $nna->last_name,
            $nna->age_years,
            $nna->birth_date?->format('Y-m-d'),
            $nna->status?->value ?? $nna->status,
            $nna->registered_at?->format('Y-m-d H:i'),
            $nna->notes,
        ];
    }
}
