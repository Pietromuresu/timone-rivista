<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" wire:key="cover-section-{{ $issue->id }}">
    <button
        type="button"
        wire:click="$set('expanded', {{ $expanded ? 'false' : 'true' }})"
        class="w-full flex items-center justify-between p-4 text-left"
    >
        <span class="font-semibold text-gray-800 dark:text-gray-200">
            📘 Copertina &amp; battente
            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                ({{ $coverPages->count() }} {{ $coverPages->count() === 1 ? 'pagina' : 'pagine' }}
                &middot; {{ $displayedTotalPages }} pagine totali{{ $hasBattente ? ' (battente escluso)' : '' }})
            </span>
        </span>
        <span class="text-gray-400">{{ $expanded ? '▲' : '▼' }}</span>
    </button>

    @if ($expanded)
        <div class="p-4 pt-0 space-y-3">
            @error('role')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            @forelse ($coverPages as $page)
                @php
                    $latestFile = $page->files->first();
                    $typeColor = $page->content_type->colorClasses();
                    $locked = $page->isLocked();
                @endphp
                <div wire:key="cover-page-{{ $page->id }}" class="flex items-center gap-3 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                    <div class="w-14 h-20 shrink-0 rounded overflow-hidden bg-gray-100 dark:bg-gray-700 relative">
                        @if ($latestFile && $latestFile->thumbnail_status->value === 'ready')
                            <img src="{{ route('page-files.thumbnail', $latestFile) }}" alt="" class="w-full h-full object-cover" />
                        @else
                            <div class="w-full h-full flex items-center justify-center text-xs text-gray-400">#{{ $page->position }}</div>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-sm text-gray-800 dark:text-gray-200">{{ $page->role->label() }}</span>
                            <span class="text-xs text-gray-400">pagina {{ $page->position }}</span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $typeColor }}">
                                {{ $page->content_type->label() }}
                            </span>
                            @if ($locked)
                                <span title="Pagina bloccata">🔒</span>
                            @endif
                        </div>

                        @forelse ($page->contents as $content)
                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 break-words">
                                {{ $content->type->value === 'articolo' ? '📄' : '📢' }} {{ $content->displayLabel() }}
                            </p>
                        @empty
                            <p class="text-xs text-gray-400 italic mt-1">Nessun contenuto assegnato</p>
                        @endforelse
                    </div>

                    @if ($page->role->value !== 'copertina1' && $page->role->value !== 'copertina4')
                        <button
                            type="button"
                            wire:click="assignRole({{ $page->id }}, 'interna')"
                            @disabled($locked)
                            title="{{ $locked ? 'Pagina bloccata: sblocca prima di rimuovere il ruolo' : 'Rimuovi dalla sezione copertina/battente' }}"
                            class="shrink-0 text-xs text-red-500 hover:text-red-700 disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            ✕ rimuovi
                        </button>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-400 italic">Nessuna copertina/battente assegnato ancora — usa il menu qui sotto su una pagina interna.</p>
            @endforelse

            @if ($internalPages->isNotEmpty())
                <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        Assegna seconda/terza di copertina o battente a una pagina interna esistente:
                    </p>
                    <form wire:submit.prevent="submitAssignment" class="flex items-center gap-2">
                        <select wire:model="assignPageId" class="text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="">— pagina —</option>
                            @foreach ($internalPages as $internalPage)
                                <option value="{{ $internalPage->id }}">Pagina {{ $pageNumbers[$internalPage->id] ?? $internalPage->position }}</option>
                            @endforeach
                        </select>
                        <select wire:model="assignRoleValue" class="text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            @foreach ($assignableRoles as $role)
                                <option value="{{ $role->value }}">{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="text-sm px-3 py-1 rounded bg-indigo-600 text-white hover:bg-indigo-500">Assegna</button>
                    </form>
                </div>
            @endif
        </div>
    @endif
</div>
