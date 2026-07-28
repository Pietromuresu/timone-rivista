<div
    class="space-y-4"
    x-data
    @auth
        x-init="
            $store.pagePresence.join({{ $issue->id }}, { id: {{ auth()->id() }}, name: @js(auth()->user()->name) });
            $store.realtimeFallback.watch(() => $wire.pollRefresh());
        "
    @endauth
>
    <div class="flex items-center justify-between">
        <h3 class="font-medium text-gray-700 dark:text-gray-300">
            Timone &middot; {{ $pages->count() }} pagine
        </h3>

        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="togglePageCountEditor"
                @class([
                    'px-3 py-1.5 rounded-lg border text-sm transition-colors',
                    'bg-indigo-600 text-white border-indigo-600' => $showPageCountEditor,
                    'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' => ! $showPageCountEditor,
                ])
            >
                ⚙️ Pagine totali
            </button>

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
                title="Scambia due pagine di posto con un click, senza trascinare — utile anche in modalità Doppia pagina, che non supporta il drag&drop"
                @class([
                    'px-3 py-1.5 rounded-lg border text-sm transition-colors',
                    'bg-indigo-600 text-white border-indigo-600' => $swapMode,
                    'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' => ! $swapMode,
                ])
            >
                🔀 Modalità scambio
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

    {{-- Componente a sé (bottone + pannello insieme, non separabili come
         gli altri toggle qui sopra che sono proprietà di Grid.php) — la
         propria riga, non incastrato nella toolbar. --}}
    <livewire:timone.activity-log-panel :issue="$issue" :key="'activity-log-'.$issue->id" />

    @if (! empty($automaticChecks['nonContiguousContents']) || ! empty($automaticChecks['approvedEmptyPages']))
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
        </div>
    @endif

    @if ($showPageCountEditor)
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 p-3 space-y-3">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Modifica pagine totali</h4>

            <div class="flex items-end gap-3 flex-wrap">
                <div>
                    <label for="new-total-pages" class="block text-xs text-gray-500 dark:text-gray-400">Nuovo totale pagine</label>
                    <input
                        id="new-total-pages"
                        type="number"
                        min="0"
                        max="2000"
                        wire:model.live="newTotalPages"
                        class="mt-1 w-28 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-2 py-1"
                    />
                </div>

                @if ($pageCountImpact['type'] === 'increase')
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

            @if ($newTotalPages > 0 && $newTotalPages % 4 !== 0)
                <p class="text-xs text-amber-600 dark:text-amber-400">⚠️ Non è un multiplo di 4 — consentito per i casi speciali, ma da verificare.</p>
            @endif

            @if ($pageCountImpact['type'] === 'decrease')
                <div class="text-sm border-t border-gray-100 dark:border-gray-700 pt-3">
                    <p class="text-gray-700 dark:text-gray-200">
                        Verranno eliminate <strong>{{ $pageCountImpact['removedCount'] }}</strong> pagine in coda
                        (dalla posizione {{ $newTotalPages + 1 }} alla {{ $newTotalPages + $pageCountImpact['removedCount'] }}).
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
                        x-on:click="confirm('Ridurre le pagine a {{ $newTotalPages }}? L\'operazione non è reversibile.') && $wire.resizePages(true)"
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
                                <strong>{{ $log->user->name }}</strong>
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

                <div class="flex items-center gap-1.5 text-xs">
                    <label for="ad-threshold-{{ $issue->id }}" class="text-gray-500 dark:text-gray-400">Soglia allarme</label>
                    <input
                        id="ad-threshold-{{ $issue->id }}"
                        type="number"
                        min="0"
                        max="100"
                        step="0.5"
                        value="{{ $adThreshold }}"
                        placeholder="—"
                        wire:change="updateAdThreshold($event.target.value)"
                        class="w-16 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-1 py-0.5"
                    />
                    <span class="text-gray-400">%</span>
                </div>
            </div>
        </div>

        @php
            $overThreshold = $adThreshold !== null && $adLoad['adLoadPercentage'] > (float) $adThreshold;
        @endphp

        <div class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <span class="text-2xl font-semibold {{ $overThreshold ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-100' }}">
                {{ $adLoad['adLoadPercentage'] }}%
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                carico pubblicitario &middot;
                {{ $adLoad['adEquivalentPages'] }} pagine equiv. pubblicità su {{ $adLoad['totalPages'] }} totali &middot;
                {{ $adLoad['editorialEquivalentPages'] }} pagine equiv. editoriale
            </span>
            @if ($overThreshold)
                <span class="text-xs font-medium text-red-600 dark:text-red-400">⚠️ Sopra soglia ({{ $adThreshold }}%)</span>
            @endif
        </div>

        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
            <span>Inserzioni assegnate: <strong>{{ $adLoad['assignedAdCount'] }}</strong></span>
            <span>Pubblicità non ancora assegnate: <strong>{{ $unassignedAdCount }}</strong></span>
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

    @error('locked')
        <p class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg px-3 py-2">🔒 {{ $message }}</p>
    @enderror

    <template x-if="! $store.realtimeFallback.connected">
        <p class="text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2">
            ⚠️ Aggiornamento in tempo reale non disponibile — la pagina si aggiorna automaticamente ogni pochi secondi.
        </p>
    </template>

    @can('update', $issue)
        <livewire:timone.content-create :issue="$issue" :key="'content-create-'.$issue->id" />
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
    </div>

    @if ($pages->isEmpty())
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700">
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <p class="font-medium">Nessuna pagina per questo numero.</p>
                <p class="text-sm mt-1">Imposta il numero di pagine totali per generare il timone.</p>
            </div>
        </div>
    @elseif ($viewMode === 'griglia')
        <div
            x-sortable="$wire.swapMode"
            @page-dropped="$wire.movePage($event.detail.pageId, $event.detail.newPosition)"
            class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3"
        >
            @foreach ($pages as $page)
                @include('livewire.timone.partials.page-card', ['page' => $page, 'dropEnabled' => true])
            @endforeach
        </div>
    @elseif ($viewMode === 'doppia')
        {{-- Niente Sortable qui: le pagine sono annidate in due livelli
             (apertura > pagina), Sortable richiede figli diretti dello
             stesso contenitore per il drag&drop di riordino. La modalità
             scambio (click, non drag) non ha questo limite ed è quindi
             l'unico modo di riordinare le pagine da questa vista — oltre
             alle frecce da tastiera, già supportate su ogni card. Il resto
             (assegnazione contenuti, cambio stato) è invece pienamente
             interattivo come nelle altre due modalità (dropEnabled true). --}}
        <div class="space-y-3">
            @foreach ($spreads as $spread)
                <div class="flex justify-center gap-0.5">
                    @foreach ($spread as $page)
                        <div class="w-40 sm:w-48 {{ ! $loop->first ? 'border-l-2 border-gray-300 dark:border-gray-600' : '' }}">
                            @include('livewire.timone.partials.page-card', ['page' => $page, 'dropEnabled' => true])
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <ul
                x-sortable="$wire.swapMode"
                @page-dropped="$wire.movePage($event.detail.pageId, $event.detail.newPosition)"
                class="divide-y divide-gray-100 dark:divide-gray-700"
            >
                @foreach ($pages as $page)
                    @include('livewire.timone.partials.page-row', ['page' => $page, 'dropEnabled' => true])
                @endforeach
            </ul>
        </div>
    @endif
</div>
