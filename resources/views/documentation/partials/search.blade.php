{{-- Ricerca nel manuale (2026-10-29): tutta nel browser (MiniSearch, Alpine
     manualSearch in resources/js/manual-search.js) sull'indice
     /manuale/indice.json; nessuna chiamata esterna. «/» o Ctrl/⌘+K portano
     qui; frecce per scorrere i risultati, Invio per aprire, Esc per chiudere.
     Senza JavaScript il campo non fa nulla: restano indice e riquadri. --}}
<div class="manual-search" x-data="manualSearch(@js(route('documentation.search-index')))" x-on:click.outside="close()" data-testid="manual-search">
    <label for="manual-search-input" class="manual-search-label">Cerca nel manuale</label>
    <div class="relative">
        <input
            id="manual-search-input"
            x-ref="input"
            type="search"
            autocomplete="off"
            placeholder="Scrivi cosa vuoi fare: «spostare una pagina», «ctrl z», «battente»…"
            class="manual-search-input"
            x-model="query"
            x-on:input.debounce.120ms="update()"
            x-on:focus="ensureEngine(); if (query) open = true"
            x-on:keydown.arrow-down.prevent="move(1)"
            x-on:keydown.arrow-up.prevent="move(-1)"
            x-on:keydown.enter.prevent="go()"
            x-on:keydown.escape="close(); $el.blur()"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="manual-search-results"
            :aria-expanded="open ? 'true' : 'false'"
            :aria-activedescendant="active >= 0 ? 'manual-result-' + active : null"
        />
        <kbd class="manual-search-kbd" aria-hidden="true">/</kbd>

        <div x-show="open" x-cloak class="manual-search-panel">
            <p x-show="loading" class="manual-search-note">Carico l'indice…</p>
            <p x-show="failed" class="manual-search-note">La ricerca non è disponibile in questo momento: usa l'indice del manuale.</p>

            <ul id="manual-search-results" x-ref="list" role="listbox" aria-label="Risultati della ricerca" x-show="results.length">
                <template x-for="(result, i) in results" :key="result.id">
                    <li
                        :id="'manual-result-' + i"
                        role="option"
                        :aria-selected="i === active ? 'true' : 'false'"
                        :class="i === active ? 'is-active' : ''"
                        x-on:mouseenter="active = i"
                        x-on:click="go(result)"
                        class="manual-search-result"
                    >
                        <span class="manual-search-result-title">
                            <span x-text="result.titolo"></span><template x-if="result.sezione"><span class="manual-search-result-section" x-text="' › ' + result.sezione"></span></template>
                        </span>
                        <span class="manual-search-result-snippet" x-html="result.html"></span>
                        <span class="manual-search-result-category" x-text="result.categoria"></span>
                    </li>
                </template>
            </ul>

            <div x-show="!loading && !failed && query.trim() !== '' && results.length === 0" class="manual-search-empty" data-testid="manual-search-empty">
                <p class="font-medium">Nessun risultato per «<span x-text="query"></span>».</p>
                <template x-if="tried.length">
                    <p>Prova con:
                        <template x-for="word in tried" :key="word">
                            <button type="button" class="manual-search-try" x-on:click="tryWord(word)" x-text="word"></button>
                        </template>
                    </p>
                </template>
                <p class="mt-2">Oppure parti da uno dei compiti più frequenti:</p>
                {{-- Scritti dal server (fissi): non dipendono dal caricamento dell'indice. --}}
                <ul class="manual-search-tasks">
                    @foreach (\App\Support\Manual\ManualSearchIndex::fromConfig()->tasks() as $task)
                        <li><a href="{{ $task['url'] }}">{{ $task['titolo'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
