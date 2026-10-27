<?php

namespace App\Enums;

/**
 * Quanto è piena una pagina (Fase 3, 2026-10-27), deciso da
 * App\Support\PageCapacity::status() sulla somma dei dodicesimi occupati.
 *
 * Icona + testo per ogni stato, non solo un colore: la richiesta è che
 * vuota/parziale/piena si distinguano anche senza percepire i colori.
 * Oltre capienza non dovrebbe esistere (l'assegnazione lo impedisce), ma
 * può emergere dalla conversione dei dati vecchi arrotondati al dodicesimo:
 * va mostrato come problema, non nascosto dentro "piena".
 */
enum PageFillStatus: string
{
    case Empty = 'empty';
    case Partial = 'partial';
    case Full = 'full';
    case Overfull = 'overfull';

    public function icon(): string
    {
        return match ($this) {
            self::Empty => '○',
            self::Partial => '◐',
            self::Full => '●',
            self::Overfull => '⚠',
        };
    }

    /** Classi del riquadro "Libero: …/Piena" sulla card. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Empty => 'border border-dashed border-gray-400 text-gray-600 bg-white/80 dark:bg-gray-900/70 dark:text-gray-300',
            self::Partial => 'border border-amber-400 text-amber-900 bg-amber-50/90 dark:bg-amber-900/60 dark:text-amber-100',
            self::Full => 'border border-gray-900 text-white bg-gray-900 dark:border-white dark:bg-white dark:text-gray-900 font-semibold',
            self::Overfull => 'border border-red-700 text-white bg-red-600 font-semibold',
        };
    }
}
