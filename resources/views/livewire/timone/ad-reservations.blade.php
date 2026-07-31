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
        📌 Pubblicità prenotate
    </button>

    @if ($show)
        <div class="mt-2 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 p-3 space-y-3">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Pubblicità di questo numero ({{ $rows->count() }})
                </h4>

                @can('update', $issue)
                    @if ($issue->status === \App\Enums\IssueStatus::Chiuso)
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">🔒 Numero chiuso</span>
                    @else
                        <button
                            type="button"
                            x-data
                            x-on:click="confirm('Chiudere questo numero? Non sarà più modificabile dalla lista dei numeri attivi.') && $wire.closeIssue()"
                            class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700"
                        >
                            🔒 Chiudi numero
                        </button>
                    @endif
                @endcan
            </div>

            @error('close')
                <p class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg px-3 py-2">{{ $message }}</p>
            @enderror

            @if ($rows->isEmpty())
                <p class="text-xs italic text-gray-400">Nessuna pubblicità (prenotata o assegnata) per questo numero.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700 max-h-80 overflow-y-auto text-sm">
                    @foreach ($rows as $row)
                        @php $ad = $row['advertisement']; @endphp
                        <li wire:key="ad-reservation-{{ $ad->id }}" class="py-2 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-medium text-gray-800 dark:text-gray-100 truncate">{{ $ad->client }}</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium {{ $row['status']->colorClasses() }}">
                                        {{ $row['status']->label() }}
                                    </span>
                                    @if ($ad->agency)
                                        <span class="text-xs text-gray-400">({{ $ad->agency }})</span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ $ad->format->label() }} · {{ $ad->confirmation_status->label() }}
                                    @if (! empty($row['positions']))
                                        · su pagina{{ count($row['positions']) > 1 ? 'e' : '' }} {{ implode(', ', $row['positions']) }}
                                    @elseif ($ad->preferred_position)
                                        · posizione preferita: pagina {{ $ad->preferred_position }} (non ancora assegnata)
                                    @endif
                                </p>
                                @if ($ad->commercial_notes)
                                    <p class="text-xs text-gray-400 mt-0.5 truncate" title="{{ $ad->commercial_notes }}">📝 {{ $ad->commercial_notes }}</p>
                                @endif
                            </div>

                            @can('update', $issue)
                                @if ($row['status'] === \App\Enums\AdMaterialStatus::Prenotato)
                                    <button
                                        type="button"
                                        x-data
                                        x-on:click="confirm('Eliminare la prenotazione per {{ $ad->client }}?') && $wire.deleteReservation({{ $ad->content_id }})"
                                        class="shrink-0 text-xs text-red-600 dark:text-red-400 hover:underline"
                                        title="Elimina prenotazione"
                                    >🗑 Elimina</button>
                                @endif
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
