<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div>
                <a href="{{ route('magazines.show', $magazine) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    &larr; {{ $magazine->name }}
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 flex items-start justify-between gap-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-3 h-3 rounded-full" style="background-color: {{ $magazine->color }}"></span>
                            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                                {{ $issue->title }}
                            </h2>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium
                                @class([
                                    'bg-gray-100 text-gray-600' => $issue->status->value === 'bozza',
                                    'bg-sky-100 text-sky-700' => $issue->status->value === 'in_lavorazione',
                                    'bg-green-100 text-green-700' => $issue->status->value === 'chiuso',
                                ])">
                                {{ $issue->status->label() }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ $magazine->name }}
                            &middot; {{ $issue->issue_date?->translatedFormat('d M Y') ?? 'Data non definita' }}
                            &middot; {{ $issue->total_pages }} pagine previste
                        </p>
                        @if ($issue->notes)
                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-3">{{ $issue->notes }}</p>
                        @endif
                    </div>

                    {{-- Utenti collegati in questo momento su questo numero (canale presence) --}}
                    <div
                        x-data="{
                            online: [],
                            join() {
                                if (! window.Echo) { return }
                                window.Echo.join('issue.{{ $issue->id }}')
                                    .here((users) => { this.online = users })
                                    .joining((user) => { this.online.push(user) })
                                    .leaving((user) => { this.online = this.online.filter(u => u.id !== user.id) })
                            }
                        }"
                        x-init="join()"
                        class="flex flex-col items-end gap-2 shrink-0"
                    >
                        <span class="text-xs text-gray-400 dark:text-gray-500">Online ora</span>
                        <div class="flex -space-x-2">
                            <template x-for="user in online" :key="user.id">
                                <div
                                    class="w-8 h-8 rounded-full bg-indigo-500 text-white text-xs flex items-center justify-center ring-2 ring-white dark:ring-gray-800 uppercase"
                                    :title="user.name"
                                    x-text="user.name.substring(0, 2)"
                                ></div>
                            </template>
                            <template x-if="online.length === 0">
                                <span class="text-xs text-gray-400 dark:text-gray-500 italic">nessuno</span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <livewire:timone.grid :issue="$issue" :key="'timone-grid-'.$issue->id" />
        </div>
    </div>
</div>
