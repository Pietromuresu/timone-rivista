<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            📖 Manuale utente
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            @if (! $available)
                {{-- Bug 3 (2026-10-12): manuale mancante → messaggio, non un
                     errore 500 (il dettaglio tecnico va nel log). --}}
                <div class="p-6 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg text-amber-800 dark:text-amber-300">
                    Il manuale utente non è disponibile in questo momento. Avvisa chi gestisce l'installazione: le pagine del manuale (cartella <code>resources/manuale</code>) non sono state trovate sul server.
                </div>
            @else
                {{-- Link vecchi del manuale a pagina unica (es. /manuale#9bis-…):
                     la parte dopo # non arriva al server, quindi la legge
                     questo script e porta alla pagina nuova (mappa in
                     App\Support\Manual\LegacyAnchors). Senza JavaScript si
                     resta qui, sulla pagina d'ingresso. --}}
                <script>
                    (function () {
                        var map = @json($legacyAnchors);
                        var hash = window.location.hash.slice(1);
                        if (! hash) return;
                        try { hash = decodeURIComponent(hash); } catch (e) {}
                        if (Object.prototype.hasOwnProperty.call(map, hash)) {
                            window.location.replace(@json(url('/manuale')) + '/' + map[hash]);
                        }
                    })();
                </script>

                @include('documentation.partials.search')

                {{-- Fase 3: domanda in parole proprie → «In breve» di una pagina. --}}
                <section class="manual-card manual-assistant-card">
                    @include('documentation.partials.assistant', ['prefix' => 'manual-ask', 'context' => null])
                    @if (auth()->user()?->isAdmin())
                        <p class="manual-help-footer"><a href="{{ route('documentation.questions') }}">Cosa chiedono gli utenti</a> (solo amministratori)</p>
                    @endif
                </section>

                <div class="manual-layout">
                    @include('documentation.partials.nav', ['categories' => $categories])

                    <div class="manual-main">
                        <section class="manual-card manuale-content" data-testid="manual-tasks" aria-labelledby="cosa-vuoi-fare-title">
                            <h1 id="cosa-vuoi-fare-title">Cosa vuoi fare?</h1>
                            {!! $tasks['html'] ?? '' !!}
                        </section>

                        <article class="manual-card manuale-content" data-testid="manual-start">
                            <h1>Inizia qui</h1>
                            {!! $start['html'] ?? '' !!}
                        </article>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($available)
        @include('documentation.partials.back-to-top')
    @endif
</x-app-layout>
