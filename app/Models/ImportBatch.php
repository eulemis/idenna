<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ImportBatch extends Model
{
    protected $fillable = [
        'uuid',
        'operativo_id',
        'user_id',
        'filename',
        'status',
        'total_rows',
        'success_rows',
        'failed_rows',
        'column_mapping',
        'errors',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'errors' => 'array',
            'summary' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ImportBatch $batch): void {
            if (empty($batch->uuid)) {
                $batch->uuid = (string) Str::uuid();
            }
        });
    }

    public function operativo(): BelongsTo
    {
        return $this->belongsTo(Operativo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
