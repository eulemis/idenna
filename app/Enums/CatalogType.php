<?php

namespace App\Enums;

enum CatalogType: string
{
    case Genero = 'genero';
    case ColorPiel = 'color_piel';
    case ColorOjos = 'color_ojos';
    case ColorCabello = 'color_cabello';
    case TipoDiscapacidad = 'tipo_discapacidad';
    case Necesidad = 'necesidad';
    case Parentesco = 'parentesco';
    case OrganoActuante = 'organo_actuante';
    case TipoMedida = 'tipo_medida';
    case Talla = 'talla';
    case TipoRefugio = 'tipo_refugio';
    case LugarNna = 'lugar_nna';

    public function label(): string
    {
        return match ($this) {
            self::Genero => 'Género',
            self::ColorPiel => 'Color de piel',
            self::ColorOjos => 'Color de ojos',
            self::ColorCabello => 'Color de cabello',
            self::TipoDiscapacidad => 'Tipo de discapacidad',
            self::Necesidad => 'Necesidad',
            self::Parentesco => 'Parentesco',
            self::OrganoActuante => 'Órgano actuante',
            self::TipoMedida => 'Tipo de medida',
            self::Talla => 'Talla',
            self::TipoRefugio => 'Tipo de refugio',
            self::LugarNna => 'Lugar donde se encuentra el NNA',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
