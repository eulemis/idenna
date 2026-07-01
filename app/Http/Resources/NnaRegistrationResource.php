<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\NnaRegistration */
class NnaRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'local_uuid' => $this->local_uuid,
            'operativo_id' => $this->operativo_id,
            'registration_code' => $this->registration_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim("{$this->first_name} {$this->last_name}"),
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'age_years' => $this->age_years,
            'gender_id' => $this->gender_id,
            'skin_color_id' => $this->skin_color_id,
            'eye_color_id' => $this->eye_color_id,
            'hair_color_id' => $this->hair_color_id,
            'size_id' => $this->size_id,
            'estado_id' => $this->estado_id,
            'municipio_id' => $this->municipio_id,
            'parroquia_id' => $this->parroquia_id,
            'attention_location_id' => $this->attention_location_id,
            'lugar_nna_id' => $this->lugar_nna_id,
            'notes' => $this->notes,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'registered_by' => $this->registered_by,
            'registered_at' => $this->registered_at?->toIso8601String(),
            'device_name' => $this->device_name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'synced_at' => $this->synced_at?->toIso8601String(),
            'server_version' => $this->server_version,
            'metadata' => $this->metadata,
            'acompanantes' => NnaAcompananteResource::collection($this->whenLoaded('acompanantes')),
            'discapacidad_ids' => $this->whenLoaded('discapacidades', fn () => $this->discapacidades->pluck('id')),
            'necesidad_ids' => $this->whenLoaded('necesidades', fn () => $this->necesidades->pluck('id')),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => $photo->disk === 'external'
                    ? $photo->path
                    : Storage::disk($photo->disk)->url($photo->path),
                'is_primary' => $photo->is_primary,
                'external' => $photo->disk === 'external',
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
