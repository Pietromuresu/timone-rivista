@php
    $latestFile = $page->files->first();
    $dropEnabled = $dropEnabled ?? false;
@endphp
<li
    wire:key="page-{{ $page->id }}"
    data-page-id="{{ $page->id }}"
    tabindex="0"
    @keydown.arrow-up.prevent="$wire.movePage({{ $page->id }}, {{ $page->position - 1 }})"
    @keydown.arrow-down.prevent="$wire.movePage({{ $page->id }}, {{ $page->position + 1 }})"
    @if ($dropEnabled)
        x-on:dragover.prevent
        x-on:drop.prevent="const cid = $event.dataTransfer.getData('text/content-id'); if (cid) { $wire.assignContent(parseInt(cid), {{ $page->id }}); }"
    @endif
    class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
>
    <span class="drag-handle w-8 shrink-0 text-right font-semibold text-gray-700 dark:text-gray-200 cursor-grab" title="Trascina per riordinare">
        {{ $page->position }}
    </span>

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
            </span>
        @empty
            <span class="text-sm text-gray-400 italic">
                {{ $page->content_type->value === 'bianca' ? 'Pagina bianca' : 'Da assegnare' }}
            </span>
        @endforelse
    </div>

    <span class="text-xs shrink-0 flex items-center gap-1">
        <input
            type="file"
            id="page-file-input-{{ $page->id }}"
            wire:model="pendingUploads.{{ $page->id }}"
            accept="application/pdf"
            class="hidden"
        />

        @if (! $latestFile)
            <button
                type="button"
                x-on:click.stop="document.getElementById('page-file-input-{{ $page->id }}').click()"
                wire:loading.attr="disabled"
                wire:target="pendingUploads.{{ $page->id }}"
                class="opacity-70 hover:opacity-100"
                title="Carica PDF"
            >📤</button>
        @elseif ($latestFile->thumbnail_status === \App\Enums\ThumbnailStatus::Ready)
            <a
                href="{{ route('page-files.show', $latestFile) }}"
                target="_blank"
                rel="noopener"
                x-on:click.stop
                class="flex items-center gap-1"
                title="{{ $latestFile->original_name }} — apri in una nuova scheda"
            >
                <img
                    src="{{ route('page-files.thumbnail', $latestFile) }}"
                    alt=""
                    class="w-5 h-6 object-cover rounded border border-gray-300 dark:border-gray-600"
                />
            </a>
        @elseif (in_array($latestFile->thumbnail_status, [\App\Enums\ThumbnailStatus::Pending, \App\Enums\ThumbnailStatus::Processing]))
            <span title="Elaborazione thumbnail in corso...">⏳ PDF</span>
        @else
            <button
                type="button"
                x-on:click.stop="document.getElementById('page-file-input-{{ $page->id }}').click()"
                class="text-red-600"
                title="Generazione thumbnail fallita — clic per ricaricare"
            >⚠️ PDF</button>
        @endif

        <span wire:loading wire:target="pendingUploads.{{ $page->id }}" class="italic">caricamento...</span>

        @if ($latestFile)
            <button
                type="button"
                x-on:click.stop="$dispatch('open-modal', 'page-file-history'); $dispatch('show-file-history', { pageId: {{ $page->id }} })"
                class="opacity-70 hover:opacity-100"
                title="Storico PDF caricati"
            >🕐</button>
        @endif
    </span>

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
</li>
