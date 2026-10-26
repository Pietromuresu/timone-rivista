{{--
    Anteprima live del peso (App\Support\Weight\WeightPreview) PRIMA del
    salvataggio: riepilogo peso ± margine e scaglione e, se c'è un valore
    salvato con cui confrontare, la differenza ("+12,4 g, passa da scaglione
    500 g a 1.000 g"). Usata da creazione numero, modale Peso e impostazioni
    rivista. Nessun calcolo qui: solo formattazione.
--}}
@props(['preview', 'title' => 'Anteprima peso'])

@use('App\Support\Weight\WeightFormatter', 'F')

<div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-3" wire:key="weight-preview">
    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">{{ $title }}</p>
    <x-weight-summary :result="$preview->result" />
    @if ($preview->deltaG() !== null)
        @php
            // Frase composta su una riga: es. "+12,4 g rispetto al valore salvato (498,3 g), passa da scaglione 500 g a 1.000 g."
            $saved = F::grams($preview->saved->pesoCopiaG);
            $deltaText = match (true) {
                round($preview->deltaG(), 1) == 0 => "Nessuna variazione rispetto al valore salvato ({$saved} g).",
                $preview->bracketChanged() => F::signedGrams($preview->deltaG())." g rispetto al valore salvato ({$saved} g), passa da scaglione ".F::bracket($preview->saved->scaglione).' a '.F::bracket($preview->result->scaglione).'.',
                default => F::signedGrams($preview->deltaG())." g rispetto al valore salvato ({$saved} g), scaglione invariato (".F::bracket($preview->result->scaglione).').',
            };
        @endphp
        <p class="text-xs mt-2 {{ $preview->bracketChanged() ? 'font-medium text-amber-700 dark:text-amber-400' : 'text-gray-600 dark:text-gray-400' }}" data-weight-delta>{{ $deltaText }}</p>
    @endif
    @if ($preview->result->complete && $preview->result->pesoNettoKg !== null)
        <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
            Tiratura: {{ F::kilograms($preview->result->pesoNettoKg) }} kg netti
            @if ($preview->result->pesoLordoKg !== null)
                / {{ F::kilograms($preview->result->pesoLordoKg) }} kg lordi
            @endif
        </p>
    @endif
</div>
