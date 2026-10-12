<?php

namespace App\Support;

use App\Models\Content;
use App\Models\Page;

/**
 * Allega un Content a una Page (calcolo percentuale, verifica spazio,
 * scrittura del pivot, sincronizzazione di Page::content_type) — estratta
 * da Grid::attachContentToPage() (Punto 4 del prompt, 2026-09-02): il
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
     * @param  float|null  $percentage  già calcolata dal chiamante (ContentPlacer), altrimenti dedotta dal contenuto
     * @return string|null  La percentuale attribuita (come stringa, stesso formato già passato ad attach()), o null se lo spazio sulla pagina non basta.
     */
    public static function attach(Content $content, Page $page, ?int $contentFileId = null, ?float $percentage = null): ?string
    {
        $occupied = $page->contents()->pluck('occupied_percentage')->all();

        // Bug 5 (2026-10-12): un articolo non prende più "tutto lo spazio
        // libero" della pagina (PagePercentageAllocator::freeSpace(), il
        // vecchio default implicito) ma la percentuale dichiarata dalla sua
        // occupazione. Occupazione mancante → null, come "spazio
        // insufficiente": il chiamante rifiuta, non sceglie un default.
        $percentage ??= $content->occupiedPercentagePerPage();

        if ($percentage === null || ! PagePercentageAllocator::fits($occupied, $percentage)) {
            return null;
        }

        $page->contents()->attach($content->id, [
            'occupied_percentage' => (string) $percentage,
            'content_file_id' => $contentFileId,
        ]);

        self::syncPageContentType($page);

        return (string) $percentage;
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
