<?php

namespace App\Support\MagazineExport;

use App\Enums\CoverSlot;
use App\Models\Issue;
use App\Models\Page;
use Illuminate\Support\Collection;

/**
 * Ordine delle pagine nel PDF sfogliabile (revisione 2, Fase 5): I di
 * copertina, II di copertina, pagine interne 1…N, III, IV. La bandella,
 * se c'è, segue la I (è il risvolto della prima di copertina: stesso
 * ordine di sfoglio di CoverSlot::order()).
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

        $front = collect([CoverSlot::I, CoverSlot::Bandella, CoverSlot::II])->map(fn (CoverSlot $slot) => $covers->get($slot->value))->filter();
        $back = collect([CoverSlot::III, CoverSlot::IV])->map(fn (CoverSlot $slot) => $covers->get($slot->value))->filter();

        return $front->concat($internal)->concat($back)
            ->map(fn (Page $page) => ['page' => $page, 'label' => $page->isCover() ? $page->cover_slot->label() : 'Pagina '.$page->position])
            ->values();
    }
}
