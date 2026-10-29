{{-- «Torna su» (2026-10-29): compare dopo un po' di scorrimento; riporta in
     cima e mette il cursore nella ricerca. Nascosto in stampa (app.css). --}}
<div x-data="manualBackToTop()">
    <button type="button" x-show="shown" x-cloak x-transition.opacity x-on:click="top()" class="manual-back-to-top" title="Torna su" data-testid="manual-back-to-top">↑ Torna su</button>
</div>
