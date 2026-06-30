<?php

namespace Database\Seeders;

use App\Enums\AttentionLocationType;
use App\Models\AttentionLocation;
use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Operativo;
use Illuminate\Database\Seeder;

class AttentionLocationSeeder extends Seeder
{
    public function run(): void
    {
        $operativo = Operativo::query()->where('code', 'TER-2026-VE-001')->first();
        $dc = Estado::query()->where('code', 'VE-A')->first();
        $libertador = $dc
            ? Municipio::query()->where('estado_id', $dc->id)->where('code', '0101')->first()
            : null;

        $locations = [
            ['type' => AttentionLocationType::Hospital, 'name' => 'Hospital JM de los Ríos'],
            ['type' => AttentionLocationType::Hospital, 'name' => 'Hospital Pérez Carreño'],
            ['type' => AttentionLocationType::Refugio, 'name' => 'Refugio Parque del Este'],
            ['type' => AttentionLocationType::Campamento, 'name' => 'Campamento Plaza Bolívar'],
            ['type' => AttentionLocationType::Plaza, 'name' => 'Plaza Venezuela'],
        ];

        foreach ($locations as $location) {
            AttentionLocation::query()->updateOrCreate(
                ['name' => $location['name'], 'type' => $location['type']],
                [
                    'operativo_id' => $operativo?->id,
                    'estado_id' => $dc?->id,
                    'municipio_id' => $libertador?->id,
                    'is_active' => true,
                ]
            );
        }
    }
}
