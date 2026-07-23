<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div>
                <a href="{{ route('magazines.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    &larr; Le tue riviste
                </a>

                <div class="flex items-center gap-2 mt-2">
                    <span class="inline-block w-3 h-3 rounded-full" style="background-color: {{ $magazine->color }}"></span>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ $magazine->name }}
                    </h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        &middot; {{ $magazine->periodicity->label() }}
                    </span>
                </div>
            </div>

            @php
                $activeIssues = $issues->whereNotIn('status', [\App\Enums\IssueStatus::Chiuso]);
                $closedIssues = $issues->where('status', \App\Enums\IssueStatus::Chiuso);
            @endphp

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="font-medium text-gray-700 dark:text-gray-300">Numeri attivi</h3>
                </div>

                @if ($activeIssues->isEmpty())
                    <div class="p-6 text-gray-500 dark:text-gray-400">
                        Nessun numero in bozza o in lavorazione al momento.
                    </div>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($activeIssues as $issue)
                            <li>
                                <a href="{{ route('issues.show', [$magazine, $issue]) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <div>
                                        <p class="font-medium text-gray-800 dark:text-gray-100">{{ $issue->title }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $issue->issue_date?->translatedFormat('d M Y') ?? 'Data non definita' }}
                                            &middot; {{ $issue->total_pages }} pagine
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium
                                        @class([
                                            'bg-gray-100 text-gray-600' => $issue->status->value === 'bozza',
                                            'bg-sky-100 text-sky-700' => $issue->status->value === 'in_lavorazione',
                                        ])">
                                        {{ $issue->status->label() }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="font-medium text-gray-700 dark:text-gray-300">Archivio (numeri chiusi)</h3>
                </div>

                @if ($closedIssues->isEmpty())
                    <div class="p-6 text-gray-500 dark:text-gray-400">
                        Nessun numero archiviato.
                    </div>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($closedIssues as $issue)
                            <li>
                                <a href="{{ route('issues.show', [$magazine, $issue]) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <div>
                                        <p class="font-medium text-gray-800 dark:text-gray-100">{{ $issue->title }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $issue->issue_date?->translatedFormat('d M Y') ?? 'Data non definita' }}
                                            &middot; {{ $issue->total_pages }} pagine
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                        {{ $issue->status->label() }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
