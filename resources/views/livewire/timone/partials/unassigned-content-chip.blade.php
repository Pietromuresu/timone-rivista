<div
    wire:key="content-{{ $content->id }}"
    draggable="true"
    x-on:dragstart="$event.dataTransfer.setData('text/content-id', '{{ $content->id }}')"
    class="flex items-center gap-1.5 px-2 py-1.5 rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs cursor-grab max-w-[220px]"
    title="{{ $content->displayLabel() }}"
>
    <span>{{ $content->type->value === 'articolo' ? '📄' : '📢' }}</span>
    <span class="truncate">{{ $content->displayLabel() }}</span>
    @if ($content->type->value === 'pubblicita' && $content->advertisement)
        <span class="text-[10px] opacity-60 shrink-0">{{ $content->advertisement->format->label() }}</span>
    @endif
</div>
