@php
    $latestFile = $page->files->first();
    $dropEnabled = $dropEnabled ?? false;
    $locked = $page->isLocked();
    // dropEnabled riflette la modalità di vista (griglia/doppia sì, doppia
    // sola lettura mai più dopo questa sessione — vedi HANDOFF.md); $locked
    // riflette il blocco pagina (§6.6), ortogonale alla modalità di vista.
    // Solo la combinazione delle due decide se la pagina è davvero editabile.
    $interactive = $dropEnabled && ! $locked;
    // §2.2: apre il PDF alla pagina interna corretta con il frammento
    // nativo del browser #page=N (nessun pdf.js in questo progetto, vedi
    // HANDOFF.md) — solo se non è la prima pagina del file sorgente.
    $fileUrl = $latestFile ? route('page-files.show', $latestFile).($latestFile->pdf_page_number > 1 ? '#page='.$latestFile->pdf_page_number : '') : null;
    // Stima PER QUESTA card (2026-07-31, vedi HANDOFF.md) — dati reali
    // (tempo di orologio trascorso, media osservata su questo numero),
    // mai un placeholder. $avgThumbnailSeconds arriva da Grid::render().
    $thumbnailEstimate = $latestFile && in_array($latestFile->thumbnail_status, [\App\Enums\ThumbnailStatus::Pending, \App\Enums\ThumbnailStatus::Processing], true)
        ? \App\Support\ThumbnailProgressEstimator::forPageFile($latestFile, $avgThumbnailSeconds ?? null)
        : null;
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
    @endif
    {{--
        Fase 4 (§4): due canali cromatici distinti sulla stessa card, non in
        conflitto — lo sfondo (content_type->colorClasses()) mostra il tipo
        pagina a colpo d'occhio (pubblicità sempre riconoscibile), il bordo
        spesso (status->borderClasses()) mostra lo stato senza dover leggere
        l'etichetta piccola sul badge di stato (che resta comunque, vedi
        sotto — "affianca", non "sostituisce", per chi preferisce leggerla).
    --}}
    class="relative flex flex-col aspect-[3/4] rounded-lg border-4 p-2 text-xs overflow-hidden focus:outline-none focus:ring-2 focus:ring-indigo-500 {{ $page->content_type->colorClasses() }} {{ $page->status->borderClasses() }} {{ $locked ? 'opacity-70' : '' }} {{ $swapMode && ! $locked ? 'cursor-pointer' : '' }} {{ $swapSelectedPageId === $page->id ? 'ring-4 ring-indigo-500' : '' }}"
>
    <template x-if="$store.pagePresence.editorFor({{ $page->id }})">
        <span
            class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-emerald-500 text-white text-[8px] flex items-center justify-center ring-2 ring-white dark:ring-gray-800 uppercase animate-pulse z-10"
            x-text="$store.pagePresence.editorFor({{ $page->id }}).name.substring(0, 2)"
            :title="$store.pagePresence.editorFor({{ $page->id }}).name + ' sta modificando questa pagina'"
        ></span>
    </template>

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
                    🔒 {{ $page->position }}
                </span>
            @else
                <span class="drag-handle cursor-grab font-semibold text-sm select-none" title="Trascina per riordinare">
                    ⠿ {{ $page->position }}
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
        @if ($latestFile && $latestFile->thumbnail_status === \App\Enums\ThumbnailStatus::Ready)
            <a
                href="{{ $fileUrl }}"
                target="_blank"
                rel="noopener"
                x-on:click.stop
                class="absolute inset-0 block"
                title="{{ $latestFile->original_name }} — apri in una nuova scheda{{ $latestFile->pdf_page_number > 1 ? ' (pagina '.$latestFile->pdf_page_number.')' : '' }}"
            >
                <img
                    src="{{ route('page-files.thumbnail', $latestFile) }}"
                    alt=""
                    class="w-full h-full object-cover"
                />
            </a>

            <div class="absolute inset-x-0 bottom-0 max-h-[60%] overflow-y-auto bg-black/60 px-1 py-0.5 space-y-0.5">
                @forelse ($page->contents as $content)
                    <div wire:key="page-{{ $page->id }}-content-{{ $content->id }}" class="flex items-center gap-1 leading-tight text-white">
                        <span class="truncate flex-1">
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
                    <div wire:key="page-{{ $page->id }}-content-{{ $content->id }}" class="flex items-center gap-1 leading-tight">
                        <span class="truncate flex-1">
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

    <div
        x-data="{ uploading: false, progress: 0, examiningSeconds: 0, examiningTimer: null }"
        x-on:livewire-upload-start="uploading = true; progress = 0"
        x-on:livewire-upload-progress="progress = $event.detail.progress"
        x-on:livewire-upload-finish="
            uploading = false; examiningSeconds = 0;
            clearInterval(examiningTimer);
            examiningTimer = setInterval(() => examiningSeconds++, 1000);
        "
        x-on:livewire-upload-error="uploading = false; clearInterval(examiningTimer)"
        x-on:livewire-upload-cancel="uploading = false; clearInterval(examiningTimer)"
    >
    <div class="flex items-center justify-between gap-1 mt-1 text-[10px] opacity-70">
        <input
            type="file"
            id="page-file-input-{{ $page->id }}"
            wire:model="pendingUploads.{{ $page->id }}"
            accept="application/pdf"
            class="hidden"
        />

        @if (! $latestFile)
            @unless ($locked)
                <button
                    type="button"
                    x-on:click.stop="document.getElementById('page-file-input-{{ $page->id }}').click()"
                    wire:loading.attr="disabled"
                    wire:target="pendingUploads.{{ $page->id }}"
                    class="opacity-70 hover:opacity-100"
                    title="Carica PDF"
                >📤</button>
            @endunless
        @elseif ($latestFile->thumbnail_status === \App\Enums\ThumbnailStatus::Ready)
            {{-- La miniatura vera e propria è ora sopra, come elemento
                 visivo principale della card — qui resta solo un pulsante
                 per sostituirla con un nuovo caricamento. --}}
            @unless ($locked)
                <button
                    type="button"
                    x-on:click.stop="document.getElementById('page-file-input-{{ $page->id }}').click()"
                    class="opacity-70 hover:opacity-100"
                    title="Carica un nuovo PDF al posto di questo"
                >📤</button>
            @endunless
        @elseif (in_array($latestFile->thumbnail_status, [\App\Enums\ThumbnailStatus::Pending, \App\Enums\ThumbnailStatus::Processing]))
            {{-- Tempo REALMENTE trascorso (non un'animazione) + stima
                 individuale se disponibile — sopravvive a un refresh
                 perché ricalcolato da $latestFile->created_at, non da uno
                 stato Livewire effimero (vedi HANDOFF.md 2026-07-31). --}}
            <span title="In coda da {{ $thumbnailEstimate['elapsedSeconds'] }}s">
                ⏳ PDF
                @if ($thumbnailEstimate['remainingSeconds'] !== null)
                    (~{{ $thumbnailEstimate['remainingSeconds'] }}s)
                @endif
            </span>
        @elseif ($locked)
            <span title="Generazione anteprima fallita — sblocca la pagina per ricaricare il file">⚠️ PDF</span>
        @else
            <button
                type="button"
                x-on:click.stop="document.getElementById('page-file-input-{{ $page->id }}').click()"
                class="text-red-600"
                title="Generazione thumbnail fallita — clic per ricaricare"
            >⚠️ PDF</button>
        @endif

        {{-- §2.3: avviso "formato non conforme" — solo un avviso, mai un
             blocco, con la possibilità di accettarlo esplicitamente. Non
             mostrato per lo stato "non verificabile" (silenzioso, per non
             affollare una card già molto compatta con un avviso che non
             richiede nessuna azione dall'utente). --}}
        @if ($latestFile && $latestFile->hasUnresolvedFormatMismatch())
            <span
                class="text-red-600 dark:text-red-400"
                title="Formato non conforme: ricevuto {{ $latestFile->measured_width_mm }}×{{ $latestFile->measured_height_mm }}mm"
            >⚠️ formato</span>
            @if ($interactive)
                <button
                    type="button"
                    wire:click="confirmFormatOverride({{ $latestFile->id }})"
                    x-on:click.stop
                    class="text-red-600 dark:text-red-400 underline decoration-dotted"
                    title="Accetta comunque questo formato (caso limite legittimo)"
                >accetta</button>
            @endif
        @endif

        {{-- Bug scoperto il 2026-07-31: senza questo @error, un upload
             respinto da Livewire (es. PDF oltre i 32MB) falliva "in
             silenzio" lato UI — lo spinner si fermava ma l'utente non
             capiva perché, sembrava che l'upload si fosse "bloccato". --}}
        @error('pendingUploads.' . $page->id)
            <span class="text-red-600 dark:text-red-400" title="{{ $message }}">⚠️ upload fallito</span>
        @enderror

        @if ($latestFile)
            <button
                type="button"
                x-on:click.stop="$dispatch('open-modal', 'page-file-history'); $dispatch('show-file-history', { pageId: {{ $page->id }} })"
                class="opacity-70 hover:opacity-100"
                title="Storico PDF caricati"
            >🕐</button>
        @endif

        @if ($page->notes)
            <span title="{{ $page->notes }}">📝</span>
        @endif
    </div>

    {{-- Sostituisce il vecchio "caricamento..." in corsivo (troppo
         discreto — causa segnalata di un upload che "sembrava bloccato",
         vedi HANDOFF.md 2026-07-31). Durante il trasferimento: barra e
         percentuale REALI (evento nativo Livewire `livewire-upload-
         progress`, byte realmente inviati, non simulati). Durante l'esame
         del PDF lato server (conteggio pagine via Imagick, un'unica
         chiamata atomica senza un vero "avanzamento" misurabile): tempo
         REALMENTE trascorso invece di una barra finta. --}}
    <div x-show="uploading" class="mt-1">
        <div class="w-full h-1 bg-gray-200 dark:bg-gray-700 rounded overflow-hidden">
            <div class="h-1 bg-indigo-600" :style="`width: ${progress}%`"></div>
        </div>
        <p class="text-[10px] text-indigo-700 dark:text-indigo-400 mt-0.5">📤 Caricamento... <span x-text="progress"></span>%</p>
    </div>

    <div wire:loading wire:target="pendingUploads.{{ $page->id }}" class="mt-1 text-[10px] text-indigo-700 dark:text-indigo-400">
        🔎 Analisi del PDF in corso... (<span x-text="examiningSeconds"></span>s)
    </div>
    </div>
</div>
