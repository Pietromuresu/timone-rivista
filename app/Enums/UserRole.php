<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Redattore = 'redattore';
    case Commerciale = 'commerciale';
    case SolaLettura = 'sola_lettura';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Redattore => 'Redattore',
            self::Commerciale => 'Commerciale',
            self::SolaLettura => 'Sola lettura',
        };
    }
}
