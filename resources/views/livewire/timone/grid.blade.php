<div
    class="space-y-4"
    {{-- Revisione 2, Fase 4.2: dove andare se il numero viene eliminato mentre è aperto (resources/js/deleted-issue-redirect.js). --}}
    data-deleted-redirect="{{ route('magazines.index', ['eliminato' => 1]) }}"
    x-data="{
        remoteToast: null, remoteToastTimeout: null,
        // Annulla/ripeti (2026-10-28): una richiesta alla volta (le raffiche
        // di Ctrl+Z si ignorano mentre una è in corso) e toast di 4 secondi.
        timelineBusy: false, timelineToast: null, timelineToastTimeout: null,
        async timeline(action) {
            if (this.timelineBusy) return;
            this.timelineBusy = true;
            try { await $wire.call(action); } finally { this.timelineBusy = false; }
        },
        // Una modale aperta (modifica contenuto, conferme, x-modal di Breeze):
        // lì Ctrl+Z non deve toccare il timone.
        isModalOpen() {
            return document.body.classList.contains('overflow-y-hidden')
                || [...document.querySelectorAll('[aria-modal=true], [data-testid=content-edit-modal]')].some((el) => el.getClientRects().length > 0);
        },
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

            // Annulla/ripeti (2026-10-28): Ctrl/⌘+Z, Ctrl/⌘+Shift+Z, Ctrl+Y.
            // Mai in un campo di testo (lì resta l'annulla del browser) né
            // con una modale aperta; non collide con Esc, Ctrl/⌘+A,
            // Ctrl/⌘+Shift+G e le frecce.
            const mod = event.ctrlKey || event.metaKey;
            const key = event.key.toLowerCase();

            if (mod && (key === 'z' || (key === 'y' && event.ctrlKey && ! event.metaKey))) {
                if (this.isModalOpen()) return;
                event.preventDefault();
                this.timeline(key === 'y' || event.shiftKey ? 'redo' : 'undo');

                return;
            }

            if (event.key === 'Escape') {
                // Con una finestra aperta (commenti, modifica contenuto) Esc
                // chiude solo quella: la selezione delle pagine resta (2026-10-29).
                if (this.isModalOpen()) return;
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
    x-on:timeline-result.window="
        clearTimeout(timelineToastTimeout);
        timelineToast = $event.detail;
        timelineToastTimeout = setTimeout(() => timelineToast = null, 4000);
    "
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
    x-on:open-content-create-for-page.window="$store.contentPanel.toggle(true); setTimeout(() => document.getElementById('content-create-panel')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 150)"
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
            {{-- Annulla/ripeti (2026-10-28): disattivati con la pila vuota; il
                 tooltip dice quale azione (TimelineActionService::state()). --}}
            @if ($timeline)
                <div class="inline-flex items-center gap-0.5" data-testid="timeline-controls">
                    <button type="button" x-on:click="timeline('undo')" @disabled(! $timeline['undo']) title="{{ $timeline['undo'] ? ($timeline['undoBlocked'] ? 'Non annullabile: '.$timeline['undo'].' — '.$timeline['undoBlocked'] : 'Annulla: '.$timeline['undo'].' (Ctrl/⌘+Z)') : 'Niente da annullare' }}" class="px-2 py-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 disabled:opacity-40" data-testid="timeline-undo">↶</button>
                    <button type="button" x-on:click="timeline('redo')" @disabled(! $timeline['redo']) title="{{ $timeline['redo'] ? 'Ripeti: '.$timeline['redo'].' (Ctrl/⌘+Shift+Z, Ctrl+Y)' : 'Niente da ripetere' }}" class="px-2 py-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 disabled:opacity-40" data-testid="timeline-redo">↷</button>
                    <div class="relative">
                        <button type="button" wire:click="$toggle('showTimelineMenu')" class="px-2 py-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300" title="Cronologia delle mie azioni su questo numero" data-testid="timeline-menu-toggle">🕘</button>
                        @if ($showTimelineMenu)
                            <div class="absolute left-0 z-50 mt-1 w-80 max-h-80 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg p-2 text-xs space-y-1" x-on:click.outside="$wire.set('showTimelineMenu', false)" data-testid="timeline-menu">
                                <p class="font-medium text-gray-600 dark:text-gray-300">Le mie ultime azioni (24 ore, max {{ \App\Support\Timeline\TimelineActionService::MAX_STEPS }})</p>
                                @forelse ($timelineHistory as $step)
                                    <div class="flex items-start gap-2">
                                        <span class="shrink-0 text-gray-400">{{ $step->created_at->format('H:i') }}</span>
                                        <span class="flex-1 {{ $step->status === \App\Models\TimelineAction::UNDONE ? 'line-through text-gray-400' : '' }}" title="{{ $step->blocked_reason }}">{{ $step->label }}</span>
                                        <span class="shrink-0 {{ $step->blocked_reason ? 'text-red-600' : 'text-gray-400' }}">{{ $step->blocked_reason ? 'non annullabile' : ($step->status === \App\Models\TimelineAction::UNDONE ? 'annullata' : 'eseguita') }}</span>
                                    </div>
                                @empty
                                    <p class="text-gray-400 italic">Nessuna azione.</p>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="flex items-center gap-1.5 text-xs" title="{{ $issue->deadline_reminder_sent_at ? 'Sollecito già inviato per questa scadenza' : '' }}">
                <label for="print-deadline-{{ $issue->id }}" class="text-gray-500 dark:text-gray-400" title="Scadenza stampa">📅<span class="sr-only"> Scadenza stampa</span></label>
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

            {{-- Pannello contenuti (2026-10-28): il contatore è la stessa lista
                 della scheda «Da assegnare» (Grid::render(), unassignedCount). --}}
            <button
                type="button"
                x-on:click="$store.contentPanel.toggle()"
                :class="$store.contentPanel.open ? '!bg-indigo-600 !text-white !border-indigo-600' : ''"
                class="px-3 py-1.5 rounded-lg border text-sm transition-colors bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700"
                title="Apri o chiudi il pannello dei contenuti (assegna senza risalire)"
                data-testid="content-panel-toggle"
            >
                📚 Contenuti · {{ $unassignedCount }} da assegnare
            </button>

            <button
                type="button"
                wire:click="toggleReorderLog"
                title="Storico spostamenti"
                @class([
                    'px-3 py-1.5 rounded-lg border text-sm transition-colors',
                    'bg-indigo-600 text-white border-indigo-600' => $showReorderLog,
                    'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' => ! $showReorderLog,
                ])
            >
                {{-- Etichette brevi (2026-10-28): con il bottone «Contenuti» la barra
                     deve restare su una riga a 1366 px; il nome completo resta per
                     screen reader e tooltip. --}}
                📜 Storico<span class="sr-only"> spostamenti</span>
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
                🔀<span class="sr-only"> Modalità</span> Scambio
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
                ☑️ Selezione<span class="sr-only"> multipla</span>
            </button>

            {{-- Nessuna proprietà Livewire per questo pannello: è un form GET
                 puro, non serve reattività server-side, solo mostrare/
                 nascondere i filtri prima dello scaricamento diretto. --}}
            <div x-data="{ open: false }" class="relative" data-testid="timone-export">
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
    {{-- Toast di annulla/ripeti (2026-10-28): «Annullato: …» con «Ripeti», oppure il motivo del rifiuto. --}}
    <div
        x-show="timelineToast"
        x-transition
        x-cloak
        :class="timelineToast?.ok ? 'bg-gray-900 dark:bg-gray-700' : 'bg-red-700'"
        class="fixed bottom-16 right-4 z-50 max-w-sm rounded-lg text-white text-sm px-4 py-2 shadow-lg flex items-center gap-3"
        role="status"
        data-testid="timeline-toast"
    >
        <span x-text="timelineToast?.message"></span>
        <button type="button" x-show="timelineToast?.canRedo" x-on:click="timelineToast = null; timeline('redo')" class="shrink-0 underline font-medium">Ripeti</button>
    </div>

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
    {{-- Commenti (2026-10-29): con Reverb collegato le pagine arrivano dagli
         eventi, i commenti solo dal polling (niente broadcast per i
         commenti, scelta dell'utente). Una richiesta al secondo in tutti e
         due i casi: senza Reverb checkForUpdates() controlla anche i
         commenti. In pausa durante il drag, come l'altro. --}}
    <template x-if="$store.realtimeFallback && $store.realtimeFallback.connected && ! $store.pagePresence?.dragging">
        <div wire:poll.1s="checkForCommentUpdates" aria-hidden="true" style="display: none;"></div>
    </template>
    {{-- «Seme» dei contatori dei commenti a ogni render: la chiave cambia
         con i valori, così x-init riparte e lo store si riallinea. --}}
    <div wire:key="comment-counts-{{ md5(json_encode($commentCounts)) }}" x-data x-init="$store.pageComments.replace(@js($commentCounts))" aria-hidden="true" hidden></div>

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
        {{-- Fase 4: modale di modifica, aperta da ✏️ (evento open-content-edit).
             «Nuovo contenuto» e «Caricamento multiplo» sono nel pannello
             contenuti dal 2026-10-28. --}}
        <livewire:timone.content-edit :issue="$issue" :key="'content-edit-'.$issue->id" />
    @endcan

    <livewire:timone.page-file-history :issue="$issue" :key="'page-file-history-'.$issue->id" />

    {{-- Pannello contenuti (2026-10-28, «contenuti sempre raggiungibili»):
         prima era una sezione nel flusso, sopra le pagine, e lavorando sulle
         ultime pagine bisognava risalire. Ora si apre dal bottone «Contenuti»
         della fascia sticky (store Alpine contentPanel,
         resources/js/timone-alpine.js): da 1440 px colonna fissa a destra,
         sotto la fascia, con il contenuto della pagina che si restringe
         (resources/css/app.css); sotto, tendina sotto la fascia, al massimo
         40% della finestra. Una sola istanza: è markup di questo componente,
         come prima. z-30: sopra le pagine, sotto fascia (40), modali (50) e
         popover dei contenuti (60). In tendina è anche il «bordo alto»
         dell'auto-scroll del drag (data-sticky-top). !mt-0: il genitore è
         space-y-4, il cui margin-top spostava di 16 px un elemento fixed. --}}
    <aside
        x-data
        x-show="$store.contentPanel.open"
        x-cloak
        :data-sticky-top="$store.contentPanel.docked ? null : ''"
        :style="$store.contentPanel.docked
            ? `top: var(--app-sticky-height, 0px); width: ${$store.contentPanel.width}px;`
            : `top: var(--app-sticky-height, 0px);`"
        :class="$store.contentPanel.docked
            ? 'right-0 bottom-0 border-l rounded-none'
            : 'inset-x-0 mx-auto max-w-7xl max-h-[40vh] sm:mx-6 lg:mx-auto border rounded-b-lg'"
        class="fixed z-30 !mt-0 flex flex-col bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 shadow-lg"
        data-testid="content-panel"
        aria-label="Contenuti"
    >
        {{-- Bordo trascinabile per la larghezza della colonna (solo in colonna). --}}
        <div
            x-show="$store.contentPanel.docked"
            x-on:mousedown.prevent="
                const move = (e) => $store.contentPanel.setWidth(window.innerWidth - e.clientX);
                const up = () => { window.removeEventListener('mousemove', move); window.removeEventListener('mouseup', up); };
                window.addEventListener('mousemove', move); window.addEventListener('mouseup', up);
            "
            class="absolute left-0 top-0 bottom-0 w-1.5 -ml-0.5 cursor-col-resize hover:bg-indigo-300/60"
            title="Trascina per cambiare la larghezza"
        ></div>

        <div class="shrink-0 p-2 space-y-1.5 border-b border-gray-100 dark:border-gray-700 text-xs">
            <div class="flex items-center gap-1.5">
                <div class="inline-flex rounded border border-gray-200 dark:border-gray-700 overflow-hidden" role="tablist">
                    @foreach (['unassigned' => 'Da assegnare', 'all' => 'Tutti', 'placeholders' => 'Segnaposto'] as $tab => $tabLabel)
                        <button type="button" role="tab" wire:click="$set('contentTab', '{{ $tab }}')" aria-selected="{{ $contentTab === $tab ? 'true' : 'false' }}" @class(['px-2 py-0.5', 'bg-indigo-600 text-white' => $contentTab === $tab, 'hover:bg-gray-50 dark:hover:bg-gray-700' => $contentTab !== $tab]) data-testid="content-tab-{{ $tab }}">
                            {{ $tabLabel }}{{ $tab === 'unassigned' ? " ({$unassignedCount})" : '' }}
                        </button>
                    @endforeach
                </div>
                <button type="button" x-on:click="$store.contentPanel.toggle(false)" class="ml-auto px-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" title="Chiudi il pannello">✕</button>
            </div>
            <div class="flex items-center gap-1.5">
                <input type="search" wire:model.live.debounce.300ms="contentSearch" placeholder="Cerca per titolo…" class="flex-1 min-w-0 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-2 py-0.5" data-testid="content-search" />
                <select wire:model.live="contentType" class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 py-0.5 pl-1 pr-6" aria-label="Tipo">
                    <option value="">Tutti i tipi</option>
                    <option value="articolo">Articoli</option>
                    <option value="pubblicita">Pubblicità</option>
                </select>
                <select wire:model.live="contentSort" class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 py-0.5 pl-1 pr-6" aria-label="Ordine">
                    <option value="recent">Ultimi creati</option>
                    <option value="alpha">A–Z</option>
                </select>
            </div>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto p-2 space-y-2">
            {{-- Nuovo contenuto e caricamento multiplo: spostati qui (scelta
                 dell'utente), una sola istanza ciascuno. --}}
            @can('workOnAds', $issue)
                {{-- Affiancati quando c'è posto (tendina larga), uno sotto l'altro in colonna. --}}
                <div class="flex flex-wrap gap-2 [&>div]:!p-2 [&>div]:!shadow-none [&>div]:flex-1 [&>div]:min-w-[16rem]">
                    <livewire:timone.content-create :issue="$issue" :key="'content-create-'.$issue->id" />
                    <livewire:timone.bulk-upload :issue="$issue" :key="'bulk-upload-'.$issue->id" />
                </div>
            @endcan

            @if ($unassignedContents->isEmpty() && $contentSearch !== '')
                <p class="text-xs italic text-gray-400">Nessun contenuto trovato per «{{ $contentSearch }}».</p>
            @elseif ($unassignedContents->isEmpty())
                <p class="text-xs italic text-gray-400">{{ $contentTab === 'unassigned' ? 'Tutti i contenuti sono stati assegnati.' : 'Nessun contenuto.' }}</p>
            @else
                <div class="space-y-1.5" data-testid="content-panel-list">
                    @foreach ($unassignedContents as $content)
                        @include('livewire.timone.partials.content-panel-item', ['content' => $content])
                    @endforeach
                </div>
            @endif

        {{-- Fase 4.3: passa il mouse su una voce per archiviarlo (🗄️) o
             eliminarlo (🗑️, doppia conferma) — gli archiviati riappaiono
             solo qui, mai nell'elenco sopra. --}}
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
    </aside>

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
        {{-- Griglia a doppia pagina (2026-10-29, sostituisce la colonna di
             aperture una sotto l'altra): più coppie per riga, le due pagine
             di una coppia attaccate come una rivista aperta, più spazio tra
             una coppia e l'altra. Coppie da PageSpreadBuilder::layout()
             (dalla posizione: pari a sinistra, dispari a destra), slot vuoti
             marcati dove starebbero II e III di copertina (le copertine sono
             nella fascia in alto).

             Vincoli che restano dal 2026-09-02 (vedi HANDOFF.md): ogni
             page-card è figlia DIRETTA del contenitore Sortable, senza div
             attorno (Sortable controlla target.parentNode === el, un wrapper
             fa fallire il drag in silenzio); gli slot vuoti non hanno
             data-page-id, quindi Sortable non li trascina e non li conta.

             Disegno in app.css (.timone-spreads): ogni posto ha una colonna
             fissa (mezzo spazio | sinistra | 2px | destra | mezzo spazio) e i
             figli la prendono dal loro ordine. Durante un trascinamento,
             quando Sortable sposta la pagina nel DOM, le pagine cambiano solo
             posto (come nella vista Griglia): le coppie si ricompongono dal
             vivo e si vede dove cade la pagina, anche tra le due pagine di una
             coppia. Due versioni scartate dopo la prova nel browser (margini
             dal lato: spostamenti a catena sotto il puntatore; margini scritti
             dal server: coppie scollate durante il drag), vedi HANDOFF.md.
             data-spread-side è il lato secondo il server, informativo (test).
             Coppie per riga con una container query sulla larghezza e lo zoom
             S/M/L: niente scroll orizzontale. --}}
        <div class="timone-spreads-wrap">
            <div
                x-sortable="$wire.swapMode"
                @page-dropped="$flipAfterDrop('movePage', $event.detail.pageId, $event.detail.newPosition)"
                @block-dropped="$flipAfterDrop('moveSelectedBlock', $event.detail.anchorPageId, $event.detail.newPosition)"
                class="timone-spreads"
                data-testid="spread-grid"
            >
                @foreach ($spreadLayout as $spreadIndex => $pair)
                    @foreach ($pair as $sideIndex => $page)
                        @if ($page)
                            @include('livewire.timone.partials.page-card', [
                                'page' => $page,
                                'dropEnabled' => true,
                                'avgThumbnailSeconds' => $avgThumbnailSeconds,
                                'pageAspectRatio' => $pageAspectRatio,
                                'spreadSide' => $sideIndex === 0 ? 'left' : 'right',
                            ])
                        @else
                            @php
                                $emptyLabel = match (true) {
                                    $loop->parent->first && $sideIndex === 0 => 'II di copertina',
                                    $loop->parent->last && $sideIndex === 1 => 'III di copertina',
                                    default => 'Nessuna pagina',
                                };
                            @endphp
                            <div
                                wire:key="spread-empty-{{ $spreadIndex }}-{{ $sideIndex }}"
                                class="spread-empty"
                                style="aspect-ratio: {{ $pageAspectRatio ?? '3 / 4' }};"
                                data-spread-empty="{{ $sideIndex === 0 ? 'left' : 'right' }}"
                                data-spread-side="{{ $sideIndex === 0 ? 'left' : 'right' }}"
                                data-testid="spread-empty"
                                title="{{ $emptyLabel === 'Nessuna pagina' ? 'Posizione senza pagina' : 'Qui sarebbe la '.$emptyLabel.': le copertine sono nella fascia in alto' }}"
                            >{{ $emptyLabel }}</div>
                        @endif
                    @endforeach
                @endforeach
            </div>
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
