<?php

namespace App\Services\Import;

use App\Imports\ArrayImport;
use App\Models\ImportBatch;
use App\Models\NnaRegistration;
use App\Services\NnaRegistrationService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelReaderType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class GoogleFormsNnaImportService
{
    public const SIGNATURE_HEADER = 'El Niño, Niña o Adolescente se encuentra en:';

    public function __construct(
        private readonly NnaRegistrationService $registrationService,
        private readonly ImportLookupService $lookup,
        private readonly NnaExternalPhotoService $photoService,
    ) {}

    public function canHandle(array $headers): bool
    {
        return in_array(self::SIGNATURE_HEADER, array_map(
            fn ($h) => trim((string) $h),
            $headers,
        ), true);
    }

    public function getLookup(): ImportLookupService
    {
        return $this->lookup;
    }

    public function importFromPath(
        string $path,
        int $operativoId,
        int $userId,
        ?string $originalFilename = null,
        bool $downloadPhotos = false,
        bool $skipDuplicates = true,
    ): ImportBatch {
        $extension = $this->resolveFileExtension($path, $originalFilename);
        $rows = match ($extension) {
            'csv', 'txt' => $this->parseCsv($path),
            'xlsx', 'xls' => collect($this->readExcelRows($path, $extension)[0] ?? []),
            default => throw new \InvalidArgumentException('Formato no soportado. Use CSV o Excel.'),
        };

        return $this->importRows(
            $rows,
            $operativoId,
            $userId,
            $originalFilename ?? basename($path),
            $downloadPhotos,
            $skipDuplicates,
        );
    }

    public function importFromUpload(
        UploadedFile $file,
        int $operativoId,
        int $userId,
        bool $downloadPhotos = false,
        bool $skipDuplicates = true,
    ): ImportBatch {
        $extension = $this->resolveFileExtension(
            $file->getRealPath(),
            $file->getClientOriginalName(),
        );
        $rows = match ($extension) {
            'csv', 'txt' => $this->parseCsv($file->getRealPath()),
            'xlsx', 'xls' => collect($this->readExcelRows($file, $extension)[0] ?? []),
            default => throw new \InvalidArgumentException('Formato no soportado. Use CSV o Excel.'),
        };

        return $this->importRows(
            $rows,
            $operativoId,
            $userId,
            $file->getClientOriginalName(),
            $downloadPhotos,
            $skipDuplicates,
        );
    }

    private function importRows(
        \Illuminate\Support\Collection $rows,
        int $operativoId,
        int $userId,
        string $filename,
        bool $downloadPhotos,
        bool $skipDuplicates,
    ): ImportBatch {
        if ($rows->isEmpty()) {
            throw new \InvalidArgumentException('El archivo está vacío.');
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_values($rows->first()));
        if (! $this->canHandle($headers)) {
            throw new \InvalidArgumentException('El archivo no corresponde al formato Google Forms de terremoto.');
        }

        $this->lookup->warmUp();

        $batch = ImportBatch::query()->create([
            'operativo_id' => $operativoId,
            'user_id' => $userId,
            'filename' => $filename,
            'status' => 'processing',
            'total_rows' => max(0, $rows->count() - 1),
            'column_mapping' => ['mode' => 'google_forms_terremoto_auto'],
        ]);

        $errors = [];
        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($rows->slice(1) as $lineNum => $row) {
            $rowNum = $lineNum + 2;
            try {
                $payload = $this->buildPayloadFromRowArray(array_values($row), $operativoId, $rowNum);

                if ($skipDuplicates && $this->isDuplicate($operativoId, $payload)) {
                    $skipped++;
                    continue;
                }

                $photoUrl = $payload['_photo_url'] ?? null;
                unset($payload['_photo_url']);

                $nna = $this->registrationService->create($payload, $userId);

                if ($photoUrl) {
                    $this->photoService->attach($nna, $photoUrl, $downloadPhotos);
                }

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
            'errors' => array_slice($errors, 0, 200),
            'summary' => [
                'imported' => $success,
                'failed' => $failed,
                'skipped' => $skipped,
                'total' => $success + $failed + $skipped,
                'mode' => 'google_forms_terremoto',
            ],
        ]);

        return $batch->fresh();
    }

    /**
     * @param  list<mixed>  $cells
     * @return array<string, mixed>
     */
    public function buildPayloadFromRowArray(array $cells, int $operativoId, int $rowNum): array
    {
        $locationType = trim((string) $this->col($cells, 1));
        $isHospital = str_contains(Str::lower($locationType), 'hospital');

        if ($isHospital) {
            return $this->buildHospitalPayloadFromCells($cells, $operativoId, $rowNum, $locationType);
        }

        return $this->buildRefugioPayloadFromCells($cells, $operativoId, $rowNum, $locationType);
    }

    /**
     * @param  list<mixed>  $cells
     * @return array<string, mixed>
     */
    private function buildHospitalPayloadFromCells(array $cells, int $operativoId, int $rowNum, string $locationType): array
    {
        $firstName = trim((string) $this->col($cells, 24));
        $lastName = trim((string) $this->col($cells, 25));

        if ($firstName === '' && $lastName === '') {
            throw new \InvalidArgumentException('Fila hospital sin nombres del NNA.');
        }

        $estadoId = $this->lookup->findEstado($this->col($cells, 30));
        $municipioId = $this->lookup->findMunicipio($estadoId, $this->col($cells, 31));
        $parroquiaId = $this->lookup->findParroquia($municipioId, $this->col($cells, 32));

        $notes = $this->composeNotes([
            'Punto de referencia' => $this->col($cells, 33),
            'Peso' => $this->col($cells, 35),
            'Estatura' => $this->col($cells, 36),
            'Vestimenta' => $this->col($cells, 40),
            'Otros datos' => $this->col($cells, 48),
            'Discapacidad (texto)' => $this->discapacidadText($this->col($cells, 34)),
            'Órgano actuante' => $this->col($cells, 64),
            'Medida tomada' => $this->col($cells, 66),
        ]);

        $discapacidadIds = $this->lookup->mapDiscapacidades($this->col($cells, 34));
        $discText = trim((string) ($this->col($cells, 34) ?? ''));
        if ($discText && empty($discapacidadIds) && ! $this->isEmptyDiscapacidad($discText)) {
            $notes = trim(($notes ? $notes."\n" : '').'Discapacidad reportada: '.$discText);
        }

        $importRegistrar = $this->buildImportRegistrarMeta($cells);
        $organoText = $this->col($cells, 64);
        $medidaText = $this->col($cells, 66);
        $acompananteGeo = $this->buildHospitalAcompananteGeo($cells, $estadoId);

        return [
            'operativo_id' => $operativoId,
            'local_uuid' => (string) Str::uuid(),
            'first_name' => $firstName,
            'last_name' => $lastName !== '' ? $lastName : '.',
            'birth_date' => $this->parseDate($this->col($cells, 26)),
            'age_years' => $this->parseAge($this->col($cells, 28)),
            'gender_id' => $this->lookup->findGenero($this->col($cells, 29)),
            'skin_color_id' => $this->lookup->findColorPiel($this->col($cells, 37)),
            'hair_color_id' => $this->lookup->findColorCabello($this->col($cells, 38)),
            'eye_color_id' => $this->lookup->findColorOjos($this->col($cells, 39)),
            'estado_id' => $estadoId,
            'municipio_id' => $municipioId,
            'parroquia_id' => $parroquiaId,
            'lugar_nna_id' => $this->lookup->findLugarNna($locationType),
            'notes' => $notes,
            'status' => 'complete',
            'registered_at' => $this->parseTimestamp($this->col($cells, 0)),
            'discapacidad_ids' => $discapacidadIds,
            'necesidad_ids' => [],
            'acompanantes' => $this->buildHospitalAcompanantesFromCells($cells),
            '_photo_url' => $this->col($cells, 2),
            'metadata' => array_filter([
                'import_source' => 'google_forms_terremoto',
                'form_section' => 'hospital',
                'import_row' => $rowNum,
                'form_location_type' => $locationType,
                'google_timestamp' => $this->col($cells, 0),
                'cedula_nna' => $this->cleanDocument($this->col($cells, 27)),
                'hospital_name' => $this->col($cells, 23),
                'hospital_reference' => $this->col($cells, 33),
                'punto_referencia' => $this->col($cells, 33),
                'peso' => $this->col($cells, 35),
                'estatura' => $this->col($cells, 36),
                'vestimenta' => $this->col($cells, 40),
                'tallas' => $this->buildTallasFromCells($cells),
                'otros_datos' => $this->col($cells, 48),
                'discapacidad_otro' => $this->discapacidadText($discText) ?: null,
                'organo_actuante_id' => $this->lookup->findOrganoActuante($organoText),
                'organo_otro' => $organoText,
                'organo_municipio' => $this->col($cells, 65),
                'tipo_medida_id' => $this->lookup->findTipoMedida($medidaText),
                'tipo_medida_otro' => $medidaText,
                ...$importRegistrar,
                'acompanante_direccion_origen' => $this->col($cells, 53),
                'acompanante_direccion' => $this->col($cells, 56),
                'acompanante_email' => $this->col($cells, 58),
                'acompanante_estado_id' => $acompananteGeo['estado_id'],
                'acompanante_municipio_id' => $acompananteGeo['municipio_id'],
                'acompanante_parroquia_id' => $acompananteGeo['parroquia_id'],
            ], fn ($v) => $v !== null && $v !== [] && $v !== ''),
        ];
    }

    /**
     * @param  list<mixed>  $cells
     * @return array<string, mixed>
     */
    private function buildRefugioPayloadFromCells(array $cells, int $operativoId, int $rowNum, string $locationType): array
    {
        [$firstName, $lastName] = $this->splitFullName((string) $this->col($cells, 5));

        if ($firstName === '') {
            throw new \InvalidArgumentException('Fila refugio sin nombre del NNA.');
        }

        $estadoId = $this->lookup->findEstado($this->col($cells, 10));
        $municipioId = $this->lookup->findMunicipio($estadoId, $this->col($cells, 11));
        $parroquiaId = $this->lookup->findParroquia($municipioId, $this->col($cells, 12));

        $necesidadIds = $this->lookup->mapNecesidades($this->col($cells, 21));
        $needsText = trim((string) ($this->col($cells, 21) ?? ''));
        $importRegistrar = $this->buildImportRegistrarMeta($cells);
        $acompananteGeo = $this->buildRefugioAcompananteGeo($cells, $estadoId);

        $notes = $this->composeNotes([
            'Observaciones' => $this->col($cells, 22),
            'Necesidades (texto)' => $needsText && empty($necesidadIds) ? $needsText : null,
        ]);

        return [
            'operativo_id' => $operativoId,
            'local_uuid' => (string) Str::uuid(),
            'first_name' => $firstName,
            'last_name' => $lastName !== '' ? $lastName : '.',
            'birth_date' => $this->parseDate($this->col($cells, 6)),
            'age_years' => $this->parseAge($this->col($cells, 8)),
            'gender_id' => $this->lookup->findGenero($this->col($cells, 9)),
            'estado_id' => $estadoId,
            'municipio_id' => $municipioId,
            'parroquia_id' => $parroquiaId,
            'lugar_nna_id' => $this->lookup->findLugarNna($locationType, $this->col($cells, 3)),
            'notes' => $notes,
            'status' => 'complete',
            'registered_at' => $this->parseTimestamp($this->col($cells, 0)),
            'discapacidad_ids' => [],
            'necesidad_ids' => $necesidadIds,
            'acompanantes' => $this->buildRefugioAcompanantesFromCells($cells),
            '_photo_url' => $this->col($cells, 2),
            'metadata' => array_filter([
                'import_source' => 'google_forms_terremoto',
                'form_section' => 'refugio',
                'import_row' => $rowNum,
                'form_location_type' => $locationType,
                'google_timestamp' => $this->col($cells, 0),
                'cedula_nna' => $this->cleanDocument($this->col($cells, 7)),
                'refuge_type' => $this->col($cells, 3),
                'refuge_name' => $this->col($cells, 4),
                'punto_referencia' => $this->col($cells, 4),
                'necesidades_texto' => $needsText ?: null,
                ...$importRegistrar,
                'acompanante_direccion' => $this->col($cells, 18),
                'acompanante_estado_id' => $acompananteGeo['estado_id'],
                'acompanante_municipio_id' => $acompananteGeo['municipio_id'],
                'acompanante_parroquia_id' => $acompananteGeo['parroquia_id'],
                'organo_otro' => $this->col($cells, 64),
            ], fn ($v) => $v !== null && $v !== [] && $v !== ''),
        ];
    }

    /**
     * @param  list<mixed>  $cells
     * @return list<array<string, mixed>>
     */
    private function buildRefugioAcompanantesFromCells(array $cells): array
    {
        if (! $this->isYes($this->col($cells, 13))) {
            return [];
        }

        $fullName = trim((string) $this->col($cells, 14));
        if ($fullName === '') {
            return [];
        }

        [$firstName, $lastName] = $this->splitFullName($fullName);

        return [[
            'first_name' => $firstName,
            'last_name' => $lastName ?: null,
            'document_id' => $this->cleanDocument($this->col($cells, 15)),
            'relationship_id' => $this->lookup->findParentesco($this->col($cells, 17)),
            'phone' => $this->cleanPhone($this->col($cells, 16)),
            'is_primary_contact' => true,
        ]];
    }

    /**
     * @param  list<mixed>  $cells
     * @return list<array<string, mixed>>
     */
    private function buildHospitalAcompanantesFromCells(array $cells): array
    {
        if (! $this->isYes($this->col($cells, 49))) {
            return [];
        }

        $fullName = trim((string) $this->col($cells, 51));
        if ($fullName === '') {
            return [];
        }

        [$firstName, $lastName] = $this->splitFullName($fullName);

        return [[
            'first_name' => $firstName,
            'last_name' => $lastName ?: null,
            'document_id' => $this->cleanDocument($this->col($cells, 52)),
            'relationship_id' => $this->lookup->findParentesco($this->col($cells, 50)),
            'phone' => $this->cleanPhone($this->col($cells, 57)),
            'is_primary_contact' => true,
        ]];
    }

    private function col(array $cells, int $index): mixed
    {
        if (! array_key_exists($index, $cells)) {
            return null;
        }

        $value = trim((string) $cells[$index]);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, string|null>
     */
    private function buildTallasFromCells(array $cells): array
    {
        return array_filter([
            'camisa' => $this->col($cells, 41),
            'pantalon' => $this->col($cells, 42),
            'panal' => $this->col($cells, 43),
            'calzado' => $this->col($cells, 44),
            'sosten' => $this->col($cells, 45),
            'ropa_interior_f' => $this->col($cells, 46),
            'ropa_interior_m' => $this->col($cells, 47),
        ]);
    }

    /**
     * Cédula y nombre del encuestador Google Forms (para crear usuarios y vincular registros).
     *
     * @return array<string, mixed>
     */
    public function buildImportRegistrarMeta(array $cells): array
    {
        $cedula = $this->normalizeDocumentId($this->col($cells, 60));
        $estadoId = $this->lookup->findEstado($this->col($cells, 61));
        $municipioId = $this->lookup->findMunicipio($estadoId, $this->col($cells, 62));
        $parroquiaId = $this->lookup->findParroquia($municipioId, $this->col($cells, 63));

        return array_filter([
            'import_registrar_nombre' => $this->col($cells, 59),
            'import_registrar_cedula' => $cedula !== '' ? $cedula : null,
            'import_registrar_estado_id' => $estadoId,
            'import_registrar_municipio_id' => $municipioId,
            'import_registrar_parroquia_id' => $parroquiaId,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function normalizeDocumentId(?string $value): string
    {
        if (! $value) {
            return '';
        }

        $value = trim($value);
        if (in_array(Str::lower($value), ['no posee', 'no tiene', 'sin cedula', 's/c', 'na', 'n/a'], true)) {
            return '';
        }

        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * @return array{estado_id: ?int, municipio_id: ?int, parroquia_id: ?int}
     */
    private function buildHospitalAcompananteGeo(array $cells, ?int $fallbackEstadoId): array
    {
        $municipioId = $this->lookup->findMunicipio($fallbackEstadoId, $this->col($cells, 54));
        $parroquiaId = $this->lookup->findParroquia($municipioId, $this->col($cells, 55));

        return [
            'estado_id' => $fallbackEstadoId,
            'municipio_id' => $municipioId,
            'parroquia_id' => $parroquiaId,
        ];
    }

    /**
     * @return array{estado_id: ?int, municipio_id: ?int, parroquia_id: ?int}
     */
    private function buildRefugioAcompananteGeo(array $cells, ?int $fallbackEstadoId): array
    {
        $municipioId = $this->lookup->findMunicipio($fallbackEstadoId, $this->col($cells, 19));
        $parroquiaId = $this->lookup->findParroquia($municipioId, $this->col($cells, 20));

        return [
            'estado_id' => $fallbackEstadoId,
            'municipio_id' => $municipioId,
            'parroquia_id' => $parroquiaId,
        ];
    }

    /**
     * Actualiza registros ya importados con metadata extendida desde el Excel.
     *
     * @return array{updated: int, skipped: int, failed: int}
     */
    public function rehydrateFromPath(string $path, int $operativoId): array
    {
        $extension = $this->resolveFileExtension($path);
        $rows = match ($extension) {
            'csv', 'txt' => $this->parseCsv($path),
            'xlsx', 'xls' => collect($this->readExcelRows($path, $extension)[0] ?? []),
            default => throw new \InvalidArgumentException('Formato no soportado.'),
        };

        $this->lookup->warmUp();

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($rows->slice(1) as $lineNum => $row) {
            $rowNum = $lineNum + 2;
            try {
                $payload = $this->buildPayloadFromRowArray(array_values($row), $operativoId, $rowNum);
                $meta = $payload['metadata'] ?? [];
                $existing = NnaRegistration::query()
                    ->where('operativo_id', $operativoId)
                    ->where('metadata->google_timestamp', $meta['google_timestamp'] ?? null)
                    ->where('first_name', $payload['first_name'])
                    ->where('last_name', $payload['last_name'])
                    ->first();

                if (! $existing) {
                    $skipped++;
                    continue;
                }

                unset($payload['_photo_url'], $payload['local_uuid']);
                $meta = $this->normalizeImportMetadata($meta);
                $existing->update([
                    'skin_color_id' => $payload['skin_color_id'] ?? $existing->skin_color_id,
                    'hair_color_id' => $payload['hair_color_id'] ?? $existing->hair_color_id,
                    'eye_color_id' => $payload['eye_color_id'] ?? $existing->eye_color_id,
                    'metadata' => array_merge(
                        $this->normalizeImportMetadata($existing->metadata ?? []),
                        $meta,
                    ),
                    'notes' => $payload['notes'] ?? $existing->notes,
                ]);

                if (array_key_exists('discapacidad_ids', $payload)) {
                    $this->registrationService->update($existing, [
                        'discapacidad_ids' => $payload['discapacidad_ids'],
                        'necesidad_ids' => $payload['necesidad_ids'] ?? [],
                        'acompanantes' => $payload['acompanantes'] ?? [],
                    ]);
                }

                $updated++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return compact('updated', 'skipped', 'failed');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function normalizeImportMetadata(array $meta): array
    {
        if (isset($meta['registrador']) && is_array($meta['registrador'])) {
            $legacy = $meta['registrador'];
            $meta['import_registrar_nombre'] ??= $legacy['nombre'] ?? null;
            $cedula = $this->normalizeDocumentId($legacy['cedula'] ?? null);
            if ($cedula !== '') {
                $meta['import_registrar_cedula'] ??= $cedula;
            }
            $meta['import_registrar_estado_id'] ??= $legacy['estado_id'] ?? null;
            $meta['import_registrar_municipio_id'] ??= $legacy['municipio_id'] ?? null;
            $meta['import_registrar_parroquia_id'] ??= $legacy['parroquia_id'] ?? null;
            unset($meta['registrador']);
        }

        if (isset($meta['import_registrar_cedula'])) {
            $meta['import_registrar_cedula'] = $this->normalizeDocumentId((string) $meta['import_registrar_cedula']);
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isDuplicate(int $operativoId, array $payload): bool
    {
        $meta = $payload['metadata'] ?? [];
        $query = NnaRegistration::query()->where('operativo_id', $operativoId);

        if (! empty($meta['google_timestamp'])) {
            return $query->clone()
                ->where('metadata->google_timestamp', $meta['google_timestamp'])
                ->where('first_name', $payload['first_name'])
                ->where('last_name', $payload['last_name'])
                ->exists();
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitFullName(string $full): array
    {
        $full = trim(preg_replace('/\s+/', ' ', $full) ?? '');
        if ($full === '') {
            return ['', ''];
        }

        $parts = explode(' ', $full);
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $lastName = array_pop($parts);

        return [implode(' ', $parts), $lastName];
    }

    private function parseAge(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $age = (int) round((float) $value);

            return ($age >= 0 && $age <= 25) ? $age : null;
        }

        if (preg_match('/(\d+)/', (string) $value, $matches)) {
            $age = (int) $matches[1];

            return ($age >= 0 && $age <= 25) ? $age : null;
        }

        return null;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed|null>  $parts
     */
    private function composeNotes(array $parts): ?string
    {
        $lines = [];
        foreach ($parts as $label => $value) {
            $value = trim((string) ($value ?? ''));
            if ($value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }

        return $lines ? implode("\n", $lines) : null;
    }

    private function isYes(?string $value): bool
    {
        if (! $value) {
            return false;
        }

        $normalized = Str::ascii(Str::lower(trim($value)));

        return in_array($normalized, ['si', 'sí', 'yes', 'true', '1'], true);
    }

    private function isEmptyDiscapacidad(string $value): bool
    {
        $normalized = Str::ascii(Str::lower(trim($value)));

        return in_array($normalized, ['ninguna', 'no registra', 'no', 'na', 'n/a'], true);
    }

    private function discapacidadText(?string $value): ?string
    {
        if (! $value || $this->isEmptyDiscapacidad($value)) {
            return null;
        }

        return $value;
    }

    private function cleanDocument(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);
        if (in_array(Str::lower($value), ['no posee', 'no tiene', 'sin cedula', 's/c'], true)) {
            return null;
        }

        return $value;
    }

    private function cleanPhone(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);
        if (in_array(Str::ascii(Str::lower($value)), ['sin informacion', 'sin información', 'n/a', 'na', 'no'], true)) {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $value) ?: '';
        if ($digits === '' || strlen($digits) < 7 || strlen($digits) > 15) {
            return null;
        }

        return substr($digits, 0, 20);
    }

    private function resolveFileExtension(string $path, ?string $originalFilename = null): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension !== '') {
            return $extension;
        }

        if ($originalFilename) {
            return strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        }

        return '';
    }

    /**
     * @param  string|UploadedFile  $source
     */
    private function readExcelRows(string|UploadedFile $source, string $extension): array
    {
        return Excel::toArray(
            new ArrayImport,
            $source,
            null,
            $this->readerTypeForExtension($extension),
        );
    }

    private function readerTypeForExtension(string $extension): string
    {
        return match ($extension) {
            'xls' => ExcelReaderType::XLS,
            'csv', 'txt' => ExcelReaderType::CSV,
            default => ExcelReaderType::XLSX,
        };
    }

    private function parseCsv(string $path): \Illuminate\Support\Collection
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
}
