@php
    $latestFile = $page->files->first();
    $dropEnabled = $dropEnabled ?? false;
    $locked = $page->isLocked();
    $interactive = $dropEnabled && ! $locked;
    // §2.2: stesso frammento #page=N di page-card.blade.php — vedi lì per
    // il perché (nessun pdf.js in questo progetto).
    $fileUrl = $latestFile ? route('page-files.show', $latestFile).($latestFile->pdf_page_number > 1 ? '#page='.$latestFile->pdf_page_number : '') : null;
    // Stima PER QUESTA card (2026-07-31, vedi HANDOFF.md) — dati reali
    // (tempo di orologio trascorso, media osservata su questo numero),
    // mai un placeholder. $avgThumbnailSeconds arriva da Grid::render().
    $thumbnailEstimate = $latestFile && in_array($latestFile->thumbnail_status, [\App\Enums\ThumbnailStatus::Pending, \App\Enums\ThumbnailStatus::Processing], true)
        ? \App\Support\ThumbnailProgressEstimator::forPageFile($latestFile, $avgThumbnailSeconds ?? null)
        : null;
@endphp
<li
    wire:key="page-{{ $page->id }}"
    data-page-id="{{ $page->id }}"
    tabindex="0"
    @unless ($locked)
        @keydown.arrow-up.prevent="$wire.movePage({{ $page->id }}, {{ $page->position - 1 }})"
        @keydown.arrow-down.prevent="$wire.movePage({{ $page->id }}, {{ $page->position + 1 }})"
    @endunless
    @if ($interactive)
        x-on:dragover.prevent
        x-on:drop.prevent="const cid = $event.dataTransfer.getData('text/content-id'); if (cid) { $wire.assignContent(parseInt(cid), {{ $page->id }}); }"
    @endif
    @if ($swapMode && ! $locked)
        x-on:click="$wire.selectForSwap({{ $page->id }})"
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
    class="flex items-center gap-4 px-4 py-3 border-l-4 {{ $page->status->borderClasses() }} hover:bg-gray-50 dark:hover:bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 {{ $locked ? 'opacity-70' : '' }} {{ $swapMode && ! $locked ? 'cursor-pointer' : '' }} {{ $swapSelectedPageId === $page->id ? 'ring-2 ring-inset ring-indigo-500 bg-indigo-50 dark:bg-indigo-900/30' : '' }}"
>
    @if ($selectionMode ?? false)
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
            🔒 {{ $page->position }}
        </span>
    @else
        <span class="drag-handle w-8 shrink-0 text-right font-semibold text-gray-700 dark:text-gray-200 cursor-grab" title="Trascina per riordinare">
            {{ $page->position }}
        </span>
    @endif

    <template x-if="$store.pagePresence.editorFor({{ $page->id }})">
        <span
            class="w-5 h-5 rounded-full bg-emerald-500 text-white text-[9px] flex items-center justify-center uppercase shrink-0 animate-pulse"
            x-text="$store.pagePresence.editorFor({{ $page->id }}).name.substring(0, 2)"
            :title="$store.pagePresence.editorFor({{ $page->id }}).name + ' sta modificando questa pagina'"
        ></span>
    </template>

    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium shrink-0 {{ $page->content_type->colorClasses() }}">
        {{ $page->content_type->label() }}
    </span>

    <div class="flex-1 min-w-0 flex flex-wrap items-center gap-3">
        @forelse ($page->contents as $content)
            <span wire:key="page-{{ $page->id }}-content-{{ $content->id }}" class="inline-flex items-center gap-1 text-sm text-gray-700 dark:text-gray-200">
                <span>{{ $content->type->value === 'articolo' ? '📄' : '📢' }}</span>
                {{ $content->displayLabel() }}
                @if ($content->pages->count() > 1)
                    <span class="opacity-60" title="Contenuto presente su {{ $content->pages->count() }} pagine">×{{ $content->pages->count() }}</span>
                @endif
                @if ($locked)
                    <span class="text-xs opacity-60">{{ $content->pivot->occupied_percentage }}%</span>
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
                        class="w-14 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-1 py-0"
                    />
                    <button
                        type="button"
                        x-on:click.stop="const pos = prompt('Estendi anche alla pagina numero:'); if (pos) $wire.extendToPage({{ $content->id }}, parseInt(pos))"
                        class="text-xs opacity-60 hover:opacity-100"
                        title="Estendi questo contenuto a un'altra pagina"
                    >↗</button>
                    <button
                        type="button"
                        x-on:click.stop="$wire.unassignContent({{ $content->id }}, {{ $page->id }})"
                        class="text-xs opacity-60 hover:opacity-100 hover:text-red-600"
                        title="Rimuovi"
                    >✕</button>
                @endif
            </span>
        @empty
            <span class="text-sm text-gray-400 italic">
                {{ $page->content_type->value === 'bianca' ? 'Pagina bianca' : 'Da assegnare' }}
            </span>
        @endforelse
    </div>

    <span
        class="text-xs shrink-0 flex items-center gap-1"
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
            <a
                href="{{ $fileUrl }}"
                target="_blank"
                rel="noopener"
                x-on:click.stop
                class="flex items-center gap-1"
                title="{{ $latestFile->original_name }} — apri in una nuova scheda{{ $latestFile->pdf_page_number > 1 ? ' (pagina '.$latestFile->pdf_page_number.')' : '' }}"
            >
                <img
                    src="{{ route('page-files.thumbnail', $latestFile) }}"
                    alt=""
                    class="w-5 h-6 object-cover rounded border border-gray-300 dark:border-gray-600"
                />
            </a>
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

        {{-- Sostituisce il vecchio "caricamento..." in corsivo (troppo
             discreto, facile da non notare — causa segnalata di un upload
             che "sembrava bloccato", vedi HANDOFF.md 2026-07-31): durante il
             trasferimento mostra la percentuale REALE di byte inviati
             (evento nativo Livewire `livewire-upload-progress`, non
             simulato); una volta trasferito il file, il server esamina il
             PDF (conteggio pagine via Imagick) — un'unica chiamata atomica,
             senza un vero "avanzamento" misurabile, quindi mostriamo il
             tempo REALMENTE trascorso invece di una barra finta. --}}
        <span x-show="uploading" class="font-medium text-indigo-700 dark:text-indigo-400">📤 <span x-text="progress"></span>%</span>
        <span wire:loading wire:target="pendingUploads.{{ $page->id }}" class="font-medium text-indigo-700 dark:text-indigo-400">
            🔎 Analisi del PDF... (<span x-text="examiningSeconds"></span>s)
        </span>

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
    </span>

    @if ($locked)
        <span class="rounded text-xs font-medium shrink-0 py-0.5 pl-2 pr-2 {{ $page->status->colorClasses() }}">
            {{ $page->status->label() }}
        </span>
    @else
        <select
            x-on:click.stop
            x-on:keydown.stop
            x-on:focus="$store.pagePresence.startEditing({{ $page->id }})"
            x-on:blur="$store.pagePresence.stopEditing({{ $page->id }})"
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
