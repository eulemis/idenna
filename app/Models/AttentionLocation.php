<?php

namespace App\Models;

use App\Enums\AttentionLocationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AttentionLocation extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'operativo_id',
        'type',
        'name',
        'estado_id',
        'municipio_id',
        'parroquia_id',
        'address',
        'latitude',
        'longitude',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => AttentionLocationType::class,
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'metadata' => 'array',
        ];
    }

    public function operativo(): BelongsTo
    {
        return $this->belongsTo(Operativo::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function parroquia(): BelongsTo
    {
        return $this->belongsTo(Parroquia::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
