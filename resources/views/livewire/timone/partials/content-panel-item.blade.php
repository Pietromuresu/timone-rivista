{{-- Una voce del pannello contenuti (2026-10-28, sostituisce il chip di
     "Contenuti da assegnare"). Si trascina su una pagina o una copertina come
     il chip di prima (text/content-id → Grid::assignContent()), oppure si
     assegna senza risalire: «Assegna alla selezione»
     (Grid::assignContentToSelection()) o «Vai a pag.»
     (Grid::assignContentToTarget()). Tutte e tre passano da
     Grid::placeUnassignedContent() → ContentPlacer, con gli stessi controlli.

     - Solo i contenuti NON in pagina si trascinano e si assegnano; nella
       scheda «Tutti» quelli già in pagina mostrano dove sono (scelta
       dell'utente: si spostano con frecce, «Posizione» o drag sulla pagina).
     - Il Commerciale non trascina né assegna articoli (cursore not-allowed e
       motivo nel tooltip; il server rifiuta comunque, ContentPolicy).
     - files: ->latest()->limit(1) da Grid::render(), solo l'ultimo file. --}}
@php
    $latestContentFile = $content->files->first();
    $canChange = ! isset($abilities) || $abilities->canChangeContent($content);
    $placement = \App\Support\ContentPanelFilter::placementLabel($content);
    $isFree = $placement === null;
    $draggable = $canChange && $isFree;
    $occupancy = $content->occupancy()?->label();
    $hasSelection = ! empty($selectedPageIds ?? []);
@endphp
@if ($confirmDeleteContentId === $content->id)
    {{-- Fase 4.3: secondo passaggio della conferma eliminazione, al posto della voce. --}}
    <div wire:key="content-{{ $content->id }}-confirm" class="flex items-center gap-1.5 px-2 py-1.5 rounded border border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-900/20 text-xs">
        <span class="truncate">Eliminare «{{ $content->displayLabel() }}»?</span>
        <button type="button" wire:click="deleteUnassignedContent({{ $content->id }}, true)" wire:confirm="Eliminare il contenuto? Si può annullare con Ctrl/⌘+Z per 24 ore." class="shrink-0 px-1.5 py-0.5 rounded bg-red-600 text-white">Conferma</button>
        <button type="button" wire:click="cancelDeleteContent" class="shrink-0 px-1.5 py-0.5 rounded border border-gray-300 dark:border-gray-600">Annulla</button>
    </div>
@else
    <div
        wire:key="content-{{ $content->id }}"
        data-testid="content-panel-item"
        data-content-id="{{ $content->id }}"
        @if ($draggable)
            draggable="true"
            x-on:dragstart="$event.dataTransfer.setData('text/content-id', '{{ $content->id }}')"
        @elseif (! $canChange)
            data-not-movable="1"
        @endif
        class="group rounded border {{ $content->isPlaceholder() ? 'border-dashed border-amber-500' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-800 px-2 py-1.5 text-xs {{ $draggable ? 'cursor-grab' : ($canChange ? '' : 'cursor-not-allowed opacity-70') }}"
        title="{{ $content->displayLabel() }}{{ $canChange ? '' : ' — '.\App\Support\Permissions\TimoneAbilities::EDITORIAL_ONLY }}"
    >
        <div class="flex items-center gap-1.5 min-w-0">
            @if ($content->previewImageUrl())
                <img src="{{ $content->previewImageUrl() }}" alt="" class="w-6 h-8 object-cover rounded-sm shrink-0 border border-gray-200 dark:border-gray-700" />
            @elseif ($latestContentFile?->thumbnail_status === \App\Enums\ThumbnailStatus::Failed)
                <span class="shrink-0 w-6 text-center" title="Anteprima non generata">⚠️</span>
            @elseif ($latestContentFile !== null)
                <span class="shrink-0 w-6 text-center" title="Anteprima in elaborazione">⏳</span>
            @else
                <span class="shrink-0 w-6 text-center">{{ $content->type->value === 'articolo' ? '📄' : '📢' }}</span>
            @endif

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1 min-w-0">
                    <span class="truncate font-medium text-gray-800 dark:text-gray-100">{{ $content->displayLabel() }}</span>
                    @if ($content->isPlaceholder())
                        <span class="shrink-0 rounded border border-dashed border-amber-600 px-1 text-[9px] font-semibold uppercase text-amber-900 dark:text-amber-200">Segnaposto</span>
                    @endif
                </div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    {{ $content->type->label() }}
                    @if ($occupancy) · {{ $occupancy }} @endif
                    @if ($content->type->value === 'pubblicita' && $content->advertisement) · {{ $content->advertisement->format->label() }} @endif
                    @if ($placement) · <span class="text-indigo-700 dark:text-indigo-300">{{ $placement }}</span> @endif
                </div>
            </div>

            <span class="flex items-center gap-0.5 shrink-0">
                @if ($latestContentFile)
                    <a href="{{ route('content-files.show', $latestContentFile) }}" target="_blank" rel="noopener" draggable="false" title="Apri il file ({{ $latestContentFile->original_name }})" class="px-1 text-gray-400 hover:text-indigo-600" data-testid="content-panel-eye">👁</a>
                @endif
                @if ($canChange)
                    <button type="button" x-on:click="$dispatch('open-content-edit', { contentId: {{ $content->id }} })" title="Modifica" class="px-1 text-gray-400 hover:text-indigo-600">✏️</button>
                    @if ($isFree)
                        <button type="button" wire:click="archiveContent({{ $content->id }})" title="Archivia" class="px-1 text-gray-400 hover:text-amber-600 hidden group-hover:inline">🗄️</button>
                        <button type="button" wire:click="requestDeleteContent({{ $content->id }})" title="Elimina (annullabile con Ctrl/⌘+Z per 24 ore)" class="px-1 text-gray-400 hover:text-red-600 hidden group-hover:inline">🗑️</button>
                    @endif
                @endif
            </span>
        </div>

        @if ($draggable)
            <div class="mt-1 flex items-center gap-1.5" x-data="{ target: '' }">
                @if ($hasSelection)
                    <button type="button" wire:click="assignContentToSelection({{ $content->id }})" class="px-1.5 py-0.5 rounded border border-indigo-300 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/40 whitespace-nowrap" title="Assegna partendo dalla prima pagina selezionata (se occupa più pagine, prende anche le seguenti)" data-testid="assign-to-selection">Assegna alla selezione</button>
                @endif
                <form class="flex items-center gap-1 ml-auto" x-on:submit.prevent="if (target.trim() !== '') { $wire.assignContentToTarget({{ $content->id }}, target); target = ''; }">
                    <label class="text-gray-500 dark:text-gray-400 whitespace-nowrap" for="go-to-{{ $content->id }}">Vai a pag.</label>
                    <input id="go-to-{{ $content->id }}" x-model="target" type="text" inputmode="text" placeholder="12, I…" class="w-14 rounded border-gray-300 dark:border-gray-600 text-xs py-0 px-1" data-testid="assign-to-target" />
                    <button type="submit" class="px-1 text-indigo-700 dark:text-indigo-300" title="Assegna partendo da questa pagina (numero, oppure I, II, III, IV per le copertine)">⏎</button>
                </form>
            </div>
        @endif
    </div>
@endif
