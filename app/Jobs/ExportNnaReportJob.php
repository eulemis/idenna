<?php

namespace App\Jobs;

use App\Exports\NnaExport;
use App\Models\NnaRegistration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExportNnaReportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        public readonly string $token,
        public readonly string $format,
        public readonly ?int $operativoId,
        public readonly int $userId,
    ) {}

    public function handle(): void
    {
        @ini_set('memory_limit', '1024M');

        $cacheKey = "export:nna:{$this->token}";
        $filename = 'registros-nna-'.now()->format('Y-m-d');
        $diskPath = "exports/nna/{$this->token}";

        try {
            if ($this->format === 'csv') {
                $relativePath = "{$diskPath}.csv";
                $this->streamCsvToDisk($relativePath);
                $downloadName = "{$filename}.csv";
            } elseif ($this->format === 'pdf') {
                $relativePath = "{$diskPath}.pdf";
                $this->generatePdfToDisk($relativePath);
                $downloadName = "{$filename}.pdf";
            } else {
                $relativePath = "{$diskPath}.xlsx";
                Excel::store(
                    new NnaExport($this->operativoId),
                    $relativePath,
                    'local',
                );
                $downloadName = "{$filename}.xlsx";
            }

            Cache::put($cacheKey, [
                'status' => 'ready',
                'path' => $relativePath,
                'filename' => $downloadName,
                'user_id' => $this->userId,
            ], now()->addHours(2));
        } catch (\Throwable $e) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'user_id' => $this->userId,
            ], now()->addHours(2));

            throw $e;
        }
    }

    private function baseQuery()
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

    private function streamCsvToDisk(string $relativePath): void
    {
        $fullPath = Storage::disk('local')->path($relativePath);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($fullPath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear el archivo CSV.');
        }

        fputcsv($handle, [
            'Código', 'Nombres', 'Apellidos', 'Edad', 'Fecha nacimiento',
            'Estado registro', 'Fecha registro', 'Notas',
        ]);

        $this->baseQuery()->cursor()->each(function ($nna) use ($handle) {
            fputcsv($handle, [
                $nna->registration_code ?? $nna->uuid,
                $nna->first_name,
                $nna->last_name,
                $nna->age_years,
                $nna->birth_date?->format('Y-m-d'),
                $nna->status?->value ?? $nna->status,
                $nna->registered_at?->format('Y-m-d H:i'),
                $nna->notes,
            ]);
        });

        fclose($handle);
    }

    private function generatePdfToDisk(string $relativePath): void
    {
        $fullPath = Storage::disk('local')->path($relativePath);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $total = $this->baseQuery()->count();
        $htmlPath = "{$fullPath}.html";
        $htmlHandle = fopen($htmlPath, 'w');
        if ($htmlHandle === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal PDF.');
        }

        fwrite($htmlHandle, view('reports.nna-pdf-header', [
            'generatedAt' => now()->format('d/m/Y H:i'),
            'total' => $total,
        ])->render());

        $this->baseQuery()->cursor()->each(function ($nna) use ($htmlHandle) {
            fwrite($htmlHandle, view('reports.nna-pdf-row', ['nna' => $nna])->render());
        });

        fwrite($htmlHandle, view('reports.nna-pdf-footer')->render());
        fclose($htmlHandle);

        Pdf::loadHtml(file_get_contents($htmlPath))
            ->setPaper('a4', 'landscape')
            ->save($fullPath);

        @unlink($htmlPath);
    }
}
