{{-- Un contenuto dentro una pagina (card su miniatura, card senza
     miniatura, riga della vista lista). Estratto nella Fase 3
     (2026-10-27): era copiato identico in tre punti, e il passaggio da
     percentuale libera a dodicesimi andava fatto in tutti e tre.
     $variant: 'overlay' (testo bianco sulla miniatura), 'card', 'row'.
     Lo spazio su questa pagina si sceglie da una select 1/12 … intera
     (Grid::updateContentOccupancy()), mai un numero libero. --}}
@php
    $twelfths = (int) $content->pivot->occupied_twelfths;
    $isRow = $variant === 'row';
    // Fase 5: pubblicità segnaposto — contorno tratteggiato + etichetta
    // "Da assegnare" (riconoscibile anche senza colori).
    $isPlaceholder = $content->isPlaceholder();
    $muted = $variant === 'overlay' ? 'text-white/80 hover:text-white' : 'opacity-60 hover:opacity-100';
    $selectClasses = $isRow
        ? 'text-xs py-0 pl-1 pr-6'
        : 'text-[10px] py-0 pl-0.5 pr-4';
@endphp
<{{ $isRow ? 'span' : 'div' }}
    wire:key="page-{{ $page->id }}-content-{{ $content->id }}"
    class="{{ $isRow ? 'inline-flex items-center gap-1 text-sm text-gray-700 dark:text-gray-200' : 'flex items-start gap-1 leading-tight'.($variant === 'overlay' ? ' text-white' : '') }} {{ $isPlaceholder ? 'rounded border border-dashed border-amber-500 px-0.5' : '' }}"
    @if ($isPlaceholder) data-placeholder="1" @endif
>
    {{-- Fase 4 (2026-10-27): rinomina inline — doppio click sul nome →
         campo di testo (✏️ più sotto apre invece la modale di modifica
         completa); Invio salva (Grid::renameContent()),
         Esc o uscita dal campo annullano. Stato solo nel browser finché non
         si salva: il nome vero arriva dal server al re-render. --}}
    <span
        class="{{ $isRow ? '' : 'break-words min-w-0 flex-1' }}"
        x-data="{ editing: false, name: @js($content->displayLabel()) }"
    >
        <span x-show="! editing" @unless ($locked) x-on:dblclick.stop="editing = true; $nextTick(() => $refs.input.select())" title="Doppio click per rinominare" @endunless data-testid="content-label">
            {{ $content->type->value === 'articolo' ? '📄' : '📢' }} {{ $content->displayLabel() }}
            @if ($isPlaceholder)
                <span class="ml-0.5 inline-block rounded border border-dashed border-amber-600 bg-amber-50 text-amber-900 px-1 text-[9px] font-semibold uppercase tracking-wide" title="Pubblicità prevista, ancora senza inserzionista né PDF">Da assegnare</span>
            @endif
        </span>
        @unless ($locked)
            <input
                x-show="editing"
                x-cloak
                x-ref="input"
                x-model="name"
                type="text"
                aria-label="Nuovo nome del contenuto"
                x-on:click.stop
                x-on:keydown.stop
                x-on:keydown.enter.prevent="editing = false; $wire.renameContent({{ $content->id }}, name)"
                x-on:keydown.escape.prevent="editing = false; name = @js($content->displayLabel())"
                x-on:blur="if (editing) { editing = false; name = @js($content->displayLabel()) }"
                class="w-full min-w-[6rem] rounded border-gray-300 dark:border-gray-600 bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100 px-1 py-0 {{ $isRow ? 'text-sm' : 'text-[10px]' }}"
            />
        @endunless
        @if ($content->pages->count() > 1)
            <span x-show="! editing" class="{{ $variant === 'overlay' ? 'opacity-70' : 'opacity-60' }}" title="Contenuto presente su {{ $content->pages->count() }} pagine">×{{ $content->pages->count() }}</span>
        @endif
    </span>
    @if ($locked)
        <span class="shrink-0 {{ $isRow ? 'text-xs' : 'text-[10px]' }} opacity-70" title="Spazio occupato su questa pagina">{{ \App\Support\Twelfths::fractionLabel($twelfths) }}</span>
    @else
        <select
            x-on:keydown.stop
            x-on:click.stop
            x-on:focus="$store.pagePresence?.startEditing({{ $page->id }})"
            x-on:blur="$store.pagePresence?.stopEditing({{ $page->id }})"
            wire:change="updateContentOccupancy({{ $page->id }}, {{ $content->id }}, parseInt($event.target.value))"
            title="Spazio occupato su questa pagina"
            aria-label="Spazio di {{ $content->displayLabel() }} su questa pagina"
            class="shrink-0 rounded border-gray-300 dark:border-gray-600 bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100 {{ $selectClasses }}"
        >
            @foreach (range(1, \App\Support\Twelfths::PER_PAGE) as $option)
                <option value="{{ $option }}" @selected($option === $twelfths)>{{ $option === \App\Support\Twelfths::PER_PAGE ? 'intera' : \App\Support\Twelfths::fractionLabel($option) }}</option>
            @endforeach
        </select>
        <button
            type="button"
            x-on:click.stop="$dispatch('open-content-edit', { contentId: {{ $content->id }} })"
            class="shrink-0 {{ $isRow ? 'text-xs' : 'text-[10px]' }} {{ $muted }}"
            title="Modifica il contenuto (rinomina: doppio click sul nome)"
            aria-label="Modifica {{ $content->displayLabel() }}"
        >✏️</button>
        <button
            type="button"
            x-on:click.stop="const pos = prompt('Estendi anche alla pagina numero:'); if (pos) $wire.extendToPage({{ $content->id }}, parseInt(pos))"
            class="shrink-0 {{ $isRow ? 'text-xs' : 'text-[10px]' }} {{ $muted }}"
            title="Estendi questo contenuto a un'altra pagina"
        >↗</button>
        <button
            type="button"
            x-on:click.stop="$wire.unassignContent({{ $content->id }}, {{ $page->id }})"
            class="shrink-0 {{ $isRow ? 'text-xs' : 'text-[10px]' }} {{ $muted }} {{ $variant === 'overlay' ? 'hover:text-red-400' : 'hover:text-red-600' }}"
            title="Rimuovi"
        >✕</button>
    @endif
</{{ $isRow ? 'span' : 'div' }}>
