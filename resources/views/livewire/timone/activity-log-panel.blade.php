<div>
    <button
        type="button"
        wire:click="toggle"
        @class([
            'px-3 py-1.5 rounded-lg border text-sm transition-colors',
            'bg-indigo-600 text-white border-indigo-600' => $show,
            'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' => ! $show,
        ])
    >
        🕓 Cronologia
    </button>

    @if ($show)
        <div class="mt-2 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 p-3">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Cronologia (ultime {{ $logs->count() }})
            </h4>

            @if ($logs->isEmpty())
                <p class="text-xs italic text-gray-400">Nessuna azione registrata per questo numero.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700 max-h-80 overflow-y-auto text-sm">
                    @foreach ($logs as $log)
                        <li class="py-1.5 flex items-center justify-between gap-3">
                            <span class="text-gray-700 dark:text-gray-200">
                                <strong>{{ $log->user->name ?? 'Utente eliminato' }}</strong>
                                — {{ $log->description }}
                            </span>
                            <span class="shrink-0 text-xs text-gray-400" title="{{ $log->created_at }}">
                                {{ $log->created_at->diffForHumans() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
