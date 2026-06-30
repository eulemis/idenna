<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NnaPhoto extends Model
{
    protected $fillable = [
        'nna_registration_id',
        'disk',
        'path',
        'thumbnail_path',
        'mime_type',
        'size_bytes',
        'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function nnaRegistration(): BelongsTo
    {
        return $this->belongsTo(NnaRegistration::class);
    }
}
