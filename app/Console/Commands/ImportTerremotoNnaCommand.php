<?php

namespace App\Console\Commands;

use App\Models\Operativo;
use App\Models\User;
use App\Services\Import\GoogleFormsNnaImportService;
use Illuminate\Console\Command;

class ImportTerremotoNnaCommand extends Command
{
    protected $signature = 'nna:import-terremoto
                            {file : Ruta al archivo Excel/CSV exportado de Google Forms}
                            {--operativo= : ID del operativo (por defecto TER-2026-VE-001)}
                            {--user= : ID del usuario registrador (por defecto admin)}
                            {--download-photos : Intenta descargar fotos de Google Drive al storage local}
                            {--allow-duplicates : No omitir filas ya importadas}';

    protected $description = 'Importa registros NNA desde el Excel de Google Forms (terremoto)';

    public function handle(GoogleFormsNnaImportService $importer): int
    {
        $path = $this->argument('file');
        if (! is_readable($path)) {
            $this->error("No se puede leer el archivo: {$path}");

            return self::FAILURE;
        }

        $operativo = $this->resolveOperativo();
        if (! $operativo) {
            $this->error('Operativo no encontrado. Ejecute OperativoSeeder o indique --operativo=ID');

            return self::FAILURE;
        }

        $user = $this->resolveUser();
        if (! $user) {
            $this->error('Usuario no encontrado. Indique --user=ID');

            return self::FAILURE;
        }

        $this->info("Importando hacia operativo: {$operativo->name} (#{$operativo->id})");
        $this->info("Registrador: {$user->name} (#{$user->id})");

        $batch = $importer->importFromPath(
            $path,
            $operativo->id,
            $user->id,
            basename($path),
            (bool) $this->option('download-photos'),
            ! $this->option('allow-duplicates'),
        );

        $summary = $batch->summary ?? [];
        $this->table(
            ['Importados', 'Fallidos', 'Omitidos', 'Total filas'],
            [[
                $summary['imported'] ?? $batch->success_rows,
                $summary['failed'] ?? $batch->failed_rows,
                $summary['skipped'] ?? 0,
                $summary['total'] ?? $batch->total_rows,
            ]],
        );

        if (! empty($batch->errors)) {
            $this->warn('Primeros errores:');
            foreach (array_slice($batch->errors, 0, 10) as $error) {
                $this->line("  Fila {$error['row']}: {$error['message']}");
            }
        }

        $this->info("Lote de importación #{$batch->id} — estado: {$batch->status}");

        return ($batch->failed_rows ?? 0) > 0 && ($batch->success_rows ?? 0) === 0
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

    private function resolveUser(): ?User
    {
        $id = $this->option('user');
        if ($id) {
            return User::query()->find($id);
        }

        return User::query()->where('email', 'admin@idenna.gob.ve')->first();
    }
}
