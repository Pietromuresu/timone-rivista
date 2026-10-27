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
    @unless ($locked || $isCover)
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
            @if ($interactive)
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

            <button
                type="button"
                wire:click="togglePageLock({{ $page->id }})"
                x-on:click.stop
                class="shrink-0 text-[10px] opacity-60 hover:opacity-100"
                title="{{ $locked ? 'Sblocca pagina' : 'Blocca pagina (impedisce spostamento, eliminazione e modifiche)' }}"
            >{{ $locked ? '🔒' : '🔓' }}</button>

            @if ($group)
                <button
                    type="button"
                    wire:click="unlinkGroup({{ $page->id }})"
                    x-on:click.stop
                    @unless ($locked) title="Pagina collegata — clic per scollegare l'intero gruppo" @else title="Pagina collegata (bloccata)" @endif
                    class="shrink-0 text-[9px] font-semibold rounded px-1 border {{ $group->badgeClasses() }}"
                    @if ($locked) disabled @endif
                >🔗</button>
            @endif
        </div>
    </div>

    {{--
        Segnalato dall'utente dopo un test dal vivo (2026-07-31): la
        miniatura PDF pronta deve essere l'elemento visivo principale della
        card, non un'icona minuscola in fondo insieme a 📤/🕐/📝 — prima
        stava sempre nella riga di icone sotto, 24×32px, indipendentemente
        da quanto contenuto/materiale ci fosse. Ora, quando la miniatura è
        pronta, riempie l'area centrale della card (al posto dell'elenco
        contenuti, che diventa una didascalia sovrapposta in basso — resta
        interamente funzionale: percentuale modificabile, "↗"/"✕" invariati)
        — quando non c'è ancora un'anteprima pronta (nessun file, in
        elaborazione, fallita), l'area centrale resta l'elenco contenuti
        testuale di sempre.
    --}}
    <div class="relative flex-1 mt-1 min-h-0 rounded overflow-hidden">
        @if ($previewImageUrl)
            <a
                href="{{ $fileUrl }}"
                target="_blank"
                rel="noopener"
                x-on:click.stop
                class="absolute inset-0 block"
                title="{{ $previewFile->original_name }} — apri in una nuova scheda{{ $previewFile->pdf_page_number > 1 ? ' (pagina '.$previewFile->pdf_page_number.')' : '' }}"
            >
                <img
                    src="{{ $previewImageUrl }}"
                    alt=""
                    class="w-full h-full object-cover"
                />
            </a>

            <div class="absolute inset-x-0 bottom-0 max-h-[60%] overflow-y-auto bg-black/60 px-1 py-0.5 space-y-0.5">
                @forelse ($page->contents as $content)
                    @include('livewire.timone.partials.page-content-entry', ['variant' => 'overlay'])
                @empty
                    <p class="italic text-white/70 text-center leading-tight">
                        {{ $page->content_type->value === 'bianca' ? 'Pagina bianca' : 'Da assegnare' }}
                    </p>
                @endforelse
            </div>
        @else
            <div class="h-full flex flex-col justify-center gap-1">
                @forelse ($page->contents as $content)
                    @include('livewire.timone.partials.page-content-entry', ['variant' => 'card'])
                @empty
                    <p class="italic opacity-60 text-center leading-tight">
                        {{ $page->content_type->value === 'bianca' ? 'Pagina bianca' : 'Da assegnare' }}
                    </p>
                @endforelse
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
