<div>
    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div>
                <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    &larr; Utenti
                </a>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mt-2">
                    {{ $user->name }}
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
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" wire:model="email" type="email" class="mt-1 block w-full" required />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>

                    <div>
                        <x-input-label for="password" value="Nuova password (lascia vuoto per non cambiarla)" />
                        <x-text-input id="password" wire:model="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                        <x-input-error class="mt-2" :messages="$errors->get('password')" />
                    </div>

                    <div>
                        <x-input-label for="role" value="Ruolo" />
                        <select id="role" wire:model="role" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                            @foreach ($roles as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('role')" />
                    </div>

                    <div>
                        <x-input-label value="Riviste accessibili" />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Un Admin vede sempre tutte le riviste, a prescindere da questa selezione.</p>
                        @if ($magazines->isEmpty())
                            <p class="text-sm text-gray-400 italic">Nessuna rivista esistente ancora.</p>
                        @else
                            <div class="space-y-1">
                                @foreach ($magazines as $magazine)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" wire:model="magazineIds" value="{{ $magazine->id }}" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                                        {{ $magazine->name }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Salva</x-primary-button>
                        <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Annulla</a>
                    </div>
                </form>
            </div>

            {{-- Revisione 2, Fase 4.3: eliminazione dell'utente (solo admin; mai sé stessi né l'ultimo admin). --}}
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg border border-red-200 dark:border-red-900">
                <h3 class="text-base font-medium text-red-700 dark:text-red-400">Elimina l'utente</h3>
                <p class="mt-1 mb-3 text-sm text-gray-600 dark:text-gray-400">L'account viene eliminato e le sue sessioni chiuse. File caricati e cronologia restano, con «Utente eliminato».</p>
                <livewire:admin.confirm-deletion kind="user" :target-id="$user->id" />
            </div>
        </div>
    </div>
</div>
