<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estado extends Model
{
    public const COUNTRY_CODE = 'VE';

    protected $fillable = ['code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class);
    }

    /** Estados de Venezuela (códigos importados con prefijo VE-, ej. VE-001). */
    public function scopeVenezuela(Builder $query): Builder
    {
        return $query->where('code', 'like', self::COUNTRY_CODE.'-%');
    }
}
