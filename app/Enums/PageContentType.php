<?php

namespace App\Enums;

enum PageContentType: string
{
    case Editoriale = 'editoriale';
    case Pubblicita = 'pubblicita';
    case Mista = 'mista';
    case Bianca = 'bianca';

    public function label(): string
    {
        return match ($this) {
            self::Editoriale => 'Editoriale',
            self::Pubblicita => 'Pubblicità',
            self::Mista => 'Mista',
            self::Bianca => 'Bianca',
        };
    }

    public function colorClasses(): string
    {
        return match ($this) {
            self::Editoriale => 'bg-blue-100 text-blue-800 border-blue-300',
            self::Pubblicita => 'bg-amber-100 text-amber-800 border-amber-300',
            self::Mista => 'bg-purple-100 text-purple-800 border-purple-300',
            self::Bianca => 'bg-gray-100 text-gray-500 border-gray-300',
        };
    }
}
