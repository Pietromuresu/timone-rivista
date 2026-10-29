{{-- «Chiedi al manuale» (2026-10-29). La risposta è sempre il riquadro
     «In breve» di una pagina del manuale, scelto dal server
     (App\Support\Manual\ManualAssistantDesk): nessun testo generato.
     Parametri: $prefix (id unici: l'assistente può stare due volte nella
     stessa pagina, in linea e nel pannello del «?»), $context (schermata
     da cui si chiede, o null). Logica Alpine in resources/js/manual-assistant.js. --}}
<div class="manual-assistant"
     x-data="manualAssistant(@js([
        'askUrl' => route('documentation.assistant.ask'),
        'voteUrl' => route('documentation.assistant.vote'),
        'context' => $context,
     ]))"
     data-testid="manual-assistant">
    <form class="manual-assistant-form" x-on:submit.prevent="ask()">
        <label for="{{ $prefix }}-question" class="manual-search-label">Chiedi al manuale</label>
        <div class="manual-assistant-row">
            <input id="{{ $prefix }}-question" type="text" x-ref="question" x-model="question" maxlength="300" autocomplete="off"
                   class="manual-assistant-input" placeholder="Per esempio: come sposto una pagina?" data-testid="manual-assistant-input">
            <button type="submit" class="manual-assistant-submit" x-bind:disabled="loading">Chiedi</button>
        </div>
    </form>

    <div class="manual-assistant-quick" aria-label="Domande rapide">
        @foreach (\App\Support\Manual\ManualAssistant::QUICK_QUESTIONS as $quick)
            <button type="button" class="manual-assistant-chip" x-on:click="ask(@js($quick))">{{ \Illuminate\Support\Str::ucfirst($quick) }}</button>
        @endforeach
    </div>

    <div aria-live="polite" class="manual-assistant-output">
        <p x-show="loading" x-cloak class="manual-assistant-note" role="status">Cerco la risposta nel manuale…</p>
        <p x-show="error" x-cloak x-text="error" class="manual-assistant-error"></p>

        {{-- Una risposta --}}
        <template x-if="result && result.stato === 'risposta'">
            <article class="manual-assistant-answer" data-testid="manual-assistant-answer">
                <p class="manual-assistant-from">Dal manuale: <a x-bind:href="result.risposta.url" x-text="result.risposta.titolo" class="manual-assistant-title"></a></p>
                <div class="manuale-content manual-assistant-inbreve" x-html="result.risposta.in_breve"></div>
                <p><a x-bind:href="result.risposta.url" class="manual-assistant-more">Leggi tutto →</a></p>

                <div class="manual-assistant-feedback" x-show="!voted">
                    <span>Ti è stata utile?</span>
                    <button type="button" class="manual-assistant-vote" x-on:click="vote('su')" data-testid="manual-assistant-useful">👍 Utile</button>
                    <button type="button" class="manual-assistant-vote" x-on:click="vote('giu')" data-testid="manual-assistant-wrong">👎 Non è questo</button>
                </div>
                <p x-show="voted === 'su'" x-cloak class="manual-assistant-note">Grazie!</p>

                <div x-show="voted === 'giu'" x-cloak class="manual-assistant-alternatives">
                    <p class="manual-assistant-note" x-show="!chosen">Forse una di queste? Se è quella giusta, dillo: aiuta chi chiederà dopo.</p>
                    @include('documentation.partials.assistant-list')
                </div>
            </article>
        </template>

        {{-- Nessuna risposta sicura: tre pagine da scegliere --}}
        <template x-if="result && result.stato === 'forse'">
            <div class="manual-assistant-answer" data-testid="manual-assistant-maybe">
                <p class="manual-assistant-from">Non sono sicuro. Forse cercavi:</p>
                @include('documentation.partials.assistant-list')
            </div>
        </template>

        {{-- Niente --}}
        <template x-if="result && result.stato === 'niente'">
            <div class="manual-assistant-answer" data-testid="manual-assistant-nothing">
                <p>Non ho trovato una risposta nel manuale. Prova a chiederlo con altre parole, per esempio con il nome del bottone che vedi sullo schermo. Oppure parti da uno di questi compiti:</p>
                <ul class="manual-search-tasks">
                    <template x-for="task in result.compiti" x-bind:key="task.url">
                        <li><a x-bind:href="task.url" x-text="task.titolo"></a></li>
                    </template>
                    <li><a href="{{ route('documentation.show') }}">Tutto il manuale</a></li>
                </ul>
            </div>
        </template>
    </div>
</div>
