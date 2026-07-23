@php
    $latestFile = $page->files->first();
@endphp
<li class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50">
    <span class="w-8 shrink-0 text-right font-semibold text-gray-700 dark:text-gray-200">
        {{ $page->position }}
    </span>

    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium shrink-0 {{ $page->content_type->colorClasses() }}">
        {{ $page->content_type->label() }}
    </span>

    <div class="flex-1 min-w-0">
        @forelse ($page->contents as $content)
            <span class="inline-flex items-center gap-1 text-sm text-gray-700 dark:text-gray-200 mr-3">
                <span>{{ $content->type->value === 'articolo' ? '📄' : '📢' }}</span>
                {{ $content->displayLabel() }}
                @if ($page->contents->count() > 1)
                    <span class="text-xs text-gray-400">({{ rtrim(rtrim(number_format($content->pivot->occupied_percentage, 1), '0'), '.') }}%)</span>
                @endif
            </span>
        @empty
            <span class="text-sm text-gray-400 italic">
                {{ $page->content_type->value === 'bianca' ? 'Pagina bianca' : 'Da assegnare' }}
            </span>
        @endforelse
    </div>

    <span class="text-xs shrink-0" title="{{ $latestFile?->original_name }}">
        @if ($latestFile) 📎 PDF @endif
    </span>

    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium shrink-0 {{ $page->status->colorClasses() }}">
        {{ $page->status->label() }}
    </span>
</li>
