<?php

namespace App\Models;

use App\Enums\OperativoStatus;
use App\Enums\OperativoType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Operativo extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'type',
        'description',
        'status',
        'started_at',
        'ended_at',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => OperativoType::class,
            'status' => OperativoStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Operativo $operativo): void {
            if (empty($operativo->uuid)) {
                $operativo->uuid = (string) Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usersWithCurrent(): HasMany
    {
        return $this->hasMany(User::class, 'current_operativo_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
