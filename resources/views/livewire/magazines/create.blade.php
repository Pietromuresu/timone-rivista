<div>
    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div>
                <a href="{{ route('magazines.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    &larr; Le tue riviste
                </a>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mt-2">
                    Nuova rivista
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
                        <x-input-label for="ad_threshold_percentage" value="Soglia allarme carico pubblicitario % (opzionale)" />
                        <x-text-input id="ad_threshold_percentage" wire:model="ad_threshold_percentage" type="number" min="0" max="100" step="0.5" class="mt-1 block w-32" />
                        <x-input-error class="mt-2" :messages="$errors->get('ad_threshold_percentage')" />
                    </div>

                    <div>
                        <x-input-label for="notes" value="Note (opzionale)" />
                        <textarea id="notes" wire:model="notes" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"></textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Crea rivista</x-primary-button>
                        <a href="{{ route('magazines.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Annulla</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
