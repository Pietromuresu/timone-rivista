{{-- Bottone «?» e pannello laterale con l'assistente (2026-10-29).
     z-40: sopra il contenuto, sotto modali e avvisi (z-50). --}}
<div x-data="manualHelpButton" x-on:keydown.escape.window="hide()" data-testid="manual-help">
    <button type="button" x-ref="button" class="manual-help-button" x-on:click="open ? hide() : show()"
            aria-label="Chiedi al manuale" title="Chiedi al manuale" x-bind:aria-expanded="open" aria-controls="manual-help-panel"
            data-testid="manual-help-button">?</button>

    <div x-show="open" x-cloak class="manual-help-backdrop" x-on:click.self="hide()">
        <aside id="manual-help-panel" x-ref="panel" class="manual-help-panel" role="dialog" aria-modal="true" aria-labelledby="manual-help-title">
            <div class="manual-help-head">
                <h2 id="manual-help-title" class="manual-help-heading">Aiuto</h2>
                <button type="button" class="manual-help-close" x-on:click="hide()" aria-label="Chiudi">✕</button>
            </div>
            @include('documentation.partials.assistant', ['prefix' => 'manual-help', 'context' => $context])
            <p class="manual-help-footer"><a href="{{ route('documentation.show') }}">Apri il manuale completo</a></p>
        </aside>
    </div>
</div>
