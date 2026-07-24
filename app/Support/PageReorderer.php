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
}
