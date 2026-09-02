<?php

namespace App\Support;

use App\Enums\PageContentType;
use App\Enums\PageStatus;
use App\Models\Issue;
use App\Models\Page;

/**
 * Fa spazio a N pagine bianche a partire da una posizione, traslando in
 * avanti quelle già presenti — estratta da Grid::makeRoomForPages() (Punto
 * 4 del prompt, 2026-09-02): usata dal pannello "pagine totali" (Grid) E
 * dalla terza scelta di conflitto upload multi-pagina ("sposta le pagine
 * esistenti dopo", ora su App\Livewire\Timone\ContentCreate) — la stessa
 * meccanica serviva già a entrambi i chiamanti prima di questa estrazione,
 * qui diventa richiamabile da qualunque componente invece di restare
 * privata su Grid.
 */
class PageInserter
{
    public static function makeRoom(Issue $issue, int $insertBefore, int $countToAdd, int $currentTotal): void
    {
        if ($countToAdd < 1) {
            return;
        }

        if ($insertBefore <= $currentTotal) {
            // Stessa tecnica a offset temporaneo di Grid::movePage(), per
            // non violare unique(issue_id, position) mentre si fa spazio
            // alle nuove pagine.
            $toShift = $issue->pages()->where('position', '>=', $insertBefore)->pluck('id', 'position');
            $offset = $currentTotal + $countToAdd;

            foreach ($toShift as $position => $id) {
                Page::whereKey($id)->update(['position' => $position + $offset]);
            }
            foreach ($toShift as $position => $id) {
                Page::whereKey($id)->update(['position' => $position + $countToAdd]);
            }
        }

        $now = now();
        $newRows = [];

        for ($i = 0; $i < $countToAdd; $i++) {
            $newRows[] = [
                'issue_id' => $issue->id,
                'position' => $insertBefore + $i,
                'content_type' => PageContentType::Bianca->value,
                'status' => PageStatus::DaAssegnare->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Page::insert($newRows);
    }
}
