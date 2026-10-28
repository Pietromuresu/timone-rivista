<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Collection;

/**
 * Cosa mostrare nella riga «azioni sulla selezione» della fascia sticky
 * (2026-10-28, richiesta "pannello azioni nella fascia sticky"): l'etichetta
 * delle pagine selezionate («3 pagine selezionate: 12–14») e, per ogni
 * azione, se è disponibile o il motivo per cui non lo è. Le azioni non
 * disponibili si disattivano invece di sparire, così i bottoni non cambiano
 * posto da una selezione all'altra.
 *
 * Solo presentazione, pura: i controlli veri restano nei metodi di Grid
 * (linkSelectedPages(), unlinkSelectedFromGroup(), bulkToggleLock(),
 * bulkChangeStatus()), invariati. Qui si anticipano gli stessi casi per
 * spiegarli nel tooltip; se divergessero, decide comunque il server.
 */
final class PageSelectionActions
{
    /**
     * @param  Collection<int, Page>  $selected  pagine selezionate (interne)
     * @return array{count: int, label: string, positions: string, actions: array<string, string|null>} actions: azione => null se disponibile, altrimenti il motivo
     */
    public static function describe(Collection $selected): array
    {
        $count = $selected->count();
        $none = 'Seleziona almeno una pagina';
        $locked = $selected->filter(fn (Page $page) => $page->isLocked());
        $grouped = $selected->filter(fn (Page $page) => $page->page_group_id !== null);
        $groupIds = $selected->pluck('page_group_id')->unique();

        return [
            'count' => $count,
            'label' => $count === 1 ? '1 pagina selezionata' : "{$count} pagine selezionate",
            'positions' => self::ranges($selected->pluck('position')->all()),
            'actions' => [
                'status' => $count === 0 ? $none : null,
                'lock' => match (true) {
                    $count === 0 => $none,
                    $locked->count() === $count => 'Sono già tutte bloccate',
                    default => null,
                },
                'unlock' => match (true) {
                    $count === 0 => $none,
                    $locked->isEmpty() => 'Nessuna pagina selezionata è bloccata',
                    default => null,
                },
                'link' => match (true) {
                    $count < 2 => 'Seleziona almeno 2 pagine da collegare',
                    $locked->isNotEmpty() => 'Una o più pagine selezionate sono bloccate: sbloccale prima',
                    $groupIds->count() === 1 && $groupIds->first() !== null => 'Sono già collegate in un gruppo',
                    $grouped->isNotEmpty() => 'Una o più pagine fanno già parte di un altro gruppo: scollegale prima',
                    default => null,
                },
                'unlink' => match (true) {
                    $count === 0 => $none,
                    $grouped->isEmpty() => 'Nessuna pagina selezionata fa parte di un gruppo',
                    default => null,
                },
            ],
        ];
    }

    /**
     * Posizioni compresse in intervalli: [2, 5, 6, 7, 9] → «2, 5–7, 9».
     * Oltre 6 pezzi si accorcia («2, 5–7, 9, … e altre 4»): la riga resta una.
     *
     * @param  list<int|null>  $positions
     */
    public static function ranges(array $positions): string
    {
        $positions = array_values(array_unique(array_filter($positions, fn ($p) => $p !== null)));
        sort($positions);

        $parts = [];
        $start = $previous = null;

        foreach ($positions as $position) {
            if ($start === null) {
                $start = $previous = $position;
            } elseif ($position === $previous + 1) {
                $previous = $position;
            } else {
                $parts[] = $start === $previous ? (string) $start : "{$start}–{$previous}";
                $start = $previous = $position;
            }
        }

        if ($start !== null) {
            $parts[] = $start === $previous ? (string) $start : "{$start}–{$previous}";
        }

        if (count($parts) > 6) {
            return implode(', ', array_slice($parts, 0, 4)).', … e altre '.(count($parts) - 4);
        }

        return implode(', ', $parts);
    }
}
