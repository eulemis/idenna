<?php

namespace App\Enums;

enum AttentionLocationType: string
{
    case Hospital = 'hospital';
    case Refugio = 'refugio';
    case Campamento = 'campamento';
    case Parque = 'parque';
    case Plaza = 'plaza';
    case Calle = 'calle';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Hospital => 'Hospital',
            self::Refugio => 'Refugio',
            self::Campamento => 'Campamento',
            self::Parque => 'Parque',
            self::Plaza => 'Plaza',
            self::Calle => 'Calle / vía pública',
            self::Otro => 'Otro',
        };
    }
}
