<?php

namespace App\Enums;

enum AdConfirmationStatus: string
{
    case InTrattativa = 'in_trattativa';
    case Confermata = 'confermata';
    case Annullata = 'annullata';

    public function label(): string
    {
        return match ($this) {
            self::InTrattativa => 'In trattativa',
            self::Confermata => 'Confermata',
            self::Annullata => 'Annullata',
        };
    }
}
