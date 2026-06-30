<?php

namespace App\Enums;

enum OperativoType: string
{
    case Terremoto = 'terremoto';
    case Inundacion = 'inundacion';
    case Deslave = 'deslave';
    case Incendio = 'incendio';
    case Migracion = 'migracion';
    case Epidemia = 'epidemia';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Terremoto => 'Terremoto',
            self::Inundacion => 'Inundación',
            self::Deslave => 'Deslave',
            self::Incendio => 'Incendio forestal',
            self::Migracion => 'Migración',
            self::Epidemia => 'Epidemia',
            self::Otro => 'Otro',
        };
    }
}
