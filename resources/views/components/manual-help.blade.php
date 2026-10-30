{{-- Piccola «?» accanto a un elemento poco ovvio (2026-10-30, Fase 5 del
     manuale): apre la sezione giusta del manuale in una NUOVA SCHEDA, così
     il lavoro nella schermata (un modulo a metà, una selezione) non si
     perde. Un link, non un bottone: si raggiunge col Tab, si apre con
     Invio, e Ctrl/⌘+clic funziona come per ogni link.

     slug e anchor si scrivono come stringhe fisse: un test
     (ManualContextHelpTest) legge le view e controlla che la pagina e la
     sezione esistano, quindi rinominando uno slug o un titolo «##» del
     manuale il test fallisce invece di lasciare un link rotto.

     $label: l'argomento, letto dagli screen reader («Aiuto: Peso»). --}}
@props(['slug', 'anchor' => null, 'label'])

<a href="{{ route('documentation.page', $slug) }}{{ $anchor ? '#'.$anchor : '' }}"
   target="_blank" rel="noopener"
   {{ $attributes->merge(['class' => 'manual-context-help']) }}
   title="Aiuto: {{ $label }} (si apre il manuale in una nuova scheda)"
   aria-label="Aiuto: {{ $label }} (si apre il manuale in una nuova scheda)"
   data-manual-help="{{ $slug }}">?</a>
