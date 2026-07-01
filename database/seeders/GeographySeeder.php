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
            $this->command?->info(sprintf(
                'Geografía importada desde %s: %d estados, %d municipios, %d parroquias.',
                config('database.connections.conapdis.database'),
                Estado::query()->count(),
                Municipio::query()->count(),
                Parroquia::query()->count(),
            ));

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

            $database = config('database.connections.conapdis.database');
            if (! Schema::connection('conapdis')->hasTable('estados')) {
                $this->command?->error("La base «{$database}» no tiene la tabla «estados».");

                return false;
            }

            $this->resetLocalGeography();

            $estados = $connection->table('estados')
                ->when(
                    Schema::connection('conapdis')->hasColumn('estados', 'deleted_at'),
                    fn ($q) => $q->whereNull('deleted_at')
                )
                ->orderBy('id')
                ->get(['id', 'nombre']);

            foreach ($estados as $estado) {
                Estado::query()->create([
                    'id' => $estado->id,
                    'code' => sprintf('VE-%03d', $estado->id),
                    'name' => $estado->nombre,
                    'is_active' => true,
                ]);
            }

            $municipios = $connection->table('municipios')
                ->when(
                    Schema::connection('conapdis')->hasColumn('municipios', 'deleted_at'),
                    fn ($q) => $q->whereNull('deleted_at')
                )
                ->orderBy('id')
                ->get(['id', 'estado_id', 'nombre']);

            $skippedMunicipios = 0;
            foreach ($municipios as $municipio) {
                if (! Estado::query()->whereKey($municipio->estado_id)->exists()) {
                    $skippedMunicipios++;
                    continue;
                }

                Municipio::query()->create([
                    'id' => $municipio->id,
                    'estado_id' => $municipio->estado_id,
                    'code' => sprintf('M-%04d', $municipio->id),
                    'name' => $municipio->nombre,
                    'is_active' => true,
                ]);
            }

            $skippedParroquias = 0;
            if (Schema::connection('conapdis')->hasTable('parroquias')) {
                $parroquias = $connection->table('parroquias')
                    ->when(
                        Schema::connection('conapdis')->hasColumn('parroquias', 'deleted_at'),
                        fn ($q) => $q->whereNull('deleted_at')
                    )
                    ->orderBy('id')
                    ->get(['id', 'municipio_id', 'nombre']);

                foreach ($parroquias as $parroquia) {
                    if (! Municipio::query()->whereKey($parroquia->municipio_id)->exists()) {
                        $skippedParroquias++;
                        continue;
                    }

                    Parroquia::query()->create([
                        'id' => $parroquia->id,
                        'municipio_id' => $parroquia->municipio_id,
                        'code' => sprintf('P-%04d', $parroquia->id),
                        'name' => $parroquia->nombre,
                        'is_active' => true,
                    ]);
                }
            }

            if ($skippedMunicipios > 0) {
                $this->command?->warn("Municipios omitidos (estado inexistente): {$skippedMunicipios}");
            }
            if ($skippedParroquias > 0) {
                $this->command?->warn("Parroquias omitidas (municipio inexistente): {$skippedParroquias}");
            }

            return Estado::query()->count() > 0;
        } catch (\Throwable $e) {
            $this->command?->error('Error al importar geografía: '.$e->getMessage());

            return false;
        }
    }

    private function resetLocalGeography(): void
    {
        Schema::disableForeignKeyConstraints();
        Parroquia::query()->truncate();
        Municipio::query()->truncate();
        Estado::query()->truncate();
        Schema::enableForeignKeyConstraints();
    }

    private function seedFallback(): void
    {
        $this->resetLocalGeography();

        foreach ([
            ['id' => 1, 'code' => 'VE-001', 'name' => 'Distrito Capital'],
            ['id' => 14, 'code' => 'VE-014', 'name' => 'Miranda'],
            ['id' => 23, 'code' => 'VE-023', 'name' => 'Zulia'],
        ] as $estado) {
            Estado::query()->create([
                'id' => $estado['id'],
                'code' => $estado['code'],
                'name' => $estado['name'],
                'is_active' => true,
            ]);
        }
    }
}
