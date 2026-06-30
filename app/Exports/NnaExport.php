<?php

namespace App\Exports;

use App\Models\NnaRegistration;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class NnaExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly ?int $operativoId = null) {}

    public function collection()
    {
        return NnaRegistration::query()
            ->when($this->operativoId, fn ($q) => $q->where('operativo_id', $this->operativoId))
            ->with(['gender'])
            ->orderBy('registered_at', 'desc')
            ->get();
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
