<?php

namespace App\Support;

class PageReorderer
{
    /**
     * Calcola le nuove posizioni conseguenti allo spostamento di una pagina.
     *
     * @param  array<int, int>  $positions  page_id => posizione attuale (permutazione di 1..N)
     * @return array<int, int> page_id => nuova posizione, solo per le pagine che cambiano
     */
    public static function move(array $positions, int $pageId, int $toPosition): array
    {
        if (! array_key_exists($pageId, $positions)) {
            throw new \InvalidArgumentException("Page {$pageId} is not part of the given positions map.");
        }

        $total = count($positions);
        $toPosition = max(1, min($toPosition, $total));

        $orderedIds = collect($positions)->sortBy(fn ($position) => $position)->keys()
            ->reject(fn ($id) => $id === $pageId)->values();
        $orderedIds->splice($toPosition - 1, 0, [$pageId]);

        $changes = [];
        foreach ($orderedIds->values() as $index => $id) {
            $newPosition = $index + 1;
            if ($positions[$id] !== $newPosition) {
                $changes[$id] = $newPosition;
            }
        }

        return $changes;
    }

    /**
     * Calcola le nuove posizioni per lo scambio diretto di due pagine
     * ("modalità scambio", spec §6.2/§6.7) — a differenza di move(), che
     * fa slittare tutte le pagine intermedie tra la posizione di partenza
     * e quella di arrivo, qui SOLO le due pagine indicate cambiano
     * posizione, scambiandosi semplicemente tra loro.
     *
     * @param  array<int, int>  $positions  page_id => posizione attuale (permutazione di 1..N)
     * @return array<int, int>  page_id => nuova posizione, solo le due pagine coinvolte (vuoto se pageIdA === pageIdB)
     */
    public static function swap(array $positions, int $pageIdA, int $pageIdB): array
    {
        if (! array_key_exists($pageIdA, $positions) || ! array_key_exists($pageIdB, $positions)) {
            throw new \InvalidArgumentException('Entrambe le pagine devono far parte della mappa delle posizioni.');
        }

        if ($pageIdA === $pageIdB) {
            return [];
        }

        return [
            $pageIdA => $positions[$pageIdB],
            $pageIdB => $positions[$pageIdA],
        ];
    }
}
