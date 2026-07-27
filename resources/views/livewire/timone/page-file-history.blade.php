<div>
    <x-modal name="page-file-history" maxWidth="lg">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Storico PDF @if ($page) — pagina {{ $page->position }} @endif
            </h3>

            <div class="mt-4 max-h-96 overflow-y-auto">
                @if ($files->isEmpty())
                    <p class="text-sm text-gray-400 italic">Nessun file caricato per questa pagina.</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($files as $file)
                            <li class="py-2 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm text-gray-800 dark:text-gray-100 truncate">
                                        {{ $file->original_name }}
                                        @if ($loop->first)
                                            <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">attuale</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $file->uploader->name }} &middot; {{ $file->created_at->format('d/m/Y H:i') }}
                                    </p>
                                </div>

                                <div class="shrink-0 flex items-center gap-2">
                                    @if (in_array($file->thumbnail_status, [\App\Enums\ThumbnailStatus::Pending, \App\Enums\ThumbnailStatus::Processing]))
                                        <span class="text-xs text-gray-400 italic">anteprima in corso...</span>
                                    @elseif ($file->thumbnail_status === \App\Enums\ThumbnailStatus::Failed)
                                        <span class="text-xs text-red-500 italic">anteprima fallita</span>
                                    @endif

                                    {{-- Il PDF originale è sempre disponibile: la generazione della
                                         miniatura è asincrona e indipendente dal file già salvato. --}}
                                    <a
                                        href="{{ route('page-files.show', $file) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                                    >Apri</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close-modal', 'page-file-history')">
                    Chiudi
                </x-secondary-button>
            </div>
        </div>
    </x-modal>
</div>
