@php
    // Punto 4 del prompt (2026-09-02): l'anteprima ora viene dal contenuto
    // assegnato alla pagina (Page::previewFile()), con fallback al vecchio
    // PageFile diretto solo per le pagine caricate prima di questa
    // modifica e non ancora migrate (Punto 8, non eseguito in questa
    // sessione). $previewIsPageFile distingue le due sole differenze
    // rilevanti qui: quali route usare (page-files.*/content-files.*) e
    // se mostrare il bottone "🕐 Storico" (solo per il percorso legacy,
    // l'unico che abbia mai avuto uno storico di più upload sulla stessa
    // pagina — vedi più sotto).
    $previewFile = $page->previewFile();
    $previewIsPageFile = $previewFile instanceof \App\Models\PageFile;
    $dropEnabled = $dropEnabled ?? false;
    // Classi extra da appendere al class= del root, per posizionamento
    // specifico del chiamante (es. larghezza/margine/bordo di apertura in
    // vista doppia pagina — vedi grid.blade.php). Il root resta SEMPRE
    // l'unico elemento draggable reale: niente wrapper esterni, altrimenti
    // Sortable non riconosce più questo div come figlio diretto del suo
    // contenitore e il drag&drop smette di partire (bug reale, 2026-09-02).
    $extraClasses = $extraClasses ?? '';
    // Fase 2 (2026-10-26): le copertine (I–IV, bandella) usano questa
    // stessa card — contenuti, anteprima, stato e blocco funzionano uguali —
    // ma sono fuori dalla sequenza delle pagine interne: niente maniglia
    // Sortable, frecce, selezione multipla o modalità scambio; si
    // trascinano solo su un'altra copertina (Grid::swapCovers()).
    // Etichetta: "II di copertina" per le copertine, la posizione per le
    // pagine interne (che dalla Fase 2 partono sempre da 1).
    $isCover = $page->isCover();
    $pageNumber = $pageNumber ?? ($isCover ? $page->cover_slot->label() : $page->position);
    $locked = $page->isLocked();
    // Fase 2.3: pagine "collegate" in un gruppo — vedi App\Models\PageGroup,
    // $page->group già eager-loaded da Grid::render().
    $group = $page->group;
    // dropEnabled riflette la modalità di vista (griglia, doppia e lista lo
    // passano tutte true — vedi grid.blade.php); $locked riflette il
    // blocco pagina (§6.6), ortogonale alla modalità di vista.
    // Solo la combinazione delle due decide se la pagina è davvero editabile.
    $interactive = $dropEnabled && ! $locked;
    // Revisione 2, Fase 4.1: permessi calcolati una volta in Grid::render()
    // (TimoneAbilities, stesse regole del server). Fuori dal timone (nessun
    // $abilities) la vista resta com'era: il server controlla comunque.
    $canMovePage = ! isset(($immovablePageIds ?? [])[$page->id]);
    $editsTimone = isset($abilities) ? $abilities->editsTimone() : true;
    // §2.2: apre il PDF alla pagina interna corretta con il frammento
    // nativo del browser #page=N (nessun pdf.js in questo progetto, vedi
    // HANDOFF.md) — solo se non è la prima pagina del file sorgente.
    $fileUrl = $previewFile
        ? ($previewIsPageFile ? route('page-files.show', $previewFile) : route('content-files.show', $previewFile)).($previewFile->pdf_page_number > 1 ? '#page='.$previewFile->pdf_page_number : '')
        : null;
    // URL dell'anteprima pronta (Page::previewImageUrl()), null se non c'è.
    $previewImageUrl = $page->previewImageUrl();
    // Stima PER QUESTA card (2026-07-31, vedi HANDOFF.md) — dati reali
    // (tempo di orologio trascorso, media osservata su questo numero),
    // mai un placeholder. $avgThumbnailSeconds arriva da Grid::render().
    $thumbnailEstimate = $previewFile && in_array($previewFile->thumbnail_status, [\App\Enums\ThumbnailStatus::Pending, \App\Enums\ThumbnailStatus::Processing], true)
        ? \App\Support\ThumbnailProgressEstimator::forFile($previewFile, $avgThumbnailSeconds ?? null)
        : null;
    // Evidenziazione selezione multipla (2026-09-02, punto 2 del prompt:
    // prima la sola checkbox segnalava una pagina selezionata, nessun
    // riscontro visivo sulla card stessa) — priorità sulle altre "ring"
    // possibili: scambio (indigo, interazione attiva in corso) > selezione
    // multipla (smeraldo, nuovo) > gruppo collegato (colore del gruppo).
    $isMultiSelected = ($selectionMode ?? false) && in_array($page->id, $selectedPageIds ?? [], true);
    $ringClasses = match (true) {
        $swapSelectedPageId === $page->id => 'ring-4 ring-indigo-500',
        $isMultiSelected => 'ring-4 ring-emerald-500',
        (bool) $group => 'ring-2 ring-offset-1 dark:ring-offset-gray-900 '.$group->ringClasses(),
        default => '',
    };
    // Bug 4 (2026-10-12): la card ha tabindex="0", quindi l'ultima cliccata
    // con Ctrl+click resta col focus — e `.focus\:ring-*:focus` (classe +
    // pseudo-classe, più specifica di `.ring-*`) sostituiva il verde della
    // selezione con l'anello indigo da 2px del focus: proprio l'ultima
    // pagina selezionata sembrava non esserlo. Una pagina selezionata (o
    // scelta per lo scambio) tiene lo stesso anello anche quando ha il
    // focus; le altre mantengono l'indicatore di focus da tastiera di
    // sempre. Nessun cambiamento allo stato: la selezione resta solo in
    // Grid::$selectedPageIds (lato server), il polling ri-renderizza dallo
    // stesso stato e quindi non c'era nulla da sospendere o isolare con
    // wire:ignore.
    $focusClasses = match (true) {
        $swapSelectedPageId === $page->id => 'focus:ring-4 focus:ring-indigo-500',
        $isMultiSelected => 'focus:ring-4 focus:ring-emerald-500',
        default => 'focus:ring-2 focus:ring-indigo-500',
    };
    // Fase 6 (2026-10-27): evidenziazione dalla barra di riepilogo
    // (pagine bianche / con pubblicità da assegnare): le pagine che
    // corrispondono hanno un contorno azzurro, le altre sono attenuate.
    $summaryHighlightActive = ($summaryHighlight ?? null) !== null;
    $isHighlighted = $summaryHighlightActive && in_array($page->id, $highlightPageIds ?? [], true);
    $highlightClasses = match (true) {
        $isHighlighted => 'outline outline-4 outline-sky-500 outline-offset-2',
        $summaryHighlightActive => 'opacity-40',
        default => '',
    };
@endphp
<div
    wire:key="page-{{ $page->id }}"
    data-page-id="{{ $page->id }}"
    tabindex="0"
    {{-- Fase 1, bug 4 (2026-10-26): Grid::nudgePage() invece di
         movePage(posizione ± 1) — per una pagina di un gruppo collegato
         movePage() interpreta la destinazione come INIZIO del blocco, quindi
         "posizione − 1" sulla seconda pagina di un gruppo lo spostava a
         destra invece che a sinistra. $animatedMove anima tutte le pagine
         che si spostano (resources/js/timone-alpine.js). --}}
    @if ($page->page_group_id) data-group-id="{{ $page->page_group_id }}" @endif
    @if ($isCover) data-cover-slot="{{ $page->cover_slot->value }}" @endif
    @unless ($locked || $isCover || ! $canMovePage)
        @keydown.arrow-left.prevent="$animatedMove('nudgePage', {{ $page->id }}, -1)"
        @keydown.arrow-right.prevent="$animatedMove('nudgePage', {{ $page->id }}, 1)"
    @endunless
    @if ($interactive)
        x-on:dragover.prevent
        {{-- Fase 2: una copertina rilasciata su un'altra si scambia di slot;
             su una pagina interna viene ignorata (le copertine non entrano
             nella sequenza). Un contenuto si assegna come sempre. --}}
        x-on:drop.prevent="const coverId = $event.dataTransfer.getData('text/cover-id'); const cid = $event.dataTransfer.getData('text/content-id'); if (coverId && {{ $isCover ? 'true' : 'false' }}) { $wire.swapCovers(parseInt(coverId), {{ $page->id }}); } else if (cid) { $wire.assignContent(parseInt(cid), {{ $page->id }}); }"
    @endif
    @if ($isCover)
        {{-- Copertine: nessuna selezione multipla né modalità scambio. --}}
    @elseif ($swapMode && ! $locked)
        x-on:click="$wire.selectForSwap({{ $page->id }})"
    @elseif (! $swapMode)
        {{-- Shortcut da tastiera (2026-09-02, punto 2 del prompt): click
             semplice azzera la selezione multipla e seleziona solo questa
             pagina (no-op se la selezione multipla non è già attiva, vedi
             Grid::clickPage()); Ctrl/Cmd+click la aggiunge/rimuove dalla
             selezione, attivandola al primo utilizzo se serve (vedi
             Grid::ctrlClickPage()). Attivo solo fuori dalla modalità
             scambio, che ha sempre priorità sul click della card (ramo
             sopra) — così le due modalità non competono mai per lo stesso
             evento click. --}}
        x-on:click="$event.ctrlKey || $event.metaKey ? $wire.ctrlClickPage({{ $page->id }}) : $wire.clickPage({{ $page->id }})"
    @endif
    {{--
        Fase 4 (§4): due canali cromatici distinti sulla stessa card, non in
        conflitto — lo sfondo (content_type->colorClasses()) mostra il tipo
        pagina a colpo d'occhio (pubblicità sempre riconoscibile), il bordo
        spesso (status->borderClasses()) mostra lo stato senza dover leggere
        l'etichetta piccola sul badge di stato (che resta comunque, vedi
        sotto — "affianca", non "sostituisce", per chi preferisce leggerla).
    --}}
    {{-- Fase 1, bug 7: proporzioni del formato reale del numero (base/altezza
         del pannello Peso, vedi Grid::render() → $pageAspectRatio) invece di
         un 3/4 fisso; la larghezza la decide lo zoom S/M/L. --}}
    style="aspect-ratio: {{ $pageAspectRatio ?? '3 / 4' }};"
    class="relative flex flex-col rounded-lg border-4 p-2 text-xs overflow-hidden focus:outline-none {{ $focusClasses }} {{ $page->content_type->colorClasses() }} {{ $page->status->borderClasses() }} {{ $locked ? 'opacity-70' : '' }} {{ $swapMode && ! $locked ? 'cursor-pointer' : '' }} {{ $ringClasses }} {{ $isCover && $ringClasses === '' ? 'ring-2 ring-offset-2 ring-indigo-400 dark:ring-offset-gray-900' : '' }} {{ $highlightClasses }} {{ $extraClasses }}"
    @if ($isHighlighted) data-highlighted="1" @endif
>
    <template x-if="$store.pagePresence?.editorFor({{ $page->id }})">
        <span
            class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-emerald-500 text-white text-[8px] flex items-center justify-center ring-2 ring-white dark:ring-gray-800 uppercase animate-pulse z-10"
            x-text="$store.pagePresence?.editorFor({{ $page->id }})?.name.substring(0, 2)"
            :title="$store.pagePresence?.editorFor({{ $page->id }})?.name + ' sta modificando questa pagina'"
        ></span>
    </template>


    <div class="flex items-center justify-between gap-1">
        <div class="flex items-center gap-1">
            @if (($selectionMode ?? false) && ! $isCover)
                <input
                    type="checkbox"
                    x-on:click.stop
                    wire:click="togglePageSelection({{ $page->id }})"
                    @checked(in_array($page->id, $selectedPageIds ?? [], true))
                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                />
            @endif

            @if ($locked)
                <span class="font-semibold text-sm select-none" title="{{ $page->lockedBy ? 'Bloccata da '.$page->lockedBy->name : 'Pagina bloccata' }}">
                    🔒 {{ $pageNumber }}
                </span>
            @elseif (! $canMovePage)
                {{-- Fase 4.1: pagina con articoli e utente che sposta solo
                     pubblicità — niente maniglia (senza .drag-handle Sortable
                     non parte), cursore e motivo nel tooltip. --}}
                <span class="cursor-not-allowed font-semibold text-sm select-none opacity-70" title="{{ \App\Support\Permissions\TimoneAbilities::EDITORIAL_PAGES_ONLY }}" data-testid="page-not-movable">
                    {{ $isCover ? '📘 ' : '' }}{{ $pageNumber }}
                </span>
            @elseif ($isCover)
                {{-- Drag nativo (non Sortable): le copertine non stanno nel
                     contenitore delle pagine interne, si scambiano solo tra
                     loro. Etichetta su sfondo indaco = "è una copertina",
                     leggibile anche senza colori. --}}
                <span
                    draggable="true"
                    x-on:dragstart.stop="$event.dataTransfer.setData('text/cover-id', '{{ $page->id }}'); $event.dataTransfer.effectAllowed = 'move'"
                    class="cursor-grab font-semibold text-[11px] select-none rounded bg-indigo-600 text-white px-1.5 py-0.5 whitespace-nowrap"
                    title="Copertina — trascinala su un'altra copertina per scambiarle"
                >📘 {{ $pageNumber }}</span>
            @else
                <span class="drag-handle cursor-grab font-semibold text-sm select-none" title="Trascina per riordinare">
                    ⠿ {{ $pageNumber }}
                </span>
            @endif
        </div>

        <div class="flex items-center gap-1">
            @if ($interactive && $editsTimone)
                <select
                    x-on:click.stop
                    x-on:keydown.stop
                    x-on:focus="$store.pagePresence?.startEditing({{ $page->id }})"
                    x-on:blur="$store.pagePresence?.stopEditing({{ $page->id }})"
                    wire:change="changePageStatus({{ $page->id }}, $event.target.value)"
                    class="rounded text-[10px] font-medium border-none py-0.5 pl-1.5 pr-5 {{ $page->status->colorClasses() }}"
                >
                    @foreach (\App\Enums\PageStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($status === $page->status)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            @else
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $page->status->colorClasses() }}">
                    {{ $page->status->label() }}
                </span>
            @endif

            {{-- Commenti (2026-10-29): numero dallo store Alpine (si aggiorna
                 senza ridisegnare la griglia), ambra se ce ne sono di non letti. --}}
            <button
                type="button"
                x-on:click.stop="$dispatch('open-page-comments', { pageId: {{ $page->id }} })"
                class="shrink-0 text-[10px] rounded px-0.5 whitespace-nowrap"
                :class="$store.pageComments?.unread({{ $page->id }}) ? 'bg-amber-200 text-amber-900 ring-1 ring-amber-500 font-semibold' : 'opacity-60 hover:opacity-100'"
                :title="$store.pageComments?.unread({{ $page->id }}) ? $store.pageComments.unread({{ $page->id }}) + ($store.pageComments.unread({{ $page->id }}) === 1 ? ' commento non letto' : ' commenti non letti') : ($store.pageComments?.total({{ $page->id }}) ? 'Commenti' : 'Commenta questa pagina')"
                data-testid="page-comments-button"
            >💬<span x-text="$store.pageComments?.total({{ $page->id }}) || ''"></span></button>

            @if ($editsTimone)
                <button
                    type="button"
                    wire:click="togglePageLock({{ $page->id }})"
                    x-on:click.stop
                    class="shrink-0 text-[10px] opacity-60 hover:opacity-100"
                    title="{{ $locked ? 'Sblocca pagina' : 'Blocca pagina (impedisce spostamento, eliminazione e modifiche)' }}"
                >{{ $locked ? '🔒' : '🔓' }}</button>
            @endif

            @if ($group)
                <button
                    type="button"
                    wire:click="unlinkGroup({{ $page->id }})"
                    x-on:click.stop
                    @unless ($locked) title="Pagina collegata — clic per scollegare l'intero gruppo" @else title="Pagina collegata (bloccata)" @endif
                    class="shrink-0 text-[9px] font-semibold rounded px-1 border {{ $group->badgeClasses() }}"
                    @if ($locked || ! $editsTimone) disabled @endif
                >🔗</button>
            @endif
        </div>
    </div>

    {{--
        Disposizione dei contenuti: dalla revisione 2, Fase 2.3 (2026-10-27)
        ogni contenuto ha un rettangolo sulla griglia 6×6 della pagina
        (Page::layout() → App\Support\PageGrid\PageGridPlanner; prima
        impilamento calcolato della Fase 7a): 1/2 verticale 3×6, 1/3
        orizzontale 6×2, 1/4 3×3…, spostabile con le frecce del menu «⋯». Lo
        spazio libero è tratteggiato, un 3/4 (forma a L) riempie le celle
        lasciate dagli altri. Ogni blocco mostra l'anteprima del PROPRIO
        file (prima un'unica immagine copriva tutta la card) e, sopra, la
        riga del contenuto in modalità compatta. Senza contenuti resta la
        scritta di sempre; una pagina legacy con solo un PageFile diretto
        (Punto 8 non migrato) mostra ancora la sua miniatura a tutta card.
    --}}
    @php
        $layout = $page->contents->isNotEmpty() ? $page->layout() : null;
    @endphp
    <div class="relative flex-1 mt-1 min-h-0 rounded overflow-hidden" data-testid="page-layout">
        @if ($layout)
            @foreach ($layout['blocks'] as $block)
                @php
                    $content = $block['content'];
                    $rect = $block['rect'];
                    $blockFile = $block['file'];
                    $blockTone = $content->type->value === 'articolo'
                        ? 'bg-sky-50 text-sky-950 dark:bg-sky-950/60 dark:text-sky-50'
                        : 'bg-amber-50 text-amber-950 dark:bg-amber-950/60 dark:text-amber-50';
                    $blockBorder = match (true) {
                        $block['kind'] === 'overflow' => 'border-2 border-dashed border-red-600',
                        $content->isPlaceholder() => 'border-dashed border-amber-500',
                        default => 'border-white/80 dark:border-gray-900/80',
                    };
                @endphp
                {{-- Revisione 2, Fase 2.3: forma a L (3/4, 7/12, 11/12) — i pezzi
                     oltre a quello principale, stesso colore, senza testo. --}}
                @if ($block['kind'] === 'fluid')
                    @foreach ($block['pieces'] as $piece)
                        @continue($piece === $rect)
                        <div
                            wire:key="page-{{ $page->id }}-block-{{ $content->id }}-piece-{{ $loop->index }}"
                            class="absolute border {{ $blockBorder }} {{ $blockTone }}"
                            style="left: {{ $piece['x'] }}%; top: {{ $piece['y'] }}%; width: {{ $piece['w'] }}%; height: {{ $piece['h'] }}%;"
                            data-testid="layout-piece"
                            aria-hidden="true"
                        ></div>
                    @endforeach
                @endif
                <div
                    wire:key="page-{{ $page->id }}-block-{{ $content->id }}"
                    class="group/block absolute overflow-hidden border {{ $blockBorder }} {{ $block['imageUrl'] ? 'bg-gray-200 dark:bg-gray-700' : $blockTone }}"
                    style="left: {{ $rect['x'] }}%; top: {{ $rect['y'] }}%; width: {{ $rect['w'] }}%; height: {{ $rect['h'] }}%;"
                    title="{{ $content->displayLabel() }} — {{ \App\Support\Twelfths::pagesLabel((int) $content->pivot->occupied_twelfths) }}"
                    data-testid="layout-block"
                    data-content-id="{{ $content->id }}"
                    data-twelfths="{{ (int) $content->pivot->occupied_twelfths }}"
                    tabindex="0"
                    x-data="{ menuOpen: false }"
                    x-on:keydown.arrow-left.stop
                    x-on:keydown.arrow-right.stop
                >
                    @if ($block['imageUrl'])
                        {{-- Revisione 2, Fase 2.1 (2026-10-27): l'anteprima non è più
                             un link. Il click su un contenuto risale alla card e
                             seleziona la pagina come un click sulla pagina
                             (Grid::clickPage()); il file si apre dall'occhio 👁 nel
                             menu «⋯» (partials/content-controls). --}}
                        {{-- Fase 2.4: ritaglio in base al rettangolo — file a pagina
                             intera → solo la sua porzione, file nel formato → intero
                             (Page::layout() → App\Support\PageGrid\ThumbnailFit). --}}
                        <div class="absolute inset-0 overflow-hidden" data-fit="{{ $block['fit']->value }}">
                            <img src="{{ $block['imageUrl'] }}" alt="" style="{{ $block['imageStyle'] }}" />
                        </div>
                        <div class="absolute inset-x-0 bottom-0 max-h-full overflow-hidden bg-black/60 px-0.5 py-px">
                            @include('livewire.timone.partials.page-content-entry', ['variant' => 'overlay', 'compact' => true])
                        </div>
                    @else
                        <div class="absolute inset-0 overflow-hidden p-0.5 flex flex-col justify-center">
                            @include('livewire.timone.partials.page-content-entry', ['variant' => 'card', 'compact' => true])
                        </div>
                    @endif

                    {{-- Revisione 2, Fase 1, bug 3 (2026-10-27): con due contenuti
                         frazionari sulla stessa pagina (1/2+1/2, 1/4×4…) i comandi
                         dentro il blocco venivano tagliati dall'overflow-hidden e la
                         select dello spazio non era cliccabile. Ora ogni blocco ha
                         sempre, nell'angolo, un bottone «⋯» abbastanza grande da
                         cliccare a ogni zoom; il menu si apre nel <body>
                         (@teleport) agganciato al bottone (x-anchor, plugin già
                         incluso in Livewire), sopra header e pagine vicine. wire:*
                         e $wire funzionano anche lì: Livewire risale al componente
                         attraverso il teleport. --}}
                    {{-- I click e i tasti qui dentro non risalgono alla card
                         (.stop: selezione pagina, frecce di spostamento), quindi
                         click.outside/Esc "globali" non bastano: un evento
                         content-menu-opened chiude gli altri menu aperti ed Esc è
                         gestito su bottone e menu stessi. --}}
                    @unless ($locked)
                        <button
                            type="button"
                            x-ref="menuTrigger"
                            x-on:click.stop="menuOpen = ! menuOpen; if (menuOpen) $dispatch('content-menu-opened', '{{ $page->id }}-{{ $content->id }}')"
                            x-on:content-menu-opened.window="if ($event.detail !== '{{ $page->id }}-{{ $content->id }}') menuOpen = false"
                            x-on:keydown.escape="menuOpen = false"
                            x-on:keydown.stop
                            :aria-expanded="menuOpen"
                            aria-haspopup="menu"
                            aria-label="Comandi di {{ $content->displayLabel() }}"
                            title="Comandi del contenuto"
                            class="absolute top-0 right-0 z-10 flex h-4 min-w-[1rem] items-center justify-center rounded-bl bg-white/90 px-0.5 text-[11px] font-bold leading-none text-gray-800 shadow-sm hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-800/90 dark:text-gray-100"
                            data-testid="content-menu-button"
                        >⋯</button>
                        @teleport('body')
                            <div>
                                <template x-if="menuOpen">
                                    <div
                                        x-anchor.bottom-end.offset.2="$refs.menuTrigger"
                                        x-on:click.outside="if (! $refs.menuTrigger.contains($event.target)) menuOpen = false"
                                        x-on:keydown.escape="menuOpen = false; $refs.menuTrigger.focus()"
                                        x-on:keydown.stop
                                        role="menu"
                                        class="z-[60] w-56 rounded-lg border border-gray-200 bg-white p-1 text-gray-800 shadow-xl dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                        data-testid="content-menu"
                                    >
                                        <p class="truncate px-2 pt-1 pb-1.5 text-xs font-semibold border-b border-gray-100 dark:border-gray-700 mb-1">{{ $content->type->value === 'articolo' ? '📄' : '📢' }} {{ $content->displayLabel() }}</p>
                                        @include('livewire.timone.partials.content-controls', ['variant' => 'popover'])
                                    </div>
                                </template>
                            </div>
                        @endteleport
                    @endunless
                </div>
            @endforeach

            {{-- Spazio libero: celle non occupate, in pochi rettangoli. --}}
            @foreach ($layout['free'] as $free)
                <div
                    class="absolute border border-dashed border-gray-400/80 dark:border-gray-500/80 flex items-center justify-center text-[9px] text-gray-500 dark:text-gray-400 pointer-events-none"
                    style="left: {{ $free['x'] }}%; top: {{ $free['y'] }}%; width: {{ $free['w'] }}%; height: {{ $free['h'] }}%;"
                    data-testid="layout-free"
                    aria-hidden="true"
                >{{ $loop->first ? 'libero' : '' }}</div>
            @endforeach
        @elseif ($previewImageUrl)
            <a
                href="{{ $fileUrl }}"
                target="_blank"
                rel="noopener"
                x-on:click.stop
                class="absolute inset-0 block"
                title="{{ $previewFile->original_name }} — apri in una nuova scheda{{ $previewFile->pdf_page_number > 1 ? ' (pagina '.$previewFile->pdf_page_number.')' : '' }}"
            >
                <img src="{{ $previewImageUrl }}" alt="" class="w-full h-full object-cover" />
            </a>
        @else
            <div class="h-full flex flex-col justify-center">
                <p class="italic opacity-60 text-center leading-tight">
                    {{ $page->content_type->value === 'bianca' ? 'Pagina bianca' : 'Da assegnare' }}
                </p>
            </div>
        @endif
    </div>

    {{-- Punto 4 del prompt (2026-09-02): rimosso il caricamento diretto
         su pagina (input file + bottoni 📤 di upload/sostituzione) — il
         PDF si carica ora solo su un contenuto (bottone "📎 Carica
         materiale" sotto, apre App\Livewire\Timone\ContentCreate ancorato
         a questa pagina). L'anteprima/gli avvisi restano identici nella
         forma, solo la fonte dati cambia ($previewFile invece di
         $latestFile, vedi @php sopra). --}}
    {{-- Fase 3: barra di riempimento sul bordo sinistro + etichetta
         "Libero: 1/3"/"Piena" (partials/page-fill). --}}
    <div class="flex items-center justify-between gap-1 mt-1 text-[10px]">
        @include('livewire.timone.partials.page-fill', ['variant' => 'card'])
    </div>

    <div class="flex items-center justify-between gap-1 mt-1 text-[10px] opacity-70">
        @if (! $previewFile)
            @unless ($locked)
                <button
                    type="button"
                    x-on:click.stop="$dispatch('open-content-create-for-page', { pageId: {{ $page->id }} })"
                    class="opacity-70 hover:opacity-100"
                    title="Carica materiale (PDF o immagine) su questa pagina"
                >📎 Carica materiale</button>
            @endunless
        @elseif ($previewFile->thumbnail_status === \App\Enums\ThumbnailStatus::Ready)
            {{-- La miniatura vera e propria è sopra, come elemento visivo
                 principale della card — qui non resta più nulla da
                 mostrare per il file in sé (nessun modo di "sostituirlo"
                 rapidamente: bisogna passare da un nuovo contenuto, non
                 richiesto esplicitamente dal prompt per questo caso). --}}
        @elseif (in_array($previewFile->thumbnail_status, [\App\Enums\ThumbnailStatus::Pending, \App\Enums\ThumbnailStatus::Processing]))
            {{-- Tempo REALMENTE trascorso (non un'animazione) + stima
                 individuale se disponibile — sopravvive a un refresh
                 perché ricalcolato da $previewFile->created_at, non da uno
                 stato Livewire effimero (vedi HANDOFF.md 2026-07-31). --}}
            <span title="In coda da {{ $thumbnailEstimate['elapsedSeconds'] }}s">
                ⏳ PDF
                @if ($thumbnailEstimate['remainingSeconds'] !== null)
                    (~{{ $thumbnailEstimate['remainingSeconds'] }}s)
                @endif
            </span>
        @else
            <span title="Generazione anteprima fallita">⚠️ PDF</span>
        @endif

        {{-- §2.3: avviso "formato non conforme" — solo un avviso, mai un
             blocco, con la possibilità di accettarlo esplicitamente. Non
             mostrato per lo stato "non verificabile" (silenzioso, per non
             affollare una card già molto compatta con un avviso che non
             richiede nessuna azione dall'utente). Il bottone "accetta"
             resta disponibile solo per il nuovo percorso (ContentFile):
             per le pagine legacy con un PageFile diretto non ancora
             migrate (Punto 8) l'avviso resta visibile ma senza azione —
             tutta la UI di gestione diretta del PageFile è stata rimossa
             con questo punto, non solo il caricamento. --}}
        @if ($previewFile && $previewFile->hasUnresolvedFormatMismatch())
            <span
                class="text-red-600 dark:text-red-400"
                title="Formato non conforme: ricevuto {{ $previewFile->measured_width_mm }}×{{ $previewFile->measured_height_mm }}mm"
            >⚠️ formato</span>
            @if ($interactive && ! $previewIsPageFile)
                <button
                    type="button"
                    wire:click="confirmFormatOverride({{ $previewFile->id }})"
                    x-on:click.stop
                    class="text-red-600 dark:text-red-400 underline decoration-dotted"
                    title="Accetta comunque questo formato (caso limite legittimo)"
                >accetta</button>
            @endif
        @endif

        @if ($previewIsPageFile)
            <button
                type="button"
                x-on:click.stop="$dispatch('open-modal', 'page-file-history'); $dispatch('show-file-history', { pageId: {{ $page->id }} })"
                class="opacity-70 hover:opacity-100"
                title="Storico PDF caricati (pagina non ancora migrata al nuovo percorso)"
            >🕐</button>
        @endif

        @if ($page->notes)
            <span title="{{ $page->notes }}">📝</span>
        @endif
    </div>
</div>
