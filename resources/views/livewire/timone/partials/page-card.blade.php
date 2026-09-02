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
    // Etichetta mostrata (copertina separata dal conteggio pagine): il
    // fallback su $page->position copre solo il caso teorico di un
    // include senza 'pageNumber' esplicito (oggi non accade, unico
    // chiamante è grid.blade.php, che lo passa sempre — vedi
    // App\Support\PageNumberer per il calcolo reale).
    $pageNumber = $pageNumber ?? $page->position;
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
    $thumbnailRouteUrl = $previewFile
        ? ($previewIsPageFile ? route('page-files.thumbnail', $previewFile) : route('content-files.thumbnail', $previewFile))
        : null;
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
@endphp
<div
    wire:key="page-{{ $page->id }}"
    data-page-id="{{ $page->id }}"
    tabindex="0"
    @unless ($locked)
        @keydown.arrow-left.prevent="$wire.movePage({{ $page->id }}, {{ $page->position - 1 }})"
        @keydown.arrow-right.prevent="$wire.movePage({{ $page->id }}, {{ $page->position + 1 }})"
    @endunless
    @if ($interactive)
        x-on:dragover.prevent
        x-on:drop.prevent="const cid = $event.dataTransfer.getData('text/content-id'); if (cid) { $wire.assignContent(parseInt(cid), {{ $page->id }}); }"
    @endif
    @if ($swapMode && ! $locked)
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
    class="relative flex flex-col aspect-[3/4] rounded-lg border-4 p-2 text-xs overflow-hidden focus:outline-none focus:ring-2 focus:ring-indigo-500 {{ $page->content_type->colorClasses() }} {{ $page->status->borderClasses() }} {{ $locked ? 'opacity-70' : '' }} {{ $swapMode && ! $locked ? 'cursor-pointer' : '' }} {{ $ringClasses }} {{ $extraClasses }}"
>
    <template x-if="$store.pagePresence.editorFor({{ $page->id }})">
        <span
            class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-emerald-500 text-white text-[8px] flex items-center justify-center ring-2 ring-white dark:ring-gray-800 uppercase animate-pulse z-10"
            x-text="$store.pagePresence.editorFor({{ $page->id }}).name.substring(0, 2)"
            :title="$store.pagePresence.editorFor({{ $page->id }}).name + ' sta modificando questa pagina'"
        ></span>
    </template>

    {{-- Fase 3.1: promemoria compatto, gestione vera nel pannello "Copertina & battente" a inizio pagina — stesso principio del badge DX/SX in vista lista. --}}
    @if ($page->role->isCoverSection())
        <span
            class="absolute -top-1.5 -left-1.5 text-[9px] leading-none rounded-full bg-indigo-600 text-white px-1 py-0.5 z-10"
            title="{{ $page->role->label() }} — gestita dal pannello Copertina &amp; battente"
        >📘</span>
    @endif

    <div class="flex items-center justify-between gap-1">
        <div class="flex items-center gap-1">
            @if ($selectionMode ?? false)
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
                    x-on:focus="$store.pagePresence.startEditing({{ $page->id }})"
                    x-on:blur="$store.pagePresence.stopEditing({{ $page->id }})"
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
        @if ($previewFile && $previewFile->thumbnail_status === \App\Enums\ThumbnailStatus::Ready)
            <a
                href="{{ $fileUrl }}"
                target="_blank"
                rel="noopener"
                x-on:click.stop
                class="absolute inset-0 block"
                title="{{ $previewFile->original_name }} — apri in una nuova scheda{{ $previewFile->pdf_page_number > 1 ? ' (pagina '.$previewFile->pdf_page_number.')' : '' }}"
            >
                <img
                    src="{{ $thumbnailRouteUrl }}"
                    alt=""
                    class="w-full h-full object-cover"
                />
            </a>

            <div class="absolute inset-x-0 bottom-0 max-h-[60%] overflow-y-auto bg-black/60 px-1 py-0.5 space-y-0.5">
                @forelse ($page->contents as $content)
                    <div wire:key="page-{{ $page->id }}-content-{{ $content->id }}" class="flex items-start gap-1 leading-tight text-white">
                        <span class="break-words min-w-0 flex-1">
                            {{ $content->type->value === 'articolo' ? '📄' : '📢' }} {{ $content->displayLabel() }}
                            @if ($content->pages->count() > 1)
                                <span class="opacity-70" title="Contenuto presente su {{ $content->pages->count() }} pagine">×{{ $content->pages->count() }}</span>
                            @endif
                        </span>
                        @if ($locked)
                            <span class="shrink-0 text-[10px] opacity-70">{{ $content->pivot->occupied_percentage }}%</span>
                        @else
                            <input
                                type="number"
                                min="0.1"
                                max="100"
                                step="0.1"
                                value="{{ $content->pivot->occupied_percentage }}"
                                x-on:keydown.stop
                                x-on:click.stop
                                x-on:focus="$store.pagePresence.startEditing({{ $page->id }})"
                                x-on:blur="$store.pagePresence.stopEditing({{ $page->id }})"
                                wire:change="updateContentPercentage({{ $page->id }}, {{ $content->id }}, $event.target.value)"
                                class="w-9 shrink-0 text-[10px] rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-0.5 py-0"
                            />
                            <button
                                type="button"
                                x-on:click.stop="const pos = prompt('Estendi anche alla pagina numero:'); if (pos) $wire.extendToPage({{ $content->id }}, parseInt(pos))"
                                class="shrink-0 text-[10px] text-white/80 hover:text-white"
                                title="Estendi questo contenuto a un'altra pagina"
                            >↗</button>
                            <button
                                type="button"
                                x-on:click.stop="$wire.unassignContent({{ $content->id }}, {{ $page->id }})"
                                class="shrink-0 text-[10px] text-white/80 hover:text-red-400"
                                title="Rimuovi"
                            >✕</button>
                        @endif
                    </div>
                @empty
                    <p class="italic text-white/70 text-center leading-tight">
                        {{ $page->content_type->value === 'bianca' ? 'Pagina bianca' : 'Da assegnare' }}
                    </p>
                @endforelse
            </div>
        @else
            <div class="h-full flex flex-col justify-center gap-1">
                @forelse ($page->contents as $content)
                    <div wire:key="page-{{ $page->id }}-content-{{ $content->id }}" class="flex items-start gap-1 leading-tight">
                        <span class="break-words min-w-0 flex-1">
                            {{ $content->type->value === 'articolo' ? '📄' : '📢' }} {{ $content->displayLabel() }}
                            @if ($content->pages->count() > 1)
                                <span class="opacity-60" title="Contenuto presente su {{ $content->pages->count() }} pagine">×{{ $content->pages->count() }}</span>
                            @endif
                        </span>
                        @if ($locked)
                            <span class="shrink-0 text-[10px] opacity-60">{{ $content->pivot->occupied_percentage }}%</span>
                        @else
                            <input
                                type="number"
                                min="0.1"
                                max="100"
                                step="0.1"
                                value="{{ $content->pivot->occupied_percentage }}"
                                x-on:keydown.stop
                                x-on:click.stop
                                x-on:focus="$store.pagePresence.startEditing({{ $page->id }})"
                                x-on:blur="$store.pagePresence.stopEditing({{ $page->id }})"
                                wire:change="updateContentPercentage({{ $page->id }}, {{ $content->id }}, $event.target.value)"
                                class="w-9 shrink-0 text-[10px] rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-0.5 py-0"
                            />
                            <button
                                type="button"
                                x-on:click.stop="const pos = prompt('Estendi anche alla pagina numero:'); if (pos) $wire.extendToPage({{ $content->id }}, parseInt(pos))"
                                class="shrink-0 text-[10px] opacity-60 hover:opacity-100"
                                title="Estendi questo contenuto a un'altra pagina"
                            >↗</button>
                            <button
                                type="button"
                                x-on:click.stop="$wire.unassignContent({{ $content->id }}, {{ $page->id }})"
                                class="shrink-0 text-[10px] opacity-60 hover:opacity-100 hover:text-red-600"
                                title="Rimuovi"
                            >✕</button>
                        @endif
                    </div>
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
