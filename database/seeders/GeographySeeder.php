<?php

namespace Database\Seeders;

use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Parroquia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        if ($this->importFromConapdis()) {
            $this->command?->info('Geografía importada desde base de datos conapdis.');

            return;
        }

        $this->command?->warn('No se pudo importar desde conapdis. Usando datos mínimos de respaldo.');
        $this->seedFallback();
    }

    private function importFromConapdis(): bool
    {
        try {
            $connection = DB::connection('conapdis');
            $connection->getPdo();

            if (! Schema::connection('conapdis')->hasTable('estados')) {
                return false;
            }

            DB::transaction(function () use ($connection) {
                $estados = $connection->table('estados')
                    ->when(Schema::connection('conapdis')->hasColumn('estados', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                    ->orderBy('id')
                    ->get(['id', 'nombre']);

                foreach ($estados as $estado) {
                    Estado::query()->updateOrCreate(
                        ['id' => $estado->id],
                        [
                            'code' => sprintf('VE-%03d', $estado->id),
                            'name' => $estado->nombre,
                            'is_active' => true,
                        ]
                    );
                }

                $municipios = $connection->table('municipios')
                    ->when(Schema::connection('conapdis')->hasColumn('municipios', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                    ->orderBy('id')
                    ->get(['id', 'estado_id', 'nombre']);

                foreach ($municipios as $municipio) {
                    if (! Estado::query()->whereKey($municipio->estado_id)->exists()) {
                        continue;
                    }

                    Municipio::query()->updateOrCreate(
                        ['id' => $municipio->id],
                        [
                            'estado_id' => $municipio->estado_id,
                            'code' => sprintf('%04d', $municipio->id),
                            'name' => $municipio->nombre,
                            'is_active' => true,
                        ]
                    );
                }

                if (Schema::connection('conapdis')->hasTable('parroquias')) {
                    $parroquias = $connection->table('parroquias')
                        ->when(Schema::connection('conapdis')->hasColumn('parroquias', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                        ->orderBy('id')
                        ->get(['id', 'municipio_id', 'nombre']);

                    foreach ($parroquias as $parroquia) {
                        if (! Municipio::query()->whereKey($parroquia->municipio_id)->exists()) {
                            continue;
                        }

                        Parroquia::query()->updateOrCreate(
                            ['id' => $parroquia->id],
                            [
                                'municipio_id' => $parroquia->municipio_id,
                                'code' => sprintf('%06d', $parroquia->id),
                                'name' => $parroquia->nombre,
                                'is_active' => true,
                            ]
                        );
                    }
                }
            });

            return Estado::query()->count() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function seedFallback(): void
    {
        foreach ([
            ['code' => 'VE-024', 'name' => 'Distrito Capital'],
            ['code' => 'VE-014', 'name' => 'Miranda'],
            ['code' => 'VE-023', 'name' => 'Zulia'],
        ] as $estado) {
            Estado::query()->updateOrCreate(['code' => $estado['code']], $estado);
        }
    }
}
