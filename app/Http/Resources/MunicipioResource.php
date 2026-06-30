<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Municipio */
class MunicipioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado_id' => $this->estado_id,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'parroquias' => ParroquiaResource::collection($this->whenLoaded('parroquias')),
        ];
    }
}
