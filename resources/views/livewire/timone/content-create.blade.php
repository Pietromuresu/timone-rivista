<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 p-3">
    <div class="flex items-center justify-between">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Nuovo contenuto</h4>
        <button
            type="button"
            wire:click="toggleForm"
            @class([
                'px-3 py-1.5 rounded-lg border text-sm transition-colors',
                'bg-indigo-600 text-white border-indigo-600' => $showForm,
                'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' => ! $showForm,
            ])
        >
            {{ $showForm ? '✕ Annulla' : '+ Nuovo contenuto' }}
        </button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-3 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="type" value="Tipo" />
                    <select id="type" wire:model.live="type" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                        <option value="articolo">Articolo</option>
                        <option value="pubblicita">Pubblicità</option>
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('type')" />
                </div>

                <div>
                    <x-input-label for="section_id" value="Rubrica (opzionale)" />
                    <select id="section_id" wire:model="section_id" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                        <option value="">— Nessuna —</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('section_id')" />
                </div>
            </div>

            <div>
                <x-input-label for="title" :value="$type === 'articolo' ? 'Titolo articolo' : 'Titolo/descrizione inserzione'" />
                <x-text-input id="title" wire:model="title" type="text" class="mt-1 block w-full text-sm" required />
                <x-input-error class="mt-1" :messages="$errors->get('title')" />
            </div>

            @if ($type === 'articolo')
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="author" value="Autore (opzionale)" />
                        <x-text-input id="author" wire:model="author" type="text" class="mt-1 block w-full text-sm" />
                    </div>
                    <div>
                        <x-input-label for="editorial_status" value="Stato redazionale" />
                        <select id="editorial_status" wire:model="editorial_status" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            @foreach ($editorialStatuses as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="expected_length" value="Lunghezza prevista (opzionale)" />
                        <x-text-input id="expected_length" wire:model="expected_length" type="number" min="1" class="mt-1 block w-full text-sm" />
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="client" value="Cliente" />
                        <x-text-input id="client" wire:model="client" type="text" class="mt-1 block w-full text-sm" required />
                        <x-input-error class="mt-1" :messages="$errors->get('client')" />
                    </div>
                    <div>
                        <x-input-label for="agency" value="Agenzia (opzionale)" />
                        <x-text-input id="agency" wire:model="agency" type="text" class="mt-1 block w-full text-sm" />
                    </div>
                    <div>
                        <x-input-label for="format" value="Formato" />
                        <select id="format" wire:model="format" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            @foreach ($adFormats as $adFormat)
                                <option value="{{ $adFormat->value }}">{{ $adFormat->label() }} ({{ $adFormat->defaultPercentage() }}%)</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="occupied_percentage_override" value="Percentuale manuale (opzionale)" />
                        <x-text-input id="occupied_percentage_override" wire:model="occupied_percentage_override" type="number" min="0.1" max="100" step="0.1" placeholder="usa il default del formato" class="mt-1 block w-full text-sm" />
                        <x-input-error class="mt-1" :messages="$errors->get('occupied_percentage_override')" />
                    </div>
                    <div>
                        <x-input-label for="preferred_position" value="Pagina preferita (opzionale)" />
                        <x-text-input id="preferred_position" wire:model="preferred_position" type="number" min="1" placeholder="nessuna preferenza" class="mt-1 block w-full text-sm" />
                        <p class="mt-1 text-xs text-gray-400">Solo indicativo: non assegna il contenuto da solo, ricordalo a chi compone il timone.</p>
                    </div>
                    <div>
                        <x-input-label for="confirmation_status" value="Stato commerciale" />
                        <select id="confirmation_status" wire:model="confirmation_status" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                            @foreach ($confirmationStatuses as $confStatus)
                                <option value="{{ $confStatus->value }}">{{ $confStatus->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="commercial_notes" value="Note commerciali (opzionale)" />
                        <textarea id="commercial_notes" wire:model="commercial_notes" rows="2" class="mt-1 block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm"></textarea>
                    </div>
                </div>
            @endif

            <x-primary-button>Crea contenuto</x-primary-button>
        </form>
    @endif
</div>
