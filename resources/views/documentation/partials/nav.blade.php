{{-- Indice del manuale per categorie (2026-10-29), da ManualRepository:
     sempre allineato alle pagine, nessuna lista da tenere a mano. Su
     schermi larghi è una colonna fissa; su quelli stretti un <details>
     richiudibile, che parte chiuso (funziona anche senza JavaScript).

     «Mostrami le pagine per»: filtro per ruolo (Alpine manualNav, campo
     `ruoli` del front matter), predefinito sul ruolo di chi legge. Non
     nasconde nulla del tutto: «Mostra tutto» riporta le altre pagine; senza
     JavaScript si vedono tutte. --}}
@php
    $readerRole = auth()->user()?->role?->value ?? 'tutti';
    $roleLabels = ['tutti' => 'Tutti', 'redattore' => 'Redattore', 'commerciale' => 'Commerciale', 'admin' => 'Admin', 'sola_lettura' => 'Sola lettura'];
@endphp
<nav aria-label="Indice del manuale" class="manual-nav" x-data="manualNav(@js($readerRole))">
    <details class="manual-nav-details" open>
        <summary class="manual-nav-summary">Indice del manuale</summary>
        <div class="manual-nav-body">
            <label class="manual-nav-filter">
                <span>Mostrami le pagine per:</span>
                <select x-model="role" data-testid="manual-role-filter">
                    @foreach ($roleLabels as $value => $label)
                        <option value="{{ $value }}" @selected($value === $readerRole)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <a href="{{ route('documentation.show') }}" class="manual-nav-home {{ isset($current) ? '' : 'is-current' }}">📖 Pagina d'ingresso</a>
            @foreach ($categories as $category => $pages)
                <p class="manual-nav-category">{{ $category }}</p>
                <ul>
                    @foreach ($pages as $navPage)
                        <li data-ruoli="{{ implode(' ', $navPage->ruoli) }}" x-show="visible(@js(implode(' ', $navPage->ruoli)))">
                            <a href="{{ route('documentation.page', $navPage->slug) }}"
                               @class(['manual-nav-link', 'is-current' => ($current ?? null) === $navPage->slug])
                               @if (($current ?? null) === $navPage->slug) aria-current="page" @endif
                            >{{ $navPage->titolo }}</a>
                            @if (($current ?? null) === $navPage->slug && ! empty($toc))
                                {{-- «In questa pagina»: la sezione che si sta leggendo è evidenziata (manualToc). --}}
                                <ul class="manual-nav-toc" x-data="manualToc()" aria-label="In questa pagina">
                                    @foreach ($toc as $entry)
                                        @if ($entry['level'] === 2 && $entry['id'] !== 'vedi-anche')
                                            <li><a href="#{{ $entry['id'] }}" :class="current === @js($entry['id']) ? 'is-current' : ''">{{ $entry['text'] }}</a></li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endforeach

            <p x-show="hiddenCount() > 0" x-cloak class="manual-nav-more">
                <button type="button" x-on:click="showAll = true" data-testid="manual-show-all">Mostra tutto (<span x-text="hiddenCount()"></span> pagine per altri ruoli)</button>
            </p>
        </div>
    </details>
    {{-- Su schermi stretti l'indice parte chiuso (è un menu sopra la
         pagina, 46 voci): lo chiude questo script. Senza JavaScript resta
         aperto, quindi sempre raggiungibile. --}}
    <script>
        if (! window.matchMedia('(min-width: 1024px)').matches) {
            document.currentScript.parentElement.querySelector('details').open = false;
        }
    </script>
</nav>
