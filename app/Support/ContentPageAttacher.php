<?php

namespace App\Support;

use App\Models\Content;
use App\Models\Page;
use Illuminate\Support\Facades\DB;

/**
 * Allega un Content a una Page (verifica spazio, scrittura del pivot,
 * sincronizzazione di Page::content_type) — estratta da
 * Grid::attachContentToPage() (Punto 4 del prompt, 2026-09-02): il
 * collegamento automatico di un contenuto PDF multi-pagina alle N pagine
 * derivate (App\Livewire\Timone\ContentCreate) deve riusare esattamente
 * questa stessa logica, non una sua copia — Grid::assignContent()/
 * extendToPage() restano gli unici punti che parlano con l'utente
 * (autorizzazione, addError(), ActivityLog, broadcast), questa classe fa
 * solo il calcolo e la scrittura, stesso principio di PageGroupLinker.
 */
class ContentPageAttacher
{
    /**
     * Fase 3 (2026-10-27): lo spazio è in dodicesimi. $twelfths lo decide
     * il chiamante quando conosce la distribuzione (ContentPlacer: 12, 12,
     * 6…); altrimenti una pagina aggiuntiva riceve una pagina intera, o
     * tutto il contenuto se è meno di una pagina
     * (PageOccupancy::perExtraPage()).
     *
     * @return int|string i dodicesimi scritti, oppure il messaggio d'errore (spazio insufficiente o occupazione mancante)
     */
    public static function attach(Content $content, Page $page, ?int $contentFileId = null, ?int $twelfths = null): int|string
    {
        $twelfths ??= $content->occupancy()?->perExtraPage();

        if ($twelfths === null) {
            return "«{$content->displayLabel()}» non ha un'occupazione di pagina: impostala prima di posizionarlo.";
        }

        $occupied = $page->contents()->pluck('occupied_twelfths')->all();

        if (! PageCapacity::fits($occupied, $twelfths)) {
            return PageCapacity::insufficientSpaceMessage($page->label(), $twelfths, PageCapacity::free($occupied), $content->displayLabel());
        }

        $page->contents()->attach($content->id, [
            'occupied_twelfths' => $twelfths,
            'content_file_id' => $contentFileId,
            // Fase 7b: in fondo all'ordine della pagina.
            'position' => (int) DB::table('page_content')->where('page_id', $page->id)->max('position') + 1,
        ]);

        self::syncPageContentType($page);

        return $twelfths;
    }

    /**
     * Bug reale scoperto il 2026-08-31 (vedi HANDOFF.md): assegnare/
     * rimuovere un contenuto non aggiornava mai `page.content_type` (il
     * colore della card) — restava "bianca" per sempre. Va richiamato dopo
     * OGNI attach/detach, non solo qui: Grid::unassignContent() la chiama
     * anche dopo aver rimosso un contenuto, per lo stesso motivo.
     */
    public static function syncPageContentType(Page $page): void
    {
        $types = $page->contents()->pluck('type')->all();
        $resolved = PageContentTypeResolver::resolve($types);

        if ($resolved !== $page->content_type) {
            $page->update(['content_type' => $resolved]);
        }
    }
}
