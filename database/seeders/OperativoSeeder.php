<?php

namespace Database\Seeders;

use App\Enums\OperativoStatus;
use App\Enums\OperativoType;
use App\Models\Operativo;
use App\Models\User;
use Illuminate\Database\Seeder;

class OperativoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@idenna.gob.ve')->first();

        $operativo = Operativo::query()->updateOrCreate(
            ['code' => 'TER-2026-VE-001'],
            [
                'name' => 'Operativo Terremoto Venezuela 2026',
                'type' => OperativoType::Terremoto,
                'description' => 'Operativo nacional de respuesta ante el terremoto de junio 2026.',
                'status' => OperativoStatus::Active,
                'started_at' => now()->subDays(3),
                'created_by' => $admin?->id,
                'metadata' => [
                    'instituciones' => ['IDENNA', 'CMDNNA', 'CONAPDIS'],
                ],
            ]
        );

        if ($admin) {
            $admin->update(['current_operativo_id' => $operativo->id]);
        }
    }
}
