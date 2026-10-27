@php
    // Fase 4.1: files() è già caricata (->latest()) da Grid::render(), qui
    // si guarda solo la più recente — stesso principio "solo l'ultima è
    // mostrata" già usato per page_files (Punto 1bis), nessuna UI per
    // sfogliare allegati precedenti dello stesso contenuto.
    $latestContentFile = $content->files->first();
@endphp
@if ($confirmDeleteContentId === $content->id)
    {{-- Fase 4.3: secondo passaggio della conferma eliminazione, sostituisce
         il chip normale al posto di un modale — stesso principio "avviso
         inline prima di un'azione distruttiva" già usato dal pannello
         pagine totali, qui più leggero perché riguarda un solo contenuto. --}}
    <div wire:key="content-{{ $content->id }}-confirm" class="flex items-center gap-1.5 px-2 py-1.5 rounded border border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-900/20 text-xs max-w-[260px]">
        <span class="truncate">Eliminare «{{ $content->displayLabel() }}»?</span>
        <button type="button" wire:click="deleteUnassignedContent({{ $content->id }}, true)" wire:confirm="Eliminazione definitiva, non recuperabile. Continuare?" class="shrink-0 px-1.5 py-0.5 rounded bg-red-600 text-white">Conferma</button>
        <button type="button" wire:click="cancelDeleteContent" class="shrink-0 px-1.5 py-0.5 rounded border border-gray-300 dark:border-gray-600">Annulla</button>
    </div>
@else
    <div
        wire:key="content-{{ $content->id }}"
        draggable="true"
        x-on:dragstart="$event.dataTransfer.setData('text/content-id', '{{ $content->id }}')"
        class="group flex items-center gap-1.5 px-2 py-1.5 rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs cursor-grab max-w-[220px]"
        title="{{ $content->displayLabel() }}"
    >
        @if ($content->previewImageUrl())
            <img src="{{ $content->previewImageUrl() }}" alt="" class="w-6 h-8 object-cover rounded-sm shrink-0 border border-gray-200 dark:border-gray-700" />
        @elseif ($latestContentFile?->thumbnail_status === \App\Enums\ThumbnailStatus::Failed)
            <span class="shrink-0" title="Anteprima non generata">⚠️</span>
        @elseif ($latestContentFile !== null)
            <span class="shrink-0" title="Anteprima in elaborazione">⏳</span>
        @else
            <span>{{ $content->type->value === 'articolo' ? '📄' : '📢' }}</span>
        @endif
        <span class="truncate">{{ $content->displayLabel() }}</span>
        @if ($content->type->value === 'pubblicita' && $content->advertisement)
            <span class="text-[10px] opacity-60 shrink-0">{{ $content->advertisement->format->label() }}</span>
        @endif
        @can('update', $issue)
            <span class="hidden group-hover:flex items-center gap-0.5 shrink-0 ml-auto">
                <button type="button" x-on:click="$dispatch('open-content-edit', { contentId: {{ $content->id }} })" title="Modifica" class="px-1 text-gray-400 hover:text-indigo-600">✏️</button>
                <button type="button" wire:click="archiveContent({{ $content->id }})" title="Archivia" class="px-1 text-gray-400 hover:text-amber-600">🗄️</button>
                <button type="button" wire:click="requestDeleteContent({{ $content->id }})" title="Elimina definitivamente" class="px-1 text-gray-400 hover:text-red-600">🗑️</button>
            </span>
        @endcan
    </div>
@endif
