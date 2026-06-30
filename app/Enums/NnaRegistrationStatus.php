<?php

namespace App\Enums;

enum NnaRegistrationStatus: string
{
    case Draft = 'draft';
    case Complete = 'complete';
    case Synced = 'synced';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Complete => 'Completo',
            self::Synced => 'Sincronizado',
        };
    }
}
