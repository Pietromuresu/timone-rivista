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

                    <button
                        type="button"
                        x-on:click="confirm('Ridurre le pagine a {{ $newTotalPages }}? L\'operazione non è reversibile.') && $wire.resizePages(true)"
                        class="mt-3 px-3 py-1.5 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700"
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

    <template x-if="! $store.realtimeFallback.connected">
        <p class="text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2">
            ⚠️ Aggiornamento in tempo reale non disponibile — la pagina si aggiorna automaticamente ogni pochi secondi.
        </p>
    </template>

    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 p-3">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Contenuti da assegnare ({{ $unassignedContents->count() }})
        </h4>

        @error('percentage')
            <p class="text-xs text-red-600 dark:text-red-400 mb-2">{{ $message }}</p>
        @enderror

        @if ($unassignedContents->isEmpty())
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
            x-sortable
            @page-dropped="$wire.movePage($event.detail.pageId, $event.detail.newPosition)"
            class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3"
        >
            @foreach ($pages as $page)
                @include('livewire.timone.partials.page-card', ['page' => $page, 'dropEnabled' => true])
            @endforeach
        </div>
    @elseif ($viewMode === 'doppia')
        <div class="space-y-3">
            @foreach ($spreads as $spread)
                <div class="flex justify-center gap-0.5">
                    @foreach ($spread as $page)
                        <div class="w-40 sm:w-48 {{ ! $loop->first ? 'border-l-2 border-gray-300 dark:border-gray-600' : '' }}">
                            @include('livewire.timone.partials.page-card', ['page' => $page, 'dropEnabled' => false])
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <ul
                x-sortable
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
