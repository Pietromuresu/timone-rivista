{{-- Riempimento della pagina (Fase 3, 2026-10-27): quanto è piena a
     colpo d'occhio, in dodicesimi. Stato e testo da
     App\Support\PageCapacity (unica fonte: "Vuota", "Libero: 1/3",
     "Piena"), icona + testo + forma della barra, quindi leggibile anche
     senza distinguere i colori.
     $variant: 'card' (barra verticale sul bordo sinistro + etichetta),
     'row' (sola etichetta, la riga della lista è troppo bassa per una barra). --}}
@php
    $used = $page->occupiedTwelfths();
    $fill = \App\Support\PageCapacity::status($used);
    $label = \App\Support\PageCapacity::statusLabel($used);
    $percent = min(100, $used * 100 / \App\Support\Twelfths::PER_PAGE);
@endphp
@if ($variant === 'card')
    <div class="absolute left-0 inset-y-0 w-1.5 bg-black/10 dark:bg-white/10 pointer-events-none" aria-hidden="true">
        <div class="absolute bottom-0 inset-x-0 {{ $fill->barClasses() }}" style="height: {{ $percent }}%"></div>
    </div>
@endif
<span
    data-testid="page-fill"
    data-fill="{{ $fill->value }}"
    title="Spazio occupato: {{ \App\Support\Twelfths::fractionLabel($used) }} di pagina"
    class="inline-flex items-center gap-0.5 rounded px-1 py-0 {{ $variant === 'card' ? 'text-[10px]' : 'text-xs shrink-0' }} whitespace-nowrap {{ $fill->badgeClasses() }}"
>{{ $fill->icon() }} {{ $label }}</span>
