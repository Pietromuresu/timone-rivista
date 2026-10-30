{{-- Azioni sulla selezione di pagine, nella fascia sticky (2026-10-28): prima
     erano un pannello nel flusso della pagina e, selezionando pagine in fondo
     al timone, bisognava tornare su. Stessi metodi Livewire e stessi
     controlli di permesso di prima (Grid::bulkChangeStatus(),
     bulkToggleLock(), linkSelectedPages(), unlinkSelectedFromGroup(),
     selectAllPages(), clearSelection()); cambia solo dove stanno.

     - Una riga sola: compare con la modalità selezione; senza pagine
       selezionate mostra un testo grigio e le azioni disattivate.
     - Le azioni non disponibili non spariscono: aria-disabled (non
       disabled, che in alcuni browser nasconde il tooltip) con il motivo nel
       tooltip, da App\Support\PageSelectionActions ($selection). Così i
       bottoni non cambiano posto da una selezione all'altra.
     - Sotto i 1100 px Blocca, Sblocca e Scollega vanno nel menu «Altro ⋯».
     - Il Commerciale vede solo seleziona/deseleziona: le altre azioni sono
       della redazione (TimoneAbilities::editsTimone()); il server le rifiuta
       comunque.
     - Scorciatoie (in grid.blade.php, mai con il focus in un campo): Esc
       deseleziona, Ctrl/⌘+A seleziona tutte, Ctrl/⌘+Shift+G collega o
       scollega. Esiti e errori delle azioni (pagina bloccata, gruppo) sono
       mostrati qui sotto, dentro la fascia: visibili ovunque si sia nel
       timone. --}}
@php
    $actionClass = 'px-2 py-0.5 rounded border border-indigo-300 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 whitespace-nowrap';
    $enabledClass = 'hover:bg-indigo-100 dark:hover:bg-indigo-800/50';
    $disabledClass = 'opacity-40 cursor-not-allowed';
    $reasons = $selection['actions'];
    $canEdit = $abilities->editsTimone();
    // Bottone di un'azione: attivo con wire:click, oppure aria-disabled con il motivo.
    $button = function (string $key, string $call, string $label, string $title) use ($reasons, $actionClass, $enabledClass, $disabledClass) {
        $reason = $reasons[$key];

        return $reason === null
            ? '<button type="button" wire:click="'.e($call).'" class="'.$actionClass.' '.$enabledClass.'" title="'.e($title).'" data-action="'.$key.'">'.e($label).'</button>'
            : '<button type="button" aria-disabled="true" class="'.$actionClass.' '.$disabledClass.'" title="'.e($reason).'" data-action="'.$key.'" data-disabled="1">'.e($label).'</button>';
    };
@endphp

@if ($selectionMode)
    <div class="flex items-center gap-1.5 text-xs border-t border-gray-100 dark:border-gray-700 pt-1 min-w-0 max-sm:flex-wrap" data-testid="selection-bar">
        @if ($selection['count'] > 0)
            <span class="font-medium text-indigo-700 dark:text-indigo-300 whitespace-nowrap truncate" data-testid="selection-label" title="{{ $selection['label'] }}: {{ $selection['positions'] }}">
                ☑️ {{ $selection['label'] }}: {{ $selection['positions'] }}
            </span>
        @else
            <span class="text-gray-400 dark:text-gray-500 whitespace-nowrap" data-testid="selection-label">Seleziona una o più pagine (clic, Ctrl/⌘+clic)</span>
        @endif

        <x-manual-help slug="selezione-multipla" label="Selezione multipla" />

        <button type="button" wire:click="clearSelection" class="{{ $actionClass }} {{ $enabledClass }}" title="Deseleziona tutte (Esc)" data-action="clear">Deseleziona</button>
        <button type="button" wire:click="selectAllPages" class="{{ $actionClass }} {{ $enabledClass }}" title="Seleziona tutte le pagine interne (Ctrl/⌘+A)" data-action="all">Tutte</button>

        @if ($canEdit)
            <span class="w-px h-4 bg-gray-200 dark:bg-gray-700 shrink-0"></span>

            <label class="flex items-center gap-1 whitespace-nowrap {{ $reasons['status'] ? 'opacity-40' : '' }}" title="{{ $reasons['status'] ?? 'Cambia lo stato di tutte le pagine selezionate' }}">
                Stato:
                <select
                    x-data
                    x-on:change="if ($event.target.value) { $wire.bulkChangeStatus($event.target.value); $event.target.value = ''; }"
                    @disabled($reasons['status'] !== null)
                    class="rounded border-indigo-300 dark:border-indigo-700 dark:bg-gray-900 text-xs py-0 pl-1 pr-6"
                    data-action="status"
                >
                    <option value="">Scegli…</option>
                    @foreach (\App\Enums\PageStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>

            {!! $button('link', 'linkSelectedPages', '🔗 Collega', 'Collega le pagine selezionate in un gruppo: si spostano insieme (Ctrl/⌘+Shift+G)') !!}

            {{-- Larghe: in riga. --}}
            <span class="hidden min-[1100px]:contents">
                {!! $button('unlink', 'unlinkSelectedFromGroup', '✂️ Scollega', 'Toglie le pagine selezionate dal loro gruppo (le altre restano collegate). Ctrl/⌘+Shift+G scollega il gruppo intero') !!}
                {!! $button('lock', 'bulkToggleLock(true)', '🔒 Blocca', 'Blocca le pagine selezionate') !!}
                {!! $button('unlock', 'bulkToggleLock(false)', '🔓 Sblocca', 'Sblocca le pagine selezionate') !!}
            </span>

            {{-- Strette: nel menu «Altro ⋯» (stesse azioni, stessi motivi). --}}
            <div class="relative min-[1100px]:hidden" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.stop="open = false">
                <button type="button" x-on:click="open = ! open" class="{{ $actionClass }} {{ $enabledClass }}" :aria-expanded="open.toString()" title="Altre azioni sulla selezione" data-action="more">Altro ⋯</button>
                <div x-show="open" x-cloak x-on:click="open = false" class="absolute left-0 z-50 mt-1 flex flex-col gap-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 shadow-lg">
                    {!! $button('unlink', 'unlinkSelectedFromGroup', '✂️ Scollega dal gruppo', 'Toglie le pagine selezionate dal loro gruppo') !!}
                    {!! $button('lock', 'bulkToggleLock(true)', '🔒 Blocca', 'Blocca le pagine selezionate') !!}
                    {!! $button('unlock', 'bulkToggleLock(false)', '🔓 Sblocca', 'Sblocca le pagine selezionate') !!}
                </div>
            </div>
        @endif

        @if ($bulkResultMessage)
            <span class="ml-auto truncate text-emerald-700 dark:text-emerald-300" title="{{ $bulkResultMessage }}" data-testid="selection-result">✅ {{ $bulkResultMessage }}</span>
        @endif
    </div>
@endif

{{-- Errori di blocco, collegamento, assegnazione/spazio (anche dal pannello
     contenuti), estensione e rinomina: qui nella fascia restano visibili
     ovunque si sia nel timone e anche a pannello chiuso. --}}
@foreach (['locked' => '🔒', 'link' => '🔗', 'percentage' => '📐', 'extend' => '↗', 'rename' => '✏️'] as $errorKey => $icon)
    @error($errorKey)
        <p class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded px-2 py-1 truncate" title="{{ $message }}" data-testid="sticky-error-{{ $errorKey }}">{{ $icon }} {{ $message }}</p>
    @enderror
@endforeach
