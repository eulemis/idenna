<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NnaAcompanante extends Model
{
    protected $fillable = [
        'nna_registration_id',
        'first_name',
        'last_name',
        'document_id',
        'relationship_id',
        'phone',
        'is_primary_contact',
    ];

    protected function casts(): array
    {
        return ['is_primary_contact' => 'boolean'];
    }

    public function nnaRegistration(): BelongsTo
    {
        return $this->belongsTo(NnaRegistration::class);
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Catalog::class, 'relationship_id');
    }
}
