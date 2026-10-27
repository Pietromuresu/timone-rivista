<div>
    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div>
                <a href="{{ route('magazines.show', $magazine) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    &larr; {{ $magazine->name }}
                </a>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mt-2">
                    Nuovo numero
                </h2>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form wire:submit="save" class="space-y-6">
                    @if ($previousIssues->isNotEmpty())
                        <div>
                            <x-input-label for="duplicateFromIssueId" value="Duplica struttura da un numero precedente (opzionale)" />
                            <select id="duplicateFromIssueId" wire:model="duplicateFromIssueId" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="">— Timone vuoto (nessuna duplicazione) —</option>
                                @foreach ($previousIssues as $previous)
                                    <option value="{{ $previous->id }}">{{ $previous->title }} ({{ $previous->total_pages }} pagine)</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Copia solo il tipo di ciascuna pagina (editoriale/pubblicità/mista/bianca), non i contenuti assegnati né gli stati.
                            </p>
                        </div>
                    @endif

                    <div>
                        <x-input-label for="title" value="Titolo numero" />
                        <x-text-input id="title" wire:model="title" type="text" class="mt-1 block w-full" required autofocus placeholder="Es. Novembre 2026" />
                        <x-input-error class="mt-2" :messages="$errors->get('title')" />
                    </div>

                    <div>
                        <x-input-label for="issue_date" value="Data di uscita (opzionale)" />
                        <x-text-input id="issue_date" wire:model="issue_date" type="date" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('issue_date')" />
                    </div>

                    <div>
                        <x-input-label for="print_deadline_at" value="Scadenza stampa (opzionale)" />
                        <x-text-input id="print_deadline_at" wire:model="print_deadline_at" type="date" class="mt-1 block w-full" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Il redattore di riferimento della rivista riceverà una mail di sollecito quando la scadenza si avvicina.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('print_deadline_at')" />
                    </div>

                    <div>
                        <x-input-label for="total_pages" value="Numero di pagine interne (copertine escluse)" />
                        {{-- Fase 2 (2026-10-26): solo pagine interne, numerate da 1. Le
                             copertine I–IV si creano sempre da sole, a parte. --}}
                        <p class="text-xs text-gray-500 dark:text-gray-400">Le quattro copertine (I, II, III, IV) si aggiungono da sole e non vanno contate qui.</p>
                        <x-text-input id="total_pages" wire:model.live="total_pages" type="number" min="0" max="2000" class="mt-1 block w-32" required />
                        <x-input-error class="mt-2" :messages="$errors->get('total_pages')" />
                        @if ($total_pages !== null && $total_pages > 0 && $total_pages % 4 !== 0)
                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                ⚠️ Non è un multiplo di 4 — consentito per i casi speciali, ma da verificare.
                            </p>
                        @endif
                    </div>

                    {{-- Carta e peso: pre-compilata con i default della rivista,
                         copiata sul numero al salvataggio (vedi Issues\Create::$weight). --}}
                    <details open wire:ignore.self class="rounded-lg border border-gray-200 dark:border-gray-700">
                        <summary class="cursor-pointer select-none px-4 py-3 font-medium text-gray-700 dark:text-gray-300">⚖️ Carta e peso</summary>
                        <div class="px-4 pb-4 space-y-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Valori proposti dalla rivista: puoi cambiarli solo per questo numero. Le pagine interne sono quelle indicate sopra; le pagine di copertina sono 4 (I–IV), 6 se scrivi 6: il numero nascerà con la bandella.
                            </p>

                            @if ($modifiedWeightFields !== [])
                                <div class="flex justify-end">
                                    <button type="button" wire:click="restoreMagazineDefaults" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Ripristina default rivista</button>
                                </div>
                            @endif

                            {{-- Carta: si inserisce la grammatura scritta sulla carta (g/m²),
                                 il peso a pagina sotto ogni campo è calcolato (WeightPreview). --}}
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Carta</p>
                            <div class="grid grid-cols-2 gap-4">
                                @foreach (\App\Support\Weight\WeightParameters::PAPER_FIELDS as $field)
                                    <x-weight-field :field="$field" :model="'weight.'.$field" :modified="in_array($field, $modifiedWeightFields, true)" :paper="$weightPreview->paper[$field] ?? null" live />
                                @endforeach
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                @foreach (['formato_base_mm', 'formato_altezza_mm'] as $field)
                                    <x-weight-field :field="$field" :model="'weight.'.$field" :modified="in_array($field, $modifiedWeightFields, true)" live />
                                @endforeach
                                <x-weight-field field="n_copie" model="weight.n_copie" live />
                                <x-weight-field field="n_bancali" model="weight.n_bancali" live />
                            </div>

                            <details wire:ignore.self class="rounded-md border border-gray-100 dark:border-gray-700">
                                <summary class="cursor-pointer select-none px-3 py-2 text-sm text-gray-600 dark:text-gray-400">Avanzate (maggiorazione e tolleranze)</summary>
                                <div class="px-3 pb-3 grid grid-cols-2 gap-4">
                                    @foreach (\App\Support\Weight\WeightParameters::ADVANCED_FIELDS as $field)
                                        <x-weight-field :field="$field" :model="'weight.'.$field" :modified="in_array($field, $modifiedWeightFields, true)" live />
                                    @endforeach
                                </div>
                            </details>

                            <x-weight-preview :preview="$weightPreview" />
                        </div>
                    </details>

                    <div>
                        <x-input-label for="notes" value="Note (opzionale)" />
                        <textarea id="notes" wire:model="notes" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"></textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Crea numero</x-primary-button>
                        <a href="{{ route('magazines.show', $magazine) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Annulla</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
