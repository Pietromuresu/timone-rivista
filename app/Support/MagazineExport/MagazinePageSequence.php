<?php

namespace App\Support\MagazineExport;

use App\Enums\CoverSlot;
use App\Models\Issue;
use App\Models\Page;
use Illuminate\Support\Collection;

/**
 * Ordine delle pagine nel PDF sfogliabile (revisione 2, Fase 5): I di
 * copertina, II di copertina, pagine interne 1…N, III, IV, con i battenti
 * (2026-10-30) prima della I e dopo la IV — CoverSlot::front()/back().
 *
 * Il PDF si apre a coppie con la prima pagina sola a destra
 * (TwoPageRight). Ogni battente ha 2 pagine, quindi la pagina interna 1
 * resta sempre a destra accanto alla II: [BI·1] [BI·2|I] [II|1] … La
 * vecchia bandella era una pagina sola e spostava tutte le coppie.
 *
 * Le relazioni servono già caricate per la disposizione (Page::layout()):
 * qui una sola query per tutte le pagine del numero.
 */
final class MagazinePageSequence
{
    /** @return Collection<int, array{page: Page, label: string}> */
    public static function for(Issue $issue): Collection
    {
        $pages = $issue->allPages()
            ->with(['contents.article', 'contents.advertisement', 'contents.files', 'files' => fn ($query) => $query->latest()])
            ->get()
            ->each(fn (Page $page) => $page->setRelation('issue', $issue));

        $covers = $pages->filter(fn (Page $page) => $page->isCover())->keyBy(fn (Page $page) => $page->cover_slot->value);
        $internal = $pages->reject(fn (Page $page) => $page->isCover())->sortBy('position')->values();

        $front = collect(CoverSlot::front())->map(fn (CoverSlot $slot) => $covers->get($slot->value))->filter();
        $back = collect(CoverSlot::back())->map(fn (CoverSlot $slot) => $covers->get($slot->value))->filter();

        return $front->concat($internal)->concat($back)
            ->map(fn (Page $page) => ['page' => $page, 'label' => $page->isCover() ? $page->cover_slot->label() : 'Pagina '.$page->position])
            ->values();
    }
}
