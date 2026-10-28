<?php

namespace App\Enums;

/**
 * Le due varianti dell'export sfogliabile (revisione 2, Fase 5): qualità
 * piena (le pagine dei PDF importate così come sono) e bassa risoluzione
 * per anteprima (lo stesso file ricompresso da Ghostscript, da mandare via
 * mail). Ognuna ha il suo file, sostituito alla generazione successiva.
 */
enum MagazineExportVariant: string
{
    case Full = 'full';
    case Preview = 'preview';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Qualità piena',
            self::Preview => 'Bassa risoluzione (anteprima)',
        };
    }

    /** Nome del file scaricato, es. «motori-elettrici-novembre-2026-anteprima.pdf». */
    public function suffix(): string
    {
        return $this === self::Preview ? '-anteprima' : '';
    }
}
