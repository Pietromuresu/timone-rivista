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
                        <x-input-label for="total_pages" value="Numero totale di pagine" />
                        <x-text-input id="total_pages" wire:model.live="total_pages" type="number" min="0" max="2000" class="mt-1 block w-32" required />
                        <x-input-error class="mt-2" :messages="$errors->get('total_pages')" />
                        @if ($total_pages > 0 && $total_pages % 4 !== 0)
                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                ⚠️ Non è un multiplo di 4 — consentito per i casi speciali, ma da verificare.
                            </p>
                        @endif
                    </div>

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
