<?php

namespace App\Support\Occupancy;

use App\Enums\AdFormat;
use App\Support\Twelfths;

/**
 * Piano della conversione dei dati esistenti dalle percentuali ai
 * dodicesimi (Fase 3, 2026-10-27). Puro: riceve righe grezze del database
 * (oggetti stdClass dal query builder), restituisce cosa diventa cosa.
 * Usato identico dalla migration e dal dry-run
 * (`php artisan timone:occupancy-dry-run`): ciò che si legge nel dry-run è
 * ciò che verrà scritto.
 *
 * Regole (scelte di default della Fase 3, vedi HANDOFF.md):
 * - Pivot `page_content`: la percentuale diventa il dodicesimo più vicino
 *   (50 → 6, 33 → 4, 99.7 → 12), mai 0 né oltre 12. La specifica diceva
 *   "i contenuti esistenti occupano 12 su ogni pagina assegnata", ma sui
 *   dati reali due mezze pagine sulla stessa pagina sarebbero diventate
 *   24/12, cioè pagine oltre capienza: l'arrotondamento conserva ciò che
 *   l'utente aveva impostato e la capienza.
 * - Occupazione totale del contenuto (`contents.occupancy_twelfths`):
 *   articolo → la vecchia occupazione dichiarata (0.25/0.33/0.5/0.67 →
 *   3/4/6/8, N pagine → 12·N); pubblicità senza percentuale manuale → il
 *   formato (AdFormat::twelfths()); con percentuale manuale → pagine
 *   attraversate × il dodicesimo più vicino alla percentuale (es. il
 *   "100% su 3 pagine" del bug 8 → 36).
 * - Pagine che dopo l'arrotondamento superano 12: segnalate, non
 *   corrette in silenzio (la redazione decide cosa togliere).
 */
final class OccupancyConversionPlanner
{
    /** Vecchie frazioni di `contents.page_occupancy` → dodicesimi. */
    private const LEGACY_FRACTIONS = [
        '0.25' => 3,
        '0.33' => 4,
        '0.50' => 6,
        '0.67' => 8,
    ];

    public static function pivotTwelfths(float|string $percentage): int
    {
        return Twelfths::fromPercentage((float) $percentage);
    }

    /**
     * @param  object  $content  riga di `contents` con `type`, `page_occupancy`, e (left join) `format`, `occupied_percentage_override`
     * @param  int  $assignedTwelfths  somma dei pivot già convertiti (ripiego solo se l'occupazione manca del tutto)
     */
    public static function contentTwelfths(object $content, int $assignedTwelfths): int
    {
        $legacySpan = self::legacySpan($content->page_occupancy);

        if ($content->type === 'pubblicita') {
            if ($content->occupied_percentage_override !== null) {
                return ($legacySpan ?? 1) * Twelfths::fromPercentage((float) $content->occupied_percentage_override);
            }

            $format = AdFormat::tryFrom((string) $content->format);

            if ($format !== null) {
                return $format->twelfths();
            }
        }

        if ($content->page_occupancy !== null) {
            $key = number_format((float) $content->page_occupancy, 2, '.', '');

            if (isset(self::LEGACY_FRACTIONS[$key])) {
                return self::LEGACY_FRACTIONS[$key];
            }

            if ($legacySpan !== null) {
                return $legacySpan * Twelfths::PER_PAGE;
            }
        }

        // Dato mancante (non dovrebbe esistere dopo la migration NOT NULL
        // del 2026-10-12): ciò che occupa davvero sulle pagine, o 1 pagina.
        return $assignedTwelfths > 0 ? $assignedTwelfths : Twelfths::PER_PAGE;
    }

    /**
     * Piano di un numero.
     *
     * @param  iterable<object>  $contents  righe di contents (+ format/override)
     * @param  iterable<object>  $pivots  righe di page_content (+ page_label, position, cover_slot)
     * @return array{
     *     contents: list<array{id: int, title: string, type: string, old: string, new: int}>,
     *     pivots: list<array{id: int, page_id: int, page_label: string, content_id: int, old: float, new: int}>,
     *     overfull_pages: list<array{page_label: string, total: int}>,
     * }
     */
    public static function plan(iterable $contents, iterable $pivots): array
    {
        $pivotPlan = [];
        $assignedByContent = [];
        $usedByPage = [];

        foreach ($pivots as $pivot) {
            $new = self::pivotTwelfths($pivot->occupied_percentage ?? 100);
            $pivotPlan[] = [
                'id' => (int) $pivot->id,
                'page_id' => (int) $pivot->page_id,
                'page_label' => $pivot->page_label,
                'content_id' => (int) $pivot->content_id,
                'old' => (float) ($pivot->occupied_percentage ?? 100),
                'new' => $new,
            ];
            $assignedByContent[$pivot->content_id] = ($assignedByContent[$pivot->content_id] ?? 0) + $new;
            $usedByPage[$pivot->page_id] ??= ['page_label' => $pivot->page_label, 'total' => 0];
            $usedByPage[$pivot->page_id]['total'] += $new;
        }

        $contentPlan = [];

        foreach ($contents as $content) {
            $contentPlan[] = [
                'id' => (int) $content->id,
                'title' => (string) $content->title,
                'type' => (string) $content->type,
                'old' => self::describeLegacy($content),
                'new' => self::contentTwelfths($content, $assignedByContent[$content->id] ?? 0),
            ];
        }

        $overfull = [];

        foreach ($usedByPage as $page) {
            if ($page['total'] > Twelfths::PER_PAGE) {
                $overfull[] = $page;
            }
        }

        return ['contents' => $contentPlan, 'pivots' => $pivotPlan, 'overfull_pages' => $overfull];
    }

    /**
     * Ritorno ai vecchi valori per le righe nate DOPO la conversione
     * (down()): le righe convertite conservano il valore originale, che la
     * migration non tocca. 2,5 pagine non esiste nel vecchio modello: si
     * torna alle pagine attraversate (3), come contava il vecchio codice.
     */
    public static function legacyOccupancy(int $twelfths): string
    {
        if ($twelfths < Twelfths::PER_PAGE) {
            $closest = collect(self::LEGACY_FRACTIONS)
                ->sortBy(fn (int $value) => abs($value - $twelfths))
                ->keys()
                ->first();

            return (string) $closest;
        }

        return (string) intdiv($twelfths + Twelfths::PER_PAGE - 1, Twelfths::PER_PAGE);
    }

    public static function legacyPercentage(int $twelfths): string
    {
        return number_format($twelfths * 100 / Twelfths::PER_PAGE, 2, '.', '');
    }

    /** Pagine attraversate secondo il vecchio valore (frazione → 1). */
    private static function legacySpan(mixed $pageOccupancy): ?int
    {
        if ($pageOccupancy === null || ! is_numeric($pageOccupancy)) {
            return null;
        }

        $value = (float) $pageOccupancy;

        return $value < 1 ? 1 : (int) ceil($value);
    }

    private static function describeLegacy(object $content): string
    {
        $occupancy = $content->page_occupancy === null ? '—' : rtrim(rtrim((string) $content->page_occupancy, '0'), '.').' pag.';

        if ($content->type !== 'pubblicita') {
            return $occupancy;
        }

        $override = $content->occupied_percentage_override !== null ? ", manuale {$content->occupied_percentage_override}%" : '';

        return "{$occupancy} (formato {$content->format}{$override})";
    }
}
