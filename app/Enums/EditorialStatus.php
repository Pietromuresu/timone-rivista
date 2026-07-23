<?php

namespace App\Enums;

enum EditorialStatus: string
{
    case DaScrivere = 'da_scrivere';
    case InScrittura = 'in_scrittura';
    case InRevisione = 'in_revisione';
    case Pronto = 'pronto';

    public function label(): string
    {
        return match ($this) {
            self::DaScrivere => 'Da scrivere',
            self::InScrittura => 'In scrittura',
            self::InRevisione => 'In revisione',
            self::Pronto => 'Pronto',
        };
    }
}
