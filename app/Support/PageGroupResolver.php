<?php

namespace App\Support;

class PageGroupResolver
{
    /**
     * Dato un insieme di pagine "seme" (quelle esplicitamente trascinate o
     * selezionate) e la mappa page_id => page_group_id dell'intera issue,
     * restituisce l'insieme completo di id che devono muoversi insieme:
     * ogni pagina che condivide un gruppo con un seme viene inclusa anche
     * se non era stata selezionata esplicitamente.
     *
     * Usata in due punti diversi da Grid.php con lo stesso identico
     * significato — "espandi ai gruppi collegati" — invece di duplicare la
     * logica: movePage() (seme = [pageId] trascinato, per sapere se e con
     * chi deve muoversi insieme) e moveSelectedBlock() (seme =
     * selectedPageIds, per il caso "selezione parziale di un gruppo
     * esistente" — decisione presa in Fase 2.3, vedi HANDOFF.md: le pagine
     * del gruppo non selezionate esplicitamente vengono incluse comunque,
     * mai un'operazione bloccata o silenziosamente parziale).
     *
     * Pura: nessuna query Eloquent, opera solo sui dati già passati — stesso
     * pattern di PageReorderer/PageCapacity/AdLoadCalculator.
     *
     * @param  array<int, int|null>  $groupIdByPageId  page_id => page_group_id (o null se non collegata)
     * @param  list<int>  $seedPageIds
     * @return list<int> seme ∪ pagine con un gruppo in comune con un seme, deduplicate
     */
    public static function expand(array $groupIdByPageId, array $seedPageIds): array
    {
        $seedGroupIds = collect($seedPageIds)
            ->map(fn (int $id) => $groupIdByPageId[$id] ?? null)
            ->filter()
            ->unique();

        if ($seedGroupIds->isEmpty()) {
            return array_values(array_unique($seedPageIds));
        }

        $expanded = collect($groupIdByPageId)
            ->filter(fn (?int $groupId) => $groupId !== null && $seedGroupIds->contains($groupId))
            ->keys();

        return $expanded->merge($seedPageIds)->unique()->values()->all();
    }
}
