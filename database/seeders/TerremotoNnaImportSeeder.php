<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Import\GoogleFormsNnaImportService;
use Illuminate\Database\Seeder;

class TerremotoNnaImportSeeder extends Seeder
{
    public function run(): void
    {
        $path = env('TERREMOTO_NNA_IMPORT_FILE');
        if (! $path || ! is_readable($path)) {
            $this->command?->warn(
                'Omitido: defina TERREMOTO_NNA_IMPORT_FILE en .env apuntando al Excel de Google Forms.',
            );

            return;
        }

        $operativoId = (int) (env('TERREMOTO_NNA_OPERATIVO_ID') ?: 0);
        if ($operativoId === 0) {
            $operativoId = (int) \App\Models\Operativo::query()
                ->where('code', 'TER-2026-VE-001')
                ->value('id');
        }

        $userId = (int) (User::query()->where('email', 'admin@idenna.gob.ve')->value('id') ?: 1);
        $downloadPhotos = filter_var(env('TERREMOTO_NNA_DOWNLOAD_PHOTOS', false), FILTER_VALIDATE_BOOL);

        $this->command?->info("Importando NNA desde: {$path}");

        $batch = app(GoogleFormsNnaImportService::class)->importFromPath(
            $path,
            $operativoId,
            $userId,
            basename($path),
            $downloadPhotos,
        );

        $summary = $batch->summary ?? [];
        $this->command?->info(sprintf(
            'Importación completada: %d ok, %d error, %d omitidos.',
            $summary['imported'] ?? $batch->success_rows,
            $summary['failed'] ?? $batch->failed_rows,
            $summary['skipped'] ?? 0,
        ));
    }
}
