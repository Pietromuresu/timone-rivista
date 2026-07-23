<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="font-medium text-gray-700 dark:text-gray-300">
            Timone &middot; {{ $pages->count() }} pagine
        </h3>

        <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-sm">
            @foreach (['griglia' => 'Griglia', 'doppia' => 'Doppia pagina', 'lista' => 'Lista'] as $mode => $label)
                <button
                    type="button"
                    wire:click="setViewMode('{{ $mode }}')"
                    @class([
                        'px-3 py-1.5 transition-colors',
                        'bg-indigo-600 text-white' => $viewMode === $mode,
                        'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' => $viewMode !== $mode,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($pages->isEmpty())
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700">
            <div class="p-12 text-center text-gray-400 dark:text-gray-500">
                <p class="font-medium">Nessuna pagina per questo numero.</p>
                <p class="text-sm mt-1">Imposta il numero di pagine totali per generare il timone.</p>
            </div>
        </div>
    @elseif ($viewMode === 'griglia')
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3">
            @foreach ($pages as $page)
                @include('livewire.timone.partials.page-card', ['page' => $page])
            @endforeach
        </div>
    @elseif ($viewMode === 'doppia')
        <div class="space-y-3">
            @foreach ($spreads as $spread)
                <div class="flex justify-center gap-0.5">
                    @foreach ($spread as $page)
                        <div class="w-40 sm:w-48 {{ ! $loop->first ? 'border-l-2 border-gray-300 dark:border-gray-600' : '' }}">
                            @include('livewire.timone.partials.page-card', ['page' => $page])
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($pages as $page)
                    @include('livewire.timone.partials.page-row', ['page' => $page])
                @endforeach
            </ul>
        </div>
    @endif
</div>
