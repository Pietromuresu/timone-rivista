<div
    class="space-y-4"
    {{-- Revisione 2, Fase 4.2: dove andare se il numero viene eliminato mentre è aperto (resources/js/deleted-issue-redirect.js). --}}
    data-deleted-redirect="{{ route('magazines.index', ['eliminato' => 1]) }}"
    x-data="{
        remoteToast: null, remoteToastTimeout: null,
        // Shortcut da tastiera (2026-09-02, punto 2 del prompt), identiche
        // in griglia/doppia/lista perché vivono qui sul componente Grid,
        // non per-vista: Esc deseleziona tutto (Grid::clearSelection()),
        // Ctrl/Cmd+A seleziona tutte le pagine dell'uscita corrente
        // (Grid::selectAllPages()), Ctrl/Cmd+Shift+G collega o scollega la
        // selezione multipla corrente, in base a se lo è già
        // (Grid::toggleLinkForSelection() — riusa linkSelectedPages()/
        // unlinkGroup(), nessuna logica di collegamento duplicata qui).
        // Il click/Ctrl+click sulla singola card/riga per selezionare
        // vive invece in page-card.blade.php/page-row.blade.php (vedi lì).
        // Mai attive col focus su un campo di testo (input/textarea/select/
        // contenteditable, incluso un modale con un form) — altrimenti
        // Ctrl+A dentro un campo selezionerebbe le pagine del timone
        // invece del testo che l'utente sta scrivendo.
        isTimoneShortcutTarget(el) {
            return el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA' && el.tagName !== 'SELECT' && ! el.isContentEditable;
        },
        handleTimoneShortcut(event) {
            if (! this.isTimoneShortcutTarget(event.target)) return;

            if (event.key === 'Escape') {
                $wire.clearSelection();

                return;
            }

            const ctrlOrCmd = event.ctrlKey || event.metaKey;

            if (ctrlOrCmd && ! event.shiftKey && event.key.toLowerCase() === 'a') {
                event.preventDefault();
                $wire.selectAllPages();

                return;
            }

            if (ctrlOrCmd && event.shiftKey && event.key.toLowerCase() === 'g') {
                event.preventDefault();
                $wire.toggleLinkForSelection();
            }
        },
    }"
    x-on:keydown.window="handleTimoneShortcut($event)"
    x-on:remote-change.window="
        clearTimeout(remoteToastTimeout);
        remoteToast = $event.detail.message;
        remoteToastTimeout = setTimeout(() => remoteToast = null, 4000);
    "
    {{-- Punto 4 (2026-09-02): il bottone "carica materiale" di una card/riga
         apre App\Livewire\Timone\ContentCreate, montato in cima alla
         griglia — su un numero lungo può restare fuori dal viewport
         rispetto alla pagina cliccata più in basso. Un piccolo ritardo
         (non un vero hook "dopo il render Livewire", per restare semplice
         qui) basta perché lo scroll parta a form già aperto, non prima. --}}
    x-on:open-content-create-for-page.window="setTimeout(() => document.getElementById('content-create-panel')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 150)"
    @auth
        x-init="
            $store.pagePresence?.join({{ $issue->id }}, { id: {{ auth()->id() }}, name: @js(auth()->user()->name) });
            $store.realtimeFallback?.watch();
        "
    @endauth
>
    {{-- Fascia del timone: riepilogo (Fase 6), comandi (revisione 2, Fase
         2.2) e, dal 2026-10-28, azioni sulla selezione. Con @teleport finisce
         nel contenitore sticky unico del layout (#timone-sticky-slot in
         layouts/app.blade.php), insieme all'header: un solo sticky top-0, una
         sola altezza misurata (--app-sticky-height), niente più `top`
         calcolato a mano. Lo scope Alpine resta quello del componente
         (scorciatoie, $wire). «Comprimi» riduce il riepilogo a una riga ed è
         ricordato nel browser (localStorage, con try/catch: in navigazione
         privata può mancare). --}}
    @teleport('#timone-sticky-slot')
    <div
        class="px-3 py-1 space-y-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm"
        data-testid="timone-sticky-band"
        x-data="{
            collapsed: false,
            init() { try { this.collapsed = window.localStorage.getItem('timone.stickyCollapsed') === '1'; } catch (e) {} },
            toggleCollapsed() {
                this.collapsed = ! this.collapsed;
                try { window.localStorage.setItem('timone.stickyCollapsed', this.collapsed ? '1' : '0'); } catch (e) {}
            },
        }"
    >
    <div class="flex items-start gap-2">
        <div class="flex-1 min-w-0">
            @include('livewire.timone.partials.summary-bar')
        </div>
        <button
            type="button"
            x-on:click="toggleCollapsed()"
            :aria-expanded="(! collapsed).toString()"
            :title="collapsed ? 'Espandi il riepilogo' : 'Comprimi il riepilogo a una riga'"
            class="shrink-0 rounded border border-gray-200 dark:border-gray-700 px-1.5 text-xs text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700"
            data-testid="sticky-collapse"
        ><span x-text="collapsed ? '⌄' : '⌃'">⌃</span></button>
    </div>


    {{-- Bottoni più compatti dentro la fascia sticky (selettori [&_…] di
         Tailwind 3.4): occupa meno altezza sopra le pagine senza cambiare il
         markup dei singoli bottoni. --}}
    <div class="flex items-center gap-x-3 gap-y-1.5 flex-wrap border-t border-gray-100 dark:border-gray-700 pt-1 [&_button]:!py-0.5 [&_button]:!px-2 [&_button]:!text-xs" data-testid="timone-toolbar">
        {{-- Fase 2 (2026-10-26): pagine interne e copertine contate a parte,
             così nessuno deve sommare o sottrarre a mente. --}}
        {{-- 2026-10-28: titolo solo per gli screen reader — ripeteva il
             riepilogo e da solo occupava una riga della fascia. --}}
        <h3 class="sr-only">
            Timone &middot; {{ $issue->total_pages }} pagine interne
            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                &middot; copertine {{ $covers->count() }}
                ({{ $covers->filter(fn ($c) => $c->contents->isNotEmpty())->count() }} assegnate)
            </span>
        </h3>

        <div class="flex flex-wrap items-center gap-1.5">
            <div class="flex items-center gap-1.5 text-xs" title="{{ $issue->deadline_reminder_sent_at ? 'Sollecito già inviato per questa scadenza' : '' }}">
                <label for="print-deadline-{{ $issue->id }}" class="text-gray-500 dark:text-gray-400">📅 Scadenza stampa</label>
                <input
                    id="print-deadline-{{ $issue->id }}"
                    type="date"
                    value="{{ $issue->print_deadline_at?->toDateString() }}"
                    wire:change="updatePrintDeadline($event.target.value)"
                    {{-- Revisione 2, Fase 4.1: impostazione del numero, solo redazione. --}}
                    @disabled(! $abilities->editsTimone())
                    class="text-xs rounded disabled:opacity-60 border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-1 py-0.5"
                />
                @if ($issue->print_deadline_at && $issue->deadline_reminder_sent_at)
                    <span class="text-emerald-600 dark:text-emerald-400" title="Sollecito già inviato al redattore per questa scadenza">✉️</span>
                @endif
            </div>

            @if ($abilities->editsTimone())
            <button
                type="button"
                wire:click="togglePageCountEditor"
                @class([
                    'px-3 py-1.5 rounded-lg border text-sm transition-colors',
                    'bg-indigo-600 text-white border-indigo-600' => $showPageCountEditor,
                    'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' => ! $showPageCountEditor,
                ])
            >
                ⚙️ Pagine interne
            </button>
            @endif

            <button
                type="button"
                wire:click="toggleReorderLog"
                @class([
                    'px-3 py-1.5 rounded-lg border text-sm transition-colors',
                    'bg-indigo-600 text-white border-indigo-600' => $showReorderLog,
                    'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' => ! $showReorderLog,
                ])
            >
                📜 Storico spostamenti
            </button>

            <button
                type="button"
                wire:click="toggleSwapMode"
                title="Scambia due pagine di posto con un click, senza trascinare"
                @class([
                    'px-3 py-1.5 rounded-lg border text-sm transition-colors',
                    'bg-indigo-600 text-white border-indigo-600' => $swapMode,
                    'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' => ! $swapMode,
                ])
            >
                🔀 Modalità scambio
            </button>

            <button
                type="button"
                wire:click="toggleSelectionMode"
                title="Seleziona più pagine insieme per applicare un cambio di stato o un blocco/sblocco a tutte in una volta"
                @class([
                    'px-3 py-1.5 rounded-lg border text-sm transition-colors',
                    'bg-indigo-600 text-white border-indigo-600' => $selectionMode,
                    'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' => ! $selectionMode,
                ])
            >
                ☑️ Selezione multipla
            </button>

            {{-- Nessuna proprietà Livewire per questo pannello: è un form GET
                 puro, non serve reattività server-side, solo mostrare/
                 nascondere i filtri prima dello scaricamento diretto. --}}
            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    x-on:click="open = ! open"
                    @class([
                        'px-3 py-1.5 rounded-lg border text-sm transition-colors',
                        'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700',
                    ])
                >
                    📄 Esporta PDF
                </button>

                <form
                    x-show="open"
                    x-on:click.outside="open = false"
                    method="GET"
                    action="{{ route('issues.export.timone-pdf', [$issue->magazine, $issue]) }}"
                    class="absolute z-10 mt-1 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg p-3 space-y-2 text-xs"
                    style="display: none;"
                >
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="withThumbnails" value="1" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                        Includi le miniature PDF
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="onlyAds" value="1" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                        Solo pagine con pubblicità
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="onlyUnapproved" value="1" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                        Solo pagine non ancora approvate
                    </label>
                    <button type="submit" class="w-full mt-1 px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                        Scarica PDF
                    </button>
                </form>
            </div>

            {{-- Fase 1, bug 7: zoom delle miniature, preferenza del singolo
                 utente ricordata in localStorage (store Alpine timoneZoom,
                 resources/js/timone-alpine.js) — nessuno stato Livewire, non
                 deve propagarsi agli altri utenti. Non ha effetto sulla lista. --}}
            @if ($viewMode !== 'lista')
                <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-sm" role="group" aria-label="Dimensione delle pagine">
                    <template x-for="(zoom, key) in ($store.timoneZoom?.levels ?? {})" :key="key">
                        <button
                            type="button"
                            x-on:click="$store.timoneZoom?.set(key)"
                            x-text="zoom.label"
                            :aria-pressed="$store.timoneZoom?.level === key"
                            :title="'Pagine ' + ({ s: 'piccole', m: 'medie', l: 'grandi' })[key]"
                            :class="$store.timoneZoom?.level === key
                                ? 'bg-indigo-600 text-white'
                                : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
                            class="px-2.5 py-1.5 transition-colors font-medium"
                        ></button>
                    </template>
                </div>
            @endif

            <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-sm">
                @foreach (['griglia' => 'Griglia', 'doppia' => 'Doppia pagina', 'lista' => 'Lista'] as $mode => $label)
                    <button
                        type="button"
                        wire:click="setViewMode('{{ $mode }}')"
                        @class([
                            'px-3 py-1.5 transition-colors',
                            'bg-indigo-600 text-white' => $viewMode === $mode,
                            'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' => $viewMode !== $mode,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    @include('livewire.timone.partials.selection-bar')
    </div>{{-- /timone-sticky-band --}}
    @endteleport

    {{-- Toast leggero per un cambiamento arrivato da un altro utente
         (richiesta: "senza essere invasivo") — nessuno stato server, si
         auto-nasconde da solo dopo 4s, alimentato dall'evento browser
         'remote-change' dispatchato da Grid::notifyRemoteChange() da
         ognuno dei listener broadcast già esistenti (#[On('echo-presence:...')])
         E da checkForUpdates() (fallback a polling, vedi sotto). --}}
    <div
        x-show="remoteToast"
        x-transition
        x-text="remoteToast"
        class="fixed bottom-4 right-4 z-50 max-w-xs rounded-lg bg-gray-900 dark:bg-gray-700 text-white text-sm px-4 py-2 shadow-lg"
        style="display: none;"
    ></div>

    {{-- Fallback a polling (2026-09-01, sostituisce il precedente
         setInterval JS + $wire.pollRefresh()): l'elemento con wire:poll
         viene montato nel DOM SOLO mentre i websocket non sono connessi
         (x-if, non x-show — un elemento con display:none avrebbe comunque
         wire:poll attivo, x-if invece lo rimuove/reinserisce davvero dal
         DOM, che è come Livewire capisce se un wire:poll è "presente")
         E mentre nessun drag&drop è in corso su questo client: un poll
         che arriva a metà di un trascinamento potrebbe far ripartire il
         morph di Livewire sull'elemento che Sortable sta ancora
         manipolando a mano, con risultati visivi imprevedibili — non un
         problema di dati (nulla è ancora scritto finché il drop non
         chiama $wire.movePage), solo di UI. `$store.pagePresence.dragging`
         è impostato dagli stessi hook onStart/onEnd di Sortable già usati
         per il badge "chi sta modificando cosa" (resources/js/app.js). --}}
    <template x-if="$store.realtimeFallback && ! $store.realtimeFallback.connected && ! $store.pagePresence?.dragging">
        <div wire:poll.1s="checkForUpdates" aria-hidden="true" style="display: none;"></div>
    </template>

    {{-- Componente a sé (bottone + pannello insieme, non separabili come
         gli altri toggle qui sopra che sono proprietà di Grid.php) — la
         propria riga, non incastrato nella toolbar. --}}
    <livewire:timone.activity-log-panel :issue="$issue" :key="'activity-log-'.$issue->id" />
    <livewire:timone.ad-reservations :issue="$issue" :key="'ad-reservations-'.$issue->id" />

    {{-- Avanzamento reale del rendering miniature, aggregato su TUTTE le
         pagine del numero ancora in coda/elaborazione (2026-07-31, non più
         legato all'ultimo batch confermato — bug segnalato dall'utente: la
         versione precedente teneva questo stato in una proprietà Livewire
         effimera, persa ad ogni refresh della pagina). $thumbnailProgress
         arriva da render() → App\Support\ThumbnailProgressEstimator,
         ricalcolato da zero ogni volta da dati reali (stato in database,
         tempo di orologio realmente trascorso) — identico prima e dopo un
         refresh, mai un placeholder. Ogni card mostra anche la propria
         stima individuale (vedi page-row.blade.php/page-card.blade.php),
         questo banner è solo l'aggregato. Non bloccante: le pagine sono
         già scritte in database, l'utente può continuare a lavorare. Si
         aggiorna da solo ad ogni render (broadcast PageFileUploaded per
         pagina, o checkForUpdates() di fallback) e sparisce da solo quando non
         resta più nessuna pagina in elaborazione. --}}
    @if ($thumbnailProgress && ! $thumbnailProgressDismissed)
        <div class="bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 rounded-lg p-3 text-xs space-y-1">
            <div class="flex items-center justify-between gap-2">
                <p class="font-medium text-indigo-800 dark:text-indigo-300">
                    🖼️ {{ $thumbnailProgress['pending'] }} {{ $thumbnailProgress['pending'] === 1 ? 'pagina' : 'pagine' }} in elaborazione
                    @if ($thumbnailProgress['remainingSeconds'] !== null)
                        — circa {{ $thumbnailProgress['remainingSeconds'] }}s rimanenti in totale
                    @else
                        — stima non ancora disponibile
                    @endif
                </p>
                <button type="button" wire:click="dismissThumbnailProgress" class="text-indigo-500 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-200" title="Nascondi">✕</button>
            </div>
            {{-- Barra indeterminata, non una percentuale: non c'è un
                 "totale del batch" fisso da cui calcolare una % reale (il
                 conteggio può crescere con nuovi upload), quindi una barra
                 a percentuale fissa sarebbe essa stessa un placeholder —
                 questa mostra onestamente "lavoro in corso", non un
                 progresso quantificato che non abbiamo. --}}
            <div class="w-full bg-indigo-100 dark:bg-indigo-950 rounded h-1.5 overflow-hidden">
                <div class="bg-indigo-600 h-1.5 w-1/3 animate-pulse"></div>
            </div>
        </div>
    @endif

    @if (! empty($automaticChecks['nonContiguousContents']) || ! empty($automaticChecks['approvedEmptyPages']) || ! empty($automaticChecks['missingPdfPages']))
        <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg p-3 text-xs space-y-1">
            <p class="font-medium text-amber-800 dark:text-amber-300">⚠️ Avvisi</p>

            @foreach ($automaticChecks['nonContiguousContents'] as $flagged)
                <p class="text-amber-700 dark:text-amber-400">
                    «{{ $flagged['title'] }}» è su pagine non consecutive ({{ implode(', ', $flagged['positions']) }}) — verifica che sia voluto.
                </p>
            @endforeach

            @if (! empty($automaticChecks['approvedEmptyPages']))
                <p class="text-amber-700 dark:text-amber-400">
                    {{ count($automaticChecks['approvedEmptyPages']) === 1 ? 'La pagina' : 'Le pagine' }}
                    {{ implode(', ', $automaticChecks['approvedEmptyPages']) }}
                    {{ count($automaticChecks['approvedEmptyPages']) === 1 ? 'è approvata' : 'sono approvate' }}
                    ma senza contenuti assegnati.
                </p>
            @endif

            @if (! empty($automaticChecks['missingPdfPages']))
                <p class="text-amber-700 dark:text-amber-400">
                    {{ count($automaticChecks['missingPdfPages']) === 1 ? 'La pagina' : 'Le pagine' }}
                    {{ implode(', ', $automaticChecks['missingPdfPages']) }}
                    {{ count($automaticChecks['missingPdfPages']) === 1 ? 'non ha' : 'non hanno' }}
                    ancora un PDF caricato — non {{ count($automaticChecks['missingPdfPages']) === 1 ? 'può' : 'possono' }} essere segnata/e «Ok stampa» finché manca.
                </p>
            @endif
        </div>
    @endif

    @if ($showPageCountEditor)
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 p-3 space-y-3">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Modifica pagine interne <span class="font-normal text-gray-400">(le copertine non cambiano)</span></h4>

            <div class="flex items-end gap-3 flex-wrap">
                <div>
                    <label for="new-total-pages" class="block text-xs text-gray-500 dark:text-gray-400">Nuovo totale pagine interne</label>
                    <input
                        id="new-total-pages"
                        type="number"
                        min="0"
                        max="2000"
                        wire:model.blur="newTotalPages"
                        class="mt-1 w-28 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-2 py-1"
                    />
                </div>

                @if ($pageCountImpact && $pageCountImpact['type'] === 'increase')
                    <div>
                        <label for="insert-mode" class="block text-xs text-gray-500 dark:text-gray-400">Dove inserire le {{ $pageCountImpact['delta'] }} nuove pagine</label>
                        <select id="insert-mode" wire:model.live="insertMode" class="mt-1 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-2 py-1">
                            <option value="end">In coda</option>
                            <option value="position">In una posizione specifica</option>
                        </select>
                    </div>

                    @if ($insertMode === 'position')
                        <div>
                            <label for="insert-at-position" class="block text-xs text-gray-500 dark:text-gray-400">Prima della posizione</label>
                            <input
                                id="insert-at-position"
                                type="number"
                                min="1"
                                max="{{ $pages->count() + 1 }}"
                                wire:model.live="insertAtPosition"
                                placeholder="{{ $pages->count() + 1 }}"
                                class="mt-1 w-24 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-2 py-1"
                            />
                        </div>
                    @endif

                    <button type="button" wire:click="resizePages" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                        Applica
                    </button>
                @endif
            </div>

            @error('pageCount')
                <p class="text-xs text-red-600 dark:text-red-400">⚠️ {{ $message }}</p>
            @enderror

            @if ($newTotalPagesParsed === null)
                <p class="text-xs text-red-600 dark:text-red-400">⚠️ Inserisci un numero di pagine valido (un intero da 0 a 2000).</p>
            @elseif ($newTotalPagesParsed > 0 && $newTotalPagesParsed % 4 !== 0)
                <p class="text-xs text-amber-600 dark:text-amber-400">⚠️ Non è un multiplo di 4 — consentito per i casi speciali, ma da verificare.</p>
            @endif

            @if ($pageCountImpact && $pageCountImpact['type'] === 'decrease')
                <div class="text-sm border-t border-gray-100 dark:border-gray-700 pt-3">
                    <p class="text-gray-700 dark:text-gray-200">
                        Verranno eliminate <strong>{{ $pageCountImpact['removedCount'] }}</strong> pagine in coda
                        (dalla posizione {{ $newTotalPagesParsed + 1 }} alla {{ $newTotalPagesParsed + $pageCountImpact['removedCount'] }}).
                    </p>

                    @if (empty($pageCountImpact['affectedPages']))
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Nessuna di queste pagine ha contenuti assegnati o file caricati.</p>
                    @else
                        <div class="mt-2 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg p-2">
                            <p class="text-xs font-medium text-red-700 dark:text-red-400 mb-1">
                                ⚠️ {{ count($pageCountImpact['affectedPages']) }} di queste pagine hanno contenuti o file coinvolti:
                            </p>
                            <ul class="text-xs text-red-700 dark:text-red-400 space-y-0.5">
                                @foreach ($pageCountImpact['affectedPages'] as $affected)
                                    <li>
                                        Posizione {{ $affected['position'] }}:
                                        @if ($affected['contentCount'] > 0)
                                            {{ $affected['contentCount'] }} contenuto/i (tornerà/tornano tra i non assegnati)
                                        @endif
                                        @if ($affected['contentCount'] > 0 && $affected['hasFiles'])
                                            &middot;
                                        @endif
                                        @if ($affected['hasFiles'])
                                            file caricato (andrà perso)
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($pageCountImpact['lockedCount'] > 0)
                        <p class="text-xs font-medium text-red-700 dark:text-red-400 mt-2">
                            🔒 {{ $pageCountImpact['lockedCount'] }} di queste pagine sono bloccate: sbloccale prima di procedere, la riduzione verrà comunque rifiutata finché restano bloccate.
                        </p>
                    @endif

                    <button
                        type="button"
                        x-on:click="confirm('Ridurre le pagine a {{ $newTotalPagesParsed }}? L\'operazione non è reversibile.') && $wire.resizePages(true)"
                        @disabled($pageCountImpact['lockedCount'] > 0)
                        class="mt-3 px-3 py-1.5 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Conferma rimozione definitiva
                    </button>
                </div>
            @endif
        </div>
    @endif

    @if ($showReorderLog)
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 p-3">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Storico spostamenti (ultimi {{ $reorderLogs->count() }})
            </h4>

            @if ($reorderLogs->isEmpty())
                <p class="text-xs italic text-gray-400">Nessuno spostamento registrato per questo numero.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700 max-h-64 overflow-y-auto text-sm">
                    @foreach ($reorderLogs as $log)
                        <li class="py-1.5 flex items-center justify-between gap-3">
                            <span class="text-gray-700 dark:text-gray-200">
                                <strong>{{ $log->user?->name ?? 'Utente eliminato' }}</strong>
                                ha spostato la pagina (ora in posizione {{ $log->page->position }})
                                dalla posizione {{ $log->from_position }} alla {{ $log->to_position }}
                            </span>
                            <span class="shrink-0 text-xs text-gray-400" title="{{ $log->created_at }}">
                                {{ $log->created_at->diffForHumans() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 p-3">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Cruscotto pubblicitario</h4>

            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 text-xs">
                    <a
                        href="{{ route('issues.export.ad-dashboard-csv', [$issue->magazine, $issue]) }}"
                        class="text-indigo-600 dark:text-indigo-400 hover:underline"
                    >⬇️ CSV</a>
                    <a
                        href="{{ route('issues.export.ad-dashboard-pdf', [$issue->magazine, $issue]) }}"
                        class="text-indigo-600 dark:text-indigo-400 hover:underline"
                    >⬇️ PDF</a>
                </div>
            </div>
        </div>

        {{-- Fase 6: niente più soglia di allarme né colori di avviso sul
             carico pubblicitario (richiesta della specifica): solo la percentuale. --}}

        <div class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <span class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                {{ $adLoad['adLoadPercentage'] }}%
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                carico pubblicitario &middot;
                {{ $adLoad['adEquivalentPages'] }} pagine equiv. pubblicità su {{ $adLoad['totalPages'] }} totali
                ({{ $adLoad['placedAdEquivalentPages'] }} già assegnate + {{ $adLoad['reservedAdEquivalentPages'] }} prenotate) &middot;
                {{ $adLoad['editorialEquivalentPages'] }} pagine equiv. editoriale
            </span>
        </div>

        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
            <span>Inserzioni assegnate: <strong>{{ $adLoad['assignedAdCount'] }}</strong></span>
            <span>Pubblicità non ancora assegnate: <strong>{{ $unassignedAdCount }}</strong></span>
            {{-- Fase 5: segnaposto (pubblicità previste senza inserzionista) in tutto il numero, copertine comprese (App\Support\IssueSummary). --}}
            <span data-testid="placeholder-ads-count">Pubblicità da assegnare (segnaposto): <strong>{{ $summary['placeholderCount'] }}</strong>@if ($summary['placeholderCount'] > 0) ({{ collect($summary['placeholderByFormat'])->map(fn ($n, $format) => \App\Enums\AdFormat::from($format)->label().': '.$n)->implode(', ') }})@endif</span>
        </div>

        @if (! empty($adLoad['formatBreakdown']))
            <div class="mt-2 flex flex-wrap gap-1.5 text-[11px]">
                @foreach ($adLoad['formatBreakdown'] as $formatValue => $count)
                    <span class="px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                        {{ \App\Enums\AdFormat::from($formatValue)->label() }}: {{ $count }}
                    </span>
                @endforeach
            </div>
        @endif

        @if (! empty($adLoad['confirmationBreakdown']))
            <div class="mt-1 flex flex-wrap gap-1.5 text-[11px]">
                @foreach ($adLoad['confirmationBreakdown'] as $statusValue => $count)
                    <span class="px-1.5 py-0.5 rounded bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
                        {{ \App\Enums\AdConfirmationStatus::from($statusValue)->label() }}: {{ $count }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    @error('covers')
        <p class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg px-3 py-2">📘 {{ $message }}</p>
    @enderror

    @error('reorder')
        <p class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg px-3 py-2">{{ $message }}</p>
    @enderror

    @if ($swapMode)
        <p class="text-xs text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 rounded-lg px-3 py-2">
            🔀 Modalità scambio attiva:
            @if ($swapSelectedPageId)
                pagina selezionata — clicca un'altra pagina per scambiarle, o clicca di nuovo la stessa per annullare.
            @else
                clicca una pagina, poi un'altra: si scambiano di posto (contenuti, stato e file restano legati alla pagina).
            @endif
        </p>
    @endif


    @error('pdfRequired')
        <p class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg px-3 py-2">📄 {{ $message }}</p>
    @enderror

    {{-- Revisione 2, Fase 2.3: freccia o rotazione rifiutata dal server
         (es. un collega ha appena occupato quel posto). --}}
    @error('grid')
        <p class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg px-3 py-2" data-testid="grid-error">↔ {{ $message }}</p>
    @enderror



    <template x-if="$store.realtimeFallback && ! $store.realtimeFallback.connected">
        <p class="text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2">
            ⚠️ Aggiornamento in tempo reale non disponibile — la pagina si aggiorna automaticamente entro un secondo.
        </p>
    </template>

    {{-- Revisione 2, Fase 4.1: anche il Commerciale (solo pubblicità: ogni
         componente controlla poi il tipo con ContentPolicy). --}}
    @can('workOnAds', $issue)
        <livewire:timone.content-create :issue="$issue" :key="'content-create-'.$issue->id" />
        {{-- Revisione 2, Fase 3.2: più file in una volta, un contenuto per file. --}}
        <livewire:timone.bulk-upload :issue="$issue" :key="'bulk-upload-'.$issue->id" />
        {{-- Fase 4: modale di modifica, aperta da ✏️ (evento open-content-edit). --}}
        <livewire:timone.content-edit :issue="$issue" :key="'content-edit-'.$issue->id" />
    @endcan

    <livewire:timone.page-file-history :issue="$issue" :key="'page-file-history-'.$issue->id" />

    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 p-3">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Contenuti da assegnare ({{ $unassignedContents->count() }})
            </h4>

            <input
                type="search"
                wire:model.live.debounce.300ms="contentSearch"
                placeholder="Cerca per titolo..."
                class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-2 py-1 w-48"
            />
        </div>

        @error('percentage')
            <p class="text-xs text-red-600 dark:text-red-400 mb-2">{{ $message }}</p>
        @enderror

        @error('rename')
            <p class="text-xs text-red-600 dark:text-red-400 mb-2">{{ $message }}</p>
        @enderror

        @error('extend')
            <p class="text-xs text-red-600 dark:text-red-400 mb-2">{{ $message }}</p>
        @enderror

        @if ($unassignedContents->isEmpty() && $contentSearch !== '')
            <p class="text-xs italic text-gray-400">Nessun contenuto trovato per «{{ $contentSearch }}».</p>
        @elseif ($unassignedContents->isEmpty())
            <p class="text-xs italic text-gray-400">Tutti i contenuti sono stati assegnati.</p>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach ($unassignedContents as $content)
                    @include('livewire.timone.partials.unassigned-content-chip', ['content' => $content])
                @endforeach
            </div>
        @endif

        {{-- Fase 4.3: passa il mouse su un chip per archiviarlo (🗄️) o
             eliminarlo (🗑️, doppia conferma) — gli archiviati riappaiono
             solo qui, mai nel pannello sopra usato per il drag&drop. --}}
        <button type="button" wire:click="$toggle('showArchivedContents')" class="mt-2 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            {{ $showArchivedContents ? '▾' : '▸' }} Contenuti archiviati
        </button>

        @if ($showArchivedContents)
            <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                @if ($archivedContents->isEmpty())
                    <p class="text-xs italic text-gray-400">Nessun contenuto archiviato.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($archivedContents as $content)
                            <div wire:key="archived-content-{{ $content->id }}" class="flex items-center gap-1.5 px-2 py-1.5 rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 text-xs max-w-[220px] opacity-75">
                                <span class="truncate" title="{{ $content->displayLabel() }}">{{ $content->displayLabel() }}</span>
                                @if ($abilities->canChangeContent($content))
                                    <button type="button" wire:click="unarchiveContent({{ $content->id }})" title="Ripristina" class="shrink-0 ml-auto text-gray-400 hover:text-emerald-600">↩️</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Fase 2: tutte le copertine (I, bandella, II, III, IV) in una sola fascia
         prima della pagina 1, in tutte e tre le viste. --}}
    @include('livewire.timone.partials.cover-band', ['covers' => $covers])

    @if ($pages->isEmpty())
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700">
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <p class="font-medium">Nessuna pagina per questo numero.</p>
                <p class="text-sm mt-1">Imposta il numero di pagine interne (⚙️ Pagine interne) per generare il timone.</p>
            </div>
        </div>
    @elseif ($viewMode === 'griglia')
        <div
            x-sortable="$wire.swapMode"
            @page-dropped="$flipAfterDrop('movePage', $event.detail.pageId, $event.detail.newPosition)"
            @block-dropped="$flipAfterDrop('moveSelectedBlock', $event.detail.anchorPageId, $event.detail.newPosition)"
            {{-- Fase 1, bug 7 (2026-10-26): colonne a larghezza minima
                 variabile (--timone-page-min, impostata dallo zoom S/M/L in
                 resources/js/timone-alpine.js) invece di 8 colonne fisse
                 che schiacciavano il contenuto delle pagine; altezza dalle
                 proporzioni reali del formato (page-card). --}}
            class="grid gap-5 grid-cols-[repeat(auto-fill,minmax(var(--timone-page-min,11.5rem),1fr))]"
        >
            @foreach ($pages as $page)
                @include('livewire.timone.partials.page-card', ['page' => $page, 'dropEnabled' => true, 'avgThumbnailSeconds' => $avgThumbnailSeconds, 'pageAspectRatio' => $pageAspectRatio])
            @endforeach
        </div>
    @elseif ($viewMode === 'doppia')
        {{-- Ogni pagina resta un elemento Sortable a sé, figlio diretto
             dello stesso contenitore usato da griglia/lista (non annidata
             in un div per apertura, il bug originario: Sortable richiede
             figli diretti dello stesso contenitore per il drag&drop di
             riordino, e prima ogni apertura aveva il proprio div "flex
             justify-center", rendendo le pagine trascinabili solo tra loro
             all'interno della stessa coppia). L'andata a capo visiva tra
             un'apertura e la successiva è ottenuta con un elemento
             "spacer" (flex-basis:100%) invece che con l'annidamento: lo
             spacer non ha data-page-id, quindi il filtro
             `draggable: '[data-page-id]'` in resources/js/app.js lo
             esclude sia dal trascinamento sia dal calcolo dell'indice di
             destinazione (Sortable conta solo gli elementi che
             corrispondono al selettore). Risultato: si può trascinare una
             singola pagina fuori da una coppia, dentro un'altra coppia, o
             in una posizione che ne spacca una esistente — lato/posizione
             si ricalcolano da soli al prossimo render() (PageSpreadBuilder
             raggruppa sempre le pagine nell'ordine risultante).

             IMPORTANTE — niente div-wrapper attorno a ogni page-card qui
             (bug reale trovato e corretto il 2026-09-02, la sessione
             precedente lo aveva solo mascherato aggiungendo un wire:key
             al wrapper senza accorgersi che il wrapper stesso rompeva il
             drag&drop): il root di page-card.blade.php porta già il suo
             data-page-id, IDENTICO a quello usato da griglia/lista.
             SortableJS individua l'elemento trascinabile risalendo dal
             target del click fino al primo antenato che soddisfa il
             selettore `draggable: '[data-page-id]'` (closest(), vedi
             node_modules/sortablejs/src/Sortable.js) e poi verifica che
             il suo `parentNode` sia esattamente il contenitore Sortable
             (Sortable.js:566, `target.parentNode === el`) prima di
             avviare il drag. Con un wrapper esterno che duplica anche lui
             data-page-id, closest() risale e si ferma SUBITO al root di
             page-card (il primo match, più vicino alla maniglia
             ".drag-handle"), il cui parentNode è il wrapper e non questo
             contenitore: la condizione fallisce silenziosamente e il drag
             non parte mai — esattamente il sintomo osservato ("la pagina
             non si muove minimamente"), invisibile ai test esistenti
             perché nessuno di essi guida un mousedown reale in un
             browser (niente Dusk in questo progetto, solo
             Livewire::test()/lettura markup). Le classi di posizione per
             apertura (larghezza, margine, bordo separatore) che prima
             stavano sul wrapper vengono quindi passate a page-card come
             parametro `extraClasses`, mantenendo il root di page-card
             l'unico elemento draggable reale, figlio diretto di questo
             contenitore, identico in tutte e tre le viste. --}}
        <div
            x-sortable="$wire.swapMode"
            @page-dropped="$flipAfterDrop('movePage', $event.detail.pageId, $event.detail.newPosition)"
            @block-dropped="$flipAfterDrop('moveSelectedBlock', $event.detail.anchorPageId, $event.detail.newPosition)"
            class="flex flex-wrap gap-x-0.5 mx-auto w-[calc(var(--timone-page-w,13rem)*2+0.125rem)]"
        >
            {{-- Niente gap-y sul contenitore: applicherebbe uno spazio
                 verticale anche sopra/sotto la riga a lì-height dello
                 spacer (raddoppiando la distanza tra due aperture) — un
                 mt-3 su ogni pagina che apre una nuova apertura ottiene lo
                 stesso risultato visivo di prima senza quell'effetto.

                 Larghezza contenitore fissata a esattamente 2 pagine +
                 gap-x-0.5 (invece di "justify-center" su un contenitore a
                 piena larghezza): convenzione editoriale di stampa, la
                 pagina 1 è sempre una pagina destra (nessuna pagina
                 sinistra la precede) e, se il totale pagine è pari,
                 l'ultima pagina resta sola a sinistra (nessuna destra
                 successiva) — PageSpreadBuilder raggruppa già le pagine in
                 questo modo (prima pagina da sola, poi coppie), qui basta
                 allineare la prima apertura (singola) a destra con
                 "ml-auto"; l'ultima apertura, se singola, resta a sinistra
                 per il normale comportamento flex-start (nessuna classe
                 extra necessaria) ora che "justify-center" non c'è più a
                 ricentrarla. Senza un contenitore di larghezza fissa,
                 "ml-auto"/l'allineamento flex-start di una singola pagina
                 la spingerebbe ai bordi dello schermo invece che a
                 fianco della "spina" delle aperture da due pagine. --}}
            @foreach ($spreads as $spreadIndex => $spread)
                @foreach ($spread as $indexInSpread => $page)
                    @include('livewire.timone.partials.page-card', [
                        'page' => $page,
                        'dropEnabled' => true,
                        'avgThumbnailSeconds' => $avgThumbnailSeconds,
                        'pageAspectRatio' => $pageAspectRatio,
                        // Dimensione fissa + margini/bordo di posizione nell'apertura:
                        // prima stavano su un div-wrapper attorno a page-card, rimosso
                        // perché rompeva silenziosamente il drag&drop (vedi commento
                        // sopra) — page-card resta l'unico root, qui gli si passano
                        // solo le classi extra da appendere al suo class= esistente.
                        'extraClasses' => trim(
                            // Larghezza dallo zoom S/M/L (Fase 1, bug 7): stessa
                            // variabile usata per la larghezza del contenitore sopra.
                            'w-[var(--timone-page-w,13rem)]'
                            . ($spreadIndex > 0 ? ' mt-6' : '')
                            . ($indexInSpread > 0 ? ' border-l-2 border-gray-300 dark:border-gray-600' : '')
                            . ($spreadIndex === 0 && count($spread) === 1 ? ' ml-auto' : '')
                        ),
                    ])
                @endforeach
                <div class="basis-full h-0" aria-hidden="true"></div>
            @endforeach
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <ul
                x-sortable="$wire.swapMode"
                @page-dropped="$flipAfterDrop('movePage', $event.detail.pageId, $event.detail.newPosition)"
                @block-dropped="$flipAfterDrop('moveSelectedBlock', $event.detail.anchorPageId, $event.detail.newPosition)"
                class="divide-y divide-gray-100 dark:divide-gray-700"
            >
                @foreach ($pages as $page)
                    @include('livewire.timone.partials.page-row', ['page' => $page, 'dropEnabled' => true, 'avgThumbnailSeconds' => $avgThumbnailSeconds])
                @endforeach
            </ul>
        </div>
    @endif
</div>
