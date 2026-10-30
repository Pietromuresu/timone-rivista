{{-- Fase 3 (2026-10-27): occupazione come "Pagine intere" + "Frazione"
     (dodicesimi, vedi App\Support\PageOccupancy::fromParts()), al posto
     della select unica del bug 5 che non permetteva 2,5 pagine. Stesso
     blocco per articolo e pubblicità; per la pubblicità i campi li
     precompila il formato (ContentCreate::updatedFormat()) e restano
     modificabili. Il totale sotto ($occupancyPreview) viene dalla stessa
     funzione del salvataggio, non da un calcolo qui.
     $hint: testo sotto i campi, diverso per i due tipi. --}}
<fieldset>
    <legend class="block font-medium text-sm text-gray-700 dark:text-gray-300">Quanto spazio occupa (obbligatorio) <x-manual-help slug="spazio-occupato" label="Occupazione" class="ml-1" /></legend>
    <div class="mt-1 flex flex-wrap items-end gap-3">
        <div>
            <x-input-label for="whole_pages" value="Pagine intere" class="text-xs" />
            <x-text-input id="whole_pages" wire:model.live.debounce.300ms="whole_pages" type="number" min="0" step="1" placeholder="0" class="mt-1 block w-24 text-sm" />
        </div>
        <span class="pb-2 text-sm text-gray-500">+</span>
        <div>
            <x-input-label for="fraction_twelfths" value="Frazione" class="text-xs" />
            <select id="fraction_twelfths" wire:model.live="fraction_twelfths" class="mt-1 block w-32 text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                <option value="0">nessuna</option>
                @foreach ($fractionOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }} di pagina</option>
                @endforeach
            </select>
        </div>
    </div>
    <p class="mt-1 text-sm {{ $occupancyPreview ? 'text-gray-800 dark:text-gray-100 font-medium' : 'text-gray-400 italic' }}" data-testid="occupancy-preview">
        {{ $occupancyPreview ? 'Occupa '.$occupancyPreview->label() : 'Indica almeno una pagina intera o una frazione' }}
    </p>
    @if ($hint ?? null)
        <p class="mt-1 text-xs text-indigo-700 dark:text-indigo-300">{{ $hint }}</p>
    @endif
    <x-input-error class="mt-1" :messages="array_merge($errors->get('occupancy'), $errors->get('whole_pages'), $errors->get('fraction_twelfths'))" />
</fieldset>
