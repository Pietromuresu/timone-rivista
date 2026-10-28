{{-- Un contenuto dentro una pagina (card su miniatura, card senza
     miniatura, riga della vista lista). Estratto nella Fase 3
     (2026-10-27): era copiato identico in tre punti, e il passaggio da
     percentuale libera a dodicesimi andava fatto in tutti e tre.
     $variant: 'overlay' (testo bianco sulla miniatura), 'card', 'row'.
     $compact (Fase 7a): dentro un blocco della disposizione — solo il
     nome; i comandi sono nel menu «⋯» del blocco (revisione 2, Fase 1,
     bug 3: vedi partials/content-controls).
     Lo spazio su questa pagina si sceglie da una select 1/12 … intera
     (Grid::updateContentOccupancy()), mai un numero libero. --}}
@php
    $twelfths = (int) $content->pivot->occupied_twelfths;
    $isRow = $variant === 'row';
    $compact = $compact ?? false;
    // Fase 5: pubblicità segnaposto — contorno tratteggiato + etichetta
    // "Da assegnare" (riconoscibile anche senza colori).
    $isPlaceholder = $content->isPlaceholder();
@endphp
<{{ $isRow ? 'span' : 'div' }}
    wire:key="page-{{ $page->id }}-content-{{ $content->id }}"
    class="{{ $isRow ? 'inline-flex items-center gap-1 text-sm text-gray-700 dark:text-gray-200' : 'flex items-start gap-1 leading-tight'.($compact ? ' flex-wrap' : '').($variant === 'overlay' ? ' text-white' : '') }} {{ $isPlaceholder ? 'rounded border border-dashed border-amber-500 px-0.5' : '' }}"
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
        {{-- Fase 4.1: niente rinomina per chi non può cambiare il contenuto. --}}
        @php($canRename = ! $locked && (! isset($abilities) || $abilities->canChangeContent($content)))
        <span x-show="! editing" @if ($canRename) x-on:dblclick.stop="editing = true; $nextTick(() => $refs.input.select())" title="Doppio click per rinominare" @endif data-testid="content-label">
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
        {{-- Revisione 2, Fase 1, bug 3 (2026-10-27): nei blocchi della
             disposizione ($compact) i comandi non stanno più qui dentro
             ma nel menu «⋯» del blocco (page-card), che si apre sopra
             tutto: qui sarebbero tagliati dall'overflow dei blocchi
             piccoli. Nelle altre varianti restano in riga. --}}
        @unless ($compact)
            <span class="contents" data-testid="content-controls">
                @include('livewire.timone.partials.content-controls')
            </span>
        @endunless
    @endif
</{{ $isRow ? 'span' : 'div' }}>
