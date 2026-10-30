<?php

namespace App\Support;

use App\Enums\CoverSlot;
use App\Models\Content;
use App\Models\Issue;
use App\Models\Page;

/**
 * Campo «Posizione nel timone» dei moduli di creazione e modifica di un
 * contenuto (revisione 2, Fase 2.5, 2026-10-27): un solo punto per le
 * scelte possibili, la loro lettura e il valore attuale di un contenuto.
 * Valori: '' = da assegnare, 'p:N' = dalla pagina interna N, 'c:I'…'c:IV'
 * (e 'c:BI1'… per i battenti) = dalla copertina. Stringhe semplici perché finiscono
 * in una proprietà Livewire e in una <select>.
 *
 * Non va confuso con Advertisement::$preferred_position (la pagina
 * desiderata dall'inserzionista, solo un'annotazione commerciale).
 */
final class PlacementChoice
{
    /** @return array<string, string> valore => etichetta, nell'ordine della select */
    public static function options(Issue $issue): array
    {
        $options = ['' => 'Da assegnare (nessuna pagina)'];

        foreach ($issue->covers()->get()->sortBy(fn (Page $cover) => $cover->cover_slot->order()) as $cover) {
            $options['c:'.$cover->cover_slot->value] = $cover->cover_slot->label();
        }

        foreach ($issue->pages()->pluck('position') as $position) {
            $options['p:'.$position] = "Pagina {$position}";
        }

        return $options;
    }

    /** La pagina di partenza scelta; null per '' o per un valore non valido. */
    public static function page(Issue $issue, string $value): ?Page
    {
        if (preg_match('/^p:(\d+)$/', $value, $match)) {
            return $issue->pages()->where('position', (int) $match[1])->first();
        }

        if (preg_match('/^c:(.+)$/', $value, $match) && CoverSlot::tryFrom($match[1]) !== null) {
            return $issue->covers()->where('cover_slot', $match[1])->first();
        }

        return null;
    }

    /** Valore attuale di un contenuto: la sua prima pagina in ordine di sfoglio. */
    public static function valueFor(Content $content): string
    {
        $first = ContentUpdater::assignedPagesInReadingOrder($content)->first();

        return match (true) {
            $first === null => '',
            $first->isCover() => 'c:'.$first->cover_slot->value,
            default => 'p:'.$first->position,
        };
    }
}
