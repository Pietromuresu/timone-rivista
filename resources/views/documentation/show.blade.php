<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            📖 Manuale utente
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if ($contentHtml === null)
                {{-- Bug 3 (2026-10-12): file mancante/illeggibile → messaggio,
                     non un errore 500 (il dettaglio tecnico va nel log). --}}
                <div class="p-6 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg text-amber-800 dark:text-amber-300">
                    Il manuale utente non è disponibile in questo momento. Avvisa chi gestisce l'installazione: il file <code>MANUALE_UTENTE.md</code> non è stato trovato sul server.
                </div>
            @else
                <div class="lg:flex lg:items-start lg:gap-6">
                    {{-- Indice generato dai titoli (App\Support\ManualRenderer):
                         sempre allineato al contenuto, nessuna lista da tenere
                         aggiornata a mano. Sticky su schermi larghi per
                         saltare tra le sezioni senza tornare in cima. --}}
                    @if (count($toc) > 0)
                        <nav aria-label="Indice del manuale" class="mb-6 lg:mb-0 lg:w-64 lg:shrink-0 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto p-4 bg-white dark:bg-gray-800 shadow sm:rounded-lg text-sm">
                            <p class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Indice</p>
                            <ul class="space-y-1">
                                @foreach ($toc as $entry)
                                    <li class="{{ $entry['level'] === 3 ? 'pl-4' : '' }}">
                                        <a href="#{{ $entry['id'] }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $entry['text'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    @endif

                    <div class="flex-1 min-w-0 p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                        {{-- Stile in resources/css/app.css (.manuale-content, @layer components) —
                             non in uno <style> qui: @apply non viene mai processato dentro un
                             .blade.php, solo nei file che passano dalla pipeline Tailwind/Vite.
                             {!! !!} è sicuro: ManualRenderer fa l'escape dell'HTML grezzo. --}}
                        <div class="manuale-content">
                            {!! $contentHtml !!}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
