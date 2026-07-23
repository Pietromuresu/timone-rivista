@php
    $latestFile = $page->files->first();
@endphp
<div class="relative flex flex-col aspect-[3/4] rounded-lg border-2 p-2 text-xs overflow-hidden {{ $page->content_type->colorClasses() }}">
    <div class="flex items-center justify-between gap-1">
        <span class="font-semibold text-sm">{{ $page->position }}</span>
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $page->status->colorClasses() }}">
            {{ $page->status->label() }}
        </span>
    </div>

    <div class="flex-1 flex flex-col justify-center gap-1 mt-1 min-h-0">
        @forelse ($page->contents as $content)
            <div class="truncate leading-tight">
                <span>{{ $content->type->value === 'articolo' ? '📄' : '📢' }}</span>
                {{ $content->displayLabel() }}
                @if ($page->contents->count() > 1)
                    <span class="text-[10px] opacity-70">({{ rtrim(rtrim(number_format($content->pivot->occupied_percentage, 1), '0'), '.') }}%)</span>
                @endif
            </div>
        @empty
            <p class="italic opacity-60 text-center leading-tight">
                {{ $page->content_type->value === 'bianca' ? 'Pagina bianca' : 'Da assegnare' }}
            </p>
        @endforelse
    </div>

    <div class="flex items-center justify-between gap-1 mt-1 text-[10px] opacity-70">
        @if ($latestFile)
            <span title="{{ $latestFile->original_name }}">📎 PDF</span>
        @else
            <span></span>
        @endif

        @if ($page->notes)
            <span title="{{ $page->notes }}">📝</span>
        @endif
    </div>
</div>
