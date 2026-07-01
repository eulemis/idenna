<?php

namespace App\Console\Commands;

use App\Models\Operativo;
use App\Services\Import\GoogleFormsNnaImportService;
use Illuminate\Console\Command;

class RehydrateImportMetadataCommand extends Command
{
    protected $signature = 'nna:rehydrate-import-metadata
                            {file : Ruta al Excel/CSV de Google Forms}
                            {--operativo= : ID del operativo (por defecto TER-2026-VE-001)}';

    protected $description = 'Actualiza metadata extendida de registros ya importados desde el Excel de terremoto';

    public function handle(GoogleFormsNnaImportService $importer): int
    {
        $path = $this->argument('file');
        if (! is_readable($path)) {
            $this->error("No se puede leer el archivo: {$path}");

            return self::FAILURE;
        }

        $operativo = $this->resolveOperativo();
        if (! $operativo) {
            $this->error('Operativo no encontrado. Indique --operativo=ID');

            return self::FAILURE;
        }

        $this->info("Rehidratando metadata en operativo: {$operativo->name} (#{$operativo->id})");

        $result = $importer->rehydrateFromPath($path, $operativo->id);

        $this->table(
            ['Actualizados', 'Omitidos (sin match)', 'Fallidos'],
            [[$result['updated'], $result['skipped'], $result['failed']]],
        );

        return $result['failed'] > 0 && $result['updated'] === 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function resolveOperativo(): ?Operativo
    {
        $id = $this->option('operativo');
        if ($id) {
            return Operativo::query()->find($id);
        }

        return Operativo::query()->where('code', 'TER-2026-VE-001')->first();
    }
}
