<?php

namespace App\Support;

use App\Enums\PageStatus;
use Illuminate\Support\Collection;

/**
 * Controlli automatici (spec §10). Solo il sottoinsieme realizzabile senza
 * estendere lo schema — la maggior parte dei 14 controlli elencati nello
 * spec presuppone concetti mai costruiti in questo progetto (scadenze su
 * Content/Advertisement, blocco pagina, lato pagina, copertine distinte).
 * "Carico pubblicitario oltre soglia" e "pagine non multiplo di 4" sono
 * già coperti altrove (cruscotto pubblicitario, creazione/modifica
 * numero) e non ripetuti qui. Pura/testabile come le altre classi in
 * App\Support: opera sulle `$pages` già caricate da Grid::render(),
 * nessuna query propria.
 */
class AutomaticChecks
{
    /**
     * @param  Collection<int, \App\Models\Page>  $pages
     * @return array{
     *     nonContiguousContents: list<array{title: string, positions: list<int>}>,
     *     approvedEmptyPages: list<int>,
     * }
     */
    public static function check(Collection $pages): array
    {
        return [
            'nonContiguousContents' => self::nonContiguousContents($pages),
            'approvedEmptyPages' => self::approvedEmptyPages($pages),
        ];
    }

    /**
     * Contenuti assegnati a più pagine le cui posizioni non sono
     * consecutive (spec: "articolo spezzato su pagine non contigue senza
     * motivo") — un segnale da rivedere, non necessariamente un errore:
     * può essere del tutto intenzionale, per questo qui è solo un avviso,
     * mai un blocco.
     *
     * @return list<array{title: string, positions: list<int>}>
     */
    private static function nonContiguousContents(Collection $pages): array
    {
        $seenContentIds = [];
        $flagged = [];

        foreach ($pages as $page) {
            foreach ($page->contents as $content) {
                if (isset($seenContentIds[$content->id])) {
                    continue;
                }
                $seenContentIds[$content->id] = true;

                $positions = $content->pages->pluck('position')->sort()->values();

                if ($positions->count() > 1 && ! self::isContiguous($positions)) {
                    $flagged[] = [
                        'title' => $content->displayLabel(),
                        'positions' => $positions->all(),
                    ];
                }
            }
        }

        return $flagged;
    }

    /**
     * @param  Collection<int, int>  $sortedPositions
     */
    private static function isContiguous(Collection $sortedPositions): bool
    {
        $first = $sortedPositions->first();

        foreach ($sortedPositions->values() as $index => $position) {
            if ($position !== $first + $index) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pagine già "Revisionata"/"Ok stampa" ma senza nessun contenuto
     * assegnato — un'incongruenza degna di attenzione: come può essere
     * pronta per la stampa una pagina vuota? (spec: "modifica di una
     * pagina già approvata" è tra i controlli richiesti; questa è la
     * variante concretamente verificabile con lo schema attuale).
     *
     * @return list<int>
     */
    private static function approvedEmptyPages(Collection $pages): array
    {
        $approvedStatuses = [PageStatus::Revisionata, PageStatus::OkStampa];

        return $pages
            ->filter(fn ($page) => in_array($page->status, $approvedStatuses, true) && $page->contents->isEmpty())
            ->pluck('position')
            ->values()
            ->all();
    }
}
