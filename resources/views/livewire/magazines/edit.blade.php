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


                    {{-- Default del calcolo peso: copiati su ogni nuovo numero (Issues\Create). --}}
                    <fieldset class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                        <legend class="px-1 font-medium text-gray-700 dark:text-gray-300">⚖️ Carta e peso — valori di default</legend>
                        <p class="text-xs font-medium text-amber-700 dark:text-amber-400">
                            Valgono solo per i nuovi numeri: i numeri già creati mantengono i propri parametri (si modificano dal pannello Peso di ciascun numero).
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Inserisci la grammatura scritta sulla carta (es. 80 per l'interno, 170 per la copertina): il peso di ogni pagina è calcolato automaticamente.</p>
                        <div class="grid grid-cols-2 gap-4">
                            @foreach (\App\Support\Weight\WeightParameters::PAPER_FIELDS as $field)
                                <x-weight-field :field="$field" :model="'weight.'.$field" :paper="$weightPreview->paper[$field] ?? null" live />
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
        </div>
    </div>
</div>
