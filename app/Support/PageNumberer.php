<?php

namespace App\Support;

use App\Enums\PageRole;
use Illuminate\Support\Collection;

/**
 * Numerazione "visualizzata" delle pagine: la copertina deve avere il
 * proprio slot separato e non contare come pagina 1, con la numerazione
 * interna che riparte da 1 subito dopo — richiesta esplicita, ma
 * implementata SENZA toccare `pages.position` (rimane l'unica sequenza
 * fisica usata da PageReorderer/drag&drop/PageSpreadBuilder/export: nessuna
 * migration, nessuna riscrittura di quella logica già stabile — scelta
 * presa esplicitamente con l'utente per limitare il rischio di
 * regressione su codice testato, vedi HANDOFF.md). Questa classe calcola
 * solo l'ETICHETTA mostrata all'utente: le quattro pagine di copertina
 * (mai numerate, `PageRole::isCoverPage()`) mostrano il nome del loro
 * ruolo; tutte le altre (interne + battente, se conteggiato per questa
 * rivista) sono numerate 1,2,3... nell'ordine fisico.
 *
 * Pura/testabile come PageReorderer/PageGroupResolver: nessuna query
 * Eloquent al suo interno, opera solo sulle pagine già passate.
 */
class PageNumberer
{
    /**
     * @param  Collection<int, \App\Models\Page>  $pages  Già ordinate per position.
     * @return array<int, string> page_id => etichetta mostrata
     */
    public static function assign(Collection $pages, bool $battenteCounted = true): array
    {
        $labels = [];
        $counter = 0;

        foreach ($pages as $page) {
            if ($page->role->isCoverPage() || ($page->role === PageRole::Battente && ! $battenteCounted)) {
                $labels[$page->id] = $page->role->label();

                continue;
            }

            $counter++;
            $labels[$page->id] = (string) $counter;
        }

        return $labels;
    }
}
