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

    /**
     * Calcola le nuove posizioni per lo spostamento di un intero blocco di
     * pagine selezionate come unità unica (Fase 5) — a differenza di
     * move() (una sola pagina, con tutte le pagine intermedie che
     * slittano), qui l'intero insieme di pagine viene rimosso dalla
     * sequenza e reinserito **insieme**, come blocco contiguo, alla
     * posizione di destinazione.
     *
     * L'ordine interno del blocco al reinserimento è quello delle
     * posizioni ATTUALI delle pagine ($positions), non l'ordine in cui
     * compaiono in $blockPageIds (che arriva da una selezione utente, in
     * un ordine qualunque) — "mantenere l'ordine relativo interno delle
     * pagine selezionate" è la richiesta esplicita della fase.
     *
     * **Selezione non contigua in origine**: questo stesso meccanismo di
     * rimozione + reinserimento la compatta automaticamente in un'unica
     * sequenza contigua alla destinazione, invece di rifiutare
     * l'operazione — scelta esplicita (vedi HANDOFF.md, Fase 5): il nome
     * stesso dell'operazione ("blocco unico") implica che il risultato sia
     * sempre un blocco compatto, mai una selezione che resta sparsa dopo
     * lo spostamento.
     *
     * @param  array<int, int>  $positions  page_id => posizione attuale (permutazione di 1..N)
     * @param  list<int>  $blockPageIds  id delle pagine selezionate, in qualunque ordine
     * @return array<int, int>  page_id => nuova posizione, solo per le pagine che cambiano
     */
    public static function moveBlock(array $positions, array $blockPageIds, int $toPosition): array
    {
        foreach ($blockPageIds as $pageId) {
            if (! array_key_exists($pageId, $positions)) {
                throw new \InvalidArgumentException("Page {$pageId} is not part of the given positions map.");
            }
        }

        $total = count($positions);
        // Fuori dai limiti dell'edizione ("spostamento che supera i limiti",
        // richiesto esplicitamente dalla fase): stesso clamp già usato da
        // move(), mai un errore — la destinazione si aggancia semplicemente
        // all'ultima posizione valida.
        $toPosition = max(1, min($toPosition, $total));

        $blockIdSet = array_flip($blockPageIds);

        $orderedBlock = collect($positions)
            ->only(array_keys($blockIdSet))
            ->sortBy(fn ($position) => $position)
            ->keys()
            ->values();

        $remainingIds = collect($positions)->sortBy(fn ($position) => $position)->keys()
            ->reject(fn ($id) => isset($blockIdSet[$id]))->values();

        $insertIndex = min($toPosition - 1, $remainingIds->count());
        $remainingIds->splice($insertIndex, 0, $orderedBlock->all());

        $changes = [];
        foreach ($remainingIds->values() as $index => $id) {
            $newPosition = $index + 1;
            if ($positions[$id] !== $newPosition) {
                $changes[$id] = $newPosition;
            }
        }

        return $changes;
    }

    /**
     * Destinazione per uno spostamento "di un passo" da tastiera (Fase 1,
     * bug 4, 2026-10-26), nel formato atteso da move() per una pagina
     * singola e da moveBlock() per un blocco: per un blocco la destinazione
     * è la posizione in cui deve INIZIARE il blocco, quindi va calcolata
     * dalla sua prima pagina, non da quella che ha il focus. Prima il
     * timone passava "posizione della pagina ± 1" in entrambi i casi: con
     * il focus sulla seconda pagina di un gruppo, freccia sinistra
     * spostava il gruppo a DESTRA.
     *
     * @param  array<int, int>  $positions  page_id => posizione attuale
     * @param  list<int>  $blockPageIds  pagine che si muovono insieme (una sola per una pagina non collegata)
     * @return int|null  null se il blocco è già al bordo in quella direzione
     */
    public static function nudgeTarget(array $positions, array $blockPageIds, int $delta): ?int
    {
        $blockPositions = array_map(fn (int $id) => $positions[$id], $blockPageIds);
        $start = min($blockPositions);
        $end = max($blockPositions);
        $step = $delta < 0 ? -1 : 1;

        if (($step < 0 && $start <= 1) || ($step > 0 && $end >= count($positions))) {
            return null;
        }

        return $start + $step;
    }
}
