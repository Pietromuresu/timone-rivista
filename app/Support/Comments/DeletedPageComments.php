<?php

namespace App\Support\Comments;

use App\Models\Page;
use App\Models\PageComment;
use Illuminate\Support\Collection;

/**
 * Prima di eliminare davvero delle pagine (riduzione del totale, bandella
 * tolta, conversione copertine annullata) i loro commenti si staccano dalla
 * pagina e si tengono il nome di ADESSO («pagina 47»): restano nel numero,
 * consultabili come «pagina 47, eliminata». Poi la FK nullOnDelete mette
 * page_id a NULL senza errori. Chiamare SEMPRE prima della delete: dopo, la
 * posizione della pagina non c'è più.
 */
final class DeletedPageComments
{
    /** @param  Collection<int, Page>  $pages */
    public static function preserve(Collection $pages): void
    {
        $touched = false;

        foreach ($pages as $page) {
            $touched = PageComment::withTrashed()->where('page_id', $page->id)->update([
                'page_label' => $page->label(),
                'page_deleted_at' => now(),
                'page_id' => null,
            ]) > 0 || $touched;
        }

        // Le icone delle pagine eliminate spariscono, il contatore della testata no.
        if ($touched) {
            $pages->first()->issue->markCommentsUpdated();
        }
    }
}
