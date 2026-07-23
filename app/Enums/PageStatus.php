<?php

namespace App\Enums;

enum PageStatus: string
{
    case DaAssegnare = 'da_assegnare';
    case Assegnata = 'assegnata';
    case InBozza = 'in_bozza';
    case Revisionata = 'revisionata';
    case OkStampa = 'ok_stampa';

    public function label(): string
    {
        return match ($this) {
            self::DaAssegnare => 'Da assegnare',
            self::Assegnata => 'Assegnata',
            self::InBozza => 'In bozza',
            self::Revisionata => 'Revisionata',
            self::OkStampa => 'Ok stampa',
        };
    }

    public function colorClasses(): string
    {
        return match ($this) {
            self::DaAssegnare => 'bg-gray-100 text-gray-600',
            self::Assegnata => 'bg-sky-100 text-sky-700',
            self::InBozza => 'bg-yellow-100 text-yellow-700',
            self::Revisionata => 'bg-orange-100 text-orange-700',
            self::OkStampa => 'bg-green-100 text-green-700',
        };
    }
}
