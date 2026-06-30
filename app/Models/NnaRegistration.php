<?php

namespace App\Models;

use App\Enums\NnaRegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class NnaRegistration extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'local_uuid',
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
        'registered_by',
        'registered_at',
        'device_name',
        'latitude',
        'longitude',
        'synced_at',
        'server_version',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'status' => NnaRegistrationStatus::class,
            'registered_at' => 'datetime',
            'synced_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NnaRegistration $nna): void {
            if (empty($nna->uuid)) {
                $nna->uuid = (string) Str::uuid();
            }
            if (empty($nna->local_uuid)) {
                $nna->local_uuid = (string) Str::uuid();
            }
        });
    }

    public function operativo(): BelongsTo
    {
        return $this->belongsTo(Operativo::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Catalog::class, 'gender_id');
    }

    public function acompanantes(): HasMany
    {
        return $this->hasMany(NnaAcompanante::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(NnaPhoto::class);
    }

    public function discapacidades(): BelongsToMany
    {
        return $this->belongsToMany(Catalog::class, 'nna_catalog', 'nna_registration_id', 'catalog_id')
            ->wherePivot('catalog_type', 'tipo_discapacidad')
            ->withTimestamps();
    }

    public function necesidades(): BelongsToMany
    {
        return $this->belongsToMany(Catalog::class, 'nna_catalog', 'nna_registration_id', 'catalog_id')
            ->wherePivot('catalog_type', 'necesidad')
            ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
