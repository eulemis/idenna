<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AttentionLocation */
class AttentionLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operativo_id' => $this->operativo_id,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'name' => $this->name,
            'estado_id' => $this->estado_id,
            'municipio_id' => $this->municipio_id,
            'parroquia_id' => $this->parroquia_id,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
        ];
    }
}
