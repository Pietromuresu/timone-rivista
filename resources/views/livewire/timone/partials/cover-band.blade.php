{{--
    Fase 2 (2026-10-26): fascia delle copertine, visivamente separata dalle
    pagine interne, PRIMA della pagina 1, con tutte le copertine in ordine di
    sfoglio (I, bandella, II, III, IV). Una prima versione aveva III e IV in
    una seconda fascia dopo l'ultima pagina: l'utente le ha volute tutte
    insieme in cima, dove si trovano subito anche su un numero lungo. Le card sono le stesse delle pagine
    (page-card / page-row con $isCover), ma stanno FUORI dal contenitore
    Sortable: non si possono trascinare tra le pagine interne né ricevere
    una pagina interna. Tra loro si scambiano col drag nativo
    (Grid::swapCovers()).

    Parametri: $covers (Collection<Page>), $viewMode, più quelli che
    page-card/page-row si aspettano.
--}}
<section
    data-testid="cover-band"
    aria-label="Copertine"
    class="rounded-lg border-2 border-indigo-200 dark:border-indigo-800 bg-indigo-50/60 dark:bg-indigo-950/30 p-3"
>
    <div class="flex items-center justify-between gap-2 mb-3 text-xs">
        <span class="font-medium text-indigo-800 dark:text-indigo-300">
            📘 Copertine — fuori dalla numerazione delle pagine interne
        </span>

        @can('update', $issue)
            {{-- La bandella vale 2 pagine di copertina nel peso (6 invece di 4). --}}
            @if ($hasBandella)
                <button type="button" wire:click="removeBandella" wire:confirm="Togliere la bandella? Si può solo se è vuota." class="text-indigo-700 dark:text-indigo-300 hover:underline">− Togli bandella</button>
            @else
                <button type="button" wire:click="addBandella" class="text-indigo-700 dark:text-indigo-300 hover:underline">+ Aggiungi bandella</button>
            @endif
        @endcan
    </div>

    @if ($viewMode === 'lista')
        <ul class="divide-y divide-indigo-100 dark:divide-indigo-900 bg-white dark:bg-gray-800 rounded">
            @foreach ($covers as $cover)
                @include('livewire.timone.partials.page-row', ['page' => $cover, 'dropEnabled' => true, 'avgThumbnailSeconds' => $avgThumbnailSeconds])
            @endforeach
        </ul>
    @else
        <div class="flex flex-wrap gap-5">
            @foreach ($covers as $cover)
                @include('livewire.timone.partials.page-card', [
                    'page' => $cover,
                    'dropEnabled' => true,
                    'avgThumbnailSeconds' => $avgThumbnailSeconds,
                    'pageAspectRatio' => $pageAspectRatio,
                    'extraClasses' => 'w-[var(--timone-page-w,13rem)]',
                ])
            @endforeach
        </div>
    @endif
</section>
