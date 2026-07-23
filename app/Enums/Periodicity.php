<?php

namespace App\Enums;

enum Periodicity: string
{
    case Mensile = 'mensile';
    case Settimanale = 'settimanale';
    case Altro = 'altro';

    public function label(): string
    {
        return match ($this) {
            self::Mensile => 'Mensile',
            self::Settimanale => 'Settimanale',
            self::Altro => 'Altro',
        };
    }
}
