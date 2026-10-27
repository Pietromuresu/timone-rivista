{{-- Riempimento della pagina (Fase 3, 2026-10-27): quanto è piena a
     colpo d'occhio, in dodicesimi. Stato e testo da
     App\Support\PageCapacity (unica fonte: "Vuota", "Libero: 1/3",
     "Piena"), icona + testo + forma della barra, quindi leggibile anche
     senza distinguere i colori.
     $variant: 'card' o 'row' (stessa etichetta, dimensione diversa). --}}
@php
    $used = $page->occupiedTwelfths();
    $fill = \App\Support\PageCapacity::status($used);
    $label = \App\Support\PageCapacity::statusLabel($used);
@endphp
{{-- Fase 7a (2026-10-27): niente più barra laterale nella card — lo
     spazio libero si vede ora nella disposizione dei blocchi (riquadro
     tratteggiato "libero"); resta l'etichetta, in card e lista. --}}
<span
    data-testid="page-fill"
    data-fill="{{ $fill->value }}"
    title="Spazio occupato: {{ \App\Support\Twelfths::fractionLabel($used) }} di pagina"
    class="inline-flex items-center gap-0.5 rounded px-1 py-0 {{ $variant === 'card' ? 'text-[10px]' : 'text-xs shrink-0' }} whitespace-nowrap {{ $fill->badgeClasses() }}"
>{{ $fill->icon() }} {{ $label }}</span>
