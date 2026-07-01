<?php

namespace App\Services\Import;

use App\Imports\ArrayImport;
use App\Models\NnaRegistration;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class ImportRegistrarService
{
    public function __construct(
        private readonly GoogleFormsNnaImportService $importService,
    ) {}

    /**
     * @return Collection<int, array{
     *   document_id: string,
     *   document_raw: string,
     *   name: string,
     *   estado_id: ?int,
     *   municipio_id: ?int,
     *   parroquia_id: ?int,
     *   rows: int
     * }>
     */
    public function extractFromPath(string $path): Collection
    {
        $rows = $this->loadRows($path);
        if ($rows->isEmpty()) {
            return collect();
        }

        $this->importService->getLookup()->warmUp();

        /** @var array<string, array<string, mixed>> $byDocument */
        $byDocument = [];

        foreach ($rows->slice(1) as $row) {
            $cells = array_values($row);
            $meta = $this->importService->buildImportRegistrarMeta($cells);
            $cedula = $meta['import_registrar_cedula'] ?? '';
            if ($cedula === '') {
                continue;
            }

            if (! isset($byDocument[$cedula])) {
                $byDocument[$cedula] = [
                    'document_id' => $cedula,
                    'document_raw' => trim((string) ($cells[60] ?? '')),
                    'name' => trim((string) ($meta['import_registrar_nombre'] ?? $cells[59] ?? '')),
                    'estado_id' => $meta['import_registrar_estado_id'] ?? null,
                    'municipio_id' => $meta['import_registrar_municipio_id'] ?? null,
                    'parroquia_id' => $meta['import_registrar_parroquia_id'] ?? null,
                    'rows' => 0,
                ];
            }

            $byDocument[$cedula]['rows']++;
        }

        return collect($byDocument)->sortByDesc('rows')->values();
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function syncUsers(
        Collection $registrars,
        int $operativoId,
        bool $dryRun = false,
        ?string $defaultPassword = null,
        bool $resetPassword = false,
    ): array {
        $password = $defaultPassword ?? config('idenna.import_registrar_default_password', 'Registrador123!');
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $passwordReset = 0;

        foreach ($registrars as $registrar) {
            $cedula = $registrar['document_id'];
            $name = $registrar['name'] !== '' ? $registrar['name'] : "Registrador {$cedula}";

            $existing = User::query()
                ->where('document_id', $cedula)
                ->orWhere('email', $this->emailForDocument($cedula))
                ->first();

            if ($dryRun) {
                $existing ? $updated++ : $created++;
                continue;
            }

            if ($existing) {
                $updates = [
                    'name' => $name,
                    'document_id' => $cedula,
                    'current_operativo_id' => $existing->current_operativo_id ?? $operativoId,
                    'is_active' => true,
                ];
                if ($resetPassword) {
                    $updates['password'] = Hash::make($password);
                    $passwordReset++;
                }
                $existing->update($updates);
                if (! $existing->hasRole('registrador')) {
                    $existing->assignRole('registrador');
                }
                $updated++;
                continue;
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $this->emailForDocument($cedula),
                'document_id' => $cedula,
                'password' => Hash::make($password),
                'organization' => 'IDENNA',
                'is_active' => true,
                'current_operativo_id' => $operativoId,
            ]);
            $user->assignRole('registrador');
            $created++;
        }

        return compact('created', 'updated', 'skipped', 'passwordReset');
    }

    /**
     * @return array{linked: int, unmatched: int, already_linked: int}
     */
    public function linkRegistrations(int $operativoId, bool $dryRun = false): array
    {
        $linked = 0;
        $unmatched = 0;
        $alreadyLinked = 0;

        $usersByDocument = User::query()
            ->whereNotNull('document_id')
            ->pluck('id', 'document_id');

        NnaRegistration::query()
            ->where('operativo_id', $operativoId)
            ->where('metadata->import_source', 'google_forms_terremoto')
            ->orderBy('id')
            ->chunkById(200, function ($registrations) use (
                $usersByDocument,
                $dryRun,
                &$linked,
                &$unmatched,
                &$alreadyLinked,
            ) {
                foreach ($registrations as $registration) {
                    $meta = $registration->metadata ?? [];
                    $cedula = $this->normalizeDocumentId(
                        (string) ($meta['import_registrar_cedula'] ?? $meta['registrador']['cedula'] ?? ''),
                    );

                    if ($cedula === '') {
                        $unmatched++;
                        continue;
                    }

                    $userId = $usersByDocument[$cedula] ?? null;
                    if (! $userId) {
                        $unmatched++;
                        continue;
                    }

                    if ((int) $registration->registered_by === (int) $userId) {
                        $alreadyLinked++;
                        continue;
                    }

                    if (! $dryRun) {
                        $registration->update(['registered_by' => $userId]);
                    }
                    $linked++;
                }
            });

        return [
            'linked' => $linked,
            'unmatched' => $unmatched,
            'already_linked' => $alreadyLinked,
        ];
    }

    public function normalizeDocumentId(?string $value): string
    {
        return $this->importService->normalizeDocumentId($value);
    }

    private function emailForDocument(string $cedula): string
    {
        return "{$cedula}@registradores.idenna.local";
    }

    private function loadRows(string $path): Collection
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv', 'txt' => collect(array_map('str_getcsv', file($path) ?: [])),
            'xlsx', 'xls' => collect(Excel::toArray(new ArrayImport, $path)[0] ?? []),
            default => throw new \InvalidArgumentException('Formato no soportado.'),
        };
    }
}
