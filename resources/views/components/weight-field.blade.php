{{--
    Un campo dei parametri del peso (App\Support\Weight\WeightParameters),
    condiviso da creazione numero, modale del pannello Peso e impostazioni
    rivista. `model` è il percorso wire:model completo (es. "weight.grammatura_interno");
    `live` attiva l'anteprima con debounce 600 ms (revisione 2, Fase 1, bug 1:
    con 300 ms la richiesta partiva a metà numero) e gli avvisi sulla
    grammatura compaiono solo all'uscita dal campo (x-live-field). `paper` (App\Support\Weight\PaperLine)
    mostra sotto le grammature il peso a pagina che ne deriva.

    type="text" + inputmode e non type="number": un input number rifiuta
    la virgola in molti browser/locali, mentre la redazione scrive "80,5".
    La virgola viene convertita lato server (WeightParameters::normalizeInput).
--}}
@props(['field', 'model', 'modified' => false, 'live' => false, 'paper' => null, 'readonly' => false])

@use('App\Support\Weight\WeightFormatter', 'F')

@php
    $id = str_replace('.', '-', $model);
    $inputMode = in_array($field, ['formato_base_mm', 'formato_altezza_mm', 'pagine_copertina', 'n_copie', 'n_bancali'], true) ? 'numeric' : 'decimal';
    $inputClasses = 'mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm';
@endphp

<x-live-field>
    <div class="flex items-center gap-2">
        <x-input-label :for="$id" :value="\App\Support\Weight\WeightParameters::LABELS[$field]" />
        @if ($modified)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="Diverso dal default della rivista">modificato</span>
        @endif
    </div>
    @if ($readonly)
        {{-- Fase 2: valore derivato (es. pagine di copertina di un numero), mostrato ma non modificabile. --}}
        <input id="{{ $id }}" type="text" wire:model="{{ $model }}" readonly class="{{ $inputClasses }} bg-gray-100 dark:bg-gray-800 cursor-not-allowed" />
    @elseif ($live)
        <input id="{{ $id }}" type="text" inputmode="{{ $inputMode }}" autocomplete="off" wire:model.live.debounce.600ms="{{ $model }}" class="{{ $inputClasses }}" />
    @else
        <input id="{{ $id }}" type="text" inputmode="{{ $inputMode }}" autocomplete="off" wire:model="{{ $model }}" class="{{ $inputClasses }}" />
    @endif
    @if ($paper !== null)
        @if ($paper->outOfRange)
            <p x-show="! editing" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $paper->message }}</p>
        @elseif ($paper->pesoPaginaG === null && $paper->message !== null)
            <p x-show="! editing" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $paper->message }}</p>
        @elseif ($paper->pesoPaginaG === null)
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Imposta base e altezza per vedere il peso a pagina.</p>
        @else
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400" data-paper-line="{{ $field }}">
                {{-- Su una riga sola: "80 g/m² → 2,268 g a pagina · 208 pagine = 471,7 g". --}}
                {{ F::parameter($paper->grammatura) }} g/m² → {{ F::pageGrams($paper->pesoPaginaG) }} g a pagina{{ $paper->pagine !== null ? ' · '.$paper->pagine.' pagine = '.F::grams($paper->totaleG).' g' : '' }}
            </p>
        @endif
    @endif
    <x-input-error class="mt-1" :messages="$errors->get($model)" />
</x-live-field>
