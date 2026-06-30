<?php

namespace App\Models;

use App\Enums\CatalogType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Catalog extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'type',
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => CatalogType::class,
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
