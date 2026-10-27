@use('App\Support\Weight\WeightFormatter', 'F')
@use('App\Support\Weight\WeightParameters')

@php
    $in = $result->input;
@endphp

<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-4">
    <div class="flex items-center justify-between gap-4">
        <h3 class="font-medium text-gray-700 dark:text-gray-300">⚖️ Peso</h3>
        @if ($canUpdate)
            <button type="button" wire:click="openEditor" class="px-3 py-1.5 rounded-lg border text-sm bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                Modifica parametri
            </button>
        @endif
    </div>

    @if (session('weight-status'))
        <p class="text-sm text-emerald-700 dark:text-emerald-400">✓ {{ session('weight-status') }}</p>
    @endif

    <x-weight-summary :result="$result" />

    @if ($result->complete)
        {{-- Dettaglio: stessi termini della formula, valori pieni arrotondati solo qui. --}}
        <dl class="grid grid-cols-[8rem_1fr_auto] gap-x-4 gap-y-1 text-sm">
            <dt class="text-gray-500 dark:text-gray-400">Interno</dt>
            <dd class="text-gray-700 dark:text-gray-300">{{ $in->pagineInterno }} pag. × {{ F::parameter($in->grammaturaInterno) }} g/m²</dd>
            <dd class="text-right text-gray-900 dark:text-gray-100">{{ F::grams($result->pesoInternoG) }} g</dd>

            <dt class="text-gray-500 dark:text-gray-400">Copertina</dt>
            <dd class="text-gray-700 dark:text-gray-300">{{ $in->pagineCopertina }} pag. × {{ F::parameter($in->grammaturaCopertina) }} g/m²</dd>
            <dd class="text-right text-gray-900 dark:text-gray-100">{{ F::grams($result->pesoCopertinaG) }} g</dd>

            <dt class="text-gray-500 dark:text-gray-400">Maggiorazione</dt>
            <dd class="text-gray-700 dark:text-gray-300">+{{ F::parameter($in->maggiorazionePercentuale) }}%</dd>
            <dd class="text-right text-gray-900 dark:text-gray-100">{{ F::grams($result->pesoMaggiorazioneG) }} g</dd>

            <dt class="text-gray-500 dark:text-gray-400">Formato</dt>
            <dd class="col-span-2 text-gray-700 dark:text-gray-300">{{ F::integer((int) $in->formatoBaseMm) }} × {{ F::integer((int) $in->formatoAltezzaMm) }} mm</dd>

            <dt class="text-gray-500 dark:text-gray-400">Tiratura</dt>
            <dd class="col-span-2 text-gray-700 dark:text-gray-300">
                @if ($result->pesoNettoKg !== null)
                    {{ F::integer($in->nCopie) }} copie → {{ F::kilograms($result->pesoNettoKg) }} kg netti
                    @if ($result->pesoLordoKg !== null)
                        / {{ F::kilograms($result->pesoLordoKg) }} kg lordi ({{ F::integer($in->nBancali) }} bancali)
                    @endif
                    <span class="block text-xs text-gray-500 dark:text-gray-400">Nel caso peggiore: {{ F::kilograms($result->pesoNettoMaxKg) }} kg netti</span>
                @else
                    <span class="text-gray-400 italic">n. copie non impostato</span>
                @endif
            </dd>
        </dl>

        {{-- Legenda del margine: tolleranze REALI di questo numero. --}}
        <details class="text-xs text-gray-600 dark:text-gray-400">
            <summary class="cursor-pointer select-none">ℹ️ Come è calcolato il margine ± {{ $result->margineG }} g</summary>
            <div class="mt-2 space-y-1">
                <p>Il peso è teorico. Il margine è il caso peggiore: la formula completa viene ricalcolata con tutti i parametri incerti al massimo e poi tutti al minimo, e si prende lo scostamento maggiore, arrotondato per eccesso al grammo.</p>
                <ul class="list-disc pl-5">
                    <li>Formato: ± {{ F::parameter($in->tolleranzaFormatoMm) }} mm su base e altezza (refilo)</li>
                    <li>Grammatura: ± {{ F::parameter($in->tolleranzaGrammaturaPerc) }}% sulla carta di interno e copertina</li>
                    <li>Maggiorazione: {{ F::parameter($in->maggiorazionePercentuale) }}% ± {{ F::parameter($in->tolleranzaMaggiorazionePerc) }} punti</li>
                </ul>
                <p>Peso massimo {{ F::grams($result->pesoMaxG) }} g (+{{ F::grams($result->erroreSopraG) }} g), minimo {{ F::grams($result->pesoMinG) }} g (−{{ F::grams($result->erroreSottoG) }} g).</p>
                <p>Scaglioni: {{ collect($brackets)->map(fn ($b) => F::bracket((int) $b))->implode(' · ') }}. Una copia ricade nel primo scaglione il cui limite è maggiore o uguale al peso.</p>
            </div>
        </details>

        {{-- Simulazione: calcolo puro, nessuna scrittura. --}}
        <div class="rounded-lg bg-gray-50 dark:bg-gray-900/50 p-3 space-y-2 text-sm">
            <div class="flex flex-wrap items-center gap-2">
                <label for="weight-simulate-{{ $issue->id }}" class="text-gray-600 dark:text-gray-400">Simula +/- pagine interne</label>
                <input id="weight-simulate-{{ $issue->id }}" type="number" step="2" wire:model.live.debounce.300ms="simulateDelta" placeholder="es. 8 o -4"
                    class="w-28 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 px-2 py-1" />
            </div>
            @if ($simulation !== null)
                @if ($simulation->complete)
                    <p class="text-gray-700 dark:text-gray-300">
                        Con {{ $simulatedDelta > 0 ? '+' : '' }}{{ $simulatedDelta }} pagine ({{ $simulation->input->pagineInterno }} interne):
                        <strong>{{ F::grams($simulation->pesoCopiaG) }} g</strong>
                        ({{ $simulation->pesoCopiaG >= $result->pesoCopiaG ? '+' : '' }}{{ F::grams($simulation->pesoCopiaG - $result->pesoCopiaG) }} g),
                        scaglione {{ F::bracket($simulation->scaglione) }}{{ $simulation->aRischio ? ' — a rischio' : '' }}.
                    </p>
                @else
                    @foreach ($simulation->errors as $error)
                        <p class="text-red-700 dark:text-red-400">⛔ {{ $error }}</p>
                    @endforeach
                @endif
            @endif
            @if ($addable !== null)
                <p class="text-xs text-gray-600 dark:text-gray-400">
                    Restando nello scaglione {{ F::bracket($result->scaglione) }} puoi aggiungere ancora
                    <strong>{{ $addable['nominale'] }}</strong> pagine interne sul peso nominale,
                    <strong>{{ $addable['sicure'] }}</strong> considerando il margine (a passi di 2 pagine, un foglio; in stampa si lavora a segnature).
                </p>
            @endif
        </div>

        {{-- Calibrazione: solo se è stato inserito il peso reale misurato. --}}
        @if ($result->maggiorazioneSuggerita !== null)
            <div class="rounded-lg border border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-900/20 p-3 space-y-2 text-sm">
                <p class="text-gray-700 dark:text-gray-300">
                    Peso reale misurato: <strong>{{ F::grams($in->pesoRealeG) }} g</strong> —
                    scostamento {{ $result->scostamentoRealeG >= 0 ? '+' : '' }}{{ F::grams($result->scostamentoRealeG) }} g
                    ({{ F::percent($result->scostamentoRealePerc, signed: true) }}%) rispetto al teorico.
                </p>
                <p class="text-gray-700 dark:text-gray-300">
                    Maggiorazione che avrebbe dato il peso esatto: <strong>{{ F::percent($result->maggiorazioneSuggerita) }}%</strong>
                    (attuale {{ F::parameter($in->maggiorazionePercentuale) }}%). Usa questa maggiorazione per i prossimi numeri.
                </p>
                @if ($canUpdateMagazine)
                    <button type="button"
                        wire:click="applySuggestedSurcharge"
                        wire:confirm="Impostare {{ F::percent($result->maggiorazioneSuggerita) }}% come maggiorazione di default della rivista? Varrà solo per i numeri creati da ora in poi; questo numero e quelli esistenti non cambiano."
                        class="px-3 py-1.5 rounded-lg border text-xs bg-white dark:bg-gray-800 text-sky-700 dark:text-sky-300 border-sky-300 dark:border-sky-700 hover:bg-sky-100 dark:hover:bg-sky-900/40">
                        Copia nei default della rivista
                    </button>
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400">Per salvarla nei default della rivista serve un Admin.</p>
                @endif
                <x-input-error :messages="$errors->get('suggestion')" />
            </div>
        @endif
    @endif

    @if ($canUpdate)
        <x-modal name="weight-params-{{ $issue->id }}" maxWidth="2xl">
            <form wire:submit="saveParams" class="p-6 space-y-4">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Parametri peso di questo numero</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Modificano solo questo numero, mai i default della rivista (la modifica resta nella cronologia). Le pagine seguono il timone: {{ $issue->total_pages }} pagine interne e {{ $issue->coverPageCount() }} pagine di copertina (le copertine esistenti: 6 se c'è la bandella, che si aggiunge o toglie dalla fascia copertine del timone).
                </p>
                {{-- Carta in cima: è il punto dove si sbaglia l'unità (0,8 invece di 80).
                     Sotto ogni grammatura il peso a pagina calcolato (WeightPreview),
                     tutti i campi aggiornano l'anteprima con debounce 300 ms. --}}
                <section class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Carta</h3>
                        @if ($modifiedFields !== [])
                            <button type="button" wire:click="restoreMagazineDefaults" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Ripristina default rivista</button>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Inserisci la grammatura scritta sulla carta (es. 80 per l'interno, 170 per la copertina): il peso di ogni pagina è calcolato automaticamente.</p>
                    <div class="grid grid-cols-2 gap-4">
                        @foreach (WeightParameters::PAPER_FIELDS as $field)
                            <x-weight-field :field="$field" :model="'params.'.$field" :modified="in_array($field, $modifiedFields, true)" :paper="$preview?->paper[$field] ?? null" :readonly="$field === 'pagine_copertina'" live />
                        @endforeach
                    </div>
                </section>

                <div class="grid grid-cols-2 gap-4">
                    @foreach (['formato_base_mm', 'formato_altezza_mm'] as $field)
                        <x-weight-field :field="$field" :model="'params.'.$field" :modified="in_array($field, $modifiedFields, true)" live />
                    @endforeach
                    @foreach (['n_copie', 'n_bancali', 'peso_reale_g'] as $field)
                        <x-weight-field :field="$field" :model="'params.'.$field" live />
                    @endforeach
                </div>
                <details wire:ignore.self class="rounded-md border border-gray-100 dark:border-gray-700">
                    <summary class="cursor-pointer select-none px-3 py-2 text-sm text-gray-600 dark:text-gray-400">Avanzate (maggiorazione e tolleranze)</summary>
                    <div class="px-3 pb-3 grid grid-cols-2 gap-4">
                        @foreach (WeightParameters::ADVANCED_FIELDS as $field)
                            <x-weight-field :field="$field" :model="'params.'.$field" :modified="in_array($field, $modifiedFields, true)" live />
                        @endforeach
                    </div>
                </details>

                @if ($preview !== null)
                    <x-weight-preview :preview="$preview" title="Anteprima prima del salvataggio" />
                @endif
                <div class="flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close-modal', 'weight-params-{{ $issue->id }}')">Annulla</x-secondary-button>
                    <x-primary-button>Salva</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
