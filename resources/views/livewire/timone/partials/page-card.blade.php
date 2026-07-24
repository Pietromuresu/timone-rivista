@php
    $latestFile = $page->files->first();
    $dropEnabled = $dropEnabled ?? false;
@endphp
<div
    wire:key="page-{{ $page->id }}"
    data-page-id="{{ $page->id }}"
    tabindex="0"
    @keydown.arrow-left.prevent="$wire.movePage({{ $page->id }}, {{ $page->position - 1 }})"
    @keydown.arrow-right.prevent="$wire.movePage({{ $page->id }}, {{ $page->position + 1 }})"
    @if ($dropEnabled)
        x-on:dragover.prevent
        x-on:drop.prevent="const cid = $event.dataTransfer.getData('text/content-id'); if (cid) { $wire.assignContent(parseInt(cid), {{ $page->id }}); }"
    @endif
    class="relative flex flex-col aspect-[3/4] rounded-lg border-2 p-2 text-xs overflow-hidden focus:outline-none focus:ring-2 focus:ring-indigo-500 {{ $page->content_type->colorClasses() }}"
>
    <div class="flex items-center justify-between gap-1">
        <span class="drag-handle cursor-grab font-semibold text-sm select-none" title="Trascina per riordinare">
            ⠿ {{ $page->position }}
        </span>
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $page->status->colorClasses() }}">
            {{ $page->status->label() }}
        </span>
    </div>

    <div class="flex-1 flex flex-col justify-center gap-1 mt-1 min-h-0">
        @forelse ($page->contents as $content)
            <div wire:key="page-{{ $page->id }}-content-{{ $content->id }}" class="flex items-center gap-1 leading-tight">
                <span class="truncate flex-1">
                    {{ $content->type->value === 'articolo' ? '📄' : '📢' }} {{ $content->displayLabel() }}
                </span>
                <input
                    type="number"
                    min="0.1"
                    max="100"
                    step="0.1"
                    value="{{ $content->pivot->occupied_percentage }}"
                    x-on:keydown.stop
                    x-on:click.stop
                    wire:change="updateContentPercentage({{ $page->id }}, {{ $content->id }}, $event.target.value)"
                    class="w-9 shrink-0 text-[10px] rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 px-0.5 py-0"
                />
                <button
                    type="button"
                    x-on:click.stop="$wire.unassignContent({{ $content->id }}, {{ $page->id }})"
                    class="shrink-0 text-[10px] opacity-60 hover:opacity-100 hover:text-red-600"
                    title="Rimuovi"
                >✕</button>
            </div>
        @empty
            <p class="italic opacity-60 text-center leading-tight">
                {{ $page->content_type->value === 'bianca' ? 'Pagina bianca' : 'Da assegnare' }}
            </p>
        @endforelse
    </div>

    <div class="flex items-center justify-between gap-1 mt-1 text-[10px] opacity-70">
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
                title="{{ $latestFile->original_name }} — apri in una nuova scheda"
            >
                <img
                    src="{{ route('page-files.thumbnail', $latestFile) }}"
                    alt=""
                    class="w-6 h-8 object-cover rounded border border-gray-300 dark:border-gray-600"
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

        @if ($page->notes)
            <span title="{{ $page->notes }}">📝</span>
        @endif
    </div>
</div>
