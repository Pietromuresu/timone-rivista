<?php

namespace App\Support;

use App\Models\Issue;
use App\Models\Page;
use App\Models\PageGroup;
use Illuminate\Support\Collection;

/**
 * Crea/scioglie/riduce gruppi di pagine collegate (Fase 2.3) — estratta da
 * Grid::linkSelectedPages()/unlinkGroup() (Punto 4 del prompt, 2026-09-02):
 * il collegamento automatico delle pagine derivate da un contenuto PDF
 * multi-pagina deve riusare ESATTAMENTE la stessa logica di collegamento
 * già usata dal pulsante "🔗 Collega selezionate"/dalla shortcut
 * Ctrl+Shift+G, non una sua copia — prima viveva accoppiata a
 * $this->selectionMode/$this->selectedPageIds dentro il componente Grid,
 * qui diventa pura (Issue + collezione di Page in ingresso), chiamabile da
 * qualunque componente Livewire. Grid::linkSelectedPages()/unlinkGroup()
 * restano gli unici punti che parlano con l'utente (autorizzazione,
 * addError(), ActivityLog, broadcast) — questa classe fa solo il lavoro
 * sui dati, stesso principio già seguito da PageReorderer/PageGroupResolver.
 */
class PageGroupLinker
{
    public const ERROR_LOCKED = 'locked';

    public const ERROR_ALREADY_GROUPED = 'already_grouped';

    /**
     * Crea un nuovo gruppo con le pagine indicate — comportamento invariato
     * da Grid::linkSelectedPages() (2026-08-31): rifiuta se una pagina è
     * bloccata o già in un altro gruppo (unire due gruppi esistenti resta
     * fuori scopo, va scollegato esplicitamente prima).
     *
     * @param  Collection<int, Page>  $pages
     * @return PageGroup|string  Il gruppo creato, o una costante ERROR_* sopra.
     */
    public static function link(Issue $issue, Collection $pages): PageGroup|string
    {
        if ($pages->contains(fn (Page $page) => $page->isLocked())) {
            return self::ERROR_LOCKED;
        }

        if ($pages->contains(fn (Page $page) => $page->page_group_id !== null)) {
            return self::ERROR_ALREADY_GROUPED;
        }

        $color = PageGroup::PALETTE[PageGroup::where('issue_id', $issue->id)->count() % count(PageGroup::PALETTE)];
        $group = PageGroup::create(['issue_id' => $issue->id, 'color' => $color]);

        Page::whereIn('id', $pages->pluck('id'))->update(['page_group_id' => $group->id]);

        return $group;
    }

    /**
     * Scioglie l'intero gruppo $groupId, non solo una pagina — comportamento
     * invariato da Grid::unlinkGroup() (2026-08-31): un gruppo con una sola
     * pagina rimasta non avrebbe senso.
     *
     * @return Collection<int, Page>|string  Le pagine che erano nel gruppo (per log/broadcast del chiamante), o ERROR_LOCKED.
     */
    public static function unlink(int $groupId): Collection|string
    {
        $groupPages = Page::where('page_group_id', $groupId)->get();

        if ($groupPages->contains(fn (Page $p) => $p->isLocked())) {
            return self::ERROR_LOCKED;
        }

        Page::where('page_group_id', $groupId)->update(['page_group_id' => null]);
        PageGroup::whereKey($groupId)->delete();

        return $groupPages;
    }

    /**
     * Rimuove SOLO le pagine indicate dal proprio gruppo, lasciando le
     * altre pagine di quel gruppo ancora collegate tra loro — a differenza
     * di unlink() sopra (tutto-o-niente). Se dopo la rimozione un gruppo
     * scende sotto 2 pagine, viene sciolto per intero (un gruppo da 1
     * pagina non ha senso, stesso principio di unlink()). Capacità nuova
     * (Punto 4.6 del prompt, decisa esplicitamente con l'utente il
     * 2026-09-02: "implementa scollegamento parziale... riusata ovunque
     * serva granularità, non solo per i gruppi da PDF") — generale, non
     * specifica ai gruppi creati da un caricamento PDF multi-pagina.
     *
     * Solo le pagine effettivamente da rimuovere devono essere sbloccate:
     * le altre pagine dello stesso gruppo, che restano collegate tra loro
     * e il cui page_group_id non viene toccato, non sono soggette al
     * controllo — a differenza di unlink(), che invece modifica TUTTE le
     * pagine del gruppo e quindi le richiede tutte sbloccate.
     *
     * @param  Collection<int, Page>  $pages
     * @return Collection<int, Page>|string  Le pagine effettivamente rimosse dal loro gruppo (può essere vuota se nessuna delle pagine indicate era in un gruppo), o ERROR_LOCKED.
     */
    public static function removeFromGroup(Collection $pages): Collection|string
    {
        $grouped = $pages->filter(fn (Page $page) => $page->page_group_id !== null);

        if ($grouped->isEmpty()) {
            return $grouped->values();
        }

        if ($grouped->contains(fn (Page $page) => $page->isLocked())) {
            return self::ERROR_LOCKED;
        }

        $affectedGroupIds = $grouped->pluck('page_group_id')->unique();

        Page::whereIn('id', $grouped->pluck('id'))->update(['page_group_id' => null]);

        foreach ($affectedGroupIds as $groupId) {
            if (Page::where('page_group_id', $groupId)->count() < 2) {
                Page::where('page_group_id', $groupId)->update(['page_group_id' => null]);
                PageGroup::whereKey($groupId)->delete();
            }
        }

        return $grouped->values();
    }
}
