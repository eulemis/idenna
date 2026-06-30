<?php

namespace App\Services;

use App\Enums\NnaRegistrationStatus;
use App\Models\NnaAcompanante;
use App\Models\NnaRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NnaRegistrationService
{
    public function create(array $data, int $userId): NnaRegistration
    {
        return DB::transaction(function () use ($data, $userId) {
            $nna = NnaRegistration::query()->create([
                ...$this->extractCoreFields($data),
                'local_uuid' => $data['local_uuid'] ?? (string) Str::uuid(),
                'registered_by' => $userId,
                'registered_at' => now(),
                'synced_at' => now(),
                'status' => $data['status'] ?? NnaRegistrationStatus::Complete,
            ]);

            $this->syncRelations($nna, $data);

            return $nna->fresh(['acompanantes', 'photos', 'discapacidades', 'necesidades']);
        });
    }

    public function update(NnaRegistration $nna, array $data): NnaRegistration
    {
        return DB::transaction(function () use ($nna, $data) {
            $nna->update([
                ...$this->extractCoreFields($data),
                'server_version' => $nna->server_version + 1,
                'synced_at' => now(),
            ]);

            $this->syncRelations($nna, $data);

            return $nna->fresh(['acompanantes', 'photos', 'discapacidades', 'necesidades']);
        });
    }

    public function upsertFromSync(array $payload, int $userId): NnaRegistration
    {
        $localUuid = $payload['local_uuid'] ?? null;
        if (! $localUuid) {
            return $this->create($payload, $userId);
        }

        $existing = NnaRegistration::query()->where('local_uuid', $localUuid)->first();
        if ($existing) {
            if (
                isset($payload['server_version'])
                && (int) $payload['server_version'] < $existing->server_version
            ) {
                return $existing->load(['acompanantes', 'photos', 'discapacidades', 'necesidades']);
            }

            return $this->update($existing, $payload);
        }

        return $this->create($payload, $userId);
    }

    private function extractCoreFields(array $data): array
    {
        return collect($data)->only([
            'operativo_id',
            'registration_code',
            'first_name',
            'last_name',
            'birth_date',
            'age_years',
            'gender_id',
            'skin_color_id',
            'eye_color_id',
            'hair_color_id',
            'size_id',
            'estado_id',
            'municipio_id',
            'parroquia_id',
            'attention_location_id',
            'lugar_nna_id',
            'notes',
            'status',
            'device_name',
            'latitude',
            'longitude',
            'metadata',
        ])->toArray();
    }

    private function syncRelations(NnaRegistration $nna, array $data): void
    {
        if (array_key_exists('acompanantes', $data)) {
            $nna->acompanantes()->delete();
            foreach ($data['acompanantes'] ?? [] as $acompanante) {
                $nna->acompanantes()->create($acompanante);
            }
        }

        if (array_key_exists('discapacidad_ids', $data)) {
            $this->syncCatalogPivot($nna, 'tipo_discapacidad', $data['discapacidad_ids'] ?? []);
        }

        if (array_key_exists('necesidad_ids', $data)) {
            $this->syncCatalogPivot($nna, 'necesidad', $data['necesidad_ids'] ?? []);
        }
    }

    private function syncCatalogPivot(NnaRegistration $nna, string $type, array $ids): void
    {
        DB::table('nna_catalog')
            ->where('nna_registration_id', $nna->id)
            ->where('catalog_type', $type)
            ->delete();

        foreach ($ids as $catalogId) {
            DB::table('nna_catalog')->insert([
                'nna_registration_id' => $nna->id,
                'catalog_id' => $catalogId,
                'catalog_type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
