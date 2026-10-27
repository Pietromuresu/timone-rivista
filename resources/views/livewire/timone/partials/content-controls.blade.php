{{-- Comandi di un contenuto su una pagina: spazio occupato, ordine,
     modifica, estendi, rimuovi. Estratti da page-content-entry nella
     revisione 2, Fase 1, bug 3 (2026-10-27) per usarli in due posti con lo
     stesso markup:
     - in riga, accanto al nome ($variant 'overlay', 'card', 'row');
     - nel menu «⋯» dei blocchi della disposizione ($variant 'popover'),
       teleportato nel <body> (page-card): dentro il blocco, con
       overflow-hidden, i comandi dei blocchi piccoli (1/4, mezza
       orizzontale) venivano tagliati e la select dello spazio non era
       raggiungibile. Nel popover ogni comando ha anche il testo e la
       scelta chiude il menu ($closeMenu).
     Richiede $page, $content, $variant. --}}
@php
    $twelfths = (int) $content->pivot->occupied_twelfths;
    $isRow = $variant === 'row';
    $isMenu = $variant === 'popover';
    $closeMenu = $isMenu ? 'menuOpen = false; ' : '';
    $muted = match ($variant) {
        'overlay' => 'text-white/80 hover:text-white',
        'popover' => '',
        default => 'opacity-60 hover:opacity-100',
    };
    $buttonClasses = $isMenu
        ? 'flex w-full items-center gap-2 rounded px-2 py-1 text-left text-xs hover:bg-gray-100 dark:hover:bg-gray-700'
        : 'shrink-0 '.($isRow ? 'text-xs' : 'text-[10px]').' '.$muted;
    $selectClasses = match (true) {
        $isMenu => 'text-xs py-0.5 pl-1 pr-6',
        $isRow => 'text-xs py-0 pl-1 pr-6',
        default => 'text-[10px] py-0 pl-0.5 pr-4',
    };
@endphp
@if ($isMenu)
    <label class="flex items-center justify-between gap-2 px-2 py-1 text-xs">
        <span>Spazio su questa pagina</span>
@endif
<select
    x-on:keydown.stop
    x-on:click.stop
    x-on:focus="$store.pagePresence?.startEditing({{ $page->id }})"
    x-on:blur="$store.pagePresence?.stopEditing({{ $page->id }})"
    wire:change="updateContentOccupancy({{ $page->id }}, {{ $content->id }}, parseInt($event.target.value))"
    @if ($isMenu) x-on:change="menuOpen = false" @endif
    title="Spazio occupato su questa pagina"
    aria-label="Spazio di {{ $content->displayLabel() }} su questa pagina"
    class="shrink-0 rounded border-gray-300 dark:border-gray-600 bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100 {{ $selectClasses }}"
>
    @foreach (range(1, \App\Support\Twelfths::PER_PAGE) as $option)
        <option value="{{ $option }}" @selected($option === $twelfths)>{{ $option === \App\Support\Twelfths::PER_PAGE ? 'intera' : \App\Support\Twelfths::fractionLabel($option) }}</option>
    @endforeach
</select>
@if ($isMenu)
    </label>
@endif
{{-- Revisione 2, Fase 2.3 (2026-10-27; sostituisce ↑/↓ della Fase 7b):
     frecce nelle 4 direzioni sulla griglia 6×6 e rotazione
     (Grid::moveContentOnGrid()/rotateContentOnGrid()). Ogni freccia è
     attiva solo se la mossa è possibile (Page::layout() → PageGridPlanner);
     il server ricontrolla comunque. Un contenuto a L o oltre capienza non
     ha frecce: si adatta allo spazio lasciato dagli altri. --}}
@php
    $gridBlock = $page->layoutBlockFor($content);
    $arrows = [
        'left' => ['←', 'Sposta a sinistra'],
        'up' => ['↑', 'Sposta in alto'],
        'down' => ['↓', 'Sposta in basso'],
        'right' => ['→', 'Sposta a destra'],
    ];
@endphp
@if ($gridBlock && $gridBlock['kind'] === 'rect')
    <span class="{{ $isMenu ? 'flex items-center gap-1 px-2 py-1 text-xs' : 'contents' }}" data-testid="grid-arrows">
        @if ($isMenu)
            <span class="mr-auto">Posizione</span>
        @endif
        @foreach ($arrows as $direction => [$symbol, $label])
            <button
                type="button"
                x-on:click.stop="$wire.moveContentOnGrid({{ $page->id }}, {{ $content->id }}, '{{ $direction }}')"
                @disabled(! $gridBlock['moves'][$direction])
                class="{{ $isMenu ? 'h-6 w-6 rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700' : 'shrink-0 '.($isRow ? 'text-xs' : 'text-[10px]').' '.$muted }} disabled:opacity-30 disabled:cursor-not-allowed"
                title="{{ $label }}{{ $gridBlock['moves'][$direction] ? '' : ' (non possibile: fuori pagina o posto occupato)' }}"
                aria-label="{{ $label }} {{ $content->displayLabel() }}"
                data-testid="move-content-{{ $direction }}"
            >{{ $symbol }}</button>
        @endforeach
    </span>
    <button
        type="button"
        x-on:click.stop="{{ $closeMenu }}$wire.rotateContentOnGrid({{ $page->id }}, {{ $content->id }})"
        @disabled(! $gridBlock['canRotate'])
        class="{{ $buttonClasses }} disabled:opacity-30 disabled:cursor-not-allowed"
        title="{{ $gridBlock['canRotate'] ? 'Ruota (verticale ↔ orizzontale)' : 'Non si può ruotare: è quadrato o ruotato non ci sta' }}"
        aria-label="Ruota {{ $content->displayLabel() }}"
        data-testid="rotate-content"
    >⟳@if ($isMenu) <span>Ruota (verticale ↔ orizzontale)</span>@endif</button>
@elseif ($gridBlock && $isMenu)
    <p class="px-2 py-1 text-[11px] text-gray-500 dark:text-gray-400">{{ $gridBlock['kind'] === 'fluid' ? 'Forma a L: occupa lo spazio lasciato libero dagli altri contenuti, non si sposta con le frecce.' : 'Non ci sta sulla pagina (oltre capienza): riduci lo spazio di un contenuto.' }}</p>
@endif
{{-- Revisione 2, Fase 2.1 (2026-10-27): occhio = apre il file del
     contenuto (la pagina del PDF che corrisponde a questa pagina del timone)
     in una nuova scheda. Rotta content-files.show: auth + Gate view sul
     numero, file servito inline. Il click sull'anteprima ora seleziona la
     pagina, quindi questo è l'unico modo di aprire il file. Senza file:
     bottone disattivato con il motivo nel tooltip. --}}
@php($eyeFile = $page->previewFileFor($content))
@if ($eyeFile)
    <a
        href="{{ route('content-files.show', $eyeFile).($eyeFile->pdf_page_number > 1 ? '#page='.$eyeFile->pdf_page_number : '') }}"
        target="_blank"
        rel="noopener"
        x-on:click.stop="{{ $closeMenu }}"
        class="{{ $buttonClasses }}"
        title="Apri il file in una nuova scheda ({{ $eyeFile->original_name }})"
        aria-label="Apri il file di {{ $content->displayLabel() }}"
        data-testid="open-content-file"
    >👁@if ($isMenu) <span>Apri il file</span>@endif</a>
@else
    {{-- aria-disabled e non disabled: un bottone disabled in alcuni browser
         non mostra il tooltip, che qui è l'informazione utile. --}}
    <button
        type="button"
        aria-disabled="true"
        x-on:click.stop.prevent
        class="{{ $buttonClasses }} opacity-40 cursor-not-allowed"
        title="Nessun file caricato"
        aria-label="Nessun file caricato per {{ $content->displayLabel() }}"
        data-testid="open-content-file-disabled"
    >👁@if ($isMenu) <span>Nessun file caricato</span>@endif</button>
@endif
<button
    type="button"
    x-on:click.stop="{{ $closeMenu }}$dispatch('open-content-edit', { contentId: {{ $content->id }} })"
    class="{{ $buttonClasses }}"
    title="Modifica il contenuto (rinomina: doppio click sul nome)"
    aria-label="Modifica {{ $content->displayLabel() }}"
>✏️@if ($isMenu) <span>Modifica</span>@endif</button>
<button
    type="button"
    x-on:click.stop="{{ $closeMenu }}const pos = prompt('Estendi anche alla pagina numero:'); if (pos) $wire.extendToPage({{ $content->id }}, parseInt(pos))"
    class="{{ $buttonClasses }}"
    title="Estendi questo contenuto a un'altra pagina"
>↗@if ($isMenu) <span>Estendi a un'altra pagina</span>@endif</button>
<button
    type="button"
    x-on:click.stop="{{ $closeMenu }}$wire.unassignContent({{ $content->id }}, {{ $page->id }})"
    class="{{ $buttonClasses }} {{ match ($variant) { 'overlay' => 'hover:text-red-400', 'popover' => 'text-red-700 dark:text-red-400', default => 'hover:text-red-600' } }}"
    title="Rimuovi"
>✕@if ($isMenu) <span>Rimuovi dalla pagina</span>@endif</button>
