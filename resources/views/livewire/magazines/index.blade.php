<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Le tue riviste
                </h2>

                @can('create', \App\Models\Magazine::class)
                    <a href="{{ route('magazines.create') }}">
                        <x-primary-button>+ Nuova rivista</x-primary-button>
                    </a>
                @endcan
            </div>

            @if ($magazines->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-500 dark:text-gray-400">
                        Non hai ancora accesso a nessuna rivista. Contatta un amministratore per essere abilitato.
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($magazines as $magazine)
                        @php($currentIssue = $magazine->currentIssue())
                        <a href="{{ route('magazines.show', $magazine) }}"
                           class="block bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border-t-4 hover:shadow-md transition-shadow"
                           style="border-top-color: {{ $magazine->color }}">
                            <div class="p-6">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="inline-block w-3 h-3 rounded-full" style="background-color: {{ $magazine->color }}"></span>
                                    <h3 class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                                        {{ $magazine->name }}
                                    </h3>
                                </div>

                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                    {{ $magazine->periodicity->label() }}
                                </p>

                                @if ($currentIssue)
                                    <div class="text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">Numero in lavorazione:</span>
                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $currentIssue->title }}</span>
                                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            @class([
                                                'bg-gray-100 text-gray-600' => $currentIssue->status->value === 'bozza',
                                                'bg-sky-100 text-sky-700' => $currentIssue->status->value === 'in_lavorazione',
                                            ])">
                                            {{ $currentIssue->status->label() }}
                                        </span>
                                    </div>
                                @else
                                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">
                                        Nessun numero in lavorazione
                                    </p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
