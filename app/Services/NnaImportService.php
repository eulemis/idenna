<?php

namespace App\Services;

use App\Imports\ArrayImport;
use App\Models\Catalog;
use App\Models\ImportBatch;
use App\Models\NnaRegistration;
use App\Services\Import\GoogleFormsNnaImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelReaderType;

class NnaImportService
{
    public function __construct(
        private readonly NnaRegistrationService $registrationService,
        private readonly GoogleFormsNnaImportService $googleFormsImporter,
    ) {}

    public function parseFile(UploadedFile $file): array
    {
        $extension = $this->resolveUploadExtension($file);
        $rows = match ($extension) {
            'csv', 'txt' => $this->parseCsv($file->getRealPath()),
            'xlsx', 'xls' => $this->parseExcel($file),
            default => throw new \InvalidArgumentException('Formato no soportado. Use CSV o Excel.'),
        };

        if ($rows->isEmpty()) {
            throw new \InvalidArgumentException('El archivo está vacío.');
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_values($rows->first()));
        $dataRows = $rows->slice(1)->take(5)->map(function ($row) use ($headers) {
            $assoc = [];
            foreach ($headers as $i => $header) {
                $assoc[$header] = $row[$i] ?? null;
            }

            return $assoc;
        })->values();

        return [
            'headers' => $headers,
            'sample_rows' => $dataRows,
            'total_rows' => max(0, $rows->count() - 1),
            'suggested_mapping' => $this->suggestMapping($headers),
            'google_forms_terremoto' => $this->googleFormsImporter->canHandle($headers),
        ];
    }

    public function processImport(
        UploadedFile $file,
        int $operativoId,
        int $userId,
        array $columnMapping,
        bool $downloadPhotos = false,
    ): ImportBatch {
        $extension = $this->resolveUploadExtension($file);
        $rows = match ($extension) {
            'csv', 'txt' => $this->parseCsv($file->getRealPath()),
            'xlsx', 'xls' => $this->parseExcel($file),
            default => throw new \InvalidArgumentException('Formato no soportado.'),
        };

        $headers = array_map(fn ($h) => trim((string) $h), array_values($rows->first()));

        if ($this->googleFormsImporter->canHandle($headers)) {
            return $this->googleFormsImporter->importFromUpload(
                $file,
                $operativoId,
                $userId,
                $downloadPhotos,
            );
        }

        $batch = ImportBatch::query()->create([
            'operativo_id' => $operativoId,
            'user_id' => $userId,
            'filename' => $file->getClientOriginalName(),
            'status' => 'processing',
            'total_rows' => max(0, $rows->count() - 1),
            'column_mapping' => $columnMapping,
        ]);

        $errors = [];
        $success = 0;
        $failed = 0;

        foreach ($rows->slice(1) as $lineNum => $row) {
            $rowNum = $lineNum + 2;
            try {
                $mapped = $this->mapRow($headers, $row, $columnMapping);
                $this->validateMappedRow($mapped);
                $payload = $this->buildPayload($mapped, $operativoId);
                $this->registrationService->create($payload, $userId);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
        }

        $batch->update([
            'status' => $failed > 0 && $success === 0 ? 'failed' : 'completed',
            'success_rows' => $success,
            'failed_rows' => $failed,
            'errors' => array_slice($errors, 0, 100),
            'summary' => [
                'imported' => $success,
                'failed' => $failed,
                'total' => $success + $failed,
            ],
        ]);

        return $batch->fresh();
    }

    private function parseCsv(string $path): Collection
    {
        $rows = collect();
        if (($handle = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $rows->push($data);
            }
            fclose($handle);
        }

        return $rows;
    }

    private function parseExcel(UploadedFile $file): Collection
    {
        $extension = $this->resolveUploadExtension($file);
        $data = Excel::toArray(
            new ArrayImport,
            $file,
            null,
            $this->readerTypeForExtension($extension),
        );
        $sheet = $data[0] ?? [];

        return collect($sheet);
    }

    private function resolveUploadExtension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== '') {
            return $extension;
        }

        return strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
    }

    private function readerTypeForExtension(string $extension): string
    {
        return match ($extension) {
            'xls' => ExcelReaderType::XLS,
            'csv', 'txt' => ExcelReaderType::CSV,
            default => ExcelReaderType::XLSX,
        };
    }

    private function suggestMapping(array $headers): array
    {
        $aliases = [
            'first_name' => ['nombre', 'nombres', 'first_name', 'primer nombre'],
            'last_name' => ['apellido', 'apellidos', 'last_name'],
            'age_years' => ['edad', 'age', 'años'],
            'birth_date' => ['fecha_nacimiento', 'fecha nacimiento', 'birth_date', 'nacimiento'],
            'gender' => ['genero', 'género', 'sexo', 'gender'],
            'notes' => ['observaciones', 'notas', 'notes', 'comentarios'],
            'estado' => ['estado', 'state'],
            'municipio' => ['municipio', 'municipality'],
        ];

        $mapping = [];
        foreach ($aliases as $field => $options) {
            foreach ($headers as $header) {
                if (in_array(strtolower($header), $options, true)) {
                    $mapping[$field] = $header;
                    break;
                }
            }
        }

        return $mapping;
    }

    private function mapRow(array $headers, array $row, array $columnMapping): array
    {
        $assoc = [];
        foreach ($headers as $i => $header) {
            $assoc[$header] = isset($row[$i]) ? trim((string) $row[$i]) : null;
        }

        $mapped = [];
        foreach ($columnMapping as $field => $header) {
            if ($header && isset($assoc[$header])) {
                $mapped[$field] = $assoc[$header];
            }
        }

        return $mapped;
    }

    private function validateMappedRow(array $mapped): void
    {
        if (empty($mapped['first_name']) || empty($mapped['last_name'])) {
            throw new \InvalidArgumentException('Nombre y apellido son obligatorios.');
        }
    }

    private function buildPayload(array $mapped, int $operativoId): array
    {
        $payload = [
            'operativo_id' => $operativoId,
            'local_uuid' => (string) Str::uuid(),
            'first_name' => $mapped['first_name'],
            'last_name' => $mapped['last_name'],
            'status' => 'complete',
            'notes' => $mapped['notes'] ?? null,
        ];

        if (! empty($mapped['age_years'])) {
            $payload['age_years'] = (int) $mapped['age_years'];
        }

        if (! empty($mapped['birth_date'])) {
            $payload['birth_date'] = $mapped['birth_date'];
        }

        if (! empty($mapped['gender'])) {
            $gender = Catalog::query()
                ->where('type', 'genero')
                ->where(function ($q) use ($mapped) {
                    $q->where('name', 'like', '%'.$mapped['gender'].'%')
                        ->orWhere('code', strtoupper($mapped['gender']));
                })
                ->first();
            if ($gender) {
                $payload['gender_id'] = $gender->id;
            }
        }

        return $payload;
    }
}
