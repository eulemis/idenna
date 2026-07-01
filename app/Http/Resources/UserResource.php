<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'document_id' => $this->document_id,
            'phone' => $this->phone,
            'organization' => $this->organization,
            'is_active' => $this->is_active,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'current_operativo' => OperativoResource::make($this->whenLoaded('currentOperativo')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
