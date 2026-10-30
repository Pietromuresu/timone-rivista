{{--
    Fase 2 (2026-10-26): fascia delle copertine, visivamente separata dalle
    pagine interne, PRIMA della pagina 1, con tutte le copertine in ordine di
    sfoglio (battente I, I, II, III, IV, battente IV). Una prima versione aveva III e IV in
    una seconda fascia dopo l'ultima pagina: l'utente le ha volute tutte
    insieme in cima, dove si trovano subito anche su un numero lungo. Le card sono le stesse delle pagine
    (page-card / page-row con $isCover), ma stanno FUORI dal contenitore
    Sortable: non si possono trascinare tra le pagine interne né ricevere
    una pagina interna. Tra loro si scambiano col drag nativo
    (Grid::swapCovers()).

    Battenti (2026-10-30): un bottone per battente, «+ Battente I» o
    «− Togli battente I». Se non si può togliere il bottone resta visibile
    ma disattivato, con il motivo nel tooltip (IssueCovers::removalBlocker(),
    lo stesso messaggio del rifiuto lato server).

    Parametri: $covers (Collection<Page>), $flaps, $viewMode, più quelli che
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

        <span class="flex flex-wrap items-center justify-end gap-x-3 gap-y-1">
            @can('update', $issue)
                @foreach ($flaps as ['flap' => $flap, 'present' => $present, 'blocker' => $blocker])
                    @if (! $present)
                        <button type="button" wire:click="addFlap('{{ $flap->value }}')" class="text-indigo-700 dark:text-indigo-300 hover:underline" data-testid="add-flap-{{ $flap->value }}">+ {{ $flap->label() }}</button>
                    @elseif ($blocker !== null)
                        {{-- aria-disabled e non disabled: su un bottone disabled alcuni browser non mostrano il tooltip e la tastiera non ci arriva. --}}
                        <button type="button" aria-disabled="true" title="{{ $blocker }}" aria-label="Togli {{ lcfirst($flap->label()) }}: {{ $blocker }}" class="text-gray-400 dark:text-gray-500 cursor-not-allowed" data-testid="remove-flap-{{ $flap->value }}">− Togli {{ lcfirst($flap->label()) }}</button>
                    @else
                        <button type="button" wire:click="removeFlap('{{ $flap->value }}')" wire:confirm="Togliere il {{ lcfirst($flap->label()) }}? Le sue 2 pagine sono vuote." class="text-indigo-700 dark:text-indigo-300 hover:underline" data-testid="remove-flap-{{ $flap->value }}">− Togli {{ lcfirst($flap->label()) }}</button>
                    @endif
                @endforeach
            @endcan
            <x-manual-help slug="copertine" anchor="i-battenti" label="Battenti" />
        </span>
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
