{{-- Campo «Posizione nel timone» dei moduli di creazione e modifica di un
     contenuto (revisione 2, Fase 2.5, 2026-10-27). Opzioni da
     App\Support\PlacementChoice::options(); il contenuto viene messo a
     partire da quella pagina con la stessa logica dell'assegnazione manuale
     (ContentPlacer) e nel primo posto libero della griglia 6×6. Se non ci
     sta, l'errore dice cosa occupa la pagina e quanto manca, e non si
     salva nulla. Richiede $placementOptions e $placementId. --}}
<div>
    <x-input-label :for="$placementId" value="Posizione nel timone (opzionale)" />
    <select id="{{ $placementId }}" wire:model="placement" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm" data-testid="placement">
        @foreach ($placementOptions as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-400">Dalla pagina scelta in poi, se occupa più pagine. Vuoto: resta tra i «Contenuti da assegnare».</p>
    <x-input-error class="mt-1" :messages="$errors->get('placement')" />
</div>
