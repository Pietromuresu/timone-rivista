<?php

namespace App\Enums;

enum IssueStatus: string
{
    case Bozza = 'bozza';
    case InLavorazione = 'in_lavorazione';
    case Chiuso = 'chiuso';

    public function label(): string
    {
        return match ($this) {
            self::Bozza => 'Bozza',
            self::InLavorazione => 'In lavorazione',
            self::Chiuso => 'Chiuso',
        };
    }
}
