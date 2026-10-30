<div>
    {{-- «Cosa chiedono gli utenti» (2026-10-29), solo admin. Elenchi e non
         tabelle: sul telefono si leggono senza scorrere di lato. --}}
    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Cosa chiedono gli utenti</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Le domande fatte a «Chiedi al manuale» e cosa l'assistente ha imparato dai voti. Per correggere una risposta modifica il riquadro «In breve» della pagina giusta del manuale; qui puoi solo disattivare quello che è stato imparato.
                </p>
            </div>

            <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 sm:p-6" data-testid="manual-questions-run">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Ultimo ricalcolo</h3>
                @if ($lastRun)
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        {{ \Illuminate\Support\Carbon::parse($lastRun->created_at)->format('d/m/Y H:i') }} —
                        {{ $lastRun->pubblicato ? 'pesi pubblicati' : 'bloccato: pesi non pubblicati' }}.
                        Voti considerati {{ $lastRun->voti }}, correzioni {{ $lastRun->correzioni }}, domande imparate {{ $lastRun->domande_imparate }}.
                    </p>
                @else
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Il ricalcolo non è ancora mai stato eseguito (gira ogni notte alle 3:15).</p>
                @endif

                @if ($blocked)
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/30 p-3 text-sm text-amber-900 dark:text-amber-200" data-testid="manual-questions-blocked">
                        <p class="font-medium">Ultimo blocco: {{ \Illuminate\Support\Carbon::parse($blocked->created_at)->format('d/m/Y H:i') }}. Con i pesi nuovi queste domande di riferimento non trovavano più la loro pagina tra le prime tre:</p>
                        <ul class="mt-2 list-disc pl-5 space-y-1">
                            @foreach ($blocked->fallimenti as $failure)
                                <li>«{{ $failure['domanda'] }}» → attesa <strong>{{ $titles[$failure['attesa']] ?? $failure['attesa'] }}</strong>, uscivano: {{ implode(', ', array_map(fn ($s) => $titles[$s] ?? $s, $failure['primi'])) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 sm:p-6" data-testid="manual-questions-unanswered">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Domande frequenti senza risposta</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">L'assistente non era sicuro della risposta. Spesso basta una parola chiave in più sulla pagina giusta.</p>
                <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($unanswered as $row)
                        <li class="py-2 flex items-baseline justify-between gap-3 text-sm">
                            <span class="text-gray-800 dark:text-gray-100">«{{ $row->domanda }}»</span>
                            <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ $row->volte }} {{ $row->volte == 1 ? 'volta' : 'volte' }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-500 dark:text-gray-400">Nessuna.</li>
                    @endforelse
                </ul>
            </section>

            @foreach ([
                ['Risposte probabilmente sbagliate', 'Almeno 5 persone, e quasi tutte hanno detto «Non è questo». Controlla il riquadro «In breve» della pagina.', $suspects, 'correzione', 'manual-questions-suspects'],
                ['Domande imparate', 'Domande nuove (non simili a quelle di domande.yml) che più persone hanno trovato utili.', $learned, 'domanda', 'manual-questions-learned'],
                ['Correzioni imparate', 'Spostano un po\' (al massimo del 30%) il punteggio di una pagina per una domanda.', $corrections, 'correzione', 'manual-questions-corrections'],
            ] as [$title, $help, $rows, $type, $testid])
                <section class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 sm:p-6" data-testid="{{ $testid }}">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">{{ $title }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $help }}</p>
                    <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($rows as $row)
                            <li class="py-2 flex flex-wrap items-center justify-between gap-2 text-sm" wire:key="{{ $testid }}-{{ $row->id }}">
                                <div class="min-w-0">
                                    <p class="text-gray-800 dark:text-gray-100">
                                        «{{ $row->domanda ?? $row->chiave }}» →
                                        <a class="underline" href="{{ route('documentation.page', $row->slug) }}">{{ $titles[$row->slug] ?? $row->slug }}</a>
                                    </p>
                                    <p class="text-gray-500 dark:text-gray-400">
                                        {{ $row->voti }} voti da {{ $row->utenti }} persone
                                        @isset($row->correzione) · correzione {{ $row->correzione > 0 ? '+' : '' }}{{ round($row->correzione * 100) }}% · utili {{ round($row->positivi * 100) }}% @endisset
                                    </p>
                                </div>
                                @if ($row->disattivata)
                                    <span class="text-gray-500 dark:text-gray-400">Disattivata</span>
                                @else
                                    <button type="button" class="text-sm font-medium text-red-700 dark:text-red-400 hover:underline"
                                        wire:click="disable('{{ $type }}', @js($row->chiave), @js($row->slug))"
                                        wire:confirm="Disattivare? L'assistente smette subito di usarla e il ricalcolo notturno la salterà.">
                                        Disattiva
                                    </button>
                                @endif
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gray-500 dark:text-gray-400">Nessuna.</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</div>
