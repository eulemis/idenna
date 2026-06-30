<?php

namespace App\Enums;

enum OperativoStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'Activo',
            self::Closed => 'Cerrado',
            self::Archived => 'Archivado',
        };
    }
}
