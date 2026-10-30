<div>
    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div>
                <a href="{{ route('magazines.show', $magazine) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    &larr; {{ $magazine->name }}
                </a>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mt-2">
                    Modifica rivista
                </h2>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form wire:submit="save" class="space-y-6">
                    <div>
                        <x-input-label for="name" value="Nome" />
                        <x-text-input id="name" wire:model="name" type="text" class="mt-1 block w-full" required autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="periodicity" value="Periodicità" />
                        <select id="periodicity" wire:model="periodicity" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                            @foreach ($periodicities as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('periodicity')" />
                    </div>

                    <div>
                        <x-input-label for="color" value="Colore identificativo" />
                        <input id="color" wire:model="color" type="color" class="mt-1 block h-10 w-20 rounded-md border-gray-300 dark:border-gray-700" />
                        <x-input-error class="mt-2" :messages="$errors->get('color')" />
                    </div>

                    <div>
                        <x-input-label for="redattore_id" value="Redattore di riferimento (opzionale)" />
                        <select id="redattore_id" wire:model="redattore_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                            <option value="">Nessuno</option>
                            @foreach ($redattori as $redattore)
                                <option value="{{ $redattore->id }}">{{ $redattore->name }} ({{ $redattore->email }})</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Riceve le mail di sollecito scadenza stampa e ottiene automaticamente accesso alla rivista.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('redattore_id')" />
                    </div>


                    {{-- Segnature (2026-10-29): valgono subito per tutti i numeri della rivista
                         (riepilogo e confini sulla griglia, App\Support\Signatures\SignaturePlan). --}}
                    <fieldset class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-3">
                        <legend class="px-1 font-medium text-gray-700 dark:text-gray-300">📐 Segnature di stampa</legend>
                        <div class="max-w-xs">
                            <x-input-label for="signature_pages" value="Pagine per segnatura" />
                            <select id="signature_pages" wire:model="signature_pages" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                @foreach (\App\Support\Signatures\SignaturePlan::SIZES as $size)
                                    <option value="{{ $size }}">{{ $size }} ({{ \App\Support\Signatures\SignaturePlan::formatName($size, 1) }})</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('signature_pages')" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" wire:model="signature_includes_covers" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                            Copertina inclusa nelle segnature (le sue facciate contano in testa alla prima)
                        </label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Di solito no: la copertina si stampa a parte. Vale subito per tutti i numeri della rivista.</p>
                    </fieldset>

                    {{-- Default del calcolo peso: copiati su ogni nuovo numero (Issues\Create). --}}
                    <fieldset class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                        <legend class="px-1 font-medium text-gray-700 dark:text-gray-300">⚖️ Carta e peso — valori di default</legend>
                        <p class="text-xs font-medium text-amber-700 dark:text-amber-400">
                            Valgono solo per i nuovi numeri: i numeri già creati mantengono i propri parametri (si modificano dal pannello Peso di ciascun numero).
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Inserisci la grammatura scritta sulla carta (es. 80 per l'interno, 170 per la copertina): il peso di ogni pagina è calcolato automaticamente.</p>
                        <div class="grid grid-cols-2 gap-4">
                            @foreach (\App\Support\Weight\WeightParameters::PAPER_FIELDS as $field)
                                @if ($field === 'pagine_copertina')
                                    <x-cover-flaps-field model="default_cover_flaps" :value="$default_cover_flaps" />
                                @else
                                    <x-weight-field :field="$field" :model="'weight.'.$field" :paper="$weightPreview->paper[$field] ?? null" live />
                                @endif
                            @endforeach
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            @foreach (array_diff(\App\Support\Weight\WeightParameters::FIELDS, \App\Support\Weight\WeightParameters::PAPER_FIELDS) as $field)
                                <x-weight-field :field="$field" :model="'weight.'.$field" live />
                            @endforeach
                        </div>

                        {{-- Pagine di prova: solo per l'anteprima, la rivista non ha un numero di pagine
                             proprio (Magazines\Edit::$previewTotalPages, mai salvato). --}}
                        <div class="max-w-xs">
                            <x-input-label for="previewTotalPages" value="Pagine interne per l'anteprima (non salvate)" />
                            <input id="previewTotalPages" type="text" inputmode="numeric" wire:model.live.debounce.300ms="previewTotalPages" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
                        </div>
                        <x-weight-preview :preview="$weightPreview" title="Anteprima con questi default" />
                    </fieldset>

                    <div>
                        <x-input-label for="notes" value="Note (opzionale)" />
                        <textarea id="notes" wire:model="notes" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"></textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Salva modifiche</x-primary-button>
                        <a href="{{ route('magazines.show', $magazine) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Annulla</a>
                    </div>
                </form>
            </div>

            {{-- Revisione 2, Fase 4.2: eliminazione della rivista (solo admin, conferma in due passaggi). --}}
            @can('delete', $magazine)
                <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg border border-red-200 dark:border-red-900">
                    <h3 class="text-base font-medium text-red-700 dark:text-red-400">Elimina la rivista</h3>
                    <p class="mt-1 mb-3 text-sm text-gray-600 dark:text-gray-400">Elimina la rivista con tutti i suoi numeri, pagine, contenuti e file. Non si può annullare.</p>
                    <livewire:admin.confirm-deletion kind="magazine" :target-id="$magazine->id" />
                </div>
            @endcan
        </div>
    </div>
</div>
