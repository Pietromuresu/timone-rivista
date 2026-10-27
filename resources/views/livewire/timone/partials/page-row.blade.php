@php
    $isCover = $page->isCover();
    // Punto 4 del prompt (2026-09-02): l'anteprima ora viene dal contenuto
    // assegnato alla pagina (Page::previewFile()) — vedi la nota completa
    // in page-card.blade.php, identica qui.
    $previewFile = $page->previewFile();
    $previewIsPageFile = $previewFile instanceof \App\Models\PageFile;
    $dropEnabled = $dropEnabled ?? false;
    $locked = $page->isLocked();
    $interactive = $dropEnabled && ! $locked;
    // §2.2: stesso frammento #page=N di page-card.blade.php — vedi lì per
    // il perché (nessun pdf.js in questo progetto).
    $fileUrl = $previewFile
        ? ($previewIsPageFile ? route('page-files.show', $previewFile) : route('content-files.show', $previewFile)).($previewFile->pdf_page_number > 1 ? '#page='.$previewFile->pdf_page_number : '')
        : null;
    // URL dell'anteprima pronta (Page::previewImageUrl()), come nella card.
    $previewImageUrl = $page->previewImageUrl();
    // Stima PER QUESTA card (2026-07-31, vedi HANDOFF.md) — dati reali
    // (tempo di orologio trascorso, media osservata su questo numero),
    // mai un placeholder. $avgThumbnailSeconds arriva da Grid::render().
    $thumbnailEstimate = $previewFile && in_array($previewFile->thumbnail_status, [\App\Enums\ThumbnailStatus::Pending, \App\Enums\ThumbnailStatus::Processing], true)
        ? \App\Support\ThumbnailProgressEstimator::forFile($previewFile, $avgThumbnailSeconds ?? null)
        : null;
    // Stessa convenzione già usata da App\Support\PageSpreadBuilder per la
    // vista doppia pagina (pagina 1 sempre a destra, poi alternanza): non
    // va reinventata qui, solo espressa come etichetta testuale per chi
    // scorre la lista senza vedere le aperture affiancate.
    $pageSide = $isCover ? null : ($page->position % 2 === 1 ? 'DX' : 'SX');
    // Fase 2.3: pagine "collegate" in un gruppo — vedi App\Models\PageGroup,
    // $page->group già eager-loaded da Grid::render().
    $group = $page->group;
    // Fase 2 (2026-10-26): una copertina (I–IV, bandella) usa la stessa
    // riga ma è fuori dalla sequenza: niente riordino/tastiera/selezione,
    // etichetta "II di copertina" al posto del numero. Vedi page-card.
    $pageNumber = $pageNumber ?? ($isCover ? $page->cover_slot->label() : $page->position);
    // Evidenziazione selezione multipla — stessa logica/priorità di
    // page-card.blade.php (vedi lì per il perché), solo classi diverse
    // per restare coerenti con lo stile "riga" invece che "card".
    $isMultiSelected = ($selectionMode ?? false) && in_array($page->id, $selectedPageIds ?? [], true);
    $selectionRowClasses = match (true) {
        $swapSelectedPageId === $page->id => 'ring-2 ring-inset ring-indigo-500 bg-indigo-50 dark:bg-indigo-900/30',
        $isMultiSelected => 'ring-2 ring-inset ring-emerald-500 bg-emerald-50 dark:bg-emerald-900/20',
        default => '',
    };
    // Bug 4 (2026-10-12): stesso motivo di page-card.blade.php — senza,
    // il focus (ultima riga cliccata) sostituiva il ring verde con quello
    // indigo del focus.
    $focusClasses = match (true) {
        $swapSelectedPageId === $page->id => 'focus:ring-indigo-500',
        $isMultiSelected => 'focus:ring-emerald-500',
        default => 'focus:ring-indigo-500',
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
<li
    wire:key="page-{{ $page->id }}"
    data-page-id="{{ $page->id }}"
    @if ($page->page_group_id) data-group-id="{{ $page->page_group_id }}" @endif
    @if ($isCover) data-cover-slot="{{ $page->cover_slot->value }}" @endif
    tabindex="0"
    @unless ($locked || $isCover)
        @keydown.arrow-up.prevent="$animatedMove('nudgePage', {{ $page->id }}, -1)"
        @keydown.arrow-down.prevent="$animatedMove('nudgePage', {{ $page->id }}, 1)"
    @endunless
    @if ($interactive)
        x-on:dragover.prevent
        x-on:drop.prevent="const coverId = $event.dataTransfer.getData('text/cover-id'); const cid = $event.dataTransfer.getData('text/content-id'); if (coverId && {{ $isCover ? 'true' : 'false' }}) { $wire.swapCovers(parseInt(coverId), {{ $page->id }}); } else if (cid) { $wire.assignContent(parseInt(cid), {{ $page->id }}); }"
    @endif
    @if ($isCover)
        {{-- Copertine: nessuna selezione multipla né modalità scambio. --}}
    @elseif ($swapMode && ! $locked)
        x-on:click="$wire.selectForSwap({{ $page->id }})"
    @elseif (! $swapMode)
        {{-- Shortcut da tastiera (2026-09-02, punto 2 del prompt) — stesso
             wiring di page-card.blade.php, vedi lì per la spiegazione
             completa: click semplice seleziona solo questa riga (no-op
             fuori dalla selezione multipla), Ctrl/Cmd+click la
             aggiunge/rimuove dalla selezione. --}}
        x-on:click="$event.ctrlKey || $event.metaKey ? $wire.ctrlClickPage({{ $page->id }}) : $wire.clickPage({{ $page->id }})"
    @endif
    {{--
        Fase 4 (§4): bordo sinistro spesso per lo stato, stesso principio
        della card in griglia (status->borderClasses()) — il tipo pagina
        resta sul badge già esistente qui sotto (non anche sullo sfondo
        dell'intera riga come in griglia: uno sfondo colorato competerebbe
        con `bg-indigo-50`/hover della selezione scambio già presenti su
        questo elemento, con un ordine di vittoria CSS non garantito tra
        due classi `bg-*` semplici — scelta pragmatica di restare con un
        solo canale cromatico aggiuntivo qui, la lista è già pensata per
        scorrere veloce, non per un colpo d'occhio sul tipo pagina).
    --}}
    class="flex items-center gap-4 px-4 py-3 border-l-4 {{ $isCover ? 'bg-indigo-50/60 dark:bg-indigo-950/30' : '' }} {{ $page->status->borderClasses() }} hover:bg-gray-50 dark:hover:bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-inset {{ $focusClasses }} {{ $locked ? 'opacity-70' : '' }} {{ $swapMode && ! $locked ? 'cursor-pointer' : '' }} {{ $selectionRowClasses }} {{ $highlightClasses }}"
    @if ($isHighlighted) data-highlighted="1" @endif
>
    @if (($selectionMode ?? false) && ! $isCover)
        <input
            type="checkbox"
            x-on:click.stop
            wire:click="togglePageSelection({{ $page->id }})"
            @checked(in_array($page->id, $selectedPageIds ?? [], true))
            class="shrink-0 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
        />
    @endif

    @if ($locked)
        <span class="w-8 shrink-0 text-right font-semibold text-gray-700 dark:text-gray-200" title="{{ $page->lockedBy ? 'Bloccata da '.$page->lockedBy->name : 'Pagina bloccata' }}">
            🔒 {{ $pageNumber }}
        </span>
    @elseif ($isCover)
        {{-- Trascinabile solo su un'altra copertina (scambio di slot):
             drag nativo, non Sortable — le copertine non sono nel
             contenitore delle pagine interne. --}}
        <span
            draggable="true"
            x-on:dragstart.stop="$event.dataTransfer.setData('text/cover-id', '{{ $page->id }}'); $event.dataTransfer.effectAllowed = 'move'"
            class="shrink-0 font-semibold text-indigo-800 dark:text-indigo-300 cursor-grab whitespace-nowrap"
            title="Trascina su un'altra copertina per scambiarle"
        >📘 {{ $pageNumber }}</span>
    @else
        <span class="drag-handle w-8 shrink-0 text-right font-semibold text-gray-700 dark:text-gray-200 cursor-grab" title="Trascina per riordinare">
            {{ $pageNumber }}
        </span>
    @endif

    <template x-if="$store.pagePresence?.editorFor({{ $page->id }})">
        <span
            class="w-5 h-5 rounded-full bg-emerald-500 text-white text-[9px] flex items-center justify-center uppercase shrink-0 animate-pulse"
            x-text="$store.pagePresence?.editorFor({{ $page->id }})?.name.substring(0, 2)"
            :title="$store.pagePresence?.editorFor({{ $page->id }})?.name + ' sta modificando questa pagina'"
        ></span>
    </template>

    @if ($pageSide)
        <span
            class="w-7 shrink-0 text-center text-[10px] font-semibold rounded px-1 py-0.5 {{ $pageSide === 'DX' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300' }}"
            title="{{ $pageSide === 'DX' ? 'Pagina destra' : 'Pagina sinistra' }}"
        >{{ $pageSide }}</span>
    @endif

    @if ($group)
        <button
            type="button"
            wire:click="unlinkGroup({{ $page->id }})"
            x-on:click.stop
            @unless ($locked) title="Pagina collegata — clic per scollegare l'intero gruppo" @else title="Pagina collegata (bloccata)" @endif
            class="shrink-0 text-[10px] font-semibold rounded px-1 py-0.5 border {{ $group->badgeClasses() }}"
            @if ($locked) disabled @endif
        >🔗</button>
    @endif

    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium shrink-0 {{ $page->content_type->colorClasses() }}">
        {{ $page->content_type->label() }}
    </span>

    {{-- Fase 3: riempimento della pagina, stessa etichetta della card. --}}
    @include('livewire.timone.partials.page-fill', ['variant' => 'row'])

    <div class="flex-1 min-w-0 flex flex-wrap items-center gap-3">
        @forelse ($page->contents as $content)
            @include('livewire.timone.partials.page-content-entry', ['variant' => 'row'])
        @empty
            <span class="text-sm text-gray-400 italic">
                {{ $page->content_type->value === 'bianca' ? 'Pagina bianca' : 'Da assegnare' }}
            </span>
        @endforelse
    </div>

    {{-- Punto 4 del prompt (2026-09-02): rimosso il caricamento diretto su
         pagina — vedi la nota completa in page-card.blade.php, identica
         qui. --}}
    <span class="text-xs shrink-0 flex items-center gap-1">
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
            <a
                href="{{ $fileUrl }}"
                target="_blank"
                rel="noopener"
                x-on:click.stop
                class="flex items-center gap-1"
                title="{{ $previewFile->original_name }} — apri in una nuova scheda{{ $previewFile->pdf_page_number > 1 ? ' (pagina '.$previewFile->pdf_page_number.')' : '' }}"
            >
                <img
                    src="{{ $previewImageUrl }}"
                    alt=""
                    class="w-5 h-6 object-cover rounded border border-gray-300 dark:border-gray-600"
                />
            </a>
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
    </span>

    @if ($locked)
        <span class="rounded text-xs font-medium shrink-0 py-0.5 pl-2 pr-2 {{ $page->status->colorClasses() }}">
            {{ $page->status->label() }}
        </span>
    @else
        <select
            x-on:click.stop
            x-on:keydown.stop
            x-on:focus="$store.pagePresence?.startEditing({{ $page->id }})"
            x-on:blur="$store.pagePresence?.stopEditing({{ $page->id }})"
            wire:change="changePageStatus({{ $page->id }}, $event.target.value)"
            class="rounded text-xs font-medium shrink-0 border-none py-0.5 pl-2 pr-6 {{ $page->status->colorClasses() }}"
        >
            @foreach (\App\Enums\PageStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected($status === $page->status)>{{ $status->label() }}</option>
            @endforeach
        </select>
    @endif

    <button
        type="button"
        wire:click="togglePageLock({{ $page->id }})"
        x-on:click.stop
        class="shrink-0 text-xs opacity-60 hover:opacity-100"
        title="{{ $locked ? 'Sblocca pagina' : 'Blocca pagina (impedisce spostamento, eliminazione e modifiche)' }}"
    >{{ $locked ? '🔒' : '🔓' }}</button>
</li>
