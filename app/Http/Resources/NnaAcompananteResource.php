<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\NnaAcompanante */
class NnaAcompananteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'document_id' => $this->document_id,
            'relationship_id' => $this->relationship_id,
            'phone' => $this->phone,
            'is_primary_contact' => $this->is_primary_contact,
        ];
    }
}
