<?php

namespace App\Enums;

enum ContentType: string
{
    case Articolo = 'articolo';
    case Pubblicita = 'pubblicita';

    public function label(): string
    {
        return match ($this) {
            self::Articolo => 'Articolo',
            self::Pubblicita => 'Pubblicità',
        };
    }
}
