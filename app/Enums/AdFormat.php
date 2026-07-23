<?php

namespace App\Enums;

enum AdFormat: string
{
    case PaginaIntera = 'pagina_intera';
    case MezzaPaginaOrizzontale = 'mezza_pagina_orizzontale';
    case MezzaPaginaVerticale = 'mezza_pagina_verticale';
    case UnTerzoPaginaOrizzontale = 'un_terzo_pagina_orizzontale';
    case UnTerzoPaginaVerticale = 'un_terzo_pagina_verticale';
    case UnQuartoPagina = 'un_quarto_pagina';

    public function label(): string
    {
        return match ($this) {
            self::PaginaIntera => 'Pagina intera',
            self::MezzaPaginaOrizzontale => 'Mezza pagina orizzontale',
            self::MezzaPaginaVerticale => 'Mezza pagina verticale',
            self::UnTerzoPaginaOrizzontale => 'Un terzo pagina orizzontale',
            self::UnTerzoPaginaVerticale => 'Un terzo pagina verticale',
            self::UnQuartoPagina => 'Un quarto pagina',
        };
    }

    /**
     * Percentuale di pagina occupata di default per questo formato.
     */
    public function defaultPercentage(): float
    {
        return match ($this) {
            self::PaginaIntera => 100.0,
            self::MezzaPaginaOrizzontale, self::MezzaPaginaVerticale => 50.0,
            self::UnTerzoPaginaOrizzontale, self::UnTerzoPaginaVerticale => 33.3,
            self::UnQuartoPagina => 25.0,
        };
    }
}
